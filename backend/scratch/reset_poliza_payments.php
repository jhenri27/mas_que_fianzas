<?php
require_once dirname(__FILE__) . '/../config.php';
$db = Database::getInstance()->getConnection();

echo "=== RESETTING POLICY POL-2026-1675 ===\n";

// 1. Get policy info
$sql = "SELECT id, cuota_total, prima_total FROM polizas WHERE numero_poliza = 'POL-2026-1675'";
$res = $db->query($sql);
if ($res && $res->num_rows > 0) {
    $poliza = $res->fetch_assoc();
    $poliza_id = $poliza['id'];
    
    // 2. Delete existing payments for this policy
    $sql_del = "DELETE FROM pagos WHERE poliza_id = $poliza_id";
    if ($db->query($sql_del)) {
        echo "Deleted existing payments for policy ID $poliza_id.\n";
    } else {
        echo "Error deleting payments: " . $db->error . "\n";
    }
    
    // 3. Update the policy cuota_total to 1
    $sql_upd = "UPDATE polizas SET cuota_total = 1 WHERE id = $poliza_id";
    if ($db->query($sql_upd)) {
        echo "Updated cuota_total to 1 for policy ID $poliza_id.\n";
    } else {
        echo "Error updating policy: " . $db->error . "\n";
    }
} else {
    echo "Policy POL-2026-1675 not found.\n";
}
?>
