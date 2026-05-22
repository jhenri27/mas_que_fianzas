<?php
require_once dirname(__FILE__) . '/../../backend/config.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT id, username, password_hash, estado FROM usuarios WHERE username='admin'");
if ($res && $res->num_rows > 0) {
    echo "EXISTS: " . json_encode($res->fetch_assoc()) . "\n";
} else {
    echo "NOT FOUND\n";
}
?>
