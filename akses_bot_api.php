<?php
declare(strict_types=1);

require_once __DIR__ . '/koneksi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ambil BOT_TOKEN dan BOT_TOKEN_FINDER
require_once __DIR__ . '/telegram/telegram_config.php';

// =============================
//  KONSTANTA URL WEBAPP
//  (samakan dengan finder_webhook.php)
// =============================

if (!defined('FINDER_SEARCH_URL')) {
    define('FINDER_SEARCH_URL', 'https://efidi.montaklo.id/finder/search_gate.php');
}

if (!defined('FINDER_CHAT_ADMIN_URL')) {
    define('FINDER_CHAT_ADMIN_URL', 'https://efidi.montaklo.id/chat_admin.php');
}

// URL langsung ke search_bot.php (untuk akses_bot = active)
if (!defined('FINDER_SEARCH_DIRECT_URL')) {
    define('FINDER_SEARCH_DIRECT_URL', 'https://efidi.montaklo.id/search_bot.php');
}

// WebApp efidi.ai (AI assistant) — tombol di bawah Buka Finder Search (hanya ACTIVE)
if (!defined('EFIDI_AI_URL')) {
    define('EFIDI_AI_URL', 'https://efidi.montaklo.id/efidi_ai.php');
}

// URL landing untuk WebApp Telegram (akan melempar ke open_app_landing.php via browser)
if (!defined('FINDER_ANDROID_LANDING_URL')) {
    define('FINDER_ANDROID_LANDING_URL', 'https://efidi.montaklo.id/open_app_landing.php');
}


// =============================
//  LOGGING
// =============================

if (!function_exists('tg_log')) {
    function tg_log(string $msg): void
    {
        @file_put_contents(
            __DIR__ . '/runtime-telegram.log',
            date('c') . ' ' . $msg . "\n",
            FILE_APPEND
        );
    }
}

/**
 * Bangun inline keyboard "Buka Finder Search"
 * SAMA KONFIGURASINYA dengan di finder_webhook.php:
 *  - $statusBot = 'active'    → langsung ke search_bot.php + ada tombol efidi.ai
 *  - $statusBot != 'active'   → ke search_gate.php (cek akses), TANPA efidi.ai
 *
 * return: array inline_keyboard (BUKAN json_encode)
 */
