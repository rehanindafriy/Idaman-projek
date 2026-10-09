<?php
// thesearching_bot.php
// Hasil pencarian: ORDER + BAI + FOTO (untuk BOT WebApp).

// Paksa browser agar tidak menyimpan cache halaman ini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

// Potong teks kalau panjangnya lebih dari $maxChar, lalu tambah "..."
if (!function_exists('es_truncate')) {
  function es_truncate($text, int $maxChar = 27): string {
    $text = trim((string)$text);
    if ($text === '') {
      return '';
    }

    // gunakan mb_ supaya aman UTF-8 (huruf Indonesia, simbol, dll)
    $len = mb_strlen($text, 'UTF-8');
    if ($len <= $maxChar) {
      return $text;
    }

    // ambil 27 karakter pertama, lalu tambahkan "..."
    $short = mb_substr($text, 0, $maxChar, 'UTF-8');
    return $short . '...';
  }
}

// ==== CEK ROLE: SIAPA YANG BOLEH SCREENSHOT ====
// akan set $canScreenshot = true hanya untuk admin & ta
// ==== CEK ROLE: SIAPA YANG BOLEH SCREENSHOT ====
// akan set $canScreenshot = true hanya untuk admin & ta

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$canScreenshot = true; // DIMATIKAN SEMENTARA SESUAI PERMINTAAN

if (!empty($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
  $roleSess = strtolower(trim((string)$_SESSION['user']['role']));
  if ($roleSess === 'admin' || $roleSess === 'ta') {
    $canScreenshot = true;
  }
}

// 1) PRIORITAS: ROLE DARI SESSION (LOGIN WEB BIASA)
if (!empty($_SESSION['role'])) {
  $roleSess = strtolower(trim((string)$_SESSION['role']));
  if ($roleSess === 'admin' || $roleSess === 'ta') {
    $canScreenshot = true;
  }
}

// 2) CEK VIA chat_id UNTUK MOBILE WEBVIEW / TELEGRAM WEBAPP
//    (blok di bawah ini tetap seperti kode asli Press)
$chatId = isset($_GET['chat_id']) ? trim((string)$_GET['chat_id']) : '';

if (!$canScreenshot && $chatId !== '') {
  try {
    $stRole = $pdo->prepare("
      SELECT al.role
      FROM akses_login al
      JOIN akses_bot ab
        ON ab.chat_id = al.chat_id
      WHERE al.chat_id   = :chat_id
        AND al.status    = 'active'
        AND ab.status    = 'active'
      LIMIT 1
    ");
    $stRole->execute([':chat_id' => $chatId]);

    if ($r = $stRole->fetch(PDO::FETCH_ASSOC)) {
      $role = strtolower(trim((string)$r['role']));
      if ($role === 'admin' || $role === 'ta') {
        $canScreenshot = true;
      }
    }
  } catch (Throwable $e) {
    // kalau mau debug:
    // file_put_contents(__DIR__.'/runtime-telegram.log', date('c').' SS-ROLE ERR '.$e->getMessage()."\n", FILE_APPEND);
  }
}


// ------- KONFIG PATH FILE -------
$BAI_FS_DIR    = __DIR__ . '/bot/file/';
$BAI_WEB_BASE  = 'bot/file/';

$FOTO_FS_DIR   = __DIR__ . '/bot/foto/';
$FOTO_WEB_BASE = 'bot/foto/';

// ------- BANTUAN KOLOM LAIN (untuk logika pencarian) -------

// baca daftar kolom orders sekali
$allOrderCols = [];
try {
  $res         = $pdo->query("SHOW COLUMNS FROM `orders`");
  $allOrderCols = $res->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $allOrderCols = [];
}

$hasAlamat = in_array('alamat', $allOrderCols, true);
$hasQrcode = in_array('qrcode', $allOrderCols, true);

// kolom no_voip (opsional)
$colVoip = null;
if (in_array('no_voip',    $allOrderCols, true)) {
  $colVoip = 'no_voip';
} elseif (in_array('no_voip_lama', $allOrderCols, true)) {
  $colVoip = 'no_voip_lama';
}

// util: cari kolom timestamp paling cocok
if (!function_exists('es_find_ts_col')) {
  function es_find_ts_col(PDO $pdo): ?string {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
      $st   = $pdo->query("SHOW COLUMNS FROM `orders`");
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $cache = null;
      return null;
    }

    $cols = array_map(static fn($r) => $r['Field'] ?? '', $rows);
    $cols = array_filter($cols, static fn($c) => $c !== '');

    $candidates = [
      'created_at','tanggal','tgl','tgl_input',
      'inserted_at','waktu','createdAt','ts','date'
    ];
    foreach ($candidates as $c) {
      if (in_array($c, $cols, true)) {
        $cache = $c;
        return $c;
      }
    }

    foreach (['id','id_order'] as $pk) {
      if (in_array($pk, $cols, true)) {
        $cache = $pk;
        return $pk;
      }
    }

    $cache = null;
    return null;
  }
}

$tsCol = es_find_ts_col($pdo);

// ------- PARAM PENCARIAN -------
// MODE BARU: multi-select dari search_bot.php => codes[]
$codes = [];
if (isset($_GET['codes']) && is_array($_GET['codes'])) {
  foreach ($_GET['codes'] as $c) {
    $c = trim((string)$c);
    if ($c === '') continue;
    $codes[] = $c;
  }
  $codes = array_values(array_unique($codes));
}

// MODE LAMA (fallback): ?q=&field=
$field = $_GET['field'] ?? 'all';
$qRaw  = trim((string)($_GET['q'] ?? ''));

$allowedFields = [
  'no_sc'          => 'no_sc',
  'no_internet'    => 'no_internet',
  'cp_pelanggan'   => 'cp_pelanggan',
  'nama_pelanggan' => 'nama_pelanggan',
  'alamat'         => 'alamat',
  'qrcode'         => 'qrcode',
  'no_voip'        => 'no_voip',       // gabungan no_internet + no_voip
  'detail'         => 'detail',    // special: pakai no_sc
  'sn_ont_type'    => 'sn_ont_type',
  'all'            => 'all',
];


if (!isset($allowedFields[$field])) {
  $field = 'all';
}

$hasCodes       = !empty($codes);
$hasQuerySingle = ($qRaw !== '' && mb_strlen($qRaw) >= 2);
$hasAnySearch   = $hasCodes || $hasQuerySingle;
$mode           = $hasCodes ? 'multi' : ($hasQuerySingle ? 'single' : 'none');

// ------- QUERY DB -------
$orders   = [];
$errorMsg = '';

if ($hasAnySearch) {
  $where  = ' WHERE 1=1 ';
  $params = [];

  if ($hasCodes) {
    // MODE BARU: multi-select exact match
    $vals = [];
    foreach ($codes as $v) {
      $v = trim((string)$v);
      if ($v === '') continue;
      $vals[] = $pdo->quote($v);
    }
    $vals = array_values(array_unique($vals));

    if ($vals) {
      $in = implode(',', $vals);

      switch ($field) {
        case 'no_sc':
        case 'detail': // detail → pakai no_sc
          $where .= " AND o.no_sc IN ($in)";
          break;

        case 'no_internet':
          $where .= " AND o.no_internet IN ($in)";
          break;

        case 'cp_pelanggan':
          $where .= " AND o.cp_pelanggan IN ($in)";
          break;

        case 'nama_pelanggan':
          $where .= " AND o.nama_pelanggan IN ($in)";
          break;

        case 'alamat':
          if ($hasAlamat) {
            $where .= " AND o.alamat IN ($in)";
          } else {
            $where .= " AND 0";
          }
          break;

        case 'qrcode':
          if ($hasQrcode) {
            $where .= " AND o.qrcode IN ($in)";
          } else {
            $where .= " AND 0";
          }
          break;

        case 'no_voip':
          if ($colVoip) {
            $where .= " AND ( o.no_internet IN ($in) OR o.`{$colVoip}` IN ($in) )";
          } else {
            $where .= " AND o.no_internet IN ($in)";
          }
          break;

        case 'sn_ont_type':
  $where .= " AND o.sn_ont_type IN ($in)";
  break;

        case 'all':
        default:
          $where .= " AND ( 
            o.no_sc IN ($in)
            OR o.no_internet IN ($in)
            " . ($colVoip   ? " OR o.`{$colVoip}` IN ($in)" : "") . "
            OR o.cp_pelanggan IN ($in)
            OR o.nama_pelanggan IN ($in)
            " . ($hasAlamat ? " OR o.alamat IN ($in)" : "") . "
            " . ($hasQrcode ? " OR o.qrcode IN ($in)" : "") . "
            " . ($hasSnOntType ? " OR o.sn_ont_type IN ($in)" : "") . "
          )";
          break;
      }
    } else {
      $where .= " AND 0";
    }
  } else {
    // MODE LAMA: q + field (LIKE)
    $like = '%' . $qRaw . '%';

    // kalau detail tapi pakai q bebas → anggap seperti all
    if ($field === 'detail') {
      $field = 'all';
    }

    if ($field === 'all') {
      $where .= " AND (
        o.no_sc LIKE :q
        OR o.no_internet LIKE :q
        " . ($colVoip   ? " OR o.`{$colVoip}` LIKE :q" : "") . "
        OR o.cp_pelanggan LIKE :q
        OR o.nama_pelanggan LIKE :q
        " . ($hasAlamat ? " OR o.alamat LIKE :q" : "") . "
        " . ($hasQrcode ? " OR o.qrcode LIKE :q" : "") . "
        " . ($hasSnOntType ? " OR o.sn_ont_type LIKE :q" : "") . "
      )";
      $params[':q'] = $like;
    } elseif ($field === 'no_voip') {
      if ($colVoip) {
        $where .= " AND ( o.no_internet LIKE :q OR o.`{$colVoip}` LIKE :q )";
      } else {
        $where .= " AND o.no_internet LIKE :q";
      }
      $params[':q'] = $like;
    } else {
      $col = $allowedFields[$field] ?? 'no_sc';
      $where .= " AND o.`{$col}` LIKE :q ";
      $params[':q'] = $like;
    }
  }
  $orderBy = '';
  if ($tsCol) {
    $orderBy = " ORDER BY o.`{$tsCol}` DESC, o.id_order DESC ";
  } else {
    $orderBy = " ORDER BY o.created_at DESC, o.id_order DESC ";
  }

  $sql = "
    SELECT
      o.*,

      b.id_bai        AS bai_join_id,
      b.kode_bai      AS bai_join_kode,
      b.nama_bai      AS bai_join_nama,
      b.uploader      AS bai_uploader,
      b.date_received AS bai_date,

      f.id_foto       AS foto_join_id,
      f.kode_foto     AS foto_join_kode,
      f.uploader      AS foto_uploader,
      f.date_received AS foto_date

    FROM orders o
    LEFT JOIN bai  b
      ON (b.kode_bai <> '' AND b.kode_bai = o.no_sc)
    LEFT JOIN foto f
      ON (f.kode_foto <> '' AND f.kode_foto = o.no_sc)
    {$where}
    {$orderBy}
    LIMIT 900000
  ";


  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $orders = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $ex) {
    $errorMsg = 'DB Error: ' . $ex->getMessage();
  }
}

// ------- BANTUAN KOLOM DETAIL -------
function label_col(string $name): string {
  $label = str_replace('_', ' ', strtolower($name));
  return ucwords($label);
}

$orderCols = [];
try {
  $res = $pdo->query("SHOW COLUMNS FROM orders");
  $orderCols = $res->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $orderCols = [];
}

