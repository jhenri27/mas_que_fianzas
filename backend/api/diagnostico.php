<?php
header('Content-Type: application/json; charset=utf-8');

$result = [
    "php_version" => PHP_VERSION,
    "openssl_enabled" => extension_loaded('openssl'),
    "mbstring_enabled" => extension_loaded('mbstring'),
    "display_errors" => ini_get('display_errors'),
    "log_errors" => ini_get('log_errors'),
    "error_log" => ini_get('error_log'),
    "writable_logs" => is_writable(__DIR__ . '/../logs/smtp.log'),
    "constants_check" => [
        "STREAM_CRYPTO_METHOD_TLS_ANY_CLIENT" => defined('STREAM_CRYPTO_METHOD_TLS_ANY_CLIENT')
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT);
?>
