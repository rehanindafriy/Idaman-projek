<?php
/**
 * Webhook Telegram (PHP 7.x compatible) — anti duplikat username, tulis ke pending_tg & akses_login.
 * - GET = "OK" (health)
 * - POST = proses update
 * - Secret via ?s=... ATAU header X-Telegram-Bot-Api-Secret-Token
 * - Log: telegram/runtime-telegram-webhook.log
 */
declare(strict_types=1);

require_once __DIR__.'/../koneksi.php';   // kalau koneksi.php di folder atas
require_once __DIR__.'/../auth.php';      // kalau perlu

// Pastikan $pdo terisi tanpa meresetnya
$ROOT = dirname(__DIR__);

// Jika belum ada, coba include sekali (pakai require, bukan require_once)
if (!isset($pdo) || !($pdo instanceof PDO)) {
  foreach ([$ROOT.'/koneksi.php', __DIR__.'/../koneksi.php', __DIR__.'/koneksi.php'] as $p) {
    if (is_file($p)) { require $p; break; }
  }
}

// Jika koneksi expose fungsi db(), manfaatkan
if (function_exists('db') && !($pdo instanceof PDO)) {
  $pdo = db() ?: $pdo;
}

// Jangan exit kalau DB belum siap; cukup log peringatan
if (!($pdo instanceof PDO)) {
  error_log('[WEBHOOK-WARN] DB not ready; continuing without DB');
}




/* ========== HARD LOGGING KE FILE ========== */
$__log = __DIR__.'/runtime-telegram-webhook.log';
ini_set('display_errors','0');        // jangan tampilkan
ini_set('log_errors','1');            // nyalakan logging
@ini_set('error_log', $__log);        // coba arahkan (kalau FPM izinkan)

function logx(string $m): void {
  @file_put_contents(__DIR__.'/runtime-telegram-webhook.log',
    date('c').' '.$m."\n", FILE_APPEND);
}

/* Tangkap semua WARNING/NOTICE ke file */
set_error_handler(function($no,$str,$file,$line){
  logx("PHP[$no] $str @ $file:$line");
  return true; // kita yang handle, jadi jangan log default
});

/* Tangkap fatal saat shutdown */
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e) logx("FATAL[{$e['type']}] {$e['message']} @ {$e['file']}:{$e['line']}");
});

@file_put_contents(__DIR__.'/runtime-telegram-webhook.log', date('c')." HIT ".$_SERVER['REQUEST_METHOD']." QS=".(@$_SERVER['QUERY_STRING'])."\n", FILE_APPEND);


/* ====== CONFIG ====== */
try {
  require_once __DIR__.'/telegram_config.php'; // harus define('BOT_TOKEN', ...)
  if (!defined('BOT_TOKEN') || BOT_TOKEN==='') soft_fail('BOT_TOKEN missing');
} catch (Throwable $e) {
   error_log('[WEBHOOK-ERR] Load config failed: '.$e->getMessage());
  header('Content-Type: text/plain; charset=utf-8'); echo "OK"; exit;
}
$API = 'https://api.telegram.org/bot'.BOT_TOKEN.'/';

/* ====== UI CONSTS ====== */
if (!defined('BTN_LANJUT')) define('BTN_LANJUT', 'LANJUT');
if (!defined('BTN_BACK_TXT')) define('BTN_BACK_TXT', 'KEMBALI KE HALAMAN WEBSITE');
if (!defined('BACK_URL'))     define('BACK_URL', 'https://efidi.montaklo.id/qrcode.php');
/* ====== CRON CONFIG & HELPERS (BARU) ====== */



/* ====== HEALTHCHECK (GET) ====== */
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
  echo 'OK';
  exit;
}

/* ====== RESPON HELPERS ====== */
function ok_exit($extra = []) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array('ok'=>true) + (array)$extra);
  exit;
}
function soft_fail($msg) {
  error_log('[WEBHOOK-ERR] '.$msg);
  ok_exit(); // tetap 200 supaya Telegram tidak banjir retry
}

/* ====== HEALTHCHECK ====== */

/* ====== SECRET CHECK ====== */
/* ====== SECRET CHECK (MODE FLEKSIBEL) ====== */
/**
 * Logika:
 * - Kalau request BAWA query `s`, dan WEBHOOK_SECRET ter-set:
 *     -> WAJIB cocok, kalau tidak => Forbidden.
 * - Kalau request BAWA header X-Telegram-Bot-Api-Secret-Token, dan WEBHOOK_SECRET_HEADER ter-set:
 *     -> WAJIB cocok, kalau tidak => Forbidden.
 * - Kalau TIDAK bawa query `s` DAN TIDAK bawa header:
 *     -> TIDAK dicek apa-apa (backward compatible, aman buat OTP lama).
 */

$hasQuerySecret  = isset($_GET['s']);
$hasHeaderSecret = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']);

