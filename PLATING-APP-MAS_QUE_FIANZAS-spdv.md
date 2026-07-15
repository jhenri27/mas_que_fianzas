# 📱 APK Android — Socio PDV | Más Que Fianzas

## Resumen Ejecutivo

Desarrollo de una **aplicación Android nativa** para el perfil **Socio PDV** (Punto de Venta), que permita gestionar operaciones de seguros y fianzas desde dispositivos móviles (celular, tablet y verifone). La app consumirá la **API REST existente** de la plataforma via `Bearer Token`, autenticándose contra el mismo backend PHP/MySQL en producción VPS.

---

## 🔍 Análisis de la Plataforma Existente

### API REST — Estado Actual
La plataforma ya expone una API REST completamente funcional con autenticación por **Bearer Token** (tabla `sesiones_usuario`). Todos los endpoints relevantes ya están preparados para consumo móvil:

| Endpoint | Archivo | Función |
|---|---|---|
| `POST /api/auth.php/login` | `auth.php` | Login con username/password → retorna `token_sesion` |
| `POST /api/auth.php/logout` | `auth.php` | Cerrar sesión |
| `GET /api/cotizaciones.php` | `cotizaciones.php` | Listar / crear cotizaciones |
| `GET /api/polizas.php` | `polizas.php` | Listar / ver pólizas |
| `GET /api/cobros.php` | `cobros.php` | Portal de Gestión de Cobros (PGC) |
| `GET /api/comisiones_panel.php` | `comisiones_panel.php` | Panel de comisiones |
| `GET /api/clientes.php` | `clientes.php` | Gestión de clientes |
| `GET /api/pagos.php` | `pagos.php` | Gestión de pagos |
| `GET /api/mi_perfil.php` | `mi_perfil.php` | Perfil del usuario |
| `GET /api/notificaciones.php` | `notificaciones.php` | Centro de notificaciones |

**Clave**: La autenticación ya soporta `Bearer Token` — la app puede operar sin necesidad de cookies/sesiones PHP. ✅

---

## Stack Tecnológico Recomendado

### Opción Seleccionada: **React Native (Expo) + Gemini AI**

> [!IMPORTANT]
> **¿Por qué React Native con Expo?**
> - **Código único** que genera APK para Android Y iOS en el futuro
> - **Expo Go** permite pruebas instantáneas sin compilar durante desarrollo
> - El usuario mencionó **Google AI Studio** — integraremos el SDK de **Gemini API** directamente en la app para asistencia inteligente
> - **Cero costo de infraestructura adicional** — reutiliza 100% el backend PHP existente
> - Ampliamente adoptado en el mercado insurtech latinoamericano

### Arquitectura de la Solución

```
┌─────────────────────────────────────────┐
│          APP SOCIO PDV (React Native)    │
│  ┌──────────┐  ┌──────────┐  ┌────────┐ │
│  │  Auth    │  │ Pólizas  │  │Comis.  │ │
│  │  Screen  │  │ Screen   │  │Screen  │ │
│  └──────────┘  └──────────┘  └────────┘ │
│  ┌──────────┐  ┌──────────┐  ┌────────┐ │
│  │ Cotizar  │  │  Cobros  │  │Gemini  │ │
│  │  Screen  │  │  Screen  │  │AI Chat │ │
│  └──────────┘  └──────────┘  └────────┘ │
│         ┌─────────────────┐              │
│         │  AsyncStorage   │              │
│         │  (token + cache)│              │
│         └─────────────────┘              │
└──────────────────┬──────────────────────┘
                   │ HTTPS + Bearer Token
    ┌──────────────▼──────────────────────┐
    │    VPS: 85.239.248.147              │
    │    Apache + PHP 8.2 + MySQL         │
    │    API REST Más Que Fianzas         │
    └─────────────────────────────────────┘
                   │
    ┌──────────────▼──────────────────────┐
    │    Google Gemini API                │
    │    (AI Studio Key)                  │
    └─────────────────────────────────────┘
```

---

## Módulos de la App (Funcionalidades por Pantalla)

### 🔐 1. Módulo de Autenticación
- **Login screen** con campos `usuario` y `contraseña`
- Soporte para **2FA** (si está activado)
- **Persistencia de sesión** via `AsyncStorage` (token cifrado)
- Pantalla de recuperación de contraseña
- Detección de sesión expirada con re-login suave

### 📊 2. Dashboard Principal (Home)
- Resumen de métricas del día:
  - Cotizaciones pendientes
  - Pólizas activas en cartera
  - Cobros del mes
  - Comisiones acumuladas
