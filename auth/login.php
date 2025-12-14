<?php
/**
 * ProLink - User Login (with role links)
 */
session_start();
$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($conn) && ($conn instanceof mysqli)) {
  $id = trim($_POST['id'] ?? '');
  $pw = trim($_POST['password'] ?? '');
  if ($id==='' || $pw==='') { $err = 'Please fill all fields.'; }
  else {
    $st = $conn->prepare('SELECT user_id, email, password FROM users WHERE email = ? LIMIT 1');
    $st->bind_param('s', $id);
    $st->execute(); $res=$st->get_result(); $u=$res->fetch_assoc(); $st->close();
    if (!$u) { $err = 'Invalid credentials.'; }
    else {
      $ok = false;
      if (!empty($u['password']) && preg_match('/^\$2[aby]\$\d{2}\$/', $u['password'])) $ok = password_verify($pw, $u['password']);
      if (!$ok && !empty($u['password']) && hash('sha256',$pw) === $u['password']) $ok = true;
      if (!$ok && !empty($u['password']) && $pw === $u['password']) $ok = true;
      if ($ok) { $_SESSION['user_id'] = (int)$u['user_id']; header('Location: ' . $baseUrl . '/user/my-bookings.php'); exit; }
      else { $err = 'Invalid credentials.'; }
    }
  }
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>User Login • ProLink</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-4">User Login</h1>
    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>
    <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input class="w-full border rounded-lg px-3 py-2" name="id" type="email" required>
      </div>
      <div>
  <label class="block text-sm mb-1">Password</label>
  <div class="relative">
    <input
      id="user_pass"
      name="password"
      type="password"
      required
      class="w-full border rounded px-3 py-2 pr-10"
      oninput="toggleUserEye()"
    >
    <button
      type="button"
      id="user_eye"
      onclick="toggleUserPass()"
      class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-600 text-sm"
      style="display:none;"
    >
      👁
    </button>
  </div>
</div>

      <button class="w-full bg-blue-600 text-white rounded-lg py-2" type="submit">Login</button>
      <div class="text-sm text-center text-gray-600 mt-2">
        No account? <a class="text-blue-700 underline" href="<?= $baseUrl ?>/auth/register.php">Sign up</a>
      </div>
      <div class="text-xs text-center text-gray-500 mt-3">
        Worker? <a class="underline" href="<?= $baseUrl ?>/auth/worker-login.php">Log in here</a> • 
        Admin? <a class="underline" href="<?= $baseUrl ?>/admin/login.php">Log in here</a>
      </div>
    </form>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
  <script>
function toggleUserEye() {
  const input = document.getElementById('user_pass');
  const eye   = document.getElementById('user_eye');
  if (!input || !eye) return;
  eye.style.display = input.value.length ? 'block' : 'none';
}

function toggleUserPass() {
  const input = document.getElementById('user_pass');
  if (!input) return;
  input.type = (input.type === 'password') ? 'text' : 'password';
}
</script>

</body></html>
