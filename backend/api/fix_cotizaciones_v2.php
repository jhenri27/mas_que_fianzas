<?php
/**
 * Corregir tabla cotizaciones - añadir columnas que faltan
 */
require_once '../config.php';
$db = Database::getInstance()->getConnection();

// Ver columnas actuales
$result = $db->query("DESCRIBE cotizaciones");
$cols = [];
while($row = $result->fetch_assoc()){ $cols[] = $row['Field']; }
echo "<h3>Columnas actuales (" . count($cols) . "):</h3><pre>" . implode(', ', $cols) . "</pre>";

// Columnas que necesita la API del historial
$needed = [
    'numero'         => "ALTER TABLE cotizaciones ADD COLUMN `numero` VARCHAR(40) DEFAULT NULL",
    'tipo'           => "ALTER TABLE cotizaciones ADD COLUMN `tipo` VARCHAR(30) DEFAULT NULL",
    'cliente'        => "ALTER TABLE cotizaciones ADD COLUMN `cliente` VARCHAR(200) DEFAULT NULL",
    'monto_afianzado'=> "ALTER TABLE cotizaciones ADD COLUMN `monto_afianzado` DECIMAL(15,2) DEFAULT 0",
    'total'          => "ALTER TABLE cotizaciones ADD COLUMN `total` DECIMAL(15,2) DEFAULT 0",
];

echo "<h3>Añadiendo columnas faltantes:</h3><ul>";
foreach ($needed as $col => $sql) {
    if (!in_array($col, $cols)) {
        if ($db->query($sql)) {
            echo "<li style='color:green'>✅ Columna '$col' añadida</li>";
        } else {
            echo "<li style='color:red'>❌ Error '$col': " . $db->error . "</li>";
        }
    } else {
        echo "<li style='color:#888'>⏭️ '$col' ya existe</li>";
    }
}

// Intentar añadir el índice único a 'numero'
$db->query("ALTER TABLE cotizaciones ADD UNIQUE KEY uk_numero (numero)");
echo "</ul>";

// Verificar resultado
$result2 = $db->query("DESCRIBE cotizaciones");
$cols2 = [];
while($row = $result2->fetch_assoc()){ $cols2[] = $row['Field']; }
echo "<h3>Columnas después del fix (" . count($cols2) . "):</h3><pre>" . implode(', ', $cols2) . "</pre>";
echo "<p style='color:green;font-weight:bold'>✅ Fix completado. Ahora prueba la migración en migrate.html</p>";
echo "<p><a href='/PLATAFORMA_INTEGRADA/frontend/migrate.html'>→ Ir a migrate.html</a></p>";
?>
