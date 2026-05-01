/**
 * Motor de Generación de Documentos de Pólizas
 * MAS QUE FIANZAS - Core Asegurador v3.0
 * 
 * Requiere: jsPDF, jsPDF-AutoTable, qrcode.js
 * Documentos: Marbete, Solicitud, Recibo, Factura
 */

// ==========================================
// UTILIDADES COMUNES
// ==========================================
const POLIZA_DOCS = {
    EMPRESA: {
        nombre: 'MAS QUE FIANZAS, S.R.L.',
        rnc: '131-12345-6',
        telefono: '(829) 629-1952',
        email: 'info@masquefianzas.com',
        direccion: 'Ave. 27 de Febrero #234, Suite-304, La Esperilla, Santo Domingo, RD',
        base_url: window.location.origin + window.location.pathname.replace(/\/frontend\/.*/, '')
    },
    COLORES: {
        navy: [0, 51, 102],
        azul: [0, 71, 160],
        dorado: [212, 175, 55],
        blanco: [255, 255, 255],
        gris: [100, 116, 139],
        verde: [22, 163, 74],
        rojo: [220, 38, 38]
    }
};

const fmtDOP = (n) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(n || 0);
const fmtFecha = (f) => f ? new Date(f).toLocaleDateString('es-DO') : 'N/A';

/** Genera QR como dataURL usando api.qrserver.com (no requiere librería) */
async function generarQRDataURL(texto) {
    try {
        const url = 'https://api.qrserver.com/v1/create-qr-code/?size=130x130&format=png&data=' + encodeURIComponent(texto);
        const res = await fetch(url);
        if (!res.ok) throw new Error('QR API error: ' + res.status);
        const blob = await res.blob();
        return await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    } catch(e) {
        console.warn('QR fetch error:', e.message);
        // Fallback: usar qrcodejs si está disponible
        if (typeof QRCode !== 'undefined') {
            return new Promise((resolve) => {
                const div = document.createElement('div');
                div.style.cssText = 'position:fixed;left:-9999px;top:-9999px;';
                document.body.appendChild(div);
                try {
                    new QRCode(div, { text: texto, width: 128, height: 128 });
                    setTimeout(() => {
                        const c = div.querySelector('canvas');
                        resolve(c ? c.toDataURL('image/png') : null);
                        document.body.removeChild(div);
                    }, 200);
                } catch(e2) { document.body.removeChild(div); resolve(null); }
            });
        }
        return null;
    }
}

/**
 * generarMarbetePDF — Marbete Provisional Multiseguros
 * Formato: A4 Portrait (210×297mm) — igual al original RD-0004
 */