// pilih kolom koordinat otomatis (kalau ada di tabel)
function pick_col(array $cols, array $cands): ?string {
  foreach ($cands as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return null;
}

$pelCoordCol = pick_col($orderCols, [
  'tag_pelanggan','taging_pelanggan','maps_pelanggan',
  'koordinat_pelanggan','koord_pelanggan','tagpelanggan'
]);
$odpCoordCol = pick_col($orderCols, [
  'tag_odp','taging_odp','maps_odp',
  'koordinat_odp','koord_odp','tagodp'
]);

// scan folder foto berdasarkan kode
function list_foto_files(string $kode_foto, string $fsBase): array {
  $files = [];
  if ($kode_foto === '') return $files;

  $dir = rtrim($fsBase, '/\\') . DIRECTORY_SEPARATOR . $kode_foto . DIRECTORY_SEPARATOR;
  if (!is_dir($dir)) return $files;

  $patterns = ['*.jpg','*.jpeg','*.png','*.webp','*.gif'];
  foreach ($patterns as $p) {
    foreach (glob($dir.$p) as $f) {
      if (is_file($f)) {
        $files[] = $f;
      }
    }
  }
  return $files;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Hasil Searching Data · EFIDI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
  >

  <style>
    :root{
      --es-teal:       #00a3ad;
      --es-teal-dark:  #00808b;
      --es-bg-page:    #ffffff;
      --es-card:       #ffffff;
      --es-border:     #e5e7eb;
      --es-text-main:  #111827;
      --es-text-muted: #3c3e40ff;
      --es-radius-sm:  6px;
      --es-radius-md:  10px;
      --es-radius-lg:  12px;
    }

    *{
      box-sizing:border-box;
      -webkit-tap-highlight-color: transparent;
    }

    html, body{
      margin:0;
      padding:0;
      background:var(--es-bg-page);
    }

    body{
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color:var(--es-text-main);
      min-height:100vh;
    }

    .shell{
      max-width:900px;
      margin:28px auto 24px;
      padding:24px 12px 24px;
    }

    /* HEADER ATAS */

    /* HEADER ATAS – disamakan dengan search_bot.php (app bar, tanpa card) */

.app-header{
  padding-bottom:10px;
  background:transparent;
}

/* HEADER BARUT: mirip app bar, hanya garis bawah tipis */
.header {
  background-color: transparent;
  border-radius: 0;
  padding: 4px 2px 8px;
  border: none;
  border-bottom: 1px solid rgba(148,163,184,0.35); /* garis tipis saja */
  display: flex;
  align-items: center;
  gap: 10px;
}

/* logo efidi di kiri */
.efidi-logo {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border-radius: 0;
  border: none;
  padding: 0;
}
.efidi-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

/* teks di kanan logo */
.header-text{
  flex:1;
  min-width:0;
}

/* baris judul + chip jumlah ORDER di kanan */
.header-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

/* judul besar */
.header-title{
  font-size:16px;
  font-weight:700;
  display:flex;
  align-items:center;
  gap:6px;
  color:#0f172a;
}

/* lingkar icon kecil seperti di search_bot.php */
.header-title-icon{
  width:20px;
  height:20px;
  border-radius:999px;
  background-color:#e5f3f5;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:15px;
  color:var(--es-teal-dark);
}

/* pill jumlah ORDER di kanan judul */
.header-count-pill{
  font-size:10px;
  text-transform:uppercase;
  letter-spacing:.10em;
  padding:3px 10px;
  border-radius:999px;
  background-color:#eff6ff;
  color:#1d4ed8;
  border:1px solid #bfdbfe;
  white-space:nowrap;
  font-weight:600;
}

/* subjudul kecil */
.header-subtitle{
  margin-top:2px;
  font-size:12px;
  color:var(--es-text-muted);
}

/* tag chip di bawah (MODE EFIDI) – diseragamkan dengan search_bot.php */
.tag-chip{
  margin-top:4px;
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:3px 10px;
  font-size:9.5px;
  text-transform:uppercase;
  letter-spacing:.12em;
  border-radius:999px;
  border:1px solid rgba(0,163,173,0.35);
  background-color:#f9fafb;
  color:#03606c;
}

.tag-dot{
  width:6px;
  height:6px;
  border-radius:999px;
  background-color:var(--es-teal);
}


    /* BACK LINK – teks saja, tanpa background/border */

    .back-link{
      margin-top:10px;
      display:inline-flex;
      align-items:center;
      gap:6px;
      font-size:12px;
      padding:0;
      border-radius:0;
      background:transparent;
      color:#03606c;
      text-decoration:none;
      border:none;
    }
    .back-link .mdi{
      font-size:17px;
    }
    .back-link:hover{
      text-decoration:underline;
    }

    /* ALERT INFO PENCARIAN (TOSCA) */

    .search-info{
      margin:10px 0 -10px;
      padding:10px 12px;
      border-radius:14px;
      border:1px solid #bae6ec;
      background:#e5f6f7;
      color:#0f172a;
      font-size:11px;
      display:flex;
      align-items:flex-start;
      gap:8px;
    }
    .search-info-icon{
      width:24px;
      height:24px;
      border-radius:999px;
      background:#00a3ad;
      display:flex;
      align-items:center;
      justify-content:center;
      flex-shrink:0;
      color:#ecfeff;
      font-size:16px;
    }
    .search-info-body{
      flex:1;
      min-width:0;
    }
    .search-info-title{
      font-size:12px;
      font-weight:600;
      margin-bottom:2px;
      color:#0f172a;
    }
    .search-info-text{
      line-height:1.4;
    }
    .search-info b{
      color:#0f172a;
    }
    .search-info.bad{
      display:block;
      background:#fef2f2;
      border-color:#fecaca;
      color:#7f1d1d;
    }

       /* ORDER WRAPPER – kartu ringkas mirip e-IPO */
    .order-card{
      margin:16px 0 20px;
      padding:0;
      border:none;
      background:transparent;
    }

    .order-top{
      padding:6px 0 0;
      margin-bottom:12px;
      border-bottom:none;
    }

    .order-top-bar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-bottom:6px;
    }

    .order-top-label{
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.14em;
      color:#9ca3af;
      font-weight:600;
    }

    /* Kartu ringkas ala e-IPO */
    .order-summary-card{
      border-radius:16px;                 /* border radius mirip kartu e-IPO */
      border:1px solid #e5e7eb;
      background:#ffffff;                 /* tanpa background warna lain */
      padding:10px 12px 12px;
    }

    .order-summary-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-bottom:8px;
    }

    .order-summary-left{
      display:flex;
      align-items:center;
      gap:10px;
      min-width:0;
    }

    /* logo bulat kiri – TANPA border, warna accent pindah ke icon */
.order-summary-logo{
  width:40px;
  height:40px;
  border-radius:999px;
  display:flex;
  align-items:center;
  justify-content:center;

  /* BACKGROUND PUTIH */
  background:#ffffff;

  /* BORDER SANGAT TIPIS, AGAK KEPUTIHAN (SAMAR) */
  border:1px solid rgba(238, 241, 245, 0.9); /* #e2e8f0 samar */

  /* ICON BIRU */
  color: #00a3ad;

  flex-shrink:0;
}

.order-summary-logo i{
  font-size:22px; /* biar icon tetap sama seperti sebelumnya */
}



    .order-summary-main{
      display:flex;
      flex-direction:column;
      gap:2px;
      min-width:0;
    }
    /* SUPA → Nama Pelanggan */
    .order-summary-name{
      font-size:12px;
      font-weight:600;
      color:#111827;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    /* PT Super Bank… → No SC */
    .order-summary-sub{
      font-size:11px;
      color:#3c3e40ff;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    .order-summary-right{
      text-align:right;
      flex-shrink:0;
    }
    /* Rp635 → ORDER TYPE (PSB, dsb) */
    .order-summary-type{
      font-size:13px;
      font-weight:600;
      color:#111827;
    }

    /* Baris “Penawaran Umum / Status Pesanan” */
    .order-summary-row{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-top:4px;
      font-size:12px;
      
    }
    
    .order-summary-row + .order-summary-row{
  margin-top:10px;   /* atur angka sesuai selera: 8, 10, 12, dll */
}


    .order-summary-row-label{
      color: #3c3e40ff;
    }

    .order-summary-row-value{
      color:#111827;
    }

    /* “Disetujui” → CP Pelanggan (biru sama seperti di gambar) */
    .order-summary-row-value-primary{
      color:#1d4ed8;                      /* warna teks CP pelanggan */
      display:inline-flex;
      align-items:center;
      font-size:11px;
      gap:4px;
      font-weight:500;
    }
    .order-summary-row-value-primary .mdi{
      font-size:12px;
    }

   /* jarak tombol dari CP → diturunkan sedikit */
.order-summary-footer{
  margin-top:14px;              /* tadinya 8px */
}

/* tombol Export PDF – lebih pendek */
.order-top-export-btn{
  border-radius:999px;
  border:1px solid #16a34a;
  background: #f4fffaff;
  padding:3px 11px;             /* vertical padding diperkecil */
  font-size:10.5px;
  font-weight:500;
  display:inline-flex;
  align-items:center;
  gap:6px;
  color:#166534;
  cursor:pointer;
}
.order-top-export-btn .mdi{
  font-size:13px;               /* icon sedikit lebih kecil */
}

    .order-top-export-btn:hover{
      background:#dcfce7;
    }

    /* PALET WARNA ACAK UNTUK LOGO SETIAP ORDER */
    .order-palette-1{
      --order-logo-bg:#dcfce7;
      --order-logo-ring:#16a34a;
      --order-logo-icon:#064e3b;
    }
    .order-palette-2{
      --order-logo-bg:#e0f2fe;
      --order-logo-ring:#3b82f6;
      --order-logo-icon:#1e3a8a;
    }
    .order-palette-3{
      --order-logo-bg:#fee2e2;
      --order-logo-ring:#ef4444;
      --order-logo-icon:#7f1d1d;
    }
    .order-palette-4{
      --order-logo-bg:#fef3c7;
      --order-logo-ring:#f59e0b;
      --order-logo-icon:#78350f;
    }
    .order-palette-5{
      --order-logo-bg:#f5f3ff;
      --order-logo-ring:#8b5cf6;
      --order-logo-icon:#4c1d95;
    }
    .order-palette-6{
      --order-logo-bg:#ecfeff;
      --order-logo-ring:#06b6d4;
      --order-logo-icon:#0f172a;
    }

    /* SECTION GROUP (biarkan seperti semula) */
    .section-group{
      margin-top:14px;
    }
    .section-heading{
      font-size:13px;
      font-weight:600;
      color:#111827;
      margin-bottom:6px;
    }
    .section-card{
      border-radius:14px;
      border:1px solid #e5e7eb;
      background:#ffffff;
      padding:10px 12px 12px;
    }
    .section-card-status .order-status{
      margin-top:0;
    }

    /* SECTION GROUP */

    .section-group{
      margin-top:14px;
    }
    .section-heading{
      font-size:13px;
      font-weight:600;
      color:#111827;
      margin-bottom:6px;
    }
    .section-card{
      border-radius:14px;
      border:1px solid #e5e7eb;
      background:#ffffff;
      padding:10px 12px 12px;
    }
    .section-card-status .order-status{
      margin-top:0;
    }

    /* STATUS PEKERJAAN */

    .order-status{
      padding-top:0;
      margin-top:0;
      border-top:none;
    }
    .order-status-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-bottom:4px;
    }
    .order-status-title{
      font-size:13px;
      font-weight:600;
      color:#111827;
    }
    .order-status-badge{
      padding:3px 10px;
      border-radius:999px;
      font-size:11px;
      border:1px solid transparent;
      white-space:nowrap;
    }
    .badge-ok{
      background:#f4fffaff;
      color:#166534;
      border-color:#16a34a;
    }
    .badge-warn{
      background:#fef2f2;
      color:#7f1d1d;
      border-color:#fecaca;
    }
    
    /* ALERT DOKUMEN (di bawah langkah ke-3) */
.doc-alert{
  margin-top:8px;
  padding:9px 10px;
  border-radius:12px;
  border:1px solid #bfdbfe;
  background:#eff6ff;            /* nuansa biru lembut mirip e-IPO */
  font-size:11px;
  display:flex;
  align-items:flex-start;
  gap:8px;
}

.doc-alert-icon{
  width:22px;
  height:22px;
  border-radius:999px;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
  font-size:14px;
}

/* teks di dalam alert */
.doc-alert-body{
  flex:1;
  min-width:0;
}
.doc-alert-title{
  font-size:12px;
  font-weight:600;
  margin-bottom:2px;
  color:#0f172a;
}
.doc-alert-text{
  color:#4b5563;
  line-height:1.4;
}

/* varian sukses & warning */
.doc-alert-ok{
  background:#eff6ff;
  border-color:#bfdbfe;
  color: #061231ff;
}
.doc-alert-ok .doc-alert-icon{
  background:#1d4ed8;
  color:#e0ecff
}

.doc-alert-warn{
  background:#eff6ff;
  border-color:#bfdbfe;
  color:#061231ff;
}
.doc-alert-warn .doc-alert-icon{
  background:#1d4ed8;
  color:#e0ecff;
}


        /* STATUS PEKERJAAN – TIMELINE MIRIP E-IPO */

    .status-steps{
      list-style:none;
      margin:10px 0 2px;
      padding:0;
      position:relative;
    }

    .status-step{
      position:relative;
      padding-left:32px;
      padding-top:12px;
      padding-bottom:12px;
    }
    .status-step + .status-step{
      margin-top:0;
    }

    /* Garis vertikal penghubung antar titik */
    .status-step::before{
      content:'';
      position:absolute;
      left:13px;             /* tepat di tengah lingkaran (40px lebar area kiri) */
      top:0;
      bottom:0;
      width:2px;
      background:#e5e7eb;    /* abu-abu muda */
      z-index:0;
    }
    .status-step:first-child::before{
      top:50%;               /* garis mulai dari tengah titik atas ke bawah */
    }
    .status-step:last-child::before{
      bottom:50%;            /* garis berhenti di tengah titik bawah */
    }

    /* Titik status */
    .status-step-circle{
      position:absolute;
      left:4px;
      top:50%;
      transform:translateY(-50%);
      width:18px;
      height:18px;
      border-radius:999px;
      border:2px solid #d1d5db;
      background:#ffffff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:11px;
      font-weight:700;
      color:#ffffff;
      box-shadow:0 0 0 2px #ffffff;
      z-index:1;             /* supaya di atas garis */
    }

    /* default: belum selesai → lingkaran abu dengan titik kecil di tengah */
    .status-step-circle::after{
      content:'';
      width:6px;
      height:6px;
      border-radius:999px;
      background:#d1d5db;
    }

    /* step selesai → lingkaran biru dengan ikon centang putih */
    .status-step-done .status-step-circle{
      background:#2563eb;
      border-color:#2563eb;
      color:#ffffff;
    }
    .status-step-done .status-step-circle::after{
      content:'\2713';       /* ✓ */
      width:auto;
      height:auto;
      border-radius:0;
      background:transparent;
      font-size:11px;
      line-height:1;
    }

    /* supaya pending tetap hanya lingkaran abu + titik tengah */
    .status-step-pending .status-step-circle{
      background:#ffffff;
      border-color:#d1d5db;
    }


    .status-step-body{
      flex:1;
      min-width:0;
    }

    .status-step-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-bottom:2px;
    }

    .status-step-title{
      font-size:13px;
      font-weight:600;
      color:#111827;
    }
    .status-step-desc{
      font-size:12px;
      color:#6b7280;
    }
    .status-step-meta{
      font-size:11px;
      color:#9ca3af;
      margin-top:2px;
    }

    .status-step-action-btn{
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      padding:4px 10px;
      font-size:11px;
      font-weight:500;
      display:inline-flex;
      align-items:center;
      gap:4px;
      color:#111827;
    }
    
    .status-step-action-btn .mdi{
      font-size:14px;
      color:#c90e0e;
    }
    /* Tombol Download Semua Foto (icon hijau) */
