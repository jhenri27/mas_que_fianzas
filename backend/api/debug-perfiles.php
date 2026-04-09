<?php
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG PERFILES LISTAR ===\n";

try {
    require_once '../config.php';
    echo "✅ config.php cargado\n";
    echo "   DB_NAME = " . DB_NAME . "\n";
} catch (Throwable $e) {
    echo "❌ Error config.php: " . $e->getMessage() . "\n";
}

try {
    require_once '../PerfilManager.php';
    echo "✅ PerfilManager.php cargado\n";
} catch (Throwable $e) {
    echo "❌ Error PerfilManager.php: " . $e->getMessage() . "\n";
}

try {
    $manager = new PerfilManager();
    echo "✅ PerfilManager instanciado\n";
} catch (Throwable $e) {
    echo "❌ Error new PerfilManager(): " . $e->getMessage() . "\n";
}

try {
    $perfiles = $manager->listarPerfiles();
    echo "✅ listarPerfiles() OK - Total: " . count($perfiles) . "\n";
    foreach ($perfiles as $p) {
        echo "   - [{$p['id']}] {$p['nombre_perfil']} (nivel: {$p['nivel_jerarquico']})\n";
    }
} catch (Throwable $e) {
    echo "❌ Error listarPerfiles(): " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEBUG ===\n";
