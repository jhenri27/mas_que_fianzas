<?php
// Script para importar la base de datos
$conn = new mysqli('localhost', 'root', '');
set_time_limit(300);

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

// Crear base de datos si no existe
$sql = "CREATE DATABASE IF NOT EXISTS mq_platform";
if ($conn->query($sql) === TRUE) {
    echo "✓ Base de datos 'mq_platform' creada/existe<br>";
} else {
    echo "✗ Error al crear BD: " . $conn->error . "<br>";
    exit;
}

// Usar la base de datos
$conn->select_db('mq_platform');

// Función para limpiar y ejecutar SQL
function execute_sql_file($conn, $file) {
    if (!file_exists($file)) {
        echo "✗ Archivo no encontrado: {$file}<br>";
        return false;
    }
    
    $content = file_get_contents($file);
    
    // Limpiar comentarios que causan problemas
    $content = preg_replace('/--.*$/m', '', $content); // Comentarios SQL estándar
    $content = preg_replace('|//.*$|m', '', $content); // Comentarios C++
    $content = preg_replace('/\/\*.*?\*\//s', '', $content); // Comentarios bloque
    
    // Remover USE statements
    $content = preg_replace('/USE\s+\w+\s*;/i', '', $content);
    $content = preg_replace('/CREATE\s+DATABASE.*?;/i', '', $content);
    
    // Dividir por punto y coma y ejecutar cada statement
    $statements = explode(';', $content);
    $success = true;
    $count = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        if (!$conn->query($statement)) {
            echo "✗ Error SQL: " . $conn->error . "<br>";
            echo "Statement: " . substr($statement, 0, 100) . "...<br>";
            $success = false;
            break;
        }
        $count++;
    }
    
    return $success ? $count : false;
}

// Importar schema
$schema_file = './database/schema_masque_fianzas.sql';
$result = execute_sql_file($conn, $schema_file);
if ($result !== false) {
    echo "✓ Schema importado: {$result} comandos ejecutados<br>";
} else {
    echo "✗ Error al importar schema<br>";
}

// Importar datos iniciales
$datos_file = './database/datos_iniciales.sql';
$result = execute_sql_file($conn, $datos_file);
if ($result !== false) {
    echo "✓ Datos iniciales importados: {$result} comandos ejecutados<br>";
} else {
    echo "✗ Error al importar datos (puede que ya existan)<br>";
}

// Verificación final
$result = $conn->query("SHOW TABLES");
if ($result) {
    $count = $result->num_rows;
    echo "✓ Tablas en la BD: {$count}<br>";
    
    $users_check = $conn->query("SELECT COUNT(*) as total FROM users");
    if ($users_check) {
        $row = $users_check->fetch_assoc();
        echo "✓ Tabla 'users' con " . $row['total'] . " registros<br>";
    }
} else {
    echo "✗ Error al verificar tablas<br>";
}

$conn->close();
echo "<br><strong>✓ Proceso completado. La base de datos está lista.</strong><br>";
echo '<a href="http://localhost/PLATAFORMA_INTEGRADA/frontend/index.html">Ir a la aplicación</a>';
?>
