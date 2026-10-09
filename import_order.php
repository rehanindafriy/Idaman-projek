<?php
/**
 * import_order.php — ORDERS ONLY
 *
 * Import Telegram export JSON (Desktop) ke DB (tabel `orders` saja)
 * - Semua pesan di file JSON diproses (TANPA filter rentang tanggal).
 */

declare(strict_types=1);

ini_set('memory_limit','-1');
set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

if (PHP_SAPI !== 'cli') {
  http_response_code(400);
  echo "Run via CLI.\n";
  exit(1);
}

require_once __DIR__ . '/koneksi.php';   // pastikan $pdo (PDO) tersedia

// ====== KONFIG & UTIL DASAR ======
const WIB_TZ     = 'Asia/Jakarta';
// Konstanta dibiarkan untuk kompatibilitas, tapi filter tanggal dimatikan.
const DATE_START = '1900-01-01 00:00:00';
const DATE_END   = '5000-12-31 23:59:59';

// --- cari file JSON (fleksibel, prioritas argumen dari import_data.php) ---
$arg = $argv[1] ?? '';

$norm = function($p){
  if (!is_string($p) || $p === '') return '';
  // buang kutip kalau pakai di shell
  $p = preg_replace('/^["\']|["\']$/', '', $p);
  return $p;
};

$candidates = [];

// 1) Kalau ada argumen dari CLI/import_data.php
if ($arg !== '') {
  $p = $norm($arg);

  // Kalau argumen adalah folder export → cari result.json / messages.json
  if (is_dir($p)) {
    $dir = rtrim($p, DIRECTORY_SEPARATOR);
    $candidates[] = $dir . DIRECTORY_SEPARATOR . 'result.json';
    $candidates[] = $dir . DIRECTORY_SEPARATOR . 'messages.json';
  } else {
    // kalau file langsung
    $candidates[] = $p;
    $rp = @realpath($p);
    if ($rp) $candidates[] = $rp;
  }
}

// 2) Fallback default kalau jalan manual tanpa argumen
$baseImport = __DIR__ . '/var/import';

// prioritas folder orders/
$candidates[] = $baseImport . '/orders/result.json';
$candidates[] = $baseImport . '/orders/messages.json';

// generic di var/import/
$candidates[] = $baseImport . '/result.json';
$candidates[] = $baseImport . '/messages.json';

// terakhir: di current working dir
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
    "Jalankan dengan:\n".
    "  php import_order.php /path/ke/result.json\n"
  );
  exit(1);
}

$exportRoot = dirname($jsonPath);

// ====== Waktu & identitas pengirim dari Telegram export ======
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
  } catch (Throwable $e) {}
  return new DateTime('now', new DateTimeZone(WIB_TZ));
}

