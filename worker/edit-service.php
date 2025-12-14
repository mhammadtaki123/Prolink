<?php
/**
 * ProLink - Worker Edit Service
 * Path: /Prolink/worker/edit-service.php
 * Fixes:
 *  - Correct columns (location instead of city/country)
 *  - Proper prepare() checks to avoid "bind_param() on bool"
 *  - Optional image add/delete (same as admin but scoped to own services)
 */
session_start();

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

if (empty($_SESSION['worker_id'])) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}
$worker_id = (int)$_SESSION['worker_id'];

$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($service_id <= 0) {
    header('Location: ' . $baseUrl . '/worker/services.php?err=invalid+id');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Fetch row (ownership check)
$svc = $conn->prepare('SELECT service_id, worker_id, title, category, price, location, description FROM services WHERE service_id = ? LIMIT 1');
if (!$svc) { die('Prepare failed (service): ' . h($conn->error)); }
$svc->bind_param('i', $service_id);
$svc->execute();
$service = $svc->get_result()->fetch_assoc();
$svc->close();
if (!$service || (int)$service['worker_id'] !== $worker_id) {
    header('Location: ' . $baseUrl . '/worker/services.php?err=not+found');
    exit;
}

// Handle POST actions
$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf) {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $category === '' || $location === '' || $price < 0) {
            $err = 'Please fill all required fields.';
        } else {
            $u = $conn->prepare('UPDATE services SET title=?, category=?, price=?, location=?, description=? WHERE service_id=? AND worker_id=?');
            if (!$u) { $err = 'Prepare failed (update): ' . $conn->error; }
            else {
                $u->bind_param('ssdssii', $title, $category, $price, $location, $description, $service_id, $worker_id);
                if ($u->execute()) {
                    $ok = 'Service updated.';
                    $service['title']=$title; $service['category']=$category; $service['price']=$price; $service['location']=$location; $service['description']=$description;
                } else {
                    $err = 'Execute failed (update): ' . $u->error;
                }
                $u->close();
            }
        }
    } elseif ($action === 'add_image' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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

            $ins = $conn->prepare('INSERT INTO service_images (service_id, file_path, caption) VALUES (?, ?, ?)');
            if ($ins) {
                $ins->bind_param('iss', $service_id, $webPath, $caption);
                if ($ins->execute()) {
                    $ok = 'Image added.';
                } else { $err = 'Failed to save image row: ' . $ins->error; }
                $ins->close();
            } else { $err = 'Prepare failed (image insert): ' . $conn->error; }
        } else {
            $err = 'Failed to move uploaded file.';
        }
    } elseif ($action === 'delete_image') {
        $image_id = (int)($_POST['image_id'] ?? 0);
        if ($image_id > 0) {
            $sel = $conn->prepare('SELECT file_path FROM service_images WHERE image_id=? AND service_id=?');
            $sel->bind_param('ii', $image_id, $service_id);
            $sel->execute();
            $r = $sel->get_result()->fetch_assoc();
            $sel->close();
            if ($r) {
                $del = $conn->prepare('DELETE FROM service_images WHERE image_id=?');
                $del->bind_param('i', $image_id);
                $del->execute();
                $del->close();

                $file = $r['file_path'];
                $prefix = $baseUrl . '/';
                if (strpos($file, $prefix) === 0) {
                    $relative = substr($file, strlen($prefix));
                    $disk = $root . '/' . $relative;
                    @unlink($disk);
                }
                $ok = 'Image deleted.';
            }
        }
    }
}

// Fetch images list
$imgs = [];
$si = $conn->prepare('SELECT image_id, file_path, caption FROM service_images WHERE service_id = ? ORDER BY image_id ASC');
$si->bind_param('i', $service_id);
$si->execute();
$res = $si->get_result();
while ($row = $res->fetch_assoc()) { $imgs[] = $row; }
$si->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Service</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Edit Service</h1>
      <a class="text-sm text-blue-700" href="<?= $baseUrl ?>/worker/services.php">&larr; Back</a>
    </div>

    <?php if ($ok): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>

    <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <div>
        <label class="block text-sm mb-1">Title*</label>
        <input name="title" required class="w-full border rounded-lg px-3 py-2" value="<?= h($service['title']) ?>">
      </div>
      <div>
        <label class="block text-sm mb-1">Category*</label>
        <input name="category" required class="w-full border rounded-lg px-3 py-2" value="<?= h($service['category']) ?>">
      </div>
      <div>
        <label class="block text-sm mb-1">Price*</label>
        <input name="price" type="number" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2" value="<?= h($service['price']) ?>">
      </div>
      <div>
        <label class="block text-sm mb-1">Location*</label>
        <input name="location" required class="w-full border rounded-lg px-3 py-2" value="<?= h($service['location']) ?>">
      </div>
      <div>
        <label class="block text-sm mb-1">Description</label>
        <textarea name="description" rows="5" class="w-full border rounded-lg px-3 py-2"><?= h($service['description']) ?></textarea>
      </div>
      <div>
        <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Save</button>
      </div>
    </form>

    <div class="bg-white rounded-xl shadow p-6 mt-8">
      <h2 class="text-lg font-semibold mb-3">Images</h2>
      <?php if (empty($imgs)): ?>
        <div class="text-gray-600">No images yet.</div>
      <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
          <?php foreach ($imgs as $im): ?>
            <div class="border rounded-lg overflow-hidden">
              <img src="<?= h($im['file_path']) ?>" class="w-full h-28 object-cover" alt="">
              <div class="p-2 text-sm"><?= h($im['caption']) ?></div>
              <form method="post" class="p-2 border-t" onsubmit="return confirm('Delete this image?')">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="delete_image">
                <input type="hidden" name="image_id" value="<?= (int)$im['image_id'] ?>">
                <button class="w-full text-left text-red-700">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-start">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="add_image">
        <input type="file" name="image" required class="border rounded-lg px-3 py-2">
        <input type="text" name="caption" placeholder="Caption (optional)" class="border rounded-lg px-3 py-2 flex-1">
        <button class="bg-gray-800 text-white rounded-lg px-4 py-2" type="submit">Upload</button>
      </form>
    </div>
  </div>
</body>
</html>
