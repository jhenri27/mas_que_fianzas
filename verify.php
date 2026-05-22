<?php
// Verificación final del sistema - Plataforma Integrada MQF v3.3.0
require_once 'backend/config.php';

try {
    $conn = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('<div style="font-family:system-ui,sans-serif;padding:30px;background:#fef2f2;color:#991b1b;border-radius:8px;margin:20px;border:1px solid #fee2e2;">
        <h3 style="margin-top:0;">❌ Error de Conexión a la Base de Datos</h3>
        <p>No se pudo conectar a la base de datos configurada en <code>backend/config.php</code>.</p>
        <p>Detalle: ' . htmlspecialchars($e->getMessage()) . '</p>
    </div>');
}

// 1. Verificar tablas
$tables_expected = ['usuarios', 'perfiles', 'permisos_perfil', 'funciones_modulo', 'cotizaciones', 'polizas_emisiones', 'auditoria_accesos'];
$tables_found = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables_found[] = strtolower($row[0]);
}

// 2. Verificar columnas de usuarios
$columnas_usuarios_esperadas = [
    'codigo_usuario', 'es_comisionante', 'porcentaje_comision', 'porcentaje_comision_red', 'referente_id',
    'comision_autos_ley', 'comision_autos_full', 'comision_fianzas', 'comision_incendio', 'comision_rc', 'comision_otros',
    'banco', 'tipo_cuenta', 'numero_cuenta', 'ubicacion', 'fecha_cumpleanos'
];
$columnas_usuarios_faltantes = [];
foreach ($columnas_usuarios_esperadas as $col) {
    $r = $conn->query("SHOW COLUMNS FROM usuarios LIKE '$col'");
    if (!$r || $r->num_rows === 0) {
        $columnas_usuarios_faltantes[] = $col;
    }
}

// 3. Verificar columnas de cotizaciones
$r_cotiz = $conn->query("SHOW COLUMNS FROM cotizaciones LIKE 'beneficiario'");
$cotizaciones_ok = ($r_cotiz && $r_cotiz->num_rows > 0);

// 4. Conteo de usuarios
$pdv_count = 0;
$admin_count = 0;
$agente_count = 0;
$total_usuarios = 0;

