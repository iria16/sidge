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

El renderizado de PDF sigue ocurriendo en el servidor, no en el navegador.

## Detalle de módulos

Cada módulo tiene su propio controlador en `app/Controllers/`, su modelo en
`app/Models/` y sus vistas bajo `app/Views/`. El acceso se controla en la lista
blanca de `public/index.php` y el menú lateral se filtra con
`sigde_nav_sections()` en `app/Views/helpers/ui.php`.

### Autenticación (`auth/*`)
**Controlador:** `AuthController`  
**Modelos:** `LoginModel`, `PasswordRecoveryModel`, `AuditLogModel`  
**Acciones:** `login`, `authenticate`, `logout`, `cambiarPasswordProvisional`, `updatePassword`, `recoverAccess`, `requestRecovery`  
**Roles:** `publico` (login y recuperación), `admin`, `docente`, `coordinador`, `directivo`, `secretaria` (logout y cambio de clave)  
Flujo de acceso al sistema: login con bloqueo por intentos fallidos, cambio de clave provisional obligatoria, recuperación de acceso gestionada por el administrador y cierre de sesión.

### Paneles (`dashboard/*`)
**Controlador:** `DashboardController`  
**Vistas:** `Views/dashboard/{rol}/index.php`  
**Acciones:** `index`, `admin`, `docente`, `coordinador`, `directivo`, `secretaria`  
**Roles:** todos los autenticados  
Cada rol tiene su propio dashboard con indicadores relevantes. El controlador resuelve el destino después del login y comprueba que el rol coincida con el destino solicitado.

### Cuentas de usuario (`cuentaUsuario/*`)
**Controlador:** `UserAccountController`  
**Modelo:** `UserAccountModel`  
**Acciones:** `index`, `create`, `store`, `edit`, `update`, `toggleStatus`, `getDetalle`  
**Roles:** `admin`  
Gestión de cuentas de usuario del sistema: listado, alta desde un empleado, edición de usuario y rol, detalle y activación/desactivación. Todo por modales AJAX.

### Bitácora (`bitacora/*`)
**Controlador:** `AuditLogController`  
**Modelo:** `AuditLogModel`  
**Acciones:** `index`, `exportar`  
**Roles:** `admin`  
Registro de auditoría del sistema: listado de acciones (usuario, módulo, IP, fecha) y exportación. Solo lectura.

### Recuperación de acceso (`recuperacionAcceso/*`)
**Controlador:** `PasswordRecoveryController`  
**Modelo:** `PasswordRecoveryModel`  
**Acciones:** `index`, `getDetalle`, `updateEstado`, `resetPassword`  
**Roles:** `admin`  
Lado administrador de la recuperación de acceso: aprueba o rechaza solicitudes de restablecimiento, genera claves provisionales y permite restablecer claves sin solicitud previa.

### Notificaciones (`notificacion/*`)
**Controlador:** `NotificationController`  
**Modelo:** `NotificationModel`  
**Acciones:** `contador`, `listar`, `leer`, `leerTodas`  
**Roles:** `admin`, `secretaria`, `directivo`  
Endpoints AJAX para el contador de notificaciones no leídas, listado y marcado como leídas. Se consumen desde el dropdown del header.

### Catálogos (`catalogo/*`)
**Controlador:** `CatalogController`  
**Modelos:** `CatalogModel` + modelos por catálogo (`GradoModel`, `SeccionModel`, `TurnoModel`, `TipoDocumentoModel`, etc.)  
**Acciones:** `index`, `store`, `update`, `toggleStatus`, `getUbicacion`  
**Roles:** `admin`  
Gestión de catálogos maestros del sistema (grados, secciones, turnos, tipos de documento, grados académicos, tipos de asignación, ocupaciones, nacionalidades, cargos, parentescos, niveles de instrucción, títulos). Cada catálogo tiene su modelo y el controlador usa `tipo` para distinguirlos.

