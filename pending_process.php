<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';                     // $pdo, flash(), csrf_token(), csrf_verify()
require_once __DIR__ . '/telegram/telegram_config.php'; // BOT_TOKEN

function send_telegram_code_pp(string $chat_id, string $code, string $username): bool {
  if ($chat_id === '' || !defined('BOT_TOKEN') || BOT_TOKEN === '') return false;
  $text = "Kode verifikasi efidi. untuk @{$username}: *{$code}*\n_Jangan bagikan kode ini kepada siapapun_.";
  $url  = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
  $data = ['chat_id'=>$chat_id,'text'=>$text,'parse_mode'=>'Markdown'];

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
  curl_close($ch);

  if ($err) error_log("send_telegram_code_pp error: $err");
  return ($resp !== false) && ($err === '');
}

/* ===== Guards ===== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: pending.php'); exit;
}
if (!csrf_verify($_POST['csrf'] ?? '')) {
  flash('err', 'Sesi berakhir. Silakan coba lagi.');
  header('Location: pending.php'); exit;
}

$uid   = (int)($_SESSION['pending_uid'] ?? 0);
$uname = (string)($_SESSION['pending_uname'] ?? '');
if ($uid <= 0) {
  flash('err', 'Sesi verifikasi tidak ditemukan.');
  header('Location: register.php'); exit;
}

/* Ambil user */
$st = $pdo->prepare("
  SELECT id_akses, username, status, verifikasi, chat_id
  FROM akses_login
  WHERE id_akses=? LIMIT 1
");
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  flash('err', 'Akun tidak ditemukan.');
  header('Location: register.php'); exit;
}

/* Tentukan action (fallback dari field yang ada) */
$action = strtolower(trim((string)($_POST['action'] ?? '')));
if ($action === '') {
  if (isset($_POST['code']) || isset($_POST['kode'])) $action = 'verify';
  elseif (isset($_POST['resend']))                    $action = 'resend';
}

/* ===== Handler ===== */
switch ($action) {
  case 'verify': {
    // Ambil 7 digit dari 'code' (hidden) atau 'kode'
    $raw  = (string)($_POST['code'] ?? $_POST['kode'] ?? '');
    $kode = preg_replace('/\D+/', '', $raw);
    if (!preg_match('/^\d{7}$/', $kode)) {
      flash('err', 'Kode harus 7 digit.');
      header('Location: pending.php'); exit;
    }

    // Cek sama dengan yang di DB
    if ((string)($user['verifikasi'] ?? '') !== $kode) {
      flash('err', 'Kode salah atau sudah kedaluwarsa.');
      header('Location: pending.php'); exit;
    }

    // === Verifikasi BERHASIL ===
    // Catatan: verifikasi TETAP DISIMPAN (tidak di-NULL)
    $u = $pdo->prepare("UPDATE akses_login
                        SET status='active', done='yes'
                        WHERE id_akses=?");
    $u->execute([$uid]);

    // Bersihkan sesi pending agar tidak kembali ke pending.php
    unset($_SESSION['pending_uid'], $_SESSION['pending_uname']);

    flash('ok', 'Verifikasi berhasil. Akun Anda sudah aktif.');
    header('Location: login.php');  // ke form login
    exit;
  }
  
  case 'resend': {
    // Generate 7 digit (boleh leading zero)
    $new = str_pad((string)random_int(0, 9_999_999), 7, '0', STR_PAD_LEFT);

    // Simpan kode baru ke kolom verifikasi (overwrite)
    $u = $pdo->prepare("UPDATE akses_login SET verifikasi=? WHERE id_akses=?");
    $u->execute([$new, $uid]);

    if (!empty($user['chat_id'])) { 
      $toUser = (string)($user['username'] ?? $uname);
      send_telegram_code_pp((string)$user['chat_id'], $new, $toUser);
    }

    flash('ok', 'Kode baru telah dikirimkan ke Telegram.');
    header('Location: pending.php'); exit;
  }
}

flash('err', 'Aksi tidak dikenal.');
header('Location: pending.php'); exit;
