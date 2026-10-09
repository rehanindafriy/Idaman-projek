    <?php
    
    include "header.php";
    ?>
    <!-- partial -->
        <div class="main-panel">
          <div class="content-wrapper">
            <div class="row">
              <div class="col-md-12 grid-margin">
                <div class="d-flex justify-content-between align-items-end flex-wrap">
            <div class="mr-md-3 mr-xl-5">
              <h2 class="mb-1">Dashboard</h2>
              <p class="mb-0">Dashboard ini menampilkan rangkuman aktivitas teknisi secara real-time, mulai dari jumlah Order, Berita Acara Instalasi (BAI), hingga Outstanding</p>
              <p class="mb-0">Pada bagian atas terdapat informasi pengguna yang sedang login, termasuk nama, role, username, tanggal, waktu, dan status akun</p>
              <p>Fitur pencarian dan pagination pada tabel membantu pengguna menemukan data dengan cepat dan efisien</p>
            </div>

            <div class="d-flex flex-column align-items-end me-3 mt-n1">
              <img src="images/logo.png" alt="Logo e-Siklo"
      class="mb-1"
      style="height:45px; margin-top:-100px; display:block;">

              <br>
              <br>
              <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n10">
                <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>
                <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
                <span class="text-primary crumb-mid">Dashboard</span>
              </div>
            </div>
          </div>
            </div>
            <div class="row">
              <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body dashboard-tabs p-0">
                    <ul class="nav nav-tabs px-4" role="tablist">
                      <li class="nav-item">
                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true">Detail</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" id="sales-tab" data-toggle="tab" href="#sales" role="tab" aria-controls="sales" aria-selected="false">Report</a>
                      </li>
                       <li class="nav-item">
                      <a class="nav-link" id="purchases-tab" data-toggle="tab" href="#purchases" role="tab" aria-controls="purchases" aria-selected="false">Users</a>
                    </li>
                    
                    </ul>
                    <style>
    /* --- Atur warna ikon per item via CSS variable --- */
    .item .mdi {
      color: var(--icon-color, #0ea5a4) !important; /* default kalau tidak di-set */
    }



    /* label "Status" dengan centang mini di kanan */
    .label-with-check{
      display:inline-flex; align-items:center; gap:0rem;
    }
    .mini-check{
      width:25px; height:25px; border-radius:999px;
      background:var(--ok-green);
      display:inline-flex; align-items:center; justify-content:center;
      /* ring lembut */
    }
    .mini-check .mdi{ font-size:14px; color:#166534 !important;  line-height:1; }

    
    /* --- Badge status Active --- */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .22rem .8rem;
      border-radius: 999px;
      background: #16a34a;         /* hijau */
      color: #fff;
      font-weight: 500;
      font-size: .9rem;
      line-height: 1;
      white-space: nowrap;
    }
    .status-badge .mdi {
      font-size: 0rem;
      color: #fff !important;
      margin-right: .1rem;
    }
  </style>

                    <div class="tab-content py-0 px-0">
                  <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
    <div class="d-flex flex-wrap justify-content-xl-between">

      <!-- NAME -->
      <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
          style="--icon-color:#e11d48;"> <!-- merah tua -->
        <i class="mdi mdi-account mr-3 icon-lg"></i>
        <div class="d-flex flex-column justify-content-around">
          <small class="mb-1 text-muted">Name</small>
          <h5 class="mr-2 mb-0"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
      </div>

      <!-- ROLE -->
      <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
          style="--icon-color:#009ea9;"> <!-- hijau -->
        <i class="mdi mdi-account-card-details mr-3 icon-lg"></i>
        <div class="d-flex flex-column justify-content-around">
          <small class="mb-1 text-muted">Role</small>
          <h5 class="mr-2 mb-0"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
      </div>

      <!-- USERNAME -->
      <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
          style="--icon-color:#182958;"> <!-- kuning -->
        <i class="mdi mdi-at mr-3 icon-lg"></i>
        <div class="d-flex flex-column justify-content-around">
          <small class="mb-1 text-muted">Username</small>
          <h5 class="mr-2 mb-0"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></h5>
        </div>
      </div>
  <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
      style="--icon-color:#166534;" id="itemDateTime"> <!-- kuning -->
    <i class="mdi mdi-calendar-clock mr-3 icon-lg"></i>
    <div class="d-flex flex-column justify-content-around">
      <small class="mb-1 text-muted">Date : <span class="today-date"></span></small>
      <h5 class="mr-2 mb-0">O'clock : <span class="current-time"></span></h5>
    </div>
  </div>
<script>
(function(){
  const root = document.getElementById('itemDateTime');
  if (!root) return;

  const dateEl = root.querySelector('.today-date');
  const timeEl = root.querySelector('.current-time');
  if (!dateEl || !timeEl) return;

  const ZONE = 'Asia/Jakarta';

  function tick() {
    const now = new Date();

    // Tanggal dalam WIB, contoh: "Senin, 17 November 2025"
    const fmtDate = new Intl.DateTimeFormat('id-ID', {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
      year: 'numeric',
      timeZone: ZONE
    }).format(now);

    // Jam WIB (HH:MM:SS)
    const timeParts = new Intl.DateTimeFormat('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
      timeZone: ZONE
    }).formatToParts(now);

    let hh = '00', mm = '00', ss = '00';
    for (const p of timeParts) {
      if (p.type === 'hour')   hh = p.value;
      else if (p.type === 'minute') mm = p.value;
      else if (p.type === 'second') ss = p.value;
    }

    dateEl.textContent = fmtDate;
    timeEl.textContent = `${hh}:${mm}:${ss}`;
  }

  tick();                 // set awal
  setInterval(tick, 1000); // update tiap detik
})();
</script>

      <!-- STATUS -->
      <div class="d-flex py-3 border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
          style="--icon-color:#19e8e8;"> <!-- hijau untuk ikon -->
        <i class="mdi mdi-credit-card-scan mr-3 icon-lg"></i>
        <div class="d-flex flex-column justify-content-around">
            <small class="mb-1 text-muted label-with-check">
        Status
        <span class="mini-check" title="Active">
          <i class="mdi mdi-check-circle" aria-hidden="true"></i>
        </span>
      </small>
          <h5 class="mr-2 mb-0">
            <span class="status-badge">
              <i class="mdi mdi-check-bold"></i>Active</span>
          </h5>
      </div>
      
      </div>
      
      </div>
      
      </div>
      <?php
// Totals global (tidak ikut filter)
if (!isset($pdo) || !($pdo instanceof PDO)) require_once __DIR__ . '/koneksi.php';

