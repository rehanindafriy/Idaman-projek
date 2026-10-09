<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php'; // pastikan $pdo tersedia

header('Content-Type: application/json; charset=utf-8');

$u = ltrim((string)($_GET['u'] ?? ''), '@');
$u = trim($u);

$exists = false;
if ($u !== '') {
  try {
    $st = $pdo->prepare("SELECT 1 FROM akses_login WHERE LOWER(username)=LOWER(?) LIMIT 1");
    $st->execute([$u]);
    $exists = (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    // biarkan $exists=false
  }
}

echo json_encode(['ok' => true, 'exists' => $exists]);
