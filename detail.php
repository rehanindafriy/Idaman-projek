<?php
include "header.php";

/* ===================  UTIL & DB  =================== */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

if (!isset($pdo)) { require_once __DIR__ . '/koneksi.php'; }

if (!function_exists('db_col_exists')) {
  function db_col_exists(PDO $pdo, string $table, string $col): bool {
    $st=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $st->execute([$table,$col]); return (bool)$st->fetchColumn();
  }
}
if (!function_exists('pick_col')) {
  function pick_col(PDO $pdo, string $table, array $cands): ?string {
    foreach ($cands as $c) if (db_col_exists($pdo,$table,$c)) return $c; return null;
  }
}

/* Folder file BAI */
$BAI_FS_DIR   = $BAI_FS_DIR   ?? (__DIR__ . '/bot/file/');
$BAI_WEB_BASE = $BAI_WEB_BASE ?? 'bot/file/';

/* Kolom fleksibel */
$ordersId = pick_col($pdo,'orders',['id_order','id']);
$ordersNo = pick_col($pdo,'orders',['no_sc','order_no','no_order','service_number']);
$ordersTs = pick_col($pdo,'orders',['created_at','inserted_at','tgl_create','tgl','tanggal','ts','created']);

$baiId    = pick_col($pdo,'bai',['id_bai','id']);
$baiKode  = pick_col($pdo,'bai',['kode_bai','kode']);
$baiName  = pick_col($pdo,'bai',['nama_bai','filename','file_name','file']);
$baiTs    = pick_col($pdo,'bai',['date_received','uploaded_at','created_at','inserted_at','tgl','tanggal','ts','created']);

if (!$ordersNo || !$baiKode) { die('<div class="p-4">Skema tabel tidak lengkap (no_sc/kode_bai tidak ditemukan).</div>'); }

/* ====== Data untuk opsi multi-select: daftar kode_bai ====== */
/* ====== Data untuk opsi multi-select: daftar no_sc dari ORDERS (termasuk yang belum punya BAI) ====== */
$kodeOptions = [];
try {
  // Ambil seluruh no_sc unik dari orders
  $sqlOpt = "SELECT DISTINCT o.`$ordersNo` AS kode
             FROM `orders` o
             WHERE o.`$ordersNo` IS NOT NULL AND o.`$ordersNo` <> ''
             ORDER BY kode DESC
             LIMIT 5000";
  $q = $pdo->query($sqlOpt);
  $kodeOptions = $q->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  $kodeOptions = [];
}

/* ====== Search handling (multi-select) ====== */
$isSearch   = isset($_POST['search']);
$selected   = isset($_POST['bai_codes']) && is_array($_POST['bai_codes']) ? array_values(array_filter($_POST['bai_codes'])) : [];
$alertType  = '';
$alertMsg   = '';

/* ====== Query utama: orders LEFT JOIN bai (kode_bai = no_sc) ====== */
$selects = [];
$selects[] = $ordersId ? "o.`$ordersId` AS oid" : "NULL AS oid";
$selects[] = "o.`$ordersNo` AS no_sc";
$selects[] = $ordersTs ? "o.`$ordersTs` AS ots" : "NULL AS ots";
$selects[] = $baiId    ? "b.`$baiId` AS bid" : "NULL AS bid";
$selects[] = "b.`$baiKode` AS kode_bai";
$selects[] = $baiName  ? "b.`$baiName` AS nama_bai" : "NULL AS nama_bai";
$selects[] = $baiTs    ? "b.`$baiTs` AS bts" : "NULL AS bts";

$sql = "SELECT ".implode(',', $selects)."
        FROM `orders` o
        LEFT JOIN `bai` b ON b.`$baiKode` = o.`$ordersNo`";

$params = [];
if ($isSearch && $selected) {
  $ph = implode(',', array_fill(0, count($selected), '?'));
  $sql .= " WHERE (o.`$ordersNo` IN ($ph) OR b.`$baiKode` IN ($ph))";
  $params = array_merge($selected, $selected);
  $alertType = 'success';
  $alertMsg  = 'Ditemukan data : ' . implode(', ', array_map(fn($x) => '<strong>'.e($x).'</strong>', $selected));
} elseif ($isSearch) {
  $alertType = 'warning';
  $alertMsg  = 'Tidak ada kode dipilih. Menampilkan semua.';
}

$sql .= " ORDER BY ".($ordersTs ? "o.`$ordersTs` DESC" : "o.`$ordersNo` DESC");

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ====== Pagination sederhana (array slice) ====== */
$totalRows = count($rows);
$PER_PAGE  = 100; // bisa diubah kalau mau

// search dengan kode terpilih → semua hasil di 1 halaman
$isSearchWithCodes = $isSearch && !empty($selected);

if ($isSearchWithCodes) {
  $page       = 1;
  $totalPages = 1;
  $offset     = 0;
  $rowsPage   = $rows;  // tampilkan semua hasil search
} else {
  $page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $totalPages = max(1, (int)ceil(($totalRows ?: 1) / $PER_PAGE));
  if ($page > $totalPages) { $page = $totalPages; }
  $offset   = ($page - 1) * $PER_PAGE;
  $rowsPage = array_slice($rows, $offset, $PER_PAGE);
}
?>
 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

