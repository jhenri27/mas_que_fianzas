<?php
require_once __DIR__ . '/config.php';
$db = Database::getInstance()->getConnection();
$db->query("ALTER TABLE polizas_ajustes_solicitudes ADD COLUMN categoria_cambio VARCHAR(50) DEFAULT 'financiero' AFTER poliza_id");
echo "Alter table done: " . $db->error;
?>
