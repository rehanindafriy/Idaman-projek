   <?php
  
   require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('short4')) {
    function short4(?string $s): string {
        $s = (string)$s;
        $len = strlen($s);

        if ($len <= 24) {
            return $s !== '' ? $s : '—';
        }

        return substr($s, 0, 12) . '…' . substr($s, -12);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_order'], $_POST['id_order'])) {

  $idOrder = (int)$_POST['id_order'];

  if ($idOrder > 0) {
    try {
      $stDel = $pdo->prepare("DELETE FROM `orders` WHERE `id_order` = :id");
      $stDel->execute([':id' => $idOrder]);
      // kalau mau, bisa kasih flash message di session di sini
    } catch (Throwable $ex) {
      // jangan matikan halaman, cukup diam / log
      // file_put_contents('runtime-delete.log', date('c').' '.$ex->getMessage()."\n", FILE_APPEND);
    }
  }

  // redirect supaya tidak ada resubmit form saat refresh
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '#'));
  exit;
}

/**
 * BACA STATE FILTER MULTI-SELECT ORDERS
 * - order_field  : kolom yang difilter (no_sc/no_internet/cp_pelanggan/nama_pelanggan)
 * - order_codes[]: nilai-nilai yang dipilih
 */
$orderField = $_POST['order_field'] ?? ($_GET['order_field'] ?? '');
$orderCodes = isset($_POST['order_codes']) && is_array($_POST['order_codes'])
  ? array_values(array_filter($_POST['order_codes']))
  : [];

/**
 * SIAPKAN OPSI DISTINCT PER KOLOM
 */
$orderOptionsByField = [
  'no_sc'          => [],
  'no_internet'    => [],
  'cp_pelanggan'   => [],
  'nama_pelanggan' => [],
];

try {
  foreach (array_keys($orderOptionsByField) as $col) {
    $sqlOpt = "
      SELECT DISTINCT `$col` AS val
      FROM `orders`
      WHERE `$col` IS NOT NULL AND `$col` <> ''
      ORDER BY val DESC
     
    ";
    $stOpt = $pdo->query($sqlOpt);
    $orderOptionsByField[$col] = $stOpt->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }
} catch (Throwable $e) {
  // kalau error, biarkan kosong saja
}

/** GABUNGAN SEMUA NILAI (untuk mode 'Semua Kolom') */
$orderOptionsAll = array_values(array_unique(array_merge(
  $orderOptionsByField['no_sc'],
  $orderOptionsByField['no_internet'],
  $orderOptionsByField['cp_pelanggan'],
  $orderOptionsByField['nama_pelanggan']
)));

/* ================= FILTER TANGGAL (mode single/range/month/year) ================= */

// Ambil nilai GET supaya form mengingat pilihan & dipakai filter
$mode   = $_GET['mode']  ?? '';
$dateV  = $_GET['date']  ?? '';
$startV = $_GET['start'] ?? '';
$endV   = $_GET['end']   ?? '';
$monthCombined = $_GET['month'] ?? ''; // 'YYYY-MM'
$yearV  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$monthY = ''; 
$monthM = '';
if (preg_match('/^(\d{4})-(\d{2})$/', $monthCombined, $m)) {
  $monthY = $m[1];
  $monthM = $m[2];
}

$where  = [];
$params = [];

// Filter berdasarkan mode
switch ($mode) {
  case 'single':
    if ($dateV) {
      $where[]       = "created_at BETWEEN :ds AND :de";
      $params[':ds'] = $dateV . " 00:00:00";
      $params[':de'] = $dateV . " 23:59:59";
    }
    break;

  case 'range':
    if ($startV && $endV) {
      $where[]       = "created_at BETWEEN :rs AND :re";
      $params[':rs'] = $startV . " 00:00:00";
      $params[':re'] = $endV   . " 23:59:59";
    } elseif ($startV) {
      $where[]       = "created_at >= :rs";
      $params[':rs'] = $startV . " 00:00:00";
    } elseif ($endV) {
      $where[]       = "created_at <= :re";
      $params[':re'] = $endV . " 23:59:59";
    }
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
    if ($yearV) {
      $where[]       = "created_at BETWEEN :ys AND :ye";
      $params[':ys'] = sprintf('%04d-01-01 00:00:00', $yearV);
      $params[':ye'] = sprintf('%04d-12-31 23:59:59', $yearV);
    }
    break;
}