$__ORD = (int)$pdo->query("SELECT COUNT(DISTINCT id_order) FROM orders")->fetchColumn();
$__BAI = (int)$pdo->query("SELECT COUNT(DISTINCT id_bai) FROM bai")->fetchColumn();
$__OUT = max(0, $__ORD - $__BAI);
?>

       <div class="tab-pane fade" id="sales" role="tabpanel" aria-labelledby="sales-tab">
  <div class="d-flex flex-wrap justify-content-xl-between">

    <!-- ORDERS -->
    <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
         style="--icon-color:#182958;"> <!-- ganti HEX sesuai selera -->
      <i class="mdi mdi-clipboard-text-outline mr-3 icon-lg"></i>
      <div class="d-flex flex-column justify-content-around">
        <small class="mb-1 text-muted">Data Orders</small>
        <h5 class="mr-2 mb-0"><span id="metricOrdersCount"><?= number_format($__ORD, 0, ',', '.') ?></span> Total</h5>
      </div>
    </div>

    <!-- BAI -->
    <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
         style="--icon-color:#166534;">
      <i class="mdi mdi-file-document-outline mr-3 icon-lg"></i>
      <div class="d-flex flex-column justify-content-around">
        <small class="mb-1 text-muted">Berita Acara Instalasi</small>
        <h5 class="mr-2 mb-0"><span id="metricBaiCount"><?= number_format($__BAI, 0, ',', '.') ?></span> Total</h5>
      </div>
    </div>

    <!-- OUTSTANDING -->
    <div class="d-flex flex-grow-1 align-items-center justify-content-center p-3 item"
         style="--icon-color:#f5320b;">
      <i class="mdi mdi-alert-circle-outline mr-3 icon-lg"></i>
      <div class="d-flex flex-column justify-content-around">
        <small class="mb-1 text-muted">Outstanding</small>
        <h5 class="mr-2 mb-0"><span id="metricOutCount"><?= number_format($__OUT, 0, ',', '.') ?></span> Total</h5>
      </div>
    </div>

  </div>
</div>
<?php
// Totals global (berdasarkan DISTINCT id_*), tidak ikut filter
require_once __DIR__ . '/koneksi.php';

function db_table_exists(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}
function db_col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}
function try_count_distinct(PDO $pdo, array $cands): int {
  foreach ($cands as $c) {
    [$table,$idcol,$where] = $c + [null,null,''];
    if ($table && $idcol && db_table_exists($pdo,$table) && db_col_exists($pdo,$table,$idcol)) {
      $sql = "SELECT COUNT(DISTINCT `$idcol`) FROM `$table`" . ($where ? " WHERE $where" : "");
      $st  = $pdo->query($sql);
      if ($st) return (int)$st->fetchColumn();
    }
  }
  return 0;
}

// ADMIN: coba beberapa kemungkinan skema
$TOTAL_ADMIN = try_count_distinct($pdo, [
  ['admin','id_admin',''],
  ['admins','id_admin',''],
  ['users','id',"LOWER(role)='admin'"],
  ['akses_login','id',"LOWER(role)='admin'"],
]);

// TA (Telkom Akses)
$TOTAL_TA = try_count_distinct($pdo, [
  ['ta','id_ta',''],
  ['telkom_akses','id_ta',''],
  ['users','id',"LOWER(role) IN ('ta','telkom akses')"],
  ['akses_login','id',"LOWER(role) IN ('ta','telkom akses')"],
]);

// TECH (teknisi)
$TOTAL_TECH = try_count_distinct($pdo, [
  ['tech','id_tech',''],
  ['teknisi','id_tech',''],
  ['users','id',"LOWER(role) IN ('tech','teknisi')"],
  ['akses_login','id',"LOWER(role) IN ('tech','teknisi')"],
]);
?>
<style>
  .icon-img {
  width: 36px; height: 36px;
  object-fit: contain;
  margin-right: .75rem;
  border-radius: 6px;       /* opsional jika png bulat */
}
/* ===== Users / Role stat cards ===== */
/* ===== Users / Role stat cards — formal ===== */
.users-grid{
  display:grid; gap:16px;
  grid-template-columns: repeat(3, minmax(0,1fr));
}
@media (max-width: 992px){ .users-grid{ grid-template-columns: repeat(2,1fr); } }
@media (max-width: 576px){ .users-grid{ grid-template-columns: 1fr; } }

