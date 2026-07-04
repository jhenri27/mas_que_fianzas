<?php
require_once __DIR__ . '/config.php';
$db = Database::getInstance()->getConnection();

// Update email for all clients (as requested: "a todos los clientes de prueba le pondrás este e-mail")
$db->query("UPDATE clientes SET correo = 'ahenriquezmarte@gmail.com'");
echo "Clientes actualizados: " . $db->affected_rows . "<br>";

// Check FPDF / TCPDF
if (file_exists(__DIR__ . '/libs/fpdf/fpdf.php')) {
    echo "FPDF exists in libs<br>";
} else if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    echo "FPDF exists in root<br>";
} else {
    echo "FPDF NOT FOUND<br>";
}

if (file_exists(__DIR__ . '/libs/tcpdf/tcpdf.php')) {
    echo "TCPDF exists in libs<br>";
} else {
    echo "TCPDF NOT FOUND<br>";
}
?>
