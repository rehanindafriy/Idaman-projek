<?php
// hapus_foto.php
session_start();
require_once __DIR__.'/koneksi.php';

$FOTO_FS_DIR = rtrim((realpath(__DIR__.'/bot/foto') ?: (__DIR__.'/bot/foto')), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

$kode = trim($_GET['kode'] ?? '');
if ($kode === '') { header('Location: evidensi.php'); exit; }

$kodeSafe = preg_replace('/[^A-Za-z0-9_\-\.]/','_', $kode);
$dir = $FOTO_FS_DIR.$kodeSafe.DIRECTORY_SEPARATOR;

if (is_dir($dir)) {
  foreach (glob($dir.'*') ?: [] as $f) { if (is_file($f)) @unlink($f); }
}
header('Location: '.$_SERVER['HTTP_REFERER']);
