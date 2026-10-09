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

/**
 * Semua kolom yang boleh di-edit
 * (kecuali id_order yang jadi primary key)
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
 * Jika POST -> update data
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idOrder > 0) {
  $sets   = [];
  $params = [':id' => $idOrder];

  foreach ($editableCols as $col) {
    if (array_key_exists($col, $_POST)) {
      $val = trim((string)$_POST[$col]);
      if ($val === '') $val = '-'; // tetap konsisten dengan default di DB
      $sets[] = "`$col` = :$col";
      $params[":$col"] = $val;
    }
  }

  if ($sets) {
    $sql = "UPDATE orders SET ".implode(', ', $sets)." WHERE id_order = :id";
    try {
      $st = $pdo->prepare($sql);
      $st->execute($params);
      $successMsg = 'Data order berhasil diperbarui.';
    } catch (Throwable $ex) {
      $errorMsg = 'Gagal menyimpan perubahan: '.$ex->getMessage();
    }
  }
}

/**
 * Ambil data 1 order
 */
$order = null;
if ($idOrder > 0) {
  $st = $pdo->prepare("SELECT * FROM orders WHERE id_order = :id");
  $st->execute([':id' => $idOrder]);
  $order = $st->fetch(PDO::FETCH_ASSOC);
}

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
 * Helper untuk render 1 field (label + value/input)
 */
function renderDetailField(string $name, string $label, array $row): void {
  $val = isset($row[$name]) ? (string)$row[$name] : '';
  ?>
  <div class="odetail-item">
    <div class="odetail-label"><?= e($label) ?></div>
    <div class="odetail-value">
      <span class="view-mode odetail-static">
        <?= $val !== '' ? e($val) : '<span class="text-muted">-</span>' ?>
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

// mapping field ke label per kolom
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
    $jam = date('H.i', $ts); // 17.48

    $namaBulan = $bulanIndo[$m] ?? date('M', $ts);
    // Contoh: "01 November 2025, 17.48 WIB"
    $createdAtFmt = "$d $namaBulan $y, $jam WIB";
  }
}

?>
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
  <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-2 mt-n30"> <!-- mt-n1 rapetin lagi -->
  <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>

  <a href="index.php" class="text-muted mx-1 crumb-mid">
  &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
</a>

    <span class="text-primary crumb-mid">Data Orders</span>
  </div>
</div>

