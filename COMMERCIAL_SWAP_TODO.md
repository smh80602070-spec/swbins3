# 상업 허용 모델로 전부 교체하기 — 할 일

> 2026-09-27 기록, 2026-09-28 교체 반영. 게임(사가 다섯 판·saga-unity·saga-godot, `C:\swbins`)에 넣어 **팔 수 있는 에셋**을 이 도구로 만들려면,
> 지금 모델 가운데 비상업 전용인 것을 상업 허용 모델로 바꿔야 한다. 이 문서는 swbins3 세션에서 따로 이어 가기 위한 할 일 목록이다.
> 모델 받는 법·넣을 위치는 `AI_MODELS_TODO.md`, 설치는 `SETUP_GUIDE.md`·`SETUP_GUIDE_RADEON.md`.

## 목표

- 이 도구로 만든 그림·동영상·음악·음성·3D 를 **게임에 넣어 팔 수 있게** 한다(상업 사용·재배포 허용).
- 만든 결과물마다 **어떤 모델로 만들었는지 기록**을 남긴다(나중에 출처를 물으면 답할 수 있게 — `C:\swbins\tools\char-forge` 가 몸마다 `.license.json` 을 남기는 방식).
- AI 로만 만든 결과물은 저작권 보호가 약하다(사람의 창작 기여가 없으면 보호받기 어렵다는 게 지금 흐름). 팔 수는 있지만 "남이 못 베끼게"는 안 된다 — 게임에 쓸 때는 사람이 고르고·고치고·조합하는 단계를 둔다.

## 모델별 상업 사용 판정 (2026-09-28 라이선스 원문·배포처 확인)

