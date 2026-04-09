<?php
require_once 'config.php';
require_once 'UsuarioManager.php';
$db = Database::getInstance()->getConnection();
$um = new UsuarioManager();

echo "Populando códigos de usuario para registros existentes...\n";

$sql = "SELECT id, perfil_id FROM usuarios WHERE codigo_usuario IS NULL OR codigo_usuario = ''";
$result = $db->query($sql);

if ($result->num_rows > 0) {
    // Necesito acceder al método privado o replicar la lógica
    // Como replicar es más seguro que cambiar visibilidad temporalmente:
    
    while ($u = $result->fetch_assoc()) {
        $id = $u['id'];
        $perfil_id = $u['perfil_id'];
        
        // REPLICAR LÓGICA DE GENERACIÓN
        $stmt_p = $db->prepare("SELECT nivel_jerarquico, siglas FROM perfiles WHERE id = ?");
        $stmt_p->bind_param("i", $perfil_id);
        $stmt_p->execute();
        $perfil = $stmt_p->get_result()->fetch_assoc();
        $stmt_p->close();
        
        if ($perfil) {
            $jerarquia = str_pad($perfil['nivel_jerarquico'], 2, '0', STR_PAD_LEFT);
            $siglas = !empty($perfil['siglas']) ? $perfil['siglas'] : 'USU';
            
            // Contar cuántos tienen este perfil YA con código o antes de este ID
            $stmt_count = $db->prepare("SELECT COUNT(*) as total FROM usuarios WHERE perfil_id = ? AND id < ?");
            $stmt_count->bind_param("ii", $perfil_id, $id);
            $stmt_count->execute();
            $count = $stmt_count->get_result()->fetch_assoc()['total'];
            $stmt_count->close();
            
            $codigo = $jerarquia . $siglas . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            
            $stmt_upd = $db->prepare("UPDATE usuarios SET codigo_usuario = ? WHERE id = ?");
            $stmt_upd->bind_param("si", $codigo, $id);
            $stmt_upd->execute();
            $stmt_upd->close();
            
            echo "Usuario ID $id -> Código $codigo\n";
        }
    }
} else {
    echo "No hay usuarios sin código.\n";
}

echo "Proceso finalizado.\n";
?>
