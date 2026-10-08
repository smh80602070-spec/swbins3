# -*- coding: utf-8 -*-
"""saga K-0036 — 짧은 대사 일괄 합성: Qwen3-TTS VoiceDesign 으로 목소리 견본 → Base 복제로 전 줄 → 받아쓰기 점검 → 음량 맞춘 OGG.

  venv_qwen\\Scripts\\python.exe saga_voice_batch.py <voice_plan.json> [--only M1,F2] [--lines 3]
  → out\\saga-voice\\voices\\<v>.wav(+.txt 견본 문장) · out\\saga-voice\\<v>\\<줄 id>.ogg · out\\saga-voice\\report.json

계획 = saga 저장소 tools/asset-forge/data/voice_plan.json(voiceplan.py). 이어하기: 있는 견본·줄은 건너뛴다.
견본: 목소리마다 3개 뽑아 받아쓰기 오류 가장 낮은 것(≤ 10% 아니면 report 에 적고 그래도 씀). 줄: 오류 > 30% 면 최대 3번 다시.
8GB: 모델은 한 번에 하나(설계 → 받아쓰기 → 복제 → 받아쓰기, 사이마다 내린다). MIOpen 끔(10-08 실측 6배).
"""
import argparse
import gc
import json
import os
import sys
import time

import numpy as np
import soundfile as sf
import torch

torch.backends.cudnn.enabled = False
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from asr_check import cer  # noqa: E402

OUT = os.path.join(HERE, 'out', 'saga-voice')
KW = {'language': 'korean', 'task': 'transcribe', 'temperature': (0.0, 0.2, 0.4, 0.6, 0.8, 1.0), 'compression_ratio_threshold': 1.35, 'logprob_threshold': -1.0}


def free():
    gc.collect()
    torch.cuda.empty_cache()


def asr_all(pairs):
    """[(wav 경로, 원문)] → {경로: (오류율, 받아쓴 글)}"""
    from transformers import pipeline
    asr = pipeline('automatic-speech-recognition', model='openai/whisper-large-v3-turbo', torch_dtype=torch.float16, device='cuda')
    res = {}
    for p, ref in pairs:
        w, sr = sf.read(p, dtype='float32')
        h = asr({'raw': w, 'sampling_rate': sr}, generate_kwargs=KW)['text'].strip()
        res[p] = (float(cer(ref, h)), h)
    del asr
    free()
    return res


