/**
 * Lógica de Importación y Exportación de Datos Multi-Formato
 * Requiere SheetJS (xlsx), jsPDF, jsPDF-AutoTable, JSZip en el HTML
 */

// ======== EXPORTACIÓN ========

function exportarListado(formato, modulo = 'clientes') {
    let rawData = [];
    let titulo = '';
    let nombreBase = '';
    let datosListos = [];
    
    const fechaStr = new Date().toISOString().split('T')[0];

    if (modulo === 'clientes') {
        if (!window.clientesData || window.clientesData.length === 0) { alert('No hay datos de clientes para exportar.'); return; }
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
        if (!window.usuariosData || window.usuariosData.length === 0) { alert('No hay datos de usuarios para exportar.'); return; }
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
        if (!window.cotizacionesData || window.cotizacionesData.length === 0) { alert('No hay datos de cotizaciones para exportar.'); return; }

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
        default: alert('Formato no soportado');
    }
    
    const exportMenu = document.getElementById('exportMenu');
    if (exportMenu) exportMenu.style.display = 'none';
}

function imprimirItem(id, modulo = 'clientes') {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    if (modulo === 'clientes') {
        if (!window.clientesData) return;
        const c = window.clientesData.find(x => x.id == id);
        if (!c) { alert('Cliente no encontrado'); return; }
        
        doc.setFontSize(20); doc.setTextColor(41, 128, 185); doc.text('Ficha de Cliente', 14, 22);
        doc.setFontSize(10); doc.setTextColor(100); doc.text(`Generado el: ${new Date().toLocaleDateString()}`, 14, 30);
        doc.setDrawColor(200); doc.line(14, 35, 196, 35);
        
        let y = 45;
        const linea = (label, value) => {
            doc.setFontSize(12); doc.setTextColor(40);
            doc.setFont(undefined, 'bold'); doc.text(label + ":", 14, y);
            doc.setFont(undefined, 'normal'); doc.text(value ? value.toString() : 'N/A', 60, y);
            y += 10;
        };
        linea('ID Cliente', c.id);
        linea('Tipo de Persona', c.tipo_persona);
        linea('Nombre / Razón', c.nombre_razon_social);
        linea('RNC / Cédula', c.rnc);
        linea('Teléfono', c.telefono);
        linea('Estatus', c.estatus);
        doc.autoPrint(); 
        
        const blob = doc.output('blob');
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank');
        
    } else if (modulo === 'cotizaciones') {
        if (!window.cotizacionesData) return;
        const c = window.cotizacionesData.find(x => x.numero == id);
        if (!c) { alert('Cotización no encontrada'); return; }
        
        dibujarCotizacionPDF(doc, c, window.LOGO_MQF_B64 || null, null);
    }
}

