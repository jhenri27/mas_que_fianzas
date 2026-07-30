import sys
sys.path.append(r"C:\Users\jhenr\AppData\Roaming\Python\Python314\site-packages")
sys.path.append(r"C:\Users\jhenr\AppData\Local\Programs\Python\Python314\Lib\site-packages")
import json
import pymysql
import pymysql.cursors

import os

# Configuración de base de datos dinámica para Windows (WAMP) y VPS Linux
DB_USER = os.getenv('DB_USER', 'masque_user')
DB_PASS = os.getenv('DB_PASS', 'MasQF_2026_Secure!')
cwd = os.getcwd().replace('\\', '/')
DB_NAME = os.getenv('DB_NAME', 'masque_fianzas_dev' if 'dev_plataforma' in cwd else 'masque_fianzas_integrada_01')

# Si estamos en Windows con WAMP sin masque_user, fallback a root
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': DB_USER,
    'password': DB_PASS,
    'database': DB_NAME,
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

def get_connection():
    try:
        return pymysql.connect(**DB_CONFIG)
    except Exception as e:
        # Fallback a root sin password para WAMP local si masque_user no existe localmente
        alt_config = DB_CONFIG.copy()
        alt_config['user'] = 'root'
        alt_config['password'] = ''
        return pymysql.connect(**alt_config)

def load_json_param(param):
    import os
    if param.endswith('.json') and os.path.exists(param):
        try:
            with open(param, 'r', encoding='utf-8') as f:
                return f.read()
        except Exception:
            pass
    return param


def list_modules_functions():
    connection = get_connection()
    try:
        with connection.cursor() as cursor:
            # Obtener todos los módulos
            cursor.execute("SELECT id, nombre_modulo, nombre_modulo AS codigo_modulo, descripcion, estado FROM modulos ORDER BY id")
            modulos = cursor.fetchall()
            
            # Obtener todas las funciones
            cursor.execute("SELECT id, modulo_id, nombre_funcion, codigo_funcion, descripcion, tipo_permiso, estado FROM funciones_modulo ORDER BY modulo_id, id")
            funciones = cursor.fetchall()
            
            # Agrupar funciones por módulo
            modulos_dict = {m['id']: {**m, 'funciones': []} for m in modulos}
            for f in funciones:
                m_id = f['modulo_id']
                if m_id in modulos_dict:
                    modulos_dict[m_id]['funciones'].append(f)
                    
            return {
                'exito': True,
                'mensaje': 'Módulos y funciones obtenidos',
                'datos': list(modulos_dict.values())
            }
    except Exception as e:
        return {'exito': False, 'mensaje': f'Error en list_modules_functions: {str(e)}'}
    finally:
        connection.close()

def get_profile_permissions(perfil_id):
    connection = get_connection()
    try:
        perfil_id = int(perfil_id)
        with connection.cursor() as cursor:
            # Obtener información del perfil
            cursor.execute("SELECT * FROM perfiles WHERE id = %s", (perfil_id,))
            perfil = cursor.fetchone()
            if not perfil:
                return {'exito': False, 'mensaje': 'Perfil no encontrado'}
                
            # Obtener los permisos del perfil
            cursor.execute("SELECT * FROM permisos_perfil WHERE perfil_id = %s", (perfil_id,))
            permisos = cursor.fetchall()
            
            return {
                'exito': True,
                'mensaje': 'Permisos del perfil obtenidos',
                'perfil': perfil,
                'datos': permisos
            }
    except Exception as e:
        return {'exito': False, 'mensaje': f'Error en get_profile_permissions: {str(e)}'}
    finally:
        connection.close()

