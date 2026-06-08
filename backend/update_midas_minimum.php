<?php
require_once dirname(__FILE__) . '/config.php';

$db = Database::getInstance()->getConnection();

try {
    echo "=== INICIANDO ACTUALIZACIÓN DE PRIMA MÍNIMA PARA MIDAS ===\n";
    $stmt = $db->prepare("UPDATE fianza_tarifarios SET prima_minima_override = 3000.00 WHERE aseguradora_id = 2");
    if ($stmt->execute()) {
        echo "✅ Prima mínima de Midas (aseguradora_id = 2) actualizada a RD$ 3,000.00 en " . $stmt->affected_rows . " filas.\n";
    } else {
        echo "❌ Error al ejecutar la actualización: " . $stmt->error . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error en la actualización: " . $e->getMessage() . "\n";
}
?>
