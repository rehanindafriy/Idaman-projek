<?php
include "header.php";
require_once __DIR__ . '/koneksi.php';

/* ================== UTIL ================== */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function idify(string $s): string {
  // jadikan aman untuk id elemen
  return 'tg-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $s);
}

/* ================== LOKASI FILE FOTO ==================
   Struktur folder: bot/foto/{kode_foto}/foto2.(jpg|jpeg|png|webp)
   ===================================================== */
$FOTO_FS_DIR   = realpath(__DIR__ . '/bot/foto') ?: (__DIR__ . '/bot/foto'); // path server
$FOTO_FS_DIR   = rtrim($FOTO_FS_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$FOTO_URL_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/bot/foto'; // base URL publik

function find_foto_relurl(string $kode): ?string {
  global $FOTO_FS_DIR, $FOTO_URL_BASE;
  $kode = trim($kode);
  if ($kode === '') return null;

  $dir = $FOTO_FS_DIR . $kode . DIRECTORY_SEPARATOR;
  if (!is_dir($dir)) return null;

  // 1) Prioritas nama "foto2.*"
  foreach (['jpg','jpeg','png','webp','JPG','JPEG','PNG','WEBP'] as $ext) {
    $full = $dir . 'foto2.' . $ext;
    if (is_file($full)) {
      return $FOTO_URL_BASE . '/' . rawurlencode($kode) . '/foto2.' . $ext;
    }
  }

  // 2) Kalau tidak ada foto2.*, ambil foto pertama apa saja
  //    (urut natural biar 2 < 10)
  $files = [];
  foreach (['jpg','jpeg','png','webp','gif','JPG','JPEG','PNG','WEBP','GIF'] as $ext) {
    foreach (glob($dir . '*.' . $ext) ?: [] as $f) {
      if (is_file($f)) $files[] = $f;
    }
  }
  if (!$files) return null;

  // Urut natural by basename (62.jpg, 63.jpg, ...)
  usort($files, function($a, $b){
    return strnatcasecmp(basename($a), basename($b));
  });

  $first = $files[0];
  $filename = basename($first); // contoh "62.jpg"
  return $FOTO_URL_BASE . '/' . rawurlencode($kode) . '/' . rawurlencode($filename);
}

function list_foto_urls(string $kode, int $limit=4): array {
  global $FOTO_FS_DIR, $FOTO_URL_BASE;
  $dir = $FOTO_FS_DIR . $kode . DIRECTORY_SEPARATOR;
  if (!is_dir($dir)) return [];

  $files = [];
  foreach (['jpg','jpeg','png','webp','gif','JPG','JPEG','PNG','WEBP','GIF'] as $ext) {
    foreach (glob($dir . '*.' . $ext) ?: [] as $f) {
      if (is_file($f)) $files[] = $f;
    }
  }
  if (!$files) return [];

  usort($files, function($a, $b){
    return strnatcasecmp(basename($a), basename($b));
  });

  $files = array_slice($files, 0, $limit);
  $urls  = [];
  foreach ($files as $f) {
    $urls[] = $FOTO_URL_BASE . '/' . rawurlencode($kode) . '/' . rawurlencode(basename($f));
  }
  return $urls;
}



/* ================== AMBIL OPSI KODE_FOTO UNTUK SELECT ================== */
$allKode = [];
try {
  $stmt = $pdo->query("SELECT DISTINCT kode_foto FROM foto ORDER BY kode_foto ASC");
  $allKode = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $allKode = [];
}

/* ================== PROSES PENCARIAN ================== */
$isSearch = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['search']);
$selectedCodes = [];
if ($isSearch) {
  $selectedCodes = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['bai_codes'] ?? [])), fn($v)=>$v!=='')));
}
/* ============ Ambil opsi NO_SC dari tabel orders ============ */
$noScOptions = [];
try {
  $sql = "
    SELECT DISTINCT TRIM(o.no_sc) AS no_sc
    FROM orders o
    WHERE o.no_sc IS NOT NULL
      AND TRIM(o.no_sc) <> ''
      AND NOT EXISTS (
        SELECT 1
        FROM foto f
        WHERE f.kode_foto = TRIM(o.no_sc)
      )
    ORDER BY TRIM(o.no_sc) ASC
  ";
  $stmt = $pdo->query($sql);
  $noScOptions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $noScOptions = [];
}


