<?php
declare(strict_types=1);
session_start();

/**
 * ProLink - User Wallet (Payment History)
 * File: /Prolink/user/wallet.php
 *
 * Shows:
 *  - Total spent (sum of completed payments)
 *  - Pending payments (completed bookings not yet paid) with "Pay now" button
 *  - Payment history (cash/card)
 *
 * Important:
 * - This file uses Lib/config.php if present.
 * - It will NOT redeclare helper functions if they already exist in config.php.
 */

$root = dirname(__DIR__);

// Load config (BASE_URL, $conn, helpers)
foreach ([$root . '/Lib/config.php', $root . '/lib/config.php'] as $cfg) {
  if (is_file($cfg)) { require_once $cfg; break; }
}

// Ensure mysqli $conn exists (fallback)
if (!isset($conn) || !($conn instanceof mysqli)) {
  foreach ([$root . '/includes/db.php', $root . '/config/db.php', $root . '/db.php'] as $db) {
    if (is_file($db)) { require_once $db; break; }
  }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  $conn = @new mysqli('localhost', 'root', '', 'prolink_db');
  if ($conn->connect_errno) {
    http_response_code(500);
    exit('DB connection failed: ' . htmlspecialchars($conn->connect_error));
  }
}
$conn->set_charset('utf8mb4');

// Base URL
if (!isset($baseUrl) || !$baseUrl) {
  $baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '/Prolink';
}

// Helpers (guarded)
if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('table_exists')) {
  function table_exists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
  }
}

// Auth guard (your project uses /user/login.php)
if (empty($_SESSION['user_id'])) {
  header('Location: ' . $baseUrl . '/user/login.php');
  exit;
}
$user_id = (int)$_SESSION['user_id'];

// Payments table must exist
if (!table_exists($conn, 'payments')) {
  http_response_code(500);
  echo "<h2 style='font-family:system-ui'>Payments table is missing</h2>";
  echo "<p style='font-family:system-ui'>Run the SQL that creates the <code>payments</code> table in phpMyAdmin.</p>";
  exit;
}

// Flash messages (optional)
$flash_success = $_SESSION['success'] ?? '';
$flash_error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Total spent
$totalSpent = 0.0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE user_id = ? AND status='completed'");
if ($stmt) {
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $totalSpent = (float)($row['total'] ?? 0);
  $stmt->close();
}

// Pending payments: completed bookings not yet in payments
$pending = [];
$sqlPending = "
SELECT b.booking_id, s.title AS service_title, s.price, w.full_name AS worker_name
FROM bookings b
JOIN services s ON s.service_id = b.service_id
JOIN workers w ON w.worker_id = b.worker_id
LEFT JOIN payments p ON p.booking_id = b.booking_id
WHERE b.user_id = ?
  AND LOWER(b.status) = 'completed'
  AND p.payment_id IS NULL
ORDER BY b.booking_id DESC";
$stmt = $conn->prepare($sqlPending);
if ($stmt) {
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($r = $res->fetch_assoc()) { $pending[] = $r; }
  }
  $stmt->close();
}

// Payment history
$history = [];
$sqlHistory = "
SELECT p.payment_id, p.booking_id, p.amount, p.method, p.status, p.created_at,
       s.title AS service_title,
       w.full_name AS worker_name
FROM payments p
JOIN bookings b ON b.booking_id = p.booking_id
JOIN services s ON s.service_id = b.service_id
JOIN workers w ON w.worker_id = p.worker_id
WHERE p.user_id = ?
ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sqlHistory);
if ($stmt) {
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($r = $res->fetch_assoc()) { $history[] = $r; }
  }
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Wallet • ProLink</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php if (is_file($root . '/partials/navbar.php')) include $root . '/partials/navbar.php'; ?>

  <div class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-4">My Wallet</h1>

    <?php if (!empty($flash_success)): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
        <?= h($flash_success) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <?= h($flash_error) ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow p-5">
        <div class="text-sm text-gray-600">Total spent</div>
        <div class="text-3xl font-semibold mt-1">$<?= number_format($totalSpent, 2) ?></div>
        <div class="text-xs text-gray-500 mt-1">Includes cash & card payments recorded in the system.</div>
      </div>

      <div class="bg-white rounded-xl shadow p-5">
        <div class="text-sm text-gray-600">Pending payments</div>
        <div class="text-3xl font-semibold mt-1"><?= count($pending) ?></div>
        <div class="text-xs text-gray-500 mt-1">Completed bookings waiting for you to confirm payment.</div>
      </div>
    </div>

    <?php if (!empty($pending)): ?>
      <div class="bg-white rounded-xl border overflow-hidden mb-8">
        <div class="px-4 py-3 border-b bg-gray-50">
          <div class="font-semibold">Pending payments</div>
          <div class="text-xs text-gray-500">Click “Pay now” to complete the transaction.</div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white border-b">
              <tr>
                <th class="px-4 py-2 text-left">Booking</th>
                <th class="px-4 py-2 text-left">Service</th>
                <th class="px-4 py-2 text-left">Worker</th>
                <th class="px-4 py-2 text-right">Amount</th>
                <th class="px-4 py-2 text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending as $p): ?>
                <tr class="border-b last:border-0">
                  <td class="px-4 py-2">#<?= (int)$p['booking_id'] ?></td>
                  <td class="px-4 py-2"><?= h($p['service_title']) ?></td>
                  <td class="px-4 py-2"><?= h($p['worker_name']) ?></td>
                  <td class="px-4 py-2 text-right">$<?= number_format((float)$p['price'], 2) ?></td>
                  <td class="px-4 py-2 text-right">
                    <a href="<?= h($baseUrl) ?>/user/pay.php?booking_id=<?= (int)$p['booking_id'] ?>"
                       class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs hover:bg-blue-700">
                      Pay now
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border overflow-hidden">
      <div class="px-4 py-3 border-b bg-gray-50">
        <div class="font-semibold">Payment history</div>
        <div class="text-xs text-gray-500">All transactions recorded for your account.</div>
      </div>

      <?php if (empty($history)): ?>
        <div class="p-4 text-gray-600">No payments recorded yet.</div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white border-b">
              <tr>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Booking</th>
                <th class="px-4 py-2 text-left">Service</th>
                <th class="px-4 py-2 text-left">Worker</th>
                <th class="px-4 py-2 text-right">Amount</th>
                <th class="px-4 py-2 text-left">Method</th>
                <th class="px-4 py-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $t): ?>
                <tr class="border-b last:border-0">
                  <td class="px-4 py-2"><?= h($t['created_at']) ?></td>
                  <td class="px-4 py-2">#<?= (int)$t['booking_id'] ?></td>
                  <td class="px-4 py-2"><?= h($t['service_title']) ?></td>
                  <td class="px-4 py-2"><?= h($t['worker_name']) ?></td>
                  <td class="px-4 py-2 text-right">$<?= number_format((float)$t['amount'], 2) ?></td>
                  <td class="px-4 py-2"><?= h(ucfirst((string)$t['method'])) ?></td>
                  <td class="px-4 py-2"><?= h(ucfirst((string)$t['status'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (is_file($root . '/partials/footer.php')) include $root . '/partials/footer.php'; ?>
</body>
</html>
