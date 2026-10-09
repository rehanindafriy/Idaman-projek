<?php
require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

$idOrder = isset($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
// DEFAULT = modal
$mode    = $_GET['mode'] ?? 'modal'; // modal | print

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
 * Jika BAI belum terisi dari join, cari di tabel BAI pakai kode_bai = no_sc
 */
$noSc = $order['no_sc'] ?? '';
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
    $order['bai_uploader'] = $brow['uploader'] ?? '-';
    $order['bai_date']     = $brow['date_received'] ?? '-';
  }
}
/**
 * Jika FOTO belum terisi dari join, cari di tabel foto pakai kode_foto = no_sc
 */
if (
  (!isset($order['id_foto']) || !$order['id_foto']) &&
  $noSc !== ''
) {
  $sqlFoto = "SELECT * FROM foto WHERE kode_foto = :no_sc ORDER BY id_foto DESC LIMIT 1";
  $stFoto  = $pdo->prepare($sqlFoto);
  $stFoto->execute([':no_sc' => $noSc]);

  if ($frow = $stFoto->fetch(PDO::FETCH_ASSOC)) {
    // isi field yang dipakai di tampilan
    $order['id_foto']       = $frow['id_foto'];
    $order['kode_foto']     = $frow['kode_foto'];
    $order['foto_uploader'] = $frow['uploader']      ?? '-';
    $order['foto_date']     = $frow['date_received'] ?? '-';
  }
}


/** Format created_at */
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
 * Cari file BAI di bot/file dan foto di bot/foto/<NO_SC>/
 */
$baseDir = __DIR__;
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$baiFiles  = [];
$fotoFiles = [];

/* ==== FILE BAI ==== */
$baiName = $order['nama_bai'] ?? '';
if ($baiName !== '') {
  $path = $baseDir . "/bot/file/" . $baiName;
  if (is_file($path)) {
    $baiFiles[] = [
      'name' => $baiName,
      'path' => $path,
      'url'  => $baseUrl . '/bot/file/' . rawurlencode($baiName),
    ];
  }
}

if (!$baiFiles && $noSc !== '') {
  foreach (glob($baseDir . "/bot/file/*" . $noSc . "*.*") ?: [] as $path) {
    if (is_file($path)) {
      $baiFiles[] = [
        'name' => basename($path),
        'path' => $path,
        'url'  => $baseUrl . '/bot/file/' . basename($path),
      ];
    }
  }
}

/* ==== FOTO: bot/foto/NO_SC/*.jpg|jpeg|png ==== */
if ($noSc !== '') {
  $fotoDir = $baseDir . "/bot/foto/" . $noSc;
  if (is_dir($fotoDir)) {
    $exts = ['jpg','jpeg','png','JPG','JPEG','PNG'];
    foreach ($exts as $ext) {
      foreach (glob($fotoDir . "/*." . $ext) ?: [] as $path) {
        if (is_file($path)) {
          $fotoFiles[] = [
            'name' => basename($path),
            'url'  => $baseUrl . '/bot/foto/' . rawurlencode($noSc) . '/' . basename($path),
          ];
        }
      }
    }
  }
}

