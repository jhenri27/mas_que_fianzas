# 📚 ÍNDICE MAESTRO - PLATAFORMA MÁS QUE FIANZAS (v4.0.0 Stable / NOFTRAB v4.0)

## 🎯 Introducción Rápida

Bienvenido a la **Plataforma Integrada MÁS QUE FIANZAS (v4.0.0 Stable)**, un sistema empresarial de emisión de seguros, fianzas y procesos contables. Esta versión implementa las normativas del **Estándar de Auditoría Inmutable NOFTRAB v4.0**, control de accesos granular con doble capa de seguridad y el nuevo panel interactivo premium del Dashboard.

- **Versión:** 4.0.0 Stable
- **Estado:** ✅ Estabilizado y Listo para Producción (MS-LS v2.0)
- **Tecnología:** PHP 8.2 + MySQL 5.7+ + Python 3.14.5 + Vanilla JS
- **Estándar de Auditoría:** NOFTRAB v4.0 (Logs inmutables JSON + Justificación obligatoria)

---

## 📑 DOCUMENTACIÓN GENERAL

### Guías Principales

| Documento | Propósito | Tiempo de Lectura |
|-----------|-----------|------------------|
| **README.md** | Documentación técnica completa y referencia formal de APIs v4.0. | 20 min |
| **INSTALACION_RAPIDA.md** | Guía de instalación rápida y configuración en WAMP. | 10 min |
| **ESPECIFICACIONES.md** | Resumen técnico, matriz de permisos y reglas de privacidad "Propios vs. Todos". | 15 min |
| **INTEGRACION_COTIZADOR.md** | Documentación del módulo cotizador integrado de seguros y fianzas. | 10 min |
| **COMIENZA_AQUI.md** | Guía rápida de orientación adaptada según el perfil del usuario. | 10 min |
| **RESUMEN_EJECUTIVO.md** | Visión ejecutiva de novedades y el estándar NOFTRAB v4.0. | 10 min |
| **PROYECTO_COMPLETADO.md** | Resumen de la entrega, árbol de archivos y estadísticas actualizadas. | 5 min |
| **INDICE_MAESTRO.md** | Este archivo - Mapa y navegación completa del proyecto. | 5 min |

---

## 🗂️ ESTRUCTURA DEL PROYECTO

### Directorio Raíz
```
PLATAFORMA_INTEGRADA/
├── backend/              # Servidores, APIs y motores transaccionales (PHP + Python)
├── frontend/             # Vistas, estilos, modales e interacciones del cliente (HTML + CSS + JS)
├── database/             # Scripts SQL de base de datos y migraciones
├── .htaccess             # Reglas Apache y cabeceras de seguridad
├── README.md             # Guía técnica general
├── INSTALACION_RAPIDA.md # Guía paso a paso de WAMP
├── ESPECIFICACIONES.md   # Matriz de roles, tablas y políticas NOFTRAB
├── INTEGRACION_COTIZADOR.md # Detalles del cotizador React y tarifas
├── COMIENZA_AQUI.md      # Orientación de perfiles
├── RESUMEN_EJECUTIVO.md  # Resumen de novedades de la versión
├── PROYECTO_COMPLETADO.md # Entrega final y checklist
└── INDICE_MAESTRO.md     # Este archivo
```

### Backend - `/backend`
```
backend/
├── api/                  # Endpoints REST de la aplicación
│   ├── auth.php          # Login, logout y sesiones seguras en BD
│   ├── usuarios.php      # CRUD de usuarios e importación masiva XLSX
│   ├── perfiles.php      # CRUD y listados de perfiles
│   ├── perfiles_engine.php # Wrapper seguro PHP que ejecuta a Python
│   ├── polizas.php       # Gestión y emisión de pólizas
│   ├── polizas_stats.php # Estadísticas dinámicas de emisión con filtro propio
│   ├── pagos.php         # Gestión contable de cobros
│   ├── comisiones.php    # Listados de comisiones del árbol de comisiones
│   └── ajustes.php       # Registro de ajustes de auditoría NOFTRAB v4.0
├── config/
│   └── smtp.json         # Configuración del servidor de correo SMTP
├── logs/
│   ├── error.log         # Errores del intérprete PHP
│   ├── smtp.log          # Bitácora de envíos de correo
│   └── audit.log         # Registro de auditoría básico en texto
├── config.php            # Base de datos central, helpers y registro de auditoría
├── Autenticacion.php     # Clase controladora de sesiones
├── UsuarioManager.php    # Lógica de usuarios y ETL
├── PerfilManager.php     # Validación de la malla de permisos
├── PagoManager.php       # Motor contable de cobros y transacciones
├── Mailer.php            # Gestor SMTP PHPMailer
└── perfiles_engine.py    # Motor CLI en Python para transacciones de permisos en MySQL
```

