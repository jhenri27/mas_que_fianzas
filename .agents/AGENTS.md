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

---

## 🛡️ Norma VAF (Verificación Antes de Finalizar - NOFTRAB 1.0 Cláusula 4)

Todos los desarrollos, correcciones, modificaciones o ajustes en la plataforma **MÁS QUE FIANZAS** deberán regirse obligatoriamente por la **Norma VAF**:

1. **Nunca marques una tarea como completada sin demostrar que funciona:** Debes realizar pruebas reales sobre los flujos de la plataforma y verificar visualmente o mediante bitácoras de salida los cambios antes de darlos por concluidos.
2. **Prohibición de Aprobación Exclusiva por Script:** Las herramientas de consola, seeders o scripts de automatización no se considerarán prueba única y suficiente de validación, puesto que en el 85% de los casos evaden los hooks reales del sistema, validaciones de sesión, tokens de autorización, etc.
3. **Comparación de Comportamiento (Diff):** Cuando sea relevante, documenta la diferencia (diff) entre el comportamiento de la rama principal original y tu solución.
4. **Validación Senior:** Antes de proponer o entregar, hazte la pregunta: *¿Aprobaría esto un Staff/Senior Engineer?* Asegura que el código sea limpio, documentado, no tenga vulnerabilidades de seguridad y se apegue a la arquitectura.

