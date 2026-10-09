   <?php
   include "header.php";
   ?>

   <style>
    #example .badge-outline-danger{ background:#fff; }
.btn-xs{ font-size:12px; line-height:1; padding:2px 6px; }

/* ==== SIZE & BASE STYLE (menang atas .btn-sm) ==== */
/* ==== SIZE & BASE ==== */
.btn.btn-sm.btn-commit,
.btn.btn-commit{
  height: 34px !important;
  min-width: 30px !important;
  padding: 8px 8px !important;
  line-height: 1 !important;

  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  border-radius: 2px;
  border-width: 1px !important;

  /* default (idle) */
  background: #E5E7EB  !important;   /* slate-200 */
  border-color: #c8cad0ff  !important;  /* slate-300 */
  color: #9CA3AF  !important;         /* slate-700 */
}

/* ikon */
.btn-commit i{ font-size:18px; line-height:1 !important; }


/* ==== ACTIVE (ada perubahan) – tingkatkan spesifisitas ==== */
.btn.btn-sm.btn-commit.teal-enabled,
.btn.btn-commit.teal-enabled{
  background: #00A3AD !important;
  border-color: #00A3AD !important;
  color: #fff !important;
}

/* ==== DISABLED (abu kehitaman supaya jelas non-aktif) ==== */
.btn.btn-sm.btn-commit:disabled,
.btn.btn-commit:disabled{
  background: #E5E7EB  !important;   /* slate-600 */
  border-color: #c8cad0ff  !important; /* slate-700 */
  color: #9CA3AF  !important;        /* zinc-400 */
  box-shadow: none !important;
  opacity: 1 !important;
  cursor: not-allowed;
}

/* ======= LOOK & BORDER UNTUK DROPDOWN ======= */
select.form-control.sel-role,
select.form-control.sel-status{
  height: 40px;                    /* biar rapi */
  border: .1px solid #cbcdd3ff !important;   /* garis tepi */
  border-radius: 1px;
  background: #fff;
  padding: 0 .75rem;
  line-height: 40px;
  box-shadow: none !important;
}

/* fokus = highlight teal */
select.form-control.sel-role:focus,
select.form-control.sel-status:focus{
  border-color: #00A3AD !important;
  box-shadow: 0 0 0 3px rgba(0,163,173,.15) !important;
  outline: none;
}

/* disabled = tampilan abu-abu */
select.form-control.sel-role:disabled,
select.form-control.sel-status:disabled{
  background: #F1F5F9 !important;    /* abu muda */
  border-color: #E2E8F0 !important;  /* garis lembut */
  color: #64748B !important;         /* teks pudar */
  cursor: not-allowed;
}

/* Saat masih "-- Pilih --" => warnai select-nya */
/* === style saat masih "-- Pilih --" === */
select.form-control.sel-role.is-placeholder:not(:disabled),
select.form-control.sel-status.is-placeholder:not(:disabled){
  background: #ffffffff !important;   /* biru lembut */
  border-color: #00A3AD !important;  /* teal seperti contoh */
  color: #2d3030ff !important;         /* teks kontras */
  font-weight: 600;
}

/* fokus saat placeholder */
select.form-control.sel-role.is-placeholder:focus,
select.form-control.sel-status.is-placeholder:focus{
  box-shadow: 0 0 0 3px rgba(0,163,173,.25) !important; /* glow teal */
  outline: none;
}



   </style>
<style>
/* ====== Select: border jelas + caret panah ====== */
.sel-role,
.sel-status{
  -webkit-appearance:none;
  -moz-appearance:none;
  appearance:none;
  background-color:#fff;

  /* garis tepi */
  border:1px solid #d1d5db;            /* slate-300 */
  border-radius:6px;
  padding-right:30px !important;       /* space untuk ikon panah */
  box-shadow:none;
}

.sel-role:focus,
.sel-status:focus{
  border-color:#00A3AD;
  box-shadow:0 0 0 3px rgba(0,163,173,.14);
  outline:none;
}

/* caret panah (SVG data URI yang valid & 1 baris) */
.sel-role,
.sel-status{
  -webkit-appearance:none;
  -moz-appearance:none;
  appearance:none;

  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='%236b7280' fill-rule='evenodd' d='M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z' clip-rule='evenodd'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right .6rem center;
  background-size: 14px 14px;
} 


.sel-role option[disabled].current,
.sel-status option[disabled].current{
  color:#94a3b8;   
}


