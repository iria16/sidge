<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Support/Instituciones.php';
require_once __DIR__ . '/../app/Support/DocumentosObligatorios.php';
require_once __DIR__ . '/../app/Support/Documento.php';
require_once __DIR__ . '/../app/Support/Tallas.php';
require_once __DIR__ . '/../app/Traits/RecaudosModelTrait.php';
require_once __DIR__ . '/../app/Support/Matriculas.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/StatisticsModel.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use App\Support\DocumentosObligatorios;
use App\Support\Tallas;
use App\Models\StatisticsModel;
use App\Models\EnrollmentModel;
use App\Controllers\BaseController;
use App\Controllers\EnrollmentController;
use App\Traits\RecaudosModelTrait;

final class EnrollmentTestStatement extends PDOStatement
{
    private mixed $result = false;

    public function __construct(private readonly Closure $resolver)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->result = ($this->resolver)($params ?? []);

        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (!is_array($this->result) || $this->result === []) {
            return $this->result;
        }

        $row = $this->result[0] ?? $this->result;

        return is_array($row) ? (array_values($row)[$column] ?? false) : $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return is_array($this->result) ? $this->result : [];
    }
}

final class EnrollmentTestPdo extends PDO
{
    private const CATALOG = [
        DocumentosObligatorios::FOTO_ALUMNO,
        DocumentosObligatorios::PARTIDA_NACIMIENTO,
        DocumentosObligatorios::CONSTANCIA_VACUNA,
        DocumentosObligatorios::FOTO_REPRESENTANTE,
        DocumentosObligatorios::CEDULA_REPRESENTANTE,
        DocumentosObligatorios::CONSTANCIA_PROSECUCION,
        DocumentosObligatorios::CEDULA_ALUMNO,
        DocumentosObligatorios::PERMISO_LOPNNA,
        DocumentosObligatorios::AUTORIZACION_RETIRO,
        DocumentosObligatorios::CEDULA_AUTORIZADA,
    ];

    public function __construct(
        private readonly array $grades = [1 => '1er Grado', 3 => '3er Grado'],
        private readonly int $institutionType = 1,
        private readonly array $relationships = [
            'madre' => 'madre',
            'padre' => 'padre',
            'abuela' => 'abuela',
            'tío' => 'tio',
        ]
    ) {
    }

    public function query(
        string $query,
        ?int $fetchMode = null,
        mixed ...$fetchModeArgs
    ): PDOStatement|false {
        if (!str_contains($query, 'FROM tipo_documento')) {
            throw new RuntimeException('Consulta inesperada en prueba: ' . $query);
        }

        $rows = [];
        foreach (self::CATALOG as $index => $code) {
            $rows[] = [
                'id_tipo_documento' => $index + 1,
                'nombre' => $code,
                'codigo' => $code,
            ];
        }

        $statement = new EnrollmentTestStatement(static fn (): array => $rows);
        $statement->execute();

        return $statement;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new EnrollmentTestStatement(function (array $params) use ($query): mixed {
            if (str_contains($query, 'FROM grado')) {
                return $this->grades[(int) ($params['id'] ?? 0)] ?? false;
            }
            if (str_contains($query, 'FROM institucion')) {
                return $this->institutionType;
            }
            if (str_contains($query, 'FROM parentesco')) {
                return $this->relationships[strtolower((string) ($params['nombre'] ?? ''))] ?? false;
            }

            throw new RuntimeException('Consulta preparada inesperada en prueba: ' . $query);
        });
    }
}

final class PersistenceTestStatement extends PDOStatement
{
    private mixed $result = false;

    public function __construct(private readonly Closure $resolver)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->result = ($this->resolver)($params ?? []);

        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (!is_array($this->result) || $this->result === []) {
            return $this->result;
        }

        $row = $this->result[0] ?? $this->result;

        return is_array($row) ? (array_values($row)[$column] ?? false) : $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return is_array($this->result) ? $this->result : [];
    }
}

final class SnapshotTestPdo extends PDO
{
    public array $inserts = [];
    public bool $evaluacionMarcada = false;

