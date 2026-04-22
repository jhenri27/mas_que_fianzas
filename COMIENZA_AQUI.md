# 🧭 GUÍA DE ORIENTACIÓN — ¿POR DÓNDE EMPEZAR?

> **Versión:** 2.1.0 | **Actualizado:** 22 de Abril de 2026

---

## 👋 Bienvenido a MAS QUE FIANZAS

Has recibido una plataforma completa en evolución activa. Este documento te guía sobre **qué leer primero** según tu rol.

---

## 🌟 NOVEDADES — Versión 2.1.0 (Abril 2026)

La plataforma ha sido significativamente enriquecida con las siguientes funcionalidades:

| Funcionalidad | Estado |
|--------------|--------|
| **Recuperación de contraseña por email** (token + SMTP) | ✅ Nuevo |
| **Panel SMTP configurable** desde el Dashboard sin código | ✅ Nuevo |
| **Visor de logs SMTP** en tiempo real | ✅ Nuevo |
| **Jerarquía de IDs de usuarios** (RED-XXX, DIR-XXX, PDV-XXX, VEN-XXX) | ✅ Nuevo |
| **Sistema de referidos en árbol** con comisiones de red | ✅ Nuevo |
| **Nuevo perfil "Socio Comercial PDV"** con menú adaptado | ✅ Nuevo |
| **PDF corporativo** con logo MQF real en todas las cotizaciones | ✅ Mejorado |
| **Cotizador Seguros de Ley** con 13 tipos, precios reales MULTISEGUROS | ✅ v2 |
| **Cotizador Fianzas** profesional con 7 tipos y coberturas | ✅ v2 |
| **Historial de cotizaciones** exportable en PDF, Excel, CSV, JSON, ZIP | ✅ Mejorado |
| **Módulo Clientes** activo con CRUD completo | ✅ Activo |
| **Módulo Pólizas** activo con base funcional | ✅ Activo |
| **Dashboard con actividad reciente** clickeable con modal de detalle | ✅ Mejorado |
| **Estadísticas en tiempo real**: Clientes, Cotizaciones, Fianzas, Seguros | ✅ Mejorado |

---

## 🎯 ELIGE TU PERFIL

### 👑 Soy ADMINISTRADOR del Sistema
**Tiempo estimado: 1 hora**

1. **[5 min]** Leer: [RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md)
   - Entender qué se ha entregado en v2.1.0

2. **[10 min]** Leer: [INSTALACION_RAPIDA.md](INSTALACION_RAPIDA.md)
   - Pasos exactos para instalar o actualizar

3. **[20 min]** Instalar y acceder:
   ```
   http://localhost/PLATAFORMA_INTEGRADA
   Usuario: admin / Contraseña: Demo@123
   ```

4. **[15 min]** Configurar SMTP:
   - Dashboard → módulo Seguridad/Configuración → SMTP
   - Ingresar servidor, puerto, usuario y contraseña de correo
   - Clic en "Probar conexión" para verificar

5. **[10 min]** Crear usuarios desde Dashboard:
   - Dashboard → Usuarios → "+ Crear Usuario"
   - Asignar perfil jerárquico (Director, PDV, Vendedor, etc.)
   - El código (RED-XXX / DIR-XXX / etc.) se asigna automáticamente

**Resultado esperado:** Sistema con SMTP funcionando y primer usuario de equipo creado ✅

---

### 📊 Soy GERENTE / Director de Red
**Tiempo estimado: 30 minutos**

1. **[5 min]** Leer: [RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md) - Sección "Nuevas funcionalidades"

2. **[10 min]** Entender jerarquía de IDs de usuarios:
   - RED-XXX: Directores de Red (máximo nivel comercial)
   - DIR-XXX: Directores de área
   - PDV-XXX: Socios Comerciales con punto de venta
   - VEN-XXX: Vendedores de campo

3. **[10 min]** Revisar módulo de usuarios:
   - Dashboard → Usuarios → ver columna "Código"
   - Crear un PDV y asignar referente (árbol de comisiones)

4. **[5 min]** Revisar estadísticas del Dashboard:
   - Cotizaciones totales, por tipo Fianza vs Seguro

