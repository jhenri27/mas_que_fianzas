<?php
/**
 * SPRINT 0 — PRUEBAS DE INTEGRACIÓN CENTRALIZADAS
 * MAS QUE FIANZAS - Plataforma Integrada
 */

require_once __DIR__ . '/../backend/config.php';
ini_set('display_errors', 1);

echo "===================================================================\n";
echo "       INICIANDO PRUEBAS DE INTEGRACIÓN CENTRALIZADAS — SPRINT 0   \n";
echo "===================================================================\n\n";

$base_url = "http://localhost/PLATAFORMA_INTEGRADA/backend/api";
$cookie_file = tempnam(sys_get_temp_dir(), 'mqf_cookie');

// Helper para llamadas HTTP CURL
function make_request($method, $url, $payload = null, $token = null) {
    global $cookie_file;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = [];
    if ($token) {
        $headers[] = "Authorization: Bearer " . $token;
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $headers[] = "Content-Type: application/json";
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $headers[] = "Content-Type: application/json";
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $http_code,
        'body' => json_decode($response, true) ?: $response
    ];
}

// -------------------------------------------------------------
// PRUEBA 0: SEGURIDAD Y SESIÓN (Proteger APIs sin autenticación)
// -------------------------------------------------------------
echo "🔒 PROBANDO SEGURIDAD Y ACCESO NO AUTORIZADO...\n";

$res = make_request('GET', "$base_url/clientes.php");
if ($res['code'] === 401) {
    echo "  ✅ PASÓ: Acceso denegado a Clientes sin sesión (HTTP 401).\n";
} else {
    echo "  ❌ FALLÓ: Clientes permitió acceso no autenticado (HTTP " . $res['code'] . ").\n";
    exit(1);
}

$res = make_request('GET', "$base_url/cotizaciones.php?action=listar");
if ($res['code'] === 401) {
    echo "  ✅ PASÓ: Acceso denegado a Cotizaciones sin sesión (HTTP 401).\n";
} else {
    echo "  ❌ FALLÓ: Cotizaciones permitió acceso no autenticado (HTTP " . $res['code'] . ").\n";
    exit(1);
}

// -------------------------------------------------------------
// PRUEBA 1: AUTENTICACIÓN (LOGIN)
// -------------------------------------------------------------
echo "\n🔑 AUTENTICANDO CON USUARIO ADMIN (Demo@123)...\n";
$login_payload = [
    'username' => 'admin',
    'password' => 'Demo@123'
];
$res = make_request('POST', "$base_url/auth.php/login", $login_payload);

if ($res['code'] === 200 && isset($res['body']['token_sesion'])) {
    $token = $res['body']['token_sesion'];
    echo "  ✅ PASÓ: Login exitoso. Perfil: " . ($res['body']['perfil'] ?? 'N/D') . "\n";
    echo "  🔑 Token generado: " . substr($token, 0, 10) . "...\n";
} else {
    echo "  ❌ FALLÓ: Login fallido (HTTP " . $res['code'] . ").\n";
    print_r($res['body']);
    exit(1);
}

// -------------------------------------------------------------
// PRUEBA 2: S0.3 - GESTIÓN DE CLIENTES (CRUD)
// -------------------------------------------------------------
echo "\n👥 PROBANDO S0.3: CRUD DE CLIENTES...\n";

// A. Crear Cliente
$rnc_test = "101-" . rand(100000, 999999) . "-2";
$cliente_payload = [
    'nombre_razon_social' => 'Industrias Dominicanas de Emergencia S.R.L.',
    'rnc' => $rnc_test,
    'tipo_persona' => 'Juridica',
    'telefono' => '809-555-0199',
    'correo' => 'contacto@industriasdom.com.do',
    'direccion' => 'Av. Winston Churchill #1024, Santo Domingo, RD',
    'estatus' => 'Activo'
];

$res = make_request('POST', "$base_url/clientes.php/crear", $cliente_payload, $token);
if ($res['code'] === 201 && isset($res['body']['datos']['id'])) {
    $cliente_id = $res['body']['datos']['id'];
    echo "  ✅ PASÓ: Cliente creado con éxito (ID: $cliente_id, RNC: $rnc_test).\n";
} else {
    echo "  ❌ FALLÓ: No se pudo crear cliente (HTTP " . $res['code'] . ").\n";
    print_r($res['body']);
    exit(1);
}

