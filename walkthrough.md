# Walkthrough - Seguridad de Perfiles, Cotización Progresiva Multicompañía y Ruteo Dinámico en BBS

Este documento resume la implementación y verificación exitosa de los controles de seguridad basados en perfiles, el chatbot conversacional progresivo para cotizaciones de Seguro de Ley, la cotización multicompañía (una cotización física por aseguradora activa), la integración de botones interactivos de acción en el chat y las pruebas integrales de escenarios con cálculos fiscales correctos (impuesto selectivo incluido en la tasa).

---

## Cambios Realizados

### 1. Control de Permisos Granulares y Sembrado en el Core
- **Ubicación**: [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php)
- Se incorporó la rutina de auto-sembrado para las funciones del módulo en la base de datos:
  - `CHAT_BOT_BHN`: Permiso para interactuar con el bot de asistencia técnica.
  - `CHAT_BOT_BBS`: Permiso para utilizar el bot comercial (SSINDI).
- Se habilitaron de forma automática estos permisos para los perfiles de `Administrador` (ID 1) y `Socio Comercial PDV` (ID 5) al cargar el módulo por primera vez.

### 2. Flujo Conversacional Progresivo Multi-Turno (Soberanía del Mensaje)
- **Ubicación**: [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php)
- Se implementó un flujo dinámico que recopila progresivamente los datos del vehículo y del cliente.
- El cliente es soberano y no está restringido a enviar los datos en un solo mensaje; el bot acumula el estado en la tabla `chat_bot_sesiones` de forma transparente.
- Si el cliente escribe **cancelar**, **salir** o **reiniciar**, el bot limpia la sesión y cancela la operación de forma inmediata.

### 3. Cotización Multicompañía e Impuestos de Seguro de Ley (ISC 16% Incluido)
- **Ubicación**: [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php)
- Una vez completados los datos, el bot consulta las aseguradoras activas en `companias_registradas` y genera **un registro de cotización independiente en la tabla `cotizaciones` por cada compañía**.
- **Cálculo Fiscal Correcto (Impuesto Incluido)**: Conforme a la legislación de seguros, el Seguro de Ley no lleva ITBIS. Lleva **Impuesto Selectivo al Consumo (ISC) de 16%**, el cual está **incluido en las tarifas base** de la base de datos.
- Por lo tanto, el bot realiza el desglose matemático inverso:
  - `total` = Tarifa de la base de datos (ej. RD$ 400.00 para motocicletas).
  - `prima_base` (Prima Neta) = `total / 1.16`.
  - `impuesto` (Impuesto Selectivo 16%) = `total - prima_base`.
  Esto asegura que el precio cobrado al cliente coincida exactamente con la tarifa autorizada en la base de datos, desglosando correctamente el impuesto en el sistema y guardándolo en la columna correspondiente.
- Mapea correctamente los campos en `cotizaciones` para evitar mezclas con fianzas: `tipo = 'SEGURO DE LEY'`, `subtipo = [CATEGORIA_VEHICULO]` y `cobertura = [COVERAGE_CODE]` (ej. `MOTOCICLETA BASICO` o `LIVIANO BASICO`).

### 4. Botones Interactivos y Endpoint de Correo
- **Ubicaciones**:
  - [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php)
  - [components.js](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/components.js)
- El bot devuelve botones HTML interactivos integrados en el hilo del chat para cada opción generada:
  - `📥 Descargar PDF`: Un enlace directo a `chat.php?action=descargar_cotizacion` que incluye `&token_sesion=...` para evitar errores 401 de autorización.
  - `📧 Enviar por Correo`: Ejecuta la función global `MQF.enviarEmailCotizacion(btn, id)` expuesta en el frontend.
- Se implementó el endpoint GET `enviar_email_cotizacion` en `chat.php` que desglosa la cotización y la despacha al correo registrado del cliente con el formato oficial e Impuesto Selectivo (16%).

### 5. Redirección y Ruteo en el Frontend (Tab de Vehículos)
- **Ubicación**: [cotizaciones.html](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/cotizaciones.html)
- Se actualizó la función `editarCotizacion` para comprobar si el registro a editar pertenece a una categoría de vehículos y si su identificador de cotización inicia con `'COT-'`.
- Si se cumple la condición, activa de forma automática el tab de **Seguro de Ley de Vehículos** en la interfaz del cotizador.

---

## Resultados de Verificación

