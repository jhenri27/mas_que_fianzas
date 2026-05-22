<?php
$types = "ssssssssisiiddii";
echo "Longitud de tipos: " . strlen($types) . "\n";
echo "Caracteres: ";
for($i=0; $i<strlen($types); $i++) echo $types[$i] . " ";
echo "\n";

$sql = "INSERT INTO usuarios VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";
$count = substr_count($sql, "?");
echo "Cantidad de placeholders (?): " . $count . "\n";

if (strlen($types) === $count) {
    echo "COINCIDEN\n";
} else {
    echo "Falla: " . strlen($types) . " != " . $count . "\n";
}
?>
