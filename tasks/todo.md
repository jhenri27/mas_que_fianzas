# TODO — PLATAFORMA MAS QUE FIANZAS
## Sistema NOFTRAB v1.0 — Gestión de Tareas
**Última actualización:** 20 Mayo 2026
> Leer tasks/NOFTRAB.md al inicio de cada sesión. Actualizar este archivo al avanzar.

---

## SPRINT 0 — Estabilización (ACTIVO)
> Verificar que el 72% existente funciona antes de construir lo nuevo.

- [ ] S0.1 Probar Cotizaciones: Fianza + Seguro de Ley → PDF → historial Edge normal
- [ ] S0.2 Probar Pólizas: wizard 4 pasos, prima+ITBIS, registrar pago, marbete PDF
- [ ] S0.3 Probar Clientes: CRUD + exportar XLSX + PDF individual
- [ ] S0.4 Probar Centro Financiero: asiento contable + generar NCF
- [x] S0.5 Limpiar /backend/api/ — mover fix_*.php, test_*.php, info_*.php a /tasks/dev/
- [x] S0.6 Agregar validarSesion() a cotizaciones.php y clientes.php (deuda de seguridad)

---

## SPRINT 1 — Módulo Fianzas (P1 — 1 semana)
> Core del negocio. Convierte cotizaciones en fianzas formales.
> Dependencia: S0.1 completado.

- [ ] Verificar tabla `fianzas` en BD masque_fianzas_integrada_01
- [ ] Crear backend/api/fianzas.php (listar, guardar, editar, cancelar, renovar)
- [ ] Crear frontend/modulos/fianzas.html — Wizard 3 pasos + tabla + acciones
- [ ] Conectar Dashboard sidebar "FIANZAS" → cargar fianzas.html en iframe
- [ ] Probar: crear fianza desde cotización → PDF → aparece en tabla

---

## SPRINT 2 — Módulo Pagos (P1 — 1 semana)
> Trazabilidad de flujo de caja. Dependencia: Sprint 1 completado.

- [ ] Verificar/crear tabla `pagos` en BD
- [ ] Crear backend/api/pagos.php (registrar, listar, estado_cuenta, recibo)
- [ ] Crear frontend/modulos/pagos.html — formulario + tabla + estado de cuenta
- [ ] Recibo de pago en PDF con NCF
- [ ] Conectar sidebar "PAGOS" → iframe

---

## SPRINT 3 — Reportes Generales (P1 — 4 días)
> Completar Tab 2 de reportes.html. Dependencia: Datos en todos los módulos.

- [ ] Crear backend/api/reportes.php con queries de producción, vencimientos, comisiones
- [ ] Completar Tab 2 en reportes.html: Producción, Vencimientos, Comisiones, KPIs
- [ ] Integrar Chart.js para gráficas

---

## SPRINT 4 — Siniestros + Productos (P2 — 1.5 semanas)

- [ ] Crear frontend/modulos/siniestros.html + backend/api/siniestros.php
- [ ] Crear frontend/modulos/productos.html + backend/api/productos.php
- [ ] Conectar ambos en sidebar

---

## SPRINT 5 — Hardening y Producción (1 semana)

- [ ] validarSesion() en TODAS las APIs
- [ ] Mi Perfil funcional para todos los usuarios
- [ ] Notificaciones de vencimientos en dashboard
- [ ] Config empresa: RNC, dirección, teléfono en PDFs
- [ ] Cambiar contraseña pdv.prueba antes de producción
- [ ] Documentación final actualizada

---

## COMPLETADO ✅

- [x] NOFTRAB.md, todo.md, lessons.md creados en /tasks/
- [x] Login y autenticación bcrypt
- [x] Dashboard con estadísticas MySQL
- [x] Módulo Cotizaciones con MySQL + PDF
- [x] Módulo Clientes con CRUD + PDF + import/export
- [x] Módulo Pólizas — wizard completo (1,639 líneas)
- [x] Módulo Usuarios — red comercial + comisiones
- [x] Centro Financiero — partida doble + NCF
- [x] Historial cotizaciones: Chrome + Edge normal e incógnito
- [x] Logo actualizado en pantallas y PDFs
- [x] Módulo Configuración: cambio de logo dinámico + SMTP
- [x] .htaccess no-cache para evitar caché en Edge
- [x] Cache-busting con timestamp dinámico en iframes
- [x] migrate.html para sync localStorage → MySQL


---

## SPRINT ACTUAL: FASE 1 — Estabilización

### Validación de módulos existentes
- [ ] Probar cotizaciones end-to-end (Fianza + Seguro de Ley → PDF → MySQL historial)
- [ ] Verificar que historial de cotizaciones carga en Chrome y Edge (normal e incógnito)
- [ ] Probar módulo Clientes: crear, editar, buscar, PDF individual
- [ ] Probar módulo Pólizas: crear póliza vinculada a cliente
- [ ] Verificar módulo Usuarios: crear usuario PDV, iniciar sesión, ver cotizaciones

### Corrección de logo en PDFs
- [ ] Ejecutar `update_logo_pdf.php` y verificar visualmente en PDF generado
- [ ] Confirmar que `logo_b64.js?v=4` carga el logo nuevo (no el anterior)

### Limpieza de archivos de desarrollo
- [x] Mover `info_usuario.php`, `fix_cotizaciones_v*.php`, `test_*.php` a una carpeta `/dev/` protegida

---

## BACKLOG: FASE 2 — Módulos Core

- [ ] **Módulo Fianzas** — crear desde cero con formulario, tabla, PDF
- [ ] **Módulo Pagos** — registro de pagos y estado de cuenta
- [ ] **Módulo Reportes** — reemplazar placeholder con reportes reales

---

## BACKLOG: FASE 3 — Módulos Secundarios

- [ ] Módulo Siniestros
- [ ] Módulo Productos (catálogo de coberturas)
- [ ] Configuración avanzada (empresa, tasas, plantillas)

---

## BACKLOG: FASE 4 — Pulido

- [ ] Mi Perfil completo para todos los usuarios
- [ ] Seguridad: proteger APIs con validación de sesión
- [ ] Notificaciones de vencimientos de pólizas

---

## COMPLETADO ✅

- [x] Login y autenticación bcrypt
- [x] Dashboard con estadísticas
- [x] Módulo Cotizaciones con MySQL
- [x] Módulo Clientes con CRUD + PDF
- [x] Historial cotizaciones: Chrome + Edge modo normal e incógnito
- [x] Logo actualizado en pantallas y PDFs
- [x] Módulo Configuración: cambio de logo dinámico
- [x] .htaccess no-cache para evitar problemas de caché en Edge
- [x] migrate.html para sincronizar localStorage → MySQL
- [x] Cache-busting con timestamp dinámico en iframes
