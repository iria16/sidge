# DESPLIEGUE — Lista de verificación

Antes de publicar SIGDE en un servidor, completa esta lista **en orden**. Cada punto
explica no solo qué hacer, sino por qué importa: casi todos son errores que en local
no se ven y en producción sí.

Estado actual: **ninguno de los pasos de servidor está aplicado.** Este documento es
la guía pendiente, no un registro de lo hecho.

---

## Fase 1 — Archivos de depuración ✅ hecho

Se eliminaron `public/test_empleado.php`, `public/test_load.php`,
`public/test_syntax.php`, `public/get_last_error.php` y `test_db.php`, y se movió
`verificar-assets.php` a `scripts/`.

Motivo: `test_empleado.php` fijaba `$_SESSION["rol"] = "admin"` sin contraseña, lo que
permitía tomar el control de la aplicación sin autenticarse.

---

## 0. `.htaccess` en la raíz — ✅ hecho (mitigación temporal)

Mientras el `DocumentRoot` apunte a `/var/www/html` (ver punto 1), la raíz del proyecto
sigue siendo alcanzable por HTTP. Se creó `.htaccess` en la raíz del proyecto para
bloquear lo que no debe servirse:

| Qué bloquea | Cómo |
|---|---|
| `.env` y variantes | `<FilesMatch "^\.env">` → 403 |
| `.sql`, `.dump`, `.sqlite` | `<FilesMatch>` → 403 |
| `.git/` | `RedirectMatch 404` |
| `.htpasswd`, `.bak`, `.swp`… | `<FilesMatch>` → 403 |

**Es una curita, no la solución.** Mientras el `DocumentRoot` no se corrija, siguen
sirviéndose los archivos del proyecto que **no** sean PHP: `composer.json`,
`package.json`, los `.scss` de `resources/`, los `.md`, los `.sql` (ya bloqueados
arriba), etc. El código fuente `.php` no se descarga como texto: mod_php lo **ejecuta**,
así que pedir `app/Config/Database.php` por HTTP devuelve la salida del script, no el
archivo. Aun así, quedan expuestos los archivos que no son PHP, y este archivo deja de
ser necesario en cuanto se aplique el punto 1.

---

## 1. Raíz web apuntando a `public/` — PENDIENTE

**Por qué importa:** mientras `DocumentRoot` sea `/var/www/html`, todo el proyecto está
servido por HTTP: el código, el esquema SQL, el historial de git y el `.env` con las
credenciales. No es un problema teórico, se comprueba con un `curl`.

Este servidor es compartido: bajo `/var/www/html` hay otros proyectos
(`Kacosa`, `login3`, `controlEstudios`, `practica`, `practica1`,
`analisis-de-abastecimiento-main`). Mover el `DocumentRoot` los dejaría sin servir, así
que lo correcto es un **VirtualHost dedicado** y no tocar el `000-default.conf`.

Crear `/etc/apache2/sites-available/sidgev1.conf`:

```apache
<VirtualHost *:80>
    ServerName sidge.local
    DocumentRoot /var/www/html/sidgev1/public

    <Directory /var/www/html/sidgev1/public>
        Options -Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/sidgev1-error.log
    CustomLog ${APACHE_LOG_DIR}/sidgev1-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite sidgev1
sudo apache2ctl configtest          # debe decir "Syntax OK"
sudo systemctl reload apache2
```

Para resolver el nombre en local, agregar a `/etc/hosts`:
```
127.0.0.1 sidge.local
```

Comprobar:
```bash
curl -I http://sidge.local/auth/login        # 200
```

**No hace falta tocar ninguna vista.** `BASE_URL` se calcula en runtime en
`public/index.php` desde `SCRIPT_NAME`, y los assets cuelgan de él
(`$assetsPath = BASE_URL . 'assets'`). Las URLs pasan de `/sidgev1/public/x` a `/x`.

---

## 2. `session.use_strict_mode = 1` — PENDIENTE

**Por qué importa:** sin esta opción, PHP acepta cualquier `PHPSESSID` que se le
inventé, lo que permite fijar la sesión de otro usuario. `public/index.php` ya lo
activa con `ini_set()`, pero eso **solo dura esa petición**: cualquier otro archivo PHP
que se cuele en `public/` vuelve a abrir el ataque.

El SAPI de este servidor es `apache2handler` (mod_php), y **`.user.ini` no se lee con
mod_php**: solo lo leen CGI/FastCGI. Por eso va en el `php.ini` de Apache
(`/etc/php/8.4/apache2/php.ini`, **no** el de `cli`, que es otro archivo distinto).

```bash
sudo nano /etc/php/8.4/apache2/php.ini    # línea ~1303: session.use_strict_mode = 0
# cambiar a:
session.use_strict_mode = 1
sudo systemctl reload apache2
```

Alternativa más limpia, para que no se pierda al actualizar PHP:
```bash
echo "session.use_strict_mode = 1" | sudo tee /etc/php/8.4/apache2/conf.d/99-sidge.ini
sudo systemctl reload apache2
```

Comprobar que quedó activo:
```bash
php -r 'echo ini_get("session.use_strict_mode"), PHP_EOL;' \
  -d error_reporting=0
# o desde el navegador: crear un archivo temporal que imprima
# ini_get("session.use_strict_mode") y pedirlo por HTTP
```

El `ini_set()` de `public/index.php` **se mantiene**: es una segunda barrera y no estorba.

---

## 3. Usuario propio de MySQL, sin `root` — PENDIENTE

**Por qué importa:** la aplicación hoy corre como `root`, que puede borrar el servidor
de MySQL entero. Una inyección o un error en el código solo debería poder tocar la base
`sidge`.

Elegir la contraseña uno mismo y definir aquí:

