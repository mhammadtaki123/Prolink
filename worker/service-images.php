<?php
// /worker/service-images.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'worker') {
  $_SESSION['error'] = 'Please log in as a Worker.';
  redirect_to('/login.php');
}

$worker_id  = (int)($_SESSION['worker_id'] ?? 0);
$service_id = (int)($_GET['service_id'] ?? 0);

// verify ownership
$stmt = $conn->prepare("SELECT service_id, title FROM services WHERE service_id=? AND worker_id=? LIMIT 1");
$stmt->bind_param('ii', $service_id, $worker_id);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$svc) {
  $_SESSION['error'] = 'Service not found or not yours.';
  redirect_to('/dashboard/worker-dashboard.php');
}

$err = $_SESSION['error']  ?? null;
$ok  = $_SESSION['success']?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Service Images — <?= htmlspecialchars($svc['title']) ?> | ProLink</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body class="bg-purple-50 min-h-screen">
  <?php include __DIR__ . '/../partials/navbar.php'; ?>

  <main class="max-w-3xl mx-auto p-6">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-purple-700">Images — <?= htmlspecialchars($svc['title']) ?></h1>
      <a href="<?= url('/dashboard/worker-dashboard.php') ?>" class="text-purple-700 hover:underline">← Back</a>
    </div>

    <?php if ($err): ?><div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok):  ?><div class="mb-4 p-3 rounded bg-green-100 text-green-800"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <form class="flex gap-2 items-center mb-6"
          method="post"
          enctype="multipart/form-data"
          action="<?= url('/worker/process_upload_image.php?service_id='.$service_id) ?>">
      <input type="file" name="image" accept="image/*" required class="input">
      <input type="text" name="caption" placeholder="Optional caption" class="input">
      <button class="btn btn-primary">Upload</button>
    </form>

    <?php
      $imgs = $conn->query("SELECT image_id, file_path, caption FROM service_images WHERE service_id={$service_id} ORDER BY image_id DESC")
                   ->fetch_all(MYSQLI_ASSOC);
    ?>
    <?php if (!$imgs): ?>
      <div class="p-4 rounded bg-gray-100">No images yet.</div>
    <?php else: ?>
      <div class="grid md:grid-cols-3 gap-4">
        <?php foreach ($imgs as $img): ?>
          <figure class="p-3 rounded bg-white shadow">
            <img src="<?= url('/uploads/'.rawurlencode($img['file_path'])) ?>"
                 alt="<?= htmlspecialchars($img['caption'] ?: 'Service image') ?>"
                 class="w-full h-48 object-cover rounded">
            <?php if ($img['caption']): ?>
              <figcaption class="mt-2 text-sm text-gray-600"><?= htmlspecialchars($img['caption']) ?></figcaption>
            <?php endif; ?>
            <a class="mt-2 inline-block text-red-700 hover:underline"
               href="<?= url('/worker/delete-image.php?id='.(int)$img['image_id'].'&sid='.$service_id) ?>"
               onclick="return confirm('Delete this image?')">Delete</a>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