    private array $relaciones = [
        ['id_persona_estudiante' => 77, 'numero_documento' => '12345678'],
        ['id_persona_estudiante' => 88, 'numero_documento' => '11111111'],
        ['id_persona_estudiante' => 99, 'numero_documento' => '22222222'],
    ];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FROM persona_estudiante pe')) {
            return new PersistenceTestStatement(fn (): array => $this->relaciones);
        }
        if (str_contains($query, 'INSERT INTO estudiante_documento')) {
            return new PersistenceTestStatement(function (array $params): bool {
                $this->inserts[] = $params;

                return true;
            });
        }
        if (str_contains($query, 'UPDATE inscripcion')) {
            return new PersistenceTestStatement(function (): bool {
                $this->evaluacionMarcada = true;

                return true;
            });
        }

        throw new RuntimeException('Consulta de persistencia inesperada: ' . $query);
    }
}

final class SnapshotTestModel
{
    use RecaudosModelTrait;

    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function guardar(int $estudianteId, array $evaluacion, array $estados): void
    {
        $this->guardarSnapshotRecaudos($estudianteId, $evaluacion, $estados, 5);
    }
}

final class EnrollmentControllerTestModel extends EnrollmentModel
{
    public function getAnioActivo(): array
    {
        return ['id' => 1, 'nombre' => '2026-2027', 'anio_inicio' => '2026-08-01'];
    }
}

final class EnrollmentControllerHarness extends EnrollmentController
{
    public function parse(array $source): array
    {
        return $this->extractData($source);
    }
}

final class StatisticsTestPdo extends PDO
{
    public string $query = '';

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->query = $query;

        return new PersistenceTestStatement(static fn (): array => [
            [
                'id_estudiante_documento' => 1,
                'id_estudiante' => 101,
                'id_tipo_documento' => 2,
                'codigo' => 'foto_alumno',
                'documento' => 'Foto del alumno',
            ],
            [
                'id_estudiante_documento' => 2,
                'id_estudiante' => 101,
                'id_tipo_documento' => 8,
                'codigo' => 'cedula_alumno',
                'documento' => 'Cédula del alumno',
            ],
            [
                'id_estudiante_documento' => 3,
                'id_estudiante' => 202,
                'id_tipo_documento' => 2,
                'codigo' => 'foto_alumno',
                'documento' => 'Foto del alumno',
            ],
        ]);
    }
}

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function recaudoPorCodigo(array $recaudos, string $codigo, ?string $documento = null): ?array
{
    foreach ($recaudos as $recaudo) {
        if ($recaudo['codigo'] === $codigo
            && ($documento === null || $recaudo['documento_persona'] === $documento)) {
            return $recaudo;
        }
    }

    return null;
}

function representante(string $parentesco = 'Madre'): array
{
    return [
        'representante_principal' => 1,
        'parentesco' => $parentesco,
        'primer_nombre' => 'Ana',
        'primer_apellido' => 'Pérez',
        'numero_documento' => '12345678',
    ];
}

$pruebas = 0;
$erroresTalla = Tallas::errores([
    'camisa' => 'XL',
    'pantalon' => '',
    'zapato' => '40',
]);
comprobar($erroresTalla === [], 'Las tallas válidas y vacías opcionales deben aceptarse.');
$erroresTalla = Tallas::errores([
    'camisa' => 'XXL',
    'pantalon' => '42',
    'zapato' => '41',
]);
comprobar(
    array_keys($erroresTalla) === ['talla_camisa', 'talla_pantalon', 'talla_zapato'],
    'Las tallas fuera del ENUM deben rechazarse en el backend.'
);
$erroresTalla = Tallas::errores(['camisa' => ['XL']]);
comprobar(isset($erroresTalla['talla_camisa']), 'El backend debe rechazar tipos no escalares.');
$schema = file_get_contents(__DIR__ . '/../database/schema_actual.sql');
$opcionesTalla = [
    'camisa' => array_merge([''], Tallas::CAMISA),
    'pantalon' => array_merge([''], Tallas::PANTALON),
    'zapato' => array_merge([''], Tallas::zapato()),
];
foreach ($opcionesTalla as $prenda => $esperadas) {
    comprobar(
        preg_match('/`talla_' . $prenda . '` enum\\(([^)]*)\\)/i', $schema, $coincidencia) === 1,
        "El esquema debe declarar talla_{$prenda} como ENUM."
    );
    comprobar(
        str_getcsv($coincidencia[1], ',', "'", '\\') === $esperadas,
        "Las opciones del ENUM de talla_{$prenda} deben coincidir con Support/Tallas."
    );
}
$pruebas++;

