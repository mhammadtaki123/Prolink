<?php
/**
 * ProLink - Privacy Policy
 * Path: /Prolink/pages/privacy.php
 */
session_start();
$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Privacy • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-4">Privacy Policy</h1>
    <p class="text-gray-700 mb-4">We respect your privacy. This template describes how we handle information on the platform.</p>
    <div class="space-y-3 text-gray-700">
      <p><strong>Data we collect:</strong> Account details you provide and usage data needed to operate the site.</p>
      <p><strong>How we use data:</strong> To provide and improve services, support bookings, and keep the platform secure.</p>
      <p><strong>Sharing:</strong> We don’t sell your data. We may share minimal data with service providers necessary to run the app.</p>
      <p><strong>Security:</strong> We use reasonable safeguards; no system is 100% secure.</p>
      <p><strong>Your choices:</strong> You can request to review, update, or delete your account data.</p>
      <p><strong>Contact:</strong> For privacy questions, use the <a class="text-blue-700 underline" href="<?= $baseUrl ?>/pages/contact.php">contact form</a>.</p>
    </div>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
