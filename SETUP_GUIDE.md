# 로컬 AI 생성 도구 — 설치·설정 안내 (2026-10-07 리뉴얼)

이 저장소의 AI 도구는 **saga(`C:\swbins`) 자체툴**이 배치로 부르는 백엔드다. 사람용 웹 허브·일괄 켜기·3D·동영상·코딩 에이전트는
2026-10-07 에 걷어냈다(안 쓰거나 이 PC 에서 못 돎). 남은 것:

| 폴더 | 하는 일 | 모델(라이선스) | 부르는 쪽(`C:\swbins`) |
|---|---|---|---|
| `comfyui/` (+ `comfyui-saga/`) | 그림 — ComfyUI API, 포트 8188 | Z-Image-Turbo(Apache-2.0) · Illustrious XL v2.0 · Animagine XL 4.0 Opt(OpenRAIL) | `tools/ai-art/gen.py`, `start_sd.ps1`/`stop_sd.ps1`, 판정기 `tools/asset-audit/judge` |
| `music-gen/` | 배경음 — ACE-Step 1.5 | MIT | `music-gen/batch_saga.py`(K 티켓이 직접 실행) |
| `voice-gen/` | 음성 — Supertonic 3(고정 10 목소리) + Qwen3-TTS 시험 | OpenRAIL-M / Apache-2.0 | `voice-gen/batch_saga.py` · `qwen3_trial.py` |
| `sfx-gen/` | 효과음 — MOSS-SoundEffect v2 시험 | Apache-2.0 | `sfx-gen/moss_trial.py` |
| `judge-models/` · `populate-clip-cache.py` | 판정기 미적 점수 가중치 · CLIP 토크나이저 오프라인 등록 | — | `tools/asset-audit/judge/judge.py` |

라이선스 판정·금지 목록은 `COMMERCIAL_SWAP_TODO.md`. 화면 녹화 도구(`rec.ps1`·`web/`)는 `README.md`(별개).

## 0. 이 PC

- Windows 11 · **AMD Radeon RX 7600 (8GB)** · RAM 32GB · Python 3.12(`py -3.12`; `python` 은 스토어 껍데기라 쓰지 않는다).
- GPU 는 **AMD ROCm 7.2 for Windows** PyTorch 휠(`torch 2.9.1+rocmsdk20260116`, cp312 전용). 모든 venv 가 같은 휠 셋을 쓴다:

```powershell
# ROCm 휠 셋 — 각 venv 에 이 순서로(약 4GB, 불안정한 망에서는 --retries 10 --timeout 60)
<venv>\Scripts\python.exe -m pip install `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_core-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_devel-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_libraries_custom-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm-7.2.0.dev0.tar.gz
<venv>\Scripts\python.exe -m pip install `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torch-2.9.1+rocmsdk20260116-cp312-cp312-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torchvision-0.24.1+rocmsdk20260116-cp312-cp312-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torchaudio-2.9.1+rocmsdk20260116-cp312-cp312-win_amd64.whl
<venv>\Scripts\python.exe -c "import torch; print(torch.cuda.is_available(), torch.cuda.get_device_name(0))"   # True AMD Radeon RX 7600
```

- 이 빌드의 함정(전부 코드가 메워 둠): `torch.distributed` 가 없다(ACE-Step 은 `acestep_launch.py` 가 대체, 다른 모델은 `sdpa` 어텐션) ·
  MIOpen 합성곱이 매우 느리다(ComfyUI 는 RDNA3 에서 스스로 끈다; ESRGAN 업스케일만 `COMFYUI_ENABLE_MIOPEN=1` 로 켜서 돌린다) ·
  fp8 연산이 없어 fp8 가중치는 VRAM 만 아낀다(**GGUF Q6/Q8 이 안전**) · bf16 VAE 가 느리다(`--fp32-vae`).
- NVIDIA PC 면 위 휠 대신 `pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu128` 이고 나머지는 같다.
- 8GB 라 **한 번에 하나만** 켠다(그림 ↔ 음악 ↔ 음성 GPU 판). Unity·Blender 배치와도 같이 돌리지 않는다(사용자 09-29 "PC 가 멈추지 않게").

