<?php

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); exit('config.php not found'); }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// Admin auth
if (empty($_SESSION['admin_id']) && (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin')) {
  $next = $baseUrl . '/admin/edit-user.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '');
  header('Location: ' . $baseUrl . '/admin/login.php?next=' . urlencode($next));
  exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500);
  exit('DB connection missing');
}

function h($s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
  header('Location: ' . $baseUrl . '/admin/manage-users.php?err=' . urlencode('Missing user id'));
  exit;
}

$hasAddress = function_exists('col_exists') ? col_exists($conn, 'users', 'address') : true;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'save') {
    $full    = trim((string)($_POST['full_name'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($full === '') {
      header('Location: ' . $baseUrl . '/admin/edit-user.php?id=' . $user_id . '&err=' . urlencode('Full name is required'));
      exit;
    }

    if ($hasAddress) {
      $st = $conn->prepare('UPDATE users SET full_name=?, phone=?, address=? WHERE user_id=?');
      if (!$st) { header('Location: ' . $baseUrl . '/admin/edit-user.php?id=' . $user_id . '&err=' . urlencode('Prepare failed: ' . $conn->error)); exit; }
      $st->bind_param('sssi', $full, $phone, $address, $user_id);
    } else {
      $st = $conn->prepare('UPDATE users SET full_name=?, phone=? WHERE user_id=?');
      if (!$st) { header('Location: ' . $baseUrl . '/admin/edit-user.php?id=' . $user_id . '&err=' . urlencode('Prepare failed: ' . $conn->error)); exit; }
      $st->bind_param('ssi', $full, $phone, $user_id);
    }

    $st->execute();
    $st->close();

    header('Location: ' . $baseUrl . '/admin/manage-users.php?msg=' . urlencode('User updated'));
    exit;
  }

  if ($action === 'reset_password') {
    $new = (string)($_POST['new_password'] ?? '');
    if (strlen($new) < 6) {
      header('Location: ' . $baseUrl . '/admin/edit-user.php?id=' . $user_id . '&err=' . urlencode('Password must be at least 6 characters'));
      exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $st = $conn->prepare('UPDATE users SET password=? WHERE user_id=?');
    if (!$st) { header('Location: ' . $baseUrl . '/admin/edit-user.php?id=' . $user_id . '&err=' . urlencode('Prepare failed: ' . $conn->error)); exit; }
    $st->bind_param('si', $hash, $user_id);
    $st->execute();
    $st->close();

    header('Location: ' . $baseUrl . '/admin/manage-users.php?msg=' . urlencode('Password updated'));
    exit;
  }
}

// Load user
$sel = 'user_id, full_name, email, phone' . ($hasAddress ? ', address' : '');
$st = $conn->prepare("SELECT $sel FROM users WHERE user_id=? LIMIT 1");
if (!$st) { http_response_code(500); exit('Prepare failed: ' . h($conn->error)); }
$st->bind_param('i', $user_id);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
  header('Location: ' . $baseUrl . '/admin/manage-users.php?err=' . urlencode('User not found'));
  exit;
}

$flash_err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Edit User</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Edit User</h1>
      <a class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50" href="<?= $baseUrl ?>/admin/manage-users.php">Back</a>
    </div>

    <?php if ($flash_err !== ''): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
        <?= h($flash_err) ?>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow p-6">
      <form method="post" action="<?= $baseUrl ?>/admin/edit-user.php?id=<?= (int)$user['user_id'] ?>" class="space-y-4">
        <input type="hidden" name="action" value="save">

        <div>
          <label class="block text-sm font-medium">Full name</label>
          <input name="full_name" value="<?= h($user['full_name']) ?>" class="mt-1 w-full border rounded-lg px-3 py-2" required>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">Email</label>
            <input value="<?= h($user['email']) ?>" class="mt-1 w-full border rounded-lg px-3 py-2 bg-gray-100 text-gray-600" disabled>
          </div>
          <div>
            <label class="block text-sm font-medium">Phone</label>
            <input name="phone" value="<?= h($user['phone'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium">Address</label>
          <input name="address" value="<?= h($user['address'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2" <?= $hasAddress ? '' : 'disabled' ?>>
          <?php if (!$hasAddress): ?>
            <p class="mt-1 text-xs text-gray-500">Your database does not have a users.address column.</p>
          <?php endif; ?>
        </div>

        <div class="flex gap-2">
          <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Save changes</button>
          <a class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50" href="<?= $baseUrl ?>/admin/manage-users.php">Cancel</a>
        </div>
      </form>
    </div>

    <div class="bg-white rounded-xl shadow p-6 mt-8">
      <h2 class="text-lg font-semibold">Reset Password</h2>
      <form method="post" action="<?= $baseUrl ?>/admin/edit-user.php?id=<?= (int)$user['user_id'] ?>" class="mt-3 flex flex-col md:flex-row gap-2">
        <input type="hidden" name="action" value="reset_password">
        <input type="password" name="new_password" minlength="6" placeholder="New password" class="flex-1 border rounded-lg px-3 py-2" required>
        <button class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black">Update</button>
      </form>
      <p class="mt-2 text-xs text-gray-500">Minimum 6 characters.</p>
    </div>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
