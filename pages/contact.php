<?php
/**
 * ProLink – Contact Page
 * Path: /Prolink/pages/contact.php
 */
session_start();
$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$ok = $_SESSION['contact_ok'] ?? '';
$err = $_SESSION['contact_error'] ?? '';
unset($_SESSION['contact_ok'], $_SESSION['contact_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contact • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold mb-4">Contact Us</h1>

    <?php if ($ok): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>

    <form method="post" action="<?= $baseUrl ?>/process/contact-submit.php" class="bg-white rounded-xl border p-6 space-y-4">
      <div>
        <label class="block text-sm mb-1">Name*</label>
        <input class="w-full border rounded-lg px-3 py-2" name="name" required>
      </div>
      <div>
        <label class="block text-sm mb-1">Email*</label>
        <input class="w-full border rounded-lg px-3 py-2" type="email" name="email" required>
      </div>
      <div>
        <label class="block text-sm mb-1">Subject*</label>
        <input class="w-full border rounded-lg px-3 py-2" name="subject" required>
      </div>
      <div>
        <label class="block text-sm mb-1">Message*</label>
        <textarea class="w-full border rounded-lg px-3 py-2" rows="6" name="message" required></textarea>
      </div>
      <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Send</button>
    </form>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