```bash
# Entrar como root (o como el usuario administrador del servidor)
mysql -u root -p

-- Crear el usuario. Cambia 'sige_app' por el nombre que prefieras.
CREATE USER 'sige_app'@'localhost' IDENTIFIED BY 'AQUI_TU_CLAVE';

-- Permisos: solo sobre la base sidge. Nada de DROP en el servidor, nada de otros usuarios.
GRANT SELECT, INSERT, UPDATE, DELETE ON sidge.* TO 'sige_app'@'localhost';
GRANT CREATE, ALTER, INDEX, REFERENCES ON sidge.* TO 'sige_app'@'localhost';

FLUSH PRIVILEGES;
```

Sobre esos permisos:

| Permiso | Para qué | ¿Hace falta en producción? |
|---|---|---|
| `SELECT, INSERT, UPDATE, DELETE` | operación normal de la app | Sí |
| `CREATE, ALTER, INDEX, REFERENCES` | crear tablas índices al migrar | Solo en desarrollo |
| `DROP` | borrar tablas | **Nunca**, ni en desarrollo |

Para producción conviene quitar los permisos de esquema y dejar solo los cuatro
primeros.

Luego actualizar `.env` con el usuario nuevo y **no volver a usar root**.

---

## 4. Rotar la contraseña — PENDIENTE (urgente)

**La clave anterior de MySQL sigue escrita en el historial de git y debe considerarse
comprometida.** Aparece en `app/Config/Database.php` en varios commits, y borrar la línea
del archivo **no la borra del historial**: sigue en `git log` y en cualquier clonación.

Cambiar la contraseña en MySQL:
```bash
ALTER USER 'root'@'localhost' IDENTIFIED BY 'NUEVA_CLAVE';
```

Y si alguna vez se publica el repositorio en un remoto, reescribir el historial
(`git filter-repo` o BFG) o, más simple, empezar un repositorio nuevo. Los secretos que
estuvo en un commit hay que tratarlos como perdidos.

---

## 5. No subir `.git/` ni el `.env` — PENDIENTE

`.env` ya está en `.gitignore` y eso funciona. Lo que hay que evitar es que el
repositorio **completo** quede servido por HTTP, porque `.git/` contiene todo el
código y todo el historial (donde está la clave vieja). Se resuelve con el punto 1.

Nunca hacer esto en el servidor de producción:
```bash
# MAL: sube las credenciales
git add -A
git commit -m "todo"

# Bien: desplegar el código, no el historial
rsync -av --exclude='.git' --exclude='.env' --exclude='node_modules' \
      /ruta/local/sidgev1/ usuario@servidor:/ruta/destino/
```

---

## 6. Pruebas con `curl` — PENDIENTE (hacerlas en el servidor final)

Son la prueba de que los puntos anteriores quedaron bien. Ejecutar **después** de
aplicar 1, 2 y 3.

```bash
# 6.1 La raíz web ya NO expone el código. Debe dar 404.
for p in /.git/HEAD /.env /app/Config/Database.php /database/schema_actual.sql \
         /composer.json /scripts/test_ver_privado.php; do
    printf "%-40s %s\n" "$p" "$(curl -s -o /dev/null -w '%{http_code}' http://localhost$p)"
done
#esperado: 404 en todos
```

```bash
# 6.2 Los archivos borrados de la Fase 1 no existen.
for p in /public/test_empleado.php /public/test_load.php \
         /public/test_syntax.php /public/get_last_error.php; do
    printf "%-40s %s\n" "$p" "$(curl -s -o /dev/null -w '%{http_code}' http://localhost$p)"
done
# esperado: 404
```

```bash
# 6.3 La sesión forjada ya no sirve. Debe redirigir (302/303), no dar 200.
curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Cookie: PHPSESSID=deadbeefdeadbeefdeadbeefdeadbeef" \
  http://localhost/auth/login
# esperado: 200 con el formulario de login, NUNCA la página de usuario
```

```bash
# 6.4 Una ruta que exige sesión devuelve 302 al login.
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" \
  http://localhost/cuentaUsuario/index
# esperado: 302 / 303 apuntando a auth/login
```

```bash
# 6.5 Una ruta de admin sin sesión NO debe devolver 200.
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/cuentaUsuario/index
```

```bash
# 6.6 La app sigue viva y con estilos (si da 200 pero sin CSS, falta `npm run build`).
curl -s -o /dev/null -w "login %{http_code}\n" http://localhost/auth/login
curl -s -o /dev/null -w "app.css %{http_code}\n" http://localhost/assets/css/app.css
```

---

## 7. Antes de publicar — otros puntos

- [ ] `npm run build` ejecutado, para que existan Bootstrap, SweetAlert2, el SCSS y las fuentes de iconos
- [ ] `npm run verificar` sale con "todos presentes"
- [ ] `display_errors` en `Off` (en desarrollo se puede tener en `On`)
- [ ] Copia de seguridad de `storage/` (contiene la firma y el sello del director)
- [ ] HTTPS configurado, para que la cookie de sesión viaje con la marca `Secure`

---

## Resumen de pendientes

| # | Punto | Estado |
|---|---|---|
| 0 | `.htaccess` en la raíz (mitigación temporal) | hecho |
| 1 | `DocumentRoot` en `public/` (vhost dedicado) | pendiente |
| 2 | `session.use_strict_mode = 1` en el php.ini de Apache | pendiente |
| 3 | Usuario propio de MySQL, sin `root` | pendiente |
| 4 | Rotar la contraseña (la vieja está en el historial) | pendiente |
| 5 | No publicar `.git/` ni `.env` | `.gitignore` listo; falta el punto 1 |
| 6 | Pruebas con `curl` | pendiente |