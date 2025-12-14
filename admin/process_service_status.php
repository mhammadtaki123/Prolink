<?php
// /admin/process_service_status.php — toggle service status
ini_set('display_errors','1'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();

$loaded = false;
foreach ([__DIR__.'/../Lib/config.php', __DIR__.'/../lib/config.php'] as $p) {
  if (is_file($p)) { require_once $p; $loaded = true; break; }
}
if (!$loaded) { http_response_code(500); exit('config not found'); }

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
  $_SESSION['error'] = 'Please log in as an Admin.';
  redirect_to('/admin/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to('/admin/manage-services.php');
}

$service_id = (int)($_POST['service_id'] ?? 0);
$status     = strtolower(trim($_POST['status'] ?? ''));

if ($service_id <= 0 || !in_array($status, ['active','inactive'], true)) {
  $_SESSION['error'] = 'Invalid request.';
  redirect_to('/admin/manage-services.php');
}

// Only proceed if services.status exists
$hasStatus = function_exists('col_exists') && col_exists($conn, 'services', 'status');
if (!$hasStatus) {
  $_SESSION['error'] = 'Cannot change status: services.status column not found.';
  redirect_to('/admin/manage-services.php');
}

$st = $conn->prepare("UPDATE services SET status=? WHERE service_id=? LIMIT 1");
if (!$st) {
  $_SESSION['error'] = 'Server error (prepare toggle).';
  redirect_to('/admin/manage-services.php');
}
$st->bind_param('si', $status, $service_id);
$st->execute();
$ok = $st->affected_rows >= 0;
$st->close();

$_SESSION['success'] = $ok ? 'Service status updated.' : 'No change applied.';
redirect_to('/admin/manage-services.php');
