<?php
header('Content-Type: text/plain; charset=utf-8');

$paths = [
  __DIR__ . '/../prolink_error.log',
  '/Applications/XAMPP/xamppfiles/logs/php_error_log',
  '/Applications/XAMPP/xamppfiles/logs/error_log',
];

foreach ($paths as $p) {
  echo "=== $p ===\n";
  if (is_file($p)) {
    $txt = @file_get_contents($p);
    if ($txt !== false) {
      $lines = explode("\n", $txt);
      $tail  = array_slice($lines, -80); // last ~80 lines
      echo implode("\n", $tail), "\n\n";
    } else {
      echo "(cannot read)\n\n";
    }
  } else {
    echo "(missing)\n\n";
  }
}
