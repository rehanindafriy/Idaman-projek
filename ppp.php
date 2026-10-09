<?php
// orders_tech.php — Data Orders milik teknisi yang sedang login
include "header.php";
require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* =========================
   Ambil identitas login
   ========================= */

// header.php sudah session_start()

// Ambil dari session dengan beberapa alias umum
$meChat    = $_SESSION['chat_id']      ?? $_SESSION['user_chat_id'] ?? null;
$meRole    = $_SESSION['role']         ?? $_SESSION['user_role']    ?? null;
$meIdAkses = $_SESSION['id_akses']     ?? $_SESSION['user_id']      ?? null; // kadang dipakai utk id_akses

// Fallback: kalau chat_id/role belum ada, ambil dari DB via id_akses
if ((!$meChat || !$meRole) && $meIdAkses) {
  $st = $pdo->prepare("SELECT chat_id, role FROM akses_login WHERE id_akses = ? LIMIT 1");
  $st->execute([$meIdAkses]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $meChat = $meChat ?: ($row['chat_id'] ?? null);
    $meRole = $meRole ?: ($row['role'] ?? null);
    // simpan balik ke session biar next request sudah ada
    $_SESSION['chat_id'] = $meChat;
    $_SESSION['role']    = $meRole;
  }
}

// Validasi role & chat_id
if (strtolower((string)$meRole) !== 'tech') {
  http_response_code(403);
  echo '<div class="container mt-4"><div class="alert alert-warning">Halaman ini khusus untuk <b>Tech</b>.</div></div>';
  include "footer.php"; exit;
}
if (!$meChat || !preg_match('/^-?\d{10,}$/', (string)$meChat)) {
  echo '<div class="container mt-4"><div class="alert alert-danger">chat_id Anda belum valid/terhubung. Hubungi admin.</div></div>';
  include "footer.php"; exit;
}


/* =========================
   Ambil parameter filter
   ========================= */
$mode   = $_GET['mode']  ?? '';
$dateV  = $_GET['date']  ?? '';
$startV = $_GET['start'] ?? '';
$endV   = $_GET['end']   ?? '';
$monthCombined = $_GET['month'] ?? ''; // 'YYYY-MM'
$yearV  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$monthY = ''; $monthM = '';
if (preg_match('/^(\d{4})-(\d{2})$/', $monthCombined, $m)) { $monthY = $m[1]; $monthM = $m[2]; }

$baseURL = htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8');
?>
<style>
/* header judul + tombol */
.tech-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.tech-header .card-title{margin:0}

