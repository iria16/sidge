<?php

declare(strict_types=1);

/**
 * Vista: configuracion_institucional/index.php
 * Dashboard Ejecutivo de Gestión Institucional, Identidad Oficial y Directiva.
 *
 * Variables esperadas del controlador:
 * @var array<string, string> $config              Valores actuales de la tabla `configuracion_institucional`
 * @var array{error: ?string, success: ?string} $flash Mensajes flash de sesión
 * @var string                $csrfToken           Token CSRF de la sesión
 * @var bool                  $directorConfigurado Indica si nombre, cédula y cargo están completos
 * @var string                $mensajeDirector     Mensaje explicativo si falta algún dato
 * @var string                $ministerioLogoUrl   URL del logo ministerial (con ?v=)
 * @var string                $plantelLogoUrl      URL del logo del plantel (con ?v=)
 * @var bool                  $tieneCintillo       Indica si tiene franja tricolor activa
 * @var string                $plantelCintilloUrl  URL de la franja tricolor / cintillo de pie de página (con ?v=)
 * @var bool                  $esCintilloOficial   Indica si la franja activa es la oficial predeterminada
 * @var bool                  $tieneFirma          Indica si hay firma digitalizada guardada
 * @var string                $firmaUrl            URL protegida para preview de firma
 * @var bool                  $tieneSello          Indica si hay sello institucional guardado
 * @var string                $selloUrl            URL protegida para preview de sello
 * @var array<int, array<string, mixed>> $estados
 * @var array<int, array<string, mixed>> $municipios
 * @var array<int, array<string, mixed>> $parroquias
 * @var array<int, array{nombre: string, codigo: string, descripcion: string}> $nivelesEducativos
 */

$pageTitle   = 'Datos Institucionales';
$currentNav  = 'configuracion-institucional';

$breadcrumbs = [
    ['label' => 'Configuración', 'href' => null],
    ['label' => 'Datos Institucionales', 'href' => null],
];

