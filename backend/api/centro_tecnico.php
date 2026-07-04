<?php
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/PerfilManager.php';
require_once dirname(__DIR__) . '/PolizaManager.php';
require_once dirname(__DIR__) . '/ComisionManager.php';
require_once dirname(__DIR__) . '/AsientoContableManager.php';

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar token de sesión
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
    $bearer_token = trim($matches[1]);
}
if (empty($bearer_token)) {
    $bearer_token = $_GET['token_sesion'] ?? $_POST['token_sesion'] ?? $_REQUEST['token'] ?? $_REQUEST['token_sesion'] ?? null;
}

$usuario_id = null;
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id']) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_temp = Database::getInstance()->getConnection();
    $stmt_tk = $db_temp->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1");
    if ($stmt_tk) {
        $stmt_tk->bind_param("s", $bearer_token);
        $stmt_tk->execute();
        $res_tk = $stmt_tk->get_result();
        if ($row_tk = $res_tk->fetch_assoc()) $usuario_id = (int)$row_tk['usuario_id'];
        $stmt_tk->close();
    }
}

if (php_sapi_name() === 'cli') {
    $usuario_id = 1;
}

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada"]);
    exit;
}

$db = Database::getInstance()->getConnection();

// Obtener datos del usuario
$stmt_check = $db->prepare("SELECT perfil_id, username FROM usuarios WHERE id = ? LIMIT 1");
$stmt_check->bind_param("i", $usuario_id);
$stmt_check->execute();
$usr_data = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

$perfil_id_usr = (int)($usr_data['perfil_id'] ?? 0);
$es_admin = ($usuario_id === 1 || $perfil_id_usr === 1 || tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'PER_GESTIONAR'));

$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? 'listar_solicitudes';

