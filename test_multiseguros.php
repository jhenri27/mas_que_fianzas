<?php
$db = new mysqli('localhost', 'root', '', 'masque_fianzas_integrada_01');
$db->query("UPDATE pdf_plantillas SET tipo_plantilla = 'marbete' WHERE id = 23");
echo "Update complete. Affected rows: " . $db->affected_rows;
