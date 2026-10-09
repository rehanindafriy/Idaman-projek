<?php
include "header.php";
require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

$successMsg = '';
$errorMsg   = '';

$idOrder = isset($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_order'])) {
  $idOrder = (int)($_POST['id_order'] ?? 0);
}

if ($idOrder <= 0) {
  ?>
  <div class="main-panel">
    <div class="content-wrapper">
      <div class="alert alert-danger">
        ID order tidak valid.
      </div>
    </div>
  </div>
  <?php
  include "footer.php";
  exit;
}

/**
 * Semua kolom yang boleh di-edit (ORDERS saja)
 * (kecuali id_order, created_at, bai_id, foto_id)
 */
$editableCols = [
  'order_type','no_sc','no_internet','no_voip','ncli','nama_pelanggan','alamat',
  'cp_pelanggan','sto','mitra','odp_wo','odp_real','port',
  'marking_dc_awal','marking_dc_akhir','ac_of_sm_1b','ac_of_sm_1_3sl','clamp_hook',
  'otp_ftth_1','prekso_intra_15_rs','prekso_intra_20_rs','rs_in_sc_1','s_clamp_sprinter',
  'soc_ils','soc_sum','qrcode','sn_ont_type','stb_id_type','tag_odp','tag_pelanggan',
  'labor','valins_id','slot','portt','ip_olt','hasil_valins','onu_gendong',
  'no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama',
  'sender_username','user_id'
];

function getOrderDetail(PDO $pdo, int $idOrder): ?array {
  $sql = "
    SELECT
      o.*,
      b.id_bai,
      b.kode_bai,
      b.nama_bai,
      b.uploader      AS bai_uploader,
      b.date_received AS bai_date,
      f.id_foto,
      f.kode_foto,
      f.uploader      AS foto_uploader,
      f.date_received AS foto_date
    FROM orders o
    /* =========================
       JOIN BAI & FOTO PAKAI no_sc
       ========================= */
    LEFT JOIN bai  b
      ON (
           b.kode_bai = o.no_sc
           OR b.nama_bai = o.no_sc
         )
    LEFT JOIN foto f
      ON (
           f.kode_foto = o.no_sc
         )
    WHERE o.id_order = :id
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':id' => $idOrder]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/* ========= AKSI HAPUS FILE BAI / FOTO (TOMBOL X) ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bai_file'], $_POST['bai_file_name'])) {
  $fileName = basename((string)$_POST['bai_file_name']); // sanitasi
  $path     = __DIR__ . '/bot/file/' . $fileName;

  if (is_file($path)) {
    if (@unlink($path)) {
      $successMsg = 'File BAI berhasil dihapus.';
    } else {
      $errorMsg = 'File BAI tidak dapat dihapus (cek permission).';
    }
  } else {
    $errorMsg = 'File BAI tidak ditemukan di server.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_foto_file'], $_POST['foto_file_name'], $_POST['foto_folder'])) {
  $fileName = basename((string)$_POST['foto_file_name']);
  $folder   = trim((string)$_POST['foto_folder']);
  $path     = __DIR__ . '/bot/foto/' . $folder . '/' . $fileName;

  if (is_file($path)) {
    if (@unlink($path)) {
      $successMsg = 'Foto berhasil dihapus.';
    } else {
      $errorMsg = 'Foto tidak dapat dihapus (cek permission).';
    }
  } else {
    $errorMsg = 'Foto tidak ditemukan di server.';
  }
}

function shorten_name(string $name, int $max = 40): string {
  if (mb_strlen($name) <= $max) {
    return $name;
  }
  return mb_substr($name, 0, $max - 3) . '...';
}

/**
 * Jika POST SIMPAN -> update orders + bai + foto
 * (dibatasi hanya jika tombol name="update_order" diklik)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_order'])
    && $idOrder > 0) {

  try {
    $pdo->beginTransaction();

    // ambil data lama
    $cur = getOrderDetail($pdo, $idOrder);
    if (!$cur) {
      throw new RuntimeException('Data order tidak ditemukan saat update.');
    }

    // --- UPDATE ORDERS ---
    $sets   = [];
    $params = [':id' => $idOrder];

    foreach ($editableCols as $col) {
      if (array_key_exists($col, $_POST)) {
        $val = trim((string)$_POST[$col]);
        if ($val === '') $val = '-';
        $sets[] = "`$col` = :$col";
        $params[":$col"] = $val;
      }
    }

    if ($sets) {
      $sqlUpd = "UPDATE orders SET ".implode(', ', $sets)." WHERE id_order = :id";
      $stUpd  = $pdo->prepare($sqlUpd);
      $stUpd->execute($params);
    }

    // --- UPDATE / INSERT BAI ---
    $kode_bai = trim((string)($_POST['kode_bai'] ?? ($cur['kode_bai'] ?? '')));
    $nama_bai = trim((string)($_POST['nama_bai'] ?? ($cur['nama_bai'] ?? '')));

    // jika kosong, samakan dengan NO_SC (sesuai aturan Press)
    if ($kode_bai === '' && !empty($cur['no_sc'])) {
      $kode_bai = $cur['no_sc'];
    }
    if ($nama_bai === '' && !empty($cur['no_sc'])) {
      $nama_bai = $cur['no_sc'];
    }

    if ($kode_bai !== '' || $nama_bai !== '') {
      if (!empty($cur['id_bai'])) {
        // update BAI
        $pdo->prepare("UPDATE bai SET kode_bai = ?, nama_bai = ? WHERE id_bai = ?")
            ->execute([$kode_bai, $nama_bai, $cur['id_bai']]);
      } else {
        // insert BAI baru dan set bai_id di orders
        $pdo->prepare("INSERT INTO bai (kode_bai, nama_bai, uploader, user_id)
                       VALUES (?, ?, ?, ?)")
            ->execute([
              $kode_bai,
              $nama_bai,
              $cur['sender_username'] ?? '-',
              $cur['user_id'] ?? 0
            ]);
        $newBaiId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE orders SET bai_id = ? WHERE id_order = ?")
            ->execute([$newBaiId, $idOrder]);
      }
    }

    // --- UPDATE / INSERT FOTO ---
    $kode_foto = trim((string)($_POST['kode_foto'] ?? ($cur['kode_foto'] ?? '')));

    // jika kosong, samakan dengan NO_SC (sesuai aturan Press)
    if ($kode_foto === '' && !empty($cur['no_sc'])) {
      $kode_foto = $cur['no_sc'];
    }

    if ($kode_foto !== '') {
      if (!empty($cur['id_foto'])) {
        $pdo->prepare("UPDATE foto SET kode_foto = ? WHERE id_foto = ?")
            ->execute([$kode_foto, $cur['id_foto']]);
      } else {
        $pdo->prepare("INSERT INTO foto (kode_foto, uploader, date_received)
                       VALUES (?, ?, NOW())")
            ->execute([
              $kode_foto,
              $cur['sender_username'] ?? '-'
            ]);
        $newFotoId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE orders SET foto_id = ? WHERE id_order = ?")
            ->execute([$newFotoId, $idOrder]);
      }
    }

    $pdo->commit();
    $successMsg = 'Data order, BAI, dan Foto berhasil diperbarui.';
  } catch (Throwable $ex) {
    $pdo->rollBack();
    $errorMsg = 'Gagal menyimpan perubahan: '.$ex->getMessage();
  }
}

/**
 * Ambil data final setelah update / hapus file
 */
