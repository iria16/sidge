# Despliegue en producción

Esta guía complementa el README principal y covers configuración de servidor,
seguridad y ajustes previos a poner el sistema en producción.

## Servidor web y HTTPS

### HTTP → HTTPS

La redirección de HTTP a HTTPS se configura en el servidor web (Apache o Nginx),
no en el código PHP. El sistema detecta automáticamente si la conexión es HTTPS
leyendo las variables del servidor:

```php
// public/index.php
$https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
```

Cuando la conexión es HTTPS, el sistema:
- Envía la cookie de sesión con los atributos `secure`, `httponly` y `samesite: Strict`
- Activa el header HSTS (`Strict-Transport-Security`)

### Configuración de cookies y sesión

Los parámetros de sesión ya incluyen las marcas de seguridad necesarias:

```php
session_set_cookie_params([
    'lifetime' => 0,      // se borra al cerrar el navegador
    'path' => '/',
    'domain' => '',
    'secure' => $https,   // solo por HTTPS
    'httponly' => true,   // JavaScript no puede leerla
    'samesite' => 'Strict' // frena CSRF desde otros sitios
]);
```

**Detrás de un proxy inverso** (Cloudflare, Nginx, etc.): el servidor proxy debe
enviar el header `X-Forwarded-Proto: https` para que PHP detecte correctamente
que la conexión original usa HTTPS.

### HSTS

El header `Strict-Transport-Security` se envía **solo cuando la conexión es HTTPS**:

```php
if ($https) {
    header('Strict-Transport-Security: max-age=31536000');
}
```

Esto indica al navegador que rechace HTTP durante un año tras la primera visita
por HTTPS.

## Rate limiting

El sistema incluye rate limiting básico por IP para endpoints sensibles.
Los contadores se guardan en `storage/rate_limit/`.

### Permisos de escritura

El usuario del servidor web debe poder escribir en esa carpeta:

```bash
# Ejemplo para Apache en Linux
sudo mkdir -p storage/rate_limit
sudo chown www-data:www-data storage/rate_limit
sudo chmod 755 storage/rate_limit
```

**Advertencia**: detrás de un proxy inverso, `REMOTE_ADDR` contiene la IP del
proxy, no la del cliente. Todos los usuarios compartirían el mismo contador de
límite de solicitudes. Para rate limiting por cliente real, configurar el
proxy para pasar el header `X-Forwarded-For` y usar ese valor en lugar de
`REMOTE_ADDR`.

## URL base

En el archivo `.env`, configurar `APP_URL` con la dirección completa incluyendo
`https://`:

```
APP_URL=https://ebisidroramirez.nfy.fyi/sigdev1
```

## Revisión de URLs hardcodeadas

Antes de pasar a producción, buscar en todo el código referencias fijas a
`http://` (enlaces, iframes,redirects) y reemplazarlas por `https://` o por la
variable de entorno `APP_URL`:

```bash
# Buscar en código PHP
grep -r "http://" app/ --include="*.php"

# Buscar en vistas
grep -r "http://" app/Views/ --include="*.php"

# Buscar en JavaScript
grep -r "http://" public/ --include="*.js"

# Revisar .env
grep "http" .env
```

## Permisos de carpetas

El usuario del servidor web debe tener permiso de escritura en:

- `storage/rate_limit/` — contadores de rate limiting
- `public/uploads/` — fotos de perfil, imágenes institucionales

No es necesario dar permisos a todo el proyecto. Las carpetas se crean
automáticamente si no existen.

## Assets y compilación

```bash
npm install
npm run build
```

Esto copia las dependencias de terceros a `public/assets/` y compila los SCSS.
El servidor web debe servir `public/` como raíz.

## Siguientes pasos

1. Configurar el virtual host del servidor web con HTTPS
2. Crear y configurar `.env` con las credenciales reales
3. Importar el esquema de base de datos
4. Crear el primer usuario administrador
5. Configurar los datos institucionales (nombre, logos, directivo)
6. Configurar el scheduler o cron para limpieza de caché de PDF