/* ================= FILTER MULTI-SELECT ORDERS ================= */

/* ================= FILTER MULTI-SELECT ORDERS ================= */

$allowedFields = [
  'no_sc'          => 'no_sc',
  'no_internet'    => 'no_internet',
  'cp_pelanggan'   => 'cp_pelanggan',
  'nama_pelanggan' => 'nama_pelanggan',
];

if (!empty($orderCodes)) {
  // kalau pilih kolom tertentu -> filter di kolom itu saja pakai parameter binding
  if ($orderField && isset($allowedFields[$orderField])) {
    $col = $allowedFields[$orderField];

    $phs = [];
    foreach ($orderCodes as $idx => $val) {
      $key = ":oc{$idx}";
      $phs[] = $key;
      $params[$key] = $val;
    }
    if ($phs) {
      $where[] = "`$col` IN (" . implode(',', $phs) . ")";
    }

  } else {
    // KOLUM = "Semua" -> cari di 4 kolom sekaligus
    // Di sini TIDAK pakai placeholder yang diulang (bisa bikin error),
    // tapi pakai $pdo->quote() supaya tetap aman.
    $vals = [];
    foreach ($orderCodes as $val) {
      $val = (string)$val;
      if ($val === '') continue;
      $vals[] = $pdo->quote($val);
    }
    $vals = array_values(array_unique($vals));

    if ($vals) {
      $in = implode(',', $vals);
      $where[] =
        "(no_sc IN ($in)
          OR no_internet IN ($in)
          OR cp_pelanggan IN ($in)
          OR nama_pelanggan IN ($in))";
    }
  }
}

/* ================= PAGINATION: 100 BARIS / PAGE ================= */

$PER_PAGE   = 100;
$totalRows  = 0;
$totalPages = 0;
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset     = 0;

// hitung total rows sesuai filter
$countSql = "SELECT COUNT(*) FROM orders";
if ($where) {
  $countSql .= " WHERE " . implode(' AND ', $where);
}
$stCount = $pdo->prepare($countSql);
$stCount->execute($params);
$totalRows = (int)($stCount->fetchColumn() ?: 0);

if (!empty($orderCodes)) {
  // MODE SEARCH (multi-select) -> tampilkan semua hasil di 1 halaman
  $totalPages = $totalRows > 0 ? 1 : 0;
  $page       = $totalPages ? 1 : 0;
  $offset     = 0;
} else {
  // MODE NORMAL -> pagination
  $totalPages = $totalRows > 0 ? (int)ceil($totalRows / $PER_PAGE) : 0;
  if ($totalPages < 1) {
    $page = 0;
    $offset = 0;
  } else {
    if ($page > $totalPages) $page = $totalPages;
    if ($page < 1)           $page = 1;
    $offset = ($page - 1) * $PER_PAGE;
  }
}

/* ================= SQL UTAMA LIST DATA (dipakai tabel) ================= */
$sqlOrders = "
  SELECT id_order, order_type, no_sc, no_internet,
         cp_pelanggan, nama_pelanggan, created_at
  FROM orders
";
if ($where) {
  $sqlOrders .= " WHERE " . implode(' AND ', $where);
}
$sqlOrders .= " ORDER BY id_order DESC";

// pagination hanya untuk mode normal (tanpa multi-select)
if (empty($orderCodes) && $totalRows > 0) {
  $limit  = (int)$PER_PAGE;
  $off    = max(0, (int)$offset);
  $sqlOrders .= " LIMIT {$limit} OFFSET {$off}";
}

// statement untuk tabel
$stList = $pdo->prepare($sqlOrders);
$stList->execute($params);

$multiSearchAlert = null;

if (!empty($orderCodes)) {
  // bersihkan & unikkan kode
  $codesClean = array_values(array_unique(array_filter(array_map('trim', $orderCodes))));
  if ($codesClean) {
    $multiSearchAlert = [
      'success' => ($totalRows > 0),
      'count'   => $totalRows,
      'codes'   => $codesClean,
    ];
  }
}
 include "header.php";
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
           <!-- Search Card -->
   <!-- Search Card: Multi-select Orders -->
