# 📖 GUÍA DE INSTALACIÓN RÁPIDA

## Paso 1️⃣: Preparar el Ambiente

1. **Abrir WAMP** (si no está abierto)
   - Click en el icono de WAMP en la bandeja del sistema
   - Esperar a que esté en verde

2. **Verificar que Apache y MySQL están corriendo**
   - Click derecho en WAMP → Icono debe estar verde

## Paso 2️⃣: Copiar Archivos

1. **Navegar a la carpeta de WAMP:**
   ```
   C:\wamp64\www\
   ```

2. **Crear carpeta si no existe:**
   ```
   C:\wamp64\www\PLATAFORMA_INTEGRADA\
   ```

3. **Copiar todos los archivos del proyecto** en esta carpeta

## Paso 3️⃣: Crear Base de Datos

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

## Paso 4️⃣: Verificar Instalación

1. **Acceder al sistema:**
   ```
   http://localhost/PLATAFORMA_INTEGRADA/frontend/
   ```

2. **Debe aparecer la página de login**

## Paso 5️⃣: Iniciar Sesión

**Credenciales de prueba:**
```
Usuario: admin
Contraseña: Demo@123
```

✅ ¡Listo! El sistema está instalado

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

**Fecha:** 22 de Febrero de 2026
**Versión:** 1.0.0
