# AMD ROCm (Windows) tweaks for AUTOMATIC1111. Copied to sd-webui\extensions\rocm-tweaks\ by the Radeon setup guide.
#
# On the ROCm 7.2 Windows PyTorch build, MIOpen (PyTorch's "cudnn" backend on AMD) falls back to very slow
# convolution solvers for the SD UNet ("workspace required: ..., provided ptr: 0 size: 0" warnings), so a
# 512x512 / 20-step image took ~130 s on an RX 7600. PyTorch's own convolution kernels are much faster here.
import torch

if getattr(torch.version, "hip", None):
    torch.backends.cudnn.enabled = False
    print("[rocm-tweaks] ROCm detected: disabled MIOpen (torch.backends.cudnn.enabled = False)")
