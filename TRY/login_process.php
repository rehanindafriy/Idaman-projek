<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/koneksi.php'; // $pdo
require_once __DIR__ . '/auth.php';    // flash(), dll

function deny_login(string $msg){
  if (function_exists('flash')) flash('err', $msg);
  header('Location: index.php'); // form login
  exit;
}

function go(string $path){ header('Location: '.$path); exit; }

// Pastikan method POST (CSRF check: biarkan pakai fungsi mu yg sudah ada)
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo 'Method Not Allowed'; exit;
}

// Ambil input
$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
if ($username === '' || $password === '') {
  deny_login('Username atau password salah.');
}

// Ambil user by username
try {
  $st = $pdo->prepare("
    SELECT id_akses, username, password, role, status, done
      FROM akses_login
     WHERE username = ?
     LIMIT 1
  ");
  $st->execute([$username]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  deny_login('Terjadi kesalahan saat login.');
}

if (!$u) {
  deny_login('Username atau password salah.');
}

// Cek password (dukung hash atau plaintext sementara)
$stored = (string)($u['password'] ?? '');
$looks_hashed = (strpos($stored, '$2y$') === 0) || (strpos($stored, '$2a$') === 0) || (strpos($stored, '$argon2') === 0);

$okPass = $looks_hashed ? password_verify($password, $stored)
                        : hash_equals($stored, $password);

if (!$okPass) {
  deny_login('Username atau password salah.');
}

// Syarat status & done
$status = strtolower((string)$u['status']);
$done   = strtolower((string)$u['done']);

if ($status !== 'active') {
  deny_login('Akun masih pending atau sedang ditangguhkan.');
}
if ($done !== 'yes') {
  deny_login('Akun belum terverifikasi, Silahkan Masukkan Kode Verifikasi.');
}
session_regenerate_id(true);

// simpan ke satu paket session: $_SESSION['user']
$_SESSION['user'] = [
  'id'       => (int)$u['id_akses'],
  'username' => (string)$u['username'],
  'name'     => (string)($u['nama'] ?? ''),          // boleh kosong kalau tidak ada
  'role'     => strtolower((string)$u['role']),      // 'admin' | 'ta' | 'tech'
];

// (opsional) bersihkan kunci lama supaya konsisten
unset($_SESSION['pending_uid'], $_SESSION['pending_uname'], $_SESSION['role']);

if (function_exists('flash')) {
  $disp = $_SESSION['user']['name'] !== '' ? $_SESSION['user']['name'] : $_SESSION['user']['username'];
  flash('ok', 'Login berhasil. Selamat datang, '.$disp.'.');
}

// redirect by role
switch ($_SESSION['user']['role']) {
  case 'admin':
    header('Location: dashboard_admin.php'); exit;
  case 'ta':
    header('Location: dashboard_ta.php'); exit;
  case 'tech':
    header('Location: dashboard_ta.php'); exit; // ganti jika punya dashboard_tech.php
  default:
    header('Location: dashboard_ta.php'); exit;  // fallback
}
