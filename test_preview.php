<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'preview_cancelar';
$_GET['id'] = 58;
$_GET['fecha_cancelacion'] = '2026-07-04';
$_SESSION['usuario_id'] = 1;

chdir('c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\api');
require 'polizas.php';
