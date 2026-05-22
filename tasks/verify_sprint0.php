<?php
/**
 * Script de verificación automatizada para Sprint 0
 * Verifica que las APIs de cotizaciones y clientes requieran sesión (HTTP 401).
 */

echo "=== INICIANDO VERIFICACIÓN DE SPRINT 0 ===\n\n";

$urls = [
    'Cotizaciones (Listar)' => 'http://localhost/PLATAFORMA_INTEGRADA/backend/api/cotizaciones.php?action=listar',
    'Cotizaciones (Guardar)' => 'http://localhost/PLATAFORMA_INTEGRADA/backend/api/cotizaciones.php?action=guardar',
    'Clientes (Listar)' => 'http://localhost/PLATAFORMA_INTEGRADA/backend/api/clientes.php',
    'Clientes (Crear)' => 'http://localhost/PLATAFORMA_INTEGRADA/backend/api/clientes.php/crear'
];

$all_passed = true;

foreach ($urls as $name => $url) {
    echo "Probando $name sin sesión...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 401) {
        echo "✅ PASÓ: Retornó HTTP 401 (Sesión no válida o expirada) como se esperaba.\n";
    } else {
        echo "❌ FALLÓ: Retornó HTTP $http_code en lugar de 401.\n";
        $all_passed = false;
    }
    echo "----------------------------------------\n";
}

if ($all_passed) {
    echo "\n🎉 TODAS LAS PRUEBAS DE SEGURIDAD PASARON CON ÉXITO!\n";
} else {
    echo "\n⚠️ ALGUNAS PRUEBAS FALLARON. Por favor verifique el estado del servidor WAMP o la configuración.\n";
}
?>
