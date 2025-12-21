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
// DB connection might be missing (config is tolerant). We'll show a styled error instead of a blank page.
if (!isset($conn) || !($conn instanceof mysqli) || empty($GLOBALS['DB_OK'])) {
  $conn = null;
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function status_badge_class(string $status): string {
  $s = strtolower(trim($status));
  if ($s === 'pending')   return 'bg-yellow-50 text-yellow-800 border border-yellow-200';
  if ($s === 'accepted')  return 'bg-blue-50 text-blue-800 border border-blue-200';
  if ($s === 'completed') return 'bg-green-50 text-green-800 border border-green-200';
  if ($s === 'cancelled') return 'bg-red-50 text-red-800 border border-red-200';
  return 'bg-gray-100 text-gray-700 border border-gray-200';
}

function alert_styles(string $type): array {
  $type = strtolower($type);
  if ($type === 'success') return ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'text' => 'text-green-800'];
  if ($type === 'warning') return ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-900'];
  if ($type === 'error')   return ['bg' => 'bg-red-50',   'border' => 'border-red-200',   'text' => 'text-red-800'];
  return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
}

$user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$booking = null;
$payment = null;

$state = 'ready'; // ready | already_paid | not_completed | not_found | invalid_request | db_error
$alert = null;    // ['type' => 'info|success|warning|error', 'title' => '', 'message' => '']

if ($booking_id <= 0) {
  $state = 'invalid_request';
  $alert = [
    'type' => 'error',
    'title' => 'Invalid request',
    'message' => 'The payment link is missing a valid booking ID. Please return to your bookings and try again.'
  ];
} elseif (!$conn) {
  $state = 'db_error';
  $alert = [
    'type' => 'error',
    'title' => 'Database unavailable',
    'message' => 'We cannot load payment details right now. Please refresh the page or try again later.'
  ];
} else {
  // Load booking + service + worker (scoped to the logged-in user)
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
    $state = 'not_found';
    $alert = [
      'type' => 'error',
      'title' => 'Booking not found',
      'message' => 'We could not find this booking under your account. Please return to My bookings and select a valid booking.'
    ];
  } else {
    // Payment (if already recorded)
    $st = $conn->prepare("SELECT payment_id, amount, method, status, created_at FROM payments WHERE booking_id = ? AND user_id = ? LIMIT 1");
    $st->bind_param('ii', $booking_id, $user_id);
    $st->execute();
    $payment = $st->get_result()->fetch_assoc();
    $st->close();

    if ($payment) {
      $state = 'already_paid';
      $alert = [
        'type' => 'success',
        'title' => 'Payment received',
        'message' => 'This booking has already been paid. You can view the receipt details below.'
      ];
    } elseif (strtolower((string)$booking['status']) !== 'completed') {
      $state = 'not_completed';
      $alert = [
        'type' => 'warning',
        'title' => 'Payment not available yet',
        'message' => 'You can complete payment once the worker marks the booking as completed.'
      ];
    }
  }
}

