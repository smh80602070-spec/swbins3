# -*- coding: utf-8 -*-
"""
⚠️ 점검 중(2026-09-28): 이 PC(RX 7600)에서 글자 프롬프트만으로 만든 영상이 뭉개져 나와 허브에서 꺼 두었다
(참고 이미지 → 영상은 그림이 나옴). diffusers 0.35.2/0.30.3·fp16/fp32·GPU/CPU 모두 같은 증상 — COMMERCIAL_SWAP_TODO.md 순서 7.

로컬 동영상(AnimateDiff) 생성 API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7865)로 요청을 보낸다.

예전에는 sd-webui 의 AnimateDiff 확장(continue-revolution/sd-webui-animatediff)을 썼는데, 그 확장 코드가
CC BY-NC-SA 4.0(비상업)이라 상업용 결과물을 만들 수 없었다. 여기서는 diffusers(Apache-2.0)의 AnimateDiff 파이프라인으로
같은 일을 한다. 모델: SD 1.5(CreativeML OpenRAIL-M) + 모션 어댑터 v1-5-2(guoyww/AnimateDiff, Apache-2.0).

- 참고 이미지가 오면 그 이미지를 멈춘 영상처럼 늘어놓고 video-to-video 로 움직임을 입힌다.
- /progress 로 현재 스텝을 알려 준다(허브 화면이 직접 읽음 — 허브의 PHP 서버는 요청을 하나씩만 처리하기 때문).
- VRAM 을 sd-webui·music-gen 과 나눠 쓰므로 일정 시간 미사용 시 모델을 내린다(IDLE_UNLOAD_SECONDS, 기본 5분).
"""
import base64
import gc
import io
import os
import tempfile
import threading
import time

import torch
from diffusers import AnimateDiffPipeline, AnimateDiffVideoToVideoPipeline, DDIMScheduler, MotionAdapter
from diffusers.models.attention_processor import AttnProcessor, SlicedAttnProcessor
from diffusers.utils import export_to_video
from flask import Flask, jsonify, request
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
SD15_DIR = os.path.join(HERE, "models", "sd15")
ADAPTER_DIR = os.path.join(HERE, "models", "motion-adapter-v1-5-2")
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
DTYPE = torch.float16 if DEVICE == "cuda" else torch.float32
IDLE_UNLOAD_SECONDS = int(os.environ.get("IDLE_UNLOAD_SECONDS", "300"))
# 공간 어텐션을 한 번에 계산할 (프레임×CFG×헤드) 묶음 수. 384px 에서 16 이면 행렬 하나가 약 170MB.
ATTN_SLICE = int(os.environ.get("ATTN_SLICE", "16"))
HUB_ORIGIN = os.environ.get("AIHUB_ORIGIN", "http://127.0.0.1:8611")

ROCM = bool(getattr(torch.version, "hip", None))
if ROCM:
    # ROCm 7.2 윈도우 빌드의 MIOpen 은 SD UNet 합성곱에서 아주 느린 대체 방식으로 떨어진다
    # (sd-webui 에서 512px 한 장 136초 → 끄면 5초, SETUP_GUIDE_RADEON.md 참고).
    torch.backends.cudnn.enabled = False

# 결과물마다 붙여 주는 출처 기록. 게임에 넣을 때 어떤 모델·라이선스로 만들었는지 답할 수 있게 한다.
PROVENANCE = {
    "tool": "video-gen (diffusers AnimateDiff)",
    "model": "stable-diffusion-v1-5/stable-diffusion-v1-5",
    "license": "CreativeML OpenRAIL-M",
    "license_url": "https://huggingface.co/stable-diffusion-v1-5/stable-diffusion-v1-5",
    "commercial_use": True,
    "motion_module": {
        "model": "guoyww/animatediff-motion-adapter-v1-5-2",
        "license": "Apache-2.0 (guoyww/AnimateDiff)",
        "license_url": "https://github.com/guoyww/AnimateDiff/blob/main/LICENSE.txt",
        "commercial_use": True,
    },
    "software": "huggingface/diffusers (Apache-2.0)",
    "note": "OpenRAIL-M Attachment A 의 금지 용도에는 쓸 수 없다.",
}

app = Flask(__name__)
_lock = threading.Lock()
_state = {"t2v": None, "v2v": None, "last_used": 0.0}
_progress = {"step": 0, "steps": 0, "busy": False}


