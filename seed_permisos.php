<?php
require_once 'backend/config.php';
$db = Database::getInstance()->getConnection();

echo "=== INICIANDO SIEMBRA DE PERMISOS DE PERFIL (NOFTRAB v4.0) ===\n";

// Limpiar permisos existentes para evitar duplicados
$db->query("DELETE FROM permisos_perfil");
echo "✓ Limpiando permisos anteriores.\n";

// Estructura de funciones por ID
// 1: DASH_COMPLETO (dashboard)
// 2: DASH_PARCIAL (dashboard)
// 3: CLI_CREAR (clientes)
// 4: CLI_EDITAR (clientes)
// 5: CLI_CONSULTAR (clientes)
// 6: CLI_ELIMINAR (clientes)
// 7: CLI_REPORTES (clientes)
// 8: POL_CREAR (polizas)
// 9: POL_EDITAR (polizas)
// 10: POL_CONSULTAR (polizas)
// 11: POL_ELIMINAR (polizas)
// 12: POL_TOTAL (polizas)
// 13: FI_CREAR (fianzas)
// 14: FI_EDITAR (fianzas)
// 15: FI_CONSULTAR (fianzas)
// 16: FI_TOTAL (fianzas)
// 17: PAG_REGISTRAR (pagos)
// 18: PAG_VALIDAR (pagos)
// 19: PAG_REPORTES (pagos)
// 20: PAG_CONSULTAR (pagos)
// 21: PAG_TOTAL (pagos)
// 22: COT_CREAR (cotizaciones)
// 23: COT_EDITAR (cotizaciones)
// 24: COT_CONSULTAR (cotizaciones)
// 25: COT_TOTAL (cotizaciones)
// 26: PRO_CREAR (productos)
// 27: PRO_EDITAR (productos)
// 28: PRO_CONSULTAR (productos)
// 29: PRO_TOTAL (productos)
// 30: CONF_VER (configuracion)
// 31: CONF_PARAMETROS_TECH (configuracion)
// 32: CONF_PARAMETROS_CONT (configuracion)
// 33: CONF_TOTAL (configuracion)
// 34: REP_TECNICO (reportes)
// 35: REP_FINANCIERO (reportes)
// 36: REP_COMERCIAL (reportes)
// 37: REP_CAJA (reportes)
// 38: REP_LIMITADO (reportes)
// 39: REP_TOTAL (reportes)
// 40: SIN_CREAR (siniestros)
// 41: SIN_SEGUIMIENTO (siniestros)
// 42: SIN_CONSULTAR (siniestros)
// 43: SIN_PROPIOS (siniestros)
// 44: SIN_TOTAL (siniestros)
// 45: USU_CREAR (usuarios)
// 46: USU_EDITAR (usuarios)
// 47: USU_BLOQUEAR (usuarios)
// 48: USU_ELIMINAR (usuarios)
// 49: USU_PASS_RESET (usuarios)
// 50: PER_GESTIONAR (usuarios)
// 51: PER_ASIGNAR (usuarios)
// 52: USU_AUDITORIA (usuarios)
// 53: USU_TOTAL (usuarios)

// Mapeo de modulo_id por funcion_id para automatizar
$modulo_mapping = [
    1 => 1, 2 => 1,
    3 => 2, 4 => 2, 5 => 2, 6 => 2, 7 => 2,
    8 => 3, 9 => 3, 10 => 3, 11 => 3, 12 => 3,
    13 => 4, 14 => 4, 15 => 4, 16 => 4,
    17 => 5, 18 => 5, 19 => 5, 20 => 5, 21 => 5,
    22 => 6, 23 => 6, 24 => 6, 25 => 6,
    26 => 7, 27 => 7, 28 => 7, 29 => 7,
    30 => 8, 31 => 8, 32 => 8, 33 => 8,
    34 => 9, 35 => 9, 36 => 9, 37 => 9, 38 => 9, 39 => 9,
    40 => 10, 41 => 10, 42 => 10, 43 => 10, 44 => 10,
    45 => 11, 46 => 11, 47 => 11, 48 => 11, 49 => 11, 50 => 11, 51 => 11, 52 => 11, 53 => 11
];

// 1. Insertar todos los permisos para el Administrador (ID 1)
$sql_insert = "INSERT INTO permisos_perfil (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, eliminar_datos, ver_reportes, exportar_datos, solo_propios, creado_por) VALUES (?, ?, ?, 1, 1, 1, 1, 1, 1, 1, 0, 1)";
$stmt = $db->prepare($sql_insert);

for ($f_id = 1; $f_id <= 53; $f_id++) {
    $m_id = $modulo_mapping[$f_id];
    $stmt->bind_param("iii", $perfil_id_val, $funcion_id_val, $modulo_id_val);
    $perfil_id_val = 1;
    $funcion_id_val = $f_id;
    $modulo_id_val = $m_id;
    $stmt->execute();
}
echo "✓ Permisos de Administrador (53/53) creados.\n";

