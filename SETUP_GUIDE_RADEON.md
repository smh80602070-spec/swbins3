# 라데온(AMD) GPU에서 돌리기

`SETUP_GUIDE.md`는 전부 NVIDIA CUDA 전용으로 만들어졌습니다. AMD Radeon GPU는 Windows에서
CUDA를 못 쓰기 때문에 몇 군데를 다르게 가야 합니다.

검증 상태(2026-09-28, RX 7600 8GB · Windows 11 · AMD 드라이버 2026-08):
- ✅ 음악(ACE-Step 1.5, ROCm 7.2) — 설치부터 곡 생성까지 이 PC 에서 실행해 확인
- ✅ 음성(Supertonic 3) — 이 PC 에서 실행해 확인
- ⬜ 이미지·동영상(sd-webui, 옵션 A·B) — 아직 **미검증**. 막히면 에러를 그대로 알려 주세요.

## 왜 그대로 안 되는지

- PyTorch 공식 ROCm(AMD GPU 가속) 빌드는 Linux 용입니다. Windows 는 AMD 가 따로 내는 **ROCm 7.2 휠**
  (Python 3.12 전용, AMD 드라이버 26.1.1 이상)이 있고, 음악(ACE-Step 1.5)은 이걸로 돌립니다 — 아래 "음악" 절.
- 이미지(sd-webui)는 아래 옵션 A·B 중 하나를 고릅니다.

## 옵션 A — DirectML (공식, 안전하지만 느림)

Microsoft의 DirectML을 통해 DirectX 12로 도는 방식. 설정은 간단하지만 CUDA보다 훨씬 느리고,
일부 연산은 지원이 안 돼서 자동으로 CPU로 떨어지기도 합니다.

**sd-webui (이미지·동영상)**

원본 AUTOMATIC1111 대신, AMD/DirectML을 공식 지원하는 포크를 씁니다:

```powershell
git clone --depth 1 https://github.com/lshqqytiger/stable-diffusion-webui-amdgpu.git sd-webui
```

`webui-user.bat`에 `--use-directml` 옵션 추가:

```bat
set COMMANDLINE_ARGS=--api --use-directml
```

나머지(레포 미러 교체, AnimateDiff 확장 등)는 `SETUP_GUIDE.md`와 동일합니다.

## 옵션 B — ZLUDA (비공식, 훨씬 빠르지만 설정이 더 복잡함)

CUDA 호출을 라데온에서 그대로 돌려주는 비공식 번역 레이어입니다. 최신 RDNA2/3 카드에서
DirectML보다 체감상 훨씬 빠르지만(거의 네이티브급), 공식 지원이 아니라 웹UI 업데이트에
깨질 수 있습니다. 위 포크(`lshqqytiger/stable-diffusion-webui-amdgpu`)가 ZLUDA 설치 스크립트도
같이 제공합니다 — 저장소의 `webui-user.bat` 안내와 README(ZLUDA 섹션)를 그대로 따라가면 됩니다.
음악·음성은 아래 절대로 따로 설치합니다(ZLUDA 불필요).

## 음악(ACE-Step 1.5) — Windows ROCm 7.2

ACE-Step 공식 안내(`docs/en/INSTALL.md` 의 AMD/ROCm 절, `requirements-rocm.txt`)를 따른 것이고, 2026-09-28 이 PC(RX 7600)에서
아래 명령 그대로 설치해 곡 생성까지 확인했습니다(ACE-Step 커밋 `ca1e85f`). 받는 양: ROCm SDK·PyTorch 약 4GB + 모델 약 11GB.

```powershell
cd C:\swbins3\music-gen
py -3.12 -m venv venv
venv\Scripts\python.exe -m pip install -r requirements.txt       # 어댑터(flask)
git clone https://github.com/ace-step/ACE-Step-1.5.git ACE-Step-1.5
cd ACE-Step-1.5
py -3.12 -m venv venv_rocm
venv_rocm\Scripts\python.exe -m pip install --no-cache-dir `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_core-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_devel-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_libraries_custom-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm-7.2.0.dev0.tar.gz
venv_rocm\Scripts\python.exe -m pip install --no-cache-dir `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torch-2.9.1+rocmsdk20260116-cp312-cp312-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torchaudio-2.9.1+rocmsdk20260116-cp312-cp312-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torchvision-0.24.1+rocmsdk20260116-cp312-cp312-win_amd64.whl
venv_rocm\Scripts\python.exe -m pip install -r requirements-rocm.txt
venv_rocm\Scripts\python.exe -c "import torch; print(torch.cuda.is_available(), torch.cuda.get_device_name(0))"
venv_rocm\Scripts\python.exe -m acestep.model_downloader
venv_rocm\Scripts\python.exe -m acestep.model_downloader --model acestep-5Hz-lm-0.6B --skip-main
```

