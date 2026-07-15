# Guía de Configuración de Credenciales y APIs — MÁS QUE FIANZAS

Este documento detalla paso a paso el funcionamiento, configuración y obtención de credenciales de las APIs y servicios integrados en la Plataforma **MÁS QUE FIANZAS**.

Para cumplir con las normas de seguridad, todos los archivos que contienen contraseñas o datos comprometedores están excluidos en `.gitignore` para no ser subidos a GitHub. En su lugar, se proveen archivos plantilla con la extensión `.example` que deben ser copiados y rellenados en el entorno local o de producción.

---

## 1. Servicio de Correo SMTP (E-mail)

El sistema cuenta con un motor de envío de correos sin dependencias externas (`backend/Mailer.php`) diseñado bajo la norma **NOFTRAB**.

### Configuración
El archivo local utilizado es [backend/config/smtp.json](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config/smtp.json).
Se provee la plantilla: [backend/config/smtp.json.example](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config/smtp.json.example).

**Campos obligatorios:**
- `server`: Servidor de correo saliente SMTP (ej: `smtp.gmail.com`).
- `port`: Puerto SMTP (587 para TLS/STARTTLS o 465 para SSL).
- `username`: Cuenta de correo emisora (ej: `pastorandersonhenriquez@gmail.com`).
- `password`: Contraseña de la cuenta o **Contraseña de Aplicación** (ver sección abajo).
- `encryption`: Tipo de cifrado (`tls` o `ssl`).
- `timeout`: Tiempo límite en segundos para la conexión SMTP (ej: `15`).

