<?php
/**
 * SEED: Sincronización Retroactiva de Comisiones — MAS QUE FIANZAS
 * =================================================================
 * Este script actualiza las tasas del Administrador y calcula
 * retroactivamente las comisiones de todas las pólizas históricas.
 *
 * URL: http://localhost/PLATAFORMA_INTEGRADA/seed_comisiones_retroactivas.php
 */

require_once 'backend/config.php';
require_once 'backend/ComisionManager.php';

$db = Database::getInstance()->getConnection();
$db->set_charset('utf8mb4');

echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sincronización Retroactiva — MAS QUE FIANZAS</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; background: #0f1117; color: #e2e8f0; padding: 2.5rem; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.8rem; background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 2rem; }
        .card { background: #1e2433; border: 1px solid #2d3748; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .card h2 { font-size: 1.1rem; color: #cbd5e1; margin-top: 0; margin-bottom: 1rem; border-bottom: 1px solid #2d3748; padding-bottom: 0.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 1rem; }
        th { background: #0f1117; color: #64748b; text-align: left; padding: 8px 12px; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid #2d3748; }
        td { padding: 10px 12px; border-bottom: 1px solid #1a2035; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 600; }
        .badge-success { background: #16a34a22; color: #4ade80; border: 1px solid #16a34a55; }
        .badge-warning { background: #d9770622; color: #fbbf24; border: 1px solid #d9770655; }
        .badge-info { background: #0284c722; color: #38bdf8; border: 1px solid #0284c755; }
        .green { color: #4ade80; font-weight: bold; }
        .yellow { color: #fbbf24; font-weight: bold; }
        .btn { display: inline-block; background: #4f46e5; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; transition: background 0.2s; border: none; cursor: pointer; }
        .btn:hover { background: #4338ca; }
    </style>
</head>
<body>
<div class="container">';

echo '<h1>⚡ Sincronización Retroactiva de Comisiones</h1>';
echo '<p class="subtitle">MAS QUE FIANZAS — Base de datos: <code>' . DB_NAME . '</code></p>';

// 1. Establecer comisiones al Administrador (ID: 1)
$sql_user = "UPDATE usuarios SET 
                porcentaje_comision = 15.00,
                comision_autos_ley = 15.00,
                comision_autos_full = 20.00,
                comision_fianzas = 25.00,
                comision_incendio = 15.00,
                comision_rc = 15.00,
                comision_otros = 15.00
             WHERE id = 1";

echo '<div class="card">';
echo '<h2>1. Configuración de Tasas del Administrador (ID 1)</h2>';
if ($db->query($sql_user)) {
    echo '<p style="color: #4ade80; font-weight: 600;">✅ Tasas de comisión del Administrador establecidas exitosamente:</p>';
    echo '<ul style="font-size: 0.85rem; margin-top: 0.5rem; line-height: 1.6;">
            <li>Seguro de Ley: <strong>15.00%</strong></li>
            <li>Autos Full / Vehículos: <strong>20.00%</strong></li>
            <li>Fianzas: <strong>25.00%</strong></li>
            <li>Otros Ramos: <strong>15.00%</strong></li>
          </ul>';
} else {
    echo '<p style="color: #f87171;">❌ Error al actualizar tasas del Administrador: ' . $db->error . '</p>';
}
echo '</div>';

// 2. Limpiar comisiones previas para evitar duplicados
$db->query("TRUNCATE TABLE comisiones_poliza");

// 3. Procesar pólizas
$mgr = new ComisionManager();
$polizas = $db->query("SELECT id, numero_poliza, tipo_seguro, prima_neta, emitida_por, fecha_emision FROM polizas ORDER BY id ASC");

echo '<div class="card">';
echo '<h2>2. Registro Retroactivo de Comisiones</h2>';
echo '<table>
        <thead>
            <tr>
                <th>Póliza</th>
                <th>Ramo</th>
                <th>Prima Neta</th>
                <th>Tasa Resuelta</th>
                <th>Comisión</th>
                <th>Estado Sincronizado</th>
            </tr>
        </thead>
        <tbody>';

$contador = 0;
$total_comision = 0.00;

if ($polizas && $polizas->num_rows > 0) {
    while ($p = $polizas->fetch_assoc()) {
        $pid = $p['id'];
        $vendedor = $p['emitida_por'];
        $prima = (float)$p['prima_neta'];
        
        // Calcular comisiones usando el motor oficial
        $creado = $mgr->calcularYRegistrarComisiones($pid, $vendedor, $prima);
        
        if ($creado) {
            // Sincronizar el estado del pago de la comisión con los pagos realizados a la póliza
            $res_pago = $db->query("SELECT estado_pago FROM pagos WHERE poliza_id = $pid ORDER BY id DESC LIMIT 1");
            $estado_comision = 'pendiente';
            
            if ($res_pago && $res_pago->num_rows > 0) {
                $pago_status = $res_pago->fetch_assoc()['estado_pago'];
                if ($pago_status === 'procesado') {
                    $estado_comision = 'pagado';
                }
            }
            
            // Actualizar en la base de datos
            $db->query("UPDATE comisiones_poliza SET estado_pago = '$estado_comision' WHERE poliza_id = $pid");
            
            // Obtener datos registrados para mostrar
            $res_com = $db->query("SELECT porcentaje_comision, monto_comision, estado_pago FROM comisiones_poliza WHERE poliza_id = $pid LIMIT 1");
            $com_reg = $res_com->fetch_assoc();
            
            $pct = (float)($com_reg['porcentaje_comision'] ?? 0);
            $monto = (float)($com_reg['monto_comision'] ?? 0);
            $est_p = $com_reg['estado_pago'] ?? 'pendiente';
            
            $badge = ($est_p === 'pagado') 
                ? '<span class="badge badge-success">✅ Cobrado</span>' 
                : '<span class="badge badge-warning">⏳ Tránsito</span>';
                
            $total_comision += $monto;
            $contador++;
            
            echo "<tr>
                    <td><strong>{$p['numero_poliza']}</strong></td>
                    <td><span style='font-size:11px;opacity:0.8;'>{$p['tipo_seguro']}</span></td>
                    <td class='green'>RD$ " . number_format($prima, 2) . "</td>
                    <td style='text-align:center;'>$pct %</td>
                    <td class='yellow'>RD$ " . number_format($monto, 2) . "</td>
                    <td>$badge</td>
                  </tr>";
        } else {
            echo "<tr>
                    <td><strong>{$p['numero_poliza']}</strong></td>
                    <td><span style='font-size:11px;opacity:0.8;'>{$p['tipo_seguro']}</span></td>
                    <td>RD$ " . number_format($prima, 2) . "</td>
                    <td colspan='3' style='color:#64748b;text-align:center;font-style:italic;'>Omitido (comisión calculada en 0.00%)</td>
                  </tr>";
        }
    }
} else {
    echo '<tr><td colspan="6" style="text-align:center;padding:20px;opacity:0.6;">No se encontraron pólizas en la base de datos.</td></tr>';
}

echo '</tbody></table></div>';

echo '<div class="card" style="border-color:#16a34a55;background:#14532d11;">';
echo '<h2>3. Consolidado Final de Sincronización</h2>';
echo '<p style="margin: 0; font-size: 0.95rem; line-height: 1.6;">
        • Pólizas con comisiones inyectadas: <strong class="green">' . $contador . ' pólizas</strong><br>
        • Total en comisiones calculadas y registradas: <strong class="yellow">RD$ ' . number_format($total_comision, 2) . '</strong><br>
        • Estado: <strong style="color: #4ade80;">Sincronización Completada en Tiempo Real</strong>
      </p>';
echo '<div style="margin-top: 20px;">
        <a href="frontend/modulos/comisiones.html" class="btn">🚀 Abrir Panel de Comisiones</a>
      </div>';
echo '</div>';

echo '</div>
</body>
</html>';
?>