.u-card{
  --accent:#0ea5a4;                       /* override per kartu via inline style */
  display:flex; align-items:center; gap:14px;
  padding:16px 18px;
  background:#fff;
  border:1px #e5e7eb;
  border-left:4px solid var(--accent);     /* aksen rapi, tanpa pseudo-element */
  border-radius:1px;
  box-shadow: 0 1px 2px rgba(15,23,42,.04);
  transition: border-color .15s ease, box-shadow .15s ease;
}
.u-card:hover{ border-color:#dbe0e6; box-shadow:0 2px 6px rgba(15,23,42,.06); }

.u-icon{
  width:52px; height:52px; flex:0 0 52px;
  display:grid; place-items:center;
  background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;
}
.u-icon img{ width:38px; height:38px; object-fit:contain; }

.u-meta small{ display:block; font-size:.85rem; color:#64748b; margin-bottom:2px; }
.u-count{ font-weight:700; font-size:1.35rem; color:#111827; letter-spacing:.1px; }

</style>

                  <div class="tab-pane fade" id="purchases" role="tabpanel" aria-labelledby="purchases-tab">
  <div class="users-grid">

    <!-- ADMIN -->
    <div class="u-card" style="--accent:#0d6efd">
      <span class="u-chip">Admin</span>
      <div class="u-icon">
        <img src="images/user/admin.png" alt="Admin">
      </div>
      <div class="u-meta">
        <small>Admin</small>
        <div class="u-count"><?= number_format($TOTAL_ADMIN, 0, ',', '.') ?></div>
      </div>
    </div>

    <!-- TA -->
    <div class="u-card" style="--accent:#00A3AD">
      <span class="u-chip">TA</span>
      <div class="u-icon">
        <img src="images/user/ta.png" alt="TA">
      </div>
      <div class="u-meta">
        <small>TA</small>
        <div class="u-count"><?= number_format($TOTAL_TA, 0, ',', '.') ?></div>
      </div>
    </div>

    <!-- TECH -->
    <div class="u-card" style="--accent:#f5320b">
      <span class="u-chip">Tech</span>
      <div class="u-icon">
        <img src="images/user/tech.png" alt="Tech">
      </div>
      <div class="u-meta">
        <small>Tech</small>
        <div class="u-count"><?= number_format($TOTAL_TECH, 0, ',', '.') ?></div>
      </div>
    </div>

  </div>
</div>


      </div>
      </div>
      </div>
      


                    
                  </div>
            </div>
            
          <style>
    .chart-box{ height: 320px; }
    @media (max-width: 991.98px){ .chart-box{ height: 260px; } }
  </style>

  <div class="row">
    <!-- BAR (kiri) -->
    <div class="col-md-8 grid-margin">
      <div class="card h-100">
        <div class="card-body">
          <!-- Header + DROPDOWN -->
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <p class="card-title mb-0">ORDER & BAI PER TECH</p>
              <small class="text-muted">Angka di atas batang = nilai aktual.</small>
            </div>

            <!-- Dropdown Filter -->
                <div class="dropdown es-filter">
        <button id="barFilterBtn" class="btn-ghost" data-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
          Semua
        </button>


              <div class="dropdown-menu dropdown-menu-right p-3" aria-labelledby="barFilterBtn">
            <div class="es-opt active" data-mode="hari_ini">
    <input type="radio" name="barFilterMode" value="hari_ini" checked>
    <span>Hari ini</span><span class="es-check">✓</span>
  </div>

  <div class="es-opt" data-mode="semua">
    <input type="radio" name="barFilterMode" value="semua">
    <span>Semua</span><span class="es-check">✓</span>
  </div>

                <div class="es-opt" data-mode="tanggal">
                  <input type="radio" name="barFilterMode" value="tanggal">
                  <span>Berdasarkan tanggal</span><span class="es-check">✓</span>
                </div>
                <div id="blkTanggal" class="es-block d-none">
                  <input type="date" id="barDate" class="form-control form-control-sm">
                </div>

                <div class="es-opt" data-mode="rentang">
                  <input type="radio" name="barFilterMode" value="rentang">
                  <span>Rentang tanggal</span><span class="es-check">✓</span>
                </div>
                <div id="blkRentang" class="es-block d-none">
                  <div class="es-row">
                    <input type="date" id="barStart" class="form-control form-control-sm">
                    <input type="date" id="barEnd"   class="form-control form-control-sm">
                  </div>
                </div>

                <div class="es-opt" data-mode="bulan">
                  <input type="radio" name="barFilterMode" value="bulan">
                  <span>Bulan</span><span class="es-check">✓</span>
                </div>
                <div id="blkBulan" class="es-block d-none">
                  <div class="es-row">
                    <select id="barMonth" class="form-control form-control-sm">
                      <option value="01">Januari</option><option value="02">Februari</option>
                      <option value="03">Maret</option><option value="04">April</option>
                      <option value="05">Mei</option><option value="06">Juni</option>
                      <option value="07">Juli</option><option value="08">Agustus</option>
                      <option value="09">September</option><option value="10">Oktober</option>
                      <option value="11">November</option><option value="12">Desember</option>
                    </select>
                    <input type="number" id="barYear" class="form-control form-control-sm"
            min="2020" max="2100" step="1" placeholder="2025">
                  </div>
                </div>

                <div class="es-opt" data-mode="tahun">
                  <input type="radio" name="barFilterMode" value="tahun">
                  <span>Tahun</span><span class="es-check">✓</span>
                </div>
            <div id="blkTahun" class="es-block d-none">
    <input type="number" id="barYearOnly" class="form-control form-control-sm"
          min="2000" max="5000" step="1" placeholder="2025">
  </div>


                <div class="d-flex justify-content-end mt-3">
                  <button type="button" id="barApply" class="btn btn-sm"
                    aria-label="Terapkan filter" title="Terapkan filter"
                    style="background: #009ea9; border-color: transparent; color: #fff;">
                    <i class="mdi mdi-check" aria-hidden="true"></i>   </button>    </div>
              </div>
            </div>
          </div>

          <div class="chart-box"><canvas id="order-bai-bar"></canvas></div>
        </div>
      </div>
    </div>

    <!-- PIE (kanan) -->
    <div class="col-md-4 grid-margin">
      <div class="card h-100">
        <div class="card-body">
          <p class="card-title mb-1">RINGKASAN (SEMUA TECH)</p>
          <h6 class="text-muted mb-3">Orders vs BAI vs Outstanding</h6>
          <div class="chart-box"><canvas id="order-bai-pie"></canvas></div>
        </div>
      </div>
    </div>
  </div>


  
  <style>
    .chart-box{ height:320px } @media (max-width:991.98px){ .chart-box{ height:260px } }
    .es-filter .dropdown-menu{ min-width:320px }
    .es-opt{ display:flex; align-items:center; gap:.6rem; padding:.45rem .6rem; border-radius:3px; cursor:pointer }
    .es-opt:hover{ background:#f5f7fa } .es-opt input[type=radio]{ appearance:none; width:0; height:0; position:absolute; left:-9999px }
    .es-opt.active{ background: #e1f6f9ff } .es-check{ margin-left:auto; font-weight:700; color:#00A3AD; opacity:0; transition:opacity .15s }
    .es-opt.active .es-check{ opacity:1 } .es-block{ margin-left:1.6rem; margin-top:.35rem } .es-row{ display:flex; gap:.5rem }
    .es-row select,.es-row input{ flex:1 }
    :root { --pie-border: #cbd5e1; } /* abu-abu outline pie */
    /* ==== Simple Chart Card ==== */

/* tombol filter minimal (ghost) */
.btn-ghost{background:#fff;border:1px solid #e5e7eb;color:#334155;border-radius:6px;padding:4px 10px;height:32px;font-size:.85rem}
.btn-ghost:hover{background:#f8fafc}


    </style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

<script>
(() => {
  /* ============== FILTER (dipertahankan) ============== */
  const btnApply  = document.getElementById('barApply');
  const btnTitle  = document.getElementById('barFilterBtn');

  const optRows    = document.querySelectorAll('.es-filter .es-opt');
  const blkTanggal = document.getElementById('blkTanggal');
  const blkRentang = document.getElementById('blkRentang');
  const blkBulan   = document.getElementById('blkBulan');
  const blkTahun   = document.getElementById('blkTahun');

  document.querySelectorAll('.es-filter .dropdown-menu')
    .forEach(menu => menu.addEventListener('click', e => e.stopPropagation()));

  optRows.forEach(r => r.addEventListener('click', () => setMode(r.dataset.mode)));
  function setMode(mode){
    optRows.forEach(r=>{
      const on = (r.dataset.mode === mode);
      r.classList.toggle('active', on);
      const input = r.querySelector('input[type="radio"]'); if(input) input.checked = on;
    });
    syncVisibility();
  }
  function getSelectedMode(){
    const active = document.querySelector('.es-filter .es-opt.active');
    return active ? active.dataset.mode : 'semua';
  }
  function syncVisibility(){
    const m = getSelectedMode();
    blkTanggal.classList.toggle('d-none', m !== 'tanggal');
    blkRentang.classList.toggle('d-none', m !== 'rentang');
    blkBulan.classList.toggle('d-none',   m !== 'bulan');
    blkTahun.classList.toggle('d-none',   m !== 'tahun');
  }
  function monthName(id){ return ({
    '01':'Januari','02':'Februari','03':'Maret','04':'April','05':'Mei','06':'Juni',
    '07':'Juli','08':'Agustus','09':'September','10':'Oktober','11':'November','12':'Desember'
  })[id] || id; }
  function labelForFilter(f){
    switch(f.type){
      case 'hari_ini': return 'Hari ini';
      case 'tanggal':  return f.date  ? new Date(f.date).toLocaleDateString('id-ID') : 'Tanggal';
      case 'rentang':  return (f.start && f.end)
                       ? `${new Date(f.start).toLocaleDateString('id-ID')}–${new Date(f.end).toLocaleDateString('id-ID')}` : 'Rentang';
      case 'bulan':    return (f.month && f.year) ? `${monthName(f.month)} ${f.year}` : 'Bulan';
      case 'tahun':    return f.year || 'Tahun';
      default:         return 'Semua';
    }
  }
  function collectFilter(){
    const mode = getSelectedMode();
    const f = { type: mode };
    if (mode==='tanggal') { f.date  = document.getElementById('barDate').value; }
    if (mode==='rentang') { f.start = document.getElementById('barStart').value; f.end = document.getElementById('barEnd').value; }
    if (mode==='bulan')   { f.month = document.getElementById('barMonth').value; f.year = document.getElementById('barYear').value; }
    if (mode==='tahun')   { f.year  = document.getElementById('barYearOnly').value; }
    return f;
  }

  // === INISIAL FILTER: normal = Hari ini, snapshot (cron_key) = Bulan berjalan ===
  const SNAPSHOT = document.documentElement.classList.contains('snapshot-mode');
  let initialFilter = { type: 'hari_ini' };

  if (SNAPSHOT) {
    // Ambil bulan & tahun berdasarkan zona waktu WIB
    let month = '01', year = '2000';
    try {
      const fmt = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit'
      });
      const parts = fmt.formatToParts(new Date());
      for (const p of parts) {
        if (p.type === 'month') month = p.value;
        else if (p.type === 'year') year = p.value;
      }
    } catch (e) {
      // fallback kalau Intl timeZone gagal
      const now = new Date();
      month = String(now.getMonth() + 1).padStart(2, '0');
      year  = String(now.getFullYear());
    }

    initialFilter = { type: 'bulan', month, year };

    // paksa UI filter ke mode BULAN
    setMode('bulan');
    syncVisibility();
    const monthSel = document.getElementById('barMonth');
    const yearInp  = document.getElementById('barYear');
    if (monthSel) monthSel.value = month;
    if (yearInp)  yearInp.value  = year;
    btnTitle.textContent = labelForFilter(initialFilter); // "November 2025", dll
  } else {
    // mode normal user: default tetap Hari ini
    setMode('hari_ini');
    syncVisibility();
    btnTitle.textContent = 'Hari ini';
  }


  /* ============== UTIL & WARNA ============== */
  const css = getComputedStyle(document.documentElement);
  const COLOR = {
    orders : (css.getPropertyValue('--order-teal-detail-h') || '#0ea5e9').trim(),
    bai    : (css.getPropertyValue('--bai-green-detail-h')  || '#00c97f').trim(),
    out    : (css.getPropertyValue('--info-color')        || '#f59e0b').trim(),
    tick   : (css.getPropertyValue('--ink')               || '#0f172a').trim(),
    grid   : (css.getPropertyValue('--line')              || 'rgba(2,0,36,.06)').trim()
  };

  const SMALL        = matchMedia('(max-width: 992px)').matches;
  const BAR_THICK    = SMALL ? 14 : 20;
  const GROUP_WIDTH  = 0.70;
  const BAR_WIDTH    = 0.90;

  const ellipsify = (s, n=18) => (String(s||'').length>n ? String(s).slice(0,n-1)+'…' : String(s||''));

  function yPolicy(maxVal){
    if (maxVal <= 10)  return { step: 1,  pad: 1  };
    if (maxVal <= 30)  return { step: 2,  pad: 2  };
    if (maxVal <= 50)  return { step: 5,  pad: 5  };
    if (maxVal <= 100) return { step: 10, pad: 10 };
    if (maxVal <= 500) return { step: 25, pad: 25 };
    return { step: 50, pad: 50 };
  }
  function nextNiceYMax(maxVal, step, pad){
    const target = Math.max(step, Math.floor(maxVal) + pad);
    return Math.ceil(target / step) * step;
  }

  /* ============== FETCH DATA ============== */
  async function fetchChartData(f){
    const p = new URLSearchParams({ mode: f.type || 'semua' });
    if (f.date)  p.set('date',  f.date);
    if (f.start) p.set('start', f.start);
    if (f.end)   p.set('end',   f.end);
    if (f.month) p.set('month', f.month);
    if (f.year)  p.set('year',  f.year);

    const res = await fetch('chart.php?' + p.toString(), { cache: 'no-store' });
    if (!res.ok) throw new Error('chart.php error status');
    return await res.json(); // {labels, orders, bai, out, totals:{orders,bai,out}}
  }

  /* ============== CHARTS — SIMPLE + DASHED LINE ============== */
  let barChart, pieChart;

function short5(s){
  // ambil 5 karakter pertama tanpa spasi; fallback ke 5 char biasa
  const t = String(s||'').trim();
  const noSpace = t.replace(/\s+/g,'');
  const cut = (noSpace || t).slice(0,5);
  return cut.toUpperCase();
}
function __hashStr(str){
  str = String(str);
  let h = 2166136261;
  for (let i=0;i<str.length;i++){
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return (h >>> 0);
}
function __rand01(seedStr){
  // xorshift32
  let x = __hashStr(seedStr) || 123456789;
  x ^= x << 13; x >>>= 0;
  x ^= x >> 17; x >>>= 0;
  x ^= x << 5;  x >>>= 0;
  return (x >>> 0) / 4294967295;
}

// ========= PLUGIN: candle (wick + body) dengan profil per dataset =========
const stockbitCandleBars = {
  id: 'stockbitCandleBars',
  defaults: {
    wickWidth: 2,
    wickAlpha: 0.70,
    bodyAlpha: 0.95,
    bodyWidthRatio: 0.52,
    xJitterRatio: 0.10 // biar posisi tiap nama keliatan beda sedikit
  },
  afterDatasetsDraw(chart, _args, opts){
    const o = { ...this.defaults, ...(opts || {}) };
    const { ctx } = chart;
    const y = chart.scales.y;
    if (!y) return;

    const y0 = y.getPixelForValue(0);

    // profil bentuk per jenis dataset
    function profileFor(label){
      const L = String(label || '').toLowerCase();

      // OUTSTANDING: body mepet atas + wick pendek ke bawah
      if (L.includes('out')) {
  return {
    bodyBottom: [0.60, 0.78],   // balok mulai lebih tinggi (jadi balok besar)
    bodyTop:    [0.92, 1.00],   // balok mepet atas
    wickBottom: [0.00, 0.00],   // GA dipakai lagi (nanti kita override)
    wickTop:    [1.00, 1.00]
  };
}


      // BAI: body cenderung tengah-atas
      if (L.includes('bai')) {
        return {
          bodyBottom: [0.18, 0.45],
          bodyTop:    [0.55, 0.88],
          wickBottom: [0.00, 0.00],  // start dari bawah
          wickTop:    [1.00, 1.00]   // tepat v
        };
      }

      // ORDERS: body cenderung tengah-bawah
      return {
        bodyBottom: [0.10, 0.35],
        bodyTop:    [0.45, 0.78],
        wickBottom: [0.00, 0.00],    // start dari bawah
        wickTop:    [1.00, 1.00]     // tepat v
      };
    }

    chart.data.datasets.forEach((ds, di) => {
      const baseType = (ds.type || chart.config.type);
      if (baseType !== 'bar') return;

      const meta = chart.getDatasetMeta(di);
      if (!meta || meta.hidden) return;

      // warna candle ambil dari borderColor (karena background bar dibuat transparan)
      const color = (ds.borderColor || ds.backgroundColor || '#111');

      const prof = profileFor(ds.label);

      meta.data.forEach((el, i) => {
        const v = Number(ds.data?.[i] ?? 0);
        const x0 = el?.x;
        if (!isFinite(x0)) return;

        // kalau 0: dash kecil di baseline
        if (!(v > 0)){
          ctx.save();
          ctx.globalAlpha = 0.45;
          ctx.strokeStyle = color;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.moveTo(x0 - 6, y0);
          ctx.lineTo(x0 + 6, y0);
          ctx.stroke();
          ctx.restore();
          return;
        }

        // slot width
        const slotW =
          (isFinite(el?.width) && el.width > 0) ? el.width :
          Math.max(10, (chart.chartArea.width / Math.max(1, chart.data.labels.length)) * 0.35);

        const bodyW = Math.max(5, slotW * o.bodyWidthRatio);

        // jitter X biar tiap nama gak terlihat "persis sejajar"
        const rj = __rand01(`${chart.data.labels[i]}|${ds.label}|${i}|xj`);
        const dx = (rj - 0.5) * slotW * o.xJitterRatio;
        const x = x0 + dx;

        // random konsisten per nama + dataset (untuk variasi posisi body)
        const r1 = __rand01(`${chart.data.labels[i]}|${ds.label}|${i}|b1`);
        const r2 = __rand01(`${chart.data.labels[i]}|${ds.label}|${i}|b2`);
        const r3 = __rand01(`${chart.data.labels[i]}|${ds.label}|${i}|w1`);

        // hitung range body (dalam persen dari v)
        const bb = prof.bodyBottom[0] + (prof.bodyBottom[1] - prof.bodyBottom[0]) * r1;
        const bt = prof.bodyTop[0]    + (prof.bodyTop[1]    - prof.bodyTop[0])    * r2;

        let bodyBottomVal = v * bb;
        let bodyTopVal    = v * bt;

        bodyBottomVal = Math.max(0, Math.min(v, bodyBottomVal));
        bodyTopVal    = Math.max(0, Math.min(v, bodyTopVal));
        if (bodyTopVal < bodyBottomVal) [bodyTopVal, bodyBottomVal] = [bodyBottomVal, bodyTopVal];

        // wick bottom/top (khusus outstanding: wick bawah tidak sampai 0)
        const wb = prof.wickBottom[0] + (prof.wickBottom[1] - prof.wickBottom[0]) * r3;
        const wt = prof.wickTop[0]; // selalu 1.0 (tepat v)

        const wickBottomVal = v * wb;
        const wickTopVal    = v * wt;

        const yWickBot = y.getPixelForValue(wickBottomVal);
        const yWickTop = y.getPixelForValue(wickTopVal);

        const yBodyTop = y.getPixelForValue(bodyTopVal);
        const yBodyBot = y.getPixelForValue(bodyBottomVal);

        // ===== WICK =====
        ctx.save();
        ctx.strokeStyle = color;
        ctx.globalAlpha = o.wickAlpha;
        ctx.lineWidth   = o.wickWidth;
        ctx.lineCap     = 'round';

        // bawah → bodyBottom (untuk Orders/BAI: dari 0; untuk Outstanding: dari wickBottomVal)
        const isOut = String(ds.label||'').toLowerCase().includes('out');
        const yStart = isOut ? yWickBot : y0;

        ctx.beginPath();
        ctx.moveTo(x, yStart);
        ctx.lineTo(x, yBodyBot);
        ctx.stroke();

        // bodyTop → atas (v)
        ctx.beginPath();
        ctx.moveTo(x, yBodyTop);
        ctx.lineTo(x, yWickTop);
        ctx.stroke();

        ctx.restore();

        // ===== BODY (balok tengah) =====
        const rectTop = Math.min(yBodyTop, yBodyBot);
        const rectBot = Math.max(yBodyTop, yBodyBot);
        const h = Math.max(3, rectBot - rectTop);

        ctx.save();
        ctx.globalAlpha = o.bodyAlpha;
        ctx.fillStyle   = color;
        ctx.fillRect(x - bodyW/2, rectTop, bodyW, h);
        ctx.restore();
      });
    });
  }
};

function buildBarChart(data, ymax, yStep){
  if (window.barChart && typeof window.barChart.destroy === 'function') {
    window.barChart.destroy();
  }

  const ctx = document.getElementById('order-bai-bar').getContext('2d');
  const css = getComputedStyle(document.documentElement);

  const COLOR = {
    orders : (css.getPropertyValue('--order-teal-detail') || '#2e53b6').trim(), // biru
    bai    : (css.getPropertyValue('--bai-green-detail')  || '#23ab57').trim(), // hijau
    out    : (css.getPropertyValue('--info-color')        || '#f5320b').trim(), // merah
    tick   : (css.getPropertyValue('--ink')               || '#0f172a').trim(),
    grid   : (css.getPropertyValue('--line')              || '#e5e7eb').trim()
  };

  const SMALL = matchMedia('(max-width: 992px)').matches;

  const FULL_LABELS  = (data.labels || []).slice();
  const SHORT_LABELS = FULL_LABELS.map(short5);

  // bikin bar “tidak kelihatan”, plugin yang menggambar candle
  const INVIS = 'rgba(0,0,0,0)';

  window.barChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: SHORT_LABELS,
      datasets: [
        {
          label: 'Orders',
          data: data.orders || [],
          backgroundColor: INVIS,
          borderColor: COLOR.orders,
          borderWidth: 0,
          borderSkipped: false,
          categoryPercentage: 0.80,
          barPercentage: 0.90,
          maxBarThickness: SMALL ? 16 : 20,
          datalabels: {
            anchor: 'end', align: 'top', offset: -2,
            formatter: v => (v ?? 0),
            font: { weight:'700', size: 10 }
          }
        },
        {
          label: 'BAI',
          data: data.bai || [],
          backgroundColor: INVIS,
          borderColor: COLOR.bai,
          borderWidth: 0,
          borderSkipped: false,
          categoryPercentage: 0.80,
          barPercentage: 0.90,
          maxBarThickness: SMALL ? 16 : 20,
          datalabels: {
            anchor: 'end', align: 'top', offset: -2,
            formatter: v => (v ?? 0),
            font: { weight:'700', size: 10 }
          }
        },
        {
          label: 'Outstanding',
          data: data.out || [],
          backgroundColor: INVIS,
          borderColor: COLOR.out,
          borderWidth: 0,
          borderSkipped: false,
          categoryPercentage: 0.80,
          barPercentage: 0.90,
          maxBarThickness: SMALL ? 16 : 20,
          datalabels: {
            anchor: 'end', align: 'top', offset: -2,
            formatter: v => (v ?? 0),
            font: { weight:'700', size: 10 }
          }
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      animation: { duration: 280, easing: 'easeOutCubic' },
      layout: { padding: { top: 10, right: 6, bottom: 0, left: 6 } },
      scales: {
        x: {
          offset: true,
          grid: { display: false },
          ticks: {
            autoSkip: false,
            color: COLOR.tick,
            maxRotation: 0,
            font: { size: SMALL ? 10 : 11 }
          }
        },
        y: {
          beginAtZero: true,
          min: 0,
          max: ymax,
          ticks: { stepSize: yStep, color: COLOR.tick, font: { size: SMALL ? 10 : 11 } },
          grid: { color: COLOR.grid },
          border: { display: false }
        }
      },
      plugins: {
  legend: {
    position: 'top',
    labels: {
      usePointStyle: true,
      pointStyle: 'circle',
      boxWidth: 10,
      boxHeight: 10,
      // FIX: paksa warna legend pakai borderColor (karena backgroundColor transparan)
      generateLabels(chart){
        const baseGen =
          (Chart?.defaults?.plugins?.legend?.labels?.generateLabels)
            ? Chart.defaults.plugins.legend.labels.generateLabels
            : null;

        const items = baseGen ? baseGen(chart) : (chart.legend?.legendItems || []);

        return items.map((it) => {
          const ds = chart.data.datasets[it.datasetIndex] || {};
          const col = (ds.borderColor || ds.backgroundColor || '#111');
          return {
            ...it,
            fillStyle: col,
            strokeStyle: col,
            lineWidth: 0,
            pointStyle: 'circle'
          };
        });
      }
    }
  },

  tooltip: {
    padding: 10,
    callbacks: {
      title: (items) => {
        const i = items?.[0]?.dataIndex ?? 0;
        return FULL_LABELS[i] || '';
      }
    }
  },

  stockbitCandleBars: {
    wickWidth: 2,
    wickAlpha: 0.70,
    bodyWidthRatio: 0.52,
    xJitterRatio: 0.10
  },

  datalabels: window.ChartDataLabels ? {} : undefined
}

    },
    plugins: (function(){
      const arr = [stockbitCandleBars];
      if (window.ChartDataLabels) arr.push(ChartDataLabels);
      return arr;
    })()
  });
}

function buildPieChart(totals){
  pieChart?.destroy();
  const ctx = document.getElementById('order-bai-pie').getContext('2d');

  const labels = ['Orders','BAI','Outstanding'];
  const values = [
    Number(totals?.orders||0),
    Number(totals?.bai||0),
    Number(totals?.out||0)
  ];
   const Cbar = (() => {
    const css = getComputedStyle(document.documentElement);
    return {
      orders: (css.getPropertyValue('--order-teal-detail') || '#2e53b6').trim(),
      bai:    (css.getPropertyValue('--bai-green-detail')  || '#23ab57').trim(),
      out:    (css.getPropertyValue('--info-color')        || '#f5320b').trim()
    };
  })();
  const colors = [Cbar.orders, Cbar.bai, Cbar.out];
  const PIE_BORDER = (getComputedStyle(document.documentElement)
    .getPropertyValue('--pie-border') || '#cbd5e1').trim();

  const maxIdx     = values.indexOf(Math.max(...values));
  const ORDERS_IDX = 0;          // index Orders
  const BW_NORMAL  = 5;          // outline abu-abu normal (inner)
  const BW_HOVER   = 2;

  pieChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: colors,

        // Outline abu-abu untuk SEMUA irisan
        borderAlign: 'inner',
        borderColor: PIE_BORDER,
        borderWidth: BW_NORMAL,

        // Hover: khusus Orders tetap abu-abu (tanpa ring biru)
        hoverBorderColor: (c) =>
          (c.dataIndex === ORDERS_IDX ? PIE_BORDER : colors[c.dataIndex]),
        hoverBorderWidth: (c) =>
          (c.dataIndex === ORDERS_IDX ? BW_NORMAL : BW_HOVER),

        // “explode” irisan terbesar (opsional)
        offset: labels.map((_, i) => i === maxIdx ? 14 : 0),
        hoverOffset: 10
      }]
    },
  options: {
  responsive: true,
  maintainAspectRatio: false,
  animation: { duration: 350, easing: 'easeOutCubic' },
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: false,      // kotak (bukan bullet)
        boxWidth: 22,
        boxHeight: 10,
        borderRadius: 3,
        padding: 24,               // jarak antar item legend
        generateLabels(chart){
          const ds   = chart.data.datasets[0];
          const vals = ds.data || [];
          const cols = ds.backgroundColor || [];
          const meta = chart.getDatasetMeta(0);

          return chart.data.labels.map((name, i) => {
            const num = Number(vals[i] || 0).toLocaleString('id-ID');
            return {
              text: `${num} ${name}`,                  // <-- angka + label
              fillStyle: Array.isArray(cols) ? cols[i] : cols,
              strokeStyle: 'transparent',
              lineWidth: 0,
              borderRadius: 3,
              hidden: meta.data[i] ? meta.data[i].hidden : false,
              index: i
            };
          });
        }
      }
    },
        tooltip: {
          padding: 8,
          callbacks: {
            label: (ctx) => `${ctx.label}: ${(ctx.parsed||0).toLocaleString('id-ID')}`
          }
        },
        // Label di dalam irisan: ANGKA + NAMA
        datalabels: window.ChartDataLabels ? {
          color: '#fff',
          font: { weight: '700', size: 12 },
          align: 'center',
          clamp: true,
          formatter: (v, ctx) =>
            v ? `${(+v).toLocaleString('id-ID')}\n${ctx.chart.data.labels[ctx.dataIndex]}` : ''
        } : undefined
      },
      elements: { arc: { borderAlign: 'inner' } }
    },
    plugins: window.ChartDataLabels ? [ChartDataLabels] : []
  });
}

  /* ============== INIT / APPLY ============== */
  async function loadCharts(filter){
    const data = await fetchChartData(filter);
    const maxVal = Math.max(
      ...(data.orders || [0]),
      ...(data.bai    || [0]),
      ...(data.out    || [0])
    );
    const { step, pad } = yPolicy(maxVal);
    const ymax = nextNiceYMax(maxVal, step, pad);

    buildBarChart(data, ymax, step);
    buildPieChart(data.totals || {orders:0,bai:0,out:0});
  }

    btnApply?.addEventListener('click', async () => {
    const f = collectFilter();
    btnTitle.textContent = labelForFilter(f);
    await loadCharts(f);
  });

  // pakai initialFilter: kalau snapshot -> Bulan berjalan, kalau tidak -> Hari ini
  loadCharts(initialFilter).catch(console.error);
})();
</script>