// 2. Insertar permisos para el Socio Comercial PDV (ID 5)
// El PDV ve Dash Parcial, Clientes, y sus propias transacciones en Pólizas, Fianzas, Pagos, Cotizaciones y Siniestros.
$permisos_pdv = [
    // funcion_id => solo_propios
    2 => 0,  // DASH_PARCIAL
    3 => 0,  // CLI_CREAR
    4 => 0,  // CLI_EDITAR
    5 => 0,  // CLI_CONSULTAR
    8 => 1,  // POL_CREAR (propios)
    9 => 1,  // POL_EDITAR (propios)
    10 => 1, // POL_CONSULTAR (propios)
    13 => 1, // FI_CREAR (propios)
    14 => 1, // FI_EDITAR (propios)
    15 => 1, // FI_CONSULTAR (propios)
    17 => 1, // PAG_REGISTRAR (propios)
    20 => 1, // PAG_CONSULTAR (propios)
    22 => 1, // COT_CREAR (propios)
    23 => 1, // COT_EDITAR (propios)
    24 => 1, // COT_CONSULTAR (propios)
    28 => 0, // PRO_CONSULTAR
    38 => 0, // REP_LIMITADO
    42 => 1, // SIN_CONSULTAR
    43 => 1, // SIN_PROPIOS
];

$sql_custom = "INSERT INTO permisos_perfil (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, editar_datos, eliminar_datos, ver_reportes, exportar_datos, solo_propios, creado_por) VALUES (?, ?, ?, 1, 1, 1, 1, 0, 1, 1, ?, 1)";
$stmt_custom = $db->prepare($sql_custom);

foreach ($permisos_pdv as $f_id => $solo_prop) {
    $m_id = $modulo_mapping[$f_id];
    $stmt_custom->bind_param("iiii", $perfil_id_val, $funcion_id_val, $modulo_id_val, $solo_propios_val);
    $perfil_id_val = 5;
    $funcion_id_val = $f_id;
    $modulo_id_val = $m_id;
    $solo_propios_val = $solo_prop;
    $stmt_custom->execute();
}
echo "✓ Permisos de Socio Comercial PDV (19/19) creados con restricción solo_propios.\n";

// 3. Insertar permisos para Gerente Técnico (ID 2)
$permisos_gerente_tecnico = [
    1 => 0, 3 => 0, 4 => 0, 5 => 0,
    8 => 0, 9 => 0, 10 => 0, 12 => 0,
    13 => 0, 14 => 0, 15 => 0, 16 => 0,
    22 => 0, 23 => 0, 24 => 0, 25 => 0,
    26 => 0, 27 => 0, 28 => 0, 29 => 0,
    34 => 0, 38 => 0, 39 => 0,
    40 => 0, 41 => 0, 42 => 0, 44 => 0
];
foreach ($permisos_gerente_tecnico as $f_id => $solo_prop) {
    $m_id = $modulo_mapping[$f_id];
    $stmt_custom->bind_param("iiii", $perfil_id_val, $funcion_id_val, $modulo_id_val, $solo_propios_val);
    $perfil_id_val = 2;
    $funcion_id_val = $f_id;
    $modulo_id_val = $m_id;
    $solo_propios_val = $solo_prop;
    $stmt_custom->execute();
}
echo "✓ Permisos de Gerente Técnico creados.\n";

// 4. Insertar permisos para Gerente Contador (ID 3)
$permisos_gerente_contador = [
    1 => 0,
    17 => 0, 18 => 0, 19 => 0, 20 => 0, 21 => 0,
    32 => 0, 35 => 0, 37 => 0, 39 => 0
];
foreach ($permisos_gerente_contador as $f_id => $solo_prop) {
    $m_id = $modulo_mapping[$f_id];
    $stmt_custom->bind_param("iiii", $perfil_id_val, $funcion_id_val, $modulo_id_val, $solo_propios_val);
    $perfil_id_val = 3;
    $funcion_id_val = $f_id;
    $modulo_id_val = $m_id;
    $solo_propios_val = $solo_prop;
    $stmt_custom->execute();
}
echo "✓ Permisos de Gerente Contador creados.\n";

// 5. Insertar permisos para Supervisor Comercial (ID 9) y Supervisor Comercial de Zona (ID 10)
$permisos_supervisor = [
    1 => 0, 3 => 0, 4 => 0, 5 => 0,
    10 => 0, 15 => 0, 20 => 0, 24 => 0,
    28 => 0, 36 => 0, 42 => 0
];
foreach ([9, 10] as $perf_id) {
    foreach ($permisos_supervisor as $f_id => $solo_prop) {
        $m_id = $modulo_mapping[$f_id];
        $stmt_custom->bind_param("iiii", $perfil_id_val, $funcion_id_val, $modulo_id_val, $solo_propios_val);
        $perfil_id_val = $perf_id;
        $funcion_id_val = $f_id;
        $modulo_id_val = $m_id;
        $solo_propios_val = $solo_prop;
        $stmt_custom->execute();
    }
}
echo "✓ Permisos de Supervisores (ID 9, 10) creados.\n";

// 6. Insertar permisos para Agente Comercial (ID 11)
$permisos_agente = [
    2 => 0, 5 => 0,
    10 => 1, 15 => 1, 20 => 1,
    22 => 1, 23 => 1, 24 => 1
];
foreach ($permisos_agente as $f_id => $solo_prop) {
    $m_id = $modulo_mapping[$f_id];
    $stmt_custom->bind_param("iiii", $perfil_id_val, $funcion_id_val, $modulo_id_val, $solo_propios_val);
    $perfil_id_val = 11;
    $funcion_id_val = $f_id;
    $modulo_id_val = $m_id;
    $solo_propios_val = $solo_prop;
    $stmt_custom->execute();
}
echo "✓ Permisos de Agente Comercial creados con restricción solo_propios.\n";

$stmt->close();
$stmt_custom->close();

echo "=== SIEMBRA COMPLETADA EXITOSAMENTE ===\n";
?>
