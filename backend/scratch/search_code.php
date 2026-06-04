<?php
$dir = 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend';
$terms = ['PDV', 'Socio Comercial', 'caja', 'sucursal', 'cajero', 'restriccion_por_sucursal'];

function searchInDir($directory, $terms) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($it as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        foreach ($terms as $term) {
            if (stripos($content, $term) !== false) {
                // Find matching lines
                $lines = explode("\n", $content);
                foreach ($lines as $i => $line) {
                    if (stripos($line, $term) !== false) {
                        $relPath = str_replace('c:/wamp64/www/PLATAFORMA_INTEGRADA/', '', $file->getPathname());
                        echo "$relPath:" . ($i + 1) . " (found '$term'): " . trim($line) . "\n";
                    }
                }
            }
        }
    }
}

searchInDir($dir, $terms);
?>
