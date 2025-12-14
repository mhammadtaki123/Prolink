<?php
/**
 * ProLink - Browse Services (Public)
 * Path: /Prolink/user/browse-services.php
 * Notes:
 *  - Uses services.location (not city/country)
 *  - Simple search + category filter + pagination
 *  - Safe prepared statements
 */

// Allow direct access without session requirement
$root = dirname(__DIR__);
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

// Inputs
$q        = isset($_GET['q'])        ? trim($_GET['q'])        : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$page     = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1;
$perPage  = 9;
$offset   = ($page - 1) * $perPage;

$where = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = '(s.title LIKE ? OR s.category LIKE ? OR s.location LIKE ? OR s.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'ssss';
}
if ($category !== '') {
    $where[] = 's.category = ?';
    $params[] = $category;
    $types   .= 's';
}

$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$sqlCount = "SELECT COUNT(*) AS total FROM services s $whereSql";
$stCount = $conn->prepare($sqlCount);
if (!$stCount) {
    http_response_code(500);
    echo 'Prepare failed (count): ' . $conn->error;
    exit;
}
if ($types !== '') {
    $stCount->bind_param($types, ...$params);
}
$stCount->execute();
$resCount = $stCount->get_result();
$total = ($row = $resCount->fetch_assoc()) ? (int)$row['total'] : 0;
$stCount->close();

// Data query (subselect first image)
$sql = "
SELECT
  s.service_id,
  s.title,
  s.category,
  s.price,
  s.location,
  s.description,
  (
    SELECT si.file_path
    FROM service_images si
    WHERE si.service_id = s.service_id
    ORDER BY si.image_id ASC
    LIMIT 1
  ) AS image_path
FROM services s
$whereSql
ORDER BY s.service_id DESC
LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
if (!$st) {
    http_response_code(500);
    echo 'Prepare failed (data): ' . $conn->error;
    exit;
}

if ($types === '') {
    $st->bind_param('ii', $perPage, $offset);
} else {
    $types2 = $types . 'ii';
    $params2 = array_merge($params, [$perPage, $offset]);
    $st->bind_param($types2, ...$params2);
}
$st->execute();
$res = $st->get_result();
$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}
$st->close();

$totalPages = (int)ceil(max(1, $total) / $perPage);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Browse Services</title>
    <?php include dirname(__DIR__) . '/partials/navbar.php';?>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Browse Services</h1> 
 
    <form class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-3" method="get" action="">
      <input type="text" name="q" placeholder="Search services..." value="<?= h($q) ?>" class="border rounded-lg px-3 py-2 w-full">
      <input type="text" name="category" placeholder="Filter by category" value="<?= h($category) ?>" class="border rounded-lg px-3 py-2 w-full">
      <button class="md:col-span-1 bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Search</button>
      <a href="<?= h($_SERVER['PHP_SELF']) ?>" class="md:col-span-1 text-center border rounded-lg px-4 py-2">Reset</a>
    </form>

    <?php if ($total === 0): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">No services found.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($items as $it): ?>
          <div class="bg-white shadow rounded-xl overflow-hidden flex flex-col">
            <?php if (!empty($it['image_path'])): ?>
              <img src="<?= h($it['image_path']) ?>" alt="<?= h($it['title']) ?>" class="w-full h-40 object-cover">
            <?php else: ?>
              <div class="w-full h-40 bg-gray-200 flex items-center justify-center text-gray-500">No Image</div>
            <?php endif; ?>
            <div class="p-4 flex-1 flex flex-col">
              <h3 class="text-lg font-semibold mb-1"><?= h($it['title']) ?></h3>
              <div class="text-sm text-gray-600 mb-1"><?= h($it['category']) ?></div>
              <div class="text-sm text-gray-600 mb-2"><?= h($it['location']) ?></div>
              <div class="text-blue-700 font-bold mb-3">$<?= number_format((float)$it['price'], 2) ?></div>
              <p class="text-sm text-gray-700 line-clamp-3 mb-4"><?= h(mb_strimwidth($it['description'] ?? '', 0, 160, '…', 'UTF-8')) ?></p>
              <div class="mt-auto">
              <a
              href="<?= $baseUrl ?>/service.php?id=<?= (int)$it['service_id'] ?>"
              class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg px-4 py-2"
        >
          View
        </a>
      </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <div class="mt-8 flex items-center justify-between">
        <div class="text-sm text-gray-600">Showing page <?= (int)$page ?> of <?= (int)$totalPages ?> (<?= (int)$total ?> total)</div>
        <div class="space-x-2">
          <?php
            $qs = $_GET;
            if ($hasPrev) { $qs['page'] = $page - 1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Prev</a>'; }
            if ($hasNext) { $qs['page'] = $page + 1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Next</a>'; }
          ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
   <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
