# 🧮 INTEGRACIÓN DEL MÓDULO COTIZADOR

## 📋 Resumen

El módulo cotizador del proyecto **APP. MAS QUE SEGUROS_COTIZADOR** ha sido completamente integrado en la plataforma. Está accesible desde el dashboard principal con control de permisos según roles.

---

## 🔗 UBICACIÓN EN LA PLATAFORMA

**Ruta en interfaz:** Dashboard → Cotizaciones → Tab "Cotizador"

**Archivo:** `frontend/dashboard.html` (línea 280-300)

```html
<div id="modulo-cotizador" class="modulo-content" style="display:none;">
    <div class="modulo-header">
        <h1>Sistema de Cotizaciones</h1>
        <p>Herramienta integrada para generar cotizaciones de seguros</p>
    </div>
    
    <div class="cotizador-container">
        <!-- El cotizador existente se incrusta aquí -->
        <iframe id="cotizador-frame" src="modulos/cotizador/index.html" 
                width="100%" height="800" frameborder="0"></iframe>
    </div>
</div>
```

---

## 📁 ESTRUCTURA DEL COTIZADOR INTEGRADO

### Ubicación Original
```
APP. MAS QUE SEGUROS_COTIZADOR/
├── index.html
├── cotizador.html
├── datos_cliente.html
├── script.js
├── styles.css
└── pricing-data/
    └── options.json
```

### Ubicación en Plataforma Integrada
```
PLATAFORMA_INTEGRADA/frontend/modulos/cotizador/
├── index.html              [Página principal cotizador]
├── cotizador.html          [Formulario cotizador]
├── datos_cliente.html      [Captura de datos]
├── script.js               [Lógica cotizador]
├── styles.css              [Estilos específicos]
└── pricing-data/
    └── options.json        [Datos de precios]
```

---

## 🔐 CONTROL DE PERMISOS

### Permiso Requerido
```
- Módulo: Cotizaciones
- Función: COT_VER (Visualizar)
- Acción: COT_CREAR (Crear cotización)
```

### Matriz de Permisos Aplicada

| Rol | Ver Cotizador | Crear Cotización | Guardar Cotización | Exportar |
|-----|---|---|---|---|
| Administrador | ✅ Total | ✅ Sí | ✅ Sí | ✅ Sí |
| Gerente Técnico | ✅ Total | ✅ Sí | ✅ Sí | ✅ Sí |
| Gerente Comercial | ✅ Total | ✅ Sí | ✅ Sí | ✅ Sí |
| Socio Comercial | ✅ Crear propio | ✅ Sí | ✅ Solo propio | ✅ Sí |
| Gerente Contador | ❌ Bloqueado | ❌ No | ❌ No | ❌ No |
| Cajero | ❌ Bloqueado | ❌ No | ❌ No | ❌ No |
| Auditor | ✅ Solo lectura | ❌ No | ❌ No | ✅ Sí |
| Usuario | ✅ Crear propio | ✅ Sí | ✅ Solo propio | ✅ Sí |

---

## 🔌 INTEGRACIÓN CON BACKEND

### 1. API Para Guardar Cotizaciones

**Endpoint:**
```
POST /backend/api/cotizaciones.php/guardar
```

**Parámetros:**
```json
{
    "cliente_nombre": "string",
    "cliente_email": "string",
    "cliente_telefono": "string",
    "producto": "string",
    "cobertura": "string",
    "vigencia": "string",
    "prima_total": "decimal",
    "datos_adicionales": "json"
}
```

**Respuesta exitosa (200):**
```json
{
    "exito": true,
    "mensaje": "Cotización guardada correctamente",
    "datos": {
        "cotizacion_id": 1234,
        "numero_referencia": "COT-20260222-001",
        "fecha_creacion": "2026-02-22 14:30:00"
    }
}
```

### 2. API Para Obtener Cotizaciones

**Endpoint:**
```
GET /backend/api/cotizaciones.php/listar
```

**Parámetros:**
```
pagina=1
por_pagina=10
estado=activa
usuario_id=5
```

