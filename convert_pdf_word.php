<?php
declare(strict_types=1);

// File: convert_pdf_word.php
// Konversi PDF -> DOCX: tiap halaman PDF dijadikan gambar (JPEG)
// lalu dimasukkan ke Word, dengan background putih (bukan hitam).

// === CONFIG PATH PDF ===
$BAI_FS_DIR = __DIR__ . '/bot/file/';

// === AMBIL PARAMETER FILE ===
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

// ==== CEK IMAGICK ====
if (!extension_loaded('imagick')) {
    http_response_code(500);
    echo 'Extension Imagick belum aktif di server.';
    exit;
}

// === LOAD LIBRARY COMPOSER ===
// composer require phpoffice/phpword
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// === RENDER PDF KE OBJEK IMAGICK ===
try {
    $imagick = new Imagick();
    $imagick->setResolution(200, 200);      // kualitas gambar
    $imagick->readImage($srcPath);         // baca semua halaman
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Gagal membaca PDF via Imagick: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

// === BUAT DOKUMEN WORD ===
$phpWord = new PhpWord();
$section = $phpWord->addSection([
    'marginLeft'   => 800,
    'marginRight'  => 800,
    'marginTop'    => 800,
    'marginBottom' => 800,
]);

$tmpFiles  = [];
$pageCount = $imagick->getNumberImages();
$index     = 0;

foreach ($imagick as $page) {
    // --- FLATTEN KE BACKGROUND PUTIH ---
    $page->setImageColorspace(Imagick::COLORSPACE_SRGB);

    $w = $page->getImageWidth();
    $h = $page->getImageHeight();

    // buat kanvas putih
    $canvas = new Imagick();
    $canvas->newImage($w, $h, 'white');
    $canvas->setImageColorspace(Imagick::COLORSPACE_SRGB);

    // tempel halaman PDF di atas kanvas putih
    $canvas->compositeImage($page, Imagick::COMPOSITE_DEFAULT, 0, 0);

    // simpan sebagai JPEG
    $canvas->setImageFormat('jpeg');

    $tmpFile = tempnam(sys_get_temp_dir(), 'pdf2img_') . '.jpg';
    $canvas->writeImage($tmpFile);
    $tmpFiles[] = $tmpFile;

    $canvas->clear();
    $canvas->destroy();

    // masukkan ke Word (lebar kira-kira satu halaman A4)
    $section->addImage($tmpFile, [
        'width'     => 600, // bisa disesuaikan
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
    ]);

    // page break kecuali halaman terakhir
    if ($index < $pageCount - 1) {
        $section->addPageBreak();
    }

    $index++;
}

// bersihkan Imagick utama
$imagick->clear();
$imagick->destroy();

// Nama output DOCX
$outName = preg_replace('/\.pdf$/i', '', $filename) . '.docx';

// === KIRIM KE BROWSER ===
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $outName . '"');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');

// hapus gambar sementara
foreach ($tmpFiles as $tmp) {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

exit;
