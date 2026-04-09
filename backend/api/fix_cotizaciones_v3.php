<?php
/**
 * Fix final: hacer numero_cotizacion nullable y limpiar constraint
 */
require_once '../config.php';
$db = Database::getInstance()->getConnection();

$fixes = [
    "ALTER TABLE cotizaciones MODIFY COLUMN `numero_cotizacion` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE cotizaciones MODIFY COLUMN `cliente_id` INT DEFAULT NULL",
    "ALTER TABLE cotizaciones MODIFY COLUMN `tipo_vehiculo` VARCHAR(100) DEFAULT NULL",
];

echo "<ul>";
foreach ($fixes as $sql) {
    if ($db->query($sql)) {
        echo "<li style='color:green'>✅ " . htmlspecialchars($sql) . "</li>";
    } else {
        echo "<li style='color:red'>❌ " . htmlspecialchars($sql) . " → " . $db->error . "</li>";
    }
}
echo "</ul>";

// Verificar resultado con INSERT de prueba
$test = $db->query("INSERT INTO cotizaciones (numero, tipo, cliente, total, fecha) 
                    VALUES ('TEST-0001', 'TEST', 'PRUEBA', 0, NOW()) 
                    ON DUPLICATE KEY UPDATE tipo='TEST'");
if ($test) {
    echo "<p style='color:green;font-weight:bold'>✅ INSERT de prueba exitoso. La tabla ya acepta inserción.</p>";
    $db->query("DELETE FROM cotizaciones WHERE numero='TEST-0001'");
} else {
    echo "<p style='color:red;font-weight:bold'>❌ INSERT falló: " . $db->error . "</p>";
}

echo "<p><a href='/PLATAFORMA_INTEGRADA/frontend/migrate.html'>→ Ir a migrate.html para reintentar</a></p>";
?>
