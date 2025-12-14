<?php
/**
 * ProLink – Process Contact Form
 * Path: /Prolink/process/contact-submit.php
 * Behavior:
 *  - Validates fields
 *  - Tries to INSERT into DB (contact_messages)
 *  - If table is missing or DB fails, appends JSON line to /storage/contact/messages.txt
 *  - (Optional) tries to send mail if MAIL_TO is configured in config.php
 *  - Redirects back to pages/contact.php with status
 */
session_start();
$root = __DIR__ . '/..'; // /Prolink
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

function clean($s){ return trim(filter_var($s ?? '', FILTER_SANITIZE_STRING)); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $baseUrl . '/pages/contact.php'); exit;
}

$name    = clean($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = clean($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

$errors = [];
if ($name === '')    $errors[] = 'Name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
if ($subject === '') $errors[] = 'Subject is required.';
if ($message === '') $errors[] = 'Message is required.';

if ($errors) {
  $_SESSION['contact_error'] = implode(' ', $errors);
  header('Location: ' . $baseUrl . '/pages/contact.php'); exit;
}

// Try DB insert
$stored = false;
if (isset($conn) && ($conn instanceof mysqli)) {
  // Ensure table exists (lightweight check)
  $exists = $conn->query("SHOW TABLES LIKE 'contact_messages'");
  if ($exists && $exists->num_rows > 0) {
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param('ssss', $name, $email, $subject, $message);
      if ($stmt->execute()) { $stored = true; }
// Notify admin about new contact message
if ($stored && $conn instanceof mysqli) {
  if ($resAdm = $conn->query("SELECT admin_id FROM admins ORDER BY admin_id ASC LIMIT 1")) {
    if ($rowAdm = $resAdm->fetch_assoc()) {
      $adminId = (int)$rowAdm['admin_id'];
      if ($adminId > 0) {
        $nAdm = $conn->prepare("INSERT INTO notifications (recipient_role,recipient_id,title,message,is_read,created_at) VALUES ('admin', ?, ?, ?, 0, NOW())");
        if ($nAdm) {
          $titleAdm = 'New contact message';
          $msgAdm   = "New contact message from {$name} <{$email}>: {$subject}";
          $nAdm->bind_param('iss', $adminId, $titleAdm, $msgAdm);
          $nAdm->execute();
          $nAdm->close();
        }
      }
    }
    $resAdm->free();
  }
}

      $stmt->close();
    }
  }
}

// Fallback to file
if (!$stored) {
  $dir = $root . '/storage/contact';
  $file = $dir . '/messages.txt';
  if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
  $row = json_encode([
    'time' => date('Y-m-d H:i:s'),
    'name' => $name,
    'email'=> $email,
    'subject'=>$subject,
    'message'=>$message
  ], JSON_UNESCAPED_UNICODE);
  @file_put_contents($file, $row . PHP_EOL, FILE_APPEND);
}

// Optional email notification
// if (defined('MAIL_TO') && filter_var(MAIL_TO, FILTER_VALIDATE_EMAIL)) {
//   @mail(MAIL_TO, "[ProLink] Contact: $subject", "From: $name <$email>\n\n$message");
// }

$_SESSION['contact_ok'] = 'Thanks! Your message has been received.';
header('Location: ' . $baseUrl . '/pages/contact.php?sent=1');
exit;
