<?php
// foto_ajax.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

function ok($data = []){ echo json_encode(['ok'=>true] + $data); exit; }
function err($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

$act  = $_GET['act'] ?? '';
$kode = trim($_REQUEST['kode'] ?? '');
$id   = (int)($_REQUEST['id_foto'] ?? 0);

// Kalau id_foto ada tapi kode kosong, resolve dari DB
if ($id > 0 && $kode === '') {
  try {
    $q = $pdo->prepare("SELECT kode_foto FROM foto WHERE id_foto=? LIMIT 1");
    $q->execute([$id]);
    $kode = (string)$q->fetchColumn();
  } catch (Throwable $e) {}
}
if ($kode === '') err('kode kosong');

// nama folder aman
$kodeSafe = preg_replace('/[^A-Za-z0-9_\-\.]/','_', $kode);

/* ---------- RESOLVE BASE DIR DENGAN BENAR ---------- */
/* Kita punya beberapa kandidat lokasi 'bot/foto'.
   Pilih yang BENAR-BENAR punya subfolder $kodeSafe. */
$BASE_URL      = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$FOTO_URL_BASE = $BASE_URL . '/bot/foto';

$candidates = [];

// Kandidat 1: relatif terhadap file ini (seperti halaman utama)
$c1 = realpath(__DIR__ . '/bot/foto');
if ($c1) $candidates[] = rtrim($c1, DIRECTORY_SEPARATOR);

// Kandidat 2: DOCUMENT_ROOT + URL publik
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
  $c2 = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR
      . trim(str_replace('/', DIRECTORY_SEPARATOR, $FOTO_URL_BASE), DIRECTORY_SEPARATOR);
  $c2 = realpath($c2) ?: $c2; // kalau belum ada realpath-nya tetap pakai string
  $candidates[] = rtrim($c2, DIRECTORY_SEPARATOR);
}

// Pilih kandidat yang memiliki subfolder $kodeSafe
$FOTO_FS_DIR = null;
foreach ($candidates as $cand) {
  if (is_dir($cand) && is_dir($cand . DIRECTORY_SEPARATOR . $kodeSafe)) {
    $FOTO_FS_DIR = $cand; break;
  }
}
// fallback: kandidat pertama yang ada
if (!$FOTO_FS_DIR) {
  foreach ($candidates as $cand) {
    if (is_dir($cand)) { $FOTO_FS_DIR = $cand; break; }
  }
}
if (!$FOTO_FS_DIR) err('Folder foto tidak ditemukan');

$FOTO_FS_DIR = rtrim($FOTO_FS_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$dir = $FOTO_FS_DIR . $kodeSafe . DIRECTORY_SEPARATOR;

/* ------------------- ACT: LIST ------------------- */
if ($act === 'list') {
  // Jangan bikin folder saat list. Jika belum ada, balas kosong.
  if (!is_dir($dir)) {
    ok(['kode'=>$kodeSafe,'files'=>[]]);
  }

  $allow = ['jpg','jpeg','png','webp','gif'];
  $files = [];
  foreach (@scandir($dir) ?: [] as $fn) {
    if ($fn === '.' || $fn === '..') continue;
    $full = $dir . $fn;
    if (!is_file($full)) continue;
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if (!in_array($ext, $allow, true)) continue;

    $files[] = [
      'name' => $fn,
      'url'  => $FOTO_URL_BASE . '/' . rawurlencode($kodeSafe) . '/' . rawurlencode($fn),
      'size' => @filesize($full) ?: 0,
    ];
  }
  usort($files, fn($a,$b) => strnatcasecmp($a['name'],$b['name']));
  ok(['kode'=>$kodeSafe,'files'=>$files]);
}

/* ------------------- ACT: UPLOAD ------------------- */
if ($act === 'upload') {
  if (!is_dir($dir)) { @mkdir($dir, 0777, true); }

  $okExt = ['jpg','jpeg','png','webp','gif'];
  $saved = 0; $skipped = 0; $MAX_FILES = 25;

  $names = $_FILES['photos']['name'] ?? [];
  $tmps  = $_FILES['photos']['tmp_name'] ?? [];
  $errs  = $_FILES['photos']['error'] ?? [];

  $total = min(count($names), $MAX_FILES);
  for ($i=0; $i<$total; $i++) {
    if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { $skipped++; continue; }
    $ext  = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
    if (!in_array($ext, $okExt, true)) { $skipped++; continue; }

    $base = preg_replace('/[^\w\-]+/','_', pathinfo($names[$i], PATHINFO_FILENAME)) ?: 'img';
    $dest = $dir . $base . '.' . $ext;
    $suf = 1;
    while (file_exists($dest)) { $dest = $dir . $base . "_{$suf}." . $ext; $suf++; }
    if (@move_uploaded_file($tmps[$i], $dest)) $saved++; else $skipped++;
  }
  ok(['saved'=>$saved,'skipped'=>$skipped]);
}

/* ------------------- ACT: DELETE ------------------- */
if ($act === 'delete') {
  $name = basename($_POST['name'] ?? '');
  if ($name === '') err('nama file kosong');
  $full = $dir . $name;
  if (!is_file($full)) err('file tidak ditemukan',404);
  if (@unlink($full)) ok(['deleted'=>$name]); else err('gagal hapus',500);
}

if ($act === 'delete_all') {
  if (is_dir($dir)) {
    foreach (glob($dir.'*') ?: [] as $f) { if (is_file($f)) @unlink($f); }
  }
  ok(['cleared'=>true]);
}

err('aksi tidak dikenal',404);
