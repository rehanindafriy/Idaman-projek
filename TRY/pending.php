<?php
require_once __DIR__.'/auth.php';

$err = flash('err'); 
$ok  = flash('ok');

$pending_uname = $_SESSION['pending_uname'] ?? '';
$pending_uid   = $_SESSION['pending_uid'] ?? null;

/* === Ambil status user dari DB untuk pewarnaan banner === */
$status = null;
$hasCode = false;
$codeVal = '';
try {
  if ($pending_uid) {
    $st = $pdo->prepare("SELECT username, status, verifikasi FROM akses_login WHERE id_akses=? LIMIT 1");
    $st->execute([$pending_uid]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $pending_uname = $row['username'] ?: $pending_uname;
      $status = $row['status'] ?? null;
      $hasCode = isset($row['verifikasi']) && $row['verifikasi'] !== '' && $row['verifikasi'] !== null;
      $codeVal = $hasCode ? (string)$row['verifikasi'] : '';
    }
  } elseif ($pending_uname) {
    $st = $pdo->prepare("SELECT username, status, verifikasi FROM akses_login WHERE username=? LIMIT 1");
    $st->execute([$pending_uname]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $pending_uname = $row['username'] ?: $pending_uname;
      $status = $row['status'] ?? null;
      $hasCode = isset($row['verifikasi']) && $row['verifikasi'] !== '' && $row['verifikasi'] !== null;
      $codeVal = $hasCode ? (string)$row['verifikasi'] : '';
    }
  }
} catch (Throwable $e) { /* abaikan */ }



/* KUNCI FORM: hanya ACTIVE yang boleh isi */
$isLocked = in_array($status, ['pending','suspended'], true);
$lockAttr = $isLocked ? ' disabled readonly' : '';
$btnAttr  = $isLocked ? ' disabled' : '';


/* === Tentukan kelas & pesan banner berdasar status === */
$bannerClass = 'info';  // default (biru)
$bannerIcon  = 'mdi-information-outline';
$bannerText  = 'Masukkan kode verifikasi.';

