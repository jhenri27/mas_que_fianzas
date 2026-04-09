# 🏛️ MAS QUE FIANZAS - Sistema Integrado de Gestión

Sistema web completo de gestión de fianzas, seguros y cotizaciones con:
- 👥 Gestión integral de usuarios y perfiles
- 🔐 Control de accesos basado en roles (RBAC)
- 📋 Módulo de cotizaciones integrado
- 📊 Sistema de auditoría y reportes
- 💰 Gestión de pagos, pólizas y siniestros

## 📋 Requisitos Previos

- **WAMP Server** instalado (Apache, MySQL, PHP)
- **MySQL 5.7+** o **MariaDB 10.3+**
- **PHP 7.4+**
- **Navegador moderno** (Chrome, Firefox, Edge, Safari)

## 🚀 Instalación Rápida

### Paso 1: Ubicar los archivos en WAMP

```bash
# Copiar la carpeta PLATAFORMA_INTEGRADA a:
C:\wamp64\www\PLATAFORMA_INTEGRADA
```

### Paso 2: Crear la Base de Datos

1. **Abrir phpMyAdmin:**
   - Ir a: `http://localhost/phpmyadmin`
   - Usuario: `root`
   - Contraseña: (vacío por defecto)

2. **Ejecutar el script SQL:**
   - Click en "Importar"
   - Seleccionar archivo: `PLATAFORMA_INTEGRADA/database/schema_masque_fianzas.sql`
   - Click en "Ejecutar"

### Paso 3: Configurar la Conexión

