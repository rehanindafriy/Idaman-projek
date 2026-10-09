<?php
// chart_data.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/koneksi.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($conn) && $conn instanceof PDO) { $pdo = $conn; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo json_encode(['error' => 'DB connection not available']); exit;
}

/* ===== helpers ===== */
function pick_date_col(PDO $pdo, string $table, array $candidates) {
  foreach ($candidates as $c) {
    $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $s->execute([$c]);
    if ($s->fetch(PDO::FETCH_ASSOC)) return $c;
  }
  return null;
}
function build_where(?string $col, array $q) {
  if (!$col) return ['', []];
  $p = []; $w = '';
  switch ($q['mode'] ?? 'semua') {
    case 'hari_ini':  $w="WHERE DATE($col)=CURDATE()"; break;
    case 'tanggal':   if(!empty($q['date'])){ $w="WHERE DATE($col)=:d"; $p[':d']=$q['date']; } break;
    case 'rentang':   if(!empty($q['start']) && !empty($q['end'])){ $w="WHERE DATE($col) BETWEEN :a AND :b"; $p[':a']=$q['start']; $p[':b']=$q['end']; } break;
    case 'bulan':     if(!empty($q['month']) && !empty($q['year'])){ $w="WHERE MONTH($col)=:m AND YEAR($col)=:y"; $p[':m']=(int)$q['month']; $p[':y']=(int)$q['year']; } break;
    case 'tahun':     if(!empty($q['year'])){ $w="WHERE YEAR($col)=:y"; $p[':y']=(int)$q['year']; } break;
    default: break;
  }
  return [$w,$p];
}
function label_two_lines($name){
  $name = trim((string)$name);
  if ($name==='') return '';
  $parts = preg_split('/\s+/', $name, 2);
  return count($parts)===2 ? ($parts[0] . '\n' . $parts[1]) : $name;
}

/* ===== ambil filter ===== */
$q = [
  'mode'  => $_GET['mode']  ?? 'semua',
  'date'  => $_GET['date']  ?? '',
  'start' => $_GET['start'] ?? '',
  'end'   => $_GET['end']   ?? '',
  'month' => $_GET['month'] ?? '',
  'year'  => $_GET['year']  ?? ''
];

/* ===== tentukan kolom tanggal yang tersedia ===== */
$ordCol = pick_date_col($pdo, 'orders', ['created_at','tanggal','tgl','date','datetime','waktu','createdTime']);
$baiCol = pick_date_col($pdo, 'bai',    ['created_at','tanggal','tgl','date','datetime','waktu','uploaded_at']);

/* ===== agregat ORDERS ===== */
list($wO,$pO) = build_where($ordCol,$q);
$sqlO = "SELECT LOWER(TRIM(sender_username)) u, COUNT(*) cnt FROM orders $wO GROUP BY u";
$stO  = $pdo->prepare($sqlO); $stO->execute($pO);
$mapO = []; foreach($stO->fetchAll(PDO::FETCH_ASSOC) as $r){ $mapO[$r['u']] = (int)$r['cnt']; }

/* ===== agregat BAI ===== */
list($wB,$pB) = build_where($baiCol,$q);
$sqlB = "SELECT LOWER(TRIM(uploader)) u, COUNT(*) cnt FROM bai $wB GROUP BY u";
$stB  = $pdo->prepare($sqlB); $stB->execute($pB);
$mapB = []; foreach($stB->fetchAll(PDO::FETCH_ASSOC) as $r){ $mapB[$r['u']] = (int)$r['cnt']; }

/* ===== daftar teknisi ===== */
$tech = $pdo->query("SELECT id_tech, nama_tech, username_tech FROM tech ORDER BY nama_tech ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ===== susun payload ===== */
$labels=[]; $orders=[]; $bai=[]; $out=[]; $table=[];
$tO=0; $tB=0; $tOut=0; $no=1;

foreach($tech as $t){
  $u = strtolower(trim($t['username_tech'] ?? ''));
  $o = $mapO[$u] ?? 0;
  $b = $mapB[$u] ?? 0;

  // hanya yang punya data agar chart rapi
  if ($o===0 && $b===0) continue;

  $labels[] = label_two_lines($t['nama_tech'] ?: $t['username_tech']);
  $orders[] = $o;
  $bai[]    = $b;
  $diff     = abs($o - $b);   // <= kunci: selisih ABSOLUT
  $out[]    = $diff;

  $tO += $o; $tB += $b; $tOut += $diff;

  $table[] = [
    'no'     => $no++,
    'name'   => $t['nama_tech'] ?: $t['username_tech'],
    'user'   => $t['username_tech'],
    'orders' => $o,
    'bai'    => $b,
    'out'    => $diff
  ];
}

echo json_encode([
  'labels'=>$labels,
  'orders'=>$orders,
  'bai'=>$bai,
  'out'=>$out,
  'totals'=>['orders'=>$tO,'bai'=>$tB,'out'=>$tOut],
  'table'=>$table
], JSON_UNESCAPED_UNICODE);