<div class="row">
  <div class="col-12 grid-margin stretch-card">
    <div class="card">
      <div class="card-body d-flex align-items-center justify-content-center py4">
          <div class="w-100">
        <form class="forms-sample mb-0 mx-auto w-100"
              method="POST" id="locker-form">
          <div class="form-group flex-grow-1 mb-0">
            <label class="mb-1">Filter Orders (Multi Select)</label>
            <small class="text-muted d-block mb-2">
              Pilih kolom terlebih dahulu, lalu pilih satu atau lebih nilai.
              Jika kolom dikosongkan, pencarian akan dilakukan di <strong>No SC, No Internet, CP, dan Nama</strong>.
            </small>

            <div class="order-multi-bar d-flex flex-wrap align-items-end">
              <!-- PILIH KOLUMNYA -->
              <div class="order-multi-field">
                <label for="orderField" class="form-label mb-1">Kolom</label>
                <select id="orderField" name="order_field"
                        class="form-select form-select-sm">
                  <option value=""
                    <?= $orderField==='' ? 'selected' : '' ?>>
                    Semua 
                  </option>
                  <option value="no_sc"
                    <?= $orderField==='no_sc' ? 'selected' : '' ?>>
                    No SC
                  </option>
                  <option value="no_internet"
                    <?= $orderField==='no_internet' ? 'selected' : '' ?>>
                    No Internet
                  </option>
                  <option value="cp_pelanggan"
                    <?= $orderField==='cp_pelanggan' ? 'selected' : '' ?>>
                    Contact Person
                  </option>
                  <option value="nama_pelanggan"
                    <?= $orderField==='nama_pelanggan' ? 'selected' : '' ?>>
                    Customer Name
                  </option>
                </select>
              </div>

              <!-- MULTI-SELECT NILAI -->
              <div class="order-multi-select flex-grow-1">
                <label for="locker" class="form-label mb-1 d-flex justify-content-between">
                  <span>Nilai (bisa pilih lebih dari satu)</span>
                </label>
                <select id="locker"
                        name="order_codes[]"
                        multiple
                        style="width:100%">
                  <!-- opsi akan diisi via TomSelect + JS -->
                </select>
              </div>
            </div>
          </div>

        <div class="form-group mb-0 form-filter-actions">
  <button type="submit"
          name="search"
          class="btn btn-search-main"
          title="Cari Orders">
    Search
  </button>

  <button type="button"
          id="btnResetFilter"
          class="btn btn-reset-main"
          title="Reset filter">
    <i class="mdi mdi-refresh" style="font-size:18px;"></i>
  </button>
</div>

        </form>
         <?php if ($multiSearchAlert): ?>
                  <div class="alert alert-<?= $multiSearchAlert['success'] ? 'success' : 'warning' ?>
                              efidi-search-alert mt-3 mb-0">
                    <?php if ($multiSearchAlert['success']): ?>
                      Ditemukan
                      <strong><?= number_format($multiSearchAlert['count'], 0, ',', '.') ?></strong>
                      data untuk kode:
                    <?php else: ?>
                      <strong>Tidak ditemukan data</strong> untuk kode:
                    <?php endif; ?>
      
                    <strong>
                      <?php
                        $codesEsc = array_map('e', $multiSearchAlert['codes']);
                        echo implode(', ', $codesEsc);
                      ?>
                    </strong>
                  </div>
                <?php endif; ?>
      </div>
      </div>

      <!-- (Kalau mau alert hasil search khusus multi-select di sini, bisa ditambah nanti) -->
    </div>
  </div>
</div>
<style>
/* === LAYOUT FORM MULTI SELECT ORDERS (RAPI & LURUS) === */
#locker-form{
  /* kiri: field, kanan: tombol */
  display:grid;
  grid-template-columns: minmax(0,1fr) auto;
  column-gap:16px;
  align-items:end;           /* garis bawah semua sejajar */
}

/* grup kiri (judul + kolom + nilai) */
#locker-form .form-group.flex-grow-1{
  min-width:0;
  margin-bottom:0;
}

/* grup kanan (tombol) */
#locker-form .form-filter-actions{
  display:flex;
  align-items:flex-end;
  justify-content:flex-end;
  gap:8px;
}

/* ===== BARIS: Kolom + Nilai ===== */
.order-multi-bar{
  display:grid;
  grid-template-columns: 180px minmax(0,1fr);  /* Kolom fix, Nilai fleksibel */
  column-gap:10px;
  row-gap:0;
  align-items:end;
}

.order-multi-field,
.order-multi-select{
  min-width:0;
}

.order-multi-field .form-label,
.order-multi-select .form-label{
  margin-bottom:4px;          /* biar tulisan Kolom & Nilai sejajar rapi */
}

