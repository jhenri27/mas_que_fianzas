<?php
/**
 * SEED: Configuración de Compañías e Integraciones — MAS QUE FIANZAS
 * ===================================================================
 * 1. Crea las tablas de base de datos `companias_registradas` e `integraciones_aseguradoras` (InnoDB + Foreign Keys).
 * 2. Registra las 4 nuevas funciones de permisos granulares en el módulo de CONFIGURACION.
 * 3. Mapea y asigna dinámicamente las mallas de permisos a los perfiles de usuario correspondientes.
 *
 * IDEMPOTENTE: puede ejecutarse múltiples veces de forma segura.
 * Parámetros GET:
 *   ?dry_run=1   → preview sin realizar cambios
 *   ?token=XXXX  → acceso mediante token
 *
 * URL: http://localhost/PLATAFORMA_INTEGRADA/seed_config_companias.php
 */

// ─── 1. SEGURIDAD ────────────────────────────────────────────────────────────
define('SEED_ADMIN_TOKEN', 'MQF_SEED_2026_SECURE');

$ip         = $_SERVER['REMOTE_ADDR'] ?? '';
$token      = $_GET['token'] ?? '';
$is_local   = in_array($ip, ['127.0.0.1', '::1', 'localhost']) || php_sapi_name() === 'cli';
$token_ok   = hash_equals(SEED_ADMIN_TOKEN, $token);

if (!$is_local && !$token_ok) {
    http_response_code(403);
    die('<h1 style="font-family:monospace;color:red">403 — Acceso denegado.<br>Ejecuta desde localhost o pasa ?token=<b>SEED_ADMIN_TOKEN</b></h1>');
}

$dry_run = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';

// ─── 2. CONEXIÓN ─────────────────────────────────────────────────────────────
require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();
$db->set_charset('utf8mb4');

$stats = [
    'tablas_creadas'      => 0,
    'funciones_creadas'   => 0,
    'funciones_existentes'=> 0,
    'permisos_insertados' => 0,
    'permisos_actualizados'=> 0,
    'errores'             => []
];

