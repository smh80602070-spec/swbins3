"""saga 음성 판정 배치 — 한국어 문장 10개(NPC 감탄사 5 · 시스템 안내 5)를 Supertonic 3 로 만들고 확인 시트를 쓴다 (K-0011).

사용: venv\\Scripts\\python.exe batch_saga.py [출력폴더] [시트경로]
  기본 출력: out\\saga-<날짜>\\01.wav ~ 10.wav + 같은 이름 .license.json (출처 기록)
  기본 시트: C:\\swbins\\tasks\\sheets\\voice-<날짜>.md  (saga 저장소 — 음성 파일은 그쪽에 안 넣는다)
  이미 있는 wav 는 건너뛴다(끊겨도 이어서). HTTP 서버(7863)는 안 띄우고 server.py 의 모델 로더를 직접 쓴다.
정책: 실존 인물·원작 대사 금지. 모델·라이선스 기록은 server.PROVENANCE 를 그대로 쓴다(OpenRAIL-M 금지 용도 주의).
"""
import io
import json
import os
import sys
import time

import soundfile

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import server  # noqa: E402  (Flask 앱은 import 만, 실행 안 함)

SPEED = 1.05
# (종류, 목소리, 문장)
LINES = [
    ("감탄사", "F1", "오오, 이게 다 뭐람!"),
    ("감탄사", "M1", "으아악, 살려 줘요!"),
    ("감탄사", "F2", "해냈다! 우리가 이겼어!"),
    ("감탄사", "M2", "어라? 이런 데도 길이 있었네."),
    ("감탄사", "F3", "흥, 두고 보자고!"),
    ("안내", "F1", "새 지역을 발견했습니다."),
    ("안내", "M1", "저장이 완료되었습니다."),
    ("안내", "F1", "배낭이 가득 찼습니다."),
    ("안내", "M1", "일일 의뢰가 갱신되었습니다."),
    ("안내", "F1", "연결이 끊어졌습니다. 다시 시도해 주세요."),
]


def main():
    date = time.strftime("%Y%m%d")
    out_dir = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "out", f"saga-{date}")
    sheet = sys.argv[2] if len(sys.argv) > 2 else rf"C:\swbins\tasks\sheets\voice-{date}.md"
    os.makedirs(out_dir, exist_ok=True)
    tts = server.get_tts()
    rows = []
    for n, (kind, voice, text) in enumerate(LINES, 1):
        path = os.path.join(out_dir, f"{n:02d}.wav")
        if not os.path.exists(path):
            t0 = time.time()
            style = tts.get_voice_style(voice_name=voice)
            wav, _dur = tts.synthesize(text, voice_style=style, lang="ko", speed=SPEED)
            buf = io.BytesIO()
            soundfile.write(buf, wav.squeeze(), tts.sample_rate, format="WAV")
            with open(path, "wb") as f:
                f.write(buf.getvalue())
            prov = dict(server.PROVENANCE, created_at=time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                        params={"text": text, "language": "ko", "voice": voice, "speed": SPEED})
            with open(path[:-4] + ".license.json", "w", encoding="utf-8") as f:
                json.dump(prov, f, ensure_ascii=False, indent=1)
            print(f"{n:02d} {voice} {time.time() - t0:.1f}s  {text}")
        info = soundfile.info(path)
        rows.append((n, kind, text, voice, path, info.frames / info.samplerate))
    lines = [
        f"# 음성 판정 시트 — Supertonic 3 한국어",
        f"{time.strftime('%Y-%m-%d')} · 문장 {len(rows)}개 · 모델 {server.PROVENANCE['model']}",
        "파일을 재생해 ○/× 만 적어 주세요. ×(어색하거나 알아듣기 힘듦)가 많으면 음성은 접습니다. 음성 파일은 로컬 전용(이 저장소엔 없음).",
        "",
        "| n | 종류 | 문장 | 목소리 | 파일 | 길이(초) | ○/× | 메모 |",
        "|---|---|---|---|---|---|---|---|",
    ]
    for n, kind, text, voice, path, sec in rows:
        lines.append(f"| {n} | {kind} | {text} | {voice} | `{path}` | {sec:.1f} | | |")
    os.makedirs(os.path.dirname(sheet), exist_ok=True)
    with open(sheet, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(lines) + "\n")
    print(f"완료 · 음성 {len(rows)} · 시트 {sheet}")


if __name__ == "__main__":
    main()
