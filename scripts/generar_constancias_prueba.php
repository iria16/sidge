<?php

declare(strict_types=1);

/**
 * Script CLI: Generador de PDFs de prueba para Constancias de Estudio e Inscripción.
 *
 * Utiliza estrictamente datos ficticios y marca de agua "PRUEBA" para validación visual.
 *
 * Uso:
 *   php scripts/generar_constancias_prueba.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Support/Configuracion.php';
require_once __DIR__ . '/../app/Support/ConstanciaPdf.php';

use App\Config\Database;
use App\Support\Configuracion;
use App\Support\ConstanciaPdf;

echo "=========================================================\n";
echo " SIGDE - Generador de Constancias de Prueba (PDF)       \n";
echo "=========================================================\n\n";

$pdo = (new Database())->getConnection();
Configuracion::setPdo($pdo);
$config = Configuracion::getAll();

// Datos directivos estrictamente ficticios para el entorno de pruebas
$config['director_nombre'] = 'MARÍA ELENA CONTRERAS PEÑA';
$config['director_cedula'] = 'V-14.852.369';
$config['director_cargo']  = 'Directora (E)';

$tz = new DateTimeZone('America/Caracas');
$hoy = new DateTimeImmutable('now', $tz);

// Carpeta de salida (únicamente en storage, nunca en public/uploads)
$dirs = [
    __DIR__ . '/../storage/constancias_prueba',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// Casos de prueba con datos ficticios realistas
$casos = [
    [
        'archivo'   => 'constancia_estudio_activo.pdf',
        'tipo'      => 'estudio',
        'modalidad' => 'activo',
        'titulo'    => 'Constancia de Estudio - Estudiante Activo (Cédula Escolar)',
        'estudiante' => [
            'nombre_completo'      => 'JUAN ANDRÉS RANGEL MARTÍNEZ',
            'tipo_documento'       => 'CE',
            'numero_documento'     => '38112233',
            'municipio_nacimiento' => 'Valencia',
            'estado_nacimiento'    => 'Carabobo',
            'fecha_nacimiento'     => new DateTimeImmutable('2015-06-18', $tz),
            'grado'                => '6to Grado',
            'periodo_escolar'      => '2026-2027',
        ],
    ],
    [
        'archivo'   => 'constancia_estudio_retirado.pdf',
        'tipo'      => 'estudio',
        'modalidad' => 'retirado',
        'titulo'    => 'Constancia de Estudio - Estudiante Retirado (Cédula V)',
        'estudiante' => [
            'nombre_completo'      => 'CARLOS EDUARDO GÓMEZ SALAZAR',
            'tipo_documento'       => 'V',
            'numero_documento'     => '33445566',
            'municipio_nacimiento' => 'Valencia',
            'estado_nacimiento'    => 'Carabobo',
            'fecha_nacimiento'     => new DateTimeImmutable('2014-03-22', $tz),
            'grado'                => '5to Grado',
            'periodo_escolar'      => '2025-2026',
        ],
    ],
    [
        'archivo'   => 'constancia_estudio_egresado.pdf',
        'tipo'      => 'estudio',
        'modalidad' => 'egresado',
        'titulo'    => 'Constancia de Estudio - Estudiante Egresado (Cédula V)',
        'estudiante' => [
            'nombre_completo'      => 'VALENTINA ISABELLA MORA CASTILLO',
            'tipo_documento'       => 'V',
            'numero_documento'     => '32556677',
            'municipio_nacimiento' => 'Valencia',
            'estado_nacimiento'    => 'Carabobo',
            'fecha_nacimiento'     => new DateTimeImmutable('2013-11-05', $tz),
            'grado'                => '6to Grado',
            'periodo_escolar'      => '2025-2026',
        ],
    ],
    [
        'archivo'   => 'constancia_estudio_extranjero.pdf',
        'tipo'      => 'estudio',
        'modalidad' => 'activo',
        'titulo'    => 'Constancia de Estudio - Estudiante Extranjero (Documento E)',
        'estudiante' => [
            'nombre_completo'      => 'JEAN PIERRE DUPONT CASTILLO',
            'tipo_documento'       => 'E',
            'numero_documento'     => '84998877',
            'municipio_nacimiento' => 'Valencia',
            'estado_nacimiento'    => 'Carabobo',
            'fecha_nacimiento'     => new DateTimeImmutable('2015-09-14', $tz),
            'grado'                => '6to Grado',
            'periodo_escolar'      => '2026-2027',
        ],
    ],
    [
        'archivo'   => 'constancia_inscripcion_activo.pdf',
        'tipo'      => 'inscripcion',
        'modalidad' => 'activo',
        'titulo'    => 'Constancia de Inscripción - Estudiante Activo (Cédula V)',
        'estudiante' => [
            'nombre_completo'      => 'SOFÍA ALEJANDRA BLANCO PINTO',
            'tipo_documento'       => 'V',
            'numero_documento'     => '34667788',
            'municipio_nacimiento' => 'Valencia',
            'estado_nacimiento'    => 'Carabobo',
            'fecha_nacimiento'     => new DateTimeImmutable('2015-02-10', $tz),
            'grado'                => '6to Grado',
            'periodo_escolar'      => '2026-2027',
        ],
    ],
];

foreach ($casos as $caso) {
    echo "Generando: {$caso['titulo']}...\n";

    if ($caso['tipo'] === 'estudio') {
        $pdfBytes = ConstanciaPdf::generarEstudio(
            $config,
            $caso['estudiante'],
            $caso['modalidad'],
            $hoy,
            true // marcaAguaPrueba = true
        );
    } else {
        $pdfBytes = ConstanciaPdf::generarInscripcion(
            $config,
            $caso['estudiante'],
            $hoy,
            true // marcaAguaPrueba = true
        );
    }

    foreach ($dirs as $dir) {
        $destino = $dir . DIRECTORY_SEPARATOR . $caso['archivo'];
        file_put_contents($destino, $pdfBytes);
    }

    $tamKb = round(strlen($pdfBytes) / 1024, 1);
    echo "  [OK] Guardado en storage: {$caso['archivo']} ({$tamKb} KB)\n\n";
}

echo "Proceso finalizado con éxito.\n";
