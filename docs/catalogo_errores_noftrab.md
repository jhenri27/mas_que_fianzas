# 📚 Catálogo de Errores Codificados y Guía de Resolución (KEDB — NOFTRAB v4.0)

Este catálogo codificado (**Known Error Database - KEDB**) establece la taxonomía oficial de errores del sistema **MÁS QUE FIANZAS** bajo las **Normas NOFTRAB v4.0** y la **Regla 4-VAF**. Permite clasificar, auditar y resolver de forma acelerada cualquier incidencia funcional, impositiva, de seguridad o de interfaz de usuario.

---

## 📋 Taxonomía General de Codificación

| Rango de Código | Categoría Dominio | Descripción |
| :--- | :--- | :--- |
| **`ERR-VAF-[001..099]`** | **Reglas de Negocio y Unicidad (VAF)** | Validaciones de Cédula, RNC, Chasis/VIN, Placas, Fianzas y Justificaciones. |
| **`ERR-UI-[100..199]`** | **Diseño, Interfaz y Frontend (UX/UI)** | Skins Obsidian Dark, modales `MQF.confirm`, responsive layouts y CSS. |
| **`ERR-SEC-[200..299]`** | **Seguridad, Permisos y Sesiones (CSO)** | Tokens JWT/Bearer, permisos granulares RBAC, OWASP y carga de archivos. |
| **`ERR-DOC-[300..399]`** | **Impresión, Impuestos y Documentación** | Régimen fiscal ISC 16% / ITBIS 0%, motores PDF y catálogo de manuales. |

---

## 🛑 Seccion 1: Reglas de Negocio y Unicidad (`ERR-VAF-*`)

### `ERR-VAF-001`: Intento de Documento Duplicado entre Clientes Distintos
- **Síntoma**: La API responde con mensaje de advertencia e impide guardar la cotización o expediente de cliente.
- **Causa Raíz**: El documento (Cédula/RNC/Pasaporte) ya se encuentra registrado a nombre de otro cliente en la tabla `clientes`, `cotizaciones` o `usuarios`.
- **Regla NOFTRAB**: *No se permite asignar el mismo documento de identidad a dos clientes o entidades distintas.*
- **Solución**: Verificar si el cliente ya existe en el padrón del sistema o corregir el número introducido.

### `ERR-VAF-002`: Error de Algoritmo Luhn Mod 10 en Cédula Dominicana
- **Síntoma**: Aparece un badge naranja de advertencia `#c2410c` y se desactiva el botón de guardado directo.
- **Causa Raíz**: La cédula introducida no satisface la suma ponderada del dígito verificador del algoritmo Luhn (11 dígitos).
- **Solución**: Corregir la cédula por una oficial emitida por la JCE.

### `ERR-VAF-003`: Error de Algoritmo Mod 11 DGII en RNC Comercial
- **Síntoma**: Rechazo automático en la creación de sociedades o personas jurídicas.
- **Causa Raíz**: El RNC de 9 dígitos no cumple con el algoritmo oficial de verificación de la DGII.
- **Solución**: Validar el RNC directamente en la consulta pública de la DGII antes de ingresarlo.

### `ERR-VAF-004`: Intento de Vehículo Duplicado (Chasis/VIN o Placa)
- **Síntoma**: Mensaje de error: *"El Chasis/VIN o Placa ya se encuentra registrado en la cotización/póliza X..."*.
- **Causa Raíz**: Se intenta procesar una fianza/seguro para un vehículo con el mismo Chasis o Placa registrado en otra póliza activa.
- **Solución**: Consultar el estado de la póliza previa o solicitar un ajuste excepcional en el **Centro Técnico**.

### `ERR-VAF-005`: Intento de Número de Fianza Duplicado
- **Síntoma**: Impedimento de guardado de emisión de fianza en `fianzas.php`.
- **Causa Raíz**: El correlativo o número de fianza ya existe en la tabla `fianzas`.
- **Solución**: Asignar un nuevo correlativo de fianza autorizado por la aseguradora.

