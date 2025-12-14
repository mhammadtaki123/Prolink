<?php
// /worker-profile.php
session_start();
require_once __DIR__ . '/Lib/config.php';

$worker_id = (int)($_GET['worker_id'] ?? 0);
if ($worker_id <= 0) {
  $_SESSION['error'] = 'Worker not specified.';
  redirect_to('/index.php');
}

// helpers
function table_has_column(mysqli $conn, string $table, string $column): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('ss', $table, $column);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

// fetch worker core fields (only select what exists to avoid unknown column errors)
$select = ['w.worker_id', 'w.full_name', 'w.email', 'w.phone'];
if (table_has_column($conn, 'workers', 'skill_category')) $select[] = 'w.skill_category';
if (table_has_column($conn, 'workers', 'hourly_rate'))    $select[] = 'w.hourly_rate';
if (table_has_column($conn, 'workers', 'address'))        $select[] = 'w.address';
if (table_has_column($conn, 'workers', 'bio'))            $select[] = 'w.bio';
$sel = implode(', ', $select);

$ws = $conn->prepare("SELECT $sel FROM workers w WHERE w.worker_id=? LIMIT 1");
$ws->bind_param('i', $worker_id);
$ws->execute();
$worker = $ws->get_result()->fetch_assoc();
$ws->close();

if (!$worker) {
  $_SESSION['error'] = 'Worker not found.';
  redirect_to('/index.php');
}

