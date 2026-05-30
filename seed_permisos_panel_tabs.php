<?php
/**
 * SEED: Permisos de Panel y Tabs — MAS QUE FIANZAS
 * ====================================================
 * Inserta/actualiza módulos, funciones y permisos de:
 *   - Módulo COMISIONES (nuevo)
 *   - Pestañas de POLIZAS, COTIZACIONES y CONFIGURACION
 *
 * IDEMPOTENTE: usa INSERT IGNORE + ON DUPLICATE KEY UPDATE.
 * Parámetros GET:
 *   ?dry_run=1   → preview sin cambios en BD
 *   ?token=XXXX  → acceso desde IPs externas con token
 *
 * URL: http://localhost/PLATAFORMA_INTEGRADA/seed_permisos_panel_tabs.php
 */

// ─── 1. SEGURIDAD ────────────────────────────────────────────────────────────
define('SEED_ADMIN_TOKEN', 'MQF_SEED_2026_SECURE');   // Cambia este token

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

// ─── 3. DATOS A INSERTAR ─────────────────────────────────────────────────────

/**
 * Módulos requeridos.
 * Clave: nombre_modulo real en BD (lowercase, tal como está insertado).
 * El campo `icono` y `nombre_ruta` se usan solo al CREAR módulos nuevos.
 */
$modulos_def = [
    'comisiones'   => ['icono' => '💵', 'ruta' => '/modulos/comisiones.php',   'orden' => 12],
    'polizas'      => ['icono' => '📋', 'ruta' => '/modulos/polizas.php',      'orden' => 3],
    'cotizaciones' => ['icono' => '📈', 'ruta' => '/modulos/cotizaciones.php', 'orden' => 6],
    'configuracion'=> ['icono' => '⚙️', 'ruta' => '/modulos/configuracion.php','orden' => 8],
    'reportes'     => ['icono' => '📊', 'ruta' => '/modulos/reportes.php',      'orden' => 9],
];

/**
 * Funciones a insertar por módulo.
 * Estructura: modulo_key => [ [codigo, nombre, tipo_permiso], ... ]
 * tipo_permiso debe ser uno de los valores del ENUM en funciones_modulo:
 *   crear | editar | eliminar | consultar | reportes | completo | validar | registrar | seguimiento | limitado
 */
$funciones_def = [
    'comisiones' => [
        ['COM_PANEL_VER',      'Ver Panel de Comisiones',                   'consultar'],
        ['COM_PANEL_GLOBAL',   'Ver Comisiones de Todos los Usuarios',      'reportes' ],
        ['COM_EXPORTAR_PDF',   'Exportar Reporte PDF con Código QR',        'reportes' ],
        ['COM_ENVIAR_EMAIL',   'Enviar Reporte por Correo Electrónico',     'completo' ],
        ['COM_VER_PROYECCION', 'Ver Proyección Mensual de Comisiones',      'reportes' ],
        ['COM_VER_CXC',        'Ver CxC / Comisiones en Tránsito',         'consultar'],
        ['COM_DETALLE_POLIZA', 'Ver Modal de Detalle de Póliza Individual', 'consultar'],
        ['COM_IMPRIMIR',       'Imprimir desde Modal de Comisiones',        'completo' ],
    ],
    'polizas' => [
        ['TAB_POL_CONSULTAR',  'Pestaña Consultar Pólizas',         'consultar'],
        ['TAB_POL_NUEVA',      'Pestaña Nueva Póliza (Wizard)',     'crear'    ],
        ['TAB_POL_COMISIONES', 'Pestaña Comisiones en Pólizas',    'reportes' ],
    ],
    'cotizaciones' => [
        ['TAB_COT_SEGUROS',    'Pestaña Cotización Seguros de Ley', 'consultar'],
        ['TAB_COT_FIANZAS',    'Pestaña Cotización Fianzas',        'consultar'],
        ['TAB_COT_HISTORIAL',  'Pestaña Historial de Cotizaciones', 'reportes' ],
    ],
    'configuracion' => [
        ['TAB_CONF_GENERALES', 'Subpanel Configuración General',     'consultar'],
        ['TAB_CONF_SEGURIDAD', 'Subpanel Seguridad y Accesos SMTP',  'editar'   ],
        ['TAB_CONF_PERFILES',  'Subpanel Perfiles y Permisos',       'editar'   ],
        ['TAB_CONF_SKINS',     'Subpanel UX y Apariencia',           'editar'   ],
    ],
    'reportes' => [
        ['TAB_REP_MODELADOR',  'Pestaña Modelador PDF-DOCS',        'consultar'],
        ['TAB_REP_GENERALES',  'Pestaña Reportes Generales',        'reportes' ],
    ],
];

