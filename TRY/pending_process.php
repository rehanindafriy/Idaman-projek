<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php'; // $pdo (PDO)

function back_with(string $type, string $msg){
  if (function_exists('flash')) flash($type, $msg);
  header("Location: pending.php");
  exit;
}

// Hanya POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

// CSRF
$csrf = $_POST['csrf'] ?? '';
$csrfOK = false;
if (function_exists('csrf_check')) {
  $csrfOK = (bool)csrf_check($csrf);
} elseif (function_exists('csrf_validate')) {
  $csrfOK = (bool)csrf_validate($csrf);
} elseif (function_exists('csrf_verify')) {
  $csrfOK = (bool)csrf_verify($csrf);
} elseif (isset($_SESSION['csrf'])) {
  $csrfOK = hash_equals((string)$_SESSION['csrf'], (string)$csrf);
}
if (!$csrfOK) {
  back_with('err', 'Sesi berakhir atau token tidak valid.');
}

/* =========================
   Ambil input kode user
   ========================= */
$codeIn = preg_replace('/\D+/', '', (string)($_POST['code'] ?? ''));
if ($codeIn === '') {
  back_with('err', 'Kode verifikasi wajib diisi.');
}

/* ================================================================
   A) FALLBACK "KODE SAJA" — saat login di device lain (tanpa sesi)
   ================================================================ */
$uid = (int)($_SESSION['pending_uid'] ?? 0);

if ($uid <= 0) {
  try {
    // Cari akun dari KODE (wajib unik & masih active)
    $st = $pdo->prepare("SELECT id_akses, username, status, verifikasi
                           FROM akses_login
                          WHERE verifikasi = ?
                          LIMIT 2");
    $st->execute([$codeIn]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    back_with('err', 'Gagal memuat data: '.$e->getMessage());
  }

  if (!$rows) back_with('err', 'Kode verifikasi tidak ditemukan atau sudah kadaluarsa.');
  if (count($rows) > 1) back_with('err', 'Kode verifikasi bentrok di lebih dari satu akun. Hubungi admin.');

  $row = $rows[0];
  $status = strtolower((string)$row['status']);
  if ($status !== 'active') {
    back_with('err', 'Akun belum ACTIVE. Hubungi admin untuk aktivasi.');
  }

  // Tandai berhasil: set done='yes' & kosongkan kode (sekali pakai) — lindungi dengan kondisi kode
  try {
    $up = $pdo->prepare("UPDATE akses_login
                            SET done='yes', verifikasi = NULL
                          WHERE id_akses = ? AND verifikasi = ?");
    $up->execute([(int)$row['id_akses'], $codeIn]);
    if ($up->rowCount() !== 1) {
      back_with('err', 'Kode verifikasi sudah digunakan atau tidak valid.');
    }
  } catch (Throwable $e) {
    back_with('err', 'Gagal memproses verifikasi: '.$e->getMessage());
  }

  // Ikat minimal sesi supaya halaman berikutnya konsisten
  $_SESSION['pending_uid']   = (int)$row['id_akses'];
  $_SESSION['pending_uname'] = (string)$row['username'];

  if (function_exists('flash')) flash('ok', 'Verifikasi berhasil. Akun siap digunakan.');
  header('Location: index.php');
  exit;
}

/* ================================================================
   B) ALUR LAMA — verifikasi berbasis sesi (seperti kode Anda)
   ================================================================ */
try {
  $st = $pdo->prepare("SELECT status, verifikasi FROM akses_login WHERE id_akses=? LIMIT 1");
  $st->execute([$uid]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) back_with('err', 'Akun tidak ditemukan.');
  $status = strtolower((string)$row['status']);
  $codeDb = isset($row['verifikasi']) ? (string)$row['verifikasi'] : null;
} catch (Throwable $e) {
  back_with('err', 'Gagal memuat status: '.$e->getMessage());
}

// RULE 1: hanya status active yang boleh submit kode verifikasi / isi form
if ($status !== 'active') {
  back_with('err', 'Form dikunci karena status akun Anda "' . $status . '". Hubungi admin untuk aktivasi.');
}

// RULE 2: kode verifikasi harus sudah diterbitkan admin (kolom verifikasi TIDAK NULL/KOSONG)
if ($codeDb === null || $codeDb === '') {
  back_with('err', 'Kode verifikasi belum diterbitkan. Silakan hubungi admin.');
}

// Validasi panjang (opsional, tapi aman): gunakan panjang kode di DB
if (strlen($codeIn) !== strlen($codeDb)) {
  back_with('err', 'Kode verifikasi tidak valid.');
}

// Bandingkan aman
if (!hash_equals((string)$codeDb, (string)$codeIn)) {
  back_with('err', 'Kode verifikasi salah.');
}

// Sukses: tandai selesai + kosongkan kode (sekali pakai), lindungi dengan kondisi kode
try {
  $up = $pdo->prepare("UPDATE akses_login
                          SET done='yes', verifikasi = NULL
                        WHERE id_akses = ? AND verifikasi = ?");
  $up->execute([$uid, $codeDb]);
  if ($up->rowCount() !== 1) {
    // Jika 0, kemungkinan kode sudah berubah/terpakai (race)
    back_with('err', 'Kode verifikasi tidak lagi berlaku. Minta kode baru.');
  }
} catch (Throwable $e) {
  back_with('err', 'Gagal menandai verifikasi: '.$e->getMessage());
}

if (function_exists('flash')) flash('ok', 'Verifikasi berhasil. Akun siap digunakan.');
header('Location: index.php');
exit;