/**
 * Filter tanggal DINONAKTIFKAN: semua pesan dianggap valid.
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

// ====== Parser ORDER ======
// ... (bagian parser sama persis, tidak diubah) ...

function norm_key(string $s): string {
  $s = trim($s);
  $s = preg_replace('/^[\/#@]+/u', '', $s);
  $s = str_replace(['-', '_'], ' ', $s);
  $s = preg_replace('/\s+/u', ' ', $s);
  $s = mb_strtoupper($s, 'UTF-8');
  $s = preg_replace('/\bNOMOR\b/u', 'NO', $s);
  $s = preg_replace('/\bNO\.\b/u', 'NO', $s);
  return $s;
}

function order_key_map(): array {
  return [
    'ORDER' => 'order_type', 'NO SC' => 'no_sc', 'NO INTERNET' => 'no_internet', 'NO VOIP' => 'no_voip',
    'NCLI' => 'ncli', 'NAMA PELANGGAN' => 'nama_pelanggan', 'ALAMAT' => 'alamat', 'CP PELANGGAN' => 'cp_pelanggan',
    'STO' => 'sto', 'MITRA' => 'mitra', 'ODP WO' => 'odp_wo', 'ODP REAL' => 'odp_real', 'PORT' => 'port',
    'MARKING DC AWAL'=>'marking_dc_awal','MARKING DC AKHIR'=>'marking_dc_akhir',
    'AC OF SM 1B'=>'ac_of_sm_1b','AC OF SM 1-3SL'=>'ac_of_sm_1_3sl','CLAMP HOOK'=>'clamp_hook',
    'OTP FTTH 1'=>'otp_ftth_1','PREKSO INTRA 15 RS'=>'prekso_intra_15_rs','PREKSO INTRA 20 RS'=>'prekso_intra_20_rs',
    'RS IN SC 1'=>'rs_in_sc_1','S CLAMP SPRINTER'=>'s_clamp_sprinter','SOC ILS'=>'soc_ils','SOC SUM'=>'soc_sum',
    'QRCODE'=>'qrcode','SN ONT TYPE'=>'sn_ont_type','STB ID TYPE'=>'stb_id_type','TAG ODP'=>'tag_odp',
    'TAG PELANGGAN'=>'tag_pelanggan','LABOR'=>'labor','VALINS ID'=>'valins_id','SLOT'=>'slot','PORTT'=>'portt',
    'IP OLT'=>'ip_olt','HASIL VALINS'=>'hasil_valins','ONU GENDONG'=>'onu_gendong',
    'NO INET LAMA'=>'no_inet_lama','NO VOIP LAMA'=>'no_voip_lama','SN ONT LAMA'=>'sn_ont_lama','STBID LAMA'=>'stbid_lama',
  ];
}
function order_key_index(): array {
  $base = order_key_map();

  $aliases = [
    '/ORDER'        => 'order_type',
    'ORDER TYPE'    => 'order_type',
    'JUDUL ORDER'   => 'order_type',
    'PSB'           => 'order_type',
    'DATA PSB'      => 'order_type',
    'TIPE ORDER'    => 'order_type',

    'NOMOR SC'      => 'no_sc',
    'SC'            => 'no_sc',

    'NO INTERNET BARU' => 'no_internet',
    'NO INET'          => 'no_internet',
    'NO INTERNET LAMA' => 'no_inet_lama',

    'NO HP'            => 'cp_pelanggan',
    'NO TELPON'        => 'cp_pelanggan',
    'NO TELP'          => 'cp_pelanggan',
    'NO TELP BARU'     => 'cp_pelanggan',
    'NO TELPON BARU'   => 'cp_pelanggan',

    'NO VOICE LAMA'    => 'no_voip_lama',

    'NAMA PELANGGAN'   => 'nama_pelanggan',
    'ALAMAT PELANGGAN' => 'alamat',
    'ALAMAT'           => 'alamat',

    'PANJANG DC'       => 'ac_of_sm_1_3sl',
    'DC'               => 'ac_of_sm_1_3sl',

    'STB'              => 'stb_id_type',
    'STB ID'           => 'stb_id_type',
    'STBID'            => 'stb_id_type',
    'STB-ID'           => 'stb_id_type',
    'STBID (TYPE)'     => 'stb_id_type',
    'ST BID (TYPE)'    => 'stb_id_type',
    'STBID LAMA'       => 'stbid_lama',
    'MAC STB'          => 'stb_id_type',
    'SN STB'           => 'stb_id_type',

    'SN ONT'           => 'sn_ont_type',
    'SN ONT (TYPE)'    => 'sn_ont_type',
    'SNONT (TYPE)'     => 'sn_ont_type',
    'SN UNCONFIG'      => 'sn_ont_type',

    'QR CODE DC'       => 'qrcode',
    'QR CODE'          => 'qrcode',

    'TAG ODP'          => 'tag_odp',
    'TAG PELANGGAN'    => 'tag_pelanggan',
    'TAG PEL'          => 'tag_pelanggan',

    'CREW'             => 'labor',
    'LABOR'            => 'labor',

    'VALINS ID'        => 'valins_id',
    'SLOT'             => 'slot',

    'HOSTNAME OLT'     => 'ip_olt',
    'HOSTNAME OLT MIGRASI' => 'ip_olt',

    'SCLAMP'           => 's_clamp_sprinter',
    'SCLAM'            => 's_clamp_sprinter',
    'S-CLAMP-SPRINTER' => 's_clamp_sprinter',
    'S CLAMP SPRINER'  => 's_clamp_sprinter',
  ];

  $idx = [];
  foreach ($base as $k=>$v)   $idx[norm_key($k)] = $v;
  foreach ($aliases as $k=>$v) $idx[norm_key($k)] = $v;
  return $idx;
}

function order_defaults(): array {
  return [
    'order_type','no_sc','no_internet','no_voip','ncli','nama_pelanggan','alamat','cp_pelanggan','sto','mitra','odp_wo','odp_real',
    'port','marking_dc_awal','marking_dc_akhir','ac_of_sm_1b','ac_of_sm_1_3sl','clamp_hook','otp_ftth_1',
    'prekso_intra_15_rs','prekso_intra_20_rs','rs_in_sc_1','s_clamp_sprinter','soc_ils','soc_sum','qrcode',
    'sn_ont_type','stb_id_type','tag_odp','tag_pelanggan','labor','valins_id','slot','portt','ip_olt','hasil_valins',
    'onu_gendong','no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama',
  ];
}

/**
 * Parse blok teks ORDER (hasil copy dari Telegram) menjadi array field orders.
 */
function parse_order_text(string $text): array {
  $text = trim($text);
  if ($text === '') return [];

  $lines = preg_split('/\r\n|\r|\n/', $text);
  $idx   = order_key_index();
  $data  = [];
  $portCount = 0;

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;

    if (strpos($line, ':') === false) {
      if (!isset($data['order_type'])) {
        if (preg_match('/\b(PSB|PDA|MO)\b/i', $line)) {
          $data['order_type'] = $line;
        }
      }
      continue;
    }

    $pos    = strpos($line, ':');
    $rawKey = trim(substr($line, 0, $pos));
    $value  = trim(substr($line, $pos + 1));
    if ($value === '') $value = '-';

    $norm = norm_key($rawKey);

    if ($norm === 'ORDER') {
      if ($value !== '-' && $value !== '') {
        $data['order_type'] = $value;
      }
      continue;
    }

    if ($norm === 'PORT') {
      $portCount++;
      if ($portCount === 2) {
        $norm = 'PORTT';
      }
    }

    if (isset($idx[$norm])) {
      $field = $idx[$norm];
      $data[$field] = $value;
    }
  }

  foreach (order_defaults() as $k) {
    if (!isset($data[$k]) || $data[$k] === '') {
      $data[$k] = '-';
    }
  }

  return $data;
}

