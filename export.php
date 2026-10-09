<?php
// export_orders.php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function abortWith($code, $msg){
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg; exit;
}

// Wajib sudah klik Search biar Excel aktif
if (($_GET['searched'] ?? '0') !== '1') {
  abortWith(400, 'Silakan klik tombol Search terlebih dahulu.');
}

// ====== READ FILTER (SAMA DENGAN LIST) ======
$mode   = $_GET['mode']  ?? '';
$date   = $_GET['date']  ?? '';
$start  = $_GET['start'] ?? '';
$end    = $_GET['end']   ?? '';
$month  = $_GET['month'] ?? ''; // "YYYY-MM"
$year   = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$where  = [];
$params = [];

// ====== FILTER KEPEMILIKAN (ROLE TECH) — SAMA DENGAN LIST ======
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$meChat = $_SESSION['chat_id']              ?? ($_SESSION['user']['chat_id'] ?? null);
$meRole = $_SESSION['role']                 ?? ($_SESSION['user']['role'] ?? null);

if (strtolower((string)$meRole) === 'tech') {
  // Ambil username & nama (izinkan spasi seperti evidensi)
  $meUserRaw = (string)($_SESSION['user']['username'] ?? $_SESSION['username'] ?? '');
  $meNameRaw = (string)($_SESSION['nama'] ?? '');

  $unameExact = trim($meUserRaw);                           // biarkan apa adanya
  $nameExact  = preg_replace('/\s+/', ' ', trim($meNameRaw));
  $nameExact  = preg_replace('/[^\pL\pN._\-\s]/u', '', $nameExact);

  // kandidat exact yang mungkin tersimpan di sender_username
  $CANDS_EXACT = array_values(array_unique(array_filter([$unameExact, $nameExact])));

  // kandidat versi dinormalisasi (lowercase + tanpa @ + tanpa spasi)
  $CANDS_NORM = array_values(array_unique(array_map(
    fn($v) => strtolower(str_replace(['@',' '], '', trim($v))),
    $CANDS_EXACT
  )));

  // chat_id valid?
  $isChatOk = $meChat && preg_match('/^-?\d{5,}$/', (string)$meChat);
  $myUid    = $isChatOk ? (string)$meChat : null;

  // Bangun klausa OR
  $OWNER_PARAMS = [];
  $ownerParts   = [];

  // (1) user_id ↔ chat_id
  if ($isChatOk) {
    $ownerParts[]             = "user_id = :uid";
    $OWNER_PARAMS[':uid']     = $myUid;
  }

  // (2) sender_username ↔ exact (username atau nama apa adanya)
  if (!empty($CANDS_EXACT)) {
    $ph = [];
    foreach ($CANDS_EXACT as $i => $v) {
      $k = ":su$i";
      $ph[] = $k;
      $OWNER_PARAMS[$k] = $v;
    }
    $ownerParts[] = "sender_username IN (" . implode(',', $ph) . ")";
  }

  // (3) sender_username (dinormalisasi) ↔ kandidat dinormalisasi
  if (!empty($CANDS_NORM)) {
    $ph2 = [];
    foreach ($CANDS_NORM as $i => $v) {
      $k = ":sn$i";
      $ph2[] = $k;
      $OWNER_PARAMS[$k] = $v;
    }
    $ownerParts[] =
      "LOWER(REPLACE(REPLACE(TRIM(sender_username),'@',''),' ','')) IN (" . implode(',', $ph2) . ")";
  }

  // Jika tidak ada identitas sama sekali -> kosongkan hasil
  $OWNER_WHERE = $ownerParts ? '(' . implode(' OR ', $ownerParts) . ')' : '1=0';

  // Tambahkan ke filter global
  $where[] = $OWNER_WHERE;
  $params  = array_merge($params, $OWNER_PARAMS);
}

