<?php
require_once dirname(__FILE__) . '/../../backend/config.php';
$db = Database::getInstance()->getConnection();

$new_hash = password_hash('Demo@123', PASSWORD_BCRYPT, ['cost' => 10]);
$stmt = $db->prepare("UPDATE usuarios SET password_hash = ? WHERE username = 'admin'");
$stmt->bind_param("s", $new_hash);

if ($stmt->execute()) {
    echo "SUCCESS: Admin password has been updated in database masque_fianzas_integrada_01.\n";
} else {
    echo "ERROR: " . $db->error . "\n";
}
$stmt->close();
?>