// extract text/caption (Telegram export: string | array parts)
$extractText = function ($m): string {
  $t = $m['text'] ?? '';
  if (is_string($t)) return $t;
  if (is_array($t)) {
    $buf = '';
    foreach ($t as $part) {
      if (is_string($part)) $buf .= $part;
      elseif (is_array($part) && isset($part['text'])) $buf .= (string)$part['text'];
    }
    return $buf;
  }
  return '';
};

// ====== Helper schema-aware ======
function table_has_column(PDO $pdo, string $table, string $col): bool {
  $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c";
  $st = $pdo->prepare($sql);
  $st->execute([':t' => $table, ':c' => $col]);
  return (int)$st->fetchColumn() > 0;
}
function orders_existing_columns(PDO $pdo): array {
  $st = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'");
  $cols = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
  $set = [];
  foreach ($cols as $c) $set[(string)$c] = true;
  return $set;
}
function filter_existing_cols(PDO $pdo, array $candidates): array {
  $exists = orders_existing_columns($pdo);
  return array_values(array_filter($candidates, fn($c) => isset($exists[$c])));
}

// ====== BACA JSON ======
try {
  $raw  = file_get_contents($jsonPath);
  $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  fwrite(STDERR, "Gagal baca JSON: ".$e->getMessage()."\n");
  exit(1);
}
$messages = $json['messages'] ?? [];
$total    = is_array($messages) ? count($messages) : 0;

// ==== Siapkan kolom untuk INSERT ke `orders` (schema-aware) ====
$core      = order_defaults();
$baseExtra = ['sender_username','user_id','created_at'];

if (table_has_column($pdo, 'orders', 'bai_id'))  $baseExtra[] = 'bai_id';
if (table_has_column($pdo, 'orders', 'foto_id')) $baseExtra[] = 'foto_id';

$cols_full = filter_existing_cols($pdo, array_merge($core, $baseExtra));
if (!$cols_full) {
  fwrite(STDERR, "Tabel orders tidak memiliki kolom yang cocok.\n");
  exit(1);
}

$colList = implode(', ', $cols_full);
$valList = ':' . implode(',:', $cols_full);
$sqlIns  = "INSERT INTO orders ($colList) VALUES ($valList)";
$stIns   = $pdo->prepare($sqlIns);

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
    // tidak di-skip apapun karena selalu true
  }

  $fromName = from_display_name($m);
  $uid      = user_id_digits((string)($m['from_id'] ?? ''));

  $text = $extractText($m);

  $hasPsbPdaMoAny = preg_match('/\b(PSB|PDA|MO)\b/i', $text) === 1;
  $hasScField = (
      preg_match('/\b(NO|NOMO?R)\s*SC\b/i', $text) === 1
      || preg_match('/\bSC\s*:/i', $text) === 1
  );

  $isOrder =
      preg_match('/^\s*\/order\b/i', $text) ||
      preg_match('/^\s*\/psb\b/i', $text)   ||
      preg_match('/^\s*(psb|pda|mo)\b/im', $text) ||
      preg_match('/\bNO\s*SC\s*:/i', $text) ||
      preg_match('/\bSC\s*:/i', $text)      ||
      preg_match('/\bNO\s*(INET|INTERNET)\s*:/i', $text);

  if (!$isOrder) {
    $skipped++;
    continue;
  }

  if ($hasPsbPdaMoAny && !$hasScField) {
    $skipped++;
    continue;
  }

  $orderData = parse_order_text($text);
  if (!$orderData) {
    $skipped++;
    continue;
  }

  $orderData['sender_username'] = $fromName;
  $orderData['user_id']         = $uid;
  $orderData['created_at']      = $dtWib->format('Y-m-d H:i:s');
  if (in_array('bai_id', $cols_full, true))  $orderData['bai_id']  = 0;
  if (in_array('foto_id', $cols_full, true)) $orderData['foto_id'] = 0;

  $bind = [];
  foreach ($cols_full as $c) {
    $v = $orderData[$c] ?? '-';
    if (in_array($c, ['bai_id','foto_id','user_id'], true)) {
      $v = (int)($orderData[$c] ?? 0);
    }
    if ($c === 'created_at') {
      $v = $orderData[$c] ?? $dtWib->format('Y-m-d H:i:s');
    }
    $bind[":$c"] = $v;
  }

  $stIns->execute($bind);
  $imported++;
}

$pdo->commit();

// ====== Rangkuman ======
echo "Selesai import ORDER.\n";
echo "File JSON       : {$jsonPath}\n";
echo "Total messages  : {$total}\n";
echo "Imported orders : {$imported}\n";
echo "Skipped         : {$skipped}\n";
echo "Rentang WIB     : TANPA BATAS (semua pesan dalam file JSON diproses)\n";
