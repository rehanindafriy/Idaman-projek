<?php
include "header.php";
?>

<!-- partial -->
<div class="main-panel">
  <div class="content-wrapper">
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <!-- kiri -->
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Search </h2>
            <p class="mb-0">
              Form pencarian ini digunakan untuk mencari data Order, berdasarkan berbagai kriteria. Anda dapat
            </p>
            <p>
              memasukkan kata kunci seperti Nomor SC, Nomor Internet, Nama Pelanggan, Alamat, atau Nomor HP Pelanggan.
            </p>
          </div>

          <!-- kanan: breadcrumb -->
          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo"
                 class="mb-1"
                 style="height:45px; margin-top:-10px; display:block;">

            <br><br>

            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon"
                 style="font-size:13px;line-height:1;vertical-align:-90px"></i>

              <a href="index.php" class="text-muted mx-1 crumb-mid">
                &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
              </a>

              <span class="text-primary crumb-mid">Search</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12 stretch-card">
        <div class="card card-narrow card-tall">
          <div class="card-body">

<?php
/* ============================================================
 * UTIL
 * ============================================================ */
if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

/**
 * Baca filter / field (disamakan dengan search_bot.php)
 * - URL baru : ?field=...
 * - URL lama : ?f=...
 */
$f = '';
if (!empty($_GET['field'])) {
  $f = (string)$_GET['field'];
} elseif (!empty($_GET['f'])) {
  $f = (string)$_GET['f'];
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
  require_once __DIR__ . '/koneksi.php';
}

/**
 * Cari kolom timestamp di tabel orders
 */
function findTsCol(PDO $pdo): ?string {
  static $cols = null;
  if ($cols === null) {
    $st   = $pdo->query("SHOW COLUMNS FROM `orders`");
    $cols = array_map(static fn($r) => $r['Field'], $st->fetchAll(PDO::FETCH_ASSOC));
  }

  $candidates = [
    'created_at','tanggal','tgl','tgl_input',
    'inserted_at','waktu','createdAt','ts','date'
  ];
  foreach ($candidates as $c) {
    if (in_array($c, $cols, true)) return $c;
  }

  foreach (['id','id_order'] as $pk) {
    if (in_array($pk, $cols, true)) return $pk;
  }

  return null;
}

$tsCol = findTsCol($pdo);

/**
 * Deteksi kolom tambahan
 */
