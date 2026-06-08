# backend/python/pdf_processor.py
import PyPDFForm
import pdfplumber
from pypdf import PdfReader
import json

class PDFModelador:
    def __init__(self, pdf_path):
        self.pdf_path = pdf_path
        self.reader = PdfReader(pdf_path)
        self.form = PyPDFForm.PyPDFForm(pdf_path)
        
    def detectar_campos_ia(self):
        """
        Detección automática de campos usando IA
        Basado en CommonForms [[62]]
        """
        campos_detectados = []
        
        # Extraer campos existentes del PDF
        if self.reader.get_fields():
            campos = self.reader.get_fields()
            for field_name, field_data in campos.items():
                campo_info = {
                    'nombre': field_name,
                    'tipo': self._clasificar_tipo_campo(field_data),
                    'pagina': field_data.get('/Page', 0),
                    'coordenadas': self._extraer_coordenadas(field_data),
                    'requerido': self._es_requerido(field_data)
                }
                campos_detectados.append(campo_info)
        
        # Detección visual con pdfplumber para PDFs sin campos
        if not campos_detectados:
            campos_detectados = self._detectar_campos_visuales()
            
        return campos_detectados
    
    def _detectar_campos_visuales(self):
        """
        Detectar campos basados en layout visual
        Usa pdfplumber para analizar estructura [[59]][[60]]
        """
        campos = []
        with pdfplumber.open(self.pdf_path) as pdf:
            for page_num, page in enumerate(pdf.pages):
                # Detectar líneas y rectángulos (campos potenciales)
                lines = page.extract_lines()
                rects = page.find_tables()
                
                # Detectar texto (labels)
                text_objects = page.extract_text_positions()
                
                # Lógica de IA para emparejar labels con campos
                campos_page = self._mapear_labels_con_campos(
                    text_objects, lines, page_num
                )
                campos.extend(campos_page)
        
        return campos
    
    def convertir_a_html_form(self, campos):
        """
        Generar formulario HTML basado en campos detectados
        """
        html_template = """
        <form class="pdf-smart-form" data-pdf-id="{pdf_id}">
            {campos_html}
        </form>
        """
        
        campos_html = ""
        for campo in campos:
            campos_html += self._generar_campo_html(campo)
            
        return html_template.format(
            pdf_id=self.pdf_path,
            campos_html=campos_html
        )