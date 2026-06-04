<?php
/**
 * SEED: Módulo Centro Financiero e Integración de Permisos
 * ==========================================================
 * Registra formalmente al modulo 'centro_financiero' con ID 13 en la tabla `modulos`,
 * y migra las funciones CF_EDITAR_CUENTA y CF_GESTIONAR_NCF desde 'configuracion'
 * hacia este nuevo modulo.
 *
 * IDEMPOTENTE: Seguro de ejecutar múltiples veces.
 * URL: http://localhost/PLATAFORMA_INTEGRADA/seed_centro_financiero_modulo.php
 */

require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();
$db->set_charset('utf8mb4');

echo "=== SEED: Registro del Módulo Centro Financiero (ID 13) ===\n\n";

// ─── 1. REGISTRAR EL MÓDULO 13 ───
$modulo_id = 13;
$nombre_modulo = 'centro_financiero';
$descripcion = 'Centro Financiero y Contabilidad';
$icono = '🏦';
$nombre_ruta = '/modulos/centro_financiero.html';
$orden_menu = 13;
$estado = 'activo';

// Verificar si ya existe
$stmt = $db->prepare("SELECT id FROM modulos WHERE id = ?");
$stmt->bind_param('i', $modulo_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if ($res->num_rows === 0) {
    // Intentar insertar con ID estático 13
    $stmt_ins = $db->prepare("INSERT INTO modulos (id, nombre_modulo, descripcion, icono, nombre_ruta, orden_menu, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_ins->bind_param('issssis', $modulo_id, $nombre_modulo, $descripcion, $icono, $nombre_ruta, $orden_menu, $estado);
    if ($stmt_ins->execute()) {
        echo "✓ Módulo '$nombre_modulo' registrado con ID $modulo_id.\n";
    } else {
        echo "❌ Error al registrar el módulo: " . $stmt_ins->error . "\n";
        exit(1);
    }
    $stmt_ins->close();
} else {
    echo "⏭️ El módulo '$nombre_modulo' (ID $modulo_id) ya está registrado en la base de datos. Actualizando...\n";
    $stmt_upd = $db->prepare("UPDATE modulos SET nombre_modulo = ?, descripcion = ?, icono = ?, nombre_ruta = ?, orden_menu = ?, estado = ? WHERE id = ?");
    $stmt_upd->bind_param('ssssisi', $nombre_modulo, $descripcion, $icono, $nombre_ruta, $orden_menu, $estado, $modulo_id);
    $stmt_upd->execute();
    $stmt_upd->close();
}

// ─── 2. REUBICAR LAS FUNCIONES CONTABLES ───
$funciones_codigos = ['CF_EDITAR_CUENTA', 'CF_GESTIONAR_NCF'];

$stmt_mig = $db->prepare("UPDATE funciones_modulo SET modulo_id = ? WHERE codigo_funcion = ?");
foreach ($funciones_codigos as $codigo) {
    $stmt_mig->bind_param('is', $modulo_id, $codigo);
    $stmt_mig->execute();
    if ($db->affected_rows > 0) {
        echo "✓ Función '$codigo' migrada al módulo $modulo_id (Centro Financiero).\n";
    } else {
        echo "⏭️ Función '$codigo' ya pertenece al módulo $modulo_id o no fue encontrada.\n";
    }
}
$stmt_mig->close();

// ─── 3. SIEMBRA DE PARÁMETROS GENERALES: EMPRESA_CUENTAS_TRANSFERENCIA ───
$clave_config = 'EMPRESA_CUENTAS_TRANSFERENCIA';
$bancos_json = json_encode([
    [
        'banco' => 'Banco Popular Dominicano',
        'cuenta' => '768594021',
        'tipo' => 'Corriente',
        'rnc' => '133-53573-4',
        'beneficiario' => 'MAS QUE FIANZAS +QF, SRL'
    ],
    [
        'banco' => 'Banco BHD',
        'cuenta' => '092837461',
        'tipo' => 'Ahorros',
        'rnc' => '133-53573-4',
        'beneficiario' => 'MAS QUE FIANZAS +QF, SRL'
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$tipo_valor = 'json';
$desc_config = 'Cuentas bancarias autorizadas para recibir transferencias ACH/LBTR y depósitos';

// Verificar si la clave ya existe
$stmt_cfg = $db->prepare("SELECT id FROM configuracion_sistema WHERE clave_config = ?");
$stmt_cfg->bind_param('s', $clave_config);
$stmt_cfg->execute();
$res_cfg = $stmt_cfg->get_result();
$stmt_cfg->close();

if ($res_cfg->num_rows === 0) {
    $stmt_ins_cfg = $db->prepare("INSERT INTO configuracion_sistema (clave_config, valor_config, tipo_valor, descripcion, modificable) VALUES (?, ?, ?, ?, 1)");
    $stmt_ins_cfg->bind_param('ssss', $clave_config, $bancos_json, $tipo_valor, $desc_config);
    if ($stmt_ins_cfg->execute()) {
        echo "✓ Parámetro '$clave_config' registrado con éxito.\n";
    } else {
        echo "❌ Error al registrar el parámetro: " . $stmt_ins_cfg->error . "\n";
    }
    $stmt_ins_cfg->close();
} else {
    echo "⏭️ Parámetro '$clave_config' ya existe en configuracion_sistema.\n";
}

echo "\n=== SEED COMPLETADO CON ÉXITO ===\n";
?>
