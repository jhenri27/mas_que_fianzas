<?php
/**
 * Subida y Configuración dinámica de Logo
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["logo"])) {
    $file = $_FILES["logo"];
    
    // Validar tipo de archivo
    $allowedTypes = ['image/png', 'image/jpeg', 'image/x-icon', 'image/vnd.microsoft.icon'];
    $mimeType = mime_content_type($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        echo json_encode(["exito" => false, "mensaje" => "Formato no permitido. Usa PNG o JPG."]);
        exit;
    }
    
    // Directorio assets frontend
    $assetsDir = 'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/';
    
    if (!is_dir($assetsDir)) {
        echo json_encode(["exito" => false, "mensaje" => "Error interno: directorio assets no encontrado."]);
        exit;
    }
    
    // Mismo proceso que update_logo_pdf.php
    $data = file_get_contents($file['tmp_name']);
    
    // Usar GD para convertir siempre a PNG limpio
    $img = @imagecreatefromstring($data);
    if ($img) {
        ob_start();
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagepng($img, null, 0); 
        $pngData = ob_get_clean();
        imagedestroy($img);
        $b64 = base64_encode($pngData);
        $mimeUsed = 'image/png';
    } else {
        // Fallback: usar raw b64
        $b64 = base64_encode($data);
        $mimeUsed = $mimeType;
    }
    
    // 1. Escribir logo_b64.js (Para PDFs)
    $js = 'window.LOGO_MQF_B64 = "data:' . $mimeUsed . ';base64,' . $b64 . '";' . "\n";
    file_put_contents($assetsDir . 'logo_b64.js', $js);
    
    // 2. Sobrescribir logo PNG principal (login, etc)
    if ($img) {
        file_put_contents($assetsDir . 'logo_mqf.png', $pngData);
    } else {
        move_uploaded_file($file['tmp_name'], $assetsDir . 'logo_mqf.png');
    }
    
    // 3. Sobrescribir logo del Sidebar (Icono)
    copy($assetsDir . 'logo_mqf.png', $assetsDir . 'mqf-logo-sidebar.ico');
    
    echo json_encode([
        "exito" => true, 
        "mensaje" => "Logo actualizado en todos los módulos y PDFs."
    ]);
} else {
    echo json_encode(["exito" => false, "mensaje" => "No se recibió ningún archivo."]);
}
?>
