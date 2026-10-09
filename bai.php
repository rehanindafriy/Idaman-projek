<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once 'koneksi.php';

if (!function_exists('e')) {
  function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('short4')) {
  function short4(?string $value): string {
    $value = (string)$value;
    $length = strlen($value);

    if ($length <= 24) {
      return $value !== '' ? $value : '—';
    }

    return substr($value, 0, 12) . '…' . substr($value, -12);
  }
}

// === Lokasi file BAI disimpan oleh bot ===
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
      // opsional: set permission
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

    // (opsional) normalisasi agar aman disimpan/ditampilkan
    $uploader = preg_replace('/[^A-Za-z0-9._-]/', '', $uploader) ?: 'web';

    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("INSERT INTO bai (kode_bai, nama_bai, uploader, date_received) VALUES (?,?,?,?)");
      foreach ($codes as $sc) {
        $stmt->execute([$sc, $storedName, $uploader, $dateReceived]);
      }
      $pdo->commit();

      // beri flash message kalau punya helper, atau redirect agar form bersih
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

/* =========== Controller: Edit BAI (edit hanya kode_bai) =========== */
if (isset($_POST['edit_bai'])) {
  $err = null;

  $idBai = (int)($_POST['edit_id_bai'] ?? 0);
  $codes = array_values(array_unique(array_filter(
    array_map('trim', (array)($_POST['edit_codes'] ?? [])),
    fn($v) => $v !== ''
  )));

  if ($idBai <= 0) {
    $err = 'Data BAI tidak ditemukan.';
  } elseif (!$codes) {
    $err = 'Pilih minimal 1 NO SC.';
  }

  if ($err === null) {
    // pakai hanya kode pertama (satu baris BAI = satu kode_bai)
    $newCode = $codes[0];

    try {
      $st = $pdo->prepare("UPDATE bai SET kode_bai = ? WHERE id_bai = ?");
      $st->execute([$newCode, $idBai]);

      if (function_exists('flash')) {
        flash('ok', 'Kode BAI berhasil diperbarui.');
      }
      header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'bai.php'));
      exit;
    } catch (Throwable $e) {
      $err = 'Gagal menyimpan perubahan ke database.';
    }
  }

  if ($err !== null && function_exists('flash')) {
    flash('err', $err);
  }
}


/* ================= Controller: Search ================= */
/* ============== Controller: Search + Pagination ============== */
$selectedCodes = $_POST['bai_codes'] ?? [];
$isSearch      = isset($_POST['search']);
$alertType = null; $alertMsg = '';
$rows = [];

// Konfigurasi pagination
$PER_PAGE   = 100;                                               // ubah kalau mau 50 / 80 / dsb
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset     = ($page - 1) * $PER_PAGE;
$totalRows  = 0;
$totalPages = 1;

