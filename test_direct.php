<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DIAGNÓSTICO DIRECTO OBTENER PERFIL 1 (ADMIN) ===\n";

$python_script = 'backend/perfiles_engine.py';
$full_cmd = 'python ' . escapeshellarg($python_script) . ' get_profile_permissions 1';

echo "Comando a ejecutar: $full_cmd\n";

$output = [];
$return_var = 0;
exec($full_cmd . ' 2>&1', $output, $return_var);

echo "Código de retorno: $return_var\n";
echo "Salida:\n";
echo implode("\n", $output) . "\n";
?>
