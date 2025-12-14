<?php
// /worker/wallet.php
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['worker_id'])) {
  header('Location: ' . $baseUrl . '/auth/worker-login.php');
  exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500); echo 'DB conn missing'; exit;
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$worker_id = (int)$_SESSION['worker_id'];

// total
$st = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE worker_id = ? AND status = 'completed'");
$st->bind_param('i', $worker_id);
$st->execute();
$res = $st->get_result();
$row = $res->fetch_assoc();
$st->close();
$total = (float)($row['total'] ?? 0);

// list
$sql = "
SELECT p.payment_id, p.amount, p.method, p.created_at,
       b.booking_id,
       s.title AS service_title,
       u.full_name AS user_name
FROM payments p
JOIN bookings b ON b.booking_id = p.booking_id
JOIN services s ON s.service_id = b.service_id
JOIN users u ON u.user_id = p.user_id
WHERE p.worker_id = ?
ORDER BY p.created_at DESC
";
$st = $conn->prepare($sql);
$st->bind_param('i', $worker_id);
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$st->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Wallet • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-4">My Wallet</h1>

    <div class="bg-white rounded-xl shadow p-4 mb-6">
      <div class="text-sm text-gray-600">Total earnings</div>
      <div class="text-3xl font-semibold mt-1">$<?= number_format($total, 2) ?></div>
    </div>

    <?php if (empty($rows)): ?>
      <div class="bg-white rounded-xl border p-4 text-gray-600">
        You don't have any payments yet.
      </div>
    <?php else: ?>
      <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Booking</th>
              <th class="px-3 py-2 text-left">User</th>
              <th class="px-3 py-2 text-left">Service</th>
              <th class="px-3 py-2 text-right">Amount</th>
              <th class="px-3 py-2 text-left">Method</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $p): ?>
              <tr class="border-b last:border-0">
                <td class="px-3 py-2"><?= h($p['created_at']) ?></td>
                <td class="px-3 py-2">#<?= (int)$p['booking_id'] ?></td>
                <td class="px-3 py-2"><?= h($p['user_name']) ?></td>
                <td class="px-3 py-2"><?= h($p['service_title']) ?></td>
                <td class="px-3 py-2 text-right">$<?= number_format((float)$p['amount'], 2) ?></td>
                <td class="px-3 py-2"><?= h(ucfirst($p['method'])) ?></td>
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
