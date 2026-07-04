<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Configuración de base de datos y utilidades (para permisos)
require_once __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();

// Validar que el usuario tiene sesión activa y es administrador (Perfil ID = 1)
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["exito" => false, "mensaje" => "No autorizado. Sesión no iniciada."]);
    exit;
}

if ($_SESSION['usuario_id'] != 1) {
    echo json_encode(["exito" => false, "mensaje" => "ERROR VAF: Permisos insuficientes. Solo LABS-QA (Administrador) puede generar actualizaciones Push."]);
    exit;
}

// Requerir el Builder de MELCA
require_once '../engine_melca/MelcaBuilder.php';

$action = $_GET['action'] ?? '';

if ($action === 'generar_update') {
    try {
        // Clave secreta (Debe coincidir en el Deployer del VPS de Producción)
        $secretKey = "MASQF_MELCA_SEC_2026_x!9qZ"; 
        
        $outputDir = __DIR__ . '/../updates/patches';
        $basePath = realpath(__DIR__ . '/../../'); // Directorio raíz de PLATAFORMA_INTEGRADA
        
        $builder = new MelcaBuilder($secretKey, $outputDir, $basePath);
        
        // --- AQUÍ DEFINIMOS QUÉ ARCHIVOS SE VAN A EMPAQUETAR EN EL PARCHE ---
        // En una implementación final, esto podría venir por POST desde la interfaz o leyendo git status
        $version = "1.0.1";
        $description = "Activación de Motor MELCA-Fixuper y Renombrado a LABS-QA";
        
        $filesToInclude = [
            "frontend/dashboard.html",
            "frontend/assets/dashboard.js",
            "frontend/modulos/labs-qa.html",
            "backend/engine_melca/MelcaDeployer.php" // Enviamos el deployer para que lo tengan en producción
        ];
        
        $sqlQueries = [
            "INSERT INTO logs_auditoria (usuario_id, modulo, accion, detalles) VALUES ({$_SESSION['usuario_id']}, 'LABS-QA', 'PUSH_UPDATE', 'Generación de actualización $version')"
        ];
        
        $result = $builder->buildPatch($version, $description, $filesToInclude, $sqlQueries);
        
        if ($result['exito']) {
            // Generar ruta relativa para descargar el archivo vía HTTP si es necesario
            $webZipPath = "/PLATAFORMA_INTEGRADA/backend/updates/patches/" . basename($result['zip_path']);
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Parche generado y firmado criptográficamente con éxito.",
                "data" => [
                    "patch_id" => $result['patch_id'],
                    "version" => $version,
                    "descargar_url" => $webZipPath,
                    "signature_meta" => $result['meta']
                ]
            ]);
        } else {
            echo json_encode(["exito" => false, "mensaje" => "Fallo al construir el parche."]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            "exito" => false,
            "mensaje" => "Excepción Crítica (VAF): " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(["exito" => false, "mensaje" => "Acción no válida."]);
}
