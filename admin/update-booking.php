<?php
/**
 * ProLink - Admin Update Booking (enum-aligned)
 * Path: /Prolink/admin/update-booking.php
 * Allowed actions: accept, completed, cancelled, delete
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection ($conn) is not available.';
    exit;
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . $baseUrl . '/admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    header('Location: ' . $baseUrl . '/admin/manage-bookings.php?err=bad+request');
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$action     = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';

$allowed = ['accept','completed','cancelled','delete'];
if ($booking_id <= 0 || !in_array($action, $allowed, true)) {
    header('Location: ' . $baseUrl . '/admin/manage-bookings.php?err=invalid+input');
    exit;
}

// Fetch booking
$st = $conn->prepare('SELECT user_id, worker_id, service_id, status FROM bookings WHERE booking_id = ? LIMIT 1');
$st->bind_param('i', $booking_id);
$st->execute();
$res = $st->get_result();
$bk = $res->fetch_assoc();
$st->close();

if (!$bk) {
    header('Location: ' . $baseUrl . '/admin/manage-bookings.php?err=not+found');
    exit;
}

$current = strtolower($bk['status']);
$next = null;
$isDelete = ($action === 'delete');

if (!$isDelete) {
    if ($current === 'pending' && $action === 'accept') { $next = 'accepted'; }
    elseif ($current === 'pending' && $action === 'cancelled') { $next = 'cancelled'; }
    elseif ($current === 'accepted' && in_array($action, ['completed','cancelled'], true)) { $next = $action; }
    else {
        header('Location: ' . $baseUrl . '/admin/manage-bookings.php?err=invalid+transition');
        exit;
    }
}

$conn->begin_transaction();
try {
    $st2 = $conn->prepare('SELECT title FROM services WHERE service_id = ? LIMIT 1');
    $st2->bind_param('i', $bk['service_id']);
    $st2->execute();
    $r2 = $st2->get_result()->fetch_assoc();
    $st2->close();
    $service_title = $r2 ? $r2['title'] : 'service';

    if ($isDelete) {
        $d = $conn->prepare('DELETE FROM bookings WHERE booking_id = ?');
        $d->bind_param('i', $booking_id);
        if (!$d->execute()) { throw new Exception('Failed to delete booking.'); }
        $d->close();

        $t = 'Booking Deleted by Admin';
        $m = 'Your booking for "' . $service_title . '" was deleted by admin.';
        $n1 = $conn->prepare('INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at) VALUES ("user", ?, ?, ?, 0, NOW())');
        $n1->bind_param('iss', $bk['user_id'], $t, $m); $n1->execute(); $n1->close();
        $n2 = $conn->prepare('INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at) VALUES ("worker", ?, ?, ?, 0, NOW())');
        $n2->bind_param('iss', $bk['worker_id'], $t, $m); $n2->execute(); $n2->close();
    } else {
        $u = $conn->prepare('UPDATE bookings SET status = ? WHERE booking_id = ?');
        $u->bind_param('si', $next, $booking_id);
        if (!$u->execute()) { throw new Exception('Failed to update booking.'); }
        $u->close();

        $t = 'Booking ' . ucfirst($next) . ' (by Admin)';
        $m = 'Status update for "' . $service_title . '": ' . ucfirst($next) . '.';
        $n1 = $conn->prepare('INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at) VALUES ("user", ?, ?, ?, 0, NOW())');
        $n1->bind_param('iss', $bk['user_id'], $t, $m); $n1->execute(); $n1->close();
        $n2 = $conn->prepare('INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at) VALUES ("worker", ?, ?, ?, 0, NOW())');
        $n2->bind_param('iss', $bk['worker_id'], $t, $m); $n2->execute(); $n2->close();
    }

    $conn->commit();
    header('Location: ' . $baseUrl . '/admin/manage-bookings.php?ok=1');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header('Location: ' . $baseUrl . '/admin/manage-bookings.php?err=' . urlencode($e->getMessage()));
    exit;
}
