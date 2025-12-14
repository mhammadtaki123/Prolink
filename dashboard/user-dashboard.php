<?php
// dashboard/user-dashboard.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

// Uncomment while diagnosing
// ini_set('display_errors', 1); error_reporting(E_ALL);

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'user') {
  redirect_to('/login.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$tot = $act = $cmp = 0;
$rows = null;
$db_error = null;

try {
  // counts
  $q1 = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE user_id=?");
  $q1->bind_param('i', $user_id); $q1->execute();
  $tot = (int)$q1->get_result()->fetch_assoc()['c']; $q1->close();

  $q2 = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE user_id=? AND status IN ('pending','accepted')");
  $q2->bind_param('i', $user_id); $q2->execute();
  $act = (int)$q2->get_result()->fetch_assoc()['c']; $q2->close();

  $q3 = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE user_id=? AND status='completed'");
  $q3->bind_param('i', $user_id); $q3->execute();
  $cmp = (int)$q3->get_result()->fetch_assoc()['c']; $q3->close();

  // recent bookings (avoid date/time columns that may differ in your DB)
  $bk = $conn->prepare("
    SELECT b.booking_id, b.status,
           s.title,
           w.full_name AS worker_name
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN workers  w ON s.worker_id = w.worker_id
    WHERE b.user_id = ?
    ORDER BY b.booking_id DESC
    LIMIT 10
  ");
  $bk->bind_param('i', $user_id);
  $bk->execute();
  $rows = $bk->get_result();
  $bk->close();

} catch (mysqli_sql_exception $e) {
  // don’t 500; show a small banner with the message so we can fix fast
  $db_error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-purple-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

<div class="max-w-5xl mx-auto mt-6 bg-white rounded-2xl shadow p-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-purple-700">User Dashboard</h1>
    <a href="<?= url('/user/browse-services.php') ?>" class="inline-block bg-purple-600 text-white px-4 py-2 rounded-lg">Browse Services</a>
  </div>

  <?php if ($db_error): ?>
    <div class="mt-4 p-3 rounded bg-red-100 text-red-700 text-sm">
      Database note: <?= htmlspecialchars($db_error) ?>
    </div>
  <?php endif; ?>

  <div class="grid md:grid-cols-3 gap-4 mt-6">
    <div class="rounded-xl p-6 bg-purple-100">
      <p class="text-3xl font-extrabold text-purple-700"><?= $tot ?></p>
      <p class="text-gray-600">Total Bookings</p>
    </div>
    <div class="rounded-xl p-6 bg-green-100">
      <p class="text-3xl font-extrabold text-green-700"><?= $act ?></p>
      <p class="text-gray-600">Active</p>
    </div>
    <div class="rounded-xl p-6 bg-blue-100">
      <p class="text-3xl font-extrabold text-blue-700"><?= $cmp ?></p>
      <p class="text-gray-600">Completed</p>
    </div>
  </div>

  <h2 class="mt-8 mb-2 text-lg font-semibold text-gray-800">Recent Bookings</h2>
  <?php if ($rows && $rows->num_rows > 0): ?>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border border-gray-200">
        <thead class="bg-purple-100 text-purple-700">
          <tr>
            <th class="p-2 border">ID</th>
            <th class="p-2 border">Service</th>
            <th class="p-2 border">Worker</th>
            <th class="p-2 border">Status</th>
            <th class="p-2 border">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($b = $rows->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50">
            <td class="p-2 border text-center"><?= $b['booking_id'] ?></td>
            <td class="p-2 border"><?= htmlspecialchars($b['title']) ?></td>
            <td class="p-2 border"><?= htmlspecialchars($b['worker_name']) ?></td>
            <td class="p-2 border"><?= htmlspecialchars($b['status']) ?></td>
            <td class="p-2 border">
              <?php if ($b['status'] === 'completed'): ?>
                <a href="<?= url('/user/add-review.php?booking_id=' . $b['booking_id']) ?>"
                   class="text-purple-700 hover:underline">Write Review</a>
              <?php else: ?>
                <span class="text-gray-400">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-gray-600">No bookings yet.</p>
  <?php endif; ?>
</div>
</body>
</html>
