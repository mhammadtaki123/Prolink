<?php
// /dev/peek.php — minimal, safe diagnostics
ini_set('display_errors','1'); ini_set('log_errors','1'); error_reporting(E_ALL);

$loaded = false;
foreach ([__DIR__ . '/../Lib/config.php', __DIR__ . '/../lib/config.php'] as $p) {
  if (is_file($p)) { require_once $p; $loaded = true; break; }
}
if (!defined('BASE_URL')) {
  define('BASE_URL', '/Prolink'); // match your folder
  function url(string $path=''): string { $path='/'.ltrim($path,'/'); return rtrim(BASE_URL,'/').$path; }
}

$DB_OK   = isset($GLOBALS['DB_OK']) ? (bool)$GLOBALS['DB_OK'] : false;
$DB_ERR  = $GLOBALS['DB_ERR_MSG'] ?? '';
$hasSvc  = $DB_OK && function_exists('table_exists') ? table_exists($conn,'services') : false;
$hasWrk  = $DB_OK && function_exists('table_exists') ? table_exists($conn,'workers')  : false;
$hasRev  = $DB_OK && function_exists('table_exists') ? table_exists($conn,'reviews')  : false;

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Prolink · peek</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
  <div class="max-w-3xl mx-auto px-4 py-6">
    <h1 class="text-xl font-bold">Prolink · Diagnostics</h1>
    <div class="mt-3 grid gap-2 text-sm">
      <div><b>BASE_URL:</b> <code><?= htmlspecialchars(BASE_URL) ?></code></div>
      <div><b>Config loaded:</b> <?= $loaded ? 'yes' : 'no' ?></div>
      <div><b>DB:</b> <?= $DB_OK ? 'connected ✓' : ('not connected ✗ ' . htmlspecialchars($DB_ERR)) ?></div>
      <div><b>Tables → services:</b> <?= $hasSvc?'yes':'no' ?> · workers: <?= $hasWrk?'yes':'no' ?> · reviews: <?= $hasRev?'yes':'no' ?></div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
      <a class="px-3 py-1.5 rounded bg-purple-600 text-white" href="<?= url('/index.php?safemode=1&diag=1') ?>">Open index (safe mode)</a>
      <a class="px-3 py-1.5 rounded bg-gray-900 text-white" href="<?= url('/index.php?diag=1') ?>">Open index (normal)</a>
      <a class="px-3 py-1.5 rounded border" href="<?= url('/login.php') ?>">Login</a>
      <a class="px-3 py-1.5 rounded border" href="<?= url('/register.php') ?>">Register</a>
    </div>

    <div class="mt-6">
      <h2 class="font-semibold">Recent PHP error log (tail)</h2>
      <pre class="mt-2 bg-white rounded-lg p-3 border overflow-auto text-xs"><?php
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
              echo htmlspecialchars(implode("\n", array_slice($lines, -120))), "\n\n";
            } else echo "(cannot read)\n\n";
          } else echo "(missing)\n\n";
        }
      ?></pre>
    </div>
  </div>
</body>
</html>

