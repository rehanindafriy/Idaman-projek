<?php
// orders_all.php
// Web App Telegram: daftar ALL ORDER (semua kolom kecuali bai_id & foto_id)
// dengan chunk 100 data per halaman (p=1,2,3… + filter multi-select)

require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}
// ==== TELEGRAM ACCESS GUARD UNTUK WEB_APP ADMIN/TA ====
$src = isset($_GET['src']) ? (string)$_GET['src'] : '';
$cid = isset($_GET['cid']) ? (int)$_GET['cid'] : 0;
$u   = isset($_GET['u'])   ? (string)$_GET['u']   : '';

// Hanya guard kalau dipanggil dari Telegram (src=telegram_admin)
if ($src === 'telegram_admin') {
  $status = 'unknown';
  $role   = '';
  try {
    if ($cid > 0 && isset($pdo) && $pdo instanceof PDO) {
      $st = $pdo->prepare("
        SELECT status, role
        FROM akses_login
        WHERE chat_id = :cid
        LIMIT 1
      ");
      $st->execute([':cid' => $cid]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $status = strtolower((string)$row['status']);
        $role   = strtolower((string)$row['role']);
      } else {
        // chat_id tidak ditemukan, anggap pending
        $status = 'pending';
      }
    } else {
      // cid kosong / DB tidak siap → treat as pending
      $status = 'pending';
    }
  } catch (Throwable $e) {
    // Kalau ada error DB, jangan bocor detail; anggap pending
    $status = 'pending';
  }

  $allowed = ($status === 'active' && in_array($role, ['admin','ta'], true));

    if (!$allowed) {
    // Redirect ke pending_page.php (blokir tombol lama & user non-active)
    $q = http_build_query([
      'status' => $status,
      'src'    => 'telegram_admin',
      'page'   => 'orders_all',
      'u'      => $u,    // kirim username
      'cid'    => $cid,  // kirim chat_id
    ]);
    header('Location: pending_page.php?' . $q);
    exit;
  }

}
// ==== END TELEGRAM ACCESS GUARD ====


/**
 * Ambil daftar kolom dari tabel orders
 * lalu buang bai_id & foto_id.
 */
try {
  $res = $pdo->query("SHOW COLUMNS FROM orders");
  $allCols = $res->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB Error (columns): " . e($e->getMessage());
  exit;
}

$exclude = ['bai_id', 'foto_id'];
$cols    = [];
foreach ($allCols as $c) {
  if (in_array($c, $exclude, true)) continue;
  $cols[] = $c;
}

if (!$cols) {
  echo "Tidak ada kolom ORDER yang bisa ditampilkan.";
  exit;
}

/* ==========================================================
 *  MULTI-SELECT SEARCH ala "SEMUA KOLUM" (NO FIELD DROPDOWN)
 * ==========================================================*/

/**
 * Siapkan pool nilai distinct dari:
 *  - no_sc
 *  - no_internet
 *  - cp_pelanggan
 *  - nama_pelanggan
 * untuk dijadikan opsi TomSelect (ORDER_OPTIONS_ALL).
 */
$orderOptionsByField = [
  'no_sc'          => [],
  'no_internet'    => [],
  'cp_pelanggan'   => [],
  'nama_pelanggan' => [],
];

try {
  foreach (array_keys($orderOptionsByField) as $col) {
    $sqlOpt = "
      SELECT DISTINCT `$col` AS val
      FROM `orders`
      WHERE `$col` IS NOT NULL AND `$col` <> ''
      ORDER BY val DESC
    ";
    $stOpt = $pdo->query($sqlOpt);
    $orderOptionsByField[$col] = $stOpt->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }
} catch (Throwable $e) {
  // kalau error, biarkan kosong saja
}

$orderOptionsAll = array_values(array_unique(array_merge(
  $orderOptionsByField['no_sc'],
  $orderOptionsByField['no_internet'],
  $orderOptionsByField['cp_pelanggan'],
  $orderOptionsByField['nama_pelanggan']
)));

/**
 * Ambil nilai yang dipilih di multi-select (GET supaya ikut di p=…)
 * parameter: codes[]
 */
$orderCodes = (isset($_GET['codes']) && is_array($_GET['codes']))
  ? array_values(array_filter($_GET['codes']))
  : [];

/**
 * Bangun WHERE untuk semua kolom sekaligus (mode "Semua").
 */
$whereSql = '';
if (!empty($orderCodes)) {
  $vals = [];
  foreach ($orderCodes as $val) {
    $val = trim((string)$val);
    if ($val === '') continue;
    $vals[] = $pdo->quote($val);
  }
  $vals = array_values(array_unique($vals));
  if ($vals) {
    $in = implode(',', $vals);
    $whereSql =
      " WHERE (no_sc IN ($in)
           OR no_internet IN ($in)
           OR cp_pelanggan IN ($in)
           OR nama_pelanggan IN ($in))";
  }
}

