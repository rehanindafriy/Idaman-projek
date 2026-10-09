<?php
declare(strict_types=1);

/**
 * admin_auto_sync_setup.php
 * Idempotent setup + triggers + backfill sinkron admin
 * - PANGGIL dari admin.php di paling atas sebelum output HTML
 */

if (!isset($pdo)) {
  require_once __DIR__ . '/koneksi.php';
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function _es_echo($_){ /* senyapkan; aktifkan saat debug */ }

/** Pastikan tabel admin siap */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS admin (
    id_admin   INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    username   VARCHAR(64)  NOT NULL,
    password   VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admin_username (username)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
_es_echo('admin table ok');

/** Cegah error kolom otp (NOT NULL tanpa default) */
try {
  $q = $pdo->prepare("
    SELECT IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'akses_login'
      AND COLUMN_NAME  = 'otp'
    LIMIT 1
  ");
  $q->execute();
  $col = $q->fetch(PDO::FETCH_ASSOC);
  if ($col && $col['IS_NULLABLE'] === 'NO' && $col['COLUMN_DEFAULT'] === null) {
    // Sesuaikan panjang jika berbeda (default aman: VARCHAR(32))
    $pdo->exec("ALTER TABLE akses_login MODIFY COLUMN otp VARCHAR(32) NOT NULL DEFAULT ''");
    _es_echo('akses_login.otp default set');
  }
} catch (Throwable $e) {
  _es_echo('otp check fail: '.$e->getMessage());
}

/** Pastikan username unik di admin (aman jika sudah ada) */
try {
  $exists = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='admin' AND INDEX_NAME='uk_admin_username'
  ");
  $exists->execute();
  if ((int)$exists->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE admin ADD UNIQUE KEY uk_admin_username (username)");
  }
} catch (Throwable $e) {
  _es_echo('uk_admin_username skip: '.$e->getMessage());
}

/** Helper: cek jumlah trigger kami */
function es_admin_trigger_count(PDO $pdo): int {
  try {
    $stmt = $pdo->query("
      SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS
      WHERE TRIGGER_SCHEMA = DATABASE()
        AND TRIGGER_NAME IN ('akses_login_ai_admin','akses_login_au_admin','akses_login_ad_admin')
    ");
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return 0; // kalau akses ke INFORMATION_SCHEMA dibatasi
  }
}

/** Buat trigger sinkron */
function es_admin_install_triggers(PDO $pdo): void {
  $pdo->exec("DROP TRIGGER IF EXISTS akses_login_ai_admin");
  $pdo->exec("DROP TRIGGER IF EXISTS akses_login_au_admin");
  $pdo->exec("DROP TRIGGER IF EXISTS akses_login_ad_admin");

  // AFTER INSERT → upsert ke admin (match by username)
  $pdo->exec("
    CREATE TRIGGER akses_login_ai_admin
    AFTER INSERT ON akses_login
    FOR EACH ROW
    BEGIN
      IF LOWER(NEW.role) = 'admin' THEN
        INSERT INTO admin (nama, username, password)
        VALUES (NEW.nama, NEW.username, COALESCE(NEW.password,''))
        ON DUPLICATE KEY UPDATE
          nama = VALUES(nama),
          password = VALUES(password);
      END IF;
    END
  ");

  // AFTER UPDATE → handle perubahan role/username/password
  $pdo->exec("
    CREATE TRIGGER akses_login_au_admin
    AFTER UPDATE ON akses_login
    FOR EACH ROW
    BEGIN
      IF LOWER(OLD.role) = 'admin' AND LOWER(NEW.role) <> 'admin' THEN
        DELETE FROM admin WHERE LOWER(username) = LOWER(OLD.username);
      ELSEIF LOWER(NEW.role) = 'admin' THEN
        IF OLD.username <> NEW.username THEN
          DELETE FROM admin WHERE LOWER(username) = LOWER(OLD.username);
        END IF;
        INSERT INTO admin (nama, username, password)
        VALUES (NEW.nama, NEW.username, COALESCE(NEW.password,''))
        ON DUPLICATE KEY UPDATE
          nama = VALUES(nama),
          password = CASE
                       WHEN COALESCE(NEW.password,'') <> '' THEN VALUES(password)
                       ELSE admin.password
                     END;
      END IF;
    END
  ");

  // AFTER DELETE → bersihkan admin
  $pdo->exec("
    CREATE TRIGGER akses_login_ad_admin
    AFTER DELETE ON akses_login
    FOR EACH ROW
    BEGIN
      IF LOWER(OLD.role) = 'admin' THEN
        DELETE FROM admin WHERE LOWER(username) = LOWER(OLD.username);
      END IF;
    END
  ");
}

/** Backfill/seed sinkron saat pertama */
function es_admin_backfill(PDO $pdo): void {
  // Tambah/Update semua admin dari akses_login → admin
  $pdo->exec("
    INSERT INTO admin (nama, username, password)
    SELECT al.nama, al.username, COALESCE(al.password,'')
    FROM akses_login al
    WHERE LOWER(al.role) = 'admin'
    ON DUPLICATE KEY UPDATE
      admin.nama = VALUES(nama),
      admin.password = VALUES(password)
  ");

  // Hapus yang di admin tapi sudah bukan admin lagi di akses_login
  $pdo->exec("
    DELETE a FROM admin a
    LEFT JOIN akses_login al
      ON LOWER(al.username) = LOWER(a.username) AND LOWER(al.role) = 'admin'
    WHERE al.id_akses IS NULL
  ");
}

/** Jalankan pemasangan (best effort—silent jika host membatasi TRIGGER) */
$needSeed = false;
try {
  es_admin_install_triggers($pdo);
  $needSeed = true;
} catch (Throwable $e) {
  _es_echo('trigger install blocked: '.$e->getMessage());
  // Kalau gagal buat trigger, tetap lakukan backfill agar halaman admin terisi
  $needSeed = true;
}

// Backfill saat: (1) habis pasang trigger, (2) diminta seed=1, (3) trigger belum lengkap
if (
  $needSeed ||
  (isset($_GET['seed']) && $_GET['seed'] === '1') ||
  es_admin_trigger_count($pdo) < 3
) {
  try {
    $pdo->beginTransaction();
    es_admin_backfill($pdo);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    _es_echo('backfill error: '.$e->getMessage());
  } 
}
