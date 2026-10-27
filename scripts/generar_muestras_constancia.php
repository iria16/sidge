<?php
declare(strict_types=1);

spl_autoload_register(function ($clase) {
    $prefijo = 'App\\';
    $dir_base = __DIR__ . '/../app/';
    $longitud = strlen($prefijo);
    if (strncmp($prefijo, $clase, $longitud) !== 0) return;
    $clase_relativa = substr($clase, $longitud);
    $archivo = $dir_base . str_replace('\\', '/', $clase_relativa) . '.php';
    if (file_exists($archivo)) require_once $archivo;
});

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Config/Env.php';
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Support/Configuracion.php';
require_once __DIR__ . '/../app/Support/ConstanciaPdf.php';

use App\Support\Configuracion;
use App\Support\ConstanciaPdf;

$outDir = __DIR__ . '/../storage/constancias_muestra';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$config = Configuracion::getAll();
$config['director_nombre'] = 'MARÍA ELENA CONTRERAS PÉREZ';
$config['director_cedula'] = 'V-14852369';
$config['director_cargo']  = 'Directora';
$config['director_sexo']   = 'F';
$config['plantel_nombre']  = 'Escuela Básica "Isidro Ramírez"';
$config['plantel_codigo_dea'] = 'OD12345678';
$config['plantel_codigo_dependencia'] = '007932154';
$config['plantel_municipio'] = 'Valencia';
$config['plantel_parroquia'] = 'Miguel Peña';
$config['cdce_estado']     = 'Carabobo';
$config['nivel_educativo'] = 'Educación Primaria';
$config['nivel_codigo']    = '21000';
$config['lugar_expedicion'] = 'Valencia';

$fechaExp = new DateTimeImmutable('2026-10-22');

// 1. Caso con Cédula de Identidad (Femenina, con sello)
$estudiante1 = [
    'nombre_completo'      => 'VALERIN ROJAS DÍAZ',
    'tipo_documento'       => 'V',
    'numero_documento'     => '29863136',
    'sexo'                 => 'F',
    'fecha_nacimiento'     => '2015-05-16',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento'    => 'Carabobo',
    'grado'                => '1° Grado',
    'periodo_escolar'      => '2026-2027',
];
$pdf1 = ConstanciaPdf::generarEstudio($config, $estudiante1, 'activo', $fechaExp);
file_put_contents($outDir . '/constancia_caso1_ci.pdf', $pdf1);

// 2. Caso con Cédula Escolar (Masculino, con sello)
$estudiante2 = [
    'nombre_completo'      => 'LEO PINTO MARTÍNEZ',
    'tipo_documento'       => 'CE',
    'numero_documento'     => 'V1162458963',
    'sexo'                 => 'M',
    'fecha_nacimiento'     => '2017-08-20',
    'municipio_nacimiento' => 'Naguanagua',
    'estado_nacimiento'    => 'Carabobo',
    'grado'                => '3° Grado',
    'periodo_escolar'      => '2026-2027',
];
$pdf2 = ConstanciaPdf::generarEstudio($config, $estudiante2, 'activo', $fechaExp);
file_put_contents($outDir . '/constancia_caso2_ce.pdf', $pdf2);

// 3. Caso sin Sello y sin lugar de nacimiento opcional
$configSinSello = $config;
$configSinSello['institucion_sello'] = '';

$estudiante3 = [
    'nombre_completo'      => 'CARLOS MANUEL GÓMEZ PINTO',
    'tipo_documento'       => 'V',
    'numero_documento'     => '32123456',
    'sexo'                 => 'M',
    'fecha_nacimiento'     => '2014-11-05',
    'municipio_nacimiento' => '',
    'estado_nacimiento'    => '',
    'grado'                => '6° Grado',
    'periodo_escolar'      => '2026-2027',
];
$pdf3 = ConstanciaPdf::generarEstudio($configSinSello, $estudiante3, 'activo', $fechaExp);
file_put_contents($outDir . '/constancia_caso3_sin_sello.pdf', $pdf3);

// 4. Caso Constancia de Retiro (Encabezado solo Ministerio)
$estudianteRetiro = [
    'nombre_completo'        => 'SOFÍA VALENTINA MÉNDEZ TORRES',
    'tipo_documento'         => 'V',
    'numero_documento'       => '31542890',
    'sexo'                   => 'F',
    'fecha_nacimiento'       => '2016-03-10',
    'municipio_nacimiento'   => 'Valencia',
    'estado_nacimiento'      => 'Carabobo',
    'grado'                  => '4° Grado',
    'periodo_escolar'        => '2026-2027',
    'representante_nombre'   => 'CARMEN ELENA TORRES DE MÉNDEZ',
    'representante_tipo_doc' => 'V',
    'representante_num_doc'  => '15874123',
    'fecha_retiro'           => '2026-10-15',
    'motivo'                 => 'CAMBIO DE RESIDENCIA',
];
$pdfRetiro = ConstanciaPdf::generarRetiro($config, $estudianteRetiro, $fechaExp);
file_put_contents($outDir . '/constancia_retiro_muestra.pdf', $pdfRetiro);

// 5. Caso Constancia de Prosecución (Ministerio + Escudo Nacional + Tabla doble firma)
$estudianteProsecucion = [
    'nombre_completo'      => 'MATEO ALEJANDRO RIVAS CASTRO',
    'tipo_documento'       => 'CE',
    'numero_documento'     => 'V1172583690',
    'sexo'                 => 'M',
    'fecha_nacimiento'     => '2017-04-12',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento'    => 'Carabobo',
    'grado_origen'         => '2° Grado',
    'grado_destino'        => '3° Grado',
    'literal'              => 'A',
    'periodo_escolar'      => '2025-2026',
];
$pdfProsecucion = ConstanciaPdf::generarProsecucion($config, $estudianteProsecucion, new DateTimeImmutable('2026-07-28'));
file_put_contents($outDir . '/constancia_prosecucion_muestra.pdf', $pdfProsecucion);

// 6. Caso Certificado de Educación Primaria (6.° Grado, con Epígrafe configurable)
$config['certificado_epigrafe'] = 'BICENTENARIO DE LA UNIDAD AMERICANA: CONGRESO ANFICTIÓNICO DE PANAMÁ';
$estudianteEgreso = [
    'nombre_completo'      => 'CAMILA ANDREA SALAZAR LÓPEZ',
    'tipo_documento'       => 'V',
    'numero_documento'     => '33456789',
    'sexo'                 => 'F',
    'fecha_nacimiento'     => '2014-09-22',
    'municipio_nacimiento' => 'Valencia',
    'estado_nacimiento'    => 'Carabobo',
    'grado'                => '6° Grado',
    'literal'              => 'A',
    'periodo_escolar'      => '2025-2026',
];
$pdfEgreso = ConstanciaPdf::generarEgreso($config, $estudianteEgreso, new DateTimeImmutable('2026-07-28'));
file_put_contents($outDir . '/certificado_primaria_muestra.pdf', $pdfEgreso);

echo "PDFs generados con éxito en $outDir\n";