.status-step-action-btn-photo .mdi{
  color:#0f766e;
}

    .status-step-action-btn:disabled{
      cursor:default;
      color:#9ca3af;
      background:#f9fafb;
      border-color:#e5e7eb;
      opacity:0.8;
    }
    .status-step-action-btn:not(:disabled){
      cursor:pointer;
    }
    .status-step-action-btn:not(:disabled):hover{
      background:#e0f2fe;
      border-color:#bae6fd;
    }

    /* DATA LOKASI & DOKUMEN */

    .order-meta-grid{
      display:grid;
      grid-template-columns:minmax(0,1.1fr) minmax(0,1.1fr);
      gap:12px;
      margin-top:6px;
    }
    .order-meta-grid.order-meta-grid--single{
      grid-template-columns:minmax(0,1fr);
    }
    @media (max-width:720px){
      .order-meta-grid{
        grid-template-columns:minmax(0,1fr);
      }
    }

    .meta-card{ padding:0; }

    .meta-header{
      display:flex;
      align-items:center;
      gap:6px;
      font-size:12px;
      font-weight:600;
      margin-bottom:4px;
      color:#111827;
    }
    .meta-header .mdi{
      font-size:17px;
      color:var(--es-teal);
    }

    .meta-rows{
      display:flex;
      flex-direction:column;
      gap:6px;
      font-size:12px;
    }
    .meta-row{
      display:flex;
      justify-content:space-between;
      gap:6px;
      flex-wrap:wrap;
    }
    .meta-label{
      font-weight:500;
      color:#6b7280;
    }
    .meta-value{
      display:flex;
      flex-wrap:wrap;
      justify-content:flex-end;
      gap:4px;
    }

    .meta-chip,
    .meta-tag{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:3px 10px;
      border-radius:999px;
      font-size:11px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      color:#111827;
    }
    .meta-tag .mdi,
    .meta-chip .mdi{
      font-size:14px;
    }

    .meta-chip-bai{
      background:#eff6ff;
      border-color:#dbeafe;
      color:#1e40af;
    }
    .meta-chip-foto{
      background:#f9fafb;
      border-color:#e5e7eb;
      color:#111827;
    }
    .meta-chip-empty{
      background:#f9fafb;
      border-color:#e5e7eb;
      color:#9ca3af;
    }

    .meta-tag a{
      color:#03606c;
      text-decoration:none;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
    }
    .meta-tag a:hover{
      text-decoration:underline;
    }

    /* FOTO THUMB */

    .foto-thumbs{
      display:flex;
      flex-wrap:wrap;
      gap:6px;
      margin-top:8px;
      margin-bottom:4px;
    }
    .foto-thumbs a{
      display:inline-block;
      border-radius:10px;
      overflow:hidden;
      border:1px solid #e5e7eb;
      width:64px;
      height:52px;
      flex-shrink:0;
      cursor:pointer;
      background:#0f172a;
    }
    .foto-thumbs img{
      width:100%;
      height:100%;
      object-fit:cover;
      display:block;
      transition:transform .18s ease, opacity .18s ease;
    }
    .foto-thumbs a:hover img{
      transform:scale(1.03);
      opacity:0.96;
    }

    /* DESKRIPSI ORDER (TABEL) */

    .order-detail-table{
      width:100%;
      border-collapse:collapse;
      border-radius:16px;
      overflow:hidden;
      background:#ffffff;
      border:1px solid #e5e7eb;
      font-size:12px;
    }
    .order-detail-table th,
    .order-detail-table td{
      padding:9px 12px;
      border-bottom:1px solid #edf2f7;
    }
    .order-detail-table tr:last-child th,
    .order-detail-table tr:last-child td{
      border-bottom:none;
    }
    .order-detail-table th{
      width:40%;
      text-align:left;
      color:#6b7280;
      font-weight:500;
      white-space:nowrap;
      background:transparent;
    }
    .order-detail-table td{
      color:#111827;
      word-break:break-word;
      background:#ffffff;
      font-weight:400;
    }

    /* MAPS */

    .map-panel{
      margin-top:4px;
      padding-top:0;
      border-top:none;
    }
    .map-panel-body{
      margin-top:4px;
    }
    .map-canvas{
      width:100%;
      height:230px;
      border-radius:12px;
      border:1px solid #e5e7eb;
      overflow:hidden;
    }

    @media (max-width:768px){
      .shell{
        padding:10px 10px 18px;
      }
      .section-card{
        padding:9px 10px 11px;
      }
    }
    
        /* TOMBOL VIEW DI KOLOM KOORDINAT (TAG PELANGGAN / TAG ODP) */
        /* TOMBOL VIEW DI KOLOM KOORDINAT (TAG PELANGGAN / TAG ODP) */
    .coord-view-btn{
      margin-left:6px;
      display:inline-flex;
      align-items:center;
      gap:4px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#ffffff;
      padding:2px 8px;
      font-size:10px;
      font-weight:500;
      color:#03606c;
      cursor:pointer;
      white-space:nowrap;
    }
    .coord-view-btn .mdi{
      font-size:13px;
    }
    .coord-view-btn:hover{
      background:#f9fafb;
    }

    /* HEADING + TOMBOL SALIN DATA ORDER */
    .section-heading-row{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-bottom:6px;
    }
    .section-heading-row .section-heading{
      margin-bottom:0;
    }
    .copy-order-btn{
      flex-shrink:0;
      display:inline-flex;
      align-items:center;
      gap:5px;
      border-radius:999px;
      border:1px solid var(--es-teal, #00a3ad);
      background:#ffffff;
      padding:4px 11px;
      font-size:11px;
      font-weight:600;
      color:var(--es-teal-dark, #00808b);
      cursor:pointer;
      white-space:nowrap;
      transition:background-color .12s ease-out, transform .08s ease-out;
    }
    .copy-order-btn .mdi{
      font-size:14px;
    }
    .copy-order-btn:hover{
      background:#e5f6f7;
    }
    .copy-order-btn:active{
      transform:translateY(1px);
    }
    .copy-order-btn.is-copied{
      border-color:#16a34a;
      color:#15803d;
      background:#f0fdf4;
    }

    /* MODAL KOORDINAT: RUTE / MAPS / WAZE / STREET */
    .coord-modal{
      position:fixed;
      inset:0;
      background:rgba(15,23,42,0.55);
      display:none;
      align-items:center;
      justify-content:center;
      z-index:10000;
      padding:14px;
    }
    .coord-modal.is-open{
      display:flex;
    }
    .coord-modal-inner{
      width:min(420px,100%);
      background:#ffffff;
      border-radius:16px;
      box-shadow:0 22px 60px rgba(15,23,42,0.45);
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }
    .coord-modal-header{
      padding:10px 12px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      border-bottom:1px solid #e5e7eb;
      background:#ffffff;
    }
    .coord-modal-title{
      display:flex;
      flex-direction:column;
      gap:2px;
      font-size:13px;
      color:#111827;
    }
    .coord-modal-title-main{
      font-weight:600;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
    .coord-modal-title-sub{
      font-size:12px;
      color:#6b7280;
    }
    .coord-modal-close{
      border:none;
      background:#f9fafb;
      border-radius:999px;
      width:26px;
      height:26px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
    }
    .coord-modal-close .mdi{
      font-size:16px;
      color:#4b5563;
    }
    .coord-modal-coord{
  display:none;
}

/* optional: sedikit styling untuk label & koordinat di header */
.coord-modal-title-sub .coord-label{
  font-weight:500;
}

.coord-modal-title-sub .coord-value{
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size:11px;
  color:#111827;
}

    .coord-modal-tabs{
      display:flex;
      background:#ffffff;
      border-bottom:1px solid #f3f4f6;
    }
    .coord-modal-tab{
      flex:1;
      padding:8px 0;
      font-size:12px;
      font-weight:500;
      border:none;
      background:#ffffff;
      cursor:pointer;
      border-right:1px solid #f3f4f6; /* garis pemisah antar menu */
    }
    .coord-modal-tab:last-child{
      border-right:none;
    }
    .coord-modal-tab.is-active{
      font-weight:600;
      color:#03606c;
    }

    .coord-modal-body{
      padding:10px 12px 12px;
      background:#ffffff;
    }

    .coord-modal-frame-wrap{
      width:100%;
      height:260px;               /* tinggi peta di dalam modal */
      border-radius:12px;
      border:1px solid #e5e7eb;
      overflow:hidden;
      margin-bottom:8px;
      background:#f9fafb;
    }
    .coord-modal-frame-wrap iframe{
      width:100%;
      height:100%;
      border:0;
    }

    .coord-modal-hint{
      font-size:11px;
      color:#6b7280;
      line-height:1.4;
    }
    .coord-modal-hint a{
      color:#03606c;
      text-decoration:none;
    }
    .coord-modal-hint a:hover{
      text-decoration:underline;
    }
    /* MODAL EXPORT PDF – gaya mirip modal koordinat */
    .export-modal{
      position:fixed;
      inset:0;
      background:rgba(15,23,42,0.55);
      display:none;
      align-items:center;
      justify-content:center;
      z-index:10000;
      padding:14px;
    }
    .export-modal.is-open{
      display:flex;
    }
    .export-modal-inner{
      width:min(520px,100%);
      background:#ffffff;
      border-radius:16px;
      box-shadow:0 22px 60px rgba(15,23,42,0.45);
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }
    .export-modal-header{
      padding:10px 12px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      border-bottom:1px solid #e5e7eb;
      background:#ffffff;
    }
    .export-modal-title{
      display:flex;
      flex-direction:column;
      gap:2px;
      font-size:13px;
      color:#111827;
    }
    .export-modal-title-main{
      font-weight:600;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
    .export-modal-title-sub{
      font-size:12px;
      color:#6b7280;
    }
    .export-modal-close{
      border:none;
      background:#f9fafb;
      border-radius:999px;
      width:26px;
      height:26px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
    }
    .export-modal-close .mdi{
      font-size:16px;
      color:#4b5563;
    }

    .export-modal-tabs{
      display:flex;
      background:#ffffff;
      border-bottom:1px solid #f3f4f6;
    }
    .export-modal-tab{
      flex:1;
      padding:8px 0;
      font-size:12px;
      font-weight:500;
      border:none;
      background:#ffffff;
      cursor:pointer;
      border-right:1px solid #f3f4f6;
    }
    .export-modal-tab:last-child{
      border-right:none;
    }
    .export-modal-tab.is-active{
      font-weight:600;
      color:#03606c;
    }

    .export-modal-body{
      padding:10px 12px 12px;
      background:#ffffff;
    }
  /* FRAME PREVIEW / EXPORT */
.export-modal-frame-wrap{
  width:100%;
  height:260px;
  border-radius:12px;
  border:1px solid #e5e7eb;
  overflow:hidden;
  margin-bottom:8px;
  background:#f9fafb;
}
.export-modal-frame-wrap iframe{
  width:100%;
  height:100%;
  border:0;
  background:#ffffff;
}

    .export-modal-actions{
      display:flex;
      justify-content:flex-end;
      margin-bottom:6px;
    }
    .export-modal-primary{
      border-radius:999px;
      border:none;
      background:linear-gradient(135deg,#f9fafb,#f9fafb);
      border:1px solid #e5e7eb;
      color:#111827;
      font-size:11px;
      padding:5px 12px;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:6px;
      font-weight:600;
    }
    .export-modal-primary .mdi{
      font-size:14px;
    }

    .export-modal-hint{
      font-size:11px;
      color:#6b7280;
      line-height:1.4;
    }
    
        /* MODAL PILIH DOWNLOAD BAI (PDF / WORD) */
    .bai-dl-modal{
      position:fixed;
      inset:0;
      background:rgba(15,23,42,0.55);
      display:none;
      align-items:center;
      justify-content:center;
      z-index:10010;
      padding:14px;
    }
    .bai-dl-modal.is-open{
      display:flex;
    }
    .bai-dl-modal-inner{
      width:min(360px,100%);
      background:#ffffff;
      border-radius:16px;
      box-shadow:0 22px 60px rgba(15,23,42,0.45);
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }
    .bai-dl-modal-header{
      padding:10px 12px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      border-bottom:1px solid #e5e7eb;
      background:#ffffff;
    }
    .bai-dl-modal-title{
      display:flex;
      flex-direction:column;
      gap:2px;
      font-size:13px;
      color:#111827;
    }
    .bai-dl-modal-title-main{
      font-weight:600;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
    .bai-dl-modal-title-sub{
      font-size:12px;
      color:#6b7280;
    }
    .bai-dl-modal-close{
      border:none;
      background:#f9fafb;
      border-radius:999px;
      width:26px;
      height:26px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
    }
    .bai-dl-modal-close .mdi{
      font-size:16px;
      color:#4b5563;
    }
    .bai-dl-modal-body{
      padding:10px 12px 12px;
      background:#ffffff;
    }
    .bai-dl-modal-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:3px 10px;
      border-radius:999px;
      font-size:11px;
      background:#e5f6f7;
      border:1px solid #bae6ec;
      color:#0f172a;
      margin-bottom:8px;
    }
    .bai-dl-modal-chip .mdi{
  font-size:8px;
  width:14px;
  height:14px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:#00a3ad;   /* biru lebih tua sebagai background icon */
  color:#ecfeff;        /* icon jadi putih kebiruan */
}

    .bai-dl-modal-text{
      font-size:12px;
      color:#4b5563;
      margin-bottom:10px;
      line-height:1.5;
    }
    .bai-dl-modal-actions{
      display:flex;
      flex-direction:column;
      gap:8px;
    }
 .bai-dl-btn{
  border-radius:999px;
  border:1px solid transparent;  /* border tipis */
  padding:8px 12px;
  font-size:12px;
  font-weight:600;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  cursor:pointer;
}

/* PDF = merah lembut + border merah tipis */
.bai-dl-btn-pdf{
  background: #fff3f3ff;   /* merah halus */
  border-color: #ffe8e8ff; /* border tipis senada */
  color: #af1e1eff;        /* teks merah */
}

/* Word = biru lembut + border biru tipis */
.bai-dl-btn-word{
  background: #eff6ff;   /* biru halus */
  border-color: #dbeafe; /* border tipis senada */
  color:#1e40af;        /* teks biru */
}

/* icon ikut warna teks (merah/biru) */
.bai-dl-btn .mdi{
  font-size:16px;
  color:inherit;
}

        /* MODAL PILIH DOWNLOAD FOTO (ZIP / JPG) */
    .photo-dl-modal{
      position:fixed;
      inset:0;
      background:rgba(15,23,42,0.55);
      display:none;
      align-items:center;
      justify-content:center;
      z-index:10010;
      padding:14px;
    }
    .photo-dl-modal.is-open{
      display:flex;
    }
    .photo-dl-modal-inner{
      width:min(360px,100%);
      background:#ffffff;
      border-radius:16px;
      box-shadow:0 22px 60px rgba(15,23,42,0.45);
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }
    .photo-dl-modal-header{
      padding:10px 12px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      border-bottom:1px solid #e5e7eb;
      background:#ffffff;
    }
    .photo-dl-modal-title{
      display:flex;
      flex-direction:column;
      gap:2px;
      font-size:13px;
      color:#111827;
    }
    .photo-dl-modal-title-main{
      font-weight:600;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
    .photo-dl-modal-title-sub{
      font-size:12px;
      color:#6b7280;
    }
    .photo-dl-modal-close{
      border:none;
      background:#f9fafb;
      border-radius:999px;
      width:26px;
      height:26px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
    }
    .photo-dl-modal-close .mdi{
      font-size:16px;
      color:#4b5563;
    }
    .photo-dl-modal-body{
      padding:10px 12px 12px;
      background:#ffffff;
    }
    .photo-dl-modal-text{
      font-size:12px;
      color:#4b5563;
      margin-bottom:10px;
      line-height:1.5;
    }
    /* CHIP FOTO – SAMA KONSEP DENGAN CHIP BAI */
.photo-dl-modal-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:3px 10px;
  border-radius:999px;
  font-size:11px;
  background:#e5f6f7;          /* sama seperti chip BAI */
  border:1px solid #bae6ec;
  color:#0f172a;
  margin-bottom:8px;
}
.photo-dl-modal-chip .mdi{
  font-size:8px;
  width:14px;
  height:14px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:#00a3ad;          /* lingkar biru kecil, sama style dengan BAI */
  color:#ecfeff;
}

/* WRAPPER TOMBOL */
.photo-dl-modal-actions{
  display:flex;
  flex-direction:column;
  gap:8px;
}

/* TOMBOL DOWNLOAD FOTO – KONSEP SAMA DENGAN PDF / WORD:
   - ZIP  = arsip/pack  → oranye lembut
   - JPG  = file gambar → hijau lembut
   Keduanya pakai pill, border tipis, icon ikut warna teks (seperti BAI) */
.photo-dl-btn{
  border-radius:999px;
  border:1px solid transparent;
  padding:8px 12px;
  font-size:12px;
  font-weight:600;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  cursor:pointer;
}

/* ZIP = arsip (oranye lembut, formal) */
.photo-dl-btn-zip{
  background:#f9fafb;       /* orange-50 */
  border-color:#e5e7eb;     /* orange-200 */
  color:#111827;            /* orange-800 */
}

/* JPG = file gambar (hijau lembut, “aman”) */
.photo-dl-btn-jpg{
  background:#ecfdf5;       /* green-50 */
  border-color:#a7f3d0;     /* green-200 */
  color:#065f46;            /* green-800 */
}

/* icon ikut warna teks, sama konsepnya dengan tombol BAI */
.photo-dl-btn .mdi{
  font-size:16px;
  color:inherit;
}


.export-modal-share{
  margin-top:6px;
  padding:8px 9px;
  border-radius:12px;
  border:1px dashed #c7d2fe;
  background:#f9fafb;
  font-size:11px;
  color:#111827;
}
.export-modal-share-label{
  font-weight:600;
  margin-bottom:4px;
}
.export-modal-share-box{
  display:flex;
  gap:6px;
  margin-bottom:4px;
}
.export-modal-share-input{
  flex:1;
  min-width:0;
  border-radius:999px;
  border:1px solid #e5e7eb;
  padding:5px 10px;
  font-size:11px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  background:#ffffff;
  color:#111827;
}
.export-modal-share-copy{
  border-radius:999px;
  border:none;
  background:#0f172a;
  color:#e5e7eb;
  width:32px;
  height:32px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
}

/* ukuran icon */
.export-modal-share-copy .mdi{
  font-size:16px;
}

/* animasi spin lingkaran saat copy sukses */
@keyframes es-copy-spin{
  0%{
    transform:rotate(0deg) scale(1);
  }
  50%{
    transform:rotate(180deg) scale(1.1);
  }
  100%{
    transform:rotate(360deg) scale(1);
  }
}

/* ketika tombol sedang animasi (class .is-animating ditambahkan dari JS) */
.export-modal-share-copy.is-animating .mdi{
  animation:es-copy-spin 0.66s ease-in-out;
  animation-iteration-count:3; /* 0.66 x 3 ≈ 2 detik */
}

.export-modal-share-expire{
  font-size:10px;
  color:#6b7280;
}
#exportModalShareCountdown{
  font-weight:600;
  margin-left:4px;
  font-size:10px;
  color:#111827;
}




/* MODAL MEDIA: BIKIN PUTIH, MIRIP MODAL KOORDINAT / EXPORT */
.media-modal{
  position:fixed;
  inset:0;
  background:rgba(15,23,42,0.55);   /* overlay gelap sama seperti modal lain */
  display:none;
  align-items:center;
  justify-content:center;
  z-index:9999;
  padding:14px;
}
.media-modal.is-open{
  display:flex;
}

.media-modal-inner{
  width:min(960px,100%);
  max-height:100vh;
  background:#ffffff;               /* DULU #020617, sekarang putih */
  border-radius:16px;
  box-shadow:0 22px 60px rgba(15,23,42,0.45);
  display:flex;
  flex-direction:column;
  overflow:hidden;
}

/* HEADER PUTIH + BORDER BAWAH, MIRIP coord-modal-header/export-modal-header */
.media-modal-header{
  padding:10px 12px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:8px;
  background:#ffffff;               /* DULU gradient gelap */
  border-bottom:1px solid #e5e7eb;
  color:#111827;
  font-size:12px;
}

.media-modal-title{
  display:flex;
  align-items:center;
  gap:6px;
  min-width:0;
}
.media-modal-title .mdi{
  font-size:18px;
  color:#03606c;                    /* icon hijau/biru EFIDI */
}
.media-modal-title span.text{
  white-space:nowrap;
  text-overflow:ellipsis;
  overflow:hidden;
}

.media-modal-actions{
  display:flex;
  gap:6px;
}
.media-modal-btn{
  border-radius:999px;
  padding:4px 9px;
  font-size:11px;
  display:inline-flex;
  align-items:center;
  gap:4px;
  cursor:pointer;
  border:1px solid #e5e7eb;
  background:#f9fafb;
  color:#111827;
}
.media-modal-btn-close{
  background:#fef2f2;
  border-color:#fecaca;
  color:#7f1d1d;
}
.media-modal-btn-close .mdi{
  font-size:14px;
}

/* BODY PUTIH JUGA */
.media-modal-body{
  background:#ffffff;               /* DULU #020617 */
  padding:10px 12px 12px;
  display:flex;
  justify-content:center;
  align-items:center;
}

/* Gambar & PDF tetap, tapi shadow dibikin lebih soft */
.media-modal-body img{
  max-width:100%;
  max-height:80vh;
  border-radius:8px;
  box-shadow:0 18px 45px rgba(15,23,42,0.25);
}
.media-modal-body iframe{
  width:100%;
  height:80vh;
  border:0;
  background:#ffffff;
  border-radius:8px;
}


    .popup-btn{
      border:none;
      border-radius:999px;
      padding:2px 8px;
      font-size:10px;
      background:#eff6ff;
      color:#0369a1;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      white-space:nowrap;
    }
    .popup-btn:hover{
      background:#dbeafe;
    }
    .popup-btn-ghost{
      background:#f9fafb;
      color:#4b5563;
    }
    .popup-btn-ghost:hover{
      background:#e5e7eb;
    }

    #ssGuardOverlay {
      position: fixed;
      inset: 0;
      background: #020617;
      color: #e5e7eb;
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 99999;
      font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont,
                   "Segoe UI", Roboto, sans-serif;
      padding: 18px;
    }
    #ssGuardOverlay.is-active {
      display: flex;
    }
    #ssGuardOverlay .ss-guard-inner {
      text-align: center;
      max-width: 420px;
      padding: 18px 20px;
      border-radius: 16px;
      background: radial-gradient(circle at 0 0,#1e293b,#020617);
      border:1px solid rgba(148,163,184,0.6);
      box-shadow:0 22px 60px rgba(15,23,42,0.9);
    }
    #ssGuardOverlay .ss-guard-title {
      font-size: 15px;
      font-weight: 700;
      letter-spacing: .16em;
      text-transform: uppercase;
      margin-bottom: 8px;
      color: #f9fafb;
    }
    #ssGuardOverlay .ss-guard-message {
      font-size: 13px;
      margin-bottom: 6px;
      color: #e5e7eb;
    }
    #ssGuardOverlay .ss-guard-hint {
      font-size: 11px;
      color: #9ca3af;
    }

    body[data-can-screenshot="0"].screen-dark .shell {
      filter: blur(2px);
      opacity: 0.2;
    }
  </style>

