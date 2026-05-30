<?php
/**
 * MIGRACIÓN Y SEED: Plan de Ventas Proyectado
 * ==========================================
 * 1. Crea la tabla `plan_ventas_proyectado` si no existe.
 * 2. Si la tabla `productos` está vacía, siembra productos por defecto:
 *    - "Seguro de Ley - Vehículo Liviano"
 *    - "Fianzas"
 * 3. Siembra la proyección por defecto (20 seguros de ley, 10 fianzas) para el mes y año actual.
 *
 * Ejecutar vía CLI: php seed_plan_ventas.php
 * O vía Web: http://localhost/PLATAFORMA_INTEGRADA/seed_plan_ventas.php
 */

require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();

echo "<html><head><title>Seed Plan Ventas</title><style>
body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f1117; color: #e2e8f0; padding: 20px; }
h1 { color: #38bdf8; font-size: 1.5rem; }
.ok { color: #4ade80; font-weight: bold; }
.info { color: #fbbf24; }
.error { color: #f87171; font-weight: bold; }
pre { background: #1e2433; padding: 10px; border-radius: 6px; border: 1px solid #2d3748; color: #a5f3fc; }
</style></head><body>";

echo "<h1>🌱 Migración y Seeding: Plan de Ventas Proyectado</h1>";

// ─── 1. CREACIÓN DE LA TABLA ──────────────────────────────────────────────────
$sql_table = "CREATE TABLE IF NOT EXISTS plan_ventas_proyectado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes INT NOT NULL,
    anio INT NOT NULL,
    producto_nombre VARCHAR(100) NOT NULL,
    cantidad_proyectada INT NOT NULL DEFAULT 0,
    creado_por INT NOT NULL DEFAULT 1,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plan_mes_anio_prod (mes, anio, producto_nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($db->query($sql_table)) {
    echo "<p class='ok'>✅ Tabla 'plan_ventas_proyectado' verificada/creada correctamente.</p>";
} else {
    echo "<p class='error'>❌ Error creando tabla 'plan_ventas_proyectado': " . $db->error . "</p>";
    die("</body></html>");
}

// ─── 2. SEMBRADO DE PRODUCTOS POR DEFECTO ─────────────────────────────────────
$check_prod = $db->query("SELECT COUNT(*) as cant FROM productos");
$prod_count = $check_prod->fetch_assoc()['cant'];

if ($prod_count == 0) {
    echo "<p class='info'>ℹ️ La tabla 'productos' está vacía. Sembrando productos por defecto...</p>";
    $productos_seed = [
        [
            'codigo' => 'SL-001',
            'nombre' => 'Seguro de Ley - Vehículo Liviano',
            'desc' => 'Seguro obligatorio de ley para vehículos de motor livianos (cobertura básica de ley)',
            'vigencia' => 365,
            'prima' => 2500.00,
            'comision' => 10.00
        ],
        [
            'codigo' => 'FZ-001',
            'nombre' => 'Fianzas',
            'desc' => 'Garantías y fianzas comerciales, aduanales, de licitación y de construcción',
            'vigencia' => 365,
            'prima' => 5000.00,
            'comision' => 15.00
        ]
    ];

    $stmt = $db->prepare("INSERT INTO productos (codigo_producto, nombre_producto, descripcion, vigencia_dias, estado, prima_base, comision_venta, creado_por) VALUES (?, ?, ?, ?, 'activo', ?, ?, 1)");
    if ($stmt) {
        foreach ($productos_seed as $p) {
            $stmt->bind_param('sssidd', $p['codigo'], $p['nombre'], $p['desc'], $p['vigencia'], $p['prima'], $p['comision']);
            if ($stmt->execute()) {
                echo "<p class='ok'>   • Producto sembrado: <b>{$p['nombre']}</b> (Código: {$p['codigo']})</p>";
            } else {
                echo "<p class='error'>   • Error al sembrar producto {$p['nombre']}: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    } else {
        echo "<p class='error'>❌ Error preparando sentencia para sembrar productos: " . $db->error . "</p>";
    }
} else {
    echo "<p class='ok'>✅ La tabla 'productos' ya contiene {$prod_count} registros. No se requiere sembrado.</p>";
}

// ─── 3. SEMBRADO DE LA PROYECCIÓN POR DEFECTO ─────────────────────────────────
$mes_actual = (int)date('n');
$anio_actual = (int)date('Y');

echo "<p class='info'>ℹ️ Verificando plan de proyección de ventas para el período actual ({$mes_actual}/{$anio_actual})...</p>";

$proyecciones_def = [
    'Seguro de Ley - Vehículo Liviano' => 20,
    'Fianzas' => 10
];

$stmt_plan = $db->prepare("INSERT INTO plan_ventas_proyectado (mes, anio, producto_nombre, cantidad_proyectada, creado_por) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE cantidad_proyectada = IF(cantidad_proyectada = 0, VALUES(cantidad_proyectada), cantidad_proyectada)");

if ($stmt_plan) {
    foreach ($proyecciones_def as $prod_nombre => $cant) {
        $stmt_plan->bind_param('iisi', $mes_actual, $anio_actual, $prod_nombre, $cant);
        if ($stmt_plan->execute()) {
            echo "<p class='ok'>   • Proyección sembrada/verificada: <b>{$prod_nombre}</b> -> Meta: {$cant} unidades.</p>";
        } else {
            echo "<p class='error'>   • Error sembrando proyección para {$prod_nombre}: " . $stmt_plan->error . "</p>";
        }
    }
    $stmt_plan->close();
} else {
    echo "<p class='error'>❌ Error preparando sentencia para sembrar proyección: " . $db->error . "</p>";
}

echo "<br><h2 class='ok'>✅ Proceso de inicialización completado con éxito.</h2>";
echo "</body></html>";
?>
