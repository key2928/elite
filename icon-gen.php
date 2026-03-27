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
$pink   = imagecolorallocate($img, 232,  50, 154);  // #e8329a  (hot pink glove)
$pinkLt = imagecolorallocate($img, 255, 133, 200);  // #ff85c8  (light pink highlight)
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

// Draw single centered glove (main fist block)
$gx = (int)(46  * $sc);
$gy = (int)(38  * $sc);
$gw = (int)(100 * $sc);
$gh = (int)(82  * $sc);
$gr = (int)(28  * $sc);
roundedRect($img, $gx, $gy, $gw, $gh, $gr, $pink);

// Knuckle highlight
$kw = (int)(84 * $sc); $kh = (int)(28 * $sc); $kr = (int)(14 * $sc);
$kc = imagecolorallocatealpha($img, 255, 133, 200, 55);
roundedRect($img, $gx + (int)(8*$sc), $gy + (int)(5*$sc), $kw, $kh, $kr, $kc);

// Thumb
$tc = imagecolorallocatealpha($img, 232, 50, 154, 20);
imagefilledellipse($img, $gx - (int)(6*$sc), $gy + (int)(22*$sc), (int)(36*$sc), (int)(26*$sc), $pink);
imagefilledellipse($img, $gx - (int)(6*$sc), $gy + (int)(22*$sc), (int)(24*$sc), (int)(17*$sc), $pinkLt);

// Cuff / wrist band
$cx = (int)(62  * $sc);
$cy = (int)(114 * $sc);
$cw = (int)(68  * $sc);
$ch = (int)(40  * $sc);
$cr = (int)(11  * $sc);
roundedRect($img, $cx, $cy, $cw, $ch, $cr, $purple);
// Cuff stripe
$stripe = imagecolorallocatealpha($img, 255, 255, 255, 90);
imagefilledrectangle($img, $cx, $cy + (int)(10*$sc), $cx + $cw, $cy + (int)(20*$sc), $stripe);

// Text "ELITE THAI"
$font = 4;
$textW = imagefontwidth($font) * strlen('ELITE');
$textX = (int)(($size - $textW) / 2);
imagestring($img, $font, $textX, (int)(158 * $sc), 'ELITE', $pinkLt);

// Text "GIRLS"
$font2 = 3;
$txt2 = 'GIRLS';
$tw2  = imagefontwidth($font2) * strlen($txt2);
$tx2  = (int)(($size - $tw2) / 2);
imagestring($img, $font2, $tx2, (int)(174 * $sc), $txt2, $white);

imagepng($img);
imagedestroy($img);
