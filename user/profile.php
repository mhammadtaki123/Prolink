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

if (empty($_SESSION['user_id'])) {
  header('Location: ' . $baseUrl . '/auth/login.php');
  exit;
}
$user_id = (int)$_SESSION['user_id'];

$hasProfileImageCol = function_exists('col_exists') ? col_exists($conn, 'users', 'profile_image') : false;

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Avatar helpers
function avatar_paths_user(string $baseUrl, int $user_id): array {
  $root = dirname(__DIR__);
  $dir = $root . '/uploads/profiles/users';
  $rel = 'uploads/profiles/users';
  $exts = ['jpg','jpeg','png','webp'];
  foreach ($exts as $ext) {
    $fs = $dir . '/' . $user_id . '.' . $ext;
    if (is_file($fs)) {
      return ['fs' => $fs, 'url' => $baseUrl . '/' . $rel . '/' . $user_id . '.' . $ext];
    }
  }
  return ['fs' => null, 'url' => null];
}

function avatar_from_db(?string $relPath, string $baseUrl): array {
  $root = dirname(__DIR__);
  $relPath = trim((string)$relPath);
  if ($relPath === '') return ['fs' => null, 'url' => null];
  $relPath = ltrim($relPath, '/');
  $fs = $root . '/' . $relPath;
  if (!is_file($fs)) return ['fs' => null, 'url' => null];
  $url = rtrim($baseUrl, '/') . '/' . $relPath . '?v=' . @filemtime($fs);
  return ['fs' => $fs, 'url' => $url];
}

function password_matches(string $plain, string $stored): bool {
  $stored = (string)$stored;
  if ($stored === '') return false;
  // bcrypt
  if (preg_match('/^\$2[aby]\$\d{2}\$/', $stored)) return password_verify($plain, $stored);
  // sha256
  if (hash('sha256', $plain) === $stored) return true;
  // fallback plain (legacy)
  return hash_equals($stored, $plain);
}