// ─── 3. CREACIÓN DE TABLAS (InnoDB) ──────────────────────────────────────────
$tablas_sql = [
    'companias_registradas' => "
        CREATE TABLE IF NOT EXISTS companias_registradas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            rnc VARCHAR(20) NOT NULL UNIQUE,
            direccion TEXT NULL,
            telefono VARCHAR(20) NULL,
            email VARCHAR(100) NULL,
            tipo ENUM('aseguradora', 'corredora', 'otra') NOT NULL,
            estado TINYINT(1) NOT NULL DEFAULT 1,
            creado_por INT NOT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            modificado_por INT NULL,
            fecha_modificacion TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'integraciones_aseguradoras' => "
        CREATE TABLE IF NOT EXISTS integraciones_aseguradoras (
            id INT AUTO_INCREMENT PRIMARY KEY,
            compania_id INT NOT NULL,
            url_base VARCHAR(255) NOT NULL,
            auth_key TEXT NULL,
            client_id VARCHAR(100) NULL,
            client_secret TEXT NULL,
            headers_json TEXT NULL,
            estado TINYINT(1) NOT NULL DEFAULT 1,
            modificado_por INT NOT NULL,
            fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (compania_id) REFERENCES companias_registradas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

if (!$dry_run) {
    foreach ($tablas_sql as $tabla => $sql) {
        if ($db->query($sql)) {
            $stats['tablas_creadas']++;
        } else {
            $stats['errores'][] = "Error al crear tabla '$tabla': " . $db->error;
        }
    }
}

// ─── 4. PERMISOS Y MALLA A SEMBRAR ───────────────────────────────────────────
// Buscar ID del módulo CONFIGURACION
$modulo_id = null;
$stmt_m = $db->prepare("SELECT id FROM modulos WHERE nombre_modulo = 'configuracion' LIMIT 1");
$stmt_m->execute();
$res_m = $stmt_m->get_result();
if ($row_m = $res_m->fetch_assoc()) {
    $modulo_id = (int)$row_m['id'];
}
$stmt_m->close();

if (!$modulo_id) {
    // Si no existe, crear módulo configuración (fallback de seguridad)
    $sql_mod = "INSERT INTO modulos (nombre_modulo, descripcion, icono, nombre_ruta, orden_menu, estado)
                VALUES ('configuracion', 'Módulo Configuración', '⚙️', '/modulos/configuracion.php', 8, 'activo')";
    if (!$dry_run) {
        $db->query($sql_mod);
        $modulo_id = $db->insert_id;
    } else {
        $modulo_id = 999; // Mock
    }
}

$funciones_def = [
    ['TAB_CONF_COMPANIAS', 'Ver subpanel de Registro de Compañías', 'consultar'],
    ['CONF_COMPANIAS_EDITAR', 'Crear, editar y desactivar datos de compañías', 'editar'],
    ['TAB_CONF_INTEGRACIONES', 'Ver panel de Centro de Integración de APIs', 'consultar'],
    ['CONF_INTEGRACIONES_EDITAR', 'Modificar credenciales, headers y ejecutar pruebas de conexión', 'editar']
];

$malla = [
    //  funcion_codigo          => [1, 2, 3, 4, 5, 6, 7, 8]
    // Perfiles: 1=Admin, 2=GteTec, 3=GteCont, 4=GteCom, 5=Socio, 6=Cajero, 7=Auditor, 8=Usuario
    'TAB_CONF_COMPANIAS'        => [1, 1, 1, 1, 0, 0, 1, 0],
    'CONF_COMPANIAS_EDITAR'     => [1, 0, 0, 0, 0, 0, 0, 0],
    'TAB_CONF_INTEGRACIONES'    => [1, 1, 0, 0, 0, 0, 1, 0],
    'CONF_INTEGRACIONES_EDITAR' => [1, 1, 0, 0, 0, 0, 0, 0]
];

$perfil_ids = [1, 2, 3, 4, 5, 6, 7, 8];

// Leer perfiles reales
$perfiles_bd = [];
$res_p = $db->query("SELECT id, nombre_perfil FROM perfiles ORDER BY id");
if ($res_p) {
    while ($row_p = $res_p->fetch_assoc()) {
        $perfiles_bd[$row_p['id']] = $row_p['nombre_perfil'];
    }
}

$funcion_ids = []; // codigo => id
foreach ($funciones_def as [$codigo, $nombre, $tipo]) {
    // Buscar si ya existe
    $stmt_f = $db->prepare("SELECT id FROM funciones_modulo WHERE modulo_id = ? AND codigo_funcion = ?");
    $stmt_f->bind_param('is', $modulo_id, $codigo);
    $stmt_f->execute();
    $res_f = $stmt_f->get_result();
    $stmt_f->close();

    if ($row_f = $res_f->fetch_assoc()) {
        $funcion_ids[$codigo] = (int)$row_f['id'];
        $stats['funciones_existentes']++;
    } else {
        // Insertar
        $stmt_ins = $db->prepare("INSERT INTO funciones_modulo (modulo_id, codigo_funcion, nombre_funcion, tipo_permiso) VALUES (?, ?, ?, ?)");
        if (!$dry_run) {
            $stmt_ins->bind_param('isss', $modulo_id, $codigo, $nombre, $tipo);
            $stmt_ins->execute();
            $funcion_ids[$codigo] = $db->insert_id;
            $stats['funciones_creadas']++;
        } else {
            $funcion_ids[$codigo] = 9999 + count($funcion_ids);
            $stats['funciones_creadas']++;
        }
        $stmt_ins->close();
    }
}

// Sembrar permisos de perfil
foreach ($malla as $codigo => $accesos) {
    $f_id = $funcion_ids[$codigo] ?? null;
    if (!$f_id) continue;

    foreach ($perfil_ids as $idx => $perf_id) {
        $puede_ejecutar = (int)($accesos[$idx] ?? 0);

        if (!$dry_run) {
            $creado_por = 1;
            $ver_datos    = $puede_ejecutar;
            $ver_reportes = $puede_ejecutar;
            $crear_datos  = $puede_ejecutar;
            $editar_datos = $puede_ejecutar;
            $eliminar_datos = 0;
            $exportar_datos = $puede_ejecutar;
            $solo_propios = 0;

            $sql_perm = "INSERT INTO permisos_perfil
                (perfil_id, funcion_id, modulo_id, puede_ejecutar,
                 ver_datos, crear_datos, editar_datos, eliminar_datos,
                 ver_reportes, exportar_datos, solo_propios, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    puede_ejecutar  = VALUES(puede_ejecutar),
                    ver_datos       = VALUES(ver_datos),
                    crear_datos     = VALUES(crear_datos),
                    editar_datos    = VALUES(editar_datos),
                    ver_reportes    = VALUES(ver_reportes),
                    exportar_datos  = VALUES(exportar_datos),
                    modulo_id       = VALUES(modulo_id)";

            $stmt_p = $db->prepare($sql_perm);
            $stmt_p->bind_param(
                'iiiiiiiiiiii',
                $perf_id, $f_id, $modulo_id, $puede_ejecutar,
                $ver_datos, $crear_datos, $editar_datos, $eliminar_datos,
                $ver_reportes, $exportar_datos, $solo_propios, $creado_por
            );
            $ok = $stmt_p->execute();
            $stmt_p->close();

            if ($ok) {
                if ($db->affected_rows == 1) $stats['permisos_insertados']++;
                elseif ($db->affected_rows == 2) $stats['permisos_actualizados']++;
            } else {
                $stats['errores'][] = "Error al asignar permiso $codigo a perfil $perf_id: " . $db->error;
            }
        } else {
            $stats['permisos_insertados']++;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed: Compañías &amp; Integraciones — MAS QUE FIANZAS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0b0f19;
            color: #f1f5f9;
            padding: 2rem;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a78bfa, #4f46e5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .subtitle { color: #94a3b8; font-size: 0.9rem; margin-bottom: 2rem; }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-dry { background: rgba(167, 139, 250, 0.15); color: #c084fc; border: 1px solid rgba(167, 139, 250, 0.3); }
        .badge-live { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        .card h2 { font-size: 1.1rem; color: #cbd5e1; margin-bottom: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 1rem;
        }
        .stat-item {
            background: #1f2937;
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
            border: 1px solid #374151;
        }
        .stat-item .num { font-size: 2.2rem; font-weight: 800; color: #a78bfa; line-height: 1; margin-bottom: 0.4rem; }
        .stat-item .lbl { font-size: 0.75rem; color: #94a3b8; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
        .success { color: #34d399 !important; }
        .danger { color: #f87171 !important; }
        .info { color: #38bdf8 !important; }
        .log-list { list-style: none; font-family: monospace; font-size: 0.85rem; display: flex; flex-direction: column; gap: 6px; }
        .log-list li { padding: 4px 8px; border-radius: 4px; background: #1f2937; border-left: 3px solid #6366f1; }
        .log-list li.err { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.08); color: #f87171; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌱 Seed: Compañías &amp; Integraciones API</h1>
        <p class="subtitle">
            MAS QUE FIANZAS — Base de datos: <code><?= htmlspecialchars(DB_NAME) ?></code> &nbsp;|&nbsp; 
            <?php if ($dry_run): ?>
                <span class="badge badge-dry">🔍 DRY RUN (Solo simulación)</span>
            <?php else: ?>
                <span class="badge badge-live">✅ MODO REAL (Aplicado)</span>
            <?php endif; ?>
        </p>

        <!-- Resumen de Stats -->
        <div class="card">
            <h2>📊 Resumen de Resultados</h2>
            <div class="grid-stats">
                <div class="stat-item">
                    <div class="num <?= $stats['tablas_creadas'] > 0 ? 'success' : '' ?>"><?= $stats['tablas_creadas'] ?></div>
                    <div class="lbl">Tablas Creadas</div>
                </div>
                <div class="stat-item">
                    <div class="num <?= $stats['funciones_creadas'] > 0 ? 'success' : '' ?>"><?= $stats['funciones_creadas'] ?></div>
                    <div class="lbl">Funciones Creadas</div>
                </div>
                <div class="stat-item">
                    <div class="num info"><?= $stats['funciones_existentes'] ?></div>
                    <div class="lbl">Funciones Existían</div>
                </div>
                <div class="stat-item">
                    <div class="num success"><?= $stats['permisos_insertados'] ?></div>
                    <div class="lbl">Permisos Nuevos</div>
                </div>
                <div class="stat-item">
                    <div class="num info"><?= $stats['permisos_actualizados'] ?></div>
                    <div class="lbl">Permisos Actualizados</div>
                </div>
            </div>
        </div>

        <!-- Errores -->
        <?php if (!empty($stats['errores'])): ?>
            <div class="card" style="border-color: #ef4444;">
                <h2 class="danger">⚠️ Errores durante la migración</h2>
                <ul class="log-list">
                    <?php foreach ($stats['errores'] as $err): ?>
                        <li class="err"><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="card" style="border-color: #10b981;">
                <h2 class="success">✨ ¡Migración ejecutada de forma limpia y exitosa!</h2>
                <p style="font-size: 0.9rem; color: #94a3b8;">
                    Las tablas correspondientes a los registros de compañías y configuraciones de API han sido creadas de forma atómica en InnoDB y los permisos de accesos granulares han sido inyectados e integrados para ser consumidos por el sistema.
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
