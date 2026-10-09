<?php
/**
 * API Endpoint untuk Telegram Bot (Monika)
 * Berfungsi untuk mengecek apakah user Telegram (chat_id) berhak mengakses bot.
 * 
 * Silakan upload file ini ke public_html atau root domain https://efidi.montaklo.id/
 */

// Mencegah error reporting merusak output JSON
error_reporting(0);
header('Content-Type: application/json');

$DB_HOST = 'localhost';
$DB_NAME = 'u408986314_klkfoo';
$DB_USER = 'u408986314_klkfoo';
$DB_PASS = 'Efidi123';

try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Ambil chat_id dari request GET
$chat_id = isset($_GET['chat_id']) ? trim($_GET['chat_id']) : '';

if (empty($chat_id)) {
    echo json_encode(['success' => false, 'error' => 'chat_id parameter is required']);
    exit;
}

try {
    // Mengecek dari tabel akses_bot sesuai instruksi
    $stmt = $pdo->prepare("SELECT id_akses_bot, username, status FROM akses_bot WHERE chat_id = ? LIMIT 1");
    $stmt->execute([$chat_id]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['status'] === 'active') {
            echo json_encode([
                'success' => true, 
                'authorized' => true, 
                'message' => 'User is authorized',
                'username' => $user['username']
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'authorized' => false, 
                'message' => 'User is suspended'
            ]);
        }
    } else {
        echo json_encode([
            'success' => true, 
            'authorized' => false, 
            'message' => 'User not found in akses_bot'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Query failed']);
    exit;
}
?>
