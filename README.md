# 화면 녹화 · 녹음 도구

화면을 GIF·mp4로 찍고, 필요한 부분을 가리고, 용량을 맞춰 주는 도구입니다.
`rec.ps1` 하나로 캡처·다듬기·용량 조절까지 다 됩니다.

사방넷 업무용 대시보드(`swbins2`)와는 무관한 범용 도구라 2026-09-17 에 이 저장소(`swbins3`)로 분리했습니다.

## 같은 저장소의 AI 생성 도구

화면 녹화와는 별개로, saga(`C:\swbins`) 자체툴이 배치로 부르는 로컬 AI 백엔드(그림 ComfyUI · 음악 ACE-Step · 음성 Supertonic/Qwen3-TTS · 효과음 MOSS)가
같이 산다. 설치·설정은 `SETUP_GUIDE.md`, 라이선스 장부는 `COMMERCIAL_SWAP_TODO.md`. 2026-10-07 에 허브·3D·동영상·코딩 에이전트를 걷어냈다.

## 빠른 사용법

```powershell
# 열린 창 목록
powershell -ExecutionPolicy Bypass -File rec.ps1 -Action list

# 창 하나만 찍기 (가려져 있어도, 다른 모니터에 있어도 잡힙니다)
powershell -ExecutionPolicy Bypass -File rec.ps1 -Action start -Target "Back-Office" -Format gif

# 모니터 통째로 (mp4는 중지할 때 변환됩니다)
powershell -ExecutionPolicy Bypass -File rec.ps1 -Action start -Target monitor2 -Format mp4

# 중지 — 결과는 out\ 에 저장됩니다
powershell -ExecutionPolicy Bypass -File rec.ps1 -Action stop

# 소리만 녹음 (콘솔에서 직접 실행해야 합니다. 아래 '제약' 참고)
powershell -ExecutionPolicy Bypass -File rec.ps1 -Action audio -Seconds 60
```

## 웹 UI

```powershell
powershell -ExecutionPolicy Bypass -File serve.ps1          # 기본 포트 8610
powershell -ExecutionPolicy Bypass -File serve.ps1 -Port 8700
```

`http://127.0.0.1:8610/` 에서 열린 창을 골라 바로 시작·중지하고, 결과물을 그 자리에서 내려받습니다.
시작·중지는 **이 PC에서 연 화면**에서만 되고(캡처가 이 PC 화면을 대상으로 하기 때문), 다른 PC에서 열면 상태·결과물만 봅니다.
`web/` 아래 4개 파일(`router.php`·`index.php`·`action.php`·`file.php`)이 전부고, 프레임워크 없이 PHP 내장 서버로 뜹니다.

`-Target` 은 네 가지로 줍니다.

| 값 | 의미 |
|---|---|
| `Back-Office` 처럼 창 제목 일부 | **그 창만** 직접 캡처 (겹침·다른 모니터 무관) |
| `monitor1` ~ `monitor3` | 그 모니터 전체 |
| `screen` | 조작 중인 창이 있는 화면을 따라감 |
| `2560,0,1200,800` | 화면 좌표 영역 |

## 찍은 뒤 다듬기

GIF로 찍으면 `frames\` 에 프레임이 남습니다. 구간을 자르거나 일부를 가릴 수 있습니다.

```powershell
# 40~100번 프레임만 골라 GIF로 (정지 구간은 자동으로 합쳐집니다)
rec.ps1 -Action build -From 40 -To 100 -Fps 5 -File out\demo.gif

# 프레임 일부를 덮기 — "x,y,w,h" 를 이어서 나열
rec.ps1 -Action mask -Rects "0,32,1080,20,160,105,352,152"

# 5MB 아래로 줄이기 (노션 첨부 제한)
rec.ps1 -Action shrink -File out\demo.gif -MaxMB 5
```

가릴 위치는 **프레임을 PNG로 한 장 뽑아 눈으로 확인하고** 정하는 편이 확실합니다.
가리는 색은 `mask-frames.ps1 -Color White` 처럼 바꿀 수 있습니다 — 페이지 배경과 같은 색으로 덮으면 가린 티가 나지 않습니다.

## 이 PC에서 확인된 제약

- **화면이 꺼져 있으면 캡처가 실패합니다.** `The handle is invalid` 오류가 납니다. 자리를 비운 사이 예약 녹화는 안 됩니다.
- **소리는 콘솔에서 직접 실행할 때만 녹음됩니다.** 웹·트레이가 띄운 프로세스에서는 마이크가 잡히지 않습니다(장치 목록 조회는 되는데 파일이 만들어지지 않습니다). 그래서 웹의 소리 옵션은 막아 두었습니다.
- **시스템 소리(상대방 목소리)는 못 받습니다.** 스테레오 믹스가 없고 ffmpeg에 wasapi 입력이 없습니다. 가상 오디오 케이블류를 따로 깔아야 합니다.
- **ffmpeg의 화면 캡처는 쓰지 않습니다.** `gdigrab`은 실시간으로 돌지 않고 `ddagrab`은 권한이 막혀 있어서, 화면은 자체 .NET 캡처로 찍고 mp4는 끝난 뒤 변환합니다.

## 파일

| 파일 | 하는 일 |
|---|---|
| `rec.ps1` | 통합 CLI. 웹·트레이·프롬프트 모두 이 파일만 호출합니다 |
| `record-window.ps1` | 창 하나를 직접 캡처(PrintWindow) |
| `record-screen.ps1` | 모니터·영역 캡처 (`-FollowMonitor`로 활성 화면 추적) |
| `build-gif.ps1` | 프레임 → 애니메이션 GIF (구간 자르기·정지구간 병합·무한 반복) |
| `mask-frames.ps1` | 저장된 프레임 위에 사각형 덮기 |
| `shrink-gif.ps1` | GIF 해상도를 줄여 용량 맞추기 |
| `list-windows.ps1` | 열린 창 목록 |
| `rec-state.json` · `rec.log` | 현재 상태 · 기록 |
| `out\` | 결과물 |
| `serve.ps1` | 웹 UI 실행 (`php -S` 로 `web\router.php` 를 띄움) |
| `web\` | 웹 UI (라우터·페이지·시작-중지 처리·파일 다운로드) |

## 손볼 때 주의

- PowerShell 스크립트를 `-File` 로 실행하면 **배열 파라미터(`[int[]]`)가 넘어가지 않습니다.** `-Rect`, `-MaskRects` 를 문자열로 받는 이유입니다.
- 같은 이유로 param 기본값의 `$PSScriptRoot` 도 비어 버립니다. 경로 기본값은 본문에서 채웁니다.
- 한글이 든 스크립트는 **UTF-8 BOM** 으로 저장해야 PowerShell 5.1이 제대로 읽습니다.