<!-- ======================= VIEW ======================= -->
<div class="main-panel">
  <div class="content-wrapper">

    <!-- Header -->
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Detail</h2>
            <p class="mb-0">Untuk Mencari Berita Acara lebih dari 1 bisa mencari di Form Search dan klik button search</p>
            <p>Jika ingin mencari 1 Berita acara bisa mencari di form search datatables</p>
          </div>

          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo" class="mb-1" style="height:45px; margin-top:-100px; display:block;">
            <br><br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
              <span class="text-primary crumb-mid">Detail</span>
            </div>
          </div>
        </div>
      </div>
    </div>
<?php
require_once __DIR__ . '/koneksi.php';
$orderCount  = (int)($pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0);
$baiCount    = (int)($pdo->query("SELECT COUNT(*) FROM bai")->fetchColumn() ?: 0);
$outstanding = max(0, $orderCount - $baiCount);
?>

<div class="section-cards">
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 mt-0 mb-3">

    <!-- CARD: DATA ORDER -->
    <div class="col mb-3 mb-xl-0">
      <div class="card h-100 card-outline">
        <div class="card-body">
          <article class="stat-card metric-card metric-card--order">
            <div class="metric-top">
              <div class="metric-left">
                <div class="metric-icon">
                  <i class="mdi mdi-file-document-multiple-outline"></i>
                </div>
                <div class="metric-texts">
                  <div class="metric-title">Data Order</div>
                  <div class="metric-subtitle">Total order di sistem</div>
                </div>
              </div>
              <div class="metric-right">
                <div class="metric-value"><?= number_format($orderCount, 0, ',', '.') ?></div>
                <div class="metric-tag">TOTAL</div>
              </div>
            </div>

            <div class="metric-bottom metric-bottom--with-actions">
              <div class="metric-actions">
                <a class="metric-link metric-link--muted btn-download" href="exportorder.php">
                  <i class="mdi mdi-download"></i><span>Export</span>
                </a>
                <span class="metric-dot">·</span>
                <a class="metric-link metric-link--primary btn-detail" href="data.php">
                  Detail
                </a>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- CARD: DATA BAI -->
    <div class="col mb-3 mb-xl-0">
      <div class="card h-100 card-outline">
        <div class="card-body">
          <article class="stat-card metric-card metric-card--bai">
            <div class="metric-top">
              <div class="metric-left">
                <div class="metric-icon">
                  <i class="mdi mdi-file-pdf"></i>
                </div>
                <div class="metric-texts">
                  <div class="metric-title">Data Berita Acara Instalasi</div>
                  <div class="metric-subtitle">BAI yang sudah ter-upload</div>
                </div>
              </div>
              <div class="metric-right">
                <div class="metric-value"><?= number_format($baiCount, 0, ',', '.') ?></div>
                <div class="metric-tag">TOTAL</div>
              </div>
            </div>

            <div class="metric-bottom metric-bottom--with-actions">
              <div class="metric-actions">
                <a class="metric-link metric-link--muted btn-download" href="export_bai.php">
                  <i class="mdi mdi-download"></i><span>Export</span>
                </a>
                <span class="metric-dot">·</span>
                <a class="metric-link metric-link--primary btn-detail" href="bai.php">
                  Detail
                </a>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- CARD: MENU DETAIL (TANPA BUTTON DETAIL) -->
    <div class="col">
      <div class="card h-100 card-outline">
        <div class="card-body">
          <article class="stat-card metric-card metric-card--info">
            <div class="metric-top">
              <div class="metric-left">
                <div class="metric-icon">
                  <i class="mdi mdi-view-dashboard-outline"></i>
                </div>
                <div class="metric-texts">
                  <div class="metric-title">Menu Detail</div>
                  <div class="metric-subtitle">Order tanpa Berita Acara</div>
                </div>
              </div>
              <div class="metric-right">
                <div class="metric-value"><?= number_format($outstanding, 0, ',', '.') ?></div>
                <div class="metric-tag">OUTSTANDING</div>
              </div>
            </div>

            <!-- TIDAK ADA LINK DETAIL DI SINI -->
            <div class="metric-bottom metric-bottom--note">
              <div class="metric-note">Monitoring jumlah order yang belum ada BAI.</div>
            </div>
          </article>
        </div>
      </div>
    </div>

  </div>
