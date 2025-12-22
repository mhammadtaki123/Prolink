<?php

if (session_status() === PHP_SESSION_NONE) session_start();

/* Load config if present (provides BASE_URL and usually $conn) */
$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';


$badge = [
  'href' => null,
  'name' => null,
  'img'  => null,
  'initials' => null,
];

function pl_initials(string $name): string {
  $name = trim($name);
  if ($name === '') return 'U';
  $parts = preg_split('/\s+/', $name);
  if ($parts && count($parts) >= 2) return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
  // handle underscores like User_Mhammad
  if (strpos($name, '_') !== false) {
    $p = explode('_', $name);
    if (count($p) >= 2) return strtoupper(substr($p[0], 0, 1) . substr($p[1], 0, 1));
  }
  return strtoupper(substr($name, 0, 1));
}

if (!empty($_SESSION['user_id'])) {
  $badge['href'] = $baseUrl . '/user/profile.php';

  // Prefer session name if your login sets it
  $badge['name'] = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? null;

  // Try DB if available
  if (($badge['name'] === null || $badge['name'] === '') || $badge['img'] === null) {
    if (isset($conn) && $conn instanceof mysqli) {
      $stmt = $conn->prepare("SELECT full_name, profile_image FROM users WHERE user_id = ?");
      if ($stmt) {
        $uid = (int)$_SESSION['user_id'];
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
          $badge['name'] = $badge['name'] ?: ($row['full_name'] ?? null);
          $badge['img']  = $row['profile_image'] ?? null;
        }
        $stmt->close();
      }
    }
  }

  $badge['name'] = $badge['name'] ?: 'User';
  $badge['initials'] = pl_initials($badge['name']);

} elseif (!empty($_SESSION['worker_id'])) {
  $badge['href'] = $baseUrl . '/worker/profile.php';
  $badge['name'] = $_SESSION['worker_name'] ?? $_SESSION['full_name'] ?? null;

  if (($badge['name'] === null || $badge['name'] === '') || $badge['img'] === null) {
    if (isset($conn) && $conn instanceof mysqli) {
      $stmt = $conn->prepare("SELECT full_name, profile_image FROM workers WHERE worker_id = ?");
      if ($stmt) {
        $wid = (int)$_SESSION['worker_id'];
        $stmt->bind_param("i", $wid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
          $badge['name'] = $badge['name'] ?: ($row['full_name'] ?? null);
          $badge['img']  = $row['profile_image'] ?? null;
        }
        $stmt->close();
      }
    }
  }

  $badge['name'] = $badge['name'] ?: 'Worker';
  $badge['initials'] = pl_initials($badge['name']);
}

$menuId = 'plMobileMenu_' . substr(md5(__FILE__), 0, 8);
$btnId  = 'plMenuBtn_' . substr(md5(__FILE__), 0, 8);
?>

