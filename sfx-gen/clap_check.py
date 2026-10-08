# -*- coding: utf-8 -*-
"""효과음 시험 자동 점검 — CLAP(laion/larger_clap_general, Apache-2.0)으로 소리와 설명문이 얼마나 맞는지 잰다(K-0082 단계 5 ③ 보조).
moss_trial.py 의 12종 프롬프트마다: 그 소리와 자기 프롬프트의 유사도 + 12 프롬프트 중 자기 것이 몇 등인지(1 = 제일 맞음).
같은 표로 지금 게임에 쓰는 효과음(K-0045 절차 생성, saga-assets/sfx)의 짝도 재서 나란히 낸다. 음질·취향은 사람 귀 몫.

  ..\\voice-gen\\venv_qwen\\Scripts\\python.exe clap_check.py out\\moss-<날짜>   → 그 폴더 clap.md
"""
import ast
import os
import sys

import numpy as np
import soundfile as sf
import torch

HERE = os.path.dirname(os.path.abspath(__file__))
CUR = r'C:\swbins\saga-assets\sfx'
PAIR = {'hit_sword': 'sfx_sword_hit_1', 'hit_blunt': 'sfx_shield_hit_1', 'hit_arrow': 'sfx_bow_hit_1', 'step_grass': 'sfx_step_grass_1',
        'step_stone': 'sfx_step_stone_1', 'step_snow': 'sfx_step_snow_1', 'ui_click': 'sfx_ui_click', 'ui_confirm': 'sfx_ui_confirm',
        'pickup_coin': 'sfx_coin', 'pickup_herb': 'sfx_item_pick'}   # 지금 효과음에 짝이 없는 둘(늑대 으르렁·불 주문)은 MOSS 만


def sfx_table():
    tree = ast.parse(open(os.path.join(HERE, 'moss_trial.py'), encoding='utf-8').read())
    for node in tree.body:
        if isinstance(node, ast.Assign) and any(getattr(t, 'id', '') == 'SFX' for t in node.targets):
            return ast.literal_eval(node.value)


def load48(path):
    import torchaudio.functional as AF
    w, sr = sf.read(path, dtype='float32', always_2d=True)
    w = torch.from_numpy(w.mean(1))
    return AF.resample(w, sr, 48000).numpy() if sr != 48000 else w.numpy()


def main():
    folder = os.path.abspath(sys.argv[1])
    from transformers import ClapModel, ClapProcessor
    m = ClapModel.from_pretrained('laion/larger_clap_general').eval()
    proc = ClapProcessor.from_pretrained('laion/larger_clap_general')
    table = sfx_table()
    ids, prompts = [t[0] for t in table], [t[1] for t in table]
    with torch.no_grad():
        te = m.get_text_features(**proc(text=prompts, return_tensors='pt', padding=True))
        te = te / te.norm(dim=-1, keepdim=True)

        def score(path, i):
            a = proc(audios=[load48(path)], sampling_rate=48000, return_tensors='pt')
            ae = m.get_audio_features(**a)
            sims = (ae / ae.norm(dim=-1, keepdim=True) @ te.T)[0].numpy()
            return float(sims[i]), int((sims > sims[i]).sum()) + 1

        rows = ['# 효과음 CLAP 점검 — 유사도(높을수록 설명과 맞음)·12 프롬프트 중 등수(1 = 제일 맞음)', '',
                '| id | MOSS 유사도 | MOSS 등수 | 지금 효과음 | 지금 유사도 | 지금 등수 |', '|---|---|---|---|---|---|']
        agg = {'moss': [], 'cur': []}
        for i, sid in enumerate(ids):
            ms, mr = score(os.path.join(folder, sid + '.wav'), i)
            agg['moss'].append((ms, mr))
            cur = PAIR.get(sid)
            if cur:
                cs, cr = score(os.path.join(CUR, cur + '.ogg'), i)
                agg['cur'].append((cs, cr))
                rows.append(f'| {sid} | {ms:.3f} | {mr} | {cur} | {cs:.3f} | {cr} |')
            else:
                rows.append(f'| {sid} | {ms:.3f} | {mr} | — | | |')
    paired = [agg['moss'][i] for i, sid in enumerate(ids) if sid in PAIR]
    summ = [f'짝 있는 10종: MOSS 평균 유사도 {np.mean([s for s, _ in paired]):.3f}·1등 {sum(r == 1 for _, r in paired)}/10 · '
            f'지금 효과음 평균 {np.mean([s for s, _ in agg["cur"]]):.3f}·1등 {sum(r == 1 for _, r in agg["cur"])}/10 · '
            f'MOSS 12종 1등 {sum(r == 1 for _, r in agg["moss"])}/12']
    open(os.path.join(folder, 'clap.md'), 'w', encoding='utf-8').write('\n'.join(rows[:2] + summ + [''] + rows[2:]) + '\n')
    print('\n'.join(summ + rows[2:]))


if __name__ == '__main__':
    main()
