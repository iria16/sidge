<?php
/**
 * Vista: docente/nomina.php  ·  /docente/nomina
 * Listados de solo lectura de la sección del docente.
 *
 * Una sola tabla: los chips cambian de vista (?vista=) y la barra de filtros
 * actualiza la tabla automáticamente (form.auto-filters + .data-panel). Las
 * columnas las decide el Controller según la vista; aquí solo se pintan.
 *
 * @var array|null $seccion        Sección resuelta en el servidor (sesión).
 * @var bool       $tieneSeccion
 * @var string     $vista          nomina | acta | contacto | cumpleaneros
 * @var array      $vistas         Definición de las vistas permitidas.
 * @var array      $columnas       clave => encabezado, en orden.
 * @var array      $filas          Resultado de EstudianteModel::nominaSeccion().
 * @var array      $filtros        q, sexo, mes (ya validados).
 * @var array      $meses          1..12 => nombre.
 * @var int        $mesActual      Mes en curso (1..12) en America/Caracas.
 * @var string     $nombreEscuela
 */
require_once __DIR__ . '/../helpers/ui.php';

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/* Query string con SOLO los filtros permitidos de la vista destino: los enlaces
   de chips y de PDF nunca reenvían parámetros ajenos a esa vista.

   `orden` y `sentido` sí viajan siempre, y esa es la razón de que el orden se
   mantenga al cambiar de pestaña: el chip apunta a la otra vista llevándose el
   criterio elegido. En las vistas de orden fijo (cumpleañeros, acta) se quitan
   antes de construir la URL, para no dejar en la barra un control que ahí no
   hace nada. */
$urlNomina = static function (array $extra = []) use ($filtros, $vista, $vistas): string {
    $vistaDestino    = (string) ($extra['vista'] ?? $vista);
    $filtrosDestino  = $vistas[$vistaDestino]['filtros'] ?? ['q'];
    $valoresActuales = [
        'q'    => $filtros['q'],
        'sexo' => $filtros['sexo'],
        'mes'  => $filtros['mes'] !== null && isset($_GET['mes']) ? (string) $filtros['mes'] : '',
    ];

    $params = ['vista' => $vistaDestino];
    foreach ($filtrosDestino as $claveFiltro) {
        if (($valoresActuales[$claveFiltro] ?? '') !== '') {
            $params[$claveFiltro] = $valoresActuales[$claveFiltro];
        }
    }

    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }

    return BASE_URL . 'docente/nomina' . ($params ? '?' . http_build_query($params) : '');
};

$tituloVista        = $vistas[$vista]['titulo'];
$filtrosVista       = $vistas[$vista]['filtros'] ?? ['q'];
/* Un solo buscador para todas las pestañas, y el mismo texto de ayuda en todas:
   la búsqueda cubre nombre, apellido y representante legal en cualquier vista,
   así que no puede cambiar la pista según la pestaña. */
$placeholderBuscar  = 'Nombre, apellido o representante';
$tieneSelectFiltro  = in_array('sexo', $filtrosVista, true) || in_array('mes', $filtrosVista, true);
$textoSeccion       = $seccion ? trim($seccion['grado'] . ' "' . $seccion['seccion'] . '"') : '';
$enlaceWaSeccion    = trim((string) ($seccion['enlace_whatsapp'] ?? ''));
$nombreDocenteWa    = trim((string) ($seccion['docente_nombre'] ?? ($_SESSION['nombre'] ?? '')));
$articuloDocente    = (($seccion['docente_sexo'] ?? '') === 'F') ? 'la docente' : 'el docente';
$identificacionDoc  = $nombreDocenteWa !== ''
    ? $articuloDocente . ' ' . $nombreDocenteWa
    : $articuloDocente;
