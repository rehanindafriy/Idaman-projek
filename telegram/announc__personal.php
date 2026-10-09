<?php
/**
 * announc__personal.php
 *
 * Laporan HARIAN GLOBAL ke akun personal:
 * - Ringkasan TODAY (semua data)
 * - Ringkasan ALL SUMMARY (ALL DATA: semua id_order & id_bai)
 * - Detail ORDER & BAI hari ini
 * - Rekap aktivitas per TECH (HARI INI SAJA, hanya yang ada aktivitas)
 * - Dikirim ke semua akun role 'admin' dan 'ta' (status='active', chat_id > 0)
 *
 * Cara pakai:
 *   CLI : php announc__personal.php [--force]
 *   HTTP: announc__personal.php?cron=summary&key=LaporanOrderBaiharianAdmin[&force=1]
 */

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

/* ========= LOAD PDO ========= */
require_once __DIR__.'/../koneksi.php';
@require_once __DIR__.'/../auth.php';

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
  error_log('[ANNOUNC-PERSONAL] DB not ready; exiting');
  http_response_code(500);
  echo 'DB not ready';
  exit;
}

/* ========= LOG ========= */
function plog(string $msg): void {
  @file_put_contents(
    __DIR__.'/runtime-announc-personal.log',
    date('c').' '.$msg."\n",
    FILE_APPEND
  );
}

/* ========= TELEGRAM CONFIG ========= */
require_once __DIR__.'/telegram_config.php'; // harus define BOT_TOKEN

if (!defined('BOT_TOKEN') || BOT_TOKEN === '') {
  http_response_code(500);
  echo 'BOT_TOKEN missing';
  exit;
}
$API = 'https://api.telegram.org/bot'.BOT_TOKEN.'/';

/**
 * Batas caption Telegram untuk photo = 1024 karakter.
 * Kalau teks laporan > 1024, kita kirim foto & teks terpisah.
 */
if (!defined('TELEGRAM_CAPTION_LIMIT')) {
  define('TELEGRAM_CAPTION_LIMIT', 1024);
}

/* ========= CONST TAMBAHAN: DASHBOARD & SCREENSHOT ========= */
// URL dashboard admin untuk screenshot (auto-login via cron_key)
if (!defined('DASHBOARD_URL')) {
  define(
    'DASHBOARD_URL',
    'https://efidi.montaklo.id/dashboard_admin.php?cron_key=efidi_ss_2025_xh3J9F7s_8KpQ2Z0Lm'
  );
}

if (!defined('BROWSERLESS_ENDPOINT')) {
  define('BROWSERLESS_ENDPOINT', 'https://production-sfo.browserless.io/screenshot');
}

if (!defined('BROWSERLESS_TOKEN')) {
  // TOKEN Browserless milik efidi
  define('BROWSERLESS_TOKEN', '2TXHscMvm0paeHI034a049446668cf34cd2f708cd2a90bacc');
}

if (!defined('DASHBOARD_CLIP_X')) {
  define('DASHBOARD_CLIP_X', 280);
}
if (!defined('DASHBOARD_CLIP_Y')) {
  define('DASHBOARD_CLIP_Y', 195);
}
if (!defined('DASHBOARD_CLIP_WIDTH')) {
  define('DASHBOARD_CLIP_WIDTH', 1580);
}
if (!defined('DASHBOARD_CLIP_HEIGHT')) {
  define('DASHBOARD_CLIP_HEIGHT', 585);
}

/**
 * Wrapper sederhana ke Browserless /screenshot
 */
