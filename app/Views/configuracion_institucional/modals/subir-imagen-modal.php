<?php

declare(strict_types=1);

/**
 * Modal: modals/subir-imagen-modal.php
 * Modal reutilizable para carga y actualización de recursos gráficos (logos, firmas, sellos, cintillo).
 *
 * Variables esperadas:
 * @var string $csrfToken
 */

ob_start();
?>
<form id="formSubirImagen">
  <input type="hidden" name="csrf" value="<?php echo e($csrfToken); ?>">
  <input type="hidden" id="subidaClave" name="clave" value="">

  <div class="mb-3">
    <label for="inputArchivoImagen" class="form-label fw-semibold">Seleccionar archivo</label>
    <input type="file"
           class="form-control"
           id="inputArchivoImagen"
           name="imagen"
           accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp"
           required>
    <div class="form-text" id="subidaRequisitosTexto">
      Formatos permitidos: PNG, JPG o WEBP. Tamaño máx: 2 MB. Ancho mínimo: 300 px.
    </div>
  </div>

  <!-- Previsualización en el modal -->
  <div class="d-none text-center p-3 border rounded transparency-pattern" id="contenedorModalPreview">
    <img id="imgModalPreview" class="modal-preview-image" src="" alt="Previsualización">
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formSubirImagen" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btnConfirmarSubida">
  <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
  <span>Subir y Guardar</span>
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalSubirImagen';
$modalTitle    = 'Actualizar Imagen';
$modalSubtitle = 'Seleccione el archivo digitalizado a subir al servidor.';

include __DIR__ . '/../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);
