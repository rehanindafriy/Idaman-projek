<?php
// chat_admin.php
// Mini App "Chat Admin" untuk BOT FINDER EFIDI

declare(strict_types=1);

$ROOT = __DIR__;
require_once $ROOT . '/koneksi.php';

// cari telegram_config.php (sama pola dgn finder_webhook)
$cfgCandidates = [
  $ROOT . '/telegram/telegram_config.php',
  $ROOT . '/telegram_config.php',
];
foreach ($cfgCandidates as $p) {
  if (is_file($p)) {
    require_once $p;
    break;
  }
}

// pakai BOT_TOKEN (sementara)
if (defined('BOT_TOKEN') && BOT_TOKEN !== '') {
  $BOT_TOKEN = BOT_TOKEN;
} elseif (defined('BOT_TOKEN') && BOT_TOKEN !== '') {
  $BOT_TOKEN = BOT_TOKEN;
} else {
  $BOT_TOKEN = '';
}

if (!function_exists('e')) {
  function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

// --- Ambil param dari GET untuk prefilling ---
$tgUsername = trim((string)($_GET['u']   ?? ''));
$tgChatId   = (int)($_GET['cid'] ?? 0);
$src        = trim((string)($_GET['src'] ?? ''));

// --- Handle submit form ---
$successMsg = '';
$errorMsg   = '';

$namaLengkap   = '';
$usernameEfidi = '';
$pesan         = '';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
          && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $namaLengkap   = trim((string)($_POST['nama_lengkap']   ?? ''));
  $usernameEfidi = trim((string)($_POST['username']       ?? ''));
  $pesan         = trim((string)($_POST['pesan']          ?? ''));
  $cid           = (int)($_POST['cid'] ?? 0);
  $tgUserHidden  = trim((string)($_POST['tg_username'] ?? ''));
  $origin        = trim((string)($_POST['origin'] ?? ''));

  if ($namaLengkap === '' || $usernameEfidi === '' || $pesan === '') {
    $errorMsg = 'Semua kolom wajib diisi.';
  } else {
    // Siapkan teks pesan ke admin
        // Siapkan teks pesan ke admin
    if ($origin === 'pending_page') {
      // FORMAT KHUSUS: dari pending_page (Akun efidi)

      // waktu dan IP
      $now = date('Y-m-d H:i:s');

      $ip = '';
      if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
      } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = (string)$_SERVER['REMOTE_ADDR'];
      }

      $lines = [];
      $lines[] = "📨 Pesan dari Pengguna Akun efidi.";
      $lines[] = "";
      $lines[] = "📘 Data Pengguna:";
      $lines[] = "• Nama Lengkap    : <b>" . e($namaLengkap) . "</b>";
      $lines[] = "• Username        : <code>" . e($usernameEfidi) . "</code>";

    
      $lines[] = "• Waktu Pengajuan : <code>{$now}</code>";
      if ($ip !== '') {
        $lines[] = "• Alamat IP       : <code>" . e($ip) . "</code>";
      }

      $lines[] = "";
      $lines[] = "🖊 <b>Keterangan Tambahan</b>";
      $lines[] = e($pesan);
      $lines[] = "";
      $lines[] = "Permohonan ini diajukan oleh pengguna Akun efidi. untuk ditindak lanjuti";
      $lines[] = "Terima kasih";

    } else {
      // FORMAT LAMA: JANGAN DIUBAH (untuk Chat Admin Finder, dll)

      $lines = [];
      $lines[] = "📨 <b>Pesan Baru dari Pengguna Finder efidi.</b>";
      $lines[] = "──────────────────────────";
      $lines[] = "";
      $lines[] = "📌 Data Pengguna:";
      $lines[] = "• Nama Lengkap : <b>" . e($namaLengkap) . "</b>";
      $lines[] = "•  Username  : <code>" . e($usernameEfidi) . "</code>";

      if ($tgUserHidden !== '') {
        $lines[] = "• Akun Telegram : @" . e($tgUserHidden);
      }

      if ($cid > 0) {
        $lines[] = "• Chat ID Telegram : <code>" . $cid . "</code>";
      }
      $lines[] = "";
      $lines[] = "Pesan:";
      $lines[] = '<i>' . e($pesan) . '</i>';
      $lines[] = "";
      $lines[] = "📝 Pesan ini disampaikan oleh pengguna melalui layanan Finder efidi. untuk ditindak lanjuti.";
    }

    $textToAdmin = implode("\n", $lines);


    // Kirim ke semua admin (role='admin', status='active', chat_id > 0)
    $sentCount = 0;

    if ($BOT_TOKEN !== '') {
      $api = 'https://api.telegram.org/bot' . $BOT_TOKEN . '/sendMessage';

      $sqlAdmins = "
        SELECT nama, username, chat_id
        FROM akses_login
        WHERE role = 'admin'
          AND status = 'active'
          AND chat_id IS NOT NULL
          AND chat_id <> 0
      ";
      $st = $pdo->query($sqlAdmins);
      $admins = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

      foreach ($admins as $a) {
        $adminChatId = (int)$a['chat_id'];
        if ($adminChatId <= 0) continue;

        $payload = [
          'chat_id'    => (string)$adminChatId,
          'text'       => $textToAdmin,
          'parse_mode' => 'HTML',
        ];

        // kirim via curl / file_get_contents
        if (function_exists('curl_init')) {
          $ch = curl_init($api);
          curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
          ]);
          $resp = curl_exec($ch);
          curl_close($ch);
        } else {
          $opts = [
            'http' => [
              'method'  => 'POST',
              'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
              'content' => http_build_query($payload),
              'timeout' => 30,
            ],
          ];
          $ctx  = stream_context_create($opts);
          $resp = @file_get_contents($api, false, $ctx);
        }

        $sentCount++;
      }
    }

       if ($sentCount > 0) {
      // TEKS BALASAN – beda tergantung asal (origin)
      if ($origin === 'pending_page') {
        // Chat dikirim dari pending_page.php (Akun efidi)
        $successMsg =
          "Terima kasih atas perhatiannya, {$namaLengkap}!! pesan anda " .
          "telah terkirimkan ke admin, harap menunggu balasan dari admin " .
          "melalui telegram @Announc efidi.";
      } else {
        // Default: Chat Admin Finder, dsb.
        $successMsg =
          "Terima kasih atas perhatiannya, {$namaLengkap}!! pesan anda " .
          "telah terkirimkan ke admin, harap menunggu balasan dari admin " .
          "melalui telegram @Finder efidi.";
      }
    } else {
      $errorMsg = 'Gagal mengirim pesan ke admin. Coba beberapa saat lagi.';
    }

  }

  // respon AJAX (dipakai JS untuk efek Typing)
  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    if ($errorMsg !== '') {
      http_response_code(400);
      echo json_encode([
        'ok'    => false,
        'error' => $errorMsg,
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode([
        'ok'    => true,
        'reply' => $successMsg,
      ], JSON_UNESCAPED_UNICODE);
    }
    exit;
  }

  // fallback non-AJAX
  $prefNama   = $namaLengkap;
  $prefUser   = $usernameEfidi;
  $prefPesan  = $pesan;
} else {
  // default nilai awal dari GET
  $prefNama  = '';
  $prefUser  = ($tgUsername !== '' ? '@' . $tgUsername : '');
  $prefPesan = '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Chat Admin - EFIDI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
  * {
    box-sizing: border-box;
  }

  /* ====================== SHELL KARTU CHAT ADMIN ======================= */
  body{
    margin:0;padding:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    background:#f3f4f6;
  }
  .chat-admin-wrap{
    width:100%;
    padding:8px;                 /* semula 12px */
  }

  .chat-admin-card{
    width:100%;
    max-width:360px;             /* semula 420px → lebih ramping */
    margin:0 auto;
    background:#fff;
    border-radius:18px;
    border:1px solid #e5e7eb;
    box-shadow:0 18px 46px rgba(15,23,42,.22);
    padding:12px 14px 14px;      /* sedikit diperkecil */
  }

  .chat-admin-header{
    display:flex;align-items:center;justify-content:space-between;gap:10px;
  }
  .chat-admin-header-left{
    display:flex;align-items:center;gap:8px;
  }
  .chat-admin-logo{
    width:26px;height:26px;border-radius:999px;
    display:grid;place-items:center;
    overflow:hidden;
  }
  .chat-admin-logo img{
    width:24px;height:24px;object-fit:contain;display:block;
  }
  .chat-admin-title{font-size:15px;font-weight:700;color:#0f172a;}

  /* ========== TOMBOL X: default HIDDEN, muncul pakai class .show ========== */
  .chat-admin-close{
    border:0;background:transparent;
    width:26px;height:26px;border-radius:999px;
    cursor:pointer;font-size:16px;color:#9ca3af;
    display:none;                /* awal: tidak tampil */
    place-items:center;
  }
  .chat-admin-close.show{
    display:grid;                /* tampil setelah bot menjawab */
  }
  .chat-admin-close:hover{background:#f3f4f6;color:#4b5563;}

  .chat-admin-body{margin-top:10px;}
  .chat-admin-note{margin-top:10px;font-size:11px;color:#94a3b8;}

  .chat-admin-form{margin-top:6px;}
  .field{margin-bottom:10px;}

  /* ====================== COPY DARI INDEX (KODEE STYLE) ======================= */

  :root{
    --kodee-bg:#fff;
    --kodee-ink:#0f172a;
    --kodee-line:#e5e7eb;
    --kodee-primary:var(--es-primary,#00A3AD);
  }
  .kodee-input,.kodee-input-sm{
    box-sizing:border-box;width:100%;height:38px;
    border:1px solid var(--kodee-line);border-radius:9px;
    padding:0 10px;
    transition:border-color .15s ease,box-shadow .15s ease;
    background:#fff;
  }
  .kodee-input::placeholder,.kodee-input-sm::placeholder{color:#94a3b8}
  .kodee-input:focus,.kodee-input-sm:focus{
    outline:none;
    border-color:var(--kodee-primary)!important;
    box-shadow:0 0 0 3px rgba(13,110,253,.18)!important;
  }

  /* CHAT AREA + BUBBLES (sama seperti index.php) */
  .kodee-chat{
    padding:12px;
    background:#f8fafc;
    border-top:1px dashed var(--kodee-line);
  }
  .kodee-chat-list{
    max-height:52vh;overflow:auto;
    display:flex;flex-direction:column;gap:10px;
  }
  .msg{display:flex;gap:8px;}
  .msg .bubble{
    max-width:88%;white-space:pre-line;
    padding:10px 12px;border:1px solid var(--kodee-line);
    border-radius:14px;box-shadow:0 3px 10px rgba(2,8,23,.06);
    line-height:1.5;
    background:#fff;
  }
  .msg-user{justify-content:flex-end;}
  .msg-user .bubble{
    background:linear-gradient(180deg,#eef6ff,#fff);
    border-color:#cfe1ff;
    line-height:.9;
    padding:6px 10px;
    font-size:14px;
  }
  .msg-user .bubble .kv{
    display:grid;
    grid-template-columns:78px 1fr;
    column-gap:6px;row-gap:2px;
    margin:0;
  }
  .msg-user .bubble .k{font-weight:700;color:#334155;}
  .msg-user .bubble .v{white-space:pre-line;}

  .msg-bot{justify-content:flex-start;}
  .msg-bot .avatar{
    width:28px;height:28px;border-radius:9px;
    display:grid;place-items:center;
    border:1px solid var(--kodee-line);
    background:#fff;
    flex-shrink:0;
  }
  .msg-bot .avatar .kodee-ava{
    width:28px;height:28px;object-fit:contain;display:block;
  }
  .msg-bot .bubble{
    line-height:1.5;
    padding:10px 12px;
    font-size:13px;
    color:#111827;
  }

  /* Typing bubble */
  .msg-typing .bubble{
    display:inline-flex;align-items:center;gap:8px;color:#64748b;
  }
  .kodee-spinner{
    width:16px;height:16px;border-radius:50%;
    border:3px solid #e2e8f0;border-top-color:var(--kodee-primary);
    animation:kodee-spin 1s linear infinite;
  }
  @keyframes kodee-spin{to{transform:rotate(1turn)}}
  .kodee-dots{display:inline-flex;gap:4px;}
  .kodee-dots i{
    width:6px;height:6px;border-radius:999px;
    background:var(--kodee-primary);
    opacity:.35;animation:kodee-bounce .9s ease-in-out infinite;
  }
  .kodee-dots i:nth-child(2){animation-delay:.15s}
  .kodee-dots i:nth-child(3){animation-delay:.3s}
  @keyframes kodee-bounce{
    0%,80%,100%{transform:translateY(0);opacity:.35}
    40%{transform:translateY(-3px);opacity:1}
  }

  /* Textarea pesan – tinggi fleksibel */
  textarea.kodee-input{
    height:auto !important;
    min-height:96px;
    max-height:280px;
    line-height:1.4;
    padding-top:10px;
    padding-bottom:10px;
    resize:vertical;
  }

  /* ERROR BAR */
  .chat-strip-error{
    margin-bottom:6px;
    padding:7px 9px;border-radius:10px;
    font-size:13px;line-height:1.4;
    background:#fee2e2;color:#b91c1c;
    border:1px solid #fecaca;
  }

  @media (max-width:480px){
    .chat-admin-card{
      max-width:100%;
      border-radius:16px;
      padding:12px 14px 14px;
    }
  }

  /* Tombol kirim – kembali seperti tampilan awal */
  .chat-admin-actions{
    margin-top: 4px;
    display: flex;
    justify-content: flex-end;
  }

  .btn-send{
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    background: #06c5d1;                 /* teal efidi */
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f9fafb;
    font-size: 20px;
    font-family: inherit;                /* ikut font body, bukan default */

    transition:
      transform 0.12s ease,
      box-shadow 0.12s ease,
      filter 0.12s ease;
  }

  .btn-send:hover{
    transform: translateY(-1px);
    filter: brightness(1.03);
  }

  .btn-send:active{
    transform: translateY(0);
  }

  .btn-send:disabled{
    opacity: .7;
    cursor: wait;
    box-shadow: none;
  }
  </style>

  <script>
    // Fungsi tombol X:
    // - Kalau sedang tampilan chat -> balik ke form awal
    // - Kalau tidak (fallback) -> close WebApp / window
    function closeChatAdmin() {
      const chatView = document.getElementById('chatView');
      const chatList = document.getElementById('chatList');
      const form     = document.getElementById('chatForm');
      const note     = document.querySelector('.chat-admin-note');
      const btnClose = document.querySelector('.chat-admin-close');

      // Sedang di mode chat: kembalikan ke halaman form pertama
      if (chatView && !chatView.hidden) {
        chatView.hidden = true;
        if (chatList) chatList.innerHTML = '';    // hapus bubble
        if (form) form.style.display = '';        // tampilkan form lagi
        if (note) note.style.display = '';        // tampilkan catatan lagi
        if (btnClose) btnClose.classList.remove('show'); // sembunyikan X lagi
        return;
      }

      // Fallback kalau entah bagaimana X muncul di mode form
      if (window.Telegram && Telegram.WebApp) {
        Telegram.WebApp.close();
      } else {
        window.close();
      }
    }

    document.addEventListener('DOMContentLoaded', function () {
      const form     = document.getElementById('chatForm');
      const btnSend  = document.getElementById('btnSend');
      const chatView = document.getElementById('chatView');
      const chatList = document.getElementById('chatList');
      const note     = document.querySelector('.chat-admin-note');
      const btnClose = document.querySelector('.chat-admin-close');

      if (!form) return;

      // Pastikan tombol X sembunyi saat awal
      if (btnClose) {
        btnClose.classList.remove('show');
      }

      const inputNama  = form.elements['nama_lengkap'];
      const inputUser  = form.elements['username'];
      const inputPesan = form.elements['pesan'];

      function scrollBottom() {
        if (chatList) chatList.scrollTop = chatList.scrollHeight;
      }

      function clearChat() {
        if (chatList) chatList.innerHTML = '';
      }

      const esc = s => String(s).replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
      }[m]));

      // === Bubble user (Nama / Username / Pesan) mirip index.php ===
      function addSummary(n, u, m) {
        if (!chatList) return;
        const wrap = document.createElement('div');
        wrap.className = 'msg msg-user';
        wrap.innerHTML = `
          <div class="bubble">
            <div class="kv"><span class="k">Nama :</span><span class="v">${n}</span></div>
            <div class="kv"><span class="k">Username :</span><span class="v">${u}</span></div>
            <div class="kv"><span class="k">Pesan :</span><span class="v">${m}</span></div>
          </div>
        `;
        chatList.appendChild(wrap);
        scrollBottom();
      }

      // === Typing bubble (Typing + dots) – 3 detik minimal ===
      function addTyping() {
        if (!chatList) return;
        const wrap = document.createElement('div');
        wrap.className = 'msg msg-bot msg-typing';
        wrap.id = 'msgTyping';
        wrap.innerHTML = `
          <div class="avatar">
            <img src="images/00.png" alt="" class="kodee-ava">
          </div>
          <div class="bubble">
            <span class="kodee-spinner" aria-hidden="true"></span>
            <span class="kodee-typing-text">Typing</span>
            <span class="kodee-dots" aria-hidden="true">
              <i></i><i></i><i></i>
            </span>
          </div>
        `;
        chatList.appendChild(wrap);
        scrollBottom();
      }

      function removeTyping() {
        const el = document.getElementById('msgTyping');
        if (el && el.parentNode) el.parentNode.removeChild(el);
      }

      // === Bubble balasan bot (teks sukses / error) ===
      function addBotBubble(text) {
        if (!chatList) return;
        const wrap = document.createElement('div');
        wrap.className = 'msg msg-bot';
        wrap.innerHTML = `
          <div class="avatar">
            <img src="images/00.png" alt="" class="kodee-ava">
          </div>
          <div class="bubble">${text}</div>
        `;
        chatList.appendChild(wrap);
        scrollBottom();
      }

      form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!form.reportValidity()) return;

        const n = (inputNama.value  || '').trim();
        const u = (inputUser.value  || '').trim();
        const m = (inputPesan.value || '').trim();

        if (!n || !u || !m) return;

        // setiap kali kirim baru, tombol X disembunyi dulu
        if (btnClose) btnClose.classList.remove('show');

        // tampilkan area chat, sembunyikan form + note
        if (chatView) chatView.hidden = false;
        form.style.display = 'none';
        if (note) note.style.display = 'none';

        clearChat();
        addSummary(esc(n), esc(u), esc(m));
        addTyping();

        if (btnSend) btnSend.disabled = true;

        const formData = new FormData(form);
        const startAt  = Date.now(); // untuk delay 3 detik

        function withDelay(cb){
          const elapsed = Date.now() - startAt;
          const remain  = 3000 - elapsed;
          if (remain > 0){
            setTimeout(cb, remain);
          } else {
            cb();
          }
        }

        fetch(form.getAttribute('action') || window.location.href, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        })
        .then(r => r.json())
        .then(j => {
          withDelay(() => {
            removeTyping();
            if (j && j.ok) {
              addBotBubble(j.reply);
            } else {
              addBotBubble(
                j && j.error ? j.error :
                'Maaf, terjadi kesalahan. Silakan coba lagi nanti.'
              );
            }
            // Setelah bot MENJAWAB (sukses / error) -> tampilkan tombol X
            if (btnClose) btnClose.classList.add('show');
          });
        })
        .catch(() => {
          withDelay(() => {
            removeTyping();
            addBotBubble('Maaf, terjadi gangguan jaringan. Silakan coba lagi.');
            // Error jaringan pun dianggap "bot sudah menjawab"
            if (btnClose) btnClose.classList.add('show');
          });
        })
        .finally(() => {
          if (btnSend) btnSend.disabled = false;
        });
      });
    });
  </script>
</head>
<body>
  <div class="chat-admin-wrap">
    <div class="chat-admin-card">
      <div class="chat-admin-header">
        <div class="chat-admin-header-left">
          <div class="chat-admin-logo">
            <img src="images/00.png" alt="">
          </div>
          <div class="chat-admin-title">Chat Admin</div>
        </div>
        <!-- X: awalnya hidden, muncul pakai class .show dari JS -->
        <button type="button" class="chat-admin-close" onclick="closeChatAdmin()">×</button>
      </div>

      <div class="chat-admin-body">
        <!-- AREA CHAT (muncul setelah submit) -->
        <div id="chatView" class="kodee-chat" hidden>
          <div id="chatList" class="kodee-chat-list"></div>
        </div>

        <!-- FORM (awal seperti gambar ke-2) -->
        <form method="post" class="chat-admin-form" id="chatForm">
          <?php if ($errorMsg !== '' && !$isAjax): ?>
            <div class="chat-strip-error"><?= nl2br(e($errorMsg)) ?></div>
          <?php endif; ?>

          <div class="field">
            <input
              class="kodee-input-sm"
              type="text"
              name="nama_lengkap"
              placeholder="Nama lengkap"
              value="<?= e($prefNama) ?>"
              required
            >
          </div>

          <div class="field">
            <input
              class="kodee-input-sm"
              type="text"
              name="username"
              placeholder="Username Terdaftar"
              value="<?= e($prefUser) ?>"
              required
            >
          </div>

          <div class="field">
            <textarea
              class="kodee-input"
              name="pesan"
              placeholder="Tulis pesan Anda..."
              required
            ><?= e($prefPesan) ?></textarea>
          </div>

          <!-- Hidden param untuk referensi -->
          <input type="hidden" name="cid" value="<?= (int)$tgChatId ?>">
          <input type="hidden" name="tg_username" value="<?= e($tgUsername) ?>">
          <input type="hidden" name="src" value="<?= e($src) ?>">

          <div class="chat-admin-actions">
            <button type="submit" class="btn-send" id="btnSend">➤</button>
          </div>
        </form>

        <div class="chat-admin-note">
          Isi nama dan username efidi kamu, lalu tulis pesan singkat yang ingin disampaikan ke admin.
        </div>
      </div>
    </div>
  </div>
</body>
</html>
