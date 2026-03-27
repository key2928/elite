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
$pink   = imagecolorallocate($img, 214,  43, 197);  // #d62bc5
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

// Rounded rect helper
function roundedRect($img, $x, $y, $w, $h, $r, $color) {
    imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
    imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
    imagefilledellipse($img, $x + $r,     $y + $r,     $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x + $w - $r, $y + $r,    $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x + $r,     $y + $h - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x + $w - $r, $y + $h - $r,$r * 2, $r * 2, $color);
}

$sc = $size / 192; // scale factor

// Draw gloves
$gx1 = (int)(35  * $sc); $gy = (int)(55  * $sc);
$gx2 = (int)(110 * $sc);
$gw  = (int)(60  * $sc);
$gh  = (int)(75  * $sc);
$gr  = (int)(25  * $sc);
roundedRect($img, $gx1, $gy, $gw, $gh, $gr, $pink);
roundedRect($img, $gx2, $gy, $gw, $gh, $gr, $purple);

// Knuckle highlight
$kw = (int)(50 * $sc); $kh = (int)(18 * $sc); $kr = (int)(9 * $sc);
$kc = imagecolorallocatealpha($img, 255, 255, 255, 70);
roundedRect($img, $gx1 + (int)(5*$sc), $gy + (int)(5*$sc), $kw, $kh, $kr, $kc);
roundedRect($img, $gx2 + (int)(5*$sc), $gy + (int)(5*$sc), $kw, $kh, $kr, $kc);

// Text "ET"
$font = 5;
$textW = imagefontwidth($font) * 2;
$textX = (int)(($size - $textW) / 2);
imagestring($img, $font, $textX, (int)(145 * $sc), 'ET', $pink);

// Text "GIRLS"
$font2 = 3;
$txt2 = 'GIRLS';
$tw2  = imagefontwidth($font2) * strlen($txt2);
$tx2  = (int)(($size - $tw2) / 2);
imagestring($img, $font2, $tx2, (int)(162 * $sc), $txt2, $white);

imagepng($img);
imagedestroy($img);
