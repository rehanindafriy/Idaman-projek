<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';             // $pdo, flash(), redirect()
require_once __DIR__ . '/redirect_helper.php';  // redirect()

$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';

// === validasi ID ===
// dukung POST (id_bai) dan GET (id) agar kompatibel
$id = (int)($_POST['id_bai'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
  if (function_exists('flash')) flash('err', 'ID tidak valid.');
  redirect($back);
}

try {
  // ambil data dulu (untuk nama file)
  $st = $pdo->prepare("SELECT id_bai, nama_bai FROM bai WHERE id_bai=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    if (function_exists('flash')) flash('err', 'Data tidak ditemukan.');
    redirect($back);
  }

  $fileName = basename((string)($row['nama_bai'] ?? ''));

  // lokasi file fisik disimpan bot
  $BAI_FS_DIR = realpath(__DIR__ . '/bot/file') ?: (__DIR__ . '/bot/file');
  $BAI_FS_DIR = rtrim($BAI_FS_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  $fullPath   = $BAI_FS_DIR . $fileName;

  // === hapus row ===
  $pdo->beginTransaction();
  $del = $pdo->prepare("DELETE FROM bai WHERE id_bai=? LIMIT 1");
  $del->execute([$id]);
  $pdo->commit();

  // === reindex id_bai: 1..N dan reset AUTO_INCREMENT ===
  reindex_bai($pdo);

  // cek apakah file masih dipakai row lain
  $st2 = $pdo->prepare("SELECT COUNT(*) FROM bai WHERE nama_bai=?");
  $st2->execute([$fileName]);
  $refs = (int) $st2->fetchColumn();

  if ($refs === 0 && $fileName !== '' && is_file($fullPath)) {
    @unlink($fullPath);
  }

  if (function_exists('flash')) flash('ok', 'Berita Acara dihapus & ID dirapikan.');
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  if (function_exists('flash')) flash('err', 'Gagal menghapus: ' . $e->getMessage());
}

redirect($back);

/**
 * Menjadikan id_bai berurutan kembali mulai 1
 * dan set AUTO_INCREMENT = MAX(id_bai)+1
 *
 * ⚠️ Gunakan HANYA jika id_bai tidak direferensikan FK di tabel lain.
 */
function reindex_bai(PDO $pdo): void {
  // beri nomor ulang 1..N berdasarkan id_bai lama
  $pdo->exec("SET @i := 0");
  // ORDER BY penting agar penomoran stabil
  $pdo->exec("UPDATE bai SET id_bai = (@i := @i + 1) ORDER BY id_bai");

  // set auto_increment ke nilai berikutnya
  $max  = (int)$pdo->query("SELECT IFNULL(MAX(id_bai),0) FROM bai")->fetchColumn();
  $next = max(1, $max + 1);
  $pdo->exec("ALTER TABLE bai AUTO_INCREMENT = {$next}");
}
