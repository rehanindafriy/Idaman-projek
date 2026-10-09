<?php
// export_all_orders.php
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

// ====== AMBIL SEMUA KOLOM DINAMIS (sesuai urutan tabel) ======
$sqlCols = "SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
            ORDER BY ORDINAL_POSITION";
$cols = $pdo->query($sqlCols)->fetchAll(PDO::FETCH_COLUMN);
if (!$cols) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Gagal membaca kolom tabel orders.'; exit;
}

$colList = implode(',', array_map(fn($c)=>"`$c`", $cols));

// ====== QUERY SEMUA DATA (TANPA FILTER) ======
$sql  = "SELECT $colList FROM orders ORDER BY id_order DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();

// ====== SUSUN EXCEL (sama gaya dengan export_orders.php) ======
$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();
$ws->setTitle('Data Orders (All)');

$HEADER_ROW        = 1;
$FIRST_DATA_ROW    = 2;
$HEADER_ROW_HEIGHT = 28;

// ---------- HEADER (KAPITAL) ----------
foreach ($cols as $i => $colName) {
  $colIndex = $i + 1;
  $A1 = Coordinate::stringFromColumnIndex($colIndex) . $HEADER_ROW;
  $ws->setCellValue($A1, strtoupper($colName)); // <- jadi KAPITAL
}

$lastColLetter = Coordinate::stringFromColumnIndex(count($cols));
$headerRange   = "A{$HEADER_ROW}:{$lastColLetter}{$HEADER_ROW}";

// ---------- STYLE HEADER: BIRU TUA + TEKS PUTIH ----------
$ws->getStyle($headerRange)->applyFromArray([
  'font' => [
    'bold'  => true,
    'color' => ['rgb' => 'FFFFFF'],      // teks putih
    'size'  => 11,
  ],
  'alignment' => [
    'horizontal' => Alignment::HORIZONTAL_CENTER,
    'vertical'   => Alignment::VERTICAL_CENTER,
  ],
  'fill' => [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => '0B3A53'], // BIRU TUA (ubah kalau mau)
  ],
  'borders' => [
    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
  ],
]);

$ws->getRowDimension($HEADER_ROW)->setRowHeight($HEADER_ROW_HEIGHT);


// Kolom yang dipaksa string (copy dari contohmu)
$forceStringCols = [
  'no_sc','no_internet','no_voip','ncli','cp_pelanggan','qrcode',
  'sn_ont_type','stb_id_type','tag_odp','tag_pelanggan','valins_id',
  'no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama','sender_username'
];

// Data (streaming baris-per-baris supaya aman untuk data besar)
$r = $FIRST_DATA_ROW;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  foreach ($cols as $i => $colName) {
    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
    $cell      = $colLetter.$r;
    $val       = $row[$colName];

    if (in_array($colName, $forceStringCols, true)) {
      $ws->setCellValueExplicit(
        $cell,
        (string)$val,
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
      );
    } else {
      if ($colName === 'created_at' && $val) {
        $ws->setCellValue($cell, date('d M Y H:i:s', strtotime((string)$val)));
      } else {
        $ws->setCellValue($cell, $val);
      }
    }
  }
  // border tipis per baris
  $ws->getStyle("A{$r}:{$lastColLetter}{$r}")
     ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $r++;
}

// Auto-width kolom sesuai isi
for ($i=1; $i<=count($cols); $i++){
  $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Wrap text untuk kolom "alamat" bila ada
$alamatIdx = array_search('alamat', $cols, true);
if ($alamatIdx !== false) {
  $L = Coordinate::stringFromColumnIndex($alamatIdx + 1);
  $ws->getStyle("{$L}{$FIRST_DATA_ROW}:{$L}{$r}")
     ->getAlignment()->setWrapText(true);
}

// Freeze header & AutoFilter
$ws->freezePane('A2');
$ws->setAutoFilter("A{$HEADER_ROW}:{$lastColLetter}{$HEADER_ROW}");

// Output
$filename = 'data_orders_all_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;