function capture_url_screenshot(string $url, string $outputPath, array $options = []): bool {
  if (!defined('BROWSERLESS_ENDPOINT') || BROWSERLESS_ENDPOINT === '') {
    plog('[SCREEN] BROWSERLESS_ENDPOINT not defined');
    return false;
  }

  $token = getenv('BROWSERLESS_TOKEN') ?: (defined('BROWSERLESS_TOKEN') ? BROWSERLESS_TOKEN : '');
  if ($token === '' || $token === 'YOUR_BROWSERLESS_TOKEN_HERE') {
    plog('[SCREEN] BROWSERLESS_TOKEN not set');
    return false;
  }

  $endpoint = rtrim(BROWSERLESS_ENDPOINT, '/') . '?token=' . rawurlencode($token);

  // viewport tetap 1920x900
  $viewportWidth  = isset($options['width'])  ? (int)$options['width']  : 1920;
  $viewportHeight = isset($options['height']) ? (int)$options['height'] : 900;

  $deviceScale = (int)($options['deviceScaleFactor'] ?? 1);
  $delaySec    = (int)($options['delay'] ?? 10);
  $delayMs     = max(0, $delaySec * 1000);
  $fullPage    = isset($options['fullPage']) ? (bool)$options['fullPage'] : false;

  $payload = [
    'url'            => $url,
    'waitForTimeout' => $delayMs,
    'gotoOptions'    => [
      'waitUntil' => 'networkidle2',
      'timeout'   => 90000,
    ],
    'options'        => [
      'type'     => 'png',
      'fullPage' => $fullPage,
    ],
    'viewport'       => [
      'width'            => $viewportWidth,
      'height'           => $viewportHeight,
      'deviceScaleFactor'=> $deviceScale,
    ],
  ];

  $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
  plog('[SCREEN] Browserless request: ' . $endpoint . ' url=' . $url);

  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      'Cache-Control: no-cache',
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
  ]);

  $imgData  = curl_exec($ch);
  $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $errMsg   = curl_error($ch);
  curl_close($ch);

  if ($imgData === false || $httpCode >= 400) {
    $body = is_string($imgData) ? substr($imgData, 0, 300) : '';
    plog('[SCREEN] Browserless screenshot failed: code=' . $httpCode .
         ' err=' . $errMsg . ' body=' . $body);
    return false;
  }

  if (@file_put_contents($outputPath, $imgData) === false) {
    plog('[SCREEN] cannot write screenshot file: ' . $outputPath);
    return false;
  }

  plog('[SCREEN] screenshot saved: ' . $outputPath . ' (bytes=' . strlen($imgData) . ')');
  return true;
}

/**
 * Crop area persegi panjang dari sebuah gambar.
 */
function crop_image_area(string $src, string $dst, array $rect): bool {
  if (!is_file($src)) {
    plog('[SCREEN] crop_image_area: src not found: ' . $src);
    return false;
  }

  if (!extension_loaded('gd')) {
    plog('[SCREEN] crop_image_area: GD extension not loaded');
    return false;
  }

  $x = max(0, (int)($rect['x'] ?? 0));
  $y = max(0, (int)($rect['y'] ?? 0));
  $w = max(1, (int)($rect['width'] ?? 1));
  $h = max(1, (int)($rect['height'] ?? 1));

  $info = @getimagesize($src);
  if ($info === false) {
    plog('[SCREEN] crop_image_area: cannot getimagesize: ' . $src);
    return false;
  }

  $mimeType = $info['mime'] ?? '';
  switch ($mimeType) {
    case 'image/png':
      $im = @imagecreatefrompng($src);
      break;
    case 'image/jpeg':
    case 'image/jpg':
      $im = @imagecreatefromjpeg($src);
      break;
    case 'image/webp':
      if (function_exists('imagecreatefromwebp')) {
        $im = @imagecreatefromwebp($src);
      } else {
        $im = false;
      }
      break;
    default:
      $im = @imagecreatefrompng($src);
      break;
  }

  if (!$im) {
    plog('[SCREEN] crop_image_area: cannot create image from ' . $src . ' (mime=' . $mimeType . ')');
    return false;
  }

  $imgW = imagesx($im);
  $imgH = imagesy($im);

  if ($x >= $imgW || $y >= $imgH) {
    imagedestroy($im);
    plog('[SCREEN] crop_image_area: rect out of bounds (x=' . $x . ', y=' . $y . ', w=' . $w . ', h=' . $h . ', img=' . $imgW . 'x' . $imgH . ')');
    return false;
  }
  if ($x + $w > $imgW) {
    $w = $imgW - $x;
  }
  if ($y + $h > $imgH) {
    $h = $imgH - $y;
  }

  if (function_exists('imagecrop')) {
    $cropped = @imagecrop($im, [
      'x'      => $x,
      'y'      => $y,
      'width'  => $w,
      'height' => $h,
    ]);
  } else {
    $cropped = imagecreatetruecolor($w, $h);
    imagealphablending($cropped, false);
    imagesavealpha($cropped, true);
    imagecopy($cropped, $im, 0, 0, $x, $y, $w, $h);
  }

  if (!$cropped) {
    imagedestroy($im);
    plog('[SCREEN] crop_image_area: crop failed');
    return false;
  }

  $ok = @imagepng($cropped, $dst);
  imagedestroy($im);
  imagedestroy($cropped);

  if (!$ok) {
    plog('[SCREEN] crop_image_area: cannot save cropped file: ' . $dst);
    return false;
  }

  return true;
}

