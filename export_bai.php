<?php
/**
 * export_bai.php
 * Kompres SELURUH isi folder bot/file/ (rekursif) menjadi ZIP dan kirim ke browser.
 */
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// Lokasi folder sumber
$base = realpath(__DIR__ . '/bot/file');
if ($base === false || !is_dir($base)) {
    http_response_code(404);
    exit('Folder bot/file/ tidak ditemukan.');
}

// Pastikan ekstensi ZipArchive tersedia
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('Ekstensi ZipArchive tidak tersedia di server.');
}

// Siapkan zip sementara
@ini_set('memory_limit', '1024M');
@set_time_limit(0);

$tmp  = tempnam(sys_get_temp_dir(), 'bai_');
$zipPath = $tmp . '.zip';
@unlink($tmp);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Gagal membuat file ZIP.');
}

// Masukkan semua file (rekursif)
$flags = FilesystemIterator::SKIP_DOTS;
$iter  = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, $flags),
    RecursiveIteratorIterator::SELF_FIRST
);

$added = 0;
foreach ($iter as $fileInfo) {
    if ($fileInfo->isFile()) {
        $abs = $fileInfo->getRealPath();
        if ($abs === false) continue;
        // Path relatif di dalam ZIP
        $rel = substr($abs, strlen($base) + 1);
        // Normalisasi separator
        $rel = str_replace('\\', '/', $rel);
        if ($zip->addFile($abs, $rel)) $added++;
    }
}

$zip->close();

if ($added === 0) {
    @unlink($zipPath);
    http_response_code(204); // no content
    exit('Tidak ada file di bot/file/.');
}

// Kirim ke browser
$filename = 'bai_files_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: ' . filesize($zipPath));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
readfile($zipPath);
@unlink($zipPath);
exit;
