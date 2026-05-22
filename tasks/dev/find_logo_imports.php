<?php
function scan_dir($dir) {
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'html') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$frontend_dir = 'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend';
$html_files = scan_dir($frontend_dir);

echo "Scanning HTML files for 'logo_b64.js'...\n";
foreach ($html_files as $f) {
    $content = file_get_contents($f);
    if (strpos($content, 'logo_b64.js') !== false) {
        echo "Found in file: $f\n";
        // Show the lines matching logo_b64.js
        $lines = explode("\n", $content);
        foreach ($lines as $idx => $line) {
            if (strpos($line, 'logo_b64.js') !== false) {
                echo "  Line " . ($idx + 1) . ": " . trim($line) . "\n";
            }
        }
    }
}
?>
