<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';              // $pdo, flash(), csrf_verify()
require_once __DIR__ . '/redirect_helper.php';   // redirect()
require_once __DIR__ . '/telegram/telegram_config.php'; // BOT_TOKEN

function back_with(string $type, string $msg): void {
  flash($type, $msg);
  redirect('panduan.php');
  exit;
}

try { csrf_verify($_POST['csrf'] ?? ''); } catch (Throwable $e) {
  back_with('err', 'Sesi kadaluarsa. Silakan muat ulang halaman.');
}

$nama  = trim((string)($_POST['nama'] ?? ''));
$uname = ltrim(trim((string)($_POST['username'] ?? '')), '@');
$pesan = trim((string)($_POST['pesan'] ?? ''));
$chat_id_hint = trim((string)($_POST['chat_id'] ?? ''));

if ($nama === '' || $uname === '' || $pesan === '') {
  back_with('err', 'Lengkapi semua field.');
}

if (!defined('BOT_TOKEN') || BOT_TOKEN === '') {
  back_with('err', 'BOT_TOKEN belum diset. Cek telegram/telegram_config.php');
}

/* Ambil SEMUA admin yang punya chat_id */
$targets = [];
try {
  $sql = "SELECT DISTINCT chat_id
            FROM akses_login
           WHERE LOWER(role)='admin'
             AND chat_id IS NOT NULL
             AND chat_id<>''";
  $st = $pdo->query($sql);
  $targets = array_map(fn($r)=> (string)$r['chat_id'], $st->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
  // biarkan $targets kosong jika gagal
}

$targets = array_values(array_unique($targets)); // dedup

if (empty($targets)) {
  back_with('err', 'Tidak ada admin dengan chat_id tersimpan. Minta admin /start bot dulu agar chat_id tercatat.');
}

/* Susun pesan (tanpa parse_mode agar aman karakter khusus) */
$ip = $_SERVER['REMOTE_ADDR'] ?? '-';
$ts = date('Y-m-d H:i:s');
$text =
    "📨 Pesan dari Pengguna Akun efidi.\n\n"
    . "📘 Data Pengguna:\n"
    . "• Nama Lengkap        : <b>{$nama}</b>\n"
    . "• Username            : <code>@{$uname}</code>\n"
    . "• Waktu Pengajuan     : <code>{$ts}</code>\n"
    . "• Alamat IP           : <code>{$ip}</code>\n\n"
    . "🖋️ <b>Keterangan Tambahan</b>\n"
    . "{$pesan}\n\n"
    . "Permohonan ini diajukan oleh pengguna Akun efidi. untuk ditindak lanjuti\n"
    . "Terima kasih ";


$api = 'https://api.telegram.org/bot'.BOT_TOKEN.'/sendMessage';

$ok = 0; $fail = 0;
foreach ($targets as $cid) {
  $payload = [
    'chat_id' => $cid,
    'text'    => $text,
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true
  ];
  $ch = curl_init($api);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if (!$err && $code === 200) $ok++; else $fail++;
}

$summary = "Terkirim ke admin: {$ok}";
if ($fail > 0) $summary .= " | Gagal: {$fail}";

if ($ok > 0) back_with('ok', "Pesan terkirim. {$summary}");
else back_with('err', "Gagal mengirim pesan. {$summary}");
