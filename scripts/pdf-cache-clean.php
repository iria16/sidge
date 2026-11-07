<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Support\PdfCache;

echo PdfCache::clean() . " archivos de PDF cache limpiados\n";
