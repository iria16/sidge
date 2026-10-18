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

1. `app/Views/components/`             → componentes PHP (17 en total)
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
npm run verificar      # assets + ids JS/vistas + reglas de vistas
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

---

### 9.1 Pendientes anotados (migrar en un módulo posterior, con aprobación)

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
