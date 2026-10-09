<?php
declare(strict_types=1);

// SECRET harus sama dengan di order_export_share_generate.php
$secret = 'GANTI_INI_DENGAN_STRING_RAHASIA_PANJANG_EFIDI_2025';

// base64url decode helper
$base64url_decode = function (string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return (string) base64_decode(strtr($data, '-_', '+/'));
};

// ambil token dari query ?t=...
$token = isset($_GET['t']) ? trim((string)$_GET['t']) : '';
if ($token === '') {
    http_response_code(400);
    echo 'Link tidak valid (token kosong).';
    exit;
}

// token: "<payload_b64url>.<sig_b64url>"
$parts = explode('.', $token);
if (count($parts) !== 2) {
    http_response_code(400);
    echo 'Link tidak valid (format token salah).';
    exit;
}

list($payloadB64, $sigB64) = $parts;

// decode payload & signature
$payload = $base64url_decode($payloadB64);
$sig     = $base64url_decode($sigB64);

if ($payload === '' || $sig === '') {
    http_response_code(400);
    echo 'Link tidak valid (decode gagal).';
    exit;
}

// cek signature HMAC
$expectedSig = hash_hmac('sha256', $payload, $secret, true);
if (!hash_equals($expectedSig, $sig)) {
    http_response_code(403);
    echo 'Link tidak sah atau sudah diubah.';
    exit;
}

// payload: "id_order|timestamp_expired"
$pieces = explode('|', $payload);
if (count($pieces) !== 2) {
    http_response_code(400);
    echo 'Link tidak valid (payload rusak).';
    exit;
}

$idOrder = (int)$pieces[0];
$expires = (int)$pieces[1];

if ($idOrder <= 0) {
    http_response_code(400);
    echo 'ID order di dalam link tidak valid.';
    exit;
}

if ($expires <= 0 || time() > $expires) {
    // Kalau sudah lewat 1 jam → arahkan ke halaman offside
    header('Location: offside.php');
    exit;
}

// Kalau lolos semua, redirect ke halaman print
$query = http_build_query([
    'id_order' => $idOrder,
    'mode'     => 'print',   // pakai mode print yang sudah kamu buat
]);

header('Location: order_export_print.php?' . $query);
exit;
