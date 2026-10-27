# Harness de diseño de interfaces — SIGDE

Reglas permanentes para estructurar vistas (PHP + Bootstrap 5 + Sass).

**Alcance:** aplican a **pantallas nuevas**. Las pantallas existentes **no se
migran en masa**. Si toco una pantalla vieja por otro motivo, la dejo ordenada
según este harness; si no, no la toco.

**Rutas reales del proyecto** (verificadas, no supuestas):

| Qué | Dónde vive de verdad |
|---|---|
| Vistas | `app/Views/<modulo>/` |
| Layout | `app/Views/layouts/app.php` |
| Componentes PHP | `app/Views/components/` |
| SCSS | `resources/scss/` → compila a `public/assets/css/app.css` |
| JS de módulos | `public/assets/js/modules/<modulo>/` |
| JS base | `public/assets/js/core/` |

---

## 1. Forma de trabajar (siempre, antes de cambiar algo)

1. Indicar **qué se va a modificar**, **en qué archivo** y, si hay que crear
   un archivo nuevo, **en qué carpeta** va.
2. No romper el esquema de trabajo existente del proyecto.
3. Reutilizar los componentes que ya existen (ver sección 5).
4. Mantener la paleta, el estilo y el formato actuales.
5. Si falta información o el código de otro archivo (layout, componentes, JS,
   controlador), **preguntar**. No inventar.
6. Al terminar, **explicar qué se hizo y por qué**: el usuario es estudiante y
   quiere entender, no solo recibir código.
7. Preguntas concretas y objetivas. Profundizar solo si lo pide.

---

## 2. Estructura de carpetas por módulo

Los nombres de archivo siguen la convención **ya existente** en el proyecto
(`<accion>-modal.php`, `<nombre>.php`), no la de un guion genérico.

```
app/Views/<modulo>/
  index.php                    <- solo orquesta: variables, includes y layout
  partials/
    <nombre>.php               <- cada bloque grande de la vista, en su archivo
  modals/
    <accion>-modal.php         <- UN modal = UN archivo
    (create-modal.php, edit-modal.php, show-modal.php, ratificar-modal.php...)

resources/scss/components/_<modulo>.scss      <- estilos del módulo
public/assets/js/modules/<modulo>/
  <modulo>.js                 <- inicialización y eventos generales
  modal-<accion>.js           <- lógica propia de un modal (si es extensa)
```

**Convención real de nombres** (la que ya usan las pantallas que lo hacen bien):

| Tipo | Patrón | Ejemplos que ya existen |
|---|---|---|
| Modal | `modals/<accion>-modal.php` | `create-modal.php`, `edit-modal.php`, `show-modal.php`, `ratificar-modal.php`, `editar-estudiante-modal.php` |

El guion bajo inicial (`_crear.php`) queda **descartado**: la convención real del
proyecto es `<accion>-modal.php` dentro de `modals/`.
| Partial | `<nombre>.php` | `employee-rows.php`, `recaudos-panel.php` |
| JS de módulo | `<modulo>.js` | `familiares.js`, `withdrawals.js`, `enrollments.js` |

**Rutas de `$extraScripts`:** van sin `assets` y sin `public`, porque
`layouts/app.php:23` las resuelve con `$assetsPath = BASE_URL . 'assets'`:

```php
$extraScripts = ['/js/modules/estudiantes/familiares.js'];
```

---

## 3. Anatomía de `index.php`

El `index.php` es un "directorio": de un vistazo se ve qué piezas tiene la
pantalla. No contiene HTML de modales ni bloques largos.

```php
<?php
declare(strict_types=1);

/**
 * Vista: <modulo>/index.php
 * Descripción breve.
 *
 * Variables esperadas del controlador:
 * @var tipo $variable  Descripción
 */

$pageTitle   = 'Gestión de Usuarios';
$currentNav  = 'usuarios';

$breadcrumbs = [
    ['label' => 'Seguridad', 'href' => null],
    ['label' => 'Usuarios',  'href' => null],
];

// ---------- Contenido principal ----------
ob_start();
?>
<div class="container-fluid px-0">
  <?php
  $pageHeading       = 'Gestión de Usuarios';
  $pageSubheading    = 'Descripción corta de la pantalla.';
  $pageHeaderActions = '';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <?php include __DIR__ . '/partials/<nombre>.php'; ?>
</div>
<?php
$pageContent = ob_get_clean();

// ---------- Modales (uno por archivo, fuera del contenido) ----------
ob_start();
include __DIR__ . '/modals/create-modal.php';
include __DIR__ . '/modals/edit-modal.php';
$pageModals = ob_get_clean();

// ---------- Scripts ----------
$extraScripts = ['/js/modules/<modulo>/<modulo>.js'];

include __DIR__ . '/../layouts/app.php';
```

**Orden fijo:** `strict_types` → docblock → variables de página →
`$pageContent` → `$pageModals` → `$extraScripts` → include del layout.

**Estado actual:** 8 de 19 `index.php` cumplen el orden completo. No es motivo
para reescribir los otros 11; sí es el patrón a seguir en las nuevas.

`declare(strict_types=1)` ya se usa en el proyecto: **23** controladores,
**40** modelos y **5** vistas.

