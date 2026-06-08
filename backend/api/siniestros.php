<?php
/**
 * API de Gestión de Siniestros y Reclamaciones — v1.0
 * MAS QUE FIANZAS — Core Asegurador
 * ==========================================================
 * Permite registrar reclamaciones, adjuntar evidencias,
 * actualizar estados (transiciones contables) y consultar casos.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../AsientoContableManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(["exito" => false, "mensaje" => "Sesión no válida o expirada"]);
    exit;
}

$usuario_actual = $usuario_id;
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'listar';

// Asegurar directorio de subida de evidencias
$uploads_dir = dirname(__DIR__) . '/uploads/siniestros';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

try {
    // Control granular
    if (!tienePermiso($usuario_actual, 'TAB_SIN_CONSULTAR') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
        http_response_code(403);
        echo json_encode(["exito" => false, "mensaje" => "Acceso restringido: No posee permisos (TAB_SIN_CONSULTAR)."]);
        exit;
    }

    if ($method === 'GET') {
        if ($action === 'listar') {
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

            $where = "1=1";
            $params = [];
            $types = "";

            if (!empty($search)) {
                $where .= " AND (s.numero_siniestro LIKE ? OR c.nombre LIKE ? OR p.numero_poliza LIKE ?)";
                $q = "%$search%";
                $params[] = $q; $params[] = $q; $params[] = $q;
                $types .= "sss";
            }

            if (!empty($estado)) {
                $where .= " AND s.estado = ?";
                $params[] = $estado;
                $types .= "s";
            }

            $sql = "SELECT s.*, 
                           p.numero_poliza, p.tipo_seguro, p.aseguradora, 
                           c.nombre as cliente_nombre, c.cedula as cliente_cedula, c.telefono as cliente_telefono,
                           u_reg.username as registrado_por_username,
                           u_rev.username as revisado_por_username,
                           u_apr.username as aprobado_por_username
                    FROM siniestros s
                    JOIN polizas p ON s.poliza_id = p.id
                    JOIN clientes c ON s.cliente_id = c.id
                    LEFT JOIN usuarios u_reg ON s.registrado_por = u_reg.id
                    LEFT JOIN usuarios u_rev ON s.revisado_por = u_rev.id
                    LEFT JOIN usuarios u_apr ON s.aprobado_por = u_apr.id
                    WHERE $where
                    ORDER BY s.fecha_registro DESC";

            $stmt = $db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $siniestros = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['poliza_id'] = (int)$row['poliza_id'];
                $row['cliente_id'] = (int)$row['cliente_id'];
                $row['monto_reclamado'] = (float)$row['monto_reclamado'];
                $row['monto_aprobado'] = $row['monto_aprobado'] !== null ? (float)$row['monto_aprobado'] : null;
                $siniestros[] = $row;
            }
            $stmt->close();

            echo json_encode(["exito" => true, "data" => $siniestros]);
            exit;

        } elseif ($action === 'obtener') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception("ID de siniestro obligatorio.");

            $stmt = $db->prepare("SELECT s.*, 
                                         p.numero_poliza, p.tipo_seguro, p.aseguradora, p.prima_total,
                                         c.nombre as cliente_nombre, c.cedula as cliente_cedula, c.telefono as cliente_telefono, c.email as cliente_email
                                  FROM siniestros s
                                  JOIN polizas p ON s.poliza_id = p.id
                                  JOIN clientes c ON s.cliente_id = c.id
                                  WHERE s.id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $siniestro = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$siniestro) throw new Exception("Siniestro no encontrado.");

            $siniestro['id'] = (int)$siniestro['id'];
            $siniestro['poliza_id'] = (int)$siniestro['poliza_id'];
            $siniestro['cliente_id'] = (int)$siniestro['cliente_id'];
            $siniestro['monto_reclamado'] = (float)$siniestro['monto_reclamado'];
            $siniestro['monto_aprobado'] = $siniestro['monto_aprobado'] !== null ? (float)$siniestro['monto_aprobado'] : null;
            $siniestro['prima_total'] = (float)$siniestro['prima_total'];

            // Obtener asientos contables asociados
            $stmt_a = $db->prepare("SELECT id, numero_asiento, fecha_asiento, descripcion, estado 
                                    FROM asientos_contables 
                                    WHERE modulo_origen = 'siniestros' AND referencia_id = ?");
            $stmt_a->bind_param("i", $id);
            $stmt_a->execute();
            $res_a = $stmt_a->get_result();
            $asientos = [];
            while ($row = $res_a->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $asientos[] = $row;
            }
            $stmt_a->close();

            $siniestro['asientos'] = $asientos;

            echo json_encode(["exito" => true, "data" => $siniestro]);
            exit;

        } elseif ($action === 'buscar_poliza') {
            $query = isset($_GET['query']) ? trim($_GET['query']) : '';
            if (strlen($query) < 2) {
                echo json_encode(["exito" => true, "data" => []]);
                exit;
            }

            $q = "%$query%";
            $stmt = $db->prepare("SELECT p.id as poliza_id, p.numero_poliza, p.aseguradora, p.tipo_seguro, p.prima_total,
                                         c.id as cliente_id, c.nombre as cliente_nombre, c.cedula as cliente_cedula, c.telefono as cliente_telefono
                                  FROM polizas p
                                  JOIN clientes c ON p.cliente_id = c.id
                                  WHERE (p.numero_poliza LIKE ? OR c.nombre LIKE ? OR c.cedula LIKE ?) AND p.estado = 'activa'
                                  LIMIT 10");
            $stmt->bind_param("sss", $q, $q, $q);
            $stmt->execute();
            $res = $stmt->get_result();
            $polizas = [];
            while ($row = $res->fetch_assoc()) {
                $row['poliza_id'] = (int)$row['poliza_id'];
                $row['cliente_id'] = (int)$row['cliente_id'];
                $row['prima_total'] = (float)$row['prima_total'];
                $polizas[] = $row;
            }
            $stmt->close();

            echo json_encode(["exito" => true, "data" => $polizas]);
            exit;
        } else {
            throw new Exception("Acción GET no soportada.");
        }
    } elseif ($method === 'POST') {
        if ($action === 'registrar') {
            if (!tienePermiso($usuario_actual, 'TAB_SIN_REGISTRAR') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
                throw new Exception("No posee permisos (TAB_SIN_REGISTRAR) para declarar siniestros.");
            }

            // Manejo de Multipart/form-data
            $poliza_id = (int)($_POST['poliza_id'] ?? 0);
            $cliente_id = (int)($_POST['cliente_id'] ?? 0);
            $fecha_siniestro = trim($_POST['fecha_siniestro'] ?? '');
            $descripcion = trim($_POST['descripcion_evento'] ?? '');
            $lugar = trim($_POST['lugar_evento'] ?? '');
            $monto_reclamado = floatval($_POST['monto_reclamado'] ?? 0);

            if (!$poliza_id) throw new Exception("Póliza asociada obligatoria.");
            if (!$cliente_id) throw new Exception("Cliente asociado obligatorio.");
            if (empty($fecha_siniestro)) throw new Exception("La fecha del siniestro es obligatoria.");
            if (empty($descripcion)) throw new Exception("La descripción del siniestro es obligatoria.");

            // Generar número correlativo de siniestro
            $anio = date('Y', strtotime($fecha_siniestro));
            $numero_siniestro = 'SIN-' . $anio . '-' . rand(1000, 9999);

            // Evidencia adjunta
            $evidencia_ruta = null;
            if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['evidencia'];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $clean_name = 'SIN_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $dest = $uploads_dir . '/' . $clean_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $evidencia_ruta = 'backend/uploads/siniestros/' . $clean_name;
                }
            }

            $db->begin_transaction();

            $stmt = $db->prepare("INSERT INTO siniestros (
                                      numero_siniestro, poliza_id, cliente_id, fecha_siniestro,
                                      descripcion_evento, lugar_evento, monto_reclamado,
                                      estado, evidencia_adjunta, registrado_por
                                  ) VALUES (?, ?, ?, ?, ?, ?, ?, 'registrado', ?, ?)");
            $stmt->bind_param("siisssdsi", 
                $numero_siniestro, $poliza_id, $cliente_id, $fecha_siniestro,
                $descripcion, $lugar, $monto_reclamado, $evidencia_ruta, $usuario_actual
            );
            
            if ($stmt->execute()) {
                $new_id = $db->insert_id;
                $stmt->close();
                
                // Bitácora de Auditoría
                logAudit(
                    $usuario_actual, 'registrar_siniestro', 'Siniestros', 'TAB_SIN_REGISTRAR',
                    "Registrado siniestro {$numero_siniestro} para póliza ID: {$poliza_id}", 'exitoso', null,
                    'siniestros', $new_id, null, $_POST
                );

                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Siniestro declarado con éxito.", "id" => $new_id, "numero_siniestro" => $numero_siniestro]);
                exit;
            } else {
                $stmt->close();
                throw new Exception("Error al guardar en base de datos: " . $db->error);
            }

        } elseif ($action === 'actualizar_estado') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;

            $id = (int)($input['id'] ?? 0);
            $nuevo_estado = trim($input['estado'] ?? '');
            $monto_aprobado = isset($input['monto_aprobado']) ? floatval($input['monto_aprobado']) : null;
            $justificacion = trim($input['justificacion'] ?? '');

            if (!$id) throw new Exception("ID de siniestro obligatorio.");
            if (!in_array($nuevo_estado, ['en_revision', 'aprobado', 'rechazado', 'pagado'])) {
                throw new Exception("Estado de siniestro no válido.");
            }

            // Validar permisos según estado de transición
            if ($nuevo_estado === 'en_revision') {
                if (!tienePermiso($usuario_actual, 'TAB_SIN_CONSULTAR') && $usuario_actual !== 1) {
                    throw new Exception("No tiene autorización para revisar reclamaciones.");
                }
            } elseif ($nuevo_estado === 'aprobado' || $nuevo_estado === 'rechazado') {
                if (!tienePermiso($usuario_actual, 'TAB_SIN_DICTAMEN') && $usuario_actual !== 1) {
                    throw new Exception("No posee permisos de dictamen (TAB_SIN_DICTAMEN).");
                }
                if ($nuevo_estado === 'aprobado' && ($monto_aprobado === null || $monto_aprobado <= 0)) {
                    throw new Exception("Para aprobar un siniestro, debe ingresar un monto aprobado válido.");
                }
            } elseif ($nuevo_estado === 'pagado') {
                if (!tienePermiso($usuario_actual, 'TAB_SIN_LIQUIDAR') && $usuario_actual !== 1) {
                    throw new Exception("No posee autorización (TAB_SIN_LIQUIDAR) para liquidar indemnizaciones.");
                }
            }

            $db->begin_transaction();

            // Consultar datos actuales del siniestro
            $stmt_sel = $db->prepare("SELECT s.*, p.numero_poliza FROM siniestros s JOIN polizas p ON s.poliza_id = p.id WHERE s.id = ? LIMIT 1");
            $stmt_sel->bind_param("i", $id);
            $stmt_sel->execute();
            $siniestro = $stmt_sel->get_result()->fetch_assoc();
            $stmt_sel->close();

            if (!$siniestro) throw new Exception("Siniestro no encontrado.");

            $estado_anterior = $siniestro['estado'];
            $monto_reclamado = (float)$siniestro['monto_reclamado'];

            // Reglas de transición de estados
            if ($estado_anterior === 'pagado') throw new Exception("Un siniestro ya pagado no puede cambiar de estado.");
            if ($nuevo_estado === 'pagado' && $estado_anterior !== 'aprobado') {
                throw new Exception("Solo se pueden liquidar siniestros en estado 'aprobado'.");
            }

            // Armar UPDATE SQL dinámicamente
            $sql_upd = "UPDATE siniestros SET estado = ?";
            $bind_types = "s";
            $bind_args = [$nuevo_estado];

            if ($nuevo_estado === 'en_revision') {
                $sql_upd .= ", revisado_por = ?, fecha_revision = NOW()";
                $bind_types .= "i";
                $bind_args[] = $usuario_actual;
            } elseif ($nuevo_estado === 'aprobado') {
                $sql_upd .= ", aprobado_por = ?, monto_aprobado = ?, fecha_aprobacion = NOW()";
                $bind_types .= "id";
                $bind_args[] = $usuario_actual;
                $bind_args[] = $monto_aprobado;
            } elseif ($nuevo_estado === 'rechazado') {
                $sql_upd .= ", aprobado_por = ?, fecha_aprobacion = NOW()";
                $bind_types .= "i";
                $bind_args[] = $usuario_actual;
            }

            $sql_upd .= " WHERE id = ?";
            $bind_types .= "i";
            $bind_args[] = $id;

            $stmt_upd = $db->prepare($sql_upd);
            $stmt_upd->bind_param($bind_types, ...$bind_args);
            $stmt_upd->execute();
            $stmt_upd->close();

            // INTEGRACIÓN CONTABLE AUTOMÁTICA (Partida Doble)
            $asientoManager = new AsientoContableManager();

            if ($nuevo_estado === 'aprobado') {
                // 1. Asiento Contable de Provisión de Siniestro (DICTAMEN)
                // DÉBITO: 5.1.02.01 (Gastos de Siniestros Aprobados)
                // CRÉDITO: 2.1.04.01 (Provisión para Reclamaciones Pendientes)
                $desc_asiento = "Provisión de Siniestro {$siniestro['numero_siniestro']} - Póliza {$siniestro['numero_poliza']}";
                $res_asiento = $asientoManager->registrarAsiento([
                    'descripcion' => $desc_asiento,
                    'modulo' => 'siniestros',
                    'ref_id' => $id,
                    'ref_tipo' => 'siniestro_provision',
                    'user_id' => $usuario_actual,
                    'lineas' => [
                        [
                            'cuenta' => '5.1.02.01', 
                            'nombre' => 'Gastos de Siniestros Aprobados', 
                            'tipo' => 'debito', 
                            'monto' => $monto_aprobado, 
                            'glosa' => 'Cargos a gasto por reclamo aprobado'
                        ],
                        [
                            'cuenta' => '2.1.04.01', 
                            'nombre' => 'Provisión para Reclamaciones Pendientes', 
                            'tipo' => 'credito', 
                            'monto' => $monto_aprobado, 
                            'glosa' => 'Pasivo provisto a favor del cliente'
                        ]
                    ]
                ]);

                if (!$res_asiento['exito']) {
                    throw new Exception("Error al generar asiento de provisión contable: " . $res_asiento['mensaje']);
                }

            } elseif ($nuevo_estado === 'pagado') {
                // 2. Asiento Contable de Liquidación de Siniestro (PAGO/LIQUIDACIÓN)
                // DÉBITO: 2.1.04.01 (Provisión para Reclamaciones Pendientes)
                // CRÉDITO: 1.1.01.01 (Caja y Bancos)
                $monto_pago = (float)$siniestro['monto_aprobado'];
                $desc_asiento = "Liquidación y Pago de Siniestro {$siniestro['numero_siniestro']} - Póliza {$siniestro['numero_poliza']}";
                $res_asiento = $asientoManager->registrarAsiento([
                    'descripcion' => $desc_asiento,
                    'modulo' => 'siniestros',
                    'ref_id' => $id,
                    'ref_tipo' => 'siniestro_pago',
                    'user_id' => $usuario_actual,
                    'lineas' => [
                        [
                            'cuenta' => '2.1.04.01', 
                            'nombre' => 'Provisión para Reclamaciones Pendientes', 
                            'tipo' => 'debito', 
                            'monto' => $monto_pago, 
                            'glosa' => 'Cancelación pasivo provisto'
                        ],
                        [
                            'cuenta' => '1.1.01.01', 
                            'nombre' => 'Caja y Bancos - Cuenta Corriente', 
                            'tipo' => 'credito', 
                            'monto' => $monto_pago, 
                            'glosa' => 'Desembolso bancario a favor del cliente'
                        ]
                    ]
                ]);

                if (!$res_asiento['exito']) {
                    throw new Exception("Error al generar asiento de pago contable: " . $res_asiento['mensaje']);
                }
            }

            // Registrar Auditoría NOFTRAB
            logAudit(
                $usuario_actual, 'actualizar_siniestro_estado', 'Siniestros', 'TAB_SIN_DICTAMEN',
                "Cambio de estado del siniestro {$siniestro['numero_siniestro']} de {$estado_anterior} a {$nuevo_estado}", 'exitoso', null,
                'siniestros', $id, ["estado" => $estado_anterior], ["estado" => $nuevo_estado, "justificacion" => $justificacion]
            );

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Estado del siniestro actualizado a '{$nuevo_estado}' con éxito."]);
            exit;

        } else {
            throw new Exception("Acción POST no soportada.");
        }
    } else {
        http_response_code(405);
        echo json_encode(["exito" => false, "mensaje" => "Método HTTP no soportado."]);
    }
} catch (Exception $e) {
    if (isset($db) && $db->in_transaction) $db->rollback();
    http_response_code(500);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
?>
