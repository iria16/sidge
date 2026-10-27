<?php
declare(strict_types=1);

/**
 * Encabezado institucional de 3 columnas para documentos PDF oficiales
 * (Constancias de estudio/inscripción, Ficha Integral del Estudiante y Ficha Integral del Empleado).
 *
 * Variables esperadas:
 * @var string|null $ministerioLogo           Data URI o ruta del logo ministerial
 * @var string|null $plantelLogo              Data URI o ruta del logo institucional
 * @var string      $nombrePlantel            Nombre del plantel educativo
 * @var string|null $plantelDea               Código DEA del plantel
 * @var string|null $codigoDependencia        Código de dependencia del plantel
 * @var bool|null   $mostrarCodigoDependencia Opcional (por defecto true)
 */
?>
<table class="header-table">
  <tr>
    <td class="col-min-logo">
      <?php if (!empty($ministerioLogo)): ?>
        <img src="<?= $ministerioLogo ?>" alt="Ministerio">
      <?php endif; ?>
    </td>
    <td class="col-box-center">
      <div class="membrete-texto">
        <div>REPÚBLICA BOLIVARIANA DE VENEZUELA</div>
        <div>MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</div>
        <div><?= htmlspecialchars(mb_strtoupper($nombrePlantel ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php if (!empty($plantelDea)): ?>
          <div>DEA: <?= htmlspecialchars(mb_strtoupper($plantelDea, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (($mostrarCodigoDependencia ?? true) && !empty($codigoDependencia)): ?>
          <div>CÓDIGO DE DEPENDENCIA <?= htmlspecialchars(mb_strtoupper($codigoDependencia, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
      </div>
    </td>
    <td class="col-plantel-logo">
      <?php if (!empty($plantelLogo)): ?>
        <img src="<?= $plantelLogo ?>" alt="Plantel">
      <?php endif; ?>
    </td>
  </tr>
</table>