</head>
<body
  data-can-screenshot="<?= $canScreenshot ? '1' : '0' ?>"
  <?php if (!empty($chatId)): ?>
    data-chat-id="<?= e($chatId) ?>"
  <?php endif; ?>
>

   <div class="shell">
    <div class="app-header">
      <!-- HEADER BARU: sama gaya dengan search_bot.php -->
      <div class="header">
        <div class="efidi-logo">
          <img src="images/logoo.png" alt="EFIDI">
        </div>
        <div class="header-text">
          <div class="header-title-row">
            <div class="header-title">
              <span class="header-title-icon">
                <i class="mdi mdi-file-search-outline"></i>
              </span>
              <span>Hasil pencarian data</span>
            </div>
            <div class="header-count-pill">
              <?= $hasAnySearch ? (count($orders) . ' ORDER' ) : '0 ORDER' ?>
            </div>
          </div>
          <div class="header-subtitle">
            Detail <b>Order</b> beserta <b>Berita Acara Instalasi</b> &
            <b>foto lapangan</b>
          </div>
          <div class="tag-chip">
            <span class="tag-dot"></span>
            <span>
              EFIDI · DETAIL SEARCH<?= $mode === 'multi' ? ' · MULTI SELECT' : ' · SEARCH BEBAS' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- back link tetap seperti sebelumnya -->
      <a href="search_bot.php<?= $chatId !== '' ? '?chat_id=' . urlencode($chatId) : '' ?>" class="back-link">
        <i class="mdi mdi-arrow-left"></i>
        <span>Kembali ke halaman Searching Data</span>
      </a>


      <?php if ($errorMsg): ?>
        <div class="search-info bad">
          <?= e($errorMsg) ?>
        </div>
      <?php elseif (!$hasAnySearch): ?>
        <div class="search-info bad">
          Gunakan halaman <b>Searching Data</b> untuk memilih minimal <b>1 kode</b>
          (multi select) atau isi kata kunci minimal <b>2 karakter</b> dengan parameter <code>q</code>.
        </div>
      <?php else: ?>
        <?php if ($hasCodes): ?>
          <div class="search-info search-info--teal">
            <div class="search-info-icon">
              <i class="mdi mdi-checkbox-multiple-marked-circle-outline"></i>
            </div>
            <div class="search-info-body">
           
              <div class="search-info-text">
                 Mode Multi Select <b>aktif</b> · Kode dipilih:
                <b><?= e(implode(', ', $codes)) ?></b> ·
                ditemukan <b><?= count($orders) ?></b> ORDER.
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="search-info search-info--teal">
            <div class="search-info-icon">
              <i class="mdi mdi-magnify"></i>
            </div>
            <div class="search-info-body">
              <div class="search-info-title">Pencarian data</div>
              <div class="search-info-text">
                Mode <b>Search Bebas</b> · Hasil untuk
                <b><?= e($qRaw) ?></b>
                pada kolom
                <b><?= e($field === 'all' ? 'Semua kolom' : strtoupper($field)) ?></b> ·
                ditemukan <b><?= count($orders) ?></b> ORDER.
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php
    if ($hasAnySearch && !$errorMsg):
      if (!$orders):
    ?>
      <div class="search-info bad">
        Tidak ada data ORDER yang cocok dengan input pencarian.
      </div>
    <?php
          else:
      $index = 0;

      // palette warna random untuk logo di kartu order
      $accentClasses = [
        'order-palette-1',
        'order-palette-2',
        'order-palette-3',
        'order-palette-4',
        'order-palette-5',
        'order-palette-6',
      ];

        foreach ($orders as $row):
          $index++;
          $id_order       = (int)($row['id_order'] ?? 0);
          $no_sc          = trim((string)($row['no_sc'] ?? ''));
          $no_internet    = trim((string)($row['no_internet'] ?? ''));
          $nama_pelanggan = trim((string)($row['nama_pelanggan'] ?? ''));
          $cp_pelanggan   = trim((string)($row['cp_pelanggan'] ?? ''));
          $order_type     = trim((string)($row['order_type'] ?? ''));

          // Kode BAI
          $kode_bai_join  = trim((string)($row['bai_join_kode'] ?? ''));
          $kode_bai_order = trim((string)($row['kode_bai'] ?? ''));
          $kode_bai       = $kode_bai_join !== '' ? $kode_bai_join :
                           ($kode_bai_order !== '' ? $kode_bai_order : $no_sc);

          // File BAI
          $bai_file = trim((string)($row['bai_join_nama'] ?? ''));
          $baiUrl   = '';
          if ($bai_file !== '') {
            $baiFs = rtrim($BAI_FS_DIR, '/\\') . DIRECTORY_SEPARATOR . $bai_file;
            if (is_file($baiFs)) {
              $baiUrl = $BAI_WEB_BASE . rawurlencode($bai_file);
            }
          }

          // Kode FOTO
          $kode_foto_join  = trim((string)($row['foto_join_kode'] ?? ''));
          $kode_foto_order = trim((string)($row['kode_foto'] ?? ''));
          $kode_foto       = $kode_foto_join !== '' ? $kode_foto_join :
                             ($kode_foto_order !== '' ? $kode_foto_order : $no_sc);

          // koordinat
          $pelCoord = ($pelCoordCol && isset($row[$pelCoordCol])) ? trim((string)$row[$pelCoordCol]) : '';
          $odpCoord = ($odpCoordCol && isset($row[$odpCoordCol])) ? trim((string)$row[$odpCoordCol]) : '';

          $hasCoord = ($pelCoord !== '' || $odpCoord !== '');

          // list foto
          $fotoFiles = $kode_foto !== '' ? list_foto_files($kode_foto, $FOTO_FS_DIR) : [];

          // status pekerjaan
          // status pekerjaan
$hasBai  = ($baiUrl !== '');
$hasFoto = !empty($fotoFiles);

// HANYA 2 STATUS: Data Lengkap / Data Tidak Lengkap
$allComplete      = ($hasBai && $hasFoto);
$statusBadgeLabel = $allComplete ? 'Data Lengkap' : 'Data Tidak Lengkap';
$statusBadgeClass = $allComplete ? 'badge-ok' : 'badge-warn';

// DETAIL UNTUK ALERT DI BAWAH LANGKAH KE-3
$docAlertType  = $allComplete ? 'ok' : 'warn';
$docAlertTitle = $allComplete
  ? 'Dokumen pendukung lengkap'
  : 'Dokumen pendukung belum lengkap';

