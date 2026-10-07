# -*- coding: utf-8 -*-
"""Qwen3-TTS 1.7B VoiceDesign 한국어 시험 — Supertonic 3 와 같은 문장 10줄을 "글로 설계한 목소리" 셋으로 뽑아 사용자가 듣고 고른다.
(2026-10-07 조사: Apache-2.0, 한국어 지원, 목소리 설계·감정 지시. Supertone 사 청산으로 Supertonic 은 목소리 10종이 끝이라 보완용.)

  venv_qwen\\Scripts\\python.exe qwen3_trial.py [--device cuda|cpu] [--model <로컬 폴더 또는 HF id>]
  → out\\qwen3-<날짜>\\<목소리>_<n>.wav + sheet.md (문장·목소리 설명·걸린 시간)

설치(별도 venv, ROCm torch 는 SETUP_GUIDE.md 의 휠 셋): pip install -U qwen-tts soundfile
ROCm 윈도우 빌드엔 flash-attn 이 없어 attn_implementation="sdpa" 로 둔다. 안 돌면 0.6B(CustomVoice) 또는 --device cpu.
"""
import argparse
import datetime
import os
import time

import soundfile as sf
import torch

HERE = os.path.dirname(os.path.abspath(__file__))
LINES = [  # 게임 대사 10줄 — 실존 인물·원작 이름 없음(SAGA 이름 정책)
    "어서 오세요. 오늘은 바람이 차니 따뜻한 국 한 그릇 드시고 가세요.",
    "저쪽 숲에 늑대가 늘었어요. 혼자 가지 마세요.",
    "이 검은 제 아버지가 벼린 겁니다. 값은 못 깎아 드려요.",
    "뒤로 물러서! 이번 공격은 내가 막는다!",
    "축하합니다. 새로운 동료가 합류했습니다.",
    "저장이 완료되었습니다.",
    "그 노래, 어디서 배운 거야? 참 오랜만에 듣네.",
    "성문은 해가 지면 닫힙니다. 서두르세요.",
    "하하, 그 정도 흥정으론 어림도 없지!",
    "다시는 이 마을에 발을 들이지 마라.",
]
VOICES = {
    'elder_m': "A calm elderly Korean man, low warm voice, slow and steady pace, village chief tone.",
    'young_f': "A bright young Korean woman, clear and lively, slightly fast, friendly merchant tone.",
    'knight_m': "A firm adult Korean man, strong chest voice, commanding and confident, battlefield tone.",
}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--device', default='cuda')
    ap.add_argument('--model', default='Qwen/Qwen3-TTS-12Hz-1.7B-VoiceDesign')
    ap.add_argument('--only', type=int, default=0, help='문장 n개만(빠른 확인)')
    a = ap.parse_args()
    from qwen_tts import Qwen3TTSModel
    out = os.path.join(HERE, 'out', 'qwen3-' + datetime.date.today().strftime('%Y%m%d'))
    os.makedirs(out, exist_ok=True)
    t0 = time.time()
    model = Qwen3TTSModel.from_pretrained(a.model, device_map=a.device, dtype=torch.bfloat16 if a.device != 'cpu' else torch.float32,
                                          attn_implementation='sdpa')
    print(f'model loaded {time.time() - t0:.0f}s')
    lines = LINES[:a.only] if a.only else LINES
    rows = ['# Qwen3-TTS VoiceDesign 한국어 시험 ' + datetime.date.today().isoformat(), '',
            f'모델 {a.model} · device {a.device} · 로딩 {time.time() - t0:.0f}s', '', '| 목소리 | 설명 | n | 문장 | 초 | 생성 시간 |', '|---|---|---|---|---|---|']
    for vid, desc in VOICES.items():
        for n, text in enumerate(lines, 1):
            t1 = time.time()
            wavs, sr = model.generate_voice_design(text=text, language='Korean', instruct=desc)
            p = os.path.join(out, f'{vid}_{n:02d}.wav')
            sf.write(p, wavs[0], sr)
            rows.append(f'| {vid} | {desc} | {n} | {text} | {len(wavs[0]) / sr:.1f} | {time.time() - t1:.1f}s |')
            print(f'{vid} {n} {time.time() - t1:.1f}s')
    open(os.path.join(out, 'sheet.md'), 'w', encoding='utf-8').write('\n'.join(rows) + '\n')
    print('→', out)


if __name__ == '__main__':
    main()
