<?php
// search_bot.php
// Form pencarian BOT (multi-select ala ALL ORDER) untuk orders + BAI + FOTO

require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

// CHAT ID dari WebApp Telegram (dilempar di URL ?chat_id=...)
$chatId = isset($_GET['chat_id']) ? trim((string)$_GET['chat_id']) : '';

/**
 * Ambil pool opsi dari beberapa kolom orders
 */
/**
 * Konfigurasi kolom pencarian (disamakan arahnya dengan search.php)
 */

// baca daftar kolom orders
$orderCols = [];
try {
  $stCols   = $pdo->query("SHOW COLUMNS FROM `orders`");
  $orderCols = $stCols->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $orderCols = [];
}

// util: cari kolom timestamp (created_at / tanggal / dll)
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

$hasAlamat = in_array('alamat', $orderCols, true);
$hasQrcode = in_array('qrcode', $orderCols, true);
$hasSnOntType = in_array('sn_ont_type', $orderCols, true);

// kolom no_voip (opsional)
$colVoip = null;
if (in_array('no_voip', $orderCols, true)) {
  $colVoip = 'no_voip';
} elseif (in_array('no_voip_lama', $orderCols, true)) {
  $colVoip = 'no_voip_lama';
}

/**
 * Ambil pool opsi dari beberapa kolom orders
 * (no_sc, no_internet, cp, nama, alamat, qrcode, tag_pelanggan)
 */
$searchCols = [
  'no_sc',
  'no_internet',
  'cp_pelanggan',
  'nama_pelanggan',
];

if ($hasAlamat) $searchCols[] = 'alamat';
if ($hasQrcode) $searchCols[] = 'qrcode';
if ($hasSnOntType) {
  $searchCols[] = 'sn_ont_type'; // Ganti tag_pelanggan dengan sn_ont_type
}

$optionsByCol   = [];
$latestByValue  = []; // untuk urutan “data terbaru” (semua kolom digabung)

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

      $v = (string)$v;
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

/**
 * GABUNG SEMUA UNTUK MODE "SEMUA KOLOM"
 * - Jika ada kolom timestamp → diurutkan berdasarkan record terbaru
 */
if ($tsCol && $latestByValue) {
  $searchOptionsAll = array_keys($latestByValue);
  usort($searchOptionsAll, static function ($a, $b) use ($latestByValue) {
    $ta = $latestByValue[$a] ?? 0;
    $tb = $latestByValue[$b] ?? 0;
    if ($ta === $tb) {
      return strcmp((string)$b, (string)$a);
    }
    return $tb <=> $ta; // terbaru dulu
  });
} else {
  $searchOptionsAll = array_values(array_unique(array_merge(
    $optionsByCol['no_sc']          ?? [],
    $optionsByCol['no_internet']    ?? [],
    $optionsByCol['cp_pelanggan']   ?? [],
    $optionsByCol['nama_pelanggan'] ?? [],
    $optionsByCol['alamat']         ?? [],
    $optionsByCol['qrcode']         ?? [],
   $optionsByCol['sn_ont_type']    ?? [] // Ganti tag_pelanggan
  )));
}

/**
 * Data per kolom untuk JS (mode filter kolom tunggal)
 */
$jsOptionsByCol = [
  'no_sc'          => $optionsByCol['no_sc']          ?? [],
  'no_internet'    => $optionsByCol['no_internet']    ?? [],
  'nama_pelanggan' => $optionsByCol['nama_pelanggan'] ?? [],
  'cp_pelanggan'   => $optionsByCol['cp_pelanggan']   ?? [],
  'alamat'         => $optionsByCol['alamat']         ?? [],
  'qrcode'         => $optionsByCol['qrcode']         ?? [],
  'sn_ont_type'    => $optionsByCol['sn_ont_type']    ?? [], // Ganti tag_pelanggan
];

