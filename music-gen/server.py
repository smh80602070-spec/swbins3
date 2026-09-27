# -*- coding: utf-8 -*-
"""
로컬 음악 생성 API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7862)로 요청을 보낸다.
상업 사용이 막힌 MusicGen(CC-BY-NC) 대신 ACE-Step 1.5(코드·가중치 모두 MIT)를 쓴다.

ACE-Step 은 자체 REST API 서버(acestep/api_server.py, 기본 포트 8001)를 갖고 있어서,
이 서버는 그 API 를 감싸는 얇은 어댑터다.
- 첫 요청 때 ACE-Step API 서버를 숨김 프로세스로 띄우고(/health 가 뜰 때까지 기다림),
- 작업을 넣고(/release_task) 끝날 때까지 기다린 뒤(/query_result) wav 를 받아(/v1/audio) 돌려준다.
- 일정 시간 미사용 시(IDLE_UNLOAD_SECONDS, 기본 5분) ACE-Step 프로세스를 통째로 끝내서
  GPU 메모리를 돌려준다(sd-webui·3d-gen 과 VRAM 을 나눠 쓰기 때문).

ACE-Step 설치 위치는 music-gen/ACE-Step-1.5 (ACESTEP_DIR 로 바꿀 수 있음). 설치법은 SETUP_GUIDE*.md 참고.
"""
import base64
import json
import os
import subprocess
import threading
import time
import urllib.error
import urllib.parse
import urllib.request

from flask import Flask, jsonify, request

HERE = os.path.dirname(os.path.abspath(__file__))
ACESTEP_DIR = os.environ.get("ACESTEP_DIR", os.path.join(HERE, "ACE-Step-1.5"))
ACESTEP_PORT = int(os.environ.get("ACESTEP_API_PORT", "8001"))
ACESTEP_BASE = f"http://127.0.0.1:{ACESTEP_PORT}"
ACESTEP_LOG = os.path.join(os.path.dirname(HERE), "logs", "acestep-api.log")
DIT_MODEL = os.environ.get("ACESTEP_CONFIG_PATH", "acestep-v15-turbo")
IDLE_UNLOAD_SECONDS = int(os.environ.get("IDLE_UNLOAD_SECONDS", "300"))
STARTUP_TIMEOUT_SECONDS = int(os.environ.get("ACESTEP_STARTUP_TIMEOUT", "600"))
GENERATE_TIMEOUT_SECONDS = int(os.environ.get("ACESTEP_GENERATE_TIMEOUT", "900"))

# 결과물마다 붙여 주는 출처 기록. 게임에 넣을 때 어떤 모델·라이선스로 만들었는지 답할 수 있게 한다.
PROVENANCE = {
    "tool": "music-gen",
    "model": f"ACE-Step/Ace-Step1.5 ({DIT_MODEL})",
    "license": "MIT (code and model weights)",
    "license_url": "https://huggingface.co/ACE-Step/Ace-Step1.5",
    "commercial_use": True,
    "note": "ACE-Step 은 기존 곡과 비슷하게 나올 수 있다고 경고한다 — 특정 곡·가수를 흉내 내는 프롬프트는 쓰지 않는다.",
}

app = Flask(__name__)
_lock = threading.Lock()
_state = {"proc": None, "last_used": 0.0}


def _acestep_python():
    """ACE-Step 을 설치한 가상환경의 python.exe. AMD 는 venv_rocm, uv 설치는 .venv."""
    override = os.environ.get("ACESTEP_PYTHON")
    if override:
        return override, False
    for venv, is_rocm in (("venv_rocm", True), (".venv", False), ("venv", False)):
        exe = os.path.join(ACESTEP_DIR, venv, "Scripts", "python.exe")
        if os.path.isfile(exe):
            return exe, is_rocm
    return None, False


