<?php
/**
 * announc_grub.php
 *
 * Laporan HARIAN (TODAY ONLY) ke GRUP TELEGRAM:
 * - Hitung seluruh ORDER & BAI HARI INI (semua TECH)
 * - List rincian ORDER hari ini (NO SC / NO INTERNET)
 * - List BAI hari ini (nama_bai dengan link ke /bot/file/)
 * - Ringkasan aktivitas per TECH HARI INI (ORDER / BAI / OUT)
 * - Kirim ke 1 grup: GROUP_CHAT_ID
 * - Jika bisa, meng-capture halaman dashboard_admin.php sebagai screenshot,
 *   lalu mengirimnya sebagai foto dengan caption = teks laporan.
 *
 * Cara pakai:
 *   CLI : php announc_grub.php [--force]
 *   HTTP: announc_grub.php?cron=group&key=LaporanOrderBaiharianGrub[&force=1]
 *      atau:
 *         announc_grub.php?cron=report&key=LaporanOrderBaiharianGrub[&force=1]
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
  error_log('[ANNOUNC-GRUB] DB not ready; exiting');
  http_response_code(500);
  echo 'DB not ready';
  exit;
}

/* ========= LOG ========= */
function gplog(string $msg): void {
  @file_put_contents(
    __DIR__.'/runtime-announc-grub.log',
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

/* ========= CONST ========= */
// Secret dan jam kirim
if (!defined('CRON_SECRET')) {
  define('CRON_SECRET', 'LaporanOrderBaiharianGrub');
}
if (!defined('REPORT_HHMM')) {
  define('REPORT_HHMM', '19:00'); // jam kirim WIB khusus grup
}
// >>> TAMBAHKAN KONSTAN INI <<<
if (!defined('REPORT_TIMES')) {
  // daftar jam yang diperbolehkan kirim laporan
  define('REPORT_TIMES', ['19:00', '23:59']);
}

// ID grup tujuan
if (!defined('GROUP_CHAT_ID')) {
  // pastikan ini ID grup yang benar (biasanya negatif, contoh: -100xxxxxxxxxx)
  define('GROUP_CHAT_ID', -1001148239410);
}

// URL publik untuk file BAI
if (!defined('PUBLIC_FILE_BASE_URL')) {
  define('PUBLIC_FILE_BASE_URL', 'https://efidi.montaklo.id/bot/file/');
}

// URL dashboard admin untuk screenshot (auto-login via cron_key)
if (!defined('DASHBOARD_URL')) {
  define(
    'DASHBOARD_URL',
    'https://efidi.montaklo.id/dashboard_admin.php?cron_key=efidi_ss_2025_xh3J9F7s_8KpQ2Z0Lm'
  );
}

/**
 * Konfigurasi BROWSERLESS untuk capture dashboard.
 *
 * Viewport (Browserless):
 *   - width  = 1920 px
 *   - height = 900 px
 *
 * Area crop (HASIL AKHIR PNG) – disamakan dengan announc__personal.php:
 *   - x      = 280 px
 *   - y      = 195 px
 *   - width  = 1580 px
 *   - height = 585 px
 */
if (!defined('BROWSERLESS_ENDPOINT')) {
  // endpoint default Browserless region SFO
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

/* ========= DB HELPERS ========= */
function gp_pdo_set_wib(PDO $pdo): void {
  try { $pdo->exec("SET time_zone = '+07:00'"); } catch (Throwable $e) {}
}

function gp_exec_scalar(PDO $pdo, string $sql, array $params = []): int {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return (int)($v ?? 0);
  } catch (Throwable $e) {
    gplog('[DB-ERR] exec_scalar: '.$e->getMessage());
    return 0;
  }
}

/* ========= FORMAT TEKS ========= */
/**
 * Hanya fokus HARI INI (tidak ada All Data).
 */
function gp_header_harian_text(int $todayO, int $todayB): string {
  $outToday = max(0, $todayO - $todayB);
  $jam = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('d M Y, H:i');

  $lines = [
    "<b>📊 EFIDI · DAILY GROUP REPORT</b>",
    "<code>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</code>",
    "• ORDER: <b>{$todayO}</b> | BAI: <b>{$todayB}</b> | OUTSTANDING: <b>{$outToday}</b>",
    "",
    "<b>📅 Generated on:</b> {$jam} WIB",
    "<i>Automated Group Report by Announc efidi.</i>",
  ];
  return implode("\n", $lines);
}

/* ========= TELEGRAM HELPERS ========= */
function gp_tg_request(string $method, array $data = []): array {
  global $API;
  if (function_exists('curl_init')) {
    $ch = curl_init($API.$method);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $data,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 60,
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
        'timeout' => 60,
      ]
    ];
    $ctx  = stream_context_create($opts);
    $resp = @file_get_contents($API.$method, false, $ctx);
    $err  = ($resp === false) ? 'file_get_contents failed' : '';
    $code = 200;
  }
  return [$resp, $err, $code];
}

