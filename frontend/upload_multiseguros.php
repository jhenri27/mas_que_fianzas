<?php
// Script para cargar el logo de MultiSeguros y agregarlo al archivo JS
if(isset($_FILES['logo'])) {
    $b64 = base64_encode(file_get_contents($_FILES['logo']['tmp_name']));
    $mime = $_FILES['logo']['type'];
    $js_file = dirname(__DIR__) . '/assets/logo_b64.js';
    
    // Evitar duplicados
    $existing = file_get_contents($js_file);
    if (strpos($existing, 'window.LOGO_MULTISEGUROS_B64') !== false) {
        $existing = preg_replace('/window\.LOGO_MULTISEGUROS_B64\s*=\s*".*?";/s', '', $existing);
        $existing = preg_replace("/window\.LOGO_MULTISEGUROS_B64\s*=\s*'.*?';/s", '', $existing);
        file_put_contents($js_file, $existing);
    }

    // Agregar al final
    $content = "\nwindow.LOGO_MULTISEGUROS_B64 = 'data:" . $mime . ";base64," . $b64 . "';\n";
    file_put_contents($js_file, $content, FILE_APPEND);
    
    echo "<div style='font-family: Arial; padding: 20px; text-align: center; color: #2e7d32;'>";
    echo "<h1>¡Logo de MultiSeguros Cargado Exitosamente!</h1>";
    echo "<p>El logo se ha inyectado correctamente en el código del sistema.</p>";
    echo "<button onclick='window.close()' style='padding: 10px 20px; cursor: pointer;'>Cerrar esta pestaña</button>";
    echo "</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Logo MultiSeguros</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        input[type="file"] { margin: 20px 0; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #333; margin-top: 0;">Subir Logo de MultiSeguros</h2>
        <p style="color: #666; font-size: 14px;">Sube aquí la imagen del logo que me acabas de mostrar para integrarla en el Marbete PDF.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="logo" accept="image/png, image/jpeg" required>
            <button type="submit">Cargar Logo al Sistema</button>
        </form>
    </div>
</body>
</html>
