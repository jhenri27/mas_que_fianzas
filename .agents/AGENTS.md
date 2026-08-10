# BBS & BHN Bots Learning & Experience Manual - MAS QUE FIANZAS
This document serves as the persistent knowledge base for autonomous bots in the workspace (Bot-BHN and Bot-BBS). It contains the catalog of coded errors, hot-fixes, database schemas, and validation algorithms, aligning with the strict NOFTRAB and ISO 9001 quality guidelines.

---

## 🤖 Bot Commands Reference

### Bot-BHN (Technical Support & Diagnostics)
Bot-BHN (`bot.helpnow`) handles technical assistance, logs verification, permissions auditing, and autonomous hot-fixes.
- **`ayuda`**: Shows the command catalog.
- **`diagnostico`**: Performs database health checks, sizes, and PHP environment queries.
- **`permisos`**: Displays the active user's permissions.
- **`logs`**: Views the last 5 entries in the PHP error logs.
- **`error`**: Lists documented platform failures and their exact solutions.
- **`reparar`**: Applies automated database repairs (e.g., verifying that all system configurations are present).

### Bot-BBS (Commercial Services Bot)
Bot-BBS (`bot.ssindi`) automates queries, emissions, and corrections of commercial entities (Policies, Quotes, Fianzas).
- **`cotizar`**: Starts a quick quote flow.
- **`emitir`**: Processes a quote to a live policy.
- **`corregir`**: Applies fields updates to active records.

---

## 🇩🇴 Dominican Document Validation (NOFTRAB Standard)

To prevent falsification, duplication, and dirty data entry, all platform inputs containing Dominican documents must be validated using the following strict algorithms implemented in `/backend/lib/ValidadorDocumentos.php`:

### 1. Cédula Identidad y Electoral (11 Digits, Luhn Mod 10)
- **Algorithm:** The 11th digit is the check digit. Calculate the sum over the first 10 digits multiplied by alternate weights `[1, 2, 1, 2, 1, 2, 1, 2, 1, 2]`. If a product is $\geq 10$, sum its digits (subtract 9). The check digit is `(10 - (sum % 10)) % 10`.
- **Precaution:** Never include the 11th digit in the Luhn checksum loop itself to prevent false positives.

### 2. RNC (9 Digits, Modulo 11)
- **Algorithm:** The 9th digit is the check digit. Multiply the first 8 digits by weights `[7, 9, 8, 6, 5, 4, 3, 2]` and sum the products. Compute `residue = sum % 11`.
- **Check Digit Mapping (DGII Standard):**
  - If `residue == 0`, check digit is `2`.
  - If `residue == 1`, check digit is `1`.
  - Otherwise, check digit is `11 - residue`.
- **Precaution:** Standard modulo 11 maps residue 0 to 0. DGII mapping MUST map residue 0 to 2.

### 3. Teléfono (10 Digits)
- **Algorithm:** Must start with valid Dominican area codes: `809`, `829`, or `849`.

### 4. Pasaporte
- **Algorithm:** 2 letters (A-Z) followed by 7 digits (`/^[a-zA-Z]{2}\d{7}$/`).

### 5. Licencia de Conducir
- **Algorithm:** 1 letter (A-Z) followed by 8 digits (`/^[a-zA-Z]{1}\d{8}$/`).

---

## 📋 Catalog of Coded Failures & Solutions

### [FAIL-001]: Logo de Aseguradora Oculto en el Cotizador
- **Symptom:** The Quote card is hidden, and jsPDF downloads crash.
- **Cause:** Base64 logo data contains literal escape sequences of backslash and 'n' (`\n`) rather than real newline characters. The Qwen QA clean regex `/\n/g` failed to replace them, leaving invalid strings that broke rendering.
- **Solution:** Keep `/\\n/g` as the clean base64 replacement in `cotizaciones.html`:
  `return str.replace(/[\r\n\s]+/g, '').replace(/\\n/g, '').replace(/"+/g, '');`

