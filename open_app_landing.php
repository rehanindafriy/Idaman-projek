<?php
declare(strict_types=1);

// ====== CONFIG DASAR (WEB) ======
$BASE_URL     = 'https://efidi.montaklo.id';
$SEARCH_WEB   = $BASE_URL . '/search_bot.php';
$APK_URL      = $BASE_URL . '/apk/efidi_search.apk';  
$PACKAGE_NAME = 'id.efidi.efidi';                     

// ====== CONFIG DEEP LINK ======
$DEEPLINK_SCHEME = 'https';
$DEEPLINK_HOST   = 'efidi.montaklo.id';
$DEEPLINK_PATH   = '/open_app_landing.php';

// ==== AMBIL PARAM U & CID ====
$uRaw   = isset($_GET['u'])   ? (string)$_GET['u']   : '';
$cidRaw = isset($_GET['cid']) ? (string)$_GET['cid'] : '';

$uSafe   = htmlspecialchars($uRaw,  ENT_QUOTES, 'UTF-8');
$cidSafe = htmlspecialchars($cidRaw, ENT_QUOTES, 'UTF-8');

$queryLanding = http_build_query([
    'u'   => $uRaw,
    'cid' => $cidRaw,
], '', '&', PHP_QUERY_RFC3986);

$landingUrl   = $BASE_URL . '/open_app_landing.php' . ($queryLanding ? ('?' . $queryLanding) : '');
$searchWebUrl = $SEARCH_WEB;

$searchGateUrl = $searchWebUrl;

// Langsung arahkan semua device (termasuk Android & iOS) ke halaman WebApp (search_bot.php)
header('Location: ' . $searchWebUrl);
exit;
