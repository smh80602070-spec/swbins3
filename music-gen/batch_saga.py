"""saga 배경음 배치 — data/bgm-plan.json(이 저장소가 아니라 saga 저장소) 의 슬롯마다 후보 2곡을 만든다 (K-0004).

사용: venv\\Scripts\\python.exe batch_saga.py <bgm-plan.json> <출력폴더> [--only id1,id2] [--dry]
  출력: <출력폴더>/<id>-a.ogg · <id>-b.ogg + 같은 이름 .license.json, 그리고 progress.json (슬롯 하나 끝날 때마다 갱신 → 끊겨도 이어서)
  이미 있는 후보는 건너뛴다. 끝나면(또는 실패하면) ACE-Step 서버를 끈다(VRAM·RAM 반환).
정책: 프롬프트에 원작 곡명·아티스트 이름 금지(BLOCK). 상업 허용 모델만(PROVENANCE 는 server.py 것을 그대로 쓴다).
"""
import json
import os
import re
import subprocess
import sys
import time

import imageio_ffmpeg

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import server  # noqa: E402  (Flask 앱은 import 만, 실행 안 함)

FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()      # soundfile 의 Vorbis 인코더는 이 PC 에서 프로세스를 죽인다(2026-10-01) → ffmpeg(libvorbis)

BLOCK = re.compile(r"(zelda|final fantasy|pokemon|genshin|diablo|maplestory|animal crossing|ghibli|joe hisaishi|hans zimmer|"
                   r"nobuo uematsu|koji kondo|in the style of|like the|theme from)", re.I)


def main():
    plan_path, out_dir = sys.argv[1], sys.argv[2]
    only = sys.argv[sys.argv.index("--only") + 1].split(",") if "--only" in sys.argv else []
    dry = "--dry" in sys.argv
    plan = json.load(open(plan_path, encoding="utf-8"))
    slots = [s for s in plan["slots"] if not only or s["id"] in only]
    for s in slots:
        if BLOCK.search(s["prompt"]):
            sys.exit(f'{s["id"]}: 프롬프트에 금지어 — 원작 곡명·아티스트·"~풍" 지시는 쓰지 않는다')
    os.makedirs(out_dir, exist_ok=True)
    todo = []
    for s in slots:
        for k, tag in enumerate(("a", "b")):
            if not os.path.exists(os.path.join(out_dir, f'{s["id"]}-{tag}.ogg')):
                todo.append((s, k, tag))
    print(f"슬롯 {len(slots)} · 만들 곡 {len(todo)}")
    if dry or not todo:
        return
    prog_path = os.path.join(out_dir, "progress.json")
    prog = json.load(open(prog_path, encoding="utf-8")) if os.path.exists(prog_path) else {"done": [], "failed": []}
    try:
        server.ensure_acestep()
        for n, (s, k, tag) in enumerate(todo, 1):
            t0 = time.time()
            try:
                wav, meta = server._generate_wav(s["prompt"], "[Instrumental]", s["seconds"])
            except Exception as exc:  # noqa: BLE001
                prog["failed"].append({"id": f'{s["id"]}-{tag}', "error": str(exc)[:200]})
                json.dump(prog, open(prog_path, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
                print(f'실패 {s["id"]}-{tag}: {exc}')
                if len(prog["failed"]) >= 3:
                    sys.exit("연속 실패 — 멈춘다")
                continue
            base = os.path.join(out_dir, f'{s["id"]}-{tag}')
            open(base + ".wav", "wb").write(wav)
            rc = subprocess.run([FFMPEG, "-y", "-loglevel", "error", "-i", base + ".wav", "-c:a", "libvorbis", "-q:a", "3", base + ".ogg"],
                                capture_output=True, text=True)
            os.remove(base + ".wav")
            if rc.returncode != 0 or not os.path.exists(base + ".ogg") or os.path.getsize(base + ".ogg") < 20000:
                prog["failed"].append({"id": f'{s["id"]}-{tag}', "error": "ogg 변환 실패 " + rc.stderr[:120]})
                json.dump(prog, open(prog_path, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
                print(f'실패 {s["id"]}-{tag}: ogg 변환')
                continue
            lic = dict(server.PROVENANCE, created_at=time.strftime("%Y-%m-%dT%H:%M:%S%z"), slot=s["id"], candidate=tag,
                       params={"prompt": s["prompt"], "lyrics": "[Instrumental]", "duration": s["seconds"], "seed_hint": s["seed"] + k},
                       result=meta, edited_by_human=False, note="후보 — 사용자가 고르기 전엔 게임에 넣지 않는다")
            json.dump(lic, open(base + ".license.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
            prog["done"].append(f'{s["id"]}-{tag}')
            json.dump(prog, open(prog_path, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
            print(f'ok {n}/{len(todo)} {s["id"]}-{tag} {time.time() - t0:.0f}s {os.path.getsize(base + ".ogg") // 1024}KB', flush=True)
    finally:
        try:
            server._stop_acestep()
        except Exception:  # noqa: BLE001
            pass


if __name__ == "__main__":
    main()