if (!function_exists('build_search_keyboard')) {
  /**
   * Bangun inline keyboard:
   *
   *  - statusBot = 'active'
   *      • 🔎 Searching data       → URL ke open_app_landing.php (FINDER_ANDROID_LANDING_URL)
   *      • 🌐 efidi.ai             → WebApp ke EFIDI_AI_URL
   *
   *  - statusBot != 'active' (suspended / lainnya)
   *      • 🔐 Searching data (Check) → WebApp ke search_gate.php (FINDER_SEARCH_URL)
   */
  function build_search_keyboard(string $username, int $chatId, string $statusBot = 'active'): array {
    $statusBot = strtolower($statusBot);

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

      // Baris 1: Searching data (URL)
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
}


/**
 * Tombol CHAT_ADMIN (Mini App) → chat_admin.php
 */
if (!function_exists('build_chat_admin_keyboard')) {

    function build_chat_admin_keyboard(string $username, int $chatId): array
    {
        $url = FINDER_CHAT_ADMIN_URL . '?' . http_build_query([
            'u'   => $username,
            'cid' => $chatId,
            'src' => 'akses_bot',
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
}

/**
 * Kirim teks ke Telegram (utamakan BOT_TOKEN_FINDER).
 * Sekarang bisa menerima $extra (reply_markup, parse_mode, dll).
 */
if (!function_exists('tg_send_text')) {

    function tg_send_text(string $chat_id, string $text, array $extra = []): bool
    {
        // === PILIH TOKEN: utamakan BOT_TOKEN_FINDER, kalau kosong baru BOT_TOKEN biasa ===
        if (defined('BOT_TOKEN_FINDER') && BOT_TOKEN_FINDER !== '') {
            $token = BOT_TOKEN_FINDER; // pakai bot FINDER (searching bot)
        } elseif (defined('BOT_TOKEN') && BOT_TOKEN !== '') {
            $token = BOT_TOKEN; // fallback ke bot utama
        } else {
            tg_log('Tidak ada BOT_TOKEN_FINDER / BOT_TOKEN yang terisi, batal kirim.');
            return false;
        }

        $api = 'https://api.telegram.org/bot' . $token . '/sendMessage';

        // payload dasar
        $payload = array_merge([
            'chat_id'                  => $chat_id,
            'text'                     => $text,
            'disable_web_page_preview' => true,
        ], $extra);

        $ch = curl_init($api);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $rc   = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($rc !== 200 || $body === false) {
            tg_log("sendMessage FAIL rc=$rc err=" . ($err ?: '-') . " chat_id=$chat_id body=" . substr((string)$body, 0, 200));
            return false;
        }

        tg_log("sendMessage OK chat_id=$chat_id via finder bot");
        return true;
    }
}

/**
 * Kirim pesan + keyboard Finder, tapi pastikan
 * TIDAK ada duplikasi tombol "Buka Finder Search".
 *
 * - baca akses_bot.finder_msg_id (pesan lama)
 * - kalau ada → kosongkan reply_markup di pesan lama
 * - kirim pesan baru + keyboard
 * - simpan message_id baru ke finder_msg_id
 */
function send_finder_message_dedup(PDO $pdo, string $username, string $chat_id, string $text, array $inlineKeyboard): bool
{
    $chat_id  = trim($chat_id);
    $username = ltrim(trim($username), '@');

    if ($chat_id === '' || $username === '') {
        return false;
    }

    // pilih token
    if (defined('BOT_TOKEN_FINDER') && BOT_TOKEN_FINDER !== '') {
        $token = BOT_TOKEN_FINDER;
    } elseif (defined('BOT_TOKEN') && BOT_TOKEN !== '') {
        $token = BOT_TOKEN;
    } else {
        tg_log('send_finder_message_dedup: tidak ada BOT_TOKEN_FINDER / BOT_TOKEN');
        return false;
    }

    $apiBase = 'https://api.telegram.org/bot' . $token . '/';

    // 1) ambil message_id lama
    $prevMsgId = 0;
    try {
        $st = $pdo->prepare("
            SELECT finder_msg_id
            FROM akses_bot
            WHERE LOWER(username) = LOWER(?)
            LIMIT 1
        ");
        $st->execute([$username]);
        $prevMsgId = (int)($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        tg_log('send_finder_message_dedup: DB error get prev: ' . $e->getMessage());
    }

    // 2) kosongkan keyboard lama (kalau ada)
    if ($prevMsgId > 0) {
        $ch = curl_init($apiBase . 'editMessageReplyMarkup');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'chat_id'      => $chat_id,
                'message_id'   => $prevMsgId,
                'reply_markup' => json_encode(['inline_keyboard' => []]),
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            tg_log('send_finder_message_dedup: editMessageReplyMarkup FAIL ' . $err);
        }
    }

    // 3) kirim pesan baru
    $payload = [
        'chat_id'                  => $chat_id,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if (!empty($inlineKeyboard)) {
        $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
    }

    $ch = curl_init($apiBase . 'sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        tg_log("send_finder_message_dedup: sendMessage FAIL err=" . ($err ?: '-'));
        return false;
    }

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded) || empty($decoded['ok'])) {
        tg_log("send_finder_message_dedup: sendMessage not ok body=" . substr((string)$body, 0, 200));
        return false;
    }

    $msgId = (int)($decoded['result']['message_id'] ?? 0);
    if ($msgId > 0) {
        try {
            $up = $pdo->prepare("
                UPDATE akses_bot
                SET finder_msg_id = ?
                WHERE LOWER(username) = LOWER(?)
            ");
            $up->execute([$msgId, $username]);
        } catch (Throwable $e) {
            tg_log('send_finder_message_dedup: DB error update finder_msg_id: ' . $e->getMessage());
        }
    }

    tg_log("send_finder_message_dedup OK chat_id=$chat_id msg_id=$msgId");
    return true;
}

/**
 * Dipanggil saat status akses_bot berubah menjadi ACTIVE.
 * Kirim pesan + tombol 🔎 Buka Finder Search (mode ACTIVE, tanpa duplikasi).
 */
function notify_bot_active(string|int|null $chat_id, string|int|null $username, ?string $adminName = null): bool
{
    global $pdo;

    $chat_id  = trim((string)$chat_id);
    $username = ltrim(trim((string)$username), '@');

    if ($chat_id === '' || $username === '') {
        return false;
    }

    $by  = $adminName ? " oleh {$adminName}" : "";
    $usr = $username ? " (@{$username})" : "";

    $text = "✅ Akses Finder efidi sekarang <b>ACTIVE</b>{$usr} oleh admin.\n"
          . "Silahkan gunakan fitur yang tersedia melalui tombol <b>Collecting data</b> di bawah ini.\n";

    // mode ACTIVE → tombol normal (+ efidi.ai di row kedua)
    $kbSearch = build_search_keyboard($username, (int)$chat_id, 'active');

    return send_finder_message_dedup($pdo, $username, $chat_id, $text, $kbSearch);
}

/**
 * Dipanggil saat status akses_bot berubah menjadi SUSPENDED.
 * Kirim pesan + tombol Buka Finder Search (cek akses), TANPA tombol Chat Admin.
 */
function notify_bot_suspended(string|int|null $chat_id, string|int|null $username, ?string $adminName = null): bool
{
    global $pdo;

    $chat_id  = trim((string)$chat_id);
    $username = ltrim(trim((string)$username), '@');

    if ($chat_id === '' || $username === '') {
        return false;
    }

    $by  = $adminName ? " oleh {$adminName}" : "";
    $usr = $username ? " (@{$username})" : "";

    $text = "⏸️ Akses Finder efidi saat ini <b>DISUSPEND</b>{$usr} oleh admin.\n\n"
          . "Kamu masih bisa membuka halaman Collecting data melalui tombol di bawah, "
          . "namun akses dan fitur dapat dibatasi sesuai kebijakan admin.\n"
          . "Jika perlu re-aktivasi, silakan hubungi admin efidi.";

    // mode SUSPENDED → tombol cek akses (tanpa efidi.ai)
    $kbSearch = build_search_keyboard($username, (int)$chat_id, 'suspended');

    return send_finder_message_dedup($pdo, $username, $chat_id, $text, $kbSearch);
}

// =============================
//  CONTROLLER AJAX
// =============================

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// bersihkan output buffering kalau ada
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

function out($a, $c = 200)
{
    http_response_code($c);
    echo json_encode($a);
    exit;
}

function post($k, $d = '')
{
    return $_POST[$k] ?? $d;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(['ok' => false, 'msg' => 'Method not allowed'], 405);
}

$action = (string)post('action', '');

switch ($action) {
    case 'get_state': {
        $id = (int)post('id', 0);
        if ($id <= 0) {
            out(['ok' => false, 'msg' => 'ID tidak valid'], 400);
        }

        $st  = $pdo->prepare("SELECT status FROM akses_bot WHERE id_akses_bot=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            out(['ok' => false, 'msg' => 'Data tidak ditemukan'], 404);
        }

        out([
            'ok'     => true,
            'status' => strtolower($row['status'] ?? ''),
        ]);
    }

    case 'save_status': {
        $id     = (int)post('id', 0);
        $status = strtolower(trim((string)post('status', '')));

        if ($id <= 0) {
            out(['ok' => false, 'msg' => 'ID kosong'], 400);
        }

        if (!in_array($status, ['active', 'suspended', ''], true)) {
            out(['ok' => false, 'msg' => 'Status tidak valid'], 422);
        }

        try {
            // kondisi sebelum update
            $st0 = $pdo->prepare("SELECT username, chat_id, status FROM akses_bot WHERE id_akses_bot=?");
            $st0->execute([$id]);
            $before = $st0->fetch(PDO::FETCH_ASSOC) ?: ['username' => '', 'chat_id' => '', 'status' => ''];

            // update
            $st = $pdo->prepare("UPDATE akses_bot SET status=? WHERE id_akses_bot=?");
            $st->execute([$status, $id]);

            // sesudah update
            $st2 = $pdo->prepare("SELECT username, chat_id, status FROM akses_bot WHERE id_akses_bot=?");
            $st2->execute([$id]);
            $after = $st2->fetch(PDO::FETCH_ASSOC) ?: ['username' => '', 'chat_id' => '', 'status' => ''];

            $prevStatus      = strtolower((string)$before['status']);
            $newStatus       = strtolower((string)$after['status']);
            $becameActive    = ($prevStatus !== 'active' && $newStatus === 'active');
            $becameSuspended = ($prevStatus !== 'suspended' && $newStatus === 'suspended');

            $notified      = false;
            $notify_reason = 'not_applicable';

            $adminName = $_SESSION['nama'] ?? ($_SESSION['user']['name'] ?? null);
            $cid       = trim((string)($after['chat_id'] ?? ''));
            $usr       = trim((string)($after['username'] ?? ''));

            // status berubah menjadi ACTIVE
            if ($becameActive) {
                if ($cid !== '' && preg_match('/^-?\d{5,}$/', $cid)) {
                    try {
                        $notified      = notify_bot_active($cid, $usr, $adminName);
                        $notify_reason = $notified ? 'sent_active' : 'tg_send_failed';
                    } catch (Throwable $te) {
                        $notified      = false;
                        $notify_reason = 'tg_exception';
                        if (function_exists('tg_log')) {
                            tg_log('notify_bot_active exception: ' . $te->getMessage());
                        }
                    }
                } else {
                    $notify_reason = 'invalid_chat_id';
                    if (function_exists('tg_log')) {
                        tg_log("skip notify_bot_active: invalid_chat_id=" . json_encode($after['chat_id'] ?? null) . " for id_akses_bot=$id");
                    }
                }
            }

            // status berubah menjadi SUSPENDED
            if ($becameSuspended) {
                if ($cid !== '' && preg_match('/^-?\d{5,}$/', $cid)) {
                    try {
                        $notified      = notify_bot_suspended($cid, $usr, $adminName);
                        $notify_reason = $notified ? 'sent_suspended' : 'tg_send_failed';
                    } catch (Throwable $te) {
                        $notified      = false;
                        $notify_reason = 'tg_exception';
                        if (function_exists('tg_log')) {
                            tg_log('notify_bot_suspended exception: ' . $te->getMessage());
                        }
                    }
                } else {
                    $notify_reason = 'invalid_chat_id';
                    if (function_exists('tg_log')) {
                        tg_log("skip notify_bot_suspended: invalid_chat_id=" . json_encode($after['chat_id'] ?? null) . " for id_akses_bot=$id");
                    }
                }
            }

            out([
                'ok'            => true,
                'status'        => $status,
                'notified_user' => $notified,
                'notify_reason' => $notify_reason,
            ]);
        } catch (Throwable $e) {
            out(['ok' => false, 'msg' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    default:
        out(['ok' => false, 'msg' => 'Unknown action'], 400);
}
