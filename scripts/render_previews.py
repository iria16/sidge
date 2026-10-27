#!/usr/bin/env python3
import os
import pymupdf

pdf_dir = '/var/www/html/sidgev1/storage/constancias_prueba'
out_dir = '/mnt/c/Users/iriamar/.gemini/antigravity/brain/ff6db16e-2b34-48c6-bd63-4447e62f0def/storage_previews'
os.makedirs(out_dir, exist_ok=True)

files = [
    'constancia_estudio_activo.pdf',
    'constancia_inscripcion_activo.pdf',
    'test_recuadro_completo.pdf',
    'test_recuadro_linea.pdf',
    'test_recuadro_quitar.pdf'
]

for f in files:
    pdf_path = os.path.join(pdf_dir, f)
    if os.path.exists(pdf_path):
        doc = pymupdf.open(pdf_path)
        page = doc[0]
        pix = page.get_pixmap(dpi=150)
        out_name = f.replace('.pdf', '.png')
        out_path = os.path.join(out_dir, out_name)
        pix.save(out_path)
        print(f"Rendered: {out_name} ({pix.width}x{pix.height})")

print("Done rendering previews.")