/* ============ Proses submit dari Modal Tambah Foto ============ */
$flashType = null; $flashMsg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['upload_foto'])) {
  $codes = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['kode_foto'] ?? [])))));
  $uploader = $_SESSION['username'] ?? 'system';

  if (!$codes) {
    $flashType = 'warning';
    $flashMsg  = 'Pilih minimal 1 <strong>No. SC</strong>.';
  } elseif (empty($_FILES['photos']['name']) || !$_FILES['photos']['name'][0]) {
    $flashType = 'warning';
    $flashMsg  = 'Pilih minimal 1 file foto.';
  } else {
    $okExt = ['jpg','jpeg','png','webp','gif'];
    $saved = 0; $inserted = 0; $skipped = 0;

    /* pastikan ada baris di tabel `foto` untuk tiap kode */
    foreach ($codes as $code) {
      $q = $pdo->prepare("SELECT id_foto FROM foto WHERE kode_foto = ? LIMIT 1");
      $q->execute([$code]);
      if (!$q->fetchColumn()) {
        $ins = $pdo->prepare("INSERT INTO foto (kode_foto, uploader, date_received) VALUES (?, ?, NOW())");
        $ins->execute([$code, $uploader]);
        $inserted++;
      }
    }

    /* helper buat nama aman & unik */
    $makeSafe = function(string $name): array {
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $base= pathinfo($name, PATHINFO_FILENAME);
      $base= preg_replace('/[^\w\-]+/','_', $base);
      if ($base === '') $base = 'img';
      return [$base, $ext];
    };
    $ensureDir = function(string $dir){
      if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    };

    $firstCode = $codes[0];                                        // Anda sudah pastikan $codes tidak kosong
$firstDir  = $FOTO_FS_DIR . $firstCode . DIRECTORY_SEPARATOR;  // bot/foto/{kode_foto}/
$ensureDir($firstDir);



    $names = $_FILES['photos']['name'];
$tmps  = $_FILES['photos']['tmp_name'];
$errs  = $_FILES['photos']['error'];

$MAX_FILES = 25;
$total = min(count($names), $MAX_FILES);

for ($i = 0; $i < $total; $i++) {
  if ($errs[$i] !== UPLOAD_ERR_OK) { $skipped++; continue; }
  [$base, $ext] = $makeSafe($names[$i]);
  if (!in_array($ext, $okExt, true)) { $skipped++; continue; }

  $destPath = $firstDir . $base . '.' . $ext;
  $suffix = 1;
  while (file_exists($destPath)) {
    $destPath = $firstDir . $base . "_$suffix." . $ext;
    $suffix++;
  }
  if (!@move_uploaded_file($tmps[$i], $destPath)) { $skipped++; continue; }
  $saved++;

  for ($j = 1; $j < count($codes); $j++) {
    $codeJ = $codes[$j];
    $dirJ  = $FOTO_FS_DIR . $codeJ . DIRECTORY_SEPARATOR;
    $ensureDir($dirJ);

    $baseName = basename($destPath);
    $cand = $dirJ . $baseName;
    $k = 1;
    while (file_exists($cand)) {
      $cand = $dirJ . pathinfo($baseName, PATHINFO_FILENAME) . "_$k." . pathinfo($baseName, PATHINFO_EXTENSION);
      $k++;
    }
    @copy($destPath, $cand);
  }
}

if (count($names) > $MAX_FILES) {
  $skipped += (count($names) - $MAX_FILES);
}

    $flashType = 'success';
    $flashMsg  = "Upload berhasil: <strong>$saved</strong> file ke <strong>".count($codes)."</strong> kode. "
               . ($inserted ? "(buat entri baru: <strong>$inserted</strong>)" : "");
  }
}

$rows = [];
$PER_PAGE   = 100; // atur sesuai kebutuhan: 50 / 80 / 100 / dst
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset     = ($page - 1) * $PER_PAGE;
$totalRows  = 0;
$totalPages = 1;