$order = getOrderDetail($pdo, $idOrder);
if (!$order) {
  ?>
  <div class="main-panel">
    <div class="content-wrapper">
      <div class="alert alert-danger">
        Data order tidak ditemukan.
      </div>
    </div>
  </div>
  <?php
  include "footer.php";
  exit;
}

/**
 * Helper render field (VIEW + EDIT)
 */
function renderDetailField(string $name, string $label, array $row): void {
  $val = isset($row[$name]) ? (string)$row[$name] : '';
  ?>
  <div class="odetail-item">
    <div class="odetail-label"><?= e($label) ?></div>
    <div class="odetail-value">
      <span class="view-mode odetail-static">
        <?= $val !== '' && $val !== '-' ? e($val) : '<span class="text-muted">-</span>' ?>
      </span>

      <?php if ($name === 'alamat'): ?>
        <textarea
          class="form-control form-control-sm edit-mode"
          name="<?= e($name) ?>"
          rows="2"
        ><?= e($val) ?></textarea>
      <?php else: ?>
        <input
          type="text"
          class="form-control form-control-sm edit-mode"
          name="<?= e($name) ?>"
          value="<?= e($val) ?>"
        >
      <?php endif; ?>
    </div>
  </div>
  <?php
}

/** Mapping field ke label untuk ORDER (bawah) */
$leftFields = [
  'sto'              => 'STO',
  'mitra'            => 'Mitra',
  'no_sc'            => 'No SC',
  'no_internet'      => 'No Internet',
  'no_voip'          => 'No VoIP',
  'ncli'             => 'NCLI',
  'nama_pelanggan'   => 'Nama Pelanggan',
  'alamat'           => 'Alamat',
  'cp_pelanggan'     => 'CP Pelanggan',
  'odp_wo'           => 'ODP WO',
  'odp_real'         => 'ODP Real',
  'port'             => 'Port',
  'marking_dc_awal'  => 'Marking DC Awal',
  'marking_dc_akhir' => 'Marking DC Akhir',
  'ac_of_sm_1b'      => 'AC OF SM 1B',
  'ac_of_sm_1_3sl'   => 'AC OF SM 1 3SL',
  'clamp_hook'       => 'Clamp Hook',
];

$rightFields = [
  'order_type'    => 'Order Type',
  'soc_ils'       => 'SOC ILS',
  'soc_sum'       => 'SOC SUM',
  'qrcode'        => 'QR Code',
  'sn_ont_type'   => 'SN ONT Type',
  'stb_id_type'   => 'STB ID Type',
  'tag_odp'       => 'Tag ODP',
  'tag_pelanggan' => 'Tag Pelanggan',
  'labor'         => 'Labor',
  'valins_id'     => 'Valins ID',
  'slot'          => 'Slot',
  'portt'         => 'Portt',
  'ip_olt'        => 'IP OLT',
  'hasil_valins'  => 'Hasil Valins',
  'onu_gendong'   => 'ONU Gendong',
];

$legacyFields = [
  'no_inet_lama'  => 'No Internet Lama',
  'no_voip_lama'  => 'No VoIP Lama',
  'sn_ont_lama'   => 'SN ONT Lama',
  'stbid_lama'    => 'STB ID Lama',
];

$metaFields = [
  'sender_username' => 'Sender Username',
  'user_id'         => 'User ID',
  'bai_id'          => 'BAI ID',
  'foto_id'         => 'Foto ID',
];

/** Digabung untuk lookup label cepat */
$allFieldLabels = $leftFields + $rightFields + $legacyFields + $metaFields;

/** Tanggal created_at diformat */
$createdAtFmt = '-';
if (!empty($order['created_at'])) {
  $ts = strtotime($order['created_at']);
  if ($ts) {
    $bulanIndo = [
      1 => 'Januari', 2 => 'Februari', 3 => 'Maret',    4 => 'April',
      5 => 'Mei',     6 => 'Juni',     7 => 'Juli',     8 => 'Agustus',
      9 => 'September',10 => 'Oktober',11 => 'November',12 => 'Desember'
    ];
    $d   = date('d', $ts);
    $m   = (int)date('n', $ts);
    $y   = date('Y', $ts);
    $jam = date('H.i', $ts);
    $namaBulan = $bulanIndo[$m] ?? date('M', $ts);
    $createdAtFmt = "$d $namaBulan $y, $jam WIB";
  }
}

/**
 * CARI FILE BAI di bot/file (berdasarkan NAMA_BAI)
 * dan FOTO di bot/foto/NO_SC (berdasarkan NO_SC)
 */
$baseDir = __DIR__;
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$noSc    = $order['no_sc']    ?? '';
$namaBai = trim((string)($order['nama_bai'] ?? ''));

$baiFiles  = [];
$fotoFiles = [];

/* ==== BAI: berdasarkan NAMA_BAI (maksimal 1 file) ==== */
if ($namaBai !== '') {
  // full match & contain match
  $pExact = $baseDir . "/bot/file/" . $namaBai;
  $pGlob  = $baseDir . "/bot/file/*" . $namaBai . "*";

  $candidates = [];
  if (is_file($pExact)) {
    $candidates[] = $pExact;
  }
  $candidates = array_merge($candidates, glob($pGlob) ?: []);
  $candidates = array_unique($candidates);

  foreach ($candidates as $path) {
    if (is_file($path)) {
      $baiFiles[] = [
        'name' => basename($path),
        'url'  => $baseUrl . '/bot/file/' . rawurlencode(basename($path)),
      ];
    }
  }

  // maksimum 1 file BAI
  if (count($baiFiles) > 1) {
    $baiFiles = array_slice($baiFiles, 0, 1);
  }
}


