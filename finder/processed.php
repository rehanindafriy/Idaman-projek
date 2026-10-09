<?php
// finder/processed.php
// Halaman perantara WebApp: cek akses_bot → redirect ke search_bot / search_gate / bot.

declare(strict_types=1);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/koneksi.php';

// config telegram
$cfgCandidates = [
  $ROOT . '/telegram/telegram_config.php',
  $ROOT . '/telegram_config.php',
  __DIR__ . '/telegram_config.php',
];
foreach ($cfgCandidates as $p) {
  if (is_file($p)) {
    require_once $p;
    break;
  }
}

if (!defined('FINDER_BOT_USERNAME')) {
  define('FINDER_BOT_USERNAME', 'searchingefidi_bot'); // ganti kalau beda
}
if (!defined('FINDER_SEARCH_URL')) {
  define('FINDER_SEARCH_URL', 'https://efidi.montaklo.id/finder/search_gate.php');
}
if (!defined('FINDER_SEARCH_DIRECT_URL')) {
  define('FINDER_SEARCH_DIRECT_URL', 'https://efidi.montaklo.id/search_bot.php');
}

if (!function_exists('e')) {
  function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

// bisa dari GET (kalau nanti mau) atau POST hasil form
$username = trim((string)($_GET['u'] ?? $_POST['u'] ?? ''));
$chatId   = (int)($_GET['cid'] ?? $_POST['cid'] ?? 0);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_search'])) {
  if ($username === '' || $chatId === 0) {
    $error = 'Data Telegram tidak terbaca. Silakan tutup dan buka ulang Finder Search dari grup.';
  } else {
    try {
      // cek di akses_bot
      $sql = "SELECT id_akses_bot, chat_id, status
              FROM akses_bot
              WHERE LOWER(username) = LOWER(?)
              LIMIT 1";
      $st  = $pdo->prepare($sql);
      $st->execute([$username]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      if ($row) {
        $idBot     = (int)$row['id_akses_bot'];
        $oldChatId = (int)($row['chat_id'] ?? 0);
        $stStatus  = strtolower(trim((string)($row['status'] ?? '')));

        if ($stStatus === '') {
          $stStatus = 'suspended';
        }

        // sinkron chat_id
        if ($chatId !== 0 && $chatId !== $oldChatId) {
          $u = $pdo->prepare("UPDATE akses_bot SET chat_id = ? WHERE id_akses_bot = ?");
          $u->execute([$chatId, $idBot]);
        } elseif ($oldChatId !== 0 && $chatId === 0) {
          $chatId = $oldChatId;
        }

        $qs = http_build_query([
          'u'   => $username,
          'cid' => $chatId,
        ]);

        if ($stStatus === 'active') {
          $base = FINDER_SEARCH_DIRECT_URL ?: FINDER_SEARCH_URL;
        } else {
          $base = FINDER_SEARCH_URL;
        }

        $target = rtrim($base, '?') . '?' . $qs;

        header('Location: ' . $target);
        ?>
        <!doctype html>
        <html lang="id">
        <head>
          <meta charset="utf-8">
          <title>Mengarahkan...</title>
          <meta http-equiv="refresh" content="0;url=<?= e($target) ?>">
        </head>
        <body style="font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif; text-align:center; padding:40px;">
          <h2>Mengarahkan ke Finder Search...</h2>
          <p>Jika tidak otomatis, klik tombol di bawah:</p>
          <p><a href="<?= e($target) ?>">Buka Finder Search</a></p>
          <script>
            setTimeout(function () {
              window.location.href = <?= json_encode($target) ?>;
            }, 100);
          </script>
        </body>
        </html>
        <?php
        exit;
      } else {
        // tidak ada di akses_bot → arahkan ke BOT
        $botUser = (defined('FINDER_BOT_USERNAME') && FINDER_BOT_USERNAME !== '')
          ? ltrim(FINDER_BOT_USERNAME, '@')
          : 'searchingefidi_bot';

        $target = 'https://t.me/' . $botUser . '?start=finder_from_group';

        header('Location: ' . $target);
        ?>
        <!doctype html>
        <html lang="id">
        <head>
          <meta charset="utf-8">
          <title>Mengarahkan ke BOT...</title>
          <meta http-equiv="refresh" content="0;url=<?= e($target) ?>">
        </head>
        <body style="font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif; text-align:center; padding:40px;">
          <h2>Mengarahkan ke BOT FINDER EFIDI...</h2>
          <p>Silakan mulai BOT di Telegram. Jika tidak otomatis, klik tombol di bawah:</p>
          <p><a href="<?= e($target) ?>">Buka BOT FINDER</a></p>
          <script>
            setTimeout(function () {
              window.location.href = <?= json_encode($target) ?>;
            }, 100);
          </script>
        </body>
        </html>
        <?php
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Terjadi kesalahan saat memproses data. Silakan coba beberapa saat lagi.';
    }
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Finder Search · Processed</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <style>
    :root {
      --tosca: #00a3ad;
      --tosca-soft: #e0f7f8;
      --bg: #f4f5f7;
      --radius: 14px;
    }
    body {
      margin: 0;
      padding: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--bg);
      color: #222;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .card {
      background: #fff;
      border-radius: var(--radius);
      box-shadow: 0 8px 24px rgba(0,0,0,0.06);
      padding: 24px 20px;
      max-width: 420px;
      width: 100%;
      box-sizing: border-box;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 4px 10px;
      font-size: 11px;
      border-radius: 999px;
      background: var(--tosca-soft);
      color: var(--tosca);
      margin-bottom: 10px;
      font-weight: 600;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }
    h1 {
      font-size: 18px;
      margin: 0 0 8px;
    }
    p {
      font-size: 14px;
      margin: 4px 0;
      line-height: 1.5;
    }
    .meta {
      font-size: 12px;
      color: #666;
      margin-top: 8px;
    }
    .meta code {
      font-size: 11px;
      background: #f1f1f4;
      padding: 2px 6px;
      border-radius: 6px;
    }
    .btn-main {
      margin-top: 18px;
      width: 100%;
      border: none;
      border-radius: 999px;
      padding: 10px 16px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      background: linear-gradient(135deg, #00a3ad, #33c7cf);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .btn-main:active {
      transform: translateY(1px);
    }
    .btn-main span.icon {
      font-size: 16px;
    }
    .msg-error {
      margin-top: 10px;
      padding: 8px 10px;
      border-radius: 10px;
      background: #ffeaea;
      color: #b00020;
      font-size: 12px;
    }
    .msg-info {
      margin-top: 10px;
      padding: 8px 10px;
      border-radius: 10px;
      background: #f1f8ff;
      color: #075985;
      font-size: 12px;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="badge">Finder Processed</div>
    <h1>Finder Search · EFIDI</h1>
    <p>Halaman ini akan mengecek akses BOT FINDER kamu, lalu mengarahkan ke halaman pencarian yang sesuai.</p>

    <p class="meta">
      Username: <code id="meta-username"><?= $username !== '' ? '@'.e($username) : '(menunggu data Telegram...)' ?></code><br>
      Chat ID : <code id="meta-chatid"><?= $chatId !== 0 ? e((string)$chatId) : '(menunggu data Telegram...)' ?></code>
    </p>

    <?php if ($error): ?>
      <div class="msg-error"><?= e($error) ?></div>
    <?php else: ?>
      <div class="msg-info">
        Klik tombol <b>SEARCH HERE</b> di bawah untuk mulai pengecekan akses BOT.
      </div>
      <form method="post" id="frm-main">
        <input type="hidden" name="u"   id="field-u"   value="<?= e($username) ?>">
        <input type="hidden" name="cid" id="field-cid" value="<?= $chatId ? e((string)$chatId) : '' ?>">
        <button class="btn-main" type="submit" name="do_search" value="1">
          <span class="icon">🔎</span>
          <span>SEARCH HERE</span>
        </button>
      </form>
    <?php endif; ?>
  </div>

  <script>
    (function () {
      const tg = window.Telegram && window.Telegram.WebApp;
      if (!tg) return;

      tg.ready();
      tg.expand();

      const user = tg.initDataUnsafe && tg.initDataUnsafe.user ? tg.initDataUnsafe.user : null;
      if (!user) return;

      let uname = user.username || "";
      if (!uname && user.id) {
        // fallback alias mirip ALLOW_UID_ALIAS
        uname = "tg_" + user.id;
      }

      const uField   = document.getElementById("field-u");
      const cidField = document.getElementById("field-cid");
      const metaUser = document.getElementById("meta-username");
      const metaCid  = document.getElementById("meta-chatid");

      if (uField && !uField.value && uname) {
        uField.value = uname;
      }
      if (cidField && !cidField.value && user.id) {
        cidField.value = String(user.id);
      }

      if (metaUser && uname) {
        metaUser.textContent = "@"+uname;
      }
      if (metaCid && user.id) {
        metaCid.textContent = String(user.id);
      }
    })();
  </script>
</body>
</html>
