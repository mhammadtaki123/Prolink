<?php
// worker/delete-image.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'worker') {
  redirect_to('/login.php');
}

$image_id  = (int)($_GET['id'] ?? 0);
$service_id = (int)($_GET['sid'] ?? 0);
$worker_id = (int)($_SESSION['worker_id'] ?? 0);

if ($image_id <= 0 || $service_id <= 0) {
  redirect_to('/dashboard/worker-dashboard.php');
}

// Ensure ownership
$chk = $conn->prepare("
  SELECT si.file_path
  FROM service_images si
  JOIN services s ON si.service_id = s.service_id
  WHERE si.image_id=? AND si.service_id=? AND s.worker_id=?
");
$chk->bind_param('iii', $image_id, $service_id, $worker_id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if ($row) {
  // delete file
  $projectRoot = realpath(__DIR__ . '/..'); // .../ProLink
  $fileFs = $projectRoot . $row['file_path'];
  if (is_file($fileFs)) { @unlink($fileFs); }

  // delete db
  $del = $conn->prepare("DELETE FROM service_images WHERE image_id=?");
  $del->bind_param('i', $image_id);
  $del->execute();
  $del->close();

  $_SESSION['success'] = 'Image deleted.';
}

redirect_to('/worker/service-images.php?service_id=' . $service_id);
