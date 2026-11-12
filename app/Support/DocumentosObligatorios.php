<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

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
     * Únicos documentos que pueden ser obligatorios, y solo en el caso que los exige.
     * Cualquier otro (fotos, partida, vacuna, cédulas) es opcional y nunca bloquea.
     */
    public const CODIGOS_OBLIGATORIOS = [
        self::CONSTANCIA_PROSECUCION,
        self::PERMISO_LOPNNA,
        self::AUTORIZACION_RETIRO,
        self::CEDULA_AUTORIZADA,
    ];

    /**
     * Evalúa el snapshot de recaudos de una inscripción nueva.
     *
     * @param array<int, array<string, mixed>> $familiares
     * @return array<int, array{
     *   clave:string,codigo:string,id_tipo_documento:int,nombre:string,
     *   requerido:bool,documento_persona:?string,persona_nombre:?string
     * }>
     */
    public function evaluarInscripcion(
        int $idGradoDestino,
        ?string $fechaNacimientoAlumno,
        array $familiares,
        ?string $fechaInscripcion = null,
        ?int $idInstitucionProcedencia = null,
        ?string $ultimoGradoCursado = null,
        bool $esPrimerIngreso = false
    ): array {
        $catalogo = $this->catalogoPorCodigo();
        $instancias = [];

        foreach ([
            self::FOTO_ALUMNO,
            self::PARTIDA_NACIMIENTO,
            self::CONSTANCIA_VACUNA,
        ] as $codigo) {
            $instancias[] = $this->instancia($catalogo, $codigo, false);
        }

        $representante = $this->representanteLegal($familiares);
        if ($representante !== null) {
            foreach ([self::FOTO_REPRESENTANTE, self::CEDULA_REPRESENTANTE] as $codigo) {
                $instancias[] = $this->instanciaPersona($catalogo, $codigo, false, $representante);
            }

            if ($this->exigePermisoLopnna($representante)) {
                $instancias[] = $this->instanciaPersona($catalogo, self::PERMISO_LOPNNA, true, $representante);
            }
        } else {
            foreach ([self::FOTO_REPRESENTANTE, self::CEDULA_REPRESENTANTE] as $codigo) {
                $instancias[] = $this->instancia($catalogo, $codigo, false);
            }
        }

        $nombreGrado = null;
        if ($idGradoDestino > 0) {
            $consulta = $this->pdo->prepare(
                'SELECT nombre FROM grado WHERE id_grado = :id LIMIT 1'
            );
            $consulta->execute(['id' => $idGradoDestino]);
            $nombre = $consulta->fetchColumn();
            $nombreGrado = $nombre === false ? null : (string) $nombre;
        }
        $ordinalGrado = $nombreGrado !== null ? Instituciones::ordinalGrado($nombreGrado) : null;
        if ($this->exigeConstanciaProsecucion(
            $ordinalGrado,
            $ultimoGradoCursado,
            $esPrimerIngreso,
            $idInstitucionProcedencia
        )) {
            $instancias[] = $this->instancia($catalogo, self::CONSTANCIA_PROSECUCION, true);
        }

        if ($this->alumnoTiene9AniosOMas($fechaNacimientoAlumno, $fechaInscripcion ?? date('Y-m-d'))) {
            $instancias[] = $this->instancia($catalogo, self::CEDULA_ALUMNO, false);
        }

        foreach ($familiares as $familiar) {
            if (!$this->exigeRecaudosPorAutorizado($familiar)) {
                continue;
            }
            $instancias[] = $this->instanciaPersona($catalogo, self::AUTORIZACION_RETIRO, true, $familiar);
            $instancias[] = $this->instanciaPersona($catalogo, self::CEDULA_AUTORIZADA, true, $familiar);
        }

        return $instancias;
    }

    /**
     * Devuelve los faltantes obligatorios de una evaluación. Los opcionales se
     * conservan pendientes en el snapshot, pero no bloquean la inscripción.
     *
     * @param array<int, array<string, mixed>> $evaluacion
     * @param array<string, array<string, mixed>> $estados clave => consignación
     * @return array<int, array{clave:string,nombre:string,persona_nombre:?string}>
     */
    public function faltantesObligatorios(array $evaluacion, array $estados): array
    {
        $faltantes = [];
        foreach ($evaluacion as $recaudo) {
            $clave = (string) $recaudo['clave'];
            if (!empty($recaudo['requerido'])
                && in_array((string) ($recaudo['codigo'] ?? ''), self::CODIGOS_OBLIGATORIOS, true)
                && empty($estados[$clave]['consignado'])) {
                $faltantes[] = [
                    'clave' => $clave,
                    'nombre' => (string) $recaudo['nombre'],
                    'persona_nombre' => $recaudo['persona_nombre'],
                ];
            }
        }

        return $faltantes;
    }

    /**
     * Comprueba que el estado enviado corresponda a la evaluación recalculada
     * en el servidor y devuelve los recaudos obligatorios pendientes.
     *
     * @param array<int, array<string, mixed>> $evaluacion
     * @param array<string, array<string, mixed>> $estados
     * @return array{lista_desactualizada:bool,faltantes:array<int,array{clave:string,nombre:string,persona_nombre:?string}>}
     */
    public function validarSnapshotInscripcion(array $evaluacion, array $estados): array
    {
        $clavesEsperadas = array_column($evaluacion, 'clave');

        return [
            'lista_desactualizada' => array_diff(array_keys($estados), $clavesEsperadas) !== [],
            'faltantes' => $this->faltantesObligatorios($evaluacion, $estados),
        ];
    }

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
    /**
     * Códigos de documentos requeridos para una inscripción, según los datos del estudiante.
     *
     * @return string[]
     */
    public function codigosRequeridosInscripcion(
        bool $primerIngreso,
        ?int $idInstitucion,
        ?string $ultimoGrado,
        array $familiares,
        ?string $fechaNacimientoAlumno = null,
        ?string $fechaInscripcion = null,
        ?int $ordinalGradoDestino = null
    ): array {
        /* Solo hay obligatorios en los cuatro casos de la regla de negocio (prosecución,
           permiso LOPNNA, autorización de retiro y cédula del autorizado). Foto, partida,
           vacuna y cédula del representante son opcionales y nunca bloquean. */
        $requeridos = [];

        if ($this->exigeConstanciaProsecucion($ordinalGradoDestino, $ultimoGrado, $primerIngreso, $idInstitucion)) {
            $requeridos[] = self::CONSTANCIA_PROSECUCION;
        }

        $representante = $this->representanteLegal($familiares);
        if ($this->exigePermisoLopnna($representante)) {
            $requeridos[] = self::PERMISO_LOPNNA;
        }

        foreach ($familiares as $familiar) {
            if ($this->exigeDocumentosRetiro($familiar)) {
                $requeridos[] = self::AUTORIZACION_RETIRO;
                $requeridos[] = self::CEDULA_AUTORIZADA;
                break;
            }
        }

        return array_values(array_unique($requeridos));
    }

    public function faltantesInscripcion(
        bool $primerIngreso,
        ?int $idInstitucion,
        ?string $ultimoGrado,
        array $familiares,
        array $recaudos,
        bool $incluirRegla3 = true,
        ?string $fechaNacimientoAlumno = null,
        ?string $fechaInscripcion = null,
        ?int $ordinalGradoDestino = null
    ): array {
        $requeridos = $this->codigosRequeridosInscripcion(
            $primerIngreso,
            $idInstitucion,
            $ultimoGrado,
            $familiares,
            $fechaNacimientoAlumno,
            $fechaInscripcion,
            $ordinalGradoDestino
        );

        $faltantes = [];
        foreach ($requeridos as $codigo) {
            if ($codigo === self::AUTORIZACION_RETIRO || $codigo === self::CEDULA_AUTORIZADA) {
                if (!$incluirRegla3) {
                    continue;
                }
            }
            $this->agregarSiFalta($faltantes, $codigo, $recaudos);
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
            'SELECT p.fecha_nacimiento,
                    (SELECT g.nombre
                       FROM inscripcion i
                       JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                       JOIN grado g ON g.id_grado = ea.id_grado
                      WHERE i.id_estudiante = e.id_estudiante
                      LIMIT 1) AS grado_inscripcion
               FROM estudiante e
               JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstudiante]);
        $datos = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $fechaNacimiento = isset($datos['fecha_nacimiento']) ? (string) $datos['fecha_nacimiento'] : null;
        /* El grado al que entró decide si fue repitente: se compara con el último que cursó. */
        $ordinalDestino = Instituciones::ordinalGrado((string) ($datos['grado_inscripcion'] ?? ''));

        return $this->faltantesInscripcion(
            (int) $procedencia['es_primer_ingreso'] === 1,
            (int) $procedencia['id_institucion_procedencia'] ?: null,
            (string) ($procedencia['ult_grado_cursado'] ?? ''),
            $representante === null ? [] : [$representante],
            $recaudos,
            false,
            $fechaNacimiento,
            null,
            $ordinalDestino
        );
    }

    /**
     * Regla 1: la constancia de prosecución se exige solo si el estudiante NO es repitente
     * (el último grado que cursó es distinto al grado al que entra) y viene de OTRA
     * escuela. Aplica a 2.º–6.º grado; no es primer ingreso.
     */
    private function exigeConstanciaProsecucion(
        ?int $ordinalGradoDestino,
        ?string $ultimoGradoCursado,
        bool $esPrimerIngreso,
        ?int $idInstitucionProcedencia
    ): bool {
        if ($ordinalGradoDestino === null || $ordinalGradoDestino < 2 || $ordinalGradoDestino > 6) {
            return false;
        }
        if ($esPrimerIngreso) {
            return false;
        }
        $esRepitente = Instituciones::ordinalGrado($ultimoGradoCursado) === $ordinalGradoDestino;

        return !$esRepitente && $this->esInstitucionEscuela($idInstitucionProcedencia);
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
     * La inscripción exige ambos documentos a cada autorizado no parental,
     * excepto al representante legal, cuya autorización para retirar es automática.
     *
     * @param array<string, mixed> $persona
     */
    private function exigeRecaudosPorAutorizado(array $persona): bool
    {
        if (strtoupper((string) ($persona['estado_relacion'] ?? 'ACTIVO')) === 'INACTIVO') {
            return false;
        }

        if ((int) ($persona['representante_principal'] ?? 0) === 1) {
            return false;
        }

        $autorizado = (int) ($persona['autorizado_retiros'] ?? 0) === 1;
        $parentesco = $this->codigoParentesco((string) ($persona['parentesco'] ?? ''));

        return $autorizado && !in_array($parentesco, ['madre', 'padre'], true);
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

        if (ctype_digit($nombre)) {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(codigo), ''), nombre)
                   FROM parentesco
                  WHERE id_parentesco = :id
                  LIMIT 1"
            );
            $stmt->execute(['id' => (int) $nombre]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT codigo FROM parentesco WHERE LOWER(TRIM(nombre)) = LOWER(:nombre) LIMIT 1'
            );
            $stmt->execute(['nombre' => $nombre]);
        }
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
     * @return array{es_primer_ingreso: mixed, id_institucion_procedencia: mixed, ult_grado_cursado: mixed}|null
     */
    private function procedenciaDeEstudiante(int $idEstudiante): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT es_primer_ingreso, id_institucion_procedencia, ult_grado_cursado
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

        $claveLegada = 'tipo_' . $idDocumento;
        if (isset($recaudos[$claveLegada]) && is_array($recaudos[$claveLegada])) {
            return !empty($recaudos[$claveLegada]['consignado']);
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

    /**
     * @return array<string, array{id:int,nombre:string}>
     */
    private function catalogoPorCodigo(): array
    {
        $filas = $this->pdo->query(
            "SELECT id_tipo_documento, nombre, codigo
               FROM tipo_documento
              WHERE estado = 'ACTIVO' AND codigo IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);
        $catalogo = [];
        foreach ($filas as $fila) {
            $catalogo[(string) $fila['codigo']] = [
                'id' => (int) $fila['id_tipo_documento'],
                'nombre' => (string) $fila['nombre'],
            ];
        }

        return $catalogo;
    }

    /**
     * @param array<string, array{id:int,nombre:string}> $catalogo
     * @return array{clave:string,codigo:string,id_tipo_documento:int,nombre:string,requerido:bool,documento_persona:?string,persona_nombre:?string}
     */
    private function instancia(array $catalogo, string $codigo, bool $requerido): array
    {
        return $this->crearInstancia($catalogo, $codigo, $requerido, null);
    }

    /**
     * @param array<string, array{id:int,nombre:string}> $catalogo
     * @param array<string, mixed> $persona
     * @return array{clave:string,codigo:string,id_tipo_documento:int,nombre:string,requerido:bool,documento_persona:?string,persona_nombre:?string}
     */
    private function instanciaPersona(array $catalogo, string $codigo, bool $requerido, array $persona): array
    {
        return $this->crearInstancia($catalogo, $codigo, $requerido, $persona);
    }

    /**
     * @param array<string, array{id:int,nombre:string}> $catalogo
     * @param array<string, mixed>|null $persona
     * @return array{clave:string,codigo:string,id_tipo_documento:int,nombre:string,requerido:bool,documento_persona:?string,persona_nombre:?string}
     */
    private function crearInstancia(array $catalogo, string $codigo, bool $requerido, ?array $persona): array
    {
        if (!isset($catalogo[$codigo])) {
            throw new RuntimeException(
                "El recaudo con código «{$codigo}» no está activo en Configuración → Tipo de Documento."
            );
        }

        $numero = $persona !== null
            ? preg_replace('/\D/', '', (string) ($persona['numero_documento'] ?? ''))
            : null;
        if ($persona !== null && ($numero === null || $numero === '')) {
            throw new RuntimeException('No se pudo identificar a la persona asociada al recaudo.');
        }

        $nombrePersona = $persona === null
            ? null
            : trim(implode(' ', array_filter([
                $persona['primer_nombre'] ?? '',
                $persona['segundo_nombre'] ?? '',
                $persona['primer_apellido'] ?? '',
                $persona['segundo_apellido'] ?? '',
            ], static fn (mixed $parte): bool => trim((string) $parte) !== '')));
        $clave = $persona === null
            ? $codigo
            : $codigo . ':' . hash('sha256', $codigo . ':' . $numero);

        return [
            'clave' => $clave,
            'codigo' => $codigo,
            'id_tipo_documento' => $catalogo[$codigo]['id'],
            'nombre' => $catalogo[$codigo]['nombre'],
            'requerido' => $requerido && in_array($codigo, self::CODIGOS_OBLIGATORIOS, true),
            'documento_persona' => $persona === null ? null : (string) $numero,
            'persona_nombre' => $nombrePersona,
        ];
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
