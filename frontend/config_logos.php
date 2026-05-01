<?php
session_start();
require_once 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config.php';

$db = Database::getInstance()->getConnection();

// Path to the dedicated JS file for logos
$js_file = __DIR__ . '/assets/logos_aseguradoras.js';

// Ensure directory exists and is writable
if (!is_writable(dirname($js_file))) {
    $error_msg = "Error: El directorio assets no tiene permisos de escritura.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo']) && isset($_POST['aseguradora'])) {
    $aseg = strtoupper(trim($_POST['aseguradora']));
    $b64 = base64_encode(file_get_contents($_FILES['logo']['tmp_name']));
    $mime = $_FILES['logo']['type'];
    $data_uri = 'data:' . $mime . ';base64,' . $b64;
    
    // Load existing logos from the JS file if it exists
    $logos = [];
    if (file_exists($js_file)) {
        $content = file_get_contents($js_file);
        // Extract the JSON part from window.LOGOS = {...};
        if (preg_match('/window\.LOGOS\s*=\s*(\{.*?\});/s', $content, $matches)) {
            $logos = json_decode($matches[1], true) ?: [];
        }
    }
    
    // Update or add the new logo
    $logos[$aseg] = $data_uri;
    
    // Also update the legacy global if it's MULTISEGUROS
    $legacy = "";
    if ($aseg === 'MULTISEGUROS') {
        $legacy = "window.LOGO_MULTISEGUROS_B64 = '" . $data_uri . "';\n";
    }
    
    // Write back to the JS file
    $new_content = "/** Logos de Aseguradoras - Generado Automáticamente **/\n";
    $new_content .= "window.LOGOS = " . json_encode($logos, JSON_PRETTY_PRINT) . ";\n";
    if ($legacy) $new_content .= $legacy;
    
    if (file_put_contents($js_file, $new_content)) {
        echo "<script>alert('Logo de $aseg cargado exitosamente.'); window.location.href='config_logos.php';</script>";
    } else {
        echo "<script>alert('Error al guardar el archivo. Verifique permisos.'); window.location.href='config_logos.php';</script>";
    }
    exit;
}

// Load current logos for display
$logos = [];
if (file_exists($js_file)) {
    $content = file_get_contents($js_file);
    if (preg_match('/window\.LOGOS\s*=\s*(\{.*?\});/s', $content, $matches)) {
        $logos = json_decode($matches[1], true) ?: [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración de Logos de Aseguradoras</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; padding: 40px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; color: #4f46e5; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 500; margin-bottom: 8px; color: #555; }
        select, input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { background: #4f46e5; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; width: 100%; }
        button:hover { background: #4338ca; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; }
        .card img { max-width: 100%; max-height: 80px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestión de Logos (Aseguradoras)</h1>
        <p>Sube el logo de cada aseguradora para que se integren automáticamente en los Marbetes y pólizas generados.</p>
        
        <form method="post" enctype="multipart/form-data" style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div class="form-group">
                <label>Aseguradora</label>
                <select name="aseguradora" required>
                    <option value="">Seleccione...</option>
                    <option value="MULTISEGUROS">MULTISEGUROS</option>
                    <option value="UNIVERSAL">UNIVERSAL</option>
                    <option value="RESERVAS">RESERVAS</option>
                    <option value="HUMANO">HUMANO</option>
                    <option value="MAPFRE">MAPFRE</option>
                    <option value="SURA">SURA</option>
                    <option value="COLONIAL">COLONIAL</option>
                    <option value="PATRIA">PATRIA</option>
                    <option value="PEPIN">PEPÍN</option>
                    <option value="APS">APS</option>
                </select>
            </div>
            <div class="form-group">
                <label>Archivo del Logo (PNG o JPG, se recomienda fondo transparente)</label>
                <input type="file" name="logo" accept="image/png, image/jpeg" required>
            </div>
            <button type="submit">Cargar Logo</button>
        </form>

        <h2 style="margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Logos Cargados</h2>
        <div class="grid">
            <?php foreach($logos as $aseg => $b64): ?>
                <div class="card">
                    <strong><?= htmlspecialchars($aseg) ?></strong>
                    <br>
                    <img src="<?= $b64 ?>" alt="<?= $aseg ?>">
                </div>
            <?php endforeach; ?>
            <?php if(empty($logos)): ?>
                <p style="grid-column: 1 / -1; color: #888;">No hay logos cargados aún.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
