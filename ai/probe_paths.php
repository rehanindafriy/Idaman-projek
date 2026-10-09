<?php
header('Content-Type: text/plain; charset=utf-8');
$base = __DIR__;
$candidates = [
  dirname($base).'/secrets/efidi_secrets.php',
  dirname($base,2).'/secrets/efidi_secrets.php',
  $base.'/../_private/secrets.php',
  $base.'/../../_private/secrets.php',
];
foreach ($candidates as $p) {
  echo ($p).'  => '.(is_file($p)?'FOUND':'MISSING')."\n";
  if (is_file($p)) {
    $arr = include $p;
    echo "  keys: ".implode(',', array_keys((array)$arr))."\n";
  }
}
