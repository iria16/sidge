# SIGDE — Sistema de Información para la Gestión de Datos Escolares

Aplicación web para la gestión de inscripciones, ratificaciones, reingresos, expedientes de
estudiantes, retiros, egresos y configuración académica de la **Escuela Básica «Isidro Ramírez»**, desarrollada como proyecto para la **Universidad Politécnica Territorial de Valencia (UPTVAL)**.

## Índice

- [Datos del proyecto](#datos-del-proyecto)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Scripts npm](#scripts-npm)
- [Pruebas](#pruebas)
- [Base de datos](#base-de-datos)
- [Estructura](#estructura)
- [Detalle de módulos](#detalle-de-módulos)
- [Matriz de roles](#matriz-de-roles)
- [Reporte PDF](#reporte-pdf)
- [Caché de PDF generados](#caché-de-pdf-generados)
- [Seguridad y despliegue](#seguridad-y-despliegue)
- [Problemas frecuentes](#problemas-frecuentes)

## Datos del proyecto

- **Casa de Estudios:** Universidad Politécnica Territorial de Valencia (UPTVAL).
- **Carrera / PNF:** PNF en Informática.
- **Institución Beneficiaria:** Escuela Básica «Isidro Ramírez» (sede donde se atiende la problemática).
- **Tutor / Asesor:** Ing. Luis Oliveros.
- **Autores:**
  - Iriamar D. Maita B.
  - Erickmar E. Sánchez P.
  - Elyenay M. Ocanto G.
- **Año:** 2026.
- **Estado:** Proyecto culminado y operativo; desplegado en línea para pruebas y validación académica, en preparación para despliegue en servidor definitivo (VPS con HTTPS).
- **Demostración en línea:** [Acceso al sistema](https://ebisidroramirez.nfy.fyi/sigdev1/public/)
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
| `npm run test:constancias` | Ejecuta las pruebas de plantillas PDF y de las reglas de constancias. |
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
npm run test:constancias
```

`test:inscripcion` ejecuta pruebas automatizadas de las reglas de recaudos sin
modificar la base de datos. `test:constancias` valida las plantillas PDF y las
reglas de negocio de constancias: prosecución solo con retiro aprobado, retiro
solo si es el último movimiento e inscripción sin duplicados. Las pruebas de
reglas usan una transacción y revierten los datos de prueba. `npm run verificar`
ejecuta las comprobaciones estructurales descritas en la tabla de scripts npm.

## Base de datos

Las credenciales **no** están en el código: se leen del archivo `.env` de la raíz del
proyecto. `.env` está en `.gitignore` y no debe subirse nunca; `.env.example` documenta
las variables necesarias sin valores reales.

```bash
cp .env.example .env      # y reemplaza los valores de ejemplo
```

El nombre de la base configurado en `.env.example` es `sigdev1`. Antes de importar
el esquema, crea esa base con la intercalación requerida y un usuario exclusivo para
la aplicación:

```sql
CREATE DATABASE sigdev1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_uca1400_ai_ci;

CREATE USER 'sigdev_app'@'localhost'
  IDENTIFIED BY 'REEMPLAZA_POR_UNA_CLAVE_SEGURA';

GRANT SELECT, INSERT, UPDATE, DELETE ON sigdev1.* TO 'sigdev_app'@'localhost';
```

Reemplaza la clave de ejemplo antes de ejecutar el comando. El usuario de aplicación
solo recibe permisos sobre `sigdev1`; usa una cuenta administrativa distinta para crear
la base e importar el esquema. Configura después `DB_NAME=sigdev1`, `DB_USER=sigdev_app`
y los valores reales de conexión en `.env`.

```bash
mariadb -u root -p sigdev1 < database/schema_actual.sql
```

`database/schema_actual.sql` es el esquema completo para una instalación nueva. Los
archivos SQL conservan los catálogos de referencia, pero omiten usuarios, personas,
matrículas y demás datos operativos. La instalación debe provisionar una cuenta inicial
y configurar la institución antes de habilitar el uso normal.
El procedimiento de instalación, publicación y alta del administrador inicial está en
[docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).
La raíz del servidor web debe apuntar a `public/`; la guía de despliegue explica la
configuración para Apache y Nginx.

Para verificar que el esquema instala bien, sobre una base temporal:

```bash
mariadb -u root -p -e "CREATE DATABASE sigdev1_prueba CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci"
mariadb -u root -p sigdev1_prueba < database/schema_actual.sql
mariadb -u root -p -e "DROP DATABASE sigdev1_prueba"
```

La base temporal `sigdev1_prueba` es independiente de la base de instalación `sigdev1`.

## Estructura

```
app/
  Config/         conexión a la base de datos
  Controllers/    un controlador por módulo
  Exceptions/     excepciones de dominio, con su código HTTP asociado
  Http/           utilidades para construir respuestas HTTP (JSON, códigos de estado, redirecciones)
  Interfaces/     contratos que implementan los modelos
  Models/         consultas y operaciones sobre los datos
  Services/       procesos de aplicación que coordinan varios modelos
  Support/        herramientas reutilizables e independientes
  Traits/         comportamiento compartido que encaja en sus clases anfitrionas
  Views/          plantillas PHP
database/         esquema actual
docs/             documentos del proyecto
public/           raíz web: index.php y assets (js/core, js/modules, css, img)
resources/scss/   fuentes de estilos
scripts/          utilidades de mantenimiento (fuera de la raíz web)
storage/          archivos generados en ejecución (caché de PDF, control de solicitudes); fuera de la raíz web
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

## Detalle de módulos

Cada módulo tiene su propio controlador en `app/Controllers/`, su modelo en
`app/Models/` y sus vistas bajo `app/Views/`. El acceso se controla en la lista
blanca de `public/index.php` y el menú lateral se filtra con
`sigde_nav_sections()` en `app/Views/helpers/ui.php`. Cada módulo se identifica con el
nombre que ve el usuario en la interfaz.

### Acceso y seguridad

#### Inicio de sesión
- **Nombre en la interfaz:** Inicio de sesión
- **Ruta:** `auth/*`
- **Controlador / Modelos:** `AuthController`; `LoginModel`, `PasswordRecoveryModel`, `AuditLogModel`
- **Roles con acceso:** `publico` para `login`, `authenticate`, `recoverAccess` y `requestRecovery`; `admin`, `docente`, `coordinador`, `directivo`, `secretaria` para `logout`, `cambiarPasswordProvisional` y `updatePassword`
- **Descripción:** Gestiona el ingreso al sistema, el cambio de clave provisional obligatoria y la recuperación de acceso. Es el primer módulo con el que interactúa cualquier usuario.
- **Funcionalidades:**
  - Iniciar sesión con cédula y contraseña.
  - Bloqueo de cuenta tras intentos fallidos repetidos.
  - Cambio obligatorio de clave provisional al primer acceso.
  - Solicitud de recuperación de acceso (atendida por el administrador).
  - Cierre de sesión.
- **Flujo principal:** el usuario accede a `/auth/login`, ingresa sus credenciales y, si tiene clave provisional, debe cambiarla antes de continuar al panel correspondiente.
- **Reglas y restricciones:** el front controller impide el acceso a rutas privadas sin sesión activa. Las rutas públicas redirigen al dashboard si ya hay sesión iniciada. Con clave provisional, el único acceso permitido es cambiarla o cerrar sesión. La solicitud de recuperación de acceso está limitada a 5 intentos cada 15 minutos por IP.
- **Acciones técnicas:** `login`, `authenticate`, `logout`, `cambiarPasswordProvisional`, `updatePassword`, `recoverAccess`, `requestRecovery`.

#### Dashboard
- **Nombre en la interfaz:** Dashboard (cada rol ve su propio panel)
- **Ruta:** `dashboard/*`
- **Controlador:** `DashboardController`
- **Roles con acceso:** `admin`, `directivo`, `coordinador`, `secretaria`, `docente`
- **Descripción:** Punto de entrada principal tras la autenticación. Cada rol visualiza un panel con los indicadores y accesos rápidos correspondientes a su responsabilidad.
- **Funcionalidades:**
  - Ver el panel de control asignado según el rol.
  - Acceder a las funciones principales del módulo propio.
- **Flujo principal:** tras iniciar sesión, el usuario llega a `dashboard/index`, que lo redirige al panel de su rol (`dashboard/admin`, `dashboard/directivo`, etc.).
- **Reglas y restricciones:** cada rol solo puede ver su propio panel; intentar acceder al panel de otro rol devuelve 403.
- **Acciones técnicas:** `index`, `admin`, `docente`, `coordinador`, `directivo`, `secretaria`.

#### Cuentas de usuario
- **Nombre en la interfaz:** Cuentas de usuario
- **Ruta:** `cuentaUsuario/*`
- **Controlador / Modelo:** `UserAccountController`; `UserAccountModel`
- **Roles con acceso:** `admin`
- **Descripción:** Gestiona las cuentas de acceso al sistema, permitiendo crear, editar y desactivar usuarios a partir de los empleados registrados.
- **Funcionalidades:**
  - Listar cuentas de usuario con filtros.
  - Crear una cuenta a partir de un empleado existente.
  - Editar usuario, rol y estado.
  - Ver detalle de una cuenta.
  - Activar o desactivar una cuenta.
- **Flujo principal:** el administrador abre el módulo, selecciona un empleado registrado, le asigna un rol y guarda la cuenta.
- **Reglas y restricciones:** solo el rol `admin` puede acceder a todas las acciones de este módulo.
- **Acciones técnicas:** `index`, `create`, `store`, `edit`, `update`, `toggleStatus`, `getDetalle`.

#### Bitácora
- **Nombre en la interfaz:** Bitácora
- **Ruta:** `bitacora/*`
- **Controlador / Modelo:** `AuditLogController`; `AuditLogModel`
- **Roles con acceso:** `admin`
- **Descripción:** Registra las acciones realizadas en el sistema para auditoría, permitiendo revisar quién hizo qué, cuándo y desde dónde.
- **Funcionalidades:**
  - Listar entradas de la bitácora con filtros de fecha, acción y texto libre.
  - Exportar el registro de auditoría.
- **Flujo principal:** el administrador consulta la bitácora, aplica filtros si es necesario y exporta el resultado para revisión externa.
- **Reglas y restricciones:** módulo de solo lectura; solo `admin` tiene acceso.
- **Acciones técnicas:** `index`, `exportar`.

#### Recuperación de acceso
- **Nombre en la interfaz:** Recuperación de acceso
- **Ruta:** `recuperacionAcceso/*`
- **Controlador / Modelo:** `PasswordRecoveryController`; `PasswordRecoveryModel`
- **Roles con acceso:** `admin`
- **Descripción:** Permite al administrador gestionar las solicitudes de restablecimiento de contraseña generadas por los usuarios y restablecer claves directamente cuando sea necesario.
- **Funcionalidades:**
  - Listar solicitudes de recuperación pendientes.
  - Ver detalle de una solicitud.
  - Aprobar o rechazar una solicitud (genera clave provisional).
  - Restablecer la contraseña de cualquier usuario sin solicitud previa.
- **Flujo principal:** un usuario solicita recuperación desde el inicio de sesión; el administrador revisa la solicitud en este módulo, la aprueba y el usuario recibe una clave provisional.
- **Reglas y restricciones:** acceso exclusivo de `admin`.
- **Acciones técnicas:** `index`, `getDetalle`, `updateEstado`, `resetPassword`.

#### Notificaciones
- **Nombre en la interfaz:** Notificaciones (menú desplegable del encabezado, no es una opción del menú lateral)
- **Ruta:** `notificacion/*`
- **Controlador / Modelo:** `NotificationController`; `NotificationModel`
- **Roles con acceso:** `admin`, `secretaria`, `directivo`
- **Descripción:** Informa al usuario sobre novedades del sistema mediante un contador y una lista de notificaciones recientes en el encabezado.
- **Funcionalidades:**
  - Consultar la cantidad de notificaciones no leídas.
  - Listar las últimas notificaciones.
  - Marcar notificaciones como leídas individualmente o todas a la vez.
- **Flujo principal:** el usuario abre el menú desplegable de notificaciones en el encabezado, ve el contador y las notificaciones pendientes, y las marca como leídas al consultarlas.
- **Reglas y restricciones:** accesible para `admin`, `secretaria` y `directivo`.
- **Acciones técnicas:** `contador`, `listar`, `leer`, `leerTodas`.

#### Mi perfil
- **Nombre en la interfaz:** Mi perfil
- **Ruta:** `perfil/*`
- **Controlador / Modelo:** `ProfileController`; `ProfileModel`
- **Roles con acceso:** `admin`, `docente`, `coordinador`, `directivo`, `secretaria`
- **Descripción:** Permite a cualquier usuario autenticado consultar sus propios datos de identidad, información laboral y canales de contacto, y actualizar su foto de perfil.
- **Funcionalidades:**
  - Ver la ficha de identidad del usuario.
  - Consultar datos de cuenta y canal de contacto.
  - Actualizar la foto de perfil.
- **Flujo principal:** el usuario accede a su perfil desde el menú de usuario en el encabezado y revisa o actualiza su información.
- **Reglas y restricciones:** cada usuario solo puede ver y editar su propio perfil. Los datos de cuenta, laborales y de contacto son de solo lectura; solo la foto es editable.
- **Acciones técnicas:** `index`, `actualizarFoto`.

### Administración del sistema

#### Catálogos
- **Nombre en la interfaz:** Catálogos
- **Ruta:** `catalogo/*`
- **Controlador / Modelos:** `CatalogController`; `CatalogModel` y modelos por catálogo (`GradoModel`, `SeccionModel`, `TurnoModel`, `TipoDocumentoModel`, `GradoAcademicoModel`, `TipoAsignacionModel`, `OcupacionModel`, `NacionalidadModel`, `CargoModel`, `ParentescoModel`, `NivelInstruccionModel`, `TituloModel`)
- **Roles con acceso:** `admin`
- **Descripción:** Administra los catálogos maestros del sistema que alimentan los formularios y listados (grados, secciones, turnos, tipos de documento, cargos, parentescos, etc.).
- **Funcionalidades:**
  - Listar registros de cada catálogo con filtros.
  - Crear, editar y activar/desactivar catálogos.
  - Consultar ubicaciones (estado, municipio, parroquia) para formularios.
- **Flujo principal:** el administrador selecciona un catálogo, edita sus valores o agrega uno nuevo, y el sistema lo pone a disposición de los formularios correspondientes.
- **Reglas y restricciones:** acceso exclusivo de `admin`. Las altas y ediciones de catálogo están limitadas a 30 por minuto por IP.
- **Acciones técnicas:** `index`, `store`, `update`, `toggleStatus`, `getUbicacion`.

#### Datos Institucionales
- **Nombre en la interfaz:** Datos Institucionales
- **Ruta:** `configuracionInstitucional/*`
- **Controlador / Modelo:** `InstitutionalConfigController`; `InstitutionalConfigModel`
- **Roles con acceso:** `admin`
- **Descripción:** Administra los datos oficiales del plantel, los logos y los elementos de firma y sello del director.
- **Funcionalidades:**
  - Ver y editar los datos institucionales (nombre, código de dependencia, dirección, directivo).
  - Subir y quitar imágenes públicas (logos) y privadas (firma y sello).
  - Previsualizar firma y sello de forma segura.
  - Restablecer los datos oficiales.
- **Flujo principal:** el administrador accede a Datos Institucionales, modifica los campos necesarios y guarda; las imágenes se procesan y se almacenan de forma segura.
- **Reglas y restricciones:** acceso restringido estrictamente a `admin` mediante una segunda barrera de seguridad en el controlador. La subida y el retiro de imágenes están limitados a 20 por minuto por IP.
- **Acciones técnicas:** `index`, `guardar`, `subirImagen`, `quitarImagen`, `verPrivado`, `restablecerOficial`.

#### Años Escolares
- **Nombre en la interfaz:** Años Escolares
- **Ruta:** `academicYear/*`
- **Controlador / Modelo:** `AcademicYearController`; `AcademicYearModel`
- **Roles con acceso:** `directivo`
- **Descripción:** Gestiona el ciclo lectivo de la institución, permitiendo iniciar, editar, cerrar y reabrir años escolares.
- **Funcionalidades:**
  - Listar años escolares con filtros.
  - Crear y editar un año escolar.
  - Iniciar y cerrar un año escolar.
  - Reabrir un año cerrado por error.
- **Flujo principal:** el directivo crea un año escolar, lo inicia cuando comienza el ciclo y lo cierra al finalizar; si se cierra por error, puede reabrirlo.
- **Reglas y restricciones:** acceso exclusivo de `directivo`. Reabrir un año queda registrado en la bitácora con el nombre de quien lo hizo y su motivo. El cierre del año ejecuta el proceso de egreso (ver más abajo).
- **Acciones técnicas:** `index`, `store`, `update`, `iniciar`, `cerrar`, `reabrir`.

##### Proceso de egreso

El egreso no es un módulo con pantalla propia. Ocurre dentro del cierre del año escolar
cuando el directivo ejecuta la acción `cerrar` desde Años Escolares.

El flujo es:
1. `AcademicYearController::cerrar()` llama a `AcademicYearModel::cerrar()`
2. `AcademicYearModel::cerrar()` invoca `egresarPromovidosSextoGrado()`
3. `egresarPromovidosSextoGrado()` obtiene los IDs de estudiantes de 6.º grado promovidos
4. Por cada estudiante, `EgresoModel::graduar()` registra el egreso

**Condiciones para el egreso:**
- El estudiante debe estar cursando 6.º grado (constante `GRADO_EGRESO = 6`)
- Debe tener un literal aprobatorio (A, B, C o D) cargado por la docente
- El resultado académico no debe estar marcado como `NO_PROMOVIDO`

**Qué sucede con los datos:**
- Se inserta una fila en `egreso` (`id_matricula`, `fecha_egreso`, `id_usuario`)
- Se crea una nueva fila de tipo `EGRESO` en `matricula` (la original de cursada se conserva)
- Se actualiza `resultado_academico.estado_resultado` a `PROMOVIDO`
- Se establece `inscripcion.motivo_egreso` a "Culminación exitosa" si estaba vacío

**Estudiantes no promovidos:** quienes reciben literal E (no promovido) no se egresan.
Permanecen en el sistema y pueden continuar en el siguiente año escolar o recibir un
Certificado de Calificaciones.

**Documento relacionado:** ver [Resultados y Promoción](#resultados-y-promoción) para el registro
de literales, y [Emisión de Constancias](#emisión-de-constancias) para el Certificado de Educación Primaria.

**Acciones técnicas:** `AcademicYearModel::cerrar()` → `egresarPromovidosSextoGrado()`;
`EgresoModel::graduar()`; `CertificatesController::emitir()` (tipo `egreso`);
`ConstanciaPdf::generarEgreso()`

### Gestión del personal

#### Empleados
- **Nombre en la interfaz:** Empleados
- **Ruta:** `empleado/*`
- **Controlador / Modelos / Servicio:** `EmployeeController`; `EmployeeModel`, `TituloModel`, `InstitucionModel`, `AuditLogModel`; `ExpedienteEmpleadoService`
- **Roles con acceso:** `directivo` (gestión completa); `coordinador` (consulta de un subconjunto de acciones)
- **Descripción:** Administra el personal empleado de la institución, sus expedientes laborales, reposos, títulos e instituciones de procedencia.
- **Funcionalidades:**
  - Listar empleados con filtros.
  - Ver el expediente completo de un empleado.
  - Crear, editar y activar/desactivar empleados.
  - Gestionar reposos (registrar, editar, cerrar, anular y consultar historial).
  - Gestionar títulos e instituciones de procedencia.
- **Flujo principal:** el directivo da de alta un empleado, registra sus datos personales y laborales, y gestiona sus reposos y títulos según sea necesario.
- **Reglas y restricciones:** `directivo` gestiona el módulo completo. `coordinador` solo consulta: listado, ficha, expediente, exportación, ubicación y reposos. El rol `secretaria` no tiene acceso a este módulo. Registrar reposos es una decisión de la dirección. Las altas de empleados están limitadas a 20 por minuto por IP.
- **Acciones técnicas:** `index`, `expediente`, `exportar`, `estadoInfo`, `cambiarEstado`, `cerrarReposo`, `anularReposo`, `create`, `show`, `edit`, `store`, `update`, `toggleStatus`, `getUbicacion`, `buscarPersona`, `registrarReposo`, `updateReposo`, `getReposos`, `guardarTitulo`, `guardarInstitucion`.

### Estructura académica

#### Estructura Académica
- **Nombre en la interfaz:** Estructura Académica
- **Ruta:** `academicStructure/*`
- **Controlador / Modelo:** `AcademicStructureController`; `AcademicStructureModel`
- **Roles con acceso:** `coordinador`, `directivo`
- **Descripción:** Define la estructura académica de la institución (año, grado, sección, turno y cupo), permitiendo organizar a los estudiantes en grupos.
- **Funcionalidades:**
  - Listar estructuras académicas con filtros.
  - Crear y editar combinaciones de año, grado, sección y turno.
  - Consultar el impacto de una edición (cuántos alumnos se verían afectados).
- **Flujo principal:** el coordinador o directivo define una estructura académica, asigna cupo y revisa el impacto antes de guardar cambios.
- **Reglas y restricciones:** `coordinador` y `directivo` tienen acceso completo. La consulta de impacto es de solo lectura y permite pedir confirmación con el número real de alumnos afectados.
- **Acciones técnicas:** `index`, `store`, `update`, `impacto`.

#### Asignación Docente
- **Nombre en la interfaz:** Asignación Docente
- **Ruta:** `teacherAssignment/*`
- **Controlador / Modelo:** `TeacherAssignmentController`; `TeacherAssignmentModel`
- **Roles con acceso:** `coordinador`, `directivo`
- **Descripción:** Asigna docentes titulares a las secciones académicas y permite retirar asignaciones vigentes.
- **Funcionalidades:**
  - Listar asignaciones docentes por estructura.
  - Asignar un docente a una sección.
  - Actualizar o retirar una asignación vigente.
  - Consultar docentes disponibles por estructura.
- **Flujo principal:** el coordinador o directivo selecciona una estructura académica, elige un docente de la lista disponible y asigna o retira la asignación.
- **Reglas y restricciones:** acceso para `coordinador` y `directivo`.
- **Acciones técnicas:** `index`, `asignar`, `actualizar`, `retirar`, `getDocentesPorEstructura`.

### Procesos estudiantiles

#### Estudiantes
- **Nombre en la interfaz:** Estudiantes
- **Ruta:** `estudiantes/*`
- **Controlador / Modelo / Servicio:** `StudentsController`; `StudentsModel`; `ExpedienteEstudianteService`
- **Roles con acceso:** `directivo`, `coordinador`, `secretaria`, `docente` (consulta); `secretaria` (guardar recaudos)
- **Descripción:** Consulta los expedientes académicos de los estudiantes, permitiendo ver su información completa, filtros y marcación de recaudos entregados.
- **Funcionalidades:**
  - Listar estudiantes con filtros (nombre, grado, sección, estado, turno).
  - Ver el expediente completo de un estudiante.
  - Exportar el listado de estudiantes.
  - Marcar recaudos como entregados.
- **Flujo principal:** el usuario busca un estudiante por cédula o filtros, abre su expediente para revisar la información y, si es secretaria, marca los recaudos entregados.
- **Reglas y restricciones:** la marcación de recaudos entregados es exclusiva de `secretaria`; los demás roles solo consultan. Además de la ruta, el controlador comprueba que el estudiante corresponda al turno de la secretaria. La edición de recaudos es un asiento sobre un requisito de matrícula, no una consulta.
- **Acciones técnicas:** `index`, `show`, `expediente`, `exportar`, `guardarRecaudos`.

#### Inscripción
- **Nombre en la interfaz:** Inscripción
- **Ruta:** `inscripciones/*`
- **Controlador / Modelo:** `EnrollmentController`; `EnrollmentModel`
- **Roles con acceso:** `secretaria`
- **Descripción:** Registra nuevas inscripciones de estudiantes, gestionando la búsqueda por cédula, la alta de matrícula y la evaluación de recaudos.
- **Funcionalidades:**
  - Buscar estudiantes por cédula para el alta.
  - Registrar una nueva inscripción.
  - Evaluar los recaudos de una inscripción.
  - Ver el detalle de una inscripción y continuar una inscripción pendiente.
  - Guardar la institución de procedencia.
- **Flujo principal:** la secretaria busca al estudiante por cédula, registra la inscripción con sus datos y evalúa si los recaudos están completos.
- **Reglas y restricciones:** acceso exclusivo de `secretaria`. El registro de inscripciones está limitado a 20 por minuto por IP.
- **Acciones técnicas:** `index`, `store`, `evaluarRecaudos`, `ver`, `continuar`, `buscarPersona`, `guardarInstitucion`, `getUbicacion`.

#### Ratificación
- **Nombre en la interfaz:** Ratificación
- **Ruta:** `ratificacion/*`
- **Controlador / Modelo:** `RatificationController`; `RatificationModel`
- **Roles con acceso:** `secretaria` (todas las acciones); `directivo` (solo `updateEstudiante`)
- **Descripción:** Gestiona la ratificación de matrícula para el año siguiente, permitiendo confirmar la continuidad de los estudiantes y editar sus datos desde el modal correspondiente.
- **Funcionalidades:**
  - Listar estudiantes para ratificación con filtros.
  - Ver el detalle de una ratificación.
  - Obtener datos del estudiante para el modal de edición.
  - Consultar las secciones del grado en el año destino.
  - Ratificar la matrícula de un estudiante.
  - Actualizar los datos del estudiante desde el modal.
- **Flujo principal:** la secretaria busca al estudiante, revisa sus datos, elige grado y sección del año destino y ratifica la matrícula; el directivo puede editar los datos del estudiante desde el modal cuando sea necesario.
- **Reglas y restricciones:** `admin` no tiene acceso a este módulo. Las acciones `index`, `ver`, `datosEstudiante`, `getUbicacion`, `buscarPersona`, `secciones` y `ratificar` son exclusivas de `secretaria`; `updateEstudiante` está disponible para `secretaria` y `directivo`.
- **Acciones técnicas:** `index`, `ver`, `datosEstudiante`, `getUbicacion`, `buscarPersona`, `secciones`, `ratificar`, `updateEstudiante`.

#### Retiro
- **Nombre en la interfaz:** Retiro
- **Ruta:** `withdrawal/*`
- **Controlador / Modelo:** `WithdrawalController`; `WithdrawalModel`
- **Roles con acceso:** `directivo`, `secretaria`
- **Descripción:** Gestiona las solicitudes de retiro de estudiantes, desde el registro por la secretaria hasta la resolución por la dirección.
- **Funcionalidades:**
  - Listar solicitudes de retiro.
  - Ver el detalle de una solicitud.
  - Buscar estudiantes para retiro.
  - Registrar una solicitud de retiro.
  - Evaluar los recaudos del retiro.
  - Actualizar el estado de una solicitud (aprobar/rechazar).
  - Eliminar una solicitud.
- **Flujo principal:** la secretaria registra la solicitud de retiro y evalúa los recaudos; el directivo revisa y aprueba o rechaza la solicitud.
- **Reglas y restricciones:** `buscarEstudiante`, `store` y `evaluarRecaudos` son exclusivas de `secretaria`; `updateEstado` y `updateStatus` son exclusivas de `directivo`; `index`, `ver` y `delete` están disponibles para ambos roles. El registro de solicitudes está limitado a 20 por minuto por IP.
- **Acciones técnicas:** `index`, `ver`, `buscarEstudiante`, `store`, `evaluarRecaudos`, `updateEstado`, `updateStatus`, `delete`.

#### Reingreso
- **Nombre en la interfaz:** Reingreso
- **Ruta:** `reingreso/*`
- **Controlador / Modelo:** `ReingresoController`; `ReingresoModel`
- **Roles con acceso:** `secretaria`
- **Descripción:** Gestiona la reincorporación de estudiantes retirados, permitiendo registrar y procesar solicitudes de reingreso.
- **Funcionalidades:**
  - Listar solicitudes de reingreso.
  - Buscar estudiantes para reingreso.
  - Ver los datos del estudiante.
  - Guardar la solicitud de reingreso.
- **Flujo principal:** la secretaria busca al estudiante retirado, revisa sus datos y guarda la solicitud de reingreso para su procesamiento.
- **Reglas y restricciones:** acceso exclusivo de `secretaria`. El guardado de solicitudes está limitado a 20 por minuto por IP. Al procesar un reingreso se reactiva la persona (`estado = 'ACTIVO'`) para que el estudiante vuelva a estar disponible en Constancia de Estudio. Los reingresos anteriores que quedaron con la persona inactiva se reparan con `php scripts/reparar-reingresos-inactivos.php`.
- **Acciones técnicas:** `index`, `buscarEstudiante`, `datos`, `guardar`.

### Docentes

#### Docente
- **Nombre en la interfaz:** Docente (listados, nómina, sección)
- **Ruta:** `docente/*`
- **Controlador:** `TeacherController`
- **Roles con acceso:** `docente`
- **Descripción:** Proporciona al docente los listados de su sección y la nómina para comunicación con los representantes.
- **Funcionalidades:**
  - Ver el listado de estudiantes de su sección.
  - Consultar la nómina de la sección.
  - Ver los detalles de la sección asignada.
- **Flujo principal:** el docente accede a su sección y revisa el listado de estudiantes y su nómina.
- **Reglas y restricciones:** acceso exclusivo de `docente`; la sección se toma de la sesión, por lo que cada docente solo ve su propia sección.
- **Acciones técnicas:** `index`, `nomina`, `seccion`.

#### Resultados y Promoción
- **Nombre en la interfaz:** Resultados y Promoción
- **Ruta:** `resultadoAcademico/*`
- **Controlador / Modelo:** `AcademicResultController`; `AcademicResultModel`
- **Roles con acceso:** `docente`
- **Descripción:** Permite al docente registrar los literales finales (A-E) de sus estudiantes al cierre del año, derivando automáticamente el estado de promoción.
- **Funcionalidades:**
  - Listar secciones con estudiantes para registrar resultados.
  - Ver los estudiantes de una sección.
  - Guardar la propuesta de literales y resultados.
- **Flujo principal:** el docente selecciona su sección, registra el literal de cada estudiante y guarda la propuesta; el sistema determina automáticamente si está promovido o no.
- **Reglas y restricciones:** acceso exclusivo de `docente`; el estado de promoción se deriva automáticamente del literal (A-D promovido, E no promovido, sin literal pendiente). Los literales de 6.º grado determinan quiénes egresan al cerrar el año escolar (ver [Proceso de egreso](#proceso-de-egreso)).
- **Acciones técnicas:** `index`, `seccion`, `guardarPropuesta`.

#### Certificados de Calificaciones
- **Nombre en la interfaz:** Certificados de Calificaciones
- **Ruta:** `certificadoCalificaciones/*`
- **Controlador / Modelos:** `GradeCertificateController`; `GradeCertificateModel`, `AcademicResultModel`
- **Roles con acceso:** `docente`
- **Descripción:** Emite Certificados de Calificaciones individuales o por sección, siguiendo el formato oficial (Art. 108 RGLOE), para estudiantes que no alcanzaron competencias en 6.º grado o para cualquier sección cuando corresponda.
- **Funcionalidades:**
  - Generar un certificado de calificaciones individual.
  - Generar certificados de calificaciones por sección completa.
- **Flujo principal:** el docente selecciona una sección, genera los certificados de los estudiantes correspondientes y los descarga o imprime.
- **Reglas y restricciones:** solo la docente asignada a la sección puede emitir certificados. En 6.º grado, solo reciben Certificado de Calificaciones los estudiantes con literal E (no promovidos); los promovidos reciben el Certificado de Educación Primaria por el módulo Emisión de Constancias.
- **Acciones técnicas:** `generarIndividual`, `generarPorSeccion`.

### Documentos y reportes

#### Emisión de Constancias
- **Nombre en la interfaz:** Emisión de Constancias
- **Ruta:** `constancias/*`
- **Controlador / Modelo:** `CertificatesController`; `CertificatesModel`
- **Roles con acceso:** `secretaria`
- **Descripción:** Emite constancias oficiales de estudio, inscripción/ratificación, retiro y prosecución, incluidas las de educación primaria para estudiantes de 6.º grado promovidos.
- **Funcionalidades:**
  - Emitir constancia de estudio.
  - Emitir constancia de inscripción.
  - Emitir constancia de retiro.
  - Emitir constancia de prosecución.
  - Ver prueba de constancia antes de emitir.
  - Emitir constancias por lote.
- **Flujo principal:** la secretaria selecciona el tipo de constancia, filtra por estudiante o sección, genera una prueba de vista y emite el documento final individualmente o por lote.
- **Reglas y restricciones:** acceso exclusivo de `secretaria`. La prueba de vista (`verPrueba`) no registra emisión; la emisión final (`emitir`/`emitirLote`) se envía por POST con CSRF y queda en bitácora. Los PDF emitidos por lote se guardan en caché durante 30 minutos (ver [Caché de PDF generados](#caché-de-pdf-generados)).
  - **Prosecución:** solo para estudiantes con `estado_resultado = 'PROMOVIDO'` confirmado, de 1.º a 5.º grado, que además tengan un retiro con estado `APROBADO`. Un literal A–D en estado `PENDIENTE` no habilita la emisión.
  - **Retiro:** solo retiros con estado `APROBADO` que sean el último movimiento del estudiante. Si existe una matrícula posterior (por ejemplo, un `REINGRESO`), el retiro ya no se lista.
  - **Inscripción / Ratificación:** incluye `INSCRIPCION`, `RATIFICACION` y `REINGRESO`; se muestra una sola fila por estudiante (su matrícula más reciente del año activo) y el reingreso se identifica como “Reingreso”.
- **Acciones técnicas:** `index`, `estudio`, `inscripcion`, `retiro`, `prosecucion`, `verPrueba`, `emitir`, `emitirLote`.

#### Estadísticas
- **Nombre en la interfaz:** Estadísticas
- **Ruta:** `estadistica/*`
- **Controlador / Modelo:** `StatisticsController`; `StatisticsModel`
- **Roles con acceso:** `directivo`, `coordinador`
- **Descripción:** Proporciona estadísticas institucionales sobre matrícula, capacidad, hermanos, demografía, salud y talento humano, con exportación a Excel y PDF.
- **Funcionalidades:**
  - Ver el panel de estadísticas institucionales.
  - Exportar datos de hermanos a Excel.
  - Exportar datos de hermanos a PDF.
  - Exportar datos de matrícula a Excel.
- **Flujo principal:** el directivo o coordinador abre el panel de estadísticas, revisa los indicadores y exporta los reportes necesarios para la dirección.
- **Reglas y restricciones:** acceso para `directivo` y `coordinador`.
- **Acciones técnicas:** `index`, `exportarHermanosExcel`, `exportarHermanosPdf`, `exportarMatriculaExcel`.

## Matriz de roles

La tabla resume los módulos con el nombre que ve el usuario en la interfaz y los roles con acceso a cada uno, según la lista blanca de `public/index.php`. El acceso puede variar por acción dentro de un módulo.

| Módulo (nombre en la interfaz) | Ruta | Admin | Directivo | Coordinador | Secretaria | Docente | Público |
|---|---|---:|---:|---:|---:|---:|---:|
| Inicio de sesión | `auth/*` | Sí | Sí | Sí | Sí | Sí | Sí |
| Dashboard | `dashboard/*` | Sí | Sí | Sí | Sí | Sí | — |
| Cuentas de usuario | `cuentaUsuario/*` | Sí | — | — | — | — | — |
| Bitácora | `bitacora/*` | Sí | — | — | — | — | — |
| Recuperación de acceso | `recuperacionAcceso/*` | Sí | — | — | — | — | — |
| Notificaciones | `notificacion/*` | Sí | Sí | — | Sí | — | — |
| Mi perfil | `perfil/*` | Sí | Sí | Sí | Sí | Sí | — |
| Catálogos | `catalogo/*` | Sí | — | — | — | — | — |
| Datos Institucionales | `configuracionInstitucional/*` | Sí | — | — | — | — | — |
| Años Escolares | `academicYear/*` | — | Sí | — | — | — | — |
| Empleados | `empleado/*` | — | Sí | Sí | — | — | — |
| Estructura Académica | `academicStructure/*` | — | Sí | Sí | — | — | — |
| Asignación Docente | `teacherAssignment/*` | — | Sí | Sí | — | — | — |
| Estudiantes | `estudiantes/*` | — | Sí | Sí | Sí | Sí | — |
| Inscripción | `inscripciones/*` | — | — | — | Sí | — | — |
| Ratificación | `ratificacion/*` | — | Sí* | — | Sí | — | — |
| Retiro | `withdrawal/*` | — | Sí | — | Sí | — | — |
| Reingreso | `reingreso/*` | — | — | — | Sí | — | — |
| Docente | `docente/*` | — | — | — | — | Sí | — |
| Resultados y Promoción | `resultadoAcademico/*` | — | — | — | — | Sí | — |
| Certificados de Calificaciones | `certificadoCalificaciones/*` | — | — | — | — | Sí | — |
| Emisión de Constancias | `constancias/*` | — | — | — | Sí | — | — |
| Estadísticas | `estadistica/*` | — | Sí | Sí | — | — | — |

`*` En Ratificación, Directivo solo tiene permiso para la acción `updateEstudiante`; las demás acciones de ese módulo son exclusivas de Secretaria.

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
después expira y se regenera. La clave incluye el turno, los filtros y los
estudiantes del lote para no mezclar documentos entre turnos o búsquedas. Esto
evita que Dompdf procese el mismo lote varias veces por cada clic del usuario.

`scripts/pdf-cache-clean.php` borra los archivos de caché con más de 1 hora. Ejecutarlo
manual o por cron para evitar acumulación en disco.

El renderizado de PDF sigue ocurriendo en el servidor, no en el navegador.

## Seguridad y despliegue

`public/index.php` es el único punto de entrada y aplica estas protecciones antes de
ejecutar cualquier controlador:

- **Sesión:** la cookie lleva `httponly` y `samesite=Strict`, y `secure` cuando la conexión
  es HTTPS (también detrás de un proxy que envíe `X-Forwarded-Proto`). El modo estricto
  de PHP rechaza identificadores de sesión que el servidor no creó, y la sesión se cierra
  tras 1 hora de inactividad.
- **Cabeceras:** `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: same-origin` y, solo bajo HTTPS, `Strict-Transport-Security`
  (HSTS, un año).
- **Permisos por ruta:** lista blanca de controlador, acción y roles; lo que no está
  declarado responde 404.
- **Métodos:** las acciones que crean, modifican, cierran o eliminan datos solo aceptan
  POST (405 en otro caso).
- **CSRF:** todo POST en rutas privadas debe traer el token de la sesión, en el campo
  `csrf` o en la cabecera `X-CSRF-Token`.
- **Límite de solicitudes por IP:** protege los endpoints sensibles (recuperación de
  acceso: 5 intentos cada 15 minutos; altas y cargas de imágenes: entre 20 y 30 por
  minuto). El contador se guarda en archivos dentro de `storage/rate_limit/`, sin usar
  la base de datos. Al superar el límite el sistema responde 429.

### Pasos para desplegar bajo HTTPS

1. **Redirección de HTTP a HTTPS:** se configura en Apache o Nginx, no en PHP.
2. **Proxy inverso:** si el servidor está detrás de un proxy, debe enviar
   `X-Forwarded-Proto: https` para que la cookie de sesión se marque como `secure`. En
   ese caso `REMOTE_ADDR` es la IP del proxy y todos los usuarios compartirían el mismo
   contador del límite de solicitudes; hay que tenerlo en cuenta.
3. **HSTS:** el sistema lo envía automáticamente en cuanto detecta HTTPS. Confirma que
   el certificado funciona antes de activar el dominio definitivo, porque los navegadores
   recuerdan la regla durante un año.
4. **Carpeta `storage/rate_limit/`:** el usuario del servidor web debe poder escribir en
   ella. Si no puede, el límite de solicitudes deja de funcionar y el fallo se registra
   en el log del servidor. Los archivos de control no se eliminan solos; conviene
   limpiarlos periódicamente.
5. **Referencias fijas a `http://`:** busca en el código, las vistas, el JavaScript y
   `.env` cualquier URL escrita con `http://`. Si queda alguna, el navegador bloqueará
   ese recurso como contenido mixto.

El procedimiento completo está en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

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

**Un formulario responde «Demasiadas solicitudes» (429).** Se superó el límite de
solicitudes por IP de esa acción. Esperar unos minutos; si ocurre con usuarios
legítimos, revisar si el servidor está detrás de un proxy (ver Seguridad y despliegue).