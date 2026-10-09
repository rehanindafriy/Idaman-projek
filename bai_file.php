<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$isAjax = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';
if (!$isAjax) {
  include "header.php"; // enforce login + role (HANYA untuk halaman normal)
}
/* ================== UTIL ================== */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('nice_bytes')) {
  function nice_bytes(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = max(0, min($i, count($units)-1));
    $val = $bytes / (1024 ** $i);
    return rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') . ' ' . $units[$i];
  }
}

/* ================== PATHS ================== */
$BAI_FS_DIR   = realpath(__DIR__ . '/bot/file') ?: (__DIR__ . '/bot/file');
$BAI_FS_DIR   = rtrim($BAI_FS_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$BAI_URL_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/bot/file';

/* ============================================================
   AJAX ENDPOINT: TomSelect opsi NAMA FILE (mirip bai_options.php)
   URL: bai_file.php?ajax=1&term=...&page=1&limit=50
   ============================================================ */
if (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1') {
  // minimal auth (karena header.php belum di-include)
  $hasLogin = !empty($_SESSION['user']) || !empty($_SESSION['uid']) || !empty($_SESSION['role']);
  if (!$hasLogin) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['options'=>[], 'more'=>false], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $term  = trim((string)($_GET['term'] ?? ''));
  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = max(10, min(100, (int)($_GET['limit'] ?? 50)));
  $off   = ($page - 1) * $limit;

  $list = [];
  if (is_dir($BAI_FS_DIR)) {
    foreach (glob($BAI_FS_DIR . '*') ?: [] as $p) {
      if (!is_file($p)) continue;
      $bn = basename($p);
      if ($term !== '' && stripos($bn, $term) === false) continue;

      $list[] = [
        'name' => $bn,
        'mt'   => (int)@filemtime($p),
      ];
    }
  }

  // sort terbaru dulu (biar enak)
  usort($list, fn($a,$b) => ($b['mt'] <=> $a['mt']) ?: strnatcasecmp($a['name'], $b['name']));

  $slice = array_slice($list, $off, $limit);
  $more  = ($off + $limit) < count($list);

  header('Content-Type: application/json; charset=utf-8');
 echo json_encode([
  'options' => array_map(fn($x) => ['v' => $x['name']], $slice),
  'more'    => $more
], JSON_UNESCAPED_SLASHES);

  exit;
}

/* ================== PAGE AUTH ================== */


$role = strtolower((string)($role ?? ($_SESSION['user']['role'] ?? $_SESSION['role'] ?? '')));
$canDelete = in_array($role, ['admin','ta'], true);

/* ================== FLASH ================== */
$flashType = $_SESSION['ff_type'] ?? null;
$flashMsg  = $_SESSION['ff_msg']  ?? '';
unset($_SESSION['ff_type'], $_SESSION['ff_msg']);

/* ================== DELETE FILE ================== */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['delete_file'])) {
  if (!$canDelete) {
    $_SESSION['ff_type'] = 'danger';
    $_SESSION['ff_msg']  = 'Anda tidak punya akses untuk menghapus file.';
    header('Location: bai_file.php');
    exit;
  }

  $name = (string)($_POST['file_name'] ?? '');
  $name = str_replace("\0", '', $name);
  $name = trim($name);
  $name = basename($name);

  if ($name === '' || strpos($name, '..') !== false) {
    $_SESSION['ff_type'] = 'danger';
    $_SESSION['ff_msg']  = 'Nama file tidak valid.';
    header('Location: bai_file.php');
    exit;
  }

  $target = $BAI_FS_DIR . $name;

  $rp     = realpath($target);
  $baseRp = realpath($BAI_FS_DIR) ?: $BAI_FS_DIR;
  $baseRp = rtrim($baseRp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

  if (!$rp || strpos($rp, $baseRp) !== 0) {
    $_SESSION['ff_type'] = 'danger';
    $_SESSION['ff_msg']  = 'Akses file ditolak.';
    header('Location: bai_file.php');
    exit;
  }

  if (!is_file($rp)) {
    $_SESSION['ff_type'] = 'warning';
    $_SESSION['ff_msg']  = 'File tidak ditemukan.';
    header('Location: bai_file.php');
    exit;
  }

  if (@unlink($rp)) {
    $_SESSION['ff_type'] = 'success';
    $_SESSION['ff_msg']  = 'File berhasil dihapus: <strong>' . e($name) . '</strong>';
  } else {
    $_SESSION['ff_type'] = 'danger';
    $_SESSION['ff_msg']  = 'Gagal menghapus file (cek permission folder).';
  }

  header('Location: bai_file.php');
  exit;
}

/* ================== FILTER (MULTI SELECT FILE NAMES) ================== */
$selectedFilesRaw = $_GET['files'] ?? [];
if (!is_array($selectedFilesRaw)) $selectedFilesRaw = [];
$selectedFiles = [];
foreach ($selectedFilesRaw as $v) {
  $v = trim((string)$v);
  if ($v === '') continue;
  $selectedFiles[] = basename($v);
}
$selectedFiles = array_values(array_unique($selectedFiles));

$isSearch = isset($_GET['search']);

/* ================== LIST FILES ================== */
$page = max(1, (int)($_GET['page'] ?? 1));
$PER_PAGE = 60;

$MAP = [
  'pdf' => ['mdi-file-pdf-box', '#e53935'],
  'doc' => ['mdi-file-word', '#2b579a'],
  'docx'=> ['mdi-file-word', '#2b579a'],
  'xls' => ['mdi-file-excel', '#1d6f42'],
  'xlsx'=> ['mdi-file-excel', '#1d6f42'],
  'csv' => ['mdi-file-delimited', '#1d6f42'],
  'zip' => ['mdi-folder-zip', '#6b7280'],
  'rar' => ['mdi-folder-zip', '#6b7280'],
  '7z'  => ['mdi-folder-zip', '#6b7280'],
  'jpg' => ['mdi-file-image', '#8b5cf6'],
  'jpeg'=> ['mdi-file-image', '#8b5cf6'],
  'png' => ['mdi-file-image', '#8b5cf6'],
  'webp'=> ['mdi-file-image', '#8b5cf6'],
  'gif' => ['mdi-file-image', '#8b5cf6'],
];

$files = [];
if (is_dir($BAI_FS_DIR)) {
  $selSet = $selectedFiles ? array_flip($selectedFiles) : null;

  foreach (glob($BAI_FS_DIR . '*') ?: [] as $p) {
    if (!is_file($p)) continue;
    $bn  = basename($p);

    // kalau tombol search ditekan tapi tidak pilih apa-apa => tampilkan semua (dengan warning)
    // kalau pilih file => filter
    if ($selectedFiles && !$isSearch) {
      // kalau user hanya buka link yang sudah ada files[]=... tanpa search, tetap filter (lebih enak)
      if (!isset($selSet[$bn])) continue;
    } elseif ($selectedFiles && $isSearch) {
      if (!isset($selSet[$bn])) continue;
    }

    $ext = strtolower(pathinfo($bn, PATHINFO_EXTENSION));
    $files[] = [
      'name' => $bn,
      'ext'  => $ext,
      'size' => (int)@filesize($p),
      'mt'   => (int)@filemtime($p),
    ];
  }
}

usort($files, fn($a,$b) => ($b['mt'] <=> $a['mt']) ?: strnatcasecmp($a['name'], $b['name']));

$total = count($files);
$totalPages = max(1, (int)ceil($total / $PER_PAGE));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $PER_PAGE;
$show = array_slice($files, $offset, $PER_PAGE);

function build_qs(array $extra = []): string {
  $q = $_GET;
  foreach ($extra as $k=>$v) $q[$k] = $v;
  return http_build_query($q);
}

// alert seperti bai.php
$alertType = null;
$alertMsg  = '';
if ($isSearch) {
  if (!$selectedFiles) {
    $alertType = 'warning';
    $alertMsg  = 'Silakan pilih minimal 1 nama file.';
  } else {
    $alertType = $total ? 'success' : 'danger';
    $alertMsg  = $total
      ? 'Ditemukan <strong>'.number_format($total,0,',','.').'</strong> file untuk pilihan Anda.'
      : 'File tidak ditemukan untuk pilihan tersebut.';
  }
}


?>
<style>
/* ====== STYLE GRID (tetap minim radius & tanpa shadow berat) ====== */
.fm-sub{ color:#6b7280; font-size:.9rem; margin-bottom:0; }
.fm-wrap .card{ border:1px solid #e5e7eb; border-radius:6px; box-shadow:none !important; }
.fm-wrap .card-body{ padding:14px; }

.fm-toolbar{ display:flex; align-items:flex-end; justify-content:center; }
.fm-actions-row{ display:flex; align-items:flex-end; gap:8px; white-space:nowrap; }

.fm-btn{
  height:40px; border-radius:2px;
  padding:0 14px;
}
.fm-btn-primary{ background:#20a1ad; border:0; color:#fff; }
.fm-btn-primary:hover{ filter:brightness(.98); color:#fff; }
.fm-btn-reset{
  background:#e5e7eb;
  border:1px solid #d1d5db;
  color:#374151;
}
.fm-btn-reset:hover{ background:#d1d5db; border-color:#d1d5db; color:#111827; }

/* GRID 4 per baris */
.fm-grid{
  display:grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}
@media (max-width: 1300px){ .fm-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); } }
@media (max-width: 992px){ .fm-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 576px){ .fm-grid{ grid-template-columns: 1fr; } }

/* kartu */
.fm-item{
  position:relative;
  background:#fff;
  border:1px dotted #dfe3ea;
  border-radius:6px;
  overflow:hidden;
}
.fm-item:hover{ border-color:#20a1ad; }

/* area yang bisa di-klik utk view */
.fm-open{
  width:100%;
  display:block;
  text-align:left;
  padding:0;
  margin:0;
  border:0;
  background:transparent;
  cursor:pointer;
}

/* preview tengah */
.fm-preview{
  height: 140px;
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 8px;
}
.fm-prev-frame{
  width:100%;
  height:100%;
  border:0;
  border-radius:6px;
  background:#0b1220;
  overflow:hidden;
  pointer-events:none;
}
.fm-prev-img{
  width:100%;
  height:100%;
  object-fit:cover;
  border-radius:6px;
  pointer-events:none;
}
.fm-prev-icon{
  width:48px; height:48px;
  display:flex; align-items:center; justify-content:center;
  pointer-events:none;
}
.fm-prev-icon i{ font-size:44px; opacity:.95; }

/* footer */
.fm-meta{
  border-top:1px solid #eef2f7;
  padding: 10px 12px;
}
.fm-name-row{
  display:flex;
  align-items:center;
  gap:8px;
  min-width:0;
}
.fm-mini-ico{
  font-size:18px;
  line-height:1;
  flex:0 0 auto;
}
.fm-name{
  font-size:.92rem;
  font-weight:500;
  color:#111827;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.fm-subrow{
  display:flex;
  justify-content:space-between;
  gap:10px;
  margin-top:4px;
  font-size:.80rem;
  color:#6b7280;
}
.fm-subrow .fm-size b{ color:#2563eb; font-weight:500; }
.fm-subrow .fm-date{ color:#9ca3af; }

/* actions top-right (HANYA download + delete) */
.fm-actions{
  position:absolute;
  top:8px; right:8px;
  display:flex;
  gap:6px;
  z-index:2;
}
.fm-act{
  width:34px; height:34px;
  border-radius:6px;
  border:1px solid #e5e7eb;
  background:#fff;
  color:#111827;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  text-decoration:none;
}
.fm-act:hover{ background:#f9fafb; }
.fm-act.danger{
  border-color:#fecaca;
  background:#ef4444;
  color:#fff;
}
.fm-act.danger:hover{ filter:brightness(.98); }

/* ========= TomSelect (SAMAKAN PERSIS gaya bai.php) ========= */
#fileLockerWrap .ts-wrapper { width:100%; }
#fileLockerWrap .ts-control { width:100%; }

.ts-wrapper .ts-control{ min-height:44px; border-radius:2px; }
.ts-dropdown{ max-height:341px; overflow:auto; z-index:1061; }

.ts-wrapper.multi .ts-control{ gap:6px; flex-wrap:wrap; }
.ts-wrapper.multi .ts-control .item{
  margin:3px 4px 0 0;
  background:#e9f1ff; color:#1e5bb8;
  border:1px solid #cfe0ff;
  border-radius:8px;
  padding:6px 10px;
}
.ts-dropdown .dropdown-content{ padding:6px; }
.ts-dropdown .option{ padding:10px 12px; line-height:1.5; }
.ts-dropdown .option:not(:last-child){ border-bottom:1px solid #eef2f7; }

/* ========= Modal seperti bai.php ========= */
#fileDetail .modal-dialog{ max-width:none; width:fit-content; margin-left:auto; margin-right:auto; }
#fileDetail .modal-content{ width:max-content; }
#fileFullName{ color:#000; white-space:nowrap; }
@media (max-width: 992px){
  #fileDetail .modal-dialog, #fileDetail .modal-content{ max-width:95vw; }
  #fileFullName{ white-space:normal; word-break:break-word; }
}

#fileDetail .btn-row{ display:flex; flex-wrap:wrap; gap:.4rem; }
#fileDetail .btn-icon{ width:35px; height:35px; border-radius:.1rem; display:inline-flex; align-items:center; justify-content:center; }
#fileDetail .btn-icon i{ font-size:16px; }

#fileDetail .btn-swatch{
  background: var(--btn-bg, #0d6efd);
  border-color: var(--btn-bg, #0d6efd);
  color: var(--btn-text, #fff);
}
#fileDetail .btn-swatch:hover,
#fileDetail .btn-swatch:focus{
  background: var(--btn-hover, #0b5ed7);
  border-color: var(--btn-hover, #0b5ed7);
  color: var(--btn-text, #fff);
}

#fileDetail .file-row{ display:flex; align-items:flex-start; gap:.5rem; }
#fileDetail .file-chip{ text-decoration:none; color:#000; }
#fileDetail .file-badge{
  display:inline-flex; align-items:center; gap:.5rem;
  background:#eee; border:1px solid #ddd;
  padding:.35rem .75rem .35rem .5rem;
  border-radius:2px;
  max-width:520px;
}
#fileDetail .file-avatar{
  width:22px; height:22px;
  display:flex; align-items:center; justify-content:center;
  color:#6c757d;
}
#fileDetail .file-avatar i{ font-size:20px; line-height:1; display:block; color:currentColor; }
#fileDetail .file-name{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:460px; }
#fileDetail .file-size{ font-size:12px; color:#6c757d; margin-left:36px; }
@media (max-width: 992px){
  #fileDetail .file-badge{ max-width:70vw; }
  #fileDetail .file-name{ max-width:calc(70vw - 60px); }
}
/* ===== ACTIONS MUNCUL SAAT HOVER SAJA ===== */
.fm-actions{
  opacity:0;
  transform: translateY(-4px);
  pointer-events:none;
  transition: .15s ease;
}
.fm-item:hover .fm-actions{
  opacity:1;
  transform: translateY(0);
  pointer-events:auto;
}

</style>

<!-- TomSelect CDN (kalau sudah ada di header, tidak masalah) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<div class="main-panel fm-wrap">
  <div class="content-wrapper">

    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Berita Acara Instalasi File Manager</h2>
            <p class="fm-sub">Klik file untuk membuka detail (modal) seperti <code>bai.php</code>.</p>
          </div>

          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo"
                 class="mb-1" style="height:45px; margin-top:-10    px; display:block;">
            <br><br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n320">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
              <span class="text-primary crumb-mid">Berita Acara Instalasi File Manager</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($flashType): ?>
      <div class="row">
        <div class="col-12 grid-margin">
          <div class="alert alert-<?= e($flashType) ?> mb-0"><?= $flashMsg ?></div>
        </div>
      </div>
    <?php endif; ?>

    <!-- SEARCH (TomSelect multi seperti bai.php, berdasarkan NAMA FILE) -->
    <div class="row">
      <div class="col-12 grid-margin stretch-card">
        <div class="card">
          <div class="card-body d-flex align-items-center justify-content-center py4">
            <form class="forms-sample d-flex align-items-center mb-0 mx-auto w-100" method="GET" id="fileLockerForm">
              <div class="form-group flex-grow-1 mb-0">
                <label for="fileLocker">Cari Berdasarkan Nama File (Bisa lebih dari satu)</label>
                <div id="fileLockerWrap" class="position-relative" style="width:100%;">
                  <select name="files[]" id="fileLocker" multiple style="width:100%"></select>
                </div>
              </div>

              <div class="form-group ml-3 mb-0 fm-actions-row">
                <button type="submit" name="search" value="1" class="btn px-10 mt-4 fm-btn fm-btn-primary">
                  Search
                </button>
                <button type="button" id="btnResetFilter" class="btn px-10 mt-4 fm-btn fm-btn-reset" title="Reset filter">
                  <i class="mdi mdi-refresh" style="font-size:18px;"></i>
                </button>
              </div>
            </form>
          </div>

          <?php if ($isSearch && $alertType): ?>
          <div class="card-body pt-0 pb-3">
            <div class="alert alert-<?= e($alertType) ?> mb-0"
                 style="margin-top:0px; padding-top:.5rem; padding-bottom:.5rem;">
              <?= $alertMsg ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="card-body pt-0">
            <div class="small text-muted">
              Total tampil: <strong><?= number_format($total,0,',','.') ?></strong> file
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PAGER -->
    <?php if ($total > 0): ?>
    <div class="row">
      <div class="col-12 grid-margin">
        <div class="card">
          <div class="card-body d-flex justify-content-between align-items-center flex-wrap" style="gap:8px">
            <div class="text-muted small">
              Halaman <strong><?= (int)$page ?></strong> dari <?= (int)$totalPages ?>
              • tampil <?= number_format($offset+1,0,',','.') ?>–<?= number_format(min($offset+$PER_PAGE,$total),0,',','.') ?>
            </div>

            <div class="d-flex" style="gap:6px; flex-wrap:wrap;">
              <?php
                $mk = function(int $p, string $label, bool $dis=false){
                  $href = $dis ? 'javascript:void(0)' : ('?'.build_qs(['page'=>$p]));
                  $sty  = $dis ? 'style="opacity:.45;pointer-events:none"' : '';
                  return '<a class="fm-act" href="'.$href.'" '.$sty.'>'.$label.'</a>';
                };
                echo $mk(1, '<i class="mdi mdi-chevron-double-left"></i>', $page<=1);
                echo $mk(max(1,$page-1), '<i class="mdi mdi-chevron-left"></i>', $page<=1);
                echo $mk(min($totalPages,$page+1), '<i class="mdi mdi-chevron-right"></i>', $page>=$totalPages);
                echo $mk($totalPages, '<i class="mdi mdi-chevron-double-right"></i>', $page>=$totalPages);
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- GRID FILES -->
    <div class="row">
      <div class="col-12 grid-margin">
        <?php if (!$show): ?>
          <div class="card">
            <div class="card-body text-muted">Tidak ada file.</div>
          </div>
        <?php else: ?>
          <div class="fm-grid">
            <?php foreach ($show as $it):
              $name = (string)$it['name'];
              $ext  = strtolower((string)$it['ext']);
              $meta = $MAP[$ext] ?? ['mdi-file-outline', '#6b7280'];

              $url  = $BAI_URL_BASE . '/' . rawurlencode($name);
              $size = nice_bytes((int)$it['size']);
              $mt   = $it['mt'] ? date('M d, Y', (int)$it['mt']) : '-';

              $isImg = in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
              $isPdf = ($ext === 'pdf');
              $thumbPdfSrc = $url . '#toolbar=0&navpanes=0&scrollbar=0&page=1&view=FitH';
            ?>
              <div class="fm-item">
                <!-- actions top-right: DOWNLOAD + DELETE (TIDAK ADA VIEW) -->
                <div class="fm-actions">
                  <a class="fm-act fm-download" href="<?= e($url) ?>" download title="Download">
                    <i class="mdi mdi-download"></i>
                  </a>
                  <?php if ($canDelete): ?>
                    <button type="button"
                            class="fm-act danger btnDelFile"
                            data-name="<?= e($name) ?>"
                            title="Hapus">
                      <i class="mdi mdi-trash-can-outline"></i>
                    </button>
                  <?php endif; ?>
                </div>

                <!-- KLIK FILE => MODAL -->
                <button type="button"
                        class="fm-open fm-openFile"
                        data-name="<?= e($name) ?>"
                        data-url="<?= e($url) ?>"
                        data-ext="<?= e($ext) ?>"
                        data-size="<?= e($size) ?>"
                        title="Klik untuk detail">
                  <div class="fm-preview">
                    <?php if ($isImg): ?>
                      <img class="fm-prev-img" src="<?= e($url) ?>" alt="<?= e($name) ?>" loading="lazy">
                   <?php elseif ($isPdf): ?>
  <div class="fm-prev-wrap" aria-hidden="true">
    <iframe class="fm-prev-frame"
            src="<?= e($thumbPdfSrc) ?>"
            loading="lazy"
            title="<?= e($name) ?>"
            scrolling="no"></iframe>
  </div>
<?php else: ?>

                      <div class="fm-prev-icon" style="color:<?= e($meta[1]) ?>">
                        <i class="mdi <?= e($meta[0]) ?>"></i>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="fm-meta">
                    <div class="fm-name-row" title="<?= e($name) ?>">
                      <i class="mdi <?= e($meta[0]) ?> fm-mini-ico" style="color:<?= e($meta[1]) ?>"></i>
                      <div class="fm-name"><?= e($name) ?></div>
                    </div>
                    <div class="fm-subrow">
                      <div class="fm-size">Size: <b><?= e($size) ?></b></div>
                      <div class="fm-date"><?= e($mt) ?></div>
                    </div>
                  </div>
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

</div>

<!-- ================= MODAL PREVIEW FILE (PURE JS) ================= -->
<div id="fileModalCustom" class="pdf-modal" aria-hidden="true">
  <div class="pdf-modal-dialog">
    <div class="pdf-modal-header">
      <h5 id="fileModalTitleCustom" class="pdf-modal-title">File Preview</h5>

      <div style="display:flex; gap:8px; align-items:center;">
        <a id="fileModalOpenNew" class="btn btn-sm"
           style="height:32px; display:inline-flex; align-items:center; gap:6px;
                  border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#111827; text-decoration:none;"
           target="_blank" rel="noopener">
          <i class="mdi mdi-open-in-new"></i><span style="font-size:12px;">Open</span>
        </a>

        <a id="fileModalDownload" class="btn btn-sm"
           style="height:32px; display:inline-flex; align-items:center; gap:6px;
                  border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#111827; text-decoration:none;">
          <i class="mdi mdi-download"></i><span style="font-size:12px;">Download</span>
        </a>

        <button type="button" class="pdf-modal-close" onclick="closeFileModal()" aria-label="Tutup">
          &times;
        </button>
      </div>
    </div>

    <div class="pdf-modal-body" id="fileModalBodyCustom">
      <iframe id="fileFrameCustom" src="" frameborder="0" style="display:none;"></iframe>

      <img id="fileImgCustom" src="" alt=""
           style="display:none; width:100%; height:100%; object-fit:contain; background:#0b1220;" />

      <div id="fileOtherCustom"
           style="display:none; padding:16px; color:#111827;">
        <div style="display:flex; gap:10px; align-items:center;">
          <i class="mdi mdi-file-outline" style="font-size:28px; color:#6b7280;"></i>
          <div>
            <div id="fileOtherName" style="font-weight:600;"></div>
            <div id="fileOtherMeta" style="font-size:12px; color:#6b7280; margin-top:2px;"></div>
          </div>
        </div>
        <div style="margin-top:12px; font-size:12px; color:#6b7280;">
          Preview tidak tersedia untuk tipe file ini. Gunakan tombol <b>Open</b> atau <b>Download</b>.
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* ===== HILANGKAN SCROLLBAR PADA THUMB PDF (IFRAME) ===== */
.fm-prev-wrap{
  width:100%;
  height:100%;
  border-radius:6px;
  overflow:hidden;        /* ini yang “crop” scroll */
  background:#0b1220;     /* pindah background ke wrapper */
}

/* iframe dibuat sedikit lebih besar supaya scrollbar ke “keluar frame” */
.fm-prev-wrap > .fm-prev-frame{
  width: calc(100% + 18px);
  height: calc(100% + 18px);
  margin-right: -18px;
  margin-bottom: -18px;
  border:0;
  border-radius:0;        /* radius sudah di wrapper */
  background:transparent;
  overflow:hidden;
}

  /* Pakai style yang sama dengan pdfModalCustom kamu */
  .pdf-modal{
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.55);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px 12px;
  }
  .pdf-modal.show{ display:flex; }

  .pdf-modal-dialog{
    width: min(1100px, 100%);
    height: 90vh;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 20px 40px rgba(15,23,42,.35);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .pdf-modal-header{
    display:flex;
    align-items:center;
    justify-content: space-between;
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    gap: 10px;
  }
  .pdf-modal-title{
    margin:0;
    font-size: 14px;
    font-weight: 600;
    color:#111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 55vw;
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
  .pdf-modal-close:hover{ color:#111827; }

  .pdf-modal-body{
    flex: 1 1 auto;
    min-height: 0;
    padding: 0;
  }
  .pdf-modal-body iframe{
    width:100%;
    height:100%;
    border:0;
    display:block;
  }
  
  /* TomSelect: jangan singkat nama file */
.ts-fullname{
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
}

/* chip selected juga jangan ellipsis */
.ts-wrapper.multi .ts-control .item{
  white-space: normal !important;
  max-width: 100% !important;
  overflow: visible !important;
  text-overflow: unset !important;
}
.ts-dropdown .option{
  white-space: normal !important;
  overflow-wrap: anywhere;
}

/* =========================
   A. SEARCH BUTTON: tetap TOSCA saat hover/klik/focus
   ========================= */
.fm-btn-primary,
.fm-btn-primary:hover,
.fm-btn-primary:focus,
.fm-btn-primary:active,
.fm-btn-primary:focus-visible{
  background:#20a1ad !important;
  border-color:#20a1ad !important;
  color:#fff !important;
  box-shadow:none !important;
  outline:none !important;
}
.fm-btn-primary:active{ filter:brightness(.98) !important; }

/* =========================
   B. ALERT: kasih jarak dari form di atas
   ========================= */
.fm-alert-gap{
  margin-top:12px;   /* jarak ke form */
}

/* =========================
   C. NAMA FILE: jangan dipotong (CARD)
   ========================= */
.fm-name{
  white-space: normal !important;
  overflow: visible !important;
  text-overflow: unset !important;
  overflow-wrap: anywhere !important;
  word-break: break-word !important;
  line-height: 1.25;
}

/* biar row-nya tetap rapi walau teks panjang */
.fm-name-row{ align-items:flex-start; }

/* =========================
   D. NAMA FILE: jangan dipotong (TITLE MODAL PREVIEW CUSTOM)
   ========================= */
.pdf-modal-title{
  white-space: normal !important;
  overflow: visible !important;
  text-overflow: unset !important;
  max-width: 70vw !important;
  line-height: 1.25;
}

/* =========================
   E. Pastikan tombol aksi (delete) selalu di atas klik card
   ========================= */
.fm-actions{ z-index: 20 !important; }
.fm-open{ position:relative; z-index:1; }

/* Touch device: ikon aksi tetap bisa ditekan */
@media (hover: none){
  .fm-actions{
    opacity:1 !important;
    transform:none !important;
    pointer-events:auto !important;
  }
}

</style>


<!-- MODAL DELETE -->
<div class="modal fade" id="delFileModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content" style="border-radius:8px; overflow:hidden;">
      <div class="modal-header">
        <h6 class="modal-title mb-0">Hapus File</h6>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup">

          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="text-muted" style="font-size:.9rem">Yakin ingin menghapus file ini?</div>
        <div class="mt-2 p-2" style="border:1px solid #eef2f7;border-radius:6px;background:#f9fafb;">
          <strong id="delFileNameLabel"></strong>
        </div>
        <div class="text-muted mt-2" style="font-size:.8rem">Tindakan ini tidak bisa dibatalkan.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Batal</button>

        <form method="POST" class="m-0 p-0">
          <input type="hidden" name="file_name" id="delFileName">
          <button type="submit" name="delete_file" class="btn btn-danger btn-sm">
            <i class="mdi mdi-trash-can-outline mr-1"></i> Hapus
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  if(!window.TomSelect) return;

  const PRESELECT = <?= json_encode($selectedFiles, JSON_UNESCAPED_SLASHES) ?>;
  const el   = document.getElementById('fileLocker');
  const wrap = document.getElementById('fileLockerWrap');
  if (!el) return;

  let page = 1, lastQuery = '', hasMore = true, loading = false;

  const ts = new TomSelect(el, {
    maxItems: null,
    valueField: 'v',
    labelField: 'v',
    searchField: ['v'],
    placeholder: 'Ketik nama file…',
    plugins: ['remove_button'],
    preload: true,              // penting: langsung load saat focus
    openOnFocus: true,
    closeAfterSelect: false,
    hideSelected: true,
    dropdownParent: wrap || document.body,
    loadThrottle: 250,

    load: function(q, cb){
      if (loading) return;
      q = (q || '').trim();

      if (q !== lastQuery){
        page = 1;
        hasMore = true;
        this.clearOptions();
      }
      lastQuery = q;
      if (!hasMore) return cb();

      loading = true;

      fetch('bai_file.php?ajax=1&term=' + encodeURIComponent(q) + '&page=' + page + '&limit=50', {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
      .then(async (r) => {
        try { return await r.json(); }
        catch(e){ return {options:[], more:false}; }
      })
      .then((d) => {
  const raw = Array.isArray(d.options) ? d.options : [];
  const list = raw.map(x => {
    if (typeof x === 'string') return { v: x };
    return { v: String(x && (x.v ?? x.name ?? '') || '') };
  }).filter(o => o.v);

  hasMore = !!d.more;
  page += 1;
  cb(list);
})

      .catch(() => cb())
      .finally(() => loading = false);
    },

    render: {
      option: (d, esc) => `<div class="ts-fullname">${esc(d.v || '')}</div>`,
      item  : (d, esc) => `<div class="ts-fullname">${esc(d.v || '')}</div>`
    }
  });

  // infinite scroll
  ts.on('dropdown_open', () => {
    setTimeout(() => {
      const c = ts.dropdown_content;
      if (!c) return;
      c.onscroll = function(){
        if (c.scrollTop + c.clientHeight >= c.scrollHeight - 10) {
          ts.load(lastQuery, () => {});
        }
      };
    }, 0);
  });

  // preselect dari URL
  if (Array.isArray(PRESELECT) && PRESELECT.length){
    PRESELECT.forEach(v => ts.addOption({v:String(v)}));
    PRESELECT.forEach(v => ts.addItem(String(v), true));
  }

  // reset
  const btnReset = document.getElementById('btnResetFilter');
  if (btnReset){
    btnReset.addEventListener('click', function(){
      ts.clear();
      const form = document.getElementById('fileLockerForm');
      if (form) form.reset();
      window.location.href = window.location.pathname;
    });
  }
})();
</script>

<script>
  function openFileModal(btn){
    const name = String(btn.getAttribute('data-name') || '');
    const url  = String(btn.getAttribute('data-url')  || '');
    const ext  = String(btn.getAttribute('data-ext')  || '').toLowerCase();
    const size = String(btn.getAttribute('data-size') || '');

    const overlay = document.getElementById('fileModalCustom');
    const titleEl = document.getElementById('fileModalTitleCustom');

    const frame   = document.getElementById('fileFrameCustom');
    const img     = document.getElementById('fileImgCustom');
    const other   = document.getElementById('fileOtherCustom');

    const otherName = document.getElementById('fileOtherName');
    const otherMeta = document.getElementById('fileOtherMeta');

    const openNew = document.getElementById('fileModalOpenNew');
    const dlBtn   = document.getElementById('fileModalDownload');

    if (!overlay) return;

    if (openNew) openNew.href = url;
    if (dlBtn){
      dlBtn.href = url;
      dlBtn.setAttribute('download', name || 'download');
    }

    if (frame){ frame.style.display='none'; frame.src=''; }
    if (img){ img.style.display='none'; img.src=''; img.alt=''; }
    if (other){ other.style.display='none'; }

    if (titleEl) titleEl.textContent = name || 'File Preview';

    const isImg = ['jpg','jpeg','png','webp','gif'].includes(ext);
    const isPdf = (ext === 'pdf');

    if (isPdf && frame){
      let viewerUrl = url;
      if (viewerUrl.indexOf('#') === -1) viewerUrl += '#zoom=page-fit';
      else viewerUrl += '&zoom=page-fit';
      frame.src = viewerUrl;
      frame.style.display = 'block';
    } else if (isImg && img){
      img.src = url;
      img.alt = name;
      img.style.display = 'block';
    } else {
      if (other){
        if (otherName) otherName.textContent = name || '-';
        if (otherMeta) otherMeta.textContent = (ext ? ext.toUpperCase() : 'FILE') + (size ? (' • ' + size) : '');
        other.style.display = 'block';
      }
    }

    overlay.classList.add('show');
  }

  function closeFileModal(){
    const overlay = document.getElementById('fileModalCustom');
    const frame   = document.getElementById('fileFrameCustom');
    const img     = document.getElementById('fileImgCustom');

    if (overlay) overlay.classList.remove('show');
    if (frame) frame.src = '';
    if (img) img.src = '';
  }

  // klik area gelap = tutup
  document.addEventListener('click', function(e){
    const overlay = document.getElementById('fileModalCustom');
    if (overlay && overlay.classList.contains('show') && e.target === overlay){
      closeFileModal();
    }
  });

  // ESC = tutup
  document.addEventListener('keyup', function(e){
    if (e.key === 'Escape') closeFileModal();
  });

  // SATU DELEGATION: download / delete / open modal
  document.addEventListener('click', function(e){
    // download (ikon pojok) => jangan buka modal
    if (e.target.closest('.fm-download')) {
      e.stopPropagation();
      return;
    }

  

    // klik kartu => modal
    const openBtn = e.target.closest('.fm-openFile');
    if (openBtn){
      e.preventDefault();
      openFileModal(openBtn);
    }
  });
</script>
<script>
(function(){
  const modalBackdrops = new Map();

  function hasBS5(){
    return !!(window.bootstrap && bootstrap.Modal);
  }
  function hasBS4(){
    return !!(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function');
  }

  // Fallback jika Bootstrap JS tidak ada: tampilkan modal bootstrap via DOM
  function fallbackShow(modalEl){
    if (!modalEl) return;
    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal','true');

    document.body.classList.add('modal-open');

    const bd = document.createElement('div');
    bd.className = 'modal-backdrop fade show';
    document.body.appendChild(bd);
    modalBackdrops.set(modalEl, bd);
  }

  function fallbackHide(modalEl){
    if (!modalEl) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden','true');

    document.body.classList.remove('modal-open');

    const bd = modalBackdrops.get(modalEl);
    if (bd) { bd.remove(); modalBackdrops.delete(modalEl); }
  }

  function showDelModal(){
    const modalEl = document.getElementById('delFileModal');
    if (!modalEl) return;

    if (hasBS5()){
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }
    if (hasBS4()){
      window.jQuery(modalEl).modal('show');
      return;
    }
    fallbackShow(modalEl);
  }

  function hideAnyModalIfFallback(target){
    const modalEl = target.closest('.modal');
    if (!modalEl) return;

    if (!hasBS5() && !hasBS4()){
      fallbackHide(modalEl);
    }
  }

  // Close fallback modal saat klik tombol close / batal
  document.addEventListener('click', function(e){
    const closeBtn = e.target.closest('[data-dismiss="modal"], [data-bs-dismiss="modal"]');
    if (closeBtn){
      e.preventDefault();
      hideAnyModalIfFallback(closeBtn);
      return;
    }

    // klik backdrop modal (area gelap bootstrap modal) => tutup fallback
    if (e.target.classList && e.target.classList.contains('modal')){
      hideAnyModalIfFallback(e.target);
    }
  });

  // SATU handler utama: download / delete / open preview
  document.addEventListener('click', function(e){

    // klik download pojok: jangan buka modal apa pun
    if (e.target.closest('.fm-download')) {
      e.stopPropagation();
      return;
    }

    // klik tombol hapus => modal delete
    const delBtn = e.target.closest('.btnDelFile');
    if (delBtn){
      e.preventDefault();
      e.stopPropagation();

      const name = String(delBtn.getAttribute('data-name') || '');
      const inp  = document.getElementById('delFileName');
      const lbl  = document.getElementById('delFileNameLabel');

      if (inp) inp.value = name;
      if (lbl) lbl.textContent = name;

      showDelModal();
      return;
    }

    // klik kartu => modal preview custom
    const openBtn = e.target.closest('.fm-openFile');
    if (openBtn){
      e.preventDefault();
      openFileModal(openBtn);
      return;
    }
  }, true); // capture=true biar menang dari listener lain (kalau masih ada)
})();
</script>



<?php include "footer.php"; ?>