</div>
    <div class="row">
      <div class="col-12 grid-margin stretch-card">
        <div class="card odetail-card" id="orderDetailCard">
          <div class="card-body p-0">
            <form method="post" id="orderEditForm">
              <input type="hidden" name="id_order" value="<?= (int)$idOrder ?>">
         <!-- HEADER ORDER – VERSI BARU EFIDI -->
            <!-- HEADER ORDER – EFIDI (final, chip di tengah dan sejajar PSB) -->
      <div class="odetail-hero">
        <div class="odetail-hero-top">
          <!-- KIRI: jenis order + detail -->
                    <!-- KIRI: jenis order + detail -->
          <div class="odetail-hero-main">
            <div class="odetail-eyebrow">Ringkasan Order</div>

            <h3 class="odetail-title">
              <?= e($order['order_type'] ?: 'Order') ?>
            </h3>

            <div class="odetail-hero-line">
              <span class="odetail-hero-line-label">Detail Order</span>
              <span class="odetail-separator">·</span>
              <span>STO <?= $order['sto'] !== '' ? e($order['sto']) : '-' ?></span>
              <span class="odetail-separator">·</span>
              <span><?= $order['mitra'] !== '' ? e($order['mitra']) : '-' ?></span>
            </div>

            <!-- CREATED di bawah Detail Order + ikon kalender -->
           <div class="odetail-created">
  <div class="odetail-created-iconwrap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

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

          <!-- TENGAH: deretan chip NO SC, NO INTERNET, CUSTOMER (sejajar PSB & di tengah banner) -->
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
                  <?= $order['no_internet'] !== '' ? e($order['no_internet']) : '-' ?>
                </div>
              </div>

              <div class="odetail-stat odetail-chip">
                <div class="odetail-stat-label">CUSTOMER</div>
                <div class="odetail-stat-value">
                  <?= $order['nama_pelanggan'] !== '' ? e($order['nama_pelanggan']) : '-' ?>
                </div>
              </div>
            </div>
          </div>

          <!-- KANAN: created + tombol -->
          <div class="odetail-hero-side">
           

            <div class="odetail-toolbar odetail-toolbar-inline">
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



        <!-- BAWAH: ringkasan utama -->


                           <!-- ALERT SUKSES / ERROR -> EFIDI TOAST -->
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


              <!-- BODY DETAIL -->
              <div class="odetail-body">

                <!-- DUA KOLOM UTAMA -->
                <div class="odetail-columns">
                  <!-- KOLOM KIRI -->
                  <div class="odetail-column">
                    <div class="odetail-column-title">
                      <i class="mdi mdi-account-details odetail-column-icon"></i>
                      <span>Data Pelanggan & Lokasi</span>
                    </div>
                    <?php foreach ($leftFields as $name => $label): ?>
                      <?php renderDetailField($name, $label, $order); ?>
                    <?php endforeach; ?>
                  </div>

                  <!-- KOLOM KANAN -->
                  <div class="odetail-column">
                    <div class="odetail-column-title">
                      <i class="mdi mdi-access-point-network odetail-column-icon"></i>
                      <span>Data Teknis & Valins</span>
                    </div>
                    <?php foreach ($rightFields as $name => $label): ?>
                      <?php renderDetailField($name, $label, $order); ?>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- SECTION TAMBAHAN -->
                <div class="odetail-extra">
                  <div class="odetail-extra-card">
                    <div class="odetail-column-title odetail-column-title-soft">
                      <i class="mdi mdi-history odetail-column-icon"></i>
                      <span>Data Lama (Migrasi)</span>
                    </div>
                    <div class="odetail-extra-grid">
                      <?php foreach ($legacyFields as $name => $label): ?>
                        <?php renderDetailField($name, $label, $order); ?>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="odetail-extra-card">
                    <div class="odetail-column-title odetail-column-title-soft">
                      <i class="mdi mdi-shield-account-outline odetail-column-icon"></i>
                      <span>Info Internal</span>
                    </div>
                    <div class="odetail-extra-grid">
                      <?php foreach ($metaFields as $name => $label): ?>
                        <?php renderDetailField($name, $label, $order); ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div><!-- /.odetail-extra -->
              </div><!-- /.odetail-body -->
            </form>
          </div>
        </div>
      </div>
    </div><!-- /.row detail -->
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
    border-radius:17px;
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

  /* =============== KARTU DETAIL =============== */
  .odetail-card{
    border-radius:17px;
    border:1px solid var(--efidi-border);
   
    overflow:hidden;
  }
  .odetail-card .card-body{
    padding:0;
    background:radial-gradient(circle at top left,#ffffff 0,var(--efidi-bg-soft) 65%);
  }

  /* =================== BANNER ORDER – VERSI FORMAL EFIDI =================== */
/* ================== EFIDI TOAST NOTIFICATION (SIMPLE SNACKBAR) ================== */

.efidi-toast-container{
  position:fixed;
  bottom:18px;          /* pojok kanan bawah, bukan atas lagi */
  right:20px;
  z-index:9999;
  pointer-events:none;  /* supaya area kosong tidak menghalangi klik */
}

.efidi-toast{
  display:flex;
  align-items:center;
  gap:8px;
  max-width:280px;
  background:#ffffff;
  border-radius:999px;  /* pill */
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

/* tombol close kecil */
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

/* animasi muncul & hilang dari kanan bawah */
@keyframes efidiToastIn{
  0%{
    opacity:0;
    transform:translate(10px,10px) scale(.96);
  }
  100%{
    opacity:1;
    transform:translate(0,0) scale(1);
  }
}

@keyframes efidiToastOut{
  0%{
    opacity:1;
    transform:translate(0,0) scale(1);
  }
  100%{
    opacity:0;
    transform:translate(6px,6px) scale(.96);
  }
}

.efidi-toast.is-hiding{
  animation:efidiToastOut .22s ease-in forwards;
}

/* ============= EFIDI ORDER HEADER – VERSI BARU ============= */
/* ============= EFIDI ORDER HEADER – RAPIH, TANPA SHADOW ============= */
/* ============= EFIDI ORDER HEADER – FINAL RAPIH ============= */

.odetail-hero{
  position:relative;
  padding:17px 24px 17px;      /* banner lebih pendek */
  border-radius:17px 17px 0 0;
  overflow:hidden;
  background:
    radial-gradient(circle at -10% -20%, rgba(255,255,255,.95) 0, rgba(255,255,255,0) 55%),
    radial-gradient(circle at 110% -10%, rgba(255,255,255,.85) 0, rgba(255,255,255,0) 60%),
    linear-gradient(90deg,#e5f7f9 0%,#cceff4 35%,#8fd8df 100%);
}

/* TOP: kiri = title, tengah = NO SC, kanan = tombol */
/* TOP: kiri = title, tengah = 3 chip, kanan = tombol */
.odetail-hero-top{
  display:grid;
  /* kiri & tengah dibagi lebih seimbang supaya chip tidak terlalu ke kanan */
  grid-template-columns:minmax(0,1.05fr) minmax(0,1.05fr) auto;
  align-items:center;
  column-gap:16px;
}


/* kiri */
.odetail-hero-main{
  min-width:0;
}

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
   margin-bottom:2px;
}
/* CREATED di bawah Detail Order */
/* CREATED di bawah Detail Order – versi baru */
.odetail-created{
  margin-top:6px;
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
  background:rgba(1, 109, 121, .10);  /* lingkaran soft teal */
  color:#014451;
}

.odetail-created-icon{
  font-size:14px;
}

.odetail-created-text{
  display:flex;
  flex-direction:column;
}

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

/* kalau masih ada .odetail-created-sep di CSS, boleh dibiarkan atau dihapus, sudah tidak dipakai */


.odetail-hero-line-label{
  font-weight:500;
}

.odetail-separator{
  padding:0 4px;
  opacity:.6;
}

/* tengah – kolom chip */
.odetail-hero-center{
   justify-self:start;    /* bukan center lagi, mulai dari kiri kolom */
  margin-left:-250px;  
}

/* deretan chip di tengah banner */
.odetail-hero-chips{
  display:flex;
  align-items:stretch;
  justify-content:center;
  gap:10px;
  flex-wrap:nowrap;
}

/* BASE KARTU */
.odetail-stat{
  min-width:210px;
  padding:7px 14px 8px;
  border-radius:8px; /* terasa chip / pill */
  border:1px solid rgba(255,255,255,.65);
}

/* CHIP KHUSUS HEADER (NO SC, NO INTERNET, CUSTOMER) – GRADIENT MIRIP ODP/PORT */
.odetail-chip{
  background: rgba(241, 241, 241, 0.16);
}

.odetail-chip .odetail-stat-label{
  color:rgba(161, 17, 17, 0.86);
}

.odetail-chip .odetail-stat-value{
  color:#03606c;
}

/* teks di dalam chip */
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
}

.odetail-stat-value-compact{
  font-size:13px;
}

/* (VARIAN LAMA .odetail-stat-header TIDAK DIPAKAI LAGI, BOLEH DIHAPUS ATAU DIBIARKAN) */

/* bawah – struktur lama NO INTERNET & CUSTOMER sudah tidak dipakai lagi */
.odetail-hero-bottom{ display:none; }
.odetail-hero-stats{ display:none; }

@media (max-width: 992px){
  .odetail-hero-chips{
    justify-content:flex-start;
    flex-wrap:wrap;
  }
}

@media (max-width: 768px){
  .odetail-hero-chips{
    flex-direction:column;
    align-items:stretch;
  }
  .odetail-stat{
    min-width:0;
  }
}


/* RESPONSIVE */
@media (max-width: 992px){
  .odetail-hero{
    padding:10px 16px 10px;
  }
  .odetail-hero-top{
    grid-template-columns:minmax(0,1fr);
    row-gap:8px;
  }
  .odetail-hero-center{
    justify-self:flex-start;
  }
  .odetail-hero-side{
    align-items:flex-start;
  }
  .odetail-meta{
    text-align:left;
  }
}

@media (max-width: 768px){
  .odetail-hero{
    border-radius:8px 8px 0 0;
  }
  .odetail-hero-stats{
    flex-direction:column;
  }
  .odetail-stat{
    min-width:0;
  }
}



/* =============== TOOLBAR TOMBOL =============== */
.odetail-toolbar{
  position:relative;
  z-index:1;
  margin-left:auto;
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  align-items:center;        /* rata tengah vertikal */
}

/* BASE: semua tombol di toolbar */
.odetail-toolbar .btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:12px;
  font-weight:500;
  line-height:1;

  /* tinggi visual dikontrol oleh height, bukan padding */
  padding:0 18px !important;     /* kiri-kanan saja */
  border-radius:8px !important;
}