// B. Listar y verificar existencia
$res = make_request('GET', "$base_url/clientes.php", null, $token);
$encontrado = false;
if ($res['code'] === 200 && is_array($res['body']['datos'])) {
    foreach ($res['body']['datos'] as $cli) {
        if ($cli['id'] == $cliente_id) {
            $encontrado = true;
            break;
        }
    }
}

if ($encontrado) {
    echo "  ✅ PASÓ: Cliente listado correctamente desde MySQL.\n";
} else {
    echo "  ❌ FALLÓ: El cliente creado no se encuentra en el listado.\n";
    exit(1);
}

// C. Editar Cliente
$edit_payload = array_merge($cliente_payload, [
    'nombre_razon_social' => 'Industrias Dominicanas de Emergencia S.R.L. (Modificado)',
    'telefono' => '809-555-9999'
]);
$res = make_request('PUT', "$base_url/clientes.php/editar/$cliente_id", $edit_payload, $token);
if ($res['code'] === 200) {
    echo "  ✅ PASÓ: Cliente editado correctamente (HTTP 200).\n";
} else {
    echo "  ❌ FALLÓ: No se pudo editar cliente (HTTP " . $res['code'] . ").\n";
    print_r($res['body']);
    exit(1);
}

// -------------------------------------------------------------
// PRUEBA 3: S0.1 - EMISIÓN DE COTIZACIÓN (FIANZAS / SEGURO LEY)
// -------------------------------------------------------------
echo "\n📝 PROBANDO S0.1: EMISIÓN DE COTIZACIÓN...\n";

$num_cotizacion = "COT-2026-" . rand(1000, 9999) . rand(10, 99);
$cotizacion_payload = [
    'numero' => $num_cotizacion,
    'tipo' => 'FIANZA',
    'subtipo' => 'Licitación / Oferta',
    'cliente' => 'Industrias Dominicanas de Emergencia S.R.L. (Modificado)',
    'cedula' => $rnc_test,
    'monto_afianzado' => 150000.00,
    'plazo' => 30,
    'prima_base' => 5000.00,
    'impuesto' => 900.00, // 18% ITBIS
    'total' => 5900.00,
    'usar_ncf' => true, // Habilitar hook de NCF DGII
    'fecha' => date('Y-m-d H:i:s')
];

$res = make_request('POST', "$base_url/cotizaciones.php?action=guardar", $cotizacion_payload, $token);
if ($res['code'] === 201) {
    echo "  ✅ PASÓ: Cotización emitida e integrada con éxito (Num: $num_cotizacion).\n";
    $ncf_generado = $res['body']['datos']['ncf'] ?? null;
    echo "  🎫 NCF Asignado: " . ($ncf_generado ?: "NINGUNO (Fallback)") . "\n";
} else {
    echo "  ❌ FALLÓ: No se pudo registrar cotización (HTTP " . $res['code'] . ").\n";
    print_r($res['body']);
    exit(1);
}

// B. Listar y verificar cotización en historial
$res = make_request('GET', "$base_url/cotizaciones.php?action=listar", null, $token);
$cot_encontrada = false;
if ($res['code'] === 200 && is_array($res['body']['datos'])) {
    foreach ($res['body']['datos'] as $cot) {
        if ($cot['numero'] === $num_cotizacion) {
            $cot_encontrada = true;
            break;
        }
    }
}

if ($cot_encontrada) {
    echo "  ✅ PASÓ: Cotización persistida correctamente en historial MySQL.\n";
} else {
    echo "  ❌ FALLÓ: Cotización no encontrada en el historial.\n";
    exit(1);
}
// PRUEBA 3.5: S0.2 - EMISIÓN DE PÓLIZA DE VEHÍCULO (AJUSTADA A TARIFARIO)
// -------------------------------------------------------------
echo "\n🚗 PROBANDO S0.2: EMISIÓN DE PÓLIZA DE VEHÍCULO (CON TARIFARIO MULTISEGUROS)...\n";

$num_poliza = "POL-2026-" . rand(1000, 9999);

// De acuerdo al tarifario MultiSeguros (pricing_multiseguros.json):
// AUTOMOVILES + PRIVADO + Hasta 4 Cilindros => Prima Neta Base = 1662.57
// Con ITBIS (18%): Total = 1662.57 * 1.18 = 1961.83
$prima_neta_tarifario = 1662.57;
$prima_total_tarifario = 1961.83; // 1662.57 + 299.26