### [FAIL-002]: Inserción de Nuevas Secuencias NCF (B12 y B15)
- **Symptom:** Financial Center failed to issue sequences of type B12 and B15 due to missing DB registry.
- **Solution:** Run SQL insertion in the `cf_ncf_secuencias` table:
  `INSERT INTO cf_ncf_secuencias (tipo, prefijo, secuencia_actual, secuencia_final, vencimiento, activa) VALUES ('B12', 'B12', 0, 100000, '2026-12-31', 1), ('B15', 'B15', 0, 100000, '2026-12-31', 1) ON DUPLICATE KEY UPDATE tipo=tipo;`

### [FAIL-003]: Permisos de Administrador (Bypass de Carga de Permisos)
- **Symptom:** Admin users (ID 1) couldn't view specific configurations or populate the global permissions array (`window.MQF_PERMISOS`), preventing iframe elements from displaying correctly.
- **Cause:** `dashboard.js` had an early return for profile 1, which bypassed the fetch call to `/backend/api/perfiles.php`.
- **Solution:** Removed `return;` inside the Admin bypass block in `dashboard.js` to ensure the dynamic menu permissions load asynchronously.

### [FAIL-004]: Desplazamiento y Ocultamiento de la Barra de Navegación Inferior en PWA Móvil
- **Symptom:** On mobile/Android viewports, bottom navigation icons were cut off at the bottom and required vertical scrolling.
- **Cause:** PWA navbar lacked viewport-fixed CSS positioning and safe-area z-index controls.
- **Solution:** Applied `position: fixed !important; bottom: 0 !important; z-index: 99999 !important;` to `.pwa-navbar` in `frontend/pwa/css/pwa-style.css` and added cache-busting `?v=2.0.0` in `frontend/pwa/index.html`.

### [FAIL-005]: Formato PDF de Cotización de Vehículos y Distorsión de Logo
- **Symptom:** Vehicle quote printing returned blank PDF documents, downloaded PDFs crashed or distorted the high-res logo over sub-header text.
- **Cause:** Missing beneficiary variable `benef` causing script exceptions in `jsPDF`, unproportional logo width/height constraints, and un-offset dynamic Y-positions for header labels.
- **Solution:** Updated `dibujarCotizacionPDF` in `frontend/assets/data-export.js` and `frontend/pwa/js/views/pdv.js` using model `SL-TEMP-5839.pdf`, adjusted logo scaling ratio (22x19.8mm) and dynamic Y-spacing (`y = yHead + 24mm`).

### [FAIL-006]: Valor de Referencia Estático "ASDE-CCC-CP-2026-0008" en Cotizaciones PDF
- **Symptom:** All exported vehicle quote PDFs rendered identical hardcoded reference text `ASDE-CCC-CP-2026-0008`.
- **Cause:** Variable `refContrato` was hardcoded as a static string literal in `frontend/assets/data-export.js` and `frontend/modulos/fianzas.html`.
- **Solution:** Replaced static literal with dynamic reference evaluation generated from quote ID and timestamp.

### [FAIL-007]: Código QR de Validación con Ruta Inválida / Error 404
- **Symptom:** Scanning QR code embedded in quotation PDFs failed to load verification details.
- **Cause:** QR generator target pointed to non-existent internal path structures without dedicated public validation endpoints.
- **Solution:** Created `/frontend/modulos/validar.html` with shield animation and NOFTRAB v4.0 audit seal, added Nginx location rewrite `/validar`, and updated dynamic QR URL target in `frontend/assets/data-export.js`.

### [FAIL-008]: Disparidad de Token de Sesión vs Cookie PHP en Métricas PWA
- **Symptom:** Logged-in PDV user (`pdv.prueba`) saw `RD$ 0.00` in commissions and `0` in quotes on the PWA dashboard despite having 22 active quotes in MySQL.
- **Cause:** Backend APIs (`cotizaciones.php` and `comisiones_panel.php`) prioritized `$_SESSION['usuario_id']` over `Authorization: Bearer <token>` / `token_sesion`, defaulting to unauthenticated state when PHP cookie was absent.
- **Solution:** Modified backend session validation order in `cotizaciones.php` and `comisiones_panel.php` to prioritize Bearer token from `localStorage` over PHP session cookies, and added contingency fallback querying.

