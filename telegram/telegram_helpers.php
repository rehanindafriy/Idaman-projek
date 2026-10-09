<?php
/**
 * telegram_helpers.php — Telegram request + upsert akses_login (tanpa no_tele)
 */
declare(strict_types=1);

require_once __DIR__ . '/telegram_config.php';

/* Satu-satunya path log untuk semua script */

/** Request ke Telegram API (timeout > poll & paksa IPv4) */
function tg_request(string $method, array $data) {
  $url  = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
  $poll = isset($data['timeout']) && is_numeric($data['timeout']) ? (int)$data['timeout'] : 0;
  $timeout = max(20, $poll + 15);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$resp, $err, $code];
}

/** Kirim pesan teks */
function tg_send_message(int|string $chat_id, string $text, array $opts = []) {
  $payload = array_merge([
    'chat_id'    => $chat_id,
    'text'       => $text,
    'parse_mode' => 'Markdown',
  ], $opts);
  return tg_request('sendMessage', $payload);
}

/** Bentuk username final: gunakan alias tg_<user_id> jika kosong & diizinkan */
function materialize_username(?string $username, int $user_id): ?string {
  $u = trim((string)($username ?? ''));
  if ($u !== '') return $u;
  if (defined('ALLOW_UID_ALIAS') && ALLOW_UID_ALIAS && $user_id > 0) return 'tg_' . $user_id;
  return null;
}

/**
 * Upsert ke tabel akses_login (TANPA no_tele):
 * - Jika username ada → update chat_id
 * - Jika belum ada → insert minimal (status 'pending', done 'no')
 * Return: true jika sukses, false jika error (log saat DEBUG_LOG=true)
 */
function upsert_akses_login(PDO $pdo, ?string $username, int|string $chat_id): bool {
  if (!$username) return false;
  $username = trim($username);

  try {
    // pastikan throw exception
    if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id_akses FROM akses_login WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      $u = $pdo->prepare("UPDATE akses_login SET chat_id = ? WHERE id_akses = ?");
      $u->execute([ (string)$chat_id, $row['id_akses'] ]);
    } else {
      $ins = $pdo->prepare("
        INSERT INTO akses_login (nama, username, password, role, status, chat_id, verifikasi, done)
        VALUES (NULL, ?, NULL, NULL, 'pending', ?, NULL, 'no')
      ");
      $ins->execute([ $username, (string)$chat_id ]);
    }

    $pdo->commit();
    return true;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch(Throwable $e2) {} }
    if (defined('DEBUG_LOG') && DEBUG_LOG) {
      error_log('[DB-ERR] upsert_akses_login: '.$e->getMessage());
      echo "[DB-ERR] upsert_akses_login: ".$e->getMessage().PHP_EOL;
    }
    return false;
  }
}