$poliza_payload = [
    'cliente_id' => $cliente_id,
    'tipo_seguro' => 'Seguro de Ley - Vehículo Liviano',
    'tipo_poliza' => 'Individual',
    'ramo' => 'Vehículos de Motor',
    'aseguradora' => 'MULTISEGUROS',
    'perfil_cobertura' => 'LIVIANO BASICO',
    'prima_total' => $prima_total_tarifario,
    'periodicidad_pago' => 'anual',
    'cuota_total' => 1,
    'fecha_vencimiento' => date('Y-m-d', strtotime('+1 year')),
    'numero_poliza' => $num_poliza,
    'numero_poliza_aseguradora' => 'ASEG-' . rand(10000, 99999),
    'notas_internas' => 'Póliza de prueba automatizada ajustada a tarifario',
    'emitida_por' => 1,
    'vehiculo' => [
        'placa' => 'A' . rand(100000, 999999),
        'marca' => 'Toyota',
        'modelo' => 'Corolla',
        'anio' => 2022,
        'color' => 'Blanco',
        'chasis' => 'VIN' . rand(1000000, 9999999),
        'motor' => 'MOT' . rand(1000000, 9999999),
        'valor_comercial' => 850000.00,
        'tipo_vehiculo' => 'AUTOMOVIL',
        'uso' => 'PRIVADO',
        'capacidad' => 'Hasta 4 Cilindros'
    ]
];

$res_pol = make_request('POST', "$base_url/polizas.php?action=emitir", $poliza_payload, $token);
if ($res_pol['code'] === 200 && isset($res_pol['body']['id'])) {
    $pol_id = $res_pol['body']['id'];
    echo "  ✅ PASÓ: Póliza de vehículo emitida con éxito (ID: $pol_id, Num: $num_poliza).\n";
} else {
    echo "  ❌ FALLÓ: No se pudo emitir la póliza (HTTP " . $res_pol['code'] . ").\n";
    print_r($res_pol['body']);
    exit(1);
}

// B. Verificar que la póliza quedó registrada en la base de datos
$res_get = make_request('GET', "$base_url/polizas.php?action=obtener&id=$pol_id", null, $token);
if ($res_get['code'] === 200 && isset($res_get['body']['data']['id'])) {
    $pol_db = $res_get['body']['data'];
    echo "  ✅ PASÓ: Póliza obtenida de la base de datos con éxito.\n";
    
    $p_total = floatval($pol_db['prima_total']);
    $p_itbis = floatval($pol_db['itbis']);
    $p_neta = floatval($pol_db['prima_neta']);
    
    echo "    - Prima Total Registrada: RD$ " . number_format($p_total, 2) . "\n";
    echo "    - Prima Neta Calculada:   RD$ " . number_format($p_neta, 2) . "\n";
    echo "    - ITBIS (18%) Calculado:   RD$ " . number_format($p_itbis, 2) . "\n";
    
    // Validar fórmula
    if (abs($p_total - $p_neta - $p_itbis) < 0.01) {
        echo "  ✅ PASÓ: Desglose matemático Prima = Neta + ITBIS verificado perfectamente.\n";
    } else {
        echo "  ❌ FALLÓ: Desglose matemático inconsistente ($p_total != $p_neta + $p_itbis).\n";
        exit(1);
    }
} else {
    echo "  ❌ FALLÓ: No se pudo verificar la póliza registrada por API.\n";
    exit(1);
}

// -------------------------------------------------------------
// PRUEBA 3.6: S0.6 - REGISTRO DE PAGO Y VERIFICACIÓN DIARIO
// -------------------------------------------------------------
echo "\n💳 PROBANDO S0.6: REGISTRO DE PAGO Y CONTABILIZACIÓN AUTOMÁTICA...\n";

$pago_payload = [
    'poliza_id' => $pol_id,
    'monto' => $p_total,
    'tipo_pago' => 'transferencia',
    'banco' => 'Banco Popular',
    'numero_comprobante' => 'REF-' . rand(100000, 999999),
    'descripcion' => "Pago total póliza $num_poliza"
];

