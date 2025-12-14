<?php
// /user/process_review.php
session_start();
require_once __DIR__ . '/../Lib/config.php';
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'user') redirect_to('/login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_to('/dashboard/user-dashboard.php');

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$booking_id = (int)($_POST['booking_id'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 0);
$comment    = trim($_POST['comment'] ?? '');

if ($booking_id<=0 || $rating<1 || $rating>5) { $_SESSION['error']='Invalid review submission.'; redirect_to('/dashboard/user-dashboard.php'); }

try {
  // ensure booking belongs to user and is completed
  $bk=$conn->prepare("
    SELECT b.booking_id, b.status, b.service_id, s.title, s.worker_id
    FROM bookings b JOIN services s ON s.service_id=b.service_id
    WHERE b.booking_id=? AND b.user_id=? AND b.status='completed' LIMIT 1
  ");
  $bk->bind_param('ii',$booking_id,$user_id); $bk->execute(); $booking=$bk->get_result()->fetch_assoc(); $bk->close();
  if (!$booking) { $_SESSION['error']='This booking is not eligible for review.'; redirect_to('/dashboard/user-dashboard.php'); }

  // unique by booking_id
  $q=$conn->prepare("SELECT review_id FROM reviews WHERE booking_id=? LIMIT 1");
  $q->bind_param('i',$booking_id); $q->execute(); $exists=(bool)$q->get_result()->fetch_row(); $q->close();
  if ($exists) { $_SESSION['error']='You already reviewed this booking.'; redirect_to('/dashboard/user-dashboard.php'); }

  // insert review (no status column in DB)
  $ins=$conn->prepare("
    INSERT INTO reviews (booking_id, user_id, worker_id, service_id, rating, comment, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $ins->bind_param('iiiiis', $booking_id, $user_id, $booking['worker_id'], $booking['service_id'], $rating, $comment);
  $ins->execute(); $ins->close();

  // notify worker
  $title = 'New review submitted';
  $msg   = "A customer reviewed “{$booking['title']}” with {$rating}/5.";
  $n = $conn->prepare("INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at)
                       VALUES ('worker', ?, ?, ?, 0, NOW())");
  $rid = (int)$booking['worker_id'];
  $n->bind_param('iss', $rid, $title, $msg); $n->execute(); $n->close();

  $_SESSION['success']='Thanks! Your review was submitted.';
  redirect_to('/dashboard/user-dashboard.php');
} catch (Throwable $e) {
  $_SESSION['error']='Could not submit review: '.$e->getMessage();
  redirect_to('/dashboard/user-dashboard.php');
}
