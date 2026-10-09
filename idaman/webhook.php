<?php
declare(strict_types=1);

// =====================================================
//  COMMAND HANDLERS
// =====================================================

function handle_showtime($chat_id): void {
    global $schedules_data;
    $active   = [];
    $inactive = [];
    foreach ($schedules_data as $time_str => $data) {
        if ($data['active']) $active[] = $time_str;
        else $inactive[] = $time_str;
    }

    $msg = "📋 **STATUS JADWAL**\n\n";
    if (!empty($active)) {
        $msg .= "✅ **Jadwal Aktif:**\n";
        sort($active);
        foreach ($active as $t) $msg .= "⏰ " . format_time_with_wib($t) . "\n";
        $msg .= "\n📊 Total aktif: " . count($active) . "\n";
    }
    if (!empty($inactive)) {
        $msg .= "\n❌ **Jadwal Nonaktif:**\n";
        sort($inactive);
        foreach ($inactive as $t) $msg .= "⏰ " . format_time_with_wib($t) . "\n";
        $msg .= "\n📊 Total nonaktif: " . count($inactive) . "\n";
    }

    $dyn = get_dynamic_config();
    $specific_schedules = [];
    if (!empty($dyn['reports'])) {
        foreach ($dyn['reports'] as $reportId => $report) {
            $schedules = $report['schedules'] ?? [];
            if (!empty($schedules)) {
                $specific_schedules[] = "🔹 *{$report['name']}* -> " . implode(', ', $schedules) . " WIB";
            }
        }
    }

    if (!empty($specific_schedules)) {
        $msg .= "\n🌐 **Jadwal Khusus (Web Admin):**\n";
        foreach ($specific_schedules as $s) {
            $msg .= $s . "\n";
        }
    }

    if (empty($active) && empty($inactive) && empty($specific_schedules)) {
        $msg = "❌ Tidak ada jadwal yang terkonfigurasi.";
    } else {
        $msg .= "\n" . get_next_schedule_info();
    }
    
    $msg .= "\n\n⚠️ *Peringatan Zona Waktu Looker Studio:*\nBot server menggunakan waktu UTC (Selisih 7 Jam dengan WIB). Screenshot otomatis sebelum jam 07:00 WIB pagi akan terbaca sebagai 'Hari Kemarin' oleh Looker Studio.";

    send_message($chat_id, $msg);
}

function handle_stop($chat_id): void {
    if (!is_task_locked()) {
        send_message($chat_id, "ℹ️ Tidak ada proses pengambilan screenshot yang sedang berjalan.");
        return;
    }
    request_task_cancel((string) $chat_id);
    send_message($chat_id, "⏹️ Permintaan *STOP* diterima.\nProses pengambilan screenshot yang sedang berjalan akan dihentikan secepatnya.");
}

function handle_settime($chat_id): void {
    global $schedules_data, $user_states;
    if (empty($schedules_data)) {
        $user_states[$chat_id] = ['action' => 'settime_input', 'old_time' => null];
        send_message($chat_id, "➕ **TAMBAH JADWAL BARU**\n\nAnda belum memiliki jadwal. Masukkan waktu untuk jadwal baru:\nFormat: `HH:MM` (contoh: `16:30`)\n\n*Catatan: Masukkan waktu dalam **WIB** - bot akan otomatis mengkonversi ke UTC*");
        save_imjas_state();
        return;
    }

    $buttons = [];
    ksort($schedules_data);
    foreach ($schedules_data as $time_str => $data) {
        $status = $data['active'] ? '✅' : '❌';
        $wib    = utc_to_wib($time_str);
        $buttons[] = [['text' => "{$status} {$wib} WIB ({$time_str} UTC)", 'callback_data' => "edit_{$time_str}"]];
    }
    $buttons[] = [['text' => '➕ Tambah Jadwal Baru', 'callback_data' => 'add_new']];
    $buttons[] = [['text' => '❌ Batal', 'callback_data' => 'cancel']];

    send_message($chat_id, "⚙️ **PENGATURAN JADWAL**\n\nPilih jadwal yang ingin diubah/aktifkan/nonaktifkan atau tambah jadwal baru:\n\n*Catatan: Input waktu dalam **WIB** - ditampilkan WIB (UTC)*", create_inline_keyboard($buttons));
    $user_states[$chat_id] = ['action' => 'settime_select'];
    save_imjas_state();
}

