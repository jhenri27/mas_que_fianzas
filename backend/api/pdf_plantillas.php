<?php
require_once dirname(__FILE__) . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            // Obtener plantilla
            $stmt = $db->prepare("SELECT * FROM pdf_plantillas WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $plantilla = $stmt->get_result()->fetch_assoc();

            if ($plantilla) {
                // Obtener campos
                $stmt = $db->prepare("SELECT * FROM pdf_campos WHERE plantilla_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $plantilla['campos'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                echo json_encode(["exito" => true, "data" => $plantilla]);
            } else {
                echo json_encode(["exito" => false, "mensaje" => "Plantilla no encontrada"]);
            }
        } else {
            // Listar todas
            $result = $db->query("SELECT * FROM pdf_plantillas ORDER BY id DESC");
            echo json_encode(["exito" => true, "data" => $result->fetch_all(MYSQLI_ASSOC)]);
        }
    } elseif ($method === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'upload') {
            // Subir nuevo archivo base
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(["exito" => false, "mensaje" => "Error al subir archivo"]);
                exit;
            }

            $nombre = $_POST['nombre'] ?? 'Plantilla Sin Nombre';
            $aseguradora = $_POST['aseguradora_nombre'] ?? null;
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'])) {
                echo json_encode(["exito" => false, "mensaje" => "Formato no soportado. Use PDF, PNG o JPG."]);
                exit;
            }

            $uploadDir = dirname(__FILE__) . '/../../uploads/plantillas_pdf/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid('plantilla_') . '.' . $ext;
            $destPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Insertar en BD
                $stmt = $db->prepare("INSERT INTO pdf_plantillas (nombre, archivo_base, tipo_archivo, aseguradora_nombre) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nombre, $fileName, $ext, $aseguradora);
                $stmt->execute();
                $id = $stmt->insert_id;

                echo json_encode(["exito" => true, "mensaje" => "Plantilla subida", "id" => $id]);
            } else {
                echo json_encode(["exito" => false, "mensaje" => "Error al mover el archivo"]);
            }
        } else {
            // Guardar coordenadas de campos (Recibe JSON payload)
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['action']) && $data['action'] === 'save_fields') {
                $plantillaId = intval($data['plantilla_id']);
                $campos = $data['campos'] ?? [];

                $db->begin_transaction();
                try {
                    // Limpiar campos anteriores
                    $stmt = $db->prepare("DELETE FROM pdf_campos WHERE plantilla_id = ?");
                    $stmt->bind_param("i", $plantillaId);
                    $stmt->execute();

                    // Insertar nuevos
                    $stmt = $db->prepare("INSERT INTO pdf_campos (plantilla_id, variable, pos_x, pos_y, font_size, color, font_weight) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($campos as $c) {
                        $v = $c['variable'];
                        $x = $c['x'];
                        $y = $c['y'];
                        $s = $c['size'] ?? 10;
                        $color = $c['color'] ?? '#000000';
                        $weight = $c['weight'] ?? 'normal';
                        $stmt->bind_param("isddiss", $plantillaId, $v, $x, $y, $s, $color, $weight);
                        $stmt->execute();
                    }
                    $db->commit();
                    echo json_encode(["exito" => true, "mensaje" => "Campos guardados correctamente"]);
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(["exito" => false, "mensaje" => "Error al guardar campos: " . $e->getMessage()]);
                }
            }
        }
    }
} catch (Exception $e) {
    echo json_encode(["exito" => false, "mensaje" => "Error del servidor: " . $e->getMessage()]);
}
?>
