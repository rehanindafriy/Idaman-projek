<?php
declare(strict_types=1);

// ====== CONFIG DASAR ======
$BASE_URL = 'https://efidi.montaklo.id';

// ==== AMBIL PARAM U & CID ====
$uRaw   = isset($_GET['u'])   ? (string)$_GET['u']   : '';
$cidRaw = isset($_GET['cid']) ? (string)$_GET['cid'] : '';

$uSafe   = htmlspecialchars($uRaw,  ENT_QUOTES, 'UTF-8');
$cidSafe = htmlspecialchars($cidRaw, ENT_QUOTES, 'UTF-8');

// URL ke open_app_landing.php (bawa u & cid yang sama)
$query = http_build_query([
    'u'   => $uRaw,
    'cid' => $cidRaw,
], '', '&', PHP_QUERY_RFC3986);

$openAppUrl = $BASE_URL . '/open_app_landing.php' . ($query ? ('?' . $query) : '');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>efidi · Wait for it</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    *{box-sizing:border-box;}
    html,body{
      margin:0;
      padding:0;
      height:100%;
      width:100%;
    }
    body{
      background:#ffffff;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
                   Roboto, sans-serif;
      display:flex;
      align-items:center;
      justify-content:center;
      color:#111827;
    }
    .text{
      font-size:20px;
      font-weight:700;
      letter-spacing:0.12em;
      text-transform:uppercase;
    }
  </style>
</head>
<body>

<div class="text">WAIT FOR IT</div>

<script>
(function () {
  var openAppUrl = <?= json_encode($openAppUrl) ?>;

  var tg = (typeof window.Telegram !== 'undefined'
            && window.Telegram
            && window.Telegram.WebApp)
           ? window.Telegram.WebApp
           : null;

  function openOutsideTelegram() {
    var opened = null;

    // 1) coba pakai window.open (seringnya langsung ke browser default)
    try {
      opened = window.open(openAppUrl, '_blank');
    } catch (e) {
      opened = null;
    }

    // 2) kalau gagal, pakai openLink (API Telegram)
    if (!opened && tg && typeof tg.openLink === 'function') {
      try {
        tg.openLink(openAppUrl, { /* clients baru bisa pakai tryBrowser */
          try_instant_view: false
        });
      } catch (e) {
        // abaikan
      }
    }

    // 3) fallback terakhir: paksa location.href
    if (!opened) {
      try { window.location.href = openAppUrl; } catch (e) {}
    }
  }

  if (tg) {
    try { tg.ready && tg.ready(); } catch (e) {}

    // buka open_app_landing.php di luar Mini App
    setTimeout(openOutsideTelegram, 150);

    // tutup WebApp efidi supaya benar-benar hilang
    setTimeout(function () {
      try { tg.close(); } catch (e) {}
    }, 1200);

  } else {
    // bukan WebApp (misal dibuka dari browser)
    setTimeout(function () {
      window.location.href = openAppUrl;
    }, 200);
  }
})();
</script>

</body>
</html>