<script>
// Plugin: perpanjang garis Outstanding dari kiri-bawah sampai titik pertama,
// lalu dari titik terakhir ke kanan-bawah.
const extendOutstandingEdges = {
  id: 'extendOutstandingEdges',
  defaults: { dsIndex: 2 },         // index dataset Outstanding
  // was: afterDatasetsDraw(...)
  beforeDatasetsDraw(chart, _args, opts) {
    const idx = (opts && typeof opts.dsIndex === 'number') ? opts.dsIndex : 2;
    const meta = chart.getDatasetMeta(idx);
    if (!meta || !meta.data || meta.data.length === 0) return;

    const first = meta.data[0].getProps(['x','y'], true);
    const last  = meta.data[meta.data.length - 1].getProps(['x','y'], true);
    const { left, right, bottom } = chart.chartArea;
    const ds  = chart.data.datasets[idx];
    const ctx = chart.ctx;

    ctx.save();
    ctx.setLineDash(ds.borderDash || []);
    ctx.strokeStyle = ds.borderColor || '#ED1C24';
    ctx.lineWidth   = ds.borderWidth || 2;

    // kiri-bawah → titik pertama
    ctx.beginPath();
    ctx.moveTo(left, bottom);
    ctx.lineTo(first.x, first.y);
    ctx.stroke();

    // titik terakhir → kanan-bawah
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(right, bottom);
    ctx.stroke();
    ctx.restore();
  }
};