/**
 * Ambil screenshot dashboard via Browserless.
 */
function ap_capture_dashboard_screenshot(string $url): ?string {
  if (!defined('BROWSERLESS_ENDPOINT') || BROWSERLESS_ENDPOINT === '') {
    plog('[SCREEN] BROWSERLESS_ENDPOINT not defined, skip screenshot');
    return null;
  }
  if (!defined('BROWSERLESS_TOKEN') || BROWSERLESS_TOKEN === '' ||
      BROWSERLESS_TOKEN === 'YOUR_BROWSERLESS_TOKEN_HERE') {
    plog('[SCREEN] BROWSERLESS_TOKEN not set, skip screenshot');
    return null;
  }

  $dir = __DIR__ . '/runtime-screens';
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }

  $fileFull    = $dir . '/dashboard-personal-full-' . date('Ymd-His') . '.png';
  $fileCropped = $dir . '/dashboard-personal-'      . date('Ymd-His') . '.png';

  $sep        = (strpos($url, '?') !== false) ? '&' : '?';
  $urlNoCache = $url . $sep . 'snap_ts=' . time();

  $ok = capture_url_screenshot(
    $urlNoCache,
    $fileFull,
    [
      'deviceScaleFactor'=> 1,
      'delay'            => 10,
      'fullPage'         => false,
    ]
  );

  if (!$ok || !is_file($fileFull)) {
    plog('[SCREEN] capture_url_screenshot failed');
    return null;
  }

  $rect = [
    'x'      => DASHBOARD_CLIP_X,
    'y'      => DASHBOARD_CLIP_Y,
    'width'  => DASHBOARD_CLIP_WIDTH,
    'height' => DASHBOARD_CLIP_HEIGHT,
  ];

  if (crop_image_area($fileFull, $fileCropped, $rect)) {
    @unlink($fileFull);
    plog('[SCREEN] cropped screenshot saved: ' . $fileCropped);
    return $fileCropped;
  }

  plog('[SCREEN] crop_image_area failed, using full screenshot');
  return $fileFull;
}

/* ========= CRON CONST ========= */
if (!defined('CRON_SECRET')) {
  define('CRON_SECRET', 'LaporanOrderBaiharianAdmin');
}
if (!defined('REPORT_HHMM')) {
  define('REPORT_HHMM', '17:10');
}
if (!defined('PUBLIC_FILE_BASE_URL')) {
  define('PUBLIC_FILE_BASE_URL', 'https://efidi.montaklo.id/bot/file/');
}

/* ========= DB HELPERS ========= */
function pdo_set_wib(PDO $pdo): void {
  try { $pdo->exec("SET time_zone = '+07:00'"); } catch (Throwable $e) {}
}

function exec_scalar(PDO $pdo, string $sql, array $params = []): int {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return (int)($v ?? 0);
  } catch (Throwable $e) {
    plog('[DB-ERR] exec_scalar: '.$e->getMessage());
    return 0;
  }
}

