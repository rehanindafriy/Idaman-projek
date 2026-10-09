<?php
declare(strict_types=1);

// File: download_bai.php
// Download file BAI (PDF) dari bot/file/ sebagai attachment.

$BAI_FS_DIR = __DIR__ . '/bot/file/';

// --- AMBIL PARAMETER FILE ---
$filename = isset($_GET['f']) ? (string)$_GET['f'] : '';
$filename = basename($filename); // cegah path traversal

if ($filename === '') {
    http_response_code(400);
    echo 'Nama file tidak valid.';
    exit;
}

$srcPath = realpath($BAI_FS_DIR . $filename);
$baseDir = realpath($BAI_FS_DIR);

if ($srcPath === false || $baseDir === false || strpos($srcPath, $baseDir) !== 0 || !is_file($srcPath)) {
    http_response_code(404);
    echo 'File PDF tidak ditemukan.';
    exit;
}

// --- KIRIM SEBAGAI DOWNLOAD ---
$filesize = filesize($srcPath);

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($srcPath);
exit;
