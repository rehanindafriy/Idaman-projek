<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__.'/redirect_helper.php';  // redirect()

$err = flash('err'); 
$ok  = flash('ok');

if (!empty($_SESSION['uid']) && !empty($_SESSION['role'])) {
  $role = strtolower((string)$_SESSION['role']);
  if ($role === 'admin') { header('Location: dashboard_admin.php'); exit; }
  if ($role === 'ta')    { header('Location: dashboard_ta.php'); exit; }
  if ($role === 'tech')  { header('Location: search.php'); exit; } // samakan dengan login_process
}


?>
<?php
function es_render_alert(?string $msg, string $type='err'): string {
  if (!$msg) return '';
  // Escape semua dulu
  $safe = htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8');
   $safe = str_ireplace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>','</b>'], $safe);
  // Ubah kata "Chat Admin" (huruf besar/kecil bebas) jadi link yang membuka widget
   $safe = preg_replace('~&lt;a\b[^&]*?&gt;\s*Chat\s+Admin\s*&lt;/a&gt;~i',
                       '<a href="#" class="open-chat-admin">Chat Admin</a>', $safe);
  return '<div class="es-alert '.$type.'"><i class="mdi mdi-alert-circle-outline"></i> '.$safe.'</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>efidi. — Sign In</title>
  <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/logoo.png" />
  <style>
    :root { --es-teal: #00A3AD; }
    


    /* Baris tombol tetap di kanan */
.btn-row{ display:flex; justify-content:flex-end; margin-top:8px; }

/* ukuran standar baru yang lebih kecil */
.es-btn-text{
  /* warna brand */
  background:#00A3AD !important; border:1px solid #00A3AD !important; color:#fff !important;

  /* UKURAN KECIL */
  height: 40px !important;       /* tinggi tombol */
  min-width: 60px !important;     /* lebar minimum */
  padding: 0 10px !important;     /* ruang kiri–kanan */
  font-size: 12px !important;
  line-height: 1 !important;
  border-radius: 2px;

  /* layout & efek */
  display:inline-flex; align-items:center; justify-content:center;
  box-sizing: border-box;
  box-shadow: 0 2px 10px rgba(0,0,0,.12);
}

.es-btn-text:hover{ filter:brightness(.95); transform:translateY(-1px); }
.es-btn-text:active{ transform:translateY(0); }
.es-btn-text:focus-visible{
  outline:none; box-shadow:0 0 0 3px rgba(0,163,173,.22), 0 2px 10px rgba(0,0,0,.12);
}


    /* label di atas input */
.form-label{
  display:block;
  margin: 8px 2px 6px;
  font-weight: 100;            /* ikuti font default template */
  font-size: 14px;
  color:#111827;
}

/* garis/border input yang jelas + highlight teal saat fokus */
.es-input.form-control{
  border: 1px solid #dfe3ea;   /* garis terlihat */
  border-radius: 8px;
  height: 48px;
  box-shadow: none;
}
.es-input.form-control:focus{
  border-color:#00A3AD;        /* teal saat fokus */
  box-shadow: 0 0 0 3px rgba(0,163,173,.12);
}
.es-footnote{
  font-size: 13px;   /* ubah 11–14px sesuai selera */
  line-height: 1.4;
}
.es-footnote a{ font-size: inherit; }


    /* ====== ukuran & shadow card (FONT TETAP dari style.css) ====== */
    #esLoginCard{
      max-width: 420px;             /* <— diperkecil (silakan ubah 320–360 sesuai selera) */
      margin: 18px auto;            /* center */
      border-radius: 14px;
      background: #fff;
      /* shadow hitam */
      box-shadow:
        0 10px 48px rgba(0, 0, 0, 0.13),
        0 3px 10px rgba(0,0,0,.08);
      padding: 20px 22px;
    }

    /* header: logo kiri, teks kanan logo */
    .brand-row{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
    .brand-row img{ height:36px; width:auto; }
    .brand-title{ margin:0; font-weight:520; color:var(--es-teal); }
    .brand-sub{ margin:2px 0 0; }

    /* flash */
    .es-alert{border-radius:6px;padding:10px 12px;margin-bottom:12px;font-size:14px}
    .es-alert.err{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
    .es-alert.ok{background:#e0f2fe;color:#082f49;border:1px solid #bae6fd}

    /* tombol: warna teal, posisi kanan, width auto */
    .btn-primary{ background:var(--es-teal) !important; border-color:var(--es-teal) !important; }
    .auth-form-btn{ width:auto !important; padding:11px 10px; border-radius:2px; }
    .btn-row{ display:flex; justify-content:flex-end; margin-top:6px; }
/* ===== Spacing fix untuk header, alert, dan form ===== */



    /* responsif kecil: biarkan center & nyaman */
    @media (max-width: 576px){
      #esLoginCard{ max-width: 92vw; padding:18px; }
    }
    /* === Tighten: alert ↔ form (tanpa ubah markup) === */
#esLoginCard .es-alert{ margin:6px 0 6px !important; }          /* kecilkan jarak bawah alert */
#esLoginCard .es-alert + form{ margin-top:0 !important; }       /* hapus jarak ekstra di awal form */
#esLoginCard form.pt-3{ padding-top:4px !important; }           /* kecilkan padding bawaan pt-3 */
#esLoginCard form .form-group:first-of-type{ margin-top:2px !important; } /* rapatkan field pertama */

/* link "Input kode verifikasi" sejajar tombol */
/* sejajarkan link (kiri) dan tombol (kanan) dalam satu baris penuh */
#esLoginCard .btn-row{
  display:flex;
  justify-content: space-between !important; /* kiri ↔ kanan */
  align-items: center;
  gap: 10px;
  margin-top: 8px;
  width: 100%;
}

/* gaya link verifikasi */
.verify-link{
  font-size: 12px;
  color: #0f172a;              /* hitam */
  text-decoration: none;
  cursor: pointer;
}
.verify-link:hover{
  color: #0d6efd;              /* biru saat hover */
  text-decoration: underline;
}

/* === Eye icon untuk password === */
/* === Eye icon untuk password (center di DALAM input) === */
.form-group.has-eye .es-input-wrap { position: relative; }

.form-group.has-eye .es-input {
  padding-right: 42px !important; /* ruang untuk ikon */
}

.password-toggle{
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);    /* benar2 di tengah input */
  background: transparent;
  border: 0;
  padding: 6px;
  display: inline-flex; align-items: center; justify-content: center;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
  z-index: 1;
}
.password-toggle:hover { color:#00A3AD; }
.password-toggle:focus-visible{
  outline:none; box-shadow:0 0 0 3px rgba(13,110,253,.25); border-radius:6px;
}
.password-toggle .mdi{ font-size:20px; }




  </style>
</head>
<body>
  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-center auth px-0">
        <div class="row w-100 mx-0">
          <div class="col-lg-4 mx-auto">
            <!-- tambahkan id di sini -->
            <div id="esLoginCard" class="auth-form-light text-left">
<br>
              <!-- brand: logo + teks kanan logo -->
              <div class="brand-row">
                <img src="images/logoo.png" alt="logo">
                <div>
                  <h4 class="brand-title">Welcome Back ! Login efidi.</h4>
                  <h6 class="font-weight-light brand-sub">Please sign in to continue to your account.</h6>
                </div>
              </div>

             <?php if($err): ?><?= es_render_alert($err, 'err') ?><?php endif; ?>
<?php if($ok):  ?><?= es_render_alert($ok, 'ok')  ?><?php endif; ?>

              <form class="pt-3" method="post" action="login_process.php" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

              <div class="form-group">
  <label for="login_username" class="form-label">Username</label>
  <input id="login_username" type="text" name="username"
         class="form-control form-control-lg es-input"
         placeholder="Username" required>
</div>

<div class="form-group has-eye">
  <label for="login_password" class="form-label">Password</label>

  <div class="es-input-wrap">
    <input id="login_password" type="password" name="password"
           class="form-control form-control-lg es-input"
           placeholder="Password" required>

    <button type="button"
            id="toggle_pwd"
            class="password-toggle"
            aria-label="Tampilkan password"
            aria-pressed="false"
            title="Tampilkan password">
      <i class="mdi mdi-eye-off"></i>
    </button>
  </div>
</div>

<div class="btn-row">
  <a href="index.php" class="verify-link" style="color: #0d6efd;">Back</a>
  <button class="es-btn-text" type="submit">SIGN IN</button>
</div>


                

               <div class="text-center mt-4 font-weight-light es-footnote">
  Don't you have an account? <a href="qrcode.php" class="text-primary"> Register</a>
</div>

              </form>

            </div> <!-- /#esLoginCard -->
          </div>
        </div>
      </div>
    </div>
  </div>

<div id="toast-container"></div>
<?php if(!empty($err)): ?>
<script>toast(<?= json_encode($err) ?>);</script>
<?php endif; ?>
<?php if(!empty($ok)): ?>
<script>toast(<?= json_encode($ok) ?>);</script>
<?php endif; ?>

<script>
(() => {
  const pwd = document.getElementById('login_password');
  const btn = document.getElementById('toggle_pwd');
  if (!pwd || !btn) return;

  btn.addEventListener('click', () => {
    const isHidden = pwd.type === 'password';
    pwd.type = isHidden ? 'text' : 'password';

    btn.setAttribute('aria-pressed', String(isHidden));
    btn.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
    btn.title = isHidden ? 'Sembunyikan password' : 'Tampilkan password';

    const icon = btn.querySelector('i');
    icon.classList.toggle('mdi-eye', isHidden);
    icon.classList.toggle('mdi-eye-off', !isHidden);
  });

  // Opsional: peek saat ditekan, lepas kembali sembunyi
  // btn.addEventListener('mousedown', () => { pwd.type = 'text'; });
  // ['mouseup','mouseleave','blur'].forEach(ev => btn.addEventListener(ev, () => { pwd.type = 'password'; }));
})();
</script>

  <script src="vendors/base/vendor.bundle.base.js"></script>
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
<style>
  :root{--kodee-bg:#fff;--kodee-ink:#0f172a;--kodee-line:#e5e7eb;--kodee-primary:var(--es-primary,#00A3AD)}
  .kodee-launcher{position:fixed;right:18px;bottom:18px;z-index:9999;display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 16px;border:0;border-radius:999px;background:linear-gradient(90deg,var(--kodee-primary),#0bb5bf);color:#fff;font-weight:500;cursor:pointer;box-shadow:0 10px 28px rgba(2,8,23,.25)}
  .kodee-panel{position:fixed;right:18px;bottom:74px;z-index:9999;width:360px;max-width:calc(100vw - 36px);background:var(--kodee-bg);color:var(--kodee-ink);border:1px solid var(--kodee-line);border-radius:12px;box-shadow:0 18px 48px rgba(2,8,23,.28);overflow:hidden;display:none}
  .kodee-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;border-bottom:1px solid var(--kodee-line)}
  .kodee-title{font-weight:600}
  .kodee-x{background:transparent;border:0;width:32px;height:32px;border-radius:8px;cursor:pointer}
  .kodee-body{padding:12px;background:#f8fafc}
  .kodee-card{background:#fff;border:1px solid var(--kodee-line);border-radius:12px;box-shadow:0 8px 24px rgba(2,8,23,.06);overflow:hidden}
  .kodee-card-head{padding:12px 12px 0}
  .kodee-card-title{font-weight:800}
  .kodee-card-sub{font-size:12px;color:#64748b;margin-top:2px}
  .kodee-form{padding:12px;display:flex;flex-direction:column;gap:10px}
  .kodee-grid{display:grid;grid-template-columns:1fr !important;gap:8px}
  @media (max-width:420px){ .kodee-grid{grid-template-columns:1fr;} }
  .kodee-input,.kodee-input-sm{box-sizing:border-box;width:100%;height:38px;border:1px solid var(--kodee-line);border-radius:9px;padding:0 10px;transition:border-color .15s ease,box-shadow .15s ease;background:#fff}
  .kodee-input::placeholder,.kodee-input-sm::placeholder{color:#94a3b8}
  .kodee-input:focus,.kodee-input-sm:focus{outline:none;border-color:var(--kodee-primary)!important;box-shadow:0 0 0 3px rgba(13,110,253,.18)!important}
  .kodee-actions{display:flex;justify-content:flex-end;padding:0 12px 12px}
  .kodee-send{height:38px;padding:0 12px;border:0;border-radius:9px;background:var(--kodee-primary);color:#fff;font-weight:700;cursor:pointer}
  .kodee-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:8px 10px;border-radius:8px;margin:12px}
  .kodee-text{border-top:1px dashed var(--kodee-line);padding:10px 12px}
  .kodee-panel.is-open{display:block;}
  /* === Kodee – Typing / Thinking animation === */
.kodee-typing{padding:10px 12px}
.kodee-typing[hidden]{display:none!important}
.kodee-typing-line{display:flex;align-items:center;gap:8px}

.kodee-botmark{
  width:22px;height:22px;display:grid;place-items:center;
  border-radius:8px;border:1px solid var(--kodee-line);
  background:#fff;color:var(--kodee-primary);font-size:12px;user-select:none
}

.kodee-botbubble{
  flex:1;display:inline-flex;align-items:center;gap:8px;
  padding:8px 10px;border:1px solid var(--kodee-line);
  border-radius:12px;background:#ffffff;box-shadow:0 3px 10px rgba(2,8,23,.06)
}
.kodee-typing-text{font-weight:700;color:#334155}
.kodee-spinner{
  width:16px;height:16px;border-radius:50%;
  border:3px solid #e2e8f0;border-top-color:var(--kodee-primary);
  animation:kodee-spin 1s linear infinite
}
@keyframes kodee-spin{to{transform:rotate(1turn)}}

.kodee-dots{display:inline-flex;gap:4px;margin-left:2px}
.kodee-dots i{
  width:6px;height:6px;border-radius:999px;background:var(--kodee-primary);
  opacity:.35;animation:kodee-bounce .9s ease-in-out infinite
}
.kodee-dots i:nth-child(2){animation-delay:.15s}
.kodee-dots i:nth-child(3){animation-delay:.3s}
@keyframes kodee-bounce{
  0%,80%,100%{transform:translateY(0);opacity:.35}
  40%{transform:translateY(-3px);opacity:1}
}

/* gelembung balasan lebih rapi */
#kodeeReply{
  line-height:1.5;border-radius:12px;background:#f8fafc;
  border:1px solid #e2e8f0;padding:10px
}
/* === Kodee – Chat bubbles === */
.kodee-chat{padding:12px;background:#f8fafc;border-top:1px dashed var(--kodee-line)}
.kodee-chat-list{max-height:52vh;overflow:auto;display:flex;flex-direction:column;gap:10px}
.msg{display:flex;gap:8px}
.msg .bubble{
  max-width:88%;white-space:pre-line;
  padding:10px 12px;border:1px solid var(--kodee-line);
  border-radius:14px;box-shadow:0 3px 10px rgba(2,8,23,.06);line-height:1.5
}
.msg-user{justify-content:flex-end}
.msg-user .bubble{background:linear-gradient(180deg,#eef6ff,#fff);border-color:#cfe1ff}
.msg-bot{justify-content:flex-start}
.msg-bot .avatar{
  width:26px;height:26px;border-radius:9px;display:grid;place-items:center;
  border:1px solid var(--kodee-line);background:#fff;color:var(--kodee-primary);font-size:13px
}

/* Typing/Thinking bubble */
.msg-typing .bubble{display:inline-flex;align-items:center;gap:8px}
.kodee-spinner{
  width:16px;height:16px;border-radius:50%;
  border:3px solid #e2e8f0;border-top-color:var(--kodee-primary);
  animation:kodee-spin 1s linear infinite
}
@keyframes kodee-spin{to{transform:rotate(1turn)}}
.kodee-dots{display:inline-flex;gap:4px}
.kodee-dots i{width:6px;height:6px;border-radius:999px;background:var(--kodee-primary);opacity:.35;animation:kodee-bounce .9s ease-in-out infinite}
.kodee-dots i:nth-child(2){animation-delay:.15s}
.kodee-dots i:nth-child(3){animation-delay:.3s}
@keyframes kodee-bounce{0%,80%,100%{transform:translateY(0);opacity:.35}40%{transform:translateY(-3px);opacity:1}}

/* === Rapatkan HANYA bubble milik user === */
.msg-user .bubble{
  line-height: 0.90;       /* padatkan teks user */
  padding: 6px 10px;
  font-size: 14px;
}
.msg-user .bubble .kv{
  display: grid;
  grid-template-columns: 78px 1fr;  /* lebar label */
  column-gap: 6px;
  row-gap: 2px;
  margin: 0;
}
.msg-user .bubble .k{ font-weight:700; color:#334155; }
.msg-user .bubble .v{ white-space: pre-line; }

/* === Kembalikan bubble BOT ke jarak normal (agar tidak ikut rapat) === */
.msg-bot .bubble{
  line-height: 1.5;        /* jarak normal untuk bot */
  padding: 10px 12px;
}


/* === Flatten: lenyapkan tampilan inner card === */
.kodee-card{
  background:transparent !important;
  border:0 !important;
  box-shadow:none !important;
  padding:0 !important;
}

/* Sembunyikan header "Hubungi Admin efidi" */
.kodee-card-head{ display:none !important; }

/* Rapikan jarak agar konten nempel ke kartu utama */
#kodeeForm,
.kodee-actions,
#kodeeChat,
#kodeeTyping,
#kodeeBotWrap{
  margin: 8px 0 !important;
}

/* (opsional) garis pemisah halus di atas area chat */
#kodeeChat{ border-top:1px dashed var(--kodee-line); padding-top:10px; }

/* Khusus textarea pesan */
textarea#kodeeMsg.kodee-input{
  height:auto !important;     /* nolkan aturan 38px */
  min-height: 96px;           /* tinggi awal */
  max-height: 280px;          /* batas atas */
  line-height: 1.4;
  padding-top: 10px;
  padding-bottom: 10px;
  resize: vertical;           /* boleh diseret */
}
/* Logo kecil di tombol Chat Admin */
.msg-bot .avatar{
  width:28px;height:28px;border-radius:9px;display:grid;place-items:center;
  border:1px solid var(--kodee-line);background:#fff;
}
.msg-bot .avatar .kodee-ava{
  width:28px;height:28px;object-fit:contain;display:block;
}

/* Pakai logo untuk ikon bot (typing & avatar balasan) */

</style>

<?php
$prefName = htmlspecialchars($_SESSION['nama'] ?? ($_SESSION['user']['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$prefUser = htmlspecialchars($_SESSION['user']['username'] ?? ($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8');

$__csrf = '';
if (function_exists('csrf_token')) {
  $__csrf = htmlspecialchars((string)csrf_token(), ENT_QUOTES, 'UTF-8');
} else {
  $__csrf = htmlspecialchars((string)($_SESSION['csrf'] ?? ''), ENT_QUOTES, 'UTF-8');
}

// Bangun URL absolut agar aman di subfolder
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$actionUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].$base.'/panduan_send.php';
$checkUrl  = $scheme.'://'.$_SERVER['HTTP_HOST'].$base.'/ajax/check_username.php';
?>

<button id="kodeeFab" class="kodee-launcher" aria-expanded="false">
  <img src="images/9000.png" alt="" class="kodee-logo" width="25" height="24" loading="lazy">
  <span>Chat Admin</span>
</button>


<div id="kodeePanel" class="kodee-panel" role="dialog" aria-label="Tanya Kodee"
     data-name="<?= $prefName ?>" data-username="<?= $prefUser ?>"
     data-csrf="<?= $__csrf ?>" data-action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>"
     data-check="<?= htmlspecialchars($checkUrl, ENT_QUOTES) ?>">
  <div class="kodee-head">
  <div class="kodee-title">
  <img src="images/0.png" alt="" class="kodee-title-logo" width="27" height="25" loading="lazy">
  <span>Chat Admin</span>
</div>

    <button class="kodee-x" id="kodeeClose" aria-label="Tutup">✕</button>
  </div>

  <div class="kodee-body">
    <div class="kodee-card">
      <div class="kodee-card-head">
        <div class="kodee-card-title">Hubungi Admin efidi</div>
        <div class="kodee-card-sub">Isi data singkat di bawah. Pesanmu dikirim ke admin.</div>
      </div>

      <!-- FORM UI -->
      <div id="kodeeForm" class="kodee-form">
        <div class="kodee-grid">
          <input id="kodeeNama" class="kodee-input-sm" type="text" placeholder="Nama lengkap" required>
            <input id="kodeeUser" class="kodee-input-sm" type="text" placeholder="Username Terdaftar" required>

         <script>
(function(){
  const panel = document.getElementById('kodeePanel');
  const input = document.getElementById('kodeeUser');
  if (!input) return;

  let t;
  // bersihkan pesan custom saat mengetik
  input.addEventListener('input', ()=> input.setCustomValidity(''));

  function cekUsername(){
    const raw = input.value.trim().replace(/^@+/, '');
    if (!raw){ input.setCustomValidity(''); return; }

    const url = (panel?.dataset.check || 'ajax/check_username.php') + '?u=' + encodeURIComponent(raw);
    fetch(url, { cache: 'no-store' })
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(j => {
        if (j && j.ok && j.exists) {
          input.setCustomValidity(''); // valid
        } else {
          input.setCustomValidity('Username tidak ditemukan. Gunakan username yang kamu daftarkan.');
        }
      })
      .catch(() => input.setCustomValidity('')); // jangan kunci form bila error jaringan
  }

  input.addEventListener('blur', cekUsername);
  input.addEventListener('keyup', ()=>{ clearTimeout(t); t = setTimeout(cekUsername, 300); }); // debounce
})();
</script>

        </div>
        <textarea id="kodeeMsg" class="kodee-input" type="text" placeholder="Tulis pesan Anda…" required></textarea>
      </div>
      <div class="kodee-actions">
        <button id="kodeeSend" class="kodee-send" type="button"><i class="mdi mdi-send" style="font-size: 22px;"></i></button>
      </div>

      <!-- SESUDAH TERKIRIM (dipakai jika kamu mau tetap tanpa reload) -->
     <!-- AREA CHAT (baru) -->
<div id="kodeeChat" class="kodee-chat" hidden>
  <div id="kodeeChatList" class="kodee-chat-list"
       aria-live="polite" aria-relevant="additions text"></div>
</div>

  <!-- Typing indicator (muncul duluan) -->
  <div id="kodeeTyping" class="kodee-typing" role="status" aria-live="polite" hidden>
    <div class="kodee-typing-line">
      <div class="kodee-botmark" aria-hidden="true">
  <img src="images/00.png" alt="" style="width:16px;height:16px;display:block;">
</div>

      <div class="kodee-botbubble">
        <span class="kodee-spinner" aria-hidden="true"></span>
        <span class="kodee-typing-text">Processing</span>
        <span class="kodee-dots" aria-hidden="true"><i></i><i></i><i></i></span>
      </div>
    </div>
  </div>

  <!-- Balasan bot (muncul setelah typing) -->
  <div class="kodee-text" id="kodeeBotWrap" style="margin-top:10px" hidden>
    <div style="font-weight:800;margin-bottom:4px">Kodee</div>
    <div id="kodeeReply"><!-- diisi via JS --></div>
  </div>

  
</div>


      <!-- FORM POST SEBENARNYA (standar, sama seperti panduan.php) -->
      <form id="kodeePost" method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>" hidden>
        <input type="hidden" name="csrf" value="<?= $__csrf ?>">
        <input type="hidden" name="nama" value="">
        <input type="hidden" name="username" value="">
        <input type="hidden" name="pesan" value="">
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  const esc = s => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));

  function getActionUrl(panel){
    if (panel && panel.dataset.action) return panel.dataset.action;
    const base = (location.origin + location.pathname).replace(/\/[^/]*$/, '');
    return base + '/panduan_send.php';
  }

  function scrollBottom(){
    const list = document.getElementById('kodeeChatList');
    if (list) list.scrollTop = list.scrollHeight;
  }

  function addUserBubble(n, u, m){
  const list = document.getElementById('kodeeChatList');
  const el = document.createElement('div');
  el.className = 'msg msg-user';
  el.innerHTML = `
    <div class="bubble">
      <div class="kv"><span class="k">Nama :</span> <span class="v">${esc(n)}</span></div>
      <div class="kv"><span class="k">Username :</span> <span class="v">@${esc(u)}</span></div>
      <div class="kv"><span class="k">Pesan :</span> <span class="v">${esc(m)}</span></div>
    </div>`;
  list.appendChild(el);
  scrollBottom();
}


 function addTyping(){
  const list = document.getElementById('kodeeChatList');
  const el = document.createElement('div');
  el.id = 'kodeeTyping';
  el.className = 'msg msg-bot msg-typing';

  // ambil URL logo dari data-logo (fallback ke path default)
  const logo = document.getElementById('kodeePanel')?.dataset.logo || 'images/00.png';

  el.innerHTML =
    `<div class="avatar"><img src="${logo}" alt="" class="kodee-ava"></div>
     <div class="bubble">
       <span class="kodee-spinner" aria-hidden="true"></span>
       <span style="font-weight:700;color:#334155">Processing</span>
       <span class="kodee-dots" aria-hidden="true"><i></i><i></i><i></i></span>
     </div>`;
  list.appendChild(el);
  scrollBottom();
}

  function removeTyping(){
    document.getElementById('kodeeTyping')?.remove();
  }

  function addBotBubble(text){
  const list = document.getElementById('kodeeChatList');
  const el = document.createElement('div');
  el.className = 'msg msg-bot';

  const logo = document.getElementById('kodeePanel')?.dataset.logo || 'images/00.png';

  el.innerHTML =
    `<div class="avatar"><img src="${logo}" alt="" class="kodee-ava"></div>
     <div class="bubble">${esc(text)}</div>`;
  list.appendChild(el);
  scrollBottom();
}


  function setup(){
    const panel = document.getElementById('kodeePanel');
    if (!panel) return;

    // Form tersembunyi utk body POST
    let post = document.getElementById('kodeePostReal');
    if (!post){
      post = document.createElement('form');
      post.id = 'kodeePostReal';
      post.method = 'post';
      post.action = getActionUrl(panel);
      post.hidden = true;
      const fCsrf = document.createElement('input');
      fCsrf.type='hidden'; fCsrf.name='csrf';
      fCsrf.value = panel.dataset.csrf || (document.querySelector('meta[name="csrf-token"]')?.content || '');
      post.appendChild(fCsrf);
      ['nama','username','pesan'].forEach(n=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; post.appendChild(i); });
      document.body.appendChild(post);
    } else {
      post.action = getActionUrl(panel);
    }

    // Kaitkan input
    const nama = document.getElementById('kodeeNama');
    const user = document.getElementById('kodeeUser');
    const msg  = document.getElementById('kodeeMsg');
    const btn  = document.getElementById('kodeeSend');
    if (btn) { btn.type='submit'; btn.setAttribute('form','kodeePostReal'); }
    if (nama) nama.setAttribute('form','kodeePostReal');
    if (user) user.setAttribute('form','kodeePostReal');
    if (msg)  msg .setAttribute('form','kodeePostReal');

    // Prefill
    if (panel.dataset.name && nama && !nama.value) nama.value = panel.dataset.name;
    if (panel.dataset.username && user && !user.value) user.value = panel.dataset.username;

    // Submit -> mode chat
    document.addEventListener('submit', async (e)=>{
      if (e.target.id !== 'kodeePostReal') return;
      e.preventDefault();

      const n = (nama?.value || '').trim();
      const u = (user?.value || '').trim().replace(/^@+/, '');
      const m = (msg ?.value || '').trim();
      if (!nama.checkValidity() || !user.checkValidity() || !msg.checkValidity() || !n || !u || !m) {
  nama.reportValidity(); user.reportValidity(); msg.reportValidity();
  return;
}
      // isi body form
      post.querySelector('input[name="nama"]').value = n;
      post.querySelector('input[name="username"]').value = u;
      post.querySelector('input[name="pesan"]').value = m;

      // sembunyikan form -> tampilkan area chat
      document.getElementById('kodeeForm')?.setAttribute('hidden','');
      document.querySelector('.kodee-actions')?.setAttribute('hidden','');
      document.getElementById('kodeeChat')?.removeAttribute('hidden');

      // bubble user + typing
      addUserBubble(n, u, m);
      addTyping();

      const oldTxt = btn?.textContent;
      if (btn) { btn.disabled = true; btn.textContent = 'Mengirim…'; }

      let replyText = '';
      try{
        const body = new URLSearchParams(new FormData(post)).toString();
        const res = await fetch(post.action, {
          method:'POST',
          headers:{
            'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With':'XMLHttpRequest'
          },
          body, credentials:'same-origin'
        });
        if (res.ok){
          const ct = res.headers.get('content-type') || '';
          if (ct.includes('application/json')){
            const j = await res.json();
            replyText = (j.reply || '').toString();
          }
        } else {
          throw new Error('HTTP ' + res.status);
        }
      } catch(err){
        console.error(err);
        replyText = 'Maaf, terjadi gangguan jaringan. Silakan coba lagi.';
      }

      if (!replyText){
        replyText =
          `Terima kasih atas perhatiannya, ${n}! pesan anda telah terkirimkan ke admin, ` +
          `harap menunggu balasan dari admin melalui telegram @Announc efidi.`;
      }

      // matikan typing, tampilkan balasan
      removeTyping();
      addBotBubble(replyText);

      if (btn) { btn.disabled = false; btn.textContent = oldTxt || 'Kirim'; }
      if (msg) msg.value = '';
    });

    // Toggle panel
    document.addEventListener('click', function(ev){
      const fab = ev.target.closest('#kodeeFab');
      if (fab){
        ev.preventDefault();
        const willOpen = !panel.classList.contains('is-open');
        panel.classList.toggle('is-open');
        fab.setAttribute('aria-expanded', String(willOpen));
        if (willOpen) setTimeout(()=> document.getElementById('kodeeNama')?.focus(), 50);
      }
      if (ev.target.closest('#kodeeClose')){
        ev.preventDefault();
        panel.classList.remove('is-open');
        document.getElementById('kodeeFab')?.setAttribute('aria-expanded','false');
      }
      if (ev.target.closest('.kodee-panel')) ev.stopPropagation();
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup, { once:true });
  } else {
    setup();
  }
})();
</script>

<script>
document.addEventListener('click', function(ev){
  const a = ev.target.closest('.open-chat-admin');
  if (!a) return;
  ev.preventDefault();
  const panel = document.getElementById('kodeePanel');
  const fab   = document.getElementById('kodeeFab');
  if (panel) {
    panel.classList.add('is-open');
    setTimeout(()=> document.getElementById('kodeeNama')?.focus(), 50);
  }
  if (fab) fab.setAttribute('aria-expanded','true');
}, true);
</script>



</body>
</html>
