<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function http_get_json(string $url): ?array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
  ]);
  $res = curl_exec($ch);
  $rc  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  if ($rc >= 200 && $rc < 300 && $res) return json_decode($res, true);
  return null;
}

$versions = defined('GEMINI_API_VERSIONS') ? GEMINI_API_VERSIONS : ['v1','v1beta'];
$out = [];
foreach ($versions as $ver) {
  $url = 'https://generativelanguage.googleapis.com/'.$ver.'/models?key='.rawurlencode(GEMINI_API_KEY);
  $j = http_get_json($url) ?? [];
  $names = array_map(fn($m)=>$m['name'] ?? '', $j['models'] ?? []);
  $methods = [];
  foreach (($j['models'] ?? []) as $m) {
    $id = preg_replace('~^models/~','', (string)($m['name'] ?? ''));
    $methods[$id] = $m['supportedGenerationMethods'] ?? [];
  }
  $out[$ver] = ['names'=>$names, 'methods'=>$methods];
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