---

## 4. Reglas de separación

### Modales
- **Un modal = un archivo** dentro de `modals/`, nombrado `<accion>-modal.php`.
  Nunca dos modales en el mismo archivo.
- Los modales se incluyen **solo** dentro de `$pageModals`, nunca dentro de
  `$pageContent`.
- Si varios botones abren el mismo tipo de modal, preferir **un modal
  reutilizable** que se configure con atributos `data-*` (clave, título,
  privado, etc.) en vez de duplicarlo.
- Nombre de ID: `modal<Accion><Modulo>` (ej. `modalCrearUsuario`).
  Formularios y campos usan el mismo prefijo (`formCrearUsuario`,
  `crearUsuario_nombre`) para que el JS no choque entre modales.
- **Todos los modales usan `components/modal.php`.** Ese componente es el único
  esquema de modal del portal y aporta la clase `.app-modal` (aspecto de marca,
  cuerpo con scroll, pie con borde). `scripts/verificar-vistas.php` falla si un
  modal raíz se escribe a mano sin el esquema completo.

### Partials
- Todo bloque grande o repetido de la vista va a `partials/<nombre>.php`.
- Los `include` comparten las variables del archivo que los llama: no se pasan
  por parámetro.
- Si un partial o modal usa una variable, la documenta en su docblock
  (`@var`). Si necesita una variable nueva, **se avisa para agregarla en el
  controlador**.

### Estilos
- **Nunca** `<style>` ni `style=""` dentro de la vista.
- Los estilos van en `resources/scss/components/_<modulo>.scss`, que llega
  compilado en `app.css`.
- Respetar la paleta y variables Sass existentes. No colores sueltos.
- **Exención: las plantillas de PDF.** `app/Views/constancias/pdf/`,
  `app/Views/docente/pdf/` y `app/Views/consulta_academica/estudiantes/export-pdf.php`
  son documentos autónomos, no pantallas: no cargan `app.css` y necesitan su CSS
  dentro. Sus `<style>` **no son una infracción** y no se tocan en este refactor.
  Cada uno lleva su hoja aparte en `resources/scss/pdf/`.
- Los anchos de columna repetidos **no se dejan como estilo en línea**:
  `<th style="width: 15%">` pasa a `<th class="ancho-15">`. Las clases viven en
  `resources/scss/base/_columnas.scss`.
- **El prefijo es `ancho-`, nunca `col-`.** Bootstrap ya define `.col-1` a
  `.col-12` para su grid, y el CSS compilado deja `.col-12{width:100%}`: si el
  archivo se llamara `.col-12` significando 12%, habría dos reglas de `width`
  sobre el mismo elemento y ganaría la última del bundle, estirando la tabla
  sin que nada fallara.
- **Coherencia:** una clase entra en `_columnas.scss` solo si su ancho se
  repite en **2 o más archivos** del proyecto. Un ancho que solo usa un archivo
  se queda en su `<colgroup>` con estilo en línea, aunque tenga el prefijo
  `ancho-` al lado: mezclar es preferible a llenar el archivo de utilidades de
  un solo uso. Los valores se añaden por orden de aparición, no se adelantan:
  una clase que nadie usa es CSS muerto.
- **Los píxeles llevan la unidad en el nombre** (`.ancho-170px`), porque
  `.ancho-170` sería ambiguo con `.ancho-12`, que significa 12%. Los porcentajes
  no llevan unidad (`ancho-14` = 14%) y los píxeles sí, en el mismo archivo.
- Al contar, hay que tolerar `style="width: 14%;"` con espacio y punto y coma:
  un patrón que solo acepte `%` justo antes de la comilla cuenta menos
  repeticiones de las que hay y hace quedarse inline un ancho que sí se repite.
- Verificar que los anchos de una tabla **suman 100%** antes y después de
  convertir, porque `table-layout: fixed` reparte el sobrante.

### Datos para el JavaScript
- Un `<script>` en la vista está prohibido, **con una excepción**: un bloque
  `<script type="application/json">` que solo lleva datos (un
  `json_encode($datos)`) no ejecuta nada y es el patrón estándar para pasar datos
  del servidor al JS sin inventar un `data-*` gigante. Lo que no vale es
  `<script>` con JavaScript dentro; ese va a
  `public/assets/js/modules/<modulo>/`.

### JavaScript
- **Nunca** `<script>` dentro de la vista.
- El JS va en `public/assets/js/modules/<modulo>/` y se registra en
  `$extraScripts`.
- Un archivo JS por modal solo si su lógica es extensa; si no, va en el JS del
  módulo.
- Los elementos que usa el JS se localizan por `id` o `data-*` descriptivos.
  No cambiar un id o `data-*` sin revisar el JS que lo usa.

---

## 5. Reutilización de componentes (revisar ANTES de crear algo)

Orden de revisión, de más general a más específico:

1. `app/Views/components/`             → componentes PHP (18 en total)
2. `resources/scss/components/`        → clases y estilos ya creados
3. `resources/scss/_variables.scss`    → paleta, espaciados, tipografía
4. `public/assets/js/modules/` y `core/` → helpers de JS ya existentes

