-- =====================================================
-- BASE DE DATOS: MAS QUE FIANZAS - GESTIÓN INTEGRADA
-- Sistema de Gestión de Usuarios, Perfiles y Permisos
-- =====================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS masque_fianzas_integrada;
USE masque_fianzas_integrada;

-- =====================================================
-- TABLA 1: PERFILES (Roles)
-- =====================================================
CREATE TABLE perfiles (
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
    modificado_por INT,
    FOREIGN KEY (hereda_de) REFERENCES perfiles(id) ON DELETE SET NULL
);

-- =====================================================
-- TABLA 2: MODULOS DISPONIBLES
-- =====================================================
CREATE TABLE modulos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_modulo VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    icono VARCHAR(50),
    nombre_ruta VARCHAR(100),
    orden_menu INT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- TABLA 3: FUNCIONES POR MODULO
-- =====================================================
CREATE TABLE funciones_modulo (
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

-- =====================================================
-- TABLA 4: PERMISOS POR PERFIL Y FUNCION
-- =====================================================
CREATE TABLE permisos_perfil (
    id INT PRIMARY KEY AUTO_INCREMENT,
    perfil_id INT NOT NULL,
    funcion_id INT NOT NULL,
    modulo_id INT NOT NULL,
    puede_ejecutar BOOLEAN DEFAULT 1,
    -- Campos específicos según el permiso
    ver_datos BOOLEAN DEFAULT 0,
    crear_datos BOOLEAN DEFAULT 0,
    editar_datos BOOLEAN DEFAULT 0,
    eliminar_datos BOOLEAN DEFAULT 0,
    ver_reportes BOOLEAN DEFAULT 0,
    exportar_datos BOOLEAN DEFAULT 0,
    -- Restricciones
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

-- =====================================================
-- TABLA 5: USUARIOS
-- =====================================================
CREATE TABLE usuarios (
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
    requiere_cambio_password BOOLEAN DEFAULT 1,
    ultimo_cambio_password TIMESTAMP,
    fecha_ultimo_acceso TIMESTAMP,
    intentos_fallidos INT DEFAULT 0,
    bloqueado_hasta TIMESTAMP,
    dos_factor_habilitado BOOLEAN DEFAULT 0,
    dos_factor_secret VARCHAR(255),
    activo_desde TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo_hasta TIMESTAMP,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_por INT,
    modificado_por INT,
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_estado (estado)
);

-- =====================================================
-- TABLA 6: HISTORIAL DE CAMBIOS DE PASSWORD
-- =====================================================
CREATE TABLE historial_password (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    password_anterior_hash VARCHAR(255),
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motivo VARCHAR(100),
    cambiado_por INT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cambiado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- =====================================================
-- TABLA 7: ASIGNACIÓN DE ROLES ADICIONALES A USUARIOS
-- =====================================================
CREATE TABLE usuarios_perfiles_adicionales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    perfil_id INT NOT NULL,
    puede_cambiar_perfil BOOLEAN DEFAULT 0,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id) ON DELETE CASCADE,
    FOREIGN KEY (asignado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY unique_perfil_usuario (usuario_id, perfil_id)
);

-- =====================================================
-- TABLA 8: AUDITORÍA DE ACCESOS
-- =====================================================
CREATE TABLE auditoria_accesos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    tipo_evento ENUM('login', 'logout', 'acceso_modulo', 'accion_fallida', 'intento_no_autorizado') NOT NULL,
    modulo_accedido VARCHAR(50),
    funcion_ejecutada VARCHAR(100),
    descripcion_evento TEXT,
    direccion_ip VARCHAR(45),
    navegador_user_agent VARCHAR(255),
    resultado ENUM('exitoso', 'fallido', 'advertencia') DEFAULT 'exitoso',
    detalles_error TEXT,
    tabla_afectada VARCHAR(50),
    registro_afectado_id INT,
    operacion_realizada ENUM('insert', 'update', 'delete', 'select', 'login', 'logout', 'cambio_permiso', 'cambio_dato_usuario') NOT NULL,
    valor_anterior JSON,
    valor_nuevo JSON,
    fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_evento),
    INDEX idx_tipo_evento (tipo_evento)
);

