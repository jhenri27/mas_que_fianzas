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
    get EMPRESA() {
        const cfg = (window.parent && window.parent.MQF_CONFIG) || window.MQF_CONFIG || {};
        return {
            nombre: cfg.empresa_nombre || 'MAS QUE FIANZAS, S.R.L.',
            rnc: cfg.empresa_rnc || '133-53573-4',
            telefono: cfg.empresa_telefono || '(829) 629-1952',
            email: cfg.empresa_correo || 'info@masquefianzas.com',
            direccion: cfg.empresa_direccion || 'Ave. 27 de Febrero #234, Suite-304, La Esperilla, Santo Domingo, RD',
            base_url: window.location.origin + window.location.pathname.replace(/\/frontend\/.*/, '')
        };
    },
    COLORES: {
        navy: [0, 51, 102],
        azul: [0, 71, 160],
        dorado: [212, 175, 55],
        blanco: [255, 255, 255],
        gris: [100, 116, 139],
        verde: [22, 163, 74],
        rojo: [220, 38, 38]
    },
    // Paletas sincronizadas con skin-engine.css
    THEMES: {
        indigo:   { primary: [79, 70, 229],  secondary: [124, 58, 237] }, // #4f46e5, #7c3aed
        obsidian: { primary: [129, 140, 248], secondary: [30, 41, 59] },  // #818cf8, #1e293b
        coral:    { primary: [244, 63, 94],  secondary: [251, 113, 133] } // #f43f5e, #fb7185
    },
    getTheme: function() {
        const skin = localStorage.getItem('mqf-skin') || 'indigo';
        return this.THEMES[skin] || this.THEMES.indigo;
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
        const asegNombre = (poliza.aseguradora || '').toUpperCase().trim();

        // ── BUSCAR PLANTILLA Y COORDENADAS DESDE LA BD (MODELADOR) ───────
        let dbMapeo = null;
        try {
            const token = localStorage.getItem("token_sesion") || 
                          sessionStorage.getItem("mqf_token") || 
                          sessionStorage.getItem("token_sesion") || "";
            const asegSearch = asegNombre.includes('PATRIA') ? 'PATRIA' : (asegNombre.includes('PEPIN') || asegNombre.includes('PEPÍN') ? 'PEPIN' : (asegNombre.includes('MIDAS') ? 'MIDAS' : ''));
            if (asegSearch) {
                const url = `/PLATAFORMA_INTEGRADA/backend/api/pdf_modeler.php?action=obtener_marbete_activo&aseguradora=${encodeURIComponent(asegSearch)}&token_sesion=${encodeURIComponent(token)}&_t=${Date.now()}`;
                const headers = {};
                if (token) {
                    headers['Authorization'] = 'Bearer ' + token;
                }
                const r = await fetch(url, { headers });
                if (r.ok) {
                    const res = await r.json();
                    if (res.exito && res.plantilla) {
                        dbMapeo = { plantilla: res.plantilla, campos: res.campos || [] };
                        console.log("[Marbete] Mapeo de coordenadas cargado desde BD para: " + asegSearch);
                    }
                }
            }
        } catch (e) {
            console.warn("[Marbete] Error cargando coordenadas de la BD:", e);
        }

        // ── INTERCEPCIÓN DE MARBETE OFICIAL DE SEGUROS PATRIA ─────────────
        if (asegNombre.includes('PATRIA')) {
            try {
                if (typeof PDFLib === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = '../assets/lib/pdf-lib.min.js';
                        script.onload = resolve;
                        script.onerror = () => {
                            const cdnScript = document.createElement('script');
                            cdnScript.src = 'https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js';
                            cdnScript.onload = resolve;
                            cdnScript.onerror = () => reject(new Error('No se pudo cargar pdf-lib'));
                            document.head.appendChild(cdnScript);
                        };
                        document.head.appendChild(script);
                    });
                }
                
                const { PDFDocument, rgb, StandardFonts } = PDFLib;
                
                // Fetch de la plantilla oficial de Patria
                const templateUrl = '/PLATAFORMA_INTEGRADA/backend/uploads/plantillas_pdf/VEH-MARBETE-OFICIAL.pdf';
                const fileRes = await fetch(templateUrl);
                if (!fileRes.ok) throw new Error('No se pudo cargar la plantilla oficial de Patria');
                const fileBuffer = await fileRes.arrayBuffer();
                
                const pdfDoc = await PDFDocument.load(fileBuffer);
                const pages = pdfDoc.getPages();
                const firstPage = pages[0];
                const pageHeight = firstPage.getHeight();
                
                // Fuentes
                const fontRegular = await pdfDoc.embedFont(StandardFonts.Helvetica);
                const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
                
                // Helper para dibujar texto
                const draw = (txt, x, y, size = 6.8, isBold = false) => {
                    firstPage.drawText(String(txt || ''), {
                        x: x,
                        y: y,
                        size: size,
                        font: isBold ? fontBold : fontRegular,
                        color: rgb(0.15, 0.15, 0.15)
                    });
                };
                
                // Formatear fechas
                const fmtC = (f) => {
                    if (!f) return 'N/A';
                    const d = new Date(f);
                    return `${String(d.getDate()).padStart(2,'0')}-${String(d.getMonth()+1).padStart(2,'0')}-${d.getFullYear()}`;
                };
                const vigenciaStr = `Del: ${fmtC(poliza.fecha_emision)} al: ${fmtC(poliza.fecha_vencimiento)}`;
                
                // Resolver valores dinámicos
                const clienteNombre = (poliza.cliente_nombre || 'A QUIEN CORRESPONDA').toUpperCase();
                const nroPol = poliza.numero_poliza || 'N/A';
                const tipoVeh = (vehiculo?.tipo_vehiculo || 'MOTOCICLETA').toUpperCase();
                const chasis = vehiculo?.chasis || 'N/A';
                const placa = vehiculo?.placa || 'N/A';
                const marcaModelo = `${vehiculo?.marca || ''} ${vehiculo?.modelo || ''}`.trim().toUpperCase() || 'N/A';
                const anio = vehiculo?.anio || 'N/A';
                const uso = (vehiculo?.uso || 'PRIVADO').toUpperCase();
                
                // Helper para dbuixar campo de la base de datos
                const drawFieldDB = (campo) => {
                    if (campo.variable === 'sistema.qr_msqf') return;
                    const val = (() => {
                        if (!campo.variable && campo.nombre_campo_pdf) {
                            return campo.nombre_campo_pdf;
                        }
                        switch(campo.variable) {
                            case 'cliente.nombre': return clienteNombre;
                            case 'cliente.cedula': return poliza.cliente_cedula || 'N/A';
                            case 'cliente.telefono': return poliza.cliente_telefono || 'N/A';
                            case 'cliente.email': return poliza.cliente_email || 'N/A';
                            case 'poliza.numero_poliza': return nroPol;
                            case 'vehiculo.tipo_vehiculo': return tipoVeh;
                            case 'vehiculo.chasis': return chasis;
                            case 'vehiculo.placa': return placa;
                            case 'poliza.fecha_inicio': return fmtC(poliza.fecha_emision);
                            case 'poliza.fecha_fin': return fmtC(poliza.fecha_vencimiento);
                            case 'vehiculo.uso': return uso;
                            case 'vehiculo.marca': return (vehiculo?.marca || '').toUpperCase();
                            case 'vehiculo.modelo': return (vehiculo?.modelo || '').toUpperCase();
                            case 'vehiculo.anio': return anio;
                            case 'poliza.objeto_fianza': return poliza.objeto_fianza || 'FIANZA DE LEY';
                            case 'poliza.fianza':
                            case 'poliza.fianza_judicial': return fmtDOP(poliza.fianza_judicial || 50000);
                            default: return '';
                        }
                    })();
                    if (!val) return;
                    
                    const pageW_pt = firstPage.getWidth();
                    const pageH_pt = firstPage.getHeight();
                    const plantW_mm = parseFloat(dbMapeo.plantilla.ancho_mm) || 210.0;
                    const plantH_mm = parseFloat(dbMapeo.plantilla.alto_mm) || 297.0;

                    const size = parseFloat(campo.font_size) || 7.0;
                    const x_pt = (parseFloat(campo.pos_x) / plantW_mm) * pageW_pt;
                    const y_pt = (pageH_pt - (parseFloat(campo.pos_y) / plantH_mm) * pageH_pt) - size;
                    
                    const font = campo.font_weight === 'bold' ? fontBold : fontRegular;
                    
                    const hex = (campo.color || '#000000').replace('#', '');
                    let r_val = 0.15, g_val = 0.15, b_val = 0.15;
                    if (hex.length === 6) {
                        r_val = parseInt(hex.substring(0, 2), 16) / 255;
                        g_val = parseInt(hex.substring(2, 4), 16) / 255;
                        b_val = parseInt(hex.substring(4, 6), 16) / 255;
                    }

                    firstPage.drawText(String(val), {
                        x: x_pt,
                        y: y_pt,
                        size: size,
                        font: font,
                        color: rgb(r_val, g_val, b_val)
                    });
                };

                if (!dbMapeo && asegSearch === 'PATRIA') {
                    dbMapeo = {
                        plantilla: { ancho_mm: 210.10, alto_mm: 297.05 },
                        campos: [
                            { variable: 'cliente.nombre', pos_x: 24.94, pos_y: 71.84, font_size: 7.5 },
                            { variable: 'poliza.numero_poliza', pos_x: 25.04, pos_y: 74.81, font_size: 7.5 },
                            { variable: 'vehiculo.tipo_vehiculo', pos_x: 25.09, pos_y: 78.56, font_size: 7.5 },
                            { variable: 'vehiculo.chasis', pos_x: 25.19, pos_y: 82.08, font_size: 7.0 },
                            { variable: 'vehiculo.placa', pos_x: 25.09, pos_y: 85.28, font_size: 7.5 },
                            { variable: 'poliza.fecha_inicio', pos_x: 26.41, pos_y: 88.98, font_size: 7.5 },
                            { variable: 'poliza.fecha_fin', pos_x: 43.80, pos_y: 89.01, font_size: 7.5 },
                            { variable: 'vehiculo.uso', pos_x: 71.66, pos_y: 78.35, font_size: 7.5 },
                            { variable: 'vehiculo.marca', pos_x: 69.36, pos_y: 81.75, font_size: 7.5 },
                            { variable: 'vehiculo.modelo', pos_x: 58.37, pos_y: 89.05, font_size: 7.5 },
                            { variable: 'vehiculo.anio', pos_x: 65.69, pos_y: 85.25, font_size: 7.5 }
                        ]
                    };
                }

                if (dbMapeo) {
                    dbMapeo.campos.forEach(drawFieldDB);
                } else {
                    // Escribir datos en la tarjeta izquierda (Alineados con las etiquetas pre-impresas y corregidos de Y)
                    draw(clienteNombre, 80, 630.0, 6.8, false);
                    draw(nroPol, 74, 621.2, 6.8, false);
                    draw(tipoVeh, 64, 611.2, 6.8, false);
                    draw(chasis, 74, 601.4, 6.2, false);
                    draw(placa, 72, 591.5, 6.8, false);
                    draw(vigenciaStr, 86, 581.6, 6.2, false);
                    draw('MÁS QUE FIANZAS', 76, 572.5, 6.8, false);
                    
                    // Valores de la columna derecha dentro de la tarjeta izquierda
                    draw(uso, 208, 611.2, 6.8, false);
                    draw(marcaModelo, 208, 601.4, 6.8, false);
                    draw(anio, 208, 591.5, 6.8, false);
                }
                
                // ── INCORPORAR QR DE VALIDACIÓN EN EL LADO DERECHO ────────────
                if (nroPol) {
                    const qrUrl = window.location.origin + '/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=' + encodeURIComponent(nroPol);
                    const qrDataURI = await generarQRDataURL(qrUrl);
                    if (qrDataURI) {
                        const dataURItoBytes = (uri) => {
                            const b64 = uri.split(',')[1];
                            const bin = atob(b64);
                            const arr = new Uint8Array(bin.length);
                            for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
                            return arr;
                        };
                        const qrBytes = dataURItoBytes(qrDataURI);
                        const qrImg = await pdfDoc.embedPng(qrBytes);
                        
                        // Verificar si existe mapeo dinámico en la BD para el QR
                        const mappedQR = dbMapeo ? dbMapeo.campos.find(c => c.variable === 'sistema.qr_msqf') : null;
                        
                        if (dbMapeo && !mappedQR) {
                            console.log("[Marbete Patria] Omitiendo dibujo de QR porque no está mapeado en la BD.");
                        } else {
                            let QS = 30; // Tamaño del QR (reducido para caber bajo el logo)
                            // ── QR BAJO EL LOGO PATRIA EN EL REVERSO ──────────────────
                            // El logo Patria está en la esquina sup-der del reverso (~x=493, y=655).
                            // El QR va justo debajo del logo, en el espacio libre antes
                            // del bloque de texto de emergencias.
                            let qrX = 493; // Centrado bajo el logo Patria del reverso
                            let qrY = 626; // Bajo el logo, arriba del texto de emergencia
                            
                            if (mappedQR) {
                                const pageW_pt = firstPage.getWidth();
                                const pageH_pt = firstPage.getHeight();
                                const plantW_mm = parseFloat(dbMapeo.plantilla.ancho_mm) || 210.0;
                                const plantH_mm = parseFloat(dbMapeo.plantilla.alto_mm) || 297.0;

                                const mappedWidth = parseFloat(mappedQR.ancho || mappedQR.font_size);
                                if (mappedWidth > 0) {
                                    QS = (mappedWidth / plantW_mm) * pageW_pt;
                                }
                                qrX = (parseFloat(mappedQR.pos_x) / plantW_mm) * pageW_pt;
                                const y_top = pageH_pt - (parseFloat(mappedQR.pos_y) / plantH_mm) * pageH_pt;
                                qrY = y_top - QS;
                            }
                            
                            // Rectángulo blanco limpia el área del QR
                            firstPage.drawRectangle({
                                x: qrX - 2,
                                y: qrY - 11,
                                width: QS + 4,
                                height: QS + 13,
                                color: rgb(1, 1, 1)
                            });
                            
                            firstPage.drawImage(qrImg, { x: qrX, y: qrY, width: QS, height: QS });
                            
                            // Texto "VERIFICACIÓN EN LÍNEA" centrado bajo el QR
                            const wVerif = fontBold.widthOfTextAtSize('VERIFICACIÓN', 4);
                            const wLinea = fontBold.widthOfTextAtSize('EN LÍNEA', 4);
                            
                            firstPage.drawText('VERIFICACIÓN', {
                                x: qrX + (QS - wVerif) / 2,
                                y: qrY - 4,
                                size: 4,
                                font: fontBold,
                                color: rgb(0, 0.2, 0.6)
                            });
                            firstPage.drawText('EN LÍNEA', {
                                x: qrX + (QS - wLinea) / 2,
                                y: qrY - 9,
                                size: 4,
                                font: fontBold,
                                color: rgb(0, 0.2, 0.6)
                            });
                        }
                    }
                }

                
                // Guardar y retornar / descargar PDF
                const pdfBytes = await pdfDoc.save();
                if (opts && opts.returnBase64) {
                    let binary = '';
                    const chunkSize = 8192;
                    for (let i = 0; i < pdfBytes.byteLength; i += chunkSize) {
                        binary += String.fromCharCode.apply(null, pdfBytes.subarray(i, i + chunkSize));
                    }
                    return window.btoa(binary);
                } else {
                    const blob = new Blob([pdfBytes], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Marbete_Patria_${nroPol}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    return;
                }
            } catch (err) {
                console.error('[Marbete] Error en plantilla oficial de Patria:', err);
                // Fallback automático al generador por código si falla la carga del PDF
            }
        }

        // ── INTERCEPCIÓN DE MARBETE OFICIAL DE SEGUROS PEPÍN ─────────────
        if (asegNombre.includes('PEPIN') || asegNombre.includes('PEPÍN')) {
            try {
                if (typeof PDFLib === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = '../assets/lib/pdf-lib.min.js';
                        script.onload = resolve;
                        script.onerror = () => {
                            const cdnScript = document.createElement('script');
                            cdnScript.src = 'https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js';
                            cdnScript.onload = resolve;
                            cdnScript.onerror = () => reject(new Error('No se pudo cargar pdf-lib'));
                            document.head.appendChild(cdnScript);
                        };
                        document.head.appendChild(script);
                    });
                }
                
                const { PDFDocument, rgb, StandardFonts } = PDFLib;
                
                // Fetch de la plantilla oficial de Pepín
                const templateUrl = '/PLATAFORMA_INTEGRADA/backend/uploads/plantillas_pdf/MARBETE BUS GRANDE.pdf';
                const fileRes = await fetch(templateUrl);
                if (!fileRes.ok) throw new Error('No se pudo cargar la plantilla oficial de Pepín');
                const fileBuffer = await fileRes.arrayBuffer();
                
                // Crear el nuevo documento PDF A4
                const pdfDoc = await PDFDocument.create();
                const firstPage = pdfDoc.addPage([595.27, 841.89]); // A4
                
                // Embed de la primera página del template de Pepín
                const [pepinPage] = await pdfDoc.embedPdf(fileBuffer, [0]);
                
                // Dibujar la página de Pepín desplazada para que la tarjeta de arriba
                // quede a la izquierda de la página A4:
                // card original en plantilla: Xt=188, Yt=638 (alto de Letter es 792)
                // destino en A4: Xf=66, Yf=629 (alto de A4 es 841.89)
                // Desplazamiento: dx = 66 - 188 = -122, dy = 629 - 638 = -9
                firstPage.drawPage(pepinPage, {
                    x: -122,
                    y: -9,
                    width: 612,
                    height: 792
                });
                
                // Borrar todo lo que quede fuera de la tarjeta de arriba y del área del marbete
                // Borrar parte inferior (debajo de Y = 629)
                firstPage.drawRectangle({
                    x: 0,
                    y: 0,
                    width: 595.27,
                    height: 628,
                    color: rgb(1, 1, 1)
                });
                // Borrar parte superior (arriba de Y = 772)
                firstPage.drawRectangle({
                    x: 0,
                    y: 772,
                    width: 595.27,
                    height: 70,
                    color: rgb(1, 1, 1)
                });
                // Borrar lados (fuera de X=66 a X=529)
                firstPage.drawRectangle({
                    x: 0,
                    y: 628,
                    width: 65,
                    height: 145,
                    color: rgb(1, 1, 1)
                });
                firstPage.drawRectangle({
                    x: 530,
                    y: 628,
                    width: 66,
                    height: 145,
                    color: rgb(1, 1, 1)
                });
                
                // ── BORRAR VALORES PRE-LLENADOS EN LA TARJETA IZQUIERDA (Blanquear) ──
                // Asegurado, Marca, Chasis, Placa, Tipo: Yf = 725, 715, 705, 695, 682
                firstPage.drawRectangle({ x: 129, y: 723, width: 161, height: 12, color: rgb(1, 1, 1) }); // Asegurado
                firstPage.drawRectangle({ x: 129, y: 713, width: 161, height: 12, color: rgb(1, 1, 1) }); // Marca
                firstPage.drawRectangle({ x: 129, y: 703, width: 161, height: 12, color: rgb(1, 1, 1) }); // Chasis
                firstPage.drawRectangle({ x: 129, y: 693, width: 78,  height: 12, color: rgb(1, 1, 1) }); // Placa
                firstPage.drawRectangle({ x: 224, y: 693, width: 66,  height: 12, color: rgb(1, 1, 1) }); // Año value only (mantiene pre-printed label 'Año')
                firstPage.drawRectangle({ x: 129, y: 681, width: 161, height: 12, color: rgb(1, 1, 1) }); // Tipo
                
                // Fianza, Inicio, Fin (X = 145 a 235)
                firstPage.drawRectangle({ x: 145, y: 668, width: 90,  height: 12, color: rgb(1, 1, 1) }); // Fianza
                firstPage.drawRectangle({ x: 145, y: 658, width: 90,  height: 12, color: rgb(1, 1, 1) }); // Inicio
                firstPage.drawRectangle({ x: 145, y: 648, width: 90,  height: 12, color: rgb(1, 1, 1) }); // Fin
                
                // Póliza y Vehículo en esquina superior derecha (alineado a la izquierda a x=225 para no borrar los dos puntos preimpresos)
                firstPage.drawRectangle({ x: 223, y: 740, width: 67,  height: 24, color: rgb(1, 1, 1) });
                
                // Fuentes
                const fontRegular = await pdfDoc.embedFont(StandardFonts.Helvetica);
                const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
                
                // Helper para dbuixar texto
                const draw = (txt, x, y, size = 6.8, isBold = false, color = rgb(0.15, 0.15, 0.15)) => {
                    firstPage.drawText(String(txt || ''), {
                        x: x,
                        y: y,
                        size: size,
                        font: isBold ? fontBold : fontRegular,
                        color: color
                    });
                };
                
                // Formatear fechas
                const fmtC = (f) => {
                    if (!f) return 'N/A';
                    const d = new Date(f);
                    return `${String(d.getDate()).padStart(2,'0')}-${String(d.getMonth()+1).padStart(2,'0')}-${d.getFullYear()}`;
                };
                
                // Resolver valores dinámicos
                const clienteNombre = (poliza.cliente_nombre || 'A QUIEN CORRESPONDA').toUpperCase();
                const nroPol = poliza.numero_poliza || 'N/A';
                const tipoVeh = (vehiculo?.tipo_vehiculo || 'AUTOBUS').toUpperCase();
                const chasis = vehiculo?.chasis || 'N/A';
                const placa = vehiculo?.placa || 'N/A';
                const marca = (vehiculo?.marca || '').toUpperCase();
                const modelo = (vehiculo?.modelo || '').toUpperCase();
                const marcaModelo = `${marca} ${modelo}`.trim() || 'N/A';
                const anio = vehiculo?.anio || 'N/A';
                const uso = (vehiculo?.uso || 'PRIVADO').toUpperCase();
                const fianzaJud = fmtDOP(poliza.fianza_judicial || 50000);
                const fechaIni = fmtC(poliza.fecha_emision);
                const fechaFin = fmtC(poliza.fecha_vencimiento);
                
                // Helper para dibujar campo de la base de datos
                const drawFieldDB = (campo) => {
                    if (campo.variable === 'sistema.qr_msqf') return;
                    const val = (() => {
                        if (!campo.variable && campo.nombre_campo_pdf) {
                            return campo.nombre_campo_pdf;
                        }
                        switch(campo.variable) {
                            case 'cliente.nombre': return clienteNombre;
                            case 'cliente.cedula': return poliza.cliente_cedula || 'N/A';
                            case 'cliente.telefono': return poliza.cliente_telefono || 'N/A';
                            case 'cliente.email': return poliza.cliente_email || 'N/A';
                            case 'poliza.numero_poliza': return nroPol;
                            case 'vehiculo.tipo_vehiculo': return tipoVeh;
                            case 'vehiculo.chasis': return chasis;
                            case 'vehiculo.placa': return placa;
                            case 'poliza.fecha_inicio': return fechaIni;
                            case 'poliza.fecha_fin': return fechaFin;
                            case 'vehiculo.uso': return uso;
                            case 'vehiculo.marca': return (vehiculo?.marca || '').toUpperCase();
                            case 'vehiculo.modelo': return (vehiculo?.modelo || '').toUpperCase();
                            case 'vehiculo.anio': return anio;
                            case 'poliza.objeto_fianza': return poliza.objeto_fianza || 'FIANZA DE LEY';
                            case 'poliza.fianza':
                            case 'poliza.fianza_judicial': return fmtDOP(poliza.fianza_judicial || 50000);
                            default: return '';
                        }
                    })();
                    if (!val) return;
                    
                    const plantW_mm = parseFloat(dbMapeo.plantilla.ancho_mm) || 215.9;
                    const plantH_mm = parseFloat(dbMapeo.plantilla.alto_mm) || 279.4;

                    // Convertir a puntos de la plantilla Letter (612x792) y aplicar el desplazamiento de la tarjeta
                    const size = parseFloat(campo.font_size) || 7.0;
                    const x_pt = (parseFloat(campo.pos_x) / plantW_mm) * 612 - 122;
                    const y_pt = ((792 - (parseFloat(campo.pos_y) / plantH_mm) * 792) - 9) - size;
                    
                    const font = campo.font_weight === 'bold' ? fontBold : fontRegular;
                    
                    const hex = (campo.color || '#000000').replace('#', '');
                    let r_val = 0.15, g_val = 0.15, b_val = 0.15;
                    if (hex.length === 6) {
                        r_val = parseInt(hex.substring(0, 2), 16) / 255;
                        g_val = parseInt(hex.substring(2, 4), 16) / 255;
                        b_val = parseInt(hex.substring(4, 6), 16) / 255;
                    }

                    firstPage.drawText(String(val), {
                        x: x_pt,
                        y: y_pt,
                        size: size,
                        font: font,
                        color: rgb(r_val, g_val, b_val)
                    });
                };

                if (dbMapeo) {
                    dbMapeo.campos.forEach(drawFieldDB);
                } else {
                    // Escribir datos en la tarjeta izquierda (front)
                    draw(nroPol, 225, 753.5, 5.0, false); // Poliza (alineado a x=225 y verticalmente a 753.5 para no salirse y alinearse con label)
                    draw('0001', 225, 742.5, 6.8, false);  // Vehiculo (dummy 0001, alineado a x=225 y verticalmente a 742.5)
                    
                    draw(clienteNombre, 129, 725, 6.8, false);
                    draw(marcaModelo, 129, 715, 6.8, false);
                    draw(chasis, 129, 705, 6.2, false);
                    draw(placa, 129, 695, 6.8, false);
                    draw(`: ${anio}`, 224, 696.5, 6.8, false); // Dibuja ': año' al lado del label 'Año' pre-printed
                    draw(uso, 129, 683, 6.8, false);
                    draw(fianzaJud, 145, 670, 6.8, false);
                    draw(fechaIni, 145, 660, 6.8, false);
                    draw(fechaFin, 145, 650, 6.8, false);
                }
                
                // ── DIBUJAR TARJETA DERECHA (REVERSO) ──
                // Borde de la tarjeta derecha
                firstPage.drawRectangle({
                    x: 301,
                    y: 629,
                    width: 228,
                    height: 142,
                    borderColor: rgb(0.54, 0.45, 0.27),
                    borderWidth: 0.5
                });
                
                // Escribir cabeceras centradas en X = 415
                const drawCentered = (txt, y, size, isBold = true, color = rgb(0.15, 0.15, 0.15)) => {
                    const f = isBold ? fontBold : fontRegular;
                    const tw = f.widthOfTextAtSize(txt, size);
                    firstPage.drawText(txt, {
                        x: 415 - tw / 2,
                        y: y,
                        size: size,
                        font: f,
                        color: color
                    });
                };
                
                drawCentered('EN CASO DE ACCIDENTE PARA LEVANTAMIENTO DE ACTA POLICIAL FAVOR', 754, 4.0, true);
                drawCentered('DIRÍJASE A LA CASA ASISTENCIAL CONTRATADA', 748, 4.0, true);
                drawCentered('003349 +QF (Autos)            RD-0004', 739, 5.5, true);
                
                // Helper para dibujar párrafos envueltos y centrados
                const drawWrappedCentered = (txt, yStart, maxW, fontSize, lineH) => {
                    const words = txt.split(' ');
                    let lines = [];
                    let curr = '';
                    for (let w of words) {
                        const test = curr ? curr + ' ' + w : w;
                        const tw = fontRegular.widthOfTextAtSize(test, fontSize);
                        if (tw > maxW) {
                            lines.push(curr);
                            curr = w;
                        } else {
                            curr = test;
                        }
                    }
                    if (curr) lines.push(curr);
                    
                    let y = yStart;
                    for (let line of lines) {
                        const tw = fontRegular.widthOfTextAtSize(line, fontSize);
                        firstPage.drawText(line, {
                            x: 415 - tw / 2,
                            y: y,
                            size: fontSize,
                            font: fontRegular,
                            color: rgb(0.15, 0.15, 0.15)
                        });
                        y -= lineH;
                    }
                    return y;
                };
                
                // Dibujar párrafos (desplazados a Y=732 para dar espacio al QR)
                let yPos = 732;
                const paragraphs = [
                    "LA CASA DEL CONDUCTOR(CMA): Av. Simón Bolivar Num. 183, Ens. La Julia, Santo Domingo 10109, D. N. N.Telefono:(809)381.2424 / Santiago,Telefono:(809)241.4848 Solicitud de Apertura y gestión de Reclamos",
                    "CENTRO ASISTENCIAL DEL AUTOMOVILISTA(CAA): Av. 27 de Febrero num.452, casi Esq Ave. Nuñez de Caceres,Santo Domingo, D. N. /Telefono.(809)565.8222 / Santiago Telefono.(809)565.8222",
                    "EN CASO DE INCONVENIENTE CON SU VEHICULO (Grua,Recarga de Bateria,Gasolina,Gomas Pinchadas)COMUNICARSE CON SU ASISTENCIA VIAL: Teléfono (809)273.2021",
                    "EN CASO DE ROBO DE SU VEHICULO, Notifiquelo inmediatamente a la policia. SEGUROS PEPIN, S.A. Teléfonos:(809)412-1006 / Av. 27 de Febrero No. 233, Ensanche La Esperilla, Santo Domingo, D.N."
                ];
                
                for (let p of paragraphs) {
                    yPos = drawWrappedCentered(p, yPos, 210, 4.0, 5.0) - 2.0;
                }
                
                // ── INCORPORAR QR DE VALIDACIÓN EN EL LADO DERECHO ────────────
                if (nroPol) {
                    const qrUrl = window.location.origin + '/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=' + encodeURIComponent(nroPol);
                    const qrDataURI = await generarQRDataURL(qrUrl);
                    if (qrDataURI) {
                        const dataURItoBytes = (uri) => {
                            const b64 = uri.split(',')[1];
                            const bin = atob(b64);
                            const arr = new Uint8Array(bin.length);
                            for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
                            return arr;
                        };
                        const qrBytes = dataURItoBytes(qrDataURI);
                        const qrImg = await pdfDoc.embedPng(qrBytes);
                        
                        // Verificar si existe mapeo dinámico en la BD para el QR
                        const mappedQR = dbMapeo ? dbMapeo.campos.find(c => c.variable === 'sistema.qr_msqf') : null;
                        
                        if (dbMapeo && !mappedQR) {
                            console.log("[Marbete Pepin] Omitiendo dibujo de QR porque no está mapeado en la BD.");
                        } else {
                            let QS = 35; // Tamaño del QR (reducido a 35 pt)
                            let qrX = 529 - QS - 8; // Alineado a la derecha de la tarjeta derecha
                            let qrY = 629 + 23;     // Altura Y desplazada hacia arriba para evitar salirse del marbete
                            
                            if (mappedQR) {
                                const plantW_mm = parseFloat(dbMapeo.plantilla.ancho_mm) || 215.9;
                                const plantH_mm = parseFloat(dbMapeo.plantilla.alto_mm) || 279.4;
                                
                                const mappedWidth = parseFloat(mappedQR.ancho || mappedQR.font_size);
                                if (mappedWidth > 0) {
                                    QS = (mappedWidth / plantW_mm) * 612;
                                }
                                qrX = (parseFloat(mappedQR.pos_x) / plantW_mm) * 612 - 122;
                                const y_top = (792 - (parseFloat(mappedQR.pos_y) / plantH_mm) * 792) - 9;
                                qrY = y_top - QS;
                            }
                            
                            // Solid white square to clear text under QR and its labels
                            firstPage.drawRectangle({
                                x: qrX - 2,
                                y: qrY - 12,
                                width: QS + 4,
                                height: QS + 14,
                                color: rgb(1, 1, 1)
                            });
                            
                            firstPage.drawImage(qrImg, { x: qrX, y: qrY, width: QS, height: QS });
                            
                            // Texto "VERIFICACIÓN EN LÍNEA" centrado bajo el QR
                            const wVerif = fontBold.widthOfTextAtSize('VERIFICACIÓN', 4.5);
                            const wLinea = fontBold.widthOfTextAtSize('EN LÍNEA', 4.5);
                            
                            firstPage.drawText('VERIFICACIÓN', {
                                x: qrX + (QS - wVerif) / 2,
                                y: qrY - 5,
                                size: 4.5,
                                font: fontBold,
                                color: rgb(0, 0.2, 0.6)
                            });
                            firstPage.drawText('EN LÍNEA', {
                                x: qrX + (QS - wLinea) / 2,
                                y: qrY - 10,
                                size: 4.5,
                                font: fontBold,
                                color: rgb(0, 0.2, 0.6)
                            });
                        }
                    }
                }
                
                // Guardar y retornar / descargar PDF
                const pdfBytes = await pdfDoc.save();
                if (opts && opts.returnBase64) {
                    let binary = '';
                    const chunkSize = 8192;
                    for (let i = 0; i < pdfBytes.byteLength; i += chunkSize) {
                        binary += String.fromCharCode.apply(null, pdfBytes.subarray(i, i + chunkSize));
                    }
                    return window.btoa(binary);
                } else {
                    const blob = new Blob([pdfBytes], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Marbete_Pepin_${nroPol}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    return;
                }
            } catch (err) {
                console.error('[Marbete] Error en plantilla oficial de Pepín:', err);
                // Fallback automático al generador por código si falla la carga del PDF
            }
        }

        // ── INTERCEPCIÓN DE MARBETE OFICIAL DE MIDAS SEGUROS ─────────────────
        if (asegNombre.includes('MIDAS')) {
            try {
                if (typeof PDFLib === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = '../assets/lib/pdf-lib.min.js';
                        script.onload = resolve;
                        script.onerror = () => {
                            const cdnScript = document.createElement('script');
                            cdnScript.src = 'https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js';
                            cdnScript.onload = resolve;
                            cdnScript.onerror = () => reject(new Error('No se pudo cargar pdf-lib'));
                            document.head.appendChild(cdnScript);
                        };
                        document.head.appendChild(script);
                    });
                }

                const { PDFDocument, rgb, StandardFonts } = PDFLib;

                // 1. Crear documento A4 y cargar la imagen de fondo oficial
                const pdfDoc = await PDFDocument.create();
                const firstPage = pdfDoc.addPage([595.27, 841.89]); // A4 Portrait

                // 2. Montar fondo del marbete (de la BD si existe, o PNG por defecto)
                const BG_W = 490;
                const BG_X = (595.27 - BG_W) / 2;
                const BG_Y = 660;
                let BG_H = 164.46; // ratio por defecto ~2.98
                let bgImg = null;
                let midasPage = null;
                let bgBuffer = null;

                if (dbMapeo) {
                    const bgUrl = '/PLATAFORMA_INTEGRADA/' + dbMapeo.plantilla.archivo_base;
                    const bgRes = await fetch(bgUrl);
                    if (!bgRes.ok) throw new Error('No se pudo cargar la plantilla de Midas de la BD');
                    bgBuffer = await bgRes.arrayBuffer();
                    const [embeddedPage] = await pdfDoc.embedPdf(bgBuffer, [0]);
                    midasPage = embeddedPage;
                    
                    const pW_mm = parseFloat(dbMapeo.plantilla.ancho_mm) || 431.53;
                    const pH_mm = parseFloat(dbMapeo.plantilla.alto_mm) || 144.83;
                    const ratio = pW_mm / pH_mm;
                    BG_H = BG_W / ratio;

                    firstPage.drawPage(midasPage, {
                        x: BG_X,
                        y: BG_Y,
                        width: BG_W,
                        height: BG_H
                    });
                } else {
                    const bgUrl = '/PLATAFORMA_INTEGRADA/backend/uploads/plantillas_pdf/MARBETE_MIDAS_SEGUROS-NEW.png';
                    const bgRes = await fetch(bgUrl);
                    if (!bgRes.ok) throw new Error('No se pudo cargar la plantilla oficial de Midas');
                    bgBuffer = await bgRes.arrayBuffer();
                    bgImg = await pdfDoc.embedPng(bgBuffer);
                    BG_H = BG_W / (bgImg.width / bgImg.height);

                    firstPage.drawImage(bgImg, {
                        x: BG_X,
                        y: BG_Y,
                        width: BG_W,
                        height: BG_H
                    });
                }

                // 3. Fuentes
                const fontReg  = await pdfDoc.embedFont(StandardFonts.Helvetica);
                const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

                // 4. Resolver datos de la póliza
                const clienteNombre = (poliza.cliente_nombre || 'A QUIEN CORRESPONDA').toUpperCase();
                const nroPol  = poliza.numero_poliza || 'N/A';
                const chasis  = vehiculo?.chasis  || 'N/A';
                const placa   = vehiculo?.placa   || 'N/A';
                const marcaModelo = `${vehiculo?.marca || ''} ${vehiculo?.modelo || ''}`.trim().toUpperCase() || 'N/A';
                const anio    = vehiculo?.anio    || 'N/A';
                const uso     = (vehiculo?.uso    || 'PRIVADO').toUpperCase();
                const tipoVeh = (vehiculo?.tipo_vehiculo || 'AUTOMÓVIL').toUpperCase();

                const fmtC = (f) => {
                    if (!f) return 'N/A';
                    const d = new Date(f);
                    return `${String(d.getDate()).padStart(2,'0')}-${String(d.getMonth()+1).padStart(2,'0')}-${d.getFullYear()}`;
                };
                const vigenciaStr = `${fmtC(poliza.fecha_emision)} al ${fmtC(poliza.fecha_vencimiento)}`;

                // Helper para dibujar campo de la base de datos
                const drawFieldDB = (campo) => {
                    if (campo.variable === 'sistema.qr_msqf') return;
                    const val = (() => {
                        if (!campo.variable && campo.nombre_campo_pdf) {
                            return campo.nombre_campo_pdf;
                        }
                        switch(campo.variable) {
                            case 'cliente.nombre': return clienteNombre;
                            case 'cliente.cedula': return poliza.cliente_cedula || 'N/A';
                            case 'cliente.telefono': return poliza.cliente_telefono || 'N/A';
                            case 'cliente.email': return poliza.cliente_email || 'N/A';
                            case 'poliza.numero_poliza': return nroPol;
                            case 'vehiculo.tipo_vehiculo': return tipoVeh;
                            case 'vehiculo.chasis': return chasis;
                            case 'vehiculo.placa': return placa;
                            case 'poliza.fecha_inicio': return fmtC(poliza.fecha_emision);
                            case 'poliza.fecha_fin': return fmtC(poliza.fecha_vencimiento);
                            case 'vehiculo.uso': return uso;
                            case 'vehiculo.marca': return (vehiculo?.marca || '').toUpperCase();
                            case 'vehiculo.modelo': return (vehiculo?.modelo || '').toUpperCase();
                            case 'vehiculo.anio': return anio;
                            case 'poliza.objeto_fianza': return poliza.objeto_fianza || 'FIANZA DE LEY';
                            case 'poliza.fianza':
                            case 'poliza.fianza_judicial': return fmtDOP(poliza.fianza_judicial || 50000);
                            default: return '';
                        }
                    })();
                    if (!val) return;
                    
                    const plantW_mm = parseFloat(dbMapeo.plantilla.ancho_mm) || 431.53;
                    const plantH_mm = parseFloat(dbMapeo.plantilla.alto_mm) || 144.83;
                    
                    const size = parseFloat(campo.font_size) || 7.0;
                    const font = campo.font_weight === 'bold' ? fontBold : fontReg;

                    // Convertir a puntos del marbete, aplicar traducción y escala, y corregir la línea base con el tamaño de letra
                    const x_pt = BG_X + (parseFloat(campo.pos_x) / plantW_mm) * BG_W;
                    const y_pt = BG_Y + BG_H - (parseFloat(campo.pos_y) / plantH_mm) * BG_H - size;
                    
                    const hex = (campo.color || '#000000').replace('#', '');
                    let r_val = 0.08, g_val = 0.08, b_val = 0.08;
                    if (hex.length === 6) {
                        r_val = parseInt(hex.substring(0, 2), 16) / 255;
                        g_val = parseInt(hex.substring(2, 4), 16) / 255;
                        b_val = parseInt(hex.substring(4, 6), 16) / 255;
                    }

                    firstPage.drawText(String(val), {
                        x: x_pt,
                        y: y_pt,
                        size: size,
                        font: font,
                        color: rgb(r_val, g_val, b_val)
                    });
                };

                // 5. Dibujar datos sobre el fondo del marbete
                if (dbMapeo) {
                    dbMapeo.campos.forEach(drawFieldDB);
                } else {
                    const draw = (txt, x, y, size = 6.5, bold = false) => {
                        firstPage.drawText(String(txt || ''), {
                            x, y, size,
                            font: bold ? fontBold : fontReg,
                            color: rgb(0.08, 0.08, 0.08)
                        });
                    };

                    // Coordenadas absolutas en la página A4 (calibradas al panel izquierdo del PNG)
                    // El anverso del marbete Midas tiene etiquetas a la izquierda y valores a la derecha
                    const COL1 = BG_X + 60;  // Columna de valores principales (después de etiquetas)
                    const COL2 = BG_X + 165; // Columna derecha (SERVICIO/MARCA/AÑO)
                    const TOP  = BG_Y + BG_H - 36; // Primera línea de datos (debajo del header con logo)
                    const LH   = 11;         // Separación entre líneas

                    // Columna izquierda: datos del asegurado y vehículo
                    draw(clienteNombre,  COL1, TOP,          6.5);  // Nombre cliente
                    draw(nroPol,         COL1, TOP - LH,     6.5);  // Póliza No.
                    draw(tipoVeh,        COL1, TOP - LH*2,   6.5);  // Tipo vehículo
                    draw(chasis,         COL1, TOP - LH*3,   6.0);  // Chasis
                    draw(placa,          COL1, TOP - LH*4,   6.5);  // Placa
                    draw(vigenciaStr,    COL1, TOP - LH*5,   5.8);  // Vigencia
                    draw('MÁS QUE FIANZAS', COL1, TOP - LH*6, 6.5); // Intermediario

                    // Columna derecha: servicio, marca, año
                    draw(uso,            COL2, TOP - LH*2,   6.5);  // Servicio/Uso
                    draw(marcaModelo,    COL2, TOP - LH*3,   6.5);  // Marca/Modelo
                    draw(anio,           COL2, TOP - LH*4,   6.5);  // Año
                }

                // 6. Código QR de verificación (en el panel derecho/reverso)
                if (nroPol) {
                    const qrUrl = window.location.origin + '/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=' + encodeURIComponent(nroPol);
                    const qrDataURI = await generarQRDataURL(qrUrl);
                    if (qrDataURI) {
                        const dataURItoBytes = (uri) => {
                            const b64 = uri.split(',')[1];
                            const bin = atob(b64);
                            const arr = new Uint8Array(bin.length);
                            for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
                            return arr;
                        };
                        const qrBytes = dataURItoBytes(qrDataURI);
                        const qrImg = await pdfDoc.embedPng(qrBytes);
                        
                        // Verificar si existe mapeo dinámico en la BD para el QR
                        const mappedQR = dbMapeo ? dbMapeo.campos.find(c => c.variable === 'sistema.qr_msqf') : null;
                        
                        if (dbMapeo && !mappedQR) {
                            console.log("[Marbete Midas] Omitiendo dibujo de QR porque no está mapeado en la BD.");
                        } else {
                            let QS = 34;
                            // QR en la esquina inferior-derecha del reverso (panel derecho)
                            let qrX = BG_X + BG_W - QS - 10;
                            let qrY = BG_Y + 12;
                            
                            if (mappedQR) {
                                const plantW_mm = parseFloat(dbMapeo.plantilla.ancho_mm) || 431.53;
                                const plantH_mm = parseFloat(dbMapeo.plantilla.alto_mm) || 144.83;
                                
                                const mappedWidth = parseFloat(mappedQR.ancho || mappedQR.font_size);
                                if (mappedWidth > 0) {
                                    QS = (mappedWidth / plantW_mm) * BG_W;
                                }
                                qrX = BG_X + (parseFloat(mappedQR.pos_x) / plantW_mm) * BG_W;
                                
                                // Centrar verticalmente el QR respecto a la caja rectangular del mapeador (alto fijo de 22px / 6.21mm en frontend)
                                const mappedBoxHeight_mm = 6.21;
                                const y_center = BG_Y + BG_H - ((parseFloat(mappedQR.pos_y) + (mappedBoxHeight_mm / 2)) / plantH_mm) * BG_H;
                                qrY = y_center - (QS / 2);
                            }

                            firstPage.drawRectangle({ x: qrX - 2, y: qrY - 2, width: QS + 4, height: QS + 4, color: rgb(1,1,1) });
                            firstPage.drawImage(qrImg, { x: qrX, y: qrY, width: QS, height: QS });

                            const label = 'VERIFICAR';
                            const wL = fontBold.widthOfTextAtSize(label, 4.5);
                            firstPage.drawText(label, { x: qrX + (QS - wL)/2, y: qrY - 8, size: 4.5, font: fontBold, color: rgb(0, 0.3, 0.7) });
                        }
                    }
                }

                // 7. Guardar y descargar / retornar
                const pdfBytes = await pdfDoc.save();
                if (opts && opts.returnBase64) {
                    let binary = '';
                    const chunkSize = 8192;
                    for (let i = 0; i < pdfBytes.byteLength; i += chunkSize) {
                        binary += String.fromCharCode.apply(null, pdfBytes.subarray(i, i + chunkSize));
                    }
                    return window.btoa(binary);
                } else {
                    const blob = new Blob([pdfBytes], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Marbete_Midas_${nroPol}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    return;
                }
            } catch (err) {
                console.error('[Marbete] Error en plantilla oficial de Midas Seguros:', err);
                // Fallback al generador dinámico si falla la carga del PNG
            }
        }

        // Intercepción dinámica (plantilla subida en Modelador)
        if (asegNombre) {
            try {
                const rP = await fetch(`/PLATAFORMA_INTEGRADA/backend/api/pdf_plantillas.php?_t=${Date.now()}`);
                const jP = await rP.json();
                if (jP.exito && jP.data) {
                    const ref = jP.data.find(p => p.aseguradora_nombre && p.aseguradora_nombre.toUpperCase().trim() === asegNombre);
                    if (ref) {
                        const rF = await fetch(`/PLATAFORMA_INTEGRADA/backend/api/pdf_plantillas.php?id=${ref.id}&_t=${Date.now()}`);
                        const jF = await rF.json();
                        if (jF.exito && jF.data) {
                            const ctx = {
                                poliza: { numero_poliza: poliza.numero_poliza||'', fecha_emision: fmtFecha(poliza.fecha_emision), fecha_vencimiento: fmtFecha(poliza.fecha_vencimiento), fianza_judicial: fmtDOP(poliza.fianza_judicial||50000), fianza: fmtDOP(poliza.fianza_judicial||50000), casa_contratada: poliza.casa_contratada||'CENTRO DEL AUTOMOVILISTA', asistencia_vial: poliza.asistencia_vial||'PREMIUM', deduccion: poliza.deduccion||'N/A' },
                                vehiculo: { marca: vehiculo?.marca||'', anio: vehiculo?.anio||'', chasis: vehiculo?.chasis||'', placa: vehiculo?.placa||'', uso: vehiculo?.uso||'PRIVADO', tipo_vehiculo: (vehiculo?.tipo_vehiculo||'AUTOMOVIL').toUpperCase() },
                                cliente: { nombre: poliza.cliente_nombre||'', cedula: poliza.cliente_cedula||'' },
                                general: { hora_emision: new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}) }
                            };
                            if (opts && opts.returnBase64) {
                                const pdfBytes = await generarDocumentoDinamicoPDF(jF.data, ctx, { returnBytes: true });
                                // Validar que realmente es un Uint8Array con contenido
                                if (!pdfBytes || !(pdfBytes instanceof Uint8Array) || pdfBytes.byteLength === 0) {
                                    throw new Error('[Marbete] generarDocumentoDinamicoPDF no retornó bytes válidos');
                                }
                                // Conversión segura a base64 por chunks (evita Maximum call stack con PDFs grandes)
                                let binary = '';
                                const chunkSize = 8192;
                                for (let i = 0; i < pdfBytes.byteLength; i += chunkSize) {
                                    binary += String.fromCharCode.apply(null, pdfBytes.subarray(i, i + chunkSize));
                                }
                                return window.btoa(binary);
                            } else {
                                return await generarDocumentoDinamicoPDF(jF.data, ctx);
                            }
                        }
                    }
                }
            } catch(e) { console.warn('[Marbete] Fallback jsPDF:', e.message); }
        }

        // ── FALLBACK jsPDF ────────────────────────────────────────────────
        if (!window.jspdf || !window.jspdf.jsPDF) {
            throw new Error('La librería jsPDF no está disponible. Verifique que ../assets/lib/jspdf.umd.min.js esté accesible.');
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

        // helper para limpiar base64 de logos
        const cleanBase64 = (str) => {
            if (!str) return '';
            return str.replace(/[\r\n\s]+/g, '').replace(/"+/g, '');
        };

        // ── LAYOUT (medidas del marbete horizontal) ───────────────────────
        const MX   = 22;          // margen izq de la caja
        const MY   = 14;          // margen sup de la caja
        const BW   = 166;         // ancho de la caja (MX+BW = 188mm)
        const BH   = 72;          // alto de la caja
        const BX2  = MX + BW;     // borde derecho = 188mm
        const XDIV = MX + 83;     // divisor vertical = 105mm (50% del ancho)

        // helper de fuente
        const T = (sz, st='normal', r=0,g=0,b=0) => {
            doc.setFontSize(sz); doc.setFont('helvetica', st); doc.setTextColor(r,g,b);
        };

        // ── CAJA Y DIVISOR (líneas punteadas) ─────────────────────────────
        doc.setDrawColor(120); doc.setLineWidth(0.25);
        doc.setLineDash([0.6, 0.6], 0);
        
        // Línea horizontal superior
        doc.line(MX, MY, BX2, MY);
        // Línea horizontal inferior
        doc.line(MX, MY + BH, BX2, MY + BH);
        // Línea vertical divisora (centro)
        doc.line(XDIV, MY, XDIV, MY + BH);
        
        doc.setLineDash([], 0); // restablecer dash de línea

        // ── HEADER ───────────────────────────────────────────────────────
        // Zona A: título (izquierda)
        T(8, 'bold');
        doc.text('MARBETE SEGURO', MX+2, MY+6);
        doc.text((vehiculo?.tipo_vehiculo || 'AUTOMOVIL').toUpperCase(), MX+2, MY+11);

        // Zona B: Logo (centro de la columna izquierda)
        const LX = MX + 35; const LW = 43; const LH = 13;
        let logoB64 = null;
        if (window.LOGOS) {
            const cleanStr = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            const normKey = cleanStr(asegNombre);
            if (window.LOGOS[asegNombre]) {
                logoB64 = window.LOGOS[asegNombre];
            } else {
                const fk = Object.keys(window.LOGOS).find(k => {
                    const normK = cleanStr(k);
                    return normKey.includes(normK) || normK.includes(normKey);
                });
                if (fk) logoB64 = window.LOGOS[fk];
            }
        }
        if (!logoB64) {
            if (window.LOGO_MULTISEGUROS_B64) {
                logoB64 = window.LOGO_MULTISEGUROS_B64;
            } else if (window.LOGOS && window.LOGOS['MULTISEGUROS']) {
                logoB64 = window.LOGOS['MULTISEGUROS'];
            }
        }
        
        console.log(`[Marbete] Logo using insurer logo (${asegNombre}):`, logoB64 ? 'Found' : 'Not Found');
        const theme = POLIZA_DOCS.getTheme();
        if (logoB64) {
            try {
                const cleanedLogo = cleanBase64(logoB64);
                const imgFmt = cleanedLogo.startsWith('data:image/jpeg') ? 'JPEG' : 'PNG';
                
                // Cargar dimensiones del logo de manera asíncrona para evitar distorsiones
                const dims = await new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => resolve({ w: img.naturalWidth, h: img.naturalHeight });
                    img.onerror = () => resolve({ w: 0, h: 0 });
                    img.src = cleanedLogo;
                });
                
                if (dims.w > 0 && dims.h > 0) {
                    const maxW = 43;
                    const maxH = 13;
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
                    const lx = LX + (maxW - lw) / 2;
                    const ly = MY + 2 + (maxH - lh) / 2;
                    doc.addImage(cleanedLogo, imgFmt, lx, ly, lw, lh, undefined, 'FAST');
                } else {
                    doc.addImage(cleanedLogo, imgFmt, LX, MY+2, LW, LH);
                }
            } catch(e) {
                console.warn('Error adding logo image inside marbete fallback:', e);
                T(11, 'bold', 37, 99, 235); doc.text(asegNombre || 'Aseguradora', LX+LW/2, MY+8, {align:'center'});
                T(5.5, 'italic', 100, 100, 100); doc.text('Somos Su Alternativa', LX+LW/2, MY+12, {align:'center'});
            }
        } else {
            T(11, 'bold', 37, 99, 235); doc.text(asegNombre || 'Aseguradora', LX+LW/2, MY+8, {align:'center'});
            T(5.5, 'italic', 100, 100, 100); doc.text('Somos Su Alternativa', LX+LW/2, MY+12, {align:'center'});
        }

        // Zona C: texto asistencia (derecha, centrado)
        const colRightCenter = XDIV + (BX2 - XDIV) / 2;
        T(5, 'bold');
        doc.text('EN CASO DE ACCIDENTE PARA LEVANTAMIENTO DE ACTA POLICIAL FAVOR', colRightCenter, MY+5, {align:'center'});
        doc.text('DIRIJASE A LA CASA ASISTENCIAL CONTRATADA', colRightCenter, MY+9, {align:'center'});
        T(7.5, 'bold');
        doc.text('003349 +QF (Autos)            RD-0004', colRightCenter, MY+15, {align:'center'});

        // ── DATOS DE LA POLIZA (LADO IZQUIERDO) ──────────────────────────
        const col1Label = MX + 2;       // Póliza, Año, Registro...
        const col1Value = MX + 21;      // valores col 1
        const col2Label = MX + 41;      // Del, Deduc. Min, Uso, Modelo
        const col2Value = MX + 55;      // valores col 2
        const col3Value = MX + 63;      // al, fecha fin, hora fin

        const Y0 = MY + 23;  // primera fila
        const DY = 4.8;      // interlineado

        // helper campo
        const DF = (lbl, val, xe, xv, y, fs=6.8) => {
            T(6.8, 'bold'); doc.text(lbl, xe, y);
            T(fs, 'normal'); doc.text(String(val??'N/A'), xv, y);
        };

        // Fecha compacta dd-mm-aaaa
        const fmtC = (f) => {
            if (!f) return 'N/A';
            const d = new Date(f);
            return `${String(d.getDate()).padStart(2,'0')}-${String(d.getMonth()+1).padStart(2,'0')}-${d.getFullYear()}`;
        };

        const horaEmision = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }).toLowerCase();

        // F1: Póliza | Del / al
        T(6.8, 'bold'); doc.text('Póliza:', col1Label, Y0);
        T(6.8, 'normal'); doc.text(poliza.numero_poliza||'N/A', col1Value, Y0);
        T(6.5, 'bold');   doc.text('Del:', col2Label, Y0);
        T(6.5, 'normal'); doc.text(fmtC(poliza.fecha_emision), col2Label + 6, Y0);
        T(6.5, 'bold');   doc.text('al:', col3Value, Y0);
        T(6.5, 'normal'); doc.text(fmtC(poliza.fecha_vencimiento), col3Value + 4, Y0);

        // F2: Año Vehículo | Deduc. Min | Hora
        DF('Año Vehículo:', vehiculo?.anio||'N/A', col1Label, col1Value, Y0+DY);
        T(6.5, 'bold');   doc.text('Deduc. Min:', col2Label, Y0+DY);
        T(6.5, 'normal'); doc.text(String(poliza.deduccion||'N/A'), col2Value, Y0+DY);
        T(6.5, 'normal'); doc.text(horaEmision, col3Value + 4, Y0+DY);

        // F3: Registro | Uso
        DF('Registro:', vehiculo?.placa||'N/A', col1Label, col1Value, Y0+DY*2);
        DF('Uso:', (vehiculo?.uso||'PRIVADO').toUpperCase(), col2Label, col2Value, Y0+DY*2, 6.5);

        // F4: Marca | Modelo
        DF('Marca:', vehiculo?.marca||'N/A', col1Label, col1Value, Y0+DY*3);
        T(6.8, 'normal'); doc.text(vehiculo?.modelo||'', col2Value, Y0+DY*3);

        // F5: Chasis (spans across)
        DF('Chasis:', vehiculo?.chasis||'N/A', col1Label, col1Value, Y0+DY*4, 6.2);

        // F6: Tipo
        DF('Tipo:', (vehiculo?.tipo_vehiculo||'AUTOMOVIL').toUpperCase(), col1Label, col1Value, Y0+DY*5);

        // F7: Fianza Judicial
        DF('Fianza Judicial:', fmtDOP(poliza.fianza_judicial||50000), col1Label, col1Value, Y0+DY*6);

        // F8: Casa Contratada
        DF('Casa Contratada:', poliza.casa_contratada||'CENTRO DEL AUTOMOVILISTA', col1Label, col1Value, Y0+DY*7, 6.5);

        // F9: Asistencia Vial
        DF('Asistencia Vial:', poliza.asistencia_vial||'PREMIUM', col1Label, col1Value, Y0+DY*8);

        // ── BLOQUE ASISTENCIA DERECHO ────────────────────────────────────
        const BWR  = BX2 - XDIV - 6;   // ≈ 77mm
        let bY = MY + 20;
        T(5.2, 'normal');
        [
            'LA CASA DEL CONDUCTOR(CMA): Av. Simón Bolivar Num. 183, Ens. La Julia, Santo Domingo 10109, D. N.',
            'N.Telefono:(809)381.2424 / Santiago,Telefono:(809)241.4848 Solicitud de Apertura y gestión de Reclamos',
            'CENTRO ASISTENCIAL DEL AUTOMOVILISTA(CAA): Av. 27 de Febrero num.452, casi Esq Ave. Nuñez de Caceres,Santo Domingo, D. N. /Telefono.(809)565.8222 / Santiago Telefono.(809)565.8222',
            'EN CASO DE INCONVENIENTE CON SU VEHICULO (Grua,Recarga de Bateria,Gasolina,Gomas Pinchadas)COMUNICARSE CON SU ASISTENCIA VIAL: Teléfono (809)273.2021',
            'EN CASO DE ROBO DE SU VEHICULO, Notifiquelo inmediatamente a la policia. MULTISEGUROS SU, S.A. Teléfonos:(809)378.1784 / (829)826-5848 Av. Bolivar No. 952, Ensanche. La Julia, Santo Domingo, D.N.'
        ].forEach(txt => {
            const lines = doc.splitTextToSize(txt, BWR);
            doc.text(lines, colRightCenter, bY, {align:'center'});
            bY += lines.length * 1.8 + 1.2;
        });

        // ── CONDICIONES PARTICULARES ─────────────────────────────────────
        const YCP = MY + BH + 8;
        doc.setLineWidth(0.3); doc.setDrawColor(0); doc.line(MX, YCP-2, BX2, YCP-2);
        
        T(8.5, 'bold');
        doc.text('1. CONDICIONES PARTICULARES:', MX, YCP+3);
        const titleW = doc.getTextWidth('1. CONDICIONES PARTICULARES: ');

        T(8.5, 'normal');
        const condText = 'El cliente debe presentar al momento de un siniestro sus documentos vigentes, como: cédula de identidad, matrícula del vehículo a su nombre, o en su defecto acto de venta, y licencia de conducir al día. MultiSeguros SU se reserva el derecho de amparar pérdidas por la falta de alguno de estos documentos. Es aceptable la licencia de conducir expedida en el extranjero que se encuentre en vigencia. No otorgar seguros a extranjeros que no tengan todos sus documentos al día.';
        
        const words = condText.split(' ');
        let firstLine = '';
        let wordIndex = 0;
        while (wordIndex < words.length) {
            const testLine = firstLine ? firstLine + ' ' + words[wordIndex] : words[wordIndex];
            if (doc.getTextWidth(testLine) > (136 - titleW)) {
                break;
            }
            firstLine = testLine;
            wordIndex++;
        }
        doc.text(firstLine, MX + titleW, YCP + 3);
        
        const remainingText = words.slice(wordIndex).join(' ');
        const lines = doc.splitTextToSize(remainingText, 136);
        doc.text(lines, MX, YCP + 7.5);

        // ── QR CODE (Online Verification) ────────────────────────────────
        const qrUrl = `${POLIZA_DOCS.EMPRESA.base_url}/frontend/verificar-poliza.html?n=${poliza.numero_poliza}`;
        const qrImg = await generarQRDataURL(qrUrl);
        if (qrImg) {
            const QR_SIZE = 25;
            const qrX = BX2 - QR_SIZE - 2;
            const qrY = YCP + 1;
            doc.addImage(qrImg, 'PNG', qrX, qrY, QR_SIZE, QR_SIZE);
            
            // Draw master brand logo (+Que Fianzas) in the center of QR code
            const overlaySize = QR_SIZE * 0.22; // 5.5mm
            const overlayX = qrX + (QR_SIZE - overlaySize) / 2;
            const overlayY = qrY + (QR_SIZE - overlaySize) / 2;

            // Solid white square to clear QR dots in center
            doc.setFillColor(255, 255, 255);
            doc.rect(overlayX, overlayY, overlaySize, overlaySize, 'F');

            // Draw brand logo slightly smaller to have a white margin
            if (window.LOGO_MQF_B64) {
                try {
                    doc.addImage(window.LOGO_MQF_B64, 'PNG', overlayX + 0.5, overlayY + 0.5, overlaySize - 1.0, overlaySize - 1.0);
                } catch(eqr) {
                    console.warn('[Marbete] Error embedding brand logo inside QR:', eqr);
                }
            }

            T(5.5, 'bold', ...theme.primary);
            doc.text('VERIFICACIÓN', BX2 - QR_SIZE/2 - 2, YCP + QR_SIZE + 4, { align: 'center' });
            doc.text('EN LÍNEA', BX2 - QR_SIZE/2 - 2, YCP + QR_SIZE + 6, { align: 'center' });
        }

        if (opts && opts.returnBase64) {
            const dataUri = doc.output('datauristring');
            return dataUri.split(',')[1];
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

    const theme = POLIZA_DOCS.getTheme();

    doc.setFillColor(...theme.primary);
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
        doc.setFillColor(...theme.primary);
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
async function generarReciboPDF(poliza, cliente, pago, opts = {}) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const { COLORES, EMPRESA } = POLIZA_DOCS;
    const W = 210, margenL = 14, margenR = 196;

    const theme = POLIZA_DOCS.getTheme();

    // Fondo encabezado
    const esPendiente = (pago.estado_pago === 'pendiente');
    doc.setFillColor(...theme.primary);
    doc.rect(0, 0, W, 42, 'F');

    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', margenL, 6, 40, 18);
    }

    doc.setTextColor(...COLORES.blanco);
    if (esPendiente) {
        doc.setFontSize(16); doc.setFont('helvetica', 'bold');
        doc.text('RECIBO PROVISIONAL', W / 2, 15, { align: 'center' });
        doc.setFontSize(9); doc.setFont('helvetica', 'bold');
        doc.setTextColor(253, 224, 71); // Amarillo brillante
        doc.text('(PENDIENTE DE VALIDACIÓN)', W / 2, 21, { align: 'center' });
        doc.setTextColor(...COLORES.blanco);
    } else {
        doc.setFontSize(18); doc.setFont('helvetica', 'bold');
        doc.text('RECIBO OFICIAL DE PAGO', W / 2, 17, { align: 'center' });
    }
    
    doc.setFontSize(8); doc.setFont('helvetica', 'normal');
    doc.text(`N° Recibo: ${pago.numero_recibo || 'REC-' + new Date().getFullYear() + '-' + String(pago.id || '0001').padStart(4, '0')}`, W / 2, 27, { align: 'center' });

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
    doc.setFontSize(9); doc.setFont('helvetica', 'bold'); doc.setTextColor(...theme.primary);
    doc.text('DESCRIPCIÓN DEL SERVICIO', margenL, y);
    doc.setLineWidth(0.3); doc.setDrawColor(...theme.primary);
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
        headStyles: { fillColor: theme.primary, textColor: 255, fontStyle: 'bold', fontSize: 9 },
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
    doc.setFont('helvetica', 'bold'); doc.setTextColor(...theme.primary); doc.setFontSize(10);
    doc.text('TOTAL:', W - 88, y + 22);
    doc.text(fmtDOP(pago.monto), margenR, y + 22, { align: 'right' });

    y += 35;

    // Caja de banco/referencia si existe
    if (pago.banco || pago.numero_comprobante || pago.numero_referencia) {
        doc.setFillColor(248, 250, 252);
        doc.rect(margenL, y, 95, 18, 'F');
        doc.setDrawColor(226, 232, 240); doc.setLineWidth(0.2);
        doc.rect(margenL, y, 95, 18);
        
        doc.setFontSize(7.5); doc.setFont('helvetica', 'bold'); doc.setTextColor(...theme.primary);
        doc.text('INFORMACIÓN DE DEPÓSITO / TRANSFERENCIA', margenL + 3, y + 5);
        doc.setFont('helvetica', 'normal'); doc.setTextColor(80, 80, 80); doc.setFontSize(7);
        doc.text(`Banco Destino: ${pago.banco || 'N/A'}`, margenL + 3, y + 10);
        doc.text(`N° Ref/Comprobante: ${pago.numero_comprobante || pago.numero_referencia || 'N/A'}`, margenL + 3, y + 14);
        y += 24;
    } else {
        y += 5;
    }

    // QR Code (Validation & Accounting Verification)
    const refVal = pago.numero_referencia || pago.numero_recibo || '';
    const qrUrl = `${window.location.origin}/PLATAFORMA_INTEGRADA/frontend/modulos/verificar_pago.html?ref=${refVal}`;
    const qrImg = await generarQRDataURL(qrUrl);
    if (qrImg) {
        doc.addImage(qrImg, 'PNG', W - margenL - 30, y - 5, 30, 30);
        doc.setFontSize(6); doc.setFont('helvetica', 'bold'); doc.setTextColor(100, 116, 139);
        doc.text('ESCANEE PARA VERIFICAR', W - margenL - 15, y + 28, { align: 'center' });
        doc.text('VALIDEZ Y ESTADO CONTABLE', W - margenL - 15, y + 30.5, { align: 'center' });
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
async function generarFacturaPDF(poliza, cliente, pago, opts = {}) {
    const doc = await generarReciboPDF(poliza, cliente, pago, { returnDoc: true });
    // La factura es igual al recibo pero el NCF se muestra grande y en rojo para que se distinga
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
async function generarDocumentoDinamicoPDF(plantilla, data, opts = {}) {
    // Cargar pdf-lib dinámicamente si no está disponible (con fallback a local/CDN)
    if (typeof PDFLib === 'undefined') {
        await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '../assets/lib/pdf-lib.min.js';
            script.onload = resolve;
            script.onerror = () => {
                console.warn('[pdf-lib] Local load failed, trying unpkg CDN...');
                const cdnScript = document.createElement('script');
                cdnScript.src = 'https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js';
                cdnScript.onload = resolve;
                cdnScript.onerror = () => reject(new Error('No se pudo cargar pdf-lib desde origen local ni desde CDN.'));
                document.head.appendChild(cdnScript);
            };
            document.head.appendChild(script);
        });
    }

    const { PDFDocument, rgb, StandardFonts } = PDFLib;

    try {
        const fileUrl = plantilla.archivo_base.startsWith('uploads/') 
            ? `/PLATAFORMA_INTEGRADA/${plantilla.archivo_base}`
            : `/PLATAFORMA_INTEGRADA/backend/uploads/plantillas_pdf/${plantilla.archivo_base}`;
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
        const esMarbete = (plantilla.nombre || '').toLowerCase().includes('marbete');
        // El logo grande quemado ya no se dibuja, porque las plantillas subidas
        // ya lo traen en el diseño original del fondo.

        // ── QR ────────────────────────────────────────────────────────────
        const nroPol = (data.poliza && data.poliza.numero_poliza) || '';
        if (nroPol) {
            try {
                const qrUrl = window.location.origin + '/PLATAFORMA_INTEGRADA/frontend/verificar-poliza.html?n=' + encodeURIComponent(nroPol);
                const qrDataURI = await generarQRDataURL(qrUrl);
                if (qrDataURI) {
                    const qrBytes = dataURItoBytes(qrDataURI);
                    const qrImg = await pdfDoc.embedPng(qrBytes);
                    
                    // Buscar si hay mapeo dinámico para el QR
                    const mappedQR = plantilla.campos ? plantilla.campos.find(c => c.variable === 'sistema.qr_msqf') : null;
                    
                    if (mappedQR) {
                        const plantW_mm = parseFloat(plantilla.ancho_mm) || (pageWidth * 25.4 / 72);
                        const plantH_mm = parseFloat(plantilla.alto_mm) || (pageHeight * 25.4 / 72);
                        
                        let QS = 65; // Default fallback
                        const mappedWidth = parseFloat(mappedQR.ancho || mappedQR.font_size);
                        if (mappedWidth > 0) {
                            // Convertir ancho en milímetros a puntos del PDF
                            QS = (mappedWidth / plantW_mm) * pageWidth;
                        }
                        const qrX = (parseFloat(mappedQR.pos_x) / plantW_mm) * pageWidth;
                        const y_top = pageHeight - (parseFloat(mappedQR.pos_y) / plantH_mm) * pageHeight;
                        const qrY = y_top - QS;
                        
                        firstPage.drawImage(qrImg, { x: qrX, y: qrY, width: QS, height: QS });

                        // Embed master brand logo inside QR center for marbetes
                        if (esMarbete) {
                            const overlaySize = QS * 0.22; // 14.3pt
                            const overlayX = qrX + (QS - overlaySize) / 2;
                            const overlayY = qrY + (QS - overlaySize) / 2;

                            // Solid white square to clear QR code center
                            firstPage.drawRectangle({
                                x: overlayX,
                                y: overlayY,
                                width: overlaySize,
                                height: overlaySize,
                                color: rgb(1, 1, 1)
                            });

                            // Draw brand logo in the center
                            if (window.LOGO_MQF_B64) {
                                try {
                                    const masterLogoBytes = dataURItoBytes(window.LOGO_MQF_B64);
                                    const isJpgMaster = window.LOGO_MQF_B64.startsWith('data:image/jpeg') || window.LOGO_MQF_B64.startsWith('data:image/jpg');
                                    const masterLogoImg = isJpgMaster ? await pdfDoc.embedJpg(masterLogoBytes) : await pdfDoc.embedPng(masterLogoBytes);
                                    firstPage.drawImage(masterLogoImg, {
                                        x: overlayX + 1.0,
                                        y: overlayY + 1.0,
                                        width: overlaySize - 2.0,
                                        height: overlaySize - 2.0
                                    });
                                } catch(eMaster) {
                                    console.warn('[PDF] Error QR center brand logo:', eMaster.message);
                                }
                            }
                        }

                        firstPage.drawText('Escanear para verificar', { x: qrX + (QS*0.1), y: Math.max(qrY - 8, 3), size: QS * 0.08, font: fontBold, color: rgb(0, 0.2, 0.6) });
                        console.log('[PDF] QR incrustado OK');
                    }
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
            const plantW_mm = parseFloat(plantilla.ancho_mm) || (pageWidth * 25.4 / 72);
            const plantH_mm = parseFloat(plantilla.alto_mm) || (pageHeight * 25.4 / 72);
            
            // Relación matemática entre el ancho en puntos que asume el Mapeador HTML y el ancho real del PDF base
            const assumed_page_width_pts = plantW_mm * 2.83464;
            const scaleRatio = pageWidth / assumed_page_width_pts;

            plantilla.campos.forEach(c => {
                if (c.variable === 'sistema.qr_msqf') return;
                const valor = resolveVar(c.variable);
                
                const rawSize = parseFloat(c.font_size) || 9;
                const fontSize = rawSize * scaleRatio;
                
                const posX = (parseFloat(c.pos_x) / plantW_mm) * pageWidth;
                const posY = pageHeight - (parseFloat(c.pos_y) / plantH_mm) * pageHeight - fontSize;
                
                const isBold  = c.negrita == 1 || c.negrita === true || c.negrita === '1' || c.font_weight === 'bold';
                const f = isBold ? fontBold : fontRegular;

                // Opacar Fondo (dibujar rectangulo blanco si el campo lo requiere)
                const opacar = c.fondo_opaco == 1 || c.fondo_opaco === '1' || c.fondo_opaco === true || c.fondo_opaco === 'true';
                if (opacar && valor.trim() !== '') {
                    const textWidth = f.widthOfTextAtSize(valor, fontSize);
                    // Dibujar un pequeño padding alrededor
                    firstPage.drawRectangle({
                        x: posX - 1,
                        y: posY - (fontSize * 0.2), // Bajar un poco para cubrir descenders
                        width: textWidth + 2,
                        height: fontSize * 1.2, // Altura que cubre todo el texto
                        color: rgb(1, 1, 1) // Blanco puro
                    });
                }

                firstPage.drawText(valor, {
                    x: posX,
                    y: posY,
                    size: fontSize,
                    font: f,
                    color: rgb(0, 0, 0)
                });
            });
        }

        const pdfBytes = await pdfDoc.save();
        if (opts && opts.returnBytes) {
            return pdfBytes;
        }
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
