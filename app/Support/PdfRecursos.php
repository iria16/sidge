<?php

declare(strict_types=1);

namespace App\Support;

use Dompdf\Options;
use RuntimeException;

/**
 * Carga de recursos para los documentos PDF.
 *
 * Los PDF se maquetan con Dompdf, que no es un navegador: no resuelve rutas
 * relativas como lo haría una página web, no descarga recursos remotos y su
 * subconjunto de CSS es reducido. Concentrar aquí la lectura de las hojas y la
 * construcción de las opciones evita que cada controlador tenga que resolverlo
 * por su cuenta y que un documento dependa de una hoja pensada para la web.
 *
 * Las hojas viven en public/assets/css/pdf/ y se compilan desde
 * resources/scss/pdf/ con `npm run sass:build:pdf`.
 *
 * @see \App\Support\ConstanciaPdf Constancias de estudio e inscripción.
 * @see \App\Controllers\EstudiantesController::exportar() Ficha integral.
 */
final class PdfRecursos
{
    /**
     * Hoja propia de cada documento. La clave es la que recibe estilos().
     *
     * Cada hoja ya trae incrustada la base compartida al compilarse, porque
     * ambas empiezan con @import 'base'. Así lo común (reinicio de lienzo,
     * tipografía base) está escrito una sola vez en resources/scss/pdf/_base.scss
     * y no hay que concatenar nada en tiempo de ejecución.
     */
    private const HOJAS_POR_DOCUMENTO = [
        'constancia' => 'constancia',
        'ficha'      => 'ficha-estudiante',
        'nomina'     => 'nomina-seccion',
    ];

    /**
     * Devuelve el CSS del documento.
     *
     * El CSS se devuelve como texto y no como <link> a propósito: Dompdf resuelve
     * un <link> contra su chroot y falla silenciosamente si la ruta no coincide
     * con el sistema de archivos del servidor. Leer el archivo y embeberlo evita
     * depender de cómo esté montado public/.
     *
     * @param string $documento 'constancia' o 'ficha'.
     * @throws RuntimeException Si el documento no existe o falta su archivo CSS.
     */
    public static function estilos(string $documento): string
    {
        if (!isset(self::HOJAS_POR_DOCUMENTO[$documento])) {
            throw new RuntimeException("Documento PDF desconocido: {$documento}");
        }

        return self::leerHoja(self::HOJAS_POR_DOCUMENTO[$documento]);
    }

    /**
     * Construye las opciones de Dompdf compartidas por todos los documentos.
     *
     * isRemoteEnabled queda en false a propósito: el documento nunca debe ir a
     * buscar nada a la red. Los logos, la firma y el sello llegan como Data URI,
     * que es la única forma de que una imagen llegue al PDF sin depender de que
     * el servidor pueda leer el archivo por ruta.
     *
     * @param string $fuenteDefecto Familia tipográfica base (p. ej. 'Times-Roman').
     */
    public static function opciones(string $fuenteDefecto = 'Times-Roman'): Options
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', $fuenteDefecto);
        // chroot acota qué rutas locales puede abrir el motor. Incluye la raíz del
        // proyecto para las hojas de la vista y public/ para las imágenes.
        $options->set('chroot', [
            Configuracion::getProjectRoot(),
            Configuracion::getPublicRoot(),
        ]);

        return $options;
    }

    /**
     * Lee una hoja de public/assets/css/pdf/.
     *
     * @throws RuntimeException Si el archivo no está en disco.
     */
    private static function leerHoja(string $hoja): string
    {
        $ruta = Configuracion::getPublicRoot()
            . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css'
            . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $hoja . '.css';

        if (!is_file($ruta)) {
            throw new RuntimeException(
                "No se encontró la hoja de estilos del PDF ({$hoja}.css). "
                . 'Ejecute «npm run sass:build:pdf» para compilarla.'
            );
        }

        $css = file_get_contents($ruta);
        if ($css === false) {
            throw new RuntimeException("No se pudo leer la hoja de estilos del PDF ({$hoja}.css).");
        }

        return $css;
    }
}