### Frontend - `/frontend`
```
frontend/
├── assets/                # Estilos, scripts y librerías
│   ├── api-client.js     # Cliente HTTP con tokens Bearer
│   ├── login.js          # Control de login y credenciales
│   ├── login.css         # Estilizado responsivo de login
│   ├── dashboard.js      # Lógica principal del Dashboard, modales y llamadas API
│   ├── dashboard.css     # Estilos premium, glassmorphism y widget de pólizas
│   ├── modulos.css       # Estilos compartidos e impresiones
│   ├── skin-engine.css   # Motor de skins dinámicos
│   ├── data-export.js    # Motor de exportación PDF, Excel, CSV, ZIP
│   └── logo_b64.js       # Logo MQF corporativo en base64
├── modulos/              # Submódulos del sistema cargados en iframes
│   ├── usuarios.html     # CRUD de usuarios y comisiones dinámicas
│   ├── cotizaciones.html # Cotizador de seguros de ley y fianzas
│   ├── polizas.html      # Emisión de pólizas con puente iframe
│   └── clientes.html     # CRUD de clientes
├── index.html            # Acceso al sistema (Login)
├── dashboard.html        # Estructura del Dashboard principal
├── recuperar.html        # Solicitud de nueva contraseña
└── cambiar-password.html # Cambio obligatorio de contraseña
```

---

## 📈 NOVEDADES Y FUNCIONALIDADES DE LA VERSIÓN 4.0.0 STABLE

### 1. Widget Premium de Pólizas Emitidas (Dashboard)
* **Ubicación Estratégica:** En la columna izquierda, justo arriba de las acciones rápidas (desplazando a estas hacia abajo).
* **Diseño Glassmorphism Premium:** Bordes redondeados suaves, sombras y fondo translúcido.
* **Pills Degradados HSL:** Cantidades del día (`.pill-diario`), semana (`.pill-semanal`) y mes (`.pill-mensual`) con degradados suaves y modernos.
* **Top 5 de Clientes con Barras de Progreso:** Muestra los 5 clientes con más pólizas y barras de progreso animadas horizontales que se cargan suavemente (`width: 0%` a `X%`).
* **Botón Maximizar:** Abre instantáneamente el Modal Detallado.

### 2. Modal Analítico Enriquecido (`#modalPolizasDetalle`)
* Despliega la tabla completa de clientes principales y las pólizas emitidas recientemente con filtros interactivos.
* **Restricción de Datos Estricta:** Si el usuario es un **Socio Comercial PDV**, el modal se filtra estrictamente en el origen (API de backend) para mostrar exclusivamente sus propias transacciones. El Administrador mantiene la vista global consolidadada de toda la plataforma.

### 3. Historial de Ajustes Inmutable (Norma NOFTRAB v4.0)
* Cualquier cambio operativo exige una justificación escrita obligatoria (mínimo 10 caracteres).
* El endpoint `/backend/api/ajustes.php` captura el estado anterior y el nuevo en formato JSON y los escribe en la tabla `historial_ajustes` de forma indeleble.

### 4. Avatar Superior Interactivo
* La cabecera `.user-info` responde al hover del ratón con escala de `1.02`, cursor pointer y fondo translúcido.
* Al hacer click sobre el avatar, se despliega instantáneamente la interfaz de edición "Mi Perfil".

### 5. Puente de Intercepción Iframe
* El submódulo de pólizas (`polizas.html`) intercepta las peticiones de alteración de estado.
* Si detecta que está en un iframe, delega la visualización del modal de auditoría al dashboard padre mediante `window.parent.solicitarAjusteAuditoria(...)`, retornando el flujo al completarse la justificación.

---

## 🔑 MALLA DE ROLES PREDEFINIDOS (9 Roles)
1. **Administrador** (ID: 1) - Acceso total global. Bypass de restricciones e inspección de logs.
2. **Gerente Técnico** - Gestión de operaciones, catálogo y emisión general.
3. **Gerente Contador** - Gestión financiera y contable. Visualiza comisiones y reportes.
4. **Gerente Comercial** - Coordinación comercial general de la red.
5. **Socio Comercial PDV** - Acceso restringido estrictamente a sus propios clientes, cotizaciones y pólizas.
6. **Cajero** - Registro de cobros y validación básica.
7. **Auditor** - Acceso general de lectura para auditoría y visualización de logs.
8. **Usuario** - Acceso básico e individualizado.

---

## 🔧 CONFIGURACIÓN DEL SISTEMA

### Cambiar Conexión a BD
**Archivo:** `backend/config.php`
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', ''); // Vacío por defecto en WAMP
define('DB_NAME', 'masque_fianzas_integrada_01');
```

### Configuración SMTP
Se administra de manera visual y sin alterar código directamente desde el Dashboard:
```
Dashboard → módulo Seguridad → Configuración SMTP → Llenar campos → Guardar
```
*Se almacena dinámicamente en el archivo JSON `backend/config/smtp.json`.*

---

*Índice Maestro de la plataforma integrada MÁS QUE FIANZAS.*  
*Actualizado para la versión v4.0.0 Stable. Mayo de 2026.*
