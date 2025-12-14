<?php
// /user/process_payment.php
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['user_id'])) {
  header('Location: ' . $baseUrl . '/auth/login.php');
  exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500);
  echo 'DB conn missing';
  exit;
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $baseUrl . '/user/my-bookings.php');
  exit;
}

$user_id    = (int)$_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$method     = $_POST['method'] ?? '';

if ($booking_id <= 0 || !in_array($method, ['cash','card'], true)) {
  $_SESSION['error'] = 'Invalid payment data.';
  header('Location: ' . $baseUrl . '/user/my-bookings.php');
  exit;
}

// already paid?
$st = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id = ? LIMIT 1");
$st->bind_param('i', $booking_id);
$st->execute();
$st->store_result();
if ($st->num_rows > 0) {
  $st->close();
  $_SESSION['error'] = 'This booking is already paid.';
  header('Location: ' . $baseUrl . '/user/my-bookings.php');
  exit;
}
$st->close();

// load booking + price + worker
$sql = "
SELECT b.booking_id, b.status, b.user_id, b.worker_id,
       s.price
FROM bookings b
JOIN services s ON s.service_id = b.service_id
WHERE b.booking_id = ? AND b.user_id = ?
LIMIT 1
";
$st = $conn->prepare($sql);
$st->bind_param('ii', $booking_id, $user_id);
$st->execute();
$res = $st->get_result();
$booking = $res->fetch_assoc();
$st->close();

if (!$booking) {
  $_SESSION['error'] = 'Booking not found.';
  header('Location: ' . $baseUrl . '/user/my-bookings.php');
  exit;
}
if (strtolower($booking['status']) !== 'completed') {
  $_SESSION['error'] = 'Booking must be completed by worker before payment.';
  header('Location: ' . $baseUrl . '/user/my-bookings.php');
  exit;
}

$worker_id = (int)$booking['worker_id'];
$amount    = (float)$booking['price'];

// insert payment
$st = $conn->prepare("
  INSERT INTO payments (booking_id, user_id, worker_id, amount, method, status)
  VALUES (?, ?, ?, ?, ?, 'completed')
");
$st->bind_param('iiids', $booking_id, $user_id, $worker_id, $amount, $method);
if (!$st->execute()) {
  $_SESSION['error'] = 'Could not record payment. Please try again.';
  $st->close();
  header('Location: ' . $baseUrl . '/user/my-bookings.php');
  exit;
}
$st->close();

// (optional) you could set booking status to 'completed' again or to another value like 'paid'
// UPDATE bookings SET status='completed' WHERE booking_id = ?

$_SESSION['success'] = 'Payment completed successfully.';
header('Location: ' . $baseUrl . '/user/my-bookings.php');
exit;