if ($status === 'pending') {
  $bannerClass = 'warn';
  $bannerIcon  = 'mdi-alert-outline';
  $bannerText  = 'Akun masih pending. Hubungi admin untuk mengaktifkan akun dan memberikan kode verifikasi.';
} elseif ($status === 'active') {
  $bannerClass = 'success';
  $bannerIcon  = 'mdi-check-circle-outline';
  $bannerText  = 'Akun Anda sudah aktif. Masukkan kode verifikasi dari admin untuk menyelesaikan aktivasi.';
} elseif ($status === 'suspended') {
  $bannerClass = 'danger';
  $bannerIcon  = 'mdi-close-octagon-outline';
  $bannerText  = 'Akun Anda sedang ditangguhkan. Hubungi admin untuk informasi lebih lanjut.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>e-Siklo — Verifikasi Akun</title>
  <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/logoo.png" />
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

/* === TOASTS (notif ringan) === */
.toast-wrap{position:fixed;right:16px;top:16px;display:flex;flex-direction:column;gap:10px;z-index:9999}
.toast{min-width:260px;max-width:360px;border-radius:12px;padding:12px 14px;border:1px solid #e5e7eb;
       background:#ffffff;box-shadow:0 10px 30px rgba(2,6,23,.15);display:flex;align-items:flex-start;gap:10px;opacity:.98;transform:translateY(-6px)}
.toast .icon{font-size:20px;line-height:1;margin-top:2px}
.toast .body{font-size:13px;color:#0f172a}
.toast .actions{margin-left:auto;display:flex;gap:8px}
.toast .btn{border:0;border-radius:2px;padding:6px 8px;font-size:12px;cursor:pointer}
.toast .btn.refresh{background:#00A3AD;color:white}
.toast.success{border-color:#a7f3d0}
.toast.info{border-color:#bae6fd}
.toast.warn{border-color:#fdba74}
.toast.danger{border-color:#fecaca}

  </style>
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

          
              <!-- Banner status DINAMIS -->
              <div class="es-alert <?= htmlspecialchars($bannerClass) ?>">
                <i class="mdi <?= htmlspecialchars($bannerIcon) ?>"></i>
                <?php if($pending_uname): ?>
                  <span class="muted">@<?= htmlspecialchars($pending_uname) ?></span> —
                <?php endif; ?>
                <?= htmlspecialchars($bannerText) ?>
              </div>

            <form id="verify_form" method="post" action="pending_process.php" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <div class="form-group">
    <label class="form-label">Kode Verifikasi</label>

    <div class="otp-group" aria-label="Masukkan 7 digit kode verifikasi">
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" <?= $lockAttr ?>>
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" <?= $lockAttr ?>>
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" <?= $lockAttr ?>>
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" <?= $lockAttr ?>>
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" <?= $lockAttr ?>>
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" <?= $lockAttr ?>>
      <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" <?= $lockAttr ?>><!-- kotak ke-7 -->
    </div>

    <!-- gabungan kode buat backend -->
    <input type="hidden" name="code" id="otp_code">
  </div>

  <div class="btn-row">
    <button class="es-btn-text" type="submit">VERIFY</button>
  </div>
</form>

            </div><!-- /#esPendingCard -->
<div class="toast-wrap" id="toastWrap" aria-live="polite" aria-atomic="true"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="vendors/base/vendor.bundle.base.js"></script>
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
  <script>
(function(){
  const inputs = Array.from(document.querySelectorAll('.otp-input'));
  const hidden = document.getElementById('otp_code');
  const form   = document.getElementById('verify_form');

  let internal = false; // guard supaya set value tidak memicu loop 'input' lagi

  function assemble(){
    hidden.value = inputs.map(i => i.value).join('');
  }

  function fillFrom(index, digitsArr){
    internal = true;
    let i = index;
    for (const d of digitsArr){
      if (i >= inputs.length) break;
      inputs[i].value = d;
      i++;
    }
    internal = false;

    // fokuskan ke kotak pertama yang masih kosong setelah pengisian
    const nextEmpty = inputs.findIndex((x, ix) => ix >= index && !x.value);
    if (nextEmpty !== -1) inputs[nextEmpty].focus();
    else inputs[inputs.length - 1].focus();

    assemble();
  }

  inputs.forEach((input, idx) => {
    // terima ketikan cepat / autofill >1 digit => sebar ke kotak berikutnya
    input.addEventListener('input', e => {
      if (internal) return;
      const digits = e.target.value.replace(/\D/g, '');
      if (!digits) { e.target.value = ''; assemble(); return; }
      fillFrom(idx, digits.split('').slice(0, inputs.length - idx));
    });

    // arahkan dengan backspace & panah
    input.addEventListener('keydown', e => {
      if (e.key === 'Backspace') {
        if (input.value) { input.value = ''; assemble(); return; }
        if (idx > 0) { inputs[idx - 1].focus(); inputs[idx - 1].value = ''; assemble(); e.preventDefault(); }
      } else if (e.key === 'ArrowLeft' && idx > 0) {
        inputs[idx - 1].focus(); e.preventDefault();
      } else if (e.key === 'ArrowRight' && idx < inputs.length - 1) {
        inputs[idx + 1].focus(); e.preventDefault();
      }
    });

    // paste 6 digit sekaligus di kotak mana pun
    input.addEventListener('paste', e => {
      const text = (e.clipboardData || window.clipboardData).getData('text') || '';
      if (!text) return;
      e.preventDefault();
      const digits = text.replace(/\D/g, '').slice(0, inputs.length - idx).split('');
      fillFrom(idx, digits);
    });
  });

  // sebelum submit: pastikan 6 digit lengkap
  form.addEventListener('submit', e => {
    assemble();
    if (hidden.value.length !== inputs.length){
      e.preventDefault();
      alert('Kode harus 7 angka.');
      const firstEmpty = inputs.find(x => !x.value) || inputs[0];
      firstEmpty.focus();
    }
  });

  // fokus awal
  if (inputs[0]) inputs[0].focus();
})();
</script>

<script>
(function(){
  // ====== STATE GLOBAL ======
  const __G = window.__ES_PENDING_STATE || (window.__ES_PENDING_STATE = {
    activeShown:false,   // notif ACTIVE sekali
    codeShown:false,     // toast kode sedang tampil
    codeValue:''
  });

  // ====== KONFIG ======
  const POLL_MS       = 8000;      // cek status/terbit tiap 8 detik (pra-refresh)
  const PERIOD_SEC    = 180;       // muncul kode tiap 3 menit
  const TOAST_TTL_MS  = 15000;     // TTL toast biasa (bukan sticky)
  const SHOW_CODE_QS  = 'show_code'; // query param penanda “tampilkan kode setelah refresh”

  // ====== DATA DARI PHP ======
  const USERNAME   = <?php echo json_encode((string)($pending_uname ?? '')); ?>;
  let   lastStatus = <?php echo json_encode(strtolower((string)($status ?? ''))); ?>;   // 'active' / lainnya
  let   lastHasCode= <?php echo isset($hasCode) && $hasCode ? 'true' : 'false'; ?>;
  let   lastCodeVal= <?php echo json_encode((string)($codeVal ?? '')); ?>;

  // ====== DOM ======
  const wrap = document.getElementById('toastWrap');

  // ====== HELPERS ======
  function $(sel){ return document.querySelector(sel); }
  function showToastBase(type, html, {ttlMs=TOAST_TTL_MS, sticky=false} = {}){
    if (!wrap) return null;
    const el = document.createElement('div');
    el.className = 'toast ' + (type||'info');
    el.innerHTML = `
      <i class="mdi mdi-bell-outline icon"></i>
      <div class="body">${html}</div>
      <div class="actions"></div>
    `;
    wrap.appendChild(el);
    requestAnimationFrame(()=>{
      el.style.transition='opacity .4s ease, transform .4s ease';
      el.style.opacity='1'; el.style.transform='translateY(0)';
    });
    if (!sticky && ttlMs > 0){
      setTimeout(()=>{
        el.style.opacity='0'; el.style.transform='translateY(-6px)';
        setTimeout(()=> el.remove(), 450);
      }, ttlMs);
    }
    return el;
  }

  function showToast(type, html, opts){
    let withRefresh = false, sticky=false, ttlMs=TOAST_TTL_MS;
    if (typeof opts === 'boolean'){ withRefresh = opts; }
    else if (opts && typeof opts === 'object'){
      withRefresh = !!opts.withRefresh;
      sticky = !!opts.sticky;
      ttlMs = Number.isFinite(opts.ttlMs) ? opts.ttlMs : TOAST_TTL_MS;
    }
    const el = showToastBase(type, html, {ttlMs, sticky});
    if (!el) return null;

    if (withRefresh){
      const btn = document.createElement('button');
      btn.className = 'btn refresh';
      btn.type = 'button';
      btn.textContent = 'Refresh';
      btn.onclick = () => {
        const url = new URL(location.href);
        url.searchParams.set(SHOW_CODE_QS, '1');   // mode “tampilkan kode setelah refresh”
        location.href = url.toString();
      };
      el.querySelector('.actions').appendChild(btn);
    }
    return el;
  }

  // Ambil status & kode terbaru (paksa non-cache + bawa cookie sesi)
  async function fetchLatestCode(){
    try{
      const r = await fetch('check_status.php?_=' + Date.now(), {
        cache: 'no-store',
        headers: { 'Accept': 'application/json' },
        credentials: 'include'
      });
      if (!r.ok) {
        return { status: lastStatus, has_code: lastHasCode, code: String(lastCodeVal || '') };
      }
      const d = await r.json();
      const codeStr = (d && d.code !== undefined && d.code !== null) ? String(d.code) : '';
      return {
        status: (d.status || '').toLowerCase(),
        has_code: !!d.has_code,
        code: codeStr
      };
    }catch(_){
      return { status: lastStatus, has_code: lastHasCode, code: String(lastCodeVal || '') };
    }
  }

  // ====== TOAST “KODE” DENGAN COUNTDOWN 3 MENIT ======
  let lastShownAt = 0; // ms: kapan toast kode terakhir MULAI ditampilkan

  function showCodeToastOnce(code, seconds = PERIOD_SEC){
    if (__G.codeShown) return; // sedang tampil, jangan ganda

    const el = showToastBase(
      'info',
      `Kode verifikasi Anda: <b><code class="code-val"></code></b><br>
       <small id="cd" style="opacity:.8">Waktu tersisa: <span class="t">03:00</span></small>`,
      { sticky:true }
    );
    if (!el) return;

    __G.codeShown = true;
    __G.codeValue = String(code);
    lastShownAt = Date.now(); // dicatat saat MULAI tampil

    const actions = el.querySelector('.actions');
    const spanCode = el.querySelector('.code-val');
    spanCode.textContent = code;

    // Isi Otomatis
    const btnFill = document.createElement('button');
    btnFill.className = 'btn refresh';
    btnFill.type = 'button';
    btnFill.textContent = 'Isi Otomatis';
    btnFill.onclick = () => {
      const inputs = Array.from(document.querySelectorAll('.otp-input'));
      const hidden = document.getElementById('otp_code');
      const digits = (code||'').toString().replace(/\D/g,'').slice(0, inputs.length).split('');
      inputs.forEach((inp,i)=> inp.value = digits[i]||'');
      if (hidden) hidden.value = digits.join('');
      (inputs.find(x => !x.value) || inputs[inputs.length-1] || {}).focus?.();
    };

    // Salin
    const btnCopy = document.createElement('button');
    btnCopy.className = 'btn';
    btnCopy.type = 'button';
    btnCopy.textContent = 'Salin';
    btnCopy.onclick = async () => {
      try { await navigator.clipboard.writeText(code); btnCopy.textContent='Tersalin'; }
      catch(e){ btnCopy.textContent='Gagal salin'; }
      setTimeout(()=>btnCopy.textContent='Salin', 2000);
    };

    actions.appendChild(btnFill);
    actions.appendChild(btnCopy);

    // Countdown 3 menit
    let remain = Math.max(0, seconds|0);
    const cd = el.querySelector('#cd');
    const tNode = el.querySelector('.t');
    const fmt = s => {
      const m = Math.floor(s/60), d = s%60;
      return String(m).padStart(2,'0')+':'+String(d).padStart(2,'0');
    };
    tNode.textContent = fmt(remain);

    const iv = setInterval(()=>{
      remain -= 1;
      if (remain < 0){
        clearInterval(iv);
        // matikan aksi setelah habis
        actions.querySelectorAll('button').forEach(b=>b.disabled = true);
        cd.innerHTML = '<span style="color:#7f1d1d">Waktu habis.</span>';
        setTimeout(()=>{
          el.style.opacity='0'; el.style.transform='translateY(-6px)';
          setTimeout(()=> { el.remove(); __G.codeShown = false; }, 450);
        }, 1200);
        return;
      }
      tNode.textContent = fmt(remain);
    }, 1000);
  }

  // ====== CEK “SUDAH 3 MENIT?” LALU TAMPILKAN KODE TERBARU ======
  async function tryShowIfDue(){
    if (__G.codeShown) return; // masih ada toast aktif
    const now = Date.now();
    if (now - lastShownAt < PERIOD_SEC * 1000) return; // belum 3 menit sejak MULAI tampil

    const d = await fetchLatestCode(); // ambil terbaru dulu
    if (d.status === 'active' && d.has_code && d.code){
      lastStatus  = d.status;
      lastHasCode = d.has_code;
      lastCodeVal = d.code;           // simpan
      showCodeToastOnce(d.code, PERIOD_SEC); // tampilkan KODE TERBARU
    }
  }

  // ====== LOOP PERIODIK (anti-throttle) ======
  function startPeriodicLoop(){
    // cek awal sekali (misal setelah refresh ?show_code=1)
    tryShowIfDue();

    // cek tiap 5 detik
    setInterval(tryShowIfDue, 5000);

    // saat user balik ke tab, evaluasi segera
    document.addEventListener('visibilitychange', ()=>{
      if (!document.hidden) tryShowIfDue();
    });
  }

  // ====== POLLING PRA-REFRESH (hanya untuk menampilkan instruksi refresh) ======
  async function ping(){
    try{
      const d = await fetchLatestCode();
      // pending -> active (tanpa kode)
      if (lastStatus !== 'active' && d.status === 'active' && !d.has_code && !__G.activeShown){
        __G.activeShown = true;
        showToast('success',
          'Akun Anda sudah <b>ACTIVE</b>.<br>Silakan <b>Refresh</b> halaman ketika admin sudah mengirimkan kode verifikasi.',
          {withRefresh:false, sticky:false}
        );
      }
      // Kode diterbitkan (sebelum refresh) -> sticky, tidak hilang
      if (d.status === 'active' && d.has_code && !lastHasCode){
        showToast('info',
          'Kode verifikasi telah diterbitkan admin. Klik <b>Refresh</b> untuk melihat kode.',
          {withRefresh:true, sticky:true}
        );
      }
      // sinkronkan cache lokal
      lastStatus  = d.status || lastStatus;
      lastHasCode = !!d.has_code;
      lastCodeVal = d.code ? String(d.code) : lastCodeVal;
    }catch(_){}
  }

  // ====== ALUR SAAT LOAD HALAMAN ======
  const qs = new URLSearchParams(location.search);
  const wantShowCodeNow = qs.get(SHOW_CODE_QS) === '1';

  if (lastStatus === 'active' && !lastHasCode && !__G.activeShown){
    // ACTIVE tapi belum ada kode
    __G.activeShown = true;
    showToast('success',
      'Akun Anda sudah <b>ACTIVE</b>.<br>Silakan <b>Refresh</b> halaman ketika admin sudah mengirimkan kode verifikasi.',
      {withRefresh:false, sticky:false}
    );
  } else if (lastStatus === 'active' && lastHasCode){
    if (wantShowCodeNow){
      // Mode “tampilkan kode sekarang” (setelah refresh pertama)
      (async () => {
        const d = await fetchLatestCode();                 // ambil terbaru
        const latest = d.code || String(lastCodeVal || '');
        if (latest) showCodeToastOnce(latest, PERIOD_SEC); // tampilkan terbaru
        startPeriodicLoop();                               // lalu auto tiapk 3 menit (tanpa refresh lagi)
        // bersihkan query param agar rapi
        qs.delete(SHOW_CODE_QS);
        const clean = location.pathname + (qs.toString() ? '?' + qs.toString() : '') + location.hash;
        history.replaceState(null, '', clean);
      })();
    } else {
      // Belum refresh (menurut aturanmu: JANGAN tampilkan kode dulu)
      showToast('info',
        'Kode verifikasi telah diterbitkan admin. Klik <b>Refresh</b> untuk melihat kode.',
        {withRefresh:true, sticky:true}
      );
    }
  }

  // Polling pra-refresh jalan terus
  setInterval(ping, POLL_MS);

})();
</script>


</body>
</html>