try {
  if ($selectedCodes) {
    // filter per kode_foto terpilih
    $in  = implode(',', array_fill(0, count($selectedCodes), '?'));
    $sql = "SELECT id_foto, kode_foto, uploader, date_received
            FROM foto
            WHERE kode_foto IN ($in)
            ORDER BY FIELD(kode_foto, " . implode(',', array_fill(0, count($selectedCodes), '?')) . "), id_foto DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($selectedCodes, $selectedCodes));

    $rows       = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    // ==== khusus MODE SEARCH ====
    $totalRows  = count($rows);  // total data hasil filter
    $totalPages = 1;             // paksa cuma 1 halaman (biar tidak ada page 2)
    $page       = 1;
    $offset     = 0;
  } else {
    // tampilkan terbaru jika belum pilih apa-apa (mode normal, pakai pagination)
    $totalRows  = (int)($pdo->query("SELECT COUNT(*) FROM foto")->fetchColumn() ?: 0);
    $totalPages = max(1, (int)ceil($totalRows / $PER_PAGE));

    $stmt = $pdo->prepare("SELECT id_foto, kode_foto, uploader, date_received
                           FROM foto
                           ORDER BY id_foto DESC
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit',  $PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  $rows = [];
  $totalRows = 0;
  $totalPages = 1;
}


// ==== ALERT ====
$alertType = null; $alertMsg = '';
if ($isSearch) {
  if (!$selectedCodes) {
    $alertType = 'warning';
    $alertMsg  = 'Pilih minimal 1 <strong>kode_foto</strong>.';
  } elseif (!$rows) {
    $alertType = 'danger';
    $alertMsg  = 'Data tidak ditemukan untuk kode: <strong>'.e(implode(', ', $selectedCodes)).'</strong>.';
  } else {
    $alertType = 'success';
    $alertMsg  = 'Ditemukan <strong>'.count($rows).'</strong> data untuk kode: <strong>'.e(implode(', ', $selectedCodes)).'</strong>.';
  }
}

?>

<!-- ======================= VIEW ======================= -->
<div class="main-panel">
  <div class="content-wrapper">

    <!-- Header -->
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Evidensi Lapangan</h2>
            <p class="mb-0">Cari berdasarkan <strong>kode_foto</strong>. Bisa pilih lebih dari satu.</p>
            <p>Gambar diambil dari <code>bot/foto/{kode_foto}/foto2.(jpg|jpeg|png|webp)</code>.</p>
          </div>

          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo" class="mb-1" style="height:45px; margin-top:-100px; display:block;">
            <br><br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
              <span class="text-primary crumb-mid">Evidensi Lapangan</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search Card -->
    <div class="row">
      <div class="col-12 grid-margin stretch-card">
        <div class="card">
          <div class="card-body d-flex align-items-center justify-content-center py4">
            <form class="forms-sample d-flex align-items-center mb-0 mx-auto w-100" method="POST" id="foto-form">
              <div class="form-group flex-grow-1 mb-0" id="leftCell">
                <label for="kode-search">Cari Berdasarkan Kode Foto (bisa multi-pilih)</label>
                <select class="mult-select-tag flex-grow-1" name="bai_codes[]" id="kode-search" multiple style="width:100%">
                  <?php foreach ($allKode as $k): ?>
                    <option value="<?= e($k) ?>" <?= in_array($k, $selectedCodes, true) ? 'selected' : '' ?>><?= e($k) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="help-list">Ketik untuk mencari, Enter untuk memilih, gunakan Backspace untuk hapus.</div>
              </div>
              <div class="form-group ml-3 mb-4 form-filter-actions">
                <button type="submit" name="search" class="btn px-10 mt-4 btn-cari">Search</button>
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
    <div class="alert alert-<?= $alertType ?> alert-dismissible fade show mb-0" role="alert"
         style="padding:.5rem .75rem; margin-top:-13px;">
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
              <span class="efidi-pill-label">Evidensi Lapangan</span>
              <span class="efidi-pager-text">
                Halaman <strong><?= $page ?></strong> dari <?= $totalPages ?>
                <span class="d-none d-sm-inline">
                  · baris <?= number_format($from,0,',','.') ?>–<?= number_format($to,0,',','.') ?>
                </span>
                <span class="d-none d-md-inline">
                  · total <?= number_format($totalRows,0,',','.') ?> kode_foto
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

    <!-- Tabel hasil -->
    <div class="row">
      <div class="col-12 stretch-card">
        <div class="card">
          <div class="card-body pb-0 d-flex justify-content-between align-items-center">
            <p class="card-title mb-2">DATA FOTO</p>
<button
  type="button"
  id="btnAddFoto"
  class="btn"
  style="background:#182958;color:#fff"
  data-bs-toggle="modal" data-bs-target="#addFotoModal"
  data-toggle="modal"  data-target="#addFotoModal">
  Tambah
</button>


          </div>
          
          <div class="card-body">
            <div class="table-responsive">
                
              <table id="example" class="display" style="width:100%">
                  
                <thead>
                  <tr>
                    <th>No</th>
                    <th  style="width:150px">Kode Foto</th>
                    <th>Foto</th>
                    <th style="width:70px" class="th-aksi">Aksi</th>

                  </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                foreach ($rows as $r):
                  $kode   = (string)$r['kode_foto'];
                  // Ambil maksimal 60 foto pertama per kode (lebih dari cukup)
                  $thumbs = list_foto_urls($kode, 60);
                  $gridId = idify($kode);
                ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td>
                      <span class="copy-wrap" data-copy="<?= e($kode) ?>">
                        <span class="copy-text"><?= e($kode) ?></span>
                        <i class="mdi mdi-content-copy copy-icon" title="Salin"></i>
                      </span>
                    </td>
<?php

  $visible = 6; // jumlah default yang terlihat saat collapsed (sinkron dgn CSS di bawah)
?>
<td>
  <?php if ($thumbs): ?>
    <div class="thumb-wrap">
      <!-- default: mode clip (hanya 12 terlihat), tetap 1 baris -->
      <div class="thumb-row clip" id="<?= e($gridId) ?>" data-visible="<?= (int)$visible ?>">
       <?php foreach ($thumbs as $u): ?>
  <button type="button"
          class="thumb-modal-trigger"
          data-img="<?= e($u) ?>"
          data-kode="<?= e($kode) ?>"
          title="Lihat foto">
    <img src="<?= e($u) ?>" alt="foto <?= e($kode) ?>">
  </button>
<?php endforeach; ?>


      </div>

      <?php if (count($thumbs) > $visible): ?>
        <button type="button" class="btn btn-xs thumb-toggle" data-target="<?= e($gridId) ?>">
          Lihat semua
        </button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <span class="badge bg-warning text-dark" style="vertical-align:middle;margin-right:8px">Belum ada foto</span>
   
  <?php endif; ?>
</td>
<td class="td-aksi text-right">
  <div class="dropdown dropdown-actions">

    <!-- Tombol utama: 3 titik -->
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

    <!-- Menu dropdown -->
    <div class="dropdown-menu dropdown-menu-right dropdown-menu-end action-dropdown">
      <!-- Header mini (sama seperti BAI) -->
      <div class="action-dropdown-header">
        <span class="action-title">Aksi</span>
        <span class="action-subtitle">Evidensi Lapangan</span>
      </div>

      <!-- Kelola / Upload foto -->
      <button type="button"
              class="dropdown-item action-item js-manage-foto"
              data-kode="<?= e(trim($kode)) ?>"
              data-id="<?= (int)$r['id_foto'] ?>"
              onclick="return openManageFoto(this);">
        <span class="action-icon action-icon-edit">
          <i class="mdi mdi-upload"></i>
        </span>
        <span class="action-text">Kelola Foto</span>
      </button>

      <div class="dropdown-divider my-1"></div>

      <!-- Hapus semua foto untuk kode ini (pakai modal konfirmasi, GET ke hapus_foto.php tetap dipakai) -->
      <a href="hapus_foto.php?kode=<?= urlencode($kode) ?>"
         class="dropdown-item action-item action-item-danger foto-delete-trigger"
         data-kode="<?= e($kode) ?>">
        <span class="action-icon action-icon-delete">
          <i class="mdi mdi-trash-can-outline"></i>
        </span>
        <span class="action-text">Hapus Semua Foto</span>
      </a>
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

  </div><!-- /.content-wrapper -->
</div><!-- /.main-panel -->
<div class="modal fade" id="manageFotoModal" tabindex="-1" aria-labelledby="manageFotoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageFotoLabel">Kelola Foto</h5>
        <button type="button"
                class="close"
                data-bs-dismiss="modal"
                data-dismiss="modal"
                aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="manageKode">
        <div class="mb-2 small text-muted">
          Foto yang sudah ada untuk
          <span id="manageKodeLabel" class="font-weight-bold"></span>:
        </div>

        <div id="manageThumbs" class="upload-preview" style="max-height:160px"></div>

        <hr>
        <form id="manageUploadForm">
          <label class="form-label">Tambah Foto</label>
          <input id="manageFiles" type="file" name="photos[]" accept="image/*" multiple class="form-control">
          <div class="small text-muted mt-1">
            Format: JPG, JPEG, PNG, WEBP, GIF. Maks 25 file per unggahan.
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
        <button id="btnUploadNow" type="button" class="btn" style="background:#182958;color:#fff">
          Simpan Perubahan
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* ==== Modal Kelola Foto (existing) ==== */
/* ==== Modal Kelola Foto: lebih formal & rapi ==== */
#manageFotoModal .modal-dialog{
  --bs-modal-width: 720px;
  margin-top: 5vh;
  margin-bottom: auto;
}