def save_profile_permissions(perfil_id, permisos_json, usuario_id):
    connection = get_connection()
    try:
        perfil_id = int(perfil_id)
        usuario_id = int(usuario_id)
        permisos = json.loads(load_json_param(permisos_json))
        
        with connection.cursor() as cursor:
            # Obtener el perfil
            cursor.execute("SELECT nombre_perfil FROM perfiles WHERE id = %s", (perfil_id,))
            perfil = cursor.fetchone()
            if not perfil:
                return {'exito': False, 'mensaje': 'Perfil no encontrado'}
            
            # Obtener permisos antiguos para la auditoría (NOFTRAB)
            cursor.execute("SELECT * FROM permisos_perfil WHERE perfil_id = %s", (perfil_id,))
            permisos_antiguos = cursor.fetchall()
            
            # Iniciar transacción
            connection.begin()
            
            # Eliminar permisos antiguos
            cursor.execute("DELETE FROM permisos_perfil WHERE perfil_id = %s", (perfil_id,))
            
            # Insertar nuevos permisos granulares
            sql_insert = """
                INSERT INTO permisos_perfil (
                    perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos,
                    crear_datos, editar_datos, eliminar_datos, ver_reportes,
                    exportar_datos, importar_datos, imprimir_datos, solo_propios, creado_por
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            for p in permisos:
                cursor.execute(sql_insert, (
                    perfil_id,
                    int(p['funcion_id']),
                    int(p['modulo_id']),
                    1 if p.get('puede_ejecutar', True) else 0,
                    1 if p.get('ver_datos', False) else 0,
                    1 if p.get('crear_datos', False) else 0,
                    1 if p.get('editar_datos', False) else 0,
                    1 if p.get('eliminar_datos', False) else 0,
                    1 if p.get('ver_reportes', False) else 0,
                    1 if p.get('exportar_datos', False) else 0,
                    1 if p.get('importar_datos', False) else 0,
                    1 if p.get('imprimir_datos', False) else 0,
                    1 if p.get('solo_propios', False) else 0,
                    usuario_id
                ))
            
            # Escribir auditoría detallada de accesos (NOFTRAB)
            sql_audit = """
                INSERT INTO auditoria_accesos (
                    usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada,
                    descripcion_evento, direccion_ip, navegador_user_agent,
                    resultado, tabla_afectada, registro_afectado_id,
                    operacion_realizada, valor_anterior, valor_nuevo
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            descripcion = f"Actualización granular de permisos para el perfil: {perfil['nombre_perfil']}"
            cursor.execute(sql_audit, (
                usuario_id,
                'cambio_permiso',
                'configuracion',
                'PER_ASIGNAR',
                descripcion,
                '127.0.0.1',
                'Python Engine API',
                'exitoso',
                'permisos_perfil',
                perfil_id,
                'update',
                json.dumps(permisos_antiguos, default=str),
                json.dumps(permisos, default=str)
            ))
            
            # Confirmar transacción
            connection.commit()
            
            return {
                'exito': True,
                'mensaje': 'Permisos guardados y auditados exitosamente'
            }
    except Exception as e:
        connection.rollback()
        return {'exito': False, 'mensaje': f'Error en save_profile_permissions: {str(e)}'}
    finally:
        connection.close()

def create_profile(profile_json, usuario_creador):
    connection = get_connection()
    try:
        usuario_creador = int(usuario_creador)
        datos = json.loads(load_json_param(profile_json))
        
        nombre_perfil = datos.get('nombre_perfil')
        descripcion = datos.get('descripcion', '')
        nivel_jerarquico = int(datos.get('nivel_jerarquico', 5))
        hereda_de = datos.get('hereda_de')
        
        if not nombre_perfil:
            return {'exito': False, 'mensaje': 'El nombre del perfil es requerido'}
            
        with connection.cursor() as cursor:
            # Validar nombre único
            cursor.execute("SELECT id FROM perfiles WHERE nombre_perfil = %s", (nombre_perfil,))
            if cursor.fetchone():
                return {'exito': False, 'mensaje': 'El nombre del perfil ya existe'}
                
            connection.begin()
            
            # Insertar perfil
            sql_insert = """
                INSERT INTO perfiles (
                    nombre_perfil, descripcion, nivel_jerarquico, estado,
                    es_predeterminado, hereda_de, creado_por
                ) VALUES (%s, %s, %s, 'activo', 0, %s, %s)
            """
            cursor.execute(sql_insert, (nombre_perfil, descripcion, nivel_jerarquico, hereda_de, usuario_creador))
            perfil_id = cursor.lastrowid
            
            # Si hereda de otro perfil, copiar sus permisos
            if hereda_de:
                cursor.execute("""
                    INSERT INTO permisos_perfil (
                        perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos,
                        crear_datos, editar_datos, eliminar_datos, ver_reportes,
                        exportar_datos, importar_datos, imprimir_datos, solo_propios, creado_por
                    )
                    SELECT %s, funcion_id, modulo_id, puede_ejecutar, ver_datos,
                           crear_datos, editar_datos, eliminar_datos, ver_reportes,
                           exportar_datos, importar_datos, imprimir_datos, solo_propios, %s
                    FROM permisos_perfil WHERE perfil_id = %s
                """, (perfil_id, usuario_creador, int(hereda_de)))
            
            # Escribir auditoría
            sql_audit = """
                INSERT INTO auditoria_accesos (
                    usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada,
                    descripcion_evento, direccion_ip, navegador_user_agent,
                    resultado, tabla_afectada, registro_afectado_id,
                    operacion_realizada
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql_audit, (
                usuario_creador,
                'crear_perfil',
                'configuracion',
                'PER_GESTIONAR',
                f"Creado perfil {nombre_perfil} con ID {perfil_id}",
                '127.0.0.1',
                'Python Engine API',
                'exitoso',
                'perfiles',
                perfil_id,
                'insert'
            ))
            
            connection.commit()
            return {
                'exito': True,
                'mensaje': 'Perfil creado exitosamente',
                'perfil_id': perfil_id
            }
    except Exception as e:
        connection.rollback()
        return {'exito': False, 'mensaje': f'Error en create_profile: {str(e)}'}
    finally:
        connection.close()

def update_profile(perfil_id, profile_json, usuario_modificador):
    connection = get_connection()
    try:
        perfil_id = int(perfil_id)
        usuario_modificador = int(usuario_modificador)
        datos = json.loads(load_json_param(profile_json))
        
        nombre_perfil = datos.get('nombre_perfil')
        descripcion = datos.get('descripcion', '')
        nivel_jerarquico = int(datos.get('nivel_jerarquico', 5))
        estado = datos.get('estado', 'activo')
        
        with connection.cursor() as cursor:
            # Obtener estado anterior
            cursor.execute("SELECT * FROM perfiles WHERE id = %s", (perfil_id,))
            perfil_antiguo = cursor.fetchone()
            if not perfil_antiguo:
                return {'exito': False, 'mensaje': 'Perfil no encontrado'}
                
            connection.begin()
            
            # Actualizar perfil
            sql_update = """
                UPDATE perfiles SET
                    nombre_perfil = %s,
                    descripcion = %s,
                    nivel_jerarquico = %s,
                    estado = %s,
                    modificado_por = %s,
                    fecha_modificacion = NOW()
                WHERE id = %s
            """
            cursor.execute(sql_update, (nombre_perfil, descripcion, nivel_jerarquico, estado, usuario_modificador, perfil_id))
            
            # Registrar auditoría
            sql_audit = """
                INSERT INTO auditoria_accesos (
                    usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada,
                    descripcion_evento, direccion_ip, navegador_user_agent,
                    resultado, tabla_afectada, registro_afectado_id,
                    operacion_realizada, valor_anterior, valor_nuevo
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql_audit, (
                usuario_modificador,
                'editar_perfil',
                'configuracion',
                'PER_GESTIONAR',
                f"Modificado perfil {nombre_perfil} con ID {perfil_id}",
                '127.0.0.1',
                'Python Engine API',
                'exitoso',
                'perfiles',
                perfil_id,
                'update',
                json.dumps(perfil_antiguo, default=str),
                json.dumps(datos, default=str)
            ))
            
            connection.commit()
            return {
                'exito': True,
                'mensaje': 'Perfil actualizado exitosamente'
            }
    except Exception as e:
        connection.rollback()
        return {'exito': False, 'mensaje': f'Error en update_profile: {str(e)}'}
    finally:
        connection.close()

def delete_profile(perfil_id, usuario_borrador):
    connection = get_connection()
    try:
        perfil_id = int(perfil_id)
        usuario_borrador = int(usuario_borrador)
        
        with connection.cursor() as cursor:
            # Obtener datos del perfil antes de borrar
            cursor.execute("SELECT * FROM perfiles WHERE id = %s", (perfil_id,))
            perfil = cursor.fetchone()
            if not perfil:
                return {'exito': False, 'mensaje': 'Perfil no encontrado'}
                
            # No permitir borrar el Administrador (ID 1)
            if perfil_id == 1:
                return {'exito': False, 'mensaje': 'No se puede eliminar el perfil Administrador'}
                
            connection.begin()
            
            # Eliminar permisos asociados
            cursor.execute("DELETE FROM permisos_perfil WHERE perfil_id = %s", (perfil_id,))
            
            # Eliminar perfil
            cursor.execute("DELETE FROM perfiles WHERE id = %s", (perfil_id,))
            
            # Registrar auditoría
            sql_audit = """
                INSERT INTO auditoria_accesos (
                    usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada,
                    descripcion_evento, direccion_ip, navegador_user_agent,
                    resultado, tabla_afectada, registro_afectado_id,
                    operacion_realizada, valor_anterior
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql_audit, (
                usuario_borrador,
                'eliminar_perfil',
                'configuracion',
                'PER_GESTIONAR',
                f"Eliminado perfil {perfil['nombre_perfil']} con ID {perfil_id}",
                '127.0.0.1',
                'Python Engine API',
                'exitoso',
                'perfiles',
                perfil_id,
                'delete',
                json.dumps(perfil, default=str)
            ))
            
            connection.commit()
            return {
                'exito': True,
                'mensaje': 'Perfil eliminado exitosamente'
            }
    except Exception as e:
        connection.rollback()
        return {'exito': False, 'mensaje': f'Error en delete_profile: {str(e)}'}
    finally:
        connection.close()

def copy_profile_permissions(origen_id, destino_id, usuario_id):
    connection = get_connection()
    try:
        origen_id = int(origen_id)
        destino_id = int(destino_id)
        usuario_id = int(usuario_id)
        
        with connection.cursor() as cursor:
            # Validar perfiles
            cursor.execute("SELECT nombre_perfil FROM perfiles WHERE id = %s", (origen_id,))
            perfil_origen = cursor.fetchone()
            cursor.execute("SELECT nombre_perfil FROM perfiles WHERE id = %s", (destino_id,))
            perfil_destino = cursor.fetchone()
            
            if not perfil_origen or not perfil_destino:
                return {'exito': False, 'mensaje': 'Perfil de origen o destino no encontrado'}
                
            if destino_id == 1:
                return {'exito': False, 'mensaje': 'No se pueden sobrescribir los permisos del Administrador principal'}
                
            # Obtener permisos antiguos de destino para auditoría
            cursor.execute("SELECT * FROM permisos_perfil WHERE perfil_id = %s", (destino_id,))
            permisos_antiguos = cursor.fetchall()
            
            connection.begin()
            
            # Eliminar permisos antiguos del destino
            cursor.execute("DELETE FROM permisos_perfil WHERE perfil_id = %s", (destino_id,))
            
            # Copiar permisos del origen al destino
            cursor.execute("""
                INSERT INTO permisos_perfil (
                    perfil_id, funcion_id, modulo_id, puede_ejecutar, ver_datos,
                    crear_datos, editar_datos, eliminar_datos, ver_reportes,
                    exportar_datos, importar_datos, imprimir_datos, solo_propios, creado_por
                )
                SELECT %s, funcion_id, modulo_id, puede_ejecutar, ver_datos,
                       crear_datos, editar_datos, eliminar_datos, ver_reportes,
                       exportar_datos, importar_datos, imprimir_datos, solo_propios, %s
                FROM permisos_perfil WHERE perfil_id = %s
            """, (destino_id, usuario_id, origen_id))
            
            # Registrar auditoría
            sql_audit = """
                INSERT INTO auditoria_accesos (
                    usuario_id, tipo_evento, modulo_accedido, funcion_ejecutada,
                    descripcion_evento, direccion_ip, navegador_user_agent,
                    resultado, tabla_afectada, registro_afectado_id,
                    operacion_realizada, valor_anterior
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql_audit, (
                usuario_id,
                'copia_permisos',
                'configuracion',
                'PER_GESTIONAR',
                f"Copiados permisos desde el perfil {perfil_origen['nombre_perfil']} al perfil {perfil_destino['nombre_perfil']}",
                '127.0.0.1',
                'Python Engine API',
                'exitoso',
                'permisos_perfil',
                destino_id,
                'copy',
                json.dumps(permisos_antiguos, default=str)
            ))
            
            connection.commit()
            return {
                'exito': True,
                'mensaje': f"Permisos copiados exitosamente de {perfil_origen['nombre_perfil']} a {perfil_destino['nombre_perfil']}."
            }
    except Exception as e:
        connection.rollback()
        return {'exito': False, 'mensaje': f'Error en copy_profile_permissions: {str(e)}'}
    finally:
        connection.close()

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'exito': False, 'mensaje': 'Falta el comando principal'}))
        sys.exit(1)
        
    cmd = sys.argv[1]
    
    if cmd == 'list_modules_functions':
        res = list_modules_functions()
    elif cmd == 'get_profile_permissions':
        if len(sys.argv) < 3:
            res = {'exito': False, 'mensaje': 'Falta ID del perfil'}
        else:
            res = get_profile_permissions(sys.argv[2])
    elif cmd == 'save_profile_permissions':
        if len(sys.argv) < 5:
            res = {'exito': False, 'mensaje': 'Faltan parámetros para save_profile_permissions'}
        else:
            res = save_profile_permissions(sys.argv[2], sys.argv[3], sys.argv[4])
    elif cmd == 'create_profile':
        if len(sys.argv) < 4:
            res = {'exito': False, 'mensaje': 'Faltan parámetros para create_profile'}
        else:
            res = create_profile(sys.argv[2], sys.argv[3])
    elif cmd == 'update_profile':
        if len(sys.argv) < 5:
            res = {'exito': False, 'mensaje': 'Faltan parámetros para update_profile'}
        else:
            res = update_profile(sys.argv[2], sys.argv[3], sys.argv[4])
    elif cmd == 'delete_profile':
        if len(sys.argv) < 4:
            res = {'exito': False, 'mensaje': 'Faltan parámetros para delete_profile'}
        else:
            res = delete_profile(sys.argv[2], sys.argv[3])
    elif cmd == 'copy_profile_permissions':
        if len(sys.argv) < 5:
            res = {'exito': False, 'mensaje': 'Faltan parámetros para copy_profile_permissions'}
        else:
            res = copy_profile_permissions(sys.argv[2], sys.argv[3], sys.argv[4])
    else:
        res = {'exito': False, 'mensaje': f'Comando no reconocido: {cmd}'}
        
    print(json.dumps(res, indent=2, default=str))
