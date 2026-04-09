<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO('mysql:host=localhost;dbname=masque_fianzas_integrada_01;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = 'pdv.prueba'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "El usuario ya existe. Borrándolo...<br>";
        $pdo->exec("DELETE FROM usuarios WHERE username = 'pdv.prueba'");
    }

    $sql = "INSERT INTO usuarios (
        cedula, nombre, apellido, email, telefono, username, password_hash,
        perfil_id, estado, requiere_cambio_password, activo_desde
    ) VALUES (
        '001-0000001-1', 'Juan', 'Socio PDV', 'pdv.prueba@masquefianzas.com',
        '809-000-0001', 'pdv.prueba',
        '$2y$10$8koY8xauk8/ACWJzHOr4ouYVG4OJnfwMmIkqW8RJHYWPyom2GjhL2',
        5, 'activo', 0, NOW()
    )";
    
    $pdo->exec($sql);
    echo "¡Usuario insertado con éxito!";
    
} catch (PDOException $e) {
    echo "Error de BD: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage();
}
