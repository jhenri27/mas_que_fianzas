# backend/python/pdf_extractor.py
import sys
import os
import json
import fitz  # PyMuPDF

def scan_pdf(pdf_path):
    """
    Escanea un archivo PDF y retorna sus páginas con dimensiones y campos interactivos.
    """
    if not os.path.exists(pdf_path):
        return {"exito": False, "mensaje": f"El archivo no existe: {pdf_path}"}
    
    try:
        doc = fitz.open(pdf_path)
        pages_info = []
        fields_info = []
        
        # 1. Obtener dimensiones de páginas (en mm, 1 pt = 25.4 / 72 mm)
        pt_to_mm = 25.4 / 72.0
        for i, page in enumerate(doc):
            w_mm = page.rect.width * pt_to_mm
            h_mm = page.rect.height * pt_to_mm
            pages_info.append({
                "pagina": i + 1,
                "ancho_mm": round(w_mm, 2),
                "alto_mm": round(h_mm, 2)
            })
            
            # 2. Leer campos interactivos (widgets) en esta página
            for widget in page.widgets():
                w_rect = widget.rect
                fields_info.append({
                    "nombre": widget.field_name,
                    "tipo": widget.field_type_string,  # 'Text', 'CheckBox', etc.
                    "pagina": i + 1,
                    "rect": [
                        round(w_rect.x0 * pt_to_mm, 2),
                        round(w_rect.y0 * pt_to_mm, 2),
                        round(w_rect.x1 * pt_to_mm, 2),
                        round(w_rect.y1 * pt_to_mm, 2)
                    ],
                    "valor": widget.field_value
                })
                
        doc.close()
        return {
            "exito": True,
            "paginas": pages_info,
            "campos": fields_info
        }
    except Exception as e:
        return {"exito": False, "mensaje": str(e)}

def hex_to_rgb(hex_str):
    hex_str = (hex_str or "#000000").lstrip('#')
    if len(hex_str) == 6:
        r = int(hex_str[0:2], 16) / 255.0
        g = int(hex_str[2:4], 16) / 255.0
        b = int(hex_str[4:6], 16) / 255.0
        return (r, g, b)
    return (0.0, 0.0, 0.0)

def map_font(font_family, font_weight):
    ff = (font_family or 'helvetica').lower()
    fw = (font_weight or 'normal').lower()
    
    if 'times' in ff:
        return 'tibo' if 'bold' in fw else 'tiro'
    elif 'courier' in ff:
        return 'cobo' if 'bold' in fw else 'cour'
    else:
        return 'hebo' if 'bold' in fw else 'helv'

def map_align(align_str):
    al = (align_str or 'left').lower()
    if al == 'center':
        return 1
    elif al == 'right':
        return 2
    return 0

