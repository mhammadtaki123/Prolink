<?php
/**
 * ProLink - About
 * Path: /Prolink/pages/about.php
 */
session_start();
$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>About • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-4">About ProLink</h1>
    <p class="text-gray-700 leading-relaxed mb-4">
      ProLink connects people who need household or outdoor services with skilled local workers.
      From cleaning and gardening to repairs and maintenance, our goal is to make trustworthy help easy to find.
    </p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
      <div class="bg-white border rounded-xl p-5">
        <div class="font-semibold mb-1">Fast discovery</div>
        <p class="text-gray-700">Search by category, location, and keywords. See photos, details, and pricing upfront.</p>
      </div>
      <div class="bg-white border rounded-xl p-5">
        <div class="font-semibold mb-1">Simple booking</div>
        <p class="text-gray-700">Pick a date and time. Workers respond with Accept or Decline. You get notified instantly.</p>
      </div>
      <div class="bg-white border rounded-xl p-5">
        <div class="font-semibold mb-1">Quality & trust</div>
        <p class="text-gray-700">Admins can verify workers. Users can rebook reliable services in one click.</p>
      </div>
    </div>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
