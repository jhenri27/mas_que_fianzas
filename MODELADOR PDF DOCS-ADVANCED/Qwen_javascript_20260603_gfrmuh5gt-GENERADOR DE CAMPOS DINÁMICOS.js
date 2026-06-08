// frontend/js/field-builder.js
const FieldTypes = {
    TEXTO: {
        type: 'text',
        icon: '📝',
        component: 'TextField',
        properties: ['placeholder', 'maxLength', 'pattern']
    },
    NUMERO: {
        type: 'number',
        icon: '🔢',
        component: 'NumberField',
        properties: ['min', 'max', 'step', 'decimals']
    },
    FECHA: {
        type: 'date',
        icon: '📅',
        component: 'DateField',
        properties: ['format', 'minDate', 'maxDate']
    },
    SELECT: {
        type: 'select',
        icon: '📋',
        component: 'SelectField',
        properties: ['options', 'multiple', 'searchable']
    },
    FIRMA: {
        type: 'signature',
        icon: '✍️',
        component: 'SignatureField',
        properties: ['width', 'height', 'penColor']
    },
    ARCHIVO: {
        type: 'file',
        icon: '📎',
        component: 'FileUploadField',
        properties: ['allowedTypes', 'maxSize', 'multiple']
    },
    TABLA: {
        type: 'table',
        icon: '📊',
        component: 'TableField',
        properties: ['columns', 'allowAddRow', 'allowDeleteRow']
    },
    CALCULADO: {
        type: 'calculated',
        icon: '🧮',
        component: 'CalculatedField',
        properties: ['formula', 'fields', 'format']
    }
};

class FormBuilder {
    addField(fieldType, config) {
        const field = {
            id: this.generateUUID(),
            type: fieldType,
            label: config.label || 'Nuevo Campo',
            name: this.generateFieldName(config.label),
            required: config.required || false,
            properties: config.properties || {},
            validations: config.validations || [],
            conditional: config.conditional || null
        };
        
        this.fields.push(field);
        this.renderField(field);
        return field;
    }
    
    renderField(field) {
        const fieldHTML = `
            <div class="form-field" data-field-id="${field.id}">
                <label>${field.label} ${field.required ? '*' : ''}</label>
                ${this.getFieldHTML(field)}
                ${field.properties.help ? `<small class="help-text">${field.properties.help}</small>` : ''}
                <div class="field-actions">
                    <button onclick="editor.editField('${field.id}')">✏️</button>
                    <button onclick="editor.deleteField('${field.id}')">🗑️</button>
                </div>
            </div>
        `;
        
        document.getElementById('form-builder').insertAdjacentHTML('beforeend', fieldHTML);
    }
}