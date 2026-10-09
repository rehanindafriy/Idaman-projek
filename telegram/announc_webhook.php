<?php
/**
 * announc_webhook.php
 *
 * Kirim laporan harian ORDER/BAI ke semua teknisi
 * (role='tech', status='active') berdasarkan user_id = chat_id.
 *
 * Bisa dipanggil:
 *  - CLI (cron: php announc_webhook.php)
 *  - HTTP: announc_webhook.php?cron=report&key=...&force=1
 */

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

/* ========= LOAD PDO ========= */
require_once __DIR__.'/../koneksi.php';
@require_once __DIR__.'/../auth.php'; // kalau ada

$ROOT = dirname(__DIR__);
if (!isset($pdo) || !($pdo instanceof PDO)) {
  foreach ([$ROOT.'/koneksi.php', __DIR__.'/../koneksi.php', __DIR__.'/koneksi.php'] as $p) {
    if (is_file($p)) { require $p; break; }
  }
}
if (function_exists('db') && !($pdo instanceof PDO)) {
  $pdo = db() ?: $pdo;
}
if (!($pdo instanceof PDO)) {
  error_log('[ANNOUNC] DB not ready; exiting');
  http_response_code(500);
  echo 'DB not ready';
  exit;
}

/* ========= LOG SEDERHANA ========= */
function alog(string $msg): void {
  @file_put_contents(
    __DIR__.'/runtime-announc-webhook.log',
    date('c').' '.$msg."\n",
    FILE_APPEND
  );
}

/* ========= TOKEN TELEGRAM ========= */
require_once __DIR__.'/telegram_config.php'; // BOT_TOKEN, WEBHOOK_SECRET_HEADER, dll

if (!defined('BOT_TOKEN') || BOT_TOKEN === '') {
  http_response_code(500);
  echo 'BOT_TOKEN missing';
  exit;
}
$API = 'https://api.telegram.org/bot'.BOT_TOKEN.'/';

/* ========= KONSTAN CRON ========= */
// Rahasia untuk mode HTTP (?key=...)
if (!defined('CRON_SECRET')) {
  define('CRON_SECRET', 'LaporanOrderBaiharian');
}
// Jam laporan WIB (HH:MM)
if (!defined('REPORT_HHMM')) {
  define('REPORT_HHMM', '18:00'); // <<< kirim setiap hari jam 17:00 WIB
}

/* ========= HELPER DB ========= */
function pdo_set_wib(PDO $pdo): void {
  try {
    $pdo->exec("SET time_zone = '+07:00'");
  } catch (Throwable $e) {
    // diam saja; fallback ke TZ server
  }
}

function exec_scalar(PDO $pdo, string $sql, array $params = []): int {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return (int)($v ?? 0);
  } catch (Throwable $e) {
    error_log('[DB-ERR] exec_scalar: '.$e->getMessage());
    return 0;
  }
}

/* ========= FORMAT TEKS LAPORAN ========= */
function laporan_text(
  string $nama,
  string $username,
  int $totalO,
  int $totalB,
  int $todayO,
  int $todayB
): string {
  $outAll   = max(0, $totalO - $totalB);
  $outToday = max(0, $todayO - $todayB);
  $who = $nama !== '' ? $nama : ($username !== '' ? '@'.$username : '(tanpa nama)');
  $jam = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))
          ->format('d M Y, H:i').' WIB';

// bikin dulu status line biar dinamis
$statusLine = ($outToday > 0)
  ? "⚠️ Masih ada {$outToday} pekerjaan outstanding hari ini. Mohon segera ditindaklanjuti 🙏"
  : "✅ Tidak ada outstanding hari ini. Terima kasih, mantap! 💪";

$lines = [
  "🛰️  EFIDI · DAILY PERFORMANCE REPORT",
  "👤  Teknisi : {$who}",
  "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
  "📆 AKTIVITAS HARI INI",
  "   • ORDER       : {$todayO}",
  "   • BAI         : {$todayB}",
  "   • OUTSTANDING : {$outToday}",
  "",
  $statusLine,
  "",
  "⏱️ Update per : {$jam}",
  "— Reminder otomatis efidi"
];


  return implode("\n", $lines);
}