function handle_deltime($chat_id): void {
    global $schedules_data, $user_states;
    $active = [];
    foreach ($schedules_data as $time_str => $data) {
        if ($data['active']) $active[] = $time_str;
    }
    if (empty($active)) {
        send_message($chat_id, "❌ Tidak ada jadwal aktif untuk dihapus.");
        return;
    }

    $buttons = [];
    sort($active);
    foreach ($active as $t) {
        $wib = utc_to_wib($t);
        $buttons[] = [['text' => "🗑️ {$wib} WIB ({$t} UTC)", 'callback_data' => "delete_{$t}"]];
    }
    $buttons[] = [['text' => '❌ Batal', 'callback_data' => 'cancel']];

    send_message($chat_id, "🗑️ **HAPUS JADWAL**\n\nPilih jadwal yang ingin dihapus:\n\n*Catatan: Ditampilkan WIB (UTC)*", create_inline_keyboard($buttons));
    $user_states[$chat_id] = ['action' => 'deltime_select'];
    save_imjas_state();
}

function handle_time_input($chat_id, string $time_input): void {
    global $user_states, $schedules_data;
    if (!validate_time_format($time_input)) {
        send_message($chat_id, "❌ Format waktu tidak valid! Gunakan format `HH:MM` (contoh: `16:30`)\n\n*Catatan: Masukkan waktu dalam **WIB** - bot akan otomatis mengkonversi ke UTC*");
        return;
    }

    $state        = $user_states[$chat_id] ?? [];
    $old_time_utc = $state['old_time'] ?? null;
    $new_time_utc = wib_to_utc($time_input);

    if ($old_time_utc) {
        if (!isset($schedules_data[$old_time_utc])) {
            send_message($chat_id, "❌ Jadwal tidak ditemukan.");
            unset($user_states[$chat_id]);
            return;
        }
        $old_time_wib = utc_to_wib($old_time_utc);
        if ($time_input === $old_time_wib) {
            $schedules_data[$old_time_utc]['active'] = !$schedules_data[$old_time_utc]['active'];
            $status = $schedules_data[$old_time_utc]['active'] ? 'diaktifkan' : 'dinonaktifkan';
            send_message($chat_id, "✅ Jadwal `{$time_input}` WIB berhasil {$status}.");
        } else {
            unset($schedules_data[$old_time_utc]);
            $schedules_data[$new_time_utc] = ['active' => true, 'id' => 'schedule_' . time()];
            send_message($chat_id, "✅ Jadwal berhasil diubah dari `{$old_time_wib}` WIB ke `{$time_input}` WIB.");
        }
        setup_schedule();
    } else {
        if (!empty($schedules_data[$new_time_utc]['active'] ?? false)) {
            send_message($chat_id, "❌ Jadwal `{$time_input}` WIB sudah ada dan aktif.");
        } else {
            $schedules_data[$new_time_utc] = ['active' => true, 'id' => 'schedule_' . time()];
            setup_schedule();
            send_message($chat_id, "✅ Jadwal baru `{$time_input}` WIB berhasil ditambahkan.");
        }
    }
    unset($user_states[$chat_id]);
    save_imjas_state();
}

function handle_showyanti($chat_id): void {
    global $schedules_yanti;
    $active = [];
    foreach ($schedules_yanti as $t => $d) {
        if ($d['active']) $active[] = $t;
    }
    if (empty($active)) {
        send_message($chat_id, "❌ Tidak ada jadwal aktif pengingat Yanti.");
        return;
    }
    sort($active);
    $msg = "🕓 **STATUS JADWAL YANTI**\n\n";
    foreach ($active as $t) $msg .= '⏰ ' . format_time_with_wib($t) . "\n";
    $msg .= "\nTotal aktif: " . count($active) . "\n";
    send_message($chat_id, $msg);
}

