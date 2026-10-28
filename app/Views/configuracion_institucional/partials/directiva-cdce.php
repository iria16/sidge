<?php
declare(strict_types=1);

/**
 * Partial: configuracion_institucional/partials/directiva-cdce.php
 * Autoridad CDCE estadal y sus recursos privados (firma y sello).
 *
 * @var array<string, string> $config
 * @var bool   $tieneFirmaCdce
 * @var string $firmaCdceUrl
 * @var bool   $tieneSelloCdce
 * @var string $selloCdceUrl
 */
?>
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
