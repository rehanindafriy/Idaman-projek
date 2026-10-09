<?php
declare(strict_types=1);

// DEBUG LOKAL – boleh dimatikan di produksi
error_reporting(E_ALL);
ini_set('display_errors', '1');

@ini_set('memory_limit', '-1');     // pakai semua memory yang diizinkan hosting
@set_time_limit(0);                 // tidak timeout di level PHP
@ini_set('max_execution_time', '0');

// (opsional tapi sangat membantu debug 500)
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo "<h2>Uncaught error di import_data.php</h2>";
    echo "<pre>" . htmlspecialchars(
        $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString(),
        ENT_QUOTES,
        'UTF-8'
    ) . "</pre>";
    exit;
});

// ====== SETUP DASAR (sama seperti halaman lain) ======
require_once __DIR__ . '/koneksi.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ====== KONFIG: PATH SCRIPT IMPORT CLI ======
define('IMPORT_ORDER_SCRIPT', __DIR__ . '/import_order.php');
define('IMPORT_BAI_SCRIPT',   __DIR__ . '/import_bai.php');
define('IMPORT_FOTO_SCRIPT',  __DIR__ . '/import_foto.php');

// ====== HELPER ALERT & TERMINAL ======
$importAlerts = [
  'success' => [],
  'error'   => [],
];
$consoleLogs = [];

/**
 * Tambah alert bootstrap (atas form)
 */
function es_import_add_alert(string $type, string $msg): void {
  global $importAlerts;
  if (!isset($importAlerts[$type])) {
    $importAlerts[$type] = [];
  }
  if ($msg !== '') {
    $importAlerts[$type][] = $msg;
  }
}

/**
 * Tambah blok log ke panel terminal
 *
 * @param string $title   Judul blok (misal: "import_order.php – result.json")
 * @param string $output  Isi output dari script CLI
 */
function es_import_append_console_log(string $title, string $output): void {
  global $consoleLogs;
  $title  = trim($title);
  $output = trim($output);

  if ($title === '' && $output === '') {
    return;
  }
  $consoleLogs[] = [
    'title'  => $title === '' ? 'Log' : $title,
    'output' => $output === '' ? '(tidak ada output)' : $output,
  ];
}

// ====== HELPER: SIMPAN FILE UPLOAD (NAMA UNIK) ======
/**
 * @return array{ok:bool, path?:string, name?:string, ext?:string, error?:string}
 */
function es_import_handle_upload(string $field, array $allowedExt, string $subdir): array {
  if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
    return ['ok' => false, 'error' => ''];
  }

  $f = $_FILES[$field];

  // Tidak ada file dipilih → bukan error, cukup di-skip
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($f['size'] ?? 0) === 0) {
    return ['ok' => false, 'error' => ''];
  }

  if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    $msg = 'Upload gagal.';

    $errCode = (int)$f['error'];
    $upMax   = (string)ini_get('upload_max_filesize');
    $postMax = (string)ini_get('post_max_size');

    switch ($errCode) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        $msg =
          'Ukuran file melebihi batas upload yang diizinkan server. ' .
          'Batas saat ini: upload_max_filesize=' . $upMax .
          ', post_max_size=' . $postMax . ".<br>" .
          'Untuk file sangat besar:<br>' .
          '1) Perbesar limit di pengaturan PHP/Hostinger (php.ini/.htaccess).<br>' .
          '2) Upload via File Manager/SFTP ke folder <code>var/import/.../</code> ' .
          'lalu jalankan <code>import_order.php</code> / <code>import_bai.php</code> / <code>import_foto.php</code> via CLI.';
        break;

      case UPLOAD_ERR_PARTIAL:
        $msg = 'File ter-upload sebagian (koneksi terputus atau timeout). Coba ulangi atau gunakan CLI + File Manager.'; 
        break;

      case UPLOAD_ERR_NO_TMP_DIR:
        $msg = 'Folder temp upload tidak ditemukan di server. Hubungi admin/hosting (upload_tmp_dir).';
        break;

      case UPLOAD_ERR_CANT_WRITE:
        $msg = 'Gagal menulis file upload ke disk (izin folder/space penuh). Periksa quota atau hubungi admin.';
        break;

      case UPLOAD_ERR_EXTENSION:
        $msg = 'Upload diblokir oleh ekstensi PHP. Cek konfigurasi php.ini kalau ada.';
        break;

      default:
        $msg = 'Upload gagal dengan kode error ' . $errCode . '.';
        break;
    }

    return ['ok' => false, 'error' => $msg];
  }

  // Tidak ada pembatasan size manual di sini, hanya batas server/php.ini
  $origName = (string)($f['name'] ?? 'file');
  $ext      = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));

  if ($allowedExt && !in_array($ext, $allowedExt, true)) {
    return [
      'ok'    => false,
      'error' => "Tipe file .$ext tidak diizinkan untuk field {$field}.",
    ];
  }

  // Semua upload disimpan di var/import/<subdir>/
  $baseDir = __DIR__ . '/var/import/' . $subdir;
  if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
    return [
      'ok'    => false,
      'error' => "Gagal membuat folder penyimpanan: {$baseDir}",
    ];
  }

  $baseName = (string)pathinfo($origName, PATHINFO_FILENAME);
  $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName) ?: 'file';
  $target   = $baseDir . '/' . date('Ymd_His') . '_' . $safeBase . '.' . $ext;

  if (!@move_uploaded_file($f['tmp_name'], $target)) {
    return [
      'ok'    => false,
      'error' => "Gagal memindahkan file upload ke server.",
    ];
  }

  return [
    'ok'   => true,
    'path' => $target,
    'name' => $origName,
    'ext'  => $ext,
  ];
}

