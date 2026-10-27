<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * CertificadoCalificacionesModel
 * ----------------------------
 * Modelo para la gestión de Certificados de Calificaciones (según Art. 108 RGLOE).
 *
 * Este modelo gestiona:
 * - El almacenamiento de los Certificados de Calificaciones en el sistema
 * - La verificación de certificados existentes
 * - La comparación con las hojas físicas del colegio
 * - El historial de generación de certificados
 */
class CertificadoCalificacionesModel extends BaseModel
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
     * Obtiene todos los estudiantes con literales en una sección.
     */
    public function getEstudiantesConLiteralesPorSeccion(int $idEstructura, int $idAnioEscolar): array
    {
        $sql = "
            SELECT 
                est.id_estudiante,
                CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo,
                p.tipo_documento,
                p.numero_documento,
                p.fecha_nacimiento,
                g.nombre AS grado,
                s.nombre AS seccion,
                a.nombre_periodo AS periodo_escolar,
                ra.literal,
                ra.estado_resultado,
                ra.id_grado_destino,
                g_dest.nombre AS grado_destino_nombre
            FROM estudiante est
            INNER JOIN persona p ON p.id_persona = est.id_persona
            INNER JOIN matricula m ON m.id_estudiante = est.id_estudiante
            INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
            INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
            INNER JOIN grado g ON g.id_grado = ea.id_grado
            LEFT JOIN grado g_dest ON g_dest.id_grado = ra.id_grado_destino
            LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
            LEFT JOIN resultado_academico ra ON ra.id_estudiante = est.id_estudiante
                AND ra.id_anio_escolar = m.id_anio_escolar
            WHERE m.id_estructura_academica = :id_estructura
                AND m.id_anio_escolar = :id_anio_escolar
                AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                AND ra.literal IS NOT NULL
                AND ra.literal != ''
            ORDER BY g.nombre ASC, s.nombre ASC, p.primer_apellido ASC, p.primer_nombre ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_estructura' => $idEstructura,
            'id_anio_escolar' => $idAnioEscolar,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}