.sel-role option:disabled,
.sel-status option:disabled{ color:#9ca3af; }
</style>

<style>
/* ===== Verifikasi: kapsul kode, tombol publish, & ikon ===== */
.btn-publish{
  background:#00A3AD!important;border-color:#00A3AD!important;color:#fff!important;
  border-radius:2px!important;padding:8px 5px!important;
  box-shadow:0 2px 10px rgba(0,163,173,.14);
}
.btn-publish:disabled{background:#e5e7eb!important;border-color:#cbd5e1!important;color:#94a3b8!important;box-shadow:none!important;cursor:not-allowed;}
.btn-publish.ready{animation:pubPulse 1.6s ease-in-out infinite;}
@keyframes pubPulse{0%,100%{box-shadow:0 0 0 0 rgba(0,163,173,.20);}50%{box-shadow:0 0 0 6px rgba(0,163,173,.08);}}

/* Kapsul kode */
.code-pill{
  position:relative; display:inline-block; min-width:180px; 
  padding:6px 12px 8px; border-radius:2px;
  background:linear-gradient(90deg,#ecfeff 0%,#f0fdfa 100%);
  border:1px solid #67e8f9; box-shadow:0 2px 10px rgba(14,116,144,.08);
}
.code-pill.live { background:linear-gradient(90deg,#e0fbff 0%,#eafff9 100%); }
.code-pill.expired{ background:#f5f6f8; border-color:#e5e7eb; box-shadow:none; }

.code-main{ display:flex; align-items:center; gap:.5rem; }
.code-val{ font-size:18px; font-weight:800; letter-spacing:.5px; color:#075985; }
.code-pill.expired .code-val{ color:#6b7280; }

.code-meta{ margin-top:1px; font-size:10px; font-weight:400; color:#b91c1c; } /* merah */
.code-pill.expired .code-meta{ color:#b91c1c; } /* tetap merah bertuliskan Expired */

.btn-icon{
  display:inline-flex; align-items:center; justify-content:center;
  width:20px; height:19px; border:1px solid #d1d5db; border-radius:2px; background:#fff;
  color:#334155;
}
.btn-icon:hover{filter:brightness(.96);}
.btn-icon i{font-size:12px;}

/* tombol refresh di pojok kanan atas kapsul */
.btn-refresh-code{
  position:absolute; top:-10px; right:-10px; 
  width:25px; height:25px; border-radius:50%;
  background:#fff; border:2px solid #7dd3fc; color:#0284c7;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 4px 12px rgba(2,132,199,.18);
}
.btn-refresh-code i{ font-size:15px; }

/* transisi saat replace button -> kode  */
.fade-replace{animation:fadePop .35s ease-out both;}
@keyframes fadePop{from{opacity:0;transform:translateY(4px) scale(.98);}to{opacity:1;transform:translateY(0) scale(1);}}

/* rapikan sel verifikasi */
td.td-verif{vertical-align:middle;}

</style>

<style>
  /* OTP kolom = tidak wrap, lebar pas */
#example th:nth-child(8),
#example td:nth-child(8){ white-space:nowrap; width:120px; }

/* Action kolom: tombol gak melebar */
#example th:nth-child(9),
#example td:nth-child(9){ white-space:nowrap; width:80px; }

/* Selalu tengah secara vertikal biar sebaris */
#example td, #example th{ vertical-align: middle; }

</style>

   

   <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="d-flex justify-content-between align-items-end flex-wrap">
      <!-- kiri -->
      <div class="mr-md-3 mr-xl-5">
        <h2 class="mb-1">Akses Login</h2>
        <p class="mb-0">Dapat digunakan untuk masuk ke sistem menggunakan akun yang telah terdaftar.</p>
        <p>Hak akses pengguna akan menentukan fitur yang dapat digunakan setelah login berhasil.</p>
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
  <!-- breadcrumb di bawah logo (tanpa <br>) -->
  <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30"> <!-- mt-n1 rapetin lagi -->
  <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>

  <a href="index.php" class="text-muted mx-1 crumb-mid">
  &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
</a>

    <span class="text-primary crumb-mid">Akses Login</span>
  </div>
</div>




              </div>
            </div>
          </div>
          <?php
// Totals global (berdasarkan DISTINCT id_*), tidak ikut filter
require_once __DIR__ . '/koneksi.php';

function db_table_exists(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}
function db_col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}
function try_count_distinct(PDO $pdo, array $cands): int {
  foreach ($cands as $c) {
    [$table,$idcol,$where] = $c + [null,null,''];
    if ($table && $idcol && db_table_exists($pdo,$table) && db_col_exists($pdo,$table,$idcol)) {
      $sql = "SELECT COUNT(DISTINCT `$idcol`) FROM `$table`" . ($where ? " WHERE $where" : "");
      $st  = $pdo->query($sql);
      if ($st) return (int)$st->fetchColumn();
    }
  }
  return 0;
}

// ADMIN: coba beberapa kemungkinan skema
$TOTAL_ADMIN = try_count_distinct($pdo, [
  ['admin','id_admin',''],
  ['admins','id_admin',''],
  ['users','id',"LOWER(role)='admin'"],
  ['akses_login','id',"LOWER(role)='admin'"],
]);

// TA (Telkom Akses)
$TOTAL_TA = try_count_distinct($pdo, [
  ['ta','id_ta',''],
  ['telkom_akses','id_ta',''],
  ['users','id',"LOWER(role) IN ('ta','telkom akses')"],
  ['akses_login','id',"LOWER(role) IN ('ta','telkom akses')"],
]);

// TECH (teknisi)
$TOTAL_TECH = try_count_distinct($pdo, [
  ['tech','id_tech',''],
  ['teknisi','id_tech',''],
  ['users','id',"LOWER(role) IN ('tech','teknisi')"],
  ['akses_login','id',"LOWER(role) IN ('tech','teknisi')"],
]);
?>
         <style>
          /* ===== Pisahkan jadi 3 kartu yang tegas ===== */

/* Grid dengan jarak jelas antar-kartu */
.users-grid{
  display:grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;                          /* jarak antar kartu */
}
@media (max-width: 992px){ .users-grid{ grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px){ .users-grid{ grid-template-columns: 1fr; } }

/* ——— Users / Role stat cards — gaya seperti gambar ——— */
.u-row .u-col{ margin-bottom: 16px; }

.u-card{
  --accent:#0ea5a4;                                 /* override per kartu via inline style */
  position: relative;
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px;
  border-radius: 4px;
  /* fallback dulu */
  background: #f8fafc !important;
  border: 1px solid #e2e8f0 !important;
  /* lembut mengikuti warna aksen (browser modern) */
  background: color-mix(in srgb, var(--accent) 6%, #ffffff) !important;
  border: 1px solid color-mix(in srgb, var(--accent) 20%, #e2e8f0) !important;
  box-shadow: 0 2px 8px rgba(2, 6, 23, .05);
}

/* bar warna di sisi kiri */
.u-card::before{
  content:"";
  position:absolute; left:0; top:0; bottom:0; width:4px;
  border-radius:16px 0 0 16px;
  background: var(--accent);
  display:block !important;                           /* paksa aktif bila ada override lama */
}

.u-icon{
  width:56px; height:56px; flex:0 0 56px;
  display:grid; place-items:center;
  background:#fff;
  border-radius:12px;
  /* fallback border tipis */
  border:1px solid #e5e7eb;
  /* border mengikuti accent (modern) */
  border: 1px solid color-mix(in srgb, var(--accent) 35%, #e5e7eb);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 8%, #ffffff);
}
.u-icon img{ width:40px; height:40px; object-fit:contain; }

.u-meta small{ display:block; font-size:.85rem; color:#64748b; margin-bottom:2px; }
.u-count{ font-weight:800; font-size:1.45rem; color:#0f172a; letter-spacing:.2px; }

.u-chip{
  position:absolute; right:10px; top:10px;
  font-size:.72rem; padding:.15rem .55rem;
  border-radius:999px; background:#fff; color:#475569;
  border:1px solid #e2e8f0;
}

         </style>
         <div class="row u-row">
  <!-- ADMIN -->
  <div class="col-xl-4 col-md-6 u-col">
    <div class="u-card" style="--accent:#3b82f6">
      <span class="u-chip">Admin</span>
      <div class="u-icon">
        <img src="images/user/admin.png" alt="Admin">
      </div>
      <div class="u-meta">
        <small>Admin</small>
        <div class="u-count"><?= number_format($TOTAL_ADMIN, 0, ',', '.') ?></div>
      </div>
    </div>
  </div>

  <!-- TA -->
  <div class="col-xl-4 col-md-6 u-col">
    <div class="u-card" style="--accent:#00A3AD">
      <span class="u-chip">TA</span>
      <div class="u-icon">
        <img src="images/user/ta.png" alt="TA">
      </div>
      <div class="u-meta">
        <small>TA</small>
        <div class="u-count"><?= number_format($TOTAL_TA, 0, ',', '.') ?></div>
      </div>
    </div>
  </div>

  <!-- TECH -->
  <div class="col-xl-4 col-md-6 u-col">
    <div class="u-card" style="--accent:#f5320b">
      <span class="u-chip">Tech</span>
      <div class="u-icon">
        <img src="images/user/tech.png" alt="Tech">
      </div>
      <div class="u-meta">
        <small>Tech</small>
        <div class="u-count"><?= number_format($TOTAL_TECH, 0, ',', '.') ?></div>
      </div>
    </div>
  </div>
</div>

         
          <div class="row">
           
            <div class="col-md-12 stretch-card">
              <div class="card">
                <div class="card-body">
               <div class="d-flex align-items-center justify-content-between mb-3">

                  <p class="card-title">Akses Login</p>
                 

<!-- ====== Toolbar atas tabel ====== -->
   <button
          type="button"
          class="btn"
          style="background: #182958;color:#fff"
          
          data-toggle="modal"   data-target="#addAksesModal">Tambah
        </button>
 
</div>

<!-- ====== Modal: Tambah Akses Login ====== -->
<div class="modal fade" id="addAksesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form id="formAddAkses" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Akses Login</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div id="addAksesErr" class="alert alert-danger d-none mb-3"></div>

          <?php if (!empty($csrf)): ?>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
          <?php endif; ?>

          <div class="form-group mb-2">
            <label>Nama <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" placeholder="Nama lengkap" required>
          </div>

          <div class="form-group mb-2">
            <label>Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required placeholder="username unik">
          </div>

          <div class="form-row">
            <div class="form-group col-md-6 mb-2">
              <label>Password <span class="text-danger">*</span></label>
              <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="form-group col-md-6 mb-2">
              <label>Confirm Password <span class="text-danger">*</span></label>
              <input type="password" name="password2" class="form-control" required minlength="6">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6 mb-2">
              <label>Role <span class="text-danger">*</span></label>
              <select name="role" class="form-control sel-role" required>
                <option value="">--- Pilih ---</option>
                <option value="admin">Admin</option>
                <option value="ta">TA</option>
                <option value="tech">Tech</option>
                <option value="monika">Monika</option>
              </select>
            </div>
           <div class="form-group col-md-6 mb-2">
  <label>ID Telegram (chat_id)<span class="text-danger">*</span></label>
  <input
    type="text"
    name="chat_id"
    class="form-control"
    placeholder="Otomatis 9 digit"
    readonly
    required
    minlength="9"
    maxlength="9"
    inputmode="numeric"
    pattern="[0-9]{9}"
  >
  <small class="form-text text-muted" style="font-size:11px;">
    Diisi otomatis oleh sistem (9 digit angka acak).
  </small>
</div>

          </div>

          <small class="text-muted">Catatan: saat disimpan, <b>status</b> otomatis <code>active</code>, <b>verifikasi</b> diisi 7 digit acak, <b>done</b> diisi <code>yes</code>.</small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
          <button id="btnAddAkses" type="submit" class="btn" style="background: #182958; color: #fff;">
             Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


                 <?php
// --- AMBIL DATA DARI DB ---
require_once __DIR__.'/koneksi.php';
$rows = [];
try {
  $q = $pdo->query("SELECT id_akses, nama, username, role, status, chat_id, verifikasi, done
                      FROM akses_login
                  ORDER BY id_akses DESC");
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $rows = [];
}
$csrf = function_exists('csrf_token') ? csrf_token() : '';
?>
<div class="table-responsive">
  <table id="example" class="display" style="width:100%">
    <thead>
      <tr>
        <th style="width:52px">No</th>
        <th>Nama</th>
        <th>Role</th>
        <th>Status</th>
        <th style="width:70px">Aksi</th>
        <th>ID Telegram</th>
        <th>Verify</th>
        <th style="width:160px">Action</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $no = 1;
      foreach ($rows as $r):
        $id   = (int)$r['id_akses'];
        $nm   = (string)$r['nama'];
        $un   = (string)$r['username'];
        $role = strtolower((string)$r['role']);
        $sts  = strtolower((string)$r['status']);
        $cht  = (string)($r['chat_id'] ?? '');
        $done = strtolower((string)$r['done']);
      

    ?>
      <tr data-id="<?= $id ?>" data-initial-role="<?= htmlspecialchars($role) ?>" data-initial-status="<?= htmlspecialchars($sts) ?>">
        <td class="text-muted"><?= $no++ ?></td>

        <td>
          <div class="d-flex align-items-center gap-2">
            <div style="width:28px;height:28px;border-radius:50%;background:#e2f3f3;display:inline-flex;align-items:center;justify-content:center;font-weight:700;color:#0f766e;">
              <?= strtoupper(substr($nm !== '' ? $nm : $un,0,1)) ?>
            </div>
            <div>
              <div class="font-weight-bold"><?= htmlspecialchars($nm !== '' ? $nm : '—') ?></div>
              <div class="text-muted" style="font-size:12px">@<?= htmlspecialchars($un) ?></div>
            </div>
          </div>
        </td>


        <td>
          <select class="form-control form-control-sm sel-role">
            <option value="" <?= $role===''?'selected':'' ?>>--- Pilih ---</option>
            <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
            <option value="ta"    <?= $role==='ta'   ?'selected':'' ?>>TA</option>
            <option value="tech"  <?= $role==='tech' ?'selected':'' ?>>Tech</option>
            <option value="monika" <?= $role==='monika'?'selected':'' ?>>Monika</option>
          </select>
        </td>

        <td>
          <select class="form-control form-control-sm sel-status">
            <option value="" <?= $sts===''?'selected':'' ?>>--- Pilih ---</option>
            <option value="active"    <?= $sts==='active'   ?'selected':'' ?>>Active</option>
            <option value="pending"   <?= $sts==='pending'  ?'selected':'' ?>>Pending</option>
            <option value="suspended" <?= $sts==='suspended'?'selected':'' ?>>Suspended</option>
          </select>
        </td>

     <td class="text-center">
  <button class="btn btn-sm btn-commit" title="Simpan role & status" disabled>
    <i class="mdi mdi-autorenew"></i>
  </button>
</td>
<td class="text-monospace">
  <?php if ($cht !== ''): ?>
   <div class="d-flex align-items-center" style="gap:4px;">
  <span><?= htmlspecialchars($cht) ?></span>
  <button type="button"
          class="btn btn-copy-chat"
          data-chat="<?= htmlspecialchars($cht) ?>"
          title="Salin chat_id"
          style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;min-width:16px;min-height:16px;padding:0;border-radius:2px;border:1px solid #e5e7eb;background:#fff;color:#6b7280;cursor:pointer;">
    <i class="mdi mdi-content-copy" style="font-size:10px;line-height:1;"></i>
  </button>
</div>

  <?php else: ?>
    <span class="text-muted">—</span>
  <?php endif; ?>
</td>

        <td>
          <?php if($done==='yes'): ?>
            <span class="badge badge-outline-success" style="border:1px solid #bbe1bcff;color:#1b5e20">Done</span>
          <?php else: ?>
            <span class="badge badge-outline-danger" style="border:1px solid #fecaca;color:#7f1d1d">Not</span>
          <?php endif; ?>
        </td>
     


<td class="text-center text-nowrap">
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
        <span class="action-subtitle">Akses Login</span>
      </div>

      <!-- EDIT -->
      <button type="button"
              class="dropdown-item action-item akses-edit-trigger"
              data-id="<?= (int)$r['id_akses'] ?>"
              data-nama="<?= htmlspecialchars($nm !== '' ? $nm : $un) ?>"
              data-username="<?= htmlspecialchars($un) ?>">
        <span class="action-icon action-icon-edit">
          <i class="mdi mdi-pencil-outline"></i>
        </span>
        <span class="action-text">Edit</span>
      </button>

      <div class="dropdown-divider my-1"></div>

      <!-- HAPUS -->
      <button type="button"
              class="dropdown-item action-item action-item-danger akses-delete-trigger"
              data-id="<?= (int)$r['id_akses'] ?>"
              data-nama="<?= htmlspecialchars($nm !== '' ? $nm : $un) ?>">
        <span class="action-icon action-icon-delete">
          <i class="mdi mdi-trash-can-outline"></i>
        </span>
        <span class="action-text">Hapus</span>
      </button>
    </div>
  </div>
</td>


      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
 
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.btn-copy-chat');
  if (!btn) return;
  const val = btn.getAttribute('data-chat') || '';
  if (!val) return;

  navigator.clipboard.writeText(val).then(function () {
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="mdi mdi-check"></i>';
    btn.style.borderColor = '#141414ff';
    btn.style.color = '#141414ff';
    setTimeout(function () {
      btn.innerHTML = old;
      btn.style.borderColor = '#e5e7eb';
      btn.style.color = '#374151';
    }, 1200);
  });
});
</script>

<script>
(function(){
  const API = 'akses_login_api.php';
  const PERIOD_MS = 180000; // 3 menit

  // ===== Helpers umum =====
  const emptyChoice = v => { v=(v||'').toLowerCase(); return !v||v==='pilih'||v==='(pilih)'||v==='select'; };

  async function api(action, payload){
    const r = await fetch(API, {
      method:'POST',
      credentials:'include',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body:new URLSearchParams({action, ...payload})
    });
    const d = await r.json().catch(()=>({ok:false,msg:'Server error'}));
    if (!r.ok || !d.ok) throw new Error(d.msg || 'Gagal');
    return d;
  }

  function lockRowSelects(tr, locked = true){
    const r = tr.querySelector('.sel-role');
    const s = tr.querySelector('.sel-status');
    if (r) r.disabled = locked;
    if (s) s.disabled = locked;
  }

  // ===== Commit button enable/disable (hanya bila berubah dari baseline DB) =====
  function computeState(tr){
    const roleSel = tr.querySelector('.sel-role');
    const statSel = tr.querySelector('.sel-status');
    const btn     = tr.querySelector('.btn-commit');
    if (!roleSel || !statSel || !btn) return;

    if (roleSel.disabled || statSel.disabled){
      btn.disabled = true;
      btn.classList.remove('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-autorenew"></i>';
      return;
    }

    const roleVal = (roleSel.value||'').trim();
    const statVal = (statSel.value||'').trim();

    if (!('savedRole' in tr.dataset))  tr.dataset.savedRole   = (tr.dataset.initialRole   ?? roleVal ?? '');
    if (!('savedStatus' in tr.dataset))tr.dataset.savedStatus = (tr.dataset.initialStatus ?? statVal ?? '');

    const savedRole = tr.dataset.savedRole   || '';
    const savedStat = tr.dataset.savedStatus || '';

    const bothPicked = !!roleVal && !!statVal;
    const changed    = bothPicked && (roleVal !== savedRole || statVal !== savedStat);

    if (changed){
      btn.disabled = false;
      btn.classList.add('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-autorenew"></i>';
    }else{
      btn.disabled = true;
      btn.classList.remove('teal-enabled');
      if (!tr._justCommitted){
        btn.innerHTML = '<i class="mdi mdi-autorenew"></i>';
      }
    }
  }

  // ===== Publish aktif hanya jika baseline DB terisi (bukan sekadar dropdown) =====
  function updatePublishState(tr){
    const btn  = tr.querySelector('.veri-wrap .btn-publish');
    if (!btn) return;
    const hasPill = !!tr.querySelector('.veri-wrap .code-pill');

    const savedRole = (tr.dataset.savedRole || tr.dataset.initialRole || '').trim().toLowerCase();
    const savedStat = (tr.dataset.savedStatus || tr.dataset.initialStatus || '').trim().toLowerCase();

    const can = !emptyChoice(savedRole) && !emptyChoice(savedStat) && !hasPill;
    btn.disabled = !can;
    btn.classList.toggle('ready', can);
  }
  window.updatePublishState = updatePublishState; // agar bisa dipanggil blok lain jika ada

  // ===== Sinkron nilai UI dengan DB untuk 1 baris =====
  async function syncRowFromDB(tr){
    const id = tr?.dataset?.id; if (!id) return;
    try{
      const d = await api('get_state', {id});
      tr.dataset.savedRole   = d.role || '';
      tr.dataset.savedStatus = d.status || '';

      const rs = tr.querySelector('.sel-role');
      const ss = tr.querySelector('.sel-status');
      if (rs){
        if (emptyChoice(d.role)) {
          const opt = Array.from(rs.options).find(o => emptyChoice(o.value) || /pilih/i.test(o.textContent));
          if (opt) rs.value = opt.value;
        } else {
          const opt = Array.from(rs.options).find(o => (o.value||o.textContent).toLowerCase() === String(d.role).toLowerCase());
          if (opt) rs.value = opt.value; else rs.value = String(d.role);
        }
      }
      if (ss){
        if (emptyChoice(d.status)) {
          const opt = Array.from(ss.options).find(o => emptyChoice(o.value) || /pilih|pending/i.test(o.textContent));
          if (opt) ss.value = opt.value;
        } else {
          const opt = Array.from(ss.options).find(o => (o.value||o.textContent).toLowerCase() === String(d.status).toLowerCase());
          if (opt) ss.value = opt.value; else ss.value = String(d.status);
        }
      }
      updatePublishState(tr);
      computeState(tr);
    }catch(_){ /* diam */ }
  }
  window.syncRowFromDB = syncRowFromDB;

  // ===== Kapsul kode & countdown =====
  function renderPill(code, expireAtMs, live=true){
    const cls  = live ? 'code-pill live fade-replace' : 'code-pill expired fade-replace';
    const meta = live ? `<div class="code-meta"><span class="v-time">03:00</span></div>`
                      : `<div class="code-meta">Expired</div>`;
    return `
      <div class="${cls}" data-expire-at="${expireAtMs||''}">
        <div class="code-main">
          <span class="code-val">${code}</span>
          <button class="btn-icon btn-copy" type="button" title="Salin kode" data-code="${code}">
            <i class="mdi mdi-content-copy"></i>
          </button>
        </div>
        ${meta}
        <button class="btn-refresh-code" type="button" title="Terbitkan ulang"><i class="mdi mdi-refresh"></i></button>
      </div>`;
  }
  function startCountdown(pill){
    const tNode = pill.querySelector('.v-time'); if (!tNode) return;
    const until = parseInt(pill.dataset.expireAt||'0',10);
    const fmt = (s)=>{ const m=Math.floor(s/60), d=s%60; return String(m).padStart(2,'0')+':'+String(d).padStart(2,'0'); };
    (function tick(){
      const rem = Math.max(0, Math.floor((until - Date.now())/1000));
      if (rem <= 0){
        pill.classList.remove('live'); pill.classList.add('expired');
        tNode.parentElement.textContent = 'Expired';
        pill.querySelector('.btn-copy')?.setAttribute('disabled','disabled');
        return;
      }
      tNode.textContent = fmt(rem);
      pill._timer = setTimeout(tick, 1000);
    })();
  }

  // ===== INIT =====
  document.querySelectorAll('#example tbody tr').forEach(tr=>{
    const r = tr.querySelector('.sel-role');
    const s = tr.querySelector('.sel-status');
    tr.dataset.initialRole   = tr.dataset.initialRole   ?? (r ? r.value : '');
    tr.dataset.initialStatus = tr.dataset.initialStatus ?? (s ? s.value : '');

    syncRowFromDB(tr); // pastikan UI = DB saat load
    r?.addEventListener('change', ()=>computeState(tr));
    s?.addEventListener('change', ()=>computeState(tr));
    computeState(tr);
    updatePublishState(tr);
  });

  // ===== HANDLERS =====

  // COMMIT → SIMPAN DB → ikon centang → publish aktif (tanpa refresh)
  document.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('.btn-commit');
    if (!btn || btn.disabled) return;

    const tr      = btn.closest('tr');
    const id      = tr.dataset.id;
    const roleVal = tr.querySelector('.sel-role')?.value?.trim() || '';
    const statVal = tr.querySelector('.sel-status')?.value?.trim() || '';

    btn.disabled = true;
    btn.classList.add('teal-enabled');
    btn.innerHTML = '<i class="mdi mdi-autorenew mdi-spin"></i>';

    try{
      const d = await api('save_role_status', { id, role: roleVal, status: statVal });

      // baseline = nilai dari DB (balikan API)
      tr.dataset.savedRole   = d.role || roleVal;
      tr.dataset.savedStatus = d.status || statVal;

      // set <select> agar match DB
      const rs = tr.querySelector('.sel-role');
      const ss = tr.querySelector('.sel-status');
      if (rs) rs.value = tr.dataset.savedRole;
      if (ss) ss.value = tr.dataset.savedStatus;

      // ikon centang dan tetap disabled
      btn.classList.remove('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-check"></i>';
      btn.disabled  = true;

      // publish aktif berdasarkan baseline DB
      updatePublishState(tr);

      // kalau mau kunci dropdown, uncomment:
      // lockRowSelects(tr, true);

    }catch(e){
      alert(e.message || 'Gagal menyimpan');
      btn.disabled = false;
      btn.classList.remove('teal-enabled');
      btn.innerHTML = '<i class="mdi mdi-autorenew"></i>';
    }
  });

  // TERBITKAN KODE
  document.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('.btn-publish');
    if (!btn || btn.disabled) return;
    const tr   = btn.closest('tr');
    const id   = tr.dataset.id;
    const wrap = tr.querySelector('.veri-wrap');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Menerbitkan…';

    try{
      const d = await api('publish_code', {id});
      const expireAt = Date.now() + PERIOD_MS;
      wrap.innerHTML = renderPill(d.code, expireAt, true);
      startCountdown(wrap.querySelector('.code-pill'));
    }catch(e){
      alert(e.message || 'Gagal menerbitkan kode');
      btn.disabled = false;
      btn.innerHTML = '<i class="mdi mdi-ticket-confirmation-outline"></i> Terbitkan Kode';
    }
  });

  

  // PERBARUI KODE
  document.addEventListener('click', async (ev)=>{
    const ref = ev.target.closest('.btn-refresh-code');
    if (!ref) return;
    const tr   = ref.closest('tr');
    const id   = tr.dataset.id;
    const wrap = tr.querySelector('.veri-wrap');

    ref.innerHTML = '<i class="mdi mdi-autorenew mdi-spin"></i>';
    try{
      const d = await api('renew_code', {id});
      const expireAt = Date.now() + PERIOD_MS;
      wrap.innerHTML = renderPill(d.code, expireAt, true);
      startCountdown(wrap.querySelector('.code-pill'));
    }catch(e){
      alert('Gagal memperbarui kode');
      ref.innerHTML = '<i class="mdi mdi-refresh"></i>';
    }
  });

  // COPY
  document.addEventListener('click', async (ev)=>{
    const c = ev.target.closest('.btn-copy');
    if (!c || c.disabled) return;
    try{
      await navigator.clipboard.writeText(c.dataset.code||'');
      c.innerHTML = '<i class="mdi mdi-check"></i>';
      setTimeout(()=> c.innerHTML = '<i class="mdi mdi-content-copy"></i>', 1000);
    }catch(_){
      c.innerHTML = '<i class="mdi mdi-close"></i>';
      setTimeout(()=> c.innerHTML = '<i class="mdi mdi-content-copy"></i>', 1000);
    }
  });
})();
</script>

<script>
(function(){
  const selects = document.querySelectorAll(
    'select.form-control.sel-role, select.form-control.sel-status'
  );

  function isPlaceholder(el){
    const val  = (el.value || '').trim();
    const text = (el.options[el.selectedIndex]?.text || '').trim();
    // kosong ATAU teksnya mengandung "Pilih" (--- Pilih --- / --Pilih-- / Pilih ...)
    const textLooksPlaceholder = /^-*\(?\s*pilih\s*\)?-*$|^pilih$/i.test(text);
    return val === '' || textLooksPlaceholder;
  }

  function apply(el){
    el.classList.toggle('is-placeholder', isPlaceholder(el));
  }

  selects.forEach(el => {
    apply(el);                   // initial state (PHP pre-selected)
    el.addEventListener('change', () => apply(el));  // update saat berubah
  });
})();
</script>
<script>
// Generate chat_id acak 9 digit setiap kali modal "Tambah Akses Login" dibuka
(function () {
  window.addEventListener('load', function () {
    if (!window.jQuery) return;
    var $ = window.jQuery;

    function generateChatId9Digit() {
      // 100.000.000 s.d. 999.999.999 → selalu 9 digit, tidak diawali 0
      var n = Math.floor(Math.random() * 900000000) + 100000000;
      return String(n);
    }

    $('#addAksesModal').on('show.bs.modal', function () {
      var $input = $(this).find('input[name="chat_id"]');
      if (!$input.length) return;

      // Setiap kali modal dibuka, isi ulang dengan ID baru
      $input.val(generateChatId9Digit());
    });
  });
})();
</script>

<script>
(function(){
  const form   = document.getElementById('formAddAkses');
  const btn    = document.getElementById('btnAddAkses');
  const errBox = document.getElementById('addAksesErr');

  function showErr(msg){
    errBox.textContent = msg || 'Gagal menyimpan.';
    errBox.classList.remove('d-none');
  }
  function hideErr(){ errBox.classList.add('d-none'); errBox.textContent=''; }

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    hideErr();

    const fd = new FormData(form);
    const nama      = (fd.get('nama')||'').trim();
    const username  = (fd.get('username')||'').trim();
    const pass      = (fd.get('password')||'');
    const pass2     = (fd.get('password2')||'');
    const role      = (fd.get('role')||'').trim().toLowerCase();
    const chat_id   = (fd.get('chat_id')||'').trim();

    if (!username) return showErr('Username wajib diisi.');
    if (!pass || pass.length < 6) return showErr('Password minimal 6 karakter.');
    if (pass !== pass2) return showErr('Konfirmasi password tidak sama.');
    if (!role) return showErr('Silakan pilih role.');

    btn.disabled = true;
    const oldHTML = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Menyimpan...';

    try{
      const body = new URLSearchParams();
      body.set('action', 'create_akses');
      body.set('nama', nama);
      body.set('username', username);
      body.set('password', pass);
      body.set('role', role);
      body.set('chat_id', chat_id);
      const csrf = form.querySelector('input[name="_token"]')?.value || '';
      if (csrf) body.set('_token', csrf);

      const r = await fetch('tambah_akses.php', {
        method: 'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        credentials:'include',
        body
      });
      const d = await r.json().catch(()=>({ok:false,msg:'Server error'}));
      if (!r.ok || !d.ok) throw new Error(d.msg || 'Gagal menyimpan.');

      // Berhasil: tutup modal dan refresh agar tabel sinkron rapi
      // (kalau mau tanpa refresh, bisa generate satu <tr> baru dari d.row)
      location.reload();

    }catch(err){
      showErr(err.message || 'Gagal menyimpan.');
      btn.disabled = false;
      btn.innerHTML = oldHTML;
    }
  });
})();
</script>

</div>

                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Modal Edit Akses Login -->
<div class="modal fade" id="editAksesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form id="formEditAkses" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title">Edit Akses Login</h5>
          <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div id="editAksesErr" class="alert alert-danger d-none mb-3"></div>

          <?php if (!empty($csrf)): ?>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
          <?php endif; ?>

          <input type="hidden" name="id" id="editAksesId">

          <div class="form-group mb-2">
            <label>Nama <span class="text-danger">*</span></label>
            <input type="text" name="nama" id="editNama" class="form-control" required>
          </div>

          <div class="form-group mb-2">
            <label>Username <span class="text-danger">*</span></label>
            <input type="text" name="username" id="editUsername" class="form-control" required>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6 mb-2">
              <label>New Password</label>
              <input type="password" name="password" id="editPassword" class="form-control" minlength="6">
            </div>
            <div class="form-group col-md-6 mb-2">
              <label>Confirm New Password</label>
              <input type="password" name="password2" id="editPassword2" class="form-control" minlength="6">
            </div>
          </div>

          <small class="text-muted">
            Kosongkan kolom password bila tidak ingin mengubah password.
          </small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">Batal</button>
          <button id="btnEditAkses" type="submit" class="btn" style="background:#182958;color:#fff;">
            Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

        <!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="hapusAksesModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Hapus Akses Login</h5>
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
              Yakin akan menghapus akses login berikut?
            </p>
            <div id="hapusAksesNama"
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
        <button type="button" class="btn btn-danger btn-sm" id="btnConfirmHapus">
          <i class="mdi mdi-delete mr-1"></i> Hapus
        </button>
      </div>

    </div>
  </div>
</div>
<style>
.dropdown-actions{
  position: relative;
}

/* Tombol 3 titik (mirip tombol reset abu-abu) */
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

/* jika menu ditempatkan di ATAS tombol */
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

/* HEADER MINI DI DROPDOWN */
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

/* ITEM AKSI */
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

/* khusus HAPUS */
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
  /* Lebarkan ukuran modal hapus akses */
  #hapusAksesModal .modal-dialog{
    max-width: 520px;      /* lebar modal (desktop) */
    width: 100%;
  }

  @media (max-width: 576px){
    #hapusAksesModal .modal-dialog{
      max-width: 100%;     /* di HP tetap full, biar responsif */
      margin: 0 12px;
    }
  }
</style>

<script>
(function(){
  const modal   = document.getElementById('editAksesModal');
  const form    = document.getElementById('formEditAkses');
  const errBox  = document.getElementById('editAksesErr');
  const inputId = document.getElementById('editAksesId');
  const inputNm = document.getElementById('editNama');
  const inputUn = document.getElementById('editUsername');
  const inputPw = document.getElementById('editPassword');
  const inputPw2= document.getElementById('editPassword2');
  const btnSave = document.getElementById('btnEditAkses');

  if (!modal || !form) return;

  function showErr(msg){
    if (!errBox) return;
    errBox.textContent = msg || 'Gagal menyimpan perubahan.';
    errBox.classList.remove('d-none');
  }
  function hideErr(){
    if (!errBox) return;
    errBox.textContent = '';
    errBox.classList.add('d-none');
  }

  // Buka modal + isi data saat klik "Edit" di dropdown
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.akses-edit-trigger');
    if (!btn) return;

    e.preventDefault();
    hideErr();

    const id   = btn.dataset.id || '';
    const nama = btn.dataset.nama || '';
    const user = btn.dataset.username || '';

    if (inputId) inputId.value = id;
    if (inputNm) inputNm.value = nama;
    if (inputUn) inputUn.value = user;
    if (inputPw)  inputPw.value  = '';
    if (inputPw2) inputPw2.value = '';

    if (window.bootstrap && bootstrap.Modal){
      new bootstrap.Modal(modal).show();
    }else if (window.jQuery && typeof jQuery(modal).modal === 'function'){
      jQuery(modal).modal('show');
    }
  });

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    hideErr();

    const fd   = new FormData(form);
    const id   = (fd.get('id') || '').toString().trim();
    const nama = (fd.get('nama') || '').toString().trim();
    const user = (fd.get('username') || '').toString().trim();
    const pass = (fd.get('password') || '').toString();
    const pass2= (fd.get('password2') || '').toString();

    if (!nama)   return showErr('Nama wajib diisi.');
    if (!user)   return showErr('Username wajib diisi.');
    if (pass !== '' && pass.length < 6)
      return showErr('Password baru minimal 6 karakter.');
    if (pass !== pass2)
      return showErr('Konfirmasi password baru tidak sama.');

    const oldHTML = btnSave.innerHTML;
    btnSave.disabled = true;
    btnSave.innerHTML =
      "<span class='spinner-border spinner-border-sm mr-1'></span> Menyimpan...";

    try{
      const body = new URLSearchParams();
      body.set('id', id);
      body.set('nama', nama);
      body.set('username', user);
      body.set('password', pass);
      body.set('password2', pass2);
      const csrf = form.querySelector('input[name=\"_token\"]')?.value || '';
      if (csrf) body.set('_token', csrf);

      const res = await fetch('edit_akses.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        credentials: 'include',
        body
      });
      const data = await res.json().catch(()=>({ok:false,msg:'Server error'}));
      if (!res.ok || !data.ok) throw new Error(data.msg || 'Gagal menyimpan perubahan.');

      // sukses -> reload agar tabel sinkron
      window.location.reload();

    }catch(err){
      showErr(err.message || 'Gagal menyimpan perubahan.');
      btnSave.disabled = false;
      btnSave.innerHTML = oldHTML;
    }
  });
})();
</script>
<script>
(function () {
  let deleteId = null;

  // Klik "Hapus" di dropdown
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.akses-delete-trigger');
    if (!btn) return;

    e.preventDefault();
    deleteId = btn.dataset.id || null;
    const nama = btn.dataset.nama || '(tanpa nama)';

    const label = document.getElementById('hapusAksesNama');
    if (label) label.textContent = nama;

    const modal = document.getElementById('hapusAksesModal');
    if (!modal) return;

    if (window.bootstrap && bootstrap.Modal){
      new bootstrap.Modal(modal).show();
    }else if (window.jQuery && typeof jQuery(modal).modal === 'function'){
      jQuery(modal).modal('show');
    }
  });

  // Tombol konfirmasi hapus
  const btnConfirm = document.getElementById('btnConfirmHapus');
  if (!btnConfirm) return;

  btnConfirm.addEventListener('click', function () {
    if (!deleteId) return;

    const $btn = this;
    if ($btn.dataset.loading === '1') return;
    $btn.dataset.loading = '1';

    $btn.disabled = true;
    $btn.innerHTML =
      "<span class='spinner-border spinner-border-sm mr-1' role='status' aria-hidden='true'></span> Menghapus...";

    // tetap pakai hapus_akses.php?id=...
    window.location.href = 'hapus_akses.php?id=' + encodeURIComponent(deleteId);
  });
})();
</script>

        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
     <?php
     include "footer.php";
      ?>