$flash_ok = $_SESSION['success'] ?? '';
$flash_err = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!is_string($token) || !hash_equals($csrf, $token)) {
    $_SESSION['error'] = 'Invalid session token. Please try again.';
    header('Location: ' . $baseUrl . '/user/profile.php');
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  // Ensure upload dirs exist
  $profilesDir = $root . '/uploads/profiles/users';
  if (!is_dir($profilesDir)) {
    @mkdir($profilesDir, 0775, true);
  }

  if ($action === 'save_profile') {
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $address   = trim((string)($_POST['address'] ?? ''));

    if ($full_name === '') {
      $_SESSION['error'] = 'Full name is required.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    $st = $conn->prepare('UPDATE users SET full_name=?, phone=?, address=? WHERE user_id=?');
    if (!$st) {
      $_SESSION['error'] = 'System error (prepare failed).';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }
    $st->bind_param('sssi', $full_name, $phone, $address, $user_id);
    if (!$st->execute()) {
      $_SESSION['error'] = 'Could not update profile.';
      $st->close();
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }
    $st->close();

    $_SESSION['success'] = 'Profile updated successfully.';
    header('Location: ' . $baseUrl . '/user/profile.php');
    exit;
  }

  if ($action === 'change_password') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($new) < 6) {
      $_SESSION['error'] = 'New password must be at least 6 characters.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }
    if ($new !== $confirm) {
      $_SESSION['error'] = 'New password and confirmation do not match.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    $st = $conn->prepare('SELECT password FROM users WHERE user_id=? LIMIT 1');
    $st->bind_param('i', $user_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row || !password_matches($current, (string)$row['password'])) {
      $_SESSION['error'] = 'Current password is incorrect.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $up = $conn->prepare('UPDATE users SET password=? WHERE user_id=?');
    $up->bind_param('si', $hash, $user_id);
    $up->execute();
    $up->close();

    $_SESSION['success'] = 'Password changed successfully.';
    header('Location: ' . $baseUrl . '/user/profile.php');
    exit;
  }

  if ($action === 'upload_photo') {
    if (empty($_FILES['avatar']) || !isset($_FILES['avatar']['error'])) {
      $_SESSION['error'] = 'No file selected.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    $f = $_FILES['avatar'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
      $_SESSION['error'] = 'Upload failed. Please try a different image.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    if (($f['size'] ?? 0) > 2 * 1024 * 1024) {
      $_SESSION['error'] = 'Image is too large. Max 2MB.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    $tmp = (string)$f['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime'])) {
      $_SESSION['error'] = 'Invalid image file.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    $mime = $info['mime'];
    $ext = null;
    if ($mime === 'image/jpeg') $ext = 'jpg';
    elseif ($mime === 'image/png') $ext = 'png';
    elseif ($mime === 'image/webp') $ext = 'webp';

    if (!$ext) {
      $_SESSION['error'] = 'Unsupported image type. Use JPG, PNG, or WEBP.';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    // Ensure upload directory exists and is writable
    if (!is_dir($profilesDir) && !@mkdir($profilesDir, 0775, true)) {
      $_SESSION['error'] = 'Upload folder is missing. Create: Prolink/uploads/profiles/users/';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }
    if (!is_writable($profilesDir)) {
      $_SESSION['error'] = 'Upload folder is not writable. Make writable: Prolink/uploads/profiles/users/';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    // Remove existing avatars for this user
    foreach (glob($profilesDir . '/' . $user_id . '.*') ?: [] as $old) {
      @unlink($old);
    }

    $destFs = $profilesDir . '/' . $user_id . '.' . $ext;
    $destRel = 'uploads/profiles/users/' . $user_id . '.' . $ext;

    if (!@move_uploaded_file($tmp, $destFs)) {
      $_SESSION['error'] = 'Could not save the uploaded file. (Check folder permissions: Prolink/uploads/profiles/users/)';
      header('Location: ' . $baseUrl . '/user/profile.php');
      exit;
    }

    // Store path in DB if column exists
    if ($hasProfileImageCol) {
      $st = $conn->prepare('UPDATE users SET profile_image=? WHERE user_id=?');
      if ($st) {
        $st->bind_param('si', $destRel, $user_id);
        if (!$st->execute()) {
          // keep DB consistent: remove file if DB update fails
          @unlink($destFs);
          $_SESSION['error'] = 'Could not save profile photo in database.';
          $st->close();
          header('Location: ' . $baseUrl . '/user/profile.php');
          exit;
        }
        $st->close();
      }
    }

    $_SESSION['success'] = 'Profile photo updated.';
    header('Location: ' . $baseUrl . '/user/profile.php');
    exit;
  }

  if ($action === 'remove_photo') {
    $profilesDir = $root . '/uploads/profiles/users';
    foreach (glob($profilesDir . '/' . $user_id . '.*') ?: [] as $old) {
      @unlink($old);
    }
    if ($hasProfileImageCol) {
      $st = $conn->prepare('UPDATE users SET profile_image=NULL WHERE user_id=?');
      if ($st) {
        $st->bind_param('i', $user_id);
        @$st->execute();
        $st->close();
      }
    }
    $_SESSION['success'] = 'Profile photo removed.';
    header('Location: ' . $baseUrl . '/user/profile.php');
    exit;
  }

  // Unknown action
  header('Location: ' . $baseUrl . '/user/profile.php');
  exit;
}

// Load user
$sql = $hasProfileImageCol
  ? 'SELECT user_id, full_name, email, phone, address, profile_image FROM users WHERE user_id=? LIMIT 1'
  : 'SELECT user_id, full_name, email, phone, address FROM users WHERE user_id=? LIMIT 1';

$st = $conn->prepare($sql);
$st->bind_param('i', $user_id);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
  http_response_code(404);
  echo 'User not found';
  exit;
}

$avatar = ($hasProfileImageCol && array_key_exists('profile_image', $user))
  ? avatar_from_db($user['profile_image'] ?? null, $baseUrl)
  : ['fs' => null, 'url' => null];
if (empty($avatar['url'])) {
  $avatar = avatar_paths_user($baseUrl, $user_id);
}

function initials_from_name(string $name): string {
  $name = trim(str_replace('_', ' ', $name));
  if ($name === '') return 'U';
  $parts = preg_split('/\s+/', $name);
  $ini = '';
  foreach ($parts as $p) {
    $p = trim($p);
    if ($p === '') continue;
    $ini .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($p, 0, 1)) : strtoupper(substr($p, 0, 1));
    $len = function_exists('mb_strlen') ? mb_strlen($ini) : strlen($ini);
    if ($len >= 2) break;
  }
  return $ini !== '' ? $ini : 'U';
}
$initials = initials_from_name((string)($user['full_name'] ?? 'User'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Profile • ProLink</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">My Profile</h1>
      <a href="<?= h($baseUrl) ?>/user/my-bookings.php" class="text-sm px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">Back</a>
    </div>

    <?php if ($flash_ok): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm"><?= h($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm"><?= h($flash_err) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Photo -->
      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-semibold mb-4">Profile photo</h2>

        <div class="flex items-center gap-4">
          <?php if (!empty($avatar['url'])): ?>
            <img src="<?= h($avatar['url']) ?>" alt="Profile" class="w-20 h-20 rounded-full object-cover border" />
          <?php else: ?>
            <div class="w-20 h-20 rounded-full bg-blue-600 text-white flex items-center justify-center text-xl font-semibold">
              <?= h($initials) ?>
            </div>
          <?php endif; ?>

          <div class="text-sm text-gray-600">
            Upload a square image for best results.<br>
            Max size: 2MB.
          </div>
        </div>

        <form class="mt-4" method="post" enctype="multipart/form-data" action="<?= h($baseUrl) ?>/user/profile.php">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="upload_photo">

          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" required>

          <button class="mt-3 w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2 text-sm font-medium" type="submit">
            Upload photo
          </button>
        </form>

        <?php if (!empty($avatar['url'])): ?>
          <form class="mt-2" method="post" action="<?= h($baseUrl) ?>/user/profile.php" onsubmit="return confirm('Remove your profile photo?');">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="remove_photo">
            <button class="w-full border rounded-lg py-2 text-sm hover:bg-gray-50" type="submit">Remove photo</button>
          </form>
        <?php endif; ?>
      </div>

      <!-- Right: Account + password -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-semibold">Account details</h2>
          <p class="text-sm text-gray-600">Update your name and contact information.</p>

          <form class="mt-4 space-y-4" method="post" action="<?= h($baseUrl) ?>/user/profile.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="save_profile">

            <div>
              <label class="block text-sm font-medium text-gray-700">Full name</label>
              <input name="full_name" required maxlength="100" value="<?= h($user['full_name'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input value="<?= h($user['email'] ?? '') ?>" disabled class="mt-1 w-full border rounded-lg px-3 py-2 bg-gray-100 text-gray-600">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Phone</label>
                <input name="phone" value="<?= h($user['phone'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Optional">
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Address</label>
              <input name="address" value="<?= h($user['address'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Optional">
            </div>

            <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm font-medium" type="submit">Save changes</button>
          </form>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-semibold">Change password</h2>
          <p class="text-sm text-gray-600">Use a strong password (minimum 6 characters).</p>

          <form class="mt-4 space-y-4" method="post" action="<?= h($baseUrl) ?>/user/profile.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="change_password">

            <div>
              <label class="block text-sm font-medium text-gray-700">Current password</label>
              <input type="password" name="current_password" required class="mt-1 w-full border rounded-lg px-3 py-2">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">New password</label>
                <input type="password" name="new_password" required minlength="6" class="mt-1 w-full border rounded-lg px-3 py-2">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Confirm new password</label>
                <input type="password" name="confirm_password" required minlength="6" class="mt-1 w-full border rounded-lg px-3 py-2">
              </div>
            </div>

            <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm font-medium" type="submit">Change password</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
