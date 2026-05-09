<?php
header('Content-Type: text/plain');
$log = '../logs/error.log';
if (file_exists($log)) {
    $lines = file($log);
    $last = array_slice($lines, -50);
    echo implode("", $last);
} else {
    echo "No hay archivo de log en $log";
}