**Resultado esperado:** Entender estructura de comisiones y árbol de referidos ✅

---

### 🏪 Soy SOCIO COMERCIAL (PDV)
**Tiempo estimado: 15 minutos**

1. **[2 min]** Acceder con tus credenciales proporcionadas por el administrador
   ```
   http://localhost/PLATAFORMA_INTEGRADA
   ```

2. **[5 min]** Explorar el Dashboard:
   - Verás solo los módulos que tienes disponibles
   - Cotizaciones, Clientes, Pólizas, Reportes

3. **[5 min]** Hacer una cotización de prueba:
   - Dashboard → Cotizaciones → Tab "Seguros de Ley"
   - Seleccionar: Tipo vehículo → Uso → Capacidad
   - Ver precio y cobertura automática
   - Descargar PDF corporativo

4. **[3 min]** Cambiar tu contraseña inicial:
   - Clic en tu nombre (arriba derecha) → Cambiar Contraseña

**Resultado esperado:** Saber cotizar y descargar PDF para clientes ✅

---

### 👤 Soy USUARIO final (Empleado)
**Tiempo estimado: 10 minutos**

1. **[2 min]** Acceder:
   ```
   http://localhost/PLATAFORMA_INTEGRADA
   ```

2. **[5 min]** Navegar el Dashboard según tu perfil

3. **[3 min]** Cambiar tu contraseña:
   - Click en nombre de usuario → "Cambiar Contraseña"

> ⚠️ Si olvidaste tu contraseña: en la pantalla de login click en "¿Olvidaste tu contraseña?" y recibirás un email con instrucciones.

**Resultado esperado:** Saber acceder y navegar ✅

---

### 🧑‍💻 Soy DESARROLLADOR / Técnico
**Tiempo estimado: 2-3 horas**

1. **[15 min]** Revisar estructura del proyecto:
   - `backend/config.php` — configuración central de BD
   - `backend/config/smtp.json` — configuración SMTP (editable sin código)
   - `backend/Mailer.php` — motor de envío de correos
   - `backend/UsuarioManager.php` — patrón de clase principal

2. **[20 min]** Explorar APIs REST:
   - `backend/api/auth.php` — login, logout, sesión
   - `backend/api/actividad.php` — registro de actividad por módulo
   - `backend/api/cotizaciones.php` — guardar/listar cotizaciones
   - `backend/api/config_smtp.php` — leer/guardar config SMTP
   - `backend/api/test_smtp.php` — prueba de correo
   - `backend/api/logs_smtp.php` — visor de logs SMTP
   - `backend/api/mi_perfil.php` — datos del usuario actual
   - `backend/api/alter_recuperacion.php` — recuperación de contraseña

3. **[20 min]** Revisar frontend:
   - `frontend/assets/api-client.js` — cliente HTTP (todas las llamadas)
   - `frontend/assets/dashboard.js` — lógica principal del Dashboard
   - `frontend/assets/data-export.js` — motor PDF/Excel/CSV/ZIP
   - `frontend/modulos/cotizaciones.html` — cotizador React integrado

4. **[15 min]** Entender el flujo de autenticación:
   - Login → sesión en BD → token → API headers
   - Recuperación: solicitud → token BD → email → validación → nueva contraseña

5. **[30 min]** Extensión de ejemplo:
   - Agregar nuevo tipo de fianza en `cotizaciones.html` (TASAS object)
   - Crear nuevo endpoint en `backend/api/`
   - Registrar actividad con `api.registrarActividad(modulo, descripcion)`

**Resultado esperado:** Dominar arquitectura y poder extender ✅

---

### 🔎 Soy AUDITOR / Compliance
**Tiempo estimado: 45 minutos**

1. **[10 min]** Revisar: [RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md) - Sección "Seguridad"

2. **[15 min]** Dashboard → Usuarios → Tab "Auditoría":
   - Todos los eventos: login, logout, cambios de datos, acceso a módulos
   - Filtros por usuario, fecha, tipo de evento