if ($allComplete) {
  $docAlertText = 'File Berita Acara Instalasi dan foto dokumentasi lapangan sudah tersedia untuk order ini.';
} else {
  $missing = [];
  if (!$hasBai)  { $missing[] = 'file Berita Acara Instalasi (BAI)'; }
  if (!$hasFoto) { $missing[] = 'foto dokumentasi lapangan'; }

  if ($missing) {
    $docAlertText = 'Bagian yang belum tersedia: ' . implode(' dan ', $missing) . '.';
  } else {
    $docAlertText = '';
  }
}
          // pilih kombinasi warna random buat logo kartu ini
          $accentClass = $accentClasses[array_rand($accentClasses)];



          $createdAtRaw = trim((string)($row['created_at'] ?? ''));
          $createdAtLbl = $createdAtRaw;
          if ($createdAtRaw !== '') {
            $ts = strtotime($createdAtRaw);
            if ($ts !== false) {
              $createdAtLbl = date('d M Y H:i:s', $ts);
            }
          }
    ?>
          <div class="order-card">

        <!-- HEADER ORDER: kartu ringkas ala e-IPO -->
        <div class="order-top">
         

          <div class="order-summary-card <?= $accentClass ?>">
            <div class="order-summary-top">
              <div class="order-summary-left">
                <!-- logo bulat dengan warna acak + icon -->
                <div class="order-summary-logo">
                  <i class="mdi mdi-file-document-multiple-outline"></i>
                </div>

                <div class="order-summary-main">
                  <!-- SUPA → Nama pelanggan -->
                  <div class="order-summary-name">
                    <?= e($nama_pelanggan ?: '-') ?>
                  </div>
                  <!-- PT Super Bank… → No SC (tanpa label) -->
                  <div class="order-summary-sub">
                    <?= e(es_truncate($no_sc ?: '-')) ?>
                  </div>
                </div>
              </div>

              <!-- Rp635 → ORDER TYPE (PSB/Dsb) -->
              <div class="order-summary-right">
                <div class="order-summary-type">
                  <?= e($order_type ?: '-') ?>
                </div>
              </div>
            </div>

            <!-- Penawaran Umum → No Internet -->
            <div class="order-summary-row">
              <div class="order-summary-row-label">No Internet</div>
              <div class="order-summary-row-value">
                <?= e($no_internet ?: '-') ?>
              </div>
            </div>

            <!-- Status Pesanan / Disetujui → CP Pelanggan / data CP -->
            <div class="order-summary-row">
              <div class="order-summary-row-label">CP Pelanggan</div>
              <div class="order-summary-row-value order-summary-row-value-primary">
                <i class="mdi mdi-check"></i>
                <span><?= e($cp_pelanggan ?: '-') ?></span>
              </div>
            </div>

            <!-- Waktu Beli Terbaik → tombol Export PDF -->
            <div class="order-summary-footer">
              <button type="button"
                      class="order-top-export-btn"
                      data-order-id="<?= $id_order ?>"
                      data-order-sc="<?= e($no_sc ?: '-') ?>">
                <i class="mdi mdi-file-export-outline"></i>
                <span>Share / Export PDF</span>
              </button>
            </div>
          </div>




          </div>
        </div>

        <!-- STATUS PEKERJAAN -->
        <div class="section-group">
          <div class="section-heading">Status Pekerjaan</div>
          <div class="section-card section-card-status">
            <div class="order-status">
              <div class="order-status-header">
                <div class="order-status-title">Progres order</div>
                <div class="order-status-badge <?= $statusBadgeClass ?>">
                  <?= e($statusBadgeLabel) ?>
                </div>
              </div>
            <ol class="status-steps">
  <li class="status-step status-step-done">
    <div class="status-step-circle"></div>
    <div class="status-step-body">
      <div class="status-step-header">
        <div class="status-step-title">Order Masuk</div>
      </div>
      <div class="status-step-desc">
        Order terdaftar di sistem EFIDI.
      </div>
      <?php if ($createdAtLbl): ?>
        <div class="status-step-meta">
          <?= e($createdAtLbl) ?> WIB
        </div>
      <?php endif; ?>
    </div>
  </li>

   <li class="status-step <?= $hasBai ? 'status-step-done' : 'status-step-pending' ?>">
    <div class="status-step-circle"></div>
    <div class="status-step-body">
      <div class="status-step-header">
        <div class="status-step-title">Berita Acara Instalasi</div>
    <button type="button"
        class="status-step-action-btn status-step-action-btn-bai"
        <?= $hasBai ? '' : 'disabled' ?>
        data-bai-pdf="<?= e($baiUrl) ?>"
        data-bai-file="<?= e($bai_file) ?>"
        data-bai-kode="<?= e($kode_bai) ?>"
        data-ss-disabled="1">
  <i class="mdi mdi-file-pdf"></i>
  <span>Download</span>
</button>

      </div>

      <div class="status-step-desc">
        <?php if ($hasBai): ?>
          File Berita Acara Instalasi tersedia.
        <?php else: ?>
          File Berita Acara Instalasi tidak tersedia.
        <?php endif; ?>
      </div>
    </div>
  </li>

     <li class="status-step <?= $hasFoto ? 'status-step-done' : 'status-step-pending' ?>">
    <div class="status-step-circle"></div>
    <div class="status-step-body">
      <div class="status-step-header">
        <div class="status-step-title">Dokumentasi Lapangan</div>

        <!-- TOMBOL DOWNLOAD SEMUA FOTO -->
        <button type="button"
                class="status-step-action-btn status-step-action-btn-photo"
                <?= $hasFoto ? '' : 'disabled' ?>
                data-kode-foto="<?= e($kode_foto) ?>"
                data-ss-disabled="1">
          <i class="mdi mdi-image-multiple-outline"></i>
          <span>Download</span>
        </button>
      </div>

      <div class="status-step-desc">
        <?php if ($hasFoto): ?>
          <?= count($fotoFiles) ?> foto tersimpan di galeri.
        <?php else: ?>
          Belum ada foto dokumentasi lapangan.
        <?php endif; ?>
      </div>
    </div>
  </li>

</ol>

<?php if (!empty($docAlertText)): ?>
  <div class="doc-alert doc-alert-<?= e($docAlertType) ?>">
    <div class="doc-alert-icon">
      <?php if ($docAlertType === 'ok'): ?>
        <i class="mdi mdi-check-circle-outline"></i>
      <?php else: ?>
        <i class="mdi mdi-alert-circle-outline"></i>
      <?php endif; ?>
    </div>
    <div class="doc-alert-body">
      <div class="doc-alert-title"><?= e($docAlertTitle) ?></div>
      <div class="doc-alert-text"><?= e($docAlertText) ?></div>
    </div>
  </div>
<?php endif; ?>


            </div>
          </div>
        </div>

        <!-- DATA LOKASI & DOKUMEN -->
        <div class="section-group">
          <div class="section-heading">Data lokasi &amp; dokumen</div>
          <div class="section-card">
            <div class="order-meta-grid<?= $hasCoord ? '' : ' order-meta-grid--single' ?>">

              <?php if ($hasCoord): ?>
              <div class="meta-card">
                <div class="meta-header">
                  <i class="mdi mdi-crosshairs-gps"></i>
                  <span>Koordinat Pelanggan &amp; ODP</span>
                </div>
                <div class="meta-rows">
                  <div class="meta-row">
                    <span class="meta-label">Pelanggan</span>
                    <div class="meta-value">
                    <?php if ($pelCoord): ?>
  <?php $pelUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($pelCoord); ?>
  <span class="meta-tag">
    <i class="mdi mdi-map-marker-radius-outline"></i>
    <a
      href="<?= e($pelUrl) ?>"
      target="_blank"
      class="coord-scroll-link"
      data-scroll-target="coord-pel-<?= $id_order ?>"
      data-coord-label="Pelanggan"
      data-coord-value="<?= e($pelCoord) ?>"
    ><?= e($pelCoord) ?></a>
  </span>
<?php else: ?>

                        <span class="meta-chip meta-chip-empty">
                          <span>-</span>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="meta-row">
                    <span class="meta-label">ODP</span>
                    <div class="meta-value">
                      <?php if ($odpCoord): ?>
  <?php $odpUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($odpCoord); ?>
  <span class="meta-tag">
    <i class="mdi mdi-access-point"></i>
    <a
      href="<?= e($odpUrl) ?>"
      target="_blank"
      class="coord-scroll-link"
      data-scroll-target="coord-odp-<?= $id_order ?>"
      data-coord-label="ODP"
      data-coord-value="<?= e($odpCoord) ?>"
    ><?= e($odpCoord) ?></a>
  </span>
<?php else: ?>

                        <span class="meta-chip meta-chip-empty">
                          <span>-</span>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <div class="meta-card">
                <div class="meta-header">
                  <i class="mdi mdi-file-document-multiple-outline"></i>
                  <span>Berita Acara &amp; Foto Lapangan</span>
                </div>
                <div class="meta-rows">
                  <div class="meta-row">
                    <span class="meta-label">BAI</span>
                    <div class="meta-value">
                      <?php if ($baiUrl !== ''): ?>
                        <a href="<?= e($baiUrl) ?>"
                           class="meta-chip meta-chip-bai"
                           data-modal-type="pdf"
                           data-modal-src="<?= e($baiUrl) ?>"
                           data-pdf-file="<?= e($baiUrl) ?>"
                           data-modal-title="File BAI · <?= e($kode_bai) ?>">
                          <i class="mdi mdi-file-pdf"></i>
                          <span><?= e($kode_bai) ?></span>
                        </a>
                      <?php else: ?>
                        <span class="meta-chip meta-chip-empty">
                          <i class="mdi mdi-file-remove-outline"></i>
                          <span>Tidak tersedia</span>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="meta-row">
                    <span class="meta-label">Foto Lapangan</span>
                    <div class="meta-value">
                      <?php if ($kode_foto !== '' && $fotoFiles): ?>
                        <span class="meta-chip meta-chip-foto">
                          <i class="mdi mdi-image-multiple"></i>
                          <span><?= e($kode_foto) ?> · <?= count($fotoFiles) ?> file</span>
                        </span>
                      <?php else: ?>
                        <span class="meta-chip meta-chip-empty">
                          <i class="mdi mdi-image-off-outline"></i>
                          <span>Tidak tersedia</span>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($kode_foto !== '' && $fotoFiles): ?>
              <div class="foto-thumbs">
                <?php foreach ($fotoFiles as $ff):
                  $basename = basename($ff);
                  $webPath  = $FOTO_WEB_BASE . rawurlencode($kode_foto) . '/' . rawurlencode($basename);
                ?>
                  <a href="<?= e($webPath) ?>"
                     data-modal-type="image"
                     data-modal-src="<?= e($webPath) ?>"
                     data-modal-title="Foto · <?= e($kode_foto) ?>">
                    <img src="<?= e($webPath) ?>" alt="">
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- DESKRIPSI DATA ORDER -->
               <div class="section-group">
          <div class="section-heading-row">
            <div class="section-heading">Deskripsi data ORDER</div>
            <button type="button"
                    class="copy-order-btn"
                    data-order-id="<?= $id_order ?>"
                    data-order-title="<?= e($no_sc ?: '-') ?>">
              <i class="mdi mdi-content-copy"></i>
              <span>Salin Data Order</span>
            </button>
          </div>
          <div class="section-card">
            <?php if ($orderCols): ?>
              <table class="order-detail-table">
                <?php foreach ($orderCols as $c): ?>
                  <?php
                    $valRaw  = $row[$c] ?? '';
                    $valStr  = trim((string)$valRaw);
                    $valDisp = ($valStr === '') ? '-' : $valStr;

                    // Deteksi kolom koordinat pelanggan / ODP
                    $isPelCell = ($pelCoordCol && $c === $pelCoordCol && $pelCoord !== '');
                    $isOdpCell = ($odpCoordCol && $c === $odpCoordCol && $odpCoord !== '');

                    $labelCoord   = $isPelCell ? 'Pelanggan' : ($isOdpCell ? 'ODP' : '');
                    $coordForCell = $isPelCell ? $pelCoord : ($isOdpCell ? $odpCoord : '');
                  ?>
                 <tr
  <?php if ($isPelCell): ?>
    id="coord-pel-<?= $id_order ?>"
  <?php elseif ($isOdpCell): ?>
    id="coord-odp-<?= $id_order ?>"
  <?php endif; ?>
>
  <th><?= e(label_col($c)) ?></th>
  <td>
    <?php if ($labelCoord && $coordForCell): ?>
      <span><?= e($coordForCell) ?></span>
      <button type="button"
              class="coord-view-btn"
              data-coord-label="<?= e($labelCoord) ?>"
              data-coord-value="<?= e($coordForCell) ?>">
        <i class="mdi mdi-arrow-top-right"></i>
        <span>View</span>
      </button>
    <?php else: ?>
      <?= e($valDisp) ?>
    <?php endif; ?>
  </td>
</tr>

                <?php endforeach; ?>
              </table>
            <?php else: ?>
              <div style="font-size:11px;color:#6b7280;padding:6px 2px;">
                Kolom <code>orders</code> tidak bisa dibaca (SHOW COLUMNS gagal),
                tetapi data utama sudah tampil di header di atas.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- MAPS PELANGGAN & ODP -->
       

      </div>
    <?php
        endforeach;
      endif;
    endif;
    ?>
  </div>
  
    <!-- MODAL KOORDINAT: RUTE / MAPS / WAZE / STREET -->
  <!-- MODAL KOORDINAT: RUTE / MAPS / WAZE / STREET -->
  <div class="coord-modal" id="coordModal">
    <div class="coord-modal-inner">
      <div class="coord-modal-header">
        <div class="coord-modal-title">
          <span class="coord-modal-title-main">Pilihan navigasi</span>
          <span class="coord-modal-title-sub" id="coordModalLabel"></span>
        </div>
        <button type="button" class="coord-modal-close" id="coordModalClose">
          <i class="mdi mdi-close"></i>
        </button>
      </div>
      <div class="coord-modal-coord" id="coordModalCoord"></div>
      <div class="coord-modal-tabs">
        <button type="button" class="coord-modal-tab is-active" data-link-type="route">Rute</button>
        <button type="button" class="coord-modal-tab" data-link-type="maps">Maps</button>
        <button type="button" class="coord-modal-tab" data-link-type="waze">Waze</button>
        <button type="button" class="coord-modal-tab" data-link-type="street">Street</button>
      </div>
      <div class="coord-modal-body">
        <div class="coord-modal-frame-wrap">
         <iframe id="coordModalFrame"
        src="about:blank"
        loading="lazy"
        allowfullscreen
        referrerpolicy="no-referrer-when-downgrade"></iframe>

        </div>
        <div class="coord-modal-hint" id="coordModalHint">
          Pilih salah satu menu di atas untuk melihat tampilan peta bawaan Google Maps / Waze di dalam modal ini.
        </div>
      </div>
    </div>
  </div>
