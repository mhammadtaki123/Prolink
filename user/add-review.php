<?php
/**
 * ProLink - User Add Review
 * Path: /Prolink/user/add-review.php
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
$booking_id = (int)($_GET['booking_id'] ?? ($_POST['booking_id'] ?? 0));

if ($booking_id <= 0) {
    $_SESSION['error'] = 'Invalid booking selected for review.';
    redirect_to('/user/my-bookings.php');
}

$booking  = null;
$review   = null;
$errors   = [];
$infoMsg  = '';

// Fetch booking & basic info
try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $errors[] = 'Database connection not available.';
    } else {
        $sql = "
            SELECT b.booking_id, b.status, b.user_id, b.worker_id, b.service_id,
                   s.title       AS service_title,
                   w.full_name   AS worker_name
            FROM bookings b
            JOIN services s ON s.service_id = b.service_id
            JOIN workers  w ON w.worker_id = b.worker_id
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
            $_SESSION['error'] = 'Booking not found or does not belong to you.';
            redirect_to('/user/my-bookings.php');
        }

        // Existing review?
        if ($st = $conn->prepare("SELECT review_id, rating, comment, created_at FROM reviews WHERE booking_id = ? AND user_id = ? LIMIT 1")) {
            $st->bind_param('ii', $booking_id, $user_id);
            $st->execute();
            $res = $st->get_result();
            $review = $res->fetch_assoc();
            $st->close();
        }
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

// User is allowed to submit only if booking is completed and there is no existing review
$canSubmit = $booking && strtolower((string)$booking['status']) === 'completed' && !$review;

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!$canSubmit) {
        $errors[] = 'You cannot submit a review for this booking.';
    } else {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Please choose a rating between 1 and 5 stars.';
        }

        if (empty($errors)) {
            $sql = "
                INSERT INTO reviews (booking_id, user_id, worker_id, service_id, rating, comment, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";
            if ($st = $conn->prepare($sql)) {
                $worker_id  = (int)$booking['worker_id'];
                $service_id = (int)$booking['service_id'];
                $st->bind_param('iiiiis', $booking_id, $user_id, $worker_id, $service_id, $rating, $comment);
                if ($st->execute()) {
                    // optional: you could update worker average rating here in future
                    $st->close();
                    $_SESSION['success'] = 'Thank you for your review!';
                    redirect_to('/user/booking-details.php?booking_id=' . $booking_id);
                } else {
                    $errors[] = 'Could not save your review. Please try again.';
                }
            } else {
                $errors[] = 'Could not prepare statement.';
            }
        }
    }
}

// If there is already a review, show info
if ($review && empty($errors)) {
    $infoMsg = 'You have already submitted a review for this booking.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Leave a Review - ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

<div class="max-w-xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Leave a Review</h1>
    <a href="<?= h(url('/user/booking-details.php?booking_id=' . (int)$booking_id)) ?>" class="text-sm text-blue-700 hover:underline">
      Back to booking
    </a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 space-y-1">
      <?php foreach ($errors as $e): ?>
        <div><?= h($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($infoMsg): ?>
    <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">
      <?= h($infoMsg) ?>
    </div>
  <?php endif; ?>

  <?php if ($booking): ?>
    <div class="bg-white rounded-xl border p-4 mb-5">
      <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
        Booking #<?= (int)$booking['booking_id'] ?>
      </div>
      <h2 class="text-base font-semibold text-gray-900">
        <?= h($booking['service_title']) ?>
      </h2>
      <p class="text-sm text-gray-600">
        With <?= h($booking['worker_name']) ?>
      </p>
      <p class="mt-2 text-xs text-gray-500">
        Status: <?= h(ucfirst($booking['status'])) ?>
      </p>
    </div>
  <?php endif; ?>

  <?php if ($canSubmit): ?>
    <form method="post" class="bg-white rounded-xl border p-5 space-y-4 shadow-sm">
      <input type="hidden" name="booking_id" value="<?= (int)$booking_id ?>">

      <div>
        <label for="rating" class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
        <select id="rating" name="rating" required
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Select rating...</option>
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= (isset($rating) && (int)$rating === $i) ? 'selected' : '' ?>>
              <?= $i ?> star<?= $i > 1 ? 's' : '' ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>

      <div>
        <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">
          Comment <span class="text-gray-400 text-xs">(optional)</span>
        </label>
        <textarea id="comment" name="comment" rows="4"
                  class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Share your experience with this service..."><?= isset($comment) ? h($comment) : '' ?></textarea>
      </div>

      <div class="flex justify-end gap-2">
        <a href="<?= h(url('/user/booking-details.php?booking_id=' . (int)$booking_id)) ?>"
           class="px-4 py-2 rounded-lg border text-sm text-gray-700 bg-white hover:bg-gray-50">
          Cancel
        </a>
        <button type="submit"
                class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
          Submit review
        </button>
      </div>
    </form>
  <?php elseif (!$review): ?>
    <div class="bg-white rounded-xl border p-4 text-sm text-gray-600">
      You can only review bookings that have been completed.
    </div>
  <?php endif; ?>

  <?php if ($review): ?>
    <div class="mt-5 bg-white rounded-xl border p-4 text-sm text-gray-700">
      <div class="font-semibold mb-1">Your review</div>
      <div class="inline-flex items-center rounded-full bg-yellow-50 border border-yellow-200 px-3 py-1 text-xs font-medium text-yellow-800 mb-2">
        <?php
          $r = (int)$review['rating'];
          echo str_repeat('★', $r) . str_repeat('☆', max(0, 5 - $r));
        ?>
        <span class="ml-2 text-[11px]"><?= $r ?>/5</span>
      </div>
      <?php if (trim((string)$review['comment']) !== ''): ?>
        <p class="whitespace-pre-line"><?= nl2br(h($review['comment'])) ?></p>
      <?php else: ?>
        <p class="italic text-gray-500">(No written comment)</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