// ====== HELPER: EKSTRAK ZIP KE FOLDER (NAMA UNIK) ======
/**
 * Ekstrak file ZIP ke $destDir
 * @return array{ok:bool,error?:string,files?:int}
 */
function es_import_extract_zip_to(string $zipPath, string $destDir): array {
  if (!is_file($zipPath)) {
    return ['ok' => false, 'error' => 'File ZIP tidak ditemukan.'];
  }
  if (!extension_loaded('zip')) {
    return ['ok' => false, 'error' => 'Ekstensi ZIP PHP belum aktif di server.'];
  }

  $zip = new ZipArchive();
  if ($zip->open($zipPath) !== true) {
    return ['ok' => false, 'error' => 'Gagal membuka arsip ZIP.'];
  }

  if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
    $zip->close();
    return ['ok' => false, 'error' => "Gagal membuat folder ekstrak: {$destDir}"];
  }

  if (!$zip->extractTo($destDir)) {
    $zip->close();
    return ['ok' => false, 'error' => 'Gagal mengekstrak isi ZIP.'];
  }

  $fileCount = $zip->numFiles;
  $zip->close();

  return ['ok' => true, 'files' => $fileCount];
}

/**
 * Escape argumen shell (beda Windows & *nix).
 */
function es_shell_arg(string $arg): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        $arg = str_replace('"', '\"', $arg);
        return '"' . $arg . '"';
    }
    return escapeshellarg($arg);
}

/**
 * Cari binary PHP CLI yang benar.
 */
function es_find_php_cli(): string
{
    $env = getenv('ES_PHP_CLI');
    if ($env && is_file($env)) {
        return $env;
    }

    $candidates = [];

    if (stripos(PHP_OS_FAMILY ?? php_uname('s'), 'Windows') !== false) {
        $dir = dirname(PHP_BINARY);
        $candidates[] = $dir . DIRECTORY_SEPARATOR . 'php.exe';
        $candidates[] = 'php';
    } else {
        if (defined('PHP_BINDIR')) {
            $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        }
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = 'php';
    }

    foreach ($candidates as $bin) {
        if ($bin === 'php') {
            return $bin;
        }
        if (is_file($bin) && is_readable($bin)) {
            return $bin;
        }
    }

    return 'php';
}

/**
 * Menjalankan script CLI dengan argumen path JSON
 *
 * @return array{ok:bool,output:string}
 */
function es_import_run_script(string $scriptPath, string $jsonPath): array
{
    $scriptPath = (string)$scriptPath;
    $jsonPath   = (string)$jsonPath;

    if (!is_file($scriptPath)) {
        return [
            'ok'     => false,
            'output' => "Script import tidak ditemukan: {$scriptPath}",
        ];
    }
    if (!is_file($jsonPath)) {
        return [
            'ok'     => false,
            'output' => "File JSON tidak ditemukan: {$jsonPath}",
        ];
    }

    $disabledList = array_filter(array_map(
        'trim',
        explode(',', (string) ini_get('disable_functions'))
    ));
    if (in_array('exec', $disabledList, true) || !function_exists('exec')) {
        $cmdExample = 'php ' . basename($scriptPath) . ' ' . basename($jsonPath);
        return [
            'ok'     => false,
            'output' =>
                "Fungsi exec() tidak boleh dipakai di PHP web.\n" .
                "Jalankan manual via CLI, misalnya:\n" .
                "  {$cmdExample}\n",
        ];
    }

    $phpBin = es_find_php_cli();
    $cmd =
        es_shell_arg($phpBin) . ' ' .
        es_shell_arg($scriptPath) . ' ' .
        es_shell_arg($jsonPath) . ' 2>&1';

    $outLines = [];
    $retCode  = 0;
    exec($cmd, $outLines, $retCode);

    return [
        'ok'     => ($retCode === 0),
        'output' => trim(implode("\n", $outLines)),
    ];
}

