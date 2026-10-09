<?php
// download_photos.php
declare(strict_types=1);

// ==== Ambil parameter ====
$sc = isset($_GET['sc']) ? trim((string)$_GET['sc']) : '';
if ($sc === '') {
  http_response_code(400);
  exit('Parameter sc kosong.');
}

// ==== Base folder foto ====
$base = realpath(__DIR__ . '/bot/foto');
if ($base === false) {
  http_response_code(500);
  exit('Base folder foto tidak ditemukan.');
}
$base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// ==== Pastikan folder target valid ====
$dir = realpath($base . $sc);
if ($dir === false || strncmp($dir, $base, strlen($base)) !== 0 || !is_dir($dir)) {
  http_response_code(404);
  exit('Folder foto tidak ditemukan.');
}

// ==== Kumpulkan file gambar ====
$allow = ['jpg','jpeg','png','webp','gif'];
$entries = [];
foreach (scandir($dir, SCANDIR_SORT_NONE) ?: [] as $name) {
  if ($name === '.' || $name === '..') continue;
  $path = $dir . DIRECTORY_SEPARATOR . $name;
  if (!is_file($path)) continue;
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $allow, true)) continue;
  $entries[$name] = $path;
}
if (!$entries) {
  http_response_code(404);
  exit('Tidak ada foto di folder tersebut.');
}

// Urut alami: 1,2,10 -> 10 di belakang (bukan di tengah)
uksort($entries, 'strnatcasecmp');
$files = array_values($entries);

// ==== Buat ZIP sementara ====
if (!class_exists('ZipArchive')) {
  http_response_code(500);
  exit('Ekstensi ZipArchive belum aktif di PHP.');
}

$zipName = 'FOTO_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $sc) . '.zip';
$tmpZip  = tempnam(sys_get_temp_dir(), 'zip');

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
  http_response_code(500);
  exit('Gagal membuat berkas ZIP.');
}

// Masukkan file, dinamai 01.jpg, 02.jpg, ...
$idx = 1;
foreach ($files as $path) {
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $nameInZip = sprintf('%02d.%s', $idx++, $ext);
  $zip->addFile($path, $nameInZip);
}
$zip->close();

// ==== Kirim ke browser ====
header('Content-Type: application/zip');
header('Content-Length: ' . filesize($tmpZip));
header('Content-Disposition: attachment; filename="' . $zipName . '"');
readfile($tmpZip);
@unlink($tmpZip);