### [FAIL-009]: Error Nginx 405 Not Allowed en Endpoints REST sin Extensión `.php` (`/backend/api/auth/login`)
- **Symptom:** Logging into the web platform displayed red error "Error de conexión con el servidor".
- **Cause:** Nginx evaluated extensionless REST API paths like `/backend/api/auth/login` as physical directory requests (due to folder `/backend/api/auth/` existing on disk), returning `405 Not Allowed` on POST requests.
- **Solution:** Configured direct FastCGI handler block in Nginx `/etc/nginx/sites-available/default`: `location ~ ^/(PLATAFORMA_INTEGRADA/)?backend/api/auth/(.*)$` passing `SCRIPT_FILENAME` to `auth.php` with `PATH_INFO`.

### [FAIL-010]: Error Nginx 403 Forbidden en la Ruta Raíz (`http://169.58.51.147/`)
- **Symptom:** Accessing direct server IP `http://169.58.51.147/` displayed `403 Forbidden`.
- **Cause:** Root directory `/var/www/plataforma_integrada` lacked an `index.html` file at the root level (frontend files are located in `/frontend/`), triggering Nginx directory listing restriction.
- **Solution:** Added Nginx location rule `location = / { return 302 /PLATAFORMA_INTEGRADA/frontend/; }` to automatically redirect root traffic to the web platform frontend.

### [FAIL-011]: ReferenceError: esPrimerReq is not defined en PDF y Campo Faltante 'A Primer Requerimiento' en Fianzas
- **Symptom:** Clicking "PDF" on fianza quotations (`F-2026-3218`) threw `Error al generar PDF: esPrimerReq is not defined`, and editing a fianza quote did not show the "¿Es a Primer Requerimiento?" field.
- **Cause:** Variable `esPrimerReq` was not declared in `exportFianzaCotizacionPDF` (`data-export.js`) or `generarPDFFianza` (`fianzas.html`). `cotizaciones` table lacked `primer_requerimiento` column, and the edition UI in `cotizaciones.html` lacked the selector.
- **Solution:** Executed `ALTER TABLE cotizaciones ADD primer_requerimiento TINYINT(1) DEFAULT 0`, declared `esPrimerReq` in PDF generators, added `<select id="f_primer_requerimiento">` in `cotizaciones.html`, and updated backend REST API `cotizaciones.php` (`INSERT` and `UPDATE`).

### [FAIL-012]: Incongruencia de Etiqueta Fiscal de Impuestos en Cotizaciones Digitales (ITBIS vs ISC 16% Ley 146-02)
- **Symptom:** Digital quote PDFs and HTML header displayed `IMPUESTO (ITBIS/Otros)` instead of referencing the insurance tax law (ISC 16% / Ley 146-02).
- **Cause:** Hardcoded HTML string `IMPUESTO (ITBIS/Otros)` in `renderPremiumQuoteHTML` (`backend/api/chat.php`).
- **Solution:** Updated string to `IMPUESTO (16% Ley 146-02)` in `backend/api/chat.php` line 517.

### [FAIL-013]: Renderizado de Iconos SVG Genéricos por Disparidad de Logos de Aseguradoras
- **Symptom:** Digital quote headers for Multiseguros, Midas, Patria, Pepín, etc. rendered generic SVG shapes (circle, crown, triangle) instead of company logos.
- **Cause:** Base64 `.txt` filenames had mismatched names (`midas_seguros.png.txt` vs `midas.png.txt`) or lacked assets fallback path in `renderPremiumQuoteHTML`.
- **Solution:** Processed high-resolution logos from local drive `F:\LOGOS PARA ARTE DE MAS QUE FIANZAS`, created PNG/JPG files in `frontend/assets/logos/aseguradoras/`, base64 Data-URIs in `uploads/logos/`, updated `fianza_aseguradoras` table, and updated `chat.php`.