// ====== PROSES FORM SUBMIT ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $anyUploaded = false;

  // --- 1) IMPORT ORDER ---
  $orderUpload = es_import_handle_upload('order_file', ['json','xlsx','xls','csv'], 'orders');
  if ($orderUpload['ok'] ?? false) {
    $anyUploaded = true;
    $ext  = $orderUpload['ext'] ?? '';
    $name = $orderUpload['name'] ?? 'file';

    if ($ext === 'json') {
      $res = es_import_run_script(IMPORT_ORDER_SCRIPT, $orderUpload['path']);
      es_import_append_console_log("import_order.php – {$name}", $res['output']);

      if ($res['ok']) {
        es_import_add_alert('success', "Import ORDER dari file <b>{$name}</b> berhasil dijalankan.");
      } else {
        es_import_add_alert('error', "Import ORDER dari file <b>{$name}</b> gagal. Lihat detail di panel terminal di bawah.");
      }
    } else {
      es_import_add_alert(
        'success',
        "File ORDER <b>{$name}</b> berhasil diunggah (format Excel/CSV). " .
        "Parsing otomatis belum diaktifkan, Press bisa tulis parser Excel terpisah pakai PhpSpreadsheet."
      );
    }
  } elseif (!empty($orderUpload['error'])) {
    es_import_add_alert('error', "ORDER: " . $orderUpload['error']);
  }

  // --- 2) IMPORT BAI (PDF) ---
  $baiJson = es_import_handle_upload('bai_json', ['json'], 'bai');
  $baiZip  = es_import_handle_upload('bai_zip',  ['zip'],  'bai');

  if ($baiJson['ok'] ?? false) {
    $anyUploaded = true;
    $jsonPath   = $baiJson['path'];
    $jsonName   = $baiJson['name'] ?? 'result.json';
    $exportRoot = dirname($jsonPath);

    if ($baiZip['ok'] ?? false) {
      $zipPath = $baiZip['path'];
      $zipName = $baiZip['name'] ?? 'files.zip';

      $zipRes = es_import_extract_zip_to($zipPath, $exportRoot);
      if ($zipRes['ok']) {
        es_import_add_alert(
          'success',
          "Paket BAI <b>{$zipName}</b> berhasil diekstrak ke folder yang sama dengan JSON " .
          "(jumlah file di ZIP: " . (int)$zipRes['files'] . ")."
        );
      } else {
        es_import_add_alert('error', "Gagal ekstrak paket BAI <b>{$zipName}</b>: " . $zipRes['error']);
      }
    } elseif (!empty($baiZip['error'])) {
      es_import_add_alert('error', "BAI ZIP: " . $baiZip['error']);
    }

    $res = es_import_run_script(IMPORT_BAI_SCRIPT, $jsonPath);
    es_import_append_console_log("import_bai.php – {$jsonName}", $res['output']);

    if ($res['ok']) {
      es_import_add_alert('success', "Import BAI dari JSON <b>{$jsonName}</b> berhasil dijalankan.");
    } else {
      es_import_add_alert('error', "Import BAI dari JSON <b>{$jsonName}</b> gagal. Lihat detail di panel terminal di bawah.");
    }

  } else {
    if ($baiZip['ok'] ?? false) {
      $anyUploaded = true;
      $zipName = $baiZip['name'] ?? 'files.zip';
      es_import_add_alert(
        'success',
        "Paket BAI <b>{$zipName}</b> berhasil diunggah ke server (belum dijalankan import otomatis karena tanpa JSON)."
      );
    } elseif (!empty($baiZip['error'])) {
      es_import_add_alert('error', "BAI ZIP: " . $baiZip['error']);
    }
  }

  // --- 3) IMPORT FOTO (EVIDENSI) ---
  $fotoJson = es_import_handle_upload('foto_json', ['json'], 'foto');
  $fotoZip  = es_import_handle_upload('foto_zip',  ['zip'],  'foto');

  if ($fotoJson['ok'] ?? false) {
    $anyUploaded = true;
    $jsonPath   = $fotoJson['path'];
    $jsonName   = $fotoJson['name'] ?? 'result.json';
    $exportRoot = dirname($jsonPath);

    if ($fotoZip['ok'] ?? false) {
      $zipPath = $fotoZip['path'];
      $zipName = $fotoZip['name'] ?? 'photos.zip';
      $zipRes  = es_import_extract_zip_to($zipPath, $exportRoot);
      if ($zipRes['ok']) {
        es_import_add_alert(
          'success',
          "Paket FOTO <b>{$zipName}</b> berhasil diekstrak (" . (int)$zipRes['files'] . " file di dalam ZIP)."
        );
      } else {
        es_import_add_alert('error', "Gagal ekstrak paket FOTO <b>{$zipName}</b>: " . $zipRes['error']);
      }
    } elseif (!empty($fotoZip['error'])) {
      es_import_add_alert('error', "FOTO ZIP: " . $fotoZip['error']);
    }

    $res = es_import_run_script(IMPORT_FOTO_SCRIPT, $jsonPath);
    es_import_append_console_log("import_foto.php – {$jsonName}", $res['output']);

    if ($res['ok']) {
      es_import_add_alert('success', "Import FOTO dari JSON <b>{$jsonName}</b> berhasil dijalankan.");
    } else {
      es_import_add_alert('error', "Import FOTO dari JSON <b>{$jsonName}</b> gagal. Lihat detail di panel terminal di bawah.");
    }

  } else {
    if ($fotoZip['ok'] ?? false) {
      $anyUploaded = true;
      $zipName = $fotoZip['name'] ?? 'photos.zip';
      es_import_add_alert(
        'success',
        "Paket FOTO <b>{$zipName}</b> berhasil diunggah ke server (belum dijalankan import otomatis karena tanpa JSON)."
      );
    } elseif (!empty($fotoZip['error'])) {
      es_import_add_alert('error', "FOTO ZIP: " . $fotoZip['error']);
    }
  }

  if (!$anyUploaded) {
    es_import_add_alert('error', 'Tidak ada file yang dipilih untuk di-import.');
  }
}

