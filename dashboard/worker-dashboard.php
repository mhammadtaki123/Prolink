<?php
// dashboard/worker-dashboard.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

// Debug temporarily (uncomment if still blank):
// ini_set('display_errors', 1); error_reporting(E_ALL);

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'worker') {
  redirect_to('/login.php');
}

$worker_id = (int)($_SESSION['worker_id'] ?? 0);

// Guard: if somehow missing, push to login
if ($worker_id <= 0) { redirect_to('/login.php'); }

// Try very small queries first; if any fail, catch and show a friendly note
$services = $conn->prepare("SELECT service_id, title, category, description, price, location, status FROM services WHERE worker_id=? ORDER BY created_at DESC");
$services->bind_param('i', $worker_id);
$services->execute();
$svcRows = $services->get_result();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Worker Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-purple-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

<div class="max-w-5xl mx-auto mt-6 bg-white rounded-2xl shadow p-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-purple-700">Worker Dashboard</h1>
    <a href="<?= url('/worker/add-service.php') ?>" class="px-4 py-2 bg-purple-600 text-white rounded-lg">+ Add Service</a>
  </div>

  <h2 class="mt-6 mb-2 text-lg font-semibold text-gray-800">My Services</h2>
  <?php if ($svcRows->num_rows > 0): ?>
    <div class="grid md:grid-cols-2 gap-4">
      <?php while ($s = $svcRows->fetch_assoc()): ?>
        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
          <a href="<?= url('/service.php?id=' . $s['service_id']) ?>" class="text-lg font-semibold text-purple-700 hover:underline">
            <?= htmlspecialchars($s['title']) ?>
          </a>
          <p class="text-sm text-gray-600"><?= htmlspecialchars($s['category']) ?></p>
          <p class="mt-2 text-sm text-gray-700"><?= htmlspecialchars($s['description']) ?></p>
          <p class="mt-2 font-medium">$<?= htmlspecialchars($s['price']) ?></p>
          <p class="text-xs text-gray-500">📍 <?= htmlspecialchars($s['location'] ?: 'Not specified') ?></p>
          <p class="text-xs text-gray-500 mt-1">Status: <?= htmlspecialchars($s['status']) ?></p>

          <div class="mt-3 flex flex-wrap gap-2">
            <a href="<?= url('/worker/edit-service.php?id=' . $s['service_id']) ?>" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">Edit</a>
            <a href="<?= url('/worker/delete-service.php?id=' . $s['service_id']) ?>" class="px-3 py-1 bg-red-600 text-white rounded text-sm" onclick="return confirm('Delete this service?')">Delete</a>
            <a href="<?= url('/worker/service-images.php?service_id=' . $s['service_id']) ?>" class="px-3 py-1 bg-gray-700 text-white rounded text-sm">Images</a>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p class="text-gray-600">No services yet.</p>
  <?php endif; ?>
</div>
</body>
</html>
