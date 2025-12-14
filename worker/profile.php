<?php
// /worker/profile.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

// --- gate: worker only ---
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'worker') {
  redirect_to('/login.php');
}

$worker_id = (int)($_SESSION['worker_id'] ?? 0);
if ($worker_id <= 0) {
  $_SESSION['error'] = 'Session expired. Please log in again.';
  redirect_to('/login.php');
}

// --- helpers (schema-safe) ---
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

$HAS_SKILL   = col_exists($conn, 'workers', 'skill_category');
$HAS_RATE    = col_exists($conn, 'workers', 'hourly_rate');
$HAS_ADDRESS = col_exists($conn, 'workers', 'address') ?: col_exists($conn, 'workers', 'location'); // support either
$ADDR_COL    = col_exists($conn, 'workers', 'address') ? 'address' : (col_exists($conn, 'workers', 'location') ? 'location' : null);
$HAS_BIO     = col_exists($conn, 'workers', 'bio');

// --- handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'save_profile') {
    $full_name     = trim($_POST['full_name'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $skill_category= trim($_POST['skill_category'] ?? '');
    $hourly_rate   = $_POST['hourly_rate'] ?? '';
    $address_in    = trim($_POST['address'] ?? '');
    $bio_in        = trim($_POST['bio'] ?? '');

    if ($full_name === '') {
      $_SESSION['error'] = 'Full name is required.';
      redirect_to('/worker/profile.php');
    }

    // Build dynamic update
    $sets   = ['full_name=?', 'phone=?'];
    $types  = 'ss';
    $params = [$full_name, $phone];

    if ($HAS_SKILL)   { $sets[]='skill_category=?'; $types.='s'; $params[]=$skill_category; }
    if ($HAS_RATE)    {
      if ($hourly_rate === '' || !is_numeric($hourly_rate) || (float)$hourly_rate < 0) {
        $_SESSION['error'] = 'Hourly rate must be a non-negative number.';
        redirect_to('/worker/profile.php');
      }
      $sets[]='hourly_rate=?'; $types.='d'; $params[]=(float)$hourly_rate;
    }
    if ($ADDR_COL)    { $sets[]="$ADDR_COL=?";    $types.='s'; $params[]=$address_in; }
    if ($HAS_BIO)     { $sets[]='bio=?';          $types.='s'; $params[]=$bio_in; }

    $sql = "UPDATE workers SET ".implode(', ', $sets)." WHERE worker_id=?";
    $types .= 'i'; $params[] = $worker_id;

    try {
      $st = $conn->prepare($sql);
      $st->bind_param($types, ...$params);
      $st->execute(); $st->close();

      // keep navbar consistent if it shows name
      $_SESSION['full_name'] = $full_name;

      $_SESSION['success'] = 'Profile updated successfully.';
      redirect_to('/worker/profile.php');
    } catch (Throwable $e) {
      $_SESSION['error'] = 'Could not update profile: ' . $e->getMessage();
      redirect_to('/worker/profile.php');
    }
  }

  if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '' || strlen($new) < 6) {
      $_SESSION['error'] = 'New password must be at least 6 characters.';
      redirect_to('/worker/profile.php');
    }
    if ($new !== $confirm) {
      $_SESSION['error'] = 'New password and confirmation do not match.';
      redirect_to('/worker/profile.php');
    }

    try {
      $st = $conn->prepare("SELECT password FROM workers WHERE worker_id=? LIMIT 1");
      $st->bind_param('i', $worker_id);
      $st->execute();
      $row = $st->get_result()->fetch_assoc();
      $st->close();

      if (!$row || !password_verify($current, $row['password'])) {
        $_SESSION['error'] = 'Current password is incorrect.';
        redirect_to('/worker/profile.php');
      }

      $hash = password_hash($new, PASSWORD_DEFAULT);
      $up   = $conn->prepare("UPDATE workers SET password=? WHERE worker_id=?");
      $up->bind_param('si', $hash, $worker_id);
      $up->execute(); $up->close();

      $_SESSION['success'] = 'Password changed successfully.';
      redirect_to('/worker/profile.php');
    } catch (Throwable $e) {
      $_SESSION['error'] = 'Could not change password: ' . $e->getMessage();
      redirect_to('/worker/profile.php');
    }
  }

  // Unknown action
  redirect_to('/worker/profile.php');
}

// --- load current worker data ---
$cols = ['worker_id','full_name','email','phone'];
if ($HAS_SKILL)   $cols[] = 'skill_category';
if ($HAS_RATE)    $cols[] = 'hourly_rate';
if ($ADDR_COL)    $cols[] = "$ADDR_COL AS address";
if ($HAS_BIO)     $cols[] = 'bio';
$sel = implode(', ', $cols);

