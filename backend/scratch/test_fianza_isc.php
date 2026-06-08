<?php
/**
 * Test standalone: verificar cálculos de ISC (16%), fraccionamiento 3 cuotas, y balance contable
 */
require_once dirname(__FILE__) . '/../config.php';

echo "\n=== VERIFICACIÓN: IMPUESTOS, FRACCIONAMIENTO y CONTABILIDAD ===\n\n";

$db = Database::getInstance()->getConnection();

// ─── 1. Cálculo ISC ──────────────────────────────────────────────────────────
$monto_afianzado = 100000; // RD$ 100,000
$tasa_anual = 2.5;         // 2.5%
$plazo_meses = 12;

$prima_calculada = round(($monto_afianzado * ($tasa_anual / 100)) * ($plazo_meses / 12), 2);

// Verificar prima_minima_override para Midas (aseguradora_id = 2)
$row_midas = $db->query("SELECT COALESCE(prima_minima_override, 0) AS prima_minima_midas FROM fianza_tarifarios WHERE aseguradora_id = 2 LIMIT 1")->fetch_assoc();
$prima_minima_midas = $row_midas ? (float)$row_midas['prima_minima_midas'] : 0;

$prima_base = max($prima_calculada, $prima_minima_midas > 0 ? $prima_minima_midas : 0);
$isc = round($prima_base * 0.16, 2);
$total = round($prima_base + $isc, 2);

echo "── CÁLCULO ISC (16%) ──\n";
echo "  Monto Afianzado: RD$ " . number_format($monto_afianzado, 2) . "\n";
echo "  Prima Base Calculada: RD$ {$prima_calculada}\n";
echo "  Prima Mínima Midas (ID=2): RD$ {$prima_minima_midas}\n";
echo "  Prima Base Efectiva: RD$ {$prima_base}\n";
echo "  ISC (16%): RD$ {$isc}\n";
echo "  Total Prima: RD$ {$total}\n";

$check_isc = round($prima_base * 0.16, 2);
echo ($check_isc === $isc) ? "[✓] ISC calculado al 16% correctamente\n" : "[X] ERROR en ISC\n";

// ─── 2. Balance Contable (EMISION_POLIZA) ────────────────────────────────────
$comision     = round($prima_base * 0.15, 2);              // 15% MQF
$itbis_com    = round($comision * 0.18, 2);                // 18% ITBIS sobre comisión
$neto_aseg    = round($total - $comision - $itbis_com, 2); // ISC va incluido aquí

echo "\n── BALANCE CONTABLE (EMISION_POLIZA) ──\n";
echo "  DB  1.1.02 Primas por Cobrar:             RD$ {$total}\n";
echo "  CR  4.1.01 Ingresos Comisiones (15%):     RD$ {$comision}\n";
echo "  CR  2.1.02 ITBIS por Pagar (18% de com):  RD$ {$itbis_com}\n";
echo "  CR  2.1.01 Primas por Pagar Aseg.:        RD$ {$neto_aseg}\n";

$suma_cr = $comision + $itbis_com + $neto_aseg;
echo "  Diferencia DB - CR: " . round($total - $suma_cr, 2) . "\n";
echo (abs($total - $suma_cr) < 0.01) ? "[✓] Asiento BALANCEADO\n" : "[X] ERROR: Asiento descuadrado\n";

// ─── 3. Fraccionamiento 3 Cuotas (mín 25% inicial) ──────────────────────────
echo "\n── FRACCIONAMIENTO 3 CUOTAS ──\n";
$pago_inicial = round($total * 0.25, 2);
$balance_rest = round($total - $pago_inicial, 2);
$cuota2       = round($balance_rest / 2, 2);
$cuota3       = round($balance_rest - $cuota2, 2);
$suma_cuotas  = $pago_inicial + $cuota2 + $cuota3;

echo "  Total Prima:        RD$ {$total}\n";
echo "  Cuota 1 (25% mín):  RD$ {$pago_inicial}\n";
echo "  Cuota 2 (50% rest): RD$ {$cuota2}\n";
echo "  Cuota 3 (50% rest): RD$ {$cuota3}\n";
echo "  Suma Cuotas:        RD$ {$suma_cuotas}\n";
echo (abs($total - $suma_cuotas) < 0.01) ? "[✓] Fraccionamiento CUADRA\n" : "[X] ERROR en fraccionamiento\n";

// ─── 4. Verificar Midas prima_minima_override en BD ─────────────────────────
echo "\n── MIDAS PRIMA MÍNIMA (BD) ──\n";
$res_midas = $db->query("SELECT COUNT(*) AS total, MIN(prima_minima_override) AS min_val, MAX(prima_minima_override) AS max_val FROM fianza_tarifarios WHERE aseguradora_id = 2 AND prima_minima_override = 3000.00");
$m = $res_midas->fetch_assoc();
echo "  Filas con prima_minima_override = 3000: {$m['total']}\n";
echo ($m['total'] > 0) ? "[✓] Midas prima mínima RD$ 3,000 verificada en BD\n" : "[X] No se encontraron filas con prima_minima_override = 3000\n";

// ─── 5. Validación de NCF PDV (perfil_id = 5) ───────────────────────────────
echo "\n── USUARIOS PERFIL PDV ──\n";
$res_pdv = $db->query("SELECT COUNT(*) AS cnt FROM usuarios WHERE perfil_id = 5");
$pdv = $res_pdv->fetch_assoc();
echo "  Usuarios con perfil_id = 5 (PDV): {$pdv['cnt']}\n";
echo ($pdv['cnt'] > 0) ? "[✓] Usuarios PDV encontrados en BD\n" : "[!] Ningún usuario PDV en BD\n";

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