</div>
<style>
  :root{
    --metric-border: #e5e7eb;
    --metric-bg:     #ffffff;

    --metric-text-main:  #111827;
    --metric-text-muted: #6b7280;

    --metric-order: #1d4ed8;
    --metric-bai:   #b91c1c;
    --metric-info:  #0f766e;
  }

  /* WRAPPER BARIS KPI */
  .section-cards{
    margin-top: .75rem;
    margin-bottom: 1.5rem;
  }
  .section-cards .row{
    row-gap: 1rem;
  }

  /* CARD LUAR – FLAT, BERSIH */
  .section-cards .card-outline{
    border-radius: 12px;
    border: 1px solid var(--metric-border);
    background: var(--metric-bg);
    box-shadow: 0 1px 3px rgba(15,23,42,.04);
  }
  .section-cards .card-body{
    padding: 14px 20px;
  }

  /* ISI CARD */
  .metric-card{
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 96px;
    gap: 8px;
  }

  .metric-top{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .metric-left{
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
  }

  .metric-icon{
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: #f3f4f6;
    color: #4b5563;
  }

  .metric-texts{
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .metric-title{
    font-size: 14px;
    font-weight: 600;
    color: var(--metric-text-main);
  }
  .metric-subtitle{
    font-size: 12px;
    color: var(--metric-text-muted);
  }

  .metric-right{
    text-align: right;
    white-space: nowrap;
  }
  .metric-value{
    font-size: 22px;
    font-weight: 600;
    letter-spacing: .03em;
  }
  .metric-tag{
    font-size: 11px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--metric-text-muted);
    margin-top: 1px;
  }

  .metric-bottom{
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-top: 4px;
  }

  .metric-bottom--note{
    justify-content: flex-start;
  }

  .metric-note{
    font-size: 11px;
    color: #6b7280;
  }

  /* LINK AKSI KECIL (EXPORT / DETAIL) */
  .metric-actions{
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
  }

  .section-cards .metric-link{
    border-radius: 999px;
    border: 1px solid transparent;
    background: transparent;
    padding: 0 8px;
    height: 24px;
    font-size: 12px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #4b5563;
    text-decoration: none;
    box-shadow: none;
  }
  .section-cards .metric-link i{
    font-size: 14px;
  }
  .section-cards .metric-link:hover{
    background: #f3f4f6;
    border-color: #e5e7eb;
    color: #111827;
  }

  .metric-link--muted{
    color: #6b7280;
  }
  .metric-link--primary{
    color: #111827;
  }
  .metric-link--primary:hover{
    background: #111827;
    border-color: #111827;
    color: #ffffff;
  }

  .metric-dot{
    font-size: 12px;
    color: #9ca3af;
  }

  /* WARNA PER CARD – HANYA ICON & ANGKA */
  .metric-card--order .metric-icon{
    background: #eff6ff;
    color: var(--metric-order);
  }
  .metric-card--order .metric-value{
    color: var(--metric-order);
  }

  .metric-card--bai .metric-icon{
    background: #fef2f2;
    color: var(--metric-bai);
  }
  .metric-card--bai .metric-value{
    color: var(--metric-bai);
  }

  .metric-card--info .metric-icon{
    background: #ecfdf3;
    color: var(--metric-info);
  }
  .metric-card--info .metric-value{
    color: var(--metric-info);
  }

  /* RESPONSIVE */
  @media (max-width: 575.98px){
    .section-cards .card-body{
      padding: 12px 14px;
    }
    .metric-top{
      align-items: flex-start;
    }
    .metric-bottom{
      justify-content: flex-start;
    }
  }
</style>


<style>
  /* Putar ikon mdi-loading */
  .mdi-spin { animation: mdi-spin 1s linear infinite; }
  @keyframes mdi-spin { to { transform: rotate(360deg); } }

  /* Saat loading: cegah klik ganda */
  .btn[disabled] { opacity: .8; pointer-events: none; }

.btn-reset{
    --btn-bg:#e5e7eb;
    --btn-hover:#d1d5db;
    --btn-text:#374151;
    --btn-border:#d1d5db;
    --btn-height:38px;
    background:var(--btn-bg);
    color:var(--btn-text);
    border:1px solid var(--btn-border);
    height:var(--btn-height);
    padding:0 14px;
    border-radius:2px;
    margin-left:6px;
    transition:.15s;
  }
  .btn-reset:hover{
    background:var(--btn-hover);
    border-color:var(--btn-hover);
  }
  
  .card-pager{
  border-radius: 2px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  box-shadow: 0 0px 1px rgba(15,23,42,.04);
  margin-top: 0px;
  margin-bottom: 0px;
}

/* Padding di dalam card dikecilkan */
.card-pager .card-body{
  padding: 6px 16px;
}

/* Isi pager: selalu sejajar kiri-kanan */
.efidi-pager{
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between; /* teks kiri, tombol kanan */
  gap: 4px;
}

/* Tidak perlu center lagi */
.efidi-pager--center{
  max-width: 100%;
  margin: 0;
}

.efidi-pager-summary{
  font-size: 13px;
  color: #4b5563;
  display: flex;
  align-items: center;
}

.efidi-pill-label{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:3px 10px;
  margin-right:8px;
  font-size:11px;
  font-weight:600;
  border-radius:999px;
  background:#e0f2f1;
  color:#0f766e;
  text-transform:uppercase;
  letter-spacing:.08em;
}

.efidi-pager-text strong{
  color:#111827;
}

/* tombol-tombol */
.efidi-pager-buttons{
  display:flex;
  gap:4px;
  flex-wrap:wrap;
  justify-content:flex-end;
}

.efidi-pager-btn{
  border-radius:999px;
  border:1px solid #d1d5db;
  background:#ffffff;
  font-size:12px;
  line-height:1.4;
  color:#374151;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  transition: background .15s, border-color .15s, box-shadow .15s;
}

/* angka halaman */
.efidi-pager-btn--num{
  min-width:28px;
  padding:3px 9px;
}

/* panah kiri/kanan */
.efidi-pager-btn--arrow{
  min-width:26px;
  padding:3px 6px;
}

.efidi-pager-btn:hover{
  background:#f1f5f9;
  border-color:#cbd5e1;
  text-decoration:none;
  box-shadow:0 1px 2px rgba(15,23,42,.08);
}

.efidi-pager-btn.is-active{
  background:#20a1ad;
  border-color:#20a1ad;
  color:#ffffff;
  font-weight:600;
}

.efidi-pager-btn.is-disabled{
  opacity:.45;
  cursor:default;
  pointer-events:none;
  box-shadow:none;
}

.efidi-pager-icon{
  font-size:14px;
  line-height:1;
}

/* Mobile: boleh turun jadi 2 baris tapi tetap rata kiri */
@media (max-width: 576px){
  .efidi-pager{
    flex-direction: column;
    align-items: flex-start;
  }
  .efidi-pager-buttons{
    justify-content: flex-start;
  }
}
/* Container tombol Search + Reset di form filter */
.form-filter-actions{
  white-space: nowrap;      /* jangan bungkus ke baris berikutnya */
}

/* Kalau mau benar-benar rapi, jadikan flex dan sejajarkan ke bawah label */
.form-filter-actions{
  display: flex;
  align-items: flex-end;    /* sejajarkan ke bawah, cocok dengan mt-4 pada tombol */
}

/* Hilangkan efek wrap kalau suatu saat Bootstrap aktifkan flex-wrap */
.form-filter-actions{
  flex-wrap: nowrap;
}

/* Tombol Search di halaman DETAIL – samakan dengan halaman DATA */
#locker-form .btn-search-main{
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 18px;
  border-radius: 2px;

  background-color: #20a1ad;
  border: 1px solid #20a1ad;
  color: #fff;

  /* FONT sama seperti halaman DATA */
  font-family: 'Roboto', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 14px;
  font-weight: 400;
}