/* --- TINGGI DIKUNCI PER BUTTON (BIAR SERAGAM) --- */
/* Tinggi khusus per button – awalnya sama semua (32px) */
.odetail-btn-back{
  height:32px !important;
}

.odetail-btn-edit{
  height:30px !important;
}

.odetail-btn-edit-actions .btn-primar{
  height:30px !important;
}

.odetail-btn-edit-actions .btn-outline-light{
  height:32px !important;
}

/* ICON */
.odetail-toolbar .btn i{
  font-size:16px;
  margin-right:6px;
  vertical-align:-1px;
}

/* KEMBALI (ghost putih) */
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

/* EDIT (teal) */
.odetail-btn-edit{
  background:#20a1ad;
  border-color:#20a1ad;
  color:#ffffff !important;
}
.odetail-btn-edit:hover{
  background:#17818b;
  border-color:#17818b;
  color:#ffffff !important;
}

/* WRAPPER SIMPAN / BATAL */
.odetail-btn-edit-actions{
  display:none;
  gap:6px;
}

/* SIMPAN (teal) */
.odetail-btn-edit-actions .btn-primar{
  background:#20a1ad;
  border-color:#20a1ad;
  color:#ffffff !important;
}
.odetail-btn-edit-actions .btn-primar:hover{
  background:#17818b;
  border-color:#17818b;
  color:#ffffff !important;
}