</script>




                <div class="row">
        <div class="col-12 stretch-card">
          <div class="card">
            <div class="card-body">
              <p class="card-title mb-2">DETAIL REKAPAN</p>
              <div class="table-responsive">
              <?php
  // --- KONEKSI (pakai $pdo atau fallback ke $conn) ---
  require_once __DIR__ . '/koneksi.php';
  if (!isset($pdo) || !($pdo instanceof PDO)) { if (isset($conn) && $conn instanceof PDO) { $pdo = $conn; } }
  if (!isset($pdo) || !($pdo instanceof PDO)) { die('Koneksi DB gagal. Pastikan koneksi.php mengisi $pdo (PDO).'); }

  // helper escape kalau belum ada
  if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

  // --- QUERY RINGKAS PER TEKNISI ---
  // Cocokkan tech.username_tech dengan orders.sender_username dan bai.uploader (case-insensitive & trim)
$sql = "
SELECT
  t.id_tech,
  t.nama_tech,
  t.username_tech,
  COALESCE(o.cnt,0)  AS orders_cnt,
  COALESCE(b.cnt,0)  AS bai_cnt,
  GREATEST(COALESCE(o.cnt,0) - COALESCE(b.cnt,0), 0) AS outstanding_cnt
FROM tech t

/* Cocokkan teknisi ↔ akun login via USERNAME (normalize: tanpa @/spasi, lower) */
LEFT JOIN akses_login a
  ON LOWER(REPLACE(REPLACE(TRIM(a.username),'@',''),' ','')) =
     LOWER(REPLACE(REPLACE(TRIM(t.username_tech),'@',''),' ',''))

