<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once 'koneksi.php';

/* ====== ROLE & IDENTITAS ====== */
$meRole = strtolower((string)($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '')));
$isTech = ($meRole === 'tech');

$meUsernameRaw = (string)($_SESSION['user']['username'] ?? $_SESSION['username'] ?? '');
$meNameRaw     = (string)($_SESSION['nama'] ?? '');
$meChat        = $_SESSION['chat_id'] ?? ($_SESSION['user']['chat_id'] ?? null);

/* exact (pakai apa adanya) */
$unameExact = trim($meUsernameRaw);
$nameExact  = preg_replace('/\s+/', ' ', trim($meNameRaw)); // rapikan spasi ganda

/* normalized untuk bridging spasi (tanpa @, tanpa spasi, lowercase) */
$normalize_spaces = static function(string $v): string {
  $v = trim($v);
  $v = strtolower($v);
  return str_replace(['@',' '], '', $v);
};
$unameNorm = $normalize_spaces($unameExact);
$nameNorm  = $normalize_spaces($nameExact);

/* ====== FILTER TEKNISI: uploader vs username/nama ======
   Lolos kalau SALAH SATU:
   - b.uploader = :u_raw OR b.uploader = :n_raw
   - LOWER(REPLACE(b.uploader,' ','')) = :u_norm / :n_norm
   (tidak pakai JOIN/EXISTS supaya tidak menghilangkan baris apa pun)
*/
$TECH_WHERE  = '1=1';
$TECH_PARAMS = [];

if ($isTech) {
  $ors = [];

  // raw exact (case-insensitive bergantung collation, tetap aman)
  $raws = [];
  if ($unameExact !== '') { $raws[':u_raw'] = $unameExact; }
  if ($nameExact  !== '' && $nameExact !== $unameExact) { $raws[':n_raw'] = $nameExact; }
  if ($raws) {
    $ors[] = 'b.uploader IN (' . implode(',', array_keys($raws)) . ')';
    $TECH_PARAMS = array_merge($TECH_PARAMS, $raws);
  }

  // normalized (buang spasi & lowercase)—untuk mengatasi mismatch "Andre west" vs "andre"
  $norms = [];
  if ($unameNorm !== '') { $norms[':u_norm'] = $unameNorm; }
  if ($nameNorm  !== '' && $nameNorm !== $unameNorm) { $norms[':n_norm'] = $nameNorm; }
  if ($norms) {
    $ors[] = "LOWER(REPLACE(b.uploader,' ','')) IN (" . implode(',', array_keys($norms)) . ")";
    $TECH_PARAMS = array_merge($TECH_PARAMS, $norms);
  }

  // kalau semua kosong (mustahil di real session), jangan bocor data
  $TECH_WHERE = $ors ? '(' . implode(' OR ', $ors) . ')' : '1=0';
}

