<?php
// orders_options.php — sumber opsi NO SC untuk modal Tambah BAI
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/koneksi.php';
header('Content-Type: application/json; charset=utf-8');

/* ==== Ambil identitas login (role + chat_id) ==== */
$role = strtolower((string)($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '')));
$chat = (string)($_SESSION['chat_id'] ?? ($_SESSION['user']['chat_id'] ?? ''));

// fallback: kalau chat_id kosong tapi punya id_akses, ambil dari akses_login
if ((!$chat || !preg_match('/^-?\d{10,}$/', $chat)) && !empty($_SESSION['uid'])) {
  try {
    $st = $pdo->prepare("SELECT chat_id, role FROM akses_login WHERE id_akses=? LIMIT 1");
    $st->execute([$_SESSION['uid']]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $chat = (string)($r['chat_id'] ?? '');
      if (!$role) $role = strtolower((string)($r['role'] ?? ''));
      $_SESSION['chat_id'] = $chat;
      $_SESSION['role'] ??= $role;
    }
  } catch (Throwable $e) { /* ignore */ }
}

/* ==== Identitas tambahan dari sesi (username & nama) ==== */
$meUsernameRaw = (string)($_SESSION['user']['username'] ?? $_SESSION['username'] ?? '');
$meNameRaw     = (string)($_SESSION['nama'] ?? '');

$unameExact = trim($meUsernameRaw);
$nameExact  = preg_replace('/\s+/', ' ', trim($meNameRaw)); // rapikan spasi ganda

$CANDS_EXACT = array_values(array_unique(array_filter([$unameExact, $nameExact], static function($v){
  return $v !== '';
})));

// normalisasi utk pembanding longgar: lower + hilangkan '@' & spasi
$normalize = static function(string $v): string {
  $v = trim($v);
  $v = strtolower($v);
  return str_replace(['@',' '], '', $v);
};
$CANDS_NORM = array_values(array_unique(array_map($normalize, $CANDS_EXACT)));

$isTech   = ($role === 'tech');
$isChatOk = ($chat && preg_match('/^-?\d{10,}$/', $chat));

/* ==== Param dari TomSelect ==== */
$term   = trim((string)($_GET['term']  ?? ''));
$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

/* ==== WHERE dasar ==== */
$where   = ["o.no_sc IS NOT NULL", "o.no_sc <> ''"];
$params  = [];
$OWNER_PARAMS = [];

/* ==== Filter pencarian NO SC ==== */
if ($term !== '') {
  $where[]         = "o.no_sc LIKE :term";
  $params[':term'] = "%{$term}%";
}

/* ==== 3 jalur kepemilikan (hanya diterapkan untuk role 'tech') ====
   (1) user_id = chat_id
   (2) sender_username = (username|nama) apa adanya
   (3) normalisasi(sender_username) = normalisasi(username|nama)
==================================================================== */
if ($isTech) {
  $ownerParts = [];

  // (1) user_id ↔ chat_id
  if ($isChatOk) {
    $ownerParts[] = "o.user_id = :uid";
    $OWNER_PARAMS[':uid'] = $chat;
  }

  // (2) sender_username ↔ kandidat exact (username/nama) apa adanya
  if (!empty($CANDS_EXACT)) {
    $ph = [];
    foreach ($CANDS_EXACT as $i => $v) {
      $k = ":su{$i}";
      $ph[] = $k;
      $OWNER_PARAMS[$k] = $v;
    }
    $ownerParts[] = "o.sender_username IN (" . implode(',', $ph) . ")";
  }

  // (3) sender_username (dinormalisasi) ↔ kandidat dinormalisasi
  if (!empty($CANDS_NORM)) {
    $ph2 = [];
    foreach ($CANDS_NORM as $i => $v) {
      $k = ":sn{$i}";
      $ph2[] = $k;
      $OWNER_PARAMS[$k] = $v;
    }
    // REPLACE(LOWER(sender_username),'@','') lalu hilangkan spasi
    $ownerParts[] = "REPLACE(REPLACE(LOWER(o.sender_username), '@',''), ' ','') IN (" . implode(',', $ph2) . ")";
  }

  // jika ada minimal satu jalur, gabung pakai OR; kalau tidak ada identitas sama sekali, kosongkan hasil
  if (!empty($ownerParts)) {
    $where[] = '(' . implode(' OR ', $ownerParts) . ')';
  } else {
    // tidak ada identitas yang bisa dicocokkan → jangan tampilkan apapun
    $where[] = '0';
  }
}

/* ==== Query: NO SC unik, terbaru di atas ==== */
$sql = "SELECT o.no_sc, MAX(o.id_order) AS mx
        FROM orders AS o";

if ($where) $sql .= " WHERE " . implode(' AND ', $where);

$sql .= " GROUP BY o.no_sc
          ORDER BY mx DESC
          LIMIT :lim OFFSET :off";

$stmt = $pdo->prepare($sql);

// bind params
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v);
}
foreach ($OWNER_PARAMS as $k => $v) {
  $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $limit + 1, PDO::PARAM_INT);  // overfetch 1 baris utk 'more'
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
$more = false;
if (count($rows) > $limit) { $more = true; $rows = array_slice($rows, 0, $limit); }

/* ==== Response utk TomSelect ==== */
echo json_encode([
  'options' => array_values(array_unique($rows)),
  'more'    => $more,
]);
