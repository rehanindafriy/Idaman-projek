<?php
declare(strict_types=1);
$BOT_USERNAME    = '@searchingefidi_bot';
$BOT_START_PARAM = 'efidi_login'; // bebas: "efidi_login" atau apa saja
$BOT_LINK        = 'https://t.me/' . ltrim($BOT_USERNAME, '@') . '?start=' . urlencode($BOT_START_PARAM);

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>efidi · Login/Register lewat Telegram</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#ffffff">

  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

  <style>
    :root{
      --es-teal:      #00a3ad;
      --es-teal-dark: #00808b;
      --es-deep:      #022733;
      --es-muted:     #64748b;
    }

    *{box-sizing:border-box;margin:0;padding:0;}

    body{
      min-height:100vh;
      margin:0;
      font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
      -webkit-font-smoothing:antialiased;
      background:#ffffff;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:env(safe-area-inset-top,0) 16px env(safe-area-inset-bottom,0);
    }

    .phone-shell{
      width:100%;
      max-width:420px;
      height:100vh;
      max-height:720px;
      background:#ffffff;
      border-radius:28px;
      border:1px solid #e5e7eb;
      padding:32px 22px 20px;
      display:flex;
      flex-direction:column;
      color:#0f172a;
      box-shadow:0 12px 24px rgba(15,23,42,0.04);
      overflow:hidden;
    }

    @media (max-width:480px){
      body{
        padding:0;
      }
      .phone-shell{
        max-width:none;
        max-height:none;
        height:100vh;
        border-radius:0;
        border:none;
        box-shadow:none;
        padding:40px 22px 20px;
      }
    }

    /* ===== HERO TOSCA + WAVE ===== */
    .hero-wave{
      position:relative;
      background:linear-gradient(135deg,var(--es-teal),var(--es-teal));
      margin:-40px -25px 0;
      padding:90px 24px 150px;
      color:#ecfeff;
      overflow:hidden;
    }

    .hero-wave-svg{
      position:absolute;
      left:0;
      bottom:0;
      width:100%;
      height:160px;
      pointer-events:none;
      z-index:0;
    }
    .hero-wave-svg svg{
      width:100%;
      height:100%;
      display:block;
    }

    .hero-inner{
      position:relative;
      z-index:1;
      text-align:center;
    }

    .hello{
      font-size:14px;
      color:#cfeff3;
      margin-bottom:4px;
    }

    .welcome{
      font-size:22px;
      font-weight:700;
      color:#ffffff;
      margin-bottom:6px;
    }

    .welcome span{
      display:block;
      font-size:13px;
      font-weight:500;
      color:#e0f9ff;
      margin-top:2px;
    }

    /* ===== KONTEN TENGAH ===== */
    .content{
      flex:1;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      text-align:center;
      padding:-30px 0 -30px;
    }

    .hero-icon{
      margin-bottom:12px;
    }

    /* LOGO DI TENGAH DIBESARKAN */
    .hero-logo{
      width:230px;      /* sebelumnya 72px */
      height:230px;     /* sebelumnya 72px */
      object-fit:contain;
      display:block;
    }

    /* ===== AREA BAWAH (alert + card putih) ===== */
    .bottom-area{
      margin-top:0;
    }

    .alert-strip{
      font-size:11px;
      color:#1f2937;
      background:#e5f6f7;
      border-radius:10px;
      padding:8px 10px;
      border:1px solid #bae6ec;
      display:flex;
      align-items:flex-start;
      gap:8px;
      margin-bottom:35px;
    }

    .alert-icon{
      width:18px;
      height:18px;
      border-radius:999px;
      background: var(--es-teal);
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:12px;
      color:#ecfeff;
      flex-shrink:0;
    }

    .alert-text-strong{
      font-weight:600;
      display:block;
      margin-bottom:2px;
    }

    .bottom-card{
      background:#ffffff;
      border-radius:20px 20px 0 0;
      box-shadow:0 -6px 18px rgba(15,23,42,0.12);
      margin-left:-22px;
      margin-right:-22px;
      margin-bottom:-20px;
      margin-top:-8px;
      padding:24px 22px 40px;
    }

    .bottom-handle{
      width:40px;
      height:4px;
      border-radius:999px;
      background:#e5e7eb;
      margin:0 auto 18px;
    }

    .cta-area{
      margin:0;
    }

    .cta-btn{
      display:block;
      width:100%;
      text-align:center;
      text-decoration:none;
      padding:12px 16px;
      border-radius:999px;
      background:linear-gradient(135deg,var(--es-teal),var(--es-teal));
      color:#f9fafb;
      font-size:13px;
      font-weight:600;
      letter-spacing:.06em;
      text-transform:uppercase;
      border:none;
      position:relative;
      overflow:hidden;
      box-shadow:0 0px 0px rgba(0,163,173,0.18);
    }

    .cta-btn span.icon{
      margin-left:6px;
      font-size:14px;
      vertical-align:middle;
    }

    .cta-btn::after{
      content:"";
      position:absolute;
      inset:0;
      background:linear-gradient(120deg,rgba(255,255,255,0.3),transparent 60%);
      opacity:0;
      transform:translateX(-60%);
      transition:opacity .16s ease-out, transform .16s ease-out;
    }
    .cta-btn:active::after,
    .cta-btn:focus-visible::after{
      opacity:1;
      transform:translateX(0%);
    }
    
    .back-btn{
  margin-top:10px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  width:100%;
  padding:10px 14px;
  border-radius:999px;
  border:1px solid #e5e7eb;
  background:#f9fafb;
  font-size:13px;
  font-weight:500;
  color:#374151;
  cursor:pointer;
}