/* ==========================
 *  HITUNG TOTAL + CHUNKING
 * ==========================*/

try {
  $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM orders{$whereSql}")->fetchColumn();
} catch (Throwable $e) {
  $totalRows = 0;
}

// Konfigurasi chunk (setiap "halaman luar" berisi 100 data)
$chunkSize = 100;
$page      = max(1, (int)($_GET['p'] ?? 1));
$maxPage   = $totalRows > 0 ? max(1, (int)ceil($totalRows / $chunkSize)) : 1;
if ($page > $maxPage) $page = $maxPage;

$offset   = ($page - 1) * $chunkSize;
$startRow = $totalRows > 0 ? ($offset + 1) : 0;
$endRow   = min($offset + $chunkSize, $totalRows);

// Susun SELECT hanya kolom yang diizinkan
$colListSql = implode(', ', array_map(static fn($c) => "`{$c}`", $cols));

// Susun ORDER BY yang elegan (paling baru di atas)
$orderParts = [];
if (in_array('created_at', $cols, true)) $orderParts[] = "`created_at` DESC";
if (in_array('id_order',   $cols, true)) $orderParts[] = "`id_order` DESC";
$orderSql = $orderParts ? (' ORDER BY '.implode(', ', $orderParts)) : '';

// LIMIT + OFFSET per chunk + WHERE (jika ada)
$sql = "SELECT {$colListSql} FROM orders{$whereSql}{$orderSql} LIMIT {$chunkSize} OFFSET {$offset}";

try {
  $st = $pdo->query($sql);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB Error (data): " . e($e->getMessage());
  exit;
}

// Helper untuk judul kolom
function human_col(string $name): string {
  return str_replace('_', ' ', strtoupper($name));
}

/**
 * Build URL untuk navigasi chunk (p=…),
 * tetap menjaga query string lain (misal src=telegram_admin & filter codes[]).
 */