#manageFotoModal .modal-content{
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 24px 60px rgba(15,23,42,.22);
}

#manageFotoModal .modal-header{
  padding: 10px 14px;
  border-bottom: 1px solid #e5e7eb;
}

#manageFotoModal .modal-footer{
  padding: 10px 14px;
  border-top: 1px solid #f3f4f6;
}

#manageFotoModal .modal-body{
  padding: 12px 14px;
  max-height: calc(72vh - 100px);
  overflow-y: auto;
}

/* Grid foto yang sudah ada di modal Kelola Foto */
#manageThumbs{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  border:1px dashed #e5e7eb;
  background:#f9fafb;
  padding:8px;
  max-height:160px;
  overflow-y:auto;
  border-radius:4px;
}

#manageThumbs .thumb{
  position:relative;
  width:64px;
  height:64px;
  flex:0 0 auto;
}

#manageThumbs .thumb img{
  width:100%;
  height:100%;
  object-fit:cover;
  border:1px solid #e5e7eb;
  border-radius:3px;
}

/* Tombol X */
#manageThumbs .thumb .thumb-x{
  position:absolute;
  top:-6px;
  right:-6px;
  width:18px;
  height:18px;
  line-height:16px;
  border-radius:50%;
  border:1px solid #d1d5db;
  background:#fff;
  cursor:pointer;
  font-weight:bold;
  font-size:12px;
  padding:0;
}

/* State: ditandai hapus */
#manageThumbs .thumb.thumb-deleting img{
  opacity:.35;
  border-color:#f97373;
}

#manageThumbs .thumb.thumb-deleting::after{
  content:"hapus";
  position:absolute;
  left:2px;
  bottom:2px;
  font-size:10px;
  padding:1px 4px;
  border-radius:999px;
  background:#fee2e2;
  color:#b91c1c;
}


</style>
<script>
// ================== HELPER MODAL (BS4 & BS5) ==================
function efidiShowModal(modalElOrId) {
  const modalEl = (typeof modalElOrId === 'string')
    ? document.getElementById(modalElOrId)
    : modalElOrId;
  if (!modalEl) return;

  try {
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }
  } catch (e) {}

  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
    window.jQuery(modalEl).modal('show');
    return;
  }

  // fallback manual (tanpa Bootstrap)
  modalEl.classList.add('show');
  modalEl.style.display = 'block';
  modalEl.removeAttribute('aria-hidden');
  document.body.classList.add('modal-open');
}

function efidiHideModal(modalElOrId) {
  const modalEl = (typeof modalElOrId === 'string')
    ? document.getElementById(modalElOrId)
    : modalElOrId;
  if (!modalEl) return;

  try {
    if (window.bootstrap && window.bootstrap.Modal) {
      const inst = window.bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
      return;
    }
  } catch (e) {}

  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
    window.jQuery(modalEl).modal('hide');
    return;
  }

  modalEl.classList.remove('show');
  modalEl.style.display = 'none';
  modalEl.setAttribute('aria-hidden','true');
  document.body.classList.remove('modal-open');
}

// ================== Modal Kelola Foto ==================
let manageDeleteSet = new Set();  // kumpulan nama file yang ditandai hapus

// Handler buka modal "Kelola Foto"
function openManageFoto(trigger){
  const manageModalEl = document.getElementById('manageFotoModal');
  const thumbsEl      = document.getElementById('manageThumbs');
  const kodeEl        = document.getElementById('manageKode');
  const kodeLabelEl   = document.getElementById('manageKodeLabel');
  const filesInput    = document.getElementById('manageFiles');

  if (!manageModalEl || !thumbsEl || !kodeEl) return false;

  const kode = (trigger && trigger.dataset && trigger.dataset.kode
                ? trigger.dataset.kode
                : '').replace(/\u00a0/g,' ').trim();
  const id   = (trigger && trigger.dataset) ? (trigger.dataset.id || '') : '';

  if (!kode) return false;

  // reset state
  manageDeleteSet = new Set();

  // set nilai & label
  kodeEl.value = kode;
  const titleEl = document.getElementById('manageFotoLabel');
  if (titleEl)  titleEl.textContent = 'Kelola Foto — ' + kode;
  if (kodeLabelEl) kodeLabelEl.textContent = kode;
  if (filesInput)  filesInput.value = '';

  // placeholder awal
  thumbsEl.innerHTML = '<div class="text-muted small">Memuat…</div>';

  // tampilkan modal
  efidiShowModal(manageModalEl);

  // load list foto
  fetch('foto_ajax.php?act=list&kode=' + encodeURIComponent(kode) +
        '&id_foto=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(js => {
      if (!js.ok) throw new Error(js.error || 'Gagal memuat');
      renderManageThumbs(js.files || []);
    })
    .catch(e => {
      thumbsEl.innerHTML =
        '<div class="text-danger small">' +
        (e && e.message ? e.message : e) +
        '</div>';
    });

  return false; // supaya onclick tidak ikut submit / navigate
}

