<?php
declare(strict_types=1);

// ===== AUTO SETUP TECH (WAJIB sebelum output HTML) =====
require_once __DIR__ . '/koneksi.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ob_start();
require __DIR__ . '/tech_auto_sync_setup.php';  // <— KHUSUS TECH
ob_end_clean();

if (!function_exists('short4')) {
  function short4(?string $s): string {
    $s = (string)$s;
    $len = strlen($s);
    if ($len <= 24) return $s !== '' ? $s : '—';
    return substr($s, 0, 12) . '…' . substr($s, -12);
  }
}

// Ambil data TECH + status done dari akses_login
$sql = <<<SQL
SELECT
  t.id_tech,
  t.nama_tech     AS nama_tech,
  t.username_tech AS user_tech,
  t.password_tech AS pass_tech,

  -- flags stechtus (ambil dari akses_login untuk username yang sama)
  MAX(CASE WHEN LOWER(al.role)='tech' AND LOWER(al.status)='active'    THEN 1 ELSE 0 END) AS st_active,
  MAX(CASE WHEN LOWER(al.role)='tech' AND LOWER(al.status)='pending'   THEN 1 ELSE 0 END) AS st_pending,
  MAX(CASE WHEN LOWER(al.role)='tech' AND LOWER(al.status)='suspended' THEN 1 ELSE 0 END) AS st_suspended,

  MAX(CASE WHEN LOWER(al.role)='tech' AND al.done='yes' THEN 1 ELSE 0 END) AS done_yes
FROM tech t
LEFT JOIN akses_login al
  ON LOWER(al.username) = LOWER(t.username_tech)
GROUP BY t.id_tech, t.nama_tech, t.username_tech, t.password_tech
ORDER BY t.id_tech ASC
SQL;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ===== Render UI =====
include "header.php";
?>
<!-- partial -->
<div class="main-panel">
  <div class="content-wrapper">
    <div class="row">
      <div class="col-md-12 grid-margin">
        <div class="d-flex justify-content-between align-items-end flex-wrap">
          <div class="mr-md-3 mr-xl-5">
            <h2 class="mb-1">Data Tech</h2>
            <p class="mb-0">Berisi daftar seluruh akun Tech yang memiliki akses ke sistem.</p>

            <p>Ditarik otomatis dari <code>akses_login</code> (role = <b>tech</b>).</p>
          </div>
          <div class="d-flex flex-column align-items-end me-3 mt-n1">
            <img src="images/logo.png" alt="Logo e-Siklo" class="mb-1" style="height:45px; margin-top:-10px; display:block;">
            <br>
            <br>
            <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
              <i class="mdi mdi-home text-muted mx-1 crumb-icon" style="font-size:13px;line-height:1;vertical-align:-90px"></i>
              <a href="index.php" class="text-muted mx-1 crumb-mid">&nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;</a>
              <span class="text-primary crumb-mid">Data Tech</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- tabel -->
    <div class="row">
      <div class="col-md-12 stretch-card">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Data Tech</p>

            <div class="table-responsive">
              <table id="example" class="display" style="width:100%">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Password (hash)</th>
                    <th style="width:50px">Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)): $no=1; foreach ($rows as $r): ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td class="td-name" data-order="<?= htmlspecialchars($r['nama_tech'], ENT_QUOTES, 'UTF-8') ?>">
  <div class="name-cell">
    <img
      class="avatar"
      src="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') ?>/images/user/tech.png"
      alt="tech"
      width="32" height="32"
      loading="lazy" decoding="async"
    >
    <span><?= htmlspecialchars($r['nama_tech'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</td>
                    <td><?= htmlspecialchars($r['user_tech']) ?></td>
                    <td style="max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"
                        title="<?= htmlspecialchars($r['pass_tech'] ?? '') ?>">
                      <?= htmlspecialchars(short4($r['pass_tech'] ?? '—')) ?>
                    </td>
                   <td class="text-center">
  <?php
    $isActive    = (int)($r['st_active']    ?? 0) === 1;
    $isPending   = (int)($r['st_pending']   ?? 0) === 1;
    $isSuspended = (int)($r['st_suspended'] ?? 0) === 1;

    if ($isActive) {
      // Active = centang hijau
      echo '<i class="mdi mdi-check-circle" title="Active" style="font-size:23px;color:#12B76A;"></i>';
    } elseif ($isPending) {
      // Pending = tanda seru kuning
      echo '<i class="mdi mdi-alert-circle-outline" title="Pending" style="font-size:23px;color:#F59E0B;"></i>';
    } elseif ($isSuspended) {
      // Suspended = X merah
      echo '<i class="mdi mdi-close-circle" title="Suspended" style="font-size:23px;color:#EF4444;"></i>';
    } else {
      // Fallback kalau belum ada status (mis. data belum sinkron)
      echo '<i class="mdi mdi-help-circle-outline" title="Unknown" style="font-size:23px;color:#64748B;"></i>';
    }
  ?>
</td>

                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="5" class="text-center text-muted">Belum ada data Tech.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Manual sync khusus TECH -->
            <div class="mt-3">
              <a class="btn btn-sm btn-outline-secondary" href="tech_auto_sync_setup.php?seed=1"
                 onclick="setTimeout(()=>location.reload(),500);">Manual Sync</a>
              <small class="text-muted ms-2">Klik jika hosting memblokir trigger—data Tech diselaraskan & halaman di-refresh.</small>
            </div>

          </div>
        </div>
      </div>
    </div>

  </div><!-- /.content-wrapper -->
  <?php include "footer.php"; ?>
</div><!-- /.main-panel -->
