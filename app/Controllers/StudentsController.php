<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\BinaryResponse;
use App\Models\StudentsModel;
use App\Models\RatificationModel;
use App\Models\EnrollmentModel;
use App\Support\Configuracion;
use App\Support\FichaEstudiantePdf;
use App\Support\PdfRecursos;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * Consulta de expedientes (consulta_academica/estudiantes).
 * Endpoints: estudiantes/index (GET, listado con filtros) y estudiantes/show/{id} (GET, expediente completo).
*/
class StudentsController extends CrudController
{
    protected string $viewPath  = 'consulta_academica/estudiantes';
    protected string $routeName = 'estudiantes';

    /**
 * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
 * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
*/
    private function modelo(): StudentsModel
    {
        /** @var StudentsModel $model*/
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new StudentsModel($pdo);
    }

    private function esSecretariaActual(): bool
    {
        return sigde_rol_clave($_SESSION['rol'] ?? '') === 'secretaria'
            || $this->turnoSecretariaActual() !== null;
    }

    protected function getFiltersFromRequest(): array
    {
        $model = $this->modelo();
        $turno = $this->turnoSecretariaActual();
        $docente = $this->docenteActualId();
        $esSecretaria = $this->esSecretariaActual();
        $anios = $model->getAniosEscolares($turno);
        $idsAnios = array_map(static fn (array $anio): int => (int) $anio['id'], $anios);
        $anioSolicitado = (int) ($_GET['anio'] ?? 0);

        if ($docente !== null || $esSecretaria) {
            // Docente y Secretaría operan exclusivamente sobre la matrícula activa del año escolar en curso
            $anioActivo = $model->getAnioEscolarActivoId();
            $anioSolicitado = $anioActivo !== null ? (int) $anioActivo : 0;
            $estadoFiltro = 'activo';
        } else {
            // Directivo y Coordinador consultan el archivo general (activos, retirados y egresados de todos los períodos)
            if (!in_array($anioSolicitado, $idsAnios, true)) {
                $anioSolicitado = 0;
            }
            $estadoCrudo = strtolower(trim((string) ($_GET['estado'] ?? '')));
            $estadoFiltro = in_array($estadoCrudo, ['activo', 'retirado', 'egresado'], true)
                ? $estadoCrudo
                : '';
        }

        return [
            'q'          => trim($_GET['q'] ?? ''),
            'anio'       => $anioSolicitado > 0 ? (string) $anioSolicitado : '',
            'grado'      => trim($_GET['grado'] ?? ''),
            'seccion'    => trim($_GET['seccion'] ?? ''),
            'estado'     => $estadoFiltro,
            'id_turno'   => $turno === null ? '' : (string) $turno,
            // Solo el docente se acota a sus estructuras; para los demás queda en null y
            // el modelo no aplica ninguna restricción adicional.
            'id_docente' => $docente === null ? '' : (string) $docente,
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        $turno = $this->turnoSecretariaActual();
        $aniosEscolares = $model->getAniosEscolares($turno);
        $esDocente = $this->docenteActualId() !== null;
        $esSecretaria = $this->esSecretariaActual();
        if ($esDocente || $esSecretaria) {
            $anioActivoId = $model->getAnioEscolarActivoId();
            $aniosEscolares = array_values(array_filter(
                $aniosEscolares,
                static fn (array $a): bool => (int) $a['id'] === (int) $anioActivoId
            ));
        }

        return [
            'stats'           => $model->getStats($filters),
            'estudiantes'     => $items,
            // Para que una tabla vacía se entienda: un docente sin asignaciones no ve a
            // nadie por diseño, no por un error de la consulta.
            'sinAsignaciones' => $esDocente && $model->getEstructurasDocente($this->docenteActualId()) === [],
            'aniosEscolares'  => $aniosEscolares,
            'esDocente'       => $esDocente,
            'esSecretaria'    => $esSecretaria,
            'grados'          => $model->getGrados(),
            'secciones'       => $model->getSecciones(),
        ];
    }

    public function show(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $model = $this->modelo();
            $turno = $this->turnoSecretariaActual();
            $anios = $model->getAniosEscolares($turno);
            $idsAnios = array_map(static fn (array $anio): int => (int) $anio['id'], $anios);
            $anioId = (int) ($_GET['anio'] ?? 0);
            $esDocente = $this->docenteActualId() !== null;
            $esSecretaria = $this->esSecretariaActual();

            if ($esDocente || $esSecretaria) {
                $anioActivo = $model->getAnioEscolarActivoId();
                $anioId = $anioActivo !== null ? (int) $anioActivo : 0;
                $anioConsulta = $anioId;
            } else {
                $anioConsulta = ($anioId > 0 && in_array($anioId, $idsAnios, true)) ? $anioId : null;
                $anioId = $anioConsulta ?? 0;
            }
            $anioSeleccionado = $anioId;

            $estudiante = $id > 0 && (!$esDocente && !$esSecretaria || $anioConsulta > 0)
                ? $model->getById($id, $anioConsulta, $turno, $this->docenteActualId())
                : null;
            if (!$estudiante) {
                $this->setFlash('error', 'El expediente solicitado no existe.');
                $this->redirect('index');
            }
            if ($esSecretaria && ($estudiante['estado'] ?? '') !== 'activo') {
                $this->setFlash('error', 'Solo la Dirección puede consultar expedientes de estudiantes retirados o egresados.');
                $this->redirect('index');
            }
            $historialAcademico = $model->getHistorialAcademico($id, $turno);
            if ($esDocente) {
                $historialAcademico = array_values(array_filter(
                    $historialAcademico,
                    static fn (array $reg): bool => ($reg['estado_anio'] ?? '') === 'ACTIVO'
                ));
            }
            $ratificacionModel = new RatificationModel($this->conexion());
            $datosEditables = $ratificacionModel->getEstudianteEditable($id, !$esDocente) ?? [];
$catFamiliares = $ratificacionModel->getCatalogosFamiliar();
              $estados = $catFamiliares['estados'];
            /* El catálogo de nacionalidades va también acá, y no solo en inscripción y
               ratificación. Esta ficha incluye el MISMO modal de edición de estudiante, así
               que su <select> de nacionalidad salía sin una sola opción: el campo se ve,
               pero el desplegable está vacío. Es el módulo donde más se edita y el único
               que se había quedado sin catálogo. */
            $nacionalidades = $ratificacionModel->getNacionalidades();
    /* Los vinculados se piden YA separados en los dos grupos que la ficha muestra: el núcleo familiar
 y los autorizados a retirar el alumno. Son dos preguntas distintas —a quién se le informa y
 quién puede recogerlo— y en una sola lista un autorizado por confianza, como el transporte,
 parecía un pariente. Una misma persona puede estar en los dos.*/
              $vinculados              = $ratificacionModel->getFamiliaresParaExpediente($id, $esDocente);
              $familiaresNucleo        = $vinculados['nucleo'];
              $autorizadosParaRetiro   = $vinculados['autorizados'];
            /* Recaudos: catálogo activo contra lo que el representante entregó. Se leen del
 modelo de inscripción y no del de ratificación porque el trait vive allí.*/
            $recaudos = (new EnrollmentModel($this->conexion()))->getRecaudosDeEstudiante($id);
            $puedeEditar = sigde_rol_clave($_SESSION['rol'] ?? '') === 'secretaria';

            require __DIR__ . "/../Views/{$this->viewPath}/show.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
 * Actualiza los recaudos de un estudiante desde su ficha.
 *
 * Es la vía para el caso normal: el representante entrega un documento días después de la
 * inscripción, y sin esto el requisito quedaba marcado como pendiente para siempre.
 *
 * Comprueba el turno con secretariaTieneAccesoAEstudiante() y no solo la ruta: la ficha
 * se puede pedir con cualquier id por URL, así que sin ese control una secretaria del
 * turno de la tarde podría marcar recaudos de un alumno de la mañana.
*/
    public function guardarRecaudos(): void
    {
        try {
            $idEstudiante = (int) ($_POST['id_estudiante'] ?? 0);
            if ($idEstudiante <= 0) {
                $this->jsonResponse(false, null, 'Estudiante no válido.', 422);
            }
            if (!$this->secretariaTieneAccesoAEstudiante($idEstudiante)) {
                $this->jsonResponse(false, null,
                    'Ese estudiante no pertenece a su turno.', 403);
            }

            $model = new EnrollmentModel($this->conexion());
            $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
            $tocados = $model->actualizarRecaudos(
                $idEstudiante,
                $this->decodificarRecaudos($_POST['recaudos'] ?? []),
                $idUsuario
            );

            $this->jsonResponse(true, ['actualizados' => $tocados],
                $tocados > 0
                    ? 'Recaudos actualizados correctamente.'
                    : 'No hubo cambios que guardar.');
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    /**
 * Normaliza el bloque de recaudos que llega del formulario de actualización.
 *
 * Mismo criterio que en la inscripción: aquí se limpian los tipos, pero decidir si un id
 * es válido lo hace el modelo contra el catálogo. Sanear el texto con normalizarEspacios
 * (que quita HTML) es lo que evita guardar un <script> en la observación.
 *
 * @return array<int, array{consignado: int, observacion: string}>
*/
    protected function decodificarRecaudos($crudo): array
    {
        if (!is_array($crudo)) {
            return [];
        }

        $recaudos = [];
        foreach ($crudo as $id => $estado) {
            $id = (int) $id;
            if ($id <= 0 || !is_array($estado)) {
                continue;
            }
            $recaudos[$id] = [
                'consignado'  => !empty($estado['consignado']) ? 1 : 0,
                'observacion' => mb_substr(
                    $this->normalizarEspacios((string) ($estado['observacion'] ?? '')),
                    0,
                    255
                ),
            ];
        }

        return $recaudos;
    }

    public function exportar(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $model = $this->modelo();
            $turno = $this->turnoSecretariaActual();
            $anios = $model->getAniosEscolares($turno);
            $idsAnios = array_map(static fn (array $anio): int => (int) $anio['id'], $anios);
            $anioId = (int) ($_GET['anio'] ?? 0);
            $esDocente = $this->docenteActualId() !== null;
            $esSecretaria = $this->esSecretariaActual();

            if ($esDocente || $esSecretaria) {
                $anioActivo = $model->getAnioEscolarActivoId();
                $anioId = $anioActivo !== null ? (int) $anioActivo : 0;
                $anioConsulta = $anioId;
            } else {
                $anioConsulta = ($anioId > 0 && in_array($anioId, $idsAnios, true)) ? $anioId : null;
                $anioId = $anioConsulta ?? 0;
            }

            $estudiante = $id > 0 && (!$esDocente && !$esSecretaria || $anioConsulta > 0)
                ? $model->getById($id, $anioConsulta, $turno, $this->docenteActualId())
                : null;
            if (!$estudiante) {
                $this->renderHttpError(404, '404 · Expediente no encontrado', 'No se encontró el expediente solicitado.');
            }
            if ($esSecretaria && ($estudiante['estado'] ?? '') !== 'activo') {
                $this->renderHttpError(403, '403 · Acceso denegado', 'Solo la Dirección puede exportar expedientes de estudiantes retirados o egresados.');
            }

            $ratificacionModel = new RatificationModel($this->conexion());
            $datosEditables = $ratificacionModel->getEstudianteEditable($id, !$esDocente) ?? [];
            /* La lista completa se conserva para buscar al representante del membrete, y además llegan los
               dos grupos separados para el reporte. Es la MISMA separación que muestra la pantalla, hecha
               con getFamiliaresParaExpediente() para que el PDF no pueda apartarse de la ficha digital:
               si mañana cambia la regla, cambia en los dos sitios o en ninguno.*/
            $familiares            = $ratificacionModel->getFamiliares($id);
            $vinculados            = $ratificacionModel->getFamiliaresParaExpediente($id, $esDocente);
            $familiaresNucleo      = $vinculados['nucleo'];
            $autorizadosParaRetiro = $vinculados['autorizados'];
            $historialAcademico = $model->getHistorialAcademico($id, $turno);
            if ($esDocente) {
                $historialAcademico = array_values(array_filter(
                    $historialAcademico,
                    static fn (array $reg): bool => ($reg['estado_anio'] ?? '') === 'ACTIVO'
                ));
            }
            $config = Configuracion::getAll();
            $fotoDataUri = $this->imagenPublicaDataUri(
                (string) ($estudiante['avatar_img'] ?? ''),
                false,
                29 / 37
            );

            $pdfContent = FichaEstudiantePdf::generar(
                $config,
                $estudiante,
                $datosEditables,
                $familiaresNucleo,
                $autorizadosParaRetiro,
                $historialAcademico,
                $esDocente,
                null,
                $fotoDataUri
            );

            $nombreArchivo = 'expediente-' . preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower((string) ($estudiante['nombre'] ?? 'estudiante'))) . '.pdf';
            $disposition = (($_GET['descargar'] ?? '') === '1') ? 'attachment' : 'inline';
            BinaryResponse::pdf($pdfContent, $nombreArchivo, $disposition === 'inline')->send();
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    private function imagenPublicaDataUri(
        string $rutaPublica,
        bool $recortarBlanco = false,
        ?float $relacionAspecto = null
    ): ?string
    {
        if ($rutaPublica === '' || str_contains($rutaPublica, '..') || $rutaPublica[0] !== '/') {
            return null;
        }

        $directorioPublico = realpath(dirname(__DIR__, 2) . '/public');
        $archivo = realpath(dirname(__DIR__, 2) . '/public' . $rutaPublica);
        if ($directorioPublico === false
            || $archivo === false
            || !str_starts_with($archivo, $directorioPublico . DIRECTORY_SEPARATOR)
            || !is_file($archivo)) {
            return null;
        }

        $imageInfo = getimagesize($archivo);
        $mime = $imageInfo['mime'] ?? '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        if ($mime === 'image/webp') {
            if (!function_exists('imagecreatefromwebp')) {
                throw new \RuntimeException('El servidor no puede procesar la foto WEBP del estudiante.');
            }
            $imagen = imagecreatefromwebp($archivo);
            if ($imagen === false) {
                throw new \RuntimeException('No se pudo procesar la foto del estudiante.');
            }
            ob_start();
            if (!imagepng($imagen)) {
                ob_end_clean();
                imagedestroy($imagen);
                throw new \RuntimeException('No se pudo preparar la foto del estudiante para el PDF.');
            }
            $contenido = ob_get_clean();
            imagedestroy($imagen);
            $mime = 'image/png';
        } elseif ($recortarBlanco && $mime === 'image/png') {
            $imagen = imagecreatefrompng($archivo);
            if ($imagen === false) {
                throw new \RuntimeException('No se pudo procesar el logo institucional.');
            }

            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);
            $minX = $ancho;
            $minY = $alto;
            $maxX = -1;
            $maxY = -1;

            for ($y = 0; $y < $alto; $y++) {
                for ($x = 0; $x < $ancho; $x++) {
                    $color = imagecolorsforindex($imagen, imagecolorat($imagen, $x, $y));
                    if (($color['alpha'] ?? 0) >= 120
                        || ($color['red'] > 247 && $color['green'] > 247 && $color['blue'] > 247)) {
                        continue;
                    }
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }

            if ($maxX >= $minX && $maxY >= $minY) {
                $recorte = imagecrop($imagen, [
                    'x' => $minX,
                    'y' => $minY,
                    'width' => $maxX - $minX + 1,
                    'height' => $maxY - $minY + 1,
                ]);
                if ($recorte === false) {
                    imagedestroy($imagen);
                    throw new \RuntimeException('No se pudo ajustar el logo del Ministerio.');
                }

                ob_start();
                if (!imagepng($recorte)) {
                    ob_end_clean();
                    imagedestroy($recorte);
                    imagedestroy($imagen);
                    throw new \RuntimeException('No se pudo convertir el logo del Ministerio.');
                }
                $contenido = ob_get_clean();
                imagedestroy($recorte);
            } else {
                imagedestroy($imagen);
                throw new \RuntimeException('El logo del Ministerio está vacío.');
            }
            imagedestroy($imagen);
        } else {
            $contenido = file_get_contents($archivo);
        }

        if ($contenido !== false && $relacionAspecto !== null) {
            if ($relacionAspecto <= 0) {
                throw new \InvalidArgumentException('La proporción de recorte de la foto no es válida.');
            }

            $imagen = @imagecreatefromstring($contenido);
            if ($imagen === false) {
                throw new \RuntimeException('No se pudo preparar la foto del estudiante para el PDF.');
            }

            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);
            $relacionActual = $ancho / $alto;
            if ($relacionActual > $relacionAspecto) {
                $anchoRecorte = max(1, (int) round($alto * $relacionAspecto));
                $caja = [
                    'x' => (int) floor(($ancho - $anchoRecorte) / 2),
                    'y' => 0,
                    'width' => $anchoRecorte,
                    'height' => $alto,
                ];
            } else {
                $altoRecorte = max(1, (int) round($ancho / $relacionAspecto));
                $caja = [
                    'x' => 0,
                    'y' => (int) floor(($alto - $altoRecorte) / 2),
                    'width' => $ancho,
                    'height' => $altoRecorte,
                ];
            }

            $recorte = imagecrop($imagen, $caja);
            imagedestroy($imagen);
            if ($recorte === false) {
                throw new \RuntimeException('No se pudo recortar la foto del estudiante para el PDF.');
            }

            ob_start();
            $convertido = imagepng($recorte);
            imagedestroy($recorte);
            if (!$convertido) {
                ob_end_clean();
                throw new \RuntimeException('No se pudo convertir la foto del estudiante para el PDF.');
            }
            $contenido = ob_get_clean();
            $mime = 'image/png';
        }

        return $contenido === false
            ? null
            : 'data:' . $mime . ';base64,' . base64_encode($contenido);
    }
}