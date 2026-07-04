<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');
$res = $db->query("SELECT id, nombre, apellido, perfil_id FROM usuarios WHERE nombre LIKE '%Jos%'");
print_r($res->fetch_all(MYSQLI_ASSOC));