if ($isSearch) {
  // Normalisasi kode yang dipilih
  $selectedCodes = array_values(array_filter(
    array_map('trim', (array)$selectedCodes),
    fn($v) => $v !== ''
  ));

  if (count($selectedCodes) === 0) {
    // Tidak pilih apa-apa → tampilkan semua BAI dengan pagination
    $alertType = 'warning';
    $alertMsg  = 'Silakan pilih minimal 1 kode Berita Acara Instalasi.';

    $totalRows  = (int)($pdo->query("SELECT COUNT(*) FROM bai")->fetchColumn() ?: 0);
    $totalPages = max(1, (int)ceil($totalRows / $PER_PAGE));
    if ($page > $totalPages) {
      $page   = $totalPages;
      $offset = ($page - 1) * $PER_PAGE;
    }

    $stmt = $pdo->prepare("SELECT * FROM bai ORDER BY id_bai DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit',  $PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

  } else {
    // Mode SEARCH: filter by kode_bai, semua hasil ditampilkan di 1 halaman
    $in   = implode(',', array_fill(0, count($selectedCodes), '?'));
    $sql  = "SELECT * FROM bai WHERE kode_bai IN ($in) ORDER BY id_bai DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($selectedCodes);
    $rows = $stmt->fetchAll();

    if (!$rows) {
      $alertType = 'danger';
      $alertMsg  = 'Data tidak ditemukan untuk kode: <strong>'.e(implode(', ', $selectedCodes)).'</strong>.';
    } else {
      $alertType = 'success';
      $alertMsg  = 'Ditemukan <strong>'.count($rows).'</strong> Berita Acara Instalasi untuk kode: <strong>'.e(implode(', ', $selectedCodes)).'</strong>.';
    }

    // Search mode: semua hasil ditampilkan tanpa pagination
    $totalRows  = count($rows);
    $totalPages = 1;
    $page       = 1;
    $offset     = 0;
  }
} else {
  // MODE NORMAL (tanpa search) → pagination penuh
  $totalRows  = (int)($pdo->query("SELECT COUNT(*) FROM bai")->fetchColumn() ?: 0);
  $totalPages = max(1, (int)ceil($totalRows / $PER_PAGE));
  if ($page > $totalPages) {
    $page = $totalPages;
  }
  $offset = ($page - 1) * $PER_PAGE;

  $stmt = $pdo->prepare("SELECT * FROM bai ORDER BY id_bai DESC LIMIT :limit OFFSET :offset");
  $stmt->bindValue(':limit',  $PER_PAGE, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
}

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
/* === Khusus dropdown filter NO SC di form atas === */
#lockerWrap .ts-wrapper {
  width: 100%;
}

#lockerWrap .ts-control {
  width: 100%;
}

#lockerWrap .ts-dropdown {
  z-index: 1061;   /* di atas card, tapi tetap di bawah modal */
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
<div id="lockerWrap" class="position-relative" style="width:100%;">
                <select class="js-example-basic-multiple flex-grow-1" name="bai_codes[]" id="locker" multiple style="width:100%">
                 </select>
              </div>
              </div>
              <div class="form-group ml-3 mb-0 form-filter-actions">
                <button type="submit" name="search" class="btn px-10 mt-4" style="color:#fff;background-color:#20a1ad">Search</button>
                <button type="button"
        id="btnResetFilter"
        class="btn px-10 mt-4 btn-reset ml-2"
        title="Reset filter">
  <i class="mdi mdi-refresh" style="font-size:18px;"></i>
</button>
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
     <?php if ($totalRows > 0): ?>
  <?php
    // hitung range data yang tampil di halaman ini
    if ($selectedCodes) {
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
             <span class="efidi-pill-label">Berita Acara Instalasi</span>
<span class="efidi-pager-text">
  Halaman <strong><?= $page ?></strong> dari <?= $totalPages ?>
  <span class="d-none d-sm-inline">
    · baris <?= number_format($from,0,',','.') ?>–<?= number_format($to,0,',','.') ?>
  </span>
  <span class="d-none d-md-inline">
    · total <?= number_format($totalRows,0,',','.') ?> BAI
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
                  <td class="text-nowrap">
  <div class="dropdown dropdown-actions">
    <!-- TOMBOL UTAMA: 3 TITIK -->
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
      <!-- HEADER MINI -->
      <div class="action-dropdown-header">
        <span class="action-title">Aksi</span>
        <span class="action-subtitle">Berita Acara Instalasi</span>
      </div>

      <!-- EDIT -->
      <button type="button"
              class="dropdown-item action-item bai-edit-trigger"
              data-toggle="modal"
              data-bs-toggle="modal"
              data-target="#editBaiModal"
              data-bs-target="#editBaiModal"
              data-id="<?= (int)$data['id_bai'] ?>"
              data-kode="<?= e($data['kode_bai']) ?>">
        <span class="action-icon action-icon-edit">
          <i class="mdi mdi-pencil-outline"></i>
        </span>
        <span class="action-text">Edit</span>
      </button>

      <div class="dropdown-divider my-1"></div>

      <!-- HAPUS (tetap pakai form & modal konfirmasi lama) -->
      <form method="post"
            action="hapus_bai.php"
            class="m-0 p-0 delete-bai-form">
        <input type="hidden" name="id_bai" value="<?= (int)$data['id_bai'] ?>">
        <button type="submit"
                name="delete_bai"
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
  <label class="form-label">Pilih NO SC </label>

  <!-- parent KHUSUS utk select -->
  <div id="addCodesBox" class="position-relative">
    <select id="addCodes" name="add_codes[]"  multiple class="ts-sc"></select>
  </div>

  <div class="small text-muted mt-1">
    No Sc yang terpilih akan menjadi <code>kode_bai</code>.
  </div>
</div>


          <div class="mb-3">
            <label class="form-label">Upload File Berita Acara  Instalasi</label>
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

<!-- Modal Edit BAI -->
<div class="modal fade" id="editBaiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="edit_id_bai" id="editBaiId">

        <div class="modal-header">
          <h5 class="modal-title">Edit Kode BAI</h5>
          <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Pilih NO SC (Orders)</label>
            <div id="editCodesBox" class="position-relative">
              <select id="editCodes" name="edit_codes[]" multiple class="ts-sc"></select>
            </div>
            <div class="small text-muted mt-1">
              NO SC diambil dari tabel <code>orders</code>. NO SC yang sudah dipakai di tabel
              <code>bai</code> tidak akan muncul lagi di daftar pilihan.
            </div>
          </div>

          <div class="small text-muted mb-0">
            File BAI, uploader, dan tanggal diterima tidak diubah. Hanya <strong>kode_bai</strong> yang diperbarui.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="edit_bai" class="btn" style="background:#182958;color:#fff">
            Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  /* styling TomSelect di modal Edit */
  #editCodesBox{ position:relative; }
  #editCodesBox .ts-control{
    min-height:44px;
    border-radius:2px;
    width:100%;
  }
  #editCodesBox .ts-dropdown{
    position:absolute;
    top:100%;
    left:0;
    right:0;
    max-height:340px;
    overflow:auto;
    z-index:1065; /* di atas modal */
  }