### 1. Suite de Pruebas de Escenarios y Casos de BBS
Se ejecutó la suite de simulación y verificación integral del sistema:
- **Comando**: `c:\wamp64\bin\php\php8.2.29\php.exe -f c:\wamp64\www\PLATAFORMA_INTEGRADA\scratch\test_bbs_new_commands.php`
- **Resultados de los Escenarios**:
  - **Escenario 1 (Bloqueo de Permiso)**: Bloqueo seguro para perfiles sin acceso.
  - **Escenario 2 (Acceso Permitido)**: Permitido interactuar al conceder permiso.
  - **Escenario 3 (Cotización NLP Conversacional)**:
    - Mensaje 1: *"deseo cotizar un honda civic negro 2016..."* -> El bot solicita datos faltantes.
    - Mensaje 2: *"Es un carro. Cliente: Juan Perez. Correo: juan.perez@example.com"* -> Completa la cotización, desglosa el 16% de Impuesto Selectivo incluido (ej. Prima Neta: RD$ 1,504.91, Impuesto Selectivo 16%: RD$ 240.79, Total Anual: RD$ 1,745.70), y genera el PDF y envío de correo.
  - **Escenario 4 (Falta de Voucher)**: Solicita obligatoriamente voucher para emitir.
  - **Escenario 5 (Emisión Exitosa)**: Al adjuntar voucher, emite la póliza en estado activa y asocia el pago.
  - **Escenarios 6, 6A, 6B, 6C (Prorata e Investigación NLP)**: Muestra vigencias y calcula la prima no devengada (prorata) correctamente.
  - **Escenario 7 & 8 (Bloqueo de Deuda y Renovación)**: Bloquea renovación si hay deuda, y la aprueba extendiendo la vigencia al saldarse.
  - **Escenario 13 (Cancelación y Cotización de Motocicletas)**:
    - **Parte A**: Cancela y limpia la sesión en `chat_bot_sesiones` de forma inmediata.
    - **Parte B**: Completa cotización de motocicleta desglosando correctamente:
      - **Multiseguros**: Prima Neta: RD$ 344.83, Impuesto Selectivo (16%): RD$ 55.17, Total: RD$ 400.00 (Tarifa base exacta de la base de datos).
      - **Midas Seguros**: Prima Neta: RD$ 362.07, Impuesto Selectivo (16%): RD$ 57.93, Total: RD$ 420.00 (Tarifa base +5% exacta).
      - Mapea correctamente `tipo = 'SEGURO DE LEY'`, `subtipo = 'MOTOCICLETAS'` y `cobertura = 'MOTOCICLETA BASICO'`.

---

## Mejoras de Aspecto Premium y Reducción de Ruido en Chat (Junio 2026)

Se han implementado las siguientes mejoras visuales y funcionales para elevar la experiencia del cliente y la presentación formal de las cotizaciones:

### 1. Simplificación del Chat de BBS (Reducción de Ruido)
- Se eliminaron los detalles redundantes de la burbuja de chat (como prima neta, desglose de impuestos, etc.) que sobrecargaban la conversación.
- El bot ahora solo muestra el nombre de la aseguradora y la prima total a pagar (ej: `🏢 **Multiseguros**: RD$ 1,662.57`), acompañada inmediatamente por sus respectivos botones interactivos de descarga y correo. Toda la información detallada se desplaza a la cotización oficial descargable y al correo.

### 2. Diseño Premium de la Cotización de Descarga (HTML/PDF)
- Se reemplazó la plantilla HTML básica por una interfaz de alta gama:
  - **Tipografía y Estilos**: Se cargan las tipografías modernas `Outfit` e `Inter` de Google Fonts. Colores basados en una paleta de slate/navy oscuro con acentos dorados y azules.
  - **Estructura en Rejilla**: División limpia entre la información del Asegurado y los Datos del Vehículo.
  - **Tarjeta de Totales**: Sección lateral oscura muy atractiva con el logo de la aseguradora, desglose de prima neta, impuesto selectivo (16%) e importe total en tipografía destacada.
  - **Optimización de Impresión**: Reglas CSS `@media print` para asegurar que el documento quepa perfectamente en una hoja A4/Carta al imprimirse o guardarse como PDF.

### 3. Integración Dinámica de Logos y Marcas
- **Marca de la Plataforma**: Se lee el archivo `logo_b64.js` en el servidor y se extrae la constante base64 `LOGO_MQF_B64` mediante expresiones regulares para inyectarla dinámicamente en el encabezado de la cotización.
- **Logos de Aseguradoras**:
  - Para **Multiseguros**, se extrae el base64 de `logos_aseguradoras.js` y se despliega en el desglose de totales.
  - Para **Midas Seguros**, se diseñó un logo en formato SVG premium con un escudo en degradado dorado y la inscripción de la marca.
  - Para otras aseguradoras, se muestra un badge formal con tipografía elegante.

