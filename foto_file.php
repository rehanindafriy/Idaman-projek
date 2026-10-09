<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

include "header.php";

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// role
$role = strtolower((string)($role ?? ($_SESSION['user']['role'] ?? $_SESSION['role'] ?? '')));
$canDelete = in_array($role, ['admin','ta'], true);

// lokasi foto (FS + URL)
$FOTO_FS_DIR   = realpath(__DIR__ . '/bot/foto') ?: (__DIR__ . '/bot/foto');
$FOTO_FS_DIR   = rtrim($FOTO_FS_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$FOTO_URL_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/bot/foto';

function is_inside(string $baseDir, string $path): bool {
  $base = realpath($baseDir) ?: $baseDir;
  $rp = realpath($path);
  if (!$rp) return false;
  $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  return strpos($rp, $base) === 0;
}
function rrmdir(string $dir): bool {
  if (!is_dir($dir)) return false;
  $items = scandir($dir);
  if ($items === false) return false;
  foreach ($items as $it) {
    if ($it === '.' || $it === '..') continue;
    $p = $dir . DIRECTORY_SEPARATOR . $it;
    if (is_dir($p)) rrmdir($p);
    else @unlink($p);
  }
  return @rmdir($dir);
}

function is_image_name(string $name): bool {
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
}

function count_images_fast(string $dir): int {
  if (!is_dir($dir)) return 0;
  $n = 0;
  try{
    foreach (new DirectoryIterator($dir) as $it){
      if ($it->isDot() || !$it->isFile()) continue;
      if (is_image_name($it->getFilename())) $n++;
    }
  } catch (Throwable $e){
    return 0;
  }
  return $n;
}

function list_images_fast(string $dir, string $q=''): array {
  $out = [];
  if (!is_dir($dir)) return $out;

  $q = trim($q);
  try{
    foreach (new DirectoryIterator($dir) as $it){
      if ($it->isDot() || !$it->isFile()) continue;

      $bn = $it->getFilename();
      if (!is_image_name($bn)) continue;
      if ($q !== '' && stripos($bn, $q) === false) continue;

      $out[] = [
        'file' => $bn,
        'mt'   => (int)$it->getMTime(),
      ];
    }
  } catch (Throwable $e){
    return [];
  }

  usort($out, fn($a,$b)=> ($b['mt'] <=> $a['mt']) ?: strnatcasecmp($a['file'], $b['file']));
  return $out;
}

function build_qs(array $extra = []): string {
  $q = $_GET;
  foreach ($extra as $k=>$v) $q[$k] = $v;
  return http_build_query($q);
}

// mode
$kode = (string)($_GET['kode'] ?? '');
$kode = str_replace("\0", '', $kode);
$kode = trim($kode);
$kode = $kode !== '' ? basename($kode) : '';

$q = trim((string)($_GET['q'] ?? ''));

// =======================
// DOWNLOAD ALL (ZIP) HANDLER
// =======================
if (isset($_GET['download_all']) && (string)$_GET['download_all'] === '1') {
  // minimal auth (karena header.php belum tentu aman untuk output)
  $hasLogin = !empty($_SESSION['user']) || !empty($_SESSION['uid']) || !empty($_SESSION['role']);
  if (!$hasLogin) {
    http_response_code(403);
    exit('Forbidden');
  }

  $k = basename(trim((string)($_GET['kode'] ?? '')));
  if ($k === '') { http_response_code(400); exit('Kode folder tidak valid'); }

  $dir = $FOTO_FS_DIR . $k . DIRECTORY_SEPARATOR;
  if (!is_dir($dir) || !is_inside($FOTO_FS_DIR, $dir)) {
    http_response_code(404);
    exit('Folder tidak ditemukan');
  }

  if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive tidak tersedia di server.');
  }

  @set_time_limit(0);

  $tmp = tempnam(sys_get_temp_dir(), 'efidi_foto_');
  if ($tmp === false) { http_response_code(500); exit('Gagal membuat file sementara'); }
  $zipPath = $tmp . '.zip';
  @rename($tmp, $zipPath);

  $zip = new ZipArchive();
  if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($zipPath);
    http_response_code(500);
    exit('Gagal membuka ZIP');
  }

  $files = list_images_fast($dir, ''); // all images, sorted
  foreach ($files as $it) {
    $fn = $it['file'];
    $full = $dir . $fn;
    if (is_file($full) && is_inside($FOTO_FS_DIR, $full)) {
      $zip->addFile($full, $fn);
    }
  }
  $zip->close();

  $dlName = $k . '_photos.zip';

  // bersihkan buffer output
  while (ob_get_level() > 0) { @ob_end_clean(); }

  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="'.str_replace('"','',$dlName).'"');
  header('Content-Length: ' . (string)filesize($zipPath));
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');

  readfile($zipPath);
  @unlink($zipPath);
  exit;
}

