<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/redirect_helper.php';

const EFIDI_SNAPSHOT_KEY = 'efidi_ss_2025_xh3J9F7s_8KpQ2Z0Lm'; // sama dengan yang dipakai di announc_grub.php

$isSnapshot = false;
if (isset($_GET['cron_key']) && hash_equals(EFIDI_SNAPSHOT_KEY, (string)$_GET['cron_key'])) {
  $isSnapshot = true;
  if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
      'id'       => 0,
      'username' => 'Announc efidi.',
      'role'     => 'admin',
      'name'     => 'announc_efidi.',
    ];
  }
}

// Normalisasi session user
$user = $_SESSION['user'] ?? null;
if (!$user && isset($_SESSION['uid'])) {
  $user = [
    'id'       => (int)($_SESSION['uid'] ?? 0),
    'username' => (string)($_SESSION['username'] ?? ''),
    'role'     => (string)($_SESSION['role'] ?? ''),
    // ambil 'nama' lama, jatuhkan ke 'name'
    'name'     => (string)($_SESSION['nama'] ?? $_SESSION['name'] ?? ''),
  ];
  $_SESSION['user'] = $user; // cache agar template konsisten
}

if (!$user) { redirect('index.php'); } // wajib login

$role        = strtolower((string)($user['role'] ?? ''));
$username    = (string)($user['username'] ?? '');
$displayName = ($user['name'] ?? '') !== '' ? (string)$user['name'] : $username;

// helper role color (opsional)
if (!function_exists('role_color')) {
  function role_color(string $r): string {
    switch (strtoupper($r)) {
      case 'Admin': return '#e11d48';
      case 'Ta':    return '#009ea9';
      case 'Tech':  return '#166534';
      default:      return '#182958';
    }
  }
}
?>
<?php
// Ambil role dari baris (jika ada), kalau tidak ada pakai dari session
$roleRaw = (string)($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? ''));

$role    = strtolower(trim($roleRaw));

// Peta role -> nama file
$iconMap = [
  'admin' => 'admin.png',
  'ta'    => 'ta.png',
  'tech'  => 'tech.png',
];

// Tentukan file ikon + URL relatif ke folder images/user/
$iconFile = $iconMap[$role] ?? 'user.png'; // fallback opsional
$iconUrl  = 'images/user/' . $iconFile;
?>

<!-- Contoh di sel tabel: tambahkan ikon di depan nama -->


<!DOCTYPE html>
<html lang="en"
      class="<?= $isSnapshot ? 'snapshot-mode' : 'efidi-loading-open' ?>">

<head>
  
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>efidi. – Evolving Framework for Integrated Data</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
  <!-- endinject -->
   
  <!-- plugin css for this page -->
   <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">



  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="css/style.css">
  
  <!-- endinject -->
  <link rel="shortcut icon" href="images/logoo.png" />
  <!-- Select2 + tema Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">


<style>
  /* Biar chip Select2 rapat dan selaras tema */
  .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered { gap: .25rem }
  .select2-container--bootstrap-5 .select2-selection--multiple { min-height: calc(1.5em + .75rem + 2px) }

  
</style>
<style>
  /* samakan tinggi field Select2 dengan tombol */
  .select2-container .select2-selection--multiple{
    min-height: calc(2.25rem + 6px);
    padding-top: .375rem; padding-bottom: .375rem;
    display: flex; align-items: center;
  }
/* scope & posisi badge */
</style>


  <style>
  /* rapetin jarak header ke konten di bawahnya */
  .content-wrapper .grid-margin { margin-bottom: 0.7rem !important; }

  /* ukuran breadcrumb */
</style>

<style>
  .breadcrumb-mini .crumb-mid{ font-size:.70rem; line-height:1.1; }
 
</style>

<style>
  /* Kalau utilitas negatif margin Bootstrap tidak aktif di temamu, pakai ini: */
  .mt-n2{ margin-top:-.20rem !important; }
  .me-3{ margin-right:1.5rem !important; } /* geser sedikit dari tepi kanan */
  
</style>
<style>
  .name-cell{display:flex;align-items:center;gap:10px}
  .name-cell .avatar{
    width:33px;height:33px;border-radius:50%;
    object-fit:cover;border:1px solid #e5e7eb;background:#f3f4f6;
  }
</style>

  <!-- ... semua CSS Press yang sudah ada di atas ... -->
<style>
  /* Kunci scroll waktu loading */
  html.efidi-loading-open,
  body.efidi-loading-open {
    overflow: hidden !important;
  }

  /* BACKDROP full-screen, abu-abu lembut */
 /* BACKDROP full-screen, abu-abu lembut */
.efidi-loading-backdrop {
  position: fixed;
  inset: 0;
  background: #f4f6fb;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2147483600;

  /* jangan di-display:none lagi, pakai opacity */
  opacity: 0;
  visibility: hidden;
  transition: opacity .18s ease-out, visibility 0s linear .18s;
}

/* saat aktif */
.efidi-loading-backdrop.is-visible {
  opacity: 1;
  visibility: visible;
  transition: opacity .18s ease-out, visibility 0s;
}
.efidi-loader-shell,
.efidi-loader-circle {
  will-change: transform;
  transform: translateZ(0);
}