/* filter bar (ringkas) */
.filter-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin:12px 0}
.filter-bar .form-select,.filter-bar .form-control{height:38px;min-height:38px;line-height:24px;border-radius:2px}
.filter-bar .btn-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:2px}
.btn-icon.btn-search{background:#20a1ad;border:1px solid #20a1ad;color:#fff}
.btn-icon.btn-search:hover{background:#17818b;border-color:#17818b}
.btn-icon.btn-clear{background:#e5e7eb;border:1px solid #d1d5db;color:#111}
.table-tools{display:flex;gap:8px;margin-bottom:8px}
.btn-excel-wide{display:inline-flex;align-items:center;height:38px;padding:0 12px;border:2px solid #d1d5db;border-radius:2px;color:#6b7280;filter:grayscale(1) opacity(.65);cursor:not-allowed}
.btn-excel-wide.active{border-color:#16a34a;color:#14532d;filter:none;cursor:pointer}
.btn-excel-wide.active:hover{border-color:#14843f}

/* util salin */
td .copy-wrap{position:relative;display:block;max-width:100%;padding-right:26px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
td .copy-wrap .copy-text{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;vertical-align:top}
td .copy-wrap .copy-icon{position:absolute;top:0;right:4px;width:16px;height:16px;line-height:16px;font-size:12px;color:#616264;opacity:.8;cursor:pointer}
td .copy-wrap:hover .copy-icon{opacity:.95}
</style>

<div class="main-panel">
  <div class="content-wrapper">
    <div class="row"><div class="col-md-12 grid-margin">
      <div class="tech-header">
        <h4 class="card-title">Orders Saya (Tech)</h4>
        <small class="text-muted">chat_id: <code><?= e((string)$meChat) ?></code></small>
      </div>
    </div></div>

    <div class="row">
      <div class="col-md-12 stretch-card">
        <div class="card">
          <div class="card-body">

            <!-- ===== FILTER ===== -->
            <form id="filterForm" class="filter-bar" method="get" action="">
              <div>
                <label class="form-label">Filter Pencarian</label>
                <select name="mode" id="mode" class="form-select form-select-sm" style="min-width:200px">
                  <option value=""        <?= $mode===''        ? 'selected' : '' ?>>Pilih Filter Pencarian</option>
                  <option value="single"  <?= $mode==='single'  ? 'selected' : '' ?>>Berdasarkan Tanggal</option>
                  <option value="range"   <?= $mode==='range'   ? 'selected' : '' ?>>Rentang Tanggal</option>
                  <option value="month"   <?= $mode==='month'   ? 'selected' : '' ?>>Bulan</option>
                  <option value="year"    <?= $mode==='year'    ? 'selected' : '' ?>>Tahun</option>
                </select>
              </div>

              <div id="fields-single" class="mode-fields" style="display:none">
                <label class="form-label">Tanggal</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= e($dateV) ?>">
              </div>

              <div id="fields-range" class="mode-fields" style="display:none;gap:8px;align-items:end">
                <div>
                  <label class="form-label">Mulai</label>
                  <input type="date" name="start" class="form-control form-control-sm" value="<?= e($startV) ?>">
                </div>
                <div>
                  <label class="form-label">Selesai</label>
                  <input type="date" name="end" class="form-control form-control-sm" value="<?= e($endV) ?>">
                </div>
              </div>

              <div id="fields-month" class="mode-fields" style="display:none;gap:8px;align-items:end">
                <?php
                $bulanNama=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                $curM = $monthM ?: date('m');
                ?>
                <div>
                  <label class="form-label">Bulan</label>
                  <select id="month_m" class="form-select form-select-sm">
                    <?php foreach ($bulanNama as $num=>$nama): $val=str_pad((string)$num,2,'0',STR_PAD_LEFT);?>
                      <option value="<?= $val ?>" <?= ($curM===$val?'selected':'') ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="form-label">Tahun</label>
                  <input id="month_y" type="number" min="2000" max="2100" step="1"
                         class="form-control form-control-sm"
                         value="<?= e($monthY ?: date('Y')) ?>">
                </div>
              </div>
              <input type="hidden" name="month" id="hidden-month" value="<?= e($monthCombined) ?>">

              <div id="fields-year" class="mode-fields" style="display:none">
                <label class="form-label">Tahun</label>
                <input type="number" min="2000" max="2100" step="1" name="year"
                       class="form-control form-control-sm" style="width:120px"
                       value="<?= e((string)$yearV) ?>">
              </div>

              <input type="hidden" name="searched" id="searched" value="<?= isset($_GET['searched']) ? (int)$_GET['searched'] : 0 ?>">
              <!-- bawa uid untuk export aman -->
              <input type="hidden" name="uid" value="<?= e((string)$meChat) ?>">

              <button id="btnSearch" type="submit" class="btn btn-icon btn-search" title="Cari" aria-label="Cari">
                <i class="mdi mdi-magnify" style="font-size:18px"></i>
              </button>
              <button id="btnClear" type="button" class="btn btn-icon btn-clear" title="Hapus Filter" aria-label="Hapus Filter">
                <i class="mdi mdi-close" style="font-size:18px"></i>
              </button>
            </form>

            <!-- Tools kanan: Excel -->
            <div class="table-tools">
              <button id="btnExcel"
                      class="btn btn-excel-wide"
                      title="Download Excel"
                      aria-label="Download Excel"
                      form="filterForm"
                      formaction="export_orders_tech.php"
                      formmethod="get"
                      disabled>
                <img src="https://upload.wikimedia.org/wikipedia/commons/e/e3/Microsoft_Office_Excel_%282019%E2%80%932025%29.svg"
                     alt="Excel" style="width:18px;height:18px;margin-right:8px">
                <span>Download Excel</span>
              </button>
            </div>

<?php
/* =========================
   Build WHERE + Query
   ========================= */
$where  = ["user_id = :uid"];       // <- kunci utama: hanya milik teknisi ini
$params = [':uid' => (string)$meChat];

switch ($mode) {
  case 'single':
    if ($dateV) { $where[]="created_at BETWEEN :ds AND :de"; $params[':ds']=$dateV." 00:00:00"; $params[':de']=$dateV." 23:59:59"; }
    break;
  case 'range':
    if ($startV && $endV)       { $where[]="created_at BETWEEN :rs AND :re"; $params[':rs']=$startV." 00:00:00"; $params[':re']=$endV." 23:59:59"; }
    elseif ($startV)            { $where[]="created_at >= :rs";              $params[':rs']=$startV." 00:00:00"; }
    elseif ($endV)              { $where[]="created_at <= :re";              $params[':re']=$endV." 23:59:59"; }
    break;
  case 'month':
    if ($monthCombined && preg_match('/^\d{4}-\d{2}$/', $monthCombined)) {
      [$y,$m] = explode('-', $monthCombined);
      $startMonth = sprintf('%04d-%02d-01 00:00:00', $y, $m);
      $endMonth   = date('Y-m-t 23:59:59', strtotime("$y-$m-01"));
      $where[]       = "created_at BETWEEN :ms AND :me";
      $params[':ms'] = $startMonth;
      $params[':me'] = $endMonth;
    }
    break;
  case 'year':
    if ($yearV) { $where[]="created_at BETWEEN :ys AND :ye"; $params[':ys']=sprintf('%04d-01-01 00:00:00',$yearV); $params[':ye']=sprintf('%04d-12-31 23:59:59',$yearV); }
    break;
}

$sql = "SELECT id_order, order_type, no_sc, no_internet, cp_pelanggan, nama_pelanggan, created_at
        FROM orders
        WHERE ".implode(' AND ', $where)."
        ORDER BY id_order DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>

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
                $no=1;
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
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

                    <td><?= e($data['nama_pelanggan']) ?></td>

                    <td>
                      <span class="copy-wrap" data-copy="<?= e($data['cp_pelanggan']) ?>">
                        <span class="copy-text"><?= e($data['cp_pelanggan']) ?></span>
                        <i class="mdi mdi-content-copy copy-icon" title="Salin"></i>
                      </span>
                    </td>

                    <td><?= date('d M Y', strtotime($data['created_at'])) ?></td>

                    <td class="text-nowrap">
                      <a href="view_data.php?id_order=<?= (int)$data['id_order'] ?>" class="btn btn-sm btn-primary" title="Lihat"><i class="mdi mdi-eye"></i></a>
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
</div>

<script>
// tampil/semmbunyikan field filter
(function(){
  const form      = document.getElementById('filterForm');
  const modeEl    = document.getElementById('mode');
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
    if(!v) return;
    blocks[v].style.display = (v==='range'||v==='month')?'flex':'block';
    blocks[v].querySelectorAll('input,select').forEach(i=>i.disabled=false);
  }
  form.addEventListener('submit', function(e){
    const sub = e.submitter;
    const hiddenMonth = document.getElementById('hidden-month');
    if (modeEl.value === 'month') {
      const mm = String(document.getElementById('month_m').value||'').padStart(2,'0');
      const yy = document.getElementById('month_y').value;
      hiddenMonth.value = (yy && mm)? `${yy}-${mm}` : '';
    } else hiddenMonth.value = '';
    if (sub && sub.id === 'btnSearch') searched.value = '1';
  });
  const urlParams = new URLSearchParams(location.search);
  const searchedOnce = urlParams.get('searched') === '1';
  btnExcel.disabled = !searchedOnce;
  btnExcel.classList.toggle('active', searchedOnce);
  document.getElementById('btnClear').addEventListener('click', ()=>{
    form.reset(); searched.value='0'; modeEl.value=''; hideAll();
    btnExcel.disabled=true; btnExcel.classList.remove('active');
    window.location = baseURL;
  });
  modeEl.addEventListener('change', refresh);
  refresh();

  // salin cepat
  document.addEventListener('click', function(e){
    const el = e.target.closest('.copy-wrap'); if(!el) return;
    const val = el.dataset.copy || '';
    if (!val) return;
    navigator.clipboard?.writeText(val);
  });
})();
</script>

<?php include "footer.php"; ?>