function gp_tg_send_message($chat_id, string $text, array $extra = []): void {
  $payload = array_merge([
    'chat_id'    => strval($chat_id),
    'text'       => $text,
    'parse_mode' => 'HTML',
  ], $extra);

  [$resp, $err, $code] = gp_tg_request('sendMessage', $payload);
  if ($err || $code >= 400) {
    gplog("[TG] sendMessage error to {$chat_id}: code={$code} err={$err} resp=".substr((string)$resp,0,200));
  }
}

/**
 * Kirim foto ke Telegram dari PATH FILE lokal.
 */
function gp_tg_send_photo($chat_id, string $photoPath, string $caption, array $extra = []): bool {
  global $API;

  if (!is_file($photoPath)) {
    gplog("[TG] photo not found: {$photoPath}, fallback to text");
    gp_tg_send_message($chat_id, $caption, $extra);
    return false;
  }

  if (!function_exists('curl_init')) {
    gplog('[TG] curl not available, fallback to text only');
    gp_tg_send_message($chat_id, $caption, $extra);
    return false;
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
    gplog("[TG] sendPhoto(FILE) error to {$chat_id}: code={$code} err={$err} resp=".substr((string)$resp,0,200));
    // fallback kirim teks saja
    gp_tg_send_message($chat_id, $caption, $extra);
    return false;
  }

  return true;
}

/* ========= BROWSERLESS HELPERS (SAMA KONSEP DENGAN announc__personal.php) ========= */
/**
 * Wrapper sederhana ke Browserless /screenshot
 * - Ukuran viewport default 1920x900 (jangan diubah untuk menjaga komposisi)
 * - $options['width'],['height'] (jika diisi) akan meng-override default di atas
 * - $options['delay'] (detik)          : waitForTimeout
 * - $options['fullPage'] true/false    : pakai fullPage atau hanya viewport
 */
function gp_capture_url_screenshot(string $url, string $outputPath, array $options = []): bool {
  if (!defined('BROWSERLESS_ENDPOINT') || BROWSERLESS_ENDPOINT === '') {
    gplog('[SCREEN] BROWSERLESS_ENDPOINT not defined');
    return false;
  }

  $token = getenv('BROWSERLESS_TOKEN') ?: (defined('BROWSERLESS_TOKEN') ? BROWSERLESS_TOKEN : '');
  if ($token === '' || $token === 'YOUR_BROWSERLESS_TOKEN_HERE') {
    gplog('[SCREEN] BROWSERLESS_TOKEN not set');
    return false;
  }

  $endpoint = rtrim(BROWSERLESS_ENDPOINT, '/') . '?token=' . rawurlencode($token);

  // viewport fix 1920x900 (sama seperti announc__personal.php)
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
  gplog('[SCREEN] Browserless request: ' . $endpoint . ' url=' . $url);

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
    gplog('[SCREEN] Browserless screenshot failed: code=' . $httpCode .
          ' err=' . $errMsg . ' body=' . $body);
    return false;
  }

  if (@file_put_contents($outputPath, $imgData) === false) {
    gplog('[SCREEN] cannot write screenshot file: ' . $outputPath);
    return false;
  }

  gplog('[SCREEN] screenshot saved: ' . $outputPath . ' (bytes=' . strlen($imgData) . ')');
  return true;
}

