<?php
// /worker/process_login.php  (WORKER)
// Handles worker login POST from /auth/worker-login.php

ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

/**
 * Load config (supports both /Lib and /lib)
 * Expecting it to define a mysqli connection in $conn (or $mysqli) and optionally BASE_URL.
 */
$loaded = false;
foreach ([__DIR__ . '/../Lib/config.php', __DIR__ . '/../lib/config.php'] as $p) {
    if (is_file($p)) { require_once $p; $loaded = true; break; }
}
if (!$loaded) {
    http_response_code(500);
    exit('Config not found');
}

// Resolve mysqli connection variable
$db = null;
if (isset($conn) && $conn instanceof mysqli) $db = $conn;
elseif (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;

if (!$db) {
    http_response_code(500);
    exit('Database connection not found in config');
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

function redirect_back($baseUrl) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back($baseUrl);
}

$email = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    $_SESSION['error'] = 'Please enter email and password.';
    redirect_back($baseUrl);
}

/**
 * Schema (from your prolink_db.sql):
 * workers(worker_id, email, password, status)
 */
$sql = "SELECT worker_id, full_name, email, password, status
        FROM workers
        WHERE email = ?
        LIMIT 1";

$stmt = $db->prepare($sql);
if (!$stmt) {
    // Log the real error for debugging
    error_log('Worker login prepare() failed: ' . $db->error);
    $_SESSION['error'] = 'System error. Please try again.';
    redirect_back($baseUrl);
}

$stmt->bind_param('s', $email);
if (!$stmt->execute()) {
    error_log('Worker login execute() failed: ' . $stmt->error);
    $_SESSION['error'] = 'System error. Please try again.';
    $stmt->close();
    redirect_back($baseUrl);
}

$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    $_SESSION['error'] = 'Invalid credentials.';
    redirect_back($baseUrl);
}

if (isset($row['status']) && $row['status'] !== 'active') {
    $_SESSION['error'] = 'Your account is inactive. Please contact support.';
    redirect_back($baseUrl);
}

// Password is stored hashed (bcrypt) in workers.password
if (!password_verify($password, (string)$row['password'])) {
    $_SESSION['error'] = 'Invalid credentials.';
    redirect_back($baseUrl);
}

// Success: set worker session
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'worker';
$_SESSION['worker_id'] = (int)$row['worker_id'];
$_SESSION['worker_name'] = $row['full_name'] ?? $row['email'];

// Redirect destination
$next = $_POST['next'] ?? ($_GET['next'] ?? '');
if (is_string($next) && $next !== '') {
    // Basic safety: allow only relative paths within app
    $u = @parse_url($next);
    $path = $u['path'] ?? '';
    if ($path && str_starts_with($path, '/')) {
        header('Location: ' . $baseUrl . $path);
        exit;
    }
}

// Default worker landing page
header('Location: ' . $baseUrl . '/worker/services.php');
exit;
