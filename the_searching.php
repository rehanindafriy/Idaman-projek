    <?php
    include "header.php";
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
      href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    
    <?php
    require_once __DIR__ . '/koneksi.php'; // $pdo (PDO) wajib ada
    
    if (!function_exists('e')) {
      function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    }
    
    // ==== DETEKSI KOLOM TAMBAHAN DI TABEL `orders` (alamat, qrcode, no_voip / no_voip_lama) ====
    $ordersCols = [];
    try {
      $stCols = $pdo->query("SHOW COLUMNS FROM `orders`");
      $ordersCols = array_map(static fn($r) => $r['Field'], $stCols->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
      $ordersCols = [];
    }
    
    $hasAlamat = in_array('alamat', $ordersCols, true);
    $hasQrcode = in_array('qrcode', $ordersCols, true);
    
    $colVoip = null;
    if (in_array('no_voip', $ordersCols, true)) {
      $colVoip = 'no_voip';
    } elseif (in_array('no_voip_lama', $ordersCols, true)) {
      $colVoip = 'no_voip_lama';
    }
    
    
    // ----- PARAMETER MASUK (dari search.php & search_bot.php) -----
    //  - ?id=123                       → prioritas utama (langsung by id_order)
    //  - ?q=xxx&f=no_internet          → mode lama single-field
    //  - ?codes[]=xxx&field=no_sc      → dari TomSelect (search_bot / search.php baru)
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    // kumpulkan codes[] (multi select) kalau ada
    $codes = [];
    if (isset($_GET['codes']) && is_array($_GET['codes'])) {
      foreach ($_GET['codes'] as $c) {
        $c = trim((string)$c);
        if ($c === '') continue;
        $codes[] = $c;
      }
      $codes = array_values(array_unique($codes));
    }
    
    // gabungkan nama field: f (lama) atau field (baru)
    $f     = $_GET['f'] ?? ($_GET['field'] ?? 'all');
    $f     = strtolower(trim((string)$f));
    if ($f === '') {
      $f = 'all';
    }
    
    // ambil nilai query utama (mode lama)
    $qRaw = trim((string)($_GET['q'] ?? ''));
    
    // mapping field yang diizinkan (seperti thesearching_bot.php)
    // mapping field yang diizinkan (disamakan dengan search.php terbaru)
    $allowedFields = [
      'no_sc'          => 'no_sc',
      'no_internet'    => 'no_internet',   // kompatibilitas link lama (kalau masih ada)
      'no_voip'        => 'no_internet',   // basis kolom, nanti di-OR dengan kolom VOIP
      'nama_pelanggan' => 'nama_pelanggan',
      'alamat'         => 'alamat',
      'cp_pelanggan'   => 'cp_pelanggan',
      'qrcode'         => 'qrcode',
      'detail'         => 'no_sc',         // detail = pilih 1 order by no_sc
      'all'            => 'all',
    ];
    
    if (!isset($allowedFields[$f])) {
      $f = 'all';
    }
    
    
    // DETEKSI MODE PENCARIAN
    $hasCodes       = !empty($codes);
    $hasQuerySingle = ($qRaw !== '' && mb_strlen($qRaw) >= 2);
    $hasAnySearch   = $hasCodes || $hasQuerySingle;
    $mode           = $hasCodes ? 'multi' : ($hasQuerySingle ? 'single' : 'none');
    
    // ================== QUERY DB ==================
    $rows       = [];
    $row        = [];
    $errorSearch = null;
    
    // ====== PRIORITAS: DETAIL BERDASARKAN ID ORDER ======
    if ($id > 0) {
      try {
        $sql = "SELECT
                  o.*,
                  b.id_bai        AS bai_match_id,
                  b.kode_bai      AS bai_kode,
                  b.nama_bai      AS bai_nama
                FROM orders o
                LEFT JOIN bai b
                  ON (b.kode_bai <> '' AND b.kode_bai = o.no_sc)
                WHERE o.id_order = :id
                ORDER BY o.created_at DESC, o.id_order DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rows = $row ? [$row] : [];
      } catch (Throwable $e) {
        $errorSearch = $e->getMessage();
        $row = [];
        $rows = [];
      }
    }
    
    // Kalau tidak by ID, pakai logika yang sama dengan thesearching_bot.php
    // Kalau tidak by ID, pakai logika yang sama dengan search.php/search_bot.php terbaru
    if ($id <= 0 && $hasAnySearch) {
      $where  = ' WHERE 1=1 ';
      $params = [];
    
      // supaya gampang, pakai variabel lokal
      $voipCol   = $colVoip;         // no_voip / no_voip_lama (kalau ada)
      $colAlamat = $hasAlamat ? 'alamat' : null;
      $colQr     = $hasQrcode ? 'qrcode' : null;
    
      if ($hasCodes) {
        // MODE BARU: multi-select (codes[]) dari TomSelect
        $vals = [];
        foreach ($codes as $v) {
          $v = trim((string)$v);
          if ($v === '') continue;
          $vals[] = $pdo->quote($v);
        }
        $vals = array_values(array_unique($vals));
    
        if (!$vals) {
          $where .= " AND 0";
        } else {
          $in = implode(',', $vals);
    
          // === MODE MULTI PER FILTER ===
          if ($f === 'no_sc' || $f === 'detail') {
            // detail di front-end sudah memastikan 1 no_sc terbaru
            $where .= " AND o.no_sc IN ($in)";
          } elseif ($f === 'no_voip') {
            // filter "No Internet / No Voip" → cek no_internet + kolom VOIP (kalau ada)
            $where .= " AND (o.no_internet IN ($in)";
            if ($voipCol) {
              $where .= " OR o.`{$voipCol}` IN ($in)";
            }
            $where .= ")";
          } elseif ($f === 'nama_pelanggan') {
            $where .= " AND o.nama_pelanggan IN ($in)";
          } elseif ($f === 'cp_pelanggan') {
            $where .= " AND o.cp_pelanggan IN ($in)";
          } elseif ($f === 'alamat') {
            if ($colAlamat) {
              $where .= " AND o.`{$colAlamat}` IN ($in)";
            } else {
              $where .= " AND 0";
            }
          } elseif ($f === 'qrcode') {
            if ($colQr) {
              $where .= " AND o.`{$colQr}` IN ($in)";
            } else {
              $where .= " AND 0";
            }
          } else {
            // 'all' (Semua Kolom) → gabungan seperti di search.php
            $parts = [
              "o.no_sc IN ($in)",
              "o.no_internet IN ($in)",
              "o.cp_pelanggan IN ($in)",
              "o.nama_pelanggan IN ($in)"
            ];
            if ($colAlamat) $parts[] = "o.`{$colAlamat}` IN ($in)";
            if ($colQr)     $parts[] = "o.`{$colQr}` IN ($in)";
            if ($voipCol)   $parts[] = "o.`{$voipCol}` IN ($in)";
    
            $where .= " AND (" . implode(' OR ', $parts) . ")";
          }
        }
      } else {
        // MODE LAMA: q + field (LIKE)
        $like = '%' . $qRaw . '%';
    
        if ($f === 'all') {
          // Semua kolom → no_sc, no_internet, cp, nama + (alamat, qrcode, voip kalau ada)
          $clauses = [
            'o.no_sc LIKE :q',
            'o.no_internet LIKE :q',
            'o.cp_pelanggan LIKE :q',
            'o.nama_pelanggan LIKE :q',
          ];
          if ($colAlamat) $clauses[] = "o.`{$colAlamat}` LIKE :q";
          if ($colQr)     $clauses[] = "o.`{$colQr}` LIKE :q";
          if ($voipCol)   $clauses[] = "o.`{$voipCol}` LIKE :q";
    
          $where .= ' AND (' . implode(' OR ', $clauses) . ')';
          $params[':q'] = $like;
    
        } elseif ($f === 'no_voip') {
          // No Internet / No Voip → LIKE di no_internet + VOIP
          $where .= ' AND (o.no_internet LIKE :q';
          if ($voipCol) {
            $where .= " OR o.`{$voipCol}` LIKE :q";
          }
          $where .= ')';
          $params[':q'] = $like;
    
        } else {
          // field spesifik lain (no_sc, nama_pelanggan, alamat, cp_pelanggan, qrcode, no_internet, detail)
          $col = $allowedFields[$f] ?? 'no_sc';
    
          if ($col === 'all') {
            // fallback kalau entah kenapa masuk ke sini
            $where .= " AND (
              o.no_sc LIKE :q
              OR o.no_internet LIKE :q
              OR o.cp_pelanggan LIKE :q
              OR o.nama_pelanggan LIKE :q
            )";
          } else {
            $where .= " AND o.`{$col}` LIKE :q ";
          }
    
          $params[':q'] = $like;
        }
      }
    
      // QUERY UTAMA (tetap sama)
      $sql = "
        SELECT
          o.*,
          b.id_bai   AS bai_match_id,
          b.kode_bai AS bai_kode,
          b.nama_bai AS bai_nama
        FROM orders o
        LEFT JOIN bai b
          ON (b.kode_bai <> '' AND b.kode_bai = o.no_sc)
        {$where}
        ORDER BY
          o.created_at DESC,
          o.id_order   DESC
        LIMIT 200
      ";
    
      try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) === 1) {
          $row = $rows[0];
        }
      } catch (Throwable $ex) {
        $errorSearch = $ex->getMessage();
        $rows = [];
        $row  = [];
      }
    }
    
    ?>
    
    <?php
    function sc_equal($a, $b): bool {
      // bandingkan trim & case-insensitive
      return strcasecmp(trim((string)$a), trim((string)$b)) === 0;
    }
    
    /**
     * Cari file BAI di uploads/bai/ dengan prioritas:
     * 1) nama_bai (persis atau tanpa ekstensi)
     * 2) bai_nama (alias) kalau ada
     * 3) kode_bai
     * 4) no_sc
     * Mengembalikan ['rel'=> path untuk href, 'name'=> nama file] atau null.
     */
    function locate_bai_file(array $row): ?array {
      $baseDir = __DIR__ . '/uploads/bai/';
      $webPref = 'uploads/bai/';
      $tryExts = ['pdf','PDF','doc','docx','xls','xlsx','jpg','jpeg','png','webp'];
    
      // urutan kandidat nama dasar
      $cands = [];
      foreach (['nama_bai','bai_nama','bai_kode','no_sc'] as $k) {
        if (!empty($row[$k])) { $cands[] = trim((string)$row[$k]); }
      }
    
      foreach ($cands as $cand) {
        if ($cand === '') { continue; }
    
        // 1) jika sudah ada ekstensi → cek langsung (case-sensitive & exact)
        if (preg_match('/\.[A-Za-z0-9]{3,4}$/', $cand)) {
          $abs = $baseDir . basename($cand);
          if (is_file($abs)) {
            return ['rel' => $webPref . basename($cand), 'name' => basename($cand)];
          }
          // coba pencarian longgar (case-insensitive) dengan glob
          $matches = glob($baseDir . '*' . pathinfo($cand, PATHINFO_FILENAME) . '*.*');
          if ($matches) {
            $abs = $matches[0];
            if (is_file($abs)) {
              return ['rel' => $webPref . basename($abs), 'name' => basename($abs)];
            }
          }
        }
    
        // 2) kalau tanpa ekstensi → coba semua ekstensi umum
        foreach ($tryExts as $ext) {
          $abs = $baseDir . basename($cand) . '.' . $ext;
          if (is_file($abs)) {
            return ['rel' => $webPref . basename($cand) . '.' . $ext, 'name' => basename($cand) . '.' . $ext];
          }
        }
    
        // 3) fallback sangat longgar: ada file yang mengandung teks kandidat
        $matches = glob($baseDir . '*' . basename($cand) . '*');
        if ($matches) {
          foreach ($matches as $abs) {
            if (is_file($abs)) {
              return ['rel' => $webPref . basename($abs), 'name' => basename($abs)];
            }
          }
        }
      }
    
      return null;
    }
    
    function shorten_middle($s, $keep = 32) {
      $s = (string)$s;
      if (mb_strlen($s) <= $keep) { return $s; }
      $half = (int)floor(($keep - 3) / 2);
      return mb_substr($s, 0, $half) . '...' . mb_substr($s, -$half);
    }
    ?>
    <?php
    // === Lokasi folder BAI ===
    $BAI_FS_DIR   = rtrim(realpath(__DIR__ . '/bot/file') ?: (__DIR__ . '/bot/file'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $BAI_URL_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/bot/file';
    
    // helper aman untuk casting/trim (hindari deprecated di PHP 8.1+)
    if (!function_exists('trimSafe')) {
      function trimSafe($v): string { return trim((string)$v); }
    }
    if (!function_exists('_n')) {
      // normalisasi: lowercase + buang spasi (untuk pencocokan longgar)
      function _n(string $s): string { return strtolower(preg_replace('/\s+/', '', $s)); }
    }
    
    /**
     * Cari file BAI untuk satu baris $row dengan prioritas:
     * 1) exact match nama file yang tersimpan (nama_bai / bai_nama) di folder bot/file/
     * 2) jika tidak ada, scan bot/file/ dan ambil file TERBARU yang nama filenya
     *    mengandung salah satu dari: kode_bai (bai_kode/kode_bai) atau no_sc.
     * Return: ['fs','name','rel'] atau null
     */
    function find_bai_file_for_row(array $row, string $fsDir, string $urlBase): ?array {
      if (!is_dir($fsDir)) return null;
    
      // 1) exact match by stored names (pakai trim aman)
      $byNames = array_values(array_unique(array_filter(array_map('trimSafe', [
        $row['nama_bai'] ?? null,
        $row['bai_nama'] ?? null,
      ]))));
      foreach ($byNames as $fn) {
        $p = $fsDir . basename($fn);
        if (is_file($p)) {
          $bn = basename($p);
          return ['fs'=>$p, 'name'=>$bn, 'rel'=>$urlBase . '/' . rawurlencode($bn)];
        }
      }
    
      // 2) scan by codes in filename (kode_bai / no_sc)
      $codes = array_values(array_unique(array_filter(array_map('trimSafe', [
        $row['bai_kode'] ?? ($row['kode_bai'] ?? null),
        $row['no_sc']    ?? null,
      ]))));
      if (!$codes) return null;
      $codesN = array_map('_n', $codes);
    
      $hits = [];
      foreach (scandir($fsDir, SCANDIR_SORT_NONE) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $p = $fsDir . $name;
        if (!is_file($p)) continue;
    
        $n = _n($name);
        foreach ($codesN as $c) {
          if ($c !== '' && strpos($n, $c) !== false) {
            $hits[$p] = @filemtime($p) ?: 0; // simpan mtime utk pilih terbaru
            break;
          }
        }
      }
      if (!$hits) return null;
    
      arsort($hits);                    // terbaru dulu
      $p  = array_key_first($hits);
      $bn = basename($p);
      return ['fs'=>$p, 'name'=>$bn, 'rel'=>$urlBase . '/' . rawurlencode($bn)];
    }
    
    // helper ikon file
    function bai_icon_by_ext(string $ext): string {
      $e = strtolower($ext);
      if ($e === 'pdf') return 'mdi-file-pdf-box';
      if ($e === 'doc' || $e === 'docx') return 'mdi-file-word';
      if ($e === 'xls' || $e === 'xlsx') return 'mdi-file-excel';
      if ($e === 'ppt' || $e === 'pptx') return 'mdi-file-powerpoint';
      if ($e === 'csv') return 'mdi-file-delimited';
      if (in_array($e, ['jpg','jpeg','png','gif','webp'], true)) return 'mdi-file-image';
      if (in_array($e, ['zip','rar','7z'], true)) return 'mdi-folder-zip';
      return 'mdi-file-outline';
    }
    ?>
    <?php
    // ====== FOTO: path dasar ======
    $FOTO_FS_DIR   = rtrim(realpath(__DIR__ . '/bot/foto') ?: (__DIR__ . '/bot/foto'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $FOTO_URL_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/bot/foto';
    
    // normalisasi nama utk cocokan longgar
    if (!function_exists('_n')) {
      function _n(string $s): string { return strtolower(preg_replace('/\s+/', '', $s)); }
    }
    
    /**
     * Cari folder foto terbaik untuk satu order:
     * Prioritas:
     * 1) folder yang NAMANYA persis = no_sc
     * 2) ada di tabel `foto` (kode_foto = no_sc) -> ambil yang date_received terbaru
     * 3) scan semua folder di bot/foto yang NAMANYA mengandung salah satu kode kandidat:
     *    - no_sc
     *    - bai_kode/kode_bai (kalau tersedia di $row)
     *    Pilih folder dengan mtime TERBARU
     * Return: ['folder'=>'NamaFolder', 'fs'=>'abs_path/', 'url'=>'/bot/foto/NamaFolder'] atau null
     */
    function find_photo_folder_for_row(array $row, PDO $pdo, string $fsDir, string $urlBase): ?array {
      if (!is_dir($fsDir)) return null;
    
      $noSc    = trim((string)($row['no_sc'] ?? ''));
      $baiCode = trim((string)($row['bai_kode'] ?? ($row['kode_bai'] ?? '')));
    
      // 1) exact match = folder no_sc
      if ($noSc !== '') {
        $p = $fsDir . $noSc;
        if (is_dir($p)) {
          return ['folder'=>$noSc, 'fs'=>$p . DIRECTORY_SEPARATOR, 'url'=>$urlBase . '/' . rawurlencode($noSc)];
        }
      }
    
      // 2) cek tabel foto (jika ada record persis kode_foto = no_sc)
      if ($noSc !== '') {
        $st = $pdo->prepare("SELECT kode_foto FROM foto WHERE kode_foto = :k ORDER BY date_received DESC LIMIT 1");
        $st->execute([':k'=>$noSc]);
        if ($f = $st->fetch(PDO::FETCH_ASSOC)) {
          $folder = $f['kode_foto'];
          $p = $fsDir . $folder;
          if (is_dir($p)) {
            return ['folder'=>$folder, 'fs'=>$p . DIRECTORY_SEPARATOR, 'url'=>$urlBase . '/' . rawurlencode($folder)];
          }
        }
      }
    
      // 3) scan longgar by candidates, pilih yang paling baru
      $cands = array_values(array_unique(array_filter([ $noSc, $baiCode ], fn($x)=>trim((string)$x) !== '')));
      if (!$cands) return null;
      $candsN = array_map('_n', $cands);
    
      $hits = [];
      foreach (scandir($fsDir, SCANDIR_SORT_NONE) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $p = $fsDir . $name;
        if (!is_dir($p)) continue;
    
        $n = _n($name);
        foreach ($candsN as $c) {
          if ($c !== '' && ( $n === $c || strpos($n, $c) !== false )) {
            $hits[$name] = filemtime($p);
            break;
          }
        }
      }
      if (!$hits) return null;
    
      arsort($hits); // terbaru dulu
      $folder = array_key_first($hits);
      $p = $fsDir . $folder;
      return ['folder'=>$folder, 'fs'=>$p . DIRECTORY_SEPARATOR, 'url'=>$urlBase . '/' . rawurlencode($folder)];
    }
    
    /**
     * List semua file gambar di dalam folder foto.
     * Urutan: angka pada nama file (1.jpg, 2.png, ...), lalu mtime.
     */
    function list_photo_urls(string $folderAbs, string $folderUrl): array {
      if (!is_dir($folderAbs)) return [];
      $allowed = '/\.(?:jpe?g|png|webp|gif)$/i';
    
      $files = [];
      foreach (scandir($folderAbs, SCANDIR_SORT_NONE) ?: [] as $fn) {
        $p = $folderAbs . $fn;
        if (!is_file($p) || !preg_match($allowed, $fn)) continue;
        $num = (int)preg_replace('/\D+/', '', pathinfo($fn, PATHINFO_FILENAME));
        $files[] = [
          'name'  => $fn,
          'num'   => $num,
          'mtime' => filemtime($p),
          'url'   => $folderUrl . '/' . rawurlencode($fn),
        ];
      }
      usort($files, fn($a,$b) => ($a['num'] <=> $b['num']) ?: ($a['mtime'] <=> $b['mtime']));
      return array_column($files, 'url');
    }
    ?>
    <!-- partial -->
    <div class="main-panel">
      <div class="content-wrapper">
        <!-- ===================== PAGE HEADER (TIDAK DIUBAH) ===================== -->
        <div class="row">
          <div class="col-md-12 grid-margin">
            <div class="d-flex justify-content-between align-items-end flex-wrap">
              <!-- kiri -->
              <div class="mr-md-3 mr-xl-5">
                <h2 class="mb-1">Detail Order </h2>
                <p class="mb-0">
                  Form pencarian ini digunakan untuk mencari data Order, berdasarkan berbagai kriteria. Anda dapat
                </p>
                <p>
                  memasukkan kata kunci seperti Nomor SC, Nomor Internet, Nama Pelanggan, ODP REAL, atau Nomor HP Pelanggan.
                </p>
              </div>
    
              <!-- kanan: logo + breadcrumb -->
              <div class="d-flex flex-column align-items-end me-3 mt-n1">
                <img
                  src="images/logo.png"
                  alt="Logo e-Siklo"
                  class="mb-1"
                  style="height:45px; margin-top:-10px; display:block;"
                />
    
                <br />
                <br />
    
                <div class="d-flex align-items-center flex-wrap breadcrumb-mini mb-0 mt-n30">
                  <i
                    class="mdi mdi-home text-muted mx-1 crumb-icon"
                    style="font-size:13px;line-height:1;vertical-align:-90px"
                  ></i>
    
                  <a href="index.php" class="text-muted mx-1 crumb-mid">
                    &nbsp;/&nbsp;Dashboard&nbsp;/&nbsp;
                  </a>
    
                  <span class="text-primary crumb-mid">Detail Order</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- ===================== /PAGE HEADER ===================== -->
    
        <?php
        // Pastikan $row dari query di atas tersedia
        if (empty($row)) {
          if (!empty($rows) && is_array($rows)) {
            // ada hasil di $rows -> ambil baris pertama
            $row = $rows[0];
          } else {
            // memang tidak ada hasil sama sekali
            $row = [];
          }
        }
    
        // ===================== HELPER FIELD & FORMAT =====================
       $fields_left = [
  'sto','mitra','no_sc','no_internet','ncli','nama_pelanggan','alamat',
  'cp_pelanggan','odp_real','port','marking_dc_awal','marking_dc_akhir',
  'ac_of_sm_1b','ac_of_sm_1_3sl','clamp_hook','otp_ftth_1',
  'prekso_intra_15_rs','prekso_intra_20_rs','rs_in_sc_1','s_clamp_sprinter'
  // created_at DIPINDAH ke kanan
];

$fields_right = [
  'order_type','soc_ils','soc_sum','qrcode','sn_ont_type','stb_id_type',
  'tag_odp','tag_pelanggan','labor','valins_id','slot','portt',
  'ip_olt','hasil_valins','onu_gendong',
  'no_inet_lama','no_voip_lama','sn_ont_lama','stbid_lama','sender_username',
  'created_at' // sekarang muncul di kartu kanan
];

    
        $caps_exceptions = [
          'qrcode','sn_ont_type','stb_id_type','valins_id','slot','portt','ip_olt',
          'hasil_valins','onu_gendong','no_inet_lama','no_voip_lama','sn_ont_lama',
          'stbid_lama','sender_username','odp_real','port'
        ];
    
        if (!function_exists('h')) {
          function h($s) { return htmlspecialchars((string)($s ?? '-'), ENT_QUOTES, 'UTF-8'); }
        }
        if (!function_exists('firstCap')) {
          function firstCap($s) { return ucwords(mb_strtolower(trim((string)$s), 'UTF-8')); }
        }
       if (!function_exists('coords_from')) {
      function coords_from($s) {
        $s = (string)$s;
        if ($s === '') { return null; }
    
        // Ambil semua angka (boleh pakai titik)
        if (!preg_match_all('/-?\d+(?:\.\d+)?/', $s, $m)) {
          return null;
        }
        $nums = $m[0];
        if (count($nums) < 2) {
          return null;
        }
    
        // Format spesial 3 angka:
        // contoh: "-7.5728884 112 3591462" → lat = -7.5728884, lon = 112.3591462
        if (count($nums) === 3
            && strpos($nums[1], '.') === false
            && strpos($nums[2], '.') === false) {
    
          $lat = $nums[0];
          $lon = $nums[1] . '.' . $nums[2];
    
        } else {
          // format normal: "LAT, LON" atau "LAT LON" (2 angka pertama saja)
          $lat = $nums[0];
          $lon = $nums[1];
        }
    
        return [trim($lat), trim($lon)];
      }
    }
    
        if (!function_exists('gmaps')) {
          function gmaps($s) {
            $c = coords_from($s); if (!$c) { return null; }
            return 'https://www.google.com/maps/search/?api=1&query=' . $c[0] . ',' . $c[1];
          }
        }
        if (!function_exists('human_dur')) {
          function human_dur($from) {
            if (!$from) { return '-'; }
            $t = strtotime($from); if (!$t) { return '-'; }
            $d = time() - $t; if ($d < 0) { $d = 0; }
            $days = floor($d / 86400); $d %= 86400; $h = floor($d / 3600); $d %= 3600; $m = floor($d / 60);
            return ($days ? "{$days} hari " : "") . ($h ? "{$h} jam " : "") . ($m ? "{$m} menit" : ($days || $h ? '' : '<1 menit'));
          }
        }
    
        if (!function_exists('render_row')) {
          function render_row(string $name, array $row, array $caps_exceptions = []): void {
            $label = strtoupper(str_replace('_', ' ', $name));
            $raw   = $row[$name] ?? '';
            $val   = trim((string)$raw) === '' ? '-' : (string)$raw;
    
            if ($name === 'created_at' && !empty($val)) {
              $ts  = strtotime($val);
              if ($ts) { $val = date('Y-m-d H:i:s', $ts); }
            }
    
            if ($name === 'alamat') {
              $val = nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
            } else {
              $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            }
    
            $valueClass = in_array($name, $caps_exceptions, true) ? 'kv-value keepcase' : 'kv-value cap';
    
            echo <<<HTML
              <div class="kv-row">
                <div class="kv-label">{$label}</div>
                <div class="{$valueClass}">{$val}</div>
              </div>
            HTML;
          }
        }
    
        // ===================== FOTO ===================== (sudah aman)
        $photoSet = !empty($row) ? find_photo_folder_for_row($row, $pdo, $FOTO_FS_DIR, $FOTO_URL_BASE) : null;
        $photos   = $photoSet ? list_photo_urls($photoSet['fs'], $photoSet['url']) : [];
        $n        = count($photos);
        $capt     = [];
        foreach ($photos as $i => $u) {
          $capt[$u] = 'Foto #' . ($i + 1);
        }
        // URL download semua foto (per kode no_sc)
        $fotoCode  = trim((string)($row['no_sc'] ?? ($row['kode_foto'] ?? '')));
        $dlFotoUrl = 'download_photos.php?sc=' . urlencode($fotoCode);
    
        // ===================== BAI FILE ===================== (sudah aman)
        $baiFile = !empty($row) ? find_bai_file_for_row($row, $BAI_FS_DIR, $BAI_URL_BASE) : null;
        $hasFile = $baiFile !== null;
        $ext     = $hasFile ? strtolower(pathinfo($baiFile['name'], PATHINFO_EXTENSION)) : '';
        $icon    = bai_icon_by_ext($ext);
    
        // ===================== DATA UTAMA =====================
        $no_sc        = $row['no_sc']          ?? ($row['id_order'] ?? '-');
        $no_internet  = $row['no_internet']    ?? '-';
        $ncli         = $row['ncli']           ?? '-';
        $sto          = $row['sto']            ?? '-';
        $mitra        = $row['mitra']          ?? '-';
        $pelanggan    = $row['nama_pelanggan'] ?? '-';
        $created      = $row['created_at']     ?? null;
        $odp_real     = $row['odp_real']       ?? '-';
        $port         = $row['port']           ?? '-';
        $order_type   = $row['order_type']     ?? '-';
    
        // ===================== KOORDINAT PELANGGAN =====================
        $pelCoords = coords_from($row['tag_pelanggan'] ?? '');
        $has_pel   = is_array($pelCoords);
        $pelPair   = $has_pel ? ($pelCoords[0] . ',' . $pelCoords[1]) : '';
        $pelMaps   = $has_pel ? 'https://www.google.com/maps/search/?api=1&query=' . $pelPair : '#';
        $pelDir    = $has_pel ? 'https://www.google.com/maps/dir/?api=1&destination=' . $pelPair . '&travelmode=driving' : '#';
        $pelStreet = $has_pel ? 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' . $pelPair : '#';
    
        // ===================== KOORDINAT ODP =====================
        $odpCoords = coords_from($row['tag_odp'] ?? '');
        $has_odp   = is_array($odpCoords);
        $odpPair   = $has_odp ? ($odpCoords[0] . ',' . $odpCoords[1]) : '';
    
        if (!function_exists('haversine_m')) {
          function haversine_m($lat1, $lon1, $lat2, $lon2) {
            $R = 6371000.0; // meter
            $phi1 = deg2rad((float)$lat1);
            $phi2 = deg2rad((float)$lat2);
            $dphi = deg2rad((float)$lat2 - (float)$lat1);
            $dl   = deg2rad((float)$lon2 - (float)$lon1);
            $a = sin($dphi/2)**2 + cos($phi1) * cos($phi2) * sin($dl/2)**2;
            return 2 * $R * asin(min(1, sqrt($a)));
          }
        }
    
        $hasBoth   = $has_pel && $has_odp;
        $pelPairArr= $has_pel ? [(float)$pelCoords[0], (float)$pelCoords[1]] : null;
        $odpPairArr= $has_odp ? [(float)$odpCoords[0], (float)$odpCoords[1]] : null;
        $distanceM = null;
        if ($hasBoth) {
          $distanceM = haversine_m($pelPairArr[0], $pelPairArr[1], $odpPairArr[0], $odpPairArr[1]);
        }
    
        $odpLabel = trim((string)($row['odp_real'] ?? 'ODP'));
        $pelName  = trim((string)($row['nama_pelanggan'] ?? 'Pelanggan'));
    
        $gmapsPel = $has_pel ? ('https://www.google.com/maps/search/?api=1&query='.$pelPairArr[0].','.$pelPairArr[1]) : '#';
        $gmapsOdp = $has_odp ? ('https://www.google.com/maps/search/?api=1&query='.$odpPairArr[0].','.$odpPairArr[1]) : '#';
        $dirUrl   = $hasBoth ? ('https://www.google.com/maps/dir/?api=1&origin='.$pelPairArr[0].','.$pelPairArr[1].'&destination='.$odpPairArr[0].','.$odpPairArr[1].'&travelmode=driving') : '#';
        $streetPel= $has_pel ? ('https://www.google.com/maps/@?api=1&map_action=pano&viewpoint='.$pelPairArr[0].','.$pelPairArr[1]) : '#';
        $streetOdp= $has_odp ? ('https://www.google.com/maps/@?api=1&map_action=pano&viewpoint='.$odpPairArr[0].','.$odpPairArr[1]) : '#';
        $wazePel  = $has_pel ? ('https://waze.com/ul?ll='.$pelPairArr[0].','.$pelPairArr[1].'&navigate=yes') : '#';
        $wazeOdp  = $has_odp ? ('https://waze.com/ul?ll='.$odpPairArr[0].','.$odpPairArr[1].'&navigate=yes') : '#';
    
        ?>
    
        <style>
          :root{
            --es-teal:       #00a3ad;
            --es-teal-soft:  #e5f7f9;
            --es-teal-dark:  #03606c;
            --ti-red:        #e60000;
            --slate-900:     #0f172a;
            --slate-700:     #334155;
            --slate-500:     #64748b;
          }
    
          .detail-shell{
            display:flex;
            flex-direction:column;
            gap:14px; /* agak dirapetin */
          }
          .detail-section{
            margin-bottom:2px;
          }
    
          /* ===================== TOP GRID ===================== */
          .ds-grid{
      display:grid;
      grid-template-columns: minmax(0, 2.1fr) minmax(0, 1.3fr);
      gap:14px;
      align-items:stretch;   /* <-- sebelumnya flex-start */
    }
    
          .ds-main-col{
            display:flex;
            flex-direction:column;
            gap:0;
          }
    
          .ds-main-card,
          .ds-side-card,
          .map-card,
          .kv-card{
            background:#ffffff;
            border-radius:9px;
            border:1px solid #e5e7eb;
        
          }
    
          /* main card kiri jadi satu kartu besar */
         .ds-main-card--stack{
      padding:0;
      overflow:hidden;
      display:flex;
      flex-direction:column;
      flex:1 1 auto;   /* <-- tambah ini supaya tinggi kartu kiri ikut penuh */
    }
    
          .ds-main-body{
            padding:10px 14px 12px;
            background:#ffffff;
            border-top:1px solid #e5e7eb;
            display:flex;
            flex-direction:column;
            gap:10px;
          }
    
          /* ===================== HERO (BANNER) ===================== */
          .order-hero{
            padding:16px 18px 14px;
            border-radius:9px 9px 0 0;
            position:relative;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            gap:14px;
            /* GRADIASI TIDAK DIUBAH — tetap lembut tosca */
            background:
              radial-gradient(circle at 0% 0%, rgba(255,255,255,.95) 0, rgba(255,255,255,0) 55%),
              radial-gradient(circle at 100% 0%, rgba(255,255,255,.85) 0, rgba(255,255,255,0) 55%),
              linear-gradient(120deg,#e5f7f9 0%,#cceff4 35%,#8fd8df 100%);
          }
          .order-hero::after{
            content:"";
            position:absolute;
            inset:0;
            pointer-events:none;
            background:
              radial-gradient(circle at 120% 0%, rgba(0,163,173,.18) 0, transparent 55%);
            opacity:.7;
          }
    
          .oh-layout{
            position:relative;
            z-index:1;
            display:grid;
            grid-template-columns:minmax(0, 2.4fr) minmax(0, 1.6fr);
            gap:16px;
            align-items:flex-start;
          }
    
          .oh-main{
            min-width:0;
          }
          .oh-eyebrow{
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.18em;
            color:#03606c;
            margin-bottom:4px;
          }
          .oh-title{
            font-size:22px;
            font-weight:800;
            color:#014451;
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:8px;
            margin-bottom:4px;
          }
          .oh-sub{
            font-size:13px;
            color:#0f3e47;
            opacity:.9;
          }
    
          .chip{
            display:inline-flex;
            align-items:center;
            padding:3px 10px;
            border-radius:9px;
            font-size:12px;
            font-weight:600;
            background:#ffffffd9;
            color:#03606c;
            border:1px solid rgba(0,163,173,.25);
            white-space:nowrap;
          }
    
          /* Kanan atas: info ringkas yang formal */
          /* Kanan atas: meta info simple pills di atas gradiasi */
    .oh-meta{
      display:flex;
      flex-direction:column;
      gap:6px;
      align-self:flex-start;
      align-items:flex-end;
    }
    
    .meta-pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 10px;
      border-radius:999px;
      background:rgba(255,255,255,.25);           /* transparan, nyatu dengan gradiasi */
      border:1px solid rgba(255,255,255,.7);
      backdrop-filter:blur(6px);
      -webkit-backdrop-filter:blur(6px);
      white-space:nowrap;
    }
    
    .meta-label{
      font-size:10px;
      text-transform:uppercase;
      letter-spacing:.16em;
      color:#0f3e47;
      opacity:.85;
    }
    
    .meta-value{
      font-size:13px;
      font-weight:600;
      color:#01323b;
    }
    
    .meta-dot{
      width:4px;
      height:4px;
      border-radius:999px;
      background:#0b7285;
      opacity:.7;
    }
    
    .meta-icon{
      font-size:16px;
      color:#0b7285;
    }
    
    .meta-chip-time{
      font-size:11px;
      padding:1px 6px;
      border-radius:999px;
      background:rgba(0,0,0,.06);
      color:#014451;
    }
    
    
          /* ===================== MINI GALLERY STYLE ===================== */
          .mini-gallery{
            position:relative;
            z-index:1;
            margin-top:0;
          }
          .mini-gallery-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            margin-bottom:6px;
          }
          .mini-eyebrow{
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.12em;
            color:#64748b;
          }
          .mini-title{
            font-size:14px;
            font-weight:700;
            color:#0f172a;
          }
          .mini-download{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 10px;
            border-radius:9px;
            border:1px solid rgba(11,127,126,.35);
            background:#0b7f7e;
            color:#fff;
            font-size:11px;
            text-decoration:none;
            white-space:nowrap;
          }
          .mini-download i{ font-size:14px; }
          .mini-download:hover{
            filter:brightness(1.05);
            color:#fff;
          }
    
          .mini-gallery-strip{
            display:flex;
            gap:8px;
            overflow-x:auto;
            padding-bottom:4px;
          }
          .mini-gallery-strip::-webkit-scrollbar{
            height:4px;
          }
          .mini-gallery-strip::-webkit-scrollbar-thumb{
            background:#cbd5e1;
            border-radius:9px;
          }
    
          .pf-item{
            position:relative;
            display:block;
            border-radius:6px;
            overflow:hidden;
            border:1px solid #dde4f0;
            background:#f8fafc;
            flex:0 0 82px;
            max-width:82px;
            height:60px;
            box-shadow:none;
          }
          .pf-item img{
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
            transition:transform .2s ease;
          }
          .pf-item:hover img{ transform:scale(1.03); }
          .pf-badge{
            position:absolute;
            left:6px;top:6px;
            padding:1px 7px;
            border-radius:9px;
            font-size:10px;
            font-weight:700;
            color:#0b7f7e;
            background:#ccfbf1;
            border:1px solid #99f6e4;
          }
          .mini-gallery--empty .mini-empty-text{
            font-size:12px;
            color:#6b7280;
            padding:6px 0 2px;
          }
    
          /* ===================== METRIC GRID (4 CHIP) ===================== */
          .metric-grid{
            display:grid;
            grid-template-columns: repeat(4,minmax(0,1fr));
            gap:8px; /* chip lebih rapat */
          }
          .metric-card{
            border-radius:9px;
            border:1px solid #e5e7eb;
            padding:6px 10px;
            background:#f9fafb;
           
          }
          .metric-card--location{
            background:#f1f5f9;
          }
          .metric-card--photo{
            background:#fef2f2;
            border-color:#fecaca;
          }
          .metric-label{
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.12em;
            color:#6b7280;
            margin-bottom:2px;
          }
          .metric-value{
            font-size:15px;
            font-weight:700;
            color:#0f172a;
            margin-bottom:1px;
          }
          .metric-value--mono{
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size:13px;
          }
          .metric-sub{
            font-size:12px;
            color:#6b7280;
          }
          .metric-actions{
            margin-top:4px;
            display:flex;
            flex-wrap:wrap;
            gap:4px;
          }
          .metric-actions a,
          .metric-actions button{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:26px;
            height:26px;
            border-radius:9px;
            border:1px solid #cbd5e1;
            background:#ffffff;
            font-size:13px;
            color:#0f172a;
            text-decoration:none;
            cursor:pointer;
          }
          .metric-actions i{
            font-size:16px;
          }
          .metric-actions a:hover,
          .metric-actions button:hover{
            filter:brightness(1.05);
          }
    
          /* ===================== DOKUMENTASI LAPANGAN (DALAM CARD KIRI) ===================== */
          .doc-card{
            background:#ffffff;
            border-radius:9px;
            border:1px solid #e5e7eb;
           
            padding:12px 14px 10px;
          }
          /* versi di dalam ds-main-card, tanpa border & shadow sendiri */
          .ds-main-card--stack .doc-card{
            border:none;
            border-radius:0;
            box-shadow:none;
            padding:8px 0 4px;
            margin-top:2px;
          }
          .doc-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            margin-bottom:6px;
          }
          .doc-meta{
            display:flex;
            flex-direction:column;
          }
    
          /* ===================== STATUS SIDE ===================== */
          .ds-side-card{
            padding:12px 13px 13px;
            display:flex;
            flex-direction:column;
            gap:10px;
          }
          .status-card{
            border-radius:9px;
            border:1px solid #e5e7eb;
            padding:8px 10px 9px;
            background:#f9fafb;
          }
          .status-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:6px;
            margin-bottom:6px;
          }
          .status-title{
            font-size:13px;
            font-weight:700;
            color:#0f172a;
          }
          .status-badge{
            font-size:11px;
            border-radius:9px;
            padding:3px 9px;
            font-weight:600;
          }
          .status-badge.ok{
            background:#ecfdf3;
            color:#15803d;
            border:1px solid #bbf7d0;
          }
          .status-badge.warn{
            background:#fef2f2;
            color:#b91c1c;
            border:1px solid #fecaca;
          }
    
          .status-steps{
            list-style:none;
            padding:0;
            margin:0;
          }
          .status-step{
            display:flex;
            align-items:flex-start;
            gap:8px;
            position:relative;
            padding-bottom:8px;
          }
          .status-step:not(:last-child)::after{
            content:"";
            position:absolute;
            left:8px;
            top:18px;
            width:1px;
            height:22px;
            background:linear-gradient(to bottom, #e5e7eb, transparent);
          }
          .status-bullet{
            flex:0 0 auto;
            width:16px;
            height:16px;
            border-radius:9px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:10px;
            color:#fff;
            background:var(--ti-red);
            box-shadow:0 0 0 2px #fff;
          }
          .status-bullet.ok{
            background:#16a34a;
          }
          .status-bullet.muted{
            background:#9ca3af;
          }
          .status-body{
            flex:1;
          }
          .status-label{
            font-size:12px;
            font-weight:600;
            color:#0f172a;
          }
          .status-desc{
            font-size:11px;
            color:#6b7280;
          }
    
                .bai-note{
            margin-top:6px;
            font-size:11px;
            line-height:1.4;
            color:#6b7280;
          }
    
    
          .bai-card{
            border-radius:9px;
            border:1px solid #e5e7eb;
            padding:8px 10px;
            background:#ffffff;
          }
          .bai-title-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:6px;
            margin-bottom:6px;
          }
          .bai-title{
            font-size:12px;
            letter-spacing:.16em;
            text-transform:uppercase;
            color:#6b7280;
          }
          .bai-title-row .mdi{
            font-size:18px;
          }
          .bai-ok{ color:#16a34a; }
          .bai-warn{ color:#b91c1c; }
    
          .bai-link-wrap{
            display:flex;
            align-items:center;
            gap:6px;
          }
          .bai-link{
            flex:1;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:7px 10px;
            border-radius:4px;
            border:1px solid #c8e6c9;
            background:#ecfdf3;
            text-decoration:none;
            min-width:0;
          }
          .bai-link .file-name{
            font-size:13px;
            color:#0f172a;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
          }
          .bai-icon{
            font-size:20px;
          }
          .bai-download{
            width:34px;
            height:34px;
            border-radius:6px;
            border:1px solid #0f766e;
            background:#0b7f7e;
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            text-decoration:none;
          }
          .bai-download:hover{
            color:#f9fafb;
            filter:brightness(1.03);
          }
          .bai-empty{
            font-size:12px;
            color:#991b1b;
            padding:7px 9px;
            border-radius:4px;
            border:1px dashed #fecaca;
            background:#fef2f2;
          }
    
          /* ===================== KV CARD ===================== */
          .kv-card .card-body{
            padding:14px 18px;
          }
          .kv-title{
            font-weight:700;
            font-size:18px;
            color:#233244;
            margin-bottom:6px;
          }
          .kv-list{ margin:0; }
          .kv-row{
            display:flex;
            align-items:flex-start;
            padding:11px 0;
            border-top:1px solid rgba(15,23,42,.06);
          }
          .kv-row:first-child{ border-top:0; }
          .kv-label{
            flex:0 0 35%;
            font-weight:400;
            text-transform:uppercase;
            letter-spacing:.18px;
            color:#0f172a;
            font-size:12px;
            line-height:1.25;
          }
          .kv-value{
            flex:1;
            color:#374151;
            font-size:13px;
            line-height:1.25;
            word-wrap:anywhere;
          }
          .kv-value.cap{ text-transform:capitalize; }
          .kv-value.keepcase{ text-transform:none; }
    /* ===================== MAP & STREET VIEW (NEW) ===================== */
    .map-card{
      position:relative;
      border-radius:12px;
      background:#ffffff;                 /* FLAT, tanpa gradiasi */
      border:1px solid #e2e8f0;
      padding:14px 16px 16px;
      overflow:hidden;
    }
    
    /* hilangkan layer gradiasi di belakang card */
    .map-card::before{
      content:none;
    }
    
    .map-card-header{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:18px;
      margin-bottom:12px;
    }
    
    .map-card-eyebrow{
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.16em;
      color:#64748b;
      margin-bottom:4px;
    }
    
    .map-card-title{
      display:flex;
      align-items:center;
      gap:8px;
      font-size:17px;
      font-weight:800;
      color:#0f172a;
      margin-bottom:4px;
    }
    .map-card-title i{
      font-size:20px;
      color:#0b7285;
    }
    
    .map-card-subtitle{
      font-size:12px;
      color:#6b7280;
      max-width:360px;
    }
    
    /* ROW CHIP PELANGGAN / ODP / JARAK */
    .map-card-stats{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      align-items:stretch;
      justify-content:flex-start;  /* jarak di kanan di-handle oleh .map-stat--distance */
      min-width:280px;
    }
    
    .map-stat{
      display:flex;
      flex-direction:column;
      gap:1px;
      padding:6px 11px;
      border-radius:6px;          /* bentuk chip/pill */
      background:#f8fafc;
      border:1px solid #e2e8f0;
      min-width:150px;
    }
    
    .map-stat-label{
      font-size:10px;
      text-transform:uppercase;
      letter-spacing:.14em;
      color:#64748b;
    }
    
    .map-stat-value{
      font-size:13px;
      font-weight:700;
      color:#0f172a;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    
    .map-stat-sub{
      font-size:11px;
      color:#6b7280;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    
    /* chip jarak lurus – selalu dorong ke paling kanan */
    .map-stat--distance{
      margin-left:auto;
    }
    
    /* tanpa gradiasi, cuma accent soft tosca */
    .map-stat--accent{
      background:linear-gradient(135deg, #ecfeff4a 0%, #ccfbf18e 100%);
      border-color:rgba(0, 164, 173, 0.2);
    }
    
    .map-stat-label{
      font-size:10px;
      text-transform:uppercase;
      letter-spacing:.14em;
      color:#64748b;
    }
    .map-stat-value{
      font-size:14px;
      font-weight:700;
      color:#0f172a;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .map-stat-sub{
      font-size:11px;
      color:#6b7280;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    
    .map-card-body{
      display:flex;
      flex-direction:column;
      gap:10px;
    }
    
    .map-layout{
      display:grid;
      grid-template-columns:minmax(0,1.7fr) minmax(0,1.1fr);
      gap:12px;
      align-items:stretch;
    }
    
    .map-panel{
      position:relative;
      border-radius:10px;
      overflow:hidden;
      background:#020617;
      border:1px solid #0f172a;
    }
    
    #linkMap{
      height:380px;
      width:100%;
    }
    
    /* ====== STREET VIEW KANAN: 1 IFRAME + TAB ====== */
    .map-street{
      display:flex;
      flex-direction:column;
      gap:8px;
    }
    
    .sv-tabs{
      display:flex;
      gap:6px;
    }
    
    .sv-tab{
      flex:1;
      display:flex;
      flex-direction:column;
      align-items:flex-start;
      justify-content:center;
      padding:6px 9px;
      border-radius:999px;
      border:1px solid #e2e8f0;
      background:#f8fafc;
      cursor:pointer;
      transition:all .18s ease-out;
    }
    .sv-tab-label{
      font-size:11px;
      font-weight:600;
      color:#0f172a;
    }
    .sv-tab-sub{
      font-size:10px;
      color:#6b7280;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .sv-tab.active{
      background:linear-gradient(135deg,#ecfeff 0%,#ccfbf1 100%);
      border-color:rgba(0, 164, 173, 0.31);
      box-shadow:0 1px 10px rgba(15,23,42,.16);
      transform:translateY(-1px);
    }
    
    .sv-main{
      flex:1;
      min-height:0;
      display:flex;
      flex-direction:column;
      border-radius:10px;
      border:1px solid #e2e8f0;
      background:#ffffff;
      overflow:hidden;
    }
    .sv-main-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:8px 10px;
      border-bottom:1px solid #e2e8f0;
      background:#f8fafc;
    }
    .sv-main-title{
      font:700 12px/1.2 system-ui,Segoe UI,Arial;
      letter-spacing:.1em;
      text-transform:uppercase;
      color:#0f172a;
    }
    .sv-main-chip{
      font-size:11px;
      padding:3px 8px;
      border-radius:999px;
      background:#e0f2fe;
      color:#0b7285;
    }
    .sv-main-body{
      flex:1;
      min-height:0;
    }
    .sv-main-footer{
      padding:6px 10px 7px;
      border-top:1px solid #e5e7eb;
      font-size:11px;
      color:#6b7280;
      background:#f9fafb;
    }
    
    .sv-frame{
      display:block;
      width:100%;
      border:0;
      height:100%;
    }
    
    /* ====== FOOTER AKSI MAP ====== */
    .map-card-footer{
      margin-top:6px;
      padding-top:8px;
      border-top:1px dashed rgba(148,163,184,.6);
      display:flex;
      flex-direction:column;
      gap:6px;
    }
    .map-actions-title{
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.16em;
      color:#9ca3af;
    }
    .map-actions-row{
      display:flex;
      flex-wrap:wrap;
      gap:8px 12px;
      align-items:flex-start;
    }
    .map-actions-group{
      display:flex;
      flex-wrap:wrap;
      gap:6px;
      align-items:center;
    }
    .map-actions-chip{
      font-size:11px;
      padding:3px 8px;
      border-radius:999px;
      background:#f1f5f9;
      color:#0f172a;
      border:1px solid #e2e8f0;
    }
    
    .map-actions a,
    .map-actions button{
      display:inline-flex;
      align-items:center;
      gap:6px;
      height:28px;
      padding:0 10px;
      border-radius:999px;
      border:1px solid #cbd5e1;
      background:#ffffff;
      color:#0f172a;
      font-size:12px;
      text-decoration:none;
      cursor:pointer;
    }
    .map-actions i{
      font-size:15px;
    }
    .map-actions a:hover,
    .map-actions button:hover{
      filter:brightness(1.05);
      background:#f9fafb;
    }
    
    .map-empty{
      margin-top:6px;
      padding:10px 12px;
      border-radius:10px;
      border:1px dashed #fecaca;
      background:#fef2f2;
      font-size:12px;
      color:#991b1b;
      display:flex;
      align-items:flex-start;
      gap:8px;
    }
    
    /* ====== PIN & JARAK LEAFLET (2D – FORMAL, BERLABEL) ====== */
    /* ====== PIN & JARAK LEAFLET (2D – FORMAL, ANIMATED) ====== */
    
    /* ====== PIN & JARAK LEAFLET (2D – KORPORAT + ANIMASI) ====== */
    
    /* ====== PIN & JARAK LEAFLET (2D – ICON KORPORAT + ANIMASI) ====== */
    
    /* wrapper bawaan divIcon */
    .lf-pin{
      pointer-events:auto;
    }
    
    /* BULATAN DASAR SEMUA PIN */
    .pin{
      position:relative;
      display:flex;
      align-items:center;
      justify-content:center;
      width:22px;
      height:22px;
      border-radius:999px;
      background:#ffffff;
      border:1px solid rgba(15,23,42,.18);
      box-shadow:
        0 0 0 1px rgba(15,23,42,.06),
        0 0 0 0 rgba(15,23,42,0);
      transform-origin:center;
      transition:transform .18s ease-out, box-shadow .18s ease-out;
    }
    
    /* isi bulatan utama */
    .pin::before{
      content:"";
      position:absolute;
      inset:4px;
      border-radius:inherit;
      background:var(--pin-fill,#0f172a);
      background-size:200% 200%;
    }
    
    /* teks bawaan dimatikan (kita pakai ikon) */
    .pin::after{
      content:"";
    }
    
    /* ikon di tengah (MDI) */
    .pin i.pin-ico{
      position:relative;
      z-index:1;
      font-size:12px;
      color:var(--pin-ink,#0f172a);
    }
    
    /* ========== ODP (tosca gradient + ripple) ========== */
    .pin-odp{
      --pin-fill: linear-gradient(135deg,#06b6d4,#0369a1);
      --pin-ink: #e0f2fe;
      animation: pinPulseOdp 2.6s ease-out infinite;
    }
    
    /* ========== PELANGGAN (green gradient + ripple) ========== */
    .pin-pel{
      --pin-fill: linear-gradient(135deg,#22c55e,#0f766e);
      --pin-ink: #ecfdf3;
      animation: pinPulsePel 2.8s ease-out infinite;
    }
    
    /* ========== TITIK JARAK (ring + garis tengah hidup) ========== */
    .pin-jarak{
      --pin-fill:#f9fafb;
      --pin-ink:#020617;
      animation: pinPulseDist 3.0s ease-out infinite;
    }
    
    /* jarak: isi diganti ring + garis bergerak */
    .pin-jarak::before{
      inset:5px;
      border-radius:inherit;
      border:1.6px solid #0f172a;
      background-image:
        linear-gradient(90deg,transparent 0%,#0f172a 50%,transparent 100%);
      background-repeat:no-repeat;
      background-position:0% 50%;
      background-size:260% 2px;
      animation: distLineFlow 1.9s linear infinite;
    }
    
    /* paket kecil yang lari di sepanjang garis */
    .pin.pin-packet{
      width:14px;
      height:14px;
      border-width:1px;
      box-shadow:
        0 0 0 1px rgba(15,23,42,.06),
        0 0 0 0 rgba(59,130,246,0);
      animation: packetPulse 2.2s ease-out infinite;
    }
    .pin.pin-packet::before{
      inset:3px;
      border-radius:inherit;
      border:none;
      background:
        radial-gradient(circle at 30% 30%, #ffffff 0, #e0f7fa 40%, #0f766e 100%);
    }
    
    /* hover halus, tetap 2D */
    .pin:hover{
      transform:translateY(-1px) scale(1.03);
    }
    
    /* ====== KEYFRAMES RIPPLE & LINE ====== */
    @keyframes pinPulseOdp{
      0%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.06),
          0 0 0 0 rgba(6,182,212,.45);
        transform:translateY(0) scale(1);
      }
      60%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.03),
          0 0 0 14px rgba(6,182,212,0);
        transform:translateY(-0.5px) scale(1.06);
      }
      100%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.06),
          0 0 0 0 rgba(6,182,212,0);
        transform:translateY(0) scale(1);
      }
    }
    
    @keyframes pinPulsePel{
      0%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.06),
          0 0 0 0 rgba(34,197,94,.50);
        transform:translateY(0) scale(1);
      }
      60%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.03),
          0 0 0 14px rgba(34,197,94,0);
        transform:translateY(-0.5px) scale(1.06);
      }
      100%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.06),
          0 0 0 0 rgba(34,197,94,0);
        transform:translateY(0) scale(1);
      }
    }
    
    @keyframes pinPulseDist{
      0%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.10),
          0 0 0 0 rgba(15,23,42,.40);
      }
      70%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.05),
          0 0 0 18px rgba(15,23,42,0);
      }
      100%{
        box-shadow:
          0 0 0 1px rgba(15,23,42,.06),
          0 0 0 0 rgba(15,23,42,0);
      }
    }
    
    /* garis kecil di titik jarak yang “berlari” */
    @keyframes distLineFlow{
      0%{
        background-position:0% 50%;
      }
      100%{
        background-position:100% 50%;
      }
    }
    
    /* glow lembut untuk paket yang bergerak */
    @keyframes packetPulse{
      0%{
        box-shadow:
          0 0 0 0 rgba(56,189,248,.45);
      }
      70%{
        box-shadow:
          0 0 0 10px rgba(56,189,248,0);
      }
      100%{
        box-shadow:
          0 0 0 0 rgba(56,189,248,0);
      }
    }
    
    /* tooltip & badge jarak tetap */
    .lf-dist{
      background:#020617;
      color:#e5e7eb;
      border:1px solid rgba(148,163,184,.7);
      padding:6px 10px;
      border-radius:7px;
      font-size:12px;
    }
    .lf-dist b{ font-size:13px; }
    
    .leaflet-tooltip.tip-pill{
      background:#fff;
      border:1px solid #e5e7eb;
      border-radius:8px;
      padding:6px 8px;
      font:600 12px/1.2 system-ui,Segoe UI,Arial;
      box-shadow:0 6px 16px rgba(2,8,23,.08);
      color:#0f172a;
    }
    .leaflet-tooltip.tip-pill .sub{
      display:block;
      font-weight:400;
      opacity:.75;
    }
    
    
          /* ===================== RESPONSIVE ===================== */
          @media (max-width: 1200px){
            .ds-grid{
              grid-template-columns: minmax(0,1fr);
            }
            .oh-layout{
              grid-template-columns:minmax(0,1fr);
            }
            .metric-grid{
              grid-template-columns: repeat(2,minmax(0,1fr));
            }
            .map-layout{
              grid-template-columns:minmax(0,1fr);
            }
          }
    
          @media (max-width: 768px){
            .metric-grid{
              grid-template-columns: minmax(0,1fr);
            }
            #linkMap{
              height:320px;
            }
            .mini-gallery-strip{
              padding-bottom:6px;
            }
            .map-card{
              padding:12px 12px 14px;
            }
            .map-card-header{
              flex-direction:column;
              align-items:flex-start;
            }
            .map-card-stats{
              justify-content:flex-start;
            }
            .map-actions-row{
              flex-direction:column;
              align-items:flex-start;
            }
          }
    
        </style>
    
       <?php if (!empty($row)): ?>
          <div class="detail-shell">
            <!-- ========== SECTION 1: HERO + STATUS + CHIP + DOKUMENTASI ========== -->
            <section class="detail-section">
              <div class="ds-grid">
                <!-- LEFT: satu kartu besar -->
                <div class="ds-main-col">
                  <div class="ds-main-card ds-main-card--stack">
                    <div class="order-hero">
                      <div class="oh-layout">
                        <!-- KIRI: judul dan identitas pelanggan -->
                        <div class="oh-main">
                          <div class="oh-eyebrow">Ringkasan Order</div>
                          <div class="oh-title">
                            Data Order
                            <span class="chip"><?= h($no_sc) ?></span>
                          </div>
                          <div class="oh-sub">
                            <?= h(firstCap($pelanggan)) ?> · STO <?= h(strtoupper($sto)) ?> · <?= h(firstCap($mitra)) ?>
                          </div>
                        </div>
    
                        <!-- KANAN: informasi formal dalam bentuk key–value -->
                       <!-- KANAN: meta info simple pills -->
    <div class="oh-meta">
      <div class="meta-pill">
        <span class="meta-label">Jenis Order</span>
        <span class="meta-dot"></span>
        <span class="meta-value"><?= h($order_type) ?></span>
      </div>
    
      <div class="meta-pill">
        <i class="mdi mdi-clock-time-four-outline meta-icon"></i>
        <span class="meta-label">Dikirim</span>
        <span class="meta-value">
          <?= $created ? h(date('d M Y H:i', strtotime($created))) . ' WIB' : '-' ?>
        </span>
        <?php if ($created): ?>
          <span class="meta-chip-time"><?= human_dur($created) ?> lalu</span>
        <?php endif; ?>
      </div>
    </div>
    
                      </div>
                    </div><!-- /order-hero -->
    
                    <div class="ds-main-body">
                    <?php
    // ====== NORMALISASI NO VOIP (pakai no_voip, fallback no_voip_lama) ======
    $rawNoVoip = '';
    if (!empty($row['no_voip'])) {
        $rawNoVoip = trim((string)$row['no_voip']);
    } elseif (!empty($row['no_voip_lama'])) {
        $rawNoVoip = trim((string)$row['no_voip_lama']);
    }
    $showNoVoip = ($rawNoVoip !== '') ? $rawNoVoip : '-';
    ?>
    
                      <!-- 4 CHIP METRIC -->
                      <div class="metric-grid">
                        <div class="metric-card">
                          <div class="metric-label">No Internet</div>
                          <div class="metric-value"><?= h($no_internet) ?></div>
    <div class="metric-sub">No Voip: <?= h($showNoVoip) ?></div>

                        </div>
    
                        <div class="metric-card metric-card--location">
                          <div class="metric-label">Koordinat Pelanggan</div>
                          <div class="metric-value metric-value--mono">
                            <?= $has_pel ? h($pelPair) : '-' ?>
                          </div>
                          <?php if ($has_pel): ?>
                            <div class="metric-actions">
                              <a href="<?= h($pelMaps) ?>" title="Google Maps" target="_blank" rel="noopener">
                                <i class="mdi mdi-map-search-outline"></i>
                              </a>
                              <a href="<?= h($pelDir) ?>" title="Rute" target="_blank" rel="noopener">
                                <i class="mdi mdi-directions"></i>
                              </a>
                              <a href="https://waze.com/ul?ll=<?= h($pelPair) ?>&navigate=yes" title="Waze" target="_blank" rel="noopener">
                                <i class="mdi mdi-routes"></i>
                              </a>
                              <a href="<?= h($pelStreet) ?>" title="Street View" target="_blank" rel="noopener">
                                <i class="mdi mdi-road-variant"></i>
                              </a>
                              <button type="button" id="kopelCopy" data-copy="<?= h($pelPair) ?>" title="Copy Koordinat">
                                <i class="mdi mdi-content-copy"></i>
                              </button>
                            </div>
                          <?php else: ?>
                            <div class="metric-sub">Belum ada koordinat pelanggan di <code>tag_pelanggan</code>.</div>
                          <?php endif; ?>
                        </div>
    
                        <div class="metric-card">
                          <div class="metric-label">ODP / Port</div>
                          <div class="metric-value"><?= h($odp_real) ?></div>
                          <div class="metric-sub">Port: <?= h($port) ?></div>
                        </div>
    
                        <div class="metric-card metric-card--photo">
                          <div class="metric-label">Dokumentasi</div>
                          <div class="metric-value"><?= $n ?> Foto</div>
                          <div class="metric-sub">
                            <?= $n ? 'Klik foto di galeri untuk perbesar.' : 'Belum ada foto dokumentasi.' ?>
                          </div>
                        </div>
                      </div>
    
                      <!-- GALERI FOTO DALAM CARD KIRI -->
                      <div class="doc-card">
                        <div class="doc-head">
                          <div class="doc-meta">
                            <div class="mini-eyebrow">Dokumentasi Lapangan</div>
                            <div class="mini-title">Galeri Foto <?= $n ? '(' . $n . ' foto)' : '' ?></div>
                          </div>
                          <?php if ($n): ?>
                            <a href="<?= h($dlFotoUrl) ?>" class="mini-download">
                              <i class="mdi mdi-download"></i>
                              <span>Download Semua Foto</span>
                            </a>
                          <?php endif; ?>
                        </div>
    
                        <div class="mini-gallery <?= $n ? '' : 'mini-gallery--empty' ?>">
                          <?php if ($n > 0): ?>
                            <div class="mini-gallery-strip">
                              <?php foreach ($photos as $i => $src): $idx = $i + 1; ?>
                                <a
                                  class="pf-item"
                                  href="javascript:void(0)"
                                  data-index="<?= $i ?>"
                                  data-src="<?= h($src) ?>"
                                  data-caption="<?= h($capt[$src] ?? "Foto #{$idx}") ?>"
                                >
                                  <img src="<?= h($src) ?>" alt="Foto <?= $idx ?>" loading="lazy">
                                  <span class="pf-badge">#<?= $idx ?></span>
                                </a>
                              <?php endforeach; ?>
                            </div>
                          <?php else: ?>
                            <div class="mini-empty-text">
                              Belum ada foto dokumentasi untuk order ini.
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div><!-- /ds-main-body -->
                  </div><!-- /ds-main-card -->
                </div>
    
                <!-- RIGHT: STATUS & BAI -->
                <aside class="ds-side-card">
                  <div class="status-card">
                    <div class="status-head">
                      <div class="status-title">Status Pekerjaan</div>
                      <span class="status-badge <?= $hasFile ? 'ok' : 'warn' ?>">
                        <?= $hasFile ? 'BAI tersedia' : 'BAI belum ada' ?>
                      </span>
                    </div>
                    <ol class="status-steps">
                      <li class="status-step">
                        <div class="status-bullet ok">1</div>
                        <div class="status-body">
                          <div class="status-label">Order Masuk</div>
                          <div class="status-desc">
                            <?= $created ? h(date('d M Y H:i', strtotime($created))) . ' WIB' : 'Tanggal belum tercatat' ?>
                          </div>
                        </div>
                      </li>
                      <li class="status-step">
                        <div class="status-bullet <?= $hasFile ? 'ok' : 'muted' ?>">2</div>
                        <div class="status-body">
                          <div class="status-label">Berita Acara Instalasi</div>
                          <div class="status-desc">
                            <?= $hasFile ? 'File BAI sudah terhubung dengan order ini.' : 'Belum ditemukan file BAI yang sesuai.' ?>
                          </div>
                        </div>
                      </li>
                      <li class="status-step">
                        <div class="status-bullet <?= $n ? 'ok' : 'muted' ?>">3</div>
                        <div class="status-body">
                          <div class="status-label">Dokumentasi Lapangan</div>
                          <div class="status-desc">
                            <?= $n ? $n . ' foto tersimpan di galeri.' : 'Belum ada dokumentasi foto.' ?>
                          </div>
                        </div>
                      </li>
                    </ol>
                  </div>
    
                  <div class="bai-card">
                    <div class="bai-title-row">
                      <span class="bai-title">Berita Acara Instalasi</span>
                      <i class="mdi <?= $hasFile ? 'mdi-check-circle-outline bai-ok' : 'mdi-alert-circle-outline bai-warn' ?>"></i>
                    </div>
    
                    <?php if ($hasFile): ?>
                      <div class="bai-link-wrap">
                        <a class="bai-link" href="<?= h($baiFile['rel']) ?>" target="_blank" rel="noopener">
                          <i class="mdi <?= h($icon) ?> bai-icon"></i>
                          <span class="file-name"><?= h(shorten_middle($baiFile['name'], 34)) ?></span>
                        </a>
                        <a class="bai-download" href="download.php?f=<?= h(urlencode($baiFile['rel'])) ?>">
                          <i class="mdi mdi-download"></i>
                        </a>
                      </div>
                    <?php else: ?>
                      <div class="bai-empty">
                        Berita Acara Instalasi untuk order ini belum ditemukan di folder BAI.
                      </div>
                    <?php endif; ?>
                     <p class="bai-note">
                      Catatan: pastikan file bai mempunyai nama yang sama dengan Nomor SC
                    </p>
                  </div>
                </aside>
              </div>
            </section>
    
            <!-- ========== MODAL GALERI FOTO ========== -->
            <?php if ($n): ?>
              <div class="modal fade" id="pfModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                  <div class="modal-content" style="background:#0b1220;color:#e5e7eb;">
                    <div class="modal-header border-0">
                      <strong id="pfCap" style="font-size:14px"></strong>
                      <div class="ms-auto d-flex align-items-center gap-2">
                        <a id="pfDl" class="btn btn-sm btn-light" href="#" download>Download</a>
                        <button type="button" class="btn btn-sm btn-secondary" id="pfPrev">‹</button>
                        <button type="button" class="btn btn-sm btn-secondary" id="pfNext">›</button>
                        <button
                          type="button"
                          id="pfClose"
                          class="btn btn-sm btn-outline-light"
                          data-bs-dismiss="modal"
                          data-dismiss="modal"
                          aria-label="Close"
                        >
                          Tutup
                        </button>
                      </div>
                    </div>
                    <div class="modal-body p-0" style="height:70vh;display:flex;align-items:center;justify-content:center;">
                      <img id="pfImg" src="" alt="" style="max-width:100%;max-height:100%;object-fit:contain" />
                    </div>
                  </div>
                </div>
              </div>
    
              <script>
                (function(){
                  const items = Array.from(document.querySelectorAll('.pf-item'));
                  if (!items.length) return;
    
                  const isBS5  = !!(window.bootstrap && window.bootstrap.Modal);
                  const modalEl= document.getElementById('pfModal');
                  const modal  = isBS5 ? new bootstrap.Modal(modalEl) : null;
    
                  function showModal(){
                    if (isBS5) modal.show();
                    else if (window.jQuery) jQuery('#pfModal').modal('show');
                  }
    
                  const img  = document.getElementById('pfImg');
                  const cap  = document.getElementById('pfCap');
                  const dl   = document.getElementById('pfDl');
    
                  function fileNameFromUrl(u){
                    try { return decodeURIComponent(u.split('/').pop().split('?')[0]) || 'photo'; }
                    catch(e){ return 'photo'; }
                  }
    
                  let idx = 0;
                  if (items[0]) {
                    const firstUrl = items[0].dataset.src;
                    dl.href = firstUrl;
                    dl.setAttribute('download', fileNameFromUrl(firstUrl));
                  }
    
                  function openAt(i){
                    if (i < 0) i = items.length - 1;
                    if (i >= items.length) i = 0;
                    idx = i;
    
                    const el = items[idx];
                    const url = el.dataset.src;
                    const caption = el.dataset.caption || '';
    
                    img.src = url;
                    cap.textContent = caption;
                    dl.href = url;
                    dl.setAttribute('download', fileNameFromUrl(url));
    
                    showModal();
                  }
    
                  items.forEach((el, i) => el.addEventListener('click', () => openAt(i)));
    
                  const prevBtn = document.getElementById('pfPrev');
                  const nextBtn = document.getElementById('pfNext');
                  if (prevBtn) prevBtn.onclick = () => openAt(idx - 1);
                  if (nextBtn) nextBtn.onclick = () => openAt(idx + 1);
    
                  document.addEventListener('keydown', (e) => {
                    const shown = modalEl.classList.contains('show');
                    if (!shown) return;
                    if (e.key === 'ArrowLeft')  { e.preventDefault(); openAt(idx - 1); }
                    if (e.key === 'ArrowRight') { e.preventDefault(); openAt(idx + 1); }
                  });
                })();
              </script>
            <?php endif; ?>
    
            <!-- ========== SECTION 3: DATA ORDER (2 KARTU) ========== -->
            <section class="detail-section">
              <div class="row">
                <div class="col-md-6 grid-margin stretch-card">
                  <div class="card kv-card">
                    <div class="card-body">
                      <div class="kv-title">Data Pelanggan & Layanan</div>
                     <div class="kv-list">
  <?php foreach ($fields_left as $f): ?>
    <?php
      // render baris standar
      render_row($f, $row, $caps_exceptions);

      // setelah NCLI, sisipkan baris NO VOIP pakai $showNoVoip
      if ($f === 'ncli'):
    ?>
      <div class="kv-row">
        <div class="kv-label">NO VOIP</div>
        <div class="kv-value keepcase"><?= h($showNoVoip) ?></div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

                    </div>
                  </div>
                </div>
                <div class="col-md-6 grid-margin stretch-card">
                  <div class="card kv-card">
                    <div class="card-body">
                      <div class="kv-title">Data Teknis & Tagging</div>
                      <div class="kv-list">
                        <?php foreach ($fields_right as $f) render_row($f, $row, $caps_exceptions); ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>
    
            <!-- ========== SECTION 4: PETA ODP ↔ PELANGGAN ========== -->
            <?php
              // label jarak untuk header (formal)
              $distanceLabel = '-';
              if ($distanceM !== null) {
                if ($distanceM >= 1000) {
                  $distanceLabel = number_format($distanceM/1000, 2, ',', '.') . ' km';
                } else {
                  $distanceLabel = number_format($distanceM, 0, ',', '.') . ' m';
                }
              }
            ?>
    
            <?php if ($has_pel || $has_odp): ?>
              <?php if (!defined('LEAFLET_ONCE')): define('LEAFLET_ONCE',1); ?>
                <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
                <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
                <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                <script src="https://unpkg.com/leaflet-ant-path@1.3.0/dist/leaflet-ant-path.min.js"></script>
                <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
              <?php endif; ?>
    
              <section class="detail-section">
                <div class="map-card">
                  <div class="map-card-header">
                    <div>
                      <div class="map-card-eyebrow">Peta Teknis</div>
                      <div class="map-card-title">
                        <i class="mdi mdi-map-legend"></i>
                        <span>Peta ODP ↔ Pelanggan</span>
                      </div>
                      <div class="map-card-subtitle">
                        Visualisasi jarak, rute, serta tampilan Street View antara titik ODP dan lokasi pelanggan.
                      </div>
                    </div>
    
                   <div class="map-card-stats">
      <?php if ($has_pel): ?>
        <div class="map-stat">
          <div class="map-stat-label">Pelanggan</div>
          <div class="map-stat-value"><?= h($pelName) ?></div>
          <div class="map-stat-sub"><?= h($pelPair) ?></div>
        </div>
      <?php endif; ?>
    
      <?php if ($has_odp): ?>
        <div class="map-stat">
          <div class="map-stat-label">ODP</div>
          <div class="map-stat-value"><?= h($odpLabel) ?></div>
          <div class="map-stat-sub">Koordinat tersimpan</div>
        </div>
      <?php endif; ?>
    
      <?php if ($hasBoth): ?>
        <div class="map-stat map-stat--accent map-stat--distance">
          <div class="map-stat-label">Perkiraan jarak lurus</div>
          <div class="map-stat-value"><?= h($distanceLabel) ?></div>
          <div class="map-stat-sub">Garis lurus ODP ↔ pelanggan</div>
        </div>
      <?php endif; ?>
    </div>
    
                  </div>
    
                  <?php if ($hasBoth): ?>
                    <div class="map-card-body">
                      <div class="map-layout">
                        <!-- PANEL MAP -->
                        <div class="map-panel">
                          <div
                            id="linkMap"
                            data-odp-lat="<?= h($odpPairArr[0]) ?>"
                            data-odp-lng="<?= h($odpPairArr[1]) ?>"
                            data-pel-lat="<?= h($pelPairArr[0]) ?>"
                            data-pel-lng="<?= h($pelPairArr[1]) ?>"
                            data-odp-label="<?= h($odpLabel) ?>"
                            data-pel-label="<?= h($pelName) ?>"
                            data-distance="<?= h((string)$distanceM) ?>"
                          ></div>
                        </div>
    
                        <!-- STREET VIEW -->
                       <!-- STREET VIEW: 1 IFRAME + TAB PELANGGAN / ODP -->
    <aside class="map-street">
      <div class="sv-tabs">
        <button type="button"
                class="sv-tab active"
                data-target="pel">
          <span class="sv-tab-label">Pelanggan</span>
          <span class="sv-tab-sub"><?= $has_pel ? h($pelPair) : '-' ?></span>
        </button>
        <button type="button"
                class="sv-tab"
                data-target="odp">
          <span class="sv-tab-label">ODP</span>
          <span class="sv-tab-sub"><?= $has_odp ? h($odpLabel) : '-' ?></span>
        </button>
      </div>
    
      <div class="sv-main">
        <div class="sv-main-head">
          <span class="sv-main-title">Street View</span>
          <span class="sv-main-chip" id="svActiveChip">Pelanggan</span>
        </div>
        <div class="sv-main-body">
          <iframe id="svFrame"
                  class="sv-frame"
                  allowfullscreen
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
        <div class="sv-main-footer">
          <small>Geser, zoom, dan putar tampilan Street View untuk orientasi posisi di lapangan.</small>
        </div>
      </div>
    </aside>
    
                      </div>
    
                      <!-- ACTIONS BAWAH: rute, maps, waze, copy koordinat -->
                      <div class="map-card-footer">
                        <div class="map-actions-title">Akses cepat</div>
                        <div class="map-actions-row">
                          <?php if ($hasBoth): ?>
                            <div class="map-actions-group">
                              <span class="map-actions-chip">Umum</span>
                              <div class="map-actions">
                                <a href="<?= h($dirUrl) ?>" target="_blank" rel="noopener">
                                  <i class="mdi mdi-directions"></i>
                                  Rute ODP ↔ Pelanggan
                                </a>
                              </div>
                            </div>
                          <?php endif; ?>
    
                          <?php if ($has_pel): ?>
                            <div class="map-actions-group">
                              <span class="map-actions-chip">Lokasi Pelanggan</span>
                              <div class="map-actions">
                                <a href="<?= h($gmapsPel) ?>" target="_blank" rel="noopener">
                                  <i class="mdi mdi-map-search-outline"></i>Maps
                                </a>
                                <a href="<?= h($wazePel) ?>" target="_blank" rel="noopener">
                                  <i class="mdi mdi-routes"></i>Waze
                                </a>
                                <a href="<?= h($streetPel) ?>" target="_blank" rel="noopener">
                                  <i class="mdi mdi-road-variant"></i>Street
                                </a>
                                <button type="button" id="copyPELx">
                                  <i class="mdi mdi-content-copy"></i>Copy
                                </button>
                              </div>
                            </div>
                          <?php endif; ?>
    
                          <?php if ($has_odp): ?>
                            <div class="map-actions-group">
                              <span class="map-actions-chip">Lokasi ODP</span>
                              <div class="map-actions">
                                <a href="<?= h($gmapsOdp) ?>" target="_blank" rel="noopener">
                                  <i class="mdi mdi-map-search-outline"></i>Maps
                                </a>
                                <a href="<?= h($wazeOdp) ?>" target="_blank" rel="noopener">
                                  <i class="mdi mdi-routes"></i>Waze
                                </a>
                                <a href="<?= h($streetOdp) ?>" target="_blank" rel="noopener">
                                  <i class="mdi mdi-road-variant"></i>Street
                                </a>
                                <button type="button" id="copyODPx">
                                  <i class="mdi mdi-content-copy"></i>Copy
                                </button>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="map-empty">
                      <i class="mdi mdi-map-marker-off-outline"></i>
                      <div>
                        Koordinat <?= $has_odp ? 'pelanggan' : ($has_pel ? 'ODP' : 'ODP & pelanggan') ?> belum lengkap.
                        Lengkapi <code>tag_odp</code> dan <code>tag_pelanggan</code> agar peta dapat menampilkan garis rute dan jarak secara otomatis.
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </section>
    
              <?php if ($hasBoth): ?>
                <!-- JS PETA (logika sama, hanya tampilan yang sudah di-style ulang) -->
    <script>
    (function () {
      const el = document.getElementById('linkMap');
      if (!el || !window.L) return;
    
      const d = {
        odp: [parseFloat(el.dataset.odpLat), parseFloat(el.dataset.odpLng)],
        pel: [parseFloat(el.dataset.pelLat), parseFloat(el.dataset.pelLng)],
        odpLabel: el.dataset.odpLabel || 'ODP',
        pelLabel: el.dataset.pelLabel || 'Pelanggan',
        distance: parseFloat(el.dataset.distance || '0')
      };
    
      function svEmbedUrl(lat, lng){
        const ll = `${lat},${lng}`;
        return `https://www.google.com/maps?q=${ll}&layer=c&cbll=${ll}&cbp=11,0,0,0,0&hl=id&output=svembed`;
      }
    
      const esc = (s) =>
        String(s ?? '').replace(/[&<>"'`]/g, m =>
          ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;'}[m])
        );
    
      const fixLatLng = (pt) => (!isFinite(pt[0]) || !isFinite(pt[1]))
        ? null
        : (Math.abs(pt[0]) <= 90 ? pt : [pt[1], pt[0]]);
    
      d.odp = fixLatLng(d.odp);
      d.pel = fixLatLng(d.pel);
      if (!d.odp || !d.pel) { console.error('Koordinat tidak valid', d); return; }
    
      const map = L.map(el, { zoomControl: true, scrollWheelZoom: false });
      const JAWG_TOKEN = 'bGqIzBsVAri5ydtWQH4IbMLwI5FQHoBDRyOi2xQC5pWnEY50f89ngkdxTVMxvCAl';
    
      const jawgSunny = L.tileLayer(
        'https://tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=' + JAWG_TOKEN,
        {
          minZoom: 2,
          maxZoom: 22,
          tileSize: 256,
          subdomains: 'abcd',
          detectRetina: true,
          attribution:
            '&copy; <a href="https://www.jawg.io/">Jawg</a>, ' +
            '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors'
        }
      ).addTo(map);
    
      jawgSunny.on('tileerror', function () {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 25,
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
      });
    
      const jawgStreets = L.tileLayer(
        'https://tile.jawg.io/jawg-streets/{z}/{x}/{y}{r}.png?access-token=' + JAWG_TOKEN,
        {
          minZoom: 2,
          maxZoom: 25,
          tileSize: 256,
          subdomains: 'abcd',
          detectRetina: true,
          attribution:
            '&copy; <a href="https://www.jawg.io/">Jawg</a>, ' +
            '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors'
        }
      );
    
      jawgStreets.on('tileerror', function () {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 25,
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
      });
    
      L.control.layers(
        { 'Jawg Sunny (aktif)': jawgSunny, 'Jawg Streets': jawgStreets },
        null,
        { position:'topleft', collapsed:true }
      ).addTo(map);
    
      const cluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        spiderfyOnEveryZoom: true,
        disableClusteringAtZoom: 14,
        maxClusterRadius: 60
      });
    
      setTimeout(() => map.invalidateSize(), 50);
    
      /* ====== ICON PIN 2D DENGAN MDI ====== */
      const ICONS = {
        odp: 'mdi-access-point-network',
        pel: 'mdi-home-map-marker',
        jarak: 'mdi-arrow-left-right'
      };
    
      const makePinIcon = (role) => L.divIcon({
        className: 'lf-pin lf-pin-' + role,
        html: `<span class="pin pin-${role}"><i class="mdi ${ICONS[role] || 'mdi-map-marker-outline'} pin-ico"></i></span>`,
        iconSize: [22,22],
        iconAnchor: [11,11],
        popupAnchor: [0,-11]
      });
    
      // marker ODP
      const mOdp = L.marker(d.odp, {icon: makePinIcon('odp')})
        .bindPopup(
          `<b>ODP</b><br>${esc(d.odpLabel)}<br>` +
          `${d.odp[0].toFixed(6)}, ${d.odp[1].toFixed(6)}`
        );
      cluster.addLayer(mOdp);
    
      // marker Pelanggan
      const mPel = L.marker(d.pel, {icon: makePinIcon('pel')})
        .bindPopup(
          `<b>Pelanggan</b><br>${esc(d.pelLabel)}<br>` +
          `${d.pel[0].toFixed(6)}, ${d.pel[1].toFixed(6)}`
        );
      cluster.addLayer(mPel);
    
      map.addLayer(cluster);
    
      /* ====== GARIS RUTE: TRIPLE-LAYER ANIMATED DASH ====== */
      const tealCss =
        (getComputedStyle(document.documentElement)
          .getPropertyValue('--es-teal') || '#05d3d2').trim() || '#05d3d2';
    
      if (L.polyline && L.polyline.antPath) {
        // underlay tipis
        L.polyline([d.pel, d.odp], {
          weight: 2,
          opacity: 0.25,
          color: '#020617',
          dashArray: '1,10'
        }).addTo(map);
    
        // utama tosca
        L.polyline.antPath([d.pel, d.odp], {
          paused: false,
          reverse: false,
          delay: 650,
          dashArray: [10, 20],
          weight: 4,
          opacity: 0.95,
          color: tealCss,
          pulseColor: '#e0f2fe',
          hardwareAccelerated: true
        }).addTo(map);
    
        // highlight tipis
        L.polyline.antPath([d.pel, d.odp], {
          paused: false,
          reverse: false,
          delay: 1800,
          dashArray: [2, 26],
          weight: 2,
          opacity: 0.7,
          color: '#0f172a',
          pulseColor: 'rgba(15,23,42,.25)',
          hardwareAccelerated: true
        }).addTo(map);
      } else {
        L.polyline([d.pel, d.odp], {
          weight: 4,
          opacity: 0.9,
          color: tealCss,
          dashArray: '10,20'
        }).addTo(map);
      }
    
      const bounds = cluster.getBounds().isValid()
        ? cluster.getBounds()
        : L.latLngBounds([d.pel, d.odp]);
      map.fitBounds(bounds.pad(0.35), { maxZoom: 25, padding: [24,24] });
    
      // hitung jarak
      const toRad = x => x * Math.PI / 180;
      const hv = (a,b) => {
        const R=6371000, dphi=toRad(b[0]-a[0]), dl=toRad(b[1]-a[1]);
        const phi1=toRad(a[0]), phi2=toRad(b[0]);
        const s=Math.sin(dphi/2)**2 + Math.cos(phi1)*Math.cos(phi2)*Math.sin(dl/2)**2;
        return 2*R*Math.asin(Math.min(1, Math.sqrt(s)));
      };
      const dist = Number.isFinite(d.distance) ? d.distance : hv(d.pel, d.odp);
      const km   = dist >= 1000 ? (dist/1000).toFixed(2)+' km' : dist.toFixed(1)+' m';
    
      // PIN JARAK di tengah
      const midPoint = [
        (d.pel[0] + d.odp[0]) / 2,
        (d.pel[1] + d.odp[1]) / 2
      ];
      const mJarak = L.marker(midPoint, { icon: makePinIcon('jarak') }).addTo(map);
      mJarak.bindTooltip(
        `<span class="sub">Perkiraan jarak lurus</span><br><b>${km}</b>`,
        { direction:'top', offset:[0,-10], className:'leaflet-tooltip tip-pill' }
      );
    
      // badge jarak di pojok kanan atas
      const DistCtl = L.Control.extend({
        onAdd: function(){
          const c = L.DomUtil.create('div','lf-dist');
          c.innerHTML = `<span>Jarak ODP ↔ Pelanggan: <b>${km}</b></span>`;
          return c;
        }, position:'topright'
      });
      map.addControl(new DistCtl());
    
      /* ====== PAKET KECIL BERGERAK SEPANJANG GARIS ====== */
      const packetIcon = L.divIcon({
        className: 'lf-pin lf-pin-packet',
        html: '<span class="pin pin-packet"></span>',
        iconSize: [14, 14],
        iconAnchor: [7, 7]
      });
    
      const packetMarker = L.marker(d.pel, {
        icon: packetIcon,
        interactive: false
      }).addTo(map);
    
      let t = 0;
      (function animatePacket(){
        t += 0.004;
        if (t > 1) t = 0;
        const lat = d.pel[0] + (d.odp[0] - d.pel[0]) * t;
        const lng = d.pel[1] + (d.odp[1] - d.pel[1]) * t;
        packetMarker.setLatLng([lat, lng]);
        requestAnimationFrame(animatePacket);
      })();
    
      /* ====== STREET VIEW (PELANGGAN / ODP) ====== */
      const svFrame = document.getElementById('svFrame');
      const svChip  = document.getElementById('svActiveChip');
      const tabButtons = document.querySelectorAll('.sv-tab');
    
      function setStreetView(target){
        if (!svFrame) return;
        let pt, label;
    
        if (target === 'odp') {
          pt = d.odp; label = 'ODP';
          target = 'odp';
        } else {
          pt = d.pel; label = 'Pelanggan';
          target = 'pel';
        }
        if (!pt || !isFinite(pt[0]) || !isFinite(pt[1])) return;
    
        svFrame.src = svEmbedUrl(pt[0], pt[1]);
        if (svChip) svChip.textContent = label;
    
        tabButtons.forEach(btn => {
          btn.classList.toggle('active', btn.dataset.target === target);
        });
      }
    
      if (svFrame) {
        setStreetView('pel'); // default pelanggan
        tabButtons.forEach(btn => {
          btn.addEventListener('click', () => {
            const target = btn.dataset.target || 'pel';
            setStreetView(target);
          });
        });
      }
    
      /* ====== COPY KOORDINAT ODP / PEL DI FOOTER ====== */
      const copyTxt = async (t)=>{
        try{
          if(navigator.clipboard && window.isSecureContext){
            await navigator.clipboard.writeText(t);
          }else{
            const ta=document.createElement('textarea');
            ta.value=t; document.body.appendChild(ta);
            ta.select(); document.execCommand('copy'); ta.remove();
          }
        }catch(e){ alert('Gagal menyalin'); }
      };
      const btnO = document.getElementById('copyODPx');
      const btnP = document.getElementById('copyPELx');
      if (btnO) btnO.onclick = ()=>copyTxt(`${d.odp[0]}, ${d.odp[1]}`);
      if (btnP) btnP.onclick = ()=>copyTxt(`${d.pel[0]}, ${d.pel[1]}`);
    })();
    </script>
    
    
              <?php endif; ?>
            <?php endif; ?>
    
    
            <!-- SCRIPT: COPY KOORDINAT DI CHIP KOORDINAT -->
            <script>
              (function(){
                const btn = document.getElementById('kopelCopy');
                if (!btn) return;
                btn.addEventListener('click', async () => {
                  const txt = btn.getAttribute('data-copy') || '';
                  try {
                    if (navigator.clipboard && window.isSecureContext) {
                      await navigator.clipboard.writeText(txt);
                    } else {
                      const ta = document.createElement('textarea');
                      ta.value = txt; document.body.appendChild(ta);
                      ta.select(); document.execCommand('copy'); ta.remove();
                    }
                    btn.innerHTML = '<i class="mdi mdi-check"></i>';
                    setTimeout(() => btn.innerHTML = '<i class="mdi mdi-content-copy"></i>', 1200);
                  } catch (e) {
                    alert('Gagal menyalin');
                  }
                });
              })();
            </script>
    
          </div> <!-- /detail-shell -->
    <?php else: ?>
          <div class="alert alert-warning">
            Data order tidak ditemukan. Silakan kembali ke halaman pencarian.
          </div>
    <?php endif; ?>
    
        <div class="mb-2 mt-2">
          <a href="search.php" class="btn btn-sm" style="background-color:#0b7f7e;color:#fff;">
            <i class="mdi mdi-arrow-left"></i> Kembali
          </a>
        </div>
    
      </div><!-- /content-wrapper -->
    
    <?php
    include "footer.php";
    ?>
