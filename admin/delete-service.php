<?php
/**
 * ProLink - Admin Delete Service (and related)
 * Path: /Prolink/admin/delete-service.php
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

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . $baseUrl . '/admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $baseUrl . '/admin/manage-services.php');
    exit;
}

$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
if ($service_id <= 0) {
    header('Location: ' . $baseUrl . '/admin/manage-services.php');
    exit;
}

// helper: recursively delete directory
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

$conn->begin_transaction();
try {
    // Remove related records (if FK not cascading)
    $d1 = $conn->prepare('DELETE FROM service_images WHERE service_id = ?');
    $d1->bind_param('i', $service_id);
    $d1->execute();
    $d1->close();

    $d2 = $conn->prepare('DELETE FROM bookings WHERE service_id = ?');
    $d2->bind_param('i', $service_id);
    $d2->execute();
    $d2->close();

    $d3 = $conn->prepare('DELETE FROM services WHERE service_id = ?');
    $d3->bind_param('i', $service_id);
    if (!$d3->execute()) {
        throw new Exception('Failed to delete service.');
    }
    $d3->close();

    // Attempt to remove upload folder
    $uploadsBase = dirname(__DIR__) . '/uploads/services/' . $service_id;
    rrmdir($uploadsBase);

    $conn->commit();
    header('Location: ' . $baseUrl . '/admin/manage-services.php?ok=1');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header('Location: ' . $baseUrl . '/admin/manage-services.php?err=' . urlencode($e->getMessage()));
    exit;
}
