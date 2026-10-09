<?php
// download_foto.php
// Download semua foto untuk 1 kode_foto sebagai file ZIP

declare(strict_types=1);

$FOTO_FS_DIR = __DIR__ . '/bot/foto/';

if (!class_exists('ZipArchive')) {
  http_response_code(500);
  echo 'Fitur ZIP tidak tersedia di server (ZipArchive mati).';
  exit;
}

// ambil dan sanitasi kode_foto dari GET
$kodeRaw = isset($_GET['kode_foto']) ? (string)$_GET['kode_foto'] : '';
$kode    = preg_replace('~[^A-Za-z0-9_\-]~', '', $kodeRaw);

if ($kode === '') {
  http_response_code(400);
  echo 'Kode foto tidak valid.';
  exit;
}

$dir = rtrim($FOTO_FS_DIR, '/\\') . DIRECTORY_SEPARATOR . $kode . DIRECTORY_SEPARATOR;
if (!is_dir($dir)) {
  http_response_code(404);
  echo 'Folder foto untuk kode ini tidak ditemukan.';
  exit;
}

// kumpulkan semua file gambar
$files = [];
$patterns = ['*.jpg','*.jpeg','*.png','*.webp','*.gif','*.JPG','*.JPEG','*.PNG','*.WEBP','*.GIF'];
foreach ($patterns as $p) {
  foreach (glob($dir . $p) as $f) {
    if (is_file($f)) {
      $files[] = $f;
    }
  }
}

if (!$files) {
  http_response_code(404);
  echo 'Tidak ada file foto untuk kode ini.';
  exit;
}

// buat file ZIP sementara
$tmpZip = tempnam(sys_get_temp_dir(), 'efidi_foto_');
if ($tmpZip === false) {
  http_response_code(500);
  echo 'Gagal membuat file ZIP sementara.';
  exit;
}

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
  @unlink($tmpZip);
  http_response_code(500);
  echo 'Gagal membuka ZIP.';
  exit;
}

// masukkan semua foto ke dalam ZIP (nama file saja)
foreach ($files as $path) {
  $basename = basename($path);
  $zip->addFile($path, $basename);
}

$zip->close();

$downloadName = 'FOTO_' . $kode . '_' . date('Ymd_His') . '.zip';

// kirim ZIP ke browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($tmpZip);
@unlink($tmpZip);
exit;
