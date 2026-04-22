<?php
require_once dirname(__DIR__) . '/config.php';
$db = Database::getInstance()->getConnection();

echo "Verificando columnas en tabla usuarios...\n";

// Columnas a agregar
$cols = [
    'reset_token' => 'VARCHAR(255) NULL DEFAULT NULL AFTER password_hash',
    'reset_token_expires' => 'DATETIME NULL DEFAULT NULL AFTER reset_token',
    'solicita_desbloqueo' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER estado'
];

foreach ($cols as $col => $def) {
    $res = $db->query("SHOW COLUMNS FROM usuarios LIKE '$col'");
    if ($res->num_rows === 0) {
        $sql = "ALTER TABLE usuarios ADD $col $def";
        if ($db->query($sql)) {
            echo "Agregada columna $col\n";
        } else {
            echo "Error agregando $col: " . $db->error . "\n";
        }
    } else {
        echo "La columna $col ya existe.\n";
    }
}
echo "Migración completada.\n";
?>
