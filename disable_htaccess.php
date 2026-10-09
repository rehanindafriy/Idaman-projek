<?php
$base = __DIR__;
$cur  = $base.'/.htaccess';
$par  = dirname($base).'/.htaccess';

function ren($from,$to){
  if (file_exists($from)) {
    if (@rename($from,$to)) { echo "Renamed: $from -> $to\n"; return true; }
    echo "Gagal rename ($from). Permission: ".substr(sprintf('%o', fileperms($from)), -4)."\n";
  }
  return false;
}

if (!ren($cur, $base.'/.htaccess.off')) {
  if (!ren($par, dirname($base).'/.htaccess.off')) {
    echo "Tidak menemukan .htaccess di folder ini maupun parent.\n";
  }
}