$nombreEscuelaWa    = $nombreEscuelaWa ?? $nombreEscuela;
$saludoWa           = $saludoWa ?? 'Buen día';
$mensajeWa          = $saludoWa . ', le escribe ' . $identificacionDoc . ', de ' . $textoSeccion . ' de la ' . $nombreEscuelaWa . '.';
$mensajeInvitacionWa = $enlaceWaSeccion !== ''
    ? $mensajeWa . ' Le comparto el enlace para unirse al grupo oficial de WhatsApp de la sección: ' . $enlaceWaSeccion
    : '';
$fmtFecha           = static fn(?string $f): string => $f ? date('d/m/Y', strtotime($f)) : '';

/** Teléfono + botón de WhatsApp individual (+ botón de invitar al grupo si aplica). */
$telefonoConWa = static function (string $tel, bool $incluirInvitacionGrupo = false) use ($e, $mensajeWa, $mensajeInvitacionWa): string {
    $html   = '<span class="nomina-tel">' . $e($tel) . '</span>';
    $enlace = enlaceWhatsapp($tel, $mensajeWa);
    if ($enlace !== '') {
        $html .= ' <a class="btn btn-sm btn-outline-success nomina-wa" href="' . $e($enlace) . '"'
               . ' target="_blank" rel="noopener noreferrer" title="Escribir mensaje directo por WhatsApp"'
               . ' aria-label="Escribir por WhatsApp a ' . $e($tel) . '">'
               . '<i class="bi bi-whatsapp" aria-hidden="true"></i></a>';
        if ($incluirInvitacionGrupo && $mensajeInvitacionWa !== '') {
            $enlaceInv = enlaceWhatsapp($tel, $mensajeInvitacionWa);
            if ($enlaceInv !== '') {
                $html .= ' <a class="btn btn-sm btn-outline-primary nomina-wa nomina-wa--invitar" href="' . $e($enlaceInv) . '"'
                       . ' data-tel="' . $e($tel) . '"'
                       . ' target="_blank" rel="noopener noreferrer" title="Enviar invitación al grupo de WhatsApp de la sección"'
                       . ' aria-label="Invitar al grupo de WhatsApp a ' . $e($tel) . '">'
                       . '<i class="bi bi-person-plus" aria-hidden="true"></i></a>';
            }
        }
    }

    return $html;
};

/* Teléfono de una persona autorizada, para la columna de autorizados.
 *
 * El ícono va a la DERECHA del número, dentro de una columna de ancho fijo, y no
 * pegado al número como en telefonoConWa(): así los números quedan en línea recta
 * aunque unos tengan WhatsApp y otros no. Un fijo (0212) no lleva ícono, pero sí
 * el hueco, para que los números empezen todos en la misma columna. */
$telefonoAutConWa = static function (string $tel) use ($e, $mensajeWa): string {
    $enlace = enlaceWhatsapp($tel, $mensajeWa);
    $boton  = $enlace !== ''
        ? '<a class="btn btn-sm btn-outline-success nomina-wa nomina-tel-ico__boton" href="' . $e($enlace) . '"'
          . ' target="_blank" rel="noopener noreferrer" title="Escribir mensaje directo por WhatsApp"'
          . ' aria-label="Escribir por WhatsApp a ' . $e($tel) . '">'
          . '<i class="bi bi-whatsapp" aria-hidden="true"></i></a>'
        : '';

    return '<div class="nomina-tel-linea">'
         . '<span class="nomina-tel-num">' . $e($tel) . '</span>'
         . '<span class="nomina-tel-ico">' . $boton . '</span>'
         . '</div>';
};

ob_start();
$pageTitle        = 'Listados de la sección';
$currentNav       = 'mi-seccion-listados';
$pageHeading      = 'Listados de la sección';
$pageSubheading   = $seccion
    ? $seccion['grado'] . ' · Sección ' . $seccion['seccion'] . ' · ' . $seccion['turno'] . ' · ' . $seccion['anio_escolar']
    : 'Sin sección asignada';