function build_chunk_url(int $targetPage): string {
  $base  = strtok($_SERVER['REQUEST_URI'], '?');
  $qsArr = $_GET;
  $qsArr['p'] = $targetPage;
  return $base . '?' . http_build_query($qsArr);
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>ALL ORDER · EFIDI</title>

  <!-- Responsive untuk web-app Telegram -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- DataTables CSS via CDN -->
  <link rel="stylesheet"
        href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

  <!-- TomSelect CSS -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

  <style>
  :root {
    /* palet terang efidi (mirip search_bot.php) */
    --es-teal:        #00a3ad;
    --es-teal-dark:   #00808b;
    --es-bg-body:     #f3f4f6;
    --es-bg-shell:    #ffffff;
    --es-bg-soft:     #e5f7f9;
    --es-text-main:   #04313a;
    --es-text-muted:  #64748b;
    --es-border-soft: #dbeafe;
  }

  * { box-sizing: border-box; }

  body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin: 0;
    padding: 4px;
    background: var(--es-bg-body);
    color: var(--es-text-main);
  }

  /* container utama di dalam webview telegram */
  .shell {
    width: 100%;
    max-width: 1350px;
    margin: 0 auto;
    background: var(--es-bg-shell);
    border-radius: 14px;
    padding: 12px 14px 16px;
    box-shadow:
      0 18px 40px rgba(15,23,42,0.12),
      0 0 0 1px rgba(226,232,240,0.9);
  }

 .header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 2px 10px;
  margin-bottom: 8px;

  /* balik ke gaya awal: hanya garis bawah, tanpa card / warna background */
  border-bottom: 1px solid #e5e7eb;
  border-radius: 0;
  background: transparent;
}

  .efidi-logo {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    flex-shrink: 0;
    background: #ffffffdd;
    border: 1px solid rgba(148,163,184,0.5);
    box-shadow: 0 6px 14px rgba(15,23,42,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .efidi-logo img {
    width: 70%;
    height: 70%;
    object-fit: contain;
    display: block;
  }

  .header-text { flex: 1; min-width: 0; }

  .header-title {
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--es-text-main);
  }

  .header-title-emoji { font-size: 18px; }

  .header-subtitle {
    font-size: 11px;
    color: var(--es-text-muted);
    margin-top: 2px;
  }

  .tag-chip {
    font-size: 10px;
    padding: 2px 9px;
    border-radius: 999px;
    /* GRADIASI HANYA DI SINI (LIVE DATA) */
    background: linear-gradient(135deg, #ffffff, #e5f7f9 45%, #00a4ad3a 100%);
    color: #022c32;
    border: 1px solid rgba(0,163,173,0.45);
    text-transform: uppercase;
    letter-spacing: 0.09em;
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .tag-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: radial-gradient(circle, #00a3ad, #00808b);
  }

  /* ============ MULTI SELECT SEARCH (EFIDI LIGHT STYLE) ============ */
  .multi-search-card{
    margin: 8px 0 6px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid var(--es-border-soft);
    background: #f5fbfc;
    box-shadow:
      0 8px 22px rgba(15,23,42,0.06);
  }

  .multi-search-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:4px;
  }

  .multi-search-title{
    font-size:11px;
    font-weight:600;
    letter-spacing:.14em;
    text-transform:uppercase;
    color:#0f172a;
  }

  .multi-search-chip{
    padding:3px 9px;
    border-radius:999px;
    font-size:10px;
    font-weight:500;
    letter-spacing:.10em;
    text-transform:uppercase;
    /* GRADIASI HANYA DI SINI (tulisan Multi Select) */
    background: linear-gradient(135deg, #ffffff, #e5f7f9 40%, #00a4ad3a 100%);
    color:#022c32;
    border:1px solid rgba(0,163,173,0.45);
  }

  .multi-search-sub{
    font-size:10px;
    color:var(--es-text-muted);
    margin-bottom:6px;
  }

  .multi-search-row{
    display:flex;
    align-items:center;
    gap:6px;
    flex-wrap:nowrap;
  }

  .multi-search-select{
    flex:1 1 auto;
    min-width:0;
  }

  .multi-search-select .ts-control{
    min-height:34px;
    border-radius:9px;
    border:1px solid #cbd5e1;
    background: #ffffff;
    color:#0f172a;
    padding:4px 10px;
    font-size:11px;
    box-shadow:
      0 0 0 1px rgba(148,163,184,0.25);
  }
  .multi-search-select .ts-control input{
    font-size:11px;
    color:#0f172a;
  }
  .multi-search-select .ts-control::placeholder,
  .multi-search-select .ts-control input::placeholder{
    color:#9ca3af;
    opacity:0.95;
  }

  .multi-search-select .ts-control .item {
    /* BUKAN gradasi, hanya solid teal */
    background: #00a3ad;
    border-radius: 3px;
    padding: 2px 8px;
    margin: 2px 4px 2px 0;
    border: 0;
    font-size: 10px;
    font-weight: 600;
    color: #022c32;
  }
  .multi-search-select .ts-control .item .remove {
    color: #022c32;
    opacity: .7;
  }
  .multi-search-select .ts-control .item .remove:hover {
    opacity: 1;
  }

  .multi-search-select .ts-dropdown{
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 11px;
    color: #0f172a;
    box-shadow:
      0 18px 40px rgba(15,23,42,0.12);
  }
  .multi-search-select .ts-dropdown .option{
    padding: 4px 8px;
    color: #0f172a;
  }
  .multi-search-select .ts-dropdown .option.active{
    background: #e0f2fe;
    color: #03606c;
  }
  .multi-search-select .ts-dropdown .option.selected{
    background: #f1f5f9;
    color: #022c32;
  }

  .multi-search-actions{
    flex:0 0 auto;
    display:flex;
    align-items:center;
    gap:4px;
  }

  .multi-search-btn,
  .multi-search-reset{
    width:34px;
    height:34px;
    border-radius:9px;
    border:1px solid transparent;
    padding:0;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    font-size:15px;
  }

  /* Search = solid teal (TANPA gradasi) */
  .multi-search-btn{
    background:#00a3ad;
    border-color:#00a3ad;
    color: #ffffffff;
   
  }
  .multi-search-btn:hover{
    filter:brightness(1.03);
  }

  /* Reset = abu-abu muda */
  .multi-search-reset{
    background:#e5e7eb;
    border-color:#d4d4d8;
    color:#111827;
  }
  .multi-search-reset:hover{
    background:#d4d4d8;
  }

  .search-alert{
    margin-top:5px;
    font-size:10px;
    padding:4px 8px;
    border-radius:8px;
    border:1px solid rgba(34,197,94,0.45);
    background:rgba(220,252,231,0.9);
    color:#166534;
  }
  .search-alert b{ color:#022c32; }

  .ts-loading-option{
    display:flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    font-size:11px;
    color:#00a3ad;
  }
  .ts-empty-hint{
    padding:4px 8px;
    font-size:11px;
    color:#9ca3af;
  }
  .ts-empty-hint strong{
    color:#0f172a;
  }
  .ts-spinner{
    width:12px;
    height:12px;
    border-radius:999px;
    border:2px solid rgba(148,163,184,0.45);
    border-top-color:#00a3ad;
    animation:ts-spin .75s linear infinite;
  }
  @keyframes ts-spin{
    to { transform: rotate(360deg); }
  }

  /* ============ CHUNK NAV ============ */
  .chunk-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 8px 2px 6px;
    font-size: 11px;
    color: var(--es-text-muted);
    gap: 10px;
    flex-wrap: wrap;
  }
  .chunk-left {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }
  .chunk-text { white-space: nowrap; }
  .chunk-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .chunk-select-label {
    font-size: 11px;
    color: var(--es-text-muted);
  }
  .chunk-select {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: var(--es-text-main);
    outline: none;
  }

  /* ============ TABLE / DATATABLES – EFIDI LIGHT ============ */
    /* ============ TABLE / DATATABLES – EFIDI LIGHT (mirip default) ============ */
    /* ============ TABLE / DATATABLES – EFIDI LIGHT (mirip default) ============ */
  .table-wrapper {
    margin-top: 8px;
    border-radius: 10px;
    padding: 6px 8px 10px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(15,23,42,0.06);
    overflow: auto; /* supaya scrollX tetap enak */
  }

  table.dataTable {
    width: 100% !important;
    border-collapse: separate;
    border-spacing: 0;
    background-color: #ffffff;
  }

  /* HEADER kolom – sangat mirip bawaan DataTables */
  table.dataTable thead th {
    padding: 8px 10px;
    background-color: #f9fafb;
    color: #111827;
    border-bottom: 1px solid #ddd;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
  }

  /* STRIPE: selang-seling mirip default */
  table.dataTable.display tbody tr.odd {
    background-color: #f9f9f9;
  }
  table.dataTable.display tbody tr.even {
    background-color: #ffffff;
  }

  /* SEL body */
  table.dataTable tbody td {
    padding: 6px 10px;
    font-size: 11px;
    color: #111827;
    border-bottom: 1px solid #eee;
    white-space: nowrap;
  }

  /* HOVER baris – lembut */
  table.dataTable.display tbody tr:hover {
    background-color: #f1f5f9;
  }

  /* baris terpilih (kalau ada fitur select nanti) */
  table.dataTable tbody tr.selected,
  table.dataTable.display tbody tr.selected {
    background-color: #e0f2fe;
  }

  /* Kontrol DataTables (length, search, paginate, info) */
  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter {
    color: #111827;
    font-size: 11px;
    margin-bottom: 4px;
  }

  .dataTables_wrapper .dataTables_length select,
  .dataTables_wrapper .dataTables_filter input {
    background-color: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    color: #111827;
    padding: 2px 6px;
    font-size: 11px;
    outline: none;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button {
    font-size: 11px;
  }

  .dataTables_wrapper .dataTables_info {
    font-size: 10px;
    color: #6b7280;
  }


  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div class="efidi-logo">
        <img src="images/logoo.png" alt="EFIDI Logo">
      </div>

      <div class="header-text">
        <div class="header-title">
          <span class="header-title-emoji">📋</span>
          <span>ALL ORDER</span>
        </div>
        <div class="header-subtitle">
          Seluruh data order yang tersimpan di EFIDI (chunk per 100 data, tanpa kolom internal bai_id &amp; foto_id).
        </div>
        <div class="tag-chip">
          <span class="tag-dot"></span>
          <span>LIVE DATA · <span id="liveClock"></span> WIB</span>
        </div>
      </div>
    </div>

    <!-- ================= MULTI SELECT SEARCH ================= -->
    <div class="multi-search-card">
      <form id="multiSearchForm" method="get">
        <?php
          // pertahankan parameter lain (misal src=telegram_admin), tapi reset p ke 1 saat search
          foreach ($_GET as $k => $v) {
            if ($k === 'p' || $k === 'codes') continue;
            if (is_array($v)) continue;
            echo '<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
          }
        ?>
        <input type="hidden" name="p" id="hiddenPage" value="1">

        <div class="multi-search-header">
          <div class="multi-search-title">Filter Orders</div>
          <div class="multi-search-chip">Multi Select</div>
        </div>
        <div class="multi-search-sub">
          Ketik minimal 2 karakter lalu pilih 1 atau lebih nilai.
        </div>

        <div class="multi-search-row">
          <div class="multi-search-select">
            <select id="orderCodesSelect" name="codes[]" multiple style="width:100%"></select>
          </div>
          <div class="multi-search-actions">
            <button type="submit" class="multi-search-btn" title="Search">
              <i class="mdi mdi-magnify"></i>
            </button>
            <button type="button" id="btnResetFilter" class="multi-search-reset" title="Reset filter">
              <i class="mdi mdi-close"></i>
            </button>
          </div>
        </div>

        <?php if (!empty($orderCodes) && $totalRows >= 0): ?>
          <div class="search-alert">
            Ditemukan <b><?= number_format($totalRows,0,',','.') ?></b> data
            untuk kode:
            <b><?= e(implode(', ', array_unique(array_map('strval', $orderCodes)))) ?></b>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- ================== INFO CHUNK ================== -->
    <div class="chunk-nav">
      <div class="chunk-left">
        <span class="chunk-text">
          Halaman <b><?= e($page) ?></b> dari <b><?= e($maxPage) ?></b>
          &middot; baris <b><?= e($startRow) ?></b>–<b><?= e($endRow) ?></b>
          &middot; total <b><?= e($totalRows) ?></b> ORDER
        </span>
      </div>
      <div class="chunk-right">
        <label for="orderChunkSelect" class="chunk-select-label">Halaman</label>
        <select id="orderChunkSelect" class="chunk-select">
          <?php for ($i = 1; $i <= $maxPage; $i++): ?>
            <option value="<?= e(build_chunk_url($i)) ?>" <?= $i === $page ? 'selected' : '' ?>>
              <?= $i ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
    </div>

    <div class="table-wrapper">
      <table id="ordersTable" class="display nowrap">
        <thead>
          <tr>
            <?php foreach ($cols as $c): ?>
              <th><?= e(human_col($c)) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($cols as $c): ?>
                <td><?= e($row[$c] ?? '') ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- jQuery + DataTables JS via CDN -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

  <!-- TomSelect JS -->
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const clockEl = document.getElementById('liveClock');

      if (clockEl) {
        function updateLiveClock() {
          const now = new Date();

          // paksa ke WIB (UTC+7)
          const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
          const wib = new Date(utc + 7 * 60 * 60 * 1000);

          const day   = String(wib.getDate()).padStart(2, '0');
          const monthIdx = wib.getMonth();
          const year  = wib.getFullYear();
          const h     = String(wib.getHours()).padStart(2, '0');
          const m     = String(wib.getMinutes()).padStart(2, '0');
          const s     = String(wib.getSeconds()).padStart(2, '0');

          const months = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
          const month  = months[monthIdx] || '---';

          clockEl.textContent = `${day} ${month} ${year} ${h}:${m}:${s}`;
        }

        updateLiveClock();
        setInterval(updateLiveClock, 1000);
      }

      // DataTables
      new DataTable('#ordersTable', {
        pageLength: 25,
        lengthMenu: [25, 50, 100],
        scrollX: true,
        order: [],
        dom: 'lrtip'
      });

      // Dropdown chunk luar
      const selChunk = document.getElementById('orderChunkSelect');
      if (selChunk) {
        selChunk.addEventListener('change', function () {
          if (this.value) window.location = this.value;
        });
      }

      // ====== MULTI SELECT ala "SEMUA KOLUM" ======
      const ORDER_OPTIONS_ALL = <?= json_encode($orderOptionsAll, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const SELECTED_CODES    = <?= json_encode($orderCodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

      const selectEl  = document.getElementById('orderCodesSelect');
      const formMulti = document.getElementById('multiSearchForm');
      const hiddenPage = document.getElementById('hiddenPage');
      const btnReset = document.getElementById('btnResetFilter');

      if (!selectEl || !formMulti) return;

      const ts = new TomSelect(selectEl, {
        plugins: ['remove_button'],
        create: false,
        persist: false,
        maxOptions: 200,
        placeholder: 'Ketik minimal 2 karakter…',
        closeOnSelect: false,
        valueField: 'value',
        labelField: 'text',
        searchField: ['text'],
        options: [],
        sortField: [{field:'text', direction:'asc'}],
        onItemAdd: function () {
          this.setTextboxValue('');
        },
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
            return '<div>' + escape(data.text) + '</div>';
          },
          no_results: function (data, escape) {
            if (!data.input || data.input.length < 2) {
              return '<div class="ts-empty-hint">Menunggu input… ketik minimal 2 karakter.</div>';
            }
            return '<div class="ts-empty-hint">Tidak ada hasil untuk "<strong>' +
                   escape(data.input) + '</strong>".</div>';
          }
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

      function filterAllByQuery(query) {
        const q = (query || '').toLowerCase().trim();

        if (!q || q.length < 2) {
          showWait();
          return;
        }

        const out = [];
        for (let i = 0; i < ORDER_OPTIONS_ALL.length; i++) {
          const v = ORDER_OPTIONS_ALL[i];
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

      // init awal
      if (Array.isArray(SELECTED_CODES) && SELECTED_CODES.length) {
        const opts = SELECTED_CODES.map(v => ({ value: v, text: v }));
        ts.addOptions(opts);
        ts.setValue(SELECTED_CODES, true);
      } else {
        showWait();
      }

      ts.on('type', function (str) {
        filterAllByQuery(str);
      });

      // ketika submit → selalu kembali ke page 1
      formMulti.addEventListener('submit', function () {
        if (hiddenPage) hiddenPage.value = '1';
      });

      // reset filter: kembali ke URL tanpa codes & p
      if (btnReset) {
        btnReset.addEventListener('click', function () {
          const url = new URL(window.location.href);
          url.searchParams.delete('p');
          url.searchParams.delete('codes[]');
          url.searchParams.delete('codes');
          window.location = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '');
        });
      }
    });
  </script>
</body>
</html>
