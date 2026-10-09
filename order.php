<?php
// orders_tech.php — Data Orders milik teknisi yang sedang login
include "header.php";
require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// ===================== Ambil identitas sesi =====================
$meChat    = $_SESSION['chat_id']              ?? ($_SESSION['user']['chat_id'] ?? null);
$meRole    = $_SESSION['role']                 ?? ($_SESSION['user']['role'] ?? null);
$meIdAkses = $_SESSION['uid']                  ?? $_SESSION['id_akses'] ?? ($_SESSION['user']['id'] ?? null);

// Fallback ke DB kalau chat_id/role belum ada tapi id_akses ada
if ((!$meChat || !$meRole) && $meIdAkses) {
  $st = $pdo->prepare("SELECT chat_id, role FROM akses_login WHERE id_akses = ? LIMIT 1");
  $st->execute([$meIdAkses]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $meChat = $meChat ?: ($row['chat_id'] ?? null);
    $meRole = $meRole ?: ($row['role'] ?? null);
    $_SESSION['chat_id'] = $meChat;
    $_SESSION['role']    = $meRole;
  }
}

// ===================== Username & Nama =====================
// (ikuti pola evidensi: izinkan spasi & huruf non-ASCII)
$meUserRaw = (string)($_SESSION['user']['username'] ?? $_SESSION['username'] ?? '');
$meNameRaw = (string)($_SESSION['nama'] ?? '');

$unameExact = trim($meUserRaw);                           // biarkan apa adanya
$nameExact  = preg_replace('/\s+/', ' ', trim($meNameRaw));
$nameExact  = preg_replace('/[^\pL\pN._\-\s]/u', '', $nameExact); // buang karakter liar, spasi dipertahankan

// kandidat exact yang mungkin tersimpan di sender_username
$CANDS_EXACT = array_values(array_unique(array_filter([$unameExact, $nameExact])));

// kandidat versi dinormalisasi (lowercase + tanpa @ + tanpa spasi)
$CANDS_NORM = array_values(array_unique(array_map(
  fn($v) => strtolower(str_replace(['@',' '], '', trim($v))),
  $CANDS_EXACT
)));

// ===================== Validasi role =====================
if (strtolower((string)$meRole) !== 'tech') {
  http_response_code(403);
  echo '<div class="container mt-4"><div class="alert alert-warning">Halaman ini khusus untuk <b>Tech</b>.</div></div>';
  include "footer.php"; exit;
}

// ===================== Chat ID (boleh kosong; kita fallback) =====================
$isChatOk = $meChat && preg_match('/^-?\d{5,}$/', (string)$meChat);
$myUid    = $isChatOk ? (string)$meChat : null;

// ===================== BANGUN FILTER KEPEMILIKAN (OR) =====================
// Hasil akhir pakai $OWNER_WHERE dan $OWNER_PARAMS di query orders.
$OWNER_PARAMS = [];
$ownerParts   = [];

// (1) user_id ↔ chat_id
if ($isChatOk) {
  $ownerParts[]            = "user_id = :uid";
  $OWNER_PARAMS[':uid']    = $myUid;
}

// (2) sender_username ↔ exact (username atau nama apa adanya)
if (!empty($CANDS_EXACT)) {
  $ph = [];
  foreach ($CANDS_EXACT as $i => $v) {
    $k = ":su$i";
    $ph[] = $k;
    $OWNER_PARAMS[$k] = $v;
  }
  $ownerParts[] = "sender_username IN (" . implode(',', $ph) . ")";
}

// (3) sender_username (dinormalisasi) ↔ kandidat dinormalisasi
if (!empty($CANDS_NORM)) {
  $ph2 = [];
  foreach ($CANDS_NORM as $i => $v) {
    $k = ":sn$i";
    $ph2[] = $k;
    $OWNER_PARAMS[$k] = $v;
  }
  // normalisasi di sisi kolom: trim → lower → hilangkan '@' & spasi
  $ownerParts[] =
    "LOWER(REPLACE(REPLACE(TRIM(sender_username),'@',''),' ','')) IN (" . implode(',', $ph2) . ")";
}

// Jika tidak ada identitas sama sekali → kosongkan hasil
$OWNER_WHERE = $ownerParts ? '(' . implode(' OR ', $ownerParts) . ')' : '1=0';
?>

   <style>
    /* baris aksi: rapi & sejajar */
