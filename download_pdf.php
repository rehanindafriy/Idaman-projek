<?php
// ================= DEBUG (boleh dimatikan nanti) =================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// =================================================================

require_once __DIR__ . '/koneksi.php';

$idOrder = isset($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
if ($idOrder <= 0) {
  die('ID order tidak valid.');
}

/**
 * CARI FILE BAI BERDASARKAN id_order
 * diasumsikan:
 *   - tabel orders punya kolom bai_id
 *   - tabel bai punya kolom nama_bai (nama file PDF di folder bot/file/)
 */
$namaBai = null;

try {
  $sql = "
    SELECT b.nama_bai
    FROM orders o
    LEFT JOIN bai b ON b.id_bai = o.bai_id
    WHERE o.id_order = :id
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':id' => $idOrder]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if ($row && !empty($row['nama_bai'])) {
    $namaBai = $row['nama_bai'];
  }
} catch (Throwable $e) {
  // kalau mau, bisa di-log ke file
  // file_put_contents(__DIR__.'/runtime-bai.log', date('c').' '.$e->getMessage()."\n", FILE_APPEND);
}

/**
 * JIKA ADA FILE BAI, BUKA LANGSUNG TANPA JAVASCRIPT
 * contoh: /bot/file/DGPS251118171928746029218_AOi425111805192887719c6e0_16880698_ba.pdf
 */
if ($namaBai) {
  // path di filesystem
  $fileFs  = __DIR__ . '/bot/file/' . $namaBai;
  // path untuk diakses browser
  $fileWeb = '/bot/file/' . rawurlencode($namaBai);

  if (file_exists($fileFs)) {
    // Cara 1: redirect ke file (browser akan buka viewer PDF-nya sendiri)
    header('Location: ' . $fileWeb);
    exit;

    // ---- Kalau kamu lebih suka langsung stream sebagai PDF, pakai blok ini,
    // ---- dan hapus/komentari header('Location...') di atas:
    /*
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($namaBai) . '"');
    header('Content-Length: ' . filesize($fileFs));
    readfile($fileFs);
    exit;
    */
  }
}

// =================================================================
// JIKA TIDAK ADA BAI / FILE BAI TIDAK KETEMU → LANJUTKAN KE DOMPDF
// =================================================================

/**
 * 1) Coba autoload dari HOME (hasil composer require di SSH)
 *    -> /home/u408986314/vendor/autoload.php
 * 2) Kalau tidak ada, baru coba beberapa kemungkinan lain
 */
$autoloadCandidates = [];

// >> SESUAIKAN USERNAME DENGAN punyamu (dari error path / SSH)
$autoloadCandidates[] = '/home/u408986314/vendor/autoload.php';

// fallback lain (kalau nanti kamu pindah composer ke public_html)
$autoloadCandidates[] = __DIR__ . '/vendor/autoload.php';
$autoloadCandidates[] = dirname(__DIR__) . '/vendor/autoload.php';
$autoloadCandidates[] = dirname(__DIR__, 2) . '/vendor/autoload.php';
$autoloadCandidates[] = dirname(__DIR__, 3) . '/vendor/autoload.php';

$autoloadPath = null;
foreach ($autoloadCandidates as $path) {
  if (file_exists($path)) {
    $autoloadPath = $path;
    break;
  }
}

if (!$autoloadPath) {
  die('Autoload Composer (vendor/autoload.php) tidak ditemukan di lokasi yang dicek.');
}

require_once $autoloadPath;

// Cek lagi: benar nggak Dompdf ada di autoload ini
if (!class_exists('Dompdf\\Dompdf')) {
  die('Dompdf belum ter-register di autoload: ' . htmlspecialchars($autoloadPath));
}

use Dompdf\Dompdf;
use Dompdf\Options;

// ====== AMBIL HTML DARI detail_print.php (layout sama persis) ======
ob_start();

// paksa mode print
$_GET['id_order'] = $idOrder;
$_GET['mode']     = 'print';

// GANTI nama file ini kalau file print kamu beda
include __DIR__ . '/detail_print.php';

$html = ob_get_clean();

// ====== HAPUS TOOLBAR CETAK & SCRIPT JS ======
$html = preg_replace('~<div class="print-toolbar".*?</div>~is', '', $html);
$html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);

// ====== PERBAIKI SRC GAMBAR RELATIF ======
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// contoh: src="images/..." -> src="https://efidi.montaklo.id/images/..."
$html = str_replace('src="images/', 'src="' . $baseUrl . '/images/', $html);

// ====== KONFIGURASI DOMPDF ======
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// KERTAS A4 PORTRAIT
$dompdf->setPaper('A4', 'portrait');

// RENDER
$dompdf->render();

// NAMA FILE DOWNLOAD
$fileName = 'Detail-Order-' . $idOrder . '.pdf';

// KIRIM KE BROWSER
$dompdf->stream($fileName, [
  'Attachment' => true,  // true -> download
]);

exit;
