# Seamless-tile node for saga tools/ai-art (K-0020 floor tiles).
# A1111's "tiling" option sets every Conv2d to circular padding so the left/right and top/bottom
# edges wrap. ComfyUI core has no such switch, so this node patches the padding mode in place.
# gen.py puts this node in EVERY workflow (enabled = item.tiling), so a non-tiling run resets the
# shared modules back to zero padding. Only conv-based denoisers tile (SDXL UNet); a DiT (Z-Image)
# has no conv blocks, so there only the VAE edges wrap and the picture itself will not tile.
import torch


def _set_padding(module, mode):
    for m in module.modules():
        if isinstance(m, torch.nn.Conv2d):
            m.padding_mode = mode


class SagaSeamlessTiling:
    @classmethod
    def INPUT_TYPES(cls):
        return {"required": {"model": ("MODEL",), "vae": ("VAE",), "enabled": ("BOOLEAN", {"default": True})}}

    RETURN_TYPES = ("MODEL", "VAE")
    FUNCTION = "apply"
    CATEGORY = "saga"

    def apply(self, model, vae, enabled):
        mode = "circular" if enabled else "zeros"
        _set_padding(model.model, mode)
        _set_padding(vae.first_stage_model, mode)
        return (model, vae)


NODE_CLASS_MAPPINGS = {"SagaSeamlessTiling": SagaSeamlessTiling}
NODE_DISPLAY_NAME_MAPPINGS = {"SagaSeamlessTiling": "Saga seamless tiling (circular padding)"}
