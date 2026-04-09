<?php
/**
 * Actualizar logo de MAS QUE FIANZAS en toda la plataforma
 * Fuente: C:\wamp64\www\PLATAFORMA_INTEGRADA\Iconos\Logo_Mas_qu_fianzas_fondo-removebg-preview-2.ico
 */

$iconoOrigen = 'C:/wamp64/www/PLATAFORMA_INTEGRADA/Iconos/Logo_Mas_qu_fianzas_fondo-removebg-preview-2.ico';
$assetsDir   = 'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/';

echo "<h2>🔄 Actualizando Logo de la Plataforma</h2><ul>";

// 1. Copiar como mqf-logo-sidebar.ico (sidebar del dashboard y módulos)
$dest1 = $assetsDir . 'mqf-logo-sidebar.ico';
if (copy($iconoOrigen, $dest1)) {
    echo "<li style='color:green'>✅ Copiado como <code>mqf-logo-sidebar.ico</code></li>";
} else {
    echo "<li style='color:red'>❌ Error copiando a mqf-logo-sidebar.ico</li>";
}

// 2. Copiar como PNG usando GD para conversión (para logo_mqf.png usado en login y PDF)
// Intentar leer el ICO como imagen
$imgData = @file_get_contents($iconoOrigen);
if ($imgData === false) {
    echo "<li style='color:red'>❌ No se puede leer el archivo ICO fuente</li>";
} else {
    // Guardar también como PNG directo (algunos ICO son PNG internamente)
    $dest2 = $assetsDir . 'logo_mqf.png';
    // Intentar crear imagen desde los bytes del ICO
    $img = @imagecreatefromstring($imgData);
    if ($img) {
        imagepng($img, $dest2);
        imagedestroy($img);
        echo "<li style='color:green'>✅ Convertido y guardado como <code>logo_mqf.png</code></li>";
    } else {
        // Copy directamente como PNG (funciona si el ICO tiene PNG embebido)
        copy($iconoOrigen, $dest2);
        echo "<li style='color:orange'>⚠️ Copiado como logo_mqf.png (sin convertir - verificar visualmente)</li>";
    }

    // 3. Actualizar logo_b64.js para PDF con el nuevo logo
    // Usar el PNG como base
    $pngData = @file_get_contents($dest2);
    if ($pngData) {
        $b64 = base64_encode($pngData);
        $js = 'window.LOGO_MQF_B64 = "data:image/png;base64,' . $b64 . '";';
        file_put_contents($assetsDir . 'logo_b64.js', $js);
        echo "<li style='color:green'>✅ Actualizado <code>logo_b64.js</code> para impresión de PDF</li>";
    }
}

// 4. También copiar el ICO como mqf-favicon.ico (por si acaso)
$dest3 = $assetsDir . 'mqf-favicon.ico';
if (copy($iconoOrigen, $dest3)) {
    echo "<li style='color:green'>✅ Actualizado <code>mqf-favicon.ico</code></li>";
}

echo "</ul>";
echo "<p style='color:green;font-weight:bold'>✅ Logos actualizados. Recarga el dashboard para ver los cambios.</p>";
echo "<p><a href='/PLATAFORMA_INTEGRADA/frontend/dashboard.html'>→ Ver Dashboard</a></p>";
echo "<p><a href='/PLATAFORMA_INTEGRADA/frontend/index.html'>→ Ver Login</a></p>";
?>