- **Notificaciones push** (FCM - Firebase Cloud Messaging)
- Accesos rápidos a las funciones más usadas
- Banner de alertas críticas

### 📄 3. Cotizaciones
- Listado de cotizaciones (propias del Socio PDV)
- Formulario de **nueva cotización** (ramos: Auto, Vida, Fianzas)
- Visualizar PDF de cotización generado
- Enviar cotización por WhatsApp/Email
- Filtros por fecha, estado, cliente

### 📋 4. Pólizas
- Consultar cartera de pólizas propias
- Detalle completo de cada póliza
- Estado: activa, vencida, cancelada, siniestrada
- Ver documentos adjuntos
- **Verificador de póliza** por número/código

### 💰 5. Portal de Cobros (PGC)
- Ver cuotas pendientes de clientes
- Registrar promesas de pago
- Gestión de cobros con prorrata automática
- Historial de gestiones por póliza
- Panel de morosidad

### 💸 6. Comisiones
- Dashboard personal de comisiones
- Desglose por período (semanal/mensual)
- Estado de pagos: pendiente/pagado
- Ver datos bancarios registrados
- Historial de liquidaciones

### 👥 7. Clientes
- Buscar clientes por nombre/cédula/RNC
- Ver ficha completa del cliente
- Historial de pólizas por cliente
- Registrar nuevo cliente rápido

### 🤖 8. Asistente IA (Gemini)
- **Chat integrado con Gemini API** (Google AI Studio)
- Consultas sobre productos, tarifas, procedimientos
- Ayuda para completar cotizaciones
- Análisis de documentos con cámara (OCR → Gemini)
- Respuestas contextualizadas al seguro dominicano

### 👤 9. Mi Perfil
- Ver datos del perfil Socio PDV
- Cambiar contraseña
- Configurar notificaciones
- Información de comisionante superior

---

## Plan de Implementación por Fases

### Fase 1 — Backend (Semana 1)
> Preparar el backend existente para consumo móvil seguro.

- [ ] Crear endpoint `GET /api/mobile/dashboard.php` (resumen métricas)
- [ ] Agregar soporte FCM (Firebase Cloud Messaging) en `NotificacionesEngine.php`
- [ ] Crear tabla `dispositivos_fcm` para tokens push
- [ ] Agregar endpoint `POST /api/mobile/fcm_register.php`
- [ ] Revisar y endurecer CORS para dominio VPS

### Fase 2 — Proyecto React Native Base (Semana 1-2)
- [ ] Inicializar proyecto Expo con `npx create-expo-app`
- [ ] Configurar navegación con `React Navigation` (Stack + Bottom Tabs)
- [ ] Implementar cliente HTTP con Axios + interceptors (token automático)
- [ ] Implementar `AuthContext` con persistencia en `AsyncStorage`
- [ ] Configurar variables de entorno (`EXPO_PUBLIC_API_BASE_URL`)

### Fase 3 — Pantallas Core (Semana 2-3)
- [ ] Login Screen
- [ ] Dashboard/Home con métricas
- [ ] Cotizaciones (lista + detalle + nueva)
- [ ] Pólizas (lista + detalle)
- [ ] Clientes (búsqueda + ficha)

### Fase 4 — Módulos Financieros (Semana 3-4)
- [ ] Portal de Cobros PGC
- [ ] Comisiones Dashboard
- [ ] Pagos y comprobantes
- [ ] Mi Perfil y configuración

### Fase 5 — IA y Push (Semana 4)
- [ ] Integración Gemini API (Google AI Studio key)
- [ ] Chat assistant con contexto del Socio
- [ ] Notificaciones push con Firebase (FCM)
- [ ] Cámara + OCR con Gemini Vision

### Fase 6 — Build y Distribución (Semana 5)
- [ ] Build APK con **EAS Build** (Expo Application Services)
- [ ] Testing en dispositivos físicos (Android 10+)
- [ ] Distribución interna via URL directa (APK descargable)
- [ ] Opcional: publicación en Google Play Store

---

## Estructura de Archivos del Proyecto

