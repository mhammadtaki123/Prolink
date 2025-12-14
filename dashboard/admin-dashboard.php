<?php
/**
 * ProLink - Admin Dashboard
 * Path: /Prolink/dashboard/admin-dashboard.php
 *
 * Shows quick stats and recent activity for the admin:
 * - counts of users, workers, services, bookings, contact messages
 * - latest bookings
 * - latest workers
 * - latest services
 * - latest contact messages
 */

session_start();

$root = dirname(__DIR__);

// Load config (support both Lib/ and lib/)
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';

if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
} else {
    http_response_code(500);
    echo 'config.php not found';
    exit;
}

// Ensure DB connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'DB connection missing';
    exit;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// Admin auth guard
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . $baseUrl . '/admin/login.php');
    exit;
}

if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Helper to run a simple COUNT(*) query safely.
 */
function fetch_count(mysqli $conn, string $sql): int {
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) {
        return (int)($row['c'] ?? 0);
    }
    return 0;
}

// Top-level stats
$totalUsers          = fetch_count($conn, "SELECT COUNT(*) AS c FROM users");
$totalWorkers        = fetch_count($conn, "SELECT COUNT(*) AS c FROM workers");
$totalServices       = fetch_count($conn, "SELECT COUNT(*) AS c FROM services");
$activeServices      = fetch_count($conn, "SELECT COUNT(*) AS c FROM services WHERE status = 'active'");
$inactiveServices    = fetch_count($conn, "SELECT COUNT(*) AS c FROM services WHERE status = 'inactive'");
$totalBookings       = fetch_count($conn, "SELECT COUNT(*) AS c FROM bookings");
$pendingBookings     = fetch_count($conn, "SELECT COUNT(*) AS c FROM bookings WHERE status = 'pending'");
$completedBookings   = fetch_count($conn, "SELECT COUNT(*) AS c FROM bookings WHERE status = 'completed'");
$cancelledBookings   = fetch_count($conn, "SELECT COUNT(*) AS c FROM bookings WHERE status = 'cancelled'");
$newContactMessages  = fetch_count($conn, "SELECT COUNT(*) AS c FROM contact_messages WHERE status = 'new'");

// Latest bookings
$latestBookings = [];
$sqlBookings = "
    SELECT 
        b.booking_id,
        b.status,
        b.booking_date,
        u.full_name AS user_name,
        w.full_name AS worker_name,
        s.title      AS service_title
    FROM bookings b
    JOIN users   u ON b.user_id   = u.user_id
    JOIN workers w ON b.worker_id = w.worker_id
    JOIN services s ON b.service_id = s.service_id
    ORDER BY b.booking_date DESC
    LIMIT 5
";
if ($res = $conn->query($sqlBookings)) {
    while ($row = $res->fetch_assoc()) {
        $latestBookings[] = $row;
    }
    $res->free();
}

// Latest workers
$latestWorkers = [];
$sqlWorkers = "
    SELECT worker_id, full_name, email, created_at, status
    FROM workers
    ORDER BY created_at DESC
    LIMIT 5
";
if ($res = $conn->query($sqlWorkers)) {
    while ($row = $res->fetch_assoc()) {
        $latestWorkers[] = $row;
    }
    $res->free();
}

// Latest services
$latestServices = [];
$sqlServices = "
    SELECT 
        s.service_id,
        s.title,
        s.category,
        s.status,
        s.created_at,
        w.full_name AS worker_name
    FROM services s
    JOIN workers w ON s.worker_id = w.worker_id
    ORDER BY s.created_at DESC
    LIMIT 5
";
if ($res = $conn->query($sqlServices)) {
    while ($row = $res->fetch_assoc()) {
        $latestServices[] = $row;
    }
    $res->free();
}

// Latest contact messages
$latestContacts = [];
$sqlContacts = "
    SELECT id, name, email, subject, status, created_at
    FROM contact_messages
    ORDER BY created_at DESC
    LIMIT 5
";
if ($res = $conn->query($sqlContacts)) {
    while ($row = $res->fetch_assoc()) {
        $latestContacts[] = $row;
    }
    $res->free();
}

