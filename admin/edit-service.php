<?php
/**
 * ProLink — Admin: Edit Service (image upload fixed)
 * Path: /Prolink/admin/edit-service.php
 * - Creates /uploads/services/{service_id}/ automatically
 * - Validates image type
 * - Saves relative path into service_images.file_path
 * - Requires the form to use: enctype="multipart/form-data"
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (empty($_SESSION['admin_id'])) {
  $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
  header('Location: ' . $baseUrl . '/admin/login.php'); exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($service_id <= 0) { http_response_code(400); echo 'Invalid service id'; exit; }

// Fetch current service (for display)
$st = $conn->prepare('SELECT service_id, title, category, price, location, description FROM services WHERE service_id = ? LIMIT 1');
$st->bind_param('i', $service_id);
$st->execute(); $svc = $st->get_result()->fetch_assoc(); $st->close();
if (!$svc) { http_response_code(404); echo 'Service not found'; exit; }

$ok = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Update core fields
  $title = trim($_POST['title'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $price = trim($_POST['price'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if ($title === '' || $category === '' || $price === '' || $location === '') {
    $err = 'Please fill all required fields.';
  } else {
    $up = $conn->prepare('UPDATE services SET title=?, category=?, price=?, location=?, description=? WHERE service_id=?');
    $priceF = (float)$price;
    $up->bind_param('ssdssi', $title, $category, $priceF, $location, $description, $service_id);
    if (!$up->execute()) { $err = 'Update failed: ' . $up->error; }
    $up->close();
  }

  // Handle image upload if a file was selected
  if ($err === '' && isset($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['image'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
      $err = 'Upload error (code ' . (int)$f['error'] . ').';
    } else {
      // Validate mime
      $tmp = $f['tmp_name'];
      $mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) : mime_content_type($tmp);
      $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif', 'image/webp'=>'webp'];
      if (!isset($allowed[$mime])) {
        $err = 'Only JPG, PNG, GIF, WEBP allowed. Detected: ' . htmlspecialchars($mime);
      } else {
        // Ensure directory
        $destBase = $root . '/uploads/services/' . $service_id;
        if (!is_dir($destBase) && !@mkdir($destBase, 0777, true)) {
          $err = 'Failed to create upload directory: ' . htmlspecialchars($destBase, ENT_QUOTES);
        } else {
          if (!is_writable($destBase)) { @chmod($destBase, 0777); }
          $nameBase = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($f['name'], PATHINFO_FILENAME));
          if ($nameBase === '') $nameBase = 'image';
          $ext = $allowed[$mime];
          $filename = $nameBase . '-' . time() . '.' . $ext;
          $destPath = $destBase . '/' . $filename;
          if (!@move_uploaded_file($tmp, $destPath)) {
            $err = 'Failed to move uploaded file to destination.';
          } else {
            // Save relative path for web
            $relPath = 'uploads/services/' . $service_id . '/' . $filename;
            $ins = $conn->prepare('INSERT INTO service_images (service_id, file_path) VALUES (?, ?)');
            if ($ins) {
              $ins->bind_param('is', $service_id, $relPath);
              if (!$ins->execute()) { $err = 'Saved file, but DB insert failed: ' . $ins->error; }
              $ins->close();
            }
            if ($err === '') { $ok = 'Service updated and image uploaded.'; }
          }
        }
      }
    }
  } elseif ($err === '') {
    $ok = 'Service updated.';
  }

  // Refresh fetched data after update
  $st = $conn->prepare('SELECT service_id, title, category, price, location, description FROM services WHERE service_id = ? LIMIT 1');
  $st->bind_param('i', $service_id);
  $st->execute(); $svc = $st->get_result()->fetch_assoc(); $st->close();
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Service</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-3xl mx-auto px-4 py-10">
    <div class="mb-4"><a href="<?= $baseUrl ?>/admin/manage-services.php" class="text-blue-700 underline">&larr; Back</a></div>
    <h1 class="text-2xl font-bold mb-4">Edit Service</h1>

    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($ok) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="bg-white rounded-xl border p-6 space-y-4">
      <div>
        <label class="block text-sm mb-1">Title*</label>
        <input class="w-full border rounded-lg px-3 py-2" name="title" required value="<?= h($svc['title']) ?>">
      </div>
      <div>
        <label class="block text-sm mb-1">Category*</label>
        <input class="w-full border rounded-lg px-3 py-2" name="category" required value="<?= h($svc['category']) ?>">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm mb-1">Price*</label>
          <input class="w-full border rounded-lg px-3 py-2" type="number" step="0.01" name="price" required value="<?= h($svc['price']) ?>">
        </div>
        <div>
          <label class="block text-sm mb-1">Location*</label>
          <input class="w-full border rounded-lg px-3 py-2" name="location" required value="<?= h($svc['location']) ?>">
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Description</label>
        <textarea class="w-full border rounded-lg px-3 py-2" rows="4" name="description"><?= h($svc['description']) ?></textarea>
      </div>

      <div class="pt-2">
        <label class="block text-sm mb-1">Add image</label>
        <input class="block w-full" type="file" name="image" accept="image/*">
        <p class="text-xs text-gray-600 mt-1">JPG, PNG, GIF, WEBP.</p>
      </div>

      <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Save</button>
    </form>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
