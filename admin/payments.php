<?php
// /admin/payments.php
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['admin_id'])) {
  header('Location: ' . $baseUrl . '/admin/login.php');
  exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500); echo 'DB conn missing'; exit;
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// total
$res = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE status = 'completed'");
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$total = (float)($row['total'] ?? 0);
if ($res) $res->free();

// list
$sql = "
SELECT p.payment_id, p.amount, p.method, p.status, p.created_at,
       b.booking_id,
       u.full_name AS user_name,
       w.full_name AS worker_name,
       s.title AS service_title
FROM payments p
JOIN bookings b ON b.booking_id = p.booking_id
JOIN users u ON u.user_id = p.user_id
JOIN workers w ON w.worker_id = p.worker_id
JOIN services s ON s.service_id = b.service_id
ORDER BY p.created_at DESC
";
$res = $conn->query($sql);
$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) { $rows[] = $r; }
  $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payments • Admin • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-4">Payments</h1>

    <div class="bg-white rounded-xl shadow p-4 mb-6 flex items-center justify-between">
      <div>
        <div class="text-sm text-gray-600">Total revenue</div>
        <div class="text-3xl font-semibold">$<?= number_format($total, 2) ?></div>
      </div>
      <div class="text-xs text-gray-500">
        Showing all recorded payments.
      </div>
    </div>

    <?php if (empty($rows)): ?>
      <div class="bg-white rounded-xl border p-4 text-gray-600">
        No payments have been recorded yet.
      </div>
    <?php else: ?>
      <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Booking</th>
              <th class="px-3 py-2 text-left">User</th>
              <th class="px-3 py-2 text-left">Worker</th>
              <th class="px-3 py-2 text-left">Service</th>
              <th class="px-3 py-2 text-right">Amount</th>
              <th class="px-3 py-2 text-left">Method</th>
              <th class="px-3 py-2 text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $p): ?>
              <tr class="border-b last:border-0">
                <td class="px-3 py-2"><?= h($p['created_at']) ?></td>
                <td class="px-3 py-2">#<?= (int)$p['booking_id'] ?></td>
                <td class="px-3 py-2"><?= h($p['user_name']) ?></td>
                <td class="px-3 py-2"><?= h($p['worker_name']) ?></td>
                <td class="px-3 py-2"><?= h($p['service_title']) ?></td>
                <td class="px-3 py-2 text-right">$<?= number_format((float)$p['amount'], 2) ?></td>
                <td class="px-3 py-2"><?= h(ucfirst($p['method'])) ?></td>
                <td class="px-3 py-2"><?= h(ucfirst($p['status'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
