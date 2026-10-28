<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Support\Matriculas;
use PDO;
use RuntimeException;
use Throwable;

/**
 * ResultadoAcademicoModel
 * -----------------------------------------------------------------------------
 * Gestiona el registro, propuesta y confirmación formal de los resultados
 * académicos anuales (Promoción y No Promoción) de los estudiantes.
 *
 * Flujo en dos fases:
 *  1. Fase Docente: Carga del literal cualitativo (A, B, C, D, E) y propuesta inicial
 *     (estado_resultado = PENDIENTE, registrando propuesto_por y fecha_propuesta).
 *  2. Fase Directiva: Validación y confirmación oficial del resultado (PROMOVIDO /
 *     NO_PROMOVIDO, registrando confirmado_por y fecha_confirmacion).
 *
 * Reglas de negocio:
 *  - Ningún resultado pendiente se interpreta como promoción o no promoción.
 *  - La ratificación y egreso se basan en resultados académicos confirmados.
 *  - El retiro de un estudiante es independiente y mantiene el resultado pendiente.
 */
class ResultadoAcademicoModel extends BaseModel
{
    protected string $table = 'resultado_academico';
    protected string $primaryKey = 'id_resultado';

    public const ESTADO_PENDIENTE    = 'PENDIENTE';
    public const ESTADO_PROMOVIDO    = 'PROMOVIDO';
    public const ESTADO_NO_PROMOVIDO = 'NO_PROMOVIDO';

    public const LITERALES_VALIDOS = ['A', 'B', 'C', 'D', 'E'];

    /**
     * Regla normativa de la escala cualitativa. La vista y el JS la reciben
     * desde aquí para no duplicarla en tres sitios.
     */
    public const LITERALES_PROMUEVEN = ['A', 'B', 'C', 'D'];
    public const LITERALES_EXIGEN_OBSERVACION = ['E'];

    /**
     * Días de antelación al cierre del año escolar en los que se abre la ventana
     * para la carga de literales finales por parte del docente.
     */
    public const DIAS_AVISO_CIERRE_LITERALES = 60;

    /**
     * Mapeo normativo de literales de Primaria a estado sugerido:
     * A, B, C, D -> Promovido al grado inmediato superior.
     * E          -> No promovido (repite el mismo grado).
     */
    public static function estadoSugeridoPorLiteral(?string $literal): string
    {
        if ($literal === null || $literal === '') {
            return self::ESTADO_PENDIENTE;
        }

        $lit = strtoupper(trim($literal));
        if (in_array($lit, self::LITERALES_PROMUEVEN, true)) {
            return self::ESTADO_PROMOVIDO;
        }

        if ($lit === 'E') {
            return self::ESTADO_NO_PROMOVIDO;
        }

        return self::ESTADO_PENDIENTE;
    }