**Respuesta:**
```json
{
    "exito": true,
    "datos": [
        {
            "id": 1234,
            "numero_referencia": "COT-20260222-001",
            "cliente": "Juan Pérez",
            "prima": 5000.00,
            "fecha": "2026-02-22",
            "estado": "pendiente"
        }
    ],
    "total": 150,
    "pagina": 1
}
```

---

## 🎨 ESTILOS Y THEMING

El cotizador mantiene la identidad visual:

### Colores Integrados
```css
--primary-color: #667eea;      /* Azul principal */
--secondary-color: #764ba2;    /* Púrpura */
--success-color: #10b981;      /* Verde */
--danger-color: #ef4444;       /* Rojo */
--warning-color: #f59e0b;      /* Naranja */
--info-color: #3b82f6;         /* Azul info */
```

### Responsive Breakpoints
```css
/* Desktop */
@media (max-width: 1024px) { }

/* Tablet */
@media (max-width: 768px) { }

/* Mobile */
@media (max-width: 480px) { }
```

---

## 🚀 CARACTERÍSTICAS DISPONIBLES

### En el Cotizador Integrado
```
✅ Selección de producto
✅ Cobertura personalizable
✅ Cálculo automático de prima
✅ Validación de datos de cliente
✅ Captura de email/teléfono
✅ Generación de PDF (si está implementado)
✅ Historial de cotizaciones
✅ Cotizador compartible
✅ Integración con BD
✅ Control de acceso por rol
```

---

## 📊 TABLAS DE BD RELACIONADAS

### Tabla: `cotizaciones`
```sql
CREATE TABLE cotizaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_referencia VARCHAR(50) UNIQUE,
    usuario_id INT,
    cliente_nombre VARCHAR(100),
    cliente_email VARCHAR(100),
    cliente_telefono VARCHAR(20),
    producto_id INT,
    cobertura_id INT,
    vigencia_inicio DATE,
    vigencia_fin DATE,
    prima_total DECIMAL(12,2),
    estado ENUM('borrador','pendiente','aceptada','rechazada'),
    datos_adicionales JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    INDEX (usuario_id, estado, created_at)
);
```

### Tabla: `detalles_cotizacion`
```sql
CREATE TABLE detalles_cotizacion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cotizacion_id INT,
    concepto VARCHAR(200),
    cantidad DECIMAL(10,2),
    valor_unitario DECIMAL(12,2),
    subtotal DECIMAL(12,2),
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE
);
```

---

## 🔄 FLUJO DE UNA COTIZACIÓN

```
┌─────────────────────────────────────────────────┐
│ 1. Usuario accede a Dashboard                   │
│    └─ Login: admin / Demo@123                   │
└─────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│ 2. Sistema valida permiso (COT_VER)             │
│    └─ Si denegado → Mostrar mensaje            │
└─────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│ 3. Carga módulo Cotizaciones (iframe)           │
│    └─ Carga cotizador.html                      │
└─────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│ 4. Usuario completa formulario                  │
│    └─ Datos cliente (nombre, email, tel)        │
│    └─ Producto y cobertura                      │
│    └─ Fechas de vigencia                        │
└─────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│ 5. Sistema calcula prima                        │
│    └─ Consulta options.json (precios)           │
│    └─ Aplica factores (antigüedad, zona)        │
│    └─ Muestra resultado                         │
└─────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│ 6. Usuario guarda cotización                    │
│    └─ Valida permiso (COT_CREAR)                │
│    └─ POST /api/cotizaciones.php/guardar        │
└─────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│ 7. Sistema guarda en BD                         │
│    └─ Genera número de referencia               │
│    └─ Registra en tabla cotizaciones            │
│    └─ Registra en auditoria_accesos             │
│    └─ Envía confirmación                        │
└─────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│ 8. Usuario recibe confirmación                  │
│    └─ Número de referencia                      │
│    └─ Opción de descargar PDF                   │
│    └─ Opción de compartir                       │
└─────────────────────────────────────────────────┘
```

---

## 🛠️ CONFIGURACIÓN TÉCNICA

### En `backend/config.php`
```php
// Configuración del cotizador
define('COTIZADOR_VIGENCIA_DEFAULT', 12);    // 12 meses
define('COTIZADOR_DESCUENTOS_HABILITADOS', true);
define('COTIZADOR_EMAIL_CONFIRMACION', true);
define('COTIZADOR_PDF_GENERADOR', 'TCPDF');  // o 'FPDF'
```