if ($hasQuerySecret && defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '') {
  $qs = (string)($_GET['s'] ?? '');
  if (!hash_equals($qs, (string)WEBHOOK_SECRET)) {
    soft_fail('Forbidden: secret mismatch (query)');
  }
}

if ($hasHeaderSecret && defined('WEBHOOK_SECRET_HEADER') && WEBHOOK_SECRET_HEADER !== '') {
  $hdr = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
  if (!hash_equals($hdr, (string)WEBHOOK_SECRET_HEADER)) {
    soft_fail('Forbidden: secret mismatch (header)');
  }
}

// Jika tidak ada query s dan tidak ada header secret -> langsung lanjut tanpa cek.


/* ====== LOAD PDO ====== */


/* ====== BUAT TABEL ====== */
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS pending_tg (
    token VARCHAR(64) PRIMARY KEY,
    username VARCHAR(64) NULL,
    chat_id VARCHAR(32) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
} catch (Throwable $e) {
  error_log('[DB] Create pending_tg failed: '.$e->getMessage());
}

/* ====== HTTP KE TELEGRAM ====== */
function tg_request($method, $data = array()) {
  global $API;
  if (function_exists('curl_init')) {
    $ch = curl_init($API.$method);
    curl_setopt_array($ch, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $data,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 30,
    ));
    $resp = curl_exec($ch);
    $err  = ($resp===false) ? (curl_error($ch) ?: 'curl_exec false') : '';
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return array($resp, $err, $code);
  }
  $opts = array('http'=>array(
    'method'=>'POST',
    'header'=>"Content-Type: application/x-www-form-urlencoded\r\n",
    'content'=>http_build_query($data),
    'timeout'=>30,
  ));
  $ctx  = stream_context_create($opts);
  $resp = @file_get_contents($API.$method, false, $ctx);
  $err  = ($resp===false) ? 'file_get_contents failed' : '';
  $code = 200;
  return array($resp, $err, $code);
}
function tg_send_message($chat_id, $text, $extra = array()) {
  $payload = array_merge(array('chat_id'=>strval($chat_id), 'text'=>$text), (array)$extra);
  tg_request('sendMessage', $payload);
}

