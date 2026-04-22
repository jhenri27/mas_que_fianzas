<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SHOW TABLES");
$tables = [];
while($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

$schema = [];
foreach($tables as $table) {
    if ($table === 'auditoria_accesos') {
        $res2 = $db->query("SHOW COLUMNS FROM $table");
        while($c = $res2->fetch_assoc()) {
            $schema[$table][] = $c;
        }
    }
}

echo json_encode(["tables" => $tables, "auditoria_schema" => $schema], JSON_PRETTY_PRINT);
?>