#locker-form .btn-search-main:hover{
  background-color: #17818b;
  border-color: #17818b;
}


</style>
<script>
(() => {
  const SELECTOR = '.stat-card .btn-download'; // dua tombol download kamu
  const SPINNER_HTML = '<i class="mdi mdi-loading mdi-spin"></i>';

  function setLoading(btn, on) {
    if (on) {
      if (!btn.dataset._orig) btn.dataset._orig = btn.innerHTML;
      btn.innerHTML = SPINNER_HTML;           // ganti ikon jadi spinner
      btn.setAttribute('aria-busy', 'true');
      btn.setAttribute('disabled', 'disabled');
    } else {
      if (btn.dataset._orig) btn.innerHTML = btn.dataset._orig;
      btn.removeAttribute('aria-busy');
      btn.removeAttribute('disabled');
    }
  }

  async function downloadWithFetch(btn, url) {
    try {
      setLoading(btn, true);

      // Ambil file biner
      const resp = await fetch(url, { credentials: 'same-origin' });
      if (!resp.ok) {
        const msg = await resp.text().catch(() => '');
        alert(msg || ('Gagal download (HTTP ' + resp.status + ')'));
        return;
      }

      // Dapatkan nama file dari header jika ada
      let filename = 'download.xlsx';
      const cd = resp.headers.get('Content-Disposition') || '';
      const m = cd.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
      if (m) filename = decodeURIComponent(m[1].replace(/['"]/g, ''));

      // Jadikan blob → paksa download
      const blob = await resp.blob();
      const urlObj = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = urlObj; a.download = filename;
      document.body.appendChild(a); a.click();
      setTimeout(() => { URL.revokeObjectURL(urlObj); a.remove(); }, 2000);

    } catch (e) {
      console.error(e);
      alert('Gagal download: ' + e.message);
    } finally {
      setLoading(btn, false);
    }
  }

  // Delegasi klik untuk semua tombol download
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest(SELECTOR);
    if (!btn) return;

    ev.preventDefault();                   // jangan navigasi halaman
    if (btn.hasAttribute('disabled')) return;  // cegah double

    const url = btn.getAttribute('href');
    downloadWithFetch(btn, url);
  });
})();
</script>
    <!-- Search Card (tetap sama) -->
     
    <div class="row">
      <div class="col-12 grid-margin stretch-card">
        <div class="card">
          <div class="card-body d-flex align-items-center justify-content-center py4">
            <form class="forms-sample d-flex align-items-center mb-0 mx-auto w-100" method="POST" id="locker-form">
              <div class="form-group flex-grow-1 mb-0">
                <label for="locker">Cari Data (Bisa Mencari Lebih dari satu kode)</label>
                <select class="js-example-basic-multiple flex-grow-1" name="bai_codes[]" id="locker" multiple style="width:100%">
                  <?php foreach ($kodeOptions as $k): ?>
                    <option value="<?= e($k) ?>" <?= in_array($k,$selected,true)?'selected':''; ?>><?= e($k) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group ml-3 mb-0 form-filter-actions">
               <button type="submit"
        name="search"
        class="btn btn-search-main mt-4"
        title="Cari Data">
  Search
</button>

                   <button type="button"
        id="btnResetFilter"
        class="btn px-10 mt-4 btn-reset ml-2"
        title="Reset filter">
  <i class="mdi mdi-refresh" style="font-size:18px;"></i>
</button>
              </div>
            </form>
          </div>

          <?php if ($isSearch && $alertType): ?>
          <div class="card-body pt-0 pb-3">
            <div class="alert alert-<?= $alertType ?> mb-0" style="margin-top:-10px; padding-top:.5rem; padding-bottom:.5rem;">
              <?= $alertMsg ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
   <?php if ($totalRows > 0): ?>
  <?php
    // hitung range data yang tampil di halaman ini
    if ($isSearchWithCodes) {
      // MODE SEARCH: semua hasil ditampilkan di satu halaman
      $from = $totalRows ? 1 : 0;
      $to   = $totalRows;
    } else {
      // MODE NORMAL (pagination biasa)
      $from = $totalRows ? ($offset + 1) : 0;
      $to   = min($offset + $PER_PAGE, $totalRows);
    }

    $maxLinks = 7;
    $start    = max(1, $page - 3);
    $end      = min($totalPages, $start + $maxLinks - 1);

    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);

    $disableFirstPrev = ($page <= 1);
    $disableLastNext  = ($page >= $totalPages);

    // helper tombol
    $makeBtn = function(string $label, int $target, bool $disabled = false, string $extraClass = '') {
      $cls  = 'efidi-pager-btn '.$extraClass.($disabled ? ' is-disabled' : '');
      $href = $disabled ? 'javascript:void(0)' : '?page='.$target;
      return '<a class="'.$cls.'" href="'.$href.'"'
           . ($disabled ? ' aria-disabled="true" tabindex="-1"' : '')
           . '>'.$label.'</a>';
    };
  ?>
  <!-- CARD khusus pager, di antara Search Card & DataTable -->
  <div class="row">
    <div class="col-12 grid-margin">
      <div class="card card-pager">
        <div class="card-body py-2 py-md-3">
          <div class="efidi-pager efidi-pager--center">
            <div class="efidi-pager-summary">
              <span class="efidi-pill-label">Detail Orders &amp; BAI</span>
              <span class="efidi-pager-text">
                Halaman <strong><?= $page ?></strong> dari <?= $totalPages ?>
                <span class="d-none d-sm-inline">
                  · baris <?= number_format($from,0,',','.') ?>–<?= number_format($to,0,',','.') ?>
                </span>
                <span class="d-none d-md-inline">
                  · total <?= number_format($totalRows,0,',','.') ?> data
                </span>
              </span>
            </div>

            <div class="efidi-pager-buttons">
              <?= $makeBtn('<i class="mdi mdi-chevron-double-left efidi-pager-icon"></i>', 1,          $disableFirstPrev, 'efidi-pager-btn--arrow'); ?>
              <?= $makeBtn('<i class="mdi mdi-chevron-left efidi-pager-icon"></i>',        $prevPage,  $disableFirstPrev, 'efidi-pager-btn--arrow'); ?>

              <?php for ($p = $start; $p <= $end; $p++): ?>
                <?= $makeBtn((string)$p, $p, false, 'efidi-pager-btn--num'.($p === $page ? ' is-active' : '')); ?>
              <?php endfor; ?>

              <?= $makeBtn('<i class="mdi mdi-chevron-right efidi-pager-icon"></i>',       $nextPage,  $disableLastNext, 'efidi-pager-btn--arrow'); ?>
              <?= $makeBtn('<i class="mdi mdi-chevron-double-right efidi-pager-icon"></i>', $totalPages, $disableLastNext, 'efidi-pager-btn--arrow'); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>


    <!-- Tabel: layout tetap, tapi kolom sesuai kebutuhan -->
    <div class="row">
      <div class="col-12 stretch-card">
        <div class="card">
          <div class="card-body pb-0 d-flex justify-content-between align-items-center">
            <p class="card-title mb-2">DATA DETAIL</p>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="example" class="display" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Orders</th>
                    <th>Berita Acara Instalasi</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $no=1;
                  foreach ($rowsPage as $r):
                    $no_sc  = (string)($r['no_sc'] ?? '');
                    $nama   = (string)($r['nama_bai'] ?? '');
                    $hasBai = !empty($r['bid']) && $nama !== '';

                    $fileUrl = '';
                    if ($hasBai) {
                      $fs = $BAI_FS_DIR.$nama;
                      if (is_file($fs)) { $fileUrl = $BAI_WEB_BASE . rawurlencode($nama); }
                      else { $hasBai = false; } // file fisik tak ada
                    }
                ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><code><?= e($no_sc) ?></code></td>
             <td>
  <?php if ($hasBai): ?>
    <a href="javascript:void(0)"
       class="file-pill"
       data-file="<?= e($fileUrl) ?>"
       data-title="Berita Acara Instalasi - <?= e($no_sc) ?>"
       onclick="openPdfModal(this)">
      <i class="mdi mdi-file-pdf"></i><span><?= e($nama) ?></span>
    </a>
  <?php else: ?>
    <?php $uid = preg_replace('/[^A-Za-z0-9_-]/','', $no_sc) ?: uniqid('bai_'); ?>
    <form class="upload-mini" method="post" action="upload_bai_for_order.php" enctype="multipart/form-data">
      <input type="hidden" name="no_sc" value="<?= e($no_sc) ?>">
      <input id="file-<?= $uid ?>" class="up-file"
             type="file" name="bai_file"
             accept=".pdf,.doc,.docx,.xls,.xlsx">
      <button type="submit" class="up-confirm d-none" aria-label="Simpan file">✓</button>
    </form>
  <?php endif; ?>