### [FAIL-014]: Formato PDF de Fianza Tipo Cotización Simple vs Cuerpo Oficial de Póliza Legal con 2 Modos y QR
- **Symptom:** Printing an active fianza (`FZ-2026-00001`) outputted a single-page informal quote layout instead of the official policy legal contract (Condiciones Particulares) with 8 clauses, QR validation code, and watermark control.
- **Cause:** Functions `generarPDFFianza` in `data-export.js` and `fianzas.html` generated a basic quote design instead of the policy legal clauses.
- **Solution:** Redesigned `generarPDFFianza` to produce the official policy legal document (`PÓLIZA DE FIANZAS COMERCIALES - CONDICIONES PARTICULARES`), 8 legal clauses ("POR CUANTO 1..8"), live QR validation code, and dual-mode watermark: Mode 1 (Unpaid/Draft/Cotización -> diagonal watermark `Borrador Sin Valor Comercial`); Mode 2 (Paid/Vigente -> clean print without watermark).

### [FAIL-015]: Ausencia de Código QR en PDF de Cotizaciones por Fallo de Fetch CORS y Omisión de Cache-Busting
- **Symptom:** Printing quote `2026-00003` or fianza `FZ-2026-00001` yielded PDFs without QR codes or stuck on outdated cached script logic.
- **Cause:** `generarQRDataURL` used an un-proxied external `fetch()` call to `api.qrserver.com` blocked by CORS/network restrictions, returning `null`. Subtypes like `Licitación` bypassed fianza detection in `pdv.js` / `dibujarCotizacionPDF`. `fianzas.html` lacked script imports for `data-export.js` and `qrcode.min.js`.
- **Solution:** Rewrote `generarQRDataURL` with 3-tier local canvas + QRCode.js fallback, broadened `isFianza` subtype detector, and injected `<script src="../assets/lib/qrcode.min.js">` and `<script src="../assets/data-export.js?v=20260808_v5">` across HTML modules.

### [FAIL-016]: Bloqueo de Cálculo y Sombrado de Nombre en Motor de PDF en Módulo de Fianzas
- **Symptom:** When editing a fianza quotation, clicking "Calcular Prima" displayed warning `⚠️ Ingrese el valor a afianzar` even though Monto del Contrato had a value and Valor a Afianzar input was disabled. Clicking the PDF button in Mis Cotizaciones or Mis Fianzas did nothing.
- **Cause:** 1) `getValorAfianzado()` in `fianzas.html` depended strictly on `% a Afianzar` when `modo === 'A'`. If `% a Afianzar` was empty and `wiz-valor-calculado` was disabled, calculation evaluated to 0. 2) Local function `generarPDFFianza(data)` in `fianzas.html` shadowed global `window.generarPDFFianza(data)` from `data-export.js`, causing `window.generarPDFFianza !== generarPDFFianza` to evaluate to `false` and returning `undefined` silently.
- **Solution:** 1) Enabled direct input and dynamic fallback in `getValorAfianzado()` reading `% a Afianzar`, direct value input, or contrato amount. 2) Renamed local wrapper in `fianzas.html` to `invocarMotorPDFFianza` and exported `window.MQF_generarPDFFianza` in `data-export.js`.

### [FAIL-017]: Presentación de Etiquetas y Campos de Vehículos en Validación QR de Fianzas Comerciales
- **Symptom:** Scanning the QR code of a Fianza policy (`FZ-2026-00001`) rendered vehicle-specific section titles (`DATOS DEL VEHÍCULO`), vehicle labels (*Marca / Modelo*, *Año / Color*, *Placa*, *Matrícula*, *No. Chasis (VIN)*), and vehicle alert text (*"circulación nacional"*), instead of Fianza specifications (*Tipo de Fianza*, *Beneficiario*, *Monto Afianzado*, *Objeto / Referencia*).
- **Cause:** 1) `verificar_poliza.php` queried `polizas` joined with `vehiculos` without checking if the target document was a Fianza (`fianzas` table), returning null vehicle fields. 2) `verificar-poliza.html` possessed narrow detection logic for `isFianza` that evaluated to `false` when response structure used `resData.datos` or `resData.data.ramo`, falling back to static vehicle labels and vehicle alert text.
- **Solution:** 1) Updated `verificar_poliza.php` and `checkout_process.php` to query `fianzas` and `cotizaciones` tables for `FZ-` policies, returning explicit Fianza payload structures (`tipo_fianza`, `beneficiario`, `monto_afianzado_fmt`, `objeto_referencia`). 2) Updated `verificar-poliza.html` and `validar.html` to evaluate broad Fianza indicators across all data structures, dynamically switching section headers to *"Especificaciones de la Fianza Comercial"*, hiding vehicle rows, and rendering Fianza audit alert text.