// rating summary
$rt = $conn->prepare("SELECT ROUND(AVG(rating),2) AS avg_rating, COUNT(*) AS total_reviews
                      FROM reviews WHERE worker_id=?");
$rt->bind_param('i', $worker_id);
$rt->execute();
$rating = $rt->get_result()->fetch_assoc();
$rt->close();

$avg_rating = $rating['avg_rating'] ?? null;
$total_reviews = (int)($rating['total_reviews'] ?? 0);

// paginate active services for this worker
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 9;
$off  = ($page - 1) * $per;

$count = $conn->prepare("SELECT COUNT(*) AS c FROM services WHERE worker_id=? AND status='active'");
$count->bind_param('i', $worker_id);
$count->execute();
$total = (int)($count->get_result()->fetch_assoc()['c'] ?? 0);
$count->close();

$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$off   = ($page - 1) * $per;

$list = $conn->prepare("
  SELECT service_id, title, category, price, location, status, created_at
  FROM services
  WHERE worker_id=? AND status='active'
  ORDER BY created_at DESC, service_id DESC
  LIMIT ? OFFSET ?
");
$list->bind_param('iii', $worker_id, $per, $off);
$list->execute();
$services = $list->get_result();
$list->close();

// for building pagination links
function qs_wp(array $extra = []) {
  $base = [
    'worker_id' => $_GET['worker_id'] ?? '',
    'page'      => $_GET['page'] ?? 1,
  ];
  $q = http_build_query(array_merge($base, $extra));
  return url('/worker-profile.php' . ($q ? ('?' . $q) : ''));
}

$logged_in_role = $_SESSION['role'] ?? null;

// flash (if any)
$flash_err = $_SESSION['error'] ?? null;
$flash_ok  = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($worker['full_name']) ?> — ProLink Worker Profile</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-purple-50 min-h-screen">
<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">
  <?php if ($flash_ok): ?>
    <div class="mb-4 p-3 rounded bg-green-100 text-green-800"><?= htmlspecialchars($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($flash_err) ?></div>
  <?php endif; ?>

  <!-- Header -->
  <div class="bg-white rounded-2xl shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($worker['full_name']) ?></h1>
        <div class="mt-1 text-gray-700">
          <?php if (!empty($worker['skill_category'])): ?>
            <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-800 text-sm"><?= htmlspecialchars($worker['skill_category']) ?></span>
          <?php endif; ?>
          <?php if (!empty($worker['address'])): ?>
            <span class="ml-2 text-sm text-gray-600">· <?= htmlspecialchars($worker['address']) ?></span>
          <?php endif; ?>
        </div>
        <div class="mt-2 text-sm text-gray-700">
          <?php if ($avg_rating !== null): ?>
            <span class="font-medium"><?= htmlspecialchars($avg_rating) ?>/5</span>
            <span class="text-gray-500">(<?= (int)$total_reviews ?> review<?= $total_reviews==1?'':'s' ?>)</span>
          <?php else: ?>
            <span class="text-gray-500">No reviews yet</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="text-right">
        <?php if (!empty($worker['hourly_rate'])): ?>
          <div class="text-2xl font-bold text-purple-700">$<?= htmlspecialchars((string)$worker['hourly_rate']) ?>/hr</div>
          <div class="text-sm text-gray-500">Typical rate</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($worker['bio'])): ?>
      <p class="mt-4 text-gray-800 leading-relaxed"><?= nl2br(htmlspecialchars($worker['bio'])) ?></p>
    <?php endif; ?>

    <div class="mt-4 text-sm text-gray-700">
      <?php if (!empty($worker['phone'])): ?>
        <span class="mr-4"><span class="font-medium">Phone:</span> <?= htmlspecialchars($worker['phone']) ?></span>
      <?php endif; ?>
      <?php if (!empty($worker['email'])): ?>
        <span class="mr-4"><span class="font-medium">Email:</span> <?= htmlspecialchars($worker['email']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Services -->
  <div class="mt-8">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-gray-900">Active Services</h2>
      <div class="text-sm text-gray-600"><?= $total ?> total</div>
    </div>

    <?php if ($services->num_rows === 0): ?>
      <div class="p-6 bg-white rounded-2xl shadow text-gray-600">No active services yet.</div>
    <?php else: ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php while ($s = $services->fetch_assoc()): ?>
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col">
            <div class="flex-1">
              <div class="text-sm text-gray-500"><?= htmlspecialchars($s['category'] ?? '') ?></div>
              <a class="block mt-1 text-lg font-semibold text-purple-700 hover:underline"
                 href="<?= url('/service.php?id=' . $s['service_id']) ?>">
                <?= htmlspecialchars($s['title']) ?>
              </a>
              <div class="mt-1 text-gray-700 text-sm">
                <?php if (!empty($s['location'])): ?>
                  <span class="mr-2"><?= htmlspecialchars($s['location']) ?></span>
                <?php endif; ?>
                <?php if ($s['price'] !== null): ?>
                  <span class="font-medium">$<?= htmlspecialchars((string)$s['price']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="mt-3">
              <?php if (!empty($_SESSION['logged_in']) && $logged_in_role === 'user'): ?>
                <a href="<?= url('/user/book-service.php?service_id=' . $s['service_id'] . '&worker_id=' . $worker_id) ?>"
                   class="block text-center w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg">
                  Book Now
                </a>
              <?php else: ?>
                <?php
                  $next = '/user/book-service.php?service_id=' . urlencode((string)$s['service_id']) . '&worker_id=' . urlencode((string)$worker_id);
                ?>
                <a href="<?= url('/login.php?next=' . urlencode(url($next))) ?>"
                   class="block text-center w-full bg-gray-800 hover:bg-gray-900 text-white py-2 rounded-lg">
                  Login to Book
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <div class="mt-6 flex items-center gap-2">
          <?php if ($page > 1): ?>
            <a class="px-3 py-1 rounded bg-white shadow" href="<?= qs_wp(['page' => $page - 1]) ?>">Prev</a>
          <?php endif; ?>
          <span class="px-3 py-1 rounded bg-purple-600 text-white"><?= $page ?></span>
          <?php if ($page < $pages): ?>
            <a class="px-3 py-1 rounded bg-white shadow" href="<?= qs_wp(['page' => $page + 1]) ?>">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
