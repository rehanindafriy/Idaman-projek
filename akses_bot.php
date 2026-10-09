<?php
include "header.php";
require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}
?>
<style>
  #example .badge-outline-danger{ background:#fff; }
  .btn-xs{ font-size:12px; line-height:1; padding:2px 6px; }

  /* ==== SIZE & BASE STYLE (sama seperti akses_login) ==== */
  .btn.btn-sm.btn-commit,
  .btn.btn-commit{
    height: 34px !important;
    min-width: 30px !important;
    padding: 8px 8px !important;
    line-height: 1 !important;

    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 2px;
    border-width: 1px !important;

    background: #E5E7EB  !important;
    border-color: #c8cad0ff  !important;
    color: #9CA3AF  !important;
  }
  .btn-commit i{ font-size:18px; line-height:1 !important; }

  .btn.btn-sm.btn-commit.teal-enabled,
  .btn.btn-commit.teal-enabled{
    background: #00A3AD !important;
    border-color: #00A3AD !important;
    color: #fff !important;
  }
  .btn.btn-sm.btn-commit:disabled,
  .btn.btn-commit:disabled{
    background: #E5E7EB  !important;
    border-color: #c8cad0ff  !important;
    color: #9CA3AF  !important;
    box-shadow: none !important;
    opacity: 1 !important;
    cursor: not-allowed;
  }

  /* Select status: border jelas + caret panah */
  .sel-status{
    -webkit-appearance:none;
    -moz-appearance:none;
    appearance:none;
    background-color:#fff;

    border:1px solid #d1d5db;
    border-radius:6px;
    padding-right:30px !important;
    box-shadow:none;
  }
  .sel-status:focus{
    border-color:#00A3AD;
    box-shadow:0 0 0 3px rgba(0,163,173,.14);
    outline:none;
  }
  .sel-status{
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='%236b7280' fill-rule='evenodd' d='M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right .6rem center;
    background-size: 14px 14px;
  }

  .sel-status option[disabled].current{
    color:#94a3b8;
  }
  .sel-status option:disabled{ color:#9ca3af; }

  /* Placeholder style (--- Pilih ---) */
  .sel-status.is-placeholder:not(:disabled){
    background:#ffffff !important;
    border-color:#00A3AD !important;
    color:#2d3030ff !important;
    font-weight:600;
  }
  .sel-status.is-placeholder:focus{
    box-shadow:0 0 0 3px rgba(0,163,173,.25) !important;
    outline:none;
  }

  /* Kolom khusus */
  #example th:nth-child(3),
  #example td:nth-child(3){ white-space:nowrap; width:160px; } /* chat_id */

  #example th:nth-child(4),
  #example td:nth-child(4){ white-space:nowrap; width:130px; } /* status */

  #example th:nth-child(5),
  #example td:nth-child(5){ white-space:nowrap; width:80px; }  /* commit */

  #example th:nth-child(6),
  #example td:nth-child(6){ white-space:nowrap; width:80px; }  /* hapus */

  #example td, #example th{ vertical-align: middle; }
</style>

<div class="main-panel">
  <div class="content-wrapper">
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <!-- kiri -->
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Akses BOT</h2>
            <p class="mb-0">
              Daftar akun Telegram (username &amp; chat_id) yang terhubung dengan BOT efidi.
            </p>
            <p>
              Status <code>active</code> akan mengizinkan BOT mengirim notifikasi ke akun tersebut.
            </p>
          </div>

          <!-- kanan: logo + breadcrumb (ganti label) -->
          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo"
                 class="mb-1"
                 style="height:45px; margin-top:-10px; display:block;">
            <br><br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon"
                 style="font-size:13px;line-height:1;vertical-align:-90px"></i>

              <a href="index.php" class="text-muted mx-1 crumb-mid">
                &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
              </a>
              <span class="text-primary crumb-mid">Akses BOT</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php
    // --- AMBIL DATA DARI TABEL akses_bot ---
    $rows = [];
    try {
      $q = $pdo->query("SELECT id_akses_bot, username, chat_id, status
                          FROM akses_bot
                      ORDER BY id_akses_bot DESC");
      $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $rows = [];
    }
    ?>

    <div class="row">
      <div class="col-md-12 stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <p class="card-title mb-0">Akses BOT</p>
              <!-- TIDAK ADA tombol Tambah di sini -->
            </div>

            <div class="table-responsive">
              <table id="example" class="display" style="width:100%">
                <thead>
                  <tr>
                    <th style="width:52px">No</th>
                    <th>Username</th>
                    <th>ID Telegram (chat_id)</th>
                    <th>Status</th>
                    <th style="width:70px">Aksi</th>
                    <th style="width:70px">Hapus</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $no = 1;
                  foreach ($rows as $r):
                    $id   = (int)$r['id_akses_bot'];
                    $un   = (string)$r['username'];
                    $cht  = (string)($r['chat_id'] ?? '');
                    $sts  = strtolower((string)$r['status']);
                     $labelNama = $un !== '' ? '@'.$un : '(tanpa username)';
                ?>
                  <tr data-id="<?= $id ?>" data-initial-status="<?= e($sts) ?>">
                    <td class="text-muted"><?= $no++ ?></td>

                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div style="width:28px;height:28px;border-radius:50%;background:#e2f3f3;display:inline-flex;align-items:center;justify-content:center;font-weight:700;color:#0f766e;">
                          <?= strtoupper(substr($un !== '' ? $un : 'U',0,1)) ?>
                        </div>
                        <div>
                          <div class="font-weight-bold">@<?= e($un) ?></div>
                        </div>
                      </div>
                    </td>

                    <td class="text-monospace">
                      <?php if ($cht !== ''): ?>
                        <div class="d-flex align-items-center" style="gap:4px;">
                          <span><?= e($cht) ?></span>
                          <button type="button"
                                  class="btn btn-copy-chat"
                                  data-chat="<?= e($cht) ?>"
                                  title="Salin chat_id"
                                  style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;min-width:16px;min-height:16px;padding:0;border-radius:2px;border:1px solid #e5e7eb;background:#fff;color:#6b7280;cursor:pointer;">
                            <i class="mdi mdi-content-copy" style="font-size:10px;line-height:1;"></i>
                          </button>
                        </div>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <select class="form-control form-control-sm sel-status">
                        <option value="" <?= $sts===''?'selected':'' ?>>--- Pilih ---</option>
                        <option value="active"    <?= $sts==='active'?'selected':'' ?>>Active</option>
                        <option value="suspended" <?= $sts==='suspended'?'selected':'' ?>>Suspended</option>
                      </select>
                    </td>

                    <td class="text-center">
                      <button class="btn btn-sm btn-commit" title="Simpan status" disabled>
                        <i class="mdi mdi-autorenew"></i>
                      </button>
                    </td>

                      <td class="text-center">
  <button type="button"
          class="btn-delete-akses"
          data-id="<?= $id ?>"
          data-nama="<?= e($labelNama) ?>"
          data-toggle="modal"
          data-target="#hapusAksesModal"
          title="Hapus"
          aria-label="Hapus">
    <i class="mdi mdi-delete"></i>
  </button>
</td>

                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
   <div class="modal fade" id="hapusAksesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
      <div class="modal-content">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title">Hapus Akses BOT</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body pt-2">
          <div class="d-flex align-items-center mb-3">
            <div class="mr-2 d-flex align-items-center justify-content-center"
                 style="width:32px;height:32px;border-radius:50%;background:#fef2f2;color:#b91c1c;">
              <i class="mdi mdi-alert-outline" style="font-size:20px;"></i>
            </div>
            <div>
              <p class="mb-1" style="font-size:14px;">
                Yakin akan menghapus akses BOT berikut?
              </p>
              <div id="hapusAksesNama"
                   class="font-weight-bold"
                   style="font-size:13px;color:#111827;"></div>
            </div>
          </div>
          <p class="text-muted mb-0" style="font-size:12px;">
            Tindakan ini tidak dapat dibatalkan. Data yang sudah dihapus tidak bisa dikembalikan.
          </p>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">
            Batal
          </button>
          <button type="button" class="btn btn-danger btn-sm" id="btnConfirmHapus">
            <i class="mdi mdi-delete mr-1"></i> Hapus
          </button>
        </div>

      </div>
    </div>
  </div>
<style>
.btn-delete-akses{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:32px;
  height:32px;
  border-radius:3px;
  border:1px solid #e5e7eb;
  background:#f9fafb;
  color:#b91c1c;
  cursor:pointer;
  transition:background .15s, border-color .15s, box-shadow .15s, color .15s;
}

.btn-delete-akses i{
  font-size:18px;
  line-height:1;
}

.btn-delete-akses:hover,
.btn-delete-akses:focus{
  background:#fee2e2;
  border-color:#f8baba;
  color:#b91c1c;
 
  outline:none;
}
  /* Lebarkan ukuran modal hapus akses */
  #hapusAksesModal .modal-dialog{
    max-width: 520px;      /* lebar modal (desktop) */
    width: 100%;
  }

  @media (max-width: 576px){
    #hapusAksesModal .modal-dialog{
      max-width: 100%;     /* di HP tetap full, biar responsif */
      margin: 0 12px;
    }
  }
