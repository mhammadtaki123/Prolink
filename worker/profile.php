<?php

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['worker_id'])) {
  header('Location: ' . $baseUrl . '/auth/worker-login.php');
  exit;
}
$worker_id = (int)$_SESSION['worker_id'];

$hasProfileImageCol = function_exists('col_exists') ? col_exists($conn, 'workers', 'profile_image') : false;

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Avatar helpers
$avatarDir = $root . '/uploads/profiles/workers';
$avatarRelBase = $baseUrl . '/uploads/profiles/workers';
$allowedExts = ['jpg','jpeg','png','webp'];

function avatar_from_db(?string $relPath, string $baseUrl): ?string {
  $root = dirname(__DIR__);
  $relPath = trim((string)$relPath);
  if ($relPath === '') return null;
  $relPath = ltrim($relPath, '/');
  $fs = $root . '/' . $relPath;
  if (!is_file($fs)) return null;
  return rtrim($baseUrl, '/') . '/' . $relPath . '?v=' . @filemtime($fs);
}

function current_avatar_for_worker(string $avatarDir, string $avatarRelBase, int $worker_id, array $allowedExts): ?string {
  foreach ($allowedExts as $ext) {
    $fs = $avatarDir . '/' . $worker_id . '.' . $ext;
    if (is_file($fs)) return $avatarRelBase . '/' . $worker_id . '.' . $ext;
  }
  return null;
}

$avatarUrl = ($hasProfileImageCol && array_key_exists('profile_image', $worker))
  ? avatar_from_db($worker['profile_image'] ?? null, $baseUrl)
  : null;