def finish(wav_path, ogg_path, target_db=-20.0):
    """앞뒤 무음 자르기 + RMS 음량 맞추기 + OGG(Vorbis)."""
    w, sr = sf.read(wav_path, dtype='float32')
    env = np.abs(w)
    on = np.where(env > env.max() * 0.02)[0]
    if len(on):
        w = w[max(0, on[0] - int(0.03 * sr)):min(len(w), on[-1] + int(0.08 * sr))]
    rms = np.sqrt(np.mean(w ** 2)) + 1e-9
    w = np.clip(w * (10 ** (target_db / 20) / rms), -0.98, 0.98)
    sf.write(ogg_path, w, sr, format='OGG', subtype='VORBIS')
    return float(len(w) / sr), float(20 * np.log10(np.sqrt(np.mean(w ** 2)) + 1e-9))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('plan')
    ap.add_argument('--only', default='')
    ap.add_argument('--lines', type=int, default=0, help='종류마다 앞 n 줄만(시험)')
    a = ap.parse_args()
    plan = json.load(open(a.plan, encoding='utf-8'))
    voices = {k: v for k, v in plan['voices'].items() if not a.only or k in a.only.split(',')}
    by_kind = {}
    for ln in plan['lines']:
        by_kind.setdefault(ln['kind'], []).append(ln)
    if a.lines:
        by_kind = {k: v[:a.lines] for k, v in by_kind.items()}
    os.makedirs(os.path.join(OUT, 'voices'), exist_ok=True)
    rep_p = os.path.join(OUT, 'report.json')
    rep = json.load(open(rep_p, encoding='utf-8')) if os.path.exists(rep_p) else {'voices': {}, 'lines': {}}
    from qwen_tts import Qwen3TTSModel

    # 1) 견본
    todo = [v for v in voices if not os.path.exists(os.path.join(OUT, 'voices', v + '.wav'))]
    if todo:
        m = Qwen3TTSModel.from_pretrained(plan['model']['design'], device_map='cuda', dtype=torch.bfloat16, attn_implementation='sdpa')
        cands = []
        for v in todo:
            for k in range(3):
                torch.manual_seed(1000 + k)
                wavs, sr = m.generate_voice_design(text=voices[v]['sample'], language='Korean', instruct=voices[v]['desc'])
                p = os.path.join(OUT, 'voices', f'{v}_cand{k}.wav')
                sf.write(p, wavs[0], sr)
                cands.append((v, p))
        del m
        free()
        sc = asr_all([(p, voices[v]['sample']) for v, p in cands])
        for v in todo:
            best = min((p for vv, p in cands if vv == v), key=lambda p: sc[p][0])
            os.replace(best, os.path.join(OUT, 'voices', v + '.wav'))
            open(os.path.join(OUT, 'voices', v + '.txt'), 'w', encoding='utf-8').write(voices[v]['sample'])
            rep['voices'][v] = {'cer': round(sc[best][0], 3), 'heard': sc[best][1], 'ok': sc[best][0] <= 0.10}
            print('VOICE', v, f'{sc[best][0] * 100:.0f}%', sc[best][1])
        for _, p in cands:
            if os.path.exists(p):
                os.remove(p)
        json.dump(rep, open(rep_p, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)

    # 2) 줄 — 복제 → 받아쓰기 → 나쁜 줄만 다시(최대 3번)
    jobs = []
    for v in voices:
        kinds = ['system'] if v == 'NA' else [k for k in by_kind if k != 'system']
        for k in kinds:
            for ln in by_kind.get(k, []):
                if not os.path.exists(os.path.join(OUT, v, ln['id'] + '.ogg')):
                    jobs.append((v, ln))
    for rnd in range(3):
        if not jobs:
            break
        m = Qwen3TTSModel.from_pretrained(plan['model']['clone'], device_map='cuda', dtype=torch.bfloat16, attn_implementation='sdpa')
        prompts = {}
        made = []
        t0 = time.time()
        for v, ln in jobs:
            if v not in prompts:
                prompts[v] = m.create_voice_clone_prompt(ref_audio=os.path.join(OUT, 'voices', v + '.wav'),
                                                         ref_text=open(os.path.join(OUT, 'voices', v + '.txt'), encoding='utf-8').read())
            torch.manual_seed(2000 + rnd)
            wavs, sr = m.generate_voice_clone(text=ln['text'], language='Korean', voice_clone_prompt=prompts[v])
            os.makedirs(os.path.join(OUT, v), exist_ok=True)
            p = os.path.join(OUT, v, ln['id'] + '.wav')
            sf.write(p, wavs[0], sr)
            made.append((v, ln, p))
        print(f'ROUND {rnd + 1} 합성 {len(made)} · {time.time() - t0:.0f}s')
        del m
        free()
        sc = asr_all([(p, ln['text']) for v, ln, p in made])
        nxt = []
        for v, ln, p in made:
            c, h = sc[p]
            key = f'{v}/{ln["id"]}'
            if c > 0.30 and rnd < 2:
                nxt.append((v, ln))
                rep['lines'][key] = {'cer': round(c, 3), 'heard': h, 'round': rnd + 1, 'ok': False}
                continue
            dur, db = finish(p, os.path.join(OUT, v, ln['id'] + '.ogg'))
            os.remove(p)
            rep['lines'][key] = {'cer': round(c, 3), 'heard': h, 'round': rnd + 1, 'ok': c <= 0.30, 'sec': round(dur, 2), 'db': round(db, 1)}
        json.dump(rep, open(rep_p, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        jobs = nxt
    ok = [v for v in rep['lines'].values() if v.get('ok')]
    print('VOICE_BATCH', len(rep['lines']), '줄 · ok', len(ok), '· 평균 오류', round(float(np.mean([v['cer'] for v in rep['lines'].values()])) * 100, 1) if rep['lines'] else 0, '%')


if __name__ == '__main__':
    main()