Reglas:
- Si existe algo parecido, **reutilizarlo o ampliarlo**. No duplicarlo.
- Si hay que crear un componente nuevo, avisar **qué es, por qué no sirve uno
  existente y en qué carpeta va**.
- Si el mismo bloque aparece en 2 o más vistas, proponer convertirlo en
  componente.
- Mantener este catálogo actualizado cuando se cree un componente nuevo.

### 5.1a Partials compartidos entre vistas

| Partial | Lo comparten | Variables |
|---|---|---|
| `constancias/partials/alertas.php` | `constancias/estudio.php`, `constancias/inscripcion.php` | `$flash`, `$directorConfigurado` |

Cuando dos vistas de un módulo repiten un bloque **idéntico**, se extrae a un
partial. Si solo se parecen (columnas distintas, formularios distintos), **no**:
se deja la repetición, que es más legible que un partial con ocho parámetros.

### 5.1 Catálogo de componentes PHP (`app/Views/components/`)

Los 17 componentes existentes, con las variables que realmente aceptan (leídas
del código, no supuestas):

| Componente | Para qué sirve | Variables que espera |
|---|---|---|
| `page-header.php` | Título, subtítulo y acciones de la pantalla | `$pageHeading`, `$pageSubheading`, `$pageHeaderActions` |
| `modal.php` | Modal genérico. **El único esquema de modal del portal** | `$modalId`, `$modalTitle`, `$modalSubtitle`, `$modalBody`, `$modalFooter`, `$modalSize` (sm/md/lg/xl), `$modalStatic`, `$modalCentered`, `$modalHeaderExtra` |
| `data-panel.php` | Panel de listado: barra de herramientas, filtros, búsqueda, tabla, resumen y paginación | `$panelFilters`, `$panelFormAction`, `$panelFormId`, `$panelSearchName`, `$panelSearchValue`, `$panelSearchPlaceholder`, `$panelColgroup`, `$panelTableClass`, `$panelTableHead`, `$panelTableBody`, `$panelTableBodyId`, `$panelSummary`, `$panelSummaryId`, `$panelPagination` |
| `stat-card.php` | Tarjeta de indicador con icono, valor, tendencia y barra | `$statLabel`, `$statValue`, `$statIcon`, `$statTone`, `$statVariant`, `$statTrend`, `$statTrendType`, `$statMeta`, `$statProgress`, `$statHref` |
| `status-badge.php` | Insignia de estado (ratificado, pendiente, retirado...) | `$badgeLabel`, `$badgeStatus` |
| `avatar.php` | Avatar con iniciales o imagen | `$avatarInitials`, `$avatarSize`, `$avatarTone`, `$avatarSrc`, `$avatarAlt` |
| `period-chip.php` | Chip del período escolar activo | `$periodText`, `$periodStatus`, `$periodIsActive` |
| `persona-form.php` | Datos de una persona (empleado, estudiante, familiar) | `$prefixId`, `$prefixName`, `$showPhoto`, `$minDate`, `$maxDate`, `$dateTitle`, `$docMinLength`, `$docMaxLength`, `$btnQuitarFotoId`, `$slotTrasIdentificacion`, `$pideMunicipioParroquiaNacimiento`, `$estadosNacimiento`, `$idSeccionNacimiento`, `$nacimientoEstadoSeleccionado`, `$nacimientoParroquiaSeleccionada`, `$pideNacionalidad`, `$nacionalidadSeleccionada`, `$nacionalidadEsRegistroExistente` |
| `contacto-form.php` | Contacto de una persona | `$prefixId`, `$prefixName`, `$supportText`, `$phoneRequired` |
| `direccion-form.php` | Dirección de una persona | `$prefixId`, `$prefixName`, `$direccionRequired`, `$estados` |
| `lugar-nacimiento-form.php` | Estado, municipio y parroquia de nacimiento | `$pideMunicipioParroquiaNacimiento`, `$estadosNacimiento`, `$nacimientoEstadoSeleccionado`, `$nacimientoParroquiaSeleccionada`, `$idSeccionNacimiento`, `$prefixId`, `$prefixName`, `$nacimientoRequired` |
| `tipo-documento-select.php` | Select de tipo de documento | `$tipoDocId`, `$tipoDocName`, `$tipoDocOpciones`, `$tipoDocLabel`, `$tipoDocRequired`, `$tipoDocCol`, `$tipoDocSeleccionado` |
| `phone-input.php` | Input de teléfono con prefijo | `$phoneLabel`, `$phoneCodeName`, `$phoneNumberName`, `$phoneCodeId`, `$phoneNumberId`, `$phoneCodeValue`, `$phoneNumberValue`, `$phoneRequired`, `$phoneFeedback` |
| `wizard-summary-section.php` | Bloque del resumen del wizard, con icono y botón Editar | `$wsBody`, `$wsCampos`, `$wsEditarPaso`, `$wsIcono`, `$wsTitulo`, `$wsClass` |
| `wizard-summary-field.php` | Par etiqueta/valor del resumen del wizard y de vistas de detalle | `$wsField`, `$wsValue`, `$wsId`, `$wsLabel`, `$wsClass`, `$wsRaw` |
| `credentials-modal.php` | Modal compartido de credenciales generadas | `$credModalTitulo`, `$credModalTexto` |
| `featured-birthday-card.php` | Tarjeta destacada de cumpleaños del mes | `$birthdayData` |
| `modal-identity-header.php` | Cabecera de identidad para modales con avatar circular editable, nombre, ID y botón quitar foto | `$identityPreviewId`, `$identityInputId`, `$identityInputName`, `$identityNameId`, `$identityDocId`, `$identityQuitarId`, `$identityEliminarId`, `$identityBadgeExtra` |

