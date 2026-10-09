<?php
require_once __DIR__ . '/auth.php';
$err = flash('err'); $ok = flash('ok');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>e-siklo — Sign In</title>
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
                  <h4 class="brand-title">Welcome Back ! Login e-siklo</h4>
                  <h6 class="font-weight-light brand-sub">Please sign in to continue to your account.</h6>
                </div>
              </div>

              <?php if($err): ?><div class="es-alert err"><i class="mdi mdi-alert-circle-outline"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>
              <?php if($ok):  ?><div class="es-alert ok"><i class="mdi mdi-check-circle-outline"></i> <?= htmlspecialchars($ok) ?></div><?php endif; ?>

              <form class="pt-3" method="post" action="login_process.php" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

              <div class="form-group">
  <label for="login_username" class="form-label">Username</label>
  <input id="login_username" type="text" name="username"
         class="form-control form-control-lg es-input"
         placeholder="Username" required>
</div>

<div class="form-group">
  <label for="login_password" class="form-label">Password</label>
  <input id="login_password" type="password" name="password"
         class="form-control form-control-lg es-input"
         placeholder="Password" required>
</div>

<div class="btn-row">
  <a href="pending.php" class="verify-link">Input kode verifikasi</a>
  <button class="es-btn-text" type="submit">SIGN IN</button>
</div>


                

               <div class="text-center mt-4 font-weight-light es-footnote">
  Don't you have an account? <a href="register.php" class="text-primary"> Register</a>
</div>

              </form>

            </div> <!-- /#esLoginCard -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="vendors/base/vendor.bundle.base.js"></script>
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
</body>
</html>