.btn-icon-row{
  display:inline-flex;
  align-items:center;
  gap:8px;                 /* jarak antar tombol */
  vertical-align:middle;
}

/* tombol ikon */
.btn-icon{
  display:inline-flex; align-items:center; justify-content:center;
  width:32px; height:32px; border-radius:2px;
  text-decoration:none; line-height:1;
  color:#fff;
  transition:background .15s, border-color .15s;
}
.btn-icon i{ font-size:18px; line-height:1; }

/* warna view */
.btn-exce{ background:#12824f; border-color:#12824f; }
.btn-exce:hover{ background:#0f6e43; border-color:#0f6e43; }
.btn-exce .excel-logo{
  display:block; width:24px; height:24px;  /* perbesar agar hampir setinggi tombol */
}


   </style>


<style>
  /* Jaga warna ikon tetap putih di semua state interaksi */
a.btn-icon,
a.btn-icon i { color:#fff; }

a.btn-icon:hover,
a.btn-icon:focus,
a.btn-icon:active,
a.btn-icon:hover i,
a.btn-icon:focus i,
a.btn-icon:active i{
  color:#fff !important;     /* override a:hover bootstrap */
  text-decoration: none;
}

/* hover background Anda tetap jalan */
.btn-view{ background:#182958; border:1px solid #182958; }
.btn-view:hover{ background:#233c80ff; border-color:#233c80ff; }

</style>

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

<style>
  /* samakan tinggi kontrol form dengan tombol .btn-icon (38px) */


/* select Bootstrap butuh extra ruang di kanan untuk caret */
.filter-bar .form-select { padding-right: 2rem; }

/* ====== BRAND / WARNA GAMPANG DIGANTI ====== */
:root{
  --search-bg: #ee1f25;        /* ganti ke warna kamu, mis. #05d3d2 */
  --search-bg-hover: #c6181dff;  /* warna hover */
  --search-border: #ee1f25;    /* border tombol */
  --excel-outline: var(--search-bg);
  
}


/* Samakan tinggi kontrol dengan tombol */
.filter-bar .form-select,
.filter-bar .form-control,
.filter-bar input[type="date"],
.filter-bar input[type="number"],
.filter-bar select{
  height:38px; min-height:38px;
  padding-top:6px; padding-bottom:6px;
  line-height:24px; border-radius:2px;
}
.filter-bar .form-select{ padding-right:2rem; }

/* Tombol ikon (search/excel) — warna dinamis */
.btn-icon.btn-search{
  background:var(--search-bg);
  color:#fff;
  border:1px solid var(--search-border);
  transition:background .15s,border-color .15s,transform .05s;
}
.btn-icon.btn-search:hover{
  background:var(--search-bg-hover);
  border-color:var(--search-bg-hover);
}
.btn-icon.btn-search:active{ transform: translateY(1px); }

.btn-icon.btn-excel{
  background:transparent;
  border:2px solid var(--excel-outline);
}

/* ====== SPACING / JARAK ====== */
/* Jarak antara judul "DATA ORDERS" dan bar filter */
.card-title + .filter-bar{ margin-top:10px; }

/* Jarak bawah bar filter ke area DataTables (Show entries) */
.filter-bar{ margin-bottom:8px; }

/* Kalau masih nempel, tambahkan top-margin untuk kontrol DataTables */
.dataTables_wrapper .dataTables_length{ margin-top:8px; }

/* ===== FORCE RADIUS UNTUK TOMBOL FILTER ===== */
/* Ubah di sini angka radiusnya */
:root{ --btn-radius: 2px; }

/* Kunci semua variasi elemen/kelas supaya yang ini menang */
.filter-bar .btn-icon,
.filter-bar .btn.btn-icon,
.filter-bar button.btn-icon,
.filter-bar a.btn-icon,
.filter-bar .btn-icon.btn-search,
.filter-bar .btn-icon.btn-excel {
  border-radius: var(--btn-radius) !important;
}

/* Kalau Excel masih kelihatan lebih bulat karena outline, samakan juga */
.filter-bar .btn-icon.btn-excel {
  outline: 0 !important;
  /* opsional: pastikan gambar tidak bikin ilusi sudut */
  overflow: hidden;
}


</style>

<style>
  /* ========== EXCEL BUTTON STATES ========== */
:root{
  --excel-green: #16a34a;        /* hijau aktif */
  --excel-green-hover: #14843f;
  --excel-gray: #979ca3ff;         /* abu-abu saat nonaktif */
}

.filter-bar .btn-icon{ width:38px; height:38px; }

/* Excel: default NONAKTIF (abu-abu) */
#btnExcel {
  background: transparent;
  border: 1px solid var(--excel-gray);
  filter: grayscale(1) opacity(.55);
  cursor: not-allowed;
  border-radius: var(--btn-radius, 2px);
}

/* Excel: AKTIF (hijau outline) */
#btnExcel.active {
  filter: none;
  cursor: pointer;
  border-color: var(--excel-green);
}

#btnExcel.active:hover { border-color: var(--excel-green-hover); }

</style>

<style>
  /* ===== HEADER FILTER + EXCEL: sejajar ===== */
.filter-header{
  display:flex;
  flex-wrap:wrap;
  align-items:flex-end;          /* sejajarkan di bawah */
  justify-content:space-between; /* filter kiri, excel kanan */
  gap:12px;
  margin:12px 0;                 /* ganti margin milik anak */
}

/* hilangkan margin bawaan bar & tools agar rata */
.filter-header .filter-bar{ margin:0 }
.filter-header .table-tools{ margin:0 }

/* tinggi tombol excel konsisten */
.table-tools .btn-excel-wide{
  height:38px;
  display:inline-flex;
  align-items:center;
}

/* responsif: kalau mepet layar, Excel turun tetap rata kanan */
@media (max-width: 768px){
  .filter-header{ align-items:stretch }
  .filter-header .table-tools{
    width:100%;
    display:flex;
    justify-content:flex-end;
    margin-top:8px;
  }
}

</style>
   <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="d-flex justify-content-between align-items-end flex-wrap">
      <!-- kiri -->
      <div class="mr-md-3 mr-xl-5">
        <h2 class="mb-1">Data Orders</h2>
        <p class="mb-0">Bisa Mencari Berdasarkan Tanggal tertentu, Rentang Tanggal, Bulan atau Tahun tertentu di Filter Pencarian.</p>
        <p class="mb-0"> Dan juga bisa di download berdasarkan filter pencarian atau masing masing order. </p>
        <p>Data bisa di edit dengan mengclick button view di tabel action </p>
      </div>

      <!-- kanan: breadcrumb, MASIH di header tapi turun & mepet row -->
   <!-- wrapper kanan -->
<div class="d-flex flex-column align-items-end me-3 mt-n1">  <!-- me-3 = geser kiri, mt-n1 = tarik ke atas -->
  <!-- logo di atas -->
 <img src="images/logo.png" alt="Logo e-Siklo"
     class="mb-1"
     style="height:45px; margin-top:-10px; display:block;">

       <br>
       <br>
       
       <br>
  <!-- breadcrumb di bawah logo (tanpa <br>) -->
  <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30"> <!-- mt-n1 rapetin lagi -->
  <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>

  <a href="index.php" class="text-muted mx-1 crumb-mid">
  &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
</a>

    <span class="text-primary crumb-mid">Data Orders</span>
  </div>
</div>




              </div>
            </div>
          </div>
         
          <div class="row">
           
            <div class="col-md-12 stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title">Data Orders</p>
                  <?php
// Ambil nilai GET supaya form mengingat pilihan
$mode   = $_GET['mode']  ?? '';
$dateV  = $_GET['date']  ?? '';
$startV = $_GET['start'] ?? '';
$endV   = $_GET['end']   ?? '';
$monthCombined = $_GET['month'] ?? ''; // 'YYYY-MM'
$yearV  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$monthY = ''; $monthM = '';
if (preg_match('/^(\d{4})-(\d{2})$/', $monthCombined, $m)) {
  $monthY = $m[1]; $monthM = $m[2];
}
$baseURL = htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8');
?>

<style>
/* ====== FILTER BAR (override, taruh paling bawah CSS-mu) ====== */
.filter-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin:12px 0 12px}
.filter-bar .form-label{margin-bottom:4px}
.filter-bar .form-select,
.filter-bar .form-control{height:38px;min-height:38px;line-height:24px;border-radius:2px}
.filter-bar .form-select{padding-right:2rem}
.filter-bar .btn-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:2px}

/* tombol */
.btn-icon.btn-search{background:#20a1ad;border:1px solid #20a1ad;color:#fff}
.btn-icon.btn-search:hover{background: #17818bff;border-color: #17818bff}
.btn-icon.btn-clear{background:#e5e7eb;border:1px solid #d1d5db;color:#111}

/* tools kanan atas tabel */
.table-tools{display:flex;justify-content:end;gap:8px;margin-bottom:8px}
.btn-excel-wide{display:inline-flex;align-items:center;height:38px;padding:0 12px;border:2px solid #d1d5db;border-radius:2px;color:#6b7280;filter:grayscale(1) opacity(.65);cursor:not-allowed}
.btn-excel-wide.active{border-color:#16a34a;color:#14532d;filter:none;cursor:pointer}
.btn-excel-wide.active:hover{border-color:#14843f}
</style>
<div class="filter-header">
<!-- ===== FILTER PENCARIAN ===== -->
<form id="filterForm" class="filter-bar" method="get" action="">
  <div>
    <label for="mode" class="form-label">Filter Pencarian</label>
    <select name="mode" id="mode" class="form-select form-select-sm" style="min-width:200px">
      <option value=""        <?= $mode===''        ? 'selected' : '' ?>>Pilih Filter Pencarian</option>
      <option value="single"  <?= $mode==='single'  ? 'selected' : '' ?>>Berdasarkan Tanggal</option>
      <option value="range"   <?= $mode==='range'   ? 'selected' : '' ?>>Rentang Tanggal</option>
      <option value="month"   <?= $mode==='month'   ? 'selected' : '' ?>>Bulan</option>
      <option value="year"    <?= $mode==='year'    ? 'selected' : '' ?>>Tahun</option>
    </select>
  </div>

  <!-- SINGLE -->
  <div id="fields-single" class="mode-fields" style="display:none">
    <label class="form-label">Tanggal</label>
    <input type="date" name="date" class="form-control form-control-sm"
           value="<?= htmlspecialchars($dateV, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- RANGE -->
  <div id="fields-range" class="mode-fields" style="display:none;gap:8px;align-items:end">
    <div>
      <label class="form-label">Mulai</label>
      <input type="date" name="start" class="form-control form-control-sm"
             value="<?= htmlspecialchars($startV, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div>
      <label class="form-label">Selesai</label>
      <input type="date" name="end" class="form-control form-control-sm"
             value="<?= htmlspecialchars($endV, ENT_QUOTES, 'UTF-8') ?>">
    </div>
  </div>

  <!-- MONTH -->
  <div id="fields-month" class="mode-fields" style="display:none;gap:8px;align-items:end">
    <div>
      <label class="form-label">Bulan</label>
      <select id="month_m" class="form-select form-select-sm">
        <?php
        $bulanNama=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $curM = $monthM ?: date('m');
        foreach ($bulanNama as $num=>$nama):
          $val = str_pad((string)$num,2,'0',STR_PAD_LEFT);
        ?>
          <option value="<?= $val ?>" <?= ($curM===$val?'selected':'') ?>><?= $nama ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label">Tahun</label>
      <input id="month_y" type="number" min="2000" max="2100" step="1"
             class="form-control form-control-sm"
             value="<?= htmlspecialchars($monthY ?: date('Y'), ENT_QUOTES, 'UTF-8') ?>">
    </div>
  </div>
  <input type="hidden" name="month" id="hidden-month" value="<?= htmlspecialchars($monthCombined, ENT_QUOTES, 'UTF-8') ?>">

  <!-- YEAR -->
  <div id="fields-year" class="mode-fields" style="display:none">
    <label class="form-label">Tahun</label>
    <input type="number" min="2000" max="2100" step="1" name="year"
           class="form-control form-control-sm" style="width:120px"
           value="<?= htmlspecialchars((string)$yearV, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- state -->
  <input type="hidden" name="searched" id="searched" value="<?= isset($_GET['searched']) ? (int)$_GET['searched'] : 0 ?>">

  <!-- tombol -->
  <button id="btnSearch" type="submit" class="btn btn-icon btn-search" title="Cari" aria-label="Cari">
    <i class="mdi mdi-magnify" style="font-size:18px"></i>
  </button>
  <button id="btnClear" type="button" class="btn btn-icon btn-clear" title="Hapus Filter" aria-label="Hapus Filter">
    <i class="mdi mdi-close" style="font-size:18px"></i>
  </button>
</form>

<!-- Tools kanan atas tabel -->
<div class="table-tools">
  <button id="btnExcel"
          class="btn btn-excel-wide"
          title="Download Excel"
          aria-label="Download Excel"
          form="filterForm"
          formaction="export.php"
          formmethod="get"
          disabled>
    <img class="excel-logo-small"
         src="https://upload.wikimedia.org/wikipedia/commons/e/e3/Microsoft_Office_Excel_%282019%E2%80%932025%29.svg"
         alt="Excel" style="width:18px;height:18px;margin-right:8px">
    <span>Download Excel</span>
  </button>
</div>
</div>

<script>
(function(){
  const form      = document.getElementById('filterForm');
  const modeEl    = document.getElementById('mode');
  const btnSearch = document.getElementById('btnSearch');
  const btnClear  = document.getElementById('btnClear');
  const btnExcel  = document.getElementById('btnExcel');
  const searched  = document.getElementById('searched');
  const baseURL   = "<?= $baseURL ?>";

  const blocks = {
    single: document.getElementById('fields-single'),
    range:  document.getElementById('fields-range'),
    month:  document.getElementById('fields-month'),
    year:   document.getElementById('fields-year')
  };

  function hideAll(){
    Object.values(blocks).forEach(el=>{
      el.style.display='none';
      el.querySelectorAll('input,select').forEach(i=>i.disabled=true);
    });
  }
  function refresh(){
    hideAll();
    const v = modeEl.value;
    if(!v) return; // saat "Pilih Filter Pencarian" -> tidak tampil apapun
    blocks[v].style.display = (v==='range'||v==='month')?'flex':'block';
    blocks[v].querySelectorAll('input,select').forEach(i=>i.disabled=false);
  }

  // submit dari Search/Excel -> gabung month + set searched
  form.addEventListener('submit', function(e){
    const sub = e.submitter;               // siapa yang men-submit
    const hiddenMonth = document.getElementById('hidden-month');
    if (modeEl.value === 'month') {
      const mm = String(document.getElementById('month_m').value||'').padStart(2,'0');
      const yy = document.getElementById('month_y').value;
      hiddenMonth.value = (yy && mm)? `${yy}-${mm}` : '';
    } else hiddenMonth.value = '';

    // Kalau submit dari Search -> tandai searched=1
    if (sub && sub.id === 'btnSearch') searched.value = '1';
  });

  // saat halaman hasil Search, Excel aktif
  const urlParams = new URLSearchParams(location.search);
  const searchedOnce = urlParams.get('searched') === '1';
  btnExcel.disabled = !searchedOnce;
  btnExcel.classList.toggle('active', searchedOnce);

  // tombol Clear (X): reset, nonaktifkan excel, reload base
  btnClear.addEventListener('click', ()=>{
    form.reset();
    searched.value = '0';
    modeEl.value = '';
    hideAll();
    btnExcel.disabled = true;
    btnExcel.classList.remove('active');
    window.location = baseURL; // kembali ke semua data
  });

  // init
  modeEl.addEventListener('change', refresh);
  refresh();
})();
</script>



                  <div class="table-responsive">
                    <table id="example" class="display" style="width:100%">
                      <thead>
                        <tr>
                            <th>No</th>
                            <th>Orders</th>
                            <th>No SC</th>
                            <th>No Internet</th>
                            <th>Customer Name</th>
                            <th>Contact Person</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                     <?php
require_once 'koneksi.php';
$no = 1;

$mode   = $_GET['mode']  ?? '';
$date   = $_GET['date']  ?? '';
$start  = $_GET['start'] ?? '';
$end    = $_GET['end']   ?? '';
$month  = $_GET['month'] ?? ''; // 'YYYY-MM' dari hidden
$year   = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// SELALU filter milik user yang login (chat_id == orders.user_id)
// SELALU filter milik user yang login (chat_id == orders.user_id)
$where  = [$OWNER_WHERE];
$params = $OWNER_PARAMS;

switch ($mode) {
  case 'single':
    if ($date) {
      $where[]       = "created_at BETWEEN :ds AND :de";
      $params[':ds'] = $date . " 00:00:00";
      $params[':de'] = $date . " 23:59:59";
    }
    break;
  case 'range':
    if ($start && $end) {
      $where[]       = "created_at BETWEEN :rs AND :re";
      $params[':rs'] = $start . " 00:00:00";
      $params[':re'] = $end   . " 23:59:59";
    } elseif ($start) {
      $where[]       = "created_at >= :rs";
      $params[':rs'] = $start . " 00:00:00";
    } elseif ($end) {
      $where[]       = "created_at <= :re";
      $params[':re'] = $end . " 23:59:59";
    }
    break;
  case 'month':
    if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
      [$y,$m] = explode('-', $month);
      $startMonth = sprintf('%04d-%02d-01 00:00:00', $y, $m);
      $endMonth   = date('Y-m-t 23:59:59', strtotime("$y-$m-01"));
      $where[]       = "created_at BETWEEN :ms AND :me";
      $params[':ms'] = $startMonth;
      $params[':me'] = $endMonth;
    }
    break;
  case 'year':
    if ($year) {
      $where[]        = "created_at BETWEEN :ys AND :ye";
      $params[':ys']  = sprintf('%04d-01-01 00:00:00', $year);
      $params[':ye']  = sprintf('%04d-12-31 23:59:59', $year);
    }
    break;
}

$sql = "SELECT id_order, order_type, no_sc, no_internet, cp_pelanggan, nama_pelanggan, created_at FROM orders";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY id_order DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) :
?>

  <tr>
    <td><?= $no++ ?></td>
    <td><?= e($data['order_type']) ?></td>
  <td>
  <span class="copy-wrap" data-copy="<?= e($data['no_sc']) ?>">
    <span class="copy-text"><?= e($data['no_sc']) ?></span>
    <i class="mdi mdi-content-copy copy-icon" title="Salin"></i>
  </span>
</td>

<td>
  <span class="copy-wrap" data-copy="<?= e($data['no_internet']) ?>">
    <span class="copy-text"><?= e($data['no_internet']) ?></span>
    <i class="mdi mdi-content-copy copy-icon" title="Salin"></i>
  </span>
</td>


    <td><?= e(short4($data['nama_pelanggan'])) ?></td>
   <td>
  <span class="copy-wrap" data-copy="<?= e($data['cp_pelanggan']) ?>">
    <span class="copy-text"><?= e($data['cp_pelanggan']) ?></span>
    <i class="mdi mdi-content-copy copy-icon" title="Salin"></i>
  </span>
</td>
   <td><?= date('d M Y', strtotime($data['created_at'])) ?></td>
  <td class="text-nowrap">
  <span class="btn-icon-row">
    <!-- VIEW -->
    <a href="view_data.php?id_order=<?= (int)$data['id_order']; ?>"
       class="btn-icon btn-view" title="Lihat" aria-label="Lihat">
      <i class="mdi mdi-eye"></i>
    </a>

    <!-- DOWNLOAD (Excel) -->
     <a href="download_data.php?id_order=<?= (int)$data['id_order'] ?>"
     class="btn-icon btn-exce" title="Download (Excel)" aria-label="Download">
    <img class="excel-logo"
         src="https://upload.wikimedia.org/wikipedia/commons/e/e3/Microsoft_Office_Excel_%282019%E2%80%932025%29.svg"
         alt="Excel">
    <!-- Jika ingin lokal, download file di atas sebagai /images/excel-2019.svg
         lalu ganti src menjadi /images/excel-2019.svg -->
  </a>
  </span>
</td>




  </tr>
<?php endwhile; ?>

                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <style>
/* Link nama BAI: selalu hitam */


/* Warna teks di dalam modal juga hitam */
#baiFullName{ color:#000; }
/* Link pendek: hitam dan bisa diklik */
.bai-short{ color:#000 !important; cursor:pointer; text-decoration:none; }
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
<div class="modal fade" id="baiDetail" tabindex="-1" aria-hidden="true" data-base="/uploads/bai/">
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
   style="--btn-bg:#20a1ad; --btn-hover:#1a8993; --btn-text:#fff;">
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