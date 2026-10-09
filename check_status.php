<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/koneksi.php';

$uid = (int)($_SESSION['pending_uid'] ?? 0);
$username = (string)($_SESSION['pending_uname'] ?? '');

$out = ['status' => null, 'has_code' => false, 'username' => $username];

try {
  if ($uid > 0) {
    $st = $pdo->prepare("SELECT status, verifikasi FROM akses_login WHERE id_akses=? LIMIT 1");
    $st->execute([$uid]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['status'] = strtolower((string)$row['status']);
      $out['has_code'] = isset($row['verifikasi']) && $row['verifikasi'] !== '' && $row['verifikasi'] !== null;
    }
  } elseif ($username !== '') {
    $st = $pdo->prepare("SELECT status, verifikasi FROM akses_login WHERE username=? LIMIT 1");
    $st->execute([$username]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['status'] = strtolower((string)$row['status']);
      $out['has_code'] = isset($row['verifikasi']) && $row['verifikasi'] !== '' && $row['verifikasi'] !== null;
    }
  }
} catch (Throwable $e) {
  // ignore
}

echo json_encode($out);
