<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';            // akses $pdo, csrf_verify() jika ada
header('Content-Type: application/json; charset=utf-8');

try {
  // Validasi method
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'err' => 'Method not allowed']);
    exit;
  }

  // Ambil input
  $id   = (int)($_POST['id_akses'] ?? 0);
  $csrf = (string)($_POST['csrf'] ?? '');

  if (function_exists('csrf_verify')) {
    if (!csrf_verify($csrf)) {
      echo json_encode(['ok' => false, 'err' => 'CSRF invalid']);
      exit;
    }
  }

  if ($id <= 0) {
    echo json_encode(['ok' => false, 'err' => 'ID tidak valid']);
    exit;
  }

  // Pastikan user ada
  $st = $pdo->prepare("SELECT id_akses FROM akses_login WHERE id_akses=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    echo json_encode(['ok' => false, 'err' => 'User tidak ditemukan']);
    exit;
  }

  // Generate 4 digit
  $code = (string)random_int(1000, 9999);

  // Simpan ke DB
  $up = $pdo->prepare("UPDATE akses_login SET otp=? WHERE id_akses=?");
  $up->execute([$code, $id]);

  echo json_encode(['ok' => true, 'otp' => $code]);
} catch (Throwable $e) {
  // (opsional) log error
  echo json_encode(['ok' => false, 'err' => 'Server error']);
}
