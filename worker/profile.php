<?php

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
  require_once $cfg1;
} elseif (file_exists($cfg2)) {
  require_once $cfg2;
} else {
  http_response_code(500);
  echo 'config.php not found';
  exit;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

function h($s): string {
  return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function go(string $path): void {
  if (function_exists('redirect_to')) {
    redirect_to($path);
  }
  global $baseUrl;
  $path = '/' . ltrim($path, '/');
  header('Location: ' . $baseUrl . $path);
  exit;
}

function u(string $path): string {
  if (function_exists('url')) return url($path);
  global $baseUrl;
  $path = '/' . ltrim($path, '/');
  return $baseUrl . $path;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500);
  echo 'DB connection missing.';
  exit;
}

// Require worker login
if (empty($_SESSION['worker_id']) || empty($_SESSION['logged_in']) || (($_SESSION['role'] ?? '') !== 'worker')) {
  header('Location: ' . $baseUrl . '/auth/worker-login.php');
  exit;
}

$worker_id = (int)$_SESSION['worker_id'];
if ($worker_id <= 0) {
  $_SESSION['error'] = 'Session expired. Please log in again.';
  header('Location: ' . $baseUrl . '/auth/worker-login.php');
  exit;
}

// Schema-safe flags (your schema currently includes all of these columns)
$HAS_SKILL = function_exists('col_exists') ? col_exists($conn, 'workers', 'skill_category') : true;
$HAS_RATE  = function_exists('col_exists') ? col_exists($conn, 'workers', 'hourly_rate') : true;
$HAS_BIO   = function_exists('col_exists') ? col_exists($conn, 'workers', 'bio') : true;
$HAS_PROFILE_IMAGE = function_exists('col_exists') ? col_exists($conn, 'workers', 'profile_image') : true;

$uploadDirRel = 'uploads/profiles/workers';
$uploadDirAbs = $root . '/' . $uploadDirRel;

function fetch_worker(mysqli $conn, int $worker_id, bool $HAS_SKILL, bool $HAS_RATE, bool $HAS_BIO, bool $HAS_PROFILE_IMAGE): ?array {
  $cols = ['worker_id','full_name','email','phone'];
  if ($HAS_SKILL) $cols[] = 'skill_category';
  if ($HAS_RATE)  $cols[] = 'hourly_rate';
  if ($HAS_BIO)   $cols[] = 'bio';
  if ($HAS_PROFILE_IMAGE) $cols[] = 'profile_image';

  $sel = implode(', ', $cols);
  $st = $conn->prepare("SELECT $sel FROM workers WHERE worker_id=? LIMIT 1");
  if (!$st) return null;
  $st->bind_param('i', $worker_id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  return $row ?: null;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Always load current worker for operations that depend on stored fields
  $current = fetch_worker($conn, $worker_id, $HAS_SKILL, $HAS_RATE, $HAS_BIO, $HAS_PROFILE_IMAGE);
  if (!$current) {
    $_SESSION['error'] = 'Worker not found.';
    go('/auth/worker-login.php');
  }

  if ($action === 'save_profile') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $skill     = trim($_POST['skill_category'] ?? '');
    $rate      = trim((string)($_POST['hourly_rate'] ?? ''));
    $bio       = trim($_POST['bio'] ?? '');

    if ($full_name === '') {
      $_SESSION['error'] = 'Full name is required.';
      go('/worker/profile.php');
    }

    $sets   = ['full_name=?', 'phone=?'];
    $types  = 'ss';
    $params = [$full_name, $phone];

    if ($HAS_SKILL) { $sets[] = 'skill_category=?'; $types .= 's'; $params[] = $skill; }

    if ($HAS_RATE) {
      if ($rate !== '' && (!is_numeric($rate) || (float)$rate < 0)) {
        $_SESSION['error'] = 'Hourly rate must be a non-negative number.';
        go('/worker/profile.php');
      }
      // allow empty => NULL
      if ($rate === '') {
        $sets[] = 'hourly_rate=NULL';
      } else {
        $sets[] = 'hourly_rate=?';
        $types .= 'd';
        $params[] = (float)$rate;
      }
    }

    if ($HAS_BIO) { $sets[] = 'bio=?'; $types .= 's'; $params[] = $bio; }

    $sql = 'UPDATE workers SET ' . implode(', ', $sets) . ' WHERE worker_id=?';
    $types .= 'i';
    $params[] = $worker_id;

    $st = $conn->prepare($sql);
    if (!$st) {
      $_SESSION['error'] = 'System error. Please try again.';
      error_log('Worker profile update prepare failed: ' . $conn->error);
      go('/worker/profile.php');
    }

    $st->bind_param($types, ...$params);
    if (!$st->execute()) {
      $_SESSION['error'] = 'Could not update profile.';
      error_log('Worker profile update execute failed: ' . $st->error);
      $st->close();
      go('/worker/profile.php');
    }
    $st->close();

    // keep navbar name consistent if used
    $_SESSION['worker_name'] = $full_name;

    $_SESSION['success'] = 'Profile updated successfully.';
    go('/worker/profile.php');
  }

  if ($action === 'change_password') {
    $current_pw = (string)($_POST['current_password'] ?? '');
    $new_pw     = (string)($_POST['new_password'] ?? '');
    $confirm_pw = (string)($_POST['confirm_password'] ?? '');

    if ($new_pw === '' || strlen($new_pw) < 6) {
      $_SESSION['error'] = 'New password must be at least 6 characters.';
      go('/worker/profile.php');
    }
    if ($new_pw !== $confirm_pw) {
      $_SESSION['error'] = 'New password and confirmation do not match.';
      go('/worker/profile.php');
    }

    $st = $conn->prepare('SELECT password FROM workers WHERE worker_id=? LIMIT 1');
    if (!$st) {
      $_SESSION['error'] = 'System error. Please try again.';
      error_log('Worker password select prepare failed: ' . $conn->error);
      go('/worker/profile.php');
    }

    $st->bind_param('i', $worker_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row || !password_verify($current_pw, (string)$row['password'])) {
      $_SESSION['error'] = 'Current password is incorrect.';
      go('/worker/profile.php');
    }

    $hash = password_hash($new_pw, PASSWORD_DEFAULT);
    $up = $conn->prepare('UPDATE workers SET password=? WHERE worker_id=?');
    if (!$up) {
      $_SESSION['error'] = 'System error. Please try again.';
      error_log('Worker password update prepare failed: ' . $conn->error);
      go('/worker/profile.php');
    }

    $up->bind_param('si', $hash, $worker_id);
    if (!$up->execute()) {
      $_SESSION['error'] = 'Could not change password.';
      error_log('Worker password update execute failed: ' . $up->error);
      $up->close();
      go('/worker/profile.php');
    }
    $up->close();

    $_SESSION['success'] = 'Password changed successfully.';
    go('/worker/profile.php');
  }

  if ($action === 'upload_photo') {
    if (!$HAS_PROFILE_IMAGE) {
      $_SESSION['error'] = 'Profile photo is not supported by your current database schema.';
      go('/worker/profile.php');
    }

    if (!is_dir($uploadDirAbs)) {
      @mkdir($uploadDirAbs, 0775, true);
    }
    if (!is_dir($uploadDirAbs)) {
      $_SESSION['error'] = 'Upload folder is missing: ' . $uploadDirRel;
      go('/worker/profile.php');
    }
    if (!is_writable($uploadDirAbs)) {
      $_SESSION['error'] = 'Upload folder is not writable. Make writable: Prolink/' . $uploadDirRel . '/';
      go('/worker/profile.php');
    }

    if (empty($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
      $_SESSION['error'] = 'No file uploaded.';
      go('/worker/profile.php');
    }

    $f = $_FILES['profile_photo'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $_SESSION['error'] = 'Upload failed. Please try again.';
      go('/worker/profile.php');
    }

    $tmp = $f['tmp_name'] ?? '';
    if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
      $_SESSION['error'] = 'Invalid upload.';
      go('/worker/profile.php');
    }

    // Validate type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);

    $ext = null;
    if ($mime === 'image/jpeg') $ext = 'jpg';
    elseif ($mime === 'image/png') $ext = 'png';
    elseif ($mime === 'image/webp') $ext = 'webp';

    if (!$ext) {
      $_SESSION['error'] = 'Only JPG, PNG, or WEBP images are allowed.';
      go('/worker/profile.php');
    }

    // Limit size: 3MB
    $size = (int)($f['size'] ?? 0);
    if ($size <= 0 || $size > 3 * 1024 * 1024) {
      $_SESSION['error'] = 'Image must be under 3MB.';
      go('/worker/profile.php');
    }

    $filename = $worker_id . '.' . $ext;
    $destAbs  = $uploadDirAbs . '/' . $filename;
    $destRel  = $uploadDirRel . '/' . $filename;

    if (!move_uploaded_file($tmp, $destAbs)) {
      $_SESSION['error'] = 'Could not save the uploaded file.';
      go('/worker/profile.php');
    }

    // If an old image exists and differs, delete it
    $old = (string)($current['profile_image'] ?? '');
    if ($old !== '' && $old !== $destRel) {
      $oldAbs = $root . '/' . ltrim($old, '/');
      if (is_file($oldAbs)) @unlink($oldAbs);
    }

    $st = $conn->prepare('UPDATE workers SET profile_image=? WHERE worker_id=?');
    if (!$st) {
      $_SESSION['error'] = 'System error. Please try again.';
      error_log('Worker photo update prepare failed: ' . $conn->error);
      go('/worker/profile.php');
    }

    $st->bind_param('si', $destRel, $worker_id);
    if (!$st->execute()) {
      $_SESSION['error'] = 'Could not save photo in database.';
      error_log('Worker photo update execute failed: ' . $st->error);
      $st->close();
      go('/worker/profile.php');
    }
    $st->close();

    $_SESSION['success'] = 'Profile photo updated.';
    go('/worker/profile.php');
  }

  if ($action === 'remove_photo') {
    if (!$HAS_PROFILE_IMAGE) {
      $_SESSION['error'] = 'Profile photo is not supported by your current database schema.';
      go('/worker/profile.php');
    }

    $old = (string)($current['profile_image'] ?? '');
    if ($old !== '') {
      $oldAbs = $root . '/' . ltrim($old, '/');
      if (is_file($oldAbs)) @unlink($oldAbs);
    }

    $st = $conn->prepare('UPDATE workers SET profile_image=NULL WHERE worker_id=?');
    if ($st) {
      $st->bind_param('i', $worker_id);
      $st->execute();
      $st->close();
    }

    $_SESSION['success'] = 'Profile photo removed.';
    go('/worker/profile.php');
  }

  // Unknown action
  go('/worker/profile.php');
}

$worker = fetch_worker($conn, $worker_id, $HAS_SKILL, $HAS_RATE, $HAS_BIO, $HAS_PROFILE_IMAGE);
if (!$worker) {
  $_SESSION['error'] = 'Worker not found.';
  header('Location: ' . $baseUrl . '/auth/worker-login.php');
  exit;
}

$flash_err = $_SESSION['error'] ?? null;
$flash_ok  = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

$photoRel = (string)($worker['profile_image'] ?? '');
$photoAbs = $photoRel !== '' ? ($root . '/' . ltrim($photoRel, '/')) : '';
$hasPhoto = ($photoRel !== '' && is_file($photoAbs));
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
<?php require_once $root . '/partials/navbar.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold text-gray-900">Your Worker Profile</h1>
    <a class="px-3 py-1 rounded bg-white border hover:bg-gray-50 text-sm" href="<?= h($baseUrl) ?>/worker/services.php">Back</a>
  </div>

  <?php if ($flash_ok): ?>
    <div class="mb-4 p-3 rounded bg-green-100 text-green-800"><?= h($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= h($flash_err) ?></div>
  <?php endif; ?>

  <!-- Profile photo -->
  <div class="bg-white rounded-2xl shadow p-6 mb-6">
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 rounded-full overflow-hidden border bg-gray-100 flex items-center justify-center">
        <?php if ($hasPhoto): ?>
          <img src="<?= h($baseUrl . '/' . ltrim($photoRel,'/')) ?>" class="w-full h-full object-cover" alt="Profile Photo">
        <?php else: ?>
          <span class="text-gray-500 font-semibold text-lg">
            <?php
              $n = trim((string)($worker['full_name'] ?? 'W'));
              $parts = preg_split('/\s+/', $n);
              $initials = strtoupper(substr($parts[0] ?? 'W', 0, 1) . substr($parts[1] ?? '', 0, 1));
              echo h($initials ?: 'W');
            ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="flex-1">
        <div class="font-semibold text-gray-900">Profile photo</div>
        <div class="text-sm text-gray-600">Upload a JPG, PNG, or WEBP (max 3MB).</div>
      </div>
    </div>

    <div class="mt-4 grid md:grid-cols-2 gap-4">
      <form method="post" enctype="multipart/form-data" action="<?= u('/worker/profile.php') ?>" class="flex items-center gap-3">
        <input type="hidden" name="action" value="upload_photo">
        <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" required>
        <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Upload</button>
      </form>

      <form method="post" action="<?= u('/worker/profile.php') ?>" class="flex items-center justify-end">
        <input type="hidden" name="action" value="remove_photo">
        <button class="px-4 py-2 rounded border bg-white hover:bg-gray-50" type="submit" <?= $hasPhoto ? '' : 'disabled' ?>>Remove photo</button>
      </form>
    </div>
  </div>

  <!-- Account details -->
  <div class="bg-white rounded-2xl shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900">Account details</h2>
    <p class="text-sm text-gray-600">Update your worker profile details.</p>

    <form class="mt-4 space-y-4" method="post" action="<?= u('/worker/profile.php') ?>">
      <input type="hidden" name="action" value="save_profile">

      <div>
        <label class="block text-sm font-medium text-gray-700">Full name <span class="text-red-600">*</span></label>
        <input name="full_name" required maxlength="120" value="<?= h($worker['full_name'] ?? '') ?>"
               class="mt-1 w-full border rounded px-3 py-2" />
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input value="<?= h($worker['email'] ?? '') ?>" disabled
                 class="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed" />
          <p class="text-xs text-gray-500 mt-1">Email is read-only.</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Phone</label>
          <input name="phone" maxlength="25" value="<?= h($worker['phone'] ?? '') ?>"
                 class="mt-1 w-full border rounded px-3 py-2" />
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <?php if ($HAS_SKILL): ?>
          <div>
            <label class="block text-sm font-medium text-gray-700">Skill category</label>
            <input name="skill_category" maxlength="120" value="<?= h($worker['skill_category'] ?? '') ?>"
                   class="mt-1 w-full border rounded px-3 py-2" />
          </div>
        <?php endif; ?>

        <?php if ($HAS_RATE): ?>
          <div>
            <label class="block text-sm font-medium text-gray-700">Hourly rate</label>
            <input name="hourly_rate" inputmode="decimal" value="<?= h($worker['hourly_rate'] ?? '') ?>"
                   class="mt-1 w-full border rounded px-3 py-2" placeholder="e.g. 25" />
          </div>
        <?php endif; ?>
      </div>

      <?php if ($HAS_BIO): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700">Bio</label>
          <textarea name="bio" rows="4" class="mt-1 w-full border rounded px-3 py-2"><?= h($worker['bio'] ?? '') ?></textarea>
        </div>
      <?php endif; ?>

      <div class="flex gap-3">
        <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Save changes</button>
        <a class="px-4 py-2 rounded border bg-white hover:bg-gray-50" href="<?= h($baseUrl) ?>/worker/services.php">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Change password -->
  <div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-lg font-semibold text-gray-900">Change password</h2>
    <p class="text-sm text-gray-600">Use a strong password you do not reuse elsewhere.</p>

    <form class="mt-4 space-y-4" method="post" action="<?= u('/worker/profile.php') ?>">
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

      <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Update password</button>
    </form>
  </div>

</div>
</body>
</html>
