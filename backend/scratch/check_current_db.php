<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();
echo "Connected database: " . DB_NAME . "\n";
$res = $db->query("SHOW TABLES");
if ($res) {
    while($row = $res->fetch_row()) {
        echo " - " . $row[0] . "\n";
    }
} else {
    echo "Error listing tables: " . $db->error . "\n";
}
?>
