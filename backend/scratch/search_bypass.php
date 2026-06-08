<?php
$fpath = 'C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/dashboard.js';
$lines = file($fpath);
foreach ($lines as $i => $line) {
    if (strpos($line, 'labs_masqf') !== false || strpos($line, 'configuracion') !== false || strpos($line, 'nav-item') !== false) {
        echo ($i+1) . ": " . trim($line) . "\n";
    }
}
?>
