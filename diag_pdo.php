<?php
echo "pdo: ".(extension_loaded('pdo') ? 'ON' : 'OFF')."\n";
echo "pdo_mysql: ".(extension_loaded('pdo_mysql') ? 'ON' : 'OFF')."\n";
try {
  require __DIR__.'/koneksi.php';
  $ok = (db() instanceof PDO) ? 'CONNECT OK' : 'CONNECT FAIL';
  echo "connect: $ok\n";
} catch (Throwable $e) {
  echo "err: ".$e->getMessage()."\n";
}