if (!$tieneSeccion) {
    $pageHeaderActions = '';
} else {
    /* Un solo botón para todas las pestañas, y hace lo mismo en todas: no
       descarga nada, muestra el documento en el modal para que el docente lo
       revise y solo entonces aparezca el enlace de descarga. El PDF sale por
       la ruta de esta misma vista con sus filtros puestos, así que lo que se ve
       es exactamente lo que se baja. El acta además pide motivo y fecha; las
       demás no, y data-filtros le dice a docente.js qué filtros son válidos. */
    $pageHeaderActions = '<button type="button" class="btn btn-primary" id="btnVistaPreviaDocumento"'
        . ' data-base-pdf="' . $e($urlNomina(['formato' => 'pdf'])) . '"'
        . ' data-filtros="' . $e(implode(',', $filtrosVista)) . '">'
        . '<i class="bi bi-eye me-1" aria-hidden="true"></i>Visualizar documento</button>';
}
include __DIR__ . '/../components/page-header.php';
?>

<?php if (!$tieneSeccion): ?>
  <div class="alert alert-warning d-flex align-items-start gap-3 shadow-sm" role="status">
    <i class="bi bi-exclamation-triangle fs-4 flex-shrink-0" aria-hidden="true"></i>
    <div>
      <div class="fw-semibold mb-1">No tienes una sección asignada</div>
      <div class="text-dark">La dirección debe asignarte una sección en el período escolar en curso.</div>
    </div>
  </div>
<?php else: ?>

  <?php /* ── Chips de vista ─────────────────────────────────────────────── */ ?>
  <nav class="nomina-chips mb-3" aria-label="Tipo de listado">
    <?php foreach ($vistas as $clave => $def): ?>
      <a class="nomina-chip <?= $clave === $vista ? 'is-active' : '' ?>"
         href="<?= $e($urlNomina(['vista' => $clave])) ?>"
         data-filtros="<?= $e(implode(',', $def['filtros'] ?? ['q'])) ?>"
         <?= $clave === $vista ? 'aria-current="page"' : '' ?>><?= $e($def['etiqueta']) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($vista === 'contacto'): ?>
    <section class="card border-0 shadow-sm mb-3" id="tarjetaGrupoWhatsapp">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
        <?php if ($enlaceWaSeccion !== ''): ?>
          <div class="d-flex align-items-start gap-3">
            <span class="text-success fs-4 lh-1 mt-1" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
            <div>
              <h2 class="h6 mb-1">Grupo de WhatsApp de la sección</h2>
              <p class="text-muted small mb-0">
                Usa el botón <i class="bi bi-person-plus text-primary"></i> junto al teléfono de cada representante en la tabla para enviarle la invitación por WhatsApp, o copia el enlace aquí.
              </p>
            </div>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnCopiarEnlaceWhatsapp"
                    data-enlace="<?= $e($enlaceWaSeccion) ?>">
              <i class="bi bi-clipboard me-1" aria-hidden="true"></i><span>Copiar enlace</span>
            </button>
            <a class="btn btn-sm btn-success" href="<?= $e($enlaceWaSeccion) ?>"
               target="_blank" rel="noopener noreferrer">
              <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Abrir grupo
            </a>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="modal" data-bs-target="#modalEnlaceWhatsapp">
              <i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar enlace
            </button>
          </div>
        <?php else: ?>
          <div class="d-flex align-items-start gap-3">
            <span class="text-success fs-4 lh-1 mt-1" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
            <div>
              <h2 class="h6 mb-1">Grupo de WhatsApp de la sección</h2>
              <p class="text-muted small mb-0">
                Aún no has registrado un grupo. Para configurarlo: 1) Crea el grupo en WhatsApp, 2) Copia el enlace de invitación del grupo y 3) Pégalo aquí para poder invitar a los representantes desde la lista.
              </p>
            </div>
          </div>
          <div>
            <button type="button" class="btn btn-sm btn-outline-success"
                    data-bs-toggle="modal" data-bs-target="#modalEnlaceWhatsapp">
              <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Agregar enlace del grupo
            </button>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php /* ── Filtros automáticos propios de cada vista (main.js) ──────────
           Se dibuja solo si la vista trae algún filtro. El acta no trae ninguno:
           sin esta comprobación la tarjeta salía vacía, con el hueco de los
           filtros, que es lo que veía el docente al entrar a esa pestaña. */ ?>
