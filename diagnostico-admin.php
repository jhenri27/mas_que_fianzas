<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli('localhost', 'root', '', 'mq_platform');

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "<h1>Diagnóstico de Usuario Admin</h1>";

// 1. Verificar que el usuario existe
$result = $conn->query("SELECT id, username, email, password_hash, estado FROM usuarios WHERE username='admin'");

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<h2>✓ Usuario Admin Encontrado</h2>";
    echo "<pre>";
    echo "ID: " . $user['id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Estado: " . $user['estado'] . "\n";
    echo "Hash almacenado: " . $user['password_hash'] . "\n";
    echo "</pre>";
    
    // 2. Verificar el hash
    $password_to_test = 'Demo@123';
    $stored_hash = $user['password_hash'];
    
    echo "<h2>Verificación de Contraseña</h2>";
    echo "<p><strong>Contraseña a verificar:</strong> {$password_to_test}</p>";
    echo "<p><strong>Hash almacenado:</strong> {$stored_hash}</p>";
    
    $is_valid = password_verify($password_to_test, $stored_hash);
    echo "<p><strong>¿Es válida?</strong> " . ($is_valid ? "✓ SÍ" : "✗ NO") . "</p>";
    
    // 3. Si no es válida, regenerar el hash
    if (!$is_valid) {
        echo "<h2>Regenerando hash...</h2>";
        $new_hash = password_hash('Demo@123', PASSWORD_BCRYPT, ['cost' => 10]);
        echo "<p><strong>Nuevo hash:</strong> {$new_hash}</p>";
        
        $update = $conn->prepare("UPDATE usuarios SET password_hash=? WHERE username='admin'");
        $update->bind_param("s", $new_hash);
        if ($update->execute()) {
            echo "<p>✓ <strong>Hash actualizado exitosamente</strong></p>";
            echo "<p>Ahora intenta hacer login con admin/Demo@123</p>";
        } else {
            echo "<p>✗ Error al actualizar: " . $conn->error . "</p>";
        }
        $update->close();
    }
} else {
    echo "<h2>✗ Usuario Admin NO encontrado</h2>";
    echo "<p>Necesito insertar el usuario admin manualmente.</p>";
    
    $hash = password_hash('Demo@123', PASSWORD_BCRYPT, ['cost' => 10]);
    $insert = $conn->prepare(
        "INSERT INTO usuarios (cedula, nombre, apellido, email, username, password_hash, perfil_id, estado, requiere_cambio_password, activo_desde, creado_por) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
    );
    
    $cedula = '0000000000';
    $nombre = 'Administrador';
    $apellido = 'Sistema';
    $email = 'admin@masquefianzas.com';
    $username = 'admin';
    $perfil_id = 1;
    $estado = 'activo';
    $requiere_cambio = 0;
    $creado_por = 1;
    
    $insert->bind_param("ssssssissi", $cedula, $nombre, $apellido, $email, $username, $hash, $perfil_id, $estado, $requiere_cambio, $creado_por);
    
    if ($insert->execute()) {
        echo "<p>✓ <strong>Usuario admin creado exitosamente</strong></p>";
        echo "<p>Hash: {$hash}</p>";
        echo "<p>Ahora intenta hacer login con admin/Demo@123</p>";
    } else {
        echo "<p>✗ Error al crear usuario: " . $conn->error . "</p>";
    }
    $insert->close();
}

$conn->close();
?>
