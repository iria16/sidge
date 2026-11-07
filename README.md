# SIGDE — Sistema de Gestión de Datos Educativos

Aplicación web para la gestión de inscripciones, ratificaciones, expedientes de
estudiantes, retiros, egresos y configuración académica de la **Escuela Básica «Isidro Ramírez»**,
desarrollada como proyecto para la **Universidad Politécnica Territorial de Valencia (UPTVAL)**.

## Índice

- [Datos del proyecto](#datos-del-proyecto)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Scripts npm](#scripts-npm)
- [Pruebas](#pruebas)
- [Base de datos](#base-de-datos)
- [Estructura](#estructura)
- [Módulos y roles](#módulos-y-roles)
- [Reporte PDF](#reporte-pdf)
- [Problemas frecuentes](#problemas-frecuentes)

## Datos del proyecto

- **Casa de Estudios:** Universidad Politécnica Territorial de Valencia (UPTVAL).
- **Carrera / PNF:** PNF en Informática.
- **Institución Beneficiaria:** Escuela Básica «Isidro Ramírez» (sede donde se atiende la problemática).
- **Tutor / Asesor:** Ing. Luis Oliveros.
- **Autores:**
  - Iriamar D. Maita B. — C.I. 25.863.136
  - Erickmar E. Sánchez P. — C.I. 30.431.260
  - Elyenay M. Ocanto G. — C.I. 16.771.283
- **Año:** 2026.
- **Estado:** Proyecto culminado y operativo; desplegado en línea para pruebas y validación académica, en preparación para despliegue en servidor definitivo (VPS con HTTPS).
- **Demostración en línea:** [Acceso al sistema](https://ebisidroramirez.nfy.fyi/sidgev1/public/)
- **Licencia:** Proyecto Académico — Uso institucional para la E.B. «Isidro Ramírez» y UPTVAL.

## Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.4 o superior con `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `dom`, `iconv`, `zip` y `zlib` |
| MariaDB | 10.10 o superior |
| Composer | 2.x |
| Node.js | 18 o superior |

El esquema usa la intercalación `utf8mb4_uca1400_ai_ci` de MariaDB; MySQL no está
validado como motor compatible.

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

## Scripts npm

Estos son los comandos declarados en `package.json`:

| Comando | Qué hace |
|---|---|
| `npm run build` | Copia las bibliotecas de terceros y compila los estilos de la aplicación y los PDF. |
| `npm run verificar` | Comprueba los assets, IDs de vistas, vistas PHP y permisos del menú. |
| `npm run test:inscripcion` | Ejecuta las pruebas de las reglas de inscripción y recaudos. |
| `npm run sass:build` | Compila el SCSS de la aplicación en `public/assets/css/app.css`. |
| `npm run sass:watch` | Observa los cambios del SCSS de la aplicación y recompila automáticamente. |
| `npm run sass:build:pdf` | Compila las hojas SCSS de los documentos PDF. |
| `npm run sass:watch:pdf` | Observa y recompila las hojas SCSS de constancias, ficha, nómina y reportes PDF. |
| `npm run copy:vendor-js` | Copia Bootstrap, SweetAlert2 y Chart.js a `public/assets/js/vendor/`. |
| `npm run copy:vendor-icons` | Copia la fuente y CSS de Bootstrap Icons a `public/assets/vendor/`. |
| `npm run verificar:ids` | Revisa la correspondencia entre IDs de JavaScript y vistas. |
| `npm run verificar:vistas` | Revisa la estructura de las vistas PHP. |
| `npm run verificar:menu` | Compara las rutas del menú con los permisos por rol. |

## Pruebas

```bash
npm run test:inscripcion
```

Ejecuta pruebas automatizadas de las reglas de recaudos sin modificar la base de
datos. `npm run verificar` ejecuta las comprobaciones estructurales descritas en
la tabla de scripts npm.

## Base de datos

Las credenciales **no** están en el código: se leen del archivo `.env` de la raíz del
proyecto. `.env` está en `.gitignore` y no debe subirse nunca; `.env.example` documenta
las variables necesarias sin valores reales.

```bash
cp .env.example .env      # y reemplaza los valores de ejemplo
```

El nombre de la base configurado en `.env.example` es `sidge`. Antes de importar
el esquema, crea esa base con la intercalación requerida y un usuario exclusivo para
la aplicación:

```sql
CREATE DATABASE sidge
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_uca1400_ai_ci;

CREATE USER 'sidge_app'@'localhost'
  IDENTIFIED BY 'REEMPLAZA_POR_UNA_CLAVE_SEGURA';

GRANT SELECT, INSERT, UPDATE, DELETE ON sidge.* TO 'sidge_app'@'localhost';
```

Reemplaza la clave de ejemplo antes de ejecutar el comando. El usuario de aplicación
solo recibe permisos sobre `sidge`; usa una cuenta administrativa distinta para crear
la base e importar el esquema. Configura después `DB_NAME=sidge`, `DB_USER=sidge_app`
y los valores reales de conexión en `.env`.

```bash
mariadb -u root -p sidge < database/schema_actual.sql
```

`database/schema_actual.sql` es el esquema completo para una instalación nueva. Incluye
los cambios de estructura que también aparecen como migraciones incrementales.
Los archivos SQL conservan los catálogos de referencia, pero omiten usuarios, personas,
matrículas y demás datos operativos. La instalación debe provisionar una cuenta inicial
y configurar la institución antes de habilitar el uso normal.
El procedimiento de instalación, publicación y alta del administrador inicial está en
[docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).
La raíz del servidor web debe apuntar a `public/`; la guía de despliegue explica la
configuración para Apache y Nginx.

`database/migrations/` contiene una línea base histórica y cambios incrementales:

| Archivo | Qué hace | ¿Destructiva? |
|---|---|---|
| `20261030_initial_schema.sql` | Línea base histórica anterior al esquema actual; no es una copia idéntica de `schema_actual.sql`. | Sí: contiene `DROP TABLE IF EXISTS`; solo debe usarse con una base vacía. |
| `20261101_enrollment_document_snapshots.sql` | Agrega campos, índices y restricciones para los recaudos de inscripción. | No elimina datos, pero puede fallar si los datos existentes incumplen las nuevas restricciones. |
| `20261102_remove_unused_pais.sql` | Elimina la tabla `pais`. | Sí: elimina la tabla. |
| `20261102_uniform_sizes_enum.sql` | Limita las tallas de uniforme a las opciones admitidas y conserva la cadena vacía para indicar que no se registró una talla opcional. | No elimina datos válidos; se detiene si encuentra tallas fuera del catálogo. |
| `20261106_add_reingreso_to_matricula_enum.sql` | Agrega `REINGRESO` a `matricula.tipo`, actualiza el disparador `trg_matricula_transicion` para permitir reincorporaciones de alumnos retirados y sincroniza registros previos. | No elimina datos; actualiza la columna, el disparador y estados de matrícula. |
| `20261106_seed_parentescos_profesionales.sql` | Estandariza los códigos canónicos (slugs) del catálogo `parentesco` e incorpora parentescos ampliados según LOPNNA y MPPE. | No elimina datos; actualiza códigos e inserta opciones adicionales. |

Para una instalación nueva, importa solo `schema_actual.sql`; no ejecutes después las
migraciones ya incluidas en ese esquema. Para actualizar una instalación existente,
respalda primero la base y aplica, una sola vez y en orden cronológico, únicamente las
migraciones que todavía no se hayan aplicado. La aplicación no lleva un registro
automático de migraciones, así que anota cuáles ejecutaste. Revisa la columna de
destructividad y respalda la base antes de cada actualización.

Para verificar que el esquema instala bien, sobre una base temporal:

```bash
mariadb -u root -p -e "CREATE DATABASE sidge_prueba CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci"
mariadb -u root -p sidge_prueba < database/schema_actual.sql
mariadb -u root -p -e "DROP DATABASE sidge_prueba"
```

La base temporal `sidge_prueba` es independiente de la base de instalación `sidge`.

## Estructura

```
app/
  Config/         conexión a la base de datos
  Controllers/    un controlador por módulo
  Exceptions/     excepciones de dominio, con su código HTTP asociado
  Http/           utilidades de respuesta
  Interfaces/     contratos que implementan los modelos
  Models/         consultas y operaciones sobre los datos
  Services/       procesos de aplicación que coordinan varios modelos
  Support/        herramientas reutilizables e independientes
  Traits/         comportamiento compartido que encaja en sus clases anfitrionas
  Views/          plantillas PHP
database/         esquema actual y migraciones incrementales
docs/             documentos del proyecto
public/           raíz web: index.php y assets (js/core, js/modules, css, img)
resources/scss/   fuentes de estilos
scripts/          utilidades de mantenimiento (fuera de la raíz web)
```

Como guía para mantener esas responsabilidades:

- Los **controladores** reciben la petición, comprueban permisos y datos de entrada y
  construyen la respuesta HTTP.
- Los **servicios** coordinan un caso de uso cuando participan varios modelos o pasos.
- Los **modelos** consultan y modifican la base de datos, y aplican las reglas que
  pertenecen a esas operaciones.
- Las clases de **Support** se invocan explícitamente como herramientas; no deben
  convertirse en controladores o servicios de negocio por acumular funcionalidades.
- Los **traits** incorporan métodos dentro de una clase con `use`; úsalos solo cuando
  ese comportamiento compartido tenga sentido como parte de cada clase anfitriona.

No es necesario mover código existente solo para ajustarlo a esta guía. Al modificar un
módulo, conserva sus interfaces y extrae responsabilidades de forma incremental.

El punto de entrada es `public/index.php`, que enruta por `/controlador/accion`. Con el
servidor embebido de PHP:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Ese servidor no sirve archivos estáticos por su cuenta y devuelve 400 en ellos; para
desarrollar conviene apuntar el navegador a las rutas PHP y dejar los assets a un
servidor web real (Apache o Nginx), que sí los sirve directamente.

## Módulos y roles

La matriz resume los roles admitidos por la lista blanca de `public/index.php`.
El acceso puede variar por acción dentro de un módulo.

| Módulo | Ruta | Admin | Directivo | Coordinador | Secretaria | Docente | Público |
|---|---|---:|---:|---:|---:|---:|---:|
| Autenticación | `auth/*` | Sí | Sí | Sí | Sí | Sí | Sí |
| Paneles | `dashboard/*` | Sí | Sí | Sí | Sí | Sí | — |
| Cuentas de usuario | `cuentaUsuario/*` | Sí | — | — | — | — | — |
| Bitácora | `bitacora/*` | Sí | — | — | — | — | — |
| Recuperación de acceso | `recuperacionAcceso/*` | Sí | — | — | — | — | — |
| Notificaciones | `notificacion/*` | Sí | Sí | — | Sí | — | — |
| Catálogos | `catalogo/*` | Sí | — | — | — | — | — |
| Empleados | `empleado/*` | — | Sí | Sí | Sí | — | — |
| Años escolares | `academicYear/*` | — | Sí | — | — | — | — |
| Estructura académica | `academicStructure/*` | — | Sí | Sí | — | — | — |
| Asignación docente | `teacherAssignment/*` | — | Sí | Sí | — | — | — |
| Retiros | `withdrawal/*` | — | Sí | — | Sí | — | — |
| Reingresos | `reingreso/*` | — | — | — | Sí | — | — |
| Estudiantes y expedientes | `estudiantes/*` | — | Sí | Sí | Sí | Sí | — |
| Inscripción | `inscripciones/*` | — | — | — | Sí | — | — |
| Ratificación | `ratificacion/*` | — | Sí* | — | Sí | — | — |
| Configuración institucional | `configuracionInstitucional/*` | Sí | — | — | — | — | — |
| Constancias | `constancias/*` | — | — | — | Sí | — | — |
| Resultados académicos | `resultadoAcademico/*` | — | — | — | — | Sí | — |
| Certificados de calificaciones | `certificadoCalificaciones/*` | — | — | — | — | Sí | — |
| Docente | `docente/*` | — | — | — | — | Sí | — |
| Estadísticas | `estadistica/*` | — | Sí | Sí | — | — | — |
| Egresos | `egresos/*` | — | — | — | — | — | — |
| Perfil | `perfil/*` | Sí | Sí | Sí | Sí | Sí | — |

`*` En Ratificación, Directivo solo tiene permiso para la acción
`updateEstudiante`; las demás acciones de ese módulo son exclusivas de Secretaria.
Admin no tiene acceso a este módulo. La edición de recaudos en Estudiantes también
es exclusiva de Secretaria. En Empleados, Coordinador y Secretaria tienen acceso
a un subconjunto de consulta; Directivo puede gestionar el módulo. Egresos no
tiene rutas declaradas en la lista blanca, aunque aparezca en la lista de acciones
POST.

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

## Caché de PDF generados

`app/Support/PdfCache.php` guarda en `storage/temp/pdf/` (fuera de `public/`)
los binarios de los PDF emitidos por lote (Certificados de Calificaciones por
sección y Certificados de Educación Primaria por lote). Cada PDF se genera una
sola vez y se reutiliza en la vista previa y la descarga durante 30 minutos;
después expira y se regenera. Esto evita que Dompdf procese el mismo lote varias
veces por cada clic del usuario, reduciendo el consumo de CPU y memoria en
hosting compartido.

`scripts/pdf-cache-clean.php` borra los archivos de caché con más de 1 hora. Ejecutarlo
manual o por cron para evitar acumulación en disco.

## Problemas frecuentes

**Los botones no abren modales.** Falta Bootstrap o SweetAlert2 en `public/assets/`.
Revisar con `npm run verificar`.

**La pantalla aparece sin iconos.** Falta la fuente `woff2` de bootstrap-icons. Es el
fallo más difícil de ver porque el CSS carga igual. `npm run verificar` lo detecta.

**Faltan estilos.** No se compiló el SCSS: `npm run sass:build`.

**Un PDF sale sin márgenes ni con el contenido pegado al borde.** Casi siempre es
que se editó la hoja compilada a mano en vez del SCSS, o que se borró
`public/assets/css/pdf/`. Regenerar con `npm run sass:build:pdf`.

**Las fotos que sube la aplicación no aparecen.** `public/uploads/` está en
`.gitignore` a propósito: son datos personales y no deben entrar al repositorio.
El código crea la carpeta raíz y sus subcarpetas cuando las necesita; el usuario
del servidor web debe tener permiso de escritura en `public/` para crearlas y
guardar archivos. La guía de [despliegue](docs/DESPLIEGUE.md) explica cómo asignar
permisos sin hacer escribible todo el proyecto.
