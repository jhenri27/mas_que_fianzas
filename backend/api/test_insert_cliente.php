<?php
require_once '../ClienteManager.php';
$m = new ClienteManager();
$res = $m->crearCliente([
    'tipo_persona' => 'Fisica',
    'nombre_razon_social' => 'Cliente de Prueba Automatizada',
    'rnc' => 'RNC-' . rand(100000, 999999),
    'telefono' => '809-555-5555',
    'correo' => 'test@demo.com',
    'direccion' => 'Prueba',
    'estatus' => 'Activo'
]);
echo json_encode($res);
?>