<?php if ($filtrosVista !== []): ?>
  <form class="card border-0 shadow-sm mb-3 auto-filters" id="filtrosListadoNomina" method="get" action="<?= BASE_URL ?>docente/nomina">
    <input type="hidden" name="vista" value="<?= $e($vista) ?>">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <?php if (in_array('sexo', $filtrosVista, true)): ?>
          <div class="col-12 col-md-4 col-lg-3">
            <label for="sexo" class="form-label small fw-semibold">Sexo</label>
            <select class="form-select" id="sexo" name="sexo">
              <option value="">Todos</option>
              <option value="F" <?= $filtros['sexo'] === 'F' ? 'selected' : '' ?>>Femenino</option>
              <option value="M" <?= $filtros['sexo'] === 'M' ? 'selected' : '' ?>>Masculino</option>
            </select>
          </div>
        <?php endif; ?>
        <?php if (in_array('mes', $filtrosVista, true)): ?>
          <div class="col-12 col-md-4 col-lg-3">
            <label for="mes" class="form-label small fw-semibold">Mes de cumpleaños</label>
            <select class="form-select" id="mes" name="mes">
              <?php foreach ($meses as $num => $nombreMes): ?>
                <option value="<?= (int) $num ?>" <?= $filtros['mes'] === $num ? 'selected' : '' ?>><?= $e($nombreMes) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if (in_array('q', $filtrosVista, true)): ?>
          <div class="<?= $tieneSelectFiltro ? 'col-12 col-md-8 col-lg-9' : 'col-12' ?>">
            <label for="q" class="form-label small fw-semibold">Buscar</label>
            <input type="search" class="form-control" id="q" name="q" maxlength="60"
                   value="<?= $e($filtros['q']) ?>" placeholder="<?= $e($placeholderBuscar) ?>">
          </div>
        <?php endif; ?>
      </div>
    </div>
  </form>
<?php endif; ?>

  <?php /* ── Tabla única ─────────────────────────────────────────────────── */ ?>
  <?php /* Los autorizados NO se aplanan: el modelo ya devuelve una lista de
           estudiantes, cada uno con su arreglo de autorizados, que es la forma en
           que la pantalla los muestra y en la que el PDF los muestra. Aplanar
           aquí era lo que obligaba a repetir el nombre del alumno y del
           representante en cada fila. */ ?>
<?php /* El acta no pinta la hoja con el alumnado. Lo que el docente necesita es
         el motivo y la fecha; el documento con los alumnos y sus líneas de
         firma es el PDF, que se arma con esas columnas. Una tabla aquí solo
         obligaba a leer dos veces lo mismo. */ ?>
