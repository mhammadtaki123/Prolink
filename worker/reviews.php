<?php
/**
 * ProLink – Worker Reviews (Feedback from Users)
 * Recommended path: /Prolink/worker/reviews.php
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'DB connection missing';
    exit;
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// --- Auth: worker only ---
if (empty($_SESSION['worker_id'])) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}
$worker_id = (int)$_SESSION['worker_id'];

// --- Summary stats ---
$summary = [
    'total_reviews' => 0,
    'avg_rating'    => 0,
    'star1'         => 0,
    'star2'         => 0,
    'star3'         => 0,
    'star4'         => 0,
    'star5'         => 0,
];

if ($st = $conn->prepare("
    SELECT
      COUNT(*)                       AS total_reviews,
      COALESCE(AVG(rating), 0)       AS avg_rating,
      SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS star5,
      SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS star4,
      SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS star3,
      SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS star2,
      SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS star1
    FROM reviews
    WHERE worker_id = ?
")) {
    $st->bind_param('i', $worker_id);
    $st->execute();
    $res = $st->get_result();
    if ($row = $res->fetch_assoc()) {
        $summary['total_reviews'] = (int)($row['total_reviews'] ?? 0);
        $summary['avg_rating']    = (float)($row['avg_rating'] ?? 0);
        $summary['star1']         = (int)($row['star1'] ?? 0);
        $summary['star2']         = (int)($row['star2'] ?? 0);
        $summary['star3']         = (int)($row['star3'] ?? 0);
        $summary['star4']         = (int)($row['star4'] ?? 0);
        $summary['star5']         = (int)($row['star5'] ?? 0);
    }
    $st->close();
}

// --- Reviews list ---
$reviews = [];
if ($st = $conn->prepare("
    SELECT
      r.review_id,
      r.rating,
      r.comment,
      r.created_at,
      r.booking_id,
      s.service_id,
      s.title AS service_title,
      u.user_id,
      u.full_name AS user_name,
      b.scheduled_at
    FROM reviews r
    JOIN services s ON s.service_id = r.service_id
    JOIN users    u ON u.user_id   = r.user_id
    LEFT JOIN bookings b ON b.booking_id = r.booking_id
    WHERE r.worker_id = ?
    ORDER BY r.created_at DESC, r.review_id DESC
")) {
    $st->bind_param('i', $worker_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $reviews[] = $row;
    }
    $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Reviews - ProLink Worker</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-purple-50 min-h-screen">
<?php include $root . '/partials/navbar.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">My Reviews</h1>
    <a href="<?= h(url('/dashboard/worker-dashboard.php')) ?>" class="text-sm text-purple-700 hover:underline">
      Back to dashboard
    </a>
  </div>

  <div class="grid gap-4 md:grid-cols-3 mb-8">
    <div class="bg-white rounded-2xl shadow p-4 md:col-span-1">
      <h2 class="text-sm font-semibold text-gray-700 mb-2">Overall rating</h2>
      <?php if ($summary['total_reviews'] > 0): ?>
        <?php
          $avg = round($summary['avg_rating'], 1);
          $fullStars = (int)floor($summary['avg_rating']);
        ?>
        <div class="text-3xl font-bold text-yellow-500 mb-1">
          <?= str_repeat('★', $fullStars) ?><?= str_repeat('☆', max(0, 5 - $fullStars)) ?>
        </div>
        <div class="text-sm text-gray-800">
          <?= number_format($avg, 1) ?>/5 from <?= (int)$summary['total_reviews'] ?> review<?= $summary['total_reviews'] === 1 ? '' : 's' ?>
        </div>
      <?php else: ?>
        <p class="text-sm text-gray-600">No reviews yet.</p>
      <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow p-4 md:col-span-2">
      <h2 class="text-sm font-semibold text-gray-700 mb-3">Rating breakdown</h2>
      <?php if ($summary['total_reviews'] > 0): ?>
        <?php
          $total = max(1, $summary['total_reviews']);
          $stars = [
            5 => $summary['star5'],
            4 => $summary['star4'],
            3 => $summary['star3'],
            2 => $summary['star2'],
            1 => $summary['star1'],
          ];
        ?>
        <div class="space-y-2 text-sm">
          <?php foreach ($stars as $star => $count): ?>
            <?php $pct = ($count / $total) * 100; ?>
            <div class="flex items-center gap-2">
              <div class="w-14 text-right"><?= $star ?>★</div>
              <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                <div class="h-2 bg-yellow-400" style="width: <?= $pct ?>%"></div>
              </div>
              <div class="w-16 text-gray-600 text-xs text-right">
                <?= $count ?> (<?= round($pct) ?>%)
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-sm text-gray-600">You will see rating distribution once users start reviewing your work.</p>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Review list</h2>

    <?php if (empty($reviews)): ?>
      <div class="bg-white rounded-2xl border p-6 text-gray-600">
        You haven't received any reviews yet.
      </div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($reviews as $r): ?>
          <article class="bg-white rounded-2xl border p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                  Booking #<?= (int)$r['booking_id'] ?>
                  <?php if (!empty($r['scheduled_at'])): ?>
                    · <?= h(date('Y-m-d', strtotime($r['scheduled_at']))) ?>
                  <?php endif; ?>
                </div>
                <h3 class="text-base font-semibold text-gray-900">
                  <a href="<?= h(url('/service.php?id=' . (int)$r['service_id'])) ?>"
                     class="hover:text-purple-700 hover:underline">
                    <?= h($r['service_title']) ?>
                  </a>
                </h3>
                <div class="text-xs text-gray-600 mt-1">
                  by <?= h($r['user_name']) ?> (User ID <?= (int)$r['user_id'] ?>)
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
                (Rating only, no written comment)
              </p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include $root . '/partials/footer.php'; ?>
</body>
</html>
