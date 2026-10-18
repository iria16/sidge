<?php
/**
 * Plantilla: Constancia de Estudio.
 *
 * Aporta su título oficial y la redacción del texto específico
 * (activo, retirado o egresado), delegando la estructura gráfica,
 * encabezado, datos del director y pie de página en la base común.
 *
 * @var array  $config      Configuración institucional
 * @var array  $estudiante  Datos del estudiante
 * @var string $modalidad   'activo', 'retirado' o 'egresado'
 */

$tituloConstancia = 'CONSTANCIA DE ESTUDIO';
$mostrarCodigoDependencia = true;

$textoEspecifico = match ($modalidad ?? 'activo') {
    'retirado' => 'cursó estudios en esta institución en <u><strong>'
        . htmlspecialchars(mb_strtoupper($estudiante['grado'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u> de <u><strong>'
        . htmlspecialchars(mb_strtoupper($config['nivel_educativo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' CÓDIGO '
        . htmlspecialchars(mb_strtoupper($config['nivel_codigo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u>, durante el periodo escolar <u><strong>'
        . htmlspecialchars(mb_strtoupper($estudiante['periodo_escolar'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u>, habiendo sido retirado(a) de la misma.',

    'egresado' => 'cursó y culminó estudios en esta institución en <u><strong>'
        . htmlspecialchars(mb_strtoupper($estudiante['grado'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u> de <u><strong>'
        . htmlspecialchars(mb_strtoupper($config['nivel_educativo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' CÓDIGO '
        . htmlspecialchars(mb_strtoupper($config['nivel_codigo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u>, durante el periodo escolar <u><strong>'
        . htmlspecialchars(mb_strtoupper($estudiante['periodo_escolar'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u>, habiendo egresado satisfactoriamente.',

    default => 'cursa estudios en esta institución en <u><strong>'
        . htmlspecialchars(mb_strtoupper($estudiante['grado'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u> de <u><strong>'
        . htmlspecialchars(mb_strtoupper($config['nivel_educativo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' CÓDIGO '
        . htmlspecialchars(mb_strtoupper($config['nivel_codigo'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u>, periodo escolar <u><strong>'
        . htmlspecialchars(mb_strtoupper($estudiante['periodo_escolar'] ?? '', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></u>.',
};

require __DIR__ . '/base.php';
