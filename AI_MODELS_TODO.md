# 로컬 AI 생성 기능 — 받아야 할 모델 파일 목록

> ✅ 2026-09-28: 비상업 전용이던 음악(MusicGen)·음성(XTTS-v2)을 상업 허용 모델(ACE-Step 1.5 · Supertonic 3)로 바꿨다.
> 모델별 라이선스 확인 결과는 `COMMERCIAL_SWAP_TODO.md` 의 표. 옛 모델 파일(`music-gen\models\musicgen-small\`,
> `voice-gen\models\xtts_v2\`)이 남아 있어도 코드는 더 이상 쓰지 않는다 — 지워도 된다.

회사 방화벽이 `huggingface.co` (전문/특화 AI 기타 정책)를 막고 있어서, 아래 파일들은 IT 예외 요청 후 다운로드하거나 다른 네트워크에서 받아서 옮겨야 합니다.

IT에 예외 요청할 때 도메인: `huggingface.co` + `*.cdn-lfs*.huggingface.co` (또는 `*.huggingface.co` 전체), `ollama.com`, `registry.ollama.ai`, `openaipublic.azureedge.net`(3D 생성용, 7번 참고)

## 다른 PC에 동일하게 세팅하기

이 문서의 모든 "넣을 위치"는 `C:\swbins3\...` 절대 경로 기준입니다. 다른 PC에서도 경로를 통일하려면:

1. 저장소를 반드시 `C:\swbins3` 경로에 클론한다 (다른 드라이브·폴더명 사용 금지).
   ```powershell
   git clone https://github.com/smh8627-jpg/swbins3.git C:\swbins3
   ```
2. 아래 1~8번 파일들을 각 "넣을 위치"에 그대로 복사한다 (모델 파일은 `.gitignore`에 있어서 git에는 포함되지 않으므로 별도 복사/다운로드 필요).
3. 4·5번(Ollama)은 파일만 복사해서는 안 되고, PC마다 아래처럼 등록 명령을 한 번씩 실행해야 한다:
   ```powershell
   ollama create qwen2.5:7b -f - <<< "FROM C:\swbins3\ollama\models\qwen\Qwen2.5-7B-Instruct-Q4_K_M.gguf"
   ollama create qwen2.5-coder:7b -f - <<< "FROM C:\swbins3\ollama\models\qwen\qwen2.5-coder-7b-instruct-q4_k_m.gguf"
   ```
   (PowerShell에서는 `Set-Content Modelfile "FROM C:\swbins3\ollama\models\qwen\...\ .gguf"` 후 `ollama create <이름> -f Modelfile`)
4. `ollama list`로 두 모델이 뜨는지 확인.

## 1. 이미지 생성용 체크포인트 (필수)

- 파일: `v1-5-pruned-emaonly.safetensors`
- 용량: 약 4GB
- 받는 곳: https://huggingface.co/stable-diffusion-v1-5/stable-diffusion-v1-5/resolve/main/v1-5-pruned-emaonly.safetensors
- 넣을 위치: `C:\swbins3\sd-webui\models\Stable-diffusion\`
- 상태: ✅ 다운로드 완료 (이 PC 기준)

## 2. 동영상 생성용 모션 모듈 (필수)

- 파일: `mm_sd15_v2.safetensors`
- 용량: 약 909MB
- 받는 곳: https://huggingface.co/conrevo/AnimateDiff-A1111/resolve/main/motion_module/mm_sd15_v2.safetensors
- 넣을 위치: `C:\swbins3\sd-webui\extensions\sd-webui-animatediff\model\`
- 상태: ✅ 다운로드 완료 (이 PC 기준)

## 3. 음악 생성 모델 — ACE-Step 1.5 (MIT)

- 모델: `ACE-Step/Ace-Step1.5` 기본 묶음(DiT `acestep-v15-turbo`, VAE, 텍스트 인코더 Qwen3-Embedding-0.6B, LM 1.7B)
  + VRAM 8GB 이하용 LM `ACE-Step/acestep-5Hz-lm-0.6B`
- 라이선스: 코드·가중치 모두 MIT (HF 태그 `license:mit`, 게이트 없음 — 2026-09-28 확인)
- 용량: 약 10GB 안팎(기본 묶음) + 0.6B LM
- 받는 곳(명령): `music-gen\ACE-Step-1.5\` 안에서 `python -m acestep.model_downloader` 와
  `python -m acestep.model_downloader --model acestep-5Hz-lm-0.6B --skip-main` (가상환경의 python — `SETUP_GUIDE*.md` 참고)
- 받는 곳(수동): `huggingface-cli download ACE-Step/Ace-Step1.5 --local-dir ./checkpoints`,
  `huggingface-cli download ACE-Step/acestep-5Hz-lm-0.6B --local-dir ./checkpoints/acestep-5Hz-lm-0.6B`
- 넣을 위치: `C:\swbins3\music-gen\ACE-Step-1.5\checkpoints\`
- 코드: `music-gen/server.py` 는 ACE-Step REST API(포트 8001)를 첫 요청 때 띄우고 5분 유휴 시 끄는 어댑터
- 상태: ✅ 이 PC(RX 7600, ROCm 7.2)에 설치·모델 다운로드·곡 생성 확인 (2026-09-28, `checkpoints\` 약 11GB — LM 1.7B 포함)
- 쓰지 않음: `facebook/musicgen-small` (가중치 CC-BY-NC 4.0, 비상업)

## 4. 문서 생성 모델 (Ollama, 명령어로 자동 다운로드)

- 모델: `qwen2.5:7b` (Ollama로 관리, 이미 이 PC에 Ollama 설치되어 있음)
- 용량: 약 4.7GB
- 받는 곳(자동): `registry.ollama.ai` — 방화벽 풀리면 `ollama pull qwen2.5:7b`
- 받는 곳(수동, GGUF 직접 다운로드): https://huggingface.co/bartowski/Qwen2.5-7B-Instruct-GGUF/resolve/main/Qwen2.5-7B-Instruct-Q4_K_M.gguf (4.68GB, huggingface.co 도메인이라 별도 예외 불필요)
  → 받은 뒤 아래처럼 Ollama에 로컬 등록:
  ```
  echo FROM ./Qwen2.5-7B-Instruct-Q4_K_M.gguf > Modelfile
  ollama create qwen2.5:7b -f Modelfile
  ```
- 상태: ✅ 다운로드 + Ollama 등록 완료 (이 PC 기준)

## 5. 코드/앱 생성 모델 (Ollama, 명령어로 자동 다운로드)

- 모델: `qwen2.5-coder:7b`
- 용량: 약 4.7GB
- 받는 곳(자동): `registry.ollama.ai` — 방화벽 풀리면 `ollama pull qwen2.5-coder:7b`
- 받는 곳(수동, GGUF 직접 다운로드): https://huggingface.co/Qwen/Qwen2.5-Coder-7B-Instruct-GGUF/resolve/main/qwen2.5-coder-7b-instruct-q4_k_m.gguf (4.68GB, huggingface.co 도메인)
  → 받은 뒤 4번과 동일하게 `Modelfile` + `ollama create qwen2.5-coder:7b -f Modelfile`
- 상태: ✅ 다운로드 + Ollama 등록 완료 (이 PC 기준)

## 6. 음성 생성 모델 — Supertonic 3 (OpenRAIL-M)

- 모델: `Supertone/supertonic-3` (pip 패키지 `supertonic==1.3.1` 이 커밋 `724fb5ab…` 로 고정해서 받음)
- 라이선스: 가중치 OpenRAIL-M(상업 허용, Attachment A 금지 용도만 제한), 코드 MIT — 2026-09-28 확인
- 용량: 약 385MB (ONNX 4개 + 목소리 10종 JSON)
- 받는 곳(자동): 첫 요청 때 huggingface.co 에서 받음
- 받는 곳(수동): https://huggingface.co/Supertone/supertonic-3/tree/main 의 `onnx/`·`voice_styles/`·`config.json` 을 그대로 복사
- 넣을 위치: `C:\swbins3\voice-gen\models\supertonic3\`
- 참고: Supertone 이 2026-07 에 오픈소스 개발을 끝내고 저장소를 보관(archive) 처리했다. 가중치·라이선스는 그대로라 쓰는 데 문제없지만 업데이트는 없다.
- 상태: ✅ 이 PC(RX 7600, CPU 로 동작)에서 설치·한국어 생성 확인 (2026-09-28)
- 쓰지 않음: `coqui/XTTS-v2` (Coqui Public Model License, 비상업)

## 7. 3D 에셋 생성 모델 (자동 다운로드, 수동 작업도 가능)

- 모델: `transmitter`(공용 인코더), `text_cond`(텍스트→3D, 문서상 "text300M"), `image_cond`(이미지→3D, 문서상 "image300M") — OpenAI Shap-E가 최초 실행 시 자동으로 받음
- 용량: 세 개 합쳐 약 2~3GB
- 받는 곳(자동): `openaipublic.azureedge.net` (캐시 위치 `%USERPROFILE%\.cache\`) — huggingface.co와는 다른 도메인이라 **별도로 방화벽 예외 필요**
- 받는 곳(수동, 직접 다운로드 URL):
  - `transmitter.pt` → https://openaipublic.azureedge.net/main/shap-e/transmitter.pt
  - `text_cond.pt` → https://openaipublic.azureedge.net/main/shap-e/text_cond.pt
  - `image_cond.pt` → https://openaipublic.azureedge.net/main/shap-e/image_cond.pt
  받은 파일은 `%USERPROFILE%\.cache\shap_e\` 폴더에 그대로 넣으면 됨(파일명 그대로 유지)
- IT에 추가로 예외 요청할 도메인: `openaipublic.azureedge.net`
- 확인: `curl.exe -I https://openaipublic.azureedge.net` 이 막혀 있으면(현재 상태) `3d-gen` 탭에서 "모델 로드 실패" 에러가 남
- 상태: ✅ 다운로드 완료 (이 PC 기준)

## 8. 웹툰/만화·애니메 스타일 체크포인트 (선택, 품질 개선용)

기본 체크포인트(1번, `v1-5-pruned-emaonly`)는 범용 모델이라 "webtoon style" 같은 프롬프트만으로는
선화가 지저분하고 스타일이 일관되지 않습니다. 아래 애니메 전용 체크포인트로 교체하면 웹툰/만화 탭
품질이 크게 좋아집니다.

- 파일: `Counterfeit-V3.0_fp16.safetensors` (Counterfeit-V3.0, 애니메 라인아트에 강함)
- 용량: 약 4.24GB (fp16 권장판. 풀버전 `Counterfeit-V3.0.safetensors`는 9.4GB)
- 받는 곳: https://huggingface.co/gsdf/Counterfeit-V3.0/resolve/main/Counterfeit-V3.0_fp16.safetensors
- 대안: `rev_1.2.2/rev_1.2.2-fp16.safetensors` (ReV Animated, 좀 더 대중적인 반실사/애니메 혼합 스타일, 약 4.24GB)
  받는 곳: https://huggingface.co/s6yx/ReV_Animated/resolve/main/rev_1.2.2/rev_1.2.2-fp16.safetensors
- 넣을 위치: `C:\swbins3\sd-webui\models\Stable-diffusion\`
- 적용 방법: 받은 뒤 sd-webui 웹 UI(http://127.0.0.1:7860) 상단 체크포인트 드롭다운에서 선택하거나,
  `curl.exe -X POST -H "Content-Type: application/json" -d "{\"sd_model_checkpoint\":\"Counterfeit-V3.0_fp16.safetensors\"}" http://127.0.0.1:7860/sdapi/v1/options` 로 API 전환
- 상태: ✅ 다운로드 완료 (이 PC 기준, Counterfeit-V3.0·rev_1.2.2 둘 다)

## 9. CLIP 토크나이저 (sd-webui 체크포인트 로딩에 필수, IT 예외 불가 시 우회 방법)

sd-webui는 체크포인트를 처음 로드할 때 `openai/clip-vit-large-patch14` 토크나이저 파일을
huggingface.co에서 받아온다. 이 회사망은 huggingface.co 자체는 도달은 되지만 프록시의 SSL
검사(self-signed cert)가 인증서 검증에 걸려서 다운로드가 실패하고, 체크포인트 드롭다운이
"오류"로 표시된다. IT 예외 요청이 불가능한 경우 아래로 우회한다.

- 받을 파일 5개(회사망이 아닌 다른 네트워크에서, 폰 핫스팟 등):
  - https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/config.json
  - https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/vocab.json
  - https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/merges.txt
  - https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/tokenizer_config.json
  - https://huggingface.co/openai/clip-vit-large-patch14/resolve/main/special_tokens_map.json
- 넣을 위치: `C:\swbins3\clip-vit-large-patch14\` (5개 파일을 이 폴더에 그대로)
- 등록: `python C:\swbins3\populate-clip-cache.py` 실행 — Hugging Face 캐시(`%USERPROFILE%\.cache\huggingface\hub\`)에
  정식 캐시 포맷으로 등록해준다. 스크립트 상단 docstring에 자세한 설명 있음.
- ✅ 코드 반영 완료: `sd-webui/webui-user.bat`에 `HF_HUB_OFFLINE=1`/`TRANSFORMERS_OFFLINE=1` 설정 완료
  — 캐시만 채워지면 다시는 네트워크를 안 탐. (이 파일은 `sd-webui/`가 gitignore 대상이라 git엔 안 올라감,
  다른 PC에서도 위 내용대로 직접 추가해야 함)
- 상태: ✅ 파일 5개 다운로드 완료 (이 PC 기준) — `populate-clip-cache.py` 실행해서 캐시에 등록하면 됨

## 10. sd-webui 로컬 실행 설정 (이 PC 하드웨어 대응, 필수 — gitignore 대상이라 git엔 안 올라감)

`sd-webui/`는 통째로 gitignore 대상이라 아래 3가지는 다른 PC(또는 재설치 시)에서 직접 다시 해줘야 합니다.
2026-09-22에 6GB급 VRAM(GTX/RTX 6GB) + 13세대 Intel CPU 조합에서 실제로 이미지 생성을 검증하며 확인한 내용입니다.

1. **`sd-webui/webui-user.bat`의 `COMMANDLINE_ARGS`에 `--lowvram` 사용 (`--medvram` 아님)**
   - `--no-half`(fp32, 13세대 Intel CPU의 torch_cpu.dll access violation 회피용)와 `--medvram`을 같이 쓰면
     체크포인트 하나만 떠 있어도 ~3.5GB를 기본으로 깔고 시작해서, 512×512 기본 생성 중간에
     `RuntimeError: bad allocation`(사실상 VRAM 부족)으로 실패했다.
   - `--lowvram`으로 바꾸면 레이어를 더 공격적으로 CPU↔GPU로 옮겨서(속도는 느려짐, 512×512/20스텝
     기준 약 40초) 6GB에서 안정적으로 끝까지 생성된다.
2. **활성 체크포인트를 `v1-5-pruned-emaonly.safetensors`로 명시 고정**
   - `sd-webui/config.json`에 `"sd_model_checkpoint": "v1-5-pruned-emaonly.safetensors"` 추가.
   - 설정이 없으면 sd-webui가 `models/Stable-diffusion/` 안에서 알파벳순으로 첫 파일(`Counterfeit-V3.0_fp16.safetensors`,
     파일명에 fp16 포함)을 집어서 로드하려다 위의 그 access violation 버그를 그대로 맞고 죽는다.
     `v1-5-pruned-emaonly`는 fp16이 아니라서 안전하다.
3. **`sd-webui/extensions/sd-webui-animatediff/scripts/animatediff_ui.py`의 `get_sd_rm_tag()`에 None 가드 추가**
   - sd-webui는 체크포인트를 백그라운드 스레드에서 로드하면서 동시에 API를 초기화하는데, 이 확장이
     그 시점에 `shared.sd_model`이 이미 로드돼 있다고 가정하고 `.is_sdxl`에 접근해서, 로딩이 늦게
     끝나면(다른 GPU 프로세스가 같이 떠 있을 때 특히 잘 발생) `AttributeError`로 sd-webui 전체가 죽는다.
   - 함수 맨 앞에 `if shared.sd_model is None: return []` 한 줄 추가하면 해결된다(모델 미로딩 시 태그
     필터링만 건너뛰고 넘어감, 원래 있던 "인식 못 하는 모델 타입"의 fallback과 동일한 동작).
   - `--skip-load-model-at-start`(체크포인트를 켜자마자가 아니라 첫 요청 때 로드)는 이 버그를 100%
     재현시켜서 시도했다가 되돌렸다. 위 None 가드를 넣은 지금은 다시 시도해볼 수도 있지만, 아직 검증 안 함.

## 확인 방법

방화벽 예외가 풀리면 아래로 접속이 되는지 먼저 확인:

```powershell
curl.exe -I https://huggingface.co
```

`200 OK`가 나오면 위 1·2번 파일을 받아서 해당 경로에 넣고, 음악은 3번의 모델 받기 명령을 먼저 실행한 뒤 사이트에서 "음악 생성"을 눌러보면 됩니다(첫 실행은 ACE-Step 기동 때문에 시간이 걸림).