### [FAIL-018]: Impresión Indebida de Borrador de Póliza en Botón PDF de Cotización de Fianzas y Falta de Botón de Borrador Independiente
- **Symptom:** Clicking the blue "PDF" button in Mis Cotizaciones rendered a policy draft body (Condiciones Particulares with 8 clauses and watermark) instead of the clean Quote Summary (Cotización Resumen) format. Also, there was no separate button to print a Policy Draft when explicitly desired.
- **Cause:** Table row renderer `renderTablaCotizaciones` in `frontend/modulos/fianzas.html` called `imprimirFianzaPDF()` (which invokes the policy contract body engine) instead of `imprimirCotizacionFzPDF()` (which invokes the Cotización Resumen engine).
- **Solution:** Updated `renderTablaCotizaciones` in `fianzas.html` to map the blue `PDF` button to `imprimirCotizacionFzPDF(f.id, origen)` (Cotización Resumen layout with logo, breakdown, notes, contact, and QR code) and added a separate purple `Borrador` button mapping to `imprimirBorradorFzPDF(f.id, origen)` (Policy draft contract layout with watermark and QR code).

### [FAIL-019]: Superposición Estética de Logo de Aseguradora en Encabezado de Cotización Resumen PDF
- **Symptom:** Generating the Quote Summary (Cotización Resumen) rendered the selected insurer's logo (*MultiSeguros*) stretched horizontally and overlapping into the blue header rectangle. Furthermore, as a quote proposal issued by the brokerage, it lacked the official MÁS QUE FIANZAS (+QF) logo in the top-left header.
- **Cause:** `dibujarCotizacionFianzaSimplePDF` in `frontend/assets/data-export.js` loaded `obtenerLogoAseguradoraB64(asegName)` drawing a 28mm wide image that collided with the blue rectangle starting at `ML + 26`.
- **Solution:** Updated `dibujarCotizacionFianzaSimplePDF` to load `obtenerLogoMQFB64()` (official +QUE FIANZAS logo) scaled proportionally `(ML, 7, 24, 20)`, perfectly aligned with the blue brokerage header bar, keeping the selected insurer in the table section (`Aseguradora | Precio / Prima Total`) and the validation QR code in the bottom-right corner.

### [FAIL-020]: ReferenceError: obtenerLogoMQFB64 is not defined al Generar PDF de Cotización Resumen de Fianzas
- **Symptom:** Clicking the blue "PDF" button in Mis Cotizaciones de Fianzas threw red toast error `Error al generar PDF: obtenerLogoMQFB64 is not defined` and failed to download the PDF document.
- **Cause:** 1) `obtenerLogoMQFB64` was called directly without global scope verification on `window` object or fallback handling. 2) Browser caching retained outdated script imports (`data-export.js?v=20260808_v5`).
- **Solution:** 1) Declared `window.obtenerLogoMQFB64` globally in `logo_b64.js` and `data-export.js`. 2) Added safe evaluation `typeof window.obtenerLogoMQFB64 === 'function'` with direct fallback to `window.LOGO_MQF_B64`. 3) Bumped script cache-busting versions to `?v=20260810_v8` across HTML modules (`fianzas.html`, `cotizaciones.html`, `pwa/index.html`).

