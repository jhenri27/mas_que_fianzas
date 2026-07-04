<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');
$res = $db->query("SELECT * FROM funciones_modulo WHERE codigo_funcion LIKE '%CANCELAR%'");
print_r($res->fetch_all(MYSQLI_ASSOC));
