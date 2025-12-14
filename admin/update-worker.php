<?php
/**
 * ProLink - Admin Update Worker (enable/disable/verify)
 * Path: /Prolink/admin/update-worker.php
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
if (empty($_SESSION['admin_id'])) { header('Location: ' . $baseUrl . '/admin/login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $baseUrl . '/admin/manage-workers.php'); exit; }

$worker_id = isset($_POST['worker_id']) ? (int)$_POST['worker_id'] : 0;
$action  = isset($_POST['action']) ? $_POST['action'] : '';

$valid = ['enable','disable','verify','unverify'];
if ($worker_id <= 0 || !in_array($action, $valid, true)) {
  header('Location: ' . $baseUrl . '/admin/manage-workers.php?err=bad+input'); exit;
}

if ($action === 'enable' || $action === 'disable') {
  $val = ($action === 'enable') ? 1 : 0;
  $st = $conn->prepare('UPDATE workers SET is_active = ? WHERE worker_id = ?');
  if ($st) { $st->bind_param('ii', $val, $worker_id); $st->execute(); $st->close(); }
} else {
  $val = ($action === 'verify') ? 1 : 0;
  $st = $conn->prepare('UPDATE workers SET is_verified = ? WHERE worker_id = ?');
  if ($st) { $st->bind_param('ii', $val, $worker_id); $st->execute(); $st->close(); }
}

header('Location: ' . $baseUrl . '/admin/manage-workers.php?ok=1');
exit;
