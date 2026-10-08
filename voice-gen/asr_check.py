# -*- coding: utf-8 -*-
"""음성 시험 받아쓰기 점검 — 뽑은 wav 를 Whisper 로 다시 받아써 원문과의 글자 오류율(CER)을 잰다(K-0082 단계 5 ② 보조).
또렷함(발음·빠뜨림·헛소리)만 본다 — 자연스러움·감정은 사람 귀 몫.

  venv_qwen\\Scripts\\python.exe asr_check.py out\\qwen3-<날짜>   → 그 폴더 asr.md (파일별 CER + 엔진·목소리별 평균)

모델 openai/whisper-large-v3-turbo(MIT). sheet.md·sheet_supertonic.md 의 표에서 원문을 읽는다.
"""
import os
import re
import sys

import soundfile as sf
import torch

torch.backends.cudnn.enabled = False   # ROCm 윈도우 MIOpen 느림(qwen3_trial.py 와 같음)


def norm(s):
    return re.sub(r'[^0-9A-Za-z가-힣]', '', s)


def cer(ref, hyp):
    r, h = norm(ref), norm(hyp)
    d = list(range(len(h) + 1))
    for i, rc in enumerate(r, 1):
        p, d[0] = d[0], i
        for j, hc in enumerate(h, 1):
            p, d[j] = d[j], min(d[j] + 1, d[j - 1] + 1, p + (rc != hc))
    return d[len(h)] / max(1, len(r))


def refs(folder):
    out = {}
    for name, pat, fn in (('sheet.md', r'^\| (\w+) \| .*? \| (\d+) \| (.*?) \| [\d.]+ \|', lambda m: f'{m[1]}_{int(m[2]):02d}.wav'),
                          ('sheet_supertonic.md', r'^\| ([MF]\d) \| (\d+) \| (.*?) \| [\d.]+ \|', lambda m: f'st_{m[1]}_{int(m[2]):02d}.wav')):
        p = os.path.join(folder, name)
        if os.path.exists(p):
            for line in open(p, encoding='utf-8'):
                m = re.match(pat, line)
                if m:
                    out[fn(m)] = m[3]
    return out


def main():
    folder = os.path.abspath(sys.argv[1])
    from transformers import pipeline
    asr = pipeline('automatic-speech-recognition', model='openai/whisper-large-v3-turbo', torch_dtype=torch.float16, device='cuda')
    rows, groups = [], {}
    for f, ref in sorted(refs(folder).items()):
        wav, sr = sf.read(os.path.join(folder, f), dtype='float32')
        if wav.ndim > 1:
            wav = wav.mean(1)
        hyp = asr({'raw': wav, 'sampling_rate': sr}, generate_kwargs={'language': 'korean', 'task': 'transcribe', 'temperature': (0.0, 0.2, 0.4, 0.6, 0.8, 1.0),
                                                                    'compression_ratio_threshold': 1.35, 'logprob_threshold': -1.0})['text'].strip()
        c = cer(ref, hyp)
        key = ('supertonic ' if f.startswith('st_') else 'qwen3 ') + f.rsplit('_', 1)[0].replace('st_', '')
        groups.setdefault(key, []).append(c)
        rows.append(f'| {f} | {c * 100:.0f}% | {hyp} |')
    head = ['# 받아쓰기 점검(Whisper large-v3-turbo) — 글자 오류율, 낮을수록 또렷', '', '| 엔진·목소리 | 평균 CER | 최악 |', '|---|---|---|']
    head += [f'| {k} | {sum(v) / len(v) * 100:.1f}% | {max(v) * 100:.0f}% |' for k, v in sorted(groups.items())]
    open(os.path.join(folder, 'asr.md'), 'w', encoding='utf-8').write('\n'.join(head + ['', '| 파일 | CER | 받아쓴 글 |', '|---|---|---|'] + rows) + '\n')
    print('\n'.join(head))


if __name__ == '__main__':
    main()
