<?php
declare(strict_types=1);
require_once __DIR__.'/koneksi.php';
// ===== [TAMBAHAN] Session + helper Telegram =====
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/telegram/telegram_config.php'; // BOT_TOKEN

if (!function_exists('tg_log')) {
  function tg_log(string $msg): void {
    @file_put_contents(__DIR__.'/runtime-telegram.log',
      date('c').' '.$msg."\n", FILE_APPEND);
  }
}

if (!function_exists('tg_send_text')) {
  function tg_send_text(string $chat_id, string $text): bool {
    if (!defined('BOT_TOKEN') || BOT_TOKEN === '') {
      tg_log('BOT_TOKEN kosong, batal kirim.');
      return false;
    }
    $api = 'https://api.telegram.org/bot'.BOT_TOKEN.'/sendMessage';
    $ch  = curl_init($api);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => [
        'chat_id' => $chat_id,
        'text'    => $text,
        'disable_web_page_preview' => true,
      ],
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 20,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $rc   = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($rc !== 200 || $body === false) {
      tg_log("sendMessage FAIL rc=$rc err=".($err?:'-')." chat_id=$chat_id body=".substr((string)$body,0,200));
      return false;
    }
    tg_log("sendMessage OK chat_id=$chat_id");
    return true;
  }
}

// semula: function notify_access_granted(?string $chat_id, ?string $username, ?string $adminName=null): bool
function notify_access_granted(string|int|null $chat_id, string|int|null $username, ?string $adminName=null): bool {
  $chat_id  = trim((string)$chat_id);           // pastikan string
  $username = ltrim(trim((string)$username), '@');
  if ($chat_id === '') return false;
  $by  = $adminName ? " oleh {$adminName}" : "";
  $usr = $username ? " (@{$username})" : "";
  $text = "✅ Akses Akun efidi Diaktifkan{$usr}\n".
          "Akun Anda telah diaktifkan oleh admin efidi.\n".
          "Silakan login dan gunakan fitur yang tersedia. Terima kasih.";
  return tg_send_text($chat_id, $text);
}
// === Tambahan: kirim notif saat status PENDING ===
function notify_access_pending(string|int|null $chat_id, string|int|null $username, ?string $adminName=null): bool {
  $chat_id  = trim((string)$chat_id);
  $username = ltrim(trim((string)$username), '@');
  if ($chat_id === '') return false;
  $by  = $adminName ? " oleh {$adminName}" : "";
  $usr = $username ? " (@{$username})" : "";
  $text = "⏳ Status Akses Akun efidi : PENDING{$usr}\n"
        . "Akun Anda sedang menunggu persetujuan oleh admin efidi.\n"
        . "Anda akan menerima pemberitahuan lagi ketika akses sudah aktif.";
  return tg_send_text($chat_id, $text);
}

// === Tambahan: kirim notif saat status SUSPENDED ===
function notify_access_suspended(string|int|null $chat_id, string|int|null $username, ?string $adminName=null): bool {
  $chat_id  = trim((string)$chat_id);
  $username = ltrim(trim((string)$username), '@');
  if ($chat_id === '') return false;
  $by  = $adminName ? " oleh {$adminName}" : "";
  $usr = $username ? " (@{$username})" : "";
  $text = "⚠️ Status Akses Akun efidi : DITANGGUHKAN{$usr}\n"
        . "Akun Anda ditangguhkan oleh admin efidi.\n"
        . "Silakan hubungi admin bila memerlukan klarifikasi.";
  return tg_send_text($chat_id, $text);
}

// ===== [AKHIR TAMBAHAN] =====

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

function out($a,$c=200){ http_response_code($c); echo json_encode($a); exit; }
function post($k,$d=''){ return $_POST[$k] ?? $d; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['ok'=>false,'msg'=>'Method not allowed'],405);

$action = (string)post('action','');