### Empleados (`empleado/*`)
**Controlador:** `EmployeeController`  
**Modelos:** `EmployeeModel`, `TituloModel`, `InstitucionModel`, `AuditLogModel`  
**Servicio:** `ExpedienteEmpleadoService`  
**Acciones:** `index`, `expediente`, `exportar`, `estadoInfo`, `cambiarEstado`, `cerrarReposo`, `anularReposo`, `create`, `show`, `edit`, `store`, `update`, `toggleStatus`, `getUbicacion`, `buscarPersona`, `registrarReposo`, `updateReposo`, `getReposos`, `guardarTitulo`, `guardarInstitucion`  
**Roles:** `directivo`, `coordinador`, `secretaria` (consulta/subconjunto); `directivo` (gestión completa)  
CRUD de personal empleado, expedientes laborales, reposos, títulos e instituciones. Coordinador y secretaria tienen acceso de consulta a un subconjunto; el directivo gestiona el módulo completo.

### Años escolares (`academicYear/*`)
**Controlador:** `AcademicYearController`  
**Modelo:** `AcademicYearModel`  
**Acciones:** `index`, `store`, `update`, `iniciar`, `cerrar`, `reabrir`  
**Roles:** `directivo`  
Gestión del ciclo lectivo: creación, edición, apertura y cierre de años escolares. Solo la dirección puede iniciar o cerrar un año.

### Estructura académica (`academicStructure/*`)
**Controlador:** `AcademicStructureController`  
**Modelo:** `AcademicStructureModel`  
**Acciones:** `index`, `store`, `update`, `impacto`  
**Roles:** `coordinador`, `directivo`  
Definición de la estructura académica (año + grado + sección + turno + cupo). Incluye un endpoint de impacto que informa cuántos alumnos se moverían al editar una estructura.

### Asignación docente (`teacherAssignment/*`)
**Controlador:** `TeacherAssignmentController`  
**Modelo:** `TeacherAssignmentModel`  
**Acciones:** `index`, `asignar`, `actualizar`, `retirar`, `getDocentesPorEstructura`  
**Roles:** `coordinador`, `directivo`  
Asignación y retiro de docentes titulares por sección académica. Consumo de docentes por estructura para el formulario de asignación.

### Retiros (`withdrawal/*`)
**Controlador:** `WithdrawalController`  
**Modelo:** `WithdrawalModel`  
**Acciones:** `index`, `ver`, `buscarEstudiante`, `store`, `evaluarRecaudos`, `updateEstado`, `updateStatus`, `delete`  
**Roles:** `directivo`, `secretaria`  
Proceso de retiro de estudiantes: la secretaria registra la solicitud y evalúa recaudos; el directivo resuelve (aprueba/rechaza) y puede eliminar registros.

### Reingresos (`reingreso/*`)
**Controlador:** `ReingresoController`  
**Modelo:** `ReingresoModel`  
**Acciones:** `index`, `buscarEstudiante`, `datos`, `guardar`  
**Roles:** `secretaria`  
Reincorporación de estudiantes retirados: listado de solicitudes, datos del estudiante y guardado de la solicitud de reingreso.

### Estudiantes y expedientes (`estudiantes/*`)
**Controlador:** `StudentsController`  
**Modelo:** `StudentsModel`  
**Servicio:** `ExpedienteEstudianteService`  
**Acciones:** `index`, `show`, `expediente`, `exportar`, `guardarRecaudos`  
**Roles:** `directivo`, `coordinador`, `secretaria`, `docente` (consulta); `secretaria` (guardar recaudos)  
Consulta de expedientes académicos: listado con filtros, expediente completo del estudiante, exportación y marcación de recaudos entregados (exclusivo de secretaria).

### Inscripción (`inscripciones/*`)
**Controlador:** `EnrollmentController`  
**Modelo:** `EnrollmentModel`  
**Acciones:** `index`, `store`, `evaluarRecaudos`, `ver`, `continuar`, `buscarPersona`, `guardarInstitucion`, `getUbicacion`  
**Roles:** `secretaria`  
Registro de nuevas inscripciones: búsqueda de estudiante por cédula, alta de matrícula, evaluación de recaudos y datos de institución de procedencia.

