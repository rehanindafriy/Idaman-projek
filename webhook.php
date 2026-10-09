<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/secrets.local.php';

// 1. Menerima Data dari Fonnte (Webhook)
$input = file_get_contents('php://input');
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - DATA MASUK: " . $input . "\n", FILE_APPEND);

$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}

$sender = $data['sender'] ?? '';
$message = $data['message'] ?? '';

if (empty($sender) || empty($message)) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - GAGAL: Sender/Message kosong\n", FILE_APPEND);
    echo json_encode(["status" => false, "reason" => "Data kosong."]);
    exit;
}

// 2. Minta Jawaban dari Groq AI (Llama 3.1)
$groq_url = "https://api.groq.com/openai/v1/chat/completions";
$groq_data = [
    "model" => "llama-3.1-8b-instant",
    "messages" => [
        [
            "role" => "system",
            "content" => "Kamu adalah AI Asisten Pribadi dari Nur Hadi Imamuddin (seorang Software Engineer profesional).
Tugasmu adalah menjawab pesan WhatsApp dari klien atau pengunjung portofolio Nur Hadi.
Jawab dengan bahasa Indonesia yang sopan, ramah, dan profesional.
Jangan terlalu panjang, maksimal 3-4 kalimat saja.
Jika ada yang bertanya tentang Nur Hadi, berikan informasi positif tentang skill programming dan portofolio-nya.
Jika ada yang ingin membuat website atau aplikasi, arahkan mereka untuk berdiskusi lebih lanjut."
        ],
        [
            "role" => "user",
            "content" => $message
        ]
    ],
    "max_tokens" => 300
];

$ch = curl_init($groq_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $groq_api_key
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($groq_data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$groq_response = curl_exec($ch);
curl_close($ch);

file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - GROQ AI: " . $groq_response . "\n", FILE_APPEND);

$groq_json = json_decode($groq_response, true);
$ai_reply = "Mohon maaf, AI sedang dalam pemeliharaan. Silakan hubungi Nur Hadi langsung.";

if (isset($groq_json['choices'][0]['message']['content'])) {
    $ai_reply = trim($groq_json['choices'][0]['message']['content']);
}

// 3. Kirim Balasan AI kembali ke pengirim melalui Fonnte
$fonnte_url = "https://api.fonnte.com/send";
$fonnte_data = [
    "target" => $sender,
    "message" => $ai_reply
];

$ch2 = curl_init($fonnte_url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "Authorization: $fonnte_token"
]);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $fonnte_data);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_TIMEOUT, 15);

$fonnte_response = curl_exec($ch2);
curl_close($ch2);

file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - FONNTE SEND: " . $fonnte_response . "\n====================\n", FILE_APPEND);

echo json_encode([
    "status" => true,
    "ai_reply" => $ai_reply,
    "fonnte_status" => json_decode($fonnte_response, true)
]);
?>
