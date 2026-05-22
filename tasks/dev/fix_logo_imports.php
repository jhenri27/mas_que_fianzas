<?php
$files = [
    'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/centro_financiero.html',
    'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/cotizaciones.html',
    'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/labs-masqf.html',
    'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/polizas.html',
    'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/reportes.html'
];

foreach ($files as $f) {
    if (!file_exists($f)) {
        echo "File does not exist: $f\n";
        continue;
    }
    
    $content = file_get_contents($f);
    
    // Replace any occurrence of '../assets/logo_b64.js' (without query param or with other query param) with '../assets/logo_b64.js?v=4'
    // Specifically, let's find '<script src="../assets/logo_b64.js"></script>'
    $target = '<script src="../assets/logo_b64.js"></script>';
    $replacement = '<script src="../assets/logo_b64.js?v=4"></script>';
    
    if (strpos($content, $target) !== false) {
        $content = str_replace($target, $replacement, $content);
        file_put_contents($f, $content);
        echo "✅ Fixed: $f\n";
    } else {
        echo "⚠️ Target import not found exactly in: $f (might be already modified or different format)\n";
    }
}
?>
