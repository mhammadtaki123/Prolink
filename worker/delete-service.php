<?php
/**
 * ProLink - Worker Delete Service
 * Path: /Prolink/worker/delete-service.php
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB conn missing'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

if (empty($_SESSION['worker_id'])) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}
$worker_id = (int)$_SESSION['worker_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $baseUrl . '/worker/services.php');
    exit;
}

$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
if ($service_id <= 0) {
    header('Location: ' . $baseUrl . '/worker/services.php?err=invalid+id');
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

// Ownership check
$st = $conn->prepare('SELECT worker_id FROM services WHERE service_id = ? LIMIT 1');
$st->bind_param('i', $service_id);
$st->execute();
$r = $st->get_result()->fetch_assoc();
$st->close();

if (!$r || (int)$r['worker_id'] !== $worker_id) {
    header('Location: ' . $baseUrl . '/worker/services.php?err=not+found');
    exit;
}

$conn->begin_transaction();
try {
    // Remove related rows
    $d1 = $conn->prepare('DELETE FROM service_images WHERE service_id = ?');
    $d1->bind_param('i', $service_id);
    $d1->execute(); $d1->close();

    $d2 = $conn->prepare('DELETE FROM bookings WHERE service_id = ?');
    $d2->bind_param('i', $service_id);
    $d2->execute(); $d2->close();

    $d3 = $conn->prepare('DELETE FROM services WHERE service_id = ? AND worker_id = ?');
    $d3->bind_param('ii', $service_id, $worker_id);
    if (!$d3->execute()) { throw new Exception('Failed to delete service.'); }
    $d3->close();

    // Remove upload dir
    $uploadsBase = $root . '/uploads/services/' . $service_id;
    rrmdir($uploadsBase);

    $conn->commit();
    header('Location: ' . $baseUrl . '/worker/services.php?ok=1');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    header('Location: ' . $baseUrl . '/worker/services.php?err=' . urlencode($e->getMessage()));
    exit;
}
