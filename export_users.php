<?php
/**
 * EXPORT DATA USER - Tampilkan daftar user untuk di-copy ke VPS
 * Buka URL ini di browser, copy hasilnya, paste ke file authorized-users.json di VPS
 */

error_reporting(0);
header('Content-Type: application/json');

$DB_HOST = 'localhost';
$DB_NAME = 'u408986314_klkfoo';
$DB_USER = 'u408986314_klkfoo';
$DB_PASS = 'Efidi123';

try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("SELECT chat_id, username, status FROM akses_bot");
    $users = $stmt->fetchAll();

    // Output langsung dalam format yang siap di-paste ke VPS
    echo json_encode($users, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}
?>
