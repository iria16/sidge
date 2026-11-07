<?php

declare(strict_types=1);

/**
 * Script generador de la Franja Tricolor Oficial en Vector (SVG) y Raster (PNG).
 * 
 * Medida fielmente a partir de la constancia física oficial:
 * - Curva de onda venezolana (Amarillo superior ondeante, Azul central con 8 estrellas blancas, Rojo inferior).
 * - Dimensiones objetivo: 1800 × 200 px (300 DPI aprox. a ancho completo carta).
 * - Peso objetivo: Menos de 150 KB.
 */

$ancho = 1800;
$alto  = 200;

// 1. GENERACIÓN DEL SVG ESCRITO A MANO
$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1800 200" width="1800" height="200">
  <defs>
    <g id="estrella">
      <polygon points="0,-7 2.1,-2.2 7.3,-2.2 3.1,1.1 4.7,6.1 0,3 
                      -4.7,6.1 -3.1,1.1 -7.3,-2.2 -2.1,-2.2" fill="#FFFFFF"/>
    </g>
  </defs>

  <!-- Banda Roja Inferior Derecha -->
  <path d="M 1120 192
           C 1300 186, 1550 178, 1800 170
           L 1800 200
           L 1180 200
           Z" fill="#BD1622"/>

  <!-- Banda Azul Marina Central con Onda -->
  <path d="M 680 182
           C 950 176, 1380 152, 1800 115
           L 1800 170
           C 1550 178, 1300 186, 1120 192
           C 900 198, 750 195, 680 182
           Z" fill="#0A3182"/>

  <!-- 8 Estrellas Blancas en Arco sobre la Banda Azul -->
  <g transform="translate(1180, 168)"><use href="#estrella"/></g>
  <g transform="translate(1260, 161)"><use href="#estrella"/></g>
  <g transform="translate(1340, 154)"><use href="#estrella"/></g>
  <g transform="translate(1420, 147)"><use href="#estrella"/></g>
  <g transform="translate(1500, 141)"><use href="#estrella"/></g>
  <g transform="translate(1580, 135)"><use href="#estrella"/></g>
  <g transform="translate(1660, 129)"><use href="#estrella"/></g>
  <g transform="translate(1740, 124)"><use href="#estrella"/></g>

  <!-- Banda Amarilla Superior Ondeante (Cresta y Onda hacia la derecha) -->
  <path d="M 0 125
           C 250 155, 480 176, 700 182
           C 1050 176, 1450 148, 1800 102
           L 1800 115
           C 1380 152, 950 176, 680 182
           C 480 185, 250 175, 0 160
           Z" fill="#F5B800"/>

  <!-- Relleno Amarillo del Extremo Izquierdo (Bloque inferior izquierdo de la onda) -->
  <path d="M 0 125
           L 0 195
           C 150 195, 350 192, 520 186
           C 350 180, 180 170, 0 160
           Z" fill="#F5B800"/>
</svg>
SVG;

$svgRuta = __DIR__ . '/../public/assets/img/franja_tricolor.svg';
file_put_contents($svgRuta, $svg);
echo "[OK] SVG escrito a mano guardado en: public/assets/img/franja_tricolor.svg\n";

// 2. RENDERIZADO RASTER DIRECTO A PNG CON GD (1800 × 200 px)
$img = imagecreatetruecolor($ancho, $alto);
imagealphablending($img, false);
imagesavealpha($img, true);

// Fondo completamente transparente
$transparente = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefilledrectangle($img, 0, 0, $ancho, $alto, $transparente);
imagealphablending($img, true);

// Colores medidos
$colorAmarillo = imagecolorallocate($img, 245, 184, 0);   // #F5B800
$colorAzul     = imagecolorallocate($img, 10, 49, 130);    // #0A3182
$colorRojo     = imagecolorallocate($img, 189, 22, 34);    // #BD1622
$colorBlanco   = imagecolorallocate($img, 255, 255, 255); // #FFFFFF

// Funciones para calcular curvas de Bézier cúbicas
function bezier(float $p0, float $p1, float $p2, float $p3, float $t): float {
    $it = 1.0 - $t;
    return ($it * $it * $it * $p0) + (3 * $it * $it * $t * $p1) + (3 * $it * $t * $t * $p2) + ($t * $t * $t * $p3);
}

// 2.A Dibujar Banda Roja
$ptsRojo = [];
for ($i = 0; $i <= 100; $i++) {
    $t = $i / 100.0;
    $x = bezier(1120, 1300, 1550, 1800, $t);
    $y = bezier(192, 186, 178, 170, $t);
    $ptsRojo[] = (int) round($x);
    $ptsRojo[] = (int) round($y);
}
$ptsRojo[] = 1800; $ptsRojo[] = 200;
$ptsRojo[] = 1180; $ptsRojo[] = 200;
imagefilledpolygon($img, $ptsRojo, $colorRojo);

