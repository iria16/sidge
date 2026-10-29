<?php

/**
 * Componente: institucion-procedencia-form.php
 *
 * Bloque de procedencia de una institución: el interruptor de institución
 * extranjera y los dos caminos que dependen de él.
 *
 *   Nacional    estado, municipio y parroquia (los tres obligatorios con componente
 *               en cascada), y el código DEA en preescolar y escuela.
 *   Extranjera  solo selector de país obligatorio (tomado de la tabla pais).
 *               Se ocultan codigo_dea, estado, municipio y parroquia.
 *               Ciudad, dirección y código se omiten.
 *
 * Aparece en los cuatro sitios donde se registra una institución:
 * Catálogos (alta y edición), el wizard de empleados y el de inscripciones.
 *
 * Variables:
 * - $prefijo            id de cada control, sin sufijo: 'nuevoElemento',
 *                        'editarElemento', etc. Con eso el nombre también
 *                        sale: {prefijo}Estado -> name="id_estado_geo".
 * - $urlCascada         endpoint de SIGDEWizardUI.initCascadaUbicacion.
 * - $estadosGeo         catálogo [id_estado, nombre]
 * - $paises             catálogo [id_pais, nombre]
 *
 * Valores precargados (opcionales):
 * - $elExtranjera, $selEstado, $selMunicipio, $selParroquia, $selPais, $selDea
 */

declare(strict_types=1);

$provPrefijo      = $prefijo      ?? 'nuevaInstitucion';
$provUrlCascada   = $urlCascada   ?? '';
$provEstados      = $estadosGeo   ?? [];
$provPaises       = $paises       ?? [];
$provExtranjera   = !empty($elExtranjera);
$provEstado       = (int) ($selEstado     ?? 0);
$provMunicipio    = (int) ($selMunicipio  ?? 0);
$provParroquia    = (int) ($selParroquia  ?? 0);
$provPais         = (int) ($selPais       ?? 0);
$provDea          = (string) ($selDea     ?? '');
?>
<div class="mb-3">
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" role="switch"
           id="<?= htmlspecialchars($provPrefijo) ?>Extranjera" name="es_extranjera" value="1"
           <?= $provExtranjera ? 'checked' : '' ?>>
    <label class="form-check-label" for="<?= htmlspecialchars($provPrefijo) ?>Extranjera">
      ¿Institución extranjera?
    </label>
  </div>
</div>

<div data-bloque="nacional" class="<?= $provExtranjera ? 'd-none' : '' ?>">
  <div class="mb-3">
    <label for="<?= htmlspecialchars($provPrefijo) ?>Estado" class="form-label">
      Estado <span class="text-danger">*</span>
    </label>
    <select id="<?= htmlspecialchars($provPrefijo) ?>Estado" name="id_estado_geo" class="form-select" <?= $provExtranjera ? '' : 'required' ?>>
      <option value="">Seleccione el estado...</option>
      <?php foreach ($provEstados as $opcionEstado): ?>
        <option value="<?= (int) $opcionEstado['id_estado'] ?>"<?= $provEstado === (int) $opcionEstado['id_estado'] ? ' selected' : '' ?>><?= htmlspecialchars($opcionEstado['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="invalid-feedback">Selecciona el estado donde está la institución.</div>
  </div>

  <div class="mb-3">
    <label for="<?= htmlspecialchars($provPrefijo) ?>Municipio" class="form-label">
      Municipio <span class="text-danger">*</span>
    </label>
    <select id="<?= htmlspecialchars($provPrefijo) ?>Municipio" name="id_municipio" class="form-select" <?= $provExtranjera ? '' : 'required' ?>>
      <option value="">Seleccione el municipio...</option>
    </select>
    <div class="invalid-feedback">Selecciona primero el estado.</div>
  </div>

  <div class="mb-3">
    <label for="<?= htmlspecialchars($provPrefijo) ?>Parroquia" class="form-label">
      Parroquia <span class="text-danger">*</span>
    </label>
    <select id="<?= htmlspecialchars($provPrefijo) ?>Parroquia" name="id_parroquia" class="form-select" <?= $provExtranjera ? '' : 'required' ?>>
      <option value="">Seleccione la parroquia...</option>
    </select>
    <div class="invalid-feedback">Selecciona primero el municipio.</div>
  </div>

  <div class="mb-3" data-solo-tipo-estudiante>
    <label for="<?= htmlspecialchars($provPrefijo) ?>Dea" class="form-label">
      Código DEA <span class="text-danger">*</span>
    </label>
    <input type="text" id="<?= htmlspecialchars($provPrefijo) ?>Dea" name="codigo_dea" class="form-control"
           maxlength="20" autocomplete="off" placeholder="Ej: OD05281402"
           pattern="[A-Za-z0-9\-]{1,20}"
           value="<?= htmlspecialchars($provDea) ?>">
    <div class="invalid-feedback">Escribe el código DEA (solo letras, números y guion, máximo 20).</div>
    <div class="text-support mt-1" style="font-size: 0.75rem">
      Obligatorio en preescolar y escuela; en universidad no se pide.
    </div>
  </div>
</div>



<script>
(function () {
  var prefijo = <?= json_encode($provPrefijo) ?>;
  var urlCascada = <?= json_encode($provUrlCascada) ?>;
  var interruptor = document.getElementById(prefijo + 'Extranjera');
  if (!interruptor) return;

  var contenedor = interruptor.closest('form') || interruptor.closest('.modal-content') || document;

  function actualizarVisibilidad() {
    var esExt = interruptor.checked;
    var bNac = contenedor.querySelector('[data-bloque="nacional"]');
    var bExt = contenedor.querySelector('[data-bloque="extranjera"]');

    if (bNac) {
      bNac.classList.toggle('d-none', esExt);
      var inputsNac = bNac.querySelectorAll('select, input');
      for (var i = 0; i < inputsNac.length; i++) {
        var bloqueOculto = inputsNac[i].closest('.d-none');
        inputsNac[i].required = !esExt && !bloqueOculto;
        if (esExt && inputsNac[i].tagName === 'SELECT') inputsNac[i].value = '';
        if (esExt && inputsNac[i].tagName === 'INPUT') inputsNac[i].value = '';
      }
    }


  }

  interruptor.addEventListener('change', actualizarVisibilidad);

  if (urlCascada && typeof SIGDEWizardUI !== 'undefined' && SIGDEWizardUI.initCascadaUbicacion) {
    SIGDEWizardUI.initCascadaUbicacion({
      url: urlCascada,
      estadoId: prefijo + 'Estado',
      municipioId: prefijo + 'Municipio',
      parroquiaId: prefijo + 'Parroquia'
    });
  }
})();
</script>
<?php
unset(
    $provPrefijo, $urlCascada, $provEstados, $provPaises, $provExtranjera, $provEstado,
    $provMunicipio, $provParroquia, $provPais, $provDea
);