// include header setelah semua endpoint yang output-nya non-HTML

// flash
$flashType = $_SESSION['pf_type'] ?? null;
$flashMsg  = $_SESSION['pf_msg']  ?? '';
unset($_SESSION['pf_type'], $_SESSION['pf_msg']);

// pagination (folder manager saja)
$page = max(1, (int)($_GET['page'] ?? 1));
$PER_PAGE = 100;

// ===== DELETE HANDLER =====
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!$canDelete) {
    $_SESSION['pf_type'] = 'danger';
    $_SESSION['pf_msg']  = 'Anda tidak punya akses untuk menghapus.';
    header('Location: foto_file.php' . ($kode ? ('?kode='.rawurlencode($kode)) : ''));
    exit;
  }

  // delete folder
  if (isset($_POST['delete_folder'])) {
    $k = basename(trim((string)($_POST['kode'] ?? '')));
    if ($k === '') {
      $_SESSION['pf_type'] = 'danger';
      $_SESSION['pf_msg']  = 'Kode folder tidak valid.';
      header('Location: foto_file.php');
      exit;
    }
    $dir = $FOTO_FS_DIR . $k;
    if (!is_dir($dir) || !is_inside($FOTO_FS_DIR, $dir)) {
      $_SESSION['pf_type'] = 'danger';
      $_SESSION['pf_msg']  = 'Folder tidak ditemukan / akses ditolak.';
      header('Location: foto_file.php');
      exit;
    }

    if (rrmdir($dir)) {
      $_SESSION['pf_type'] = 'success';
      $_SESSION['pf_msg']  = 'Folder berhasil dihapus: <strong>'.e($k).'</strong>';
    } else {
      $_SESSION['pf_type'] = 'danger';
      $_SESSION['pf_msg']  = 'Gagal menghapus folder (cek permission).';
    }
    header('Location: foto_file.php');
    exit;
  }

  // delete photo
  if (isset($_POST['delete_photo'])) {
    $k = basename(trim((string)($_POST['kode'] ?? '')));
    $f = basename(trim((string)($_POST['file'] ?? '')));
    if ($k === '' || $f === '') {
      $_SESSION['pf_type'] = 'danger';
      $_SESSION['pf_msg']  = 'Data hapus foto tidak valid.';
      header('Location: foto_file.php' . ($kode ? ('?kode='.rawurlencode($kode)) : ''));
      exit;
    }

    $full = $FOTO_FS_DIR . $k . DIRECTORY_SEPARATOR . $f;
    if (!is_file($full) || !is_inside($FOTO_FS_DIR, $full)) {
      $_SESSION['pf_type'] = 'danger';
      $_SESSION['pf_msg']  = 'Foto tidak ditemukan / akses ditolak.';
      header('Location: foto_file.php?kode=' . rawurlencode($k));
      exit;
    }

    if (@unlink($full)) {
      $_SESSION['pf_type'] = 'success';
      $_SESSION['pf_msg']  = 'Foto berhasil dihapus: <strong>'.e($f).'</strong>';
    } else {
      $_SESSION['pf_type'] = 'danger';
      $_SESSION['pf_msg']  = 'Gagal menghapus foto (cek permission).';
    }

    header('Location: foto_file.php?kode=' . rawurlencode($k));
    exit;
  }
}

// =======================
// DATA BUILD
// =======================
$foldersMeta = [];
$images = [];

