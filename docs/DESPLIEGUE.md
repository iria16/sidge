# Despliegue de SIGDE en Debian 13

Esta guía prepara una instalación nueva en un VPS Debian 13 con Apache y
`mod_php`. El dominio aún no está definido, por lo que TLS y el acceso público
deben configurarse y probarse cuando exista un dominio apuntando al VPS.

## Instalar los paquetes del servidor

Actualiza Debian e instala Apache, MariaDB y PHP con las extensiones requeridas:

```sh
sudo apt update
sudo apt install apache2 mariadb-server mariadb-client \
  libapache2-mod-php8.4 php8.4-mysql php8.4-mbstring php8.4-gd \
  php8.4-xml php8.4-zip
sudo a2enmod rewrite
sudo systemctl enable --now apache2 mariadb
```

Composer y Node.js se usan para preparar el ZIP, no son necesarios en el VPS si
el paquete ya incluye `vendor/` y los assets construidos.

## Requisitos

- PHP 8.4 o superior con `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `dom`, `iconv`,
  `zip` y `zlib`.
- MariaDB 10.10 o superior para `database/schema_actual.sql`. Para MySQL de
  InfinityFree usa `database/schema_infinityfree.sql`; la compatibilidad integral
  de la aplicación debe validarse en la versión que ofrezca el plan.
- Composer 2 y Node.js 18 durante la preparación del paquete.
- Apache con `mod_rewrite`, o Nginx con PHP-FPM.
- HTTPS activo antes de permitir el acceso de usuarios.

## Preparar el paquete ZIP

Desde una copia limpia del proyecto:

1. Instala dependencias PHP y construye los recursos:

   ```sh
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

2. No incluyas `.env`, `.git/`, `node_modules/`, volcados de base de datos,
   archivos de pruebas ni datos reales. Incluye `vendor/` y los assets generados
   dentro de `public/assets/`.
3. Revisa el contenido final del ZIP antes de entregarlo. Debe contener
   `public/index.php`, `vendor/autoload.php` y los assets que carga la aplicación.

## Preparar una base nueva

1. Crea una base `utf8mb4` y un usuario propio para la aplicación. No uses `root`
   desde PHP. Limita la cuenta de ejecución a la base de SIGDE; conserva una
   cuenta administrativa distinta para importar el esquema y aplicar migraciones.
2. Importa una sola vez el esquema correspondiente a tu proveedor, siempre en
   una base nueva y vacía:

   Para MariaDB:

   ```sh
   mysql -u ADMIN_DB -p sidge < database/schema_actual.sql
   ```

   Para InfinityFree, selecciona la base creada en el panel y usa phpMyAdmin
   para importar `database/schema_infinityfree.sql`. El nombre completo de la
   base y el usuario incluyen el prefijo que muestra InfinityFree; configura
   también en la app el hostname exacto indicado en el panel.

   El esquema de InfinityFree usa `utf8mb4_unicode_ci` en vez de la collation
   exclusiva de MariaDB y no crea triggers. Conserva la restricción única que
   impide más de un año escolar activo. Las validaciones del flujo de matrículas
   se ejecutan desde la aplicación; escrituras directas a la base de datos no
   tendrán las protecciones que antes imponía el trigger. Los esquemas actuales
   contienen 45 tablas, incluida `cargo`; `pais` fue retirada porque no se usa
   (la migración inicial tenía ambas, `cargo` y `pais`). Si ya importaste una versión
   anterior de InfinityFree que no tenía `cargo`, ejecuta
   `database/fix_infinityfree_missing_cargo.sql` en phpMyAdmin; es no destructivo.
   El esquema completo contiene `DROP TABLE`: no lo importes sobre una base con datos.
   Para cuentas ficticias de prueba, importa opcionalmente
   `database/seed_infinityfree_demo_users.sql` después del esquema y solo en una
   base de pruebas. Sus contraseñas provisionales se deben cambiar al primer
   acceso; no uses esas cuentas en producción.
3. Configura las variables `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` en `.env`
   fuera de `public/`, o en el gestor de variables del hosting. Nunca las pongas
   en el ZIP ni en un comando que quede guardado en el historial.
4. Desde una terminal interactiva en el servidor, crea la primera cuenta:

   ```sh
   php scripts/crear_admin_inicial.php
   ```

   El comando solo acepta ejecución interactiva por CLI y solo cuando no hay
   ninguna cuenta. Crea la persona y el administrador en una transacción, genera
   una clave temporal aleatoria y obliga a cambiarla en el primer acceso. Guarda
   la clave temporal en un gestor de contraseñas: se muestra una sola vez.
   Si el plan no ofrece terminal/CLI, este paso no se puede ejecutar desde el
   panel phpMyAdmin y habrá que preparar el administrador por un método seguro
   antes de habilitar el sitio.