</style>



<!-- Modal Hapus BAI -->
<div class="modal fade" id="hapusBaiModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Hapus Berita Acara</h5>
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
              Yakin akan menghapus Berita Acara berikut?
            </p>
            <div id="hapusBaiLabel"
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
        <button type="button" class="btn btn-danger btn-sm" id="hapusBaiConfirm">
          <i class="mdi mdi-delete mr-1"></i> Hapus
        </button>
      </div>

    </div>
  </div>
</div>

<style>
  #hapusBaiModal .modal-dialog{
    max-width: 520px;
    width: 100%;
  }
  @media (max-width: 576px){
    #hapusBaiModal .modal-dialog{
      max-width: 100%;
      margin: 0 12px;
    }
  }
</style>
<script>
(function () {
  let deleteBaiForm = null;

  window.addEventListener('load', function () {
    if (!window.jQuery) {
      console.error('jQuery belum tersedia, handler hapus BAI tidak dipasang.');
      return;
    }
    var $ = window.jQuery;

    // INTERCEPT submit form hapus BAI
    $(document).on('submit', 'form.delete-bai-form', function (ev) {
      var $form = $(this);

      // kalau ini submit kedua (setelah konfirmasi), biarkan lewat
      if ($form.data('force-submit') === 1) {
        return;
      }

      ev.preventDefault();
      deleteBaiForm = this;

      // Ambil info dari baris: kode + nama file
      var $tr   = $form.closest('tr');
      var kode  = $.trim($tr.find('td:nth-child(2) .copy-text').text());
     

      var labelParts = [];
      if (kode) labelParts.push('Kode: ' + kode);
     

      $('#hapusBaiLabel').text(labelParts.join(' · ') || 'BAI ini');

      $('#hapusBaiModal').modal('show');
    });

    // Reset state saat modal ditutup
    $('#hapusBaiModal').on('hidden.bs.modal', function () {
      deleteBaiForm = null;
      var $btn = $('#hapusBaiConfirm');
      $btn.data('loading', false)
          .prop('disabled', false)
          .html('<i class="mdi mdi-delete mr-1"></i> Hapus');
    });

    // Klik tombol Hapus di modal → spinner + submit form asli (ke hapus_bai.php)
    $('#hapusBaiConfirm').on('click', function () {
      if (!deleteBaiForm) return;

      var $btn = $(this);
      if ($btn.data('loading')) return;  // cegah double click

      $btn.data('loading', true)
          .prop('disabled', true)
          .html(
            "<span class='spinner-border spinner-border-sm mr-1' " +
            "role='status' aria-hidden='true'></span> Menghapus..."
          );

      var $form = $(deleteBaiForm);

      // jaga-jaga: pastikan field delete_bai ikut terkirim
      if ($form.find('input[name="delete_bai"]').length === 0) {
        $('<input>', {
          type: 'hidden',
          name: 'delete_bai',
          value: '1'
        }).appendTo($form);
      }

      // tandai supaya submit berikutnya tidak di-intercept
      $form.data('force-submit', 1);
      deleteBaiForm.submit();        // → POST ke hapus_bai.php
    });
  });
})();
</script>