| 기능 | 모델 | 라이선스 (확인한 곳) | 상업 | 비고 |
|---|---|---|---|---|
| 음악 | **ACE-Step 1.5** (`acestep-v15-turbo` + LM 0.6B) | MIT — 코드·가중치 모두 ([GitHub](https://github.com/ace-step/ACE-Step-1.5) README "licensed under MIT", [HF](https://huggingface.co/ACE-Step/Ace-Step1.5) 태그 `license:mit`, 게이트 없음) | ✅ | **교체함**. 저작자는 "기존 곡과 비슷하게 나올 수 있으니 독창성을 확인하고 AI 사용을 밝히라"고 권고 |
| 음성 | **Supertonic 3** | 가중치 OpenRAIL-M, 코드 MIT ([HF LICENSE](https://huggingface.co/Supertone/supertonic-3/blob/main/LICENSE), 게이트 없음) | ✅ (금지 용도만 제한) | **교체함**. 정해진 목소리 10종, 목소리 흉내 없음. 2026-07 개발 종료·보관 처리됨(가중치는 그대로 쓸 수 있음) |
| 이미지(기본) | **SDXL base 1.0** `sd_xl_base_1.0` | CreativeML OpenRAIL++-M ([HF](https://huggingface.co/stabilityai/stable-diffusion-xl-base-1.0) 태그, 게이트 없음) | ✅ (금지 용도만 제한) | **새로 넣음**(2026-09-28). 허브의 이미지 일반·디자인 |
| 이미지(애니) | **Animagine XL 4.0 Opt** | CreativeML OpenRAIL++-M — 모델 카드 License 절: "SDXL 라이선스를 수정·추가 제한 없이 그대로", "Permitted: Commercial use" ([HF](https://huggingface.co/cagliostrolab/animagine-xl-4.0)) | ✅ (금지 용도만 제한) | **새로 넣음**. 허브의 웹툰/만화·2D 에셋. 애니 그림으로 학습된 모델이라 실존 작품 캐릭터 이름·작가 화풍을 프롬프트에 넣지 않는다 |
| VAE | `madebyollin/sdxl-vae-fp16-fix` | MIT ([HF](https://huggingface.co/madebyollin/sdxl-vae-fp16-fix) 태그) | ✅ | SDXL 원본 VAE 가 fp16 에서 검은 그림(NaN)을 내서 쓰는 보정판 |
| 이미지(예비) | SD 1.5 `v1-5-pruned-emaonly` | CreativeML OpenRAIL-M ([HF](https://huggingface.co/stable-diffusion-v1-5/stable-diffusion-v1-5) 태그) | ✅ (금지 용도만 제한) | SDXL 이 없는 PC 에서 허브가 대신 씀 |
| 이미지(애니) | Counterfeit-V3.0 | CreativeML OpenRAIL-M ([HF](https://huggingface.co/gsdf/Counterfeit-V3.0) 태그). civitai(모델 4468) 상업 표시: `Image`(생성 이미지 판매 허용)·`RentCivit` | ✅ 생성 이미지 판매 가능 | 유지. 모델 파일 자체를 팔거나 재배포하는 건 안 됨 |
| 이미지(애니) | ReV Animated 1.2.2 | CreativeML OpenRAIL-M ([HF](https://huggingface.co/s6yx/ReV_Animated) 태그). civitai(모델 7371) 상업 표시: `Image`·`RentCivit`, **크레딧 표기 필요**(`allowNoCredit: false`) | ✅ 생성 이미지 판매 가능 | 유지. 게임 크레딧에 "ReV Animated (s6yx)" 를 적는다 |
| 동영상(가중치) | AnimateDiff 모션 모듈 v2 | Apache 2.0 ([guoyww/AnimateDiff](https://github.com/guoyww/AnimateDiff), [HF guoyww/animatediff](https://huggingface.co/guoyww/animatediff) 태그) | ✅ | 가중치 자체는 문제없음 |
| 동영상(실행 코드) | ~~sd-webui AnimateDiff 확장~~ (`continue-revolution/sd-webui-animatediff`) | **CC BY-NC-SA 4.0** — README 첫머리: "I have recently added a non-commercial license to this extension. If you want to use this extension for commercial purpose, please contact me via email." | ❌ 비상업 | **뺐음**(2026-09-28). 가중치가 Apache 라도 이 확장으로 돌리면 상업 목적 사용에 제작자 허락이 필요하다. 아래 순서 7 참고 |
| 3D | OpenAI Shap-E | MIT | ✅ | 품질이 낮아(뭉툭한 덩어리·뼈대 없음) 게임 인물로는 못 씀 — 소품 초안 정도 |
| 문서 | Qwen2.5 7B | Apache 2.0 | ✅ | 유지 |
| 코드 | Qwen2.5-Coder 7B | Apache 2.0 | ✅ | 유지 |

**쓰지 않음(비상업)**: `facebook/musicgen-small`(CC-BY-NC 4.0), `coqui/XTTS-v2`(Coqui Public Model License),
sd-webui AnimateDiff 확장(CC BY-NC-SA 4.0). 코드에서 뺐다.

**도구(실행 프로그램) 라이선스** — 결과물에는 영향 없음: AUTOMATIC1111 sd-webui(AGPL-3.0, 로컬에서만 쓰고 배포하지 않음),
diffusers·ACE-Step·Supertonic 코드(Apache-2.0/MIT), PHP·Slim(PHP License/MIT).

## 검토했지만 고르지 않은 후보

| 기능 | 후보 | 이유 |
|---|---|---|
| 음악 | Stable Audio Open 1.0 / Small | Stability AI Community License — 연 매출 한도가 있고 저장소가 게이트(동의 필요). ACE-Step(MIT) 이 더 깔끔함 |
| 음성 | MeloTTS 한국어 | 모델은 MIT 지만 한국어 발음에 쓰는 BERT(`kykim/bert-kor-base`)의 README 가 "Apache-2.0" 과 "상업적 사용은 MOU(무료) 문의" 를 함께 적고 있어 애매함. 윈도우에서 mecab 설치도 번거로움 |
| 음성 | Piper | 한국어 공식 목소리가 없음 |
| 3D | TRELLIS(Microsoft) | NVIDIA CUDA·큰 VRAM 필요 — 이 PC(AMD)에선 어려움 |
| 3D | Hunyuan3D | 라이선스가 **한국을 적용 지역에서 뺌** — 쓰지 않는다 |

## 이 PC 조건 (2026-09-27)

- 그래픽카드 **AMD Radeon RX 7600 (8GB)**, RAM 32GB. 기존 설정 문서는 NVIDIA 6GB PC 기준으로 검증됨.
- `SETUP_GUIDE_RADEON.md` 는 이미지(A1111 + ROCm 7.2, SDXL)·음악(ACE-Step)·음성(Supertonic)·허브(PHP) 절이 검증됨. 3D 절은 **미검증**, 동영상은 **점검 중**.
- 이 PC 에 설치됨: `sd-webui/`(SDXL base·Animagine XL 4.0·SD 1.5), `music-gen/ACE-Step-1.5/`, voice-gen, video-gen(점검 중), PHP 8.3 + 허브.
  **아직 없음**: `ollama`(문서·코드·프롬프트 번역), 3d-gen.

## 순서

1. ✅ 이 PC 에서 이미지가 돈다 — 원본 A1111 + ROCm 7.2, SDXL base·Animagine XL 4.0 (기능별로 허브가 골라 씀). 막힌 곳과 해결은 라데온 안내서에 적음 (2026-09-28).
2. ✅ 표의 ⚠️ 항목(애니 체크포인트·AnimateDiff) 라이선스 원문 확인 → 표 갱신 (2026-09-28).
3. ✅ 음악 교체: ACE-Step 1.5 로 `music-gen/server.py` 를 바꾸고, 이 PC 에 ROCm 7.2 로 설치해 15·30·120초 곡 생성 확인 (2026-09-28).
   시험 곡: `out\music-samples\` (gitignore 대상).
4. ✅ 음성 교체: Supertonic 3 로 `voice-gen/server.py` 교체, 허브 음성 탭에서 참조 음성 올리기를 없애고 목소리 선택을 넣음. 이 PC 에서 한국어 생성 확인.
5. ✅ 결과물 출처 기록: 모든 결과(이미지·동영상·음악·음성·3D)에 `provenance`(모델·라이선스·상업 허용 여부·생성 시각·프롬프트·시드)를 붙이고,
   허브가 결과 아래에 `⬇ 파일 다운로드` 와 짝이 되는 `⬇ 출처 기록(<파일>.license.json)` 링크를 띄운다.
   - 이미지·동영상은 sd-webui 응답에 적힌 실제 체크포인트 이름으로 `sdapi.php` 의 `AIHUB_CHECKPOINT_LICENSES` 표를 찾는다.
     표에 없는 체크포인트로 만들면 `commercial_use: null` 로 나간다 — 새 체크포인트를 쓰려면 라이선스를 확인하고 이 표와 위 판정 표에 같이 적는다.
   - 이 PC 에서 이미지·동영상·음악·음성은 허브를 거쳐 확인. 3D 는 3d-gen 을 아직 설치하지 않아 미확인.
6. ✅ `AI_MODELS_TODO.md` 의 음악·음성 절을 새 모델로 바꾸고, 옛 비상업 모델은 "쓰지 않음"으로 표시.
7. ⬜ **동영상 — 점검 중(허브에서 꺼 둠)**. sd-webui AnimateDiff 확장이 비상업 라이선스여서 뺐다. 대신 만든 `video-gen`(diffusers AnimateDiff, Apache-2.0)은
   이 PC 에서 글자 프롬프트만으로는 뭉개진 영상만 낸다(2026-09-28). 확인한 것:
   - diffusers 0.35.2·0.30.3, 어댑터 두 형식(HF 변환본·원본 `mm_sd_v15_v2.ckpt`), fp16·fp32, GPU·**CPU** 모두 같은 증상 — GPU/ROCm 문제가 아니다.
   - 같은 SD 1.5 로 모션 모듈 없이 만든 그림은 정상, UNet2D→UNetMotion 변환 뒤 공유 가중치 686개도 모두 같음.
   - 참고 이미지로 움직임을 입히는(video-to-video) 경로는 그림이 나온다(움직임은 적음).
   다음 후보(하나 고르기): (a) ComfyUI + ComfyUI-AnimateDiff-Evolved(Apache-2.0) 로 새로 구성, (b) 확장 제작자에게 상업 사용 허락을 메일로 받기,
   (c) diffusers 원인 추적 계속. 허브에서 켜려면 `AIHUB_VIDEO_ENABLED=1` + `video-gen\run.bat`.

## 하지 말 것

- 비상업 모델(MusicGen·XTTS-v2)로 **예전에** 만든 결과물을 게임 저장소(`C:\swbins`)에 넣지 않는다.
- 라이선스 원문을 확인하지 않은 모델을 "상업 OK"로 적지 않는다.
- 원작 게임·실존 인물 이름·특정 작가 화풍을 프롬프트에 넣어 만든 결과물을 게임에 쓰지 않는다(사가 저장소의 이름·에셋 정책).
- 음악 프롬프트에 실제 곡 제목·가수 이름을 넣지 않는다(ACE-Step 저작자 권고와 같음).
