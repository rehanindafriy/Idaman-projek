<?php

// auth.php — session, CSRF, flash, login helpers, remember-me
declare(strict_types=1);
session_start();

define('TELEGRAM_BOT_TOKEN', '8018239392:AAGljEwie-tnSe7sl8GNxymACMakJg2iurs');
require_once __DIR__ . '/koneksi.php'; // $pdo

// ===== Flash message (Laravel-like) =====
function flash(string $key, ?string $val = null) {
  if ($val === null) {
    if (!isset($_SESSION['_flash'][$key])) return null;
    $v = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $v;
  } else {
    $_SESSION['_flash'][$key] = $val;
    return null;
  }
}

// ===== CSRF =====
function csrf_token(): string {
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['_csrf'];
}
function csrf_verify(?string $token): bool {
  return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
}

// ===== Current user & guards =====
// ===== Current user & guards =====

// Kalau $allowRemember = true → boleh auto-login dari cookie remember-me.
// Kalau false → hanya cek session (berguna untuk halaman guest seperti register/login).
function current_user(bool $allowRemember = true): ?array {
  // 1) Cek session dulu
  if (!empty($_SESSION['uid'])) {
    static $cache = null;
    if ($cache !== null) return $cache;

    global $pdo;
    $st = $pdo->prepare("
      SELECT id_akses, nama, username, role, status, done
      FROM akses_login
      WHERE id_akses = ?
      LIMIT 1
    ");
    $st->execute([(int)$_SESSION['uid']]);
    $cache = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$cache) { session_unset(); session_destroy(); return null; }
    return $cache;
  }

  // 2) Jangan auto-login kalau tidak diizinkan
  if (!$allowRemember) return null;

  // 3) Auto-login via cookie remember-me
  if (!empty($_COOKIE['es_r'])) {
    [$selector, $token] = array_pad(explode(':', $_COOKIE['es_r'], 2), 2, '');
    if ($selector && $token) {
      global $pdo;
      $st = $pdo->prepare("SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()");
      $st->execute([$selector]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        if (hash_equals($row['token_hash'], hash('sha256', $token))) {
          $_SESSION['uid'] = (int)$row['user_id'];
          // rotate token untuk keamanan
          rotate_remember_token((int)$row['user_id']);
          // ambil data user dari session (tanpa loop)
          return current_user(false);
        }
      }
    }
  }
  return null;
}

// Halaman yang butuh login
function require_auth(): void {
  if (!current_user(true)) {
    flash('err', 'Silakan login terlebih dahulu.');
    header('Location: index.php'); // login page = index.php
    exit;
  }
}

// Halaman guest (login/register). Di sini kita TIDAK mengaktifkan auto-login cookie.
function require_guest(): void {
  if (current_user(false)) {
    header('Location: ' . route_after_login($_SESSION['role'] ?? ''));
    exit;
  }
}

// ===== Session login/logout =====
function login_user(int $uid, bool $remember = false): void {
  $_SESSION['uid'] = $uid;
  if ($remember) {
    rotate_remember_token($uid);
  }
}

function rotate_remember_token(int $uid): void {
  $selector = bin2hex(random_bytes(6));
  $token = bin2hex(random_bytes(32));
  $hash = hash('sha256', $token);

  // hapus token lama user
  $GLOBALS['pdo']->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$uid]);
  $GLOBALS['pdo']->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))")->execute([$uid, $selector, $hash]);

  setcookie('es_r', $selector . ':' . $token, [
    'expires'  => time() + 60 * 60 * 24 * 30,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

function logout_user(): void {
  if (!empty($_COOKIE['es_r'])) {
    $parts = explode(':', $_COOKIE['es_r']);
    $selector = $parts[0] ?? '';
    if ($selector !== '') {
      $GLOBALS['pdo']->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$selector]);
    }
    setcookie('es_r', '', time() - 3600, '/');
  }
  session_unset();
  session_destroy();
}

// ===== Google reCAPTCHA v2 (checkbox) =====


function find_verif_column(PDO $pdo): string {
  static $col = null;
  if ($col !== null) return $col;
  $q1 = $pdo->query("SHOW COLUMNS FROM akses_login LIKE 'api_token'");
  if ($q1 && $q1->rowCount()) { $col = 'api_token'; return $col; }
  $q2 = $pdo->query("SHOW COLUMNS FROM akses_login LIKE 'verifikasi'");
  if ($q2 && $q2->rowCount()) { $col = 'verifikasi'; return $col; }
  // default fallback
  $col = 'api_token';
  return $col;
}

function do_login(array $row): void {
  $_SESSION['uid']   = (int)$row['id_akses'];
  $_SESSION['role']  = $row['role'] ?? null;
  $_SESSION['nama']  = $row['nama'] ?? '';
  $_SESSION['uname'] = $row['username'] ?? '';
}

function route_after_login(string $role): string {
  switch ($role) {
    case 'admin': return 'dashboard_admin.php';
    case 'ta':    return 'dashboard_ta.php';
    case 'tech':  return 'dashboard_tech.php';
    default:      return 'index.php';
  }
}