/**
 * Malla de permisos: funcion_codigo => [ perfil_id => puede_ejecutar ]
 * Perfiles: 1=Admin, 2=GteTec, 3=GteCont, 4=GteCom, 5=Socio, 6=Cajero, 7=Auditor, 8=Usuario
 */
$malla = [
    //  funcion_codigo          => [1, 2, 3, 4, 5, 6, 7, 8]
    'COM_PANEL_VER'      => [1, 1, 1, 1, 1, 0, 1, 1],
    'COM_PANEL_GLOBAL'   => [1, 1, 1, 1, 0, 0, 1, 0],
    'COM_EXPORTAR_PDF'   => [1, 0, 1, 1, 1, 0, 1, 1],
    'COM_ENVIAR_EMAIL'   => [1, 0, 1, 1, 0, 0, 0, 0],
    'COM_VER_PROYECCION' => [1, 0, 1, 1, 0, 0, 1, 0],
    'COM_VER_CXC'        => [1, 1, 1, 1, 0, 0, 1, 0],
    'COM_DETALLE_POLIZA' => [1, 1, 1, 1, 1, 0, 1, 1],
    'COM_IMPRIMIR'       => [1, 1, 1, 1, 1, 0, 1, 1],
    'TAB_POL_CONSULTAR'  => [1, 1, 1, 1, 1, 0, 1, 1],
    'TAB_POL_NUEVA'      => [1, 1, 0, 1, 0, 0, 0, 0],
    'TAB_POL_COMISIONES' => [1, 1, 1, 1, 1, 0, 1, 1],
    'TAB_COT_SEGUROS'    => [1, 1, 0, 1, 1, 0, 1, 1],
    'TAB_COT_FIANZAS'    => [1, 1, 0, 1, 1, 0, 1, 1],
    'TAB_COT_HISTORIAL'  => [1, 1, 1, 1, 1, 0, 1, 1],
    'TAB_CONF_GENERALES' => [1, 0, 0, 0, 0, 0, 1, 0],
    'TAB_CONF_SEGURIDAD' => [1, 1, 0, 0, 0, 0, 1, 0],
    'TAB_CONF_PERFILES'  => [1, 0, 0, 0, 0, 0, 1, 0],
    'TAB_CONF_SKINS'     => [1, 1, 0, 1, 0, 0, 1, 0],
    'TAB_REP_MODELADOR'  => [1, 1, 0, 1, 1, 0, 1, 1],
    'TAB_REP_GENERALES'  => [1, 1, 1, 1, 0, 0, 1, 0],
];

// Orden de perfiles para la malla (índice 0 = perfil_id 1, etc.)
$perfil_ids = [1, 2, 3, 4, 5, 6, 7, 8];

// ─── 4. CONTADORES ───────────────────────────────────────────────────────────
$stats = [
    'modulos_creados'    => 0, 'modulos_existentes'    => 0,
    'funciones_creadas'  => 0, 'funciones_existentes'  => 0,
    'permisos_insertados'=> 0, 'permisos_actualizados' => 0,
    'errores'            => [],
    'log'                => [],
];