<script>
  // Modal koordinat: RUTE / MAPS / WAZE / STREET
  (function () {
    var modal    = document.getElementById('coordModal');
    if (!modal) return;

    var labelEl  = document.getElementById('coordModalLabel');
    var coordEl  = document.getElementById('coordModalCoord');
    var frameEl  = document.getElementById('coordModalFrame');
    var hintEl   = document.getElementById('coordModalHint');
    var btnClose = document.getElementById('coordModalClose');
    var tabs     = Array.prototype.slice.call(
      modal.querySelectorAll('.coord-modal-tab')
    );

    var urlSet      = null;   // {coordText, embed:{...}, open:{...}}
    var currentType = 'route';

    // parsing koordinat: "lat,lon" atau "lat lon"
    function parseCoord(str) {
      if (!str) return null;
      var m = String(str).match(/-?\d+(?:\.\d+)?/g);
      if (!m || m.length < 2) return null;
      var lat = parseFloat(m[0]);
      var lng = parseFloat(m[1]);
      if (isNaN(lat) || isNaN(lng)) return null;
      return { lat: lat, lng: lng };
    }

    // URL embed yang "boleh" di iframe
    function buildUrls(coordStr) {
      var p = parseCoord(coordStr);
      if (!p) return null;

      var coordText = p.lat.toFixed(6) + ',' + p.lng.toFixed(6);
      var q         = encodeURIComponent(coordText);

      // Street View: pakai pola yang sama dengan halaman web utama (output=svembed)
      var streetEmbed =
        'https://www.google.com/maps?q=' + coordText +
        '&layer=c&cbll=' + coordText +
        '&cbp=11,0,0,0,0&hl=id&output=svembed';

      // Peta biasa untuk MAPS
      var mapsEmbed =
        'https://www.google.com/maps?q=' + coordText +
        '&z=16&output=embed';

      // Untuk tab RUTE kita juga pakai peta biasa;
      // rute lengkap akan dibuka di aplikasi / tab baru via link cadangan.
      var routeEmbed = mapsEmbed;

      return {
        coordText: coordText,
        embed: {
          route : routeEmbed,
          maps  : mapsEmbed,
          waze  : 'https://embed.waze.com/iframe?ll=' + coordText + '&zoom=16&pin=1',
          street: streetEmbed
        },
        // link cadangan kalau user mau buka di aplikasi / tab baru
        open: {
          route : 'https://www.google.com/maps/dir/?api=1&destination=' + q,
          maps  : 'https://www.google.com/maps/search/?api=1&query=' + q,
          waze  : 'https://waze.com/ul?ll=' + coordText + '&navigate=yes',
          street: 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' + q
        }
      };
    }

    function setActiveTab(type) {
      currentType = type;

      tabs.forEach(function (t) {
        t.classList.toggle('is-active', t.getAttribute('data-link-type') === type);
      });

      if (!urlSet || !urlSet.embed[type]) {
        frameEl.src = 'about:blank';
        hintEl.textContent = 'Koordinat tidak valid.';
        return;
      }

      frameEl.src = urlSet.embed[type];

      var label;
      if (type === 'route') {
        label = 'Rute (peta tujuan – petunjuk arah lengkap di aplikasi Google Maps)';
      } else if (type === 'maps') {
        label = 'Maps (peta standar Google Maps, zoom sedang)';
      } else if (type === 'waze') {
        label = 'Waze (peta Waze)';
      } else {
        label = 'Street View (Google Street View)';
      }

      var fallbackUrl = urlSet.open[type];

      // teks bantuan di bawah iframe
      hintEl.innerHTML =
        label +
        '. Jika tampilan tidak muncul atau ingin membuka langsung di aplikasi, ' +
        '<a href="' + fallbackUrl + '" target="_blank" rel="noopener">buka di aplikasi / tab baru</a>.';
    }

   function openModal(label, coordStr) {
  var urls = buildUrls(coordStr);
  if (!urls) return;

  urlSet = urls;

  var safeLabel = label || '';
  var safeCoord = urls.coordText || '';

  if (safeLabel && safeCoord) {
    // contoh: "Pelanggan · -6.123456,106.123456"
    labelEl.innerHTML =
      '<span class="coord-label">' + safeLabel + '</span>' +
      '<span class="coord-sep">&nbsp;·&nbsp;</span>' +
      '<span class="coord-value">' + safeCoord + '</span>';
  } else if (safeLabel) {
    labelEl.textContent = safeLabel;
  } else if (safeCoord) {
    labelEl.textContent = safeCoord;
  } else {
    labelEl.textContent = '';
  }

  // elemen coord di bawah header tidak dipakai lagi
  coordEl.textContent = '';

  // default: tetap buka tab Rute (kalau mau lebih ringan, boleh diganti 'maps')
  setActiveTab('route');

  modal.classList.add('is-open');
}


    function closeModal() {
      modal.classList.remove('is-open');
      if (frameEl) {
        frameEl.src = 'about:blank';
      }
    }
    
     window.esCoordOpen = openModal;

    if (btnClose) {
      btnClose.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) {
        closeModal();
      }
    });

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var type = tab.getAttribute('data-link-type');
        if (!type) return;
        setActiveTab(type);
      });
    });

    // Listener global untuk tombol View di tabel Deskripsi data ORDER
    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.coord-view-btn');
      if (!btn) return;

      ev.preventDefault();

      var label = btn.getAttribute('data-coord-label') || '';
      var val   = btn.getAttribute('data-coord-value') || '';

      if (!val) return;
      openModal(label, val);
    });

    // ===== TOMBOL "SALIN DATA ORDER" (section Deskripsi data ORDER) =====
    function collectOrderDescriptionText(sectionGroup) {
      var table = sectionGroup.querySelector('.order-detail-table');
      if (!table) return '';

      var lines = [];
      var rows  = table.querySelectorAll('tr');

      rows.forEach(function (tr) {
        var th = tr.querySelector('th');
        var td = tr.querySelector('td');
        if (!th || !td) return;

        var label = th.textContent.replace(/\s+/g, ' ').trim();
        var value;

        var coordBtn = td.querySelector('.coord-view-btn');
        if (coordBtn) {
          value = coordBtn.getAttribute('data-coord-value') || '';
        } else {
          value = td.textContent.replace(/\s+/g, ' ').trim();
        }

        if (label === '') return;
        lines.push(label + ': ' + value);
      });

      return lines.join('\n');
    }

    function fallbackCopyText(text) {
      var ok = false;
      try {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity  = '0';
        document.body.appendChild(ta);
        ta.select();
        ok = document.execCommand('copy');
        document.body.removeChild(ta);
      } catch (e) {
        ok = false;
      }
      return ok;
    }

    function showCopyFeedback(btn, success) {
      var original = btn.getAttribute('data-original-html');
      if (!original) {
        original = btn.innerHTML;
        btn.setAttribute('data-original-html', original);
      }

      btn.innerHTML = success
        ? '<i class="mdi mdi-check"></i><span>Tersalin</span>'
        : '<i class="mdi mdi-alert-circle-outline"></i><span>Gagal salin</span>';

      btn.classList.toggle('is-copied', success);

      setTimeout(function () {
        btn.innerHTML = original;
        btn.classList.remove('is-copied');
      }, 1600);
    }

    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.copy-order-btn');
      if (!btn) return;

      ev.preventDefault();

      var sectionGroup = btn.closest('.section-group');
      var text = sectionGroup ? collectOrderDescriptionText(sectionGroup) : '';

      if (!text) {
        showCopyFeedback(btn, false);
        return;
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
          .then(function () { showCopyFeedback(btn, true); })
          .catch(function () { showCopyFeedback(btn, fallbackCopyText(text)); });
      } else {
        showCopyFeedback(btn, fallbackCopyText(text));
      }
    });
  })();
</script>
<script>
  (function () {
    document.addEventListener('click', function (ev) {
      var link = ev.target.closest('.coord-scroll-link');
      if (!link) return;

      // kalau user Ctrl+Click / Cmd+Click / middle-click → biarkan buka tab baru Maps
      if (ev.ctrlKey || ev.metaKey || ev.button === 1) {
        return;
      }

      var targetId = link.getAttribute('data-scroll-target') || '';
      var label    = link.getAttribute('data-coord-label') || '';
      var coord    = link.getAttribute('data-coord-value') || '';

      if (!targetId || !coord) return;

      ev.preventDefault();

      var targetEl = document.getElementById(targetId);
      if (targetEl && typeof targetEl.scrollIntoView === 'function') {
        targetEl.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });
      }

      // buka modal sedikit setelah scroll, biar kelihatan gerakan scroll-nya
      if (window.esCoordOpen) {
        setTimeout(function () {
          window.esCoordOpen(label, coord);
        }, 380); // boleh diatur 300–400ms
      }
    });
  })();
</script>
  <!-- MODAL EXPORT PDF: PREVIEW & EXPORT -->
  <div class="export-modal" id="exportModal">
    <div class="export-modal-inner">
      <div class="export-modal-header">
        <div class="export-modal-title">
          <span class="export-modal-title-main">Export detail order</span>
          <span class="export-modal-title-sub">
            NO SC: <span id="exportModalSc">-</span>
          </span>
        </div>
        <button type="button" class="export-modal-close" id="exportModalClose">
          <i class="mdi mdi-close"></i>
        </button>
      </div>
      <div class="export-modal-tabs">
        <button type="button"
                class="export-modal-tab is-active"
                data-export-tab="preview">
          Preview
        </button>
        <button type="button"
                class="export-modal-tab"
                data-export-tab="export">
          Export PDF
        </button>
      </div>
      <div class="export-modal-body">
  <div class="export-modal-frame-wrap" id="exportModalFrameWrap">
    <iframe id="exportModalFrame"
            src="about:blank"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"></iframe>
  </div>

  <div class="export-modal-actions">
    <button type="button"
            class="export-modal-primary"
            id="exportModalOpenPrint"
             data-ss-disabled="1">
      <i class="mdi mdi-share-variant" id="exportModalPrimaryIcon"></i>
      <span id="exportModalPrimaryLabel">Bagikan</span>
    </button>
  </div>

  <!-- HASIL LINK BAGIKAN (MUNCUL DI BAWAH TULISAN BAGIKAN) -->
<div class="export-modal-share" id="exportModalShare" style="display:none;">
  <div class="export-modal-share-label">
    Link berbagi sudah dibuat
    <span id="exportModalShareCountdown"></span>:
  </div>
  <div class="export-modal-share-box">
      <input type="text"
             id="exportModalShareInput"
             class="export-modal-share-input"
             readonly>
      <button type="button"
              class="export-modal-share-copy"
              id="exportModalShareCopy">
        <i class="mdi mdi-content-copy"></i>
      </button>
    </div>
    <div class="export-modal-share-expire" id="exportModalShareExpire">
      <!-- diisi via JS, misal: Berlaku sampai 21:15 WIB -->
    </div>
  </div>

  <div class="export-modal-hint" id="exportModalHint">
    Pilih <b>Preview</b> untuk melihat tampilan cetak di laptop/desktop.
    Gunakan tombol <b>Export / Print PDF</b> untuk membuka halaman khusus
    dan menyimpan sebagai PDF.
  </div>
</div>

    </div>
  </div>