<style>
.dropdown-actions{
  position: relative;
}

/* Tombol 3 titik (disamakan dengan tombol reset abu-abu) */
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
  display: none; /* hilangkan caret bootstrap */
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
  margin-top: 6px;
}

/* panah ketika menu di bawah tombol */
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

/* jika Popper taruh menu di ATAS tombol */
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

/* ====== HEADER MINI DI DROPDOWN ====== */
.action-dropdown-header{
  padding: 6px 14px 6px;
  border-bottom: 1px solid #f3f4f6;
  margin-bottom: 4px;
}
.action-dropdown-header .action-title{
  display:block;
  font-size:11px;
  font-weight:600;
  letter-spacing:.14em;
  text-transform:uppercase;
  color:#6b7280;
}
.action-dropdown-header .action-subtitle{
  display:block;
  font-size:11px;
  color:#9ca3af;
  margin-top:1px;
}

/* ====== ITEM AKSI ====== */
.action-item{
  display:flex;
  align-items:center;
  padding:7px 14px;
  gap:8px;
  color:#374151;
  transition:background .15s, color .15s;
}
.action-item .action-text{
  flex:1;
  white-space:nowrap;
}
.action-icon{
  width:20px;
  height:20px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:16px;
  color:#6b7280;
}

/* warna per aksi */
.action-icon-edit{   color:#182958; }
.action-icon-delete{ color:#ef4444; }

.action-item:hover{
  background:#f3f4f6;
  color:#111827;
  text-decoration:none;
}
.action-item:hover .action-icon{
  color:#111827;
}

/* khusus HAPUS (danger) */
.action-item-danger{
  color:#dc2626 !important;
}
.action-item-danger .action-icon{
  color:#ef4444;
}
.action-item-danger:hover{
  background:#fef2f2;
  color:#b91c1c !important;
}
.action-item-danger:hover .action-icon{
  color:#dc2626;
}

/* form hapus di dalam dropdown full width */
.delete-bai-form{
  width:100%;
}
.delete-bai-form .dropdown-item{
  width:100%;
  text-align:left;
}

</style>
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
    if (window.tsAddCodes) return;  // cegah dobel init

    const el  = document.getElementById('addCodes');
    const box = document.getElementById('addCodesBox');
    if (!el || !window.TomSelect) return;

    let page = 1,
        lastQuery = '',
        hasMore = true,
        loading = false;

    window.tsAddCodes = new TomSelect(el, {
      maxItems: 1,                       // 1 NO SC per baris BAI
      valueField: 'v',
      labelField: 'v',
      searchField: ['v'],
      placeholder: 'Ketik NO SC…',
      plugins: ['remove_button'],
      openOnFocus: true,
      closeAfterSelect: true,
      hideSelected: true,

      // langsung load ketika fokus
      preload: 'focus',
      shouldLoad: () => true,
      loadThrottle: 250,

      // PENTING: pakai box yang benar, bukan id yang tidak ada
      dropdownParent: box || document.body,

      render: {
        option : (d,esc)=>`<div>${esc(d.v)}</div>`,
        item   : (d,esc)=>`<div>${esc(d.v)}</div>`,
        loading: ()=>`<div class="py-2 px-2 text-muted">Loading…</div>`,
        no_results: ()=>`<div class="py-2 px-2 text-muted">Tidak ada hasil</div>`
      },

      load(query, cb) {
        if (loading) return;

        // reset paging kalau query berubah
        if (query !== lastQuery) {
          page    = 1;
          hasMore = true;
          this.clearOptions();
        }
        lastQuery = query || '';

        if (!hasMore) return cb();

        loading = true;

        fetch('orders_options.php?term=' + encodeURIComponent(lastQuery) +
              '&page=' + page +
              '&limit=50',
          { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )
          .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
          .then(j => {
            const list = (j.options || []).map(v => ({ v: String(v) }));
            hasMore = !!j.more;
            page   += 1;
            cb(list);
          })
          .catch(() => cb())
          .finally(() => { loading = false; });
      }
    });

    // infinite scroll
    window.tsAddCodes.on('dropdown_open', () => {
      page = 1;
      hasMore = true;
      window.tsAddCodes.load(lastQuery, () => {});
    });

    window.tsAddCodes.on('dropdown_scroll', () => {
      const c = window.tsAddCodes.dropdown_content;
      if (!c) return;
      if (c.scrollTop + c.clientHeight >= c.scrollHeight - 10) {
        window.tsAddCodes.load(lastQuery, () => {});
      }
    });
  }

  // helper: jalankan ketika TomSelect sudah siap
  function whenTSReady(fn){
    if (window.TomSelect) { fn(); return; }
    const s = document.querySelector('script[src*="tom-select"]');
    if (s) s.addEventListener('load', fn, {once:true});
  }

  // init saat DOM siap
  document.addEventListener('DOMContentLoaded', function(){
    whenTSReady(initAddCodes);
  });

  // juga init ketika modal Tambah dibuka (jaga-jaga)
  const modalEl = document.getElementById('addBaiModal');
  if (modalEl){
    const focusInput = () => {
      setTimeout(() => {
        document.querySelector('#addBaiModal .ts-control input')?.focus();
      }, 0);
    };

    modalEl.addEventListener('shown.bs.modal', function(){
      whenTSReady(function(){
        initAddCodes();
        focusInput();
      });
    });

    if (window.jQuery){
      jQuery(modalEl).on('shown.bs.modal', function(){
        whenTSReady(function(){
          initAddCodes();
          focusInput();
        });
      });
    }

    // optional: kalau mau destroy setiap modal ditutup
    const destroyTS = () => {
      if (window.tsAddCodes){
        window.tsAddCodes.destroy();
        window.tsAddCodes = null;
      }
    };
    modalEl.addEventListener('hidden.bs.modal', destroyTS);
    if (window.jQuery){
      jQuery(modalEl).on('hidden.bs.modal', destroyTS);
    }
  }
})();
</script>
<script>
(function () {
  window.tsEditCodes = null;

  function initEditCodes(){
    if (window.tsEditCodes) return;

    const el  = document.getElementById('editCodes');
    const box = document.getElementById('editCodesBox');
    if (!el || !window.TomSelect) return;

    let page=1, lastQuery='', hasMore=true, loading=false;

    window.tsEditCodes = new TomSelect(el, {
      maxItems: 1,
      valueField: 'v',
      labelField: 'v',
      searchField: ['v'],
      placeholder: 'Ketik NO SC…',
      plugins: ['remove_button'],
      openOnFocus: true,
      closeAfterSelect: true,
      hideSelected: true,
      preload: 'focus',
      shouldLoad: () => true,
      loadThrottle: 250,
      dropdownParent: box || document.body,

      render:{
        option : (d,esc)=>`<div>${esc(d.v)}</div>`,
        item   : (d,esc)=>`<div>${esc(d.v)}</div>`,
        loading: ()=>`<div class="py-2 px-2 text-muted">Loading…</div>`,
        no_results: ()=>`<div class="py-2 px-2 text-muted">Tidak ada hasil</div>`
      },

      load(query, cb){
        if (loading) return;

        if (query !== lastQuery){
          page    = 1;
          hasMore = true;
          this.clearOptions();
        }
        lastQuery = query || '';
        if (!hasMore) return cb();

        loading = true;
        fetch('orders_options.php?term=' + encodeURIComponent(lastQuery) +
              '&page=' + page +
              '&limit=50',
          {headers:{'X-Requested-With':'XMLHttpRequest'}}
        )
          .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
          .then(j => {
            const list = (j.options||[]).map(v => ({v:String(v)}));
            hasMore = !!j.more;
            page   += 1;
            cb(list);
          })
          .catch(() => cb())
          .finally(() => { loading = false; });
      }
    });

    // infinite scroll
    window.tsEditCodes.on('dropdown_open', () => {
      page = 1;
      hasMore = true;
      window.tsEditCodes.load(lastQuery, () => {});
    });

    window.tsEditCodes.on('dropdown_scroll', () => {
      const c = window.tsEditCodes.dropdown_content;
      if (!c) return;
      if (c.scrollTop + c.clientHeight >= c.scrollHeight - 10) {
        window.tsEditCodes.load(lastQuery, () => {});
      }
    });
  }

  function whenTSReady(fn){
    if (window.TomSelect) { fn(); return; }
    const s = document.querySelector('script[src*="tom-select"]');
    if (s) s.addEventListener('load', fn, {once:true});
  }

  // Klik menu "Edit"
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.bai-edit-trigger');
    if (!btn) return;

    e.preventDefault();

    const id   = btn.dataset.id || '';
    const kode = btn.dataset.kode || '';

    const modalEl   = document.getElementById('editBaiModal');
    const inputIdEl = document.getElementById('editBaiId');

    if (inputIdEl) inputIdEl.value = id;

    whenTSReady(function(){
      initEditCodes();

      if (window.tsEditCodes){
        // reset isi TomSelect
        window.tsEditCodes.clear();
        window.tsEditCodes.clearOptions();

        // pre-fill kode lama supaya tetap bisa dipilih,
        // walaupun no_sc tersebut sebenarnya sudah ada di tabel bai
        if (kode) {
          window.tsEditCodes.addOption({v:String(kode)});
          window.tsEditCodes.addItem(String(kode), true);
        }

        // load opsi lainnya
        window.tsEditCodes.load('', () => {});
        setTimeout(() => {
          document.querySelector('#editBaiModal .ts-control input')?.focus();
        }, 0);
      }
    });

    // buka modal (BS5 / BS4)
    if (window.bootstrap && bootstrap.Modal) {
      new bootstrap.Modal(modalEl).show();
    } else if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
      jQuery(modalEl).modal('show');
    }
  });

  // bersihkan saat modal ditutup
  const modalEdit = document.getElementById('editBaiModal');
  if (modalEdit){
    const clean = () => {
      if (window.tsEditCodes){
        window.tsEditCodes.destroy();
        window.tsEditCodes = null;
      }
      const inputIdEl = document.getElementById('editBaiId');
      if (inputIdEl) inputIdEl.value = '';
    };

    modalEdit.addEventListener('hidden.bs.modal', clean);
    if (window.jQuery){
      jQuery(modalEdit).on('hidden.bs.modal', clean);
    }
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
  const wrap = document.getElementById('lockerWrap');
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
    dropdownParent: wrap || 'body',
    loadThrottle: 250,
    load: function(q, cb){
      if (loading) return;
      if (q !== lastQuery){ page=1; hasMore=true; this.clearOptions(); }
      lastQuery = q;
      if (!hasMore) return cb();

      loading = true;
      fetch('./bai_options.php?term=' + encodeURIComponent(q||'') + '&page=' + page + '&limit=50')
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
  
    const btnReset = document.getElementById('btnResetFilter');
  if (btnReset) {
    btnReset.addEventListener('click', function () {
      // bersihkan pilihan di TomSelect
      ts.clear();          // hapus semua selected item
      // kalau mau sekalian hapus opsi yang sudah ke-load:
      // ts.clearOptions();

      // reset form search
      const form = document.getElementById('locker-form');
      if (form) form.reset();

      // reload sebagai GET tanpa POST -> balik ke halaman awal & tanpa filter
      window.location.href = window.location.pathname;
    });
  }


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
