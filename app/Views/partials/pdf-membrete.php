<?php

/**
 * Membrete institucional de los documentos PDF.
 *
 * Es la misma pieza en todos los documentos oficiales: la constancia de
 * estudio, la de inscripción y la ficha integral del estudiante. Antes cada
 * documento tenía su propia copia de esta tabla de tres columnas, con el
 * nombre de la escuela y el código DEA escritos a mano dentro del marcado, así
 * que cambiar la escuela obligaba a tocar cada plantilla y era fácil que una
 * quedara con el nombre de otra.
 *
 * Aquí solo va la ESTRUCTURA y los DATOS. Las medidas (pt en la constancia, mm
 * en la ficha) viven en la hoja de estilos de cada documento, que es lo que
 * permite que la misma tabla mida distinto en Carta y en A4 sin duplicar
 * marcado.
 *
 * Variables esperadas:
 *   $membreteLineas      array<string>  Líneas del membrete, ya en su texto final.
 *   $membreteLogoMin     array|null     ['uri','width','height'] del ministerio.
 *   $membreteLogoPla     array|null     ['uri','width','height'] del plantel.
 *   $membreteTextoClase  string         Clase extra de la celda central, para que
 *                                        cada documento aplique su variante
 *                                        (recuadro, línea única, sin recuadro).
 *   $membreteAltEscuela  string|null    Texto alternativo del escudo del plantel.
 *
 * @var array       $membreteLineas
 * @var array|null  $membreteLogoMin
 * @var array|null  $membreteLogoPla
 * @var string      $membreteTextoClase
 * @var string|null $membreteAltEscuela
 */

declare(strict_types=1);

/* double_encode=false a propósito: el llamador puede incluir entidades ya
   escritas, como los &nbsp; que usa la constancia alrededor del separador de
   códigos, y con el escape por defecto saldrían impresas como texto. El dato
   viene de la configuración institucional y de etiquetas fijas del documento,
   no de texto que escriba el usuario. */
$escMembrete = static fn ($valor): string => htmlspecialchars(
    (string) ($valor ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
    'UTF-8',
    false
);
?>
<table class="pdf-membrete">
  <tr>
    <td class="pdf-membrete__ministerio">
      <?php if (!empty($membreteLogoMin)): ?>
        <img class="pdf-membrete__logo pdf-membrete__logo--ministerio"
             src="<?= $escMembrete($membreteLogoMin['uri']) ?>"
             width="<?= (int) $membreteLogoMin['width'] ?>"
             height="<?= (int) $membreteLogoMin['height'] ?>"
             alt="Ministerio del Poder Popular para la Educación">
      <?php endif; ?>
    </td>
    <td class="pdf-membrete__texto <?= $escMembrete($membreteTextoClase) ?>">
      <?php foreach ($membreteLineas as $membreteLinea): ?>
        <span class="pdf-membrete__linea"><?= $escMembrete($membreteLinea) ?></span>
      <?php endforeach; ?>
    </td>
    <td class="pdf-membrete__plantel">
      <?php if (!empty($membreteLogoPla)): ?>
        <img class="pdf-membrete__logo pdf-membrete__logo--plantel"
             src="<?= $escMembrete($membreteLogoPla['uri']) ?>"
             width="<?= (int) $membreteLogoPla['width'] ?>"
             height="<?= (int) $membreteLogoPla['height'] ?>"
             alt="<?= $escMembrete($membreteAltEscuela ?? 'Escuela') ?>">
      <?php endif; ?>
    </td>
  </tr>
</table>