<?php
/**
 * Verificación Pública de Pólizas via QR
 * MAS QUE FIANZAS - Core Asegurador v3.0
 * 
 * Endpoint público: no requiere autenticación.
 * Devuelve solo estado e información básica para validación del Marbete.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once '../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener info institucional pública
    $empresa_correo = 'info@masquefianzas.com';
    $empresa_telefono = '(829) 629-1952';
    $res_conf = $db->query("SELECT clave_config, valor_config FROM configuracion_sistema WHERE clave_config IN ('EMPRESA_CORREO', 'EMPRESA_TELEFONO')");
    if ($res_conf) {
        while ($row_conf = $res_conf->fetch_assoc()) {
            if ($row_conf['clave_config'] === 'EMPRESA_CORREO' && !empty($row_conf['valor_config'])) {
                $empresa_correo = $row_conf['valor_config'];
            }
            if ($row_conf['clave_config'] === 'EMPRESA_TELEFONO' && !empty($row_conf['valor_config'])) {
                $empresa_telefono = $row_conf['valor_config'];
            }
        }
    }
} catch (Exception $e) {
    $empresa_correo = 'info@masquefianzas.com';
    $empresa_telefono = '(829) 629-1952';
}

$numero = $_GET['n'] ?? '';

if (empty($numero)) {
    echo json_encode([
        "exito" => false,
        "mensaje" => "Número de póliza no proporcionado",
        "empresa" => [
            "correo" => $empresa_correo,
            "telefono" => $empresa_telefono
        ]
    ]);
    exit;
}

try {
    
    $sql = "SELECT 
                p.numero_poliza,
                p.estado,
                p.tipo_seguro,
                p.aseguradora,
                p.perfil_cobertura,
                p.prima_total,
                p.fecha_emision,
                p.fecha_vencimiento,
                p.validada,
                CASE 
                    WHEN c.tipo_cliente = 'persona_natural' THEN CONCAT(c.nombre, ' ', COALESCE(c.apellido, ''))
                    ELSE c.razon_social 
                END as asegurado,
                v.placa,
                v.marca,
                v.modelo,
                v.anio
            FROM polizas p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
            WHERE p.numero_poliza = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $numero);
    $stmt->execute();
    $poliza = $stmt->get_result()->fetch_assoc();
    
    if (!$poliza) {
        echo json_encode([
            "exito" => false,
            "mensaje" => "Póliza no encontrada en nuestros registros",
            "numero" => $numero,
            "empresa" => [
                "correo" => $empresa_correo,
                "telefono" => $empresa_telefono
            ]
        ]);
        exit;
    }
    
    // Calcular si está vigente
    $hoy = new DateTime();
    $vence = $poliza['fecha_vencimiento'] ? new DateTime($poliza['fecha_vencimiento']) : null;
    $vigente = $vence && $hoy <= $vence;
    
    echo json_encode([
        "exito" => true,
        "datos" => [
            "numero_poliza"   => $poliza['numero_poliza'],
            "estado"          => strtoupper($poliza['estado']),
            "validada"        => $poliza['validada'],
            "es_valida"       => $poliza['estado'] === 'activa' && $poliza['validada'] === 'Si',
            "vigente"         => $vigente,
            "asegurado"       => $poliza['asegurado'],
            "tipo_seguro"     => $poliza['tipo_seguro'],
            "aseguradora"     => $poliza['aseguradora'],
            "cobertura"       => $poliza['perfil_cobertura'],
            "vehiculo_placa"  => $poliza['placa'],
            "vehiculo_info"   => trim($poliza['anio'] . ' ' . $poliza['marca'] . ' ' . $poliza['modelo']),
            "fecha_emision"   => $poliza['fecha_emision'],
            "fecha_vencimiento" => $poliza['fecha_vencimiento'],
            "verificado_en"   => date('Y-m-d H:i:s')
        ],
        "empresa" => [
            "correo" => $empresa_correo,
            "telefono" => $empresa_telefono
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "exito" => false,
        "mensaje" => "Error al verificar la póliza",
        "empresa" => [
            "correo" => $empresa_correo ?? 'info@masquefianzas.com',
            "telefono" => $empresa_telefono ?? '(829) 629-1952'
        ]
    ]);
}