## 1. 그림 — ComfyUI (A1111 에서 2026-10-07 이사)

왜 바꿨나: A1111 1.10.1 은 2025-02 이후 멈췄고 SDXL 이후 모델(Z-Image·FLUX.2 klein)을 못 돌린다. ComfyUI 는 2026-01 부터
Windows ROCm 공식 지원, 새 모델 네이티브, 메모리 관리가 나아 8GB 에서 1024² 가 공유 메모리로 안 넘친다. 툴 라이선스(GPL-3)는 결과물과 무관.

```powershell
cd C:\swbins3
git clone --depth 1 https://github.com/Comfy-Org/ComfyUI.git comfyui
git clone --depth 1 https://github.com/city96/ComfyUI-GGUF.git comfyui\custom_nodes\ComfyUI-GGUF   # Apache-2.0, GGUF 로더
copy comfyui-saga\saga_seamless.py comfyui\custom_nodes\                                          # 이음매 타일 노드(K-0020)
copy comfyui-saga\gguf_ops_patched.py comfyui\custom_nodes\ComfyUI-GGUF\ops.py                     # 10-07: GGUF main(2026-01)이 ComfyUI 의 융합 활성화 kwargs 를 몰라 Linear 만 손봄(CRLF 주의)
cd comfyui
py -3.12 -m venv venv
venv\Scripts\python.exe -m pip install --upgrade pip
# → 0번의 ROCm 휠 셋
venv\Scripts\python.exe -m pip install -r requirements.txt gguf
```

