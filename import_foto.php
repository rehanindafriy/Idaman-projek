<?php
/**
 * import_foto.php — FOTO SAJA
 *
 * Import Telegram export JSON (Desktop) untuk evidensi lapangan (foto) saja.
 * - Semua pesan di file JSON diproses (tanpa filter tanggal).
 * - Output foto: bot/foto/<NO_SC>/...
 * - 1 baris per NO_SC di tabel `foto`.
 */

declare(strict_types=1);
ini_set('memory_limit', '-1');
set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

if (PHP_SAPI !== 'cli') {
  http_response_code(400);
  echo "Run via CLI.\n";
  exit(1);
}

/* ================== KONFIG ================== */
const WIB_TZ     = 'Asia/Jakarta';
const DATE_START = '1900-01-01 00:00:00';
const DATE_END   = '5000-12-31 23:59:59';

// max jarak waktu foto dari anchor untuk fallback sekitar anchor (detik)
const ANCHOR_MAX_GAP_SEC = 6 * 3600;

/**
 * Root output: bot/foto/
 * Hasil akhir: bot/foto/<NO_SC>/
 */
function out_root(): string {
  return __DIR__ . '/bot/foto';
}

/* ================== UTIL FILE ================== */
function ensure_dir(string $dir): bool {
  return is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir);
}
function sanitize_folder(string $s): string {
  $s = trim($s);
  $s = str_replace(' ', '-', $s);
  return preg_replace('/[^A-Za-z0-9._-]+/', '', $s) ?: 'unknown';
}
function sanitize_filename(string $s): string {
  $s = trim($s);
  $s = str_replace(' ', '_', $s);
  return preg_replace('/[^A-Za-z0-9._-]+/', '', $s) ?: 'file';
}

/* ================== UTIL WAKTU ================== */
function wib_dt_from_msg(array $m): DateTime {
  try {
    if (!empty($m['date_unixtime']) && ctype_digit((string)$m['date_unixtime'])) {
      $dt = new DateTime('@' . $m['date_unixtime']);
      $dt->setTimezone(new DateTimeZone(WIB_TZ));
      return $dt;
    }
    if (!empty($m['date'])) {
      $dt = new DateTime((string)$m['date'], new DateTimeZone('UTC'));
      $dt->setTimezone(new DateTimeZone(WIB_TZ));
      return $dt;
    }
  } catch (Throwable $e) {}
  return new DateTime('now', new DateTimeZone(WIB_TZ));
}
function ts_from_msg(array $m): int {
  if (!empty($m['date_unixtime']) && ctype_digit((string)$m['date_unixtime'])) {
    return (int)$m['date_unixtime'];
  }
  return wib_dt_from_msg($m)->getTimestamp();
}

/**
 * Filter tanggal DINONAKTIFKAN → semua pesan valid.
 */
function in_wib_range(DateTime $dt): bool {
  return true;
}

/* ================== UTIL TEXT/JSON ================== */
function extract_text($m): string {
  $t = $m['text'] ?? '';
  if (is_string($t)) return $t;
  if (is_array($t)) {
    $buf = '';
    foreach ($t as $part) {
      if (is_string($part)) {
        $buf .= $part;
      } elseif (is_array($part) && isset($part['text'])) {
        $buf .= (string)$part['text'];
      }
    }
    return $buf;
  }
  return '';
}

function parse_no_sc_from_order(string $text): ?string {
  if (stripos($text, '/ORDER') !== 0) return null;
  return parse_no_sc_loose($text);
}

function parse_no_sc_loose(string $text): ?string {
  if (preg_match(
        '/(?:NO(?:MOR)?\s*[.\-_]?\s*SC)\s*[:\-]?\s*([A-Za-z0-9\-_]+)/i',
        $text,
        $m
      )) {
    return trim($m[1]);
  }
  return null;
}

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

