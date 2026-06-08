<?php
/**
 * API de Gestión de Productos y Deducibles — v1.0
 * MAS QUE FIANZAS — Core Asegurador
 * ==========================================================
 * Permite listar, crear y editar productos de seguros,
 * y gestionar los deducibles asociados por compañía aseguradora.
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

try {
    // Verificar permisos
    if (!tienePermiso($usuario_actual, 'TAB_PRO_CONSULTAR') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
        http_response_code(403);
        echo json_encode(["exito" => false, "mensaje" => "Acceso restringido: No posee permisos (TAB_PRO_CONSULTAR)."]);
        exit;
    }

    if ($method === 'GET') {
        if ($action === 'listar') {
            $sql = "SELECT p.*, u.username as creador_username,
                           (SELECT COUNT(*) FROM producto_deducibles WHERE producto_id = p.id AND activo = 1) as cant_deducibles
                    FROM productos p
                    LEFT JOIN usuarios u ON p.creado_por = u.id
                    ORDER BY p.nombre_producto ASC";
            $res = $db->query($sql);
            $productos = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['vigencia_dias'] = (int)$row['vigencia_dias'];
                $row['prima_base'] = (float)$row['prima_base'];
                $row['comision_venta'] = (float)$row['comision_venta'];
                $row['cant_deducibles'] = (int)$row['cant_deducibles'];
                $productos[] = $row;
            }
            echo json_encode(["exito" => true, "data" => $productos]);
            exit;
            
        } elseif ($action === 'obtener') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception("ID de producto no especificado.");
            
            $stmt = $db->prepare("SELECT * FROM productos WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$producto) throw new Exception("Producto no encontrado.");
            
            $producto['id'] = (int)$producto['id'];
            $producto['vigencia_dias'] = (int)$producto['vigencia_dias'];
            $producto['prima_base'] = (float)$producto['prima_base'];
            $producto['comision_venta'] = (float)$producto['comision_venta'];

            // Deducibles
            $stmt_d = $db->prepare("SELECT d.*, c.nombre as compania_nombre 
                                    FROM producto_deducibles d
                                    LEFT JOIN companias_registradas c ON d.compania_id = c.id
                                    WHERE d.producto_id = ?
                                    ORDER BY c.nombre ASC, d.concepto ASC");
            $stmt_d->bind_param("i", $id);
            $stmt_d->execute();
            $res_d = $stmt_d->get_result();
            $deducibles = [];
            while ($row = $res_d->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['producto_id'] = (int)$row['producto_id'];
                $row['compania_id'] = (int)$row['compania_id'];
                $row['porcentaje'] = (float)$row['porcentaje'];
                $row['minimo_dop'] = (float)$row['minimo_dop'];
                $row['activo'] = (int)$row['activo'];
                $deducibles[] = $row;
            }
            $stmt_d->close();
            
            $producto['deducibles'] = $deducibles;
            echo json_encode(["exito" => true, "data" => $producto]);
            exit;

        } elseif ($action === 'listar_deducibles') {
            $producto_id = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;
            if (!$producto_id) throw new Exception("ID de producto no especificado.");

            $stmt_d = $db->prepare("SELECT d.*, c.nombre as compania_nombre 
                                    FROM producto_deducibles d
                                    LEFT JOIN companias_registradas c ON d.compania_id = c.id
                                    WHERE d.producto_id = ?
                                    ORDER BY c.nombre ASC, d.concepto ASC");
            $stmt_d->bind_param("i", $producto_id);
            $stmt_d->execute();
            $res_d = $stmt_d->get_result();
            $deducibles = [];
            while ($row = $res_d->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['producto_id'] = (int)$row['producto_id'];
                $row['compania_id'] = (int)$row['compania_id'];
                $row['porcentaje'] = (float)$row['porcentaje'];
                $row['minimo_dop'] = (float)$row['minimo_dop'];
                $row['activo'] = (int)$row['activo'];
                $deducibles[] = $row;
            }
            $stmt_d->close();
            
            echo json_encode(["exito" => true, "data" => $deducibles]);
            exit;
            
        } elseif ($action === 'aseguradoras') {
            // Obtener aseguradoras registradas para poder asociar deducibles
            $sql = "SELECT id, nombre FROM companias_registradas WHERE tipo = 'aseguradora' AND estado = 1 ORDER BY nombre ASC";
            $res = $db->query($sql);
            $aseguradoras = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $aseguradoras[] = $row;
            }
            echo json_encode(["exito" => true, "data" => $aseguradoras]);
            exit;
        } else {
            throw new Exception("Acción GET no soportada.");
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        if ($action === 'guardar') {
            if (!tienePermiso($usuario_actual, 'TAB_PRO_NUEVO') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
                throw new Exception("No tiene autorización para registrar o modificar productos.");
            }

            $id = isset($input['id']) ? (int)$input['id'] : null;
            $codigo = trim($input['codigo_producto'] ?? '');
            $nombre = trim($input['nombre_producto'] ?? '');
            $descripcion = trim($input['descripcion'] ?? '');
            $tipo_vehiculo = trim($input['tipo_vehiculo'] ?? '');
            $capacidad_motor = trim($input['capacidad_motor'] ?? '');
            $uso_vehiculo = trim($input['uso_vehiculo'] ?? '');
            $vigencia = isset($input['vigencia_dias']) ? (int)$input['vigencia_dias'] : 365;
            $estado = trim($input['estado'] ?? 'activo');
            $prima_base = isset($input['prima_base']) ? floatval($input['prima_base']) : 0.00;
            $comision = isset($input['comision_venta']) ? floatval($input['comision_venta']) : 0.00;

            if (empty($codigo)) throw new Exception("El código del producto es obligatorio.");
            if (empty($nombre)) throw new Exception("El nombre del producto es obligatorio.");

            $db->begin_transaction();

            // Verificar duplicado de código
            $sql_chk = "SELECT id FROM productos WHERE codigo_producto = ? AND id != ?";
            $id_check = $id ?? 0;
            $stmt_chk = $db->prepare($sql_chk);
            $stmt_chk->bind_param("si", $codigo, $id_check);
            $stmt_chk->execute();
            $res_chk = $stmt_chk->get_result();
            $stmt_chk->close();
            if ($res_chk->num_rows > 0) {
                throw new Exception("El código de producto '{$codigo}' ya está registrado.");
            }

            if ($id) {
                // EDITAR PRODUCTO
                $stmt_upd = $db->prepare("UPDATE productos 
                                          SET codigo_producto = ?, nombre_producto = ?, descripcion = ?, 
                                              tipo_vehiculo = ?, capacidad_motor = ?, uso_vehiculo = ?, 
                                              vigencia_dias = ?, estado = ?, prima_base = ?, comision_venta = ?, 
                                              fecha_modificacion = NOW()
                                          WHERE id = ?");
                $stmt_upd->bind_param("ssssssisddi", 
                    $codigo, $nombre, $descripcion, $tipo_vehiculo, $capacidad_motor, 
                    $uso_vehiculo, $vigencia, $estado, $prima_base, $comision, $id
                );
                $stmt_upd->execute();
                $stmt_upd->close();
                
                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Producto actualizado con éxito.", "id" => $id]);
                exit;
            } else {
                // CREAR PRODUCTO
                $stmt_ins = $db->prepare("INSERT INTO productos (codigo_producto, nombre_producto, descripcion, 
                                                                 tipo_vehiculo, capacidad_motor, uso_vehiculo, 
                                                                 vigencia_dias, estado, prima_base, comision_venta, creado_por) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("ssssssisddi", 
                    $codigo, $nombre, $descripcion, $tipo_vehiculo, $capacidad_motor, 
                    $uso_vehiculo, $vigencia, $estado, $prima_base, $comision, $usuario_actual
                );
                $stmt_ins->execute();
                $new_id = $db->insert_id;
                $stmt_ins->close();
                
                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Producto registrado con éxito.", "id" => $new_id]);
                exit;
            }
            
        } elseif ($action === 'guardar_deducible') {
            if (!tienePermiso($usuario_actual, 'TAB_PRO_DEDUCIBLES') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
                throw new Exception("No tiene autorización para gestionar deducibles.");
            }

            $id = isset($input['id']) ? (int)$input['id'] : null;
            $producto_id = (int)($input['producto_id'] ?? 0);
            $compania_id = (int)($input['compania_id'] ?? 0);
            $concepto = trim($input['concepto'] ?? '');
            $porcentaje = isset($input['porcentaje']) ? floatval($input['porcentaje']) : 0.00;
            $minimo_dop = isset($input['minimo_dop']) ? floatval($input['minimo_dop']) : 0.00;
            $activo = isset($input['activo']) ? (int)$input['activo'] : 1;

            if (!$producto_id) throw new Exception("ID de producto obligatorio.");
            if (!$compania_id) throw new Exception("Compañía aseguradora obligatoria.");
            if (empty($concepto)) throw new Exception("El concepto del deducible es obligatorio.");

            $db->begin_transaction();

            if ($id) {
                $stmt_upd = $db->prepare("UPDATE producto_deducibles 
                                          SET compania_id = ?, concepto = ?, porcentaje = ?, minimo_dop = ?, 
                                              activo = ?, updated_at = NOW()
                                          WHERE id = ? AND producto_id = ?");
                $stmt_upd->bind_param("isddiii", $compania_id, $concepto, $porcentaje, $minimo_dop, $activo, $id, $producto_id);
                $stmt_upd->execute();
                $stmt_upd->close();
                
                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Deducible actualizado con éxito.", "id" => $id]);
                exit;
            } else {
                $stmt_ins = $db->prepare("INSERT INTO producto_deducibles (producto_id, compania_id, concepto, porcentaje, minimo_dop, activo) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("iisddi", $producto_id, $compania_id, $concepto, $porcentaje, $minimo_dop, $activo);
                $stmt_ins->execute();
                $new_id = $db->insert_id;
                $stmt_ins->close();
                
                $db->commit();
                echo json_encode(["exito" => true, "mensaje" => "Deducible registrado con éxito.", "id" => $new_id]);
                exit;
            }

        } elseif ($action === 'toggle_deducible') {
            if (!tienePermiso($usuario_actual, 'TAB_PRO_DEDUCIBLES') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
                throw new Exception("No tiene autorización para gestionar deducibles.");
            }

            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if (!$id) throw new Exception("ID de deducible no especificado.");

            $db->begin_transaction();

            $stmt_sel = $db->prepare("SELECT activo FROM producto_deducibles WHERE id = ? LIMIT 1");
            $stmt_sel->bind_param("i", $id);
            $stmt_sel->execute();
            $ded = $stmt_sel->get_result()->fetch_assoc();
            $stmt_sel->close();

            if (!$ded) throw new Exception("Deducible no encontrado.");

            $nuevo_estado = $ded['activo'] == 1 ? 0 : 1;

            $stmt_upd = $db->prepare("UPDATE producto_deducibles SET activo = ?, updated_at = NOW() WHERE id = ?");
            $stmt_upd->bind_param("ii", $nuevo_estado, $id);
            $stmt_upd->execute();
            $stmt_upd->close();

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Estatus del deducible actualizado.", "activo" => $nuevo_estado]);
            exit;

        } elseif ($action === 'eliminar_deducible') {
            if (!tienePermiso($usuario_actual, 'TAB_PRO_DEDUCIBLES') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
                throw new Exception("No tiene autorización para gestionar deducibles.");
            }

            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if (!$id) throw new Exception("ID de deducible no especificado.");

            $db->begin_transaction();

            $stmt_del = $db->prepare("DELETE FROM producto_deducibles WHERE id = ?");
            $stmt_del->bind_param("i", $id);
            $stmt_del->execute();
            $stmt_del->close();

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Deducible eliminado correctamente."]);
            exit;

        } elseif ($action === 'importar_masivo') {
            if (!tienePermiso($usuario_actual, 'TAB_PRO_IMPORTAR') && $usuario_actual !== 1 && !tienePermiso($usuario_actual, 'PAG_TOTAL')) {
                throw new Exception("No tiene autorización para importar tarifarios.");
            }

            // Recibe JSON de productos y deducibles para cargarlos en masa
            $productos = $input['productos'] ?? [];
            if (empty($productos) || !is_array($productos)) {
                throw new Exception("Listado de productos vacío o inválido.");
            }

            $db->begin_transaction();
            $insertados = 0;
            $errores = [];

            foreach ($productos as $idx => $p) {
                try {
                    $codigo = trim($p['codigo_producto'] ?? '');
                    $nombre = trim($p['nombre_producto'] ?? '');
                    $descripcion = trim($p['descripcion'] ?? '');
                    $tipo_vehiculo = trim($p['tipo_vehiculo'] ?? '');
                    $capacidad_motor = trim($p['capacidad_motor'] ?? '');
                    $uso_vehiculo = trim($p['uso_vehiculo'] ?? '');
                    $vigencia = isset($p['vigencia_dias']) ? (int)$p['vigencia_dias'] : 365;
                    $prima_base = isset($p['prima_base']) ? floatval($p['prima_base']) : 0.00;
                    $comision = isset($p['comision_venta']) ? floatval($p['comision_venta']) : 0.00;

                    if (empty($codigo) || empty($nombre)) {
                        throw new Exception("Fila " . ($idx + 1) . ": Código y Nombre son obligatorios.");
                    }

                    // Insertar producto
                    $stmt_ins = $db->prepare("INSERT INTO productos (codigo_producto, nombre_producto, descripcion, 
                                                                     tipo_vehiculo, capacidad_motor, uso_vehiculo, 
                                                                     vigencia_dias, estado, prima_base, comision_venta, creado_por) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', ?, ?, ?)
                                              ON DUPLICATE KEY UPDATE nombre_producto = VALUES(nombre_producto), descripcion = VALUES(descripcion), prima_base = VALUES(prima_base)");
                    $stmt_ins->bind_param("ssssssisddi", 
                        $codigo, $nombre, $descripcion, $tipo_vehiculo, $capacidad_motor, 
                        $uso_vehiculo, $vigencia, $prima_base, $comision, $usuario_actual
                    );
                    $stmt_ins->execute();
                    
                    // Si se insertó o actualizó, obtener ID
                    $prod_id = $db->insert_id;
                    if (!$prod_id) {
                        $stmt_getId = $db->prepare("SELECT id FROM productos WHERE codigo_producto = ? LIMIT 1");
                        $stmt_getId->bind_param("s", $codigo);
                        $stmt_getId->execute();
                        $prod_id = $stmt_getId->get_result()->fetch_assoc()['id'];
                        $stmt_getId->close();
                    }
                    $stmt_ins->close();

                    // Si vienen deducibles en el objeto, importarlos
                    if (!empty($p['deducibles']) && is_array($p['deducibles'])) {
                        foreach ($p['deducibles'] as $d) {
                            $comp_nombre = trim($d['compania_nombre'] ?? '');
                            $concepto = trim($d['concepto'] ?? '');
                            $porc = floatval($d['porcentaje'] ?? 0.00);
                            $min = floatval($d['minimo_dop'] ?? 0.00);

                            if (empty($comp_nombre) || empty($concepto)) continue;

                            // Buscar id de compañía
                            $stmt_c = $db->prepare("SELECT id FROM companias_registradas WHERE nombre = ? AND tipo = 'aseguradora' LIMIT 1");
                            $stmt_c->bind_param("s", $comp_nombre);
                            $stmt_c->execute();
                            $comp = $stmt_c->get_result()->fetch_assoc();
                            $stmt_c->close();

                            if ($comp) {
                                $comp_id = $comp['id'];
                                $stmt_ded = $db->prepare("INSERT INTO producto_deducibles (producto_id, compania_id, concepto, porcentaje, minimo_dop, activo)
                                                          VALUES (?, ?, ?, ?, ?, 1)
                                                          ON DUPLICATE KEY UPDATE porcentaje = VALUES(porcentaje), minimo_dop = VALUES(minimo_dop)");
                                $stmt_ded->bind_param("iisdd", $prod_id, $comp_id, $concepto, $porc, $min);
                                $stmt_ded->execute();
                                $stmt_ded->close();
                            }
                        }
                    }

                    $insertados++;
                } catch (Exception $ex) {
                    $errores[] = $ex->getMessage();
                }
            }

            if (!empty($errores)) {
                $db->rollback();
                echo json_encode(["exito" => false, "mensaje" => "Error durante la importación masiva.", "errores" => $errores]);
                exit;
            }

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Importación masiva completada. Productos procesados: $insertados."]);
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
