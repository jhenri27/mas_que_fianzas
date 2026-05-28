# 📋 ESPECIFICACIONES TÉCNICAS Y DE ARQUITECTURA - MAS QUE FIANZAS (v4.0.0 Stable / NOFTRAB v4.0)

## 🎯 Objetivo Cumplido

Desarrollar una **plataforma integrada completa** bajo estándares de alta calidad técnica que:
✅ Integre el módulo cotizador React de Seguros de Ley y Fianzas comerciales en MAS QUE FIANZAS.
✅ Implemente gestión integral de usuarios con nomenclatura jerárquica automática (RED, DIR, PDV, VEN).
✅ Establezca control de accesos basado en roles (RBAC) y **Doble Capa de Seguridad (Layered Security)**.
✅ Proporcione auditoría total inmutable y cumplimiento estricto del **Estándar NOFTRAB v4.0**.
✅ Aplique restricciones de privacidad granular "Propios vs. Todos" en listados, widgets y modales.
✅ Cumpla plenamente con las regulaciones de la Superintendencia de Seguros en República Dominicana.

---

## 📦 COMPONENTES ENTREGADOS

### 1. BASE DE DATOS ✅
**Archivo Principal:** `database/schema_masque_fianzas.sql`

Tablas implementadas e integradas (21):
- `perfiles` - Gestión de roles y permisos generales.
- `usuarios` - Información y credenciales de usuarios.
- `permisos_perfil` - Asignación granular de permisos por módulo y función (incluye `solo_propios`).
- `modulos` - Definición de módulos del sistema.
- `funciones_modulo` - Funciones específicas por módulo.
- `auditoria_accesos` - Registro completo de auditoría de accesos.
- `sesiones_usuario` - Gestión de sesiones activas.
- `historial_password` - Historial de cambios de contraseña.
- `clientes` - Gestión de clientes (Personas Físicas y Jurídicas).
- `cotizaciones` - Sistema de cotizaciones de seguros de ley y fianzas (con `creado_por` FK).
- `polizas` - Gestión y emisión de pólizas (con `emitida_por` FK).
- `pagos` - Registro y validación contable de cobros (con `registrado_por` FK).
- `fianzas` - Gestión de fianzas comerciales.
- `siniestros` - Registro de reclamos y siniestralidad.
- `productos` - Catálogo de productos y tarifas dinámicas.
- `configuracion_sistema` - Parámetros SMTP y variables del cotizador.
- `usuarios_perfiles_adicionales` - Perfiles secundarios para multi-rol.
- `acceso_datos_usuario` - Restricciones personalizadas por base de datos.
- `notificaciones_alertas` - Sistema de alertas interactivas.
- `reportes_personalizados` - Reportes financieros y operativos configurables.
- `historial_ajustes` - **[NUEVA]** Bitácora inmutable de auditoría forense para ajustes de transacciones (Estándar NOFTRAB v4.0).