/* samakan tinggi input Kolom dengan TomSelect */
.order-multi-field select.form-select-sm{
  height:38px;
  min-height:38px;
}

.order-multi-select .ts-control{
  min-height:38px;
  padding:.375rem .5rem;
  border-radius:2px;
}

/* ===== TOMBOL SEARCH & RESET ===== */
#locker-form .btn-search-main,
#locker-form .btn-reset-main{
  height:38px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border-radius:2px;
}

/* Search (teal panjang) */
#locker-form .btn-search-main{
  padding:0 18px;
  background-color:#20a1ad;
  color:#fff;
  border:1px solid #20a1ad;
}
#locker-form .btn-search-main:hover{
  background-color:#17818b;
  border-color:#17818b;
}

/* Reset (kotak abu-abu) */
#locker-form .btn-reset-main{
  width:38px;
  padding:0;
  border:1px solid #d1d5db;
  background:#e5e7eb;
  color:#111827;
}

/* RESPONSIVE: di HP form turun vertikal */
@media (max-width: 768px){
  #locker-form{
    grid-template-columns: 1fr;   /* tombol turun ke bawah */
    row-gap:8px;
  }
  #locker-form .form-filter-actions{
    justify-content:flex-start;
  }
}
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
</style>
<?php if (!empty($totalRows)): ?>
<?php
  // hitung range data yang tampil di halaman ini
  if (!empty($orderCodes)) {
    // MODE SEARCH (multi-select): semua hasil di 1 halaman
    $from = $totalRows ? 1 : 0;
    $to   = $totalRows;
  } else {
    // MODE NORMAL (pagination biasa)
    $offset   = $offset   ?? 0;
    $PER_PAGE = $PER_PAGE ?? $totalRows;
    $page     = $page     ?? 1;

    $from = $totalRows ? ($offset + 1) : 0;
    $to   = min($offset + $PER_PAGE, $totalRows);
  }

  $page       = $page       ?? 1;
  $totalPages = $totalPages ?? 1;

  $maxLinks = 7;
  $start    = max(1, $page - 3);
  $end      = min($totalPages, $start + $maxLinks - 1);

  $prevPage = max(1, $page - 1);
  $nextPage = min($totalPages, $page + 1);

  $disableFirstPrev = ($page <= 1);
  $disableLastNext  = ($page >= $totalPages);

  // base query string (tanpa page) → supaya filter (mode, date, dll) tetap kebawa
  $qsBase = $_GET;
  unset($qsBase['page']);

  // helper tombol
  $makeBtn = function(string $label, int $target, bool $disabled = false, string $extraClass = '') use ($qsBase) {
    $cls = 'efidi-pager-btn ' . $extraClass . ($disabled ? ' is-disabled' : '');

    if ($disabled) {
      $href = 'javascript:void(0)';
    } else {
      $qs = $qsBase;
      $qs['page'] = $target;
      $href = '?' . http_build_query($qs);
    }

    return '<a class="' . $cls . '" href="' . $href . '"'
         . ($disabled ? ' aria-disabled="true" tabindex="-1"' : '')
         . '>' . $label . '</a>';
  };
?>
<!-- CARD khusus pager -->
<div class="row">
  <div class="col-12 grid-margin">
    <div class="card card-pager">
      <div class="card-body py-2 py-md-3">
        <div class="efidi-pager efidi-pager--center">
          <div class="efidi-pager-summary">
            <span class="efidi-pill-label">Data Orders</span>
            <span class="efidi-pager-text">
              Halaman <strong><?= $page ?></strong> dari <?= $totalPages ?>
              <span class="d-none d-sm-inline">
                · baris <?= number_format($from,0,',','.') ?>–<?= number_format($to,0,',','.') ?>
              </span>
              <span class="d-none d-md-inline">
                · total <?= number_format($totalRows,0,',','.') ?> ORDER
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
          formaction="export_orders.php"
          formmethod="get"
          disabled>
    <img class="excel-logo-small"
         src="https://upload.wikimedia.org/wikipedia/commons/e/e3/Microsoft_Office_Excel_%282019%E2%80%932025%29.svg"
         alt="Excel" style="width:18px;height:18px;margin-right:8px">
    <span>Download Excel</span>
  </button>
  <a href="tambah_order.php" class="btn" style="background:#182958;color:#fff;height:37px">
  Tambah