def _acestep_env(is_rocm):
    env = dict(os.environ)
    env.setdefault("ACESTEP_CONFIG_PATH", DIT_MODEL)
    # VRAM 8GB 이하에서는 2B turbo + 0.6B LM 조합이 권장값(ACE-Step README).
    env.setdefault("ACESTEP_LM_MODEL_PATH", "acestep-5Hz-lm-0.6B")
    env.setdefault("ACESTEP_OFFLOAD_TO_CPU", "true")
    env.setdefault("TOKENIZERS_PARALLELISM", "false")
    env["PYTHONIOENCODING"] = "utf-8"
    if is_rocm:
        # start_api_server_rocm.bat 이 넣는 값과 같다. 단 GFX 버전은 RX 7600 = 11.0.2
        # (그 bat 은 11.0.0 으로 고정돼 있고, 업데이트 확인에서 Y/N 입력을 기다리며 멈추므로 직접 띄운다).
        env.setdefault("ACESTEP_LM_BACKEND", "pt")
        env.setdefault("HSA_OVERRIDE_GFX_VERSION", "11.0.2")
        env.setdefault("TORCH_COMPILE_BACKEND", "eager")
        env.setdefault("MIOPEN_FIND_MODE", "FAST")
        # ACE-Step 은 ROCm 에서 기본 float32 로 올려 2B 모델만 ~8GB 를 먹고 공유 메모리로 넘친다.
        # RDNA3(RX 7600)에서 bfloat16 으로 두면 ~5GB 로 줄어 30초 곡이 107초 → 18초(2026-09-28 측정).
        env.setdefault("ACESTEP_ROCM_DTYPE", "bfloat16")
    return env


def _api(method, path, payload=None, timeout=30):
    data = None
    headers = {}
    if payload is not None:
        data = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(ACESTEP_BASE + path, data=data, headers=headers, method=method)
    with urllib.request.urlopen(req, timeout=timeout) as res:
        return res.read()


def _api_json(method, path, payload=None, timeout=30):
    body = json.loads(_api(method, path, payload, timeout).decode("utf-8"))
    if body.get("code") not in (None, 200) or body.get("error"):
        raise RuntimeError(f"ACE-Step API 오류({path}): {body.get('error')}")
    return body.get("data")


def _is_up():
    try:
        _api_json("GET", "/health", timeout=3)
        return True
    except (urllib.error.URLError, OSError, ValueError, RuntimeError):
        return False


def ensure_acestep():
    """ACE-Step API 서버가 떠 있게 한다. 이미 (다른 곳에서) 떠 있으면 그걸 쓴다."""
    with _lock:
        _state["last_used"] = time.time()
        if _is_up():
            return
        if _state["proc"] is None or _state["proc"].poll() is not None:
            python, is_rocm = _acestep_python()
            if python is None:
                raise RuntimeError(
                    f"ACE-Step 이 설치돼 있지 않습니다({ACESTEP_DIR}). SETUP_GUIDE 의 음악(ACE-Step 1.5) 설치 절을 따라 주세요.")
            os.makedirs(os.path.dirname(ACESTEP_LOG), exist_ok=True)
            log = open(ACESTEP_LOG, "ab")
            _state["proc"] = subprocess.Popen(
                # acestep_launch.py 가 ROCm 윈도우 빌드의 torch.distributed 공백을 메운 뒤 acestep.api_server 를 실행한다.
                [python, "-u", os.path.join(HERE, "acestep_launch.py"),
                 "--host", "127.0.0.1", "--port", str(ACESTEP_PORT)],
                cwd=ACESTEP_DIR, env=_acestep_env(is_rocm),
                stdin=subprocess.DEVNULL, stdout=log, stderr=subprocess.STDOUT,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
            )
        deadline = time.time() + STARTUP_TIMEOUT_SECONDS
        while time.time() < deadline:
            if _state["proc"].poll() is not None:
                raise RuntimeError(f"ACE-Step API 서버가 시작하다 종료됐습니다. 로그: {ACESTEP_LOG}")
            if _is_up():
                return
            time.sleep(2)
        raise RuntimeError(f"ACE-Step API 서버가 {STARTUP_TIMEOUT_SECONDS}초 안에 뜨지 않았습니다. 로그: {ACESTEP_LOG}")


