<?php
// /admin/edit-worker.php
session_start();
require_once __DIR__ . '/../Lib/config.php';
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') redirect_to('/login.php');

$worker_id = (int)($_GET['id'] ?? 0);
if ($worker_id <= 0) redirect_to('/admin/manage-workers.php?err=Missing%20worker%20id');

// helpers
function col_exists(mysqli $conn, string $table, string $col): bool {
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st=$conn->prepare($sql); $st->bind_param('ss',$table,$col); $st->execute();
  $ok=(bool)$st->get_result()->fetch_row(); $st->close(); return $ok;
}
$HAS_SKILL=col_exists($conn,'workers','skill_category');
$HAS_RATE=col_exists($conn,'workers','hourly_rate');
$ADDR_COL= col_exists($conn,'workers','address') ? 'address' : (col_exists($conn,'workers','location') ? 'location' : null);
$HAS_BIO =col_exists($conn,'workers','bio');

// POST
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if ($action==='save'){
    $full=trim($_POST['full_name']??'');
    $phone=trim($_POST['phone']??'');
    $skill=trim($_POST['skill_category']??'');
    $rate =$_POST['hourly_rate']??'';
    $addr =trim($_POST['address']??'');
    $bio  =trim($_POST['bio']??'');

    if ($full==='') redirect_to('/admin/edit-worker.php?id='.$worker_id.'&err=Full%20name%20required');

    $sets=['full_name=?','phone=?']; $types='ss'; $params=[$full,$phone];
    if ($HAS_SKILL){ $sets[]='skill_category=?'; $types.='s'; $params[]=$skill; }
    if ($HAS_RATE){
      if ($rate!=='' && (!is_numeric($rate) || (float)$rate<0)) redirect_to('/admin/edit-worker.php?id='.$worker_id.'&err=Invalid%20hourly%20rate');
      if ($rate==='') { $sets[]='hourly_rate=NULL'; }
      else { $sets[]='hourly_rate=?'; $types.='d'; $params[]=(float)$rate; }
    }
    if ($ADDR_COL){ $sets[]="$ADDR_COL=?"; $types.='s'; $params[]=$addr; }
    if ($HAS_BIO){ $sets[]='bio=?'; $types.='s'; $params[]=$bio; }
    $sql="UPDATE workers SET ".implode(', ',$sets)." WHERE worker_id=?";
    $types.='i'; $params[]=$worker_id;

    try{
      $q=$conn->prepare($sql); $q->bind_param($types,...$params); $q->execute(); $q->close();
      redirect_to('/admin/manage-workers.php?msg=Worker%20updated');
    }catch(Throwable $e){
      redirect_to('/admin/edit-worker.php?id='.$worker_id.'&err='.urlencode($e->getMessage()));
    }
  } elseif ($action==='reset_password'){
    $new=$_POST['new_password']??'';
    if (strlen($new)<6) redirect_to('/admin/edit-worker.php?id='.$worker_id.'&err=Password%20too%20short');
    try{
      $hash=password_hash($new,PASSWORD_DEFAULT);
      $u=$conn->prepare("UPDATE workers SET password=? WHERE worker_id=?");
      $u->bind_param('si',$hash,$worker_id); $u->execute(); $u->close();
      redirect_to('/admin/manage-workers.php?msg=Password%20updated');
    }catch(Throwable $e){
      redirect_to('/admin/edit-worker.php?id='.$worker_id.'&err='.urlencode($e->getMessage()));
    }
  }
}

// load worker
$cols=['worker_id','full_name','email','phone']; if ($HAS_SKILL) $cols[]='skill_category'; if ($HAS_RATE) $cols[]='hourly_rate'; if ($ADDR_COL) $cols[]="$ADDR_COL AS address"; if ($HAS_BIO) $cols[]='bio';
$sel=implode(', ',$cols);
$st=$conn->prepare("SELECT $sel FROM workers WHERE worker_id=? LIMIT 1"); $st->bind_param('i',$worker_id);
$st->execute(); $w=$st->get_result()->fetch_assoc(); $st->close();
if(!$w) redirect_to('/admin/manage-workers.php?err=Worker%20not%20found');

$flash_err=$_GET['err']??null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Admin — Edit Worker</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-purple-50 min-h-screen">
<?php require_once __DIR__ . '/../partials/navbar.php'; ?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-2xl font-bold mb-4">Edit Worker</h1>
  <?php if($flash_err): ?><div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($flash_err) ?></div><?php endif; ?>

  <div class="bg-white rounded-2xl shadow p-6">
    <form method="post" action="<?= url('/admin/edit-worker.php?id='.(int)$w['worker_id']) ?>" class="space-y-4">
      <input type="hidden" name="action" value="save">
      <div>
        <label class="block text-sm font-medium">Full name</label>
        <input name="full_name" value="<?= htmlspecialchars($w['full_name']) ?>" class="mt-1 w-full border rounded px-3 py-2" required>
      </div>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium">Email</label>
          <input value="<?= htmlspecialchars($w['email']) ?>" class="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-600" disabled>
        </div>
        <div>
          <label class="block text-sm font-medium">Phone</label>
          <input name="phone" value="<?= htmlspecialchars($w['phone'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2">
        </div>
      </div>
      <?php if ($HAS_SKILL): ?>
        <div>
          <label class="block text-sm font-medium">Skill category</label>
          <input name="skill_category" value="<?= htmlspecialchars($w['skill_category'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2">
        </div>
      <?php endif; ?>
      <?php if ($HAS_RATE): ?>
        <div>
          <label class="block text-sm font-medium">Hourly rate (USD)</label>
          <input type="number" min="0" step="0.01" name="hourly_rate"
                 value="<?= isset($w['hourly_rate']) ? htmlspecialchars((string)$w['hourly_rate']) : '' ?>"
                 class="mt-1 w-full border rounded px-3 py-2">
        </div>
      <?php endif; ?>
      <?php if ($ADDR_COL): ?>
        <div>
          <label class="block text-sm font-medium">Address</label>
          <input name="address" value="<?= htmlspecialchars($w['address'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2">
        </div>
      <?php endif; ?>
      <?php if ($HAS_BIO): ?>
        <div>
          <label class="block text-sm font-medium">Bio</label>
          <textarea name="bio" rows="5" class="mt-1 w-full border rounded px-3 py-2"><?= htmlspecialchars($w['bio'] ?? '') ?></textarea>
        </div>
      <?php endif; ?>
      <div class="flex gap-2">
        <button class="px-4 py-2 rounded bg-purple-600 text-white hover:bg-purple-700">Save</button>
        <a class="px-4 py-2 rounded bg-white border hover:bg-gray-50" href="<?= url('/admin/manage-workers.php') ?>">Cancel</a>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-2xl shadow p-6 mt-8">
    <h2 class="text-lg font-semibold">Reset Password</h2>
    <form method="post" action="<?= url('/admin/edit-worker.php?id='.(int)$w['worker_id']) ?>" class="mt-3 flex gap-2">
      <input type="hidden" name="action" value="reset_password">
      <input type="password" name="new_password" minlength="6" placeholder="New password" class="flex-1 border rounded px-3 py-2" required>
      <button class="px-4 py-2 rounded bg-gray-800 text-white hover:bg-gray-900">Update</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
