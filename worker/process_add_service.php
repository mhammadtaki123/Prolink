<?php
// /worker/process_add_service.php
session_start();
require_once __DIR__ . '/../Lib/config.php';
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'worker') redirect_to('/login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_to('/worker/add-service.php');

$worker_id  = (int)($_SESSION['worker_id'] ?? 0);
$title      = trim($_POST['title'] ?? '');
$category   = trim($_POST['category'] ?? '');
$description= trim($_POST['description'] ?? '');
$location   = trim($_POST['location'] ?? '');
$price_raw  = $_POST['price'] ?? '';

if ($worker_id<=0){ $_SESSION['error']='Session expired.'; redirect_to('/login.php'); }
if ($title===''){ $_SESSION['error']='Title is required.'; redirect_to('/worker/add-service.php'); }
if ($category===''){ $_SESSION['error']='Category is required.'; redirect_to('/worker/add-service.php'); }
if ($description===''){ $_SESSION['error']='Description is required.'; redirect_to('/worker/add-service.php'); }
if ($price_raw==='' || !is_numeric($price_raw)){ $_SESSION['error']='Price must be a number.'; redirect_to('/worker/add-service.php'); }
$price=(float)$price_raw; if ($price<0){ $_SESSION['error']='Price cannot be negative.'; redirect_to('/worker/add-service.php'); }

try {
  $sql="INSERT INTO services (worker_id,title,description,category,price,location,status,created_at)
        VALUES (?,?,?,?,?,?,'inactive',NOW())";
  $st=$conn->prepare($sql);
  $st->bind_param('isssds', $worker_id, $title, $description, $category, $price, $location);
  $st->execute(); $service_id=(int)$st->insert_id; $st->close();

  // confirmation to worker
  $n=$conn->prepare("INSERT INTO notifications (recipient_role,recipient_id,title,message,is_read,created_at)
                     VALUES ('worker', ?, 'New service submitted', ?, 0, NOW())");
  $msg="You added “{$title}”. An admin can activate it.";
  $n->bind_param('is',$worker_id,$msg); $n->execute(); $n->close();

// notify admin (first admin in admins table)
if ($conn instanceof mysqli) {
  if ($resAdm = $conn->query("SELECT admin_id FROM admins ORDER BY admin_id ASC LIMIT 1")) {
    if ($rowAdm = $resAdm->fetch_assoc()) {
      $adminId = (int)$rowAdm['admin_id'];
      if ($adminId > 0) {
        $nAdm = $conn->prepare("INSERT INTO notifications (recipient_role,recipient_id,title,message,is_read,created_at) VALUES ('admin', ?, ?, ?, 0, NOW())");
        if ($nAdm) {
          $titleAdm = 'New service submitted';
          $msgAdm   = "Worker #{$worker_id} submitted a new service: “{$title}”.";
          $nAdm->bind_param('iss', $adminId, $titleAdm, $msgAdm);
          $nAdm->execute();
          $nAdm->close();
        }
      }
    }
    $resAdm->free();
  }
}

  $_SESSION['success']='Service created! It is currently inactive until approved.';
  redirect_to('/worker/service-images.php?service_id='.$service_id);
} catch (Throwable $e) {
  $_SESSION['error']='Could not create service: '.$e->getMessage();
  redirect_to('/worker/add-service.php');
}