$reglas = new DocumentosObligatorios(new EnrollmentTestPdo());

$base = $reglas->evaluarInscripcion(
    1,
    '2018-06-01',
    [representante()],
    '2026-11-02'
);
$codigosBase = [
    DocumentosObligatorios::FOTO_ALUMNO,
    DocumentosObligatorios::PARTIDA_NACIMIENTO,
    DocumentosObligatorios::CONSTANCIA_VACUNA,
    DocumentosObligatorios::FOTO_REPRESENTANTE,
    DocumentosObligatorios::CEDULA_REPRESENTANTE,
];
foreach ($codigosBase as $codigo) {
    $item = recaudoPorCodigo($base, $codigo);
    comprobar($item !== null && $item['requerido'], "El recaudo base {$codigo} debe ser obligatorio.");
}
comprobar(
    recaudoPorCodigo($base, DocumentosObligatorios::PERMISO_LOPNNA) === null,
    'Madre o padre como representante no debe exigir permiso LOPNNA.'
);
$pruebas++;

$primaria = (new DocumentosObligatorios(new EnrollmentTestPdo(
    institutionType: 8
)))->evaluarInscripcion(
    3,
    '2018-06-01',
    [representante()],
    '2026-11-02',
    10,
    '2do Grado'
);
$prosecucion = recaudoPorCodigo($primaria, DocumentosObligatorios::CONSTANCIA_PROSECUCION);
comprobar($prosecucion !== null && $prosecucion['requerido'], 'Primaria debe exigir prosecución.');
$pruebas++;

$preescolar = (new DocumentosObligatorios(new EnrollmentTestPdo(
    institutionType: 1
)))->evaluarInscripcion(
    1,
    '2018-06-01',
    [representante()],
    '2026-11-02',
    10,
    'Educación Inicial'
);
comprobar(
    recaudoPorCodigo($preescolar, DocumentosObligatorios::CONSTANCIA_PROSECUCION) === null,
    'Preescolar no debe exigir constancia de prosecución.'
);
$pruebas++;

$edadExacta = $reglas->evaluarInscripcion(
    1,
    '2017-11-02',
    [representante()],
    '2026-11-02'
);
$cedulaAlumno = recaudoPorCodigo($edadExacta, DocumentosObligatorios::CEDULA_ALUMNO);
comprobar($cedulaAlumno !== null && !$cedulaAlumno['requerido'], 'A los 9 años la cédula debe aparecer opcional.');
$pruebas++;

$menorDeNueve = $reglas->evaluarInscripcion(
    1,
    '2017-11-03',
    [representante()],
    '2026-11-02'
);
comprobar(
    recaudoPorCodigo($menorDeNueve, DocumentosObligatorios::CEDULA_ALUMNO) === null,
    'La cédula del estudiante no debe aparecer antes de cumplir 9 años.'
);
$pruebas++;

$representanteNoParental = $reglas->evaluarInscripcion(
    1,
    '2018-06-01',
    [representante('Abuela')],
    '2026-11-02'
);
$permiso = recaudoPorCodigo($representanteNoParental, DocumentosObligatorios::PERMISO_LOPNNA);
comprobar($permiso !== null && $permiso['requerido'], 'Representante no parental debe exigir permiso LOPNNA.');
$pruebas++;