$ordersCols = [];
try {
  $stCols = $pdo->query("SHOW COLUMNS FROM `orders`");
  $ordersCols = array_map(static fn($r) => $r['Field'], $stCols->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
  $ordersCols = [];
}

$hasAlamat = in_array('alamat', $ordersCols, true);
$hasQrcode = in_array('qrcode', $ordersCols, true);

$colVoip = null;
if (in_array('no_voip', $ordersCols, true)) {
  $colVoip = 'no_voip';
} elseif (in_array('no_voip_lama', $ordersCols, true)) {
  $colVoip = 'no_voip_lama';
}

/**
 * Lokasi foto
 * Folder: bot/foto/{no_sc}/*.jpg|jpeg|png|webp|gif
 */
$FOTO_FS_DIR   = rtrim(realpath(__DIR__ . '/bot/foto') ?: (__DIR__ . '/bot/foto'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$FOTO_URL_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/bot/foto';

/**
 * List semua foto untuk satu row
 */
function list_photo_urls(array $row, string $fsDir, string $urlBase): array {
  $noSc = trim((string)($row['no_sc'] ?? ''));
  if ($noSc === '') return [];

  $folder = $fsDir . $noSc;
  if (!is_dir($folder)) return [];

  $allowed = '/\.(?:jpe?g|png|webp|gif)$/i';
  $scan    = @scandir($folder, SCANDIR_SORT_NONE) ?: [];

  $urls = [];
  foreach ($scan as $fn) {
    if ($fn === '.' || $fn === '..') continue;
    if (!preg_match($allowed, $fn)) continue;

    $p = $folder . DIRECTORY_SEPARATOR . $fn;
    if (!is_file($p)) continue;

    $urls[] = $urlBase . '/' . rawurlencode($noSc) . '/' . rawurlencode($fn);
  }
  return $urls;
}

/* ============================================================
 * OPTIONS UNTUK SEMUA / PER KOLOM
 * ============================================================ */
$searchCols = [
  'no_sc',
  'no_internet',
  'cp_pelanggan',
  'nama_pelanggan',
];
if ($hasAlamat) $searchCols[] = 'alamat';
if ($hasQrcode) $searchCols[] = 'qrcode';

$optionsByCol  = [];
$latestByValue = [];

foreach ($searchCols as $col) {
  $optionsByCol[$col] = [];

  try {
    if ($tsCol) {
      $sql = "
        SELECT `$col` AS v, MAX(`$tsCol`) AS latest
        FROM `orders`
        WHERE `$col` IS NOT NULL AND `$col` <> ''
        GROUP BY `$col`
        ORDER BY latest DESC, `$col` DESC
      ";
    } else {
      $sql = "
        SELECT DISTINCT `$col` AS v
        FROM `orders`
        WHERE `$col` IS NOT NULL AND `$col` <> ''
        ORDER BY v DESC
      ";
    }

    $st   = $pdo->query($sql);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $r) {
      $v = $r['v'] ?? '';
      if ($v === '' || $v === null) continue;

      $optionsByCol[$col][] = $v;

      if ($tsCol && isset($r['latest']) && $r['latest'] !== null) {
        $t = strtotime((string)$r['latest']);
        if ($t && (!isset($latestByValue[$v]) || $t > $latestByValue[$v])) {
          $latestByValue[$v] = $t;
        }
      }
    }
  } catch (Throwable $e) {
    $optionsByCol[$col] = [];
  }
}

/* ============================================================
 * NO INTERNET / NO VOIP – DIGABUNG
 * ============================================================ */
$voipPairs = [];
if ($colVoip !== null) {
  try {
    if ($tsCol) {
      $sqlVoip = "
        SELECT 
          `no_internet`,
          `$colVoip` AS no_voip,
          MAX(`$tsCol`) AS latest
        FROM `orders`
        WHERE 
          (`no_internet` IS NOT NULL AND `no_internet` <> '')
          OR (`$colVoip` IS NOT NULL AND `$colVoip` <> '')
        GROUP BY `no_internet`, `$colVoip`
        ORDER BY latest DESC
      ";
    } else {
      $sqlVoip = "
        SELECT DISTINCT
          `no_internet`,
          `$colVoip` AS no_voip
        FROM `orders`
        WHERE 
          (`no_internet` IS NOT NULL AND `no_internet` <> '')
          OR (`$colVoip` IS NOT NULL AND `$colVoip` <> '')
        ORDER BY `no_internet` DESC, `$colVoip` DESC
      ";
    }

    $stV  = $pdo->query($sqlVoip);
    $rows = $stV->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $r) {
      $net  = trim((string)($r['no_internet'] ?? ''));
      $voip = trim((string)($r['no_voip'] ?? ''));
      if ($net === '' && $voip === '') continue;

      $voipPairs[] = [
        'no_internet' => $net,
        'no_voip'     => $voip,
      ];
    }
  } catch (Throwable $e) {
    $voipPairs = [];
  }
}

/* ============================================================
 * GABUNGAN UNTUK MODE "SEMUA KOLOM"
 * ============================================================ */
if ($tsCol && $latestByValue) {
  $searchOptionsAll = array_keys($latestByValue);
  usort($searchOptionsAll, static function($a, $b) use ($latestByValue) {
    $ta = $latestByValue[$a] ?? 0;
    $tb = $latestByValue[$b] ?? 0;
    if ($ta === $tb) {
      return strcmp((string)$b, (string)$a);
    }
    return $tb <=> $ta;
  });
} else {
  $searchOptionsAll = array_values(array_unique(array_merge(
    $optionsByCol['no_sc']          ?? [],
    $optionsByCol['no_internet']    ?? [],
    $optionsByCol['cp_pelanggan']   ?? [],
    $optionsByCol['nama_pelanggan'] ?? [],
    $optionsByCol['alamat']         ?? [],
    $optionsByCol['qrcode']         ?? []
  )));
}

/**
 * Data per kolom untuk JS
 */
$jsOptionsByCol = [
  'no_sc'          => $optionsByCol['no_sc']          ?? [],
  'no_internet'    => $optionsByCol['no_internet']    ?? [],
  'nama_pelanggan' => $optionsByCol['nama_pelanggan'] ?? [],
  'cp_pelanggan'   => $optionsByCol['cp_pelanggan']   ?? [],
  'alamat'         => $optionsByCol['alamat']         ?? [],
  'qrcode'         => $optionsByCol['qrcode']         ?? [],
];

/* ============================================================
 * DATA DETAIL (1 baris per no_sc paling baru)
 * ============================================================ */
$detailRows = [];
try {
  if ($tsCol) {
    $sqlDetail = "
      SELECT o.*
      FROM orders o
      INNER JOIN (
        SELECT no_sc, MAX(`$tsCol`) AS latest
        FROM orders
        WHERE no_sc IS NOT NULL AND no_sc <> ''
        GROUP BY no_sc
      ) t ON t.no_sc = o.no_sc AND t.latest = o.`$tsCol`
      ORDER BY o.`$tsCol` DESC
      LIMIT 800
    ";
  } else {
    $sqlDetail = "
      SELECT *
      FROM orders
      WHERE no_sc IS NOT NULL AND no_sc <> ''
      ORDER BY id_order DESC
      LIMIT 800
    ";
  }

  $stD   = $pdo->query($sqlDetail);
  $rowsD = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rowsD as $r) {
    $noSc = trim((string)($r['no_sc'] ?? ''));
    if ($noSc === '') continue;

    $thumbs = list_photo_urls($r, $FOTO_FS_DIR, $FOTO_URL_BASE);

    $detailRows[] = [
      'no_sc'          => $noSc,
      'no_internet'    => trim((string)($r['no_internet']    ?? '')),
      'no_voip'        => $colVoip ? trim((string)($r[$colVoip] ?? '')) : '',
      'nama_pelanggan' => trim((string)($r['nama_pelanggan'] ?? '')),
      'alamat'         => trim((string)($r['alamat']         ?? '')), // FULL alamat
      'cp_pelanggan'   => trim((string)($r['cp_pelanggan']   ?? '')),
      'qrcode'         => trim((string)($r['qrcode']         ?? '')),
      'thumbs'         => $thumbs,
      'thumb'          => $thumbs[0] ?? null,
    ];
  }
} catch (Throwable $e) {
  $detailRows = [];
}

/* ============================================================
 * BACA CODES TERPILIH
 * ============================================================ */
