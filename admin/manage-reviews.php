<?php

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
if (empty($_SESSION['admin_id'])) { header('Location: ' . $baseUrl . '/admin/login.php'); exit; }
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB connection missing'; exit; }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Delete action
$action = $_GET['action'] ?? '';
$rid    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($action === 'delete' && $rid > 0) {
  $stDel = $conn->prepare('DELETE FROM reviews WHERE review_id = ?');
  if (!$stDel) {
    header('Location: ' . $baseUrl . '/admin/manage-reviews.php?err=' . urlencode('Prepare failed: ' . $conn->error));
    exit;
  }
  $stDel->bind_param('i', $rid);
  $ok = $stDel->execute();
  $err = $stDel->error;
  $stDel->close();

  if (!$ok) {
    header('Location: ' . $baseUrl . '/admin/manage-reviews.php?err=' . urlencode('Delete failed: ' . $err));
    exit;
  }

  header('Location: ' . $baseUrl . '/admin/manage-reviews.php?msg=' . urlencode('Review deleted'));
  exit;
}

// Filters
$q = trim((string)($_GET['q'] ?? ($_GET['search'] ?? '')));
$minr = isset($_GET['minr']) ? (int)$_GET['minr'] : 1;
$maxr = isset($_GET['maxr']) ? (int)$_GET['maxr'] : 5;
$minr = max(1, min(5, $minr));
$maxr = max(1, min(5, $maxr));
if ($minr > $maxr) { $tmp = $minr; $minr = $maxr; $maxr = $tmp; }

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where = ['r.rating BETWEEN ? AND ?'];
$params = [$minr, $maxr];
$types  = 'ii';
if ($q !== '') {
  $where[] = '(r.comment LIKE ? OR s.title LIKE ? OR u.full_name LIKE ? OR w.full_name LIKE ?)';
  $like = '%' . $q . '%';
  $params = array_merge($params, [$like, $like, $like, $like]);
  $types  .= 'ssss';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// Count
$sqlCount = "SELECT COUNT(*) AS total
             FROM reviews r
             JOIN services s ON s.service_id = r.service_id
             JOIN users    u ON u.user_id    = r.user_id
             JOIN workers  w ON w.worker_id  = r.worker_id
             $whereSql";
$stCount = $conn->prepare($sqlCount);
if (!$stCount) { http_response_code(500); echo 'Prepare failed (count): ' . h($conn->error); exit; }
$stCount->bind_param($types, ...$params);
$stCount->execute();
$total = ($row = $stCount->get_result()->fetch_assoc()) ? (int)$row['total'] : 0;
$stCount->close();

$totalPages = (int)ceil(max(1, $total) / $perPage);
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Data
$sql = "SELECT r.review_id, r.rating, r.comment, r.created_at,
               s.service_id, s.title AS service_title,
               u.user_id, u.full_name AS user_name,
               w.worker_id, w.full_name AS worker_name
        FROM reviews r
        JOIN services s ON s.service_id = r.service_id
        JOIN users    u ON u.user_id    = r.user_id
        JOIN workers  w ON w.worker_id  = r.worker_id
        $whereSql
        ORDER BY r.created_at DESC, r.review_id DESC
        LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);
if (!$st) { http_response_code(500); echo 'Prepare failed (data): ' . h($conn->error); exit; }

$types2  = $types . 'ii';
$params2 = array_merge($params, [$perPage, $offset]);
$st->bind_param($types2, ...$params2);
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$st->close();

function build_qs(array $extra = []): string {
  $base = [
    'q'    => $_GET['q'] ?? ($_GET['search'] ?? ''),
    'minr' => $_GET['minr'] ?? 1,
    'maxr' => $_GET['maxr'] ?? 5,
    'page' => $_GET['page'] ?? 1,
  ];
  return http_build_query(array_merge($base, $extra));
}

$flashOk  = $_GET['msg'] ?? '';
$flashErr = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Reviews</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between gap-3 mb-6">
      <h1 class="text-2xl font-bold">Manage Reviews</h1>
      <div class="text-sm text-gray-600">Total: <span class="font-medium"><?= (int)$total ?></span></div>
    </div>

    <?php if ($flashOk): ?>
      <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 border border-green-200"><?= h($flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-100 text-red-800 border border-red-200"><?= h($flashErr) ?></div>
    <?php endif; ?>

    <form method="get" class="bg-white border rounded-xl p-4 mb-6 grid grid-cols-1 md:grid-cols-10 gap-3">
      <div class="md:col-span-6">
        <label class="block text-sm mb-1">Search</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Comment, service title, user, worker..." class="w-full border rounded-lg px-3 py-2">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm mb-1">Min rating</label>
        <input type="number" name="minr" min="1" max="5" value="<?= (int)$minr ?>" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm mb-1">Max rating</label>
        <input type="number" name="maxr" min="1" max="5" value="<?= (int)$maxr ?>" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div class="md:col-span-10 flex items-center gap-2">
        <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Filter</button>
        <a class="border rounded-lg px-4 py-2" href="<?= h($_SERVER['PHP_SELF']) ?>">Reset</a>
      </div>
    </form>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">No reviews found.</div>
    <?php else: ?>
      <div class="bg-white rounded-xl shadow divide-y">
        <?php foreach ($rows as $r): ?>
          <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-2">
              <div class="text-sm text-gray-600">Review #<?= (int)$r['review_id'] ?></div>
              <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-sm font-medium"><?= (int)$r['rating'] ?>/5</div>
              <div class="text-xs text-gray-500 mt-2"><?= h($r['created_at'] ?? '') ?></div>
            </div>

            <div class="md:col-span-7">
              <div class="font-medium">
                <a class="text-blue-700 hover:underline" href="<?= $baseUrl ?>/service.php?id=<?= (int)$r['service_id'] ?>">
                  <?= h($r['service_title'] ?? 'Service') ?>
                </a>
              </div>
              <div class="text-sm text-gray-700 mt-1">
                User: <span class="font-medium"><?= h($r['user_name'] ?? '—') ?></span>
                <span class="mx-2 text-gray-400">•</span>
                Worker: <span class="font-medium"><?= h($r['worker_name'] ?? '—') ?></span>
              </div>
              <?php if (!empty($r['comment'])): ?>
                <p class="mt-2 text-gray-800 whitespace-pre-line"><?= h($r['comment']) ?></p>
              <?php else: ?>
                <p class="mt-2 text-gray-500 italic">No comment.</p>
              <?php endif; ?>
            </div>

            <div class="md:col-span-3 flex md:justify-end items-start">
              <a
                class="inline-flex items-center px-3 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700"
                onclick="return confirm('Delete this review?');"
                href="<?= $baseUrl ?>/admin/manage-reviews.php?<?= build_qs(['action' => 'delete', 'id' => (int)$r['review_id']]) ?>"
              >
                Delete
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex items-center gap-2">
          <?php if ($page > 1): ?>
            <a class="px-3 py-2 rounded-lg bg-white border" href="<?= $baseUrl ?>/admin/manage-reviews.php?<?= build_qs(['page' => $page - 1]) ?>">Prev</a>
          <?php endif; ?>

          <span class="px-3 py-2 rounded-lg bg-blue-600 text-white">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>

          <?php if ($page < $totalPages): ?>
            <a class="px-3 py-2 rounded-lg bg-white border" href="<?= $baseUrl ?>/admin/manage-reviews.php?<?= build_qs(['page' => $page + 1]) ?>">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php require_once $root . '/partials/footer.php'; ?>
</body>
</html>
