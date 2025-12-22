<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
  require_once $cfg1;
} elseif (file_exists($cfg2)) {
  require_once $cfg2;
}

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '/Prolink';

if (!function_exists('h')) {
  function h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('initials_from_name')) {
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
}

if (!function_exists('prolink_profile_image_legacy')) {
  /**
   * Legacy file lookup: uploads/profiles/{users|workers}/<id>.<ext>
   * Returns URL string or null.
   */
  function prolink_profile_image_legacy(string $role, int $id, string $baseUrl, string $root): ?string {
    $role = strtolower($role);
    $sub = ($role === 'worker') ? 'workers' : 'users';
    $dirFs = $root . '/uploads/profiles/' . $sub;
    $dirUrl = $baseUrl . '/uploads/profiles/' . $sub;

    foreach (['jpg','jpeg','png','webp'] as $ext) {
      $fs = $dirFs . '/' . $id . '.' . $ext;
      if (is_file($fs)) {
        return $dirUrl . '/' . $id . '.' . $ext . '?v=' . @filemtime($fs);
      }
    }
    return null;
  }
}

if (!function_exists('prolink_profile_image_from_db')) {
  /**
   * DB path lookup: profile_image stored as relative path like "uploads/profiles/users/1.jpg".
   * Returns URL string or null.
   */
  function prolink_profile_image_from_db(?string $relPath, string $baseUrl, string $root): ?string {
    $relPath = trim((string)$relPath);
    if ($relPath === '') return null;

    $relPath = ltrim($relPath, '/');
    $fs = $root . '/' . $relPath;
    if (!is_file($fs)) return null;

    return $baseUrl . '/' . $relPath . '?v=' . @filemtime($fs);
  }
}
?>

