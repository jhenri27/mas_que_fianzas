<?php
// Script para corregir la tabla cotizaciones
require_once '../config.php';
$db = Database::getInstance()->getConnection();

// Consultar columnas actuales
$result = $db->query("DESCRIBE cotizaciones");
$cols = [];
while($row = $result->fetch_assoc()){ $cols[] = $row['Field']; }
echo "Columnas actuales: " . implode(', ', $cols) . "\n\n";

// Añadir columnas faltantes
$alteraciones = [
    'fecha'               => "ALTER TABLE cotizaciones ADD COLUMN `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP",
    'subtipo'             => "ALTER TABLE cotizaciones ADD COLUMN `subtipo` VARCHAR(100) DEFAULT NULL",
    'cedula'              => "ALTER TABLE cotizaciones ADD COLUMN `cedula` VARCHAR(30) DEFAULT NULL",
    'uso'                 => "ALTER TABLE cotizaciones ADD COLUMN `uso` VARCHAR(60) DEFAULT NULL",
    'capacidad'           => "ALTER TABLE cotizaciones ADD COLUMN `capacidad` VARCHAR(100) DEFAULT NULL",
    'aseguradora'         => "ALTER TABLE cotizaciones ADD COLUMN `aseguradora` VARCHAR(100) DEFAULT NULL",
    'cobertura'           => "ALTER TABLE cotizaciones ADD COLUMN `cobertura` VARCHAR(60) DEFAULT NULL",
    'plazo'               => "ALTER TABLE cotizaciones ADD COLUMN `plazo` INT DEFAULT NULL",
    'prima_base'          => "ALTER TABLE cotizaciones ADD COLUMN `prima_base` DECIMAL(15,2) DEFAULT 0",
    'impuesto'            => "ALTER TABLE cotizaciones ADD COLUMN `impuesto` DECIMAL(15,2) DEFAULT 0",
    'servicios_opcionales'=> "ALTER TABLE cotizaciones ADD COLUMN `servicios_opcionales` TEXT DEFAULT NULL",
];
foreach ($alteraciones as $col => $sql) {
    if (!in_array($col, $cols)) {
        if ($db->query($sql)) {
            echo "OK: Columna '$col' añadida\n";
        } else {
            echo "ERR '$col': " . $db->error . "\n";
        }
    } else {
        echo "SKIP: '$col' ya existe\n";
    }
}

// Asegurar índice único en numero
$db->query("ALTER TABLE cotizaciones ADD UNIQUE KEY uk_numero (numero)");
echo "\nTabla lista.\n";
?>
