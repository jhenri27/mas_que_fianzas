# 🧭 GUÍA DE ORIENTACIÓN - ¿POR DÓNDE EMPEZAR?

## 👋 Bienvenido a MAS QUE FIANZAS

Has recibido una plataforma completa y lista para usar. Este documento te guía sobre **qué leer primero** según tu rol.

### 🌟 Novedades Recientes (Actualización Abril 2026)
La plataforma ha sido enriquecida con las siguientes funcionalidades:
- **Integración de Módulos Core**: Cotizaciones, Clientes y Seguros operan al 100% de manera nativa e integrada en el Dashboard.
- **Sistema Avanzado de Gestión de Usuarios**: Nueva nomenclatura jerárquica de IDs (RED-XXX, DIR-XXX, PDV-XXX, VEN-XXX), sistema de referidos en árbol y soporte para cálculo automático de comisiones individuales y vinculadas a la red de ventas.
- **Personalización de Marca**: Actualización dinámica del logo de la plataforma en la interfaz web y documentos PDF directamente desde el Dashboard.
- **Nuevo Perfil Comercial**: Integración del "Socio Comercial (PDV)" con accesos adaptados para el uso del cotizador y la visualización de rendimiento.

---

## 🎯 ELIGE TU PERFIL

### Soy ADMINISTRADOR del Sistema
**Tiempo: 1 hora**

1. **[5 min]** Leer: [RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md)
   - Entender qué se ha entregado

2. **[10 min]** Leer: [INSTALACION_RAPIDA.md](INSTALACION_RAPIDA.md)
   - Pasos exactos para instalar

3. **[20 min]** Hacer: Instalar el sistema
   - Copiar archivos a WAMP
   - Crear BD e importar SQL
   - Acceder a http://localhost/PLATAFORMA_INTEGRADA

4. **[15 min]** Leer: [README.md](README.md) - Sección "Usuarios y Roles"
   - Entender cómo funcionan los permisos

5. **[10 min]** Ejecutar: Crear usuarios en Dashboard
   - Dashboard → Usuarios → "+ Nuevo usuario"

**Resultado esperado:** Sistema funcionando con usuario admin logeado

---

### Soy GERENTE / líder de equipo
**Tiempo: 30 minutos**

1. **[5 min]** Leer: [RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md)
   - Visión general del proyecto

2. **[15 min]** Leer: [ESPECIFICACIONES.md](ESPECIFICACIONES.md) - Sección "Malla de Permisos"
   - Entender roles disponibles
   - Saber qué puede hacer cada rol

3. **[10 min]** Revisar: [INTEGRACION_COTIZADOR.md](INTEGRACION_COTIZADOR.md)
   - Entender módulo de cotizaciones

**Resultado esperado:** Conocer estructura de control de acceso

---

### Soy USUARIO final
**Tiempo: 15 minutos**

1. **[5 min]** Leer: [INSTALACION_RAPIDA.md](INSTALACION_RAPIDA.md) - Sección "Acceso"
   - URL de acceso

2. **[5 min]** Acceder: Abrir http://localhost/PLATAFORMA_INTEGRADA
   - Usuario: admin / Contraseña: Demo@123

3. **[5 min]** Navegar: Explorar el Dashboard
   - Ver módulos disponibles
   - Cambiar contraseña si es necesario

**Resultado esperado:** Saber acceder al sistema y navegar

---

### Soy DESARROLLADOR / Técnico
**Tiempo: 2 horas**

1. **[15 min]** Leer: [INDICE_MAESTRO.md](INDICE_MAESTRO.md)
   - Entender estructura general

2. **[30 min]** Leer: [ESPECIFICACIONES.md](ESPECIFICACIONES.md)
   - Componentes técnicos
   - Tablas de BD
   - Endpoints API

3. **[30 min]** Revisar código:
   - `backend/config.php` - Configuración central
   - `backend/UsuarioManager.php` - Patrón de clase
   - `frontend/assets/api-client.js` - Cliente HTTP

4. **[15 min]** Leer: [README.md](README.md) - Sección "API"
   - Ejemplos de endpoints
   - Formatos de request/response

5. **[30 min]** Hacer: Crear una extensión simple
   - Agregar nuevo rol en BD
   - Asignar permisos
   - Probar desde frontend

**Resultado esperado:** Entender arquitectura, poder extender

---

### Soy AUDITOR / Compliance
**Tiempo: 45 minutos**

1. **[10 min]** Leer: [RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md) - Sección "Seguridad"
   - Medidas implementadas

2. **[15 min]** Leer: [ESPECIFICACIONES.md](ESPECIFICACIONES.md) - Sección "Características de Seguridad"
   - Detalles técnicos de seguridad
   - Auditoría integrada