// Render grid thumbnail di modal Kelola Foto
function renderManageThumbs(files){
  const thumbsEl = document.getElementById('manageThumbs');
  if (!thumbsEl) return;

  thumbsEl.innerHTML = '';
  if (!files || !files.length){
    thumbsEl.innerHTML = '<div class="small text-muted">Belum ada foto.</div>';
    return;
  }

  files.forEach(f => {
    const box = document.createElement('div');
    box.className = 'thumb';
    box.dataset.name = f.name;

    if (manageDeleteSet.has(f.name)) {
      box.classList.add('thumb-deleting');
    }

    const img = document.createElement('img');
    img.src = f.url;
    img.alt = f.name;

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'thumb-x';
    del.title = 'Tandai / batalkan hapus';
    del.textContent = '×';

    box.appendChild(img);
    box.appendChild(del);
    thumbsEl.appendChild(box);
  });
}

// Klik X di thumbnail: hanya tandai/batalkan hapus
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('#manageThumbs .thumb-x');
  if (!btn) return;

  const box  = btn.parentElement;
  const name = box && box.dataset ? box.dataset.name : '';
  if (!name) return;

  if (manageDeleteSet.has(name)) {
    manageDeleteSet.delete(name);
    box.classList.remove('thumb-deleting');
  } else {
    manageDeleteSet.add(name);
    box.classList.add('thumb-deleting');
  }
});

// Tombol "Simpan Perubahan" di modal Kelola Foto
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#btnUploadNow');
  if (!btn) return;

  const kode       = (document.getElementById('manageKode') || {}).value || '';
  const filesInput = document.getElementById('manageFiles');

  if (!kode) {
    alert('Kode foto tidak dikenali.');
    return;
  }

  const toDelete = Array.from(manageDeleteSet);
  const hasNew   = filesInput && filesInput.files && filesInput.files.length > 0;

  if (!toDelete.length && !hasNew) {
    alert('Tidak ada perubahan yang disimpan.');
    return;
  }

  if (!confirm('Simpan perubahan foto untuk kode "' + kode + '" ?')) {
    return;
  }

  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Menyimpan…';

  try {
    // 1) Hapus file yang ditandai
    for (const name of toDelete) {
      const fdDel = new FormData();
      fdDel.append('kode', kode);
      fdDel.append('name', name);

      const resDel = await fetch('foto_ajax.php?act=delete', {
        method: 'POST',
        body: fdDel
      });
      const jsDel = await resDel.json();
      if (!jsDel.ok) throw new Error(jsDel.error || ('Gagal hapus "' + name + '"'));
    }

    // 2) Upload file baru (jika ada)
    if (hasNew) {
      const fdUp = new FormData();
      fdUp.append('kode', kode);
      for (const f of filesInput.files) {
        fdUp.append('photos[]', f);
      }

      const resUp = await fetch('foto_ajax.php?act=upload', {
        method: 'POST',
        body: fdUp
      });
      const jsUp = await resUp.json();
      if (!jsUp.ok) throw new Error(jsUp.error || 'Gagal upload foto baru');
      filesInput.value = '';
    }

    // reset state + reload list di modal
    manageDeleteSet.clear();

    const thumbsEl = document.getElementById('manageThumbs');
    if (thumbsEl) {
      thumbsEl.innerHTML = '<div class="text-muted small">Memuat…</div>';
    }

    const r2 = await fetch('foto_ajax.php?act=list&kode=' +
                           encodeURIComponent(kode) + '&id_foto=');
    const j2 = await r2.json();
    if (!j2.ok) throw new Error(j2.error || 'Gagal memuat ulang');
    renderManageThumbs(j2.files || []);

    // tutup modal + refresh tabel utama supaya thumb di kolom ikut update
    efidiHideModal('manageFotoModal');
    window.location.reload();
  } catch (e) {
    alert(e.message || e);
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
});
</script>


<div class="modal fade" id="addFotoModal" tabindex="-1" aria-labelledby="addFotoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" id="addFotoDialog">

    <form class="modal-content" method="POST" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="addFotoLabel">Tambah Foto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="upload_foto" value="1">

       <div class="mb-3">
  <label class="form-label">Pilih NO SC</label>
  <div id="addFotoCodesBox" class="position-relative">
    <select id="ms-kode-foto" name="kode_foto[]" multiple class="ts-sc">
      <?php foreach ($noScOptions as $sc): ?>
        <option value="<?= e($sc) ?>"><?= e($sc) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <small class="text-muted">
    NO SC yang terpilih akan menjadi <code>kode_foto</code>.
  </small>
</div>

        <div class="mb-3">
          <label class="form-label">Upload Foto</label>
          <input type="file" name="photos[]" accept="image/*" multiple class="form-control">
          <small class="text-muted">Format: JPG, JPEG, PNG, WEBP, GIF. Foto tidak boleh sama.</small>
          <div id="uploadPreview" class="upload-preview"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary"
        style="background:#182958;border-color:#182958">
  Simpan
</button>

      </div>
    </form>
  </div>
</div>

<!-- ====== Styles + Tom Select ====== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<style>
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