/* === Lokasi file BAI disimpan oleh bot === */
$BAI_FS_DIR   = realpath(__DIR__ . '/bot/file') ?: (__DIR__ . '/bot/file');  // path di server
$BAI_FS_DIR   = rtrim($BAI_FS_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Base URL publik agar benar di subfolder (mis. /e-siklo/bot/file/)
$BAI_URL_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/bot/file';

if (isset($_POST['add_bai'])) {
  $err = null;

  // === Kode NO SC terpilih
  $codes = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['add_codes'] ?? [])), fn($v)=>$v!=='')));
  if (!$codes) $err = 'Pilih minimal 1 NO SC.';

  // === Tanggal diterima
  $dateReceived = trim((string)($_POST['add_date'] ?? ''));
  $dateReceived = $dateReceived !== '' ? date('Y-m-d H:i:s', strtotime($dateReceived)) : date('Y-m-d H:i:s');

  // === Upload file
  $storedName = null;
  if (!isset($_FILES['add_file']) || ($_FILES['add_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $err = $err ?: 'Pilih file BAI.';
  } else {
    $f = $_FILES['add_file'];
    $orig = (string)$f['name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $base = trim(pathinfo($orig, PATHINFO_FILENAME));
    if ($base === '') $base = 'BAI';

    // whitelist sederhana
    $allowed = ['pdf','doc','docx','xls','xlsx','csv','jpg','jpeg','png','webp','gif','zip','rar','7z'];
    if ($ext && !in_array($ext, $allowed, true)) {
      $err = $err ?: 'Ekstensi file tidak diizinkan.';
    } else {
      // buat nama unik bila sudah ada
      $storedName = $base . ($ext ? ".{$ext}" : '');
      $dst = $BAI_FS_DIR . $storedName;
      if (file_exists($dst)) {
        $storedName = $base . '__' . date('Ymd_His') . ($ext ? ".{$ext}" : '');
        $dst = $BAI_FS_DIR . $storedName;
      }
      if (!is_dir($BAI_FS_DIR)) @mkdir($BAI_FS_DIR, 0777, true);
      if (!move_uploaded_file($f['tmp_name'], $dst)) {
        $err = $err ?: 'Gagal menyimpan file.';
      }
      @chmod($dst, 0644);
    }
  }

  if ($err === null) {
    $uploader = 'web';
    if (!empty($_SESSION['user']['username'])) {
      $uploader = (string)$_SESSION['user']['username'];
    } elseif (!empty($_SESSION['username'])) {
      $uploader = (string)$_SESSION['username'];
    } elseif (!empty($_SESSION['nama'])) {
      $uploader = (string)$_SESSION['nama'];
    }

    // (catatan) Saat ini baris lama di DB masih menyimpan uploader dg SPASI.
    // Jangan disanitasi ketat agar konsisten dg data lama:
    // Jika tetap ingin sanitize, komentari baris di bawah.
    // $uploader = preg_replace('/[^A-Za-z0-9._-]/', '', $uploader) ?: 'web';

    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("INSERT INTO bai (kode_bai, nama_bai, uploader, date_received) VALUES (?,?,?,?)");
      foreach ($codes as $sc) {
        $stmt->execute([$sc, $storedName, $uploader, $dateReceived]);
      }
      $pdo->commit();

      if (function_exists('flash')) {
        flash('ok', 'BAI berhasil ditambahkan.');
      }
      header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'bai.php'));
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      $err = 'Gagal menyimpan ke database.';
    }
  }

  if ($err !== null && function_exists('flash')) {
    flash('err', $err);
  }
}

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$selectedCodes = $_POST['bai_codes'] ?? [];
$isSearch      = isset($_POST['search']);
$alertType = null; $alertMsg = '';
$rows = [];

