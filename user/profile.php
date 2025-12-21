<?php
// /user/profile.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

// --- gate: user only ---
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
  redirect_to('/login.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
  $_SESSION['error'] = 'Session expired. Please log in again.';
  redirect_to('/login.php');
}

// --- small helpers ---
function col_exists(mysqli $conn, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('ss', $table, $col);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

// Prefer DB column 'address'; fall back to 'location' if needed.
$ADDRESS_COL = null;
if (col_exists($conn, 'users', 'address'))      $ADDRESS_COL = 'address';
elseif (col_exists($conn, 'users', 'location')) $ADDRESS_COL = 'location';

// --- handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'save_profile') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    if ($full_name === '') {
      $_SESSION['error'] = 'Full name is required.';
      redirect_to('/user/profile.php');
    }

    try {
      // Build dynamic UPDATE depending on which address-like column exists
      if ($ADDRESS_COL) {
        $sql = "UPDATE users SET full_name=?, phone=?, {$ADDRESS_COL}=? WHERE user_id=?";
        $st  = $conn->prepare($sql);
        $st->bind_param('sssi', $full_name, $phone, $address, $user_id);
      } else {
        // No address/location column in DB — update only the fields we have
        $sql = "UPDATE users SET full_name=?, phone=? WHERE user_id=?";
        $st  = $conn->prepare($sql);
        $st->bind_param('ssi', $full_name, $phone, $user_id);
      }

      $st->execute();
      $st->close();

      // keep navbar consistent if it uses session name
      $_SESSION['full_name'] = $full_name;

      $_SESSION['success'] = 'Profile updated successfully.';
      redirect_to('/user/profile.php');
    } catch (Throwable $e) {
      $_SESSION['error'] = 'Could not update profile: ' . $e->getMessage();
      redirect_to('/user/profile.php');
    }

  } elseif ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '' || strlen($new) < 6) {
      $_SESSION['error'] = 'New password must be at least 6 characters.';
      redirect_to('/user/profile.php');
    }
    if ($new !== $confirm) {
      $_SESSION['error'] = 'New password and confirmation do not match.';
      redirect_to('/user/profile.php');
    }

    try {
      // fetch hash
      $st = $conn->prepare("SELECT password FROM users WHERE user_id=? LIMIT 1");
      $st->bind_param('i', $user_id);
      $st->execute();
      $row = $st->get_result()->fetch_assoc();
      $st->close();

      if (!$row || !password_verify($current, $row['password'])) {
        $_SESSION['error'] = 'Current password is incorrect.';
        redirect_to('/user/profile.php');
      }

      $hash = password_hash($new, PASSWORD_DEFAULT);
      $up   = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
      $up->bind_param('si', $hash, $user_id);
      $up->execute();
      $up->close();

      $_SESSION['success'] = 'Password changed successfully.';
      redirect_to('/user/profile.php');
    } catch (Throwable $e) {
      $_SESSION['error'] = 'Could not change password: ' . $e->getMessage();
      redirect_to('/user/profile.php');
    }
  }

  // Unknown action — just bounce back
  redirect_to('/user/profile.php');
}

// --- load current user data for display ---
$selectCols = ['user_id', 'full_name', 'email', 'phone'];
if ($ADDRESS_COL) $selectCols[] = $ADDRESS_COL . ' AS address';
$sel = implode(', ', $selectCols);

$st = $conn->prepare("SELECT $sel FROM users WHERE user_id=? LIMIT 1");
$st->bind_param('i', $user_id);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
  $_SESSION['error'] = 'User not found.';
  redirect_to('/login.php');
}

// flash
$flash_err = $_SESSION['error'] ?? null;
$flash_ok  = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Profile — ProLink</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-2xl font-bold text-gray-900 mb-4">Your Profile</h1>

  <?php if ($flash_ok): ?>
    <div class="mb-4 p-3 rounded bg-green-100 text-green-800"><?= htmlspecialchars($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($flash_err) ?></div>
  <?php endif; ?>

  <!-- Profile form -->
  <div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-lg font-semibold text-gray-900">Account details</h2>
    <p class="text-sm text-gray-600">Update your name, phone, and address.</p>

    <form class="mt-4 space-y-4" method="post" action="<?= url('/user/profile.php') ?>">
      <input type="hidden" name="action" value="save_profile">

      <div>
        <label class="block text-sm font-medium text-gray-700">Full name <span class="text-red-600">*</span></label>
        <input name="full_name" required maxlength="120" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
               class="mt-1 w-full border rounded px-3 py-2" />
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled
                 class="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed" />
          <p class="text-xs text-gray-500 mt-1">Email is currently read-only.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Phone</label>
          <input name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                 class="mt-1 w-full border rounded px-3 py-2" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700"><?= $ADDRESS_COL ? 'Address' : 'Address (not stored — column missing in DB)' ?></label>
        <input name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>"
               <?= $ADDRESS_COL ? '' : 'disabled' ?>
               class="mt-1 w-full border rounded px-3 py-2 <?= $ADDRESS_COL ? '' : 'bg-gray-100 text-gray-500 cursor-not-allowed' ?>" />
        <?php if (!$ADDRESS_COL): ?>
          <p class="text-xs text-yellow-700 mt-1 bg-yellow-50 inline-block px-2 py-1 rounded">Your DB does not have an address/location column in <code>users</code>.</p>
        <?php endif; ?>
      </div>

      <div class="flex gap-2">
        <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Save changes</button>
        <a class="px-4 py-2 rounded bg-white border hover:bg-gray-50" href="<?= url('/') ?>">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Password form -->
  <div class="bg-white rounded-2xl shadow p-6 mt-8">
    <h2 class="text-lg font-semibold text-gray-900">Change password</h2>
    <p class="text-sm text-gray-600">Enter your current password, then choose a new one.</p>

    <form class="mt-4 space-y-4" method="post" action="<?= url('/user/profile.php') ?>">
      <input type="hidden" name="action" value="change_password">

      <div>
        <label class="block text-sm font-medium text-gray-700">Current password</label>
        <input type="password" name="current_password" required class="mt-1 w-full border rounded px-3 py-2" />
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">New password</label>
          <input type="password" name="new_password" required minlength="6" class="mt-1 w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Confirm new password</label>
          <input type="password" name="confirm_password" required minlength="6" class="mt-1 w-full border rounded px-3 py-2" />
        </div>
      </div>

      <div class="flex gap-2">
        <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Change password</button>
        <a class="px-4 py-2 rounded bg-white border hover:bg-gray-50" href="<?= url('/') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
