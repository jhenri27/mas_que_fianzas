# 📖 GUÍA DE INSTALACIÓN RÁPIDA (v3.3.0 Stabilized)

## Paso 1️⃣: Preparar el Ambiente y Prerrequisitos

1. **Abrir WAMP** (si no está abierto)
   - Click en el icono de WAMP en la bandeja del sistema.
   - Asegurarse de que Apache, MySQL (puerto 3306) y PHP 8.2+ estén en ejecución (icono verde).

2. **Verificar Instancia de Python (para motor ETL)**
   - Abrir CMD o PowerShell.
   - Ejecutar: `python --version`
   - Debe retornar **Python 3.14.x** (o compatible v3.11+). Si no está disponible, instálalo y agrégalo al PATH del sistema.

3. **Copiar Archivos de la Plataforma**
   - Navegar a la carpeta de WAMP: `C:\wamp64\www\`
   - Copiar el directorio `PLATAFORMA_INTEGRADA` en esa ruta.
   - Ruta resultante: `C:\wamp64\www\PLATAFORMA_INTEGRADA\`

## Paso 2️⃣: Crear la Base de Datos

### Opción A: Usando phpMyAdmin (Recomendado)

1. **Abrir phpMyAdmin:**
   ```
   http://localhost/phpmyadmin
   ```

2. **Iniciar sesión:**
   - Usuario: `root`
   - Contraseña: (dejar en blanco)
   - Click en "Continuar"

3. **Importar base de datos:**
   - Click en la pestaña "Importar"
   - Click en "Seleccionar archivo"
   - Navegar a: `PLATAFORMA_INTEGRADA/database/schema_masque_fianzas.sql`
   - Click en "Ejecutar"
   - Esperar a que finalice

4. **Verificar que se creó la BD:**
   - En el panel izquierdo debe aparecer: `masque_fianzas_integrada`

### Opción B: Usando MySQL Command Line

1. **Abrir CMD o PowerShell**

2. **Conectar a MySQL:**
   ```bash
   mysql -u root -p
   ```
   (presionar Enter cuando pida contraseña si está vacía)

3. **Ejecutar el script:**
   ```sql
   SOURCE C:/wamp64/www/PLATAFORMA_INTEGRADA/database/schema_masque_fianzas.sql;
   ```

4. **Salir:**
   ```sql
   EXIT;
   ```

## Paso 3️⃣: Verificar e Inicializar con Verificador de Sistema

1. **Ejecutar Verificación Visual:**
   - Abre en tu navegador la URL:
     ```
     http://localhost/PLATAFORMA_INTEGRADA/verify.php
     ```
   - Este Dashboard Premium v3.3.0 comprobará la base de datos, las 16 columnas requeridas, el estado de los perfiles comerciales, y si Python 3.14.5 está en el PATH del servidor.

2. **Ejecutar Diagnóstico Técnico Completo:**
   - Abre la URL:
     ```
     http://localhost/PLATAFORMA_INTEGRADA/verify_system_end_to_end.php
     ```
   - Este script confirmará que el motor ETL de Python es capaz de procesar el archivo XLSX e importar a los socios comerciales de forma idempotente con cuentas bancarias normalizadas.

3. **Acceder a la Aplicación:**
   - URL: `http://localhost/PLATAFORMA_INTEGRADA/frontend/`

## Paso 4️⃣: Iniciar Sesión

**Credenciales Administrativas Predeterminadas:**
- **Usuario/Email:** `admin@masquefianzas.com`
- **Contraseña:** `Demo@123`

✅ ¡Listo! El sistema se encuentra 100% instalado, verificado y estabilizado comercialmente.

---

## 🔧 Configuración Avanzada

### Cambiar Contraseña de MySQL

Si configuraste una contraseña en MySQL:

1. **Abrir:** `PLATAFORMA_INTEGRADA/backend/config.php`

2. **Buscar la línea:**
   ```php
   define('DB_PASSWORD', '');
   ```

3. **Cambiar a tu contraseña:**
   ```php
   define('DB_PASSWORD', 'tu_contraseña_aqui');
   ```

4. **Guardar archivo**

### Cambiar Puerto

Si MySQL usa un puerto diferente de 3306:

1. **En config.php, cambiar:**
   ```php
   define('DB_PORT', 3307); // O tu puerto
   ```

### Habilitar HTTPS

Para producción, habilitar SSL en Apache

---

## ⚠️ Solución de Problemas

### "No se puede conectar a MySQL"

1. ✓ Verificar que MySQL esté corriendo (verde en WAMP)
2. ✓ Verificar contraseña en `config.php`
3. ✓ Verificar puerto (por defecto 3306)

### "Base de datos no encontrada"

1. ✓ El script SQL no se ejecutó
2. ✓ Hacer click en "Administración" en WAMP →Refreshear
3. ✓ Intentar importar nuevamente en phpMyAdmin

### "Error de permisos de archivo"

1. ✓ Dar permisos a carpetas:
   ```bash
   icacls "C:\wamp64\www\PLATAFORMA_INTEGRADA" /grant Everyone:F /R /T
   ```

### "CORS error"

1. ✓ El archivo `.htaccess` está en la carpeta correcta
2. ✓ Apache tiene `mod_rewrite` habilitado
3. ✓ Reiniciar Apache

---

## 🧪 Pruebas Recomendadas

1. **Login exitoso** con admin
2. **Ver sección de usuarios** (debe estar vacía)
3. **Crear un nuevo usuario**
4. **Cambiar contraseña** de admin
5. **Logout y login** con nuevo usuario

---

## 📊 Próximos Pasos

1. **Crear clientes** en el módulo de clientes
2. **Generar cotizaciones** en el cotizador
3. **Crear pólizas** basadas en cotizaciones
4. **Registrar pagos** de pólizas
5. **Ver reportes** de actividad

---

## 📞 Soporte

- Revisar logs en: `backend/logs/error.log`
- Consultar auditoría en: MySQL → tabla `auditoria_accesos`
- Documentación completa en: `README.md`

---

**Fecha:** 21 de Mayo de 2026
**Versión:** v3.3.0 Stabilized