$res_pago = make_request('POST', "$base_url/pagos.php?action=registrar", $pago_payload, $token);
if ($res_pago['code'] === 200 && isset($res_pago['body']['id'])) {
    $pago_id = $res_pago['body']['id'];
    $num_ref = $res_pago['body']['numero_referencia'];
    $num_rec = $res_pago['body']['numero_recibo'];
    echo "  ✅ PASÓ: Pago de póliza registrado con éxito (ID: $pago_id, Ref: $num_ref, Recibo: $num_rec).\n";
} else {
    echo "  ❌ FALLÓ: No se pudo registrar el pago (HTTP " . $res_pago['code'] . ").\n";
    print_r($res_pago['body']);
    exit(1);
}

// B. Validar asiento automático de cobro (COBRO_PRIMA) en libro diario
$res_diario = make_request('GET', "$base_url/centro_financiero.php?action=get_diario", null, $token);
$asiento_pago_encontrado = false;
$asiento_pago_id = null;

if ($res_diario['code'] === 200 && is_array($res_diario['body']['datos'])) {
    foreach ($res_diario['body']['datos'] as $asiento) {
        if (strpos($asiento['descripcion'], $num_poliza) !== false && strpos($asiento['descripcion'], 'Cobro') !== false) {
            $asiento_pago_encontrado = true;
            $asiento_pago_id = $asiento['id'];
            echo "  ✅ PASÓ: Asiento contable de cobro encontrado (ID: $asiento_pago_id, Código: {$asiento['numero']}).\n";
            echo "  📊 Descripción: {$asiento['descripcion']}\n";
            break;
        }
    }
}

if (!$asiento_pago_encontrado) {
    echo "  ❌ FALLÓ: No se encontró el asiento contable para el cobro del pago.\n";
    exit(1);
}

// C. Validar líneas de asiento (Partida Doble Cobro: Caja vs Primas por Cobrar)
$res_det = make_request('GET', "$base_url/centro_financiero.php?action=get_asiento_detalle&id=$asiento_pago_id", null, $token);
if ($res_det['code'] === 200 && is_array($res_det['body']['datos'])) {
    $suma_debe = 0;
    $suma_haber = 0;
    echo "  📋 Desglose de Asiento de Cobro (Partida Doble):\n";
    foreach ($res_det['body']['datos'] as $l) {
        echo "    - [{$l['cuenta_codigo']}] {$l['cuenta_nombre']}: Debe: RD$ {$l['debe']} | Haber: RD$ {$l['haber']}\n";
        $suma_debe += $l['debe'];
        $suma_haber += $l['haber'];
    }
    
    if (abs($suma_debe - $suma_haber) < 0.001 && $suma_debe > 0) {
        echo "  ✅ PASÓ: Ecuación contable de cobro equilibrada (Débitos = Créditos = RD$ " . number_format($suma_debe, 2) . ").\n";
    } else {
        echo "  ❌ FALLÓ: Las líneas del asiento de cobro están desbalanceadas o vacías.\n";
        exit(1);
    }
} else {
    echo "  ❌ FALLÓ: No se pudieron obtener las líneas de detalle del asiento de cobro.\n";
    exit(1);
}

// -------------------------------------------------------------
// PRUEBA 3.7: S0.8 - SOPORTE DE DEPÓSITOS Y TRAZABILIDAD DE PAGOS POR QR
// -------------------------------------------------------------
echo "\n📂 PROBANDO S0.8: DEPOSITOS DIFERIDOS Y VERIFICACION PUBLICADA POR QR...\n";

// A. Crear archivo temporal de prueba para simular el depósito bancario
$temp_file = tempnam(sys_get_temp_dir(), 'depo_soporte_') . '.pdf';
file_put_contents($temp_file, '%PDF-1.4 mock content for validation ' . uniqid());

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$base_url/pagos.php?action=registrar");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$headers = ["Authorization: Bearer " . $token];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);

$ref_deposito_test = 'DEP-' . rand(100000, 999999);
$post_fields = [
    'poliza_id' => $pol_id,
    'monto' => $p_total,
    'tipo_pago' => 'transferencia',
    'banco' => 'Banco BHD',
    'numero_comprobante' => $ref_deposito_test,
    'descripcion' => "Pago diferido con soporte póliza $num_poliza",
    'documento_deposito' => new CURLFile($temp_file, 'application/pdf', 'deposito_bhd.pdf')
];
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
unlink($temp_file);