// ====== RENDER UI ======
include "header.php";
?>
<div class="main-panel">
  <div class="content-wrapper">
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <!-- kiri -->
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Import Data efidi</h2>
            <p class="mb-0">
              Halaman ini digunakan untuk mengimpor data <b>ORDER</b>, <b>Berita Acara Instalasi (BAI)</b>,
              dan <b>Evidensi Lapangan (Foto)</b> 
            </p>
            <p class="mb-0">dari hasil <i>Telegram Export Chat History</i> atau file pendukung lainnya.</p>
            <p class="mb-0">
              Dukungan format:
              <code>JSON Telegram</code>, <code>Excel/Spreadsheet</code> (ORDER), dan <code>ZIP</code> paket file BAI/FOTO.
            </p>
          </div>

          <!-- kanan: logo + breadcrumb -->
          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo" class="mb-1"
                 style="height:45px; margin-top:-10px; display:block;">
            <br><br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon"
                 style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
              <span class="text-primary crumb-mid">Import Data</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CARD IMPORT -->
    <div class="row">
      <div class="col-md-12 stretch-card">
        <div class="card import-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <p class="card-title mb-1">Import Data dari Telegram / File Pendukung</p>
                <p class="text-muted mb-0">
                  Pilih satu atau lebih sumber data di bawah ini. Minimal satu file sudah cukup
                  untuk menyalakan tombol <b>Simpan &amp; Jalankan Import</b>.
                </p>
              </div>
              <div class="import-card-icon d-none d-md-flex">
                <i class="mdi mdi-database-import"></i>
              </div>
            </div>

            <!-- ALERT HASIL IMPORT -->
            <?php if (!empty($importAlerts['success']) || !empty($importAlerts['error'])): ?>
              <div class="mb-3">
                <?php foreach ($importAlerts['success'] as $msg): ?>
                  <div class="alert alert-success alert-import" role="alert">
                    <?= $msg ?>
                  </div>
                <?php endforeach; ?>
                <?php foreach ($importAlerts['error'] as $msg): if ($msg === '') continue; ?>
                  <div class="alert alert-danger alert-import" role="alert">
                    <?= $msg ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- PANEL TERMINAL (LOG EKSEKUSI SCRIPT) -->
            <?php if (!empty($consoleLogs)): ?>
              <div class="terminal-card mb-3">
                <div class="terminal-header">
                  <div class="terminal-header-left">
                    <div class="terminal-dots">
                      <span class="terminal-dot dot-red"></span>
                      <span class="terminal-dot dot-amber"></span>
                      <span class="terminal-dot dot-green"></span>
                    </div>
                    <span class="terminal-title">
                      <i class="mdi mdi-console me-1"></i>
                      Log Eksekusi Script Import
                    </span>
                  </div>
                  <span class="terminal-hint d-none d-sm-inline">
                    Output dari <code>import_order.php</code>, <code>import_bai.php</code>, dan <code>import_foto.php</code>.
                  </span>
                </div>
                <div class="terminal-body js-terminal-log">
                  <?php foreach ($consoleLogs as $block): ?>
                    <div class="terminal-block">
                      <div class="terminal-block-title">
                        <?= htmlspecialchars($block['title'], ENT_QUOTES, 'UTF-8') ?>
                      </div>
                      <pre class="terminal-pre js-typewriter"><?= htmlspecialchars($block['output'], ENT_QUOTES, 'UTF-8') ?></pre>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- FORM IMPORT -->
            <form method="post" enctype="multipart/form-data" id="form-import-data">
              <div class="row g-3">
                <!-- IMPORT ORDER -->
                <div class="col-md-4">
                  <div class="import-tile" data-import-type="order">
                    <span class="import-tile-badge">ORDER</span>
                    <div class="import-tile-header">
                      <i class="import-tile-icon import-tile-icon--order mdi mdi-file-document-multiple-outline"></i>
                      <div class="import-tile-heading">
                        <h5 class="import-tile-title mb-1">Import Data Orders</h5>
                        <p class="import-tile-subtitle mb-0">
                          Data utama Order dari Telegram Export atau Excel/Spreadsheet.
                        </p>
                      </div>
                    </div>

                    <div class="import-tile-body">
                      <div class="import-upload">
                        <label class="btn-import">
                          <i class="mdi mdi-file-upload-outline me-1"></i>
                          <span class="btn-label">Pilih file Orders</span>
                          <input type="file" name="order_file" class="file-input"
                                 accept=".json,.xlsx,.xls,.csv" hidden>
                        </label>
                        <div class="file-name small text-muted mt-1" data-placeholder="Belum ada file dipilih">
  Belum ada file dipilih
