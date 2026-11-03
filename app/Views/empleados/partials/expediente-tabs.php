<?php
declare(strict_types=1);

/**
 * Fragmento: empleados/partials/expediente-tabs.php
 * Secciones del expediente del empleado (estilo uniforme con expediente del estudiante).
 *
 * @var array $empleado
 * @var array $formacion
 * @var array $asignaciones
 * @var array $reposos
 * @var array $historial
 * @var bool  $puedeGestionar
 */
$fecha = static fn(?string $valor): string => $valor ? date('d/m/Y', strtotime($valor)) : '—';
$sexo  = ['F' => 'Femenino', 'M' => 'Masculino'][(string) ($empleado['sexo'] ?? '')] ?? '—';

$reposoVigente = null;
foreach ($reposos as $reposo) {
    if (!empty($reposo['vigente'])) {
        $reposoVigente = $reposo;
        break;
    }
}

$badgesReposo = [
    'VIGENTE' => ['Vigente', 'pending'],
    'CERRADO' => ['Cerrado', 'inactive'],
    'ANULADO' => ['Anulado', 'info'],
];
?>
<ul class="nav nav-tabs student-record__tabs" id="expedienteTabs" role="tablist" aria-label="Secciones del expediente">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-personal-link" data-bs-toggle="tab" data-bs-target="#tab-personal" type="button" role="tab" aria-controls="tab-personal" aria-selected="true">
      <i class="bi bi-person-vcard me-1" aria-hidden="true"></i> Datos personales
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-laboral-link" data-bs-toggle="tab" data-bs-target="#tab-laboral" type="button" role="tab" aria-controls="tab-laboral" aria-selected="false">
      <i class="bi bi-briefcase me-1" aria-hidden="true"></i> Datos laborales
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-formacion-link" data-bs-toggle="tab" data-bs-target="#tab-formacion" type="button" role="tab" aria-controls="tab-formacion" aria-selected="false">
      <i class="bi bi-mortarboard me-1" aria-hidden="true"></i> Formación
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-asignaciones-link" data-bs-toggle="tab" data-bs-target="#tab-asignaciones" type="button" role="tab" aria-controls="tab-asignaciones" aria-selected="false">
      <i class="bi bi-person-video3 me-1" aria-hidden="true"></i> Asignaciones
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-reposos-link" data-bs-toggle="tab" data-bs-target="#tab-reposos" type="button" role="tab" aria-controls="tab-reposos" aria-selected="false">
      <i class="bi bi-heart-pulse me-1" aria-hidden="true"></i> Reposos
      <?php if ($reposoVigente !== null): ?>
        <span class="badge rounded-pill bg-warning text-dark ms-1">1</span>
      <?php endif; ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-historial-link" data-bs-toggle="tab" data-bs-target="#tab-historial" type="button" role="tab" aria-controls="tab-historial" aria-selected="false">
      <i class="bi bi-clock-history me-1" aria-hidden="true"></i> Historial de estados
    </button>
  </li>
</ul>