/* Card-nya: lebih ramping, nggak terlalu tinggi */
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


  .order-search-card{background:#fff;border:1px solid #eee;border-radius:2px;padding:30px 22px}
  #leftCell > *{width:100%}
  .help-list{margin-top:8px;color:#6b7280;font-size:14px;line-height:1.6}
  .btn-cari{
    --btn-bg:#20a1ad; --btn-hover:#20a1ad; --btn-text:#fff; --btn-border:#20a1ad;
    --btn-height:38px;
    background:var(--btn-bg); color:var(--btn-text); border:1px solid var(--btn-border);
    height:var(--btn-height); padding:0 18px; border-radius:2px; transition:.15s;
  }
  .btn-cari:hover{background:var(--btn-hover); border-color:var(--btn-hover)}
  .copy-wrap{display:inline-flex;align-items:center;gap:6px;border:1px dashed #cbd5e1;padding:2px 6px;border-radius:2px}
  .copy-wrap .copy-icon{font-size:10px;color:#64748b;cursor:pointer}

  /* Grid thumbnail di dalam sel tabel */
/* 1 baris, bisa scroll kanan */
.thumb-row{
  display: flex;
  flex-wrap: nowrap;   /* jangan turun baris */
  gap: 6px;
  align-items: center;
  overflow-x: auto;    /* scroll horizontal */
  overflow-y: hidden;
  padding-bottom: 2px;
  scroll-behavior: smooth;
}

/* Tombol thumbnail (bukan link lagi) */
.thumb-row .thumb-modal-trigger{
  border: 0;
  padding: 0;
  margin: 0;
  background: transparent;
  cursor: pointer;
  flex: 0 0 auto;
}

/* Gambar di dalam tombol */
.thumb-row .thumb-modal-trigger img{
  width: 44px;
  height: 44px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
  border-radius: 2px;
}

/* Mode clip: sembunyikan item ke-7 dst */
.thumb-row.clip .thumb-modal-trigger:nth-child(n+7){
  display: none;
}

/* Tombol kecil di bawah baris */
.btn-xs{
  padding: 2px 8px;
  font-size: 12px;
  line-height: 1.4;
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
  border-radius: 2px;
  margin-top: 6px;
}
.btn-xs:hover{ background:#f3f4f6; }

/* (opsional) scrollbar lebih rapi */
.thumb-row::-webkit-scrollbar{height:8px}
.thumb-row::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:8px}
.thumb-row:hover::-webkit-scrollbar-thumb{background:#a8aeb7}
.td-aksi{ position: relative; overflow: visible; white-space: nowrap; }
.btn-icon{ display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0 }
@media (max-width: 767.98px){
  .dropdown-menu-right{ right:0; left:auto; }
}

/* === Spasi antar menu di dropdown Tom Select === */
.ts-wrapper.mult-select-tag .ts-dropdown .ts-dropdown-content{
  display: grid;           /* gampang bikin jarak antar item */
  row-gap: 8px;            /* JARAK antar menu */
  padding: 6px;            /* ruang tepi dropdown */
}

.ts-wrapper.mult-select-tag .ts-dropdown .option{
  padding: 8px 12px;       /* nyaman di-klik */
  border-radius: 4px;
}

/* highlight saat hover/aktif */
.ts-wrapper.mult-select-tag .ts-dropdown .option:hover,
.ts-wrapper.mult-select-tag .ts-dropdown .option.active{
  background: #f0fbfb;
}

/* (opsional) spasi tag yang sudah dipilih di input */
.ts-wrapper.mult-select-tag .ts-control .item{
  margin: 3px 6px 3px 0;
}

/* === Ukuran & tampilan modal Tambah Foto (lebih kecil) === */
#addFotoModal .modal-dialog{
  --bs-modal-width: 640px;
  margin: 0 auto;              /* biar benar-benar center dengan .modal-dialog-centered */
}


#addFotoModal .modal-content{
  border-radius: 8px;
  max-height: 60vh;            /* batasi tinggi total modal */
}

#addFotoModal .modal-header,
#addFotoModal .modal-footer{    /* kecilkan padding header/footer */
  padding: 10px 14px;
}

#addFotoModal .modal-body{      /* kecilkan padding body + scroll lokal */
  padding: 12px 14px;
  overflow-y: auto;
  /* tinggi body = total - header - footer kira2 */
  max-height: calc(72vh - 100px);
}

/* (opsional) lebih kecil lagi untuk kontrol Tom Select di modal */
#addFotoModal .ts-wrapper .ts-control{
  min-height: 34px;
  padding: 2px 6px;
}
#addFotoModal .ts-wrapper .ts-control .item{
  font-size: 12px;
  padding: 2px 6px;
  margin: 2px 4px 2px 0;
}
#addFotoModal .ts-wrapper .ts-dropdown{ font-size: 13px; }
#addFotoModal .ts-wrapper .ts-dropdown .option{ padding: 6px 8px; }

/* Preview foto kecil tetap memanjang satu baris */
/* Preview foto kecil: auto wrap ke baris berikutnya */
#addFotoModal .upload-preview{
  display: flex;
  flex-wrap: wrap;       /* <- semula nowrap */
  gap: 6px;
  padding: 6px 2px;
  margin-top: 6px;
  border: 1px dashed #e5e7eb;
  border-radius: 3px;
  background: #f9fafb;

  /* tinggi pas 2 baris thumb */
  --thumb: 32px;
  max-height: calc((var(--thumb) * 2) + 6px); /* 2 baris + gap */
  overflow-y: auto;       /* kalau lebih dari 2 baris, scroll vertikal tipis */
}

#addFotoModal .upload-preview a{
  width: 32px;
  height: 32px;
  display: block;
  flex: 0 0 auto;
}
#addFotoModal .upload-preview img{
  width: 100%; height: 100%;
  object-fit: cover;
  border: 1px solid #e5e7eb;
  border-radius: 2px;
}

/* Area preview */
#addFotoModal .upload-preview{
  display:flex; flex-wrap:wrap; gap:8px;
  padding:8px; margin-top:6px;
  border:1px dashed #e5e7eb; border-radius:3px; background:#f9fafb;
  max-height:120px; overflow:auto;
}

/* Kotak per thumbnail */
#addFotoModal .upload-preview .thumb{
  position:relative; width:48px; height:48px; flex:0 0 auto;
}
#addFotoModal .upload-preview .thumb img{
  width:100%; height:100%; object-fit:cover;
  border:1px solid #e5e7eb; border-radius:2px;
}