// ─── 5. HTML HEADER ──────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed: Permisos Panel & Tabs — MAS QUE FIANZAS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f1117;
            color: #e2e8f0;
            padding: 2rem;
            min-height: 100vh;
        }
        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.25rem;
        }
        .subtitle { color: #64748b; font-size: 0.85rem; margin-bottom: 1.5rem; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .badge-dry  { background:#7c3aed22; color:#a78bfa; border:1px solid #7c3aed55; }
        .badge-live { background:#16a34a22; color:#4ade80; border:1px solid #16a34a55; }
        .card {
            background: #1e2433;
            border: 1px solid #2d3748;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
        }
        .card h2 { font-size: 1rem; font-weight: 600; color: #94a3b8; margin-bottom: 1rem; }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }
        .stat {
            background: #0f1117;
            border-radius: 8px;
            padding: 0.9rem 1rem;
            border: 1px solid #2d3748;
            text-align: center;
        }
        .stat .number { font-size: 2rem; font-weight: 800; }
        .stat .label  { font-size: 0.72rem; color: #64748b; margin-top: 0.2rem; }
        .green  { color: #4ade80; }
        .yellow { color: #fbbf24; }
        .blue   { color: #38bdf8; }
        .red    { color: #f87171; }
        .gray   { color: #64748b; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        th {
            background: #0f1117;
            color: #64748b;
            text-align: left;
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid #2d3748;
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #1a2035;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #ffffff08; }
        .tag {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .tag-new  { background:#0369a122; color:#38bdf8; }
        .tag-skip { background:#37415122; color:#64748b; }
        .tag-upd  { background:#92400e22; color:#fbbf24; }
        code {
            font-family: 'Cascadia Code', 'Consolas', monospace;
            font-size: 0.78rem;
            background: #0f1117;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            color: #a5f3fc;
        }
        .perm-dot {
            display: inline-block;
            width: 14px; height: 14px;
            border-radius: 50%;
            text-align: center;
            line-height: 14px;
            font-size: 9px;
        }
        .dot-on  { background:#16a34a33; color:#4ade80; border:1px solid #16a34a; }
        .dot-off { background:#37415133; color:#64748b; border:1px solid #374151; }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .alert-warn { background:#92400e22; border:1px solid #92400e55; color:#fbbf24; }
        .alert-ok   { background:#14532d22; border:1px solid #14532d55; color:#4ade80; }
        .scroll-x { overflow-x: auto; }
    </style>
</head>
<body>

<h1>🌱 Seed: Permisos Panel &amp; Tabs</h1>
<p class="subtitle">
    MAS QUE FIANZAS — Base de datos: <code><?= DB_NAME ?></code> &nbsp;|&nbsp;
    IP: <code><?= htmlspecialchars($ip) ?></code>
    <?php if ($dry_run): ?>
        <span class="badge badge-dry">🔍 DRY RUN — Sin cambios en BD</span>
    <?php else: ?>
        <span class="badge badge-live">✅ MODO REAL</span>
    <?php endif; ?>
</p>

<?php if ($dry_run): ?>
<div class="alert alert-warn">
    ⚠️ <strong>Dry Run activo.</strong> Se mostrará el preview de lo que se insertaría/actualizaría, pero NO se realizarán cambios en la base de datos.
    Para aplicar, ejecuta sin el parámetro <code>?dry_run=1</code>.
</div>
<?php endif; ?>

<?php

// ─── 6. LEER PERFILES REALES DE LA BD ────────────────────────────────────────
$perfiles_bd = [];
$res = $db->query("SELECT id, nombre_perfil FROM perfiles ORDER BY id");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $perfiles_bd[$row['id']] = $row['nombre_perfil'];
    }
}

// ─── 7. FUNCIÓN HELPER: ejecutar o simular ───────────────────────────────────
function db_exec($db, $sql, $dry_run) {
    if ($dry_run) return true;
    return $db->query($sql);
}

// ─── 8. PROCESAR MÓDULOS ─────────────────────────────────────────────────────
$modulo_ids = [];  // nombre_modulo => id

foreach ($modulos_def as $nombre => $meta) {
    // Buscar si ya existe
    $stmt = $db->prepare("SELECT id FROM modulos WHERE nombre_modulo = ?");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($row = $res->fetch_assoc()) {
        $modulo_ids[$nombre] = $row['id'];
        $stats['modulos_existentes']++;
        $stats['log'][] = ['type' => 'modulo', 'action' => 'skip', 'name' => $nombre, 'id' => $row['id']];
    } else {
        // Crear módulo nuevo
        $desc = ucfirst($nombre);
        $sql_mod = "INSERT INTO modulos (nombre_modulo, descripcion, icono, nombre_ruta, orden_menu, estado)
                    VALUES ('{$nombre}', 'Módulo " . ucwords($nombre) . "', '{$meta['icono']}', '{$meta['ruta']}', {$meta['orden']}, 'activo')";

        if (!$dry_run) {
            $db->query($sql_mod);
            $new_id = $db->insert_id;
            $modulo_ids[$nombre] = $new_id;
            $stats['log'][] = ['type' => 'modulo', 'action' => 'created', 'name' => $nombre, 'id' => $new_id];
        } else {
            $modulo_ids[$nombre] = 'NEW';
            $stats['log'][] = ['type' => 'modulo', 'action' => 'would_create', 'name' => $nombre, 'id' => 'NEW'];
        }
        $stats['modulos_creados']++;
    }
}

// ─── 9. PROCESAR FUNCIONES ───────────────────────────────────────────────────
$funcion_ids = [];  // codigo_funcion => id

foreach ($funciones_def as $modulo_nombre => $funciones) {
    $modulo_id = $modulo_ids[$modulo_nombre];

    foreach ($funciones as [$codigo, $nombre_fun, $tipo]) {
        // Buscar si ya existe (UNIQUE: modulo_id + codigo_funcion)
        if (is_numeric($modulo_id)) {
            $stmt = $db->prepare("SELECT id FROM funciones_modulo WHERE modulo_id = ? AND codigo_funcion = ?");
            $stmt->bind_param('is', $modulo_id, $codigo);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
            $existing = $res->fetch_assoc();
        } else {
            $existing = null; // módulo nuevo (dry_run)
        }

        if ($existing) {
            $funcion_ids[$codigo] = $existing['id'];
            $stats['funciones_existentes']++;
            $stats['log'][] = ['type' => 'funcion', 'action' => 'skip', 'codigo' => $codigo, 'id' => $existing['id']];
        } else {
            if (!$dry_run && is_numeric($modulo_id)) {
                $stmt2 = $db->prepare(
                    "INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE nombre_funcion = VALUES(nombre_funcion), tipo_permiso = VALUES(tipo_permiso)"
                );
                $desc_fun = $nombre_fun;
                $stmt2->bind_param('issss', $modulo_id, $nombre_fun, $codigo, $desc_fun, $tipo);
                $stmt2->execute();
                $new_fid = $db->insert_id ?: null;
                $stmt2->close();

                if ($new_fid) {
                    $funcion_ids[$codigo] = $new_fid;
                } else {
                    // ON DUPLICATE KEY — recuperar el id
                    $stmt3 = $db->prepare("SELECT id FROM funciones_modulo WHERE modulo_id = ? AND codigo_funcion = ?");
                    $stmt3->bind_param('is', $modulo_id, $codigo);
                    $stmt3->execute();
                    $r3 = $stmt3->get_result()->fetch_assoc();
                    $stmt3->close();
                    $funcion_ids[$codigo] = $r3['id'] ?? null;
                }
                $stats['log'][] = ['type' => 'funcion', 'action' => 'created', 'codigo' => $codigo, 'id' => $funcion_ids[$codigo]];
            } else {
                $funcion_ids[$codigo] = 'NEW';
                $stats['log'][] = ['type' => 'funcion', 'action' => 'would_create', 'codigo' => $codigo, 'id' => 'NEW'];
            }
            $stats['funciones_creadas']++;
        }
    }
}

// ─── 10. PROCESAR PERMISOS ───────────────────────────────────────────────────
// Recuperar modulo_id para cada función (para permisos_perfil.modulo_id)
$funcion_modulo_map = []; // codigo_funcion => modulo_id
foreach ($funciones_def as $modulo_nombre => $funciones) {
    foreach ($funciones as [$codigo, ,]) {
        $funcion_modulo_map[$codigo] = $modulo_ids[$modulo_nombre];
    }
}

$permisos_preview = []; // Para la tabla de preview

foreach ($malla as $codigo => $accesos) {
    $funcion_id = $funcion_ids[$codigo] ?? null;
    $modulo_id  = $funcion_modulo_map[$codigo] ?? null;

    foreach ($perfil_ids as $idx => $perfil_id) {
        $puede_ejecutar = (int)($accesos[$idx] ?? 0);

        // Datos de preview
        $permisos_preview[] = [
            'codigo'         => $codigo,
            'perfil_id'      => $perfil_id,
            'perfil_nombre'  => $perfiles_bd[$perfil_id] ?? "Perfil #$perfil_id",
            'puede_ejecutar' => $puede_ejecutar,
            'funcion_id'     => $funcion_id,
            'modulo_id'      => $modulo_id,
        ];

        if (!$dry_run && is_numeric($funcion_id) && is_numeric($modulo_id)) {
            $creado_por = 1; // Admin

            // ver_datos y ver_reportes siguen la regla de puede_ejecutar
            $ver_datos    = $puede_ejecutar;
            $ver_reportes = $puede_ejecutar;
            $crear_datos  = 0;
            $editar_datos = 0;
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
                    ver_reportes    = VALUES(ver_reportes),
                    exportar_datos  = VALUES(exportar_datos),
                    modulo_id       = VALUES(modulo_id)";

            $stmt_p = $db->prepare($sql_perm);
            $stmt_p->bind_param(
                'iiiiiiiiiiii',
                $perfil_id, $funcion_id, $modulo_id, $puede_ejecutar,
                $ver_datos, $crear_datos, $editar_datos, $eliminar_datos,
                $ver_reportes, $exportar_datos, $solo_propios, $creado_por
            );
            $ok = $stmt_p->execute();
            $stmt_p->close();

            if ($ok) {
                if ($db->affected_rows == 1) $stats['permisos_insertados']++;
                elseif ($db->affected_rows == 2) $stats['permisos_actualizados']++;
                // affected_rows == 0 means no change (same value)
            } else {
                $stats['errores'][] = "Error en permiso $codigo / perfil $perfil_id: " . $db->error;
            }
        }
    }
}

?>

<!-- ─── RESUMEN VISUAL ──────────────────────────────────────────────────────── -->
<div class="card">
    <h2>📊 Resumen de Ejecución</h2>
    <div class="stat-grid">
        <div class="stat">
            <div class="number <?= $stats['modulos_creados'] > 0 ? 'green' : 'gray' ?>">
                <?= $dry_run ? '~' : '' ?><?= $stats['modulos_creados'] ?>
            </div>
            <div class="label">Módulos <?= $dry_run ? 'a crear' : 'creados' ?></div>
        </div>
        <div class="stat">
            <div class="number yellow"><?= $stats['modulos_existentes'] ?></div>
            <div class="label">Módulos ya existían</div>
        </div>
        <div class="stat">
            <div class="number <?= $stats['funciones_creadas'] > 0 ? 'green' : 'gray' ?>">
                <?= $dry_run ? '~' : '' ?><?= $stats['funciones_creadas'] ?>
            </div>
            <div class="label">Funciones <?= $dry_run ? 'a insertar' : 'insertadas' ?></div>
        </div>
        <div class="stat">
            <div class="number yellow"><?= $stats['funciones_existentes'] ?></div>
            <div class="label">Funciones ya existían</div>
        </div>
        <div class="stat">
            <div class="number blue">
                <?= $dry_run ? count($permisos_preview) : $stats['permisos_insertados'] ?>
            </div>
            <div class="label">Permisos <?= $dry_run ? 'a procesar' : 'insertados' ?></div>
        </div>
        <?php if (!$dry_run): ?>
        <div class="stat">
            <div class="number yellow"><?= $stats['permisos_actualizados'] ?></div>
            <div class="label">Permisos actualizados</div>
        </div>
        <?php endif; ?>
        <?php if (!empty($stats['errores'])): ?>
        <div class="stat">
            <div class="number red"><?= count($stats['errores']) ?></div>
            <div class="label">Errores</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── ERRORES ─────────────────────────────────────────────────────────────── -->
<?php if (!empty($stats['errores'])): ?>
<div class="card" style="border-color:#7f1d1d55;">
    <h2 class="red">❌ Errores Encontrados</h2>
    <?php foreach ($stats['errores'] as $err): ?>
    <p style="color:#f87171;font-size:0.82rem;margin-top:0.5rem;">• <?= htmlspecialchars($err) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ─── MÓDULOS ──────────────────────────────────────────────────────────────── -->
<div class="card">
    <h2>📦 Módulos</h2>
    <table>
        <thead>
            <tr>
                <th>Módulo</th><th>ID en BD</th><th>Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($modulo_ids as $nombre => $id): ?>
            <?php
            $log_entry = array_filter($stats['log'], fn($l) => $l['type'] === 'modulo' && $l['name'] === $nombre);
            $entry = reset($log_entry);
            $action = $entry['action'] ?? '';
            ?>
            <tr>
                <td><code><?= htmlspecialchars($nombre) ?></code></td>
                <td><?= is_numeric($id) ? "<code>$id</code>" : '<span class="gray">pendiente</span>' ?></td>
                <td>
                    <?php if (str_contains($action, 'skip')): ?>
                        <span class="tag tag-skip">⏭️ Ya existía</span>
                    <?php elseif (str_contains($action, 'created')): ?>
                        <span class="tag tag-new">✅ Creado</span>
                    <?php else: ?>
                        <span class="tag tag-new">🔍 Se crearía</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ─── FUNCIONES ─────────────────────────────────────────────────────────────── -->
<div class="card">
    <h2>⚙️ Funciones del Módulo</h2>
    <div class="scroll-x">
    <table>
        <thead>
            <tr>
                <th>Código Función</th><th>Módulo</th><th>ID en BD</th><th>Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($funciones_def as $modulo_nombre => $funciones): ?>
            <?php foreach ($funciones as [$codigo, $nombre_fun, $tipo]): ?>
            <?php
            $log_entry = array_filter($stats['log'], fn($l) => $l['type'] === 'funcion' && $l['codigo'] === $codigo);
            $entry = reset($log_entry);
            $action = $entry['action'] ?? '';
            $fid = $funcion_ids[$codigo] ?? '?';
            ?>
            <tr>
                <td><code><?= htmlspecialchars($codigo) ?></code></td>
                <td><code><?= htmlspecialchars($modulo_nombre) ?></code></td>
                <td><?= is_numeric($fid) ? "<code>$fid</code>" : '<span class="gray">pendiente</span>' ?></td>
                <td>
                    <?php if (str_contains($action, 'skip')): ?>
                        <span class="tag tag-skip">⏭️ Ya existía</span>
                    <?php elseif ($action === 'created'): ?>
                        <span class="tag tag-new">✅ Insertada</span>
                    <?php else: ?>
                        <span class="tag tag-new">🔍 Se insertaría</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ─── MALLA DE PERMISOS ─────────────────────────────────────────────────────── -->
<div class="card">
    <h2>🔐 Malla de Permisos por Perfil</h2>
    <div class="scroll-x">
    <table>
        <thead>
            <tr>
                <th>Función</th>
                <?php foreach ($perfil_ids as $pid): ?>
                    <th style="text-align:center;"><?= htmlspecialchars($perfiles_bd[$pid] ?? "P$pid") ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($malla as $codigo => $accesos): ?>
            <tr>
                <td><code><?= htmlspecialchars($codigo) ?></code></td>
                <?php foreach ($accesos as $val): ?>
                    <td style="text-align:center;">
                        <?php if ($val): ?>
                            <span class="perm-dot dot-on">✓</span>
                        <?php else: ?>
                            <span class="perm-dot dot-off">–</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ─── RESULTADO FINAL ──────────────────────────────────────────────────────── -->
<div class="card" style="border-color:<?= empty($stats['errores']) ? '#14532d55' : '#7f1d1d55' ?>;">
    <?php if (empty($stats['errores'])): ?>
    <div class="alert alert-ok" style="margin-bottom:0;">
        <?= $dry_run
            ? '🔍 <strong>Dry Run completado.</strong> Ejecuta sin <code>?dry_run=1</code> para aplicar los cambios.'
            : '✅ <strong>Seed ejecutado correctamente.</strong> Módulos, funciones y permisos sincronizados.'
        ?>
        <br>
        <span style="font-size:0.78rem;color:#86efac;margin-top:0.3rem;display:block;">
            Ejecutado: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp; BD: <?= DB_NAME ?>
        </span>
    </div>
    <?php else: ?>
    <div class="alert alert-warn" style="margin-bottom:0;">
        ⚠️ Seed completado con <strong><?= count($stats['errores']) ?> errores</strong>. Revisa la sección de errores arriba.
    </div>
    <?php endif; ?>
</div>

</body>
</html>