</td>



                    <td>
                      <?php if ($hasBai): ?>
                        <span class="badge rounded-pill text-bg-success d-inline-flex align-items-center gap-1 px-2 py-1">
                          <i class="mdi mdi-check-circle-outline" style="font-size:13px"></i> <span>Done</span>
                        </span>
                      <?php else: ?>
                        <span class="badge rounded-pill text-bg-danger d-inline-flex align-items-center gap-1 px-2 py-1">
                          <i class="mdi mdi-close-circle-outline" style="font-size:13px"></i> <span>Not</span>
                        </span>
                      <?php endif; ?>
                    </td>
                     <td class="text-nowrap">
  <div class="dropdown dropdown-actions">
    <!-- TOMBOL UTAMA (3 TITIK) -->
    <button type="button"
            class="btn btn-sm btn-action-main dropdown-toggle"
            data-toggle="dropdown"
            data-bs-toggle="dropdown"
            aria-haspopup="true"
            aria-expanded="false"
            title="Aksi"
            aria-label="Aksi">
      <i class="mdi mdi-dots-vertical"></i>
    </button>

    <!-- MENU DROPDOWN -->
    <div class="dropdown-menu dropdown-menu-right dropdown-menu-end action-dropdown">
      <!-- HEADER KECIL DI ATAS -->
      <div class="action-dropdown-header">
        <span class="action-title">Aksi</span>
        <span class="action-subtitle">Data Orders</span>
      </div>

      <!-- VIEW -->
       <?php
  // ambil id_order dari alias 'oid'
  $idOrderRow = isset($r['oid']) ? (int)$r['oid'] : 0;