/* Tombol hapus X */
#addFotoModal .upload-preview .thumb-x{
  position:absolute; top:-6px; right:-6px;
  width:18px; height:18px; line-height:16px;
  border-radius:50%; border:1px solid #d1d5db; background:#fff;
  cursor:pointer; font-weight:bold; font-size:12px; padding:0;
}
/* TomSelect di modal Tambah Foto (NO SC) */
#addFotoCodesBox{ position:relative; }
#addFotoCodesBox .ts-control{
  min-height:44px;
  border-radius:2px;
  width:100%;
}
#addFotoCodesBox .ts-dropdown{
  position:absolute;
  top:100%;
  left:0;
  right:0;
  max-height:340px;
  overflow:auto;
  z-index:1065; /* di atas isi modal */
}
/* === Dropdown aksi 3 titik, disamakan dengan BAI === */
.dropdown-actions{
  position: relative;
}

/* Tombol 3 titik (abu-abu, bulat) */
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

/* kalau dropdown di-render di atas tombol */
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

/* Header mini di dalam dropdown */
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

/* Item aksi */
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




</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  /* === Tom Select: search utama === */
  const elSearch = document.getElementById('kode-search');
  let tsSearch = null;
  if (elSearch) {
    tsSearch = new TomSelect(elSearch, {
      plugins: { remove_button: {title: 'Hapus'} },
      placeholder: 'Ketik kode_foto lalu Enter…',
      maxOptions: 1000,
      persist: false,
      create: false,
      hideSelected: true,
      sortField: {field: 'text', direction: 'asc'}
    });
  }

  /* === Tom Select: multi-select No. SC di modal === */
    /* === Tom Select: multi-select NO SC di modal Tambah Foto === */
  const elSc   = document.getElementById('ms-kode-foto');
  const scBox  = document.getElementById('addFotoCodesBox');
  if (elSc && window.TomSelect) {
    new TomSelect(elSc, {
      plugins: { remove_button: { title: 'Hapus' } },
      placeholder: 'Ketik NO SC lalu Enter…',
      maxOptions: 4000,
      maxItems: 1,              // satu kode_foto per upload (seperti BAI)
      create: false,
      hideSelected: true,
      openOnFocus: true,
      closeAfterSelect: true,
      dropdownParent: scBox || document.body,
      sortField: { field: 'text', direction: 'asc' }
    });
  }


  /* === Tombol RESET filter utama === */
  const btnReset = document.getElementById('btnResetFilter');
  if (btnReset) {
    btnReset.addEventListener('click', function () {
      // bersihkan pilihan di TomSelect (UI)
      if (tsSearch) {
        tsSearch.clear();
      }
      // reset form (jaga-jaga)
      const form = document.getElementById('foto-form');
      if (form) form.reset();

      // reload ke path tanpa query -> balik ke halaman 1 & tanpa filter
      window.location.href = window.location.pathname;
    });
  }

  /* === Fallback buka modal (kalau data-bs-toggle tidak bekerja) === */
  const btn = document.getElementById('btnAddFoto');
  const modalEl = document.getElementById('addFotoModal');
  if (btn && modalEl) {
    btn.addEventListener('click', function () {
      if (window.bootstrap?.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      } else if (window.jQuery?.fn?.modal) {
        jQuery(modalEl).modal('show');
      }
    });
  }

   const MAX_FILES = 25;
  const input   = document.querySelector('#addFotoModal input[name="photos[]"]');
  const preview = document.getElementById('uploadPreview');
  if (!input || !preview) return;

  // simpan file di array => paling kompatibel; input.files selalu disinkronkan
  let filesArr = [];
  const keyOf = f => `${f.name}|${f.size}|${f.lastModified}`;

  function paint() {
    preview.innerHTML = '';
    filesArr.forEach(f => {
      const box = document.createElement('div');
      box.className = 'thumb';
      box.dataset.key = keyOf(f);

      const img = document.createElement('img');
      const url = URL.createObjectURL(f);
      img.src = url; img.alt = f.name;
      img.onload = () => URL.revokeObjectURL(url);

      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'thumb-x';
      del.title = 'Hapus';
      del.textContent = '×';

      box.appendChild(img);
      box.appendChild(del);
      preview.appendChild(box);
    });
  }

  function syncInput() {
    const dt = new DataTransfer();
    filesArr.forEach(f => dt.items.add(f));
    input.files = dt.files;
  }

  // tambah file tanpa mengganti
  input.addEventListener('change', (ev) => {
    const chosen = [...(ev.target.files || [])].filter(f => /^image\//.test(f.type));
    for (const f of chosen) {
      if (filesArr.length >= MAX_FILES) break;
      if (filesArr.some(g => keyOf(g) === keyOf(f))) continue; // skip duplikat
      filesArr.push(f);
    }
    syncInput();
    ev.target.value = ''; // biar pilih file yang sama tetap memicu 'change'
    paint();
  });

  // hapus per item (tombol ×)
  preview.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.thumb-x');
    if (!btn) return;
    const key = btn.parentElement.dataset.key;
    filesArr = filesArr.filter(f => keyOf(f) !== key);
    syncInput();
    paint();
  });

  // validasi submit
  const form = document.querySelector('#addFotoModal form');
  form?.addEventListener('submit', (ev) => {
    if (filesArr.length === 0) {
      ev.preventDefault(); alert('Pilih minimal 1 foto.'); return;
    }
    if (filesArr.length > MAX_FILES) {
      ev.preventDefault(); alert('Maksimum ' + MAX_FILES + ' foto per unggahan.'); return;
    }
    syncInput(); // pastikan input membawa file yang sudah di-accumulate
  });
  /* === Click-to-copy untuk kode di tabel === */
  document.querySelectorAll('.copy-wrap .copy-icon').forEach(function(el){
    el.addEventListener('click', function(){
      const parent = el.closest('.copy-wrap');
      const val = parent?.getAttribute('data-copy') || '';
      if (val) { navigator.clipboard.writeText(val); }
    });
  });

  /* === Toggle "Lihat semua" pada deretan foto di tabel === */
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('.thumb-toggle');
    if (!btn) return;
    ev.preventDefault();

    const id  = btn.getAttribute('data-target');
    const row = document.getElementById(id);
    if (!row) return;

    row.classList.toggle('clip');
    btn.textContent = row.classList.contains('clip') ? 'Lihat semua' : 'Tutup';
  });
    /* === Klik thumbnail -> buka modal preview === */
    /* === Klik thumbnail -> buka modal preview === */
  document.addEventListener('click', function (ev) {
    const link = ev.target.closest('.thumb-modal-trigger'); // <--- TIDAK pakai 'a.'
    if (!link) return;

    ev.preventDefault(); // nggak wajib untuk <button>, tapi aman

    const imgUrl = link.dataset.img || '';
    if (!imgUrl) return;

    const modalEl = document.getElementById('viewFotoModal');
    const imgEl   = document.getElementById('viewFotoImg');
    const dlEl    = document.getElementById('viewFotoDownload');

    if (imgEl) {
      imgEl.src = imgUrl;
    }
    if (dlEl) {
      dlEl.href = imgUrl;
      const fname = (imgUrl.split('/').pop() || '').split('?')[0] || 'foto.jpg';
      dlEl.setAttribute('download', fname);
    }

    efidiShowModal(modalEl);
  });


  /* === Tombol X di modal preview foto === */
  const viewCloseBtn = document.getElementById('viewFotoClose');
  if (viewCloseBtn) {
    viewCloseBtn.addEventListener('click', function () {
      efidiHideModal('viewFotoModal');
    });
  }

});
</script>

