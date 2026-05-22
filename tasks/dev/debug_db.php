<?php
header('Content-Type: text/plain; charset=utf-8');
require_once '../config.php';

echo "=== DIAGNÓSTICO DE BASE DE DATOS ===\n\n";

$db = Database::getInstance()->getConnection();

$tablas = ['perfiles', 'permisos_perfil', 'funciones_modulo', 'modulos', 'usuarios'];

foreach ($tablas as $tabla) {
    echo "Tabla: $tabla\n";
    $res = $db->query("SHOW TABLES LIKE '$tabla'");
    if ($res->num_rows > 0) {
        echo "  [OK] Existe\n";
        $count = $db->query("SELECT COUNT(*) as c FROM $tabla")->fetch_assoc()['c'];
        echo "  [INFO] Registros: $count\n";
        
        if ($count > 0 && $tabla === 'perfiles') {
            $data = $db->query("SELECT * FROM $tabla LIMIT 5");
            while($row = $data->fetch_assoc()) {
                echo "    - ID: {$row['id']}, Nombre: {$row['nombre_perfil']}, Nivel: {$row['nivel_jerarquico']}\n";
            }
        }
    } else {
        echo "  [ERROR] NO EXISTE\n";
    }
    echo "\n";
}

echo "=== SESIÓN ACTUAL ===\n";
session_start();
echo "PHP Session ID: " . session_id() . "\n";
echo "USUARIO_ID en sesión: " . ($_SESSION['usuario_id'] ?? 'NO DEFINIDO') . "\n";

echo "\n=== FIN DIAGNÓSTICO ===\n";
?>
