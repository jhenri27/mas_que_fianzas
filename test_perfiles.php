<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['PATH_INFO'] = '/guardar/1';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer fake_token_bypass';
$_SESSION['usuario_id'] = 1;

// Override file_get_contents wrapper for testing
$json_data = json_encode([
    [
        'funcion_id' => 138,
        'modulo_id' => 3,
        'puede_ejecutar' => true,
        'ver_datos' => true,
        'crear_datos' => true,
        'editar_datos' => true,
        'eliminar_datos' => true,
        'ver_reportes' => false,
        'exportar_datos' => false,
        'importar_datos' => false,
        'imprimir_datos' => false,
        'solo_propios' => false
    ]
]);

// Since perfiles_engine uses php://input, we can't easily mock it without stream wrappers,
// let's instead modify perfiles_engine.php directly to read from a variable if it's set.
// But wait! Look at the actual UI code in dashboard.js!
