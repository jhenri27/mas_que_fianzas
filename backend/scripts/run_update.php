<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=masque_fianzas_integrada_01', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = file_get_contents('backend/scripts/update_db.sql');
    $pdo->exec($sql);
    echo "DB Updated successfully.\n";
} catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