<?php if ($vista !== 'acta'): ?>
  <section class="card border-0 shadow-sm nomina-hoja nomina-hoja--<?= $e($vista) ?> data-panel">
    <div class="data-panel__body">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">
          <?= $e($tituloVista) ?>
          <?php if ($vista === 'cumpleaneros' && $filtros['mes'] !== null): ?>
            · <?= $e($meses[$filtros['mes']]) ?>
          <?php endif; ?>
        </h2>
        <span class="small text-muted"><?= count($filas) ?> estudiante<?= count($filas) === 1 ? '' : 's' ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 nomina-tabla" id="nominaTabla">
        <thead class="table-light">
          <tr>
            <?php /* Encabezados de texto. El orden de los listados es fijo y lo
                    aplica el servidor: alfabético por apellido, que es como se
                    lee un listado y como se entrega en el PDF. */ ?>
            <?php foreach ($columnas as $clave => $titulo): ?>
              <th scope="col" class="nomina-col--<?= $e($clave) ?>"><?= $e($titulo) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="nominaCuerpo">
        <?php if ($filas === []): ?>
          <tr>
            <td colspan="<?= count($columnas) ?>" class="text-center text-muted py-4">
              No hay estudiantes que coincidan con los filtros.
            </td>
          </tr>
        <?php else: ?>
          <?php /* GRUPOS: una celda de estudiante y una de representante por alumno,
                   con rowspan sobre el número de autorizados. El nombre del alumno
                   y el del representante salen una sola vez, arriba de su grupo. */ ?>
          <?php foreach ($filas as $i => $f):
              $esAutorizados = ($vista === 'autorizados');
              $autorizados = $f['autorizados'] ?? [];
              $nFilas = count($autorizados) > 0 ? count($autorizados) : 1;
              /* Cada persona autorizada ocupa una fila; el alumno sin ninguno
                 ocupa una sola, que dice que solo el representante puede retirar. */
              $grupos = $autorizados !== [] ? $autorizados : [null];
              foreach ($grupos as $k => $aut):
                  $primera = ($k === 0);
              ?>
            <tr<?= $primera && $esAutorizados ? ' class="nomina-grupo-inicio"' : '' ?>>
              <?php foreach ($columnas as $clave => $titulo):
                  /* Solo el estudiante se agrupa: la columna Representante legal ya
                     no está en esta vista. */
                  $celdaGrupo = $esAutorizados && $clave === 'estudiante';
                  $salta = $celdaGrupo && !$primera;
                  if ($salta) { continue; }
                  $atributos = $celdaGrupo
                      ? ' rowspan="' . $nFilas . '" class="nomina-col--' . $e($clave) . '"'
                      : ' class="nomina-col--' . $e($clave) . '"';
                  ?>
                <td<?= $atributos ?>>
                  <?php switch ($clave):
                    case 'n': ?>
                      <?= $i + 1 ?>
                      <?php break;
                    case 'cedula': ?>
                      <?= $f['documento'] !== '' ? $e($f['documento']) : '—' ?>
                      <?php break;
                    case 'estudiante': ?>
                      <?= $e($f['estudiante']) ?>
                      <?php break;
                    case 'sexo': ?>
                      <?= $f['sexo'] === 'F' ? 'F' : 'M' ?>
                      <?php break;
                    case 'fecha_nac': ?>
                      <?= $e($fmtFecha($f['fecha_nacimiento'])) ?>
                      <?php break;
                    case 'edad': ?>
                      <?= (int) $f['edad'] ?>
                      <?php break;
                    case 'representante': ?>
                      <?php if (!empty($f['representante'])): ?>
                        <div><?= $e($f['representante']) ?></div>
                        <?php if (!empty($f['parentesco'])): ?>
                          <div class="text-muted small">(<?= $e($f['parentesco']) ?>)</div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted">Sin representante</span>
                      <?php endif; ?>
                      <?php break;
                    case 'cedula_rep': ?>
                      <?= $f['rep_documento'] !== '' ? $e($f['rep_documento']) : '—' ?>
                      <?php break;
                    case 'firma': ?>
                      <span class="nomina-firma" aria-label="Espacio para firmar"></span>
                      <?php break;
                    case 'telefonos_rep': ?>
                      <?php if ($f['rep_telefonos'] === []): ?>
                        <span class="text-muted">Sin teléfono</span>
                      <?php else: ?>
                        <?php foreach ($f['rep_telefonos'] as $tel): ?>
                          <div><?= $telefonoConWa($tel, true) ?></div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                      <?php break;
                    case 'direccion_rep': ?>
                      <?php if (($f['rep_direccion'] ?? '') === ''): ?>
                        <span class="text-muted">Sin dirección</span>
                      <?php else: ?>
                        <?= $e($f['rep_direccion']) ?>
                      <?php endif; ?>
                      <?php break;
                    case 'autorizado': ?>
                      <?php if ($aut === null): ?>
                        <span class="text-muted">Solo el representante</span>
                      <?php else: ?>
                        <?= $e($aut['nombre']) ?>
                      <?php endif; ?>
                      <?php break;
                    case 'parentesco_aut': ?>
                      <?php if ($aut === null): ?>
                        <span class="text-muted">—</span>
                      <?php elseif ($aut['parentesco'] !== ''): ?>
                        <?= $e($aut['parentesco']) ?>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                      <?php break;
                    case 'telefono_aut': ?>
                      <?php if ($aut === null || $aut['telefonos'] === []): ?>
                        <div class="nomina-tel-linea">
                          <span class="nomina-tel-num text-muted">Sin teléfono</span>
                          <span class="nomina-tel-ico"></span>
                        </div>
                      <?php else: ?>
                        <?php foreach ($aut['telefonos'] as $tel): ?>
                          <?= $telefonoAutConWa($tel) ?>
                        <?php endforeach; ?>
                      <?php endif; ?>
                      <?php break;
                    case 'dia': ?>
                      <?= (int) $f['dia_cumple'] ?>
                      <?php break;
                    case 'edad_cumple': ?>
                      <?= (int) $f['edad_cumple'] ?> años
                      <?php /* Si el mes ya pasó, el cumpleaños también: se marca para
                               que el listado no insinúe que está por llegar. */ ?>
                      <?php if (!empty($f['cumple_ya_paso'])): ?>
                        <span class="nomina-cumple-ok">Ya cumplió</span>
                      <?php endif; ?>
                      <?php break;
                  endswitch; ?>
                </td>
              <?php endforeach; ?>
            </tr>
              <?php endforeach; /* una fila por persona autorizada */ ?>
          <?php endforeach; /* un grupo por estudiante */ ?>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php /* Motivo y fecha del acta. Antes iban dentro de un modal que se abría
       desde el botón del encabezado y que, además, salía en todas las pestañas.
       Ahora viven dentro de la propia pestaña del acta, que es donde se
       necesitan: el documento se arma con ellos y no con los de otra vista. Los
       ids se conservan porque docente.js los lee por nombre. */ ?>
