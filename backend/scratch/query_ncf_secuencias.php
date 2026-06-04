<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT * FROM cf_ncf_secuencias");
echo "Secuencias NCF:\n";
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