/* ================== UTIL FOTO/ALBUM ================== */
function is_photo_msg(array $m): bool {
  if (!empty($m['photo']) && is_string($m['photo'])) return true;
  if (($m['media_type'] ?? '') === 'photo' && !empty($m['file']) && is_string($m['file'])) return true;
  if (!empty($m['photos']) && is_array($m['photos'])) return true;
  return false;
}
function get_photo_path_from_msg(array $m, string $exportRoot): ?string {
  if (!empty($m['photo']) && is_string($m['photo'])) {
    $p = resolve_export_path($exportRoot, $m['photo']);
    if ($p) return $p;
  }
  if (($m['media_type'] ?? '') === 'photo' && !empty($m['file']) && is_string($m['file'])) {
    $p = resolve_export_path($exportRoot, $m['file']);
    if ($p) return $p;
  }
  if (!empty($m['photos']) && is_array($m['photos'])) {
    foreach ($m['photos'] as $rel) {
      if (!is_string($rel)) continue;
      $p = resolve_export_path($exportRoot, $rel);
      if ($p) return $p;
    }
  }
  return null;
}

function build_index_map(array $messages): array {
  $map = [];
  foreach ($messages as $i => $m) {
    if (isset($m['id'])) {
      $map[(string)$m['id']] = $i;
    }
  }
  return $map;
}

function collect_album_by_grouped_id(array $messages, int $idx): array {
  $m = $messages[$idx];
  if (!isset($m['grouped_id'])) return [];
  $gid = (string)$m['grouped_id'];
  $out = [];

  for ($i = $idx; $i >= 0; $i--) {
    if (($messages[$i]['grouped_id'] ?? null) !== $gid) break;
    if (is_photo_msg($messages[$i])) $out[$i] = true;
  }
  $n = count($messages);
  for ($i = $idx + 1; $i < $n; $i++) {
    if (($messages[$i]['grouped_id'] ?? null) !== $gid) break;
    if (is_photo_msg($messages[$i])) $out[$i] = true;
  }
  ksort($out);
  return array_keys($out);
}

function collect_album_by_contiguity(array $messages, int $idx): array {
  $base   = $messages[$idx];
  $fromId = (string)($base['from_id'] ?? '');
  $ts0    = ts_from_msg($base);
  $out    = [];

  for ($i = $idx; $i >= 0; $i--) {
    $m = $messages[$i];
    if (!is_photo_msg($m)) break;
    if ((string)($m['from_id'] ?? '') !== $fromId) break;
    if (abs(ts_from_msg($m) - $ts0) > 2) break;
    $out[$i] = true;
  }
  $n = count($messages);
  for ($i = $idx + 1; $i < $n; $i++) {
    $m = $messages[$i];
    if (!is_photo_msg($m)) break;
    if ((string)($m['from_id'] ?? '') !== $fromId) break;
    if (abs(ts_from_msg($m) - $ts0) > 2) break;
    $out[$i] = true;
  }
  ksort($out);
  return array_keys($out);
}

function collect_album_around_anchor_text(array $messages, int $anchorIdx, int $maxGapSec = ANCHOR_MAX_GAP_SEC): array {
  if (!isset($messages[$anchorIdx])) return [];
  $anchor       = $messages[$anchorIdx];
  $tsAnchor     = ts_from_msg($anchor);
  $fromIdAnchor = (string)($anchor['from_id'] ?? '');
  $out          = [];
  $n            = count($messages);

  for ($i = $anchorIdx - 1; $i >= 0; $i--) {
    $m  = $messages[$i];
    $ts = ts_from_msg($m);
    if ($tsAnchor - $ts > $maxGapSec) break;

    if (is_photo_msg($m)) {
      if ($fromIdAnchor === '' || (string)($m['from_id'] ?? '') === $fromIdAnchor) {
        $out[$i] = true;
      }
      continue;
    }

    if ($fromIdAnchor !== '' && (string)($m['from_id'] ?? '') === $fromIdAnchor) {
      break;
    }
  }

  for ($i = $anchorIdx + 1; $i < $n; $i++) {
    $m  = $messages[$i];
    $ts = ts_from_msg($m);
    if ($ts - $tsAnchor > $maxGapSec) break;

    if (is_photo_msg($m)) {
      if ($fromIdAnchor === '' || (string)($m['from_id'] ?? '') === $fromIdAnchor) {
        $out[$i] = true;
      }
      continue;
    }

    if ($fromIdAnchor !== '' && (string)($m['from_id'] ?? '') === $fromIdAnchor) {
      break;
    }
  }

  if (!$out) return [];
  ksort($out);
  return array_keys($out);
}

