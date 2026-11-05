<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLogModel;
use App\Support\Documento;
use PDO;
use Throwable;

/**
 * Recaudos que debe entregar el representante (partida de nacimiento, fotos, cédula, etc.).
 *
 * La lista es el catálogo tipo_documento y se administra en Configuración; lo que el
 * representante entregó en cada caso se guarda en estudiante_documento.
 *
 * El estado de cada recaudo se guarda en estudiante_documento: si fue consignado y
 * la observación asociada.
*/
trait RecaudosModelTrait
{
    /**
     * Persiste la evaluación completa de una inscripción nueva como snapshot.
     *
     * @param array<int, array<string, mixed>> $evaluacion Requisitos generados por DocumentosObligatorios.
     * @param array<string, array{consignado?: mixed, observacion?: mixed}> $estados
     */
    protected function guardarSnapshotRecaudos(
        int $estudianteId,
        array $evaluacion,
        array $estados,
        int $idUsuario
    ): void {
        $relaciones = $this->relacionesFamiliaresPorDocumento($estudianteId);
        $insertar = $this->pdo->prepare(
            'INSERT INTO estudiante_documento
                (id_estudiante, id_persona_estudiante, id_persona_estudiante_unico, id_tipo_documento,
                 consignado, requerido, observacion, actualizado_en, actualizado_por)
             VALUES
                (:estudiante, :persona_estudiante, :persona_estudiante_unico, :tipo, :consignado, :requerido,
                 :observacion, :actualizado_en, :usuario)'
        );

        foreach ($evaluacion as $recaudo) {
            $clave = (string) $recaudo['clave'];
            $estado = $estados[$clave] ?? [];
            $documentoPersona = $recaudo['documento_persona'] ?? null;
            $idRelacion = null;
            if ($documentoPersona !== null) {
                $idRelacion = $relaciones[(string) $documentoPersona] ?? null;
                if ($idRelacion === null) {
                    throw new \RuntimeException(
                        'No se encontró el vínculo con el estudiante para ' .
                        (string) ($recaudo['persona_nombre'] ?? 'la persona autorizada') . '.'
                    );
                }
            }

            $observacion = trim((string) ($estado['observacion'] ?? ''));
            $consignado = !empty($estado['consignado']) ? 1 : 0;
            $insertar->execute([
                'estudiante' => $estudianteId,
                'persona_estudiante' => $idRelacion,
                'persona_estudiante_unico' => $idRelacion ?? 0,
                'tipo' => (int) $recaudo['id_tipo_documento'],
                'consignado' => $consignado,
                'requerido' => !empty($recaudo['requerido']) ? 1 : 0,
                'observacion' => $observacion !== '' ? $observacion : null,
                'actualizado_en' => $consignado ? date('Y-m-d H:i:s') : null,
                'usuario' => $consignado && $idUsuario > 0 ? $idUsuario : null,
            ]);
        }

        $marcarEvaluacion = $this->pdo->prepare(
            'UPDATE inscripcion
                SET documentos_evaluados_en = NOW()
              WHERE id_estudiante = :estudiante'
        );
        $marcarEvaluacion->execute(['estudiante' => $estudianteId]);
    }

    /**
     * @return array<string, int>
     */
    private function relacionesFamiliaresPorDocumento(int $estudianteId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT pe.id_persona_estudiante, p.numero_documento
               FROM persona_estudiante pe
               JOIN persona p ON p.id_persona = pe.id_persona
              WHERE pe.id_estudiante = :estudiante'
        );
        $consulta->execute(['estudiante' => $estudianteId]);

