<?php
/**
 * Generates PWA icon PNG on-the-fly using GD.
 * Usage: icon-192.png (via .htaccess rewrite) or ?size=192
 */
$size = 192;
if (isset($_GET['s'])) {
    $s = (int)$_GET['s'];
    if (in_array($s, [72, 96, 128, 144, 152, 192, 384, 512])) $size = $s;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

if (!function_exists('imagecreatetruecolor')) {
    // GD not available – serve SVG redirect
    header('Location: icon.svg');
    exit;
}

$img = imagecreatetruecolor($size, $size);
imagealphablending($img, true);
imagesavealpha($img, true);

// Colors
$bg1    = imagecolorallocate($img, 11,   7,  16);  // #0b0710
$bg2    = imagecolorallocate($img, 26,  10,  46);  // #1a0a2e
$pink   = imagecolorallocate($img, 232,  50, 154);  // #e8329a  (hot pink)
$pinkLt = imagecolorallocate($img, 255, 133, 200);  // #ff85c8  (light pink)
$purple = imagecolorallocate($img, 123,  44, 191);  // #7b2cbf
$white  = imagecolorallocate($img,  255,255, 255);
$gray   = imagecolorallocate($img,  180, 168, 201);

// Background gradient (top-left dark, bottom-right purple)
for ($y = 0; $y < $size; $y++) {
    $ratio = $y / $size;
    $r = (int)(11  + ($ratio * 15));
    $g = (int)(7   + ($ratio *  3));
    $b = (int)(16  + ($ratio * 30));
    $c = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $size - 1, $y, $c);
}

$sc = $size / 192; // scale factor

// Draw "ELITE" text (centered)
$font   = ($size >= 192) ? 5 : 4;
$textW  = imagefontwidth($font) * strlen('ELITE');
$textX  = (int)(($size - $textW) / 2);
$textY  = (int)($size * 0.30);
imagestring($img, $font, $textX, $textY, 'ELITE', $pinkLt);

// Separator line
$lineY = $textY + imagefontheight($font) + (int)(4 * $sc);
imageline($img, (int)(16 * $sc), $lineY, $size - (int)(16 * $sc), $lineY, $pink);

// Draw "THAI" text (centered)
$font2  = ($size >= 192) ? 5 : 4;
$textW2 = imagefontwidth($font2) * strlen('THAI');
$textX2 = (int)(($size - $textW2) / 2);
$textY2 = $lineY + (int)(10 * $sc);
imagestring($img, $font2, $textX2, $textY2, 'THAI', $white);

// Separator line
$lineY2 = $textY2 + imagefontheight($font2) + (int)(4 * $sc);
imageline($img, (int)(16 * $sc), $lineY2, $size - (int)(16 * $sc), $lineY2, $purple);

// Draw "GIRLS" text (centered)
$font3  = ($size >= 192) ? 4 : 3;
$textW3 = imagefontwidth($font3) * strlen('GIRLS');
$textX3 = (int)(($size - $textW3) / 2);
$textY3 = $lineY2 + (int)(10 * $sc);
imagestring($img, $font3, $textX3, $textY3, 'GIRLS', $gray);

imagepng($img);
imagedestroy($img);