#### Esquema de la Tabla `historial_ajustes` (NOFTRAB):
```sql
CREATE TABLE IF NOT EXISTS historial_ajustes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    modulo_afectado VARCHAR(50) NOT NULL,
    tabla_afectada VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    valor_anterior JSON NOT NULL,
    valor_nuevo JSON NOT NULL,
    justificacion TEXT NOT NULL,
    fecha_ajuste TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    direccion_ip VARCHAR(45) NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 2. BACKEND (PHP + Python CLI) ✅

#### Archivos de Configuración y Core:
- `backend/config.php` (312 líneas):
  - Conexión a BD mediante patrón Singleton y soporte mysqli.
  - Constantes de seguridad y Mailer SMTP.
  - Función de validación de permisos `tienePermiso()`.
  - Función de restricción de datos propios `restringirSoloPropios()`.
  - Helper inmutable bajo norma NOFTRAB `registrarAjuste()`.

#### Clases de Negocio:
- `backend/UsuarioManager.php` (512 líneas) - Gestión de usuarios, comisiones y ETL idempotente.
- `backend/PerfilManager.php` (512 líneas) - Gestión de perfiles y lógica de herencia de permisos.
- `backend/Autenticacion.php` (420 líneas) - Sesiones en BD, rate limiting y bloqueo temporal.
- `backend/PagoManager.php` (480 líneas) - Conciliación bancaria y amortización de comisiones.
- `backend/perfiles_engine.py` - Motor CLI en Python para transacciones atómicas de permisos y logging JSON de auditorías en BD.

#### APIs REST (/backend/api/):
- `auth.php` - Endpoints de sesión, login, logout e inicio.
- `usuarios.php` - CRUD e importación masiva de redes.
- `perfiles.php` - CRUD y obtención de la malla de roles.
- `perfiles_engine.php` - Wrapper seguro en PHP para ejecución del motor de Python.
- `polizas.php` - Listado y emisión de pólizas con filtros de privacidad.
- `polizas_stats.php` - Endpoint dinámico de estadísticas de emisión diaria/semanal/mensual.
- `pagos.php` - Registro e historial contable.
- `comisiones.php` - Listados de comisiones individuales y de red.
- `ajustes.php` - Registro inmutable de modificaciones bajo la norma NOFTRAB v4.0.

---

### 3. FRONTEND (HTML/CSS/JavaScript) ✅

#### Páginas e Interfaces:
- `frontend/index.html` - Página de login responsiva con transiciones suaves.
- `frontend/dashboard.html` - Dashboard Shell principal con diseño Glassmorphism e integración de submódulos en iframes.
- `frontend/recuperar.html` - Recuperación de contraseñas por email con token seguro de 30 minutos.
- `frontend/cambiar-password.html` - Interfaz obligatoria de actualización de credenciales.

#### Estilos CSS (assets/):
- `login.css` - Estilizado responsivo con fondos HSL degradados.
- `dashboard.css` - Sidebar responsive, modales superpuestos, y estilos para el **Widget de Pólizas Emitidas** con pills degradados y barras de progreso animadas.
- `modulos.css` - Estilos compartidos e iframe bridges.
- `skin-engine.css` - Selector estético dinámico (Premium dark/light y glassmorphism).

#### JavaScript (assets/):
- `api-client.js` - Cliente HTTP unificado con soporte para Bearer Token y manejo global de errores.
- `login.js` - Validación en cliente y animaciones de carga.
- `dashboard.js` - Navegación asíncrona, control de modales, carga dinámica de módulos según perfil, e integración del modal de justificaciones obligatorias `#modalAjustesAuditoria`.
- `data-export.js` - Motor de exportación avanzada (PDF corporativo MQF, Excel, CSV, JSON, ZIP).
- `logo_b64.js` - Logo MQF optimizado para impresión.

---

## 👥 PRIVACIDAD Y RESTRICCIÓN "PROPIOS VS. TODOS"

Para garantizar la confidencialidad de la red comercial y ajustarse estrictamente a las normas de negocio, la plataforma implementa una restricción de datos en la capa de datos:

1. **La columna `solo_propios` en la base de datos:**
   - La tabla `permisos_perfil` contiene una columna booleana `solo_propios`.
   - Cuando se asignan permisos a un perfil (ej. **Socio Comercial PDV**), el Administrador puede activar esta opción.

2. **Inyección en el Backend (PHP APIs):**
   - La función `restringirSoloPropios($usuario_id, $modulo)` comprueba si el perfil del usuario activo tiene la restricción activa.
   - En Cotizaciones, Pólizas, Pagos y Comisiones se inyecta la cláusula SQL:
     - `creado_por = {usuario_id}`
     - `emitida_por = {usuario_id}`
     - `registrado_por = {usuario_id}`
     - `usuario_id = {usuario_id}`
   - El Administrador (`usuario_id = 1`) posee un bypass directo en `config.php` y visualiza la totalidad de los registros de forma global.

3. **Restricción Estricta en el Dashboard y Modales:**
   - El **Socio Comercial PDV** solo visualizará en los widgets de estadísticas, barras del Top 5 de clientes y en el Modal Enriquecido de Pólizas Emitidas lo concerniente a sus operaciones propias. Las APIs filtran los datos de origen, impidiendo la manipulación en el frontend.

