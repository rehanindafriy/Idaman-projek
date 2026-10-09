<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

set_time_limit(0); // Mencegah PHP timeout (30s default) karena proses screenshot bisa memakan waktu hingga 45 detik

$configFile = __DIR__ . '/bot_config_live.json';
$defaultConfigFile = __DIR__ . '/bot_config.json';

// Jika file live belum ada, copy dari template default
if (!file_exists($configFile) && file_exists($defaultConfigFile)) {
    copy($defaultConfigFile, $configFile);
}

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access. Please login via Telegram."]);
    exit;
}

$defaultConfig = [
    "reports" => []
];

// Load current config
if (file_exists($configFile)) {
    $currentConfig = json_decode(file_get_contents($configFile), true);
    if (!is_array($currentConfig)) $currentConfig = $defaultConfig;
} else {
    $currentConfig = $defaultConfig;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return all configs
    echo json_encode(["status" => "success", "data" => $currentConfig]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (isset($data['update_crop'])) {
        $reportId = $data['report_id'];
        $cropId = $data['crop_id'];
        $x = (float)$data['x'];
        $y = (float)$data['y'];
        $w = (float)$data['w'];
        $h = (float)$data['h'];
        
        if (isset($currentConfig['reports'][$reportId])) {
            foreach ($currentConfig['reports'][$reportId]['crops'] as &$crop) {
                if ($crop['id'] === $cropId) {
                    $crop['x'] = $x;
                    $crop['y'] = $y;
                    $crop['w'] = $w;
                    $crop['h'] = $h;
                }
            }
        }
        
        file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        echo json_encode(["status" => "success", "message" => "Crop updated"]);
        exit;
    }
    
    if (isset($data['update_url'])) {
        $reportId = $data['report_id'];
        $newUrl = $data['url'];
        
        if (isset($currentConfig['reports'][$reportId])) {
            $currentConfig['reports'][$reportId]['url'] = $newUrl;
        }
        file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        
        // --- PROSES LIVE PREVIEW (SCREENSHOT LANGSUNG) ---
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/utils.php';
        require_once __DIR__ . '/image_processor.php';
        
        $rawImageName = $currentConfig['reports'][$reportId]['crops'][0]['raw_image'] ?? ($reportId . '_raw.png');
        $rawImagePath = __DIR__ . '/raw/' . basename($rawImageName);
        if (!is_dir(__DIR__ . '/raw')) @mkdir(__DIR__ . '/raw');
        
        // Atur opsi spesifik berdasarkan TIPE URL (bukan tab laporan)
        $options = [
            'width' => 1920, 
            'height' => 1200, 
            'delay' => 10, 
            'fullPage' => false,
            'addStyleTag' => [
                ['content' => '::-webkit-scrollbar { display: none !important; } html, body { -ms-overflow-style: none; scrollbar-width: none; }']
            ]
        ];
        
        if (strpos($newUrl, 'lookerstudio') !== false || strpos($newUrl, 'datastudio') !== false) {
            // Pengaturan khusus untuk Looker Studio agar tidak terpotong & ukurannya pas
            $options['height'] = 2500;
            $options['delay'] = 30; // Diperlama menjadi 30 detik agar loading grafik selesai
            $options['addStyleTag'][] = ['content' => 'html, body { margin: 0 !important; padding: 0 !important; background: #ffffff !important; }'];
            $options['addScriptTag'][] = ['content' => 'document.body.style.zoom = "0.8";'];
        } elseif (strpos($newUrl, 'spreadsheets') !== false) {
            // Pengaturan khusus untuk Google Sheets (Diperbesar agar full, tapi delay dikurangi)
            $options['width'] = 2500; 
            $options['height'] = 1600;
            $options['delay'] = 10; 
        }

        // Jalankan Puppeteer/Browserless!
        $ok = capture_url_screenshot($newUrl, $rawImagePath, $options);
        
        if ($ok) {
            echo json_encode(["status" => "success", "message" => "URL updated and Live Preview generated"]);
        } else {
            echo json_encode(["status" => "warning", "message" => "URL updated, but Live Preview failed to load"]);
        }
        exit;
    }
    if (isset($data['create_report'])) {
        $reportId = preg_replace('/[^a-z0-9_]/', '_', strtolower($data['name']));
        if (isset($currentConfig['reports'][$reportId])) {
            $reportId = $reportId . '_' . time();
        }
        
        $currentConfig['reports'][$reportId] = [
            'name' => $data['name'],
            'url' => $data['url'],
            'group' => 'main',
            'crops' => [
                [
                    'id' => 'main',
                    'name' => 'Tabel Utama',
                    'x' => 0.1, 'y' => 0.1, 'w' => 0.8, 'h' => 0.8,
                    'raw_image' => $reportId . '_raw.png',
                    'caption' => $data['caption'] ?? $data['name'],
                    'targets' => !empty($data['targets']) ? explode(',', $data['targets']) : ['-5430717889']
                ]
            ]
        ];
        
        $res = file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        if ($res === false) {
            echo json_encode(["status" => "error", "message" => "Gagal menyimpan ke file bot_config.json. Periksa permission file."]);
            exit;
        }
        echo json_encode(["status" => "success", "message" => "Laporan berhasil ditambahkan", "report_id" => $reportId]);
        exit;
    }
    
    if (isset($data['delete_report'])) {
        $reportId = $data['report_id'];
        if (isset($currentConfig['reports'][$reportId])) {
            unset($currentConfig['reports'][$reportId]);
            file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        }
        echo json_encode(["status" => "success", "message" => "Laporan berhasil dihapus"]);
        exit;
    }
    
    if (isset($data['update_metadata'])) {
        $reportId = $data['report_id'];
        $cropId = $data['crop_id'];
        $name = $data['name'] ?? null;
        $caption = $data['caption'];
        $targets = explode(',', $data['targets']);
        
        if (isset($currentConfig['reports'][$reportId])) {
            foreach ($currentConfig['reports'][$reportId]['crops'] as &$crop) {
                if ($crop['id'] === $cropId) {
                    if ($name) $crop['name'] = $name;
                    $crop['caption'] = $caption;
                    $crop['targets'] = array_map('trim', $targets);
                }
            }
            file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        }
        
        echo json_encode(["status" => "success", "message" => "Metadata updated"]);
        exit;
    }

    if (isset($data['update_schedules'])) {
        $reportId = $data['report_id'];
        $schedulesRaw = explode(',', $data['schedules']);
        $clean_schedules = [];
        foreach ($schedulesRaw as $s) {
            $s = trim($s);
            if (preg_match('/^\d{2}:\d{2}$/', $s)) {
                $clean_schedules[] = $s;
            }
        }
        
        if (isset($currentConfig['reports'][$reportId])) {
            $currentConfig['reports'][$reportId]['schedules'] = $clean_schedules;
            file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        }
        
        echo json_encode(["status" => "success", "message" => "Jadwal laporan berhasil disimpan"]);
        exit;
    }
    
    if (isset($data['edit_report_name'])) {
        $reportId = $data['report_id'];
        $newName = $data['name'];
        if (isset($currentConfig['reports'][$reportId])) {
            $currentConfig['reports'][$reportId]['name'] = $newName;
            file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        }
        echo json_encode(["status" => "success", "message" => "Nama laporan berhasil diubah"]);
        exit;
    }

    if (isset($data['create_crop'])) {
        $reportId = $data['report_id'];
        $cropName = $data['name'];
        $caption = $data['caption'] ?? $cropName;
        $targetsStr = $data['targets'] ?? '';
        $targetsArray = !empty(trim($targetsStr)) ? array_map('trim', explode(',', $targetsStr)) : ['-5430717889'];
        
        $cropId = preg_replace('/[^a-z0-9_]/', '_', strtolower($cropName)) . '_' . time();
        
        if (isset($currentConfig['reports'][$reportId])) {
            $rawImage = $currentConfig['reports'][$reportId]['crops'][0]['raw_image'] ?? ($reportId . '_raw.png');
            $currentConfig['reports'][$reportId]['crops'][] = [
                'id' => $cropId,
                'name' => $cropName,
                'x' => 0.1, 'y' => 0.1, 'w' => 0.5, 'h' => 0.5,
                'raw_image' => $rawImage,
                'caption' => $caption,
                'targets' => $targetsArray
            ];
            file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        }
        echo json_encode(["status" => "success", "message" => "Crop berhasil ditambahkan", "crop_id" => $cropId]);
        exit;
    }

    if (isset($data['delete_crop'])) {
        $reportId = $data['report_id'];
        $cropId = $data['crop_id'];
        if (isset($currentConfig['reports'][$reportId])) {
            $crops = $currentConfig['reports'][$reportId]['crops'];
            $newCrops = [];
            foreach ($crops as $c) {
                if ($c['id'] !== $cropId) $newCrops[] = $c;
            }
            if (empty($newCrops)) {
                echo json_encode(["status" => "error", "message" => "Minimal harus ada 1 crop!"]);
                exit;
            }
            $currentConfig['reports'][$reportId]['crops'] = array_values($newCrops);
            file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        }
        echo json_encode(["status" => "success", "message" => "Crop berhasil dihapus"]);
        exit;
    }
    
    echo json_encode(["error" => "Invalid action"]);
    exit;
}
