-- Tabla principal de PDFs
CREATE TABLE pdf_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    archivo_pdf_original VARCHAR(500),
    archivo_pdf_plantilla VARCHAR(500),
    categoria VARCHAR(100),
    paginas INT,
    estado ENUM('activo', 'inactivo', 'borrador') DEFAULT 'borrador',
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id)
);

-- Campos del formulario
CREATE TABLE pdf_form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pdf_id INT NOT NULL,
    nombre_campo VARCHAR(100) NOT NULL,
    etiqueta VARCHAR(255),
    tipo_campo ENUM('texto', 'numero', 'fecha', 'email', 'textarea', 
                    'checkbox', 'radio', 'select', 'firma', 'archivo',
                    'tabla', 'calculado') NOT NULL,
    posicion_x INT,
    posicion_y INT,
    ancho INT,
    alto INT,
    pagina INT DEFAULT 1,
    requerido BOOLEAN DEFAULT FALSE,
    validaciones JSON,
    valor_por_defecto VARCHAR(500),
    opciones JSON, -- Para select, radio, checkbox
    campo_calculo TEXT, -- Fórmula para campos calculados
    condicion_mostrar JSON, -- Reglas condicionales
    orden INT,
    FOREIGN KEY (pdf_id) REFERENCES pdf_documents(id) ON DELETE CASCADE
);

-- Mapeo campos PDF original
CREATE TABLE pdf_field_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_field_id INT NOT NULL,
    pdf_field_name VARCHAR(255),
    pdf_field_type VARCHAR(50),
    pdf_page INT,
    pdf_coordinates JSON,
    FOREIGN KEY (form_field_id) REFERENCES pdf_form_fields(id) ON DELETE CASCADE
);

-- Submissions/Respuestas
CREATE TABLE pdf_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pdf_id INT NOT NULL,
    submission_token VARCHAR(100) UNIQUE,
    datos_respuesta JSON NOT NULL,
    pdf_generado VARCHAR(500),
    estado ENUM('pendiente', 'completado', 'rechazado') DEFAULT 'pendiente',
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha_submission TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pdf_id) REFERENCES pdf_documents(id) ON DELETE CASCADE
);

-- Datos individuales de campos (para búsquedas/reportes)
CREATE TABLE submission_field_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    field_id INT NOT NULL,
    field_name VARCHAR(100),
    field_value TEXT,
    FOREIGN KEY (submission_id) REFERENCES pdf_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES pdf_form_fields(id) ON DELETE CASCADE,
    INDEX idx_field_search (field_name, field_value(100))
);

-- Plantillas predefinidas
CREATE TABLE pdf_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    categoria VARCHAR(100),
    descripcion TEXT,
    archivo_pdf VARCHAR(500),
    configuracion_campos JSON,
    es_publica BOOLEAN DEFAULT FALSE,
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Configuración de notificaciones
CREATE TABLE pdf_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pdf_id INT NOT NULL,
    tipo ENUM('email_admin', 'autoresponder', 'webhook') NOT NULL,
    destinatarios JSON,
    asunto VARCHAR(255),
    mensaje TEXT,
    incluir_pdf BOOLEAN DEFAULT TRUE,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (pdf_id) REFERENCES pdf_documents(id) ON DELETE CASCADE
);