// For greeting (optional)
$adminName = $_SESSION['admin_username'] ?? 'Admin';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - ProLink</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-sm text-gray-500">
          Welcome back, <?= h($adminName) ?>. Here is an overview of what’s happening on ProLink.
        </p>
      </div>
    </div>

    <!-- Top stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- Users -->
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">Users</div>
        <div class="text-2xl font-bold text-gray-900"><?= $totalUsers ?></div>
      </div>

      <!-- Workers -->
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">Workers</div>
        <div class="text-2xl font-bold text-gray-900"><?= $totalWorkers ?></div>
      </div>

      <!-- Services -->
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">Active services</div>
        <div class="text-2xl font-bold text-gray-900"><?= $activeServices ?></div>
        <div class="mt-1 text-[11px] text-gray-500">
          <?= $inactiveServices ?> inactive
        </div>
      </div>

      <!-- Contact messages -->
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">New contact messages</div>
        <div class="text-2xl font-bold text-gray-900"><?= $newContactMessages ?></div>
      </div>
    </div>

    <!-- Bookings summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">Total bookings</div>
        <div class="text-2xl font-bold text-gray-900"><?= $totalBookings ?></div>
      </div>
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">Pending</div>
        <div class="text-2xl font-bold text-amber-600"><?= $pendingBookings ?></div>
      </div>
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">Completed</div>
        <div class="text-2xl font-bold text-emerald-600"><?= $completedBookings ?></div>
      </div>
      <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
        <div class="text-xs font-semibold text-gray-500 mb-1">Cancelled</div>
        <div class="text-2xl font-bold text-rose-600"><?= $cancelledBookings ?></div>
      </div>
    </div>

    <!-- Bottom sections: latest activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Latest bookings -->
      <div class="bg-white rounded-xl border shadow-sm">
        <div class="px-5 py-4 border-b flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Latest bookings</h2>
        </div>
        <div class="px-5 py-3">
          <?php if (empty($latestBookings)): ?>
            <p class="text-xs text-gray-500">No bookings yet.</p>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs">
                <thead class="text-gray-500">
                  <tr class="border-b">
                    <th class="text-left py-2 pr-2">#</th>
                    <th class="text-left py-2 pr-2">Service</th>
                    <th class="text-left py-2 pr-2">User</th>
                    <th class="text-left py-2 pr-2">Worker</th>
                    <th class="text-left py-2 pr-2">Status</th>
                    <th class="text-left py-2">Date</th>
                  </tr>
                </thead>
                <tbody class="text-gray-800">
                  <?php foreach ($latestBookings as $b): ?>
                    <tr class="border-b last:border-0">
                      <td class="py-2 pr-2 text-gray-500">#<?= h($b['booking_id']) ?></td>
                      <td class="py-2 pr-2"><?= h($b['service_title']) ?></td>
                      <td class="py-2 pr-2"><?= h($b['user_name']) ?></td>
                      <td class="py-2 pr-2"><?= h($b['worker_name']) ?></td>
                      <td class="py-2 pr-2">
                        <?php
                          $status = $b['status'];
                          $badgeClass = 'bg-gray-100 text-gray-700 border-gray-200';
                          if ($status === 'pending')   $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
                          if ($status === 'accepted')  $badgeClass = 'bg-blue-50 text-blue-700 border-blue-200';
                          if ($status === 'completed') $badgeClass = 'bg-green-50 text-green-700 border-green-200';
                          if ($status === 'cancelled') $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
                        ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] border <?= $badgeClass ?>">
                          <?= h(ucfirst($status)) ?>
                        </span>
                      </td>
                      <td class="py-2 text-gray-500">
                        <?= h(date('Y-m-d H:i', strtotime($b['booking_date']))) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Latest workers -->
      <div class="bg-white rounded-xl border shadow-sm">
        <div class="px-5 py-4 border-b flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Latest workers</h2>
        </div>
        <div class="px-5 py-3">
          <?php if (empty($latestWorkers)): ?>
            <p class="text-xs text-gray-500">No workers yet.</p>
          <?php else: ?>
            <ul class="space-y-2 text-xs">
              <?php foreach ($latestWorkers as $w): ?>
                <li class="flex items-center justify-between border-b last:border-0 py-2">
                  <div>
                    <div class="font-medium text-gray-900"><?= h($w['full_name']) ?></div>
                    <div class="text-gray-500"><?= h($w['email']) ?></div>
                    <div class="text-[11px] text-gray-400">
                      Joined: <?= h(date('Y-m-d', strtotime($w['created_at']))) ?>
                    </div>
                  </div>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] border
                    <?= $w['status'] === 'active'
                        ? 'bg-green-50 text-green-700 border-green-200'
                        : 'bg-gray-100 text-gray-600 border-gray-200' ?>">
                    <?= h(ucfirst($w['status'])) ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Latest services -->
      <div class="bg-white rounded-xl border shadow-sm">
        <div class="px-5 py-4 border-b flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Latest services</h2>
        </div>
        <div class="px-5 py-3">
          <?php if (empty($latestServices)): ?>
            <p class="text-xs text-gray-500">No services yet.</p>
          <?php else: ?>
            <ul class="space-y-2 text-xs">
              <?php foreach ($latestServices as $s): ?>
                <li class="border-b last:border-0 py-2">
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="font-medium text-gray-900"><?= h($s['title']) ?></div>
                      <div class="text-gray-500">
                        <?= h($s['category']) ?> • by <?= h($s['worker_name']) ?>
                      </div>
                      <div class="text-[11px] text-gray-400">
                        Added: <?= h(date('Y-m-d', strtotime($s['created_at']))) ?>
                      </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] border
                      <?= $s['status'] === 'active'
                          ? 'bg-green-50 text-green-700 border-green-200'
                          : 'bg-amber-50 text-amber-700 border-amber-200' ?>">
                      <?= h(ucfirst($s['status'])) ?>
                    </span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <!-- Latest contact messages -->
      <div class="bg-white rounded-xl border shadow-sm">
        <div class="px-5 py-4 border-b flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Latest contact messages</h2>
        </div>
        <div class="px-5 py-3">
          <?php if (empty($latestContacts)): ?>
            <p class="text-xs text-gray-500">No contact messages yet.</p>
          <?php else: ?>
            <ul class="space-y-2 text-xs">
              <?php foreach ($latestContacts as $c): ?>
                <li class="border-b last:border-0 py-2">
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="font-medium text-gray-900"><?= h($c['subject']) ?></div>
                      <div class="text-gray-500">
                        From: <?= h($c['name']) ?> • <?= h($c['email']) ?>
                      </div>
                      <div class="text-[11px] text-gray-400">
                        Received: <?= h(date('Y-m-d H:i', strtotime($c['created_at']))) ?>
                      </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] border
                      <?= $c['status'] === 'new'
                          ? 'bg-blue-50 text-blue-700 border-blue-200'
                          : ($c['status'] === 'read'
                                ? 'bg-gray-100 text-gray-700 border-gray-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200') ?>">
                      <?= h(ucfirst($c['status'])) ?>
                    </span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