### `ERR-VAF-006`: Justificación Técnica Insuficiente en Cambio VAF
- **Síntoma**: El botón "Enviar a Validación" permanece inactivo o el servidor retorna HTTP 400.
- **Causa Raíz**: El texto introducido en la justificación de cambio posee menos de 15 caracteres.
- **Solución**: Redactar una justificación formal detallada describiendo el motivo del ajuste.

### `ERR-VAF-007`: Desviación por Valores Hardcodeados (Incumplimiento de Principio Fundamental)
- **Síntoma**: Incongruencias en comportamientos dinámicos, valores estáticos no configurables o pérdida de interconexión entre módulos.
- **Causa Raíz**: Hardcodear cadenas, IDs, tasas, rutas o parámetros estáticos en lugar de utilizar las fuentes dinámicas de base de datos, APIs o servicios interconectados.
- **Regla NOFTRAB (Principio Fundamental)**: *Evita el harcodear: En cualquier escenario que se presente evita harcodear utilizando todos los medios posibles para mantener las interconexiones entre funciones y módulos.*
- **Solución**: Refactorizar para consultar dinámicamente el valor desde las configuraciones del sistema, base de datos o endpoints de API correspondientes.

---

## 🎨 Sección 2: Diseño e Interfaz Frontend (`ERR-UI-*`)

### `ERR-UI-101`: Desviación de Skin Obsidian Dark (Estilos Blancos Hardcodeados)
- **Síntoma**: Un cuadro modal o formulario muestra fondo blanco estridencial en lugar del tema oscuro de vidrio templado.
- **Causa Raíz**: Presencia de inline styles con `#ffffff`, `#f8fafc` o `#fff7ed` en lugar de las variables del tema (`var(--mqf-surface-1)`, `var(--mqf-surface-2)`, `var(--mqf-border)`).
- **Solución**: Reemplazar los colores hardcodeados por variables CSS de superficie y bordes con resplandor.

### `ERR-UI-102`: Uso de Diálogos o Confirmaciones Nativas del Navegador
- **Síntoma**: Aparece una ventana emergente gris nativa del navegador (`window.confirm()`).
- **Causa Raíz**: Invocación directa de funciones síncronas de JavaScript no estilizadas.
- **Solución**: Sustituir la llamada por `await MQF.confirm({ title, message, type: 'danger' })` provisto en `components.js`.

---

## 🔐 Sección 3: Seguridad, Permisos y Sesiones (`ERR-SEC-*`)

### `ERR-SEC-201`: Sesión Expirada o Token Inválido (HTTP 401)
- **Síntoma**: La llamada API retorna error de autenticación y redirige al login.
- **Causa Raíz**: El token Bearer/JWT almacenado en `localStorage` caducó por inactividad.
- **Solución**: Volver a iniciar sesión para generar un nuevo token.

### `ERR-SEC-202`: Permiso Granular Insuficiente (HTTP 403)
- **Síntoma**: *"Acceso denegado: Su perfil no cuenta con permisos para ejecutar esta acción"*.
- **Causa Raíz**: El perfil del usuario no posee activada la casilla `crear_datos`, `editar_datos` o `eliminar_datos` en `permisos_perfil` para el módulo objetivo.
- **Solución**: Solicitar al Administrador la concesión del permiso granular en el Administrador de Perfiles.

---

## 📄 Sección 4: Impresión, Impuestos y Documentación (`ERR-DOC-*`)

### `ERR-DOC-301`: Declaración Errónea de ITBIS en Cotizaciones de Seguros
- **Síntoma**: Un reporte o comprobante muestra ITBIS (18.00%) sobre primas de seguros.
- **Causa Raíz**: Confusión impositiva entre ventas gravadas generales e intermediación de seguros.
- **Regla Fiscal**: *Las primas de seguros están exentas de ITBIS (0%) según la Ley de Seguros 146-02 (SIS) y normativa DGII, aplacándose únicamente el Impuesto Selectivo al Consumo (ISC 16%).*
- **Solución**: Verificar que el cálculo utilice la exención de ITBIS y aplique la tasa del 16% ISC.