/* ==== FOTO: bot/foto/NO_SC/*.jpg|jpeg|png ==== */
if ($noSc !== '') {
  $fotoDir = $baseDir . "/bot/foto/" . $noSc;
  if (is_dir($fotoDir)) {
    $exts = ['jpg','jpeg','png','JPG','JPEG','PNG'];
    foreach ($exts as $ext) {
      foreach (glob($fotoDir . "/*." . $ext) ?: [] as $path) {
        if (is_file($path)) {
          $fotoFiles[] = [
            'name' => basename($path),
            'url'  => $baseUrl . '/bot/foto/' . rawurlencode($noSc) . '/' . rawurlencode(basename($path)),
          ];
        }
      }
    }
  }
}

/* ==== STREET VIEW / MAPS dari TAG ODP & TAG PELANGGAN ==== */
$tagOdp        = trim((string)($order['tag_odp'] ?? ''));
$tagPelanggan  = trim((string)($order['tag_pelanggan'] ?? ''));

$mapsEmbedOdp   = '';
$mapsEmbedCust  = '';
$svEmbedOdp     = '';
$svEmbedCust    = '';

$buildQueryForMaps = function(string $tag): string {
  // Kalau format "lat,lon" -> pakai langsung
  if (preg_match('/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/', $tag, $m)) {
    return $m[1] . ',' . $m[2];
  }
  // Kalau teks biasa -> biarkan sebagai query
  return $tag;
};

$parseLatLng = function(string $tag): ?array {
  if (preg_match('/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/', $tag, $m)) {
    return ['lat' => $m[1], 'lng' => $m[2]];
  }
  return null;
};

/* --- MAPS biasa (atas) --- */
if ($tagOdp !== '') {
  $q = $buildQueryForMaps($tagOdp);
  $mapsEmbedOdp = 'https://www.google.com/maps?q=' . rawurlencode($q) . '&hl=id&z=18&output=embed';
}
if ($tagPelanggan !== '') {
  $q = $buildQueryForMaps($tagPelanggan);
  $mapsEmbedCust = 'https://www.google.com/maps?q=' . rawurlencode($q) . '&hl=id&z=18&output=embed';
}

/* --- Street View (alam, 360°) – butuh koordinat lat,lng --- */
if ($tagOdp !== '') {
  if ($coord = $parseLatLng($tagOdp)) {
    $lat = $coord['lat'];
    $lng = $coord['lng'];
    // embed Street View tanpa API key
    $svEmbedOdp = 'https://www.google.com/maps?q=&layer=c&cbll='
      . rawurlencode($lat . ',' . $lng)
      . '&cbp=11,0,0,0,0&hl=id&output=svembed';
  }
}
if ($tagPelanggan !== '') {
  if ($coord = $parseLatLng($tagPelanggan)) {
    $lat = $coord['lat'];
    $lng = $coord['lng'];
    $svEmbedCust = 'https://www.google.com/maps?q=&layer=c&cbll='
      . rawurlencode($lat . ',' . $lng)
      . '&cbp=11,0,0,0,0&hl=id&output=svembed';
  }
}

?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
  href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
  rel="stylesheet"
/>

