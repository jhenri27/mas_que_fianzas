<?php
header('Content-Type: text/plain; charset=utf-8');
require_once '../config.php';
require_once '../UsuarioManager.php';

// Simular usuario administrador
session_start();
$_SESSION['usuario_id'] = 1;

$manager = new UsuarioManager();
$rand = rand(1000, 9999);

echo "--- TEST 1: CREAR USUARIO CON COMISION ---\n";
$datos_nuevo = [
    'cedula' => 'V-' . $rand . '-' . time(),
    'nombre' => 'Test',
    'apellido' => 'Comision',
    'email' => 'test.' . $rand . '@pruebas.com',
    'username' => 'user' . $rand,
    'perfil_id' => 5,
    'es_comisionante' => 1,
    'porcentaje_comision' => 0.55,
    'porcentaje_comision_red' => 0.22,
    'referente_id' => 1
];

$res = $manager->crearUsuario($datos_nuevo, 1);
echo "Resultado: " . ($res['exito'] ? "EXITO" : "FALLO - " . $res['mensaje']) . "\n";

if ($res['exito']) {
    $nuevo_id = $res['usuario_id'];
    echo "Usuario creado con ID: $nuevo_id\n";
    
    echo "\n--- TEST 2: EDITAR USUARIO CREADO ---\n";
    $datos_edicion = [
        'nombre' => 'Test Editado ' . $rand,
        'porcentaje_comision' => 0.66
    ];
    $res_edit = $manager->editarUsuario($nuevo_id, $datos_edicion, 1);
    echo "Resultado edición: " . ($res_edit['exito'] ? "EXITO" : "FALLO - " . $res_edit['mensaje']) . "\n";
} else {
    echo "No se pudo probar la edición porque falló la creación.\n";
}
?>