try {
    switch ($action) {
        case 'listar_polizas':
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $query = "SELECT p.id, p.numero_poliza, p.aseguradora, p.prima_total, p.emitida_por,
                             c.nombre as cliente_nombre, v.placa as vehiculo_placa 
                      FROM polizas p 
                      LEFT JOIN clientes c ON p.cliente_id = c.id 
                      LEFT JOIN vehiculos v ON p.vehiculo_id = v.id";
            if (!empty($search)) {
                $query .= " WHERE p.numero_poliza LIKE ? OR c.nombre LIKE ? OR v.placa LIKE ?";
            }
            $query .= " ORDER BY p.id DESC LIMIT 100";

            $stmt = $db->prepare($query);
            if (!empty($search)) {
                $searchParam = "%" . $search . "%";
                $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            
            $polizas = [];
            while ($row = $res->fetch_assoc()) {
                $polizas[] = [
                    "id" => (int)$row['id'],
                    "numero_poliza" => $row['numero_poliza'],
                    "aseguradora" => $row['aseguradora'],
                    "prima_total" => (float)$row['prima_total'],
                    "cliente_nombre" => $row['cliente_nombre'] ?? 'N/D',
                    "vehiculo_placa" => $row['vehiculo_placa'] ?? 'N/D',
                    "emitida_por" => (int)$row['emitida_por']
                ];
            }
            $stmt->close();
            echo json_encode(["exito" => true, "datos" => $polizas]);
            break;

        case 'obtener_poliza':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception("ID de póliza requerido");

            $pm = new PolizaManager();
            $poliza = $pm->obtenerPolizaDetalle($id);

            if (!$poliza) {
                throw new Exception("Póliza no encontrada");
            }

            echo json_encode(["exito" => true, "datos" => $poliza]);
            break;

        case 'simular_impacto':
            // Recibe poliza_id y prima_total_nueva
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) throw new Exception("Datos de simulación inválidos");

            $polizaId = (int)($data['poliza_id'] ?? 0);
            $nuevaPrimaTotal = (float)($data['prima_total_nueva'] ?? 0);

            if (!$polizaId || $nuevaPrimaTotal <= 0) {
                throw new Exception("Parámetros incompletos para simulación");
            }

            // Obtener póliza original
            $stmt = $db->prepare("SELECT prima_total FROM polizas WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $polizaId);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$p) throw new Exception("Póliza no encontrada");

            $origTotal = (float)$p['prima_total'];
            $origItbis = $origTotal * 0.16; // 16% ISC
            $origNeta = $origTotal - $origItbis;

            $newTotal = $nuevaPrimaTotal;
            $newItbis = $newTotal * 0.16;
            $newNeta = $newTotal - $newItbis;

            $diffTotal = $newTotal - $origTotal;
            $diffNeta = $newNeta - $origNeta;
            $diffItbis = $newItbis - $origItbis;

            // Partida Doble
            $lineas = [];
            
            // Cuenta 1: Primas por Cobrar - Vigentes (1.1.02.01)
            $tipo1 = ($diffTotal >= 0) ? 'debito' : 'credito';
            $lineas[] = [
                "cuenta" => "1.1.02.01",
                "nombre" => "Primas por Cobrar - Vigentes",
                "tipo" => $tipo1,
                "original" => $origTotal,
                "nuevo" => $newTotal,
                "impacto" => abs($diffTotal)
            ];

            // Cuenta 2: Primas Netas de Seguros - Automóviles (4.1.01.01)
            $tipo2 = ($diffNeta >= 0) ? 'credito' : 'debito';
            $lineas[] = [
                "cuenta" => "4.1.01.01",
                "nombre" => "Primas Netas de Seguros - Automóviles",
                "tipo" => $tipo2,
                "original" => $origNeta,
                "nuevo" => $newNeta,
                "impacto" => abs($diffNeta)
            ];

            // Cuenta 3: ITBIS por Pagar (2.1.03.01)
            $tipo3 = ($diffItbis >= 0) ? 'credito' : 'debito';
            $lineas[] = [
                "cuenta" => "2.1.03.01",
                "nombre" => "ITBIS por Pagar",
                "tipo" => $tipo3,
                "original" => $origItbis,
                "nuevo" => $newItbis,
                "impacto" => abs($diffItbis)
            ];

            echo json_encode([
                "exito" => true,
                "datos" => [
                    "poliza_id" => $polizaId,
                    "cambio_prima" => $diffTotal,
                    "simulacion_asiento" => $lineas
                ]
            ]);
            break;

        case 'crear_solicitud':
            // Safely add categoria_cambio column only if it doesn't exist yet
            $colCheck = $db->query("SHOW COLUMNS FROM polizas_ajustes_solicitudes LIKE 'categoria_cambio'");
            if ($colCheck && $colCheck->num_rows === 0) {
                $db->query("ALTER TABLE polizas_ajustes_solicitudes ADD COLUMN categoria_cambio VARCHAR(50) DEFAULT 'financiero' AFTER poliza_id");
            }

            $polizaId = (int)($_POST['poliza_id'] ?? 0);
            $categoriaCambio = trim($_POST['categoria_cambio'] ?? 'financiero');
            $justificacion = trim($_POST['justificacion'] ?? '');
            
            if (!$polizaId || empty($justificacion)) {
                throw new Exception("Justificación y Póliza son obligatorios");
            }

            $valoresNuevosRaw = $_POST['valores_nuevos'] ?? '{}';
            $camposNuevos = json_decode($valoresNuevosRaw, true);
            if(!is_array($camposNuevos)) $camposNuevos = [];

            if($categoriaCambio === 'financiero') {
                $camposNuevos['aseguradora'] = trim($_POST['aseguradora'] ?? '');
                $camposNuevos['prima_total'] = (float)($_POST['prima_total'] ?? 0);
                if (empty($camposNuevos['aseguradora']) || $camposNuevos['prima_total'] <= 0) {
                    throw new Exception("Datos de ajuste (Aseguradora y Prima) incompletos");
                }
            } else {
                if(empty($camposNuevos)) {
                     throw new Exception("No se recibieron los nuevos valores a modificar");
                }
            }

            // Obtener campos originales (dependiendo de la categoría)
            $camposOriginales = [];
            if ($categoriaCambio === 'financiero') {
                $stmt = $db->prepare("SELECT aseguradora, prima_total FROM polizas WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $polizaId);
                $stmt->execute();
                $orig = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$orig) throw new Exception("Póliza no encontrada");
                $camposOriginales = [
                    "aseguradora" => $orig['aseguradora'],
                    "prima_total" => (float)$orig['prima_total']
                ];
            } else if ($categoriaCambio === 'vehiculo') {
                $stmt = $db->prepare("SELECT v.placa, v.marca as vehiculo_marca, v.chasis FROM polizas p JOIN vehiculos v ON p.vehiculo_id = v.id WHERE p.id = ? LIMIT 1");
                $stmt->bind_param("i", $polizaId);
                $stmt->execute();
                $orig = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if($orig) {
                    $camposOriginales = [
                        "placa" => $orig['placa'],
                        "marca" => $orig['vehiculo_marca'],
                        "chasis" => $orig['chasis']
                    ];
                }
            } else if ($categoriaCambio === 'cliente') {
                $stmt = $db->prepare("SELECT c.nombre, c.documento FROM polizas p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ? LIMIT 1");
                $stmt->bind_param("i", $polizaId);
                $stmt->execute();
                $orig = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if($orig) {
                    $camposOriginales = [
                        "nombre" => $orig['nombre'],
                        "documento" => $orig['documento']
                    ];
                }
            } else if ($categoriaCambio === 'intermediario') {
                // Dummy values since schema might differ
                $camposOriginales = [
                    "nombre" => "N/D",
                    "codigo" => "N/D"
                ];
            }

            // Procesar Soporte Documental (File Upload)
            $rutaArchivo = null;
            if (isset($_FILES['soporte_file']) && $_FILES['soporte_file']['error'] === UPLOAD_ERR_OK) {
                $dirDestino = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'centro_tecnico';
                if (!file_exists($dirDestino)) {
                    @mkdir($dirDestino, 0777, true);
                }

                $ext = pathinfo($_FILES['soporte_file']['name'], PATHINFO_EXTENSION);
                $nombreArchivo = 'soporte_' . $polizaId . '_' . time() . '.' . $ext;
                $rutaCompleta = $dirDestino . DIRECTORY_SEPARATOR . $nombreArchivo;

                // Limpiar cualquier error previo de PHP para capturar el de move_uploaded_file si falla
                error_clear_last();
                if (@move_uploaded_file($_FILES['soporte_file']['tmp_name'], $rutaCompleta)) {
                    $rutaArchivo = '/uploads/centro_tecnico/' . $nombreArchivo;
                } else {
                    $err = error_get_last();
                    $msgDetalle = isset($err['message']) ? ': ' . $err['message'] : '';
                    throw new Exception("Error al subir archivo soporte documental" . $msgDetalle);
                }
            }

            $camposOrigStr = json_encode($camposOriginales);
            $camposNuevStr = json_encode($camposNuevos);

            $stmtIns = $db->prepare("INSERT INTO polizas_ajustes_solicitudes 
                (poliza_id, categoria_cambio, usuario_solicita, campos_originales, campos_nuevos, justificacion, soporte_documental, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')");
            $stmtIns->bind_param("isissss", $polizaId, $categoriaCambio, $usuario_id, $camposOrigStr, $camposNuevStr, $justificacion, $rutaArchivo);
            
            if ($stmtIns->execute()) {
                echo json_encode(["exito" => true, "mensaje" => "Solicitud de ajuste registrada en estado Pendiente para aprobación de Administradores."]);
            } else {
                throw new Exception("Error al insertar solicitud: " . $stmtIns->error);
            }
            $stmtIns->close();
            break;

        case 'listar_solicitudes':
            $estadoFiltro = $_GET['estado'] ?? '';
            $categoriaFiltro = $_GET['categoria'] ?? '';
            $query = "SELECT s.*, p.numero_poliza, u.nombre as solicita_nombre, a.nombre as aprueba_nombre 
                      FROM polizas_ajustes_solicitudes s
                      JOIN polizas p ON s.poliza_id = p.id
                      JOIN usuarios u ON s.usuario_solicita = u.id
                      LEFT JOIN usuarios a ON s.usuario_aprueba = a.id";
            
            $conditions = [];
            $params = [];
            $types = "";
            
            if (!empty($estadoFiltro)) {
                $conditions[] = "s.estado = ?";
                $params[] = $estadoFiltro;
                $types .= "s";
            }
            if (!empty($categoriaFiltro)) {
                $conditions[] = "s.categoria_cambio = ?";
                $params[] = $categoriaFiltro;
                $types .= "s";
            }
            
            if (count($conditions) > 0) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            $query .= " ORDER BY s.id DESC";

            $stmt = $db->prepare($query);
            if (count($params) > 0) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            $solicitudes = [];
            while ($row = $res->fetch_assoc()) {
                $solicitudes[] = [
                    "id" => (int)$row['id'],
                    "poliza_id" => (int)$row['poliza_id'],
                    "numero_poliza" => $row['numero_poliza'],
                    "categoria_cambio" => $row['categoria_cambio'] ?? 'financiero',
                    "solicita_nombre" => $row['solicita_nombre'],
                    "fecha_solicitud" => $row['fecha_solicitud'],
                    "campos_originales" => json_decode($row['campos_originales'], true),
                    "campos_nuevos" => json_decode($row['campos_nuevos'], true),
                    "justificacion" => $row['justificacion'],
                    "soporte_documental" => $row['soporte_documental'],
                    "estado" => $row['estado'],
                    "aprueba_nombre" => $row['aprueba_nombre'] ?? '-',
                    "fecha_resolucion" => $row['fecha_resolucion'] ?? '-',
                    "motivo_resolucion" => $row['motivo_resolucion'] ?? '-'
                ];
            }
            $stmt->close();
            echo json_encode(["exito" => true, "datos" => $solicitudes]);
            break;

        case 'resolver_solicitud':
            // Solo Administrador
            if (!$es_admin) {
                throw new Exception("Acceso denegado: solo Administradores pueden aprobar o rechazar ajustes");
            }

            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data) throw new Exception("Datos de resolución inválidos");

            $solicitudId = (int)($data['id'] ?? 0);
            $nuevoEstado = trim($data['estado'] ?? '');
            $motivo = trim($data['motivo_resolucion'] ?? '');

            if (!$solicitudId || !in_array($nuevoEstado, ['aprobada', 'rechazada'])) {
                throw new Exception("Parámetros de resolución incompletos");
            }

            $db->begin_transaction();

            // Obtener solicitud
            $stmt = $db->prepare("SELECT * FROM polizas_ajustes_solicitudes WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $solicitudId);
            $stmt->execute();
            $solicitud = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$solicitud) throw new Exception("Solicitud de ajuste no encontrada");
            if ($solicitud['estado'] !== 'pendiente') throw new Exception("Esta solicitud ya ha sido resuelta");

            $polizaId = (int)$solicitud['poliza_id'];
            $camposNuevos = json_decode($solicitud['campos_nuevos'], true);
            $camposOriginales = json_decode($solicitud['campos_originales'], true);

            if ($nuevoEstado === 'aprobada') {
                // 1. Aplicar cambios a la Póliza
                $nuevaPrimaTotal = (float)$camposNuevos['prima_total'];
                $nuevaAseguradora = $camposNuevos['aseguradora'];

                $nuevaItbis = $nuevaPrimaTotal * 0.16;
                $nuevaNeta = $nuevaPrimaTotal - $nuevaItbis;

                $stmtUpd = $db->prepare("UPDATE polizas 
                    SET prima_total = ?, prima_neta = ?, itbis = ?, aseguradora = ? 
                    WHERE id = ?");
                $stmtUpd->bind_param("dddsi", $nuevaPrimaTotal, $nuevaNeta, $nuevaItbis, $nuevaAseguradora, $polizaId);
                if (!$stmtUpd->execute()) throw new Exception("Error al actualizar la póliza: " . $stmtUpd->error);
                $stmtUpd->close();

                // 2. Recalcular Comisiones
                // Eliminar comisiones pendientes anteriores
                $db->query("DELETE FROM comisiones_poliza WHERE poliza_id = $polizaId AND estado_pago = 'pendiente'");
                
                // Obtener emisor original de la póliza
                $stmtEmisor = $db->prepare("SELECT emitida_por FROM polizas WHERE id = ? LIMIT 1");
                $stmtEmisor->bind_param("i", $polizaId);
                $stmtEmisor->execute();
                $emisorRes = $stmtEmisor->get_result()->fetch_assoc();
                $stmtEmisor->close();
                $emisorId = (int)($emisorRes['emitida_por'] ?? $usuario_id);

                $cm = new ComisionManager();
                $cm->calcularYRegistrarComisiones($polizaId, $emisorId, $nuevaNeta);

                // 3. Registrar Asiento Contable del ajuste
                $diffTotal = $nuevaPrimaTotal - (float)$camposOriginales['prima_total'];
                $diffNeta = $nuevaNeta - ((float)$camposOriginales['prima_total'] * 0.84);
                $diffItbis = $nuevaItbis - ((float)$camposOriginales['prima_total'] * 0.16);

                $acm = new AsientoContableManager();
                $refAsiento = $acm->registrarAsiento([
                    'descripcion' => "Ajuste excepcional póliza ID $polizaId - Motivo: {$solicitud['justificacion']}",
                    'modulo' => 'polizas',
                    'ref_id' => $polizaId,
                    'ref_tipo' => 'poliza_ajuste',
                    'user_id' => $usuario_id,
                    'lineas' => [
                        ['cuenta' => '1.1.02.01', 'nombre' => 'Primas por Cobrar - Vigentes', 'tipo' => ($diffTotal >= 0 ? 'debito' : 'credito'), 'monto' => abs($diffTotal), 'glosa' => 'Ajuste comercial prima total'],
                        ['cuenta' => '4.1.01.01', 'nombre' => 'Primas Netas de Seguros - Automóviles', 'tipo' => ($diffNeta >= 0 ? 'credito' : 'debito'), 'monto' => abs($diffNeta), 'glosa' => 'Ajuste ingreso prima neta'],
                        ['cuenta' => '2.1.03.01', 'nombre' => 'ITBIS por Pagar', 'tipo' => ($diffItbis >= 0 ? 'credito' : 'debito'), 'monto' => abs($diffItbis), 'glosa' => 'Ajuste ITBIS 16%']
                    ]
                ]);

                if (!$refAsiento['exito']) {
                    throw new Exception("Error al registrar asiento contable: " . $refAsiento['mensaje']);
                }

                $asientoId = $refAsiento['id'];

                // 4. Actualizar solicitud
                $stmtRes = $db->prepare("UPDATE polizas_ajustes_solicitudes 
                    SET estado = 'aprobada', usuario_aprueba = ?, fecha_resolucion = NOW(), motivo_resolucion = ?, asiento_ajuste_id = ? 
                    WHERE id = ?");
                $stmtRes->bind_param("isii", $usuario_id, $motivo, $asientoId, $solicitudId);
                $stmtRes->execute();
                $stmtRes->close();

                // 5. Bitácora inmutable
                $descAudit = "Aprobó ajuste excepcional para póliza ID $polizaId. Asiento contable registrado: {$refAsiento['numero']}.";
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/D';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'N/D';
                
                $sqlAudit = "INSERT INTO auditoria_accesos 
                             (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo)
                             VALUES (?, 'configuracion', 'update', ?, ?, ?, ?, ?)";
                $stmtAudit = $db->prepare($sqlAudit);
                $valAnt = json_encode($camposOriginales);
                $valNue = json_encode($camposNuevos);
                $stmtAudit->bind_param("isssss", $usuario_id, $descAudit, $ip, $ua, $valAnt, $valNue);
                $stmtAudit->execute();
                $stmtAudit->close();

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Solicitud aprobada y cambios aplicados con impacto contable exitoso."]);
            } else {
                // Rechazada
                $stmtRes = $db->prepare("UPDATE polizas_ajustes_solicitudes 
                    SET estado = 'rechazada', usuario_aprueba = ?, fecha_resolucion = NOW(), motivo_resolucion = ? 
                    WHERE id = ?");
                $stmtRes->bind_param("isi", $usuario_id, $motivo, $solicitudId);
                $stmtRes->execute();
                $stmtRes->close();

                // Bitácora
                $descAudit = "Rechazó ajuste excepcional para póliza ID $polizaId. Motivo: $motivo";
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/D';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'N/D';
                
                $sqlAudit = "INSERT INTO auditoria_accesos 
                             (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo)
                             VALUES (?, 'configuracion', 'update', ?, ?, ?, ?, NULL)";
                $stmtAudit = $db->prepare($sqlAudit);
                $valAnt = json_encode($camposOriginales);
                $stmtAudit->bind_param("issss", $usuario_id, $descAudit, $ip, $ua, $valAnt);
                $stmtAudit->execute();
                $stmtAudit->close();

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Solicitud de ajuste rechazada."]);
            }
            break;

        case 'listar_reglas':
            $res = $db->query("SELECT * FROM reglas_negocio ORDER BY categoria ASC, codigo ASC");
            $reglas = [];
            while ($row = $res->fetch_assoc()) {
                $reglas[] = [
                    "id" => (int)$row['id'],
                    "codigo" => $row['codigo'],
                    "nombre" => $row['nombre'],
                    "categoria" => $row['categoria'],
                    "relaciones" => $row['relaciones'],
                    "mejores_practicas" => $row['mejores_practicas'],
                    "descripcion" => $row['descripcion'],
                    "valor_configurado" => $row['valor_configurado'],
                    "estado" => $row['estado']
                ];
            }
            echo json_encode(["exito" => true, "datos" => $reglas]);
            break;

        case 'guardar_regla':
            if (!$es_admin) {
                throw new Exception("Acceso denegado: solo Administradores pueden editar reglas de negocio");
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data || !isset($data['id']) || !isset($data['valor_configurado'])) {
                throw new Exception("Datos incompletos");
            }

            $id = (int)$data['id'];
            $valor = trim($data['valor_configurado']);
            $estado = trim($data['estado'] ?? 'activo');

            if (empty($valor)) throw new Exception("El valor no puede estar vacío");

            $db->begin_transaction();

            $stmt = $db->prepare("SELECT * FROM reglas_negocio WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $prev = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$prev) throw new Exception("Regla no encontrada");

            $stmtUpd = $db->prepare("UPDATE reglas_negocio SET valor_configurado = ?, estado = ?, modificado_por = ? WHERE id = ?");
            $stmtUpd->bind_param("ssii", $valor, $estado, $usuario_id, $id);
            if (!$stmtUpd->execute()) throw new Exception("Error al actualizar regla");
            $stmtUpd->close();

            // Auditoría
            $descAudit = "Modificó regla de negocio {$prev['codigo']}. Nuevo valor: $valor";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/D';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'N/D';
            
            $sqlAudit = "INSERT INTO auditoria_accesos 
                         (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo)
                         VALUES (?, 'configuracion', 'update', ?, ?, ?, ?, ?)";
            $stmtAudit = $db->prepare($sqlAudit);
            $valAnt = $prev['valor_configurado'];
            $stmtAudit->bind_param("issssss", $usuario_id, $descAudit, $ip, $ua, $valAnt, $valor);
            $stmtAudit->execute();
            $stmtAudit->close();

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Regla de negocio actualizada exitosamente."]);
            break;

        case 'descargar_pdf_solicitud':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception("ID de solicitud requerido");
            
            $stmt = $db->prepare("SELECT s.*, p.numero_poliza, u.nombre as solicita_nombre, a.nombre as aprueba_nombre 
                                  FROM polizas_ajustes_solicitudes s 
                                  JOIN polizas p ON s.poliza_id = p.id 
                                  JOIN usuarios u ON s.usuario_solicita = u.id 
                                  LEFT JOIN usuarios a ON s.usuario_aprueba = a.id 
                                  WHERE s.id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $solicitud = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$solicitud) throw new Exception("Solicitud no encontrada");

            // Clean the output buffer to avoid JSON headers contaminating the PDF stream
            while (ob_get_level()) ob_end_clean();
            // Suppress deprecation warnings (utf8_decode in PHP 8.2) that would corrupt PDF binary
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);

            // Set FPDF font path BEFORE loading so FPDF2 resolves fonts at load time
            if (!defined('FPDF_FONTPATH')) {
                define('FPDF_FONTPATH', dirname(__DIR__) . '/libs/fpdf/font/');
            }

            // Load FPDF
            if (!class_exists('FPDF')) {
                @require_once dirname(__DIR__) . '/libs/fpdf/fpdf.php';
            }

            if (!class_exists('FPDF') && !class_exists('TCPDF')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['exito' => false, 'mensaje' => 'Libreria PDF no disponible en el servidor.']);
                exit;
            }

            $campoLabel = [
                'placa'       => 'Placa',
                'marca'       => 'Marca/Modelo',
                'chasis'      => 'Chasis',
                'aseguradora' => 'Aseguradora',
                'prima_total' => 'Prima Total',
                'nombre'      => 'Nombre',
                'documento'   => 'Documento',
                'codigo'      => 'Codigo',
            ];

            // Helper: safe string for FPDF (latin1)
            $s = function($str) { return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)$str); };

            $orig = json_decode($solicitud['campos_originales'] ?? '{}', true) ?: [];
            $nuev = json_decode($solicitud['campos_nuevos']     ?? '{}', true) ?: [];

            $pdf = new FPDF();
            $pdf->SetAutoPageBreak(true, 20);
            $pdf->AddPage();


            // ----- HEADER -----
            $pdf->SetFillColor(79, 70, 229);
            $pdf->Rect(0, 0, 210, 28, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->SetXY(10, 6);
            $pdf->Cell(0, 10, $s('BITACORA AUDITORIAL DE AJUSTE'), 0, 1, 'C');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetX(10);
            $pdf->Cell(0, 7, 'MAS QUE FIANZAS - Centro Tecnico de Seguros', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(8);

            // ----- POLIZA INFO -----
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->Cell(0, 8, $s('Datos de la Poliza'), 0, 1, 'L', true);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Ln(2);
            $pdf->Cell(50, 7, $s('Poliza N:'), 0, 0);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(0, 7, $s($solicitud['numero_poliza']), 0, 1);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Cell(50, 7, $s('Categoria:'), 0, 0);
            $pdf->Cell(0, 7, $s(strtoupper($solicitud['categoria_cambio'] ?? 'FINANCIERO')), 0, 1);
            $pdf->Cell(50, 7, $s('Estado:'), 0, 0);
            $pdf->Cell(0, 7, $s(strtoupper($solicitud['estado'])), 0, 1);
            $pdf->Ln(4);

            // ----- NOFTRAB REGISTRO -----
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->Cell(0, 8, $s('Registro de Solicitud (NOFTRAB)'), 0, 1, 'L', true);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Ln(2);
            $pdf->Cell(50, 7, $s('Solicitado por:'), 0, 0);
            $pdf->Cell(0, 7, $s($solicitud['solicita_nombre']), 0, 1);
            $pdf->Cell(50, 7, $s('Fecha/Hora:'), 0, 0);
            $pdf->Cell(0, 7, $s($solicitud['fecha_solicitud']), 0, 1);
            $pdf->Cell(50, 7, $s('Justificacion:'), 0, 0);
            $pdf->MultiCell(0, 7, $s($solicitud['justificacion']), 0, 'L');
            $pdf->Ln(4);

            // ----- VALORES -----
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->Cell(0, 8, $s('Valores Anteriores vs. Valores Nuevos'), 0, 1, 'L', true);
            $pdf->Ln(2);

            // Table header
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetFillColor(79, 70, 229);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(60, 7, $s('Campo'), 1, 0, 'C', true);
            $pdf->Cell(60, 7, $s('Valor Anterior'), 1, 0, 'C', true);
            $pdf->Cell(60, 7, $s('Valor Nuevo'), 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            foreach ($nuev as $k => $v) {
                $label = $campoLabel[$k] ?? ucfirst($k);
                $pdf->Cell(60, 7, $s($label), 1, 0);
                $pdf->Cell(60, 7, $s($orig[$k] ?? 'N/A'), 1, 0);
                $pdf->Cell(60, 7, $s($v), 1, 1);
            }
            $pdf->Ln(4);

            // ----- RESOLUCION -----
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->Cell(0, 8, $s('Resolucion'), 0, 1, 'L', true);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Ln(2);
            $pdf->Cell(50, 7, $s('Estado:'), 0, 0);
            $pdf->Cell(0, 7, $s(strtoupper($solicitud['estado'])), 0, 1);
            $pdf->Cell(50, 7, $s('Resuelto por:'), 0, 0);
            $pdf->Cell(0, 7, $s($solicitud['aprueba_nombre'] ?? 'Pendiente de validacion'), 0, 1);
            $pdf->Cell(50, 7, $s('Fecha Resolucion:'), 0, 0);
            $pdf->Cell(0, 7, $s($solicitud['fecha_resolucion'] ?? 'N/A'), 0, 1);
            $pdf->Cell(50, 7, $s('Motivo:'), 0, 0);
            $pdf->MultiCell(0, 7, $s($solicitud['motivo_resolucion'] ?? 'N/A'), 0, 'L');

            // ----- FOOTER -----
            $pdf->Ln(8);
            $pdf->SetFont('Helvetica', 'I', 8);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->Cell(0, 6, $s('Documento generado el ' . date('d/m/Y H:i:s') . ' - MAS QUE FIANZAS Sistema Asegurador v3.0'), 0, 1, 'C');

            // Output the PDF inline
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="bitacora_' . $id . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            $pdf->Output('I', 'bitacora_' . $id . '.pdf');
            exit;

        case 'crear_solicitud_regla':
            $tipo_solicitud = trim($_POST['tipo_solicitud'] ?? 'creacion');
            $regla_id = isset($_POST['regla_id']) && !empty($_POST['regla_id']) ? (int)$_POST['regla_id'] : null;
            $codigo = trim($_POST['codigo'] ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $categoria = trim($_POST['categoria'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $valor_configurado = trim($_POST['valor_configurado'] ?? '');
            $relaciones = trim($_POST['relaciones'] ?? '[]');
            $mejores_practicas = trim($_POST['mejores_practicas'] ?? '');
            $justificacion = trim($_POST['justificacion'] ?? '');

            if (empty($codigo) || empty($nombre) || empty($categoria) || empty($justificacion)) {
                throw new Exception("Código, Nombre, Categoría y Justificación son requeridos");
            }

            // Clasificación automática VAF o VAFF
            $categoria_clean = strtolower(trim($categoria));
            if (in_array($categoria_clean, ['cobros', 'ventas', 'comisiones'])) {
                $tipo_validacion = 'VAFF';
            } else {
                $tipo_validacion = 'VAF';
            }

            $stmtIns = $db->prepare("INSERT INTO reglas_negocio_solicitudes 
                (regla_id, tipo_solicitud, codigo, nombre, categoria, descripcion, valor_configurado, relaciones, mejores_practicas, estado, tipo_validacion, usuario_solicita, justificacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)");
            $stmtIns->bind_param("isssssssssis", $regla_id, $tipo_solicitud, $codigo, $nombre, $categoria, $descripcion, $valor_configurado, $relaciones, $mejores_practicas, $tipo_validacion, $usuario_id, $justificacion);
            
            if ($stmtIns->execute()) {
                // Registrar log de auditoría
                $detalles_log = "Solicitud de regla de negocio registrada ($tipo_solicitud). Tipo de validación: $tipo_validacion. Justificación: $justificacion";
                $db->query("INSERT INTO auditoria_accesos (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo) 
                            VALUES ($usuario_id, 'configuracion', 'create', '" . $db->real_escape_string($detalles_log) . "', '" . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . "', '" . $db->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'N/D') . "', NULL, NULL)");
                
                echo json_encode(["exito" => true, "mensaje" => "Solicitud de regla de negocio registrada en estado Pendiente para verificación [$tipo_validacion]."]);
            } else {
                throw new Exception("Error al guardar solicitud de regla: " . $stmtIns->error);
            }
            $stmtIns->close();
            break;

        case 'listar_solicitudes_reglas':
            $query = "SELECT r.*, u.nombre as solicita_nombre, a.nombre as aprueba_nombre 
                      FROM reglas_negocio_solicitudes r
                      JOIN usuarios u ON r.usuario_solicita = u.id
                      LEFT JOIN usuarios a ON r.usuario_aprueba = a.id
                      ORDER BY r.id DESC";
            $res = $db->query($query);
            $solicitudes = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['regla_id'] = $row['regla_id'] ? (int)$row['regla_id'] : null;
                $solicitudes[] = $row;
            }
            echo json_encode(["exito" => true, "datos" => $solicitudes]);
            break;

        case 'resolver_solicitud_regla':
            if (!$es_admin) {
                throw new Exception("Acceso denegado: solo Administradores pueden resolver solicitudes de reglas");
            }
            $solicitud_id = (int)($_POST['solicitud_id'] ?? 0);
            $estado_nuevo = trim($_POST['estado'] ?? ''); // 'aprobada' o 'rechazada'
            $motivo_resolucion = trim($_POST['motivo_resolucion'] ?? '');

            if (!$solicitud_id || !in_array($estado_nuevo, ['aprobada', 'rechazada'])) {
                throw new Exception("ID de solicitud y estado ('aprobada' o 'rechazada') válidos requeridos");
            }
            if (empty($motivo_resolucion)) {
                throw new Exception("El motivo de resolución es obligatorio");
            }

            // Obtener la solicitud
            $stmt = $db->prepare("SELECT * FROM reglas_negocio_solicitudes WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $solicitud_id);
            $stmt->execute();
            $sol = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$sol) throw new Exception("Solicitud de regla no encontrada");
            if ($sol['estado'] !== 'pendiente') throw new Exception("Esta solicitud ya fue resuelta anteriormente");

            $db->begin_transaction();

            // Actualizar estado de la solicitud
            $stmtUp = $db->prepare("UPDATE reglas_negocio_solicitudes 
                                    SET estado = ?, usuario_aprueba = ?, fecha_resolucion = NOW(), motivo_resolucion = ? 
                                    WHERE id = ?");
            $stmtUp->bind_param("sisi", $estado_nuevo, $usuario_id, $motivo_resolucion, $solicitud_id);
            $stmtUp->execute();
            $stmtUp->close();

            if ($estado_nuevo === 'aprobada') {
                if ($sol['tipo_solicitud'] === 'creacion') {
                    // Insertar en la tabla productiva
                    $stmtProd = $db->prepare("INSERT INTO reglas_negocio 
                        (codigo, nombre, categoria, relaciones, mejores_practicas, descripcion, valor_configurado, estado, modificado_por) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', ?)");
                    $stmtProd->bind_param("sssssssi", $sol['codigo'], $sol['nombre'], $sol['categoria'], $sol['relaciones'], $sol['mejores_practicas'], $sol['descripcion'], $sol['valor_configurado'], $usuario_id);
                    $stmtProd->execute();
                    $stmtProd->close();
                } else if ($sol['tipo_solicitud'] === 'modificacion') {
                    $regla_id = (int)$sol['regla_id'];
                    // Actualizar en la tabla productiva
                    $stmtProd = $db->prepare("UPDATE reglas_negocio 
                        SET codigo = ?, nombre = ?, categoria = ?, relaciones = ?, mejores_practicas = ?, descripcion = ?, valor_configurado = ?, modificado_por = ? 
                        WHERE id = ?");
                    $stmtProd->bind_param("sssssssii", $sol['codigo'], $sol['nombre'], $sol['categoria'], $sol['relaciones'], $sol['mejores_practicas'], $sol['descripcion'], $sol['valor_configurado'], $usuario_id, $regla_id);
                    $stmtProd->execute();
                    $stmtProd->close();
                }
            }

            $db->commit();

            // Inscribir log de auditoría
            $detalles_log = "Solicitud de regla #$solicitud_id resuelta como $estado_nuevo por usuario $usuario_id. Motivo: $motivo_resolucion";
            $db->query("INSERT INTO auditoria_accesos 
                        (usuario_id, modulo_accedido, tipo_evento, descripcion_evento, direccion_ip, navegador_user_agent, valor_anterior, valor_nuevo)
                        VALUES ($usuario_id, 'configuracion', 'update', '" . $db->real_escape_string($detalles_log) . "', '" . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . "', '" . $db->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'N/D') . "', '" . $db->real_escape_string(json_encode($sol['estado'])) . "', '" . $db->real_escape_string(json_encode($estado_nuevo)) . "')");

            // Inscribir ajuste en historial_ajustes
            $db->query("INSERT INTO historial_ajustes (usuario_id, modulo_afectado, tabla_afectada, registro_id, valor_anterior, valor_nuevo, justificacion, direccion_ip) 
                       VALUES ($usuario_id, 'configuracion', 'reglas_negocio', $solicitud_id, '" . $db->real_escape_string(json_encode($sol['estado'])) . "', '" . $db->real_escape_string(json_encode($estado_nuevo)) . "', '" . $db->real_escape_string($motivo_resolucion) . "', '" . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . "')");

            echo json_encode(["exito" => true, "mensaje" => "La solicitud ha sido " . ($estado_nuevo === 'aprobada' ? 'APROBADA' : 'RECHAZADA') . " y registrada exitosamente."]);
            break;

        case 'enviar_correo_solicitud':
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $correoDestino = trim($_POST['correo'] ?? $_GET['correo'] ?? '');
            if (!$id) throw new Exception("ID requerido");
            if (empty($correoDestino)) $correoDestino = 'ahenriquezmarte@gmail.com';
            
            // Validate email format
            if (!filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Dirección de correo no válida: $correoDestino");
            }

            // Fetch policy details for the email body
            $stmt = $db->prepare("SELECT s.id, s.estado, s.justificacion, s.campos_originales, s.campos_nuevos,
                                         s.fecha_solicitud, s.categoria_cambio,
                                         p.numero_poliza, u.nombre as solicita_nombre,
                                         a.nombre as aprueba_nombre, s.motivo_resolucion, s.fecha_resolucion
                                  FROM polizas_ajustes_solicitudes s 
                                  JOIN polizas p ON s.poliza_id = p.id 
                                  JOIN usuarios u ON s.usuario_solicita = u.id 
                                  LEFT JOIN usuarios a ON s.usuario_aprueba = a.id
                                  WHERE s.id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $solicitud = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            if (!$solicitud) throw new Exception("Solicitud no encontrada para el correo.");

            $orig = json_decode($solicitud['campos_originales'] ?? '{}', true) ?: [];
            $nuev = json_decode($solicitud['campos_nuevos'] ?? '{}', true) ?: [];
            $tablaValores = '';
            foreach ($nuev as $k => $v) {
                $tablaValores .= "<tr><td style='padding:4px 8px; border:1px solid #e2e8f0;'>" . ucfirst($k) . "</td>"
                               . "<td style='padding:4px 8px; border:1px solid #e2e8f0; color:#ef4444;'>" . ($orig[$k] ?? 'N/A') . "</td>"
                               . "<td style='padding:4px 8px; border:1px solid #e2e8f0; color:#22c55e;'>" . $v . "</td></tr>";
            }

            require_once dirname(__DIR__) . '/Mailer.php';
            $mailer = new Mailer();
            $asunto = "Bitacora de Ajuste - Poliza " . $solicitud['numero_poliza'];
            $cuerpo = "
            <div style='font-family:Arial,sans-serif; max-width:640px; margin:0 auto;'>
                <div style='background:#4f46e5; padding:20px; border-radius:8px 8px 0 0;'>
                    <h2 style='color:#fff; margin:0;'>Bitacora Auditorial de Ajuste</h2>
                    <p style='color:#c7d2fe; margin:4px 0 0;'>MAS QUE FIANZAS - Centro Tecnico de Seguros</p>
                </div>
                <div style='background:#f8fafc; padding:20px; border:1px solid #e2e8f0;'>
                    <p><strong>Poliza N°:</strong> {$solicitud['numero_poliza']}<br>
                       <strong>Categoria:</strong> " . strtoupper($solicitud['categoria_cambio'] ?? '') . "<br>
                       <strong>Estado:</strong> " . strtoupper($solicitud['estado']) . "<br>
                       <strong>Solicitado por:</strong> {$solicitud['solicita_nombre']}<br>
                       <strong>Fecha/Hora:</strong> {$solicitud['fecha_solicitud']}<br>
                       <strong>Justificacion:</strong> {$solicitud['justificacion']}
                    </p>
                    <table style='width:100%; border-collapse:collapse; margin-top:12px;'>
                        <thead><tr>
                            <th style='padding:6px 8px; background:#4f46e5; color:#fff; border:1px solid #4f46e5;'>Campo</th>
                            <th style='padding:6px 8px; background:#4f46e5; color:#fff; border:1px solid #4f46e5;'>Valor Anterior</th>
                            <th style='padding:6px 8px; background:#4f46e5; color:#fff; border:1px solid #4f46e5;'>Valor Nuevo</th>
                        </tr></thead>
                        <tbody>{$tablaValores}</tbody>
                    </table>
                    <p style='margin-top:16px;'><strong>Resolucion:</strong> " . strtoupper($solicitud['estado']) . "<br>
                       <strong>Resuelto por:</strong> " . ($solicitud['aprueba_nombre'] ?? 'Pendiente') . "<br>
                       <strong>Motivo:</strong> " . ($solicitud['motivo_resolucion'] ?? 'N/A') . "
                    </p>
                </div>
                <div style='background:#e2e8f0; padding:10px 20px; border-radius:0 0 8px 8px; font-size:11px; color:#64748b;'>
                    Documento generado automaticamente el " . date('d/m/Y H:i:s') . " por el sistema MAS QUE FIANZAS.
                </div>
            </div>";

            // Mailer method is 'enviar' (not 'sendEmail')
            $enviado = $mailer->enviar($correoDestino, $asunto, $cuerpo, true);
            
            if ($enviado) {
                echo json_encode(["exito" => true, "mensaje" => "Correo con bitacora enviado exitosamente a $correoDestino."]);
            } else {
                // Show SMTP log path to help debug if sending fails
                $logInfo = file_exists(dirname(__DIR__) . '/logs/smtp.log') ? ' Revise el log SMTP en backend/logs/smtp.log para detalles.' : '';
                echo json_encode(["exito" => false, "mensaje" => "Error al enviar el correo. Verifique la configuracion SMTP del servidor.$logInfo"]);
            }
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    if (isset($db) && $db->ping()) {
        $db->rollback();
    }
    http_response_code(400);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
?>
