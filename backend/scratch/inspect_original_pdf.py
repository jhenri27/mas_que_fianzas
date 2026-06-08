import fitz

pdf_path = "../../uploads/pdf_templates/Fianza-Multi_seguros-plantilla_1780683721.pdf"
doc = fitz.open(pdf_path)
print(f"Number of pages: {len(doc)}")
page = doc[0]
print(f"Page 1 Rect: {page.rect}")
print("\n--- Text in original template ---")
print(page.get_text())
print("\n--- Widgets in original template ---")
for widget in page.widgets():
    print(f"Widget: {widget.field_name} | Type: {widget.field_type_string} | Value: {widget.field_value}")
doc.close()