?>
<a class="dropdown-item action-item"
   href="detailing.php?id_order=<?= $idOrderRow; ?>">
  <span class="action-icon action-icon-view">
    <i class="mdi mdi-eye-outline"></i>
  </span>
  <span class="action-text">View</span>
</a>

      <!-- PRINT (GANTI HAPUS) -->
   <a class="dropdown-item action-item"
   href="detail_print.php?id_order=<?= $idOrderRow; ?>"
   target="_blank">
  <span class="action-icon action-icon-print">
    <i class="mdi mdi-printer"></i>
  </span>
  <span class="action-text">Print</span>
</a>

      <!-- DOWNLOAD PDF (GANTI DOWNLOAD EXCEL) -->
      <a class="dropdown-item action-item"
          href="download_pdf.php?id_order=<?= $idOrderRow; ?>">
        <span class="action-icon action-icon-download">
          <!-- ikon PDF Microsoft (lokal) -->
          <img class="pdf-icon-menu"
               src="images/pdff.png"
               alt="PDF">
        </span>
        <span class="action-text">Download PDF</span>
      </a>
    </div>
  </div>
</td>


<style>
/* ikon dasar di dropdown (kalau belum ada) */
.action-icon{
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: #6b7280;
}

/* khusus ikon Print – warna hijau elegan */
.action-icon-print i{
  font-size: 18px;
  color: #0f766e;          /* hijau formal */
}

/* saat hover baris menu, ikon ikut gelap */
.action-item:hover .action-icon-print i{
  color: #064e3b;
}

/* Icon dasar */
/* Icon dasar */
.action-icon{
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: #6b7280;
}

/* LOGO PRINT & PDF (ikon Microsoft lokal) */
.action-icon-print .print-icon-menu,
.action-icon-download .pdf-icon-menu{
  display: block;
  width: 16px;
  height: 16px;
  object-fit: contain;
}

/* warna icon spesifik (optional) */
.action-icon-view{
  color: #182958;
}
.action-icon-print{
  color: inherit;        /* warna dari gambar saja */
}
.action-icon-download{
  color: inherit;        /* warna dari gambar saja */
}

/* ====== WRAPPER TOMBOL AKSI ====== */
.dropdown-actions{
  position: relative;
}

/* Tombol 3 titik: bulat abu-abu seperti reset */
.dropdown-actions .btn-action-main{
  width: 32px;
  height: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;

  border-radius: 999px;
  background: #e5e7eb;
  border: 1px solid #d1d5db;
  color: #374151;
  box-shadow: none;
}
.dropdown-actions .btn-action-main i{
  font-size: 18px;
}
.dropdown-actions .btn-action-main:hover,
.dropdown-actions .btn-action-main:focus{
  background: #d1d5db;
  border-color: #d1d5db;
  color: #111827;
}
.dropdown-actions .btn-action-main::after{
  display: none;
}

/* ====== MENU DROPDOWN ====== */
.action-dropdown{
  min-width: 135px;
  padding: 6px 0 8px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #ffffff;
  box-shadow: 0 18px 40px rgba(15,23,42,.22);
  font-size: 13px;
  transform-origin: top right;
  margin-top: 6px;   /* default: menu di bawah tombol */
}

