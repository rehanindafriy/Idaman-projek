<?php
require_once __DIR__ . '/auth.php';
$prefill_tg_username = $_SESSION['reg_tg_username'] ?? '';
$is_done_yes = false;
if ($prefill_tg_username !== '') {
  try {
    $st = $pdo->prepare("SELECT LOWER(done) FROM akses_login WHERE LOWER(username)=LOWER(?) LIMIT 1");
    $st->execute([$prefill_tg_username]);
    $d = $st->fetchColumn();
    if ($d !== false && $d === 'yes') $is_done_yes = true;
  } catch (Throwable $e) {}
}

require_guest();   // sekarang aman: tidak akan auto-login dari cookie di halaman ini
$err = flash('err'); $ok = flash('ok');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>efidi. — Register</title>
  <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/logoo.png" />
  <style>
    :root { --es-teal:#00A3AD; }
    #esRegisterCard{
      max-width: 520px; margin:18px auto; border-radius:14px; background:#fff;
      box-shadow: 0 10px 48px rgba(0,0,0,.13), 0 3px 10px rgba(0,0,0,.08);
      padding:20px 22px;
    }
    .brand-row{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
    .brand-row img{ height:36px; width:auto; }
    .brand-title{ margin:0; font-weight:520; color:var(--es-teal); }
    .brand-sub{ margin:2px 0 0; }

    .es-alert{border-radius:6px;padding:10px 12px;margin-bottom:12px;font-size:14px}
    .es-alert.err{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
    .es-alert.ok{background:#e0f2fe;color:#082f49;border:1px solid #bae6fd}

    .form-label{ display:block; margin:8px 2px 6px; font-weight:100; font-size:14px; color:#111827; }
    .es-input.form-control{ border:1px solid #dfe3ea; border-radius:8px; height:48px; box-shadow:none; }
    .es-input.form-control:focus{ border-color:#00A3AD; box-shadow:0 0 0 3px rgba(0,163,173,.12); }

    .btn-row{ display:flex; justify-content:flex-end; margin-top:8px; }
    
    /* captcha row: gambar | refresh | input (ikon kunci) */
    .captcha-row{ display:flex; align-items:center; gap:10px; }
    .captcha-img{ width:130px; height:44px; border-radius:6px; border:1px solid #dfe3ea; background:#eee; }
    .captcha-refresh{ width:44px; height:44px; border-radius:8px; border:1px solid #dfe3ea; background:#fff; display:flex; align-items:center; justify-content:center; }
    .captcha-refresh i{ font-size:20px; color:#334155; }
    .captcha-input .mdi{ position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b; }
    .captcha-input input{ padding-left:40px; }

    .es-footnote{ font-size:13px; line-height:1.4; }
    .es-footnote a{ font-size:inherit; }
    /* Tombol teks compact (tanpa ikon) */
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


    @media (max-width:576px){ #esRegisterCard{ max-width:92vw; padding:18px; } }
    
    
        /* Eye icon: center di dalam input */
.form-group.has-eye .es-input-wrap { position: relative; }

.form-group.has-eye .es-input {
  padding-right: 42px !important; /* ruang untuk ikon di kanan */
}

.password-toggle{
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
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
            <div id="esRegisterCard" class="auth-form-light text-left">
<br>
              <div class="brand-row">
                <img src="images/logoo.png" alt="logo">
                <div>
                  <h4 class="brand-title">Welcome ! Register efidi.</h4>
                  <h6 class="font-weight-light brand-sub">Join us and get started in just a few seconds.</h6>
                </div>
              </div>

              <?php if($err): ?><div class="es-alert err"><i class="mdi mdi-alert-circle-outline"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>
             <?php if ($is_done_yes): ?>
  <div class="es-alert warn">
    <i class="mdi mdi-alert-circle-outline"></i> Username ini sudah menyelesaikan proses registrasi. Silakan kembali ke halaman  <a href="login.php" class="text-primary">login</a>.
  </div>
<?php endif; ?>
              <?php if($ok):  ?><div class="es-alert ok"><i class="mdi mdi-check-circle-outline"></i> <?= htmlspecialchars($ok) ?></div><?php endif; ?>

              <form method="post" action="register_process.php" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="form-group">
                  <label for="reg_name" class="form-label">Name</label>
                  <input id="reg_name" type="text" name="nama" class="form-control form-control-lg es-input" placeholder="Full name" required>
                </div>

                <div class="form-group">
                  <label for="reg_username" class="form-label">Username <small style="color: red;">(Harus Sesuai dengan username Telegram) </small></label>
                  <input type="text" name="username"
       value="<?= htmlspecialchars($prefill_tg_username) ?>"
       <?php if (!empty($prefill_tg_username)): ?>readonly<?php endif; ?>
       class="form-control" required>

                </div>
                

                              <!-- Password -->
<div class="form-group has-eye">
  <label for="reg_password" class="form-label">Password</label>
  <div class="es-input-wrap">
    <input id="reg_password" type="password" name="password"
           class="form-control form-control-lg es-input"
           placeholder="Password" minlength="6" required>

    <button type="button"
            class="password-toggle"
            aria-label="Tampilkan password"
            aria-pressed="false"
            title="Tampilkan password">
      <i class="mdi mdi-eye-off"></i>
    </button>
  </div>
</div>

<!-- Confirm Password -->
<div class="form-group has-eye">
  <label for="reg_password2" class="form-label">Confirm Password</label>
  <div class="es-input-wrap">
    <input id="reg_password2" type="password" name="password2"
           class="form-control form-control-lg es-input"
           placeholder="Confirm password" minlength="6" required>

    <button type="button"
            class="password-toggle"
            aria-label="Tampilkan password"
            aria-pressed="false"
            title="Tampilkan password">
      <i class="mdi mdi-eye-off"></i>
    </button>
  </div>
</div>


                <!-- Captcha -->
                <div class="form-group">
                  <label class="form-label">Code</label>
                  <div class="captcha-row">
                    <img id="captcha_image" class="captcha-img" src="captcha.php?ts=<?= time() ?>" alt="captcha">
                    <button class="captcha-refresh" type="button" aria-label="Refresh captcha" onclick="refreshCaptcha()"><i class="mdi mdi-refresh"></i></button>
                    <div class="position-relative captcha-input" style="flex:1;">
                      <i class="mdi mdi-lock"></i>
                      <input type="text" name="captcha" class="form-control es-input" placeholder="Masukkan Code" inputmode="numeric" autocomplete="off" required>
                    </div>
                  </div>
                </div>
<style>

  /* alert umum sudah ada: .es-alert, .es-alert.err, .es-alert.ok */
/* tambahkan variasi KUNING */
.es-alert.warn{
  background:#fff7d6;            /* kuning muda */
  border:1px solid #fde68a;
  color:#7a5600;                  /* coklat tua */
}
.es-alert.warn .mdi{
  color:#d97706;
  margin-right:6px;
  vertical-align:-2px;
}

  .es-btn-text:disabled,
.es-btn-text[disabled]{
  opacity:.6; cursor:not-allowed; filter:grayscale(.15);
}

</style>
                <!-- Submit ikon -->
             <div class="btn-row">
  <button id="btnSignUp" class="es-btn-text" type="submit" <?= $is_done_yes ? 'disabled' : '' ?>>SIGN UP</button>
</div>




                <div class="text-center mt-4 font-weight-light es-footnote">
                  Already have an account? <a href="login.php" class="text-primary"> Login</a>
                </div>
              </form>
            </div><!-- /#esRegisterCard -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function refreshCaptcha(){
      var img = document.getElementById('captcha_image');
      img.src = 'captcha.php?ts=' + Date.now(); // anti-cache
    }
  </script>
  <script src="vendors/base/vendor.bundle.base.js"></script>
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
  <?php if (!empty($prefill_tg_username)): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var el = document.querySelector('input[name="username"]');
  if (el) {
    el.value = <?= json_encode($prefill_tg_username) ?>;
    el.setAttribute('readonly','readonly');
    el.style.background = '#f1f5f9';
    el.title = 'Diisi otomatis dari Telegram';
  }
});
</script>
<?php endif; ?>

<script>
(function(){
  const input = document.getElementById('reg_username');
  const btn   = document.getElementById('btnSignUp');
  const hint  = document.getElementById('unameHint');
  let timer   = null;

  function setHint(text, color){ if(hint){ hint.textContent = text; if(color) hint.style.color = color; } }

  async function checkUsername(){
    const raw = (input.value || '').trim().replace(/^@/,'');
    input.setCustomValidity('');

    if (raw === '') { 
      btn.disabled = true;
      setHint('(Harus Sesuai dengan username Telegram)', 'red');
      return;
    }

    try{
      const r = await fetch('ajax/check_username_done.php?u=' + encodeURIComponent(raw), {cache:'no-store'});
      const j = await r.json();

      if (!j.ok) { /* server error, jangan kunci form */ return; }

      if (!j.exists) {
        input.setCustomValidity('USERNAME TIDAK ADA');
        input.reportValidity();
        btn.disabled = true;
        setHint('USERNAME TIDAK ADA', '#ef4444');
      } else if ((j.done || '').toLowerCase() === 'yes') {
        // sudah terdaftar lengkap → nonaktifkan signup
        btn.disabled = true;
        setHint('USERNAME TELAH TERDAFTAR', '#ef4444');
      } else {
        // username ada tapi belum "done=yes" → boleh daftar
        btn.disabled = false;
        setHint('(Harus Sesuai dengan username Telegram)', 'red');
      }
    } catch(e){
      // jaringan error: jangan blok user
    }
  }

  if (input && btn){
    // initial (untuk prefill & readonly)
    document.addEventListener('DOMContentLoaded', checkUsername);

    input.addEventListener('input', () => {
      input.setCustomValidity('');
      clearTimeout(timer);
      timer = setTimeout(checkUsername, 250); // debounce
    });
    input.addEventListener('blur', checkUsername);
  }
})();
</script>

<script>
(() => {
  // Toggle untuk semua tombol .password-toggle pada halaman
  document.querySelectorAll('.password-toggle').forEach(btn => {
    const wrap  = btn.closest('.es-input-wrap');
    const input = wrap ? wrap.querySelector('input') : null;
    if (!input) return;

    btn.addEventListener('click', () => {
      const nowShow = input.type === 'password';
      input.type = nowShow ? 'text' : 'password';

      btn.setAttribute('aria-pressed', String(nowShow));
      btn.setAttribute('aria-label', nowShow ? 'Sembunyikan password' : 'Tampilkan password');
      btn.title = nowShow ? 'Sembunyikan password' : 'Tampilkan password';

      const icon = btn.querySelector('i');
      icon.classList.toggle('mdi-eye', nowShow);
      icon.classList.toggle('mdi-eye-off', !nowShow);
    });
  });
})();
</script>

</body>
</html>