<!-- Modal Hapus Evidensi Foto -->
<div class="modal fade" id="hapusFotoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Hapus Evidensi Foto</h5>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup">
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
              Yakin akan menghapus semua foto untuk kode berikut?
            </p>
            <div id="hapusFotoLabel"
                 class="font-weight-bold"
                 style="font-size:13px;color:#111827;"></div>
          </div>
        </div>
        <p class="text-muted mb-0" style="font-size:12px;">
          Tindakan ini tidak dapat dibatalkan. Semua file foto di folder kode tersebut akan dihapus.
        </p>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" data-bs-dismiss="modal">
          Batal
        </button>
        <button type="button" class="btn btn-danger btn-sm" id="hapusFotoConfirm">
          <i class="mdi mdi-delete mr-1"></i> Hapus
        </button>
      </div>

    </div>
  </div>
</div>

<style>
  #hapusFotoModal .modal-dialog{
    max-width: 520px;
    width: 100%;
  }
  @media (max-width: 576px){
    #hapusFotoModal .modal-dialog{
      max-width: 100%;
      margin: 0 12px;
    }
  }
</style>
<script>
(function () {
  let deleteHref = null;

  // klik "Hapus Semua Foto" di dropdown
  document.addEventListener('click', function (ev) {
    const link = ev.target.closest('.foto-delete-trigger');
    if (!link) return;

    ev.preventDefault();
    deleteHref = link.getAttribute('href') || '#';

    const kode   = link.dataset.kode || '';
    const labelEl = document.getElementById('hapusFotoLabel');
    if (labelEl) {
      labelEl.textContent = kode ? ('Kode: ' + kode) : 'Kode ini';
    }

    // pakai helper modal yang sama
    efidiShowModal('hapusFotoModal');
  });

  // tombol "Hapus" di dalam modal
  window.addEventListener('load', function () {
    const btn = document.getElementById('hapusFotoConfirm');
    if (!btn) return;

    btn.addEventListener('click', function () {
      if (!deleteHref) return;

      efidiHideModal('hapusFotoModal');
      window.location.href = deleteHref; // tetap pakai GET ke hapus_foto.php
    });
  });

})();
</script>

<!-- Modal Preview Foto -->
<div class="modal fade" id="viewFotoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content foto-view-modal">

      <!-- Tombol X pojok kanan atas -->
      <button type="button"
              class="foto-view-close"
              id="viewFotoClose"
              aria-label="Tutup">
        ×
      </button>

      <!-- Isi gambar -->
      <div class="foto-view-body">
        <img id="viewFotoImg"
             src=""
             alt="Foto evidensi"
             class="img-fluid">
      </div>

      <!-- Footer: tombol download di kiri bawah -->
      <div class="foto-view-footer">
       <a id="viewFotoDownload"
   href="#"
   download
   class="btn btn-sm"
   style="background:#182958;color:#fff;border-color:#182958">
  Download
</a>

      </div>

    </div>
  </div>
</div>
<style>
/* ===== Modal preview foto (putih polos) ===== */
#viewFotoModal .modal-dialog{
  max-width: 900px;
  width: 100%;
}

.foto-view-modal{
  position: relative;
  background: #ffffff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 24px 60px rgba(15,23,42,.22);
  padding: 12px 12px 40px; /* ruang buat footer download di bawah */
}

/* Tombol X di pojok kanan atas */
.foto-view-close{
  position: absolute;
  top: 8px;
  right: 10px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid #e5e7eb;
  background: #ffffff;
  color: #111827;
  font-size: 18px;
  line-height: 24px;
  padding: 0;
  cursor: pointer;
  text-align: center;
  z-index: 5;
}
.foto-view-close:hover{
  background: #f3f4f6;
}

/* Area gambar */
.foto-view-body{
  padding-top: 12px;
  padding-bottom: 8px;
  text-align: center;
}
.foto-view-body img{
  max-height: 70vh;
  width: auto;
  height: auto;
}

/* Footer download di pojok kiri bawah */
.foto-view-footer{
  position: absolute;
  left: 12px;
  bottom: 8px;
}

/* Di layar kecil, modal menyesuaikan */
@media (max-width: 576px){
  #viewFotoModal .modal-dialog{
    max-width: 100%;
    margin: 0 10px;
  }
  .foto-view-modal{
    padding: 10px 10px 38px;
  }
}

</style>
<?php include "footer.php"; ?>
