<?php
declare(strict_types=1);
$fname = basename((string)($_GET['f'] ?? ''));
if ($fname === '') { http_response_code(400); exit('Bad request'); }

$base = realpath(__DIR__ . '/bot/file') ?: (__DIR__ . '/bot/file');
$base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$path = $base . $fname;

if (!is_file($path)) { http_response_code(404); exit('File not found'); }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="'.rawurlencode($fname).'"');
readfile($path);
