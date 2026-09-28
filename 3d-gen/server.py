# -*- coding: utf-8 -*-
"""
로컬 Shap-E(OpenAI) 텍스트/이미지→3D 생성 API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7864)로 요청을 보낸다.
프롬프트 또는 참고 이미지로 3D 메시를 생성해 glTF 바이너리(.glb)로 반환한다. Godot·Unity·웹(three.js 등)에 바로 임포트 가능.
VRAM이 넉넉하지 않아(sd-webui·music-gen·voice-gen과 동시에 켜두는 경우가 많음) 일정 시간
미사용 시 자동으로 GPU 메모리에서 내린다(IDLE_UNLOAD_SECONDS, 기본 5분).
"""
import base64
import gc
import io
import os
import tempfile
import threading
import time

import torch
import trimesh
from flask import Flask, jsonify, request
from PIL import Image

from shap_e.diffusion.gaussian_diffusion import diffusion_from_config
from shap_e.diffusion.sample import sample_latents
from shap_e.models.download import load_config, load_model
from shap_e.util.notebooks import decode_latent_mesh

DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
IDLE_UNLOAD_SECONDS = int(os.environ.get("IDLE_UNLOAD_SECONDS", "300"))

# 결과물마다 붙여 주는 출처 기록(음악·음성 서버와 같은 모양). 게임에 넣을 때 어떤 모델·라이선스로 만들었는지 답할 수 있게 한다.
PROVENANCE = {
    "tool": "3d-gen",
    "model": "openai/shap-e (text300M / image300M + transmitter)",
    "license": "MIT",
    "license_url": "https://github.com/openai/shap-e/blob/main/LICENSE",
    "commercial_use": True,
}

app = Flask(__name__)
_lock = threading.Lock()
_state = {"xm": None, "text_model": None, "image_model": None, "diffusion": None, "last_used": 0.0}


def get_transmitter_and_diffusion():
    with _lock:
        if _state["xm"] is None:
            _state["xm"] = load_model("transmitter", device=DEVICE)
            _state["diffusion"] = diffusion_from_config(load_config("diffusion"))
        _state["last_used"] = time.time()
        return _state["xm"], _state["diffusion"]


def get_text_model():
    with _lock:
        if _state["text_model"] is None:
            _state["text_model"] = load_model("text300M", device=DEVICE)
        _state["last_used"] = time.time()
        return _state["text_model"]


def get_image_model():
    with _lock:
        if _state["image_model"] is None:
            _state["image_model"] = load_model("image300M", device=DEVICE)
        _state["last_used"] = time.time()
        return _state["image_model"]


def _unload_models():
    with _lock:
        if _state["xm"] is None and _state["text_model"] is None and _state["image_model"] is None:
            return
        _state["xm"] = None
        _state["diffusion"] = None
        _state["text_model"] = None
        _state["image_model"] = None
    gc.collect()
    if DEVICE == "cuda":
        torch.cuda.empty_cache()


def _idle_unload_watcher():
    while True:
        time.sleep(30)
        any_loaded = _state["xm"] is not None or _state["text_model"] is not None or _state["image_model"] is not None
        if any_loaded and time.time() - _state["last_used"] >= IDLE_UNLOAD_SECONDS:
            _unload_models()


threading.Thread(target=_idle_unload_watcher, daemon=True).start()


@app.post("/generate")
def generate():
    body = request.get_json(silent=True) or {}
    prompt = (body.get("prompt") or "").strip()
    image_b64 = body.get("image")
    steps = max(16, min(128, int(body.get("steps") or 64)))
    guidance_scale = float(body.get("guidance_scale") or 15.0)

    if not prompt and not image_b64:
        return jsonify(ok=False, error="프롬프트를 입력하거나 참고 이미지를 첨부해 주세요."), 400

    try:
        xm, diffusion = get_transmitter_and_diffusion()
        if image_b64:
            model = get_image_model()
            image = Image.open(io.BytesIO(base64.b64decode(image_b64))).convert("RGB")
            model_kwargs = dict(images=[image])
        else:
            model = get_text_model()
            model_kwargs = dict(texts=[prompt])
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("모델 로드 실패")
        return jsonify(ok=False, error=f"모델 로드 실패: {exc}"), 500

    try:
        latents = sample_latents(
            batch_size=1,
            model=model,
            diffusion=diffusion,
            guidance_scale=guidance_scale,
            model_kwargs=model_kwargs,
            progress=False,
            clip_denoised=True,
            use_fp16=(DEVICE == "cuda"),
            use_karras=True,
            karras_steps=steps,
            sigma_min=1e-3,
            sigma_max=160,
            s_churn=0,
        )
        mesh = decode_latent_mesh(xm, latents[0]).tri_mesh()

        with tempfile.TemporaryDirectory() as tmp:
            obj_path = os.path.join(tmp, "mesh.obj")
            with open(obj_path, "w", encoding="utf-8") as f:
                mesh.write_obj(f)
            scene = trimesh.load(obj_path, force="mesh")
            glb_bytes = scene.export(file_type="glb")

        _state["last_used"] = time.time()
        provenance = dict(PROVENANCE, created_at=time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                          params={"prompt": prompt or None, "from_image": bool(image_b64),
                                  "steps": steps, "guidance_scale": guidance_scale})
        return jsonify(ok=True, model=base64.b64encode(glb_bytes).decode("ascii"), provenance=provenance)
    except torch.cuda.OutOfMemoryError:
        app.logger.exception("GPU 메모리 부족")
        return jsonify(ok=False, error="GPU 메모리가 부족합니다. 잠시 후 다시 시도해 주세요."), 500
    except Exception as exc:  # noqa: BLE001
        app.logger.exception("생성 실패")
        return jsonify(ok=False, error=f"생성 실패: {exc}"), 500


@app.get("/health")
def health():
    return jsonify(ok=True, device=DEVICE, model_loaded=_state["xm"] is not None)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=7864)