### 4. Código QR de Validación Física
- Se incorporó un código QR dinámico en la parte inferior izquierda de la cotización que enlaza directamente con la URL de descarga y visualización segura de la cotización en la plataforma (incluyendo el parámetro de autenticación `token_sesion`). Esto permite a cualquier persona validar la autenticidad e integridad del documento impreso mediante escaneo.

### 5. Conservación de Detalles del Vehículo
- Se actualizó el backend para almacenar la descripción del vehículo (ej: "Honda Civic Negro 2016") en el campo `beneficiario` de la tabla `cotizaciones`, garantizando que este dato se guarde de forma persistente y se muestre en la cotización de descarga.

### 6. Registro de Origen y Badge del Bot en el Historial
- **Base de Datos**: Se añadió la columna `origen VARCHAR(50) DEFAULT 'web'` a la tabla `cotizaciones` mediante una migración de base de datos.
- **Backend (API)**: Se modificó la consulta SQL de inserción en `chat.php` (y en el script de pruebas) para registrar automáticamente `'bot'` en la columna `origen` cuando la cotización es creada desde el bot BBS. Las cotizaciones preexistentes creadas por el bot fueron migradas automáticamente a `'bot'`.
- **Frontend (UI)**: Se actualizó [cotizaciones.html](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/modulos/cotizaciones.html) para identificar si el origen del registro es `'bot'`. De ser así, muestra un badge azul premium con el ícono de un robot y la etiqueta `🤖 Bot BBS`, responsabilizando formalmente al bot comercial por la cotización en el historial de cotizaciones.

### 7. Validaciones en Tiempo Real y Corrección de Datos Conversacionales (NOFTRAB v4.0)
- **Ubicación**: [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php)
- **Validación de Correo**: El bot ahora valida que los correos electrónicos ingresados en tiempo real no estén malformados, rechazándolos y notificando al cliente mediante una burbuja amigable con sugerencias de formato.
- **Validación de Año de Vehículo**: El bot valida que el año del vehículo ingresado esté en el rango de **1920 a 2027**, previniendo errores de digitación del usuario en tiempo real.
- **Correcciones Conversacionales por Lenguaje Humano**:
  - Si el usuario dice frases naturales como *"puedes corregir el correo que lo puse mal"*, el bot reacciona positivamente y modifica el dato en la sesión activa (`chat_bot_sesiones`).
  - Si el usuario hace este pedido una vez las cotizaciones ya han sido emitidas en la base de datos (dentro de un límite de 15 minutos), el bot actualiza de forma retro-activa los registros de cotizaciones en la base de datos y recalcula automáticamente los precios si el tipo de vehículo cambió.
- **Auditoría Estricta NOFTRAB v4.0**:
  - Cualquier actualización retro-activa a cotizaciones ya guardadas en la base de datos es registrada de manera inmutable en la tabla `historial_ajustes` de la base de datos invocando la función centralizada `registrarAjuste()`.
  - El registro guarda los datos exactos del valor anterior y el valor nuevo en formato JSON, la justificación detallada y la IP del emisor.

---

## Resultados de Verificación (Nuevos Escenarios)

### 1. Suite de Pruebas de Escenarios y Casos de BBS
Se incorporaron y validaron con éxito los siguientes nuevos escenarios:
- **Escenario 14 (Validación de Correo)**: Al ingresar un formato de correo incorrecto (`invalid-email@@domain.com`), el bot BBS lo detecta, lo rechaza y le pide al usuario el formato correcto.
- **Escenario 15 (Validación de Año)**: Al ingresar un año fuera de rango (`2029`), el bot lo detecta y notifica al cliente que debe estar entre 1920 y 2027.
- **Escenario 16 (Corrección en Sesión)**: El bot actualiza dinámicamente los datos de la sesión activa al recibir indicaciones naturales como *"puedes corregir el correo..."* o *"cambia el tipo a moto"*.
- **Escenario 17 (Correcciones Retroactivas y NOFTRAB)**:
  - Al recibir una solicitud de corrección natural posterior a la generación de cotizaciones en la base de datos (ej. *"corrige el correo que lo puse mal, es pedro.actualizado@example.com"*), el bot actualiza todos los registros generados en la base de datos en los últimos 15 minutos.
  - Genera automáticamente los registros de auditoría en `historial_ajustes` guardando los valores `before`/`after` en formato JSON y la justificación.

