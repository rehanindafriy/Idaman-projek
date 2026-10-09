<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/redirect_helper.php';
require_once __DIR__ . '/telegram/telegram_config.php'; // BOT_USERNAME

// Ensure table
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS pending_tg (
    token VARCHAR(64) PRIMARY KEY,
    username VARCHAR(64) NULL,
    chat_id VARCHAR(32) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
} catch (Throwable $e) {}

// AJAX check
if (isset($_GET['check'])) {
  header('Content-Type: application/json; charset=utf-8');
  $tok = (string)($_SESSION['tg_link_token'] ?? '');
  if ($tok === '') { echo json_encode(['ok'=>false]); exit; }
  try {
    $st = $pdo->prepare("SELECT username, chat_id FROM pending_tg WHERE token=? LIMIT 1");
    $st->execute([$tok]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $row = false; }
  if ($row && !empty($row['username'])) {
    $_SESSION['reg_tg_username'] = (string)$row['username'];
    $_SESSION['reg_tg_chat']     = (string)($row['chat_id'] ?? '');
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false]); exit;
}

// Normal render
$token = (string)($_SESSION['tg_link_token'] ?? '');
if ($token === '') {
  $token = bin2hex(random_bytes(8));
  $_SESSION['tg_link_token'] = $token;
}

// Normal render
$token = (string)($_SESSION['tg_link_token'] ?? '');
if ($token === '') {
  $token = bin2hex(random_bytes(8));
  $_SESSION['tg_link_token'] = $token;
}

// Ambil username bot & sanitisasi: hapus '@' kalau ada
$bot = defined('BOT_USERNAME') && BOT_USERNAME !== '' ? (string)BOT_USERNAME : 'OTP_Esiklo_bot';
$bot = ltrim($bot, '@'); // <-- penting: hapus '@'

$payload = "link-" . $token;

// Deep-link ke Telegram Web & App (tanpa '@')
$tg_web = "https://t.me/{$bot}?start=" . rawurlencode($payload);
$tg_app = "tg://resolve?domain={$bot}&start=" . rawurlencode($payload);

// QR pakai link web (paling kompatibel)
$qr_img = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" . urlencode($tg_web);

?>