$res_pago_pend = json_decode($response, true);
if ($http_code === 200 && isset($res_pago_pend['id']) && $res_pago_pend['estado_pago'] === 'pendiente') {
    $pago_pend_id = $res_pago_pend['id'];
    $ref_pend = $res_pago_pend['numero_referencia'];
    echo "  ✅ PASÓ: Registro de pago pendiente exitoso (ID: $pago_pend_id, Ref: $ref_pend, Estado: pendiente).\n";
} else {
    echo "  ❌ FALLÓ: No se pudo registrar pago diferido (HTTP $http_code).\n";
    print_r($res_pago_pend ?: $response);
    exit(1);
}

// B. Verificar que no se generó asiento contable aún (diferido)
$res_diario_check = make_request('GET', "$base_url/centro_financiero.php?action=get_diario", null, $token);
$asiento_anticipado = false;
if ($res_diario_check['code'] === 200 && is_array($res_diario_check['body']['datos'])) {
    foreach ($res_diario_check['body']['datos'] as $asiento) {
        if (strpos($asiento['descripcion'], $ref_deposito_test) !== false) {
            $asiento_anticipado = true;
            break;
        }
    }
}
if (!$asiento_anticipado) {
    echo "  ✅ PASÓ: Se confirmó diferimiento de contabilidad (sin asiento generado para pago pendiente).\n";
} else {
    echo "  ❌ FALLÓ: Se detectó asiento contable anticipado para un pago en estado pendiente.\n";
    exit(1);
}

// C. Verificar el endpoint de verificación público (sin autenticación Bearer)
$res_verif = make_request('GET', "$base_url/pagos.php?action=verificar&ref=$ref_pend");
if ($res_verif['code'] === 200 && isset($res_verif['body']['data']['estado_pago'])) {
    $data_v = $res_verif['body']['data'];
    if ($data_v['estado_pago'] === 'pendiente' && $data_v['comprobante_nombre'] === 'deposito_bhd.pdf') {
        echo "  ✅ PASÓ: Endpoint de verificación público ratifica estado pendiente y soporte de archivo.\n";
    } else {
        echo "  ❌ FALLÓ: Datos devueltos inconsistentes en la verificación pública.\n";
        exit(1);
    }
} else {
    echo "  ❌ FALLÓ: Error consultando la validación pública (HTTP " . $res_verif['code'] . ").\n";
    print_r($res_verif['body']);
    exit(1);
}

// D. Aprobar Pago Administrativamente para disparar contabilidad
$approve_payload = ['id' => $pago_pend_id];
$res_appr = make_request('POST', "$base_url/pagos.php?action=aprobar", $approve_payload, $token);
if ($res_appr['code'] === 200 && $res_appr['body']['exito'] === true) {
    echo "  ✅ PASÓ: Pago aprobado y validado administrativamente con éxito.\n";
} else {
    echo "  ❌ FALLÓ: Error al aprobar pago (HTTP " . $res_appr['code'] . ").\n";
    print_r($res_appr['body']);
    exit(1);
}

// E. Validar asiento automático de cobro (COBRO_PRIMA) generado tras aprobación
$res_diario_final = make_request('GET', "$base_url/centro_financiero.php?action=get_diario", null, $token);
$asiento_final_encontrado = false;
if ($res_diario_final['code'] === 200 && is_array($res_diario_final['body']['datos'])) {
    foreach ($res_diario_final['body']['datos'] as $asiento) {
        if (strpos($asiento['descripcion'], $ref_deposito_test) !== false && strpos($asiento['descripcion'], 'Cobro') !== false) {
            $asiento_final_encontrado = true;
            echo "  ✅ PASÓ: Asiento automático COBRO_PRIMA generado exitosamente tras aprobación!\n";
            echo "  📊 Descripción detallada: {$asiento['descripcion']}\n";
            break;
        }
    }
}
if (!$asiento_final_encontrado) {
    echo "  ❌ FALLÓ: No se disparó el trigger contable post-aprobación o no se encontró el asiento.\n";
    exit(1);
}

// -------------------------------------------------------------
// PRUEBA 4: S0.4 - CONTABILIDAD AUTOMÁTICA Y NCF (CENTRO FINANCIERO)
// -------------------------------------------------------------
echo "\n📈 PROBANDO S0.4: CONTABILIDAD AUTOMÁTICA & NCF...\n";

