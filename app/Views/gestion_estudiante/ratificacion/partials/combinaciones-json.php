<?php
declare(strict_types=1);

/**
 * Partial: ratificacion/partials/combinaciones-json.php
 * Combinaciones del año activo para los selects del modal. Es un bloque de
 * datos, no JavaScript ejecutable (excepción del harness).
 *
 * @var array $combinaciones
 */
?>
<script type="application/json" id="ratificarCombinaciones"><?= json_encode(
    $combinaciones ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?></script>