5. Inicia sesión, cambia la clave temporal y configura los datos institucionales
   y el año escolar antes de habilitar inscripciones.

## Publicar los archivos

El directorio público del sitio debe ser **solo** `public/`. El resto del proyecto
(`app/`, `database/`, `docs/`, `scripts/`, `storage/`, `.env` y `vendor/`) debe
quedar fuera del DocumentRoot y no descargable por HTTP.

### Apache

```sh
apachectl -M | grep -E 'rewrite|php'
php -m
```

Confirma que aparezcan `rewrite_module`, `php_module`, `pdo_mysql`, `mbstring`,
`gd`, `fileinfo`, `dom`, `iconv`, `zip` y `zlib`. La extensión `php8.4-xml`
incluye DOM.

Crea un VirtualHost HTTP, sustituyendo el dominio y la ruta:

```apache
<VirtualHost *:80>
    ServerName sigde.example.org
    DocumentRoot /srv/sigde/current/public

    <Directory /srv/sigde/current/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sigde-error.log
    CustomLog ${APACHE_LOG_DIR}/sigde-access.log combined
</VirtualHost>
```

Activa el sitio, valida la configuración y recarga Apache:

```sh
sudo a2ensite sigde.conf
sudo apachectl configtest
sudo systemctl reload apache2
```

Cuando tengas un dominio, crea su registro DNS hacia el VPS y permite tráfico
entrante en los puertos 80 y 443. Luego instala Certbot, sustituye el dominio
de ejemplo y solicita el certificado:

```sh
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d sigde.example.org
sudo apachectl configtest
```

El VirtualHost final debe redirigir HTTP a HTTPS y conservar como `DocumentRoot`
`/srv/sigde/current/public`; no cambies la raíz al directorio del proyecto. Si
prefieres PHP-FPM, configura el handler FPM en vez de `mod_php`, no ambos a la vez.
Sin un dominio real y su DNS apuntando al VPS no se puede emitir ni validar el
certificado TLS; no habilites usuarios hasta completar ese paso.

### Nginx con PHP-FPM

Configura `root` en `<ruta-del-proyecto>/public`, con reglas equivalentes a:

```nginx
location ~* ^/uploads/.*\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$ {
    deny all;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

Reemplaza la ruta del socket por la que provee el servidor y valida la
configuración de Nginx antes de recargarla. No publiques el directorio raíz del
proyecto como `root`.

## Permisos y configuración de PHP

- El usuario de PHP necesita lectura del código y escritura solo en los directorios
  que la aplicación usa para cargar archivos: `storage/` y las carpetas necesarias
  bajo `public/uploads/`. No des permisos globales `777`.
- Incluye `storage/rate_limit/.gitkeep` en el paquete para conservar el directorio
  que usa el limitador de solicitudes. En el VPS, permite que PHP escriba ahí y
  en los directorios de archivos subidos.
- No incluyas archivos locales existentes de `public/uploads/` o `storage/` en el ZIP:
  pueden contener fotografías, firmas o sellos reales.
- Mantén `.env` legible únicamente por el usuario/grupo que ejecuta PHP.
- En producción configura `display_errors=Off` y `log_errors=On`; consulta los logs
  privados del servidor para investigar errores.
- Mantén `session.cookie_httponly=1`, `session.use_strict_mode=1` y HTTPS. No
  expongas PHP-FPM directamente a Internet.
- Si hay un proxy TLS, configura el proxy para establecer/eliminar
  `X-Forwarded-Proto` y asegúrate de que solo el proxy de confianza pueda llegar
  al servidor de aplicación.

## Antes de habilitar usuarios

- Confirma que el sitio redirija a HTTPS y que el certificado sea válido.
- Comprueba login, cierre de sesión, cambio de clave provisional, recuperación,
  permisos por rol, carga de imágenes y generación de PDF.
- Crea y prueba un respaldo de base de datos y de los archivos privados de
  `storage/`; documenta cómo restaurarlos.
- Verifica que el ZIP no contenga `.env`, credenciales, respaldos ni información
  personal.
- Aplica cambios futuros con las migraciones incrementales correspondientes y con
  un respaldo previo. `schema_actual.sql` es solo para una base nueva.

## Limitaciones que dependen del proveedor

Antes de declarar el sistema listo, registra el proveedor, dominio, servidor web,
versión/extensiones PHP, versión de base de datos, ruta de logs, política de
respaldos y mecanismo de actualización. Esta guía no puede confirmar esos puntos
sin acceso a la configuración del hosting.