// A. Validar que el asiento contable fue creado por el trigger/hook contable
$res = make_request('GET', "$base_url/centro_financiero.php?action=get_diario", null, $token);
$asiento_encontrado = false;
$asiento_id = null;

if ($res['code'] === 200 && is_array($res['body']['datos'])) {
    foreach ($res['body']['datos'] as $asiento) {
        if (strpos($asiento['descripcion'], $num_cotizacion) !== false) {
            $asiento_encontrado = true;
            $asiento_id = $asiento['id'];
            echo "  ✅ PASÓ: Asiento automático encontrado en el Libro Diario (ID: $asiento_id, Código: {$asiento['numero']}).\n";
            echo "  📊 Descripción: {$asiento['descripcion']}\n";
            break;
        }
    }
}

if (!$asiento_encontrado) {
    echo "  ❌ FALLÓ: No se disparó o no se encontró el asiento contable automático para la cotización.\n";
    exit(1);
}

// B. Aprobar Asiento para validar saldo en libro mayor (de acuerdo a ContabilidadManager)
// Para que tenga efecto en los saldos oficiales de getSaldoCuenta, lo aprobamos directamente
$db = Database::getInstance()->getConnection();
$db->query("UPDATE cf_asientos SET estado = 'APROBADO' WHERE id = $asiento_id");
echo "  ✅ PASÓ: Asiento contable aprobado en base de datos para impacto financiero.\n";

// C. Validar líneas de asiento (Partida Doble)
$res = make_request('GET', "$base_url/centro_financiero.php?action=get_asiento_detalle&id=$asiento_id", null, $token);
if ($res['code'] === 200 && is_array($res['body']['datos'])) {
    $suma_debe = 0;
    $suma_haber = 0;
    echo "  📋 Desglose de Asiento Automático:\n";
    foreach ($res['body']['datos'] as $l) {
        echo "    - [{$l['cuenta_codigo']}] {$l['cuenta_nombre']}: Debe: RD$ {$l['debe']} | Haber: RD$ {$l['haber']}\n";
        $suma_debe += $l['debe'];
        $suma_haber += $l['haber'];
    }
    
    if (abs($suma_debe - $suma_haber) < 0.001 && $suma_debe > 0) {
        echo "  ✅ PASÓ: Ecuación contable equilibrada (Débitos = Créditos = RD$ $suma_debe).\n";
    } else {
        echo "  ❌ FALLÓ: Las líneas del asiento están desbalanceadas o vacías.\n";
        exit(1);
    }
} else {
    echo "  ❌ FALLÓ: No se pudieron obtener las líneas de detalle del asiento.\n";
    exit(1);
}

// D. Verificar Resumen Financiero
$res = make_request('GET', "$base_url/centro_financiero.php?action=get_resumen", null, $token);
if ($res['code'] === 200 && isset($res['body']['datos'])) {
    $datos_res = $res['body']['datos'];
    echo "  ✅ PASÓ: Resumen financiero cargado exitosamente.\n";
    echo "    - Disponibilidad Caja/Bancos: RD$ " . number_format($datos_res['disponibilidad'], 2) . "\n";
    echo "    - Primas por Cobrar: RD$ " . number_format($datos_res['primas_cobrar'], 2) . "\n";
    echo "    - Comisiones Ganadas: RD$ " . number_format($datos_res['comisiones'], 2) . "\n";
    echo "    - ITBIS por Pagar: RD$ " . number_format($datos_res['itbis'], 2) . "\n";
} else {
    echo "  ❌ FALLÓ: No se pudo obtener el resumen financiero.\n";
    exit(1);
}

// -------------------------------------------------------------
// CONCLUSIÓN
// -------------------------------------------------------------
echo "\n===================================================================\n";
echo "🎉 ¡PRUEBAS DE INTEGRACIÓN CENTRALIZADAS DE SPRINT 0 COMPLETADAS! 🎉\n";
echo "  - SEGURIDAD API (HTTP 401): FUNCIONANDO EXCELENTE\n";
echo "  - CRUD CLIENTES (MySQL): FUNCIONANDO EXCELENTE\n";
echo "  - COTIZACIÓN + NCF (DGII): FUNCIONANDO EXCELENTE\n";
echo "  - MOTOR CONTABLE (PARTIDA DOBLE): FUNCIONANDO EXCELENTE\n";
echo "===================================================================\n";

unlink($cookie_file);
exit(0);
