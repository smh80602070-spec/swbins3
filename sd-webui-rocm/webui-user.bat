@echo off
rem AMD Radeon (Windows ROCm 7.2) version of webui-user.bat. Copy to sd-webui\webui-user.bat.
rem venv must already contain the ROCm torch wheels (see SETUP_GUIDE_RADEON.md).

set PYTHON=%~dp0venv\Scripts\python.exe
set GIT=
set VENV_DIR=
rem --medvram-sdxl: keep only the SDXL UNet in VRAM (8GB); without it SDXL 1024px spills into shared memory.
set COMMANDLINE_ARGS=--api --skip-python-version-check --medvram-sdxl
set STABLE_DIFFUSION_REPO=https://github.com/w-e-w/stablediffusion.git
set REQS_FILE=%~dp0..\sd-webui-rocm\requirements_versions_py312.txt
set TORCH_ROCM_AOTRITON_ENABLE_EXPERIMENTAL=1
rem Without this MIOpen exhaustively searches conv algorithms on first use and VAE decode appears to hang for 10+ minutes.
set MIOPEN_FIND_MODE=FAST

call webui.bat