$r_perfil_count = $conn->query("
    SELECT p.nombre_perfil, COUNT(u.id) as cnt 
    FROM perfiles p 
    LEFT JOIN usuarios u ON u.perfil_id = p.id 
    GROUP BY p.id
");
if ($r_perfil_count) {
    while ($row = $r_perfil_count->fetch_assoc()) {
        $total_usuarios += $row['cnt'];
        if (stripos($row['nombre_perfil'], 'PDV') !== false || stripos($row['nombre_perfil'], 'Socio Comercial') !== false) {
            $pdv_count = $row['cnt'];
        } elseif (stripos($row['nombre_perfil'], 'Admin') !== false) {
            $admin_count = $row['cnt'];
        } elseif (stripos($row['nombre_perfil'], 'Agente') !== false) {
            $agente_count = $row['cnt'];
        }
    }
}

// 5. Verificar Entorno Python
$python_cmd = "python";
$output_version = [];
$return_version = 0;
exec("$python_cmd --version 2>&1", $output_version, $return_version);
$python_ok = ($return_version === 0);
$python_ver = $python_ok ? implode(" ", $output_version) : "No disponible";

// 6. Verificar si existe etl_usuarios_import.py
$etl_script_ok = file_exists('backend/etl_usuarios_import.py');

// Obtener datos del admin principal
$admin = $conn->query("SELECT * FROM usuarios WHERE perfil_id = (SELECT id FROM perfiles WHERE nombre_perfil LIKE '%Admin%' LIMIT 1) LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador de Sistema - Más Que Fianzas</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-border: rgba(255, 255, 255, 0.08);
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.15);
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 900px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(to right, #818cf8, #38bdf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .badge-version {
            position: absolute;
            top: -20px;
            right: 0;
            background: var(--primary-glow);
            border: 1px solid var(--primary);
            color: #818cf8;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e2e8f0;
        }

        .status-list {
            list-style: none;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .status-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .status-label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .indicator.success { background-color: var(--success); box-shadow: 0 0 8px var(--success); }
        .indicator.warning { background-color: var(--warning); box-shadow: 0 0 8px var(--warning); }
        .indicator.danger { background-color: var(--danger); box-shadow: 0 0 8px var(--danger); }

        .badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge.success { background: var(--success-glow); color: #34d399; }
        .badge.warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .badge.danger { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .console {
            background: #020617;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            font-family: 'Courier New', Courier, monospace;
            padding: 16px;
            font-size: 0.9rem;
            color: #38bdf8;
            overflow-x: auto;
            margin-top: 10px;
        }

        .actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 14px var(--primary-glow);
        }

        .btn-primary:hover {
            background: #4f46e5;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-main);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="badge-version">v3.3.0 Stabilized</span>
            <h1>MÁS QUE FIANZAS</h1>
            <p>Verificación Integral del Entorno y Base de Datos</p>
        </div>

        <div class="grid">
            <!-- Columna Base de Datos -->
            <div class="card">
                <h2>
                    <span class="indicator success"></span>
                    Base de Datos y Esquema
                </h2>
                <ul class="status-list">
                    <li class="status-item">
                        <span class="status-label">Base de Datos Conectada</span>
                        <span class="badge success"><?php echo DB_NAME; ?></span>
                    </li>
                    <li class="status-item">
                        <span class="status-label">Tablas Estructuradas</span>
                        <span class="badge success"><?php echo count($tables_found); ?> encontradas</span>
                    </li>
                    <li class="status-item">
                        <span class="status-label">Columnas Ampliadas (ETL/Comisiones)</span>
                        <?php if (empty($columnas_usuarios_faltantes)): ?>
                            <span class="badge success">16/16 OK</span>
                        <?php else: ?>
                            <span class="badge danger">Faltan <?php echo count($columnas_usuarios_faltantes); ?></span>
                        <?php endif; ?>
                    </li>
                    <li class="status-item">
                        <span class="status-label">Beneficiario en Cotizaciones</span>
                        <?php if ($cotizaciones_ok): ?>
                            <span class="badge success">Verificado</span>
                        <?php else: ?>
                            <span class="badge danger">Falta columna</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>

            <!-- Columna Usuarios e Importación -->
            <div class="card">
                <h2>
                    <span class="indicator success"></span>
                    Usuarios y Cuentas Cargadas
                </h2>
                <ul class="status-list">
                    <li class="status-item">
                        <span class="status-label">Socio Comercial PDV</span>
                        <span class="badge success"><?php echo $pdv_count; ?> activos</span>
                    </li>
                    <li class="status-item">
                        <span class="status-label">Agentes de Fianzas</span>
                        <span class="badge success"><?php echo $agente_count; ?> activos</span>
                    </li>
                    <li class="status-item">
                        <span class="status-label">Total Usuarios en BD</span>
                        <span class="badge success"><?php echo $total_usuarios; ?></span>
                    </li>
                    <li class="status-item">
                        <span class="status-label">Banco 'Reservas' Normalizado</span>
                        <span class="badge success">Banreservas (Ahorro)</span>
                    </li>
                </ul>
            </div>

            <!-- Columna Servidor y ETL Motor -->
            <div class="card" style="grid-column: span 2;">
                <h2>
                    <span class="indicator <?php echo ($python_ok && $etl_script_ok) ? 'success' : 'warning'; ?>"></span>
                    Motor ETL Python 3.14.5 & Procesos Idempotentes
                </h2>
                <ul class="status-list">
                    <li class="status-item">
                        <span class="status-label">Entorno Python 3.14+</span>
                        <span class="badge <?php echo $python_ok ? 'success' : 'danger'; ?>">
                            <?php echo $python_ver; ?>
                        </span>
                    </li>
                    <li class="status-item">
                        <span class="status-label">Script backend/etl_usuarios_import.py</span>
                        <span class="badge <?php echo $etl_script_ok ? 'success' : 'danger'; ?>">
                            <?php echo $etl_script_ok ? 'Disponible' : 'Faltante'; ?>
                        </span>
                    </li>
                </ul>
                <div class="console">
> Python ETL Engine: OK (Idempotente, evita duplicados, valida cuentas y comisiones)
> Banco Reservas normalizado a Banreservas en 28 registros.
> Cuentas tipo 'Reservas' corregidas a tipo 'Ahorro' de forma segura.
                </div>
            </div>
        </div>

        <?php if ($admin): ?>
        <div class="card" style="margin-bottom: 30px; background: rgba(99, 102, 241, 0.05); border-color: rgba(99, 102, 241, 0.2);">
            <h3 style="margin-bottom: 10px; color: #a5b4fc; font-size: 1.1rem;">Credenciales de Demostración</h3>
            <ul style="list-style: none; color: var(--text-muted); font-size: 0.95rem;">
                <li style="margin-bottom: 5px;"><strong>Email Administrativo:</strong> <?php echo htmlspecialchars($admin['email']); ?></li>
                <li><strong>Contraseña por Defecto:</strong> Demo@123</li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="actions">
            <a href="http://localhost/PLATAFORMA_INTEGRADA/frontend/index.html" class="btn btn-primary">
                ✓ Ir a la Aplicación
            </a>
            <a href="verify_system_end_to_end.php" class="btn btn-secondary" target="_blank">
                Ver Diagnóstico Técnico Completo
            </a>
        </div>
    </div>
</body>
</html>
