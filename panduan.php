<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';              // $pdo, csrf_token(), flash()
require_once __DIR__ . '/redirect_helper.php';   // redirect()
require_once __DIR__ . '/telegram/telegram_config.php'; // BOT_USERNAME (opsional)

$ok  = flash('ok');
$err = flash('err');

// data sesi (jika user sudah link telegram)
$ses_uname = (string)($_SESSION['reg_tg_username'] ?? '');
$ses_chat  = (string)($_SESSION['reg_tg_chat'] ?? '');

// nama bot untuk tampilan
$bot = defined('BOT_USERNAME') && BOT_USERNAME !== '' ? (string)BOT_USERNAME : 'OTP_Esiklo_bot';
$bot = ltrim($bot, '@');
$bot_link = "https://t.me/{$bot}";
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>efidi. — Panduan Registrasi</title>
  <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/logoo.png"/>
  <style>
    :root{ --teal:#00A3AD; --ink:#0b0f15; --muted:#64748b; }
    body{ background:#f5f7fb; }
    .wrap{ max-width:680px; margin:28px auto; }
    .card{ background:#fff; border-radius:14px; box-shadow:0 10px 32px rgba(2,8,23,.12); padding:18px 18px; }
    .head{ display:flex; gap:10px; align-items:center; margin-bottom:6px; }
    .head img{ height:32px; }
    .head h3{ margin:0; color:var(--teal); font-weight:600; }
    .muted{ color:var(--muted); }
    ol.steps{ margin:10px 0 0 22px; }
    ol.steps li{ margin:6px 0; line-height:1.45; }
    .btn{ display:inline-flex; align-items:center; gap:8px; border:0; cursor:pointer; text-decoration:none; }
    .btn-primary{ background:var(--teal); color:#fff; padding:10px 12px; border-radius:6px; }
    .btn-ghost{ background: #e7e7e8ff; color:#111827; padding:10px 12px; border-radius:6px; }
    .actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
    .alert{ border-radius:8px; padding:10px 12px; margin:8px 0; font-size:14px }
    .alert.ok{ background:#e0f2fe; color:#082f49; border:1px solid #bae6fd }
    .alert.err{ background:#fee2e2; color:#7f1d1d; border:1px solid #fecaca }
    .form-label{ display:block; margin:.5rem 0 .25rem; font-weight:600; }
    .form-control{ border:1px solid #dfe3ea; height:46px; border-radius:8px; }
    .form-control:focus{ border-color:var(--teal); box-shadow:0 0 0 3px rgba(0,163,173,.12); }
    textarea.form-control{ height:auto; min-height:100px; }
    .subtle{ font-size:12px; color:var(--muted); }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
      <img src="images/logoo.png" alt="e-Siklo">
      <h3>Panduan Registrasi</h3>
    </div>

   

    <p class="muted">Dibawah ini merupakan Panduan Registrasi Akun efidi.</p>

   <ol class="steps">
  <li>Dari halaman <b>Login</b>, klik <b>Register</b> untuk memulai.</li>

  <li>Kamu akan masuk ke halaman <b>Hubungkan Telegram</b>. Scan QR atau klik <b>Open in Telegram</b> untuk membuka bot
      <a href="<?= htmlspecialchars($bot_link) ?>" target="_blank" rel="noopener">@Announc efidi.</a>
      Pastikan Telegram terpasang (mobile/desktop) atau kamu sudah login di Telegram Web.</li>

  <li>Di bot, tekan <b>Start</b> (atau ketik <b>/start</b>) lalu tekan <b>LANJUT</b>.
      Username harus <b>aktif</b> di Telegram (Settings → Username) dan <b>unik</b> (tidak dipakai akun lain).</li>
      
       <li>Setelah <b>LANJUT</b> bot akan menampilkan tombol <b>KEMBALI KE HALAMAN WEBSITE.</b> Ketuk tombol itu untuk kembali ke website.</li>

  <li>Jika username valid, kembali ke halaman website, maka otomatis halaman akan menuju <b>Form Registrasi</b> dan kolom <b>Username</b> sudah terisi otomatis.
      Jika ditolak karena duplikat, halaman tidak berpindah—silakan ganti username Telegram-mu atau hubungi Admin.</li>

  <li>Lengkapi seluruh field pada <b>Form Registrasi</b>, kirim, lalu masukkan <b>kode verifikasi</b> yang kami kirim via bot.
      Kode bersifat sementara demi keamanan.</li>

  <li>Setelah verifikasi selesai kamu kembali ke <b>Login</b>. Kamu <b>belum dapat login</b> sampai Admin memberikan <b>hak akses/role</b>.
      Silakan gunakan form “Chat Admin” di halaman login untuk meminta aktivasi.</li>

  <li>Setelah role diaktifkan, kamu bisa login. Demi keamanan, setiap login akan diminta <b>OTP</b> yang dikirim bot ke telegram anda.</li>
</ol>


    <div class="actions">
      <a class="btn btn-primary" href="qrcode.php">Hubungkan Telegram</a>
      <a class="btn btn-ghost" href="index.php">Kembali ke Login</a>
    </div>


</div>

<script src="vendors/base/vendor.bundle.base.js"></script>
<script src="js/off-canvas.js"></script>
<script src="js/hoverable-collapse.js"></script>
<script src="js/template.js"></script>
</body>
</html>
