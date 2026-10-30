<?php
/**
 * Partial: auth-footer.php
 * Pie de página de las pantallas de autenticación (login, recuperar-acceso,
 * cambio-password). No requiere variables externas.
 */
?>
<footer class="auth-footer text-center mt-5">
  <nav aria-label="Enlaces legales">
    <ul class="list-unstyled d-flex justify-content-center gap-4 mb-2">
      <li>
        <a href="#" role="button" data-bs-toggle="modal" data-bs-target="#modalPrivacidad">
          Privacidad
        </a>
      </li>
      <li>
        <a href="#" role="button" data-bs-toggle="modal" data-bs-target="#modalSoporte">
          Soporte
        </a>
      </li>
    </ul>
  </nav>
  <p class="mb-0">E.B. Isidro Ramírez &copy; <?php echo date('Y'); ?></p>
</footer>

<?php
// Modales definidos una sola vez para todas las pantallas de autenticación.
$modalId = 'modalPrivacidad';
$modalTitle = 'Privacidad y condiciones de uso';
$modalBody = '<p>Este sistema es de uso exclusivo del personal autorizado de la E.B. Isidro Ramírez para la gestión administrativa y académica de la escuela.</p>'
    . '<p>Se registran datos de estudiantes, representantes y personal, que son confidenciales. Se usan solo para fines escolares.</p>'
    . '<p>Cada usuario accede únicamente a la información que corresponde a su rol.</p>'
    . '<p>Las cuentas son personales e intransferibles. No compartas tu usuario ni tu contraseña.</p>'
    . '<p>Las acciones realizadas en el sistema quedan registradas en la bitácora.</p>'
    . '<p>Está prohibido copiar, difundir o usar los datos fuera de los fines institucionales.</p>';
$modalFooter = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Cerrar">Cerrar</button>';
include __DIR__ . '/../components/modal.php';

$configInstitucional = \App\Support\Configuracion::getAll();
$contacto = [];
if (!empty($configInstitucional['plantel_nombre'])) {
    $contacto[] = htmlspecialchars((string) $configInstitucional['plantel_nombre']);
}
$ubicacion = trim((string) (($configInstitucional['plantel_municipio'] ?? '') !== '' ? $configInstitucional['plantel_municipio'] : ''));
if (!empty($configInstitucional['plantel_parroquia'])) {
    $ubicacion = trim($ubicacion . ' — ' . $configInstitucional['plantel_parroquia']);
}
if ($ubicacion !== '') {
    $contacto[] = htmlspecialchars($ubicacion);
}
if (!empty($configInstitucional['plantel_telefono'])) {
    $contacto[] = 'Teléfono: ' . htmlspecialchars((string) $configInstitucional['plantel_telefono']);
}
if (!empty($configInstitucional['plantel_correo'])) {
    $contacto[] = 'Correo: ' . htmlspecialchars((string) $configInstitucional['plantel_correo']);
}
$soporteBody = '<p>Si olvidaste tu contraseña, usa "Olvidé mi contraseña" en el inicio de sesión. La solicitud llega al Administrador.</p>'
    . '<p>Si tu cuenta está bloqueada (3 intentos fallidos, bloqueo de 15 minutos), espera o contacta al Administrador.</p>'
    . '<p>Para otras consultas o fallas del sistema, comunícate con la institución.</p>';
if ($contacto !== []) {
    $soporteBody .= '<ul class="mb-0">';
    foreach ($contacto as $linea) {
        $soporteBody .= '<li>' . $linea . '</li>';
    }
    $soporteBody .= '</ul>';
} else {
    $soporteBody .= '<p class="text-support mb-0">No hay datos de contacto institucional registrados.</p>';
}

$modalId = 'modalSoporte';
$modalTitle = 'Soporte';
$modalBody = $soporteBody;
$modalFooter = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Cerrar">Cerrar</button>';
include __DIR__ . '/../components/modal.php';

unset($configInstitucional, $contacto, $ubicacion, $soporteBody, $modalId, $modalTitle, $modalBody, $modalFooter);
?>
