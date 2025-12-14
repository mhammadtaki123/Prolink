<?php
session_start();
$loaded = false;
foreach ([__DIR__.'/Lib/config.php', __DIR__.'/lib/config.php'] as $p) {
  if (is_file($p)) { require_once $p; $loaded = true; break; }
}
if (!$loaded) { http_response_code(500); exit('config not found'); }
$next = (string)($_GET['next'] ?? '');
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<?php include __DIR__.'/partials/navbar.php'; ?>
<div class="max-w-xl mx-auto p-6">
  <h1 class="text-2xl font-bold mb-4">Login</h1>
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div>
  <?php endif; ?>
  <form method="post" action="<?= url('/process_login.php') ?>" class="space-y-4">
    <input type="hidden" name="role" value="user">
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <div>
      <label class="block text-sm mb-1">Email</label>
      <input name="email" type="email" required class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm mb-1">Password</label>
      <input name="password" type="password" required class="w-full border rounded px-3 py-2">
    </div>
    <button class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">Login</button>
  </form>
</div>
