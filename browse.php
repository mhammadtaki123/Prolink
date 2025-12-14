<?php
declare(strict_types=1);
session_start();

/**
 * Prolink • Browse Services (Tailwind version)
 * URL: /Prolink/browse.php
 *
 * Lists records from `services` joined with `workers` with optional filters:
 *   - q: search in title/description/category/location/worker name
 *   - category: exact match on services.category
 *   - min_price, max_price
 *   - location: LIKE match on services.location
 *
 * Fixes earlier bug where code tried to read w.location (location is on services).
 */

// Try common DB includes
$__db_bootstrap_files = [
  __DIR__ . '/includes/db.php',
  __DIR__ . '/config/db.php',
  __DIR__ . '/db.php',
];
foreach ($__db_bootstrap_files as $__db_file) {
  if (is_file($__db_file)) { require_once $__db_file; break; }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  // Fallback (edit if your env differs)
  $db_host = getenv('DB_HOST') ?: 'localhost';
  $db_user = getenv('DB_USER') ?: 'root';
  $db_pass = getenv('DB_PASS') ?: '';
  $db_name = getenv('DB_NAME') ?: 'prolink_db';
  $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
  if ($conn->connect_errno) {
    http_response_code(500);
    exit('Database connection failed: ' . htmlspecialchars($conn->connect_error));
  }
}
$conn->set_charset('utf8mb4');

// ------ Inputs ------
$q         = trim($_GET['q'] ?? '');
$category  = trim($_GET['category'] ?? '');
$location  = trim($_GET['location'] ?? '');
$min_price = trim($_GET['min_price'] ?? '');
$max_price = trim($_GET['max_price'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 12;
$offset    = ($page - 1) * $per_page;

// ------ Build WHERE dynamically (safe) ------
$where = [];
$params = [];
$types  = '';

if ($q !== '') {
  $where[] = "(s.title LIKE CONCAT('%', ?, '%') 
               OR s.description LIKE CONCAT('%', ?, '%')
               OR s.category LIKE CONCAT('%', ?, '%')
               OR s.location LIKE CONCAT('%', ?, '%')
               OR w.full_name LIKE CONCAT('%', ?, '%'))";
  $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
  $types .= 'sssss';
}
if ($category !== '') {
  $where[] = "s.category = ?";
  $params[] = $category;
  $types .= 's';
}
if ($location !== '') {
  $where[] = "s.location LIKE CONCAT('%', ?, '%')";
  $params[] = $location;
  $types .= 's';
}
if ($min_price !== '' && is_numeric($min_price)) {
  $where[] = "s.price >= ?";
  $params[] = (float)$min_price;
  $types .= 'd';
}
if ($max_price !== '' && is_numeric($max_price)) {
  $where[] = "s.price <= ?";
  $params[] = (float)$max_price;
  $types .= 'd';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ------ Count for pagination ------
$count_sql = "SELECT COUNT(*) 
              FROM services s 
              INNER JOIN workers w ON w.worker_id = s.worker_id
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) {
  http_response_code(500);
  exit('Prepare failed (count): ' . htmlspecialchars($conn->error));
}
if ($types !== '') {
  $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total);
$count_stmt->fetch();
$count_stmt->close();
$total = (int)$total;
$total_pages = max(1, (int)ceil($total / $per_page));

// ------ Fetch page ------
$sql = "SELECT 
          s.service_id, s.title, s.description, s.category, s.price, s.location, s.status, s.created_at,
          w.worker_id, w.full_name, w.rating, w.skill_category, w.hourly_rate
        FROM services s
        INNER JOIN workers w ON w.worker_id = s.worker_id
        $where_sql
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  exit('Prepare failed (data): ' . htmlspecialchars($conn->error));
}

// bind dynamic filters + limit/offset
$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $per_page;
$params2[] = $offset;

$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Services • Prolink</title>
  <!-- Tailwind CSS: If your project already compiles Tailwind locally, swap this CDN for your built CSS file -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
  <?php include __DIR__ . '/partials/navbar.php'; ?>
  <header class="sticky top-0 z-10 bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 py-4">
      <h1 class="text-xl font-semibold">Browse Services</h1>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 py-6">
    <!-- Filters -->
    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 mb-6" method="get">
      <input type="text" name="q" placeholder="Search services, skills or names…" value="<?= h($q) ?>"
             class="w-full rounded-xl border border-slate-300 bg-white/80 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <input type="text" name="category" placeholder="Category (e.g., Plumbing)" value="<?= h($category) ?>"
             class="w-full rounded-xl border border-slate-300 bg-white/80 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <input type="text" name="location" placeholder="Location (city)" value="<?= h($location) ?>"
             class="w-full rounded-xl border border-slate-300 bg-white/80 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <input type="number" step="0.01" name="min_price" placeholder="Min price" value="<?= h($min_price) ?>"
             class="w-full rounded-xl border border-slate-300 bg-white/80 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <input type="number" step="0.01" name="max_price" placeholder="Max price" value="<?= h($max_price) ?>"
             class="w-full rounded-xl border border-slate-300 bg-white/80 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <button type="submit"
              class="inline-flex items-center justify-center rounded-xl bg-indigo-600 text-white font-semibold px-4 py-2 hover:bg-indigo-700 transition">
        Filter
      </button>
    </form>

    <?php if (empty($rows)): ?>
      <div class="text-center text-slate-500 py-16">No services found. Try broadening your filters.</div>
    <?php else: ?>
      <!-- Cards Grid -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($rows as $r): ?>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="font-semibold text-slate-800"><?= h($r['title']) ?></div>
            <div class="text-sm text-slate-500 mt-0.5"><?= h($r['category']) ?> • <?= h($r['location'] ?? 'N/A') ?></div>
            <p class="text-sm text-slate-600 mt-2">
              <?= h(mb_strimwidth($r['description'], 0, 120, '…', 'UTF-8')) ?>
            </p>
            <div class="text-sm text-slate-500 mt-3">
              By <?= h($r['full_name']) ?> • ★ <?= number_format((float)$r['rating'], 1) ?>
            </div>
                        <div class="mt-3 flex items-center justify-between">
              <div class="font-bold text-slate-800">
                $<?= number_format((float)$r['price'], 2) ?>
              </div>
              <a
                href="service.php?id=<?= (int)$r['service_id'] ?>"
                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs hover:bg-blue-700"
              >
                View
              </a>
            </div>

          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <nav class="flex justify-center gap-2 mt-6" aria-label="Pagination">
        <?php
          // retain query except page
          $qs = $_GET; unset($qs['page']);
          for ($p = 1; $p <= $total_pages; $p++):
            $qs['page'] = $p;
            $url = '?' . http_build_query($qs);
            $isActive = $p === $page;
        ?>
          <a href="<?= $url ?>"
             class="px-3 py-2 rounded-xl border text-sm <?= $isActive ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-slate-200 text-slate-800 hover:bg-slate-50' ?>">
             <?= $p ?>
          </a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </main>
   <?php include (__DIR__) . '/partials/footer.php'; ?>
</body>
</html>