</style>

 

<script>
// tombol copy chat_id (sama seperti akses_login)
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.btn-copy-chat');
  if (!btn) return;
  const val = btn.getAttribute('data-chat') || '';
  if (!val) return;

  navigator.clipboard.writeText(val).then(function () {
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="mdi mdi-check"></i>';
    btn.style.borderColor = '#141414ff';
    btn.style.color = '#141414ff';
    setTimeout(function () {
      btn.innerHTML = old;
      btn.style.borderColor = '#e5e7eb';
      btn.style.color = '#374151';
    }, 1200);
  });
});
</script>

<script>
(function(){
  const API = 'akses_bot_api.php';

  async function api(action, payload){
    const r = await fetch(API, {
      method:'POST',
      credentials:'include',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body:new URLSearchParams({action, ...payload})
    });
    const d = await r.json().catch(()=>({ok:false,msg:'Server error'}));
    if (!r.ok || !d.ok) throw new Error(d.msg || 'Gagal');
    return d;
  }

  function computeState(tr){
    const statSel = tr.querySelector('.sel-status');
    const btn     = tr.querySelector('.btn-commit');
    if (!statSel || !btn) return;

    const statVal = (statSel.value || '').trim();

    if (!('savedStatus' in tr.dataset))
      tr.dataset.savedStatus = (tr.dataset.initialStatus ?? statVal ?? '');

    const savedStat = tr.dataset.savedStatus || '';

    const hasVal  = !!statVal;
    const changed = hasVal && (statVal !== savedStat);

    if (changed){
      btn.disabled = false;
      btn.classList.add('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-autorenew"></i>';
    }else{
      btn.disabled = true;
      btn.classList.remove('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-autorenew"></i>';
    }
  }

  async function syncRowFromDB(tr){
    const id = tr?.dataset?.id; if (!id) return;
    try{
      const d = await api('get_state', {id});
      tr.dataset.savedStatus = d.status || '';

      const ss = tr.querySelector('.sel-status');
      if (ss){
        const val = (d.status || '').toLowerCase();
        const opt = Array.from(ss.options).find(o =>
          (o.value || '').toLowerCase() === val
        );
        if (opt) ss.value = opt.value; else ss.value = d.status || '';
      }
      computeState(tr);
    }catch(_){ /* diam */ }
  }

  // INIT semua baris
  document.querySelectorAll('#example tbody tr').forEach(tr=>{
    const s = tr.querySelector('.sel-status');
    tr.dataset.initialStatus = tr.dataset.initialStatus ?? (s ? s.value : '');
    syncRowFromDB(tr);
    s?.addEventListener('change', ()=>computeState(tr));
    computeState(tr);
  });

  // COMMIT STATUS
  document.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('.btn-commit');
    if (!btn || btn.disabled) return;

    const tr      = btn.closest('tr');
    const id      = tr.dataset.id;
    const statVal = tr.querySelector('.sel-status')?.value?.trim() || '';

    btn.disabled = true;
    btn.classList.add('teal-enabled');
    btn.innerHTML = '<i class="mdi mdi-autorenew mdi-spin"></i>';

    try{
      const d = await api('save_status', { id, status: statVal });

      tr.dataset.savedStatus = d.status || statVal;

      const ss = tr.querySelector('.sel-status');
      if (ss) ss.value = tr.dataset.savedStatus;

      btn.classList.remove('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-check"></i>';
      btn.disabled  = true;
    }catch(e){
      alert(e.message || 'Gagal menyimpan');
      btn.disabled = false;
      btn.classList.remove('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-autorenew"></i>';
    }
  });
})();
</script>

<script>
// Placeholder highlight untuk select status
(function(){
  const selects = document.querySelectorAll('select.form-control.sel-status');

  function isPlaceholder(el){
    const val  = (el.value || '').trim();
    const text = (el.options[el.selectedIndex]?.text || '').trim();
    const textLooksPlaceholder = /^-*\(?\s*pilih\s*\)?-*$|^pilih$/i.test(text);
    return val === '' || textLooksPlaceholder;
  }

  function apply(el){
    el.classList.toggle('is-placeholder', isPlaceholder(el));
  }

  selects.forEach(el => {
    apply(el);
    el.addEventListener('change', () => apply(el));
  });
})();
</script>
<script>
(function () {
  let deleteId = null;

  window.addEventListener('load', function () {
    if (!window.jQuery) {
      console.error('jQuery belum tersedia, handler hapus tidak dipasang.');
      return;
    }

    var $ = window.jQuery;

    // Isi nama & ID ketika modal mau dibuka
    $('#hapusAksesModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);   // tombol yang diklik
      deleteId   = button.data('id') || null;
      var nama   = button.data('nama') || '(tanpa nama)';

      $('#hapusAksesNama').text(nama);
    });

    // Klik tombol Hapus -> spinner + redirect
    $('#btnConfirmHapus').on('click', function () {
      if (!deleteId) return;

      var $btn = $(this);

      // cegah double-click
      if ($btn.data('loading')) return;
      $btn.data('loading', true);

      // ubah tampilan tombol jadi spinner
      $btn
        .prop('disabled', true)
        .html(
          "<span class='spinner-border spinner-border-sm mr-1' " +
          "role='status' aria-hidden='true'></span> Menghapus..."
        );

      // lanjut ke script penghapusan khusus BOT
      window.location.href = 'hapus_akses_bot.php?id=' + encodeURIComponent(deleteId);
    });
  });
})();
</script>

 <?php include "footer.php"; ?>