-- =====================================================
-- TABLA 9: SESIONES DE USUARIO
-- =====================================================
CREATE TABLE sesiones_usuario (
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
    INDEX idx_token (token_sesion(100)),
    INDEX idx_usuario_id (usuario_id)
);

-- =====================================================
-- TABLA 10: CLIENTES
-- =====================================================
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_cliente VARCHAR(20) NOT NULL UNIQUE,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100),
    razon_social VARCHAR(255),
    tipo_cliente ENUM('persona_natural', 'empresa') NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    direccion TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo', 'inactivo', 'suspendido') DEFAULT 'activo',
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);
);

-- =====================================================
-- TABLA 11: COTIZACIONES
-- =====================================================
CREATE TABLE cotizaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_cotizacion VARCHAR(30) NOT NULL UNIQUE,
    cliente_id INT,
    tipo_vehiculo VARCHAR(100),
    capacidad_cilindro VARCHAR(50),
    uso_vehiculo VARCHAR(50),
    valor_asegurado DECIMAL(15,2),
    prima_anual DECIMAL(15,2),
    prima_mensual DECIMAL(15,2),
    cobertura_adicional TEXT,
    vigencia_desde DATE,
    vigencia_hasta DATE,
    estado ENUM('pendiente', 'aceptada', 'rechazada', 'vencida') DEFAULT 'pendiente',
    contacto_solicitante VARCHAR(100),
    telefono_solicitante VARCHAR(20),
    email_solicitante VARCHAR(100),
    creado_por INT,
    aceptado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aceptacion TIMESTAMP,
    fecha_vigencia TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (aceptado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_fecha_creacion (fecha_creacion)
);

-- =====================================================
-- TABLA 12: PÓLIZAS
-- =====================================================
CREATE TABLE polizas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_poliza VARCHAR(30) NOT NULL UNIQUE,
    cotizacion_id INT,
    cliente_id INT NOT NULL,
    tipo_seguro VARCHAR(100),
    descripcion_cobertura TEXT,
    valor_asegurado DECIMAL(15,2),
    prima_total DECIMAL(15,2),
    periodicidad_pago ENUM('mensual', 'trimestral', 'semestral', 'anual') DEFAULT 'mensual',
    proxima_fecha_pago DATE,
    fecha_vencimiento DATE,
    estado ENUM('activa', 'suspendida', 'vencida', 'cancelada') DEFAULT 'activa',
    emitida_por INT,
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (emitida_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_numero_poliza (numero_poliza),
    INDEX idx_estado (estado),
    INDEX idx_cliente_id (cliente_id)
);

-- =====================================================
-- TABLA 13: PAGOS
-- =====================================================
CREATE TABLE pagos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_referencia VARCHAR(50) NOT NULL UNIQUE,
    poliza_id INT NOT NULL,
    cliente_id INT NOT NULL,
    monto DECIMAL(15,2),
    fecha_pago DATE,
    tipo_pago ENUM('efectivo', 'transferencia', 'cheque', 'tarjeta_credito', 'tarjeta_debito') NOT NULL,
    numero_comprobante VARCHAR(100),
    banco VARCHAR(100),
    estado_pago ENUM('pendiente', 'procesado', 'rechazado', 'reembolsado') DEFAULT 'pendiente',
    registrado_por INT,
    validado_por INT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_validacion TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (poliza_id) REFERENCES polizas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (validado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado_pago (estado_pago),
    INDEX idx_fecha_pago (fecha_pago)
);

