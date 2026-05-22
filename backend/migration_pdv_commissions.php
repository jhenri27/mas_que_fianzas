<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

echo "Iniciando migración para comisiones por ramos y cotizaciones...\n";

// 1. Agregar columnas a la tabla usuarios
$columnas_usuarios = [
    "comision_autos_ley DECIMAL(5,2) DEFAULT 0.00 AFTER porcentaje_comision_red",
    "comision_autos_full DECIMAL(5,2) DEFAULT 0.00 AFTER comision_autos_ley",
    "comision_fianzas DECIMAL(5,2) DEFAULT 0.00 AFTER comision_autos_full",
    "comision_incendio DECIMAL(5,2) DEFAULT 0.00 AFTER comision_fianzas",
    "comision_rc DECIMAL(5,2) DEFAULT 0.00 AFTER comision_incendio",
    "comision_otros DECIMAL(5,2) DEFAULT 0.00 AFTER comision_rc",
    "banco VARCHAR(100) DEFAULT NULL AFTER comision_otros",
    "tipo_cuenta VARCHAR(50) DEFAULT NULL AFTER banco",
    "numero_cuenta VARCHAR(100) DEFAULT NULL AFTER tipo_cuenta",
    "ubicacion VARCHAR(255) DEFAULT NULL AFTER numero_cuenta",
    "fecha_cumpleanos DATE DEFAULT NULL AFTER ubicacion"
];

foreach ($columnas_usuarios as $col) {
    $col_name = explode(' ', trim($col))[0];
    $check = $db->query("SHOW COLUMNS FROM usuarios LIKE '$col_name'");
    
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE usuarios ADD COLUMN $col";
        if ($db->query($sql)) {
            echo "Columna '$col_name' agregada a usuarios.\n";
        } else {
            echo "Error agregando '$col_name' a usuarios: " . $db->error . "\n";
        }
    } else {
        echo "La columna '$col_name' ya existe en usuarios.\n";
    }
}

// 2. Agregar columna beneficiario a la tabla cotizaciones
$check_beneficiario = $db->query("SHOW COLUMNS FROM cotizaciones LIKE 'beneficiario'");
if ($check_beneficiario->num_rows == 0) {
    $sql_cotizaciones = "ALTER TABLE cotizaciones ADD COLUMN beneficiario VARCHAR(255) DEFAULT NULL AFTER contacto_solicitante";
    if ($db->query($sql_cotizaciones)) {
        echo "Columna 'beneficiario' agregada a la tabla cotizaciones.\n";
    } else {
        echo "Error agregando 'beneficiario' a cotizaciones: " . $db->error . "\n";
    }
} else {
    echo "La columna 'beneficiario' ya existe en cotizaciones.\n";
}

echo "Migración completada exitosamente.\n";
?>
