<?php
declare(strict_types=1);

/**
 * Partial: dashboard/docente/partials/cumpleanos.php
 * Tarjeta destacada del cumpleañero del día y lista cronológica de cumpleaños del mes.
 *
 * Variables esperadas:
 * @var array $cumpleanos
 */

$birthdayData = $cumpleanos['cumpleanero_hoy'] ?? null;
$mesNombre    = (string) ($cumpleanos['mes_nombre'] ?? date('F'));
$cumpleMes    = $cumpleanos['cumpleanos_mes'] ?? [];
?>
<div class="row g-3 mb-4">
  <!-- 1. Tarjeta destacada Cumpleañera/o de Hoy -->
  <div class="col-12 col-lg-5">
    <?php include __DIR__ . '/../../../components/featured-birthday-card.php'; ?>
  </div>

  <!-- 2. Lista de Cumpleaños del Mes -->
  <div class="col-12 col-lg-7">
    <section class="content-card h-100" aria-labelledby="cumpleanosMesHeading">
      <div class="content-card__header d-flex align-items-center justify-content-between">
        <h2 class="content-card__title mb-0" id="cumpleanosMesHeading">
          <i class="bi bi-cake2 text-primary me-2" aria-hidden="true"></i>Cumpleañeros de <?= htmlspecialchars($mesNombre) ?>
        </h2>
        <span class="badge bg-light text-primary border fw-bold px-2 py-1 small">
          <?= count($cumpleMes) ?> <?= count($cumpleMes) === 1 ? 'ALUMNO' : 'ALUMNOS' ?>
        </span>
      </div>

      <div class="content-card__body p-3">
        <?php if (empty($cumpleMes)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-balloon fs-2 d-block mb-2 text-secondary" aria-hidden="true"></i>
            <p class="small mb-0">No hay cumpleaños registrados este mes en tu sección.</p>
          </div>
        <?php else: ?>
          <div class="birthday-list">
            <?php foreach ($cumpleMes as $item): ?>
              <?php
              $esHoy      = !empty($item['es_hoy']);
              $mesAbrev   = htmlspecialchars((string) ($item['mes_abrev'] ?? ''));
              $dia        = htmlspecialchars((string) ($item['dia'] ?? ''));
              $nombre     = htmlspecialchars((string) ($item['nombre'] ?? ''));
              $textoEdad  = htmlspecialchars((string) ($item['texto_edad'] ?? ''));
              $estado     = htmlspecialchars((string) ($item['estado'] ?? ''));
              $estadoTipo = htmlspecialchars((string) ($item['estado_tipo'] ?? 'default'));
              ?>
              <div class="birthday-list__item <?= $esHoy ? 'birthday-list__item--today' : '' ?>">
                <div class="birthday-list__main">
                  <div class="birthday-list__date-badge <?= $esHoy ? 'birthday-list__date-badge--today' : '' ?>">
                    <span class="birthday-list__month"><?= $mesAbrev ?></span>
                    <span class="birthday-list__day"><?= $dia ?></span>
                  </div>
                  <div class="birthday-list__info">
                    <div class="birthday-list__name"><?= $nombre ?></div>
                    <p class="birthday-list__age mb-0"><?= $textoEdad ?></p>
                  </div>
                </div>

                <span class="birthday-list__pill birthday-list__pill--<?= $estadoTipo ?>">
                  <?= $estado ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>