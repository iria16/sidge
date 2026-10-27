<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Instituciones;
use PDO;

/**
 * InstitucionModel
 *
 * Catálogo `institucion`: es el único catálogo del sistema con un campo extra
 * obligatorio (el tipo), así que sobrescribe create() de CatalogoModel para
 * incluirlo. El resto (Listable, Toggleable, existeNombre, getPrimaryKey,
 * búsqueda por nombre) lo hereda igual que los demás catálogos.
 *
 * El tipo es el código del enum de App\Support\Instituciones
 * (titulo_academico, preescolar, escuela), guardado en `institucion.tipo`.
 *
 * DESPUÉS DE 20261021_institucion_tipo_fk_1_datos.sql existe además
 * `institucion.id_tipo_institucion`, una FK a `tipo_institucion` con los ids
 * 1 (Preescolar), 6 (Universidad) y 8 (Escuela). El enum NO cambia: sigue
 * siendo el que decide, y `Instituciones::idCatalogo()` traduce su código al
 * id. La tabla deja de ser un histórico y pasa a ser la que manda.
 */
class InstitucionModel extends CatalogoModel
{
    protected string $table      = 'institucion';
    protected string $primaryKey = 'id_institucion';

    /**
     * Opciones de tipo para el <select> de alta rápida, en el formato que
     * esperan los modales: el código del enum (lo que viaja en el POST) y su
     * texto.
     *
     * No consulta la base: la lista sale del enum, que es quien sabe qué tipos
     * existen y qué regla tiene cada uno.
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
     * Alta de institución.
     *
     * Escribe LAS DOS columnas de tipo a propósito: `tipo` (texto) e
     * `id_tipo_institucion` (FK). Están las dos porque la migración se
     * despliega en pasos y hay una ventana en la que el código nuevo ya corre
     * y `tipo` todavía existe. Cuando el tercer script borre `tipo`, aquí se
     * quita su línea y el resto no cambia.
     *
     * El id no se toma del formulario: sale del enum, que es quien sabe qué
     * categoría es cada tipo.
     *
     * @param array $data Se espera 'nombre' y 'tipo' (código del enum).
     */
    public function create(array $data): bool
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));

        return $this->pdo->prepare(
            'INSERT INTO institucion
                (nombre, id_tipo_institucion, es_extranjera, id_estado_geo,
                 id_municipio, id_parroquia, pais_id, ciudad_extranjera,
                 direccion_extranjera, referencia_plantel, codigo_dea)
             VALUES
                (:nombre, :id_tipo_institucion, :es_extranjera, :id_estado_geo,
                 :id_municipio, :id_parroquia, :pais_id, :ciudad_extranjera,
                 :direccion_extranjera, :referencia_plantel, :codigo_dea)'
        )->execute($this->datosDeAlta($data, $tipo));
    }

    /**
     * Edición de institución. Igual que create(), escribe las dos columnas de
     * tipo para que no queden desincronizadas.
     *
     * El padre (CatalogoModel) solo toca `nombre`, así que sin este sobrescribe
     * el tipo quedaría congelado con el valor del alta. Desde la pantalla de
     * Catálogos se puede corregir una institución creada por error.
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
     * Datos de alta y de edición, ya resueltos según sea nacional o extranjera.
     *
     * Las dos rutas (alta y edición) arman el mismo conjunto de columnas, y por
     * eso viven en un solo método: si divergieran, corregir un alta y no una
     * edición dejaría la base con datos distintos según por dónde se tocó.
     *
     * La regla de fondo es una sola: **si es extranjera, se ignoran los datos
     * nacionales; si es nacional, se ignoran los extranjeros.** Eso evita que un
     * cambio de interruptor deje datos viejos pegados: si alguien marca
     * "extranjera" y guarda, el estado, el municipio y la parroquia se van a
     * NULL en vez de quedarse ahí sin que se usen.
     *
     * El código DEA tiene su propio matiz: se ignora cuando es extranjera, y
     * cuando es nacional solo aplica a preescolar y escuela.
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
            'pais_id'             => null,
            'ciudad_extranjera'   => null,
            'direccion_extranjera' => null,
            'referencia_plantel'  => null,
            'codigo_dea'          => null,
        ];

        if ($esExtranjera) {
            $base['pais_id'] = $this->enteroONulo($data['pais_id'] ?? null);
            // Ciudad, dirección y referencia se omiten: instituciones extranjeras solo registran país
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
     * Código DEA: obligatorio en preescolar y escuela, y en universidad se
     * guarda NULL aunque venga informado.
     *
     * Se guarda como NULL cuando no aplica, nunca como cadena vacía:
     * `codigo_dea` tiene índice UNIQUE, y dos universidades sin código con ''
     * chocarían una contra otra. Los NULL no chocan entre sí porque en un
     * índice UNIQUE los nulos no se consideran duplicados.
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
     * ¿Ese id de `pais` existe y está activo?
     */
    public function existePais(int $idPais): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM pais WHERE id_pais = :id AND estado = 'ACTIVO'"
        );
        $stmt->execute(['id' => $idPais]);

        return $stmt->fetchColumn() > 0;
    }

    /** Países para el <select>, en orden alfabético. */
    public function getPaises(): array
    {
        return $this->pdo->query(
            "SELECT id_pais, nombre FROM pais WHERE estado = 'ACTIVO' ORDER BY nombre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estados para el <select> de ubicación, en orden alfabético.
     *
     * @return array<int, array{id_estado: int, nombre: string}>
     */
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