function handle_setyanti($chat_id): void {
    global $schedules_yanti, $user_states;
    if (empty($schedules_yanti)) {
        $user_states[$chat_id] = ['action' => 'setyanti_input', 'old_time' => null];
        send_message($chat_id, "➕ **TAMBAH JADWAL YANTI BARU**\n\nMasukkan waktu untuk jadwal pengingat baru:\nFormat: `HH:MM` (contoh: `06:00`)\n\n*Catatan: Masukkan waktu dalam **WIB** - bot akan otomatis mengkonversi ke UTC*");
        save_imjas_state();
        return;
    }

    $buttons = [];
    ksort($schedules_yanti);
    foreach ($schedules_yanti as $t => $d) {
        $status = $d['active'] ? '✅' : '❌';
        $wib    = utc_to_wib($t);
        $buttons[] = [['text' => "{$status} {$wib} WIB ({$t} UTC)", 'callback_data' => "edity_{$t}"]];
    }
    $buttons[] = [['text' => '➕ Tambah Jadwal Baru', 'callback_data' => 'addy_new']];
    $buttons[] = [['text' => '❌ Batal', 'callback_data' => 'cancel']];

    send_message($chat_id, "⚙️ **PENGATURAN JADWAL YANTI**\n\nPilih jadwal yang ingin diubah/aktifkan/nonaktifkan atau tambah jadwal baru:\n\n*Catatan: Input waktu dalam **WIB** - ditampilkan WIB (UTC)*", create_inline_keyboard($buttons));
    $user_states[$chat_id] = ['action' => 'setyanti_select'];
    save_imjas_state();
}

function handle_deleteyanti($chat_id): void {
    global $schedules_yanti, $user_states;
    $active = [];
    foreach ($schedules_yanti as $t => $d) {
        if ($d['active']) $active[] = $t;
    }
    if (empty($active)) {
        send_message($chat_id, "❌ Tidak ada jadwal aktif pengingat Yanti.");
        return;
    }
    sort($active);
    $buttons = [];
    foreach ($active as $t) {
        $wib = utc_to_wib($t);
        $buttons[] = [['text' => "🗑️ {$wib} WIB ({$t} UTC)", 'callback_data' => "dely_{$t}"]];
    }
    $buttons[] = [['text' => '❌ Batal', 'callback_data' => 'cancel']];

    send_message($chat_id, "🗑️ **HAPUS JADWAL YANTI**\n\nPilih jadwal yang ingin dihapus:", create_inline_keyboard($buttons));
    $user_states[$chat_id] = ['action' => 'deletey_select'];
    save_imjas_state();
}

function handle_time_input_yanti($chat_id, string $time_input): void {
    global $user_states, $schedules_yanti;
    if (!validate_time_format($time_input)) {
        send_message($chat_id, "❌ Format waktu tidak valid! Gunakan format `HH:MM` (contoh: `06:00`)\n\n*Masukkan waktu dalam WIB — bot otomatis konversi ke UTC*");
        return;
    }

    $state        = $user_states[$chat_id] ?? [];
    $old_time_utc = $state['old_time'] ?? null;
    $new_time_utc = wib_to_utc($time_input);

    if ($old_time_utc) {
        if (!isset($schedules_yanti[$old_time_utc])) {
            send_message($chat_id, "❌ Jadwal Yanti tidak ditemukan.");
            unset($user_states[$chat_id]);
            return;
        }
        $old_wib = utc_to_wib($old_time_utc);
        if ($time_input === $old_wib) {
            $schedules_yanti[$old_time_utc]['active'] = !$schedules_yanti[$old_time_utc]['active'];
            $status = $schedules_yanti[$old_time_utc]['active'] ? 'diaktifkan' : 'dinonaktifkan';
            send_message($chat_id, "✅ Jadwal pengingat `{$time_input}` WIB berhasil {$status}.");
        } else {
            unset($schedules_yanti[$old_time_utc]);
            $schedules_yanti[$new_time_utc] = ['active' => true, 'id' => 'yanti_' . time()];
            send_message($chat_id, "✅ Jadwal pengingat berhasil diubah dari `{$old_wib}` WIB ke `{$time_input}` WIB.");
        }
        setup_schedule_yanti();
    } else {
        if (!empty($schedules_yanti[$new_time_utc]['active'] ?? false)) {
            send_message($chat_id, "❌ Jadwal pengingat `{$time_input}` WIB sudah ada dan aktif.");
        } else {
            $schedules_yanti[$new_time_utc] = ['active' => true, 'id' => 'yanti_' . time()];
            setup_schedule_yanti();
            send_message($chat_id, "✅ Jadwal pengingat baru `{$time_input}` WIB berhasil ditambahkan.");
        }
    }
    unset($user_states[$chat_id]);
    save_imjas_state();
}

