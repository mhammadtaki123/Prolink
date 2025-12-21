<?php
/**
 * ProLink - User My Bookings (safe datetime)
 * Path: /Prolink/user/my-bookings.php
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Fetch bookings
$sql = "
SELECT
  b.booking_id, b.status, b.scheduled_at, b.notes,
  s.service_id, s.title, s.location, s.price,
  (SELECT si.file_path FROM service_images si WHERE si.service_id = s.service_id ORDER BY si.image_id ASC LIMIT 1) AS image_path
FROM bookings b
JOIN services s ON s.service_id = b.service_id
WHERE b.user_id = ?
ORDER BY b.booking_id DESC
";
$st = $conn->prepare($sql);
$st->bind_param('i', $user_id);
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$st->close();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function format_when($dt){
  if (!$dt || $dt === '0000-00-00 00:00:00') return 'Not scheduled';
  $ts = strtotime($dt);
  if ($ts === false) return 'Not scheduled';
  return date('Y-m-d H:i', $ts);
}
function actions_for($status){
  $status = strtolower((string)$status);
  if ($status === 'pending' || $status === 'accepted') return ['cancelled' => 'Cancel'];
  return [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Bookings</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">My Bookings</h1>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">You don't have any bookings yet.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($rows as $b): $a = actions_for($b['status']); ?>
          <div class="bg-white rounded-xl border p-4">
            <div class="flex items-center justify-between mb-2">
              <h3 class="font-semibold"><?= h($b['title']) ?></h3>
              <span class="text-xs px-2 py-1 rounded-full border"><?= h(ucfirst($b['status'])) ?></span>
            </div>
            <?php if (!empty($b['image_path'])): ?>
              <img class="w-full h-40 object-cover rounded-lg mb-3" src="<?= h($b['image_path']) ?>" alt="">
            <?php endif; ?>
            <div class="text-sm text-gray-700 space-y-1">
              <div><strong>When:</strong> <?= h(format_when($b['scheduled_at'])) ?></div>
              <div><strong>Location:</strong> <?= h($b['location'] ?? '—') ?></div>
              <div><strong>Price:</strong> $<?= number_format((float)$b['price'], 2) ?></div>
              <?php if (!empty($b['notes'])): ?><div><strong>Notes:</strong> <?= nl2br(h($b['notes'])) ?></div><?php endif; ?>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3">
              <a href="<?= h(url('/user/booking-details.php?booking_id=' . (int)$b['booking_id'])) ?>"
                 class="inline-flex items-center text-xs text-blue-700 hover:text-blue-900 hover:underline">
                View details
                <?php if (strtolower($b['status']) === 'completed'): ?>
               <a
               href="<?= h(url('/user/pay.php?booking_id=' . (int)$b['booking_id'])) ?>"
              class="inline-flex items-center px-3 py-2 rounded-lg bg-blue-600 text-white text-xs hover:bg-blue-700">
              Proceed to payment
             </a>
             <?php endif; ?>

              </a>

              <?php if (strtolower((string)$b['status']) === 'completed' && (int)($b['has_review'] ?? 0) === 0): ?>
                <a href="<?= h(url('/user/add-review.php?booking_id=' . (int)$b['booking_id'])) ?>"
                   class="inline-flex items-center text-xs text-amber-700 hover:text-amber-900 hover:underline">
                  Leave review
                </a>
              <?php endif; ?>
            </div>

            <?php if (!empty($a)): ?>
              <div class="mt-3 flex gap-2">
                <?php foreach ($a as $act => $label): ?>
                  <form method="post" action="<?= $baseUrl ?>/user/update-booking.php" onsubmit="return confirm('Confirm: <?= h($label) ?>?')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                    <input type="hidden" name="action" value="<?= h($act) ?>">
                    <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit"><?= h($label) ?></button>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
