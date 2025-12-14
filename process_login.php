<?php
// /process_login.php
session_start();
require_once __DIR__ . '/Lib/config.php';

// basic guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to('/login.php');
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

if (!$email || !$password) {
  $_SESSION['error'] = 'Please enter your email and password.';
  redirect_to('/login.php');
}

// Helper: verify a row (id, email, password, optional full_name) and return normalized account array
function verify_row(?array $row, string $role, string $idKey): ?array {
  if (!$row) return null;
  if (!isset($row['password'])) return null;
  // password check
  if (!password_verify($_POST['password'], $row['password'])) return null;

  // normalize name (fallback to email handle)
  $name = $row['full_name'] ?? $row['name'] ?? $row['username'] ?? '';
  if ($name === '' || $name === null) {
    $at = strpos($_POST['email'], '@');
    $name = $at === false ? $_POST['email'] : substr($_POST['email'], 0, $at);
  }

  return [
    'role'       => $role,
    'id_key'     => $idKey,
    'id'         => (int)$row[$idKey],
    'email'      => $row['email'] ?? $_POST['email'],
    'full_name'  => $name,
  ];
}

$account = null;

try {
  // 1) USERS
  $stmt = $conn->prepare("SELECT user_id, email, password, full_name FROM users WHERE email=? LIMIT 1");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $account = verify_row($row, 'user', 'user_id');

  // 2) WORKERS (if not user)
  if (!$account) {
    $stmt = $conn->prepare("SELECT worker_id, email, password, full_name FROM workers WHERE email=? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $account = verify_row($row, 'worker', 'worker_id');
  }

  // 3) ADMINS (if not worker)
  if (!$account) {
    // Some DBs don’t have full_name on admins — only pick what we’re sure about.
    $stmt = $conn->prepare("SELECT admin_id, email, password FROM admins WHERE email=? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $account = verify_row($row, 'admin', 'admin_id');
  }

} catch (Throwable $e) {
  // Database-level problem — show a friendly message and bail
  $_SESSION['error'] = 'Login failed due to a server error.';
  // Uncomment while debugging locally:
  // $_SESSION['error'] .= ' ' . $e->getMessage();
  redirect_to('/login.php');
}

if (!$account) {
  $_SESSION['error'] = 'Invalid email or password.';
  redirect_to('/login.php');
}

// Success: build session
$_SESSION = array_merge($_SESSION, [
  'logged_in'  => true,
  'role'       => $account['role'],
  'email'      => $account['email'],
  'full_name'  => $account['full_name'],
]);

// Also store role-specific id key so existing pages keep working
$_SESSION[$account['id_key']] = $account['id'];

// Send them to the proper dashboard
switch ($account['role']) {
  case 'user':
    redirect_to('/dashboard/user-dashboard.php');
  case 'worker':
    redirect_to('/dashboard/worker-dashboard.php');
  case 'admin':
    redirect_to('/dashboard/admin-dashboard.php');
  default:
    // fallback
    redirect_to('/index.php');
}
