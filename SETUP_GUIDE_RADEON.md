# 라데온(AMD) GPU에서 돌리기

`SETUP_GUIDE.md`는 전부 NVIDIA CUDA 전용으로 만들어졌습니다. AMD Radeon GPU는 Windows에서
CUDA를 못 쓰기 때문에 몇 군데를 다르게 가야 합니다.

검증 상태(2026-09-28, RX 7600 8GB · Windows 11 · AMD 드라이버 2026-08):
- ✅ 음악(ACE-Step 1.5, ROCm 7.2) — 설치부터 곡 생성까지 이 PC 에서 실행해 확인
- ✅ 음성(Supertonic 3) — 이 PC 에서 실행해 확인
- ✅ 이미지(원본 AUTOMATIC1111 + ROCm 7.2, SDXL base·Animagine XL 4.0) — 설치부터 허브를 통한 생성까지 이 PC 에서 확인
- ✅ 허브(PHP 8.3) — 이 PC 에서 이미지·음성·음악 요청, 진행률, 출처 기록까지 확인
- ⬜ 동영상 — 점검 중(`COMMERCIAL_SWAP_TODO.md` 순서 7)

## 왜 그대로 안 되는지

- PyTorch 공식 ROCm(AMD GPU 가속) 빌드는 Linux 용입니다. Windows 는 AMD 가 따로 내는 **ROCm 7.2 휠**
  (Python 3.12 전용, AMD 드라이버 26.1.1 이상)이 있고, 이미지(sd-webui)·음악(ACE-Step)은 이걸로 돌립니다.
- 원본 A1111 은 Python 3.10 기준이라 3.12 에서 몇 가지를 맞춰 줘야 합니다 — 필요한 파일은 저장소의 `sd-webui-rocm\` 에 있습니다.

## 이미지(sd-webui) — 원본 AUTOMATIC1111 + Windows ROCm 7.2 (추천, 검증됨)

```powershell
cd C:\swbins3
git clone --depth 1 https://github.com/AUTOMATIC1111/stable-diffusion-webui.git sd-webui
cd sd-webui\extensions
git clone --depth 1 https://github.com/AUTOMATIC1111/stable-diffusion-webui-old-localizations.git
cd ..
xcopy /e /i ..\sd-webui-rocm\extensions\rocm-tweaks extensions\rocm-tweaks
copy /y ..\sd-webui-rocm\webui-user.bat webui-user.bat
py -3.12 -m venv venv
venv\Scripts\python.exe -m pip install --upgrade pip
venv\Scripts\python.exe -m pip install `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_core-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_devel-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm_sdk_libraries_custom-7.2.0.dev0-py3-none-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/rocm-7.2.0.dev0.tar.gz
venv\Scripts\python.exe -m pip install `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torch-2.9.1+rocmsdk20260116-cp312-cp312-win_amd64.whl `
  https://repo.radeon.com/rocm/windows/rocm-rel-7.2/torchvision-0.24.1+rocmsdk20260116-cp312-cp312-win_amd64.whl
venv\Scripts\python.exe -m pip install --upgrade "setuptools<70" wheel
venv\Scripts\python.exe -m pip install --no-build-isolation "https://github.com/openai/CLIP/archive/d50d76daa670286dd6cacf3bcd80b5e4823fc8e1.zip"
webui-user.bat
```

모델 파일(SDXL base, Animagine XL 4.0, SD 1.5, SDXL 용 VAE)은 `AI_MODELS_TODO.md` 1번. AnimateDiff 확장은 비상업 라이선스라 설치하지 않습니다. 첫 실행은 보조 저장소 복제와
패키지 설치로 몇 분 걸리고, "creating model quickly: OSError ... None" 이 한 번 찍히는 건 정상입니다(느린 방식으로 다시 만듦).