/* Agregat ORDERS per user_id (Telegram) */
LEFT JOIN (
  SELECT user_id, COUNT(DISTINCT TRIM(no_sc)) AS cnt
  FROM orders
  WHERE COALESCE(TRIM(no_sc),'') <> ''
  GROUP BY user_id
) o
  /* <-- kunci yang benar: user_id (orders) ↔ chat_id (akses_login) */
  ON CAST(o.user_id AS CHAR) = CAST(a.chat_id AS CHAR)

/* Agregat BAI per user_id (Telegram) */
LEFT JOIN (
  SELECT user_id, COUNT(DISTINCT TRIM(kode_bai)) AS cnt
  FROM bai
  WHERE COALESCE(TRIM(kode_bai),'') <> ''
  GROUP BY user_id
) b
  /* <-- kunci yang benar: user_id (bai) ↔ chat_id (akses_login) */
  ON CAST(b.user_id AS CHAR) = CAST(a.chat_id AS CHAR)

ORDER BY t.nama_tech ASC
";


  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <style>
    /* tampilan tabel rapi */
    #tech-summary.table thead th { white-space: nowrap; }
    .badge-kpi{ font-weight:600; border-radius:0rem; padding:.25rem .5rem; font-size:.82rem; }
    .badge-orders{ background:#2f4788ff; color:#fff; }     /* BUMN light */
    .badge-bai{    background:#1a8644ff; color:#fff; }  /* abu */
    .badge-out{    background:#f5320bff; color:#fff; }     /* Telkom red */
    .btn-view{
      --btn: #0ea5a4;
      display:inline-flex; align-items:center; gap:.35rem;
      padding:.35rem .6rem; border:1px solid var(--btn); color:var(--btn);
      background:#fff; border-radius:1rem; font-weight:600; text-decoration:none;
      transition:.15s;
    }
    .btn-view:hover{ background:var(--btn); color:#fff; text-decoration:none; }
    .btn-view .mdi{ font-size:16px; line-height:1; }
    /* Lebarkan modal & kunci body supaya header/footer tetap */
#viewTechModal .modal-dialog{
  max-width: min(1200px, 95vw); /* lebar besar tapi tetap responsif */
}

/* Biar body modal membentuk kolom dan wrapper tabel yang akan scroll */
#viewTechModal .modal-body{
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 180px); /* tinggi aman layar, header/footer tetap */
}

/* HANYA isi tabel yang scroll (vertikal & horizontal bila perlu) */
#vtTableWrap{
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;           /* <— ini kuncinya */
}

  </style>

  <table id="example" class="table table-striped table-hover w-100">
    <thead>
      <tr>
        <th>No</th>
        <th>Nama</th>
        <th>Order</th>
        <th>Berita Acara Instalasi</th>
        <th>Outstanding</th>
       
      </tr>
    </thead>
    <tbody>
    <?php
    $no = 1;
    foreach ($rows as $r):
      $nama = $r['nama_tech'] ?: $r['username_tech'];
      $u    = $r['username_tech'];
      $o    = (int)$r['orders_cnt'];
      $b    = (int)$r['bai_cnt'];
      $out  = (int)$r['outstanding_cnt'];
    ?>
      <tr>
        <td><?= $no++ ?></td>
        <td><?= e($nama) ?> <small class="text-muted d-block">@<?= e($u) ?></small></td>
        <td><span class="badge-kpi badge-orders"><?= $o ?></span></td>
        <td><span class="badge-kpi badge-bai"><?= $b ?></span></td>
        <td><span class="badge-kpi badge-out"><?= $out ?></span></td>
       
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