### 5.2 Clases de estilo existentes (reutilizar antes de crear)

| Clase | Partial SCSS | Uso |
|---|---|---|
| `.app-modal` | `_modal.scss` | Aspecto de marca de todos los modales |
| `.info-alert`, `.security-alert` | `_modal.scss` | Avisos dentro de modales |
| `.content-card`, `__header`, `__title`, `__body`, `__empty` | `_content-card.scss` | Tarjeta de contenido |
| `.data-panel` y sus 13 slots | `_data-panel.scss` | Panel de listado (filtros, tabla, pie) |
| `.stat-card` y sus 11 slots | `_stat-card.scss` | Tarjeta de indicador |
| `.status-badge` | `_status-badge.scss` | Insignia de estado |
| `.action-badge` | `_action-badge.scss` | Insignia de acción |
| `.page-header` y sus 3 slots (4 clases) | `_page-header.scss` | Encabezado de pantalla |
| `.period-chip` | `_period-chip.scss` | Chip de período |
| `.avatar`, `.avatar-group` | `_avatar.scss` | Avatares |
| `.modal-tabs` | `_modal-tabs.scss` | Pestañas dentro de un modal |
| `.input-icon-group` | `_input-icon-group.scss` | Input con icono |
| `.label-sigde`, `.text-support`, `.link-sigde` | `_custom.scss` | Etiqueta, texto de apoyo y enlace del sistema |
| `.student-record__*` (26 clases) | `_user-detail.scss` | Ficha de estudiante |
| `.wizard-*` (41 clases) | `_form-wizard.scss` | Asistente de formularios |
| `.transparency-pattern`, `.image-preview-box`, `.cintillo-preview-box`, `.private-preview-box` | `_configuracion-institucional.scss` | Cajas de previsualización de imágenes |
| `.nomina-*` (23 clases) | `_docente-nomina.scss` | Nómina del docente |
| `.table` | `_tables.scss` | Estilo de tablas |
| `.ancho-<N>` | `base/_columnas.scss` | Ancho de columna de tabla. Hoy: 12, 13, 14, 15, 16, 17, 18, 19, 22, 30 |
| `.ancho-<N>px` | `base/_columnas.scss` | Ancho fijo en píxeles, cuando el reparto depende del contenido. Hoy: 120, 150, 170 |

### 5.3 Paleta y tokens (`resources/scss/_variables.scss`)

**Nunca escribir un color literal.** Estos son los tokens:

```scss
$primary:   #0a1440;   $secondary: #8a90a3;
$success:   #1fa971;   $danger:    #e0455f;
$info:      #3b6fe0;   $warning:   #e0a13b;
$light:     #f4f6fb;   $dark:      #1c2340;
$surface:         #ffffff;   $surface-soft:     #f8f9fb;
$border-color-soft: #e3e7f0;
$primary-tint:  rgba($primary, 0.06);
$tint-hover:    rgba($primary, 0.08);
$tint-active:   rgba($primary, 0.1);

$font-family-sans-serif: 'Work Sans', ...;
$font-weight-bold: 700;
$border-radius: .5rem;  $border-radius-sm: .4rem;
$border-radius-lg: .75rem;  $border-radius-xl: 1.25rem;
$border-radius-pill: 50rem;
$box-shadow;  $box-shadow-sm;  $shadow-input;
$card-padding: 1.25rem 1.35rem;  $card-padding-lg: 1.5rem 1.65rem;
```

Iconos: **Bootstrap Icons 1.13.1**, cargada desde
`public/assets/vendor/bootstrap-icons/`. Es la única fuente de iconos del
proyecto. Un nombre de icono mal escrito no da error en ningún sitio: sale un
`<i>` vacío. `scripts/verificar-vistas.php --html` lo detecta.

---

## 6. Reglas de código

- Escapar toda salida con `htmlspecialchars($valor)`.
  **En PHP 8.4 el default ya es `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401`**
  (cambió en PHP 8.1), así que escapa comillas simples y dobles sin pasar flags.
  Las 583 llamadas del proyecto no necesitan tocarse por seguridad.
- Sin lógica de negocio en la vista: solo condicionales de presentación
  (`if` para mostrar/ocultar). Consultas y cálculos viven en el controlador.
- Formularios con `<input type="hidden" name="csrf" ...>`.
- Mensajes de error junto al campo:
  `<div class="invalid-feedback" id="error-<campo>">`.
- Íconos decorativos con `aria-hidden="true"`; modales con `aria-labelledby`.
- Código limpio, ordenado, con indentación consistente. Sin duplicados
  ni "código pasta" (bloques copiados y pegados).
