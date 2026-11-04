<?php

declare(strict_types=1);

namespace App\Http;

/**
 * BinaryResponse
 *
 * Respuesta binaria (PDF, CSV, etc.) que se descarga o se abre en el navegador.
 *
 * Existe para que ningún controlador repita el bloque de headers + echo que
 * necesita un archivo. Igual que JsonResponse, se construye con un método
 * estático y se emite con send(), que corta la ejecución.
 *
 * La generación del contenido (el PDF o el CSV) NO vive aquí: eso le
 * corresponde a App\Support (ListadoPdf, ListadoCsv, ...). Esta clase solo
 * transporta los bytes.
 *
 * @author Logística
 * @package App\Http
 */
final class BinaryResponse
{
    private function __construct(
        private readonly string $content,
        private readonly string $contentType,
        private readonly string $filename,
        private readonly bool $inline
    ) {}

    /**
     * Envía un PDF ya generado.
     *
     * @param bool $inline true para abrirlo en el visor del navegador;
     *                     false para forzar la descarga.
     */
    public static function pdf(string $content, string $filename, bool $inline = false): self
    {
        return new self($content, 'application/pdf', $filename, $inline);
    }

    /**
     * Envía un archivo de cualquier tipo MIME.
     */
    public static function file(
        string $content,
        string $contentType,
        string $filename,
        bool $inline = false
    ): self {
        return new self($content, $contentType, $filename, $inline);
    }

    /**
     * Escribe los headers y el cuerpo, y termina la petición.
     */
    public function send(): never
    {
        /* Algunos visores embeben el PDF en un iframe; SAMEORIGIN y frame-ancestors 'self'
           permiten eso sin abrir el documento a sitios externos y evitan bloqueos
           por discrepancia de esquema (HTTP/HTTPS) tras proxies inversos (InfinityFree/Cloudflare). */
        header_remove('X-Frame-Options');
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $this->contentType);
        header(
            'Content-Disposition: ' . ($this->inline ? 'inline' : 'attachment')
            . '; filename="' . $this->filename . '"'
        );
        header('Content-Length: ' . strlen($this->content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $this->content;
        exit;
    }
}
