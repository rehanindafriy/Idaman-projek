<?php
declare(strict_types=1);

// =====================================================
//  TELEGRAM HELPER
// =====================================================

function telegram_request(string $method, array $params = [], array $files = []): ?array {
    $url = API_URL . '/' . $method;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (!empty($files)) {
        foreach ($files as $field => $path) {
            if (is_string($path) && file_exists($path)) {
                $params[$field] = new CURLFile($path);
            }
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }

    $resp = curl_exec($ch);
    if ($resp === false) {
        log_error("HTTP error ({$method}): " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        log_error("Telegram {$method} status {$code}: {$resp}");
        return null;
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        log_error("Telegram {$method} response not JSON: {$resp}");
        return null;
    }
    if (!($data['ok'] ?? false)) {
        log_error("Telegram {$method} not ok: {$resp}");
    }
    return $data;
}

function send_message($chat_id, string $text, ?array $reply_markup = null): void {
    $params = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($reply_markup !== null) {
        $params['reply_markup'] = json_encode($reply_markup);
    }

    telegram_request('sendMessage', $params);
    log_info("Pesan terkirim ke {$chat_id}: " . mb_substr($text, 0, 60) . '...');
}

function send_screenshot_to_telegram(
    string $image_path,
    string $caption,
    ?array $target_chat_ids = null,
    bool $delete_after = true
): void {
    global $GROUP_TARGETS;

    if (!file_exists($image_path)) {
        log_error("File screenshot tidak ditemukan: {$image_path}");
        return;
    }

    if ($target_chat_ids === null) {
        $chat_ids = $GROUP_TARGETS[$caption] ?? [];
    } else {
        $chat_ids = $target_chat_ids;
    }

    if (empty($chat_ids)) {
        log_warn("Tidak ada grup tujuan untuk caption: {$caption}");
        return;
    }

    $success = false;
    foreach ($chat_ids as $cid) {
        telegram_request(
            'sendPhoto',
            [
                'chat_id'    => $cid,
                'caption'    => $caption,
                'parse_mode' => 'HTML',
            ],
            [
                'photo'      => $image_path,
            ]
        );
        log_info("Screenshot {$image_path} terkirim ke {$cid} ({$caption})");
        $success = true;
    }

    if ($success && $delete_after) {
        @unlink($image_path);
        log_info("File {$image_path} dihapus setelah pengiriman.");
    }
}

function create_inline_keyboard(array $buttons): array {
    return ['inline_keyboard' => $buttons];
}

function answer_callback_query(string $callback_query_id): void {
    telegram_request('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
}

function send_yanti_reminder(): void {
    global $YANTI_REMINDER_TEXT;

    if (!ENABLE_YANTI_REMINDER) {
        log_info("send_yanti_reminder() di-skip karena ENABLE_YANTI_REMINDER=false.");
        return;
    }

    $chat_id = '-1002755104290';
    send_message($chat_id, $YANTI_REMINDER_TEXT);
}
