<?php
/**
 * API: Skins / Apariencia
 * Gestiona la configuración de temas (skins) por empresa y por usuario.
 * Versión: 1.0 | Mayo 2026
 */

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$uri    = $_SERVER['REQUEST_URI'];
$parts  = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
$action = end($parts);

// El endpoint 'obtener' no requiere auth (es público — solo lee config de empresa)
$esPublico = ($action === 'obtener' || $action === 'exportar_brand');
$usuario   = null;

if (!$esPublico) {
    $usuario = verificarToken();
    if (!$usuario) {
        http_response_code(401);
        echo json_encode(['exito' => false, 'mensaje' => 'No autenticado']);
        exit;
    }
} else {
    // Intentar obtener usuario si hay token (para preferencias personales)
    try { $usuario = verificarToken(); } catch (Exception $e) { $usuario = null; }
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ── Asegurar que las tablas existen ──────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS skins_config (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id            INT DEFAULT 1,
    skin_activo           ENUM('indigo','obsidian','coral','custom') DEFAULT 'indigo',
    brand_primary         VARCHAR(7),
    brand_primary_dark    VARCHAR(7),
    brand_primary_light   VARCHAR(7),
    brand_secondary       VARCHAR(7),
    brand_gradient_start  VARCHAR(7),
    brand_gradient_end    VARCHAR(7),
    brand_bg              VARCHAR(7),
    brand_surface         VARCHAR(7),
    brand_text            VARCHAR(7),
    brand_text_secondary  VARCHAR(7),
    brand_nombre_empresa  VARCHAR(150),
    brand_tipografia      ENUM('Inter','Roboto','Poppins','Outfit') DEFAULT 'Inter',
    brand_modo            ENUM('light','dark') DEFAULT 'light',
    updated_at            DATETIME ON UPDATE CURRENT_TIMESTAMP,
    updated_by            INT
)");

$db->exec("CREATE TABLE IF NOT EXISTS user_skin_preference (
    usuario_id  INT PRIMARY KEY,
    skin        ENUM('indigo','obsidian','coral','custom','sistema') DEFAULT 'sistema',
    updated_at  DATETIME ON UPDATE CURRENT_TIMESTAMP
)");

// Asegurar fila por defecto en skins_config
$row = $db->query("SELECT id FROM skins_config WHERE empresa_id = 1 LIMIT 1")->fetch();
if (!$row) {
    $db->exec("INSERT INTO skins_config (empresa_id, skin_activo) VALUES (1, 'indigo')");
}

// ── ROUTER ────────────────────────────────────────────────────────────────────
try {
    switch ($action) {
        case 'obtener':
            accionObtener($db, $usuario);
            break;
        case 'guardar_empresa':
            requerirMetodo('POST');
            accionGuardarEmpresa($db, $usuario);
            break;
        case 'guardar_usuario':
            requerirMetodo('POST');
            accionGuardarUsuario($db, $usuario);
            break;
        case 'guardar_custom':
            requerirMetodo('POST');
            accionGuardarCustom($db, $usuario);
            break;
        case 'exportar_brand':
            accionExportarBrand($db);
            break;
        case 'importar_brand':
            requerirMetodo('POST');
            accionImportarBrand($db, $usuario);
            break;
        default:
            http_response_code(404);
            echo json_encode(['exito' => false, 'mensaje' => 'Acción no encontrada']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error interno: ' . $e->getMessage()]);
}

// ── FUNCIONES ─────────────────────────────────────────────────────────────────

function accionObtener($db, $usuario) {
    // Config de empresa
    $empresa = $db->query("SELECT * FROM skins_config WHERE empresa_id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Preferencia personal del usuario (solo si está autenticado)
    $prefRow = null;
    if ($usuario && isset($usuario['id'])) {
        $pref = $db->prepare("SELECT skin FROM user_skin_preference WHERE usuario_id = ?");
        $pref->execute([$usuario['id']]);
        $prefRow = $pref->fetch(PDO::FETCH_ASSOC);
    }

    $skinEfectivo = ($prefRow && $prefRow['skin'] !== 'sistema')
        ? $prefRow['skin']
        : ($empresa['skin_activo'] ?? 'indigo');

    echo json_encode([
        'exito'          => true,
        'skin_empresa'   => $empresa['skin_activo'] ?? 'indigo',
        'skin_usuario'   => $prefRow['skin'] ?? 'sistema',
        'skin_efectivo'  => $skinEfectivo,
        'custom_tokens'  => [
            'brand_primary'        => $empresa['brand_primary'] ?? '#4f46e5',
            'brand_primary_dark'   => $empresa['brand_primary_dark'] ?? '#4338ca',
            'brand_primary_light'  => $empresa['brand_primary_light'] ?? '#eef2ff',
            'brand_secondary'      => $empresa['brand_secondary'] ?? '#7c3aed',
            'brand_gradient_start' => $empresa['brand_gradient_start'] ?? '#4f46e5',
            'brand_gradient_end'   => $empresa['brand_gradient_end'] ?? '#7c3aed',
            'brand_bg'             => $empresa['brand_bg'] ?? '#f4f6fb',
            'brand_surface'        => $empresa['brand_surface'] ?? '#ffffff',
            'brand_text'           => $empresa['brand_text'] ?? '#1f2937',
            'brand_text_secondary' => $empresa['brand_text_secondary'] ?? '#374151',
        ],
        'brand_nombre_empresa' => $empresa['brand_nombre_empresa'] ?? 'MAS QUE FIANZAS',
        'brand_tipografia'     => $empresa['brand_tipografia'] ?? 'Inter',
        'brand_modo'           => $empresa['brand_modo'] ?? 'light',
    ]);
}

function accionGuardarEmpresa($db, $usuario) {
    // Solo Admin (perfil_id = 1) puede cambiar el skin de empresa
    if (($usuario['perfil_id'] ?? 99) > 1) {
        http_response_code(403);
        echo json_encode(['exito' => false, 'mensaje' => 'Solo los administradores pueden cambiar el tema de empresa.']);
        return;
    }
    $body = json_decode(file_get_contents('php://input'), true);
    $skin = in_array($body['skin'] ?? '', ['indigo','obsidian','coral','custom']) ? $body['skin'] : 'indigo';

    $stmt = $db->prepare("UPDATE skins_config SET skin_activo = ?, updated_by = ?, updated_at = NOW() WHERE empresa_id = 1");
    $stmt->execute([$skin, $usuario['id']]);
    echo json_encode(['exito' => true, 'mensaje' => "Tema de empresa actualizado a '$skin'.", 'skin' => $skin]);
}

function accionGuardarUsuario($db, $usuario) {
    $body = json_decode(file_get_contents('php://input'), true);
    $skin = in_array($body['skin'] ?? '', ['indigo','obsidian','coral','custom','sistema']) ? $body['skin'] : 'sistema';

    $stmt = $db->prepare("INSERT INTO user_skin_preference (usuario_id, skin, updated_at)
                          VALUES (?, ?, NOW())
                          ON DUPLICATE KEY UPDATE skin = ?, updated_at = NOW()");
    $stmt->execute([$usuario['id'], $skin, $skin]);
    echo json_encode(['exito' => true, 'mensaje' => "Preferencia personal actualizada a '$skin'.", 'skin' => $skin]);
}

function accionGuardarCustom($db, $usuario) {
    if (($usuario['perfil_id'] ?? 99) > 1) {
        http_response_code(403);
        echo json_encode(['exito' => false, 'mensaje' => 'Solo los administradores pueden configurar el tema personalizado.']);
        return;
    }
    $b = json_decode(file_get_contents('php://input'), true);
    $campos = ['brand_primary','brand_primary_dark','brand_primary_light','brand_secondary',
               'brand_gradient_start','brand_gradient_end','brand_bg','brand_surface',
               'brand_text','brand_text_secondary','brand_nombre_empresa','brand_tipografia','brand_modo'];
    $sets   = [];
    $vals   = [];
    foreach ($campos as $c) {
        if (isset($b[$c])) { $sets[] = "$c = ?"; $vals[] = $b[$c]; }
    }
    if (!$sets) { echo json_encode(['exito' => false, 'mensaje' => 'Sin datos para guardar.']); return; }
    $vals[] = $usuario['id'];
    $stmt = $db->prepare("UPDATE skins_config SET " . implode(', ', $sets) . ", updated_by = ?, updated_at = NOW() WHERE empresa_id = 1");
    $stmt->execute($vals);
    echo json_encode(['exito' => true, 'mensaje' => 'Configuración de marca personalizada guardada.']);
}

function accionExportarBrand($db) {
    $empresa = $db->query("SELECT * FROM skins_config WHERE empresa_id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    header('Content-Disposition: attachment; filename="brand-config.json"');
    header('Content-Type: application/json');
    unset($empresa['id'], $empresa['empresa_id'], $empresa['updated_at'], $empresa['updated_by']);
    echo json_encode($empresa, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function accionImportarBrand($db, $usuario) {
    if (($usuario['perfil_id'] ?? 99) > 1) {
        http_response_code(403);
        echo json_encode(['exito' => false, 'mensaje' => 'Solo administradores.']);
        return;
    }
    $b = json_decode(file_get_contents('php://input'), true);
    // Reutilizar lógica de guardar_custom
    accionGuardarCustom($db, $usuario);
}

function requerirMetodo($metodo) {
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        http_response_code(405);
        echo json_encode(['exito' => false, 'mensaje' => "Método $metodo requerido."]);
        exit;
    }
}
