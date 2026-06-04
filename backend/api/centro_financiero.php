<?php
/**
 * API CENTRO FINANCIERO v4.0
 * MAS QUE FIANZAS +QF, SRL
 *
 * Endpoints:
 * - get_resumen          : KPIs de resumen contable
 * - get_diario           : Libro diario de asientos
 * - get_asiento_detalle  : Detalle de líneas de un asiento
 * - get_catalogo         : Catálogo de cuentas
 * - crear_cuenta         : Crear nueva cuenta contable
 * - editar_cuenta        : Editar cuenta existente (CF_EDITAR_CUENTA)
 * - get_ncf_status       : Estado de secuencias NCF
 * - update_ncf_sequence  : Ajustar secuencia NCF (CF_GESTIONAR_NCF)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config.php';
require_once '../ContabilidadManager.php';
require_once '../NCFManager.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Autenticación: sesión PHP o Bearer token
$bearer_token = null;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $m)) $bearer_token = trim($m[1]);

$usuario_id = null;
if (!empty($_SESSION['usuario_id'])) {
    $usuario_id = (int)$_SESSION['usuario_id'];
} elseif (!empty($bearer_token)) {
    $db_t = Database::getInstance()->getConnection();
    $s = $db_t->prepare("SELECT usuario_id FROM sesiones_usuario WHERE token_sesion=? AND activa=1 AND fecha_expiracion>NOW() LIMIT 1");
    if ($s) { $s->bind_param('s', $bearer_token); $s->execute(); $r = $s->get_result(); if ($row = $r->fetch_assoc()) $usuario_id = (int)$row['usuario_id']; $s->close(); }
}

if (!$usuario_id) {
    respuestaJSON(false, 'Sesión no válida o expirada', null, 401);
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_resumen':
            // 1. Disponibilidad (Caja y Bancos 1.1.01)
            $stmt = $db->query("SELECT SUM(debe - haber) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '1.1.01%'");
            $disponibilidad = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            // 2. Primas por Cobrar (1.1.02)
            $stmt = $db->query("SELECT SUM(debe - haber) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '1.1.02%'");
            $primas_cobrar = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            // 3. Comisiones Ganadas (4.1.01)
            // Naturaleza Acreedora: (Haber - Debe)
            $stmt = $db->query("SELECT SUM(haber - debe) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '4.1.01%'");
            $comisiones = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            // 4. ITBIS por Pagar (2.1.02)
            $stmt = $db->query("SELECT SUM(haber - debe) as saldo FROM cf_asiento_lineas WHERE cuenta_codigo LIKE '2.1.02%'");
            $itbis = (float)($stmt->fetch_assoc()['saldo'] ?? 0);

            respuestaJSON(true, "Resumen obtenido", [
                'disponibilidad' => $disponibilidad,
                'primas_cobrar' => $primas_cobrar,
                'comisiones' => $comisiones,
                'itbis' => $itbis
            ]);
            break;

        case 'get_diario':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $sql = "SELECT a.*, SUM(l.debe) as total_monto 
                    FROM cf_asientos a 
                    JOIN cf_asiento_lineas l ON a.id = l.asiento_id 
                    GROUP BY a.id 
                    ORDER BY a.fecha DESC, a.id DESC 
                    LIMIT ?";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $asientos = [];
            while($row = $result->fetch_assoc()) {
                $asientos[] = $row;
            }
            
            respuestaJSON(true, "Libro Diario obtenido", $asientos);
            break;

        case 'get_asiento_detalle':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT l.*, c.nombre as cuenta_nombre 
                                 FROM cf_asiento_lineas l 
                                 JOIN cf_catalogo_cuentas c ON l.cuenta_codigo = c.codigo 
                                 WHERE l.asiento_id = ? 
                                 ORDER BY l.debe DESC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $lineas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            respuestaJSON(true, "Detalle de asiento obtenido", $lineas);
            break;

        case 'get_catalogo':
            $stmt = $db->query("SELECT * FROM cf_catalogo_cuentas ORDER BY codigo");
            $cuentas = $stmt->fetch_all(MYSQLI_ASSOC);
            respuestaJSON(true, "Catálogo obtenido", $cuentas);
            break;

        case 'crear_cuenta':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $codigo = trim($data['codigo'] ?? '');
            $nombre = trim($data['nombre'] ?? '');
            $tipo = trim($data['tipo'] ?? '');
            $naturaleza = trim($data['naturaleza'] ?? '');
            $es_detalle = isset($data['es_detalle']) ? (int)$data['es_detalle'] : 1;

            if (empty($codigo) || empty($nombre) || empty($tipo) || empty($naturaleza)) {
                respuestaJSON(false, "Todos los campos marcados con * son obligatorios.");
                break;
            }

            // Validar si el código ya existe
            $stmt = $db->prepare("SELECT id FROM cf_catalogo_cuentas WHERE codigo = ?");
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                respuestaJSON(false, "El código de cuenta '$codigo' ya está registrado.");
                break;
            }
            $stmt->close();

            // Calcular nivel y cuenta padre
            $tokens = explode('.', $codigo);
            $nivel = count($tokens);
            $cuenta_padre = '';
            if ($nivel > 1) {
                array_pop($tokens);
                $cuenta_padre = implode('.', $tokens);
            }

            // Insertar la cuenta
            $stmt = $db->prepare("INSERT INTO cf_catalogo_cuentas (codigo, nombre, tipo, naturaleza, nivel, cuenta_padre, es_detalle, activa) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssssisi", $codigo, $nombre, $tipo, $naturaleza, $nivel, $cuenta_padre, $es_detalle);
            if ($stmt->execute()) {
                respuestaJSON(true, "Cuenta '$nombre' registrada con éxito.");
            } else {
                respuestaJSON(false, "Error al registrar la cuenta: " . $stmt->error);
            }
            $stmt->close();
            break;

        // =============================================================
        // POST: editar_cuenta — Requiere CF_EDITAR_CUENTA — AUDITADO
        // =============================================================
        case 'editar_cuenta':
            if (!tienePermiso($usuario_id, 'CF_EDITAR_CUENTA')) {
                respuestaJSON(false, 'Acceso denegado: se requiere permiso CF_EDITAR_CUENTA', null, 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) { $data = $_POST; }

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $justificacion = trim($data['justificacion'] ?? '');

            if ($id <= 0) {
                respuestaJSON(false, 'ID de cuenta inválido.');
                break;
            }
            if (strlen($justificacion) < 15) {
                respuestaJSON(false, 'La justificación debe tener al menos 15 caracteres.');
                break;
            }

            // Obtener cuenta actual
            $stmt = $db->prepare("SELECT * FROM cf_catalogo_cuentas WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $cuenta_actual = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$cuenta_actual) {
                respuestaJSON(false, 'Cuenta no encontrada.');
                break;
            }

            $codigo_nuevo = trim($data['codigo'] ?? $cuenta_actual['codigo']);
            $nombre_nuevo = trim($data['nombre'] ?? $cuenta_actual['nombre']);
            $tipo_nuevo = trim($data['tipo'] ?? $cuenta_actual['tipo']);
            $naturaleza_nueva = trim($data['naturaleza'] ?? $cuenta_actual['naturaleza']);
            $es_detalle_nuevo = isset($data['es_detalle']) ? (int)$data['es_detalle'] : (int)$cuenta_actual['es_detalle'];
            $activa_nueva = isset($data['activa']) ? (int)$data['activa'] : (int)$cuenta_actual['activa'];

            $codigo_anterior = $cuenta_actual['codigo'];
            $es_detalle_anterior = (int)$cuenta_actual['es_detalle'];

            // Validación: si el código cambia
            if ($codigo_nuevo !== $codigo_anterior) {
                // Verificar que el nuevo código no exista en otra cuenta
                $stmt = $db->prepare("SELECT id FROM cf_catalogo_cuentas WHERE codigo = ? AND id != ?");
                $stmt->bind_param("si", $codigo_nuevo, $id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    respuestaJSON(false, "El código de cuenta '$codigo_nuevo' ya está registrado en otra cuenta.");
                    break;
                }
                $stmt->close();

                // Verificar que el código anterior no tenga transacciones
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM cf_asiento_lineas WHERE cuenta_codigo = ?");
                $stmt->bind_param("s", $codigo_anterior);
                $stmt->execute();
                $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();

                if ($cnt > 0) {
                    respuestaJSON(false, "No se puede cambiar el código: la cuenta '$codigo_anterior' tiene $cnt transacciones registradas.");
                    break;
                }

                // Actualizar cuentas hijas que referencian el código anterior como padre
                $stmt = $db->prepare("UPDATE cf_catalogo_cuentas SET cuenta_padre = ? WHERE cuenta_padre = ?");
                $stmt->bind_param("ss", $codigo_nuevo, $codigo_anterior);
                $stmt->execute();
                $stmt->close();
            }

            // Validación: si es_detalle cambia de 1 a 0
            if ($es_detalle_anterior === 1 && $es_detalle_nuevo === 0) {
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM cf_asiento_lineas WHERE cuenta_codigo = ?");
                $stmt->bind_param("s", $codigo_nuevo);
                $stmt->execute();
                $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();

                if ($cnt > 0) {
                    respuestaJSON(false, "No se puede cambiar a cuenta de grupo: tiene $cnt transacciones registradas.");
                    break;
                }
            }

            // Validación: si es_detalle cambia de 0 a 1
            if ($es_detalle_anterior === 0 && $es_detalle_nuevo === 1) {
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM cf_catalogo_cuentas WHERE cuenta_padre = ?");
                $stmt->bind_param("s", $codigo_nuevo);
                $stmt->execute();
                $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();

                if ($cnt > 0) {
                    respuestaJSON(false, "No se puede cambiar a cuenta de detalle: tiene $cnt subcuentas registradas.");
                    break;
                }
            }

            // Recalcular nivel y cuenta_padre desde el código (posiblemente nuevo)
            $tokens = explode('.', $codigo_nuevo);
            $nivel = count($tokens);
            $cuenta_padre = '';
            if ($nivel > 1) {
                array_pop($tokens);
                $cuenta_padre = implode('.', $tokens);
            }

            // Guardar valor anterior para auditoría
            $valor_anterior = [
                'codigo' => $cuenta_actual['codigo'],
                'nombre' => $cuenta_actual['nombre'],
                'tipo' => $cuenta_actual['tipo'],
                'naturaleza' => $cuenta_actual['naturaleza'],
                'es_detalle' => $es_detalle_anterior,
                'activa' => (int)$cuenta_actual['activa']
            ];

            // Ejecutar UPDATE
            $stmt = $db->prepare("UPDATE cf_catalogo_cuentas SET codigo = ?, nombre = ?, tipo = ?, naturaleza = ?, nivel = ?, cuenta_padre = ?, es_detalle = ?, activa = ? WHERE id = ?");
            $stmt->bind_param("ssssisiii", $codigo_nuevo, $nombre_nuevo, $tipo_nuevo, $naturaleza_nueva, $nivel, $cuenta_padre, $es_detalle_nuevo, $activa_nueva, $id);

            if (!$stmt->execute()) {
                respuestaJSON(false, "Error al actualizar la cuenta: " . $stmt->error);
                break;
            }
            $stmt->close();

            $valor_nuevo = [
                'codigo' => $codigo_nuevo,
                'nombre' => $nombre_nuevo,
                'tipo' => $tipo_nuevo,
                'naturaleza' => $naturaleza_nueva,
                'es_detalle' => $es_detalle_nuevo,
                'activa' => $activa_nueva
            ];

            // Auditoría
            registrarAjuste(
                $usuario_id,
                'centro_financiero',
                'cf_catalogo_cuentas',
                $id,
                $valor_anterior,
                $valor_nuevo,
                $justificacion
            );

            logAudit($usuario_id, 'editar_cuenta', 'centro_financiero', 'CF_EDITAR_CUENTA', "Editó cuenta ID=$id código=$codigo_nuevo", 'exitoso', null, 'cf_catalogo_cuentas');

            respuestaJSON(true, "Cuenta '$nombre_nuevo' actualizada con éxito. Cambio registrado en historial de auditoría.");
            break;

        // =============================================================
        // GET: get_ncf_status — Estado de secuencias NCF
        // =============================================================
        case 'get_ncf_status':
            $stmt = $db->query("SELECT * FROM cf_ncf_secuencias ORDER BY tipo");
            $secuencias = $stmt->fetch_all(MYSQLI_ASSOC);

            $logs = [];
            try {
                $stmt_log = $db->prepare("SELECT * FROM cf_ncf_log ORDER BY fecha_emision DESC LIMIT 20");
                $stmt_log->execute();
                $logs = $stmt_log->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_log->close();
            } catch (Exception $e) {
                // Tabla cf_ncf_log puede no existir aún
                $logs = [];
            }

            respuestaJSON(true, "Estado NCF obtenido", [
                'secuencias' => $secuencias,
                'logs' => $logs
            ]);
            break;

        // =============================================================
        // POST: update_ncf_sequence — Requiere CF_GESTIONAR_NCF — AUDITADO
        // =============================================================
        case 'update_ncf_sequence':
            if (!tienePermiso($usuario_id, 'CF_GESTIONAR_NCF')) {
                respuestaJSON(false, 'Acceso denegado: se requiere permiso CF_GESTIONAR_NCF', null, 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) { $data = $_POST; }

            $tipo = trim($data['tipo'] ?? '');
            $nuevo_valor = isset($data['valor']) ? (int)$data['valor'] : -1;
            $justificacion = trim($data['justificacion'] ?? '');

            if (empty($tipo)) {
                respuestaJSON(false, 'El tipo de secuencia es obligatorio.');
                break;
            }
            if ($nuevo_valor < 0) {
                respuestaJSON(false, 'El valor de secuencia es obligatorio y debe ser >= 0.');
                break;
            }
            if (strlen($justificacion) < 15) {
                respuestaJSON(false, 'La justificación debe tener al menos 15 caracteres.');
                break;
            }

            // Obtener secuencia actual
            $stmt = $db->prepare("SELECT secuencia_actual, secuencia_final FROM cf_ncf_secuencias WHERE tipo = ?");
            $stmt->bind_param("s", $tipo);
            $stmt->execute();
            $seq = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$seq) {
                respuestaJSON(false, "Tipo de secuencia '$tipo' no encontrado.");
                break;
            }

            $old_val = (int)$seq['secuencia_actual'];
            $secuencia_final = (int)$seq['secuencia_final'];

            if ($nuevo_valor > $secuencia_final) {
                respuestaJSON(false, "El valor no puede superar la secuencia final ($secuencia_final).");
                break;
            }

            // Actualizar secuencia
            $stmt = $db->prepare("UPDATE cf_ncf_secuencias SET secuencia_actual = ? WHERE tipo = ?");
            $stmt->bind_param("is", $nuevo_valor, $tipo);

            if (!$stmt->execute()) {
                respuestaJSON(false, "Error al actualizar la secuencia: " . $stmt->error);
                break;
            }
            $stmt->close();

            // Auditoría
            registrarAjuste(
                $usuario_id,
                'centro_financiero',
                'cf_ncf_secuencias',
                0,
                ['tipo' => $tipo, 'secuencia_actual' => $old_val],
                ['tipo' => $tipo, 'secuencia_actual' => $nuevo_valor],
                $justificacion
            );

            logAudit($usuario_id, 'ajuste_ncf', 'centro_financiero', 'CF_GESTIONAR_NCF', "Ajustó secuencia NCF tipo=$tipo de $old_val a $nuevo_valor", 'exitoso', null, 'cf_ncf_secuencias');

            respuestaJSON(true, "Secuencia NCF tipo '$tipo' actualizada de $old_val a $nuevo_valor. Cambio registrado en historial de auditoría.");
            break;

        default:
            respuestaJSON(false, "Acción no definida");
            break;
    }
} catch (Exception $e) {
    respuestaJSON(false, $e->getMessage());
}