        $relaciones = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $documento = Documento::normalizar((string) $fila['numero_documento']);
            if ($documento !== '') {
                $relaciones[$documento] = (int) $fila['id_persona_estudiante'];
            }
        }

        return $relaciones;
    }

    /**
 * Recaudos del catálogo activo, usado por pantallas antiguas que editan un tipo a la vez.
 *
 * Se leen de la base y no del archivo para que agregar uno en Configuración lo muestre
 * de inmediato, y para que marcarlo INACTIVO lo retire de la lista sin borrar nada.
 *
 * 'detalle' va vacío porque el catálogo solo tiene nombre y estado: el texto gris pequeño
 * ("Original y copia") era texto fijo del archivo. Si algún día cada recaudo debe llevar
 * su propio requisito, es una columna nueva en tipo_documento y se rellena aquí.
 *
 * @return array<int, array<string, mixed>>
*/
    public function getRecaudos(): array
    {
        $filas = $this->pdo->query(
            "SELECT id_tipo_documento AS id, nombre, codigo, '' AS detalle
               FROM tipo_documento
              WHERE estado = 'ACTIVO'
           ORDER BY nombre"
        )->fetchAll(PDO::FETCH_ASSOC);

        /* Ningún recaudo es obligatorio de forma fija: los únicos obligatorios dependen del caso
           (prosecución, permiso LOPNNA, autorización y cédula del autorizado) y los evalúa
           DocumentosObligatorios. */
        $siempre = [];
        foreach ($filas as &$fila) {
            $fila['requerido'] = in_array((string) ($fila['codigo'] ?? ''), $siempre, true);
        }
        unset($fila);

        return $filas;
    }

    /**
 * Guarda lo que se marcó en el paso 5, dentro de la transacción de la inscripción.
 *
 * Se guardan TODOS los recaudos del catálogo, no solo los marcados: la fila con
 * consignado = 0 dice "este documento se pidió y no vine", que es información distinta de
 * "este documento no existe". Así la ficha puede mostrar lo que falta.
 *
 * El id del recaudo viene del formulario y no se confía en él: se contrasta con el
 * catálogo antes de escribir. Sin esa comprobación, un id inventado o uno desactivado
 * provocaría un error de FK y tumbaría la inscripción entera por un campo secundario.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
*/
    protected function guardarRecaudos(int $estudianteId, array $recaudos): void
    {
        if ($recaudos === []) {
            return;
        }

        $consulta = $this->pdo->query("SELECT id_tipo_documento FROM tipo_documento WHERE estado = 'ACTIVO'");
        $permitidos = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
        if ($permitidos === []) {
            return;
        }

        $insertar = $this->pdo->prepare(
            'INSERT INTO estudiante_documento (id_estudiante, id_tipo_documento, consignado, observacion)
             VALUES (:estudiante, :tipo, :consignado, :observacion)'
        );

        foreach ($recaudos as $id => $estado) {
            $id = (int) $id;
            if (!in_array($id, $permitidos, true)) {
                continue;
            }

            $observacion = trim((string) ($estado['observacion'] ?? ''));
            $insertar->execute([
                'estudiante'   => $estudianteId,
                'tipo'         => $id,
                'consignado'   => !empty($estado['consignado']) ? 1 : 0,
                'observacion'  => $observacion !== '' ? $observacion : null,
            ]);
        }
    }

    /**
 * Recaudos de un estudiante.
 *
 * Las inscripciones evaluadas leen el snapshot sin recalcular reglas. Las antiguas
 * muestran el catálogo como "sin evaluar" y solo guardan cambios que la secretaría
 * haga explícitamente; así no se crean pendientes retroactivos.
 *
 * @return array<int, array<string, mixed>>
*/
    public function getRecaudosDeEstudiante(int $estudianteId): array
    {
        $evaluado = $this->recaudosEvaluados($estudianteId);
        if (!$evaluado) {
            $consulta = $this->pdo->prepare(
                "SELECT CASE
                            WHEN ed.id_estudiante_documento IS NULL
                                THEN CONCAT('tipo_', t.id_tipo_documento)
                            ELSE CAST(ed.id_estudiante_documento AS CHAR)
                        END AS id,
                        t.id_tipo_documento,
                        t.nombre,
                        t.codigo,
                        COALESCE(ed.consignado, 0) AS consignado,
                        ed.requerido,
                        ed.observacion,
                        ed.actualizado_en,
                        u.nombre_usuario AS actualizado_por_nombre,
                        NULL AS persona_nombre,
                        0 AS evaluado
                   FROM inscripcion i
                   JOIN tipo_documento t ON t.estado = 'ACTIVO'
                   LEFT JOIN estudiante_documento ed
                          ON ed.id_estudiante = i.id_estudiante
                         AND ed.id_tipo_documento = t.id_tipo_documento
                         AND ed.id_persona_estudiante IS NULL
                   LEFT JOIN usuario u ON u.id_usuario = ed.actualizado_por
                  WHERE i.id_estudiante = :estudiante
                    AND i.documentos_evaluados_en IS NULL
               ORDER BY t.nombre"
            );
            $consulta->execute(['estudiante' => $estudianteId]);
            $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);
            $permitidos = ['constancia_prosecucion', 'permiso_lopnna', 'autorizacion_retiro', 'cedula_autorizada'];
            foreach ($filas as &$f) {
                if (!in_array((string) ($f['codigo'] ?? ''), $permitidos, true)) {
                    $f['requerido'] = 0;
                }
            }
            unset($f);
            return $filas;
        }

        $consulta = $this->pdo->prepare(
            "SELECT ed.id_estudiante_documento AS id,
                    ed.id_tipo_documento,
                    t.nombre,
                    t.codigo,
                    ed.consignado,
                    ed.requerido,
                    ed.observacion,
                    ed.actualizado_en,
                    u.nombre_usuario AS actualizado_por_nombre,
                    CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre,
                        p.primer_apellido, p.segundo_apellido) AS persona_nombre,
                    i.documentos_evaluados_en IS NOT NULL AS evaluado
               FROM estudiante_documento ed
               JOIN tipo_documento t ON t.id_tipo_documento = ed.id_tipo_documento
               LEFT JOIN usuario u ON u.id_usuario = ed.actualizado_por
               LEFT JOIN persona_estudiante pe
                      ON pe.id_persona_estudiante = ed.id_persona_estudiante
               LEFT JOIN persona p ON p.id_persona = pe.id_persona
               LEFT JOIN inscripcion i ON i.id_estudiante = ed.id_estudiante
              WHERE ed.id_estudiante = :estudiante
           ORDER BY t.nombre, persona_nombre"
        );
        $consulta->execute(['estudiante' => $estudianteId]);
        $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $permitidos = ['constancia_prosecucion', 'permiso_lopnna', 'autorizacion_retiro', 'cedula_autorizada'];
        foreach ($filas as &$f) {
            if (!in_array((string) ($f['codigo'] ?? ''), $permitidos, true)) {
                $f['requerido'] = 0;
            }
        }
        unset($f);
        return $filas;
    }

    public function recaudosEvaluados(int $estudianteId): bool
    {
        $consulta = $this->pdo->prepare(
            'SELECT documentos_evaluados_en
               FROM inscripcion
              WHERE id_estudiante = :estudiante
              LIMIT 1'
        );
        $consulta->execute(['estudiante' => $estudianteId]);
        $evaluadoEn = $consulta->fetchColumn();

        return $evaluadoEn !== false && $evaluadoEn !== null;
    }

    /**
 * Actualiza lo que se entregó de un estudiante y lo registra en bitácora.
 *
 * Es un upsert (ON DUPLICATE KEY UPDATE) porque la llave única es (id_estudiante,
 * id_tipo_documento): el catálogo se muestra completo en la pantalla, así que un recaudo
 * pendiente puede no tener fila todavía y hay que crearla en el momento de marcarlo.
 *
 * Solo se tocan los recaudos que vienen marcados en el formulario; el resto se deja como
 * está. Con una actualización ciega del catálogo completo, guardar la pantalla borraría
 * de paso lo que alguien hubiera anotado desde otra sesión.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
 * @return int Cuántos recaudos cambiaron de estado o se crearon.
*/
    /**
 * Actualiza lo que se entregó y lo registra en bitácora, en su propia transacción.
 *
 * Es la vía de la pantalla de actualización de recaudos. Cuando los recaudos van dentro
 * de otro guardado (el modal "Actualizar expediente"), quien lo llama debe usar
 * guardarRecaudosEnTransaccion() para que todo se guarde junto o nada.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
 * @return int Cuántos recaudos cambiaron de estado o se crearon.
*/
    public function actualizarRecaudos(int $estudianteId, array $recaudos, int $idUsuario): int
    {
        if ($recaudos === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $tocados = $this->guardarRecaudosEnTransaccion($estudianteId, $recaudos, $idUsuario);
            $this->pdo->commit();

            return $tocados;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
 * Igual que actualizarRecaudos(), pero sin abrir transacción: la usa quien ya está
 * dentro de la suya y quiere que los recaudos se guarden o se deshagan con el resto.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
 * @return int Cuántos recaudos cambiaron de estado o se crearon.
*/
    protected function guardarRecaudosEnTransaccion(int $estudianteId, array $recaudos, int $idUsuario): int
    {
        if ($recaudos === []) {
            return 0;
        }

        $actualizar = $this->pdo->prepare(
            'UPDATE estudiante_documento
                SET consignado = :consignado,
                    observacion = :observacion,
                    actualizado_en = NOW(),
                    actualizado_por = :usuario
              WHERE id_estudiante_documento = :id
                AND id_estudiante = :estudiante'
        );

        $tocados = 0;
        $evaluado = $this->recaudosEvaluados($estudianteId);
        $buscar = $this->pdo->prepare(
            'SELECT id_tipo_documento, consignado, observacion
               FROM estudiante_documento
              WHERE id_estudiante_documento = :id
                AND id_estudiante = :estudiante'
        );
        $buscarLegado = $this->pdo->prepare(
            'SELECT id_estudiante_documento, consignado, observacion
               FROM estudiante_documento
              WHERE id_estudiante = :estudiante
                AND id_tipo_documento = :tipo
                AND id_persona_estudiante IS NULL'
        );
        $tipoActivo = $this->pdo->prepare(
            "SELECT 1 FROM tipo_documento
              WHERE id_tipo_documento = :tipo AND estado = 'ACTIVO'"
        );
        $insertarLegado = $this->pdo->prepare(
            'INSERT INTO estudiante_documento
                (id_estudiante, id_tipo_documento, consignado, requerido,
                 observacion, actualizado_en, actualizado_por)
             VALUES
                (:estudiante, :tipo, :consignado, NULL, :observacion, NOW(), :usuario)
             ON DUPLICATE KEY UPDATE
                consignado = VALUES(consignado),
                observacion = VALUES(observacion),
                actualizado_en = NOW(),
                actualizado_por = VALUES(actualizado_por)'
        );
        foreach ($recaudos as $clave => $estado) {
            $clave = (string) $clave;
            $observacion = trim((string) ($estado['observacion'] ?? ''));
            $consignado = !empty($estado['consignado']) ? 1 : 0;

            if (preg_match('/^tipo_([1-9]\d*)(?:_.*)?$/', $clave, $coincidencia)) {
                $idTipo = (int) $coincidencia[1];
                $tipoActivo->execute(['tipo' => $idTipo]);
                if ($tipoActivo->fetchColumn() === false) {
                    continue;
                }

                $buscarLegado->execute(['estudiante' => $estudianteId, 'tipo' => $idTipo]);
                $fila = $buscarLegado->fetch(PDO::FETCH_ASSOC);
                if ($fila
                    && (int) $fila['consignado'] === $consignado
                    && (string) ($fila['observacion'] ?? '') === $observacion) {
                    continue;
                }

                $insertarLegado->execute([
                    'estudiante' => $estudianteId,
                    'tipo' => $idTipo,
                    'consignado' => $consignado,
                    'observacion' => $observacion !== '' ? $observacion : null,
                    'usuario' => $idUsuario > 0 ? $idUsuario : null,
                ]);
                $tocados++;
                $cambios[] = ($consignado ? 'entregado' : 'pendiente')
                    . ': ' . $this->nombreRecaudo($idTipo);
                continue;
            }

            $idInstancia = (int) $clave;
            if ($idInstancia <= 0) {
                continue;
            }

            $buscar->execute(['id' => $idInstancia, 'estudiante' => $estudianteId]);
            $fila = $buscar->fetch(PDO::FETCH_ASSOC);
            if (!$fila) {
                continue;
            }

            if ($fila
                && (int) $fila['consignado'] === $consignado
                && (string) ($fila['observacion'] ?? '') === $observacion) {
                continue;
            }

            $actualizar->execute([
                'consignado'  => $consignado,
                'observacion' => $observacion !== '' ? $observacion : null,
                'usuario'     => $idUsuario > 0 ? $idUsuario : null,
                'id'          => $idInstancia,
                'estudiante'  => $estudianteId,
            ]);
            $tocados++;
            $cambios[] = ($consignado ? 'entregado' : 'pendiente')
                . ': ' . $this->nombreRecaudo((int) $fila['id_tipo_documento']);
        }

        if ($tocados > 0 && $idUsuario > 0) {
            (new AuditLogModel($this->pdo))->registrar(
                $idUsuario,
                'ACTUALIZACIÓN RECAUDOS ' . $this->descripcionEstudianteBruto($estudianteId)
                    . ': ' . implode('; ', $cambios),
                'Recaudos'
            );
        }

        return $tocados;
    }

    /** Nombre de un recaudo del catálogo, para los mensajes de la bitácora.*/
    private function nombreRecaudo(int $idTipoDocumento): string
    {
        $consulta = $this->pdo->prepare('SELECT nombre FROM tipo_documento WHERE id_tipo_documento = :id');
        $consulta->execute(['id' => $idTipoDocumento]);
        $nombre = $consulta->fetchColumn();

        return $nombre === false ? "tipo {$idTipoDocumento}" : (string) $nombre;
    }

    /**
 * Nombre y cédula de un estudiante para los textos de la bitácora.
 *
 * El método se llama descripciónEstudianteBruto() y no descripciónEstudiante() a
 * propósito: RatificationModel ya tenía un private con ese nombre para lo mismo, y
 * un trait que declara un método que la clase ya define es un error fatal de PHP
 * ("Cannot redeclare"), no una sobrescritura. Con dos nombres distintos conviven los
 * dos y el que se usa es explícito.
*/
    private function descripcionEstudianteBruto(int $estudianteId): string
    {
        $consulta = $this->pdo->prepare(
            'SELECT p.primer_nombre, p.primer_apellido, p.numero_documento
               FROM estudiante e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $consulta->execute(['id' => $estudianteId]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return "estudiante {$estudianteId}";
        }

        return trim($fila['primer_nombre'] . ' ' . $fila['primer_apellido'])
            . ' (' . $fila['numero_documento'] . ')';
    }
}
