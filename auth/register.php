<?php
/**
 * ProLink - User Register
 * Path: /Prolink/auth/register.php
 */
session_start();
$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf) {
  $full_name = trim($_POST['full_name'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $password  = $_POST['password'] ?? '';
  $confirm   = $_POST['confirm'] ?? '';
  $phone     = trim($_POST['phone'] ?? '');
  $address   = trim($_POST['address'] ?? '');

  if ($full_name === '' || $email === '' || $password === '' || $confirm === '') {
    $err = 'Please fill all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Please enter a valid email.';
  } elseif ($password !== $confirm) {
    $err = 'Passwords do not match.';
  } else {
    // Check unique email
    $chk = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $chk->bind_param('s', $email);
    $chk->execute(); $has = $chk->get_result()->fetch_assoc(); $chk->close();
    if ($has) {
      $err = 'An account with this email already exists.';
    } else {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $ins = $conn->prepare('INSERT INTO users (full_name, email, password, phone, address) VALUES (?, ?, ?, ?, ?)');
      if ($ins) {
        $ins->bind_param('sssss', $full_name, $email, $hash, $phone, $address);
        if ($ins->execute()) {
          $user_id = $ins->insert_id;
          $ins->close();
          // auto-login
          $_SESSION['user_id'] = (int)$user_id;
          header('Location: ' . $baseUrl . '/user/my-bookings.php');
          exit;
        } else {
          $err = 'Could not create account: ' . $ins->error;
          $ins->close();
        }
      } else {
        $err = 'Prepare failed: ' . $conn->error;
      }
    }
  }
  // Rotate token to avoid resubmits
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  $csrf = $_SESSION['csrf_token'];
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign up • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-4">Create your account</h1>
    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>
    <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
      <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

  <div>
    <label class="block text-sm mb-1">Full name*</label>
    <input
      class="w-full border rounded-lg px-3 py-2"
      name="full_name"
      required
      value="<?= h($_POST['full_name'] ?? '') ?>">
  </div>

  <div>
    <label class="block text-sm mb-1">Email*</label>
    <input
      class="w-full border rounded-lg px-3 py-2"
      type="email"
      name="email"
      required
      value="<?= h($_POST['email'] ?? '') ?>">
  </div>

  <div>
    <label class="block text-sm mb-1">Password*</label>
    <input
      class="w-full border rounded-lg px-3 py-2"
      type="password"
      name="password"
      required>
  </div>

  <div>
    <label class="block text-sm mb-1">Confirm Password*</label>
    <input
      class="w-full border rounded-lg px-3 py-2"
      type="password"
      name="confirm"
      required>
  </div>

  <div>
    <label class="block text-sm mb-1">Phone</label>
    <input
      class="w-full border rounded-lg px-3 py-2"
      name="phone"
      value="<?= h($_POST['phone'] ?? '') ?>">
  </div>

  <div>
    <label class="block text-sm mb-1">Address</label>
    <input
      class="w-full border rounded-lg px-3 py-2"
      name="address"
      value="<?= h($_POST['address'] ?? '') ?>">
  </div>

  <button class="w-full bg-blue-600 text-white rounded-lg py-2" type="submit">
    Sign up
  </button>

  <div class="text-sm text-center text-gray-600 mt-2">
    Already have an account?
    <a class="text-blue-700 underline" href="<?= $baseUrl ?>/auth/login.php">Log in</a>
  </div>
</form>

  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
