<?php
/**
 * Actualizar logo_b64.js con el PNG correcto para los PDF
 */
$pngSrc  = 'C:/wamp64/www/PLATAFORMA_INTEGRADA/Iconos/Logo_Mas_qu_fianzas_fondo-removebg-preview-2.png';
$b64File = 'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/logo_b64.js';

header('Content-Type: text/html; charset=utf-8');

$source = $pngSrc;
$mime   = 'image/png';

$data = @file_get_contents($source);
if (!$data) {
    die("<p style='color:red'>❌ No se puede leer el archivo: $source</p>");
}

// Intentar crear imagen GD y guardar como PNG limpio (por si acaso el PNG original tiene formato extraño)
$img = @imagecreatefromstring($data);
if ($img) {
    ob_start();
    // Guardar PNG manteniendo transparencia
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagepng($img, null, 0); 
    $pngData = ob_get_clean();
    imagedestroy($img);
    $b64 = base64_encode($pngData);
    $mimeType = 'image/png';
    echo "<p style='color:green'>✅ Imagen convertida y optimizada correctamente a PNG limpio vía GD</p>";
} else {
    // Fallback: usar datos crudos
    $b64 = base64_encode($data);
    $mimeType = 'image/png';
    echo "<p style='color:orange'>⚠️ GD no pudo procesar. Usando datos directos.</p>";
}

// Escribir logo_b64.js
$js = 'window.LOGO_MQF_B64 = "data:' . $mimeType . ';base64,' . $b64 . '";' . "\n";
$bytes = file_put_contents($b64File, $js);

// También actualizamos logo_mqf.png
file_put_contents('C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/logo_mqf.png', base64_decode($b64));

echo "<p style='color:green;font-weight:bold'>✅ <code>logo_b64.js</code> y <code>logo_mqf.png</code> actualizados correctamente con Log_Mas_qu_fianzas_fondo-removebg-preview-2.png</p>";
?>
