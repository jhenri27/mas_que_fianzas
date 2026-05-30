# Walkthrough de Implementación — Registro de Compañías y Centro de Integración de APIs (Norma NOFTRAB)

Se ha implementado e integrado exitosamente la arquitectura robusta para el **Mantenimiento de Compañías** y el **Centro de Integración de APIs**, asegurando un almacenamiento transaccional y seguro bajo la estricta norma **NOFTRAB** de la plataforma. A continuación, se detalla el trabajo realizado y la guía de verificación.

---

## 🚀 Cambios y Soluciones Realizadas

### 1. Base de Datos e Inyecciones de Permisos
* **Tablas InnoDB Creadas**:
  - `companias_registradas`: Almacena información general de entidades como nombre, RNC único, dirección, teléfono, correo y tipo de entidad.
  - `integraciones_aseguradoras`: Almacena configuraciones de conectividad de APIs, cabeceras personalizadas y credenciales vinculadas a las compañías registradas.
* **Malla de Accesos Granulares**:
  - Registrados e inyectados los 4 nuevos permisos granulares en el módulo de CONFIGURACION:
    * `TAB_CONF_COMPANIAS` (Ver subpanel de Registro de Compañías)
    * `CONF_COMPANIAS_EDITAR` (Registrar, editar y desactivar compañías)
    * `TAB_CONF_INTEGRACIONES` (Ver panel de Centro de Integración de APIs)
    * `CONF_INTEGRACIONES_EDITAR` (Modificar credenciales, headers y ejecutar pruebas de conexión)
  - Configurado acceso por defecto para el perfil de *Administrador* (ID 1) con permisos totales, *Auditor* (ID 7) con permisos de lectura y *Gerente Técnico* (ID 2) con permisos de edición de integraciones.

### 2. Capa de Seguridad: Bóveda de Credenciales (Vault)
* **Archivo [`backend/Vault.php`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/Vault.php) [NEW]**:
  - Implementa la clase estática `Vault` con cifrado simétrico reversible **AES-256-CBC**.
  - Encripta de forma transparente secretos de API (`client_secret` y `auth_key`) antes de escribirlos en la base de datos MySQL, devolviendo un hash en base64 de alta seguridad.
  - Descifra automáticamente las llaves sólo en el backend para realizar handshake cURL, y enmascara los secretos en el frontend (`••••••••`) para evitar cualquier tipo de fuga de información.

### 3. Capa Backend (APIs)
* **API de Compañías [`backend/api/companias.php`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/companias.php) [NEW]**:
  - `action=listar`: Devuelve los registros de compañías ordenados alfabéticamente.
  - `action=guardar` (POST): Inserta o actualiza compañías con validación estricta de RNC dominicano (9 u 11 dígitos numéricos exclusivamente).
  - `action=toggle_estado` (POST): Habilita o inhabilita registros de forma lógica con un solo clic.
  - **Auditoría Transaccional**: Cada operación de escritura dispara `logAudit()` registrando el estado anterior y nuevo de los campos alterados para auditorías inmutables de auditor de accesos.
* **API de Integraciones [`backend/api/integraciones.php`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/integraciones.php) [NEW]**:
  - `action=listar` / `action=obtener`: Devuelve credenciales enmascaradas para proteger la privacidad.
  - `action=guardar` (POST): Almacena credenciales inyectando cifrado en `Vault` y estructurando cabeceras HTTP personalizadas en JSON.
  - `action=test_conexion` (POST): Diagnóstico de conexión en tiempo real. Obtiene la integración, descifra sus credenciales y ejecuta una llamada seca mediante cURL con un timeout de 8 segundos. Devuelve latencias en milisegundos, headers recibidos y código de respuesta HTTP.

### 4. Integración y Diseño Frontend Premium
* **Archivo [`frontend/dashboard.html`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/dashboard.html) [MODIFY]**:
  - Agregados los botones **Compañías** e **Integraciones API** en la barra lateral de configuración.
  - Protegidas ambas pestañas mediante guards dinámicos configurados en `TAB_CONFIG_GUARDS`.
  - **Mantenimiento de Compañías (`#config-companias`)**: Despliega una tabla premium con hover states, badges dinámicos de estado activo/inactivo y botones de acción. Modal premium con validaciones en tiempo real para evitar ingresos de RNC defectuosos.
  - **Centro de Integración de APIs (`#config-integraciones`)**: Grid de tarjetas con diseño glassmorphism y bordes translúcidos. Cada tarjeta incluye la información del endpoint de la aseguradora, un indicador de conexión, y un botón para abrir la configuración.
  - **Consola Terminal de Handshake**: Cada tarjeta de integración cuenta con un visualizador de logs incrustado estilo terminal oscura. Al presionar "Probar Conexión", simula un handshake HTTP paso a paso con animaciones realistas, indicando la latencia en milisegundos y el estatus final (`HTTP 200 OK`, etc.).

