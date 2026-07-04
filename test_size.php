<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');
$res = $db->query("SELECT ancho, font_size FROM pdf_campos WHERE variable = 'sistema.qr_msqf' ORDER BY id DESC LIMIT 1");
print_r($res->fetch_assoc());
