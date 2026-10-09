<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/koneksi.php'; // $pdo

$field = $_GET['field'] ?? '';
$term  = trim((string)($_GET['term'] ?? ''));
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 50;
$off   = ($page - 1) * $per;

$map = [
  'no_sc'          => 'no_sc',
  'no_internet'    => 'no_internet',
  'nama_pelanggan' => 'nama_pelanggan',
  'tag_pelanggan'       => 'tag_pelanggan',
  'cp_pelanggan'   => 'cp_pelanggan',
];
if (!isset($map[$field])) { echo json_encode(['options'=>[], 'more'=>false]); exit; }
$col = $map[$field];

/** Cari kolom timestamp yang ada di tabel */
function findTsCol(PDO $pdo): ?string {
  static $cols = null;
  if ($cols === null) {
    $st = $pdo->query("SHOW COLUMNS FROM `orders`");
    $cols = array_map(fn($r)=>$r['Field'], $st->fetchAll(PDO::FETCH_ASSOC));
  }
  $candidates = ['created_at','tanggal','tgl','tgl_input','inserted_at','waktu','createdAt','ts','date'];
  foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
  // fallback: pakai PK sebagai proxy “waktu”
  foreach (['id','id_order'] as $pk) if (in_array($pk, $cols, true)) return $pk;
  return null;
}
$ts = findTsCol($pdo);

/** Query: GROUP BY nilai unik lalu ORDER BY terbaru */
$params = [];
$sql =
  "SELECT $col AS v, MAX($ts) AS latest
   FROM orders
   WHERE $col IS NOT NULL AND $col <> ''";

if ($term !== '') { $sql .= " AND $col LIKE ?"; $params[] = "%{$term}%"; }

$sql .= " GROUP BY $col
          ORDER BY latest DESC, $col DESC
          LIMIT ? OFFSET ?";

try {
  $st = $pdo->prepare($sql);
  $i = 1;
  if ($term !== '') $st->bindValue($i++, "%{$term}%", PDO::PARAM_STR);
  $st->bindValue($i++, $per + 1, PDO::PARAM_INT); // ambil 1 ekstra utk deteksi 'more'
  $st->bindValue($i++, $off,     PDO::PARAM_INT);
  $st->execute();

  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $more = false;
  if (count($rows) > $per) { $more = true; array_pop($rows); }

  echo json_encode([
    'options' => array_column($rows, 'v'),
    'more'    => $more
  ]);
} catch (Throwable $e) {
  echo json_encode(['options'=>[], 'more'=>false, 'err'=>true]);
}