- Comentarios solo cuando explican el **porqué**, no lo obvio.
- Un archivo de más de ~150 líneas se evalúa para dividirlo en partials.

---

## 7. Checklist antes de entregar

- [ ] Dije qué modifiqué, dónde y qué archivos nuevos creé (con su ruta).
- [ ] `index.php` solo tiene variables, includes y layout.
- [ ] Cada modal está en su propio archivo en `modals/` y usa
      `components/modal.php`.
- [ ] No hay `<style>`, `style=""` ni `<script>` en la vista.
- [ ] Revisé el catálogo de componentes antes de crear algo nuevo.
- [ ] Reutilicé componentes y clases existentes en vez de reescribirlos.
- [ ] Si creé un componente o clase nueva, lo agregué a este catálogo.
- [ ] Los estilos nuevos están en el SCSS del módulo y usan `$primary`,
      `$surface-soft`, etc., no colores sueltos.
- [ ] Toda salida está escapada con `htmlspecialchars()`.
- [ ] Los `id` y `data-*` que usa el JS siguen coincidiendo.
- [ ] Corrí `npm run verificar` y pasó.
- [ ] Expliqué qué hice y por qué.
- [ ] Si faltaba información, pregunté en vez de inventar.

---

## 8. Verificación obligatoria

```bash
npm run verificar      # assets + ids JS/vistas + reglas de vistas + menú vs rutas
```

Y sobre HTML ya renderizado, cuando el módulo lo permita:

```bash
php scripts/verificar-vistas.php --html /ruta/al/render.html
```

Cubre siete fallos que rompen la pantalla en silencio:

1. `?>` dentro de un comentario de una línea.
2. Código PHP fuera de bloque.
3. Búferes `ob_start()`/`ob_get_clean()` descuadrados.
4. Celdas de la fila contra columnas de la cabecera (sumando `colspan`).
5. `<col>` del `colgroup` contra columnas de la cabecera.
6. Modal raíz que no sigue el esquema de `components/modal.php`.
7. Icono `.bi-*` que no existe en la librería.

Y una más, en `scripts/verificar-menu.php`: cruza los 41 enlaces del menú
lateral (`app/Views/helpers/ui.php`) con el mapa de permisos del front
controller (`$rutasPermitidas`, en `public/index.php`) para los cinco roles.
Falla si un rol ve un enlace que la ruta no abre (**403**) o si el enlace
apunta a una ruta inexistente (**404**).

**Por qué hace falta:** el menú y las rutas son dos listas de roles escritas
a mano en archivos distintos. Cuando se separan, el enlace ya se pintó en el
menú y el 403 solo aparece al pulsarlo. Los `href="#"` se informan aparte
pero no fallan: son funciones planificadas.

Se ejecuta con `npm run verificar` o suelto con
`php scripts/verificar-menu.php`.

---

### 8.1 Pantalla vieja de listados: retirada

`docente/listados_actas.php` era la versión anterior de `docente/nomina.php`, que
es la que usa el menú del docente. Se retiró el 2026-10-18: borrados la vista,
`ListadosSeccionController` y `ListadosSeccionModel`, más las tres rutas
`listadosSeccion/*`.

Lo único que tenía y no se portó, y por qué:

| Qué | Por qué no se migra |
|---|---|
| **Ordenar por columna** | La sección tiene 4-6 alumnos. No hay nada que ordenar |
| **Mostrar/ocultar columnas** | Por lo mismo |
| **Elegir otra sección** | La vieja dejaba ver la lista de una sección que no era del docente. Es un agujero de privacidad, no una función |
| **Pestaña «Asistencia»** | No registraba asistencia: era un registro en blanco para imprimir y firmar a mano, sin guardar nada en la base. No hay tabla de asistencia en el esquema. Portarlo no era un refactor, era diseñar el circuito completo |

Lo que sí era útil se portó antes de borrar: **imprimir desde la vista previa**
del `docente/nomina.php`, con el mismo respaldo que usaba la vieja.

Si algún día se quiere la asistencia **de verdad**, es un módulo nuevo: tabla
`asistencia_reunion`, quién la registra, y qué pasa con el papel. Va en su
propia decisión, no aquí.

### 8.2 Pendientes anotados (migrar en un módulo posterior, con aprobación)

| Qué | Dónde | Nº |
|---|---|---|
| `style=""` estáticos | `app/Views/empleados/modals/show-modal.php` | 4 |
| `style=""` estáticos | `app/Views/empleados/modals/add-title-modal.php` | 2 |
| `style=""` estáticos | `app/Views/empleados/modals/edit-modal.php` | 1 |
| `declare(strict_types=1)` ausente | 13 archivos de `empleados/` (6 `employee-steps/`, 6 `modals/`, `partials/employee-rows.php`) | 13 |

Los `style=""` de esos modales son estáticos y pueden ir a
`resources/scss/components/_empleados.scss`, que todavía no existe. Antes de
añadir `strict_types` a cualquiera de esos 13 hay que revisar, variable por
variable, qué llega a `htmlspecialchars()` y a funciones con tipos: si puede
venir `null`, el tipo estricto convierte un *deprecation* en un **TypeError
fatal**. Ese análisis se hizo para `empleados/index.php` y salió limpio
(el único `htmlspecialchars` del archivo recibe `cargo.nombre`, que es
`NOT NULL` con clave única).