<div class="main-panel">
  <div class="content-wrapper">

    <!-- ============ HEADER: SAMA PERSIS DENGAN HALAMAN DETAIL ============ -->
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <!-- kiri: judul + deskripsi (H2, P, P) -->
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Detail Data</h2>
            <p class="mb-0">
              Halaman ini menampilkan ringkasan dan detail lengkap Data Orders, termasuk informasi pelanggan,
              teknis, BAI, dan foto lapangan.
            </p>
            <p>
              Gunakan halaman ini untuk meninjau atau mengedit data secara terkontrol sebelum digunakan
              pada laporan, monitoring, dan kebutuhan operasional lainnya.
            </p>
          </div>

          <!-- kanan: logo + breadcrumb (layout, font, warna sama) -->
          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png"
                 alt="Logo e-Siklo"
                 class="mb-1"
                 style="height:45px; margin-top:-100px; display:block;">
            <br><br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon"
                 style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">
                &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
              </a>
              <span class="text-primary crumb-mid">Detail Data</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- WRAPPER KHUSUS HALAMAN DETAIL (UNTUK MODE VIEW / EDIT) -->
    <div class="odetail-wrapper" id="orderDetailWrapper">

      <!-- ================= HERO BANNER RINGKASAN (GRADIASI TETAP) ================= -->
      <div class="row">
        <div class="col-12 grid-margin stretch-card">
          <div class="card odetail-hero-card">
            <form method="post" id="orderEditForm">
              <input type="hidden" name="id_order" value="<?= (int)$idOrder ?>">

              <div class="odetail-hero">
                <div class="odetail-hero-top">
                  <!-- KIRI: INFO UTAMA -->
                  <div class="odetail-hero-main">
                    <div class="odetail-eyebrow">Ringkasan Order</div>
                    <h3 class="odetail-title">
                      <?= e($order['order_type'] ?: 'Order') ?>
                    </h3>
                    <div class="odetail-hero-line">
                      <span class="odetail-hero-line-label">Detail Order</span>
                      <span class="odetail-separator">·</span>
                      <span>STO <?= $order['sto'] !== '' && $order['sto'] !== '-' ? e($order['sto']) : '-' ?></span>
                      <span class="odetail-separator">·</span>
                      <span><?= $order['mitra'] !== '' && $order['mitra'] !== '-' ? e($order['mitra']) : '-' ?></span>
                    </div>

                    <div class="odetail-created">
                      <div class="odetail-created-iconwrap">
                        <i class="mdi mdi-calendar-month-outline odetail-created-icon"></i>
                      </div>
                      <div class="odetail-created-text">
                        <span class="odetail-created-label">Dibuat</span>
                        <span class="odetail-created-value">
                          <?= e($createdAtFmt) ?>
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- TENGAH: CHIP UTAMA (NO SC / NO INTERNET / CUSTOMER) -->
                  <div class="odetail-hero-center">
                    <div class="odetail-hero-chips">
                      <?php if (!empty($order['no_sc']) && $order['no_sc'] !== '-'): ?>
                        <div class="odetail-stat odetail-chip">
                          <div class="odetail-stat-label">NO SC</div>
                          <div class="odetail-stat-value odetail-stat-value-compact">
                            <?= e($order['no_sc']) ?>
                          </div>
                        </div>
                      <?php endif; ?>

                      <div class="odetail-stat odetail-chip">
                        <div class="odetail-stat-label">NO INTERNET</div>
                        <div class="odetail-stat-value">
                          <?= $order['no_internet'] !== '' && $order['no_internet'] !== '-' ? e($order['no_internet']) : '-' ?>
                        </div>
                      </div>

                      <div class="odetail-stat odetail-chip">
                        <div class="odetail-stat-label">CUSTOMER</div>
                        <div class="odetail-stat-value">
                          <?= $order['nama_pelanggan'] !== '' && $order['nama_pelanggan'] !== '-' ? e($order['nama_pelanggan']) : '-' ?>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- KANAN: TOMBOL -->
                  <div class="odetail-hero-side">
                    <div class="odetail-toolbar">
                      <button type="button"
                              class="btn btn-light btn-sm odetail-btn-back"
                              id="btnBack">
                        <i class="mdi mdi-arrow-left"></i> Kembali
                      </button>

                      <button type="button"
                              class="btn btn-primar btn-sm odetail-btn-edit"
                              id="btnEdit">
                        <i class="mdi mdi-pencil-outline"></i> Edit
                      </button>

                      <div class="odetail-btn-edit-actions">
                        <button type="submit"
                                name="update_order"
                                class="btn btn-primar btn-sm">
                          <i class="mdi mdi-content-save-outline"></i> Simpan
                        </button>
                        <button type="button"
                                class="btn btn-outline-light btn-sm"
                                id="btnCancel">
                          Batal
                        </button>
                      </div>
                    </div>
                  </div>
                </div><!-- /.odetail-hero-top -->
              </div><!-- /.odetail-hero -->
            </form>
          </div><!-- /.card -->
        </div>
      </div>

      <!-- ================= TOAST NOTIF ================= -->
      <?php if ($successMsg || $errorMsg): ?>
        <div class="efidi-toast-container">
          <div class="efidi-toast <?= $successMsg ? 'efidi-toast-success' : 'efidi-toast-error' ?>" id="efidiToast">
            <div class="efidi-toast-icon">
              <div class="efidi-toast-icon-circle">
                <i class="mdi <?= $successMsg ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline' ?>"></i>
              </div>
            </div>
            <div class="efidi-toast-body">
              <div class="efidi-toast-title">
                <?= $successMsg ? 'Berhasil' : 'Gagal' ?>
              </div>
              <div class="efidi-toast-text">
                <?= e($successMsg ?: $errorMsg) ?>
              </div>
            </div>
            <button type="button"
                    class="efidi-toast-close"
                    aria-label="Tutup">
              <i class="mdi mdi-close"></i>
            </button>
          </div>
        </div>
      <?php endif; ?>

      <!-- ================= CARD BESAR DETAIL (SEPERTI IMPORT DATA) ================= -->
      <div class="row">
        <div class="col-12 grid-margin stretch-card">
          <div class="card odetail-main-card">
            <div class="odetail-main-card-body">

              <!-- ================= BODY DETAIL (2 KOLOM) ================= -->
              <div class="row odetail-main-row">
                <!-- KOLOM KIRI: INFORMASI ORDER -->
                <div class="col-lg-8">
                  <!-- DATA PELANGGAN & ORDER -->
                  <div class="odetail-section-card">
                    <div class="odetail-section-header">
                      <div class="odetail-section-title">
                        <div class="odetail-section-bar"></div>
                        <span>Data Pelanggan &amp; Order</span>
                      </div>
                    </div>
                    <div class="odetail-section-body">
                      <?php
                      $infoFields = [
                        'no_sc','order_type','nama_pelanggan','alamat',
                        'cp_pelanggan','no_internet','no_voip',
                        'ncli','sto','mitra'
                      ];
                      foreach ($infoFields as $name):
                        $label = $allFieldLabels[$name] ?? strtoupper($name);
                        renderDetailField($name, $label, $order);
                      endforeach;
                      ?>
                    </div>
                  </div>

                  <!-- DATA TEKNIS & VALINS -->
                  <div class="odetail-section-card">
                    <div class="odetail-section-header">
                      <div class="odetail-section-title">
                        <div class="odetail-section-bar"></div>
                        <span>Data Teknis &amp; Valins</span>
                      </div>
                    </div>
                    <div class="odetail-section-body">
                      <?php
                      $teknisFields = [
                        'odp_wo','odp_real','port',
                        'marking_dc_awal','marking_dc_akhir',
                        'ac_of_sm_1b','ac_of_sm_1_3sl','clamp_hook',
                        'soc_ils','soc_sum','qrcode',
                        'sn_ont_type','stb_id_type',
                        'tag_odp','tag_pelanggan',
                        'labor','valins_id','slot','portt',
                        'ip_olt','hasil_valins','onu_gendong'
                      ];
                      foreach ($teknisFields as $name):
                        $label = $allFieldLabels[$name] ?? strtoupper($name);
                        renderDetailField($name, $label, $order);
                      endforeach;
                      ?>
                    </div>
                  </div>

                  <!-- DATA MIGRASI -->
                  <div class="odetail-section-card">
                    <div class="odetail-section-header">
                      <div class="odetail-section-title">
                        <div class="odetail-section-bar"></div>
                        <span>Data Migrasi &amp; Lainnya</span>
                      </div>
                    </div>
                    <div class="odetail-section-body">
                      <?php
                      $migrasiFields = [
                        'no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama'
                      ];
                      foreach ($migrasiFields as $name):
                        $label = $allFieldLabels[$name] ?? strtoupper($name);
                        renderDetailField($name, $label, $order);
                      endforeach;
                      ?>
                      <div class="odetail-item">
                        <div class="odetail-label">Created At (Raw)</div>
                        <div class="odetail-value">
                          <span class="odetail-static"><?= e($order['created_at'] ?? '-') ?></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- INFO INTERNAL -->
                  <div class="odetail-section-card">
                    <div class="odetail-section-header">
                      <div class="odetail-section-title">
                        <div class="odetail-section-bar"></div>
                        <span>Info Internal</span>
                      </div>
                    </div>
                    <div class="odetail-section-body">
                      <?php
                      $internalFields = ['sender_username','user_id','bai_id','foto_id'];
                      foreach ($internalFields as $name):
                        $label = $allFieldLabels[$name] ?? strtoupper($name);
                        renderDetailField($name, $label, $order);
                      endforeach;
                      ?>
                    </div>
                  </div>
                </div>

                <!-- KOLOM KANAN: BAI, FOTO, MAPS -->
                <div class="col-lg-4">
                  <div class="odetail-sidebar">

                    <!-- BAI -->
                    <div class="odetail-section-card odetail-sidebar-card odetail-sticky-top">
                      <div class="odetail-section-header">
                        <div class="odetail-section-title">
                          <div class="odetail-section-bar"></div>
                          <span>Berita Acara Instalasi (BAI)</span>
                        </div>
                      </div>
                      <div class="odetail-section-body">
                        <!-- Kode BAI -->
                        <div class="odetail-item">
                          <div class="odetail-label">Kode BAI</div>
                          <div class="odetail-value">
                            <span class="view-mode odetail-static">
                              <?= !empty($order['kode_bai']) && $order['kode_bai'] !== '-' ? e($order['kode_bai']) : '<span class="text-muted">-</span>' ?>
                            </span>
                            <input type="text"
                                   class="form-control form-control-sm edit-mode"
                                   name="kode_bai"
                                   form="orderEditForm"
                                   value="<?= e($order['kode_bai'] ?? '') ?>">
                          </div>
                        </div>

                        <!-- Nama file BAI -->
                        <div class="odetail-item">
                          <div class="odetail-label">Nama File BAI</div>
                          <div class="odetail-value">
                            <span class="view-mode odetail-static">
                              <?= !empty($order['nama_bai']) && $order['nama_bai'] !== '-' ? e($order['nama_bai']) : '<span class="text-muted">-</span>' ?>
                            </span>
                            <input type="text"
                                   class="form-control form-control-sm edit-mode"
                                   name="nama_bai"
                                   form="orderEditForm"
                                   value="<?= e($order['nama_bai'] ?? '') ?>">
                          </div>
                        </div>

                        <!-- Uploader -->
                        <div class="odetail-item">
                          <div class="odetail-label">Uploader &amp; Tanggal</div>
                          <div class="odetail-value">
                            <span class="odetail-static">
                              <?= e($order['bai_uploader'] ?? '-') ?> /
                              <?= e($order['bai_date'] ?? '-') ?>
                            </span>
                          </div>
                        </div>

                        <!-- File bot/file -->
                        <div class="odetail-item odetail-item-no-border">
                          <div class="odetail-label">File di bot/file</div>
                          <div class="odetail-value">
                            <?php if ($baiFiles): ?>
                              <?php foreach ($baiFiles as $f): ?>
                                <div class="bai-file-wrapper">
                                  <a href="<?= e($f['url']) ?>" target="_blank"
                                     class="file-pill text-decoration-none text-reset">
                                    <span class="d-inline-flex align-items-center gap-1">
                                      <img src="images/pdff.png" style="width:14px" class="me-1" alt="PDF">
                                      <?php $shortName = shorten_name($f['name'], 40); ?>
                                      <span class="text-truncate" title="<?= e($f['name']) ?>">
                                        <?= e($shortName) ?>
                                      </span>
                                    </span>
                                    <i class="mdi mdi-open-in-new"></i>
                                  </a>

                                  <form method="post" class="bai-delete-form">
                                    <input type="hidden" name="id_order" value="<?= (int)$idOrder ?>">
                                    <input type="hidden" name="bai_file_name" value="<?= e($f['name']) ?>">
                                    <button type="submit"
                                            name="delete_bai_file"
                                            class="btn-delete-x"
                                            title="Hapus file BAI">
                                      ✕
                                    </button>
                                  </form>
                                </div>
                              <?php endforeach; ?>
                            <?php else: ?>
                              <span class="text-muted">
                                <?php if ($namaBai !== ''): ?>
                                  File BAI dengan nama
                                  <code><?= e($namaBai) ?></code>
                                  tidak ditemukan di <code>bot/file</code>.
                                <?php else: ?>
                                  Belum ada informasi nama file BAI.
                                <?php endif; ?>
                              </span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- FOTO LAPANGAN -->
                    <div class="odetail-section-card odetail-sidebar-card">
                      <div class="odetail-section-header">
                        <div class="odetail-section-title">
                          <div class="odetail-section-bar"></div>
                          <span>Foto Lapangan</span>
                        </div>
                        <?php if ($noSc): ?>
                          <div class="odetail-section-subtitle small text-muted">
                            Folder: <code>bot/foto/<?= e($noSc) ?>/</code>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="odetail-section-body">
                        <div class="odetail-item">
                          <div class="odetail-label">Kode Foto</div>
                          <div class="odetail-value">
                            <span class="view-mode odetail-static">
                              <?= !empty($order['kode_foto']) && $order['kode_foto'] !== '-' ? e($order['kode_foto']) : '<span class="text-muted">-</span>' ?>
                            </span>
                            <input type="text"
                                   class="form-control form-control-sm edit-mode"
                                   name="kode_foto"
                                   form="orderEditForm"
                                   value="<?= e($order['kode_foto'] ?? '') ?>">
                          </div>
                        </div>

                        <div class="odetail-item">
                          <div class="odetail-label">Uploader &amp; Tanggal</div>
                          <div class="odetail-value">
                            <span class="odetail-static">
                              <?= e($order['foto_uploader'] ?? '-') ?> /
                              <?= e($order['foto_date'] ?? '-') ?>
                            </span>
                          </div>
                        </div>

                        <div class="odetail-item odetail-item-no-border">
                          <div class="odetail-label">Foto di bot/foto</div>
                          <div class="odetail-value">
                            <?php if ($fotoFiles): ?>
                              <div class="row g-2 odetail-foto-grid">
                                <?php foreach ($fotoFiles as $idx => $f): ?>
                                  <div class="col-4 col-sm-4">
                                    <div class="foto-thumb">
                                      <form method="post" class="foto-delete-form">
                                        <input type="hidden" name="id_order" value="<?= (int)$idOrder ?>">
                                        <input type="hidden" name="foto_folder" value="<?= e($noSc) ?>">
                                        <input type="hidden" name="foto_file_name" value="<?= e($f['name']) ?>">
                                        <button type="submit"
                                                name="delete_foto_file"
                                                class="btn-delete-x btn-delete-x-photo"
                                                title="Hapus foto ini">
                                          ✕
                                        </button>
                                      </form>

                                      <a href="<?= e($f['url']) ?>"

                                         class="d-block border rounded-3 overflow-hidden foto-preview-link"
                                         data-fotoname="<?= e($f['name']) ?>">
                                        <img src="<?= e($f['url']) ?>"
                                             alt="Foto <?= $idx+1 ?>"
                                             class="odetail-foto-img">
                                      </a>
                                    </div>
                                    <div class="small mt-1 text-truncate" title="<?= e($f['name']) ?>">
                                      <?= e($f['name']) ?>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            <?php else: ?>
                              <span class="text-muted">
                                Tidak ada foto di folder <code>bot/foto/<?= e($noSc) ?>/</code>.
                              </span>
                            <?php endif; ?>
                            <div class="small text-muted mt-1">
                              *Semua file gambar (<code>.jpg, .jpeg, .png</code>) di folder ini akan tampil sebagai gallery.
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- MAPS & STREET VIEW -->
                    <div class="odetail-section-card odetail-sidebar-card">
                      <div class="odetail-section-header">
                        <div class="odetail-section-title">
                          <div class="odetail-section-bar"></div>
                          <span>Street View / Maps</span>
                        </div>
                        <div class="odetail-section-subtitle small text-muted">
                          Peta &amp; tampilan 360° berdasarkan TAG ODP &amp; TAG PELANGGAN.
                        </div>
                      </div>
                      <div class="odetail-section-body">
                        <?php if ($mapsEmbedOdp || $svEmbedOdp || $mapsEmbedCust || $svEmbedCust): ?>
                          <div class="maps-view-grid">
                            <?php if ($mapsEmbedOdp || $svEmbedOdp): ?>
                              <?php if ($mapsEmbedOdp): ?>
                                <div class="maps-view-block">
                                  <div class="odetail-label">Lokasi ODP (TAG ODP)</div>
                                  <div class="maps-frame-wrap">
                                    <iframe
                                      src="<?= e($mapsEmbedOdp) ?>"

                                      loading="lazy"
                                      referrerpolicy="no-referrer-when-downgrade"
                                    ></iframe>
                                  </div>
                                  <div class="small text-muted mt-1">
                                    Tag: <?= e($tagOdp) ?>
                                  </div>
                                </div>
                              <?php endif; ?>

                              <?php if ($svEmbedOdp): ?>
                                <div class="maps-view-block mt-2">
                                  <div class="odetail-label">Street View ODP (360°)</div>
                                  <div class="maps-frame-wrap">
                                    <iframe
                                      src="<?= e($svEmbedOdp) ?>"
                                      loading="lazy"
                                      referrerpolicy="no-referrer-when-downgrade"
                                    ></iframe>
                                  </div>
                                </div>
                              <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($mapsEmbedCust || $svEmbedCust): ?>
                              <?php if ($mapsEmbedCust): ?>
                                <div class="maps-view-block mt-3">
                                  <div class="odetail-label">Lokasi Pelanggan (TAG PELANGGAN)</div>
                                  <div class="maps-frame-wrap">
                                    <iframe
                                      src="<?= e($mapsEmbedCust) ?>"
                                      loading="lazy"
                                      referrerpolicy="no-referrer-when-downgrade"
                                    ></iframe>
                                  </div>
                                  <div class="small text-muted mt-1">
                                    Tag: <?= e($tagPelanggan) ?>
                                  </div>
                                </div>
                              <?php endif; ?>

                              <?php if ($svEmbedCust): ?>
                                <div class="maps-view-block mt-2">
                                  <div class="odetail-label">Street View Pelanggan (360°)</div>
                                  <div class="maps-frame-wrap">
                                    <iframe
                                      src="<?= e($svEmbedCust) ?>"
                                      loading="lazy"
                                      referrerpolicy="no-referrer-when-downgrade"
                                    ></iframe>
                                  </div>
                                </div>
                              <?php endif; ?>
                            <?php endif; ?>
                          </div>

                          <div class="small text-muted mt-1">
                            *Street View hanya muncul bila TAG berisi koordinat
                            <code>lat,lng</code> (contoh: <code>-7.973154,112.610359</code>).
                          </div>
                        <?php else: ?>
                          <span class="text-muted">
                            Belum ada data peta. Isi kolom <code>tag_odp</code> dan
                            <code>tag_pelanggan</code> (format <code>lat,lng</code>)
                            untuk menampilkan Maps &amp; Street View.
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>

                  </div><!-- /.odetail-sidebar -->
                </div><!-- /.col-lg-4 -->
              </div><!-- /.row odetail-main-row -->

            </div><!-- /.odetail-main-card-body -->
          </div><!-- /.card odetail-main-card -->
        </div><!-- /.col-12 -->
      </div><!-- /.row -->
    </div><!-- /.odetail-wrapper -->

  <!-- MODAL PREVIEW FOTO -->
  <div class="modal fade" id="fotoPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content foto-modal-content">
        <div class="modal-body p-0">
          <button type="button" class="btn-close btn-close-white foto-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="foto-modal-imgwrap">
            <img src="" alt="Preview Foto" id="fotoPreviewImg">
          </div>
          <div class="foto-modal-caption small text-muted px-3 pb-3 pt-2" id="fotoPreviewCaption"></div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /.main-panel -->

