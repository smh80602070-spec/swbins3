# 상업 허용 모델 장부 (2026-10-07 리뉴얼)

이 파일은 **라이선스 판정의 정본**이다. `C:\swbins` 자체툴(`tools/ai-art/gen.py` 의 `MODELS`, 음악·음성 배치)은 여기 "쓴다" 줄에 있는 모델만 쓰고,
결과물마다 `.license.json`(모델·라이선스·프롬프트·씨앗)을 남긴다. 새 모델은 **라이선스 원문을 읽고** 이 표에 적은 뒤에만 코드에 넣는다.

## 쓴다 (원문·배포처 확인, 날짜)

| 분야 | 모델 | 라이선스(정확한 이름) | 확인 | 비고 |
|---|---|---|---|---|
| 그림(주력) | **Z-Image-Turbo** (Tongyi-MAI, GGUF `unsloth/Z-Image-Turbo-GGUF` Q6_K) | **Apache-2.0** | 2026-10-07 | 6B DiT, 8단계·CFG 1. 텍스트 인코더 `Qwen/Qwen3-4B-GGUF`(Apache-2.0), VAE `Comfy-Org/z_image_turbo`(Apache-2.0) |
| 그림(애니) | **Illustrious XL v2.0** (OnomaAIResearch) | CreativeML OpenRAIL-M — 저자 HF 토론(2025-04): 상업 가능, 폐쇄 파생 모델 수익화만 금지 | 2026-10-07 | v3.x 는 저장소 게이트·라이선스 불명 → 쓰지 않음 |
| 그림(애니) | **Animagine XL 4.0 Opt** (cagliostrolab) | CreativeML OpenRAIL++-M — 모델 카드 "Permitted: Commercial use" | 2026-09-28 | 기존 배치 86개 |
| 그림(VAE) | `madebyollin/sdxl-vae-fp16-fix` | MIT | 2026-09-28 | SDXL fp16 NaN 보정 |
| 음악 | **ACE-Step 1.5** (`acestep-v15-turbo` + `acestep-5Hz-lm-0.6B`) | MIT(코드·가중치, 저장소 LICENSE 파일) | 2026-09-28 / 10-07 재확인 | 2026-10-07 현재 ACE-Step 2 없음. XL(4B)·1.7B LM 은 12GB+ |
| 음성 | **Supertonic 3** (Supertone) | 가중치 BigScience Open RAIL-M(사용 제한 조항 = 딥페이크·동의 없는 흉내 등, 게임엔 무관), 코드 MIT | 2026-09-28 / 10-07 | **회사 청산(2026-07)·저장소 아카이브** — 로컬 사본이 원본. 배포물 고지에 사용 제한 조항 한 줄 |
| 음성(시험) | **Qwen3-TTS 1.7B VoiceDesign / 0.6B** | Apache-2.0(코드·가중치) | 2026-10-07 | 한국어, 글로 목소리 설계·감정 지시. ROCm Win 실측 전 |
| 효과음(시험) | **MOSS-SoundEffect v2.0** (OpenMOSS) | Apache-2.0 | 2026-10-07 | 48kHz ≤30초, en/zh 프롬프트. 실측 전 |
| 판정기 | CLIP ViT-L/14(`openai`), LAION aesthetic `sac+logos+ava1-l14-linearMSE` | MIT / MIT | 2026-10-05 | 생성물 아님(점수만) |

둘째 단계 후보(받기 전 원문 재확인): FLUX.2 klein **4B**(Apache-2.0 — 9B·dev 는 비상업) · BiRefNet(MIT) · Real-ESRGAN(BSD-3) ·
ControlNet union SDXL `xinsir`(Apache-2.0) · IP-Adapter(Apache-2.0) · Chatterbox Multilingual V3(MIT, 출력 워터마크 기본) · Stable Audio 3 Small(Stability Community — 연매출 $1M 상한).

## 쓰지 않는다 (비상업·불명·조건 나쁨)

| 모델 | 이유 |
|---|---|
| NoobAI XL | Fair AI Public License 변형 — 생성물 상업 사용 금지. 10-07 삭제 |
| Illustrious v3.0/3.5 | 저장소 게이트, 라이선스 불명 |
| Qwen-Image-2.1 | 2026-09 연구용(비상업) 라이선스로 변경 |
| FLUX.1 dev · Kontext dev · FLUX.2 dev · klein 9B | 비상업 |
| RMBG-2.0 · 4x-UltraSharp | CC BY-NC |
| MusicGen · JASCO · AudioLDM2 · TangoFlux | CC-BY-NC(연구용) |
| Coqui XTTS-v2 · Fish OpenAudio S1/S2 · Higgs Audio · IndexTTS-2 · Voxtral TTS | 비상업 또는 메일 협의 |
| Kokoro | 한국어 없음 |
| Hunyuan3D | 라이선스가 한국을 적용 지역에서 뺌 |
| sd-webui AnimateDiff 확장 | CC BY-NC-SA 4.0 (video-gen 자체도 10-07 삭제) |

도구(실행 프로그램) 라이선스는 결과물에 영향 없음: ComfyUI(GPL-3), ComfyUI-GGUF(Apache-2.0), diffusers·ACE-Step·Supertonic 코드(Apache/MIT).

## 하지 말 것

- 비상업 모델로 **예전에** 만든 결과물(MusicGen·XTTS-v2·NoobAI 비교 배치)을 게임 저장소(`C:\swbins`)에 넣지 않는다.
- 라이선스 원문을 확인하지 않은 모델을 "상업 OK"로 적지 않는다. HF 태그만 믿지 않는다(Qwen-Image-2.1 처럼 바뀐다).
- 원작 게임·실존 인물 이름·특정 작가 화풍을 프롬프트에 넣어 만든 결과물을 게임에 쓰지 않는다(사가 저장소의 이름·에셋 정책, `gen.py` 의 `BLOCK`).
- 음악 프롬프트에 실제 곡 제목·가수 이름을 넣지 않는다(ACE-Step 저작자 권고와 같음).
- AI 단독 산출물은 저작권 보호가 약하다 — 사람이 고르고·고치고·조합하는 단계를 둔다(SAGA-ARCH §4.5).
