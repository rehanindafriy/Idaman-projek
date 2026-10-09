<?php
declare(strict_types=1);
/**
 * Safe redirect: works even if some output already happened.
 * Use: redirect('index.php');
 */
if (!function_exists('redirect')) {
  function redirect(string $url) {
    if (!headers_sent()) {
      header('Location: ' . $url);
    } else {
      // Fallback when headers already sent
      echo '<script>location.replace(' . json_encode($url) . ');</script>';
      echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    }
    exit;
  }
}
