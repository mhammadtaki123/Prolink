<?php
/**
 * ProLink - Worker Add Service
 * Path: /Prolink/worker/add-service.php
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['worker_id'])) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}
$worker_id = (int)$_SESSION['worker_id'];

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $category === '' || $location === '' || $price < 0) {
        $err = 'Please fill all required fields.';
    } else {
        $ins = $conn->prepare('INSERT INTO services (worker_id, title, category, price, location, description) VALUES (?, ?, ?, ?, ?, ?)');
        if ($ins) {
            $ins->bind_param('issdss', $worker_id, $title, $category, $price, $location, $description);
            if ($ins->execute()) {
                $service_id = $ins->insert_id;
                $ins->close();

                // Optional image upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadsDir = $root . '/uploads/services/' . $service_id;
                    if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0777, true); }
                    $name = basename($_FILES['image']['name']);
                    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/','_', pathinfo($name, PATHINFO_FILENAME));
                    $final = $safe . '-' . time() . ($ext ? ('.' . $ext) : '');
                    $targetPath = $uploadsDir . '/' . $final;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        $webPath = $baseUrl . '/uploads/services/' . $service_id . '/' . $final;
                        $caption = trim($_POST['caption'] ?? '');
                        $im = $conn->prepare('INSERT INTO service_images (service_id, file_path, caption) VALUES (?, ?, ?)');
                        if ($im) {
                            $im->bind_param('iss', $service_id, $webPath, $caption);
                            $im->execute();
                            $im->close();
                        }
                    }
                }

                header('Location: ' . $baseUrl . '/worker/services.php?ok=1');
                exit;
            } else {
                $err = 'Failed to create service: ' . $ins->error;
                $ins->close();
            }
        } else {
            $err = 'Prepare failed: ' . $conn->error;
        }
    }
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Service</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include dirname(__DIR__) . '/partials/navbar.php'; ?>
  <div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Add Service</h1>
      <a class="text-sm text-blue-700" href="<?= $baseUrl ?>/worker/services.php">&larr; Back</a>
    </div>

    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="bg-white rounded-xl shadow p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div>
        <label class="block text-sm mb-1">Title*</label>
        <input name="title" required class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Category*</label>
        <input name="category" required class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Price*</label>
        <input name="price" type="number" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Location*</label>
        <input name="location" required class="w-full border rounded-lg px-3 py-2" placeholder="e.g., Beirut, Lebanon">
      </div>
      <div>
        <label class="block text-sm mb-1">Description</label>
        <textarea name="description" rows="5" class="w-full border rounded-lg px-3 py-2" placeholder="Describe your service..."></textarea>
      </div>

      <div class="pt-2 border-t">
        <h2 class="font-semibold mb-2">First Image (optional)</h2>
        <input type="file" name="image" class="border rounded-lg px-3 py-2">
        <input type="text" name="caption" placeholder="Caption (optional)" class="border rounded-lg px-3 py-2 mt-2 w-full">
      </div>

      <div>
        <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Create</button>
      </div>
    </form>
  </div>
  <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