    /**
     * Devuelve las estructuras/secciones accesibles para el usuario y año escolar.
     * Si el rol es docente, restringe a las secciones asignadas en asignacion_docente.
     * Si no tiene secciones asignadas pero es directivo/admin/coordinador, muestra todas.
     */
    public function getSeccionesPorAnio(?int $idAnioEscolar = null, int $idUsuario = 0, string $rol = 'admin'): array
    {
        // El docente únicamente puede visualizar el período escolar activo
        if (strtolower($rol) === 'docente') {
            $idAnioEscolar = AniosEscolares::idEnCurso($this->pdo) ?: 0;
            if ($idUsuario > 0) {
                $idDocente = $this->getIdDocentePorUsuario($idUsuario);
            } else {
                $idDocente = 0;
            }
            if ($idDocente <= 0) {
                return []; // El docente no tiene perfil asignado; no puede ver secciones ajenas
            }
        } else {
            if ($idAnioEscolar === null || $idAnioEscolar <= 0) {
                $idAnioEscolar = AniosEscolares::idEnCurso($this->pdo) ?: 0;
            }
        }

        if ($idAnioEscolar <= 0) {
            return [];
        }

        $sql = "SELECT ea.id_estructura_academica AS id,
                       ea.id_anio_escolar,
                       ae.nombre_periodo AS anio_escolar,
                       g.id_grado,
                       g.nombre AS grado,
                       s.id_seccion,
                       s.nombre AS seccion,
                       t.id_turno,
                       t.nombre AS turno,
                       ea.capacidad_maxima,
                       -- Total de alumnos activos cursando en esta sección
                       COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                       -- Conteo de estados de resultado académico
                       COUNT(DISTINCT CASE 
                           WHEN ra.estado_resultado = 'PROMOVIDO' 
                             OR (ra.literal IN ('A','B','C','D') AND (ra.estado_resultado IS NULL OR ra.estado_resultado = 'PENDIENTE')) 
                           THEN ra.id_estudiante 
                       END) AS total_promovidos,
                       COUNT(DISTINCT CASE 
                           WHEN ra.estado_resultado = 'NO_PROMOVIDO' 
                             OR (ra.literal = 'E' AND (ra.estado_resultado IS NULL OR ra.estado_resultado = 'PENDIENTE')) 
                           THEN ra.id_estudiante 
                       END) AS total_no_promovidos,
                       -- Pendientes de carga (sin literal asignado todavía)
                       COUNT(DISTINCT CASE WHEN m.id_estudiante IS NOT NULL AND (ra.literal IS NULL OR TRIM(ra.literal) = '') THEN m.id_estudiante END) AS total_pendientes,
                       /* Registrados ≠ confirmados. Registrado/cargado es a quien YA se le
                          asignó un literal válido; confirmado es a quien la Dirección validó.
                          Se devuelven ambos alias (total_cargados y total_registrados) para
                          que la vista de índice y cualquier otro consumidor reciban el dato. */
                       COUNT(DISTINCT CASE WHEN ra.literal IS NOT NULL AND TRIM(ra.literal) != '' THEN ra.id_estudiante END) AS total_cargados,
                       COUNT(DISTINCT CASE WHEN ra.literal IS NOT NULL AND TRIM(ra.literal) != '' THEN ra.id_estudiante END) AS total_registrados,
                       COUNT(DISTINCT CASE WHEN ra.confirmado_por IS NOT NULL THEN ra.id_estudiante END) AS total_confirmados,
                       -- Docente asignado
                       CONCAT_WS(' ', p_doc.primer_nombre, p_doc.primer_apellido) AS docente_nombre
                FROM estructura_academica ea
                INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno
                LEFT JOIN matricula m ON m.id_estructura_academica = ea.id_estructura_academica
                                     AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                                     AND " . Matriculas::condicionCursando('m') . "
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = m.id_estudiante
                                                AND ra.id_anio_escolar = ea.id_anio_escolar
                LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                                               AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                LEFT JOIN docente doc ON doc.id_docente = ad.id_docente
                LEFT JOIN empleado emp ON emp.id_empleado = doc.id_empleado
                LEFT JOIN persona p_doc ON p_doc.id_persona = emp.id_persona
                WHERE ea.id_anio_escolar = :id_anio";

        $params = ['id_anio' => $idAnioEscolar];

        if (strtolower($rol) === 'docente' && $idDocente > 0) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM asignacion_docente ad_filtro
                WHERE ad_filtro.id_estructura_academica = ea.id_estructura_academica
                  AND ad_filtro.id_docente = :id_docente
                  AND (ad_filtro.fecha_fin IS NULL OR ad_filtro.fecha_fin >= CURDATE())
            )";
            $params['id_docente'] = $idDocente;
        }

        $sql .= " GROUP BY ea.id_estructura_academica
                  ORDER BY g.id_grado ASC, s.nombre ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el detalle de la sección y la nómina completa de sus estudiantes
     * con su resultado académico actual (propuesto o confirmado).
     */
    public function getDetalleSeccion(int $idEstructura, int $idUsuario = 0, string $rol = 'admin'): ?array
    {
        // 1. Datos de la estructura
        $stmt = $this->pdo->prepare(
            "SELECT ea.id_estructura_academica AS id,
                    ea.id_anio_escolar,
                    ae.nombre_periodo AS anio_escolar,
                    ae.estado AS estado_anio,
                    g.id_grado,
                    g.nombre AS grado,
                    s.id_seccion,
                    s.nombre AS seccion,
                    t.id_turno,
                    t.nombre AS turno,
                    CONCAT_WS(' ', p_doc.primer_nombre, p_doc.primer_apellido) AS docente_nombre
             FROM estructura_academica ea
             INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ea.id_anio_escolar
             INNER JOIN grado g ON g.id_grado = ea.id_grado
             INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
             INNER JOIN turno t ON t.id_turno = ea.id_turno
             LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                                            AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
             LEFT JOIN docente doc ON doc.id_docente = ad.id_docente
             LEFT JOIN empleado emp ON emp.id_empleado = doc.id_empleado
             LEFT JOIN persona p_doc ON p_doc.id_persona = emp.id_persona
             WHERE ea.id_estructura_academica = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $idEstructura]);
        $estructura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$estructura) {
            return null;
        }

        /* Alcance de la consulta, por rol.
         *
         * Para todo rol que no sea admin o directivo:
         *   - el período escolar debe ser el que está en curso;
         *   - y, si el rol es docente, además la sección debe ser la suya.
         *
         * La pertenencia de sección solo significa algo para el docente, que es el
         * único rol con asignación en `asignacion_docente`. Para el coordinador la
         * sección no es suya ni de nadie: su ámbito es la consulta de todas las
         * secciones del período, y por eso lo que se le acota es el año, no la
         * sección. */
        $rolNormalizado = strtolower($rol);
        $esPrivilegiado = in_array($rolNormalizado, ['admin', 'directivo'], true);

        if (!$esPrivilegiado) {
            $idAnioActivo = AniosEscolares::idEnCurso($this->pdo) ?: 0;
            if ($idAnioActivo <= 0 || (int) $estructura['id_anio_escolar'] !== (int) $idAnioActivo) {
                return null; // Solo se consulta el período escolar en curso
            }
        }

        if ($rolNormalizado === 'docente') {
            $idDocente = ($idUsuario > 0) ? $this->getIdDocentePorUsuario($idUsuario) : 0;
            if ($idDocente <= 0 || !$this->docenteTieneSeccion($idDocente, $idEstructura)) {
                return null; // No autorizado para esta sección
            }
        }

        // 2. Estudiantes cursando en esta sección
        $sqlEstudiantes = "SELECT est.id_estudiante,
                                  m.id_matricula,
                                  p.primer_nombre,
                                  p.segundo_nombre,
                                  p.primer_apellido,
                                  p.segundo_apellido,
                                  CONCAT_WS(', ',
                                      TRIM(CONCAT_WS(' ', NULLIF(p.primer_apellido, ''), NULLIF(p.segundo_apellido, ''))),
                                      TRIM(CONCAT_WS(' ', NULLIF(p.primer_nombre, ''), NULLIF(p.segundo_nombre, '')))
                                  ) AS nombre_completo,
                                  p.tipo_documento,
                                  p.numero_documento,
                                  ra.id_resultado,
                                  ra.literal,
                                  COALESCE(ra.estado_resultado, 'PENDIENTE') AS estado_resultado,
                                  ra.id_grado_destino,
                                  g_dest.nombre AS grado_destino_nombre,
                                  ra.observaciones,
                                  ra.propuesto_por,
                                  ra.fecha_propuesta,
                                  CONCAT_WS(' ', p_prop.primer_nombre, p_prop.primer_apellido) AS propuesto_por_nombre,
                                  ra.confirmado_por,
                                  ra.fecha_confirmacion,
                                  CONCAT_WS(' ', p_conf.primer_nombre, p_conf.primer_apellido) AS confirmado_por_nombre
                           FROM matricula m
                           INNER JOIN estudiante est ON est.id_estudiante = m.id_estudiante
                           INNER JOIN persona p ON p.id_persona = est.id_persona
                           LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                                                           AND ra.id_anio_escolar = m.id_anio_escolar
                           LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
                           LEFT JOIN usuario u_prop ON u_prop.id_usuario = ra.propuesto_por
                           LEFT JOIN persona p_prop ON p_prop.id_persona = u_prop.id_persona
                           LEFT JOIN usuario u_conf ON u_conf.id_usuario = ra.confirmado_por
                           LEFT JOIN persona p_conf ON p_conf.id_persona = u_conf.id_persona
                           WHERE m.id_estructura_academica = :id_estructura
                             AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                             AND " . Matriculas::condicionCursando('m') . "
                           ORDER BY p.primer_apellido ASC, p.primer_nombre ASC";

        $stmtEst = $this->pdo->prepare($sqlEstudiantes);
        $stmtEst->execute(['id_estructura' => $idEstructura]);
        $estudiantes = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

        $estructura['estudiantes'] = $estudiantes;
        $estructura['grados_catalogo'] = $this->gradosOrdenados();

        return $estructura;
    }

    /**
     * Guarda o actualiza la PROPUESTA pedagógica del docente.
     * El estado queda siempre en PENDIENTE hasta que la dirección lo confirme.
     */
    public function guardarPropuesta(int $idEstructura, array $datosEstudiantes, int $idUsuario, string $rol = 'admin'): array
    {
        if (strtolower($rol) !== 'docente') {
            return ['ok' => false, 'mensaje' => 'Solo el docente de la sección puede proponer literales.'];
        }

        $detalle = $this->getDetalleSeccion($idEstructura, $idUsuario, $rol);
        if (!$detalle) {
            return ['ok' => false, 'mensaje' => 'No tiene permisos para modificar esta sección o la sección no existe.'];
        }

        $idAnioEscolar = (int) $detalle['id_anio_escolar'];
        $idGradoActual = (int) $detalle['id_grado'];
        $idGradoSiguiente = $this->calcularSiguienteGradoId($idGradoActual);

        // 1. Obtener nómina activa autorizada para esta sección y año escolar
        $stmtNomina = $this->pdo->prepare(
            "SELECT m.id_matricula,
                    m.id_estudiante,
                    ra.id_resultado,
                    ra.estado_resultado,
                    ra.fecha_confirmacion,
                    ra.confirmado_por
               FROM matricula m
               LEFT JOIN resultado_academico ra
                      ON ra.id_estudiante = m.id_estudiante
                     AND ra.id_anio_escolar = m.id_anio_escolar
              WHERE m.id_estructura_academica = :id_estructura
                AND " . Matriculas::condicionCursando('m')
        );
        $stmtNomina->execute(['id_estructura' => $idEstructura]);
        $filasNomina = $stmtNomina->fetchAll(PDO::FETCH_ASSOC);

        $nominaValida = [];
        foreach ($filasNomina as $fila) {
            $idEst = (int) $fila['id_estudiante'];
            $nominaValida[$idEst] = [
                'id_matricula'       => (int) $fila['id_matricula'],
                'estado_resultado'   => (string) ($fila['estado_resultado'] ?? self::ESTADO_PENDIENTE),
                'confirmado'         => !empty($fila['fecha_confirmacion']) || !empty($fila['confirmado_por']),
            ];
        }

        $abrioTransaccion = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $abrioTransaccion = true;
        }
        try {
            $stmtUpsert = $this->pdo->prepare(
                "INSERT INTO resultado_academico (
                    id_estudiante, id_anio_escolar, id_estructura_academica, id_matricula,
                    literal, estado_resultado, id_grado_destino, observaciones,
                    propuesto_por, fecha_propuesta
                 ) VALUES (
                    :id_est, :id_anio, :id_estr, :id_mat,
                    :literal, :estado, :id_grado_dest, :obs,
                    :usuario, NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    literal = IF(confirmado_por IS NULL, VALUES(literal), literal),
                    estado_resultado = IF(confirmado_por IS NULL, VALUES(estado_resultado), estado_resultado),
                    id_grado_destino = IF(confirmado_por IS NULL, VALUES(id_grado_destino), id_grado_destino),
                    observaciones = IF(confirmado_por IS NULL, VALUES(observaciones), observaciones),
                    propuesto_por = IF(confirmado_por IS NULL, VALUES(propuesto_por), propuesto_por),
                    fecha_propuesta = IF(confirmado_por IS NULL, VALUES(fecha_propuesta), fecha_propuesta)"
            );

            $guardados           = 0;
            $promovidos          = 0;
            $noPromovidos        = 0;
            $sinLiteral          = 0;
            $omitidosConfirmados = 0;
            $omitidosAjenos      = 0;
            $omitidosLiterales   = 0;
            $guardadosIds        = [];
            $errores             = [];

            foreach ($datosEstudiantes as $idEstudiante => $data) {
                $idEstudiante = (int) $idEstudiante;
                $idMat        = (int) ($data['id_matricula'] ?? 0);

                // Defensa PHP 1: Verificar que el estudiante pertenece a la nómina activa de la sección
                if (!isset($nominaValida[$idEstudiante]) || $nominaValida[$idEstudiante]['id_matricula'] !== $idMat) {
                    $omitidosAjenos++;
                    $errores[$idEstudiante] = 'El estudiante no pertenece a esta sección.';
                    continue;
                }

                // Defensa PHP 2: Bloqueo universal de registros ya confirmados por la Dirección
                if (!empty($nominaValida[$idEstudiante]['confirmado'])) {
                    $omitidosConfirmados++;
                    $errores[$idEstudiante] = 'Ya fue confirmado por la Dirección.';
                    continue;
                }

                // Defensa PHP 3: Literal válido
                $literal = !empty($data['literal']) ? strtoupper(trim((string)$data['literal'])) : null;
                $obs     = !empty($data['observaciones']) ? mb_substr(trim((string)$data['observaciones']), 0, 255) : null;

                if ($literal !== null && !in_array($literal, self::LITERALES_VALIDOS, true)) {
                    $omitidosLiterales++;
                    $errores[$idEstudiante] = 'El literal no es válido.';
                    continue;
                }

                // Determinar grado destino sugerido y estado de resultado según el literal
                $idGradoDestino = $this->determinarGradoDestino($literal, $idGradoActual, $idGradoSiguiente);
                $estado         = self::estadoSugeridoPorLiteral($literal);

                $stmtUpsert->execute([
                    'id_est'        => $idEstudiante,
                    'id_anio'       => $idAnioEscolar,
                    'id_estr'       => $idEstructura,
                    'id_mat'        => $idMat,
                    'literal'       => $literal,
                    'estado'        => $estado,
                    'id_grado_dest' => $idGradoDestino,
                    'obs'           => $obs,
                    'usuario'       => $idUsuario > 0 ? $idUsuario : null,
                ]);

                $guardados++;
                $guardadosIds[] = $idEstudiante;

                if ($literal === 'E') {
                    $noPromovidos++;
                } elseif ($literal !== null) {
                    $promovidos++;
                } else {
                    $sinLiteral++;
                }
            }

            if ($abrioTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $omitidosTotal = $omitidosConfirmados + $omitidosAjenos + $omitidosLiterales;

            // Mensaje simple para el usuario (sin detalles técnicos)
            if ($guardados > 0 && $omitidosTotal > 0) {
                $mensaje = "{$guardados} guardado(s), {$omitidosTotal} omitido(s) (ya confirmados por la Dirección u otros no válidos).";
            } elseif ($guardados > 0) {
                $mensaje = "{$guardados} estudiante(s) guardado(s) exitosamente. Queda pendiente de confirmación directiva.";
            } elseif ($omitidosTotal > 0) {
                $mensaje = "No se realizaron cambios: {$omitidosTotal} registro(s) omitido(s) (ya confirmados por la Dirección u otros no válidos).";
            } else {
                $mensaje = "No se recibieron datos para guardar.";
            }

            return [
                'ok' => true,
                'mensaje' => $mensaje,
                'resumen' => [
                    'guardados'            => $guardados,
                    'promovidos'           => $promovidos,
                    'no_promovidos'        => $noPromovidos,
                    'sin_literal'          => $sinLiteral,
                    'omitidos'             => $omitidosTotal,
                    'omitidos_confirmados' => $omitidosConfirmados,
                    'omitidos_ajenos'      => $omitidosAjenos,
                    'omitidos_literales'   => $omitidosLiterales,
                    'guardados_ids'        => $guardadosIds,
                    'errores'              => $errores,
                ],
            ];
        } catch (Throwable $e) {
            if ($abrioTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('ResultadoAcademicoModel::guardarPropuesta: ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'Error al guardar la propuesta académica.'];
        }
    }

    /**
     * Confirma y valida formalmente el resultado académico (solo Directivos / Administradores).
     * Pasa el estado a PROMOVIDO o NO_PROMOVIDO y sella confirmado_por y fecha_confirmacion.
     * Aplica doble defensa: filtro de nómina con Matriculas::condicionCursando('m') + candado SQL atómico.
     */
    public function confirmarResultados(int $idEstructura, array $datosEstudiantes, int $idUsuarioDirectivo, string $rol = 'directivo'): array
    {
        if (strtolower($rol) !== 'directivo') {
            return ['ok' => false, 'mensaje' => 'Solo el personal directivo puede confirmar los resultados académicos.'];
        }

        $idAnioEnCurso = (int) (AniosEscolares::idEnCurso($this->pdo) ?: 0);
        if ($idAnioEnCurso <= 0) {
            return ['ok' => false, 'mensaje' => 'No hay un período escolar en curso. No se pueden confirmar resultados.'];
        }

        $detalle = $this->getDetalleSeccion($idEstructura);
        if (!$detalle) {
            return ['ok' => false, 'mensaje' => 'Estructura académica no encontrada.'];
        }

        if ((int) $detalle['id_anio_escolar'] !== $idAnioEnCurso) {
            return ['ok' => false, 'mensaje' => 'Este período escolar está cerrado. Los resultados solo se pueden confirmar mientras el año está en curso.'];
        }

        $idAnioEscolar = (int) $detalle['id_anio_escolar'];
        $idGradoActual = (int) $detalle['id_grado'];
        $idGradoSiguiente = $this->calcularSiguienteGradoId($idGradoActual);

        // 1. Obtener nómina activa autorizada para esta sección y año escolar
        $stmtNomina = $this->pdo->prepare(
            "SELECT m.id_matricula,
                    m.id_estudiante,
                    ra.id_resultado,
                    ra.estado_resultado,
                    ra.fecha_confirmacion
               FROM matricula m
               LEFT JOIN resultado_academico ra
                      ON ra.id_estudiante = m.id_estudiante
                     AND ra.id_anio_escolar = m.id_anio_escolar
              WHERE m.id_estructura_academica = :id_estructura
                AND " . Matriculas::condicionCursando('m')
        );
        $stmtNomina->execute(['id_estructura' => $idEstructura]);
        $filasNomina = $stmtNomina->fetchAll(PDO::FETCH_ASSOC);

        $nominaValida = [];
        foreach ($filasNomina as $fila) {
            $idEst = (int) $fila['id_estudiante'];
            $nominaValida[$idEst] = [
                'id_matricula'       => (int) $fila['id_matricula'],
                'estado_resultado'   => (string) ($fila['estado_resultado'] ?? self::ESTADO_PENDIENTE),
                'fecha_confirmacion' => $fila['fecha_confirmacion'],
            ];
        }

        $abrioTransaccion = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $abrioTransaccion = true;
        }
        try {
            $stmtUpsert = $this->pdo->prepare(
                "INSERT INTO resultado_academico (
                    id_estudiante, id_anio_escolar, id_estructura_academica, id_matricula,
                    literal, estado_resultado, id_grado_destino, observaciones,
                    confirmado_por, fecha_confirmacion
                 ) VALUES (
                    :id_est, :id_anio, :id_estr, :id_mat,
                    :literal, :estado, :id_grado_dest, :obs,
                    :usuario, NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    literal = IF(estado_resultado = 'PENDIENTE', VALUES(literal), literal),
                    estado_resultado = IF(estado_resultado = 'PENDIENTE', VALUES(estado_resultado), estado_resultado),
                    id_grado_destino = IF(estado_resultado = 'PENDIENTE', VALUES(id_grado_destino), id_grado_destino),
                    observaciones = IF(estado_resultado = 'PENDIENTE', VALUES(observaciones), observaciones),
                    confirmado_por = IF(estado_resultado = 'PENDIENTE', VALUES(confirmado_por), confirmado_por),
                    fecha_confirmacion = IF(estado_resultado = 'PENDIENTE', VALUES(fecha_confirmacion), fecha_confirmacion)"
            );

            $confirmados         = 0;
            $promovidos          = 0;
            $noPromovidos        = 0;
            $omitidosConfirmados = 0;
            $omitidosAjenos      = 0;
            $omitidosSinLiteral  = 0;

            foreach ($datosEstudiantes as $idEstudiante => $data) {
                $idEstudiante = (int) $idEstudiante;
                $idMat        = (int) ($data['id_matricula'] ?? 0);

                // Defensa PHP 1: Verificar pertenencia a nómina activa de la sección
                if (!isset($nominaValida[$idEstudiante]) || $nominaValida[$idEstudiante]['id_matricula'] !== $idMat) {
                    $omitidosAjenos++;
                    continue;
                }

                // Defensa PHP 2: Omitir registros que ya fueron confirmados previamente
                $estadoActual = $nominaValida[$idEstudiante]['estado_resultado'];
                if (in_array($estadoActual, [self::ESTADO_PROMOVIDO, self::ESTADO_NO_PROMOVIDO], true)
                    || !empty($nominaValida[$idEstudiante]['fecha_confirmacion'])) {
                    $omitidosConfirmados++;
                    continue;
                }

                // Defensa PHP 3: Literal válido (no se puede confirmar sin literal)
                $literal = !empty($data['literal']) ? strtoupper(trim((string)$data['literal'])) : null;
                if ($literal === null || !in_array($literal, self::LITERALES_VALIDOS, true)) {
                    $omitidosSinLiteral++;
                    continue;
                }

                $estado = self::estadoSugeridoPorLiteral($literal);
                if (!in_array($estado, [self::ESTADO_PROMOVIDO, self::ESTADO_NO_PROMOVIDO], true)) {
                    $omitidosSinLiteral++;
                    continue;
                }

                $idGradoDestino = $this->determinarGradoDestino($literal, $idGradoActual, $idGradoSiguiente);
                $obs = !empty($data['observaciones']) ? mb_substr(trim((string)$data['observaciones']), 0, 255) : null;

                $stmtUpsert->execute([
                    'id_est'        => $idEstudiante,
                    'id_anio'       => $idAnioEscolar,
                    'id_estr'       => $idEstructura,
                    'id_mat'        => $idMat,
                    'literal'       => $literal,
                    'estado'        => $estado,
                    'id_grado_dest' => $idGradoDestino,
                    'obs'           => $obs,
                    'usuario'       => $idUsuarioDirectivo > 0 ? $idUsuarioDirectivo : null,
                ]);

                $confirmados++;
                if ($estado === self::ESTADO_PROMOVIDO) {
                    $promovidos++;
                } else {
                    $noPromovidos++;
                }
            }

            if ($abrioTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $omitidosTotal = $omitidosConfirmados + $omitidosAjenos + $omitidosSinLiteral;

            if ($confirmados > 0 && $omitidosTotal > 0) {
                $mensaje = "{$confirmados} confirmado(s), {$omitidosTotal} omitido(s) (ya confirmados previamente u otros no válidos).";
            } elseif ($confirmados > 0) {
                $mensaje = "Resultados académicos confirmados oficialmente para {$confirmados} estudiante(s).";
            } elseif ($omitidosTotal > 0) {
                $mensaje = "No se realizaron cambios: {$omitidosTotal} registro(s) omitido(s) (ya confirmados previamente u otros no válidos).";
            } else {
                $mensaje = "No se recibieron datos para confirmar.";
            }

            return [
                'ok' => true,
                'mensaje' => $mensaje,
                'resumen' => [
                    'confirmados'          => $confirmados,
                    'promovidos'           => $promovidos,
                    'no_promovidos'        => $noPromovidos,
                    'omitidos'             => $omitidosTotal,
                    'omitidos_confirmados' => $omitidosConfirmados,
                    'omitidos_ajenos'      => $omitidosAjenos,
                    'omitidos_sin_literal' => $omitidosSinLiteral,
                ],
            ];
        } catch (Throwable $e) {
            if ($abrioTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('ResultadoAcademicoModel::confirmarResultados: ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'Error al confirmar los resultados académicos.'];
        }
    }

    /**
     * Consulta el resultado académico del estudiante en su último año escolar cursado.
     * Retorna null si no tiene matrícula previa o no tiene resultado registrado.
     */
    public function getResultadoUltimoAnio(int $idEstudiante, ?int $idAnioDestino = null): ?array
    {
        $params = ['id_est' => $idEstudiante];
        $filtroAnio = '';

        if ($idAnioDestino !== null && $idAnioDestino > 0) {
            // Comparar contra el año de inicio del año destino
            $stmtAnio = $this->pdo->prepare('SELECT anio_inicio FROM anio_escolar WHERE id_anio_escolar = :id');
            $stmtAnio->execute(['id' => $idAnioDestino]);
            $inicioDestino = $stmtAnio->fetchColumn();
            if ($inicioDestino) {
                $filtroAnio = 'AND ae.anio_inicio < :inicio_dest';
                $params['inicio_dest'] = $inicioDestino;
            }
        }

        $sql = "SELECT ra.id_resultado,
                       ra.id_estudiante,
                       ra.id_anio_escolar,
                       ae.nombre_periodo AS anio_escolar,
                       ra.id_estructura_academica,
                       g_orig.id_grado AS id_grado_cursado,
                       g_orig.nombre AS grado_cursado_nombre,
                       ra.id_matricula,
                       ra.literal,
                       ra.estado_resultado,
                       ra.id_grado_destino,
                       g_dest.nombre AS grado_destino_nombre,
                       ra.observaciones,
                       COALESCE(ra.fecha_propuesta, ra.fecha_actualizacion) AS fecha_resultado
                FROM resultado_academico ra
                INNER JOIN anio_escolar ae ON ae.id_anio_escolar = ra.id_anio_escolar
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ra.id_estructura_academica
                INNER JOIN grado g_orig ON g_orig.id_grado = ea.id_grado
                LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
                WHERE ra.id_estudiante = :id_est
                  {$filtroAnio}
                ORDER BY ae.anio_inicio DESC, ra.id_resultado DESC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return $res ?: null;
    }

    /**
     * Valida si un año escolar tiene estudiantes activos con resultados académicos
     * todavía en estado PENDIENTE o sin resultado registrado.
     * Usado en el candado de cierre de año escolar.
     */
    public function contarPendientesDeCierre(int $idAnioEscolar): int
    {
        $sql = "SELECT COUNT(DISTINCT m.id_estudiante)
                FROM matricula m
                LEFT JOIN resultado_academico ra ON ra.id_estudiante = m.id_estudiante
                                                AND ra.id_anio_escolar = m.id_anio_escolar
                WHERE m.id_anio_escolar = :id_anio
                  AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                  AND " . Matriculas::condicionCursando('m') . "
                  AND (ra.id_resultado IS NULL OR ra.estado_resultado = 'PENDIENTE')";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_anio' => $idAnioEscolar]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Helpers internos
     */
    public function esDocenteAsignada(int $idUsuario, int $idEstructura): bool
    {
        if ($idUsuario <= 0 || $idEstructura <= 0) {
            return false;
        }
        $idDocente = $this->getIdDocentePorUsuario($idUsuario);
        return ($idDocente > 0 && $this->docenteTieneSeccion($idDocente, $idEstructura));
    }

    public function porMatricula(int $idMatricula): ?array
    {
        if ($idMatricula <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT ra.id_resultado,
                    ra.id_estudiante,
                    ra.id_anio_escolar,
                    ra.id_estructura_academica,
                    ra.id_matricula,
                    ra.literal,
                    ra.estado_resultado,
                    ra.id_grado_destino,
                    ra.observaciones,
                    ra.propuesto_por,
                    ra.fecha_propuesta,
                    ra.fecha_actualizacion,
                    ra.fecha_creacion,
                    COALESCE(ra.fecha_actualizacion, ra.fecha_propuesta, ra.fecha_creacion) AS fecha_resultado,
                    CONCAT_WS(' ', p_prop.primer_nombre, p_prop.primer_apellido) AS propuesto_por_nombre
             FROM resultado_academico ra
             LEFT JOIN usuario u_prop ON u_prop.id_usuario = ra.propuesto_por
             LEFT JOIN persona p_prop ON p_prop.id_persona = u_prop.id_persona
             WHERE ra.id_matricula = :id_matricula
             LIMIT 1"
        );
        $stmt->execute(['id_matricula' => $idMatricula]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getIdDocentePorUsuario(int $idUsuario): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.id_docente
             FROM usuario u
             INNER JOIN empleado e ON e.id_persona = u.id_persona
             INNER JOIN docente d ON d.id_empleado = e.id_empleado
             WHERE u.id_usuario = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $idUsuario]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function docenteTieneSeccion(int $idDocente, int $idEstructura): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM asignacion_docente
             WHERE id_docente = :id_doc
               AND id_estructura_academica = :id_estr
               AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())"
        );
        $stmt->execute(['id_doc' => $idDocente, 'id_estr' => $idEstructura]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function getMatriculaCursandoId(int $idEstudiante, int $idAnioEscolar): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id_matricula
             FROM matricula m
             WHERE m.id_estudiante = :id_est
               AND m.id_anio_escolar = :id_anio
               AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
               AND " . Matriculas::condicionCursando('m') . "
             ORDER BY m.fecha_matricula DESC, m.id_matricula DESC
             LIMIT 1"
        );
        $stmt->execute(['id_est' => $idEstudiante, 'id_anio' => $idAnioEscolar]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function gradosOrdenados(): array
    {
        $rows = $this->pdo->query("SELECT id_grado AS id, nombre FROM grado WHERE estado = 'ACTIVO'")->fetchAll(PDO::FETCH_ASSOC);
        usort($rows, function ($a, $b) {
            $ordA = (int) (preg_match('/^(\d+)/', $a['nombre'], $m) ? $m[1] : PHP_INT_MAX);
            $ordB = (int) (preg_match('/^(\d+)/', $b['nombre'], $m) ? $m[1] : PHP_INT_MAX);
            return $ordA <=> $ordB;
        });
        return $rows;
    }

    public function calcularSiguienteGradoId(int $idGradoActual): ?int
    {
        $grados = $this->gradosOrdenados();
        $ordinalActual = PHP_INT_MAX;

        foreach ($grados as $g) {
            if ($g['id'] === $idGradoActual) {
                $ordinalActual = (int) (preg_match('/^(\d+)/', $g['nombre'], $m) ? $m[1] : PHP_INT_MAX);
                break;
            }
        }

        foreach ($grados as $g) {
            $ord = (int) (preg_match('/^(\d+)/', $g['nombre'], $m) ? $m[1] : PHP_INT_MAX);
            if ($ord > $ordinalActual) {
                return (int) $g['id'];
            }
        }

        return null;
    }

    /**
     * Determina el grado destino según el literal pedagógico obtenido.
     * Para A, B, C, D avanza al grado inmediato superior (o mantiene el actual si es el último grado del plantel, ej. 6to).
     * Para E permanece en el mismo grado cursado (repitiente).
     */
    public function determinarGradoDestino(?string $literal, int $idGradoActual, ?int $idGradoSiguiente): ?int
    {
        if ($literal === null || $literal === '') {
            return null;
        }

        if (in_array($literal, self::LITERALES_PROMUEVEN, true)) {
            return $idGradoSiguiente ?: $idGradoActual;
        }

        if ($literal === 'E') {
            return $idGradoActual;
        }

        return null;
    }

    /**
     * Decide si el dashboard del docente muestra la tarjeta de literales.
     *
     * Se muestra siempre que ya haya al menos un literal registrado, o cuando
     * la fecha actual está dentro de los días previos al cierre del año escolar.
     * Si el cierre es nulo o inválido, la condición por fecha no aplica.
     */
    public function debeMostrarAvanceLiterales(int $registrados, ?string $fechaCierre): bool
    {
        if ($registrados > 0) {
            return true;
        }

        if ($fechaCierre === null || trim($fechaCierre) === '') {
            return false;
        }

        $tsCierre = strtotime($fechaCierre);
        if ($tsCierre === false) {
            return false;
        }

        $tsInicioVentana = strtotime('-' . self::DIAS_AVISO_CIERRE_LITERALES . ' days', $tsCierre);
        $hoy = strtotime(date('Y-m-d'));

        return $hoy >= $tsInicioVentana;
    }

    /**
     * Avance de literales cargados para el dashboard del docente.
     * Cuenta lo registrado por el docente (literal IS NOT NULL),
     * el total de matriculados y calcula los pendientes y porcentaje.
     *
     * @param int $idEstructura
     * @return array{total: int, registrados: int, pendientes: int, porcentaje: float, literales_validos: list<string>, escala_texto: string}
     */
    public function getAvanceLiteralesDocente(int $idEstructura): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT m.id_estudiante) AS total_estudiantes,
                    COUNT(DISTINCT CASE WHEN ra.literal IS NOT NULL AND TRIM(ra.literal) != '' THEN ra.id_estudiante END) AS total_registrados,
                    COUNT(DISTINCT CASE 
                        WHEN ra.estado_resultado = 'PROMOVIDO' 
                          OR (ra.literal IN ('A','B','C','D') AND (ra.estado_resultado IS NULL OR ra.estado_resultado = 'PENDIENTE')) 
                        THEN ra.id_estudiante 
                    END) AS total_promovidos,
                    COUNT(DISTINCT CASE 
                        WHEN ra.estado_resultado = 'NO_PROMOVIDO' 
                          OR (ra.literal = 'E' AND (ra.estado_resultado IS NULL OR ra.estado_resultado = 'PENDIENTE')) 
                        THEN ra.id_estudiante 
                    END) AS total_no_promovidos,
                    COUNT(DISTINCT CASE WHEN ra.confirmado_por IS NOT NULL THEN ra.id_estudiante END) AS total_confirmados
               FROM matricula m
               LEFT JOIN resultado_academico ra
                      ON ra.id_estudiante = m.id_estudiante
                     AND ra.id_anio_escolar = m.id_anio_escolar
              WHERE m.id_estructura_academica = :estructura
                AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                AND " . Matriculas::condicionCursando('m')
        );
        $stmt->execute(['estructura' => $idEstructura]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total        = (int) ($row['total_estudiantes'] ?? 0);
        $registrados  = (int) ($row['total_registrados'] ?? 0);
        $pendientes   = max(0, $total - $registrados);
        $promovidos   = (int) ($row['total_promovidos'] ?? 0);
        $noPromovidos = (int) ($row['total_no_promovidos'] ?? 0);
        $confirmados  = (int) ($row['total_confirmados'] ?? 0);
        $porcentaje   = $total > 0 ? round(($registrados / $total) * 100, 1) : 0.0;

        return [
            'total'             => $total,
            'cargados'          => $registrados,
            'registrados'       => $registrados,
            'sin_literal'       => $pendientes,
            'pendientes'        => $pendientes,
            'promovidos'        => $promovidos,
            'no_promovidos'     => $noPromovidos,
            'confirmados'       => $confirmados,
            'porcentaje'        => $porcentaje,
            'literales_validos' => self::LITERALES_VALIDOS,
            'escala_texto'      => implode(', ', self::LITERALES_VALIDOS),
        ];
    }
}