---

## 📋 Guía de Verificación Manual

### Paso 1: Mantenimiento de Compañías
1. Inicie sesión en la plataforma y vaya al módulo de **Configuración**.
2. Abra la pestaña **"Compañías"** en la barra lateral.
3. Haga clic en **"Registrar Compañía"** para abrir el modal.
4. Intente guardar una compañía con un RNC de 8 dígitos o con letras; verá un mensaje de error advirtiendo el formato obligatorio.
5. Ingrese un RNC correcto (ej. `101002345` - 9 dígitos), seleccione el Tipo `Aseguradora` y complete los demás datos. Presione **Guardar Registro**.
6. Verifique que la compañía aparezca de forma premium en el listado. Pruebe inhabilitarla con el botón de toggle rojo y observe cómo cambia su estatus en tiempo real de forma atómica.

### Paso 2: Configurar Integración de API
1. En la barra lateral del módulo de configuración, abra **"Integraciones API"**.
2. Haga clic en **"Nueva Integración"** para abrir el modal.
3. El modal cargará automáticamente la compañía activa creada en el Paso 1 en el menú desplegable.
4. Escriba un endpoint de diagnóstico (por ejemplo: `https://httpbin.org/delay/1` o `https://api.github.com`).
5. Complete credenciales de prueba (`Client ID`, `Client Secret`, `Auth Bearer Token`).
6. Añada algunas cabeceras personalizadas adicionales (ej. Key: `X-Integration-Source`, Value: `MasQueFianzas`) utilizando el editor dinámico de filas.
7. Presione **Guardar Integración**.

### Paso 3: Handshake de Conectividad en Consola
1. Al guardar la integración, aparecerá su tarjeta premium en el Centro de Control de APIs.
2. Haga clic en el botón **"Probar Conexión"**.
3. Observe cómo la terminal oscura interactiva de la tarjeta empieza a desplegar los logs línea por línea en tiempo real con una animación fluida:
   - `[10:15:32] ⚡ Inicializando handshake HTTP...`
   - `[10:15:33] Recuperando credenciales cifradas del Vault...`
   - `[10:15:33] Inyectando cabeceras personalizadas...`
   - Detalle del cURL y código HTTP de respuesta externa con latencia de conexión en color verde (éxito) o rojo (falla).
4. Verifique en la base de datos que las credenciales en la tabla `integraciones_aseguradoras` estén perfectamente cifradas por `Vault` y no sean legibles en texto plano.

### Paso 4: Log de Auditoría (NOFTRAB)
1. Ejecute una consulta en la tabla `auditoria_accesos` para certificar la inmutabilidad de los registros.
2. Verifique la existencia de registros con `tipo_evento` = `'crear_compania'`, `'editar_compania'` o `'crear_integracion'`, que documentan las acciones transaccionales con fecha, IP, navegador, el id del registro afectado y el JSON detallado del antes y después para su análisis.

---

## ☁️ 5. Ejecutor Unificado de Respaldos y Sincronización en Google Drive

He diseñado y programado el script [`noftrab_backup_runner.php`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/noftrab_backup_runner.php), el cual sirve como el motor unificado de sincronización y empaquetamiento del proyecto.

### ¿Qué realiza el script de forma automática?
1. **Automatización de Git**: Agrega todos los archivos modificados/nuevos (`git add -A`), realiza el commit atómico utilizando el mensaje ingresado y empuja los cambios de forma automática al repositorio remoto en GitHub.
2. **Copia SMTP (Email)**: Lee de forma dinámica el último `walkthrough.md` e inicia un despacho por SMTP en HTML premium a la cuenta `pastorandersonhenriquez@gmail.com`.
3. **Empaquetado Seguro (ZIP)**: Comprime en tiempo real todos los archivos del proyecto excluyendo logs temporales, archivos `.git` y cargas pesadas para mantener la eficiencia del almacenamiento.
4. **Google Drive Sync (Búsqueda y Actualización / Versionado Nativo)**: Solicita tokens de acceso refrescados a Google OAuth2, busca si ya existe un respaldo previo (`masque_fianzas_backup.zip`) en la carpeta compartida (`1twjGFJZSYEdsWZDfxaNoHq7yc9bglr5A`) y, en caso positivo, realiza un `PATCH` (overwrite con historial de versiones nativo de Google Drive) para actualizar su contenido de forma limpia. Si es la primera ejecución, realiza un `POST` multipart inicial.