### `ERR-DOC-302`: Presentación de Etiquetas de Vehículos en Validación QR de Fianzas Comerciales
- **Síntoma**: Al escanear el código QR de validación de una fianza comercial (`FZ-2026-00001`), la página de verificación o la API devolvían etiquetas de vehículos (*Marca / Modelo*, *Año / Color*, *Placa*, *Matrícula*, *No. Chasis (VIN)*) y mensajes de *"circulación nacional"*.
- **Causa Raíz**: Ausencia de consulta a la tabla `fianzas` en `verificar_poliza.php` y evaluación estricta en `verificar-poliza.html` / `validar.html` que provocaba fallback a los elementos HTML por defecto de pólizas de vehículos.
- **Solución**: Implementar consulta explícita a la tabla `fianzas` en el backend cuando el número inicia por `FZ-` o el ramo es `FIANZAS COMERCIALES`, y conmutar dinámicamente la UI a *"Especificaciones de la Fianza Comercial"*, mostrando *Tipo de Fianza*, *Beneficiario*, *Monto Afianzado*, *Objeto / Referencia* y alerta de auditoría NOFTRAB v4.0.

### `ERR-DOC-303`: Impresión Indebida de Borrador de Póliza en Botón PDF de Cotización de Fianzas y Falta de Botón de Borrador Independiente
- **Síntoma**: Al presionar el botón azul "PDF" en Mis Cotizaciones de Fianzas, se generaba un cuerpo legal de póliza en borrador (Condiciones Particulares con 8 cláusulas y marca de agua) en lugar del formato limpio de Cotización Resumen. Además, no existía un botón dedicado para imprimir el Borrador de Póliza de forma independiente.
- **Causa Raíz**: La función renderizadora `renderTablaCotizaciones` en `frontend/modulos/fianzas.html` invocaba `imprimirFianzaPDF()` (motor del cuerpo legal de póliza) en lugar de `imprimirCotizacionFzPDF()` (motor de Cotización Resumen).
- **Solución**: Se vinculó el botón azul `PDF` a `imprimirCotizacionFzPDF(f.id, origen)` para imprimir la Cotización Resumen (formato de propuesta comercial con logo, desglose, requisitos, notas, contacto y código QR) y se agregó el botón morado `Borrador` vinculado a `imprimirBorradorFzPDF(f.id, origen)` para imprimir el cuerpo legal en borrador (con 8 cláusulas, marca de agua "Borrador Sin Valor Comercial" y código QR).

### `ERR-DOC-304`: Superposición Estética de Logo de Aseguradora en Encabezado de Cotización Resumen PDF
- **Síntoma**: La Cotización Resumen PDF mostraba el logo de la aseguradora seleccionada (*MultiSeguros*) estirado horizontalmente e invadiendo el rectángulo azul del encabezado del corredor. Asimismo, el documento carecía del logo institucional de MÁS QUE FIANZAS (+QF) en la esquina superior izquierda.
- **Causa Raíz**: `dibujarCotizacionFianzaSimplePDF` en `frontend/assets/data-export.js` cargaba `obtenerLogoAseguradoraB64(asegName)` dibujando una imagen ancha (28mm) que colisionaba con la barra azul iniciada en `ML + 26`.
- **Solución**: Se actualizó `dibujarCotizacionFianzaSimplePDF` para cargar únicamente `obtenerLogoMQFB64()` (logo oficial de +QUE FIANZAS) con ajuste proporcional `(ML, 7, 24, 20)`, asegurando la alineación limpia con la barra azul del corredor, manteniendo la aseguradora en la sección de tablas `Aseguradora | Precio / Prima Total` y el código QR de validación en la esquina inferior derecha.