<style>
:root{
  --efidi-teal:#00a3ad;
  --efidi-teal-dark:#00808b;
  --efidi-bg-soft:#f5f7fa;
  --efidi-border:#d4d7dd;
  --efidi-label:#111827;
  --efidi-text:#6b7280;
  --efidi-radius-card:8px;
  --efidi-radius-input:2px;
}

/* wrapper umum HANYA untuk konten detail, bukan header */
.odetail-wrapper{
  font-family:'Plus Jakarta Sans',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
  font-size:13px;
  color:var(--efidi-text);
}

/* ==== HERO BANNER ==== */
.odetail-hero-card{
  border-radius:12px;
  border:1px solid var(--efidi-border);
  background:#ffffff;
  margin-bottom:1px;
}
.odetail-hero{
  position:relative;
  padding:16px 24px 14px;
  border-radius:12px;
  overflow:hidden;
  background:
    radial-gradient(circle at -10% -20%, rgba(255,255,255,.95) 0, rgba(255,255,255,0) 55%),
    radial-gradient(circle at 110% -10%, rgba(255,255,255,.85) 0, rgba(255,255,255,0) 60%),
    linear-gradient(90deg,#e5f7f9 0%,#cceff4 35%,#8fd8df 100%);
}

.odetail-hero-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:24px;
}
.odetail-hero-main{flex:1.2;min-width:0;}
.odetail-hero-center{flex:1;min-width:0;}
.odetail-hero-side{flex:0 0 auto;}