$selectedCodes = [];
if (isset($_GET['codes']) && is_array($_GET['codes'])) {
  foreach ($_GET['codes'] as $v) {
    $v = trim((string)$v);
    if ($v === '') continue;
    $selectedCodes[] = $v;
  }
} else {
  $q = $_GET['q'] ?? '';
  if (is_array($q)) {
    $q = $q[0] ?? '';
  }
  $q = trim((string)$q);
  if ($q !== '') $selectedCodes[] = $q;
}
$selectedCodes = array_values(array_unique($selectedCodes));

$fieldSelected = ($f === '' ? 'all' : $f);
?>

<!-- ===================== SEARCH CARD ===================== -->

<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/habibmhamadi/multi-select-tag@4.0.1/dist/css/multi-select-tag.min.css">
<script src="https://cdn.jsdelivr.net/gh/habibmhamadi/multi-select-tag@4.0.1/dist/js/multi-select-tag.min.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<style>
/* ====== LAYOUT CARD SEARCH (khusus halaman ini) ====== */
.stretch-card > .card.card-narrow{
  width:auto !important;
  max-width:1120px;
  margin-left:auto;
  margin-right:auto;
}

.card-tall .card-body{
  min-height:520px;
  padding-bottom:24px;
}

.search-shell{
  margin-top:4px;
}

.search-section-label{
  font-size:.74rem;
  letter-spacing:.18em;
  text-transform:uppercase;
  font-weight:600;
  color:#6b7280;
  margin-bottom:6px;
}

.order-search-card{
  background:#f4f9fb;
  border-radius:18px;
  border:1px solid #dbeafe;
  overflow:hidden;
}

