<?php
/**
 * ProLink - User Booking Details
 * Path: /Prolink/user/booking-details.php
 */
session_start();
require_once __DIR__ . '/../Lib/config.php';

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

// --- Auth: user only ---
if (empty($_SESSION['user_id'])) {
    redirect_to('/auth/login.php');
}

$user_id    = (int)$_SESSION['user_id'];
$booking_id = (int)($_GET['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['error'] = 'Invalid booking selected.';
    redirect_to('/user/my-bookings.php');
}

$booking   = null;
$review    = null;
$db_error  = null;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $db_error = 'Database connection not available.';
    } else {
        // Fetch booking with service + worker details
        $sql = "
            SELECT b.*,
                   s.title        AS service_title,
                   s.description  AS service_description,
                   s.category     AS service_category,
                   s.price        AS service_price,
                   s.location     AS service_location,
                   w.full_name    AS worker_name,
                   w.skill_category AS worker_skill
            FROM bookings b
            JOIN services s ON b.service_id = s.service_id
            JOIN workers w  ON b.worker_id = w.worker_id
            WHERE b.booking_id = ? AND b.user_id = ?
            LIMIT 1
        ";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('ii', $booking_id, $user_id);
            $st->execute();
            $res = $st->get_result();
            $booking = $res->fetch_assoc();
            $st->close();
        }

        if (!$booking) {
            $_SESSION['error'] = 'Booking not found.';
            redirect_to('/user/my-bookings.php');
        }

        // Check if a review already exists for this booking
        if ($st = $conn->prepare("SELECT review_id, rating, comment, created_at FROM reviews WHERE booking_id = ? AND user_id = ? LIMIT 1")) {
            $st->bind_param('ii', $booking_id, $user_id);
            $st->execute();
            $res = $st->get_result();
            $review = $res->fetch_assoc();
            $st->close();
        }
    }
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Booking #<?= (int)$booking_id ?> - ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Booking #<?= (int)$booking_id ?></h1>
    <a href="<?= h(url('/user/my-bookings.php')) ?>" class="text-sm text-blue-700 hover:underline">Back to my bookings</a>
  </div>

  <?php if ($db_error): ?>
    <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 mb-4">
      <?= h($db_error) ?>
    </div>
  <?php endif; ?>

  <?php if ($booking): ?>
    <div class="bg-white rounded-2xl border shadow-sm p-5 mb-6">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
            <?= h($booking['service_category'] ?? '') ?>
          </div>
          <h2 class="text-lg font-semibold text-gray-900">
            <?= h($booking['service_title'] ?? '') ?>
          </h2>
          <div class="text-sm text-gray-600 mt-1">
            With <?= h($booking['worker_name'] ?? '') ?>
            <?php if (!empty($booking['worker_skill'])): ?>
              <span class="text-gray-400">• <?= h($booking['worker_skill']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="text-right">
          <?php
            $status = (string)($booking['status'] ?? 'pending');
            $statusColor = 'bg-gray-100 text-gray-700';
            if ($status === 'pending')   $statusColor = 'bg-yellow-50 text-yellow-800 border border-yellow-200';
            if ($status === 'accepted')  $statusColor = 'bg-blue-50 text-blue-800 border border-blue-200';
            if ($status === 'completed') $statusColor = 'bg-green-50 text-green-800 border border-green-200';
            if ($status === 'cancelled') $statusColor = 'bg-red-50 text-red-800 border border-red-200';
          ?>
          <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $statusColor ?>">
            Status: <?= h(ucfirst($status)) ?>
          </div>
          <?php if (!empty($booking['booking_date'])): ?>
            <div class="mt-2 text-xs text-gray-500">
              Booked on <?= h(date('Y-m-d H:i', strtotime($booking['booking_date']))) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($booking['scheduled_at'])): ?>
            <div class="text-xs text-gray-500">
              Scheduled for <?= h(date('Y-m-d H:i', strtotime($booking['scheduled_at']))) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($booking['service_location'])): ?>
        <div class="mt-4 text-sm text-gray-700">
          <span class="font-medium">Location:</span>
          <?= h($booking['service_location']) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($booking['service_price'])): ?>
        <div class="mt-1 text-sm text-gray-700">
          <span class="font-medium">Price:</span>
          <?= h(number_format((float)$booking['service_price'], 2)) ?> USD
        </div>
      <?php endif; ?>

      <?php if (!empty($booking['notes'])): ?>
        <div class="mt-4">
          <div class="text-sm font-medium text-gray-700 mb-1">Your notes</div>
          <p class="text-sm text-gray-800 whitespace-pre-line"><?= nl2br(h($booking['notes'])) ?></p>
        </div>
      <?php endif; ?>

      <?php if (!empty($booking['service_description'])): ?>
        <div class="mt-4">
          <div class="text-sm font-medium text-gray-700 mb-1">Service description</div>
          <p class="text-sm text-gray-800 whitespace-pre-line"><?= nl2br(h($booking['service_description'])) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm p-5">
      <h2 class="text-lg font-semibold text-gray-900 mb-3">Review</h2>

      <?php if ($review): ?>
        <div class="space-y-2">
          <div class="inline-flex items-center rounded-full bg-yellow-50 border border-yellow-200 px-3 py-1 text-xs font-medium text-yellow-800">
            <?php
              $rating = (int)$review['rating'];
              echo str_repeat('★', $rating) . str_repeat('☆', max(0, 5 - $rating));
            ?>
            <span class="ml-2 text-[11px]"><?= $rating ?>/5</span>
          </div>
          <div class="text-xs text-gray-500">
            Submitted on <?= h(date('Y-m-d H:i', strtotime($review['created_at']))) ?>
          </div>
          <?php if (trim((string)$review['comment']) !== ''): ?>
            <p class="text-sm text-gray-800 whitespace-pre-line">
              <?= nl2br(h($review['comment'])) ?>
            </p>
          <?php else: ?>
            <p class="text-sm text-gray-500 italic">
              (No written comment, rating only)
            </p>
          <?php endif; ?>
        </div>
      <?php elseif (($booking['status'] ?? '') === 'completed'): ?>
        <p class="text-sm text-gray-600 mb-3">
          You haven't reviewed this booking yet.
        </p>
        <a href="<?= h(url('/user/add-review.php?booking_id=' . (int)$booking_id)) ?>"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
          Leave a review
        </a>
      <?php else: ?>
        <p class="text-sm text-gray-500">
          You can leave a review once this booking is marked as completed.
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