def fill_pdf(template_path, output_path, data_json_path, meta_ancho=215.9, meta_alto=279.4):
    """
    Rellena una plantilla PDF con datos y la guarda en la ruta de salida.
    Soporta AcroForm y superposición por coordenadas.
    """
    if not os.path.exists(template_path):
        return {"exito": False, "mensaje": f"Plantilla no encontrada: {template_path}"}
    if not os.path.exists(data_json_path):
        return {"exito": False, "mensaje": f"Archivo de datos no encontrado: {data_json_path}"}
        
    try:
        with open(data_json_path, 'r', encoding='utf-8') as f:
            fields_data = json.load(f)
            
        doc = fitz.open(template_path)
        
        # Obtener los nombres de todos los widgets del documento para verificar existencia
        widget_names = set()
        for page in doc:
            for widget in page.widgets():
                if widget.field_name:
                    widget_names.add(widget.field_name)
                    
        for cf in fields_data:
            val = str(cf.get('value', ''))
            if not val:
                continue
                
            name = cf.get('nombre_campo_pdf')
            # Si tiene nombre_campo_pdf y el widget existe en el PDF, rellenar como widget
            if name and name in widget_names:
                for page in doc:
                    for widget in page.widgets():
                        if widget.field_name == name:
                            if widget.field_type_string == 'CheckBox':
                                if val in [True, 1, '1', 'Yes', 'checked', 'on']:
                                    widget.field_value = 'Yes'
                                else:
                                    widget.field_value = 'Off'
                            else:
                                widget.field_value = val
                            widget.update()
            else:
                # Escribir por coordenadas como fallback
                page_num = int(cf.get('pagina', 1))
                if page_num < 1 or page_num > len(doc):
                    continue
                    
                page = doc[page_num - 1]
                pos_x = float(cf.get('pos_x', 0))
                pos_y = float(cf.get('pos_y', 0))
                
                # Convertir mm a puntos relativos al tamaño real de la página en PDF
                plantilla_w = float(meta_ancho or 215.9)
                plantilla_h = float(meta_alto or 279.4)
                
                x_pts = (pos_x / plantilla_w) * page.rect.width
                y_pts = (pos_y / plantilla_h) * page.rect.height
                
                font_size = float(cf.get('font_size', 10))
                font_name = map_font(cf.get('font_family', 'helvetica'), cf.get('font_weight', 'normal'))
                color_rgb = hex_to_rgb(cf.get('color', '#000000'))
                alignment = map_align(cf.get('alineacion', 'left'))
                
                ancho = float(cf.get('ancho', 50.0))
                w_pts = (ancho / plantilla_w) * page.rect.width
                h_pts = max(20.0, font_size * 2.2)
                rect_box = fitz.Rect(x_pts, y_pts, x_pts + w_pts, y_pts + h_pts)
                
                # Si se activa el fondo opaco, dibujar un rectángulo blanco para borrar lo que hay detrás
                if cf.get('fondo_opaco', 0) in [1, '1', True]:
                    page.draw_rect(rect_box, color=(1,1,1), fill=(1,1,1), width=0)
                
                page.insert_textbox(
                    rect_box,
                    val,
                    fontsize=font_size,
                    fontname=font_name,
                    color=color_rgb,
                    align=alignment
                )
            
        # Guardar el PDF resultante
        # Evitamos incremental para aplanar widgets si es necesario (opcional)
        doc.save(output_path, clean=True)
        doc.close()
        
        return {"exito": True, "archivo_generado": output_path}
    except Exception as e:
        return {"exito": False, "mensaje": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"exito": False, "mensaje": "Argumentos insuficientes. Uso: python pdf_extractor.py <scan|fill> <params>"}))
        sys.exit(1)
        
    cmd = sys.argv[1]
    
    if cmd == "scan":
        pdf_path = sys.argv[2]
        res = scan_pdf(pdf_path)
        print(json.dumps(res, ensure_ascii=False))
        
    elif cmd == "fill":
        if len(sys.argv) < 6:
            print(json.dumps({"exito": False, "mensaje": "Faltan parámetros para la acción 'fill'. Uso: python pdf_extractor.py fill <template_path> <output_path> <data_json_path> [meta_ancho] [meta_alto]"}))
            sys.exit(1)
            
        template = sys.argv[2]
        output = sys.argv[3]
        data_json = sys.argv[4]
        ancho = float(sys.argv[5]) if len(sys.argv) > 5 else 215.9
        alto = float(sys.argv[6]) if len(sys.argv) > 6 else 279.4
        
        res = fill_pdf(template, output, data_json, ancho, alto)
        print(json.dumps(res, ensure_ascii=False))
        
    elif cmd == "img2pdf":
        if len(sys.argv) < 4:
            print(json.dumps({"exito": False, "mensaje": "Faltan parámetros para 'img2pdf'. Uso: python pdf_extractor.py img2pdf <img_path> <pdf_path>"}))
            sys.exit(1)
        img_path = sys.argv[2]
        pdf_path = sys.argv[3]
        try:
            imgdoc = fitz.open(img_path)
            pdfbytes = imgdoc.convert_to_pdf()
            imgdoc.close()
            outdoc = fitz.open("pdf", pdfbytes)
            outdoc.save(pdf_path)
            outdoc.close()
            print(json.dumps({"exito": True}))
        except Exception as e:
            print(json.dumps({"exito": False, "mensaje": str(e)}))
    else:
        print(json.dumps({"exito": False, "mensaje": f"Comando no reconocido: {cmd}"}))
