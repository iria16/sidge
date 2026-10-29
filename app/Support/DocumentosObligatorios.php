<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Documentos obligatorios de la matrícula
 * -----------------------------------------------------------------
 * Centraliza las reglas que deciden cuándo un documento del catálogo
 * `tipo_documento` es obligatorio y si ya está consignado.
 *
 * Las reglas no se escriben en los controladores para que inscripción,
 * ratificación y el expediente usen exactamente el mismo criterio. El vínculo
 * con el catálogo es por `codigo`, no por id ni por nombre: los ids no son
 * correlativos y el nombre se puede corregir desde la interfaz de catálogos.
 *
 * Cubre:
 *   - Constancia de prosecución de estudios (Regla 1).
 *   - Permiso de la LOPNNA (Regla 2).
 *   - Autorización de retiro y cédula de la persona autorizada (Regla 3).
 *
 * La Regla 3 se aplica a personas autorizadas para retirar que NO son el
 * representante legal: la cédula del representante ya está en su expediente y
 * pedirla otra vez duplicaría el mismo documento.
 */
final class DocumentosObligatorios
{
    public const CONSTANCIA_PROSECUCION = 'constancia_prosecucion';
    public const PERMISO_LOPNNA         = 'permiso_lopnna';
    public const AUTORIZACION_RETIRO    = 'autorizacion_retiro';
    public const CEDULA_AUTORIZADA      = 'cedula_autorizada';
    public const CEDULA_ALUMNO          = 'cedula_alumno';
    public const PARTIDA_NACIMIENTO     = 'partida_nacimiento';
    public const FOTO_ALUMNO           = 'foto_alumno';
    public const FOTO_REPRESENTANTE     = 'foto_representante';
    public const CONSTANCIA_VACUNA      = 'constancia_vacuna';
    public const CEDULA_REPRESENTANTE   = 'cedula_representante';