/**
 * Crop area persegi panjang dari sebuah gambar.
 * - $src : path file sumber (PNG/JPG/WebP)
 * - $dst : path file hasil crop (akan ditimpa bila sudah ada)
 * - $rect: ['x'=>int, 'y'=>int, 'width'=>int, 'height'=>int]
 */
function gp_crop_image_area(string $src, string $dst, array $rect): bool {
  if (!is_file($src)) {
    gplog('[SCREEN] crop_image_area: src not found: ' . $src);
    return false;
  }

  if (!extension_loaded('gd')) {
    gplog('[SCREEN] crop_image_area: GD extension not loaded');
    return false;
  }

  $x = max(0, (int)($rect['x'] ?? 0));
  $y = max(0, (int)($rect['y'] ?? 0));
  $w = max(1, (int)($rect['width'] ?? 1));
  $h = max(1, (int)($rect['height'] ?? 1));

  $info = @getimagesize($src);
  if ($info === false) {
    gplog('[SCREEN] crop_image_area: cannot getimagesize: ' . $src);
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
      // default: coba sebagai PNG
      $im = @imagecreatefrompng($src);
      break;
  }

  if (!$im) {
    gplog('[SCREEN] crop_image_area: cannot create image from ' . $src . ' (mime=' . $mimeType . ')');
    return false;
  }

  $imgW = imagesx($im);
  $imgH = imagesy($im);

  // Pastikan rect masih di dalam gambar
  if ($x >= $imgW || $y >= $imgH) {
    imagedestroy($im);
    gplog('[SCREEN] crop_image_area: rect out of bounds (x=' . $x . ', y=' . $y . ', w=' . $w . ', h=' . $h . ', img=' . $imgW . 'x' . $imgH . ')');
    return false;
  }
  if ($x + $w > $imgW) {
    $w = $imgW - $x;
  }
  if ($y + $h > $imgH) {
    $h = $imgH - $y;
  }

  // Gunakan imagecrop kalau ada, kalau tidak manual
  if (function_exists('imagecrop')) {
    $cropped = @imagecrop($im, [
      'x'      => $x,
      'y'      => $y,
      'width'  => $w,
      'height' => $h,
    ]);
  } else {
    $cropped = imagecreatetruecolor($w, $h);
    // jaga alpha (transparansi PNG)
    imagealphablending($cropped, false);
    imagesavealpha($cropped, true);
    imagecopy($cropped, $im, 0, 0, $x, $y, $w, $h);
  }

  if (!$cropped) {
    imagedestroy($im);
    gplog('[SCREEN] crop_image_area: crop failed');
    return false;
  }

  // Simpan sebagai PNG
  $ok = @imagepng($cropped, $dst);
  imagedestroy($im);
  imagedestroy($cropped);

  if (!$ok) {
    gplog('[SCREEN] crop_image_area: cannot save cropped file: ' . $dst);
    return false;
  }

  return true;
}

/**
 * Ambil screenshot dashboard via Browserless.
 * - Viewport 1920x900 (tidak fullPage)
 * - Dipotong di area kartu + chart (pakai konstanta DASHBOARD_CLIP_*)
 * - Hasil akhir ukuran sama dengan announc__personal.php (1580x585).
 */
