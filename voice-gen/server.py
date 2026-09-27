# -*- coding: utf-8 -*-
"""
로컬 Supertonic 3 음성 생성 API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7863)로 요청을 보낸다.
상업 사용이 막힌 Coqui XTTS-v2 대신 Supertonic 3(가중치 OpenRAIL-M, 코드 MIT)를 쓴다.
정해진 목소리 10종(M1~M5 남성, F1~F5 여성) 가운데 하나를 고른다 — 참조 음성(목소리 흉내)은 없다.
ONNX Runtime 으로 CPU 에서 돌아가므로 그래픽카드(NVIDIA·AMD)와 상관없이 동작한다.
메모리를 아끼려고 일정 시간 미사용 시 모델을 내린다(IDLE_UNLOAD_SECONDS, 기본 5분).
"""
import base64
import gc
import io
import os
import threading
import time

import soundfile
from flask import Flask, jsonify, request
from supertonic import TTS
from supertonic.config import MODEL_CONFIGS

MODEL_NAME = "supertonic-3"
MODEL_DIR = os.path.join(os.path.dirname(__file__), "models", "supertonic3")
VOICES = ["M1", "M2", "M3", "M4", "M5", "F1", "F2", "F3", "F4", "F5"]
LANGUAGES = ["ko", "en", "ja"]
IDLE_UNLOAD_SECONDS = int(os.environ.get("IDLE_UNLOAD_SECONDS", "300"))

# 결과물마다 붙여 주는 출처 기록. 게임에 넣을 때 어떤 모델·라이선스로 만들었는지 답할 수 있게 한다.
PROVENANCE = {
    "tool": "voice-gen",
    "model": "Supertone/supertonic-3",
    "model_revision": MODEL_CONFIGS[MODEL_NAME]["revision"],
    "license": "OpenRAIL-M (model weights), MIT (code)",
    "license_url": "https://huggingface.co/Supertone/supertonic-3/blob/main/LICENSE",
    "commercial_use": True,
    "note": "OpenRAIL-M Attachment A 의 금지 용도(남을 해치거나 속이는 용도 등)에는 쓸 수 없다.",
}

app = Flask(__name__)
_lock = threading.Lock()
_state = {"tts": None, "last_used": 0.0}


def get_tts():
    with _lock:
        if _state["tts"] is None:
            # 모델이 폴더에 없으면 huggingface.co 에서 한 번 받아 MODEL_DIR 에 저장한다(약 400MB).
            _state["tts"] = TTS(model=MODEL_NAME, model_dir=MODEL_DIR, auto_download=True)
        _state["last_used"] = time.time()
        return _state["tts"]


def _unload_tts():
    with _lock:
        if _state["tts"] is None:
            return
        _state["tts"] = None
    gc.collect()


def _idle_unload_watcher():
    while True:
        time.sleep(30)
        if _state["tts"] is not None and \
                time.time() - _state["last_used"] >= IDLE_UNLOAD_SECONDS:
            _unload_tts()


threading.Thread(target=_idle_unload_watcher, daemon=True).start()


@app.post("/generate")
def generate():
    body = request.get_json(silent=True) or {}
    text = (body.get("text") or "").strip()
    language = (body.get("language") or "ko").strip()
    voice = (body.get("voice") or "F1").strip()
    speed = max(0.7, min(2.0, float(body.get("speed") or 1.05)))

    if not text:
        return jsonify(ok=False, error="텍스트를 입력해 주세요."), 400
    if language not in LANGUAGES:
        return jsonify(ok=False, error=f"지원하지 않는 언어입니다: {language}"), 400
    if voice not in VOICES:
        return jsonify(ok=False, error=f"없는 목소리입니다: {voice}"), 400

    try:
        tts = get_tts()
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("모델 로드 실패")
        return jsonify(ok=False, error=f"모델 로드 실패: {exc}"), 500

    try:
        style = tts.get_voice_style(voice_name=voice)
        wav, _duration = tts.synthesize(text, voice_style=style, lang=language, speed=speed)
        buf = io.BytesIO()
        soundfile.write(buf, wav.squeeze(), tts.sample_rate, format="WAV")
        audio_b64 = base64.b64encode(buf.getvalue()).decode("ascii")
        _state["last_used"] = time.time()
        provenance = dict(PROVENANCE, created_at=time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                          params={"text": text, "language": language, "voice": voice, "speed": speed})
        return jsonify(ok=True, audio=audio_b64, provenance=provenance)
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("생성 실패")
        return jsonify(ok=False, error=f"생성 실패: {exc}"), 500


@app.get("/health")
def health():
    return jsonify(ok=True, device="cpu", model=MODEL_NAME, model_loaded=_state["tts"] is not None)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=7863)
