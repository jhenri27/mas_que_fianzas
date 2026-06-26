<?php
require_once __DIR__ . '/config.php';
$db = Database::getInstance()->getConnection();
$usuario_id = 1;

$supervisor_id = null;
// Simulate supervisor check
$usr_data = $db->query("SELECT id, username, nombre, apellido, perfil_id, referente_id FROM usuarios WHERE id = $usuario_id")->fetch_assoc();
$supervisor_id = $usr_data['referente_id'] ? (int)$usr_data['referente_id'] : 1;
if ($supervisor_id === $usuario_id) {
    $supervisor_id = ($usuario_id == 1) ? null : 1;
}

$sql_interacciones = "
    SELECT DISTINCT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil,
           (SELECT MAX(fecha_envio) FROM mensajes_chat 
            WHERE (emisor_id = u.id AND receptor_id = ?) 
               OR (emisor_id = ? AND receptor_id = u.id)) as ultima_fecha,
           (SELECT COUNT(*) FROM mensajes_chat 
            WHERE emisor_id = u.id AND receptor_id = ? AND leido = 0) as no_leidos
    FROM usuarios u
    JOIN perfiles p ON u.perfil_id = p.id
    WHERE u.id IN (
        SELECT emisor_id FROM mensajes_chat WHERE receptor_id = ?
        UNION
        SELECT receptor_id FROM mensajes_chat WHERE emisor_id = ?
    )
    ORDER BY ultima_fecha DESC
";

$stmt_int = $db->prepare($sql_interacciones);
if ($stmt_int) {
    $stmt_int->bind_param("iiiii", $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id);
    $stmt_int->execute();
    $res_int = $stmt_int->get_result();
    $conversaciones = [];
    $interacted_ids = [];
    while ($row = $res_int->fetch_assoc()) {
        $row['es_bot'] = 0;
        $row['bot_code'] = null;
        if ($row['username'] === 'bot.helpnow') {
            $row['es_bot'] = 1;
            $row['bot_code'] = 'BHN';
        } elseif ($row['username'] === 'bot.ssindi') {
            $row['es_bot'] = 1;
            $row['bot_code'] = 'BBS';
        }
        $conversaciones[] = $row;
        $interacted_ids[] = (int)$row['id'];
    }
    $stmt_int->close();
    
    echo "Interacted conversations: " . count($conversaciones) . "\n";
    print_r($conversaciones);
} else {
    echo "SQL Prepare failed: " . $db->error . "\n";
}

// Bots query
$bots_res = $db->query("SELECT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil FROM usuarios u JOIN perfiles p ON u.perfil_id = p.id WHERE u.username IN ('bot.helpnow', 'bot.ssindi')");
$bots = [];
if ($bots_res) {
    while ($b_row = $bots_res->fetch_assoc()) {
        if (!in_array((int)$b_row['id'], $interacted_ids)) {
            $b_row['ultima_fecha'] = null;
            $b_row['no_leidos'] = 0;
            $b_row['es_bot'] = 1;
            $b_row['bot_code'] = ($b_row['username'] === 'bot.helpnow') ? 'BHN' : 'BBS';
            $conversaciones[] = $b_row;
            $interacted_ids[] = (int)$b_row['id'];
            $bots[] = $b_row;
        }
    }
}
echo "Added bots: " . count($bots) . "\n";
print_r($bots);

if ($supervisor_id && !in_array($supervisor_id, $interacted_ids)) {
    $stmt_sup = $db->prepare("SELECT u.id, u.username, u.nombre, u.apellido, p.nombre_perfil FROM usuarios u JOIN perfiles p ON u.perfil_id = p.id WHERE u.id = ? LIMIT 1");
    $stmt_sup->bind_param("i", $supervisor_id);
    $stmt_sup->execute();
    $sup_info = $stmt_sup->get_result()->fetch_assoc();
    $stmt_sup->close();
    if ($sup_info) {
        $sup_info['ultima_fecha'] = null;
        $sup_info['no_leidos'] = 0;
        $sup_info['es_supervisor'] = true;
        $sup_info['es_bot'] = 0;
        $sup_info['bot_code'] = null;
        array_unshift($conversaciones, $sup_info);
        echo "Added supervisor: " . $sup_info['nombre'] . "\n";
        print_r($sup_info);
    }
}

echo "Total conversations returned: " . count($conversaciones) . "\n";
?>