.order-search-header{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  padding:18px 24px 16px;
  background:linear-gradient(90deg,#e5f7f9 0%,#cceff4 80%,#f3f4f6 100%);
  color:#064e4f;
}

.order-search-header-main{
  display:flex;
  align-items:flex-start;
  gap:12px;
}

.order-search-header-icon{
  width:42px;
  height:42px;
  border-radius:14px;
  background:#ffffff;
  color:#00A3AD;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:22px;
}

.order-search-title{
  font-weight:600;
  font-size:1.06rem;
  margin-bottom:2px;
  color:#03606c;
}

.order-search-subtitle{
  font-size:.84rem;
  color:#014451;
}

.order-search-pills{
  display:flex;
  flex-wrap:wrap;
  gap:6px;
  justify-content:flex-end;
}

.search-pill{
  display:inline-flex;
  align-items:center;
  gap:4px;
  font-size:.74rem;
  padding:4px 10px;
  border-radius:999px;
  background:rgba(255,255,255,.75);
  color:#047479;
  border:1px solid rgba(148,210,218,.65);
  white-space:nowrap;
}

.search-pill--accent{
  background:#047479;
  color:#ecfeff;
  border-color:#047479;
}

.order-search-body{
  padding:18px 22px 20px;
  background:#f9fafb;
  display:grid;
  grid-template-columns:minmax(0,1.9fr) minmax(260px,1.1fr);
  gap:18px;
}
@media (max-width: 991.98px){
  .order-search-body{
    grid-template-columns:1fr;
  }
}

.order-search-main{
  background:#ffffff;
  border-radius:14px;
  border:1px solid #e5e7eb;
  padding:16px 16px 14px;
}

.order-search-main-header{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  margin-bottom:8px;
}

.order-search-main-title{
  font-size:.9rem;
  font-weight:600;
  color:#111827;
}

.order-search-main-caption{
  font-size:.79rem;
  color:#6b7280;
}

.order-search-mode{
  font-size:.78rem;
  color:#047479;
  background:linear-gradient(90deg,#e5f7f9b3 0%,#cceff46b 80%,#f3f4f6a4 100%);
  padding:4px 10px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  gap:4px;
}

.order-search-grid{
  display:grid;
  grid-template-columns:minmax(0,1.6fr) 170px;
  gap:10px;
  align-items:flex-start;
  margin-top:10px;
}

.order-search-field{
  display:flex;
  flex-direction:column;
  gap:4px;
}

.order-search-main .form-select{
  height:44px;
  min-height:44px;
  font-size:.88rem;
  border-radius:8px;
}

.order-search-card .ts-loading-option{
  display:flex;
  align-items:center;
  gap:6px;
  padding:4px 8px;
  font-size:.78rem;
  color:#00a3ad;
}
.order-search-card .ts-empty-hint{
  padding:4px 8px;
  font-size:.78rem;
  color:#9ca3af;
}
.order-search-card .ts-empty-hint strong{ color:#0f172a; }
.order-search-card .ts-spinner{
  width:12px;
  height:12px;
  border-radius:999px;
  border:2px solid rgba(148,163,184,0.45);
  border-top-color:#00a3ad;
  animation:ts-spin .75s linear infinite;
}
@keyframes ts-spin{
  to{ transform: rotate(360deg); }
}

#leftCell > *{ width:100%; }

.order-search-footer-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-top:12px;
  gap:10px;
}

.order-search-hint-inline{
  font-size:.78rem;
  color:#6b7280;
}

/* tombol cari */
.btn-cari{
  --btn-bg1:#20a1ad;
  --btn-bg2:#49b3bdff;
  --btn-press:#108893ff;
  --btn-text:#ffffff;
  --btn-radius:999px;

  position:relative;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:.45rem;

  height:40px;
  min-height:40px;
  padding:0 18px;
  border-radius:var(--btn-radius);

  border:1px solid rgba(255,255,255,.45);
  box-shadow:none !important;

  background-image:linear-gradient(135deg,var(--btn-bg1),var(--btn-bg2));
  background-size:180% 180%;
  background-position:12% 50%;

  color:var(--btn-text);
  font-size:.9rem;
  font-weight:500;
  letter-spacing:.02em;
  white-space:nowrap;
  text-decoration:none;
  cursor:pointer;

  transition:
    background-position .4s ease-out,
    transform .14s ease-out,
    border-color .18s ease-out,
    color .14s ease-out;
}
.btn-cari i{
  font-size:1.1em;
  line-height:1;
  color:inherit;
  transition:transform .18s ease-out;
}
.btn-cari:hover,
.btn-cari:focus,
.btn-cari:focus-visible,
.btn-cari:active{
  color:var(--btn-text) !important;
  text-decoration:none;
}
.btn-cari:hover,
.btn-cari:focus-visible{
  background-position:80% 50%;
  border-color:rgba(255,255,255,.8);
  transform:translateY(-0.5px);
}
.btn-cari:hover i,
.btn-cari:focus-visible i{
  transform:translateX(1px);
}
.btn-cari:active{
  transform:translateY(0);
  background-image:linear-gradient(135deg,var(--btn-press),var(--btn-bg1));
}
.btn-cari:focus{ outline:none; }
.btn-cari::after{
  content:''; position:absolute; inset:1px;
  border-radius:inherit; border:1px solid rgba(255,255,255,.35);
  opacity:0; transform:scale(.96);
  transition:opacity .22s ease-out, transform .22s ease-out;
}
.btn-cari:hover::after,
.btn-cari:focus-visible::after{
  opacity:1; transform:scale(1);
}
.btn-cari::before{
  content:''; position:absolute;
  top:-30%; left:-35%; width:40%; height:160%;
  pointer-events:none;
  background:linear-gradient(
    120deg,
    rgba(255,255,255,0) 0%,
    rgba(255,255,255,.9) 50%,
    rgba(255,255,255,0) 100%
  );
  opacity:0;
  transform:translateX(-120%) skewX(-18deg);
  animation:btnCariShine 1.6s cubic-bezier(.25,.8,.25,1) infinite;
}
@keyframes btnCariShine{
  0%{ transform:translateX(-120%) skewX(-18deg); opacity:0; }
  15%{ opacity:.95; }
  100%{ transform:translateX(260%) skewX(-18deg); opacity:0; }
}

/* aside kanan */
.order-search-aside{
  background:#ffffff;
  border-radius:14px;
  border:1px dashed #d1d5db;
  padding:14px 16px 13px;
  font-size:.8rem;
  color:#4b5563;
}

.order-search-aside-title{
  font-weight:600;
  font-size:.86rem;
  display:flex;
  align-items:center;
  gap:6px;
  margin-bottom:4px;
}
.order-search-aside-title i{ font-size:1rem; }

.order-search-aside-sub{
  font-size:.78rem;
  color:#6b7280;
  margin-bottom:8px;
}

.help-list{
  margin:0 0 6px 18px;
  padding-left:0;
  font-size:.78rem;
  line-height:1.5;
}
.help-list li + li{ margin-top:2px; }
.help-list span.text-muted{
  color:#9ca3af !important;
}

.order-search-aside-divider{
  border-top:1px dashed #e5e7eb;
  margin:8px 0 8px;
}

.order-search-aside-meta-label{
  font-size:.75rem;
  text-transform:uppercase;
  letter-spacing:.04em;
  color:#9ca3af;
  margin-bottom:3px;
}

.order-search-aside-meta-pill{
  display:inline-flex;
  align-items:center;
  gap:5px;
  padding:3px 9px;
  border-radius:999px;
  background:#ecfdf5;
  color:#047857;
  font-size:.78rem;
}
.order-search-aside-meta-pill i{ font-size:.98rem; }

.order-search-aside-meta-time{
  font-size:.74rem;
  color:#9ca3af;
  margin-top:3px;
}

/* Tom Select */
.order-search-card .ts-dropdown .dropdown-content{ padding:4px; }
.order-search-card .ts-dropdown .option{
  padding:10px 12px;
  line-height:1.5;
  border-bottom:1px solid #eef2f7;
}
.order-search-card .ts-dropdown .option:last-child{ border-bottom:0; }
.order-search-card .ts-dropdown .option.active,
.order-search-card .ts-dropdown .option:hover{
  background:rgba(0,163,173,.08);
}

.order-search-card .ts-wrapper.single .ts-control{
  min-height:44px;
  padding:6px 10px;
  gap:6px;
  flex-wrap:wrap;
  border:1px solid #C9E4E6 !important;
  border-radius:8px;
  box-shadow:none !important;
}

/* ====== STYLE TAG TERPILIH AGAR MIRIP GAMBAR 1 ====== */
.order-search-card .ts-wrapper.single .ts-control .item{
  background: #f3f3f3 !important;
  color: #444 !important;
  border: 1px solid #d4d4d4 !important;
  border-radius: 1px !important;
  font-size: 11px;
  font-weight: 400;
  line-height: 1.4;
  padding: 2px 6px !important;
  margin: 2px 0 !important;
  max-width: calc(100% - 14px);  /* sisakan ruang di kanan */
  box-sizing: border-box;
}

/* teks di dalam item detail tetap inline */
.order-search-card .ts-wrapper.single .ts-control .ts-item-detail{
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;                       /* samakan dengan chip */
}

/* tombol X mirip yang di gambar: garis vertikal + X kecil */
.order-search-card .ts-wrapper.single .ts-control .remove{
  display: inline-block !important;
  margin-left: 6px;
  padding-left: 6px;
  width: auto;
  height: auto;
  border-left: 1px solid #d4d4d4;        /* garis pemisah */
  border-radius: 0;                      /* tanpa bulat */
  text-decoration: none !important;
  color: #666 !important;                /* X abu tua */
  opacity: 1;
  font-size: 11px;
  line-height: 1;
  background: transparent !important;
}

/* input di dalam control supaya placeholder & teks kecil juga */
.order-search-card .ts-wrapper.single .ts-control input{
  font-size: 11px;
}


.order-search-card .ts-wrapper .ts-control:focus,
.order-search-card .ts-wrapper.focus .ts-control,
.order-search-card .ts-wrapper.is-focused .ts-control,
.order-search-card .ts-wrapper.input-active .ts-control,
.order-search-card .ts-wrapper.dropdown-active .ts-control,
.order-search-card .ts-wrapper:focus-within .ts-control{
  box-shadow:none !important;
  border-color:#03c8c6 !important;
}

.order-search-card .ts-wrapper.has-items .ts-control input{ display:none; }

.ts-wrapper.single .ts-control{ min-height:44px; padding:8px 10px; }
.ts-dropdown{ max-height:320px; overflow:auto; }

/* DETAIL OPTION STYLE */
.order-search-card .ts-dropdown .search-opt{
  display:block;              /* vertikal, bukan 2 kolom */
}

.search-opt-main{
  flex:1;
  min-width:0;
  margin-bottom:2px;
}

.search-opt-line{
  display:flex;
  flex-wrap:wrap;
  gap:4px 6px;
  align-items:center;
  margin-bottom:2px;
}

.search-opt-line-alamat{
  font-size:11px;
  white-space:normal;
}

.search-opt-pill{
  display:inline-flex;
  align-items:center;
  padding:2px 7px;
  border-radius:999px;
  font-size:11px;
  border:1px solid #d1d5db;
  background:#f3f4f6;
  color:#111827;
  max-width:100%;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}

.pill-sc{
  border-color:#bfdbfe;
  background:#eff6ff;
  font-weight:600;
}
.pill-qr{
  border-color:#fecaca;
  background:#fef2f2;
  color:#b91c1c;
}

/* teks biasa untuk No Internet / No Voip (tanpa border dan background) */
.search-opt-voip-text{
  font-size:11px;
  color:#0f172a;
}

/* baris nama + cp */
.search-opt-label{
  font-size:12px;
  font-weight:600;
  color:#0f172a;
  max-width:200px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}

.search-opt-meta{
  font-size:11px;
  color:#6b7280;
}

/* alamat full */
.search-opt-sub{
  font-size:11px;
  color:#6b7280;
  max-width:100%;
  white-space:normal;
  overflow:visible;
  text-overflow:unset;
}

/* baris foto: full width satu baris sendiri */
.search-opt-thumb-wrap{
  margin-top:4px;
  display:flex;
  flex-wrap:wrap;
  gap:4px;
}

.search-opt-thumb{
  display:block;
  width:40px;
  height:32px;
  object-fit:cover;
  border-radius:4px;
  border:1px solid #e5e7eb;
  box-shadow:0 0 0 1px rgba(148,163,184,.25);
  cursor:pointer; /* zoom in feel */
}

/* selected item khusus detail */
.ts-item-detail{
  display:inline-flex;
  align-items:center;
  gap:4px;
}

/* footer notes */
.search-footer-notes{
  margin-top:16px;
  padding-top:10px;
  border-top:1px dashed #e5e7eb;
  display:flex;
  flex-wrap:wrap;
  gap:14px;
  font-size:.78rem;
  color:#6b7280;
}
.search-footer-note{
  flex:1 1 260px;
}
.search-footer-note-label{
  font-weight:600;
  color:#047479;
}

/* teks untuk opsi filter No Internet / No Voip (bukan pill) */
.search-opt-voip-line{
  font-size:12px;
  color:#0f172a;
}
</style>

<div class="search-shell">
  <div class="search-section-label">Form Pencarian Order</div>

  <div class="order-search-card">
    <!-- HEADER -->
    <div class="order-search-header">
      <div class="order-search-header-main">
        <div class="order-search-header-icon">
          <i class="mdi mdi-magnify"></i>
        </div>
        <div>
          <div class="order-search-title">Order Search</div>
          <div class="order-search-subtitle">
            Cari data Order secara cepat berdasarkan nomor, pelanggan, atau titik koordinat.
          </div>
        </div>
      </div>
      <div class="order-search-pills">
        <div class="search-pill search-pill--accent">
          <i class="mdi mdi-lightbulb-on-outline"></i> Smart Lookup
        </div>
        <div class="search-pill">
          <i class="mdi mdi-filter-variant"></i> Multi Filter
        </div>
        <div class="search-pill">
          <i class="mdi mdi-database-search"></i> Realtime Index
        </div>
      </div>
    </div>

    <!-- BODY -->
    <div class="order-search-body">
      <!-- KIRI -->
      <div class="order-search-main">
        <div class="order-search-main-header">
          <div>
            <div class="order-search-main-title">Masukkan kata kunci pencarian</div>
            <div class="order-search-main-caption">
              Form Search akan langsung menampilkan data ketika Anda memilih salah satu filter.
            </div>
          </div>
          <div class="order-search-mode">
            <i class="mdi mdi-ray-start-arrow"></i> Single / Multi Search
          </div>
        </div>

        <form id="orderSearchFormS" method="get" action="the_searching.php">
          <div class="order-search-grid">
            <div class="order-search-field">
              <label for="searchMulti_s" class="form-label mb-1" style="font-size:.78rem;color:#6b7280;">
                Multi Select
              </label>
              <div id="leftCell"></div>
            </div>

            <div class="order-search-field">
              <label for="filter_s" class="form-label mb-1" style="font-size:.78rem;color:#6b7280;">
                Pilih Filter
              </label>
              <select id="filter_s" name="field" class="form-select">
                <option value=""               <?= $f===''?'selected':'' ?>>Semua Kolom</option>
                <option value="no_sc"          <?= $f==='no_sc'?'selected':'' ?>>No SC</option>
                <!-- menu No Internet DIHAPUS, digabung dengan No Internet / No Voip -->
                <option value="no_voip"        <?= $f==='no_voip'?'selected':'' ?>>No Internet / No Voip</option>
                <option value="nama_pelanggan" <?= $f==='nama_pelanggan'?'selected':'' ?>>Nama Pelanggan</option>
                <option value="alamat"         <?= $f==='alamat'?'selected':'' ?>>Alamat</option>
                <option value="cp_pelanggan"   <?= $f==='cp_pelanggan'?'selected':'' ?>>CP Pelanggan</option>
                <option value="qrcode"         <?= $f==='qrcode'?'selected':'' ?>>QR Code</option>
                <option value="detail"         <?= $f==='detail'?'selected':'' ?>>Detail</option>
              </select>
            </div>
          </div>

          <div class="order-search-footer-row">
            <div class="order-search-hint-inline">
              Mode <strong>Semua Kolom</strong> membutuhkan minimal <strong>2 karakter</strong> untuk memunculkan data.
            </div>
            <button type="submit" class="btn btn-cari">
              <i class="mdi mdi-magnify"></i> Find Search
            </button>
          </div>
        </form>

      </div>

      <!-- KANAN -->
      <aside class="order-search-aside">
        <div class="order-search-aside-title">
          <i class="mdi mdi-information-outline"></i>
          Panduan kata kunci
        </div>
        <div class="order-search-aside-sub">
          Sistem dapat menemukan Order berdasarkan beberapa jenis informasi berikut:
        </div>

        <ol class="help-list">
          <li>NO SC <span class="text-muted">[Contoh: AOI4250901105105667e002d0]</span></li>
          <li>No Internet / Voip <span class="text-muted">[Contoh: 152703264300]</span></li>
          <li>Nama Pelanggan <span class="text-muted">[Contoh: Muhammad Fulan]</span></li>
          <li>Alamat <span class="text-muted">[Contoh: Jl. Soekarno Hatta No. ...]</span></li>
          <li>CP Pelanggan <span class="text-muted">[Contoh: 082141681331]</span></li>
          <li>QR Code <span class="text-muted">[Contoh: kode unik QR]</span></li>
        </ol>

        <div class="order-search-aside-divider"></div>

        <div class="order-search-aside-meta-label">Status layanan</div>
        <div class="order-search-aside-meta-pill">
          <i class="mdi mdi-check-circle-outline"></i> Online
        </div>
        <div class="order-search-aside-meta-time">
          Terakhir diperbarui: <?= date('d M Y H:i') ?> WIB
        </div>
      </aside>
    </div>
  </div>

  <div class="search-footer-notes">
    <div class="search-footer-note">
      <div class="search-footer-note-label">Catatan pencarian</div>
      Sistem hanya menampilkan sejumlah data pada setiap permintaan.
      Gunakan filter yang lebih spesifik jika hasil terlalu banyak atau kurang relevan.
    </div>
    <div class="search-footer-note">
      <div class="search-footer-note-label">Tips</div>
      Untuk nama pelanggan, cukup ketik sebagian nama (misal: <em>“SIGURA”</em>)
      dan kombinasikan dengan filter yang tepat untuk mempercepat proses pencarian.
    </div>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById('orderSearchFormS');
  const fSel = document.getElementById('filter_s');
  const left = document.getElementById('leftCell');

  if (!form || !fSel || !left) return;

  const SEARCH_OPTIONS_ALL = <?= json_encode($searchOptionsAll, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  const SEARCH_BY_COL      = <?= json_encode($jsOptionsByCol,   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  const VOIP_ROWS          = <?= json_encode($voipPairs,        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  const DETAIL_ROWS        = <?= json_encode($detailRows,       JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  const SELECTED_CODES     = <?= json_encode($selectedCodes,    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  const FIELD_SELECTED_PHP = <?= json_encode($fieldSelected,    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

  if (!window.TomSelect) {
    const inp = document.createElement('input');
    inp.type = 'text';
    inp.className = 'form-control';
    inp.name = 'codes[]';
    inp.placeholder = "AOIXXXXX / 15XXXX / FULANXXX / ALAMAT / QR / 08XXXXXXXXXX";
    inp.value = (Array.isArray(SELECTED_CODES) && SELECTED_CODES.length ? SELECTED_CODES[0] : '');
    left.appendChild(inp);
    return;
  }

  const sel = document.createElement('select');
  sel.id = 'searchMulti_s';
  sel.name = 'codes[]';
  sel.multiple = true;
  sel.setAttribute('autocomplete','off');
  left.appendChild(sel);

  let currentMode = fSel.value || FIELD_SELECTED_PHP || 'all';
  if (!currentMode) currentMode = 'all';

  const ts = new TomSelect(sel, {
    plugins: ['remove_button'],
    create: false,
    persist: false,
    maxItems: 1,
    valueField: 'value',
    labelField: 'text',
    /* Pencarian DETAIL pakai semua field */
    searchField: [
      'text',
      'no_sc',
      'no_internet',
      'no_voip',
      'nama_pelanggan',
      'alamat',
      'cp_pelanggan',
      'qrcode',
      'search_all'
    ],
    placeholder: "Cari berdasarkan kata kunci pencarian...",
    closeOnSelect: false,
    render: {
      option: function (data, escape) {
        if (data.is_wait) {
          return (
            '<div class="ts-option ts-loading-option" data-selectable="false">' +
              '<span class="ts-spinner" aria-hidden="true"></span>' +
              '<span>' + escape(data.text) + '</span>' +
            '</div>'
          );
        }

        /* DETAIL: 4 baris penuh */
        if (data.kind === 'detail') {
          var sc      = escape(data.no_sc || '');
          var net     = escape(data.no_internet || '');
          var voip    = escape(data.no_voip || '');
          var nama    = escape(data.nama_pelanggan || '');
          var alamat  = escape(data.alamat || '');
          var cp      = escape(data.cp_pelanggan || '');
          var qr      = escape(data.qrcode || '');
          var thumbs  = Array.isArray(data.thumbs) ? data.thumbs : [];
          var thumb1  = data.thumb ? String(data.thumb) : '';

          var netLabel = '';
          if (net && voip) netLabel = net + ' – ' + voip;
          else if (net)   netLabel = net;
          else if (voip)  netLabel = voip;

          var html = '<div class="search-opt search-opt--detail">';
          html +=   '<div class="search-opt-main">';

          /* Baris 1: SC, No Internet/Voip (teks biasa), QR */
          html +=   '<div class="search-opt-line search-opt-line-top">';
          if (sc)       html += '<span class="search-opt-pill pill-sc">'  + sc       + '</span>';
          if (netLabel) html += '<span class="search-opt-voip-text">' + netLabel + '</span>';
          if (qr)       html += '<span class="search-opt-pill pill-qr">'  + qr       + '</span>';
          html +=   '</div>';

          /* Baris 2: Nama + CP */
          html +=   '<div class="search-opt-line">';
          if (nama) html += '<span class="search-opt-label">' + nama + '</span>';
          if (cp)   html += '<span class="search-opt-meta">CP: ' + cp + '</span>';
          html +=   '</div>';

          /* Baris 3: Alamat (full) */
          if (alamat) {
            html += '<div class="search-opt-line search-opt-line-alamat">';
            html +=   '<span class="search-opt-sub">' + alamat + '</span>';
            html += '</div>';
          }

          /* Baris 4: semua foto (full width) */
          if (thumbs.length || thumb1) {
            html += '<div class="search-opt-thumb-wrap">';
            if (thumbs.length) {
              thumbs.forEach(function(u){
                if (!u) return;
                html += '<img src="' + escape(u) +
                        '" alt="" class="search-opt-thumb" data-src="' +
                        escape(u) + '" loading="lazy">';
              });
            } else {
              html += '<img src="' + escape(thumb1) +
                      '" alt="" class="search-opt-thumb" data-src="' +
                      escape(thumb1) + '" loading="lazy">';
            }
            html += '</div>';
          }

          html +=   '</div>'; // search-opt-main
          html += '</div>';   // search-opt
          return html;
        }

        /* NO INTERNET / NO VOIP digabung, teks biasa */
        if (data.kind === 'voip') {
          var net2  = escape(data.no_internet || '');
          var voip2 = escape(data.no_voip || '');
          var labelParts = [];
          if (net2)  labelParts.push(net2);
          if (voip2) labelParts.push(voip2);
          var label = labelParts.join(' – ') || escape(data.text || data.value || '');
          return '<div class="search-opt search-opt--voip">' +
                   '<div class="search-opt-line">' +
                     '<span class="search-opt-voip-line">' + label + '</span>' +
                   '</div>' +
                 '</div>';
        }

        return '<div>' + escape(data.text || '') + '</div>';
      },
      /* selected item: khusus detail biar kelihatan semua */
      item: function(data, escape) {
        if (data.kind === 'detail') {
          var pieces = [];
          if (data.no_sc)          pieces.push(escape(data.no_sc));
          if (data.nama_pelanggan) pieces.push(escape(data.nama_pelanggan));
          var netShort = '';
          if (data.no_internet) netShort = escape(String(data.no_internet));
          if (!netShort && data.no_voip) netShort = escape(String(data.no_voip));
          if (netShort) pieces.push(netShort);
          if (data.alamat)       pieces.push(escape(data.alamat));
          var label = pieces.join(' · ');
          return '<div class="item ts-item-detail">' + label + '</div>';
        }
        if (data.kind === 'voip') {
          var parts = [];
          if (data.no_internet) parts.push(escape(data.no_internet));
          if (data.no_voip)     parts.push(escape(data.no_voip));
          var lbl = parts.join(' – ') || escape(data.text || data.value || '');
          return '<div class="item ts-item-detail">' + lbl + '</div>';
        }
        return '<div>' + escape(data.text || data.value || '') + '</div>';
      },
      no_results: function (data, escape) {
        if (!data.input || data.input.length < 2) {
          return '<div class="ts-empty-hint">Menunggu input… ketik minimal 2 karakter.</div>';
        }
        return '<div class="ts-empty-hint">Tidak ada hasil untuk "<strong>' +
                 escape(data.input) + '</strong>".</div>';
      }
    },
    onItemAdd: function () {
      this.setTextboxValue('');
    }
  });

  function showWait() {
    ts.clearOptions();
    ts.addOption({
      value: '__wait__',
      text: 'Menunggu input… ketik minimal 2 karakter.',
      is_wait: true
    });
    ts.refreshOptions(false);
  }

  function fillFromColumn(col){
    ts.clearOptions();

    if (col === 'detail') {
      if (Array.isArray(DETAIL_ROWS) && DETAIL_ROWS.length) {
        const opts = DETAIL_ROWS.map(function(r){
          const searchAll = [
            r.no_sc || '',
            r.no_internet || '',
            r.no_voip || '',
            r.nama_pelanggan || '',
            r.alamat || '',
            r.cp_pelanggan || '',
            r.qrcode || ''
          ].join(' ');
          return {
            kind: 'detail',
            value: String(r.no_sc || ''),
            text: String(r.no_sc || ''),
            no_sc: r.no_sc || '',
            no_internet: r.no_internet || '',
            no_voip: r.no_voip || '',
            nama_pelanggan: r.nama_pelanggan || '',
            alamat: r.alamat || '',
            cp_pelanggan: r.cp_pelanggan || '',
            qrcode: r.qrcode || '',
            thumbs: Array.isArray(r.thumbs) ? r.thumbs : [],
            thumb: r.thumb || '',
            search_all: searchAll
          };
        });
        ts.addOptions(opts);
      }
      ts.refreshOptions(false);
      return;
    }

    if (col === 'no_voip') {
      if (Array.isArray(VOIP_ROWS) && VOIP_ROWS.length) {
        const opts = VOIP_ROWS.map(function(r){
          const net  = r.no_internet || '';
          const voip = r.no_voip || '';
          const labelParts = [];
          if (net)  labelParts.push(net);
          if (voip) labelParts.push(voip);
          const combined = labelParts.join(' – ');
          const searchAll = [net, voip].join(' ');
          const primary = voip || net;
          return {
            kind: 'voip',
            value: String(primary),
            text: combined || String(primary),
            no_internet: net,
            no_voip: voip,
            search_all: searchAll
          };
        });
        ts.addOptions(opts);
      }
      ts.refreshOptions(false);
      return;
    }

    const list = SEARCH_BY_COL[col] || [];
    const opts = list.map(function(v){
      return { value: String(v), text: String(v) };
    });
    if (opts.length) {
      ts.addOptions(opts);
    }
    ts.refreshOptions(false);
  }

  function filterAllByQuery(query){
    const q = (query || '').toLowerCase().trim();

    if (!q || q.length < 2) {
      showWait();
      return;
    }

    const out = [];
    for (let i = 0; i < SEARCH_OPTIONS_ALL.length; i++) {
      const v = SEARCH_OPTIONS_ALL[i];
      if (!v) continue;
      const text = String(v);
      if (text.toLowerCase().indexOf(q) !== -1) {
        out.push({ value: text, text: text });
        if (out.length >= 200) break;
      }
    }

    ts.clearOptions();
    if (out.length) ts.addOptions(out);
    ts.refreshOptions(false);
  }

  function initOptionsFirstLoad(){
    const hasSelected = Array.isArray(SELECTED_CODES) && SELECTED_CODES.length > 0;

    if (hasSelected) {
      const v = String(SELECTED_CODES[0]);
      ts.addOption({ value: v, text: v });
      ts.setValue(v, true);
    }

    if (currentMode === 'all') {
      if (!hasSelected) showWait();
    } else {
      fillFromColumn(currentMode);
    }
  }

  initOptionsFirstLoad();

  ts.on('type', function(str){
    if (currentMode === 'all') {
      filterAllByQuery(str);
    }
  });

  fSel.addEventListener('change', function(){
    currentMode = this.value || 'all';
    ts.clear();
    ts.clearOptions();

    if (currentMode === 'all') {
      showWait();
    } else {
      fillFromColumn(currentMode);
    }
  });

})();

/* Modal preview foto: pakai mousedown capture supaya tidak dimakan TomSelect */
(function(){
  const modalEl = document.getElementById('searchPhotoModal');
  const imgEl   = document.getElementById('searchPhotoImg');
  let modalInst = null;

  function showModal(src){
    if (!modalEl || !imgEl || !src) return;
    imgEl.src = src;
    if (window.bootstrap && window.bootstrap.Modal) {
      if (!modalInst) modalInst = new bootstrap.Modal(modalEl);
      modalInst.show();
    } else if (window.jQuery) {
      window.jQuery(modalEl).modal('show');
    } else {
      modalEl.style.display = 'block';
    }
  }

  // pakai mousedown + capture: klik foto di dropdown tetap buka modal
  document.addEventListener('mousedown', function(ev){
    const thumb = ev.target.closest('.search-opt-thumb');
    if (!thumb) return;
    ev.preventDefault();
    ev.stopPropagation();
    const src = thumb.getAttribute('data-src') || thumb.getAttribute('src') || '';
    if (src) showModal(src);
  }, true);
})();
</script>

<!-- Modal foto dari dropdown DETAIL -->
<div class="modal fade" id="searchPhotoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:#020617;color:#e5e7eb;border-radius:10px;">
      <div class="modal-body p-0" style="display:flex;align-items:center;justify-content:center;min-height:280px;">
        <img id="searchPhotoImg" src="" alt="Preview Foto" style="max-width:100%;max-height:80vh;object-fit:contain;">
      </div>
    </div>
  </div>
</div>

<!-- ================== END SEARCH CARD ================== -->

          </div>
        </div>
      </div>
    </div>
  </div>

<?php
include "footer.php";
?>