// ---------- Contenido principal ----------
ob_start();
?>
<div class="container-fluid px-0">

  <!-- Encabezado de página -->
  <?php
  $pageHeading       = 'Datos Institucionales y Directiva';
  $pageSubheading    = 'Identidad oficial del plantel, membretes de constancias y datos de la autoridad directiva.';
  $pageHeaderActions = '';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <!-- Alerta de estado de la autoridad directiva -->
  <?php if (!$directorConfigurado): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm mb-4" role="alert" id="alertaDirectorIncompleto">
      <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h6 class="alert-heading fw-bold mb-1">Autoridad Directiva Incompleta</h6>
        <p class="mb-0 small text-dark">
          <?php echo e($mensajeDirector); ?>
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- Cabecera de Identidad Institucional (Banner de Perfil) -->
  <div class="institutional-banner p-4 mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
      
      <div class="d-flex align-items-center gap-3">
        <!-- Logo del Plantel -->
        <div class="school-logo-frame">
          <img id="header-plantel_logo"
               src="<?php echo e($plantelLogoUrl); ?>"
               alt="Logo del Plantel">
        </div>

        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h3 class="fw-bold text-dark mb-0 fs-4" id="header-plantel_nombre">
              <?php echo e($config['plantel_nombre'] ?? 'Plantel Sin Configurar'); ?>
            </h3>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-7" id="header-plantel_nombre_corto">
              <?php echo e($config['plantel_nombre_corto'] ?? 'E.B.'); ?>
            </span>
          </div>

          <div class="d-flex align-items-center gap-2 flex-wrap text-muted small mt-2">
            <span class="badge bg-white text-dark border font-monospace px-2 py-1">
              <i class="bi bi-upc-scan text-primary me-1"></i>DEA: <strong id="header-plantel_codigo_dea"><?php echo e(!empty($config['plantel_codigo_dea']) ? $config['plantel_codigo_dea'] : 'Sin asignar'); ?></strong>
            </span>
            <span class="badge bg-white text-secondary border px-2 py-1">
              <i class="bi bi-geo-alt-fill text-danger me-1"></i><span id="header-cdce_estado"><?php echo e($valEstado); ?></span>, <span id="header-plantel_municipio"><?php echo e($valMunicipio); ?></span>
            </span>
            <span class="badge bg-white text-dark border font-monospace px-2 py-1">
              <i class="bi bi-hash text-secondary me-1"></i>Dep: <span id="header-plantel_codigo_dependencia"><?php echo e($config['plantel_codigo_dependencia'] ?? '006562012'); ?></span>
            </span>
            <span class="badge bg-white text-primary border px-2 py-1">
              <i class="bi bi-mortarboard-fill me-1"></i><span id="header-nivel_educativo"><?php echo e($valNivel); ?></span> (<span id="header-nivel_codigo"><?php echo e($valCodigoNivel); ?></span>)
            </span>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
        <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 btn-abrir-subida" data-clave="plantel_logo" data-titulo="Logo del Plantel" data-privado="0">
          <i class="bi bi-camera"></i> Cambiar Logo
        </button>
        <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEditarPlantel">
          <i class="bi bi-pencil-square"></i>
          <span>Editar Datos Oficiales</span>
        </button>
      </div>

    </div>
  </div>

  <!-- Dashboard de Configuración Institucional (2 Columnas) -->
  <div class="row g-4">

    <!-- COLUMNA IZQUIERDA: Membrete Oficial y Ubicación Territorial -->
    <div class="col-lg-6 d-flex flex-column gap-4">

      <!-- Tarjeta 1: Membrete Oficial de la República y Ministerio -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-card-heading" aria-hidden="true"></i> Membrete Oficial de la República
          </h5>
          <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEditarMembrete">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            <span>Editar Membrete</span>
          </button>
        </div>
        <div class="card-body p-4">
          <div class="d-flex flex-column gap-3">
            <div class="p-3 bg-light rounded border">
              <div class="text-muted small fw-semibold text-uppercase">Línea 1 del Ministerio</div>
              <div class="fw-bold text-dark mt-1" id="view-ministerio_linea_1">
                <?php echo e($config['ministerio_linea_1'] ?? 'REPÚBLICA BOLIVARIANA DE VENEZUELA'); ?>
              </div>
            </div>
            <div class="p-3 bg-light rounded border">
              <div class="text-muted small fw-semibold text-uppercase">Línea 2 del Ministerio</div>
              <div class="fw-bold text-dark mt-1" id="view-ministerio_linea_2">
                <?php echo e($config['ministerio_linea_2'] ?? 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN'); ?>
              </div>
            </div>
            <div class="p-3 bg-light rounded border">
              <div class="text-muted small fw-semibold text-uppercase">Epígrafe Anual (Certificado de Educación Primaria)</div>
              <div class="fw-bold text-dark mt-1 small" id="view-certificado_epigrafe">
                <?php echo e($config['certificado_epigrafe'] ?? 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tarjeta 2: Ubicación Territorial y Dependencia -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Ubicación Territorial y Dependencia
          </h5>
          <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEditarPlantel">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            <span>Editar</span>
          </button>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Estado CDCE</div>
                <div class="fw-bold text-dark mt-1" id="view-cdce_estado">
                  <?php echo e($valEstado); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Municipio del Plantel</div>
                <div class="fw-bold text-dark mt-1" id="view-plantel_municipio">
                  <?php echo e($valMunicipio); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Parroquia del Plantel</div>
                <div class="fw-bold text-dark mt-1" id="view-plantel_parroquia">
                  <?php echo e($valParroquia); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Lugar de Expedición</div>
                <div class="fw-bold text-dark mt-1" id="view-lugar_expedicion">
                  <?php echo e($config['lugar_expedicion'] ?? 'Carabobo'); ?>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Código Dependencia</div>
                <div class="mt-1">
                  <span class="badge bg-white text-dark border font-monospace px-2 py-1 fs-6" id="view-plantel_codigo_dependencia">
                    <?php echo e($config['plantel_codigo_dependencia'] ?? '006562012'); ?>
                  </span>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="p-3 bg-light rounded border h-100">
                <div class="text-muted small fw-semibold text-uppercase">Nivel Educativo y MPPE</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                  <span class="fw-bold text-dark" id="view-nivel_educativo"><?php echo e($valNivel); ?></span>
                  <span class="badge bg-primary text-white font-monospace px-2 py-1" id="view-nivel_codigo">
                    <?php echo e($valCodigoNivel); ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- COLUMNA DERECHA: Autoridad Directiva y Recursos Gráficos -->
    <div class="col-lg-6 d-flex flex-column gap-4">

      <!-- Tarjeta 3: Autoridad Directiva y Firmante -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-person-vcard-fill" aria-hidden="true"></i> Autoridad Directiva y Firmante
          </h5>
          <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEditarDirectiva">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            <span>Editar Autoridad</span>
          </button>
        </div>
        <div class="card-body p-4">

          <!-- Datos de la Autoridad -->
          <div class="p-3 bg-light rounded border mb-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="text-muted small fw-semibold text-uppercase">Director(a) / Máxima Autoridad</div>
                <div class="fs-5 fw-bold text-dark mt-1" id="view-director_nombre">
                  <?php echo e($nombreCompletoDir !== '' ? $nombreCompletoDir : 'No configurado'); ?>
                </div>
              </div>
              <span id="badge-estado-director" class="badge <?php echo $directorConfigurado ? 'bg-success' : 'bg-warning text-dark'; ?>">
                <?php echo $directorConfigurado ? 'Configurado' : 'Incompleto'; ?>
              </span>
            </div>
            <div class="row g-2 mt-2">
              <div class="col-sm-6">
                <span class="text-muted small">Cédula:</span>
                <span class="badge bg-white text-primary border font-monospace ms-1" id="view-director_cedula">
                  <?php echo e(!empty($config['director_cedula']) ? $config['director_cedula'] : 'No registrada'); ?>
                </span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small">Cargo:</span>
                <strong class="text-dark ms-1" id="view-director_cargo">
                  <?php echo e(!empty($config['director_cargo']) ? $config['director_cargo'] : 'Director (E)'); ?>
                </strong>
              </div>
            </div>
          </div>

          <!-- Firma y Sello Privados -->
          <div class="row g-3">
            <!-- Firma -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Firma Digitalizada</span>
                    <span id="badge-director_firma" class="badge <?php echo $tieneFirma ? 'bg-success' : 'bg-secondary'; ?>">
                      <?php echo $tieneFirma ? 'Configurada' : 'Sin Firma'; ?>
                    </span>
                  </div>
                  <div class="private-preview-box transparency-pattern mb-2" id="box-director_firma">
                    <?php if ($tieneFirma): ?>
                      <img id="preview-director_firma"
                           src="<?php echo e($firmaUrl); ?>"
                           alt="Firma del Director">
                    <?php else: ?>
                      <div class="text-center text-secondary small py-2" id="placeholder-director_firma">
                        <i class="bi bi-pen fs-5 d-block mb-1 text-muted"></i>
                        <span class="small">Sin firma cargada</span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="director_firma"
                          data-titulo="Firma del Director"
                          data-privado="1">
                    <i class="bi bi-upload"></i> <?php echo $tieneFirma ? 'Cambiar' : 'Cargar'; ?>
                  </button>
                  <?php if ($tieneFirma): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                            id="btn-quitar-director_firma"
                            data-clave="director_firma"
                            data-titulo="Firma del Director"
                            data-privado="1">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Sello -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Sello Institucional</span>
                    <span id="badge-institucion_sello" class="badge <?php echo $tieneSello ? 'bg-success' : 'bg-secondary'; ?>">
                      <?php echo $tieneSello ? 'Configurado' : 'Sin Sello'; ?>
                    </span>
                  </div>
                  <div class="private-preview-box transparency-pattern mb-2" id="box-institucion_sello">
                    <?php if ($tieneSello): ?>
                      <img id="preview-institucion_sello"
                           src="<?php echo e($selloUrl); ?>"
                           alt="Sello Institucional">
                    <?php else: ?>
                      <div class="text-center text-secondary small py-2" id="placeholder-institucion_sello">
                        <i class="bi bi-award fs-5 d-block mb-1 text-muted"></i>
                        <span class="small">Sin sello cargado</span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="institucion_sello"
                          data-titulo="Sello Institucional"
                          data-privado="1">
                    <i class="bi bi-upload"></i> <?php echo $tieneSello ? 'Cambiar' : 'Cargar'; ?>
                  </button>
                  <?php if ($tieneSello): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                            id="btn-quitar-institucion_sello"
                            data-clave="institucion_sello"
                            data-titulo="Sello Institucional"
                            data-privado="1">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <!-- Datos de la Autoridad CDCE Estadal (Opcional para Prosecución y Certificado 6.° Grado) -->
          <div class="p-3 bg-light rounded border mb-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="text-muted small fw-semibold text-uppercase">Director(a) CDCE Estadal (Prosecución y Certificado 6.° Grado)</div>
                <div class="fs-6 fw-bold text-dark mt-1" id="view-cdce_director_nombre">
                  <?php echo e(!empty($config['cdce_director_nombre']) ? $config['cdce_director_nombre'] : 'En blanco (para llenado manual)'); ?>
                </div>
              </div>
              <span class="badge bg-light text-secondary border">Opcional</span>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-sm-6">
                <span class="text-muted small">Cédula CDCE:</span>
                <span class="badge bg-white text-primary border font-monospace ms-1" id="view-cdce_director_cedula">
                  <?php echo e(!empty($config['cdce_director_cedula']) ? $config['cdce_director_cedula'] : 'En blanco'); ?>
                </span>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small">Cargo:</span>
                <strong class="text-dark ms-1">DIRECTOR(A)</strong>
              </div>
            </div>
          </div>

          <!-- Firma y Sello Privados del CDCE Estadal -->
          <div class="row g-3">
            <!-- Firma CDCE -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Firma Director(a) CDCE</span>
                    <span id="badge-cdce_director_firma" class="badge <?php echo !empty($tieneFirmaCdce) ? 'bg-success' : 'bg-secondary'; ?>">
                      <?php echo !empty($tieneFirmaCdce) ? 'Configurada' : 'Sin Firma'; ?>
                    </span>
                  </div>
                  <div class="private-preview-box transparency-pattern mb-2" id="box-cdce_director_firma">
                    <?php if (!empty($tieneFirmaCdce)): ?>
                      <img id="preview-cdce_director_firma"
                           src="<?php echo e($firmaCdceUrl); ?>"
                           alt="Firma del Director CDCE">
                    <?php else: ?>
                      <div class="text-center text-secondary small py-2" id="placeholder-cdce_director_firma">
                        <i class="bi bi-pen fs-5 d-block mb-1 text-muted"></i>
                        <span class="small">Sin firma cargada</span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="cdce_director_firma"
                          data-titulo="Firma del Director CDCE"
                          data-privado="1">
                    <i class="bi bi-upload"></i> <?php echo !empty($tieneFirmaCdce) ? 'Cambiar' : 'Cargar'; ?>
                  </button>
                  <?php if (!empty($tieneFirmaCdce)): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                            id="btn-quitar-cdce_director_firma"
                            data-clave="cdce_director_firma"
                            data-titulo="Firma del Director CDCE"
                            data-privado="1">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Sello CDCE -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Sello CDCE Estadal</span>
                    <span id="badge-cdce_institucion_sello" class="badge <?php echo !empty($tieneSelloCdce) ? 'bg-success' : 'bg-secondary'; ?>">
                      <?php echo !empty($tieneSelloCdce) ? 'Configurado' : 'Sin Sello'; ?>
                    </span>
                  </div>
                  <div class="private-preview-box transparency-pattern mb-2" id="box-cdce_institucion_sello">
                    <?php if (!empty($tieneSelloCdce)): ?>
                      <img id="preview-cdce_institucion_sello"
                           src="<?php echo e($selloCdceUrl); ?>"
                           alt="Sello CDCE Estadal">
                    <?php else: ?>
                      <div class="text-center text-secondary small py-2" id="placeholder-cdce_institucion_sello">
                        <i class="bi bi-award fs-5 d-block mb-1 text-muted"></i>
                        <span class="small">Sin sello cargado</span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="cdce_institucion_sello"
                          data-titulo="Sello CDCE Estadal"
                          data-privado="1">
                    <i class="bi bi-upload"></i> <?php echo !empty($tieneSelloCdce) ? 'Cambiar' : 'Cargar'; ?>
                  </button>
                  <?php if (!empty($tieneSelloCdce)): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                            id="btn-quitar-cdce_institucion_sello"
                            data-clave="cdce_institucion_sello"
                            data-titulo="Sello CDCE Estadal"
                            data-privado="1">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Tarjeta 4: Recursos Gráficos Oficiales -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-images" aria-hidden="true"></i> Recursos Gráficos y Logotipos
          </h5>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">

            <!-- Logo Ministerial -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Logo del Ministerio</span>
                    <span class="badge bg-light text-secondary border">Público</span>
                  </div>
                  <div class="image-preview-box transparency-pattern mb-2">
                    <img id="preview-ministerio_logo"
                         src="<?php echo e($ministerioLogoUrl); ?>"
                         alt="Logo del Ministerio">
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="ministerio_logo"
                          data-titulo="Logo del Ministerio"
                          data-privado="0">
                    <i class="bi bi-upload"></i> Cambiar
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                          data-clave="ministerio_logo"
                          data-titulo="Logo del Ministerio"
                          data-privado="0"
                          title="Restablecer oficial">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Logo del Plantel -->
            <div class="col-sm-6">
              <div class="border rounded p-3 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark small">Logo del Plantel</span>
                    <span class="badge bg-light text-secondary border">Público</span>
                  </div>
                  <div class="image-preview-box transparency-pattern mb-2">
                    <img id="preview-plantel_logo"
                         src="<?php echo e($plantelLogoUrl); ?>"
                         alt="Logo del Plantel">
                  </div>
                </div>
                <div class="d-flex gap-1 mt-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 btn-abrir-subida"
                          data-clave="plantel_logo"
                          data-titulo="Logo del Plantel"
                          data-privado="0">
                    <i class="bi bi-upload"></i> Cambiar
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                          data-clave="plantel_logo"
                          data-titulo="Logo del Plantel"
                          data-privado="0"
                          title="Restablecer oficial">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Franja Tricolor (Cintillo) -->
            <div class="col-12">
              <div class="border rounded p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <span class="fw-semibold text-dark small">Franja Tricolor (Cintillo de Pie de Página)</span>
                    <div class="text-muted small">Cintillo inferior en las constancias oficiales</div>
                  </div>
                  <span id="badge-plantel_cintillo" class="badge <?php echo $tieneCintillo ? ($esCintilloOficial ? 'bg-info text-dark border' : 'bg-success') : 'bg-secondary'; ?>">
                    <?php echo $tieneCintillo ? ($esCintilloOficial ? 'Oficial' : 'Personalizada') : 'Sin Franja'; ?>
                  </span>
                </div>
                <div class="cintillo-preview-box mb-2" id="box-plantel_cintillo">
                  <?php if ($tieneCintillo): ?>
                    <img id="preview-plantel_cintillo"
                         src="<?php echo e($plantelCintilloUrl); ?>"
                         alt="Franja Tricolor">
                  <?php else: ?>
                    <div class="text-center text-secondary small py-1" id="placeholder-plantel_cintillo">
                      <i class="bi bi-slash-circle me-1 text-muted"></i> Sin franja inferior activa
                    </div>
                  <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                          id="btn-subir-plantel_cintillo"
                          data-clave="plantel_cintillo"
                          data-titulo="Franja Tricolor"
                          data-privado="0">
                    <i class="bi bi-upload"></i> <?php echo $tieneCintillo ? 'Cambiar Imagen' : 'Cargar Franja'; ?>
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen <?php echo $tieneCintillo ? '' : 'd-none'; ?>"
                          id="btn-quitar-plantel_cintillo"
                          data-clave="plantel_cintillo"
                          data-titulo="Franja Tricolor"
                          data-privado="0">
                    <i class="bi bi-trash"></i> Retirar
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-restablecer-oficial <?php echo (!$tieneCintillo || !$esCintilloOficial) ? '' : 'd-none'; ?>"
                          id="btn-oficial-plantel_cintillo"
                          data-clave="plantel_cintillo">
                    <i class="bi bi-arrow-counterclockwise"></i> Restablecer Oficial
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>

  </div>
</div>
<?php
$pageContent = ob_get_clean();

// ---------- Modales (uno por archivo, fuera del contenido) ----------
ob_start();
include __DIR__ . '/modals/editar-plantel-modal.php';
include __DIR__ . '/modals/editar-directiva-modal.php';
include __DIR__ . '/modals/editar-membrete-modal.php';
include __DIR__ . '/modals/subir-imagen-modal.php';
$pageModals = ob_get_clean();

// ---------- Scripts ----------
$extraScripts = ['/js/modules/institucional/configuracion-institucional.js'];

include __DIR__ . '/../layouts/app.php';
