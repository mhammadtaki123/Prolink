<?php
/**
 * ProLink - User Update Booking (cancel only)
 * Path: /Prolink/user/update-booking.php
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500); echo 'DB conn missing'; exit;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['user_id'])) { header('Location: ' . $baseUrl . '/auth/login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  header('Location: ' . $baseUrl . '/user/my-bookings.php?err=bad+request'); exit;
}

$user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';

if ($booking_id <= 0 || $action !== 'cancelled') {
  header('Location: ' . $baseUrl . '/user/my-bookings.php?err=invalid+input'); exit;
}

// fetch booking and ensure ownership
$st = $conn->prepare('SELECT user_id, worker_id, service_id, status FROM bookings WHERE booking_id = ? LIMIT 1');
$st->bind_param('i', $booking_id);
$st->execute(); $bk = $st->get_result()->fetch_assoc(); $st->close();
if (!$bk || (int)$bk['user_id'] !== $user_id) { header('Location: ' . $baseUrl . '/user/my-bookings.php?err=not+found'); exit; }

$cur = strtolower($bk['status']);
if (!in_array($cur, ['pending','accepted'], true)) { header('Location: ' . $baseUrl . '/user/my-bookings.php?err=invalid+state'); exit; }

$conn->begin_transaction();
try {
  $u = $conn->prepare('UPDATE bookings SET status = "cancelled" WHERE booking_id = ?');
  $u->bind_param('i', $booking_id);
  if (!$u->execute()) { throw new Exception('Failed to cancel booking.'); }
  $u->close();

  // notify worker
  $st2 = $conn->prepare('SELECT title FROM services WHERE service_id = ? LIMIT 1');
  $st2->bind_param('i', $bk['service_id']);
  $st2->execute(); $r2=$st2->get_result()->fetch_assoc(); $st2->close();
  $title = $r2 ? $r2['title'] : 'service';

  $t = 'Booking Cancelled by User';
  $m = 'Booking for "' . $title . '" was cancelled by the user.';
  $n = $conn->prepare('INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at) VALUES ("worker", ?, ?, ?, 0, NOW())');
  $n->bind_param('iss', $bk['worker_id'], $t, $m);
  $n->execute(); $n->close();

  $conn->commit();
  header('Location: ' . $baseUrl . '/user/my-bookings.php?ok=1'); exit;
} catch (Exception $e) {
  $conn->rollback();
  header('Location: ' . $baseUrl . '/user/my-bookings.php?err=' . urlencode($e->getMessage())); exit;
}
