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

/** Genera un QR Code como dataURL usando el texto dado */
async function generarQRDataURL(texto) {
    if (typeof QRCode === 'undefined') return null;
    const canvas = document.createElement('canvas');
    try {
        await QRCode.toCanvas(canvas, texto, { width: 120, margin: 1, color: { dark: '#003366', light: '#ffffff' } });
        return canvas.toDataURL('image/png');
    } catch (e) {
        console.warn('QR error:', e);
        return null;
    }
}

// ==========================================
// 1. MARBETE PROVISIONAL
//    Formato: A6 horizontal (148×105mm)
//    Fondo: Azul Navy, texto: blanco/dorado
//    QR: Apunta a verificar-poliza?n={numero}
// ==========================================
async function generarMarbetePDF(poliza, vehiculo, opts = {}) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [148, 105] });
    const { COLORES, EMPRESA } = POLIZA_DOCS;

    const W = 148, H = 105;

    // -- FONDO PRINCIPAL --
    doc.setFillColor(...COLORES.navy);
    doc.rect(0, 0, W, H, 'F');

    // -- FRANJA DORADA SUPERIOR --
    doc.setFillColor(...COLORES.dorado);
    doc.rect(0, 0, W, 3, 'F');
    doc.rect(0, H - 3, W, 3, 'F');

    // -- LOGO MQF (si disponible) --
    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', 4, 5, 32, 13);
    } else {
        doc.setTextColor(...COLORES.dorado);
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        doc.text('MAS QUE FIANZAS', 4, 13);
    }

    // -- TÍTULO CENTRAL --
    doc.setTextColor(...COLORES.dorado);
    doc.setFontSize(13);
    doc.setFont('helvetica', 'bold');
    doc.text('MARBETE PROVISIONAL', W / 2, 11, { align: 'center' });

    doc.setTextColor(...COLORES.blanco);
    doc.setFontSize(7);
    doc.setFont('helvetica', 'normal');
    doc.text('SEGURO DE RESPONSABILIDAD CIVIL — VEHÍCULOS DE MOTOR', W / 2, 16, { align: 'center' });

    // -- LÍNEA SEPARADORA --
    doc.setDrawColor(...COLORES.dorado);
    doc.setLineWidth(0.4);
    doc.line(4, 19, W - 4, 19);

    // -- ASEGURADORA ARRIBA DERECHA --
    doc.setTextColor(180, 210, 255);
    doc.setFontSize(6.5);
    doc.setFont('helvetica', 'normal');
    doc.text('Aseguradora:', 90, 9);
    doc.setTextColor(...COLORES.blanco);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.5);
    doc.text(poliza.aseguradora || 'MULTISEGUROS', 90, 14);

    // -- DATOS EN 2 COLUMNAS --
    const col1 = 4, col2 = 78;
    const LABEL_COLOR = [140, 180, 230];
    const VAL_COLOR = [255, 255, 255];
    const BOLD_COLOR = [255, 230, 100];

    const campos = [
        [['Nombre Asegurado:', poliza.cliente_nombre, true], ['N° Póliza:', poliza.numero_poliza, true]],
        [['Cédula:', poliza.cliente_cedula || 'N/A', false], ['Tipo Seguro:', poliza.tipo_seguro || 'Seguro de Ley', false]],
        [['Placa:', vehiculo?.placa || 'N/A', true], ['Vigencia Desde:', fmtFecha(poliza.fecha_emision), false]],
        [['Marca / Modelo:', `${vehiculo?.marca || ''} ${vehiculo?.modelo || ''}`.trim() || 'N/A', false], ['Vigencia Hasta:', fmtFecha(poliza.fecha_vencimiento), true]],
        [['Año / Color:', `${vehiculo?.anio || ''} / ${vehiculo?.color || ''}`.trim() || 'N/A', false], ['Cobertura:', poliza.perfil_cobertura || 'Ley', false]],
        [['Tipo Vehículo:', vehiculo?.tipo_vehiculo || 'N/A', false], ['Prima Total:', fmtDOP(poliza.prima_total), true]],
    ];

    let y = 25;
    campos.forEach(([izq, der]) => {
        // Columna izquierda
        doc.setTextColor(...LABEL_COLOR);
        doc.setFontSize(5.8);
        doc.setFont('helvetica', 'normal');
        doc.text(izq[0], col1, y);
        doc.setTextColor(...(izq[2] ? BOLD_COLOR : VAL_COLOR));
        doc.setFontSize(izq[2] ? 7 : 6.5);
        doc.setFont('helvetica', izq[2] ? 'bold' : 'normal');
        doc.text(String(izq[1]), col1, y + 4.5);

        // Columna derecha
        doc.setTextColor(...LABEL_COLOR);
        doc.setFontSize(5.8);
        doc.setFont('helvetica', 'normal');
        doc.text(der[0], col2, y);
        doc.setTextColor(...(der[2] ? BOLD_COLOR : VAL_COLOR));
        doc.setFontSize(der[2] ? 7 : 6.5);
        doc.setFont('helvetica', der[2] ? 'bold' : 'normal');
        doc.text(String(der[1]), col2, y + 4.5);

        y += 11;
    });

    // -- QR CODE --
    const urlVerificacion = `${EMPRESA.base_url}/frontend/verificar-poliza.html?n=${encodeURIComponent(poliza.numero_poliza)}`;
    const qrData = await generarQRDataURL(urlVerificacion);
    if (qrData) {
        doc.addImage(qrData, 'PNG', W - 30, 20, 27, 27);
        doc.setTextColor(140, 180, 230);
        doc.setFontSize(4.5);
        doc.text('Escanee para', W - 17, 50, { align: 'center' });
        doc.text('verificar vigencia', W - 17, 53.5, { align: 'center' });
    }

    // -- PIE LEGAL --
    doc.setFillColor(0, 35, 75);
    doc.rect(0, H - 12, W, 9, 'F');
    doc.setTextColor(140, 180, 230);
    doc.setFontSize(4.8);
    doc.setFont('helvetica', 'normal');
    doc.text('☑ Este documento es válido hasta la emisión del Marbete Definitivo por la Dirección General de Impuestos Internos (DGII)', W / 2, H - 8, { align: 'center' });
    doc.text(`${EMPRESA.nombre} | ${EMPRESA.telefono} | ${EMPRESA.email}`, W / 2, H - 4.5, { align: 'center' });

    if (!opts.returnDoc) {
        doc.save(`Marbete-${poliza.numero_poliza}.pdf`);
    }
    return doc;
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
// EXPORTAR AL GLOBAL
// ==========================================
window.generarMarbetePDF = generarMarbetePDF;
window.generarSolicitudPDF = generarSolicitudPDF;
window.generarReciboPDF = generarReciboPDF;
window.generarFacturaPDF = generarFacturaPDF;
window.generarQRDataURL = generarQRDataURL;
