# 🏛️ MÁS QUE FIANZAS - Sistema Integrado de Gestión (v4.0.0 Stable / NOFTRAB v4.0)

Sistema web completo de gestión de fianzas, seguros y cotizaciones con:
- 👥 Gestión de usuarios y perfiles comerciales con nomenclatura jerárquica (RED, DIR, PDV, VEN).
- 🐍 Motor ETL de importación masiva en **Python 3.14.5** (Idempotente, con normalización Banreservas).
- 🔐 Control de accesos granular basado en roles (RBAC) y **Doble Capa de Seguridad (Layered Security)**.
- 📋 Módulo de cotizaciones de seguros de ley y fianzas con PDF corporativos de alta fidelidad.
- 📊 Sistema contable de pagos, pólizas y siniestros con conciliación bancaria.
- 📈 Widget y Modal Analítico de **Pólizas Emitidas** con filtros temporales interactivos.
- ⚖️ **Estándar de Auditoría Inmutable NOFTRAB v4.0** con registro forense y justificación obligatoria.

---

## 📋 Requisitos Previos

- **WAMP Server** instalado con **Apache**, **MySQL 5.7+ / MariaDB 10.3+**
- **PHP 8.2+** (Probado y plenamente compatible con PHP 8.2.29)
- **Python 3.14+** (Instalado en el PATH del sistema para soporte del motor de perfiles y ETL)
- **Navegador moderno** (Chrome, Firefox, Edge, Safari)

---

## 🚀 Instalación Rápida

### Paso 1: Ubicar los archivos en WAMP

Copiar la carpeta `PLATAFORMA_INTEGRADA` a la raíz de publicación de WAMP:
```bash
C:\wamp64\www\PLATAFORMA_INTEGRADA
```

### Paso 2: Configurar la Base de Datos

1. **Abrir phpMyAdmin:** Ir a `http://localhost/phpmyadmin` con usuario `root` y contraseña vacía.
2. **Importar el Esquema e Iniciales:**
   - Crear una base de datos llamada `masque_fianzas_integrada_01`.
   - Seleccionar e importar el archivo: `database/schema_masque_fianzas.sql`.
   - Importar las migraciones correspondientes, especialmente el esquema de ajustes de auditoría.
