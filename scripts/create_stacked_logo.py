from PIL import Image
import numpy as np

# Load original logo
img = Image.open("/var/www/html/sigdev1/public/assets/img/ministerio_logo_bandera.png")
rgb = np.array(img.convert("RGBA"))

# The flag is on the left: x < 200
# The text is on the right: x >= 210
flag_crop = img.crop((0, 0, 205, img.height))
# Trim flag transparent borders
flag_rgb = np.array(flag_crop.convert("RGB"))
flag_mask = (flag_rgb < 250).any(axis=2)
coords = np.argwhere(flag_mask)
fy0, fx0 = coords.min(axis=0)
fy1, fx1 = coords.max(axis=0) + 1
flag = flag_crop.crop((fx0, fy0, fx1, fy1))

# Text crop
text_crop = img.crop((215, 0, img.width, img.height))
text_rgb = np.array(text_crop.convert("RGB"))
text_mask = (text_rgb < 250).any(axis=2)
tcoords = np.argwhere(text_mask)
ty0, tx0 = tcoords.min(axis=0)
ty1, tx1 = tcoords.max(axis=0) + 1
text = text_crop.crop((tx0, ty0, tx1, ty1))

print("Flag size:", flag.size)
print("Text size:", text.size)

# Create a stacked image: flag on top, text underneath, centered
# Target width ~ 450
target_w = max(flag.width, text.width) + 20
total_h = flag.height + text.height + 15

stacked = Image.new("RGBA", (target_w, total_h), (255, 255, 255, 0))
# Paste flag centered at top
flag_x = (target_w - flag.width) // 2
stacked.paste(flag, (flag_x, 0), flag.convert("RGBA"))
# Paste text centered at bottom
text_x = (target_w - text.width) // 2
stacked.paste(text, (text_x, flag.height + 15), text.convert("RGBA"))

stacked.save("/mnt/c/Users/iriamar/.gemini/antigravity/brain/ff6db16e-2b34-48c6-bd63-4447e62f0def/ministerio_stacked.png")
print("Saved ministerio_stacked.png. Size:", stacked.size, "Ratio:", stacked.width / stacked.height)