$familiares = [
    representante(),
    [
        'parentesco' => 'Tío',
        'numero_documento' => '11111111',
        'primer_nombre' => 'Luis',
        'autorizado_retiros' => 1,
    ],
    [
        'parentesco' => 'Abuela',
        'numero_documento' => '22222222',
        'primer_nombre' => 'Rosa',
        'autorizado_retiros' => 1,
    ],
    [
        'parentesco' => 'Madre',
        'numero_documento' => '55555555',
        'autorizado_retiros' => 1,
    ],
    [
        'parentesco' => 'Abuela',
        'numero_documento' => '33333333',
        'autorizado_retiros' => 0,
    ],
    [
        'parentesco' => 'Tío',
        'numero_documento' => '44444444',
        'autorizado_retiros' => 1,
        'estado_relacion' => 'INACTIVO',
    ],
];
$autorizados = $reglas->evaluarInscripcion(1, '2018-06-01', $familiares, '2026-11-02');
$recaudosDeAutorizados = array_values(array_filter(
    $autorizados,
    static fn (array $recaudo): bool => in_array(
        $recaudo['codigo'],
        [DocumentosObligatorios::AUTORIZACION_RETIRO, DocumentosObligatorios::CEDULA_AUTORIZADA],
        true
    )
));
comprobar(count($recaudosDeAutorizados) === 4, 'Deben generarse dos recaudos por cada autorizado activo no parental.');
foreach (['11111111', '22222222'] as $documento) {
    foreach ([DocumentosObligatorios::AUTORIZACION_RETIRO, DocumentosObligatorios::CEDULA_AUTORIZADA] as $codigo) {
        $item = recaudoPorCodigo($autorizados, $codigo, $documento);
        comprobar($item !== null && $item['requerido'], "{$codigo} debe ser obligatorio para {$documento}.");
    }
}
$pruebas++;

$estados = [];
foreach ($base as $recaudo) {
    $estados[$recaudo['clave']] = ['consignado' => 1];
}
$cedulaAlumno = recaudoPorCodigo($edadExacta, DocumentosObligatorios::CEDULA_ALUMNO);
$estados[$cedulaAlumno['clave']] = ['consignado' => 0];
$faltantes = $reglas->faltantesObligatorios($edadExacta, $estados);
comprobar($faltantes === [], 'La cédula opcional pendiente no debe bloquear la inscripción.');
$faltantes = $reglas->faltantesObligatorios($edadExacta, []);
comprobar(
    count($faltantes) === count($codigosBase),
    'Los cinco recaudos base pendientes deben bloquear la inscripción.'
);
$pruebas++;

$validacion = $reglas->validarSnapshotInscripcion($edadExacta, $estados);
comprobar(
    !$validacion['lista_desactualizada'] && $validacion['faltantes'] === [],
    'Una cédula opcional pendiente no debe bloquear el snapshot vigente.'
);
$estados['recaudo_falso'] = ['consignado' => 1];
$validacion = $reglas->validarSnapshotInscripcion($edadExacta, $estados);
comprobar($validacion['lista_desactualizada'], 'El servidor debe rechazar claves de recaudo inventadas.');
$pruebas++;

$controllerPdo = new EnrollmentTestPdo();
$controller = (new ReflectionClass(EnrollmentControllerHarness::class))->newInstanceWithoutConstructor();
$modelProperty = new ReflectionProperty(BaseController::class, 'model');
$modelProperty->setAccessible(true);
$modelProperty->setValue($controller, new EnrollmentControllerTestModel($controllerPdo));
$pdoProperty = new ReflectionProperty(BaseController::class, 'pdo');
$pdoProperty->setAccessible(true);
$pdoProperty->setValue($controller, $controllerPdo);
$datosRecalculados = $controller->parse([
    'tipo_documento' => 'V',
    'numero_documento' => '87654321',
    'fecha_nacimiento' => '2017-11-02',
    'primer_nombre' => 'Prueba',
    'primer_apellido' => 'Estudiante',
    'sexo' => 'F',
    'id_grado' => 1,
    'familiares_json' => json_encode([[
        'representante_principal' => 1,
        'parentesco' => 'Madre',
        'primer_nombre' => 'Ana',
        'primer_apellido' => 'Pérez',
        'numero_documento' => '12345678',
    ]], JSON_THROW_ON_ERROR),
    'recaudos_evaluacion' => [['clave' => 'inventado', 'requerido' => false]],
    'recaudos_json' => json_encode(['recaudo_falso' => ['consignado' => 1]], JSON_THROW_ON_ERROR),
]);
$cedulaRecalculada = recaudoPorCodigo(
    $datosRecalculados['recaudos_evaluacion'],
    DocumentosObligatorios::CEDULA_ALUMNO
);
comprobar(
    $cedulaRecalculada !== null && !$cedulaRecalculada['requerido'],
    'El controlador debe recalcular la cédula opcional desde la fecha recibida, no aceptar la evaluación cliente.'
);
$validacionManipulada = $reglas->validarSnapshotInscripcion(
    $datosRecalculados['recaudos_evaluacion'],
    $datosRecalculados['recaudos']
);
comprobar(
    $validacionManipulada['lista_desactualizada'],
    'El backend debe detectar las claves falsificadas del snapshot enviado.'
);
$pruebas++;

