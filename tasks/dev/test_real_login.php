<?php
require_once dirname(__FILE__) . '/../../backend/Autenticacion.php';
$auth = new Autenticacion();
$res = $auth->login('admin', 'Demo@123');
echo json_encode($res, JSON_PRETTY_PRINT) . "\n";
?>