3. **[10 min]** Revisar: `backend/Autenticacion.php`
   - Entender validación de permisos

4. **[10 min]** Acceder: Dashboard → Usuarios → Tab "Auditoría"
   - Ver registro de accesos

**Resultado esperado:** Entender controles de seguridad y auditabilidad

---

## 📚 DOCUMENTOS EN ORDEN DE LECTURA

### Recomendado para TODOS
```
1. RESUMEN_EJECUTIVO.md       ← Comienza aquí
   ├─ Entender qué se entregó
   └─ Tiempo: 10 min
```

### Siguiente paso (elige uno)
```
2a. INSTALACION_RAPIDA.md     ← Si necesitas instalar
    └─ Tiempo: 10 min

2b. ESPECIFICACIONES.md       ← Si quieres entender técnica
    └─ Tiempo: 20 min

2c. INTEGRACION_COTIZADOR.md ← Si usarás cotizador
    └─ Tiempo: 15 min
```

### Luego (según rol)
```
3a. README.md                 ← Para referencia API
    └─ Tiempo: 30 min

3b. INDICE_MAESTRO.md         ← Para navegar proyecto
    └─ Tiempo: 20 min

3c. VERIFICACION_FINAL.md     ← Para checklist de archivos
    └─ Tiempo: 15 min
```

---

## 🔗 MAPA MENTAL DEL PROYECTO

```
┌─────────────────────────────────────┐
│   PLATAFORMA MAS QUE FIANZAS 1.0    │
└─────────────────────────────────────┘
           ↓↓↓
┌──────────────────────────────────────────────┐
│           ¿QUÉ QUIERO HACER?                 │
└──────────────────────────────────────────────┘
     │          │           │          │
     ↓          ↓           ↓          ↓
  INSTALAR   ENTENDER    USAR       EXTENDER
     │          │           │          │
     ↓          ↓           ↓          ↓
  RÁPIDA    ESPECIF.     INDICES    README
     │          │           │          │
     ↓          ↓           ↓          ↓
  →LEER     →REVISAR     →NAVEGAR   →ANALIZAR
     │          │           │          │
     ↓          ↓           ↓          ↓
  CONFIG     DIAGRAMA     MOVER      CÓDIGO
```

---

## ⏰ TIEMPOS SUGERIDOS

| Actividad | Tiempo | Documento |
|-----------|--------|-----------|
| Visión general | 10 min | RESUMEN_EJECUTIVO |
| Instalación | 15 min | INSTALACION_RAPIDA |
| Primera sesión | 5 min | README (Inicio) |
| Entender permisos | 20 min | ESPECIFICACIONES |
| Usar cotizador | 10 min | INTEGRACION_COTIZADOR |
| Explorar código | 30 min | README (Técnica) |
| Administrar usuarios | 20 min | README (Admin) |
| **TOTAL** | **~110 min** | **Familiarización completa** |

---

## 🎯 FLUJO RECOMENDADO

### Día 1: Instalación y Primer Acceso
```
Mañana:
  1. Leer RESUMEN_EJECUTIVO (10 min)
  2. Leer INSTALACION_RAPIDA (10 min)
  3. Instalar sistema (15 min)
  4. Loguearse como admin (5 min)
  
Tarde:
  5. Explorar Dashboard (10 min)
  6. Crear usuario de prueba (5 min)
  7. Verificar que todo funciona (5 min)

RESULTADO: Sistema funcionando ✅
```

### Día 2: Entender Administración
```
Mañana:
  1. Leer ESPECIFICACIONES - Sección Permisos (15 min)
  2. Leer README - Sección Usuarios (15 min)
  
Tarde:
  3. Crear usuarios adicionales (20 min)
  4. Asignar roles diferentes (20 min)
  5. Probar acceso con diferentes roles (15 min)

RESULTADO: Entender sistema de roles ✅
```

### Día 3: Profundizar
```
Mañana:
  1. Leer README - Sección API (20 min)
  2. Revisar código backend (30 min)
  
Tarde:
  3. Revisar código frontend (30 min)
  4. Entender flujos (20 min)

RESULTADO: Dominar arquitectura técnica ✅
```

---

## 📞 PREGUNTAS FRECUENTES INICIALES

### "¿Dónde comienzo?"
**Respuesta:** Lee **INSTALACION_RAPIDA.md** primero

### "¿Cómo instalo el sistema?"
**Respuesta:** Sigue pasos en **INSTALACION_RAPIDA.md**

### "¿Cuál es la contraseña del admin?"
**Respuesta:** `admin / Demo@123` (ver **INSTALACION_RAPIDA.md**)

### "¿Cómo creo usuarios nuevos?"
**Respuesta:** Dashboard → Usuarios → "+ Nuevo usuario" (ver **README.md**)

