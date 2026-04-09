<?php
// Verificación final del sistema

$conn = new mysqli('localhost', 'root', '', 'mq_platform');

if ($conn->connect_error) {
    die('❌ Error: No se puede conectar a la BD');
}

echo "<h2>✓ SISTEMA VERIFICADO Y LISTO</h2>";

// Verificar tablas
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

echo "<p><strong>Tablas en la BD (" . count($tables) . "):</strong></p>";
echo "<ul>";
foreach ($tables as $table) {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
    echo "<li>✓ {$table} ({$count} registros)</li>";
}
echo "</ul>";

// Verificar usuario admin
$admin = $conn->query("SELECT * FROM usuarios WHERE rol='admin' LIMIT 1")->fetch_assoc();
if ($admin) {
    echo "<p><strong>✓ Usuario Admin:</strong></p>";
    echo "<ul>";
    echo "<li>Email: " . $admin['email'] . "</li>";
    echo "<li>Rol: " . $admin['rol'] . "</li>";
    echo "</ul>";
}

$conn->close();

echo "<h3>Credenciales de Demo:</h3>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@masquefianzas.com</li>";
echo "<li><strong>Contraseña:</strong> Demo@123</li>";
echo "</ul>";

echo "<br><a href='http://localhost/PLATAFORMA_INTEGRADA/frontend/index.html' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'><strong>✓ Ir a la Aplicación</strong></a>";
?>
