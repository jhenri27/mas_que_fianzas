-- ============================================================================
-- MIGRACIÓN NOFTRAB v4.0: Creación e Integración del Perfil Perito-Mensajero
-- Absorbe la App Jotform "MENSAJERÍA +QF" (261593518544868)
-- ============================================================================

INSERT INTO perfiles (
    nombre_perfil, 
    siglas, 
    descripcion, 
    nivel_jerarquico, 
    estado, 
    es_predeterminado, 
    creado_por
) VALUES (
    'Perito-Mensajero', 
    'MEN', 
    'Perito-Mensajero: inspecciones de riesgo en campo, capturas de firma digital y entregas de pólizas en ruta (Absorbe App MENSAJERÍA +QF 261593518544868)', 
    6, 
    'activo', 
    0, 
    1
) ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion), 
    estado = 'activo';

-- Asignar Permisos del Sistema para el Perfil Perito-Mensajero (ID derivado)
INSERT INTO permisos_perfil (perfil_id, modulo_id, funcion_id, puede_ver, puede_crear, puede_editar, puede_eliminar)
SELECT 
    p.id AS perfil_id,
    m.id AS modulo_id,
    f.id AS funcion_id,
    1 AS puede_ver,
    1 AS puede_crear,
    1 AS puede_editar,
    0 AS puede_eliminar
FROM perfiles p
CROSS JOIN modulos m
LEFT JOIN funciones f ON f.modulo_id = m.id
WHERE p.nombre_perfil = 'Perito-Mensajero'
  AND m.nombre_modulo IN ('dashboard', 'clientes', 'polizas', 'siniestros', 'perfil_data', 'notificaciones', 'helpdesk')
ON DUPLICATE KEY UPDATE 
    puede_ver = 1, 
    puede_crear = 1, 
    puede_editar = 1;