---

## 📊 MALLA DE PERMISOS IMPLEMENTADA

| Módulo | Admin | Gte. Tec | Gte. Con | Gte. Com | Socio Comercial PDV | Cajero | Auditor | Usuario |
|--------|-------|----------|----------|----------|----------------------|--------|---------|---------|
| Dashboard | C | C | C | C | **P (Propio)** | P | C | P |
| Clientes | C/E | C/E | C | C/E | **C (Propio)** | ❌ | C | C |
| Pólizas | T | C/E | C | C/E | **C (Propio)** | ❌ | C | C |
| Fianzas | T | C/E | C | C/E | **C (Propio)** | ❌ | C | C |
| Pagos | T | ❌ | V/R | C | ❌ | Reg | C | CP |
| Cotizaciones | T | C/E | ❌ | C/E | **C/E (Propio)** | ❌ | C | CPr |
| Productos | T | C/E | C | C | ❌ | ❌ | C | ❌ |
| Configuración | T | PT | PC | ❌ | ❌ | ❌ | C | ❌ |
| Reportes | T | Tec | Fin | Com | **Com (Propio)** | Caja | Todos | Lim |
| Siniestros | T | C/E | C | Seg | **C (Propio)** | ❌ | C | CP |

*Leyenda: C=Completo, C/E=Crear/Editar, C=Consultar, T=Total, P=Parcial, V=Validar, R=Reportes, PT=Parámetros Técnicos, PC=Parámetros Contables, Reg=Registrar, CP=Consultar Propio, CPr=Crear Propio, Seg=Seguimiento, Caja=De Caja, Lim=Limitados, ❌=Bloqueado*

---

## ⚖️ HISTORIAL DE AUDITORÍA DE AJUSTES INMUTABLE (NOFTRAB)

El cumplimiento de la norma NOFTRAB v4.0 garantiza la inmutabilidad de los expedientes y la auditoría forense del sistema:
* **Flujo de Intercepción:** Cualquier cambio sobre una póliza, pago o comisión activa el modal `#modalAjustesAuditoria`.
* **Validación de Justificación:** El sistema bloquea el envío si el usuario no introduce una justificación superior a los 9 caracteres.
* **Captura de Estados (JSON):** El backend extrae la fila actual de la tabla en cuestión y la almacena de forma íntegra como `valor_anterior` en formato JSON, aplicando posteriormente el cambio y guardando el nuevo estado completo como `valor_nuevo`.
* **Trazabilidad:** Se registra de forma automática el `usuario_id`, `modulo_afectado`, `tabla_afectada`, `registro_id`, `direccion_ip` y la marca de tiempo de forma inmutable.

---

## 📈 MÉTRICAS DEL PROYECTO (v4.0.0 Stable)

| Métrica | Valor |
|---------|-------|
| Líneas de código PHP | ~2,600 |
| Líneas de código JavaScript | ~1,250 |
| Líneas de CSS | ~1,400 |
| Líneas SQL (BD) | ~1,600 |
| Tablas de BD | 21 |
| Endpoints API | 26 |
| Roles predefinidos | 9 (incluye Socio Comercial PDV) |
| Módulos del Dashboard | 12 |
| Líneas de Python | ~250 |
| Documentación (líneas) | ~2,500 |

---

## 🏆 ESTADO DEL PROYECTO

- **Backend core:** ✅ 100% Estabilizado
- **Malla de perfiles e inyección "Propios vs. Todos":** ✅ 100% Completada
- **Widget y Modal Premium de Pólizas:** ✅ 100% Operativo y Responsivo
- **Estándar de Auditoría NOFTRAB v4.0:** ✅ 100% Integrado e Inmutable
- **Avatar Superior Interactivo:** ✅ 100% Implementado
- **Documentación general:** ✅ Sincronizada y Actualizada para Commit Directo

---

*Especificaciones técnicas y arquitecturales de MAS QUE FIANZAS.*  
*Actualizado conforme a las directrices de la versión v4.0.0 Stable de la plataforma. Mayo de 2026.*
