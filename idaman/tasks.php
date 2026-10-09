<?php
declare(strict_types=1);

/**
 * tasks.php
 * Fully Dynamic Screenshot Engine
 */

// Kompatibilitas cron lama
function run_capture_imjas_dan_kliring(?array $target_chat_ids = null, int $step = 1): void {
    run_dynamic_task('imjas_kliring', $target_chat_ids, $step);
}

function run_full_task(?array $caller_chat_ids = null, int $step = 1): void {
    run_dynamic_task('all', $caller_chat_ids, $step);
}

function run_dynamic_task(string $groupName, ?array $caller_chat_ids = null, int $step = 1): void {
    global $is_running;
    $is_manual = !empty($caller_chat_ids);

    // Filter reports based on group
    $dyn = get_dynamic_config();
    $is_auto_cron = ($caller_chat_ids === null);

    $reports = [];
    foreach ($dyn['reports'] as $key => $r) {
        $rGroup = $r['group'] ?? 'main';
        $hasCustomSchedule = !empty($r['schedules']);
        
        $should_include = false;
        
        if ($key === $groupName) {
            // Jika dipanggil berdasarkan ID laporan spesifik
            $should_include = true;
        } elseif ($groupName === 'all' || $rGroup === $groupName) {
            if ($is_auto_cron) {
                // Jika otomatis dari Cron Global, abaikan laporan yang punya jadwal mandiri
                if (!$hasCustomSchedule) {
                    $should_include = true;
                }
            } else {
                // Jika manual dari Telegram (/start), proses semuanya
                $should_include = true;
            }
        }

        if ($should_include) {
            $r['id'] = $key;
            $reports[] = $r;
        }
    }

    $totalReports = count($reports);

    // Kunci proses di step 1
    if ($step === 1) {
        if ($is_running || is_task_locked()) {
            log_warn("Task {$groupName} sedang berjalan (lock aktif), permintaan baru dimasukkan ke antrean.");
            if ($is_manual && !empty($caller_chat_ids[0])) {
                send_message($caller_chat_ids[0], "⚠️ Task pengambilan screenshot sedang berjalan. Permintaan Anda dimasukkan ke dalam antrean.");
            } else {
                queue_add_group($groupName);
            }
            return;
        }
        $is_running = true;
        set_task_lock();
    }

    $checkCancel = function () use ($is_manual, $caller_chat_ids): bool {
        if (!is_task_cancel_requested()) return false;
        $info = get_cancel_info();
        $by   = $info['by_chat_id'] ?? null;
        log_warn("Task dibatalkan via /stop");

        if ($is_manual && !empty($caller_chat_ids[0])) {
            send_message($caller_chat_ids[0], "⏹️ Proses dihentikan sebelum selesai karena perintah /stop.");
        } elseif ($by) {
            send_message($by, "⏹️ Proses pengambilan screenshot dihentikan.");
        }
        clear_task_cancel();
        return true;
    };

    try {
        if ($checkCancel()) return;

        if ($step > $totalReports) {
            // Selesai semua laporan
            if ($is_manual && !empty($caller_chat_ids[0])) {
                send_message($caller_chat_ids[0], "✅ Pengambilan screenshot {$groupName} selesai dan telah dikirim.");
            }
            log_info("Semua STEP {$groupName} berhasil diselesaikan.");
            return; // Selesai, akan masuk ke finally untuk unlock
        }

        $currentReport = $reports[$step - 1];
        log_info("[STEP {$step}] Mengambil screenshot {$currentReport['name']}...");

        $url = $currentReport['url'] ?? '';
        $crops = $currentReport['crops'] ?? [];
        
        if (empty($url) || empty($crops)) {
            log_warn("Laporan {$currentReport['name']} tidak valid, melewati step ini.");
            fire_async_step($step + 1, $caller_chat_ids, $groupName);
            return;
        }

        // Tentukan Opsi Kamera Dinamis
        $options = [
            'width' => 1920, 
            'height' => 1200, 
            'delay' => 10, 
            'fullPage' => false,
            'addStyleTag' => [
                ['content' => '::-webkit-scrollbar { display: none !important; } html, body { -ms-overflow-style: none; scrollbar-width: none; }']
            ]
        ];
        
        if (strpos($url, 'lookerstudio') !== false || strpos($url, 'datastudio') !== false) {
            $options['height'] = 2500;
            $options['delay'] = 24; // Dikurangi dari 30s ke 15s agar total eksekusi Step 1 tidak melebihi 120 detik (limit Hostinger)
            $options['addStyleTag'][] = ['content' => 'html, body { margin: 0 !important; padding: 0 !important; background: #ffffff !important; }'];
            $options['addScriptTag'][] = ['content' => 'document.body.style.zoom = "0.8";'];
        } elseif (strpos($url, 'spreadsheets') !== false) {
            $options['width'] = 1920; 
            $options['height'] = 1200; 
            $options['delay'] = 10; 
        }

        // File raw pertama di crops array (asumsi sama semua)
        $rawImageName = $crops[0]['raw_image'] ?? ($currentReport['id'] . '_raw.png');
        $rawImagePath = __DIR__ . '/raw/' . basename($rawImageName);
        if (!is_dir(__DIR__ . '/raw')) @mkdir(__DIR__ . '/raw');

        $captureOk = capture_url_screenshot($url, $rawImagePath, $options);

        if ($captureOk && file_exists($rawImagePath)) {
            $baseFile = $rawImagePath;
            
            // Trim khusus untuk Looker Studio agar menghilangkan border putih
            $trimFile = __DIR__ . '/raw/' . $currentReport['id'] . '_trim.png';
            if (strpos($url, 'lookerstudio') !== false) {
                if (crop_image_trim_border($rawImagePath, $trimFile, 12)) {
                    $baseFile = $trimFile;
                }
            }

            // Lakukan pemotongan sesuai crops di JSON
            foreach ($crops as $crop) {
                $cropId = $crop['id'];
                $cropFile = __DIR__ . '/raw/' . $currentReport['id'] . '_' . $cropId . '.png';
                
                $x = $crop['x'] ?? 0.0;
                $y = $crop['y'] ?? 0.0;
                $w = $crop['w'] ?? 1.0;
                $h = $crop['h'] ?? 1.0;
                
                $cropOk = crop_image_area_relative($baseFile, $cropFile, $x, $y, $w, $h);
                if ($cropOk) {
                    // Prioritaskan grup target yang diatur di JSON
                    $finalTarget = !empty($crop['targets']) ? $crop['targets'] : ($is_manual ? $caller_chat_ids : null);
                    $caption = $crop['caption'] ?? $currentReport['name'];
                    
                    send_screenshot_to_telegram($cropFile, $caption, $finalTarget);
                    @unlink($cropFile);
                }
            }

            if (file_exists($trimFile)) @unlink($trimFile);
            // $rawImagePath sengaja tidak dihapus agar bisa dipakai UI Cropper Web Admin
        } else {
            log_error("Gagal capture URL: {$url}");
            if ($is_manual && !empty($caller_chat_ids[0])) {
                send_message($caller_chat_ids[0], "❌ Gagal mengambil screenshot untuk laporan: *{$currentReport['name']}*. URL mungkin terlalu lambat atau tidak valid.");
            }
        }

        // Lanjut ke Step berikutnya secara asinkronus!
        fire_async_step($step + 1, $caller_chat_ids, $groupName);

    } catch (Throwable $e) {
        log_error("Error fatal saat menjalankan task {$groupName} step {$step}: " . $e->getMessage());
    } finally {
        if ($step > $totalReports || isset($e)) {
            $is_running = false;
            clear_task_lock();
            clear_task_cancel();
            
            $nextGroup = queue_pop_group();
            if ($nextGroup) {
                log_info("Menjalankan task grup tertunda dari antrean: {$nextGroup}");
                fire_async_step(1, null, $nextGroup);
            }
        }
    }
}
