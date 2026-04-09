<?php
/**
 * Test Login Endpoint Directly
 */

header('Content-Type: application/json; charset=utf-8');

// Start output buffering to prevent headers already sent errors
ob_start();

include 'backend/config.php';
include 'backend/Autenticacion.php';

// Get JSON from request
$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? 'admin';
$password = $input['password'] ?? 'Demo@123';

echo json_encode([
    'test' => 'LoginTest',
    'input' => [
        'username' => $username,
        'password' => $password
    ],
    'db_configured' => constant('DB_NAME') === 'mq_platform',
    'constants' => [
        'DB_HOST' => constant('DB_HOST'),
        'DB_NAME' => constant('DB_NAME'),
        'DB_USER' => constant('DB_USER'),
        'SESSION_TIMEOUT' => constant('SESSION_TIMEOUT_MINUTES')
    ]
], JSON_PRETTY_PRINT);

ob_end_flush();
?>
