   <footer class="footer">
            <div class="d-sm-flex justify-content-center justify-content-sm-between">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">Monitoring Data Klojen  <a href="https://montaklo.id/" target="_blank">https://montaklo.id/</a></span>
            <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Safety and Trusty <i class="mdi mdi-lock text-danger"></i></span>
          </div>
        </footer>
        <!-- partial -->
      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->
<style>
  .breadcrumb-mini{font-size:.91rem; line-height:1.2}
  .breadcrumb-mini .mdi{font-size:16px}
</style>
<!-- letakkan di <head> SETELAH CSS utama -->
<!-- HEAD: gaya zoom adaptif (desktop) -->
<!-- HEAD: Zoom adaptif aman untuk modal -->
<style id="efidi-zoom-style">
  /* SNAPSHOT (cron_key) → paksa zoom di <html>, bukan di #zoomRoot */
  html.snapshot-mode{
    zoom: .99 !important; /* kalau mau cuma sedikit kecil, boleh ganti .90 → .95 */
  }
  @-moz-document url-prefix() {
    html.snapshot-mode{
      transform: scale(.90) !important;
      transform-origin: 0 0;
      width: calc(100% / .90);
    }
  }

  /* ---------- BASE VAR ---------- */
  html{ --zoom:1; --moz-scale:1; }

  /* Semua scaling DITERAPKAN ke #zoomRoot, bukan ke <html>  */
  #zoomRoot{ position: relative; }

  /* Chromium/WebKit: properti zoom (tajam & cepat) */
  @supports (zoom:.95){
    html.zoomed #zoomRoot{ zoom: var(--zoom); }
  }

  /* Firefox fallback: transform + kompensasi lebar */
  @-moz-document url-prefix() {
    html.zoomed #zoomRoot{
      transform: scale(var(--moz-scale));
      transform-origin: 0 0;
      width: calc(100% / var(--moz-scale));
    }
  }

  /* Sembunyikan kontrol di layar kecil */
  @media (max-width: 900px){
    html.zoomed #zoomRoot{ zoom:1; transform:none; width:auto; }
    #zoomCtl{ display:none !important; }
  }

  /* Kontrol zoom mengambang */
  #zoomCtl{
  position: fixed;
  left: max(14px, env(safe-area-inset-left));
  bottom: 14px;
  z-index: 2147483590; /* DI BAWAH loader (3600) */
  display: inline-flex;
  gap: 2px;
  padding: 2px 2px;
  border-radius: 999px;
  background: rgba(116,119,126,.66);
  color:#fff;
  backdrop-filter: blur(4px);
  font: 600 10px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Arial;
  transition: opacity .18s ease-out, transform .18s ease-out;
}
/* Saat loader aktif (html/body punya class efidi-loading-open),
   zoom bar disembunyikan sementara */
html.efidi-loading-open #zoomCtl,
body.efidi-loading-open #zoomCtl{
  opacity: 0;
  pointer-events: none;
  transform: translateY(6px);
}

  #zoomCtl button{
    border:0; padding:3px 6px; border-radius:999px; cursor:pointer; color:#fff;
    background:transparent;
  }
  #zoomCtl button:hover{ background: rgba(255,255,255,.14); }
  #zoomCtl button.active{ background:#009ea9; }

  /* Penting: pastikan elemen yang dibuat Bootstrap tetap di atas wrapper */
  .modal,
  .modal-backdrop{ isolation:auto; }
</style>


<!-- BODY (akhir halaman): logic + kontrol -->
<script>
(() => {
  const onHost   = location.hostname && !/^(localhost|127\.0\.0\.1)$/i.test(location.hostname);
  const isMobile = /Mobi|Android/i.test(navigator.userAgent) || innerWidth <= 900;
  const KEY      = 'efidi_zoom_pct';
  const isSnapshot = /[?&]cron_key=/.test(location.search); // halaman untuk capture

     if (isSnapshot) {
    document.documentElement.classList.add('snapshot-mode');
  }
  const pctToScale = p => Math.max(0.8, Math.min(1.2, p / 100));

  /** Bungkus seluruh konten body ke #zoomRoot (untuk zoom normal) */
  function ensureZoomRoot(){
    if (isSnapshot) return null; // snapshot: biarkan DOM apa adanya (CSS sudah handle)
    let root = document.getElementById('zoomRoot');
    if (!root){
      root = document.createElement('div');
      root.id = 'zoomRoot';

      const keepOut = el =>
        el.id === 'zoomCtl' ||
        el.classList?.contains('modal') ||
        el.classList?.contains('modal-backdrop');

      const moving = [];
      [...document.body.children].forEach(el => { if (!keepOut(el)) moving.push(el); });
      moving.forEach(el => root.appendChild(el));
      document.body.prepend(root);
    }
    return root;
  }

  function applyZoom(pct){
    if (isSnapshot) return; // snapshot mode pakai CSS html.snapshot-mode saja
    const scale = pctToScale(pct);
    const html  = document.documentElement;

    html.classList.add('zoomed');
    html.style.setProperty('--zoom',      scale);
    html.style.setProperty('--moz-scale', scale);
    localStorage.setItem(KEY, String(pct));

    document.querySelectorAll('#zoomCtl button[data-pct]').forEach(b =>
      b.classList.toggle('active', Number(b.dataset.pct) === pct)
    );
  }

  function mountControls(){
    if (!onHost || isMobile || isSnapshot || document.getElementById('zoomCtl')) return;

    const el = document.createElement('div');
    el.id = 'zoomCtl';
    el.innerHTML = `
      <button type="button" data-pct="90">90%</button>
      <button type="button" data-pct="95">95%</button>
      <button type="button" data-pct="100">100%</button>
      <button type="button" data-pct="105">105%</button>`;
    document.body.appendChild(el);

    el.addEventListener('click', e => {
      const b = e.target.closest('button[data-pct]');
      if (!b) return;
      applyZoom(Number(b.dataset.pct));
    });
  }

  function init(){
    if (!onHost || isMobile) return;

    // Halaman snapshot (cron_key) → tidak pakai zoom JS sama sekali
    if (isSnapshot) return;

    ensureZoomRoot();

    let pct;
    const saved = Number(localStorage.getItem(KEY));
    pct = saved || 100;

    applyZoom(pct);

    // Shortcut: Ctrl+Alt+(+ / - / 0)
    addEventListener('keydown', e => {
      if (!(e.ctrlKey && e.altKey)) return;
      let cur = Number(localStorage.getItem(KEY) || pct);
      if (e.key === '+' || e.key === '=') cur += 5;
      else if (e.key === '-' || e.key === '_') cur -= 5;
      else if (e.key === '0') cur = 100;
      else return;
      e.preventDefault();
      applyZoom(Math.max(80, Math.min(120, cur)));
    });
  }

  addEventListener('DOMContentLoaded', () => {
    mountControls();
    init();
  });
})();
</script>

  <!-- plugins:js -->
  <script src="vendors/base/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page-->
  <script src="vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <!-- End plugin js for this page-->
  <!-- inject:js -->
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
  <!-- endinject -->
   <script src="https://cdn.datatables.net/2.2.2/js/dataTables.js"></script>

