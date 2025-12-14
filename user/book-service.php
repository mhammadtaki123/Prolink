<?php
/**
 * ProLink - Book Service (User)
 * Path: /Prolink/user/book-service.php
 * Purpose: Creates a booking using bookings.scheduled_at (DATETIME) and sends a worker notification
 * Notes:
 *   - Fix implements: $scheduled_at = $date . ' ' . $time . ':00';
 *   - Prepared statements throughout
 *   - Inserts notification with title + message
 */

session_start();

// Require config.php (support both Lib/ and lib/)
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

// Ensure $conn (mysqli) exists
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection ($conn) is not available.';
    exit;
}

// Helper: send JSON if AJAX, else redirect with querystring
function end_response($success, $message = '', $redirectOk = '/user/my-bookings.php?ok=1', $redirectErr = '/user/book-service.php?error=1')
{
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/Prolink';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $success, 'message' => $message]);
        exit;
    }

    if ($success) {
        header('Location: ' . rtrim($baseUrl, '/') . $redirectOk);
    } else {
        header('Location: ' . rtrim($baseUrl, '/') . $redirectErr . '&msg=' . urlencode($message));
    }
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

// Must be logged in as user
if (empty($_SESSION['user_id'])) {
    end_response(false, 'Please log in first.', '/auth/login.php', '/auth/login.php');
}

$user_id   = (int)($_SESSION['user_id']);
$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
$worker_id  = isset($_POST['worker_id'])  ? (int)$_POST['worker_id']  : 0;
$date       = isset($_POST['date'])       ? trim($_POST['date'])       : ''; // expected 'YYYY-MM-DD'
$time       = isset($_POST['time'])       ? trim($_POST['time'])       : ''; // expected 'HH:MM'
$notes      = isset($_POST['notes'])      ? trim($_POST['notes'])      : '';

// Basic validation
if ($service_id <= 0 || $worker_id <= 0) {
    end_response(false, 'Invalid service or worker.');
}

// Validate date/time formats
$validDate = DateTime::createFromFormat('Y-m-d', $date);
$validTime = DateTime::createFromFormat('H:i', $time);
if (!$validDate || !$validTime) {
    end_response(false, 'Please provide a valid date and time.');
}

// scheduled_at = 'YYYY-MM-DD HH:MM:00'
$scheduled_at = $date . ' ' . $time . ':00';

// Optional: fetch service title for a nicer notification
$service_title = 'service';
if ($svc = $conn->prepare('SELECT title FROM services WHERE service_id = ? LIMIT 1')) {
    $svc->bind_param('i', $service_id);
    if ($svc->execute()) {
        $res = $svc->get_result();
        if ($row = $res->fetch_assoc()) {
            $service_title = $row['title'];
        }
    }
    $svc->close();
}

$conn->begin_transaction();

try {
    // Insert booking using scheduled_at + notes + pending
    $stmt = $conn->prepare('
        INSERT INTO bookings (user_id, worker_id, service_id, scheduled_at, notes, status)
        VALUES (?, ?, ?, ?, ?, "pending")
    ');

    if (!$stmt) {
        throw new Exception('Prepare failed for bookings insert: ' . $conn->error);
    }

    // >>> The key fix is right here (bind with scheduled_at as string) <<<
    $stmt->bind_param('iiiss', $user_id, $worker_id, $service_id, $scheduled_at, $notes);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed for bookings insert: ' . $stmt->error);
    }
    $booking_id = $stmt->insert_id;
    $stmt->close();

    // Create worker notification (title + message)
    $title = 'New booking request';
    $msg   = 'New booking request for: ' . $service_title;

    $n = $conn->prepare('
        INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at)
        VALUES ("worker", ?, ?, ?, 0, NOW())
    ');
    if (!$n) {
        throw new Exception('Prepare failed for notifications insert: ' . $conn->error);
    }
    $n->bind_param('iss', $worker_id, $title, $msg);

    if (!$n->execute()) {
        throw new Exception('Execute failed for notifications insert: ' . $n->error);
    }
    $n->close();

    $conn->commit();
    end_response(true, 'Booking created successfully.');

} catch (Exception $e) {
    $conn->rollback();
    end_response(false, $e->getMessage());
}
