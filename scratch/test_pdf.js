const fs = require('fs');

// Mock document
const document = {
    addEventListener: () => {},
    head: {
        appendChild: () => {}
    },
    createElement: () => ({
        setAttribute: () => {},
        style: {}
    })
};

// Mock window
const window = {
    document: document,
    addEventListener: () => {},
};

// Mock jsPDF class
class MockjsPDF {
    constructor() {
        this.pages = [[]];
        this.currentPage = 0;
    }
    setFontSize() {}
    setTextColor() {}
    setFont() {}
    text(txt, x, y, options) {
        // console.log(`TEXT: "${txt}" at (${x}, ${y})`, options || '');
    }
    setFillColor() {}
    rect() {}
    line() {}
    setLineWidth() {}
    addImage(img, format, x, y, w, h) {
        // console.log(`IMAGE: format=${format} at (${x}, ${y}) size=${w}x${h}`);
    }
    save(name) {
        console.log(`Saved PDF as: ${name}`);
    }
}

// Load data-export.js
const code = fs.readFileSync('frontend/assets/data-export.js', 'utf8');

// Define global scope simulation
const globalScope = {
    Intl: Intl,
    console: console,
    Object: Object,
    Math: Math,
    parseFloat: parseFloat,
    parseInt: parseInt,
    Array: Array,
    XLSX: {},
    JSZip: {},
    document: document,
    window: window
};

// Evaluate the code in global context
const fn = new Function('window', 'LOGO_MQF_B64', 'document', code + '\nreturn dibujarCotizacionPDF;');
const dibujarCotizacionPDF = fn(globalScope, 'mock_logo_b64', document);

// Run with mock data
const doc = new MockjsPDF();
const data = {
    tipo: 'SEGURO DE LEY',
    subtipo: 'MOTOCICLETAS',
    numero: 'SL-2026-3977',
    cliente: 'Alberto Baez',
    cedula: '001-5687943-1',
    uso: 'PRIVADO',
    capacidad: 'Hasta 250 cc',
    aseguradora: 'MULTISEGUROS',
    cobertura: 'MOTOCICLETA BASICO',
    plazo: 0,
    prima_base: 400,
    servicios_opcionales: {},
    prima_mensual: 0,
    impuesto: 0,
    total: 400,
    usar_ncf: true,
    fecha: new Date().toISOString()
};

try {
    dibujarCotizacionPDF(doc, data, 'mock_logo_b64', null);
    console.log("SUCCESS: dibujarCotizacionPDF executed without throwing!");
} catch (e) {
    console.error("FAILED to execute:", e);
}
