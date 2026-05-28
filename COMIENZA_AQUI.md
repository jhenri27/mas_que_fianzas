# 🧭 GUÍA DE ORIENTACIÓN — ¿POR DÓNDE EMPEZAR?

> **Versión:** 4.0.0 Stable | **Actualizado:** 22 de Mayo de 2026 | **Estándar:** NOFTRAB v4.0 (Logs Inmutables)

---

## 👋 Bienvenido a MÁS QUE FIANZAS (Plataforma Estabilizada v4.0.0)

Has recibido una plataforma robusta y estabilizada bajo estrictas normas de aseguramiento de calidad. Este documento te guía sobre **qué leer primero** y **cómo operar las nuevas herramientas** según tu perfil.

---

## 🌟 NOVEDADES — Versión 4.0.0 Stable (Mayo 2026)

La plataforma ha sido enriquecida y estabilizada con funcionalidades de alta seguridad corporativa y diseño visual premium:

| Funcionalidad | Estándar / Estado | Descripción |
|--------------|-------------------|-------------|
| **Auditoría Inmutable NOFTRAB v4.0** | ⚖️ Norma NOFTRAB v4.0 | Cualquier ajuste o edición en pólizas, pagos o comisiones requiere obligatoriamente una justificación (>9 caracteres) y guarda los estados `before`/`after` en JSON en `historial_ajustes`. |
| **Privacidad "Propios vs. Todos"** | 🔐 Control de Acceso Granular | Los usuarios con la restricción `solo_propios = 1` activa en su perfil solo visualizan sus propias transacciones en listados, comisiones y modales del Dashboard. |
| **Widget de Pólizas Emitidas** | 📊 Premium Glassmorphism | Panel en la columna izquierda inferior con pills degradados dinámicos de emisión diaria, semanal y mensual, Top 5 de clientes con barras de progreso y botón maximizar. |
| **Modal Analítico de Pólizas** | 📈 Premium UI | Modal enriquecido `#modalPolizasDetalle` con el desglose analítico de emisiones y clientes que respeta automáticamente las reglas de privacidad de datos. |
| **Avatar Superior Interactivo** | 👤 Premium UI / UX | Cabecera `.user-info` con hover animado (escala y transiciones suaves) y click-handler que abre directamente el panel de edición "Mi Perfil". |
| **Puente de Intercepción Iframe** | 🔗 Iframe Bridge | Los submódulos en iframes (ej: `polizas.html`) interceptan los cambios de estado y delegan la recolección de justificación obligatoria al dashboard padre mediante `window.parent.solicitarAjusteAuditoria(...)`. |

---

## 🎯 ELIGE TU PERFIL

### 👑 Soy ADMINISTRADOR del Sistema
**Tiempo estimado: 45 minutos**

1. **[5 min]** Leer: [RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md)
   - Comprender el estándar de auditoría NOFTRAB v4.0 y el alcance de la versión.
2. **[10 min]** Leer: [INSTALACION_RAPIDA.md](INSTALACION_RAPIDA.md)
   - Pasos exactos para inicializar o actualizar el sistema WAMP y las migraciones.
3. **[15 min]** Acceder al sistema y configurar SMTP:
   - URL: `http://localhost/PLATAFORMA_INTEGRADA`
   - Ingresar con credenciales: `admin` / `Demo@123`
   - Ir a: Dashboard → módulo Seguridad → Configuración SMTP. Rellenar los campos y hacer clic en **"Probar conexión"**.
4. **[15 min]** Crear y configurar perfiles:
   - Crear un usuario Socio Comercial PDV y activar el checkbox de comisiones.
   - Verificar en la tabla de usuarios la nomenclatura automática (ej: `PDV-001`).
   - Ir a la base de datos y verificar que para el perfil PDV la opción `solo_propios` esté activa en la malla de permisos.

**Resultado esperado:** Servidor de correo activo, usuarios jerárquicos creados y árbol de referidos operando ✅

---

### 🏪 Soy SOCIO COMERCIAL (PDV)
**Tiempo estimado: 15 minutos**

1. **[2 min]** Acceder a la plataforma con las credenciales que te asignó el administrador:
   ```
   http://localhost/PLATAFORMA_INTEGRADA
   ```
2. **[5 min]** Explorar el Dashboard de Inicio:
   - Verás el nuevo **Widget de Pólizas Emitidas** en la columna izquierda inferior. Las cifras diarias, semanales y mensuales reflejarán **exclusivamente tu propia producción**.
   - Haz clic en el botón de maximizar en la esquina superior derecha del widget para abrir el **Modal Analítico**. La lista de clientes y las barras de progreso se autolimitarán de forma segura a tus operaciones.
3. **[5 min]** Realizar una cotización y emitir póliza:
   - Cotizaciones → Seguros de Ley → Rellenar datos → Calcular → Guardar e imprimir PDF.
   - Pólizas → Emitir. Comprobarás que al intentar realizar un ajuste, el sistema te solicitará obligatoriamente una justificación detallada.
4. **[3 min]** Gestionar tu cuenta:
   - Pasa el ratón sobre tu foto en la cabecera (notarás un suave efecto hover de escala y sombra). Haz clic en él para abrir instantáneamente la interfaz de **"Mi Perfil"** y cambiar tu contraseña inicial.

