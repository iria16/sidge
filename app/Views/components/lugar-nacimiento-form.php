<?php

/**
 * Componente: lugar-nacimiento-form.php
 *
 * Entidad federal de nacimiento, y para el estudiante también municipio y parroquia.
 *
 * Variables:
 * - $pideMunicipioParroquiaNacimiento  solo el estudiante registra los tres niveles
 * - $estadosNacimiento  catálogo [id_estado, nombre]
 * - $nacimientoEstadoSeleccionado / $nacimientoParroquiaSeleccionada  valores precargados
 * - $idSeccionNacimiento  id opcional, para que el JavaScript esconda la sección entera
 * - $prefixId / $prefixName  como en persona-form.php
 *
 * Por qué es un componente aparte y no una sección más de persona-form.php: el
 * sub-modal de familiares lo incluye DESPUÉS de "Relación con el estudiante", porque
 * el control que lo revela —el parentesco y el interruptor de representante— está ahí. Si
 * se imprimiera dentro de persona-form.php quedaría por encima del control y se
 * revelaría fuera de la vista del modal.
 */
$pideMunicipioParroquiaNacimiento = $pideMunicipioParroquiaNacimiento ?? false;
$estadosNacimiento = $estadosNacimiento ?? ($estados ?? []);
$nacimientoEstadoSeleccionado = $nacimientoEstadoSeleccionado ?? 0;
$nacimientoParroquiaSeleccionada = $nacimientoParroquiaSeleccionada ?? 0;
$nacimientoExtranjero = $nacimientoExtranjero ?? false;
$lugarNacimientoExtranjero = $lugarNacimientoExtranjero ?? '';
$idSeccionNacimiento = $idSeccionNacimiento ?? '';
$prefixId = $prefixId ?? '';
$prefixName = $prefixName ?? '';

/* El required va en el marcado y lo QUITA el JavaScript cuando el bloque no aplica,
  que es lo mismo que hace el domicilio. Ponerlo solo por JavaScript sería un error:
  el formulario del estudiante no tiene ningún script que se lo vuelva a poner, y se
  quedaría sin la validación del navegador. */
$nacimientoRequired = $nacimientoRequired ?? true;
$reqAttr = $nacimientoRequired ? 'required' : '';
$reqClass = $nacimientoRequired ? '' : 'd-none';
$reqAttrVenezolano = $nacimientoExtranjero ? '' : $reqAttr;
$reqAttrExtranjero = $nacimientoExtranjero ? ' required' : '';

$idNacEstado = $prefixId ? $prefixId . 'EstadoNacimiento' : 'nacimientoEstado';
$idNacMunicipio = $prefixId ? $prefixId . 'MunicipioNacimiento' : 'nacimientoMunicipio';
$idNacParroquia = $prefixId ? $prefixId . 'ParroquiaNacimiento' : 'nacimientoParroquia';
$nameNacEstado = $prefixName ? $prefixName . 'id_estado_nacimiento' : 'id_estado_nacimiento';
$nameNacParroquia = $prefixName ? $prefixName . 'id_parroquia_nacimiento' : 'id_parroquia_nacimiento';
$nameNacMunicipio = $prefixName ? $prefixName . 'id_municipio_nacimiento_ui' : 'id_municipio_nacimiento_ui';
?>
<section class="wizard-summary__section"<?= $idSeccionNacimiento !== '' ? ' id="' . htmlspecialchars($idSeccionNacimiento) . '"' : '' ?> data-nacimiento-requerido="<?= $nacimientoRequired ? '1' : '0' ?>">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-pin-map" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Lugar de nacimiento</span>
  </div>
  <div class="wizard-summary__card">
    <?php if ($pideMunicipioParroquiaNacimiento): ?>
    <div class="row g-2">
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="nacimientoExtranjero" name="nacimiento_extranjero" value="1"<?= $nacimientoExtranjero ? ' checked' : '' ?>>
          <label class="form-check-label" for="nacimientoExtranjero">Nació en el extranjero</label>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="row g-2<?= ($pideMunicipioParroquiaNacimiento && $nacimientoExtranjero) ? ' d-none' : '' ?>" data-nacimiento-venezolano-grupo>
      <div class="<?= $pideMunicipioParroquiaNacimiento ? 'col-md-4' : 'col-md-12' ?>">
        <label class="label-sigde" for="<?= $idNacEstado ?>">Entidad federal<span data-requerido-nacimiento class="<?= $reqClass ?>"> *</span></label>
        <select class="form-select" id="<?= $idNacEstado ?>" name="<?= $nameNacEstado ?>" data-requerido-nacimiento <?= $reqAttrVenezolano ?>>
          <option value="">Seleccione la entidad federal</option>
          <?php foreach ($estadosNacimiento as $estado): ?>
            <option value="<?= (int) $estado['id_estado'] ?>"
                <?= (int) $nacimientoEstadoSeleccionado === (int) $estado['id_estado'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($estado['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="invalid-feedback">Seleccione la entidad federal donde nació.</div>
      </div>

      <?php /* El municipio no se guarda: se deduce de la parroquia. Por eso su name
             lleva el sufijo _ui, igual que en direccion-form.php, y ningún extractData
             lo lee. Se pregunta igual porque es lo que la secretaria sabe de memoria. */ ?>
      <?php if ($pideMunicipioParroquiaNacimiento): ?>
        <div class="col-md-4">
          <label class="label-sigde" for="<?= $idNacMunicipio ?>">Municipio<span data-requerido-nacimiento class="<?= $reqClass ?>"> *</span></label>
          <select class="form-select" id="<?= $idNacMunicipio ?>" name="<?= $nameNacMunicipio ?>"
                  data-depende-de="<?= $idNacEstado ?>"                   data-requerido-nacimiento <?= $reqAttrVenezolano ?>>
            <option value="">Seleccione el municipio</option>
          </select>
          <div class="invalid-feedback">Seleccione el municipio donde nació el alumno.</div>
        </div>
        <div class="col-md-4">
          <label class="label-sigde" for="<?= $idNacParroquia ?>">Parroquia<span data-requerido-nacimiento class="<?= $reqClass ?>"> *</span></label>
          <select class="form-select" id="<?= $idNacParroquia ?>" name="<?= $nameNacParroquia ?>"
                  data-depende-de="<?= $idNacMunicipio ?>" data-requerido-nacimiento <?= $reqAttrVenezolano ?>>
            <option value="">Seleccione la parroquia</option>
          </select>
          <div class="invalid-feedback">Seleccione la parroquia donde nació el alumno.</div>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($pideMunicipioParroquiaNacimiento): ?>
    <div class="row g-2<?= $nacimientoExtranjero ? '' : ' d-none' ?>" data-nacimiento-extranjero-grupo>
      <div class="col-12">
        <label class="label-sigde" for="lugarNacimientoExtranjero">Ciudad y país de nacimiento<span data-requerido-nacimiento class="<?= $reqClass ?>"> *</span></label>
        <input type="text" class="form-control" id="lugarNacimientoExtranjero" name="lugar_nacimiento_extranjero" maxlength="150" value="<?= htmlspecialchars((string) $lugarNacimientoExtranjero, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $reqAttrExtranjero ?>>
        <div class="invalid-feedback">Indique la ciudad y el país donde nació.</div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
