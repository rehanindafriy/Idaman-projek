<?php
declare(strict_types=1);

/**
 * bot.php - ENTRY POINT (ROUTER)
 * Memanggil semua komponen secara modular
 */

// Sertakan modul-modul sistem
require_once __DIR__ . '/config.php';
set_time_limit(0); // WAJIB ada di entry point agar proses async tidak dihentikan paksa setelah 30 detik
ignore_user_abort(true); // WAJIB untuk server Hostinger/LiteSpeed agar PHP tidak mati saat koneksi cURL diputus (async)
require_once __DIR__ . '/utils.php';
$configFile = __DIR__ . '/bot_config_live.json';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/bot_config.json';
}
$botConfig = json_decode(file_get_contents($configFile), true);
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/image_processor.php';
require_once __DIR__ . '/tasks.php';
require_once __DIR__ . '/scheduler.php';
require_once __DIR__ . '/webhook.php';

// Fungsi khusus untuk memproses antrean dari file .bot_imjas_queue.txt
function process_queue(): void {
    if (!file_exists(IMJAS_QUEUE_FILE)) return;
    
    $json = @file_get_contents(IMJAS_QUEUE_FILE);
    if (!$json) return;
    
    $queue = json_decode($json, true);
    if (!empty($queue) && is_array($queue)) {
        // Ambil ID pertama dari antrean
        $chat_id = array_shift($queue);
        
        // Simpan sisa antrean
        @file_put_contents(IMJAS_QUEUE_FILE, json_encode($queue));
        
        log_info("Memproses antrean /start tertunda untuk chat_id: {$chat_id}");
        run_full_task([$chat_id]);
    }
}


// =====================================================
//  MAIN ENTRY: MODE CLI (CRON) & HTTP (WEBHOOK)
// =====================================================

// 1. MODE CLI (Dipanggil otomatis oleh Cron Job Hostinger)
if (PHP_SAPI === 'cli') {
    $mode = $argv[1] ?? 'cron';

    if ($mode === 'cron') {
        log_info("🚀 Cron tick dimulai...");
        setup_schedule();
        setup_schedule_yanti();
        setup_report_schedules();
        run_scheduler_tick();
        process_queue(); // PROSES ANTREAN /start JIKA ADA
        log_info(get_next_schedule_info());
    } elseif ($mode === 'run-full') {
        $chat_id = $argv[2] ?? null;
        log_info("🚀 Manual run_full_task dari CLI..." . ($chat_id ? " (Dipanggil oleh {$chat_id})" : ""));
        run_full_task($chat_id ? [$chat_id] : null);
    } elseif ($mode === 'run-imjas') {
        log_info("🚀 Manual run_capture_imjas_dan_kliring dari CLI...");
        run_capture_imjas_dan_kliring(null);
    }

    exit;
}

// 2. MODE HTTP CRON (Jika Cron Job memanggil via HTTP URL)
$httpMode = $_GET['mode'] ?? ($_GET['cron'] ?? null);
if ($httpMode === 'cron' || $httpMode === '1') {
    // Timeout dipanjangkan karena cron HTTP masih kena limit LiteSpeed
    @set_time_limit(0);
    @ignore_user_abort(true);
    
    log_info("🚀 Cron tick (HTTP) dimulai...");
    setup_schedule();
    setup_schedule_yanti();
    setup_report_schedules();
    run_scheduler_tick();
    process_queue(); // PROSES ANTREAN /start JIKA ADA
    echo "CRON_OK";
    exit;
}

// 3. MODE ASYNC HTTP STEP BY STEP (Bypass Limit Hostinger 60s)
if (isset($_GET['async_step'])) {
    close_connection_and_continue();
    $step = (int)$_GET['async_step'];
    $chat_ids = null;
    if (!empty($_GET['chat_ids'])) {
        $chat_ids = json_decode(urldecode($_GET['chat_ids']), true);
    }
    $group = $_GET['group'] ?? 'main';
    
    // Jangan batasi timeout (kalau bisa)
    @set_time_limit(0);
    @ignore_user_abort(true);
    
    // Tutup koneksi agar yang panggil (cURL) tidak nunggu
    ob_start();
    echo "ASYNC_OK";
    header('Connection: close');
    header('Content-Length: ' . ob_get_length());
    ob_end_flush();
    @ob_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    log_info("Eksekusi Async Step {$step} untuk group {$group}...");
    run_dynamic_task($group, $chat_ids, $step);
    exit;
}

// 4. MODE HTTP WEBHOOK (Dipanggil otomatis oleh Telegram saat ada pesan masuk)
$raw = file_get_contents('php://input');
if (!empty($raw)) {
    $update = json_decode($raw, true);
    if (is_array($update)) {
        // Anti-Spam: Cek apakah update_id ini sudah pernah diproses
        $updateId = $update['update_id'] ?? 0;
        $dedupFile = __DIR__ . '/.last_update_id';
        $lastId = (int)@file_get_contents($dedupFile);
        if ($updateId > 0 && $updateId <= $lastId) {
            // Sudah pernah diproses, skip (Telegram retry)
            http_response_code(200);
            echo 'OK';
            exit;
        }
        @file_put_contents($dedupFile, (string)$updateId);

        // Balas HTTP 200 ke Telegram secepat mungkin agar tidak nyangkut
        ob_start();
        echo "OK";
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        @ob_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Jalankan webhook (hanya set antrean, bukan eksekusi berat)
        handle_telegram_update($update);
        exit;
    }
}

echo "OK - Bot Modular Aktif";
