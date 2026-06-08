// frontend/js/pdf-editor.js
class PDFEditor {
    constructor() {
        this.pdfViewer = null;
        this.formBuilder = null;
        this.fields = [];
        this.splitView = true;
    }
    
    initSplitView() {
        // Vista dividida: Formulario | PDF Original
        const container = document.getElementById('editor-container');
        container.innerHTML = `
            <div class="split-view">
                <div class="split-left">
                    <h3>Formulario Online</h3>
                    <div id="form-builder"></div>
                </div>
                <div class="split-right">
                    <h3>PDF Original</h3>
                    <div id="pdf-thumbnail"></div>
                </div>
            </div>
        `;
        
        this.initPDFViewer();
        this.initFormBuilder();
        this.enableDragDrop();
    }
    
    enableDragDrop() {
        // Drag and drop de campos
        const formFields = document.querySelectorAll('.form-field');
        formFields.forEach(field => {
            field.draggable = true;
            field.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('field-id', field.dataset.id);
            });
        });
        
        // Drop zones en el PDF
        const pdfDropZones = document.querySelectorAll('.pdf-drop-zone');
        pdfDropZones.forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });
            
            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                const fieldId = e.dataTransfer.getData('field-id');
                this.mapFieldToPDF(fieldId, zone.dataset.pdf_coords);
            });
        });
    }
    
    mapFieldToPDF(fieldId, pdfCoords) {
        // Mapear campo del formulario con coordenadas del PDF
        fetch('/api/pdf/map-field', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                field_id: fieldId,
                pdf_coordinates: pdfCoords
            })
        })
        .then(response => response.json())
        .then(data => {
            this.updateFieldMapping(data);
        });
    }
}