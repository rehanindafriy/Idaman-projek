<?php
declare(strict_types=1);

/**
 * tech_auto_sync_setup.php
 * Sinkron otomatis akses_login (role='tech') -> tabel tech (kolom: *_tech)
 * Letakkan include file ini DI PALING ATAS tech.php (sebelum output HTML).
 */

if (!isset($pdo)) {
  require_once __DIR__ . '/koneksi.php';
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function _silent($_){ /* no-op; aktifkan utk debug jika perlu */ }

/** 1) Pastikan tabel TECH sesuai skema yang kamu pakai */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS tech (
    id_tech        INT AUTO_INCREMENT PRIMARY KEY,
    nama_tech      VARCHAR(255) NOT NULL,
    username_tech  VARCHAR(100) NOT NULL,
    password_tech  VARCHAR(50)  NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/** 1a) Upgrade panjang password_tech jika < 60 (supaya hash bcrypt/argon tidak terpotong) */
try {
  $len = $pdo->query("
    SELECT CHARACTER_MAXIMUM_LENGTH
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tech' AND COLUMN_NAME='password_tech'
  ")->fetchColumn();
  if ($len !== false && (int)$len < 60) {
    $pdo->exec("ALTER TABLE tech MODIFY password_tech VARCHAR(255) NOT NULL DEFAULT ''");
  }
} catch (Throwable $e) { _silent($e->getMessage()); }

/** 1b) Index unik pada username_tech (untuk ON DUPLICATE KEY) */
try {
  $exists = $pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME='tech'
      AND INDEX_NAME='uk_tech_username_tech'
  ")->fetchColumn();
  if ((int)$exists === 0) {
    $pdo->exec("ALTER TABLE tech ADD UNIQUE KEY uk_tech_username_tech (username_tech)");
  }
} catch (Throwable $e) { _silent($e->getMessage()); }

/** 2) (Opsional tapi aman) — harden kolom otp di akses_login agar tidak error 1364 */
try {
  $q = $pdo->query("
    SELECT IS_NULLABLE, COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME='akses_login'
      AND COLUMN_NAME='otp'
  ");
  $c = $q->fetch(PDO::FETCH_ASSOC);
  if ($c && $c['IS_NULLABLE'] === 'NO' && $c['COLUMN_DEFAULT'] === null) {
    $pdo->exec("ALTER TABLE akses_login MODIFY otp VARCHAR(32) NOT NULL DEFAULT ''");
  }
} catch (Throwable $e) { _silent($e->getMessage()); }

/** 3) Pasang TRIGGER KHUSUS TECH (tidak menyentuh trigger admin/ta) */
foreach (['akses_login_ai_tech','akses_login_au_tech','akses_login_ad_tech'] as $t) {
  try { $pdo->exec("DROP TRIGGER IF EXISTS `$t`"); } catch (Throwable $e) {}
}

/* AFTER INSERT: jika role=tech -> upsert ke tech */
$pdo->exec("
  CREATE TRIGGER akses_login_ai_tech
  AFTER INSERT ON akses_login
  FOR EACH ROW
  BEGIN
    IF LOWER(NEW.role) = 'tech' THEN
      INSERT INTO tech (nama_tech, username_tech, password_tech)
      VALUES (NEW.nama, NEW.username, COALESCE(NEW.password,''))
      ON DUPLICATE KEY UPDATE
        nama_tech = VALUES(nama_tech),
        password_tech = VALUES(password_tech);
    END IF;
  END
");

/* AFTER UPDATE:
   - jika OLD.role=tech lalu berubah bukan tech -> hapus di tech
   - jika NEW.role=tech -> upsert (dan kalau username pindah, bersihkan yang lama)
*/
$pdo->exec("
  CREATE TRIGGER akses_login_au_tech
  AFTER UPDATE ON akses_login
  FOR EACH ROW
  BEGIN
    IF LOWER(OLD.role) = 'tech' AND LOWER(NEW.role) <> 'tech' THEN
      DELETE FROM tech WHERE LOWER(username_tech) = LOWER(OLD.username);
    ELSEIF LOWER(NEW.role) = 'tech' THEN
      IF OLD.username <> NEW.username THEN
        DELETE FROM tech WHERE LOWER(username_tech) = LOWER(OLD.username);
      END IF;
      INSERT INTO tech (nama_tech, username_tech, password_tech)
      VALUES (NEW.nama, NEW.username, COALESCE(NEW.password,''))
      ON DUPLICATE KEY UPDATE
        nama_tech = VALUES(nama_tech),
        password_tech = CASE
                          WHEN COALESCE(NEW.password,'') <> '' THEN VALUES(password_tech)
                          ELSE tech.password_tech
                        END;
    END IF;
  END
");

/* AFTER DELETE: jika baris TECH dihapus dari akses_login -> bersihkan di tech */
$pdo->exec("
  CREATE TRIGGER akses_login_ad_tech
  AFTER DELETE ON akses_login
  FOR EACH ROW
  BEGIN
    IF LOWER(OLD.role) = 'tech' THEN
      DELETE FROM tech WHERE LOWER(username_tech) = LOWER(OLD.username);
    END IF;
  END
");

/** 4) Backfill/seed awal dari akses_login (role=tech) */
function _tech_backfill(PDO $pdo): void {
  // Upsert semua TECH dari akses_login -> tech
  $pdo->exec("
    INSERT INTO tech (nama_tech, username_tech, password_tech)
    SELECT al.nama, al.username, COALESCE(al.password,'')
    FROM akses_login al
    WHERE LOWER(al.role) = 'tech'
    ON DUPLICATE KEY UPDATE
      tech.nama_tech = VALUES(nama_tech),
      tech.password_tech = VALUES(password_tech)
  ");

  // Hapus yang di tabel tech tapi sudah tidak punya akses_login dengan role=tech
  $pdo->exec("
    DELETE t FROM tech t
    LEFT JOIN akses_login al
      ON LOWER(al.username) = LOWER(t.username_tech) AND LOWER(al.role) = 'tech'
    WHERE al.id_akses IS NULL
  ");
}

try {
  $pdo->beginTransaction();
  _tech_backfill($pdo);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
}

/** 5) Manual seed via ?seed=1 */
if (isset($_GET['seed']) && $_GET['seed'] === '1') {
  try {
    $pdo->beginTransaction();
    _tech_backfill($pdo);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
  }
}
