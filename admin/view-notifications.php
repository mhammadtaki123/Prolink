<?php
/**
 * ProLink - Admin Notifications
 * Path: /Prolink/admin/view-notifications.php
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
    echo 'config.php not found (Lib/ or lib/)';
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection ($conn) is not available.';
    exit;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// ---- Admin auth (mirror worker logic, but for admin) ----
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . $baseUrl . '/admin/login.php');
    exit;
}
$admin_id = (int)$_SESSION['admin_id'];
// ---------------------------------------------------------

// Handle "mark all read" for this admin's notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    if ($u = $conn->prepare('
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_role = "admin"
          AND recipient_id = ?
          AND is_read = 0
    ')) {
        $u->bind_param('i', $admin_id);
        $u->execute();
        $u->close();
    }
    header('Location: ' . $baseUrl . '/admin/view-notifications.php');
    exit;
}

// Fetch notifications for THIS admin
$rows = [];
if ($st = $conn->prepare('
    SELECT notification_id, title, message, is_read, created_at
    FROM notifications
    WHERE recipient_role = "admin"
      AND recipient_id = ?
    ORDER BY created_at DESC, notification_id DESC
    LIMIT 200
')) {
    $st->bind_param('i', $admin_id);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $st->close();
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Notifications</h1>
      <form method="post">
        <input type="hidden" name="mark_all" value="1">
        <button class="px-3 py-2 border rounded-lg bg-white hover:bg-gray-50" type="submit">
          Mark all read
        </button>
      </form>
    </div>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">
        No notifications yet.
      </div>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($rows as $n): ?>
          <div class="bg-white rounded-xl border p-4 <?= $n['is_read'] ? '' : 'border-blue-300' ?>">
            <div class="flex items-center justify-between">
              <h3 class="font-semibold"><?= h($n['title']) ?></h3>
              <div class="text-xs text-gray-500">
                <?= h(date('Y-m-d H:i', strtotime($n['created_at']))) ?>
              </div>
            </div>
            <p class="text-gray-800 mt-1">
              <?= nl2br(h($n['message'])) ?>
            </p>
            <?php if (!$n['is_read']): ?>
              <span class="inline-block mt-2 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                New
              </span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
