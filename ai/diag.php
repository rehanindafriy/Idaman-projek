<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';

function http($u,$o=[]){ $c=curl_init($u);
  curl_setopt_array($c,$o+[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4]);
  $r=curl_exec($c); $rc=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); $e=$r===false?curl_error($c):''; curl_close($c);
  return [$rc,$r,$e];
}
function mask($s){ if($s==='') return ''; return substr($s,0,5).'…'.substr($s,-4); }

$out = ['time'=>date('c')];
$out['secrets_path'] = defined('EFIDI_SECRETS_PATH') ? EFIDI_SECRETS_PATH : null;
$out['openrouter_meta'] = [
  'key_present' => (defined('OPENROUTER_KEY') && OPENROUTER_KEY!==''),
  'key_mask'    => (defined('OPENROUTER_KEY') && OPENROUTER_KEY!=='') ? mask(OPENROUTER_KEY) : null,
  'model'       => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : null,
  'site'        => defined('OPENROUTER_SITE')  ? OPENROUTER_SITE  : null,
];

if (defined('OPENROUTER_KEY') && OPENROUTER_KEY!=='') {
  $payload=json_encode(['model'=>OPENROUTER_MODEL,'messages'=>[['role'=>'user','content'=>'ping']],'max_tokens'=>5]);
  [$rc,$res,$err]=http('https://openrouter.ai/api/v1/chat/completions',[
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'Authorization: Bearer '.OPENROUTER_KEY,
      'HTTP-Referer: '.OPENROUTER_SITE,
      'X-Title: efidi',
    ],
    CURLOPT_POSTFIELDS=>$payload
  ]);
  $out['openrouter']=['rc'=>$rc,'ok'=>$rc>=200&&$rc<300,'body'=>substr((string)$res,0,200),'err'=>$err];
} else {
  $out['openrouter']=['ok'=>false,'err'=>'No key'];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
