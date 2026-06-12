<?php
/**
 * Migración: Datos Institucionales de la Plataforma
 * Asegura que los campos Nombre, RNC, Correo, Dirección, Teléfono, Web y Redes existan en la tabla configuracion_sistema
 */

require_once __DIR__ . '/config.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Iniciando migración de datos institucionales...\n";

    $keys = [
        [
            'clave' => 'EMPRESA_NOMBRE',
            'valor' => 'MAS QUE FIANZAS',
            'tipo' => 'texto',
            'desc' => 'Nombre comercial o razón social de la empresa'
        ],
        [
            'clave' => 'EMPRESA_RNC',
            'valor' => '123-45678-9',
            'tipo' => 'texto',
            'desc' => 'Registro Nacional de Contribuyente (RNC)'
        ],
        [
            'clave' => 'EMPRESA_CORREO',
            'valor' => 'pastorandersonhenriquez@gmail.com',
            'tipo' => 'texto',
            'desc' => 'Correo institucional de la plataforma (remitente y notificaciones)'
        ],
        [
            'clave' => 'EMPRESA_DIRECCION',
            'valor' => 'Av. 27 de Febrero, Santo Domingo, República Dominicana',
            'tipo' => 'texto',
            'desc' => 'Dirección física principal de la empresa'
        ],
        [
            'clave' => 'EMPRESA_TELEFONO',
            'valor' => '809-555-0123',
            'tipo' => 'texto',
            'desc' => 'Teléfono institucional de contacto'
        ],
        [
            'clave' => 'EMPRESA_WEB',
            'valor' => 'https://www.masquefianzas.com.do',
            'tipo' => 'texto',
            'desc' => 'Página web oficial de la plataforma'
        ],
        [
            'clave' => 'EMPRESA_REDES',
            'valor' => json_encode([
                'instagram' => 'https://instagram.com/masquefianzas',
                'facebook' => 'https://facebook.com/masquefianzas',
                'twitter' => '',
                'linkedin' => ''
            ]),
            'tipo' => 'json',
            'desc' => 'Redes sociales de la plataforma'
        ]
    ];

    foreach ($keys as $k) {
        $st_check = $db->prepare("SELECT id FROM configuracion_sistema WHERE clave_config = ?");
        $st_check->bind_param('s', $k['clave']);
        $st_check->execute();
        $res = $st_check->get_result();
        $st_check->close();

        if ($res->num_rows == 0) {
            $st_ins = $db->prepare("INSERT INTO configuracion_sistema (clave_config, valor_config, tipo_valor, descripcion, modificable) VALUES (?, ?, ?, ?, 1)");
            $st_ins->bind_param('ssss', $k['clave'], $k['valor'], $k['tipo'], $k['desc']);
            if ($st_ins->execute()) {
                echo "[OK] Insertado: {$k['clave']}\n";
            } else {
                echo "[ERROR] Fallo al insertar {$k['clave']}: " . $db->error . "\n";
            }
            $st_ins->close();
        } else {
            echo "[INFO] Ya existe: {$k['clave']}\n";
        }
    }

    echo "\nMigración completada exitosamente.\n";

} catch (Exception $e) {
    echo "[CRITICAL ERROR] " . $e->getMessage() . "\n";
}
?>
