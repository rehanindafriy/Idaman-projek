<?php
require_once __DIR__ . '/auth.php';
require_guest();   // sekarang aman: tidak akan auto-login dari cookie di halaman ini
$err = flash('err'); $ok = flash('ok');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>e-Siklo — Register</title>
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
                  <h4 class="brand-title">Welcome ! Register e-siklo</h4>
                  <h6 class="font-weight-light brand-sub">Join us and get started in just a few seconds.</h6>
                </div>
              </div>

              <?php if($err): ?><div class="es-alert err"><i class="mdi mdi-alert-circle-outline"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>
              <?php if($ok):  ?><div class="es-alert ok"><i class="mdi mdi-check-circle-outline"></i> <?= htmlspecialchars($ok) ?></div><?php endif; ?>

              <form method="post" action="register_process.php" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="form-group">
                  <label for="reg_name" class="form-label">Name</label>
                  <input id="reg_name" type="text" name="nama" class="form-control form-control-lg es-input" placeholder="Full name" required>
                </div>

                <div class="form-group">
                  <label for="reg_username" class="form-label">Username</label>
                  <input id="reg_username" type="text" name="username" class="form-control form-control-lg es-input" placeholder="Username" required>
                </div>

                <div class="form-group">
                  <label for="reg_password" class="form-label">Password</label>
                  <input id="reg_password" type="password" name="password" class="form-control form-control-lg es-input" placeholder="Password" minlength="6" required>
                </div>

                <div class="form-group">
                  <label for="reg_password2" class="form-label">Confirm Password</label>
                  <input id="reg_password2" type="password" name="password2" class="form-control form-control-lg es-input" placeholder="Confirm password" minlength="6" required>
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

                <!-- Submit ikon -->
              <div class="btn-row">
  <button class="es-btn-text" type="submit">SIGN UP</button>
</div>



                <div class="text-center mt-4 font-weight-light es-footnote">
                  Already have an account? <a href="index.php" class="text-primary"> Login</a>
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
</body>
</html>
