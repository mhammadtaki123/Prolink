<?php
/**
 * ProLink - Terms of Service
 * Path: /Prolink/pages/terms.php
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
  <title>Terms • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-4">Terms of Service</h1>
    <div class="space-y-3 text-gray-700">
      <p>By using ProLink, you agree to these terms. This is a brief template you can customize.</p>
      <p><strong>Use of the service:</strong> Don’t misuse the platform or harm other users.</p>
      <p><strong>Bookings:</strong> ProLink connects users and workers; parties are responsible for fulfilling their agreements.</p>
      <p><strong>Payments:</strong> If you add payments later, include terms for refunds, disputes, and fees.</p>
      <p><strong>Termination:</strong> We may suspend accounts that violate these terms.</p>
      <p><strong>Changes:</strong> We may update these terms; continued use means you accept the new terms.</p>
    </div>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