$snapshotEvaluacion = $reglas->evaluarInscripcion(
    1,
    '2017-11-02',
    $familiares,
    '2026-11-02'
);
$snapshotEstados = [];
foreach ($snapshotEvaluacion as $recaudo) {
    $snapshotEstados[$recaudo['clave']] = [
        'consignado' => $recaudo['requerido'] ? 1 : 0,
        'observacion' => 'prueba',
    ];
}
$snapshotPdo = new SnapshotTestPdo();
(new SnapshotTestModel($snapshotPdo))->guardar(501, $snapshotEvaluacion, $snapshotEstados);
comprobar(
    count($snapshotPdo->inserts) === count($snapshotEvaluacion),
    'Debe persistirse una fila por cada instancia evaluada, incluida la opcional.'
);
comprobar($snapshotPdo->evaluacionMarcada, 'La inscripción debe quedar marcada como evaluada.');
foreach ($snapshotEvaluacion as $recaudo) {
    $fila = current(array_filter(
        $snapshotPdo->inserts,
        static fn (array $insert): bool => (int) $insert['tipo'] === $recaudo['id_tipo_documento']
            && (int) $insert['persona_estudiante_unico'] === (
                $recaudo['documento_persona'] === null
                    ? 0
                    : match ($recaudo['documento_persona']) {
                        '12345678' => 77,
                        '11111111' => 88,
                        '22222222' => 99,
                        default => -1,
                    }
            )
    ));
    comprobar(is_array($fila), 'Cada recaudo debe guardarse con el vínculo personal esperado.');
    comprobar(
        (int) $fila['requerido'] === ($recaudo['requerido'] ? 1 : 0),
        'El snapshot debe conservar si cada recaudo era obligatorio.'
    );
    comprobar(
        (int) $fila['consignado'] === ($recaudo['requerido'] ? 1 : 0),
        'El snapshot debe conservar consignado y opcional pendiente.'
    );
}
$pruebas++;

$statisticsPdo = new StatisticsTestPdo();
$estadisticas = (new StatisticsModel($statisticsPdo))->getDocumentosPendientes(2);
comprobar(
    str_contains($statisticsPdo->query, 'ed.requerido IS NOT NULL')
        && str_contains($statisticsPdo->query, 'ed.consignado = 0')
        && str_contains($statisticsPdo->query, 'i.documentos_evaluados_en IS NOT NULL'),
    'Estadísticas debe consultar snapshots evaluados y pendientes, incluso opcionales.'
);
comprobar(
    $estadisticas['estudiantes_con_deuda'] === 2
        && count($estadisticas['documentos']) === 2,
    'Las estadísticas deben contar estudiantes y tipos de recaudo pendientes.'
);
$pendientesPorNombre = [];
foreach ($estadisticas['documentos'] as $documento) {
    $pendientesPorNombre[$documento['documento']] = $documento['pendientes'];
}
comprobar(
    ($pendientesPorNombre['Foto del alumno'] ?? 0) === 2
        && ($pendientesPorNombre['Estudiantes sin cédula (9 años o más)'] ?? 0) === 1,
    'Las estadísticas deben incluir y etiquetar los recaudos opcionales pendientes.'
);
$pruebas++;

echo "{$pruebas} pruebas de recaudos aprobadas.\n";
