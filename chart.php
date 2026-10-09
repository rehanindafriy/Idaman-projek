<?php
// chart.php — JSON data untuk Chart.js (Orders/BAI/Outstanding per TECH aktif)
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ---- Matikan output error ke browser (supaya JSON tidak rusak) ----
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ---- Serap output nyasar dari koneksi.php (BOM/echo) ----
ob_start();
require_once __DIR__ . '/koneksi.php';
$noise = ob_get_clean();
if ($noise && trim($noise) !== '') {
  error_log('[chart.php] stray output from koneksi.php: ' . substr(strip_tags($noise), 0, 300));
}

// ---- Normalisasi handle koneksi ($pdo prefer) ----
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($conn) && $conn instanceof PDO) $pdo = $conn;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode(['error' => 'Koneksi DB gagal']); exit;
}

// ---- Helper input ----
function s($v){ return is_string($v) ? trim($v) : ''; }
function is_date_ymd(string $s): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }

$mode   = s($_GET['mode']  ?? 'semua');   // semua|hari_ini|tanggal|rentang|bulan|tahun
$date   = s($_GET['date']  ?? '');
$start  = s($_GET['start'] ?? '');
$end    = s($_GET['end']   ?? '');
$month  = s($_GET['month'] ?? '');
$year   = s($_GET['year']  ?? '');
$strict = ($_GET['strict'] ?? '1') === '1'; // DEFAULT: strict ON
$params = [];

/**
 * Build WHERE tanggal untuk kolom tertentu (range: >= start AND < end).
 * Range dihitung di PHP agar tidak tergantung timezone MySQL. Default timezone: Asia/Jakarta.
 * Tag dipakai utk nama parameter unik per sumber (orders=bai berbeda).
 */
function build_date_sql(string $col, string $mode, string $date, string $start, string $end, string $month, string $year, array &$params, string $tag): string {
  $tz = new DateTimeZone('Asia/Jakarta');

  $range = null; // [start, endExclusive]
  switch ($mode) {
    case 'hari_ini': {
      $now = new DateTime('now', $tz);
      $s   = (clone $now)->setTime(0,0,0);
      $e   = (clone $s)->modify('+1 day');
      $range = [$s, $e];
      break;
    }
    case 'tanggal': {
      if (!is_date_ymd($date)) break;
      $s = new DateTime($date.' 00:00:00', $tz);
      $e = (clone $s)->modify('+1 day');
      $range = [$s, $e];
      break;
    }
    case 'rentang': {
      if (!is_date_ymd($start) || !is_date_ymd($end)) break;
      $s = new DateTime($start.' 00:00:00', $tz);
      $e = (new DateTime($end.' 00:00:00', $tz))->modify('+1 day'); // end eksklusif
      $range = [$s, $e];
      break;
    }
    case 'bulan': {
      if (!preg_match('/^(0[1-9]|1[0-2])$/', $month) || !preg_match('/^\d{4}$/', $year)) break;
      $s = new DateTime("$year-$month-01 00:00:00", $tz);
      $e = (clone $s)->modify('+1 month');
      $range = [$s, $e];
      break;
    }
    case 'tahun': {
      if (!preg_match('/^\d{4}$/', $year)) break;
      $s = new DateTime("$year-01-01 00:00:00", $tz);
      $e = (clone $s)->modify('+1 year');
      $range = [$s, $e];
      break;
    }
    default: /* semua */ break;
  }

  if (!$range) return '';
  [$s, $e] = $range;

  // Simpan sebagai string 'Y-m-d H:i:s' (anggap kolom disimpan lokal/server time; jika UTC, sesuaikan di sini)
  $params[":{$tag}_s"] = $s->format('Y-m-d H:i:s');
  $params[":{$tag}_e"] = $e->format('Y-m-d H:i:s');
  return " AND $col >= :{$tag}_s AND $col < :{$tag}_e ";
}

// ==== Kolom tanggal: sesuai permintaan ====
$ORD_DATE_COL = 'created_at';    // orders
$BAI_DATE_COL = 'date_received'; // bai

// ==== Ekspresi normalisasi (lower, trim, buang @ dan spasi) ====
// NB: COALESCE untuk handle NULL
$NORM = function(string $expr): string {
  return "REPLACE(REPLACE(LOWER(TRIM(COALESCE($expr,''))), '@',''), ' ','')";
};