## 9. Fuera de alcance por ahora (decisiones tomadas)

No se hacen salvo que el usuario lo pida explícitamente, y por módulo con su
aprobación:

- **Migrar las pantallas existentes** a este harness. Solo las nuevas.
- **Renombrar `app/Views`.** La estructura de carpetas es la real y no cambia.
- **Los 247 `style=""` y las 526 líneas de `<style>`** que quedan en vistas
  antiguas. Se limpia por módulo, con aprobación.
- **Las 583 llamadas de `htmlspecialchars()`** sin flags explícitos. En PHP 8.4
  ya escapan comillas correctamente; los flags explícitos solo aportarían
  independencia de la versión de PHP.

## 10. Diagramas de casos de uso (documentación de tesis)

Los diagramas de casos de uso se construyen en **draw.io**, quedan en **hoja
carta vertical** y se presentan con **normas APA (7.ª ed.)** dentro de la tesis.
No forman parte de la aplicación: viven en la carpeta de documentación, no en
`app/`.

**Archivo plantilla (maestro):**
`docs/gestion-cuentas-usuario/diagrama-gestion-cuentas-usuario.drawio`.
Para una interfaz nueva se **copia esa plantilla** y se cambian solo textos y
posiciones. No se rediseñan estilos, tamaños ni colores. No se inventan casos de
uso que no se vean en la interfaz. Si falta información, se pregunta antes de
dibujar.

Nombres de archivo: `fig-NN-<interfaz>.drawio/png/pdf`
(p. ej. `fig-05-gestion-cuentas-usuario.png`).

### 10.1 Regla principal: diagrama «por interacción»

- El actor se conecta **directamente solo a casos base**, que son las
  **interacciones visibles** de la interfaz: un botón, un grupo de filtros, una
  columna de acciones.
- Todo lo demás (filtros, acciones de fila, modales, resultados) cuelga de esos
  casos base con `«extend»` o `«include»`.
- **No** se conecta el actor a casos extend ni include.
- **No** se crean casos genéricos como «Consultar listado»: ver el listado es lo
  que ocurre al abrir la pantalla.

| Elemento de la interfaz | Caso de uso base |
|---|---|
| Botón principal (ej. «+ Crear Usuario») | Crear usuario |
| Barra de filtros y buscador | Aplicar parámetros de búsqueda |
| Columna «Acciones» de la tabla | Administrar cuentas registradas |

### 10.2 Reglas UML

| Relación | Línea | Flecha | Dirección | Cuándo |
|---|---|---|---|---|
| Asociación (actor–caso) | Sólida | Sin punta | Actor → caso base | Solo actor con casos base |
| `«extend»` | Punteada (`dashPattern=7 5`) | Punta abierta | **Del extensor hacia el caso base** | Opcional (filtros, acciones de fila) |
| `«include»` | Punteada (`dashPattern=7 5`) | Punta abierta | **Del caso base hacia el incluido** | Obligatorio (siempre ocurre) |

- Opcional / el usuario decide → `extend`. Siempre ocurre al ejecutar el caso →
  `include`.
- Un caso reutilizado (como el modal) se dibuja **una sola vez**; las flechas de
  varios casos llegan al mismo óvalo.
- Las credenciales generadas se conectan a **Crear usuario**, no al modal: el
  modal solo captura datos; las credenciales son el resultado de crear.

### 10.3 Página y tamaño (hoja vertical)

- **Hoja carta vertical** (21,59 × 27,94 cm), márgenes de **2,54 cm** por lado.
- Área útil: **16,51 × 22,86 cm = 624 × 864 px** (draw.io trabaja a 96 px por
  pulgada). En draw.io: *Archivo → Configuración de página → Papel: Carta,
  Orientación: Vertical*. El origen del diagrama es **(96, 96)** px.
- **Ancho máximo de la figura: 624 px. Alto máximo: 760 px** (el resto del área
  útil lo ocupan la leyenda de arriba y la nota de abajo).
- Si un diagrama necesita más de 760 px de alto: se reduce el espacio entre
  grupos (**nunca** el tamaño de letra) o se divide en dos figuras.

**Distribución** (coordenadas relativas al área útil; en el XML llevan sumado el
origen 96, 96):

```
x=5..85     actor (centro x=45), nombre debajo
x=98        tronco vertical de las asociaciones del actor
x=110..590  título del RF encima (y=2, alto 28) y recuadro del sistema (y=40, alto 710)
x=130..280  columna A: casos base (150x70) y "Visualizar credenciales" (150x70)
x=350..520  columna B: extend y modal (170x56)
x=550       línea vertical (codo) del include "Editar → Modal"
```