    /**
     * Nombres de respaldo. Solo se usan si el catálogo todavía no tiene el
     * código (por ejemplo, una base a la que aún no se le corrió la migración).
     * No son el criterio principal: el criterio es `codigo`.
     */
    private const NOMBRES = [
        self::CONSTANCIA_PROSECUCION => 'Constancia prosecución estudios',
        self::PERMISO_LOPNNA         => 'Permiso de la LOPNNA',
        self::AUTORIZACION_RETIRO    => 'Autorización de retiro',
        self::CEDULA_AUTORIZADA      => 'Cédula de la persona autorizada',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Documentos que faltan para una inscripción.
     *
     * @param bool        $primerIngreso     true si es la primera vez del alumno.
     * @param int|null    $idInstitucion     Institución de procedencia.
     * @param string|null $ultimoGrado        Último grado cursado en la procedencia.
     * @param array<int, array<string, mixed>> $familiares Familiares tal como llegan del formulario.
     * @param array<int|string, mixed>          $recaudos   Recaudos del formulario (id => datos).
     * @param bool $incluirRegla3 false para expedientes ya guardados, donde la
     *        Regla 3 no debe aplicarse hacia atrás.
     *
     * @return array<int, array{codigo: string, mensaje: string}>
     */
    public function faltantesInscripcion(
        bool $primerIngreso,
        ?int $idInstitucion,
        ?string $ultimoGrado,
        array $familiares,
        array $recaudos,
        bool $incluirRegla3 = true,
        ?string $fechaNacimientoAlumno = null,
        ?string $fechaInscripcion = null
    ): array {
        $faltantes = [];

        foreach ([
            self::PARTIDA_NACIMIENTO,
            self::FOTO_ALUMNO,
            self::FOTO_REPRESENTANTE,
            self::CONSTANCIA_VACUNA,
            self::CEDULA_REPRESENTANTE,
        ] as $codigoObligatorio) {
            $this->agregarSiFalta($faltantes, $codigoObligatorio, $recaudos);
        }

        $gradoOrdinal = Instituciones::ordinalGrado($ultimoGrado);
        $requiereProsecucion = false;
        if ($this->esInstitucionEscuela($idInstitucion)
            && Instituciones::esGradoDePrimaria($gradoOrdinal)) {
            if ($gradoOrdinal !== null && $gradoOrdinal >= 2) {
                $requiereProsecucion = true;
            } elseif ($gradoOrdinal === 1 && !$primerIngreso) {
                $requiereProsecucion = true;
            }
        }
        if ($requiereProsecucion) {
            $this->agregarSiFalta($faltantes, self::CONSTANCIA_PROSECUCION, $recaudos);
        }

        if ($this->alumnoTiene9AniosOMas($fechaNacimientoAlumno, $fechaInscripcion)) {
            $this->agregarSiFalta($faltantes, self::CEDULA_ALUMNO, $recaudos);
        }

        $representante = $this->representanteLegal($familiares);
        if ($this->exigePermisoLopnna($representante)) {
            $this->agregarSiFalta($faltantes, self::PERMISO_LOPNNA, $recaudos);
        }

        if ($incluirRegla3) {
            foreach ($familiares as $familiar) {
                if ($this->exigeDocumentosRetiro($familiar)) {
                    $this->agregarSiFalta($faltantes, self::AUTORIZACION_RETIRO, $recaudos);
                    $this->agregarSiFalta($faltantes, self::CEDULA_AUTORIZADA, $recaudos);
                    break;
                }
            }
        }

        return $faltantes;
    }

    /**
     * Documentos de la Regla 3 que faltan en los recaudos del estudiante (`estudiante_documento`)
     * cuando existe una persona autorizada para retirar que no es el representante legal.
     *
     * @param array<string, mixed>     $persona  Familiar ya normalizado.
     * @param array<int|string, mixed> $recaudos Recaudos del estudiante.
     *
     * @return array<int, array{codigo: string, mensaje: string}>
     */
    public function faltantesDocumentosRetiro(array $persona, array $recaudos = []): array
    {
        if (!$this->exigeDocumentosRetiro($persona)) {
            return [];
        }

        $faltantes = [];
        $this->agregarSiFalta($faltantes, self::AUTORIZACION_RETIRO, $recaudos);
        $this->agregarSiFalta($faltantes, self::CEDULA_AUTORIZADA, $recaudos);

        return $faltantes;
    }

    /**
     * ¿El alumno requiere cédula (9 años o más a la fecha de referencia)?
     *
     * Expone la regla de edad para que las estadísticas y otros consumidores no
     * dupliquen el cálculo.
     *
     * @param string|null $fechaNacimiento Fecha de nacimiento del alumno.
     * @param string|null $fechaReferencia Fecha de referencia (por defecto hoy).
     */
    public function requiereCedulaAlumno(?string $fechaNacimiento, ?string $fechaReferencia = null): bool
    {
        return $this->alumnoTiene9AniosOMas($fechaNacimiento, $fechaReferencia);
    }

    /**
     * ¿Esta persona exige autorización de retiro y cédula consignadas en recaudos?
     *
     * El representante legal siempre puede retirar, pero su cédula ya está en
     * el expediente del alumno. Pedirla de nuevo en la Regla 3 duplicaría el
     * mismo dato y confundiría a la secretaría.
     *
     * @param array<string, mixed> $persona
     */
    public function exigeDocumentosRetiro(array $persona): bool
    {
        if (strtoupper((string) ($persona['estado_relacion'] ?? 'ACTIVO')) === 'INACTIVO') {
            return false;
        }
        $autorizado = (int) ($persona['autorizado_retiros'] ?? 0) === 1;
        $representante = (int) ($persona['representante_principal'] ?? 0) === 1;
        $codigo = $this->codigoParentesco((string) ($persona['parentesco'] ?? ''));

        if ($autorizado === false || $representante === true) {
            return false;
        }
        if (in_array($codigo, ['padre', 'madre'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Documentos de una persona vinculada que faltan al guardar un alumno
     * existente. En la fase 1 solo aplica la Regla 2 (permiso LOPNNA).
     *
     * La llama el controlador únicamente cuando cambió el representante o su
     * parentesco: la regla no es retroactiva para expedientes ya guardados.
     *
     * @param array<string, mixed>|null $representante
     * @param array<int|string, mixed>  $recaudos
     *
     * @return array<int, array{codigo: string, mensaje: string}>
     */
    public function faltantesPermisoLopnna(?array $representante, array $recaudos): array
    {
        if (!$this->exigePermisoLopnna($representante)) {
            return [];
        }

        $faltantes = [];
        $this->agregarSiFalta($faltantes, self::PERMISO_LOPNNA, $recaudos);

        return $faltantes;
    }

    /**
     * Documentos que faltan en el expediente de un alumno ya inscrito.
     *
     * No bloquea nada: el expediente solo informa. La constancia se exige en
     * la inscripción; si un expediente antiguo no la tiene, se muestra como
     * pendiente para que la secretaría pueda completarla.
     *
     * @param array<int, array<string, mixed>> $recaudos
     * @param array<string, mixed>|null        $representante
     *
     * @return array<int, array{codigo: string, mensaje: string}>
     */
    public function faltantesExpediente(int $idEstudiante, array $recaudos, ?array $representante): array
    {
        $procedencia = $this->procedenciaDeEstudiante($idEstudiante);
        if ($procedencia === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.fecha_nacimiento
               FROM estudiante e
               JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstudiante]);
        $fechaNacimiento = $stmt->fetchColumn();
        $fechaNacimiento = $fechaNacimiento === false ? null : (string) $fechaNacimiento;

        return $this->faltantesInscripcion(
            (int) $procedencia['es_primer_ingreso'] === 1,
            (int) $procedencia['id_institucion'] ?: null,
            (string) ($procedencia['ult_grado_cursado'] ?? ''),
            $representante === null ? [] : [$representante],
            $recaudos,
            false,
            $fechaNacimiento,
            null
        );
    }

    /**
     * ¿El representante legal no es el padre ni la madre?
     *
     * Si no hay representante, devuelve false: la falta de representante la
     * reporta la validación de familiares, no esta regla.
     *
     * @param array<string, mixed>|null $representante
     */
    private function exigePermisoLopnna(?array $representante): bool
    {
        if ($representante === null) {
            return false;
        }

        $codigo = $this->codigoParentesco((string) ($representante['parentesco'] ?? ''));

        return !in_array($codigo, ['madre', 'padre'], true);
    }

    /**
     * Código del parentesco a partir de su nombre.
     *
     * El formulario manda el nombre porque `persona_estudiante` guarda
     * `id_parentesco`. La consulta usa `parentesco.codigo`; si el catálogo
     * todavía no tiene código, se usa el nombre normalizado como respaldo.
     */
    public function codigoParentesco(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '';
        }

        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM parentesco WHERE LOWER(TRIM(nombre)) = LOWER(:nombre) LIMIT 1'
        );
        $stmt->execute(['nombre' => $nombre]);
        $codigo = $stmt->fetchColumn();

        if ($codigo !== false && $codigo !== null && trim((string) $codigo) !== '') {
            return strtolower(trim((string) $codigo));
        }

        return strtolower($nombre);
    }

    /**
     * @param array<int, array<string, mixed>> $familiares
     *
     * @return array<string, mixed>|null
     */
    private function representanteLegal(array $familiares): ?array
    {
        foreach ($familiares as $familiar) {
            $esRepresentante = (int) (
                $familiar['representante_principal']
                ?? $familiar['es_representante_legal']
                ?? 0
            );

            if ($esRepresentante === 1) {
                return $familiar;
            }
        }

        return null;
    }

    /** ¿La institución de procedencia es una escuela primaria? */
    private function esInstitucionEscuela(?int $idInstitucion): bool
    {
        if ($idInstitucion === null || $idInstitucion <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id_tipo_institucion FROM institucion WHERE id_institucion = :id LIMIT 1'
        );
        $stmt->execute(['id' => $idInstitucion]);
        $idTipo = $stmt->fetchColumn();

        return $idTipo !== false
            && (int) $idTipo === Instituciones::idCatalogo(Instituciones::TIPO_ESCUELA);
    }

    /**
     * Procedencia de la inscripción del alumno. `uk_inscripcion_estudiante`
     * garantiza una sola fila por estudiante.
     *
     * @return array{es_primer_ingreso: mixed, id_institucion: mixed, ult_grado_cursado: mixed}|null
     */
    private function procedenciaDeEstudiante(int $idEstudiante): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT es_primer_ingreso, id_institucion, ult_grado_cursado
               FROM inscripcion
              WHERE id_estudiante = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstudiante]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }

    /**
     * Agrega el documento a la lista si no está consignado.
     *
     * @param array<int, array{codigo: string, mensaje: string}> $faltantes
     * @param array<int|string, mixed>                          $recaudos
     */
    private function agregarSiFalta(array &$faltantes, string $codigo, array $recaudos): void
    {
        $idDocumento = $this->idDocumento($codigo);
        if ($idDocumento === null) {
            /* El catálogo no tiene el documento ni por código ni por nombre.
               No se inventa un error de negocio: se reporta como configuración
               faltante para que la secretaría lo cree desde Catálogos. */
            $faltantes[] = [
                'codigo'  => $codigo,
                'mensaje' => 'Falta configurar en el catálogo el documento obligatorio: '
                    . (self::NOMBRES[$codigo] ?? $codigo) . '.',
            ];
            return;
        }

        if ($this->estaConsignado($recaudos, $idDocumento)) {
            return;
        }

        $faltantes[] = [
            'codigo'  => $codigo,
            'mensaje' => $this->mensaje($codigo),
        ];
    }

    /** Id del documento por código y, si no existe, por nombre. */
    private function idDocumento(string $codigo): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_tipo_documento
               FROM tipo_documento
              WHERE codigo = :codigo
                AND estado = \'ACTIVO\'
              LIMIT 1'
        );
        $stmt->execute(['codigo' => $codigo]);
        $id = $stmt->fetchColumn();

        if ($id !== false && $id !== null) {
            return (int) $id;
        }

        $nombre = self::NOMBRES[$codigo] ?? null;
        if ($nombre === null) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id_tipo_documento
               FROM tipo_documento
              WHERE LOWER(TRIM(nombre)) = LOWER(:nombre)
                AND estado = \'ACTIVO\'
              LIMIT 1'
        );
        $stmt->execute(['nombre' => $nombre]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Los recaudos llegan de dos formas:
     *   - formulario: [id => ['consignado' => 1], ...]
     *   - expediente: [ ['id' => 7, 'consignado' => 1], ... ]
     *
     * @param array<int|string, mixed> $recaudos
     */
    private function estaConsignado(array $recaudos, int $idDocumento): bool
    {
        if (isset($recaudos[$idDocumento]) && is_array($recaudos[$idDocumento])) {
            return !empty($recaudos[$idDocumento]['consignado']);
        }

        foreach ($recaudos as $recaudo) {
            if (!is_array($recaudo)) {
                continue;
            }

            if ((int) ($recaudo['id'] ?? 0) === $idDocumento) {
                return !empty($recaudo['consignado']);
            }
        }

        return false;
    }

    private function mensaje(string $codigo): string
    {
        return match ($codigo) {
            self::CONSTANCIA_PROSECUCION =>
                'Falta la constancia de prosecución de estudios: el estudiante viene '
                . 'de otra institución y cursó un grado de primaria.',
            self::PERMISO_LOPNNA =>
                'Falta el permiso de la LOPNNA: el representante legal no es el padre '
                . 'ni la madre del estudiante.',
            self::AUTORIZACION_RETIRO =>
                'Falta la autorización de retiro de la persona autorizada.',
            self::CEDULA_AUTORIZADA =>
                'Falta la cédula de la persona autorizada para retirar al estudiante.',
            default => 'Falta un documento obligatorio: ' . $codigo . '.',
        };
    }

    private function alumnoTiene9AniosOMas(?string $fechaNacimiento, ?string $fechaInscripcion): bool
    {
        if ($fechaNacimiento === null || !$this->fechaValida($fechaNacimiento)) {
            return false;
        }

        $referencia = $fechaInscripcion !== null && $this->fechaValida($fechaInscripcion)
            ? $fechaInscripcion
            : date('Y-m-d');

        try {
            $nac = new \DateTimeImmutable($fechaNacimiento);
            $ref = new \DateTimeImmutable($referencia);
        } catch (\Exception $e) {
            return false;
        }

        return (int) $nac->diff($ref)->y >= 9;
    }

    private function fechaValida(string $valor): bool
    {
        $fecha = \DateTimeImmutable::createFromFormat('Y-m-d', $valor);

        return $fecha !== false && $fecha->format('Y-m-d') === $valor;
    }
}