async function generarMarbetePDF(poliza, vehiculo, opts = {}) {
    try {
        // Intercepción dinámica (plantilla subida en Modelador)
        const asegNombre = (poliza.aseguradora || '').toUpperCase().trim();
        if (asegNombre) {
            try {
                const rP = await fetch('/PLATAFORMA_INTEGRADA/backend/api/pdf_plantillas.php');
                const jP = await rP.json();
                if (jP.exito && jP.data) {
                    const ref = jP.data.find(p => p.aseguradora_nombre && p.aseguradora_nombre.toUpperCase().trim() === asegNombre);
                    if (ref) {
                        const rF = await fetch(`/PLATAFORMA_INTEGRADA/backend/api/pdf_plantillas.php?id=${ref.id}`);
                        const jF = await rF.json();
                        if (jF.exito && jF.data) {
                            const ctx = {
                                poliza: { numero_poliza: poliza.numero_poliza||'', fecha_emision: fmtFecha(poliza.fecha_emision), fecha_vencimiento: fmtFecha(poliza.fecha_vencimiento), fianza_judicial: fmtDOP(poliza.fianza_judicial||50000), casa_contratada: poliza.casa_contratada||'CENTRO DEL AUTOMOVILISTA', asistencia_vial: poliza.asistencia_vial||'PREMIUM', deduccion: poliza.deduccion||'N/A' },
                                vehiculo: { marca: vehiculo?.marca||'', anio: vehiculo?.anio||'', chasis: vehiculo?.chasis||'', placa: vehiculo?.placa||'', uso: vehiculo?.uso||'PRIVADO', tipo_vehiculo: (vehiculo?.tipo_vehiculo||'AUTOMOVIL').toUpperCase() },
                                cliente: { nombre: poliza.cliente_nombre||'', cedula: poliza.cliente_cedula||'' },
                                general: { hora_emision: new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}) }
                            };
                            return await generarDocumentoDinamicoPDF(jF.data, ctx);
                        }
                    }
                }
            } catch(e) { console.warn('[Marbete] Fallback jsPDF:', e.message); }
        }

        // ── FALLBACK jsPDF ────────────────────────────────────────────────
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

        // ── LAYOUT (medidas del original RD-0004) ─────────────────────────
        const MX   = 22;          // margen izq de la caja
        const MY   = 14;          // margen sup de la caja
        const BW   = 166;         // ancho de la caja (MX+BW = 188mm)
        const BH   = 106;         // alto de la caja
        const BX2  = MX + BW;     // borde derecho = 188mm
        const XDIV = MX + 80;     // divisor vertical = 102mm (≈48% del ancho)

        // helper de fuente
        const T = (sz, st='normal', r=0,g=0,b=0) => {
            doc.setFontSize(sz); doc.setFont('helvetica', st); doc.setTextColor(r,g,b);
        };

        // ── CAJA (borde punteado) ─────────────────────────────────────────
        doc.setDrawColor(100); doc.setLineWidth(0.3);
        doc.setLineDash([1.5, 1], 0);
        doc.rect(MX, MY, BW, BH);
        doc.setLineDash([], 0);

        // ── HEADER ───────────────────────────────────────────────────────
        // Zona A: título (izquierda)
        T(9, 'bold');
        doc.text('MARBETE SEGURO', MX+2, MY+7);
        doc.text('AUTOMOVIL', MX+2, MY+13);

        // Zona B: Logo (centro)
        const LX=MX+38, LW=42, LH=14;
        const logoB64 = (window.LOGOS && window.LOGOS[asegNombre]) || (asegNombre === 'MULTISEGUROS' ? window.LOGO_MULTISEGUROS_B64 : null);
        
        console.log(`[Marbete] Logo for ${asegNombre}:`, logoB64 ? 'Found (Length: ' + logoB64.length + ')' : 'Not Found');

        if (logoB64) {
            try {
                doc.addImage(logoB64,'PNG',LX,MY+2,LW,LH);
            } catch(e) {
                console.warn('Error adding logo image:', e);
                T(14,'bold',0,51,153); doc.text(asegNombre || 'Aseguradora',LX+LW/2,MY+9,{align:'center'});
            }
        } else {
            T(14,'bold',0,51,153); doc.text(asegNombre || 'Aseguradora',LX+LW/2,MY+9,{align:'center'});
            if(asegNombre === 'MULTISEGUROS') {
                T(5.5,'italic',0,51,153); doc.text('Somos Su Alternativa',LX+LW/2,MY+14,{align:'center'});
            }
        }

        // Zona C: texto asistencia (derecha, alineado al borde)
        T(5,'bold');
        doc.text('EN CASO DE ACCIDENTE PARA LEVANTAMIENTO DE ACTA POLICIAL FAVOR', BX2-1, MY+5, {align:'right'});
        doc.text('DIRIJASE A LA CASA ASISTENCIAL CONTRATADA', BX2-1, MY+9, {align:'right'});
        T(7.5,'bold');
        doc.text('003349 +QF (Autos)            RD-0004', BX2-1, MY+15, {align:'right'});

        // ── DIVISORES ────────────────────────────────────────────────────
        const Y_HDR = MY+22;      // línea horizontal bajo header
        doc.setLineWidth(0.4); doc.setDrawColor(0);
        doc.line(MX, Y_HDR, BX2, Y_HDR);                   // horizontal
        // vertical: desde Y_HDR hasta antes de Casa Contratada
        const Y_DIVEND = MY + BH - 22;
        doc.line(XDIV, Y_HDR, XDIV, Y_DIVEND);

        // posiciones X — todo dentro del half izquierdo (MX=22 a XDIV=102, ancho=80mm)
        const XE1 = MX+2;       // etiqueta col-1  (24mm)
        const XV1 = MX+26;      // valor col-1     (48mm) — deja espacio a "Fianza Judicial:"
        const XE2 = MX+46;     // label col-2  (68mm) — empieza después de valores col-1
        const XV2 = MX+64;     // valor col-2  (86mm) — después de "Deduc. Min:" (~18mm label)

        const Y0 = Y_HDR + 8;  // primera fila
        const DY = 8;          // interlineado

        // helper campo
        const DF = (lbl, val, xe, xv, y, fs=8) => {
            T(8,'bold'); doc.text(lbl, xe, y);
            T(fs,'normal'); doc.text(String(val??'N/A'), xv, y);
        };

        // Fecha compacta dd/mm/aa para evitar desborde
        const fmtC = (f) => {
            if (!f) return 'N/A';
            const d = new Date(f);
            return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${String(d.getFullYear()).slice(-2)}`;
        };

        // F1: Póliza (7.5pt → ~20mm → termina en 68mm) | Del/al fechas (desde 68mm)
        T(8,'bold'); doc.text('Póliza:', XE1, Y0);
        T(7.5,'bold'); doc.text(poliza.numero_poliza||'N/A', XV1, Y0);
        T(6.5,'bold');   doc.text('Del:', XE2, Y0);
        T(6.5,'normal'); doc.text(fmtC(poliza.fecha_emision), XE2+7, Y0);
        T(6.5,'bold');   doc.text('al:', XE2+20, Y0);
        T(6.5,'normal'); doc.text(fmtC(poliza.fecha_vencimiento), XE2+25, Y0);

        // F2: Año Vehículo | Deduc.Min (label 16mm → valor desde 86mm)
        DF('Año Vehículo:', vehiculo?.anio||'N/A', XE1, XV1, Y0+DY);
        T(6.5,'bold');   doc.text('Deduc. Min:', XE2, Y0+DY);
        T(6.5,'normal'); doc.text(String(poliza.deduccion||'N/A'), XV2, Y0+DY);

        // F3: Registro | Uso
        DF('Registro:', vehiculo?.placa||'N/A', XE1, XV1, Y0+DY*2);
        DF('Uso:', (vehiculo?.uso||'PRIVADO').toUpperCase(), XE2, XV2, Y0+DY*2);


        // F4: Marca
        DF('Marca:', vehiculo?.marca||'N/A', XE1, XV1, Y0+DY*3);

        // F5: Chasis
        DF('Chasis:', vehiculo?.chasis||'N/A', XE1, XV1, Y0+DY*4);

        // F6: Tipo (fila completa)
        DF('Tipo:', (vehiculo?.tipo_vehiculo||'AUTOMOVIL').toUpperCase(), XE1, XV1, Y0+DY*5);

        // F7: Fianza Judicial (fila completa)
        DF('Fianza Judicial:', fmtDOP(poliza.fianza_judicial||50000), XE1, XV1, Y0+DY*6);

        // línea fina separadora antes de las filas full-width
        doc.setLineWidth(0.2); doc.setDrawColor(140);
        doc.line(MX+1, Y_DIVEND+1, BX2-1, Y_DIVEND+1);
        doc.setDrawColor(0);

        // F8: Casa Contratada (full-width)
        DF('Casa Contratada:', poliza.casa_contratada||'CENTRO DEL AUTOMOVILISTA', XE1, XV1+3, Y0+DY*7+1);

        // F9: Asistencia Vial (full-width)
        DF('Asistencia Vial:', poliza.asistencia_vial||'PREMIUM', XE1, XV1+3, Y0+DY*8+1);

        // ── BLOQUE ASISTENCIA DERECHO ────────────────────────────────────
        const XBL  = XDIV + 3;
        const BWR  = BX2 - XBL - 2;   // ≈ 83mm
        let bY = Y_HDR + 5;
        T(5, 'normal');
        [
            'LA CASA DEL CONDUCTOR(CMA): Av. Simón Bolivar Num. 183, Ens. La Julia, Santo Domingo 10109, D. N.',
            'N.Telefono:(809)381.2424 / Santiago,Telefono:(809)241.4848 Solicitud de Apertura y gestión de Reclamos',
            'CENTRO ASISTENCIAL DEL AUTOMOVILISTA(CAA): Av. 27 de Febrero num.452, casi Esq Ave. Nuñez de Caceres,Santo Domingo, D. N. /Telefono.(809)565.8222 / Santiago Telefono.(809)565.8222',
            'EN CASO DE INCONVENIENTE CON SU VEHICULO (Grua,Recarga de Bateria,Gasolina,Gomas Pinchadas)COMUNICARSE CON SU ASISTENCIA VIAL: Teléfono (809)273.2021',
            'EN CASO DE ROBO DE SU VEHICULO, Notifiquelo inmediatamente a la policia. MULTISEGUROS SU, S.A. Teléfonos:(809)378.1784 / (829)826-5848 Av. Bolivar No. 952, Ensanche. La Julia, Santo Domingo, D.N.'
        ].forEach(txt => {
            const lines = doc.splitTextToSize(txt, BWR);
            doc.text(lines, XBL, bY);
            bY += lines.length * 1.8 + 1.0;
        });

        // ── CONDICIONES PARTICULARES ─────────────────────────────────────
        const YCP = MY + BH + 8;
        doc.setLineWidth(0.3); doc.line(MX, YCP-2, BX2, YCP-2);
        T(9,'bold');
        doc.text('1. CONDICIONES PARTICULARES:', MX, YCP+3);
        T(8.5,'normal');
        const cLines = doc.splitTextToSize('El cliente debe presentar al momento de un siniestro sus documentos vigentes, como: cédula de identidad, matrícula del vehículo a su nombre, o en su defecto acto de venta, y licencia de conducir al día. MultiSeguros SU se reserva el derecho de amparar pérdidas por la falta de alguno de estos documentos. Es aceptable la licencia de conducir expedida en el extranjero que se encuentre en vigencia. No otorgar seguros a extranjeros que no tengan todos sus documentos al día.', BW - 30);
        doc.text(cLines, MX, YCP+9);

        // ── QR CODE (Online Verification) ────────────────────────────────
        const qrUrl = `${POLIZA_DOCS.EMPRESA.base_url}/frontend/verificar-poliza.html?n=${poliza.numero_poliza}`;
        const qrImg = await generarQRDataURL(qrUrl);
        if (qrImg) {
            const QR_SIZE = 25;
            doc.addImage(qrImg, 'PNG', BX2 - QR_SIZE - 2, YCP + 2, QR_SIZE, QR_SIZE);
            T(5.5, 'bold', 0, 51, 153);
            doc.text('VERIFICACIÓN', BX2 - QR_SIZE/2 - 2, YCP + QR_SIZE + 5, { align: 'center' });
            doc.text('EN LÍNEA', BX2 - QR_SIZE/2 - 2, YCP + QR_SIZE + 7, { align: 'center' });
        }

        if (!opts.returnDoc) {
            doc.save(`Marbete_${String(poliza.numero_poliza||'PROVISIONAL').replace(/[^a-z0-9]/gi,'_')}.pdf`);
        }
        return doc;
    } catch(err) {
        console.error('[Marbete] Error:', err); throw err;
    }
}

// ==========================================
// 2. SOLICITUD DE SEGURO DE LEY
//    Formato: A4 vertical (210×297mm)
//    5 Secciones con barras azules MULTISEGUROS
// ==========================================
function generarSolicitudPDF(poliza, cliente, vehiculo, opts = {}) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const { COLORES, EMPRESA } = POLIZA_DOCS;

    const W = 210, margenL = 14, margenR = 196;

    // ---- ENCABEZADO ----
    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', margenL, 8, 38, 16);
    } else {
        doc.setFontSize(14); doc.setTextColor(...COLORES.azul);
        doc.setFont('helvetica', 'bold');
        doc.text('MAS QUE FIANZAS', margenL, 18);
    }

    doc.setFillColor(...COLORES.azul);
    doc.rect(55, 8, 100, 16, 'F');
    doc.setTextColor(...COLORES.blanco);
    doc.setFontSize(11); doc.setFont('helvetica', 'bold');
    doc.text('SOLICITUD DE SEGURO DE LEY', W / 2, 15, { align: 'center' });
    doc.setFontSize(7); doc.setFont('helvetica', 'normal');
    doc.text(`Formulario N°: ${poliza.numero_poliza || 'Por Asignar'}`, W / 2, 21, { align: 'center' });

    doc.setTextColor(60, 60, 60);
    doc.setFontSize(7);
    doc.text(EMPRESA.nombre, margenR, 10, { align: 'right' });
    doc.text(`Fecha: ${fmtFecha(new Date())}`, margenR, 14, { align: 'right' });
    doc.text(EMPRESA.email, margenR, 18, { align: 'right' });

    const dibujarSeccion = (titulo, num, yPos) => {
        doc.setFillColor(...COLORES.azul);
        doc.rect(margenL, yPos, 182, 7, 'F');
        doc.setTextColor(...COLORES.blanco);
        doc.setFontSize(9); doc.setFont('helvetica', 'bold');
        doc.text(`${num}. ${titulo}`, margenL + 2, yPos + 5);
        return yPos + 12;
    };

    const dibujarCampo = (label, valor, x, y, ancho) => {
        doc.setFontSize(6.5); doc.setTextColor(80, 80, 80);
        doc.setFont('helvetica', 'bold');
        doc.text(label, x, y);
        doc.setFont('helvetica', 'normal'); doc.setTextColor(30, 30, 30);
        doc.text(String(valor || '─────────'), x, y + 4);
        doc.setDrawColor(180, 180, 180);
        doc.line(x, y + 5, x + ancho, y + 5);
    };

    let y = 30;

    // ---- SECCIÓN I: DATOS DEL SOLICITANTE ----
    y = dibujarSeccion('DATOS DEL SOLICITANTE', 'I', y);
    dibujarCampo('Nombre / Razón Social:', cliente?.nombre_completo || `${cliente?.nombre || ''} ${cliente?.apellido || ''}`.trim() || 'N/A', margenL, y, 120);
    dibujarCampo('Cédula / RNC:', cliente?.cedula || 'N/A', margenL + 127, y, 55);
    y += 12;
    dibujarCampo('Teléfono:', cliente?.telefono || 'N/A', margenL, y, 55);
    dibujarCampo('Correo Electrónico:', cliente?.email || 'N/A', margenL + 62, y, 115);
    y += 12;
    dibujarCampo('Dirección:', cliente?.direccion || 'N/A', margenL, y, 182);
    y += 16;

    // ---- SECCIÓN II: DATOS DEL VEHÍCULO ----
    y = dibujarSeccion('DATOS DEL VEHÍCULO', 'II', y);
    dibujarCampo('Placa:', vehiculo?.placa || 'N/A', margenL, y, 38);
    dibujarCampo('Año:', vehiculo?.anio || 'N/A', margenL + 45, y, 22);
    dibujarCampo('Marca:', vehiculo?.marca || 'N/A', margenL + 74, y, 40);
    dibujarCampo('Modelo:', vehiculo?.modelo || 'N/A', margenL + 121, y, 40);
    y += 12;
    dibujarCampo('Tipo Vehículo:', vehiculo?.tipo_vehiculo || 'N/A', margenL, y, 45);
    dibujarCampo('Uso:', vehiculo?.uso || 'PRIVADO', margenL + 52, y, 35);
    dibujarCampo('Color:', vehiculo?.color || 'N/A', margenL + 94, y, 35);
    dibujarCampo('Valor Comercial:', vehiculo?.valor_comercial ? fmtDOP(vehiculo.valor_comercial) : 'N/A', margenL + 136, y, 46);
    y += 12;
    dibujarCampo('N° Chasis:', vehiculo?.chasis || 'N/A', margenL, y, 88);
    dibujarCampo('N° Motor:', vehiculo?.motor || 'N/A', margenL + 95, y, 87);
    y += 16;

    // ---- SECCIÓN III: DATOS DEL SEGURO ----
    y = dibujarSeccion('DATOS DEL SEGURO', 'III', y);
    dibujarCampo('Aseguradora:', poliza.aseguradora || 'MULTISEGUROS', margenL, y, 55);
    dibujarCampo('Tipo de Cobertura:', poliza.perfil_cobertura || 'Seguro de Ley', margenL + 62, y, 60);
    dibujarCampo('N° Póliza:', poliza.numero_poliza || 'Por Asignar', margenL + 129, y, 53);
    y += 12;
    dibujarCampo('Prima Neta:', fmtDOP(poliza.prima_neta), margenL, y, 45);
    dibujarCampo('ITBIS (18%):', fmtDOP(poliza.itbis), margenL + 52, y, 40);
    dibujarCampo('Prima Total:', fmtDOP(poliza.prima_total), margenL + 99, y, 45);
    dibujarCampo('Periodicidad:', poliza.periodicidad_pago || 'Anual', margenL + 151, y, 31);
    y += 12;
    dibujarCampo('Vigencia Desde:', fmtFecha(poliza.fecha_emision), margenL, y, 55);
    dibujarCampo('Vigencia Hasta:', fmtFecha(poliza.fecha_vencimiento), margenL + 62, y, 55);
    y += 16;

    // ---- SECCIÓN IV: SERVICIOS OPCIONALES ----
    y = dibujarSeccion('SERVICIOS OPCIONALES', 'IV', y);
    const checkboxes = [
        ['Asistencia Vial (Liviano)  RD$2,600', 'ASIST_VIAL_LIV'],
        ['Asistencia Vial (Pesado)   RD$4,600', 'ASIST_VIAL_PES'],
        ['Casa del Conductor         RD$1,020', 'CASA_CONDUCTOR'],
        ['Centro de Automovilista    RD$1,020', 'CENTRO_AUTOMOVILISTA'],
    ];
    let xCB = margenL;
    checkboxes.forEach(([label, key]) => {
        const marcado = poliza.servicios_opcionales?.[key] || false;
        doc.setFillColor(marcado ? 0 : 255, marcado ? 71 : 255, marcado ? 160 : 255);
        doc.rect(xCB, y, 4, 4, 'F');
        doc.setDrawColor(0, 71, 160); doc.rect(xCB, y, 4, 4);
        if (marcado) { doc.setTextColor(...COLORES.blanco); doc.setFontSize(5); doc.text('✓', xCB + 1, y + 3.2); }
        doc.setTextColor(40, 40, 40); doc.setFontSize(7); doc.setFont('helvetica', 'normal');
        doc.text(label, xCB + 6, y + 3.5);
        xCB += 50;
    });
    y += 14;

    // ---- SECCIÓN V: DECLARACIÓN Y FIRMAS ----
    y = dibujarSeccion('DECLARACIÓN Y FIRMAS', 'V', y);
    doc.setFontSize(7); doc.setTextColor(60, 60, 60); doc.setFont('helvetica', 'italic');
    doc.text('El solicitante declara que los datos suministrados son fidedignos y acepta las condiciones del seguro contratado.', margenL, y, { maxWidth: 182 });
    y += 14;

    // Firmas
    doc.setDrawColor(100); doc.setLineWidth(0.4);
    doc.line(margenL, y, margenL + 75, y);
    doc.line(margenR - 75, y, margenR, y);
    doc.setTextColor(80, 80, 80); doc.setFontSize(7.5); doc.setFont('helvetica', 'bold');
    doc.text('Firma del Solicitante', margenL + 37, y + 4, { align: 'center' });
    doc.text('Firma del Agente Autorizado', margenR - 37, y + 4, { align: 'center' });
    doc.setFont('helvetica', 'normal'); doc.setFontSize(6.5);

    const nombreAgente = poliza.agente_nombre || 'Agente MAS QUE FIANZAS';
    doc.text(nombreAgente, margenR - 37, y + 8, { align: 'center' });
    doc.text(`Fecha: ${fmtFecha(new Date())}`, W / 2, y + 12, { align: 'center' });

    // ---- PIE DE PÁGINA ----
    doc.setFontSize(7); doc.setTextColor(150); doc.setFont('helvetica', 'normal');
    doc.text(EMPRESA.direccion, W / 2, 285, { align: 'center' });
    doc.text(`Tel: ${EMPRESA.telefono}  |  Email: ${EMPRESA.email}`, W / 2, 289, { align: 'center' });

    if (!opts.returnDoc) {
        doc.save(`Solicitud-${poliza.numero_poliza}.pdf`);
    }
    return doc;
}

// ==========================================
// 3. RECIBO DE PAGO
//    Formato: A4, diseño corporativo azul
// ==========================================
function generarReciboPDF(poliza, cliente, pago, opts = {}) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const { COLORES, EMPRESA } = POLIZA_DOCS;
    const W = 210, margenL = 14, margenR = 196;

    // Fondo encabezado
    doc.setFillColor(...COLORES.azul);
    doc.rect(0, 0, W, 42, 'F');

    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', margenL, 6, 40, 18);
    }

    doc.setTextColor(...COLORES.blanco);
    doc.setFontSize(20); doc.setFont('helvetica', 'bold');
    doc.text('RECIBO DE PAGO', W / 2, 18, { align: 'center' });
    doc.setFontSize(8); doc.setFont('helvetica', 'normal');
    doc.text(`N° Recibo: ${pago.numero_recibo || 'REC-' + new Date().getFullYear() + '-' + String(pago.id || '0001').padStart(4, '0')}`, W / 2, 24, { align: 'center' });

    // Info de empresa derecha
    doc.setFontSize(7);
    doc.text(EMPRESA.nombre, margenR, 10, { align: 'right' });
    doc.text(`RNC: ${EMPRESA.rnc}`, margenR, 14, { align: 'right' });
    doc.text(EMPRESA.telefono, margenR, 18, { align: 'right' });

    // NCF
    doc.setFontSize(9); doc.setFont('helvetica', 'bold');
    doc.text(`NCF: ${pago.numero_ncf || 'B02-PENDIENTE'}`, margenR, 32, { align: 'right' });
    doc.text(`Tipo: ${pago.tipo_comprobante || 'B02'} — Persona Física`, margenR, 38, { align: 'right' });

    let y = 52;

    // Datos del cliente
    doc.setFillColor(240, 246, 255);
    doc.rect(margenL, y, 182, 24, 'F');
    doc.setTextColor(30, 30, 30); doc.setFontSize(9); doc.setFont('helvetica', 'bold');
    doc.text('DATOS DEL CLIENTE', margenL + 4, y + 6);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8);
    doc.text(`Nombre: ${cliente?.nombre_completo || cliente?.nombre || 'N/A'}`, margenL + 4, y + 12);
    doc.text(`Cédula / RNC: ${cliente?.cedula || 'N/A'}`, margenL + 4, y + 18);
    doc.text(`Fecha de Pago: ${fmtFecha(pago.fecha_pago)}`, margenL + 100, y + 12);
    doc.text(`Método: ${pago.tipo_pago?.toUpperCase() || 'N/A'}`, margenL + 100, y + 18);
    y += 32;

    // Descripción del servicio
    doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(...COLORES.azul);
    doc.text('DESCRIPCIÓN DEL SERVICIO', margenL, y);
    doc.setLineWidth(0.3); doc.setDrawColor(...COLORES.azul);
    doc.line(margenL, y + 2, margenR, y + 2);
    y += 8;

    doc.autoTable({
        startY: y,
        head: [['Descripción', 'Póliza N°', 'Cuota', 'Monto']],
        body: [[
            pago.descripcion || `Pago ${pago.cuota_numero || 1} de ${pago.cuota_total || 1} — ${poliza.tipo_seguro || 'Póliza'}`,
            poliza.numero_poliza,
            `${pago.cuota_numero || 1} / ${pago.cuota_total || 1}`,
            fmtDOP(pago.monto)
        ]],
        theme: 'grid',
        headStyles: { fillColor: COLORES.azul, textColor: 255, fontStyle: 'bold', fontSize: 9 },
        styles: { fontSize: 8, cellPadding: 3 },
        columnStyles: { 3: { halign: 'right', fontStyle: 'bold' } }
    });

    y = doc.lastAutoTable.finalY + 8;

    // Totales
    doc.setFillColor(240, 246, 255);
    doc.rect(W - 90, y, 76, 28, 'F');
    doc.setTextColor(60, 60, 60); doc.setFontSize(8);

    const primaNeta = pago.monto / 1.18;
    const itbis = pago.monto - primaNeta;

    doc.setFont('helvetica', 'normal');
    doc.text('Prima Neta:', W - 88, y + 7);
    doc.text(fmtDOP(primaNeta), margenR, y + 7, { align: 'right' });
    doc.text('ITBIS (18%):', W - 88, y + 13);
    doc.text(fmtDOP(itbis), margenR, y + 13, { align: 'right' });
    doc.setFont('helvetica', 'bold'); doc.setTextColor(...COLORES.azul); doc.setFontSize(10);
    doc.text('TOTAL:', W - 88, y + 22);
    doc.text(fmtDOP(pago.monto), margenR, y + 22, { align: 'right' });

    y += 40;

    // Nota de referencia
    if (pago.numero_comprobante) {
        doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.setTextColor(80, 80, 80);
        doc.text(`Banco: ${pago.banco || 'N/A'}  |  N° Ref/Comprobante: ${pago.numero_comprobante}`, margenL, y);
        y += 10;
    }

    // Firma
    doc.setLineWidth(0.4); doc.setDrawColor(120);
    doc.line(margenL, y + 12, margenL + 60, y + 12);
    doc.setFontSize(7.5); doc.setTextColor(80, 80, 80); doc.setFont('helvetica', 'bold');
    doc.text('Cajero / Receptor', margenL + 30, y + 16, { align: 'center' });

    // Pie
    doc.setFontSize(7); doc.setTextColor(150); doc.setFont('helvetica', 'normal');
    doc.text(`${EMPRESA.nombre}  |  ${EMPRESA.direccion}`, W / 2, 283, { align: 'center' });
    doc.text(`Tel: ${EMPRESA.telefono}  |  ${EMPRESA.email}`, W / 2, 287, { align: 'center' });
    doc.text('Este documento es un comprobante de pago interno. La factura fiscal es emitida por la aseguradora MULTISEGUROS.', W / 2, 291, { align: 'center' });

    if (!opts.returnDoc) {
        doc.save(`Recibo-${pago.numero_recibo || poliza.numero_poliza}.pdf`);
    }
    return doc;
}

// ==========================================
// 4. FACTURA INTERNA
//    Igual que Recibo + NCF grande + datos DGII
// ==========================================
function generarFacturaPDF(poliza, cliente, pago, opts = {}) {
    const doc = generarReciboPDF(poliza, cliente, pago, { returnDoc: true });
    // La factura es igual al recibo pero el NCF se muestra grande y en rojo para que se distinga
    // Se puede ampliar aquí con lógica de NCF autorizado
    if (!opts.returnDoc) {
        doc.save(`Factura-${pago.numero_ncf || poliza.numero_poliza}.pdf`);
    }
    return doc;
}

// ==========================================
// 5. GENERADOR DINÁMICO (MODELADOR PDF-DOCS)
// ==========================================
/**
 * Motor de PDF Dinámico (estilo JotForm PDF Editor)
 * Recibe el objeto plantilla completo (con sus campos ya cargados) y el contexto de datos.
 * Carga el archivo base (PDF/imagen), estampa cada campo en la posición guardada
 * y descarga el documento resultante.
 *
 * @param {Object} plantilla  - Objeto de plantilla con { id, nombre, archivo_base, tipo_archivo, ancho_mm, alto_mm, campos[] }
 * @param {Object} data       - Contexto de datos { cliente:{}, poliza:{}, vehiculo:{}, general:{} }
 */
async function generarDocumentoDinamicoPDF(plantilla, data) {
    // Cargar pdf-lib dinámicamente si no está disponible
    if (typeof PDFLib === 'undefined') {
        await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js';
            script.onload = resolve;
            script.onerror = () => reject(new Error('No se pudo cargar pdf-lib'));
            document.head.appendChild(script);
        });
    }

    const { PDFDocument, rgb, StandardFonts } = PDFLib;

    try {
        const fileUrl = `/PLATAFORMA_INTEGRADA/backend/uploads/plantillas_pdf/${plantilla.archivo_base}`;
        const fileRes = await fetch(fileUrl);
        if (!fileRes.ok) throw new Error(`Archivo de plantilla no encontrado: ${plantilla.archivo_base}`);
        const fileBuffer = await fileRes.arrayBuffer();

        let pdfDoc;

        if (plantilla.tipo_archivo === 'pdf') {
            pdfDoc = await PDFDocument.load(fileBuffer);
        } else {
            // Es imagen (PNG / JPG)
            const ptW = parseFloat(plantilla.ancho_mm) * 2.83465;
            const ptH = parseFloat(plantilla.alto_mm) * 2.83465;
            pdfDoc = await PDFDocument.create();
            const page = pdfDoc.addPage([ptW, ptH]);
            const image = plantilla.tipo_archivo === 'png'
                ? await pdfDoc.embedPng(fileBuffer)
                : await pdfDoc.embedJpg(fileBuffer);
            page.drawImage(image, { x: 0, y: 0, width: ptW, height: ptH });
        }

        const pages = pdfDoc.getPages();
        const firstPage = pages[0];
        const pageHeight = firstPage.getHeight();
        const pageWidth  = firstPage.getWidth();

        // Fuentes embebidas
        const fontRegular  = await pdfDoc.embedFont(StandardFonts.Helvetica);
        const fontBold     = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

        // ── HELPER: dataURI a Uint8Array ──────────────────────────────────
        const dataURItoBytes = (uri) => {
            const b64 = uri.split(',')[1];
            const bin = atob(b64);
            const arr = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
            return arr;
        };

        // ── LOGO ──────────────────────────────────────────────────────────
        const asegKey = (plantilla.aseguradora_nombre || '').toUpperCase().trim();
        const logoURI = (window.LOGOS && window.LOGOS[asegKey]) ||
                        (asegKey === 'MULTISEGUROS' ? window.LOGO_MULTISEGUROS_B64 : null);
        console.log('[PDF] Logo:', asegKey, logoURI ? 'OK len='+logoURI.length : 'NO');
        if (logoURI) {
            try {
                const logoBytes = dataURItoBytes(logoURI);
                const isJpg = logoURI.startsWith('data:image/jpeg') || logoURI.startsWith('data:image/jpg');
                const logoImg = isJpg ? await pdfDoc.embedJpg(logoBytes) : await pdfDoc.embedPng(logoBytes);
                const logoW = 100, logoH = 38;
                firstPage.drawImage(logoImg, { x: 100, y: pageHeight - 58, width: logoW, height: logoH });
                console.log('[PDF] Logo incrustado OK');
            } catch(e) { console.warn('[PDF] Error logo:', e.message); }
        }

        // ── QR ────────────────────────────────────────────────────────────
        const nroPol = (data.poliza && data.poliza.numero_poliza) || '';
        if (nroPol) {
            try {
                const qrUrl = window.location.origin + '/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=' + encodeURIComponent(nroPol);
                const qrDataURI = await generarQRDataURL(qrUrl);
                if (qrDataURI) {
                    const qrBytes = dataURItoBytes(qrDataURI);
                    const qrImg = await pdfDoc.embedPng(qrBytes);
                    const QS = 65;
                    firstPage.drawImage(qrImg, { x: pageWidth - QS - 12, y: 12, width: QS, height: QS });
                    firstPage.drawText('Escanear para verificar', { x: pageWidth - QS - 10, y: 9, size: 5.5, font: fontBold, color: rgb(0, 0.2, 0.6) });
                    console.log('[PDF] QR incrustado OK');
                }
            } catch(e) { console.warn('[PDF] Error QR:', e.message); }
        }

        // Resolver variables de la plantilla desde el contexto
        const resolveVar = (path) => {
            if (!path) return '';
            const val = path.split('.').reduce((obj, key) => {
                return (obj && obj[key] !== undefined) ? obj[key] : '';
            }, data);
            return String(val || '');
        };

        if (plantilla.campos && plantilla.campos.length > 0) {
            plantilla.campos.forEach(c => {
                const valor = resolveVar(c.variable);
                const fontSize = parseFloat(c.font_size) || 9;
                const posX    = parseFloat(c.pos_x);
                // Invertir Y: pdf-lib tiene (0,0) en la esquina inferior izquierda
                const posY    = pageHeight - parseFloat(c.pos_y) - fontSize;
                const isBold  = c.negrita == 1 || c.negrita === true || c.negrita === '1' || c.font_weight === 'bold';

                firstPage.drawText(valor, {
                    x: posX,
                    y: posY,
                    size: fontSize,
                    font: isBold ? fontBold : fontRegular,
                    color: rgb(0, 0, 0)
                });
            });
        }

        const pdfBytes = await pdfDoc.save();
        const blob = new Blob([pdfBytes], { type: 'application/pdf' });
        const url  = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = `${plantilla.nombre || 'Documento'}_Generado.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        console.log(`PDF dinámico generado: ${plantilla.nombre}`);

    } catch (e) {
        console.error('Error en generarDocumentoDinamicoPDF:', e);
        // Lanzar error para que el caller pueda hacer fallback
        throw e;
    }
}

// ==========================================
// EXPORTAR AL GLOBAL
// ==========================================
window.generarMarbetePDF = generarMarbetePDF;
window.generarSolicitudPDF = generarSolicitudPDF;
window.generarReciboPDF = generarReciboPDF;
window.generarFacturaPDF = generarFacturaPDF;
window.generarDocumentoDinamicoPDF = generarDocumentoDinamicoPDF;
window.generarQRDataURL = generarQRDataURL;
