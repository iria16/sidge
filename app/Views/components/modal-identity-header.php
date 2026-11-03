<?php
declare(strict_types=1);

/**
 * Componente: app/Views/components/modal-identity-header.php
 * Encabezado de identidad centrado para modales de edición (Estudiante, Empleado, etc.).
 *
 * Muestra el avatar circular editable centrado (con botón de lápiz para cambiar foto),
 * el nombre completo, la insignia con el identificador (Cédula / Cédula Escolar)
 * y el botón para retirar la foto cuando exista una.
 *
 * @var string $identityPreviewId   ID del contenedor de preview (ej. 'fotoPreview' o 'editFotoPreview')
 * @var string $identityInputId     ID del input file (ej. 'fotoInput' o 'editFotoInput')
 * @var string $identityInputName   Name del input file (default 'foto')
 * @var string $identityNameId      ID del texto del nombre (ej. 'editNombreCompleto' o 'editEncabezadoNombre')
 * @var string $identityDocId       ID del texto del documento (ej. 'editCedulaId' o 'editEncabezadoDocumento')
 * @var string $identityQuitarId    ID del botón quitar foto (ej. 'btnQuitarFotoEstudiante' o 'btnQuitarFotoEdicion')
 * @var string $identityEliminarId  ID del input hidden eliminar foto (ej. 'fotoEliminar' o 'editEliminarFoto')
 * @var string $identityBadgeExtra  HTML opcional de insignias adicionales
 */
$identityPreviewId  = $identityPreviewId ?? 'fotoPreview';
$identityInputId    = $identityInputId ?? 'fotoInput';
$identityInputName  = $identityInputName ?? 'foto';
$identityNameId     = $identityNameId ?? 'editNombreCompleto';
$identityDocId      = $identityDocId ?? 'editCedulaId';
$identityQuitarId   = $identityQuitarId ?? 'btnQuitarFotoEstudiante';
$identityEliminarId = $identityEliminarId ?? 'fotoEliminar';
$identityBadgeExtra = $identityBadgeExtra ?? '';
?>
<div class="user-detail__header pt-3 pb-3 mb-0 border-bottom">
  <div class="wizard-photo-upload__circle">
    <div class="wizard-photo-upload__preview" id="<?= htmlspecialchars($identityPreviewId) ?>">
      <i class="bi bi-person"></i>
    </div>
    <label class="wizard-photo-upload__edit" for="<?= htmlspecialchars($identityInputId) ?>" title="Elegir foto">
      <i class="bi bi-pencil-fill"></i>
    </label>
    <input type="file" id="<?= htmlspecialchars($identityInputId) ?>" name="<?= htmlspecialchars($identityInputName) ?>" accept="image/*" class="d-none" data-foto-archivo>
    <input type="file" id="<?= htmlspecialchars($identityInputId) ?>Camara" accept="image/*" capture="environment" class="d-none" data-foto-camara-input data-foto-destino="<?= htmlspecialchars($identityInputId) ?>">
  </div>
  <div class="d-flex justify-content-center gap-2 mt-2">
    <label class="btn btn-outline-primary btn-sm mb-0" for="<?= htmlspecialchars($identityInputId) ?>Camara">
      <i class="bi bi-camera me-1" aria-hidden="true"></i>Tomar foto
    </label>
    <label class="btn btn-outline-secondary btn-sm mb-0" for="<?= htmlspecialchars($identityInputId) ?>" data-foto-elegir>
      <i class="bi bi-image me-1" aria-hidden="true"></i>Elegir foto
    </label>
  </div>
  <div class="user-detail__name mt-2" id="<?= htmlspecialchars($identityNameId) ?>">—</div>
  <div class="user-detail__meta mt-1">
    <span class="badge bg-light text-dark border">
      ID: <span id="<?= htmlspecialchars($identityDocId) ?>">—</span>
    </span>
    <?= $identityBadgeExtra ?>
  </div>
  <button type="button" class="btn btn-link btn-sm p-0 text-danger mt-1" id="<?= htmlspecialchars($identityQuitarId) ?>" hidden>
    <i class="bi bi-x-circle me-1"></i>Quitar foto
  </button>
  <input type="hidden" name="eliminar_foto" id="<?= htmlspecialchars($identityEliminarId) ?>" value="0">
</div>
