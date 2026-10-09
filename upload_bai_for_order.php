<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/koneksi.php';

/* ===== Util ===== */
$BAI_FS_DIR = __DIR__ . '/bot/file/';
if (!is_dir($BAI_FS_DIR)) { @mkdir($BAI_FS_DIR, 0777, true); }

function back(string $msg = ''): void {
  $_SESSION['flash_msg'] = $msg;
  header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'bai.php'));
  exit;
}
function unique_name(string $dir, string $name): string {
  $base = pathinfo($name, PATHINFO_FILENAME);
  $ext  = pathinfo($name, PATHINFO_EXTENSION);
  $i = 0; $n = $name;
  while (file_exists($dir . $n)) { $i++; $n = $base . " ($i)" . ($ext ? ".$ext" : ''); }
  return $n;
}
/* Helper cek kolom & pilih yang tersedia (biar fleksibel) */
function db_col_exists(PDO $pdo, string $table, string $col): bool {
  $sql = "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$table, $col]);
  return (bool)$st->fetchColumn();
}
function pick_col(PDO $pdo, string $table, array $cands): ?string {
  foreach ($cands as $c) if (db_col_exists($pdo, $table, $c)) return $c;
  return null;
}

/* ===== Input & Validasi ===== */
$no_sc = trim((string)($_POST['no_sc'] ?? ''));
if ($no_sc === '') back('NO SC kosong.');

if (!isset($_FILES['bai_file']) || $_FILES['bai_file']['error'] !== UPLOAD_ERR_OK) {
  back('Pilih file terlebih dahulu.');
}
$allow = ['pdf','doc','docx','xls','xlsx'];
$ext   = strtolower(pathinfo($_FILES['bai_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allow, true)) back('Ekstensi tidak diperbolehkan.');

/* ===== Simpan file fisik ===== */
$saveName = unique_name($BAI_FS_DIR, $_FILES['bai_file']['name']);
if (!move_uploaded_file($_FILES['bai_file']['tmp_name'], $BAI_FS_DIR . $saveName)) {
  back('Gagal menyimpan file.');
}

/* ===== Ambil metadata dari ORDERS berdasarkan no_sc =====
   - uploader    <= sender_username (fallback session username / "-")
   - user_id     <= user_id         (fallback 0)
   - date_received <= created_at    (fallback NOW)
*/
try {
  // Cari nama kolom yang tersedia
  $colNo     = pick_col($pdo, 'orders', ['no_sc','order_no','no_order','service_number']) ?? 'no_sc';
  $colSender = pick_col($pdo, 'orders', ['sender_username','from','from_name','created_by','uploader','user']);
  $colUid    = pick_col($pdo, 'orders', ['user_id','uid','userID','id_user']);
  $colTs     = pick_col($pdo, 'orders', ['created_at','inserted_at','tgl_create','tgl','tanggal','ts','created']);

  // Build SELECT dinamis (yang tidak ada akan dipaksa NULL)
  $selSender = $colSender ? "`$colSender` AS sender_username" : "NULL AS sender_username";
  $selUid    = $colUid    ? "`$colUid` AS user_id"            : "NULL AS user_id";
  $selTs     = $colTs     ? "`$colTs` AS created_at"          : "NULL AS created_at";

  $sql = "SELECT $selSender, $selUid, $selTs
          FROM `orders`
          WHERE `$colNo` = :nos
          ORDER BY " . ($colTs ? "`$colTs` DESC" : "1") . "
          LIMIT 1";
  $st  = $pdo->prepare($sql);
  $st->execute([':nos' => $no_sc]);
  $ord = $st->fetch(PDO::FETCH_ASSOC) ?: [];

  $uploader = (string)($ord['sender_username'] ?? ($_SESSION['user']['username'] ?? $_SESSION['username'] ?? '-'));
  $userId   = (string)($ord['user_id'] ?? ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0));
  $dtRecv   = (string)($ord['created_at'] ?? date('Y-m-d H:i:s'));

  /* ===== Insert ke BAI ===== */
  // Pastikan kolom user_id ada di tabel bai; kalau tidak ada, pakai insert tanpa user_id
  $baiHasUserId = db_col_exists($pdo, 'bai', 'user_id');

  if ($baiHasUserId) {
    $ins = $pdo->prepare(
      "INSERT INTO bai (kode_bai, nama_bai, uploader, user_id, date_received)
       VALUES (:kode, :nama, :uploader, :uid, :dt)"
    );
    $ins->execute([
      ':kode'     => $no_sc,
      ':nama'     => $saveName,
      ':uploader' => $uploader,
      ':uid'      => $userId,
      ':dt'       => $dtRecv,
    ]);
  } else {
    $ins = $pdo->prepare(
      "INSERT INTO bai (kode_bai, nama_bai, uploader, date_received)
       VALUES (:kode, :nama, :uploader, :dt)"
    );
    $ins->execute([
      ':kode'     => $no_sc,
      ':nama'     => $saveName,
      ':uploader' => $uploader,
      ':dt'       => $dtRecv,
    ]);
  }

  back('Upload berhasil. Metadata tersalin dari orders.');
} catch (Throwable $e) {
  @unlink($BAI_FS_DIR . $saveName);
  back('DB error: ' . $e->getMessage());
}