| Elemento | y (arriba-izq.) |
|---|---|
| Crear usuario (A) / Modal (B) | 110 / 117 (centros alineados en y=145) |
| Visualizar credenciales (A) | 235 |
| Aplicar parámetros (A) | 355 (centro y=390) |
| 3 filtros (B) | 296, 362, 428 (separación 66) |
| Administrar cuentas (A) | 585 (centro y=620) |
| 3 acciones (B) | 526, 592, 658 (separación 66) |
| Actor | x=30, y=369, **tamaño exacto 30 x 60 px** (brazos a y=390, alineados con «Aplicar parámetros») |

Para otra interfaz: mismas columnas y misma separación de 66 px; más extends →
más alto, hasta 760 px.

### 10.4 Estilo visual (para tesis)

**Tipografía: una sola familia, `fontFamily=Times New Roman`.** Todo el texto
del diagrama (óvalos, etiquetas, actor, sistema) la usa. La tesis exige Times
New Roman, aunque APA 7 recomiende sans serif en figuras; prevalece la fuente de
la institución. El tamaño va **entre 11 y 18 px** (1 px = 0,75 pt) y **nunca
bajo 12 px**.

| Elemento | fontSize (px) | ≈ pt | Estilo | Color |
|---|---|---|---|---|
| Título del RF, sobre el recuadro | 16 | 12 | Negrita | `#14213D` |
| Nombre del sistema (en el recuadro) | 14 | 10,5 | Negrita | `#14213D` |
| Institución (bajo el sistema) | 13 | 9,75 | Normal | `#14213D` |
| Casos base | 13 | 9,75 | Negrita | `#14213D` |
| Casos extend / include | 13 | 9,75 | Normal | `#111111` |
| Etiquetas `«extend»` / `«include»` | 12 | 9 | Cursiva | `#3A4358` |
| Nombre del actor | 13 | 9,75 | Negrita | `#14213D` |

**Colores y trazos** (solo esta paleta; legible también en blanco y negro):

- Azul marino `#14213D`: bordes, actor, asociaciones.
- Casos base: relleno `#E8ECF5`, borde 1,5. Demás casos: relleno blanco, borde 1,3.
- Recuadro del sistema: relleno blanco, borde 1,6.
- Asociación: sólida, 1,4. Extend/include: punteada (`dashPattern=7 5`), 1,3,
  color `#3A4358`, punta abierta (`endArrow=open;endFill=0`), etiqueta con fondo
  blanco (`labelBackgroundColor=#FFFFFF`).

**Estilos base (copiar tal cual):**

```
Base:      ellipse;whiteSpace=wrap;html=1;fillColor=#E8ECF5;strokeColor=#14213D;strokeWidth=1.5;fontSize=13;fontStyle=1;fontColor=#14213D;fontFamily=Times New Roman;
Otro:      ellipse;whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#14213D;strokeWidth=1.3;fontSize=13;fontColor=#111111;fontFamily=Times New Roman;
Asoc.:     endArrow=none;html=1;strokeColor=#14213D;strokeWidth=1.4;
Relación:  endArrow=open;endFill=0;endSize=8;dashed=1;dashPattern=7 5;html=1;strokeColor=#3A4358;strokeWidth=1.3;fontSize=12;fontStyle=2;fontColor=#3A4358;labelBackgroundColor=#FFFFFF;fontFamily=Times New Roman;
Actor:     shape=umlActor;aspect=fixed;verticalLabelPosition=bottom;verticalAlign=top;html=1;outlineConnect=0;strokeColor=#14213D;strokeWidth=1.8;fillColor=#FFFFFF;fontSize=13;fontStyle=1;fontColor=#14213D;fontFamily=Times New Roman;
```

**Reglas de disposición**

- Actor fuera del recuadro, a la izquierda. Las asociaciones salen por un
  **tronco vertical común** (x=98) para que ninguna línea cruce el nombre del
  actor.
- Sin cruces entre relaciones. Etiquetas sobre la línea, sin tapar óvalos ni
  texto.
- Máximo 3 líneas de texto por óvalo; separación uniforme; margen ≥ 20 px entre
  cualquier óvalo y el borde del recuadro.
- Nombres: verbo en infinitivo + objeto, mayúscula solo en la primera palabra,
  mismo vocabulario de la interfaz. Símbolos « » (no `<<` `>>`).
- Recuadro del sistema, dos líneas: `Sistema de Información para la Gestión de
  Datos Escolares` (negrita) y `U.E. Escuela Básica Isidro Ramírez`.
- **Título del RF** encima del recuadro, centrado sobre él, Times New Roman
  16 px en negrita: el nombre exacto de la interfaz (ej. `Gestión de Cuentas
  de Usuario`), precedido del código del requisito si existe (ej. `RF-03.
  Gestión de Cuentas de Usuario`). Sin prefijo «Módulo:» ni subtítulo.
- **Tamaños fijos, no modificar:** casos base 150 × 70 px; extend e include
  170 × 56 px; **actor 30 × 60 px** (proporción 1:2). Si el actor se ve achatado o
  estirado, seleccionarlo y en *Organizar → Tamaño* poner 30 × 60 con la
  proporción bloqueada (`aspect=fixed`).

### 10.5 Reglas del XML (cuando el agente escribe el archivo)

- Cada caso de uso es un `vertex` con estilo `ellipse;whiteSpace=wrap;html=1;...`
  (los estilos exactos de la plantilla; no cambiarlos).
