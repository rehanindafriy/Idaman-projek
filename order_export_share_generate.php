<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$response = [
    'ok'            => false,
    'msg'           => '',
    'share_url'     => '',
    'expires_label' => '',
    'expires_ts'    => 0,   // UNIX timestamp (detik)
];

try {
    require_once __DIR__ . '/koneksi.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Metode akses tidak diizinkan.');
    }

    $idOrder = isset($_POST['id_order']) ? (int)$_POST['id_order'] : 0;
    if ($idOrder <= 0) {
        throw new RuntimeException('ID order tidak valid.');
    }

    // Pastikan order ada
    $st = $pdo->prepare("
        SELECT id_order, no_sc
        FROM orders
        WHERE id_order = :id
        LIMIT 1
    ");
    $st->execute([':id' => $idOrder]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Data order tidak ditemukan.');
    }

    $noSc = trim((string)($row['no_sc'] ?? ''));

    // === TOKEN LINK 1 JAM ===
    $now     = time();
    $expires = $now + 3600; // 1 jam ke depan

    // payload yang akan ditandatangani
    $payload = $idOrder . '|' . $expires;

    // RAHASIA — samakan dengan di order_export_share_view.php
    $secret = 'GANTI_INI_DENGAN_STRING_RAHASIA_PANJANG_EFIDI_2025';

    // base64url encode
    $b64url = function (string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    };

    $sigRaw = hash_hmac('sha256', $payload, $secret, true);
    $token  = $b64url($payload) . '.' . $b64url($sigRaw);

    // Base URL dinamis
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = $scheme . '://' . $host;

    // Link share → ke view yang cek token
    $shareUrl = $base . '/order_export_share_view.php?t=' . $token;

    $response['ok']            = true;
    $response['share_url']     = $shareUrl;
    $response['expires_ts']    = $expires;                     // dipakai JS untuk countdown
    $response['expires_label'] = date('d M Y H:i', $expires);  // cadangan saja

} catch (Throwable $e) {
    $response['ok'] = false;
    $response['msg'] = $e->getMessage();
}

echo json_encode($response);
