<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');
$res = $db->query("SELECT f.codigo_funcion, pp.puede_ejecutar, pp.perfil_id FROM permisos_perfil pp JOIN funciones_modulo f ON pp.funcion_id = f.id WHERE f.codigo_funcion LIKE '%CANCELAR%'");
print_r($res->fetch_all(MYSQLI_ASSOC));
