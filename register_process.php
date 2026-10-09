<?php
/**
 * register_process.php (FIX)
 * – Form fields: nama, username, no_tele, password, password2, captcha, csrf
 * – Syarat: username sudah ada di tabel akses_login (dibuat dari bot)
 * – Aksi: update nama, no_tele, password (hash), verifikasi=7 digit, status='pending', done='no'
 * – Kirim OTP ke chat_id jika ada
 * – Redirect ke pending.php + flash notifikasi
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php'; // $pdo, flash(), csrf_verify()


/* ====== LOG TERPUSAT ====== */
$__otp_log = __DIR__ . '/telegram/runtime-telegram-otp.log';
if (!is_dir(__DIR__ . '/telegram')) @mkdir(__DIR__ . '/telegram', 0775, true);

function otp_log(string $msg, array $ctx = []): void {
  $line = date('c').' '.$msg.($ctx ? ' '.json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '');
  @file_put_contents($GLOBALS['__otp_log'], $line.PHP_EOL, FILE_APPEND);
}

/** Kirim kode ke Telegram */
function send_telegram_code(string $chat_id, string $code, string $username): bool {
  $token = defined('BOT_TOKEN') ? BOT_TOKEN
         : (defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '');
  $chat_id = trim((string)$chat_id);

  if ($chat_id === '' || $token === '') { otp_log('ABORT: token/chat_id kosong'); return false; }
  if (!preg_match('/^-?\d+$/', $chat_id)) { otp_log('ABORT: chat_id non-numeric', ['chat_id'=>$chat_id]); return false; }

  $u = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
  $c = htmlspecialchars($code,     ENT_QUOTES, 'UTF-8');

  // mention klik-able ke id user
  $mention = $u !== '' ? ' untuk <a href="tg://user?id='.rawurlencode($chat_id).'">@'.$u.'</a>' : '';
  $text    = 'Kode verifikasi efidi'.$mention.': <b>'.$c.'</b>'."\n".'<i>Jangan bagikan kode ini kepada siapapun.</i>';

  $url  = "https://api.telegram.org/bot{$token}/sendMessage";
  $data = ['chat_id'=>$chat_id,'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);

  otp_log('REG OTP SEND', ['to'=>$chat_id, 'http'=>$info['http_code'] ?? null]);

  if ($resp === false || $err !== '') { otp_log('CURL ERROR', ['err'=>$err]); return false; }
  $j = json_decode((string)$resp, true);
  if (!is_array($j) || empty($j['ok'])) {
    otp_log('API FAIL', ['desc'=>$j['description'] ?? (string)$resp]);
    return false;
  }
  otp_log('API OK', ['msg_id'=>$j['result']['message_id'] ?? null]);
  return true;
}


/** Ambil nilai captcha dari berbagai kemungkinan key session */
function get_session_captcha(): ?string {
  foreach (['captcha_text','captcha_code','captcha','es_captcha','code'] as $k) {
    if (!empty($_SESSION[$k]) && is_string($_SESSION[$k])) return $_SESSION[$k];
  }
  return null;
}

/** Redirect helper */
function back_with(string $key, string $msg, array $old = []): void {
  if (!empty($old)) $_SESSION['old'] = $old;
  flash($key, $msg);
  header('Location: register.php'); exit;
}

/* ===== Ambil input ===== */
$nama     = trim($_POST['nama'] ?? '');
$username = strtolower(trim($_POST['username'] ?? ''));
$pass     = (string)($_POST['password']  ?? '');
$pass2    = (string)($_POST['password2'] ?? '');
$captcha  = trim((string)($_POST['captcha'] ?? ''));
$csrf     = $_POST['csrf'] ?? null;

/* Simpan old input (kecuali password) */
$old = ['nama' => $nama, 'username' => $username,];

/* ===== Validasi dasar ===== */
if (!csrf_verify($csrf)) {
  back_with('err', 'Sesi kadaluarsa. Silakan muat ulang halaman dan coba lagi.', $old);
}
if ($nama === '' || $username === '' || $pass === '' || $pass2 === '' || $captcha === '') {
  back_with('err', 'Semua kolom wajib diisi.', $old);
}
if (strlen($pass) < 6) {
  back_with('err', 'Password minimal 6 karakter.', $old);
}
if ($pass !== $pass2) {
  back_with('err', 'Konfirmasi password tidak cocok.', $old);
}
/* Validasi captcha (gunakan berbagai kemungkinan key session) */
$sessCaptcha = get_session_captcha();
if (!$sessCaptcha || strcasecmp($sessCaptcha, $captcha) !== 0) {
  back_with('err', 'Kode captcha salah.', $old);
}

/* ===== Pastikan username SUDAH ada di DB dari bot ===== */
$st = $pdo->prepare("SELECT id_akses, username, chat_id, status, role, done
                     FROM akses_login WHERE username = ? LIMIT 1");
$st->execute([$username]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  // Minta user DM bot dulu — tampilkan pesan dekat form (pakai flash umum)
  back_with('err', 'Username tidak tersedia. Silakan DM bot terlebih dahulu agar username terdaftar.', $old);
}

/* ===== Siapkan update ===== */
$uid     = (int)$row['id_akses'];
$chat_id = (string)($row['chat_id'] ?? '');
$otp7    = (string)random_int(1_000_000, 9_999_999); // 7 digit
$hash    = password_hash($pass, PASSWORD_BCRYPT);

$pdo->beginTransaction();
try {
  $u = $pdo->prepare("UPDATE akses_login
                        SET nama = ?, password = ?, verifikasi = ?, status = 'pending', done = 'no'
                      WHERE id_akses = ?");
  $u->execute([$nama, $hash, $otp7, $uid]);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('register_process update fail: '.$e->getMessage());
  back_with('err', 'Registrasi gagal. Coba lagi.', $old);
}

/* ===== Kirim OTP ke Telegram (jika chat_id tersedia) ===== */
if ($chat_id !== '') {
  send_telegram_code($chat_id, $otp7, $username);
}

$uname = ltrim(strtolower(trim($_POST['username'] ?? '')), '@');
$st = $pdo->prepare("SELECT done FROM akses_login WHERE LOWER(username)=? LIMIT 1");
$st->execute([$uname]);
$done = $st->fetchColumn();
if ($done && strtolower((string)$done) === 'yes') {
  flash('err','Username sudah terdaftar (done=yes). Silakan login atau hubungi admin.');
  redirect('register.php'); exit;
}


/* ===== Simpan sesi dan arahkan ke pending ===== */
$_SESSION['pending_uid']   = $uid;
$_SESSION['pending_uname'] = $username;

/* Notif pakai mekanisme pending.php yang sudah ada */
flash('ok', 'Kode verifikasi telah dikirimkan ke Telegram Anda. Masukkan 7 digit kode di bawah ini.');
header('Location: pending.php');
exit;
