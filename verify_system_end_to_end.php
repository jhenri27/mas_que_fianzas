<?php
/**
 * Script de Verificación End-to-End
 * Valida base de datos, Python ETL y reglas de importación.
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== INICIANDO VERIFICACIÓN END-TO-END DE PLATAFORMA INTEGRADA ===\n\n";

// 1. Verificar Conexión a la Base de Datos
require_once 'backend/config.php';
try {
    $db = Database::getInstance()->getConnection();
    echo "[✓] Conexión a base de datos exitosa.\n";
} catch (Exception $e) {
    die("[X] ERROR de conexión a la BD: " . $e->getMessage() . "\n");
}

// 2. Verificar Columnas en Tabla 'usuarios'
$columnas_usuarios_esperadas = [
    'codigo_usuario', 'es_comisionante', 'porcentaje_comision', 'porcentaje_comision_red', 'referente_id',
    'comision_autos_ley', 'comision_autos_full', 'comision_fianzas', 'comision_incendio', 'comision_rc', 'comision_otros',
    'banco', 'tipo_cuenta', 'numero_cuenta', 'ubicacion', 'fecha_cumpleanos'
];

echo "\n--- Verificando columnas de 'usuarios' ---\n";
$todo_ok_usuarios = true;
foreach ($columnas_usuarios_esperadas as $col) {
    $r = $db->query("SHOW COLUMNS FROM usuarios LIKE '$col'");
    if ($r && $r->num_rows > 0) {
        $meta = $r->fetch_assoc();
        echo "[✓] Columna 'usuarios.{$col}' existe (Tipo: {$meta['Type']}).\n";
    } else {
        echo "[X] ERROR: Columna 'usuarios.{$col}' NO EXISTE.\n";
        $todo_ok_usuarios = false;
    }
}

// 3. Verificar Columnas en Tabla 'cotizaciones'
echo "\n--- Verificando columnas de 'cotizaciones' ---\n";
$r = $db->query("SHOW COLUMNS FROM cotizaciones LIKE 'beneficiario'");
if ($r && $r->num_rows > 0) {
    $meta = $r->fetch_assoc();
    echo "[✓] Columna 'cotizaciones.beneficiario' existe (Tipo: {$meta['Type']}).\n";
} else {
    echo "[X] ERROR: Columna 'cotizaciones.beneficiario' NO EXISTE.\n";
}

// 4. Contar Usuarios por Perfil
echo "\n--- Conteo de Usuarios por Perfil ---\n";
$sql_perfiles = "SELECT p.id, p.nombre_perfil, COUNT(u.id) as total 
                 FROM perfiles p 
                 LEFT JOIN usuarios u ON u.perfil_id = p.id 
                 GROUP BY p.id, p.nombre_perfil";
$r_perfiles = $db->query($sql_perfiles);
while ($row = $r_perfiles->fetch_assoc()) {
    echo "  - Perfil ID {$row['id']} [{$row['nombre_perfil']}]: {$row['total']} usuarios.\n";
}

// 5. Verificar Entorno Python y Script ETL
echo "\n--- Verificando motor Python ETL ---\n";
$python_cmd = "python";
$output_version = [];
$return_version = 0;
exec("$python_cmd --version", $output_version, $return_version);

if ($return_version === 0) {
    echo "[✓] Python está disponible: " . implode(" ", $output_version) . "\n";
} else {
    echo "[X] ERROR: Python no se encuentra en el PATH o no está disponible.\n";
}

// 6. Validar Ejecución de ETL con Archivo XLSX
$xlsx_path = 'F:\\Nueva carpeta\\Formulario_Solicitud_de_Codigo2026-04-30_19_59_09.xlsx';
echo "\n--- Probando parsing ETL de: $xlsx_path ---\n";

if (!file_exists($xlsx_path)) {
    echo "[X] ADVERTENCIA: El archivo en $xlsx_path no existe o no se puede leer directamente desde PHP. Probando ruta alternativa en c:\\wamp64...\n";
    // Intentar buscar el archivo en uploads o carpeta temporal
    $xlsx_path = 'uploads/Formulario_Solicitud_de_Codigo2026-04-30_19_59_09.xlsx';
    if (!file_exists($xlsx_path)) {
        $xlsx_path = null;
    }
}

if ($xlsx_path) {
    echo "[i] Archivo origen encontrado en: $xlsx_path. Ejecutando motor ETL...\n";
    $script_path = 'backend/etl_usuarios_import.py';
    $command = "python " . escapeshellarg($script_path) . " " . escapeshellarg($xlsx_path);
    
    $output_etl = [];
    $return_etl = 0;
    exec($command, $output_etl, $return_etl);
    
    $json_res = implode("\n", $output_etl);
    $data_etl = json_decode($json_res, true);
    
    if ($return_etl === 0 && !empty($data_etl) && $data_etl['exito']) {
        echo "[✓] ETL procesó el archivo XLSX exitosamente.\n";
        echo "    - Registros leídos: " . count($data_etl['registros']) . "\n";
        echo "    - Advertencias / Warnings: " . count($data_etl['warnings']) . "\n";
        
        // Muestra de un registro para validar la redirección de Reservas y normalización
        if (count($data_etl['registros']) > 0) {
            $muestra = $data_etl['registros'][0];
            echo "    - Registro de muestra:\n";
            echo "      * Nombre: {$muestra['nombre']} {$muestra['apellido']}\n";
            echo "      * Código PDV: {$muestra['codigo_usuario']}\n";
            echo "      * Email: {$muestra['email']}\n";
            echo "      * Banco Normalizado: {$muestra['banco']}\n";
            echo "      * Tipo Cuenta Normalizado: {$muestra['tipo_cuenta']}\n";
            echo "      * Número Cuenta: {$muestra['numero_cuenta']}\n";
            echo "      * Comisión Fianzas: {$muestra['comision_fianzas']}%\n";
        }
        
        // Verificar cuántos tienen banco Reservas y tipo de cuenta corregidos
        $reservas_corregidos = 0;
        foreach ($data_etl['registros'] as $reg) {
            if ($reg['banco'] === 'Banreservas' && $reg['tipo_cuenta'] === 'Ahorro') {
                $reservas_corregidos++;
            }
        }
        echo "    - Registros normalizados a 'Banreservas' de tipo 'Ahorro': $reservas_corregidos\n";
    } else {
        echo "[X] ERROR en la ejecución del ETL de Python.\n";
        echo "    - Código de retorno: $return_etl\n";
        echo "    - Salida:\n" . substr($json_res, 0, 500) . "\n";
    }
} else {
    echo "[X] ERROR: No se encontró el archivo de origen Formulario_Solicitud_de_Codigo2026-04-30_19_59_09.xlsx para validar.\n";
}

echo "\n=== VERIFICACIÓN FINALIZADA ===\n";
?>
