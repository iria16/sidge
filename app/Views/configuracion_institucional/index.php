<?php

declare(strict_types=1);

/**
 * Vista: configuracion_institucional/index.php
 * Dashboard Ejecutivo de Gestión Institucional, Identidad Oficial y Directiva.
 *
 * Variables esperadas del controlador:
 * @var array<string, string> $config              Valores actuales de la tabla `configuracion_institucional`
 * @var array{error: ?string, success: ?string} $flash Mensajes flash de sesión
 * @var string                $csrfToken           Token CSRF de la sesión
 * @var bool                  $directorConfigurado Indica si nombre, cédula y cargo están completos
 * @var string                $mensajeDirector     Mensaje explicativo si falta algún dato
 * @var string                $ministerioLogoUrl   URL del logo ministerial (con ?v=)
 * @var string                $plantelLogoUrl      URL del logo del plantel (con ?v=)
 * @var bool                  $tieneCintillo       Indica si tiene franja tricolor activa
 * @var string                $plantelCintilloUrl  URL de la franja tricolor / cintillo de pie de página (con ?v=)
 * @var bool                  $esCintilloOficial   Indica si la franja activa es la oficial predeterminada
 * @var bool                  $tieneFirma          Indica si hay firma digitalizada guardada
 * @var string                $firmaUrl            URL protegida para preview de firma
 * @var bool                  $tieneSello          Indica si hay sello institucional guardado
 * @var string                $selloUrl            URL protegida para preview de sello
 * @var array<int, array<string, mixed>> $estados
 * @var array<int, array<string, mixed>> $municipios
 * @var array<int, array<string, mixed>> $parroquias
 * @var array<int, array{nombre: string, codigo: string, descripcion: string}> $nivelesEducativos
 * @var string                $nombreCompletoDir   Nombre completo del director(a)
 * @var string                $valEstado           Nombre del estado del plantel
 * @var string                $valMunicipio        Nombre del municipio del plantel
 * @var string                $valParroquia        Nombre de la parroquia del plantel
 * @var string                $valNivel            Nombre del nivel educativo
 * @var string                $valCodigoNivel      Código del nivel educativo
 * @var bool                  $tieneFirmaCdce      Indica si hay firma del director CDCE
 * @var string                $firmaCdceUrl        URL protegida de la firma CDCE
 * @var bool                  $tieneSelloCdce      Indica si hay sello del CDCE estadal
 * @var string                $selloCdceUrl        URL protegida del sello CDCE
 */

$pageTitle   = 'Datos Institucionales';
$currentNav  = 'configuracion-institucional';

$breadcrumbs = [
    ['label' => 'Configuración', 'href' => null],
    ['label' => 'Datos Institucionales', 'href' => null],
];

// ---------- Contenido principal ----------
ob_start();
?>
<div class="container-fluid px-0">

  <!-- Encabezado de página -->
  <?php
  $pageHeading       = 'Datos Institucionales y Directiva';
  $pageSubheading    = 'Identidad oficial del plantel, membretes de constancias y datos de la autoridad directiva.';
  $pageHeaderActions = '';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <?php include __DIR__ . '/partials/alerta-director.php'; ?>

  <?php include __DIR__ . '/partials/banner-identidad.php'; ?>

  <!-- Dashboard de Configuración Institucional (2 Columnas) -->
  <div class="row g-4">

    <!-- COLUMNA IZQUIERDA: Membrete Oficial y Ubicación Territorial -->
    <div class="col-lg-6 d-flex flex-column gap-4">
      <?php include __DIR__ . '/partials/tarjeta-membrete.php'; ?>
      <?php include __DIR__ . '/partials/tarjeta-ubicacion.php'; ?>
    </div>

    <!-- COLUMNA DERECHA: Autoridad Directiva y Recursos Gráficos -->
    <div class="col-lg-6 d-flex flex-column gap-4">
      <?php include __DIR__ . '/partials/tarjeta-directiva.php'; ?>
      <?php include __DIR__ . '/partials/tarjeta-recursos.php'; ?>
    </div>

  </div>
</div>
<?php
$pageContent = ob_get_clean();

// ---------- Modales (uno por archivo, fuera del contenido) ----------
ob_start();
include __DIR__ . '/modals/editar-plantel-modal.php';
include __DIR__ . '/modals/editar-directiva-modal.php';
include __DIR__ . '/modals/editar-membrete-modal.php';
include __DIR__ . '/modals/subir-imagen-modal.php';
$pageModals = ob_get_clean();

// ---------- Scripts ----------
$extraScripts = ['/js/modules/institucional/configuracion-institucional.js'];

include __DIR__ . '/../layouts/app.php';
