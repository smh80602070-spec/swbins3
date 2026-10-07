# -*- coding: utf-8 -*-
"""MOSS-SoundEffect v2.0 효과음 시험 — 상업 허용(Apache-2.0) 효과음 모델이 없던 빈칸을 메운다(2026-10-07 조사).
게임 효과음 12종(타격·발소리·UI·획득)을 한 번씩 뽑아 사용자가 듣고 고른다.

  venv\\Scripts\\python.exe moss_trial.py [--device cuda|cpu] [--steps 50] [--seconds 3]
  → out\\moss-<날짜>\\<id>.wav + sheet.md

설치: git clone https://github.com/OpenMOSS/MOSS-TTS.git  →  moss_soundeffect_v2 폴더를 ROCm torch 가 든 venv 에 pip install -e . (cu128 extra 는 빼고)
ROCm 윈도우엔 Triton 이 없으니 TORCHDYNAMO_DISABLE=1 로 둔다(이 스크립트가 켠다).
"""
import argparse
import datetime
import os
import time

os.environ.setdefault('TORCHDYNAMO_DISABLE', '1')
import torch  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
SFX = [  # id, 영어 프롬프트(모델이 en/zh 만 받는다), 길이(초)
    ('hit_sword', 'A sharp metallic sword slash hitting leather armor, short impact, game sound effect', 2),
    ('hit_blunt', 'A heavy wooden club thud hitting a wooden shield, short impact', 2),
    ('hit_arrow', 'An arrow whooshing and thumping into a wooden post', 2),
    ('step_grass', 'Two footsteps on dry grass, light boots, close', 2),
    ('step_stone', 'Two footsteps on cobblestone, leather boots, echo of a small street', 2),
    ('step_snow', 'Two crunching footsteps on fresh snow', 2),
    ('ui_click', 'A soft wooden UI click, single, very short', 1),
    ('ui_confirm', 'A gentle two-note chime, confirm sound, bright', 2),
    ('pickup_coin', 'A small pouch of coins jingling once, pickup sound', 2),
    ('pickup_herb', 'A soft rustle of leaves and a light pop, picking a plant', 2),
    ('monster_growl', 'A low guttural growl of a large wolf, close, menacing', 3),
    ('magic_fire', 'A burst of fire whooshing then crackling briefly, spell cast', 3),
]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--device', default='cuda')
    ap.add_argument('--steps', type=int, default=50)
    ap.add_argument('--model', default='OpenMOSS-Team/MOSS-SoundEffect-v2.0')
    ap.add_argument('--only', type=int, default=0)
    a = ap.parse_args()
    from moss_soundeffect_v2 import MossSoundEffectPipeline
    out = os.path.join(HERE, 'out', 'moss-' + datetime.date.today().strftime('%Y%m%d'))
    os.makedirs(out, exist_ok=True)
    t0 = time.time()
    pipe = MossSoundEffectPipeline.from_pretrained(a.model, torch_dtype=torch.bfloat16 if a.device != 'cpu' else torch.float32, device=a.device)
    print(f'model loaded {time.time() - t0:.0f}s')
    rows = ['# MOSS-SoundEffect v2.0 효과음 시험 ' + datetime.date.today().isoformat(), '', f'device {a.device} · steps {a.steps} · 로딩 {time.time() - t0:.0f}s', '',
            '| id | 프롬프트 | 초 | 생성 시간 |', '|---|---|---|---|']
    for sid, prompt, sec in (SFX[:a.only] if a.only else SFX):
        t1 = time.time()
        audio = pipe(prompt=prompt, seconds=sec, num_inference_steps=a.steps, cfg_scale=4.0)
        pipe.save_audio(audio, os.path.join(out, sid + '.wav'))
        rows.append(f'| {sid} | {prompt} | {sec} | {time.time() - t1:.1f}s |')
        print(sid, f'{time.time() - t1:.1f}s')
    open(os.path.join(out, 'sheet.md'), 'w', encoding='utf-8').write('\n'.join(rows) + '\n')
    print('→', out)


if __name__ == '__main__':
    main()
