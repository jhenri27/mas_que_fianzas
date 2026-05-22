# LECCIONES APRENDIDAS — MAS QUE FIANZAS PLATAFORMA
> Archivo NOFTRAB: actualizar después de CADA corrección del usuario.
> Revisar al inicio de cada sesión para NO repetir errores.

---

## ERRORES CRÍTICOS RESUELTOS

### [L-001] Cache de navegador en Edge bloquea módulos actualizados
- **Patrón detectado:** Edge (modo normal) cargaba versiones viejas de archivos .html/.js del iframe, mostrando historial vacío aunque la BD tenía datos.
- **Causa raíz:** Los archivos HTML estáticos no tenían cabeceras HTTP no-cache. Edge los almacenaba en disco.
- **Solución elegante aplicada:**  
  1. `.htaccess` en `/frontend/` con `Cache-Control: no-store, no-cache, must-revalidate`.
  2. `?t=Date.now()` dinámico en el `src` del iframe (no `?v=3` estático, que Edge también cachea).
- **Regla para mí mismo:** Cualquier iframe cuyo contenido cambie DEBE tener timestamp dinámico en su src. NUNCA usar versión estática como cache-buster para iframes en Edge.

### [L-002] Rutas relativas dentro de iframes fallan en Edge pero no en Chrome
- **Patrón detectado:** `fetch('../../../backend/api/')` funciona en Chrome pero da 404 en Edge dentro de un iframe.
- **Causa raíz:** Edge resuelve rutas relativas desde la URL del iframe, que varía según el contexto de carga.
- **Solución elegante aplicada:** Siempre usar rutas absolutas `/PLATAFORMA_INTEGRADA/backend/api/` en fetches dentro de iframes.
- **Regla para mí mismo:** En esta plataforma, TODOS los fetch() dentro de módulos que se cargan como iframes deben usar rutas absolutas desde la raíz del servidor.

### [L-003] Error "Unknown column X in field list" en MySQL
- **Patrón detectado:** La tabla `cotizaciones` fue creada antes con schema diferente (columnas como `numero_cotizacion` en vez de `numero`). Mi API asumía columnas que no existían.
- **Causa raíz:** El script de auto-creación de la tabla no corría porque la tabla ya existía con schema anterior.
- **Solución aplicada:**  
  1. `fix_cotizaciones_v2.php`: añadir columnas faltantes.
  2. `fix_cotizaciones_v3.php`: hacer nullable las columnas NOT NULL sin default.
- **Regla para mí mismo:** Antes de crear API que hace INSERT, verificar SIEMPRE el schema real con `DESCRIBE tabla`. No asumir el schema del código.

### [L-004] Logo en PDFs no se actualiza aunque se sobreescribe el archivo
- **Patrón detectado:** `logo_b64.js` actualizado pero PDFs siguen mostrando logo viejo.
- **Causa raíz:** El archivo JS estaba cacheado con la versión anterior (v=2, v=3 en el script tag).
- **Solución aplicada:** Incrementar la versión del query param (`?v=4`) en los script tags de cotizaciones.html y clientes.html.
- **Regla para mí mismo:** Al actualizar logo_b64.js, siempre incrementar la versión del `<script src="...?v=N">` en TODOS los módulos que lo usan.

### [L-005] ICO no es PNG — GD no puede procesar iconos ICO directamente
- **Patrón detectado:** `Logo_Mas_qu_fianzas_fondo-removebg-preview-2.ico` copiado como PNG no se renderizaba correctamente en jsPDF.
- **Causa raíz:** Los archivos .ico tienen formato binario diferente al PNG. GD/PHP no los procesa igual.
- **Solución elegante aplicada:** Usar el archivo `.png` equivalente (`Logo_Mas_qu_fianzas_fondo-removebg-preview-2.png`) con `imagecreatefromstring()` + `imagesavealpha()` para transparencia.
- **Regla para mí mismo:** Siempre confirmar la extensión REAL del archivo antes de procesarlo. Hay un PNG y un ICO con el mismo nombre base en `/Iconos/`. Usar el `.png` para PDFs, `.ico` para favicon.

### [L-006] Contraseña de usuario creada con hash directo no tiene password_temporal
- **Patrón detectado:** `pdv.prueba` fue creado con INSERT directo con hash bcrypt. No tiene contraseña recuperable.
- **Causa raíz:** El script `insert-test-user.php` usó hash hardcodeado sin guardar el valor en `password_temporal`.
- **Solución aplicada:** Script `info_usuario.php` que resetea la contraseña a `PDV@2024`.
- **Regla para mí mismo:** Documentar credenciales de usuarios de prueba. Siempre usar `UsuarioManager::crearUsuario()` para que guarde `password_temporal`. Credenciales de prueba actuales: `admin/Demo@123`, `pdv.prueba/PDV@2024`.

---

## PATRONES DE ARQUITECTURA PROBADOS

### [A-001] Patrón de persistencia: Dual-Save (localStorage + MySQL)
- Guardar en localStorage (inmediato, offline) + fetch a API MySQL (persistente, cross-browser).
- El historial prioriza MySQL. localStorage es fallback.
- Migración de localStorage a MySQL: `frontend/migrate.html`.

### [A-002] Cache-busting estándar del proyecto
- HTML en iframes: `?t=Date.now()` dinámico.
- JS/CSS: `?v=N` con N incrementado manualmente.
- `.htaccess` con `Cache-Control: no-store` para todos los .html y .js.

### [A-003] API endpoints con ?action=
- Todos los endpoints del proyecto usan `?action=listar|guardar|importar|editar|eliminar`.
- Esto es compatible con WAMP/Apache que no soporta mod_rewrite de rutas REST limpias.

---

## CONOCIMIENTO DEL DOMINIO: SEGUROS RD

### Regulación
- **SINASEH:** Sistema Nacional de Seguros de la República Dominicana.
- **SENASA:** Para seguros de salud.
- **Ley 146-02:** Ley General de Seguros sobre Fianzas de la RD.
- **SIV (Superintendencia de Seguros):** Ente regulador. Toda aseguradora debe estar autorizada.

### Tipos de Pólizas RD más comunes
1. **Fianzas** (Fidelidad, Contrato, Licencias)
2. **Seguro de Ley (SOAT equivalente):** Seguro obligatorio de vehículos.
3. **Vehículos:** Todo riesgo, colisión, robo, RC.
4. **Incendio y Líneas Aliadas**
5. **Responsabilidad Civil**
6. **Vida Individual/Colectivo**
7. **Accidentes Personales**
8. **Salud Colectivo**

### Cálculo de prima típico (Vehículos RD)
- Prima Base = Valor Asegurado × Tasa (%)
- Impuesto ITBIS = 18% sobre la prima
- Recargo por pago fraccionado (mensual +15%, trimestral +10%)
- SRL: Aseguradoras locales autorizadas (BHD Seguros, ARS Humano, Reservas, Universal, etc.)
