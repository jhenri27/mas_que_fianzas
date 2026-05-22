# NOFTRAB — New Orquestación del Flujo de Trabajo
## Sistema de Gobierno para el Agente IA — MAS QUE FIANZAS
**Versión:** 1.0 | **Integrado:** 20 Mayo 2026
> Este archivo es la constitución de trabajo del agente. Se lee al inicio de CADA sesión.

---

## REGLA DE ORO
> **"¿Aprobaría esto un ingeniero senior (staff engineer)?"**
> Si la respuesta es NO → vuelve a planificar antes de presentar.

---

## 1. PLANIFICACIÓN POR DEFECTO

**Entra en modo PLAN para CUALQUIER tarea que tenga más de 3 pasos o decisiones arquitectónicas:**

```
SEÑALES que requieren planificación:
✦ Crear un módulo nuevo (HTML + API + BD)
✦ Cambiar lógica de autenticación o sesiones
✦ Modificar esquema de base de datos
✦ Integrar una librería nueva
✦ Cualquier cambio que afecte más de 2 archivos
✦ Decisiones de arquitectura (¿iframe o SPA? ¿MySQL o localStorage?)
```

**Protocolo de planificación:**
1. Investigar estado actual (subagentes si es necesario)
2. Escribir spec detallada en `tasks/todo.md` con ítems marcables
3. Confirmar con el usuario ANTES de implementar
4. Si algo falla → DETENER → replantear → confirmar de nuevo
5. Usar planificación para VERIFICACIÓN, no solo construcción

**NO requieren planificación:**
- Correcciones de typo o sintaxis
- Cambios de estilo CSS triviales
- Incrementar un número de versión (v=N)
- Respuestas informativas sin cambio de código

---

## 2. ESTRATEGIA DE SUBAGENTES

**Usar subagentes generosamente para:**
- Investigación y auditoría de archivos grandes
- Análisis paralelo (explorar frontend Y backend al mismo tiempo)
- Tareas que contaminarían el contexto principal (leer 20 archivos)
- Verificación de funcionamiento en el navegador (browser subagent)

**Reglas de subagentes:**
```
✦ UNA tarea por subagente — foco total, no multitask
✦ Lanzar en PARALELO cuando las tareas son independientes
✦ El subagente NUNCA modifica código — solo investiga y reporta
✦ Workspace del subagente = el directorio mínimo necesario
✦ Si el subagent falla por capacidad → reintentar con "self" subagent
```

**Plantilla estándar de prompt para subagente:**
```
Analiza [ARCHIVO/DIRECTORIO]. Reporta:
1. [Pregunta específica 1]
2. [Pregunta específica 2]
Sé conciso pero preciso. NO modifiques nada.
```

---

## 3. BUCLE DE AUTOMEJORA

**Después de CUALQUIER corrección del usuario:**
1. Identificar el patrón del error (¿fue de contexto? ¿de lógica? ¿de caché?)
2. Escribir una regla en `tasks/lessons.md` con formato [L-XXX]
3. Si el mismo error ocurre 2 veces → escalarlo como "ERROR CRÍTICO RECURRENTE"

**Al inicio de cada sesión:**
1. Leer `tasks/lessons.md` — aplicar reglas existentes
2. Leer `tasks/todo.md` — retomar donde quedamos
3. Verificar que los módulos "en progreso" siguen funcionando

---

## 4. VERIFICACIÓN ANTES DE FINALIZAR

**Nunca marcar como ✅ sin demostrar funcionamiento:**

```
Checklist de cierre por tarea:
□ El código ejecuta sin errores (PHP, JS consola)
□ La funcionalidad se puede probar en el navegador
□ Diff revisado — no hay efectos secundarios en otros módulos
□ Si afecta Edge → probado específicamente en Edge
□ El logo/UI se ve correcto (screenshot si aplica)
□ tasks/todo.md actualizado con ✅
```

**Para cambios de API:**
```
□ Endpoint responde con JSON válido
□ Casos de error manejados (datos vacíos, BD caída)
□ No expone información sensible (passwords, tokens)
```

---

## 5. EXIGIR ELEGANCIA (EQUILIBRADO)

**Para cambios NO triviales — preguntarse ANTES de codificar:**
> "¿Existe una forma más simple y mantenible de lograr esto?"

**Señales de código "sucio" (hacky) que debo refactorizar:**
```
✦ Copy-paste del mismo bloque en 3+ lugares → extraer función
✦ Un fix que requiere otro fix → buscar causa raíz
✦ Un setTimeout() para "esperar que cargue" → usar Promise/evento
✦ Versión estática ?v=3 como cache-buster → usar timestamp dinámico
✦ Rutas relativas dentro de iframes → siempre rutas absolutas
✦ Un script PHP de "corrección" que no se limpia después → limpiar
```

**Evitar sobre-ingeniería para:**
- Cambios de texto, etiquetas o colores
- Un solo incremento de versión
- Agregar un campo a un formulario existente

---

## 6. RESOLUCIÓN AUTÓNOMA DE ERRORES

**Cuando el usuario reporta un error:**
```
PROCESO:
1. Reproducir el error (¿qué acción lo genera?)
2. Localizar la causa raíz (revisar consola, logs, BD, PHP)
3. Aplicar fix directo sin pedir confirmación (si es < 3 pasos)
4. Si > 3 pasos → planificar primero
5. Demostrar que el fix funciona (screenshot o respuesta del servidor)
6. Documentar en lessons.md si es un patrón nuevo
```

**Herramientas de diagnóstico disponibles en el proyecto:**
- `backend/api/read_error_log.php` → errores PHP
- `backend/api/debug_db.php` → verificar conexión BD
- `backend/api/diagnostico.php` → estado general del sistema
- `frontend/modulos/labs-masqf.html` → terminal de eventos + status

---

## CONTEXTO DEL PROYECTO (Referencia Rápida)

### Credenciales
| Usuario | Contraseña | Perfil |
|---------|-----------|--------|
| `admin` | `Demo@123` | Administrador |
| `pdv.prueba` | `PDV@2024` | Socio Comercial PDV |

### URLs Clave
```
Login:       http://localhost/PLATAFORMA_INTEGRADA/frontend/
Dashboard:   http://localhost/PLATAFORMA_INTEGRADA/frontend/dashboard.html
API Base:    http://localhost/PLATAFORMA_INTEGRADA/backend/api/
phpMyAdmin:  http://localhost/phpmyadmin/
BD:          masque_fianzas_integrada_01
```

### Patrones Obligatorios en esta Plataforma
```
✦ fetch() dentro de módulos/iframes → SIEMPRE rutas absolutas /PLATAFORMA_INTEGRADA/...
✦ Cache-busting en iframes → ?t=Date.now() dinámico, NUNCA ?v=N estático
✦ APIs → patrón ?action=listar|guardar|editar|eliminar (sin mod_rewrite)
✦ Logo para PDF → usar logo_b64.js (PNG con transparencia, NO .ico)
✦ Sesiones → validar con api-client.js antes de cargar cualquier módulo
✦ .htaccess Cache-Control: no-store en /frontend/ → NUNCA eliminar
```

### Marco Regulatorio RD (Contexto de Negocio)
```
Ley 146-02 — Ley General de Seguros y Fianzas de la RD
SIV (Superintendencia de Seguros) — ente regulador
ITBIS: 18% sobre primas de seguros
NCF: Números de Comprobante Fiscal (obligatorio en facturas)
Tipos principales: Fianzas, Seguro de Ley, Vehículos, RC, Incendio
```
