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
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15; $offset = ($page - 1) * $perPage;

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$where = [];
$params = [];
$types  = '';
if ($q !== '') {
  $where[] = '(w.full_name LIKE ? OR w.email LIKE ? OR w.phone LIKE ? OR w.skill_category LIKE ?)';
  $like = '%' . $q . '%';
  $params = array_merge($params, [$like, $like, $like, $like]);
  $types  .= 'ssss';
}
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$sqlCount = "SELECT COUNT(*) AS total FROM workers w $whereSql";
$stCount = $conn->prepare($sqlCount);
if (!$stCount) { die('Prepare failed (count): ' . h($conn->error)); }
if ($types !== '') $stCount->bind_param($types, ...$params);
$stCount->execute();
$total = ($r = $stCount->get_result()->fetch_assoc()) ? (int)$r['total'] : 0;
$stCount->close();

// Data
$sql = "SELECT w.worker_id, w.full_name, w.email, w.phone, w.skill_category, w.hourly_rate, w.created_at
        FROM workers w
        $whereSql
        ORDER BY w.worker_id DESC
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Workers</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Manage Workers</h1>

    <form method="get" class="bg-white border rounded-xl p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
      <div class="md:col-span-4">
        <label class="block text-sm mb-1">Search</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Name, email, phone, skills..." class="w-full border rounded-lg px-3 py-2">
      </div>
      <div class="md:col-span-2 flex items-end gap-2">
        <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Search</button>
        <a class="border rounded-lg px-4 py-2" href="<?= h($_SERVER['PHP_SELF']) ?>">Reset</a>
      </div>
    </form>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">No workers found.</div>
    <?php else: ?>
      <div class="bg-white rounded-xl shadow divide-y">
        <?php foreach ($rows as $w): ?>
          <div class="p-4 grid grid-cols-1 md:grid-cols-6 gap-2 items-center">
            <div><strong>#<?= (int)$w['worker_id'] ?></strong></div>
            <div>
              <div class="font-medium"><?= h($w['full_name'] ?? '—') ?></div>
              <div class="text-sm text-gray-600"><?= h($w['email'] ?? '—') ?></div>
            </div>
            <div class="text-sm text-gray-700">
              <div><?= h($w['phone'] ?? '—') ?></div>
              <div><?= h($w['skill_category'] ?? '—') ?></div>
            </div>
            <div class="text-sm text-gray-700">$<?= $w['hourly_rate'] !== null ? number_format((float)$w['hourly_rate'], 2) : '—' ?></div>
            <div class="text-sm text-gray-600">Joined: <?= h($w['created_at'] ?? '') ?></div>
            <div class="flex md:justify-end">
              <a
                class="inline-flex items-center px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700"
                href="<?= $baseUrl ?>/admin/edit-worker.php?id=<?= (int)$w['worker_id'] ?>"
              >
                Edit
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-600">Page <?= (int)$page ?> of <?= (int)$totalPages ?> (<?= (int)$total ?> total)</div>
        <div class="space-x-2">
          <?php $qs = $_GET;
            if ($page > 1) { $qs['page']=$page-1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Prev</a>'; }
            if ($page < $totalPages) { $qs['page']=$page+1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Next</a>'; } ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