// 2.B Dibujar Banda Azul
$ptsAzul = [];
// Borde superior de azul
for ($i = 0; $i <= 100; $i++) {
    $t = $i / 100.0;
    $x = bezier(680, 950, 1380, 1800, $t);
    $y = bezier(182, 176, 152, 115, $t);
    $ptsAzul[] = (int) round($x);
    $ptsAzul[] = (int) round($y);
}
$ptsAzul[] = 1800; $ptsAzul[] = 170;
// Borde inferior de azul hacia la izquierda
for ($i = 100; $i >= 0; $i--) {
    $t = $i / 100.0;
    $x = bezier(1120, 1300, 1550, 1800, $t);
    $y = bezier(192, 186, 178, 170, $t);
    $ptsAzul[] = (int) round($x);
    $ptsAzul[] = (int) round($y);
}
for ($i = 100; $i >= 0; $i--) {
    $t = $i / 100.0;
    $x = bezier(680, 750, 900, 1120, $t);
    $y = bezier(182, 195, 198, 192, $t);
    $ptsAzul[] = (int) round($x);
    $ptsAzul[] = (int) round($y);
}
imagefilledpolygon($img, $ptsAzul, $colorAzul);

// 2.C Dibujar 8 Estrellas Blancas en GD
function dibujarEstrella($img, float $cx, float $cy, float $rExt, float $rInt, int $color): void {
    $puntos = [];
    $angBase = -M_PI / 2;
    for ($i = 0; $i < 10; $i++) {
        $r = ($i % 2 === 0) ? $rExt : $rInt;
        $ang = $angBase + ($i * M_PI / 5);
        $puntos[] = (int) round($cx + $r * cos($ang));
        $puntos[] = (int) round($cy + $r * sin($ang));
    }
    imagefilledpolygon($img, $puntos, $color);
}

$estrellasPos = [
    [1180, 168], [1260, 161], [1340, 154], [1420, 147],
    [1500, 141], [1580, 135], [1660, 129], [1740, 124]
];
foreach ($estrellasPos as [$ex, $ey]) {
    dibujarEstrella($img, (float)$ex, (float)$ey, 7.5, 3.2, $colorBlanco);
}

// 2.D Dibujar Banda Amarilla
$ptsAmarillo = [];
// Borde superior
for ($i = 0; $i <= 100; $i++) {
    $t = $i / 100.0;
    $x = bezier(0, 250, 480, 700, $t);
    $y = bezier(125, 155, 176, 182, $t);
    $ptsAmarillo[] = (int) round($x);
    $ptsAmarillo[] = (int) round($y);
}
for ($i = 0; $i <= 100; $i++) {
    $t = $i / 100.0;
    $x = bezier(700, 1050, 1450, 1800, $t);
    $y = bezier(182, 176, 148, 102, $t);
    $ptsAmarillo[] = (int) round($x);
    $ptsAmarillo[] = (int) round($y);
}
$ptsAmarillo[] = 1800; $ptsAmarillo[] = 115;
// Borde inferior amarillo
for ($i = 100; $i >= 0; $i--) {
    $t = $i / 100.0;
    $x = bezier(680, 950, 1380, 1800, $t);
    $y = bezier(182, 176, 152, 115, $t);
    $ptsAmarillo[] = (int) round($x);
    $ptsAmarillo[] = (int) round($y);
}
for ($i = 100; $i >= 0; $i--) {
    $t = $i / 100.0;
    $x = bezier(0, 250, 480, 680, $t);
    $y = bezier(160, 175, 185, 182, $t);
    $ptsAmarillo[] = (int) round($x);
    $ptsAmarillo[] = (int) round($y);
}
// Relleno bloque izquierdo
$ptsAmarillo[] = 0; $ptsAmarillo[] = 195;
$ptsAmarillo[] = 520; $ptsAmarillo[] = 186;
$ptsAmarillo[] = 0; $ptsAmarillo[] = 160;

imagefilledpolygon($img, $ptsAmarillo, $colorAmarillo);

// Guardar PNG a 1800 px
$pngRuta = __DIR__ . '/../public/assets/img/franja_tricolor.png';
imagepng($img, $pngRuta, 8);
imagedestroy($img);

$tamBytes = filesize($pngRuta);
$tamKb = round($tamBytes / 1024, 2);
echo "[OK] PNG de 1800 px generado en: public/assets/img/franja_tricolor.png ({$tamKb} KB)\n";

if ($tamBytes < 150 * 1024) {
    echo "  [PASS] Cumple objetivo: menor a 150 KB ({$tamKb} KB < 150 KB)\n";
} else {
    echo "  [FAIL] Supera 150 KB ({$tamKb} KB)\n";
}
