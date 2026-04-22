-- ================================================================
-- MIGRACIÓN v3.0 — Módulo Pólizas Producción
-- Fixed for standard MySQL 8.0+ (No IF NOT EXISTS in ALTER TABLE)
-- ================================================================

USE masque_fianzas_integrada_01;

-- ----------------------------------------------------------------
-- TABLA: vehiculos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vehiculos (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id       INT NOT NULL,
    placa            VARCHAR(20)  UNIQUE,
    chasis           VARCHAR(50),
    motor            VARCHAR(50),
    marca            VARCHAR(60),
    modelo           VARCHAR(60),
    anio             YEAR,
    color            VARCHAR(40),
    tipo_vehiculo    VARCHAR(60),
    uso              ENUM('PRIVADO','PUBLICO','RENT CAR') DEFAULT 'PRIVADO',
    capacidad        VARCHAR(60),
    valor_comercial  DECIMAL(15,2),
    estado           ENUM('activo','inactivo') DEFAULT 'activo',
    creado_por       INT,
    fecha_creacion   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_placa (placa),
    INDEX idx_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- TABLA: polizas (Ampliación)
-- ----------------------------------------------------------------
-- Usamos múltiples ALTER TABLE simples para mayor compatibilidad
ALTER TABLE polizas ADD COLUMN numero_poliza_aseguradora VARCHAR(40) AFTER numero_poliza;
ALTER TABLE polizas ADD COLUMN vehiculo_id INT AFTER cliente_id;
ALTER TABLE polizas ADD COLUMN tipo_poliza ENUM('Individual','Flotilla','Colectiva') DEFAULT 'Individual' AFTER tipo_seguro;
ALTER TABLE polizas ADD COLUMN ramo VARCHAR(80) AFTER tipo_poliza;
ALTER TABLE polizas ADD COLUMN aseguradora VARCHAR(100) AFTER ramo;
ALTER TABLE polizas ADD COLUMN perfil_cobertura VARCHAR(80) AFTER aseguradora;
ALTER TABLE polizas ADD COLUMN prima_neta DECIMAL(15,2) DEFAULT 0 AFTER prima_total;
ALTER TABLE polizas ADD COLUMN itbis DECIMAL(15,2) DEFAULT 0 AFTER prima_neta;
ALTER TABLE polizas ADD COLUMN otros_cargos DECIMAL(15,2) DEFAULT 0 AFTER itbis;
ALTER TABLE polizas ADD COLUMN validada ENUM('Si','No') DEFAULT 'No';
ALTER TABLE polizas ADD COLUMN validada_por INT;
ALTER TABLE polizas ADD COLUMN fecha_validacion TIMESTAMP NULL;
ALTER TABLE polizas ADD COLUMN notas_internas TEXT;

-- Constraints
ALTER TABLE polizas ADD CONSTRAINT fk_polizas_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE SET NULL;
ALTER TABLE polizas ADD CONSTRAINT fk_polizas_validador FOREIGN KEY (validada_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- ----------------------------------------------------------------
-- TABLA: comisiones_poliza
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comisiones_poliza (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    poliza_id               INT NOT NULL,
    usuario_id              INT NOT NULL,
    tipo_comision           ENUM('intermediario','supervisor','red') NOT NULL,
    porcentaje_comision     DECIMAL(5,2) NOT NULL,
    monto_base              DECIMAL(15,2) NOT NULL,
    monto_comision          DECIMAL(15,2) NOT NULL,
    estado_pago             ENUM('pendiente','pagado','retenido') DEFAULT 'pendiente',
    fecha_calculo           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_pago              DATE NULL,
    pagado_por              INT NULL,
    referencia_pago         VARCHAR(100) NULL,
    FOREIGN KEY (poliza_id) REFERENCES polizas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (pagado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_poliza (poliza_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_estado (estado_pago)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- TABLA: pagos (Ampliación)
-- ----------------------------------------------------------------
ALTER TABLE pagos ADD COLUMN numero_recibo VARCHAR(30) AFTER numero_referencia;
ALTER TABLE pagos ADD COLUMN numero_ncf VARCHAR(20) AFTER numero_recibo;
ALTER TABLE pagos ADD COLUMN tipo_comprobante ENUM('B01','B02','B14','B15') DEFAULT 'B02' AFTER numero_ncf;
ALTER TABLE pagos ADD COLUMN itbis_pago DECIMAL(15,2) DEFAULT 0;
ALTER TABLE pagos ADD COLUMN descripcion VARCHAR(255);
ALTER TABLE pagos ADD COLUMN cuota_numero INT DEFAULT 1;
ALTER TABLE pagos ADD COLUMN cuota_total INT DEFAULT 1;

-- ----------------------------------------------------------------
-- TABLA: documentos_poliza
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documentos_poliza (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    poliza_id       INT NOT NULL,
    pago_id         INT NULL,
    tipo_documento  ENUM('marbete','solicitud','recibo','factura','carnet','endoso') NOT NULL,
    nombre_archivo  VARCHAR(200),
    ruta_archivo    VARCHAR(500),
    hash_documento  VARCHAR(64),
    generado_por    INT,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enviado_email   BOOLEAN DEFAULT 0,
    enviado_whatsapp BOOLEAN DEFAULT 0,
    fecha_envio_email TIMESTAMP NULL,
    fecha_envio_whatsapp TIMESTAMP NULL,
    destinatario_email VARCHAR(150),
    destinatario_telefono VARCHAR(20),
    FOREIGN KEY (poliza_id) REFERENCES polizas(id) ON DELETE CASCADE,
    FOREIGN KEY (pago_id)   REFERENCES pagos(id)   ON DELETE SET NULL,
    FOREIGN KEY (generado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_poliza_tipo (poliza_id, tipo_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- TABLA: asientos_contables
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asientos_contables (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    numero_asiento      VARCHAR(30) NOT NULL UNIQUE,
    fecha_asiento       DATE NOT NULL,
    descripcion         TEXT,
    modulo_origen       ENUM('polizas','pagos','siniestros','comisiones','fianzas') NOT NULL,
    referencia_id       INT NOT NULL,
    referencia_tipo     VARCHAR(50),
    estado              ENUM('borrador','publicado','anulado') DEFAULT 'borrador',
    creado_por          INT,
    fecha_creacion      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fecha (fecha_asiento),
    INDEX idx_referencia (referencia_id, referencia_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- TABLA: lineas_asiento
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lineas_asiento (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    asiento_id          INT NOT NULL,
    codigo_cuenta       VARCHAR(20) NOT NULL,
    nombre_cuenta       VARCHAR(150),
    tipo_movimiento     ENUM('debito','credito') NOT NULL,
    monto               DECIMAL(15,2) NOT NULL,
    descripcion_linea   VARCHAR(255),
    FOREIGN KEY (asiento_id) REFERENCES asientos_contables(id) ON DELETE CASCADE,
    INDEX idx_cuenta (codigo_cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- TABLA: catalogo_cuentas_super
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS catalogo_cuentas_super (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    codigo_cuenta   VARCHAR(20) NOT NULL UNIQUE,
    nombre_cuenta   VARCHAR(200) NOT NULL,
    tipo_cuenta     ENUM('activo','pasivo','patrimonio','ingreso','gasto') NOT NULL,
    nivel           INT DEFAULT 1,
    cuenta_padre    VARCHAR(20) NULL,
    acepta_movimiento BOOLEAN DEFAULT 1,
    descripcion     TEXT,
    estado          ENUM('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar cuentas principales Superintendencia de Seguros RD
INSERT IGNORE INTO catalogo_cuentas_super (codigo_cuenta, nombre_cuenta, tipo_cuenta, nivel, acepta_movimiento) VALUES
('1.1.01.01', 'Caja General', 'activo', 4, 1),
('1.1.01.02', 'Bancos - Cuentas Corrientes', 'activo', 4, 1),
('1.1.02.01', 'Primas por Cobrar - Vigentes', 'activo', 4, 1),
('1.1.02.02', 'Primas por Cobrar - Vencidas', 'activo', 4, 1),
('1.1.03.01', 'Comisiones por Cobrar', 'activo', 4, 1),
('2.1.01.01', 'Primas Cobradas por Adelantado', 'pasivo', 4, 1),
('2.1.02.01', 'Comisiones por Pagar - Agentes', 'pasivo', 4, 1),
('2.1.02.02', 'Comisiones por Pagar - Supervisores', 'pasivo', 4, 1),
('2.1.03.01', 'ITBIS por Pagar', 'pasivo', 4, 1),
('2.1.04.01', 'ISR Retenido por Pagar', 'pasivo', 4, 1),
('4.1.01.01', 'Primas Netas de Seguros - Automóviles', 'ingreso', 4, 1),
('4.1.01.02', 'Primas Netas de Seguros - Fianzas', 'ingreso', 4, 1),
('4.1.02.01', 'Comisiones Recibidas de Aseguradoras', 'ingreso', 4, 1),
('5.1.01.01', 'Comisiones Pagadas a Agentes', 'gasto', 4, 1),
('5.1.01.02', 'Comisiones Pagadas a Supervisores', 'gasto', 4, 1),
('5.1.02.01', 'Gastos de Emisión de Pólizas', 'gasto', 4, 1);