.efidi-ring-rotor {
  transform-origin: 50% 50%;
  animation: efidi-ring-spin 2.8s linear infinite;
  will-change: transform;
}

  /* Shell di tengah, untuk posisi */
  .efidi-loader-shell {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 420px;
    min-height: 220px;
  }

  /* Lingkaran putih dengan shadow halus */
  .efidi-loader-circle {
    position: relative;
    width: 260px;
    height: 260px;
    border-radius: 999px;
   
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
  }

  /* SVG ring */
  .efidi-loader-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
  }

  /* ring abu muda statis */
  .efidi-ring-bg {
    fill: none;
    stroke: rgba(199, 210, 254, 0.69);
    stroke-width: 0.3;
  }

  /* ring highlight (hanya sebagian) */
  .efidi-ring-fg {
    fill: none;
    stroke: url(#efidiRingGradient);
    stroke-width: 0.8;
    stroke-linecap: round;
    stroke-dasharray: 190 120; /* 90px kelihatan, sisanya kosong */
    stroke-dashoffset: 0;
  }

  /* grup ring diputar terus */
  .efidi-ring-rotor {
    transform-origin: 50% 50%;
    animation: efidi-ring-spin 2.8s linear infinite;
  }

  @keyframes efidi-ring-spin {
    to {
      transform: rotate(360deg);
    }
  }

 /* LOGO EFIDI DI TENGAH – TANPA BACKGROUND */
.efidi-loader-logo {
  position: relative;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: center;
  /* tanpa lingkaran / gradient */
   width: 80px;
    height: 80px;
  border-radius: 0;
  background: transparent;
}


  .efidi-loader-logo img {
    max-width: 70%;
    max-height: 70%;
    object-fit: contain;
    display: block;
  }

  /* Mode snapshot via cron_key → loader mati total */
  html.snapshot-mode .efidi-loading-backdrop {
    display: none !important;
  }
</style>
<script>
(function () {
  const LOADER_ID   = 'efidiGlobalLoader';
  const MIN_VISIBLE = 200; // minimal 0.8 detik kelihatan
  const isSnapshot  = document.documentElement.classList.contains('snapshot-mode');

  let loaderEl      = null;
  let pendingCount  = isSnapshot ? 0 : 1;  // 1 = initial page load
  let firstShowAt   = isSnapshot ? 0 : performance.now();
  let hideTimer     = null;

  function getLoader() {
    if (isSnapshot) return null;
    if (!loaderEl) loaderEl = document.getElementById(LOADER_ID);
    return loaderEl;
  }

  function ensureVisible() {
    const el = getLoader();
    if (!el) return;
    el.classList.add('is-visible');
  }

  function reallyHide() {
    const el = getLoader();
    if (!el) return;
    el.classList.remove('is-visible');
    document.documentElement.classList.remove('efidi-loading-open');
    if (document.body) document.body.classList.remove('efidi-loading-open');
    firstShowAt = 0;
    hideTimer   = null;
    // console.log('[efidi-loader] hidden');
  }

  function showLoader() {
    if (isSnapshot) return;
    const el = getLoader();
    if (!el) return;

    if (pendingCount === 0) {
      firstShowAt = performance.now();
    }
    pendingCount++;

    ensureVisible();
    document.documentElement.classList.add('efidi-loading-open');
    if (document.body) document.body.classList.add('efidi-loading-open');

    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
    // console.log('[efidi-loader] show, pending =', pendingCount);
  }

  function hideLoader(force) {
    if (isSnapshot) return;
    const el = getLoader();
    if (!el) return;

    if (force) {
      pendingCount = 0;
    } else {
      if (pendingCount > 0) pendingCount--;
    }

    // masih ada proses, jangan matikan overlay
    if (pendingCount > 0) return;

    const now     = performance.now();
    const elapsed = firstShowAt ? (now - firstShowAt) : MIN_VISIBLE;
    const remain  = MIN_VISIBLE - elapsed;

    if (remain <= 0) {
      reallyHide();
    } else {
      if (hideTimer) clearTimeout(hideTimer);
      hideTimer = setTimeout(reallyHide, remain);
      // console.log('[efidi-loader] delay hide', remain, 'ms');
    }
  }

  // Biar bisa dipanggil manual dari halaman manapun
  window.EfidiLoader = {
    show: () => showLoader(),
    hide: () => hideLoader(true),
    state: () => ({
      pendingCount,
      firstShowAt,
      isSnapshot,
      hasEl: !!getLoader()
    })
  };

  document.addEventListener('DOMContentLoaded', function () {
    if (isSnapshot) return;
    const el = getLoader();
    if (!el) return;

    // pastikan dari awal sudah nge-lock scroll + overlay on
    ensureVisible();
    document.documentElement.classList.add('efidi-loading-open');
    if (document.body) document.body.classList.add('efidi-loading-open');

    // === TRACK SEMUA AJAX jQuery (termasuk DataTables server-side) ===
    if (window.jQuery) {
      jQuery(document).ajaxStart(function () {
        showLoader();          // setiap ada request baru
      });
      jQuery(document).ajaxStop(function () {
        hideLoader(false);     // kalau semua request selesai
      });

      // kalau DOMContentLoaded terlambat tapi sudah ada AJAX aktif
      if (jQuery.active > 0) {
        showLoader();
      }
    } else {
      console.warn('[efidi-loader] jQuery tidak ditemukan, loader AJAX hanya bisa dipanggil manual (EfidiLoader.show/hide).');
    }

    // === OPTIONAL: Loader untuk link / form tertentu saja ===
    // Tambah data-loader="page" kalau mau pakai loader saat pindah halaman
    document.addEventListener('click', function (e) {
      const a = e.target.closest('a[data-loader="page"]');
      if (!a) return;

      if (a.target && a.target.toLowerCase() === '_blank') return;
      const href = a.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

      try {
        const url = new URL(href, location.href);
        if (url.origin !== location.origin) return; // hanya internal
      } catch {
        return;
      }
      showLoader();
    }, true);

    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!form || !form.matches('form[data-loader="page"]')) return;
      showLoader();
    }, true);
  });

  // ketika semua resource halaman selesai (CSS, img, dll)
  window.addEventListener('load', function () {
    // kurangi "pending awal" (initial page load)
    hideLoader(false);
  });
})();
</script>

</head>
<body class="<?= $isSnapshot ? '' : 'efidi-loading-open' ?>">
  <div id="efidiGlobalLoader" class="efidi-loading-backdrop is-visible">

  <div class="efidi-loader-shell">
    <div class="efidi-loader-circle">
      <!-- LOGO EFIDI DI TENGAH -->
      <div class="efidi-loader-logo">
        <!-- silakan ganti src sesuai logo utama efidi -->
        <img src="images/logoo.png" alt="efidi logo">
      </div>

      <!-- RING MUTER -->
      <svg class="efidi-loader-svg" viewBox="0 0 120 120">
        <defs>
          <!-- gradient warna highlight -->
          <linearGradient id="efidiRingGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%"   stop-color="#08b2bbff"/>
            <stop offset="50%"  stop-color="#08b2bbff"/>
            <stop offset="100%" stop-color="#08b2bbff"/>
          </linearGradient>
        </defs>

        <!-- ring background tipis -->
        <circle class="efidi-ring-bg" cx="60" cy="60" r="46"></circle>

        <!-- ring highlight yang MUTER -->
        <g class="efidi-ring-rotor">
          <circle class="efidi-ring-fg" cx="60" cy="60" r="46"></circle>
        </g>
      </svg>
    </div>
  </div>
</div>


  
  <div class="container-scroller">
    <?php
// ==== NOTIFIKASI (24 jam terakhir untuk admin & ta) ====
// butuh $pdo
if (!isset($pdo)) {
  $p = __DIR__ . '/koneksi.php';
  if (is_file($p)) require_once $p;
}