```
masque-fianzas-pdv/
├── app/
│   ├── (auth)/
│   │   ├── login.tsx
│   │   └── recuperar.tsx
│   ├── (tabs)/
│   │   ├── index.tsx           ← Dashboard
│   │   ├── cotizaciones.tsx
│   │   ├── polizas.tsx
│   │   ├── cobros.tsx
│   │   ├── comisiones.tsx
│   │   └── perfil.tsx
│   └── _layout.tsx
├── components/
│   ├── ui/
│   ├── cards/
│   └── modals/
├── services/
│   ├── api.ts                  ← Cliente HTTP centralizado
│   ├── auth.service.ts
│   ├── cotizaciones.service.ts
│   ├── polizas.service.ts
│   └── gemini.service.ts
├── store/
│   └── auth.store.ts           ← Zustand state management
├── hooks/
│   └── usePermissions.ts
├── constants/
│   └── Colors.ts
└── .env
    ├── EXPO_PUBLIC_API_URL=https://85.239.248.147/...
    └── EXPO_PUBLIC_GEMINI_KEY=...
```

---

## Integración con Google AI Studio / Gemini

> [!NOTE]
> El usuario tiene acceso a https://aistudio.google.com/u/1/apps — se usará una **API Key de Gemini** para el módulo de Asistente IA integrado en la app.

**Casos de uso de Gemini en la app:**
1. **Chat de soporte**: El Socio PDV hace preguntas sobre productos, el AI responde con contexto del catálogo de seguros.
2. **Analizador de documentos**: Foto de cédula/RNC → Gemini Vision extrae los datos y los rellena en el formulario.
3. **Resumen de póliza**: El AI genera un resumen claro de los términos de una póliza en lenguaje simple.
4. **Asistente de cotización**: El AI guía al agente paso a paso para llenar una cotización correctamente.

**Modelo a usar**: `gemini-1.5-flash` (rápido, económico, ideal para mobile)

---

## Seguridad y Mejores Prácticas

> [!WARNING]
> Consideraciones de seguridad críticas para la APK:

1. **API Key de Gemini**: Nunca exponer en el código fuente. Usar backend proxy o variable de entorno Expo.
2. **Token de sesión**: Almacenado en `SecureStore` (Expo), no en AsyncStorage plano.
3. **Certificado SSL**: El VPS debe tener HTTPS activo para que la APK pueda conectarse en Android 9+.
4. **Certificate Pinning**: Implementar en producción para evitar ataques MITM.
5. **Ofuscación de código**: Usar ProGuard / R8 en el build de producción.
6. **Timeout de sesión**: Detectar inactividad y hacer logout automático después de 30 min.

---

## Decisiones de Diseño

> [!IMPORTANT]
> **Preguntas abiertas antes de ejecutar:**

1. **¿La VPS tiene HTTPS activo?** — Android 9+ requiere HTTPS para conexiones de red. Si no, hay que agregar el certificado SSL al VPS primero.
2. **¿Tienes una API Key de Google AI Studio?** — Necesaria para el módulo Gemini. Puedes crearla en https://aistudio.google.com/u/1/apikey.
3. **¿Distribución interna o Google Play Store?** — Para uso interno: APK descargable directamente. Para Play Store se necesita una cuenta de desarrollador Google ($25 USD una vez).
4. **¿Se requiere soporte de Verifone?** — Los verifones Android pueden instalar APK si tienen modo sideload habilitado. Confirmar modelo de verifone.
5. **¿Qué permisos debe tener el Socio PDV exactamente?** — Confirmar si puede crear cotizaciones desde cero o solo consultar las asignadas.

---

## Herramientas y Tecnologías

| Herramienta | Propósito | Costo |
|---|---|---|
| React Native + Expo | Framework móvil multiplataforma | Gratis |
| EAS Build | Compilar APK en la nube | Gratis (tier gratuito) |
| Google Gemini API | Asistente IA integrado | Pay-per-use (muy bajo) |
| Firebase FCM | Notificaciones push | Gratis |
| Axios | Cliente HTTP | Gratis |
| Zustand | State management | Gratis |
| React Navigation | Navegación entre pantallas | Gratis |
| Expo SecureStore | Almacenamiento seguro de tokens | Gratis |

---

## Verificación del Plan

### Pruebas Técnicas
- [ ] Verificar conectividad con la API del VPS desde red móvil
- [ ] Test de autenticación Bearer Token desde Postman/Insomnia
- [ ] Test de cada endpoint en condición de red lenta (3G)

### Entrega
- [ ] APK de desarrollo (debug) para testing inmediato con Expo Go
- [ ] APK de producción (release) firmada y lista para instalar
- [ ] Documentación de configuración (API URL + Keys)

---

*Plan preparado el 2026-07-15 | Más Que Fianzas — PLATING-KIT v4.0*
