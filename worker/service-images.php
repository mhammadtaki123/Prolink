<?php

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Auth
if (empty($_SESSION['worker_id']) || ($_SESSION['role'] ?? '') !== 'worker') {
  $_SESSION['error'] = 'Please log in as a Worker.';
  header('Location: ' . $baseUrl . '/auth/worker-login.php');
  exit;
}

$worker_id  = (int)($_SESSION['worker_id'] ?? 0);
$service_id = (int)($_GET['service_id'] ?? 0);

if ($service_id <= 0) {
  $_SESSION['error'] = 'Invalid service.';
  header('Location: ' . $baseUrl . '/worker/services.php');
  exit;
}

// Verify ownership
$st = $conn->prepare("SELECT service_id, title FROM services WHERE service_id = ? AND worker_id = ? LIMIT 1");
$st->bind_param('ii', $service_id, $worker_id);
$st->execute();
$svc = $st->get_result()->fetch_assoc();
$st->close();

if (!$svc) {
  $_SESSION['error'] = 'Service not found or not yours.';
  header('Location: ' . $baseUrl . '/worker/services.php');
  exit;
}

// Flash
$err = $_SESSION['error'] ?? '';
$ok  = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Fetch images
$imgs = [];
$st = $conn->prepare("SELECT image_id, file_path, caption, created_at FROM service_images WHERE service_id = ? ORDER BY image_id DESC");
$st->bind_param('i', $service_id);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $imgs[] = $row; }
$st->close();

function img_src(string $baseUrl, ?string $path): string {
  $p = trim((string)$path);
  if ($p === '') return '';
  if (preg_match('~^https?://~i', $p)) return $p;
  // If already includes baseUrl, keep as-is
  if (str_starts_with($p, $baseUrl . '/')) return $p;
  // If absolute from web root (e.g., /uploads/..), prefix baseUrl
  if (str_starts_with($p, '/')) return $baseUrl . $p;
  // Otherwise treat as relative within app
  return $baseUrl . '/' . ltrim($p, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Service Images • <?= h($svc['title']) ?> • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <main class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">Service Images</h1>
        <p class="text-sm text-gray-600 mt-1"><?= h($svc['title']) ?></p>
      </div>
      <a href="<?= $baseUrl ?>/worker/services.php" class="text-sm text-blue-700 hover:underline">← Back to My Services</a>
    </div>

    <?php if ($err): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($ok) ?></div>
    <?php endif; ?>

    <div class="bg-white border rounded-xl p-6 mb-8">
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h2 class="text-lg font-semibold">Upload new image</h2>
          <p class="text-sm text-gray-600 mt-1">Supported: JPG, PNG, GIF, WEBP • Max size: 5MB</p>
        </div>
        <div class="text-sm text-gray-500">Images: <span class="font-medium text-gray-700"><?= (int)count($imgs) ?></span></div>
      </div>

      <form class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end"
            method="post"
            enctype="multipart/form-data"
            action="<?= $baseUrl ?>/worker/process_upload_image.php">

        <input type="hidden" name="service_id" value="<?= (int)$service_id ?>">

        <div class="md:col-span-1">
          <label class="block text-sm font-medium mb-1">Image file *</label>
          <input type="file" name="image" accept="image/*" required
                 class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-700 border rounded-lg p-2" />
        </div>

        <div class="md:col-span-1">
          <label class="block text-sm font-medium mb-1">Caption</label>
          <input type="text" name="caption" placeholder="Optional caption"
                 class="w-full border rounded-lg px-3 py-2" />
        </div>

        <div class="md:col-span-1">
          <button type="submit" class="w-full bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700">Upload</button>
        </div>
      </form>

      <p class="text-xs text-gray-500 mt-3">Tip: The first uploaded image is typically used as the service thumbnail.</p>
    </div>

    <?php if (empty($imgs)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">
        No images yet. Upload at least one image to improve your service listing.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($imgs as $img): ?>
          <?php $src = img_src($baseUrl, $img['file_path'] ?? ''); ?>
          <div class="bg-white rounded-xl shadow overflow-hidden flex flex-col">
            <?php if ($src): ?>
              <img src="<?= h($src) ?>" alt="<?= h($img['caption'] ?: 'Service image') ?>" class="w-full h-56 object-cover">
            <?php else: ?>
              <div class="w-full h-56 bg-gray-200 flex items-center justify-center text-gray-500">Image missing</div>
            <?php endif; ?>

            <div class="p-4 flex-1 flex flex-col">
              <?php if (!empty($img['caption'])): ?>
                <div class="text-sm text-gray-800 mb-2"><?= h($img['caption']) ?></div>
              <?php else: ?>
                <div class="text-sm text-gray-500 mb-2">No caption</div>
              <?php endif; ?>

              <div class="mt-auto flex items-center justify-between gap-3">
                <span class="text-xs text-gray-500">#<?= (int)$img['image_id'] ?></span>
                <a href="<?= $baseUrl ?>/worker/delete-image.php?id=<?= (int)$img['image_id'] ?>&sid=<?= (int)$service_id ?>"
                   class="text-sm text-red-700 hover:underline"
                   onclick="return confirm('Delete this image?')">Delete</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
