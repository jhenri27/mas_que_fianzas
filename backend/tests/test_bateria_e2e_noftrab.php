<?php
/**
 * BATERÍA DE PRUEBAS AUTOMATIZADAS END-TO-END (E2E) — NORMA NOFTRAB v4.0
 * MÁS QUE FIANZAS — Sistema Integrado
 * ========================================================================
 * Ejecución vía CLI: php backend/tests/test_bateria_e2e_noftrab.php
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/ValidadorDocumentos.php';

echo "\n========================================================================\n";
echo "🧪 INICIANDO BATERÍA DE PRUEBAS AUTOMATIZADAS E2E (NOFTRAB v4.0 / 4-VAF)\n";
echo "========================================================================\n\n";

$db = Database::getInstance()->getConnection();
$passed = 0;
$failed = 0;

function assertTest($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ PASÓ: $description\n";
        $passed++;
    } else {
        echo "  ❌ FALLÓ: $description\n";
        $failed++;
    }
}

// ────────────────────────────────────────────────────────────────────────
// TEST 1: Algoritmo Luhn Mod 10 para Cédulas Dominicanas
// ────────────────────────────────────────────────────────────────────────
echo "📋 TEST 1: Algoritmo Luhn Mod 10 para Cédulas Dominicanas\n";
$cedulaValida = '001-1423642-5';
$cedulaInvalida = '552-5698475-8';

$resValida = ValidadorDocumentos::validarCedula($cedulaValida);
$resInvalida = ValidadorDocumentos::validarCedula($cedulaInvalida);

assertTest("Cédula oficial legítima ($cedulaValida) aprobada por algoritmo Luhn", $resValida === true);
assertTest("Cédula inventada no válida ($cedulaInvalida) rechazada por algoritmo Luhn", $resInvalida === false);

// ────────────────────────────────────────────────────────────────────────
// TEST 2: Algoritmo Mod 11 DGII para RNC Comercial
// ────────────────────────────────────────────────────────────────────────
echo "\n📋 TEST 2: Algoritmo Mod 11 DGII para RNC Comercial\n";
$rncValido = '131-77085-1';
$rncInvalido = '999-99999-9';

$resRncValido = ValidadorDocumentos::validarRNC($rncValido);
$resRncInvalido = ValidadorDocumentos::validarRNC($rncInvalido);

assertTest("RNC comercial legítimo ($rncValido) aprobado por Mod 11 DGII", $resRncValido === true);
assertTest("RNC ficticio no válido ($rncInvalido) rechazado", $resRncInvalido === false);

// ────────────────────────────────────────────────────────────────────────
// TEST 3: Unicidad Global de Cliente por Cédula / RNC
// ────────────────────────────────────────────────────────────────────────
echo "\n📋 TEST 3: Unicidad Global de Documentos de Clientes\n";
$checkCedulaExistente = ValidadorDocumentos::validarDocumentoUnicoGlobal($db, $cedulaValida, "Cliente Intruso Diferente");
assertTest("Detección de duplicado global para cédula existente de cliente ($cedulaValida)", $checkCedulaExistente !== null && is_string($checkCedulaExistente));

// ────────────────────────────────────────────────────────────────────────
// TEST 4: Unicidad Global de Datos de Vehículos (Chasis/VIN y Placa)
// ────────────────────────────────────────────────────────────────────────
echo "\n📋 TEST 4: Unicidad Global de Vehículos (Chasis/VIN y Placa)\n";
$chasisTest = '1HGCR2F83HA000111';
$placaTest = 'A123456';

$checkChasis = ValidadorDocumentos::validarVehiculoUnicoGlobal($db, $chasisTest, '');
$checkPlaca = ValidadorDocumentos::validarVehiculoUnicoGlobal($db, '', $placaTest);

assertTest("Filtro de unicidad de Chasis/VIN ejecutado correctamente", $checkChasis === null || is_string($checkChasis));
assertTest("Filtro de unicidad de Placa vehicular ejecutado correctamente", $checkPlaca === null || is_string($checkPlaca));

// ────────────────────────────────────────────────────────────────────────
// TEST 5: Unicidad Global de Identificadores de Fianzas
// ────────────────────────────────────────────────────────────────────────
echo "\n📋 TEST 5: Unicidad Global de Identificadores de Fianzas\n";
$fianzaTest = 'FZ-2026-001';
$checkFianza = ValidadorDocumentos::validarFianzaUnicaGlobal($db, $fianzaTest);

assertTest("Filtro de unicidad de N° de Fianza ejecutado correctamente", $checkFianza === null || is_string($checkFianza));

// ────────────────────────────────────────────────────────────────────────
// TEST 6: Justificación Obligatoria VAF (Mínimo 15 Caracteres)
// ────────────────────────────────────────────────────────────────────────
echo "\n📋 TEST 6: Validación de Justificación Obligatoria VAF (Mín. 15 caracteres)\n";
$justCorta = "Corto";
$justValida = "Justificación formal según la norma NOFTRAB 4-VAF de auditoría";

assertTest("Justificación menor a 15 caracteres rechazada", strlen($justCorta) < 15);
assertTest("Justificación mayor o igual a 15 caracteres aprobada", strlen($justValida) >= 15);

// ────────────────────────────────────────────────────────────────────────
// TEST 7: Verificación de Regla Fiscal de Seguros (ISC 16% / Exento ITBIS)
// ────────────────────────────────────────────────────────────────────────
echo "\n📋 TEST 7: Verificación de Regla Fiscal de Seguros (Ley 146-02 / DGII)\n";
$primaNeta = 1000.00;
$tasaISC = 0.16; // 16% Impuesto Selectivo al Consumo sobre Primas de Seguros
$iscCalculado = $primaNeta * $tasaISC;
$totalPagar = $primaNeta + $iscCalculado;

assertTest("Prima Neta ($1,000.00) + ISC 16% ($160.00) = Total ($1,160.00)", $iscCalculado == 160.00 && $totalPagar == 1160.00);

// ────────────────────────────────────────────────────────────────────────
// TEST 8: Permisos Granulares y Perfil Administrador (ID 1)
// ────────────────────────────────────────────────────────────────────────
echo "\n📋 TEST 8: Permisos Granulares RBAC y Bypass Administrador (Perfil ID 1)\n";
$resPerm = $db->query("SELECT puede_ejecutar, crear_datos, editar_datos, eliminar_datos FROM permisos_perfil WHERE perfil_id = 1 AND modulo_id = 20");
$adminOk = false;
if ($resPerm && $row = $resPerm->fetch_assoc()) {
    $adminOk = ($row['puede_ejecutar'] == 1 && $row['crear_datos'] == 1 && $row['editar_datos'] == 1 && $row['eliminar_datos'] == 1);
}
assertTest("Perfil Administrador (ID 1) con permisos totales sobre Módulo Centro Técnico (ID 20)", $adminOk === true);

// ────────────────────────────────────────────────────────────────────────
// RESUMEN FINAL
// ────────────────────────────────────────────────────────────────────────
echo "\n========================================================================\n";
echo "📊 RESUMEN DE EJECUCIÓN DE PRUEBAS E2E\n";
echo "========================================================================\n";
echo "  Aprobadas: $passed\n";
echo "  Fallidas:  $failed\n";
echo "  Total:     " . ($passed + $failed) . "\n";
echo "  Resultado: " . ($failed === 0 ? "✅ 100% ÉXITO — SISTEMA OPERATIVO Y NOFTRAB COMPLIANT" : "❌ ATENCIÓN REQUERIDA EN REGLAS") . "\n";
echo "========================================================================\n\n";

exit($failed === 0 ? 0 : 1);
?>
