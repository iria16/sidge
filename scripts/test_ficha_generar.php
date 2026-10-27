<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Config/Env.php';
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Support/PdfRecursos.php';
require_once __DIR__ . '/../app/Support/Configuracion.php';
require_once __DIR__ . '/../app/Support/Direccion.php';
require_once __DIR__ . '/../app/Support/FichaEstudiantePdf.php';

use App\Support\Configuracion;
use App\Support\FichaEstudiantePdf;

$config = Configuracion::getAll();
$config['director_nombre'] = 'MARÍA ELENA CONTRERAS PÉREZ';
$config['director_cedula'] = 'V-14852369';
$config['director_cargo']  = 'Directora';
$config['plantel_nombre']  = 'Escuela Básica "Isidro Ramírez"';
$config['plantel_codigo_dea'] = 'OD12345678';
$config['plantel_codigo_dependencia'] = '007932154';
$config['lugar_expedicion'] = 'Valencia';

$estudiante = [
    'id' => 1,
    'primer_nombre' => 'Erickmar',
    'segundo_nombre' => 'Margarita',
    'primer_apellido' => 'Sanchez',
    'segundo_apellido' => 'Lopez',
    'nombre' => 'Erickmar Margarita Sanchez Lopez',
    'tipo_documento' => 'V',
    'numero_documento' => '31245896',
    'sexo' => 'F',
    'fecha_nacimiento' => '2014-04-12',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento' => 'Carabobo',
    'grado' => '5° Grado',
    'seccion' => 'A',
    'estado' => 'activo',
    'nacionalidad' => 'Venezolana',
];

$representante = [
    'representante_principal' => 1,
    'nombre_referencia' => 'MARÍA LÓPEZ',
    'parentesco' => 'Madre',
    'tipo_documento' => 'V',
    'numero_documento' => '16852369',
    'nacionalidad' => 'Venezolana',
    'contacto' => ['codigo' => '0414', 'numero' => '1234567', 'correo' => 'maria@correo.com'],
    'direccion' => [
        'calle' => 'Calle 5',
        'casa' => '12',
        'sector' => 'El Triunfo',
        'parroquia_nombre' => 'Miguel Peña',
        'municipio_nombre' => 'Valencia',
        'estado_nombre' => 'Carabobo',
        'referencia' => 'Cerca de la plaza',
    ],
];

$autorizado = [
    'nombre_referencia' => 'PEDRO SANCHEZ',
    'parentesco' => 'Tío',
    'tipo_documento' => 'V',
    'numero_documento' => '15963214',
    'nacionalidad' => 'Venezolana',
    'contacto' => ['codigo' => '0412', 'numero' => '7654321'],
];

$historial = [
    [
        'anio_escolar' => '2025-2026',
        'grado' => '4° Grado',
        'seccion' => 'A',
        'turno' => 'Mañana',
        'estado_anio' => 'CERRADO',
        'tipo_matricula' => 'REGULAR',
    ],
    [
        'anio_escolar' => '2026-2027',
        'grado' => '5° Grado',
        'seccion' => 'A',
        'turno' => 'Mañana',
        'estado_anio' => 'ACTIVO',
        'tipo_matricula' => 'REGULAR',
    ],
];

$foto = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

$pdf = FichaEstudiantePdf::generar(
    $config,
    $estudiante,
    [
        'talla_camisa' => '12',
        'talla_pantalon' => '12',
        'talla_zapato' => '34',
        'tipo_sangre' => 'O+',
        'alergias' => 'Ninguna conocida',
        'medicamentos' => 'Ninguno',
        'enfermedades' => 'Ninguna',
        'condiciones_especiales' => 'Ninguna',
        'observaciones_medicas' => 'Estudiante saludable',
        'talla_observaciones' => 'Estatura acorde a la edad',
    ],
    [$representante],
    [$autorizado],
    $historial,
    false,
    new DateTimeImmutable('2026-10-22'),
    $foto
);

echo "PDF generado exitosamente. Longitud: " . strlen($pdf) . " bytes\n";
file_put_contents(__DIR__ . '/test_ficha.pdf', $pdf);