**Resultado esperado:** Dominar el cotizador y el panel de estadísticas privadas de tu punto de venta ✅

---

### 🔎 Soy AUDITOR / Compliance
**Tiempo estimado: 30 minutos**

1. **[5 min]** Leer: [ESPECIFICACIONES.md](ESPECIFICACIONES.md)
   - Estudiar las reglas de auditoría del Estándar NOFTRAB v4.0.
2. **[15 min]** Auditar el Historial de Ajustes Inmutable:
   - Ir a la base de datos a la tabla `historial_ajustes`.
   - Modificar cualquier póliza desde el panel de administración ingresando la justificación (ej. "Corrección de prima por error de digitación").
   - Comprobar que en la base de datos se almacene de forma inmutable el registro del ajuste con el estado JSON anterior (`valor_anterior`) y posterior (`valor_nuevo`), el `usuario_id`, IP y marca de tiempo.
3. **[10 min]** Inspeccionar los logs técnicos y accesos:
   - Ir a: Dashboard → Usuarios → pestaña **"Auditoría"** para revisar la bitácora de accesos.
   - Ir a: Dashboard → Seguridad → pestaña **"Logs SMTP"** para revisar el visor de correos.

**Resultado esperado:** Asegurar la consistencia técnica de la bitácora de auditoría forense inmutable ✅

---

### 🧑‍💻 Soy DESARROLLADOR / Técnico
**Tiempo estimado: 1 hora**

1. **[15 min]** Estudiar las APIs de la v4.0.0 Stable:
   - `backend/api/polizas_stats.php` - Estadísticas y Top 5 con inyección del filtro `restringirSoloPropios()`.
   - `backend/api/ajustes.php` - Endpoint receptor de la justificación que invoca `registrarAjuste()`.
   - `backend/api/perfiles_engine.php` y `backend/perfiles_engine.py` - Motor transaccional de permisos en Python.
2. **[15 min]** Entender el flujo de intercepción de submódulos:
   - Revisar en `frontend/modulos/polizas.html` la función `validarPoliza(id)`. Ella detecta si el script corre en un iframe y redirige al dashboard padre la ejecución de la auditoría:
     ```javascript
     window.parent.solicitarAjusteAuditoria('Pólizas', 'polizas', id, valorAnterior, valorNuevo, () => {
         // Callback que refresca la lista local
     });
     ```
3. **[20 min]** Ejecutar verificaciones:
   - Correr en consola: `php verify_system_end_to_end.php` para validar la correcta integración de todos los componentes y motores de Python/PHP.

**Resultado esperado:** Dominar el puente de comunicación en iframes, la API de estadísticas y el estándar NOFTRAB v4.0 ✅

---

## 📚 DOCUMENTOS EN ORDEN DE LECTURA

```
Para TODOS:
  1. RESUMEN_EJECUTIVO.md       ← Novedades y alcance de la v4.0.0 Stable
     └─ 10 min

Para INSTALAR:
  2. INSTALACION_RAPIDA.md      ← Configuración paso a paso en WAMP
     └─ 10 min

Para ENTENDER LA ARQUITECTURA:
  3. ESPECIFICACIONES.md        ← Matriz de roles y estándar NOFTRAB
     └─ 15 min

Para el COTIZADOR:
  4. INTEGRACION_COTIZADOR.md   ← Operación de primas y PDF MQF
     └─ 10 min

Para NAVEGAR EL CÓDIGO:
  5. INDICE_MAESTRO.md          ← Estructura de carpetas y APIs
     └─ 5 min
```

---

## ✅ CHECKLIST DE INICIO OPERATIVO (v4.0.0 Stable)

### Seguridad y Auditoría
- [ ] La tabla `historial_ajustes` contiene llaves foráneas y registros correctos.
- [ ] Cualquier modificación de póliza, pago o comisión exige justificación obligatoria.
- [ ] La justificación de menos de 10 caracteres es rechazada de forma interactiva.
- [ ] Los logs inmutables JSON se guardan en la base de datos con IP y marca de tiempo.

### Interfaz del Dashboard
- [ ] El Widget de Pólizas se muestra en la barra izquierda inferior (encima de acciones rápidas).
- [ ] Las barras de progreso de clientes cargan con transiciones fluidas de CSS.
- [ ] El Modal Analítico se abre al hacer clic en maximizar y renderiza la tabla de clientes.
- [ ] El avatar del usuario en `.user-info` posee cursor pointer y escala suave en hover.
- [ ] Al hacer clic en el avatar se abre instantáneamente "Mi Perfil".

### Privacidad del Socio Comercial PDV
- [ ] El Socio Comercial PDV no puede ver cotizaciones, pólizas, comisiones o pagos de otros agentes.
- [ ] Los números en el widget de pólizas del PDV corresponden únicamente a su ID de usuario.
- [ ] El Modal de pólizas para el PDV restringe la lista de clientes a los registrados por él.
- [ ] El Administrador conserva el bypass global para supervisar todas las transacciones.

---

*Guía de orientación técnica y operativa.*  
*Actualizado conforme al estándar corporativo NOFTRAB v4.0 de la plataforma MÁS QUE FIANZAS. Mayo de 2026.*
