<?php

declare(strict_types=1);

/**
 * Vista: configuracion_institucional/index.php
 * Gestión de Parámetros Oficiales del Plantel, Directiva y Recursos Gráficos.
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
 */

$pageTitle   = 'Datos Institucionales';
$currentNav  = 'configuracion-institucional';

$breadcrumbs = [
    ['label' => 'Configuración', 'href' => null],
    ['label' => 'Datos Institucionales', 'href' => null],
];

ob_start();
?>

  <!-- El <style> que estaba aqui (72 lineas) duplicaba caracter por caracter
       las reglas .transparency-pattern, .image-preview-box,
       .cintillo-preview-box y .private-preview-box, que ya estan en
       resources/scss/components/_configuracion-institucional.scss y llegan
       compiladas en app.css. Se estaba mandando dos veces al navegador. -->



<div class="container-fluid px-0">

  <!-- Encabezado de página -->
  <?php
  $pageHeading       = 'Datos Institucionales y Directiva';
  $pageSubheading    = 'Configure la identidad oficial del plantel, membretes de constancias y datos de la autoridad directiva.';
  $pageHeaderActions = '';
  include __DIR__ . '/../components/page-header.php';
  ?>

  <!-- Alerta de estado de la autoridad directiva -->
  <?php if (!$directorConfigurado): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm mb-4" role="alert" id="alertaDirectorIncompleto">
      <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0" aria-hidden="true"></i>
      <div>
        <h6 class="alert-heading fw-bold mb-1">Autoridad Directiva Incompleta</h6>
        <p class="mb-0 small text-dark">
          Complete los campos de <strong>nombre, cédula y cargo</strong> del director(a) en el formulario a continuación para habilitar la emisión de constancias de estudio e inscripción en el sistema.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- COLUMNA IZQUIERDA: Formulario de Identificación y Directiva -->
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white border-bottom py-3">
          <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
            <i class="bi bi-bank" aria-hidden="true"></i> Identificación Oficial y Membretes
          </h5>
        </div>
        <div class="card-body p-4">

          <form id="formTextosInstitucion" novalidate>
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

            <!-- Bloque 1: Membrete Ministerial -->
            <div class="mb-4">
              <h6 class="text-uppercase text-secondary fw-bold small mb-3 border-bottom pb-1">
                Membrete Oficial de la República
              </h6>
              <div class="mb-3">
                <label for="ministerio_linea_1" class="form-label fw-semibold">
                  Línea 1 del Ministerio <span class="text-danger">*</span>
                </label>
                <input type="text"
                       class="form-control"
                       id="ministerio_linea_1"
                       name="ministerio_linea_1"
                       value="<?php echo htmlspecialchars($config['ministerio_linea_1'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       maxlength="200"
                       required>
                <div class="invalid-feedback" id="error-ministerio_linea_1"></div>
                <div class="form-text">Ejemplo: REPÚBLICA BOLIVARIANA DE VENEZUELA</div>
              </div>

              <div class="mb-3">
                <label for="ministerio_linea_2" class="form-label fw-semibold">
                  Línea 2 del Ministerio <span class="text-danger">*</span>
                </label>
                <input type="text"
                       class="form-control"
                       id="ministerio_linea_2"
                       name="ministerio_linea_2"
                       value="<?php echo htmlspecialchars($config['ministerio_linea_2'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       maxlength="200"
                       required>
                <div class="invalid-feedback" id="error-ministerio_linea_2"></div>
                <div class="form-text">Ejemplo: MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN</div>
              </div>
            </div>

            <!-- Bloque 2: Datos del Plantel Educativo -->
            <div class="mb-4">
              <h6 class="text-uppercase text-secondary fw-bold small mb-3 border-bottom pb-1">
                Identificación del Plantel
              </h6>
              <div class="mb-3">
                <label for="plantel_nombre" class="form-label fw-semibold">
                  Nombre Oficial Completo del Plantel <span class="text-danger">*</span>
                </label>
                <input type="text"
                       class="form-control"
                       id="plantel_nombre"
                       name="plantel_nombre"
                       value="<?php echo htmlspecialchars($config['plantel_nombre'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       maxlength="200"
                       required>
                <div class="invalid-feedback" id="error-plantel_nombre"></div>
                <div class="form-text">Denominación completa según el registro ministerial.</div>
              </div>

              <div class="row g-3">
                <div class="col-md-7">
                  <label for="plantel_nombre_corto" class="form-label fw-semibold">
                    Nombre Corto / Siglas <span class="text-danger">*</span>
                  </label>
                  <input type="text"
                         class="form-control"
                         id="plantel_nombre_corto"
                         name="plantel_nombre_corto"
                         value="<?php echo htmlspecialchars($config['plantel_nombre_corto'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         maxlength="100"
                         required>
                  <div class="invalid-feedback" id="error-plantel_nombre_corto"></div>
                  <div class="form-text">Ejemplo: E.B.N.B. ISIDRO RAMÍREZ</div>
                </div>

                <div class="col-md-5">
                  <label for="plantel_codigo_dea" class="form-label fw-semibold">
                    Código DEA
                  </label>
                  <input type="text"
                         class="form-control"
                         id="plantel_codigo_dea"
                         name="plantel_codigo_dea"
                         value="<?php echo htmlspecialchars($config['plantel_codigo_dea'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         maxlength="50">
                  <div class="invalid-feedback" id="error-plantel_codigo_dea"></div>
                  <div class="form-text">Ejemplo: OD05281402</div>
                </div>
              </div>
            </div>

            <!-- Bloque 3: Autoridad Directiva -->
            <div class="mb-4">
              <h6 class="text-uppercase text-secondary fw-bold small mb-3 border-bottom pb-1">
                Autoridad Directiva (Firmante en Constancias)
              </h6>
              <!-- Nombres del Director(a) -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="director_primer_nombre" class="form-label fw-semibold">
                    Primer Nombre <span class="text-danger">*</span>
                  </label>
                  <input type="text"
                         class="form-control"
                         id="director_primer_nombre"
                         name="director_primer_nombre"
                         value="<?php echo htmlspecialchars($dirPrimerNombre ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Tibisay"
                         maxlength="50">
                  <div class="invalid-feedback" id="error-director_primer_nombre"></div>
                </div>

                <div class="col-md-6">
                  <label for="director_segundo_nombre" class="form-label fw-semibold">
                    Segundo Nombre
                  </label>
                  <input type="text"
                         class="form-control"
                         id="director_segundo_nombre"
                         name="director_segundo_nombre"
                         value="<?php echo htmlspecialchars($dirSegundoNombre ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Coromoto"
                         maxlength="50">
                  <div class="invalid-feedback" id="error-director_segundo_nombre"></div>
                </div>
              </div>

              <!-- Apellidos del Director(a) -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="director_primer_apellido" class="form-label fw-semibold">
                    Primer Apellido <span class="text-danger">*</span>
                  </label>
                  <input type="text"
                         class="form-control"
                         id="director_primer_apellido"
                         name="director_primer_apellido"
                         value="<?php echo htmlspecialchars($dirPrimerApellido ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: López"
                         maxlength="50">
                  <div class="invalid-feedback" id="error-director_primer_apellido"></div>
                </div>

                <div class="col-md-6">
                  <label for="director_segundo_apellido" class="form-label fw-semibold">
                    Segundo Apellido
                  </label>
                  <input type="text"
                         class="form-control"
                         id="director_segundo_apellido"
                         name="director_segundo_apellido"
                         value="<?php echo htmlspecialchars($dirSegundoApellido ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Hernández"
                         maxlength="50">
                  <div class="invalid-feedback" id="error-director_segundo_apellido"></div>
                </div>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="director_cedula" class="form-label fw-semibold">
                    Cédula de Identidad
                  </label>
                  <input type="text"
                         class="form-control"
                         id="director_cedula"
                         name="director_cedula"
                         value="<?php echo htmlspecialchars($config['director_cedula'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: V-12.345.678"
                         maxlength="25">
                  <div class="invalid-feedback" id="error-director_cedula"></div>
                </div>

                <div class="col-md-6">
                  <label for="director_cargo" class="form-label fw-semibold">
                    Cargo Oficial
                  </label>
                  <input type="text"
                         class="form-control"
                         id="director_cargo"
                         name="director_cargo"
                         value="<?php echo htmlspecialchars($config['director_cargo'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Director (E)"
                         maxlength="100">
                  <div class="invalid-feedback" id="error-director_cargo"></div>
                </div>
              </div>
            </div>

            <!-- Bloque 4: Parámetros Oficiales para Constancias -->
            <div class="mb-4">
              <h6 class="text-uppercase text-secondary fw-bold small mb-3 border-bottom pb-1">
                Ubicación y Parámetros para Constancias
              </h6>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="plantel_municipio" class="form-label fw-semibold">
                    Municipio del Plantel
                  </label>
                  <input type="text"
                         class="form-control"
                         id="plantel_municipio"
                         name="plantel_municipio"
                         value="<?php echo htmlspecialchars($config['plantel_municipio'] ?? 'Valencia', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Valencia"
                         maxlength="200">
                  <div class="invalid-feedback" id="error-plantel_municipio"></div>
                </div>
                <div class="col-md-6">
                  <label for="plantel_parroquia" class="form-label fw-semibold">
                    Parroquia del Plantel
                  </label>
                  <input type="text"
                         class="form-control"
                         id="plantel_parroquia"
                         name="plantel_parroquia"
                         value="<?php echo htmlspecialchars($config['plantel_parroquia'] ?? 'Miguel Peña', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Miguel Peña"
                         maxlength="200">
                  <div class="invalid-feedback" id="error-plantel_parroquia"></div>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="cdce_estado" class="form-label fw-semibold">
                    Estado CDCE (Centro de Calidad Educativa)
                  </label>
                  <input type="text"
                         class="form-control"
                         id="cdce_estado"
                         name="cdce_estado"
                         value="<?php echo htmlspecialchars($config['cdce_estado'] ?? 'Carabobo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Carabobo"
                         maxlength="200">
                  <div class="invalid-feedback" id="error-cdce_estado"></div>
                </div>
                <div class="col-md-6">
                  <label for="plantel_codigo_dependencia" class="form-label fw-semibold">
                    Código de Dependencia
                  </label>
                  <input type="text"
                         class="form-control"
                         id="plantel_codigo_dependencia"
                         name="plantel_codigo_dependencia"
                         value="<?php echo htmlspecialchars($config['plantel_codigo_dependencia'] ?? '006562012', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: 006562012"
                         maxlength="50">
                  <div class="invalid-feedback" id="error-plantel_codigo_dependencia"></div>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="nivel_educativo" class="form-label fw-semibold">
                    Nivel Educativo
                  </label>
                  <input type="text"
                         class="form-control"
                         id="nivel_educativo"
                         name="nivel_educativo"
                         value="<?php echo htmlspecialchars($config['nivel_educativo'] ?? 'Educación Primaria', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: Educación Primaria"
                         maxlength="200">
                  <div class="invalid-feedback" id="error-nivel_educativo"></div>
                </div>
                <div class="col-md-6">
                  <label for="nivel_codigo" class="form-label fw-semibold">
                    Código de Nivel MPPE
                  </label>
                  <input type="text"
                         class="form-control"
                         id="nivel_codigo"
                         name="nivel_codigo"
                         value="<?php echo htmlspecialchars($config['nivel_codigo'] ?? '21000', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                         placeholder="Ej: 21000"
                         maxlength="50">
                  <div class="invalid-feedback" id="error-nivel_codigo"></div>
                </div>
              </div>

              <div class="mb-3">
                <label for="lugar_expedicion" class="form-label fw-semibold">
                  Lugar de Expedición en Constancias
                </label>
                <input type="text"
                       class="form-control"
                       id="lugar_expedicion"
                       name="lugar_expedicion"
                       value="<?php echo htmlspecialchars($config['lugar_expedicion'] ?? 'Carabobo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       placeholder="Ej: Carabobo"
                       maxlength="200">
                <div class="invalid-feedback" id="error-lugar_expedicion"></div>
                <div class="form-text">Texto que aparecerá en «Constancia que se expide en [Lugar]...».</div>
              </div>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top">
              <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 shadow-sm" id="btnGuardarTextos">
                <i class="bi bi-save" aria-hidden="true"></i>
                <span>Guardar Cambios</span>
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <!-- COLUMNA DERECHA: Logotipos y Elementos Gráficos -->
    <div class="col-lg-5">
      <div class="d-flex flex-column gap-4">

        <!-- TARJETA: Logotipos Públicos -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom py-3">
            <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
              <i class="bi bi-images" aria-hidden="true"></i> Logotipos Oficiales (Membretes)
            </h5>
          </div>
          <div class="card-body p-4">

            <!-- Logo Ministerial -->
            <div class="mb-4 pb-3 border-bottom">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark">Logo del Ministerio</span>
                <div>
                  <span class="badge bg-warning text-dark border">Provisional</span>
                  <span class="badge bg-light text-secondary border">Público · PNG/JPG</span>
                </div>
              </div>
              <div class="image-preview-box transparency-pattern mb-2">
                <img id="preview-ministerio_logo"
                     src="<?php echo htmlspecialchars($ministerioLogoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                     alt="Logo del Ministerio">
              </div>
              <div class="d-flex gap-2">
                <button type="button"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                        data-clave="ministerio_logo"
                        data-titulo="Logo del Ministerio"
                        data-privado="0">
                  <i class="bi bi-upload"></i> Cambiar Imagen
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                        data-clave="ministerio_logo"
                        data-titulo="Logo del Ministerio"
                        data-privado="0">
                  <i class="bi bi-arrow-counterclockwise"></i> Restablecer
                </button>
              </div>
            </div>

            <!-- Logo del Plantel -->
            <div class="mb-4 pb-3 border-bottom">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark">Logo del Plantel</span>
                <span class="badge bg-light text-secondary border">Público · PNG/JPG</span>
              </div>
              <div class="image-preview-box transparency-pattern mb-2">
                <img id="preview-plantel_logo"
                     src="<?php echo htmlspecialchars($plantelLogoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                     alt="Logo del Plantel">
              </div>
              <div class="d-flex gap-2">
                <button type="button"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                        data-clave="plantel_logo"
                        data-titulo="Logo del Plantel"
                        data-privado="0">
                  <i class="bi bi-upload"></i> Cambiar Imagen
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                        data-clave="plantel_logo"
                        data-titulo="Logo del Plantel"
                        data-privado="0">
                  <i class="bi bi-arrow-counterclockwise"></i> Restablecer
                </button>
              </div>
            </div>

            <!-- Franja Tricolor / Cintillo de Pie de Página -->
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                  <span class="fw-semibold text-dark">Franja Tricolor (Pie de Página)</span>
                  <div class="small text-muted" style="font-size: 0.75rem;">Cintillo inferior en constancias oficiales</div>
                </div>
                <div class="d-flex align-items-center gap-1">
                  <span id="badge-plantel_cintillo" class="badge <?php echo $tieneCintillo ? ($esCintilloOficial ? 'bg-info text-dark border' : 'bg-success') : 'bg-secondary'; ?>">
                    <?php echo $tieneCintillo ? ($esCintilloOficial ? 'Oficial' : 'Personalizada') : 'Sin Franja'; ?>
                  </span>
                  <span class="badge bg-light text-secondary border">Público · PNG/JPG</span>
                </div>
              </div>

              <div class="cintillo-preview-box transparency-pattern mb-2" id="box-plantel_cintillo">
                <?php if ($tieneCintillo): ?>
                  <img id="preview-plantel_cintillo"
                       src="<?php echo htmlspecialchars($plantelCintilloUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       alt="Franja Tricolor de Pie de Página">
                <?php else: ?>
                  <div class="text-center text-secondary small py-2" id="placeholder-plantel_cintillo">
                    <i class="bi bi-slash-circle fs-5 d-block mb-1 text-muted"></i>
                    <span>Sin franja de pie de página (las constancias se emitirán sin cintillo inferior)</span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <button type="button"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                        id="btn-subir-plantel_cintillo"
                        data-clave="plantel_cintillo"
                        data-titulo="Franja Tricolor (Pie de Página)"
                        data-privado="0">
                  <i class="bi bi-upload"></i> <?php echo $tieneCintillo ? 'Cambiar Imagen' : 'Cargar Imagen (PNG/JPG)'; ?>
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen <?php echo $tieneCintillo ? '' : 'd-none'; ?>"
                        id="btn-quitar-plantel_cintillo"
                        data-clave="plantel_cintillo"
                        data-titulo="Franja Tricolor"
                        data-privado="0">
                  <i class="bi bi-trash"></i> Retirar Franja
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 btn-restablecer-oficial <?php echo (!$tieneCintillo || !$esCintilloOficial) ? '' : 'd-none'; ?>"
                        id="btn-oficial-plantel_cintillo"
                        data-clave="plantel_cintillo">
                  <i class="bi bi-arrow-counterclockwise"></i> Restablecer Oficial
                </button>
              </div>
            </div>

          </div>
        </div>

        <!-- TARJETA: Firma y Sello Privados -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom py-3">
            <h5 class="card-title fw-bold mb-0 text-primary d-flex align-items-center gap-2">
              <i class="bi bi-shield-lock" aria-hidden="true"></i> Firma Digitalizada y Sello
            </h5>
          </div>
          <div class="card-body p-4">
            <p class="small text-secondary mb-3">
              <i class="bi bi-shield-check text-success me-1"></i>
              Almacenados de forma privada fuera de la raíz web. Se leen únicamente en el servidor para generar las constancias. Si no se cargan, los documentos se emitirán con el espacio en blanco para firma manual.
            </p>

            <!-- Firma del Director -->
            <div class="mb-4 pb-3 border-bottom">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark">Firma del Director(a)</span>
                <span id="badge-director_firma" class="badge <?php echo $tieneFirma ? 'bg-success' : 'bg-secondary'; ?>">
                  <?php echo $tieneFirma ? 'Configurada' : 'Sin Firma'; ?>
                </span>
              </div>

              <div class="private-preview-box transparency-pattern mb-2" id="box-director_firma">
                <?php if ($tieneFirma): ?>
                  <img id="preview-director_firma"
                       src="<?php echo htmlspecialchars($firmaUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       alt="Firma del Director">
                <?php else: ?>
                  <div class="text-center text-secondary small py-3" id="placeholder-director_firma">
                    <i class="bi bi-pen fs-4 d-block mb-1 text-muted"></i>
                    <span>Sin firma digitalizada (se emitirá en blanco)</span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2">
                <button type="button"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                        data-clave="director_firma"
                        data-titulo="Firma del Director"
                        data-privado="1">
                  <i class="bi bi-upload"></i> <?php echo $tieneFirma ? 'Cambiar Firma' : 'Cargar Firma (PNG)'; ?>
                </button>
                <?php if ($tieneFirma): ?>
                  <button type="button"
                          class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                          id="btn-quitar-director_firma"
                          data-clave="director_firma"
                          data-titulo="Firma del Director"
                          data-privado="1">
                    <i class="bi bi-trash"></i> Retirar
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <!-- Sello Institucional -->
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-dark">Sello Institucional</span>
                <span id="badge-institucion_sello" class="badge <?php echo $tieneSello ? 'bg-success' : 'bg-secondary'; ?>">
                  <?php echo $tieneSello ? 'Configurado' : 'Sin Sello'; ?>
                </span>
              </div>

              <div class="private-preview-box transparency-pattern mb-2" id="box-institucion_sello">
                <?php if ($tieneSello): ?>
                  <img id="preview-institucion_sello"
                       src="<?php echo htmlspecialchars($selloUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                       alt="Sello Institucional">
                <?php else: ?>
                  <div class="text-center text-secondary small py-3" id="placeholder-institucion_sello">
                    <i class="bi bi-award fs-4 d-block mb-1 text-muted"></i>
                    <span>Sin sello digitalizado (se emitirá en blanco)</span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2">
                <button type="button"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-abrir-subida"
                        data-clave="institucion_sello"
                        data-titulo="Sello Institucional"
                        data-privado="1">
                  <i class="bi bi-upload"></i> <?php echo $tieneSello ? 'Cambiar Sello' : 'Cargar Sello (PNG)'; ?>
                </button>
                <?php if ($tieneSello): ?>
                  <button type="button"
                          class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 btn-quitar-imagen"
                          id="btn-quitar-institucion_sello"
                          data-clave="institucion_sello"
                          data-titulo="Sello Institucional"
                          data-privado="1">
                    <i class="bi bi-trash"></i> Retirar
                  </button>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<?php