모델(`comfyui\models\`, 전부 상업 허용 — 판정은 `COMMERCIAL_SWAP_TODO.md`):

| 파일 | 어디서 | 용량 | 용도 |
|---|---|---|---|
| `unet\z-image-turbo-Q6_K.gguf` | `unsloth/Z-Image-Turbo-GGUF` | 5.9GB | **주력**. 범용·사실풍·글자. 8단계·CFG 1, 문장형 프롬프트 |
| `text_encoders\Qwen3-4B-Q8_0.gguf` | `Qwen/Qwen3-4B-GGUF` | 4.3GB | Z-Image 텍스트 인코더 |
| `vae\ae.safetensors` | `Comfy-Org/z_image_turbo` (split_files/vae) | 0.3GB | Z-Image VAE |
| `checkpoints\Illustrious-XL-v2.0.safetensors` | `OnomaAIResearch/Illustrious-XL-v2.0` | 6.6GB | 애니·태그형·LoRA. **이음매 타일은 이것으로**(DiT 는 안 됨) |
| `checkpoints\animagine-xl-4.0-opt.safetensors` | `cagliostrolab/animagine-xl-4.0` | 6.6GB | 애니(기존 배치 86개가 이 모델. 깔끔한 현대 애니) |
| `vae\sdxl-vae-fp16-fix.safetensors` | `madebyollin/sdxl-vae-fp16-fix` | 0.3GB | SDXL 둘의 VAE(fp16 NaN 보정) |

둘째 단계 후보(아직 안 받음): FLUX.2 klein 4B(Apache-2.0, 생성+편집+다중 참조 → 인물 일관성, GGUF Q6 ≈4GB) · BiRefNet(MIT, 배경 제거) ·
Real-ESRGAN x4plus_anime_6B(BSD-3, 업스케일). **받지 말 것**: NoobAI(NC) · Illustrious v3.x(라이선스 미확인) · Qwen-Image-2.1(연구용) ·
FLUX dev/Kontext dev/klein 9B(NC) · RMBG-2.0 · 4x-UltraSharp(NC).

켜기·끄기·실행 플래그는 `C:\swbins\tools\ai-art\start_sd.ps1`(숨김 창·낮은 우선순위·PID 파일·RAM 8GB 미만이면 거부)이 정본:
`--use-pytorch-cross-attention --disable-dynamic-vram --lowvram --disable-pinned-memory --fp32-vae`, 환경 `TORCH_ROCM_AOTRITON_ENABLE_EXPERIMENTAL=1`.
왜 이 조합인가(10-07~08 실측 네 번): 기본(동적 VRAM)은 텍스트 인코더(4.4GB)와 DiT(5.7GB)를 프롬프트마다 갈아 끼워 장당 2~5분, 두 계열이 RAM 에 같이 남으면 페이지 파일로 밀려 48s/it.
고전 관리 + `--lowvram` 이면 인코더가 CPU 에 남고 DiT 만 GPU 에 상주. `--fp16-vae` 는 Z-Image 가 검은 그림(NaN)·21s/it 로 깨져 fp32 유지. `gen.py` 는 VAE 를 타일 디코드(512)해 UNet 이 쫓겨나지 않게 하고 배치 시작마다 `/free`.
손으로 켤 땐 `venv\Scripts\python.exe main.py --listen 127.0.0.1 --port 8188 --disable-auto-launch <같은 플래그>`. 측정값은 6번.

## 2. 음악 — ACE-Step 1.5 (`music-gen/`)

`batch_saga.py` 가 `server.py` 를 import 해 ACE-Step API(8001)를 직접 띄우고 끈다(flask 서버 7862 는 안 띄움). 2026-09-28 이 PC 에서 설치·확인(커밋 `ca1e85f`).

```powershell
cd C:\swbins3\music-gen
py -3.12 -m venv venv
venv\Scripts\python.exe -m pip install -r requirements.txt           # flask·imageio-ffmpeg
git clone https://github.com/ace-step/ACE-Step-1.5.git ACE-Step-1.5
cd ACE-Step-1.5
py -3.12 -m venv venv_rocm
# → 0번의 ROCm 휠 셋 (주소는 ACE-Step 의 requirements-rocm.txt 머리말과 같아야 한다)
venv_rocm\Scripts\python.exe -m pip install -r requirements-rocm.txt
venv_rocm\Scripts\python.exe -m acestep.model_downloader                                     # turbo DiT·VAE·Qwen3-Embedding(1.7B LM 도 같이 오면 지운다)
venv_rocm\Scripts\python.exe -m acestep.model_downloader --model acestep-5Hz-lm-0.6B --skip-main
```

- 조합은 **2B turbo + LM 0.6B**(ACE-Step README 의 6–8GB 권장). 1.7B LM 은 12GB+ 전용이라 받아도 못 쓴다(10-07 삭제). XL(4B)도 12GB+.
- `server.py` 가 넣는 값: `HSA_OVERRIDE_GFX_VERSION=11.0.2`(RX 7600; 7900 계열 11.0.0, 7800/7700 계열 11.0.1), `ACESTEP_ROCM_DTYPE=bfloat16`
  (float32 기본값은 8GB 를 넘겨 30초 곡이 107초 → bf16 18초), `torch.distributed` 대체는 `acestep_launch.py`.
- 측정(09-28): 첫 요청 2분 곡 73초(기동 40초 포함), 이후 30초 곡 18초. 5분 유휴면 프로세스를 끝내 VRAM 반환.
- 루프: 모델이 끊김 없는 루프를 만들진 않는다 — 30~60초 기악을 뽑고 끝 2초 크로스페이드(K 티켓 쪽 후처리).

## 3. 음성 — Supertonic 3 + Qwen3-TTS 시험 (`voice-gen/`)

- **Supertonic 3**(`supertonic==1.3.1`, ONNX CPU, 목소리 M1~M5·F1~F5, ko·en·ja): `py -3.12 -m venv venv && venv\Scripts\python.exe -m pip install -r requirements.txt`.
  모델 385MB 는 첫 실행 때 `models\supertonic3\` 로 받는다. **Supertone 사가 2026-07 청산·저장소 아카이브** — 가중치(OpenRAIL-M)는 계속 쓸 수 있으나
  목소리 추가·갱신은 영원히 없다. `models\supertonic3\` 를 지우지 말 것(HF 조직이 사라질 수 있어 로컬 사본이 원본이다; 미러 `jinhwan000/supertonic-3-mirror`).
- **Qwen3-TTS 1.7B VoiceDesign**(Apache-2.0, 한국어, 글로 목소리 설계·감정 지시) — 보완 후보. 별도 venv:
  `py -3.12 -m venv venv_qwen` → 0번 ROCm 휠 셋 → `venv_qwen\Scripts\python.exe -m pip install -U qwen-tts soundfile` →
  `venv_qwen\Scripts\python.exe qwen3_trial.py`(문장 10 × 목소리 3 → `out\qwen3-<날짜>\sheet.md`). ROCm 에서 막히면 `--device cpu` 또는 0.6B.
  **한국어 자연스러움의 독립 청취 비교는 세상에 없다** — 같은 10줄을 Supertonic·Qwen3 로 뽑아 사용자가 듣고 고른다(K 티켓).
- **10-08 실측(K-0082 단계 3)**: Qwen3 설치됨(`venv_qwen`, qwen-tts 0.1.1 — `-U` 를 빼야 ROCm torch 가 안 바뀐다). 모델 받기·로딩 8s(두 번째부터).
  **MIOpen 을 끄면(`torch.backends.cudnn.enabled = False`) 한 줄 63~125초 → 10~18초**(실시간 약 2배) — 스크립트가 켬. 같은 10줄 비교 짝 `supertonic_trial.py`(목소리 M3·F1·M1).
  받아쓰기 점검 `asr_check.py`(Whisper large-v3-turbo, MIT, 되풀이 방지 폴백) → `out\qwen3-20261008sr.md`: **Qwen3 글자 오류율 3.9~5.7%, Supertonic 72~149%**.
  Supertonic 한국어는 같은 문장도 뽑을 때마다 다른 소리가 나고 대부분 알아들을 수 없다(10-02 K-0036 시트 10줄도 받아쓰기 ○ 2줄뿐). 패키지가 이미 NFKD 를 하므로 입력 문제가 아니다.
  → **한국어 음성은 Qwen3-TTS VoiceDesign 이 주력 후보**(사용자 귀가 거부권). Supertonic 은 영어·일본어 쪽이 필요하면 다시 본다.

## 4. 효과음 — MOSS-SoundEffect v2 시험 (`sfx-gen/`)

Apache-2.0 · 1.3B DiT · 48kHz · ≤30초 · 프롬프트 en/zh. 상업 허용 효과음 모델이 없던 빈칸(절차 생성만 있었음).
`py -3.12 -m venv venv` → 0번 ROCm 휠 셋 → `git clone https://github.com/OpenMOSS/MOSS-TTS.git` →
`venv\Scripts\python.exe -m pip install -e MOSS-TTS\moss_soundeffect_v2`(cu128 extra 는 빼고) → `venv\Scripts\python.exe moss_trial.py`.
Triton 이 없으니 `TORCHDYNAMO_DISABLE=1`(스크립트가 켬). 2순위는 Stable Audio 3 Small SFX(연매출 $1M 상한 조건).
- **10-08 설치 실측(K-0082 단계 4)**: `pip install -c constraints-rocm.txt -e MOSS-TTS\moss_soundeffect_v2`(constraints 로 ROCm torch 고정 — 안 하면 PyPI torch 로 바뀐다).
  함정 넷(전부 `moss_trial.py` 가 메움): ① audiotools 가 `torch.distributed.ReduceOp` 를 찾는다 → 빈 자리 심 ② HF 캐시 심볼릭 링크 권한 오류(WinError 1314) →
  `snapshot_download(..., local_dir='models\moss-sfx-v2')`(11GB) 로 받고 `--model models\moss-sfx-v2` ③ `pipe.save_audio` 가 torchaudio 2.9 의 torchcodec 을 요구 → soundfile 로 저장
  ④ 파이프라인이 늘 30초 창을 만들고 앞을 자른다 — 30초 창은 8GB 를 넘쳐 단계당 44초(소리 하나 37분), 안 쓰는 모델 CPU 로(`engine.vram_management_enabled`)+`TORCH_ROCM_AOTRITON_ENABLE_EXPERIMENTAL=1`(AMD 효율 어텐션)로 22초. **창 2~3초는 깨지고(내내 최대 음량), 5초는 소리가 창 뒤쪽에 놓여 앞이 빈다** →
  **창 10초를 다 만들고 소리 시작점부터 길이만큼 자름**(+끝 30ms 페이드) — 한 개 약 184초(효율 어텐션 켬). MIOpen 은 끔(`cudnn.enabled=False`).
  10-08 결과(12종, `out\moss-20261008\clap.md`): 자기 설명 1등 6/12, 짝 있는 10종 평균 유사도 0.105(지금 절차 효과음 0.078·1등 3/10). **타격·동전·괴물 으르렁·불 주문은 MOSS 가 낫고, UI 클릭·확인은 지금 절차 효과음이 낫다, 발소리는 둘 다 약함** — 귀 판정이 거부권.
  자동 점검 `clap_check.py`(CLAP `laion/larger_clap_general`, Apache-2.0): 소리↔설명 유사도·12 프롬프트 중 등수, 지금 효과음(K-0045 절차 생성) 짝과 나란히 → `out\moss-<날짜>\clap.md`.

## 5. 판정기 (`judge-models/`, `populate-clip-cache.py`)

`C:\swbins\tools\asset-audit\judge\judge.sh` 가 `comfyui\venv` 의 파이썬(토치·transformers)으로 CLIP ViT-L/14 + LAION 미적 예측기
(`judge-models\sac+logos+ava1-l14-linearMSE.pth`)를 돌린다. CLIP 모델은 HF 캐시(`~\.cache\huggingface\hub\models--openai--clip-vit-large-patch14`)에서
`local_files_only` 로 읽는다 — 회사망(SSL 검사)에서 못 받으면 `populate-clip-cache.py` 로 다른 망에서 받은 파일을 캐시에 등록한다.

## 6. 측정 결과 (2026-10-07, ComfyUI 이사 직후, RX 7600 8GB)

`gen.py` 가 찍는 `seconds`(요청 → 파일). 샘플링 자체는 A1111 때보다 빠르고(2.4 vs 2.0 it/s) 1024² 가 공유 메모리로 안 넘치지만, 장당 시간은 모델 로딩·CPU 인코딩이 지배한다.
**다음 최적화 후보**(별 티켓): Z-Image 인코더를 GGUF Q4(2.5GB)로 바꿔 GPU 에 같이 올리기 · 인코더 결과를 배치 단위로 묶어 한 번만 인코딩 · 기동 5~6분 원인(ROCm torch import·comfy_kitchen 탐지) · TeaCache/FBCache 노드 · Illustrious 에 Hyper/LCM LoRA(단계 8).


| 모델 | 크기·단계 | 첫 장(로딩 포함) | 이후 장당 |
|---|---|---|---|
| Z-Image-Turbo Q6_K | 1024×1024 · 8 | 184초 | 샘플링 40초(5 s/it) + 인코더(CPU, GGUF Q8 4B) 1~2분 — **프롬프트가 바뀔 때마다** |
| Z-Image-Turbo Q6_K | 768×768 · 8 | 101초 | 샘플링 19초(2.3 s/it) + 인코더 1~2분 |
| Illustrious XL v2.0 | 832×1216 · 28 Euler a | 120~150초 | 약 86초(샘플링 17초, 1.65 it/s) |
| Illustrious XL v2.0 타일 | 768×768 · 28 | 144초(배치 첫 장, 디스크 로딩) | 약 64초(샘플링 12초, 2.4 it/s) |
| 참고: A1111 시절 SDXL | 768×1024 · 28 | 68초 | 40초(1024² 는 5분+, 공유 메모리로 넘침) |

## 7. 걷어낸 것 (2026-10-07)

`video-gen`(AnimateDiff, 뭉개짐·VFX "안 된다") · `3d-gen`(Shap-E, 설치된 적 없음) · `aider`+Ollama(미설치) · `ai-tools-hub`+`start/stop-all`+워치독(09-28 이후 안 켬) ·
`sd-webui`(A1111) · NoobAI(NC) · SD1.5·SDXL base(상위 모델에 밀림) · ACE-Step 1.7B LM(12GB+ 전용) · HF 캐시의 AnimateDiff·SD1.5·CLIP bigG.
되살리지 않는다(SAGA-ARCH §4.5: 인간형 몸·모션·VFX 는 재시도 금지).
