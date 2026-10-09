<?php
// bai_options.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/koneksi.php';
header('Content-Type: application/json; charset=utf-8');

// ==== Ambil identitas login ====
$role = strtolower((string)($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '')));

// Ambil username/nama dari session
$meUsernameRaw = (string)($_SESSION['user']['username'] ?? $_SESSION['username'] ?? '');
$meNameRaw     = (string)($_SESSION['nama'] ?? ($_SESSION['user']['nama'] ?? ''));

// Normalisasi util
$normalize = static function (string $v): string {
  $v = trim($v);
  $v = strtolower($v);
  $v = str_replace(['@', ' '], '', $v);
  return $v;
};

// Kandidat exact & normalized (username dan nama)
$unameExact = trim($meUsernameRaw);
$nameExact  = preg_replace('/\s+/', ' ', trim($meNameRaw));

$CANDS_EXACT = array_values(array_unique(array_filter([$unameExact, $nameExact], fn($v) => $v !== '')));
$CANDS_NORM  = array_values(array_unique(array_map($normalize, $CANDS_EXACT)));

$onlyTechOwner = ($role === 'tech' && (!empty($CANDS_EXACT) || !empty($CANDS_NORM)));

// ==== Param pencarian (TomSelect) ====
$term   = trim((string)($_GET['term']  ?? ''));
$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

// ==== Build WHERE ====
$where  = [];
$params = [];

if ($term !== '') {
  $where[]         = 'kode_bai LIKE :term';
  $params[':term'] = '%'.$term.'%';
}

/*
 * === OWNER MATCH (untuk role tech) ===
 * 1) Exact match: uploader == username/nama
 * 2) Exact match dengan prefix '@': uploader == '@' . username/nama
 * 3) Normalized match: abaikan case, spasi, dan '@'
 */
if ($onlyTechOwner) {
  $ownerParts = [];
  $paramsEX   = [];
  $paramsAT   = [];
  $paramsN    = [];

  // Kumpulkan kandidat dari username & nama (apa adanya)
  $candExact = $CANDS_EXACT; // sudah dibangun di atas

  // --- (1) Exact match
  if (!empty($candExact)) {
    $ph = [];
    foreach ($candExact as $i => $v) {
      if ($v === '') continue;
      $k = ':ue' . $i;
      $paramsEX[$k] = $v;
      $ph[] = $k;
    }
    if ($ph) {
      $ownerParts[] = 'uploader IN (' . implode(',', $ph) . ')';
    }
  }

  // --- (2) Exact match dengan '@' prefix
  if (!empty($candExact)) {
    $ph2 = [];
    foreach ($candExact as $i => $v) {
      if ($v === '') continue;
      $k = ':ua' . $i;
      $paramsAT[$k] = '@' . ltrim($v); // pastikan ada '@' di depan
      $ph2[] = $k;
    }
    if ($ph2) {
      $ownerParts[] = 'uploader IN (' . implode(',', $ph2) . ')';
    }
  }

  // --- (3) Normalized match (abaikan case, spasi, dan '@')
  if (!empty($CANDS_EXACT) || !empty($CANDS_NORM)) {
    $j  = 0;
    $ph3 = [];

    // dari EXact → normalisasi di PHP
    foreach ($CANDS_EXACT as $v) {
      $candN = strtolower(str_replace(['@', ' '], '', trim($v)));
      if ($candN === '') continue;
      $k = ':un' . $j++;
      $paramsN[$k] = $candN;
      $ph3[] = $k;
    }
    // dari NORM (kalau sudah ada) langsung pakai
    foreach ($CANDS_NORM as $v) {
      if ($v === '') continue;
      $k = ':un' . $j++;
      $paramsN[$k] = $v;
      $ph3[] = $k;
    }

    if ($ph3) {
      $ownerParts[] =
        "REPLACE(REPLACE(LOWER(COALESCE(uploader,'')),'@',''),' ','') IN (" .
        implode(',', $ph3) . ")";
    }
  }

  if (!empty($ownerParts)) {
    $where[] = '(' . implode(' OR ', $ownerParts) . ')';
    // gabungkan semua parameter owner
    $params = array_merge($params, $paramsEX, $paramsAT, $paramsN);
  } else {
    $where[] = '1=0'; // aman jika tak ada kandidat
  }
}


// ==== Query (distinct per kode, urut terbaru) ====
$sql = 'SELECT kode_bai, MAX(id_bai) AS mx
        FROM bai';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' GROUP BY kode_bai
          ORDER BY mx DESC
          LIMIT :lim OFFSET :off';

$stmt = $pdo->prepare($sql);

// overfetch 1 baris untuk tahu apakah masih ada halaman berikutnya
foreach ($params as $k => $v) {
  // PDO akan infer type kecuali LIMIT/OFFSET
  $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $limit + 1, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
$more = false;
if (count($rows) > $limit) { $more = true; $rows = array_slice($rows, 0, $limit); }

// Output sesuai yang diharapkan TomSelect { options: [...], more: bool }
echo json_encode([
  'options' => array_values(array_unique($rows)),
  'more'    => $more
], JSON_UNESCAPED_UNICODE);
