<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

echo "Iniciando migración de base de datos...\n";

// 1. Agregar columna 'siglas' a perfiles
$check_siglas = $db->query("SHOW COLUMNS FROM perfiles LIKE 'siglas'");
if ($check_siglas->num_rows == 0) {
    $sql_perfiles = "ALTER TABLE perfiles ADD COLUMN siglas VARCHAR(10) AFTER nombre_perfil";
    if ($db->query($sql_perfiles)) {
        echo "¡Columna 'siglas' agregada a tabla perfiles!\n";
    }
}

if (true) { // Siempre intentar actualizar siglas por si hay nuevas
    // Actualizar siglas existentes
    $siglas = [
        'Administrador' => 'ADM',
        'Gerente Técnico' => 'GER',
        'Gerente Contador' => 'GER',
        'Gerente Comercial' => 'GER',
        'Socio Comercial PDV' => 'PDV',
        'Supervisor Comercial' => 'SUP',
        'Auditor' => 'AUD',
        'Cajero' => 'CAJ',
        'Usuario' => 'USU'
    ];
    
    foreach ($siglas as $nombre => $sigla) {
        $stmt = $db->prepare("UPDATE perfiles SET siglas = ? WHERE nombre_perfil LIKE ? AND (siglas IS NULL OR siglas = '')");
        $like_nombre = "%$nombre%";
        $stmt->bind_param("ss", $sigla, $like_nombre);
        $stmt->execute();
        $stmt->close();
    }
    echo "Siglas de perfiles actualizadas.\n";
}

// 2. Agregar columnas a usuarios
$columnas_usuarios = [
    "codigo_usuario VARCHAR(20) UNIQUE AFTER id",
    "es_comisionante TINYINT(1) DEFAULT 0 AFTER estado",
    "porcentaje_comision DECIMAL(5,2) DEFAULT 0.00 AFTER es_comisionante",
    "porcentaje_comision_red DECIMAL(5,2) DEFAULT 0.00 AFTER porcentaje_comision",
    "referente_id INT NULL AFTER porcentaje_comision_red"
];

foreach ($columnas_usuarios as $col) {
    // Extraer nombre de columna para el IF NOT EXISTS manual (MySQL < 8.0.19 workaround)
    $col_name = explode(' ', trim($col))[0];
    $check = $db->query("SHOW COLUMNS FROM usuarios LIKE '$col_name'");
    
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE usuarios ADD COLUMN $col";
        if ($db->query($sql)) {
            echo "Columna '$col_name' agregada a usuarios.\n";
        } else {
            echo "Error agregando '$col_name': " . $db->error . "\n";
        }
    } else {
        echo "La columna '$col_name' ya existe en usuarios.\n";
    }
}

// 3. Agregar clave foránea para referente_id si no existe
$check_fk = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'referente_id' 
                        AND TABLE_SCHEMA = '".DB_NAME."' AND REFERENCED_TABLE_NAME = 'usuarios'");

if ($check_fk->num_rows == 0) {
    $sql_fk = "ALTER TABLE usuarios ADD CONSTRAINT fk_usuario_referente 
               FOREIGN KEY (referente_id) REFERENCES usuarios(id) ON DELETE SET NULL";
    if ($db->query($sql_fk)) {
        echo "Clave foránea 'fk_usuario_referente' agregada.\n";
    }
}

echo "Migración completada exitosamente.\n";
?>