function gp_capture_dashboard_screenshot(string $url): ?string {
  if (!defined('BROWSERLESS_ENDPOINT') || BROWSERLESS_ENDPOINT === '') {
    gplog('[SCREEN] BROWSERLESS_ENDPOINT not defined, skip screenshot');
    return null;
  }
  if (!defined('BROWSERLESS_TOKEN') || BROWSERLESS_TOKEN === '' ||
      BROWSERLESS_TOKEN === 'YOUR_BROWSERLESS_TOKEN_HERE') {
    gplog('[SCREEN] BROWSERLESS_TOKEN not set, skip screenshot');
    return null;
  }

  $dir = __DIR__ . '/runtime-screens';
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }

  // file full & file hasil crop (khusus GRUP)
  $fileFull    = $dir . '/dashboard-grup-full-' . date('Ymd-His') . '.png';
  $fileCropped = $dir . '/dashboard-grup-'      . date('Ymd-His') . '.png';

  // anti-cache
  $sep        = (strpos($url, '?') !== false) ? '&' : '?';
  $urlNoCache = $url . $sep . 'snap_ts=' . time();

  $ok = gp_capture_url_screenshot(
    $urlNoCache,
    $fileFull,
    [
      'deviceScaleFactor'=> 1,
      'delay'            => 10,
      'fullPage'         => false,
    ]
  );

  if (!$ok || !is_file($fileFull)) {
    gplog('[SCREEN] gp_capture_url_screenshot failed');
    return null;
  }

  // Crop ke area kartu + chart sesuai CLIP (SAMA dengan announc__personal.php)
  $rect = [
    'x'      => DASHBOARD_CLIP_X,
    'y'      => DASHBOARD_CLIP_Y,
    'width'  => DASHBOARD_CLIP_WIDTH,
    'height' => DASHBOARD_CLIP_HEIGHT,
  ];

  if (gp_crop_image_area($fileFull, $fileCropped, $rect)) {
    @unlink($fileFull); // hapus full, simpan cuma hasil crop
    gplog('[SCREEN] cropped screenshot saved: ' . $fileCropped);
    return $fileCropped;
  }

  // Kalau crop gagal, pakai full screenshot sebagai fallback
  gplog('[SCREEN] gp_crop_image_area failed, using full screenshot');
  return $fileFull;
}

