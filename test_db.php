<?php
// Test de conexión a MySQL
$conn = new mysqli('localhost', 'root', '', 'mq_platform');

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "✓ Conexión exitosa a mq_platform<br>";

// Verificar tabla users
$result = $conn->query("SHOW TABLES");
echo "✓ Tablas en la base de datos:<br>";
while ($row = $result->fetch_row()) {
    echo "  - " . $row[0] . "<br>";
}

// Contar usuarios
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$row = $result->fetch_assoc();
echo "✓ Total de usuarios: " . $row['total'] . "<br>";

// Mostrar usuario admin
$result = $conn->query("SELECT email, role FROM users WHERE role='admin'");
if ($row = $result->fetch_assoc()) {
    echo "✓ Usuario admin encontrado: " . $row['email'] . "<br>";
} else {
    echo "✗ No hay usuario admin<br>";
}

$conn->close();
?>
