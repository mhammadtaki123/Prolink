<?php

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); exit('config.php not found'); }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// Admin auth
if (empty($_SESSION['admin_id']) && (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin')) {
  $next = $baseUrl . '/admin/edit-worker.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '');
  header('Location: ' . $baseUrl . '/admin/login.php?next=' . urlencode($next));
  exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
  http_response_code(500);
  exit('DB connection missing');
}

function h($s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$worker_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($worker_id <= 0) {
  header('Location: ' . $baseUrl . '/admin/manage-workers.php?err=' . urlencode('Missing worker id'));
  exit;
}

$hasSkill  = function_exists('col_exists') ? col_exists($conn, 'workers', 'skill_category') : true;
$hasRate   = function_exists('col_exists') ? col_exists($conn, 'workers', 'hourly_rate') : true;
$hasBio    = function_exists('col_exists') ? col_exists($conn, 'workers', 'bio') : true;
$hasStatus = function_exists('col_exists') ? col_exists($conn, 'workers', 'status') : true;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'save') {
    $full   = trim((string)($_POST['full_name'] ?? ''));
    $phone  = trim((string)($_POST['phone'] ?? ''));
    $skill  = trim((string)($_POST['skill_category'] ?? ''));
    $rateIn = (string)($_POST['hourly_rate'] ?? '');
    $bio    = trim((string)($_POST['bio'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'active'));

    if ($full === '') {
      header('Location: ' . $baseUrl . '/admin/edit-worker.php?id=' . $worker_id . '&err=' . urlencode('Full name is required'));
      exit;
    }

    if ($hasStatus && !in_array($status, ['active', 'inactive'], true)) {
      $status = 'active';
    }

    $sets  = ['full_name=?', 'phone=?'];
    $types = 'ss';
    $vals  = [$full, $phone];

    if ($hasSkill) {
      $sets[] = 'skill_category=?';
      $types .= 's';
      $vals[] = $skill;
    }

    if ($hasRate) {
      if ($rateIn === '') {
        $sets[] = 'hourly_rate=NULL';
      } else {
        if (!is_numeric($rateIn) || (float)$rateIn < 0) {
          header('Location: ' . $baseUrl . '/admin/edit-worker.php?id=' . $worker_id . '&err=' . urlencode('Invalid hourly rate'));
          exit;
        }
        $sets[] = 'hourly_rate=?';
        $types .= 'd';
        $vals[] = (float)$rateIn;
      }
    }

    if ($hasBio) {
      $sets[] = 'bio=?';
      $types .= 's';
      $vals[] = $bio;
    }

    if ($hasStatus) {
      $sets[] = 'status=?';
      $types .= 's';
      $vals[] = $status;
    }

    $sql = 'UPDATE workers SET ' . implode(', ', $sets) . ' WHERE worker_id=?';
    $types .= 'i';
    $vals[]  = $worker_id;

    $st = $conn->prepare($sql);
    if (!$st) {
      header('Location: ' . $baseUrl . '/admin/edit-worker.php?id=' . $worker_id . '&err=' . urlencode('Prepare failed: ' . $conn->error));
      exit;
    }
    $st->bind_param($types, ...$vals);
    $st->execute();
    $st->close();

    header('Location: ' . $baseUrl . '/admin/manage-workers.php?msg=' . urlencode('Worker updated'));
    exit;
  }

  if ($action === 'reset_password') {
    $new = (string)($_POST['new_password'] ?? '');
    if (strlen($new) < 6) {
      header('Location: ' . $baseUrl . '/admin/edit-worker.php?id=' . $worker_id . '&err=' . urlencode('Password must be at least 6 characters'));
      exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $st = $conn->prepare('UPDATE workers SET password=? WHERE worker_id=?');
    if (!$st) {
      header('Location: ' . $baseUrl . '/admin/edit-worker.php?id=' . $worker_id . '&err=' . urlencode('Prepare failed: ' . $conn->error));
      exit;
    }
    $st->bind_param('si', $hash, $worker_id);
    $st->execute();
    $st->close();

    header('Location: ' . $baseUrl . '/admin/manage-workers.php?msg=' . urlencode('Password updated'));
    exit;
  }
}

// Load worker
$sel = ['worker_id', 'full_name', 'email', 'phone'];
if ($hasSkill)  $sel[] = 'skill_category';
if ($hasRate)   $sel[] = 'hourly_rate';
if ($hasBio)    $sel[] = 'bio';
if ($hasStatus) $sel[] = 'status';
$selSql = implode(', ', $sel);

$st = $conn->prepare("SELECT $selSql FROM workers WHERE worker_id=? LIMIT 1");
if (!$st) { http_response_code(500); exit('Prepare failed: ' . h($conn->error)); }
$st->bind_param('i', $worker_id);
$st->execute();
$w = $st->get_result()->fetch_assoc();
$st->close();

if (!$w) {
  header('Location: ' . $baseUrl . '/admin/manage-workers.php?err=' . urlencode('Worker not found'));
  exit;
}

$flash_err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Edit Worker</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Edit Worker</h1>
      <a class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50" href="<?= $baseUrl ?>/admin/manage-workers.php">Back</a>
    </div>

    <?php if ($flash_err !== ''): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
        <?= h($flash_err) ?>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow p-6">
      <form method="post" action="<?= $baseUrl ?>/admin/edit-worker.php?id=<?= (int)$w['worker_id'] ?>" class="space-y-4">
        <input type="hidden" name="action" value="save">

        <div>
          <label class="block text-sm font-medium">Full name</label>
          <input name="full_name" value="<?= h($w['full_name']) ?>" class="mt-1 w-full border rounded-lg px-3 py-2" required>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">Email</label>
            <input value="<?= h($w['email']) ?>" class="mt-1 w-full border rounded-lg px-3 py-2 bg-gray-100 text-gray-600" disabled>
          </div>
          <div>
            <label class="block text-sm font-medium">Phone</label>
            <input name="phone" value="<?= h($w['phone'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2">
          </div>
        </div>

        <?php if ($hasSkill): ?>
          <div>
            <label class="block text-sm font-medium">Skill category</label>
            <input name="skill_category" value="<?= h($w['skill_category'] ?? '') ?>" class="mt-1 w-full border rounded-lg px-3 py-2">
          </div>
        <?php endif; ?>

        <?php if ($hasRate): ?>
          <div>
            <label class="block text-sm font-medium">Hourly rate (USD)</label>
            <input type="number" min="0" step="0.01" name="hourly_rate" value="<?= isset($w['hourly_rate']) ? h((string)$w['hourly_rate']) : '' ?>" class="mt-1 w-full border rounded-lg px-3 py-2">
          </div>
        <?php endif; ?>

        <?php if ($hasStatus): ?>
          <div>
            <label class="block text-sm font-medium">Status</label>
            <select name="status" class="mt-1 w-full border rounded-lg px-3 py-2">
              <option value="active" <?= (($w['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= (($w['status'] ?? 'active') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($hasBio): ?>
          <div>
            <label class="block text-sm font-medium">Bio</label>
            <textarea name="bio" rows="5" class="mt-1 w-full border rounded-lg px-3 py-2"><?= h($w['bio'] ?? '') ?></textarea>
          </div>
        <?php endif; ?>

        <div class="flex gap-2">
          <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Save changes</button>
          <a class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50" href="<?= $baseUrl ?>/admin/manage-workers.php">Cancel</a>
        </div>
      </form>
    </div>

    <div class="bg-white rounded-xl shadow p-6 mt-8">
      <h2 class="text-lg font-semibold">Reset Password</h2>
      <form method="post" action="<?= $baseUrl ?>/admin/edit-worker.php?id=<?= (int)$w['worker_id'] ?>" class="mt-3 flex flex-col md:flex-row gap-2">
        <input type="hidden" name="action" value="reset_password">
        <input type="password" name="new_password" minlength="6" placeholder="New password" class="flex-1 border rounded-lg px-3 py-2" required>
        <button class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black">Update</button>
      </form>
      <p class="mt-2 text-xs text-gray-500">Minimum 6 characters.</p>
    </div>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