<nav class="bg-white border-b">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <!-- Left: Brand + main links -->
    <div class="flex items-center gap-6">
      <a href="<?= h($baseUrl) ?>/" class="font-bold">ProLink</a>
      <a href="<?= h($baseUrl) ?>/" class="text-sm">Home</a>
      <a href="<?= h($baseUrl) ?>/browse.php" class="text-sm">Browse</a>
      <a href="<?= h($baseUrl) ?>/pages/contact.php" class="text-sm">Contact</a>

      <?php if (!empty($_SESSION['admin_id'])): ?>
        <a href="<?= h($baseUrl) ?>/dashboard/admin-dashboard.php" class="text-sm">Dashboard</a>
        <a href="<?= h($baseUrl) ?>/admin/manage-services.php" class="text-sm">Manage Services</a>
        <a href="<?= h($baseUrl) ?>/admin/manage-bookings.php" class="text-sm">Manage Bookings</a>
        <a href="<?= h($baseUrl) ?>/admin/manage-users.php" class="text-sm">Users</a>
        <a href="<?= h($baseUrl) ?>/admin/manage-workers.php" class="text-sm">Workers</a>
        <a href="<?= h($baseUrl) ?>/admin/contact-messages.php" class="text-sm">Contact Messages</a>
        <a href="<?= h($baseUrl) ?>/admin/payments.php" class="text-sm">Payments</a>
      <?php endif; ?>
    </div>

    <!-- Right: role-specific actions -->
    <div class="flex items-center gap-4">

      <?php if (!empty($_SESSION['user_id'])): ?>
        <?php
          $uid = (int)$_SESSION['user_id'];
          $displayName = 'User';
          $profileImageRel = null;

          $hasImgCol = (function_exists('col_exists') && isset($conn) && ($conn instanceof mysqli))
            ? col_exists($conn, 'users', 'profile_image')
            : false;

          if (isset($conn) && ($conn instanceof mysqli)) {
            if ($hasImgCol) {
              $st = @$conn->prepare('SELECT full_name, profile_image FROM users WHERE user_id=? LIMIT 1');
            } else {
              $st = @$conn->prepare('SELECT full_name FROM users WHERE user_id=? LIMIT 1');
            }
            if ($st) {
              @$st->bind_param('i', $uid);
              @$st->execute();
              $res = @$st->get_result();
              $row = $res ? $res->fetch_assoc() : null;
              if (!empty($row['full_name'])) $displayName = (string)$row['full_name'];
              if ($hasImgCol && !empty($row['profile_image'])) $profileImageRel = (string)$row['profile_image'];
              @$st->close();
            }
          }

          $imgUrl = prolink_profile_image_from_db($profileImageRel, $baseUrl, $root);
          if (!$imgUrl) {
            $imgUrl = prolink_profile_image_legacy('user', $uid, $baseUrl, $root);
          }

          $initials = initials_from_name($displayName);
        ?>

        <a class="text-sm" href="<?= h($baseUrl) ?>/user/my-bookings.php">My bookings</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/user/user-messages.php">Messages</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/user/notifications.php">Notifications</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/user/wallet.php">Wallet</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/user/my-reviews.php">My reviews</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/auth/logout.php">Logout</a>

        <!-- Clickable Profile chip -->
        <a href="<?= h($baseUrl) ?>/user/profile.php"
           class="flex items-center gap-2 ml-1 px-2 py-1 rounded-full hover:bg-gray-50"
           title="Edit profile">
          <?php if (!empty($imgUrl)): ?>
            <img src="<?= h($imgUrl) ?>" alt="Profile" class="w-9 h-9 rounded-full object-cover border" />
          <?php else: ?>
            <div class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-semibold">
              <?= h($initials) ?>
            </div>
          <?php endif; ?>
          <div class="hidden md:block leading-tight">
            <div class="text-xs text-gray-500">Welcome back</div>
            <div class="text-sm font-medium text-gray-900 max-w-[180px] truncate"><?= h($displayName) ?></div>
          </div>
        </a>

      <?php elseif (!empty($_SESSION['worker_id'])): ?>
        <?php
          $wid = (int)$_SESSION['worker_id'];
          $displayName = $_SESSION['worker_name'] ?? 'Worker';
          $profileImageRel = null;

          $hasImgCol = (function_exists('col_exists') && isset($conn) && ($conn instanceof mysqli))
            ? col_exists($conn, 'workers', 'profile_image')
            : false;

          if (isset($conn) && ($conn instanceof mysqli)) {
            if ($hasImgCol) {
              $st = @$conn->prepare('SELECT full_name, profile_image FROM workers WHERE worker_id=? LIMIT 1');
            } else {
              $st = @$conn->prepare('SELECT full_name FROM workers WHERE worker_id=? LIMIT 1');
            }
            if ($st) {
              @$st->bind_param('i', $wid);
              @$st->execute();
              $res = @$st->get_result();
              $row = $res ? $res->fetch_assoc() : null;
              if (!empty($row['full_name'])) $displayName = (string)$row['full_name'];
              if ($hasImgCol && !empty($row['profile_image'])) $profileImageRel = (string)$row['profile_image'];
              @$st->close();
            }
          }

          $imgUrl = prolink_profile_image_from_db($profileImageRel, $baseUrl, $root);
          if (!$imgUrl) {
            $imgUrl = prolink_profile_image_legacy('worker', $wid, $baseUrl, $root);
          }

          $initials = initials_from_name((string)$displayName);
        ?>

        <a class="text-sm" href="<?= h($baseUrl) ?>/worker/bookings.php">My bookings</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/worker/reviews.php">My reviews</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/worker/messages.php">Messages</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/worker/notifications.php">Notifications</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/worker/wallet.php">Wallet</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/auth/logout.php">Logout</a>

        <!-- Clickable Profile chip -->
        <a href="<?= h($baseUrl) ?>/worker/profile.php"
           class="flex items-center gap-2 ml-1 px-2 py-1 rounded-full hover:bg-gray-50"
           title="Edit profile">
          <?php if (!empty($imgUrl)): ?>
            <img src="<?= h($imgUrl) ?>" alt="Profile" class="w-9 h-9 rounded-full object-cover border" />
          <?php else: ?>
            <div class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-semibold">
              <?= h($initials) ?>
            </div>
          <?php endif; ?>
          <div class="hidden md:block leading-tight">
            <div class="text-xs text-gray-500">Welcome back</div>
            <div class="text-sm font-medium text-gray-900 max-w-[180px] truncate"><?= h((string)$displayName) ?></div>
          </div>
        </a>

      <?php elseif (!empty($_SESSION['admin_id'])): ?>
        <a class="text-sm" href="<?= h($baseUrl) ?>/admin/view-notifications.php">Notifications</a>
        <a class="text-sm" href="<?= h($baseUrl) ?>/auth/logout.php">Logout</a>

      <?php else: ?>
        <a class="text-sm" href="<?= h($baseUrl) ?>/auth/login.php">Login</a>
        <a class="text-sm bg-blue-600 text-white px-3 py-1 rounded" href="<?= h($baseUrl) ?>/auth/register.php">Sign up</a>
      <?php endif; ?>

    </div>
  </div>
</nav>