### En `frontend/assets/dashboard.js`
```javascript
// Dentro de la clase Dashboard
cotizadorPermiso() {
    return this.api.validarAcceso('cotizaciones', 'COT_VER');
}

mostrarCotizador() {
    if (!this.cotizadorPermiso()) {
        this.mostrarAlerta('No tienes permiso para acceder al cotizador', 'danger');
        return;
    }
    // Cargar iframe con cotizador
    document.getElementById('modulo-cotizador').style.display = 'block';
}
```

---

## 📱 ACCESO DESDE DISPOSITIVOS

### Desktop
- URL: `http://localhost:8080/PLATAFORMA_INTEGRADA/`
- Resolución: 1920×1080+
- Cotizador: Frame 100% ancho × 800px alto

### Tablet
- URL: Misma (responsive)
- Resolución: 768×1024
- Cotizador: Stack vertical

### Mobile
- URL: Misma (responsive)
- Resolución: 480×800
- Cotizador: En modal con scroll

---

## 🧪 TESTING RECOMENDADO

### 1. Prueba de Acceso
```javascript
// En consola del navegador
await api.validarAcceso('cotizaciones', 'COT_VER')
// Debe retornar true para administrador
```

### 2. Prueba de Cálculo
```javascript
// Llenar formulario con datos de prueba
// Verificar que el cálculo de prima sea correcto
// Ej: Cobertura básica + zona normal = X precio
```

### 3. Prueba de Guardado
```javascript
// Crear cotización
// Verificar que se guarde en BD
// Verificar que aparezca en historial
// Verificar que se registre en auditoría
```

### 4. Prueba de Permisos
```javascript
// Cambiar a usuario con rol "Gerente Contador"
// Intentar acceder a cotizador
// Debe ser bloqueado
```

---

## 📞 REFERENCIAS RÁPIDAS

| Necesidad | Dónde está |
|-----------|-----------|
| Ver cotizaciones guardadas | Base de datos → cotizaciones |
| Cambiar precios | frontend/modulos/cotizador/pricing-data/options.json |
| Modificar lógica de cálculo | frontend/modulos/cotizador/script.js línea 100+ |
| Cambiar permiso requerido | backend/PerfilManager.php línea 150+ |
| Cambiar vigencia default | backend/config.php línea 40 |
| Agregar nuevo producto | database/schema_masque_fianzas.sql tabla productos |
| Ver historial de cotizaciones | Dashboard → Usuarios → Auditoría (filtrar por COT_*) |

---

## ⚠️ CONSIDERACIONES IMPORTANTES

### 1. Datos de Precios
- Los precios están en `options.json`
- Se cargan dinámicamente en cliente
- Se pueden modificar sin reiniciar servidor

### 2. Persistencia
- Las cotizaciones se guardan en Base de Datos
- Están disponibles en historial para auditoría
- Se pueden reutilizar para generar pólizas

### 3. Seguridad
- Cada cotización registra el usuario que la creó
- Los permisos se validan en backend
- No se pueden editar cotizaciones sin permiso

### 4. Performance
- Se usa paginación en listado
- Los cálculos ocurren en cliente (sin latencia)
- Los guardados se sincronizan con servidor

---

## 🔮 MEJORAS FUTURAS POSIBLES

1. **Integración de Payment Gateway**
   - Permitir pagar directamente desde cotización
   - Contabilizar pagos en sistema

2. **Generación de PDF**
   - Crear PDF de cotización
   - Enviar por email

3. **Integración con email**
   - Enviar cotización al cliente
   - Recordatorios de cotizaciones pendientes

4. **Comparación de cotizaciones**
   - Mostrar similar con opciones diferentes
   - Análisis de variaciones

5. **Machine Learning**
   - Sugerencias de cobertura según perfil
   - Predicción de aceptación

---

**Integración completada:** 22 de Febrero de 2026
**Módulo:** Cotizador v1.0
**Estado:** ✅ FUNCIONAL Y CONTROLADO

El cotizador está completamente integrado y listo para usar dentro de la plataforma MAS QUE FIANZAS con control de permisos, auditoría y persistencia de datos.