3. **[10 min]** Dashboard → Seguridad → "Logs SMTP":
   - Ver intentos de envío de correo y resultados

4. **[10 min]** Revisar backend/logs/:
   - `error.log` — errores del sistema PHP
   - `smtp.log` — actividad de correo electrónico

**Resultado esperado:** Entender controles de seguridad y auditabilidad ✅

---

## 📚 DOCUMENTOS EN ORDEN DE LECTURA

```
Para TODOS:
  1. RESUMEN_EJECUTIVO.md       ← Comienza aquí (versión 2.1.0)
     └─ 10 min

Para INSTALAR:
  2. INSTALACION_RAPIDA.md      ← Pasos exactos
     └─ 15 min

Para ENTENDER TÉCNICA:
  3. ESPECIFICACIONES.md        ← Componentes, APIs, BD
     └─ 20 min

Para el COTIZADOR:
  4. INTEGRACION_COTIZADOR.md   ← Módulo de cotizaciones
     └─ 15 min

Para NAVEGAR EL PROYECTO:
  5. INDICE_MAESTRO.md          ← Mapa del proyecto
     └─ 20 min

Para VERIFICAR INSTALACIÓN:
  6. VERIFICACION_FINAL.md      ← Checklist de archivos
     └─ 15 min
```

---

## 🔗 MAPA DEL PROYECTO

```
┌──────────────────────────────────────────────────┐
│       PLATAFORMA MAS QUE FIANZAS v2.1.0          │
│                                                  │
│  LOGIN ──→ DASHBOARD ──→ MÓDULOS                 │
│              │                                   │
│       ┌──────┴──────────────────────────┐        │
│       ↓         ↓          ↓            ↓        │
│  COTIZACIONES CLIENTES  USUARIOS  SEGURIDAD       │
│  (Seguros+    (CRUD)    (Jerarquía  (SMTP+        │
│   Fianzas)              +Comisiones) Logs)        │
│       ↓                                          │
│  PDF CORPORATIVO (logo MQF)                      │
│  Excel/CSV/ZIP/JSON                              │
└──────────────────────────────────────────────────┘
```

---

## ⏰ TIEMPOS SUGERIDOS

| Actividad | Tiempo | Documento/Acción |
|-----------|--------|-----------------|
| Visión general v2.1.0 | 10 min | RESUMEN_EJECUTIVO |
| Instalación / actualización | 15 min | INSTALACION_RAPIDA |
| Configurar SMTP | 10 min | Dashboard → Seguridad |
| Primera cotización PDF | 5 min | Dashboard → Cotizaciones |
| Crear usuario con jerarquía | 10 min | Dashboard → Usuarios |
| Entender APIs | 30 min | ESPECIFICACIONES + código |
| Administrar árbol de referidos | 20 min | Dashboard → Usuarios |
| **TOTAL admin** | **~100 min** | **Dominio completo** |

---

## 📞 PREGUNTAS FRECUENTES

### "¿Cómo configuro el correo del sistema?"
**Respuesta:** Dashboard → módulo Seguridad → pestaña "Configuración SMTP" → llenar datos → Guardar → Probar

### "¿Cómo recupero una contraseña olvidada?"
**Respuesta:** En la pantalla de login → "¿Olvidaste tu contraseña?" → ingresar email → revisar bandeja → seguir el enlace (válido 30 minutos)

### "¿Qué significa el código RED-001, DIR-002, etc.?"
**Respuesta:** Es la nomenclatura jerárquica de usuarios:
- `RED-XXX` = Director de Red (máximo nivel)
- `DIR-XXX` = Director de área
- `PDV-XXX` = Socio Comercial (Punto de Venta)
- `VEN-XXX` = Vendedor de campo

### "¿Cómo genero una cotización con PDF?"
**Respuesta:** Dashboard → Cotizaciones → Tab "Seguros de Ley" o "Fianzas" → llenar datos → "Guardar y Descargar PDF"

### "¿Cómo exporto el historial de cotizaciones?"
**Respuesta:** Dashboard → Cotizaciones → Tab "Historial" → botón "Exportar" → elegir formato (PDF, Excel, CSV, JSON, ZIP)

