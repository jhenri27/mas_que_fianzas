<?php
/**
 * APLICADOR DE ESQUEMA CONTABLE
 * Ejecuta cf_schema.sql en la base de datos.
 */

require_once 'backend/config.php';

try {
    $db = Database::getInstance()->getConnection();
    $sqlFile = 'database/cf_schema.sql';
    
    if (!file_exists($sqlFile)) {
        die("Error: No se encuentra el archivo $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    
    // Limpieza previa para el lab
    $tablesToDrop = ['cf_ncf', 'cf_asiento_lineas', 'cf_asientos', 'cf_periodos', 'cf_ncf_secuencias', 'cf_catalogo_cuentas', 'cf_reglas_asiento'];
    $db->query("SET FOREIGN_KEY_CHECKS = 0");
    foreach($tablesToDrop as $t) { $db->query("DROP TABLE IF EXISTS $t"); }
    $db->query("SET FOREIGN_KEY_CHECKS = 1");

    // Ejecutar consultas una a una para mejor debugging
    $queries = explode(';', $sql);
    $ok = 0;
    $errors = [];

    foreach ($queries as $q) {
        $q = trim($q);
        if (empty($q)) continue;
        
        if ($db->query($q)) {
            $ok++;
        } else {
            $errors[] = "Error en query: $q \nDetalle: " . $db->error;
            // No detenemos, intentamos seguir (algunas pueden fallar por existir ya)
        }
    }

    echo "Consultas exitosas: $ok\n";
    if (!empty($errors)) {
        echo "\nErrores detectados:\n" . implode("\n\n", $errors);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
