<?php
declare(strict_types=1);

/**
 * edit_akses.php
 * Update nama, username, dan (optional) password pada tabel akses_login.
 * Dipanggil via AJAX dari form Edit Akses Login.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/koneksi.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function json_error(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_error('Method not allowed', 405);
}

$id       = (int)($_POST['id'] ?? 0);
$nama     = trim((string)($_POST['nama'] ?? ''));
$username = trim((string)($_POST['username'] ?? ''));
$pass     = (string)($_POST['password'] ?? '');
$pass2    = (string)($_POST['password2'] ?? '');

if ($id <= 0)        json_error('Data akses tidak ditemukan.');
if ($nama === '')    json_error('Nama wajib diisi.');
if ($username === '')json_error('Username wajib diisi.');
if ($pass !== '' && strlen($pass) < 6)
  json_error('Password baru minimal 6 karakter.');
if ($pass !== $pass2)
  json_error('Konfirmasi password baru tidak sama.');

// Cek duplikasi username
$cek = $pdo->prepare("SELECT id_akses FROM akses_login WHERE username = ? AND id_akses <> ? LIMIT 1");
$cek->execute([$username, $id]);
if ($cek->fetch()) {
  json_error('Username sudah digunakan akun lain.');
}

// Susun query update
if ($pass !== '') {
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $sql  = "UPDATE akses_login
              SET nama = ?, username = ?, password = ?
            WHERE id_akses = ?";
  $params = [$nama, $username, $hash, $id];
} else {
  $sql = "UPDATE akses_login
             SET nama = ?, username = ?
           WHERE id_akses = ?";
  $params = [$nama, $username, $id];
}

$st = $pdo->prepare($sql);
$st->execute($params);

echo json_encode([
  'ok'  => true,
  'msg' => 'Akses login berhasil diperbarui.'
], JSON_UNESCAPED_UNICODE);