<script>
// Modal EXPORT PDF: Preview (HTML) + Export (Print/PDF viewer) + Share PDF 1 jam + countdown
(function () {
  var modal      = document.getElementById('exportModal');
  if (!modal) return;

  var scEl       = document.getElementById('exportModalSc');
  var frameEl    = document.getElementById('exportModalFrame');
  var wrapEl     = document.getElementById('exportModalFrameWrap');
  var hintEl     = document.getElementById('exportModalHint');
  var btnClose   = document.getElementById('exportModalClose');
  var btnPrimary = document.getElementById('exportModalOpenPrint');
  var tabs       = Array.prototype.slice.call(
    modal.querySelectorAll('.export-modal-tab')
  );

  var btnIcon    = document.getElementById('exportModalPrimaryIcon');
  var btnLabel   = document.getElementById('exportModalPrimaryLabel');

  // elemen share result
  var shareBox        = document.getElementById('exportModalShare');
  var shareInput      = document.getElementById('exportModalShareInput');
  var shareCopyBtn    = document.getElementById('exportModalShareCopy');
  var shareExpireEl   = document.getElementById('exportModalShareExpire');
  var shareCountdownEl= document.getElementById('exportModalShareCountdown');

  // ===========================
  //  KONFIG LINK
  // ===========================
    var EXPORT_BASE    = 'order_export_print.php';   // untuk preview HTML di iframe
  var PDF_BASE       = 'order_export_pdf.php';     // untuk file PDF langsung
  var SHARE_GENERATE = 'order_export_share_generate.php';

  // handle interval countdown
  var countdownInterval = null;

  var state = {
    orderId    : null,
    noSc       : '-',
    previewUrl : '',
    pdfUrl     : '',
    currentTab : 'preview',
    shareUrl   : '',
    shareExpire: '',
    expiresTs  : 0          // UNIX timestamp kedaluwarsa (detik)
  };

  function isMobile() {
    var ua = (navigator.userAgent || '').toLowerCase();
    return /android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(ua);
  }

  function isExpired() {
    if (!state.expiresTs) return false;
    var nowTs = Math.floor(Date.now() / 1000);
    return nowTs >= state.expiresTs;
  }

  function updatePrimaryButton() {
    if (!btnIcon || !btnLabel) return;

    if (state.currentTab === 'preview') {
      // tombol bagikan
      btnIcon.className = 'mdi mdi-share-variant';
      if (state.shareUrl && !isExpired()) {
        btnLabel.textContent = 'Done';
      } else {
        btnLabel.textContent = 'Bagikan';
      }
    } else {
      // TAB EXPORT -> buka halaman print / PDF
      btnIcon.className    = 'mdi mdi-file-pdf';
      btnLabel.textContent = 'Download PDF';
    }
  }

  function setExportTab(type) {
    state.currentTab = type;

    tabs.forEach(function (t) {
      t.classList.toggle('is-active', t.getAttribute('data-export-tab') === type);
    });

    if (wrapEl) {
      wrapEl.classList.toggle('is-preview', type === 'preview');
    }

    if (shareBox) {
      // Tab PREVIEW: tampilkan box share kalau sudah ada link.
      // Tab EXPORT: sembunyikan (biar fokus ke print).
      if (type === 'preview' && state.shareUrl) {
        shareBox.style.display = 'block';
      } else {
        shareBox.style.display = 'none';
      }
    }

    if (type === 'preview') {
      if (!state.previewUrl) {
        frameEl.src = 'about:blank';
        hintEl.textContent = 'Data order tidak valid untuk preview.';
      } else {
        frameEl.src = state.previewUrl;
        if (isMobile()) {
          hintEl.innerHTML =
            'Tampilan di atas adalah pratinjau kertas detail order (mode <b>preview</b>). ' +
            'Gunakan pinch-zoom jika perlu. Tekan tombol <b>Bagikan</b> untuk membuat ' +
            'link PDF 1 jam dan membagikannya.';
        } else {
          hintEl.innerHTML =
            'Tampilan di atas adalah pratinjau format cetak dari ' +
            '<code>order_export_print.php?mode=modal</code>. ' +
            'Gunakan tombol <b>Bagikan</b> untuk membuat link PDF sementara (1 jam) ' +
            'yang bisa dibagikan ke pihak lain.';
        }
      }
    } else {
      // TAB EXPORT: tetap pakai preview HTML,
      // tombol di bawah yang akan men-download PDF
      if (!state.previewUrl) {
        frameEl.src = 'about:blank';
        hintEl.textContent = 'Data order tidak valid untuk export / print PDF.';
      } else {
        frameEl.src = state.previewUrl;
        if (isMobile()) {
          hintEl.innerHTML =
            'Tampilan di atas adalah pratinjau cetak. ' +
            'Gunakan tombol <b>Download PDF</b> di bawah untuk mengunduh file.';
        } else {
          hintEl.innerHTML =
            'Ini adalah pratinjau format cetak. ' +
            'Gunakan tombol <b>Download PDF</b> di bawah untuk mengunduh file PDF langsung ' +
            'tanpa dialog print browser.';
        }
      }
    }

    updatePrimaryButton();
  }

  function stopCountdown() {
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
  }

  function startCountdown() {
    if (!shareExpireEl || !shareCountdownEl) return;

    // kalau server tidak kirim expires_ts, fallback 1 jam dari sekarang
    var expiresMs;
    if (typeof state.expiresTs === 'number' && state.expiresTs > 0) {
      expiresMs = state.expiresTs * 1000;
    } else {
      expiresMs = Date.now() + 3600 * 1000;
      state.expiresTs = Math.floor(expiresMs / 1000);
    }

    stopCountdown();

    function render() {
      var nowMs = Date.now();
      var diff  = Math.floor((expiresMs - nowMs) / 1000); // detik

      if (diff <= 0) {
        shareCountdownEl.textContent = ' (00:00)';
        shareExpireEl.textContent = 'Link sudah kedaluwarsa. Silakan buat link baru.';
        stopCountdown();
        // anggap link ini sudah tidak dipakai lagi
        state.shareUrl   = '';
        state.expiresTs  = 0;
        updatePrimaryButton();
        return;
      }

      var h = Math.floor(diff / 3600);
      var m = Math.floor((diff % 3600) / 60);
      var s = diff % 60;

      var timeStr;
      if (h > 0) {
        timeStr =
          String(h).padStart(2, '0') + ':' +
          String(m).padStart(2, '0') + ':' +
          String(s).padStart(2, '0');
      } else {
        timeStr =
          String(m).padStart(2, '0') + ':' +
          String(s).padStart(2, '0');
      }

      shareCountdownEl.textContent = ' (' + timeStr + ')';

      // format "Berlaku sampai ..." pakai jam lokal HP (id-ID)
      var expDate = new Date(expiresMs);
      var options = {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      };
      var localLabel = expDate.toLocaleString('id-ID', options);
      // kalau mau, tambahkan "WIB" di belakang
      shareExpireEl.textContent = 'Berlaku sampai ' + localLabel + ' WIB';
    }

    render();
    countdownInterval = setInterval(render, 1000);
  }

  function openExportModal(orderId, noSc) {
    // reset state
    state.orderId     = orderId;
    state.noSc        = (noSc || '-').trim();
    state.shareUrl    = '';
    state.shareExpire = '';
    state.expiresTs   = 0;

    var qs = '?id_order=' + encodeURIComponent(orderId);

     state.previewUrl = EXPORT_BASE + qs + '&mode=modal';
    // link PDF langsung: file baru order_export_pdf.php
    state.pdfUrl     = PDF_BASE   + qs;

    if (scEl) {
      scEl.textContent = state.noSc;
    }

    stopCountdown();

    if (shareBox) {
      shareBox.style.display = 'none';
    }
    if (shareInput) {
      shareInput.value = '';
    }
    if (shareExpireEl) {
      shareExpireEl.textContent = '';
    }
    if (shareCountdownEl) {
      shareCountdownEl.textContent = '';
    }

    setExportTab('preview');
    modal.classList.add('is-open');
  }

  function closeExportModal() {
    modal.classList.remove('is-open');
    stopCountdown();
    if (frameEl) {
      frameEl.src = 'about:blank';
    }
  }

  if (btnClose) {
    btnClose.addEventListener('click', closeExportModal);
  }

  modal.addEventListener('click', function (ev) {
    if (ev.target === modal) {
      closeExportModal();
    }
  });

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var t = tab.getAttribute('data-export-tab');
      if (!t) return;
      setExportTab(t);
    });
  });

  // ==== COPY LINK SHARE ====
    // ==== COPY LINK SHARE + ANIMASI ICON ====

  // Fungsi animasi: icon copy → centang + spin ±2 detik
  function showCopySuccess() {
    if (!shareCopyBtn) return;

    var icon = shareCopyBtn.querySelector('i');
    if (!icon) return;

    // tambahkan class animasi ke tombol
    shareCopyBtn.classList.add('is-animating');

    // ganti icon copy → centang
    icon.classList.remove('mdi-content-copy');
    icon.classList.remove('mdi-content-copy'); // jaga-jaga kalau double
    icon.classList.add('mdi-check');

    // setelah ±2 detik, kembalikan ke icon copy & hentikan animasi
    setTimeout(function () {
      shareCopyBtn.classList.remove('is-animating');
      icon.classList.remove('mdi-check');
      icon.classList.add('mdi-content-copy');
    }, 2000);
  }

  // fallback manual untuk browser yang tidak support navigator.clipboard
  function manualCopyFallback(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.select();

    var ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }
    document.body.removeChild(ta);

    if (ok) {
      showCopySuccess();
    } else {
      alert('Gagal menyalin link, silakan salin manual.');
    }
  }

    // ==== COPY LINK SHARE + ICON COPY → SPIN → CENTANG ====

  // Helper: set icon jadi "copy"
  function setCopyIcon() {
    if (!shareCopyBtn) return;
    var icon = shareCopyBtn.querySelector('i');
    if (!icon) return;
    icon.className = 'mdi mdi-content-copy';
  }

  // Helper: set icon jadi "spinner" (muter)
  function setSpinIcon() {
    if (!shareCopyBtn) return;
    var icon = shareCopyBtn.querySelector('i');
    if (!icon) return;
    // mdi-loading + mdi-spin bawaan Material Design Icons
    icon.className = 'mdi mdi-loading mdi-spin';
  }

  // Helper: set icon jadi "centang" (sukses)
  function setCheckIcon() {
    if (!shareCopyBtn) return;
    var icon = shareCopyBtn.querySelector('i');
    if (!icon) return;
    icon.className = 'mdi mdi-check-circle-outline';
  }

  // Fallback copy manual jika tidak ada navigator.clipboard
  function manualCopyFallback(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.select();

    var ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }
    document.body.removeChild(ta);

    if (ok) {
      // tampilkan spinner dulu selama 2 detik, lalu centang
      setSpinIcon();
      setTimeout(function () {
        setCheckIcon();
      }, 2000);
    } else {
      // gagal → kembalikan ke icon copy & kasih alert error
      setCopyIcon();
      alert('Gagal menyalin link, silakan salin manual.');
    }
  }

  function copyShareLink() {
    if (!state.shareUrl) return;
    var text = state.shareUrl;

    // Saat user klik, langsung tampilkan spinner
    setSpinIcon();

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        // Berhasil → tunggu 2 detik (spinner aktif), lalu ganti centang
        setTimeout(function () {
          setCheckIcon();
        }, 2000);
      }).catch(function () {
        // Kalau gagal pakai clipboard API → fallback manual
        manualCopyFallback(text);
      });
    } else {
      // Browser lama → langsung ke fallback
      manualCopyFallback(text);
    }
  }

  if (shareCopyBtn) {
    shareCopyBtn.addEventListener('click', function (e) {
      e.preventDefault();
      copyShareLink();
    });
  }



  // ==== BAGIKAN: generate token 1 jam + link ====
  function handleShare() {
    if (!state.orderId) return;

    // Kalau masih ada link dan belum expired → langsung share / copy
    if (state.shareUrl && !isExpired()) {
      if (navigator.share) {
        navigator.share({
          title: 'Detail Order ' + (state.noSc || ''),
          text : 'Detail Order ' + (state.noSc || '') + ' · EFIDI',
          url  : state.shareUrl
        }).catch(function() {});
      } else {
        copyShareLink();
      }
      return;
    }

    // Kalau expired, kita buat link baru
    if (state.shareUrl && isExpired()) {
      state.shareUrl  = '';
      state.expiresTs = 0;
    }

    if (btnPrimary) {
      btnPrimary.disabled = true;
      btnLabel.textContent = 'Memproses...';
    }

    var formData = new FormData();
    formData.append('id_order', String(state.orderId));

    fetch(SHARE_GENERATE, {
      method: 'POST',
      body: formData
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (!data || !data.ok) {
        alert(data && data.msg ? data.msg : 'Gagal membuat link share PDF.');
        return;
      }

      state.shareUrl    = data.share_url || '';
      state.shareExpire = data.expires_label || '';
      state.expiresTs   = (typeof data.expires_ts === 'number')
                           ? data.expires_ts
                           : 0;

      if (state.shareUrl && shareInput) {
        shareInput.value = state.shareUrl;
      }
      if (shareBox && state.shareUrl) {
        shareBox.style.display = 'block';
      }

      startCountdown();      // mulai hitung mundur 1 jam
      updatePrimaryButton(); // ganti label jadi "Bagikan lagi"

      // Sekalian panggil Web Share kalau ada
      if (navigator.share && state.shareUrl) {
        navigator.share({
          title: 'Detail Order ' + (state.noSc || ''),
          text : 'Detail Order ' + (state.noSc || '') + ' · EFIDI',
          url  : state.shareUrl
        }).catch(function() {});
      } else if (!navigator.share && state.shareUrl) {
        alert('Link berbagi sudah dibuat.\n\n' + state.shareUrl);
      }
    })
    .catch(function (err) {
      console.error(err);
      alert('Terjadi kesalahan saat membuat link share.');
    })
    .finally(function () {
      if (btnPrimary) {
        btnPrimary.disabled = false;
        updatePrimaryButton();
      }
    });
  }

    if (btnPrimary) {
    btnPrimary.addEventListener('click', function () {
      if (state.currentTab === 'preview') {
        // MODE PREVIEW → BAGIKAN (seperti semula)
        handleShare();
      } else {
        // MODE EXPORT → LANGSUNG DOWNLOAD PDF
        if (!state.pdfUrl) return;
        
        // Gunakan window.open untuk membuka link download di tab baru
        window.open(state.pdfUrl, '_blank');
      }
    });
  }


  // Listener global: tombol Export PDF di header ORDER
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.order-top-export-btn');
    if (!btn) return;
    if (btn.disabled) return;

    var id = btn.getAttribute('data-order-id');
    if (!id) return;

    var sc = btn.getAttribute('data-order-sc') || '-';

    ev.preventDefault();
    openExportModal(id, sc);
  });
})();
</script>


  <!-- MODAL PILIH DOWNLOAD BAI (PDF / WORD) -->
  <div class="bai-dl-modal" id="baiDownloadModal">
    <div class="bai-dl-modal-inner">
      <div class="bai-dl-modal-header">
        <div class="bai-dl-modal-title">
          <span class="bai-dl-modal-title-main">Download Berita Acara</span>
          <span class="bai-dl-modal-title-sub">
            Kode BAI: <span id="baiDownloadKode">-</span>
          </span>
        </div>
        <button type="button"
                class="bai-dl-modal-close"
                id="baiDownloadClose">
          <i class="mdi mdi-close"></i>
        </button>
      </div>
      <div class="bai-dl-modal-body">
        <div class="bai-dl-modal-chip">
          <i class="mdi mdi-file-pdf"></i>
          <span>Berita Acara Instalasi</span>
        </div>
        <div class="bai-dl-modal-text">
          Silakan pilih format file yang ingin di-download:
          <br>• <b>PDF</b> &mdash; file asli dari direktori <code>bot/file/</code>.
          <br>• <b>Word (DOCX)</b> &mdash; hasil konversi otomatis dari PDF.
        </div>
        <div class="bai-dl-modal-actions">
          <button type="button"
                  class="bai-dl-btn bai-dl-btn-pdf"
                  id="baiDownloadPdf">
            <i class="mdi mdi-file-pdf"></i>
            <span>Download PDF</span>
          </button>
          <button type="button"
                  class="bai-dl-btn bai-dl-btn-word"
                  id="baiDownloadWord">
            <i class="mdi mdi-file-word-box"></i>
            <span>Download Word (DOCX)</span>
          </button>
        </div>
      </div>
    </div>
  </div>
<script>
  // MODAL DOWNLOAD BAI: PDF / WORD (versi langsung download, halaman tidak pindah)
  (function () {
    var modal    = document.getElementById('baiDownloadModal');
    if (!modal) return;

    var kodeEl   = document.getElementById('baiDownloadKode');
    var btnClose = document.getElementById('baiDownloadClose');
    var btnPdf   = document.getElementById('baiDownloadPdf');
    var btnWord  = document.getElementById('baiDownloadWord');

    var currentPdf  = ''; // URL relatif (bot/file/xxx.pdf)
    var currentFile = ''; // hanya nama file (xxx.pdf)
    var currentKode = '';

    // helper: trigger download tanpa pindah halaman
    function triggerDownload(url) {
      if (!url) return;

      // standar: <a download> + click programatik
      var a = document.createElement('a');
      a.href = url;
      a.setAttribute('download', '');
      a.style.display = 'none';

      document.body.appendChild(a);
      a.click();
      setTimeout(function () {
        try { document.body.removeChild(a); } catch (e) {}
      }, 0);
    }

    function openModal(pdfUrl, fileName, kodeBai) {
      currentPdf  = pdfUrl || '';
      currentFile = fileName || '';
      currentKode = kodeBai || '';

      if (kodeEl) {
        kodeEl.textContent = currentKode || '-';
      }

      modal.classList.add('is-open');
    }

    function closeModal() {
      modal.classList.remove('is-open');
    }

    if (btnClose) {
      btnClose.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) {
        closeModal();
      }
    });

    // === TOMBOL DOWNLOAD PDF (FILE ASLI DI bot/file/) ===
    if (btnPdf) {
      btnPdf.addEventListener('click', function (e) {
        e.preventDefault();
        if (!currentFile) {
          alert('File BAI (PDF) tidak tersedia.');
          return;
        }

        // lewat download_bai.php supaya selalu attachment
        var url = 'download_bai.php?f=' + encodeURIComponent(currentFile);

        triggerDownload(url);
        closeModal();
      });
    }

    // === TOMBOL DOWNLOAD WORD (DOCX hasil convert_pdf_word.php) ===
    if (btnWord) {
      btnWord.addEventListener('click', function (e) {
        e.preventDefault();
        if (!currentFile) {
          alert('Nama file BAI tidak valid.');
          return;
        }

        var url = 'convert_pdf_word.php?f=' + encodeURIComponent(currentFile);

        triggerDownload(url);
        closeModal();
      });
    }

    // Listener global: klik tombol Download di langkah BAI
    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.status-step-action-btn-bai');
      if (!btn || btn.disabled) return;

      var pdfUrl = btn.getAttribute('data-bai-pdf')  || '';
      var file   = btn.getAttribute('data-bai-file') || '';
      var kode   = btn.getAttribute('data-bai-kode') || '';

      if (!pdfUrl || !file) {
        alert('File Berita Acara Instalasi tidak tersedia.');
        return;
      }

      ev.preventDefault();
      openModal(pdfUrl, file, kode);
    });
  })();