function handle_showyanti_text($chat_id): void {
    global $YANTI_REMINDER_TEXT;
    send_message($chat_id, "📄 *Teks pengingat Yanti saat ini:*\n\n" . $YANTI_REMINDER_TEXT);
}

function handle_setyanti_text($chat_id): void {
    global $user_states;
    $user_states[$chat_id] = ['action' => 'setyanti_text'];
    send_message($chat_id, "✏️ **UBAH TEKS PENGINGAT YANTI**\n\nSilakan kirim teks baru yang ingin dipakai untuk pengingat Yanti.\n\nContoh:\n`Mba Yanti jangan lupa update laporan ya 📝 @rolimartin @JackSpaarroww @YantiMohadi @alibassss`");
    save_imjas_state();
}

function handle_yanti_text_input($chat_id, string $new_text): void {
    global $YANTI_REMINDER_TEXT, $user_states;
    $new_text = trim($new_text);
    if ($new_text === '') {
        send_message($chat_id, "❌ Teks tidak boleh kosong. Kirim lagi teks pengingat yang baru.");
        return;
    }
    $old_text = $YANTI_REMINDER_TEXT;
    $YANTI_REMINDER_TEXT = $new_text;
    send_message($chat_id, "✅ **Teks pengingat Yanti berhasil diubah.**\n\n*Sebelumnya:*\n{$old_text}\n\n*Sekarang:*\n{$YANTI_REMINDER_TEXT}");
    unset($user_states[$chat_id]);
    save_imjas_state();
}

function handle_callback_query(array $callback_query): void {
    global $user_states, $schedules_data, $schedules_yanti;

    $message = $callback_query['message'] ?? [];
    $chat_id = $message['chat']['id'] ?? null;
    $data    = $callback_query['data'] ?? '';
    $cbid    = $callback_query['id'] ?? '';

    if ($cbid) answer_callback_query($cbid);
    if (!$chat_id) return;

    if ($data === 'cancel') {
        unset($user_states[$chat_id]);
        send_message($chat_id, "❌ Operasi dibatalkan.");
        save_imjas_state(); 
        return;
    }

    if (strpos($data, 'edit_') === 0) {
        $time_str = substr($data, strlen('edit_'));
        if (isset($schedules_data[$time_str])) {
            $user_states[$chat_id] = ['action' => 'settime_input', 'old_time' => $time_str];
            $status  = $schedules_data[$time_str]['active'] ? 'aktif' : 'nonaktif';
            $current = utc_to_wib($time_str);
            send_message($chat_id, "⏰ **UBAH JADWAL**\n\nJadwal saat ini: `{$current}` WIB ({$status})\n\nSilakan masukkan waktu baru dalam **WIB** (atau waktu yang sama untuk mengubah status aktif/nonaktifnya).\nFormat: `HH:MM` (contoh: `16:30`)\n\n*Catatan: Input waktu dalam **WIB** - bot akan otomatis mengkonversi ke UTC*");
        } else {
            send_message($chat_id, "❌ Jadwal tidak ditemukan.");
            unset($user_states[$chat_id]);
        }
        save_imjas_state(); 
        return;
    }

    if ($data === 'add_new') {
        $user_states[$chat_id] = ['action' => 'settime_input', 'old_time' => null];
        send_message($chat_id, "➕ **TAMBAH JADWAL BARU**\n\nMasukkan waktu untuk jadwal baru:\nFormat: `HH:MM` (contoh: `16:30`)\n\n*Catatan: Masukkan waktu dalam **WIB** - bot akan otomatis mengkonversi ke UTC*");
        save_imjas_state(); 
        return;
    }

    if (strpos($data, 'edity_') === 0) {
        $time_str = substr($data, strlen('edity_'));
        if (isset($schedules_yanti[$time_str])) {
            $user_states[$chat_id] = ['action' => 'setyanti_input', 'old_time' => $time_str];
            $current = utc_to_wib($time_str);
            send_message($chat_id, "⏰ **UBAH JADWAL YANTI**\n\nJadwal saat ini: `{$current}` (aktif)\n\nSilakan masukkan waktu baru (atau waktu yang sama untuk mengubah status aktif/nonaktifnya).\nFormat: `HH:MM` (contoh: `13:00`)");
        } else {
            send_message($chat_id, "❌ Jadwal Yanti tidak ditemukan.");
        }
        save_imjas_state(); 
        return;
    }

    if ($data === 'addy_new') {
        $user_states[$chat_id] = ['action' => 'setyanti_input', 'old_time' => null];
        send_message($chat_id, "➕ **TAMBAH JADWAL YANTI BARU**\n\nMasukkan waktu baru untuk jadwal pengingat Yanti:\nFormat: `HH:MM` (contoh: `06:00`)\n\n*Masukkan waktu dalam WIB — bot otomatis konversi ke UTC.*");
        save_imjas_state(); 
        return;
    }

    if (strpos($data, 'dely_') === 0) {
        $time_str = substr($data, strlen('dely_'));
        if (isset($schedules_yanti[$time_str])) {
            $schedules_yanti[$time_str]['active'] = false;
            setup_schedule_yanti();
            send_message($chat_id, "✅ Jadwal pengingat Yanti " . format_time_with_wib($time_str) . " berhasil dinonaktifkan.");
        } else {
            send_message($chat_id, "❌ Jadwal Yanti tidak ditemukan.");
        }
        save_imjas_state(); 
        return;
    }

    if (strpos($data, 'delete_') === 0) {
        $time_str = substr($data, strlen('delete_'));
        if (isset($schedules_data[$time_str])) {
            $schedules_data[$time_str]['active'] = false;
            setup_schedule();
            send_message($chat_id, "✅ Jadwal " . format_time_with_wib($time_str) . " berhasil dinonaktifkan.");
        } else {
            send_message($chat_id, "❌ Jadwal `{$time_str}` tidak ditemukan.");
        }
        unset($user_states[$chat_id]);
        save_imjas_state(); 
        return;
    }
}

