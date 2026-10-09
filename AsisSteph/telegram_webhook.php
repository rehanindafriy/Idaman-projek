<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/* ============ Logging dasar ============ */
@ini_set('log_errors', '1');
@ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
@ini_set('error_log', LOG_FILE);

function tlog($m, $ctx = []) {
  $line = date('c') . ' ' . $m;
  if (!empty($ctx)) $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  @file_put_contents(LOG_FILE, $line . "\n", FILE_APPEND);
}

/* ============ Validasi config minimum ============ */
if (!defined('BOT_TOKEN') || BOT_TOKEN === '') { http_response_code(500); echo 'BOT_TOKEN kosong'; exit; }

/* ============ Helpers Telegram API ============ */
function tg_api($method, array $data) {
  $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/{$method}";
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 60,
  ]);
  $res = curl_exec($ch);
  $rc  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  if ($res === false || $rc >= 400) {
    tlog('tg_api_error', ['m'=>$method, 'rc'=>$rc, 'err'=>curl_error($ch)]);
  }
  curl_close($ch);
  return $res ? (json_decode($res, true) ?: []) : [];
}

function tg_send_message($chat_id, $text, $reply_markup = null, $reply_to = null) {
  $data = [
    'chat_id' => $chat_id,
    'text'    => $text,
    'disable_web_page_preview' => true,
  ];
  if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
  if ($reply_to)     $data['reply_to_message_id'] = $reply_to;
  tg_api('sendMessage', $data);
}

function tg_edit_message_text($chat_id, $message_id, $text, $reply_markup = null) {
  $data = [
    'chat_id'    => $chat_id,
    'message_id' => $message_id,
    'text'       => $text,
    'disable_web_page_preview' => true,
  ];
  if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
  $r = tg_api('editMessageText', $data);
  return !empty($r['ok']);
}

function tg_answer_callback($cbq_id) {
  tg_api('answerCallbackQuery', ['callback_query_id' => $cbq_id]);
}

function tg_send_photo($chat_id, $abs_path, $caption = null) {
  if (!is_file($abs_path)) { tlog('photo_missing', ['path'=>$abs_path]); return; }
  $data = [
    'chat_id' => $chat_id,
    'photo'   => new CURLFile($abs_path),
  ];
  if ($caption) $data['caption'] = $caption;
  tg_api('sendPhoto', $data);
}

/* ============ Struktur Menu (sama seperti Python) ============ */
$MENU = [
  'K3'   => ['APD', 'SAFETY RIDING', "do and don't", 'Listrik', 'Ketinggian', 'ruangan terbatas', 'bekerja di jalan'],
  'IODN' => ['FTM', 'TIANG', 'DUCT & POLONGAN', 'ODC', 'ODP', 'STANDART INSTALASI FTTH'],
  'QC'   => ['QC FTM', 'QC ODC', 'QC AKSESORIS', 'QC ODP'],
  'BPSM' => [],
];

function kb_main_menu() {
  return [
    'inline_keyboard' => [
      [ ['text'=>'K3',   'callback_data'=>'menu_K3'] ],
      [ ['text'=>'IODN', 'callback_data'=>'menu_IODN'] ],
      [ ['text'=>'QC',   'callback_data'=>'menu_QC'] ],
      [ ['text'=>'BPSM', 'callback_data'=>'menu_BPSM'] ],
    ]
  ];
}

function kb_submenu($group, $items) {
  $rows = [];
  foreach ($items as $m) {
    $rows[] = [ ['text'=>$m, 'callback_data'=>"materi_{$group}_{$m}"] ];
  }
  $rows[] = [ ['text'=>'⬅️ Back', 'callback_data'=>'back_to_groups'] ];
  return ['inline_keyboard' => $rows];
}

/* ============ Health check GET ============ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true,'ping'=>true,'time'=>date('c')], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ============ Secret check (opsional tapi disarankan) ============ */
if (defined('TELEGRAM_SECRET') && TELEGRAM_SECRET !== '') {
  $qs_secret  = isset($_GET['s']) ? $_GET['s'] : '';
  $hdr_secret = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
  if ($qs_secret !== TELEGRAM_SECRET || $hdr_secret !== TELEGRAM_SECRET) {
    tlog('secret_mismatch', ['qs'=>($qs_secret!==''), 'hdr'=>($hdr_secret!=='')]);
    http_response_code(204); // diamkan
    exit;
  }
}

