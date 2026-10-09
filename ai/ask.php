<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// CORS (opsional)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__.'/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

function out(array $a){
  // tambahkan JSON_UNESCAPED_SLASHES supaya URL referensi tidak di-escape
  echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function tidy(string $t): string {
  return trim(preg_replace("/\r\n|\r/", "\n", $t));
}

/**
 * Paksa brand-voice: ubah “saya/aku/kami/kita” -> efidi (di luar blok kode)
 * - Lebih toleran huruf besar/kecil (case-insensitive)
 * - Tetap skip isi dalam blok ```code```
 */
function normalize_brand_voice(string $s): string {
  $parts = preg_split('/(```[\s\S]*?```)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
  $out = '';
  foreach ($parts as $i => $p) {
    // bagian code → biarkan
    if ($i % 2 === 1) {
      $out .= $p;
      continue;
    }

    // kata ganti dasar (case-insensitive + batas kata)
    $p = preg_replace('/\b(saya)\b/iu', 'efidi', $p);
    $p = preg_replace('/\b(aku)\b/iu',  'efidi', $p);
    $p = preg_replace('/\b(kami)\b/iu', 'efidi', $p);
    $p = preg_replace('/\b(kita)\b/iu', 'efidi', $p);

    // frasa khusus
    $p = preg_replace(
      '/\b(Ada yang bisa)\s+(?:saya|aku|kami|kita)\s+(bantu)\b/iu',
      '$1 efidi $2',
      $p
    );
    $p = preg_replace(
      '/\b(Biarkan)\s+(?:saya|aku|kami|kita)\s+(jelaskan)\b/iu',
      'Baik, efidi akan menjelaskan',
      $p
    );

    $out .= $p;
  }
  return $out;
}

function call_openrouter(string $prompt, string $model, array $opt=[]): array {
  if (!defined('OPENROUTER_KEY') || OPENROUTER_KEY==='') {
    return ['ok'=>false,'rc'=>0,'body'=>'','err'=>'OPENROUTER key kosong'];
  }

  $payload = [
    'model'    => $model,
    'messages' => array_values(array_filter([
      isset($opt['system']) ? ['role'=>'system','content'=>$opt['system']] : null,
      ['role'=>'user','content'=>$prompt],
    ])),
    'max_tokens'  => $opt['max_tokens']  ?? 800,
    'temperature' => $opt['temperature'] ?? 0.2,
  ];

  $site = defined('OPENROUTER_SITE')
    ? OPENROUTER_SITE
    : (isset($_SERVER['HTTP_HOST']) ? 'https://'.$_SERVER['HTTP_HOST'] : 'https://efidi.montaklo.id');

  $ch = curl_init(OPENROUTER_API_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Authorization: Bearer '.OPENROUTER_KEY,
      'HTTP-Referer: '.$site,
      'X-Title: efidi.AI',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
  ]);
  $res = curl_exec($ch);
  $rc  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = $res===false ? curl_error($ch) : '';
  curl_close($ch);

  if ($rc >= 200 && $rc < 300) {
    $j = json_decode((string)$res, true);
    $choice = $j['choices'][0] ?? [];
    $text   = $choice['message']['content'] ?? '';
    $finish = $choice['finish_reason']      ?? '';

    return [
      'ok'     => true,
      'text'   => $text,
      'model'  => $model,
      'finish' => $finish,   // length / stop / dll
      'raw'    => $j,
    ];
  }

  return ['ok'=>false,'rc'=>$rc,'body'=>$res,'err'=>$err];
}

/**
 * Wrapper pintar:
 * - Bisa auto-continue kalau jawaban kepotong (finish_reason = "length")
 * - Menggabungkan beberapa part jadi satu teks panjang
 */
function smart_answer(string $q, string $model, array $opt = []): array {
  $maxParts = defined('KODEE_MAX_PARTS') ? (int) KODEE_MAX_PARTS : 1;
  if ($maxParts < 1) $maxParts = 1;

  $combined = '';
  $finish   = '';
  $raw      = null;

  for ($i = 0; $i < $maxParts; $i++) {
    // Part pertama: kirim pertanyaan asli
    if ($i === 0) {
      $prompt = $q;
    } else {
      // Part lanjutan (dalam 1 request) → minta model melanjutkan
      $tail = mb_substr($combined, -1200);
      $prompt = "Lanjutkan jawaban panjang sebelumnya tanpa mengulang kalimat yang sudah ada.\n\n"
              . "Berikut bagian terakhir jawabanmu:\n\"{$tail}\"\n\n"
              . "Lanjutkan langsung isi jawaban (jangan menulis pengantar baru).";
    }

    $r = call_openrouter($prompt, $model, $opt);
    if (!$r['ok']) {
      // Kalau part pertama gagal → langsung balikin error ke caller
      if ($i === 0) {
        return $r;
      }
      // Kalau part lanjutan gagal → stop, tapi simpan teks yang sudah ada
      break;
    }

    $piece = (string)($r['text'] ?? '');
    if ($piece !== '') {
      $combined .= ($combined === '' ? '' : "\n\n") . $piece;
    }

    $finish = (string)($r['finish'] ?? '');
    $raw    = $r['raw'] ?? null;

    // Kalau model merasa sudah selesai → stop loop
    if ($finish !== 'length') {
      break;
    }
  }

  return [
    'ok'     => $combined !== '',
    'text'   => $combined,
    'model'  => $model,
    'finish' => $finish,
    'raw'    => $raw,
  ];
}

/* ====== HELPER CONTEXT (untuk LANJUTKAN) ====== */
function save_last_ai_context(string $q, string $answer, string $model, string $finish): void {
  $_SESSION['efidi_ai_last'] = [
    'q'      => $q,
    'answer' => $answer,
    'model'  => $model,
    'finish' => $finish,
    'time'   => time(),
  ];
}

function get_last_ai_context(): ?array {
  return $_SESSION['efidi_ai_last'] ?? null;
}

/**
 * Lanjutkan jawaban sebelumnya dengan model yang sama.
 * Mengembalikan HANYA bagian lanjutan (bukan jawaban penuh dari awal).
 */
function continue_answer(string $model, array $opt = []): array {
  $ctx = get_last_ai_context();
  if (!$ctx || ($ctx['model'] ?? '') !== $model) {
    return ['ok'=>false,'rc'=>0,'body'=>'','err'=>'no_context'];
  }

  $prev = (string)($ctx['answer'] ?? '');
  if ($prev === '') {
    return ['ok'=>false,'rc'=>0,'body'=>'','err'=>'empty_context'];
  }

  $tail = mb_substr($prev, -1200);
  $prompt = "Lanjutkan jawaban panjang sebelumnya tanpa mengulang kalimat yang sudah ada.\n\n"
          . "Berikut bagian terakhir jawabanmu:\n\"{$tail}\"\n\n"
          . "Lanjutkan langsung isi jawaban (jangan menulis pengantar baru atau rekap).";

  $r = call_openrouter($prompt, $model, $opt);
  if (!$r['ok']) {
    return $r;
  }

  $piece  = (string)($r['text'] ?? '');
  $finish = (string)($r['finish'] ?? '');
  $raw    = $r['raw'] ?? null;

  // Normalisasi brand-voice untuk potongan baru
  $pieceNorm = normalize_brand_voice($piece);

  // Update konteks penuh di session
  $combined = $prev;
  if ($pieceNorm !== '') {
    $combined .= ($combined === '' ? '' : "\n\n") . $pieceNorm;
  }

  save_last_ai_context(
    (string)($ctx['q'] ?? ''),
    $combined,
    $model,
    $finish
  );

  return [
    'ok'     => $pieceNorm !== '',
    'text'   => $pieceNorm,
    'model'  => $model,
    'finish' => $finish,
    'raw'    => $raw,
  ];
}

/* ===== INPUT ===== */
$q = tidy((string)($_POST['q'] ?? ($_GET['q'] ?? '')));
if ($q === '') {
  $raw = file_get_contents('php://input');
  if ($raw) {
    $j = json_decode($raw, true);
    if (isset($j['q'])) {
      $q = tidy((string)$j['q']);
    }
  }
}
if ($q === '') {
  out(['reply'=>'Pertanyaannya kosong.']);
}

$isContinue = (
  (isset($_POST['continue']) && $_POST['continue'] === '1') ||
  (isset($_GET['continue'])  && $_GET['continue']  === '1')
);

/* ===== MODEL LIST ===== */
$models = array_unique(array_filter([
  defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : null,

  // --- DAFTAR MODEL UTAMA (tidak diubah strukturnya, hanya bisa diurutkan/tambah sendiri di config) ---
  'deepseek/deepseek-chat',
  'deepseek/deepseek-coder',
  'deepseek/deepseek-v2',
  'deepseek/deepseek-v2-lite',
  'deepseek/deepseek-math',
  'deepseek/deepseek-llm-67b-chat',

  // GOOGLE/GEMINI MODELS
  'google/gemini-2.0-flash-exp',
  'google/gemini-2.0-flash-thinking-exp',
  'google/gemini-1.5-flash',
  'google/gemini-1.5-flash-8b',
  'google/gemini-1.5-pro',
  'google/gemini-1.0-pro',

  // META/LLAMA MODELS
  'meta-llama/llama-3.3-70b-instruct',
  'meta-llama/llama-3.1-8b-instruct',
  'meta-llama/llama-3.1-70b-instruct',
  'meta-llama/llama-3.2-1b-instruct',
  'meta-llama/llama-3.2-3b-instruct',
  'meta-llama/llama-3-8b-instruct',
  'meta-llama/llama-3-70b-instruct',
  'meta-llama/llama-2-13b-chat',
  'meta-llama/llama-2-70b-chat',

  // MICROSOFT MODELS
  'microsoft/wizardlm-2-8x22b',
  'microsoft/wizardlm-2-7b',
  'microsoft/orca-2-13b',

  // MISTRAL AI MODELS
  'mistralai/mistral-7b-instruct',
  'mistralai/mistral-7b-instruct-v0.3',
  'mistralai/mixtral-8x7b-instruct',
  'mistralai/mixtral-8x22b-instruct',

  // QWEN MODELS
  'qwen/qwen-2.5-72b-instruct',
  'qwen/qwen-2.5-7b-instruct',
  'qwen/qwen-2-7b-instruct',
  'qwen/qwen-2-72b-instruct',

  // CODING SPECIFIC MODELS
  'codellama/codellama-34b-instruct',
  'codellama/codellama-13b-instruct',
  'codellama/codellama-7b-instruct',

  // OTHER POPULAR FREE MODELS
  'huggingfaceh4/zephyr-7b-beta',
  'huggingfaceh4/zephyr-orpo-141b-a35b',
  'nousresearch/nous-hermes-2-mixtral-8x7b-dpo',
  'nousresearch/nous-hermes-2-vision-7b',
  'openchat/openchat-7b',
  'openchat/openchat-8b',
  'teknium/openhermes-2.5-mistral-7b',
  'undi95/remm-slerp-l2-13b',
  'cognitivecomputations/dolphin-2.9.2-mistral-7b',
  'cognitivecomputations/dolphin-2.9.2-qwen-7b',
  'jondurbin/airoboros-l2-70b',
  'gryphe/mythomax-l2-13b',

  // RESEARCH & ACADEMIC MODELS
  'allenai/olmo-7b',
  'allenai/olmo-7b-1124-instruct',
  'tiiuae/falcon-7b-instruct',
  'tiiuae/falcon-40b-instruct',
  'togethercomputer/stripedhyena-nous-7b',

  // ASIAN LANGUAGE OPTIMIZED
  'sophosympatheia/midnight-rose-70b',
  'sao10k/fimbulvetr-11b-v2',

  // MULTIMODAL MODELS (jika butuh image/text)
  'liuhaotian/llava-13b',
  'liuhaotian/llava-1.5-7b',
  'openbmb/minicpm-v',

  'openrouter/auto',
]));

/* ===== System Prompt dari config + knowledge file (opsional) ===== */

// ringkasan default kalau KODEE_SYS_PROMPT tidak didefinisikan
$defaultSys = <<<'EFIDI_SYS'
You are efidi.AI, asisten AI resmi untuk platform efidi.

Tugas utama:
- Membantu pengguna memahami dan mengerjakan hal-hal teknis maupun non-teknis terkait efidi dan topik umum.
- Menjawab dengan bahasa Indonesia yang sopan, jelas, dan mudah dimengerti, kecuali jika pengguna menggunakan bahasa lain.
- Menjaga jawaban tetap ringkas namun lengkap, terstruktur, dan langsung ke inti.
EFIDI_SYS;

// tambahan aturan supaya jawaban lebih pintar + ada referensi & gambar referensi
$extraSys = <<<'EFIDI_EXTRA'
[STYLE]
- Posisikan diri sebagai asisten tingkat lanjut sekelas model GPT generasi terbaru (setara GPT-5.1).
- Gunakan kata ganti "efidi" ketika merujuk pada dirimu sendiri (bukan saya/aku/kami/kita).
- Jawab dengan bahasa Indonesia yang sopan, jelas, natural, analitis, dan kreatif.
- Jangan gunakan markdown dekoratif seperti **tebal**, _miring_, atau heading #.
- Jawaban cukup berupa paragraf biasa, daftar bernomor (1., 2., 3.), dan bullet (•), plus blok kode.

[FOCUS & RELEVANSI]
- Baca pertanyaan minimal dua kali lalu simpulkan apa kebutuhan utama pengguna.
- Jawaban harus langsung ke inti dulu (1 paragraf ringkas), baru detail pendukung.
- Jika pertanyaan ambigu, boleh ajukan 1–3 pertanyaan klarifikasi singkat.
- Jika pengguna tidak menjawab klarifikasi, tetap berikan jawaban terbaik berdasarkan asumsi paling wajar.

[REASONING]
- Untuk logika, matematika, debugging, atau desain sistem:
  - Lakukan penalaran langkah demi langkah secara internal.
  - Tampilkan hanya ringkasan langkah penting yang bermanfaat (bukan log berpikir mentah yang sangat panjang).
  - Double-check hasil hitungan, kode, atau argumen sebelum jawaban final.

[FORMAT JAWABAN]
Struktur ideal:
1. Ringkasan singkat (1–3 kalimat inti).
2. Penjelasan / langkah-langkah utama, terstruktur.
3. (Opsional) Contoh kode / query / konfigurasi bila relevan.
4. (Opsional) Tips tambahan, edge case, atau peringatan penting.
5. Jika jawaban akan sangat panjang dan mendekati limit, prioritaskan poin terpenting dulu dan boleh menutup dengan:
   - "Kalau mau efidi lanjutkan bagian lain yang lebih detail, tulis saja: LANJUTKAN."

[REFERENSI]
- Jika topiknya fakta/ilmu/teknis dan pengguna tampak butuh rujukan, tambahkan bagian "Referensi:" di akhir secara singkat.
- Cantumkan 1–3 sumber tepercaya (misalnya dokumentasi resmi, Wikipedia).
- Jangan mengarang URL yang terlalu spesifik jika tidak yakin.

[GAMBAR REFERENSI]
- Hanya jika benar-benar membantu, tambahkan ide "gambar referensi" dalam bentuk teks (misalnya: "diagram alur request Telegram bot ke webhook efidi").
EFIDI_EXTRA;


// load knowledge (opsional)
$efidi_brief = @file_get_contents(__DIR__.'/data/efidi_knowledge.md');
$efidi_brief = $efidi_brief ? mb_substr($efidi_brief, 0, 4000) : '';

// gabungkan: config → default → extra → knowledge
$baseSys = defined('KODEE_SYS_PROMPT') ? KODEE_SYS_PROMPT : $defaultSys;

$sys = $baseSys
     . "\n\n"
     . $extraSys
     . ($efidi_brief ? "\n\n[EFIDI_KNOWLEDGE]\n".$efidi_brief : '');

$opt = [
  'system'      => $sys,
  'max_tokens'  => defined('KODEE_MAX_TOKENS')  ? KODEE_MAX_TOKENS  : 2048,
  'temperature' => defined('KODEE_TEMPERATURE') ? KODEE_TEMPERATURE : 0.15,
];

/* ===== Jalankan ===== */

/* MODE LANJUTKAN: kalau ada context terakhir di session */
if ($isContinue) {
  $ctx = get_last_ai_context();
  if ($ctx && !empty($ctx['model'])) {
    $mdl = (string)$ctx['model'];
    $r   = continue_answer($mdl, $opt);

    if ($r['ok']) {
      $text = $r['text'] ?? '';
      $payload = [
        'reply'    => $text,
        'provider' => 'openrouter',
        'model'    => $mdl,
        'finish'   => $r['finish'] ?? null,
      ];
      if (($r['finish'] ?? '') === 'length') {
        $payload['note'] = 'Jawaban masih bisa dilanjutkan. Klik LANJUTKAN untuk meneruskan.';
      }
      out($payload);
    }

    // kalau gagal lanjut, teruskan ke mode normal (di bawah)
  }
}

/* MODE NORMAL: pertanyaan baru */
foreach ($models as $mdl) {
  $r = smart_answer($q, $mdl, $opt);

  if ($r['ok']) {
    // normalisasi brand-voice
    $text = normalize_brand_voice($r['text'] ?? '');

    // simpan konteks terakhir untuk fitur LANJUTKAN
    save_last_ai_context($q, $text, $mdl, (string)($r['finish'] ?? ''));

    $payload = [
      'reply'    => $text,
      'provider' => 'openrouter',
      'model'    => $mdl,
      'finish'   => $r['finish'] ?? null,
    ];

    if (($r['finish'] ?? '') === 'length') {
      $payload['note'] = 'Jawaban sangat panjang dan mungkin masih bisa dilanjutkan. Klik LANJUTKAN untuk meneruskan.';
    }

    out($payload);
  }

  // log error jika gagal untuk model ini
  @file_put_contents(
    __DIR__.'/var/ask_error.log',
    date('c')." [openrouter rc=".($r['rc'] ?? '-')." mdl={$mdl}] ".($r['body'] ?? '')."\n",
    FILE_APPEND
  );
}

/* ===== Semua gagal ===== */
out(['reply'=>'Maaf, semua model sedang penuh / tidak tersedia. Coba lagi sebentar lagi ya.']);