switch ($action) {
  case 'get_state': {
    $id = (int)post('id',0);
    if ($id<=0) out(['ok'=>false,'msg'=>'ID tidak valid'],400);
    $st = $pdo->prepare("SELECT role, status FROM akses_login WHERE id_akses=?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) out(['ok'=>false,'msg'=>'Data tidak ditemukan'],404);
    out(['ok'=>true,'role'=>strtolower($row['role']??''),'status'=>strtolower($row['status']??'')]);
  }

   case 'save_role_status': {
  $id     = (int)post('id',0);
  $role   = strtolower(trim((string)post('role','')));
  $status = strtolower(trim((string)post('status','')));

  if ($id<=0) out(['ok'=>false,'msg'=>'ID kosong'],400);
  if (!in_array($role,   ['admin','ta','tech','monika',''], true)) out(['ok'=>false,'msg'=>'Role tidak valid'],422);
  if (!in_array($status, ['active','pending','suspended',''], true)) out(['ok'=>false,'msg'=>'Status tidak valid'],422);


  try {
    // --- kondisi sebelum update (untuk pembanding)
    $st0 = $pdo->prepare("SELECT username, chat_id, role, status FROM akses_login WHERE id_akses=?");
    $st0->execute([$id]);
    $before = $st0->fetch(PDO::FETCH_ASSOC) ?: ['username'=>'','chat_id'=>'','role'=>'','status'=>''];

    // --- UPDATE ke DB
    $st = $pdo->prepare("UPDATE akses_login SET role=?, status=? WHERE id_akses=?");
    $st->execute([$role, $status, $id]);

    // --- kondisi sesudah update
    $st2 = $pdo->prepare("SELECT username, chat_id, role, status FROM akses_login WHERE id_akses=?");
    $st2->execute([$id]);
    $after = $st2->fetch(PDO::FETCH_ASSOC) ?: ['username'=>'','chat_id'=>'','role'=>'','status'=>''];

    // --- LOGIKA NOTIF
    $notified = false;
    $notify_reason = 'not_applicable';

    $prevRole   = strtolower((string)$before['role']);
    $prevStatus = strtolower((string)$before['status']);
    $newRole    = strtolower((string)$after ['role']);
    $newStatus  = strtolower((string)$after ['status']);

    $validRole       = in_array($newRole, ['admin','ta','tech'], true);
    $roleJustGranted = ($prevRole === '' || $prevRole === null) && $validRole; // dari kosong -> valid
    $becameActive    = ($prevStatus !== 'active'    && $newStatus === 'active');
    // >>> Tambahan: deteksi ke pending / suspended
    $becamePending   = ($prevStatus !== 'pending'   && $newStatus === 'pending');
    $becameSuspended = ($prevStatus !== 'suspended' && $newStatus === 'suspended');

    $adminName = $_SESSION['nama'] ?? ($_SESSION['user']['name'] ?? null);

    // Normalisasi untuk semua percabangan
    $cid = trim((string)($after['chat_id'] ?? ''));
    $usr = trim((string)($after['username'] ?? ''));

    // --- Notif existing: role baru diberikan ATAU menjadi active ---
    $shouldNotify = $roleJustGranted || ($validRole && $becameActive);
    if ($shouldNotify) {
      if ($cid !== '' && preg_match('/^-?\d{5,}$/', $cid)) {
        try {
          $notified = notify_access_granted($cid, $usr, $adminName);
          $notify_reason = $notified ? 'sent' : 'tg_send_failed';
        } catch (Throwable $te) {
          $notified = false;
          $notify_reason = 'tg_exception';
          if (function_exists('tg_log')) tg_log('notify exception: '.$te->getMessage());
        }
      } else {
        $notify_reason = 'invalid_chat_id';
        if (function_exists('tg_log')) tg_log("skip notify(active): invalid chat_id=".json_encode($after['chat_id'] ?? null)." for id=$id");
      }
    }

    // --- Tambahan: kirim notif bila status berubah ke pending / suspended (jika belum notif sebelumnya) ---
    if (!$notified && ($becamePending || $becameSuspended)) {
      if ($cid !== '' && preg_match('/^-?\d{5,}$/', $cid)) {
        try {
          if ($becamePending) {
            $notified = notify_access_pending($cid, $usr, $adminName);
            $notify_reason = $notified ? 'sent_pending' : 'tg_send_failed';
          } elseif ($becameSuspended) {
            $notified = notify_access_suspended($cid, $usr, $adminName);
            $notify_reason = $notified ? 'sent_suspended' : 'tg_send_failed';
          }
        } catch (Throwable $te) {
          $notified = false;
          $notify_reason = 'tg_exception';
          if (function_exists('tg_log')) tg_log('notify exception: '.$te->getMessage());
        }
      } else {
        $notify_reason = 'invalid_chat_id';
        if (function_exists('tg_log')) tg_log("skip notify(pending/suspended): invalid chat_id=".json_encode($after['chat_id'] ?? null)." for id=$id");
      }
    }

    out([
      'ok'            => true,
      'role'          => $role,
      'status'        => $status,
      'notified_user' => $notified,
      'notify_reason' => $notify_reason
    ]);
  } catch (Throwable $e) {
    out(['ok'=>false,'msg'=>'Server error: '.$e->getMessage()],500);
  }
}



  case 'publish_code':
  case 'renew_code': {
    // supaya tombol publish tidak error di UI
    $code = (string)random_int(1000000, 9999999);
    out(['ok'=>true,'code'=>$code]);
  }

  default:
    out(['ok'=>false,'msg'=>'Unknown action'],400);
}
