<?php
include "header.php";
require_once __DIR__ . '/koneksi.php';
// ==== Batasi berdasarkan chat_id jika role teknisi ====
// ==== Batasi berdasarkan username/uploader kalau role teknisi ====
// ==== role & dua kemungkinan identitas (username / nama) ====
$meRole = strtolower((string)($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '')));

// dua sumber: username & nama (PAKAI ASLINYA, spasi dipertahankan)
$meUserRaw = (string)($_SESSION['user']['username'] ?? $_SESSION['username'] ?? '');
$meNameRaw = (string)($_SESSION['nama'] ?? '');

// normalisasi ringan (jaga spasi)
$meUploaderUser = trim($meUserRaw);
// boleh rapikan spasi ganda -> satu spasi
$meUploaderName = preg_replace('/\s+/', ' ', trim($meNameRaw));
// kalau ingin filter karakter non-alfanumerik, IZINKAN spasi:
$meUploaderName = preg_replace('/[^\pL\pN._\-\s]/u', '', $meUploaderName);

// kandidat identitas untuk filter
$MY_IDS   = array_values(array_unique(array_filter([$meUploaderUser, $meUploaderName])));
$onlyMine = ($meRole === 'tech' && !empty($MY_IDS));

/** helper: bikin potongan SQL dan param untuk filter uploader */
function buildUploaderFilter(array $ids): array {
  $ids = array_values(array_unique(array_filter($ids, fn($v)=>$v!=='')));
  if (!$ids) return ['', []];
  if (count($ids) === 1) return ['uploader = ?', $ids];
  return ['uploader IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
}


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
  if ($onlyMine) {
    [$cond, $p] = buildUploaderFilter($MY_IDS);
    $stmt = $pdo->prepare("
      SELECT DISTINCT kode_foto
      FROM foto
      WHERE $cond
      ORDER BY kode_foto ASC
    ");
    $stmt->execute($p);
  } else {
    $stmt = $pdo->query("SELECT DISTINCT kode_foto FROM foto ORDER BY kode_foto ASC");
  }
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
try {
  if ($selectedCodes) {
    $in     = implode(',', array_fill(0, count($selectedCodes), '?'));
    $params = $selectedCodes;

    $sql = "
      SELECT id_foto, kode_foto, uploader, date_received
      FROM foto
      WHERE kode_foto IN ($in)
    ";

    if ($onlyMine) {
      [$cond, $p] = buildUploaderFilter($MY_IDS);
      $sql     .= " AND $cond";
      $params   = array_merge($params, $p);
    }

    $sql .= " ORDER BY FIELD(kode_foto," . implode(',', array_fill(0, count($selectedCodes), '?')) . "), id_foto DESC";
    $params = array_merge($params, $selectedCodes);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

  } else {
    if ($onlyMine) {
      [$cond, $p] = buildUploaderFilter($MY_IDS);
      $stmt = $pdo->prepare("
        SELECT id_foto, kode_foto, uploader, date_received
        FROM foto
        WHERE $cond
        ORDER BY id_foto DESC
        LIMIT 300
      ");
      $stmt->execute($p);
    } else {
      $stmt = $pdo->query("
        SELECT id_foto, kode_foto, uploader, date_received
        FROM foto
        ORDER BY id_foto DESC
        LIMIT 300
      ");
    }
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $rows = [];
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
              <div class="form-group ml-3 mb-4">
                <button type="submit" name="search" class="btn px-10 mt-4 btn-cari">Search</button>
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
                  $kode = (string)$r['kode_foto'];
                  $imgUrl = find_foto_relurl($kode);
                  $thumbs = list_foto_urls($kode, 50);
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
  // ambil semua foto (bisa banyak, biar scroll)
  $thumbs = list_foto_urls($kode, 300);
  $gridId = idify($kode);
  $visible = 6; // jumlah default yang terlihat saat collapsed (sinkron dgn CSS di bawah)
?>
<td>
  <?php if ($thumbs): ?>
    <div class="thumb-wrap">
      <!-- default: mode clip (hanya 12 terlihat), tetap 1 baris -->
      <div class="thumb-row clip" id="<?= e($gridId) ?>" data-visible="<?= (int)$visible ?>">
        <?php foreach ($thumbs as $u): ?>
          <a href="<?= e($u) ?>" target="_blank" title="Buka foto">
            <img src="<?= e($u) ?>" alt="foto <?= e($kode) ?>">
          </a>
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
  <div class="dropdown">
    <button class="btn btn-icon btn-sm"
        type="button"
        title="Aksi"
        style="background-color: #BDBDBD; color: #ffffffff;"
        data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false">
      <i class="mdi mdi-chevron-down" style="font-size:18px"></i>
    </button>

    <div class="dropdown-menu dropdown-menu-right">
    <a href="javascript:void(0)"
   class="dropdown-item js-manage-foto"
   data-kode="<?= e(trim($kode)) ?>"
   data-id="<?= (int)$r['id_foto'] ?>"
   data-toggle="modal" data-target="#manageFotoModal"
   data-bs-toggle="modal" data-bs-target="#manageFotoModal"
   onclick="return openManageFoto(this);">
  <i class="mdi mdi-upload" style="font-size:16px;vertical-align:-2px;margin-right:6px"></i>
  Upload Foto
</a>




      <div class="dropdown-divider"></div>
      <a class="dropdown-item text-danger"
         href="hapus_foto.php?kode=<?= urlencode($kode) ?>"
         onclick="return confirm('Hapus semua foto untuk kode <?= e($kode) ?> ?')">
        <i class="mdi mdi-delete" style="font-size:16px;vertical-align:-2px;margin-right:6px"></i>
        Hapus Foto
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
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageFotoLabel">Kelola Foto</h5>
       <button type="button"
          class="btn-close close"
          data-bs-dismiss="modal"
          data-dismiss="modal"
          aria-label="Tutup">
    <span aria-hidden="true" style="font-size:0;">&times;</span>
  </button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="manageKode">
        <div class="mb-2 small text-muted">Foto yang sudah ada:</div>

        <div id="manageThumbs" class="upload-preview" style="max-height:160px"></div>

        <hr>
        <form id="manageUploadForm">
          <label class="form-label">Tambah Foto</label>
          <input id="manageFiles" type="file" name="photos[]" accept="image/*" multiple class="form-control">
          <div class="small text-muted mt-1">Format: JPG, JPEG, PNG, WEBP, GIF. Maks 25 file per unggahan.</div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button id="btnUploadNow" type="button" class="btn btn-primary">Simpan</button>
      </div>
    </div>
  </div>
</div>
<style>
  #manageThumbs{display:flex;flex-wrap:wrap;gap:8px;border:1px dashed #e5e7eb;background:#f9fafb;padding:8px}
#manageThumbs .thumb{position:relative;width:64px;height:64px;flex:0 0 auto}
#manageThumbs .thumb img{width:100%;height:100%;object-fit:cover;border:1px solid #e5e7eb;border-radius:2px}
#manageThumbs .thumb .thumb-x{
  position:absolute;top:-6px;right:-6px;width:18px;height:18px;line-height:16px;
  border-radius:50%;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-weight:bold;font-size:12px;padding:0;
}

</style>
<script>
// ==== Handler yang PASTI jalan untuk modal "Kelola Foto" ====
function openManageFoto(trigger){
  const manageModalEl = document.getElementById('manageFotoModal');
  const thumbsEl      = document.getElementById('manageThumbs');
  const kodeEl        = document.getElementById('manageKode');
  const filesInput    = document.getElementById('manageFiles');

  if (!manageModalEl || !thumbsEl || !kodeEl) return false;

  // Ambil data dari <a>
  const kode = (trigger?.dataset?.kode || '').replace(/\u00a0/g,' ').trim();
  const id   = trigger?.dataset?.id || '';
  if (!kode) return false;

  // Set judul & hidden field
  kodeEl.value = kode;
  document.getElementById('manageFotoLabel').textContent = `Kelola Foto — ${kode}`;
  if (filesInput) filesInput.value = '';

  // Placeholder dulu
  thumbsEl.innerHTML = '<div class="text-muted small">Memuat…</div>';

  // Tampilkan modal (BS5 -> BS4 -> fallback manual)
  try {
    if (window.bootstrap && window.bootstrap.Modal) {
      (window.bootstrap.Modal.getInstance(manageModalEl) || new window.bootstrap.Modal(manageModalEl)).show();
    } else if (window.jQuery && jQuery.fn && jQuery.fn.modal) {
      jQuery(manageModalEl).modal('show');
    } else {
      // Fallback tanpa Bootstrap
      manageModalEl.classList.add('show');
      manageModalEl.style.display = 'block';
      manageModalEl.removeAttribute('aria-hidden');
      document.body.classList.add('modal-open');
      if (!document.getElementById('__simpleBackdrop')) {
        const bd = document.createElement('div');
        bd.id = '__simpleBackdrop';
        bd.className = 'modal-backdrop fade show';
        document.body.appendChild(bd);
      }
    }
  } catch(_) {
    // Kalau new Modal crash (mismatch versi), pakai jQuery/fallback
    if (window.jQuery && jQuery.fn && jQuery.fn.modal) jQuery(manageModalEl).modal('show');
    else {
      manageModalEl.classList.add('show');
      manageModalEl.style.display = 'block';
      manageModalEl.removeAttribute('aria-hidden');
    }
  }

  // Load thumbs asinkron (tidak menghalangi tampilan modal)
  fetch(`foto_ajax.php?act=list&kode=${encodeURIComponent(kode)}&id_foto=${encodeURIComponent(id)}`)
    .then(r => r.json())
    .then(js => {
      if (!js.ok) throw new Error(js.error || 'Gagal memuat');
      renderManageThumbs(js.files || []);
    })
    .catch(e => {
      thumbsEl.innerHTML = `<div class="text-danger small">${e.message}</div>`;
    });

  return false; // cegah event default
}

// Render grid thumbnail di modal Kelola Foto
function renderManageThumbs(files){
  const thumbsEl = document.getElementById('manageThumbs');
  if (!thumbsEl) return;
  thumbsEl.innerHTML = '';
  if (!files?.length){
    thumbsEl.innerHTML = '<div class="small text-muted">Belum ada foto.</div>';
    return;
  }
  files.forEach(f => {
    const box = document.createElement('div');
    box.className = 'thumb'; box.dataset.name = f.name;

    const img = document.createElement('img');
    img.src = f.url; img.alt = f.name;

    const del = document.createElement('button');
    del.type='button'; del.className='thumb-x'; del.title='Hapus'; del.textContent='×';

    box.appendChild(img); box.appendChild(del);
    thumbsEl.appendChild(box);
  });
}

// Hapus satu foto (delegasi pada container modal)
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#manageThumbs .thumb-x');
  if (!btn) return;
  const box = btn.parentElement;
  const name= box?.dataset.name || '';
  const kode= document.getElementById('manageKode')?.value || '';
  if (!name || !kode) return;
  if (!confirm(`Hapus foto "${name}"?`)) return;

  const fd = new FormData();
  fd.append('act','delete'); fd.append('kode', kode); fd.append('name', name);

  try{
    const res = await fetch('foto_ajax.php?act=delete', { method:'POST', body: fd });
    const js  = await res.json();
    if (js?.ok) { box.remove(); }
    else { alert(js?.error || 'Gagal hapus'); }
  }catch(e){ alert(e.message); }
});

// Upload file baru dari modal Kelola Foto
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#btnUploadNow');
  if (!btn) return;

  const filesInput = document.getElementById('manageFiles');
  const kode = document.getElementById('manageKode')?.value || '';
  if (!filesInput?.files?.length) { alert('Pilih foto terlebih dahulu.'); return; }

  const fd = new FormData();
  fd.append('kode', kode);
  for (const f of filesInput.files) fd.append('photos[]', f);

  btn.disabled = true; btn.textContent = 'Menyimpan…';
  try{
    const res = await fetch('foto_ajax.php?act=upload', { method:'POST', body: fd });
    const js  = await res.json();
    if (!js.ok) throw new Error(js.error || 'Gagal upload');

    // refresh thumbs
    const thumbsEl = document.getElementById('manageThumbs');
    if (thumbsEl) thumbsEl.innerHTML = '<div class="text-muted small">Memuat…</div>';
    const r2 = await fetch(`foto_ajax.php?act=list&kode=${encodeURIComponent(kode)}&id_foto=`);
    const j2 = await r2.json();
    if (!j2.ok) throw new Error(j2.error || 'Gagal memuat');
    renderManageThumbs(j2.files || []);
    filesInput.value = '';
  }catch(e){ alert(e.message); }
  finally{
    btn.disabled = false; btn.textContent = 'Simpan';
  }
});
</script>


<div class="modal fade" id="addFotoModal" tabindex="-1" aria-labelledby="addFotoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" id="addFotoDialog">
    <form class="modal-content" method="POST" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="addFotoLabel">Tambah Foto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="upload_foto" value="1">

        <div class="mb-3">
          <label class="form-label">Pilih No. SC</label>
         <select id="ms-kode-foto" name="kode_foto[]" multiple class="ts-sc">
  <?php foreach ($noScOptions as $sc): ?>
    <option value="<?= e($sc) ?>"><?= e($sc) ?></option>
  <?php endforeach; ?>
</select>
          <small class="text-muted">No. SC yang terpilih akan menjadi <code>kode_foto</code></small>
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
        <button type="submit" class="btn btn-primary" style="background:#20a1ad;border-color:#20a1ad">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- ====== Styles + Tom Select ====== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<style>
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

/* Ukuran thumbnail */
.thumb-row a img{
  width: 44px;         /* ubah ke 40/36 kalau mau lebih kecil */
  height: 44px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
  border-radius: 2px;
  flex: 0 0 auto;
}

/* Mode clip = sembunyikan item ke-13 dst (tetap 1 baris) */
.thumb-row.clip a:nth-child(n+7){ display: none; }

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
  /* atur lebar modal (kecil) */
  --bs-modal-width: 640px;     /* ubah ke 560/600 kalau mau lebih kecil lagi */
  margin-top: 5vh;             /* beri jarak dari atas */
  margin-bottom: auto;
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



</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  /* === Tom Select: search utama === */
  const elSearch = document.getElementById('kode-search');
  if (elSearch) {
    new TomSelect(elSearch, {
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
  const elSc = document.getElementById('ms-kode-foto');
  if (elSc) {
    new TomSelect(elSc, {
      plugins: { remove_button: {title: 'Hapus'} },
      placeholder: 'Ketik No. SC lalu Enter…',
      maxOptions: 4000,
      maxItems: 1,    
      create: false,
      hideSelected: true,
      openOnFocus: true,
      closeAfterSelect: true,
      hideSelected: true,
      sortField: {field: 'text', direction: 'asc'}
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
});
</script>


<?php include "footer.php"; ?>
