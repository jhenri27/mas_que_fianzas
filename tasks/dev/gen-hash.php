<?php
$password = 'PDVtest2026!';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
echo $hash;