### `ERR-DOC-305`: ReferenceError: obtenerLogoMQFB64 is not defined al Generar PDF de Cotización Resumen de Fianzas
- **Síntoma**: Al presionar el botón azul "PDF" en Mis Cotizaciones de Fianzas, se desplegaba una alerta roja (toast) `Error al generar PDF: obtenerLogoMQFB64 is not defined` y se interrumpía la descarga del archivo PDF.
- **Causa Raíz**: 1) Invocación de `obtenerLogoMQFB64()` sin verificación previa de existencia ni asignación global a `window` en `logo_b64.js` / `data-export.js`. 2) La caché del navegador retenía versiones obsoletas de los archivos JS (`data-export.js?v=20260808_v5`).
- **Solución**: 1) Declaración explícita de `window.obtenerLogoMQFB64` en `logo_b64.js` y `data-export.js`. 2) Implementación de evaluación condicional tolerante `typeof window.obtenerLogoMQFB64 === 'function'` con fallback directo a `window.LOGO_MQF_B64`. 3) Actualización de parámetros de cache-busting a `?v=20260810_v8` en los módulos HTML (`fianzas.html`, `cotizaciones.html`, `pwa/index.html`).

### `ERR-DOC-306`: Desalineación y Espaciado Vertical Insuficiente en Filas de Datos de Tipo de Fianza y Aseguradora en Cotización Resumen PDF
- **Síntoma**: Los datos de las secciones "Tipo de Fianza" y "Aseguradora" se renderizaban pegados e invadiendo la línea inferior de los rectángulos grises de encabezado.
- **Causa Raíz**: En `dibujarCotizacionFianzaSimplePDF` (`frontend/assets/data-export.js`), el salto previo a la impresión del texto de datos era insuficiente (`y += 8`), dejando solo 2mm entre el fondo del rectángulo gris y el baseline del texto.
- **Solución**: Se ajustó la altura del rectángulo gris a 5.5mm, se incrementó el salto vertical previo a `y += 10.5` (creando un margen limpio de 3.0mm entre la barra gris y la tipografía) y se ajustó el tamaño de fuente a 8.5pt, alineándose exactamente con el diseño de la Imagen 2.

### `ERR-DOC-307`: Fallo de Estilos CSS/JS y Error de Autenticación por Desconfiguración Nginx y config.local.php Ausente en Ambiente Desarrollo (DEV)
- **Síntoma**: Al acceder al ambiente DEV (`/PLATAFORMA_INTEGRADA/dev/frontend/`), la interfaz se renderizaba como HTML plano sin estilos CSS ni scripts, y el inicio de sesión fallaba en todos los usuarios.
- **Causa Raíz**: 1) Ausencia del archivo `/var/www/dev_plataforma/backend/config.local.php`, provocando que la conexión a MySQL utilizara `root@localhost` sin contraseña. 2) Falta de reglas de alias explícitas en Nginx para `/dev/` y `/PLATAFORMA_INTEGRADA/dev/` con manejo `$uri $uri/`, respondiendo con `index.html` (text/html) ante peticiones CSS/JS. 3) Falta de enrutamiento REST para la API de autenticación `/backend/api/auth/login` en DEV.
- **Solución**: 1) Creación de `/var/www/dev_plataforma/backend/config.local.php` con credenciales válidas (`masque_user`). 2) Configuración de bloques `location` en Nginx para `/dev/`, `/dev_plataforma/` y `/PLATAFORMA_INTEGRADA/dev/` con ejecución PHP y entrega estricta de CSS/JS. 3) Adición de enrutador REST para `/backend/api/auth/(.*)$` en DEV. 4) Actualización de hashes de contraseñas para los usuarios `admin`, `jtaveras` y `pdv.prueba`.

### `ERR-DOC-308`: Pérdida de Contexto DEV y Redirección Indebida al Dashboard de Producción
- **Síntoma**: Al iniciar sesión en DEV (`/dev/frontend/`) o navegar entre módulos, el sistema redirigía al usuario a `http://169.58.51.147/frontend/dashboard.html` (Producción), perdiendo el contexto de desarrollo.
- **Causa Raíz**: 1) `login.js` y los listeners de navegación evaluaban prefijos rígidos que omitían la subcarpeta `/dev/` al calcular las rutas relativas. 2) Los módulos carecían de un script detector de ambiente global para marcar la distintiva `[DEV]` en los títulos de pestañas del navegador, barras superiores y logotipos.
- **Solución**: 1) Creación de `frontend/assets/env-detector.js` para detectar automáticamente el ambiente DEV, anteponer `[DEV]` al `document.title` de las pestañas del navegador, inyectar la barra superior de advertencia `🧪 [DEV] AMBIENTE DE DESARROLLO Y PRUEBAS`, añadir insignias `DEV` al logo y encabezados, y reescribir redirecciones internas. 2) Inyección de `env-detector.js` en los 45 archivos HTML/PHP del frontend. 3) Actualización de `login.js`, `dashboard.js`, `api-client.js` y `control_remoto_listener.js` con `obtenerRutaBaseFrontend()`.

