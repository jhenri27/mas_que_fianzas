<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

echo "=== PERFILES ===\n";
$res = $db->query("SELECT id, nombre FROM perfiles ORDER BY id");
while ($row = $res->fetch_assoc()) {
    echo "ID={$row['id']} | Nombre={$row['nombre']}\n";
}

echo "\n=== PERMISOS POR PERFIL (total filas) ===\n";
$res2 = $db->query("SELECT perfil_id, COUNT(*) as total FROM permisos_perfil GROUP BY perfil_id ORDER BY perfil_id");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo "perfil_id={$row['perfil_id']} => {$row['total']} permisos\n";
    }
} else {
    echo "Tabla permisos_perfil no existe o error\n";
}

echo "\n=== USUARIO pdv.prueba ===\n";
$res3 = $db->query("SELECT u.id, u.nombre_usuario, u.nombre_completo, u.perfil_id, p.nombre as perfil_nombre FROM usuarios u LEFT JOIN perfiles p ON u.perfil_id = p.id WHERE u.nombre_usuario = 'pdv.prueba'");
if ($res3 && $row = $res3->fetch_assoc()) {
    echo "ID={$row['id']} | user={$row['nombre_usuario']} | perfil_id={$row['perfil_id']} | perfil={$row['perfil_nombre']}\n";
    
    $pid = $row['perfil_id'];
    echo "\n=== PERMISOS PERFIL ID=$pid ===\n";
    $res4 = $db->query("SELECT pp.modulo_id, pp.funcion_id, pp.puede_ejecutar, m.nombre_modulo, fm.nombre_funcion FROM permisos_perfil pp LEFT JOIN modulos m ON pp.modulo_id = m.id LEFT JOIN funciones_modulo fm ON pp.funcion_id = fm.id WHERE pp.perfil_id = $pid LIMIT 40");
    if ($res4 && $res4->num_rows > 0) {
        while ($r = $res4->fetch_assoc()) {
            echo "modulo={$r['nombre_modulo']} | func={$r['nombre_funcion']} | ejecutar={$r['puede_ejecutar']}\n";
        }
    } else {
        echo "SIN PERMISOS en permisos_perfil para perfil_id=$pid\n";
    }
} else {
    echo "Usuario pdv.prueba no encontrado\n";
}