/**
 * DATA NO INTERNET / NO VOIP – untuk filter "No Internet / No Voip"
 */
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

    $stV       = $pdo->query($sqlVoip);
    $voipPairs = $stV->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $voipPairs = [];
  }
}

/**
 * DATA DETAIL (1 baris per no_sc paling baru) – TANPA FOTO (khusus BOT)
 */
// DATA DETAIL (1 baris per no_sc paling baru) – TANPA FOTO (khusus BOT)
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
      LIMIT 900000
    ";
  } else {
    $sqlDetail = "
      SELECT *
      FROM orders
      WHERE no_sc IS NOT NULL AND no_sc <> ''
      ORDER BY id_order DESC
      LIMIT 9000000
    ";
  }

  $stD   = $pdo->query($sqlDetail);
  $rowsD = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rowsD as $r) {
    $noSc = trim((string)($r['no_sc'] ?? ''));
    if ($noSc === '') continue;

    $detailRows[] = [
      'no_sc'          => $noSc,
      'no_internet'    => trim((string)($r['no_internet']    ?? '')),
      'no_voip'        => $colVoip ? trim((string)($r[$colVoip] ?? '')) : '',
      'nama_pelanggan' => trim((string)($r['nama_pelanggan'] ?? '')),
      'alamat'         => trim((string)($r['alamat']         ?? '')),
      'cp_pelanggan'   => trim((string)($r['cp_pelanggan']   ?? '')),
      'qrcode'         => trim((string)($r['qrcode']         ?? '')),
      'sn_ont_type'    => trim((string)($r['sn_ont_type']    ?? '')), // Tambah ini
      // KHUSUS BOT: tidak perlu foto (thumb hanya di search.php)
      'thumbs'         => [],
      'thumb'          => null,
    ];
  }
} catch (Throwable $e) {
  $detailRows = [];
}

/**
 * Jika balik dari hasil (thesearching_bot) dan URL masih punya codes[]
 */
$selectedCodes = [];
if (isset($_GET['codes']) && is_array($_GET['codes'])) {
  foreach ($_GET['codes'] as $v) {
    $v = trim((string)$v);
    if ($v === '') continue;
    $selectedCodes[] = $v;
  }
  $selectedCodes = array_values(array_unique($selectedCodes));
}

/**
 * Filter kolom dari query string
 */