def get_pipelines():
    with _lock:
        if _state["t2v"] is None:
            adapter = MotionAdapter.from_pretrained(ADAPTER_DIR, torch_dtype=DTYPE, variant="fp16")
            t2v = AnimateDiffPipeline.from_pretrained(SD15_DIR, motion_adapter=adapter, torch_dtype=DTYPE, variant="fp16")
            # diffusers AnimateDiff 문서의 권장 스케줄러 설정.
            t2v.scheduler = DDIMScheduler.from_config(
                t2v.scheduler.config, beta_schedule="linear", clip_sample=False,
                timestep_spacing="linspace", steps_offset=1)
            t2v.enable_vae_slicing()
            if ROCM:
                # 이 빌드에는 메모리를 아끼는 SDPA 커널이 없어 16프레임 어텐션이 매우 느리다(RX 7600 측정:
                # SDPA 4381ms, baddbmm 198ms). 그래서 baddbmm 방식의 고전 프로세서를 쓰되,
                # 공간 어텐션(토큰 수천 개)은 행렬이 VRAM 을 넘지 않게 나눠 계산하고(SlicedAttnProcessor),
                # 모션 모듈의 시간 어텐션(토큰 16개, 묶음이 수만 개)은 나누면 반복이 너무 많아 그대로 둔다.
                t2v.unet.set_attn_processor({
                    name: AttnProcessor() if "motion_modules" in name else SlicedAttnProcessor(ATTN_SLICE)
                    for name in t2v.unet.attn_processors})
                t2v.vae.set_attn_processor(SlicedAttnProcessor(1))
            t2v.to(DEVICE)
            _state["t2v"] = t2v
            # from_pipe() 로 만들면 UNet 을 공유하는데도 GPU 메모리가 3.8GB → 7.6GB 로 두 배가 되어(diffusers 0.35.2,
            # RX 7600 측정) 공유 메모리로 넘치고 16프레임 한 스텝이 85초까지 느려졌다. 구성 요소를 그대로 넘기면 늘지 않는다.
            _state["v2v"] = AnimateDiffVideoToVideoPipeline(**t2v.components)
        _state["last_used"] = time.time()
        return _state["t2v"], _state["v2v"]


def _unload():
    with _lock:
        if _state["t2v"] is None:
            return
        _state["t2v"] = None
        _state["v2v"] = None
    gc.collect()
    if DEVICE == "cuda":
        torch.cuda.empty_cache()


def _idle_unload_watcher():
    while True:
        time.sleep(30)
        if _state["t2v"] is not None and not _progress["busy"] and \
                time.time() - _state["last_used"] >= IDLE_UNLOAD_SECONDS:
            _unload()


threading.Thread(target=_idle_unload_watcher, daemon=True).start()


@app.after_request
def _cors(response):
    response.headers["Access-Control-Allow-Origin"] = HUB_ORIGIN
    return response


def _on_step_end(pipe, step, timestep, callback_kwargs):
    _progress["step"] = step + 1
    return callback_kwargs


@app.post("/generate")
def generate():
    body = request.get_json(silent=True) or {}
    prompt = (body.get("prompt") or "").strip()
    negative = (body.get("negative_prompt") or "").strip()
    size = max(256, min(512, int(body.get("width") or 384))) // 8 * 8
    frames = max(8, min(32, int(body.get("video_length") or 16)))
    fps = max(4, min(16, int(body.get("fps") or 8)))
    steps = max(4, min(50, int(body.get("steps") or 20)))
    seed = int(body.get("seed") if body.get("seed") not in (None, "") else -1)
    init_image = body.get("init_image")
    strength = max(0.1, min(1.0, float(body.get("denoising_strength") or 0.6)))

    if not prompt:
        return jsonify(ok=False, error="프롬프트를 입력해 주세요."), 400

    try:
        t2v, v2v = get_pipelines()
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("모델 로드 실패")
        return jsonify(ok=False, error=f"모델 로드 실패: {exc}"), 500

    if seed < 0:
        seed = int(torch.randint(0, 2**31 - 1, (1,)).item())
    generator = torch.Generator("cpu").manual_seed(seed)
    common = dict(prompt=prompt, negative_prompt=negative or None, guidance_scale=7.5,
                  num_inference_steps=steps, generator=generator, callback_on_step_end=_on_step_end)

    _progress.update(step=0, steps=steps, busy=True)
    try:
        with torch.inference_mode():
            if init_image:
                image = Image.open(io.BytesIO(base64.b64decode(init_image))).convert("RGB").resize((size, size))
                # video-to-video 는 strength 만큼만 스텝을 돈다.
                _progress["steps"] = max(1, int(steps * strength))
                result = v2v(video=[image] * frames, strength=strength, **common)
            else:
                result = t2v(width=size, height=size, num_frames=frames, **common)
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "out.mp4")
            export_to_video(result.frames[0], path, fps=fps)
            with open(path, "rb") as f:
                video_b64 = base64.b64encode(f.read()).decode("ascii")
        _state["last_used"] = time.time()
        provenance = dict(PROVENANCE, created_at=time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                          params={"prompt": prompt, "negative_prompt": negative or None, "seed": seed,
                                  "steps": steps, "size": size, "frames": frames, "fps": fps,
                                  "from_image": bool(init_image),
                                  "strength": strength if init_image else None})
        return jsonify(ok=True, video=video_b64, provenance=provenance)
    except torch.cuda.OutOfMemoryError:
        app.logger.exception("GPU 메모리 부족")
        return jsonify(ok=False, error="GPU 메모리가 부족합니다. 크기나 프레임 수를 줄여서 다시 시도해 주세요."), 500
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("생성 실패")
        return jsonify(ok=False, error=f"생성 실패: {exc}"), 500
    finally:
        _progress["busy"] = False


@app.get("/progress")
def progress():
    total = _progress["steps"] or 1
    return jsonify(ok=True, busy=_progress["busy"],
                   progress=min(1.0, _progress["step"] / total) if _progress["busy"] else 0.0)


@app.get("/health")
def health():
    return jsonify(ok=True, device=DEVICE, model_loaded=_state["t2v"] is not None)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=7865, threaded=True)
