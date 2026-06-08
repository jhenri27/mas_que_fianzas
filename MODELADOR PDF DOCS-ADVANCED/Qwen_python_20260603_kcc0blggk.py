# backend/python/mysql_storage.py
import mysql.connector
from mysql.connector import Error
import json

class FormDataStorage:
    def __init__(self):
        self.connection = self.create_connection()
    
    def create_connection(self):
        try:
            connection = mysql.connector.connect(
                host='localhost',
                database='modelador_pdf',
                user='root',
                password=''
            )
            return connection
        except Error as e:
            print(f"Error connecting to MySQL: {e}")
            return None
    
    def save_submission(self, pdf_id, form_data, files=None):
        """
        Guardar submission en MySQL
        """
        cursor = self.connection.cursor()
        
        try:
            # Insertar submission principal
            submission_query = """
                INSERT INTO pdf_submissions 
                (pdf_id, submission_token, datos_respuesta, pdf_generado)
                VALUES (%s, %s, %s, %s)
            """
            
            token = self.generate_token()
            datos_json = json.dumps(form_data)
            
            cursor.execute(submission_query, (
                pdf_id, 
                token, 
                datos_json,
                None  # Se actualizará cuando se genere el PDF
            ))
            
            submission_id = cursor.lastrowid
            
            # Guardar datos individuales por campo (para búsquedas)
            for field_name, field_value in form_data.items():
                field_query = """
                    INSERT INTO submission_field_data
                    (submission_id, field_id, field_name, field_value)
                    VALUES (%s, %s, %s, %s)
                """
                
                field_id = self.get_field_id_by_name(pdf_id, field_name)
                
                cursor.execute(field_query, (
                    submission_id,
                    field_id,
                    field_name,
                    str(field_value) if not isinstance(field_value, str) else field_value
                ))
            
            # Si hay archivos, guardarlos
            if files:
                self.save_files(submission_id, files)
            
            self.connection.commit()
            return submission_id, token
            
        except Error as e:
            self.connection.rollback()
            print(f"Error saving submission: {e}")
            raise
        finally:
            cursor.close()
    
    def generate_pdf_from_submission(self, submission_id, pdf_template_path, data):
        """
        Generar PDF completado con los datos del formulario
        Usa PyPDFForm para llenar el PDF [[36]][[37]]
        """
        from PyPDFForm import PyPDFForm
        
        form = PyPDFForm(pdf_template_path)
        
        # Llenar campos con datos
        for field_name, field_value in data.items():
            if field_name in form.fields:
                form.fill_field(field_name, field_value)
        
        # Guardar PDF generado
        output_path = f"/uploads/pdfs/completed/submission_{submission_id}.pdf"
        form.dump(output_path)
        
        # Actualizar registro en BD
        cursor = self.connection.cursor()
        update_query = """
            UPDATE pdf_submissions 
            SET pdf_generado = %s, estado = 'completado'
            WHERE id = %s
        """
        cursor.execute(update_query, (output_path, submission_id))
        self.connection.commit()
        cursor.close()
        
        return output_path