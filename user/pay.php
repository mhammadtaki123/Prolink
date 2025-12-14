<?php
// /user/pay.php
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['user_id'])) {
  header('Location: ' . $baseUrl . '/auth/login.php');
  exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500);
  echo 'DB conn missing';
  exit;
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($booking_id <= 0) {
  http_response_code(400);
  echo 'Invalid booking.';
  exit;
}

// check if already paid
$st = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id = ? LIMIT 1");
$st->bind_param('i', $booking_id);
$st->execute();
$st->store_result();
if ($st->num_rows > 0) {
  $st->close();
  echo 'This booking is already paid.';
  exit;
}
$st->close();

// load booking + service + worker
$sql = "
SELECT b.booking_id, b.status, b.user_id, b.worker_id,
       s.service_id, s.title, s.price,
       w.full_name AS worker_name
FROM bookings b
JOIN services s ON s.service_id = b.service_id
JOIN workers w ON w.worker_id = b.worker_id
WHERE b.booking_id = ? AND b.user_id = ?
LIMIT 1
";
$st = $conn->prepare($sql);
$st->bind_param('ii', $booking_id, $user_id);
$st->execute();
$res = $st->get_result();
$booking = $res->fetch_assoc();
$st->close();

if (!$booking) {
  http_response_code(404);
  echo 'Booking not found.';
  exit;
}

// ensure it was completed by worker
if (strtolower($booking['status']) !== 'completed') {
  echo 'This booking is not marked as completed yet.';
  exit;
}

$amount = (float)$booking['price'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-md mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold mb-4">Complete Payment</h1>

    <div class="bg-white rounded-xl shadow p-5 space-y-3 mb-4">
      <div><strong>Service:</strong> <?= h($booking['title']) ?></div>
      <div><strong>Worker:</strong> <?= h($booking['worker_name']) ?></div>
      <div><strong>Amount:</strong> $<?= number_format($amount, 2) ?></div>
      <div><strong>Booking ID:</strong> #<?= (int)$booking['booking_id'] ?></div>
    </div>

    <form method="post" action="<?= h($baseUrl) ?>/user/process_payment.php" class="bg-white rounded-xl shadow p-5 space-y-4">
      <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">

      <div>
        <label class="block text-sm font-medium mb-1">Payment method</label>
        <div class="space-y-1 text-sm">
          <label class="flex items-center gap-2">
            <input type="radio" name="method" value="cash" required>
            <span>Cash (pay worker in person)</span>
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" name="method" value="card" required>
            <span>Card (simulated)</span>
          </label>
        </div>
      </div>

      <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white rounded-lg py-2 font-medium">
        Confirm Payment
      </button>

      <p class="text-xs text-gray-500 text-center">
        By confirming, you agree that the work was completed by the worker.
      </p>
    </form>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
