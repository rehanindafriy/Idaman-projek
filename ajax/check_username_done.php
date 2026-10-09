<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php'; // pastikan $pdo ada

header('Content-Type: application/json; charset=utf-8');

$u = trim(ltrim((string)($_GET['u'] ?? ''), '@'));
$out = ['ok'=>true, 'exists'=>false, 'done'=>null];

if ($u !== '') {
  try {
    $st = $pdo->prepare("SELECT done FROM akses_login WHERE LOWER(username)=LOWER(?) LIMIT 1");
    $st->execute([$u]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $out['exists'] = true;
      $out['done']   = (string)($row['done'] ?? '');
    }
  } catch (Throwable $e) {
    $out['ok'] = false;
  }
}

echo json_encode($out);
