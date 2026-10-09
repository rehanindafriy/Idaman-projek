<?php
// captcha.php — gambar angka sederhana berbasis session (hardened)
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$w = 130; $h = 44;
$img = imagecreatetruecolor($w, $h);

// warna
$bg     = imagecolorallocate($img, 235, 238, 243); // abu terang
$fg     = imagecolorallocate($img, 40, 40, 40);    // teks
$noise1 = imagecolorallocate($img, 180, 180, 180);
$noise2 = imagecolorallocate($img, 120, 120, 120);

imagefilledrectangle($img, 0, 0, $w, $h, $bg);

// kode 6 digit
$code = (string)random_int(100000, 999999);
$_SESSION['captcha_code'] = $code;
$_SESSION['captcha_ts']   = time(); // untuk TTL validasi

// noise garis
imagesetthickness($img, 2);
for ($i=0; $i<4; $i++){
  imageline($img, rand(0,$w-1), rand(0,$h-1), rand(0,$w-1), rand(0,$h-1), $noise1);
}
// noise titik
for ($i=0; $i<250; $i++){
  imagesetpixel($img, random_int(0,$w-1), random_int(0,$h-1), $noise2);
}

// tulis angka (tanpa TTF agar portable)
$font = 5; // built-in
$tw = imagefontwidth($font) * strlen($code);
$th = imagefontheight($font);
$x = (int)(($w - $tw)/2);
$y = (int)(($h - $th)/2);
imagestring($img, $font, $x, $y, $code, $fg);

// anti-cache
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

imagepng($img);
imagedestroy($img);