<script>
(function(){
  const $$ = (sel,root=document)=>root.querySelector(sel);

  // --- helper ukuran file
  function fmtBytes(b){
    if(!b && b!==0) return '-';
    const u=['B','KB','MB','GB']; let i=0;
    while(b>=1024 && i<u.length-1){ b/=1024; i++; }
    return b.toFixed(1)+' '+u[i];
  }

  // --- DataTables instance untuk modal utama
  let vtDT = null;
  let vtUser = '';   // username/uploader aktif

  // dipanggil saat tombol View teknisi diklik (kamu sudah punya handlernya)
  async function loadTechRows(user){
    vtUser = user;

    const url = 'api/tech_rows.php?user=' + encodeURIComponent(user);
    const res = await fetch(url, {cache:'no-store'});
    const json = await res.json();

    const rows = (json.rows || []).map(r => {
      const code  = r.no_sc || r.code || '';
      // sisi kanan (BAI)
      let baiHtml = '';
      if (r.bai && r.bai.name){
        const safeName = String(r.bai.name);
        const url = r.bai.url;
        const size = r.bai.size;
        baiHtml =
          `<a href="#" class="bai-link" data-name="${safeName}"
                         data-url="${url}" data-size="${size||''}">
             ${safeName.replace(/^(.{32}).+$/, '$1…')}
           </a>`;
      } else {
        // UI upload kalau belum ada
        const inputId = 'f_'+Math.random().toString(36).slice(2);
        baiHtml =
          `<div class="vt-upload" data-code="${code}">
             <label class="pick mb-0" for="${inputId}">
               <i class="mdi mdi-upload"></i> Pilih file
             </label>
             <input id="${inputId}" type="file" accept=".pdf,.jpg,.jpeg,.png">
             <button type="button" class="btn btn-sm btn-primary send" disabled>
               Upload
             </button>
           </div>`;
      }
      return [code, baiHtml];
    });

    // render/refresh DataTables
   if (vtDT){
  vtDT.clear().rows.add(rows).draw();
} else {
  vtDT = $('#vtTable').DataTable({
    data: rows,
    columns: [
      { title:'Order (No SC)' },
      { title:'BAI', orderable:false }
    ],
    paging: false,        // <— tampilkan semua
    info: true,           // info jumlah baris
    searching: true,
    autoWidth: false,
    responsive: true,     // collapse rapi di layar sempit
    order: [[0,'asc']]
  });

  // adjust kolom ketika modal tampak
  $('#viewTechModal').on('shown.bs.modal', () => vtDT.columns.adjust());
}

  }

  // --- hook tombol "View" (tepatkan dengan punyamu)
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('.btn-view');
    if (!btn) return;

    const user = btn.dataset.user || '';
    // isi header yg sudah ada
    $('#vtName').text(btn.dataset.name || '-');
    $('#vtUser').text('@'+(user||'-'));
    $('#vtOrders').text(btn.dataset.orders || '0');
    $('#vtBai').text(btn.dataset.bai || '0');
    $('#vtOut').text(btn.dataset.out || '0');

    // muat tabel
    loadTechRows(user).catch(console.error);
  });

  // --- klik link BAI → buka modal file
  document.addEventListener('click', function(ev){
    const a = ev.target.closest('.bai-link');
    if (!a) return;
    ev.preventDefault();

    const url  = a.dataset.url;
    const name = a.dataset.name || '';
    const size = a.dataset.size ? fmtBytes(+a.dataset.size) : '-';

    $('#bfFullName').text(name);
    $('#bfShownName').val(name);
    $('#bfSize').text(size);

    $('#bfView').attr('href', url);
    $('#bfDownload').data('url', url);

    $('#baiFileModal').modal('show');
  });

  // --- tombol download di modal file
  $('#bfDownload').on('click', async function(){
    const url = $(this).data('url');
    if(!url) return;
    try{
      const resp = await fetch(url);
      if(!resp.ok) throw new Error('HTTP '+resp.status);
      const blob = await resp.blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = ( $('#bfShownName').val() || 'BAI.pdf' );
      document.body.appendChild(a); a.click();
      setTimeout(()=>URL.revokeObjectURL(a.href), 1500);
      a.remove();
    }catch(e){ alert('Gagal download: '+e.message); }
  });

  // --- Upload handler (delegasi)
  document.addEventListener('change', function(ev){
    const input = ev.target.closest('.vt-upload input[type=file]');
    if(!input) return;
    const wrap = input.closest('.vt-upload');
    const btn  = wrap.querySelector('.send');
    btn.disabled = !input.files.length;
  });

  document.addEventListener('click', async function(ev){
    const btn = ev.target.closest('.vt-upload .send');
    if(!btn) return;
    const wrap = btn.closest('.vt-upload');
    const input = wrap.querySelector('input[type=file]');
    const code  = wrap.dataset.code || '';
    if(!input.files.length) return;

    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('code', code);
    fd.append('user', vtUser);

    btn.disabled = true; btn.textContent = 'Uploading…';
    try{
      const res = await fetch('api/bai_upload.php', { method:'POST', body: fd });
      const j = await res.json();
      if(!j.ok) throw new Error(j.error || 'Upload gagal');
      // reload tabel setelah upload sukses
      await loadTechRows(vtUser);
    }catch(e){
      alert(e.message);
    }finally{
      btn.textContent = 'Upload';
      btn.disabled = false;
    }
  });

  // expose bila perlu
  window.__loadTechRows = loadTechRows;
})();
</script>

  <script>
  // DataTables (jika plugin sudah dipasang di template-mu)
  $(function () {
  $('#example').DataTable({
    pageLength: 10,
    order: [[1,'asc']],
    columnDefs: [
      { targets: [2,3,4], className: 'text-center' },
      { targets: 0, width: 40 }
    ]
  });
});

  

  // GANTI blok "Modal 'View' sederhana ..."
