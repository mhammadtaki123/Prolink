<?php
/**
 * ProLink - Home
 * Path: /Prolink/index.php
 */
session_start();
$root = __DIR__;
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// Get a few categories
$cats = [];
$st = $conn->prepare("SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC LIMIT 12");
$st->execute(); $res=$st->get_result();
while($r=$res->fetch_assoc()){ $cats[]=$r['category']; }
$st->close();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include __DIR__ . '/partials/navbar.php'; ?>

  <section class="bg-gradient-to-b from-blue-50 to-transparent">
    <div class="max-w-7xl mx-auto px-4 py-16 grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
      <div>
        <h1 class="text-3xl md:text-4xl font-extrabold leading-tight mb-4">Find trusted workers for any household job.</h1>
        <p class="text-gray-700 mb-6">Cleaning, gardening, repairs, and more — book skilled workers near you.</p>
        <form class="flex flex-col sm:flex-row gap-3" method="get" action="<?= $baseUrl ?>/user/browse-services.php">
          <input type="text" name="q" placeholder="What do you need?" class="flex-1 border rounded-lg px-4 py-3">
          <button class="bg-blue-600 text-white rounded-lg px-6 py-3" type="submit">Search</button>
        </form>
      </div>
      <div class="bg-white/70 border rounded-2xl p-6 shadow">
        <h2 class="font-semibold mb-3">Popular categories</h2>
        <?php if (empty($cats)): ?>
          <div class="text-gray-600">Add services to see categories here.</div>
        <?php else: ?>
          <div class="grid grid-cols-2 gap-3">
            <?php foreach ($cats as $c): ?>
              <a class="px-3 py-2 rounded-lg border hover:bg-gray-50" href="<?= $baseUrl ?>/user/browse-services.php?category=<?= urlencode($c) ?>"><?= h($c) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="max-w-7xl mx-auto px-4 py-12">
    <h2 class="text-xl font-bold mb-4">How it works</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white border rounded-xl p-5">
        <div class="font-semibold mb-2">1. Browse services</div>
        <p class="text-gray-700">Search by category, location, or keywords. Open a service to see details and images.</p>
      </div>
      <div class="bg-white border rounded-xl p-5">
        <div class="font-semibold mb-2">2. Request a booking</div>
        <p class="text-gray-700">Pick a date and time; the worker will accept or decline. You'll get a notification.</p>
      </div>
      <div class="bg-white border rounded-xl p-5">
        <div class="font-semibold mb-2">3. Get it done</div>
        <p class="text-gray-700">Once completed, you can rebook the same worker or explore more services.</p>
      </div>
    </div>
  </section>

  <?php include (__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
