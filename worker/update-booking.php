<?php
/**
 * ProLink - Worker Update Booking Status
 * Path: /Prolink/worker/update-booking.php
 * Notes:
 *  - Ensures booking belongs to logged-in worker
 *  - Validates transition
 *  - Notifies the user
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
} else {
    http_response_code(500);
    echo 'config.php not found (Lib/ or lib/)';
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection ($conn) is not available.';
    exit;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['worker_id'])) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}
$worker_id = (int)$_SESSION['worker_id'];

// CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    header('Location: ' . $baseUrl . '/worker/bookings.php?err=bad+request');
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$action     = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';

$allowed = ['accept', 'decline', 'completed', 'cancelled'];
if ($booking_id <= 0 || !in_array($action, $allowed, true)) {
    header('Location: ' . $baseUrl . '/worker/bookings.php?err=invalid+input');
    exit;
}

// Fetch booking (and ensure ownership)
$st = $conn->prepare('SELECT user_id, worker_id, service_id, status FROM bookings WHERE booking_id = ? LIMIT 1');
$st->bind_param('i', $booking_id);
$st->execute();
$res = $st->get_result();
$bk = $res->fetch_assoc();
$st->close();

if (!$bk || (int)$bk['worker_id'] !== $worker_id) {
    header('Location: ' . $baseUrl . '/worker/bookings.php?err=not+found');
    exit;
}

$current = strtolower($bk['status']);
$next = null;

// Transition rules
if ($current === 'pending' && ($action === 'accept' || $action === 'decline')) {
    $next = ($action === 'accept') ? 'accepted' : 'declined';
} elseif ($current === 'accepted' && ($action === 'completed' || $action === 'cancelled')) {
    $next = $action; // completed | cancelled
} else {
    header('Location: ' . $baseUrl . '/worker/bookings.php?err=invalid+transition');
    exit;
}

$conn->begin_transaction();
try {
    // Update status
    $u = $conn->prepare('UPDATE bookings SET status = ? WHERE booking_id = ?');
    $u->bind_param('si', $next, $booking_id);
    if (!$u->execute()) {
        throw new Exception('Failed to update booking.');
    }
    $u->close();

    // Notify user
    // Fetch service title
    $title = '';
    $st2 = $conn->prepare('SELECT title FROM services WHERE service_id = ? LIMIT 1');
    $st2->bind_param('i', $bk['service_id']);
    $st2->execute();
    $r2 = $st2->get_result()->fetch_assoc();
    $st2->close();
    $service_title = $r2 ? $r2['title'] : 'service';

    $notifTitle = 'Booking ' . ucfirst($next);
    $notifMsg   = 'Status update for "' . $service_title . '": ' . ucfirst($next) . '.';

    $n = $conn->prepare('INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at) VALUES ("user", ?, ?, ?, 0, NOW())');
    $n->bind_param('iss', $bk['user_id'], $notifTitle, $notifMsg);
    if (!$n->execute()) {
        throw new Exception('Failed to create notification.');
    }
    $n->close();

    $conn->commit();
    header('Location: ' . $baseUrl . '/worker/bookings.php?ok=1');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header('Location: ' . $baseUrl . '/worker/bookings.php?err=' . urlencode($e->getMessage()));
    exit;
}