</script>

  <!-- MODAL PILIH DOWNLOAD FOTO (ZIP / JPG) -->
  <div class="photo-dl-modal" id="photoDownloadModal">
    <div class="photo-dl-modal-inner">
      <div class="photo-dl-modal-header">
        <div class="photo-dl-modal-title">
          <span class="photo-dl-modal-title-main">Download dokumentasi</span>
          <span class="photo-dl-modal-title-sub">
            Kode foto: <span id="photoDownloadKode">-</span>
          </span>
        </div>
        <button type="button"
                class="photo-dl-modal-close"
                id="photoDownloadClose">
          <i class="mdi mdi-close"></i>
        </button>
      </div>
      <div class="photo-dl-modal-body">
        <div class="photo-dl-modal-chip">
          <i class="mdi mdi-image-multiple-outline"></i>
          <span>Dokumentasi lapangan efidi</span>
        </div>
        <div class="photo-dl-modal-text">
          Silakan pilih cara download dokumentasi:
          <br>• <b>ZIP</b> &mdash; 1 file ZIP berisi semua foto.
          <br>• <b>JPG</b> &mdash; semua foto di-download satu per satu.
        </div>
        <div class="photo-dl-modal-actions">
          <button type="button"
                  class="photo-dl-btn photo-dl-btn-zip"
                  id="photoDownloadZip">
            <i class="mdi mdi-zip-box-outline"></i>
            <span>Download as ZIP</span>
          </button>
          <button type="button"
                  class="photo-dl-btn photo-dl-btn-jpg"
                  id="photoDownloadJpg">
            <i class="mdi mdi-file-image-outline"></i>
            <span>Download as JPG</span>
          </button>
        </div>
      </div>
    </div>
  </div>

<script>
  // PILIH DOWNLOAD FOTO: ZIP / JPG (versi diperbaiki)
  (function () {
    var modal    = document.getElementById('photoDownloadModal');
    if (!modal) return;

    var kodeEl   = document.getElementById('photoDownloadKode');
    var btnClose = document.getElementById('photoDownloadClose');
    var btnZip   = document.getElementById('photoDownloadZip');
    var btnJpg   = document.getElementById('photoDownloadJpg');

    var currentKode = '';
    var currentCard = null;

    function openModal(kode, card) {
      currentKode = kode || '';
      currentCard = card || null;

      if (kodeEl) {
        kodeEl.textContent = currentKode || '-';
      }

      // reset href tombol (supaya selalu fresh)
      if (btnZip) btnZip.removeAttribute('href');
      if (btnJpg) btnJpg.removeAttribute('href');

      modal.classList.add('is-open');
    }

    function closeModal() {
      modal.classList.remove('is-open');
    }

    if (btnClose) {
      btnClose.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) {
        closeModal();
      }
    });

    // === DOWNLOAD ZIP ===
    // → langsung pindah ke download_foto.php (paling stabil),
    //   sekaligus set href supaya di HP bisa long-press "Download link"
    if (btnZip) {
      btnZip.addEventListener('click', function (e) {
        e.preventDefault();
        if (!currentKode) return;

        var url = 'download_foto.php?kode_foto=' + encodeURIComponent(currentKode);

        // simpan href untuk fallback (long-press di mobile browser)
        btnZip.setAttribute('href', url);

        // trigger navigasi langsung (di browser normal akan langsung download)
        window.location.href = url;

        closeModal();
      });
    }

    // === DOWNLOAD SEMUA JPG ===
    // Catatan: banyak browser / WebView membatasi multi-download otomatis.
    // Di sini kita tetap coba satu-per-satu secara sinkron.
    if (btnJpg) {
      btnJpg.addEventListener('click', function (e) {
        e.preventDefault();
        if (!currentCard || !currentKode) return;

        var thumbs = currentCard.querySelectorAll('.foto-thumbs a');
        if (!thumbs.length) {
          alert('Tidak ada foto yang bisa di-download.');
          return;
        }

        // Simpan href pertama sebagai fallback (user bisa long-press link)
        var firstHref = thumbs[0].getAttribute('href') || '';
        if (firstHref) {
          btnJpg.setAttribute('href', firstHref);
        }

        // Coba download semua JPG secara sinkron dalam satu user-gesture
        thumbs.forEach(function (a) {
          var url = a.getAttribute('href');
          if (!url) return;

          var link = document.createElement('a');
          link.href = url;
          link.setAttribute('download', '');
          link.style.display = 'none';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        });

        closeModal();
      });
    }

    // Listener global untuk tombol "Download" di langkah "Dokumentasi Lapangan"
    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.status-step-action-btn-photo');
      if (!btn || btn.disabled) return;

      var kode = btn.getAttribute('data-kode-foto') || '';
      if (!kode) return;

      var card = btn.closest('.order-card');
      openModal(kode, card);
    });
  })();
</script>


  <!-- MODAL MEDIA GLOBAL (PDF & FOTO) -->
  <div class="media-modal" id="mediaModal">
    <div class="media-modal-inner">
      <div class="media-modal-header">
        <div class="media-modal-title">
          <i class="mdi mdi-open-in-new"></i>
          <span class="text" id="mediaModalTitle">Media</span>
        </div>
        <div class="media-modal-actions">
          <button type="button" class="media-modal-btn media-modal-btn-close" id="mediaModalClose">
            <i class="mdi mdi-close"></i>
            <span>Tutup</span>
          </button>
        </div>
      </div>
      <div class="media-modal-body" id="mediaModalBody"></div>
    </div>
  </div>

  <script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin="">
  </script>

  <script>
    // Modal global untuk BAI (PDF) dan FOTO (image)
    (function () {
      const modal      = document.getElementById('mediaModal');
      const modalBody  = document.getElementById('mediaModalBody');
      const modalTitle = document.getElementById('mediaModalTitle');
      const btnClose   = document.getElementById('mediaModalClose');

      if (!modal || !modalBody || !modalTitle || !btnClose) return;

      function isMobile() {
        const ua = (navigator.userAgent || '').toLowerCase();
        return /android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(ua);
      }

      function openModal(type, src, title) {
        modalTitle.textContent = title || 'Media';
        modalBody.innerHTML = '';

        if (type === 'image') {
          const img = document.createElement('img');
          img.src = src;
          img.alt = title || '';
          modalBody.appendChild(img);
        } else if (type === 'pdf') {
          const frame = document.createElement('iframe');
          frame.src = src;
          frame.loading = 'lazy';
          frame.style.background = '#ffffff';
          frame.style.width  = '100%';
          frame.style.height = '80vh';
          frame.setAttribute('allowfullscreen', 'true');
          modalBody.appendChild(frame);
        }

        modal.classList.add('is-open');
      }

      function closeModal() {
        modal.classList.remove('is-open');
      }

      btnClose.addEventListener('click', closeModal);
      modal.addEventListener('click', function (ev) {
        if (ev.target === modal) {
          closeModal();
        }
      });

      document.body.addEventListener('click', function (ev) {
        const trigger = ev.target.closest('[data-modal-type]');
        if (!trigger) return;

        const typeAttr  = trigger.getAttribute('data-modal-type');
        let   srcAttr   = trigger.getAttribute('data-modal-src') || trigger.getAttribute('href');
        const titleAttr = trigger.getAttribute('data-modal-title') || trigger.title || '';

        if (!typeAttr || !srcAttr) return;

        let type = typeAttr;
        let src  = srcAttr;

        if (type === 'pdf') {
          const pdfFile = trigger.getAttribute('data-pdf-file') || srcAttr;
          if (isMobile()) {
            src = 'pdf_viewer.php?f=' + encodeURIComponent(pdfFile);
          } else {
            src = pdfFile;
          }
        }

        ev.preventDefault();
        openModal(type, src, titleAttr);
      });
    })();

    function copyCoord(text) {
      if (!text) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).catch(function(){});
      } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
      }
    }

    document.addEventListener('click', function (ev) {
      const btn = ev.target.closest('.popup-btn[data-copy]');
      if (!btn) return;
      const val = btn.getAttribute('data-copy') || '';
      copyCoord(val);
    });
    
    (function () {
      if (typeof L === 'undefined') return;

      function parseCoord(str) {
        if (!str) return null;
        const parts = str.split(',');
        if (parts.length < 2) return null;
        const lat = parseFloat(parts[0]);
        const lng = parseFloat(parts[1]);
        if (isNaN(lat) || isNaN(lng)) return null;
        return [lat, lng];
      }

      function buildPopup(label, lat, lng) {
        const coord = lat.toFixed(6) + ',' + lng.toFixed(6);
        const gmaps  = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(coord);
        const route  = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(coord);
        const waze   = 'https://waze.com/ul?ll=' + encodeURIComponent(coord) + '&navigate=yes';
        const street = 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' + encodeURIComponent(coord);

        return `
          <div style="min-width:180px;">
            <div style="font-weight:600;margin-bottom:2px;">${label}</div>
            <div style="font-size:12px;color:#4b5563;margin-bottom:6px;">${coord}</div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;">
              <a href="${gmaps}" target="_blank" class="popup-btn">Maps</a>
              <a href="${route}" target="_blank" class="popup-btn">Rute</a>
              <a href="${waze}" target="_blank" class="popup-btn">Waze</a>
              <button type="button" class="popup-btn popup-btn-ghost" data-copy="${coord}">Salin</button>
              <a href="${street}" target="_blank" class="popup-btn">Street</a>
            </div>
          </div>`;
      }

      document.querySelectorAll('.map-panel').forEach(function(panel) {
        const canvas = panel.querySelector('.map-canvas');
        if (!canvas) return;

        const pelStr = (panel.getAttribute('data-pel') || '').trim();
        const odpStr = (panel.getAttribute('data-odp') || '').trim();

        const pts = [];
        const pel = parseCoord(pelStr);
        const odp = parseCoord(odpStr);

        if (pel) pts.push({ pos: pel, label: 'Pelanggan' });
        if (odp) pts.push({ pos: odp, label: 'ODP' });

        if (!pts.length) return;

        const map = L.map(canvas, {
          scrollWheelZoom: false,
          zoomControl: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: ''
        }).addTo(map);

        const latlngs = [];

        pts.forEach(function(p) {
          latlngs.push(p.pos);

          const isODP = (p.label === 'ODP');
          const opts = {
            radius: 7,
            weight: 3,
            opacity: 1,
            fillOpacity: 0.9,
            fillColor: isODP ? '#22c55e' : '#0ea5e9',
            color:     isODP ? '#bbf7d0' : '#e0f2fe'
          };

          const m = L.circleMarker(p.pos, opts).addTo(map);

          const lat = p.pos[0];
          const lng = p.pos[1];

          m.bindPopup(buildPopup(p.label, lat, lng));
        });

        if (latlngs.length === 1) {
          map.setView(latlngs[0], 18);
        } else {
          const poly = L.polyline(latlngs, {
            color: '#0ea5e9',
            weight: 4,
            opacity: 0.9
          }).addTo(map);
          map.fitBounds(poly.getBounds(), { padding: [20, 20], maxZoom: 17 });
        }
      });
    })();
  </script>

  <!-- OVERLAY HITAM ANTISCREENSHOT UNTUK NON ADMIN / NON TA -->
  <div id="ssGuardOverlay">
    <div class="ss-guard-inner">
      <div class="ss-guard-title">EFIDI · Protected Screen</div>
      <div class="ss-guard-message">
        Screenshot / capture halaman ini dibatasi untuk akun ini (bukan admin/TA).
      </div>
      <div class="ss-guard-hint">
        Jika Anda merasa ini salah, silakan hubungi Admin EFIDI.
      </div>
    </div>
  </div>

  <script>
  (function () {
    var canScreenshot = document.body.getAttribute('data-can-screenshot') === '1';

    try {
      if (window.AndroidBridge && typeof AndroidBridge.setScreenshotAllowed === 'function') {
        AndroidBridge.setScreenshotAllowed(canScreenshot);
      }
    } catch (e) {}

    if (canScreenshot) return;

    document.addEventListener('contextmenu', function (e) {
      e.preventDefault();
    });

    document.addEventListener('keydown', function (e) {
      var key = (e.key || '').toLowerCase();

      if (key === 'printscreen') {
        e.preventDefault();
        e.stopPropagation();

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText('Screenshot dinonaktifkan untuk akun ini.');
        }

        alert('Screenshot dinonaktifkan untuk akun ini (bukan admin/TA).');
        return;
      }

      if ((e.ctrlKey || e.metaKey) && (key === 'p' || key === 's')) {
        e.preventDefault();
        e.stopPropagation();
        alert('Print/Save halaman dinonaktifkan untuk akun ini.');
        return;
      }

      if (e.metaKey && e.shiftKey && (key === '3' || key === '4')) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
    });
  })();
  </script>
<script>
  // TAMBAHAN: proteksi ekstra untuk non-admin/non-TA di WEB (laptop/desktop)
  (function () {
    var canScreenshot = document.body.getAttribute('data-can-screenshot') === '1';
    if (canScreenshot) {
      // Admin & TA: tidak ada pembatasan ekstra di web
      return;
    }

    var overlay = document.getElementById('ssGuardOverlay');

    function showGuard(showAlert) {
      if (overlay) {
        overlay.classList.add('is-active');         // munculkan overlay hitam
      }
      document.body.classList.add('screen-dark');   // blur .shell (CSS kamu sudah ada)

      // hilangkan selection supaya tidak ada teks kebaca jelas
      try {
        var sel = window.getSelection && window.getSelection();
        if (sel && sel.removeAllRanges) sel.removeAllRanges();
      } catch (e) {}

      if (showAlert) {
        alert('Screenshot / crop halaman ini dibatasi untuk akun ini (bukan admin/TA).');
      }
    }

    function hideGuard() {
      if (overlay) {
        overlay.classList.remove('is-active');
      }
      document.body.classList.remove('screen-dark');
    }

    // 1) Saat window kehilangan fokus (user pindah aplikasi / buka tool lain)
    window.addEventListener('blur', function () {
      showGuard(false);
    });

    // 2) Saat tab jadi hidden / balik lagi
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        // tab "pergi" (misal pindah ke aplikasi lain) -> langsung gelapkan
        showGuard(false);
      } else {
        // balik ke tab -> boleh dibuka lagi (tapi keyboard block dari script lama tetap jalan)
        hideGuard();
      }
    });

    // 3) Non-admin/non-TA: matikan tombol-tombol penting (misal export)
    // 3) Non-admin/non-TA: matikan tombol-tombol yang diberi flag khusus
function disableButtonsForViewer() {
  // Hanya tombol yang memang ingin dibatasi:
  // beri atribut data-ss-disabled="1" di HTML
  var btns = Array.prototype.slice.call(
    document.querySelectorAll('[data-ss-disabled="1"]')
  );

  btns.forEach(function (btn) {
    btn.setAttribute('disabled', 'disabled');
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.5';
    btn.style.cursor = 'not-allowed';
    btn.title = 'Fitur ini hanya tersedia untuk Admin / TA';
  });
}


    // jalankan setelah DOM ready
    if (document.readyState === 'loading') {
      // document.addEventListener('DOMContentLoaded', disableButtonsForViewer);
    } else {
      // disableButtonsForViewer();
    }
  })();
</script>

</body>
</html>
