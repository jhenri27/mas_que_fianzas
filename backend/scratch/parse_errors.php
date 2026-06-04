<?php
$logFile = 'c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/logs/error.log';
if (!file_exists($logFile)) {
    die("Log file not found");
}
$today = "04-Jun-2026";
$lines = file($logFile);
foreach ($lines as $line) {
    if (strpos($line, $today) !== false && (strpos($line, "Fatal error") !== false || strpos($line, "Exception") !== false || strpos($line, "Warning") !== false || strpos($line, "Notice") !== false)) {
        echo $line;
    }
}
