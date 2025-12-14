<?php
session_start();

// Load config (BASE_URL, DB, helpers like url())
$loaded = false;
foreach ([__DIR__ . '/../Lib/config.php', __DIR__ . '/../lib/config.php'] as $p) {
  if (is_file($p)) {
    require_once $p;
    $loaded = true;
    break;
  }
}
if (!$loaded) {
  http_response_code(500);
  exit('config.php not found for admin login');
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$next    = (string)($_GET['next'] ?? '');

function h(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login • ProLink</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-4">Admin Login</h1>

    <?php if (!empty($_SESSION['error'])): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <?= h($_SESSION['error']); unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <form method="post"
          action="<?= function_exists('url') ? url('/admin/process_login.php') : $baseUrl . '/admin/process_login.php' ?>"
          class="bg-white rounded-xl shadow p-6 space-y-4">

      <input type="hidden" name="next" value="<?= h($next) ?>">

      <div>
        <label class="block text-sm mb-1">Email</label>
        <input
          name="email"
          type="email"
          required
          class="w-full border rounded px-3 py-2"
        >
      </div>

      <div>
        <label class="block text-sm mb-1">Password</label>
        <div class="relative">
          <input
            id="admin_pass"
            name="password"
            type="password"
            required
            class="w-full border rounded px-3 py-2 pr-10"
            oninput="toggleAdminEye()"
          >
          <button
            type="button"
            id="admin_eye"
            onclick="toggleAdminPass()"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-600 text-sm"
            style="display:none;"
          >
            👁
          </button>
        </div>
      </div>

      <button
  class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-medium"
  type="submit"
>
  Login
</button>


      <div class="text-xs text-center text-gray-500 mt-3">
        User? <a class="underline" href="<?= $baseUrl ?>/auth/login.php">Log in here</a> •
        Worker? <a class="underline" href="<?= $baseUrl ?>/auth/worker-login.php">Log in here</a>
      </div>
    </form>
  </div>

  <script>
    function toggleAdminEye() {
      const input = document.getElementById('admin_pass');
      const eye   = document.getElementById('admin_eye');
      if (!input || !eye) return;
      // show eye only when there is some text
      eye.style.display = input.value.length ? 'block' : 'none';
    }

    function toggleAdminPass() {
      const input = document.getElementById('admin_pass');
      if (!input) return;
      input.type = (input.type === 'password') ? 'text' : 'password';
    }
  </script>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