/* ================== BACA JSON ================== */

$arg = $argv[1] ?? '';

$normJson = function ($p) {
  if (!is_string($p) || $p === '') return '';
  $p = preg_replace('/^["\']|["\']$/', '', $p);
  return $p;
};

$candidates = [];

if ($arg !== '') {
  $p = $normJson($arg);
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

$baseImport = __DIR__ . '/var/import';
$candidates[] = $baseImport . '/foto/result.json';
$candidates[] = $baseImport . '/foto/messages.json';
$candidates[] = $baseImport . '/result.json';
$candidates[] = $baseImport . '/messages.json';
$candidates[] = getcwd() . DIRECTORY_SEPARATOR . 'result.json';
$candidates[] = getcwd() . DIRECTORY_SEPARATOR . 'messages.json';

$jsonPath = '';
foreach ($candidates as $c) {
  if ($c !== '' && @is_file($c)) {
    $jsonPath = $c;
    break;
  }
}

if ($jsonPath === '') {
  fwrite(STDERR, "File JSON tidak ditemukan.\n".
                 "Jalankan: php import_foto.php /path/ke/result.json\n");
  exit(1);
}

$exportRoot = dirname($jsonPath);

try {
  $raw  = file_get_contents($jsonPath);
  $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  fwrite(STDERR, "Gagal baca JSON: " . $e->getMessage() . "\n");
  exit(1);
}
$messages = $json['messages'] ?? [];

if (!is_array($messages)) {
  fwrite(STDERR, "Format messages tidak valid.\n");
  exit(1);
}
$idxMap = build_index_map($messages);

/* ================== KONEKSI DB ================== */

require_once __DIR__ . '/koneksi.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  fwrite(STDERR, "[FATAL] Tidak menemukan \$pdo dari koneksi.php\n");
  exit(1);
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stFotoCheck = $pdo->prepare("SELECT 1 FROM foto WHERE kode_foto = ? LIMIT 1");
$stFotoIns   = $pdo->prepare("INSERT INTO foto (kode_foto, uploader, date_received) VALUES (?, ?, ?)");

/* ================== CARI ANCHOR /ORDER / NO SC ================== */
$anchors = [];
for ($i = 0, $n = count($messages); $i < $n; $i++) {
  $m = $messages[$i];
  if (($m['type'] ?? '') !== 'message') continue;

  $dt = wib_dt_from_msg($m);
  if (!in_wib_range($dt)) {
    // filter tanggal dimatikan → tidak ada yang di-skip
  }

  $txt = extract_text($m);
  if ($txt === '') continue;

  $no_sc = null;
  if (stripos($txt, '/ORDER') === 0) {
    $no_sc = parse_no_sc_from_order($txt);
  }

  if ($no_sc === null) {
    $no_sc = parse_no_sc_loose($txt);
  }

  if ($no_sc) {
    $anchors[] = [
      'i'        => $i,
      'no_sc'    => $no_sc,
      'ts'       => $dt->getTimestamp(),
      'reply_to' => isset($m['reply_to_message_id']) ? (string)$m['reply_to_message_id'] : null
    ];
  }
}
if (!$anchors) {
  echo "Tidak ada /ORDER atau NO SC pada file JSON.\n";
  exit(0);
}

/* ================== SALIN FOTO per NO_SC ================== */
$totalGroups    = 0;
$totalFiles     = 0;
$totalFotoRows  = 0;
$seenSrc        = [];

for ($k = 0; $k < count($anchors); $k++) {
  $curr  = $anchors[$k];
  $currI = $curr['i'];

  $albumIdx = [];
  if (is_photo_msg($messages[$currI])) {
    $albumIdx = collect_album_by_grouped_id($messages, $currI);
    if (!$albumIdx) $albumIdx = collect_album_by_contiguity($messages, $currI);
  }

  if (!$albumIdx && !empty($curr['reply_to'])) {
    $refId = $curr['reply_to'];
    if (isset($idxMap[$refId]) && is_photo_msg($messages[$idxMap[$refId]])) {
      $refIdx   = $idxMap[$refId];
      $albumIdx = collect_album_by_grouped_id($messages, $refIdx);
      if (!$albumIdx) $albumIdx = collect_album_by_contiguity($messages, $refIdx);
    }
  }

  if (!$albumIdx) {
    $albumIdx = collect_album_around_anchor_text($messages, $currI);
  }

  if (!$albumIdx) continue;

  usort($albumIdx, function ($a, $b) use ($messages) {
    $ta = ts_from_msg($messages[$a]);
    $tb = ts_from_msg($messages[$b]);
    if ($ta === $tb) {
      $na = basename((string)($messages[$a]['photo'] ?? $messages[$a]['file'] ?? ''));
      $nb = basename((string)($messages[$b]['photo'] ?? $messages[$b]['file'] ?? ''));
      return strcmp($na, $nb);
    }
    return $ta <=> $tb;
  });

  $no_sc   = $curr['no_sc'];
  $destDir = rtrim(out_root(), '/\\') . DIRECTORY_SEPARATOR . sanitize_folder($no_sc);
  if (!ensure_dir($destDir)) {
    fwrite(STDERR, "Gagal buat folder: $destDir\n");
    continue;
  }

  $seq        = 1;
  $copiedHere = 0;

  foreach ($albumIdx as $idx) {
    $pp = get_photo_path_from_msg($messages[$idx], $exportRoot);
    if (!$pp) continue;

    $real = @realpath($pp) ?: $pp;
    if (isset($seenSrc[$real])) continue;
    $seenSrc[$real] = true;

    $ext  = strtolower(pathinfo($pp, PATHINFO_EXTENSION)) ?: 'jpg';
    $base = sanitize_filename(pathinfo($pp, PATHINFO_FILENAME));

    $dest = $destDir . DIRECTORY_SEPARATOR . sprintf('%03d_%s.%s', $seq, $base, $ext);
    while (file_exists($dest)) {
      $seq++;
      $dest = $destDir . DIRECTORY_SEPARATOR . sprintf('%03d_%s.%s', $seq, $base, $ext);
    }

    if (@copy($pp, $dest)) {
      $totalFiles++;
      $seq++;
      $copiedHere++;
    } else {
      fwrite(STDERR, "Gagal salin: $pp -> $dest\n");
    }
  }

  if ($copiedHere > 0) {
    $totalGroups++;

    try {
      $kodeFoto = $no_sc;
      $uploader = (string)($messages[$currI]['from'] ?? '');
      $dtMsg    = wib_dt_from_msg($messages[$currI]);
      $dateRecv = $dtMsg->format('Y-m-d H:i:s');

      $stFotoCheck->execute([$kodeFoto]);
      $exists = (bool)$stFotoCheck->fetchColumn();

      if (!$exists) {
        $stFotoIns->execute([$kodeFoto, $uploader, $dateRecv]);
        $totalFotoRows++;
      }
    } catch (Throwable $e) {
      fwrite(STDERR, "Gagal insert foto untuk NO_SC {$no_sc}: " . $e->getMessage() . "\n");
    }
  }
}

/* ================== SUMMARY ================== */
echo "Selesai import FOTO.\n";
echo "Anchor ORDER/NO SC diproses : {$totalGroups}\n";
echo "Total foto tersalin         : {$totalFiles}\n";
echo "Baris baru di tabel foto    : {$totalFotoRows}\n";
echo "Output dir                  : " . out_root() . "\n";
echo "Rentang WIB                 : TANPA BATAS (semua pesan dalam file JSON diproses)\n";