- 휠 주소는 ACE-Step 저장소의 `requirements-rocm.txt` 머리말에서 가져온 것입니다. 받기 전에 그 파일을 열어 주소가 바뀌지 않았는지 확인합니다.
- 어댑터(`music-gen/server.py`)가 `venv_rocm` 을 찾으면 ROCm 용 환경 변수를 넣어 ACE-Step 을 직접 띄웁니다.
  `HSA_OVERRIDE_GFX_VERSION` 은 **RX 7600 에 맞는 11.0.2** 로 넣습니다(ACE-Step 의 `start_api_server_rocm.bat` 은 11.0.0 으로
  고정돼 있고 업데이트 확인에서 Y/N 입력을 기다리며 멈추므로 그 bat 은 쓰지 않습니다). 다른 카드면 `run.bat` 에
  `set HSA_OVERRIDE_GFX_VERSION=...` 을 넣습니다(7900 계열 11.0.0, 7800/7700 계열 11.0.1).
- ROCm 이 안 잡히면(위 확인 명령이 `False`) ACE-Step 은 CPU 로 돕니다 — 동작은 하지만 매우 느립니다.
- 어댑터는 ROCm 에서 `ACESTEP_ROCM_DTYPE=bfloat16` 을 기본으로 넣습니다. ACE-Step 은 ROCm 에서 기본 float32 로 모델을 올려
  VRAM 8GB 를 넘기고(공유 메모리로 새서) VAE 디코딩까지 CPU 로 떨어지는데, bfloat16 이면 ~5GB 로 들어갑니다.
- ROCm 윈도우용 PyTorch 에는 `torch.distributed` 가 빠져 있어서 ACE-Step 이 쓰는 `vector_quantize_pytorch` 가 import 에서 죽습니다
  (`cannot import name 'group' from 'torch.distributed'`, ACE-Step 이슈 #644). `music-gen/acestep_launch.py` 가 그 빈자리를
  "분산 안 씀" 대체 함수로 채운 뒤 ACE-Step API 서버를 띄우므로 따로 할 일은 없습니다(site-packages 를 고치지 않음).

측정값(RX 7600, bfloat16, 기본 LM 0.6B 켬):

| 요청 | 걸린 시간 |
|---|---|
| 첫 요청(ACE-Step 기동·모델 로딩 약 40초 포함), 2분 곡 | 73초 |
| 모델이 올라간 뒤 30초 곡 | 18초 |
| (참고) float32 기본값일 때 30초 곡 | 107초 |

유휴 5분이 지나면 어댑터가 ACE-Step 프로세스를 끝내 VRAM 을 돌려줍니다(다음 요청은 다시 첫 요청처럼 기동부터).

## 음성(Supertonic 3)

ONNX Runtime 으로 CPU 에서 돌기 때문에 라데온이라고 따로 할 일이 없습니다. `SETUP_GUIDE.md` 3번 그대로.
(2026-09-28 이 PC — RX 7600 — 에서 설치·한국어 생성까지 확인: 첫 요청 약 10초(모델 385MB 다운로드 포함), 이후 1초 안팎.)

## 옵션 C — 그냥 CPU로

아무 설정 없이 되지만 이미지 하나에 수 분, 음악/음성은 그보다 더 걸릴 수 있습니다.
GPU 세팅이 막힐 때 임시로 확인용으로만 쓰는 걸 권장합니다.

## 정리

| 구성 | 이미지/동영상(sd-webui) | 음악(music-gen, ACE-Step) | 음성(voice-gen, Supertonic) |
|---|---|---|---|
| 추천 | `lshqqytiger/stable-diffusion-webui-amdgpu` (ZLUDA 또는 `--use-directml`) | Windows ROCm 7.2 (`venv_rocm`) | CPU(ONNX) — 설정 불필요 |
| VRAM 부족하거나 안 될 때 | `--use-directml` 로 다운그레이드 | CPU (매우 느림) | — |

실제로 진행해보시고, 설치 중 에러 메시지를 그대로 알려주시면 그 카드/드라이버 조합에 맞게 같이 고쳐드리겠습니다.
