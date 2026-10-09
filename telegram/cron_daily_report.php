<?php
declare(strict_types=1);
chdir(__DIR__);                 // ke folder telegram/
$_GET['cron'] = 'report';       // trigger blok cron di webhook
$_GET['key']  = 'LaporanOrderBaiharian';  // harus sama dgn CRON_SECRET
require __DIR__.'/telegram_webhook.php';
