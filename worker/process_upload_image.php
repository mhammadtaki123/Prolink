<?php
// worker/process_upload_image.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'worker') {
  redirect_to('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to('/dashboard/worker-dashboard.php');
}

$worker_id  = (int)($_SESSION['worker_id'] ?? 0);
$service_id = (int)($_POST['service_id'] ?? 0);
$caption    = trim($_POST['caption'] ?? '');

if ($worker_id <= 0 || $service_id <= 0) {
  $_SESSION['error'] = 'Invalid request.';
  redirect_to('/dashboard/worker-dashboard.php');
}

// Make sure this service belongs to the logged-in worker
$chk = $conn->prepare("SELECT service_id FROM services WHERE service_id=? AND worker_id=?");
$chk->bind_param('ii', $service_id, $worker_id);
$chk->execute();
$owned = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$owned) {
  $_SESSION['error'] = 'You do not have permission for this service.';
  redirect_to('/dashboard/worker-dashboard.php');
}

// Validate the uploaded file
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  $_SESSION['error'] = 'Please choose an image to upload.';
  redirect_to('/worker/service-images.php?service_id=' . $service_id);
}

$img = $_FILES['image'];
$allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif', 'image/webp'=>'webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $img['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
  $_SESSION['error'] = 'Unsupported image type. Allowed: JPG, PNG, GIF, WEBP.';
  redirect_to('/worker/service-images.php?service_id=' . $service_id);
}
if ($img['size'] > 5 * 1024 * 1024) { // 5MB
  $_SESSION['error'] = 'Image is too large (max 5MB).';
  redirect_to('/worker/service-images.php?service_id=' . $service_id);
}

// Build filesystem path
$ext = $allowed[$mime];
$baseName = uniqid('svc_', true) . '.' . $ext;

// Project root (parent of /worker)
$projectRoot = realpath(__DIR__ . '/..');                   // .../ProLink
$uploadDirFs = $projectRoot . '/uploads/services/' . $service_id; // filesystem path
if (!is_dir($uploadDirFs)) {
  mkdir($uploadDirFs, 0775, true);
}

// Move file
$destFs = $uploadDirFs . '/' . $baseName;
if (!move_uploaded_file($img['tmp_name'], $destFs)) {
  $_SESSION['error'] = 'Failed to save the image.';
  redirect_to('/worker/service-images.php?service_id=' . $service_id);
}

// Build URL path saved to DB (relative to web root)
$fileUrl = '/uploads/services/' . $service_id . '/' . $baseName;

// Save DB record
$stmt = $conn->prepare("INSERT INTO service_images (service_id, file_path, caption, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param('iss', $service_id, $fileUrl, $caption);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = 'Image uploaded.';
redirect_to('/worker/service-images.php?service_id=' . $service_id);
