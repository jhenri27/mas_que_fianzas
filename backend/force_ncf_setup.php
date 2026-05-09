<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS cf_ncf_secuencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(10) NOT NULL UNIQUE,
    prefijo VARCHAR(5) NOT NULL,
    secuencia_actual INT NOT NULL DEFAULT 0,
    secuencia_final INT NOT NULL,
    vencimiento DATE,
    activa BOOLEAN DEFAULT TRUE,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cf_ncf_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(10) NOT NULL,
    ncf VARCHAR(20) NOT NULL,
    modulo_origen VARCHAR(50),
    documento_id INT,
    usuario_id INT,
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO cf_ncf_secuencias (tipo, prefijo, secuencia_actual, secuencia_final, vencimiento) VALUES
('B01', 'B01', 0, 100000, '2026-12-31'),
('B02', 'B02', 0, 1000000, '2026-12-31'),
('B14', 'B14', 0, 50000, '2026-12-31');
";

if ($db->multi_query($sql)) {
    do {
        if ($result = $db->store_result()) { $result->free(); }
    } while ($db->next_result());
    echo "Tablas NCF creadas/verificadas correctamente.";
} else {
    echo "Error: " . $db->error;
}