</a>
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
// Nomor urut mengikuti pagination (mulai dari offset+1)
$no = $totalRows > 0 ? ($offset + 1) : 0;

while ($data = $stList->fetch(PDO::FETCH_ASSOC)) :
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
      <a class="dropdown-item action-item"
         href="view_data.php?id_order=<?= (int)$data['id_order']; ?>">
        <span class="action-icon action-icon-view">
          <i class="mdi mdi-eye-outline"></i>
        </span>
        <span class="action-text">View</span>
      </a>

    <!-- DOWNLOAD -->
<a class="dropdown-item action-item"
   href="download_data.php?id_order=<?= (int)$data['id_order']; ?>">
  <span class="action-icon action-icon-download">
    <img class="excel-icon-menu"
         src="https://upload.wikimedia.org/wikipedia/commons/e/e3/Microsoft_Office_Excel_%282019%E2%80%932025%29.svg"
         alt="Excel">
    <!-- kalau mau lokal:
         simpan file di /images/excel-2019.svg
         lalu ganti src dengan "images/excel-2019.svg" -->
  </span>
  <span class="action-text">Download</span>
</a>


      <div class="dropdown-divider my-1"></div>

      <!-- HAPUS -->
      <form method="post" class="m-0 p-0 delete-order-form">
  <input type="hidden" name="id_order" value="<?= (int)$data['id_order']; ?>">
  <button type="submit"
          name="delete_order"
          class="dropdown-item action-item action-item-danger">
    <span class="action-icon action-icon-delete">
      <i class="mdi mdi-trash-can-outline"></i>
    </span>
    <span class="action-text">Hapus</span>
  </button>
</form>

    </div>
  </div>
</td>


<style>
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

/* Khusus logo Excel asli di dropdown */
.action-icon-download .excel-icon-menu{
  display: block;
  width: 16px;
  height: 16px;
  object-fit: contain;
}

/* Opsional: hilangkan warna hijau sebelumnya karena sekarang pakai logo asli */
.action-icon-download{
  color: inherit;
}

/* ====== WRAPPER TOMBOL AKSI ====== */
.dropdown-actions{
  position: relative;
}

/* Tombol 3 titik: bulat, shadow halus */
/* ===== SAMAKAN WARNA TOMBOL AKSI DENGAN RESET ===== */
.dropdown-actions .btn-action-main{
  width: 32px;
  height: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;

  /* sama seperti reset: kotak abu-abu */
  border-radius: 999px;
  background: #e5e7eb;        /* bg reset */
  border: 1px solid #d1d5db;  /* border reset */
  color: #374151;             /* ikon abu tua */

  box-shadow: none;           /* buang shadow biru tadi */
}

.dropdown-actions .btn-action-main i{
  font-size: 18px;
}

/* hover/focus sama juga seperti reset */
.dropdown-actions .btn-action-main:hover,
.dropdown-actions .btn-action-main:focus{
  background: #d1d5db;        /* hover reset */
  border-color: #d1d5db;
  color: #111827;
}

/* tetap hilangkan caret default bootstrap */
.dropdown-actions .btn-action-main::after{
  display: none;
}


/* ====== MENU DROPDOWN ====== */

/* ====== HEADER DI ATAS ITEM ====== */
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

/* ====== ITEM DI DALAM MENU ====== */
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

.action-icon{
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: #6b7280;
}

/* warna icon spesifik (opsional, biar beda fungsi kelihatan) */
.action-icon-view{
  color: #182958;
}
.action-icon-download{
  color: #16a34a;
}
.action-icon-delete{
  color: #ef4444;
}

/* hover item normal */
.action-item:hover{
  background: #f3f4f6;
  color: #111827;
  text-decoration: none;
}
.action-item:hover .action-icon{
  color: #111827;
}

/* khusus HAPUS (danger) */
.action-item-danger{
  color: #dc2626 !important;
}
.action-item-danger .action-icon{
  color: #ef4444;
}
.action-item-danger:hover{
  background: #fef2f2;
  color: #b91c1c !important;
}
.action-item-danger:hover .action-icon{
  color: #dc2626;
}

/* rapikan form hapus di dalam dropdown */
.delete-order-form{
  width: 100%;
}
.delete-order-form .dropdown-item{
  width: 100%;
  text-align: left;
}
/* ------ RESET PANAH LAMA ------ */
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

  /* default: menu di BAWAH tombol → ada jarak ke tombol */
  margin-top: 6px;
}

