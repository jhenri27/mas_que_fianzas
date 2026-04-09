<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

echo "<h2>Importando Base de Datos...</h2>";

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

// 1. Limpiar base de datos existente
echo "<p>Limpiando base de datos existente...</p>";
$conn->query("DROP DATABASE IF EXISTS mq_platform");
$conn->query("CREATE DATABASE mq_platform");
$conn->select_db('mq_platform');
echo "✓ Base de datos recreada<br>";

// Función para limpiar y ejecutar SQL desde un archivo
function importarSQL($conn, $rutaArchivo) {
    if (!file_exists($rutaArchivo)) {
        return "✗ Archivo no encontrado: {$rutaArchivo}";
    }
    
    $content = file_get_contents($rutaArchivo);
    
    // Limpiar comentarios problemáticos
    $content = preg_replace('/--.*$/m', '', $content);
    $content = preg_replace('|//.*$|m', '', $content);
    $content = preg_replace('/\/\*.*?\*\//s', '', $content);
    
    // Remover USE y CREATE DATABASE statements
    $content = preg_replace('/USE\s+\w+\s*;/i', '', $content);
    $content = preg_replace('/CREATE\s+DATABASE.*?;/i', '', $content);
    
    // Dividir por punto y coma
    $statements = array_filter(array_map('trim', explode(';', $content)));
    
    $contador = 0;
    $errores = [];
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        if (!$conn->query($statement)) {
            $errores[] = "Error: " . $conn->error . " | SQL: " . substr($statement, 0, 80);
        } else {
            $contador++;
        }
    }
    
    if (!empty($errores)) {
        return "⚠️ {$contador} comandos ejecutados con " . count($errores) . " errores:<br>" . implode("<br>", array_slice($errores, 0, 5));
    }
    
    return "✓ {$contador} comandos ejecutados exitosamente";
}

// 2. Importar schema
echo "<p><strong>Importando schema...</strong></p>";
$resultado = importarSQL($conn, './database/schema_limpio.sql');
echo $resultado . "<br>";

// 3. Importar datos iniciales
echo "<p><strong>Importando datos iniciales...</strong></p>";
$resultado = importarSQL($conn, './database/datos_limpios.sql');
echo $resultado . "<br>";

// 4. Verificar
echo "<p><strong>Verificando...</strong></p>";
$result = $conn->query("SHOW TABLES");
$numTables = $result->num_rows;
echo "✓ Tablas creadas: {$numTables}<br>";

// Verificar usuarios
$usuarios = $conn->query("SELECT COUNT(*) as cnt FROM usuarios")->fetch_assoc();
echo "✓ Usuarios en la BD: " . $usuarios['cnt'] . "<br>";

if ($usuarios['cnt'] > 0) {
    $admin = $conn->query("SELECT username, email FROM usuarios WHERE username='admin' LIMIT 1")->fetch_assoc();
    if ($admin) {
        echo "✓ Usuario admin encontrado: " . $admin['username'] . " (" . $admin['email'] . ")<br>";
    }
}

$conn->close();

echo "<br><h3>✓ Importación completada exitosamente</h3>";
echo '<p><a href="http://localhost/PLATAFORMA_INTEGRADA/frontend/index.html" style="padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">Ir a la Aplicación</a></p>';
?>
