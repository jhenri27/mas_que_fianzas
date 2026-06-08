<?php
// Buscar referencias a tarifas_seguro
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../'));
foreach ($files as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (strpos($path, 'node_modules') !== false || strpos($path, '.git') !== false || strpos($path, 'scratch') !== false) continue;
    
    $content = file_get_contents($path);
    if (strpos($content, 'tarifas_seguro') !== false) {
        echo "Found in: $path\n";
    }
}
?>
