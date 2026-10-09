<?php
declare(strict_types=1);

require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

// Konversi gambar lokal ke Base64 (solusi jitu agar logo muncul di dompdf)
if (!function_exists('es_img_b64')) {
  function es_img_b64(string $path): string {
    $realPath = __DIR__ . '/' . ltrim($path, '/');
    if (!file_exists($realPath)) {
      return $path; // fallback jika tidak ada
    }
    $mime = mime_content_type($realPath) ?: 'image/png';
    $data = base64_encode(file_get_contents($realPath));
    return 'data:' . $mime . ';base64,' . $data;
  }
}

/**
 * Apakah ini dipanggil dari order_export_pdf.php ?
 * Kalau YA → EFIDI_EXPORT_PDF didefinisikan di sana.
 */
$isPdfExport = defined('EFIDI_EXPORT_PDF') && EFIDI_EXPORT_PDF === true;

$idOrder = isset($_GET['id_order']) ? (int)$_GET['id_order'] : 0;

// mode: preview/modal (default) atau print
$modeParam = $_GET['mode'] ?? 'modal';
$modeParam = strtolower((string)$modeParam);
// Apapun selain 'print' dianggap mode preview/modal
$mode = ($modeParam === 'print') ? 'print' : 'modal';

if ($idOrder <= 0) {
  die('ID order tidak valid.');
}

/**
 * Ambil 1 record orders + bai + foto utama
 */
function getOrderDetail(PDO $pdo, int $idOrder): ?array {
  $sql = "
    SELECT
      o.*,
      b.id_bai,
      b.kode_bai,
      b.nama_bai,
      b.uploader      AS bai_uploader,
      b.date_received AS bai_date,
      f.id_foto,
      f.kode_foto,
      f.uploader      AS foto_uploader,
      f.date_received AS foto_date
    FROM orders o
    LEFT JOIN bai  b ON b.id_bai  = o.bai_id
    LEFT JOIN foto f ON f.id_foto = o.foto_id
    WHERE o.id_order = :id
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':id' => $idOrder]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/** Ambil detail order */
$order = getOrderDetail($pdo, $idOrder);
if (!$order) {
  die('Data order tidak ditemukan.');
}

/**
 * Fallback: kalau BAI/FOTO belum terisi dari join, cari pakai no_sc
 */
$noSc = trim((string)($order['no_sc'] ?? ''));

if (
  (!isset($order['id_bai']) || !$order['id_bai']) &&
  $noSc !== ''
) {
  $sqlBai = "SELECT * FROM bai WHERE kode_bai = :no_sc ORDER BY id_bai DESC LIMIT 1";
  $stBai  = $pdo->prepare($sqlBai);
  $stBai->execute([':no_sc' => $noSc]);
  if ($brow = $stBai->fetch(PDO::FETCH_ASSOC)) {
    $order['id_bai']       = $brow['id_bai'];
    $order['kode_bai']     = $brow['kode_bai'];
    $order['nama_bai']     = $brow['nama_bai'];
    $order['bai_uploader'] = $brow['uploader']      ?? '-';
    $order['bai_date']     = $brow['date_received'] ?? '-';
  }
}

if (
  (!isset($order['id_foto']) || !$order['id_foto']) &&
  $noSc !== ''
) {
  $sqlFoto = "SELECT * FROM foto WHERE kode_foto = :no_sc ORDER BY id_foto DESC LIMIT 1";
  $stFoto  = $pdo->prepare($sqlFoto);
  $stFoto->execute([':no_sc' => $noSc]);

  if ($frow = $stFoto->fetch(PDO::FETCH_ASSOC)) {
    $order['id_foto']       = $frow['id_foto'];
    $order['kode_foto']     = $frow['kode_foto'];
    $order['foto_uploader'] = $frow['uploader']      ?? '-';
    $order['foto_date']     = $frow['date_received'] ?? '-';
  }
}

/** Format created_at ke Indonesia */
$createdAtFmt = '-';
if (!empty($order['created_at'])) {
  $ts = strtotime($order['created_at']);
  if ($ts) {
    $bulanIndo = [
      1 => 'Januari', 2 => 'Februari', 3 => 'Maret',    4 => 'April',
      5 => 'Mei',     6 => 'Juni',     7 => 'Juli',     8 => 'Agustus',
      9 => 'September',10 => 'Oktober',11 => 'November',12 => 'Desember'
    ];
    $d   = date('d', $ts);
    $m   = (int)date('n', $ts);
    $y   = date('Y', $ts);
    $jam = date('H.i', $ts);
    $namaBulan   = $bulanIndo[$m] ?? date('M', $ts);
    $createdAtFmt = "$d $namaBulan $y, $jam WIB";
  }
}

