<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != 1) {
    echo json_encode(["exito" => false, "mensaje" => "No autorizado."]);
    exit;
}

// 1. Fix DB module name for LABS-QA just in case
$db->query("UPDATE modulos SET nombre_modulo = 'labs_qa' WHERE nombre_modulo = 'labs_masqf'");

// 2. Obtener nombre de la BD actual
$result = $db->query("SELECT DATABASE() as dbname");
$row = $result->fetch_assoc();
$dbName = $row['dbname'] ?? 'Desconocida';

// 3. Buscar la última actualización de MELCA
$patchesDir = __DIR__ . '/../updates/patches/';
$lastUpdate = 'Sin actualizaciones recientes';

if (is_dir($patchesDir)) {
    $files = glob($patchesDir . '*.meta.json');
    if (!empty($files)) {
        // Ordenar por tiempo de modificación (el más reciente primero)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $meta = json_decode(file_get_contents($files[0]), true);
        if ($meta && isset($meta['file'])) {
            $lastUpdate = str_replace('.zip', '', $meta['file']);
        }
    }
}

// Versión de plataforma (hardcoded here or could be fetched from config)
$platformVersion = 'v3.0.1 Stable';

echo json_encode([
    "exito" => true,
    "version" => $platformVersion,
    "db_name" => $dbName,
    "last_update" => $lastUpdate
]);
