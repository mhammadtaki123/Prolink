<?php
// /admin/process_login.php  (ADMIN)
ini_set('display_errors','1'); ini_set('log_errors','1'); error_reporting(E_ALL);
session_start();

$loaded = false;
foreach ([__DIR__.'/../Lib/config.php', __DIR__.'/../lib/config.php'] as $p) {
  if (is_file($p)) { require_once $p; $loaded = true; break; }
}
if (!$loaded) { http_response_code(500); exit('config not found'); }

function clean_next(string $next): string {
  if ($next === '') return '';
  $u = @parse_url($next); if (!$u) return '';
  $path  = $u['path']  ?? '';
  $query = isset($u['query']) && $u['query'] !== '' ? ('?'.$u['query']) : '';
  if ($path === '') return '';
  if ($path[0] !== '/') $path = '/'.$path;

  $base = rtrim(BASE_URL, '/');
  if ($base && strncasecmp($path, $base, strlen($base)) === 0) {
    $path = (string)substr($path, strlen($base));
    if ($path === '') $path = '/';
  }

  $deny = ['/login.php','/register.php','/auth/worker-login.php','/admin/login.php'];
  foreach ($deny as $d) { if (strcasecmp($path, $d) === 0) return ''; }

  return $path.$query;
}

function find_account(mysqli $conn, string $table, string $idcol, string $email): ?array {
  $sql = "SELECT $idcol AS id, email, password AS password_hash FROM $table WHERE email=? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('s', $email);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('/admin/login.php'); }

$email    = trim($_POST['email']    ?? '');
$password = (string)($_POST['password'] ?? '');
$next     = clean_next((string)($_POST['next'] ?? ''));

if (!$email || !$password) {
  $_SESSION['error'] = 'Please enter email and password.';
  redirect_to('/admin/login.php');
}

$acct = find_account($conn, 'admins', 'admin_id', $email);
if (!$acct || !password_verify($password, (string)$acct['password_hash'])) {
  $_SESSION['error'] = 'Invalid credentials.';
  redirect_to('/admin/login.php');
}

$_SESSION['logged_in'] = true;
$_SESSION['role']      = 'admin';
$_SESSION['admin_id']  = (int)$acct['id'];

redirect_to($next !== '' ? $next : '/dashboard/admin-dashboard.php');
