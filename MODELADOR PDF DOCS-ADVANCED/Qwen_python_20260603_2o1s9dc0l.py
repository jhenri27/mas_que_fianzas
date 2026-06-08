# backend/python/api.py
from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
from pdf_processor import PDFModelador
from mysql_storage import FormDataStorage
import os

app = Flask(__name__)
CORS(app)

storage = FormDataStorage()

@app.route('/api/pdf/upload', methods=['POST'])
def upload_pdf():
    """
    Subir PDF y detectar campos automáticamente
    """
    if 'file' not in request.files:
        return jsonify({'error': 'No file uploaded'}), 400
    
    file = request.files['file']
    if file.filename == '':
        return jsonify({'error': 'No file selected'}), 400
    
    # Guardar PDF temporal
    upload_path = f"/uploads/pdfs/{file.filename}"
    file.save(upload_path)
    
    # Procesar PDF
    processor = PDFModelador(upload_path)
    campos = processor.detectar_campos_ia()
    html_form = processor.convertir_a_html_form(campos)
    
    # Guardar en BD
    pdf_id = storage.save_pdf_document(
        nombre=file.filename,
        archivo_pdf=upload_path,
        campos=campos
    )
    
    return jsonify({
        'pdf_id': pdf_id,
        'campos_detectados': campos,
        'html_form': html_form,
        'preview_url': f'/api/pdf/preview/{pdf_id}'
    })

@app.route('/api/pdf/<int:pdf_id>/fields', methods=['POST'])
def update_fields(pdf_id):
    """
    Actualizar/configurar campos del formulario
    """
    data = request.json
    fields = data.get('fields', [])
    
    storage.update_pdf_fields(pdf_id, fields)
    
    return jsonify({'message': 'Fields updated successfully'})

@app.route('/api/form/submit', methods=['POST'])
def submit_form():
    """
    Recibir submission del formulario
    """
    data = request.json
    pdf_id = data.get('pdf_id')
    form_data = data.get('data', {})
    
    # Guardar en MySQL
    submission_id, token = storage.save_submission(
        pdf_id=pdf_id,
        form_data=form_data
    )
    
    # Generar PDF completado
    pdf_path = storage.generate_pdf_from_submission(
        submission_id=submission_id,
        pdf_template_path=storage.get_pdf_template_path(pdf_id),
        data=form_data
    )
    
    # Enviar notificaciones
    storage.send_notifications(pdf_id, submission_id, pdf_path)
    
    return jsonify({
        'success': True,
        'submission_id': submission_id,
        'token': token,
        'pdf_download': f'/api/pdf/download/{submission_id}'
    })

@app.route('/api/pdf/download/<int:submission_id>', methods=['GET'])
def download_pdf(submission_id):
    """
    Descargar PDF completado
    """
    pdf_path = storage.get_submission_pdf_path(submission_id)
    return send_file(pdf_path, as_attachment=True)

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)