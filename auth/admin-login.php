<?php
/**
 * ProLink - Admin Login
 * Path: /Prolink/auth/admin-login.php
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

$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id = trim($_POST['id'] ?? ''); $pw = trim($_POST['password'] ?? '');
  if ($id==='' || $pw==='') { $err = 'Please fill all fields.'; }
  else {
    $st = $conn->prepare('SELECT admin_id, email, username, password FROM admins WHERE email = ? OR username = ? LIMIT 1');
    $st->bind_param('ss', $id, $id);
    $st->execute(); $res=$st->get_result(); $a=$res->fetch_assoc(); $st->close();
    if (!$a) { $err = 'Invalid credentials.'; }
    else {
      $ok=false;
      if (!empty($a['password']) && preg_match('/^\$2[aby]\$\d{2}\$/', $a['password'])) {
        $ok = password_verify($pw, $a['password']);
      }
      if (!$ok && !empty($a['password']) && hash('sha256',$pw) === $a['password']) $ok=true;
      if (!$ok && !empty($a['password']) && $pw === $a['password']) $ok=true;
      if ($ok) { $_SESSION['admin_id']=(int)$a['admin_id']; header('Location: ' . $baseUrl . '/admin/manage-services.php'); exit; }
      else { $err='Invalid credentials.'; }
    }
  }
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Login</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include dirname(__DIR__) . '/partials/navbar.php'; ?>
  <div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-4">Admin Login</h1>
    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>
    <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
      <div><label class="block text-sm mb-1">Email or Username</label><input class="w-full border rounded-lg px-3 py-2" name="id" required></div>
      <div><label class="block text-sm mb-1">Password</label><input class="w-full border rounded-lg px-3 py-2" type="password" name="password" required></div>
      <button class="w-full bg-blue-600 text-white rounded-lg py-2" type="submit">Login</button>
    </form>
  </div>
  <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body></html>
