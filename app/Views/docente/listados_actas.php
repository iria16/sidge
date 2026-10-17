<?php
/**
 * Vista: docente/listados_actas.php
 * Interfaz profesional, limpia y sobria para Listados y Actas de Sección.
 *
 * Enfoque institucional:
 * - Sin elementos decorativos innecesarios (sin avatares, sin columnas de acciones superfluas).
 * - Tabla de datos precisa, legible y eficiente enfocada en la consulta directa.
 * - Barra superior con chips de contexto (Grado, Turno, Período) y botón unificado [ Exportar ].
 * - Pestañas de formato: Nómina Matriz [OFICIAL], Directorio y Contactos [FAMILIAR], Asistencia [ACTAS].
 * - Filtros ágiles de búsqueda, ordenamiento, género y selección de columnas.
 * - Resumen institucional al pie y modal Dompdf a demanda para emisión formal.
 *
 * @var array  $seccion
 * @var array  $estudiantes
 * @var string $formatoActual
 * @var string $ordenActual
 * @var string $generoActual
 * @var array  $opcionesColumnas
 * @var array  $seccionesDisponibles
 * @var array  $config
 * @var string $rolUsuario
 * @var bool   $esDocente
 * @var string|null $flash_error
 * @var string|null $flash_success
 * @var string|null $flash_info
 */

$gradoNombre   = htmlspecialchars($seccion['grado'] ?? '');
$seccionNombre = htmlspecialchars($seccion['seccion'] ?? '');
$turnoNombre   = htmlspecialchars($seccion['turno'] ?? '');
$anioNombre    = htmlspecialchars($seccion['anio_escolar'] ?? '');
$docenteNombre = htmlspecialchars($seccion['docente_nombre'] ?? 'Docente no asignado');
$idEstructura  = (int) ($seccion['id'] ?? 0);

// Estadísticas de asignación de cédula
$totalEstBase = count($estudiantes);
$conCedula = 0;
foreach ($estudiantes as $e) {
    if (!empty($e['numero_documento'])) {
        $conCedula++;
    }
}
$pctCedula = $totalEstBase > 0 ? (int) round(($conCedula / $totalEstBase) * 100) : 100;

// Metadatos de la vista para layout
$pageTitle       = "Listados y Actas - {$gradoNombre} \"{$seccionNombre}\"";
$pageDescription = "Gestión tabular de nóminas, datos de contacto y actas oficiales de la sección.";
$currentNav      = $esDocente ? 'mi-seccion-listados' : 'listado-estudiantes';

$breadcrumbs = $esDocente
    ? [
        ['label' => 'Mi sección', 'href' => null],
        ['label' => 'Listados y Actas', 'href' => null],
    ]
    : [
        ['label' => 'Gestión de estudiantes', 'href' => null],
        ['label' => 'Listados y Actas', 'href' => null],
        ['label' => "{$gradoNombre} \"{$seccionNombre}\"", 'href' => null],
    ];

ob_start();
?>