def _stop_acestep():
    with _lock:
        proc = _state["proc"]
        _state["proc"] = None
    if proc is not None and proc.poll() is None:
        # 자식 프로세스까지 한꺼번에 끝내야 GPU 메모리가 확실히 풀린다.
        subprocess.run(["taskkill", "/PID", str(proc.pid), "/T", "/F"],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                       creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))


def _idle_unload_watcher():
    while True:
        time.sleep(30)
        if _state["proc"] is not None and \
                time.time() - _state["last_used"] >= IDLE_UNLOAD_SECONDS:
            _stop_acestep()


threading.Thread(target=_idle_unload_watcher, daemon=True).start()


def _remove_acestep_output(audio_url):
    """ACE-Step 은 만든 곡을 자기 임시 폴더에 남겨 두므로(2분 곡 ≈ 23MB) 받아 온 뒤 지운다."""
    query = urllib.parse.urlparse(audio_url).query
    file_path = urllib.parse.parse_qs(query).get("path", [""])[0]
    real = os.path.realpath(file_path)
    if file_path and real.startswith(os.path.realpath(ACESTEP_DIR) + os.sep) and os.path.isfile(real):
        try:
            os.remove(real)
        except OSError:
            app.logger.warning("ACE-Step 임시 파일 삭제 실패: %s", real)


def _generate_wav(prompt, lyrics, duration):
    task = _api_json("POST", "/release_task", {
        "prompt": prompt,
        "lyrics": lyrics,
        "audio_duration": duration,
        "audio_format": "wav",
        "batch_size": 1,
        "thinking": False,
    })
    task_id = task["task_id"]
    deadline = time.time() + GENERATE_TIMEOUT_SECONDS
    while time.time() < deadline:
        time.sleep(2)
        _state["last_used"] = time.time()
        items = _api_json("POST", "/query_result", {"task_id_list": [task_id]})
        item = items[0] if items else {}
        if item.get("status") == 2:
            raise RuntimeError(f"ACE-Step 생성 실패: {item.get('result') or item}")
        if item.get("status") != 1:
            continue
        results = item.get("result")
        if isinstance(results, str):
            results = json.loads(results)
        file_url = results[0]["file"]
        # 보통 "/v1/audio?path=..." 로 오지만, 파일 경로 그대로 오는 경우도 있다.
        path = file_url if file_url.startswith("/v1/") else "/v1/audio?path=" + urllib.parse.quote(file_url)
        meta = {k: results[0].get(k) for k in ("metas", "seed_value", "dit_model", "lm_model")}
        wav = _api("GET", path, timeout=120)
        _remove_acestep_output(path)
        return wav, meta
    raise RuntimeError(f"{GENERATE_TIMEOUT_SECONDS}초 안에 생성이 끝나지 않았습니다.")


@app.post("/generate")
def generate():
    body = request.get_json(silent=True) or {}
    prompt = (body.get("prompt") or "").strip()
    lyrics = (body.get("lyrics") or "").strip() or "[Instrumental]"
    duration = max(10, min(240, int(body.get("duration") or 30)))

    if not prompt:
        return jsonify(ok=False, error="프롬프트를 입력해 주세요."), 400

    try:
        ensure_acestep()
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("ACE-Step 시작 실패")
        return jsonify(ok=False, error=str(exc)), 500

    try:
        wav, meta = _generate_wav(prompt, lyrics, duration)
        _state["last_used"] = time.time()
        provenance = dict(PROVENANCE, created_at=time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                          params={"prompt": prompt, "lyrics": lyrics, "duration": duration}, result=meta)
        return jsonify(ok=True, audio=base64.b64encode(wav).decode("ascii"), provenance=provenance)
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("생성 실패")
        return jsonify(ok=False, error=f"생성 실패: {exc}"), 500


@app.get("/health")
def health():
    running = _state["proc"] is not None and _state["proc"].poll() is None
    return jsonify(ok=True, model=PROVENANCE["model"], installed=_acestep_python()[0] is not None,
                   model_loaded=running)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=7862, threaded=True)
