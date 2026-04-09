<?php
/**
 * Test file for debugging dashboard API responses
 */

// Include configuration
include 'backend/config.php';

// Create database connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($db->connect_error) {
    echo "DB Connection Error: " . $db->connect_error;
    exit;
}

echo "<h1>Dashboard API Test</h1>";

// Test 1: Check if database and tables exist
echo "<h2>1. Database Tables Check</h2>";
$tables = ['usuarios', 'perfiles', 'permisos_perfil'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '{$table}'");
    if ($result->num_rows > 0) {
        echo "<p style='color:green;'>✓ Table '{$table}' exists</p>";
    } else {
        echo "<p style='color:red;'>✗ Table '{$table}' NOT FOUND</p>";
    }
}

// Test 2: Check usuarios records
echo "<h2>2. Usuarios Records</h2>";
$result = $db->query("SELECT COUNT(*) as total FROM usuarios");
$row = $result->fetch_assoc();
echo "<p>Total usuarios: " . $row['total'] . "</p>";

$result = $db->query("SELECT id, nombre, apellido, email, estado FROM usuarios LIMIT 5");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Test 3: Check perfiles records
echo "<h2>3. Perfiles Records</h2>";
$result = $db->query("SELECT COUNT(*) as total FROM perfiles");
$row = $result->fetch_assoc();
echo "<p>Total perfiles: " . $row['total'] . "</p>";

$result = $db->query("SELECT id, nombre_perfil, descripcion, nivel_jerarquico, estado FROM perfiles");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Test 4: Test API endpoint responses
echo "<h2>4. API Endpoint Simulation</h2>";

// Simulate listarPerfiles
echo "<h3>listarPerfiles ()</h3>";
$perfiles = [];
$sql = "SELECT * FROM perfiles ORDER BY nivel_jerarquico ASC";
$result = $db->query($sql);

while ($row = $result->fetch_assoc()) {
    $perfiles[] = $row;
}

echo "<pre>" . json_encode([
    'exito' => true,
    'mensaje' => 'Perfiles obtenidos',
    'datos' => $perfiles
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// Simulate listarUsuarios
echo "<h3>listarUsuarios (pagina=1, por_pagina=20)</h3>";
$pagina = 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$sql = "SELECT u.id, u.cedula, u.nombre, u.apellido, u.email, u.username, u.estado,
               p.nombre_perfil, u.fecha_creacion, u.fecha_ultimo_acceso
        FROM usuarios u
        LEFT JOIN perfiles p ON u.perfil_id = p.id
        WHERE 1=1
        ORDER BY u.fecha_creacion DESC LIMIT {$offset}, {$por_pagina}";

$usuarios = [];
$result = $db->query($sql);

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

// Total de registros
$result_count = $db->query("SELECT COUNT(*) as total FROM usuarios");
$total_registros = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $por_pagina);

echo "<pre>" . json_encode([
    'exito' => true,
    'mensaje' => 'Usuarios obtenidos',
    'datos' => [
        'usuarios' => $usuarios,
        'paginacion' => [
            'pagina_actual' => $pagina,
            'por_pagina' => $por_pagina,
            'total_registros' => $total_registros,
            'total_paginas' => $total_paginas
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// Test 5: Check session/auth
echo "<h2>5. Session Check</h2>";
session_start();

if (isset($_SESSION['usuario_id'])) {
    echo "<p style='color:green;'>✓ User session exists: User ID = " . $_SESSION['usuario_id'] . "</p>";
} else {
    echo "<p style='color:orange;'>No user session detected (normal for this test file)</p>";
}

?>