if ($kode === '') {
  // list folder (kode_foto)
  foreach (glob($FOTO_FS_DIR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
    $bn = basename($dir);
    if ($q !== '' && stripos($bn, $q) === false) continue;

    $foldersMeta[] = [
      'kode' => $bn,
      'mt'   => (int)@filemtime($dir),
      'dir'  => $dir,
    ];
  }

  usort($foldersMeta, fn($a,$b) => ($b['mt'] <=> $a['mt']) ?: strnatcasecmp($a['kode'], $b['kode']));

  $total = count($foldersMeta);
  $totalPages = max(1, (int)ceil($total / $PER_PAGE));
  if ($page > $totalPages) $page = $totalPages;

  $offset = ($page - 1) * $PER_PAGE;
  $slice = array_slice($foldersMeta, $offset, $PER_PAGE);

  // hitung count hanya untuk halaman yang tampil (lebih ringan)
  $showFolders = [];
  foreach ($slice as $fd) {
    $dir = rtrim((string)$fd['dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $showFolders[] = [
      'kode'  => (string)$fd['kode'],
      'mt'    => (int)$fd['mt'],
      'count' => count_images_fast($dir),
    ];
  }

  $fromN = $total ? ($offset + 1) : 0;
  $toN   = $total ? min($offset + $PER_PAGE, $total) : 0;

} else {
  // inside folder
  $dir = $FOTO_FS_DIR . $kode . DIRECTORY_SEPARATOR;
  if (!is_dir($dir) || !is_inside($FOTO_FS_DIR, $dir)) {
    $kode = '';
  } else {
    $raw = list_images_fast($dir, $q);
    foreach ($raw as $it) {
      $bn = (string)$it['file'];
      $images[] = [
        'file' => $bn,
        'url'  => $FOTO_URL_BASE . '/' . rawurlencode($kode) . '/' . rawurlencode($bn),
        'mt'   => (int)$it['mt'],
      ];
    }
  }
  $total = count($images);
}

?>
<style>
:root{
  --acc:#20a1ad;
  --text:#0f172a;
  --muted:#6b7280;
  --line:#e5e7eb;
  --soft:#f8fafc;
}

.ef-sub{ color:var(--muted); font-size:.9rem; margin:0; }
.ef-card{ border:1px solid var(--line); border-radius:0; box-shadow:none !important; background:#fff; }
.ef-card .card-body{ padding:14px; }

/* ==== CARD RADIUS 5px ==== */
.ef-card{
  border:1px solid var(--line);
  border-radius:5px;           /* dari 0 -> 5 */
  box-shadow:none !important;
  background:#fff;
}

.ef-item{
  border:1px solid var(--line);
  border-radius:5px;           /* dari 0 -> 5 */
  background:#fff;
  overflow:hidden;             /* biar radius rapih */
  position:relative;
  transition: background .12s ease, border-color .12s ease;
}

/* optional: pager card juga ikut radius 5 karena pakai .ef-card */

/* ==== KURANGI BOLD YANG TERLALU TEBAL ==== */
.ef-title{
  font-weight:400;             /* dari 800 -> 600 */
  color:var(--text);
  font-size:.92rem;
  line-height:1.25;
}

.ef-stat b{
  font-weight:400;             /* dari 800 -> 600 */
}

.ef-photo-foot b{
  font-weight:400;             /* dari 800 -> 600 */
}

/* kalau judul besar H2 masih terlalu tebal */
.ef-topline h2{
  font-weight:400;             /* default bootstrap sering 700 */
}

/* OPTIONAL: kalau <strong> juga kebanyakan tebal, batasi di area ini saja */
.content-wrapper strong{
  font-weight:400;
}

.ef-searchbar{ display:flex; gap:.5rem; align-items:end; flex-wrap:wrap; }
.ef-searchbar .form-control{ height:40px; border-radius:0; }
.ef-btn{ height:40px; border-radius:0; padding:0 14px; }
.ef-btn-primary{
  background:var(--acc) !important;
  border:1px solid var(--acc) !important;
  color:#fff !important;
  box-shadow:none !important;
}
.ef-btn-primary:hover,
.ef-btn-primary:focus,
.ef-btn-primary:active{
  background:var(--acc) !important;
  border-color:var(--acc) !important;
  color:#fff !important;
  box-shadow:none !important;
  outline:none !important;
  filter:brightness(.98);
}
.ef-btn-soft{
  background:var(--soft);
  border:1px solid var(--line);
  color:var(--text);
}
.ef-btn-soft:hover{ background:#f1f5f9; border-color:#dbe3ee; color:var(--text); }

.ef-topline{
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}
.ef-stat{ font-size:12px; color:var(--muted); }
.ef-stat b{ color:var(--text); font-weight:500; }

.ef-grid{
  display:grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}
@media (max-width: 1200px){ .ef-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); } }
@media (max-width: 768px){ .ef-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 576px){ .ef-grid{ grid-template-columns: 1fr; } }

.ef-item{
  border:1px solid var(--line);
  border-radius:0;
  background:#fff;
  overflow:hidden;
  position:relative;
  transition: background .12s ease, border-color .12s ease;
}
.ef-item:hover{
  background:#fbfdff;
  border-color: rgba(32,161,173,.55);
}

/* header row */
.ef-row{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
  padding: 12px 12px 10px 12px;
}
.ef-main{
  min-width:0;
  text-decoration:none;
  color:inherit;
  display:flex;
  gap:10px;
  align-items:flex-start;
}
.ef-ico{
  width:38px; height:38px;
  border:0 !important;              /* HAPUS BORDER */
  background:transparent !important; /* HAPUS BACKGROUND */
  border-radius:0 !important;
  display:flex; align-items:center; justify-content:center;
  color:#9ca3af !important;         /* ICON ABU-ABU */
  flex:0 0 auto;
}
.ef-ico i{ font-size:22px; }

.ef-title{
  font-weight:500;
  color:var(--text);
  font-size:.92rem;
  line-height:1.25;
  white-space:normal;
  overflow:visible;
  text-overflow:unset;
  overflow-wrap:anywhere;
  word-break:break-word;
}
.ef-meta{
  margin-top:4px;
  font-size:.78rem;
  color:var(--muted);
  line-height:1.2;
}

.ef-actions{
  display:flex;
  gap:6px;
  flex:0 0 auto;
  opacity:0;
  transform: translateX(4px);
  transition: .12s ease;
}
.ef-item:hover .ef-actions{ opacity:1; transform: translateX(0); }

.ef-icbtn{
  width:34px; height:34px;
  border-radius:0;
  display:inline-flex; align-items:center; justify-content:center;
  border: 1px solid var(--line);
  background:#fff;
  color:var(--text);
  text-decoration:none;
}
.ef-icbtn:hover{ background:#f3f4f6; }
.ef-icbtn.danger{ color:#b91c1c; border-color:#f3b7b7; }
.ef-icbtn.danger:hover{ background:#fff1f1; }

/* photo thumb */
.ef-photo-thumb{ border-top:1px solid var(--line); background:#0b1220; }
.ef-photo-thumb img{
  width:100%;
  height:180px;
  object-fit:cover;
  display:block;
}
.ef-photo-foot{
  border-top:1px solid var(--line);
  padding:10px 12px;
  font-size:.78rem;
  color:var(--muted);
  display:flex;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}
.ef-photo-foot b{ color:var(--text); font-weight:500; }

/* pager (folder mode only) */
.ef-pager{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}
.ef-pbtn{
  width:34px; height:34px;
  border:1px solid var(--line);
  background:#fff;
  color:var(--text);
  display:inline-flex;
  align-items:center;
  justify-content:center;
  text-decoration:none;
  border-radius:0;
}
.ef-pbtn:hover{ background:#f8fafc; }
.ef-pbtn.disabled{ opacity:.45; pointer-events:none; }
.ef-main{
  min-width:0;
  color:inherit;
  display:flex;
  gap:10px;
  align-items:flex-start;
  text-decoration:none;
}


/* modal flatten */
.modal-content{ border-radius:0 !important; box-shadow:none !important; }
</style>

<div class="main-panel">
  <div class="content-wrapper">

    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="ef-topline">
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1"><?= $kode ? 'Images Manager' : 'Folders Manager' ?></h2>
            <p class="ef-sub">
              <?= $kode
                ? 'Folder: <code>'.e($kode).'</code> (bot/foto/'.e($kode).'/)'
                : 'Menampilkan folder fisik dari <code>bot/foto/</code> (berdasarkan kode_foto).'
              ?>
            </p>
          </div>

          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo"
                 class="mb-1" style="height:45px; margin-top:-10px; display:block;">
            <br><br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
              <span class="text-primary crumb-mid"><?= $kode ? 'Images' : 'Folders' ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($flashType): ?>
      <div class="row">
        <div class="col-12 grid-margin">
          <div class="alert alert-<?= e($flashType) ?> mb-0" style="margin-top:10px;"><?= $flashMsg ?></div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Search Card -->
    <!--<div class="row">-->
    <!--  <div class="col-12 grid-margin stretch-card">-->
    <!--    <div class="card ef-card">-->
    <!--      <div class="card-body">-->
    <!--        <form method="GET" class="ef-searchbar">-->
    <!--          <?php if ($kode): ?>-->
    <!--            <input type="hidden" name="kode" value="<?= e($kode) ?>">-->
    <!--          <?php endif; ?>-->

    <!--          <div class="flex-grow-1" style="min-width:260px;">-->
    <!--            <label class="mb-1"><?= $kode ? 'Cari nama foto' : 'Cari kode_foto (nama folder)' ?></label>-->
    <!--            <input type="text" class="form-control" name="q" value="<?= e($q) ?>"-->
    <!--                   placeholder="<?= $kode ? 'contoh: 010_...jpg / 62.jpg' : 'contoh: SC1002168188 / AOi...' ?>">-->
    <!--          </div>-->

    <!--          <div>-->
    <!--            <button class="btn ef-btn ef-btn-primary" type="submit">-->
    <!--              <i class="mdi mdi-magnify"></i> Search-->
    <!--            </button>-->
    <!--          </div>-->

    <!--          <div>-->
    <!--            <a class="btn ef-btn ef-btn-soft"-->
    <!--               href="foto_file.php<?= $kode ? ('?kode='.rawurlencode($kode)) : '' ?>"-->
    <!--               title="Reset">-->
    <!--              <i class="mdi mdi-refresh"></i>-->
    <!--            </a>-->
    <!--          </div>-->

    <!--          <?php if ($kode): ?>-->
    <!--          <div>-->
    <!--            <a class="btn ef-btn ef-btn-soft" href="foto_file.php" title="Kembali ke list folder">-->
    <!--              <i class="mdi mdi-arrow-left"></i>-->
    <!--            </a>-->
    <!--          </div>-->
    <!--          <?php endif; ?>-->
    <!--        </form>-->

    <!--        <div class="ef-stat mt-2">-->
    <!--          Total: <b><?= number_format((int)$total,0,',','.') ?></b>-->
    <!--          <?= $kode ? 'foto' : 'folder' ?>-->
    <!--          <?php if (!$kode): ?> • Per halaman: <b><?= (int)$PER_PAGE ?></b><?php endif; ?>-->
    <!--          <?php if ($q !== ''): ?> • Filter: <code><?= e($q) ?></code><?php endif; ?>-->
    <!--        </div>-->
    <!--      </div>-->
    <!--    </div>-->
    <!--  </div>-->
    <!--</div>-->

    <?php if (!$kode): ?>
      <!-- PAGER (TOP ONLY) -->
      <?php if ($total > 0): ?>
      <div class="row">
        <div class="col-12 grid-margin">
          <div class="card ef-card">
            <div class="card-body ef-pager">
              <div class="ef-stat">
                Halaman <b><?= (int)$page ?></b> dari <b><?= (int)$totalPages ?></b>
                • tampil <b><?= (int)$fromN ?></b>–<b><?= (int)$toN ?></b> dari <b><?= number_format((int)$total,0,',','.') ?></b>
              </div>
              <div class="d-flex" style="gap:6px; flex-wrap:wrap;">
                <?php
                  $mk = function(int $p, string $label, bool $dis=false){
                    $href = $dis ? 'javascript:void(0)' : ('?'.build_qs(['page'=>$p]));
                    $cls  = 'ef-pbtn' . ($dis ? ' disabled' : '');
                    return '<a class="'.$cls.'" href="'.$href.'">'.$label.'</a>';
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

      <!-- FOLDER LIST -->
      <div class="row">
        <div class="col-12 grid-margin">
          <?php if (empty($showFolders)): ?>
            <div class="card ef-card"><div class="card-body text-muted">Folder tidak ditemukan.</div></div>
          <?php else: ?>
            <div class="ef-grid">
              <?php foreach ($showFolders as $fd):
                $k = (string)$fd['kode'];
                $count = (int)$fd['count'];
                $mt = !empty($fd['mt']) ? date('d M Y H:i', (int)$fd['mt']).' WIB' : '-';
                $href = 'foto_file.php?kode=' . rawurlencode($k);
              ?>
                <div class="ef-item">
  <div class="ef-row">
    <!-- DULU: <a class="ef-main" href="...">  SEKARANG: div biasa -->
    <div class="ef-main" style="cursor:default;">
      <div class="ef-ico"><i class="mdi mdi-folder-multiple-image"></i></div>
      <div style="min-width:0;">
        <div class="ef-title" title="<?= e($k) ?>"><?= e($k) ?></div>
        <div class="ef-meta"><?= number_format($count,0,',','.') ?> foto • <?= e($mt) ?></div>
      </div>
    </div>

    <div class="ef-actions">
      <!-- INI SAJA yang bisa masuk halaman folder -->
      <a class="ef-icbtn" href="<?= e($href) ?>" title="Buka folder">
        <i class="mdi mdi-folder-open-outline"></i>
      </a>

      <?php if ($canDelete): ?>
        <button type="button" class="ef-icbtn danger btnDelFolder" data-kode="<?= e($k) ?>" title="Hapus folder">
          <i class="mdi mdi-trash-can-outline"></i>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <!-- PHOTO GRID (NO PAGER, NO MULTI SELECT) -->
      <div class="row">
        <div class="col-12 grid-margin">
          <?php if (empty($images)): ?>
            <div class="card ef-card"><div class="card-body text-muted">Foto tidak ditemukan di folder ini.</div></div>
          <?php else: ?>
            <div class="ef-grid">
              <?php foreach ($images as $im):
                $file = (string)$im['file'];
                $url  = (string)$im['url'];
                $mt   = !empty($im['mt']) ? date('d M Y H:i', (int)$im['mt']).' WIB' : '-';
              ?>
                <div class="ef-item">
                  <div class="ef-row" style="padding-bottom:10px;">
                    <a class="ef-main" href="<?= e($url) ?>" target="_blank" rel="noopener" title="Buka foto">
                      <div class="ef-ico"><i class="mdi mdi-image-outline"></i></div>
                      <div style="min-width:0;">
                        <div class="ef-title" title="<?= e($file) ?>"><?= e($file) ?></div>
                        <div class="ef-meta"><?= e($mt) ?></div>
                      </div>
                    </a>

                    <div class="ef-actions">
                      <a class="ef-icbtn" href="<?= e($url) ?>" target="_blank" rel="noopener" title="View">
                        <i class="mdi mdi-eye"></i>
                      </a>
                      <?php if ($canDelete): ?>
                        <button type="button"
                                class="ef-icbtn danger btnDelPhoto"
                                data-kode="<?= e($kode) ?>"
                                data-file="<?= e($file) ?>"
                                title="Hapus foto">
                          <i class="mdi mdi-trash-can-outline"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="ef-photo-thumb">
                    <a href="<?= e($url) ?>" target="_blank" rel="noopener">
                      <img src="<?= e($url) ?>" alt="<?= e($file) ?>" loading="lazy">
                    </a>
                  </div>

                  <div class="ef-photo-foot">
                    <div>Folder: <b><?= e($kode) ?></b></div>
                    <div>File: <b><?= e($file) ?></b></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- ACTIONS: DOWNLOAD ALL + DELETE ALL -->
          <div class="mt-3 d-flex justify-content-end" style="gap:8px; flex-wrap:wrap;">
<a class="btn btn-outline-secondary btn-sm"
   href="foto_file.php?kode=<?= e(rawurlencode($kode)) ?>&download_all=1"
   target="_blank" rel="noopener"
   title="Download semua foto (ZIP)">
  <i class="mdi mdi-archive-arrow-down-outline mr-1"></i> Download Semua Foto
</a>


            <?php if ($canDelete): ?>
              <button type="button" class="btn btn-outline-danger btn-sm btnDelFolder" data-kode="<?= e($kode) ?>">
                <i class="mdi mdi-trash-can-outline mr-1"></i> Hapus Semua Foto (<?= e($kode) ?>)
              </button>
            <?php endif; ?>
          </div>

          <?php if ($canDelete): ?>
            <div class="small text-muted mt-1 text-right">Hapus semua foto = menghapus folder beserta isinya.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

</div>

<!-- MODAL HAPUS FOLDER -->
<div class="modal fade" id="delFolderModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content" style="border-radius:0; overflow:hidden;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Hapus Folder</h5>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pt-2">
        <p class="mb-2 text-muted" style="font-size:.9rem">Yakin ingin menghapus folder ini beserta isinya?</p>
        <div class="p-2" style="border:1px solid #e5e7eb;border-radius:0;background:#f9fafb;">
          <i class="mdi mdi-folder-outline mr-1"></i>
          <strong id="delFolderLabel"></strong>
        </div>
        <p class="mb-0 mt-2 text-muted" style="font-size:.8rem">Tindakan ini tidak bisa dibatalkan.</p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Batal</button>
        <form method="POST" class="m-0 p-0">
          <input type="hidden" name="kode" id="delFolderKode">
          <button type="submit" name="delete_folder" class="btn btn-danger btn-sm">
            <i class="mdi mdi-trash-can-outline mr-1"></i> Hapus
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL HAPUS FOTO -->
<div class="modal fade" id="delPhotoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content" style="border-radius:0; overflow:hidden;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Hapus Foto</h5>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pt-2">
        <p class="mb-2 text-muted" style="font-size:.9rem">Yakin ingin menghapus foto ini?</p>
        <div class="p-2" style="border:1px solid #e5e7eb;border-radius:0;background:#f9fafb;">
          <i class="mdi mdi-image-outline mr-1"></i>
          <strong id="delPhotoLabel"></strong>
        </div>
        <p class="mb-0 mt-2 text-muted" style="font-size:.8rem">Tindakan ini tidak bisa dibatalkan.</p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Batal</button>
        <form method="POST" class="m-0 p-0">
          <input type="hidden" name="kode" id="delPhotoKode">
          <input type="hidden" name="file" id="delPhotoFile">
          <button type="submit" name="delete_photo" class="btn btn-danger btn-sm">
            <i class="mdi mdi-trash-can-outline mr-1"></i> Hapus
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  function showModal(modalEl){
    if (!modalEl) return;

    // Bootstrap 5
    if (window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }
    // Bootstrap 4 (jQuery)
    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
      window.jQuery(modalEl).modal('show');
      return;
    }
    // fallback minimal
    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
  }

  // Delete folder
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btnDelFolder');
    if (!btn) return;

    e.preventDefault();
    const k = String(btn.getAttribute('data-kode') || '');
    const inp = document.getElementById('delFolderKode');
    const lbl = document.getElementById('delFolderLabel');
    if (inp) inp.value = k;
    if (lbl) lbl.textContent = k;

    showModal(document.getElementById('delFolderModal'));
  });

  // Delete photo
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btnDelPhoto');
    if (!btn) return;

    e.preventDefault();
    const k = String(btn.getAttribute('data-kode') || '');
    const f = String(btn.getAttribute('data-file') || '');

    const inpK = document.getElementById('delPhotoKode');
    const inpF = document.getElementById('delPhotoFile');
    const lbl  = document.getElementById('delPhotoLabel');
    if (inpK) inpK.value = k;
    if (inpF) inpF.value = f;
    if (lbl)  lbl.textContent = f;

    showModal(document.getElementById('delPhotoModal'));
  });
})();
</script>

<?php include "footer.php"; ?>
