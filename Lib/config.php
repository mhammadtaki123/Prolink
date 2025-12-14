<?php
// /Lib/config.php — tolerant local config (folder is /Prolink)
ini_set('display_errors','1');
ini_set('log_errors','1');
error_reporting(E_ALL);

if (!defined('PROLINK_ERROR_LOG')) {
  define('PROLINK_ERROR_LOG', __DIR__ . '/../prolink_error.log');
  ini_set('error_log', PROLINK_ERROR_LOG);
}

define('APP_DEBUG', true);

// --- DB (tolerant connect) ---
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "prolink_db";

// Don't throw mysqli exceptions; we'll handle gracefully.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($servername, $username, $password, $dbname);
if ($conn && !$conn->connect_errno) {
  @$conn->set_charset("utf8mb4");
  $GLOBALS['DB_OK'] = true;
} else {
  $GLOBALS['DB_OK'] = false;
  $GLOBALS['DB_ERR_MSG'] = $conn ? $conn->connect_error : 'mysqli init failed';
}

// --- BASE URL (match your actual folder name exactly) ---
define('BASE_URL', '/Prolink');

// --- URL helpers ---
function url(string $path = ''): string {
  $path = '/' . ltrim($path, '/');
  $full = rtrim(BASE_URL, '/') . $path;
  // collapse accidental '//' but keep 'http://'
  return preg_replace('~(?<!:)//+~', '/', $full);
}
function redirect_to(string $path): void {
  header('Location: ' . url($path));
  exit;
}

// --- Schema helpers (NOTE: nullable ?mysqli to allow null/default) ---
function table_exists(?mysqli $conn, string $table): bool {
  if (!$conn || empty($GLOBALS['DB_OK'])) return false;
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st) return false;
  $st->bind_param('s', $table);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

function col_exists(?mysqli $conn, string $table, string $col): bool {
  if (!$conn || empty($GLOBALS['DB_OK'])) return false;
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
          LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st) return false;
  $st->bind_param('ss', $table, $col);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}