/* ============================
   DEFAULT: MENU DI BAWAH TOMBOL
   (placement bottom-*) → panah di ATAS menu,
   ujung panah menghadap ke ATAS (ke tombol)
   ============================ */
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

/* ===========================================
   OVERRIDE: MENU DI ATAS TOMBOL
   (placement top-*) → panah PINDAH ke BAWAH menu,
   ujung panah menghadap ke BAWAH (ke tombol)
   =========================================== */
.action-dropdown[data-popper-placement^="top"],
.action-dropdown[x-placement^="top"]{
  margin-top:0;          /* jangan dorong ke bawah */
  margin-bottom:6px;     /* beri jarak ke tombol di bawah */
}

.action-dropdown[data-popper-placement^="top"]::before,
.action-dropdown[x-placement^="top"]::before{
  top:auto;
  bottom:-6px;           /* pindah ke bawah menu */
  right:18px;
  border-width:6px 6px 0 6px;
  border-style:solid;
  border-color:#ffffff transparent transparent transparent;
}

.action-dropdown[data-popper-placement^="top"]::after,
.action-dropdown[x-placement^="top"]::after{
  top:auto;
  bottom:-7px;
  right:17px;
  border-width:7px 7px 0 7px;
  border-style:solid;
  border-color:rgba(148,163,184,.45) transparent transparent transparent;
}



</style>




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
        <!-- Modal Hapus Order -->
<div class="modal fade" id="hapusOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Hapus Data Order</h5>
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
              Yakin akan menghapus data order berikut?
            </p>
            <div id="hapusOrderLabel"
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
        <button type="button" class="btn btn-danger btn-sm" id="hapusOrderConfirm">
          <i class="mdi mdi-delete mr-1"></i> Hapus
        </button>
      </div>

    </div>
  </div>
</div>

<style>
  /* (opsional) samakan lebar modal dengan yang lain */
  #hapusOrderModal .modal-dialog{
    max-width: 520px;
    width: 100%;
  }
  @media (max-width: 576px){
    #hapusOrderModal .modal-dialog{
      max-width: 100%;
      margin: 0 12px;
    }
  }
</style>
<script>
(function () {
  let deleteForm = null;

  window.addEventListener('load', function () {
    if (!window.jQuery) {
      console.error('jQuery belum tersedia, handler hapus order tidak dipasang.');
      return;
    }

    var $ = window.jQuery;

    // INTERCEPT submit form hapus order
    $(document).on('submit', 'form.delete-order-form', function (ev) {
      var $form = $(this);

      // Kalau ini submit kedua setelah confirm (force submit) => biarkan lewat
      if ($form.data('force-submit') === 1) {
        return;
      }

      ev.preventDefault();      // tahan submit dulu
      deleteForm = this;

      // Ambil info order dari baris (No SC, No Internet, Nama)
      var $tr        = $form.closest('tr');
      var noSc       = $.trim($tr.find('td:nth-child(3) .copy-text').text());
      var noInternet = $.trim($tr.find('td:nth-child(4) .copy-text').text());
     

      var labelParts = [];
      if (noSc)       labelParts.push('No SC: ' + noSc);
      if (noInternet) labelParts.push('No Internet: ' + noInternet);
    

      $('#hapusOrderLabel').text(labelParts.join(' · ') || 'Order ini');

      // Tampilkan modal
      $('#hapusOrderModal').modal('show');
    });

    // Reset state tombol kalau modal ditutup
    $('#hapusOrderModal').on('hidden.bs.modal', function () {
      deleteForm = null;
      var $btn = $('#hapusOrderConfirm');
      $btn.data('loading', false)
          .prop('disabled', false)
          .html('<i class="mdi mdi-delete mr-1"></i> Hapus');
    });

    // Klik tombol Hapus di modal => spinner + submit form asli
   $('#hapusOrderConfirm').on('click', function () {
  if (!deleteForm) return;

  var $btn = $(this);
  if ($btn.data('loading')) return;  // cegah double click
  $btn.data('loading', true);

  $btn
    .prop('disabled', true)
    .html(
      "<span class='spinner-border spinner-border-sm mr-1' " +
      "role='status' aria-hidden='true'></span> Menghapus..."
    );

  // ✅ Pastikan field delete_order ikut terkirim
  var $form = $(deleteForm);
  if ($form.find('input[name="delete_order"]').length === 0) {
    $('<input>', {
      type: 'hidden',
      name: 'delete_order',
      value: '1'
    }).appendTo($form);
  }

  // tandai supaya intercept submit berikutnya dilewatkan
  $form.data('force-submit', 1);
  deleteForm.submit();
});

  });
})();
</script>

        <style>
  :root{
    /* warna aksen kolom + nilai */
    --order-filter-border: #00a3ad;
    --order-filter-shadow: rgba(0,163,173,.35);
  }
  
  /* PERKECIL SUDUT UNTUK KOLUM & NILAI (MULTI SELECT) */