<?php if ($vista === 'acta'): ?>
  <section class="card border-0 shadow-sm mt-3" id="panelActa">
    <div class="card-body">
      <div class="row g-3 align-items-start">
        <div class="col-12 col-md-8 col-lg-8">
          <label for="actaMotivo" class="form-label small fw-semibold">
            Motivo del acta <span class="text-danger">*</span>
          </label>
          <input type="text" class="form-control" id="actaMotivo" minlength="3" maxlength="120" required
                 value="<?= $e($motivoActa ?? '') ?>"
                 placeholder="Ej.: Entrega de boletines del primer momento pedagógico">
          <div class="invalid-feedback" id="actaMotivoError">
            Indica un motivo válido (de 3 a 120 caracteres, sin etiquetas HTML ni símbolos especiales).
          </div>
        </div>
        <div class="col-12 col-md-4 col-lg-4">
          <label for="actaFecha" class="form-label small fw-semibold">
            Fecha <span class="text-danger">*</span>
          </label>
          <input type="date" class="form-control" id="actaFecha"
                 value="<?= $e($fechaActa ?? ($fechaHoyIso ?? date('Y-m-d'))) ?>" required>
          <div class="invalid-feedback" id="actaFechaError">
            Indica una fecha válida.
          </div>
        </div>
      </div>
      <?php /* El botón que dispara la vista previa es «Exportar PDF», en el
             encabezado. Este bloque se queda solo con los dos datos: el acta
             como tal se muestra en el modal, no aquí. */ ?>
    </div>
  </section>
<?php endif; ?>

<?php endif; ?>
<?php
$pageContent = ob_get_clean();

