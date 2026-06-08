import fitz
import os

pdf_dir = "c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/uploads/pdfs_aseguradoras/"
files = sorted([f for f in os.listdir(pdf_dir) if f.endswith(".pdf")])
if files:
    latest_pdf = os.path.join(pdf_dir, files[-1])
    print("Inspecting latest generated PDF:", latest_pdf)
    doc = fitz.open(latest_pdf)
    for i, page in enumerate(doc):
        print(f"--- Page {i+1} Text ---")
        print(page.get_text())
    doc.close()
else:
    print("No generated PDFs found.")
