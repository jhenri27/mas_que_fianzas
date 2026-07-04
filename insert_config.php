<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');
$db->query("INSERT IGNORE INTO configuracion_sistema (clave_config, valor_config, tipo_valor, descripcion, modificable) VALUES ('GENERAR_NCF_AUTOMATICO', '0', 'booleano', 'Generar NCF automatico (1=Si, 0=No)', 1)");
echo 'OK';