/* ========= FORMAT TEKS ========= */
function header_laporan_text(
  int $todayO,
  int $todayB,
  int $allO,
  int $allB
): string {
  $outToday = max(0, $todayO - $todayB);
  $outAll   = max(0, $allO - $allB);
  $jam      = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))
                ->format('d M Y, H:i');

  $lines = [
    "<b>📊 EFIDI · DAILY PERFORMANCE REPORT</b>",
    "<code>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</code>",
    "",
    "<b>📦 TODAY SUMMARY (ALL TECH)</b>",
    "• ORDER: <b>{$todayO}</b> | BAI: <b>{$todayB}</b> | OUTSTANDING: <b>{$outToday}</b>",
    "",
    "<b>📦 ALL SUMMARY (ALL DATA)</b>",
    "• ORDER: <b>{$allO}</b> | BAI: <b>{$allB}</b> | OUTSTANDING: <b>{$outAll}</b>",
    "",
    "<i>🔎 Detail lengkap ALL SUMMARY dapat dibuka melalui tombol di bawah.</i>",
    "",
    "<b>📅 Generated on:</b> {$jam} WIB",
    "<i>Automated Admin Report by EFIDI.</i>",
  ];
  return implode("\n", $lines);
}

/* ========= TELEGRAM HELPERS ========= */
function ap_tg_request(string $method, array $data = []): array {
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

function ap_tg_send_message($chat_id, string $text, array $extra = []): void {
  $payload = array_merge([
    'chat_id'    => strval($chat_id),
    'text'       => $text,
    'parse_mode' => 'HTML',
  ], $extra);

  [$resp, $err, $code] = ap_tg_request('sendMessage', $payload);
  if ($err || $code >= 400) {
    plog("[TG] sendMessage error to {$chat_id}: code={$code} err={$err} resp=".substr((string)$resp,0,200));
  }
}

function ap_tg_send_photo($chat_id, string $photoPath, string $caption, array $extra = []): void {
  global $API;

  if (!is_file($photoPath)) {
    plog("[TG] photo not found: {$photoPath}, fallback to text");
    ap_tg_send_message($chat_id, $caption, $extra);
    return;
  }

  if (!function_exists('curl_init')) {
    plog('[TG] curl not available, fallback to text only');
    ap_tg_send_message($chat_id, $caption, $extra);
    return;
  }

  if (function_exists('curl_file_create')) {
    $file = curl_file_create($photoPath, 'image/png', basename($photoPath));
  } else {
    $file = new CURLFile($photoPath, 'image/png', basename($photoPath));
  }

  $payload = array_merge([
    'chat_id'    => strval($chat_id),
    'photo'      => $file,
    'caption'    => $caption,
    'parse_mode' => 'HTML',
  ], $extra);

  $ch = curl_init($API.'sendPhoto');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 60,
  ]);

  $resp = curl_exec($ch);
  $err  = ($resp === false) ? (curl_error($ch) ?: 'curl_exec false') : '';
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($err || $code >= 400) {
    plog("[TG] sendPhoto(FILE) error to {$chat_id}: code={$code} err={$err} resp=".substr((string)$resp,0,200));
    ap_tg_send_message($chat_id, $caption, $extra);
  }
}

