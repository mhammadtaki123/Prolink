<?php
/**
 * ProLink - Service Details + Booking
 * Path: /Prolink/service.php
 * Notes:
 *  - Uses service_images.file_path
 *  - Booking form posts to /user/book-service.php (expects: service_id, worker_id, date, time, notes)
 */
session_start();

// Locate config.php (support Lib/ and lib/)
$root = __DIR__;
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
} else {
    http_response_code(500);
    echo 'config.php not found (Lib/ or lib/)';
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection ($conn) is not available.';
    exit;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($service_id <= 0) {
    http_response_code(400);
    echo 'Invalid service id';
    exit;
}

// Fetch service
$svc = $conn->prepare('
    SELECT service_id, worker_id, title, category, price, location, description
    FROM services WHERE service_id = ? LIMIT 1
');
$svc->bind_param('i', $service_id);
$svc->execute();
$service = $svc->get_result()->fetch_assoc();
$svc->close();

if (!$service) {
    http_response_code(404);
    echo 'Service not found';
    exit;
}

// Fetch images
$imgs = [];
$si = $conn->prepare('SELECT file_path, caption FROM service_images WHERE service_id = ? ORDER BY image_id ASC');
$si->bind_param('i', $service_id);
$si->execute();
$res = $si->get_result();
while ($row = $res->fetch_assoc()) { $imgs[] = $row; }
$si->close();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= h($service['title']) ?> - ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include __DIR__ . '/partials/navbar.php'; ?>
  <div class="max-w-6xl mx-auto px-4 py-8">
    <a href="<?= $baseUrl ?>/user/browse-services.php" class="text-sm text-blue-700">&larr; Back to Browse</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-4">
      <!-- Gallery + Info -->
      <div class="lg:col-span-2 bg-white rounded-xl shadow">
        <?php if (!empty($imgs)): ?>
          <img src="<?= h($imgs[0]['file_path']) ?>" alt="<?= h($imgs[0]['caption'] ?? $service['title']) ?>" class="w-full h-80 object-cover rounded-t-xl">
        <?php else: ?>
          <div class="w-full h-80 bg-gray-200 rounded-t-xl flex items-center justify-center text-gray-500">No Image</div>
        <?php endif; ?>

        <?php if (count($imgs) > 1): ?>
          <div class="p-4 grid grid-cols-3 gap-3">
            <?php foreach (array_slice($imgs, 1) as $im): ?>
              <img src="<?= h($im['file_path']) ?>" alt="<?= h($im['caption'] ?? $service['title']) ?>" class="w-full h-24 object-cover rounded-lg">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="p-6">
          <h1 class="text-2xl font-bold mb-2"><?= h($service['title']) ?></h1>
          <div class="text-sm text-gray-600 mb-1">Category: <?= h($service['category']) ?></div>
          <div class="text-sm text-gray-600 mb-1">Location: <?= h($service['location']) ?></div>
          <div class="text-blue-700 font-semibold mb-4 text-lg">$<?= number_format((float)$service['price'], 2) ?></div>
          <p class="text-gray-800 leading-relaxed"><?= nl2br(h($service['description'])) ?></p>
        </div>
      </div>

      <!-- Booking card -->
      <div class="bg-white rounded-xl shadow p-6 h-fit">
        <h2 class="text-xl font-semibold mb-4">Book this service</h2>
        <?php if (!empty($_GET['msg'])): ?>
          <div class="mb-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3"><?= h($_GET['msg']) ?></div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/user/book-service.php" method="post" class="space-y-3">
          <input type="hidden" name="service_id" value="<?= (int)$service['service_id'] ?>">
          <input type="hidden" name="worker_id" value="<?= (int)$service['worker_id'] ?>">

          <div>
            <label class="block text-sm mb-1">Date</label>
            <input type="date" name="date" required class="w-full border rounded-lg px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Time</label>
            <input type="time" name="time" required class="w-full border rounded-lg px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Notes (optional)</label>
            <textarea name="notes" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Any details for the worker..."></textarea>
          </div>

          <?php if (!empty($_SESSION['user_id'])): ?>
            <button type="submit" class="w-full bg-blue-600 text-white rounded-lg py-2">Request Booking</button>
          <?php else: ?>
            <a href="<?= $baseUrl ?>/auth/login.php" class="block w-full text-center bg-gray-300 text-gray-700 rounded-lg py-2">Log in to book</a>
          <?php endif; ?>
        </form>

        <div class="text-xs text-gray-500 mt-3">
          You won’t be charged now. The worker will accept or decline your request.
        </div>
      </div>
    </div>
  </div>
</body>
</html>