### "¿Cómo creo un usuario con comisiones?"
**Respuesta:** Dashboard → Usuarios → "+ Crear Usuario" → activar checkbox "¿Tiene comisión?" → ingresar porcentaje individual y de red → seleccionar referente

### "¿Qué módulos ve un PDV (Socio Comercial)?"
**Respuesta:** Solo ve: Dashboard, Cotizaciones, Clientes, Pólizas, Reportes y Mi Perfil. Los módulos administrativos están ocultos automáticamente.

### "¿Dónde veo los logs del sistema?"
**Respuesta:** 
- Actividad de usuarios: Dashboard → Usuarios → Tab "Auditoría"
- Logs SMTP: Dashboard → Seguridad → "Logs de Correo"
- Logs técnicos: `backend/logs/error.log` y `backend/logs/smtp.log`

---

## ✅ CHECKLIST DE INICIO

### Instalación (una sola vez)
- [ ] Sistema accesible en http://localhost/PLATAFORMA_INTEGRADA
- [ ] Login funciona con admin/Demo@123
- [ ] Dashboard muestra estadísticas (aunque sean en 0)
- [ ] SMTP configurado y probado → "Prueba exitosa"

### Configuración inicial
- [ ] Crear usuarios reales con sus roles apropiados
- [ ] Asignar árbol de referidos si aplica
- [ ] Configurar comisiones por usuario si aplica
- [ ] Cambiar contraseña del admin por una más segura

### Verificación operativa
- [ ] Cotizador Seguros de Ley genera precio y PDF
- [ ] Cotizador Fianzas calcula prima y genera PDF
- [ ] Historial de cotizaciones carga desde la BD
- [ ] Recuperación de contraseña envía email correctamente
- [ ] Auditoría registra los eventos de login

**Si todas están marcadas:** ¡La plataforma está lista para operar! ✅

---

## 🎯 PRÓXIMAS ACCIONES RECOMENDADAS

### Hoy (Día 1)
1. ✅ Instalar/verificar sistema
2. ✅ Configurar SMTP con datos reales de correo
3. ✅ Crear usuarios del equipo comercial
4. ✅ Probar flujo completo: cotizar → PDF → historial

### Esta semana
1. ✅ Asignar roles y árbol de referidos al equipo
2. ✅ Capacitar a PDVs en el uso del cotizador
3. ✅ Probar recuperación de contraseña

### Este mes
1. ✅ Usar cotizador activamente con clientes reales
2. ✅ Revisar auditoría regularmente
3. ✅ Revisar historial y estadísticas del Dashboard
4. ✅ Realizar backups de la base de datos

---

## 💡 TIPS IMPORTANTES

🎯 **PDFs del cotizador incluyen el logo MQF** — impresiona a tus clientes  
📧 **El SMTP se configura sin código** — solo llena el formulario del Dashboard  
🔑 **Los usuarios pueden recuperar su contraseña solos** — vía email  
👥 **Los códigos de usuarios son automáticos** — el backend los genera según el perfil  
📊 **Las estadísticas del Dashboard son en tiempo real** — se cargan desde la API  
🔒 **El menú se adapta al rol** — un PDV nunca verá el módulo de usuarios

---

## 🏁 ¡COMIENZA AQUÍ!

```
Paso 1: Lee RESUMEN_EJECUTIVO.md (10 min)
         → Entiende qué cambió en v2.1.0

Paso 2: Lee INSTALACION_RAPIDA.md (10 min)
         → Sigue los pasos exactos

Paso 3: Instala y entra (15 min)
         → http://localhost/PLATAFORMA_INTEGRADA

Paso 4: Configura SMTP (10 min)
         → Dashboard → Seguridad → Configuración SMTP

Paso 5: Crea usuarios del equipo (10 min)
         → Dashboard → Usuarios → + Crear Usuario

¡LISTO! → El sistema está operativo ✅
```

---

**Tiempo total hasta tener el sistema plenamente configurado: ~55 minutos**

---

*Documento de orientación actualizado para la versión 2.1.0 de la plataforma.*  
*Actualizado: 22 de Abril de 2026*