<nav class="bg-white border-b">
  <div class="max-w-7xl mx-auto px-4 py-3">
    <div class="flex items-center justify-between gap-3">

      <!-- Left: Brand + main links (desktop) -->
      <div class="flex items-center gap-6 min-w-0">
        <a href="<?= $baseUrl ?>/" class="font-bold whitespace-nowrap">ProLink</a>

        <div class="hidden md:flex items-center gap-6 flex-wrap">
          <a href="<?= $baseUrl ?>/" class="text-sm whitespace-nowrap">Home</a>
          <a href="<?= $baseUrl ?>/browse.php" class="text-sm whitespace-nowrap">Browse</a>
          <a href="<?= $baseUrl ?>/pages/contact.php" class="text-sm whitespace-nowrap">Contact</a>

          <?php if (!empty($_SESSION['admin_id'])): ?>
            <a href="<?= $baseUrl ?>/dashboard/admin-dashboard.php" class="text-sm whitespace-nowrap">Dashboard</a>
            <a href="<?= $baseUrl ?>/admin/manage-services.php" class="text-sm whitespace-nowrap">Manage Services</a>
            <a href="<?= $baseUrl ?>/admin/manage-bookings.php" class="text-sm whitespace-nowrap">Manage Bookings</a>
            <a href="<?= $baseUrl ?>/admin/manage-users.php" class="text-sm whitespace-nowrap">Users</a>
            <a href="<?= $baseUrl ?>/admin/manage-workers.php" class="text-sm whitespace-nowrap">Workers</a>
            <a href="<?= $baseUrl ?>/admin/contact-messages.php" class="text-sm whitespace-nowrap">Contact Messages</a>
            <a href="<?= $baseUrl ?>/admin/payments.php" class="text-sm whitespace-nowrap">Payments</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: role links (desktop) + profile badge for user/worker -->
      <div class="hidden md:flex items-center gap-4 flex-wrap justify-end">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/user/my-bookings.php">My bookings</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/user/user-messages.php">Messages</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/user/notifications.php">Notifications</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/user/wallet.php">Wallet</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/user/my-reviews.php">My reviews</a>

          <!-- Profile badge (clickable) -->
          <?php if (!empty($badge['href'])): ?>
            <a href="<?= htmlspecialchars($badge['href']) ?>"
               class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-gray-100 transition whitespace-nowrap">
              <?php if (!empty($badge['img'])): ?>
                <img src="<?= htmlspecialchars($baseUrl . '/' . ltrim($badge['img'], '/')) ?>"
                     alt="Profile"
                     class="h-9 w-9 rounded-full object-cover border" />
              <?php else: ?>
                <div class="h-9 w-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold">
                  <?= htmlspecialchars($badge['initials'] ?? 'U') ?>
                </div>
              <?php endif; ?>
              <div class="leading-tight">
                <div class="text-[11px] text-gray-500">Welcome back</div>
                <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($badge['name'] ?? 'User') ?></div>
              </div>
            </a>
          <?php endif; ?>

          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>

        <?php elseif (!empty($_SESSION['worker_id'])): ?>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/worker/bookings.php">My bookings</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/worker/reviews.php">My reviews</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/worker/messages.php">Messages</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/worker/notifications.php">Notifications</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/worker/wallet.php">Wallet</a>

          <!-- Profile badge (clickable) -->
          <?php if (!empty($badge['href'])): ?>
            <a href="<?= htmlspecialchars($badge['href']) ?>"
               class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-gray-100 transition whitespace-nowrap">
              <?php if (!empty($badge['img'])): ?>
                <img src="<?= htmlspecialchars($baseUrl . '/' . ltrim($badge['img'], '/')) ?>"
                     alt="Profile"
                     class="h-9 w-9 rounded-full object-cover border" />
              <?php else: ?>
                <div class="h-9 w-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold">
                  <?= htmlspecialchars($badge['initials'] ?? 'W') ?>
                </div>
              <?php endif; ?>
              <div class="leading-tight">
                <div class="text-[11px] text-gray-500">Welcome back</div>
                <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($badge['name'] ?? 'Worker') ?></div>
              </div>
            </a>
          <?php endif; ?>

          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>

        <?php elseif (!empty($_SESSION['admin_id'])): ?>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/admin/view-notifications.php">Notifications</a>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>

        <?php else: ?>
          <a class="text-sm whitespace-nowrap" href="<?= $baseUrl ?>/auth/login.php">Login</a>
          <a class="text-sm bg-blue-600 text-white px-3 py-1 rounded whitespace-nowrap" href="<?= $baseUrl ?>/auth/register.php">Sign up</a>
        <?php endif; ?>
      </div>

      <!-- Mobile: hamburger -->
      <button id="<?= $btnId ?>" class="md:hidden inline-flex items-center justify-center rounded-lg border px-3 py-2 text-sm">
        ☰
      </button>
    </div>

    <!-- Mobile dropdown -->
    <div id="<?= $menuId ?>" class="hidden md:hidden border-t mt-3 pt-3 space-y-2">

      <?php if (!empty($_SESSION['user_id']) || !empty($_SESSION['worker_id'])): ?>
        <!-- Profile badge at top for user/worker -->
        <?php if (!empty($badge['href'])): ?>
          <a href="<?= htmlspecialchars($badge['href']) ?>" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-50">
            <?php if (!empty($badge['img'])): ?>
              <img src="<?= htmlspecialchars($baseUrl . '/' . ltrim($badge['img'], '/')) ?>"
                   alt="Profile"
                   class="h-10 w-10 rounded-full object-cover border" />
            <?php else: ?>
              <div class="h-10 w-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold">
                <?= htmlspecialchars($badge['initials'] ?? 'U') ?>
              </div>
            <?php endif; ?>
            <div class="leading-tight">
              <div class="text-xs text-gray-500">Welcome back</div>
              <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($badge['name'] ?? 'User') ?></div>
            </div>
          </a>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Main links -->
      <a href="<?= $baseUrl ?>/" class="block text-sm">Home</a>
      <a href="<?= $baseUrl ?>/browse.php" class="block text-sm">Browse</a>
      <a href="<?= $baseUrl ?>/pages/contact.php" class="block text-sm">Contact</a>

      <?php if (!empty($_SESSION['admin_id'])): ?>
        <a href="<?= $baseUrl ?>/dashboard/admin-dashboard.php" class="block text-sm">Dashboard</a>
        <a href="<?= $baseUrl ?>/admin/manage-services.php" class="block text-sm">Manage Services</a>
        <a href="<?= $baseUrl ?>/admin/manage-bookings.php" class="block text-sm">Manage Bookings</a>
        <a href="<?= $baseUrl ?>/admin/manage-users.php" class="block text-sm">Users</a>
        <a href="<?= $baseUrl ?>/admin/manage-workers.php" class="block text-sm">Workers</a>
        <a href="<?= $baseUrl ?>/admin/contact-messages.php" class="block text-sm">Contact Messages</a>
        <a href="<?= $baseUrl ?>/admin/payments.php" class="block text-sm">Payments</a>
        <a href="<?= $baseUrl ?>/admin/view-notifications.php" class="block text-sm">Notifications</a>
        <a href="<?= $baseUrl ?>/auth/logout.php" class="block text-sm font-semibold">Logout</a>

      <?php elseif (!empty($_SESSION['user_id'])): ?>
        <div class="pt-2 border-t"></div>
        <a class="block text-sm" href="<?= $baseUrl ?>/user/my-bookings.php">My bookings</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/user/user-messages.php">Messages</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/user/notifications.php">Notifications</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/user/wallet.php">Wallet</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/user/my-reviews.php">My reviews</a>
        <a class="block text-sm font-semibold" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>

      <?php elseif (!empty($_SESSION['worker_id'])): ?>
        <div class="pt-2 border-t"></div>
        <a class="block text-sm" href="<?= $baseUrl ?>/worker/bookings.php">My bookings</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/worker/reviews.php">My reviews</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/worker/messages.php">Messages</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/worker/notifications.php">Notifications</a>
        <a class="block text-sm" href="<?= $baseUrl ?>/worker/wallet.php">Wallet</a>
        <a class="block text-sm font-semibold" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>

      <?php else: ?>
        <div class="pt-2 border-t"></div>
        <a class="block text-sm" href="<?= $baseUrl ?>/auth/login.php">Login</a>
        <a class="block text-sm bg-blue-600 text-white px-3 py-2 rounded inline-block w-fit" href="<?= $baseUrl ?>/auth/register.php">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<script>
(function() {
  const btn = document.getElementById('<?= $btnId ?>');
  const menu = document.getElementById('<?= $menuId ?>');
  if (!btn || !menu) return;

  btn.addEventListener('click', function() {
    menu.classList.toggle('hidden');
  });

  // Close after clicking a link (nice on mobile)
  menu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => menu.classList.add('hidden'));
  });
})();
</script>
