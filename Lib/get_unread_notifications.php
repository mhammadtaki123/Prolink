<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
  echo json_encode(['count' => 0]); exit;
}
$role = $_SESSION['role'] ?? '';
if ($role === 'user') {
  $id = (int)($_SESSION['user_id'] ?? 0);
} elseif ($role === 'worker') {
  $id = (int)($_SESSION['worker_id'] ?? 0);
} elseif ($role === 'admin') {
  $id = (int)($_SESSION['admin_id'] ?? 0);
} else {
  echo json_encode(['count' => 0]);
  exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE recipient_role=? AND recipient_id=? AND is_read=0");
$stmt->bind_param("si", $role, $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
echo json_encode(['count' => intval($row['c'] ?? 0)]);