function dibujarCotizacionPDF(doc, c, logoImg, printWindow) {
    const formatter = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' });
    const fmt = (n) => formatter.format(n || 0);

    const primaryColor = [25, 99, 163];   
    const lightColor = [220, 235, 248];  
    const textColor = [50, 50, 50];

    // Logo
    if (logoImg) {
        doc.addImage(logoImg, 'PNG', 14, 10, 50, 22);
    } else {
        doc.setFontSize(22); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
        doc.text('MAS QUE FIANZAS', 14, 25);
    }
    
    // Header Right
    doc.setFontSize(9); doc.setTextColor(...textColor); doc.setFont(undefined, 'normal');
    doc.text('Usuario:', 150, 25, {align: 'right'}); doc.text('Generado Sistema', 196, 25, {align: 'right'});
    doc.text('Fecha:', 150, 29, {align: 'right'}); doc.text(new Date().toLocaleString('es-DO'), 196, 29, {align: 'right'});
    doc.text('Vigencia:', 150, 33, {align: 'right'}); doc.text('30 días', 196, 33, {align: 'right'});
    doc.text('Moneda:', 150, 37, {align: 'right'}); doc.text('RD$ Peso Dominicano', 196, 37, {align: 'right'});
    
    // Titulo COTIZACION
    doc.setFontSize(18); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
    doc.text('COTIZACIÓN', 14, 45);
    doc.setFontSize(14); doc.setTextColor(...textColor); doc.text(c.numero || 'S/N', 14, 52);

    // Saludo
    doc.setFontSize(10); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
    doc.text(`Estimado Sr(a). ${c.cliente || 'A QUIEN CORRESPONDA'}`, 14, 62);
    doc.setTextColor(...textColor); doc.setFont(undefined, 'normal');
    doc.text('Le agradecemos que haya contado con nosotros para su necesidad de fianza/seguro, y nos satisface', 14, 68);
    doc.text('presentarle estas propuestas para la cobertura de su solicitud basado en los siguientes detalles.', 14, 73);

    // Producto Line
    doc.setFillColor(...lightColor);
    doc.rect(14, 80, 182, 8, 'F');
    doc.setFont(undefined, 'bold'); doc.setTextColor(...primaryColor);
    doc.text(`Producto: ${c.subtipo || c.tipo || 'FIANZA'}`, 16, 85.5);
    // Para Seguro de Ley mostrar "Aseguradora" y "Prima Anual"; para Fianza mostrar "Monto a Afianzar"
    const esSeguroLey = c.tipo && c.tipo.toUpperCase().includes('SEGURO');
    const labelMonto = esSeguroLey ? 'Aseguradora' : 'Monto a Afianzar';
    const valorMonto = esSeguroLey ? (c.aseguradora || 'MULTISEGUROS') : fmt(c.monto_afianzado || c.suma_asegurada || 0);
    doc.text(`${labelMonto}: ${valorMonto}`, 95, 85.5);
    doc.text(`Prima: ${fmt(c.total || c.prima_total || 0)}`, 196, 85.5, {align: 'right'});

    // Coberturas Header
    doc.setFontSize(10); doc.text('Coberturas', 14, 98);
    doc.line(14, 100, 196, 100); 
    doc.line(14, 106, 196, 106); 

    doc.setFontSize(9); doc.setTextColor(...textColor);
    doc.setFont(undefined, 'bold');
    doc.text('Riesgos a Terceros / Detalles', 14, 104); 
    doc.text('Límite RD$', 160, 104, {align: 'right'});
    doc.text('Deducible', 196, 104, {align: 'right'});

    const COVERAGE_PROFILES = {
        'MOTOCICLETA BASICO': [{label:'Daños a la Propiedad Ajena',amount:50000},{label:'Lesiones Corporales o Muerte a 1 Persona',amount:50000},{label:'Lesiones Corporales o Muerte a Más de 1 Persona',amount:100000},{label:'Fianza Judicial',amount:50000}],
        'LIVIANO BASICO': [{label:'Daños a la Propiedad Ajena',amount:100000},{label:'Lesiones Corporales o Muerte a 1 Persona',amount:100000},{label:'Lesiones Corporales o Muerte a Más de 1 Persona',amount:200000},{label:'Lesiones Corporales o Muerte a 1 Pasajero',amount:100000},{label:'Lesiones Corporales o Muerte a Más de 1 Pasajero',amount:200000},{label:'Fianza Judicial',amount:200000},{label:'Riesgo Conductor',amount:50000}],
        'PESADO PLUS': [{label:'Daños a la Propiedad Ajena',amount:300000},{label:'Lesiones Corporales o Muerte a 1 Persona',amount:300000},{label:'Lesiones Corporales o Muerte a Más de 1 Persona',amount:600000},{label:'Lesiones Corporales o Muerte a 1 Pasajero',amount:300000},{label:'Lesiones Corporales o Muerte a Más de 1 Pasajero',amount:600000},{label:'Fianza Judicial',amount:500000},{label:'Riesgo Conductor',amount:50000}]
    };
    const OPTIONAL_LABELS = {
        'ASIST_VIAL_LIV': 'ASISTENCIA VIAL (LIVIANO)', 'ASIST_VIAL_PES': 'ASISTENCIA VIAL (PESADO)', 'CASA_CONDUCTOR': 'CASA DEL CONDUCTOR', 'CENTRO_AUTOMOVILISTA': 'CENTRO DE AUTOMOVILISTA'
    };

    // Filas Cobertura
    doc.setFont(undefined, 'normal');
    let yRow = 112;

    if (c.tipo === 'SEGURO DE LEY' && c.cobertura && COVERAGE_PROFILES[c.cobertura]) {
        COVERAGE_PROFILES[c.cobertura].forEach(p => {
            doc.text(`- ${p.label}`, 14, yRow);
            doc.text(`${fmt(p.amount)}`, 160, yRow, {align: 'right'});
            doc.text('0.00', 196, yRow, {align: 'right'});
            yRow += 6;
        });
        // ==== FIX: Parsear servicios_opcionales si es string (evita bug de +0, +1, +2) ====
        let serviciosOpc = c.servicios_opcionales;
        if (typeof serviciosOpc === 'string') {
            try { serviciosOpc = JSON.parse(serviciosOpc); } catch(e) { serviciosOpc = {}; }
        }
        // Si no es un objeto plano válido, ignorar
        if (!serviciosOpc || typeof serviciosOpc !== 'object' || Array.isArray(serviciosOpc)) {
            serviciosOpc = {};
        }
        if (Object.keys(serviciosOpc).length > 0) {
            Object.keys(serviciosOpc).forEach(k => {
                if (serviciosOpc[k]) {
                    doc.text(`+ ${OPTIONAL_LABELS[k] || k}`, 14, yRow);
                    doc.text('Incluido', 160, yRow, {align: 'right'});
                    doc.text('0.00', 196, yRow, {align: 'right'});
                    yRow += 6;
                }
            });
        }
    } else {
        doc.text(`Aval solidario / Póliza (${c.subtipo || c.tipo || 'General'})`, 14, yRow);
        doc.text(`${fmt(c.monto_afianzado || c.suma_asegurada || 0)}`, 160, yRow, {align: 'right'});
        doc.text('0.00', 196, yRow, {align: 'right'});
        yRow += 6;
    }
    
    doc.setTextColor(200); doc.line(14, yRow - 2, 196, yRow - 2); doc.setTextColor(...textColor);
    
    // Totales Box
    yRow += 5;
    let yTotales = yRow > 135 ? yRow : 135;
    doc.setFillColor(...lightColor);
    doc.rect(110, yTotales, 86, 25, 'F');
    doc.setFont(undefined, 'bold'); doc.setTextColor(0,0,0);
    if (esSeguroLey) {
        // SEGURO DE LEY: mostrar Prima Base y Prima Total Anual
        doc.text('Cobertura', 115, yTotales + 6); doc.text(c.cobertura || 'N/A', 192, yTotales + 6, {align: 'right'});
        doc.setFont(undefined, 'normal');
        doc.text('Prima Base', 115, yTotales + 12); doc.text(`${fmt(c.prima_base || 0)}`, 192, yTotales + 12, {align: 'right'});
        doc.text('Servicios Opcionales', 115, yTotales + 17); 
        // Calcular suma de servicios opcionales
        const OPTIONAL_PRICES = { ASIST_VIAL_LIV: 2600, ASIST_VIAL_PES: 4600, CASA_CONDUCTOR: 1020, CENTRO_AUTOMOVILISTA: 1020 };
        let serviciosOpc2 = c.servicios_opcionales;
        if (typeof serviciosOpc2 === 'string') { try { serviciosOpc2 = JSON.parse(serviciosOpc2); } catch(e) { serviciosOpc2 = {}; } }
        if (!serviciosOpc2 || typeof serviciosOpc2 !== 'object' || Array.isArray(serviciosOpc2)) serviciosOpc2 = {};
        const sumOpc = Object.keys(serviciosOpc2).reduce((acc, k) => acc + (serviciosOpc2[k] ? (OPTIONAL_PRICES[k] || 0) : 0), 0);
        doc.text(`${fmt(sumOpc)}`, 192, yTotales + 17, {align: 'right'});
        doc.setFont(undefined, 'bold'); doc.setTextColor(0,0,0);
        doc.text('Prima Total Anual', 115, yTotales + 23); doc.text(`${fmt(c.total || c.prima_total || 0)}`, 192, yTotales + 23, {align: 'right'});
    } else {
        // FIANZA: mostrar Monto Afianzado, Prima Neta, Impuestos, Prima Bruta
        doc.text('Monto a Afianzar', 115, yTotales + 6); doc.text(`${fmt(c.monto_afianzado || c.suma_asegurada || 0)}`, 192, yTotales + 6, {align: 'right'});
        doc.setFont(undefined, 'normal');
        doc.text('Prima Neta', 115, yTotales + 12); doc.text(`${fmt(c.prima_base || c.total || c.prima_total || 0)}`, 192, yTotales + 12, {align: 'right'});
        doc.text('Impuestos (ISC)', 115, yTotales + 17); doc.text(`${fmt(c.impuesto || 0)}`, 192, yTotales + 17, {align: 'right'});
        doc.setFont(undefined, 'bold'); doc.setTextColor(0,0,0);
        doc.text('Prima Bruta', 115, yTotales + 23); doc.text(`${fmt(c.total || c.prima_total || 0)}`, 192, yTotales + 23, {align: 'right'});
    }

    // Observaciones
    doc.setFontSize(10); doc.setTextColor(...primaryColor); doc.setFont(undefined, 'bold');
    doc.text('Observaciones', 14, yTotales + 4);
    doc.setFontSize(9); doc.setTextColor(...textColor); doc.setFont(undefined, 'normal');
    doc.text('La aceptación de esta cotización para la Emisión', 14, yTotales + 9);
    doc.text('de la Póliza, dependerá de la inspección de', 14, yTotales + 14);
    doc.text('dicho riesgo, válida por 30 días.', 14, yTotales + 19);

    // Firma
    doc.text('Atentamente,', 14, yTotales + 35);
    doc.setLineWidth(0.5); doc.line(90, yTotales + 65, 140, yTotales + 65);
    doc.setFont(undefined, 'bold'); doc.setFontSize(9);
    doc.text('Firma autorizada', 115, yTotales + 70, {align: 'center'});

    // Footer Address dinámico
    doc.setFont(undefined, 'normal'); doc.setFontSize(8); doc.setTextColor(150, 150, 150);
    doc.text('Ave. 27 de febrero #234, Suite-304, La esperilla, Santo Domingo. DN. Código postal: 10107, República Dominicana', 105, 280, {align: 'center'});
    doc.text('Tel: +1 (829) 629-1952 | Email: info@masquefianzas.com', 105, 284, {align: 'center'});

    doc.autoPrint();
    const blob = doc.output('blob');
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
}