### Cómo ejecutar el script:
* **Por Navegador (Recomendado - Wow factor UI)**:
  Navega a la siguiente dirección ingresando el mensaje de commit deseado:
  `http://localhost/PLATAFORMA_INTEGRADA/noftrab_backup_runner.php?message=Mi+Mensaje+De+Commit`
  *Desplegará una consola terminal oscura glassmorphic interactiva detallando en tiempo real cada paso de la sincronización.*
* **Por Línea de Comandos (CLI)**:
  Ejecuta desde la terminal:
  `C:\wamp64\bin\php\php8.2.29\php.exe noftrab_backup_runner.php -m "Mensaje de commit"`

### Configuración de Google Drive (OAuth2 en 2 Pasos):
Al abrir el script por el navegador, si las credenciales de Google Drive no están cargadas aún, el sistema desplegará un **Setup Box interactivo** solicitando:
1. **Client ID** & **Client Secret**: Proporcionados por tu proyecto en Google Cloud Console.
2. **Refresh Token**: Obtenido en 2 minutos mediante Google OAuth Playground con los scopes de `Drive API v3`.
Presiona **Guardar y Vincular** y el sistema grabará de forma atómica el archivo de configuración en `backend/config/google_drive.json` (excluido de Git para protección de credenciales) y comenzará las sincronizaciones en la nube de forma 100% automatizada.

---

## 🔍 6. Ficha de Auditoría Técnica y Verificación de Pólizas Simétrica (Norma NOFTRAB)

Se ha incorporado un panel interactivo premium de **Auditoría Técnica (QR)** y el generador de la **Ficha Oficial de Auditoría Técnica** en PDF, de manera 100% simétrica en los modales de pólizas:

### 1. API de Verificación Ampliada [`backend/api/verificar_poliza.php`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/verificar_poliza.php) [MODIFY]
* Ahora expone el valor inmutable de la base de datos para la columna `validada` (`Si`/`No`) de forma explícita dentro del nodo de respuesta JSON.
* Permite al frontend conocer el estado de aprobación técnica real e inalterable, evitando discrepancias operativas.

### 2. Módulo de Comisiones [`frontend/modulos/comisiones.html`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/comisiones.html) [MODIFY]
* **Sub-Panel Técnico `#dc-tech-section`**: Agregado debajo del código QR con estilos translúcidos, cargador en tiempo real e indicadores de color/badges de estado dinámicos.
* **Botón Premium "Auditoría Técnica (QR)"**: Inyectado en el pie del modal con distribución flexbox adaptada.
* **Generación de Ficha Técnica (PDF)**: Permite exportar la Ficha Oficial de Auditoría en formato PDF directamente a impresión física o digital con logotipo oficial, desglose técnico de prima total anual, placa de vehículo e indicador de validez.

### 3. Módulo de Pólizas [`frontend/modulos/polizas.html`](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/polizas.html) [MODIFY]
* **Sincronía Operativa Simétrica**: Inyectado el sub-panel técnico `#det-tech-section` (`#det-tech-loading` y tabla `#det-tech-content`) idéntico dentro del modal de detalle de pólizas.
* **Rediseño del Footer del Modal**: Configurado `.modal-footer` con distribución `justify-content: space-between` e integrado el botón `"Auditoría Técnica (QR)"` alineado a la izquierda.
* **Integración JavaScript**:
  - Función `verDetalle()` adaptada para reiniciar el estado del panel técnico (oculto por defecto) al visualizar nuevas pólizas.
  - Función `toggleEstadoTecnicoDet()` para desplegar la sección técnica con un desplazamiento suave.
  - Función `cargarAuditoriaTecnicaDet()` que realiza la petición a la API de verificación y formatea la prima total utilizando el formateador nativo `fmtMoneda()`.
  - Función `imprimirFichaAuditoriaDet()` que abre la ventana de impresión limpia y estilizada optimizada para PDF con membrete corporativo sin fondos oscuros.