### 8. Corrección del Autodiagnóstico del Sistema
- **Ubicación**: [bot_testing_dev.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/bot_testing_dev.php)
- Se corrigió la consulta de verificación y limpieza del bot CSR en la suite de diagnóstico. El script buscaba la respuesta del bot utilizando `emisor_id = 1` de manera fija, lo cual fallaba dado que el identificador real de `bot.helpnow` en la base de datos es `121`.
- Tras la corrección, la suite de diagnóstico autónoma se ejecuta con éxito (`fallos_count = 0`), logrando una aprobación completa (verde) en todos los módulos críticos del sistema.

### 9. Visualización de Ambos Bots en la Barra Lateral del Chat (Chats CSR)
- **Ubicación**: [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php)
- **Problema**: El listado de conversaciones en el panel lateral del chat solo mostraba los contactos con quienes ya existían mensajes guardados en la base de datos (`mensajes_chat`). Por lo tanto, si el bot técnico (`bot.helpnow`) no tenía conversaciones activas previas, este no figuraba en la barra lateral. Adicionalmente, el backend no enviaba las propiedades `es_bot` y `bot_code`, por lo que el bot BBS comercial se representaba como un contacto humano con iniciales `B(`.
- **Solución**: Se actualizó el endpoint de listado de conversaciones en el backend. Ahora realiza una consulta para identificar los bots del sistema, forzando su inclusión constante en el listado y enriqueciéndolos con `es_bot = 1` y `bot_code = 'BHN'/'BBS'`. Esto permite que el frontend los reconozca y pinte con sus gradientes premium e íconos correspondientes (🛠️ para Soporte Técnico y 📈 para Seguro de Ley BBS).

### 10. Rediseño Premium de Cotizaciones en Chat (Glassmorphism & Botones Compactos)
- **Ubicación**:
  - [components.js](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/components.js) (CSS de rejilla y efectos)
  - [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php) (Generación de respuestas HTML)
  - [test_bbs_new_commands.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/scratch/test_bbs_new_commands.php) (Mock CLI de pruebas)
- **Cambios**:
  - Se implementó un diseño de tarjeta horizontal con efecto de vidrio esmerilado (**Glassmorphism**) para cada opción de cotización.
  - La tarjeta presenta un degradado azul premium (`linear-gradient` basado en `#0052d4`, `#4364f7`, `#6fb1fc`), un desenfoque de fondo (`backdrop-filter: blur(10px)`), borde sutil y una sombra suave.
  - Incorpora la inicial de la aseguradora en una insignia circular interna y resalta tipográficamente el precio y nombre comercial.
  - Las acciones (`Descargar PDF` y `Enviar por Correo`) se colocan fuera de la cápsula de precios en un panel de botones circulares más pequeños, mejorando la jerarquía visual para que el usuario no confunda los botones de descarga/envío con los valores de la cotización.
  - Se sincronizó el mock del test CLI para que la representation HTML coincida en todas las capas del sistema.

### 11. Mejoras de Usabilidad y Experiencia de Usuario (Ventana, PDF, Copiado y Botón de Envío)
- **Ubicación**:
  - [components.js](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/components.js) (Estructura de chat y estilos de UI)
  - [chat.php](file:///c:/wamp64/www/PLATAFORMA_INTEGRADA/backend/api/chat.php) (Headers HTTP de descarga)
- **Mejoras**:
  - **Ventana de Chat Redimensionable**: Se agregaron propiedades CSS de redimensionamiento nativo (`resize: both; overflow: hidden;`) y dimensiones iniciales más amplias (`850px` de ancho por `580px` de alto) con límites mínimos. Esto permite que el área de mensajes se expanda dinámicamente al estirar la ventana desde la esquina.
  - **Corrección de PDFs que cargaban como código**: Se forzó el envío de cabeceras HTTP `Content-Type: text/html; charset=utf-8` antes de imprimir el HTML de cotizaciones, marbetes y condiciones. Esto evita que el navegador renderice el código fuente plano y cargue las plantillas visuales diseñadas de forma interactiva.
  - **Copiado de texto selectivo facilitado**: Se configuró la propiedad `user-select: text !important` en las burbujas de chat y el contenedor principal de mensajes. El usuario ahora puede seleccionar palabras o frases individuales con el cursor de forma fácil.
  - **Efecto visual del botón de envío**: Se implementó una clase transicional `.sending` que remueve la rigidez del botón circular convirtiéndolo en un pill elástico durante el fetch asíncrono, permitiendo que la animación y el texto "Enviando..." se desplieguen sin cortes ni saltos de línea verticales.

---

> [!NOTE]
> Todos los cambios han sido validados exitosamente ejecutando la suite de pruebas del sistema y el autodiagnóstico de la plataforma, confirmando que las integraciones asíncronas y los cálculos matemáticos se mantienen 100% consistentes con las reglas de negocio y la legislación de seguros y auditoría.