function handle_help($chat_id): void {
    $help = <<<TEXT
🤖 **PANDUAN BOT SCHEDULER**

📋 **Perintah yang tersedia:**
• `/start` - Jalankan task screenshot sekarang
• `/showtime` - Tampilkan semua jadwal
• `/settime` - Ubah atau tambah jadwal
• `/deltime` - Hapus jadwal
• `/setbot` - Buka pengaturan & Target Chat
• `/help` - Tampilkan panduan ini

⏰ **Informasi Jadwal:**
Bot akan otomatis mengambil screenshot dari dashboard dan mengirimkannya sesuai jadwal yang telah diatur.

📝 **Format Waktu:**
Gunakan format `HH:MM` (24 jam)
Contoh: `08:30`, `14:45`, `23:00`

🌏 **Zona Waktu:**
Input waktu dalam **WIB (Waktu Indonesia Barat)**
Bot otomatis menyimpan sebagai UTC dan menjalankan sesuai waktu WIB yang dimasukkan.
TEXT;
    send_message($chat_id, $help);
}

function handle_setbot($chat_id, $fromUser): void {
    try {
        // Koneksi ke database (inline agar tidak bentrok scope)
        $dsn = "mysql:host=localhost;dbname=u408986314_klkfoo;charset=utf8mb4";
        $db = new PDO($dsn, 'u408986314_klkfoo', 'Efidi123', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Validasi apakah user ini adalah Admin aktif di database
        $stmt = $db->prepare("SELECT id_akses, nama, username FROM akses_login WHERE chat_id = ? AND role = 'admin' AND status = 'active' LIMIT 1");
        $stmt->execute([$chat_id]);
        $admin = $stmt->fetch();

        if (!$admin) {
            send_message($chat_id, "❌ Maaf, Anda tidak memiliki akses Admin.\n*(Chat ID Anda: `{$chat_id}`)*");
            return;
        }

        // Link pakai username Telegram asli yang mengirim pesan
        $url = "https://efidi.montaklo.id/idaman/admin.php?u={$fromUser}&cid={$chat_id}";

        // Escape underscore agar tidak dimakan Markdown Telegram
        $safeUser = str_replace('_', '\_', $fromUser);
        $msg = "✅ **Selamat datang Admin @{$safeUser}.**\n\nKlik tombol di bawah ini untuk masuk ke Panel Configurator.";
        $buttons = [
            [['text' => '⚙️ Buka Panel Admin', 'url' => $url]]
        ];
        send_message($chat_id, $msg, create_inline_keyboard($buttons));
    } catch (\Throwable $e) {
        log_error("handle_setbot error: " . $e->getMessage());
        send_message($chat_id, "⚠️ Terjadi kesalahan saat memproses. Silakan coba lagi nanti.");
    }
}

function handle_telegram_update(array $update): void {
    global $user_states;

    if (isset($update['callback_query'])) {
        handle_callback_query($update['callback_query']);
        return;
    }

    $message = $update['message'] ?? null;
    if (!$message) return;

    $chat      = $message['chat'] ?? [];
    $chat_id   = $chat['id'] ?? null;
    $text      = $message['text'] ?? '';
    $fromUser  = $message['from']['username'] ?? 'unknown_user';
    $chat_type = $chat['type'] ?? 'unknown';
    $chat_title = $chat['title'] ?? '';

    if ($chat_id === null) return;

    log_info("Pesan dari @{$fromUser} | chat_id={$chat_id} | type={$chat_type} | title='{$chat_title}' | text='{$text}'");

    $lower = mb_strtolower(trim((string)$text));

    // SYSTEM QUEUE UNTUK START (ASYNC HTTP - BYPASS 60s LIMIT HOSTINGER)
    if ($lower === '/start') {
        log_info("Command /start dari {$chat_id} - MENCOBA ASYNC HTTP STEP 1");
        
        // Kirim Notifikasi Awal ke User
        send_message($chat_id, "⏳ Memulai proses pengambilan screenshot SELURUH Laporan (Termasuk IMJAS & Kliring). Mohon tunggu sebentar...");
        
        // Panggil Step 1 via cURL Async
        fire_async_step(1, [(string) $chat_id], 'all');
        return;
    }

    if ($lower === '/imjas') {
        log_info("Command /imjas dari {$chat_id} - MENCOBA ASYNC HTTP STEP 1");
        send_message($chat_id, "⏳ Memulai proses pengambilan screenshot IMJAS & Kliring. Mohon tunggu sebentar...");
        fire_async_step(1, [(string) $chat_id], 'imjas_kliring');
        return;
    }
    
    if ($lower === '/showtime') { handle_showtime($chat_id); return; }
    if ($lower === '/settime') { handle_settime($chat_id); return; }
    if ($lower === '/deltime') { handle_deltime($chat_id); return; }
    if ($lower === '/help') { handle_help($chat_id); return; }
    if ($lower === '/stop') { handle_stop($chat_id); return; }
    if ($lower === '/setbot') { handle_setbot($chat_id, $fromUser); return; }

    if ($lower === '/set-yanti') { handle_setyanti($chat_id); return; }
    if ($lower === '/show-yanti') { handle_showyanti($chat_id); return; }
    if ($lower === '/delete-yanti') { handle_deleteyanti($chat_id); return; }
    if ($lower === '/set-yanti-text') { handle_setyanti_text($chat_id); return; }
    if ($lower === '/show-yanti-text') { handle_showyanti_text($chat_id); return; }

    if (isset($user_states[$chat_id]) && ($user_states[$chat_id]['action'] ?? null) === 'setyanti_input') {
        log_info("Input waktu pengingat Yanti dari {$chat_id}: '{$text}'");
        handle_time_input_yanti($chat_id, trim((string)$text));
        return;
    }

    if (isset($user_states[$chat_id]) && ($user_states[$chat_id]['action'] ?? null) === 'setyanti_text') {
        log_info("Input teks pengingat Yanti baru dari {$chat_id}: '{$text}'");
        handle_yanti_text_input($chat_id, (string)$text);
        return;
    }

    if (isset($user_states[$chat_id]) && ($user_states[$chat_id]['action'] ?? null) === 'settime_input') {
        log_info("Input waktu jadwal dari {$chat_id}: '{$text}'");
        handle_time_input($chat_id, trim((string)$text));
        return;
    }
}