/* teks di hero */
.odetail-eyebrow{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.18em;
  color:#03606c;
  margin-bottom:8px;
}
.odetail-title{
  margin:0;
  font-size:22px;
  font-weight:700;
  color:#014451;
  margin-bottom:8px;
}
.odetail-hero-line{
  font-size:12px;
  color:#0f3e47;
  opacity:.9;
  margin-bottom:4px;
}
.odetail-hero-line-label{font-weight:500;}
.odetail-separator{padding:0 4px;opacity:.6;}

/* created info */
.odetail-created{
  margin-top:4px;
  display:flex;
  align-items:center;
  gap:8px;
  font-size:11px;
  color:#0f3e47;
  opacity:.9;
}
.odetail-created-iconwrap{
  width:22px;
  height:22px;
  border-radius:999px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(1,109,121,.10);
  color:#014451;
}
.odetail-created-icon{font-size:14px;}
.odetail-created-text{display:flex;flex-direction:column;}
.odetail-created-label{
  text-transform:uppercase;
  letter-spacing:.18em;
  font-size:9px;
  font-weight:600;
  color:#6b7280;
  margin-bottom:1px;
}
.odetail-created-value{
  font-weight:600;
  font-size:11px;
}

/* chips */
.odetail-hero-chips{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}
.odetail-stat{
  flex:1 1 140px;
  min-width:0;
  padding:7px 12px 8px;
  border-radius:9px;
  border:1px solid rgba(255,255,255,.7);
  background:rgba(255,255,255,.20);
}
.odetail-chip .odetail-stat-label{color:rgba(161,17,17,.86);}
.odetail-stat-label{
  font-size:10px;
  text-transform:uppercase;
  letter-spacing:.18em;
  margin-bottom:2px;
}
.odetail-stat-value{
  font-size:14px;
  font-weight:700;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  color:#03606c;
}
.odetail-stat-value-compact{font-size:13px;}