$amount = $booking ? (float)$booking['price'] : 0.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-start sm:items-center justify-between gap-3 mb-6">
      <div>
        <div class="text-xs text-gray-500">
          <a class="hover:underline" href="<?= h($baseUrl) ?>/user/my-bookings.php">My bookings</a>
          <span class="mx-1">/</span>
          <span>Payment</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Checkout</h1>
        <p class="text-sm text-gray-600 mt-1">Review the booking details and confirm your payment.</p>
      </div>

      <a href="<?= h($baseUrl) ?>/user/my-bookings.php" class="text-sm text-blue-700 hover:underline">Back to my bookings</a>
    </div>

    <?php if ($alert): $a = alert_styles($alert['type']); ?>
      <div class="rounded-xl border <?= h($a['border']) ?> <?= h($a['bg']) ?> px-4 py-3 mb-5">
        <div class="flex items-start gap-3">
          <div class="mt-0.5">
            <?php if ($alert['type'] === 'success'): ?>
              <svg class="w-5 h-5 text-green-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php elseif ($alert['type'] === 'warning'): ?>
              <svg class="w-5 h-5 text-amber-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.7l-8.6 15A2 2 0 0 0 3.4 21h17.2a2 2 0 0 0 1.7-3.3l-8.6-15a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php else: ?>
              <svg class="w-5 h-5 text-red-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86l-8.4 14.5A2 2 0 0 0 3.61 21h16.78a2 2 0 0 0 1.72-3.02l-8.4-14.5a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php endif; ?>
          </div>
          <div>
            <div class="text-sm font-semibold <?= h($a['text']) ?>"><?= h($alert['title']) ?></div>
            <div class="text-sm text-gray-700 mt-0.5"><?= h($alert['message']) ?></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- Summary -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border shadow-sm p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-xs uppercase tracking-wide text-gray-400">Booking</div>
              <div class="text-lg font-semibold text-gray-900 mt-1">
                <?php if ($booking): ?>
                  #<?= (int)$booking['booking_id'] ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </div>
            </div>
            <?php if ($booking): ?>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= h(status_badge_class((string)$booking['status'])) ?>">
                <?= h(ucfirst((string)$booking['status'])) ?>
              </span>
            <?php endif; ?>
          </div>

          <div class="mt-4 space-y-2 text-sm text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <span class="text-gray-500">Service</span>
              <span class="font-medium text-right"><?= $booking ? h($booking['title']) : '—' ?></span>
            </div>
            <div class="flex items-start justify-between gap-3">
              <span class="text-gray-500">Worker</span>
              <span class="font-medium text-right"><?= $booking ? h($booking['worker_name']) : '—' ?></span>
            </div>
            <div class="border-t pt-3 mt-3">
              <div class="flex items-center justify-between">
                <span class="text-gray-600">Total</span>
                <span class="text-base font-semibold">$<?= number_format($amount, 2) ?></span>
              </div>
              <p class="text-xs text-gray-500 mt-2">Payments are recorded for demo purposes only.</p>
            </div>
          </div>

          <?php if ($booking): ?>
            <div class="mt-5 flex flex-wrap gap-2">
              <a href="<?= h($baseUrl) ?>/user/booking-details.php?booking_id=<?= (int)$booking['booking_id'] ?>"
                 class="inline-flex items-center justify-center px-3 py-2 rounded-lg border bg-white hover:bg-gray-50 text-sm">
                View booking
              </a>
              <a href="<?= h($baseUrl) ?>/user/my-bookings.php"
                 class="inline-flex items-center justify-center px-3 py-2 rounded-lg border bg-white hover:bg-gray-50 text-sm">
                My bookings
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Action -->
      <div class="lg:col-span-3">
        <?php if ($state === 'already_paid' && $payment && $booking): ?>
          <div class="bg-white rounded-2xl border shadow-sm p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-gray-900">Receipt</h2>
                <p class="text-sm text-gray-600 mt-1">Payment details for this booking.</p>
              </div>
              <button type="button" onclick="window.print()" class="text-sm px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">
                Print
              </button>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <div class="rounded-xl border bg-gray-50 p-4">
                <div class="text-xs text-gray-500">Payment ID</div>
                <div class="font-semibold mt-1">#<?= (int)$payment['payment_id'] ?></div>
              </div>
              <div class="rounded-xl border bg-gray-50 p-4">
                <div class="text-xs text-gray-500">Status</div>
                <div class="font-semibold mt-1"><?= h(ucfirst((string)$payment['status'])) ?></div>
              </div>
              <div class="rounded-xl border bg-gray-50 p-4">
                <div class="text-xs text-gray-500">Method</div>
                <div class="font-semibold mt-1"><?= h(strtoupper((string)$payment['method'])) ?></div>
              </div>
              <div class="rounded-xl border bg-gray-50 p-4">
                <div class="text-xs text-gray-500">Paid on</div>
                <div class="font-semibold mt-1"><?php
                  $ts = strtotime((string)$payment['created_at']);
                  echo h($ts ? date('Y-m-d H:i', $ts) : (string)$payment['created_at']);
                ?></div>
              </div>
            </div>

            <div class="mt-4 rounded-xl border p-4">
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">Amount</span>
                <span class="font-semibold">$<?= number_format((float)$payment['amount'], 2) ?></span>
              </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
              <a href="<?= h($baseUrl) ?>/user/booking-details.php?booking_id=<?= (int)$booking['booking_id'] ?>"
                 class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                View booking details
              </a>
              <a href="<?= h($baseUrl) ?>/user/my-bookings.php"
                 class="inline-flex items-center justify-center px-4 py-2 rounded-lg border bg-white hover:bg-gray-50 text-sm">
                Return to bookings
              </a>
            </div>
          </div>

        <?php elseif ($state === 'ready' && $booking): ?>
          <form method="post" action="<?= h($baseUrl) ?>/user/process_payment.php" class="bg-white rounded-2xl border shadow-sm p-5">
            <h2 class="text-lg font-semibold text-gray-900">Payment method</h2>
            <p class="text-sm text-gray-600 mt-1">Choose how you would like to pay for this completed booking.</p>

            <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <input class="peer sr-only" type="radio" name="method" id="method_cash" value="cash" required>
                <label for="method_cash" class="block cursor-pointer rounded-xl border p-4 bg-white hover:bg-gray-50 peer-checked:border-blue-600 peer-checked:ring-2 peer-checked:ring-blue-200">
                  <div class="flex items-start gap-3">
                    <div class="mt-0.5">
                      <svg class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7h18v10H3V7Zm0 3h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900">Cash</div>
                      <div class="text-sm text-gray-600 mt-0.5">Pay the worker in person.</div>
                    </div>
                  </div>
                </label>
              </div>

              <div>
                <input class="peer sr-only" type="radio" name="method" id="method_card" value="card" required>
                <label for="method_card" class="block cursor-pointer rounded-xl border p-4 bg-white hover:bg-gray-50 peer-checked:border-blue-600 peer-checked:ring-2 peer-checked:ring-blue-200">
                  <div class="flex items-start gap-3">
                    <div class="mt-0.5">
                      <svg class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7h18v10H3V7Zm0 3h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900">Card</div>
                      <div class="text-sm text-gray-600 mt-0.5">Simulated payment for the demo.</div>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <div class="mt-5 rounded-xl border bg-gray-50 p-4">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-gray-700 mt-0.5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 17h.01M7 7h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <div class="text-sm text-gray-700">
                  <div class="font-semibold text-gray-900">Confirmation</div>
                  <p class="mt-1">By confirming, you acknowledge the work was completed and agree to record this payment for the booking.</p>
                </div>
              </div>
            </div>

            <div class="mt-5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
              <div class="text-sm text-gray-600">
                Total: <span class="font-semibold text-gray-900">$<?= number_format($amount, 2) ?></span>
              </div>
              <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                Confirm payment
              </button>
            </div>
          </form>

        <?php else: ?>
          <div class="bg-white rounded-2xl border shadow-sm p-5">
            <h2 class="text-lg font-semibold text-gray-900">Payment</h2>
            <p class="text-sm text-gray-600 mt-1">There is nothing to pay right now.</p>

            <div class="mt-5 flex flex-wrap gap-2">
              <a href="<?= h($baseUrl) ?>/user/my-bookings.php" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                Go to My bookings
              </a>
              <?php if ($booking): ?>
                <a href="<?= h($baseUrl) ?>/user/booking-details.php?booking_id=<?= (int)$booking['booking_id'] ?>" class="inline-flex items-center justify-center px-4 py-2 rounded-lg border bg-white hover:bg-gray-50 text-sm">
                  View booking details
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>