document.addEventListener('click', function(ev){
  const btn = ev.target.closest('.btn-view');
  if (!btn) return;

  const user = btn.dataset.user || '';
  document.getElementById('vtName').textContent  = btn.dataset.name || '-';
  document.getElementById('vtUser').textContent  = '@' + user;
  document.getElementById('vtOrders').textContent= btn.dataset.orders || '0';
  document.getElementById('vtBai').textContent   = btn.dataset.bai || '0';
  document.getElementById('vtOut').textContent   = btn.dataset.out || '0';

  // panggil loader (AJAX) → isi DataTable di modal
  window.__loadTechRows?.(user);
});

  </script>
<?php if (!empty($isSnapshot)) : ?>
<script>
  // default: belum siap
  window.efidiDashboardReady = false;

  (function() {
    // hanya aktif kalau mode snapshot (dipanggil dari cron_key)
    var isSnapshot = <?php echo $isSnapshot ? 'true' : 'false'; ?>;
    if (!isSnapshot) return;

    function markReady() {
      if (window.efidiDashboardReady === true) return;
      window.efidiDashboardReady = true;
      console.log('efidiDashboardReady = true (snapshot)');
    }

    // Contoh: kalau pakai DataTables
    if (window.jQuery && jQuery.fn.dataTable) {
      // ganti #tabel_tech dengan ID DataTable utama di dashboard admin
      $('#tabel_tech').on('init.dt', function () {
        markReady();
      });
    }

    // Fallback: kalau event di atas tidak kejadian dalam 9 detik
    setTimeout(markReady, 9000);
  })();
</script>
<?php endif; ?>


              </div>
            </div>
          </div>
          </div>
          </div>
          </div>
          </div>
          <!-- content-wrapper ends -->
          <!-- partial:partials/_footer.html -->
      <?php
      include "footer.php";
        ?>