3. El archivo `backend/config.php` está configurado para WAMP de forma predeterminada:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASSWORD', '');
   define('DB_NAME', 'masque_fianzas_integrada_01');
   define('DB_PORT', 3306);
   ```

### Paso 3: Acceder al Sistema

```
URL: http://localhost/PLATAFORMA_INTEGRADA/frontend/
```

### 🔑 Credenciales de Demo
- **Usuario:** `admin`
- **Contraseña:** `Demo@123`

---

## 📁 Estructura del Proyecto

```
PLATAFORMA_INTEGRADA/
├── backend/
│   ├── api/
│   │   ├── auth.php              # API de autenticación y control de tokens
│   │   ├── usuarios.php          # API de gestión de usuarios (con importación XLSX)
│   │   ├── perfiles.php          # API de gestión de perfiles (RBAC)
│   │   ├── perfiles_engine.php   # Wrapper de API que invoca al motor de Python
│   │   ├── polizas.php           # API de gestión y emisión de pólizas
│   │   ├── polizas_stats.php     # API de estadísticas dinámicas de pólizas
│   │   ├── pagos.php             # API de cobros e ingresos (Finanzas)
│   │   ├── comisiones.php        # API de comisiones y árbol comercial
│   │   └── ajustes.php           # API de auditoría de ajustes inmutables NOFTRAB
│   ├── config/
│   │   └── smtp.json             # Configuración dinámica del servidor de correo
│   ├── logs/
│   │   ├── error.log             # Logs técnicos de PHP
│   │   ├── smtp.log              # Logs del servidor de correo SMTP
│   │   └── audit.log             # Logs de auditoría en texto plano
│   ├── config.php                # Configuración de base de datos y helpers globales
│   ├── Autenticacion.php         # Clase controladora de sesiones y autenticación
│   ├── UsuarioManager.php        # Gestión de lógica comercial e importación
│   ├── PerfilManager.php         # Matriz de permisos y herencias
│   ├── PagoManager.php           # Motor contable de control de pagos
│   ├── Mailer.php                # Gestor de envíos SMTP
│   └── perfiles_engine.py        # Motor CLI en Python para transacciones de permisos en BD
├── frontend/
│   ├── index.html                # Interfaz de login y acceso
│   ├── dashboard.html            # Dashboard Shell interactivo (Glassmorphism)
│   ├── recuperar.html            # Interfaz de recuperación de contraseña por email
│   ├── cambiar-password.html     # Interfaz de cambio de contraseña obligatoria
│   ├── assets/
│   │   ├── api-client.js         # Cliente unificado de llamadas HTTP (Bearer Token)
│   │   ├── dashboard.js          # Lógica interactiva del Dashboard y modales
│   │   ├── dashboard.css         # Estilizado visual premium (Skins dark/light)
│   │   ├── data-export.js        # Motor PDF/Excel/CSV/ZIP unificado
│   │   ├── skin-engine.css       # Reglas estéticas de skins interactivos
│   │   └── logo_b64.js           # Logo MQF codificado para incrustación directa en PDFs
│   └── modulos/
│       ├── usuarios.html         # CRUD y formulario dinámico (Comisiones/Bancos)
│       ├── cotizaciones.html     # Cotizador integrado de seguros y fianzas
│       ├── polizas.html          # Emisión de pólizas e intercepción de auditorías
│       └── clientes.html         # CRUD de clientes (Personas Físicas/Jurídicas)
├── database/
│   ├── schema_masque_fianzas.sql # Script SQL original de la BD
│   └── cf_schema.sql             # Esquema ampliado para canales de cobro
├── verify.php                    # Diagnóstico visual del sistema
└── verify_system_end_to_end.php  # Verificador técnico del CLI e integridad de BD
```

---

## ⚖️ Estándar de Auditoría Inmutable NOFTRAB v4.0

La plataforma implementa rigurosamente las normas del **Estándar NOFTRAB v4.0** para la trazabilidad absoluta de expedientes y transacciones financieras:
1. **Justificación Escrita Obligatoria:** Cualquier alteración manual (ajuste, anulación, edición de montos) sobre pólizas, pagos o comisiones requiere una justificación escrita detallada (mínimo 10 caracteres).
2. **Registro de Doble Estado (JSON):** Se almacenan en la tabla `historial_ajustes` los estados completos del registro afectado antes del cambio (`before`) y después del cambio (`after`) en formato JSON.
3. **Inmutabilidad de Logs:** Los datos se escriben a nivel transaccional sin posibilidad de edición posterior, guardando la dirección IP, navegador y marca de tiempo del usuario responsable de forma indeleble.

---

## 👥 Control de Acceso Granular y Privacidad "Propios vs. Todos"

Para cumplir con las políticas de confidencialidad de la red comercial, el sistema aplica un filtro de privacidad a nivel de backend:
* **Filtro `solo_propios = 1`:** Si el perfil del usuario conectado (como el **Socio Comercial PDV**) tiene habilitada la restricción de datos propios, todos los listados de cotizaciones, pólizas, comisiones y pagos se filtrarán automáticamente por su identificador (`creado_por = {usuario_id}`, `emitida_por = {usuario_id}` o `registrado_por = {usuario_id}`).
* **Dashboard y Modales Restringidos:** Las tarjetas de estadísticas rápidas, el Top 5 de clientes y los modales analíticos del Dashboard calculan y muestran únicamente los datos pertenecientes a las operaciones de dicho usuario.
* **Bypass de Administrador:** Los perfiles de nivel Administrador principal (`usuario_id = 1`) poseen un bypass explícito en los helpers de base de datos para supervisar y auditar la totalidad de la plataforma global.

---

## 🔧 API Endpoints Adicionales (v4.0.0 Stable)

### Estadísticas de Pólizas
```
GET /backend/api/polizas_stats.php
- Retorna las cantidades de pólizas emitidas en el día, semana y mes actual.
- Retorna el Top 5 de clientes con más pólizas y sus conteos.
- Respeta automáticamente el filtro "Propios vs. Todos" según el perfil del usuario.
```

### Auditoría de Ajustes
```
POST /backend/api/ajustes.php
- Registra una justificación de modificación de transacciones.
- Parámetros: registro_id, tabla_afectada, modulo_afectado, valor_anterior, valor_nuevo, justificacion.
- Almacena el registro inmutable bajo las normas NOFTRAB v4.0.
```

### Motor de Perfiles
```
POST /backend/api/perfiles_engine.php
- Wrapper de API para el Administrador que invoca el script de Python perfiles_engine.py.
- Permite la creación y actualización granular y atómica de permisos por base de datos.
```

---

## 👨‍💻 Desarrollado por
MAS QUE FIANZAS - Equipo de Desarrollo Integrado  
*Estabilizado bajo el estándar NOFTRAB v4.0 — Mayo de 2026*
