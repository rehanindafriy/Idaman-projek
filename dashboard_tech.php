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
            <p class="mb-0">Untuk Mencari Berita Acara lebih dari 1 bisa mencari di Form Search dan klik button search</p>
            <p>Jika ingin mencari 1 Berita acara bisa mencari di form search datatables</p>
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
        <h5 class="mr-2 mb-0">Muhammad Fulan</h5>
      </div>
    </div>

    <!-- ROLE -->
    <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
         style="--icon-color:#009ea9;"> <!-- hijau -->
      <i class="mdi mdi-account-card-details mr-3 icon-lg"></i>
      <div class="d-flex flex-column justify-content-around">
        <small class="mb-1 text-muted">Role</small>
        <h5 class="mr-2 mb-0">TA</h5>
      </div>
    </div>

    <!-- USERNAME -->
    <div class="d-flex border-md-right flex-grow-1 align-items-center justify-content-center p-3 item"
         style="--icon-color:#182958;"> <!-- kuning -->
      <i class="mdi mdi-at mr-3 icon-lg"></i>
      <div class="d-flex flex-column justify-content-around">
        <small class="mb-1 text-muted">Username</small>
        <h5 class="mr-2 mb-0">fulanda</h5>
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
  const root   = document.getElementById('itemDateTime');
  if(!root) return;

  const dateEl = root.querySelector('.today-date');
  const timeEl = root.querySelector('.current-time');

  function two(n){ return String(n).padStart(2,'0'); }

  function tick(){
    const now = new Date();

    // Contoh: "Sabtu, 11 Oktober 2025"
    const fmtDate = new Intl.DateTimeFormat('id-ID', {
      weekday: 'long', day: '2-digit', month: 'long', year: 'numeric'
    }).format(now);

    const hh = two(now.getHours());
    const mm = two(now.getMinutes());
    const ss = two(now.getSeconds());

    dateEl.textContent = fmtDate;
    timeEl.textContent = `${hh}:${mm}:${ss}`;
  }

  tick();                // set awal
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
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                    id="barFilterBtn"
                    data-toggle="dropdown"                
                    data-bs-auto-close="outside"          
                    aria-haspopup="true" aria-expanded="false">
              Semua
            </button>

            <div class="dropdown-menu dropdown-menu-right p-3" aria-labelledby="barFilterBtn">
              <div class="es-opt active" data-mode="semua">
                <input type="radio" name="barFilterMode" value="semua" checked>
                <span>Semua</span><span class="es-check">✓</span>
              </div>

              <div class="es-opt" data-mode="hari_ini">
                <input type="radio" name="barFilterMode" value="hari_ini">
                <span>Hari ini</span><span class="es-check">✓</span>
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
                <button type="button" id="barApply" class="btn btn-sm btn-primary">Terapkan</button>
              </div>
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


