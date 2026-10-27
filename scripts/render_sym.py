import fitz

doc = fitz.open("/tmp/test_size_11_perfect.pdf")
print("Page count:", len(doc))
page = doc.load_page(0)
pix = page.get_pixmap(dpi=150)
pix.save("/mnt/c/Users/iriamar/.gemini/antigravity/brain/ff6db16e-2b34-48c6-bd63-4447e62f0def/test_size_11_perfect.png")
print("Saved test_size_11_perfect.png")
