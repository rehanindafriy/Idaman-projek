<?php
declare(strict_types=1);
require_once __DIR__ . '/koneksi.php';
header('Content-Type: application/json; charset=utf-8');

$term  = trim((string)($_GET['term'] ?? ''));
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

try {
  // DISTINCT kode_bai, urut terbaru berdasar id_bai terbesar yang punya kode tsb
  $sql = "
    SELECT t.kode_bai
    FROM (
      SELECT kode_bai, MAX(id_bai) AS max_id
      FROM bai
      WHERE kode_bai <> '' AND LOWER(kode_bai) LIKE LOWER(?)
      GROUP BY kode_bai
    ) AS t
    ORDER BY t.max_id DESC
    LIMIT ? OFFSET ?
  ";
  $st = $pdo->prepare($sql);
  $like = ($term === '') ? '%' : ('%'.$term.'%');
  $st->bindParam(1, $like, PDO::PARAM_STR);
  $st->bindParam(2, $limit, PDO::PARAM_INT);
  $st->bindParam(3, $offset, PDO::PARAM_INT);
  $st->execute();

  $opts = [];
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $v = (string)$r['kode_bai'];
    if ($v !== '') $opts[] = $v;
  }
  echo json_encode(['options' => $opts, 'more' => (count($opts) === $limit)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['options'=>[], 'more'=>false, 'error'=>$e->getMessage()]);
}
