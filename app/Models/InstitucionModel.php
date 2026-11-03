<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Instituciones;
use PDO;

/**
 * InstitucionModel Catálogo `institucion`: es el único catálogo del sistema con un
 * campo extra obligatorio (el tipo), así que sobrescribe create() de CatalogModel para
 * incluirlo. El resto (Listable, Toggleable, existeNombre, getPrimaryKey, búsqueda por
 * nombre) lo hereda igual que los demás catálogos.
 */
class InstitucionModel extends CatalogModel
{
    protected string $table      = 'institucion';
    protected string $primaryKey = 'id_institucion';

    protected array $columns = ['id_institucion', 'nombre', 'estado', 'id_tipo_institucion', 'codigo_dea', 'es_extranjera', 'id_estado_geo', 'id_municipio', 'id_parroquia'];
    protected array $allowedColumns = ['id_institucion', 'nombre', 'estado', 'id_tipo_institucion', 'codigo_dea', 'es_extranjera', 'id_estado_geo', 'id_municipio', 'id_parroquia'];

    /**
 * Opciones de tipo para el <select> de alta rápida, en el formato que esperan los
 * modales: el código del enum (lo que viaja en el POST) y su texto. No consulta la
 * base: la lista sale del enum, que es quien sabe qué tipos existen y qué regla tiene
 * cada uno.
 *
 * @param array<int, string> $tipos Códigos del enum a ofrecer.
 * @return array<int, array{tipo: string, nombre: string}>
 */
    public function tiposFormulario(array $tipos): array
    {
        return array_map(
            static fn (string $tipo): array => [
                'tipo'   => $tipo,
                'nombre' => Instituciones::etiqueta($tipo),
            ],
            array_values($tipos)
        );
    }

