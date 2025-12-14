<?php
// /admin/edit-user.php
session_start();
require_once __DIR__ . '/../Lib/config.php';
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') redirect_to('/login.php');

$user_id = (int)($_GET['id'] ?? 0);
if ($user_id <= 0) redirect_to('/admin/manage-users.php?err=Missing%20user%20id');

// helpers
function col_exists(mysqli $conn, string $table, string $col): bool {
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st=$conn->prepare($sql); $st->bind_param('ss',$table,$col); $st->execute();
  $ok=(bool)$st->get_result()->fetch_row(); $st->close(); return $ok;
}
$ADDR_COL = col_exists($conn,'users','address') ? 'address' : (col_exists($conn,'users','location') ? 'location' : null);

// handle POSTs
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if ($action==='save'){
    $full=trim($_POST['full_name']??'');
    $phone=trim($_POST['phone']??'');
    $address=trim($_POST['address']??'');
    if ($full==='') redirect_to('/admin/edit-user.php?id='.$user_id.'&err=Full%20name%20required');

    try{
      if ($ADDR_COL){
        $q=$conn->prepare("UPDATE users SET full_name=?, phone=?, {$ADDR_COL}=? WHERE user_id=?");
        $q->bind_param('sssi',$full,$phone,$address,$user_id);
      } else {
        $q=$conn->prepare("UPDATE users SET full_name=?, phone=? WHERE user_id=?");
        $q->bind_param('ssi',$full,$phone,$user_id);
      }
      $q->execute(); $q->close();
      redirect_to('/admin/manage-users.php?msg=User%20updated');
    }catch(Throwable $e){
      redirect_to('/admin/edit-user.php?id='.$user_id.'&err='.urlencode($e->getMessage()));
    }
  } elseif ($action==='reset_password'){
    $new=$_POST['new_password']??'';
    if (strlen($new)<6) redirect_to('/admin/edit-user.php?id='.$user_id.'&err=Password%20too%20short');
    try{
      $hash=password_hash($new,PASSWORD_DEFAULT);
      $u=$conn->prepare("UPDATE users SET password=? WHERE user_id=?");
      $u->bind_param('si',$hash,$user_id); $u->execute(); $u->close();
      redirect_to('/admin/manage-users.php?msg=Password%20updated');
    }catch(Throwable $e){
      redirect_to('/admin/edit-user.php?id='.$user_id.'&err='.urlencode($e->getMessage()));
    }
  }
}

// load user
$cols=['user_id','full_name','email','phone']; if ($ADDR_COL) $cols[]="$ADDR_COL AS address"; $sel=implode(', ',$cols);
$st=$conn->prepare("SELECT $sel FROM users WHERE user_id=? LIMIT 1"); $st->bind_param('i',$user_id);
$st->execute(); $user=$st->get_result()->fetch_assoc(); $st->close();
if(!$user) redirect_to('/admin/manage-users.php?err=User%20not%20found');

$flash_err=$_GET['err']??null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Admin — Edit User</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-purple-50 min-h-screen">
<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-2xl font-bold mb-4">Edit User</h1>
  <?php if($flash_err): ?><div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($flash_err) ?></div><?php endif; ?>

  <div class="bg-white rounded-2xl shadow p-6">
    <form method="post" action="<?= url('/admin/edit-user.php?id='.(int)$user['user_id']) ?>" class="space-y-4">
      <input type="hidden" name="action" value="save">
      <div>
        <label class="block text-sm font-medium">Full name</label>
        <input name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" class="mt-1 w-full border rounded px-3 py-2" required>
      </div>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium">Email</label>
          <input value="<?= htmlspecialchars($user['email']) ?>" class="mt-1 w-full border rounded px-3 py-2 bg-gray-100 text-gray-600" disabled>
        </div>
        <div>
          <label class="block text-sm font-medium">Phone</label>
          <input name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium"><?= $ADDR_COL ? 'Address' : 'Address (no column in DB)' ?></label>
        <input name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2" <?= $ADDR_COL?'':'disabled' ?>>
      </div>
      <div class="flex gap-2">
        <button class="px-4 py-2 rounded bg-purple-600 text-white hover:bg-purple-700">Save</button>
        <a class="px-4 py-2 rounded bg-white border hover:bg-gray-50" href="<?= url('/admin/manage-users.php') ?>">Cancel</a>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-2xl shadow p-6 mt-8">
    <h2 class="text-lg font-semibold">Reset Password</h2>
    <form method="post" action="<?= url('/admin/edit-user.php?id='.(int)$user['user_id']) ?>" class="mt-3 flex gap-2">
      <input type="hidden" name="action" value="reset_password">
      <input type="password" name="new_password" minlength="6" placeholder="New password" class="flex-1 border rounded px-3 py-2" required>
      <button class="px-4 py-2 rounded bg-gray-800 text-white hover:bg-gray-900">Update</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
