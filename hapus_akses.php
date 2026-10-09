<?php
// hapus_akses.php (FIX)
declare(strict_types=1);

require_once __DIR__ . '/auth.php';               // $pdo, flash()
require_once __DIR__ . '/redirect_helper.php';    // <-- WAJIB, karena pakai redirect()

$id   = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$back = $_SERVER['HTTP_REFERER'] ?? 'akses_login.php';

if ($id <= 0) {
  if (function_exists('flash')) flash('err', 'ID tidak valid.');
  redirect($back); exit;
}

try {
  $pdo->beginTransaction();

  // Hapus baris
  $st = $pdo->prepare("DELETE FROM akses_login WHERE id_akses=? LIMIT 1");
  $st->execute([$id]);
  $deleted = $st->rowCount();

  // Cek ada FK ke akses_login.id_akses
  $schema = (string)($pdo->query("SELECT DATABASE()")->fetchColumn() ?? '');
  $q = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = ?
      AND REFERENCED_TABLE_NAME = 'akses_login'
      AND REFERENCED_COLUMN_NAME = 'id_akses'
  ");
  $q->execute([$schema]);
  $hasFKs = ((int)$q->fetchColumn()) > 0;

  // Urutkan ulang ID hanya jika aman (tanpa FK)
  if ($deleted && !$hasFKs) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('SET @i:=0');
    $pdo->exec('UPDATE akses_login SET id_akses = (@i:=@i+1) ORDER BY id_akses');
    $next = (int)$pdo->query('SELECT IFNULL(MAX(id_akses),0)+1 FROM akses_login')->fetchColumn();
    $pdo->exec("ALTER TABLE akses_login AUTO_INCREMENT={$next}");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
  }

  $pdo->commit();

  if (function_exists('flash')) {
    if (!$deleted) {
      flash('err', 'Data tidak ditemukan / sudah terhapus.');
    } else {
      flash('ok', !$hasFKs
        ? 'Data dihapus dan ID diurutkan ulang.'
        : 'Data dihapus. ID tidak diurutkan ulang karena ada relasi ke tabel lain.');
    }
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  if (function_exists('flash')) flash('err', 'Gagal menghapus: '.$e->getMessage());
}

redirect($back); exit;