/* HEADER DI ATAS ITEM */
.action-dropdown-header{
  padding: 6px 14px 6px;
  border-bottom: 1px solid #f3f4f6;
  margin-bottom: 4px;
}
.action-dropdown-header .action-title{
  display: block;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: #6b7280;
}
.action-dropdown-header .action-subtitle{
  display: block;
  font-size: 11px;
  color: #9ca3af;
  margin-top: 1px;
}

/* ITEM DI DALAM MENU */
.action-item{
  display: flex;
  align-items: center;
  padding: 7px 14px;
  gap: 8px;
  color: #374151;
  transition: background .15s, color .15s;
}
.action-item .action-text{
  flex: 1;
  white-space: nowrap;
}
.action-item:hover{
  background: #f3f4f6;
  color: #111827;
  text-decoration: none;
}
.action-item:hover .action-icon{
  color: #111827;
}

/* panah / arrow ke tombol (bawah default) */
.action-dropdown::before{
  content:'';
  position:absolute;
  top:-6px;
  right:18px;
  border-width:0 6px 6px 6px;
  border-style:solid;
  border-color:transparent transparent #ffffff transparent;
}
.action-dropdown::after{
  content:'';
  position:absolute;
  top:-7px;
  right:17px;
  border-width:0 7px 7px 7px;
  border-style:solid;
  border-color:transparent transparent rgba(148,163,184,.45) transparent;
}

/* kalau menu muncul di ATAS tombol → panah pindah ke bawah */
.action-dropdown[data-popper-placement^="top"],
.action-dropdown[x-placement^="top"]{
  margin-top:0;
  margin-bottom:6px;
}
.action-dropdown[data-popper-placement^="top"]::before,
.action-dropdown[x-placement^="top"]::before{
  top:auto;
  bottom:-6px;
  right:18px;
  border-width:6px 6px 0 6px;
  border-color:#ffffff transparent transparent transparent;
}
.action-dropdown[data-popper-placement^="top"]::after,
.action-dropdown[x-placement^="top"]::after{
  top:auto;
  bottom:-7px;
  right:17px;
  border-width:7px 7px 0 7px;
  border-color:rgba(148,163,184,.45) transparent transparent transparent;
}

</style>

                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

</div><!-- /.main-panel -->

<!-- ====== Styles + Tom Select (biar multi-select tetap ada) ====== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<style>
/* ===============================
   DETAIL TABLE – MINIMAL CORPORATE
   Berlaku hanya untuk tabel #example
   =============================== */

#example tbody td{
  vertical-align: middle;
  font-size: 13px;
}

/* ---- ORDERS (kolom ke-2) ---- */
/* Hilangkan background default <code> dan jadikan teks monospaced polos */
#example td:nth-child(2) code{
  background: transparent;
  padding: 0;
  border-radius: 0;
  border: 0;
  color: #111827;
  font-size: 13px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

/* ---- STATUS (badge Done / Not) ---- */
/* Ubah badge jadi text ringan tanpa background, border, atau pill */
#example .badge.text-bg-success,
#example .badge.text-bg-danger{
  background: transparent !important;
  border: 0 !important;
  box-shadow: none !important;
  border-radius: 0 !important;
  padding: 0 !important;

  display: inline-flex;
  align-items: center;
  gap: .3rem;

  font-size: 13px;
  font-weight: 500;
}

#example .badge.text-bg-success{
  color: #15803d !important;   /* hijau */
}
#example .badge.text-bg-danger{
  color: #b91c1c !important;   /* merah */
}

#example .badge.text-bg-success i,
#example .badge.text-bg-danger i{
  font-size: 14px !important;
}

/* ---- BERITA ACARA (file-pill) ---- */
/* Hanya icon + nama file (truncate), tanpa pill/background/border */
#example .file-pill{
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: 0;
  margin: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  box-shadow: none;

  text-decoration: none;
  color: #111827;
  font-size: 13px;
}

#example .file-pill i{
  font-size: 17px;
  color: #dc2626; /* merah PDF */
}

#example .file-pill span{
  max-width: 420px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

#example .file-pill:hover{
  text-decoration: underline;
  background: transparent;
}

/* ---- Upload BAI (ketika belum ada file) ---- */
/* Form kecil, inline dan sangat minimal */
#example .upload-mini{
  display: inline-flex;
  align-items: center;
  gap: .4rem;
}

/* input file biarkan standar browser (tanpa styling aneh) */
#example .up-file{
  font-size: 12px;
}

/* Tombol konfirmasi: hanya text "Simpan", tidak ada kotak, border, atau shadow */
#example .up-confirm{
  border: 0;
  background: transparent;
  box-shadow: none;
  padding: 0;
  margin: 0;
  font-size: 0;           /* sembunyikan karakter '✓' */
  cursor: pointer;
}

#example .up-confirm::after{
  content: 'Simpan';
  font-size: 13px;
  color: #0f766e;
}

#example .up-confirm:hover::after{
  text-decoration: underline;
}

