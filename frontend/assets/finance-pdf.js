/**
 * Motor de Exportación Financiera PDF
 * MQF Financial Core v3.0
 */

async function exportarDiario() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    // Configuración de cabecera
    const margin = 15;
    const logoWidth = 40;
    const logoHeight = 15;

    // Logo
    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', margin, margin, logoWidth, logoHeight);
    }

    // Información de la Empresa
    doc.setFontSize(10);
    doc.setFont("helvetica", "bold");
    doc.text("MAS QUE FIANZAS, S.R.L.", margin + logoWidth + 5, margin + 5);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(8);
    doc.text("RNC: 131-12345-6", margin + logoWidth + 5, margin + 9);
    doc.text("Santo Domingo, República Dominicana", margin + logoWidth + 5, margin + 13);

    // Título del Reporte
    doc.setFontSize(14);
    doc.setFont("helvetica", "bold");
    doc.text("LIBRO DIARIO GENERAL", 105, 40, { align: "center" });
    
    doc.setFontSize(9);
    doc.setFont("helvetica", "normal");
    doc.text(`Fecha de impresión: ${new Date().toLocaleString()}`, 105, 45, { align: "center" });

    // Obtener datos del API
    const res = await fetch('../../backend/api/centro_financiero.php?action=get_diario&limit=100').then(r => r.json());
    
    if (!res.exito) {
        alert("Error al obtener datos para el reporte");
        return;
    }

    const tableData = res.datos.map(a => [
        new Date(a.fecha).toLocaleDateString(),
        a.numero,
        a.descripcion,
        parseFloat(a.total_monto).toLocaleString('en-US', { minimumFractionDigits: 2 }),
        parseFloat(a.total_monto).toLocaleString('en-US', { minimumFractionDigits: 2 }),
        "POSTEADO"
    ]);

    doc.autoTable({
        startY: 55,
        head: [['Fecha', 'Asiento #', 'Descripción', 'Débito', 'Crédito', 'Estado']],
        body: tableData,
        headStyles: { fillColor: [0, 51, 102], textColor: [255, 255, 255] },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        styles: { fontSize: 8 },
        columnStyles: {
            3: { halign: 'right' },
            4: { halign: 'right' }
        }
    });

    // Pie de página
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.text(`Página ${i} de ${pageCount}`, 105, 285, { align: "center" });
    }

    doc.save(`Libro_Diario_${new Date().toISOString().split('T')[0]}.pdf`);
}

async function exportarCatalogo() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    const margin = 15;

    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', margin, margin, 40, 15);
    }

    doc.setFontSize(10);
    doc.setFont("helvetica", "bold");
    doc.text("MAS QUE FIANZAS, S.R.L.", 60, 20);
    doc.text("CATÁLOGO DE CUENTAS (SIS)", 105, 40, { align: "center" });

    const res = await fetch('../../backend/api/centro_financiero.php?action=get_catalogo').then(r => r.json());
    
    if (!res.exito) return;

    const tableData = res.datos.map(c => [
        c.codigo,
        c.nombre,
        c.tipo,
        c.naturaleza
    ]);

    doc.autoTable({
        startY: 50,
        head: [['Código', 'Nombre de la Cuenta', 'Tipo', 'Naturaleza']],
        body: tableData,
        headStyles: { fillColor: [0, 51, 102] },
        styles: { fontSize: 8 },
        columnStyles: {
            0: { cellWidth: 30, fontStyle: 'bold' }
        }
    });

    doc.save(`Catalogo_Cuentas_MQF.pdf`);
}

async function exportarComprobanteAsiento(asientoId) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    const margin = 20;

    if (window.LOGO_MQF_B64) {
        doc.addImage(window.LOGO_MQF_B64, 'PNG', margin, margin, 40, 15);
    }

    doc.setFontSize(10);
    doc.setFont("helvetica", "bold");
    doc.text("MAS QUE FIANZAS, S.R.L.", 65, margin + 5);
    doc.setFontSize(14);
    doc.text("COMPROBANTE DE DIARIO", 105, 50, { align: "center" });

    const res = await fetch(`../../backend/api/centro_financiero.php?action=get_asiento_detalle&id=${asientoId}`).then(r => r.json());
    if (!res.exito) return;

    // Info del encabezado
    doc.setFontSize(10);
    doc.text(`Asiento No: ${asientoId}`, margin, 65);
    doc.text(`Fecha: ${new Date().toLocaleDateString()}`, 150, 65);

    const tableData = res.datos.map(l => [
        l.cuenta_codigo,
        l.cuenta_nombre,
        l.debe > 0 ? parseFloat(l.debe).toLocaleString() : '',
        l.haber > 0 ? parseFloat(l.haber).toLocaleString() : ''
    ]);

    doc.autoTable({
        startY: 75,
        head: [['Código', 'Nombre de Cuenta', 'Débito', 'Crédito']],
        body: tableData,
        headStyles: { fillColor: [0, 51, 102] },
        columnStyles: {
            2: { halign: 'right' },
            3: { halign: 'right' }
        }
    });

    // Firmas
    let finalY = doc.lastAutoTable.finalY + 30;
    doc.line(margin, finalY, margin + 50, finalY);
    doc.text("Hecho por", margin, finalY + 5);
    
    doc.line(140, finalY, 140 + 50, finalY);
    doc.text("Autorizado por", 140, finalY + 5);

    doc.save(`Comprobante_Asiento_${asientoId}.pdf`);
}