/* ========= HELPER TELEGRAM ========= */
function ann_tg_request(string $method, array $data = []): array {
  global $API;
  if (function_exists('curl_init')) {
    $ch = curl_init($API.$method);
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
      ]
    ];
    $ctx  = stream_context_create($opts);
    $resp = @file_get_contents($API.$method, false, $ctx);
    $err  = ($resp === false) ? 'file_get_contents failed' : '';
    $code = 200;
  }
  return [$resp, $err, $code];
}

function ann_tg_send_message($chat_id, string $text, array $extra = []): void {
  $payload = array_merge([
    'chat_id' => strval($chat_id),
    'text'    => $text,
  ], $extra);

  [$resp, $err, $code] = ann_tg_request('sendMessage', $payload);
  if ($err || $code >= 400) {
    alog("[TG] sendMessage error to {$chat_id}: code={$code} err={$err} resp=".substr((string)$resp,0,200));
  }
}

/* ========= FUNGSI UTAMA: RUN LAPORAN ========= */
function run_daily_report(PDO $pdo, bool $force = false): void {
  // Daily Performance private Tech dinonaktifkan sementara
  alog("CRON-REPORT skipped: tech daily report disabled");
  return;

  pdo_set_wib($pdo);

  $nowWIB = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
  $hhmm   = $nowWIB->format('H:i');

  // Gate jam kirim
  if (!$force && $hhmm !== REPORT_HHMM) {
    if (PHP_SAPI !== 'cli') {
      header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
      'ok'   => true,
      'skip' => 'not_target_time',
      'want' => REPORT_HHMM,
      'now'  => $nowWIB->format('c'),
    ]);
    return;
  }

  alog("CRON-REPORT start want=".REPORT_HHMM." now=".$nowWIB->format('H:i'));

  // Ambil semua tech aktif yg punya chat_id
  $qTech = $pdo->query("
    SELECT nama, username, chat_id
    FROM akses_login
    WHERE role='tech' AND status='active' AND chat_id > 0
  ");
  $techs = $qTech ? $qTech->fetchAll(PDO::FETCH_ASSOC) : [];
  $sent = 0;
  $errors = 0;

  foreach ($techs as $t) {
    $cid  = (string)$t['chat_id'];              // chat_id = user_id
    $nama = (string)($t['nama'] ?? '');
    $user = (string)($t['username'] ?? '');

    // TOTAL
    $totalO = exec_scalar($pdo,
      "SELECT COUNT(*) FROM orders WHERE user_id = ?",
      [$cid]
    );
    $totalB = exec_scalar($pdo,
      "SELECT COUNT(*) FROM bai WHERE user_id = ?",
      [$cid]
    );

    // HARI INI (pakai created_at & date_received)
    $todayO = exec_scalar($pdo,
      "SELECT COUNT(*) FROM orders
       WHERE user_id = ? AND DATE(created_at) = CURDATE()",
      [$cid]
    );
    $todayB = exec_scalar($pdo,
      "SELECT COUNT(*) FROM bai
       WHERE user_id = ? AND DATE(date_received) = CURDATE()",
      [$cid]
    );

    $text = laporan_text($nama, $user, $totalO, $totalB, $todayO, $todayB);

    try {
      ann_tg_send_message($cid, $text, ['disable_web_page_preview' => true]);
      $sent++;
    } catch (Throwable $e) {
      $errors++;
      alog('[CRON-REPORT] send fail to '.$cid.': '.$e->getMessage());
    }
  }

  if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode([
    'ok'         => true,
    'sent'       => $sent,
    'errors'     => $errors,
    'tech_count' => count($techs),
  ]);
}

/* ========= ENTRY POINT ========= */

// Mode CLI (cron: php announc_webhook.php [--force])
if (PHP_SAPI === 'cli') {
  $force = in_array('--force', $argv ?? [], true);
  run_daily_report($pdo, $force);
  exit;
}

// Mode HTTP
if (isset($_GET['cron']) && $_GET['cron'] === 'report') {
  $key = (string)($_GET['key'] ?? '');
  if (!hash_equals(CRON_SECRET, $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  $force = isset($_GET['force']) && $_GET['force'] === '1';
  run_daily_report($pdo, $force);
  exit;
}

// Healthcheck biasa
echo 'OK announc';
exit;