<!-- Estilos profesionales y limpios para la tabla de datos -->
<style>
/* Contenedor principal */
.actas-card-wrapper {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  padding: 1.5rem 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Chips de contexto */
.chip-meta {
  display: inline-flex;
  align-items: center;
  font-weight: 600;
  font-size: 0.78rem;
  padding: 3px 10px;
  border-radius: 20px;
}
.chip-meta--grado {
  background-color: #e0f2fe;
  color: #0369a1;
}
.chip-meta--turno {
  background-color: #f1f5f9;
  color: #475569;
}
.chip-meta--periodo {
  background-color: #fef3c7;
  color: #b45309;
}

/* Botón de exportación */
.btn-export-dark {
  background-color: #0f233a;
  color: #ffffff;
  border: none;
  font-weight: 600;
  font-size: 0.86rem;
  padding: 0.45rem 1.15rem;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  transition: all 0.15s ease-in-out;
}
.btn-export-dark:hover, .btn-export-dark:focus {
  background-color: #1e3a5f;
  color: #ffffff;
}

/* Pestañas de tipo de reporte */
.report-pills-nav {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.report-pill-btn {
  border: none;
  background: transparent;
  color: #64748b;
  font-weight: 600;
  font-size: 0.88rem;
  padding: 0.45rem 0.95rem;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  cursor: pointer;
  transition: all 0.15s ease;
}
.report-pill-btn:hover {
  background-color: #f8fafc;
  color: #0f172a;
}
.report-pill-btn.active {
  background-color: #0f233a;
  color: #ffffff;
}
.report-pill-badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.report-pill-btn.active .report-pill-badge {
  background-color: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}
.report-pill-btn:not(.active) .report-pill-badge {
  background-color: #f1f5f9;
  color: #64748b;
}

/* Barra de filtros */
.unified-filter-bar {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.55rem 0.85rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.search-box-unified {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  min-width: 250px;
}
.search-box-unified input {
  border: none;
  outline: none;
  font-size: 0.86rem;
  color: #1e293b;
  width: 100%;
  background: transparent;
}
.search-box-unified input::placeholder {
  color: #94a3b8;
}
.filter-select-unified {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.84rem;
  font-weight: 500;
  color: #334155;
  padding: 0.35rem 0.65rem;
  background-color: #ffffff;
}

/* Tabla institucional limpia */
.table-actas {
  width: 100%;
  border-collapse: collapse;
}
.table-actas th {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: #475569;
  padding: 0.75rem 0.75rem;
  border-bottom: 2px solid #cbd5e1;
  background: #f8fafc;
  vertical-align: middle;
}
.table-actas td {
  padding: 0.75rem 0.75rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
  font-size: 0.86rem;
  color: #1e293b;
}
.table-actas tbody tr:hover {
  background-color: #f8fafc;
}

/* Tipografías y etiquetas de datos */
.student-name-text {
  font-weight: 600;
  color: #0f172a;
}
.gender-badge-clean {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
}
.gender-badge-clean--f {
  background-color: #fce7f3;
  color: #be123c;
}
.gender-badge-clean--m {
  background-color: #e0e7ff;
  color: #3730a3;
}
.cell-mono {
  font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size: 0.84rem;
  font-weight: 600;
  color: #0f172a;
}
.tag-esc {
  background-color: #ede9fe;
  color: #5b21b6;
  font-size: 0.62rem;
  font-weight: 700;
  padding: 1px 4px;
  border-radius: 3px;
  margin-left: 4px;
}
.badge-retiro {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  background-color: #dcfce7;
  color: #15803d;
}

/* Barra inferior */
.bottom-summary-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding-top: 0.35rem;
}
.pill-stat {
  font-size: 0.78rem;
  font-weight: 600;
  padding: 4px 9px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.pill-stat--total {
  background-color: #f1f5f9;
  color: #334155;
}
.pill-stat--fem {
  background-color: #fdf2f8;
  color: #be123c;
}
.pill-stat--masc {
  background-color: #eff6ff;
  color: #1d4ed8;
}
.pill-stat--pct {
  background-color: #ecfdf5;
  color: #047857;
}

/* Modal Iframe */
.modal-iframe-box {
  height: 72vh;
  min-height: 520px;
  background-color: #525659;
  border-radius: 6px;
  overflow: hidden;
  position: relative;
}
.modal-iframe-frame {
  width: 100%;
  height: 100%;
  border: none;
}
.modal-iframe-spinner {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(255, 255, 255, 0.95);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  z-index: 10;
}
</style>

<div class="actas-card-wrapper">

  <!-- ==================== 1. ENCABEZADO Y EXPORTACIÓN ==================== -->
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
        <h1 class="h4 fw-bold text-dark mb-0">Listados y Actas de la Sección</h1>
        <span class="chip-meta chip-meta--grado"><?= $gradoNombre ?> "<?= $seccionNombre ?>"</span>
        <span class="chip-meta chip-meta--turno">Turno <?= $turnoNombre ?></span>
        <span class="chip-meta chip-meta--periodo">Período <?= $anioNombre ?></span>
      </div>
      <p class="text-secondary small mb-0">
        Gestión tabular de estudiantes matriculados, datos de contacto y emisión oficial de actas.
      </p>

      <?php if (count($seccionesDisponibles) > 1): ?>
        <div class="d-flex align-items-center gap-2 mt-2">
          <label for="selectorSeccion" class="small text-muted fw-semibold mb-0">Cambiar sección:</label>
          <select id="selectorSeccion" class="form-select form-select-sm" style="max-width: 280px;" onchange="ListadosApp.cambiarSeccion(this.value)">
            <?php foreach ($seccionesDisponibles as $secDisp): ?>
              <option value="<?= (int) $secDisp['id'] ?>" <?= ((int) $secDisp['id'] === $idEstructura) ? 'selected' : '' ?>>
                <?= htmlspecialchars($secDisp['grado']) ?> "<?= htmlspecialchars($secDisp['seccion']) ?>" (<?= htmlspecialchars($secDisp['turno']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>

    <!-- Menú Desplegable de Exportación -->
    <div class="dropdown">
      <button class="btn-export-dark dropdown-toggle" type="button" id="btnDropdownExportar" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download"></i>
        <span>Exportar</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="btnDropdownExportar" style="font-size: 0.88rem; min-width: 230px;">
        <li>
          <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:void(0)" onclick="ListadosApp.abrirModalPrevisualizacion()">
            <i class="bi bi-eye text-primary"></i>
            <span>Previsualizar PDF Oficial</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item d-flex align-items-center gap-2 py-2" id="dropdownDescargarPdf" href="#" onclick="ListadosApp.descargarPdfDirecto()">
            <i class="bi bi-file-earmark-pdf text-danger"></i>
            <span>Descargar PDF (Dompdf)</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item d-flex align-items-center gap-2 py-2" id="dropdownExportarCsv" href="#">
            <i class="bi bi-file-earmark-spreadsheet text-success"></i>
            <span>Exportar a Excel (CSV)</span>
          </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:void(0)" onclick="ListadosApp.imprimirDocumento()">
            <i class="bi bi-printer text-secondary"></i>
            <span>Imprimir Documento</span>
          </a>
        </li>
      </ul>
    </div>
  </div>

  <!-- ==================== 2. PESTAÑAS DE FORMATO ==================== -->
  <div class="report-pills-nav" role="tablist">
    <button type="button" class="report-pill-btn <?= ($formatoActual === 'nomina') ? 'active' : '' ?>" data-formato="nomina" onclick="ListadosApp.setFormato('nomina')" role="tab">
      <i class="bi bi-people"></i>
      <span>Nómina Matriz</span>
      <span class="report-pill-badge">OFICIAL</span>
    </button>
    <button type="button" class="report-pill-btn <?= ($formatoActual === 'contactos') ? 'active' : '' ?>" data-formato="contactos" onclick="ListadosApp.setFormato('contactos')" role="tab">
      <i class="bi bi-telephone"></i>
      <span>Directorio y Contactos</span>
      <span class="report-pill-badge">FAMILIAR</span>
    </button>
    <button type="button" class="report-pill-btn <?= ($formatoActual === 'asistencia') ? 'active' : '' ?>" data-formato="asistencia" onclick="ListadosApp.setFormato('asistencia')" role="tab">
      <i class="bi bi-check2-square"></i>
      <span>Asistencia a Reunión</span>
      <span class="report-pill-badge">ACTAS</span>
    </button>
  </div>

  <!-- ==================== 3. BARRA DE FILTROS ==================== -->
  <div class="unified-filter-bar">
    
    <!-- Búsqueda -->
    <div class="search-box-unified">
      <i class="bi bi-search text-secondary"></i>
      <input type="text" id="filtroBusqueda" placeholder="Buscar por estudiante, cédula..." oninput="ListadosApp.setBusqueda(this.value)">
      <i class="bi bi-x text-muted" onclick="ListadosApp.limpiarBusqueda()" title="Limpiar" style="cursor: pointer;"></i>
    </div>

    <!-- Selectores Orden y Género -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <select id="filtroOrden" class="filter-select-unified" onchange="ListadosApp.setOrden(this.value)">
        <option value="apellido" <?= ($ordenActual === 'apellido') ? 'selected' : '' ?>>Ordenar: Apellido (A - Z)</option>
        <option value="cedula" <?= ($ordenActual === 'cedula') ? 'selected' : '' ?>>Ordenar: Cédula</option>
        <option value="edad" <?= ($ordenActual === 'edad') ? 'selected' : '' ?>>Ordenar: Edad</option>
      </select>

      <select id="filtroGenero" class="filter-select-unified" onchange="ListadosApp.setGenero(this.value)">
        <option value="TODOS" <?= ($generoActual === 'TODOS') ? 'selected' : '' ?>>Género: Todos</option>
        <option value="F" <?= ($generoActual === 'F') ? 'selected' : '' ?>>Femenino (F)</option>
        <option value="M" <?= ($generoActual === 'M') ? 'selected' : '' ?>>Masculino (M)</option>
      </select>
    </div>

    <!-- Toggles de Columnas -->
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <span class="small text-muted fw-semibold">Columnas:</span>
      <div class="form-check form-check-inline mb-0">
        <input class="form-check-input" type="checkbox" id="chkColCedula" <?= !empty($opcionesColumnas['mostrar_cedula_escolar']) ? 'checked' : '' ?> onchange="ListadosApp.toggleColumna('cedulaEscolar', this.checked)">
        <label class="form-check-label small" for="chkColCedula">Cédula Escolar</label>
      </div>
      <div class="form-check form-check-inline mb-0">
        <input class="form-check-input" type="checkbox" id="chkColEdad" checked onchange="ListadosApp.toggleColumna('edadNac', this.checked)">
        <label class="form-check-label small" for="chkColEdad">Edad & Nac.</label>
      </div>
      <div class="form-check form-check-inline mb-0">
        <input class="form-check-input" type="checkbox" id="chkColRetiro" <?= !empty($opcionesColumnas['mostrar_retiro']) ? 'checked' : '' ?> onchange="ListadosApp.toggleColumna('retiro', this.checked)">
        <label class="form-check-label small" for="chkColRetiro">Retiro</label>
      </div>
    </div>

  </div>

  <!-- ==================== 4. ESTADO DE NÓMINA ==================== -->
  <div class="d-flex align-items-center justify-content-between px-1">
    <div class="d-flex align-items-center gap-2 small text-secondary">
      <i class="bi bi-shield-check text-success"></i>
      <span>Nómina oficial validada para emisión de actas</span>
    </div>
    <div class="small text-muted">
      Mostrando <strong id="lblMostrandoCant" class="text-dark">0</strong> de <strong id="lblTotalCant" class="text-dark">0</strong> registros
    </div>
  </div>

  <!-- ==================== 5. TABLA DE DATOS PROFESIONAL ==================== -->
  <div class="table-responsive border rounded-1">
    <table class="table-actas" id="tablaActasDinamica">
      <thead id="theadActas">
        <!-- Encabezados dinámicos vía JS -->
      </thead>
      <tbody id="tbodyActas">
        <!-- Filas dinámicas vía JS -->
      </tbody>
    </table>
  </div>

  <!-- ==================== 6. RESUMEN AL PIE ==================== -->
  <div class="bottom-summary-container">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="pill-stat pill-stat--total">
        Total: <strong id="statTotalVal"><?= $totalEstBase ?></strong>
      </span>
      <span class="pill-stat pill-stat--fem">
        Femenino: <strong id="statFemVal">0</strong>
      </span>
      <span class="pill-stat pill-stat--masc">
        Masculino: <strong id="statMascVal">0</strong>
      </span>
      <span class="pill-stat pill-stat--pct">
        <i class="bi bi-check2"></i> <span id="statPctVal"><?= $pctCedula ?>% Cédula Asignada</span>
      </span>
    </div>

    <div class="small text-secondary">
      Docente Guía: <strong class="text-dark"><?= $docenteNombre ?></strong>
      <span class="mx-2 text-muted">|</span>
      <span>SIGDE v2.0</span>
    </div>
  </div>

</div>

<?php
$pageContent = ob_get_clean();

// ==================== MODAL DE PREVISUALIZACIÓN DOMPDF ====================
ob_start();
?>
<div class="modal fade app-modal" id="modalPrevisualizarPdf" tabindex="-1" aria-labelledby="modalPrevisualizarPdfLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      
      <div class="modal-header">
        <div>
          <div class="d-flex align-items-center gap-2">
            <h5 class="modal-title fw-bold" id="modalPrevisualizarPdfLabel">Documento Oficial para Emisión</h5>
            <span class="badge bg-primary text-white" id="modalBadgeFormato">NÓMINA MATRIZ</span>
          </div>
          <p class="modal-header__subtitle mb-0 text-muted small">
            Formato Carta vertical oficial compilado con membrete del MPPE, paginación Canvas y firmas institucionales.
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body p-0">
        <div class="modal-iframe-box">
          <div class="modal-iframe-spinner" id="pdfModalLoading">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
            <span class="small fw-semibold text-secondary">Compilando documento legal con firmas digitales...</span>
          </div>
          <iframe id="iframePdfViewer" class="modal-iframe-frame" src="" onload="ListadosApp.onIframeLoaded()"></iframe>
        </div>
      </div>

      <div class="modal-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="small text-muted">
          <i class="bi bi-shield-check text-success me-1"></i> Documento oficial respaldado por SIGDE
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
            Cerrar
          </button>
          <a id="btnModalAbrirPestana" href="#" target="_blank" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-box-arrow-up-right"></i>
            <span>Abrir en pestaña</span>
          </a>
          <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="ListadosApp.imprimirIframe()">
            <i class="bi bi-printer"></i>
            <span>Imprimir</span>
          </button>
          <a id="btnModalDescargarPdf" href="#" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 fw-semibold">
            <i class="bi bi-file-earmark-arrow-down-fill"></i>
            <span>Descargar PDF</span>
          </a>
        </div>
      </div>

    </div>
  </div>
</div>
<?php
$pageModals = ob_get_clean();
?>

<!-- ==================== CONTROLADOR REACTIVO JS (ListadosApp) ==================== -->
<script>
const ListadosApp = (function() {
  const masterData = <?= json_encode($estudiantes, JSON_UNESCAPED_UNICODE) ?>;
  const idEstructura = <?= $idEstructura ?>;
  const baseUrl = <?= json_encode(BASE_URL) ?>;

  const state = {
    formato: <?= json_encode($formatoActual) ?>,
    orden: <?= json_encode($ordenActual) ?>,
    genero: <?= json_encode($generoActual) ?>,
    busqueda: '',
    columnas: {
      cedulaEscolar: <?= !empty($opcionesColumnas['mostrar_cedula_escolar']) ? 'true' : 'false' ?>,
      edadNac: true,
      retiro: <?= !empty($opcionesColumnas['mostrar_retiro']) ? 'true' : 'false' ?>
    },
    totalBase: masterData.length
  };

  const configFormatos = {
    nomina: {
      titulo: 'Nómina Matriz de Estudiantes',
      badge: 'NÓMINA MATRIZ'
    },
    contactos: {
      titulo: 'Directorio de Contactos y Familiares',
      badge: 'DIRECTORIO Y CONTACTOS'
    },
    asistencia: {
      titulo: 'Registro de Asistencia a Reunión',
      badge: 'CONTROL DE ASISTENCIA'
    }
  };

  let debounceTimer = null;
  let modalInstance = null;

  function setFormato(nuevoFormato) {
    if (!configFormatos[nuevoFormato] || state.formato === nuevoFormato) return;
    state.formato = nuevoFormato;

    document.querySelectorAll('.report-pill-btn').forEach(btn => {
      if (btn.dataset.formato === nuevoFormato) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

    renderTable();
    syncUrls();
  }

  function setOrden(nuevoOrden) {
    if (state.orden === nuevoOrden) return;
    state.orden = nuevoOrden;
    renderTable();
    syncUrls();
  }

  function setGenero(nuevoGenero) {
    if (state.genero === nuevoGenero) return;
    state.genero = nuevoGenero;
    renderTable();
    syncUrls();
  }

  function setBusqueda(texto) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      state.busqueda = (texto || '').trim().toLowerCase();
      renderTable();
    }, 120);
  }

  function limpiarBusqueda() {
    const input = document.getElementById('filtroBusqueda');
    if (input) input.value = '';
    state.busqueda = '';
    renderTable();
  }

  function toggleColumna(colKey, checked) {
    if (state.columnas[colKey] === checked) return;
    state.columnas[colKey] = checked;
    renderTable();
    syncUrls();
  }

  function cambiarSeccion(nuevoId) {
    if (!nuevoId || parseInt(nuevoId, 10) === idEstructura) return;
    const qs = buildQueryString();
    window.location.href = `${baseUrl}listadosSeccion/index?id=${nuevoId}&${qs}`;
  }

  function buildQueryString(extraParams = {}) {
    const params = new URLSearchParams();
    params.set('id', idEstructura);
    params.set('formato', state.formato);
    params.set('orden', state.orden);
    params.set('genero', state.genero);
    params.set('col_cedula', state.columnas.cedulaEscolar ? '1' : '0');
    params.set('col_telefono', '1');
    params.set('col_retiro', state.columnas.retiro ? '1' : '0');
    for (const [k, v] of Object.entries(extraParams)) {
      params.set(k, v);
    }
    return params.toString();
  }

  function getFiltrados() {
    let res = masterData.filter(est => {
      if (state.genero !== 'TODOS') {
        const g = (est.genero || est.sexo || '').toUpperCase();
        if (g !== state.genero) return false;
      }
      if (state.busqueda !== '') {
        const q = state.busqueda;
        const nombre = (est.apellidos_nombres || '').toLowerCase();
        const cedula = (est.numero_documento || '').toLowerCase();
        const rep = (est.rep_apellidos_nombres || '').toLowerCase();
        const repCed = (est.rep_cedula || '').toLowerCase();
        if (!nombre.includes(q) && !cedula.includes(q) && !rep.includes(q) && !repCed.includes(q)) {
          return false;
        }
      }
      return true;
    });

    res.sort((a, b) => {
      if (state.orden === 'cedula') {
        const numA = (a.numero_documento || '').replace(/\D/g, '');
        const numB = (b.numero_documento || '').replace(/\D/g, '');
        if (numA && numB) {
          const diff = parseInt(numA, 10) - parseInt(numB, 10);
          if (diff !== 0) return diff;
        }
        return (a.numero_documento || '').localeCompare(b.numero_documento || '');
      } else if (state.orden === 'edad') {
        const edadA = parseInt(a.edad_anios, 10) || 0;
        const edadB = parseInt(b.edad_anios, 10) || 0;
        if (edadA !== edadB) return edadA - edadB;
        return (a.apellidos_nombres || '').localeCompare(b.apellidos_nombres || '', 'es', { sensitivity: 'base' });
      } else {
        return (a.apellidos_nombres || '').localeCompare(b.apellidos_nombres || '', 'es', { sensitivity: 'base' });
      }
    });

    return res;
  }

  function renderTable() {
    const filtrados = getFiltrados();

    let countFem = 0;
    let countMasc = 0;
    let conCed = 0;
    filtrados.forEach(e => {
      const g = (e.genero || e.sexo || '').toUpperCase();
      if (g === 'F') countFem++;
      else if (g === 'M') countMasc++;
      if (e.numero_documento) conCed++;
    });

    const pct = filtrados.length > 0 ? Math.round((conCed / filtrados.length) * 100) : 100;

    // Actualizar conteos
    document.getElementById('lblMostrandoCant').textContent = filtrados.length;
    document.getElementById('lblTotalCant').textContent = state.totalBase;
    document.getElementById('statTotalVal').textContent = filtrados.length;
    document.getElementById('statFemVal').textContent = countFem;
    document.getElementById('statMascVal').textContent = countMasc;
    document.getElementById('statPctVal').textContent = `${pct}% Cédula Asignada`;

    const thead = document.getElementById('theadActas');
    const tbody = document.getElementById('tbodyActas');

    // Thead según formato (sin columna de acciones)
    if (state.formato === 'asistencia') {
      thead.innerHTML = `
        <tr>
          <th style="width: 45px; text-align: center;">N°</th>
          <th style="width: 250px;">ESTUDIANTE</th>
          <th style="width: 150px;">CÉDULA / ESCOLAR</th>
          <th>REPRESENTANTE LEGAL</th>
          <th style="width: 140px;">C.I. REPRESENTANTE</th>
          <th style="width: 170px; text-align: center;">FIRMA / HUELLA</th>
          <th>OBSERVACIONES</th>
        </tr>
      `;
    } else if (state.formato === 'contactos') {
      thead.innerHTML = `
        <tr>
          <th style="width: 45px; text-align: center;">N°</th>
          <th style="width: 250px;">ESTUDIANTE</th>
          <th style="width: 150px;">DOCUMENTO</th>
          <th>REPRESENTANTE LEGAL</th>
          <th style="width: 120px;">PARENTESCO</th>
          <th style="width: 140px;">TELÉFONO CELULAR</th>
          <th style="width: 140px;">TEL. ALTERNATIVO</th>
          <th>CORREO ELECTRÓNICO</th>
        </tr>
      `;
    } else {
      // Nómina Matriz
      thead.innerHTML = `
        <tr>
          <th style="width: 45px; text-align: center;">N°</th>
          <th style="width: 260px;">ESTUDIANTE</th>
          <th style="width: 150px;">CÉDULA ESCOLAR</th>
          ${state.columnas.edadNac ? '<th style="width: 160px;">F. NACIMIENTO / EDAD</th>' : ''}
          <th>REPRESENTANTE LEGAL</th>
          <th style="width: 140px;">C.I. REPRESENTANTE</th>
          ${state.columnas.retiro ? '<th style="width: 100px; text-align: center;">AUTORIZ. RETIRO</th>' : ''}
        </tr>
      `;
    }

    if (filtrados.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-muted">
            <em>No se encontraron estudiantes para los filtros seleccionados.</em>
          </td>
        </tr>
      `;
      return;
    }

    let rowsHtml = '';
    filtrados.forEach((est, idx) => {
      const num = String(idx + 1).padStart(2, '0');
      const sexo = (est.genero || est.sexo || '').toUpperCase();
      const esFem = (sexo === 'F');
      const badgeGenero = esFem 
        ? '<span class="gender-badge-clean gender-badge-clean--f">F</span>' 
        : '<span class="gender-badge-clean gender-badge-clean--m">M</span>';

      const fechaNac = formatFecha(est.fecha_nacimiento);
      const edadNum = est.edad_anios ? est.edad_anios : (est.edad ? est.edad : '—');
      const edadStr = `${edadNum} años`;

      const repNombre = est.rep_apellidos_nombres || 'Sin asignar';
      const parentesco = est.parentesco || 'Principal';
      const repCedula = est.rep_documento_etiqueta || 'S/I';
      const escolarBadge = (state.columnas.cedulaEscolar && (est.es_cedula_escolar || est.tipo_documento === 'CE')) ? '<span class="tag-esc">ESC</span>' : '';

      if (state.formato === 'asistencia') {
        rowsHtml += `
          <tr>
            <td class="text-center text-muted fw-semibold small">${num}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="student-name-text">${escapeHtml(est.apellidos_nombres)}</span>
                ${badgeGenero}
              </div>
            </td>
            <td><span class="cell-mono">${escapeHtml(est.documento_etiqueta)}</span>${escolarBadge}</td>
            <td>
              <span class="fw-semibold text-dark">${escapeHtml(repNombre)}</span>
              <span class="text-muted small ms-1">(${escapeHtml(parentesco)})</span>
            </td>
            <td><span class="cell-mono">${escapeHtml(repCedula)}</span></td>
            <td class="text-center">
              <div style="border-bottom: 1px dotted #94a3b8; height: 24px; width: 140px; margin: 0 auto;"></div>
            </td>
            <td class="text-muted small">—</td>
          </tr>
        `;
      } else if (state.formato === 'contactos') {
        let telAlt = (est.numero_telefono_alternativo || '').trim();
        if (telAlt && est.codigo_telefono_alternativo) {
          telAlt = `${est.codigo_telefono_alternativo}-${telAlt}`;
        } else if (!telAlt) {
          telAlt = '—';
        }
        const telPrincipal = est.telefono_completo || '—';
        const telLink = (telPrincipal !== '—' && telPrincipal !== 'No registrado') 
          ? `<a href="tel:${escapeHtml(telPrincipal)}" class="text-decoration-none fw-semibold cell-mono"><i class="bi bi-telephone me-1 text-primary"></i>${escapeHtml(telPrincipal)}</a>` 
          : '<span class="text-muted">—</span>';

        const correo = est.correo_electronico 
          ? `<a href="mailto:${escapeHtml(est.correo_electronico)}" class="text-decoration-none text-dark small"><i class="bi bi-envelope me-1 text-muted"></i>${escapeHtml(est.correo_electronico)}</a>` 
          : '<span class="text-muted">—</span>';

        rowsHtml += `
          <tr>
            <td class="text-center text-muted fw-semibold small">${num}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="student-name-text">${escapeHtml(est.apellidos_nombres)}</span>
                ${badgeGenero}
              </div>
            </td>
            <td><span class="cell-mono">${escapeHtml(est.documento_etiqueta)}</span>${escolarBadge}</td>
            <td><span class="fw-semibold text-dark">${escapeHtml(repNombre)}</span></td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(parentesco)}</span></td>
            <td>${telLink}</td>
            <td class="text-muted small">${escapeHtml(telAlt)}</td>
            <td>${correo}</td>
          </tr>
        `;
      } else {
        // Nómina Matriz
        const retiro = (est.autorizado_retirar || est.es_autorizado_retiro) 
          ? '<span class="badge-retiro">SÍ</span>' 
          : '<span class="text-muted">—</span>';

        rowsHtml += `
          <tr>
            <td class="text-center text-muted fw-semibold small">${num}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="student-name-text">${escapeHtml(est.apellidos_nombres)}</span>
                ${badgeGenero}
              </div>
            </td>
            <td><span class="cell-mono">${escapeHtml(est.documento_etiqueta)}</span>${escolarBadge}</td>
            ${state.columnas.edadNac ? `
              <td>
                <span class="text-dark fw-semibold">${fechaNac}</span>
                <span class="text-muted small ms-1">(${edadStr})</span>
              </td>
            ` : ''}
            <td>
              <span class="fw-semibold text-dark">${escapeHtml(repNombre)}</span>
              <span class="text-muted small ms-1">(${escapeHtml(parentesco)})</span>
            </td>
            <td><span class="cell-mono">${escapeHtml(repCedula)}</span></td>
            ${state.columnas.retiro ? `<td class="text-center">${retiro}</td>` : ''}
          </tr>
        `;
      }
    });

    tbody.innerHTML = rowsHtml;
  }

  function syncUrls() {
    const qs = buildQueryString();
    const csvUrl = `${baseUrl}listadosSeccion/exportarCsv?${qs}`;
    const pdfUrl = `${baseUrl}listadosSeccion/descargarPdf?${qs}`;

    const dropdownCsv = document.getElementById('dropdownExportarCsv');
    if (dropdownCsv) dropdownCsv.href = csvUrl;

    const dropdownPdf = document.getElementById('dropdownDescargarPdf');
    if (dropdownPdf) dropdownPdf.href = pdfUrl;

    const browserUrl = `${baseUrl}listadosSeccion/index?${qs}`;
    window.history.replaceState(null, '', browserUrl);
  }

  function abrirModalPrevisualizacion() {
    const qs = buildQueryString();
    const inlinePdfUrl = `${baseUrl}listadosSeccion/descargarPdf?${qs}&inline=1`;
    const downloadPdfUrl = `${baseUrl}listadosSeccion/descargarPdf?${qs}`;

    const iframe = document.getElementById('iframePdfViewer');
    const loading = document.getElementById('pdfModalLoading');
    const badgeFormato = document.getElementById('modalBadgeFormato');
    const btnAbrir = document.getElementById('btnModalAbrirPestana');
    const btnDescargar = document.getElementById('btnModalDescargarPdf');

    const fmtConfig = configFormatos[state.formato] || configFormatos.nomina;
    if (badgeFormato) badgeFormato.textContent = fmtConfig.badge;
    if (btnAbrir) btnAbrir.href = inlinePdfUrl;
    if (btnDescargar) btnDescargar.href = downloadPdfUrl;

    if (loading) loading.style.display = 'flex';
    if (iframe) iframe.src = inlinePdfUrl;

    const modalEl = document.getElementById('modalPrevisualizarPdf');
    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
      if (!modalInstance) {
        modalInstance = new bootstrap.Modal(modalEl);
      }
      modalInstance.show();
    }
  }

  function onIframeLoaded() {
    const loading = document.getElementById('pdfModalLoading');
    if (loading) loading.style.display = 'none';
  }

  function descargarPdfDirecto() {
    const qs = buildQueryString();
    window.location.href = `${baseUrl}listadosSeccion/descargarPdf?${qs}`;
  }

  function imprimirDocumento() {
    abrirModalPrevisualizacion();
  }

  function imprimirIframe() {
    const iframe = document.getElementById('iframePdfViewer');
    if (iframe && iframe.contentWindow) {
      try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      } catch (e) {
        const qs = buildQueryString();
        window.open(`${baseUrl}listadosSeccion/descargarPdf?${qs}&inline=1`, '_blank');
      }
    }
  }

  function formatFecha(isoStr) {
    if (!isoStr || isoStr === '0000-00-00') return '—';
    const partes = isoStr.split('-');
    if (partes.length === 3) return `${partes[2]}/${partes[1]}/${partes[0]}`;
    return isoStr;
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function init() {
    renderTable();
    syncUrls();
  }

  return {
    init,
    setFormato,
    setOrden,
    setGenero,
    setBusqueda,
    limpiarBusqueda,
    toggleColumna,
    cambiarSeccion,
    abrirModalPrevisualizacion,
    descargarPdfDirecto,
    imprimirDocumento,
    imprimirIframe,
    onIframeLoaded
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  ListadosApp.init();
});
ListadosApp.init();
</script>

<?php
require __DIR__ . '/../layouts/app.php';