$st = $conn->prepare("SELECT $sel FROM workers WHERE worker_id=? LIMIT 1");
$st->bind_param('i', $worker_id);
$st->execute();
$worker = $st->get_result()->fetch_assoc();
$st->close();

if (!$worker) {
  $_SESSION['error'] = 'Worker not found.';
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
  <title>Your Worker Profile — ProLink</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-purple-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-gray-900">Your Worker Profile</h1>
    <a class="px-3 py-1 rounded bg-white border hover:bg-gray-50 text-sm" href="<?= url('/dashboard/worker-dashboard.php') ?>">Back to Dashboard</a>
  </div>

  <?php if ($flash_ok): ?>
    <div class="mb-4 p-3 rounded bg-green-100 text-green-800"><?= htmlspecialchars($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($flash_err) ?></div>
  <?php endif; ?>

  <!-- Profile form -->
  <div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-lg font-semibold text-gray-900">Account details</h2>
    <p class="text-sm text-gray-600">Update your contact info and professional details.</p>

    <form class="mt-4 space-y-4" method="post" action="<?= url('/worker/profile.php') ?>">
      <input type="hidden" name="action" value="save_profile">

      <div>
        <label class="block text-sm font-medium text-gray-700">Full name <span class="text-red-600">*</span></label>
        <input name="full_name" required maxlength="120" value="<?= htmlspecialchars($worker['full_name'] ?? '') ?>"
               class="mt-1 w-full border rounded px-3 py-2" />
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input value="<?= htmlspecialchars($worker['email'] ?? '') ?>" disabled
                 class="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed" />
          <p class="text-xs text-gray-500 mt-1">Email is read-only.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Phone</label>
          <input name="phone" value="<?= htmlspecialchars($worker['phone'] ?? '') ?>"
                 class="mt-1 w-full border rounded px-3 py-2" />
        </div>
      </div>

      <?php if ($HAS_SKILL): ?>
      <div>
        <label class="block text-sm font-medium text-gray-700">Skill category</label>
        <input name="skill_category" value="<?= htmlspecialchars($worker['skill_category'] ?? '') ?>"
               class="mt-1 w-full border rounded px-3 py-2" placeholder="e.g., Cleaning, Gardening, Repairs" />
      </div>
      <?php endif; ?>

      <?php if ($HAS_RATE): ?>
      <div>
        <label class="block text-sm font-medium text-gray-700">Hourly rate (USD)</label>
        <input type="number" step="0.01" min="0" name="hourly_rate"
               value="<?= isset($worker['hourly_rate']) ? htmlspecialchars((string)$worker['hourly_rate']) : '' ?>"
               class="mt-1 w-full border rounded px-3 py-2" />
      </div>
      <?php endif; ?>

      <?php if ($ADDR_COL): ?>
      <div>
        <label class="block text-sm font-medium text-gray-700">Address</label>
        <input name="address" value="<?= htmlspecialchars($worker['address'] ?? '') ?>"
               class="mt-1 w-full border rounded px-3 py-2" />
      </div>
      <?php else: ?>
      <div>
        <label class="block text-sm font-medium text-gray-700">Address (not stored — column missing in DB)</label>
        <input disabled class="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-500 cursor-not-allowed" />
        <p class="text-xs text-yellow-700 mt-1 bg-yellow-50 inline-block px-2 py-1 rounded">Your DB lacks <code>address</code>/<code>location</code> in <code>workers</code>.</p>
      </div>
      <?php endif; ?>

      <?php if ($HAS_BIO): ?>
      <div>
        <label class="block text-sm font-medium text-gray-700">Bio</label>
        <textarea name="bio" rows="5" class="mt-1 w-full border rounded px-3 py-2"
                  placeholder="Tell customers about your experience, tools, and specialties."><?= htmlspecialchars($worker['bio'] ?? '') ?></textarea>
      </div>
      <?php endif; ?>

      <div class="flex gap-2">
        <button class="px-4 py-2 rounded bg-purple-600 text-white hover:bg-purple-700">Save changes</button>
        <a class="px-4 py-2 rounded bg-white border hover:bg-gray-50" href="<?= url('/dashboard/worker-dashboard.php') ?>">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Password form -->
  <div class="bg-white rounded-2xl shadow p-6 mt-8">
    <h2 class="text-lg font-semibold text-gray-900">Change password</h2>
    <p class="text-sm text-gray-600">Enter your current password, then choose a new one.</p>

    <form class="mt-4 space-y-4" method="post" action="<?= url('/worker/profile.php') ?>">
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
        <button class="px-4 py-2 rounded bg-purple-600 text-white hover:bg-purple-700">Change password</button>
        <a class="px-4 py-2 rounded bg-white border hover:bg-gray-50" href="<?= url('/dashboard/worker-dashboard.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