$pageContent = ob_get_clean();

// ---------- Modales ----------
ob_start();
?>
<!-- Modal Reutilizable para Carga de Imágenes -->
<div class="modal fade app-modal" id="modalSubirImagen" tabindex="-1" aria-labelledby="modalSubirImagenTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold" id="modalSubirImagenTitulo">Actualizar Imagen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="formSubirImagen">
        <div class="modal-body p-4">
          <input type="hidden" id="subidaClave" name="clave" value="">

          <div class="mb-3">
            <label for="inputArchivoImagen" class="form-label fw-semibold">Seleccionar archivo</label>
            <input type="file"
                   class="form-control"
                   id="inputArchivoImagen"
                   name="imagen"
                   required>
            <div class="form-text" id="subidaRequisitosTexto">
              Formatos permitidos: PNG o JPG. Tamaño máx: 2 MB. Ancho mínimo: 300 px.
            </div>
          </div>

          <!-- Previsualización en el modal -->
          <div class="d-none text-center p-3 border rounded transparency-pattern" id="contenedorModalPreview">
            <img id="imgModalPreview" src="" alt="Previsualización" style="max-height: 180px; max-width: 100%; object-fit: contain;">
          </div>
        </div>
        <div class="modal-footer border-top bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btnConfirmarSubida">
            <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
            <span>Subir y Guardar</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
$pageModals = ob_get_clean();

$extraScripts = ['/js/modules/institucional/configuracion-institucional.js'];
include __DIR__ . '/../layouts/app.php';
