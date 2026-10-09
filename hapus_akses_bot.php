<?php
require_once __DIR__ . '/koneksi.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
  try {
    $st = $pdo->prepare("DELETE FROM akses_bot WHERE id_akses_bot = ?");
    $st->execute([$id]);
  } catch (Throwable $e) {
    // kalau mau, bisa tulis log di sini
  }
}

header('Location: akses_bot.php');
exit;