/* toolbar */
.odetail-toolbar{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  align-items:center;
  justify-content:flex-end;
}
.odetail-toolbar .btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:12px;
  font-weight:500;
  line-height:1;
  padding:0 18px!important;
  border-radius:8px!important;
}
.odetail-toolbar .btn i{
  font-size:16px;
  margin-right:6px;
  vertical-align:-1px;
}
.odetail-btn-back{
  height:32px!important;
  background:#ffffff;
  border-color:#d1d5db;
  color:#111827;
}
.odetail-btn-back:hover{
  background:#f3f4f6;
  border-color:#cbd5e1;
  color:#020617;
}
.odetail-btn-edit{
  height:30px!important;
  background:#20a1ad;
  border-color:#20a1ad;
  color:#ffffff!important;
}
.odetail-btn-edit:hover{
  background:#17818b;
  border-color:#17818b;
  color:#ffffff!important;
}
.odetail-btn-edit-actions{
  display:none;
  gap:6px;
}
.odetail-btn-edit-actions .btn-primar{
  height:30px!important;
  background:#20a1ad;
  border-color:#20a1ad;
  color:#ffffff!important;
}
.odetail-btn-edit-actions .btn-primar:hover{
  background:#17818b;
  border-color:#17818b;
}
.odetail-btn-edit-actions .btn-outline-light{
  height:32px!important;
  background:#f9fafb;
  border-color:#d1d5db;
  color:#111827;
}
.odetail-btn-edit-actions .btn-outline-light:hover{
  background:#e5e7eb;
}

/* responsive hero */
@media (max-width: 992px){
  .odetail-hero{
    padding:12px 16px 12px;
  }
  .odetail-hero-top{
    flex-direction:column;
    gap:12px;
  }
  .odetail-toolbar{
    justify-content:flex-start;
  }
}
@media (max-width: 768px){
  .odetail-hero-card{
    border-radius:8px;
  }
}

/* ==== CARD BESAR LAYOUT DETAIL (SEPERTI IMPORT DATA) ==== */
.odetail-main-card{
  border-radius:4px;
  border:1px solid #e5e7eb;
  background:#ffffff;
}
.odetail-main-card-body{
  padding:14px 16px 12px;
}
.odetail-main-row{margin-top:2px;}

/* ==== SUB CARD DI DALAMNYA ==== */
.odetail-section-card{
  background:#f9fafb;
  border-radius:var(--efidi-radius-card);
  border:1px solid #d1d5db;
  padding:8px 12px 8px;
  margin-bottom:10px;
  box-shadow:0 8px 20px rgba(15,23,42,.04);
}
.odetail-section-header{
  display:flex;
  flex-direction:column;
  gap:2px;
  margin-bottom:6px;
}
.odetail-section-title{
  display:flex;
  align-items:center;
  gap:6px;
  font-size:11px;
  font-weight:600;
  letter-spacing:.10em;
  text-transform:uppercase;
  color:var(--efidi-label);
}
.odetail-section-bar{
  width:3px;
  height:14px;
  background:var(--efidi-teal);
}
.odetail-section-body{font-size:12px;}
.odetail-section-subtitle{font-size:11px;}

.odetail-item{
  padding:5px 0 5px;
  border-bottom:1px dashed var(--efidi-border);
}
.odetail-item:last-child{border-bottom:none;padding-bottom:0;}
.odetail-item-no-border{border-bottom:none;}
.odetail-label{
  font-size:10px;
  text-transform:uppercase;
  letter-spacing:.12em;
  color:var(--efidi-label);
  margin-bottom:2px;
}
.odetail-value{font-size:12px;color:var(--efidi-text);}
.odetail-static{
  display:block;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  min-height:18px;
}
.odetail-value .form-control,
.odetail-value textarea{
  font-size:12px;
  padding:4px 7px;
  border-radius:var(--efidi-radius-input);
}
.odetail-value textarea{resize:vertical;}

/* sidebar */
.odetail-sidebar{width:100%;}
.odetail-sidebar-card{font-size:12px;}
.odetail-sticky-top{
  position:sticky;
  top:90px;
  z-index:1;
}

/* file pill */
.file-pill{
  border-radius:var(--efidi-radius-card);
  padding:5px 8px;
  font-size:11px;
  border:1px solid #d1d5db;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:8px;
  background:#fafafa;
}
.bai-file-wrapper{
  position:relative;
  display:flex;
  align-items:center;
  gap:6px;
}

/* maps */
.maps-view-grid{
  display:flex;
  flex-direction:column;
  gap:8px;
}
.maps-view-block{width:100%;}
.maps-frame-wrap{
  border-radius:4px;
  overflow:hidden;
  border:1px solid #d1d5db;
  background:#e5e7eb;
}
.maps-frame-wrap iframe{
  width:100%;
  height:190px;
  border:0;
}

/* foto grid */
.odetail-foto-grid{margin-top:4px;}
.foto-thumb{position:relative;}
.odetail-foto-img{
  width:100%;
  height:70px;
  object-fit:cover;
  display:block;
}