/* ========= FUNGSI UTAMA ========= */
function run_global_summary(PDO $pdo, bool $force = false): void {
  pdo_set_wib($pdo);

  $nowWIB = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
  $hhmm   = $nowWIB->format('H:i');

  // Gate waktu kirim
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

  plog("GLOBAL-SUMMARY start want=".REPORT_HHMM." now=".$nowWIB->format('H:i'));

  // Ambil semua TECH aktif (role = tech, case-insensitive, trim)
  $qTech = $pdo->query("
    SELECT nama, username, chat_id
    FROM akses_login
    WHERE LOWER(TRIM(role)) = 'tech'
      AND status = 'active'
      AND chat_id > 0
  ");
  $techs     = $qTech ? $qTech->fetchAll(PDO::FETCH_ASSOC) : [];
  $totalTech = count($techs);

  $perTech     = [];
  $reportedCnt = 0;

  foreach ($techs as $t) {
    $cid  = (string)$t['chat_id'];
    $nama = (string)($t['nama'] ?? '');
    $user = (string)($t['username'] ?? '');

    $totalO = exec_scalar($pdo,
      "SELECT COUNT(*) FROM orders WHERE user_id = ?",
      [$cid]
    );
    $totalB = exec_scalar($pdo,
      "SELECT COUNT(*) FROM bai WHERE user_id = ?",
      [$cid]
    );
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

    $hasActivity = ($todayO + $todayB) > 0;
    if ($hasActivity) {
      $reportedCnt++;
      $perTech[] = [
        'nama'     => $nama,
        'username' => $user,
        'todayO'   => $todayO,
        'todayB'   => $todayB,
        'totalO'   => $totalO,
        'totalB'   => $totalB,
      ];
    }
  }

  // GLOBAL SUMMARY
  $todayAllO = exec_scalar($pdo,
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
  );
  $todayAllB = exec_scalar($pdo,
    "SELECT COUNT(*) FROM bai WHERE DATE(date_received) = CURDATE()"
  );
  $allTotalO = exec_scalar($pdo,
    "SELECT COUNT(*) FROM orders"
  );
  $allTotalB = exec_scalar($pdo,
    "SELECT COUNT(*) FROM bai"
  );

  // DETAIL GLOBAL HARI INI (ORDER & BAI) – tidak dipakai langsung di teks, tapi bisa dipakai kalau mau
  $todayOrders = [];
  $todayBai    = [];

  try {
    $stO = $pdo->prepare("
      SELECT no_sc, no_internet
      FROM orders
      WHERE DATE(created_at) = CURDATE()
      ORDER BY created_at ASC
    ");
    $stO->execute();
    $todayOrders = $stO->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    plog('[DB-ERR] todayOrders: '.$e->getMessage());
  }

  try {
    $stB = $pdo->prepare("
      SELECT nama_bai
      FROM bai
      WHERE DATE(date_received) = CURDATE()
      ORDER BY date_received ASC
    ");
    $stB->execute();
    $todayBai = $stB->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    plog('[DB-ERR] todayBai: '.$e->getMessage());
  }

  // SUSUN TEKS
  $txt  = header_laporan_text($todayAllO, $todayAllB, $allTotalO, $allTotalB);
  $txt .= "\n\n<b>🧑‍🔧 TECHNICIAN PERFORMANCE SUMMARY</b>\n";

  if ($totalTech > 0) {
    $txt .= "✅ <b>{$reportedCnt}</b> tech aktif hari ini dari <b>{$totalTech}</b> tech terdaftar.\n\n";
  } else {
    $txt .= "<i>Belum ada teknisi yang terdaftar di sistem.</i>\n\n";
  }

  if (!$perTech) {
    $txt .= "• <i>Belum terdapat aktivitas ORDER maupun BAI yang dilaporkan oleh teknisi pada hari ini.</i>\n";
  } else {
    foreach ($perTech as $r) {
      $nama = $r['nama'];
      $user = $r['username'];
      $tag  = $user !== '' ? '@'.$user : '';
      $disp = $nama !== '' ? $nama : ($tag !== '' ? $tag : '(tanpa nama)');
      if ($tag !== '' && $nama !== '') {
        $disp .= " ({$tag})";
      }

      $outToday = max(0, $r['todayO'] - $r['todayB']);

      $txt .= sprintf(
        "• %s\n".
        "   ORDER: <b>%d</b> | BAI: <b>%d</b> | OUTSTANDING: <b>%d</b>\n",
        $disp,
        $r['todayO'],
        $r['todayB'],
        $outToday
      );
    }
  }

  // Hitung panjang teks untuk cek batas caption
  if (function_exists('mb_strlen')) {
    $captionLen = mb_strlen($txt, 'UTF-8');
  } else {
    $captionLen = strlen($txt);
  }

  // CAPTURE DASHBOARD SEKALI
  $screenshot = ap_capture_dashboard_screenshot(DASHBOARD_URL);

  // === PENERIMA: ADMIN (case-insensitive, trimming) ===
  $qAdm = $pdo->query("
    SELECT nama, username, chat_id, role
    FROM akses_login
    WHERE status = 'active'
      AND chat_id > 0
      AND LOWER(TRIM(role)) = 'admin'
  ");
  $admins = $qAdm ? $qAdm->fetchAll(PDO::FETCH_ASSOC) : [];

  plog('[GLOBAL-SUMMARY] recipients (admin) count='.count($admins));

  $sent   = 0;
  $errors = 0;

  foreach ($admins as $a) {
    $cidStr   = (string)$a['chat_id'];
    $username = trim((string)($a['username'] ?? ''));
    $role     = strtolower(trim((string)($a['role'] ?? '')));
    $uParam   = $username !== '' ? '&u=' . rawurlencode($username) : '';

    $inlineKeyboard = [
      'inline_keyboard' => [
        [
          [
            'text'    => '📋 Detail ORDER',
            'web_app' => [
              'url' => 'https://efidi.montaklo.id/orders_all.php?src=telegram_admin&cid='
                       . $cidStr . $uParam,
            ],
          ],
          [
            'text'    => '📂 Detail BAI',
            'web_app' => [
              'url' => 'https://efidi.montaklo.id/bai_all.php?src=telegram_admin&cid='
                       . $cidStr . $uParam,
            ],
          ],
        ],
      ],
    ];

    try {
      if ($screenshot) {
        if ($captionLen <= TELEGRAM_CAPTION_LIMIT) {
          ap_tg_send_photo($cidStr, $screenshot, $txt, [
            'disable_web_page_preview' => true,
            'reply_markup'             => json_encode($inlineKeyboard, JSON_UNESCAPED_SLASHES),
          ]);
        } else {
          $shortCaption = "(Detail laporan di pesan berikutnya)";
          ap_tg_send_photo($cidStr, $screenshot, $shortCaption, [
            'disable_web_page_preview' => true,
          ]);

          ap_tg_send_message($cidStr, $txt, [
            'disable_web_page_preview' => true,
            'reply_markup'             => json_encode($inlineKeyboard, JSON_UNESCAPED_SLASHES),
          ]);
        }
      } else {
        ap_tg_send_message($cidStr, $txt, [
          'disable_web_page_preview' => true,
          'reply_markup'             => json_encode($inlineKeyboard, JSON_UNESCAPED_SLASHES),
        ]);
      }
      $sent++;
      plog("[GLOBAL-SUMMARY] sent to chat_id={$cidStr} role={$role}");
    } catch (Throwable $e) {
      $errors++;
      plog('[GLOBAL-SUMMARY] send fail to '.$cidStr.': '.$e->getMessage());
    }
  }

  if ($screenshot && is_file($screenshot)) {
    @unlink($screenshot);
    plog('[SCREEN] screenshot deleted after send: '.$screenshot);
  }

  if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode([
    'ok'             => true,
    'sent'           => $sent,
    'errors'         => $errors,
    'tech_count'     => $totalTech,
    'admin_ta_count' => count($admins),
    'today_orders'   => $todayAllO,
    'today_bai'      => $todayAllB,
    'all_orders'     => $allTotalO,
    'all_bai'        => $allTotalB,
  ]);
}

/* ========= ENTRY POINT ========= */

// Mode CLI
if (PHP_SAPI === 'cli') {
  $force = in_array('--force', $argv ?? [], true);
  run_global_summary($pdo, $force);
  exit;
}

// Mode HTTP
if (isset($_GET['cron']) && $_GET['cron'] === 'summary') {
  $key = (string)($_GET['key'] ?? '');
  if (!hash_equals(CRON_SECRET, $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  $force = isset($_GET['force']) && $_GET['force'] === '1';
  run_global_summary($pdo, $force);
  exit;
}

// Healthcheck biasa
echo 'OK announc_personal';
exit;