.order-multi-field select.form-select-sm{
  border-radius: 2px !important;   /* kolom "Semua / No SC / ..." */
}

/* kotak input TomSelect "Ketik lalu pilih nilai..." */
.order-multi-select .ts-control{
  border-radius: 2px !important;
}

/* dropdown list TomSelect-nya juga dibikin tajam */
.order-multi-select .ts-dropdown{
  border-radius: 2px !important;
}


  /* ======= KOLUM (select biasa) ======= */
  .order-multi-field select.form-select-sm{
    border:1px solid var(--order-filter-border);
    box-shadow:none;
    outline:0;
    transition:border-color .15s, box-shadow .15s;
  }

  .order-multi-field select.form-select-sm:focus{
    border-color:var(--order-filter-border) !important;
    box-shadow:0 0 0 2px var(--order-filter-shadow) !important;
    outline:0;
  }

  /* ======= NILAI (TomSelect) ======= */
 
</style>

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

<style>
  .ts-dropdown .ts-loading-option,
  .ts-dropdown .ts-empty-hint{
    padding:6px 10px;
    font-size:13px;
    color:#6b7280;
  }
  .ts-dropdown .spinner-border{
    width:1rem;
    height:1rem;
    border-width:.15em;
  }
  .order-multi-select .ts-dropdown .option{
    padding: 6px 10px;     /* tinggi tiap baris */
    margin: 2px 0;         /* jarak antar baris */
    line-height: 1.4;
    font-size: 13px;       /* opsional, biar enak dibaca */
  }

  /* Saat aktif / hover tetap rapi */
  .order-multi-select .ts-dropdown .option.active,
  .order-multi-select .ts-dropdown .option.selected{
    padding: 6px 10px;
  }