-- =====================================================
-- TABLA 14: FIANZAS
-- =====================================================
CREATE TABLE fianzas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_fianza VARCHAR(30) NOT NULL UNIQUE,
    numero_fianza_aseguradora VARCHAR(30),
    poliza_id INT,
    cliente_id INT NOT NULL,
    tipo_fianza VARCHAR(100),
    monto_fianza DECIMAL(15,2),
    beneficiario VARCHAR(255),
    fecha_emission DATE,
    fecha_vencimiento DATE,
    estado ENUM('activa', 'pagada', 'cancelada', 'vencida') DEFAULT 'activa',
    razon_cancelacion TEXT,
    emitida_por INT,
    cancelada_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_cancelacion TIMESTAMP,
    FOREIGN KEY (poliza_id) REFERENCES polizas(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (emitida_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_numero_fianza (numero_fianza),
    INDEX idx_estado (estado)
);

-- =====================================================
-- TABLA 15: SINIESTROS
-- =====================================================
CREATE TABLE siniestros (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_siniestro VARCHAR(30) NOT NULL UNIQUE,
    poliza_id INT NOT NULL,
    cliente_id INT NOT NULL,
    fecha_siniestro DATE NOT NULL,
    descripcion_evento TEXT NOT NULL,
    lugar_evento TEXT,
    monto_reclamado DECIMAL(15,2),
    monto_aprobado DECIMAL(15,2),
    estado ENUM('registrado', 'en_revision', 'aprobado', 'rechazado', 'pagado') DEFAULT 'registrado',
    evidencia_adjunta VARCHAR(255),
    registrado_por INT,
    revisado_por INT,
    aprobado_por INT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_revision TIMESTAMP,
    fecha_aprobacion TIMESTAMP,
    FOREIGN KEY (poliza_id) REFERENCES polizas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_numero_siniestro (numero_siniestro),
    INDEX idx_estado (estado),
    INDEX idx_fecha_siniestro (fecha_siniestro)
);

-- =====================================================
-- TABLA 16: PRODUCTOS (TIPOS DE SEGUROS)
-- =====================================================
CREATE TABLE productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo_producto VARCHAR(50) NOT NULL UNIQUE,
    nombre_producto VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tipo_vehiculo VARCHAR(100),
    capacidad_motor VARCHAR(100),
    uso_vehiculo VARCHAR(50),
    vigencia_dias INT DEFAULT 365,
    estado ENUM('activo', 'inactivo', 'descontinuado') DEFAULT 'activo',
    prima_base DECIMAL(15,2),
    comision_venta DECIMAL(5,2),
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- =====================================================
-- TABLA 17: CONFIGURACION DEL SISTEMA
-- =====================================================
CREATE TABLE configuracion_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave_config VARCHAR(100) NOT NULL UNIQUE,
    valor_config TEXT,
    tipo_valor ENUM('texto', 'numero', 'booleano', 'json') DEFAULT 'texto',
    descripcion VARCHAR(255),
    modificable BOOLEAN DEFAULT 1,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modificado_por INT,
    FOREIGN KEY (modificado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- =====================================================
-- TABLA 18: REPORTES PERSONALIZADOS
-- =====================================================
CREATE TABLE reportes_personalizados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_reporte VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tipo_reporte ENUM('pólizas', 'pagos', 'clientes', 'siniestros', 'cotizaciones', 'financiero') NOT NULL,
    filtros_predefinidos JSON,
    campos_incluir JSON,
    orden_campos JSON,
    creado_por INT NOT NULL,
    es_publico BOOLEAN DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =====================================================
-- TABLA 19: ACCESOS A DATOS (Por Usuario)
-- =====================================================
CREATE TABLE acceso_datos_usuario (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    puede_ver_clientes BOOLEAN DEFAULT 0,
    puede_ver_polizas_clientes_propios BOOLEAN DEFAULT 0,
    puede_ver_todos_clientes BOOLEAN DEFAULT 0,
    puede_ver_pagos_propios BOOLEAN DEFAULT 0,
    puede_ver_todos_pagos BOOLEAN DEFAULT 0,
    puede_ver_cotizaciones_propias BOOLEAN DEFAULT 0,
    puede_ver_todas_cotizaciones BOOLEAN DEFAULT 0,
    puede_ver_siniestros_propios BOOLEAN DEFAULT 0,
    puede_ver_todos_siniestros BOOLEAN DEFAULT 0,
    restriccion_por_sucursal VARCHAR(100),
    restriccion_por_territorio VARCHAR(100),
    restriccion_por_producto JSON,
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY unique_usuario (usuario_id)
);

-- =====================================================
-- TABLA 20: NOTIFICACIONES Y ALERTAS
-- =====================================================
CREATE TABLE notificaciones_alertas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    titulo VARCHAR(200),
    mensaje TEXT,
    tipo_alerta ENUM('info', 'advertencia', 'error', 'exito') DEFAULT 'info',
    leida BOOLEAN DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_lectura TIMESTAMP,
    accion_asociada VARCHAR(255),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_leida (leida),
    INDEX idx_usuario_id (usuario_id)
);

-- =====================================================
-- INSERCIÓN DE DATOS INICIALES
-- =====================================================

-- Insertar perfiles
INSERT INTO perfiles (nombre_perfil, descripcion, nivel_jerarquico, estado, es_predeterminado) VALUES
('Administrador', 'Acceso total al sistema', 1, 'activo', 0),
('Gerente Técnico', 'Gestión técnica de pólizas y cotizaciones', 2, 'activo', 0),
('Gerente Contador', 'Gestión de pagos y reportes contables', 3, 'activo', 0),
('Gerente Comercial', 'Gestión comercial y clientes', 4, 'activo', 0),
('Socio Comercial', 'Acceso comercial limitado', 5, 'activo', 0),
('Cajero', 'Registro de pagos en caja', 6, 'activo', 0),
('Auditor', 'Acceso de auditoría a reportes', 7, 'activo', 0),
('Usuario', 'Usuario estándar del sistema', 8, 'activo', 1);

-- Insertar módulos
INSERT INTO modulos (nombre_modulo, descripcion, icono, nombre_ruta, orden_menu, estado) VALUES
('dashboard', 'Dashboard Principal', '🏠', '/modulos/dashboard.php', 1, 'activo'),
('clientes', 'Gestión de Clientes', '👥', '/modulos/clientes.php', 2, 'activo'),
('polizas', 'Gestión de Pólizas', '📋', '/modulos/polizas.php', 3, 'activo'),
('fianzas', 'Gestión de Fianzas', '🛡️', '/modulos/fianzas.php', 4, 'activo'),
('pagos', 'Registro y Control de Pagos', '💰', '/modulos/pagos.php', 5, 'activo'),
('cotizaciones', 'Sistema de Cotizaciones', '📈', '/modulos/cotizaciones.php', 6, 'activo'),
('productos', 'Gestión de Productos', '🧾', '/modulos/productos.php', 7, 'activo'),
('configuracion', 'Configuración del Sistema', '⚙️', '/modulos/configuracion.php', 8, 'activo'),
('reportes', 'Reportes y Análisis', '📊', '/modulos/reportes.php', 9, 'activo'),
('siniestros', 'Gestión de Siniestros', '⚠️', '/modulos/siniestros.php', 10, 'activo'),
('usuarios', 'Gestión de Usuarios y Perfiles', '👨‍💼', '/modulos/usuarios.php', 11, 'activo');

-- Insertar funciones por módulo (Dashboard)
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(1, 'Ver Dashboard Completo', 'DASH_COMPLETO', 'Acceso a todas las métricas del dashboard', 'completo'),
(1, 'Ver Dashboard Parcial', 'DASH_PARCIAL', 'Acceso limitado a métricas del dashboard', 'consultar');

-- Insertar funciones para Clientes
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(2, 'Crear Clientes', 'CLI_CREAR', 'Crear nuevos registros de clientes', 'crear'),
(2, 'Editar Clientes', 'CLI_EDITAR', 'Editar información de clientes existentes', 'editar'),
(2, 'Consultar Clientes', 'CLI_CONSULTAR', 'Consultar información de clientes', 'consultar'),
(2, 'Eliminar Clientes', 'CLI_ELIMINAR', 'Eliminar registros de clientes', 'eliminar'),
(2, 'Reportes Clientes', 'CLI_REPORTES', 'Ver reportes de clientes', 'reportes');

-- Insertar funciones para Pólizas
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(3, 'Crear Pólizas', 'POL_CREAR', 'Crear nuevas pólizas', 'crear'),
(3, 'Editar Pólizas', 'POL_EDITAR', 'Editar pólizas existentes', 'editar'),
(3, 'Consultar Pólizas', 'POL_CONSULTAR', 'Consultar pólizas', 'consultar'),
(3, 'Eliminar Pólizas', 'POL_ELIMINAR', 'Eliminar pólizas', 'eliminar'),
(3, 'Gestión Total Pólizas', 'POL_TOTAL', 'Acceso total a gestión de pólizas', 'completo');

-- Insertar funciones para Fianzas
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(4, 'Crear Fianzas', 'FI_CREAR', 'Crear nuevas fianzas', 'crear'),
(4, 'Editar Fianzas', 'FI_EDITAR', 'Editar fianzas existentes', 'editar'),
(4, 'Consultar Fianzas', 'FI_CONSULTAR', 'Consultar fianzas', 'consultar'),
(4, 'Gestión Total Fianzas', 'FI_TOTAL', 'Acceso total a fianzas', 'completo');

-- Insertar funciones para Pagos
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(5, 'Registrar Pagos', 'PAG_REGISTRAR', 'Registrar nuevos pagos', 'registrar'),
(5, 'Validar Pagos', 'PAG_VALIDAR', 'Validar pagos registrados', 'validar'),
(5, 'Ver Reportes Pagos', 'PAG_REPORTES', 'Ver reportes de pagos', 'reportes'),
(5, 'Consultar Pagos', 'PAG_CONSULTAR', 'Consultar pagos', 'consultar'),
(5, 'Gestión Total Pagos', 'PAG_TOTAL', 'Acceso total a pagos', 'completo');

-- Insertar funciones para Cotizaciones
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(6, 'Crear Cotizaciones', 'COT_CREAR', 'Crear nuevas cotizaciones', 'crear'),
(6, 'Editar Cotizaciones', 'COT_EDITAR', 'Editar cotizaciones existentes', 'editar'),
(6, 'Consultar Cotizaciones', 'COT_CONSULTAR', 'Consultar cotizaciones', 'consultar'),
(6, 'Gestión Total Cotizaciones', 'COT_TOTAL', 'Acceso total a cotizaciones', 'completo');

-- Insertar funciones para Productos
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(7, 'Crear Productos', 'PRO_CREAR', 'Crear nuevos productos', 'crear'),
(7, 'Editar Productos', 'PRO_EDITAR', 'Editar productos existentes', 'editar'),
(7, 'Consultar Productos', 'PRO_CONSULTAR', 'Consultar productos', 'consultar'),
(7, 'Gestión Total Productos', 'PRO_TOTAL', 'Acceso total a productos', 'completo');

-- Insertar funciones para Configuración
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(8, 'Ver Configuración', 'CONF_VER', 'Ver configuración del sistema', 'consultar'),
(8, 'Parámetros Técnicos', 'CONF_PARAMETROS_TECH', 'Gestionar parámetros técnicos', 'editar'),
(8, 'Parámetros Contables', 'CONF_PARAMETROS_CONT', 'Gestionar parámetros contables', 'editar'),
(8, 'Gestión Total Configuración', 'CONF_TOTAL', 'Acceso total a configuración', 'completo');

-- Insertar funciones para Reportes
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(9, 'Reportes Técnicos', 'REP_TECNICO', 'Acceso a reportes técnicos', 'reportes'),
(9, 'Reportes Financieros', 'REP_FINANCIERO', 'Acceso a reportes financieros', 'reportes'),
(9, 'Reportes Comerciales', 'REP_COMERCIAL', 'Acceso a reportes comerciales', 'reportes'),
(9, 'Reportes Caja', 'REP_CAJA', 'Acceso a reportes de caja', 'reportes'),
(9, 'Reportes Limitados', 'REP_LIMITADO', 'Acceso a reportes limitados', 'limitado'),
(9, 'Todos los Reportes', 'REP_TOTAL', 'Acceso a todos los reportes', 'completo');

-- Insertar funciones para Siniestros
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(10, 'Crear Siniestros', 'SIN_CREAR', 'Registrar nuevos siniestros', 'crear'),
(10, 'Seguimiento Siniestros', 'SIN_SEGUIMIENTO', 'Seguimiento de siniestros', 'seguimiento'),
(10, 'Consultar Siniestros', 'SIN_CONSULTAR', 'Consultar siniestros', 'consultar'),
(10, 'Consultar Propios Siniestros', 'SIN_PROPIOS', 'Consultar siniestros propios', 'consultar'),
(10, 'Gestión Total Siniestros', 'SIN_TOTAL', 'Acceso total a siniestros', 'completo');

-- Insertar funciones para Gestión de Usuarios
INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso) VALUES
(11, 'Crear Usuarios', 'USU_CREAR', 'Crear nuevos usuarios', 'crear'),
(11, 'Editar Usuarios', 'USU_EDITAR', 'Editar usuarios existentes', 'editar'),
(11, 'Bloquear Usuarios', 'USU_BLOQUEAR', 'Bloquear acceso de usuarios', 'editar'),
(11, 'Eliminar Usuarios', 'USU_ELIMINAR', 'Eliminar usuarios', 'eliminar'),
(11, 'Restablecer Contraseña', 'USU_PASS_RESET', 'Restablecer contraseñas de usuarios', 'editar'),
(11, 'Gestionar Perfiles', 'PER_GESTIONAR', 'Crear y editar perfiles', 'crear'),
(11, 'Asignar Permisos', 'PER_ASIGNAR', 'Asignar permisos a perfiles', 'editar'),
(11, 'Auditoría de Usuarios', 'USU_AUDITORIA', 'Ver auditoría de usuarios', 'reportes'),
(11, 'Gestión Total Usuarios', 'USU_TOTAL', 'Acceso total a gestión de usuarios', 'completo');