- Cada relación es un `edge` con `source` y `target`. En `«extend»` el `source`
  es el extensor y el `target` el caso base. En `«include»` el `source` es el
  caso base y el `target` el incluido.
- Las posiciones del XML llevan **sumado el origen (96, 96)**.
- Orden de las celdas: **recuadro del sistema primero** (queda al fondo), luego
  óvalos y actor, luego relaciones.
- El tronco del actor se hace con puntos de control en `x = 194`; el codo del
  include «Editar → Modal», con puntos de control en `x = 646`.
- Comprobar que el XML esté bien formado antes de entregar.

### 10.6 Exportación

1. **PNG**: zoom 300 % (o 300 DPI), borde 10, fondo **blanco** (sin
   transparencia), sin «solo selección». Es el que se inserta en Word.
2. **PDF**: marcar *Recortar* (crop). Respaldo vectorial.
3. Guardar siempre también el `.drawio` (es el editable).

> En este entorno **no hay conversor SVG→PNG ni a PDF instalado** (`rsvg-convert`,
> `inkscape`, `imagemagick`, `cairosvg` no están y `pip` está bloqueado por
> PEP 668). La exportación la hace el usuario desde draw.io, en dos clics:
> *Archivo → Exportar como → PNG*.

### 10.7 Presentación en la tesis (APA 7.ª ed.)

Leyenda **encima** de la figura, en Word y no dentro de la imagen:

1. Línea 1: **Figura N** (negrita, alineada a la izquierda). Numeración
   correlativa en toda la tesis, en arábigos.
2. Línea 2: título de la figura en *cursiva*, descriptivo y breve. Ej.:
   *Diagrama de casos de uso del módulo Gestión de cuentas de usuario*.
3. Debajo, la figura, sin recuadro adicional alrededor de la imagen.
4. Debajo de la figura, la nota: *Nota.* (en cursiva) + texto. Ej.: *Nota.*
   Elaboración propia.

Reglas de uso:

- Mencionar la figura **en el texto antes de que aparezca**: «En la Figura 5 se
  muestra…» (no «la siguiente figura»).
- Mismo tipo de letra del documento para número, título y nota (Times New Roman
  12 o la que fije la institución); el texto interno de la imagen también es
  Times New Roman.
- Insertar el PNG **en línea con el texto**, a **16,5 cm de ancho como máximo**
  y sin ampliarlo por encima del 100 %. Si se amplía, la letra supera los 14 pt;
  si se reduce mucho, baja de 8 pt.
- Una figura de página completa va sola en la página (hoja vertical), con su
  leyenda y su nota.
- Insertar las leyendas con la función de títulos de Word para generar después
  la **lista de figuras** del índice.
- Agregar texto alternativo a la imagen (descripción breve del diagrama).
- **Verificar con el manual de la institución o el tutor** dos detalles que
  varían entre universidades: mayúsculas en el título de la figura (en español
  APA usa mayúscula solo inicial) y si exigen «Fuente: Elaboración propia (año)»
  en lugar de la nota.

### 10.8 Procedimiento

1. Leer la vista del módulo y listar: botón principal, filtros y buscador,
   columnas de acciones, modales.
2. Presentar al usuario la tabla **casos base / extend / include** y esperar
   confirmación antes de dibujar.
3. Construir en draw.io desde la plantilla (secciones 10.3, 10.4 y 10.5).
4. Revisar con la lista de verificación.
5. Exportar PNG y PDF, **mirar el PNG** y corregir lo que se vea mal.
6. Entregar `.drawio` + PNG + PDF, con la leyenda APA sugerida (número, título y
   nota), y explicar brevemente qué se decidió y por qué.

### 10.9 Lista de verificación

- [ ] El actor solo se conecta a casos base (línea sólida sin punta).
- [ ] Cada caso base corresponde a una interacción visible en la interfaz.
- [ ] Cada `«extend»` apunta **hacia** su caso base; cada `«include»` sale
      **desde** el caso base.
- [ ] Ningún caso de uso está duplicado.
- [ ] Título del RF encima del recuadro; nombre del sistema e institución
      dentro del recuadro.
- [ ] El actor mide exactamente 30 × 60 px (no se ve achatado ni estirado).
- [ ] Nombres con verbo en infinitivo, mismos términos que la interfaz, sin
      faltas de ortografía.
- [ ] Hoja carta vertical; figura de máximo 624 × 760 px, dentro de los márgenes
      de 2,54 cm.
- [ ] Letra **Times New Roman**, con los tamaños de la tabla de 10.4 (nunca
      menos de 12 px); ninguna celda con otra fuente.
- [ ] Solo la paleta definida; se lee bien en blanco y negro.
- [ ] Sin cruces de líneas, sin etiquetas sobre óvalos, ningún texto cortado.
- [ ] Exportado en PNG (300 %, fondo blanco) y PDF; se revisó visualmente el
      PNG.
- [ ] Leyenda APA lista: **Figura N**, título en cursiva, *Nota.*
- [ ] No se agregó ningún caso que no esté en la interfaz ni en las indicaciones
      del usuario.
