# 📚 Catálogo de Errores Codificados y Guía de Resolución (KEDB — NOFTRAB v4.0)

Este catálogo codificado (**Known Error Database - KEDB**) establece la taxonomía oficial de errores del sistema **MÁS QUE FIANZAS** bajo las **Normas NOFTRAB v4.0** y la **Regla 4-VAF**. Permite clasificar, auditar y resolver de forma acelerada cualquier incidencia funcional, impositiva, de seguridad o de interfaz de usuario.

---

## 📋 Taxonomía General de Codificación

| Rango de Código | Categoría Dominio | Descripción |
| :--- | :--- | :--- |
| **`ERR-VAF-[001..099]`** | **Reglas de Negocio y Unicidad (VAF)** | Validaciones de Cédula, RNC, Chasis/VIN, Placas, Fianzas y Justificaciones. |
| **`ERR-UI-[100..199]`** | **Diseño, Interfaz y Frontend (UX/UI)** | Skins Obsidian Dark, modales `MQF.confirm`, responsive layouts y CSS. |
| **`ERR-SEC-[200..299]`** | **Seguridad, Permisos y Sesiones (CSO)** | Tokens JWT/Bearer, permisos granulares RBAC, OWASP y carga de archivos. |
| **`ERR-DOC-[300..399]`** | **Impresión, Impuestos y Documentación** | Régimen fiscal ISC 16% / ITBIS 0%, motores PDF y catálogo de manuales. |

---

## 🛑 Seccion 1: Reglas de Negocio y Unicidad (`ERR-VAF-*`)

### `ERR-VAF-001`: Intento de Documento Duplicado entre Clientes Distintos
- **Síntoma**: La API responde con mensaje de advertencia e impide guardar la cotización o expediente de cliente.
- **Causa Raíz**: El documento (Cédula/RNC/Pasaporte) ya se encuentra registrado a nombre de otro cliente en la tabla `clientes`, `cotizaciones` o `usuarios`.
- **Regla NOFTRAB**: *No se permite asignar el mismo documento de identidad a dos clientes o entidades distintas.*
- **Solución**: Verificar si el cliente ya existe en el padrón del sistema o corregir el número introducido.

### `ERR-VAF-002`: Error de Algoritmo Luhn Mod 10 en Cédula Dominicana
- **Síntoma**: Aparece un badge naranja de advertencia `#c2410c` y se desactiva el botón de guardado directo.
- **Causa Raíz**: La cédula introducida no satisface la suma ponderada del dígito verificador del algoritmo Luhn (11 dígitos).
- **Solución**: Corregir la cédula por una oficial emitida por la JCE.

### `ERR-VAF-003`: Error de Algoritmo Mod 11 DGII en RNC Comercial
- **Síntoma**: Rechazo automático en la creación de sociedades o personas jurídicas.
- **Causa Raíz**: El RNC de 9 dígitos no cumple con el algoritmo oficial de verificación de la DGII.
- **Solución**: Validar el RNC directamente en la consulta pública de la DGII antes de ingresarlo.

### `ERR-VAF-004`: Intento de Vehículo Duplicado (Chasis/VIN o Placa)
- **Síntoma**: Mensaje de error: *"El Chasis/VIN o Placa ya se encuentra registrado en la cotización/póliza X..."*.
- **Causa Raíz**: Se intenta procesar una fianza/seguro para un vehículo con el mismo Chasis o Placa registrado en otra póliza activa.
- **Solución**: Consultar el estado de la póliza previa o solicitar un ajuste excepcional en el **Centro Técnico**.

### `ERR-VAF-005`: Intento de Número de Fianza Duplicado
- **Síntoma**: Impedimento de guardado de emisión de fianza en `fianzas.php`.
- **Causa Raíz**: El correlativo o número de fianza ya existe en la tabla `fianzas`.
- **Solución**: Asignar un nuevo correlativo de fianza autorizado por la aseguradora.

### `ERR-VAF-006`: Justificación Técnica Insuficiente en Cambio VAF
- **Síntoma**: El botón "Enviar a Validación" permanece inactivo o el servidor retorna HTTP 400.
- **Causa Raíz**: El texto introducido en la justificación de cambio posee menos de 15 caracteres.
- **Solución**: Redactar una justificación formal detallada describiendo el motivo del ajuste.

---

## 🎨 Sección 2: Diseño e Interfaz Frontend (`ERR-UI-*`)

### `ERR-UI-101`: Desviación de Skin Obsidian Dark (Estilos Blancos Hardcodeados)
- **Síntoma**: Un cuadro modal o formulario muestra fondo blanco estridencial en lugar del tema oscuro de vidrio templado.
- **Causa Raíz**: Presencia de inline styles con `#ffffff`, `#f8fafc` o `#fff7ed` en lugar de las variables del tema (`var(--mqf-surface-1)`, `var(--mqf-surface-2)`, `var(--mqf-border)`).
- **Solución**: Reemplazar los colores hardcodeados por variables CSS de superficie y bordes con resplandor.

### `ERR-UI-102`: Uso de Diálogos o Confirmaciones Nativas del Navegador
- **Síntoma**: Aparece una ventana emergente gris nativa del navegador (`window.confirm()`).
- **Causa Raíz**: Invocación directa de funciones síncronas de JavaScript no estilizadas.
- **Solución**: Sustituir la llamada por `await MQF.confirm({ title, message, type: 'danger' })` provisto en `components.js`.

---

## 🔐 Sección 3: Seguridad, Permisos y Sesiones (`ERR-SEC-*`)

### `ERR-SEC-201`: Sesión Expirada o Token Inválido (HTTP 401)
- **Síntoma**: La llamada API retorna error de autenticación y redirige al login.
- **Causa Raíz**: El token Bearer/JWT almacenado en `localStorage` caducó por inactividad.
- **Solución**: Volver a iniciar sesión para generar un nuevo token.

### `ERR-SEC-202`: Permiso Granular Insuficiente (HTTP 403)
- **Síntoma**: *"Acceso denegado: Su perfil no cuenta con permisos para ejecutar esta acción"*.
- **Causa Raíz**: El perfil del usuario no posee activada la casilla `crear_datos`, `editar_datos` o `eliminar_datos` en `permisos_perfil` para el módulo objetivo.
- **Solución**: Solicitar al Administrador la concesión del permiso granular en el Administrador de Perfiles.

---

## 📄 Sección 4: Impresión, Impuestos y Documentación (`ERR-DOC-*`)

### `ERR-DOC-301`: Declaración Errónea de ITBIS en Cotizaciones de Seguros
- **Síntoma**: Un reporte o comprobante muestra ITBIS (18.00%) sobre primas de seguros.
- **Causa Raíz**: Confusión impositiva entre ventas gravadas generales e intermediación de seguros.
- **Regla Fiscal**: *Las primas de seguros están exentas de ITBIS (0%) según la Ley de Seguros 146-02 (SIS) y normativa DGII, aplacándose únicamente el Impuesto Selectivo al Consumo (ISC 16%).*
- **Solución**: Verificar que el cálculo utilice la exención de ITBIS y aplique la tasa del 16% ISC.

---
*Fin del Catálogo KEDB — MÁS QUE FIANZAS, S.R.L. | Versión NOFTRAB v4.0*
