<?php
include "header.php";
require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
  function e($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

/**
 * Kolom yang akan di-insert ke tabel orders
 * (sama seperti editableCols di halaman detail)
 */
$editableCols = [
  'order_type','no_sc','no_internet','no_voip','ncli','nama_pelanggan','alamat',
  'cp_pelanggan','sto','mitra','odp_wo','odp_real','port',
  'marking_dc_awal','marking_dc_akhir','ac_of_sm_1b','ac_of_sm_1_3sl','clamp_hook',
  'otp_ftth_1','prekso_intra_15_rs','prekso_intra_20_rs','rs_in_sc_1','s_clamp_sprinter',
  'soc_ils','soc_sum','qrcode','sn_ont_type','stb_id_type','tag_odp','tag_pelanggan',
  'labor','valins_id','slot','portt','ip_olt','hasil_valins','onu_gendong',
  'no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama',
  'sender_username','user_id','bai_id','foto_id'
];

/**
 * Field yang wajib diisi
 */
$requiredFields = [
  'order_type'     => 'Order Type',
  'no_sc'          => 'No SC',
  'no_internet'    => 'No Internet',
  'nama_pelanggan' => 'Nama Pelanggan',
  'sto'            => 'STO',
  'mitra'          => 'Mitra',
  'odp_real'       => 'ODP Real',
  'port'           => 'Port',
];
$requiredKeys = array_keys($requiredFields);

$successMsg = '';
$errorMsg   = '';

/**
 * Ambil info user login untuk mengisi sender_username & user_id otomatis
 * >>> SESUAIKAN NAMA SESSION DI SINI DENGAN SISTEMMU <<<
 */
$senderUsernameSession = $_SESSION['username']      ?? ($_SESSION['akses_username'] ?? '-');
$chatIdSession         = $_SESSION['chat_id']       ?? ($_SESSION['akses_chat_id']  ?? '-');

/**
 * Data form (supaya sticky saat error)
 */
$formData = array_fill_keys($editableCols, '');

/**
 * Helper render field input (mirip detail, tapi full edit mode)
 */
function renderAddField(string $name, string $label, array $data, bool $required = false): void {
  $val = isset($data[$name]) ? (string)$data[$name] : '';
  ?>
  <div class="odetail-item">
    <div class="odetail-label">
      <?= e($label) ?>
      <?php if ($required): ?>
        <span class="text-danger">*</span>
      <?php endif; ?>
    </div>
    <div class="odetail-value">
      <?php if ($name === 'alamat'): ?>
        <textarea
          class="form-control form-control-sm"
          name="<?= e($name) ?>"
          rows="2"
          <?= $required ? 'required' : '' ?>
        ><?= e($val) ?></textarea>
      <?php else: ?>
        <input
          type="text"
          class="form-control form-control-sm"
          name="<?= e($name) ?>"
          value="<?= e($val) ?>"
          <?= $required ? 'required' : '' ?>
        >
      <?php endif; ?>
    </div>
  </div>
  <?php
}

/**
 * PROSES SUBMIT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {

  // Baca semua field dari POST untuk sticky form (kecuali meta otomatis)
  foreach ($editableCols as $col) {
    if (in_array($col, ['sender_username','user_id','bai_id','foto_id'], true)) {
      continue;
    }
    $formData[$col] = trim((string)($_POST[$col] ?? ''));
  }

  // Validasi field wajib
  $missing = [];
  foreach ($requiredFields as $field => $label) {
    if (($formData[$field] ?? '') === '') {
      $missing[] = $label;
    }
  }

  if ($missing) {
    $errorMsg = 'Field wajib berikut belum diisi: '.implode(', ', $missing).'.';
  } else {
    // Siapkan data untuk INSERT (konversi '' ke '-')
    $insertCols = $editableCols;
    $params     = [];

  foreach ($insertCols as $col) {
  if ($col === 'sender_username') {
    $v = $senderUsernameSession !== '' ? $senderUsernameSession : '-';

  } elseif ($col === 'user_id') {
    // pastikan numeric, kalau tidak ada → 0
    $v = ctype_digit((string)$chatIdSession) ? (int)$chatIdSession : 0;

  } elseif ($col === 'bai_id' || $col === 'foto_id') {
    // default 0 (belum ter-link BAI / foto)
    $v = 0;

  } else {
    $raw = $formData[$col] ?? '';
    $v   = trim((string)$raw);
    if ($v === '') $v = '-';
  }

  $params[":$col"] = $v;
}


    $colsSql = implode(', ', array_map(fn($c) => "`$c`", $insertCols));
    $valsSql = implode(', ', array_map(fn($c) => ":$c", $insertCols));

    try {
      $st = $pdo->prepare("INSERT INTO `orders` ($colsSql) VALUES ($valsSql)");
      $st->execute($params);
      $newId     = $pdo->lastInsertId();
      $successMsg = 'Order baru berhasil ditambahkan'.($newId ? ' (ID #'.$newId.')' : '').'.';

      // Reset form setelah sukses
      foreach ($editableCols as $col) {
        $formData[$col] = '';
      }
    } catch (Throwable $ex) {
      $errorMsg = 'Gagal menyimpan data: '.$ex->getMessage();
    }
  }
}

/**
 * Nilai awal untuk hero (ringkasan order)
 */
$heroOrderType = $formData['order_type']     !== '' ? $formData['order_type']     : 'Order Baru';
$heroNoSc      = $formData['no_sc']          !== '' ? $formData['no_sc']          : '-';
$heroSto       = $formData['sto']            !== '' ? $formData['sto']            : '-';
$heroMitra     = $formData['mitra']          !== '' ? $formData['mitra']          : '-';
$heroNoInet    = $formData['no_internet']    !== '' ? $formData['no_internet']    : '-';
$heroCustomer  = $formData['nama_pelanggan'] !== '' ? $formData['nama_pelanggan'] : '-';

/**
 * Mapping field ke kolom / section
 */
$leftFields = [
  'sto'            => 'STO',
  'mitra'          => 'Mitra',
  'no_sc'          => 'No SC',
  'no_internet'    => 'No Internet',
  'no_voip'        => 'No VoIP',
  'ncli'           => 'NCLI',
  'nama_pelanggan' => 'Nama Pelanggan',
  'alamat'         => 'Alamat',
  'cp_pelanggan'   => 'CP Pelanggan',
  'odp_wo'         => 'ODP WO',
  'odp_real'       => 'ODP Real',
  'port'           => 'Port',
];

$rightFields = [
  'order_type'   => 'Order Type',
  'soc_ils'      => 'SOC ILS',
  'soc_sum'      => 'SOC SUM',
  'qrcode'       => 'QR Code',
  'sn_ont_type'  => 'SN ONT Type',
  'stb_id_type'  => 'STB ID Type',
  'tag_odp'      => 'Tag ODP',
  'tag_pelanggan'=> 'Tag Pelanggan',
  'labor'        => 'Labor',
  'valins_id'    => 'Valins ID',
  'slot'         => 'Slot',
  'portt'        => 'Portt',
  'ip_olt'       => 'IP OLT',
  'hasil_valins' => 'Hasil Valins',
  'onu_gendong'  => 'ONU Gendong',
];

$extraTeknis = [
  'otp_ftth_1'        => 'OTP FTTH 1',
  'prekso_intra_15_rs'=> 'Prekso Intra 15 RS',
  'prekso_intra_20_rs'=> 'Prekso Intra 20 RS',
  'rs_in_sc_1'        => 'RS IN SC 1',
  's_clamp_sprinter'  => 'S Clamp Sprinter',
];

$legacyFields = [
  'no_inet_lama'  => 'No Internet Lama',
  'no_voip_lama'  => 'No VoIP Lama',
  'sn_ont_lama'   => 'SN ONT Lama',
  'stbid_lama'    => 'STB ID Lama',
];

?>
<div class="main-panel">
  <div class="content-wrapper">

    <!-- HEADER EFIDI -->
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
  <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-2 mt-n30"> <!-- mt-n1 rapetin lagi -->
  <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>

  <a href="index.php" class="text-muted mx-1 crumb-mid">
  &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
</a>

    <span class="text-primary crumb-mid">Data Orders</span>
  </div>
</div>

</div>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

    <!-- CARD FORM -->
    <div class="row">
      <div class="col-12 grid-margin stretch-card">
        <div class="card odetail-card" id="orderAddCard">
          <div class="card-body p-0">
            <form method="post" id="orderAddForm" autocomplete="off">

         <!-- HERO / HEADER TAMBAH ORDER – 3 LEVEL: TITLE → CHIP RINGKASAN → 5 SECTION -->
<div class="odetail-hero">
  <div class="odetail-hero-inner">
    <!-- KIRI: judul + ringkasan + chip + 5 section -->
    <div class="odetail-hero-main">
      <div class="odetail-eyebrow">FORM ORDER BARU</div>

      <h3 class="odetail-title" id="heroOrderType">
        <?= e($heroOrderType) ?>
      </h3>

      <div class="odetail-hero-line">
        <span class="odetail-hero-line-label">Ringkasan Order</span>
        <span class="odetail-separator">·</span>
        <span>STO <span id="heroStoVal"><?= e($heroSto) ?></span></span>
        <span class="odetail-separator">·</span>
        <span>MITRA <span id="heroMitraVal"><?= e($heroMitra) ?></span></span>
      </div>

     

      <!-- 5 SECTION SESUAI BLOK DI BAWAH -->
      <div class="odetail-section-row">
        <span class="odetail-section-pill is-active">Data Pelanggan &amp; Lokasi</span>
        <span class="odetail-section-pill is-active">Data Teknis &amp; Valins</span>
        <span class="odetail-section-pill is-active">Data Teknis Tambahan</span>
        <span class="odetail-section-pill is-active">Data Lama (Migrasi)</span>
        <span class="odetail-section-pill is-active">Info Internal (Otomatis)</span>
      </div>
    </div>

    <!-- KANAN: PETUGAS + TOMBOL -->
   <div class="odetail-hero-side">
  <div class="odetail-meta-user">
    <div class="odetail-meta-user-label">Users EFIDI.</div>
    <div class="odetail-meta-user-name">
      <?= e($senderUsernameSession ?: 'Petugas Login') ?>
    </div>
    <div class="odetail-meta-user-id">
      Chat ID: <?= e($chatIdSession ?: '-') ?>
    </div>
  </div>


      <div class="odetail-toolbar">
        <button type="button"
                class="btn btn-light btn-sm odetail-btn-back"
                id="btnBack">
          <i class="mdi mdi-arrow-left"></i> Kembali
        </button>

        <button type="reset"
                class="btn btn-outline-dark btn-sm">
          Reset
        </button>

        <button type="submit"
                name="add_order"
                class="btn btn-primar btn-sm">
          <i class="mdi mdi-content-save-outline"></i> Simpan Order
        </button>
      </div>
    </div>
  </div>

  <!-- MINI CHIP DI BAWAH (OPSIONAL) -->
  <div class="odetail-hero-bottom">
    <div class="odetail-hero-bottom-chips">
      <div class="odetail-mini-chip">
        <span class="odetail-mini-label">NO SC</span>
        <span class="odetail-mini-value" id="heroNoScVal2">
          <?= e($heroNoSc) ?>
        </span>
      </div>
      <div class="odetail-mini-chip">
        <span class="odetail-mini-label">NO INTERNET</span>
        <span class="odetail-mini-value" id="heroNoInetVal2">
          <?= e($heroNoInet) ?>
        </span>
      </div>
      <div class="odetail-mini-chip">
        <span class="odetail-mini-label">CUSTOMER</span>
        <span class="odetail-mini-value" id="heroCustVal">
          <?= e($heroCustomer) ?>
        </span>
      </div>
    </div>
  </div>
</div>



              <!-- TOAST EFIDI -->
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

              <!-- BODY FORM -->
              <div class="odetail-body">
                <div class="odetail-columns">
                  <!-- KIRI -->
                  <div class="odetail-column">
                    <div class="odetail-column-title">
                      <i class="mdi mdi-account-details odetail-column-icon"></i>
                      <span>Data Pelanggan & Lokasi</span>
                    </div>
                    <?php foreach ($leftFields as $name => $label): ?>
                      <?php renderAddField($name, $label, $formData, in_array($name, $requiredKeys, true)); ?>
                    <?php endforeach; ?>
                  </div>

                  <!-- KANAN -->
                  <div class="odetail-column">
                    <div class="odetail-column-title">
                      <i class="mdi mdi-access-point-network odetail-column-icon"></i>
                      <span>Data Teknis & Valins</span>
                    </div>
                    <?php foreach ($rightFields as $name => $label): ?>
                      <?php renderAddField($name, $label, $formData, in_array($name, $requiredKeys, true)); ?>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- SECTION TAMBAHAN -->
                <div class="odetail-extra">
                  <!-- TEKNIS TAMBAHAN -->
                  <div class="odetail-extra-card">
                    <div class="odetail-column-title odetail-column-title-soft">
                      <i class="mdi mdi-tools odetail-column-icon"></i>
                      <span>Data Teknis Tambahan</span>
                    </div>
                    <div class="odetail-extra-grid">
                      <?php foreach ($extraTeknis as $name => $label): ?>
                        <?php renderAddField($name, $label, $formData, false); ?>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- DATA LAMA -->
                  <div class="odetail-extra-card">
                    <div class="odetail-column-title odetail-column-title-soft">
                      <i class="mdi mdi-history odetail-column-icon"></i>
                      <span>Data Lama (Migrasi)</span>
                    </div>
                    <div class="odetail-extra-grid">
                      <?php foreach ($legacyFields as $name => $label): ?>
                        <?php renderAddField($name, $label, $formData, false); ?>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- INFO INTERNAL -->
                  <div class="odetail-extra-card">
                    <div class="odetail-column-title odetail-column-title-soft">
                      <i class="mdi mdi-shield-account-outline odetail-column-icon"></i>
                      <span>Info Internal (Otomatis)</span>
                    </div>
                    <div class="odetail-extra-grid">
                      <div class="odetail-item">
                        <div class="odetail-label">Sender Username</div>
                        <div class="odetail-value">
                          <span class="badge bg-light text-dark">
                            <?= e($senderUsernameSession ?: '-') ?>
                          </span>
                          <small class="text-muted d-block">
                            Terisi otomatis dari user yang login.
                          </small>
                        </div>
                      </div>
                      <div class="odetail-item">
                        <div class="odetail-label">User ID (Chat ID)</div>
                        <div class="odetail-value">
                          <span class="badge bg-light text-dark">
                            <?= e($chatIdSession ?: '-') ?>
                          </span>
                          <small class="text-muted d-block">
                            Terisi otomatis dari akun Telegram terhubung.
                          </small>
                        </div>
                      </div>
                      <div class="odetail-item">
                        <div class="odetail-label">BAI ID</div>
                        <div class="odetail-value">
                          <span class="text-muted">Akan terisi otomatis saat BAI di-link ke order ini.</span>
                        </div>
                      </div>
                      <div class="odetail-item">
                        <div class="odetail-label">Foto ID</div>
                        <div class="odetail-value">
                          <span class="text-muted">Akan terisi otomatis saat foto di-link ke order ini.</span>
                        </div>
                      </div>
                    </div>
                  </div>

                </div><!-- /.odetail-extra -->
              </div><!-- /.odetail-body -->
            </form>
          </div>
        </div>
    </div><!-- /.row -->
  </div><!-- /.content-wrapper -->
</div><!-- /.main-panel -->

<style>
  :root{
    --efidi-teal:       #00a3ad;
    --efidi-teal-dark:  #00808b;
    --efidi-teal-soft:  #13c4ce;
    --efidi-purple:     #46dde5ff;
    --efidi-bg-soft:    #f3f6fb;
    --efidi-border:     #e5e7eb;
    --efidi-label:      #9ca3af;
    --efidi-text:       #111827;
  }

  /* =============== PAGE HEADER ATAS =============== */
  .page-header-efidi{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    padding:14px 18px 10px;
    border-radius:12px;
    background:linear-gradient(115deg,#ffffff 0%,#f9fafb 40%,#f3f6fb 100%);
    border:1px solid var(--efidi-border);
    
  }
  .page-header-main{
    max-width: 62%;
  }
  .page-header-eyebrow{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.16em;
    color:var(--efidi-label);
    margin-bottom:2px;
  }
  .page-header-title{
    font-size:1.6rem;
    font-weight:600;
    margin-bottom:4px;
    color:var(--efidi-text);
  }
  .page-header-text{
    font-size:13px;
    color:#4b5563;
  }

  .page-header-side{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    gap:8px;
  }
  .page-header-logo img{
    height:40px;
    display:block;
  }
  .page-header-breadcrumb{
    display:flex;
    align-items:center;
    gap:4px;
    font-size:12px;
    color:#6b7280;
  }
  .breadcrumb-icon{
    font-size:16px;
  }
  .breadcrumb-link{
    color:#6b7280;
    text-decoration:none;
  }
  .breadcrumb-link:hover{
    color:var(--efidi-teal);
  }
  .breadcrumb-divider{
    padding:0 2px;
  }
  .breadcrumb-current{
    color:var(--efidi-teal-dark);
    font-weight:600;
  }

  /* =============== KARTU DETAIL / FORM =============== */
  .odetail-card{
    border-radius:16px;
    border:1px solid var(--efidi-border);
    
    overflow:hidden;
  }
  .odetail-card .card-body{
    padding:0;
    background:radial-gradient(circle at top left,#ffffff 0,var(--efidi-bg-soft) 65%);
  }

  /* =================== EFIDI TOAST =================== */
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
    border-radius:999px;
    padding:8px 12px;
    box-shadow:0 10px 30px rgba(15,23,42,.20);
    border:1px solid rgba(15,23,42,.06);
    pointer-events:auto;
    animation:efidiToastIn .25s ease-out;
  }
  .efidi-toast-success{
    border-color:rgba(0,163,173,.35);
  }
  .efidi-toast-error{
    border-color:rgba(248,113,113,.55);
  }
  .efidi-toast-icon{
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .efidi-toast-icon-circle{
    width:22px;
    height:22px;
    border-radius:999px;
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
  .efidi-toast-body{
    flex:1;
    min-width:0;
  }
  .efidi-toast-title{
    font-size:11px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.10em;
    color:#03606c;
    margin-bottom:1px;
  }
  .efidi-toast-error .efidi-toast-title{
    color:#b91c1c;
  }
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
  .efidi-toast-close i{
    font-size:16px;
  }
  .efidi-toast-close:hover{
    color:#4b5563;
  }
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

 /* =================== BANNER / HERO TAMBAH ORDER =================== */
/* Gradiasi & warna sama seperti view_data, layout lebih rapi */
/* =================== BANNER / HERO TAMBAH ORDER =================== */
/* Gradiasi sama dengan view_data, layout: TITLE → CHIP → 5 SECTION */

.odetail-hero{
  position:relative;
  padding: 15px 24px 15px;
  border-radius:17px 17px 0 0;
  overflow:hidden;
  background:
    radial-gradient(circle at -10% -20%, rgba(255,255,255,.95) 0, rgba(255,255,255,0) 55%),
    radial-gradient(circle at 110% -10%, rgba(255,255,255,.85) 0, rgba(255,255,255,0) 60%),
    linear-gradient(90deg,#e5f7f9 0%,#cceff4 35%,#8fd8df 100%);
}

/* WRAPPER UTAMA: kiri (judul) & kanan (petugas + tombol) */
.odetail-hero-inner{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:24px;
}

.odetail-hero-main{
  min-width:0;
  max-width:520px;
}

.odetail-eyebrow{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.18em;
  color:#03606c;
  margin-bottom:6px;
}

.odetail-title{
  margin:0;
  font-size:20px;
  font-weight:700;
  color:#014451;
  margin-bottom:6px;
}

.odetail-hero-line{
  font-size:11px;
  color:#0f3e47;
  opacity:.95;
}

.odetail-hero-line-label{
  font-weight:500;
}

.odetail-separator{
  padding:0 4px;
  opacity:.6;
}

/* 3 CHIP RINGKASAN – TEPAT DI BAWAH TEKS RINGKASAN ORDER */
.odetail-hero-chips{
  margin-top:8px;
  display:flex;
  gap:10px;
}

.odetail-summary-card{
  flex:1 1 0;
  min-width:0;
  padding: 8px 14px 9px;
  border-radius:10px;
  border:1px solid rgba(255,255,255,.82);
  background:rgba(255,255,255,.88);
  box-shadow:0 12px 26px rgba(129,199,212,.45);
}

.odetail-summary-label{
  font-size:10px;
  text-transform:uppercase;
  letter-spacing:.18em;
  color:rgba(161,17,17,.86);
  margin-bottom:2px;
}

.odetail-summary-value{
  font-size:12px;
  font-weight:700;
  color:#03606c;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* 5 CHIP SECTION – PERSIS DI BAWAH CHIP RINGKASAN */
.odetail-section-row{
  margin-top:6px;
  display:flex;
  flex-wrap:wrap;
  gap:6px;
}

.odetail-section-pill{
  font-size:10px;
  padding: 2px 6px;
  border-radius:999px;
  border:1px solid rgba(255,255,255,.65);
  background:rgba(255,255,255,.40);
  color:#03606c;
  font-weight:600;
  white-space:nowrap;
}

.odetail-section-pill.is-active{
  background:#ffffff;
  box-shadow:0 1px 1px rgba(148,210,218,.7);
}

/* KANAN: PETUGAS + TOMBOL */
.odetail-hero-side{
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:10px;
  min-width:230px;
}
/* PETUGAS – versi clean tanpa card background */
.odetail-meta-user{
  padding:0;
  margin-top:2px;
  border:none;
  background:transparent;   /* tidak ada background lagi */
  text-align:right;
}

/* tulisan PETUGAS kecil rapi seperti eyebrow */
.odetail-meta-user-label{
  font-size:10px;
  text-transform:uppercase;
  letter-spacing:.20em;
  color:#6b7280;
  margin-bottom:3px;
}

/* nama petugas lebih tebal & sedikit diberi underline tipis */
.odetail-meta-user-name{
  font-size:13px;
  font-weight:600;
  color:#014451;
  line-height:1.3;
  display:inline-block;
  padding-bottom:2px;
  margin-bottom:1px;
  border-bottom:1px solid rgba(255,255,255,.45);
}

/* Chat ID lebih kecil & soft */
.odetail-meta-user-id{
  font-size:11px;
  color:#0f3e47;
  opacity:.85;
}

/* TOOLBAR TOMBOL DI HERO – pojok kanan bawah & ukuran kecil */
.odetail-toolbar{
  position:absolute;          /* nempel ke .odetail-hero */
  right:18px;
  bottom:10px;                /* pojok kanan bawah banner */
  display:flex;
  flex-wrap:wrap;
  gap:6px;
  align-items:center;
}

.odetail-toolbar .btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:4px;
  padding:5px 14px;          /* lebih kecil */
  font-size:11px;            /* font lebih kecil */
  line-height:1.2;
  border-radius:12px;
  border-width:1px;
  font-weight:500;
   height:32px;
}

.odetail-toolbar .btn i{
  font-size:14px;            /* icon sedikit lebih kecil */
}

/* KEMBALI */
.odetail-btn-back{
  background:#ffffff;
  border-color:#d1d5db;
  color:#111827;
}

.odetail-btn-back:hover{
  background:#f3f4f6;
  border-color:#cbd5e1;
  color:#020617;
}

.btn-primar{
  background:#00a3ad;          /* teal efidi */
  border-color:#00a3ad;
  color:#ffffff;
}

.btn-primar:hover{
  background:#00808b;          /* teal lebih gelap saat hover */
  border-color:#00808b;
  color:#ffffff;
}

.btn-primar i{
  color:inherit;               /* icon ikut putih */
}
/* MINI CHIP DI BAWAH */
.odetail-hero-bottom{
  margin-top:8px;
  display:flex;
  justify-content:flex-start;
  align-items:center;
  gap:10px;
}

.odetail-hero-bottom-chips{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}

.odetail-mini-chip{
  display:inline-flex;
  align-items:baseline;
  gap:6px;
  padding:4px 8px;
  border-radius:999px;
  border:1px solid rgba(255, 255, 255, 1);
  background:rgba(209, 201, 201, 0.18);
  font-size:12px;
  color:#014451;
}

.odetail-mini-label{
  text-transform:uppercase;
  letter-spacing:.16em;
  font-size:8px;
  color:#6b7280;
}

.odetail-mini-value{
  font-weight:600;
  max-width:180px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* RESPONSIVE HERO */
@media (max-width: 992px){
  .odetail-hero{
    padding:14px 16px 12px;
  }
  .odetail-hero-inner{
    flex-direction:column;
    gap:10px;
  }
  .odetail-hero-side{
    align-items:flex-start;
    min-width:0;
  }
  .odetail-meta-user{
    text-align:left;
  }
}

@media (max-width: 768px){
  .odetail-hero-chips{
    flex-direction:column;
  }
  .odetail-summary-card{
    padding:7px 12px 8px;
  }
  .odetail-hero-bottom{
    flex-direction:column;
    align-items:flex-start;
  }
}

  /* BODY & KOLOM */
  .odetail-body{
    padding:18px 20px 20px;
  }
  .odetail-columns{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
  }
  .odetail-column{
    background:#ffffff;
    border-radius:13px;
    border:1px solid var(--efidi-border);
    padding:12px 18px 10px;
    position:relative;
  }
  .odetail-column::before{
    content:'';
    position:absolute;
    left:0; top:0; bottom:0;
    width:3px;
    border-radius:13px 0 0 13px;
    background:linear-gradient(to bottom,var(--efidi-teal-soft),transparent);
  }
  .odetail-column:nth-child(2)::before{
    background:linear-gradient(to bottom,var(--efidi-purple),transparent);
  }
  .odetail-column-title{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:11px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.14em;
    color:var(--efidi-label);
    margin-bottom:8px;
  }
  .odetail-column-icon{
    font-size:16px;
    color:var(--efidi-teal-soft);
  }
  .odetail-column:nth-child(2) .odetail-column-icon{
    color:var(--efidi-purple);
  }
  .odetail-column-title-soft{
    letter-spacing:.10em;
  }

  .odetail-item{
    padding:6px 0 7px;
    border-bottom:1px dashed var(--efidi-border);
    transition:background .18s ease;
  }
  .odetail-item:last-child{
    border-bottom:none;
    padding-bottom:0;
  }
  .odetail-item:hover{
    background:linear-gradient(to right,rgba(0,163,173,.03),transparent);
  }
  .odetail-label{
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.14em;
    color:var(--efidi-label);
    margin-bottom:2px;
  }
  .odetail-value{
    font-size:12px;
    color:var(--efidi-text);
  }
  .odetail-value .form-control,
  .odetail-value textarea{
    font-size:12px;
    padding:4px 8px;
    border-radius:6px;
  }

  /* SECTION TAMBAHAN */
  .odetail-extra{
    margin-top:18px;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:14px;
  }
  .odetail-extra-card{
    background:#ffffff;
    border-radius:13px;
    border:1px solid var(--efidi-border);
    padding:10px 16px 8px;
  }
  .odetail-extra-grid{
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:4px;
  }

  /* RESPONSIVE */
  @media (max-width: 992px){
    .page-header-main{ max-width:100%; }
    .page-header-efidi{
      flex-direction:column;
      align-items:flex-start;
    }
    .page-header-side{
      align-items:flex-start;
    }
  }
  @media (max-width: 768px){
    .odetail-hero{
      padding:14px 16px 14px;
    }
    .odetail-hero-top{
      flex-direction:column;
      align-items:flex-start;
      gap:10px;
    }
    .odetail-hero-right{
      align-items:flex-start;
    }
    .odetail-hero-bottom{
      flex-direction:column;
      align-items:flex-start;
    }
    .odetail-toolbar{
       position:static; 
      width:100%;
      justify-content:flex-start;
      margin-top:6px;
    }
    .odetail-columns{
      grid-template-columns:minmax(0,1fr);
    }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const btnBack    = document.getElementById('btnBack');
  const efidiToast = document.getElementById('efidiToast');
  const efidiToastClose = document.querySelector('.efidi-toast-close');

  // Tombol kembali
  if (btnBack){
    btnBack.addEventListener('click', function(e){
      e.preventDefault();
      if (window.history.length > 1){
        window.history.back();
      } else {
        window.location.href = 'data.php';
      }
    });
  }

  // Toast auto hide
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

  // Binding hero dengan input form (live update)
  const map = {
    order_type:   'heroOrderType',
    no_sc:          'heroNoScVal2',    // pakai ID yg benar
    no_internet:    'heroNoInetVal2',  // pakai ID yg benar
    nama_pelanggan: 'heroCustVal',
    sto:          'heroStoVal',
    mitra:        'heroMitraVal'
  };

  Object.keys(map).forEach(function(field){
    const input  = document.querySelector('[name="'+field+'"]');
    const target = document.getElementById(map[field]);

    // Untuk NO SC & NO INTERNET, juga update versi kedua (chip bawah)
    const target2 = (field === 'no_sc')
      ? document.getElementById('heroNoScVal2')
      : (field === 'no_internet'
          ? document.getElementById('heroNoInetVal2')
          : null);

    if (input && target){
      const handler = function(){
        const v = input.value.trim() || '-';
        target.textContent = v;
        if (target2){ target2.textContent = v; }
      };
      input.addEventListener('input', handler);
    }
  });
});
</script>

<?php
include "footer.php";
