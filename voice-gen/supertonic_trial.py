# -*- coding: utf-8 -*-
"""Supertonic 3 비교 짝 — qwen3_trial.py 와 같은 한국어 10줄을 목소리 셋으로 뽑아 나란히 듣게 한다(K-0082 단계 5 ②).

  venv\\Scripts\\python.exe supertonic_trial.py [--voices M3,F1,M1]
  → out\\qwen3-<날짜>\\st_<목소리>_<n>.wav + sheet_supertonic.md   (같은 폴더라 Qwen3 파일과 이름순으로 붙어 있다)

문장은 qwen3_trial.py 의 LINES 를 그대로 읽는다(그 파일은 torch 를 불러 이 venv 에서 import 못 하므로 글로 읽음). ONNX CPU.
"""
import argparse
import ast
import datetime
import os
import time

import soundfile as sf
from supertonic import TTS

HERE = os.path.dirname(os.path.abspath(__file__))


def lines():
    tree = ast.parse(open(os.path.join(HERE, 'qwen3_trial.py'), encoding='utf-8').read())
    for node in tree.body:
        if isinstance(node, ast.Assign) and any(getattr(t, 'id', '') == 'LINES' for t in node.targets):
            return ast.literal_eval(node.value)
    raise SystemExit('qwen3_trial.py 에 LINES 없음')


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--voices', default='M3,F1,M1', help='Qwen3 의 elder_m·young_f·knight_m 짝으로 고른 기본값')
    a = ap.parse_args()
    out = os.path.join(HERE, 'out', 'qwen3-' + datetime.date.today().strftime('%Y%m%d'))
    os.makedirs(out, exist_ok=True)
    t0 = time.time()
    tts = TTS(model='supertonic-3', model_dir=os.path.join(HERE, 'models', 'supertonic3'), auto_download=True)
    rows = ['# Supertonic 3 비교 짝 ' + datetime.date.today().isoformat(), '', f'로딩 {time.time() - t0:.0f}s · ONNX CPU', '',
            '| 목소리 | n | 문장 | 초 | 생성 시간 |', '|---|---|---|---|---|']
    for v in a.voices.split(','):
        style = tts.get_voice_style(voice_name=v)
        for n, text in enumerate(lines(), 1):
            t1 = time.time()
            wav, _ = tts.synthesize(text, voice_style=style, lang='ko', speed=1.0)
            w = wav.squeeze()
            sf.write(os.path.join(out, f'st_{v}_{n:02d}.wav'), w, tts.sample_rate)
            rows.append(f'| {v} | {n} | {text} | {len(w) / tts.sample_rate:.1f} | {time.time() - t1:.1f}s |')
    open(os.path.join(out, 'sheet_supertonic.md'), 'w', encoding='utf-8').write('\n'.join(rows) + '\n')
    print('→', out)


if __name__ == '__main__':
    main()
