<?php
// download_data.php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/koneksi.php';              // $pdo (PDO) harus tersedia

/* ===== Fallback untuk server PHP < 8.1 ===== */
require_once __DIR__ . '/vendor/autoload.php';


/* ===== PHP >= 8.1: baru load Composer & lanjut XLSX ===== */


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


function abortWith($code = 400, $msg = 'Bad Request'){
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

$id = isset($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
if ($id <= 0) {
    abortWith(400, 'Parameter id_order tidak valid.');
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    abortWith(404, 'Data order tidak ditemukan.');
}

/** Mapping kolom => judul kolom di Excel */
$columns = [
    'id_order'           => 'ID Order',
    'order_type'         => 'Order Type',
    'no_sc'              => 'No SC',
    'no_internet'        => 'No Internet',
    'no_voip'            => 'No VOIP',
    'ncli'               => 'NCLI',
    'nama_pelanggan'     => 'Nama Pelanggan',
    'alamat'             => 'Alamat',
    'cp_pelanggan'       => 'Contact Person',
    'sto'                => 'STO',
    'mitra'              => 'Mitra',
    'odp_real'           => 'ODP Real',
    'port'               => 'Port',
    'marking_dc_awal'    => 'Marking DC Awal',
    'marking_dc_akhir'   => 'Marking DC Akhir',
    'ac_of_sm_1b'        => 'AC/OF/SM 1B',
    'ac_of_sm_1_3sl'     => 'AC/OF/SM 1-3SL',
    'clamp_hook'         => 'Clamp/Hook',
    'otp_ftth_1'         => 'OTP/FTTH 1',
    'prekso_intra_15_rs' => 'Prekso Intra 15 RS',
    'prekso_intra_20_rs' => 'Prekso Intra 20 RS',
    'rs_in_sc_1'         => 'RS in SC 1',
    's_clamp_sprinter'   => 'S Clamp Sprinter',
    'soc_ils'            => 'SOC ILS',
    'soc_sum'            => 'SOC SUM',
    'qrcode'             => 'QR Code',
    'sn_ont_type'        => 'SN ONT / Type',
    'stb_id_type'        => 'STB ID / Type',
    'tag_odp'            => 'Tag ODP',
    'tag_pelanggan'      => 'Tag Pelanggan',
    'labor'              => 'Labor',
    'valins_id'          => 'Valins ID',
    'slot'               => 'Slot',
    'portt'              => 'Port T',
    'ip_olt'             => 'IP OLT',
    'hasil_valins'       => 'Hasil Valins',
    'onu_gendong'        => 'ONU Gendong',
    'no_inet_lama'       => 'No Inet Lama',
    'no_voip_lama'       => 'No VOIP Lama',
    'sn_ont_lama'        => 'SN ONT Lama',
    'stbid_lama'         => 'STB ID Lama',
    'sender_username'    => 'Sender Username',
    'created_at'         => 'Created At',
];

// === Bangun spreadsheet ===
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('e-Siklo')
    ->setTitle('Order #' . $row['id_order'])
    ->setSubject('Detail Order')
    ->setDescription('Export detail order e-Siklo');

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Order');

// Header
$headerLabels = array_values($columns);
$sheet->fromArray($headerLabels, null, 'A1');

// Data
$dataRow = [];
foreach ($columns as $key => $label) {
    $val = $row[$key] ?? '';
    if ($key === 'created_at' && !empty($val)) {
        $dt = new DateTime($val);
        $val = $dt->format('d-m-Y H:i');
    }
    $dataRow[] = $val;
}
$sheet->fromArray([$dataRow], null, 'A2');

// Style header: hijau (#12824F), teks putih, tebal, tengah
$highestColumn = $sheet->getHighestColumn(); // contoh: 'AQ'
$headerRange   = "A1:{$highestColumn}1";

$sheet->getStyle($headerRange)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('12824F');

$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Border tipis untuk semua sel terisi
$usedRange = "A1:{$highestColumn}2";
$sheet->getStyle($usedRange)->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

// Freeze baris header
$sheet->freezePane('A2');

// Auto filter di header
$sheet->setAutoFilter($headerRange);

// --- AUTO FIT JARAK TABEL & KONTEN ---
// --- LAYOUT: MELEBAR KE SAMPING & TINGGI MAKS 2 BARIS ---

$lastRow = $sheet->getHighestRow();

// Kolom yang harus bertipe TEKS (hindari 1.53E+11, nomor telp, IP, dsb)
$asText = [
    'no_sc','no_internet','no_voip','ncli','cp_pelanggan',
    'no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama',
    'sn_ont_type','stb_id_type','ip_olt'
];

// Kolom yang boleh melipat (wrap). Lainnya single-line.
$wrapCols = ['alamat'];

// Lebar kolom (karakter) agar cenderung melebar ke samping
$widths = [
  'order_type'     => 12,
  'no_sc'          => 35,
  'no_internet'    => 25,
  'cp_pelanggan'   => 22,
  'nama_pelanggan' => 25,
  'alamat'         => 60,
  'sto'            => 10,
  'mitra'          => 12,
  'odp_real'       => 22
];
$defaultWidth = 14;

// Terapkan per kolom
$idx = 1;
foreach ($columns as $key => $label) {
    $col = Coordinate::stringFromColumnIndex($idx);

    // 1) Paksa tipe TEKS utk kolom tertentu (hindari scientific notation)
    if (in_array($key, $asText, true)) {
        $val = isset($row[$key]) ? (string)$row[$key] : '';
        $sheet->setCellValueExplicit($col.'2', $val, DataType::TYPE_STRING);
        $sheet->getStyle($col.'2')->getNumberFormat()->setFormatCode('@');
    }

    // 2) Perlebar kolom (no autosize, width manual)
    $sheet->getColumnDimension($col)->setAutoSize(false);
    $sheet->getColumnDimension($col)->setWidth($widths[$key] ?? $defaultWidth);

    // 3) Bungkus teks HANYA untuk kolom tertentu (alamat). Lainnya 1 baris.
    $sheet->getStyle($col.'2')->getAlignment()->setWrapText(in_array($key, $wrapCols, true));

    $idx++;
}

// Tinggi baris: header 1 baris, data ≈ 2 baris
$sheet->getRowDimension(1)->setRowHeight(22);  // header
$sheet->getRowDimension(2)->setRowHeight(32);  // ~2 baris
$sheet->getStyle('A2:'.$highestColumn.'2')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

// Header tetap satu baris, rata tengah
$sheet->getStyle("A1:{$highestColumn}1")->getAlignment()
      ->setWrapText(false)
      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER);

// AutoSize setiap kolom agar lebar ngikut konten
foreach (range('A', $highestColumn) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
// --- END AUTO FIT ---

// Output ke browser
$fname = sprintf(
    'order-%d-%s.xlsx',
    (int)$row['id_order'],
    preg_replace('/[^A-Za-z0-9_-]+/','-', (string)$row['order_type'])
);

if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