.back-btn:active{
  background:#e5e7eb;
}

.back-icon{
  font-size:14px;
  line-height:1;
}

.back-text{
  line-height:1;
}


    .cta-note{
      margin-top:15px;
      font-size:12px;
      color:var(--es-muted);
      line-height:1.5;
    }
    .cta-note b{
      font-weight:600;
      color:#0f172a;
    }

    .foot-mini{
      margin-top:25px;
      font-size:10px;
      color:#9ca3af;
      text-align:right;
    }
  </style>
</head>
<body>
  <div class="phone-shell">

    <!-- HERO TOSCA BERGELOMBANG -->
    <div class="hero-wave">
      <div class="hero-inner">
        <div class="hello">Hello,</div>
        <div class="welcome">
          Welcome to efidi.
          <span>Portal Login &amp; Registrasi Mitra</span>
        </div>
      </div>
    </div>

    <!-- KONTEN TENGAH: HANYA LOGO BESAR -->
    <div class="content">
      <div class="hero-icon">
        <img src="images/ph.png" alt="efidi icon" class="hero-logo">
      </div>
    </div>

    <!-- AREA BAWAH: ALERT + CARD PUTIH -->
    <div class="bottom-area">
      <div class="alert-strip">
        <div class="alert-icon">
          <i class="mdi mdi-shield-account-outline"></i>
        </div>
        <div>
          <span class="alert-text-strong">Akses resmi efidi.</span>
        Silakan klik tombol <b>Login / Register</b> di bawah ini untuk melakukan pendaftaran dengan memulai bot atau menggunakan perintah <b>/start</b>.
Setelah berhasil registrasi, Anda akan menerima tombol <b>Searching Data</b>.

Jika Anda sudah pernah melakukan registrasi sebelumnya, cukup klik kembali tombol <b>Searching Data</b> tersebut, dan Anda akan diarahkan langsung ke halaman pencarian.
        </div>
      </div>

      <div class="bottom-card">
        <div class="bottom-handle"></div>

        <div class="cta-area">
          <a class="cta-btn"
             href="<?= htmlspecialchars($BOT_LINK, ENT_QUOTES, 'UTF-8'); ?>">
            Login / Register melalui Telegram
            <span class="icon">➜</span>
          </a>
          
          <button type="button"
          class="back-btn"
          onclick="if (window.AndroidBridge && AndroidBridge.forceLogout) { AndroidBridge.forceLogout(); }">
    <span class="back-icon">⬅️</span>
    <span class="back-text">Keluar</span>
  </button>

          <div class="cta-note">
            Anda akan diarahkan ke bot
            <b><?= htmlspecialchars($BOT_USERNAME, ENT_QUOTES, 'UTF-8'); ?></b>
            di Telegram untuk verifikasi dan mengaktifkan akses efidi.
          </div>

          <div class="foot-mini">
            Pastikan aplikasi Telegram sudah ter-install di perangkat Anda.
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- JS: generate wave SVG di bawah hero -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var heroWave = document.querySelector('.hero-wave');
      if (!heroWave) return;

      var wrapper = document.createElement('div');
      wrapper.className = 'hero-wave-svg';
      wrapper.setAttribute('aria-hidden', 'true');

      wrapper.innerHTML =
        '<svg viewBox="0 0 1440 320" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">' +
          '<path d="M0,160 C 240,220 480,260 720,240 C 960,220 1200,160 1440,170 L1440,320 L0,320 Z" fill="#ffffff"></path>' +
        '</svg>';

      heroWave.appendChild(wrapper);
    });
  </script>

</body>
</html>
