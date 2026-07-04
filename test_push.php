<?php
session_start();
$_SESSION['usuario_id'] = 1;
$_GET['action'] = 'generar_update';
require 'c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\api\melca_push.php';