if ($tieneSeccion) {
    /* Vista previa del documento en modal, para todas las pestañas por igual:
       «Visualizar documento» no baja nada, arma el PDF de la vista en curso y
       lo enseña aquí. Recién entonces aparece el enlace de descarga, que es el
       momento en que el docente decide. El acta pide además motivo y fecha, que
       van en su propia tarjeta y los valida el mismo JS. */
    ob_start();
    ?>
    <div class="student-record__pdf-preview d-none" id="contenedorPreviewDocumento">
      <iframe id="iframeVistaPreviaDocumento" title="Vista previa del documento"></iframe>
    </div>
    <?php
    $modalBody = ob_get_clean();

    ob_start();
    ?>
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
    <?php /* Imprimir va junto a Descargar y se destapa con él, por el mismo motivo:
             la vista previa es el momento en que el docente decide, y antes de
             revisarla no hay nada que imprimir. Imprime el iframe de la propia
             vista previa, no una descarga aparte: el mismo documento que acaba
             de ver. */ ?>
    <button type="button" class="btn btn-outline-secondary d-none" id="btnImprimirDocumentoPdf">
      <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir
    </button>
    <a href="#" id="btnDescargarDocumentoPdf" class="btn btn-primary d-none" target="_blank" rel="noopener">
      <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF
    </a>
    <?php
    $modalFooter   = ob_get_clean();
    $modalId       = 'modalVistaPreviaDocumento';
    $modalTitle    = $tituloVista;
    $modalSubtitle = $textoSeccion . ' · ' . ($seccion['anio_escolar'] ?? '');
    $modalSize     = 'xl';
    $modalCentered = false;

    ob_start();
    require __DIR__ . '/../components/modal.php';
    $pageModals = ($pageModals ?? '') . ob_get_clean();
}

if ($tieneSeccion && $vista === 'contacto') {
    $enlaceWaActual = trim((string) ($seccion['enlace_whatsapp'] ?? ''));

    ob_start();
    ?>
    <form id="formEnlaceWhatsapp" action="<?= BASE_URL ?>docente/guardarEnlaceWhatsapp" method="post" novalidate>
      <div class="mb-3">
        <label for="inputEnlaceWhatsapp" class="form-label fw-semibold">
          Enlace de invitación de WhatsApp
        </label>
        <input type="url" class="form-control" id="inputEnlaceWhatsapp" name="enlace_whatsapp"
               maxlength="120" value="<?= $e($enlaceWaActual) ?>"
               placeholder="https://chat.whatsapp.com/...">
        <div class="invalid-feedback" id="errorEnlaceWhatsapp">
          El enlace debe comenzar por https://chat.whatsapp.com/ seguido del código de invitación, sin espacios.
        </div>
        <div class="form-text mt-2">
          En WhatsApp abre la información del grupo, toca <strong>Enlace de invitación</strong>, copia el enlace y pégalo aquí.
        </div>
      </div>
    </form>
    <?php
    $modalBody = ob_get_clean();

    ob_start();
    ?>
    <?php if ($enlaceWaActual !== ''): ?>
      <button type="button" class="btn btn-outline-danger me-auto" id="btnQuitarEnlaceWhatsapp">
        <i class="bi bi-trash me-1" aria-hidden="true"></i>Quitar enlace
      </button>
    <?php endif; ?>
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="submit" form="formEnlaceWhatsapp" class="btn btn-primary" id="btnGuardarEnlaceWhatsapp">
      Guardar enlace
    </button>
    <?php
    $modalFooter   = ob_get_clean();
    $modalId       = 'modalEnlaceWhatsapp';
    $modalTitle    = $enlaceWaActual !== '' ? 'Editar enlace del grupo de WhatsApp' : 'Agregar enlace del grupo de WhatsApp';
    $modalSubtitle = $textoSeccion . ' · ' . ($seccion['anio_escolar'] ?? '');
    $modalSize     = 'md';
    $modalCentered = true;

    ob_start();
    require __DIR__ . '/../components/modal.php';
    $pageModals = ($pageModals ?? '') . ob_get_clean();
}

/* docente.js: búsqueda de la sección y botón del acta. */
$extraScripts = array_merge($extraScripts ?? [], ['/js/modules/docente/docente.js']);
include __DIR__ . '/../layouts/app.php';