<script>
  $(document).ready(function() {
    $('table#example').DataTable();
  });
</script>

  <!-- Custom js for this page-->
  <script src="js/dashboard.js"></script>
  <script src="js/data-table.js"></script>
  <script src="js/jquery.dataTables.js"></script>
  <script src="js/dataTables.bootstrap4.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>

<script>
/* Toast global: muncul 5 detik, reset kalau di-klik lagi */
function showCopyToast(msg = 'Teks disalin', ms = 100){
  let el = document.getElementById('copyToast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'copyToast';
    el.className = 'copy-toast';
    document.body.appendChild(el);
  }
  el.textContent = msg;
  // tampilkan
  requestAnimationFrame(() => el.classList.add('show'));
  // reset timer bila ada klik berulang
  clearTimeout(el._hideTimer);
  el._hideTimer = setTimeout(() => el.classList.remove('show'), ms);
}

// Delegasi klik untuk ikon salin
document.addEventListener('click', async (e) => {
  const icon = e.target.closest('.copy-icon');
  if (!icon) return;

  // Ambil teks: prioritas data-copy, lalu wrapper, lalu sibling
  const text =
    icon.getAttribute('data-copy') ||
    icon.closest('.copy-wrap')?.dataset.copy ||
    icon.previousElementSibling?.textContent?.trim() ||
    '';

  if (!text) return;

  try {
    await navigator.clipboard.writeText(text);
  } catch {
    // Fallback lama (execCommand) jika Clipboard API gagal
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  }

  // Tampilkan toast hitam 5 detik
  showCopyToast('Teks disalin', 1500);
});
</script>

<div id="zoom-alert">
  Tampilan efidi paling presisi di zoom 100%. Silakan reset ke 100% (Ctrl + 0).
</div>


<style>
  #zoom-alert {
    position: fixed;
    right: 16px;
    bottom: 16px;       /* ganti ke top:16px kalau mau di pojok kiri ATAS */
    max-width: 420px;
    padding: 8px 14px;
    border-radius: 9px;
    font-size: 12px;
    line-height: 1.4;
    font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;

    background: #ff8b19;
    color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,.16);
    z-index: 99999;

    opacity: 0;
    transform: translateY(10px);
    pointer-events: none;
    transition: opacity .25s ease, transform .25s ease;
  }

  #zoom-alert.es-show {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
  }
</style>
<script>
  (function () {
    const alertEl = document.getElementById('zoom-alert');
    if (!alertEl) return;

    // Hitung zoom dengan perbandingan outerWidth vs innerWidth
    function getZoomPercent() {
      const iw = window.innerWidth;
      const ow = window.outerWidth;

      if (iw && ow) {
        return Math.round((ow / iw) * 100);
      }

      // Fallback kalau outerWidth aneh (browser lama)
      if (window.screen && window.screen.width && iw) {
        return Math.round((window.screen.width / iw) * 100);
      }

      // Fallback terakhir: pakai devicePixelRatio (tidak selalu akurat)
      if (window.devicePixelRatio) {
        return Math.round(window.devicePixelRatio * 100);
      }

      return null;
    }

    function updateZoomAlert() {
      const zoom = getZoomPercent();
      if (!zoom) return;

      // ❗ Alert hanya muncul jika zoom > 100
      if (zoom > 100) {
        alertEl.textContent =
          'Tampilan efidi paling presisi di zoom 100%. ' +
          'Zoom Anda sekarang sekitar ' + zoom + '%. ' +
          'Silakan reset ke 100% (Ctrl + 0).';
        alertEl.classList.add('es-show');
      } else {
        alertEl.classList.remove('es-show');
      }
    }


    // Deteksi saat halaman pertama kali muncul & ketika user zoom (resize)
    window.addEventListener('load',   updateZoomAlert);
    window.addEventListener('resize', updateZoomAlert);
  })();
</script>


  <!-- End plugin js for this page-->
  <!-- End custom js for this page-->
</body>

</html>