function run_group_daily_report(PDO $pdo, bool $force = false): void {
  gp_pdo_set_wib($pdo);

  $nowWIB = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
  $hhmm   = $nowWIB->format('H:i');

  // Daftar jam yang diperbolehkan kirim
  $allowedTimes = defined('REPORT_TIMES') ? REPORT_TIMES : [REPORT_HHMM];

  // Gate waktu kirim
  if (!$force && !in_array($hhmm, $allowedTimes, true)) {
    if (PHP_SAPI !== 'cli') {
      header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
      'ok'   => true,
      'skip' => 'not_target_time',
      'want' => implode(',', $allowedTimes),
      'now'  => $nowWIB->format('c'),
    ]);
    return;
  }


  gplog("GROUP-DAILY start want=".REPORT_HHMM." now=".$nowWIB->format('H:i'));

  // ===== TOTAL HARI INI (semua user) =====
  $todayO = gp_exec_scalar($pdo,
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
  );
  $todayB = gp_exec_scalar($pdo,
    "SELECT COUNT(*) FROM bai WHERE DATE(date_received) = CURDATE()"
  );

  // ===== DETAIL GLOBAL HARI INI (ORDER & BAI) =====
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
    gplog('[DB-ERR] todayOrders: '.$e->getMessage());
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
    gplog('[DB-ERR] todayBai: '.$e->getMessage());
  }

  // ===== AKTIVITAS PER TECH HARI INI =====
  $qTech = $pdo->query("
    SELECT nama, username, chat_id
    FROM akses_login
    WHERE role = 'tech' AND status = 'active' AND chat_id > 0
  ");
  $techs = $qTech ? $qTech->fetchAll(PDO::FETCH_ASSOC) : [];

  $perTech     = [];   // HANYA teknisi yang ada aktivitas
  $reportedCnt = 0;    // berapa teknisi yang aktif hari ini

  foreach ($techs as $t) {
    $cid  = (string)($t['chat_id'] ?? '');
    if ($cid === '') continue;

    $nama = (string)($t['nama'] ?? '');
    $user = (string)($t['username'] ?? '');

    $todayO_user = gp_exec_scalar(
      $pdo,
      "SELECT COUNT(*) FROM orders
       WHERE user_id = ? AND DATE(created_at) = CURDATE()",
      [$cid]
    );
    $todayB_user = gp_exec_scalar(
      $pdo,
      "SELECT COUNT(*) FROM bai
       WHERE user_id = ? AND DATE(date_received) = CURDATE()",
      [$cid]
    );

    $hasActivity = ($todayO_user + $todayB_user) > 0;

    if ($hasActivity) {
      $reportedCnt++;
      $perTech[] = [
        'nama'     => $nama,
        'username' => $user,
        'todayO'   => $todayO_user,
        'todayB'   => $todayB_user,
      ];
    }
  }

  // ===== SUSUN TEKS (HARIAN SAJA) =====
  // Header + ringkasan
  $txt  = gp_header_harian_text($todayO, $todayB) . "\n";


  // ---- REKAP AKTIVITAS TEKNISI HARI INI ----
  $totalTech = count($techs);        // semua teknisi terdaftar
  $activeCnt = count($perTech);      // hanya yang ada aktivitas

  $txt .= "\n<b>🧑‍🔧 TECHNICIAN PERFORMANCE SUMMARY</b>\n";

  if ($totalTech > 0) {
    $txt .= "✅ <b>{$activeCnt}</b> tech aktif dari "
          . "<b>{$totalTech}</b> tech terdaftar.\n\n";
  } else {
    $txt .= "<i>Belum ada teknisi yang terdaftar di sistem.</i>\n\n";
  }

  if (!$perTech) {
    $txt .= "• <i>Belum terdapat aktivitas ORDER maupun BAI yang dilaporkan "
          . "oleh teknisi pada hari ini.</i>\n";
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
        "• %s\n   ORDER: <b>%d</b> | BAI: <b>%d</b> | OUTSTANDING: <b>%d</b>\n",
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

  // ===== CAPTURE DASHBOARD & KIRIM KE GRUP =====
  $screenshot = gp_capture_dashboard_screenshot(DASHBOARD_URL);

  if ($screenshot) {
    // Kalau teks masih muat sebagai caption (<= 1024) -> foto + caption full
    if ($captionLen <= TELEGRAM_CAPTION_LIMIT) {
      gp_tg_send_photo(GROUP_CHAT_ID, $screenshot, $txt, [
        'disable_web_page_preview' => true,
      ]);
    } else {
      // Teks terlalu panjang untuk caption:
      // 1) Kirim foto dulu dengan caption pendek
      $shortCaption = "(Detail laporan di pesan berikutnya)";
      gp_tg_send_photo(GROUP_CHAT_ID, $screenshot, $shortCaption, [
        'disable_web_page_preview' => true,
      ]);

      // 2) Kirim teks FULL sebagai pesan terpisah
      gp_tg_send_message(GROUP_CHAT_ID, $txt, [
        'disable_web_page_preview' => true,
      ]);
    }

    // Setelah dipakai kirim, hapus file screenshot
    if (is_file($screenshot)) {
      @unlink($screenshot);
      gplog('[SCREEN] screenshot deleted after send: '.$screenshot);
    }
  } else {
    // Kalau screenshot gagal, kirim teks saja
    gp_tg_send_message(GROUP_CHAT_ID, $txt, [
      'disable_web_page_preview' => true,
    ]);
  }
}

/* ========= ENTRY POINT ========= */

// Mode CLI
if (PHP_SAPI === 'cli') {
  $force = in_array('--force', $argv ?? [], true);
  run_group_daily_report($pdo, $force);
  exit;
}

// Mode HTTP (menerima cron=group ATAU cron=report)
if (isset($_GET['cron']) && in_array($_GET['cron'], ['group','report'], true)) {
  $key = (string)($_GET['key'] ?? '');
  if (!hash_equals(CRON_SECRET, $key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  $force = isset($_GET['force']) && $_GET['force'] === '1';
  run_group_daily_report($pdo, $force);
  exit;
}

// Healthcheck biasa
echo 'OK announc_grub';
exit;