### [FAIL-021]: Desalineación y Espaciado Vertical Insuficiente en Filas de Datos de Tipo de Fianza y Aseguradora en Cotización Resumen PDF
- **Symptom:** In the Quote Summary PDF (Cotización Resumen), data rows under "Tipo de Fianza" and "Aseguradora" rendered squished directly against the bottom edge of the grey section header bars.
- **Cause:** `dibujarCotizacionFianzaSimplePDF` in `frontend/assets/data-export.js` used an insufficient vertical Y increment (`y += 8`) after drawing the 6.0mm grey rectangle, placing the data text baseline only 2mm below the rectangle bottom edge.
- **Solution:** Adjusted grey rectangle height to 5.5mm, increased pre-text vertical offset to `y += 10.5` (providing 3.0mm clear margin between grey bar and top of text), and adjusted font size to 8.5pt, aligning perfectly with Image 2 design.

### [FAIL-022]: Fallo de Estilos CSS/JS y Error de Autenticación por Desconfiguración Nginx y config.local.php Ausente en Ambiente Desarrollo (DEV)
- **Symptom:** Accessing DEV environment rendered unstyled HTML without CSS/JS styles (mismatched MIME type `text/html`), and logins failed across all credentials.
- **Cause:** 1) Missing `/var/www/dev_plataforma/backend/config.local.php` file, causing MySQL connection fallback to `root@localhost` with empty password. 2) Nginx lacked explicit `/dev/` and `/PLATAFORMA_INTEGRADA/dev/` alias location blocks with `$uri $uri/` fallbacks, serving `index.html` for CSS/JS requests. 3) Nginx lacked REST API endpoint route for `/backend/api/auth/login` in DEV.
- **Solution:** 1) Created `/var/www/dev_plataforma/backend/config.local.php` with `masque_user` credentials. 2) Configured Nginx location blocks for `/dev/`, `/dev_plataforma/`, and `/PLATAFORMA_INTEGRADA/dev/` with FastCGI PHP execution and exact MIME type asset delivery. 3) Added DEV REST API location block for `/backend/api/auth/(.*)$` passing `PATH_INFO`. 4) Updated password hashes for `admin`, `jtaveras`, and `pdv.prueba`.

### [FAIL-023]: Pérdida de Contexto DEV y Redirección Indebida al Dashboard de Producción
- **Symptom:** Logging in from DEV (`/dev/frontend/`) or navigating between modules redirected the user to `http://169.58.51.147/frontend/dashboard.html` (Production), losing the `[DEV]` environment context.
- **Cause:** 1) `login.js` and module navigation listeners contained hardcoded prefix logic (`const basePrefix = isSubdir ? '/PLATAFORMA_INTEGRADA' : '';`) that omitted `/dev/` when evaluating `/dev/frontend/` pathnames. 2) Modules lacked a global environment detector script to enforce `[DEV]` branding in browser titles, headers, and top banners.
- **Solution:** 1) Implemented `frontend/assets/env-detector.js` to automatically detect DEV environments, prepend `[DEV]` to `document.title`, inject a top warning bar, append `DEV` badges to sidebar logos and header titles, and rewrite internal redirects. 2) Injected `env-detector.js` across all 45 HTML/PHP pages. 3) Updated `login.js`, `dashboard.js`, `api-client.js`, and `control_remoto_listener.js` with `obtenerRutaBaseFrontend()`.

### [FAIL-024]: Renderizado de Plantilla HTML Flotante, Banners Duplicados y Redirección a Producción por Rutas Nginx e Inyección Regex Incorrecta
- **Symptom:** 1) In DEV Incognito mode, an unrendered email template card (`MAS QUE FIANZAS Notificación Automática — {{TIPO_LABEL}}`) was displayed floating in the middle of the dashboard. 2) Two overlapping DEV banners and badges were rendered simultaneously. 3) Accessing `169.58.51.147/dev` in normal browser mode redirected to Production (`/frontend/dashboard.html`).
- **Cause:** 1) Automated regex injection inserted `<script src="assets/env-detector.js"></script>` into an inline JS HTML template literal (`notif_plantillaPiloto()`) inside `dashboard.html`, breaking HTML parsing and dumping raw HTML into the DOM body. 2) Both `dashboard.js` and `env-detector.js` created independent top banners and badges with different element IDs. 3) Nginx lacked explicit 302 redirect rules for `/dev` and `/dev/`, returning 403 or falling through to Production `location /`.
- **Solution:** 1) Cleaned `dashboard.html` and all 45 frontend pages so `env-detector.js` is imported EXACTLY ONCE in document `<head>`. 2) Unified DEV branding centrally in `env-detector.js`, removing duplicate banner creation in `dashboard.js` and `login.js`. 3) Added explicit Nginx location redirect rules `location = /dev { return 302 /dev/frontend/; }` and `location = /dev/ { return 302 /dev/frontend/; }`.

