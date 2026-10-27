<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;
use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * ConstanciaPdf
 * -------------
 * Servicio centralizado para la renderización y generación de PDFs de
 * Constancias de Estudio e Inscripción mediante Dompdf.
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

class ConstanciaPdf
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
     * Genera el contenido binario del PDF para una Constancia de Estudio.
     *
     * @param array $config Configuración institucional proveniente de Configuracion::getAll()
     * @param array $estudiante Datos del estudiante
     * @param string $modalidad 'activo', 'retirado' o 'egresado'
     * @param DateTimeInterface|null $fechaExpedicion Fecha de expedición (por defecto ahora)
     * @param bool $marcaAguaPrueba Si es true, imprime la marca de agua diagonal "PRUEBA"
     * @return string Contenido binario del PDF generado
     * @throws DomainException Si se solicita modalidad retirada o egresada en producción, o si faltan datos institucionales
     * @throws InvalidArgumentException Si falta algún dato obligatorio del estudiante
     */
    public static function generarEstudio(
        array $config,
        array $estudiante,
        string $modalidad = 'activo',
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false
    ): string {
        // En el módulo real (producción), retirados y egresados quedan bloqueados hasta aprobación formal
        if (!$marcaAguaPrueba && in_array($modalidad, ['retirado', 'egresado'], true)) {
            throw new DomainException(
                "La emisión de constancias de estudio para estudiantes {$modalidad}s " .
                'se encuentra suspendida a la espera de aprobación del formato institucional por parte de la Dirección.'
            );
        }

        // La nota explicativa de borrador solo aparece en los PDFs de prueba
        $notaBorrador = ($marcaAguaPrueba && in_array($modalidad, ['retirado', 'egresado'], true))
            ? "Variante en pasado para estudiantes {$modalidad}s (pendiente de aprobación de la directora)."
            : null;

        $datos = self::prepararVariables($config, $estudiante, $fechaExpedicion, $marcaAguaPrueba);
        $datos['modalidad']       = $modalidad;
        $datos['notaBorrador']    = $notaBorrador;
        $datos['marcaAguaPrueba'] = $marcaAguaPrueba;

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/pdf/estudio.php', $datos);
    }

    /**
     * Genera el contenido binario del PDF para una Constancia de Inscripción.
     *
     * @param array $config Configuración institucional
     * @param array $estudiante Datos del estudiante
     * @param DateTimeInterface|null $fechaExpedicion Fecha de expedición
     * @param bool $marcaAguaPrueba Si es true, imprime la marca de agua diagonal "PRUEBA"
     * @return string Contenido binario del PDF generado
     * @throws DomainException Si faltan datos institucionales
     * @throws InvalidArgumentException Si falta algún dato obligatorio del estudiante
     */
    public static function generarInscripcion(
        array $config,
        array $estudiante,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false
    ): string {
        $datos = self::prepararVariables($config, $estudiante, $fechaExpedicion, $marcaAguaPrueba);
        $datos['notaBorrador']    = null;
        $datos['marcaAguaPrueba'] = $marcaAguaPrueba;

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/pdf/inscripcion.php', $datos);
    }

    /**
     * Genera el contenido binario del PDF para una Constancia de Retiro oficial.
     */
    public static function generarRetiro(
        array $config,
        array $estudiante,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false
    ): string {
        $datos = self::prepararVariables($config, $estudiante, $fechaExpedicion, $marcaAguaPrueba);
        $datos['notaBorrador']    = null;
        $datos['marcaAguaPrueba'] = $marcaAguaPrueba;

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/pdf/retiro.php', $datos);
    }

    /**
     * Genera el contenido binario del PDF para una Constancia de Prosecución oficial.
     */
    public static function generarProsecucion(
        array $config,
        array $estudiante,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false
    ): string {
        $datos = self::prepararVariables($config, $estudiante, $fechaExpedicion, $marcaAguaPrueba);
        $datos['notaBorrador']    = null;
        $datos['marcaAguaPrueba'] = $marcaAguaPrueba;

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/pdf/prosecucion.php', $datos);
    }

    /**
     * Genera el contenido binario del PDF para el Certificado de Educación Primaria (Egreso) oficial.
     */
    public static function generarEgreso(
        array $config,
        array $estudiante,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false
    ): string {
        $datos = self::prepararVariables($config, $estudiante, $fechaExpedicion, $marcaAguaPrueba);
        $datos['notaBorrador']    = null;
        $datos['marcaAguaPrueba'] = $marcaAguaPrueba;

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/pdf/egreso.php', $datos);
    }

    /**
     * Genera el contenido binario del PDF para un lote de Certificados de Educación Primaria (Egreso).
     *
     * @param array $config Configuración institucional
     * @param array $estudiantes Array de estudiantes con sus datos oficiales de egreso
     * @param DateTimeInterface|null $fechaExpedicion Fecha de expedición
     * @param bool $marcaAguaPrueba Si es true, imprime la marca de agua diagonal "PRUEBA"
     * @return string Contenido binario del PDF generado
     * @throws DomainException|InvalidArgumentException Si faltan datos requeridos
     */
    public static function generarEgresoPorLote(
        array $config,
        array $estudiantes,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $marcaAguaPrueba = false
    ): string {
        if ($estudiantes === []) {
            throw new InvalidArgumentException('No hay estudiantes para emitir certificados de egreso.');
        }

        $estudiantesPreparados = [];
        $tz = new DateTimeZone(self::TIMEZONE);
        if ($fechaExpedicion === null) {
            $fechaExpedicion = new DateTimeImmutable('now', $tz);
        }

        // Preparamos las variables comunes usando el primer estudiante para validar configuración institucional
        $datosBase = self::prepararVariables($config, $estudiantes[0], $fechaExpedicion, $marcaAguaPrueba);

        foreach ($estudiantes as $est) {
            $varsEst = self::prepararVariables($config, $est, $fechaExpedicion, $marcaAguaPrueba);
            $estPreparado = $varsEst['estudiante'];
            $estPreparado['fecha_nacimiento_texto'] = $varsEst['fechaNacTexto'];
            $estPreparado['literal']                = $varsEst['estudiante']['literal'] ?? 'A';
            $estPreparado['periodo_escolar']         = $varsEst['estudiante']['periodo_escolar'] ?? '';
            $estudiantesPreparados[] = $estPreparado;
        }

        $datosBase['estudiantes']         = $estudiantesPreparados;
        $datosBase['cantidadEstudiantes'] = count($estudiantesPreparados);
        $datosBase['notaBorrador']        = null;
        $datosBase['marcaAguaPrueba']     = $marcaAguaPrueba;
        $datosBase['estadoExpedicion']    = mb_strtoupper((string) ($config['cdce_estado'] ?? $config['lugar_expedicion'] ?? 'CARABOBO'), 'UTF-8');

        return self::renderizarPdf(__DIR__ . '/../Views/constancias/pdf/egreso_por_seccion.php', $datosBase);
    }

    /**
     * Normaliza y valida exhaustivamente las variables requeridas por las plantillas.
     *
     * Regla estricta: NO se inventan datos. Si falta algún campo del estudiante o del
     * director/institución, se lanza una excepción clara para completar la ficha.
     */
    private static function prepararVariables(
        array $config,
        array $estudiante,
        ?DateTimeInterface $fechaExpedicion = null,
        bool $esModoPrueba = false
    ): array {
        $tz = new DateTimeZone(self::TIMEZONE);
        if ($fechaExpedicion === null) {
            $fechaExpedicion = new DateTimeImmutable('now', $tz);
        }

        // 1. VALIDACIÓN ESTRICTA DEL ESTUDIANTE (sin valores por defecto inventados)
        $nombreEst = trim((string) ($estudiante['nombre_completo'] ?? ''));
        if ($nombreEst === '') {
            throw new InvalidArgumentException('Falta el nombre completo; complete la ficha del estudiante.');
        }

        $docEstNum = trim((string) ($estudiante['numero_documento'] ?? ''));
        if ($docEstNum === '') {
            throw new InvalidArgumentException('Falta el número de documento; complete la ficha del estudiante.');
        }

        $fechaNacRaw = $estudiante['fecha_nacimiento'] ?? null;
        if ($fechaNacRaw === null || $fechaNacRaw === '') {
            throw new InvalidArgumentException('Falta la fecha de nacimiento; complete la ficha del estudiante.');
        }

        if ($fechaNacRaw instanceof DateTimeInterface) {
            $fechaNac = $fechaNacRaw;
        } elseif (is_string($fechaNacRaw)) {
            try {
                $fechaNac = new DateTimeImmutable($fechaNacRaw, $tz);
            } catch (Throwable) {
                throw new InvalidArgumentException('La fecha de nacimiento no tiene un formato de fecha válido; complete la ficha del estudiante.');
            }
        } else {
            throw new InvalidArgumentException('Falta la fecha de nacimiento; complete la ficha del estudiante.');
        }

        $municipioNac = trim((string) ($estudiante['municipio_nacimiento'] ?? ''));
        $estadoNac    = trim((string) ($estudiante['estado_nacimiento'] ?? ''));

        $gradoEst = trim((string) ($estudiante['grado'] ?? ($estudiante['grado_origen'] ?? '')));
        if ($gradoEst === '') {
            throw new InvalidArgumentException('Falta el grado; complete la información correspondiente.');
        }
        $estudiante['grado'] = $gradoEst;

        $periodoEst = trim((string) ($estudiante['periodo_escolar'] ?? ''));
        if ($periodoEst === '') {
            throw new InvalidArgumentException('Falta el período escolar; complete la ficha del estudiante.');
        }

        // Formateo de fecha de nacimiento
        $diaNac = (int) $fechaNac->format('j');
        $mesNac = (int) $fechaNac->format('n');
        $anioNac = (int) $fechaNac->format('Y');
        $fechaNacTexto = sprintf('%02d DE %s DE %04d', $diaNac, self::MESES_ESPANOL[$mesNac] ?? '', $anioNac);

        // 2. VALIDACIÓN DE CÓDIGO DE DEPENDENCIA Y CONFIGURACIÓN INSTITUCIONAL
        $codigoDependencia = trim((string) ($config['plantel_codigo_dependencia'] ?? ''));
        if ($codigoDependencia === '') {
            throw new DomainException('Falta el Código de Dependencia en la configuración institucional.');
        }

        // Fuera de modo de prueba, validar obligatoriedad de la autoridad directiva
        if (!$esModoPrueba) {
            $dirNombre = trim((string) ($config['director_nombre'] ?? ''));
            $dirCedula = trim((string) ($config['director_cedula'] ?? ''));
            $dirCargo  = trim((string) ($config['director_cargo'] ?? ''));

            if ($dirNombre === '' || $dirCedula === '' || $dirCargo === '') {
                throw new DomainException(
                    'No se puede emitir la constancia: solicite al administrador que complete el nombre, ' .
                    'cédula y cargo del director(a) en Datos Institucionales.'
                );
            }

            if (trim((string) ($config['plantel_nombre'] ?? '')) === '') {
                throw new DomainException('Falta el nombre oficial del plantel en la configuración institucional.');
            }
            if (trim((string) ($config['plantel_codigo_dea'] ?? '')) === '') {
                throw new DomainException('Falta el Código DEA en la configuración institucional.');
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

        // Datos del Director(a) del CDCE Estadal (opcionales)
        $cdceDirNombre = mb_strtoupper(trim((string) ($config['cdce_director_nombre'] ?? '')), 'UTF-8');
        $cdceCedulaRaw = trim((string) ($config['cdce_director_cedula'] ?? ''));
        $cdceLetra = preg_match('/^[VE]/i', $cdceCedulaRaw, $mCdce) ? strtoupper($mCdce[0]) : 'V';
        $cdceNumLimpio = preg_replace('/\D/', '', $cdceCedulaRaw);
        $cdceNumFormatted = ($cdceNumLimpio !== '') ? number_format((int) $cdceNumLimpio, 0, '', '.') : '';
        $cdceDirDocTexto = ($cdceNumFormatted !== '') ? ($cdceLetra . '-' . $cdceNumFormatted) : '';

        // Documento del estudiante
        $estDocTipo = strtoupper((string) ($estudiante['tipo_documento'] ?? 'V'));
        if ($estDocTipo === 'E') {
            $docEstNum = preg_replace('/^E[-.\s]*/i', '', $docEstNum);
            $docEstEtiqueta = 'Documento de Identidad Nº E-';
        } elseif ($estDocTipo === 'CE') {
            $docEstEtiqueta = 'la Cédula Escolar Nº';
        } else {
            $docEstEtiqueta = 'la Cédula de Identidad Nº';
        }
        $estudiante['numero_documento'] = $docEstNum;

        // Assets gráficos como Data URIs base64 seguros
        // Los logos del ministerio y escuela usan resolución estricta (solo si están subidos y existen en disco)
        $logoMin = Configuracion::logoEstrictoConstancia('ministerio_logo', 140, 42);
        $ministerioLogo = $logoMin['uri'] ?? null;
        $dimMin         = $logoMin ? ['width' => $logoMin['width'], 'height' => $logoMin['height']] : ['width' => 0, 'height' => 0];

        $logoPla = Configuracion::logoEstrictoConstancia('plantel_logo', 50, 58);
        $plantelLogo = $logoPla['uri'] ?? null;
        $dimPla      = $logoPla ? ['width' => $logoPla['width'], 'height' => $logoPla['height']] : ['width' => 0, 'height' => 0];

        $cintilloLogo = Configuracion::dataUriPublico('plantel_cintillo');
        $escudoNacionalUri = Configuracion::dataUriPublico('escudo_nacional', '/assets/img/escudo_nacional.png');

        $firmaUri = Configuracion::dataUriPrivado('director_firma');
        $selloUri = Configuracion::dataUriPrivado('institucion_sello');
        $cdceFirmaUri = Configuracion::dataUriPrivado('cdce_director_firma');
        $cdceSelloUri = Configuracion::dataUriPrivado('cdce_institucion_sello');

        return [
            'config'            => $config,
            'estudiante'        => $estudiante,
            'codigoDependencia' => $codigoDependencia,
            'ministerioLogo'    => $ministerioLogo,
            'plantelLogo'       => $plantelLogo,
            'cintilloLogo'      => $cintilloLogo,
            'escudoNacionalUri' => $escudoNacionalUri,
            'dimMin'            => $dimMin,
            'dimPla'            => $dimPla,
            'firmaUri'          => $firmaUri,
            'selloUri'          => $selloUri,
            'cdceFirmaUri'      => $cdceFirmaUri,
            'cdceSelloUri'      => $cdceSelloUri,
            'cdceDirNombre'     => $cdceDirNombre,
            'cdceDirDocTexto'   => $cdceDirDocTexto,
            'fechaNacTexto'     => $fechaNacTexto,
            'fechaExpTexto'     => $fechaExpTexto,
            'diaExp'            => $diaExp,
            'mesExpTexto'       => $mesExpTexto,
            'anioExpTexto'      => $anioExpTexto,
            'dirLetra'          => $dirLetra,
            'dirNumFormatted'   => $dirNumFormatted,
            'dirNumLimpio'      => $dirNumLimpio,
            'dirDocTexto'       => ($dirNumFormatted !== '') ? ($dirLetra . '-' . $dirNumFormatted) : '',
            'docEstEtiqueta'    => $docEstEtiqueta,
            'recuadroModo'      => $config['constancia_recuadro_modo'] ?? 'completo',
        ];
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
        $options->set('isRemoteEnabled', false); // data URIs funcionan localmente sin acceso remoto
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
