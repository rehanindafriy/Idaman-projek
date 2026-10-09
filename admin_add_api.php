<?php
// admin_add_api.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  require_once __DIR__ . '/koneksi.php';
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Baca JSON
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true) ?: [];
  $nama     = trim((string)($in['nama'] ?? ''));
  $username = trim((string)($in['username'] ?? ''));
  $password = (string)($in['password'] ?? '');

  if ($nama === '' || $username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Nama, username, dan password wajib diisi.']); exit;
  }
  if (mb_strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Panjang password minimal 6 karakter.']); exit;
  }

  // Hash password
  $hash = password_hash($password, PASSWORD_BCRYPT);

  // Mulai transaksi
  $pdo->beginTransaction();

  // Pastikan unique username di tabel admin (kalau belum ada unique index)
  // Kamu sudah punya uk_admin_username dari setup trigger; kalau belum, aktifkan baris ini:
  // $pdo->exec("ALTER TABLE admin ADD UNIQUE KEY IF NOT EXISTS uk_admin_username (username)");

  // Cek sudah ada?
  $st = $pdo->prepare("SELECT id_admin FROM admin WHERE LOWER(TRIM(username)) = LOWER(TRIM(?)) LIMIT 1");
  $st->execute([$username]);
  if ($st->fetchColumn()) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'message'=>'Username sudah ada di tabel admin.']); 
    $pdo->rollBack(); exit;
  }

  // Insert ke admin (hash disimpan di kolom password)
  $ins = $pdo->prepare("INSERT INTO admin (nama, username, password) VALUES (?,?,?)");
  $ins->execute([$nama, $username, $hash]);
  $idAdmin = (int)$pdo->lastInsertId();

  // Sinkron ke akses_login:
  // - kalau sudah ada row-nya → update jadi role='admin', status='active', done='yes' + update hash & nama
  // - kalau belum ada → insert baru
  $st = $pdo->prepare("SELECT id_akses FROM akses_login WHERE LOWER(TRIM(username)) = LOWER(TRIM(?)) LIMIT 1");
  $st->execute([$username]);
  $idAkses = $st->fetchColumn();

  if ($idAkses) {
    $up = $pdo->prepare("
      UPDATE akses_login
         SET nama = ?,
             password = ?,
             role = 'admin',
             status = COALESCE(NULLIF(status,''),'active'),
             done = 'yes'
       WHERE id_akses = ?
    ");
    $up->execute([$nama, $hash, $idAkses]);
  } else {
    $iv = $pdo->prepare("
      INSERT INTO akses_login (nama, username, password, role, status, verifikasi, done)
      VALUES (?,?,?,?,?,'', 'yes')
    ");
    $iv->execute([$nama, $username, $hash, 'admin', 'active']);
  }

  $pdo->commit();

  echo json_encode(['ok'=>true,'id_admin'=>$idAdmin]);
} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