if ($isSearch) {
  $selectedCodes = array_values(array_filter(array_map('trim',(array)$selectedCodes), fn($v)=>$v!==''));

  if (!$selectedCodes) {
    $alertType = 'warning';
    $alertMsg  = 'Silakan pilih minimal 1 kode Berita Acara Instalasi.';

    $sql = "
      SELECT b.*
      FROM bai b
      WHERE $TECH_WHERE
      ORDER BY b.id_bai DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($TECH_PARAMS);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  } else {
    // IN (:c0,:c1,...)
    $inPlace  = [];
    $inParams = [];
    foreach ($selectedCodes as $i => $code) {
      $k = ":c{$i}";
      $inPlace[] = $k;
      $inParams[$k] = $code;
    }

    $sql = "
      SELECT b.*
      FROM bai b
      WHERE b.kode_bai IN (" . implode(',', $inPlace) . ")
        AND $TECH_WHERE
      ORDER BY b.id_bai DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($inParams, $TECH_PARAMS));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
      $alertType = 'danger';
      $alertMsg  = 'Data tidak ditemukan untuk kode: <strong>'.e(implode(', ', $selectedCodes)).'</strong>.';
    } else {
      $alertType = 'success';
      $alertMsg  = 'Ditemukan <strong>'.count($rows).'</strong> Berita Acara Instalasi untuk kode: <strong>'.e(implode(', ', $selectedCodes)).'</strong>.';
    }
  }
} else {
  // default list (tanpa search)
  $sql = "
    SELECT b.*
    FROM bai b
    WHERE $TECH_WHERE
    ORDER BY b.id_bai DESC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($TECH_PARAMS);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ====== (UI & script kamu lanjut seperti semula) ====== */
?>


<?php 
include "header.php";
?>
<style>
/* === Wrapper teks + ikon copy di dalam <td> === */
td .copy-wrap{
  position: relative !important;
  display: block !important;         /* biar nempel penuh ke lebar sel */
  max-width: 100%;
  padding-right: 26px;               /* ruang buat ikon di kanan */
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
td .copy-wrap .copy-text{
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: top;
}
/* === Ikon copy: pojok kanan-atas tulisan === */
td .copy-wrap .copy-icon{
  position: absolute !important;
  top: 0px;                          /* pojok atas */
  right: 4px;                        /* pojok kanan */
  width: 16px; height: 16px;         /* lebih kecil */
  line-height: 16px;
  font-size: 12px;
  color: #616264ff;
  opacity: .8;
  cursor: pointer;
  z-index: 1;
}
td .copy-wrap:hover .copy-icon{ opacity: .95; }
td .copy-wrap .copy-icon:hover{ color:#111827; transform: scale(1.05); }

/* === Toast hitam: di ATAS-TENGAH layar, 1 detik === */
.copy-toast{
  position: fixed;
  top: 40px;
  left: 50%;
  right: auto;
  transform: translate(-50%, -6px);
  background:#111827; color:#fff;
  padding:8px 12px; border-radius:5px;
  font-size:13px; line-height:1;
  box-shadow:0 8px 24px rgba(0,0,0,.28);
  pointer-events:none;
  opacity:0;
  transition: opacity .15s ease, transform .15s ease;
  z-index:9999;
}
.copy-toast.show{
  opacity:1;
  transform: translate(-50%, 0);
}
</style>

<!-- ======================= VIEW ======================= -->
<div class="main-panel">
  <div class="content-wrapper">

    <!-- Header -->
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Berita Acara Instalasi</h2>
            <p class="mb-0">Untuk Mencari Berita Acara lebih dari 1 bisa mencari di Form Search dan klik button search</p>
            <p>Jika ingin mencari 1 Berita acara bisa mencari di form search datatables</p>
          </div>

          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo"
     class="mb-1"
     style="height:45px; margin-top:-100px; display:block;">

            <br>
            <br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
              <span class="text-primary crumb-mid">Berita Acara Instalasi</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search Card -->
    <div class="row">
      <div class="col-12 grid-margin stretch-card">
        <div class="card">
          <!-- pad dikurangi supaya alert dempet -->
          <div class="card-body d-flex align-items-center justify-content-center py4">
            <form class="forms-sample d-flex align-items-center mb-0 mx-auto w-100" method="POST" id="locker-form">
              <div class="form-group flex-grow-1 mb-0">
                          <label for="bai-search">Cari Berdasarkan Kode Berita Acara Instalasi (Bisa Mencari Lebih dari satu kode)</label>

                <select class="js-example-basic-multiple flex-grow-1" name="bai_codes[]" id="locker" multiple style="width:100%">
                 </select>
              </div>
              <div class="form-group ml-3 mb-0">
                <button type="submit" name="search" class="btn px-10 mt-4" style="color:#fff;background-color:#20a1ad">Search</button>
              </div>
            </form>
          </div>

          <!-- Alert menempel di bawah form -->
          <?php if ($isSearch && $alertType): ?>
          <div class="card-body pt-0 pb-3">
            <div class="alert alert-<?= $alertType ?> mb-0"
       style="margin-top:-10px; padding-top:.5rem; padding-bottom:.5rem;">
              <?= $alertMsg ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Tabel hasil: sekarang sejajar karena dibungkus row/col -->
    <div class="row">
      <div class="col-12 stretch-card">
        <div class="card">
          <div class="card-body pb-0 d-flex justify-content-between align-items-center">
            <p class="card-title mb-2">DATA BERITA ACARA INSTALASI</p>
             <button
          type="button"
          id="btnAddBai"
          class="btn"
          style="background: #182958;color:#fff"
          data-bs-toggle="modal" data-bs-target="#addBaiModal"
          data-toggle="modal"   data-target="#addBaiModal">Tambah
        </button>
      </div>  
<div class="card-body">
            <div class="table-responsive">
              <table id="example" class="display" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Codes</th>
                    <th>Name's BAI (Detail)</th>
                    <th>Date Received</th>
                    <th>Uploader</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $uploadDir = $BAI_FS_DIR;;
                foreach ($rows as $data):
                  $filename = $data['nama_bai'];
                  $sizeStr  = (is_file($uploadDir.$filename) ? nice_bytes(filesize($uploadDir.$filename)) : '');
                ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td>
  <span class="copy-wrap" data-copy="<?= e($data['kode_bai']) ?>">
    <span class="copy-text"><?= e($data['kode_bai']) ?></span>
    <i class="mdi mdi-content-copy copy-icon" title="Salin"></i>
  </span>
</td>
                    <td>
                      <a href="#"
                         class="bai-short"
                         data-full="<?= e($data['nama_bai']) ?>"
                         data-file="<?= e($data['nama_bai']) ?>"
                         data-size="<?= e($sizeStr) ?>"
                         title="<?= e($data['nama_bai']) ?>">
                         <?= e(short4($data['nama_bai'])) ?>
                      </a>
                    </td>
                    <td><?= date('H:i', strtotime($data['date_received'])) ?> WIB, <?= date('d F Y', strtotime($data['date_received'])) ?></td>
                    <td>@<?= e($data['uploader']) ?></td>
                    <td>
                      <a href="hapus_bai.php?id=<?= (int)$data['id_bai'] ?>"
                         onclick="return confirm('Yakin Akan Menghapus Data Ini?')"
                         title="Hapus" aria-label="Hapus"
                         style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:1.5px;cursor:pointer;background:#BDBDBD;border:1px solid #BDBDBD;color:#fff;text-decoration:none;transition:background .15s,border-color .15s;"
                         onmouseover="this.style.background='#e21a25'; this.style.borderColor='#e21a25';"
                         onmouseout ="this.style.background='#BDBDBD'; this.style.borderColor='#BDBDBD';"
                         onfocus    ="this.style.background='#e21a25'; this.style.borderColor='#e21a25';"
                         onblur     ="this.style.background='#BDBDBD'; this.style.borderColor='#BDBDBD';">
                        <i class="mdi mdi-delete" style="font-size:18px;line-height:1;color:#fff;"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        </div>

  </div><!-- /.content-wrapper -->
  </div><!-- /.content-wrapper -->

<!-- ====== Styles + Modal + Scripts kamu tetap seperti sebelumnya ====== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<!-- ====== Tombol & Modal: Tambah BAI ====== -->


<!-- Modal Tambah BAI -->
<div class="modal fade" id="addBaiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Tambah BAI ke Orders</h5>
          <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup">
  <span aria-hidden="true">&times;</span>
</button>

        </div>

        <div class="modal-body">
         <div class="mb-3">
  <label class="form-label">Pilih NO SC <span class="text-danger">*</span></label>

  <!-- parent KHUSUS utk select -->
  <div id="addCodesBox" class="position-relative">
    <select id="addCodes" name="add_codes[]"  multiple class="ts-sc"></select>
  </div>

  <div class="small text-muted mt-1">
    No Sc yang terpilih akan menjadi <code>kode_bai</code>.
  </div>
</div>


          <div class="mb-3">
            <label class="form-label">Upload File Berita Acara  Instalasi <span class="text-danger">*</span></label>
            <input type="file" name="add_file" class="form-control" required
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp,.gif,.zip,.rar,.7z,image/*,application/pdf">
            <div class="small text-muted mt-1">
              Nama file asli akan disimpan ke kolom <code>nama_bai</code>. Bila nama sudah ada, otomatis diberi suffix waktu.
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label">Tanggal diterima</label>
            <input type="datetime-local" name="add_date" class="form-control">
            <div class="small text-muted mt-1">Kosongkan bila ingin memakai waktu saat ini.</div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="add_bai" class="btn" style="background:#182958;color:#fff">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* nyaman dipakai bersama modal bootstrap */
.ts-wrapper .ts-control{ min-height:44px; border-radius:2px; }
.ts-dropdown{ max-height:341px; overflow:auto; z-index:1061; } /* di atas backdrop modal */

/* Jarak antar baris di dropdown */
.ts-dropdown .dropdown-content{ padding:6px; }
.ts-dropdown .option{ padding:10px 12px; line-height:1.5; }
.ts-dropdown .option:not(:last-child){ border-bottom:1px solid #eef2f7; }

/* Chip terpilih rapi */
.ts-wrapper.multi .ts-control{ gap:6px; flex-wrap:wrap; }
.ts-wrapper.multi .ts-control .item{
  margin:3px 4px 0 0;
  background:#e9f1ff; color:#1e5bb8; border:1px solid #cfe0ff; border-radius:8px; padding:6px 10px;
}
/* pastikan wrapper benar-benar relatif walau util class tidak tersedia */
/* khusus area select dalam modal */
#addCodesBox { position: relative; }
#addCodesBox .ts-control { min-height:44px; border-radius:2px; width:100%; }
#addCodesBox .ts-dropdown{
  position:absolute;
  top:100%;
  left:0; right:0;
  z-index:1065;            /* di atas modal */
  max-height:340px;
  overflow:auto;
}

/* opsional: HAPUS kalau masih ada aturan global ini
.ts-dropdown{ z-index:1061; } */


</style>
<script>
(function () {
  // satu-satunya sumber kebenaran instance TS
  window.tsAddCodes = null;

  function initAddCodes(){
    if (window.tsAddCodes) return;                 // cegah dobel init
    const el    = document.getElementById('addCodes');
    if (!el || !window.TomSelect) return;

    let page=1, lastQuery='', hasMore=true, loading=false;

    const box = document.getElementById('addCodesBox');
   window.tsAddCodes = new TomSelect(document.getElementById('addCodes'), {

      maxItems: 1,
      valueField: 'v',
      labelField: 'v',
      searchField: ['v'],
      placeholder: 'Ketik NO SC…',
      plugins: ['remove_button'],
      openOnFocus: true,
      closeAfterSelect: true,
      hideSelected: true,

      // BIAR LANGSUNG MUNCUL daftar pertama tanpa harus ngetik
      preload: 'focus',
      shouldLoad: () => true,
      loadThrottle: 250,

      // aman untuk BS4/BS5; biarkan di body + CSS z-index sudah tinggi
      dropdownParent: document.getElementById('addCodesWrap'),

      render:{
        option : (d,esc)=>`<div>${esc(d.v)}</div>`,
        item   : (d,esc)=>`<div>${esc(d.v)}</div>`,
        loading: ()=>`<div class="py-2 px-2 text-muted">Loading…</div>`,
        no_results: ()=>`<div class="py-2 px-2 text-muted">Tidak ada hasil</div>`
      },

      load(q, cb){
        if (loading) return;
        if (q !== lastQuery){ page=1; hasMore=true; this.clearOptions(); }
        lastQuery = q || '';
        if (!hasMore) return cb();

        loading = true;
        fetch('baitt.php?term='+encodeURIComponent(lastQuery)+'&page='+page+'&limit=50',
          {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
        .then(j => {
          const list = (j.options||[]).map(v => ({v:String(v)}));
          hasMore = !!j.more;
          page += 1;
          cb(list);
        })
        .catch(()=>cb())
        .finally(()=>loading=false);
      }
    });

    // infinite scroll
    window.tsAddCodes.on('dropdown_open', ()=>{
      page=1; hasMore=true; window.tsAddCodes.load(lastQuery);
    });
    window.tsAddCodes.on('dropdown_scroll', ()=>{
      const c = window.tsAddCodes.dropdown_content; if (!c) return;
      if (c.scrollTop + c.clientHeight >= c.scrollHeight - 10){
        window.tsAddCodes.load(lastQuery);
      }
    });
  }

  // --- 1) PASTIKAN INIT TERPANGGIL SAAT DOM SIAP ---
  function whenTSReady(fn){
    if (window.TomSelect) { fn(); return; }
    const s = document.querySelector('script[src*="tom-select"]');
    if (s) s.addEventListener('load', fn, {once:true});
  }
  document.addEventListener('DOMContentLoaded', function(){
    whenTSReady(initAddCodes);                      // <— kunci perbaikan
  });

  // --- 2) BONUS: tetap init saat modal dibuka (untuk jaga-jaga), fokuskan input ---
  const modalEl = document.getElementById('addBaiModal');
  if (modalEl){
    modalEl.addEventListener('shown.bs.modal', function(){
      whenTSReady(initAddCodes);
      setTimeout(()=>document.querySelector('#addBaiModal .ts-control input')?.focus(), 0);
    });
    if (window.jQuery){
      jQuery(modalEl).on('shown.bs.modal', function(){
        whenTSReady(initAddCodes);
        setTimeout(()=>document.querySelector('#addBaiModal .ts-control input')?.focus(), 0);
      });
    }

    // --- 3) Destroy saat ditutup supaya bersih ---
    const destroyTS = ()=>{
      if (window.tsAddCodes){ window.tsAddCodes.destroy(); window.tsAddCodes=null; }
    };
    modalEl.addEventListener('hidden.bs.modal', destroyTS);
    if (window.jQuery) jQuery(modalEl).on('hidden.bs.modal', destroyTS);
  }
})();
</script>




        <style>
/* Link nama BAI: selalu hitam */


/* Warna teks di dalam modal juga hitam */
#baiFullName{ color:#000; }
/* Link pendek: hitam dan bisa diklik */
.bai-short{ color:#387ce2ff !important; cursor:pointer; text-decoration:none; }
.bai-short:hover,.bai-short:focus{ color:#0d6efd !important; text-decoration:underline; }

/* baris tombol di modal */
#baiDetail .btn-row{ display:flex; flex-wrap:wrap; gap:.5rem; }
#baiDetail .btn i{ vertical-align:-1px; margin-right:0rem; }

/* modal mengecil mengikuti isi (tetap aman di layar kecil) */
#baiDetail .modal-dialog{ max-width:none; width:fit-content; margin-left:auto; margin-right:auto; }
#baiDetail .modal-content{ width:max-content; }
#baiFullName{ white-space:nowrap; }
@media (max-width: 992px){
  #baiDetail .modal-dialog, #baiDetail .modal-content{ max-width:95vw; }
  #baiFullName{ white-space:normal; word-break:break-word; }
}

/* tombol kecil berbasis warna kustom */
#baiDetail .btn-swatch{
  background: var(--btn-bg, #0d6efd);
  border-color: var(--btn-bg, #0d6efd);
  color: var(--btn-text, #fff);
}
#baiDetail .btn-swatch:hover,
#baiDetail .btn-swatch:focus{
  background: var(--btn-hover, #0b5ed7);
  border-color: var(--btn-hover, #0b5ed7);
  color: var(--btn-text, #fff);
}

/* ukuran ikon-button rapi */
#baiDetail .btn-icon{
  display:inline-flex; align-items:center; justify-content:center;
}
/* kecilkan semua tombol view/download */
#baiDetail .btn-icon{
  width: 35px;           /* dulu 40px */
  height: 35px;
  border-radius: .1rem;
}
#baiDetail .btn-icon i{
  font-size: 16px;       /* dulu 20px */
}
#baiDetail .btn-row{ gap: .4rem; } /* jarak antar tombol lebih rapat */

/* baris chip */
#baiDetail .file-row{ display:flex; align-items:flex-start; gap:.5rem; }
#baiDetail .file-chip{ text-decoration:none; color:#000; }

/* satu badge abu-abu berisi ikon + nama */
#baiDetail .file-badge{
  display:inline-flex; align-items:center; gap:.5rem;
  background:#eee; border:1px solid #ddd;
  padding:.35rem .75rem .35rem .5rem;
  border-radius:2px;                      /* persegi panjang rounded */
  max-width:520px;
}

/* ikon di dalam badge */
#baiDetail .file-avatar{
  width:22px; height:22px; display:flex; align-items:center; justify-content:center;
  color:#6c757d;                           /* diubah via JS sesuai ekstensi */
}
#baiDetail .file-avatar i{
  font-size:20px; line-height:1; display:block; color:currentColor;
}

/* teks nama di badge (ellipsis) */
#baiDetail .file-name{
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:460px;
}

/* size kecil di bawah */
#baiDetail .file-size{ font-size:12px; color:#6c757d; margin-left:36px; }

@media (max-width: 992px){
  #baiDetail .file-badge{ max-width:70vw; }
  #baiDetail .file-name{ max-width:calc(70vw - 60px); }
}


</style>
<!-- Modal detail nama BAI -->
<div class="modal fade" id="baiDetail" tabindex="-1" aria-hidden="true" data-base="<?= e($BAI_URL_BASE) ?>">
  <div class="modal-dialog modal-dialog-centered"><!-- kecil, menyesuaikan isi -->
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Name's BAI</h6>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="small text-muted mb-1">The File <span class="smal text-muted mb-1" id="baiFullName">></span> </div>
      <div class="file-row mt-2">
  <a id="baiFileChip" class="file-chip" target="_blank">
    <span class="file-badge">                       <!-- ⬅️ satu wadah -->
      <span class="file-avatar" id="baiFileAvatar">
        <i class="mdi mdi-file-outline" id="baiFileIcon"></i>
      </span>
      <span class="file-name" id="baiFileShort"></span>
    </span>
  </a>
  <div class="file-size" id="baiFileSize"></div>    <!-- size di bawah/terpisah -->
</div>



        <!-- tombol di bawah nama file -->
        <div class="btn-row mt-4">
        <a id="baiView" class="btn btn-sm btn-icon btn-swatch"
   style="--btn-bg:#20a1ad; --btn-hover:#1a8993; --btn-text:#fff;" target="_blank">
  <i class="mdi mdi-eye"></i>
</a>
<a id="baiDownload" class="btn btn-sm btn-icon btn-swatch"
style="--btn-bg:#182958; --btn-hover:#1e3982ff; --btn-text:#fff;">
<i class="mdi mdi-download"></i>
</a>

        </div>
      </div>

      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<style>
.ts-wrapper .ts-control{ min-height:44px; border-radius:2px; }
.ts-dropdown{ max-height:340px; overflow:auto; }
.ts-wrapper.multi .ts-control .item{
  background:#e9f1ff; color:#1e5bb8; border:1px solid #cfe0ff; border-radius:8px; padding:6px 10px;
}
</style>
<style>
/* Jarak antar baris di dropdown */
.ts-dropdown .dropdown-content{ padding: 6px; }              /* ruang tepi */
.ts-dropdown .option{
  padding: 10px 12px;                                        /* tinggi baris */
  line-height: 1.5;
}
.ts-dropdown .option:not(:last-child){
  border-bottom: 1px solid #eef2f7;                          /* pemisah halus */
}

/* Jarak antar chip (nilai yang sudah dipilih) di input */
.ts-wrapper.multi .ts-control{ gap: 6px; flex-wrap: wrap; }
.ts-wrapper.multi .ts-control .item{
  margin: 3px 4px 0 0;                                       /* spasi antar chip */
  padding: 6px 10px;
}
</style>


<script>
(function(){
  const PRESELECT = <?= json_encode(array_values($selectedCodes ?? [])) ?>;
  const el = document.getElementById('locker');
  if (!el || !window.TomSelect) return;

  let page=1, lastQuery='', hasMore=true, loading=false;

  const ts = new TomSelect(el, {
    maxItems: null,
    valueField: 'v',
    labelField: 'v',
    searchField: ['v'],
    placeholder: 'Example : AOi4250901105105667e002d0',
    plugins: ['remove_button'],
    preload: 'focus',
    openOnFocus: true,
    closeAfterSelect: false,
    hideSelected: true,
    dropdownParent: 'body',
    loadThrottle: 250,
    load: function(q, cb){
      if (loading) return;
      if (q !== lastQuery){ page=1; hasMore=true; this.clearOptions(); }
      lastQuery = q;
      if (!hasMore) return cb();

      loading = true;
      fetch('./bait.php?term=' + encodeURIComponent(q||'') + '&page=' + page + '&limit=50')
        .then(r => r.ok ? r.json() : {options:[], more:false})
        .then(d => {
          const list = (d.options || []).map(v => ({v}));
          hasMore = !!d.more;
          page += 1;
          cb(list);
        })
        .catch(() => cb())
        .finally(() => loading=false);
    },
    render:{
      option:(d,esc)=>`<div>${esc(d.v)}</div>`,
      item:(d,esc)=>`<div>${esc(d.v)}</div>`
    }
  });

  ts.on('dropdown_open', () => { page=1; hasMore=true; ts.load(lastQuery); });
  ts.on('dropdown_scroll', () => {
    const c = ts.dropdown_content; if (!c) return;
    if (c.scrollTop + c.clientHeight >= c.scrollHeight - 10) ts.load(lastQuery);
  });

  if (Array.isArray(PRESELECT) && PRESELECT.length){
    PRESELECT.forEach(v => ts.addOption({v}));
    PRESELECT.forEach(v => ts.addItem(v, true));
  }
})();
</script>


<script>
  // buka modal saat klik "DGPS…"
  document.addEventListener('click', function(e){
    const el = e.target.closest('.bai-short');
    if(!el) return;
    e.preventDefault();

    const full = el.dataset.full || '';
    const file = el.dataset.file || full;

    // tampilkan nama
    document.getElementById('baiFullName').textContent = full;

    // bangun URL file
    const modalEl = document.getElementById('baiDetail');
    const base = (modalEl.dataset.base || '').replace(/\/+$/,'') + '/';
    let url = file;

    // kalau bukan http/https, anggap relatif ke base
    if (!/^https?:\/\//i.test(url)) url = base + file;

    // encode hanya segmen terakhir (nama file)
    const parts = url.split('/');
    const last = parts.pop();
    parts.push(encodeURIComponent(last));
    url = parts.join('/');

    // set tautan View & Download
    const aView = document.getElementById('baiView');
    const aDl   = document.getElementById('baiDownload');
    if (aView) aView.href = url;
    if (aDl) {
      aDl.href = url;
      aDl.setAttribute('download', last); // minta browser unduh
    }

    // buka modal (BS5 atau BS4)
    if (window.bootstrap && bootstrap.Modal) {
      new bootstrap.Modal(modalEl).show();
    } else if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
      jQuery(modalEl).modal('show');
    }
  });

  // tombol salin
  document.getElementById('copyBai')?.addEventListener('click', function(){
    const txt = document.getElementById('baiFullName').textContent || '';
    if (navigator.clipboard) navigator.clipboard.writeText(txt);
  });
</script>
<script>
  const FILE_MAP = {
    pdf:  {icon:'mdi-file-pdf-box',      color:'#e53935'},
    doc:  {icon:'mdi-file-word',         color:'#2b579a'},
    docx: {icon:'mdi-file-word',         color:'#2b579a'},
    xls:  {icon:'mdi-file-excel',        color:'#1d6f42'},
    xlsx: {icon:'mdi-file-excel',        color:'#1d6f42'},
    ppt:  {icon:'mdi-file-powerpoint',   color:'#d24726'},
    pptx: {icon:'mdi-file-powerpoint',   color:'#d24726'},
    csv:  {icon:'mdi-file-delimited',    color:'#1d6f42'},
    jpg:  {icon:'mdi-file-image',        color:'#8e44ad'},
    jpeg: {icon:'mdi-file-image',        color:'#8e44ad'},
    png:  {icon:'mdi-file-image',        color:'#8e44ad'},
    gif:  {icon:'mdi-file-image',        color:'#8e44ad'},
    webp: {icon:'mdi-file-image',        color:'#8e44ad'},
    zip:  {icon:'mdi-folder-zip',        color:'#6c757d'},
    rar:  {icon:'mdi-folder-zip',        color:'#6c757d'},
    '7z': {icon:'mdi-folder-zip',        color:'#6c757d'},
    txt:  {icon:'mdi-file-document-outline', color:'#6c757d'},
    default:{icon:'mdi-file-outline',    color:'#6c757d'}
  };

  document.addEventListener('click', function(e){
    const el = e.target.closest('.bai-short');
    if(!el) return;
    e.preventDefault();

    const full = el.dataset.full || '';
    const file = el.dataset.file || full;
    const size = el.dataset.size || '';

    // set nama lengkap
    baiFullName.textContent = full;

    // build URL
    const base = (baiDetail.dataset.base || '').replace(/\/+$/,'') + '/';
    let url = /^https?:\/\//i.test(file) ? file : base + file;
    const parts = url.split('/'); const last = parts.pop();
    parts.push(encodeURIComponent(last)); url = parts.join('/');

    // tombol
    baiView.href = url; baiDownload.href = url; baiDownload.setAttribute('download', last);

    // === chip ===
    baiFileChip.href = url;
    baiFileShort.textContent = full;
    baiFileSize.textContent  = size || '';

    const ext  = (full.split('.').pop() || '').toLowerCase();
    const meta = FILE_MAP[ext] || FILE_MAP.default;

    baiFileIcon.className = 'mdi ' + meta.icon;     // ikon spesifik (pdf, word, dll)
    baiFileAvatar.style.color = meta.color;         // warnai ikon (pdf merah)
    // latar avatar transparan supaya "logo pdf" terlihat asli (merah kotak)
  });
</script>




        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
     <?php
     include "footer.php";
      ?>