</div>
                      </div>

                      <ul class="import-meta mt-2 mb-0">
                        <li>
                          <span class="import-meta-label">Format:</span>
                          <span class="import-meta-value">.json, .xlsx, .xls, .csv</span>
                        </li>
                        <li class="import-meta-note">
                          Import otomatis ke DB saat ini diprioritaskan untuk <code>JSON Telegram</code>.
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                <!-- IMPORT BAI -->
                <div class="col-md-4">
                  <div class="import-tile" data-import-type="bai">
                    <span class="import-tile-badge">BAI</span>
                    <div class="import-tile-header">
                      <i class="import-tile-icon import-tile-icon--bai mdi mdi-file-pdf"></i>
                      <div class="import-tile-heading">
                        <h5 class="import-tile-title mb-1">Import Berita Acara Instalasi (PDF)</h5>
                        <p class="import-tile-subtitle mb-0">
                          Hubungkan JSON Telegram dengan paket PDF BAI.
                        </p>
                      </div>
                    </div>

                    <div class="import-tile-body">
                      <div class="import-upload">
                        <div class="import-step-label">Langkah 1</div>
                        <label class="btn-import">
                          <i class="mdi mdi-code-json me-1"></i>
                          <span class="btn-label">Pilih JSON (BAI)</span>
                          <input type="file" name="bai_json" class="file-input"
                                 accept=".json" hidden>
                        </label>
                        <div class="file-name small text-muted mt-1" data-placeholder="Belum ada JSON dipilih">
                          Belum ada JSON dipilih
                        </div>
                      </div>

                      <div class="import-upload extra-folder-slot">
                        <div class="import-step-label import-step-label-secondary">Langkah 2 (opsional)</div>
                        <label class="btn-import-secondary">
                          <i class="mdi mdi-folder-zip-outline me-1"></i>
                          <span class="btn-label">Pilih paket folder BAI (.zip)</span>
                          <input type="file" name="bai_zip" class="file-input"
                                 accept=".zip" hidden>
                        </label>
                        <div class="file-name small text-muted mt-1" data-placeholder="Belum ada ZIP dipilih">
                          Belum ada ZIP dipilih
                        </div>
                      </div>

                      <ul class="import-meta mt-2 mb-0">
                        <li>
                          <span class="import-meta-label">JSON:</span>
                          <span class="import-meta-value">
                            <code>result.json</code> atau <code>messages.json</code> dari Telegram Export.
                          </span>
                        </li>
                        <li>
                          <span class="import-meta-label">Paket ZIP:</span>
                          <span class="import-meta-value">
                            folder <code>files/</code>; PDF disalin ke <code>bot/file/</code> dan dicatat di tabel <code>bai</code>.
                          </span>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                <!-- IMPORT FOTO -->
                <div class="col-md-4">
                  <div class="import-tile" data-import-type="foto">
                    <span class="import-tile-badge">FOTO</span>
                    <div class="import-tile-header">
                      <i class="import-tile-icon import-tile-icon--foto mdi mdi-image-multiple-outline"></i>
                      <div class="import-tile-heading">
                        <h5 class="import-tile-title mb-1">Import Evidensi Lapangan (Foto)</h5>
                        <p class="import-tile-subtitle mb-0">
                          JSON Telegram untuk mapping foto dengan paket ZIP.
                        </p>
                      </div>
                    </div>

                    <div class="import-tile-body">
                      <div class="import-upload">
                        <div class="import-step-label">Langkah 1</div>
                        <label class="btn-import">
                          <i class="mdi mdi-code-json me-1"></i>
                          <span class="btn-label">Pilih JSON (Foto)</span>
                          <input type="file" name="foto_json" class="file-input"
                                 accept=".json" hidden>
                        </label>
                        <div class="file-name small text-muted mt-1" data-placeholder="Belum ada JSON dipilih">
                          Belum ada JSON dipilih
                        </div>
                      </div>

                      <div class="import-upload extra-folder-slot">
                        <div class="import-step-label import-step-label-secondary">Langkah 2 (opsional)</div>
                        <label class="btn-import-secondary">
                          <i class="mdi mdi-folder-zip-outline me-1"></i>
                          <span class="btn-label">Pilih paket foto (.zip)</span>
                          <input type="file" name="foto_zip" class="file-input"
                                 accept=".zip" hidden>
                        </label>
                        <div class="file-name small text-muted mt-1" data-placeholder="Belum ada ZIP dipilih">
                          Belum ada ZIP dipilih
                        </div>
                      </div>

                      <ul class="import-meta mt-2 mb-0">
                        <li>
                          <span class="import-meta-label">Struktur:</span>
                          <span class="import-meta-value">
                            ZIP berisi folder <code>photos/</code> dari Telegram Export.
                          </span>
                        </li>
                        <li class="import-meta-note">
                          Foto disusun ke <code>bot/foto/&lt;NO_SC&gt;/</code> dan dibuat 1 baris per <code>NO_SC</code> di tabel <code>foto</code>.
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <hr class="mt-4 mb-3">

              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                  Minimal <b>1</b> sumber data terisi. User bebas mengombinasikan:
                  misalnya hanya ORDER saja, atau BAI + FOTO sekaligus.
                </div>
                <button type="submit" class="btn btn-primary btn-lg" id="btn-submit-import" disabled>
                  <i class="mdi mdi-content-save-outline me-1"></i>
                  Simpan &amp; Jalankan Import
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- STYLE KHUSUS IMPORT & TERMINAL -->
    <style>
      /* ===== CARD UTAMA (tanpa shadow, tanpa gradient) ===== */
     /* ===== CARD UTAMA (tanpa shadow, tanpa gradient) ===== */