$fieldSelected = 'all';
if (isset($_GET['field'])) {
  $f = strtolower((string)$_GET['field']);
   $allowed = [
    'all',
    'no_sc',
    'no_voip',        // No Internet / No Voip
    'nama_pelanggan',
    'alamat',
    'cp_pelanggan',
    'qrcode',
    'detail',         // Mode detail (order terbaru per no_sc)
    'sn_ont_type',  // tambahan khusus BOT
  ];

  if (in_array($f, $allowed, true)) {
    $fieldSelected = $f;
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  
  <!-- Icon untuk Bookmark dan Add to Home Screen -->
  <link rel="icon" type="image/png" href="images/ph.png">
  <link rel="apple-touch-icon" href="images/ph.png">
  
  <!-- Nama Otomatis saat di-bookmark (Add to Home Screen) -->
  <meta name="apple-mobile-web-app-title" content="Finder efidi">
  <meta name="application-name" content="Finder efidi">
  
  <title>Finder efidi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#00a3ad">

  <!-- TomSelect + MDI -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

  <style>
    :root {
      --es-teal:       #00a3ad;
      --es-teal-dark:  #00808b;
      --es-bg-soft:    #ffffffff;
      --es-text-main:  #041b28;
      --es-text-muted: #444f5eff;
      --es-border-soft:#e2e8f0;
    }

    * {
      box-sizing: border-box;
    }

    html, body {
      height: 100%;
    }

    body {
      margin: 0;
      padding: calc(6px + env(safe-area-inset-top, 0))
               12px
               calc(8px + env(safe-area-inset-bottom, 0));
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      -webkit-font-smoothing: antialiased;
      background-color: var(--es-bg-soft);
      color: var(--es-text-main);
      overflow-y: auto;
      -webkit-tap-highlight-color: transparent;
    }

    .shell {
      width: 100%;
      max-width: 520px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 8px; /* sedikit lebih rapat */
    }

    /* ===== HEADER APP: BUKAN KARTU LAGI, MIRIP APP BAR ===== */
    .header {
      background-color: transparent;
      border-radius: 0;
      padding: 4px 2px 8px;
      border: none;
      border-bottom: 1px solid rgba(148,163,184,0.35); /* garis tipis */
      display: flex;
      align-items: center;
      gap: 10px;
    }

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

    .header-text {
      flex: 1;
      min-width: 0;
    }

    .header-title-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .header-title {
      font-size: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 6px;
      color: #0f172a;
    }

    .header-title-icon {
      width: 20px;
      height: 20px;
      border-radius: 999px;
      background-color: #e5f3f5;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      color: var(--es-teal-dark);
    }

    .header-subtitle {
      margin-top: 2px;
      font-size: 12px;
      color: var(--es-text-muted);
    }

    .tag-chip {
      margin-top: 4px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 10px;
      font-size: 9.5px;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      border-radius: 999px;
      border: 1px solid rgba(0,163,173,0.35);
      background-color: #f9fafb;
      color: #03606c;
    }

    .tag-dot {
      width: 6px;
      height: 6px;
      border-radius: 999px;
      background-color: var(--es-teal);
    }

    /* SECTION TITLE */
    .section-label {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 4px 2px 0;
      margin-top: 4px;
      margin-bottom: 0;
      font-size: 10.5px;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      color: #6b7280;
    }

    .section-label-bar {
      width: 14px;
      height: 2px;
      border-radius: 999px;
      background-color: var(--es-teal);
    }

    /* TITLE DI LUAR CARD */
    .search-title-main-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 2px 2px 0;
      margin-top: 2px;
    }

    .search-title-main {
      font-size: 15px;
      font-weight: 700;
      color: #0f172a;
    }

    /* KARTU PENCARIAN */
    .search-card {
      margin-top: 4px;
      padding: 14px 16px 16px;
      border-radius: 14px;
      background-color: #ffffff;
      border: 1px solid var(--es-border-soft);
      box-shadow: 0 10px 28px rgba(15, 23, 42, 0.02);
    }

    .search-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .search-subtitle-inline {
      font-size: 11px;
      color: var(--es-text-muted);
    }

    .search-subtitle-inline b {
      font-weight: 700;
    }

    .search-chip {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .10em;
      padding: 3px 10px;
      border-radius: 999px;
      background-color: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }

    /* BLOK FILTER KOLOM */
    .field-filter {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 9px;
    }

    .field-filter-label {
      font-size: 12px;
      font-weight: 600;
      color: #111827;
      white-space: nowrap;
    }

    .field-filter-select {
      flex: 1;
    }

    .field-filter-select select {
      width: 100%;
      border-radius: 999px;
      border: 1px solid #cbd5e1;
      background-color: #ffffff;
      padding: 8px 38px 8px 13px;
      font-size: 12px;
      color: #0f172a;
      outline: none;
      appearance: none;
      background-image:
        linear-gradient(45deg, transparent 50%, #64748b 50%),
        linear-gradient(135deg, #64748b 50%, transparent 50%);
      background-position:
        calc(100% - 16px) calc(50% - 3px),
        calc(100% - 10px) calc(50% - 3px);
      background-size: 6px 6px, 6px 6px;
      background-repeat: no-repeat;
    }

    .field-filter-select select:focus {
      border-color: var(--es-teal);
      box-shadow:
        0 0 0 1px rgba(0,163,173,0.35),
        0 0 0 3px rgba(0,163,173,0.16);
    }

    .field-label-inline {
      font-size: 12px;
      font-weight: 600;
      color: #111827;
      margin-bottom: 5px;
    }

    .search-row {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .search-select-wrap {
      flex: 1 1 auto;
      min-width: 0;
    }

    .search-actions {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    /* TOMSELECT */
    .search-select-wrap .ts-control {
      min-height: 42px;
      border-radius: 10px;
      border: 1px solid #cbd5e1;
      background-color: #f9fafb;
      color: #0f172a;
      padding: 5px 10px;
      font-size: 11px;
      box-shadow: 0 0 0 1px rgba(148,163,184,0.20);
    }

    .search-select-wrap .ts-control input {
      font-size: 11px;
      color: #0f172a;
    }

    .search-select-wrap .ts-control::placeholder,
    .search-select-wrap .ts-control input::placeholder {
      color: #9ca3af;
      opacity: .95;
    }

    .search-select-wrap .ts-control .item {
      background-color: #f5f7fa;
      border-radius: 3px;
      padding: 2px 9px;
      margin: 2px 4px 2px 0;
      border: 1px solid #cbd5e1;
      font-size: 10px;
      font-weight: 500;
      color: #111827;
    }

    .search-select-wrap .ts-control .item .remove {
      color: #4b5563;
      opacity: .7;
    }

    .search-select-wrap .ts-dropdown {
      border-radius: 10px;
      border: 1px solid #cbd5e1;
      background-color: #ffffff;
      font-size: 11px;
      color: #0f172a;
      box-shadow: 0 18px 40px rgba(15,23,42,0.12);
    }

    .search-select-wrap .ts-dropdown .option {
      padding: 4px 9px;
    }

    .search-select-wrap .ts-dropdown .option.active {
      background-color: #e0f2fe;
      color: #03606c;
    }

    .search-btn,
    .reset-btn {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      border: 1px solid transparent;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 20px;
      transition:
        transform .08s ease-out,
        box-shadow .08s ease-out,
        background-color .08s ease-out;
    }

    .search-btn {
      background-color: var(--es-teal);
      border-color: var(--es-teal);
      color: #f9fafb;
      box-shadow: 0 0px 0px rgba(0,163,173,0.35);
    }

    .search-btn:active {
      transform: translateY(1px);
      box-shadow: 0 0px 0px rgba(0,163,173,0.30);
    }

    .reset-btn {
      background-color: #e5e7eb;
      border-color: #d4d4d8;
      color: #111827;
    }

    .reset-btn:active {
      transform: translateY(1px);
    }

    .ts-loading-option {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 4px 8px;
      font-size: 11px;
      color: var(--es-teal);
    }

    .ts-empty-hint {
      padding: 4px 8px;
      font-size: 11px;
      color: #9ca3af;
    }

    .ts-empty-hint strong {
      color: #0f172a;
    }

    .ts-spinner {
      width: 12px;
      height: 12px;
      border-radius: 999px;
      border: 2px solid rgba(148,163,184,0.45);
      border-top-color: var(--es-teal);
      animation: ts-spin .75s linear infinite;
    }

    @keyframes ts-spin {
      to { transform: rotate(360deg); }
    }

    .help-text {
      margin-top: 9px;
      font-size: 11px;
      color: var(--es-text-muted);
    }

    .help-text b {
      color: #111827;
    }

    .footer-note {
      margin-top: 25px;
      font-size: 10px;
      color: #6b7280;
      text-align: right;
    }

    /* KARTU INFO DI BAWAH – Petunjuk penggunaan */
    .info-card {
      margin-top: 8px;
      padding: 9px 12px 11px;
      border-radius: 12px;
      background-color: #e5f6f7;
      border: 1px solid #bae6ec;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: 11px;
    }

    .info-icon {
      width: 22px;
      height: 22px;
      border-radius: 999px;
      background-color: var(--es-teal);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ecfeff;
      font-size: 14px;
      flex-shrink: 0;
    }

    .info-body {
      flex: 1;
      min-width: 0;
    }

    .info-title {
      font-size: 12px;
      font-weight: 600;
      color: #0f172a;
      margin-bottom: 2px;
    }

    .info-text {
      font-size: 11px;
      color: var(--es-text-muted);
    }

    .info-text b {
      color: #111827;
    }

    .back-btn {
      align-self: flex-start;
      margin-bottom: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid #d1d5db;
      background-color: #ffffff;
      font-size: 12px;
      color: #111827;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
    }

    .back-btn:active {
      background-color: #f3f4f6;
    }

    .back-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }

    .back-text {
      letter-spacing: 0.03em;
    }

    /* KARTU KEAMANAN DATA – BIRU SEPERTI BADGE SEARCH */
    .security-card {
      margin-top: 6px;
      padding: 9px 12px 11px;
      border-radius: 12px;
      background-color: #eff6ff;   /* sama tone dengan SEARCH */
      border: 1px solid #bfdbfe;   /* biru muda */
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: 11px;
    }

    .security-icon {
      width: 22px;
      height: 22px;
      border-radius: 999px;
      background-color: #1d4ed8;   /* biru utama */
      display: flex;
      align-items: center;
      justify-content: center;
      color: #e0ecff;
      font-size: 14px;
      flex-shrink: 0;
    }

    .security-body {
      flex: 1;
      min-width: 0;
    }

    .security-title {
      font-size: 12px;
      font-weight: 600;
      color: #1f2937;  /* sedikit lebih tegas */
      margin-bottom: 2px;
    }

    .security-text {
      font-size: 11px;
      color: #4b5563;
    }

    .security-text b {
      color: #111827;
    }

    @media (min-width: 640px) {
      body {
        padding-top: 10px;
        padding-bottom: 12px;
      }
    }
  </style>

</head>
<body>
  <div class="shell">
      <!-- TOMBOL LOGOUT (ganti tombol kembali yang lama) -->
<!-- TOMBOL KELUAR: ARAHKAN KE open_telegram.php -->
<button type="button" class="back-btn" onclick="if (window.AndroidBridge && AndroidBridge.forceLogout) { AndroidBridge.forceLogout(); }"> <span class="back-icon">⬅️</span> <span class="back-text">Keluar</span> </button>

    <!-- HEADER APP -->
    <div class="header">
      <div class="efidi-logo">
        <img src="images/logoo.png" alt="EFIDI">
      </div>
      <div class="header-text">
        <div class="header-title-row">
          <div class="header-title">
            <span class="header-title-icon">
              <i class="mdi mdi-magnify"></i>
            </span>
            <span>Searching data by efidi.</span>
          </div>
        </div>
        <div class="header-subtitle">
           Pencarian detail data instalasi dan berita acara instalasi
        </div>
        <div class="tag-chip">
          <span class="tag-dot"></span>
          <span>MODE EFIDI · MULTI SELECT</span>
        </div>
      </div>
    </div>

    <div class="section-label">
      <span class="section-label-bar"></span>
      <span>Filter Data BOT</span>
    </div>

    <!-- TITLE PENCARIAN DI LUAR CARD -->
    <div class="search-title-main-row">
      <div class="search-title-main">Pencarian data instalasi</div>
    </div>

    <!-- KARTU FILTER / SEARCH -->
    <div class="search-card">
      <form id="botSearchForm" method="get" action="thesearching_bot.php">
        <input type="hidden" name="chat_id" value="<?= e($chatId) ?>">

        <div class="search-header">
          <div class="search-subtitle-inline">
            Pilih maksimal <b>1</b> data.
          </div>
          <div class="search-chip">SEARCH</div>
        </div>

        <!-- FILTER KOLOM -->
        <div class="field-filter">
          <div class="field-filter-label">Kolom</div>
          <div class="field-filter-select">
          <select id="fieldFilter" name="field">
  <option value="all"            <?= $fieldSelected === 'all' ? 'selected' : '' ?>>Semua Kolom</option>
  <option value="no_sc"          <?= $fieldSelected === 'no_sc' ? 'selected' : '' ?>>No SC</option>
  <option value="no_voip"        <?= $fieldSelected === 'no_voip' ? 'selected' : '' ?>>No Internet / No Voip</option>
  <option value="nama_pelanggan" <?= $fieldSelected === 'nama_pelanggan' ? 'selected' : '' ?>>Nama Pelanggan</option>
  <option value="alamat"         <?= $fieldSelected === 'alamat' ? 'selected' : '' ?>>Alamat</option>
  <option value="cp_pelanggan"   <?= $fieldSelected === 'cp_pelanggan' ? 'selected' : '' ?>>CP Pelanggan</option>
  <option value="qrcode"         <?= $fieldSelected === 'qrcode' ? 'selected' : '' ?>>QR Code</option>
  <option value="sn_ont_type"    <?= $fieldSelected === 'sn_ont_type' ? 'selected' : '' ?>>SN ONT</option>
  <option value="detail"         <?= $fieldSelected === 'detail' ? 'selected' : '' ?>>Detail</option>
  
</select>

          </div>
        </div>

        <div class="field-label-inline">Nilai yang dicari</div>
        <div class="search-row">
          <div class="search-select-wrap">
            <select id="botSearchCodes" name="codes[]" multiple></select>
          </div>
          <div class="search-actions">
            <button type="submit" id="btnSearch" class="search-btn" title="Search">
              <i class="mdi mdi-magnify"></i>
            </button>
            <button type="button" id="btnReset" class="reset-btn" title="Reset">
              <i class="mdi mdi-close"></i>
            </button>
          </div>
        </div>

        <div class="help-text">
          Pencarian berdasarkan <b>No SC</b>, <b>No Internet</b>, <b>CP Pelanggan</b>,
          <b>Nama Pelanggan</b> dan <b>SN ONT</b> dengan kecocokan <b>tepat (exact)</b>.
        </div>

        <div class="footer-note">
          Setelah memilih kode, tekan <b>Search</b> untuk membuka halaman hasil.
        </div>
      </form>
    </div>

    <!-- KARTU INFO TAMBAHAN DI BAWAH -->
    <div class="info-card">
      <div class="info-icon">
        <i class="mdi mdi-information-outline"></i>
      </div>
      <div class="info-body">
        <div class="info-title">Petunjuk penggunaan</div>
        <div class="info-text">
  Silakan pilih <b>filter kolom</b> terlebih dahulu sesuai kebutuhan. Jika filter
  tidak diubah, sistem akan otomatis melakukan pencarian pada <b>semua kolom</b>.
  Ketik minimal <b>2 karakter</b> pada kotak <b>Nilai yang dicari</b> sampai opsi
  muncul di daftar dropdown, lalu pilih data yang diinginkan dari dropdown tersebut.
  Setelah data terpilih, tekan tombol <b>Search</b> untuk menampilkan halaman hasil.
</div>

      </div>
    </div>

    <!-- KARTU KEAMANAN DATA -->
    <div class="security-card">
      <div class="security-icon">
        <i class="mdi mdi-shield-check-outline"></i>
      </div>
      <div class="security-body">
        <div class="security-title">Keamanan dan pencatatan</div>
           <div class="security-text">
      Untuk menjaga kerahasiaan data pelanggan, tampilan pada halaman ini
      dilindungi oleh sistem keamanan <b>efidi</b>. Konten yang ditampilkan
      tidak dapat di-screenshot, di-capture, ataupun direkam menggunakan fitur
      perekam layar pada perangkat, sehingga informasi sensitif tetap
      terlindungi sesuai kebijakan keamanan internal efidi.
    </div>

      </div>
    </div>

  </div>


  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const SEARCH_OPTIONS_ALL = <?= json_encode($searchOptionsAll, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const SEARCH_BY_COL      = <?= json_encode($jsOptionsByCol,   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const VOIP_ROWS          = <?= json_encode($voipPairs,        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const DETAIL_ROWS        = <?= json_encode($detailRows,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const SELECTED_CODES     = <?= json_encode($selectedCodes,    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const FIELD_SELECTED_PHP = <?= json_encode($fieldSelected,    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const selectEl = document.getElementById('botSearchCodes');
    const form     = document.getElementById('botSearchForm');
    const btnReset = document.getElementById('btnReset');
    const fieldEl  = document.getElementById('fieldFilter');

    if (!selectEl || !form) return;

    if (!window.TomSelect) {
      // fallback kalau TomSelect gagal load
      const inp = document.createElement('input');
      inp.type = 'text';
      inp.name = 'codes[]';
      inp.className = 'form-control';
      inp.placeholder = 'Masukkan kata kunci pencarian…';
      if (Array.isArray(SELECTED_CODES) && SELECTED_CODES.length) {
        inp.value = SELECTED_CODES[0];
      }
      selectEl.parentNode.replaceChild(inp, selectEl);
      return;
    }

    let currentField = (fieldEl && fieldEl.value) || FIELD_SELECTED_PHP || 'all';
    if (!currentField) currentField = 'all';

    const ts = new TomSelect(selectEl, {
  plugins: ['remove_button'],
  create: false,
  persist: false,
  maxOptions: 200,
  maxItems: 1,
  valueField: 'value',
  labelField: 'text',
  searchField: [
    'text',
    'search_all',
    'no_sc',
    'no_internet',
    'no_voip',
    'nama_pelanggan',
    'alamat',
    'cp_pelanggan',
    'qrcode',
    'sn_ont_type' // Tambah field ini
  ],
  options: [],
  placeholder: 'Ketik minimal 2 karakter…',
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
      // tampilan sederhana saja (tanpa thumbnail foto)
      return '<div>' + escape(data.text) + '</div>';
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

    function setPlaceholder(text) {
      ts.settings.placeholder = text;
      if (ts.control_input) {
        ts.control_input.placeholder = text;
      }
    }

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

    function buildSimpleOptions(list) {
      if (!Array.isArray(list)) return [];
      return list.map(function (v) {
        const s = String(v);
        return { value: s, text: s };
      });
    }

    function buildVoipOptions() {
      if (!Array.isArray(VOIP_ROWS)) return [];
      const out = [];
      VOIP_ROWS.forEach(function (row) {
        const net  = (row.no_internet || '').trim();
        const voip = (row.no_voip || '').trim();
        if (!net && !voip) return;

        let labelParts = [];
        if (net)  labelParts.push('NET: ' + net);
        if (voip) labelParts.push('VOIP: ' + voip);

        const text = labelParts.join(' · ') || (net || voip);
        const val  = net || voip;

        out.push({
          value: val,
          text: text,
          no_internet: net,
          no_voip: voip,
          search_all: text
        });
      });
      return out;
    }

    function buildDetailOptions() {
  if (!Array.isArray(DETAIL_ROWS)) return [];
  const out = [];
  DETAIL_ROWS.forEach(function (row) {
    const noSc   = (row.no_sc          || '').trim();
    const net    = (row.no_internet    || '').trim();
    const voip   = (row.no_voip        || '').trim();
    const nama   = (row.nama_pelanggan || '').trim();
    const alamat = (row.alamat         || '').trim();
    const cp     = (row.cp_pelanggan   || '').trim();
    const qr     = (row.qrcode         || '').trim();
    const snOnt  = (row.sn_ont_type    || '').trim(); // Tambah SN ONT TYPE

    // Periksa apakah ada data yang valid
    if (!noSc && !net && !voip && !nama && !alamat && !cp && !qr && !snOnt) return;

    // Bangun array header parts
    let headerParts = [];
    if (noSc) headerParts.push(noSc);

    let netLabel = '';
    if (net && voip) netLabel = net + ' · ' + voip;
    else if (net)   netLabel = net;
    else if (voip)  netLabel = voip;
    if (netLabel) headerParts.push(netLabel);

    if (nama)   headerParts.push(nama);
    if (alamat) headerParts.push(alamat);
    if (cp)     headerParts.push('CP: ' + cp); // Tambah CP
    if (qr)     headerParts.push('QR: ' + qr); // Tambah QR
    if (snOnt)  headerParts.push('SN: ' + snOnt); // Tambah SN ONT

    const text      = headerParts.join(' — ');
    const searchAll = [noSc, net, voip, nama, alamat, cp, qr, snOnt].join(' ');

    out.push({
      value: noSc || net || cp || qr || snOnt || text,
      text: text || searchAll,
      no_sc: noSc,
      no_internet: net,
      no_voip: voip,
      nama_pelanggan: nama,
      alamat: alamat,
      cp_pelanggan: cp,
      qrcode: qr,
      sn_ont_type: snOnt, // Tambah field untuk pencarian
      search_all: searchAll
    });
  });
  return out;
}

    function initOptionsForField(field, isFirstLoad) {
      currentField = field || 'all';

      ts.clear();
      ts.clearOptions();

      if (currentField === 'detail') {
        setPlaceholder('Cari berdasarkan detail Order (SC / Nama / Alamat / QR / dll)…');
      } else if (currentField === 'no_voip') {
        setPlaceholder('Cari No Internet / No Voip…');
      } else if (currentField === 'all') {
        setPlaceholder('Ketik minimal 2 karakter…');
      } else {
        setPlaceholder('Ketik minimal 2 karakter…');
      }

      if (currentField === 'all') {
        showWait();
      } else if (currentField === 'no_voip') {
        const opts = buildVoipOptions();
        if (opts.length) {
          ts.addOptions(opts);
          ts.refreshOptions(false);
        }
      } else if (currentField === 'detail') {
        const opts = buildDetailOptions();
        if (opts.length) {
          ts.addOptions(opts);
          ts.refreshOptions(false);
        }
      } else {
        // field = nama_pelanggan / cp_pelanggan / alamat / qrcode / tag_pelanggan / no_sc / no_internet
        let col = currentField;
        if (col === 'no_voip') {
          col = 'no_internet';
        }
        const list = SEARCH_BY_COL[col] || [];
        const opts = buildSimpleOptions(list);
        if (opts.length) {
          ts.addOptions(opts);
          ts.refreshOptions(false);
        }
      }

      // Preselect value dari URL (kembali dari hasil)
      if (isFirstLoad && Array.isArray(SELECTED_CODES) && SELECTED_CODES.length) {
        const val = String(SELECTED_CODES[0]);
        ts.addOption({ value: val, text: val });
        ts.setValue(val, true);
      }
    }

    // init pertama kali
    initOptionsForField(currentField, true);

    // Ketik: khusus mode "Semua Kolom" pakai filterAllByQuery
    ts.on('type', function (str) {
      if (currentField === 'all') {
        filterAllByQuery(str);
      }
    });

    // Ganti filter kolom
    if (fieldEl) {
      fieldEl.addEventListener('change', function () {
        const val = this.value || 'all';
        initOptionsForField(val, false);
      });
    }

    // Tombol reset
    if (btnReset) {
      btnReset.addEventListener('click', function () {
        if (fieldEl) {
          fieldEl.value = 'all';
        }
        initOptionsForField('all', false);
        showWait();
      });
    }

    // Validasi submit: wajib ada 1 nilai
    form.addEventListener('submit', function (e) {
      const val = ts.getValue();
      if (!val || (Array.isArray(val) && val.length === 0)) {
        e.preventDefault();
        alert('Pilih minimal satu nilai terlebih dahulu.');
      }
    });
  });
</script>

</body>
</html>
