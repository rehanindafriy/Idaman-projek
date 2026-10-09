<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';            // $pdo, flash(), csrf_verify()
require_once __DIR__ . '/redirect_helper.php'; // redirect()

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
ini_set('log_errors','1');
ini_set('error_log', __DIR__.'/runtime-php-error.log');

set_exception_handler(function(Throwable $e){
  file_put_contents(__DIR__.'/runtime-php-error.log',
    date('c')." [EXC] ".$e->getMessage()."\n".$e->getTraceAsString()."\n", FILE_APPEND);
  http_response_code(500);
  echo "Internal error.";
});

/* ====== KONEKSI DB (PASTIKAN FILE INI ADA) ====== */
require_once __DIR__ . '/koneksi.php';  // mendefinisikan $pdo (PDO)

/* ====== SESSION & HELPER FALLBACK ====== */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('redirect')) {
  function redirect(string $to): void { header('Location: '.$to); exit; }
}
if (!function_exists('flash')) {
  function flash(string $key, string $val): void { $_SESSION['flash'][$key] = $val; }
}
if (!function_exists('csrf_verify')) {
  function csrf_verify(string $t): bool { return true; } // fallback: anggap valid
}
if (!function_exists('str_starts_with')) { // jaga2 bila PHP<8
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' ? true : substr($haystack, 0, strlen($needle)) === $needle;
  }
}


// Hanya terima POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  redirect('login.php');
}

// CSRF (kalau ada)
$csrf_ok = true;
if (function_exists('csrf_verify')) {
  $csrf_ok = csrf_verify($_POST['csrf'] ?? '');
}
if (!$csrf_ok) {
  flash('err', 'Sesi kadaluarsa. Silakan login lagi.');
  redirect('login.php');
}

// Ambil input
$username = strtolower(trim((string)($_POST['username'] ?? '')));
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
  flash('err', 'Masukkan username dan password.');
  redirect('login.php');
}

// Ambil user (abaikan kolom OTP apapun)
$st = $pdo->prepare("
  SELECT id_akses, nama, username, password, role, status, chat_id
  FROM akses_login
  WHERE LOWER(username)=? 
  LIMIT 1
");
$st->execute([$username]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  flash('err', 'Username atau password salah.');
  redirect('login.php');
}

// Pastikan status aktif (silakan matikan blok ini jika tidak dipakai)
$status = strtolower((string)($user['status'] ?? ''));
if ($status !== '' && $status !== 'active') {
  flash('err', 'Akun belum aktif / ditangguhkan.');
  redirect('login.php');
}

// Verifikasi password (dukung hash baru & warisan)
$hash = (string)($user['password'] ?? '');
$ok   = false;

if ($hash !== '' && (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2id$'))) {
  // bcrypt / argon2id
  $ok = password_verify($password, $hash);
} elseif (strlen($hash) === 32 && ctype_xdigit($hash)) {
  // md5 legacy
  $ok = hash_equals($hash, md5($password));
} elseif (strlen($hash) === 40 && ctype_xdigit($hash)) {
  // sha1 legacy
  $ok = hash_equals($hash, sha1($password));
} else {
  // plain legacy (tidak disarankan)
  $ok = hash_equals($hash, $password);
}

if (!$ok) {
  flash('err', 'Username atau password salah.');
  redirect('login.php');
}

// (Opsional) Rehash jika masih md5/sha1/plain
// ===== Ambil & validasi ROLE lebih dulu =====
$role_val = strtolower(trim((string)($user['role'] ?? '')));
$allowed_roles = ['admin','ta','tech'];

if ($role_val === '' || !in_array($role_val, $allowed_roles, true)) {
  // kirim plain text saja — TANPA <a>
  $msg  = 'Menunggu hak akses dari admin. ';
  $msg .= 'Silakan Chat Admin ';
  $msg .= 'untuk segera memberikan akses untuk akun <b>efidi</b> Anda.';

  // jaga-jaga: kalau ada <a> terselip dari tempat lain, buang tag-nya
  $msg = preg_replace('~<a\b[^>]*>(.*?)</a>~is', '$1', $msg);

  flash('err', $msg);
  redirect('login.php');
}

// (Opsional) Rehash jika masih md5/sha1/plain
$needs_rehash = false;
$info = password_get_info($hash);
if ($info['algo'] === 0) $needs_rehash = true;
if ($needs_rehash) {
  try {
    $new = password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
    $pdo->prepare("UPDATE akses_login SET password=? WHERE id_akses=?")
        ->execute([$new, (int)$user['id_akses']]);
  } catch (Throwable $e) { /* ignore */ }
}

// ===== Set session login (SETELAH role valid) =====
session_regenerate_id(true);
$_SESSION['uid']      = (int)$user['id_akses'];
$_SESSION['username'] = (string)$user['username'];
$_SESSION['nama']     = (string)$user['nama'];
$_SESSION['role']     = $role_val;
$_SESSION['chat_id']     = (string)$user['chat_id'];

$_SESSION['user'] = [
  'id'       => (int)$user['id_akses'],
  'username' => (string)$user['username'],
  'name'     => (string)$user['nama'],   // konsisten: key 'name'
  'role'     => (string)$role_val,
  'chat_id'     => (string)$user['chat_id'],   // konsisten: key 'name'
];

// Bersihkan jejak OTP jika ada
unset($_SESSION['otp_uid'], $_SESSION['otp_pending'], $_SESSION['otp_code']);

// (Opsional) update last_login
try {
  $pdo->prepare("UPDATE akses_login SET last_login = NOW() WHERE id_akses=?")
      ->execute([(int)$user['id_akses']]);
} catch (Throwable $e) { /* ignore */ }

// ===== Redirect per role (pakai $role_val, bukan nilai session lama) =====
// ===== Redirect per role (pakai $role_val, bukan nilai session lama) =====
// ===== Redirect per role (pakai $role_val, bukan nilai session lama) =====
$redirect_by_role = [
  'admin' => 'dashboard_admin.php',
  'ta'    => 'dashboard_ta.php',
  'tech'  => 'search.php',          // tech masuk ke halaman search
];

// Tentukan dashboard tujuan
$dashboard = $redirect_by_role[$role_val] ?? 'dashboard_admin.php';

// Pesan welcome (kalau masih mau dipakai untuk toast / log)
// (Boleh kamu biarkan atau hapus, terserah)
flash('ok', 'Selamat datang, ' . ($_SESSION['nama'] ?: $_SESSION['username']) . '!');

// LANGSUNG ke dashboard, tanpa animasi login
redirect($dashboard);

