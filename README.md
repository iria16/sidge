# SIGDE — Sistema de Gestión de Datos Educativos

Aplicación web para la gestión de inscripciones, ratificaciones, expedientes de
estudiantes, retiros, egresos y configuración académica de una escuela.

## Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.4 con las extensiones `pdo_mysql` y `mbstring` |
| MySQL | 8.0 o superior |
| Composer | 2.x |
| Node.js | 18 o superior |

## Instalación

```bash
composer install     # dependencias de PHP (dompdf, para el reporte PDF)
npm install          # bootstrap, bootstrap-icons, sweetalert2 y sass
npm run build        # copia los recursos de terceros y compila los estilos
npm run verificar    # comprueba que todos los recursos del layout existan
```

### Por qué `npm run build` es un paso aparte

`npm install` deja las bibliotecas en `node_modules/`, pero el layout no las carga de
ahí: apunta a `public/assets/`. El paso `build` las copia a esa carpeta y compila
`resources/scss/app.scss` en `public/assets/css/app.css`.

Si se omite, la aplicación carga y responde bien pero **sin estilos y sin iconos**, que
es la forma más difícil de detectar de una vista. `npm run verificar` existe para eso.

## Base de datos

Las credenciales **no** están en el código: se leen del archivo `.env` de la raíz del
proyecto. `.env` está en `.gitignore` y no debe subirse nunca; `.env.example` documenta
las variables necesarias sin valores reales.

```bash
cp .env.example .env      # y rellena DB_USER, DB_PASS y las demás
```

```bash
mysql -u root -p sidge < database/schema_actual.sql
```

Usa un usuario propio de la aplicación, no `root`: con permisos solo sobre la base
`sidge` es imposible que una fuga borre el servidor de MySQL entero. Los comandos
para crearlo están en `DESPLIEGUE.md`.

`database/migrations/` guarda el esquema consolidado en un único archivo inicial
(`20261030_initial_schema.sql`), que es copia de `schema_actual.sql`. **No hay que
ejecutarlas para instalar de cero**: `schema_actual.sql` ya está consolidado y trae
todo (esquema, datos de prueba y disparadores).

`database/backups/` conserva respaldos históricos del disparador de transición de
matrícula.

Para verificar que el esquema instala bien, sobre una base temporal:

```bash
mysql -u root -p -e "CREATE DATABASE sigde_prueba"
mysql -u root -p sigde_prueba < database/schema_actual.sql
mysql -u root -p -e "DROP DATABASE sigde_prueba"
```

## Estructura

```
app/
  Config/         conexión a la base de datos
  Controllers/    un controlador por módulo
  Exceptions/     excepciones de dominio, con su código HTTP asociado
  Http/           utilidades de respuesta
  Interfaces/     contratos que implementan los modelos
  Models/         acceso a datos; aquí vive la lógica de negocio
  Support/        clases sin estado compartidas
  Traits/         reutilización entre controladores y modelos
  Views/          plantillas PHP
database/         esquema, migraciones y respaldos
docs/             documentos del proyecto
public/           raíz web: index.php y assets
resources/scss/   fuentes de estilos
scripts/          utilidades de mantenimiento (fuera de la raíz web)
```

El punto de entrada es `public/index.php`, que enruta por `/controlador/accion`. Con el
servidor embebido de PHP:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Ese servidor no sirve archivos estáticos por su cuenta y devuelve 400 en ellos; para
desarrollar conviene apuntar el navegador a las rutas PHP y dejar los assets a un
servidor web real (Apache o Nginx), que sí los sirve directamente.

## Módulos

| Módulo | Ruta |
|---|---|
| Años escolares | `academicYear/index` |
| Estructura académica | `academicStructure/index` |
| Asignación docente | `teacherAssignment/index` |
| Catálogos | `catalogo/index` |
| Empleados | `empleado/index` |
| Inscripción | `inscripciones/index` |
| Ratificación | `ratificacion/index` |
| Retiro | `withdrawal/index` |
| Egreso | `egresos/index` |
| Expediente del estudiante | `estudiantes/show/{id}` |
| Cuentas de usuario | `cuentaUsuario/index` |
| Bitácora | `bitacora/index` |

Las acciones disponibles de cada módulo están en la lista blanca de
`public/index.php`, que es también lo que decide qué rol entra a qué.

## Reporte PDF

Los PDF se maquetan con Dompdf, no con el navegador: no resuelven rutas relativas,
no descargan recursos remotos y su subconjunto de CSS es reducido. Por eso los
documentos no toman su estilo de `app.css` (el bundle de la interfaz web, con
Bootstrap) sino de hojas propias.

| Documento | Vista | Hoja de estilos |
|---|---|---|
| Constancia de estudio / inscripción | `app/Views/constancias/pdf/` | `public/assets/css/pdf/constancia.css` |
| Ficha integral del estudiante | `app/Views/consulta_academica/estudiantes/export-pdf.php` | `public/assets/css/pdf/ficha-estudiante.css` |

Las fuentes SCSS están en `resources/scss/pdf/` y se compilan aparte del bundle
web con `npm run sass:build:pdf`. `_base.scss` es el reinicio común a todos los
documentos y se incrusta en cada hoja al compilar. Para tocar el estilo de un
PDF: editar su SCSS y recompilar; `npm run build` hace las dos cosas.

`app/Support/PdfRecursos.php` carga esas hojas y arma las opciones de Dompdf
(`chroot`, `isRemoteEnabled` en false). Las imágenes llegan como Data URI, que es
la única forma de que entren sin depender de rutas del servidor.

La plantilla comparte con la pantalla el método de agrupación de familiares
(`RatificacionModel::getFamiliaresParaExpediente()`), de modo que el documento impreso y
la ficha digital no pueden apartarse.

## Problemas frecuentes

**Los botones no abren modales.** Falta Bootstrap o SweetAlert2 en `public/assets/`.
Revisar con `npm run verificar`.

**La pantalla aparece sin iconos.** Falta la fuente `woff2` de bootstrap-icons. Es el
fallo más difícil de ver porque el CSS carga igual. `npm run verificar` lo detecta.

**Faltan estilos.** No se compiló el SCSS: `npm run sass:build`.

**Un PDF sale sin márgenes ni con el contenido pegado al borde.** Almost siempre es
que se editó la hoja compilada a mano en vez del SCSS, o que se borró
`public/assets/css/pdf/`. Regenerar con `npm run sass:build:pdf`.

**Las fotos que sube la aplicación no aparecen.** `public/uploads/` está en
`.gitignore` a propósito: son datos personales y no deben entrar al repositorio.
