<?php
declare(strict_types=1);

spl_autoload_register(function ($clase) {
    $prefijo = 'App\\';
    $dir_base = __DIR__ . '/../app/';
    $longitud = strlen($prefijo);
    if (strncmp($prefijo, $clase, $longitud) !== 0) return;
    $clase_relativa = substr($clase, $longitud);
    $archivo = $dir_base . str_replace('\\', '/', $clase_relativa) . '.php';
    if (file_exists($archivo)) require $archivo;
});

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Support/Configuracion.php';
require_once __DIR__ . '/../app/Support/CertificadoCalificacionesPdf.php';

use App\Config\Database;
use App\Support\Configuracion;
use App\Support\CertificadoCalificacionesPdf;

$pdo = (new Database())->getConnection();
$config = Configuracion::getAll();

$dirBoletas = __DIR__ . '/../storage/boletas';
if (!is_dir($dirBoletas)) {
    mkdir($dirBoletas, 0777, true);
}

// 1. Caso: 3.º con literal A
$est3A = [
    'nombre_completo'      => 'ANDRÉS FELIPE RIVAS HERNÁNDEZ',
    'tipo_documento'       => 'V',
    'numero_documento'     => '13456781',
    'fecha_nacimiento'     => '2016-04-12',
    'fecha_nacimiento_texto' => '12 DE ABRIL DE 2016',
    'literal'              => 'A',
    'grado'                => '3° Grado',
    'grado_origen'         => '3° Grado',
    'periodo_escolar'      => '2026-2027',
    'id_anio_escolar'      => 238,
    'estado_resultado'     => 'PROMOVIDO',
    'siguiente_grado_nombre' => '4° Grado',
    'municipio_nacimiento' => 'VALENCIA',
    'estado_nacimiento'    => 'CARABOBO',
];
$pdf3A = CertificadoCalificacionesPdf::generar($config, $est3A);
file_put_contents($dirBoletas . '/boleta_3ro_literal_A.pdf', $pdf3A);
echo "1. PDF generado: storage/boletas/boleta_3ro_literal_A.pdf (" . strlen($pdf3A) . " bytes)\n";

// 2. Caso: 3.º con literal E
$est3E = [
    'nombre_completo'      => 'BEATRIZ ELENA MENDOZA SILVA',
    'tipo_documento'       => 'V',
    'numero_documento'     => '13456782',
    'fecha_nacimiento'     => '2016-08-20',
    'fecha_nacimiento_texto' => '20 DE AGOSTO DE 2016',
    'literal'              => 'E',
    'grado'                => '3° Grado',
    'grado_origen'         => '3° Grado',
    'periodo_escolar'      => '2026-2027',
    'id_anio_escolar'      => 238,
    'estado_resultado'     => 'NO_PROMOVIDO',
    'siguiente_grado_nombre' => null,
    'municipio_nacimiento' => 'VALENCIA',
    'estado_nacimiento'    => 'CARABOBO',
];
$pdf3E = CertificadoCalificacionesPdf::generar($config, $est3E);
file_put_contents($dirBoletas . '/boleta_3ro_literal_E.pdf', $pdf3E);
echo "2. PDF generado: storage/boletas/boleta_3ro_literal_E.pdf (" . strlen($pdf3E) . " bytes)\n";

// 3. Caso: 6.º con literal A
$est6A = [
    'nombre_completo'      => 'CARLOS MANUEL GÓMEZ PINTO',
    'tipo_documento'       => 'V',
    'numero_documento'     => '13456783',
    'fecha_nacimiento'     => '2013-02-15',
    'fecha_nacimiento_texto' => '15 DE FEBRERO DE 2013',
    'literal'              => 'A',
    'grado'                => '6° Grado',
    'grado_origen'         => '6° Grado',
    'periodo_escolar'      => '2026-2027',
    'id_anio_escolar'      => 238,
    'estado_resultado'     => 'PROMOVIDO',
    'siguiente_grado_nombre' => '1er Año del Nivel de Educación Media',
    'municipio_nacimiento' => 'NAGUANAGUA',
    'estado_nacimiento'    => 'CARABOBO',
];
$pdf6A = CertificadoCalificacionesPdf::generar($config, $est6A);
file_put_contents($dirBoletas . '/boleta_6to_literal_A.pdf', $pdf6A);
echo "3. PDF generado: storage/boletas/boleta_6to_literal_A.pdf (" . strlen($pdf6A) . " bytes)\n";

// 4. Caso: 6.º con literal E
$est6E = [
    'nombre_completo'      => 'DANIELA CAROLINA TORRES VEGA',
    'tipo_documento'       => 'V',
    'numero_documento'     => '13456784',
    'fecha_nacimiento'     => '2013-11-05',
    'fecha_nacimiento_texto' => '05 DE NOVIEMBRE DE 2013',
    'literal'              => 'E',
    'grado'                => '6° Grado',
    'grado_origen'         => '6° Grado',
    'periodo_escolar'      => '2026-2027',
    'id_anio_escolar'      => 238,
    'estado_resultado'     => 'NO_PROMOVIDO',
    'siguiente_grado_nombre' => null,
    'municipio_nacimiento' => 'SAN DIEGO',
    'estado_nacimiento'    => 'CARABOBO',
];
$pdf6E = CertificadoCalificacionesPdf::generar($config, $est6E);
file_put_contents($dirBoletas . '/boleta_6to_literal_E.pdf', $pdf6E);
echo "4. PDF generado: storage/boletas/boleta_6to_literal_E.pdf (" . strlen($pdf6E) . " bytes)\n";