/* tombol X (hapus) */
.btn-delete-x{
  position:absolute;
  top:4px;
  right:4px;
  width:18px;
  height:18px;
  border-radius:var(--efidi-radius-card);
  border:none;
  background:#ef4444;
  color:#ffffff;
  font-size:11px;
  line-height:1;
  display:none;
  align-items:center;
  justify-content:center;
  cursor:pointer;
  box-shadow:0 2px 4px rgba(0,0,0,.18);
}
.btn-delete-x-photo{top:6px;right:6px;}

/* MODE VIEW / EDIT GLOBAL */
.odetail-wrapper .edit-mode{display:none;}
.odetail-wrapper.is-editing .view-mode{display:none;}
.odetail-wrapper.is-editing .edit-mode{display:block;}
.odetail-wrapper.is-editing .odetail-btn-edit{display:none;}
.odetail-wrapper.is-editing .odetail-btn-edit-actions{display:flex;}
.odetail-wrapper.is-editing .btn-delete-x{display:flex;}

/* toast */
.efidi-toast-container{
  position:fixed;
  bottom:18px;
  right:20px;
  z-index:9999;
  pointer-events:none;
}
.efidi-toast{
  display:flex;
  align-items:center;
  gap:8px;
  max-width:280px;
  background:#ffffff;
  border-radius:6px;
  padding:8px 12px;
  box-shadow:0 10px 30px rgba(15,23,42,.20);
  border:1px solid rgba(148,163,184,.40);
  pointer-events:auto;
  animation:efidiToastIn .25s ease-out;
}
.efidi-toast-success{border-color:rgba(0,163,173,.55);}
.efidi-toast-error{border-color:rgba(248,113,113,.70);}
.efidi-toast-icon{display:flex;align-items:center;justify-content:center;}
.efidi-toast-icon-circle{
  width:22px;
  height:22px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(0,163,173,.12);
  color:var(--efidi-teal-dark);
  font-size:14px;
}
.efidi-toast-error .efidi-toast-icon-circle{
  background:#fee2e2;
  color:#b91c1c;
}
.efidi-toast-body{flex:1;min-width:0;}
.efidi-toast-title{
  font-size:11px;
  font-weight:600;
  text-transform:uppercase;
  letter-spacing:.10em;
  color:#03606c;
  margin-bottom:1px;
}
.efidi-toast-error .efidi-toast-title{color:#b91c1c;}
.efidi-toast-text{
  font-size:12px;
  color:#111827;
  line-height:1.3;
  white-space:nowrap;
  text-overflow:ellipsis;
  overflow:hidden;
}
.efidi-toast-close{
  border:none;
  background:transparent;
  color:#9ca3af;
  padding:0;
  margin-left:4px;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.efidi-toast-close i{font-size:16px;}
.efidi-toast-close:hover{color:#4b5563;}
@keyframes efidiToastIn{
  0%{opacity:0;transform:translate(10px,10px) scale(.96);}
  100%{opacity:1;transform:translate(0,0) scale(1);}
}
@keyframes efidiToastOut{
  0%{opacity:1;transform:translate(0,0) scale(1);}
  100%{opacity:0;transform:translate(6px,6px) scale(.96);}
}
.efidi-toast.is-hiding{
  animation:efidiToastOut .22s ease-in forwards;
}

/* modal foto */
.foto-modal-content{
  background:#000000;
  border-radius:6px;
  overflow:hidden;
}
.foto-modal-imgwrap{
  background:#000;
  display:flex;
  align-items:center;
  justify-content:center;
  max-height:70vh;
}
.foto-modal-imgwrap img{
  max-width:100%;
  max-height:70vh;
  object-fit:contain;
}
.foto-modal-close{
  position:absolute;
  top:6px;
  right:8px;
  z-index:5;
}

/* responsive section card */
@media (max-width: 992px){
  .odetail-section-card{
    padding:8px 10px 8px;
  }
  .odetail-sticky-top{
    position:static;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const wrapper = document.getElementById('orderDetailWrapper');
  const form    = document.getElementById('orderEditForm');
  const btnEdit   = document.getElementById('btnEdit');
  const btnCancel = document.getElementById('btnCancel');
  const btnBack   = document.getElementById('btnBack');

  if (btnEdit && wrapper){
    btnEdit.addEventListener('click', function(e){
      e.preventDefault();
      wrapper.classList.add('is-editing');
    });
  }

  if (btnCancel && wrapper && form){
    btnCancel.addEventListener('click', function(e){
      e.preventDefault();
      wrapper.classList.remove('is-editing');
      form.reset();
    });
  }

 if (btnBack) {
  btnBack.addEventListener('click', function (e) {
    e.preventDefault();

    // pakai halaman sebelumnya kalau memang dari data.php
    const ref = document.referrer || '';

    if (ref && ref.indexOf('data.php') !== -1) {
      // kembali ke list dengan query yang sama (?mode=..., ?page=..., dll)
      window.location.href = ref;
    } else {
      // fallback kalau langsung buka halaman detail dari URL / bookmark
      window.location.href = 'data.php';
    }
  });
}


  // toast auto hide
  const efidiToast = document.getElementById('efidiToast');
  const efidiToastClose = document.querySelector('.efidi-toast-close');
  if (efidiToast){
    setTimeout(function(){
      efidiToast.classList.add('is-hiding');
    }, 10000);
    efidiToast.addEventListener('animationend', function(ev){
      if (ev.animationName === 'efidiToastOut'){
        efidiToast.remove();
      }
    });
  }
  if (efidiToast && efidiToastClose){
    efidiToastClose.addEventListener('click', function(){
      efidiToast.classList.add('is-hiding');
    });
  }

  // preview foto
  const modalEl = document.getElementById('fotoPreviewModal');
  const modalImg = document.getElementById('fotoPreviewImg');
  const modalCaption = document.getElementById('fotoPreviewCaption');
  let bsModal = null;

  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal){
    bsModal = new bootstrap.Modal(modalEl);
  }

  document.querySelectorAll('.foto-preview-link').forEach(function(link){
    link.addEventListener('click', function(e){
      e.preventDefault();
      const href = this.getAttribute('href');
      const name = this.getAttribute('data-fotoname') || '';

      if (bsModal){
        modalImg.src = href;
        modalCaption.textContent = name;
        bsModal.show();
      } else {
        window.open(href, '_blank');
      }
    });
  });
});
</script>

<?php
include "footer.php";
?>