if (empty($avatarUrl)) {
  $avatarUrl = current_avatar_for_worker($avatarDir, $avatarRelBase, $worker_id, $allowedExts);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $token  = (string)($_POST['csrf_token'] ?? '');

  if (!hash_equals($csrf, $token)) {
    $_SESSION['error'] = 'Security check failed. Please try again.';
    header('Location: ' . $baseUrl . '/worker/profile.php');
    exit;
  }

  // Save profile
  if ($action === 'save_profile') {
    $full_name     = trim((string)($_POST['full_name'] ?? ''));
    $phone         = trim((string)($_POST['phone'] ?? ''));
    $skill_category= trim((string)($_POST['skill_category'] ?? ''));
    $hourly_rate   = trim((string)($_POST['hourly_rate'] ?? ''));
    $bio           = trim((string)($_POST['bio'] ?? ''));

    if ($full_name === '') {
      $_SESSION['error'] = 'Full name is required.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    $rateVal = null;
    if ($hourly_rate !== '') {
      if (!is_numeric($hourly_rate) || (float)$hourly_rate < 0) {
        $_SESSION['error'] = 'Hourly rate must be a non-negative number.';
        header('Location: ' . $baseUrl . '/worker/profile.php');
        exit;
      }
      $rateVal = (float)$hourly_rate;
    }

    $st = $conn->prepare('UPDATE workers SET full_name=?, phone=?, skill_category=?, hourly_rate=?, bio=? WHERE worker_id=?');
    // hourly_rate may be NULL
    if ($rateVal === null) {
      $null = null;
      $st->bind_param('sssssi', $full_name, $phone, $skill_category, $null, $bio, $worker_id);
    } else {
      $rateStr = (string)$rateVal;
      $st->bind_param('sssssi', $full_name, $phone, $skill_category, $rateStr, $bio, $worker_id);
    }

    if (!$st->execute()) {
      $_SESSION['error'] = 'Could not update profile.';
      $st->close();
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }
    $st->close();

    $_SESSION['success'] = 'Profile updated successfully.';
    header('Location: ' . $baseUrl . '/worker/profile.php');
    exit;
  }

  // Change password
  if ($action === 'change_password') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($new) < 6) {
      $_SESSION['error'] = 'New password must be at least 6 characters.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }
    if ($new !== $confirm) {
      $_SESSION['error'] = 'New password and confirmation do not match.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    $st = $conn->prepare('SELECT password FROM workers WHERE worker_id=? LIMIT 1');
    $st->bind_param('i', $worker_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    $stored = (string)($row['password'] ?? '');
    if ($stored === '' || !password_verify($current, $stored)) {
      $_SESSION['error'] = 'Current password is incorrect.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $up = $conn->prepare('UPDATE workers SET password=? WHERE worker_id=?');
    $up->bind_param('si', $hash, $worker_id);
    if (!$up->execute()) {
      $_SESSION['error'] = 'Could not change password.';
      $up->close();
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }
    $up->close();

    $_SESSION['success'] = 'Password changed successfully.';
    header('Location: ' . $baseUrl . '/worker/profile.php');
    exit;
  }

  // Upload photo
  if ($action === 'upload_photo') {
    // Ensure upload directory exists and is writable
    if (!is_dir($avatarDir) && !@mkdir($avatarDir, 0775, true)) {
      $_SESSION['error'] = 'Upload folder is missing. Create: Prolink/uploads/profiles/workers/';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }
    if (!is_writable($avatarDir)) {
      $_SESSION['error'] = 'Upload folder is not writable. Make writable: Prolink/uploads/profiles/workers/';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    if (empty($_FILES['avatar']) || !isset($_FILES['avatar']['error'])) {
      $_SESSION['error'] = 'Please choose an image to upload.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    if ((int)$_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
      $_SESSION['error'] = 'Upload failed. Please try again.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    if ((int)$_FILES['avatar']['size'] > 2 * 1024 * 1024) {
      $_SESSION['error'] = 'Image is too large (max 2MB).';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    $tmp = (string)$_FILES['avatar']['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime'])) {
      $_SESSION['error'] = 'Invalid image file.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    $mime = (string)$info['mime'];
    $ext = match ($mime) {
      'image/jpeg' => 'jpg',
      'image/png'  => 'png',
      'image/webp' => 'webp',
      default      => ''
    };

    if ($ext === '') {
      $_SESSION['error'] = 'Only JPG, PNG, or WEBP images are allowed.';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    // Remove previous avatars for this worker
    foreach ($allowedExts as $e) {
      $old = $avatarDir . '/' . $worker_id . '.' . $e;
      if (is_file($old)) @unlink($old);
    }

    $destFs = $avatarDir . '/' . $worker_id . '.' . $ext;
    $destRel = 'uploads/profiles/workers/' . $worker_id . '.' . $ext;

    if (!@move_uploaded_file($tmp, $destFs)) {
      $_SESSION['error'] = 'Could not save the uploaded file. (Check folder permissions: Prolink/uploads/profiles/workers/)';
      header('Location: ' . $baseUrl . '/worker/profile.php');
      exit;
    }

    // Store path in DB if column exists
    if ($hasProfileImageCol) {
      $st = $conn->prepare('UPDATE workers SET profile_image=? WHERE worker_id=?');
      if ($st) {
        $st->bind_param('si', $destRel, $worker_id);
        if (!$st->execute()) {
          @unlink($destFs);
          $_SESSION['error'] = 'Could not save profile photo in database.';
          $st->close();
          header('Location: ' . $baseUrl . '/worker/profile.php');
          exit;
        }
        $st->close();
      }
    }

    $_SESSION['success'] = 'Profile photo updated.';
    header('Location: ' . $baseUrl . '/worker/profile.php');
    exit;
  }

  // Remove photo
  if ($action === 'remove_photo') {
    foreach ($allowedExts as $e) {
      $fs = $avatarDir . '/' . $worker_id . '.' . $e;
      if (is_file($fs)) @unlink($fs);
    }
    if ($hasProfileImageCol) {
      $st = $conn->prepare('UPDATE workers SET profile_image=NULL WHERE worker_id=?');
      if ($st) {
        $st->bind_param('i', $worker_id);
        @$st->execute();
        $st->close();
      }
    }
    $_SESSION['success'] = 'Profile photo removed.';
    header('Location: ' . $baseUrl . '/worker/profile.php');
    exit;
  }

  header('Location: ' . $baseUrl . '/worker/profile.php');
  exit;
}

// Load worker
$sql = $hasProfileImageCol
  ? 'SELECT worker_id, full_name, email, phone, skill_category, hourly_rate, bio, rating, status, profile_image FROM workers WHERE worker_id=? LIMIT 1'
  : 'SELECT worker_id, full_name, email, phone, skill_category, hourly_rate, bio, rating, status FROM workers WHERE worker_id=? LIMIT 1';

$st = $conn->prepare($sql);
$st->bind_param('i', $worker_id);
$st->execute();
$worker = $st->get_result()->fetch_assoc();
$st->close();

if (!$worker) {
  http_response_code(404);
  echo 'Worker not found';
  exit;
}

// Flash
$flash_ok  = $_SESSION['success'] ?? '';
$flash_err = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$avatarUrl = current_avatar_for_worker($avatarDir, $avatarRelBase, $worker_id, $allowedExts);
$displayName = (string)($worker['full_name'] ?? 'Worker');
$initials = '';
foreach (preg_split('/\s+/', trim(str_replace('_',' ', $displayName))) as $p) {
  if ($p === '') continue;
  $initials .= strtoupper(substr($p, 0, 1));
  if (strlen($initials) >= 2) break;
}
if ($initials === '') $initials = 'W';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Worker Profile • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-start justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Your Worker Profile</h1>
        <p class="text-sm text-gray-600">Manage your account details and profile photo.</p>
      </div>
      <a href="<?= h($baseUrl) ?>/worker/services.php" class="text-sm px-4 py-2 rounded-lg bg-white border hover:bg-gray-50">Back to dashboard</a>
    </div>

    <?php if ($flash_ok): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($flash_err) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Avatar card -->
      <div class="bg-white rounded-2xl shadow p-6">
        <div class="flex items-center gap-4">
          <?php if ($avatarUrl): ?>
            <img src="<?= h($avatarUrl) ?>" alt="Profile photo" class="w-16 h-16 rounded-full object-cover border" />
          <?php else: ?>
            <div class="w-16 h-16 rounded-full bg-blue-600 text-white flex items-center justify-center text-lg font-semibold"><?= h($initials) ?></div>
          <?php endif; ?>
          <div>
            <div class="text-sm text-gray-500">Welcome back</div>
            <div class="font-semibold text-gray-900"><?= h($displayName) ?></div>
            <div class="text-xs text-gray-500">Rating: <?= h((string)($worker['rating'] ?? '0')) ?> • Status: <?= h((string)($worker['status'] ?? 'active')) ?></div>
          </div>
        </div>

        <form class="mt-5" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
          <input type="hidden" name="action" value="upload_photo" />

          <label class="block text-sm font-medium text-gray-700 mb-2">Profile photo</label>
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" />

          <div class="mt-3 flex gap-2">
            <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2 text-sm font-medium" type="submit">Upload</button>
          </div>
        </form>

        <?php if ($avatarUrl): ?>
          <form class="mt-2" method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
            <input type="hidden" name="action" value="remove_photo" />
            <button class="w-full bg-white border hover:bg-gray-50 rounded-lg py-2 text-sm" type="submit">Remove photo</button>
          </form>
        <?php endif; ?>
      </div>

      <!-- Right: Forms -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Profile -->
        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-900">Account details</h2>
          <p class="text-sm text-gray-600">Update your contact info and professional details.</p>

          <form class="mt-4 space-y-4" method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
            <input type="hidden" name="action" value="save_profile" />

            <div>
              <label class="block text-sm font-medium text-gray-700">Full name <span class="text-red-600">*</span></label>
              <input name="full_name" required maxlength="100" value="<?= h($worker['full_name'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2" />
            </div>

            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input value="<?= h($worker['email'] ?? '') ?>" disabled class="mt-1 w-full border rounded-lg px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed" />
                <p class="text-xs text-gray-500 mt-1">Email is read-only.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Phone</label>
                <input name="phone" value="<?= h($worker['phone'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Skill category</label>
              <input name="skill_category" value="<?= h($worker['skill_category'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="e.g., Outdoor, Cleaning, Repairs" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Hourly rate (USD)</label>
              <input name="hourly_rate" type="number" step="0.01" min="0" value="<?= h((string)($worker['hourly_rate'] ?? '')) ?>" class="mt-1 w-full border rounded-lg px-3 py-2" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Bio</label>
              <textarea name="bio" rows="5" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Tell customers about your experience."><?= h($worker['bio'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-2">
              <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm font-medium" type="submit">Save changes</button>
              <a class="bg-white border hover:bg-gray-50 rounded-lg px-4 py-2 text-sm" href="<?= h($baseUrl) ?>/worker/services.php">Cancel</a>
            </div>
          </form>
        </div>

        <!-- Password -->
        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-900">Change password</h2>
          <p class="text-sm text-gray-600">Enter your current password, then choose a new one.</p>

          <form class="mt-4 space-y-4" method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
            <input type="hidden" name="action" value="change_password" />

            <div>
              <label class="block text-sm font-medium text-gray-700">Current password</label>
              <input type="password" name="current_password" required class="mt-1 w-full border rounded-lg px-3 py-2" />
            </div>

            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">New password</label>
                <input type="password" name="new_password" required minlength="6" class="mt-1 w-full border rounded-lg px-3 py-2" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Confirm new password</label>
                <input type="password" name="confirm_password" required minlength="6" class="mt-1 w-full border rounded-lg px-3 py-2" />
              </div>
            </div>

            <div class="flex gap-2">
              <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm font-medium" type="submit">Change password</button>
              <a class="bg-white border hover:bg-gray-50 rounded-lg px-4 py-2 text-sm" href="<?= h($baseUrl) ?>/worker/services.php">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
