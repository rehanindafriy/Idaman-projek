<?php
declare(strict_types=1);

// =====================================================
//  SCREENSHOT ENGINE (Browserless + GD)
// =====================================================
ini_set('memory_limit', '512M');

function capture_sheets_pdf_to_png(string $url, string $outputPath): bool {
    if (!preg_match('/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
        log_error("Bukan URL Google Sheets yang valid.");
        return false;
    }
    $docId = $matches[1];
    
    $gid = '0';
    if (preg_match('/gid=([0-9]+)/', $url, $mGid)) {
        $gid = $mGid[1];
    }
    
    // Parameter PDF export (Google Sheets native)
    $params = [
        'format' => 'pdf',
        'gid' => $gid,
        'portrait' => 'false',     // Landscape
        'size' => 'A3',            // Kertas besar
        'gridlines' => 'false',
        'top_margin' => '0.2',
        'bottom_margin' => '0.2',
        'left_margin' => '0.2',
        'right_margin' => '0.2',
        'sheetnames' => 'false',
        'printtitle' => 'false',
        'pagenum' => 'UNDEFINED',
        'attachment' => 'false',
        'fitw' => 'true'           // Fit to width
    ];
    
    $pdfUrl = "https://docs.google.com/spreadsheets/d/{$docId}/export?" . http_build_query($params);
    log_info("Downloading PDF dari Google Sheets: {$pdfUrl}");
    
    $ch = curl_init($pdfUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40); // 40s timeout for download
    $pdfContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$pdfContent || $httpCode !== 200) {
        log_error("Gagal mendownload PDF (HTTP {$httpCode}).");
        return false;
    }
    
    $tmpPdf = tempnam(sys_get_temp_dir(), 'sheet_') . '.pdf';
    file_put_contents($tmpPdf, $pdfContent);
    
    try {
        $imagick = new Imagick();
        $imagick->setResolution(300, 300); // 300 DPI untuk resolusi ketajaman tinggi
        // readImage dengan [0] untuk mengambil halaman pertama saja
        $imagick->readImage($tmpPdf . '[0]'); 
        
        // Atur background putih agar tidak transparan/hitam
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->flattenImages();
        
        $imagick->setImageFormat('png');
        $imagick->writeImage($outputPath);
        
        $imagick->clear();
        $imagick->destroy();
        
        @unlink($tmpPdf);
        log_info("Berhasil mengkonversi PDF ke PNG via Imagick: {$outputPath}");
        return true;
    } catch (Exception $e) {
        log_error("Imagick Error: " . $e->getMessage());
        @unlink($tmpPdf);
        return false;
    }
}

function capture_url_screenshot(string $url, string $outputPath, array $options = []): bool
{
    // BYPASS BROWSERLESS UNTUK GOOGLE SHEETS JIKA IMAGICK TERSEDIA
    if (strpos($url, 'docs.google.com/spreadsheets') !== false && extension_loaded('imagick')) {
        log_info("Menggunakan Solusi 2 (PDF to PNG) untuk Google Sheets...");
        return capture_sheets_pdf_to_png($url, $outputPath);
    }

    $token = getenv('BROWSERLESS_TOKEN') ?: BROWSERLESS_TOKEN;

    if (empty($token) || $token === 'ISI_TOKEN_BROWSERLESS_DI_SINI') {
        log_warn("BROWSERLESS_TOKEN belum di-set. Isi konstanta BROWSERLESS_TOKEN atau ENV BROWSERLESS_TOKEN.");
        return false;
    }

    $endpoint = rtrim(BROWSERLESS_ENDPOINT, '/') . '?token=' . urlencode($token);

    $viewportWidth  = (int)($options['width'] ?? $options['viewport_width'] ?? 1440);
    $viewportHeight = (int)($options['height'] ?? $options['viewport_height'] ?? 900);
    $deviceScaleFactor = isset($options['deviceScaleFactor']) ? (int)$options['deviceScaleFactor'] : 2; // KEMBALI KE 2 SEPERTI VERSI LAMA
    $delaySeconds = (int)($options['delay'] ?? 10);
    $delayMs      = max(0, $delaySeconds * 1000);
    $fullPage   = isset($options['fullPage']) ? (bool)$options['fullPage'] : false;
    $noViewport = !empty($options['noViewport']);

    $payload = [
        'url'            => $url,
        'waitForTimeout' => $delayMs,
        'gotoOptions'    => [
            'waitUntil' => 'load', // KEMBALI KE 'load' SEPERTI VERSI LAMA
            'timeout'   => 90000,
        ],
        'options'        => [
            'type'     => 'png',
            'fullPage' => $fullPage,
        ],
        'setExtraHTTPHeaders' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        ],
    ];

    if (!empty($options['options']) && is_array($options['options'])) {
        $payload['options'] = array_merge($payload['options'], $options['options']);
    }

    if (!$noViewport) {
        $payload['viewport'] = [
            'width'            => $viewportWidth,
            'height'           => $viewportHeight,
            'deviceScaleFactor'=> $deviceScaleFactor,
        ];
    }

    if (!empty($options['addStyleTag'])) $payload['addStyleTag'] = $options['addStyleTag'];
    if (!empty($options['addScriptTag'])) $payload['addScriptTag'] = $options['addScriptTag'];
    if (!empty($options['waitFor'])) $payload['waitFor'] = $options['waitFor'];
    if (!empty($options['waitForSelector'])) $payload['waitForSelector'] = $options['waitForSelector'];
    if (!empty($options['waitForFunction'])) $payload['waitForFunction'] = $options['waitForFunction'];

    $maxRetries = 1; // WAJIB 1! Jika di-retry dengan timeout 110s, total waktu akan melebihi 120s limit Hostinger dan mati diam-diam!
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        $attempt++;
        
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Cache-Control: no-cache',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 110, // Max 110 detik agar PHP tidak dibunuh diam-diam oleh Hostinger (limit 120s)
        ]);

        $img = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($img === false) {
            log_error("cURL error Browserless (Attempt {$attempt}): " . curl_error($ch));
            curl_close($ch);
            if ($attempt >= $maxRetries) return false;
            sleep(10);
            continue;
        }

        curl_close($ch);

        if ($httpCode === 429 || $httpCode >= 500) {
            log_warn("HTTP error Browserless ({$httpCode}) (Attempt {$attempt}) - Menunggu 15 detik sebelum retry...");
            if ($attempt >= $maxRetries) {
                log_error("Gagal setelah {$maxRetries} kali percobaan.");
                return false;
            }
            sleep(15); // Tunggu 15 detik sebelum mencoba lagi karena rate limit
            continue;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            log_error("HTTP error Browserless ({$httpCode}) untuk URL {$url}: " . substr($img, 0, 300));
            return false;
        }
        
        break; // Sukses, keluar dari loop
    }

    if (@file_put_contents($outputPath, $img) === false) {
        log_error("Gagal menulis file screenshot ke {$outputPath}");
        return false;
    }

    log_info("Screenshot URL {$url} tersimpan sebagai {$outputPath} (Browserless).");
    return true;
}

