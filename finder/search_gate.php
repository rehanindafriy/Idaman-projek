<?php
// finder/search_page.php
// Halaman informasi jika akun Finder Search sedang DISUSPENDED
declare(strict_types=1);

if (!function_exists('e')) {
  function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

// param opsional dari Telegram (untuk dikirim ke chat_admin.php)
$tgUsername = trim((string)($_GET['u']   ?? ''));
$tgChatId   = (int)($_GET['cid'] ?? 0);
$src        = trim((string)($_GET['src'] ?? 'finder_search_suspended'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>EFIDI · Akun Finder Search Disuspended</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
  :root {
    /* PALET EFIDI (SAMA PERSIS DENGAN pending_page.php) */
    --efidi-teal:       #00a3ad;
    --efidi-teal-dark:  #00808b;
    --efidi-bg-soft:    #e5f7f9;
    --efidi-bg-soft2:   #f1f5f9;
    --efidi-text-main:  #042f3a;
    --efidi-text-muted: #6b7280;
    --efidi-border-soft:#dbeafe;
  }

  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    /* Background lembut ala Hasil Search BOT & pending_page */
    background:
      radial-gradient(circle at -10% -10%, var(--efidi-bg-soft) 0, transparent 60%),
      radial-gradient(circle at 110% 100%, #cceff4 0, transparent 60%),
      #f3f4f6;
    color: var(--efidi-text-main);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .wrap {
    width: 100%;
    max-width: 460px;
  }

  .card {
    /* Card putih bersih seperti pending_page.php */
    background: #ffffff;
    border-radius: 22px;
    border: 1px solid var(--efidi-border-soft);
    box-shadow:
      0 18px 40px rgba(15, 23, 42, 0.08),
      0 0 0 1px rgba(226, 232, 240, 0.9);
    padding: 22px 20px 20px;
    position: relative;
    overflow: hidden;
  }

  .card::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(148, 163, 184, 0.12), transparent 40%);
    opacity: 0.7;
    pointer-events: none;
  }

  .card-inner {
    position: relative;
    z-index: 1;
  }

  .brand-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
  }

  .brand-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 11px;
    border-radius: 999px;
    /* Chip gradiasi ala tag “EFIDI · ACCESS CONTROL” */
    background: linear-gradient(135deg, #e0f9ff, #f9fafb);
    border: 1px solid #bae6fd;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #022c32;
  }

  .brand-chip span.logo-dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: radial-gradient(circle at 30% 30%, #ffffff, var(--efidi-teal));
    box-shadow: 0 0 8px rgba(34, 211, 238, 0.8);
  }

  .info-badge {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    border: 1px solid rgba(248, 113, 113, 0.45);
    color: #b91c1c;
    background: #fee2e2;
    white-space: nowrap;
  }

  .icon-circle {
    width: 58px;
    height: 58px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    /* Icon bulat terang (mirip pending_page.php) */
    background:
      radial-gradient(circle at 20% 0%, #ffffff, #e5f7f9),
      radial-gradient(circle at 100% 100%, rgba(0, 163, 173, 0.35), rgba(241, 245, 249, 0.95));
    box-shadow:
      0 10px 22px rgba(148, 163, 184, 0.4),
      0 0 0 1px rgba(209, 213, 219, 0.9);
    color: #022c32;
    font-size: 24px;
  }

  .title {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.02em;
    margin: 0 0 6px;
    color: var(--efidi-text-main);
  }

  .subtitle {
    margin: 0 0 12px;
    font-size: 13px;
    color: var(--efidi-text-muted);
  }

  .info-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    border: 1px dashed #cbd5f5;
    padding: 5px 10px;
    font-size: 11px;
    color: #0f172a;
    margin-bottom: 12px;
    background: #eff6ff;
  }

  .info-chip span.dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--efidi-teal);
    box-shadow: 0 0 6px rgba(45, 212, 191, 0.8);
  }

  .desc {
    font-size: 13px;
    line-height: 1.6;
    color: #111827;
    margin-bottom: 10px;
  }

  .list {
    margin: 0 0 14px;
    padding-left: 16px;
    font-size: 12px;
    color: #4b5563;
  }

  .list li {
    margin-bottom: 4px;
  }

  .note {
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 16px;
    padding: 8px 10px;
    border-radius: 10px;
    background: #f9fafb;
    border: 1px dashed #d1d5db;
  }

  .note b {
    color: #111827;
  }

  .actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
  }

  .btn {
    flex: 1 1 auto;
    min-width: 130px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: transform 0.08s ease-out,
                box-shadow 0.08s ease-out,
                background 0.08s ease-out,
                border-color 0.08s ease-out;
  }

  .btn-outline {
    background: #ffffff;
    color: var(--efidi-text-main);
    border-color: #d1d5db;
  }

  .btn:active {
    transform: translateY(1px) scale(0.99);
    box-shadow: 0 6px 14px rgba(148, 163, 184, 0.7);
  }

  @media (max-width: 480px) {
    .card {
      border-radius: 18px;
      padding: 18px 16px 16px;
    }
    .title {
      font-size: 18px;
    }
    .icon-circle {
      width: 52px;
      height: 52px;
      font-size: 22px;
    }
  }

  /* ===================== MODAL CHAT ADMIN EFIDI ====================== */

  .chat-modal-backdrop{
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 60;
  }
  .chat-modal-backdrop.show{
    display: flex;
  }
  .chat-modal-center{
    width: 100%;
    max-width: 380px;
    padding: 8px;
  }

  .chat-admin-card{
    width: 100%;
    background:#fff;
    border-radius:18px;
    border:1px solid #e5e7eb;
    box-shadow:0 18px 46px rgba(15,23,42,.22);
    padding:12px 14px 14px;
  }

  .chat-admin-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
  }
  .chat-admin-header-left{
    display:flex;
    align-items:center;
    gap:8px;
  }
  .chat-admin-logo{
    width:26px;height:26px;border-radius:999px;
    display:grid;place-items:center;
    overflow:hidden;
  }
  .chat-admin-logo img{
    width:24px;height:24px;object-fit:contain;display:block;
  }
  .chat-admin-title{
    font-size:15px;
    font-weight:700;
    color:#0f172a;
  }

  /* Tombol X di pojok kanan atas, sejajar dengan “Chat Admin” */
  .chat-admin-close{
    border:0;
    background:transparent;
    width:26px;
    height:26px;
    border-radius:999px;
    cursor:pointer;
    font-size:16px;
    color:#9ca3af;
    display:grid;
    place-items:center;
  }
  .chat-admin-close:hover{
    background:#f3f4f6;
    color:#4b5563;
  }

  .chat-admin-body{margin-top:10px;}
  .chat-admin-note{margin-top:10px;font-size:11px;color:#94a3b8;}

  .chat-admin-form{margin-top:6px;}
  .field{margin-bottom:10px;}

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

  textarea.kodee-input{
    height:auto !important;
    min-height:96px;
    max-height:280px;
    line-height:1.4;
    padding-top:10px;
    padding-bottom:10px;
    resize:vertical;
  }

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
    background: #06c5d1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f9fafb;
    font-size: 20px;
    font-family: inherit;
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

  @media (max-width:480px){
    .chat-modal-center{
      max-width:100%;
    }
    .chat-admin-card{
      max-width:100%;
      border-radius:16px;
      padding:12px 14px 14px;
    }
  }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="card-inner">
        <div class="brand-row">
          <div class="brand-chip">
            <span class="logo-dot"></span>
            <span>EFIDI · FINDER SEARCH</span>
          </div>
          <div class="info-badge">
            DISUSPENDED
          </div>
        </div>

        <div class="icon-circle">
          &#9888;
        </div>

        <h1 class="title">Akun Finder Search Anda Sedang Disuspended</h1>
        <p class="subtitle">
          Saat ini akses ke fitur <strong>Finder Search EFIDI</strong> untuk akun Telegram Anda
          sedang <strong>ditangguhkan (suspended)</strong> oleh admin.
        </p>

        <div class="info-chip">
          <span class="dot"></span>
          <span>
            Selama status <strong>suspended</strong>, Anda <strong>tidak dapat menggunakan</strong>
            tombol <strong>🔎 Buka Finder Search</strong> yang dikirim oleh BOT FINDER EFIDI.
          </span>
        </div>

        <p class="desc">
          Penangguhan ini merupakan bagian dari pengelolaan akses dan keamanan sistem EFIDI.
          Beberapa hal penting yang perlu Anda ketahui:
        </p>

        <ol class="list">
          <li>
            Hak akses ke halaman <strong>Finder Search</strong> ditahan sementara,
            namun data dan riwayat Anda di sistem EFIDI tetap tersimpan.
          </li>
          <li>
            Jika Anda merasa penangguhan ini tidak sesuai, silakan segera menghubungi
            <strong>admin EFIDI</strong> untuk klarifikasi lebih lanjut.
          </li>
          <li>
            Setelah admin mengubah status akun Anda menjadi <strong>active</strong>,
            tombol <strong>🔎 Buka Finder Search</strong> yang baru dapat kembali digunakan
            untuk membuka halaman pencarian.
          </li>
        </ol>

        <p class="note">
          <b>Langkah selanjutnya:</b><br>
          Silakan hubungi admin efidi melalui fitur <strong>Chat Admin efidi</strong> di bawah ini untuk:
          <br>– Menanyakan alasan penangguhan (suspended). 
          <br>– Mengajukan permohonan aktivasi kembali akses Finder Search.
          <br><br>
          Setelah proses verifikasi selesai dan status diubah menjadi <strong>active</strong>,
          Anda dapat kembali menggunakan BOT FINDER EFIDI seperti biasa.
        </p>

        <div class="actions">
          <button type="button" class="btn btn-outline" id="btnOpenChatAdmin">
            💬 Chat Admin efidi
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== MODAL CHAT ADMIN ===================== -->
  <div class="chat-modal-backdrop" id="chatAdminModal" aria-hidden="true">
    <div class="chat-modal-center">
      <div class="chat-admin-card">
        <div class="chat-admin-header">
          <div class="chat-admin-header-left">
            <div class="chat-admin-logo">
              <img src="../images/00.png" alt="">
            </div>
            <div class="chat-admin-title">Chat Admin</div>
          </div>
          <!-- Tombol X di pojok kanan atas -->
          <button type="button" class="chat-admin-close" id="chatAdminCloseBtn">×</button>
        </div>

        <div class="chat-admin-body">
          <!-- AREA CHAT (muncul setelah submit) -->
          <div id="chatView" class="kodee-chat" hidden>
            <div id="chatList" class="kodee-chat-list"></div>
          </div>

          <!-- FORM -->
          <form method="post" class="chat-admin-form" id="chatForm" action="../chat_admin.php">
            <div class="field">
              <input
                class="kodee-input-sm"
                type="text"
                name="nama_lengkap"
                placeholder="Nama lengkap"
                required
              >
            </div>

            <div class="field">
              <input
                class="kodee-input-sm"
                type="text"
                name="username"
                placeholder="Username Terdaftar"
                value="<?= $tgUsername !== '' ? '@' . e($tgUsername) : '' ?>"
                required
              >
            </div>

            <div class="field">
              <textarea
                class="kodee-input"
                name="pesan"
                placeholder="Tulis pesan Anda..."
                required
              ></textarea>
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
  </div>

  <script>
  (function () {
    const modal   = document.getElementById('chatAdminModal');
    const btnOpen = document.getElementById('btnOpenChatAdmin');
    const btnClose = document.getElementById('chatAdminCloseBtn');

    function resetChatForm() {
      const chatView = document.getElementById('chatView');
      const chatList = document.getElementById('chatList');
      const form     = document.getElementById('chatForm');
      const note     = document.querySelector('.chat-admin-note');

      if (chatView) chatView.hidden = true;
      if (chatList) chatList.innerHTML = '';
      if (form) {
        form.style.display = '';
        form.reset();
      }
      if (note) note.style.display = '';
    }

    function openModal() {
      if (!modal) return;
      modal.classList.add('show');
    }

    function closeModal() {
      if (!modal) return;
      modal.classList.remove('show');
      resetChatForm();
    }

    if (btnOpen) {
      btnOpen.addEventListener('click', openModal);
    }
    if (btnClose) {
      btnClose.addEventListener('click', closeModal);
    }
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          closeModal();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closeModal();
        }
      });
    }

    // ====== LOGIKA CHAT ADMIN (AJAX ke chat_admin.php) – sama seperti chat_admin.php ======
    document.addEventListener('DOMContentLoaded', function () {
      const form     = document.getElementById('chatForm');
      const btnSend  = document.getElementById('btnSend');
      const chatView = document.getElementById('chatView');
      const chatList = document.getElementById('chatList');
      const note     = document.querySelector('.chat-admin-note');

      if (!form) return;

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

      function addTyping() {
        if (!chatList) return;
        const wrap = document.createElement('div');
        wrap.className = 'msg msg-bot msg-typing';
        wrap.id = 'msgTyping';
        wrap.innerHTML = `
          <div class="avatar">
            <img src="../images/00.png" alt="" class="kodee-ava">
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

      function addBotBubble(text) {
        if (!chatList) return;
        const wrap = document.createElement('div');
        wrap.className = 'msg msg-bot';
        wrap.innerHTML = `
          <div class="avatar">
            <img src="../images/00.png" alt="" class="kodee-ava">
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

        if (chatView) chatView.hidden = false;
        form.style.display = 'none';
        if (note) note.style.display = 'none';

        clearChat();
        addSummary(esc(n), esc(u), esc(m));
        addTyping();

        if (btnSend) btnSend.disabled = true;

        const formData = new FormData(form);
        const startAt  = Date.now(); // delay 3 detik biar efek "typing"

        function withDelay(cb) {
          const elapsed = Date.now() - startAt;
          const remain  = 3000 - elapsed;
          if (remain > 0) {
            setTimeout(cb, remain);
          } else {
            cb();
          }
        }

        fetch(form.getAttribute('action') || '../chat_admin.php', {
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
          });
        })
        .catch(() => {
          withDelay(() => {
            removeTyping();
            addBotBubble('Maaf, terjadi gangguan jaringan. Silakan coba lagi.');
          });
        })
        .finally(() => {
          if (btnSend) btnSend.disabled = false;
        });
      });
    });
  })();
  </script>
</body>
</html>
