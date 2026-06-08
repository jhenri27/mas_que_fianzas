<?php
/**
 * TEST AUTOCOMPLETAR PDF - PRUEBA UNITARIA
 */
require_once dirname(__FILE__) . '/../config.php';

try {
    echo "=== INICIANDO PRUEBA DE AUTOCOMPLETADO DE PDF ===\n";

    $db = Database::getInstance()->getConnection();

    // 1. Crear plantilla de prueba en la base de datos
    echo "1. Creando plantilla de prueba...\n";
    $nombre = "Test Solicitud - Autocompletar";
    $archivo_base = "Ejemplos de documentos pdf/Solicitud-RD-0004.pdf";
    $ancho_mm = 214.12;
    $alto_mm = 277.96;
    
    // Verificar si ya existe para no duplicar
    $stmt = $db->prepare("SELECT id FROM pdf_plantillas WHERE nombre = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    $plantilla_id = null;
    if ($row = $res->fetch_assoc()) {
        $plantilla_id = $row['id'];
        echo "Plantilla existente encontrada con ID: $plantilla_id\n";
    }
    $stmt->close();

    if (!$plantilla_id) {
        $stmt = $db->prepare("INSERT INTO pdf_plantillas (aseguradora_id, aseguradora_nombre, nombre, archivo_base, tipo_archivo, ancho_mm, alto_mm, estado) VALUES (1, 'Multiseguros', ?, ?, 'pdf', ?, ?, 1)");
        $stmt->bind_param("ssdd", $nombre, $archivo_base, $ancho_mm, $alto_mm);
        if ($stmt->execute()) {
            $plantilla_id = $db->insert_id;
            echo "Plantilla de prueba creada con ID: $plantilla_id\n";
        } else {
            throw new Exception("Error al crear plantilla: " . $db->error);
        }
        $stmt->close();
    }

    // 2. Limpiar y recrear campos de prueba
    echo "2. Creando campos de mapeo de prueba...\n";
    $db->query("DELETE FROM pdf_campos WHERE plantilla_id = $plantilla_id");

    $campos = [
        [
            'variable' => 'cliente.nombre',
            'nombre_campo_pdf' => '',
            'pagina' => 1,
            'pos_x' => 45.0,
            'pos_y' => 55.0,
            'font_size' => 12,
            'font_family' => 'helvetica',
            'color' => '#ff0000',
            'font_weight' => 'bold',
            'alineacion' => 'left',
            'ancho' => 120.0
        ],
        [
            'variable' => 'poliza.total_pagar',
            'nombre_campo_pdf' => '',
            'pagina' => 1,
            'pos_x' => 45.0,
            'pos_y' => 75.0,
            'font_size' => 11,
            'font_family' => 'times-roman',
            'color' => '#0000ff',
            'font_weight' => 'normal',
            'alineacion' => 'left',
            'ancho' => 100.0
        ],
        [
            'variable' => '',
            'nombre_campo_pdf' => 'campo_adicional_test',
            'pagina' => 1,
            'pos_x' => 45.0,
            'pos_y' => 95.0,
            'font_size' => 10,
            'font_family' => 'courier',
            'color' => '#008000',
            'font_weight' => 'bold',
            'alineacion' => 'center',
            'ancho' => 110.0
        ]
    ];

    foreach ($campos as $c) {
        $stmt = $db->prepare("INSERT INTO pdf_campos (plantilla_id, variable, nombre_campo_pdf, pagina, pos_x, pos_y, font_size, font_family, color, font_weight, alineacion, ancho) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiddsssssd", $plantilla_id, $c['variable'], $c['nombre_campo_pdf'], $c['pagina'], $c['pos_x'], $c['pos_y'], $c['font_size'], $c['font_family'], $c['color'], $c['font_weight'], $c['alineacion'], $c['ancho']);
        $stmt->execute();
        $stmt->close();
    }
    echo "Campos de mapeo de prueba creados.\n";

    // 3. Simular autollenado (Ejecutar script de Python)
    echo "3. Simulando datos de autollenado...\n";
    $datos_sistema = [
        'cliente.nombre' => 'PEDRO MARTINEZ',
        'poliza.total_pagar' => 'RD$ 247,800.00'
    ];
    $datos_manuales = [
        'campo_adicional_test' => 'VALOR MANUAL COMPLETADO DESDE EL FORMULARIO INTELIGENTE'
    ];

    // Compilar datos a rellenar
    $final_fields_data = [];
    foreach ($campos as $c) {
        $val = '';
        if (!empty($c['variable'])) {
            $val = $datos_sistema[$c['variable']] ?? '';
        } else {
            $val = $datos_manuales[$c['nombre_campo_pdf']] ?? '';
        }

        $final_fields_data[] = [
            'nombre_campo_pdf' => $c['nombre_campo_pdf'],
            'variable' => $c['variable'],
            'value' => $val,
            'pagina' => $c['pagina'],
            'pos_x' => $c['pos_x'],
            'pos_y' => $c['pos_y'],
            'font_size' => $c['font_size'],
            'font_family' => $c['font_family'],
            'color' => $c['color'],
            'font_weight' => $c['font_weight'],
            'alineacion' => $c['alineacion'],
            'ancho' => $c['ancho']
        ];
    }

    $dir_temp = dirname(__FILE__) . '/../uploads/temp';
    if (!file_exists($dir_temp)) {
        mkdir($dir_temp, 0777, true);
    }
    $temp_json_path = $dir_temp . '/test_fill_' . time() . '.json';
    file_put_contents($temp_json_path, json_encode($final_fields_data, JSON_UNESCAPED_UNICODE));

    $dir_out = dirname(__FILE__) . '/../uploads/pdfs_aseguradoras';
    if (!file_exists($dir_out)) {
        mkdir($dir_out, 0777, true);
    }
    $out_filename = 'test_filled_' . time() . '.pdf';
    $output_path = $dir_out . '/' . $out_filename;

    echo "4. Ejecutando motor de Python...\n";
    $python_exe = 'python';
    $script_path = dirname(__FILE__) . '/../python/pdf_extractor.py';
    $template_full_path = dirname(__FILE__) . '/../../' . $archivo_base;

    $cmd = "$python_exe " . escapeshellarg($script_path) . " fill " 
           . escapeshellarg($template_full_path) . " " 
           . escapeshellarg($output_path) . " " 
           . escapeshellarg($temp_json_path) . " " 
           . escapeshellarg($ancho_mm) . " " 
           . escapeshellarg($alto_mm);

    echo "Comando: $cmd\n";
    $output = shell_exec($cmd);
    echo "Salida de Python:\n$output\n";

    // Limpiar JSON temporal
    if (file_exists($temp_json_path)) {
        unlink($temp_json_path);
    }

    $res_python = json_decode($output, true);
    if ($res_python && isset($res_python['exito']) && $res_python['exito']) {
        echo "✅ PRUEBA EXITOSA: PDF generado en $output_path\n";
        echo "Tamaño del PDF generado: " . filesize($output_path) . " bytes\n";
        
        // Simular logAudit
        echo "5. Verificando log de auditoría...\n";
        logAudit(
            1, // Administrador
            'llenar_pdf',
            'pdf_modeler',
            'autollenado_pdf',
            "TEST: Autocompletado de PDF oficial para plantilla ID: $plantilla_id, archivo: $out_filename",
            'exitoso',
            null,
            'pdf_plantillas',
            $plantilla_id,
            null,
            $final_fields_data
        );
        echo "Log de auditoría insertado con éxito.\n";
    } else {
        throw new Exception("Error en la ejecución de Python: " . ($res_python['mensaje'] ?? $output));
    }

} catch (Exception $e) {
    echo "❌ ERROR EN LA PRUEBA: " . $e->getMessage() . "\n";
}
?>