.import-card {
  border-radius: 4px;
  border: 1px solid #e5e7eb;
  background-color: #ffffff;
}
.import-card > .card-body {
  padding: 20px 20px 18px;
}
.import-card-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  border: 1px solid #d1d5db;
  background-color: #f9fafb;
  color: #00a3ad;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
}

/* ===== TILE PER IMPORT (flat, formal) ===== */
.import-tile {
  position: relative;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  background-color: #f9fafb;
  padding: 14px 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  height: 100%;
  transition: border-color .15s ease, background-color .15s ease;
}
.import-tile:hover {
  border-color: #00a3ad;
  background-color: #f3f4f6;
}

.import-tile-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.import-tile-icon {
  font-size: 24px;
  line-height: 1;
}
/* ikon TANPA background, warna beda-beda */
.import-tile-icon--order { color: #1b306bff; }  /* teal  ORDER */
.import-tile-icon--bai   { color: #dc2626; }  /* merah PDF  */
.import-tile-icon--foto  { color: #0f766e; }  /* ungu  FOTO */

.import-tile-heading {
  flex: 1;
}
.import-tile-title {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}
.import-tile-subtitle {
  font-size: 12.5px;
  color: #6b7280;
}

.import-tile-badge {
  position: absolute;
  top: 8px;
  right: 12px;
  padding: 2px 9px 3px;
  border-radius: 999px;
  border: 1px solid #d1d5db;
  background-color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #4b5563;
}

.import-tile-body {
  margin-top: 6px;
}

/* ===== SLOT UPLOAD ===== */
/* ===== SLOT UPLOAD ===== */
.import-upload {
  padding-top: 4px;
  padding-bottom: 8px;
  margin-bottom: 4px;
  border-bottom: 1px dashed rgba(148,163,184,0.55); /* 1 garis putus-putus di bawah */
}

/* blok kedua (Langkah 2) cuma dikasih jarak, TANPA garis tambahan */
.import-upload + .import-upload {
  margin-top: 12px;
  padding-top: 0;                      /* tidak perlu padding + garis di atas */
  border-top: 0;                       /* HAPUS garis atas */
}


.import-step-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 6px;
  padding: 1px 9px 2px;
  border-radius: 999px;
  border: 1px solid #d1d5db;
  font-size: 10.5px;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #4b5563;
  font-weight: 600;
  background-color: #ffffff;
}
.import-step-label-secondary {
  border-color: #00a3ad;
  color: #036673;
}

/* ===== BUTTON UPLOAD (flat, no shadow, no gradient) ===== */
.btn-import,
.btn-import-secondary {
  border-radius: 999px;
  border-width: 1px;
  border-style: solid;
  padding: 9px 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  width: 100%;
  background-color: #ffffff;
  transition: background-color .12s ease, border-color .12s ease, color .12s ease;
}

/* tombol utama (Langkah 1) */
.btn-import {
  border-color: #00a3ad;      /* teal utama */
  color: #036673;
}
.btn-import:hover {
  background-color: #e0f2f1;  /* #e0f2f1 */
}

/* tombol LANGKAH 2 OPSIONAL (BAI & FOTO)
   >>> border garis PUTUS-PUTUS <<< */
.btn-import-secondary {
  border-color: #d1d5db;      /* #d1d5db */
  border-style: dashed;
  color: #374151;
}
.btn-import-secondary:hover {
  border-color: #00a3ad;      /* #00a3ad */
  background-color: #f3f4f6;  /* #f3f4f6 */
}

/* ===== TAMPILAN NAMA FILE TERPILIH ===== */
.file-name {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 12.5px;
  line-height: 1.4;
  min-height: 24px;
}

/* placeholder (belum ada file) */
.file-name--placeholder {
  color: #9ca3af;          /* abu formal */
  padding-left: 2px;
}

/* ketika sudah ada file dipilih */
.file-name--selected {
  padding: 3px 10px 4px;
  border-radius: 999px;
  border: 1px solid #d1d5db;  /* garis halus */
  background-color: #ffffff;  /* chip putih, flat */
  color: #111827;
}

/* icon kecil sebelum nama file (pakai MDI yang sudah di-include) */
.file-name--selected::before {
  content: "\F0214";                  /* mdi-file-outline */
  font-family: "Material Design Icons";
  font-size: 14px;
  margin-right: 6px;
  color: #00a3ad;                     /* teal efidi #00A3AD */
}


.alert-import {
  border-radius: 12px;
  padding: 8px 12px;
  font-size: 13px;
}

/* ===== META DI BAWAH SLOT ===== */
.import-meta {
  list-style: none;
  padding-left: 0;
  margin: 0;
  font-size: 11.5px;
  color: #6b7280;
}
.import-meta li + li {
  margin-top: 4px;
}
.import-meta-label {
  font-weight: 600;
  color: #4b5563;
  margin-right: 4px;
}
.import-meta-note {
  color: #6b7280;
}

/* ===== BUTTON SIMPAN & JALANKAN IMPORT ===== */
#btn-submit-import {
  font-size: 13px;                 /* diperkecil */
  padding: 7px 18px;               /* lebih ramping */
  border-radius: 999px;            /* pill */
  background-color: #182958;       /* teal efidi: #00A3AD */
  border-color: #182958;           /* #00A3AD */
}
#btn-submit-import:hover:not(:disabled) {
  background-color: #0f204eff;       /* hover: #00828B */
  border-color: #0f204eff;
}
#btn-submit-import:disabled {
  background-color: #213362ff;       /* disabled: #9CA3AF */
  border-color: #213362ff;
}

