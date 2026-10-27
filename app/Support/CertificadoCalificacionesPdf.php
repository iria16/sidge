<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * CertificadoCalificacionesPdf
 * -------------------
 * Servicio centralizado para la renderización y generación de PDFs de
 * Certificados de Calificaciones (Art. 108 RGLOE) mediante Dompdf.
 *
 * Sigue fielmente el formato oficial del Ministerio del Poder Popular
 * para la Educación y la identidad institucional del plantel.
 */
if (!class_exists(\Dompdf\Dompdf::class)) {
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    }
}

class CertificadoCalificacionesPdf
{
    private const TIMEZONE = 'America/Caracas';

    private const MESES_ESPANOL = [
        1  => 'ENERO',
        2  => 'FEBRERO',
        3  => 'MARZO',
        4  => 'ABRIL',
        5  => 'MAYO',
        6  => 'JUNIO',
        7  => 'JULIO',
        8  => 'AGOSTO',
        9  => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE',
    ];

    /**
     * Genera el contenido binario del PDF para un Certificado de Calificaciones (individual).
     *
     * @param array $config Configuración institucional proveniente de Configuracion::getAll()
     * @param array $estudiante Datos del estudiante con su literal y resultado académico
     * @param DateTimeInterface|null $fechaExpedicion Fecha de expedición (por defecto ahora)
     * @param bool $marcaAguaPrueba Si es true, imprime la marca de agua diagonal "PRUEBA"
     * @param string|null $textoArt108 Texto del Art. 108 RGLOE (copiado literalmente de la hoja física)
     * @return string Contenido binario del PDF generado
     * @throws DomainException Si faltan datos institucionales o del estudiante
     * @throws InvalidArgumentException Si falta algún dato obligatorio
     */
    public static function generar(
        array $config,
        array $estudiante,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false,
        ?string $textoArt108 = null
    ): string {
        $datos = self::prepararVariables($config, $estudiante, $fechaExpedicion, $marcaAguaPrueba, $textoArt108);
        $datos['marcaAguaPrueba'] = $marcaAguaPrueba;

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/certificado_calificaciones.php', $datos);
    }

    /**
     * Genera el contenido binario del PDF para un Certificado de Calificaciones por sección (múltiple).
     *
     * @param array $config Configuración institucional
     * @param array $estudiantes Array de estudiantes con sus datos y literales
     * @param DateTimeInterface|null $fechaExpedicion Fecha de expedición
     * @param bool $marcaAguaPrueba Si es true, imprime la marca de agua diagonal "PRUEBA"
     * @param string|null $textoArt108 Texto del Art. 108 RGLOE
     * @return string Contenido binario del PDF generado
     * @throws DomainException Si faltan datos institucionales
     */
    public static function generarPorSeccion(
        array $config,
        array $estudiantes,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false,
        ?string $textoArt108 = null
    ): string {
        $datos = self::prepararVariablesPorSeccion($config, $estudiantes, $fechaExpedicion, $marcaAguaPrueba, $textoArt108);
        $datos['marcaAguaPrueba'] = $marcaAguaPrueba;

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/certificado_calificaciones_por_seccion.php', $datos);
    }

