<?php
/**
 * SEED: Permisos Centro Financiero — MAS QUE FIANZAS
 * ====================================================
 * Registra funciones granulares para el Centro Financiero:
 *   - CF_EDITAR_CUENTA: Editar cuentas del catálogo contable
 *   - CF_GESTIONAR_NCF: Visualizar y ajustar secuencias NCF (DGII)
 *
 * IDEMPOTENTE: usa INSERT IGNORE / ON DUPLICATE KEY UPDATE.
 * URL: http://localhost/PLATAFORMA_INTEGRADA/seed_permisos_centro_financiero.php
 */

require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();
$db->set_charset('utf8mb4');

echo "=== SEED: Permisos Centro Financiero (NOFTRAB v4.0) ===\n\n";

// ─── 1. VERIFICAR/OBTENER MÓDULO configuracion (ID 8) ───
$modulo_id = 8; // configuracion
$stmt = $db->prepare("SELECT id FROM modulos WHERE id = ?");
$stmt->bind_param('i', $modulo_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if ($res->num_rows === 0) {
    echo "❌ Error: El módulo configuracion (ID 8) no existe en la tabla modulos.\n";
    exit(1);
}
echo "✓ Módulo configuracion (ID $modulo_id) verificado.\n";

// ─── 2. INSERTAR FUNCIONES ───
$funciones = [
    [
        'codigo' => 'CF_EDITAR_CUENTA',
        'nombre' => 'Editar Cuentas del Catálogo Contable',
        'descripcion' => 'Permite modificar nombre, tipo, naturaleza, estado y código (con restricciones) de cuentas en el catálogo contable del Centro Financiero',
        'tipo_permiso' => 'editar'
    ],
    [
        'codigo' => 'CF_GESTIONAR_NCF',
        'nombre' => 'Gestionar Secuencias NCF (DGII)',
        'descripcion' => 'Permite visualizar el estado de las secuencias NCF y ajustar los contadores con justificación obligatoria (NOFTRAB)',
        'tipo_permiso' => 'completo'
    ]
];

$sql_insert_fn = "INSERT INTO funciones_modulo (modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso, estado) 
                  VALUES (?, ?, ?, ?, ?, 'activo')
                  ON DUPLICATE KEY UPDATE nombre_funcion = VALUES(nombre_funcion), descripcion = VALUES(descripcion)";

$stmt_fn = $db->prepare($sql_insert_fn);

$funcion_ids = [];
foreach ($funciones as $fn) {
    $stmt_fn->bind_param('issss', $modulo_id, $fn['nombre'], $fn['codigo'], $fn['descripcion'], $fn['tipo_permiso']);
    $stmt_fn->execute();
    
    $new_id = $db->insert_id;
    if ($new_id) {
        $funcion_ids[$fn['codigo']] = $new_id;
        echo "✓ Función '{$fn['codigo']}' creada con ID $new_id.\n";
    } else {
        // Ya existe, obtener su ID
        $stmt_get = $db->prepare("SELECT id FROM funciones_modulo WHERE modulo_id = ? AND codigo_funcion = ?");
        $stmt_get->bind_param('is', $modulo_id, $fn['codigo']);
        $stmt_get->execute();
        $row = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();
        $funcion_ids[$fn['codigo']] = $row['id'];
        echo "⏭️ Función '{$fn['codigo']}' ya existía (ID {$row['id']}). Actualizada.\n";
    }
}
$stmt_fn->close();

// ─── 3. ASIGNAR PERMISOS AL ADMINISTRADOR (perfil_id = 1) ───
$perfil_admin = 1;
$creado_por = 1;

$sql_perm = "INSERT INTO permisos_perfil 
             (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, eliminar_datos, ver_reportes, exportar_datos, importar_datos, imprimir_datos, solo_propios, creado_por) 
             VALUES (?, ?, ?, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0, ?)
             ON DUPLICATE KEY UPDATE 
                puede_ejecutar = 1, ver_datos = 1, editar_datos = 1, ver_reportes = 1";

$stmt_perm = $db->prepare($sql_perm);

foreach ($funcion_ids as $codigo => $fid) {
    $stmt_perm->bind_param('iiii', $perfil_admin, $fid, $modulo_id, $creado_por);
    $stmt_perm->execute();
    
    if ($db->affected_rows >= 1) {
        echo "✓ Permiso '{$codigo}' asignado al perfil Administrador (ID 1).\n";
    } else {
        echo "⏭️ Permiso '{$codigo}' ya estaba asignado al Administrador.\n";
    }
}
$stmt_perm->close();

// ─── 4. VERIFICACIÓN ───
echo "\n--- Verificación Final ---\n";
$res = $db->query("SELECT fm.id, fm.codigo_funcion, fm.nombre_funcion, pp.puede_ejecutar, pp.editar_datos
                    FROM funciones_modulo fm
                    LEFT JOIN permisos_perfil pp ON fm.id = pp.funcion_id AND pp.perfil_id = 1
                    WHERE fm.codigo_funcion IN ('CF_EDITAR_CUENTA', 'CF_GESTIONAR_NCF')");
while ($row = $res->fetch_assoc()) {
    $estado = ($row['puede_ejecutar'] == 1) ? '✅ Activo' : '❌ Inactivo';
    echo "  {$row['codigo_funcion']} (ID {$row['id']}): {$row['nombre_funcion']} → Admin: $estado\n";
}

echo "\n=== SEED COMPLETADO ===\n";
?>
