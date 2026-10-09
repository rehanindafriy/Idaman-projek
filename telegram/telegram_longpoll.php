<?php
/**
 * Long-poll Telegram: link username ke token & tulis ke DB (ANTI DUPLIKAT USERNAME).
 * - pending_tg: token, username, chat_id (untuk qrcode auto-redirect)
 * - akses_login: username, chat_id, status='pending', done='no'
 *
 * Jalankan dari root project:
 *   php telegram/telegram_longpoll.php
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ====== KONEKSI DB ======
$ROOT = dirname(__DIR__); // .../e-siklo

$pdo = null;
// Proyekmu ada yang pakai koneksi.php, ada yang auth.php. Coba dua-duanya:
if (is_file($ROOT . '/koneksi.php')) require $ROOT . '/koneksi.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (is_file($ROOT . '/auth.php')) require $ROOT . '/auth.php';
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  fwrite(STDERR, "[FATAL] Tidak menemukan \$pdo. Pastikan koneksi.php/auth.php mendefinisikan \$pdo (PDO)\n");
  exit(1);
}

// ====== CONFIG BOT ======
require_once __DIR__ . '/telegram_config.php'; // BOT_TOKEN, BOT_USERNAME
if (!defined('BOT_TOKEN') || BOT_TOKEN === '') {
  fwrite(STDERR, "[FATAL] BOT_TOKEN belum diset di telegram/telegram_config.php\n");
  exit(1);
}
$API = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';

// ====== TABEL ======
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS pending_tg (
    token VARCHAR(64) PRIMARY KEY,
    username VARCHAR(64) NULL,
    chat_id VARCHAR(32) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
  echo "[boot] Table pending_tg OK\n";
} catch (Throwable $e) {
  echo "[WARN] Gagal CREATE TABLE pending_tg: " . $e->getMessage() . "\n";
}

// ====== HTTP HELPER ======
function tg_request(string $method, array $data = []): array {
  global $API;
  $ch = curl_init($API . $method);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  return [$resp, $err, $code];
}
function tg_send_message(int|string $chat_id, string $text, array $extra = []): void {
  $payload = array_merge(['chat_id' => (string)$chat_id, 'text' => $text], $extra);
  tg_request('sendMessage', $payload);
}

// ====== DB HELPERS ======
function safe_str(null|string|int $v): string {
  return (string)$v;
}

function find_username_row(PDO $pdo, string $username): ?array {
  try {
    $st = $pdo->prepare("SELECT username, chat_id FROM akses_login WHERE username=? LIMIT 1");
    $st->execute([$username]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  } catch (Throwable $e) {
    error_log("[DB-ERR] find_username_row: " . $e->getMessage());
    return null;
  }
}

// ====== UPSERT pending_tg ======
function upsert_pending_tg(PDO $pdo, string $token, string $username, int|string $chat_id): bool {
  try {
    $st = $pdo->prepare(
      "INSERT INTO pending_tg (token, username, chat_id, created_at)
       VALUES (?, ?, ?, CURRENT_TIMESTAMP)
       ON DUPLICATE KEY UPDATE username=VALUES(username), chat_id=VALUES(chat_id), created_at=CURRENT_TIMESTAMP"
    );
    return $st->execute([$token, $username, (string)$chat_id]);
  } catch (Throwable $e) {
    error_log("[DB-ERR] pending_tg: " . $e->getMessage());
    echo "[DB-ERR] pending_tg: " . $e->getMessage() . "\n";
    return false;
  }
}

// ====== UPSERT akses_login (dipanggil HANYA kalau lolos cek duplikat) ======
function upsert_akses_login(PDO $pdo, string $username, int|string $chat_id): string {
  if ($username === '') return 'skip-empty';

  try {
    // 1) coba UPDATE dulu (baris existing tanpa mengubah status/done kalau sudah terisi)
    $u = $pdo->prepare("
      UPDATE akses_login
         SET chat_id = ?,
             status  = CASE WHEN (status IS NULL OR status='') THEN 'pending' ELSE status END,
             done    = CASE WHEN (done   IS NULL OR done  ='') THEN 'no'      ELSE done   END
       WHERE username = ?
    ");
    $u->execute([safe_str($chat_id), $username]);
    if ($u->rowCount() > 0) return 'update';

    // 2) kalau belum ada → INSERT minimal
    $i = $pdo->prepare("
      INSERT INTO akses_login (username, chat_id, status, done)
      VALUES (?, ?, 'pending', 'no')
    ");
    $i->execute([$username, safe_str($chat_id)]);
    return 'insert';
  } catch (Throwable $e) {
    error_log("[DB-ERR] akses_login: " . $e->getMessage());
    echo "[DB-ERR] akses_login: " . $e->getMessage() . "\n";
    return 'err';
  }
}

// ====== MATIKAN WEBHOOK ======
[$r,$e,$c] = tg_request('deleteWebhook', ['drop_pending_updates' => true]);
echo "[boot] deleteWebhook code={$c} err=" . ($e ?: '-') . "\n";

// ====== LONG POLLING ======
$offset = 0;
echo "[boot] longpoll start. CTRL+C to stop.\n";

while (true) {
  // tambah allowed_updates agar hanya ambil yang kita butuh
  [$resp, $err, $code] = tg_request('getUpdates', [
    'timeout'          => 25,
    'offset'           => $offset,
    'allowed_updates'  => json_encode(['message','callback_query']),
    'limit'            => 50
  ]);
  if ($err)  { echo "[http-err] {$err}\n"; usleep(300000); continue; }
  if ($code !== 200 || !$resp) { echo "[http-bad] code={$code}\n"; usleep(300000); continue; }

  $data = json_decode($resp, true);
  if (!$data || empty($data['ok'])) { usleep(250000); continue; }

  $updates = $data['result'] ?? [];
  if ($updates) { echo "[tick] updates=" . count($updates) . " offset={$offset}\n"; }

  foreach ($updates as $up) {
    $update_id = (int)($up['update_id'] ?? 0);
    $offset = max($offset, $update_id + 1);

    $msg = $up['message'] ?? ($up['callback_query']['message'] ?? null);
    if (!$msg) continue;

    $chat     = $msg['chat'] ?? [];
    $from     = $msg['from'] ?? [];
    $text     = trim((string)($msg['text'] ?? ''));
    $chat_id  = (int)($chat['id'] ?? 0);   // untuk private chat, chat_id == user id
    $uname    = (string)($from['username'] ?? '');
    $fname    = trim(((string)($from['first_name'] ?? '')) . ' ' . ((string)($from['last_name'] ?? '')));
    $ctype    = (string)($chat['type'] ?? '');

    echo "[update] type={$ctype} raw=@" . ($uname ?: '(EMPTY)') . " id={$chat_id} text=" . ($text ?: '(no-text)') . "\n";

    // Hanya proses private chat
    if ($ctype !== 'private') continue;

    // /start [payload]
    if (preg_match('~^/start(?:\s+(.+))?$~i', $text, $m)) {
      $payload = (string)($m[1] ?? '');

      if (stripos($payload, 'link-') === 0) {
        $token = substr($payload, 5);

        if ($uname !== '') {
          // ====== ANTI DUPLIKAT USERNAME ======
          $existing = find_username_row($pdo, $uname);
          if ($existing) {
            $existing_chat = safe_str($existing['chat_id'] ?? '');
            $curr_chat     = safe_str($chat_id);

            if ($existing_chat !== '' && $existing_chat !== $curr_chat) {
              // Username sudah dipakai user lain -> TOLAK KERAS.
              echo "[akses_login] DUP username=@{$uname} existing_chat={$existing_chat} current_chat={$curr_chat}\n";
              tg_send_message($chat_id,
                "Maaf, username @{$uname} sudah terdaftar untuk akun Telegram lain.\n".
                "Jika ini milikmu, hubungi admin untuk melepas keterkaitan sebelumnya.");
              // JANGAN tulis ke pending_tg maupun akses_login
              continue;
            }

            // Kalau existing & chat_id sama (atau kosong), anggap sudah terhubung.
            // Boleh update pending_tg supaya halaman QR bisa auto-redirect.
            $ok1 = upsert_pending_tg($pdo, $token, $uname, $chat_id);
            echo $ok1
              ? "[pending] OK  (already-linked) token={$token} user=@{$uname} chat_id={$chat_id}\n"
              : "[pending] ERR (already-linked) token={$token} user=@{$uname} chat_id={$chat_id}\n";

            tg_send_message($chat_id,
              "Hai {$fname}!\nUsername @{$uname} sudah terhubung dengan akun ini.\nSilakan kembali ke website.");
            continue;
          }

          // ====== TIDAK ADA DUPLIKAT → lanjut tulis ======
          $ok1 = upsert_pending_tg($pdo, $token, $uname, $chat_id);
          echo $ok1
            ? "[pending] OK  token={$token} user=@{$uname} chat_id={$chat_id}\n"
            : "[pending] ERR token={$token} user=@{$uname} chat_id={$chat_id}\n";

          $res = upsert_akses_login($pdo, $uname, $chat_id);
          echo "[akses_login] {$res} username=@{$uname} chat_id={$chat_id}\n";
        } else {
          echo "[pending] SKIP: user belum set username (Settings → Username)\n";
          tg_send_message($chat_id, "Kamu belum punya username Telegram. Set dulu di Settings → Username, lalu klik /start lagi dari link website.");
        }
      } else {
        echo "[info] /start tanpa payload. Minta user klik link/QR dari website.\n";
      }

      // Kirim keyboard + info (opsional)
      $msgTxt = "Hai {$fname}!\n"
              . "Username kamu: " . ($uname !== '' ? '@' . $uname : '(tidak ada username)') . ".\n\n"
              . "Kamu bisa klik button KIRIM USERNAME TELEGRAM untuk melakukan aktivasi akun sebelum registrasi di website.\n";
      tg_send_message($chat_id, $msgTxt, ['reply_markup' => json_encode([
        'keyboard' => [[['text' => 'KIRIM USERNAME TELEGRAM']]],
        'resize_keyboard' => true, 'one_time_keyboard' => false
      ])]);
      continue;
    }

    // Tombol teks
    if (strcasecmp($text, 'KIRIM USERNAME TELEGRAM') === 0) {
      if ($uname === '') {
        tg_send_message($chat_id, "Akunmu belum memiliki username Telegram. Atur dulu di Settings → Username.");
      } else {
        // Anti duplikat juga saat user tekan tombol
        $existing = find_username_row($pdo, $uname);
        if ($existing && safe_str($existing['chat_id'] ?? '') !== safe_str($chat_id) && safe_str($existing['chat_id'] ?? '') !== '') {
          tg_send_message($chat_id, "Maaf, username @{$uname} sudah dipakai akun lain. Hubungi admin jika ini milikmu.");
        } else {
          tg_send_message($chat_id, "Oke. Username " . '@'.$uname . " sudah tercatat. Silakan kembali ke website.");
        }
      }
      continue;
    }

    // Debug opsional
    if ($text === '/whoami') {
      tg_send_message($chat_id, "username: " . ($uname ? '@'.$uname : '(none)') . "\nchat_id: {$chat_id}\nchat_type: {$ctype}");
      continue;
    }
  }
}
