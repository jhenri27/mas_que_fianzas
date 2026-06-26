<?php
require_once __DIR__ . '/config.php';
$db = Database::getInstance()->getConnection();
$bots_res = $db->query("SELECT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil FROM usuarios u JOIN perfiles p ON u.perfil_id = p.id WHERE u.username IN ('bot.helpnow', 'bot.ssindi')");
if ($bots_res) {
    echo "Query succeeded.\n";
    while ($row = $bots_res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Query failed: " . $db->error . "\n";
}
?>
