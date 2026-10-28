<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/tarjeta-directiva.php
 * Tarjeta 3: datos de la autoridad directiva y su firma/sello.
 * Incluye el bloque de la autoridad CDCE estadal.
 *
 * @var array<string, string> $config
 * @var bool   $directorConfigurado
 * @var string $nombreCompletoDir
 * @var bool   $tieneFirma
 * @var string $firmaUrl
 * @var bool   $tieneSello
 * @var string $selloUrl
 */
?>
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

          <?php include __DIR__ . '/directiva-cdce.php'; ?>

        </div>
      </div>