// WIB di PHP & MySQL (hindari selisih 7 jam)
date_default_timezone_set('Asia/Jakarta');
try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->exec("SET time_zone = '+07:00'");
  }
} catch (Throwable $e) { /* no-op */ }


// helper: cek kolom ada/tidak
if (!function_exists('db_col_exists')) {
  function db_col_exists(PDO $pdo, string $table, string $col): bool {
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$table, $col]);
    return (bool)$st->fetchColumn();
  }
}
// helper: pilih kolom pertama yang ada dari kandidat
if (!function_exists('pick_col')) {
  function pick_col(PDO $pdo, string $table, array $cands): ?string {
    foreach ($cands as $c) {
      try {
        if (db_col_exists($pdo, $table, $c)) return $c;
      } catch (Throwable $e) { /* no-op */ }
    }
    return null;
  }
}

// pilih kolom timestamp bertipe DATETIME/TIMESTAMP saja
if (!function_exists('pick_ts_col')) {
  function pick_ts_col(PDO $pdo, string $table, array $cands): ?string {
    if (!$cands) return null;
    $in  = implode(',', array_fill(0, count($cands), '?'));
    $sql = "SELECT COLUMN_NAME, DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ($in)";
    $st  = $pdo->prepare($sql);
    $st->execute(array_merge([$table], $cands));
    $ok = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $t = strtolower((string)($row['DATA_TYPE'] ?? ''));
      if (in_array($t, ['datetime','timestamp'], true)) $ok[] = (string)$row['COLUMN_NAME'];
    }
    foreach ($cands as $c) if (in_array($c, $ok, true)) return $c;
    return null;
  }
}

// helper: waktu "x menit/jam lalu"
if (!function_exists('human_ago')) {
  function human_ago(string $ts): string {
    try {
      $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
      $dt  = new DateTime($ts, new DateTimeZone('Asia/Jakarta'));
    } catch (Throwable $e) {
      return htmlspecialchars($ts, ENT_QUOTES, 'UTF-8');
    }
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    if ($diff < 60)  return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff/60) . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
    return $dt->format('d M Y H:i');
  }
}
if (!function_exists('extract_bai_key')) {
  function extract_bai_key(string $s): string {
    $s = trim($s);
    if ($s === '') return '';

    // Ambil potongan AOi... (paling diprioritaskan)
    if (preg_match('~(AOi?[A-Za-z0-9]{6,})~', $s, $m)) return $m[1];

    // Atau SC123456...
    if (preg_match('~(SC[0-9]{6,})~', $s, $m)) return $m[1];

    // Atau PDKxxx...
    if (preg_match('~(PDK[A-Za-z0-9]{4,})~', $s, $m)) return $m[1];

    // Fallback: potong sebelum spasi/underscore/dash
    if (preg_match('~^([^\s_-]+)~', $s, $m)) return $m[1];

    return $s;
  }
}

$notifItems = [];
$notifCount = 0;

// hanya siapkan feed untuk admin/ta
if (in_array($role, ['admin','ta'], true) && isset($pdo) && $pdo instanceof PDO) {
  $since = date('Y-m-d H:i:s', time() - 86400); // 24 jam terakhir

  // ===== ORDERS =====
  // ===== ORDERS =====
$ordersTs  = pick_ts_col($pdo, 'orders', ['created_at','inserted_at','tgl_create','ts','created']);
$ordersId  = pick_col($pdo, 'orders', ['id_order','id']);
$ordersNo  = pick_col($pdo, 'orders', ['no_sc','order_no','no_order','service_number']);
$ordersWho = pick_col($pdo, 'orders', ['sender_username','from','from_name','uploader','created_by','user']);

if ($ordersTs && $ordersId) {
  $selNo  = $ordersNo  ? ", `$ordersNo`  AS no_sc" : ", NULL AS no_sc";
  $selWho = $ordersWho ? ", `$ordersWho` AS who"   : ", NULL AS who";
  $sql = "SELECT `$ordersId` AS id, `$ordersTs` AS ts, 'order' AS typ $selNo $selWho
          FROM `orders`
          WHERE `$ordersTs` >= (NOW() - INTERVAL 1 DAY)
          ORDER BY `$ordersTs` DESC
          LIMIT 50";
  $st = $pdo->prepare($sql);
  $st->execute();
  $notifItems = array_merge($notifItems, $st->fetchAll(PDO::FETCH_ASSOC));
}

// ===== BAI =====
$baiTs  = pick_ts_col($pdo, 'bai', ['uploaded_at','created_at','inserted_at','ts','created']);
$baiId  = pick_col($pdo, 'bai', ['id_bai','id']);
$baiKode= pick_col($pdo, 'bai', ['kode_bai','kode','filename','nama_file']);
$baiWho = pick_col($pdo, 'bai', ['uploader','sender_username','from','created_by','user']);

if ($baiTs && $baiId) {
  $selKode = $baiKode ? ", `$baiKode` AS kode_bai" : ", NULL AS kode_bai";
  $selWho  = $baiWho  ? ", `$baiWho`  AS who"      : ", NULL AS who";
  $sql = "SELECT `$baiId` AS id, `$baiTs` AS ts, 'bai' AS typ $selKode $selWho
          FROM `bai`
          WHERE `$baiTs` >= (NOW() - INTERVAL 1 DAY)
          ORDER BY `$baiTs` DESC
          LIMIT 50";
  $st = $pdo->prepare($sql);
  $st->execute();
  $notifItems = array_merge($notifItems, $st->fetchAll(PDO::FETCH_ASSOC));
}

  // gabung & urutkan terbaru dulu
  usort($notifItems, function($a,$b){
    return strcmp($b['ts'] ?? '', $a['ts'] ?? '');
  });
  // maksimal 30 item biar ringan
  $notifItems = array_slice($notifItems, 0, 30);
  $notifCount = count($notifItems);
}
?>

<?php
// ===== QUICK COUNTS (untuk badge icon File/Images) =====
$navFileCount = 0;
$navFotoCount = 0;

$__dirFile = realpath(__DIR__ . '/bot/file') ?: (__DIR__ . '/bot/file');
$__dirFoto = realpath(__DIR__ . '/bot/foto') ?: (__DIR__ . '/bot/foto');

