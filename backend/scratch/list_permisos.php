<?php
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';
$db = Database::getInstance()->getConnection();

echo "=== MODULOS ===\n";
$res_m = $db->query("SELECT id, nombre_modulo, codigo_modulo FROM modulos");
while ($row = $res_m->fetch_assoc()) {
    echo "  ID: {$row['id']} | Nombre: {$row['nombre_modulo']} | Codigo: {$row['codigo_modulo']}\n";
}

echo "\n=== ALGUNAS FUNCIONES DEL MODULO DE PAGOS ===\n";
$res_f = $db->query("SELECT id, modulo_id, nombre_funcion, codigo_funcion FROM funciones_modulo WHERE nombre_funcion LIKE '%pago%' OR codigo_funcion LIKE '%pag%' OR modulo_id = (SELECT id FROM modulos WHERE nombre_modulo LIKE '%pago%' LIMIT 1)");
while ($row = $res_f->fetch_assoc()) {
    echo "  ID: {$row['id']} | ModuloID: {$row['modulo_id']} | Nombre: {$row['nombre_funcion']} | Codigo: {$row['codigo_funcion']}\n";
}

echo "\n=== PERMISOS DE PAGOS PARA PERFILES ===\n";
$sql = "SELECT p.nombre_perfil, pp.modulo_id, pp.funcion_id, pp.puede_ejecutar, pp.ver_datos, pp.crear_datos, pp.editar_datos
        FROM permisos_perfil pp
        JOIN perfiles p ON pp.perfil_id = p.id
        WHERE pp.modulo_id = (SELECT id FROM modulos WHERE nombre_modulo LIKE '%pago%' LIMIT 1)";
$res_pp = $db->query($sql);
if ($res_pp) {
    while ($row = $res_pp->fetch_assoc()) {
        echo "  Perfil: {$row['nombre_perfil']} | ModuloID: {$row['modulo_id']} | FuncionID: {$row['funcion_id']} | Ejecutar: {$row['puede_ejecutar']} | Ver: {$row['ver_datos']} | Crear: {$row['crear_datos']}\n";
    }
} else {
    echo "  No se pudieron obtener permisos de perfiles.\n";
}
?>