<style>
  :root{
    --brand-1:#05d3d2; --brand-2:#03c8c6; --brand-3:#20a1ad; --brand-4:#12824f;
    --text:#0b0f15; --muted:#6b7280; --card:#ffffff; --ring:#eef2ff;
  }
  .qr-card{max-width:760px;margin:18px auto;background:var(--card);border-radius:16px;box-shadow:0 10px 30px rgba(16,24,40,.08);padding:22px 22px 18px}
  .qr-head{display:flex;align-items:center;gap:12px;margin-bottom:6px}
  .qr-head .badge{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--brand-1),var(--brand-3));display:grid;place-items:center;color:#fff;font-weight:700;font-size:20px;box-shadow:0 8px 20px rgba(3,200,198,.25)}
  .qr-title{font-size:26px;font-weight:800;color:#0f3534;letter-spacing:.2px}
  .qr-desc{color:#6b7280;margin:6px 0 16px;line-height:1.55}
  .qr-layout{display:grid;grid-template-columns:1fr 1.2fr;gap:22px}
  @media (max-width:720px){.qr-layout{grid-template-columns:1fr}}
  .qr-box{position:relative;width:320px;max-width:100%;aspect-ratio:1/1;padding:12px;border-radius:18px;background:#fff;box-shadow:0 10px 26px rgba(2,32,56,.08);display:grid;place-items:center;justify-self:start}
  .qr-box img,.qr-box canvas,.qr-box svg.qr{width:100%;height:100%;object-fit:contain;border-radius:14px;image-rendering:pixelated}
  .qr-badge{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:54px;height:54px;border-radius:50%;background:#2AABEE;display:grid;place-items:center;box-shadow:0 6px 18px rgba(42,171,238,.45),inset 0 0 0 3px #fff}
  .qr-badge svg{width:28px;height:28px;fill:#fff}
  .qr-steps{margin:0;padding-left:18px;color:#0b0f15}
  .qr-steps li{margin-bottom:8px}
  .qr-steps b{font-weight:800}
  .qr-actions{margin-top:14px;display:flex;gap:10px;flex-wrap:wrap}
  .btn-tg{appearance:none;border:0;cursor:pointer;padding:12px 18px;border-radius:12px;font-weight:700;background:linear-gradient(135deg,var(--brand-1),var(--brand-4));color:#fff;box-shadow:0 8px 16px rgba(5,211,210,.25);transition:transform .06s ease;text-decoration:none;display:inline-block}
  .btn-tg:active{transform:translateY(1px)}
  .qr-fallback{font-size:.92rem;color:#6b7280}
  .qr-fallback a{color:#20a1ad;font-weight:600;text-decoration:none}
</style>

<!doctype html>
<html lang="id">
<head>
  
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>efidi. — Hubungkan Telegram</title>
  <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/logoo.png" />
  <style>
    :root{ --es1:#05d3d2; --es4:#12824f; --ink:#0b0f15; }
    body{ background:#f5f7fb; }
    #card{max-width:960px;margin:42px auto;background:#fff;border-radius:14px;box-shadow:0 10px 40px rgba(2,8,23,.12);padding:20px 22px;}
    .title{display:flex;align-items:center;gap:10px;margin-bottom:8px}
    .title img{height:36px}
    .title h4{margin:0;color:#00A3AD;font-weight:600}
    /* GANTI baris .qr-wrap lama (flex) menjadi: */
/* Grid 2 kolom: kiri (visual), kanan (teks & tombol) */
.qr-wrap{
  display:grid;
  grid-template-columns:auto 1fr;
  gap:18px;
  align-items:start;
}

/* Dua visual berdampingan: QR + frame HP (240px sejajar) */
.visuals{
  display:grid;
  grid-template-columns:240px 240px;
  gap:18px;
}

/* QR tetap dalam kotak putih */
.qr{
  width:245px; height:245px;
  border-radius:12px;
  border:1px solid #e5e7eb;
  padding:10px; background:#fff;
  box-shadow:0 10px 26px rgba(2,32,56,.08);
  object-fit:contain;
}

/* Frame HP: TANPA background putih / padding / border */
.phone-sq{
  width:290px; height:290px;
  border:0; padding:0; background:transparent; box-shadow:none;
  border-radius:12px;
  object-fit:contain; /* jaga proporsi */
}

/* Responsif */
@media (max-width:720px){
  .qr-wrap{ grid-template-columns:1fr; }
  .visuals{ grid-template-columns:1fr 1fr; }
  .qr, .phone-sq{ width:100%; height:auto; aspect-ratio:1/1; }
}


    .qr{border-radius:12px;border:1px solid #e5e7eb;padding:10px;background:#fff}
    .note{color:#475569;font-size:14px}
    .step{margin:6px 0}
    .btn-open{display:inline-flex;align-items:center;gap:8px;padding:8px 8px;border-radius:2px;border:1px solid #00A3AD;background:#00A3AD;color:#fff;text-decoration:none;font-weight:500;cursor:pointer}
    
    #toast{position:fixed;top:14px;right:14px;background:#fff;border-radius:14px;border:2px solid transparent;padding:10px 12px;box-shadow:0 10px 30px rgba(2,6,23,.15);
      background:linear-gradient(#fff,#fff) padding-box, linear-gradient(135deg,var(--es1),var(--es4)) border-box;}
    .help{font-size:12px;color:#64748b;margin-top:6px}
  </style>
</head>
<body>
<div id="card">
  <div class="title">
    <img src="images/logoo.png" alt="logo">
    <h4>Sebelum Registrasi, Silakan Hubungkan Telegram Anda terlebih dahulu</h4>
  </div>
  <p class="note">Silakan <b>Scan QR</b> berikut atau klik tombol di bawah untuk membuka chat bot di Telegram. Kami otomatis akan mengarahkan Anda ke aplikasi Telegram jika tersedia, atau ke Telegram Web sebagai cadangan.</p>
  <div class="qr-wrap">
  <div class="visuals">
   <div id="qrStyled" class="qr"></div>
<noscript>
  <img class="qr" src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=https%3A%2F%2Ft.me%2FOTP_Esiklo_bot" alt="QR Telegram">
</noscript>

    <img class="phone-sq" src="images/12.png" width="240" height="240" alt="Frame HP (Kontak e-Siklo)">
  </div>

  <div>
    <div class="step">1. Buka Bot Telegram <i>"Announc edifi."</i></div>
    <div class="step">2. Lalu ketik <span style="color: blue;">/start</span> Pada bot</div>
    <div class="step">3. Tekan tombol <b>KIRIM USERNAME TELEGRAM</b> Untuk melakukan Aktivasi akun sebelum registrasi</div>
    <div class="step">4. Setelah username terbaca oleh Bot, halaman ini akan otomatis menuju formulir registrasi</div>
    <div class="step">5. Jika <b>sudah</b> memiliki akun, silakan kembali ke halaman <a href="login.php">Login</a>, Jika <b>belum</b> memiliki akun, silakan ikuti langkah-langkah Registrasi pada poin 1–4</div>
    <div style="margin-top:15px">
      <button type="button" class="btn-open" id="btnOpenTG"><i class="mdi mdi-qrcode-scan"></i>Open in Telegram</button>
      <div class="help">Jika aplikasi tidak terbuka otomatis, kami akan mengarahkan ke <a href="<?= htmlspecialchars($tg_web) ?>" target="_blank" rel="noopener">Telegram Web</a>.</div>
    </div>
  </div>
</div>

<br>
<span style="color: red; font-size: 11px;">* Aktivasi ini hanya perlu dilakukan satu kali sebelum Anda bisa melanjutkan ke tahap pendaftaran.</span>

  <div id="toast" style="display:none"></div>
</div>

<script>
(function(){
  const tgApp = <?= json_encode($tg_app) ?>;
  const tgWeb = <?= json_encode($tg_web) ?>;
  const btn   = document.getElementById('btnOpenTG');

  // Smart open: coba open schema tg:// (Desktop/Mobile App). Kalau gagal -> fallback ke https://t.me
  function openTelegramSmart(){
    let done = false;

    const goWeb = () => { if(!done){ done = true; window.open(tgWeb, '_blank', 'noopener'); } };

    // 1) Coba via tg:// (akan ter-handle oleh Telegram Desktop/Mobile jika terpasang)
    // Cara aman: gunakan iframe tersembunyi agar tidak memblok UI; banyak browser menolak window.location tg://.
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = tgApp;
    document.body.appendChild(iframe);

    // 2) Fallback jika tidak pindah fokus dalam ~1 detik
    const timer = setTimeout(goWeb, 1100);

    // 3) Jika tab menjadi hidden (berpindah ke app), hentikan fallback
    document.addEventListener('visibilitychange', function onVC(){
      if (document.hidden) { done = true; clearTimeout(timer); }
      document.removeEventListener('visibilitychange', onVC);
      setTimeout(()=>iframe.remove(), 1500);
    });
  }

  btn.addEventListener('click', function(e){
    e.preventDefault();
    openTelegramSmart();
  });

  // Poll qrcode.php?check=1 sampai username ada, lalu redirect register.php
  let tries = 0;
  function tick(){
    fetch('qrcode.php?check=1', {cache:'no-store'}).then(r=>r.json()).then(j=>{
      if(j && j.ok){ setTimeout(()=>{ location.replace('register.php'); }, 300); }
      else if(++tries < 120){ setTimeout(tick, 1000); }
    }).catch(()=>{ if(++tries < 120) setTimeout(tick, 1200); });
  }
  setTimeout(tick, 800);
})();
</script>
<!-- Lib QR styling dari CDN -->
<script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
<script>
(function(){
  // Target URL QR: langsung ke bot (tanpa payload)
  const TG_URL = "https://t.me/OTP_Esiklo_bot";

  // Buat QR styled 240×240, dot rounded, finder rounded, dengan logo di tengah
  const qr = new QRCodeStyling({
    width: 225,
    height: 225,
    type: "svg", // tajam di retina
    data: TG_URL,
    margin: 1,
    qrOptions: { errorCorrectionLevel: "Q" }, // ruang buat logo
    dotsOptions: {
      color: "#000000",
      type: "rounded"      // bentuk dot bulat
    },
    cornersSquareOptions: {
      type: "extra-rounded" // finder bulat kaya contoh
    },
    cornersDotOptions: {
      type: "dot"
    },
    backgroundOptions: { color: "#ffffff" },
    image: "images/telegram.webp", // logo di tengah
    imageOptions: {
      hideBackgroundDots: true,
      imageSize: 0.25,     // ±25% dari QR
      margin: 2,
      crossOrigin: "anonymous"
    }
  });

  const mount = document.getElementById("qrStyled");
  if (mount) {
    // Pastikan kotak visual tetap style yang kamu pakai (shadow putih dsb.)
    qr.append(mount);
  } else {
    // Fallback: jika kontainer tidak ada, buat <img> sederhana
    const img = document.createElement("img");
    img.className = "qr";
    img.alt = "QR Telegram";
    img.src = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" + encodeURIComponent(TG_URL);
    document.querySelector(".visuals")?.prepend(img);
  }
})();
</script>

</body>
</html>
