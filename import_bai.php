<?php
/**
 * import_bai.php — BAI ONLY
 *
 * Import Telegram export JSON (Desktop) ke tabel `bai`.
 * - Semua pesan di file JSON akan diproses (TIDAK ada filter rentang tanggal).
 * - File PDF BAI disalin ke folder bot/file/.
 */
declare(strict_types=1);
ini_set('memory_limit','-1');
set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

if (PHP_SAPI !== 'cli') {
  http_response_code(400);
  echo "Run this script via CLI.\n";
  exit(1);
}

// koneksi ada di folder yang sama dengan script
require_once __DIR__ . '/koneksi.php'; // pastikan $pdo tersedia (PDO)

/* ================== KONFIG & UTIL DASAR ================== */

const WIB_TZ = 'Asia/Jakarta';

// (Konstanta rentang waktu dibiarkan untuk kompatibilitas,
//  tapi filter tanggal dimatikan di fungsi in_wib_range()).
const DATE_START = '1900-01-01 00:00:00';
const DATE_END   = '5000-12-31 23:59:59';

// Folder tujuan simpan PDF BAI
// Sekarang: e-siklo/bot/file/...
function file_root(): string {
  return __DIR__ . '/bot/file';
}

// --- cari file JSON (fleksibel, prioritas argumen dari CLI) ---
$arg = $argv[1] ?? '';

$norm = function($p){
  if (!is_string($p) || $p === '') return '';
  $p = preg_replace('/^["\']|["\']$/', '', $p);
  return $p;
};

$candidates = [];

// 1) argumen dari CLI
if ($arg !== '') {
  $p = $norm($arg);
  if (is_dir($p)) {
    $dir = rtrim($p, DIRECTORY_SEPARATOR);
    $candidates[] = $dir . DIRECTORY_SEPARATOR . 'result.json';
    $candidates[] = $dir . DIRECTORY_SEPARATOR . 'messages.json';
  } else {
    $candidates[] = $p;
    $rp = @realpath($p);
    if ($rp) $candidates[] = $rp;
  }
}

// 2) fallback default (jalan manual)
$baseImport = __DIR__ . '/var/import';
$candidates[] = $baseImport . '/bai/result.json';
$candidates[] = $baseImport . '/bai/messages.json';
$candidates[] = $baseImport . '/result.json';
$candidates[] = $baseImport . '/messages.json';
$candidates[] = getcwd() . DIRECTORY_SEPARATOR . 'result.json';
$candidates[] = getcwd() . DIRECTORY_SEPARATOR . 'messages.json';

$jsonPath = '';
foreach ($candidates as $c) {
  if (is_string($c) && $c !== '' && @is_file($c)) {
    $jsonPath = $c;
    break;
  }
}

if ($jsonPath === '') {
  fwrite(STDERR,
    "File JSON tidak ditemukan.\n".
    "Jalankan:\n".
    "  php import_bai.php /path/ke/result.json\n"
  );
  exit(1);
}

$exportRoot = dirname($jsonPath);

/* ================== UTIL WAKTU & IDENTITAS ================== */

function wib_dt_from_msg(array $m): DateTime {
  try {
    if (!empty($m['date_unixtime']) && ctype_digit((string)$m['date_unixtime'])) {
      $dt = new DateTime('@' . $m['date_unixtime']); // UTC epoch
      $dt->setTimezone(new DateTimeZone(WIB_TZ));
      return $dt;
    }
    if (!empty($m['date'])) {
      $dt = new DateTime((string)$m['date'], new DateTimeZone('UTC'));
      $dt->setTimezone(new DateTimeZone(WIB_TZ));
      return $dt;
    }
  } catch (Throwable $e) {
    // biarkan fallback di bawah
  }
  return new DateTime('now', new DateTimeZone(WIB_TZ));
}

/**
 * Filter tanggal DINONAKTIFKAN.
 * Semua pesan dalam file JSON akan dianggap dalam rentang dan diproses.
 */
function in_wib_range(DateTime $dt): bool {
  return true;
}

function from_display_name(array $m): string {
  $name = trim((string)($m['from'] ?? ''));
  if ($name === '' || strtolower($name) === 'null') return '-';
  // buang " (@username)" jika ada
  $name = preg_replace('/\s*\(@[A-Za-z0-9_]+\)\s*$/', '', $name);
  return preg_replace('/\s+/', ' ', $name);
}

function user_id_digits(?string $fromId): int {
  if (!$fromId) return 0;
  return preg_match('/(\d{5,})/', $fromId, $m) ? (int)$m[1] : 0;
}

/* ================== UTIL BAI / FILE PDF ================== */

/**
 * Ambil kode BAI dari nama file, misal:
 *  DGPS250614203559586811707_AOi42506140835598619417a0_16210029_ba.pdf
 *  -> AOi42506140835598619417a0
 */
function extract_kode_from_filename(string $fname): ?string {
  $b = basename($fname);

  $PREF = '(?:AO|PDA|SC)';          // jenis kode yang dikenal
  $BODY = '[A-Za-z]?[0-9A-Za-z]+';  // opsional 1 huruf lalu alfanumerik

  // Pola paling spesifik: ..._<KODE>_169..._(ba|bai).pdf
  if (preg_match('/_(' . $PREF . $BODY . ')_(?:\d+_)?(?:ba|bai)\.pdf$/i', $b, $m)) {
    return $m[1];
  }

  // Umum: <KODE> diapit underscore
  if (preg_match('/_(' . $PREF . $BODY . ')_/i', $b, $m)) {
    return $m[1];
  }

  // Fallback: <KODE> diikuti underscore
  if (preg_match('/(' . $PREF . $BODY . ')(?=_)/i', $b, $m)) {
    return $m[1];
  }

  // Fallback terakhir: <KODE> di akhir sebelum .pdf
  if (preg_match('/(' . $PREF . $BODY . ')\.pdf$/i', $b, $m)) {
    return $m[1];
  }

  return null;
}