### [FAIL-025]: Desalineación de Marcas DEV en Login, Omisión de Opciones en Select de Perfil y Fallo de Carga en Malla de Permisos
- **Symptom:** 1) On the login screen (`/dev/frontend/index.html`), the DEV banner broke out horizontally into the left margin. 2) Editing user `jtaveras` in `USUARIOS` modal did not update profile to `Supervisor Comercial`. 3) Selecting a profile in `Configuración -> Perfiles y Permisos` threw red error *"No se pudieron cargar los datos de permisos"*.
- **Cause:** 1) `#mqf-dev-global-bar` lacked login page layout detection, breaking page margins. 2) Modal `<select id="usuarioPerfil">` lacked static `<option>` elements in HTML and was not populated dynamically in JS, causing `value` assignment to fail silently. 3) Permissions fetch in `dashboard.js` relied on `localStorage` token without fallback, returning 401 when token was missing or fallback API was needed.
- **Solution:** 1) Updated `env-detector.js` to suppress top margin bar on login pages and insert a sleek DEV card inside the `.login-side-content` ("Bienvenido") hero panel. 2) Updated `abrirModalUsuario()` in `dashboard.js` to dynamically populate `<select id="usuarioPerfil">` with options before setting `.value = u.perfil_id` and updated `jtaveras` to `perfil_id = 9` (Supervisor Comercial). 3) Added token fallbacks (`sessionStorage`, `MQF_TOKEN`) and graceful fallback to `/backend/api/perfiles.php` in `cargarPermisosPerfilSeleccionado()`.

---

## 🛡️ Norma VAF y Regla de Actualización Obligatoria KEDB (NOFTRAB v4.0 Cláusula 4 y 5)

Todos los desarrollos, correcciones, modificaciones o ajustes en la plataforma **MÁS QUE FIANZAS** deberán regirse obligatoriamente por la **Norma VAF** y la **Regla de Actualización KEDB**:

### 🎯 Principios Fundamentales (Core Principles)

1. **Evita el harcodear:** En cualquier escenario que se presente evita harcodear utilizando todos los medios posibles para mantener las interconexiones entre funciones y módulos.
2. **Nunca marques una tarea como completada sin demostrar que funciona:** Debes realizar pruebas reales sobre los flujos de la plataforma y verificar visualmente o mediante bitácoras de salida los cambios antes de darlos por concluidos.
3. **Prohibición de Aprobación Exclusiva por Script:** Las herramientas de consola, seeders o scripts de automatización no se considerarán prueba única y suficiente de validación, puesto que en el 85% de los casos evaden los hooks reales del sistema, validaciones de sesión, tokens de autorización, etc.
4. **Comparación de Comportamiento (Diff):** Cuando sea relevante, documenta la diferencia (diff) entre el comportamiento de la rama principal original y tu solución.
5. **Validación Senior:** Antes de proponer o entregar, hazte la pregunta: *¿Aprobaría esto un Staff/Senior Engineer?* Asegura que el código sea limpio, documentado, no tenga vulnerabilidades de seguridad y se apegue a la arquitectura.
6. **Regla de Actualización Obligatoria KEDB:** Todo error o falla solucionada debe revisarse contra el Catálogo KEDB (`docs/catalogo_errores_noftrab.html` y `.agents/AGENTS.md`). De no existir registrado:
   - **Opción 1:** Se incrustará el nuevo tipo de error (`FAIL-XXX`) dentro de una categoría existente si corresponde a su taxonomía.
   - **Opción 2:** Se creará una nueva categoría/sección si la falla aborda una nueva dimensión técnica o funcional.

