-- =====================================================
-- BASE DE DATOS: MAS QUE FIANZAS - GESTIÓN INTEGRADA
-- Sistema Limpio para WAMP
-- =====================================================

CREATE TABLE IF NOT EXISTS perfiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_perfil VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    nivel_jerarquico INT NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    es_predeterminado BOOLEAN DEFAULT 0,
    hereda_de INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_por INT,
    modificado_por INT
);

CREATE TABLE IF NOT EXISTS modulos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_modulo VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    icono VARCHAR(10),
    nombre_ruta VARCHAR(255),
    orden_menu INT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS funciones_modulo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    modulo_id INT NOT NULL,
    nombre_funcion VARCHAR(100) NOT NULL,
    codigo_funcion VARCHAR(50) NOT NULL,
    descripcion TEXT,
    tipo_permiso ENUM('crear', 'editar', 'eliminar', 'consultar', 'reportes', 'completo', 'validar', 'registrar', 'seguimiento', 'limitado') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_funcion (modulo_id, codigo_funcion)
);

CREATE TABLE IF NOT EXISTS permisos_perfil (
    id INT PRIMARY KEY AUTO_INCREMENT,
    perfil_id INT NOT NULL,
    funcion_id INT NOT NULL,
    modulo_id INT NOT NULL,
    puede_ejecutar BOOLEAN DEFAULT 1,
    ver_datos BOOLEAN DEFAULT 0,
    crear_datos BOOLEAN DEFAULT 0,
    editar_datos BOOLEAN DEFAULT 0,
    eliminar_datos BOOLEAN DEFAULT 0,
    ver_reportes BOOLEAN DEFAULT 0,
    exportar_datos BOOLEAN DEFAULT 0,
    solo_propios BOOLEAN DEFAULT 0,
    restriccion_segun_perfil BOOLEAN DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_por INT,
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id) ON DELETE CASCADE,
    FOREIGN KEY (funcion_id) REFERENCES funciones_modulo(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permiso (perfil_id, funcion_id)
);

CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    perfil_id INT,
    estado ENUM('activo', 'inactivo', 'bloqueado') DEFAULT 'inactivo',
    descripcion_bloqueo TEXT,
    intentos_fallidos INT DEFAULT 0,
    bloqueado_hasta TIMESTAMP NULL,
    requiere_cambio_password BOOLEAN DEFAULT 0,
    activo_desde TIMESTAMP NULL,
    fecha_ultimo_acceso TIMESTAMP NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_por INT,
    modificado_por INT,
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id) ON DELETE SET NULL,
    INDEX idx_email (email(50)),
    INDEX idx_username (username(50)),
    INDEX idx_estado (estado)
);

CREATE TABLE IF NOT EXISTS usuarios_perfiles_adicionales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    perfil_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_perfil_usuario (usuario_id, perfil_id)
);

CREATE TABLE IF NOT EXISTS auditoria_accesos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    tipo_evento VARCHAR(50),
    modulo VARCHAR(100),
    accion VARCHAR(100),
    descripcion TEXT,
    resultado ENUM('exitoso', 'fallido', 'advertencia') DEFAULT 'exitoso',
    mensaje_error TEXT,
    ip_origen VARCHAR(45),
    navegador TEXT,
    fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_evento),
    INDEX idx_tipo_evento (tipo_evento)
);

CREATE TABLE IF NOT EXISTS sesiones_usuario (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    token_sesion VARCHAR(100) NOT NULL UNIQUE,
    direccion_ip VARCHAR(45),
    navegador_user_agent VARCHAR(100),
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP,
    activa BOOLEAN DEFAULT 1,
    motivo_cierre VARCHAR(100),
    fecha_cierre TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token_sesion(50)),
    INDEX idx_usuario_id (usuario_id)
);

CREATE TABLE IF NOT EXISTS clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_cliente VARCHAR(20) NOT NULL UNIQUE,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100),
    razon_social VARCHAR(100),
    tipo_cliente ENUM('persona_natural', 'empresa') NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    direccion VARCHAR(255),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'inactivo', 'suspendido') DEFAULT 'activo'
);

CREATE TABLE IF NOT EXISTS configuracion_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave_config VARCHAR(100) NOT NULL UNIQUE,
    valor_config TEXT,
    tipo_valor VARCHAR(50),
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS historial_password (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
