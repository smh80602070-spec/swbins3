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
os.environ.setdefault('TORCH_ROCM_AOTRITON_ENABLE_EXPERIMENTAL', '1')   # AMD 효율 어텐션(실험) — 30초 창 단계당 39초 → 22초, 공유 메모리 넘침 줄임(10-08)
import soundfile as sf  # noqa: E402
import torch  # noqa: E402
import torch.distributed as _dist  # noqa: E402
if not hasattr(_dist, 'ReduceOp'):   # ROCm 윈도우 빌드엔 torch.distributed 가 비어 있다 — audiotools 가 기본 인자로만 쓴다(10-08)
    _dist.ReduceOp = type('ReduceOp', (), {k: k.lower() for k in ('SUM', 'AVG', 'MAX', 'MIN', 'PRODUCT')})
    _dist.is_initialized = getattr(_dist, 'is_initialized', lambda: False)
torch.backends.cudnn.enabled = False   # MIOpen 합성곱이 매우 느리다(voice-gen qwen3_trial.py 와 같음)

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


def trim(w, sr, sec):
    """창 안에서 소리가 시작되는 곳(20ms 봉우리가 최댓값의 10% 를 처음 넘는 곳, 20ms 앞)부터 sec 초 + 끝 30ms 페이드."""
    import numpy as np
    hop = sr // 50
    env = np.abs(w[:len(w) // hop * hop]).reshape(-1, hop).max(1)
    on = max(0, int(np.argmax(env > env.max() * 0.1)) - 1) * hop
    seg = w[on:on + int(sr * sec)].copy()
    f = min(len(seg), int(sr * 0.03))
    seg[-f:] *= np.linspace(1, 0, f)
    return seg


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--device', default='cuda')
    ap.add_argument('--steps', type=int, default=50)
    ap.add_argument('--model', default='OpenMOSS-Team/MOSS-SoundEffect-v2.0')
    ap.add_argument('--only', type=int, default=0)
    ap.add_argument('--window', type=int, default=10, help='만들 창(초) — 30 이면 원래 방식(한 개 18분), 10 = 약 4분')
    a = ap.parse_args()
    from moss_soundeffect_v2 import MossSoundEffectPipeline
    out = os.path.join(HERE, 'out', 'moss-' + datetime.date.today().strftime('%Y%m%d'))
    os.makedirs(out, exist_ok=True)
    t0 = time.time()
    pipe = MossSoundEffectPipeline.from_pretrained(a.model, torch_dtype=torch.bfloat16 if a.device != 'cpu' else torch.float32, device=a.device)
    pipe.engine.vram_management_enabled = True   # 8GB: 지금 단계에 쓰는 모델(인코더→DiT→VAE)만 GPU 에, 나머지는 CPU 로(diffsynth 기능, 10-08)
    pipe.engine.load_models_to_device([])
    print(f'model loaded {time.time() - t0:.0f}s')
    rows = ['# MOSS-SoundEffect v2.0 효과음 시험 ' + datetime.date.today().isoformat(), '', f'device {a.device} · steps {a.steps} · 로딩 {time.time() - t0:.0f}s', '',
            '| id | 프롬프트 | 초 | 생성 시간 |', '|---|---|---|---|']
    for sid, prompt, sec in (SFX[:a.only] if a.only else SFX):
        t1 = time.time()
        win = a.window                                   # 기본 파이프라인은 늘 30초 창을 만들고 앞을 자른다(30초 = 한 개 18분). 10-08 실측: 창 2~3초는 깨지고(내내 최대 음량),
        audio = pipe(prompt=prompt, seconds=win, num_inference_steps=a.steps, cfg_scale=4.0, max_inference_seconds=win)   # 5초는 소리가 창 뒤쪽에 놓여 앞 2초가 빔 → 창 전체를 만들고
        w = audio[0].detach().float().cpu().numpy().mean(0)                                                              # 소리가 시작되는 곳부터 길이만큼 자른다
        sf.write(os.path.join(out, sid + '.wav'), trim(w, pipe.sample_rate, sec), pipe.sample_rate)   # pipe.save_audio 는 torchaudio 2.9 라 torchcodec 이 필요(ROCm 휠 없음)
        rows.append(f'| {sid} | {prompt} | {sec} | {time.time() - t1:.1f}s |')
        print(sid, f'{time.time() - t1:.1f}s')
    open(os.path.join(out, 'sheet.md'), 'w', encoding='utf-8').write('\n'.join(rows) + '\n')
    print('→', out)


if __name__ == '__main__':
    main()
