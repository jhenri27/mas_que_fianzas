-- =====================================================
-- DATOS INICIALES: MAS QUE FIANZAS
-- Script para insertar solo datos mínimos de prueba
-- =====================================================

USE masque_fianzas_integrada;

-- Insertar perfiles mínimos
INSERT IGNORE INTO perfiles (id, nombre_perfil, descripcion, nivel_jerarquico, estado, es_predeterminado) 
VALUES 
(1, 'Administrador', 'Acceso total al sistema', 1, 'activo', 0),
(8, 'Usuario', 'Usuario estándar del sistema', 8, 'activo', 1);

-- Insertar usuario administrador
-- Usuario: admin
-- Contraseña: Demo@123
-- Hash bcrypt generado con: password_hash('Demo@123', PASSWORD_BCRYPT, ['cost' => 10])
INSERT IGNORE INTO usuarios (cedula, nombre, apellido, email, username, password_hash, perfil_id, estado, requiere_cambio_password, activo_desde, creado_por)
VALUES ('0000000000', 'Administrador', 'Sistema', 'admin@masquefianzas.com', 'admin', '$2y$10$6gMrP.WYW7LR1j0EwkkqWeP1KHhcXHZLKi4O0Dxt8hU5oVPYPKVdO', 1, 'activo', 0, NOW(), 1);

-- Insertar módulos
INSERT IGNORE INTO modulos (nombre_modulo, descripcion, icono, nombre_ruta, orden_menu, estado) VALUES
(1, 'dashboard', 'Dashboard Principal', '🏠', '/modulos/dashboard.php', 1, 'activo'),
(2, 'clientes', 'Gestión de Clientes', '👥', '/modulos/clientes.php', 2, 'activo'),
(3, 'polizas', 'Gestión de Pólizas', '📋', '/modulos/polizas.php', 3, 'activo'),
(4, 'fianzas', 'Gestión de Fianzas', '🛡️', '/modulos/fianzas.php', 4, 'activo'),
(5, 'pagos', 'Registro y Control de Pagos', '💰', '/modulos/pagos.php', 5, 'activo'),
(6, 'cotizaciones', 'Sistema de Cotizaciones', '📈', '/modulos/cotizaciones.php', 6, 'activo'),
(7, 'productos', 'Gestión de Productos', '🧾', '/modulos/productos.php', 7, 'activo'),
(8, 'configuracion', 'Configuración del Sistema', '⚙️', '/modulos/configuracion.php', 8, 'activo'),
(9, 'reportes', 'Reportes y Análisis', '📊', '/modulos/reportes.php', 9, 'activo'),
(10, 'siniestros', 'Gestión de Siniestros', '⚠️', '/modulos/siniestros.php', 10, 'activo'),
(11, 'usuarios', 'Gestión de Usuarios y Perfiles', '👨‍💼', '/modulos/usuarios.php', 11, 'activo');

-- Insertar configuración del sistema
INSERT IGNORE INTO configuracion_sistema (clave_config, valor_config, tipo_valor, descripcion) VALUES
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

-- Fin del script
