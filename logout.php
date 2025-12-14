<?php
// logout.php
session_start();
require_once __DIR__ . '/Lib/config.php'; // NOTE: use 'Lib' if your folder is capitalized

// wipe session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// optional: also clear any remember-me cookies here

redirect_to('/index.php'); // back to home
