<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$conn = new mysqli('localhost','root','','mq_platform');
if ($conn->connect_error) die('Conexion error: '.$conn->connect_error);
$cols = [
    'modulo_accedido' => "VARCHAR(100)",
    'funcion_ejecutada' => "VARCHAR(100)",
    'descripcion_evento' => "TEXT",
    'direccion_ip' => "VARCHAR(45)",
    'navegador_user_agent' => "VARCHAR(255)",
    'detalles_error' => "TEXT",
    'tabla_afectada' => "VARCHAR(50)",
    'registro_afectado_id' => "INT",
    'operacion_realizada' => "VARCHAR(50)",
    'valor_anterior' => "JSON",
    'valor_nuevo' => "JSON",
];
foreach($cols as $col => $type) {
    $res = $conn->query("SHOW COLUMNS FROM auditoria_accesos LIKE '{$col}'");
    if ($res && $res->num_rows == 0) {
        $sql = "ALTER TABLE auditoria_accesos ADD COLUMN {$col} {$type} NULL";
        if ($conn->query($sql)) echo "Added {$col}<br>";
        else echo "Error adding {$col}: " . $conn->error . "<br>";
    } else {
        echo "Exists {$col}<br>";
    }
}
$conn->close();
?>