<!-- libs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<style>
  .chart-box{ height:320px } @media (max-width:991.98px){ .chart-box{ height:260px } }
  .es-filter .dropdown-menu{ min-width:320px }
  .es-opt{ display:flex; align-items:center; gap:.6rem; padding:.45rem .6rem; border-radius:3px; cursor:pointer }
  .es-opt:hover{ background:#f5f7fa } .es-opt input[type=radio]{ appearance:none; width:0; height:0; position:absolute; left:-9999px }
  .es-opt.active{ background: #e1f6f9ff } .es-check{ margin-left:auto; font-weight:700; color:#00A3AD; opacity:0; transition:opacity .15s }
  .es-opt.active .es-check{ opacity:1 } .es-block{ margin-left:1.6rem; margin-top:.35rem } .es-row{ display:flex; gap:.5rem }
  .es-row select,.es-row input{ flex:1 }
</style>


<script>
(() => {
  // ====== TUNABLE ======
  const Y_MAX = 20;
  const PIE_RADIUS = '80%';
  const PIE_CUTOUT = '50%';
  const BUMN_LIGHT = '#00A3AD'; // Orders
  const BUMN_DARK  = '#a7a7a7'; // BAI
  const TELKOM_RED = '#ED1C24'; // Outstanding

  // ====== DATA BASE (contoh) ======
  const labelsBase = ['Nini\\nHawkins','Marvin\\nPaul','Vincent\\nEstrada','Rhonda\\nRamsey','Tyler\\nMatthews','Kendra\\nMurphy'];
  const ordersBase = [18,16,18,10,12,8];
  const baiBase    = [ 6, 5, 4, 2, 6,1];

  // ====== Plugins donut ======
  const centerLabelPlugin = {
    id: 'centerLabel',
    afterDraw(chart){
      if (chart.config.type !== 'doughnut') return;
      const meta = chart.getDatasetMeta(0);
      if (!meta?.data?.length) return;
      const {x: cx, y: cy} = meta.data[0]; const {ctx} = chart;
      ctx.save(); ctx.textAlign='center'; ctx.textBaseline='middle';
      ctx.fillStyle='#334155'; ctx.font='700 15px Roboto, Arial, sans-serif';
      ctx.fillText('ALL REPORT', cx, cy); ctx.restore();
    }
  };
  const cornerTotalsPlugin = {
    id: 'cornerTotals',
    afterDraw(chart){
      if (chart.config.type !== 'doughnut') return;
      const ds = chart.data.datasets?.[0]; if (!ds) return;
      const [orders, bai] = ds.data;
      const {ctx, chartArea} = chart; const pad=10;
      let x=chartArea.right-pad, y=chartArea.top+pad;
      ctx.save(); ctx.textAlign='right'; ctx.textBaseline='top'; ctx.font='700 12px Roboto, Arial, sans-serif';
      const box=10, gap=6, lineGap=4;
      ctx.fillStyle=BUMN_LIGHT; ctx.fillRect(x-box,y,box,box); ctx.fillStyle='#334155'; ctx.fillText(`Orders: ${orders}`, x-(box+gap), y);
      y+=box+lineGap;
      ctx.fillStyle=BUMN_DARK;  ctx.fillRect(x-box,y,box,box); ctx.fillStyle='#334155'; ctx.fillText(`BAI: ${bai}`, x-(box+gap), y);
      ctx.restore();
    }
  };

  // ===== helper =====
  function sum(a){ return a.reduce((x,y)=>x+y,0); }
  function loadDataForFilter(filter){
    // Placeholder logic: skala nilai agar terasa berubah.
    const scaleMap = {semua:1, hari_ini:0.35, tanggal:0.5, rentang:0.7, bulan:0.85, tahun:1};
    const k = scaleMap[filter.type] ?? 1;
    const orders = ordersBase.map(v => Math.max(0, Math.round(v*k)));
    const bai    = baiBase.map((v,i) => Math.min(orders[i], Math.round(v*k)));
    const out    = orders.map((o,i) => Math.max(o - bai[i], 0));
    return { labels: labelsBase.slice(), orders, bai, out,
             totals: { orders: sum(orders), bai: sum(bai), out: sum(out) } };
  }
  function labelForFilter(filter){
    switch(filter.type){
      case 'hari_ini': return 'Hari ini';
      case 'tanggal':  return filter.date ? new Date(filter.date).toLocaleDateString('id-ID') : 'Tanggal';
      case 'rentang':  return (filter.start && filter.end) ? `${new Date(filter.start).toLocaleDateString('id-ID')}–${new Date(filter.end).toLocaleDateString('id-ID')}` : 'Rentang';
      case 'bulan':    return filter.month || 'Bulan';
      case 'tahun':    return filter.year  || 'Tahun';
      default:         return 'Semua';
    }
  }

  // ===== rebuild util =====
  function rebuildChart(canvas, config){
    const ctx = canvas.getContext('2d');
    const prev = Chart.getChart(canvas); if (prev) prev.destroy();
    return new Chart(ctx, config);
  }

  // ===== init charts (default: semua) =====
  const initData = loadDataForFilter({type:'semua'});

  const barCanvas = document.getElementById('order-bai-bar');
  const pieCanvas = document.getElementById('order-bai-pie');
  const GROUP_WIDTH = 0.62;  // kecilkan kalau mau antar-kategori lebih rapat
const BAR_WIDTH   = 1.0;   // 1.0 = antar bar (tosca & abu) nyaris menempel
  let esBarChart = null;
  let esPieChart = null;
  

  if (barCanvas){
    esBarChart = rebuildChart(barCanvas, {
      type:'bar',
 data: {
  labels: initData.labels,
  datasets: [
    // Orders (BAR)
    {
      type:'bar',
      label:'Orders',
      data:initData.orders,
      backgroundColor: BUMN_LIGHT,
      borderRadius: 2, borderSkipped:false, borderWidth:0,
      // >>> rapatkan pasangan bar:
      categoryPercentage: GROUP_WIDTH,
  barPercentage: BAR_WIDTH,
      maxBarThickness: 26,
      datalabels:{ anchor:'end', align:'end', offset:3, color:'#0f172a', formatter:v=>v||'', font:{weight:'700'} }
    },
    // BAI (BAR)
    {
      type:'bar',
      label:'BAI',
      data:initData.bai,
      backgroundColor: BUMN_DARK,
      borderRadius: 2, borderSkipped:false, borderWidth:0,
      // >>> samakan agar bar saling menempel rapat
      categoryPercentage: GROUP_WIDTH,
  barPercentage: BAR_WIDTH,
      maxBarThickness: 26,
      datalabels:{ anchor:'end', align:'end', offset:3, color:'#0f172a', formatter:v=>v||'', font:{weight:'700'} }
    },
    // Outstanding (LINE di atas bar)
    {
      type:'line',
      label:'Outstanding',
      data:initData.out,
      borderColor: TELKOM_RED,
      backgroundColor: TELKOM_RED,
      borderWidth: 3,
      tension: 0,
      // >>> kecilkan titik line:
      pointRadius: 2,
      pointHoverRadius: 4,
      pointBorderWidth: 1.5,
      pointBackgroundColor: '#fff',
      pointBorderColor: TELKOM_RED,
      fill: false,
      spanGaps: true,
      clip: false,
      order: 99,
      datalabels:{ anchor:'end', align:'top', offset:6, color:TELKOM_RED, formatter:v=>v||'', font:{weight:'700'} }
    }
  ]
},
options: {
  responsive:true,
  maintainAspectRatio:false,
  layout:{ padding:{ top:18, right:8, bottom:0, left:6 }},
  interaction:{ mode:'index', intersect:false },
  plugins:{
    legend:{
      position:'top',
      labels:{
        usePointStyle:true, boxWidth:8, boxHeight:8,
        // >>> beri jarak antar item legend:
        padding: 22
      }
    },
    tooltip:{ mode:'index', intersect:false },
    datalabels:{ display:true }
  },
  scales:{
    x:{ ticks:{ autoSkip:false, maxRotation:0, font:{ size:11 } }, grid:{ display:false } },
    y:{ beginAtZero:true, max:Y_MAX, ticks:{ stepSize:5, precision:0 }, grid:{ color:'rgba(148,163,184,.25)' }, border:{display:false} }
  }
}
,
      plugins:[ChartDataLabels]
    });
  }

  if (pieCanvas){
    esPieChart = rebuildChart(pieCanvas, {
      type:'doughnut',
      data:{
        labels:['Orders','BAI','Outstanding'],
        datasets:[{ data:[initData.totals.orders, initData.totals.bai, initData.totals.out],
                    backgroundColor:[BUMN_LIGHT,BUMN_DARK,TELKOM_RED], borderWidth:0, borderRadius:0, hoverOffset:4 }]
      },
      options:{
        responsive:true, maintainAspectRatio:false,
        radius: PIE_RADIUS, cutout: PIE_CUTOUT,
        plugins:{
          legend:{ position:'bottom' },
          datalabels:{ color:'#ffffff',
            formatter:(v,ctx)=>{ const arr=ctx.chart.data.datasets[0].data||[]; const total=arr.reduce((a,b)=>a+b,0); if(!total||!v) return ''; const pct=Math.round((v/total)*100); return `${v} (${pct}%)`; },
            font:{ weight:'530' }
          }
        }
      },
      plugins:[ChartDataLabels, centerLabelPlugin, cornerTotalsPlugin]
    });
  }

  // === helper: isi dropdown tahun (2020..tahun sekarang) ===
// --- Jangan tutup dropdown saat klik di dalam menu (Bootstrap 4/5) ---
document.querySelectorAll('.es-filter .dropdown-menu')
  .forEach(menu => menu.addEventListener('click', e => e.stopPropagation()));

// === helper: isi dropdown tahun (2020..tahun sekarang) ===
function fillYearSelect(sel, start=2020, end=(new Date().getFullYear())){
  if(!sel) return; sel.innerHTML='';
  for(let y=end; y>=start; y--){
    const o=document.createElement('option'); o.value=String(y); o.textContent=String(y);
    if(y===end) o.selected=true; sel.appendChild(o);
  }
}
fillYearSelect(document.getElementById('barYear'));
fillYearSelect(document.getElementById('barYearOnly'));

// === pilih mode tanpa radio-bulat ===
const optRows   = document.querySelectorAll('.es-filter .es-opt');
const blkTanggal= document.getElementById('blkTanggal');
const blkRentang= document.getElementById('blkRentang');
const blkBulan  = document.getElementById('blkBulan');
const blkTahun  = document.getElementById('blkTahun');
const btnApply  = document.getElementById('barApply');
const btnTitle  = document.getElementById('barFilterBtn');

function setMode(mode){
  optRows.forEach(r=>{
    const on=r.dataset.mode===mode;
    r.classList.toggle('active', on);
    const input=r.querySelector('input[type="radio"]'); if(input) input.checked=on;
  });
  syncVisibility();
}
optRows.forEach(r=>r.addEventListener('click',()=>setMode(r.dataset.mode)));

function getSelectedMode(){
  const active=document.querySelector('.es-filter .es-opt.active');
  return active?active.dataset.mode:'semua';
}
function syncVisibility(){
  const mode=getSelectedMode();
  blkTanggal.classList.toggle('d-none', mode!=='tanggal');
  blkRentang.classList.toggle('d-none', mode!=='rentang');
  blkBulan.classList.toggle('d-none',   mode!=='bulan');
  blkTahun.classList.toggle('d-none',   mode!=='tahun');
}
syncVisibility();

// === label tombol sesuai filter ===
function monthName(id){ return ({
  '01':'Januari','02':'Februari','03':'Maret','04':'April','05':'Mei','06':'Juni',
  '07':'Juli','08':'Agustus','09':'September','10':'Oktober','11':'November','12':'Desember'
})[id] || id; }
function labelForFilter(filter){
  switch(filter.type){
    case 'hari_ini': return 'Hari ini';
    case 'tanggal':  return filter.date ? new Date(filter.date).toLocaleDateString('id-ID') : 'Tanggal';
    case 'rentang':  return (filter.start && filter.end) ? `${new Date(filter.start).toLocaleDateString('id-ID')}–${new Date(filter.end).toLocaleDateString('id-ID')}` : 'Rentang';
    case 'bulan':    return (filter.month && filter.year) ? `${monthName(filter.month)} ${filter.year}` : 'Bulan';
    case 'tahun':    return filter.year || 'Tahun';
    default:         return 'Semua';
  }
}

// === Apply: ambil nilai input & update chart ===
btnApply?.addEventListener('click', () => {
  const mode = getSelectedMode();
  const filter = { type: mode };
  if (mode==='tanggal') { filter.date  = document.getElementById('barDate').value; }
  if (mode==='rentang') { filter.start = document.getElementById('barStart').value; filter.end = document.getElementById('barEnd').value; }
  if (mode==='bulan')   { filter.month = document.getElementById('barMonth').value; filter.year = document.getElementById('barYear').value; }
  if (mode==='tahun')   { filter.year  = document.getElementById('barYearOnly').value; }

  btnTitle.textContent = labelForFilter(filter);

  // ambil & terapkan data (pakai loadDataForFilter milikmu)
  const d = loadDataForFilter(filter);
  if (esBarChart){
    esBarChart.data.labels = d.labels;
    esBarChart.data.datasets[0].data = d.orders;
    esBarChart.data.datasets[1].data = d.bai;
    esBarChart.data.datasets[2].data = d.out;
    esBarChart.update();
  }
  if (esPieChart){
    esPieChart.data.datasets[0].data = [d.totals.orders, d.totals.bai, d.totals.out];
    esPieChart.update();
  }
});

})();
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
LEFT JOIN (
  SELECT LOWER(TRIM(sender_username)) AS u, COUNT(*) AS cnt
  FROM orders
  GROUP BY LOWER(TRIM(sender_username))
) o ON o.u = LOWER(TRIM(t.username_tech))
LEFT JOIN (
  SELECT LOWER(TRIM(uploader)) AS u, COUNT(*) AS cnt
  FROM bai
  GROUP BY LOWER(TRIM(uploader))
) b ON b.u = LOWER(TRIM(t.username_tech))
ORDER BY t.nama_tech ASC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
  /* tampilan tabel rapi */
  #tech-summary.table thead th { white-space: nowrap; }
  .badge-kpi{ font-weight:600; border-radius:0rem; padding:.25rem .5rem; font-size:.82rem; }
  .badge-orders{ background:#00A3AD; color:#fff; }     /* BUMN light */
  .badge-bai{    background:#a7a7a7; color:#fff; }  /* abu */
  .badge-out{    background:#ED1C24; color:#fff; }     /* Telkom red */
  .btn-view{
    --btn: #0ea5a4;
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.35rem .6rem; border:1px solid var(--btn); color:var(--btn);
    background:#fff; border-radius:1rem; font-weight:600; text-decoration:none;
    transition:.15s;
  }
  .btn-view:hover{ background:var(--btn); color:#fff; text-decoration:none; }
  .btn-view .mdi{ font-size:16px; line-height:1; }
</style>

<table id="example" class="table table-striped table-hover w-100">
  <thead>
    <tr>
      <th>No</th>
      <th>Nama</th>
      <th>Order</th>
      <th>Berita Acara Instalasi</th>
      <th>Outstanding</th>
      <th>Aksi</th>
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
      <td>
        <!-- Tombol VIEW: buka modal ringkas & link cepat -->
        <a href="#"
           class="btn-view"
           data-toggle="modal" data-target="#viewTechModal"
           data-name="<?= e($nama) ?>"
           data-user="<?= e($u) ?>"
           data-orders="<?= $o ?>"
           data-bai="<?= $b ?>"
           data-out="<?= $out ?>">
          <i class="mdi mdi-eye"></i> View
        </a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- Modal View -->
<div class="modal fade" id="viewTechModal" tabindex="-1" role="dialog" aria-labelledby="viewTechLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="viewTechLabel">Detail Teknisi</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="outline:none;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pt-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="h5 mb-0" id="vtName">-</div>
            <div class="text-muted" id="vtUser">@-</div>
          </div>
          <div class="text-right">
            <div><span class="badge-kpi badge-orders" id="vtOrders">0</span> <small>Orders</small></div>
            <div><span class="badge-kpi badge-bai" id="vtBai">0</span> <small>BAI</small></div>
            <div><span class="badge-kpi badge-out" id="vtOut">0</span> <small>Outstanding</small></div>
          </div>
        </div>
        <hr>
        <div class="d-flex flex-wrap gap-2">
          <!-- tautan cepat (ubah target file sesuai halamanmu) -->
          <a class="btn btn-sm btn-outline-secondary mr-2" id="vtLinkOrders"   href="#">Lihat Orders</a>
          <a class="btn btn-sm btn-outline-secondary"       id="vtLinkBai"      href="#">Lihat BAI</a>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-light" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
// DataTables (jika plugin sudah dipasang di template-mu)
if (window.jQuery && $.fn.DataTable) {
  $(function(){
    $('#tech-summary').DataTable({
      pageLength: 10,
      order: [[1,'asc']],
      columnDefs: [
        { targets: [2,3,4], className: 'text-center' },
        { targets: 0, width: 40 }
      ]
    });
  });
}

// Modal "View" sederhana (tanpa AJAX, pakai data-* dari tombol)
document.addEventListener('click', function(ev){
  const btn = ev.target.closest('.btn-view');
  if (!btn) return;
  const name = btn.dataset.name || '-';
  const user = btn.dataset.user || '-';
  const o    = btn.dataset.orders || '0';
  const b    = btn.dataset.bai || '0';
  const out  = btn.dataset.out || '0';

  document.getElementById('vtName').textContent = name;
  document.getElementById('vtUser').textContent = '@' + user;
  document.getElementById('vtOrders').textContent = o;
  document.getElementById('vtBai').textContent = b;
  document.getElementById('vtOut').textContent = out;

  // Sesuaikan URL tujuan daftar detail versi kamu:
  document.getElementById('vtLinkOrders').href = 'orders_list.php?sender_username=' + encodeURIComponent(user);
  document.getElementById('vtLinkBai').href    = 'bai_list.php?uploader=' + encodeURIComponent(user);
});
</script>

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