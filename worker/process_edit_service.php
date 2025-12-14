<?php
// /worker/process_edit_service.php — robust, column-aware updater
ini_set('display_errors','1'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();

$loaded=false;
foreach ([__DIR__.'/../Lib/config.php', __DIR__.'/../lib/config.php'] as $p) {
  if (is_file($p)) { require_once $p; $loaded=true; break; }
}
if (!$loaded) { http_response_code(500); exit('config not found'); }

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '')!=='worker') {
  $_SESSION['error']='Please log in as a Worker.'; redirect_to('/login.php');
}

$worker_id  = (int)($_SESSION['worker_id'] ?? 0);
$service_id = (int)($_GET['service_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $service_id <= 0) {
  $_SESSION['error'] = 'Invalid request.'; redirect_to('/dashboard/worker-dashboard.php');
}

// Verify ownership
$own = $conn->prepare("SELECT service_id FROM services WHERE service_id=? AND worker_id=? LIMIT 1");
if (!$own) { $_SESSION['error']='Server error (verify).'; redirect_to('/dashboard/worker-dashboard.php'); }
$own->bind_param('ii', $service_id, $worker_id);
$own->execute();
$isMine = (bool)$own->get_result()->fetch_row();
$own->close();
if (!$isMine) { $_SESSION['error']='Service not found or not yours.'; redirect_to('/dashboard/worker-dashboard.php'); }

// Detect available columns
$hasLocation = function_exists('col_exists') && col_exists($conn,'services','location');
$hasCity     = function_exists('col_exists') && col_exists($conn,'services','city');
$hasCountry  = function_exists('col_exists') && col_exists($conn,'services','country');
$hasStatus   = function_exists('col_exists') && col_exists($conn,'services','status');

// Collect inputs
$title       = trim($_POST['title']       ?? '');
$category    = trim($_POST['category']    ?? '');
$price       = $_POST['price']            ?? null;
$description = trim($_POST['description'] ?? '');

$location = $hasLocation ? trim($_POST['location'] ?? '') : null;
$city     = (!$hasLocation && $hasCity)    ? trim($_POST['city'] ?? '')    : null;
$country  = (!$hasLocation && $hasCountry) ? trim($_POST['country'] ?? '') : null;
$status   = $hasStatus ? trim(strtolower($_POST['status'] ?? '')) : null;

// Build dynamic update
$sets = [];
$types = '';
$vals = [];

if ($title !== '')         { $sets[]='title=?';       $types.='s'; $vals[]=$title; }
if ($category !== '')      { $sets[]='category=?';    $types.='s'; $vals[]=$category; }
if ($price !== '' && $price !== null) { $sets[]='price=?'; $types.='d'; $vals[]=(float)$price; }
if ($description !== '')   { $sets[]='description=?'; $types.='s'; $vals[]=$description; }

if ($hasLocation && $location !== null) { $sets[]='location=?'; $types.='s'; $vals[]=$location; }
if (!$hasLocation && $hasCity && $city !== null)       { $sets[]='city=?';    $types.='s'; $vals[]=$city; }
if (!$hasLocation && $hasCountry && $country !== null) { $sets[]='country=?'; $types.='s'; $vals[]=$country; }

if ($hasStatus && ($status==='active' || $status==='inactive')) { $sets[]='status=?'; $types.='s'; $vals[]=$status; }

if (!$sets) { $_SESSION['success']='Nothing to update.'; redirect_to('/worker/edit-service.php?service_id='.$service_id); }

$sql = "UPDATE services SET ".implode(', ',$sets)." WHERE service_id=? AND worker_id=? LIMIT 1";
$st = $conn->prepare($sql);
if (!$st) {
  $_SESSION['error'] = 'Server error (update prepare).';
  // For local debugging uncomment:
  // $_SESSION['error'] .= ' SQL: '.$sql.' / '.$conn->error;
  redirect_to('/worker/edit-service.php?service_id='.$service_id);
}

// Merge IDs at end to avoid PHP 8 unpacking-after-args issue
$typesAll = $types . 'ii';
$bindAll  = array_merge($vals, [$service_id, $worker_id]);
$st->bind_param($typesAll, ...$bindAll);
$st->execute();
$affected = $st->affected_rows;
$st->close();

$_SESSION['success'] = $affected >= 0 ? 'Service updated.' : 'No changes saved.';
redirect_to('/worker/edit-service.php?service_id='.$service_id);