    /**
     * Tipo (código del enum) de una institución ya registrada. Lo necesita el
     * wizard de inscripciones para aplicar la regla de grado, que depende del
     * tipo: solo la escuela exige un grado anterior dentro del rango 1-6.
     */
    public function tipoDeInstitucion(int $idInstitucion): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT CASE t.id_tipo_institucion
                      WHEN 1 THEN 'preescolar'
                      WHEN 6 THEN 'titulo_academico'
                      WHEN 8 THEN 'escuela'
                    END AS tipo
             FROM institucion i
             JOIN tipo_institucion t
               ON t.id_tipo_institucion = i.id_tipo_institucion
             WHERE i.id_institucion = :id
               AND i.estado = 'ACTIVO'
             LIMIT 1"
        );
        $stmt->execute(['id' => $idInstitucion]);
        $tipo = $stmt->fetchColumn();

        return $tipo === false ? null : (string) $tipo;
    }

    /**
 * Alta de institución. Escribe el tipo a través de la FK `id_tipo_institucion`.
 *
 * @param array $data Se espera 'nombre' y 'tipo' (código del enum).
 */
    public function create(array $data): bool
    {
        return $this->createAndGetId($data) > 0;
    }

    /**
     * Inserta la institución y devuelve su id autogenerado.
     * Lo usa la alta rápida del wizard, que necesita el id para la fila.
     */
    public function createAndGetId(array $data): int
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));

        $this->pdo->prepare(
            'INSERT INTO institucion
                (nombre, id_tipo_institucion, es_extranjera, id_estado_geo,
                 id_municipio, id_parroquia, codigo_dea)
             VALUES
                (:nombre, :id_tipo_institucion, :es_extranjera, :id_estado_geo,
                 :id_municipio, :id_parroquia, :codigo_dea)'
        )->execute($this->datosDeAlta($data, $tipo));

        return (int) $this->pdo->lastInsertId();
    }

    /**
 * Edición de institución. Actualiza el tipo a través de la FK.
 *
 * @param array $data Se espera 'nombre' y 'tipo' (código del enum).
 */
    public function update(int $id, array $data): bool
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));

        $fila = $this->datosDeAlta($data, $tipo);
        $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($fila)));

        $fila['id'] = $id;

        return $this->pdo->prepare(
            "UPDATE institucion SET {$sets} WHERE id_institucion = :id"
        )->execute($fila);
    }

    /**
 * Datos de alta y de edición, ya resueltos según sea nacional o extranjera. Las dos
 * rutas (alta y edición) arman el mismo conjunto de columnas, y por eso viven en un
 * solo método: si divergieran, corregir un alta y no una edición dejaría la base con
 * datos distintos según por dónde se tocó.
 *
 * @param string $tipo Código del enum de App\Support\Instituciones.
 * @return array<string, int|string|null>
 */
    private function datosDeAlta(array $data, string $tipo): array
    {
        $esExtranjera = $this->esExtranjera($data);

        $nombre = trim((string) ($data['nombre'] ?? ''));

        $base = [
            'nombre'              => $nombre,
            'id_tipo_institucion' => Instituciones::idCatalogo($tipo),
            'es_extranjera'       => $esExtranjera,
            'id_estado_geo'       => null,
            'id_municipio'        => null,
            'id_parroquia'        => null,
            'codigo_dea'          => null,
        ];

        if ($esExtranjera) {
            // Institución extranjera: solo registra nombre, tipo y es_extranjera = 1
            return $base;
        }

        $base['id_estado_geo'] = $this->enteroONulo($data['id_estado_geo'] ?? null);
        $base['id_municipio']  = $this->enteroONulo($data['id_municipio'] ?? null);
        $base['id_parroquia']  = $this->enteroONulo($data['id_parroquia'] ?? null);
        $base['codigo_dea']    = $this->codigoDea($data);

        return $base;
    }

    /** El interruptor "¿Institución extranjera?". Todo lo que no sea '1' es no. */
    private function esExtranjera(array $data): int
    {
        return ((string) ($data['es_extranjera'] ?? '0')) === '1' ? 1 : 0;
    }

    private function enteroONulo(mixed $valor): ?int
    {
        $id = (int) ($valor ?? 0);

        return $id > 0 ? $id : null;
    }

    private function textoONulo(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    /**
 * Código DEA: obligatorio en preescolar y escuela, y en universidad se guarda NULL
 * aunque venga informado. Se guarda como NULL cuando no aplica, nunca como cadena
 * vacía: `codigo_dea` tiene índice UNIQUE, y dos universidades sin código con ''
 * chocarían una contra otra.
 */
    private function codigoDea(array $data): ?string
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));

        if (!Instituciones::esDeEstudiante($tipo)) {
            return null;
        }

        return $this->textoONulo(
            mb_strtoupper(trim((string) ($data['codigo_dea'] ?? '')), 'UTF-8')
        );
    }

    /**
     * Ya existe una institución con ese código DEA. $excludeId omite la
     * institución que se está editando, para que no choque consigo misma.
     */
    public function existeCodigoDea(string $codigoDea, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM institucion
                 WHERE codigo_dea = :dea
                   AND codigo_dea IS NOT NULL";
        $params = ['dea' => mb_strtoupper(trim($codigoDea), 'UTF-8')];

        if ($excludeId !== null) {
            $sql .= ' AND id_institucion <> :id';
            $params['id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /** ¿Ese id de `estado` existe y está activo? */
    public function existeEstadoGeo(int $idEstado): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM estado WHERE id_estado = :id AND estado = 'ACTIVO'"
        );
        $stmt->execute(['id' => $idEstado]);

        return $stmt->fetchColumn() > 0;
    }

    /** ¿Ese municipio es hijo de ese estado? */
    public function municipioPerteneceAlEstado(int $idMunicipio, int $idEstado): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM municipio
              WHERE id_municipio = :id AND id_estado = :padre AND estado = 'ACTIVO'"
        );
        $stmt->execute(['id' => $idMunicipio, 'padre' => $idEstado]);

        return $stmt->fetchColumn() > 0;
    }

    /** ¿Esa parroquia es hija de ese municipio? */
    public function parroquiaPerteneceAlMunicipio(int $idParroquia, int $idMunicipio): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM parroquia
              WHERE id_parroquia = :id AND id_municipio = :padre AND estado = 'ACTIVO'"
        );
        $stmt->execute(['id' => $idParroquia, 'padre' => $idMunicipio]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Estados para el <select> de ubicación, en orden alfabético.
     *
     * @return array<int, array{id_estado: int, nombre: string}>
     */
    /**
     * Valida la procedencia geográfica y el código DEA de una institución.
     *
     * @return array<string, string> Errores por campo.
     */
    public function validarUbicacion(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        $tipo  = trim((string) ($data['tipo'] ?? ''));
        $esExt = ((string) ($data['es_extranjera'] ?? '0')) === '1';

        if ($esExt) {
            return $errors;
        }

        if (!\App\Support\Instituciones::esDeEstudiante($tipo)) {
            $estado = (int) ($data['id_estado_geo'] ?? 0);
            if ($estado > 0) {
                if (!$this->existeEstadoGeo($estado)) {
                    $errors['id_estado_geo'] = 'El estado seleccionado no existe.';
                }
                $municipio = (int) ($data['id_municipio'] ?? 0);
                if ($municipio > 0 && !$this->municipioPerteneceAlEstado($municipio, $estado)) {
                    $errors['id_municipio'] = 'El municipio seleccionado no pertenece a ese estado.';
                }
                $parroquia = (int) ($data['id_parroquia'] ?? 0);
                if ($parroquia > 0 && !$this->parroquiaPerteneceAlMunicipio($parroquia, $municipio)) {
                    $errors['id_parroquia'] = 'La parroquia seleccionada no pertenece a ese municipio.';
                }
            }
            return $errors;
        }

        $estado = (int) ($data['id_estado_geo'] ?? 0);
        if ($estado <= 0) {
            $errors['id_estado_geo'] = 'Selecciona el estado donde está la institución.';
        } elseif (!$this->existeEstadoGeo($estado)) {
            $errors['id_estado_geo'] = 'El estado seleccionado no existe.';
        }

        $municipio = (int) ($data['id_municipio'] ?? 0);
        if ($municipio <= 0) {
            $errors['id_municipio'] = 'Selecciona el municipio.';
        } elseif (!$this->municipioPerteneceAlEstado($municipio, $estado)) {
            $errors['id_municipio'] = 'El municipio seleccionado no pertenece a ese estado.';
        }

        $parroquia = (int) ($data['id_parroquia'] ?? 0);
        if ($parroquia <= 0) {
            $errors['id_parroquia'] = 'Selecciona la parroquia.';
        } elseif (!$this->parroquiaPerteneceAlMunicipio($parroquia, $municipio)) {
            $errors['id_parroquia'] = 'La parroquia seleccionada no pertenece a ese municipio.';
        }

        $dea = mb_strtoupper(trim((string) ($data['codigo_dea'] ?? '')), 'UTF-8');
        if ($dea === '') {
            $errors['codigo_dea'] = 'Escribe el código DEA de la institución.';
        } elseif (!preg_match('/^[A-Z0-9]{10}$/', $dea)) {
            $errors['codigo_dea'] = 'El código DEA debe tener exactamente 10 caracteres alfanuméricos en mayúscula (ej. OD05281402).';
        } elseif ($this->existeCodigoDea($dea, $excludeId)) {
            $errors['codigo_dea'] = 'Ya existe una institución con ese código DEA.';
        }

        return $errors;
    }

    public function getEstadosGeo(): array
    {
        return $this->pdo->query(
            "SELECT id_estado, nombre
               FROM estado
              WHERE estado = 'ACTIVO'
           ORDER BY nombre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