-- Crear usuario administrador por defecto
INSERT INTO usuarios (cedula, nombre, apellido, email, username, password_hash, perfil_id, estado, requiere_cambio_password, activo_desde, creado_por)
VALUES (
    '0000000000',
    'Administrador',
    'Sistema',
    'admin@masquefianzas.com',
    'admin',
    '$2y$10$6gMrP.WYW7LR1j0EwkkqWeP1KHhcXHZLKi4O0Dxt8hU5oVPYPKVdO',
    1,
    'activo',
    0,
    NOW(),
    1
);

-- Crear configuraciones iniciales
INSERT INTO configuracion_sistema (clave_config, valor_config, tipo_valor, descripcion) VALUES
('EMPRESA_NOMBRE', 'MAS QUE FIANZAS', 'texto', 'Nombre de la empresa'),
('EMPRESA_RNC', '123456789', 'texto', 'RNC de la empresa'),
('EMPRESA_PAIS', 'Republica Dominicana', 'texto', 'País de operación'),
('INTENTOS_LOGIN_MAX', '5', 'numero', 'Máximo de intentos fallidos de login'),
('MINUTOS_BLOQUEO', '30', 'numero', 'Minutos de bloqueo tras exceder intentos'),
('DIAS_EXPIRACION_PASSWORD', '90', 'numero', 'Días de vigencia de contraseña'),
('SESION_TIMEOUT_MINUTOS', '30', 'numero', 'Minutos de inactividad para cerrar sesión'),
('AUDITORIA_HABILITADA', '1', 'booleano', 'Auditoría del sistema habilitada'),
('DOS_FACTOR_OPCIONAL', '1', 'booleano', 'Autenticación de dos factores opcional'),
('VERSION_SISTEMA', '1.0.0', 'texto', 'Versión actual del sistema');

-- Crear restricciones de índices adicionales
CREATE INDEX idx_usuarios_perfil ON usuarios(perfil_id);
CREATE INDEX idx_usuarios_estado ON usuarios(estado);
CREATE INDEX idx_permisos_perfil ON permisos_perfil(perfil_id);
CREATE INDEX idx_funciones_modulo ON funciones_modulo(modulo_id);
CREATE INDEX idx_auditoria_usuario ON auditoria_accesos(usuario_id);
CREATE INDEX idx_auditoria_fecha ON auditoria_accesos(fecha_evento);

-- Fin del script de creación de base de datos