    /**
     * Normaliza y valida exhaustivamente las variables requeridas por las plantillas de Certificado de Calificaciones.
     */
    private static function prepararVariables(
        array $config,
        array $estudiante,
        ?DateTimeInterface $fechaExpedicion,
        bool $esModoPrueba,
        ?string $textoArt108
    ): array {
        $tz = new DateTimeZone(self::TIMEZONE);
        if ($fechaExpedicion === null) {
            $fechaExpedicion = new DateTimeImmutable('now', $tz);
        }

        // Validación estricta del estudiante
        $nombreEst = trim((string) ($estudiante['nombre_completo'] ?? ''));
        if ($nombreEst === '') {
            throw new InvalidArgumentException('Falta el nombre completo; complete la ficha del estudiante.');
        }

        $docEstNum = trim((string) ($estudiante['numero_documento'] ?? ''));
        if ($docEstNum === '') {
            throw new InvalidArgumentException('Falta el número de documento; complete la ficha del estudiante.');
        }

        $tipoDoc = strtoupper((string) ($estudiante['tipo_documento'] ?? ''));
        if (in_array($tipoDoc, ['V', 'E', 'P', 'J', 'C'], true)) {
            $docEstEtiqueta = match ($tipoDoc) {
                'V' => 'la Cédula de Identidad Nº',
                'E' => 'la Cédula de Identidad Nº E-',
                'P' => 'el Pasaporte Nº',
                'J' => 'la Cédula de Identidad Juvenil Nº',
                'C' => 'el Carnet de la Patria Nº',
                default => 'el Documento de Identidad Nº',
            };
        } elseif ($tipoDoc === 'CE') {
            $docEstEtiqueta = 'la Cédula Escolar Nº';
        } else {
            $docEstEtiqueta = 'el Documento de Identidad Nº';
        }

        // Validación de literal
        $literal = strtoupper(trim((string) ($estudiante['literal'] ?? '')));
        if ($literal === '') {
            throw new InvalidArgumentException('El estudiante no tiene un literal registrado.');
        }
        if (!in_array($literal, ['A', 'B', 'C', 'D', 'E'], true)) {
            throw new InvalidArgumentException("Literal inválido: {$literal}. Debe ser A, B, C, D o E.");
        }

        // Obtener el grado cursado
        $gradoCursado = trim((string) ($estudiante['grado'] ?? ''));
        if ($gradoCursado === '') {
            throw new InvalidArgumentException('Falta el grado cursado; complete la información correspondiente.');
        }

        // Obtener el período escolar
        $periodoEscolar = trim((string) ($estudiante['periodo_escolar'] ?? ''));
        if ($periodoEscolar === '') {
            throw new InvalidArgumentException('Falta el período escolar; complete la información correspondiente.');
        }

        // Obtener el resultado de promoción
        $resultadoPromocion = strtoupper(trim((string) ($estudiante['estado_resultado'] ?? '')));
        if ($resultadoPromocion === '') {
            // Calcular el estado de promoción a partir del literal
            $resultadoPromocion = match ($literal) {
                'A', 'B', 'C', 'D' => 'PROMOVIDO',
                'E' => 'NO_PROMOVIDO',
                default => 'PENDIENTE',
            };
        }

        // Validación de datos institucionales
        $codigoDependencia = trim((string) ($config['plantel_codigo_dependencia'] ?? ''));
        if ($codigoDependencia === '') {
            throw new DomainException('Falta el Código de Dependencia en la configuración institucional.');
        }

        if (!$esModoPrueba) {
            $dirNombre = trim((string) ($config['director_nombre'] ?? ''));
            $dirCedula = trim((string) ($config['director_cedula'] ?? ''));
            $dirCargo  = trim((string) ($config['director_cargo'] ?? ''));

            if ($dirNombre === '' || $dirCedula === '' || $dirCargo === '') {
                throw new DomainException(
                    'No se puede emitir el certificado: solicite al administrador que complete el nombre, ' .
                    'cédula y cargo del director(a) en Datos Institucionales.'
                );
            }

            if (trim((string) ($config['plantel_nombre'] ?? '')) === '') {
                throw new DomainException('Falta el nombre oficial del plantel en la configuración institucional.');
            }
        }

        // Fecha de expedición
        $diaExp = (int) $fechaExpedicion->format('j');
        $mesExp = (int) $fechaExpedicion->format('n');
        $anioExp = (int) $fechaExpedicion->format('Y');
        $mesExpTexto = self::MESES_ESPANOL[$mesExp] ?? '';
        $anioExpTexto = (string) $anioExp;
        $fechaExpTexto = sprintf('%02d de %s de %04d', $diaExp, mb_strtolower($mesExpTexto, 'UTF-8'), $anioExp);

        // Cédula del director
        $dirCedulaRaw = (string) ($config['director_cedula'] ?? '');
        $dirLetra = preg_match('/^[VE]/i', $dirCedulaRaw, $m) ? strtoupper($m[0]) : 'V';
        $dirNumLimpio = preg_replace('/\D/', '', $dirCedulaRaw);
        $dirNumFormatted = ($dirNumLimpio !== '') ? number_format((int) $dirNumLimpio, 0, '', '.') : '';

        // Assets gráficos (preservando relación de aspecto natural sin distorsión)
        $logoMin = Configuracion::logoEstrictoConstancia('ministerio_logo', 150, 45);
        $ministerioLogo = $logoMin['uri'] ?? null;
        $dimMin = $logoMin ? ['width' => $logoMin['width'], 'height' => $logoMin['height']] : ['width' => 0, 'height' => 0];

        $logoPla = Configuracion::logoEstrictoConstancia('plantel_logo', 60, 50);
        $plantelLogo = $logoPla['uri'] ?? null;
        $dimPla = $logoPla ? ['width' => $logoPla['width'], 'height' => $logoPla['height']] : ['width' => 0, 'height' => 0];

        $cintilloLogo = Configuracion::dataUriPublico('plantel_cintillo');

        $firmaUri = $config['firmaUri'] ?? Configuracion::dataUriPrivado('director_firma');
        $selloUri = $config['selloUri'] ?? Configuracion::dataUriPrivado('institucion_sello');

        // Calcular el siguiente grado (para promoción)
        $siguienteGrado = $literal !== 'E' ? ($estudiante['id_grado_destino'] ?? $estudiante['id_grado_siguiente'] ?? null) : null;
        $siguienteGradoNombre = null;
        if ($siguienteGrado !== null && isset($estudiante['grados_catalogo'])) {
            foreach ($estudiante['grados_catalogo'] as $g) {
                if ((int) ($g['id'] ?? 0) === (int) $siguienteGrado) {
                    $siguienteGradoNombre = (string) ($g['nombre'] ?? '');
                    break;
                }
            }
        }
        if (!$siguienteGradoNombre) {
            $siguienteGradoNombre = $literal !== 'E' ? ($estudiante['grado_destino_nombre'] ?? '') : '';
        }

        // Docente
        $docenteNombre = trim((string) ($estudiante['docente_nombre'] ?? $config['docente_nombre'] ?? ''));
        $docenteCi     = trim((string) ($estudiante['docente_ci'] ?? $config['docente_ci'] ?? ''));

        // Director
        $directorNombre = trim((string) ($config['director_nombre'] ?? ''));
        $directorCi     = $dirNumFormatted !== '' ? ($dirLetra . '-' . $dirNumFormatted) : '';

        // Texto del Art. 108 (si se proporciona)
        $textoArt108Escapado = $textoArt108 ? htmlspecialchars($textoArt108, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';

        return [
            'config'            => $config,
            'estudiante'        => $estudiante,
            'codigoDependencia' => $codigoDependencia,
            'ministerioLogo'    => $ministerioLogo,
            'plantelLogo'       => $plantelLogo,
            'cintilloLogo'      => $cintilloLogo,
            'dimMin'            => $dimMin,
            'dimPla'            => $dimPla,
            'firmaUri'          => $firmaUri,
            'selloUri'          => $selloUri,
            'fechaNacTexto'     => $estudiante['fecha_nacimiento_texto'] ?? '',
            'fechaExpTexto'     => $fechaExpTexto,
            'diaExp'            => $diaExp,
            'mesExpTexto'       => $mesExpTexto,
            'anioExpTexto'      => $anioExpTexto,
            'dirLetra'          => $dirLetra,
            'dirNumFormatted'   => $dirNumFormatted,
            'dirNumLimpio'      => $dirNumLimpio,
            'docEstEtiqueta'    => $docEstEtiqueta,
            'recuadroModo'      => $config['constancia_recuadro_modo'] ?? 'completo',

            'docenteNombre'     => $docenteNombre,
            'docenteCi'         => $docenteCi,
            'directorNombre'    => $directorNombre,
            'directorCi'        => $directorCi,

            'literal'            => $literal,
            'gradoCursado'       => $gradoCursado,
            'periodoEscolar'     => $periodoEscolar,
            'resultadoPromocion' => $resultadoPromocion,
            'siguienteGradoNombre' => $siguienteGradoNombre,
            'textoArt108'        => $textoArt108Escapado,
            'anioEscolar'        => $periodoEscolar,
        ];
    }

    /**
     * Preparar variables para la versión por sección (múltiple estudiantes).
     */
    private static function prepararVariablesPorSeccion(
        array $config,
        array $estudiantes,
        ?DateTimeInterface $fechaExpedicion,
        bool $esModoPrueba,
        ?string $textoArt108
    ): array {
        $datos = self::prepararVariables($config, $estudiantes[0], $fechaExpedicion, $esModoPrueba, $textoArt108);

        $datos['estudiantes'] = $estudiantes;
        $datos['cantidadEstudiantes'] = count($estudiantes);

        // Calcular los grados únicos presentes
        $grados = array_unique(array_filter(array_column($estudiantes, 'grado')));
        $datos['gradosUnicos'] = array_values($grados);

        return $datos;
    }

    /**
     * Renderiza una plantilla PHP con variables y la compila con Dompdf.
     */
    private static function renderizarPdf(string $templatePath, array $variables): string
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException("Plantilla PDF no encontrada: {$templatePath}");
        }

        extract($variables);

        ob_start();
        try {
            require $templatePath;
            $html = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Times-Roman');
        $options->set('chroot', [
            Configuracion::getProjectRoot(),
            Configuracion::getPublicRoot(),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}