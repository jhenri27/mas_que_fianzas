<?php
$hash = '$2y$10$YOUctOB2v8YwHNS/pW06X.eNas1k25MQdnt8zTAZy/zbFCd1/1CZu';
$pass = 'Demo@123';
if (password_verify($pass, $hash)) {
    echo "VERIFIED\n";
} else {
    echo "NOT VERIFIED\n";
}
?>