El archivo `backend/config.php` está pre-configurado para WAMP:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', ''); // Vacío por defecto en WAMP
define('DB_NAME', 'masque_fianzas_integrada');
```

Si tienes contraseña en MySQL, modificar `DB_PASSWORD` en `config.php`

### Paso 4: Acceder al Sistema

```
URL: http://localhost/PLATAFORMA_INTEGRADA/frontend/
```

## 🔑 Credenciales de Demo

**Usuario:** `admin`
**Contraseña:** `Demo@123`

## 📁 Estructura del Proyecto

```
PLATAFORMA_INTEGRADA/
├── backend/
│   ├── api/
│   │   ├── auth.php          # API de autenticación
│   │   ├── usuarios.php      # API de gestión de usuarios
│   │   └── perfiles.php      # API de gestión de perfiles
│   ├── config.php            # Configuración de BD
│   ├── Autenticacion.php     # Clase de autenticación
│   ├── UsuarioManager.php    # Gestión de usuarios
│   └── PerfilManager.php     # Gestión de perfiles
├── frontend/
│   ├── index.html            # Página de login
│   ├── dashboard.html        # Dashboard principal
│   ├── assets/
│   │   ├── api-client.js     # Cliente de API
│   │   ├── login.js          # Lógica de login
│   │   ├── login.css         # Estilos de login
│   │   ├── dashboard.js      # Lógica del dashboard
│   │   ├── dashboard.css     # Estilos del dashboard
│   │   └── modulos.css       # Estilos de módulos
│   └── modulos/              # Módulos adicionales
├── database/
│   └── schema_masque_fianzas.sql  # Script de BD
└── README.md                 # Este archivo
```

## 🛠️ Características Principales

### 1. Autenticación y Seguridad

- ✅ Login con usuario y contraseña
- ✅ Sesiones seguras en BD
- ✅ Bloqueo temporal tras intentos fallidos
- ✅ Cambio obligatorio de contraseña
- ✅ Auditoría de todos los accesos

### 2. Gestión de Usuarios

- ✅ Crear nuevos usuarios
- ✅ Editar información de usuarios
- ✅ Bloquear/desbloquear usuarios
- ✅ Restablecer contraseñas
- ✅ Asignar perfiles y roles

### 3. Gestión de Perfiles y Permisos

- ✅ Crear perfiles personalizados
- ✅ Editar perfiles
- ✅ Asignar permisos granulares
- ✅ Herencia de permisos
- ✅ Malla de roles predefinida (8 roles)

### 4. Módulos de Negocio

- ✅ Cotizador de seguros integrado
- ✅ Gestión de clientes
- ✅ Gestión de pólizas
- ✅ Gestión de fianzas
- ✅ Registro de pagos
- ✅ Gestión de siniestros

### 5. Reportes y Auditoría

- ✅ Auditoría de accesos
- ✅ Historial de cambios de contraseña
- ✅ Reportes de actividad por usuario
- ✅ Consulta de acciones realizadas

## 👥 Roles Predefinidos

| Rol | Descripción | Acceso |
|-----|-------------|---------|
| **Administrador** | Acceso total al sistema | Completo |
| **Gerente Técnico** | Gestión técnica de operaciones | Parcial |
| **Gerente Contador** | Gestión contable y financiera | Parcial |
| **Gerente Comercial** | Gestión comercial y clientes | Parcial |
| **Socio Comercial** | Acceso comercial limitado | Limitado |
| **Cajero** | Registro de pagos | Muy limitado |
| **Auditor** | Acceso a reportes de auditoría | Consulta |
| **Usuario** | Usuario estándar | Básico |

## 🔑 Malla de Permisos

La plataforma implementa una malla de control de accesos según la tabla de requisitos:

- **Dashboard**: Acceso parcial o completo según rol
- **Clientes**: Crear/Editar, Consultar o Bloqueado
- **Pólizas**: Total, Crear/Editar, Consultar o Bloqueado
- **Fianzas**: Total, Crear/Editar, Consultar o Bloqueado
- **Pagos**: Total, Validar/Reportes, Registrar o Bloqueado
- **Cotizaciones**: Crear/Editar o propio, o Bloqueado
- **Productos**: Total o Consultar
- **Configuración**: Total, Parámetros Técnicos/Contables o Consultar
- **Reportes**: Según tipo de reporte
- **Siniestros**: Total o Seguimiento, Consultar o propio

## 🔧 API Endpoints

### Autenticación

```
POST   /backend/api/auth/login           - Iniciar sesión
POST   /backend/api/auth/logout          - Cerrar sesión
POST   /backend/api/auth/cambiar-password - Cambiar contraseña
GET    /backend/api/auth/validar-sesion  - Validar sesión activa
```

### Usuarios

```
POST   /backend/api/usuarios/crear           - Crear usuario
PUT    /backend/api/usuarios/editar/{id}    - Editar usuario
POST   /backend/api/usuarios/bloquear/{id}  - Bloquear usuario
POST   /backend/api/usuarios/desbloquear/{id} - Desbloquear usuario
POST   /backend/api/usuarios/restablecer-password/{id} - Restablecer contraseña
GET    /backend/api/usuarios/obtener/{id}   - Obtener usuario único
GET    /backend/api/usuarios/listar         - Listar usuarios con filtros
DELETE /backend/api/usuarios/eliminar/{id}  - Eliminar usuario (soft delete)
```

### Perfiles

```
POST   /backend/api/perfiles/crear                   - Crear perfil
PUT    /backend/api/perfiles/editar/{id}            - Editar perfil
POST   /backend/api/perfiles/asignar-permisos/{id}  - Asignar permisos
GET    /backend/api/perfiles/obtener/{id}           - Obtener perfil
GET    /backend/api/perfiles/listar                 - Listar perfiles
GET    /backend/api/perfiles/malla-permisos         - Obtener malla
GET    /backend/api/perfiles/validar-acceso         - Validar acceso a módulo
```

## 📝 Ejemplos de Uso

### Crear Usuario

```javascript
const resultado = await api.crearUsuario({
    cedula: '001-1234567-8',
    nombre: 'Juan',
    apellido: 'Pérez',
    email: 'juan@example.com',
    username: 'jperez',
    perfil_id: 4 // Gerente Comercial
});
```

### Bloquear Usuario

```javascript
const resultado = await api.bloquearUsuario(5, 'Usuario suspenso por incumplimiento');
```

### Validar Acceso

```javascript
const tiene_acceso = await api.validarAcceso('cotizaciones', 'crear');
```

## 🐛 Solución de Problemas

### Error: "Conexión rechazada"
- Verificar que WAMP esté ejecutándose
- Verificar puerto MySQL (3306)
- Revisar credenciales en `config.php`

### Error: "Base de datos no encontrada"
- Ejecutar el script SQL manualmente en phpMyAdmin
- Cambiar nombre de BD en `config.php` si es diferente

### Error: "Permiso denegado"
- Verificar que el usuario tenga permisos suficientes
- Revisar la malla de permisos del rol

### Sesión expirada
- Las sesiones expiran después de 30 minutos de inactividad
- Configurar `SESSION_TIMEOUT_MINUTES` en `config.php`

## 📚 Documentación Técnica

### Base de Datos

**Tablas principales:**
- `usuarios` - Almacena información de usuarios
- `perfiles` - Define roles y perfiles del sistema
- `permisos_perfil` - Asigna permisos a perfiles
- `auditoria_accesos` - Registra todas las acciones
- `sesiones_usuario` - Gestiona sesiones activas

**Características:**
- Relaciones normalizadas
- Índices para optimización
- Soft delete en usuarios
- Auditoría automática de cambios

### Seguridad

- Contraseñas con hash bcrypt (costo 10)
- SQL prepared statements (previene SQL injection)
- Validación de permisos en cada acción
- Tokens de sesión únicos
- CORS configurado
- Rate limiting (5 intentos fallidos máximo)

## 🎓 Ejemplo de Integración del Cotizador

El módulo de cotizador está completamente integrado:

```html
<section id="modulo-cotizaciones" class="module">
    <div class="module-header">
        <h2>Cotizador de Seguros</h2>
    </div>
    <!-- Aquí va el cotizador -->
</section>
```

## 🌍 Cumplimiento Normativo

El sistema cumple con:
- ✅ Requisitos de seguros en República Dominicana
- ✅ Segregación de funciones (SoD)
- ✅ Trazabilidad completa de operaciones
- ✅ Políticas de contraseña robustas
- ✅ Auditoría integral

## 📞 Soporte y Mantenimiento

Para soporte o mantenimiento:
1. Revisar logs en `backend/logs/`
2. Consultar tabla de auditoría
3. Verificar estado de sesiones activas

## 📄 Licencia

Sistema propietario de MAS QUE FIANZAS
Todos los derechos reservados.

## 👨‍💻 Desarrollado por

GitHub Copilot - Sistema Integrado
Versión 1.0.0 (Febrero 2026)

---

**Última actualización:** 22 de Febrero de 2026