if (is_dir($__dirFile)) {
  $tmp = glob(rtrim($__dirFile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*');
  if ($tmp) {
    foreach ($tmp as $p) if (is_file($p)) $navFileCount++;
  }
}
if (is_dir($__dirFoto)) {
  $tmp = glob(rtrim($__dirFoto, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
  $navFotoCount = $tmp ? count($tmp) : 0;
}
?>


    <!-- partial:partials/_navbar.html -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="navbar-brand-wrapper d-flex justify-content-center">
        <div class="navbar-brand-inner-wrapper d-flex justify-content-between align-items-center w-100">  
            <a class="navbar-brand brand-logo">
              <img src="images/100.png" alt="logo" style="height:27px; width:110px; object-fit:contain;" />
            </a>
          <a class="navbar-brand brand-logo-mini"><img src="images/logoo.png" alt="logo"/></a>
          <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
            <span class="mdi mdi-sort-variant"></span>
          </button>
        </div>  
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
        
        <ul class="navbar-nav navbar-nav-right">
        
         <?php if (in_array($role, ['admin','ta'], true)) : ?>
         <!-- === CHAT / MESSAGES (kiri dari NOTIFICATION) === -->
<!--<li class="nav-item mr-4">-->
<!--  <a class="nav-link count-indicator position-relative d-flex align-items-center justify-content-center"-->
<!--     href="chat.php"-->
<!--     title="Messages">-->
<!--    <i class="mdi mdi-message-text-outline mx-0"></i>-->

    <!-- badge unread (diupdate via localStorage oleh chat.php) -->
<!--    <span id="efChatBadge" class="notif-count chat-count d-none">0</span>-->
<!--  </a>-->
<!--</li>-->
<style>
/* badge chat beda warna (tosca) */
.notif-count.chat-count{
  background:#20a1ad !important;
}

</style>
<script>
(function(){
  function updateChatBadge(){
    try{
      var el = document.getElementById('efChatBadge');
      if(!el) return;

      var n = parseInt(localStorage.getItem('ef_chat_unread') || '0', 10) || 0;

      if(n > 0){
        el.textContent = (n > 99 ? '99+' : String(n));
        el.classList.remove('d-none');
      }else{
        el.classList.add('d-none');
      }
    }catch(e){}
  }

  document.addEventListener('DOMContentLoaded', updateChatBadge);
  window.addEventListener('storage', updateChatBadge);
  window.EfidiChatBadgeUpdate = updateChatBadge; // bisa dipanggil dari chat.php
})();
</script>

<!-- === FILE (bot/file) === -->
<li class="nav-item efidi-navicon-item">
  <a class="nav-link count-indicator position-relative d-flex align-items-center justify-content-center nav-quick-icon"
     href="bai_file.php" data-loader="page" title="File (bot/file)">
    <i class="mdi mdi-file-pdf-box mx-0"></i>

  </a>
</li>

<li class="nav-item efidi-navicon-item">
  <a class="nav-link count-indicator position-relative d-flex align-items-center justify-content-center nav-quick-icon"
     href="foto_file.php" data-loader="page" title="Images (bot/foto)">
    <i class="mdi mdi-folder-multiple-image mx-0"></i>

  </a>
</li>

<style>
/* tombol icon navbar (File/Images) */
.nav-quick-icon{
  width: 36px;
  height: 36px;
  border-radius: 999px;
  transition: background .15s ease;
}
.nav-quick-icon:hover{
  background: rgba(0,0,0,.06);
}
.nav-quick-icon i{
  font-size: 20px;
  color: #111827;
}

/* ikon NOTIFICATION jadi hitam */
.notification-dropdown i{
  color: #000 !important;
}

/* beda warna badge (opsional) */
.notif-count--file{ background:#111827 !important; }  /* hitam */
.notif-count--img{  background:#0ea5e9 !important; }  /* biru */

/* Dempetkan jarak antar ikon File / Images / Notif */
/* Biar UL kanan benar-benar rapat */
.navbar-nav.navbar-nav-right{
  display: flex !important;
  align-items: center !important;
  gap: 2px;               /* atur jarak antar icon di sini */
}

/* Matikan margin bawaan antar <li> */
.navbar-nav.navbar-nav-right .efidi-navicon-item{
  margin-right: 0 !important;
}

/* KUNCI: hilangkan padding nav-link bawaan Bootstrap */
.navbar-nav.navbar-nav-right .efidi-navicon-item .nav-link.nav-quick-icon{
  padding: 0 !important;
  margin: 0 !important;
}

/* Kalau mau masih lebih nempel lagi, ubah gap jadi 0 atau 1px */

/* Kasih jarak sedikit sebelum profile biar tidak nempel banget */
.navbar-nav.navbar-nav-right .efidi-navicon-item--last{
  margin-right: .65rem !important;
}

/* Optional: kecilkan sedikit tombol icon biar makin rapat */
.nav-quick-icon{
  width: 34px;
  height: 34px;
}
.nav-quick-icon i{
  font-size: 20px;
}

/* ===========================
   SUPER DEMPET ICON HEADER
   (File / Images / Notif)
   =========================== */

/* matikan mr-4 yang bikin notif jauh */
.navbar-nav.navbar-nav-right > li.nav-item.dropdown.mr-4{
  margin-right: 0 !important;
}

/* rapatkan layout ul */
.navbar-nav.navbar-nav-right{
  gap: 0 !important;
  column-gap: 0 !important;
}

/* KUNCI: kecilkan "tap area" icon biar ikon terlihat saling dekat */
.navbar-nav.navbar-nav-right a.nav-quick-icon,
.navbar-nav.navbar-nav-right a.notification-dropdown{
  width: 41px !important;     /* makin kecil = makin dempet */
  height: 26px !important;
  padding: 0 !important;
  margin: 0 !important;
}

/* ikon tetap 20px */
.navbar-nav.navbar-nav-right a.nav-quick-icon i,
.navbar-nav.navbar-nav-right a.notification-dropdown i{
  font-size: 20px !important;
}

/* pastikan li icon tidak punya jarak */
.navbar-nav.navbar-nav-right .efidi-navicon-item{
  margin: 0 !important;
  padding: 0 !important;
}

/* geser badge supaya pas di ukuran baru */
.navbar-nav.navbar-nav-right a.nav-quick-icon .notif-count,
.navbar-nav.navbar-nav-right a.notification-dropdown .notif-count{
  top: -6px !important;
  right: -8px !important;
}


</style>


<li class="nav-item dropdown mr-4 efidi-navicon-item">

<a class="nav-link count-indicator position-relative d-flex align-items-center justify-content-center notification-dropdown"
   id="notificationDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
  <i class="mdi mdi-bell mx-0"></i>
  <?php if ($notifCount > 0): ?>
    <span class="notif-count"><?= $notifCount > 99 ? '99+' : (int)$notifCount ?></span>
  <?php endif; ?>
</a>


 <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="notificationDropdown">
  <p class="mb-0 font-weight-normal notif-header">Notifications (24 jam terakhir)</p>

  <?php if ($notifCount === 0): ?>
    <div class="dropdown-item">
      <div class="item-thumbnail">
        <div class="item-icon bg-secondary">
          <i class="mdi mdi-bell-off mx-0"></i>
        </div>
      </div>
      <div class="item-content">
        <h6 class="font-weight-normal mb-0">Tidak ada notifikasi baru</h6>
        <p class="font-weight-light small-text mb-0 text-muted">—</p>
      </div>
    </div>
  <?php else: ?>

  <!-- S A T U  wrapper untuk scroll -->
  <div class="notif-scroll">
    <?php foreach ($notifItems as $i => $it):
      $typ    = $it['typ'] ?? '';
      $isBai  = ($typ === 'bai');
      $title  = $isBai ? 'BAI baru' : 'Order baru';
      $icon   = $isBai ? 'mdi-file-pdf' : 'mdi-file-document';

      $rawTag = $isBai ? ($it['kode_bai'] ?? '') : ($it['no_sc'] ?? '');
      $who    = $it['who'] ?? '';
      $ts     = $it['ts']  ?? '';

      // Palet warna avatar (deterministik)
      $palette = ['#0ea5e9','#22c55e','#eab308','#f97316','#ef4444','#8b5cf6','#14b8a6','#6366f1','#06b6d4','#10b981'];
      $seed    = $rawTag !== '' ? $rawTag : ($it['id'] ?? $i);
      $idx     = abs(crc32((string)$seed)) % count($palette);
      $iconBg  = $palette[$idx];
      $iconFg  = '#fff';

      // Kunci pencocokan BAI
      $key = function_exists('extract_bai_key') ? extract_bai_key((string)$rawTag) : (string)$rawTag;

      // Cek ketersediaan BAI
      $hasBai = false;
      if ($isBai) {
        $hasBai = true;
      } elseif ($key !== '' && isset($pdo) && $pdo instanceof PDO) {
        static $colCode = null, $colFile = null, $stEq = null, $stLike = null;
        if ($colCode === null && $colFile === null) {
          $colCode = pick_col($pdo, 'bai', ['kode_bai','kode','code']);
          $colFile = pick_col($pdo, 'bai', ['filename','nama_file','file_name','file','path','file_path']);
          if ($colCode) $stEq   = $pdo->prepare("SELECT 1 FROM `bai` WHERE `$colCode` = ? LIMIT 1");
          if ($colFile) $stLike = $pdo->prepare("SELECT 1 FROM `bai` WHERE `$colFile` LIKE ? LIMIT 1");
        }
        if ($stEq)   { $stEq->execute([$key]);              $hasBai = (bool)$stEq->fetchColumn(); }
        if (!$hasBai && $stLike) {
          $kw = '%'.$key.'%';
          $stLike->execute([$kw]);                         $hasBai = (bool)$stLike->fetchColumn();
        }
      }

      $lineTitle = $title . ($rawTag ? (' • ' . htmlspecialchars((string)$rawTag, ENT_QUOTES, 'UTF-8')) : '');
      $lineMeta  = ($who ? htmlspecialchars((string)$who, ENT_QUOTES, 'UTF-8') : '-') . ' • ' . human_ago((string)$ts);
    ?>
      <a class="dropdown-item" href="<?= $isBai ? 'bai.php' : 'data.php' ?>">
        <div class="item-thumbnail">
          <div class="item-icon" style="background-color: <?= $iconBg ?>; color: <?= $iconFg ?>;">
            <i class="mdi <?= $icon ?> mx-0"></i>
          </div>
        </div>
        <div class="item-content">
          <h6 class="font-weight-normal mb-0"><?= $lineTitle ?></h6>
          <p class="font-weight-light small-text mb-0 text-muted"><?= $lineMeta ?></p>

          <p class="small-text mb-0" style="display:inline-flex;align-items:center">
            <span>Berita Acara Instalasi:</span>
            <span class="badge rounded-pill text-bg-<?= $hasBai ? 'success' : 'danger' ?> d-inline-flex align-items-center px-2 py-1 ms-1" style="gap:5px">
              <i class="mdi <?= $hasBai ? 'mdi-check-circle-outline' : 'mdi-close-circle-outline' ?>" style="margin:0;font-size:13px;line-height:1"></i>
              <span class="m-0 p-0"><?= $hasBai ? 'Done' : 'Not' ?></span>
            </span>
          </p>
        </div>
      </a>
    <?php endforeach; ?>
  </div><!-- /.notif-scroll -->

  <?php endif; ?>
</div><!-- /.navbar-dropdown -->

</li>
<?php endif; ?>
<style>
  .badge.rounded-pill { font-weight: 600; letter-spacing: .2px; }
  .text-bg-success { background-color: #e9f7ef !important; color: #146c43 !important; border: 1px solid #bfe3cc; }
  .text-bg-danger  { background-color: #fdecec !important; color: #b42318 !important; border: 1px solid #f5b5b5; }

   /* ukuran dropdown & header */
  /* khusus dropdown NOTIFIKASI (ikon lonceng) */
.nav-item .notification-dropdown + .navbar-dropdown{
  width: min(92vw, 420px);
  padding-bottom: .5rem;
}

/* dropdown profil (avatar kanan atas) */
#profileDropdown + .navbar-dropdown{
  width: auto;          /* override width 420px */
  max-width: 220px;     /* batasi kalau isinya nanti banyak */
  min-width: 170px;
  padding: .3rem 0;
  box-shadow: 0 10px 25px rgba(15,23,42,.18);
  border-radius: 2px;
}

/* item di dalam dropdown profil */
#profileDropdown + .navbar-dropdown .dropdown-item{
  font-size: .82rem;
  padding: .35rem 1rem;
}


  .navbar-dropdown .notif-header {
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 2;
    padding: .5rem 1rem;
    margin: 0;
    border-bottom: 1px solid #f1f5f9;
  }

  /* container scroll: tampilkan ~3 item sebelum scroll */
  .navbar-dropdown .notif-scroll {
    max-height: calc(3 * 78px + .75rem); /* kira-kira 3 item; silakan tweak 70–84px */
    overflow-y: auto;
    overscroll-behavior: contain;
  }

  /* rapikan item agar wrapping teks oke */
  .navbar-dropdown .dropdown-item { white-space: normal; padding-top:.5rem; padding-bottom:.5rem; }

  /* scrollbar cantik (opsional) */
  .navbar-dropdown .notif-scroll::-webkit-scrollbar { width: 8px; }
  .navbar-dropdown .notif-scroll::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:8px; }

   /* anchor tetap relative */
.nav-link.count-indicator.position-relative { position: relative; }

/* badge angka di kanan–atas lonceng */
.notif-count{
  position: absolute;
  top: 2px;       /* naikkan ke atas; sesuaikan -4..-10px */
  right: -11px;     /* geser ke kanan; sesuaikan -4..-12px */
  z-index: 10;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  min-width: 12px;
  height: 12px;
  padding: 0 6px;
  font-size: 8px;
  line-height: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 0 2px #fff;
  pointer-events: none;
}
/* Avatar di navbar (dropdown profile) – jangan potong rambut :) */
.nav-profile img{
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  object-position: top center;  /* fokus crop ke bagian atas */
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
}

/* sembunyikan badge bawaan tema jika ada */
.nav-link.count-indicator .count { display: none !important; }

</style>
<style>
  /* ==== Khusus item Search, Akses Login, Akses Bot ==== */

  /* Search + Finder badge */
  .sidebar .nav .nav-item.nav-search .nav-link{
    display:flex;
    align-items:center;
    justify-content:space-between;
  }
  .sidebar .nav .nav-item.nav-search .nav-left{
    display:flex;
    align-items:center;
    gap:.5rem;
  }
  .sidebar .nav .finder-badge{
    font-size:.68rem;
    padding:.1rem .6rem;
    border-radius:999px;
    background:linear-gradient(135deg, #def2f632, #a9e0e636);
    color:#0f3e47;
    font-weight:600;
    letter-spacing:.02em;
    box-shadow:0 0 0 1px rgba(255,255,255,.6);
    white-space:nowrap;
    border:1px solid rgba(12, 114, 135, 0.09); /* border tipis */
  }
  .sidebar .nav .nav-item.nav-search.active .finder-badge{
   
  }

  /* Akses Bot: icon dengan background gradasi putih–tosca */
  
  /* icon Akses Bot: lingkaran putih–tosca di tengah */
.sidebar .nav .nav-item .menu-icon.icon-bot{
  display: inline-flex;               /* biar bisa center */
  align-items: center;
  justify-content: center;
  width: 29px;                        /* perbesar lingkaran */
  height: 29px;
  border-radius: 999px;
  padding: 0;                         /* jangan pakai padding */
  line-height: 1;
  font-size: 16px;                    /* ukuran icon di dalam */
  background: radial-gradient(circle, #e5f7f9, #cceff4); /* pusat di tengah */
 
}

.sidebar .nav .nav-item.active .menu-icon.icon-bot{
  background: radial-gradient(circle, #e5f7f9, #cceff4);
 
}


  /* Akses Login: aksen garis tipis di kiri (tanpa label / chip) */
  .sidebar .nav .nav-item.nav-login .nav-link{
    position:relative;
    padding-left:1.35rem;
  }
  .sidebar .nav .nav-item.nav-login .nav-link::before{
  content:'';
  position:absolute;
  left:.7rem;
  top:50%;
  transform:translateY(-50%);
  width:2px;
  height:55%;
  border-radius:999px;
  /* lebih cerah & tosca */
  background: radial-gradient(circle, #cceff4, #8fd8df);
}

.sidebar .nav .nav-item.nav-login.active .nav-link::before{
  background: radial-gradient(circle, #cceff4, #8fd8df);
  height:65%;
}
/* teks Search: title + subtitle */
.sidebar .nav .nav-item.nav-search .nav-feature-text{
  display:flex;
  flex-direction:column;
  line-height:1.1;
  margin-left:-8px;
}

.sidebar .nav .nav-item.nav-search .nav-feature-text .menu-title{
  font-size:.82rem;
  font-weight:500;
}

.sidebar .nav .nav-item.nav-search .nav-feature-text .menu-subtitle{
  font-size:.70rem;
  color:#6b7280;
}

</style>



          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              
             <img src="<?= htmlspecialchars($iconUrl) ?>" alt="profile" />

              <span class="nav-profile-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
              <!--<a class="dropdown-item">-->
              <!--  <i class="mdi mdi-settings text-primary"></i>-->
              <!--  Settings-->
              <!--</a>-->
             <a class="dropdown-item logout-trigger" href="#">
  <i class="mdi mdi-logout text-primary"></i>
  Logout
</a>

            </div>
          </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="mdi mdi-menu"></span>
        </button>
      </div>
    </nav>
    <!-- partial -->

    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar.html -->
<?php
// helper
// helper
if (!function_exists('is_role')) {
  function is_role(string $want, string $have): bool {
    return strtolower($want) === strtolower($have);
  }
}

if (!function_exists('nav_item_active')) {
  /**
   * Hanya aktif kalau nama file persis sama.
   * Boleh string atau array nama file.
   */
  function nav_item_active(string|array $files): string {
    $cur = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');

    if (is_array($files)) {
      return in_array($cur, $files, true) ? ' active' : '';
    }

    return $cur === $files ? ' active' : '';
  }
}

?>


<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">

    <!-- ========= ADMIN ========= -->
    <?php if (is_role('admin', $role)) { ?>
        <li class="nav-item<?= nav_item_active('dashboard_admin.php') ?>">
        <a class="nav-link" href="dashboard_admin.php">
          <i class="mdi mdi-home menu-icon"></i>
          <span class="menu-title">Dashboard</span>
        </a>
      </li>
     <li class="nav-item nav-search<?= nav_item_active('search.php') ?>">
  <a class="nav-link" href="search.php">
    <div class="nav-left">
      <i class="mdi mdi-magnify menu-icon"></i>
      <div class="nav-feature-text">
        <span class="menu-title">Search</span>
        <span class="menu-subtitle">Order • BAI • Foto</span>
      </div>
    </div>
    <span class="finder-badge">Finder</span>
  </a>
</li>


       <li class="nav-item<?= nav_item_active('detail.php') ?>">
        <a class="nav-link" href="detail.php">
          <i class="mdi mdi-information-outline menu-icon"></i>
          <span class="menu-title">Detail</span>
        </a>
      </li>
      <li class="nav-item<?= nav_item_active('bai.php') ?>">
        <a class="nav-link" href="bai.php">
          <i class="mdi mdi-file-pdf menu-icon"></i>
          <span class="menu-title">Berita Acara Instalasi</span>
        </a>
      </li>
            <li class="nav-item<?= nav_item_active('dokumentasi.php') ?>">
        <a class="nav-link" href="dokumentasi.php">
          <i class="mdi mdi-image-multiple-outline menu-icon"></i>
          <span class="menu-title">Evidensi Lapangan</span>
        </a>
      </li>
      <li class="nav-item<?= nav_item_active('data.php') ?>">
        <a class="nav-link" href="data.php">
          <i class="mdi mdi-file-document-multiple-outline menu-icon"></i>
          <span class="menu-title">Data Orders</span>
        </a>
      </li>
<li class="nav-item<?= nav_item_active('import_data.php') ?>">
  <a class="nav-link" href="import_data.php">
    <!-- Icon khusus IMPORT DATA -->
    <i class="mdi mdi-database-import menu-icon"></i>

    <!-- Teks + bintang merah -->
    <span class="menu-title">
      Import Data
      <span class="menu-badge-star">*</span>
    </span>
  </a>
</li>

      
    <li class="nav-item nav-login<?= nav_item_active('akses_login.php') ?>">
  <a class="nav-link" href="akses_login.php">
    <i class="mdi mdi-lock menu-icon"></i>
    <span class="menu-title">Akses Login</span>
  </a>
</li>

      <li class="nav-item<?= nav_item_active('admin.php') ?>">
        <a class="nav-link" href="admin.php">
          <i class="mdi mdi-account-tie menu-icon"></i>
          <span class="menu-title">Data Admin</span>
        </a>
      </li>
      <li class="nav-item<?= nav_item_active('ta.php') ?>">
        <a class="nav-link" href="ta.php">
          <i class="mdi mdi-account-tag menu-icon"></i>
          <span class="menu-title">Data TA</span>
        </a>
      </li>
      <li class="nav-item<?= nav_item_active('tech.php') ?>">
        <a class="nav-link" href="tech.php">
          <i class="mdi mdi-account-hard-hat menu-icon"></i>
          <span class="menu-title">Data Tech</span>
        </a>
      </li>
      
      <li class="nav-item<?= nav_item_active('akses_bot.php') ?>">
        <a class="nav-link" href="akses_bot.php">
          <i class="mdi mdi-robot-happy-outline menu-icon icon-bot"></i>
          <span class="menu-title">Akses Bot</span>
        </a>
      </li>
      

    <?php } ?>

    <!-- ========= TA ========= -->
    <?php if (is_role('ta', $role)) { ?>
     <li class="nav-item nav-search<?= nav_item_active('search.php') ?>">
  <a class="nav-link" href="search.php">
    <div class="nav-left">
      <i class="mdi mdi-magnify menu-icon"></i>
      <div class="nav-feature-text">
        <span class="menu-title">Search</span>
        <span class="menu-subtitle">Order • BAI • Foto</span>
      </div>
    </div>
    <span class="finder-badge">Finder</span>
  </a>
</li>
      <li class="nav-item<?= nav_item_active('dashboard_ta.php') ?>">
        <a class="nav-link" href="dashboard_ta.php">
          <i class="mdi mdi-home menu-icon"></i>
          <span class="menu-title">Dashboard</span>
        </a>
      </li>
       <li class="nav-item<?= nav_item_active('detail.php') ?>">
        <a class="nav-link" href="detail.php">
          <i class="mdi mdi-information-outline menu-icon"></i>
          <span class="menu-title">Detail</span>
        </a>
      </li>
      <li class="nav-item<?= nav_item_active('bai.php') ?>">
        <a class="nav-link" href="bai.php">
          <i class="mdi mdi-file-pdf menu-icon"></i>
          <span class="menu-title">Berita Acara Instalasi</span>
        </a>
      </li>
       <li class="nav-item<?= nav_item_active('dokumentasi.php') ?>">
        <a class="nav-link" href="dokumentasi.php">
          <i class="mdi mdi-image-multiple-outline menu-icon"></i>
          <span class="menu-title">Evidensi Lapangan</span>
        </a>
      </li>
      <li class="nav-item<?= nav_item_active('data.php') ?>">
        <a class="nav-link" href="data.php">
          <i class="mdi mdi-file-document-multiple-outline menu-icon"></i>
          <span class="menu-title">Data Orders</span>
        </a>
      </li>
      <li class="nav-item<?= nav_item_active('import_data.php') ?>">
  <a class="nav-link" href="import_data.php">
    <!-- Icon khusus IMPORT DATA -->
    <i class="mdi mdi-database-import menu-icon"></i>

    <!-- Teks + bintang merah -->
    <span class="menu-title">
      Import Data
      <span class="menu-badge-star">*</span>
    </span>
  </a>
</li>
      
      
    <?php } ?>

    <!-- ========= TECH ========= -->
    <?php if (is_role('tech', $role)) { ?>
        <li class="nav-item<?= nav_item_active('search.php') ?>">
        <a class="nav-link" href="search.php">
          <i class="mdi mdi-magnify menu-icon"></i>
          <span class="menu-title">Search</span>
        </a>
      </li>
    <?php } ?>

    <!-- ========= COMMON ========= -->
    <li class="nav-item<?= nav_item_active('logout.php') ?>">
  <a class="nav-link logout-trigger" href="#">
    <i class="mdi mdi-logout menu-icon"></i>
    <span class="menu-title">Logout</span>
  </a>
</li>

<!-- Modal Logout -->

<style>
/* ==== MODAL LOGOUT EFIDI ==== */
.logout-modal .modal-content{
  border-radius: 13px;
  overflow: hidden;
  position: relative;
}

/* garis aksen tosca di atas modal */
.logout-modal .modal-content::before{
  content:'';
  position:absolute;
  top:0; left:0; right:0;
  height:4px;
  background: linear-gradient(90deg, #00a3ad, #06b6d4);
}
.menu-badge-star {
  color: #00a3ad;     /* merah: #DC2626 */
  margin-left: 4px;
  font-weight: 700;
  font-size: 16px;
  position: relative;
  top: -1px;          /* sedikit naik biar sejajar teks */
}

/* icon lingkaran di header */
.logout-modal .logout-icon{
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle, #ecfeff, #e0f2fe);
  color: #0f766e;
  box-shadow: 0 0 0 1px rgba(148,163,184,.35);
  font-size: 20px;
}

/* kartu user di dalam modal */
.logout-modal .logout-user-card{
  display: flex;
  align-items: center;
  padding: .75rem .9rem;
  border-radius: 12px;
  background: linear-gradient(135deg, #f8f9faff, #f8f9faff);
  border: 1px solid #d1d5db;
}

.logout-modal .avatar-wrapper{
  flex-shrink: 0;
}

.logout-modal .avatar-img{
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
  object-position: top center;      /* ⬅ fokus crop ke bagian atas */
  border: 1px solid #e5e7eb;
  background: #f3f4f6;
}

.logout-modal .logout-name{
  font-size: .9rem;
  font-weight: 600;
  color: #0f172a;
}

.logout-modal .logout-role-badge .badge{
  font-size: .65rem;
  letter-spacing: .04em;
}

/* padding modal */
.logout-modal .modal-header{
  padding: 1rem 1.25rem .25rem;
}
.logout-modal .modal-body{
  padding: 0 1.25rem 1rem;
}
.logout-modal .modal-footer{
  padding: .75rem 1.25rem 1.1rem;
}

/* tombol rounded pill */
.logout-modal .btn-pill{
  border-radius: 999px;
}

/* tombol batal */
.logout-modal .btn-light{
  border-color: #e5e7eb;
  padding: .35rem .9rem;
  font-size: .78rem;
}

/* tombol keluar (destruktif) */
.logout-modal .btn-danger{
  padding: .35rem 1.05rem;
  font-size: .78rem;
  background: linear-gradient(135deg, #ef4444, #ef4444);
  border: none;
}
.logout-modal .btn-danger:hover{
  filter: brightness(.97);
}

.logout-modal .close{
  outline: none;
}

/* =========================================================
   EFIDI SIDEBAR HIGHLIGHT: icon + text (seperti "Horizons")
   Tempel paling bawah agar override.
   ========================================================= */

/* 0) Matikan style lama yang cuma kasih background ke teks */
.sidebar .nav .nav-item .nav-link .menu-title,
.sidebar .nav .nav-item.nav-search .nav-feature-text{
  background: transparent !important;
  padding: 0 !important;
  border-radius: 0 !important;
}

/* 1) Hapus garis pemisah antar menu */
.sidebar .nav,
.sidebar .nav .nav-item,
.sidebar .nav .nav-item .nav-link{
  border: 0 !important;
  box-shadow: none !important;
}

/* 2) Jarak antar menu sedikit dilebarin + bentuk item */
.sidebar .nav .nav-item{
  margin: .15rem 0 !important;
}

/* Link dibuat seperti "pill row" (seperti gambar 2) */
.sidebar .nav .nav-item .nav-link{
  margin: 0 .60rem !important;     /* biar tidak mepet tepi sidebar */
  border-radius: 7px !important;  /* rounded seperti contoh */
  padding: .70rem .90rem !important;
  display: flex !important;
  align-items: center !important;
  gap: .65rem !important;
  background: transparent !important;
  transition: background .15s ease, color .15s ease;
}

/* 3) Hover / Active / Touch: background hitam (tapi soft), icon+teks jadi hitam */
.sidebar .nav .nav-item:hover > .nav-link,
.sidebar .nav .nav-item.active > .nav-link,
.sidebar .nav .nav-item > .nav-link:active,
.sidebar .nav .nav-item > .nav-link:focus{
  background: rgba(0, 0, 0, 0.05) !important; /* “hitam” soft -> tampak abu seperti contoh */
  color: #000 !important;
}

/* icon ikut hitam */
.sidebar .nav .nav-item:hover > .nav-link .menu-icon,
.sidebar .nav .nav-item.active > .nav-link .menu-icon,
.sidebar .nav .nav-item > .nav-link:active .menu-icon,
.sidebar .nav .nav-item > .nav-link:focus .menu-icon{
  color: #000 !important;
}

/* teks utama + subtitle (Search) ikut hitam */
.sidebar .nav .nav-item:hover > .nav-link .menu-title,
.sidebar .nav .nav-item.active > .nav-link .menu-title,
.sidebar .nav .nav-item:hover > .nav-link .menu-subtitle,
.sidebar .nav .nav-item.active > .nav-link .menu-subtitle{
  color: #000 !important;
}

/* rapikan focus outline */
.sidebar .nav .nav-item .nav-link:focus{
  outline: none !important;
}


</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // pakai jQuery Bootstrap 4
  var $logoutModal = $('#logoutModal');

  // semua link yang punya class .logout-trigger akan buka modal ini
  $('.logout-trigger').on('click', function (e) {
    e.preventDefault();
    $logoutModal.modal('show');
  });

  // kalau user setuju logout
  $('#logout-confirm-btn').on('click', function () {
    window.location.href = 'logout.php';
  });
});
</script>


  </ul>
</nav>
<div class="modal fade logout-modal" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content shadow-lg border-0">
      
      <!-- Header -->
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center">
          <div class="logout-icon me-3">
            <i class="mdi mdi-alert-circle-outline"></i>
          </div>
          <div>
            <h5 class="modal-title mb-1" id="logoutModalLabel">Konfirmasi Logout</h5>
            <p class="mb-0 small text-muted">Anda akan keluar dari sesi efidi. Dashboard</p>
          </div>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body pt-3">
        <div class="logout-user-card mb-3">
          <div class="avatar-wrapper me-3">
            <img src="<?= htmlspecialchars($iconUrl) ?>" alt="profile" class="avatar-img">
          </div>
          <div class="flex-grow-1">
            <div class="logout-name">
              <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="logout-role-badge mt-1">
              <?php
                $roleLabel = strtoupper((string)$role ?: 'USER');
                $roleColor = function_exists('role_color') ? role_color((string)$role) : '#182958';
              ?>
              <span class="badge badge-pill" style="background: <?= $roleColor ?>1a; color: <?= $roleColor ?>; border:1px solid <?= $roleColor ?>33;">
                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
          </div>
        </div>

        <p class="mb-1 small text-muted">
          Pastikan seluruh perubahan data yang penting sudah tersimpan.
        </p>
        <p class="mb-0 small text-muted">
          Apakah Anda yakin ingin mengakhiri sesi ini sekarang?
        </p>
      </div>

      <!-- Footer -->
      <div class="modal-footer border-0 pt-0 d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-light btn-sm btn-pill" data-dismiss="modal">
          <i class="mdi mdi-arrow-left mr-1"></i> Batal
        </button>
        <button type="button" class="btn btn-danger btn-sm btn-pill" id="logout-confirm-btn">
          <i class="mdi mdi-logout-variant mr-1"></i> Ya, Keluar
        </button>
      </div>

    </div>
  </div>
</div>
