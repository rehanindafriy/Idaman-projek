<?php
// akses_login_api.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

function jexit($ok, $msg='', $extra=[]) {
  echo json_encode(['ok'=>$ok, 'msg'=>$msg] + $extra);
  exit;
}

// ——— helper: generate N digit acak (tanpa leading rule ribet) ———
function rand_digits(int $n = 7): string {
  $s = '';
  for ($i=0; $i<$n; $i++) $s .= (string) random_int(0,9);
  return $s;
}

// optional: validasi CSRF jika kamu punya csrf_token()
// if (function_exists('csrf_verify') && !csrf_verify($_POST['_token'] ?? '')) {
//   jexit(false, 'CSRF tidak valid');
// }

$action = $_POST['action'] ?? '';

if ($action === 'create_akses') {
  try {
    $nama     = trim((string)($_POST['nama'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role     = strtolower(trim((string)($_POST['role'] ?? '')));
    $chat_id  = trim((string)($_POST['chat_id'] ?? ''));

    if ($username === '') jexit(false, 'Username wajib diisi');
    if (strlen($password) < 6) jexit(false, 'Password minimal 6 karakter');
    if (!in_array($role, ['admin','ta','tech','monika'], true)) jexit(false, 'Role tidak valid');

    // chat_id opsional: izinkan negatif (grup), angka saja
    if ($chat_id !== '' && !preg_match('/^-?\d{5,20}$/', $chat_id)) {
      jexit(false, 'Format chat_id tidak valid');
    }

    // cek username unik
    $st = $pdo->prepare("SELECT id_akses FROM akses_login WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    if ($st->fetch()) jexit(false, 'Username sudah dipakai');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $veri = rand_digits(7);                 // 7 digit acak
    $stat = 'active';
    $done = 'yes';

    $sql = "INSERT INTO akses_login
              (nama, username, password, role, status, chat_id, verifikasi, done)
            VALUES
              (:nama, :username, :password, :role, :status, :chat_id, :verifikasi, :done)";
    $ins = $pdo->prepare($sql);
    $ok = $ins->execute([
      ':nama'       => $nama,
      ':username'   => $username,
      ':password'   => $hash,
      ':role'       => $role,
      ':status'     => $stat,
      // biarkan PDO kirim string; MySQL BIGINT akan konversi, null jika kosong
      ':chat_id'    => ($chat_id === '' ? null : $chat_id),
      ':verifikasi' => $veri,
      ':done'       => $done,
    ]);
    if (!$ok) jexit(false, 'Gagal menyimpan');

    $id = (int)$pdo->lastInsertId();

    // kirim balik ringkasan untuk (opsional) append baris baru di UI
    jexit(true, '', [
      'row' => [
        'id_akses'   => $id,
        'nama'       => $nama,
        'username'   => $username,
        'role'       => $role,
        'status'     => $stat,
        'chat_id'    => $chat_id,
        'verifikasi' => $veri,
        'done'       => $done,
      ]
    ]);

  } catch (Throwable $e) {
    jexit(false, 'Error: '.$e->getMessage());
  }
}

/* …biarkan handler-mu yang lain tetap ada…
   contoh: save_role_status, get_state, publish_code, renew_code, dll. */

jexit(false, 'Action tidak dikenali');
