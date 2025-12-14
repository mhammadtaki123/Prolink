<?php
/**
 * ProLink – Admin: Manage Services (robust)
 * Path: /Prolink/admin/manage-services.php
 * - Admin guard
 * - Search (title/category/location/description)
 * - Pagination
 * - Shows first image from service_images
 * - Delete handled here with CSRF + file cleanup
 */

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
if (empty($_SESSION['admin_id'])) {
  header('Location: ' . $baseUrl . '/admin/login.php');
  exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500);
  echo 'DB connection missing.'; exit;
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Handle DELETE (self-contained)
$ok = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf) {
    $err = 'Invalid request (CSRF).';
  } else {
    $sid = (int)($_POST['service_id'] ?? 0);
    if ($sid <= 0) {
      $err = 'Invalid service id.';
    } else {
      // Collect image paths for cleanup
      $paths = [];
      $st = $conn->prepare('SELECT file_path FROM service_images WHERE service_id = ?');
      if ($st) {
        $st->bind_param('i', $sid);
        $st->execute();
        $rs = $st->get_result();
        while ($row = $rs->fetch_assoc()) { $paths[] = $row['file_path']; }
        $st->close();
      }

      $conn->begin_transaction();
      try {
        // Remove child rows first
        $d1 = $conn->prepare('DELETE FROM service_images WHERE service_id = ?');
        if (!$d1) throw new Exception('Prepare failed: ' . $conn->error);
        $d1->bind_param('i', $sid);
        if (!$d1->execute()) throw new Exception('Delete images failed: ' . $d1->error);
        $d1->close();

        // Optionally remove bookings for this service (uncomment if desired)
        // $dB = $conn->prepare('DELETE FROM bookings WHERE service_id = ?');
        // if ($dB) { $dB->bind_param('i', $sid); $dB->execute(); $dB->close(); }

        // Finally delete the service
        $d2 = $conn->prepare('DELETE FROM services WHERE service_id = ?');
        if (!$d2) throw new Exception('Prepare failed: ' . $conn->error);
        $d2->bind_param('i', $sid);
        if (!$d2->execute()) throw new Exception('Delete service failed: ' . $d2->error);
        $d2->close();

        $conn->commit();
        $ok = 'Service #' . $sid . ' deleted.';

        // Best-effort file cleanup (relative paths under /Prolink)
        foreach ($paths as $rel) {
          $full = $root . '/' . ltrim($rel, '/');
          if (is_file($full)) { @unlink($full); }
        }
        // Remove empty dir /uploads/services/{id}
        $dir = $root . '/uploads/services/' . $sid;
        if (is_dir($dir)) { @rmdir($dir); } // succeeds only if empty
      } catch (Exception $e) {
        $conn->rollback();
        $err = $e->getMessage();
      }
    }
  }
}

// Filters & pagination
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage  = 18;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
  $where[] = '(s.title LIKE ? OR s.category LIKE ? OR s.location LIKE ? OR s.description LIKE ?)';
  $like = '%' . $q . '%';
  $params = array_merge($params, [$like, $like, $like, $like]);
  $types  .= 'ssss';
}
if ($category !== '') {
  $where[] = 's.category = ?';
  $params[] = $category;
  $types .= 's';
}
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$sqlCount = "SELECT COUNT(*) AS total FROM services s $whereSql";
$stC = $conn->prepare($sqlCount);
if (!$stC) { die('Prepare failed (count): ' . h($conn->error)); }
if ($types !== '') { $stC->bind_param($types, ...$params); }
$stC->execute();
$total = ($r = $stC->get_result()->fetch_assoc()) ? (int)$r['total'] : 0;
$stC->close();

// Data
$sql = "
SELECT
  s.service_id, s.title, s.category, s.price, s.location, s.description,
  (SELECT si.file_path FROM service_images si
     WHERE si.service_id = s.service_id
     ORDER BY si.image_id ASC LIMIT 1) AS image_path
FROM services s
$whereSql
ORDER BY s.service_id DESC
LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);
if (!$st) { die('Prepare failed (data): ' . h($conn->error)); }

if ($types === '') {
  $st->bind_param('ii', $perPage, $offset);
} else {
  $types2 = $types . 'ii';
  $params2 = array_merge($params, [$perPage, $offset]);
  $st->bind_param($types2, ...$params2);
}
$st->execute();
$res = $st->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
$st->close();

$totalPages = (int)ceil(max(1, $total) / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Services • ProLink (Admin)</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Manage Services</h1>

    <?php if ($err): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($ok) ?></div>
    <?php endif; ?>

    <form class="bg-white border rounded-xl p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3" method="get" action="">
      <div class="md:col-span-4">
        <label class="block text-sm mb-1">Search</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Title, category, location, description" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Category</label>
        <input type="text" name="category" value="<?= h($category) ?>" placeholder="Optional" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div class="md:col-span-1 flex items-end gap-2">
        <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Search</button>
        <a class="border rounded-lg px-4 py-2" href="<?= h($_SERVER['PHP_SELF']) ?>">Reset</a>
      </div>
    </form>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">No services found.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($rows as $s): ?>
          <div class="bg-white rounded-xl border overflow-hidden">
            <div class="w-full h-40 bg-gray-100 flex items-center justify-center">
              <?php if (!empty($s['image_path'])): ?>
                <img src="<?= h($s['image_path']) ?>" alt="" class="w-full h-40 object-cover">
              <?php else: ?>
                <div class="text-gray-500">No Image</div>
              <?php endif; ?>
            </div>
            <div class="p-4">
              <div class="font-semibold"><?= h($s['title']) ?></div>
              <div class="text-sm text-gray-600"><?= h($s['category'] ?? '—') ?></div>
              <div class="text-sm text-gray-600"><?= h($s['location'] ?? '—') ?></div>
              <div class="mt-1 font-semibold text-blue-700">$<?= number_format((float)$s['price'], 2) ?></div>

              <div class="mt-3 flex gap-2">
                <a href="<?= $baseUrl ?>/admin/edit-service.php?id=<?= (int)$s['service_id'] ?>"
                   class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">Edit</a>

                <form method="post" onsubmit="return confirm('Delete this service? This cannot be undone.')">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="service_id" value="<?= (int)$s['service_id'] ?>">
                  <button type="submit" class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">Delete</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-600">
          Page <?= (int)$page ?> of <?= (int)$totalPages ?> (<?= (int)$total ?> total)
        </div>
        <div class="space-x-2">
          <?php
            $qs = $_GET;
            if ($page > 1) { $qs['page'] = $page - 1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Prev</a>'; }
            if ($page < $totalPages) { $qs['page'] = $page + 1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Next</a>'; }
          ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
