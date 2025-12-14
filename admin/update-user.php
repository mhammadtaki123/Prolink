<?php
/**
 * ProLink - Admin Update User (enable/disable)
 * Path: /Prolink/admin/update-user.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $baseUrl . '/admin/manage-users.php'); exit; }

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action  = isset($_POST['action']) ? $_POST['action'] : '';

if ($user_id <= 0 || !in_array($action, ['enable','disable'], true)) {
  header('Location: ' . $baseUrl . '/admin/manage-users.php?err=bad+input'); exit;
}

$val = ($action === 'enable') ? 1 : 0;
$st = $conn->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
if (!$st) { header('Location: ' . $baseUrl . '/admin/manage-users.php?err=prepare'); exit; }
$st->bind_param('ii', $val, $user_id);
$ok = $st->execute();
$st->close();

header('Location: ' . $baseUrl . '/admin/manage-users.php?' . ($ok?'ok=1':'err=1'));
exit;