<div class="student-record__layout mt-3">
  <div class="tab-content" id="expedienteTabsContent">
    <!-- 1. DATOS PERSONALES -->
    <div class="tab-pane fade show active" id="tab-personal" role="tabpanel" aria-labelledby="tab-personal-link" tabindex="0">
      <section class="content-card student-record__section" id="datosPersonales" aria-labelledby="datosPersonalesHeading">
        <div class="content-card__header d-flex justify-content-between align-items-center">
          <h2 class="content-card__title" id="datosPersonalesHeading">
            <i class="bi bi-person-vcard me-2" aria-hidden="true"></i>Información básica y de contacto
          </h2>
          <?php if ($puedeGestionar): ?>
            <button type="button" class="btn btn-sm btn-outline-primary editar-empleado"
                    data-empleado-id="<?= (int) $empleado['id_empleado'] ?>">
              <i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar empleado
            </button>
          <?php endif; ?>
        </div>
        <div class="student-record__fields">
          <div class="student-record__field">
            <span class="label-sigde">Documento de identidad</span>
            <strong><?= htmlspecialchars((string) ($empleado['tipo_documento'] ?? '') . '-' . (string) ($empleado['numero_documento'] ?? '')) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Fecha de nacimiento</span>
            <strong><?= htmlspecialchars($fecha($empleado['fecha_nacimiento'] ?? null)) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Sexo</span>
            <strong><?= htmlspecialchars($sexo) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Nacionalidad</span>
            <strong><?= htmlspecialchars((string) ($empleado['nacionalidad'] ?? '—')) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Teléfono principal</span>
            <strong><?= htmlspecialchars((string) ($empleado['telefono'] ?? '—')) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Teléfono alternativo</span>
            <strong><?= htmlspecialchars((string) (($empleado['telefono_alternativo'] ?? '') !== '' ? $empleado['telefono_alternativo'] : '—')) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Correo electrónico</span>
            <strong><?= htmlspecialchars((string) (($empleado['correo'] ?? '') !== '' ? $empleado['correo'] : '—')) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Estado</span>
            <strong><?= htmlspecialchars(strtoupper((string) ($empleado['estado'] ?? '')) === 'ACTIVO' ? 'Activo' : 'Inactivo') ?></strong>
          </div>
          <div class="student-record__field student-record__field--wide">
            <span class="label-sigde">Dirección de habitación</span>
            <strong><?= htmlspecialchars((string) (($empleado['direccion_completa'] ?? '') !== '' ? $empleado['direccion_completa'] : 'Sin dirección registrada')) ?></strong>
          </div>
        </div>
      </section>
    </div>

    <!-- 2. DATOS LABORALES -->
    <div class="tab-pane fade" id="tab-laboral" role="tabpanel" aria-labelledby="tab-laboral-link" tabindex="0">
      <section class="content-card student-record__section" id="datosLaborales" aria-labelledby="datosLaboralesHeading">
        <div class="content-card__header">
          <h2 class="content-card__title" id="datosLaboralesHeading">
            <i class="bi bi-briefcase me-2" aria-hidden="true"></i>Información laboral institucional
          </h2>
        </div>
        <div class="student-record__fields">
          <div class="student-record__field">
            <span class="label-sigde">Cargo asignado</span>
            <strong><?= htmlspecialchars((string) ($empleado['cargo'] ?? 'Sin cargo')) ?></strong>
          </div>
          <?php if (!empty($empleado['turno'])): ?>
            <div class="student-record__field">
              <span class="label-sigde">Turno de trabajo</span>
              <strong><?= htmlspecialchars((string) $empleado['turno']) ?></strong>
            </div>
          <?php endif; ?>
          <div class="student-record__field">
            <span class="label-sigde">Fecha de ingreso</span>
            <strong><?= htmlspecialchars($fecha($empleado['fecha_ingreso'] ?? null)) ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Estado laboral</span>
            <strong><?= htmlspecialchars(strtoupper((string) ($empleado['estado'] ?? '')) === 'ACTIVO' ? 'Activo' : 'Inactivo') ?></strong>
          </div>
        </div>
      </section>
    </div>

    <!-- 3. FORMACIÓN ACADÉMICA -->
    <div class="tab-pane fade" id="tab-formacion" role="tabpanel" aria-labelledby="tab-formacion-link" tabindex="0">
      <section class="content-card student-record__section" id="formacionAcademica" aria-labelledby="formacionAcademicaHeading">
        <div class="content-card__header">
          <h2 class="content-card__title" id="formacionAcademicaHeading">
            <i class="bi bi-mortarboard me-2" aria-hidden="true"></i>Formación académica y títulos
          </h2>
        </div>
        <?php if ($formacion === []): ?>
          <div class="content-card__empty">
            <i class="bi bi-journal-x" aria-hidden="true"></i>
            <p>No hay formación académica registrada para este empleado.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Título</th><th>Grado académico</th><th>Institución</th><th>Fecha de obtención</th></tr>
              </thead>
              <tbody>
                <?php foreach ($formacion as $fila): ?>
                  <tr>
                    <td><?= htmlspecialchars((string) $fila['titulo']) ?></td>
                    <td><?= htmlspecialchars((string) $fila['grado_academico']) ?></td>
                    <td><?= htmlspecialchars((string) $fila['institucion']) ?></td>
                    <td><?= htmlspecialchars($fecha($fila['fecha_obtencion'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <!-- 4. ASIGNACIONES DOCENTES -->
    <div class="tab-pane fade" id="tab-asignaciones" role="tabpanel" aria-labelledby="tab-asignaciones-link" tabindex="0">
      <section class="content-card student-record__section" id="asignacionesDocentes" aria-labelledby="asignacionesHeading">
        <div class="content-card__header">
          <h2 class="content-card__title" id="asignacionesHeading">
            <i class="bi bi-person-video3 me-2" aria-hidden="true"></i>Historial de asignaciones académicas
          </h2>
        </div>
        <?php if ($asignaciones === []): ?>
          <div class="content-card__empty">
            <i class="bi bi-journal-x" aria-hidden="true"></i>
            <p>No tiene asignaciones docentes registradas.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Período</th><th>Grado</th><th>Sección</th><th>Turno</th><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Estado</th></tr>
              </thead>
              <tbody>
                <?php foreach ($asignaciones as $asignacion): ?>
                  <tr>
                    <td><?= htmlspecialchars((string) $asignacion['periodo']) ?></td>
                    <td><?= htmlspecialchars((string) $asignacion['grado']) ?></td>
                    <td><?= htmlspecialchars((string) $asignacion['seccion']) ?></td>
                    <td><?= htmlspecialchars((string) $asignacion['turno']) ?></td>
                    <td><?= htmlspecialchars((string) $asignacion['tipo_asignacion']) ?></td>
                    <td><?= htmlspecialchars($fecha($asignacion['fecha_inicio'] ?? null)) ?></td>
                    <td><?= htmlspecialchars($fecha($asignacion['fecha_fin'] ?? null)) ?></td>
                    <td>
                      <?php
                      $badgeLabel  = !empty($asignacion['activa']) ? 'Activa' : 'Finalizada';
                      $badgeStatus = !empty($asignacion['activa']) ? 'active' : 'inactive';
                      include __DIR__ . '/../../components/status-badge.php';
                      ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <!-- 5. REPOSOS -->
    <div class="tab-pane fade" id="tab-reposos" role="tabpanel" aria-labelledby="tab-reposos-link" tabindex="0">
      <section class="content-card student-record__section" id="repososMedicos" aria-labelledby="repososHeading">
        <div class="content-card__header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h2 class="content-card__title mb-0" id="repososHeading">
            <i class="bi bi-heart-pulse me-2" aria-hidden="true"></i>Reposos y permisos médicos
          </h2>
          <?php if ($puedeGestionar): ?>
            <button type="button"
                    class="btn btn-sm btn-primary"
                    id="btnNuevoReposoExpediente"
                    data-empleado-id="<?= (int) $empleado['id_empleado'] ?>"
                    data-empleado-nombre="<?= htmlspecialchars((string) $empleado['nombre_completo']) ?>"
                    data-empleado-activo="<?= strtoupper((string) $empleado['estado']) === 'ACTIVO' ? '1' : '0' ?>">
              <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Registrar reposo
            </button>
          <?php endif; ?>
        </div>

        <?php if ($reposoVigente !== null): ?>
          <div class="info-alert mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
              <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="status-badge status-badge--pending">Reposo vigente</span>
                  <strong><?= htmlspecialchars($fecha($reposoVigente['fecha_inicio']) . ' al ' . $fecha($reposoVigente['fecha_fin'])) ?></strong>
                </div>
                <div class="text-support small">Motivo: <?= htmlspecialchars((string) (($reposoVigente['motivo'] ?? '') !== '' ? $reposoVigente['motivo'] : 'Sin motivo')) ?></div>
              </div>
              <?php if ($puedeGestionar): ?>
                <div class="d-flex gap-1">
                  <button type="button"
                          class="btn btn-sm btn-outline-danger btn-cerrar-reposo"
                          data-reposo-id="<?= (int) $reposoVigente['id_reposo'] ?>"
                          data-fecha-inicio="<?= htmlspecialchars((string) $reposoVigente['fecha_inicio']) ?>"
                          data-fecha-fin="<?= htmlspecialchars((string) ($reposoVigente['fecha_fin'] ?? '')) ?>"
                          data-empleado-nombre="<?= htmlspecialchars((string) $empleado['nombre_completo']) ?>">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Cerrar
                  </button>
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary btn-editar-reposo"
                          data-reposo-id="<?= (int) $reposoVigente['id_reposo'] ?>"
                          data-fecha-inicio="<?= htmlspecialchars((string) $reposoVigente['fecha_inicio']) ?>"
                          data-fecha-fin="<?= htmlspecialchars((string) ($reposoVigente['fecha_fin'] ?? '')) ?>"
                          data-motivo="<?= htmlspecialchars((string) ($reposoVigente['motivo'] ?? '')) ?>"
                          data-observacion="<?= htmlspecialchars((string) ($reposoVigente['observacion'] ?? '')) ?>">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                  </button>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr><th>Período</th><th>Motivo</th><th>Estado</th><th>Registrado por</th><th>Observación</th><?php if ($puedeGestionar): ?><th class="text-end">Acciones</th><?php endif; ?></tr>
            </thead>
            <tbody id="expedienteRepososBody">
              <?php if ($reposos === []): ?>
                <tr><td colspan="<?= $puedeGestionar ? 6 : 5 ?>" class="text-center text-support py-3">No hay reposos registrados.</td></tr>
              <?php else: ?>
                <?php foreach ($reposos as $reposo): ?>
                  <?php
                  $estadoReposo = strtoupper((string) ($reposo['estado'] ?? 'CERRADO'));
                  [$badgeLabel, $badgeStatus] = $badgesReposo[$estadoReposo] ?? ['—', 'info'];
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($fecha($reposo['fecha_inicio']) . ' — ' . $fecha($reposo['fecha_fin'])) ?></td>
                    <td><?= htmlspecialchars((string) (($reposo['motivo'] ?? '') !== '' ? $reposo['motivo'] : 'Sin motivo')) ?></td>
                    <td><?php include __DIR__ . '/../../components/status-badge.php'; ?></td>
                    <td><?= htmlspecialchars((string) ($reposo['registrado_por'] ?? 'Usuario eliminado')) ?></td>
                    <td class="text-support small"><?= htmlspecialchars((string) (($reposo['observacion'] ?? '') !== '' ? $reposo['observacion'] : '—')) ?></td>
                    <?php if ($puedeGestionar): ?>
                      <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                          <?php if ($estadoReposo === 'VIGENTE'): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-reposo"
                                    data-reposo-id="<?= (int) $reposo['id_reposo'] ?>"
                                    data-fecha-inicio="<?= htmlspecialchars((string) $reposo['fecha_inicio']) ?>"
                                    data-fecha-fin="<?= htmlspecialchars((string) ($reposo['fecha_fin'] ?? '')) ?>"
                                    data-motivo="<?= htmlspecialchars((string) ($reposo['motivo'] ?? '')) ?>"
                                    data-observacion="<?= htmlspecialchars((string) ($reposo['observacion'] ?? '')) ?>"
                                    title="Editar reposo"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-cerrar-reposo"
                                    data-reposo-id="<?= (int) $reposo['id_reposo'] ?>"
                                    data-fecha-inicio="<?= htmlspecialchars((string) $reposo['fecha_inicio']) ?>"
                                    data-fecha-fin="<?= htmlspecialchars((string) ($reposo['fecha_fin'] ?? '')) ?>"
                                    data-empleado-nombre="<?= htmlspecialchars((string) $empleado['nombre_completo']) ?>"
                                    title="Cerrar reposo"><i class="bi bi-check2-circle" aria-hidden="true"></i></button>
                          <?php endif; ?>
                          <?php if ($estadoReposo !== 'ANULADO'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-anular-reposo"
                                    data-reposo-id="<?= (int) $reposo['id_reposo'] ?>"
                                    data-empleado-nombre="<?= htmlspecialchars((string) $empleado['nombre_completo']) ?>"
                                    title="Anular reposo"><i class="bi bi-x-circle" aria-hidden="true"></i></button>
                          <?php endif; ?>
                        </div>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- 6. HISTORIAL DE ESTADOS -->
    <div class="tab-pane fade" id="tab-historial" role="tabpanel" aria-labelledby="tab-historial-link" tabindex="0">
      <section class="content-card student-record__section" id="historialEstados" aria-labelledby="historialEstadosHeading">
        <div class="content-card__header">
          <h2 class="content-card__title" id="historialEstadosHeading">
            <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Historial de cambios de estado
          </h2>
        </div>
        <?php if ($historial === []): ?>
          <div class="content-card__empty">
            <i class="bi bi-journal-x" aria-hidden="true"></i>
            <p>Todavía no hay cambios de estado registrados.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Fecha efectiva</th><th>Cambio</th><th>Motivo</th><th>Registrado por</th><th>Registrado el</th></tr>
              </thead>
              <tbody>
                <?php foreach ($historial as $cambio): ?>
                  <tr>
                    <td><?= htmlspecialchars($fecha($cambio['fecha_efectiva'] ?? null)) ?></td>
                    <td><?= htmlspecialchars((string) ($cambio['estado_anterior'] ?? '—') . ' → ' . (string) ($cambio['estado_nuevo'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) (($cambio['motivo'] ?? '') !== '' ? $cambio['motivo'] : '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($cambio['registrado_por'] ?? 'Usuario eliminado')) ?></td>
                    <td><?= htmlspecialchars($fecha($cambio['created_at'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
