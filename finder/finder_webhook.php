<?php
/**
 * finder_webhook.php
 *
 * Webhook BOT FINDER (bot searching EFIDI):
 * - /start di private  : cek akses_login / akses_bot, kirim tombol sesuai status
 * - /start di group    : kirim tombol FINDER SEARCH (deep-link ke bot /start di private)
 * - callback "finder:search" : fallback lama -> sekarang juga diarahkan ke bot via deeplink
 *
 * Konsep:
 * 1) Pendaftaran awal:
 *    - akses_login.active          -> akses_bot.active + tombol Buka Finder Search
 *    - akses_login.pending/dll     -> akses_bot.suspended + tombol Chat Admin
 *    - tidak ada di akses_login    -> akses_bot.suspended + tombol Chat Admin
 *
 * 2) Penggunaan (SUDAH terdaftar di akses_bot):
 *    - Semua akses BOT diatur ONLY oleh status di akses_bot (akses_login diabaikan).
 *    - akses_bot.active    -> tombol Buka Finder Search (mode ACTIVE, langsung ke search_bot.php).
 *    - akses_bot.suspended -> tombol Buka Finder Search (mode CEK AKSES, lewat search_gate.php).
 */

declare(strict_types=1);

// ========== 1) LOAD DB & TELEGRAM CONFIG ==========

$ROOT = dirname(__DIR__);

// koneksi PDO
require_once $ROOT . '/koneksi.php';

// cari telegram_config.php (dipakai juga announc__personal, dll)
$cfgCandidates = [
  $ROOT . '/telegram/telegram_config.php', // kalau file di /telegram
  $ROOT . '/telegram_config.php',          // fallback lain
  __DIR__ . '/telegram_config.php',
];
foreach ($cfgCandidates as $p) {
  if (is_file($p)) {
    require_once $p;
    break;
  }
}

// ===== KONFIGURASI KHUSUS FINDER BOT =====

// Token bot finder (lebih bagus pakai bot baru dari BotFather)
if (!defined('BOT_TOKEN_FINDER')) {
  // default pakai BOT_TOKEN lama kalau belum didefinisikan
  define('BOT_TOKEN_FINDER', BOT_TOKEN);
}

// username bot finder (TANPA @)
if (!defined('FINDER_BOT_USERNAME')) {
  define('FINDER_BOT_USERNAME', 'searchingefidi_bot'); // ganti kalau perlu
}

// URL halaman CHAT ADMIN (Web App)
if (!defined('FINDER_CHAT_ADMIN_URL')) {
  define('FINDER_CHAT_ADMIN_URL', 'https://efidi.montaklo.id/chat_admin.php');
}

// URL gateway search khusus Finder Bot (cek akses_bot dulu)
if (!defined('FINDER_SEARCH_URL')) {
  define('FINDER_SEARCH_URL', 'https://efidi.montaklo.id/finder/search_gate.php');
}

// URL langsung ke search_bot.php (dipakai ketika status BOT = active)
if (!defined('FINDER_SEARCH_DIRECT_URL')) {
  define('FINDER_SEARCH_DIRECT_URL', 'https://efidi.montaklo.id/search_bot.php');
}

// URL landing Android App (deep link ke efidi Search Android)
if (!defined('FINDER_ANDROID_LANDING_URL')) {
  define('FINDER_ANDROID_LANDING_URL', 'https://efidi.montaklo.id/open_app_landing.php');
}


// URL processed untuk flow lama GROUP → PROCESSED → SEARCH (disisakan untuk kompatibilitas)
if (!defined('FINDER_PROCESSED_URL')) {
  define('FINDER_PROCESSED_URL', 'https://efidi.montaklo.id/finder/processed.php');
}

// WebApp efidi.ai (dipanggil dari tombol inline di private)
if (!defined('EFIDI_AI_URL')) {
  define('EFIDI_AI_URL', 'https://efidi.montaklo.id/efidi_ai.php');
}