try {
  $ordDate = build_date_sql($ORD_DATE_COL, $mode, $date, $start, $end, $month, $year, $params, 'o');
  $baiDate = build_date_sql($BAI_DATE_COL, $mode, $date, $start, $end, $month, $year, $params, 'b');

  /* ======== MODE STRICT (DEFAULT ON) ========
     - Orders: COUNT(DISTINCT o.no_sc) untuk user (o.user_id = a.chat_id)
     - BAI:    COUNT(DISTINCT b.kode_bai) yg berpasangan dgn order (o2.no_sc=b.kode_bai)
               dan user sama (b.user_id=o2.user_id=a.chat_id)
  */
  if ($strict) {
    $sqlStrict = "
      SELECT
        a.id_akses, a.nama, a.username, a.chat_id,

        (
          SELECT COUNT(DISTINCT o.no_sc)
          FROM orders o
          WHERE 1=1
            $ordDate
            AND CAST(o.user_id AS CHAR) = CAST(a.chat_id AS CHAR)
        ) AS orders_cnt,

        (
          SELECT COUNT(DISTINCT b.kode_bai)
          FROM bai b
          INNER JOIN orders o2
            ON o2.user_id = b.user_id
           AND o2.no_sc   = b.kode_bai
          WHERE 1=1
            $baiDate
            AND CAST(b.user_id AS CHAR) = CAST(a.chat_id AS CHAR)
        ) AS bai_cnt

      FROM akses_login a
      WHERE a.role = 'tech' AND a.status = 'active'
      ORDER BY a.nama ASC, a.username ASC
    ";

    $st2 = $pdo->prepare($sqlStrict);
    $st2->execute($params);
    $rows2 = $st2->fetchAll(PDO::FETCH_ASSOC);

    $labels = $orders = $bais = $outs = [];
    $tOrders = $tBai = $tOut = 0;

    foreach ($rows2 as $r) {
      $name = $r['nama'];
      if ($name === null || $name === '') $name = $r['username'] ?: '—';

      $o = (int)$r['orders_cnt'];
      $b = (int)$r['bai_cnt'];
      $out = max(0, $o - $b);

      $labels[] = $name;
      $orders[] = $o;
      $bais[]   = $b;
      $outs[]   = $out;

      $tOrders += $o; $tBai += $b; $tOut += $out;
    }

    echo json_encode([
      'labels' => $labels,
      'orders' => $orders,
      'bai'    => $bais,
      'out'    => $outs,
      'totals' => ['orders'=>$tOrders, 'bai'=>$tBai, 'out'=>$tOut],
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ==== JALUR LAMA (dipertahankan persis, untuk kompatibilitas) ====
  $sql = "
    SELECT
      a.id_akses, a.nama, a.username, a.chat_id,

      (
        SELECT COUNT(*)
        FROM orders o
        WHERE 1=1
          $ordDate
          AND (
            {$NORM('o.sender_username')} = {$NORM('a.nama')}
            OR {$NORM('o.sender_username')} = {$NORM('a.username')}
            OR CAST(o.user_id AS CHAR) = CAST(a.chat_id AS CHAR)
          )
      ) AS orders_cnt,

      (
        SELECT COUNT(*)
        FROM bai b
        WHERE 1=1
          $baiDate
          AND (
            {$NORM('b.uploader')} = {$NORM('a.nama')}
            OR {$NORM('b.uploader')} = {$NORM('a.username')}
          )
      ) AS bai_cnt

    FROM akses_login a
    WHERE a.role = 'tech' AND a.status = 'active'
    ORDER BY a.nama ASC, a.username ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $labels = $orders = $bais = $outs = [];
  $tOrders = $tBai = $tOut = 0;

  foreach ($rows as $r) {
    $name = $r['nama'];
    if ($name === null || $name === '') $name = $r['username'] ?: '—';

    $o = (int)$r['orders_cnt'];
    $b = (int)$r['bai_cnt'];
    $out = abs($o - $b); // sesuai jalur lama

    $labels[] = $name;
    $orders[] = $o;
    $bais[]   = $b;
    $outs[]   = $out;

    $tOrders += $o; $tBai += $b; $tOut += $out;
  }

  echo json_encode([
    'labels' => $labels,
    'orders' => $orders,
    'bai'    => $bais,
    'out'    => $outs,
    'totals' => ['orders'=>$tOrders, 'bai'=>$tBai, 'out'=>$tOut],
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Chart data error', 'detail' => $e->getMessage()]);
}
