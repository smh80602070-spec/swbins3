# AI 생성 스튜디오

이미지·동영상·음악·웹툰(만화)·디자인·2D/3D 게임 에셋·UI·SNS/블로그 초안·문서·코드/앱·음성을
**이 PC에 설치된 로컬 AI로 직접 생성**하는 웹 UI입니다(외부로 전송되지 않음).
한국어 프롬프트는 로컬 Ollama가 자동으로 영어로 번역해서 생성 품질을 높입니다.
추가로 토큰(비용) 절약 팁 탭도 있습니다.

사이드바에서 종류를 고르고 프롬프트 하나만 입력하면 되는 단일 화면 구조입니다(카테고리별로 탭이 따로 있던 구버전과 다름).

Slim 프레임워크(경량 PHP 프레임워크) 기반이며, PHP 내장 서버로 띄웁니다.

## 실행

```powershell
# 최초 1회: 의존성 설치 (이미 vendor/ 가 있으면 생략)
php composer.phar install

# 서버 실행 (기본 포트 8611)
powershell -ExecutionPolicy Bypass -File serve.ps1
powershell -ExecutionPolicy Bypass -File serve.ps1 -Port 8700
```

`http://127.0.0.1:8611/` 에서 왼쪽 사이드바로 종류(이미지·동영상·음악·웹툰/만화·디자인·2D 에셋·3D 에셋·UI·SNS/블로그·문서·코드/앱·음성)를
고르고 프롬프트를 입력합니다.

## 로컬 생성 기능

`C:\swbins3\start-all.bat`으로 아래 백엔드를 한 번에 켜고, `stop-all.bat`으로 끕니다. "서버 상태" 탭에서 가동 여부를 확인할 수 있습니다.

| 종류 | 백엔드 | 포트 |
|---|---|---|
| 이미지·디자인 | `sd-webui`(AUTOMATIC1111, SDXL base) | 7860 |
| 웹툰/만화·2D 에셋 | `sd-webui`(Animagine XL 4.0 Opt) | 7860 |
| 동영상(점검 중, 꺼 둠) | `video-gen`(diffusers AnimateDiff) — 예전 sd-webui AnimateDiff 확장은 비상업 라이선스라 뺐음 | 7865 |
| 음악 | `music-gen`(ACE-Step 1.5 어댑터, ACE-Step API 8001 을 필요할 때 띄움) | 7862 |
| 음성 | `voice-gen`(Supertonic 3, 정해진 목소리 10종) | 7863 |
| 3D 에셋 | `3d-gen`(Shap-E) | 7864 |
| 문서·코드·UI·SNS/블로그, 한국어→영어 프롬프트 번역 | Ollama(qwen2.5) | 11434 |

모두 무료이고 결과물을 상업적으로 쓸 수 있는 모델입니다(라이선스 확인 결과: `C:\swbins3\COMMERCIAL_SWAP_TODO.md`).
결과 아래의 `⬇ 출처 기록(.license.json)` 으로 어떤 모델·라이선스로 만들었는지 파일과 함께 남길 수 있습니다.
sd-webui 는 기능별로 알맞은 체크포인트로 허브가 요청마다 바꿔 씁니다(`sdapi.php` 의 `AIHUB_CHECKPOINTS`) — 바꿀 때 수십 초가 더 걸립니다.

필요한 모델 파일은 `C:\swbins3\AI_MODELS_TODO.md`에 정리되어 있습니다.

### VRAM 자동 관리

GPU 백엔드 4개(`sd-webui`·`music-gen`·`voice-gen`·`3d-gen`) 모두 "쓸 때만 로드, 안 쓰면 자동 언로드"로 동작합니다.
VRAM이 6GB급으로 넉넉하지 않아서, 이미지→음악→음성→3D를 순서대로 한 번씩만 써도 모델 4개가 동시에
VRAM에 쌓여 부족해지는 걸 막기 위함입니다.

