# -*- coding: utf-8 -*-
"""
ACE-Step API 서버(acestep.api_server)를 띄우는 래퍼. music-gen/server.py 가 ACE-Step 폴더를 cwd 로 두고 실행한다.

AMD ROCm 7.2 윈도우용 PyTorch 는 torch.distributed 를 빼고 빌드돼 있어서(is_available() == False),
ACE-Step 이 쓰는 vector_quantize_pytorch 가 import 할 때
"cannot import name 'group' from 'torch.distributed'" 로 죽는다(ACE-Step 이슈 #644).
분산 학습은 쓰지 않으므로, 그런 빌드일 때만 "분산 초기화 안 됨·프로세스 1개" 로 답하는 대체 함수를 채워 넣는다.
site-packages 를 직접 고치지 않으니 패키지를 다시 설치해도 그대로 동작한다.
"""
import os
import runpy
import sys
import types

import torch.distributed as dist

if not dist.is_available():
    dist.is_initialized = lambda: False
    dist.get_world_size = lambda group=None: 1
    dist.get_rank = lambda group=None: 0

    _nn = types.ModuleType("torch.distributed.nn")
    _nn.all_reduce = lambda tensor, *args, **kwargs: tensor
    sys.modules["torch.distributed.nn"] = _nn
    dist.nn = _nn

# 스크립트로 실행되면 sys.path[0] 이 이 파일의 폴더라서 cwd(ACE-Step 폴더)의 acestep 패키지를 못 찾는다.
sys.path.insert(0, os.getcwd())
runpy.run_module("acestep.api_server", run_name="__main__", alter_sys=True)