/* BATAL (ghost putih) */
.odetail-btn-edit-actions .btn-outline-light{
  background:#f9fafb;
  border-color:#d1d5db;
  color:#111827;
}
.odetail-btn-edit-actions .btn-outline-light:hover{
  background:#e5e7eb;
}


  /* =============== BODY =============== */
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
    padding:4px 8px;
    border-radius:6px;
  }

  /* =============== SECTION TAMBAHAN =============== */
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

  /* alert sukses/gagal */
  .odetail-alert{
    border-radius:0;
    border-left:none;
    border-right:none;
    margin:0;
    font-size:13px;
  }

  /* =============== MODE EDIT =============== */
  .odetail-card .edit-mode{ display:none; }
  .odetail-card.is-editing .view-mode{ display:none; }
  .odetail-card.is-editing .edit-mode{ display:block; }
  .odetail-card.is-editing .odetail-btn-edit{ display:none; }
  .odetail-card.is-editing .odetail-btn-edit-actions{ display:flex; }

  /* =============== RESPONSIVE =============== */
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
      flex-direction:column;
      align-items:flex-start;
      padding:14px 16px 14px;
    }
    .odetail-toolbar{
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
  const card   = document.getElementById('orderDetailCard');
  const form   = document.getElementById('orderEditForm');
  const btnEdit   = document.getElementById('btnEdit');
  const btnCancel = document.getElementById('btnCancel');
  const btnBack   = document.getElementById('btnBack');

  if (btnEdit && card){
    btnEdit.addEventListener('click', function(e){
      e.preventDefault();
      card.classList.add('is-editing');
    });
  }

  if (btnCancel && card && form){
    btnCancel.addEventListener('click', function(e){
      e.preventDefault();
      card.classList.remove('is-editing');
      form.reset(); // kembalikan ke nilai terakhir dari DB
    });
  }

 if (btnBack){
  btnBack.addEventListener('click', function(e){
    e.preventDefault();

    // coba pakai halaman sebelumnya kalau memang dari data.php
    const ref = document.referrer || '';

    if (ref && ref.indexOf('data.php') !== -1) {
      // kembali ke list dengan query yang sama (?mode=..., ?page=..., dll)
      window.location.href = ref;
    } else {
      // fallback kalau langsung buka view_data.php dari URL / bookmark
      window.location.href = 'data.php';
    }
  });
}

  /* ====== TOAST EFIDI: AUTO HIDE & CLOSE ====== */
  const efidiToast = document.getElementById('efidiToast');
  const efidiToastClose = document.querySelector('.efidi-toast-close');

  if (efidiToast){
    // auto-hide setelah ~4,5 detik
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
});
</script>

<?php
include "footer.php";