// API endpoint untuk bot finder
$API = 'https://api.telegram.org/bot' . BOT_TOKEN_FINDER . '/';

// file log ringan
function f_log(string $msg): void {
  @file_put_contents(
    __DIR__ . '/runtime-finder.log',
    date('c') . ' ' . $msg . "\n",
    FILE_APPEND
  );
}

// ========== 2) TELEGRAM HELPER ==========

function tg_request(string $method, array $data = []): array {
  global $API;

  if (function_exists('curl_init')) {
    $ch = curl_init($API . $method);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $data,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $err  = ($resp === false) ? (curl_error($ch) ?: 'curl_exec false') : '';
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
  } else {
    $opts = [
      'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
        'timeout' => 30,
      ],
    ];
    $ctx  = stream_context_create($opts);
    $resp = @file_get_contents($API . $method, false, $ctx);
    $err  = ($resp === false) ? 'file_get_contents failed' : '';
    $code = 200;
  }

  if ($err || $code >= 400) {
    f_log("[TG] {$method} error code={$code} err={$err} resp=" . substr((string)$resp, 0, 200));
  }

  $decoded = json_decode((string)$resp, true);
  return $decoded ?: [];
}

function tg_send_message($chatId, string $text, array $extra = []): void {
  $payload = array_merge([
    'chat_id'    => (string)$chatId,
    'text'       => $text,
    'parse_mode' => 'HTML',
  ], $extra);

  tg_request('sendMessage', $payload);
}

function tg_answer_callback(string $cbId, string $text = '', bool $alert = false): void {
  $data = [
    'callback_query_id' => $cbId,
    'show_alert'        => $alert ? true : false,
  ];
  if ($text !== '') {
    $data['text'] = $text;
  }
  tg_request('answerCallbackQuery', $data);
}

// normalisasi username (pakai alias tg_<id> kalau ALLOW_UID_ALIAS = true)
function normalize_username(array $from): array {
  $uid  = (int)($from['id'] ?? 0);
  $user = trim((string)($from['username'] ?? ''));

  if ($user === '' && defined('ALLOW_UID_ALIAS') && ALLOW_UID_ALIAS) {
    $user = 'tg_' . $uid;
  }

  return [$uid, $user];
}

// ========== 3) DB HELPER UNTUK AKSES ==========

/**
 * Cari user di akses_login berdasarkan username (case-insensitive).
 */
