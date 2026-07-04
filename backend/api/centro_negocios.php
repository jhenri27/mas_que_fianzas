<?php
/**
 * API: Centro de Negocios - Estadísticas y Gestión de Bonos/Enlaces
 * MAS QUE FIANZAS - Core Asegurador v3.0
 */

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

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
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
$stmt_check = $db->prepare("SELECT perfil_id, username, referente_id FROM usuarios WHERE id = ? LIMIT 1");
$stmt_check->bind_param("i", $usuario_id);
$stmt_check->execute();
$usr_data = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

$perfil_id_usr = (int)($usr_data['perfil_id'] ?? 0);
$es_admin = ($usuario_id === 1 || $perfil_id_usr === 1 || tienePermiso($usuario_id, 'CONF_TOTAL') || tienePermiso($usuario_id, 'PER_GESTIONAR'));
$es_supervisor = ($perfil_id_usr === 2 || tienePermiso($usuario_id, 'VER_ESTADISTICAS_RED'));

$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? 'obtener_estadisticas';

try {
    switch ($action) {
        case 'obtener_estadisticas':
            // 1. Obtener ventas generales
            // Si es admin, ve global. Si es supervisor, ve las suyas y las de su red. Si es agente, solo las suyas.
            $condicion = " WHERE 1=1 ";
            $params = [];
            $types = "";

            if (!$es_admin) {
                if ($es_supervisor) {
                    $condicion = " WHERE (p.emitida_por = ? OR u.referente_id = ?) ";
                    $params = [$usuario_id, $usuario_id];
                    $types = "ii";
                } else {
                    $condicion = " WHERE p.emitida_por = ? ";
                    $params = [$usuario_id];
                    $types = "i";
                }
            }

            // Metricas generales
            $sql_metrics = "SELECT COUNT(p.id) as cantidad_polizas, 
                                   IFNULL(SUM(p.prima_total), 0) as total_primas, 
                                   IFNULL(AVG(p.prima_total), 0) as prima_promedio
                            FROM polizas p 
                            JOIN usuarios u ON p.emitida_por = u.id" . $condicion;
            
            $stmt = $db->prepare($sql_metrics);
            if ($types !== "") {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $general_metrics = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Ventas por Perfil de Usuario
            $sql_perfil = "SELECT pr.nombre_perfil, COUNT(p.id) as cantidad_ventas, IFNULL(SUM(p.prima_total), 0) as prima_emitida 
                           FROM polizas p 
                           JOIN usuarios u ON p.emitida_por = u.id 
                           JOIN perfiles pr ON u.perfil_id = pr.id";
            if (!$es_admin) {
                if ($es_supervisor) {
                    $sql_perfil .= " WHERE (p.emitida_por = ? OR u.referente_id = ?)";
                } else {
                    $sql_perfil .= " WHERE p.emitida_por = ?";
                }
            }
            $sql_perfil .= " GROUP BY pr.id ORDER BY prima_emitida DESC";

            $stmt = $db->prepare($sql_perfil);
            if (!$es_admin) {
                if ($es_supervisor) {
                    $stmt->bind_param("ii", $usuario_id, $usuario_id);
                } else {
                    $stmt->bind_param("i", $usuario_id);
                }
            }
            $stmt->execute();
            $res_perfil = $stmt->get_result();
            $ventas_perfil = [];
            while ($row = $res_perfil->fetch_assoc()) {
                $ventas_perfil[] = [
                    "nombre_perfil" => $row['nombre_perfil'],
                    "cantidad_ventas" => (int)$row['cantidad_ventas'],
                    "prima_emitida" => (float)$row['prima_emitida']
                ];
            }
            $stmt->close();

            // 2. Procesar y sincronizar logros de bonos de manera dinámica para el usuario actual
            $bonos_vigentes = $db->query("SELECT * FROM bonos_configuracion WHERE estado = 'activo' AND fecha_fin >= CURDATE()");
            $logros = [];
            if ($bonos_vigentes) {
                while ($bono = $bonos_vigentes->fetch_assoc()) {
                    $bono_id = (int)$bono['id'];
                    
                    // Verificar si este bono es aplicable al perfil del usuario
                    if ($bono['perfil_id'] !== null && (int)$bono['perfil_id'] !== $perfil_id_usr) {
                        continue;
                    }

                    // Calcular el progreso dinámico del usuario durante la vigencia del bono
                    $tipo_meta = $bono['tipo_meta'];
                    $valor_meta = (float)$bono['valor_meta'];
                    $fecha_inicio = $bono['fecha_inicio'];
                    $fecha_fin = $bono['fecha_fin'];

                    if ($tipo_meta === 'ventas_cantidad') {
                        $sql_progreso = "SELECT COUNT(id) as total FROM polizas WHERE emitida_por = ? AND fecha_emision BETWEEN ? AND ?";
                        $stmt_prog = $db->prepare($sql_progreso);
                        $start = $fecha_inicio . " 00:00:00";
                        $end = $fecha_fin . " 23:59:59";
                        $stmt_prog->bind_param("iss", $usuario_id, $start, $end);
                        $stmt_prog->execute();
                        $total_prog = $stmt_prog->get_result()->fetch_assoc();
                        $progreso = (float)($total_prog['total'] ?? 0);
                        $stmt_prog->close();
                    } else {
                        $sql_progreso = "SELECT SUM(prima_total) as total FROM polizas WHERE emitida_por = ? AND fecha_emision BETWEEN ? AND ?";
                        $stmt_prog = $db->prepare($sql_progreso);
                        $start = $fecha_inicio . " 00:00:00";
                        $end = $fecha_fin . " 23:59:59";
                        $stmt_prog->bind_param("iss", $usuario_id, $start, $end);
                        $stmt_prog->execute();
                        $total_prog = $stmt_prog->get_result()->fetch_assoc();
                        $progreso = (float)($total_prog['total'] ?? 0.00);
                        $stmt_prog->close();
                    }

                    $completado = ($progreso >= $valor_meta) ? 1 : 0;

                    // Comprobar si ya existe un registro de logro en la base de datos
                    $stmt_check_log = $db->prepare("SELECT id, completado, pagado FROM bonos_logros WHERE bono_id = ? AND usuario_id = ? LIMIT 1");
                    $stmt_check_log->bind_param("ii", $bono_id, $usuario_id);
                    $stmt_check_log->execute();
                    $exist_log = $stmt_check_log->get_result()->fetch_assoc();
                    $stmt_check_log->close();

                    if ($exist_log) {
                        $log_id = (int)$exist_log['id'];
                        $was_completado = (int)$exist_log['completado'];
                        $is_pagado = (int)$exist_log['pagado'];

                        // Actualizar progreso
                        if ($was_completado === 0 && $completado === 1) {
                            $stmt_up_log = $db->prepare("UPDATE bonos_logros SET progreso_actual = ?, completado = 1, fecha_completado = NOW() WHERE id = ?");
                            $stmt_up_log->bind_param("di", $progreso, $log_id);
                            $stmt_up_log->execute();
                            $stmt_up_log->close();
                        } else {
                            $stmt_up_log = $db->prepare("UPDATE bonos_logros SET progreso_actual = ? WHERE id = ?");
                            $stmt_up_log->bind_param("di", $progreso, $log_id);
                            $stmt_up_log->execute();
                            $stmt_up_log->close();
                        }
                    } else {
                        // Crear registro
                        $fecha_comp = $completado ? date('Y-m-d H:i:s') : null;
                        $stmt_ins_log = $db->prepare("INSERT INTO bonos_logros (bono_id, usuario_id, progreso_actual, completado, fecha_completado) VALUES (?, ?, ?, ?, ?)");
                        $stmt_ins_log->bind_param("iidis", $bono_id, $usuario_id, $progreso, $completado, $fecha_comp);
                        $stmt_ins_log->execute();
                        $stmt_ins_log->close();
                        
                        $is_pagado = 0;
                    }

                    $logros[] = [
                        "bono_id" => $bono_id,
                        "nombre_bono" => $bono['nombre_bono'],
                        "descripcion" => $bono['descripcion'],
                        "tipo_meta" => $tipo_meta,
                        "valor_meta" => $valor_meta,
                        "monto_bono" => (float)$bono['monto_bono'],
                        "progreso_actual" => $progreso,
                        "completado" => $completado === 1 || ($exist_log && (int)$exist_log['completado'] === 1),
                        "pagado" => $is_pagado === 1,
                        "fecha_fin" => $fecha_fin
                    ];
                }
            }

            // Tasa de conversión de enlaces para el usuario actual
            $sql_conv = "SELECT IFNULL(SUM(vistas), 0) as total_vistas, IFNULL(SUM(conversiones), 0) as total_conversiones 
                         FROM enlaces_venta_online WHERE usuario_id = ?";
            $stmt_c = $db->prepare($sql_conv);
            $stmt_c->bind_param("i", $usuario_id);
            $stmt_c->execute();
            $conv_data = $stmt_c->get_result()->fetch_assoc();
            $stmt_c->close();

            $total_vistas = (int)$conv_data['total_vistas'];
            $total_conversiones = (int)$conv_data['total_conversiones'];
            $tasa_conversion = ($total_vistas > 0) ? round(($total_conversiones / $total_vistas) * 100, 2) : 0.00;

            echo json_encode([
                "exito" => true,
                "metricas" => [
                    "cantidad_polizas" => (int)$general_metrics['cantidad_polizas'],
                    "total_primas" => (float)$general_metrics['total_primas'],
                    "prima_promedio" => (float)$general_metrics['prima_promedio'],
                    "tasa_conversion" => $tasa_conversion,
                    "enlaces_vistas" => $total_vistas,
                    "enlaces_conversiones" => $total_conversiones
                ],
                "ventas_perfil" => $ventas_perfil,
                "logros" => $logros
            ]);
            break;

        case 'listar_bonos':
            $res = $db->query("SELECT b.*, p.nombre_perfil FROM bonos_configuracion b LEFT JOIN perfiles p ON b.perfil_id = p.id ORDER BY b.id DESC");
            $bonos = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $row['id'] = (int)$row['id'];
                    $row['valor_meta'] = (float)$row['valor_meta'];
                    $row['monto_bono'] = (float)$row['monto_bono'];
                    $row['perfil_id'] = $row['perfil_id'] !== null ? (int)$row['perfil_id'] : null;
                    $bonos[] = $row;
                }
            }
            echo json_encode(["exito" => true, "datos" => $bonos]);
            break;

        case 'crear_bono':
            if (!$es_admin) throw new Exception("Acceso no autorizado para configurar bonos");
            $nombre = trim($_POST['nombre_bono'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $tipo_meta = trim($_POST['tipo_meta'] ?? '');
            $valor_meta = (float)($_POST['valor_meta'] ?? 0);
            $monto_bono = (float)($_POST['monto_bono'] ?? 0);
            $perfil_id = isset($_POST['perfil_id']) && !empty($_POST['perfil_id']) ? (int)$_POST['perfil_id'] : null;
            $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
            $fecha_fin = trim($_POST['fecha_fin'] ?? '');

            if (empty($nombre) || empty($tipo_meta) || $valor_meta <= 0 || $monto_bono <= 0 || empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception("Todos los campos requeridos deben completarse con valores válidos.");
            }

            $stmt = $db->prepare("INSERT INTO bonos_configuracion (nombre_bono, descripcion, tipo_meta, valor_meta, monto_bono, perfil_id, fecha_inicio, fecha_fin, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo')");
            $stmt->bind_param("sssddiss", $nombre, $descripcion, $tipo_meta, $valor_meta, $monto_bono, $perfil_id, $fecha_inicio, $fecha_fin);
            if ($stmt->execute()) {
                echo json_encode(["exito" => true, "mensaje" => "Bono configurado exitosamente."]);
            } else {
                throw new Exception("Error al registrar bono: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'editar_bono':
            if (!$es_admin) throw new Exception("Acceso no autorizado para configurar bonos");
            $id = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre_bono'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $tipo_meta = trim($_POST['tipo_meta'] ?? '');
            $valor_meta = (float)($_POST['valor_meta'] ?? 0);
            $monto_bono = (float)($_POST['monto_bono'] ?? 0);
            $perfil_id = isset($_POST['perfil_id']) && !empty($_POST['perfil_id']) ? (int)$_POST['perfil_id'] : null;
            $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
            $fecha_fin = trim($_POST['fecha_fin'] ?? '');
            $estado = trim($_POST['estado'] ?? 'activo');

            if (!$id || empty($nombre) || empty($tipo_meta) || $valor_meta <= 0 || $monto_bono <= 0 || empty($fecha_inicio) || empty($fecha_fin)) {
                throw new Exception("Campos obligatorios incorrectos.");
            }

            $stmt = $db->prepare("UPDATE bonos_configuracion SET nombre_bono = ?, descripcion = ?, tipo_meta = ?, valor_meta = ?, monto_bono = ?, perfil_id = ?, fecha_inicio = ?, fecha_fin = ?, estado = ? WHERE id = ?");
            $stmt->bind_param("sssddisssi", $nombre, $descripcion, $tipo_meta, $valor_meta, $monto_bono, $perfil_id, $fecha_inicio, $fecha_fin, $estado, $id);
            if ($stmt->execute()) {
                echo json_encode(["exito" => true, "mensaje" => "Bono actualizado con éxito."]);
            } else {
                throw new Exception("Error al actualizar bono: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'eliminar_bono':
            if (!$es_admin) throw new Exception("Acceso no autorizado");
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if (!$id) throw new Exception("ID de bono inválido");

            $stmt = $db->prepare("DELETE FROM bonos_configuracion WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(["exito" => true, "mensaje" => "Bono eliminado exitosamente."]);
            } else {
                throw new Exception("Error al eliminar bono: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'listar_logros_usuarios':
            if (!$es_admin && !$es_supervisor) throw new Exception("Acceso denegado");
            
            $query = "SELECT l.*, b.nombre_bono, b.monto_bono, u.nombre, u.apellido, u.username, p.nombre_perfil
                      FROM bonos_logros l 
                      JOIN bonos_configuracion b ON l.bono_id = b.id
                      JOIN usuarios u ON l.usuario_id = u.id
                      JOIN perfiles p ON u.perfil_id = p.id
                      ORDER BY l.completado DESC, l.id DESC";
            
            $res = $db->query($query);
            $datos = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $row['id'] = (int)$row['id'];
                    $row['bono_id'] = (int)$row['bono_id'];
                    $row['usuario_id'] = (int)$row['usuario_id'];
                    $row['progreso_actual'] = (float)$row['progreso_actual'];
                    $row['completado'] = (int)$row['completado'];
                    $row['pagado'] = (int)$row['pagado'];
                    $row['monto_bono'] = (float)$row['monto_bono'];
                    $datos[] = $row;
                }
            }
            echo json_encode(["exito" => true, "datos" => $datos]);
            break;

        case 'marcar_bono_pagado':
            if (!$es_admin) throw new Exception("Solo administradores pueden gestionar pagos de bonos.");
            $logro_id = (int)($_POST['logro_id'] ?? 0);
            $referencia_pago = trim($_POST['referencia_pago'] ?? '');
            
            if (!$logro_id || empty($referencia_pago)) {
                throw new Exception("ID del logro y Referencia de pago son obligatorios.");
            }

            // Verificar si el logro está completado
            $stmt_check = $db->prepare("SELECT completado, pagado FROM bonos_logros WHERE id = ? LIMIT 1");
            $stmt_check->bind_param("i", $logro_id);
            $stmt_check->execute();
            $logro = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if (!$logro) throw new Exception("Logro no encontrado.");
            if ((int)$logro['completado'] !== 1) throw new Exception("El bono no se puede pagar porque la meta no se ha cumplido aún.");
            if ((int)$logro['pagado'] === 1) throw new Exception("Este bono ya figura como pagado.");

            $fecha_pago = date('Y-m-d');
            $stmt = $db->prepare("UPDATE bonos_logros SET pagado = 1, fecha_pago = ?, referencia_pago = ? WHERE id = ?");
            $stmt->bind_param("ssi", $fecha_pago, $referencia_pago, $logro_id);
            if ($stmt->execute()) {
                echo json_encode(["exito" => true, "mensaje" => "Bono marcado como pagado exitosamente."]);
            } else {
                throw new Exception("Error al marcar pago: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'listar_enlaces':
            // Agentes ven sus enlaces; admins y supervisores ven todos (o los de su red)
            $condicion = " WHERE 1=1 ";
            $params = [];
            $types = "";

            if (!$es_admin) {
                if ($es_supervisor) {
                    $condicion = " WHERE (e.usuario_id = ? OR u.referente_id = ?) ";
                    $params = [$usuario_id, $usuario_id];
                    $types = "ii";
                } else {
                    $condicion = " WHERE e.usuario_id = ? ";
                    $params = [$usuario_id];
                    $types = "i";
                }
            }

            $sql = "SELECT e.*, u.nombre as creador_nombre, u.apellido as creador_apellido, u.username as creador_username 
                    FROM enlaces_venta_online e
                    JOIN usuarios u ON e.usuario_id = u.id" . $condicion . " ORDER BY e.id DESC";

            $stmt = $db->prepare($sql);
            if ($types !== "") {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $enlaces = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['usuario_id'] = (int)$row['usuario_id'];
                $row['descuento_aplicado'] = (float)$row['descuento_aplicado'];
                $row['vistas'] = (int)$row['vistas'];
                $row['conversiones'] = (int)$row['conversiones'];
                $enlaces[] = $row;
            }
            $stmt->close();

            echo json_encode(["exito" => true, "datos" => $enlaces]);
            break;

        case 'crear_enlace':
            $aseguradora = trim($_POST['aseguradora'] ?? '');
            $ramo = trim($_POST['ramo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $descuento = (float)($_POST['descuento_aplicado'] ?? 0.00);
            $fecha_exp = isset($_POST['fecha_expiracion']) && !empty($_POST['fecha_expiracion']) ? trim($_POST['fecha_expiracion']) : null;

            if (empty($aseguradora) || empty($ramo)) {
                throw new Exception("Aseguradora y Ramo son campos requeridos.");
            }

            // Generar hash único
            $codigo_enlace = md5(uniqid(rand(), true));

            $stmt = $db->prepare("INSERT INTO enlaces_venta_online (usuario_id, codigo_enlace, aseguradora, ramo, descripcion, descuento_aplicado, fecha_expiracion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'activo')");
            $stmt->bind_param("issssds", $usuario_id, $codigo_enlace, $aseguradora, $ramo, $descripcion, $descuento, $fecha_exp);
            
            if ($stmt->execute()) {
                echo json_encode([
                    "exito" => true, 
                    "mensaje" => "Enlace de venta online generado exitosamente.",
                    "codigo_enlace" => $codigo_enlace
                ]);
            } else {
                throw new Exception("Error al insertar enlace: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'toggle_enlace':
            $id = (int)($_POST['id'] ?? 0);
            $estado_nuevo = trim($_POST['estado'] ?? '');

            if (!$id || !in_array($estado_nuevo, ['activo', 'inactivo'])) {
                throw new Exception("Parámetros incorrectos.");
            }

            // Si no es admin ni supervisor, verificar pertenencia del enlace
            if (!$es_admin) {
                $stmt_chk = $db->prepare("SELECT usuario_id FROM enlaces_venta_online WHERE id = ? LIMIT 1");
                $stmt_chk->bind_param("i", $id);
                $stmt_chk->execute();
                $enl = $stmt_chk->get_result()->fetch_assoc();
                $stmt_chk->close();
                if (!$enl || (int)$enl['usuario_id'] !== $usuario_id) {
                    throw new Exception("No tienes autorización para modificar este enlace.");
                }
            }

            $stmt = $db->prepare("UPDATE enlaces_venta_online SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $estado_nuevo, $id);
            if ($stmt->execute()) {
                echo json_encode(["exito" => true, "mensaje" => "Estado del enlace modificado exitosamente."]);
            } else {
                throw new Exception("Error al actualizar estado: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'eliminar_enlace':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception("ID de enlace inválido.");
            }

            // Si no es admin ni supervisor, verificar pertenencia del enlace
            if (!$es_admin) {
                $stmt_chk = $db->prepare("SELECT usuario_id FROM enlaces_venta_online WHERE id = ? LIMIT 1");
                $stmt_chk->bind_param("i", $id);
                $stmt_chk->execute();
                $enl = $stmt_chk->get_result()->fetch_assoc();
                $stmt_chk->close();
                if (!$enl || (int)$enl['usuario_id'] !== $usuario_id) {
                    throw new Exception("No tienes autorización para eliminar este enlace.");
                }
            }

            $stmt = $db->prepare("DELETE FROM enlaces_venta_online WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(["exito" => true, "mensaje" => "Enlace eliminado exitosamente."]);
            } else {
                throw new Exception("Error al eliminar enlace: " . $stmt->error);
            }
            $stmt->close();
            break;

        default:
            throw new Exception("Acción no definida.");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["exito" => false, "mensaje" => $e->getMessage()]);
}
?>
