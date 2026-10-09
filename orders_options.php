<?php
/**
 * orders_options.php
 *
 * Dipakai oleh TomSelect di:
 *  - Modal TAMBAH BAI (select #addCodes)
 *  - Modal EDIT BAI   (select #editCodes)
 *
 * Mengembalikan daftar NO SC dari tabel `orders`
 * KECUALI NO SC yang SUDAH dipakai di `bai.kode_bai`.
 *
 * Diurutkan dari data TERBARU (berdasarkan orders.created_at DESC).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/koneksi.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ====== parameter pencarian & paging ======
$term  = trim((string)($_GET['term'] ?? ''));
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));

$offset = ($page - 1) * $limit;

// ====== Subquery: kumpulkan no_sc + waktu terakhir muncul ======
// NOTE: kalau kolom waktunya bukan `created_at`, ganti ke nama kolom yang Press pakai.
$sqlInner = "
  SELECT 
      o.no_sc,
      MAX(o.created_at) AS latest_time
  FROM orders o
  WHERE 
      o.no_sc IS NOT NULL
  AND o.no_sc <> ''
";

$params = [];

// filter LIKE jika ada term
if ($term !== '') {
    $sqlInner .= " AND o.no_sc LIKE :term";
    $params[':term'] = '%' . $term . '%';
}

// group per no_sc (satu baris per NO SC)
$sqlInner .= " GROUP BY o.no_sc";

// ====== Query utama: buang yang sudah dipakai di BAI, urut terbaru ======
$sql = "
  SELECT x.no_sc
  FROM ( $sqlInner ) AS x
  LEFT JOIN bai b
    ON b.kode_bai = x.no_sc
  WHERE b.id_bai IS NULL
  ORDER BY x.latest_time DESC, x.no_sc DESC
  LIMIT :limit_plus_one OFFSET :offset
";

$stmt = $pdo->prepare($sql);

// bind param LIKE (kalau ada)
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}

// ambil +1 baris untuk cek apakah masih ada halaman berikutnya
$stmt->bindValue(':limit_plus_one', $limit + 1, PDO::PARAM_INT);
$stmt->bindValue(':offset',         $offset,        PDO::PARAM_INT);

$stmt->execute();

$options = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $options[] = (string)$row['no_sc'];
}

// cek apakah masih ada data (more)
$more = false;
if (count($options) > $limit) {
    $more    = true;
    $options = array_slice($options, 0, $limit);
}

// kirim JSON ke TomSelect
echo json_encode([
    'options' => $options,
    'more'    => $more,
]);
