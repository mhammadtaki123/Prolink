<?php
/**
 * ProLink – Navbar
 * Path: /Prolink/partials/navbar.php
 * This version includes user links for: My bookings, Messages, Notifications, My reviews.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
?>
<nav class="bg-white border-b">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <!-- Left: Brand + main links -->
    <div class="flex items-center gap-6">
      <a href="<?= $baseUrl ?>/" class="font-bold">ProLink</a>
      <a href="<?= $baseUrl ?>/" class="text-sm">Home</a>
      <a href="<?= $baseUrl ?>/browse.php" class="text-sm">Browse</a>
      <a href="<?= $baseUrl ?>/pages/contact.php" class="text-sm">Contact</a>

    <?php if (!empty($_SESSION['admin_id'])): ?>
  <a href="<?= $baseUrl ?>/dashboard/admin-dashboard.php" class="text-sm">Dashboard</a>
  <a href="<?= $baseUrl ?>/admin/manage-services.php" class="text-sm">Manage Services</a>
  <a href="<?= $baseUrl ?>/admin/manage-bookings.php" class="text-sm">Manage Bookings</a>
  <a href="<?= $baseUrl ?>/admin/manage-users.php" class="text-sm">Users</a>
  <a href="<?= $baseUrl ?>/admin/manage-workers.php" class="text-sm">Workers</a>
  <a href="<?= $baseUrl ?>/admin/contact-messages.php" class="text-sm">Contact Messages</a>
  <a href="<?= $baseUrl ?>/admin/payments.php" class="text-sm">Payments</a>

<?php endif; ?>

    </div>

    <!-- Right: Auth / role-specific actions -->
    <div class="flex items-center gap-3">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <!-- Logged in as USER -->
        <a class="text-sm" href="<?= $baseUrl ?>/user/my-bookings.php">My bookings</a>
        <a class="text-sm" href="<?= $baseUrl ?>/user/user-messages.php">Messages</a>
        <a class="text-sm" href="<?= $baseUrl ?>/user/notifications.php">Notifications</a>
        <a class="text-sm" href="<?= $baseUrl ?>/user/wallet.php">Wallet</a>
        <a class="text-sm" href="<?= $baseUrl ?>/user/my-reviews.php">My reviews</a>
        <a class="text-sm" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>

      <?php elseif (!empty($_SESSION['worker_id'])): ?>
        <!-- Logged in as Worker -->
        <a class="text-sm" href="<?= $baseUrl ?>/worker/bookings.php">My bookings</a>
        <a class="text-sm" href="<?= $baseUrl ?>/worker/reviews.php">My reviews</a>
        <a class="text-sm" href="<?= $baseUrl ?>/worker/messages.php">Messages</a>
        <a class="text-sm" href="<?= $baseUrl ?>/worker/notifications.php">Notifications</a>
        <a class="text-sm" href="<?= $baseUrl ?>/worker/wallet.php">Wallet</a>
        <a class="text-sm" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>



      <?php elseif (!empty($_SESSION['admin_id'])): ?>
        <!-- Logged in as ADMIN -->
        <a class="text-sm" href="<?= $baseUrl ?>/admin/view-notifications.php">Notifications</a>
        <a class="text-sm" href="<?= $baseUrl ?>/auth/logout.php">Logout</a>

      <?php else: ?>
        <!-- Guest -->
        <a class="text-sm" href="<?= $baseUrl ?>/auth/login.php">Login</a>
        <a class="text-sm bg-blue-600 text-white px-3 py-1 rounded" href="<?= $baseUrl ?>/auth/register.php">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