// Helpers
function exportarAExcel(datos, filename, isCsv = false) {
    if (typeof XLSX === 'undefined') { alert('Librería SheetJS no encontrada'); return; }
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
    if (typeof window.jspdf === 'undefined') { alert('Librería jsPDF no encontrada'); return; }
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
    if (typeof JSZip === 'undefined') { alert('Librería JSZip no encontrada'); return; }
    const zip = new JSZip();
    zip.file("clientes_backup.json", JSON.stringify(datos, null, 2));
    
    if (typeof XLSX !== 'undefined') {
        const ws = XLSX.utils.json_to_sheet(datos);
        const csvText = XLSX.utils.sheet_to_csv(ws);
        zip.file("clientes_backup.csv", csvText);
    }
    
    zip.generateAsync({ type: "blob" }).then(function(content) {
        const url = URL.createObjectURL(content);
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}

// ======== IMPORTACIÓN ========

function importarDatos(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const json = XLSX.utils.sheet_to_json(worksheet);
            
            if (json.length === 0) {
                alert('El archivo está vacío o no es válido.');
                return;
            }
            
            const payload = json.map(row => {
                return {
                    tipo_persona: row['Tipo'] || row['tipo_persona'] || 'Fisica',
                    nombre_razon_social: row['Nombre / Razón Social'] || row['nombre_razon_social'] || row['Nombre'] || 'Importado',
                    rnc: row['RNC / Cédula'] || row['rnc'] || row['Cedula'] || ('RNC-' + Math.floor(Math.random()*100000)),
                    telefono: row['Teléfono'] || row['telefono'] || '',
                    correo: row['Correo'] || row['email'] || '',
                    direccion: row['Dirección Física'] || row['direccion'] || '',
                    estatus: row['Estatus'] || row['estado'] || row['estatus'] || 'Activo'
                };
            });
            
            const endpoint = modulo === 'usuarios' ? '/usuarios/importar' : '/clientes/importar';
            const payloadKey = modulo === 'usuarios' ? 'usuarios' : 'clientes';

            if (confirm(`¿Estás seguro que deseas importar ${payload.length} registros a la base de datos?`)) {
                // Bloque visual
                document.body.style.cursor = 'wait';
                const btnSelector = modulo === 'usuarios' ? 'button[onclick*="importFile"]' : 'button[onclick*="importFile"]';
                const btn = document.querySelector(btnSelector);
                const btnOriginal = btn ? btn.innerHTML : 'Importar';
                if(btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cargando...';
                
                const dataToSend = {};
                dataToSend[payloadKey] = payload;

                const respuesta = await api.solicitud(endpoint, 'POST', dataToSend);
                
                document.body.style.cursor = 'default';
                if(btn) btn.innerHTML = btnOriginal;
                
                if (respuesta.exito) {
                    alert(`Importación completada: ${respuesta.insertados} registros agregados, ${respuesta.errores} fallaron.`);
                    if(modulo === 'usuarios' && typeof cargarUsuarios === 'function') cargarUsuarios();
                    if(modulo === 'clientes' && typeof cargarClientes === 'function') cargarClientes();
                } else {
                    alert('Error en la importación: ' + respuesta.mensaje);
                }
            }

        } catch (error) {
            console.error(error);
            alert('Error procesando el archivo. Asegúrate de que es un Excel o CSV válido.');
        } finally {
            event.target.value = null;
        }
    };
    reader.readAsArrayBuffer(file);
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
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const json = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            
            if (json.length === 0) { alert('Archivo vacío.'); return; }
            
            const payload = json.map(row => ({
                numero: row['N° Cotizacion'] || row['numero'] || ('F-IMP-' + Math.floor(Math.random()*9000)),
                tipo: row['Tipo'] || row['tipo'] || 'SEGURO',
                subtipo: row['Ramo / Subtipo'] || row['subtipo'] || '',
                cliente: row['Cliente'] || row['cliente'] || 'Importado',
                cedula: row['Cédula / RNC'] || row['cedula'] || '',
                monto_afianzado: parseFloat(row['Monto Base']) || parseFloat(row['monto_afianzado']) || 0,
                total: parseFloat(row['Prima Total']) || parseFloat(row['total']) || 0,
                fecha: row['Fecha Emisión'] ? new Date(row['Fecha Emisión']).toISOString() : new Date().toISOString()
            }));
            
            if (confirm(`¿Importar ${payload.length} cotizaciones al historial local?`)) {
                const hist = JSON.parse(localStorage.getItem('cotHistorial') || '[]');
                const newHist = [...payload, ...hist];
                localStorage.setItem('cotHistorial', JSON.stringify(newHist));
                alert(`¡${payload.length} cotizaciones importadas!`);
                if(typeof loadHistorial === 'function') loadHistorial();
            }
        } catch (error) {
            alert('Error procesando el archivo Excel/CSV.');
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