/**
 * Helper: ubah file gambar (jpg/png) → data:image/...;base64
 * Dipakai SAAT EXPORT PDF supaya Dompdf pasti bisa baca.
 */
function es_image_to_dataurl(string $path): string {
  if (!is_file($path)) return '';
  $info = @getimagesize($path);
  $mime = $info['mime'] ?? 'image/jpeg';
  $bin  = @file_get_contents($path);
  if ($bin === false) return '';
  return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

/**
 * Helper: ubah PDF (scan BAI) → array data:image/jpeg;base64,
 * Butuh ekstensi php-imagick + ghostscript.
 * Kalau tidak ada Imagick → return [] (tidak error fatal).
 */
function es_pdf_to_dataurl_images(string $pdfPath, int $targetWidth = 900): array {
  if (!class_exists('Imagick')) {
    return [];
  }

  try {
    $imagick = new Imagick();
    $imagick->setResolution(144, 144); // kualitas lumayan
    $imagick->readImage($pdfPath);

    $result = [];

    foreach ($imagick as $page) {
      $page->setImageFormat('jpeg');
      $page->setImageCompression(Imagick::COMPRESSION_JPEG);
      $page->setImageCompressionQuality(85);

      if ($targetWidth > 0 && $page->getImageWidth() > $targetWidth) {
        $page->thumbnailImage($targetWidth, 0);
      }

      $data = base64_encode($page->getImageBlob());
      $result[] = 'data:image/jpeg;base64,' . $data;
    }

    $imagick->clear();
    $imagick->destroy();

    return $result;
  } catch (Throwable $e) {
    return [];
  }
}

/**
 * Cari file BAI di bot/file dan foto di bot/foto/<NO_SC>/
 */
$BAI_FS_DIR    = __DIR__ . '/bot/file/';
$BAI_WEB_BASE  = 'bot/file/';

$FOTO_FS_DIR   = __DIR__ . '/bot/foto/';
$FOTO_WEB_BASE = 'bot/foto/';

$baiFiles  = [];
$fotoFiles = [];

/** helper tambah file BAI */
function es_add_bai_file(array &$baiFiles, string $fsDir, string $webBase, string $fileName): void {
  $fileName = trim($fileName);
  if ($fileName === '') return;

  $path = rtrim($fsDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
  if (!is_file($path)) return;

  $baiFiles[] = [
    'name' => $fileName,
    'path' => $path,
    'url'  => $webBase . rawurlencode($fileName),
  ];
}

/* ==== FILE BAI ==== */
$baiName = trim((string)($order['nama_bai'] ?? ''));
$kodeBai = trim((string)($order['kode_bai'] ?? ''));

// 1) Langsung dari nama_bai (kalau ada)
if ($baiName !== '') {
  es_add_bai_file($baiFiles, $BAI_FS_DIR, $BAI_WEB_BASE, $baiName);
}

// 2) Kalau belum ketemu, cari berdasarkan kode_bai
if (!$baiFiles && $kodeBai !== '') {
  foreach (glob($BAI_FS_DIR . '*' . $kodeBai . '*.*') ?: [] as $path) {
    if (is_file($path)) {
      $name = basename($path);
      $baiFiles[] = [
        'name' => $name,
        'path' => $path,
        'url'  => $BAI_WEB_BASE . rawurlencode($name),
      ];
    }
  }
}

// 3) Kalau masih belum, cari berdasarkan no_sc
if (!$baiFiles && $noSc !== '') {
  foreach (glob($BAI_FS_DIR . '*' . $noSc . '*.*') ?: [] as $path) {
    if (is_file($path)) {
      $name = basename($path);
      $baiFiles[] = [
        'name' => $name,
        'path' => $path,
        'url'  => $BAI_WEB_BASE . rawurlencode($name),
      ];
    }
  }
}

/* URL & PATH BAI pertama (khusus yang PDF saja, untuk preview/export) */
$firstBaiUrl  = '';
$firstBaiPath = '';
if ($baiFiles) {
  foreach ($baiFiles as $bf) {
    $ext = strtolower(pathinfo($bf['name'], PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
      $firstBaiUrl  = $bf['url'];
      $firstBaiPath = $bf['path'] ?? '';
      break;
    }
  }
}

/* ==== FOTO: bot/foto/NO_SC/*.jpg|jpeg|png ==== */
if ($noSc !== '') {
  $fotoDir = rtrim($FOTO_FS_DIR, '/\\') . DIRECTORY_SEPARATOR . $noSc;
  if (is_dir($fotoDir)) {
    $exts = ['jpg','jpeg','png','JPG','JPEG','PNG'];
    foreach ($exts as $ext) {
      foreach (glob($fotoDir . "/*." . $ext) ?: [] as $path) {
        if (is_file($path)) {
          $webUrl = $FOTO_WEB_BASE . rawurlencode($noSc) . '/' . rawurlencode(basename($path));

          // Untuk EXPORT PDF → pakai dataURL supaya pasti tampil
          $src = $webUrl;
          if ($isPdfExport) {
            $dataUrl = es_image_to_dataurl($path);
            if ($dataUrl !== '') {
              $src = $dataUrl;
            }
          }

          $fotoFiles[] = [
            'name' => basename($path),
            'src'  => $src,
          ];
        }
      }
    }
  }
}

/** Mapping field ke label (kiri/kanan) */
$leftFields = [
  'sto'              => 'STO',
  'mitra'            => 'Mitra',
  'no_sc'            => 'No SC',
  'no_internet'      => 'No Internet',
  'no_voip'          => 'No VoIP',
  'ncli'             => 'NCLI',
  'nama_pelanggan'   => 'Nama Pelanggan',
  'alamat'           => 'Alamat',
  'cp_pelanggan'     => 'CP Pelanggan',
  'odp_wo'           => 'ODP WO',
  'odp_real'         => 'ODP Real',
  'port'             => 'Port',
  'marking_dc_awal'  => 'Marking DC Awal',
  'marking_dc_akhir' => 'Marking DC Akhir',
  'ac_of_sm_1b'      => 'AC OF SM 1B',
  'ac_of_sm_1_3sl'   => 'AC OF SM 1 3SL',
  'clamp_hook'       => 'Clamp Hook',
];

$rightFields = [
  'order_type'    => 'Order Type',
  'soc_ils'       => 'SOC ILS',
  'soc_sum'       => 'SOC SUM',
  'qrcode'        => 'QR Code',
  'sn_ont_type'   => 'SN ONT Type',
  'stb_id_type'   => 'STB ID Type',
  'tag_odp'       => 'Tag ODP',
  'tag_pelanggan' => 'Tag Pelanggan',
  'labor'         => 'Labor',
  'valins_id'     => 'Valins ID',
  'slot'          => 'Slot',
  'portt'         => 'Portt',
  'ip_olt'        => 'IP OLT',
  'hasil_valins'  => 'Hasil Valins',
  'onu_gendong'   => 'ONU Gendong',
];

$legacyFields = [
  'no_inet_lama'  => 'No Internet Lama',
  'no_voip_lama'  => 'No VoIP Lama',
  'sn_ont_lama'   => 'SN ONT Lama',
  'stbid_lama'    => 'STB ID Lama',
];

$metaFields = [
  'sender_username' => 'Sender Username',
  'user_id'         => 'User ID',
  'bai_id'          => 'BAI ID',
  'foto_id'         => 'Foto ID',
];

/** Helper untuk tabel */
function renderPrintRows(array $fields, array $row): void {
  foreach ($fields as $name => $label) {
    $val = isset($row[$name]) ? (string)$row[$name] : '';
    if ($val === '') $val = '-';

    if ($name === 'alamat') {
      $valHtml = nl2br(e($val));
    } else {
      $valHtml = e($val);
    }

    echo "<tr>";
    echo "<th>" . e($label) . "</th>";
    echo "<td>" . $valHtml . "</td>";
    echo "</tr>";
  }
}

/** ukuran font berbeda antara preview & export PDF */
$fontBody      = $isPdfExport ? '10pt'  : '6.5pt';
$fontDetail    = $isPdfExport ? '8pt'   : '3.5pt';
$fontDetailTh  = $isPdfExport ? '9pt'   : '4.4pt';
$fontFileList  = $isPdfExport ? '8pt'   : '6.5pt';
$fontFotoCap   = $isPdfExport ? '7.5pt' : '5.3pt';
$fotoMaxHeight = $isPdfExport ? '140px' : '50px';

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Detail Order - <?= e($order['no_sc'] ?? '-') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

<style>
  /* --- PAKSA A4 & MARGIN KERTAS SAAT CETAK / SAVE AS PDF --- */
  @page {
    size: A4;
    margin: 10mm 10mm 10mm 10mm;
  }

  *{box-sizing:border-box;}
  body{
    font-family:"Segoe UI",system-ui,-apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif;
    font-size:<?= $fontBody ?>;
    color:#111827;
    margin:0;
    background:#f3f4f6;
  }
  h1,h2,h3,h4,h5{margin:0;padding:0;}

  .page-shell{
    padding:4px;
  }
  .paper-page{
    max-width:680px;
    margin:0 auto;
    background:#ffffff;
    border-radius:4px;
    border:1px solid #e5e7eb;
    box-shadow:0 5px 14px rgba(15,23,42,.16);
    padding:10px 12px 8px 12px;
  }

  /* HEADER SURAT */
  .letter-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:2px;
  }
  .letter-logo{
    width:60px;
    text-align:center;
  }
  .letter-logo img{
    max-width:45px;
    max-height:45px;
    object-fit:contain;
  }
  .letter-title-block{
    flex:1;
    text-align:center;
    padding:0 6px;
  }
  .company-name{
    font-size:8.5pt;
    font-weight:bold;
    text-transform:uppercase;
    letter-spacing:.07em;
  }
  .company-subname{
    font-size:7.2pt;
    margin-top:1px;
  }
  .company-address{
    font-size:6.2pt;
    margin-top:2px;
    line-height:1.25;
  }
  .letter-divider{
    border:none;
    border-top:2px solid #111827;
    margin:2px 0 4px 0;
  }
  .letter-divider-thin{
    border:none;
    border-top:1px solid #111827;
    margin-top:-4px;
    margin-bottom:6px;
  }

  .document-title{
    text-align:center;
    margin-bottom:3px;
  }
  .document-title-main{
    font-size:6.5pt;
    font-weight:bold;
    text-transform:uppercase;
    text-decoration:underline;
  }
  .document-title-sub{
    font-size:5pt;
    margin-top:1px;
  }

  .document-meta{
    margin-top:2px;
    margin-bottom:5px;
    font-size:3.8pt;
  }
  .document-meta-table{
    border-collapse:collapse;
    width:100%;
  }
  .document-meta-table td{
    padding:0;
    vertical-align:top;
  }
  .document-meta-label{width:26%;}
  .document-meta-sep{width:2%;}

  .section-title{
    margin-top:4px;
    margin-bottom:2px;
    font-weight:bold;
    text-transform:uppercase;
    font-size:4.9pt;
    color:#008a97;
    border-bottom:1px solid #e5e7eb;
    padding-bottom:1px;
  }

  table.detail{
    width:100%;
    border-collapse:collapse;
    margin-bottom:3px;
    font-size:<?= $fontDetail ?>;
    background:#ffffff;
  }
  table.detail th,
  table.detail td{
    border:1px solid #e5e7eb;
    padding:1.5px 2px;
    vertical-align:top;
  }
  table.detail th{
    width:30%;
    background:#f9fafb;
    text-align:left;
    font-weight:400;
    color:#374151;
    font-size:<?= $fontDetailTh ?>;
  }

  /* KHUSUS TABEL LAMPIRAN BAI */
  .lampiran-bai-table{
    table-layout: fixed;
    width:100%;
  }
  .lampiran-bai-table th,
  .lampiran-bai-table td{
    word-break: break-word;
    overflow-wrap: anywhere;
  }
  .lampiran-bai .file-list{
    font-size:<?= $fontFileList ?>;
  }
  .lampiran-bai .file-list-item{
    word-break: break-all;
    overflow-wrap: anywhere;
  }

  .two-col{
    display:flex;
    gap:4px;
    margin-top:2px;
  }
  .two-col>.col{
    flex:1;
  }

  .lampiran-box{
    border:1px solid #e5e7eb;
    border-radius:3px;
    padding:3px;
    margin-bottom:3px;
    background:#ffffff;
  }
  .file-list{
    font-size:<?= $fontFileList ?>;
  }
  .file-list-item{
    margin-bottom:1px;
  }

  .foto-grid{
    display:flex;
    flex-wrap:wrap;
    gap:3px;
    margin-top:2px;
  }
  .foto-item{
    width:calc(25% - 6px);
    border:1px solid #e5e7eb;
    padding:2px;
    border-radius:2px;
    page-break-inside:avoid;
    background:#ffffff;
  }
  .foto-item img{
    width:100%;
    height:auto;
    max-height:<?= $fotoMaxHeight ?>;
    object-fit:contain;
    display:block;
  }
  .foto-caption{
    font-size:<?= $fontFotoCap ?>;
    margin-top:1px;
    word-break:break-all;
  }

  /* ==== BAI preview (PDF -> gambar) ==== */
  .bai-preview{
    margin-top:6px;
  }
  .bai-page{
    margin-top:4px;
    border:1px solid #e5e7eb;
    border-radius:4px;
    overflow:hidden;
    background:#ffffff;
    max-width:650px;
    page-break-inside:avoid;
  }
  .bai-page-header{
    font-size:7.5pt;
    padding:2px 4px;
    background:#f3f4f6;
    border-bottom:1px solid #e5e7eb;
    color:#4b5563;
  }
  .bai-page img{
    width:100%;
    display:block;
  }

  .page-break{
    page-break-before:always;   /* Mulai Lampiran BAI di halaman baru */
    margin-top:8px;
  }

  .footer-print{
    margin-top:5px;
    font-size:6.5pt;
    color:#6b7280;
    border-top:1px dashed #d1d5db;
    padding-top:3px;
  }

  /* Preview di HP: scale saja untuk tampilan, tidak mengubah hasil print */
  @media (max-width: 768px){
    .page-shell{
      padding:0;
    }
    .paper-page{
      transform:scale(0.78);
      transform-origin:top center;
    }
  }

  @media print{
    body{
      margin:0;
      background:#ffffff;
    }
    .page-shell{
      padding:0;
    }
    .paper-page{
      max-width:none;
      margin:0;
      border-radius:0;
      border:none;
      box-shadow:none;
      padding:0;
      transform:none;
    }
  }
</style>

</head>
<body>

<div class="page-shell">
  <div class="paper-page">

    <!-- HEADER SURAT -->
    <div class="letter-head">
      <div class="letter-logo">
        <img src="<?= es_img_b64('images/akses.png') ?>" alt="Logo Kiri">
      </div>
      <div class="letter-title-block">
        <div class="company-name">PT TELKOM AKSES / EFIDI</div>
        <div class="company-subname">Divisi Provisioning &amp; Quality Assurance</div>
        <div class="company-address">
          Jl. Contoh Alamat No. 123, Kota Malang, Jawa Timur<br>
          Telp. (0341) 000000 · Email: info@efidi.id
        </div>
      </div>
      <div class="letter-logo">
        <img src="<?= es_img_b64('images/90.png') ?>" alt="Logo Kanan">
      </div>
    </div>

    <hr class="letter-divider">
    <hr class="letter-divider-thin">

    <div class="document-title">
      <div class="document-title-main">BERITA ACARA INSTALASI</div>
      <div class="document-title-sub">
        Detail Order Layanan Internet &amp; IPTV
      </div>
    </div>

    <!-- META SURAT -->
    <div class="document-meta">
      <table class="document-meta-table">
        <tr>
          <td class="document-meta-label">Nomor SC</td>
          <td class="document-meta-sep">:</td>
          <td><?= e($order['no_sc'] ?? '-') ?></td>
        </tr>
        <tr>
          <td>Nomor Internet</td>
          <td>:</td>
          <td><?= e($order['no_internet'] ?? '-') ?></td>
        </tr>
        <tr>
          <td>STO</td>
          <td>:</td>
          <td><?= e($order['sto'] ?? '-') ?></td>
        </tr>
        <tr>
          <td>Mitra</td>
          <td>:</td>
          <td><?= e($order['mitra'] ?? '-') ?></td>
        </tr>
        <tr>
          <td>Tanggal Pembuatan</td>
          <td>:</td>
          <td><?= e($createdAtFmt) ?></td>
        </tr>
      </table>
    </div>

    <!-- DATA ORDER -->
    <div class="section-title">I. Data Pelanggan &amp; Lokasi</div>
    <table class="detail">
      <?php renderPrintRows($leftFields, $order); ?>
    </table>

    <div class="section-title">II. Data Teknis &amp; Valins</div>
    <table class="detail">
      <?php renderPrintRows($rightFields, $order); ?>
    </table>

    <div class="two-col">
      <div class="col">
        <div class="section-title">III. Data Lama (Migrasi)</div>
        <table class="detail">
          <?php renderPrintRows($legacyFields, $order); ?>
        </table>
      </div>
      <div class="col">
        <div class="section-title">IV. Informasi Internal Sistem</div>
        <table class="detail">
          <?php renderPrintRows($metaFields, $order); ?>
          <tr>
            <th>Created At (Raw)</th>
            <td><?= e($order['created_at'] ?? '-') ?></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- LAMPIRAN -->
    <div class="section-title">V. Lampiran</div>

    <!-- Lampiran BAI -->
    <div class="lampiran-box lampiran-bai">
      <strong>Lampiran BAI</strong>
      <table class="detail lampiran-bai-table" style="margin-top:4px;">
        <tr>
          <th>Kode BAI</th>
          <td><?= e($order['kode_bai'] ?? '-') ?></td>
        </tr>
        <tr>
          <th>Nama File BAI</th>
          <td><?= e($order['nama_bai'] ?? '-') ?></td>
        </tr>
        <tr>
          <th>Uploader &amp; Tanggal</th>
          <td>
            <?= e($order['bai_uploader'] ?? '-') ?> /
            <?= e($order['bai_date'] ?? '-') ?>
          </td>
        </tr>
        <tr>
          <th>File di bot/file</th>
          <td>
            <div class="file-list">
              <?php if ($baiFiles): ?>
                <?php foreach ($baiFiles as $bf): ?>
                  <div class="file-list-item">
                    <?= e($bf['name']) ?> (<?= e($BAI_WEB_BASE . $bf['name']) ?>)
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:#9ca3af;">
                  File BAI dengan kode <code><?= e($noSc) ?></code>
                  tidak ditemukan di <code>bot/file</code>.
                </span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      </table>
    </div>

    <!-- Lampiran Foto -->
    <div class="lampiran-box">
      <strong>Lampiran Foto Lapangan</strong>
      <table class="detail" style="margin-top:4px;">
        <tr>
          <th>Kode Foto</th>
          <td><?= e($order['kode_foto'] ?? '-') ?></td>
        </tr>
        <tr>
          <th>Uploader &amp; Tanggal</th>
          <td>
            <?= e($order['foto_uploader'] ?? '-') ?> /
            <?= e($order['foto_date'] ?? '-') ?>
          </td>
        </tr>
      </table>

      <div class="foto-grid">
        <?php if ($fotoFiles): ?>
          <?php foreach ($fotoFiles as $idx => $f): ?>
            <div class="foto-item">
              <img src="<?= e($f['src']) ?>" alt="Foto <?= $idx+1 ?>">
              <div class="foto-caption">
                <?= e($f['name']) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="font-size:8.5pt;color:#9ca3af;">
            Tidak ada foto di folder <code><?= e($FOTO_WEB_BASE . $noSc . '/') ?></code>.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Halaman tambahan: isi BAI sebagai gambar -->
    <?php if ($baiFiles && $firstBaiUrl): ?>
      <div class="page-break">
        <div class="section-title">Lampiran BAI - Scan Dokumen</div>

        <?php if ($isPdfExport && $firstBaiPath && is_file($firstBaiPath)): ?>
          <?php $baiImages = es_pdf_to_dataurl_images($firstBaiPath); ?>

          <?php if ($baiImages): ?>
            <div class="bai-preview">
              <?php foreach ($baiImages as $idx => $src): ?>
                <div class="bai-page">
                  <div class="bai-page-header">Halaman <?= $idx+1 ?></div>
                  <img src="<?= $src ?>" alt="BAI halaman <?= $idx+1 ?>">
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="bai-preview">
              <div style="font-size:<?= $fontFileList ?>;color:#6b7280;">
                Lampiran BAI (PDF) tidak dapat diubah menjadi gambar
                di server (modul Imagick belum tersedia).
                File asli: <?= e($firstBaiUrl) ?>
              </div>
            </div>
          <?php endif; ?>

        <?php else: ?>
          <!-- Mode preview biasa (browser), pakai pdf.js -->
          <div id="bai-image-container" class="bai-preview">
            <div style="font-size:8.5pt;color:#6b7280;">
              Memuat isi BAI dari PDF, mohon tunggu sebentar...
            </div>
          </div>

          <noscript>
            <div style="margin-top:8px;font-size:8.5pt;color:#374151;">
              JavaScript non-aktif. Buka file BAI langsung:
              <?= e($firstBaiUrl) ?>
            </div>
          </noscript>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="footer-print">
      Dicetak pada: <?= date('d-m-Y H:i') ?> oleh sistem efidi.<br>
      Mode: <?= e($mode) ?> (Preview / Print).
    </div>

  </div>
</div>

<?php if ($baiFiles && $firstBaiUrl && !$isPdfExport): ?>
<!-- PDF.js dari CDN (untuk render BAI sebagai gambar di PREVIEW) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js"></script>
<script>
(function(){
  const baiUrl = "<?= e($firstBaiUrl) ?>";
  const container = document.getElementById('bai-image-container');
  if (!baiUrl || !container || !window['pdfjsLib']) {
    if (container) {
      container.innerHTML = '<div style="font-size:8.5pt;color:#e11d48;">Gagal memuat library PDF untuk pratinjau BAI.</div>';
    }
    return;
  }

  pdfjsLib.GlobalWorkerOptions.workerSrc =
    "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js";

  pdfjsLib.getDocument(baiUrl).promise.then(function(pdf){
    container.innerHTML = '';
    const total = pdf.numPages || 0;
    if (!total) {
      return;
    }

    let rendered = 0;

    for (let pageNum = 1; pageNum <= total; pageNum++) {
      const wrapper = document.createElement('div');
      wrapper.className = 'bai-page';

      const header = document.createElement('div');
      header.className = 'bai-page-header';
      header.textContent = 'Halaman ' + pageNum;

      const img = document.createElement('img');
      img.alt = 'BAI halaman ' + pageNum;
      img.style.width = '100%';

      wrapper.appendChild(header);
      wrapper.appendChild(img);
      container.appendChild(wrapper);

      (function(pageNumber, imgEl){
        pdf.getPage(pageNumber).then(function(page){
          const viewport = page.getViewport({ scale: 1.0 });

          const canvas = document.createElement('canvas');
          const ctx    = canvas.getContext('2d');
          canvas.width  = viewport.width;
          canvas.height = viewport.height;

          page.render({ canvasContext: ctx, viewport }).promise.then(function(){
            imgEl.src = canvas.toDataURL('image/png');
            rendered++;

            if (rendered === total) {
              try {
                const ev = new Event('efidi-bai-render-done');
                window.dispatchEvent(ev);
              } catch (e) {
                if (document.createEvent) {
                  const evLegacy = document.createEvent('Event');
                  evLegacy.initEvent('efidi-bai-render-done', true, true);
                  window.dispatchEvent(evLegacy);
                }
              }
            }
          });
        });
      })(pageNum, img);
    }
  }).catch(function(err){
    console.error('Gagal memuat PDF BAI:', err);
    if (container) {
      container.innerHTML =
        '<div style="font-size:8.5pt;color:#e11d48;">Gagal memuat isi BAI dari PDF. Silakan buka file langsung di <code>bot/file</code>.</div>';
    }
  });
})();
</script>
<?php endif; ?>

<?php if ($mode === 'print'): ?>
<script>
  // CETAK OTOMATIS SAAT MODE=print
  (function () {
    var hasBai = <?= ($baiFiles && $firstBaiUrl) ? 'true' : 'false' ?>;
    var printed = false;

    function doPrint() {
      if (printed) return;
      printed = true;
      setTimeout(function () {
        window.print();
      }, 400);
    }

    if (!hasBai) {
      window.addEventListener('load', function () {
        doPrint();
      });
    } else {
      window.addEventListener('efidi-bai-render-done', function () {
        doPrint();
      });

      window.addEventListener('load', function () {
        setTimeout(doPrint, 8000);
      });
    }
  })();
</script>
<?php endif; ?>

</body>
</html>
