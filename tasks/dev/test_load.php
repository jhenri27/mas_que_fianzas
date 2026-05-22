<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    echo "Cargando ContabilidadManager...\n";
    require_once '../ContabilidadManager.php';
    $mgr = \MQF\Finance\ContabilidadManager::getInstance();
    echo "ContabilidadManager cargado OK.\n";

    echo "Cargando MotorContable...\n";
    require_once '../MotorContable.php';
    echo "MotorContable cargado OK.\n";

    echo "Cargando NCFManager...\n";
    require_once '../NCFManager.php';
    echo "NCFManager cargado OK.\n";

    echo "Todos los componentes cargados exitosamente.";
} catch (Throwable $e) {
    echo "FALLO FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
