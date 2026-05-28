<?php
require_once 'backend/config/config.php';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check perfiles table
    echo "=== PERFILES ===\n";
    $stmt = $pdo->query('SELECT id, nombre, descripcion FROM perfiles ORDER BY id');
    foreach ($stmt as $r) {
        echo "ID={$r['id']} | Nombre={$r['nombre']}\n";
    }
    
    // Check permisos for PDV profile (find which ID is PDV)
    echo "\n=== PERMISOS POR PERFIL ===\n";
    $stmt2 = $pdo->query('SELECT perfil_id, COUNT(*) as total FROM permisos_perfil GROUP BY perfil_id ORDER BY perfil_id');
    foreach ($stmt2 as $r) {
        echo "perfil_id={$r['perfil_id']} => {$r['total']} permisos\n";
    }
    
    // Check user pdv.prueba profile_id
    echo "\n=== USUARIO pdv.prueba ===\n";
    $stmt3 = $pdo->prepare('SELECT u.id, u.nombre_usuario, u.nombre_completo, u.perfil_id, p.nombre as perfil_nombre FROM usuarios u LEFT JOIN perfiles p ON u.perfil_id = p.id WHERE u.nombre_usuario = ?');
    $stmt3->execute(['pdv.prueba']);
    $user = $stmt3->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo "ID={$user['id']} | user={$user['nombre_usuario']} | perfil_id={$user['perfil_id']} | perfil={$user['perfil_nombre']}\n";
    } else {
        echo "Usuario pdv.prueba no encontrado\n";
    }
    
    // Check permisos_perfil for that profile
    if ($user && $user['perfil_id']) {
        $pid = $user['perfil_id'];
        echo "\n=== PERMISOS PERFIL ID=$pid ===\n";
        $stmt4 = $pdo->prepare('SELECT pp.*, m.nombre_modulo, fm.nombre_funcion FROM permisos_perfil pp LEFT JOIN modulos m ON pp.modulo_id = m.id LEFT JOIN funciones_modulo fm ON pp.funcion_id = fm.id WHERE pp.perfil_id = ? LIMIT 30');
        $stmt4->execute([$pid]);
        $rows = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo "SIN PERMISOS en permisos_perfil para perfil_id=$pid\n";
        } else {
            foreach ($rows as $r) {
                echo "modulo={$r['nombre_modulo']} | funcion={$r['nombre_funcion']} | ejecutar={$r['puede_ejecutar']}\n";
            }
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
