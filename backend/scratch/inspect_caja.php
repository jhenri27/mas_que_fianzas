<?php
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';
$db = Database::getInstance()->getConnection();

echo "=== COLUMNAS DE PAGOS ===\n";
$res = $db->query("SHOW COLUMNS FROM pagos");
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== COLUMNAS DE USUARIOS ===\n";
$res = $db->query("SHOW COLUMNS FROM usuarios");
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== BUSCANDO CAMPO CAJA / SUCURSAL / PDV IN ALL TABLES ===\n";
$res_tables = $db->query("SHOW TABLES");
while ($t_row = $res_tables->fetch_array()) {
    $table = $t_row[0];
    $res_cols = $db->query("SHOW COLUMNS FROM `$table`");
    while ($c_row = $res_cols->fetch_assoc()) {
        $col = $c_row['Field'];
        if (stripos($col, 'caja') !== false || stripos($col, 'sucursal') !== false || stripos($col, 'pdv') !== false || stripos($col, 'canal') !== false) {
            echo "  $table.$col (" . $c_row['Type'] . ")\n";
        }
    }
}

echo "\n=== PERFILES REGISTRADOS ===\n";
$res_p = $db->query("SELECT id, nombre_perfil, siglas, nivel_jerarquico FROM perfiles");
while ($row = $res_p->fetch_assoc()) {
    echo "  ID: {$row['id']} | Nombre: {$row['nombre_perfil']} | Siglas: {$row['siglas']} | Nivel: {$row['nivel_jerarquico']}\n";
}
?>
