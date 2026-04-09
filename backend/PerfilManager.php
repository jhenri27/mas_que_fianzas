<?php
/**
 * Clase de Gestión de Perfiles y Permisos
 * MAS QUE FIANZAS
 */

require_once dirname(__FILE__) . '/config.php';

class PerfilManager {
    private $db;
    private $malla_permisos_predefinida = []; // Malla de permisos según la tabla del requisito

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->inicializarMallaPermisos();
    }

    /**
     * Inicializar la malla de permisos según la tabla de requisitos
     */
    private function inicializarMallaPermisos() {
        $this->malla_permisos_predefinida = [
            'Dashboard' => [
                'Administrador' => 'Completo',
                'Gerente Técnico' => 'Completo',
                'Gerente Contador' => 'Completo',
                'Gerente Comercial' => 'Completo',
                'Socio Comercial' => 'Parcial',
                'Cajero' => 'Parcial',
                'Auditor' => 'Completo',
                'Usuario' => 'Parcial'
            ],
            'Clientes' => [
                'Administrador' => 'Crear/Editar',
                'Gerente Técnico' => 'Consultar/Editar',
                'Gerente Contador' => 'Consultar',
                'Gerente Comercial' => 'Crear/Editar',
                'Socio Comercial' => 'Consultar',
                'Cajero' => 'No',
                'Auditor' => 'Consultar',
                'Usuario' => 'Consultar'
            ],
            'Pólizas' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'Crear/Editar',
                'Gerente Contador' => 'Consultar',
                'Gerente Comercial' => 'Crear/Editar',
                'Socio Comercial' => 'Consultar',
                'Cajero' => 'No',
                'Auditor' => 'Consultar',
                'Usuario' => 'Consultar'
            ],
            'Fianzas' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'Crear/Editar',
                'Gerente Contador' => 'Consultar',
                'Gerente Comercial' => 'Crear/Editar',
                'Socio Comercial' => 'Consultar',
                'Cajero' => 'No',
                'Auditor' => 'Consultar',
                'Usuario' => 'Consultar'
            ],
            'Pagos' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'No',
                'Gerente Contador' => 'Validar/Reportes',
                'Gerente Comercial' => 'Consultar',
                'Socio Comercial' => 'No',
                'Cajero' => 'Registrar',
                'Auditor' => 'Consultar',
                'Usuario' => 'Consultar propio'
            ],
            'Cotizaciones' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'Crear/Editar',
                'Gerente Contador' => 'No',
                'Gerente Comercial' => 'Crear/Editar',
                'Socio Comercial' => 'Crear/Editar',
                'Cajero' => 'No',
                'Auditor' => 'Consultar',
                'Usuario' => 'Crear propio'
            ],
            'Productos' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'Crear/Editar',
                'Gerente Contador' => 'Consultar',
                'Gerente Comercial' => 'Consultar',
                'Socio Comercial' => 'No',
                'Cajero' => 'No',
                'Auditor' => 'Consultar',
                'Usuario' => 'No'
            ],
            'Configuración' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'Parámetros técnicos',
                'Gerente Contador' => 'Parámetros contables',
                'Gerente Comercial' => 'No',
                'Socio Comercial' => 'No',
                'Cajero' => 'No',
                'Auditor' => 'Consultar',
                'Usuario' => 'No'
            ],
            'Reportes' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'Técnicos',
                'Gerente Contador' => 'Financieros',
                'Gerente Comercial' => 'Comerciales',
                'Socio Comercial' => 'Comerciales',
                'Cajero' => 'Caja',
                'Auditor' => 'Todos',
                'Usuario' => 'Limitados'
            ],
            'Siniestros' => [
                'Administrador' => 'Total',
                'Gerente Técnico' => 'Crear/Editar',
                'Gerente Contador' => 'Consultar',
                'Gerente Comercial' => 'Seguimiento',
                'Socio Comercial' => 'Consultar',
                'Cajero' => 'No',
                'Auditor' => 'Consultar',
                'Usuario' => 'Consultar propio'
            ]
        ];
    }

    /**
     * Crear nuevo perfil
     */
    public function crearPerfil($datos, $usuario_creador) {
        try {
            if (!tienePermiso($usuario_creador, 'PER_GESTIONAR') && !tienePermiso($usuario_creador, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para crear perfiles'];
            }

            // Validar datos requeridos
            $requeridos = ['nombre_perfil', 'nivel_jerarquico'];
            foreach ($requeridos as $campo) {
                if (empty($datos[$campo])) {
                    return ['exito' => false, 'mensaje' => "El campo $campo es requerido"];
                }
            }

            // Verificar que el nombre sea único
            $sql_verifica = "SELECT id FROM perfiles WHERE nombre_perfil = ?";
            $stmt = $this->db->prepare($sql_verifica);
            $stmt->bind_param("s", $datos['nombre_perfil']);
            $stmt->execute();
            if ($stmt->num_rows > 0) {
                $stmt->close();
                return ['exito' => false, 'mensaje' => 'El nombre del perfil ya existe'];
            }
            $stmt->close();

            // Insertar nuevo perfil
            $sql = "INSERT INTO perfiles 
                    (nombre_perfil, descripcion, nivel_jerarquico, estado, hereda_de, creado_por) 
                    VALUES (?, ?, ?, 'activo', ?, ?)";

            $stmt = $this->db->prepare($sql);
            $hereda_de = isset($datos['hereda_de']) ? $datos['hereda_de'] : NULL;
            $nombre_perfil = $datos['nombre_perfil'];
            $descripcion = $datos['descripcion'] ?? null;
            $nivel_jerarquico = $datos['nivel_jerarquico'];
            
            $stmt->bind_param(
                "ssiii",
                $nombre_perfil,
                $descripcion,
                $nivel_jerarquico,
                $hereda_de,
                $usuario_creador
            );

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al crear perfil: ' . $stmt->error];
            }

            $perfil_id = $stmt->insert_id;
            $stmt->close();

            // Si hereda de otro perfil, copiar permisos heredados
            if ($hereda_de) {
                $this->copiarPermisosHeredados($hereda_de, $perfil_id);
            }

            logAudit($usuario_creador, 'crear_perfil', 'perfiles', 'PER_GESTIONAR', "Nuevo perfil: {$datos['nombre_perfil']}", 'exitoso', null, 'perfiles', $perfil_id);

            return [
                'exito' => true,
                'mensaje' => 'Perfil creado exitosamente',
                'perfil_id' => $perfil_id
            ];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Editar perfil existente
     */
    public function editarPerfil($perfil_id, $datos, $usuario_editor) {
        try {
            if (!tienePermiso($usuario_editor, 'PER_GESTIONAR') && !tienePermiso($usuario_editor, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para editar perfiles'];
            }

            $perfil_actual = $this->obtenerPerfilCompleto($perfil_id);
            if (!$perfil_actual) {
                return ['exito' => false, 'mensaje' => 'Perfil no encontrado'];
            }

            // Preparar campos para actualizar
            $campos_actualizables = ['nombre_perfil', 'descripcion', 'estado'];
            $campos_actualizar = [];
            $tipos = '';
            $valores = [];

            foreach ($campos_actualizables as $campo) {
                if (isset($datos[$campo])) {
                    $campos_actualizar[] = "$campo = ?";
                    $tipos .= 's';
                    $valores[] = $datos[$campo];
                }
            }

            if (empty($campos_actualizar)) {
                return ['exito' => false, 'mensaje' => 'No hay campos para actualizar'];
            }

            $campos_actualizar[] = 'fecha_modificacion = NOW()';
            $valores[] = $usuario_editor;

            $sql = "UPDATE perfiles SET " . implode(', ', $campos_actualizar) . ", modificado_por = ? WHERE id = ?";
            $tipos .= 'ii';
            $valores[] = $perfil_id;

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($tipos, ...$valores);

            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al actualizar perfil'];
            }

            $stmt->close();

            $nombre_para_log = $datos['nombre_perfil'] ?? $perfil_actual['nombre_perfil'];
            logAudit($usuario_editor, 'editar_perfil', 'perfiles', 'PER_GESTIONAR', "Perfil actualizado: $nombre_para_log", 'exitoso');

            return ['exito' => true, 'mensaje' => 'Perfil actualizado exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Asignar permisos a un perfil
     */
    public function asignarPermisosAPerfil($perfil_id, $permisos, $usuario_asignador) {
        try {
            if (!tienePermiso($usuario_asignador, 'PER_ASIGNAR') && !tienePermiso($usuario_asignador, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para asignar permisos'];
            }

            $perfil = $this->obtenerPerfilCompleto($perfil_id);
            if (!$perfil) {
                return ['exito' => false, 'mensaje' => 'Perfil no encontrado'];
            }

            // Eliminar permisos existentes
            $sql_delete = "DELETE FROM permisos_perfil WHERE perfil_id = ?";
            $stmt = $this->db->prepare($sql_delete);
            $stmt->bind_param("i", $perfil_id);
            $stmt->execute();
            $stmt->close();

            // Insertar nuevos permisos
            $sql_insert = "INSERT INTO permisos_perfil 
                          (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, 
                           editar_datos, eliminar_datos, ver_reportes, exportar_datos, solo_propios, creado_por) 
                          VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql_insert);

            foreach ($permisos as $permiso) {
                $funcion_id = $permiso['funcion_id'];
                $modulo_id = $permiso['modulo_id'];
                $ver_datos = $permiso['ver_datos'] ?? 1;
                $crear_datos = $permiso['crear_datos'] ?? 0;
                $editar_datos = $permiso['editar_datos'] ?? 0;
                $eliminar_datos = $permiso['eliminar_datos'] ?? 0;
                $ver_reportes = $permiso['ver_reportes'] ?? 0;
                $exportar_datos = $permiso['exportar_datos'] ?? 0;
                $solo_propios = $permiso['solo_propios'] ?? 0;

                $stmt->bind_param(
                    "iiiiiiiiiii",
                    $perfil_id,
                    $funcion_id,
                    $modulo_id,
                    $ver_datos,
                    $crear_datos,
                    $editar_datos,
                    $eliminar_datos,
                    $ver_reportes,
                    $exportar_datos,
                    $solo_propios,
                    $usuario_asignador
                );

                if (!$stmt->execute()) {
                    $stmt->close();
                    return ['exito' => false, 'mensaje' => 'Error al asignar permisos: ' . $stmt->error];
                }
            }

            $stmt->close();

            logAudit($usuario_asignador, 'cambio_permiso', 'permisos_perfil', 'PER_ASIGNAR', "Permisos asignados al perfil: {$perfil['nombre_perfil']}", 'exitoso');

            return ['exito' => true, 'mensaje' => 'Permisos asignados exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener lista de perfiles
     */
    public function listarPerfiles() {
        $sql = "SELECT * FROM perfiles ORDER BY nivel_jerarquico ASC";
        $result = $this->db->query($sql);

        $perfiles = [];
        while ($row = $result->fetch_assoc()) {
            $perfiles[] = $row;
        }

        return $perfiles;
    }

    /**
     * Obtener perfil completo con permisos
     */
    public function obtenerPerfilCompleto($perfil_id) {
        $sql = "SELECT p.* FROM perfiles p WHERE p.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $perfil_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return null;
        }

        // Obtener permisos del perfil
        $sql_permisos = "SELECT pp.*, fm.nombre_funcion, m.nombre_modulo 
                        FROM permisos_perfil pp
                        INNER JOIN funciones_modulo fm ON pp.funcion_id = fm.id
                        INNER JOIN modulos m ON pp.modulo_id = m.id
                        WHERE pp.perfil_id = ?
                        ORDER BY m.nombre_modulo, fm.nombre_funcion";

        $stmt = $this->db->prepare($sql_permisos);
        $stmt->bind_param("i", $perfil_id);
        $stmt->execute();
        $result_permisos = $stmt->get_result();

        $result['permisos'] = [];
        while ($row = $result_permisos->fetch_assoc()) {
            $result['permisos'][] = $row;
        }
        $stmt->close();

        return $result;
    }

    /**
     * Copiar permisos heredados de otro perfil
     */
    private function copiarPermisosHeredados($perfil_origen, $perfil_destino) {
        $sql = "SELECT perfil_id, funcion_id, modulo_id, ver_datos, crear_datos, 
                       editar_datos, eliminar_datos, ver_reportes, exportar_datos, solo_propios
                FROM permisos_perfil 
                WHERE perfil_id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $perfil_origen);
        $stmt->execute();
        $result = $stmt->get_result();

        $sql_insert = "INSERT INTO permisos_perfil 
                      (perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos, crear_datos, 
                       editar_datos, eliminar_datos, ver_reportes, exportar_datos, solo_propios) 
                      VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_insert = $this->db->prepare($sql_insert);

        while ($row = $result->fetch_assoc()) {
            $stmt_insert->bind_param(
                "iiiiiiiiiii",
                $perfil_destino,
                $row['funcion_id'],
                $row['modulo_id'],
                $row['ver_datos'],
                $row['crear_datos'],
                $row['editar_datos'],
                $row['eliminar_datos'],
                $row['ver_reportes'],
                $row['exportar_datos'],
                $row['solo_propios'],
                $perfil_origen
            );

            $stmt_insert->execute();
        }

        $stmt->close();
        $stmt_insert->close();
    }

    /**
     * Validar acceso según la malla de permisos
     */
    public function validarAccesoSegunMalla($usuario_id, $modulo, $accion) {
        $usuario = $this->obtenerUsuarioPerfil($usuario_id);
        if (!$usuario) {
            return false;
        }

        $perfil = $usuario['nombre_perfil'];

        // Si es administrador, tiene acceso total
        if ($perfil === 'Administrador') {
            return true;
        }

        // Validar en la malla de permisos
        if (isset($this->malla_permisos_predefinida[$modulo][$perfil])) {
            $permiso_asignado = $this->malla_permisos_predefinida[$modulo][$perfil];

            // Validar según la acción solicitada
            if ($permiso_asignado === 'No' || $permiso_asignado === '❌') {
                return false;
            }

            if ($permiso_asignado === 'Total' || $permiso_asignado === 'Completo') {
                return true;
            }

            // Validaciones más granulares según la acción
            return $this->validarAccionSegun($permiso_asignado, $accion, $usuario_id);
        }

        return false;
    }

    /**
     * Validar acción específica según el permiso asignado
     */
    private function validarAccionSegun($permiso, $accion, $usuario_id) {
        // Mapeo de acciones a permisos
        $mapeo = [
            'Crear/Editar' => ['crear', 'editar'],
            'Consultar/Editar' => ['consultar', 'editar'],
            'Validar/Reportes' => ['validar', 'reportes'],
            'Parámetros técnicos' => ['editar_parametros_tech'],
            'Parámetros contables' => ['editar_parametros_cont'],
            'Técnicos' => ['reportes_tech'],
            'Financieros' => ['reportes_financieros'],
            'Comerciales' => ['reportes_comerciales'],
            'Caja' => ['reportes_caja'],
            'Todos' => ['reportes_tech', 'reportes_financieros', 'reportes_comerciales', 'reportes_caja'],
            'Limitados' => ['reportes_limitados'],
            'Registrar' => ['registrar'],
            'Crear propio' => ['crear_propio'],
            'Consultar propio' => ['consultar_propio'],
            'Seguimiento' => ['seguimiento'],
            'Parcial' => ['ver_datos_limitados'],
            'Consultar' => ['consultar']
        ];

        if (isset($mapeo[$permiso])) {
            return in_array($accion, $mapeo[$permiso]);
        }

        // Por defecto, si la acción es 'consultar', la mayoría la pueden hacer
        return $accion === 'consultar';
    }

    /**
     * Obtener usuario y su perfil
     */
    private function obtenerUsuarioPerfil($usuario_id) {
        $sql = "SELECT u.id, u.nombre, u.apellido, p.nombre_perfil, p.nivel_jerarquico
                FROM usuarios u
                LEFT JOIN perfiles p ON u.perfil_id = p.id
                WHERE u.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    }

    /**
     * Obtener malla completa de permisos predefinida
     */
    public function obtenerMallaPermisos() {
        return $this->malla_permisos_predefinida;
    }
    /**
     * Eliminar un perfil
     */
    public function eliminarPerfil($perfil_id, $usuario_borrador) {
        try {
            if (!tienePermiso($usuario_borrador, 'PER_GESTIONAR') && !tienePermiso($usuario_borrador, 'USU_TOTAL')) {
                return ['exito' => false, 'mensaje' => 'No tiene permisos para eliminar perfiles'];
            }

            // Verificar si hay usuarios asociados a este perfil
            $sql_check = "SELECT COUNT(*) as cuenta FROM usuarios WHERE perfil_id = ?";
            $stmt_check = $this->db->prepare($sql_check);
            $stmt_check->bind_param("i", $perfil_id);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($res_check['cuenta'] > 0) {
                return ['exito' => false, 'mensaje' => "No se puede eliminar el perfil porque tiene {$res_check['cuenta']} usuarios asociados"];
            }

            // Eliminar permisos asociados
            $sql_p = "DELETE FROM permisos_perfil WHERE perfil_id = ?";
            $stmt_p = $this->db->prepare($sql_p);
            $stmt_p->bind_param("i", $perfil_id);
            $stmt_p->execute();
            $stmt_p->close();

            // Eliminar perfil
            $sql = "DELETE FROM perfiles WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $perfil_id);
            
            if (!$stmt->execute()) {
                return ['exito' => false, 'mensaje' => 'Error al eliminar perfil'];
            }
            $stmt->close();

            logAudit($usuario_borrador, 'eliminar_perfil', 'perfiles', 'PER_GESTIONAR', "Perfil ID $perfil_id eliminado", 'exitoso');

            return ['exito' => true, 'mensaje' => 'Perfil eliminado exitosamente'];

        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }
}

?>
