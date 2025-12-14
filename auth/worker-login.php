<?php
declare(strict_types=1);
session_start();

/**
 * ProLink - Worker Login
 * Replace existing file at: /Prolink/auth/worker-login.php
 * Matches the USER login layout + adds "No account? Sign up" under the login button.
 */

$root = dirname(__DIR__);

// Load config (BASE_URL, $conn, helpers)
foreach ([$root . '/Lib/config.php', $root . '/lib/config.php', $root . '/config.php'] as $cfg) {
  if (is_file($cfg)) { require_once $cfg; break; }
}

// Base URL fallback
$baseUrl = $baseUrl ?? (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '/Prolink');

// Small helper (guarded)
if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$next = (string)($_GET['next'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Worker Login • ProLink</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php if (is_file($root . '/partials/navbar.php')) include $root . '/partials/navbar.php'; ?>

  <div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-4">Worker Login</h1>

    <?php if (!empty($_SESSION['error'])): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <?= h($_SESSION['error']); unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
        <?= h($_SESSION['success']); unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <form method="post"
          action="<?= h($baseUrl) ?>/worker/process_login.php"
          class="bg-white rounded-xl shadow p-6 space-y-4">
      <input type="hidden" name="next" value="<?= h($next) ?>">

      <div>
        <label class="block text-sm mb-1">Email</label>
        <input name="email" type="email" required class="w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm mb-1">Password</label>
        <div class="relative">
          <input id="worker_pass"
                 name="password"
                 type="password"
                 required
                 class="w-full border rounded px-3 py-2 pr-10"
                 oninput="toggleWorkerEye()">
          <button type="button"
                  id="worker_eye"
                  onclick="toggleWorkerPass()"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-600 text-sm"
                  style="display:none;"
                  aria-label="Show/Hide password">
            👁
          </button>
        </div>
      </div>

      <button class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-medium"
              type="submit">
        Login
      </button>

      <!-- Match USER login style: Sign up link under button -->
      <div class="text-sm text-center text-gray-600">
        No account?
        <a class="text-blue-700 underline" href="<?= h($baseUrl) ?>/auth/worker-register.php">Sign up</a>
      </div>

      <!-- Role switch links -->
      <div class="text-xs text-center text-gray-500 mt-2">
        User? <a class="underline" href="<?= h($baseUrl) ?>/auth/login.php">Log in here</a> •
        Admin? <a class="underline" href="<?= h($baseUrl) ?>/admin/login.php">Log in here</a>
      </div>
    </form>
  </div>

  <?php if (is_file($root . '/partials/footer.php')) include $root . '/partials/footer.php'; ?>

  <script>
    function toggleWorkerEye() {
      const input = document.getElementById('worker_pass');
      const eye   = document.getElementById('worker_eye');
      if (!input || !eye) return;
      eye.style.display = input.value.length ? 'block' : 'none';
    }
    function toggleWorkerPass() {
      const input = document.getElementById('worker_pass');
      if (!input) return;
      input.type = (input.type === 'password') ? 'text' : 'password';
    }
  </script>
</body>
</html>
