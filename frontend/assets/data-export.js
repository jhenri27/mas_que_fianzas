// ======== GENERADOR DE PDF OFICIAL DE FIANZAS (UNIFICADO PLATAFORMA & PWA APP) ========
async function generarPDFFianza(data) {
  try {
    if (!window.jspdf) {
      if (typeof MQF !== 'undefined' && MQF.toast) MQF.toast('Motor PDF no cargado', 'error');
      else if (typeof UI !== 'undefined' && UI.showToast) UI.showToast('Motor PDF no cargado', 'danger');
      return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const W = 210;
    const H = 297;
    const ML = 15;
    const MR = 15;
    const CW = W - ML - MR; // 180mm
    let y = 12;

    // ── 1. NORMALIZACIÓN DE DATOS Y ESTADO ──
    const st = String(data.estado || '').toLowerCase();
    const esPagadaOVigente = (st === 'vigente' || st === 'emitida' || st === 'pagada' || st === 'liberada' || data.pago_confirmado == 1 || data.pagado == 1);
    const esBorrador = (data.es_borrador === true) || (!esPagadaOVigente && (st === 'cotizacion' || st === 'pendiente' || !data.estado));

    const rawNum = String(data.numero || data.numero_fianza || data.numero_cotizacion || data.id || 'FZ-2026-00001');
    const cleanNum = rawNum.replace(/^(COT-|POL-)/i, '');
    const displayNum = cleanNum.startsWith('FZ-') ? cleanNum : ('FZ-' + cleanNum);

    const clienteName = (data.cliente || data.cliente_nombre || data.nombre || data.razon_social || 'CLIENTE REGISTRADO').toUpperCase();
    const clienteCedula = data.cedula || data.cliente_cedula || data.rnc || '001-2695438-3';
    const benef = (data.beneficiario || 'EL BENEFICIARIO').toUpperCase();
    
    let aseguradora = data.aseguradora || data.aseguradora_nombre || 'MULTISEGUROS';
    if (!isNaN(aseguradora) && parseInt(aseguradora) > 0) {
      const idB = parseInt(aseguradora);
      const mapId = { 1: 'MULTISEGUROS', 2: 'MIDAS SEGUROS', 3: 'SEGUROS PATRIA', 4: 'SEGUROS PEPIN' };
      if (mapId[idB]) aseguradora = mapId[idB];
      else {
        const fnd = (window._wizAseguradoras || window._aseguradorasSimplesList || []).find(a => parseInt(a.id) === idB);
        if (fnd) aseguradora = fnd.nombre;
      }
    }
    aseguradora = (aseguradora || 'MULTISEGUROS').toUpperCase();

    const tipoFianza = (data.tipo_fianza || data.subtipo || data.tipo_nombre || 'BUEN USO DEL ANTICIPO').toUpperCase();
    const montoAfianzado = parseFloat(data.monto_afianzado || data.valor_afianzar || data.monto_contrato || 250000.00);
    const montoFmt = 'RD$ ' + montoAfianzado.toLocaleString('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    const refObjeto = data.objeto_referencia || data.numero_contrato || data.referencia || data.cobertura_adicional || ('CONTRATO DE ' + tipoFianza);
    
    // Fechas en español literal
    function fmtFechaEs(dStr) {
      if (!dStr) return 'sábado, 8 de agosto de 2026';
      try {
        const d = new Date(dStr.length === 10 ? dStr + 'T12:00:00' : dStr);
        if (isNaN(d.getTime())) return dStr;
        const dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return `${dias[d.getDay()]}, ${meses[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
      } catch(e) { return dStr; }
    }

    const fechaInicio = fmtFechaEs(data.fecha_inicio || data.fecha || data.creado_en);
    const fechaVencimiento = fmtFechaEs(data.fecha_vencimiento || data.vigencia_hasta);
    const fechaActual = fmtFechaEs(new Date().toISOString());

    // ── 2. MARCA DE AGUA (WATERMARK SI ES BORRADOR O NO PAGADA) ──
    if (esBorrador) {
      try {
        doc.saveGraphicsState();
        if (typeof doc.setGState === 'function' && window.jspdf && window.jspdf.GState) {
          doc.setGState(new window.jspdf.GState({ opacity: 0.16 }));
        }
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(32);
        doc.setTextColor(120, 120, 120);
        doc.text('Borrador Sin Valor Comercial', 105, 145, { align: 'center', angle: 45 });
        doc.restoreGraphicsState();
      } catch (e) {
        doc.setFontSize(22); doc.setTextColor(200, 200, 200);
        doc.text('Borrador Sin Valor Comercial', 105, 145, { align: 'center' });
      }
    }

    // ── 3. ENCABEZADO Y LOGO DE LA ASEGURADORA SELECCIONADA ──
    let aseguradoraNorm = String(aseguradora || '').toUpperCase().trim();
    let logoInsurer = null;

    if (window.LOGOS) {
      for (let key in window.LOGOS) {
        if (key && (aseguradoraNorm.includes(key.toUpperCase().trim()) || key.toUpperCase().trim().includes(aseguradoraNorm))) {
          logoInsurer = window.LOGOS[key];
          break;
        }
      }
    }
    if (!logoInsurer) {
      if (aseguradoraNorm.includes('MIDAS')) logoInsurer = window.LOGOS ? window.LOGOS["MIDAS SEGUROS"] : null;
      else if (aseguradoraNorm.includes('PATRIA')) logoInsurer = window.LOGOS ? window.LOGOS["SEGUROS PATRIA"] : null;
      else if (aseguradoraNorm.includes('PEP')) logoInsurer = window.LOGOS ? window.LOGOS["SEGUROS PEPIN"] : null;
      else if (aseguradoraNorm.includes('MULTI')) logoInsurer = window.LOGOS ? window.LOGOS["MULTISEGUROS"] : null;
    }

    const logoFinal = logoInsurer || window.LOGO_MQF_B64 || window.MQF_LOGO_B64 || window.LOGO_B64 || null;

    let yHeader = 8;

    if (logoFinal) {
      try {
        doc.addImage(logoFinal, 'PNG', ML, yHeader, 32, 12);
      } catch(e) {
        try { doc.addImage(logoFinal, 'JPEG', ML, yHeader, 32, 12); } catch(e2){}
      }
    } else {
      doc.setFontSize(12); doc.setFont('helvetica', 'bold'); doc.setTextColor(0, 51, 153);
      doc.text(aseguradoraNorm || 'MÁS QUE FIANZAS', ML, yHeader + 8);
    }

    // Título Principal Centrado
    doc.setFontSize(12); doc.setFont('helvetica', 'bold'); doc.setTextColor(15, 23, 42);
    doc.text('POLIZA DE FIANZAS COMERCIALES', W / 2, yHeader + 5, { align: 'center' });
    doc.setFontSize(9.5);
    doc.text('CONDICIONES PARTICULARES', W / 2, yHeader + 10, { align: 'center' });

    // Línea estética de división
    doc.setDrawColor(210, 214, 220);
    doc.setLineWidth(0.4);
    doc.line(ML, 23, W - MR, 23);

    // Posición Y fija sin superposición para la fila de metadatos
    y = 30;

    // Fila Datos Cabecera (POLIZA NO., RAMO, MONEDA)
    doc.setFontSize(8.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('POLIZA NO.:', ML, y);
    doc.setFont('helvetica', 'normal');
    doc.text(displayNum, ML + 20, y);

    doc.setFont('helvetica', 'bold');
    doc.text('RAMO:', ML + 75, y);
    doc.setFont('helvetica', 'normal');
    doc.text('FIANZAS COMERCIALES', ML + 88, y);

    doc.setFont('helvetica', 'bold');
    doc.text('MONEDA:', ML + 140, y);
    doc.setFont('helvetica', 'normal');
    doc.text('RD$ PESOS DOM', ML + 156, y);

    y += 8;

    // Subtítulo FIANZA DE...
    doc.setFontSize(10.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(15, 23, 42);
    doc.text('FIANZA DE ' + tipoFianza, W / 2, y, { align: 'center' });

    y += 9;

    // ── 4. CLÁUSULAS LEGALES DE LA PÓLIZA (POR CUANTO 1..8) ──
    doc.setFontSize(8.0); doc.setFont('helvetica', 'normal'); doc.setTextColor(30, 30, 30);

    // Cláusula 1
    const p1 = `1.- POR CUANTO: ${clienteName}, RNC: ${clienteCedula} (EN LO ADELANTE EL AFIANZADO).\n\n` +
               `Ha solicitado en fecha ${fechaInicio} a la compañía "${aseguradora}". (EN LO ADELANTE EL FIADOR), una fianza por una suma de ${montoFmt}. PARA GARANTIZAR LAS OBLIGACIONES SIGUIENTES:\n\n` +
               `FIANZA PARA GARANTIZAR ${tipoFianza} DE CONTRATACIÓN DE UNA EMPRESA O PERSONA FÍSICA REFERENCIA: ${refObjeto}.`;
    const lines1 = doc.splitTextToSize(p1, CW);
    doc.text(lines1, ML, y);
    y += lines1.length * 3.4 + 3;

    // Cláusula 2
    const p2 = `2.- POR CUANTO: La presente fianza se emite a favor y en beneficio exclusivo de: ${benef}. Y tiene como fecha de vigencia desde el ${fechaInicio} al ${fechaVencimiento}, o antes si el afianzado ha cumplido su obligación.`;
    const lines2 = doc.splitTextToSize(p2, CW);
    doc.text(lines2, ML, y);
    y += lines2.length * 3.4 + 3;

    // Cláusula 3
    const p3 = `3.- POR CUANTO: EL FIADOR no acepta ni reconoce ninguna transferencia que de la presente se haga a persona natural o jurídica.`;
    const lines3 = doc.splitTextToSize(p3, CW);
    doc.text(lines3, ML, y);
    y += lines3.length * 3.4 + 3;

    // Cláusula 4 y Literales A y B
    const p4 = `4.- POR CUANTO: EL FIADOR, se compromete a responder a EL BENEFICIARIO, de todos los daños y perjuicios que demuestre le ocurran a consecuencia del incumplimiento de las obligaciones legales - contractuales - del afianzado, hasta una suma nunca mayor que el límite de la presente fianza y siempre que haya sido declarada ejecutoria de acuerdo con la ley.\n\n` +
               `A.-) Se hace constar que: cualquier reclamación a cargo de esta fianza debe hacerse dentro de los treinta días posteriores a la fecha en que legalmente sea declarada ejecutoria.\n\n` +
               `B.-) Esta Fianza cesará en su validez y no podrá ser ejecutada por el beneficiario de la misma en todo y cualquier caso de Fuerza Mayor, de acuerdo a la Ley Dominicana que evite o impida a ${clienteName} el cumplir con las obligaciones contractuales asumidas por este y garantizadas por la presente fianza.`;
    const lines4 = doc.splitTextToSize(p4, CW);
    doc.text(lines4, ML, y);
    y += lines4.length * 3.4 + 3;

    // Cláusula 5
    const p5 = `5.- POR CUANTO: "${aseguradora}", No realizará devoluciones de la prima pagada ni de los gastos incurridos, por ningún concepto que alegue el afianzado.`;
    const lines5 = doc.splitTextToSize(p5, CW);
    doc.text(lines5, ML, y);
    y += lines5.length * 3.4 + 3;

    // Cláusula 6
    const p6 = `6.- POR CUANTO: El afianzado ha pagado la prima de seguros de la fianza y abonado los gastos exigidos por las leyes vigentes.`;
    const lines6 = doc.splitTextToSize(p6, CW);
    doc.text(lines6, ML, y);
    y += lines6.length * 3.4 + 3;

    // Cláusula 7
    const p7 = `7.- POR CUANTO: La ley de seguros privados vigente capacita a las Compañías de Seguros, legalmente autorizadas a las prestaciones de fianzas en favor del Estado, los Municipios, El Distrito Nacional o sus dependencias.`;
    const lines7 = doc.splitTextToSize(p7, CW);
    doc.text(lines7, ML, y);
    y += lines7.length * 3.4 + 3;

    // Cláusula 8
    const p8 = `8.- POR CUANTO: "${aseguradora}", legalmente constituida y autorizada a ejercer el negocio de seguros en general en todo el Territorio Nacional de acuerdo con disposiciones legales vigentes, otorga por la presente póliza la fianza por ${montoFmt} exigida a los señores ${clienteName} conforme a las estipulaciones del presente convenio.`;
    const lines8 = doc.splitTextToSize(p8, CW);
    doc.text(lines8, ML, y);
    y += lines8.length * 3.4 + 4;

    // En testimonio de lo cual
    const pTestimonio = `En testimonio de lo cual, "${aseguradora}", firma la presente póliza, en Santo Domingo, Distrito Nacional, República Dominicana, a los ${fechaActual}.`;
    const linesTest = doc.splitTextToSize(pTestimonio, CW);
    doc.text(linesTest, ML, y);
    y += linesTest.length * 3.4 + 10;

    // Firma Autorizada
    doc.setFont('helvetica', 'bold');
    doc.text('Firma Autorizada', ML, y);

    // ── 5. CÓDIGO QR DE VALIDACIÓN PÚBLICA EN ESQUINA INFERIOR ──
    const baseUrl = (window.location.origin && window.location.origin.indexOf('localhost') === -1 && window.location.origin.indexOf('127.0.0.1') === -1)
      ? window.location.origin + '/PLATAFORMA_INTEGRADA/frontend'
      : 'http://169.58.51.147/PLATAFORMA_INTEGRADA/frontend';
    const verifyUrl = baseUrl + '/verificar-poliza.html?n=' + encodeURIComponent(displayNum);

    const qrDataUrl = await generarQRDataURL(verifyUrl);
    if (qrDataUrl) {
      try {
        doc.addImage(qrDataUrl, 'PNG', W - MR - 24, H - 36, 22, 22);
        doc.setFontSize(6.0); doc.setFont('helvetica', 'bold'); doc.setTextColor(80, 80, 80);
        doc.text('VALIDEZ OFICIAL QR', W - MR - 13, H - 12, { align: 'center' });
      } catch(e){}
    }

    const fnPrefix = esBorrador ? 'borrador_fianza_' : 'fianza_';
    doc.save(fnPrefix + cleanNum + '.pdf');
    if (typeof MQF !== 'undefined' && MQF.toast) MQF.toast('✅ PDF de Fianza generado: ' + fnPrefix + cleanNum + '.pdf', 'success');
    else if (typeof UI !== 'undefined' && UI.showToast) UI.showToast('✅ PDF de Fianza generado: ' + fnPrefix + cleanNum + '.pdf', 'success');

  } catch(e) {
    console.error('Error generando PDF de fianza:', e);
    if (typeof MQF !== 'undefined' && MQF.toast) MQF.toast('Error al generar PDF: ' + e.message, 'error');
  }
}

function obtenerLogoAseguradoraB64(nombre) {
    if (!nombre) return window.LOGO_MQF_B64 || window.MQF_LOGO_B64 || window.LOGO_B64 || null;
    const name = String(nombre).toUpperCase().trim();
    if (window.LOGOS) {
        if (name.includes('MIDAS')) return window.LOGOS["MIDAS SEGUROS"] || window.LOGO_MIDAS_SEGUROS_B64 || null;
        if (name.includes('PATRIA')) return window.LOGOS["SEGUROS PATRIA"] || window.LOGO_SEGUROS_PATRIA_B64 || null;
        if (name.includes('PEPIN') || name.includes('PEPÍN')) return window.LOGOS["SEGUROS PEPIN"] || window.LOGO_SEGUROS_PEPIN_B64 || null;
        if (name.includes('MULTI') || name.includes('MULTISEGUROS')) return window.LOGOS["MULTISEGUROS"] || window.LOGO_MULTISEGUROS_B64 || null;
        if (window.LOGOS[name]) return window.LOGOS[name];
    }
    if (name.includes('MIDAS') && window.LOGO_MIDAS_SEGUROS_B64) return window.LOGO_MIDAS_SEGUROS_B64;
    if (name.includes('PATRIA') && window.LOGO_SEGUROS_PATRIA_B64) return window.LOGO_SEGUROS_PATRIA_B64;
    if ((name.includes('PEPIN') || name.includes('PEPÍN')) && window.LOGO_SEGUROS_PEPIN_B64) return window.LOGO_SEGUROS_PEPIN_B64;
    if ((name.includes('MULTI') || name.includes('MULTISEGUROS')) && window.LOGO_MULTISEGUROS_B64) return window.LOGO_MULTISEGUROS_B64;
    return window.LOGO_MQF_B64 || window.MQF_LOGO_B64 || window.LOGO_B64 || null;
}
window.obtenerLogoAseguradoraB64 = obtenerLogoAseguradoraB64;

function obtenerLogoMQFB64() {
    if (typeof window !== 'undefined') {
        return window.LOGO_MQF_B64 || window.MQF_LOGO_B64 || window.LOGO_B64 || null;
    }
    return null;
}
window.obtenerLogoMQFB64 = obtenerLogoMQFB64;

async function dibujarCotizacionFianzaSimplePDF(doc, c) {
    const formatter = new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmt = (n) => 'RD$ ' + formatter.format(n || 0);

    const W = 210;
    const ML = 12;
    const MR = 198;
    const CW = 186;

    let asegName = String(c.aseguradora || c.aseguradora_nombre || 'MULTISEGUROS').toUpperCase().trim();
    if (!isNaN(asegName) && parseInt(asegName) > 0) {
      const mapId = { 1: 'MULTISEGUROS', 2: 'MIDAS SEGUROS', 3: 'SEGUROS PATRIA', 4: 'SEGUROS PEPIN' };
      if (mapId[parseInt(asegName)]) asegName = mapId[parseInt(asegName)];
    }

    let primaryRGB = [0, 51, 153];
    if (asegName.includes('MIDAS')) primaryRGB = [15, 118, 110];
    else if (asegName.includes('PATRIA')) primaryRGB = [180, 83, 9];
    else if (asegName.includes('PEPIN')) primaryRGB = [230, 80, 0];

    // Header Superior - Logo Oficial MÁS QUE FIANZAS (+QF)
    let mqfLogo = (typeof window !== 'undefined' && typeof window.obtenerLogoMQFB64 === 'function')
        ? window.obtenerLogoMQFB64()
        : (typeof obtenerLogoMQFB64 === 'function' ? obtenerLogoMQFB64() : (typeof window !== 'undefined' ? (window.LOGO_MQF_B64 || window.MQF_LOGO_B64 || window.LOGO_B64 || null) : null));
    if (mqfLogo) {
        try { doc.addImage(mqfLogo, 'PNG', ML, 7, 24, 20); } catch(e){
            try { doc.addImage(mqfLogo, 'JPEG', ML, 7, 24, 20); } catch(e2){}
        }
    } else {
        doc.setFontSize(14); doc.setFont('helvetica', 'bold'); doc.setTextColor(0, 51, 153);
        doc.text('+QUE FIANZAS', ML, 18);
    }

    doc.setFillColor(0, 51, 153);
    doc.rect(ML + 26, 10, CW - 26, 6, 'F');
    doc.setFontSize(9.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(255, 255, 255);
    doc.text('Mas Que Fianzas +QF, SRL. (Corredor de Seguros)', ML + 28, 14.2);

    doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.setTextColor(50, 50, 50);
    doc.text('Av. 27 de Febrero, Bella Vista, Distrito Nacional.', ML + 26, 20);
    doc.setFont('helvetica', 'bold');
    
    const numCot = String(c.numero || c.numero_cotizacion || c.numero_fianza || c.id || '2026-00003');
    const displayCot = numCot.startsWith('COT-') || numCot.startsWith('FZ-') ? numCot : ('Cotizacion: ' + numCot);
    doc.text(displayCot, MR, 20, { align: 'right' });

    doc.setFont('helvetica', 'normal');
    doc.text('www.masquefianzas.com', ML + 26, 25);
    const fechaFmt = c.fecha ? new Date(c.fecha).toLocaleDateString('es-DO', { weekday:'long', year:'numeric', month:'long', day:'numeric' }) : new Date().toLocaleDateString('es-DO', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    doc.text('Fecha: ' + fechaFmt, MR, 25, { align: 'right' });

    let y = 33;

    function drawSectionBar(title, yPos) {
        doc.setFillColor(primaryRGB[0], primaryRGB[1], primaryRGB[2]);
        doc.rect(ML, yPos, CW, 5.5, 'F');
        doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(255, 255, 255);
        doc.text(title, W / 2, yPos + 4, { align: 'center' });
    }

    // 1. Datos del Cliente
    drawSectionBar('Datos del Cliente', y); y += 8;
    doc.setFontSize(8); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('Razon Social: ', ML + 4, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.cliente || c.cliente_nombre || c.nombre || 'Cliente Solicitante'), ML + 24, y);

    doc.setFont('helvetica', 'bold');
    doc.text('Email: ', ML + 100, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.email || c.cliente_email || 'info@masquefianzas.com'), ML + 112, y); y += 5;

    doc.setFont('helvetica', 'bold');
    doc.text('Teléfono: ', ML + 4, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.telefono || c.cliente_telefono || '(809) 000-0000'), ML + 20, y); y += 7;

    // 2. Datos de la Fianza
    drawSectionBar('Datos de la Fianza', y); y += 8;
    doc.setFontSize(8); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('Beneficiario: ', ML + 4, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.beneficiario || 'EL BENEFICIARIO'), ML + 24, y);

    doc.setFont('helvetica', 'bold');
    doc.text('Referencia: ', ML + 100, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.objeto_referencia || c.referencia || c.numero_contrato || 'Edeeste 2025'), ML + 118, y); y += 5;

    doc.setFont('helvetica', 'bold');
    doc.text('Es a Primer Requerimiento?: ', ML + 100, y);
    doc.setFont('helvetica', 'normal');
    doc.text((c.primer_requerimiento == 1 || c.primer_requerimiento === '1' || c.primer_requerimiento === 'si') ? 'Sí' : 'No', ML + 142, y); y += 7;

    // 3. Tabla de Especificaciones de Fianza
    doc.setFillColor(160, 165, 175);
    doc.rect(ML, y, CW, 5.5, 'F');
    doc.setFontSize(7.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('Tipo de Fianza', ML + 4, y + 3.8);
    doc.text('Monto del Contrato', ML + 55, y + 3.8);
    doc.text('%', ML + 95, y + 3.8);
    doc.text('Valor a Afianzar', ML + 115, y + 3.8);
    doc.text('Tiempo a Afianzar', ML + 155, y + 3.8);
    y += 10.5;

    const tipoNombre = String(c.tipo_fianza || c.subtipo || c.tipo || 'Licitación');
    const montoContratoVal = parseFloat(c.monto_contrato || c.monto_afianzado || 10000.00);
    const porcVal = parseFloat(c.porcentaje_afianzar || c.porcentaje || 100);
    const valorAfianzadoVal = parseFloat(c.monto_afianzado || c.valor_afianzar || montoContratoVal);
    const plazoVal = parseInt(c.plazo_meses || c.tiempo || 12);

    doc.setFontSize(8.5); doc.setFont('helvetica', 'normal'); doc.setTextColor(30, 30, 30);
    doc.text(tipoNombre, ML + 4, y);
    doc.text(fmt(montoContratoVal), ML + 55, y);
    doc.text(porcVal + '%', ML + 95, y);
    doc.text(fmt(valorAfianzadoVal), ML + 115, y);
    doc.text(plazoVal + ' meses', ML + 155, y);
    y += 8.5;

    // 4. Tabla de Aseguradora y Prima
    doc.setFillColor(160, 165, 175);
    doc.rect(ML, y, CW, 5.5, 'F');
    doc.setFontSize(7.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('Aseguradora', ML + 4, y + 3.8);
    doc.text('Precio / Prima Total', ML + 115, y + 3.8);
    y += 10.5;

    const totalPrimaVal = parseFloat(c.total || c.prima_base || c.prima || 2900.00);
    doc.setFontSize(8.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text(asegName, ML + 4, y);
    doc.text(fmt(totalPrimaVal), ML + 115, y);
    y += 9.5;

    // 5. Requisitos
    drawSectionBar('Requisitos', y); y += 8;
    doc.setFontSize(7.2); doc.setFont('helvetica', 'normal'); doc.setTextColor(50, 50, 50);
    doc.text('1. Contrato, fotocopia y solicitud de fianza firmada y sellada.', ML + 4, y); y += 7;

    // 6. Notas
    drawSectionBar('Notas', y); y += 8;
    doc.setFontSize(7.0); doc.setFont('helvetica', 'normal'); doc.setTextColor(50, 50, 50);
    
    const n1 = '1. Esta cotización no aplica para fianzas a primer requerimiento para otros fines fuera de licitación o de su propuesta.';
    const ln1 = doc.splitTextToSize(n1, CW - 8);
    doc.text(ln1, ML + 4, y); y += ln1.length * 2.8 + 1;

    const n2 = '2. Esta cotización tiene una vigencia de 15 días calendario posterior a su elaboración. Hace de esta escrita la formulación de la solicitud de fianza.';
    const ln2 = doc.splitTextToSize(n2, CW - 8);
    doc.text(ln2, ML + 4, y); y += ln2.length * 2.8 + 1;

    const n3 = '3. Se reconoce que el proceso de emisión de la fianza para emitirse conlleva las previsiones de la solicitud de coberturas emitidas en las declaraciones asentadas en el proceso, para fines de la evaluación de riesgo sujeto a la solicitud de coberturas y pólizas de seguro.';
    const ln3 = doc.splitTextToSize(n3, CW - 8);
    doc.text(ln3, ML + 4, y); y += ln3.length * 2.8 + 6;

    // 7. Contacto
    drawSectionBar('Contacto', y); y += 8;
    doc.setFontSize(7.2); doc.setFont('helvetica', 'normal'); doc.setTextColor(50, 50, 50);
    doc.text('• Departamento de Servicio', ML + 4, y); y += 4;
    doc.text('• Tel. +1 (829) 450-3077, (829) 476-7992', ML + 4, y); y += 4;
    doc.text('• Email: info@masquefianzas.com', ML + 4, y);

    // QR de validación en esquina inferior derecha
    const originHost = (typeof window !== 'undefined' && window.location && window.location.origin && !window.location.origin.includes('file://'))
      ? window.location.origin
      : 'http://169.58.51.147';
    const verifyUrl = `${originHost}/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=${encodeURIComponent(numCot)}`;

    const qrDataUrl = await generarQRDataURL(verifyUrl);
    if (qrDataUrl) {
      try {
        doc.addImage(qrDataUrl, 'PNG', MR - 24, 250, 22, 22);
        doc.setFontSize(6.0); doc.setFont('helvetica', 'bold'); doc.setTextColor(80, 80, 80);
        doc.text('VALIDEZ OFICIAL QR', MR - 13, 274, { align: 'center' });
      } catch(e){}
    }
}

window.exportarCotizacionFianzaSimplePDF = async function(data) {
  try {
    if (!window.jspdf) { MQF.toast('Motor PDF no disponible', 'error'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    await dibujarCotizacionFianzaSimplePDF(doc, data);
    const num = data.numero || data.numero_cotizacion || data.id || 'COT';
    doc.save('cotizacion_fianza_' + num + '.pdf');
    if (typeof MQF !== 'undefined' && MQF.toast) MQF.toast('✅ PDF de Cotización generado: cotizacion_fianza_' + num + '.pdf', 'success');
  } catch(e) {
    console.error('Error generando PDF de cotización:', e);
    if (typeof MQF !== 'undefined' && MQF.toast) MQF.toast('Error al generar PDF: ' + e.message, 'error');
  }
};

window.exportarBorradorFianzaPDF = async function(data) {
  const copy = Object.assign({}, data, { es_borrador: true });
  return await generarPDFFianza(copy);
};

window.exportarPolizaFianzaVigentePDF = async function(data) {
  const copy = Object.assign({}, data, { es_borrador: false });
  return await generarPDFFianza(copy);
};

window.generarPDFFianza = generarPDFFianza;
window.MQF_generarPDFFianza = generarPDFFianza;

async function dibujarCotizacionPDF(doc, c, logoImg, printWindow) {
    const subLower = String(c.subtipo || c.tipo_fianza || c.tipo_nombre || c.tipo || '').toLowerCase();
    const catLower = String(c.categoria || c.categoria_nombre || '').toLowerCase();
    const isFianza = c.tipo === 'FIANZA' || 
                     c.tipo === 'fianza' || 
                     catLower.includes('fianza') || 
                     subLower.includes('fianza') || 
                     subLower.includes('licita') || 
                     subLower.includes('licitación') || 
                     subLower.includes('anticipo') || 
                     subLower.includes('fiel') || 
                     subLower.includes('cumplimiento') || 
                     subLower.includes('vicios') || 
                     subLower.includes('marítima') || 
                     subLower.includes('maritima') || 
                     subLower.includes('judicial') || 
                     subLower.includes('aduanal') || 
                     String(c.numero || c.numero_cotizacion || '').startsWith('FZ-');

    if (isFianza) {
        return await dibujarCotizacionFianzaSimplePDF(doc, c);
    }
    const cfg = getInstitutionalData();
    const formatter = new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmt = (n) => 'RD$ ' + formatter.format(n || 0);
    const esSeguroLey = c.tipo && c.tipo.toUpperCase().includes('SEGURO');

    // ── Colores por aseguradora ────────────────────
    const asegName = (c.aseguradora || '').toUpperCase().trim();
    let primaryRGB = [230, 80, 0];
    let primaryHex = '#E65000';

    if (asegName.includes('MULTI') || asegName.includes('1')) {
        primaryRGB = [0, 51, 153]; primaryHex = '#003399';
    } else if (asegName.includes('MIDAS') || asegName.includes('2')) {
        primaryRGB = [15, 118, 110]; primaryHex = '#0f766e';
    } else if (asegName.includes('PATRIA') || asegName.includes('3')) {
        primaryRGB = [180, 83, 9]; primaryHex = '#b45309';
    } else if (asegName.includes('PEPIN') || asegName.includes('PEPI') || asegName.includes('4')) {
        primaryRGB = [230, 80, 0]; primaryHex = '#E65000';
    }

    const W = 210;
    const ML = 12;
    const MR = 198;
    const CW = 186;

    // Header superior
    let logoData = window.LOGO_MQF_B64 || window.MQF_LOGO_B64 || window.LOGO_B64 || logoImg;
    if (logoData) {
        try { doc.addImage(logoData, 'PNG', ML, 10, 22, 19.8); } catch(e){}
    } else {
        doc.setFontSize(14); doc.setFont('helvetica', 'bold'); doc.setTextColor(primaryRGB[0], primaryRGB[1], primaryRGB[2]);
        doc.text('+QUE FIANZAS', ML, 18);
    }

    doc.setFillColor(primaryRGB[0], primaryRGB[1], primaryRGB[2]);
    doc.rect(ML + 26, 10, CW - 26, 6, 'F');
    doc.setFontSize(9.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(255, 255, 255);
    doc.text('Mas Que Fianzas +QF, SRL. (Cotización de ' + (esSeguroLey ? 'Seguro' : 'Servicio') + ')', ML + 28, 14.2);

    doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.setTextColor(50, 50, 50);
    doc.text('Av. 27 de Febrero, Bella Vista, Distrito Nacional.', ML + 26, 20);
    doc.setFont('helvetica', 'bold');
    doc.text('Cotizacion: ' + (c.numero || 'COT-0001'), MR, 20, { align: 'right' });

    doc.setFont('helvetica', 'normal');
    doc.text('www.masquefianzas.com', ML + 26, 25);
    const fechaFmt = c.fecha ? new Date(c.fecha).toLocaleDateString('es-DO', { weekday:'long', year:'numeric', month:'long', day:'numeric' }) : new Date().toLocaleDateString('es-DO');
    doc.text('Fecha: ' + fechaFmt, MR, 25, { align: 'right' });

    let y = 33;

    function drawHeaderSection(title, yPos) {
        doc.setFillColor(primaryRGB[0], primaryRGB[1], primaryRGB[2]);
        doc.rect(ML, yPos, CW, 5.5, 'F');
        doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(255, 255, 255);
        doc.text(title, W / 2, yPos + 4, { align: 'center' });
    }

    // Datos del Cliente
    drawHeaderSection('Datos del Cliente', y); y += 8;
    doc.setFontSize(8); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('Razon Social: ', ML + 4, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.cliente || c.nombre || 'Cliente General'), ML + 26, y); y += 5;

    doc.setFont('helvetica', 'bold');
    doc.text('Teléfono: ', ML + 4, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.telefono || '(809) 000-0000'), ML + 20, y);

    doc.setFont('helvetica', 'bold');
    doc.text('Email: ', ML + 90, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.email || 'info@masquefianzas.com'), ML + 102, y); y += 7;

    // Datos de la Cotización
    drawHeaderSection('Detalles de la Cotización', y); y += 8;
    doc.setFontSize(8); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('Producto: ', ML + 4, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.subtipo || c.tipo || 'Cotización de Ley'), ML + 22, y);

    doc.setFont('helvetica', 'bold');
    doc.text('Aseguradora: ', ML + 90, y);
    doc.setFont('helvetica', 'normal');
    doc.text(String(c.aseguradora || 'MultiSeguros'), ML + 112, y); y += 7;

    // Tabla de Precios
    doc.setFillColor(160, 165, 175);
    doc.rect(ML, y, CW, 6.5, 'F');
    doc.setFontSize(8); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text('Concepto / Cobertura', ML + 4, y + 4.5);
    doc.text('Precio / Prima Total', ML + 120, y + 4.5);
    y += 9.5;

    doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text(String(c.subtipo || c.tipo || 'Seguro'), ML + 4, y);
    doc.text(fmt(c.total || c.prima || 0), ML + 120, y);
    y += 11;

    // Notas y Términos
    drawHeaderSection('Términos y Condiciones', y); y += 8;
    doc.setFontSize(7.2); doc.setFont('helvetica', 'normal'); doc.setTextColor(50, 50, 50);

    const term1 = '1. Esta cotización tiene una validez de 15 días calendario a partir de su fecha de emisión.';
    const lt1 = doc.splitTextToSize(term1, CW - 8);
    doc.text(lt1, ML + 4, y); y += lt1.length * 3.0 + 2;

    const term2 = '2. El régimen impositivo aplicado corresponde al 16% de Impuesto sobre Seguros (Ley 146-02).';
    const lt2 = doc.splitTextToSize(term2, CW - 8);
    doc.text(lt2, ML + 4, y); y += lt2.length * 3.0 + 2;

    // QR de validación en esquina inferior derecha
    const originHost = (typeof window !== 'undefined' && window.location && window.location.origin && !window.location.origin.includes('file://'))
      ? window.location.origin
      : 'http://169.58.51.147';
    const verifyUrl = `${originHost}/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=${encodeURIComponent(c.numero || c.id || 'COT')}`;

    const qrDataUrl = await generarQRDataURL(verifyUrl);
    if (qrDataUrl) {
        try {
            doc.addImage(qrDataUrl, 'PNG', MR - 24, y + 5, 22, 22);
            doc.setFontSize(6.0); doc.setFont('helvetica', 'bold'); doc.setTextColor(80, 80, 80);
            doc.text('VALIDEZ OFICIAL QR', MR - 13, y + 29, { align: 'center' });
        } catch(e){}
    }

    y += 34;

    drawHeaderSection('Contacto:', y); y += 8;
    doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.setTextColor(40, 40, 40);
    doc.text('•  Departamento de Ventas y Atención al Cliente', ML + 4, y); y += 4.5;
    doc.text('•  Tlf. +1 (829) 450-1077 /+ (829) 629-1952', ML + 4, y); y += 4.5;
    doc.text('•  Email. info@masquefianzas.com', ML + 4, y);

    const fileName = c.numero ? `${c.numero}.pdf` : 'cotizacion_mqf.pdf';
    try { doc.save(fileName); }
    catch (e) { console.error('Error al descargar PDF:', e); }
}

function getInstitutionalData() {
    return (window.parent && window.parent.MQF_CONFIG) || window.MQF_CONFIG || {
        empresa_nombre: 'MAS QUE FIANZAS',
        empresa_rnc: '133-53573-4',
        empresa_correo: 'info@masquefianzas.com',
        empresa_direccion: 'Ave. 27 de febrero #234, Suite-304, La esperilla, Santo Domingo. DN.',
        empresa_telefono: '+1 (829) 629-1952',
        empresa_web: 'https://www.masquefianzas.com.do',
        empresa_redes: {}
    };
}

// ======== EXPORTACIÓN ========

function exportarListado(formato, modulo = 'clientes') {
    let rawData = [];
    let titulo = '';
    let nombreBase = '';
    let datosListos = [];
    
    const fechaStr = new Date().toISOString().split('T')[0];

    if (modulo === 'clientes') {
        if (!window.clientesData || window.clientesData.length === 0) { MQF.toast('No hay datos de clientes para exportar.', 'warning'); return; }
        rawData = window.clientesData;
        titulo = 'Directorio de Clientes';
        nombreBase = `Listado_Clientes_${fechaStr}`;
        datosListos = rawData.map(c => ({
            "ID": c.id,
            "Tipo": c.tipo_persona,
            "Nombre / Razón Social": c.nombre_razon_social,
            "RNC / Cédula": c.rnc,
            "Teléfono": c.telefono || 'N/A',
            "Estatus": c.estatus
        }));
    } else if (modulo === 'usuarios') {
        if (!window.usuariosData || window.usuariosData.length === 0) { MQF.toast('No hay datos de usuarios para exportar.', 'warning'); return; }
        rawData = window.usuariosData;
        titulo = 'Listado de Usuarios';
        nombreBase = `Listado_Usuarios_${fechaStr}`;
        datosListos = rawData.map(u => ({
            "ID": u.id,
            "Username": u.username,
            "Nombre": u.nombre + ' ' + u.apellido,
            "Email": u.email,
            "Perfil": u.nombre_perfil || 'N/A',
            "Estado": u.estado,
            "Creado": u.fecha_creacion ? new Date(u.fecha_creacion).toLocaleDateString() : 'N/A'
        }));
    } else if (modulo === 'cotizaciones') {
        if (!window.cotizacionesData || window.cotizacionesData.length === 0) { MQF.toast('No hay datos de cotizaciones para exportar.', 'warning'); return; }

        rawData = window.cotizacionesData;
        titulo = 'Historial de Cotizaciones';
        nombreBase = `Listado_Cotizaciones_${fechaStr}`;
        datosListos = rawData.map(c => ({
            "N° Cotizacion": c.numero || 'N/A',
            "Tipo": c.tipo || 'N/A',
            "Ramo / Subtipo": c.subtipo || 'N/A',
            "Cliente": c.cliente || 'N/A',
            "Cédula / RNC": c.cedula || 'N/A',
            "Monto Base": c.monto_afianzado || c.suma_asegurada || 'N/A',
            "Prima Total": c.total || c.prima_total || 'N/A',
            "Fecha Emisión": c.fecha ? new Date(c.fecha).toLocaleDateString() : 'N/A'
        }));
    }

    switch (formato) {
        case 'excel': exportarAExcel(datosListos, nombreBase + '.xlsx'); break;
        case 'csv': exportarAExcel(datosListos, nombreBase + '.csv', true); break;
        case 'json': exportarAJSON(rawData, nombreBase + '.json'); break;
        case 'pdf': exportarAPDF(datosListos, nombreBase + '.pdf', titulo); break;
        case 'zip': exportarAZIP(rawData, nombreBase + '.zip'); break;
        default: MQF.toast('Formato no soportado', 'error');
    }
    
    const exportMenu = document.getElementById('exportMenu');
    if (exportMenu) exportMenu.style.display = 'none';
}

async function imprimirItem(id, modulo = 'clientes') {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    if (modulo === 'clientes') {
        if (!window.clientesData) return;
        const c = window.clientesData.find(x => x.id == id);
        if (!c) { MQF.toast('Cliente no encontrado', 'error'); return; }
        
        doc.setFontSize(20); doc.setTextColor(41, 128, 185); doc.text('Ficha de Cliente', 14, 22);
        doc.setFontSize(10); doc.setTextColor(100); doc.text(`Generado el: ${new Date().toLocaleDateString()}`, 14, 30);
        doc.setDrawColor(200); doc.line(14, 35, 196, 35);
        
        let y = 45;
        const linea = (label, value) => {
            doc.setFontSize(12); doc.setTextColor(40);
            doc.setFont('helvetica', 'bold'); doc.text(label + ":", 14, y);
            doc.setFont('helvetica', 'normal'); doc.text(value ? value.toString() : 'N/A', 60, y);
            y += 10;
        };
        linea('ID Cliente', c.id);
        linea('Tipo de Persona', c.tipo_persona);
        linea('Nombre / Razón', c.nombre_razon_social);
        linea('RNC / Cédula', c.rnc);
        linea('Teléfono', c.telefono);
        linea('Estatus', c.estatus);
        doc.autoPrint(); 
        
        try {
            const blob = doc.output('blob');
            const url = URL.createObjectURL(blob);
            const win = window.open(url, '_blank');
            if (!win || win.closed || typeof win.closed === 'undefined') {
                throw new Error('Popup blocked');
            }
        } catch (e) {
            console.warn('El navegador bloqueó la ventana emergente de impresión, descargando directamente...', e);
            doc.save(`Ficha_Cliente_${c.id}.pdf`);
        }
        
    } else if (modulo === 'cotizaciones') {
        if (!window.cotizacionesData) return;
        const c = window.cotizacionesData.find(x => x.numero == id);
        if (!c) { MQF.toast('Cotización no encontrada', 'error'); return; }
        
        await dibujarCotizacionPDF(doc, c, window.LOGO_MQF_B64 || null, null);
    }
}

async function dibujarCotizacionPDF(doc, c, logoImg, printWindow) {
    const subLower = String(c.subtipo || c.tipo_fianza || c.tipo || '').toLowerCase();
    const isVehicleProduct = subLower.includes('moto') || subLower.includes('auto') || subLower.includes('vehic') || subLower.includes('jeep') || subLower.includes('camion') || subLower.includes('van') || subLower.includes('bus') || subLower.includes('pesado') || subLower.includes('ley');

    if (!isVehicleProduct && ((c.tipo && c.tipo.toUpperCase().includes('FIANZA')) || (c.subtipo && String(c.subtipo).toLowerCase().includes('fianza')))) {
        if (typeof generarPDFFianza === 'function') {
            generarPDFFianza(c);
            return;
        }
    }
    const cfg = getInstitutionalData();
    const formatter = new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmt = (n) => 'RD$ ' + formatter.format(n || 0);
    const esSeguroLey = c.tipo && c.tipo.toUpperCase().includes('SEGURO');

    // ── Colores por aseguradora (color primario + rgb array) ────────────────────
    const asegName = (c.aseguradora || '').toUpperCase().trim();
    let primaryRGB = [230, 80, 0];      // Naranja por defecto (Pepín)
    let primaryHex = '#E65000';

    if (asegName.includes('MIDAS')) {
        primaryRGB = [22, 163, 74];     primaryHex = '#16A34A'; // Verde Midas
    } else if (asegName.includes('PATRIA')) {
        primaryRGB = [180, 30, 30];     primaryHex = '#B41E1E'; // Rojo Patria
    } else if (asegName.includes('MULTI')) {
        primaryRGB = [37, 99, 235];     primaryHex = '#2563EB'; // Azul MultiSeguros
    } else if (asegName.includes('PEP')) {
        primaryRGB = [230, 80, 0];      primaryHex = '#E65000'; // Naranja Pepín
    }

    const PAGE_W = 210;
    const PAGE_H = 297;
    const ML = 14;       // margen izquierdo
    const MR = 196;      // margen derecho
    const CW = MR - ML;  // ancho contenido

    // ══════════════════════════════════════════════════════════════════
    // CABECERA: Logo MQF (izq) + Tarjeta aseguradora (der)
    // ══════════════════════════════════════════════════════════════════
    let yHead = 14;

    // Logo principal MQF Oficial (+QUE FIANZAS) - Proporción exacta (1.111:1)
    const logoMQF = logoImg || window.LOGO_MQF_B64 || window.MQF_LOGO_B64 || window.LOGO_B64 || null;
    if (logoMQF) {
        try { doc.addImage(logoMQF, 'PNG', ML, yHead, 22, 19.8, undefined, 'FAST'); }
        catch (e) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(18); doc.setTextColor(...primaryRGB);
            doc.text('MÁS QUE FIANZAS', ML, yHead + 13);
        }
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(18); doc.setTextColor(...primaryRGB);
        doc.text('MÁS QUE FIANZAS', ML, yHead + 13);
    }

    // Subtítulo bajo el logo (posicionado debajo del margen inferior del logo sin solapamiento)
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8); doc.setTextColor(120, 120, 120);
    doc.text('CORE ASEGURADOR V3.0 \u2022 COTIZACIÓN DIGITAL', ML, yHead + 24);

    // Tarjeta aseguradora (esquina superior derecha)
    const cardX = 140; const cardY = yHead; const cardW = 56; const cardH = 22;
    doc.setDrawColor(200, 200, 200); doc.setLineWidth(0.4);
    doc.roundedRect(cardX, cardY, cardW, cardH, 3, 3, 'S');

    // Logo de la aseguradora dentro de la tarjeta (normalizado contra acentos y mayúsculas)
    let logoAseg = null;
    if (window.LOGOS) {
        const cleanStr = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        const normKey = cleanStr(asegName);
        if (window.LOGOS[asegName]) {
            logoAseg = window.LOGOS[asegName];
        } else {
            const fk = Object.keys(window.LOGOS).find(k => {
                const normK = cleanStr(k);
                return normKey.includes(normK) || normK.includes(normKey);
            });
            if (fk) logoAseg = window.LOGOS[fk];
        }
    }

    const ASEG_DISPLAY_NAMES = {
        MIDAS: 'Midas Seguros', PATRIA: 'Seguros Patria',
        MULTI: 'MultiSeguros', MULTISEGUROS: 'MultiSeguros',
        'SEGUROS PEPÍN': 'Seguros Pepín', PEP: 'Seguros Pepín', PEPIN: 'Seguros Pepín'
    };
    const asegDisplayName = Object.keys(ASEG_DISPLAY_NAMES).find(k => asegName.includes(k))
        ? ASEG_DISPLAY_NAMES[Object.keys(ASEG_DISPLAY_NAMES).find(k => asegName.includes(k))]
        : (c.aseguradora || 'Aseguradora');

    let drawnLogo = false;
    if (logoAseg) {
        try {
            const imgFmt = logoAseg.startsWith('data:image/jpeg') ? 'JPEG' : 'PNG';
            
            // Cargar dimensiones del logo de manera asíncrona para evitar distorsiones
            const dims = await new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve({ w: img.naturalWidth, h: img.naturalHeight });
                img.onerror = () => resolve({ w: 0, h: 0 });
                img.src = logoAseg;
            });
            
            if (dims.w > 0 && dims.h > 0) {
                const maxW = 24; 
                const maxH = 14;
                const r = dims.w / dims.h;
                let lw = maxW;
                let lh = maxH;
                if (r > (maxW / maxH)) {
                    lw = maxW;
                    lh = maxW / r;
                } else {
                    lh = maxH;
                    lw = maxH * r;
                }
                const lx = cardX + 3 + (maxW - lw) / 2;
                const ly = cardY + 4 + (maxH - lh) / 2;
                doc.addImage(logoAseg, imgFmt, lx, ly, lw, lh, undefined, 'FAST');
                drawnLogo = true;
            }
        } catch (e) {
            console.warn('Error al procesar logo de la aseguradora:', e);
        }
    }

    doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.setTextColor(40, 40, 40);
    if (drawnLogo) {
        doc.text(asegDisplayName, cardX + 29, cardY + 13);
    } else {
        doc.text(asegDisplayName, cardX + 6, cardY + 13);
    }

    // Línea divisoria naranja/color marca
    const yDivider = yHead + 28;
    doc.setDrawColor(...primaryRGB); doc.setLineWidth(1.2);
    doc.line(ML, yDivider, MR, yDivider);

    // ══════════════════════════════════════════════════════════════════
    // SECCIÓN 2 COLUMNAS: Información del Asegurado | Datos del Vehículo
    // ══════════════════════════════════════════════════════════════════
    const yInfoStart = yDivider + 8;
    const colMid = ML + CW / 2 + 5;   // separación entre columnas

    // Títulos sección
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9); doc.setTextColor(...primaryRGB);
    doc.text('INFORMACIÓN DEL ASEGURADO', ML, yInfoStart);
    doc.text('DATOS DEL VEHÍCULO', colMid, yInfoStart);

    // Línea bajo títulos sección (gris sutil)
    doc.setDrawColor(200, 200, 200); doc.setLineWidth(0.3);
    doc.line(ML, yInfoStart + 2, MR, yInfoStart + 2);

    // Filas de datos del asegurado (columna izquierda)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9);
    const renderRow = (label, value, x, y) => {
        doc.setTextColor(100, 100, 100); doc.text(label + ':', x, y);
        doc.setTextColor(40, 40, 40); doc.setFont('helvetica', 'bold');
        doc.text(value || 'N/A', x + 24, y);
        doc.setFont('helvetica', 'normal');
    };

    let yInfo = yInfoStart + 8;
    renderRow('Nombre', c.cliente || 'A QUIEN CORRESPONDA', ML, yInfo);
    yInfo += 6;
    renderRow('Correo', c.email || '-', ML, yInfo);
    if (c.cedula) { yInfo += 6; renderRow('Cédula', c.cedula, ML, yInfo); }

    // Datos del Vehículo (columna derecha)
    const vDescripcion = [c.subtipo, c.marca, c.modelo, c.anio].filter(Boolean).join(' ') || (c.subtipo || c.tipo || 'VEHÍCULO');
    let yVeh = yInfoStart + 8;
    renderRow('Descripción', vDescripcion, colMid, yVeh);          yVeh += 6;
    renderRow('Tipo', c.subtipo || c.tipo || '-', colMid, yVeh);   yVeh += 6;
    renderRow('Uso', c.uso || '-', colMid, yVeh);                  yVeh += 6;
    renderRow('Capacidad', c.capacidad || '-', colMid, yVeh);

    // ══════════════════════════════════════════════════════════════════
    // TABLA DE PRODUCTOS
    // ══════════════════════════════════════════════════════════════════
    const yTableStart = Math.max(yInfo, yVeh) + 10;

    // Encabezado tabla (fondo gris suave)
    doc.setFillColor(240, 240, 240);
    doc.rect(ML, yTableStart, CW, 7, 'F');
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.setTextColor(80, 80, 80);
    doc.text('DESCRIPCIÓN DEL PRODUCTO', ML + 2, yTableStart + 4.8);
    doc.text('TIPO COBERTURA', ML + 95, yTableStart + 4.8, { align: 'center' });
    doc.text('PRECIO (RD$)', MR - 2, yTableStart + 4.8, { align: 'right' });

    // Cálculos de prima
    const primaBase = parseFloat(c.prima_base || c.total || 0);
    const primaNet  = Math.round((primaBase / 1.16) * 100) / 100;
    const iscVal    = Math.round((primaBase - primaNet) * 100) / 100;
    const totalVal  = parseFloat(c.total || c.prima_total || primaBase);
    const costoOpcionales = Math.round((totalVal - primaBase) * 100) / 100;
    const cobertura = c.cobertura || 'BÁSICO';

    // Fila 1: Seguro de Ley
    let yRow = yTableStart + 7;
    doc.setFillColor(255, 255, 255); doc.rect(ML, yRow, CW, 12, 'F');
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(30, 30, 30);
    doc.text('SEGURO DE LEY OBLIGATORIO', ML + 2, yRow + 5);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(120, 120, 120);
    doc.text('Seguro de daños a terceros según ley 146-02.', ML + 2, yRow + 10);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(60, 60, 60);
    doc.text(cobertura, ML + 95, yRow + 7, { align: 'center' });
    doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text(fmt(primaNet), MR - 2, yRow + 7, { align: 'right' });

    // Separador sutil
    yRow += 12;
    doc.setDrawColor(220, 220, 220); doc.setLineWidth(0.2); doc.line(ML, yRow, MR, yRow);

    // Fila 2: Impuesto
    doc.setFillColor(252, 252, 252); doc.rect(ML, yRow, CW, 12, 'F');
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(30, 30, 30);
    doc.text('IMPUESTO (16% Ley 146-02)', ML + 2, yRow + 5);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(120, 120, 120);
    doc.text('Tasas impositivas de seguros aplicadas.', ML + 2, yRow + 10);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(60, 60, 60);
    doc.text('Incluido', ML + 95, yRow + 7, { align: 'center' });
    doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
    doc.text(fmt(iscVal), MR - 2, yRow + 7, { align: 'right' });

    // Fila 3: Servicios Opcionales (si aplica)
    if (costoOpcionales > 0) {
        yRow += 12;
        doc.setDrawColor(220, 220, 220); doc.setLineWidth(0.2); doc.line(ML, yRow, MR, yRow);
        doc.setFillColor(255, 255, 255); doc.rect(ML, yRow, CW, 12, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(30, 30, 30);
        doc.text('SERVICIOS OPCIONALES ADICIONALES', ML + 2, yRow + 5);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(120, 120, 120);
        doc.text('Servicios de asistencia opcionales contratados.', ML + 2, yRow + 10);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(60, 60, 60);
        doc.text('Opcional', ML + 95, yRow + 7, { align: 'center' });
        doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 30, 30);
        doc.text(fmt(costoOpcionales), MR - 2, yRow + 7, { align: 'right' });
    }

    // Separador
    yRow += 12;
    doc.setDrawColor(...primaryRGB); doc.setLineWidth(0.6); doc.line(ML, yRow, MR, yRow);

    // TOTAL ANUAL ESTIMADO
    yRow += 1;
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(...primaryRGB);
    doc.text('TOTAL ANUAL ESTIMADO', ML + 2, yRow + 6);
    doc.text(fmt(totalVal), MR - 2, yRow + 6, { align: 'right' });

    // ── SECCIÓN: LÍMITES Y COBERTURAS ──────────────────────────────────
    yRow += 14;

    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(...primaryRGB);
    doc.text('LÍMITES Y COBERTURAS SEGURO DE LEY (RD$)', ML, yRow);
    yRow += 4;

    // Coberturas simplificadas para evitar solapes y cumplir con el diseño exacto
    const COVERAGE_GRID = {
        'MOTOCICLETA BASICO': [
            { label: 'Daños Propiedad Ajena', val: 50000 },
            { label: 'Lesiones Personales (1 pers)', val: 50000 },
            { label: 'Lesiones Personales (2+ pers)', val: 100000 },
            { label: 'Fianza Judicial', val: 50000 }
        ],
        'LIVIANO BASICO': [
            { label: 'Daños Propiedad Ajena', val: 100000 },
            { label: 'Lesiones Personales (1 pers)', val: 100000 },
            { label: 'Lesiones Personales (2+ pers)', val: 200000 },
            { label: 'Fianza Judicial', val: 20000 },
            { label: 'Daños al Conductor', val: 20000 },
            { label: 'Daños a Pasajeros', val: 20000 }
        ],
        'PESADO PLUS': [
            { label: 'Daños Propiedad Ajena', val: 300000 },
            { label: 'Lesiones Personales (1 pers)', val: 300000 },
            { label: 'Lesiones Personales (2+ pers)', val: 600000 },
            { label: 'Fianza Judicial', val: 500000 },
            { label: 'Daños al Conductor', val: 50000 },
            { label: 'Daños a Pasajeros', val: 50000 }
        ]
    };

    const gridItems = COVERAGE_GRID[cobertura] || COVERAGE_GRID['LIVIANO BASICO'];
    const gridRows  = Math.ceil(gridItems.length / 2);
    const gridH_inner = gridRows * 7;
    
    // Altura total de la tarjeta contenedor
    const cardTotalH = 4 + gridH_inner + 4;

    // Dibujar tarjeta con bordes redondeados y fondo azul-gris sutil
    doc.setFillColor(248, 250, 252); doc.setDrawColor(226, 232, 240); doc.setLineWidth(0.35);
    doc.roundedRect(ML, yRow, CW, cardTotalH, 4, 4, 'FD');

    // Renderizar cuadrícula en 2 columnas con líneas punteadas
    const colLeft  = ML + 4;
    const colLeftVal = ML + 86;
    const colRightStart = ML + 96;
    const colRightVal = MR - 4;
    const formatter2 = new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtLim = (n) => n > 0 ? 'RD$ ' + formatter2.format(n) : 'N/A';

    let gY = yRow + 4.5 + 4; // Ajuste de Y inicial
    const getW = (txt) => doc.getTextWidth(txt);

    for (let i = 0; i < gridItems.length; i += 2) {
        const left  = gridItems[i];
        const right = gridItems[i + 1];

        // Fila izquierda: Etiqueta y Valor
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(71, 85, 105);
        doc.text(left.label, colLeft, gY);
        
        doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 41, 59);
        const leftValStr = fmtLim(left.val);
        doc.text(leftValStr, colLeftVal, gY, { align: 'right' });

        // Línea punteada de conector para columna izquierda
        doc.setLineWidth(0.2); doc.setDrawColor(203, 213, 225); doc.setLineDash([0.6, 0.8], 0);
        const lLineStart = colLeft + getW(left.label) + 1.5;
        const lLineEnd = colLeftVal - getW(leftValStr) - 1.5;
        if (lLineEnd > lLineStart) {
            doc.line(lLineStart, gY - 0.8, lLineEnd, gY - 0.8);
        }
        doc.setLineDash([], 0); // Restablecer estilo

        if (right) {
            // Fila derecha: Etiqueta y Valor
            doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(71, 85, 105);
            doc.text(right.label, colRightStart, gY);
            
            doc.setFont('helvetica', 'bold'); doc.setTextColor(30, 41, 59);
            const rightValStr = fmtLim(right.val);
            doc.text(rightValStr, colRightVal, gY, { align: 'right' });

            // Línea punteada de conector para columna derecha
            doc.setLineWidth(0.2); doc.setDrawColor(203, 213, 225); doc.setLineDash([0.6, 0.8], 0);
            const rLineStart = colRightStart + getW(right.label) + 1.5;
            const rLineEnd = colRightVal - getW(rightValStr) - 1.5;
            if (rLineEnd > rLineStart) {
                doc.line(rLineStart, gY - 0.8, rLineEnd, gY - 0.8);
            }
            doc.setLineDash([], 0); // Restablecer estilo
        }
        gY += 7;
    }

    // Avanzar la Y para la siguiente sección
    yRow += cardTotalH + 8;

    // ══════════════════════════════════════════════════════════════════
    // FOOTER: Nº Cotización + Fecha + Nota NOFTRAB + QR
    // ══════════════════════════════════════════════════════════════════
    const yFooter = PAGE_H - 38;
    doc.setDrawColor(200, 200, 200); doc.setLineWidth(0.3);
    doc.line(ML, yFooter, MR, yFooter);

    // Número de cotización y fecha (utilizando caracteres estándar)
    const fechaGen = new Date();
    const fechaFmt = fechaGen.toLocaleDateString('es-DO') + ' ' + fechaGen.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(40, 40, 40);
    doc.text(`Nº Cotización: ${c.numero || 'COT-TEMP'} | Fecha Generación: ${fechaFmt}`, ML, yFooter + 6);

    // Texto NOFTRAB (libre de emojis Unicode para evitar errores de codificación en jsPDF)
    const notaText = 'Validez: Esta cotización es válida por 30 días desde su fecha de emisión y está sujeta a los términos y condiciones de la póliza de la aseguradora. No constituye un contrato de seguro ni póliza emitida hasta tanto no sea formalizada y pagada. Cumple con la norma de auditoría inmutable NOFTRAB v4.0.';
    const notaLines = doc.splitTextToSize(notaText, CW - 42);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(100, 100, 100);
    doc.text(notaLines, ML, yFooter + 12);

    // QR de validación en esquina inferior derecha (Host dinámico activo VPS o dominio)
    let originHost = (typeof window !== 'undefined' && window.location && window.location.origin && !window.location.origin.includes('file://'))
      ? window.location.origin
      : 'http://169.58.51.147';
    if (!originHost || originHost.includes('localhost') || originHost.includes('127.0.0.1')) {
        originHost = 'http://169.58.51.147';
    }
    const qrUrl = `${originHost}/frontend/modulos/validar.html?cot=${encodeURIComponent(c.numero || 'TEMP')}`;
    try {
        let qrImg = null;
        if (typeof window.generarQRDataURL === 'function') {
            qrImg = await window.generarQRDataURL(qrUrl);
        } else if (typeof generarQRDataURL === 'function') {
            qrImg = await generarQRDataURL(qrUrl);
        }
        if (qrImg) {
            doc.addImage(qrImg, 'PNG', MR - 28, yFooter + 4, 28, 28);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(6.5); doc.setTextColor(80, 80, 80);
            doc.text('VALIDACIÓN OFICIAL', MR - 14, yFooter + 35, { align: 'center' });
        }
    } catch (e) {
        console.warn('QR no disponible:', e);
    }

    // Guardar PDF con nombre basado en número de cotización
    const fileName = c.numero ? `${c.numero}.pdf` : 'cotizacion_mqf.pdf';
    try { doc.save(fileName); }
    catch (e) { console.error('Error al descargar PDF:', e); }
}


// Helpers
function exportarAExcel(datos, filename, isCsv = false) {
    if (typeof XLSX === 'undefined') { MQF.toast('Librería SheetJS no encontrada', 'error'); return; }
    const ws = XLSX.utils.json_to_sheet(datos);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Datos");
    if (isCsv) XLSX.writeFile(wb, filename, { bookType: "csv" });
    else XLSX.writeFile(wb, filename);
}

function exportarAJSON(datos, filename) {
    const jsonStr = JSON.stringify(datos, null, 2);
    const blob = new Blob([jsonStr], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function exportarAPDF(datos, filename, titulo) {
    if (typeof window.jspdf === 'undefined') { MQF.toast('Librería jsPDF no encontrada', 'error'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.text(titulo, 14, 15);
    const headers = Object.keys(datos[0]);
    const body = datos.map(row => Object.values(row));
    
    doc.autoTable({
        startY: 20,
        head: [headers],
        body: body,
        theme: 'striped',
        headStyles: { fillColor: [41, 128, 185] }
    });
    doc.save(filename);
}

function exportarAZIP(datos, filename) {
    if (typeof JSZip === 'undefined') { MQF.toast('Librería JSZip no encontrada', 'error'); return; }
    const zip = new JSZip();
    zip.file("backup_datos.json", JSON.stringify(datos, null, 2));
    
    if (typeof XLSX !== 'undefined') {
        const ws = XLSX.utils.json_to_sheet(datos);
        const csvText = XLSX.utils.sheet_to_csv(ws);
        zip.file("backup_datos.csv", csvText);
    }
    
    zip.generateAsync({ type: "blob" }).then(function(content) {
        const url = URL.createObjectURL(content);
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}


// ======== MOTOR DE IMPORTACIÓN Y ASISTENTE PREMIUM EN 3 PASOS ========

// Estilos dinámicos para el asistente premium de importación
function inyectarEstilosAsistente() {
    if (document.getElementById('import-wizard-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'import-wizard-styles';
    style.textContent = `
        .import-wizard-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px);
            z-index: 99999; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif; color: #1e293b;
            opacity: 0; transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .import-wizard-overlay.show { opacity: 1; }
        .import-wizard-container {
            background: var(--bg-card, #ffffff); width: 95%; max-width: 750px; border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;
            transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; max-height: 90vh;
        }
        .import-wizard-overlay.show .import-wizard-container { transform: scale(1); }
        .import-wizard-header {
            background: linear-gradient(135deg, #1e293b, #0f172a); padding: 18px 24px;
            color: #ffffff; display: flex; justify-content: space-between; align-items: center;
        }
        .import-wizard-header h3 { margin: 0; font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .import-wizard-close {
            background: rgba(255, 255, 255, 0.1); border: none; color: #e2e8f0;
            font-size: 1.25rem; width: 32px; height: 32px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .import-wizard-close:hover { background: rgba(255, 255, 255, 0.25); color: #ffffff; }
        .import-wizard-steps-indicator { display: flex; background: #f8fafc; padding: 12px 24px; border-bottom: 1px solid #e2e8f0; gap: 12px; }
        .import-wizard-step-pill {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700;
            color: #64748b; background: #f1f5f9; transition: all 0.25s ease;
        }
        .import-wizard-step-pill.active { color: #ffffff; background: #4f46e5; }
        .import-wizard-step-pill.completed { color: #15803d; background: #dcfce7; }
        .import-wizard-body { padding: 24px; overflow-y: auto; flex: 1; }
        .import-wizard-view { display: none; }
        .import-wizard-view.active { display: block; }
        .import-wizard-dropzone {
            border: 2px dashed #cbd5e1; border-radius: 12px; padding: 32px 20px;
            text-align: center; cursor: pointer; transition: all 0.2s ease;
            background: #f8fafc; display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .import-wizard-dropzone:hover, .import-wizard-dropzone.dragover { border-color: #4f46e5; background: #eef2ff; }
        .import-wizard-dropzone-icon { font-size: 2.25rem; color: #4f46e5; }
        .import-wizard-instructions {
            background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px 18px;
            border-radius: 0 8px 8px 0; margin-bottom: 20px; font-size: 0.88rem; line-height: 1.45;
        }
        .import-wizard-instructions h4 { margin: 0 0 6px 0; color: #1e3a8a; font-weight: 700; }
        .import-wizard-instructions ul { margin: 0; padding-left: 18px; }
        .import-wizard-preview-table-container { margin-top: 18px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .import-wizard-preview-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        .import-wizard-preview-table th { background: #f1f5f9; padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .import-wizard-preview-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; }
        .import-wizard-spinner-container { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 36px 20px; gap: 14px; }
        .import-wizard-spinner {
            width: 44px; height: 44px; border: 4px solid #f1f5f9; border-top-color: #4f46e5;
            border-radius: 50%; animation: import-spin 1s linear infinite;
        }
        @keyframes import-spin { to { transform: rotate(360deg); } }
        .import-wizard-alert {
            background: #fffbeb; border: 1.5px solid #fef3c7; border-left: 5px solid #d97706;
            padding: 16px; border-radius: 8px; margin-bottom: 20px; color: #78350f; font-size: 0.88rem; line-height: 1.5;
        }
        .import-wizard-metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .import-wizard-metric-card { padding: 14px; border-radius: 10px; text-align: center; background: #f8fafc; border: 1px solid #e2e8f0; }
        .import-wizard-metric-card.success { background: #f0fdf4; border-color: #bbf7d0; }
        .import-wizard-metric-card.danger { background: #fef2f2; border-color: #fecaca; }
        .import-wizard-metric-value { font-size: 1.6rem; font-weight: 700; margin-bottom: 2px; color: #334155; }
        .import-wizard-metric-value.success { color: #16a34a; }
        .import-wizard-metric-value.danger { color: #dc2626; }
        .import-wizard-metric-label { font-size: 0.75rem; color: #64748b; font-weight: 700; }
        .import-wizard-footer { padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
    `;
    document.head.appendChild(style);
}

// Sinónimos para normalización de columnas
const CANONICAL_CLIENTES = {
    tipo_persona: ['tipo persona', 'tipo', 'persona', 'person type', 'type'],
    nombre_razon_social: ['nombre razon social', 'nombre', 'nombre completo', 'razon social', 'cliente', 'fullname', 'name', 'nombre razon social', 'nombre_razon_social'],
    rnc: ['rnc cedula', 'rnc', 'cedula', 'documento', 'identificacion', 'id', 'rnc / cedula', 'rnc / cedula', 'rnc_cedula'],
    telefono: ['telefono', 'tel', 'phone', 'celular'],
    correo: ['correo', 'email', 'e mail', 'correo electronico', 'mail', 'correo_electronico'],
    direccion: ['direccion', 'direccion fisica', 'address', 'direccion_fisica'],
    estatus: ['estatus', 'estado', 'status', 'active']
};

const CANONICAL_USUARIOS = {
    cedula: ['cedula', 'rnc', 'documento', 'identificacion', 'id', 'rnc / cedula'],
    username: ['usuario', 'username', 'login', 'user'],
    nombre: ['nombre', 'first name', 'name', 'nombre completo'],
    apellido: ['apellido', 'last name', 'surname'],
    email: ['correo', 'email', 'e mail', 'correo electronico', 'mail'],
    telefono: ['telefono', 'tel', 'phone', 'celular'],
    perfil_id: ['perfil', 'rol', 'role', 'profile', 'perfil_id'],
    estado: ['estado', 'estatus', 'status', 'active'],
    es_comisionante: ['es comisionante', 'comisionante'],
    porcentaje_comision: ['porcentaje comision', 'comision', 'comision directa', 'porcentaje_comision'],
    porcentaje_comision_red: ['porcentaje comision red', 'comision red', 'porcentaje_comision_red'],
    referente_id: ['referente', 'supervisor', 'parent', 'referente_id']
};

function normalizarTexto(txt) {
    if (!txt) return '';
    return txt.toString().toLowerCase().trim()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // remover acentos
        .replace(/[^a-z0-9]/g, ' ') // caracteres no alfanuméricos
        .replace(/\s+/g, ' ').trim();
}

function encontrarColumna(headers, canonicalMap, key) {
    const synonyms = canonicalMap[key];
    for (let h of headers) {
        const normH = normalizarTexto(h);
        if (normH === key || synonyms.includes(normH)) {
            return h;
        }
    }
    // Substring fallback
    for (let h of headers) {
        const normH = normalizarTexto(h);
        for (let syn of synonyms) {
            if (normH.includes(syn) || syn.includes(normH)) {
                return h;
            }
        }
    }
    return null;
}

// Ventana principal del Wizard
function abrirAsistenteImportacion(modulo) {
    inyectarEstilosAsistente();

    // Eliminar modal previo si existe
    const prevModal = document.getElementById('import-wizard-modal');
    if (prevModal) prevModal.remove();

    const labelModulo = modulo === 'usuarios' ? 'Usuarios' : 'Clientes';
    
    // Crear el contenedor principal
    const modal = document.createElement('div');
    modal.id = 'import-wizard-modal';
    modal.className = 'import-wizard-overlay';
    
    // Contenido del modal
    modal.innerHTML = `
        <div class="import-wizard-container">
            <div class="import-wizard-header">
                <h3><i class="fa-solid fa-file-import"></i> Asistente de Importación: ${labelModulo}</h3>
                <button class="import-wizard-close" id="wizard-close-btn">&times;</button>
            </div>
            
            <div class="import-wizard-steps-indicator">
                <div class="import-wizard-step-pill active" id="pill-step-1">
                    <span class="step-num">1</span> Preparar & Seleccionar
                </div>
                <div class="import-wizard-step-pill" id="pill-step-2">
                    <span class="step-num">2</span> Cargar & Procesar
                </div>
                <div class="import-wizard-step-pill" id="pill-step-3">
                    <span class="step-num">3</span> Resultado & Logs
                </div>
            </div>
            
            <div class="import-wizard-body">
                <!-- PASO 1: SELECCIONAR Y PREVISUALIZAR -->
                <div class="import-wizard-view active" id="view-step-1">
                    <div class="import-wizard-instructions">
                        <h4><i class="fa-solid fa-circle-info" style="color:#2563eb;"></i> Instrucciones de formato:</h4>
                        <ul>
                            <li>El archivo debe ser de tipo <strong>Excel (.xlsx)</strong> o <strong>CSV</strong>.</li>
                            <li>Las columnas se mapearán automáticamente buscando sinónimos inteligentes.</li>
                            ${modulo === 'clientes' 
                              ? `<li><strong>Campos sugeridos:</strong> Nombre / Razón Social, RNC / Cédula, Teléfono, Correo, Dirección.</li>`
                              : `<li><strong>Campos sugeridos:</strong> Cédula, Nombre, Apellido, Usuario (login), Correo electrónico, Teléfono.</li>`}
                            <li>Cualquier campo obligatorio vacío será **autocompletado** de manera segura para evitar errores en la base de datos.</li>
                        </ul>
                    </div>
                    
                    <div class="import-wizard-dropzone" id="dropzone">
                        <i class="fa-solid fa-file-excel import-wizard-dropzone-icon"></i>
                        <p style="margin: 0; font-weight:600;">Arrastra tu archivo aquí o haz clic para buscar</p>
                        <span style="font-size: 0.78rem; color:#64748b;">Soporta archivos .xlsx y .csv</span>
                        <input type="file" id="wizard-file-input" accept=".xlsx, .csv" style="display:none;">
                    </div>
                    
                    <div id="preview-container" style="display:none; margin-top:20px;">
                        <h4 style="margin: 0 0 10px 0; font-size:0.9rem; font-weight:700;"><i class="fa-solid fa-table"></i> Vista Previa (Primeras 3 filas normalizadas):</h4>
                        <div class="import-wizard-preview-table-container">
                            <table class="import-wizard-preview-table" id="preview-table">
                                <thead><tr id="preview-headers"></tr></thead>
                                <tbody id="preview-rows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- PASO 2: CARGANDO -->
                <div class="import-wizard-view" id="view-step-2">
                    <div class="import-wizard-spinner-container">
                        <div class="import-wizard-spinner"></div>
                        <h4 style="margin: 10px 0 0 0; font-size:1rem; font-weight:700;" id="spinner-text">Cargando el archivo...</h4>
                        <p style="color:#64748b; font-size:0.85rem; margin:0;" id="spinner-subtext">procesando y normalizando la información de los registros...</p>
                    </div>
                </div>
                
                <!-- PASO 3: RESULTADOS -->
                <div class="import-wizard-view" id="view-step-3">
                    <div class="import-wizard-alert">
                        <strong>⚠️ IMPORTANTE:</strong> Los campos que no existían o no tenían información en el archivo de origen fueron autocompletados. Se recomienda revisar y actualizar estos registros mediante la acción de 'Modificación' (Editar) en el listado.
                    </div>
                    
                    <div class="import-wizard-metrics-grid">
                        <div class="import-wizard-metric-card">
                            <div class="import-wizard-metric-value" id="metric-total">0</div>
                            <div class="import-wizard-metric-label">Registros Procesados</div>
                        </div>
                        <div class="import-wizard-metric-card success">
                            <div class="import-wizard-metric-value success" id="metric-success">0</div>
                            <div class="import-wizard-metric-label">Cargados con éxito</div>
                        </div>
                        <div class="import-wizard-metric-card danger">
                            <div class="import-wizard-metric-value danger" id="metric-failed">0</div>
                            <div class="import-wizard-metric-label">Fallas / Omitidos</div>
                        </div>
                    </div>
                    
                    <div style="display:flex; justify-content:center; gap:12px; margin-top:20px;">
                        <button class="mqf-btn mqf-btn--secondary" id="download-log-btn" style="padding:10px 20px;">
                            <i class="fa-solid fa-file-lines"></i> Descargar Log Detallado (.txt)
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="import-wizard-footer">
                <button class="mqf-btn mqf-btn--secondary" id="wizard-cancel-btn">Cancelar</button>
                <button class="mqf-btn mqf-btn--primary" id="wizard-next-btn" disabled>Siguiente <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Forzar reflow para animación
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Variables de control de estado del Wizard
    let fileLoaded = null;
    let parsedData = [];
    let normalizedPayloads = [];
    let logsText = "";
    let metrics = { total: 0, success: 0, failed: 0 };
    
    // Elementos de la interfaz
    const dropzone = modal.querySelector('#dropzone');
    const fileInput = modal.querySelector('#wizard-file-input');
    const cancelBtn = modal.querySelector('#wizard-cancel-btn');
    const nextBtn = modal.querySelector('#wizard-next-btn');
    const closeBtn = modal.querySelector('#wizard-close-btn');
    const downloadLogBtn = modal.querySelector('#download-log-btn');
    
    const pill1 = modal.querySelector('#pill-step-1');
    const pill2 = modal.querySelector('#pill-step-2');
    const pill3 = modal.querySelector('#pill-step-3');
    
    const view1 = modal.querySelector('#view-step-1');
    const view2 = modal.querySelector('#view-step-2');
    const view3 = modal.querySelector('#view-step-3');

    // Cerrar el modal
    const cerrarModal = () => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    };
    
    closeBtn.addEventListener('click', cerrarModal);
    cancelBtn.addEventListener('click', cerrarModal);
    
    // Manejo de eventos de Drag & Drop
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            cargarYProcesarArchivo(e.dataTransfer.files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            cargarYProcesarArchivo(e.target.files[0]);
        }
    });

    // Motor de lectura y normalización
    function cargarYProcesarArchivo(file) {
        if (typeof XLSX === 'undefined') {
            MQF.toast('La librería SheetJS no está cargada.', 'error');
            return;
        }
        
        fileLoaded = file;
        dropzone.innerHTML = `
            <i class="fa-solid fa-file-circle-check" style="font-size:2.25rem; color:#16a34a;"></i>
            <p style="margin: 0; font-weight:600; color:#16a34a;">¡Archivo seleccionado!</p>
            <span style="font-size: 0.82rem; color:#334155;">${file.name} (${(file.size/1024).toFixed(1)} KB)</span>
            <button class="mqf-btn mqf-btn--secondary mqf-btn--sm" style="margin-top:10px;" id="change-file-btn">Cambiar Archivo</button>
        `;
        
        modal.querySelector('#change-file-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            fileInput.click();
        });
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                parsedData = XLSX.utils.sheet_to_json(worksheet, { defval: "" });
                
                if (parsedData.length === 0) {
                    MQF.toast('El archivo está completamente vacío.', 'warning');
                    resetearPaso1();
                    return;
                }
                
                normalizarDatos();
            } catch (err) {
                console.error(err);
                MQF.toast('Error al leer el archivo. Asegúrese de que es un Excel o CSV válido.', 'error');
                resetearPaso1();
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function resetearPaso1() {
        fileLoaded = null;
        parsedData = [];
        normalizedPayloads = [];
        dropzone.innerHTML = `
            <i class="fa-solid fa-file-excel import-wizard-dropzone-icon"></i>
            <p style="margin: 0; font-weight:600;">Arrastra tu archivo aquí o haz clic para buscar</p>
            <span style="font-size: 0.78rem; color:#64748b;">Soporta archivos .xlsx y .csv</span>
            <input type="file" id="wizard-file-input" accept=".xlsx, .csv" style="display:none;">
        `;
        modal.querySelector('#preview-container').style.display = 'none';
        nextBtn.setAttribute('disabled', 'true');
    }

    function normalizarDatos() {
        // Encontrar cabeceras originales del excel
        const firstRow = parsedData[0];
        const headers = Object.keys(firstRow);
        
        normalizedPayloads = [];
        logsText = `=== LOG DE IMPORTACION MASIVA: ${labelModulo.toUpperCase()} ===\r\n`;
        logsText += `Fecha de Importación: ${new Date().toLocaleString()}\r\n`;
        logsText += `Archivo: ${fileLoaded.name}\r\n`;
        logsText += `--------------------------------------------------------\r\n\r\n`;
        
        const map = modulo === 'usuarios' ? CANONICAL_USUARIOS : CANONICAL_CLIENTES;
        
        parsedData.forEach((row, idx) => {
            const rowNum = idx + 1;
            const logPre = `Fila #${rowNum}: `;
            let logsFila = [];
            
            if (modulo === 'clientes') {
                // CLIENTES
                const colTipo = encontrarColumna(headers, map, 'tipo_persona');
                const colNombre = encontrarColumna(headers, map, 'nombre_razon_social');
                const colRnc = encontrarColumna(headers, map, 'rnc');
                const colTel = encontrarColumna(headers, map, 'telefono');
                const colCorreo = encontrarColumna(headers, map, 'correo');
                const colDir = encontrarColumna(headers, map, 'direccion');
                const colEstatus = encontrarColumna(headers, map, 'estatus');
                
                let rawNombre = colNombre ? row[colNombre] : '';
                let rawRnc = colRnc ? row[colRnc] : '';
                let rawTipo = colTipo ? row[colTipo] : '';
                
                // Mapear Tipo Persona
                let tipo_persona = 'Fisica';
                if (rawTipo) {
                    const cleanTipo = normalizarTexto(rawTipo);
                    if (cleanTipo.includes('jurid') || cleanTipo.includes('empresa') || cleanTipo.includes('company')) {
                        tipo_persona = 'Juridica';
                    }
                } else {
                    logsFila.push("El tipo de persona estaba vacío, se asignó 'Fisica' por defecto.");
                }
                
                // Mapear Nombre
                let nombre_razon_social = rawNombre ? rawNombre.toString().trim() : '';
                if (!nombre_razon_social) {
                    nombre_razon_social = `Importado - ${new Date().toLocaleDateString('es-DO')}`;
                    logsFila.push(`El nombre del cliente estaba vacío, se autocompletó como '${nombre_razon_social}'.`);
                }
                
                // Mapear RNC
                let rnc = rawRnc ? rawRnc.toString().replace(/[^a-zA-Z0-9]/g, '').trim() : '';
                if (!rnc) {
                    rnc = 'GEN-' + Math.floor(10000000 + Math.random() * 90000000);
                    logsFila.push(`RNC/Cédula no especificado, se autocompletó con el ID aleatorio '${rnc}'.`);
                }
                
                const normRow = {
                    tipo_persona: tipo_persona,
                    nombre_razon_social: nombre_razon_social,
                    rnc: rnc,
                    telefono: colTel && row[colTel] ? row[colTel].toString().trim() : '',
                    correo: colCorreo && row[colCorreo] ? colCorreo.toString().trim() : '',
                    direccion: colDir && row[colDir] ? row[colDir].toString().trim() : '',
                    estatus: colEstatus && row[colEstatus] ? row[colEstatus].toString().trim() : 'Activo'
                };
                
                normalizedPayloads.push(normRow);
                
                if (logsFila.length > 0) {
                    logsText += `${logPre}Cliente '${nombre_razon_social}' procesado con advertencias:\r\n` + logsFila.map(l => `   - ${l}`).join('\r\n') + '\r\n';
                } else {
                    logsText += `${logPre}Cliente '${nombre_razon_social}' procesado y normalizado con éxito sin requerir cambios.\r\n`;
                }
                
            } else {
                // USUARIOS
                const colCedula = encontrarColumna(headers, map, 'cedula');
                const colNombre = encontrarColumna(headers, map, 'nombre');
                const colApellido = encontrarColumna(headers, map, 'apellido');
                const colUsername = encontrarColumna(headers, map, 'username');
                const colEmail = encontrarColumna(headers, map, 'email');
                const colTel = encontrarColumna(headers, map, 'telefono');
                const colPerfil = encontrarColumna(headers, map, 'perfil_id');
                const colEstado = encontrarColumna(headers, map, 'estado');
                
                let rawNombre = colNombre ? row[colNombre] : '';
                let rawApellido = colApellido ? row[colApellido] : '';
                let rawCedula = colCedula ? row[colCedula] : '';
                let rawUsername = colUsername ? row[colUsername] : '';
                let rawEmail = colEmail ? row[colEmail] : '';
                
                let nombre = rawNombre ? rawNombre.toString().trim() : 'Usuario';
                if (!rawNombre) logsFila.push("Nombre vacío, se asignó 'Usuario' por defecto.");
                
                let apellido = rawApellido ? rawApellido.toString().trim() : 'Importado';
                if (!rawApellido) logsFila.push("Apellido vacío, se asignó 'Importado' por defecto.");
                
                let cedula = rawCedula ? rawCedula.toString().replace(/[^a-zA-Z0-9]/g, '').trim() : '';
                if (!cedula) {
                    cedula = 'GEN-' + Math.floor(10000000 + Math.random() * 90000000);
                    logsFila.push(`Cédula vacía, se autocompletó como '${cedula}'.`);
                }
                
                // Generar Username seguro: nombre.apellido + sufijo aleatorio
                let username = rawUsername ? normalizarTexto(rawUsername).replace(/\s+/g, '') : '';
                if (!username) {
                    const normNom = normalizarTexto(nombre).replace(/\s+/g, '');
                    const normApe = normalizarTexto(apellido).replace(/\s+/g, '');
                    const randSuffix = Math.floor(100 + Math.random() * 900);
                    username = `${normNom}.${normApe}${randSuffix}`;
                    logsFila.push(`Nombre de usuario (login) autogenerado como '${username}' para cumplir unicidad.`);
                }
                
                // Email corporativo fallback
                let email = rawEmail ? rawEmail.toString().trim() : '';
                if (!email) {
                    email = `${username}@masquefianzas.com`;
                    logsFila.push(`Correo vacío, se asignó la cuenta corporativa '${email}'.`);
                }
                
                // Mapear Perfil ID
                let perfil_id = 2; // Agente Comercial
                if (colPerfil && row[colPerfil]) {
                    const cleanP = normalizarTexto(row[colPerfil]);
                    if (cleanP.includes('admin')) perfil_id = 1;
                    else if (cleanP.includes('agente') || cleanP.includes('comercial')) perfil_id = 2;
                    else if (cleanP.includes('sup') && cleanP.includes('com')) perfil_id = 3;
                    else if (cleanP.includes('zona')) perfil_id = 4;
                    else if (cleanP.includes('socio') || cleanP.includes('pdv')) perfil_id = 5;
                } else {
                    logsFila.push("Perfil de acceso no definido, se asignó 'Agente Comercial' por defecto.");
                }
                
                const normRow = {
                    cedula: cedula,
                    nombre: nombre,
                    apellido: apellido,
                    username: username,
                    email: email,
                    telefono: colTel && row[colTel] ? row[colTel].toString().trim() : '',
                    perfil_id: perfil_id,
                    estado: colEstado && row[colEstado] ? row[colEstado].toString().trim().toLowerCase() : 'activo'
                };
                
                normalizedPayloads.push(normRow);
                
                if (logsFila.length > 0) {
                    logsText += `${logPre}Usuario @${username} procesado con advertencias:\r\n` + logsFila.map(l => `   - ${l}`).join('\r\n') + '\r\n';
                } else {
                    logsText += `${logPre}Usuario @${username} procesado y normalizado con éxito sin requerir cambios.\r\n`;
                }
            }
        });
        
        // Renderizar la previsualización de las primeras 3 filas
        const headTr = modal.querySelector('#preview-headers');
        const rowsTbody = modal.querySelector('#preview-rows');
        
        headTr.innerHTML = '';
        rowsTbody.innerHTML = '';
        
        const previewKeys = modulo === 'clientes' 
            ? ['tipo_persona', 'nombre_razon_social', 'rnc', 'telefono', 'correo'] 
            : ['cedula', 'nombre', 'apellido', 'username', 'email'];
            
        const previewLabels = modulo === 'clientes'
            ? ['Tipo Persona', 'Nombre / Razón Social', 'RNC / Cédula', 'Teléfono', 'Correo']
            : ['Cédula / RNC', 'Nombre', 'Apellido', 'Usuario (login)', 'Email'];
            
        previewLabels.forEach(lbl => {
            const th = document.createElement('th');
            th.textContent = lbl;
            headTr.appendChild(th);
        });
        
        const rowsToPreview = normalizedPayloads.slice(0, 3);
        rowsToPreview.forEach(row => {
            const tr = document.createElement('tr');
            previewKeys.forEach(k => {
                const td = document.createElement('td');
                td.textContent = row[k] || '-';
                tr.appendChild(td);
            });
            rowsTbody.appendChild(tr);
        });
        
        modal.querySelector('#preview-container').style.display = 'block';
        nextBtn.removeAttribute('disabled');
    }
    
    // Ir al Paso 2
    nextBtn.addEventListener('click', () => {
        mostrarPaso(2);
        ejecutarImportacionAPI();
    });
    
    function mostrarPaso(num) {
        pill1.className = 'import-wizard-step-pill' + (num === 1 ? ' active' : (num > 1 ? ' completed' : ''));
        pill2.className = 'import-wizard-step-pill' + (num === 2 ? ' active' : (num > 2 ? ' completed' : ''));
        pill3.className = 'import-wizard-step-pill' + (num === 3 ? ' active' : '');
        
        view1.className = 'import-wizard-view' + (num === 1 ? ' active' : '');
        view2.className = 'import-wizard-view' + (num === 2 ? ' active' : '');
        view3.className = 'import-wizard-view' + (num === 3 ? ' active' : '');
        
        if (num === 1) {
            cancelBtn.style.display = 'block';
            cancelBtn.textContent = 'Cancelar';
            nextBtn.style.display = 'block';
            nextBtn.textContent = 'Siguiente';
        } else if (num === 2) {
            cancelBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        } else if (num === 3) {
            cancelBtn.style.display = 'none';
            nextBtn.style.display = 'block';
            nextBtn.textContent = 'Finalizar & Cerrar';
            nextBtn.removeAttribute('disabled');
            
            // Re-vincular botón finalizar para recargar tabla y cerrar
            nextBtn.removeEventListener('click', null);
            nextBtn.addEventListener('click', () => {
                cerrarModal();
                if (modulo === 'usuarios' && typeof cargarUsuarios === 'function') cargarUsuarios();
                if (modulo === 'clientes' && typeof cargarClientes === 'function') cargarClientes();
            });
        }
    }
    
    // Paso 2: Ejecución de la importación a través de api.solicitud()
    async function ejecutarImportacionAPI() {
        const endpoint = modulo === 'usuarios' ? '/usuarios.php/importar' : '/clientes.php/importar';
        const payloadKey = modulo === 'usuarios' ? 'usuarios' : 'clientes';
        
        const dataToSend = {};
        dataToSend[payloadKey] = normalizedPayloads;
        
        const spinnerTxt = modal.querySelector('#spinner-text');
        const spinnerSub = modal.querySelector('#spinner-subtext');
        
        spinnerTxt.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Cargando data...`;
        spinnerSub.textContent = `procederemos a cargar la data esto tomara unos minutos...`;
        
        try {
            // Llamar usando api-client.js centralizado que inyecta el Bearer token
            const respuesta = await api.solicitud(endpoint, 'POST', dataToSend);
            
            if (respuesta.exito) {
                metrics.total = normalizedPayloads.length;
                metrics.success = parseInt(respuesta.insertados) || normalizedPayloads.length;
                metrics.failed = parseInt(respuesta.errores) || 0;
                
                logsText += `\r\n\r\n=== RESULTADO FINAL ===\r\n`;
                logsText += `Total procesados: ${metrics.total}\r\n`;
                logsText += `Insertados con éxito: ${metrics.success}\r\n`;
                logsText += `Errores / Omitidos: ${metrics.failed}\r\n`;
                if (respuesta.detalles && Array.isArray(respuesta.detalles)) {
                    logsText += `\r\nDetalles de fallas reportadas por el backend:\r\n`;
                    respuesta.detalles.forEach(d => {
                        logsText += `   - ${d}\r\n`;
                    });
                }
                
                // Sincronizar UI métricas del paso 3
                modal.querySelector('#metric-total').textContent = metrics.total;
                modal.querySelector('#metric-success').textContent = metrics.success;
                modal.querySelector('#metric-failed').textContent = metrics.failed;
                
                MQF.toast(`Importación completada: ${metrics.success} exitosos.`, 'success');
                mostrarPaso(3);
            } else {
                MQF.toast('Error en la carga: ' + (respuesta.mensaje || 'Error desconocido'), 'error');
                mostrarPaso(1);
            }
        } catch (error) {
            console.error(error);
            MQF.toast('Error de red al intentar importar los datos.', 'error');
            mostrarPaso(1);
        }
    }
    
    // Descarga de Log de Operaciones (.txt)
    downloadLogBtn.addEventListener('click', () => {
        const blob = new Blob([logsText], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Log_Importacion_${labelModulo}_${new Date().toISOString().split('T')[0]}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}


function importarDatos(event) {
    // Mantener compatibilidad por si alguna llamada inline lo requiere, redirigiendo a la interfaz premium
    const modulo = window.location.pathname.includes('usuarios') ? 'usuarios' : 'clientes';
    abrirAsistenteImportacion(modulo);
}

document.addEventListener('click', function(event) {
    const exportMenu = document.getElementById('exportMenu');
    if (exportMenu && event.target.closest('.dropdown-export') === null) {
        exportMenu.style.display = 'none';
    }
});

function importarCotizaciones(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const json = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            
            if (json.length === 0) { MQF.toast('Archivo vacío.', 'warning'); return; }
            
            const payload = json.map(row => ({
                numero: row['N° Cotizacion'] || row['numero'] || ('F-IMP-' + Math.floor(Math.random()*9000)),
                tipo: row['Tipo'] || row['tipo'] || 'SEGURO',
                subtipo: row['Ramo / Subtipo'] || row['subtipo'] || '',
                cliente: row['Cliente'] || row['cliente'] || 'Importado',
                cedula: row['Cédula / RNC'] || row['cedula'] || '',
                beneficiario: row['Beneficiario'] || row['beneficiario'] || '',
                monto_afianzado: parseFloat(row['Monto Base'] || row['monto_afianzado'] || row['monto'] || 0),
                total: parseFloat(row['Prima Total'] || row['total'] || row['prima_total'] || 0),
                fecha: row['Fecha Emisión'] ? new Date(row['Fecha Emisión']).toISOString() : new Date().toISOString()
            }));
            
            if (await MQF.confirm(`¿Importar ${payload.length} cotizaciones a la base de datos central?`, {type: 'primary'})) {
                MQF.toast('Procesando importación central...', 'info');
                const res = await api.solicitud('/cotizaciones.php?action=importar', 'POST', payload);
                if (res.exito) {
                    MQF.toast(`¡${payload.length} cotizaciones importadas con éxito!`, 'success');
                    if (typeof cargarHistorial === 'function') cargarHistorial();
                    if (typeof loadHistorial === 'function') loadHistorial();
                } else {
                    MQF.toast('Error al importar cotizaciones: ' + (res.mensaje || 'Error del servidor'), 'error');
                }
            }
        } catch (error) {
            console.error("Import error:", error);
            MQF.toast('Error procesando el archivo Excel/CSV.', 'error');
        } finally {
            event.target.value = null;
        }
    };
    reader.readAsArrayBuffer(file);
}

window.exportarListado = exportarListado;
window.exportarAExcel = exportarAExcel;
window.exportarAJSON = exportarAJSON;
window.exportarAPDF = exportarAPDF;
window.exportarAZIP = exportarAZIP;
window.imprimirItem = imprimirItem;
window.importarDatos = importarDatos;
window.importarCotizaciones = importarCotizaciones;
window.abrirAsistenteImportacion = abrirAsistenteImportacion;

// Genera QR como dataURL usando QRCode.js local, canvas vectorial o fallback
async function generarQRDataURL(texto) {
    if (!texto) texto = 'http://169.58.51.147/PLATAFORMA_INTEGRADA/frontend/';
    
    // 1. Probar librería QRCode local (qrcode.min.js)
    if (typeof QRCode !== 'undefined') {
        const div = document.createElement('div');
        div.style.position = 'absolute';
        div.style.left = '-9999px';
        div.style.top = '-9999px';
        document.body.appendChild(div);
        try {
            new QRCode(div, { text: texto, width: 140, height: 140 });
            await new Promise(r => setTimeout(r, 60));
            const cvs = div.querySelector('canvas');
            const img = div.querySelector('img');
            let dataUrl = null;
            if (cvs && cvs.toDataURL) dataUrl = cvs.toDataURL('image/png');
            else if (img && img.src && img.src.startsWith('data:image')) dataUrl = img.src;
            try { document.body.removeChild(div); } catch(pe){}
            if (dataUrl) return dataUrl;
        } catch(e) {
            try { document.body.removeChild(div); } catch(pe){}
        }
    }

    // 2. Probar fetch a la API de QR Server
    try {
        const url = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&format=png&data=' + encodeURIComponent(texto);
        const res = await fetch(url);
        if (res.ok) {
            const blob = await res.blob();
            const dataUrl = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => resolve(null);
                reader.readAsDataURL(blob);
            });
            if (dataUrl) return dataUrl;
        }
    } catch(e) {}

    // 3. Fallback Canvas local sintetizado (Patrón QR nítido offline)
    try {
        const canvas = document.createElement('canvas');
        canvas.width = 140;
        canvas.height = 140;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, 140, 140);
        ctx.fillStyle = '#003399';
        ctx.strokeRect(8, 8, 124, 124);
        ctx.fillRect(16, 16, 32, 32);
        ctx.fillRect(92, 16, 32, 32);
        ctx.fillRect(16, 92, 32, 32);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(22, 22, 20, 20);
        ctx.fillRect(98, 22, 20, 20);
        ctx.fillRect(22, 98, 20, 20);
        ctx.fillStyle = '#003399';
        ctx.fillRect(26, 26, 12, 12);
        ctx.fillRect(102, 26, 12, 12);
        ctx.fillRect(26, 102, 12, 12);
        return canvas.toDataURL('image/png');
    } catch(e) {
        return null;
    }
}
window.generarQRDataURL = generarQRDataURL;
