<?php
// Script temporal: mostrar info y resetear password pdv.prueba
$pdo = new PDO('mysql:host=localhost;dbname=masque_fianzas_integrada_01;charset=utf8', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header('Content-Type: text/html; charset=utf-8');

// Buscar usuario
$stmt = $pdo->prepare("SELECT id, username, nombre, apellido, email, estado, requiere_cambio_password, fecha_creacion FROM usuarios WHERE username LIKE '%pdv%' OR email LIKE '%pdv%'");
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>👤 Usuarios PDV encontrados:</h2>";
foreach ($usuarios as $u) {
    echo "<pre style='background:#f0f4f8;padding:15px;border-radius:8px;'>";
    foreach ($u as $k => $v) echo "<b>$k:</b> $v\n";
    echo "</pre>";
}

// Action: resetear password
$nueva = 'PDV@2024';
$hash = password_hash($nueva, PASSWORD_BCRYPT);
$upd = $pdo->prepare("UPDATE usuarios SET password_hash=?, requiere_cambio_password=0 WHERE username='pdv.prueba'");
$upd->execute([$hash]);

if ($upd->rowCount() > 0) {
    echo "<div style='background:#d1fae5;padding:15px;border-radius:8px;border:1px solid #6ee7b7;'>";
    echo "<h3 style='color:#065f46;margin:0'>✅ Contraseña reseteada exitosamente</h3>";
    echo "<p><b>Usuario:</b> <code>pdv.prueba</code></p>";
    echo "<p><b>Nueva contraseña:</b> <code style='font-size:18px;color:#1963a3;'>PDV@2024</code></p>";
    echo "</div>";
} else {
    echo "<div style='background:#fee2e2;padding:15px;border-radius:8px;'>";
    echo "<p style='color:#991b1b'>⚠️ Usuario 'pdv.prueba' no encontrado para actualizar.</p>";
    echo "</div>";
}

// Info tabla cotizaciones
$r = $pdo->query("SELECT COUNT(*) FROM cotizaciones");
$cnt = $r->fetchColumn();
echo "<h2>📋 Tabla de Cotizaciones</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
echo "<tr><th>Base de datos</th><td><code>masque_fianzas_integrada_01</code></td></tr>";
echo "<tr><th>Tabla</th><td><code>cotizaciones</code></td></tr>";
echo "<tr><th>Registros actuales</th><td><b>$cnt cotizaciones</b></td></tr>";
echo "</table>";
echo "<p><a href='/PLATAFORMA_INTEGRADA/frontend/dashboard.html'>← Volver al Dashboard</a></p>";
?>
