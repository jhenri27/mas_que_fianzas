<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'generar_update';
$_SESSION['usuario_id'] = 1;

// Simulate the auth functions bypass for test
function tienePermiso($x) { return true; }

// Because melca_push requires db.php and auth.php which might have conflicts in CLI,
// let's actually just invoke the MelcaBuilder directly as if we were the API.
require_once 'c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\engine_melca\MelcaBuilder.php';

$secretKey = "MASQF_MELCA_SEC_2026_x!9qZ"; 
$outputDir = 'c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\updates\patches';
$basePath = 'c:\wamp64\www\PLATAFORMA_INTEGRADA';

$builder = new MelcaBuilder($secretKey, $outputDir, $basePath);
$version = "1.0.1";
$description = "Activación de Motor MELCA-Fixuper y Renombrado a LABS-QA";

$filesToInclude = [
    "frontend/dashboard.html",
    "frontend/assets/dashboard.js",
    "frontend/modulos/labs-qa.html",
    "backend/engine_melca/MelcaDeployer.php" 
];

$sqlQueries = [
    "INSERT INTO logs_auditoria (usuario_id, modulo, accion, detalles) VALUES (1, 'LABS-QA', 'PUSH_UPDATE', 'Generación de actualización 1.0.1')"
];

$result = $builder->buildPatch($version, $description, $filesToInclude, $sqlQueries);
echo json_encode($result, JSON_PRETTY_PRINT);