function find_akses_login(PDO $pdo, string $username): ?array {
  if ($username === '') return null;

  $sql = "SELECT * FROM akses_login
          WHERE LOWER(username) = LOWER(?)
          LIMIT 1";
  $st  = $pdo->prepare($sql);
  $st->execute([$username]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/**
 * Cari user di akses_bot berdasarkan username (case-insensitive).
 */
function find_akses_bot(PDO $pdo, string $username): ?array {
  if ($username === '') return null;

  $sql = "SELECT * FROM akses_bot
          WHERE LOWER(username) = LOWER(?)
          LIMIT 1";
  $st  = $pdo->prepare($sql);
  $st->execute([$username]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/**
 * Upsert ke akses_bot:
 * - Jika BELUM ada: insert dengan status default ($defaultStatus).
 * - Jika SUDAH ada: hanya update chat_id, TIDAK mengubah status (admin atur dari akses_bot.php),
 *   kecuali kalau status di DB masih kosong -> isi dengan default.
 * Fungsi ini mengembalikan status akhir di DB (lowercase).
 */
function upsert_akses_bot(PDO $pdo, string $username, int $chatId, string $defaultStatus = 'suspended'): string {
  if ($username === '' || $chatId === 0) {
    return strtolower($defaultStatus);
  }

  $sql = "SELECT id_akses_bot, chat_id, status
          FROM akses_bot
          WHERE LOWER(username) = LOWER(?)
          LIMIT 1";
  $st  = $pdo->prepare($sql);
  $st->execute([$username]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $id      = (int)$row['id_akses_bot'];
    $curChat = (int)($row['chat_id'] ?? 0);
    $status  = strtolower((string)($row['status'] ?? ''));

    // update chat_id kalau berubah
    if ($curChat !== $chatId) {
      $u = $pdo->prepare("UPDATE akses_bot SET chat_id = ? WHERE id_akses_bot = ?");
      $u->execute([$chatId, $id]);
    }

    // kalau status kosong di DB, isi dengan default
    if ($status === '') {
      $status = strtolower($defaultStatus);
      $u = $pdo->prepare("UPDATE akses_bot SET status = ? WHERE id_akses_bot = ?");
      $u->execute([$status, $id]);
    }

    return $status; // status dari DB
  }

  // belum ada row → insert baru
  $status = strtolower($defaultStatus);
  $i = $pdo->prepare("
    INSERT INTO akses_bot (username, chat_id, status)
    VALUES (?, ?, ?)
  ");
  $i->execute([$username, $chatId, $status]);

  return $status;
}

/**
/**
 * Bangun tombol "Buka Finder Search" untuk PRIVATE CHAT.
 *
 *  - statusBot = 'active'
 *      • 🔎 Searching data        → URL ke open_app_landing.php (FINDER_ANDROID_LANDING_URL)
 *      • 🌐 efidi.ai              → WebApp ke EFIDI_AI_URL
 *
 *  - statusBot != 'active' (suspended / lainnya)
 *      • 🔐 Searching data (Check) → WebApp ke search_gate.php (FINDER_SEARCH_URL)
 */
function build_search_keyboard(string $username, int $chatId, string $statusBot = 'active'): array {
  $statusBot = strtolower($statusBot);

  // query string yang dikirim ke URL / WebApp
  $qs = http_build_query([
    'u'   => $username,
    'cid' => $chatId,
  ]);

  $rows = [];

  if ($statusBot === 'active') {
    // =========================
    //  ACTIVE → BUTTON URL ke open_app_landing.php
    // =========================
    $base = (defined('FINDER_ANDROID_LANDING_URL') && FINDER_ANDROID_LANDING_URL)
      ? FINDER_ANDROID_LANDING_URL                  // open_app_landing.php
      : FINDER_SEARCH_URL;                          // fallback lama (search_gate.php)

    $urlUnified = rtrim($base, '?') . '?' . $qs;

    // Baris 1: Searching data (URL biasa, bukan WebApp)
    $rows[] = [
      [
        'text' => '🔎 Searching data',
        'url'  => $urlUnified,
      ],
    ];

    // Baris 2: efidi.ai (WebApp) — hanya ACTIVE
    if (defined('EFIDI_AI_URL') && EFIDI_AI_URL !== '') {
      $urlAi = rtrim(EFIDI_AI_URL, '?') . '?' . $qs;

      $rows[] = [
        [
          'text'    => '🌐 efidi.ai',
          'web_app' => ['url' => $urlAi],
        ],
      ];
    }
  } else {
    // =========================
    //  BUKAN ACTIVE (misal: SUSPENDED)
    //  → Searching data (Check) via WebApp ke search_gate.php
    // =========================
    $baseGate = (defined('FINDER_SEARCH_URL') && FINDER_SEARCH_URL)
      ? FINDER_SEARCH_URL                            // finder/search_gate.php
      : (defined('FINDER_SEARCH_DIRECT_URL') ? FINDER_SEARCH_DIRECT_URL : '');

    if ($baseGate !== '') {
      $urlGate = rtrim($baseGate, '?') . '?' . $qs;

      $rows[] = [
        [
          'text'    => '🔐 Searching data (Check)',
          'web_app' => ['url' => $urlGate],
        ],
      ];
    }
    // Tidak ada efidi.ai kalau status != active
  }

  return $rows;
}

/**
 * Tombol CHAT_ADMIN (APP MINI).
 * Diarahkan ke chat_admin.php sebagai Web App.
 */
function build_chat_admin_keyboard(string $username, int $chatId): array {
  $url = FINDER_CHAT_ADMIN_URL . '?' . http_build_query([
    'u'   => $username,
    'cid' => $chatId,
    'src' => 'finder_bot',
  ]);

  return [
    [
      [
        'text'    => '💬 Chat Admin efidi.',
        'web_app' => ['url' => $url],
      ],
    ],
  ];
}

/**
 * Bangun deep-link ke bot Finder di t.me:
 * - https://t.me/<FINDER_BOT_USERNAME>?start=<payload>
 */
function build_finder_deeplink(string $payload = 'finder_group'): string {
  $botUser = defined('FINDER_BOT_USERNAME') ? (string)FINDER_BOT_USERNAME : '';
  if ($botUser === '' && defined('BOT_USERNAME')) {
    $botUser = (string)BOT_USERNAME;
  }
  $botUser = ltrim($botUser, '@');

  if ($botUser === '') {
    // fallback generik
    return 'https://t.me/';
  }

  $url = 'https://t.me/' . rawurlencode($botUser);
  if ($payload !== '') {
    $url .= '?start=' . rawurlencode($payload);
  }
  return $url;
}

/**
 * Kirim pesan Finder + keyboard, sambil memastikan
 * TIDAK ADA duplikasi tombol "Buka Finder Search".
 */
/**
 * Kirim pesan Finder + keyboard, sambil memastikan
 * TIDAK ADA duplikasi tombol "Buka Finder Search".
 */
function send_finder_message_dedup(PDO $pdo, string $username, int $chatId, string $text, array $inlineKeyboard): void {
  if ($chatId === 0 || $username === '') {
    return;
  }

  // 1) ambil message_id lama dari akses_bot
  $st = $pdo->prepare("
    SELECT finder_msg_id
    FROM akses_bot
    WHERE LOWER(username) = LOWER(?)
    LIMIT 1
  ");
  $st->execute([$username]);
  $prevMsgId = (int)($st->fetchColumn() ?: 0);

  // 2) kosongkan keyboard lama (kalau ada)
  if ($prevMsgId > 0) {
    tg_request('editMessageReplyMarkup', [
      'chat_id'      => (string)$chatId,
      'message_id'   => $prevMsgId,
      'reply_markup' => json_encode(['inline_keyboard' => []]),
    ]);
  }

  // 3) kirim pesan baru
  $payload = [
    'chat_id'                  => (string)$chatId,
    'text'                     => $text,
    'parse_mode'               => 'HTML',
    'disable_web_page_preview' => true,
  ];
  if (!empty($inlineKeyboard)) {
    $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
  }

  // tg_request sekarang DIANGGAP mengembalikan array JSON decode Telegram
  $resp = tg_request('sendMessage', $payload);

  if (is_array($resp) && !empty($resp['ok']) && !empty($resp['result']['message_id'])) {
    $newMsgId = (int)$resp['result']['message_id'];

    $up = $pdo->prepare("
      UPDATE akses_bot
      SET finder_msg_id = ?
      WHERE LOWER(username) = LOWER(?)
    ");
    $up->execute([$newMsgId, $username]);
  }
}

// ========== 4) LOGIKA /start PRIVATE & GROUP ==========

/**
 * /start di PRIVATE CHAT.
 */
function handle_start_private(PDO $pdo, array $message): void {
  $chat = $message['chat'] ?? [];
  $from = $message['from'] ?? $chat;

  $chatId = (int)($chat['id'] ?? 0);
  [$uid, $username] = normalize_username($from);

  if ($chatId === 0) return;

  if ($username === '') {
    tg_send_message($chatId,
      "⚠️ <b>Username Telegram kamu belum ada.</b>\n\n" .
      "Untuk bisa memakai Finder efidi, silakan set <b>username</b> di pengaturan Telegram, " .
      "atau aktifkan opsi alias (ALLOW_UID_ALIAS) di server."
    );
    return;
  }

  // ========= MODE PENGGUNAAN (SUDAH TERDAFTAR DI akses_bot) =========
  $rowBot = find_akses_bot($pdo, $username);
  if ($rowBot) {
    $idBot     = (int)$rowBot['id_akses_bot'];
    $oldChatId = (int)($rowBot['chat_id'] ?? 0);
    $botStatus = strtolower(trim((string)($rowBot['status'] ?? '')));

    if ($botStatus === '') {
      $botStatus = 'suspended';
    }

    // sinkron chat_id di akses_bot
    if ($oldChatId !== $chatId) {
      $u = $pdo->prepare("UPDATE akses_bot SET chat_id = ? WHERE id_akses_bot = ?");
      $u->execute([$chatId, $idBot]);
    }

    // Semua akses BOT sekarang tergantung akses_bot.status (akses_login diabaikan).
    if ($botStatus === 'active') {
      $kbSearch = build_search_keyboard($username, $chatId, 'active');

      $text = "✅ <b>FINDER EFIDI</b>\n\n"
            . "Akses kamu saat ini: <b>ACTIVE</b>.\n"
            . "Klik tombol di bawah untuk membuka halaman pencarian efidi.";

      send_finder_message_dedup($pdo, $username, $chatId, $text, $kbSearch);
    } else {
      $kbSearch = build_search_keyboard($username, $chatId, $botStatus);

      $text = "⚠️ <b>FINDER EFIDI</b>\n\n"
            . "Akses kamu saat ini: <b>{$botStatus}</b>.\n"
            . "Kamu masih bisa membuka halaman pengecekan akses melalui tombol di bawah.";

      send_finder_message_dedup($pdo, $username, $chatId, $text, $kbSearch);
    }

    return;
  }

  // ========= MODE PENDAFTARAN (BELUM TERDAFTAR DI akses_bot) =========

  $rowLogin = find_akses_login($pdo, $username);

  if ($rowLogin) {
    // --- user ada di akses_login ---
    $idAkses   = (int)$rowLogin['id_akses'];
    $oldChatId = (int)($rowLogin['chat_id'] ?? 0);
    $status    = strtolower((string)($rowLogin['status'] ?? ''));
    $role      = (string)($rowLogin['role'] ?? '');

    // sinkron chat_id di akses_login
    if ($oldChatId !== $chatId) {
      $u = $pdo->prepare("UPDATE akses_login SET chat_id = ? WHERE id_akses = ?");
      $u->execute([$chatId, $idAkses]);
    }

    if ($status === 'active') {
      // akses_login active → akses_bot.active + kirim tombol Buka Finder Search
      $botStatus = upsert_akses_bot($pdo, $username, $chatId, 'active');

      $kbSearch = build_search_keyboard($username, $chatId, 'active');

      $text = "✅ <b>Selamat datang di FINDER EFIDI.</b>\n\n"
            . "Akun kamu terdaftar sebagai <b>{$role}</b> dan status Finder telah <b>ACTIVE</b>.\n"
            . "Klik tombol di bawah untuk membuka halaman pencarian efidi.";

      send_finder_message_dedup($pdo, $username, $chatId, $text, $kbSearch);
      return;
    }

    // akses_login pending / suspended / lainnya → akses_bot.suspended + Chat Admin
    $botStatus = upsert_akses_bot($pdo, $username, $chatId, 'suspended');
    $kbAdmin   = build_chat_admin_keyboard($username, $chatId);

    $text = "⚠️ <b>Akun efidi. kamu sudah terdaftar</b>,\n"
          . "namun status login saat ini masih <b>{$status}</b>.\n\n"
          . "Data kamu telah dicatat di tabel <b>akses_bot</b> dengan status awal <b>{$botStatus}</b>.\n"
          . "Silakan hubungi admin melalui tombol di bawah ini untuk proses aktivasi Finder efidi.";

    send_finder_message_dedup($pdo, $username, $chatId, $text, $kbAdmin);
    return;
  }

  // --- tidak ada di akses_login → daftarkan ke akses_bot.suspended + Chat Admin ---
  $botStatus = upsert_akses_bot($pdo, $username, $chatId, 'suspended');
  $kbAdmin   = build_chat_admin_keyboard($username, $chatId);

  $text = "⚠️ <b>Username & chat_id kamu belum terdaftar di sistem efidi.</b>\n\n"
        . "Data berikut sudah <b>dicatat</b> di tabel <b>Akses Finder efidi</b> dengan status <b>{$botStatus}</b>:\n"
        . "• Username: <code>@{$username}</code>\n"
        . "• Chat ID : <code>{$chatId}</code>\n\n"
        . "Silakan menunggu proses aktivasi dari admin.\n"
        . "Jika ingin konfirmasi, silakan hubungi admin melalui tombol di bawah ini.";

  send_finder_message_dedup($pdo, $username, $chatId, $text, $kbAdmin);
}

/**
 * /start di GROUP / SUPERGROUP
 */
function handle_start_group(PDO $pdo, array $message): void {
  $chat   = $message['chat'] ?? [];
  $chatId = (int)($chat['id'] ?? 0);
  if ($chatId === 0) return;

  // deep-link ke bot finder (private)
  $deepLink = build_finder_deeplink('finder_from_group');

  // Inline keyboard: tombol dengan URL (BUKAN callback_data lagi)
  $kb = [
    'inline_keyboard' => [
      [
        [
          'text' => '🔎 Finder efidi.',
          'url'  => $deepLink,
        ],
      ],
    ],
  ];

  tg_send_message(
    $chatId,
    "👋 <b>FINDER EFIDI SYSTEM</b>\n\n" .
    "Klik tombol <b>🔎 Finder efidi.</b> di bawah untuk mencari detaiL data instalasi\n" .
    "Setelah tombol ditekan, proses akan dimulai dan menu pencarian akan ditampilkan.",
    [
      'reply_markup'             => json_encode($kb),
      'disable_web_page_preview' => true,
    ]
  );
}

/**
 * Fallback lama: callback "finder:search" -> sekarang diarahkan ke deeplink.
 */
function handle_callback_finder_search(PDO $pdo, array $cb): void {
  $cbId = (string)($cb['id'] ?? '');
  if ($cbId === '') return;

  $url = build_finder_deeplink('finder_from_group_cb');
  f_log('[CALLBACK finder:search] open URL (deeplink) = ' . $url);

  tg_request('answerCallbackQuery', [
    'callback_query_id' => $cbId,
    'url'               => $url,
  ]);
}

// ========== 5) ENTRY POINT WEBHOOK ==========

// Cek secret header (supaya webhook aman)
$hdr = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (defined('WEBHOOK_SECRET_HEAD') && WEBHOOK_SECRET_HEAD !== '' && $hdr !== WEBHOOK_SECRET_HEAD) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$raw = file_get_contents('php://input');
if (!$raw) {
  echo 'OK';
  exit;
}

$update = json_decode($raw, true);
if (!is_array($update)) {
  echo 'OK';
  exit;
}

try {
  if (isset($update['message'])) {
    $msg      = $update['message'];
    $chat     = $msg['chat'] ?? [];
    $chatType = $chat['type'] ?? 'private';
    $text     = trim((string)($msg['text'] ?? ''));

    // deteksi /start (dengan /start@BotName juga aman)
    $isStart = false;
    if ($text !== '') {
      $cmd = strtok($text, ' ');
      if (stripos($cmd, '/start') === 0) {
        $isStart = true;
      }
    }

    if ($isStart) {
      if ($chatType === 'private') {
        handle_start_private($pdo, $msg);
      } elseif ($chatType === 'group' || $chatType === 'supergroup') {
        handle_start_group($pdo, $msg);
      }
    }
  } elseif (isset($update['callback_query'])) {
    $cb   = $update['callback_query'];
    $data = (string)($cb['data'] ?? '');
    if ($data === 'finder:search') {
      handle_callback_finder_search($pdo, $cb);
    }
  }
} catch (Throwable $e) {
  f_log('[ERR] ' . $e->getMessage());
}

echo 'OK';
