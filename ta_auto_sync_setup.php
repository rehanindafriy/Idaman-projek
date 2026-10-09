<?php
declare(strict_types=1);

/**
 * ta_auto_sync_setup.php
 * Sinkron otomatis akses_login (role='ta') -> tabel ta (kolom: *_ta)
 * Letakkan include file ini DI PALING ATAS ta.php (sebelum output HTML).
 */

if (!isset($pdo)) {
  require_once __DIR__ . '/koneksi.php';
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function _silent($_){ /* no-op; hidupkan untuk debug jika mau */ }

/** 1) Pastikan tabel TA sesuai skema yang kamu pakai */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS ta (
    id_ta        INT AUTO_INCREMENT PRIMARY KEY,
    nama_ta      VARCHAR(255) NOT NULL,
    username_ta  VARCHAR(100) NOT NULL,
    password_ta  VARCHAR(50)  NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/** 1a) Upgrade panjang password_ta jika < 60 (supaya hash tidak terpotong) */
try {
  $len = $pdo->query("
    SELECT CHARACTER_MAXIMUM_LENGTH
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='ta' AND COLUMN_NAME='password_ta'
  ")->fetchColumn();
  if ($len !== false && (int)$len < 60) {
    $pdo->exec("ALTER TABLE ta MODIFY password_ta VARCHAR(255) NOT NULL DEFAULT ''");
  }
} catch (Throwable $e) { _silent($e->getMessage()); }

/** 1b) Index unik pada username_ta (untuk ON DUPLICATE KEY) */
try {
  $exists = $pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME='ta'
      AND INDEX_NAME='uk_ta_username_ta'
  ")->fetchColumn();
  if ((int)$exists === 0) {
    $pdo->exec("ALTER TABLE ta ADD UNIQUE KEY uk_ta_username_ta (username_ta)");
  }
} catch (Throwable $e) { _silent($e->getMessage()); }

/** 2) (Opsional, tapi aman) — harden kolom otp di akses_login agar tidak error 1364 */
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

/** 3) Pasang TRIGGER KHUSUS TA (tidak menyentuh trigger admin) */
foreach (['akses_login_ai_ta','akses_login_au_ta','akses_login_ad_ta'] as $t) {
  try { $pdo->exec("DROP TRIGGER IF EXISTS `$t`"); } catch (Throwable $e) {}
}

/* AFTER INSERT: jika role=ta -> upsert ke ta */
$pdo->exec("
  CREATE TRIGGER akses_login_ai_ta
  AFTER INSERT ON akses_login
  FOR EACH ROW
  BEGIN
    IF LOWER(NEW.role) = 'ta' THEN
      INSERT INTO ta (nama_ta, username_ta, password_ta)
      VALUES (NEW.nama, NEW.username, COALESCE(NEW.password,''))
      ON DUPLICATE KEY UPDATE
        nama_ta = VALUES(nama_ta),
        password_ta = VALUES(password_ta);
    END IF;
  END
");

/* AFTER UPDATE:
   - jika OLD.role=ta lalu berubah bukan ta -> hapus di ta
   - jika NEW.role=ta -> upsert (dan kalau username pindah, bersihkan yang lama)
*/
$pdo->exec("
  CREATE TRIGGER akses_login_au_ta
  AFTER UPDATE ON akses_login
  FOR EACH ROW
  BEGIN
    IF LOWER(OLD.role) = 'ta' AND LOWER(NEW.role) <> 'ta' THEN
      DELETE FROM ta WHERE LOWER(username_ta) = LOWER(OLD.username);
    ELSEIF LOWER(NEW.role) = 'ta' THEN
      IF OLD.username <> NEW.username THEN
        DELETE FROM ta WHERE LOWER(username_ta) = LOWER(OLD.username);
      END IF;
      INSERT INTO ta (nama_ta, username_ta, password_ta)
      VALUES (NEW.nama, NEW.username, COALESCE(NEW.password,''))
      ON DUPLICATE KEY UPDATE
        nama_ta = VALUES(nama_ta),
        password_ta = CASE
                        WHEN COALESCE(NEW.password,'') <> '' THEN VALUES(password_ta)
                        ELSE ta.password_ta
                      END;
    END IF;
  END
");

/* AFTER DELETE: jika baris TA dihapus dari akses_login -> bersihkan di ta */
$pdo->exec("
  CREATE TRIGGER akses_login_ad_ta
  AFTER DELETE ON akses_login
  FOR EACH ROW
  BEGIN
    IF LOWER(OLD.role) = 'ta' THEN
      DELETE FROM ta WHERE LOWER(username_ta) = LOWER(OLD.username);
    END IF;
  END
");

/** 4) Backfill/seed awal dari akses_login (role=ta) */
function _ta_backfill(PDO $pdo): void {
  // Upsert semua TA dari akses_login -> ta
  $pdo->exec("
    INSERT INTO ta (nama_ta, username_ta, password_ta)
    SELECT al.nama, al.username, COALESCE(al.password,'')
    FROM akses_login al
    WHERE LOWER(al.role) = 'ta'
    ON DUPLICATE KEY UPDATE
      ta.nama_ta = VALUES(nama_ta),
      ta.password_ta = VALUES(password_ta)
  ");

  // Hapus yang di tabel ta tapi sudah tidak punya akses_login dengan role=ta
  $pdo->exec("
    DELETE t FROM ta t
    LEFT JOIN akses_login al
      ON LOWER(al.username) = LOWER(t.username_ta) AND LOWER(al.role) = 'ta'
    WHERE al.id_akses IS NULL
  ");
}

try {
  $pdo->beginTransaction();
  _ta_backfill($pdo);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
}

/** 5) Manual seed via ?seed=1 */
if (isset($_GET['seed']) && $_GET['seed'] === '1') {
  try {
    $pdo->beginTransaction();
    _ta_backfill($pdo);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
  }
}
