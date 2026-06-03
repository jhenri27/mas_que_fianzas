-- =====================================================================
-- MIGRACIÓN: MÓDULO DE FIANZAS v1
-- MAS QUE FIANZAS +QF, SRL
-- NOFTRAB — Registro de cambios auditado
-- Fecha: 2026-06-02
-- =====================================================================

USE masque_fianzas_integrada_01;

-- =====================================================================
-- TABLA 1: ASEGURADORAS HABILITADAS PARA FIANZAS
-- =====================================================================
CREATE TABLE IF NOT EXISTS fianza_aseguradoras (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  codigo    VARCHAR(30)  NOT NULL UNIQUE,
  nombre    VARCHAR(100) NOT NULL,
  rnc       VARCHAR(20),
  logo_url  VARCHAR(255),
  estado    ENUM('activo','inactivo') DEFAULT 'activo',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 2: CATEGORÍAS INTERNAS (NUNCA VISIBLES AL CLIENTE U OPERADOR)
-- =====================================================================
CREATE TABLE IF NOT EXISTS fianza_categorias (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nombre          VARCHAR(100) NOT NULL,
  descripcion     TEXT,
  prima_minima    DECIMAL(12,2) DEFAULT 2500.00,
  modo_calculo    ENUM('A','B') DEFAULT 'B',
  -- A = Contractual: Monto Contrato × % a Afianzar → Valor a Afianzar → Prima
  -- B = Directo: Usuario ingresa Valor a Afianzar directamente → Prima
  visible_cliente TINYINT(1) DEFAULT 0, -- SIEMPRE 0 — NOFTRAB R1
  estado          ENUM('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 3: TARIFARIO EDITABLE (SOLO ADMIN — AUDITADO NOFTRAB)
-- =====================================================================
CREATE TABLE IF NOT EXISTS fianza_tarifarios (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  aseguradora_id        INT NOT NULL,
  categoria_id          INT NOT NULL,
  tipo_fianza           VARCHAR(100) NOT NULL,
  -- TASA: Información estratégica interna — NUNCA expuesta en UI cliente/operador
  tasa                  DECIMAL(8,4) NOT NULL,
  prima_minima_override DECIMAL(12,2) DEFAULT NULL, -- NULL = heredar de categoria
  estado                ENUM('activo','inactivo') DEFAULT 'activo',
  modificado_por        INT DEFAULT NULL,
  fecha_mod             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (aseguradora_id) REFERENCES fianza_aseguradoras(id) ON DELETE RESTRICT,
  FOREIGN KEY (categoria_id)   REFERENCES fianza_categorias(id) ON DELETE RESTRICT,
  UNIQUE KEY uk_tar (aseguradora_id, tipo_fianza),
  INDEX idx_aseguradora (aseguradora_id),
  INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 4: FIANZAS — CICLO DE VIDA COMPLETO
-- =====================================================================
CREATE TABLE IF NOT EXISTS fianzas (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  numero_fianza          VARCHAR(40) NOT NULL UNIQUE,
  cotizacion_id          INT DEFAULT NULL,
  aseguradora_id         INT NOT NULL,
  categoria_id           INT NOT NULL,
  tipo_fianza            VARCHAR(100) NOT NULL,
  -- DECLARACIONES LEGALES (Paso 1 del wizard)
  declaracion_veracidad  TINYINT(1) DEFAULT 0,
  declaracion_cesion     TINYINT(1) DEFAULT 0,
  -- DATOS DEL CLIENTE (Paso 2 del wizard)
  cliente_nombre         VARCHAR(200) NOT NULL,
  cliente_cedula         VARCHAR(30),
  cliente_telefono       VARCHAR(30),
  cliente_email          VARCHAR(120),
  objeto_referencia      TEXT,            -- "Nombre, Objeto o Referencia del Proyecto u Obligación"
  beneficiario           VARCHAR(255),
  primer_requerimiento   TINYINT(1) DEFAULT 0, -- 0=No, 1=Sí → activa cláusula en PDF
  -- DATOS DE LA FIANZA (Paso 3 del wizard)
  numero_contrato        VARCHAR(100),
  monto_contrato         DECIMAL(15,2) DEFAULT NULL, -- Solo Modo A (contractual)
  porcentaje_afianzar    DECIMAL(8,2) DEFAULT NULL,  -- Solo Modo A (% del pliego)
  monto_afianzado        DECIMAL(15,2) NOT NULL,     -- Valor a Afianzar (ambos modos)
  plazo_meses            INT NOT NULL,
  fecha_inicio           DATE,
  fecha_vencimiento      DATE,
  -- RESULTADOS DEL CÁLCULO (la TASA no se almacena — NOFTRAB R1)
  prima_base             DECIMAL(15,2) NOT NULL,
  itbis                  DECIMAL(15,2) NOT NULL,
  total                  DECIMAL(15,2) NOT NULL,
  prima_minima_aplicada  TINYINT(1) DEFAULT 0,
  ncf                    VARCHAR(40),
  -- ESTADO Y TRAZABILIDAD
  estado                 ENUM('cotizacion','pendiente','vigente','vencida','cancelada','renovada') DEFAULT 'cotizacion',
  observaciones          TEXT,
  pdf_filename           VARCHAR(255),
  email_enviado          TINYINT(1) DEFAULT 0,
  creado_por             INT NOT NULL,
  creado_en              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  modificado_en          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (aseguradora_id) REFERENCES fianza_aseguradoras(id),
  FOREIGN KEY (categoria_id)   REFERENCES fianza_categorias(id),
  INDEX idx_estado (estado),
  INDEX idx_cliente (cliente_nombre),
  INDEX idx_fecha (creado_en),
  INDEX idx_aseguradora (aseguradora_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 5: DOCUMENTOS DESCARGABLES (Sección del módulo)
-- =====================================================================
CREATE TABLE IF NOT EXISTS fianza_documentos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(200) NOT NULL,
  descripcion TEXT,
  url_archivo VARCHAR(500),
  tipo        ENUM('plantilla','formulario','legal','otro') DEFAULT 'otro',
  publico     TINYINT(1) DEFAULT 1,
  orden       INT DEFAULT 0,
  estado      ENUM('activo','inactivo') DEFAULT 'activo',
  subido_por  INT,
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABLA 6: EMPRESAS DEL CLIENTE (Sección "Mis Empresas")
-- =====================================================================
CREATE TABLE IF NOT EXISTS fianza_empresas_cliente (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id      INT NOT NULL,
  razon_social    VARCHAR(200) NOT NULL,
  rnc             VARCHAR(20),
  direccion       TEXT,
  telefono        VARCHAR(30),
  email           VARCHAR(120),
  contacto_nombre VARCHAR(150),
  estado          ENUM('activo','inactivo') DEFAULT 'activo',
  creado_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- RBAC: PERMISOS DEL MÓDULO FIANZAS
-- =====================================================================
INSERT IGNORE INTO modulos (nombre_modulo, descripcion, icono, nombre_ruta, orden_menu, estado)
VALUES ('fianzas', 'Módulo de Gestión de Fianzas', 'fa-shield-halved', 'fianzas.html', 4, 'activo');

SET @mod_fianzas = (SELECT id FROM modulos WHERE nombre_modulo = 'fianzas' LIMIT 1);

INSERT IGNORE INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso)
VALUES
  (@mod_fianzas, 'Ver Fianzas',            'FIANZAS_VER',            'Consultar listado e historial de fianzas',          'consultar'),
  (@mod_fianzas, 'Crear Fianza',           'FIANZAS_CREAR',          'Crear y procesar nuevas cotizaciones de fianza',    'crear'),
  (@mod_fianzas, 'Editar Estado Fianza',   'FIANZAS_EDITAR',         'Cambiar estado del ciclo de vida de una fianza',    'editar'),
  (@mod_fianzas, 'Administrar Tarifarios', 'FIANZAS_ADMIN_TARIFARIO','Ver y modificar tasas internas del tarifario',      'completo'),
  (@mod_fianzas, 'Exportar Fianzas',       'FIANZAS_EXPORTAR',       'Exportar reportes de fianzas (PDF, Excel, CSV)',    'reportes');

-- Asignar permisos al perfil Administrador (nivel_jerarquico = 1)
SET @perfil_admin = (SELECT id FROM perfiles WHERE nivel_jerarquico = 1 ORDER BY id LIMIT 1);
SET @f_ver        = (SELECT id FROM funciones_modulo WHERE codigo_funcion = 'FIANZAS_VER' LIMIT 1);
SET @f_crear      = (SELECT id FROM funciones_modulo WHERE codigo_funcion = 'FIANZAS_CREAR' LIMIT 1);
SET @f_editar     = (SELECT id FROM funciones_modulo WHERE codigo_funcion = 'FIANZAS_EDITAR' LIMIT 1);
SET @f_admin_tar  = (SELECT id FROM funciones_modulo WHERE codigo_funcion = 'FIANZAS_ADMIN_TARIFARIO' LIMIT 1);
SET @f_exportar   = (SELECT id FROM funciones_modulo WHERE codigo_funcion = 'FIANZAS_EXPORTAR' LIMIT 1);

INSERT IGNORE INTO permisos_perfil (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, ver_reportes, exportar_datos)
VALUES
  (@perfil_admin, @f_ver,       @mod_fianzas, 1, 1, 0, 0, 0, 0),
  (@perfil_admin, @f_crear,     @mod_fianzas, 1, 1, 1, 0, 0, 0),
  (@perfil_admin, @f_editar,    @mod_fianzas, 1, 1, 1, 1, 0, 0),
  (@perfil_admin, @f_admin_tar, @mod_fianzas, 1, 1, 1, 1, 1, 1),
  (@perfil_admin, @f_exportar,  @mod_fianzas, 1, 1, 0, 0, 1, 1);

-- =====================================================================
-- SEED: ASEGURADORAS
-- =====================================================================
INSERT IGNORE INTO fianza_aseguradoras (codigo, nombre, rnc, estado) VALUES
  ('MULTISEGUROS', 'MultiSeguros',  '1-30-00001-4', 'activo'),
  ('MIDAS',        'Midas Seguros', '1-30-00002-1', 'activo');

-- =====================================================================
-- SEED: CATEGORÍAS INTERNAS
-- =====================================================================
INSERT IGNORE INTO fianza_categorias (nombre, descripcion, prima_minima, modo_calculo, visible_cliente) VALUES
  ('Judicial',
   'Fianzas judiciales penales y no penales',
   2500.00, 'B', 0),
  ('Construcción',
   'Fianzas para proyectos de construcción, licitaciones y contratos de obra',
   2500.00, 'A', 0),
  ('Aduanal',
   'Fianzas requeridas por la Dirección General de Aduanas',
   2500.00, 'B', 0),
  ('Comercial',
   'Fianzas para actividades comerciales y turísticas',
   2500.00, 'B', 0),
  ('Judiciales No Penales',
   'Fianzas judiciales de carácter no penal: embargos, pensiones, laborales',
   2500.00, 'B', 0);

-- =====================================================================
-- SEED: TARIFARIOS — MULTISEGUROS
-- =====================================================================
SET @ase_multi = (SELECT id FROM fianza_aseguradoras WHERE codigo = 'MULTISEGUROS');
SET @cat_jud   = (SELECT id FROM fianza_categorias WHERE nombre = 'Judicial');
SET @cat_con   = (SELECT id FROM fianza_categorias WHERE nombre = 'Construcción');
SET @cat_adu   = (SELECT id FROM fianza_categorias WHERE nombre = 'Aduanal');
SET @cat_com   = (SELECT id FROM fianza_categorias WHERE nombre = 'Comercial');
SET @cat_jnp   = (SELECT id FROM fianza_categorias WHERE nombre = 'Judiciales No Penales');

INSERT IGNORE INTO fianza_tarifarios (aseguradora_id, categoria_id, tipo_fianza, tasa, estado) VALUES
  -- Judicial (Modo B)
  (@ase_multi, @cat_jud, 'Fianza Judicial',                      0.0300, 'activo'),
  -- Construcción (Modo A)
  (@ase_multi, @cat_con, 'Licitación',                           0.0050, 'activo'),
  (@ase_multi, @cat_con, 'Seriedad de la Oferta',                0.0050, 'activo'),
  (@ase_multi, @cat_con, 'Fiel Cumplimiento',                    0.0100, 'activo'),
  (@ase_multi, @cat_con, 'Anticipo',                             0.0100, 'activo'),
  (@ase_multi, @cat_con, 'Vicios Ocultos',                       0.0110, 'activo'),
  -- Aduanal (Modo B)
  (@ase_multi, @cat_adu, 'Admisión Temporal',                    0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Certificado de Carga Marítima',        0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Pago de Diferencia de Impuestos',      0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Pago de Impuestos',                    0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Falta de Documentos',                  0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Admisión Temporal Ley 84/99',          0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Admisión Temporal Ley 3489',           0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Consolidador Aduanal',                 0.0150, 'activo'),
  (@ase_multi, @cat_adu, 'Bebidas Alcohólicas',                  0.0150, 'activo'),
  -- Comercial (Modo B)
  (@ase_multi, @cat_com, 'Estafeta de Pago',                     0.0150, 'activo'),
  (@ase_multi, @cat_com, 'Agencias de Viajes',                   0.0150, 'activo'),
  (@ase_multi, @cat_com, 'Tour Operador',                        0.0150, 'activo'),
  -- Judiciales No Penales (Modo B)
  (@ase_multi, @cat_jnp, 'Embargos Precautorios',                0.0500, 'activo'),
  (@ase_multi, @cat_jnp, 'Pensión Alimenticia',                  0.0500, 'activo'),
  (@ase_multi, @cat_jnp, 'Inmobiliarias o Desahucio',            0.0500, 'activo'),
  (@ase_multi, @cat_jnp, 'Laborales y Amparos',                  0.0500, 'activo');

-- =====================================================================
-- SEED: TARIFARIOS — MIDAS SEGUROS (mismo tarifario base)
-- =====================================================================
SET @ase_midas = (SELECT id FROM fianza_aseguradoras WHERE codigo = 'MIDAS');

INSERT IGNORE INTO fianza_tarifarios (aseguradora_id, categoria_id, tipo_fianza, tasa, estado) VALUES
  (@ase_midas, @cat_jud, 'Fianza Judicial',                      0.0300, 'activo'),
  (@ase_midas, @cat_con, 'Licitación',                           0.0050, 'activo'),
  (@ase_midas, @cat_con, 'Seriedad de la Oferta',                0.0050, 'activo'),
  (@ase_midas, @cat_con, 'Fiel Cumplimiento',                    0.0100, 'activo'),
  (@ase_midas, @cat_con, 'Anticipo',                             0.0100, 'activo'),
  (@ase_midas, @cat_con, 'Vicios Ocultos',                       0.0110, 'activo'),
  (@ase_midas, @cat_adu, 'Admisión Temporal',                    0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Certificado de Carga Marítima',        0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Pago de Diferencia de Impuestos',      0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Pago de Impuestos',                    0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Falta de Documentos',                  0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Admisión Temporal Ley 84/99',          0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Admisión Temporal Ley 3489',           0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Consolidador Aduanal',                 0.0150, 'activo'),
  (@ase_midas, @cat_adu, 'Bebidas Alcohólicas',                  0.0150, 'activo'),
  (@ase_midas, @cat_com, 'Estafeta de Pago',                     0.0150, 'activo'),
  (@ase_midas, @cat_com, 'Agencias de Viajes',                   0.0150, 'activo'),
  (@ase_midas, @cat_com, 'Tour Operador',                        0.0150, 'activo'),
  (@ase_midas, @cat_jnp, 'Embargos Precautorios',                0.0500, 'activo'),
  (@ase_midas, @cat_jnp, 'Pensión Alimenticia',                  0.0500, 'activo'),
  (@ase_midas, @cat_jnp, 'Inmobiliarias o Desahucio',            0.0500, 'activo'),
  (@ase_midas, @cat_jnp, 'Laborales y Amparos',                  0.0500, 'activo');

-- =====================================================================
-- SEED: DOCUMENTOS DESCARGABLES INICIALES
-- =====================================================================
INSERT IGNORE INTO fianza_documentos (nombre, descripcion, tipo, publico, orden) VALUES
  ('Solicitud de Cotización de Fianza', 'Formulario oficial para solicitar cotización de fianza', 'formulario', 1, 1),
  ('Declaración de Veracidad', 'Texto completo de la Declaración de Veracidad y Autorización', 'legal', 1, 2),
  ('Autorización de Cesión de Información', 'Texto completo de la Autorización de Administración y Cesión', 'legal', 1, 3);

-- =====================================================================
-- FIN DE MIGRACIÓN
-- =====================================================================
SELECT 'Migración fianzas_v1 completada exitosamente' AS resultado;
