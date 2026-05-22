<?php
header('Content-Type: text/plain; charset=utf-8');
require_once '../config.php';

$db = Database::getInstance()->getConnection();
$result = $db->query('DESCRIBE usuarios');

echo "Columnas en la tabla 'usuarios':\n";
while($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