/* ====== DB HELPERS ====== */
function safe_str($v) { return (string)$v; }
function find_username_row($pdo, $username) {
  try {
    $st = $pdo->prepare("SELECT username, chat_id FROM akses_login WHERE username=? LIMIT 1");
    $st->execute(array($username));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
  } catch (Throwable $e) {
    error_log('[DB-ERR] find_username_row: '.$e->getMessage());
    return null;
  }
}
function upsert_pending_tg($pdo, $token, $username, $chat_id) {
  try {
    $st = $pdo->prepare(
      "INSERT INTO pending_tg (token, username, chat_id, created_at)
       VALUES (?, ?, ?, CURRENT_TIMESTAMP)
       ON DUPLICATE KEY UPDATE username=VALUES(username), chat_id=VALUES(chat_id), created_at=CURRENT_TIMESTAMP"
    );
    return $st->execute(array($token, $username, safe_str($chat_id)));
  } catch (Throwable $e) {
    error_log('[DB-ERR] pending_tg: '.$e->getMessage());
    return false;
  }
}
function upsert_akses_login($pdo, $username, $chat_id) {
  if ($username==='') return 'skip-empty';
  try {
    $u = $pdo->prepare("
      UPDATE akses_login
         SET chat_id = ?,
             status  = CASE WHEN (status IS NULL OR status='') THEN 'pending' ELSE status END,
             done    = CASE WHEN (done   IS NULL OR done  ='') THEN 'no'      ELSE done   END
       WHERE username = ?
    ");
    $u->execute(array(safe_str($chat_id), $username));
    if ($u->rowCount()>0) return 'update';
    $i = $pdo->prepare("INSERT INTO akses_login (username, chat_id, status, done) VALUES (?, ?, 'pending', 'no')");
    $i->execute(array($username, safe_str($chat_id)));
    return 'insert';
  } catch (Throwable $e) {
    error_log('[DB-ERR] akses_login: '.$e->getMessage());
    return 'err';
  }
}

/* ====== BACA UPDATE ====== */
/* ====== BACA UPDATE ====== */
$raw = @file_get_contents('php://input') ?: '';
if ($raw !== '') {
  @file_put_contents(__DIR__.'/runtime-telegram-webhook.log', date('c')." RAW ".substr($raw,0,2048)."\n", FILE_APPEND);
}
$upd = json_decode($raw, true);
if (!is_array($upd)) ok_exit();

/* ====== PROSES ====== */
try {
  $msg = $upd['message'] ?? ($upd['callback_query']['message'] ?? null);
  if (!$msg) ok_exit();

  $chat     = $msg['chat'] ?? [];
  $ctype    = (string)($chat['type'] ?? '');
  $chat_id  = (string)($chat['id'] ?? '');
  $from     = $msg['from'] ?? [];
  $fname    = trim((string)($from['first_name'] ?? ''));
  $uname    = trim((string)($from['username'] ?? ''));
  $text     = trim((string)($msg['text'] ?? ''));

  if ($chat_id === '') ok_exit();

  /* ====== /start (dengan atau tanpa payload) ====== */
  if (preg_match('~^/start(?:\s+(.+))?$~i', $text, $m)) {
    $payload = isset($m[1]) ? (string)$m[1] : '';

    // Mode deep link dari website: /start link-<TOKEN>
    if (stripos($payload, 'link-') === 0) {
      $token = substr($payload, 5);

      if ($uname !== '') {
        // anti-duplikat username
        $existing = find_username_row($pdo, $uname);
        if ($existing) {
          $existing_chat = safe_str($existing['chat_id'] ?? '');
          $curr_chat     = safe_str($chat_id);
          if ($existing_chat !== '' && $existing_chat !== $curr_chat) {
            tg_send_message($chat_id,
              "Maaf, username @{$uname} sudah terdaftar di akun Telegram lain.\n".
              "Jika ini milikmu, hubungi admin untuk melepas keterkaitan sebelumnya.");
            ok_exit();
          }
        }

        // simpan pending & tautkan ke akses_login
        upsert_pending_tg($pdo, $token, $uname, $chat_id);
        upsert_akses_login($pdo, $uname, $chat_id);

        // Ajak tekan LANJUT untuk konfirmasi akhir
        $msgTxt = "Hai {$fname}!\n".
                  "Username kamu: @{$uname}.\n\n".
                  "Tap tombol ".BTN_LANJUT." untuk mengirim username dan mengaktifkan akun sebelum registrasi di website.";
        tg_send_message($chat_id, $msgTxt, [
          'reply_markup' => json_encode([
            'keyboard' => [[ ['text'=>BTN_LANJUT] ]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
          ])
        ]);
        ok_exit();
      } else {
        // belum punya username telegram
        tg_send_message($chat_id,
          "Akunmu belum memiliki username Telegram. Atur dulu di Settings → Username, lalu balik ke link website untuk klik /start lagi.");
        ok_exit();
      }
    }

    // /start tanpa payload → dorong user tekan LANJUT
    $msgTxt = "Hai {$fname}!\n".
              "Username kamu: ".($uname !== '' ? '@'.$uname : '(belum ada)').".\n\n".
              "Tap tombol ".BTN_LANJUT." untuk mengirim username dan aktivasi akun sebelum registrasi di website.";
    tg_send_message($chat_id, $msgTxt, [
      'reply_markup' => json_encode([
        'keyboard' => [[ ['text'=>BTN_LANJUT] ]],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
      ])
    ]);
    ok_exit();
  }

  /* ====== TOMBOL LANJUT ====== */
  if (strcasecmp($text, BTN_LANJUT) === 0) {
    if ($uname === '') {
      tg_send_message($chat_id,
        "Akunmu belum memiliki username Telegram. Atur dulu di Settings → Username.",
        [
          'reply_markup' => json_encode([
            'keyboard' => [[ ['text'=>BTN_LANJUT] ]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
          ])
        ]
      );
      ok_exit();
    }

    // Cek duplikat username → tolak jika dipakai chat lain
    $existing = find_username_row($pdo, $uname);
    $exChat   = safe_str(is_array($existing) ? ($existing['chat_id'] ?? '') : '');
    if ($existing && $exChat !== '' && $exChat !== safe_str($chat_id)) {
      tg_send_message($chat_id,
        "Maaf, username @{$uname} sudah dipakai akun lain. Hubungi admin jika ini milikmu.", [
          'reply_markup' => json_encode([
            'keyboard' => [[ ['text'=>BTN_LANJUT] ]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
          ])
        ]);
      ok_exit();
    }

    // Username OK → tampilkan inline URL button untuk kembali ke website
    $inlineKb = [ 'inline_keyboard' => [
      [ ['text'=>BTN_BACK_TXT, 'url'=>BACK_URL] ]
    ]];

    tg_send_message($chat_id,
      "Oke, {$fname}. Username @{$uname} sudah tercatat.\nTap tombol di bawah untuk kembali ke website.",
      [
        'disable_web_page_preview' => true,
        'reply_markup' => json_encode($inlineKb)
      ]
    );

    // Catatan: reply keyboard LANJUT sudah one_time_keyboard=true → otomatis sembunyi.
    ok_exit();
  }

  /* ====== /whoami (debug) ====== */
  if ($text === '/whoami') {
    tg_send_message($chat_id, "username: ".($uname ? '@'.$uname : '(none)')."\nchat_id: {$chat_id}\nchat_type: {$ctype}");
    ok_exit();
  }

  ok_exit();
} catch (Throwable $e) {
  soft_fail('Process failed: '.$e->getMessage());
}