/* ===== TERMINAL CARD (biarkan seperti sebelumnya) ===== */
/* ... (bagian terminal-card, terminal-header, dll tetap seperti yang Press punya) ... */

.btn-loading {
  opacity: .9;
  pointer-events: none;
}
.spinner-border {
  display: inline-block;
  width: 1rem;
  height: 1rem;
  vertical-align: text-bottom;
  border: .15em solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: spinner-border .75s linear infinite;
}
@keyframes spinner-border {
  to { transform: rotate(360deg); }
}


      /* ===== TERMINAL (dibiarkan dark seperti sebelumnya) ===== */
      .terminal-card {
        --term-bg:     #000000;
        --term-border: #111827;
        --term-text:   #e5e5e5;
        --term-muted:  #6b7280;
        --term-green:  #a3e635;
        --term-yellow: #eab308;
        --term-magenta: #7affdeff;
        --term-cyan:   #22d3ee;
        --term-red:    #f97373;
        --term-blue:   #4ce7ff;
        --term-orange: #fb923c;
        --term-purple: #e879f9;

        border-radius: 8px;
        border: 1px solid var(--term-text);
        background: var(--term-bg);
        color: var(--term-text);
        font-family: "Consolas", "DejaVu Sans Mono", "Liberation Mono",
                     "Courier New", monospace;
        overflow: hidden;
      }

      .terminal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 12px 5px;
        border-bottom: 1px solid #f7f4f4ff;
        background: #f7f4f4ff;
        font-size: 11px;
        letter-spacing: 0.06em;
        color: #17181a;
      }

      .terminal-header-left {
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .terminal-dots {
        display: inline-flex;
        gap: 4px;
      }

      .terminal-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
      }

      .terminal-dot.dot-red   { background: #fe3838ff; }
      .terminal-dot.dot-amber { background: #f7bc0bff; }
      .terminal-dot.dot-green { background: #08c54dff; }

      .terminal-title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        color: var(--term-bg);
        text-shadow: none;
      }

      .terminal-body {
        max-height: 340px;
        overflow: auto;
        padding: 10px 14px 12px;
        font-size: 12px;
        line-height: 1.4;
        background: var(--term-bg);
      }

      .terminal-block + .terminal-block {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #1f2937;
      }

      .terminal-block-title {
        font-weight: 600;
        margin-bottom: 4px;
        color: var(--term-green);
        text-shadow: none;
      }

      .terminal-pre {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        color: var(--term-text);
        background: transparent !important;
        border: 0;
        padding: 0;
      }

      .terminal-pre .term-line {
        display: block;
        padding-left: 1px;
      }

      .terminal-pre .term-line.log-ok    { color: var(--term-green);  font-weight: 500; }
      .terminal-pre .term-line.log-error { color: var(--term-red);    font-weight: 500; }
      .terminal-pre .term-line.log-warn  { color: var(--term-yellow); }
      .terminal-pre .term-line.log-time  { color: var(--term-cyan);   }
      .terminal-pre .term-line.log-dim   { color: var(--term-muted);  }
      .terminal-pre .term-line.log-head  { color: var(--term-text);   font-weight: 700; }
      .terminal-pre .term-line.log-file  { color: var(--term-purple); font-weight: 500; }
      .terminal-pre .term-line.log-count { color: var(--term-text);   font-weight: 500; }
      .terminal-pre .term-line.log-import{ color: var(--term-blue);   font-weight: 600; }
      .terminal-pre .term-line.log-skip  { color: var(--term-orange); font-weight: 500; }
      .terminal-pre .term-line.log-range { color: var(--term-text);   }

      .terminal-pre .term-label {
        color: var(--term-yellow);
        font-weight: 500;
      }
      .terminal-pre .term-value {
        color: var(--term-magenta);
        font-weight: 500;
      }
      .terminal-pre .term-link {
        color: var(--term-cyan);
        text-decoration: underline;
        text-decoration-style: dotted;
      }

      .terminal-caret {
        display: inline-block;
        width: 6px;
        height: 1.05em;
        margin-left: 2px;
        background: var(--term-green);
        box-shadow: 0 0 4px rgba(34,197,94,0.9);
        animation: term-caret 0.9s steps(1) infinite;
      }

      @keyframes term-caret {
        0%, 50%   { opacity: 1; }
        51%, 100% { opacity: 0; }
      }

      .btn-loading {
        opacity: .9;
        pointer-events: none;
      }
      .spinner-border {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        vertical-align: text-bottom;
        border: .15em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border .75s linear infinite;
      }
      @keyframes spinner-border {
        to { transform: rotate(360deg); }
      }
    </style>

    <!-- SCRIPT: update nama file + enable tombol + animasi terminal -->
    <script>
  (function () {
    function initImportForm() {
      var inputs    = document.querySelectorAll('.file-input');
      var btnSubmit = document.getElementById('btn-submit-import');
      var form      = document.getElementById('form-import-data');

      function refreshState() {
  var any = false;

  inputs.forEach(function (inp) {
    var files      = inp.files;
    var uploadBox  = inp.closest('.import-upload');
    var fileNameEl = uploadBox ? uploadBox.querySelector('.file-name') : null;

    if (files && files.length > 0) {
      any = true;
      if (fileNameEl) {
        fileNameEl.textContent = files[0].name;
        fileNameEl.classList.remove('file-name--placeholder');
        fileNameEl.classList.add('file-name--selected');
      }
    } else if (fileNameEl) {
      var placeholder = fileNameEl.dataset.placeholder || 'Belum ada file dipilih';
      fileNameEl.textContent = placeholder;
      fileNameEl.classList.remove('file-name--selected');
      fileNameEl.classList.add('file-name--placeholder');
    }
  });

  if (btnSubmit) {
    btnSubmit.disabled = !any;
  }
}


      inputs.forEach(function (inp) {
        var label = inp.parentElement;
        if (label && label.tagName === 'LABEL') {
          label.addEventListener('click', function () {
            inp.click();
          });
        }
        inp.addEventListener('change', refreshState);
      });

      if (form && btnSubmit) {
        form.addEventListener('submit', function () {
          btnSubmit.disabled = true;
          btnSubmit.classList.add('btn-loading');
          btnSubmit.innerHTML =
            '<span class="spinner-border me-1" role="status" aria-hidden="true"></span>' +
            'Menjalankan import...';
        });
      }

      refreshState();
    }

    function classifyLine(line) {
      var t = (line || '').trim();
      if (!t) return 'log-dim';

      if (/^selesai import\b/i.test(t)) {
        return 'log-head';
      }

      if (/^file json\b/i.test(t)) {
        return 'log-file';
      }
      if (/^(folder bai|output dir)\b/i.test(t)) {
        return 'log-file';
      }

      if (/^total\b/i.test(t)) {
        return 'log-count';
      }

      if (/^(imported orders\b|bai ter-import\b|anchor order\/no sc diproses\b|baris baru di tabel foto\b)/i.test(t)) {
        return 'log-import';
      }

      if (/^(skipped\b|pesan di-skip\b)/i.test(t)) {
        return 'log-skip';
      }

      if (/^rentang wib\b/i.test(t)) {
        return 'log-range';
      }

      if (/^(ok|done|success|berhasil)/i.test(t)) {
        return 'log-ok';
      }
      if (/^(error|err|fail|gagal)/i.test(t)) {
        return 'log-error';
      }
      if (/^(warn|warning|peringatan)/i.test(t)) {
        return 'log-warn';
      }
      if (/^\d{4}-\d{2}-\d{2}/.test(t)) {
        return 'log-time';
      }

      return 'log-dim';
    }

    function typeWriter(pre, onDone) {
      var full = pre.textContent || '';
      pre.textContent = '';

      var lines = full.split('\n');
      var lineIndex = 0;
      var charIndex = 0;
      var span = null;

      var caret = document.createElement('span');
      caret.className = 'terminal-caret';
      caret.textContent = ' ';
      pre.appendChild(caret);

      function step() {
        if (lineIndex >= lines.length) {
          if (typeof onDone === 'function') onDone();
          return;
        }

        if (!span) {
          span = document.createElement('span');
          span.className = 'term-line ' + classifyLine(lines[lineIndex]);
          pre.insertBefore(span, caret);
        }

        var line = lines[lineIndex];

        if (charIndex < line.length) {
          span.textContent += line.charAt(charIndex);
          charIndex++;
        } else {
          lineIndex++;
          charIndex = 0;
          span = null;

          if (lineIndex < lines.length) {
            pre.insertBefore(document.createTextNode('\n'), caret);
          }
        }

        var body = pre.closest('.terminal-body');
        if (body) {
          body.scrollTop = body.scrollHeight;
        }

        setTimeout(step, 6);
      }

      step();
    }

    function initTerminalAnimation() {
      var pres = document.querySelectorAll('.terminal-pre.js-typewriter');
      if (!pres.length) return;

      var idx = 0;
      function next() {
        if (idx >= pres.length) return;
        typeWriter(pres[idx], function () {
          idx++;
          next();
        });
      }
      next();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        initImportForm();
        initTerminalAnimation();
      });
    } else {
      initImportForm();
      initTerminalAnimation();
    }
  })();
    </script>

  </div><!-- /.content-wrapper -->
  <?php include "footer.php"; ?>
</div><!-- /.main-panel -->