/* --- Tom Select (biarkan seperti sebelumnya, hanya aku ringkas) --- */
.ts-control {
  min-height: calc(2.25rem + 2px);
  padding: .375rem .5rem;
}
.ts-dropdown{
  padding: .25rem;
}
.ts-dropdown .option{
  padding: .55rem .80rem;
  line-height: 1.15;
  border-radius: .35rem;
}
.ts-dropdown .option + .option{
  margin-top: .22rem;
}
.ts-dropdown .active{
  background: #eef6ff;
  color: #0b5ed7;
}

/* form upload inline lain (kalau ada) */
.upload-inline .btn{
  line-height: 1.2;
}
</style>

<script>
document.addEventListener('change', function(e){
  const inp = e.target.closest('.up-file');
  if (!inp) return;
  const form = inp.closest('.upload-mini');
  const ok   = form.querySelector('.up-confirm');
  ok.classList.toggle('d-none', !(inp.files && inp.files.length));
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // SIMPAN instance ke variabel tsLocker
  const tsLocker = new TomSelect('#locker', {
    plugins: ['remove_button'],
    create: false,
    persist: false,
    maxOptions: 1000,
    placeholder: 'Ketik kode BAI lalu pilih…',
    closeOnSelect: false
  });
  
  const btnReset = document.getElementById('btnResetFilter');
  if (btnReset) {
    btnReset.addEventListener('click', function () {
      // bersihkan pilihan di TomSelect
      tsLocker.clear();          // hapus semua selected item

      // reset form search (kalau ada value lain)
      const form = document.getElementById('locker-form');
      if (form) form.reset();

      // reload sebagai GET tanpa POST -> balik ke halaman awal & tanpa filter
      window.location.href = window.location.pathname;
    });
  }

  // autosubmit saat pilih file (kalau masih dipakai di tempat lain)
  document.querySelectorAll('.upload-inline input[type=file]').forEach(function(inp){
    inp.addEventListener('change', function(){
      if (this.files && this.files.length) {
        this.form.submit();
      }
    });
  });
});
</script>
<!-- ================= MODAL PDF CUSTOM ================= -->
<div id="pdfModalCustom" class="pdf-modal" aria-hidden="true">
  <div class="pdf-modal-dialog">
    <div class="pdf-modal-header">
      <h5 id="pdfModalTitleCustom" class="pdf-modal-title">
        Berita Acara Instalasi
      </h5>
      <button type="button"
              class="pdf-modal-close"
              onclick="closePdfModal()"
              aria-label="Tutup">
        &times;
      </button>
    </div>
    <div class="pdf-modal-body">
      <iframe id="pdfFrameCustom"
              src=""
              frameborder="0"></iframe>
    </div>
  </div>
</div>

<style>
  /* Overlay penuh layar */
  .pdf-modal{
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.55);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px 12px;          /* ada jarak tipis dari tepi layar */
  }
  .pdf-modal.show{
    display: flex;
  }

  /* Kotak putih di tengah */
  .pdf-modal-dialog{
    width: min(1100px, 100%);
    height: 90vh;                /* tingginya hampir 1 layar */
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 20px 40px rgba(15,23,42,.35);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .pdf-modal-header{
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-bottom: 1px solid #e5e7eb;
  }
  .pdf-modal-title{
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #111827;
  }
  .pdf-modal-close{
    border: none;
    background: transparent;
    font-size: 22px;
    line-height: 1;
    padding: 0 6px;
    cursor: pointer;
    color: #4b5563;
  }
  .pdf-modal-close:hover{
    color: #111827;
  }

  .pdf-modal-body{
    flex: 1 1 auto;
    min-height: 0;      /* supaya iframe bisa pakai tinggi penuh */
    padding: 0;
  }
  .pdf-modal-body iframe{
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
  }
</style>

<script>
  // Buka modal dengan PDF
  function openPdfModal(el){
    var url   = el.getAttribute('data-file');
    if (!url) return;

    var title = el.getAttribute('data-title') || 'Berita Acara Instalasi';

    var overlay = document.getElementById('pdfModalCustom');
    var frame   = document.getElementById('pdfFrameCustom');
    var titleEl = document.getElementById('pdfModalTitleCustom');

    if (frame){
      // kecilkan tampilan PDF: minta viewer pakai "page-fit"
      var viewerUrl = url;
      if (viewerUrl.indexOf('#') === -1){
        viewerUrl += '#zoom=page-fit';
      } else {
        viewerUrl += '&zoom=page-fit';
      }
      frame.src = viewerUrl;
    }
    if (titleEl) {
      titleEl.textContent = title;
    }

    if (overlay){
      overlay.classList.add('show');
    }
  }

  // Tutup modal
  function closePdfModal(){
    var overlay = document.getElementById('pdfModalCustom');
    var frame   = document.getElementById('pdfFrameCustom');
    if (overlay){
      overlay.classList.remove('show');
    }
    if (frame){
      frame.src = ''; // hentikan load PDF
    }
  }

  // Klik area gelap = tutup
  document.addEventListener('click', function(e){
    var overlay = document.getElementById('pdfModalCustom');
    if (!overlay || !overlay.classList.contains('show')) return;
    if (e.target === overlay){
      closePdfModal();
    }
  });

  // ESC = tutup
  document.addEventListener('keyup', function(e){
    if (e.key === 'Escape'){
      closePdfModal();
    }
  });
</script>


<?php include "footer.php"; ?>