</style>
<!-- TomSelect untuk multi-select Orders -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const fieldSelect = document.getElementById('orderField');
  const selectEl    = document.getElementById('locker');
  if (!fieldSelect || !selectEl) return;

  // Data opsi dari PHP (array string per kolom)
  const ORDER_OPTIONS = <?= json_encode([
    'no_sc'          => $orderOptionsByField['no_sc'],
    'no_internet'    => $orderOptionsByField['no_internet'],
    'cp_pelanggan'   => $orderOptionsByField['cp_pelanggan'],
    'nama_pelanggan' => $orderOptionsByField['nama_pelanggan'],
    'all'            => $orderOptionsAll,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  // Nilai yang sudah terpilih (dari submit sebelumnya)
  const SELECTED_VALUES = <?= json_encode($orderCodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  // --- Inisialisasi TomSelect dengan mode "local options", tanpa AJAX/load ---

  const ts = new TomSelect(selectEl, {
    plugins: ['remove_button'],
    create: false,
    persist: false,
    maxOptions: 200,
    placeholder: 'Ketik lalu pilih nilai…',
    closeOnSelect: false,
    valueField: 'value',
    labelField: 'text',
    searchField: ['text'],
    options: [],               // kita isi manual sesuai kolom
    sortField: [{field:'text', direction:'asc'}],
    
      onItemAdd: function (value, item) {
    this.setTextboxValue('');
  },

    render: {
      option: function (data, escape) {
        // Opsi "menunggu input" dengan spinner
        if (data.is_wait) {
          return '' +
            '<div class="ts-option ts-loading-option" data-selectable="false">' +
              '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
              '<span>' + escape(data.text) + '</span>' +
            '</div>';
        }
        return '<div>' + escape(data.text) + '</div>';
      },

      no_results: function (data, escape) {
        const fieldKey = (fieldSelect.value || '').trim();
        const isAll    = !fieldKey; // '' = Semua

        if (isAll) {
          if (!data.input || data.input.length < 2) {
            return '<div class="ts-empty-hint">Menunggu input… ketik minimal 2 karakter.</div>';
          }
          return '<div class="ts-empty-hint">Tidak ada hasil untuk "<strong>' +
                 escape(data.input) + '</strong>".</div>';
        }

        // Kolom tertentu
        if (!ORDER_OPTIONS[fieldKey] || !ORDER_OPTIONS[fieldKey].length) {
          return '<div class="ts-empty-hint">Belum ada data untuk kolom ini.</div>';
        }

        if (!data.input) {
          // Harusnya jarang muncul, karena kita sudah isi semua opsi
          return '<div class="ts-empty-hint">Tidak ada data.</div>';
        }

        return '<div class="ts-empty-hint">Tidak ada hasil untuk "<strong>' +
               escape(data.input) + '</strong>".</div>';
      }
    }
  });

  // ===== Helper untuk mengisi opsi =====

  // Tampilkan pseudo-option "menunggu input" (untuk kolom = Semua)
  function showWaitAll() {
    ts.clearOptions();
    ts.addOption({
      value: '__wait__',
      text: 'Menunggu input… ketik minimal 2 karakter.',
      is_wait: true
    });
    ts.refreshOptions(false);
  }

  // Isi semua nilai untuk kolom tertentu (No SC / No Internet / dst)
  function fillOptionsForField(fieldKey) {
    ts.clearOptions();

    const src = ORDER_OPTIONS[fieldKey] || [];
    const out = [];

    for (let i = 0; i < src.length; i++) {
      const v = src[i];
      if (!v) continue;
      const text = String(v);
      out.push({ value: text, text: text });
      if (out.length >= 200) break; // batas aman
    }

    if (out.length) {
      ts.addOptions(out);
    }
    ts.refreshOptions(false);
  }

  // Filter untuk kolom "Semua" berdasarkan query (minimal 2 karakter)
  function filterAllByQuery(query) {
    const q = (query || '').toLowerCase().trim();

    if (!q || q.length < 2) {
      showWaitAll();
      return;
    }

    const src = ORDER_OPTIONS.all || [];
    const out = [];

    for (let i = 0; i < src.length; i++) {
      const v = src[i];
      if (!v) continue;
      const text = String(v);
      if (text.toLowerCase().indexOf(q) !== -1) {
        out.push({ value: text, text: text });
        if (out.length >= 200) break;
      }
    }

    ts.clearOptions();
    if (out.length) {
      ts.addOptions(out);
    }
    ts.refreshOptions(false);
  }

  // ===== Inisialisasi pertama kali (sesuai kolom & nilai dari PHP) =====

  const initialField = (fieldSelect.value || '').trim();
  if (!initialField) {
    // Kolom = Semua
    showWaitAll();
  } else {
    // Kolom tertentu: tampilkan semua nilai langsung
    fillOptionsForField(initialField);
  }

  // Restore nilai yang sudah dipilih (jika ada) untuk kolom saat ini
  if (Array.isArray(SELECTED_VALUES) && SELECTED_VALUES.length) {
    const selectedOpts = SELECTED_VALUES.map(function (v) {
      return { value: v, text: v };
    });
    ts.addOptions(selectedOpts);
    ts.setValue(SELECTED_VALUES, true);
  }

  // ===== Event: ketika kolom diganti =====

  fieldSelect.addEventListener('change', function () {
    const fieldKey = (fieldSelect.value || '').trim();

    ts.clear();        // hapus pilihan user
    ts.clearOptions(); // hapus opsi lama

    if (!fieldKey) {
      // Kembali ke mode "Semua" -> spinner menunggu input
      showWaitAll();
    } else {
      // Kolom tertentu -> langsung isi semua nilai kolom tersebut
      fillOptionsForField(fieldKey);
    }

    ts.focus();
  });

  // ===== Event: ketika user mengetik di textbox TomSelect =====
  // Hanya untuk kolom = Semua (''), kolom lain pakai filter lokal bawaan TomSelect.

  ts.on('type', function (str) {
    const fieldKey = (fieldSelect.value || '').trim();
    const isAll    = !fieldKey;
    if (!isAll) return;

    filterAllByQuery(str);
  });

  // ===== Tombol reset filter =====

    const btnReset = document.getElementById('btnResetFilter');
  if (btnReset) {
    btnReset.addEventListener('click', function () {
      // Reload ke halaman awal (GET saja) -> semua filter multi-select dibuang
      window.location.href = window.location.pathname;
    });
  }

});
</script>


        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
     <?php
     include "footer.php";
      ?>