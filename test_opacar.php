<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');
$res = $db->query("SHOW COLUMNS FROM pdf_campos");
print_r($res->fetch_all(MYSQLI_ASSOC));