// ====== FILTER TANGGAL ======
switch ($mode) {
  case 'single':
    if ($date) {
      $where[]       = "created_at BETWEEN :ds AND :de";
      $params[':ds'] = $date.' 00:00:00';
      $params[':de'] = $date.' 23:59:59';
    }
    break;
  case 'range':
    if ($start && $end) {
      $where[]       = "created_at BETWEEN :rs AND :re";
      $params[':rs'] = $start.' 00:00:00';
      $params[':re'] = $end.' 23:59:59';
    } elseif ($start) {
      $where[]       = "created_at >= :rs";
      $params[':rs'] = $start.' 00:00:00';
    } elseif ($end) {
      $where[]       = "created_at <= :re";
      $params[':re'] = $end.' 23:59:59';
    }
    break;
  case 'month':
    if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
      [$y,$m] = explode('-', $month);
      $s = sprintf('%04d-%02d-01 00:00:00', $y, $m);
      $e = date('Y-m-t 23:59:59', strtotime("$y-$m-01"));
      $where[]       = "created_at BETWEEN :ms AND :me";
      $params[':ms'] = $s;
      $params[':me'] = $e;
    }
    break;
  case 'year':
    if ($year) {
      $where[]       = "created_at BETWEEN :ys AND :ye";
      $params[':ys'] = sprintf('%04d-01-01 00:00:00', $year);
      $params[':ye'] = sprintf('%04d-12-31 23:59:59', $year);
    }
    break;
  default:
    break;
}

// ====== AMBIL SEMUA KOLOM DINAMIS (sesuai urutan tabel) ======
$sqlCols = "SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
            ORDER BY ORDINAL_POSITION";
$cols = $pdo->query($sqlCols)->fetchAll(PDO::FETCH_COLUMN);
if (!$cols) abortWith(500, 'Gagal membaca kolom tabel orders.');

$colList = implode(',', array_map(fn($c)=>"`$c`", $cols));

// ====== QUERY DATA ======
$sql = "SELECT $colList FROM orders";
if ($where) $sql .= " WHERE ".implode(' AND ', $where);
$sql .= " ORDER BY id_order DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== SUSUN EXCEL ======
$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();
$ws->setTitle('Data Orders');

$HEADER_ROW = 1;
$FIRST_DATA_ROW = 2;
$HEADER_ROW_HEIGHT = 28;

// Header
foreach ($cols as $i => $colName) {
  $colIndex = $i + 1;
  $A1 = Coordinate::stringFromColumnIndex($colIndex) . $HEADER_ROW;
  $ws->setCellValue($A1, $colName);
}
$lastColLetter = Coordinate::stringFromColumnIndex(count($cols));
$ws->getStyle("A{$HEADER_ROW}:{$lastColLetter}{$HEADER_ROW}")->getFont()->setBold(true);
$ws->getStyle("A{$HEADER_ROW}:{$lastColLetter}{$HEADER_ROW}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$ws->getStyle("A{$HEADER_ROW}:{$lastColLetter}{$HEADER_ROW}")->getFill()
   ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DFF5E1');
$ws->getStyle("A{$HEADER_ROW}:{$lastColLetter}{$HEADER_ROW}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$ws->getRowDimension($HEADER_ROW)->setRowHeight($HEADER_ROW_HEIGHT);

// Data
$r = $FIRST_DATA_ROW;
foreach ($rows as $row) {
  foreach ($cols as $i => $colName) {
    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
    $cell = $colLetter.$r;
    $val = $row[$colName];

    $forceStringCols = [
      'no_sc','no_internet','no_voip','ncli','cp_pelanggan','qrcode',
      'sn_ont_type','stb_id_type','tag_odp','tag_pelanggan','valins_id',
      'no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama','sender_username'
    ];
    if (in_array($colName, $forceStringCols, true)) {
      $ws->setCellValueExplicit($cell, (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    } else {
      if ($colName === 'created_at' && $val) {
        $ws->setCellValue($cell, date('d M Y H:i:s', strtotime((string)$val)));
      } else {
        $ws->setCellValue($cell, $val);
      }
    }
  }
  $ws->getStyle("A{$r}:{$lastColLetter}{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $r++;
}

// Auto-width kolom
for ($i=1; $i<=count($cols); $i++){
  $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Wrap text untuk kolom alamat (kalau ada)
$alamatIdx = array_search('alamat', $cols, true);
if ($alamatIdx !== false) {
  $L = Coordinate::stringFromColumnIndex($alamatIdx + 1);
  $ws->getStyle("{$L}{$FIRST_DATA_ROW}:{$L}{$r}")->getAlignment()->setWrapText(true);
}

// Freeze header & AutoFilter
$ws->freezePane('A2');
$ws->setAutoFilter("A{$HEADER_ROW}:{$lastColLetter}{$HEADER_ROW}");

// Output
$filename = 'data_orders_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;
