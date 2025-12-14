<?php
/**
 * ProLink - User My Reviews
 * Path: /Prolink/user/my-reviews.php
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

$user_id = (int)$_SESSION['user_id'];

$reviews  = [];
$db_error = null;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $db_error = 'Database connection not available.';
    } else {
        $sql = "
            SELECT r.review_id,
                   r.rating,
                   r.comment,
                   r.created_at,
                   b.booking_id,
                   b.booking_date,
                   s.title      AS service_title,
                   s.category   AS service_category,
                   w.full_name  AS worker_name
            FROM reviews r
            JOIN bookings b ON r.booking_id = b.booking_id
            JOIN services s ON r.service_id = s.service_id
            JOIN workers w  ON r.worker_id = w.worker_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
        ";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('i', $user_id);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $reviews[] = $row;
            }
            $st->close();
        } else {
            $db_error = 'Could not prepare query.';
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
  <title>My Reviews - ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">My Reviews</h1>
    <div class="flex gap-2">
      <a href="<?= h(url('/dashboard/user-dashboard.php')) ?>" class="text-sm text-gray-600 hover:underline">Dashboard</a>
      <a href="<?= h(url('/user/my-bookings.php')) ?>" class="text-sm text-purple-700 hover:underline">My bookings</a>
    </div>
  </div>

  <?php if ($db_error): ?>
    <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 mb-4">
      <?= h($db_error) ?>
    </div>
  <?php endif; ?>

  <?php if (!$db_error && empty($reviews)): ?>
    <div class="bg-white rounded-xl border px-6 py-8 text-center">
      <p class="text-gray-600 mb-2">You haven't written any reviews yet.</p>
      <p class="text-gray-500 text-sm mb-4">After you complete a booking, you can leave a review from your bookings page.</p>
      <a href="<?= h(url('/user/browse-services.php')) ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-purple-600 text-white text-sm hover:bg-purple-700">
        Browse services
      </a>
    </div>
  <?php elseif (!$db_error): ?>
    <div class="space-y-4">
      <?php foreach ($reviews as $r): ?>
        <article class="bg-white rounded-xl border px-4 py-4 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
              <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                Booking #<?= (int)$r['booking_id'] ?>
              </div>
              <h2 class="text-sm sm:text-base font-semibold text-gray-900">
                <?= h($r['service_title']) ?>
              </h2>
              <div class="text-xs text-gray-500">
                <?= h($r['service_category']) ?> • with <?= h($r['worker_name']) ?>
              </div>
            </div>
            <div class="text-right">
              <div class="inline-flex items-center rounded-full bg-yellow-50 border border-yellow-200 px-3 py-1 text-xs font-medium text-yellow-800 mb-1">
                <?php
                  $rating = (int)$r['rating'];
                  echo str_repeat('★', $rating) . str_repeat('☆', max(0, 5 - $rating));
                ?>
                <span class="ml-2 text-[11px]"><?= $rating ?>/5</span>
              </div>
              <div class="text-[11px] text-gray-500">
                <?= h(date('Y-m-d H:i', strtotime($r['created_at']))) ?>
              </div>
            </div>
          </div>

          <?php if (trim((string)$r['comment']) !== ''): ?>
            <p class="mt-3 text-sm text-gray-800 whitespace-pre-line">
              <?= nl2br(h($r['comment'])) ?>
            </p>
          <?php else: ?>
            <p class="mt-3 text-sm text-gray-500 italic">
              (No written comment, rating only)
            </p>
          <?php endif; ?>

          <?php if (!empty($r['booking_date'])): ?>
            <div class="mt-3 text-xs text-gray-500">
              Booked on <?= h(date('Y-m-d', strtotime($r['booking_date']))) ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