/* ============ Ambil update ============ */
$raw = file_get_contents('php://input') ?: '';
$upd = $raw !== '' ? json_decode($raw, true) : null;
if (!$upd) { http_response_code(204); exit; }
tlog('hit', ['has_message'=>isset($upd['message']), 'has_cbq'=>isset($upd['callback_query'])]);

/* ============ Handler ============ */
try {
  // /start
  if (isset($upd['message'])) {
    $msg = $upd['message'];
    $text = isset($msg['text']) ? trim($msg['text']) : '';
    $chat_id = isset($msg['chat']['id']) ? $msg['chat']['id'] : null;
    if (!$chat_id) { http_response_code(204); exit; }
    if ($text !== '' && preg_match('~^/start(?:@\w+)?~', $text)) {
      tg_send_message($chat_id, 'Pilih Kelompok Materi:', kb_main_menu(), isset($msg['message_id']) ? $msg['message_id'] : null);
    }
  }
  // callback_query
  elseif (isset($upd['callback_query'])) {
    $cbq = $upd['callback_query'];
    $data = isset($cbq['data']) ? $cbq['data'] : '';
    $msg  = isset($cbq['message']) ? $cbq['message'] : [];
    $chat_id = isset($msg['chat']['id']) ? $msg['chat']['id'] : null;
    $message_id = isset($msg['message_id']) ? $msg['message_id'] : null;
    tg_answer_callback($cbq['id']);

    if (!$chat_id) { http_response_code(204); exit; }

    if (strpos($data, 'menu_') === 0) {
      $group = substr($data, 5);
      if ($group === 'BPSM') {
        $folder = MATERI_BASE . '/BPSM';
        if (is_dir($folder)) {
          $list = scandir($folder);
          if (!$list) $list = [];
          // filter file gambar
          $files = [];
          foreach ($list as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $folder . '/' . $f;
            if (is_file($full)) $files[] = $f;
          }
          sort($files, SORT_NATURAL|SORT_FLAG_CASE);
          foreach ($files as $f) {
            tg_send_photo($chat_id, $folder . '/' . $f);
          }
          tg_send_message($chat_id, "✅ Materi BPSM telah dikirimkan.");
        } else {
          tg_send_message($chat_id, "Belum ada materi untuk BPSM.");
        }
        tg_send_message($chat_id, "⬅️ Kembali ke menu utama:", kb_main_menu());
      } else {
        $items = isset($GLOBALS['MENU'][$group]) ? $GLOBALS['MENU'][$group] : [];
        $kb = kb_submenu($group, $items);
        $ok = false;
        if ($message_id) {
          $ok = tg_edit_message_text($chat_id, $message_id, "Pilih materi {$group}:", $kb);
        }
        if (!$ok) tg_send_message($chat_id, "Pilih materi {$group}:", $kb);
      }
    }
    elseif (strpos($data, 'materi_') === 0) {
      // materi_{GROUP}_{MATERI}
      $parts = explode('_', $data, 3);
      $group  = isset($parts[1]) ? $parts[1] : '';
      $materi = isset($parts[2]) ? $parts[2] : '';
      $folder = MATERI_BASE . '/' . $group . '/' . $materi;

      if (is_dir($folder)) {
        $list = scandir($folder);
        if (!$list) $list = [];
        $files = [];
        foreach ($list as $f) {
          if ($f === '.' || $f === '..') continue;
          $full = $folder . '/' . $f;
          if (is_file($full)) $files[] = $f;
        }
        sort($files, SORT_NATURAL|SORT_FLAG_CASE);

        foreach ($files as $f) {
          tg_send_photo($chat_id, $folder . '/' . $f);
        }
        tg_send_message($chat_id, "✅ Materi {$materi} telah dikirimkan.");

        $items = isset($GLOBALS['MENU'][$group]) ? $GLOBALS['MENU'][$group] : [];
        $kb = kb_submenu($group, $items);
        tg_send_message($chat_id, "Pilih materi {$group}:", $kb);
      } else {
        tg_send_message($chat_id, "Belum ada materi untuk {$materi}");
      }
    }
    elseif ($data === 'back_to_groups') {
      $ok = false;
      if ($message_id) {
        $ok = tg_edit_message_text($chat_id, $message_id, "Pilih Kelompok Materi:", kb_main_menu());
      }
      if (!$ok) tg_send_message($chat_id, "Pilih Kelompok Materi:", kb_main_menu());
    }
  }

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  tlog('handler_exception', ['err'=>$e->getMessage()]);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
