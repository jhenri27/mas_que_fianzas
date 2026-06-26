# Walkthrough - Midas Marbete Alignment, QR Positioning, and Fianza Variable Integration

We have successfully resolved the field alignment shift, corrected the QR code positioning, and added the `Fianza` variable to the Integrador UI variables list.

## Changes Made

### Frontend - Integrador UI

#### [modelador_pdf.html](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/modelador_pdf.html)
- Added `poliza.fianza` (labeled **Fianza de Ley**) into the "Datos de la Póliza / Fianza" variables pool:
  ```html
  <div class="variable-item" draggable="true" ondragstart="drag(event, 'poliza.fianza')"><i class="fa-solid fa-shield-halved"></i> Fianza de Ley</div>
  ```

### Frontend - PDF Generation Module

#### [polizas-pdf.js](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/polizas-pdf.js)
- **drawFieldDB (Patria, Pepín, and Midas blocks)**:
  - Added switch cases to support mapping variables such as `cliente.cedula`, `cliente.telefono`, `cliente.email`, `poliza.objeto_fianza`, and `poliza.fianza` / `poliza.fianza_judicial`.
  - Added fallback to `nombre_campo_pdf` if `variable` is empty to support custom text fields.
- **Baseline Offset Correction (Midas block)**:
  - Subtracted the font size (`size`) from `y_pt` in the Midas block:
    ```javascript
    const y_pt = BG_Y + BG_H - (parseFloat(campo.pos_y) / plantH_mm) * BG_H - size;
    ```
    This corrects the difference between HTML's top-left positioning and PDF's character baseline positioning.
- **Fianza Resolution in Context**:
  - Mapped `fianza: fmtDOP(poliza.fianza_judicial||50000)` inside the dynamic template context `ctx.poliza`.
- **Centered QR Scaling**:
  - Corrected QR code positioning by centering it vertically relative to the center of the mapped box (which has a height of `22px` / `6.21 mm` in the UI), preventing bottom boundary and text overlaps.

#### [polizas.html](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/polizas.html)
- Bumped the cache-busting query parameter for `polizas-pdf.js` from `v=11` to `v=12`.

---

## Verification Results

We verified the generation using a simulation Node script that fetches live mapped fields from the database and renders the output PDF to a PNG.

### Mapped Marbete Result
Below is the rendered image demonstrating perfect baseline alignment of all fields (Cédula, Póliza No., Efectivo Desde, Expira En, Chasis, Tipo, Fianza, Teléfono) and a perfectly sized, centered QR code:

![Rendered Marbete Midas](./test_midas_mapped_vaf_rendered.png)