### [FAIL-024]: Renderizado de Plantilla HTML Flotante, Banners Duplicados y Redirección a Producción por Rutas Nginx e Inyección Regex Incorrecta
- **Síntoma:** 1) En modo incógnito DEV, se mostraba una tarjeta de plantilla de correo sin renderizar (`MAS QUE FIANZAS Notificación Automática — {{TIPO_LABEL}}`) flotando en el centro del dashboard. 2) Se superponían dos barras de advertencia DEV y dos insignias simultáneamente. 3) Al escribir `169.58.51.147/dev` en el navegador normal, se enviaba al usuario a Producción.
- **Causa:** 1) La inyección automática mediante regex insertó `<script src="assets/env-detector.js"></script>` dentro de un template literal JS (`notif_plantillaPiloto()`) en `dashboard.html`, corrompiendo el parser HTML del navegador. 2) Tanto `dashboard.js` como `env-detector.js` creaban banners e insignias independientes con IDs distintos. 3) Nginx carecía de reglas de redirección 302 explícitas para `/dev` y `/dev/`.
- **Solución:** 1) Limpieza estricta de `dashboard.html` y los 45 archivos del frontend asegurando EXACTAMENTE UNA etiqueta `env-detector.js` en el `<head>` del documento. 2) Unificación centralizada del branding DEV en `env-detector.js` y eliminación de banners duplicados en `dashboard.js` y `login.js`. 3) Adición de reglas de redirección Nginx `location = /dev { return 302 /dev/frontend/; }` y `location = /dev/ { return 302 /dev/frontend/; }`.

### [FAIL-025]: Desalineación de Marcas DEV en Login, Omisión de Opciones en Select de Perfil y Fallo de Carga en Malla de Permisos
- **Síntoma:** 1) En la pantalla de login (`/dev/frontend/index.html`), la barra DEV sobresalía horizontalmente en el margen izquierdo. 2) Al editar al usuario `jtaveras` en la modal `USUARIOS`, no se guardaba el perfil `Supervisor Comercial`. 3) Al seleccionar un perfil en `Configuración -> Perfiles y Permisos`, se mostraba el error *"No se pudieron cargar los datos de permisos"*.
- **Causa:** 1) `#mqf-dev-global-bar` carecía de detección de diseño para la página de login, rompiendo los márgenes laterales. 2) La modal `<select id="usuarioPerfil">` carecía de elementos `<option>` en el HTML estático y no se poblaba dinámicamente en JS, haciendo que la asignación `.value` fallara silenciosamente. 3) La consulta de permisos en `dashboard.js` dependía estrictamente del token en `localStorage` sin fallback, retornando 401 si no existía o si la API de Python requería contingencia.
- **Solución:** 1) Se actualizó `env-detector.js` para suprimir la barra superior de margen en login e inyectar una tarjeta DEV estilizada dentro del panel "Bienvenido" (`.login-side-content`). 2) Se actualizó `abrirModalUsuario()` en `dashboard.js` para poblar dinámicamente el `<select id="usuarioPerfil">` antes de asignar el valor del perfil, y se actualizó a `jtaveras` con `perfil_id = 9` (Supervisor Comercial). 3) Se añadieron fallbacks de token (`sessionStorage`, `MQF_TOKEN`) y fallback a `/backend/api/perfiles.php` en `cargarPermisosPerfilSeleccionado()`.

---
*Fin del Catálogo KEDB — MÁS QUE FIANZAS, S.R.L. | Versión NOFTRAB v4.0*