- `music-gen`·`voice-gen`·`3d-gen`: 최초 요청이 와야 모델을 로드하고(지연 로딩), 이후 **5분간 요청이 없으면
  자동으로 메모리에서 내립니다**(Ollama의 `keep_alive`와 같은 개념) — GPU(CUDA) 환경이면 VRAM을, CPU 환경이면
  RAM을 비웁니다. 대기 시간은 각 서버 실행 시 `IDLE_UNLOAD_SECONDS` 환경변수로 조절할 수 있습니다(초 단위, 기본 300).
- `sd-webui`: `start-all.bat` 실행 직후 체크포인트를 바로 로드합니다(`--skip-load-model-at-start`는 설치된
  AnimateDiff 확장과 충돌해 죽는 문제가 있어 되돌림 — `AI_MODELS_TODO.md` 10번 참고). 대신
  `sd-webui-idle-watchdog.ps1`(start-all.ps1이 함께 띄움)이 30초마다 마지막 생성 시각(`logs\sd-last-used.txt`)을
  확인해서 5분 이상 유휴면 `/sdapi/v1/unload-checkpoint` API로 체크포인트를 내립니다. 다음 생성 요청이 오면
  sd-webui가 알아서 다시 로드합니다.

네 백엔드 모두 다시 로드되는 첫 응답은 그만큼 느려집니다(체크포인트 기준 보통 수십 초). 항상 즉시 응답이
필요하면(여러 명이 동시에 자주 쓰는 등) VRAM이 더 큰 GPU가 근본적인 해결책입니다 — 소프트웨어로는 "필요할 때만
올리고 안 쓰면 내리기"가 6GB급 VRAM에서 할 수 있는 최선입니다.

실제로 파일을 읽고 쓰고 명령을 실행하는 "에이전트형" 코딩이 필요하면 웹 UI가 아니라 `C:\swbins3\aider`(Aider + 로컬 Ollama)를
터미널에서 직접 씁니다 — `aider/README.md` 참고.

## 첨부파일

이미지/동영상/웹툰/디자인/2D 에셋 탭은 참고 이미지를 첨부하면 그 이미지를 바탕으로 변형(img2img)합니다.
문서/코드/UI/SNS 탭은 텍스트 파일을 첨부하면 그 내용을 참고자료로 씁니다. 3D 탭은 이미지를 첨부하면 프롬프트 없이도 그 이미지 기준으로 생성합니다(image-to-3D).
음성 탭은 목소리 샘플(wav/mp3)을 첨부해서 그 목소리를 흉내냅니다.

## SNS/블로그에 대해

캡션·해시태그·스크립트·포스트 **초안 생성까지만** 합니다. 인스타그램/틱톡/카페 등에 실제로 게시·업로드하는 자동화는
계정 정지·ToS 위반 위험과 공식 API 연동이 필요해서 지원하지 않습니다.

## 팁 추가/수정

코드를 건드릴 필요 없이 JSON 파일만 고치면 됩니다.

- `data/token-saving.json` — 토큰 절약법 팁 목록 (`general`, `claude_code` 두 그룹)

## 구조

| 경로 | 하는 일 |
|---|---|
| `public/index.php` | Slim 프론트 컨트롤러. `/`, `/generate/*`, `/status` 라우트 |
| `src/data.php` | `data/*.json` 로더 + HTML 이스케이프 헬퍼 |
| `src/render.php` | 메인 페이지 HTML 렌더링 |
| `src/sdapi.php` | 로컬 백엔드(sd-webui·music-gen·voice-gen·3d-gen·Ollama) 연동 + 한↔영 프롬프트 번역 |
| `data/token-saving.json` | 토큰 절약법 팁 |
| `serve.ps1` | 개발 서버 실행 (`php -S`) |
| `composer.phar` | Composer 실행 파일 (의존성 설치용) |
| `vendor/` | Composer가 설치한 라이브러리 (git 추적 안 함) |