### "¿Cómo cambio los permisos de un usuario?"
**Respuesta:** Dashboard → Usuarios → seleccionar usuario → editar rol (ver **ESPECIFICACIONES.md**)

### "¿Dónde veo quién hizo qué?"
**Respuesta:** Dashboard → Usuarios → Tab "Auditoría" (ver **README.md**)

### "¿Cómo integro mi módulo nuevo?"
**Respuesta:** Ver **README.md** sección "Extensibilidad"

### "¿Qué hacer si algo no funciona?"
**Respuesta:** Ver **README.md** sección "Troubleshooting"

---

## 🚦 INDICADORES DE PROGRESO

### ✅ Instalación correcta
- [ ] Sistema accesible en http://localhost/PLATAFORMA_INTEGRADA
- [ ] Login funciona con admin/Demo@123
- [ ] Dashboard muestra todos los módulos
- [ ] Puedes crear un usuario nuevo

### ✅ Entendimiento básico
- [ ] Sabes qué es RBAC
- [ ] Entiendes los 8 roles disponibles
- [ ] Sabes qué permiso controla qué
- [ ] Puedes asignar roles a usuarios

### ✅ Operación normal
- [ ] Usuarios logeados normalmente
- [ ] Permisos funcionan correctamente
- [ ] Auditoría registra acciones
- [ ] Cotizador genera cotizaciones

---

## 📊 ÁRBOL DE DECISIÓN

```
                    ¿YO QUIERO?
                        │
              ┌─────────┼─────────┐
              ↓         ↓         ↓
          INSTALAR  ENTENDER   USAR
              │         │         │
              ↓         ↓         ↓
            RÁPIDA  ESPECIF.   INDICE
```

---

## 🎓 RECURSOS DISPONIBLES

| Tipo | Ubicación | Propósito |
|------|-----------|----------|
| Instalación | INSTALACION_RAPIDA.md | Paso a paso |
| Referencia | README.md | Técnica completa |
| Especificaciones | ESPECIFICACIONES.md | Detalles |
| Decisiones | INDICE_MAESTRO.md | Navegación |
| Validación | VERIFICACION_FINAL.md | Checklist |
| Resumen | RESUMEN_EJECUTIVO.md | Visión general |

---

## ✅ CHECKLIST INICIAL

- [ ] He leído RESUMEN_EJECUTIVO.md
- [ ] He leído INSTALACION_RAPIDA.md
- [ ] He instalado el sistema en WAMP
- [ ] He accedido a http://localhost/PLATAFORMA_INTEGRADA
- [ ] He logueado con admin/Demo@123
- [ ] He visto el Dashboard completo
- [ ] He creado un usuario de prueba
- [ ] He explorado los módulos disponibles

**Si todas están marcadas:** ¡Listo para usar! ✅

---

## 🎯 PRÓXIMAS ACCIONES

### Inmediatas (Hoy)
1. ✅ Instalar sistema
2. ✅ Loguearse como admin
3. ✅ Explorar dashboard

### Corto plazo (Esta semana)
1. ✅ Crear usuarios reales
2. ✅ Asignar roles apropiados
3. ✅ Capacitar equipo

### Mediano plazo (Este mes)
1. ✅ Usar cotizador activamente
2. ✅ Revisar auditoría regularmente
3. ✅ Realizar backups

---

## 💡 TIPS INICIALES

🎯 **Empieza simple:** No intentes hacerlo todo el primer día

📚 **Lee en orden:** No saltes documentos

🧪 **Prueba con usuario de prueba:** No uses admin para todo

🔒 **Entiende permisos:** Es clave para seguridad

📝 **Toma notas:** Documenta tus cambios

🆘 **Cuando dudes:** Revisa README.md

---

## 🏁 ¡COMIENZA AQUÍ!

### Paso 0: Dónde estoy
```
Acabo de recibir la plataforma
```

### Paso 1: Qué hago
```
Lee: RESUMEN_EJECUTIVO.md (5 min)
```

### Paso 2: Cómo instalo
```
Lee: INSTALACION_RAPIDA.md (10 min)
```

### Paso 3: Instalar
```
Sigue los pasos (15 min)
```

### Paso 4: Probar
```
Intenta acceder (5 min)
```

### ✅ Listo
```
Si todo funciona → Continúa explorando
Si hay error → Ver README.md → Troubleshooting
```

---

**Tiempo total hasta tener el sistema funcionando: ~45 minutos**

**¡Comienza ahora leyendo RESUMEN_EJECUTIVO.md!**

---

*Documento de orientación creado para guiarte en tu recorrido por la plataforma.*

*Actualizado: 8 de Abril de 2026*