function ensure_dir(string $dir): bool {
  if (@is_dir($dir)) return true;
  return @mkdir($dir, 0775, true);
}

/**
 * Resolve path file dari export (photo/file) → absolute path yang ada
 */
function resolve_export_path(string $exportRoot, string $rel): ?string {
  $cands = [];
  $cands[] = $exportRoot . '/' . ltrim($rel, '/');
  $base = basename($rel);
  $cands[] = $exportRoot . '/photos/' . $base;
  $cands[] = $exportRoot . '/files/'  . $base;

  foreach ($cands as $p) {
    if (@is_file($p)) return $p;
  }
  return null;
}

/**
 * Ambil dokumen PDF dari message (kalau ada)
 */
function get_message_pdf(array $m, string $exportRoot): ?string {
  $f = $m['file'] ?? '';
  if (!is_string($f) || $f === '') return null;

  if (!preg_match('/\.pdf$/i', $f)) return null;

  return resolve_export_path($exportRoot, $f);
}

/* ================== CEK SCHEMA TABEL BAI ================== */

function table_has_column(PDO $pdo, string $table, string $col): bool {
  $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = :t
            AND COLUMN_NAME  = :c";
  $st = $pdo->prepare($sql);
  $st->execute([':t' => $table, ':c' => $col]);
  return (int)$st->fetchColumn() > 0;
}

$baiHasUser = table_has_column($pdo, 'bai', 'user_id');

$baiCols = $baiHasUser
  ? ['kode_bai','nama_bai','uploader','user_id','date_received']
  : ['kode_bai','nama_bai','uploader','date_received'];

$colList   = implode(', ', $baiCols);
$paramList = ':' . implode(',:', $baiCols);

$sqlInsBai = "INSERT INTO bai ({$colList}) VALUES ({$paramList})";
$stInsBai  = $pdo->prepare($sqlInsBai);

/* ================== BACA JSON ================== */

try {
  $raw  = file_get_contents($jsonPath);
  $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  fwrite(STDERR, "Gagal baca JSON: ".$e->getMessage()."\n");
  exit(1);
}

$messages = $json['messages'] ?? [];
if (!is_array($messages)) {
  fwrite(STDERR, "Format JSON tidak sesuai: field 'messages' tidak valid.\n");
  exit(1);
}
$total = count($messages);

/* ================== PROSES: IMPORT BAI SAJA ================== */

$destRoot = rtrim(file_root(), '/\\');
if (!ensure_dir($destRoot)) {
  fwrite(STDERR, "Gagal membuat folder tujuan BAI: {$destRoot}\n");
  exit(1);
}

$pdo->beginTransaction();

$imported = 0;
$skipped  = 0;

foreach ($messages as $m) {
  if (($m['type'] ?? '') !== 'message') {
    $skipped++;
    continue;
  }

  $dtWib = wib_dt_from_msg($m);
  if (!in_wib_range($dtWib)) {
    // untuk saat ini selalu true, jadi tidak ada yang di-skip karena tanggal
  }

  $pdfPath = get_message_pdf($m, $exportRoot);
  if (!$pdfPath) {
    $skipped++;
    continue;
  }

  $fromName = from_display_name($m);
  $uid      = user_id_digits((string)($m['from_id'] ?? ''));

  // Pastikan nama tujuan unik di bot/file/
  $baseName = basename($pdfPath);
  $destPath = $destRoot . DIRECTORY_SEPARATOR . $baseName;
  $i        = 1;
  while (is_file($destPath)) {
    $destPath = $destRoot . DIRECTORY_SEPARATOR .
                pathinfo($baseName, PATHINFO_FILENAME) . '_' . $i . '.' .
                pathinfo($baseName, PATHINFO_EXTENSION);
    $i++;
  }

  if (!@copy($pdfPath, $destPath)) {
    fwrite(STDERR, "Gagal menyalin BAI: {$pdfPath} -> {$destPath}\n");
    $skipped++;
    continue;
  }

  $kode = extract_kode_from_filename($destPath) ?? '-';

  $bind = [
    ':kode_bai'      => $kode,
    ':nama_bai'      => basename($destPath),
    ':uploader'      => $fromName,
    ':date_received' => $dtWib->format('Y-m-d H:i:s'),
  ];
  if ($baiHasUser) {
    $bind[':user_id'] = $uid;
  }

  // Normalisasi urutan param sesuai $baiCols
  $normBind = [];
  foreach ($baiCols as $c) {
    $param           = ':' . $c;
    $normBind[$param]= $bind[$param] ?? null;
  }

  try {
    $stInsBai->execute($normBind);
    $imported++;
  } catch (Throwable $e) {
    fwrite(
      STDERR,
      "Gagal insert BAI ke tabel untuk file ".basename($destPath).
      ": ".$e->getMessage()."\n"
    );
    $skipped++;
  }
}

$pdo->commit();

/* ================== SUMMARY ================== */

echo "Selesai import BAI.\n";
echo "File JSON       : {$jsonPath}\n";
echo "Total messages  : {$total}\n";
echo "BAI ter-import  : {$imported}\n";
echo "Pesan di-skip   : {$skipped}\n";
echo "Rentang WIB     : TANPA BATAS (semua pesan dalam file JSON diproses)\n";
echo "Folder BAI      : ".$destRoot."\n";
