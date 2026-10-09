<?php
declare(strict_types=1);

// =====================================================
//  GLOBAL STATE
// =====================================================
$is_running    = false;
$user_states   = []; 
$scheduledJobs = []; 

$schedules_data = [
    '02:59' => ['active' => true, 'id' => 'schedule_1'],
    '05:59' => ['active' => true, 'id' => 'schedule_2'],
    '09:59' => ['active' => true, 'id' => 'schedule_3'],
    '11:59' => ['active' => true, 'id' => 'schedule_4'],
    '14:59' => ['active' => true, 'id' => 'schedule_5'],
];

$schedules_yanti = [
    '02:59' => ['active' => true, 'id' => 'yanti_1'],
    '05:59' => ['active' => true, 'id' => 'yanti_2'],
    '07:59' => ['active' => true, 'id' => 'yanti_3'],
    '11:59' => ['active' => true, 'id' => 'yanti_4'],
];

$YANTI_REMINDER_TEXT = 'Mba Yanti Moban Update 📝@rolimartin @JackSpaarroww @YantiMohadi @alibassss';
const ENABLE_YANTI_REMINDER = false;

const IMJAS_STATE_FILE = __DIR__ . '/.bot_imjas_state.json';
const IMJAS_LOCK_FILE  = __DIR__ . '/.bot_imjas_running.lock';
const IMJAS_CANCEL_FILE= __DIR__ . '/.bot_imjas_cancel.flag';
const IMJAS_QUEUE_FILE = __DIR__ . '/.bot_imjas_queue.txt';
const IMJAS_GROUP_QUEUE_FILE = __DIR__ . '/.bot_imjas_group_queue.txt';
const IMJAS_LOCK_TTL   = 300; 

function load_imjas_state(): void
{
    global $user_states, $schedules_data, $schedules_yanti, $YANTI_REMINDER_TEXT;
    if (!file_exists(IMJAS_STATE_FILE)) return;
    $json = @file_get_contents(IMJAS_STATE_FILE);
    if ($json === false) return;
    $data = json_decode($json, true);
    if (!is_array($data)) return;
    
    if (isset($data['user_states']) && is_array($data['user_states'])) $user_states = $data['user_states'];
    if (isset($data['schedules_data']) && is_array($data['schedules_data'])) $schedules_data = $data['schedules_data'];
    if (isset($data['schedules_yanti']) && is_array($data['schedules_yanti'])) $schedules_yanti = $data['schedules_yanti'];
    if (!empty($data['yanti_text']) && is_string($data['yanti_text'])) $YANTI_REMINDER_TEXT = $data['yanti_text'];
}

