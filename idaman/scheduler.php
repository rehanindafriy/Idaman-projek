<?php
declare(strict_types=1);

// =====================================================
//  SCHEDULER INTERNAL (UNTUK MODE CRON)
// =====================================================

function setup_schedule(): void {
    global $schedules_data, $scheduledJobs;

    foreach ($scheduledJobs as $idx => $job) {
        if (($job['source'] ?? '') === 'main') unset($scheduledJobs[$idx]);
    }

    $active_count = 0;
    foreach ($schedules_data as $time_str => $data) {
        if (!($data['active'] ?? false)) continue;
        $dt = DateTime::createFromFormat('H:i', $time_str, new DateTimeZone('UTC'));
        if (!$dt) {
            log_warn("Jadwal '{$time_str}' tidak valid, dilewati.");
            continue;
        }

        $scheduledJobs[] = [
            'id'          => $data['id'],
            'time_utc'    => $time_str,
            'callback'    => 'run_full_task',
            'active'      => true,
            'last_run_at' => null,
            'source'      => 'main',
        ];
        $active_count++;
    }
    log_info("Jadwal main diperbarui: {$active_count} jadwal aktif");
}

function setup_schedule_yanti(): void {
    global $schedules_yanti, $scheduledJobs;

    foreach ($scheduledJobs as $idx => $job) {
        if (($job['source'] ?? '') === 'yanti') unset($scheduledJobs[$idx]);
    }

    $active = 0;
    foreach ($schedules_yanti as $time_str => $data) {
        if (!($data['active'] ?? false)) continue;
        $dt = DateTime::createFromFormat('H:i', $time_str, new DateTimeZone('UTC'));
        if (!$dt) {
            log_warn("Jadwal Yanti '{$time_str}' tidak valid, dilewati.");
            continue;
        }
        $scheduledJobs[] = [
            'id'          => $data['id'],
            'time_utc'    => $time_str,
            'callback'    => 'send_yanti_reminder',
            'active'      => true,
            'last_run_at' => null,
            'source'      => 'yanti',
        ];
        $active++;
    }
    log_info("Jadwal Yanti diperbarui: {$active} jadwal aktif");
}

function setup_report_schedules(): void {
    global $scheduledJobs;

    foreach ($scheduledJobs as $idx => $job) {
        if (($job['source'] ?? '') === 'report_specific') unset($scheduledJobs[$idx]);
    }

    $dyn = get_dynamic_config();
    if (empty($dyn['reports'])) return;

    $active = 0;
    foreach ($dyn['reports'] as $reportId => $report) {
        $schedules = $report['schedules'] ?? [];
        foreach ($schedules as $time_wib) {
            $time_wib = trim($time_wib);
            if (!$time_wib) continue;

            $time_utc = wib_to_utc($time_wib);

            $scheduledJobs[] = [
                'id'          => 'report_' . $reportId . '_' . $time_utc,
                'time_utc'    => $time_utc,
                'callback'    => 'run_specific_report',
                'report_id'   => $reportId,
                'active'      => true,
                'last_run_at' => null,
                'source'      => 'report_specific',
                'wib_display' => $time_wib
            ];
            $active++;
        }
    }
    if ($active > 0) log_info("Jadwal spesifik laporan diperbarui: {$active} jadwal aktif");
}

function run_scheduler_tick(): void {
    global $scheduledJobs;

    if (empty($scheduledJobs)) return;

    $nowUtc   = new DateTime('now', new DateTimeZone('UTC'));
    $hhmmUtc  = $nowUtc->format('H:i');
    $todayUtc = $nowUtc->format('Y-m-d');

    foreach ($scheduledJobs as &$job) {
        if (!($job['active'] ?? false)) continue;
        if (($job['time_utc'] ?? '') !== $hhmmUtc) continue;

        $cb = $job['callback'] ?? null;
        log_info("Menjalankan job scheduler id={$job['id']} callback={$cb} time_utc={$hhmmUtc}");

        if ($cb === 'run_full_task') {
            fire_async_step(1, null, 'all');
        } elseif ($cb === 'send_yanti_reminder') {
            send_yanti_reminder();
        } elseif ($cb === 'run_capture_imjas_dan_kliring') {
            fire_async_step(1, null, 'imjas_kliring');
        } elseif ($cb === 'run_specific_report') {
            fire_async_step(1, null, $job['report_id']);
        }

        $job['last_run_at'] = $todayUtc;
    }
    unset($job);
}

function get_next_schedule_info(): string {
    global $scheduledJobs;

    // Pastikan jadwal di-load terlebih dahulu (berguna saat dipanggil via Webhook / Telegram)
    setup_schedule();
    setup_schedule_yanti();
    setup_report_schedules();

    if (empty($scheduledJobs)) return 'Tidak ada jadwal.';

    $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
    $minDiff = null;
    $nextJob = null;

    foreach ($scheduledJobs as $job) {
        if (!($job['active'] ?? false)) continue;
        $time = $job['time_utc'] ?? null;
        if (!$time) continue;

        $target = DateTime::createFromFormat('H:i', $time, new DateTimeZone('UTC'));
        if (!$target) continue;

        if ((int)$target->format('H') < (int)$nowUtc->format('H') ||
            ((int)$target->format('H') === (int)$nowUtc->format('H') &&
             (int)$target->format('i') <= (int)$nowUtc->format('i'))) {
            $target->modify('+1 day');
        }

        $diffSec = $target->getTimestamp() - $nowUtc->getTimestamp();
        if ($diffSec < 0) continue;
        
        if ($minDiff === null || $diffSec < $minDiff) {
            $minDiff = $diffSec;
            $nextJob = $job;
        }
    }

    if ($nextJob === null) return 'Tidak ada jadwal aktif.';

    $nextTimeUtc = $nextJob['time_utc'] ?? '';
    $wib         = utc_to_wib($nextTimeUtc);
    
    $source_info = "";
    if (($nextJob['source'] ?? '') === 'report_specific') {
        $dyn = get_dynamic_config();
        $reportName = $dyn['reports'][$nextJob['report_id']]['name'] ?? 'Laporan Khusus';
        $source_info = "\n📌 *Tipe:* Jadwal Khusus Web ({$reportName})";
    } elseif (($nextJob['source'] ?? '') === 'main') {
        $source_info = "\n📌 *Tipe:* Jadwal Global (Semua Laporan)";
    } elseif (($nextJob['source'] ?? '') === 'yanti') {
        $source_info = "\n📌 *Tipe:* Pengingat Yanti";
    }

    return "⏭️ **Jadwal berikutnya sekitar:** `{$nextTimeUtc}` UTC (`{$wib}` WIB){$source_info}";
}