### Ratificación (`ratificacion/*`)
**Controlador:** `RatificationController`  
**Modelo:** `RatificationModel`  
**Acciones:** `index`, `ver`, `datosEstudiante`, `getUbicacion`, `buscarPersona`, `secciones`, `ratificar`, `updateEstudiante`  
**Roles:** `secretaria` (todas las acciones); `directivo` (solo `updateEstudiante`)  
Ratificación de matrícula para el año siguiente. Admin no tiene acceso a este módulo. La edición de recaudos en Estudiantes también es exclusiva de secretaria.

### Configuración institucional (`configuracionInstitucional/*`)
**Controlador:** `InstitutionalConfigController`  
**Modelo:** `InstitutionalConfigModel`  
**Acciones:** `index`, `guardar`, `subirImagen`, `quitarImagen`, `verPrivado`, `restablecerOficial`  
**Roles:** `admin`  
Datos oficiales del plantel (nombre, código de dependencia, dirección, directivo), logos públicos y privados (firma y sello del director). Acceso restringido estrictamente a administrador.

### Constancias (`constancias/*`)
**Controlador:** `CertificatesController`  
**Modelo:** `CertificatesModel`  
**Acciones:** `index`, `estudio`, `inscripcion`, `retiro`, `prosecucion`, `verPrueba`, `emitir`, `emitirLote`  
**Roles:** `secretaria`  
Emisión oficial de constancias: estudio, inscripción/ratificación, prosecución y retiro. Incluye vista previa de prueba y emisión por lote de certificados de educación primaria (6.º grado).

### Resultados académicos (`resultadoAcademico/*`)
**Controlador:** `AcademicResultController`  
**Modelo:** `AcademicResultModel`  
**Acciones:** `index`, `seccion`, `guardarPropuesta`  
**Roles:** `docente`  
Registro de literales finales (A-E) por estudiante al cierre del año. El estado de promoción se deriva automáticamente: A-D = promovido, E = no promovido, sin literal = pendiente.

### Certificados de calificaciones (`certificadoCalificaciones/*`)
**Controlador:** `GradeCertificateController`  
**Modelos:** `GradeCertificateModel`, `AcademicResultModel`  
**Acciones:** `generarIndividual`, `generarPorSeccion`  
**Roles:** `docente`  
Emisión de Certificados de Calificaciones (Art. 108 RGLOE) individuales o por sección. Solo la docente asignada a la sección puede emitir. En 6.º grado solo reciben certificado quienes no alcanzaron competencias (literal E); los promovidos reciben el Certificado de Educación Primaria por el módulo de Constancias.

### Docente (`docente/*`)
**Controlador:** `TeacherController`  
**Acciones:** `index`, `nomina`, `seccion`, `guardarEnlaceWhatsapp`  
**Roles:** `docente`  
Listados del docente (nómina de su sección), ver sección y enlace de WhatsApp de la sección.

### Estadísticas (`estadistica/*`)
**Controlador:** `StatisticsController`  
**Modelo:** `StatisticsModel`  
**Acciones:** `index`, `exportarHermanosExcel`, `exportarHermanosPdf`, `exportarMatriculaExcel`  
**Roles:** `directivo`, `coordinador`  
Estadísticas institucionales: control de representantes y hermanos, matrícula y capacidad, demografía, salud y talento humano. Exportación a Excel y PDF.

### Egresos (`egresos/*`)
**Roles:** —  
Módulo sin rutas declaradas en la lista blanca del front controller, aunque aparezca en la lista de acciones POST. No está operativo en la versión actual.

### Perfil (`perfil/*`)
**Controlador:** `ProfileController`  
**Modelo:** `ProfileModel`  
**Acciones:** `index`, `actualizarFoto`  
**Roles:** `admin`, `docente`, `coordinador`, `directivo`, `secretaria`  
Ficha de identidad del usuario autenticado: datos de cuenta, información laboral y canales de contacto en modo solo lectura, más actualización de foto de perfil.

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
