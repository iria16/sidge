<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * GradeCertificateModel ---------------------------- Modelo para la gestión de
 * Certificados de Calificaciones (según Art. 108 RGLOE).
 */
class GradeCertificateModel extends BaseModel
{
    protected string $table = 'certificados_calificaciones';
    protected string $primaryKey = 'id_certificado';

    /**
     * Obtiene un certificado de calificaciones por estudiante y año escolar.
     */
    public function getCertificadoPorEstudiante(int $idEstudiante, int $idAnioEscolar): ?array
    {
        $sql = "
            SELECT 
                cc.id_certificado,
                cc.id_estudiante,
                cc.id_anio_escolar,
                cc.id_estructura_academica,
                cc.pdf_data,
                cc.fecha_emision,
                cc.texto_art108,
                est.id_estudiante,
                p.primer_nombre,
                p.segundo_nombre,
                p.primer_apellido,
                p.segundo_apellido,
                p.tipo_documento,
                p.numero_documento,
                p.fecha_nacimiento,
                ra.literal,
                ra.estado_resultado,
                g.nombre AS grado,
                s.nombre AS seccion,
                a.nombre_periodo AS periodo_escolar
            FROM certificados_calificaciones cc
            INNER JOIN estudiante est ON est.id_estudiante = cc.id_estudiante
            INNER JOIN persona p ON p.id_persona = est.id_persona
            INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante
            INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
            INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
            INNER JOIN grado g ON g.id_grado = ea.id_grado
            LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
            LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                AND ra.id_anio_escolar = m.id_anio_escolar
            WHERE cc.id_estudiante = :id_estudiante
                AND cc.id_anio_escolar = :id_anio_escolar
                AND cc.tipo_certificado = 'calificaciones'
            ORDER BY cc.fecha_emision DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_estudiante' => $idEstudiante,
            'id_anio_escolar' => $idAnioEscolar,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene todos los certificados de calificaciones para una sección.
     */
    public function getCertificadosPorSeccion(int $idEstructura, int $idAnioEscolar): array
    {
        $sql = "
            SELECT 
                cc.id_certificado,
                cc.id_estudiante,
                cc.fecha_emision,
                est.id_estudiante,
                p.primer_nombre,
                p.segundo_nombre,
                p.primer_apellido,
                p.segundo_apellido,
                p.tipo_documento,
                p.numero_documento,
                p.fecha_nacimiento,
                ra.literal,
                ra.estado_resultado,
                g.nombre AS grado,
                s.nombre AS seccion,
                a.nombre_periodo AS periodo_escolar,
                COUNT(*) as estudiantes_count
            FROM certificados_calificaciones cc
            INNER JOIN estudiante est ON est.id_estudiante = cc.id_estudiante
            INNER JOIN persona p ON p.id_persona = est.id_persona
            INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante
            INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
            INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
            INNER JOIN grado g ON g.id_grado = ea.id_grado
            LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
            LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                AND ra.id_anio_escolar = m.id_anio_escolar
            WHERE cc.id_estructura_academica = :id_estructura
                AND cc.id_anio_escolar = :id_anio_escolar
                AND cc.tipo_certificado = 'calificaciones'
            GROUP BY cc.id_estudiante
            ORDER BY est.id_estudiante
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_estructura' => $idEstructura,
            'id_anio_escolar' => $idAnioEscolar,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si un certificado de calificaciones ya existe para un estudiante.
     */
    public function verificarCertificadoExiste(int $idEstudiante, int $idAnioEscolar): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM certificados_calificaciones
            WHERE id_estudiante = :id_estudiante
                AND id_anio_escolar = :id_anio_escolar
                AND tipo_certificado = 'calificaciones'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_estudiante' => $idEstudiante,
            'id_anio_escolar' => $idAnioEscolar,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene el texto del Art. 108 desde la hoja física del colegio.
     */
    public function obtenerTextoArt108Fisico(): string
    {
        // Por ahora, devolver el texto estándar
        // En el futuro, esto podría leer desde un archivo o base de datos
        return "ESCALA A-E DEL ARTÍCULO 108 DEL REGLAMENTO GENERAL DE LA LEY ORGÁNICA DE EDUCACIÓN (RGLOE)\n" .
               "A - Promoción: Comprende los desempeños sobresalientes, Notables, Bueno y Regular.\n" .
               "B - Promoción: Comprende los desempeños Notables, Bueno y Regular.\n" .
               "C - Promoción: Comprende los desempeños Bueno y Regular.\n" .
               "D - Promoción: Comprende el desempeño Regular.\n" .
               "E - No Promoción: Comprende el desempeño Insuficiente.\n" .
               "\n" .
               "Los estudiantes que obtengan un literal Promoción (A, B, C, D) pasan al grado inmediatamente superior.\n" .
               "Los estudiantes que obtengan un literal No Promoción (E) reprueban y permanecen en el mismo grado.\n" .
               "Todos los estudiantes deben cumplir con los requisitos establecidos en la normativa legal vigente para su promoción o readmisión.";
    }

    /**
     * Datos completos de un estudiante para emitir su certificado individual.
     * Devuelve null si no pertenece a la matrícula de la sección o si no tiene literal.
     */
    public function getDatosParaCertificado(int $idEstudiante, int $idEstructura): ?array
    {
        $sql = "
            SELECT 
                est.id_estudiante,
                p.primer_nombre,
                p.segundo_nombre,
                p.primer_apellido,
                p.segundo_apellido,
                p.tipo_documento,
                p.numero_documento,
                p.fecha_nacimiento,
                p.sexo,
                p.estado AS estado_persona,
                par.nombre AS parroquia_nacimiento,
                mun.nombre AS municipio_nacimiento,
                COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                g.nombre AS grado,
                s.nombre AS seccion,
                a.nombre_periodo AS periodo_escolar,
                ra.literal,
                ra.estado_resultado,
                ra.id_grado_destino,
                g_dest.nombre AS grado_destino_nombre,
                ra.observaciones,
                m.id_matricula,
                m.id_anio_escolar,
                m.id_estructura_academica,
                CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                CONCAT_WS(' ', p_doc.primer_nombre, p_doc.segundo_nombre, p_doc.primer_apellido, p_doc.segundo_apellido) AS docente_nombre,
                CONCAT(p_doc.tipo_documento, '-', p_doc.numero_documento) AS docente_ci
            FROM estudiante est
            INNER JOIN persona p ON p.id_persona = est.id_persona
            LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
            LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
            LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
            LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
            INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante
            INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
            INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
            INNER JOIN grado g ON g.id_grado = ea.id_grado
            LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
            LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
            LEFT JOIN docente d_doc ON d_doc.id_docente = ad.id_docente
            LEFT JOIN empleado e_doc ON e_doc.id_empleado = d_doc.id_empleado
            LEFT JOIN persona p_doc ON p_doc.id_persona = e_doc.id_persona
            LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                AND ra.id_anio_escolar = m.id_anio_escolar
            LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
            WHERE est.id_estudiante = :id_estudiante
              AND m.id_estructura_academica = :id_estructura
              AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_estudiante' => $idEstudiante,
            'id_estructura' => $idEstructura,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Datos de todos los estudiantes con literal de una sección, para emitir
     * los certificados en lote.
     */
    public function getDatosParaCertificadosPorSeccion(int $idEstructura): array
    {
        $sql = "
            SELECT 
                est.id_estudiante,
                CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                p.tipo_documento,
                p.numero_documento,
                p.fecha_nacimiento,
                p.sexo,
                par.nombre AS parroquia_nacimiento,
                mun.nombre AS municipio_nacimiento,
                COALESCE(est_nac.nombre, est_mun.nombre) AS estado_nacimiento,
                g.nombre AS grado,
                s.nombre AS seccion,
                a.nombre_periodo AS periodo_escolar,
                ra.literal,
                ra.estado_resultado,
                ra.id_grado_destino,
                g_dest.nombre AS grado_destino_nombre,
                m.id_matricula,
                m.id_anio_escolar,
                m.id_estructura_academica,
                CONCAT_WS(' ', p_doc.primer_nombre, p_doc.segundo_nombre, p_doc.primer_apellido, p_doc.segundo_apellido) AS docente_nombre,
                CONCAT(p_doc.tipo_documento, '-', p_doc.numero_documento) AS docente_ci
            FROM estudiante est
            INNER JOIN persona p ON p.id_persona = est.id_persona
            LEFT JOIN parroquia par ON par.id_parroquia = p.id_parroquia_nacimiento
            LEFT JOIN municipio mun ON mun.id_municipio = par.id_municipio
            LEFT JOIN estado est_mun ON est_mun.id_estado = mun.id_estado
            LEFT JOIN estado est_nac ON est_nac.id_estado = p.id_estado_nacimiento
            INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante
            INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
            INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
            INNER JOIN grado g ON g.id_grado = ea.id_grado
            LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
            LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
            LEFT JOIN docente d_doc ON d_doc.id_docente = ad.id_docente
            LEFT JOIN empleado e_doc ON e_doc.id_empleado = d_doc.id_empleado
            LEFT JOIN persona p_doc ON p_doc.id_persona = e_doc.id_persona
            LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                AND ra.id_anio_escolar = m.id_anio_escolar
            LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
            WHERE m.id_estructura_academica = :id_estructura
              AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
              AND ra.literal IS NOT NULL
              AND ra.literal != ''
            ORDER BY p.primer_apellido ASC, p.primer_nombre ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id_estructura' => $idEstructura]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Regla de 6.° grado: el certificado de calificaciones solo aplica a
     * quienes no alcanzaron las competencias (literal E).
     */
    public function filtrarCertificablesDeSexto(array $estudiantes): array
    {
        return array_values(array_filter(
            $estudiantes,
            static fn (array $est): bool => self::literalEsNoPromovido((string) ($est['literal'] ?? ''))
        ));
    }

    public static function esGradoSexto(string $grado): bool
    {
        return (bool) preg_match('/^6|sexto/i', $grado);
    }

    public static function literalEsNoPromovido(string $literal): bool
    {
        return strtoupper(trim($literal)) === 'E';
    }

    public static function literalEsPromovido(string $literal): bool
    {
        return in_array(strtoupper(trim($literal)), ['A', 'B', 'C', 'D'], true);
    }
}