### Cómo obtener una Contraseña de Aplicación de Gmail
Si utilizas una cuenta de Gmail (o Google Workspace) con autenticación en dos pasos activa, no puedes usar tu contraseña habitual. Debes generar una contraseña de aplicación:
1. Ve a la consola de [Mi Cuenta de Google](https://myaccount.google.com/).
2. Accede a la pestaña **Seguridad**.
3. En la sección *Iniciar sesión en Google*, busca **Contraseñas de aplicación** (o búscalo en el buscador superior).
4. Elige un nombre descriptivo (ej: `Plataforma Mas Que Fianzas`) y haz clic en **Crear**.
5. Copia el código de 16 caracteres generado y colócalo en el campo `password` de tu `smtp.json` (sin espacios).

### Configuración Alternativa (config.json)
El archivo [backend/config/config.json](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config/config.json) (basado en la plantilla [config.json.example](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config/config.json.example)) también puede contener una sección `smtp` que sirve como configuración general del sistema, además de datos de la empresa y placeholders para integraciones de mensajería (como WhatsApp Business API).

---

## 2. API de Google Drive (Copias de Seguridad en la Nube)

El script de respaldo automatizado [noftrab_backup_runner.php](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/noftrab_backup_runner.php) genera un archivo comprimido de la plataforma (`masque_fianzas_backup.zip`) y lo sube directamente a una carpeta compartida en Google Drive.

### Configuración
El archivo local utilizado es [backend/config/google_drive.json](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config/google_drive.json).
Se provee la plantilla: [backend/config/google_drive.json.example](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/config/google_drive.json.example).

**Constante interna del script:**
- La carpeta destino de Google Drive está definida en la constante `GOOGLE_DRIVE_FOLDER_ID` dentro de `noftrab_backup_runner.php`. Actualmente es: `1twjGFJZSYEdsWZDfxaNoHq7yc9bglr5A`.

### Cómo configurar OAuth 2.0 y obtener las credenciales:
Para interactuar con la API de Google Drive, el sistema requiere permisos de tipo "Offline" utilizando tres claves:
1. **Client ID**: ID de cliente provisto al crear una aplicación en Google Cloud.
2. **Client Secret**: Secreto del cliente provisto junto al Client ID.
3. **Refresh Token**: Token persistente de autorización que permite refrescar el token de acceso sin interactuar con el navegador.

#### Pasos para crearlos:
1. Accede a la [Google Cloud Console](https://console.cloud.google.com/).
2. Crea un proyecto (o selecciona uno existente).
3. Ve a **API y Servicios** > **Biblioteca** y busca **Google Drive API**. Haz clic en **Habilitar**.
4. Ve a **API y Servicios** > **Pantalla de consentimiento de OAuth** (OAuth Consent Screen). Configúrala en tipo *Externo* y agrega tu cuenta como usuario de prueba.
5. Ve a **API y Servicios** > **Credenciales**. Haz clic en **Crear credenciales** y selecciona **ID de cliente de OAuth**.
   - Tipo de aplicación: *Web Application* (Aplicación web).
   - En **URI de redireccionamiento autorizados**, añade la URL oficial de Google para pruebas OAuth: `https://developers.google.com/oauthplayground`.
6. Guarda y copia el **Client ID** y **Client Secret** en tu archivo `google_drive.json`.
7. Abre [Google OAuth Playground](https://developers.google.com/oauthplayground/):
   - Haz clic en el ícono de configuración (engranaje arriba a la derecha).
   - Marca la casilla **Use your own OAuth credentials** (Usar sus propias credenciales de OAuth) e ingresa tu **Client ID** y **Client Secret**.
   - En la lista de la izquierda (Paso 1), busca **Drive API v3** y selecciona el scope `https://www.googleapis.com/auth/drive` (o `https://www.googleapis.com/auth/drive.file`).
   - Haz clic en **Authorize APIs** e inicia sesión con tu cuenta de Google aprobando los accesos.
   - En el Paso 2, haz clic en **Exchange authorization code for tokens**.
   - Copia el **Refresh Token** que aparece en el panel y guárdalo en tu archivo `google_drive.json`.

---

## 3. Servicio de Google Cloud Vision (OCR para lectura de documentos)

La plataforma utiliza el servicio **Google Cloud Vision OCR** para analizar de forma automatizada las imágenes y PDFs de documentos de identidad, comprobantes y vehículos cargados por los usuarios en el backend de pagos.

### Configuración
El archivo de clave privada local se guarda en [backend/google-key.json](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/google-key.json).
Se provee la plantilla: [backend/google-key.json.example](file:///C:/wamp64/www/PLATAFORMA_INTEGRADA/backend/google-key.json.example).

El archivo `backend/config.php` detecta automáticamente este archivo de credenciales y define la variable de entorno necesaria:
```php
define('GOOGLE_VISION_KEY_PATH', dirname(__FILE__) . '/google-key.json');
if (file_exists(GOOGLE_VISION_KEY_PATH)) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . GOOGLE_VISION_KEY_PATH);
}
```

### Cómo obtener el archivo JSON de Service Account:
1. Ve a tu proyecto en la [Google Cloud Console](https://console.cloud.google.com/).
2. Asegúrate de habilitar la **Cloud Vision API** desde la Biblioteca de APIs.
3. Dirígete a **IAM y Administración** > **Cuentas de servicio** (Service Accounts).
4. Haz clic en **Crear cuenta de servicio** en la parte superior.
5. Asígnale un nombre (ej: `ocr-vision-plataforma`) y dale clic en **Crear y Continuar**.
6. En roles, asígnale el rol de **Usuario de Cloud Vision** o **Administrador de Cloud Vision** (para acceso completo a la API).
7. Haz clic en **Listo**.
8. Selecciona la cuenta de servicio recién creada en la tabla y ve a la pestaña **Claves** (Keys).
9. Haz clic en **Agregar clave** > **Crear clave nueva**. Selecciona el formato **JSON**.
10. Se descargará automáticamente un archivo `.json` a tu máquina.
11. Renombra este archivo como `google-key.json` y colócalo en el directorio `/backend/` de tu plataforma local.

---

## 4. Credenciales de la VPS (Contabo)

Para la administración directa del servidor de hosting VPS en Contabo (despliegue del código de producción y hosting del servidor Apache/WAMP o Linux de producción):

- **Archivo de referencia local**: `Credenciales para accesar a la VPS.txt` (Excluido de Git).
- **Acceso**: Se realiza vía cliente SSH (ej: PuTTY, terminal) mediante el puerto estándar `22`.
- **Dirección IP**: Configurada para el servidor correspondiente.
- **Usuario**: Generalmente `root`.
- **Llave / Password**: Configurada exclusivamente en el panel de control de Contabo y conocida por el administrador.

---

## Resumen de Ubicación de Archivos de Credenciales

| Servicio | Archivo Local Real | Archivo Plantilla (Tracked) | ¿Rastreado en Git? |
| :--- | :--- | :--- | :--- |
| **Google Vision OCR** | `backend/google-key.json` | `backend/google-key.json.example` | **No** (Ignorado) |
| **Google Drive Cloud** | `backend/config/google_drive.json` | `backend/config/google_drive.json.example` | **No** (Ignorado) |
| **Servidor SMTP (Gmail)** | `backend/config/smtp.json` | `backend/config/smtp.json.example` | **No** (Ignorado) |
| **Configuración General** | `backend/config/config.json` | `backend/config/config.json.example` | **No** (Ignorado) |
| **Acceso a VPS** | `Credenciales para accesar a la VPS.txt` | *(Ninguno)* | **No** (Ignorado) |