`sd-webui-rocm\` 에 있는 것과 이유(2026-09-28 설치하며 하나씩 부딪힌 것):

| 파일 | 하는 일 | 없으면 |
|---|---|---|
| `webui-user.bat` | `PYTHON` 을 venv 의 python 으로 지정 | PATH 의 `python` 이 Microsoft Store 가짜 실행 파일이라 "Couldn't launch python" |
| 〃 | `REQS_FILE` 로 아래 요구 목록 사용 | Python 3.12 용 설치 파일이 없는 옛 버전을 소스로 빌드하다 실패 |
| 〃 | `MIOPEN_FIND_MODE=FAST` | 첫 생성 때 합성곱 방식을 전수 탐색하느라 10분 넘게 멈춘 것처럼 보임 |
| 〃 | `--medvram-sdxl` | SDXL 일 때 UNet 만 VRAM 에 두고 나머지를 CPU 로 내림. 없으면 SDXL 1024px 이 공유 메모리로 넘쳐 한 장 5분 이상(있으면 42초) |
| 〃 | `--cors-allow-origins=http://127.0.0.1:8611` | 허브 화면이 sd-webui 진행률을 직접 읽음. 없으면 생성 중 진행률이 멈춰 보임(허브 PHP 서버는 요청을 하나씩만 처리) |
| `requirements_versions_py312.txt` | Pillow 10.4 · scikit-image 0.22 · blendmodes 2024.1.1 · numpy 1.26.4 · transformers 4.36.2 · accelerate 0.25.0 | 설치 실패, 또는 `accelerate 0.21` 이 ROCm 윈도우 PyTorch 에 없는 `torch.distributed` 를 불러오다 기동 실패 |
| `extensions\rocm-tweaks` | MIOpen 끄기(`torch.backends.cudnn.enabled = False`) | 512×512·20스텝 한 장이 **136초**(끄면 **5초**). MIOpen 을 켜고 `cudnn.benchmark` 를 써도 91초 |

측정값(RX 7600, 허브 경유):

| 요청 | 걸린 시간 |
|---|---|
| SDXL 1024×1024(또는 1216×832), 20스텝, 같은 체크포인트로 연달아 | 약 42초 |
| 체크포인트가 바뀌는 첫 요청(SDXL base ↔ Animagine, 약 7GB 로딩) | 58~95초 |
| SD 1.5 512×512, 20스텝(SDXL 이 없는 PC) | 4~5초 |

- 예전에 AnimateDiff 확장을 쓰던 때는 영상 요청을 한 번 거치면 그 뒤 SDXL 이 한 스텝 15초로 느려졌다(확장이 모델 교체 뒤에도 메모리를 붙잡음).
  확장을 뺀 뒤로는 없음.

## (대안) 옵션 A — DirectML / 옵션 B — ZLUDA — 미검증

위 ROCm 방식이 안 될 때만. `lshqqytiger/stable-diffusion-webui-amdgpu` 포크를 쓰고 Python 3.10 이 필요합니다.
- A(DirectML): `set COMMANDLINE_ARGS=--api --use-directml`. 설정은 간단하지만 느리고 일부 연산이 CPU 로 떨어짐.
- B(ZLUDA): `--use-zluda`. 빠르지만 비공식이고 HIP SDK 를 시스템에 따로 설치해야 함. 포크 README 의 ZLUDA 절대로.

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

## 그냥 CPU로

아무 설정 없이 되지만 이미지 하나에 수 분, 음악은 그보다 훨씬 더 걸립니다. GPU 세팅이 막힐 때 확인용으로만 씁니다.

## 3D(3d-gen, Shap-E)

이 PC 에는 아직 설치하지 않았습니다(미검증). Shap-E 는 PyTorch 스크립트라 sd-webui 와 같은 ROCm 7.2 휠을 venv 에 넣으면
될 것으로 보지만, 품질이 낮아 게임 에셋으로는 초안 정도라 우선순위를 낮춰 두었습니다.

## 허브(ai-tools-hub) — PHP

`SETUP_GUIDE.md` 7번과 같습니다. winget 으로 설치한 PHP 에는 `php.ini` 가 없어서 `curl` 등 확장이 꺼져 있으니 먼저 켭니다:

```powershell
winget install --id PHP.PHP.8.3
# 설치 폴더(%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_...)에서 php.ini-production 을 php.ini 로 복사한 뒤
# extension_dir = "ext" 와 extension=curl / openssl / mbstring / fileinfo 줄의 앞 ; 를 지운다.
php -m   # curl, openssl, mbstring 이 보이면 됨
```

## 정리

| 구성 | 이미지/동영상(sd-webui) | 음악(music-gen, ACE-Step) | 음성(voice-gen, Supertonic) |
|---|---|---|---|
| 추천(검증됨) | 원본 A1111 + ROCm 7.2 (`sd-webui-rocm\` 파일 사용) | ROCm 7.2 (`venv_rocm`) | CPU(ONNX) — 설정 불필요 |
| 안 될 때 | amdgpu 포크 + DirectML / ZLUDA (미검증) | CPU (매우 느림) | — |
