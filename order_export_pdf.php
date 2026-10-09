<?php
declare(strict_types=1);

require_once __DIR__ . '/koneksi.php';

// composer autoload untuk Dompdf
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// === FLAG: kita lagi ekspor PDF, bukan preview biasa ===
if (!defined('EFIDI_EXPORT_PDF')) {
  define('EFIDI_EXPORT_PDF', true);
}

// ambil id_order dari GET
$idOrder = isset($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
if ($idOrder <= 0) {
  http_response_code(400);
  echo 'ID order tidak valid.';
  exit;
}

/**
 * 1) TANGKAP HTML DARI order_export_print.php (MODE=modal)
 *    supaya layout-nya sama persis, tapi font & gambar
 *    akan dibesarkan/didataURL karena EFIDI_EXPORT_PDF = true
 */
$_GET['id_order'] = $idOrder;
$_GET['mode']     = 'modal';   // tetap pakai mode modal (tanpa window.print di browser)

ob_start();
include __DIR__ . '/order_export_print.php';
$html = ob_get_clean();

if ($html === '' || $html === false) {
  http_response_code(500);
  echo 'Gagal membuat konten HTML untuk PDF.';
  exit;
}

/**
 * 2) KONFIG DOMPDF
 */
$options = new Options();
$options->set('isRemoteEnabled', true);      // boleh load resource remote
$options->set('isHtml5ParserEnabled', true); // parser HTML5
// kalau mau batasi akses file lokal, bisa pakai chroot:
// $options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);

// load HTML
$dompdf->loadHtml($html, 'UTF-8');

// ukuran kertas A4 portrait
$dompdf->setPaper('A4', 'portrait');

// render
$dompdf->render();

/**
 * 3) KIRIM KE BROWSER SEBAGAI DOWNLOAD PDF
 */
$filename = 'order-' . $idOrder . '.pdf';

// Attachment => true → paksa download
$dompdf->stream($filename, [
  'Attachment' => true
]);

exit;