function save_imjas_state(): void
{
    global $user_states, $schedules_data, $schedules_yanti, $YANTI_REMINDER_TEXT;
    $payload = [
        'user_states'     => $user_states,
        'schedules_data'  => $schedules_data,
        'schedules_yanti' => $schedules_yanti,
        'yanti_text'      => $YANTI_REMINDER_TEXT,
    ];
    @file_put_contents(IMJAS_STATE_FILE, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function is_task_locked(): bool {
    if (!file_exists(IMJAS_LOCK_FILE)) return false;
    $mtime = @filemtime(IMJAS_LOCK_FILE);
    if ($mtime === false) {
        @unlink(IMJAS_LOCK_FILE);
        return false;
    }
    if ((time() - $mtime) > IMJAS_LOCK_TTL) {
        @unlink(IMJAS_LOCK_FILE);
        return false;
    }
    return true;
}

function set_task_lock(): void { @file_put_contents(IMJAS_LOCK_FILE, (string) time()); }
function clear_task_lock(): void { if (file_exists(IMJAS_LOCK_FILE)) @unlink(IMJAS_LOCK_FILE); }

function request_task_cancel(?string $by_chat_id = null): void {
    $payload = ['by_chat_id' => $by_chat_id, 'time' => time()];
    @file_put_contents(IMJAS_CANCEL_FILE, json_encode($payload));
}

function clear_task_cancel(): void { if (file_exists(IMJAS_CANCEL_FILE)) @unlink(IMJAS_CANCEL_FILE); }

function get_cancel_info(): ?array {
    if (!file_exists(IMJAS_CANCEL_FILE)) return null;
    $json = @file_get_contents(IMJAS_CANCEL_FILE);
    if ($json === false) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function is_task_cancel_requested(): bool {
    if (!file_exists(IMJAS_CANCEL_FILE)) return false;
    $mtime = @filemtime(IMJAS_CANCEL_FILE);
    if ($mtime === false) {
        clear_task_cancel();
        return false;
    }
    if ((time() - $mtime) > IMJAS_LOCK_TTL) {
        clear_task_cancel();
        return false;
    }
    return true;
}

function queue_add_task(string $chat_id): void {
    $current = [];
    if (file_exists(IMJAS_QUEUE_FILE)) {
        $json = @file_get_contents(IMJAS_QUEUE_FILE);
        if ($json) $current = json_decode($json, true) ?: [];
    }
    if (!in_array($chat_id, $current)) {
        $current[] = $chat_id;
        @file_put_contents(IMJAS_QUEUE_FILE, json_encode($current));
    }
}

function queue_add_group(string $groupName): void {
    $current = [];
    if (file_exists(IMJAS_GROUP_QUEUE_FILE)) {
        $json = @file_get_contents(IMJAS_GROUP_QUEUE_FILE);
        if ($json) $current = json_decode($json, true) ?: [];
    }
    if (!in_array($groupName, $current)) {
        $current[] = $groupName;
        @file_put_contents(IMJAS_GROUP_QUEUE_FILE, json_encode($current));
    }
}

function queue_pop_group(): ?string {
    if (!file_exists(IMJAS_GROUP_QUEUE_FILE)) return null;
    $json = @file_get_contents(IMJAS_GROUP_QUEUE_FILE);
    if (!$json) return null;
    $current = json_decode($json, true) ?: [];
    if (empty($current)) return null;
    
    $next = array_shift($current);
    @file_put_contents(IMJAS_GROUP_QUEUE_FILE, json_encode($current));
    return $next;
}


// LOGGING
function log_info(string $msg): void {
    $line = date('Y-m-d H:i:s') . " [INFO]  {$msg}\n";
    if (PHP_SAPI === 'cli' && defined('STDOUT')) fwrite(STDOUT, $line);
    @file_put_contents(__DIR__ . '/runtime-bot_imjas.log', $line, FILE_APPEND);
}

function log_warn(string $msg): void {
    $line = date('Y-m-d H:i:s') . " [WARN]  {$msg}\n";
    if (PHP_SAPI === 'cli' && defined('STDOUT')) fwrite(STDOUT, $line);
    @file_put_contents(__DIR__ . '/runtime-bot_imjas.log', $line, FILE_APPEND);
}

function log_error(string $msg): void {
    $line = date('Y-m-d H:i:s') . " [ERROR] {$msg}\n";
    if (PHP_SAPI === 'cli' && defined('STDERR')) fwrite(STDERR, $line);
    @file_put_contents(__DIR__ . '/runtime-bot_imjas.log', $line, FILE_APPEND);
}

// HELPER WAKTU
function wib_to_utc(string $wib_time_str): string {
    try {
        $dt = DateTime::createFromFormat('H:i', $wib_time_str, new DateTimeZone('Asia/Jakarta'));
        if (!$dt) return $wib_time_str;
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('H:i');
    } catch (Throwable $e) { return $wib_time_str; }
}

function utc_to_wib(string $utc_time_str): string {
    try {
        $dt = DateTime::createFromFormat('H:i', $utc_time_str, new DateTimeZone('UTC'));
        if (!$dt) return $utc_time_str;
        $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
        return $dt->format('H:i');
    } catch (Throwable $e) { return $utc_time_str; }
}

function format_time_with_wib(string $utc_time_str): string {
    $wib = utc_to_wib($utc_time_str);
    return sprintf('`%s` UTC (`%s` WIB)', $utc_time_str, $wib);
}

function validate_time_format(string $time_str): bool {
    $dt = DateTime::createFromFormat('H:i', $time_str);
    return $dt && $dt->format('H:i') === $time_str;
}

// BLAZING FAST BACKGROUND TASKS VIA ASYNC HTTP (Bypass 60s Hostinger Limit)
function fire_async_step(int $step, ?array $chat_ids = null, string $group = 'main'): void {
    // Selalu paksa pakai https agar tidak kena redirect 301 dari http ke https
    $protocol = "https";
    $host = $_SERVER['HTTP_HOST'] ?? 'efidi.montaklo.id';
    $path = explode('?', $_SERVER['REQUEST_URI'] ?? '/idaman/bot.php')[0];
    if (strpos($path, 'bot.php') === false) {
        $path = '/idaman/bot.php'; 
    }
    
    $url = $protocol . "://" . $host . $path . "?async_step=" . $step . "&group=" . urlencode($group);
    if (!empty($chat_ids)) {
        $url .= "&chat_ids=" . urlencode(json_encode($chat_ids));
    }

    log_info("Menembakkan Async Step {$step} ke: {$url}");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Naikkan jadi 3 detik agar server sempat menutup koneksi dengan rapi
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

// FUNGSI SAKTI: Memutus koneksi klien tanpa mematikan script (Anti LiteSpeed Kill)
function close_connection_and_continue() {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }
    
    // Fallback untuk LiteSpeed/Apache
    @ob_end_clean();
    header("Connection: close\r\n");
    header("Content-Encoding: none\r\n");
    header("Content-Length: 0");
    ob_start();
    echo "";
    ob_end_flush();
    @ob_flush();
    @flush();
    
    if(function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }
}

load_imjas_state();

// MEMBACA KONFIGURASI DINAMIS (DARI WEB ADMIN) DENGAN AMAN
function get_dynamic_config(): array {
    $file = __DIR__ . '/bot_config_live.json';
    if (!file_exists($file)) {
        $file = __DIR__ . '/bot_config.json';
    }
    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }
    }
    return [];
}
