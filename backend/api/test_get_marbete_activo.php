<?php
$_GET['action'] = 'obtener_marbete_activo';
$_GET['aseguradora'] = 'MIDAS';

// Mock session and user
session_start();
$_SESSION['usuario_id'] = 1;

require_once 'pdf_modeler.php';
?>
