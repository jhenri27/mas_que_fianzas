<?php
require_once __DIR__ . '/config.php';
$db = Database::getInstance()->getConnection();
$db->query("UPDATE clientes SET correo = 'ahenriquezmarte@gmail.com'");
echo 'Done! Rows updated: '.$db->affected_rows;
?>
