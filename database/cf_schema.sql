-- ==========================================
-- PLAN DE EMERGENCIA: CENTRO FINANCIERO v3.0
-- SCHEMA SQL: NÚCLEO CONTABLE (CORE)
-- ==========================================

-- 1. Catálogo de Cuentas (Estandarizado SIS)
CREATE TABLE IF NOT EXISTS cf_catalogo_cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL, 
    nombre VARCHAR(200) NOT NULL,
    tipo ENUM('ACTIVO','PASIVO','PATRIMONIO','INGRESO','EGRESO','ORDEN') NOT NULL,
    naturaleza ENUM('DEUDORA','ACREEDORA') NOT NULL,
    nivel TINYINT DEFAULT 1,
    cuenta_padre VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    es_detalle BOOLEAN DEFAULT TRUE,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_padre (cuenta_padre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Períodos Contables (Control de Cierres)
CREATE TABLE IF NOT EXISTS cf_periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anio SMALLINT NOT NULL,
    mes TINYINT NOT NULL,
    estado ENUM('ABIERTO','CERRADO','BLOQUEADO') DEFAULT 'ABIERTO',
    fecha_inicio DATE,
    fecha_cierre DATETIME,
    cerrado_por INT,
    UNIQUE KEY uk_periodo (anio, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Libro Diario: Encabezado de Asientos
CREATE TABLE IF NOT EXISTS cf_asientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    fecha DATE NOT NULL,
    descripcion TEXT,
    tipo ENUM('AUTOMATICO','MANUAL','AJUSTE','CIERRE') DEFAULT 'AUTOMATICO',
    origen_modulo VARCHAR(50),
    origen_id INT,
    periodo_id INT,
    estado ENUM('BORRADOR','APROBADO','ANULADO') DEFAULT 'BORRADOR',
    creado_por INT,
    aprobado_por INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (periodo_id) REFERENCES cf_periodos(id),
    INDEX idx_fecha (fecha),
    INDEX idx_origen (origen_modulo, origen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Libro Diario: Líneas de Asiento (Detalle D/H)
CREATE TABLE IF NOT EXISTS cf_asiento_lineas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asiento_id INT NOT NULL,
    cuenta_codigo VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    descripcion_linea VARCHAR(255),
    debe DECIMAL(15,2) DEFAULT 0.00,
    haber DECIMAL(15,2) DEFAULT 0.00,
    orden TINYINT DEFAULT 0,
    FOREIGN KEY (asiento_id) REFERENCES cf_asientos(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_codigo) REFERENCES cf_catalogo_cuentas(codigo),
    INDEX idx_asiento (asiento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Comprobantes Fiscales (NCF / DGII)
CREATE TABLE IF NOT EXISTS cf_ncf (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('B01','B02','B14','B15','B16') NOT NULL,
    ncf VARCHAR(20) UNIQUE NOT NULL,
    fecha DATE NOT NULL,
    cliente_id INT,
    asiento_id INT,                          -- Vinculación directa al registro contable
    monto_bruto DECIMAL(15,2) NOT NULL,
    itbis DECIMAL(15,2) DEFAULT 0.00,
    monto_neto DECIMAL(15,2) NOT NULL,
    estado ENUM('ACTIVO','ANULADO') DEFAULT 'ACTIVO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asiento_id) REFERENCES cf_asientos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Configuración de Reglas para Asientos Automáticos
CREATE TABLE IF NOT EXISTS cf_reglas_asiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento VARCHAR(100) UNIQUE NOT NULL,     -- Ej: 'EMISION_POLIZA_LEY'
    nombre VARCHAR(150),
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    plantilla_json JSON                      -- Define las cuentas y proporciones D/H
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Secuencias de NCF (DGII)
CREATE TABLE IF NOT EXISTS cf_ncf_secuencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('B01','B02','B14','B15','B16') NOT NULL,
    prefijo VARCHAR(3) NOT NULL,             -- Ej: B01
    secuencia_actual INT DEFAULT 0,
    secuencia_final INT NOT NULL,
    vencimiento DATE,
    activa BOOLEAN DEFAULT TRUE,
    UNIQUE KEY uk_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- SEED DATA: CATALOGO BASE (SIS NIVEL 1-2)
-- ==========================================

INSERT INTO cf_catalogo_cuentas (codigo, nombre, tipo, naturaleza, nivel, es_detalle) VALUES
('1', 'ACTIVO', 'ACTIVO', 'DEUDORA', 1, FALSE),
('1.1', 'ACTIVO CORRIENTE', 'ACTIVO', 'DEUDORA', 2, FALSE),
('1.1.01', 'CAJA Y BANCOS', 'ACTIVO', 'DEUDORA', 3, TRUE),
('1.1.02', 'PRIMAS POR COBRAR', 'ACTIVO', 'DEUDORA', 3, TRUE),
('2', 'PASIVO', 'PASIVO', 'ACREEDORA', 1, FALSE),
('2.1', 'PASIVO CORRIENTE', 'PASIVO', 'ACREEDORA', 2, FALSE),
('2.1.01', 'PRIMAS POR PAGAR ASEGURADORAS', 'PASIVO', 'ACREEDORA', 3, TRUE),
('2.1.02', 'ITBIS POR PAGAR', 'PASIVO', 'ACREEDORA', 3, TRUE),
('2.1.03', 'ISR RETENIDO POR PAGAR (10%)', 'PASIVO', 'ACREEDORA', 3, TRUE),
('3', 'PATRIMONIO', 'PATRIMONIO', 'ACREEDORA', 1, FALSE),
('4', 'INGRESOS', 'INGRESO', 'ACREEDORA', 1, FALSE),
('4.1', 'INGRESOS OPERACIONALES', 'INGRESO', 'ACREEDORA', 2, FALSE),
('4.1.01', 'COMISIONES SEGUROS DE LEY', 'INGRESO', 'ACREEDORA', 3, TRUE),
('5', 'GASTOS', 'EGRESO', 'DEUDORA', 1, FALSE),
('5.1', 'GASTOS OPERACIONALES', 'EGRESO', 'DEUDORA', 2, FALSE),
('5.1.01', 'COMISIONES PAGADAS AGENTES', 'EGRESO', 'DEUDORA', 3, TRUE);


-- SEED DATA: SECUENCIAS NCF (Ejemplos iniciales)
INSERT INTO cf_ncf_secuencias (tipo, prefijo, secuencia_actual, secuencia_final, vencimiento) VALUES
('B01', 'B01', 0, 100000, '2026-12-31'),
('B02', 'B02', 0, 500000, '2026-12-31'),
('B14', 'B14', 0, 50000, '2026-12-31');
