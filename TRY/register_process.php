<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php'; // $pdo (PDO)

function redirect_to(string $path){
  header("Location: " . $path);
  exit;
}

// Hanya POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

// ==== CSRF ====
$csrf = $_POST['csrf'] ?? '';
$csrfOK = false;
if (function_exists('csrf_check')) {
  $csrfOK = (bool)csrf_check($csrf);
} elseif (function_exists('csrf_validate')) {
  $csrfOK = (bool)csrf_validate($csrf);
} elseif (function_exists('csrf_verify')) {
  $csrfOK = (bool)csrf_verify($csrf);
} elseif (isset($_SESSION['csrf'])) {
  $csrfOK = hash_equals((string)$_SESSION['csrf'], (string)$csrf);
}
if (!$csrfOK) {
  if (function_exists('flash')) flash('err', 'Sesi berakhir atau token tidak valid. Silakan coba lagi.');
  redirect_to('register.php');
}

// ==== Ambil input ====
$nama     = trim((string)($_POST['nama']     ?? ''));
$username = trim((string)($_POST['username'] ?? ''));
$pass1    = (string)($_POST['password']      ?? '');
$pass2    = (string)($_POST['password2']     ?? '');
$captchaI = trim((string)($_POST['captcha']  ?? ''));

// ==== Validasi dasar ====
$errs = [];
if ($nama === '')                   $errs[] = 'Nama wajib diisi.';
if ($username === '')               $errs[] = 'Username wajib diisi.';
if ($pass1 === '' || $pass2 === '') $errs[] = 'Password & konfirmasi wajib diisi.';
if ($pass1 !== $pass2)              $errs[] = 'Konfirmasi password tidak cocok.';
if (strlen($pass1) < 6)             $errs[] = 'Password minimal 6 karakter.';
if ($captchaI === '')               $errs[] = 'Captcha wajib diisi.';

// ==== Validasi CAPTCHA (strict) ====
$captchaS = (string)($_SESSION['captcha_code'] ?? '');
$ts       = (int)($_SESSION['captcha_ts'] ?? 0);

// One-time use: hapus kode dari session setelah diverifikasi (apapun hasilnya)
unset($_SESSION['captcha_code'], $_SESSION['captcha_ts']);

// TTL 3 menit
if (!$ts || (time() - $ts) > 180) {
  $errs[] = 'Captcha kadaluarsa. Silakan refresh captcha dan coba lagi.';
} else {
  // perbandingan konstan-waktu
  if (!hash_equals($captchaS, $captchaI)) {
    $errs[] = 'Kode captcha salah.';
  }
}

if ($errs) {
  if (function_exists('flash')) flash('err', implode(' ', $errs));
  redirect_to('register.php');
}

// ==== Cek duplikasi username ====
try {
  $st = $pdo->prepare("SELECT id_akses FROM akses_login WHERE username = ? LIMIT 1");
  $st->execute([$username]);
  if ($st->fetch()) {
    if (function_exists('flash')) flash('err', 'Username sudah digunakan. Silakan pilih yang lain.');
    redirect_to('register.php');
  }
} catch (Throwable $e) {
  if (function_exists('flash')) flash('err', 'Gagal memeriksa username: ' . $e->getMessage());
  redirect_to('register.php');
}

// ==== Insert user: status PENDING, done='no' (verifikasi tetap NULL) ====
try {
  $hash = password_hash($pass1, PASSWORD_DEFAULT);

  // Catatan: kolom 'verifikasi' sengaja TIDAK diisi (biarkan NULL).
  // Sesuaikan kolom bila skema DB berbeda.
  $ins = $pdo->prepare("
    INSERT INTO akses_login (nama, username, password, status, done)
    VALUES (?,?,?,?,?)
  ");
  $ins->execute([$nama, $username, $hash, 'pending', 'no']);
  $uid = (int)$pdo->lastInsertId();

  // Bawa ke pending.php
  $_SESSION['pending_uname'] = $username;
  $_SESSION['pending_uid']   = $uid;

  if (function_exists('flash')) flash('ok', 'Pendaftaran berhasil. Akun Anda berstatus PENDING — tunggu aktivasi admin.');
  redirect_to('pending.php');
} catch (Throwable $e) {
  if (function_exists('flash')) flash('err', 'Gagal menyimpan akun: ' . $e->getMessage());
  redirect_to('register.php');
}
