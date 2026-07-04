<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');

// Get function IDs
$res = $db->query("SELECT id, codigo_funcion FROM funciones_modulo WHERE codigo_funcion LIKE '%CANCELAR%'");
$funciones = $res->fetch_all(MYSQLI_ASSOC);

foreach ($funciones as $f) {
    $fid = $f['id'];
    echo "Processing {$f['codigo_funcion']} (ID $fid)...\n";
    
    // Check if exists for perfil 1
    $chk = $db->query("SELECT * FROM permisos_perfil WHERE perfil_id = 1 AND funcion_id = $fid");
    if ($chk->num_rows == 0) {
        $db->query("INSERT INTO permisos_perfil (perfil_id, funcion_id, puede_ejecutar) VALUES (1, $fid, 1)");
        echo "Inserted for perfil 1\n";
    } else {
        $db->query("UPDATE permisos_perfil SET puede_ejecutar = 1 WHERE perfil_id = 1 AND funcion_id = $fid");
        echo "Updated for perfil 1\n";
    }
}
echo "Done.";
