<?php
/**
 * ProLink - Worker Services (List)
 * Path: /Prolink/worker/services.php
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

if (empty($_SESSION['worker_id'])) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}
$worker_id = (int)$_SESSION['worker_id'];

// Flash
$flash = '';
if (isset($_GET['ok'])) $flash = 'Action completed successfully.';
if (isset($_GET['err'])) $flash = 'Action failed: ' . htmlspecialchars($_GET['err']);

// Query services
$st = $conn->prepare("
    SELECT service_id, title, category, price, location,
           (SELECT si.file_path FROM service_images si WHERE si.service_id = s.service_id ORDER BY si.image_id ASC LIMIT 1) AS image_path
    FROM services s
    WHERE worker_id = ?
    ORDER BY service_id DESC
");
$st->bind_param('i', $worker_id);
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$st->close();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Services</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include dirname(__DIR__) . '/partials/navbar.php'; ?>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">My Services</h1>
      <a href="<?= $baseUrl ?>/worker/add-service.php" class="bg-blue-600 text-white rounded-lg px-4 py-2">Add Service</a>
    </div>

    <?php if ($flash): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($flash) ?></div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">You have not added any services yet.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($rows as $s): ?>
          <div class="bg-white rounded-xl shadow overflow-hidden flex flex-col">
            <?php if (!empty($s['image_path'])): ?>
              <img src="<?= h($s['image_path']) ?>" class="w-full h-40 object-cover" alt="">
            <?php else: ?>
              <div class="w-full h-40 bg-gray-200 flex items-center justify-center text-gray-500">No Image</div>
            <?php endif; ?>
            <div class="p-4 flex-1 flex flex-col">
              <h3 class="font-semibold mb-1"><?= h($s['title']) ?></h3>
              <div class="text-sm text-gray-600 mb-1"><?= h($s['category']) ?></div>
              <div class="text-sm text-gray-600 mb-1"><?= h($s['location']) ?></div>
              <div class="text-blue-700 font-bold mb-3">$<?= number_format((float)$s['price'], 2) ?></div>
              <div class="mt-auto flex gap-2">
                <a class="px-3 py-2 rounded-lg border" href="<?= $baseUrl ?>/worker/edit-service.php?id=<?= (int)$s['service_id'] ?>">Edit</a>
                <form method="post" action="<?= $baseUrl ?>/worker/delete-service.php" onsubmit="return confirm('Delete this service (and related bookings/images)?')">
                  <input type="hidden" name="service_id" value="<?= (int)$s['service_id'] ?>">
                  <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit">Delete</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
