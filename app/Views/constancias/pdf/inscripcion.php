<?php
/**
 * Plantilla: Constancia de Inscripción.
 *
 * Aporta su título oficial y la redacción del texto específico de inscripción,
 * delegando la estructura gráfica, encabezado, datos del director y pie
 * de página en la base común.
 *
 * @var array $config     Configuración institucional
 * @var array $estudiante Datos del estudiante
 */

$tituloConstancia = 'CONSTANCIA DE INSCRIPCIÓN';
$mostrarCodigoDependencia = false;

$sexoEstInsc = strtoupper(trim((string) ($estudiante['sexo'] ?? '')));
$fueInscritoTexto = ($sexoEstInsc === 'F') ? 'fue inscrita' : (($sexoEstInsc === 'M') ? 'fue inscrito' : 'fue inscrito (a)');

$textoEspecifico = $fueInscritoTexto . ' en esta institución para cursar el <u><strong>'
    . htmlspecialchars(mb_strtoupper($estudiante['grado'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '</strong></u> de <u><strong>'
    . htmlspecialchars(mb_strtoupper($config['nivel_educativo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' CÓDIGO '
    . htmlspecialchars(mb_strtoupper($config['nivel_codigo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '</strong></u>, durante el periodo escolar <u><strong>'
    . htmlspecialchars(mb_strtoupper($estudiante['periodo_escolar'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '</strong></u>.';

require __DIR__ . '/base.php';
