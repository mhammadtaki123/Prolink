<?php
// /process_register.php
session_start();
require_once __DIR__ . '/Lib/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_to('/register.php');

$role  = strtolower(trim($_POST['role'] ?? ''));
$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';
$pass2 = $_POST['confirm_password'] ?? '';
$allowed = ['user','worker','admin'];
if (!in_array($role,$allowed,true)) { $_SESSION['error']='Choose a valid account type.'; redirect_to('/register.php'); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $_SESSION['error']='Enter a valid email.'; redirect_to('/register.php'); }
if (strlen($pass)<6) { $_SESSION['error']='Password must be at least 6 characters.'; redirect_to('/register.php'); }
if ($pass!==$pass2) { $_SESSION['error']='Passwords do not match.'; redirect_to('/register.php'); }

try {
  $hash=password_hash($pass,PASSWORD_DEFAULT);

  if ($role==='admin') {
    $username=trim($_POST['username'] ?? '');
    if ($username===''){ $_SESSION['error']='Enter a username.'; redirect_to('/register.php'); }
    $chk=$conn->prepare("SELECT admin_id FROM admins WHERE email=? LIMIT 1"); $chk->bind_param('s',$email); $chk->execute();
    $exists=(bool)$chk->get_result()->fetch_row(); $chk->close();
    if ($exists){ $_SESSION['error']='Admin with this email exists.'; redirect_to('/register.php'); }
    $ins=$conn->prepare("INSERT INTO admins (username,email,password,created_at) VALUES (?,?,?,NOW())");
    $ins->bind_param('sss',$username,$email,$hash); $ins->execute(); $ins->close();
    $_SESSION['success']='Admin registered. Please log in.'; redirect_to('/login.php');

  } elseif ($role==='user') {
    $full_name=trim($_POST['full_name'] ?? ''); if ($full_name===''){ $_SESSION['error']='Enter your full name.'; redirect_to('/register.php'); }
    $phone=trim($_POST['phone'] ?? ''); $address=trim($_POST['address'] ?? '');
    $chk=$conn->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1"); $chk->bind_param('s',$email); $chk->execute();
    $exists=(bool)$chk->get_result()->fetch_row(); $chk->close();
    if ($exists){ $_SESSION['error']='User with this email exists.'; redirect_to('/register.php'); }
    $ins=$conn->prepare("INSERT INTO users (full_name,email,password,phone,address,created_at) VALUES (?,?,?,?,?,NOW())");
    $ins->bind_param('sssss',$full_name,$email,$hash,$phone,$address); $ins->execute(); $ins->close();
    $_SESSION['success']='User registered. Please log in.'; redirect_to('/login.php');

  } else { // worker
    $full_name=trim($_POST['full_name'] ?? ''); if ($full_name===''){ $_SESSION['error']='Enter your full name.'; redirect_to('/register.php'); }
    $phone=trim($_POST['phone'] ?? ''); $skill_category=trim($_POST['skill_category'] ?? ''); // DB column
    $hourly_rate = isset($_POST['hourly_rate']) && $_POST['hourly_rate']!=='' ? (float)$_POST['hourly_rate'] : null;
    $chk=$conn->prepare("SELECT worker_id FROM workers WHERE email=? LIMIT 1"); $chk->bind_param('s',$email); $chk->execute();
    $exists=(bool)$chk->get_result()->fetch_row(); $chk->close();
    if ($exists){ $_SESSION['error']='Worker with this email exists.'; redirect_to('/register.php'); }

    if ($hourly_rate===null) {
      $ins=$conn->prepare("INSERT INTO workers (full_name,email,password,phone,skill_category,created_at) VALUES (?,?,?,?,?,NOW())");
      $ins->bind_param('sssss',$full_name,$email,$hash,$phone,$skill_category);
    } else {
      $ins=$conn->prepare("INSERT INTO workers (full_name,email,password,phone,skill_category,hourly_rate,created_at) VALUES (?,?,?,?,?,?,NOW())");
      $ins->bind_param('sssssd',$full_name,$email,$hash,$phone,$skill_category,$hourly_rate);
    }
    $ins->execute(); $ins->close();
    $_SESSION['success']='Worker registered. Please log in.'; redirect_to('/login.php');
  }
} catch (Throwable $e) {
  $_SESSION['error']='Registration failed: '.$e->getMessage();
  redirect_to('/register.php');
}
