<?php
require_once __DIR__.'/auth.php';

$err = flash('err'); 
$ok  = flash('ok');

$pending_uname = $_SESSION['pending_uname'] ?? '';
$pending_uid   = $_SESSION['pending_uid'] ?? null;



/* KUNCI FORM: hanya ACTIVE yang boleh isi */


?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>efidi. — Verifikasi Akun</title>
  <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/logoo.png" />
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

  <style>
    :root{ --es-teal:#00A3AD; }
    #esPendingCard{ max-width:420px;margin:18px auto;border-radius:14px;background:#fff;
      box-shadow:0 10px 48px rgba(0,0,0,.13),0 3px 10px rgba(0,0,0,.08);padding:20px 22px;}
    .brand-row{display:flex;align-items:center;gap:12px;margin-bottom:8px}
    .brand-row img{height:36px;width:auto}
    .brand-title{margin:0;font-weight:700;color:var(--es-teal)}
    .brand-sub{margin:2px 0 0}

    /* ALERTS */
    .es-alert{border-radius:8px;padding:10px 12px;margin:10px 0;font-size:14px;border:1px solid transparent}
    .es-alert.info{background:#e0f2fe;color:#075985;border-color:#bae6fd}          /* biru */
    .es-alert.warn{background:#fff7ed;color:#7c2d12;border-color:#fdba74}          /* kuning/oranye */
    .es-alert.success{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}       /* hijau */
    .es-alert.danger{background:#fee2e2;color:#7f1d1d;border-color:#fecaca}        /* merah */

    .form-label{display:block;margin:10px 2px 6px;font-weight:100;font-size:14px;color:#111827}
    .es-input.form-control{border:1px solid #dfe3ea;border-radius:8px;height:48px;box-shadow:none}
    .es-input.form-control:focus{border-color:#00A3AD;box-shadow:0 0 0 3px rgba(0,163,173,.12)}

    .btn-row{display:flex;justify-content:flex-end;margin-top:10px}
    .es-btn-text{background:#00A3AD!important;border:1px solid #00A3AD!important;color:#fff!important;
      height:42px;min-width:70px;padding:0 10px;font-size:12px;line-height:1;border-radius:2px;
      display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,.16)}
    .es-btn-text:hover{filter:brightness(.95);transform:translateY(-1px)}
    .es-btn-text:active{transform:translateY(0)}
    .muted{color:#475569}
    /* OTP 6 digit */
.otp-group{ display:flex; gap:8px; }
.otp-input{
  width:44px; height:48px;
  border:1px solid #dfe3ea; border-radius:8px;
  text-align:center;
  font-size:20px; font-weight:600;
  box-shadow:none;
}
.otp-input:focus{
  border-color:#00A3AD;
  box-shadow:0 0 0 3px rgba(0,163,173,.12);
  outline:none;
}
@media (max-width:576px){
  .otp-input{ width:40px; height:44px; font-size:18px; }
}



  </style>

<!-- === TOAST (SAMA PERSIS DENGAN pending.php) === -->
<style>
  :root{
    /* e-Sikló palette */
    --brand-1:#05d3d2; --brand-2:#03c8c6; --brand-3:#20a1ad; --brand-4:#12824f;
    --ink:#0b0f15;

    --ring-grad:linear-gradient(135deg,var(--brand-1),var(--brand-4));
    --shadow:0 14px 40px rgba(2,8,23,.16);
  }

  #toast-container{
    position:fixed; top:14px; right:14px; z-index:9999;
    display:flex; flex-direction:column; gap:12px;
    pointer-events:none;
  }

  .toast{
    pointer-events:auto;
    position:relative; display:grid; grid-template-columns:36px 1fr auto;
    align-items:center; gap:12px;
    min-width:300px; max-width:440px;

    padding:14px 14px 16px 12px; border-radius:14px;
    /* white card + gradient border */
    background:
      linear-gradient(#fff,#fff) padding-box,
      var(--ring-grad) border-box;
    border:2px solid transparent;
    box-shadow:var(--shadow);
    color:var(--ink);

    transform:translateY(-10px) scale(.98);
    opacity:0; animation:toast-enter .45s cubic-bezier(.22,1,.36,1) forwards;
  }

  /* strip aksen kiri */
  .toast::before{
    content:""; position:absolute; inset:0 auto 0 0; width:6px; border-radius:inherit;
    background:var(--ring-grad);
  }

  /* ikon bulat kiri (ring gradient) */
  .toast .icon{
    display:grid; place-items:center; width:36px; height:36px; border-radius:999px;
    background:
      linear-gradient(#ffffff,#ffffff) padding-box,
      var(--ring-grad) border-box;
    border:2px solid transparent;
  }
  .toast .icon svg{ width:18px; height:18px; stroke:var(--ink) }

  /* pesan */
  .toast .msg{ font-size:.98rem; line-height:1.4; }

  /* tombol tutup */
  .toast .close{
    appearance:none; border:0; background:transparent; color:inherit;
    width:30px; height:30px; border-radius:8px; cursor:pointer; opacity:.7;
    display:grid; place-items:center;
  }
  .toast .close:hover{ opacity:1; background:rgba(2,8,23,.06); }

  /* progress bar bawah */
  .toast .bar{
    position:absolute; left:12px; right:12px; bottom:8px; height:3px; border-radius:999px;
    background:linear-gradient(90deg,var(--brand-4),var(--brand-2));
    transform-origin:left center; animation:bar linear forwards; opacity:.9;
  }
  .toast:hover .bar{ animation-play-state:paused; }

  /* state */
  .toast.ok .icon svg{ stroke:#12824f; }
  .toast.err .icon svg{ stroke:#d63031; }
  .toast.info .icon svg{ stroke:#0b0f15; }
  .toast.notif .icon svg{ stroke:#0b0f15; }

  @keyframes bar{ from{transform:scaleX(1)} to{transform:scaleX(0)} }
  @keyframes toast-enter{ to{ transform:translateY(0) scale(1); opacity:1 } }
  @keyframes toast-exit{ to{ transform:translateX(40px); opacity:0 } }

  @media (prefers-reduced-motion: reduce){
    .toast{ animation-duration:.1s }
    .toast .bar{ display:none }
  }
</style>

<script>
(function(){
  // container
  let cont = document.getElementById('toast-container');
  if(!cont){
    cont = document.createElement('div');
    cont.id = 'toast-container';
    cont.setAttribute('aria-live','polite');
    cont.setAttribute('aria-atomic','true');
    document.body.appendChild(cont);
  }

  // ikon: notif (bell) default, ok (check), err (warning)
  function iconSVG(type){
    if(type==='ok'){
      return '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>';
    }
    if(type==='err'){
      return '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M12 8v6"/><path d="M12 18h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>';
    }
    // bell (notif/info)
    return '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 5 3 9H3c0-4 3-2 3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
  }

  function showToast(text, type='notif', opts={}){
    if(!text) return;
    const duration = Math.max(2200, Number(opts.duration||5200));

    const el = document.createElement('div');
    el.className = 'toast ' + (type||'notif');
    el.setAttribute('role','alert');

    const ic = document.createElement('span');
    ic.className = 'icon';
    ic.innerHTML = iconSVG(type);

    const msg = document.createElement('div');
    msg.className = 'msg';
    msg.textContent = text;

    const btn = document.createElement('button');
    btn.className = 'close'; btn.type = 'button';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>';
    btn.addEventListener('click', () => dismiss(el));

    const bar = document.createElement('div');
    bar.className = 'bar';
    bar.style.animationDuration = duration + 'ms';

    el.append(ic, msg, btn, bar);

    // cascade kecil
    const offset = Math.min(cont.children.length, 5) * 60;
    el.style.animationDelay = offset + 'ms';
    cont.appendChild(el);

    // auto dismiss
    let timer = setTimeout(()=> dismiss(el), duration + offset + 120);

    // drag ke kanan untuk dismiss
    let startX = 0, dx = 0, dragging = false;
    el.addEventListener('pointerdown', e => { dragging = true; startX = e.clientX; el.setPointerCapture(e.pointerId); clearTimeout(timer); });
    el.addEventListener('pointermove', e => {
      if(!dragging) return;
      dx = e.clientX - startX;
      if(dx>0){
        el.style.transform = `translateX(${dx}px)`;
        el.style.opacity = String(Math.max(0, 1 - dx/180));
      }
    });
    el.addEventListener('pointerup', () => {
      dragging = false;
      if(dx > 90){ dismiss(el); } else {
        el.style.transition = 'transform .18s ease, opacity .18s ease';
        el.style.transform = ''; el.style.opacity = '';
        setTimeout(()=> el.style.transition='', 200);
        // lanjutkan timer
        timer = setTimeout(()=> dismiss(el), 1200);
      }
    });

    // pause saat hover
    el.addEventListener('mouseenter', ()=> clearTimeout(timer));
  }

  function dismiss(el){
    if(!el || el._closing) return;
    el._closing = true;
    el.style.animation = 'toast-exit .22s ease forwards';
    setTimeout(()=> el.remove(), 220);
  }

  // === Integrasi PHP flash (samakan dengan pending.php) ===
  const messages = [];
  <?php if (!empty($ok)): ?> messages.push({text: <?= json_encode($ok) ?>, type:'ok'}); <?php endif; ?>
  <?php if (!empty($err)): ?> messages.push({text: <?= json_encode($err) ?>, type:'err'}); <?php endif; ?>

  // Pesan default
  messages.push({ text:'Kode verifikasi telah dikirim ke Telegram Anda. Masukkan 7 digit kodenya di bawah ini.', type:'notif' });

  if(messages.length){
    let i = 0;
    showToast(messages[0].text, messages[0].type, {duration:5200});
    setInterval(()=>{
      i=(i+1)%messages.length;
      const m = messages[i];
      showToast(m.text, m.type, {duration:5200});
    }, 10000);
  }

  // expose
  window.showToast = showToast;
})();
</script>
<!-- === END TOAST === -->


</head>
<body>
  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-center auth px-0">
        <div class="row w-100 mx-0">
          <div class="col-lg-4 mx-auto">
            <div id="esPendingCard" class="auth-form-light text-left">
              <div class="brand-row">
                <img src="images/logoo.png" alt="logo">
                <div>
                  <h4 class="brand-title">Verifikasi Akun</h4>
                  <h6 class="font-weight-light brand-sub">Masukkan kode verifikasi.</h6>
                </div>
              </div>
              <!-- TOASTS -->


              <!-- Banner status DINAMIS -->
             

            <form id="verify_form" method="post" action="pending_process.php" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <div class="form-group">
    <label class="form-label">Kode Verifikasi</label>

    <div class="otp-group" aria-label="Masukkan 7 digit kode verifikasi">
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" >
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" >
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" >
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" >
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" >
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" >
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" ><!-- kotak ke-7 -->
    </div>

    <!-- gabungan kode buat backend -->
    <input type="hidden" name="code" id="otp_code">
  </div>

  <div class="btn-row">
    <button class="es-btn-text" type="submit">VERIFY</button>
  </div>
</form>

            </div><!-- /#esPendingCard -->
          </div>
        </div>
      </div>
    </div>
  </div>
<script>
(function(){
  const inputs = Array.from(document.querySelectorAll('.otp-input'));
  const hidden = document.getElementById('otp_code');
  const form   = document.getElementById('verify_form');
  const submit = form?.querySelector('button[type="submit"]');
  const N = inputs.length;

  function syncHidden(){
    const code = inputs.map(i => i.value).join('');
    hidden.value = code;
    if (submit) submit.disabled = (code.length < N);
  }

  // Fokus awal
  inputs[0]?.focus();
  syncHidden();

  inputs.forEach((inp, idx) => {
    // Seleksi semua saat fokus (biar ganti angka cepat)
    inp.addEventListener('focus', e => e.target.select());

    // Ketik angka -> maju otomatis
    inp.addEventListener('input', e => {
      const v = e.target.value.replace(/\D/g, '');   // hanya digit
      e.target.value = v.slice(-1);                  // keep 1 digit
      if (v && idx < N-1) inputs[idx+1].focus();
      syncHidden();
    });

    // Navigasi: Backspace, panah kiri/kanan, Enter
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace') {
        if (inp.value) {
          inp.value = '';
          syncHidden();
          return;
        }
        if (idx > 0) {
          inputs[idx-1].focus();
          inputs[idx-1].value = '';
          e.preventDefault();
          syncHidden();
        }
      } else if (e.key === 'ArrowLeft' && idx > 0) {
        inputs[idx-1].focus(); e.preventDefault();
      } else if (e.key === 'ArrowRight' && idx < N-1) {
        inputs[idx+1].focus(); e.preventDefault();
      } else if (e.key === 'Enter') {
        // Enter kirim jika sudah lengkap
        if (hidden.value.length === N) form.submit();
      }
    });

    // Paste 7 digit sekaligus
    inp.addEventListener('paste', e => {
      e.preventDefault();
      const clip = (e.clipboardData || window.clipboardData).getData('text') || '';
      const digits = (clip.match(/\d/g) || []).join('').slice(0, N);
      if (!digits) return;

      inputs.forEach(x => x.value = '');
      for (let i=0; i<digits.length; i++) inputs[i].value = digits[i];

      if (digits.length < N) inputs[digits.length].focus();
      else inputs[N-1].focus();

      syncHidden();
    });
  });
})();
</script>

  <script src="vendors/base/vendor.bundle.base.js"></script>
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
 


</body>
</html>