/** Mapping field ke label */
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

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Print Detail Order - <?= e($order['no_sc'] ?? '') ?></title>
  <style>
    * { box-sizing:border-box; }
    body {
  /* FONT SAMA NUANSANYA DENGAN FORM EFIDI */
  font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont,
               "Helvetica Neue", Arial, sans-serif;
  font-size: 10pt;
  color:#111827;
  margin:0;
  background:#ffffff;
}

    h1,h2,h3,h4,h5 { margin:0; padding:0; }

    /* MODE MODAL */
    .modal-overlay{
      position:fixed;
      inset:0;
      background:rgba(209, 214, 226, 0.8);
      display:flex;
      align-items:center;
      justify-content:center;
      z-index:999;
      padding:16px;
    }

    .modal-dialog{
      background:linear-gradient(
        110deg,
        #f8feff 0%,
        #f8feff 22%,
        #dcf3f8 50%,
        #eef8fc 78%,
        #eef8fc 100%
      );
      border-radius:8px;
      max-width:1180px;
      width:100%;
      max-height:100%;
      display:flex;
      flex-direction:column;
      overflow:hidden;
      box-shadow:0 28px 80px rgba(15,23,42,.45);
      border:1px solid rgba(148,163,184,.35);
    }

    .modal-header{
      padding:10px 18px;
      border-bottom:1px solid rgba(148,163,184,.35);
      display:flex;
      align-items:center;
      justify-content:space-between;
      font-size:10pt;
      font-weight:600;
      color:#0f3e47;
      background:linear-gradient(
        110deg,
        #f8feff 0%,
        #f8feff 30%,
        #dcf3f8 80%,
        #eef8fc 100%
      );
      /* toolbar pakai font sans supaya lebih modern */
      font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
    }
    .modal-header span{
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .modal-body{
      padding:16px 16px 18px 16px;
      overflow:auto;
    }
    .modal-close-btn{
      border:none;
      background:transparent;
      cursor:pointer;
      font-size:11pt;
      padding:4px 8px;
      border-radius:999px;
    }
    .modal-close-btn:hover{
      background:rgba(148,163,184,.25);
    }

    .preview-shell{
      background:linear-gradient(
        110deg,
        #f9feff 0%,
        #eef8fc 40%,
        #f9feff 100%
      );
      border-radius:8px;
      padding:18px;
      border:1px dashed rgba(148,163,184,.6);
      position:relative;
      overflow:auto;
    }

    /* PAPER / KERTAS */
    .paper-page{
      background:#ffffff;
      border-radius:6px;
      max-width:900px;
      margin:0 auto 4px auto;
      padding:22px 28px 18px 28px;
      box-shadow:0 10px 30px rgba(15,23,42,.18);
      border:1px solid #e5e7eb;
    }

    /* Toolbar print di atas (pojok kanan) */
    .print-toolbar{
      position:fixed;
      top:16px;
      right:24px;
      z-index:1000;
      background:rgba(15,23,42,.95);
      border-radius:999px;
      padding:6px 10px 6px 14px;
      box-shadow:0 0px 2px rgba(15,23,42,.65);
      font-size:9pt;
      display:flex;
      align-items:center;
      gap:8px;
      color:#e5e7eb;
      border:1px solid rgba(148,163,184,.6);
      backdrop-filter:blur(10px);
      font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
    }
    .print-toolbar-badge{
      font-size:8pt;
      text-transform:uppercase;
      letter-spacing:.16em;
      opacity:.85;
    }
    .print-toolbar-label{
      font-weight:600;
      color:#e5e7eb;
      max-width:180px;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .print-toolbar-spacer{
      width:1px;
      height:20px;
      background:linear-gradient(to bottom,transparent,#94a3b8,transparent);
      margin:0 2px;
    }

    .print-toolbar button{
      border-radius:999px;
      border:none;
      background:linear-gradient(135deg,#e5f7f9,#8fd8df);
      color:#0f3e47;
      font-size:9pt;
      padding:5px 13px;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:6px;
      font-weight:600;
      font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
    }
    .print-toolbar button span.icon{
      font-size:12px;
    }
    .print-toolbar button.btn-secondary{
      border:1px solid rgba(148,163,184,.7);
      background:rgba(15,23,42,.85);
      box-shadow:none;
      color:#e5e7eb;
      font-weight:500;
      padding-inline:11px;
    }
    .print-toolbar button:hover{
      transform:translateY(-1px);
    }
    .print-toolbar button:active{
      transform:translateY(0);
      box-shadow:none;
    }

    /* ====== HEADER SURAT FORMAL ====== */
    .letter-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:6px;
    }
    .letter-logo{
      width:130px;
      text-align:center;
    }
    .letter-logo img{
      max-width:100px;
      max-height:100px;
      object-fit:contain;
    }
    .letter-title-block{
      flex:1;
      text-align:center;
      padding:0 12px;
    }
    .company-name{
      font-size:12pt;
      font-weight:bold;
      text-transform:uppercase;
      letter-spacing:.08em;
    }
    .company-subname{
      font-size:10pt;
      margin-top:2px;
    }
    .company-address{
      font-size:8.8pt;
      margin-top:3px;
      line-height:1.3;
    }

    .letter-divider{
      border:none;
      border-top:2px solid #111827;
      margin:4px 0 10px 0;
    }
    .letter-divider-thin{
      border:none;
      border-top:1px solid #111827;
      margin-top:-7px;
      margin-bottom:12px;
    }

    .document-title{
      text-align:center;
      margin-bottom:8px;
    }
    .document-title-main{
      font-size:14pt;
      font-weight:bold;
      text-transform:uppercase;
      text-decoration:underline;
    }
    .document-title-sub{
      font-size:10pt;
      margin-top:2px;
    }

    .document-meta{
      margin-top:4px;
      margin-bottom:10px;
      font-size:9.5pt;
    }
    .document-meta-table{
      border-collapse:collapse;
      width:100%;
    }
    .document-meta-table td{
      padding:1px 0;
      vertical-align:top;
    }
    .document-meta-label{
      width:22%;
    }
    .document-meta-sep{
      width:2%;
    }

    .section-title {
      margin-top:10px;
      margin-bottom:4px;
      font-weight:bold;
      text-transform:uppercase;
      font-size:9pt;
      color:#008a97;
      border-bottom:1px solid #e5e7eb;
      padding-bottom:2px;
    }

    table.detail {
      width:100%;
      border-collapse:collapse;
      margin-bottom:6px;
      font-size:9pt;
      background:#ffffff;
    }
    table.detail th,
    table.detail td {
      border:1px solid #e5e7eb;
      padding:3px 5px;
      vertical-align:top;
    }
    table.detail th {
      width:28%;
      background:#f9fafb;
      text-align:left;
      font-weight:600;
      color:#374151;
      font-size:8.8pt;
    }

    .two-col {
      display:flex;
      gap:8px;
      margin-top:4px;
    }
    .two-col > .col {
      flex:1;
    }

    .lampiran-box {
      border:1px solid #e5e7eb;
      border-radius:6px;
      padding:6px;
      margin-bottom:6px;
      background:#ffffff;
    }

    .file-list {
      font-size:8.8pt;
    }
    .file-list-item {
      margin-bottom:2px;
    }

    .foto-grid {
      display:flex;
      flex-wrap:wrap;
      gap:6px;
    }
    .foto-item {
      width:calc(25% - 10px);
      border:1px solid #e5e7eb;
      padding:4px;
      border-radius:4px;
      page-break-inside:avoid;
      background:#ffffff;
    }
    .foto-item img {
      width:100%;
      height:auto;
      max-height:80px;
      object-fit:contain;
      display:block;
    }
    .foto-caption {
      font-size:7pt;
      margin-top:2px;
      word-break:break-all;
    }

    .footer-print {
      margin-top:10px;
      font-size:8.5pt;
      color:#6b7280;
      border-top:1px dashed #d1d5db;
      padding-top:4px;
    }

    /* BAI preview (hasil render PDF -> gambar via PDF.js) */
    .bai-preview{
      margin-top:6px;
    }
    .bai-page{
      margin-top:6px;
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
      page-break-before: always;
      margin-top:12px;
    }

    @media print {
      body {
        margin:8mm 10mm;
        background:#ffffff;
      }
      .print-toolbar{ display:none; }

      .modal-overlay{
        position:static;
        background:transparent;
        box-shadow:none;
        padding:0;
      }
      .modal-dialog{
        max-width:none;
        width:100%;
        max-height:none;
        border-radius:0;
        box-shadow:none;
        background:#ffffff;
        border:none;
      }
      .modal-header{ display:none; }
      .modal-body{
        overflow:visible;
        background:#ffffff;
        padding:0;
      }
      .preview-shell{
        border:none;
        padding:0;
        border-radius:0;
        background:#ffffff;
      }
      .paper-page{
        max-width:none;
        margin:0;
        border-radius:0;
        box-shadow:none;
        padding:0;
        border:none;
      }
    }
  </style>

</head>
<body>

<?php if ($mode === 'modal'): ?>
  <!-- MODE MODAL -->
  <div class="modal-overlay">
    <div class="modal-dialog">
      <div class="modal-header">
        <span>Preview Print - NO SC: <?= e($order['no_sc'] ?? '-') ?></span>
        <button class="modal-close-btn" onclick="window.close()">✕</button>
      </div>
      <div class="modal-body">
        <div class="preview-shell">
<?php endif; ?>

  <!-- TOOLBAR CETAK -->
  <div class="print-toolbar">
    <div>
      <div class="print-toolbar-badge">Preview Print</div>
      <div class="print-toolbar-label">
        NO SC: <?= e($order['no_sc'] ?? '-') ?>
      </div>
    </div>
    <div class="print-toolbar-spacer"></div>
    <button type="button" onclick="doPrint()">
      <span class="icon">🖨</span><span>Cetak</span>
    </button>
    <button type="button" class="btn-secondary" onclick="window.close()">
      <span class="icon">✕</span><span>Tutup</span>
    </button>
  </div>

  <!-- KERTAS -->
  <div class="paper-page">

    <!-- HEADER SURAT DENGAN LOGO KIRI–KANAN -->
    <div class="letter-head">
      <div class="letter-logo">
        <!-- GANTI SRC DENGAN LOGO PERUSAHAAN KIRI -->
        <img src="images/akses.png" alt="Logo Kiri">
      </div>
      <div class="letter-title-block">
        <div class="company-name">PT TELKOM AKSES / EFIDI</div>
        <div class="company-subname">Divisi Provisioning & Quality Assurance</div>
        <div class="company-address">
          Jl. Contoh Alamat No. 123, Kota Malang, Jawa Timur<br>
          Telp. (0341) 000000 · Email: info@efidi.id
        </div>
      </div>
      <div class="letter-logo">
        <!-- GANTI SRC DENGAN LOGO PERUSAHAAN KANAN -->
        <img src="images/90.png" alt="Logo Kanan">
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

    <!-- META SURAT (NO SC, NO INTERNET, STO, MITRA, TANGGAL) -->
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
    <div class="lampiran-box">
      <strong>Lampiran BAI</strong>
      <table class="detail" style="margin-top:4px;">
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
                    <?= e($bf['name']) ?> (<?= e('bot/file/' . $bf['name']) ?>)
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:#9ca3af;">
                  File BAI dengan kode <code><?= e($noSc) ?></code>
                  tidak ditemukan di <code>bot/file</code>.
                </span>
              <?php endif; ?>
            </div>
            <?php if ($baiFiles): ?>
              <div style="font-size:8pt;color:#6b7280;margin-top:3px;">
                Pratinjau isi BAI akan dimuat sebagai gambar di halaman terakhir.
              </div>
            <?php endif; ?>
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
              <img src="<?= e($f['url']) ?>" alt="Foto <?= $idx+1 ?>">
              <div class="foto-caption">
                <?= e($f['name']) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="font-size:8.5pt;color:#9ca3af;">
            Tidak ada foto di folder <code>bot/foto/<?= e($noSc) ?>/</code>.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer-print">
      Dicetak pada: <?= date('d-m-Y H:i') ?> oleh sistem efidi.<br>
      Mode: <?= e($mode) ?> (Preview / Print).
    </div>

    <!-- HALAMAN TERAKHIR: ISI BAI SEBAGAI GAMBAR (PDF.js) -->
    <?php if ($baiFiles): ?>
      <?php $firstBaiUrl = $baiFiles[0]['url']; ?>
      <div class="page-break">
        <div class="section-title">Lampiran BAI - Scan Dokumen</div>

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
      </div>
    <?php endif; ?>

  </div><!-- /.paper-page -->

<?php if ($mode === 'modal'): ?>
        </div><!-- /.preview-shell -->
      </div> <!-- .modal-body -->
    </div>   <!-- .modal-dialog -->
  </div>     <!-- .modal-overlay -->
<?php endif; ?>

<?php if ($mode === 'print' || $mode === 'modal'): ?>
<!-- PDF.js dari CDN (untuk render BAI sebagai gambar) -->
<?php if ($baiFiles): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js"></script>
<script>
(function(){
  const baiUrl = "<?= e($firstBaiUrl ?? '') ?>";
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
    const total = pdf.numPages;

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
          const viewport = page.getViewport({ scale: 1.2 });

          const canvas = document.createElement('canvas');
          const ctx    = canvas.getContext('2d');
          canvas.width  = viewport.width;
          canvas.height = viewport.height;

          page.render({ canvasContext: ctx, viewport }).promise.then(function(){
            imgEl.src = canvas.toDataURL('image/png');
          });
        });
      })(pageNum, img);
    }
  }).catch(function(err){
    console.error('Gagal memuat PDF BAI:', err);
    container.innerHTML =
      '<div style="font-size:8.5pt;color:#e11d48;">Gagal memuat isi BAI dari PDF. Silakan buka file langsung di <code>bot/file</code>.</div>';
  });
})();
</script>
<?php endif; ?>

<script>
function doPrint(){
  try { window.focus(); } catch(e){}
  window.print();
}
</script>

<?php if ($mode === 'print'): ?>
<script>
window.addEventListener('load', function(){
  setTimeout(function(){ doPrint(); }, 800);
});
</script>
<?php endif; ?>
<?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