function crop_image_area(string $src, string $dst, array $rect): bool
{
    if (!file_exists($src)) {
        log_error("Source image untuk crop tidak ditemukan: {$src}");
        return false;
    }

    $img = @imagecreatefrompng($src);
    if (!$img) return false;

    $sw = imagesx($img);
    $sh = imagesy($img);

    $left   = max(0, (int)($rect['left']   ?? 0));
    $top    = max(0, (int)($rect['top']    ?? 0));
    $width  = max(1, (int)($rect['width']  ?? $sw));
    $height = max(1, (int)($rect['height'] ?? $sh));

    $right  = min($sw, $left + $width);
    $bottom = min($sh, $top + $height);
    $width  = max(1, $right - $left);
    $height = max(1, $bottom - $top);

    $dstImg = imagecreatetruecolor($width, $height);
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $trans = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
    imagefill($dstImg, 0, 0, $trans);

    imagecopyresampled($dstImg, $img, 0, 0, $left, $top, $width, $height, $width, $height);

    $ok = imagepng($dstImg, $dst, 9);
    imagedestroy($img);
    imagedestroy($dstImg);

    if ($ok) log_info("Crop berhasil: {$dst}");
    else log_error("Gagal menyimpan hasil crop: {$dst}");

    return $ok;
}

function crop_image_trim_border(string $src, string $dst, int $tolerance = 10): bool
{
    if (!file_exists($src)) return false;

    $img = @imagecreatefrompng($src);
    if (!$img) return false;

    $w = imagesx($img);
    $h = imagesy($img);

    if ($w <= 0 || $h <= 0) {
        imagedestroy($img);
        return false;
    }

    $bg = imagecolorat($img, 0, 0);
    $bgR = ($bg >> 16) & 0xFF;
    $bgG = ($bg >> 8)  & 0xFF;
    $bgB = $bg & 0xFF;

    $is_bg = function(int $r, int $g, int $b) use ($bgR, $bgG, $bgB, $tolerance): bool {
        return (abs($r - $bgR) <= $tolerance) && (abs($g - $bgG) <= $tolerance) && (abs($b - $bgB) <= $tolerance);
    };

    $col_is_bg = function($img, int $x, int $h) use ($is_bg): bool {
        for ($y = 0; $y < $h; $y += 3) {
            $c = imagecolorat($img, $x, $y);
            $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
            if (!$is_bg($r, $g, $b)) return false;
        }
        return true;
    };

    $row_is_bg = function($img, int $y, int $w) use ($is_bg): bool {
        for ($x = 0; $x < $w; $x += 3) {
            $c = imagecolorat($img, $x, $y);
            $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
            if (!$is_bg($r, $g, $b)) return false;
        }
        return true;
    };

    $left = 0; while ($left < $w && $col_is_bg($img, $left, $h)) $left++;
    $right = $w - 1; while ($right > $left && $col_is_bg($img, $right, $h)) $right--;
    $top = 0; while ($top < $h && $row_is_bg($img, $top, $w)) $top++;
    $bottom = $h - 1; while ($bottom > $top && $row_is_bg($img, $bottom, $w)) $bottom--;

    $newW = max(1, $right - $left + 1);
    $newH = max(1, $bottom - $top + 1);

    $dstImg = imagecreatetruecolor($newW, $newH);
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $trans = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
    imagefill($dstImg, 0, 0, $trans);

    imagecopyresampled($dstImg, $img, 0, 0, $left, $top, $newW, $newH, $newW, $newH);

    $ok = imagepng($dstImg, $dst, 9);
    imagedestroy($img);
    imagedestroy($dstImg);

    if ($ok) log_info("Auto-trim border berhasil: {$dst} ({$newW}x{$newH})");
    else log_error("Gagal menyimpan hasil auto-trim: {$dst}");

    return $ok;
}

function crop_image_area_relative(
    string $src,
    string $dst,
    float $leftPct,
    float $topPct,
    float $widthPct,
    float $heightPct
): bool {
    if (!file_exists($src)) return false;

    $size = @getimagesize($src);
    if ($size === false || $size[0] <= 0 || $size[1] <= 0) return false;

    [$w, $h] = $size;
    $rect = [
        'left'   => (int) round($w * $leftPct),
        'top'    => (int) round($h * $topPct),
        'width'  => (int) round($w * $widthPct),
        'height' => (int) round($h * $heightPct),
    ];

    return crop_image_area($src, $dst, $rect);
}
