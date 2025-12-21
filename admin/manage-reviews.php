<?php
// /admin/manage-reviews.php
session_start();
require_once __DIR__ . '/../Lib/config.php';
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') redirect_to('/login.php');

// actions: delete
$action=$_GET['action']??null; $rid=isset($_GET['id'])?(int)$_GET['id']:0;
if ($action==='delete' && $rid>0){
  try{
    $d=$conn->prepare("DELETE FROM reviews WHERE review_id=?");
    $d->bind_param('i',$rid); $d->execute(); $d->close();
    redirect_to('/admin/manage-reviews.php?msg=Review%20deleted');
  }catch(Throwable $e){ redirect_to('/admin/manage-reviews.php?err='.urlencode($e->getMessage())); }
}

// filters
$search=trim($_GET['search']??'');
$minr=isset($_GET['minr'])?(int)$_GET['minr']:0;
$maxr=isset($_GET['maxr'])?(int)$_GET['maxr']:5;
$page=max(1,(int)($_GET['page']??1)); $per=25; $off=($page-1)*$per;

$where=['1=1','r.rating BETWEEN ? AND ?']; $params=[$minr,$maxr]; $types='ii';
if ($search!==''){
  $where[]='(r.comment LIKE CONCAT("%", ?, "%") OR s.title LIKE CONCAT("%", ?, "%") OR u.full_name LIKE CONCAT("%", ?, "%") OR w.full_name LIKE CONCAT("%", ?, "%"))';
  array_push($params,$search,$search,$search,$search); $types.='ssss';
}
$whereSql=' WHERE '.implode(' AND ',$where);

// total
$sqlC="SELECT COUNT(*) c
       FROM reviews r
       JOIN services s ON s.service_id=r.service_id
       JOIN users    u ON u.user_id=r.user_id
       JOIN workers  w ON w.worker_id=r.worker_id
       $whereSql";
$stc=$conn->prepare($sqlC); $stc->bind_param($types,...$params); $stc->execute();
$total=(int)($stc->get_result()->fetch_assoc()['c']??0); $stc->close();

$pages=max(1,(int)ceil($total/$per)); $page=min($page,$pages); $off=($page-1)*$per;

// list
$sql="SELECT r.review_id, r.rating, r.comment, r.created_at,
             s.service_id, s.title AS service_title,
             u.user_id, u.full_name AS user_name,
             w.worker_id, w.full_name AS worker_name
      FROM reviews r
      JOIN services s ON s.service_id=r.service_id
      JOIN users    u ON u.user_id=r.user_id
      JOIN workers  w ON w.worker_id=r.worker_id
      $whereSql
      ORDER BY r.created_at DESC, r.review_id DESC
      LIMIT ? OFFSET ?";
$st=$conn->prepare($sql); $types2=$types.'ii'; $st->bind_param($types2,...$params,$per,$off);
$st->execute(); $list=$st->get_result(); $st->close();

function qs_r(array $extra=[]){ $base=['search'=>$_GET['search']??'','minr'=>$_GET['minr']??0,'maxr'=>$_GET['maxr']??5,'page'=>$_GET['page']??1]; $q=http_build_query(array_merge($base,$extra)); return url('/admin/manage-reviews.php'.($q?('?'.$q):'')); }

$flash_err=$_GET['err']??null; $flash_ok=$_GET['msg']??null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin — Manage Reviews</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen">
<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="max-w-7xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Manage Reviews</h1>
    <nav class="flex gap-2 text-sm">
      <a class="px-3 py-1 rounded bg-white shadow hover:bg-gray-50" href="<?= url('/admin/manage-users.php') ?>">Users</a>
      <a class="px-3 py-1 rounded bg-white shadow hover:bg-gray-50" href="<?= url('/admin/manage-workers.php') ?>">Workers</a>
      <a class="px-3 py-1 rounded bg-white shadow hover:bg-gray-50" href="<?= url('/admin/manage-services.php') ?>">Services</a>
      <a class="px-3 py-1 rounded bg-white shadow hover:bg-gray-50" href="<?= url('/admin/manage-bookings.php') ?>">Bookings</a>
      <a class="px-3 py-1 rounded bg-white shadow hover:bg-gray-50" href="<?= url('/admin/view-notifications.php') ?>">Notifications</a>
    </nav>
  </div>

  <?php if ($flash_ok): ?><div class="mb-4 p-3 rounded bg-green-100 text-green-800"><?= htmlspecialchars($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($flash_err) ?></div><?php endif; ?>

  <form method="get" action="<?= url('/admin/manage-reviews.php') ?>" class="mb-4 grid md:grid-cols-4 gap-2">
    <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search comment/service/user/worker…" class="rounded px-3 py-2 md:col-span-2">
    <div class="flex gap-2">
      <input type="number" name="minr" min="1" max="5" value="<?= (int)$minr ?>" class="w-20 rounded px-3 py-2" />
      <input type="number" name="maxr" min="1" max="5" value="<?= (int)$maxr ?>" class="w-20 rounded px-3 py-2" />
    </div>
    <button class="px-4 py-2 rounded bg-blue-600 text-white">Filter</button>
  </form>

  <div class="text-sm text-gray-700 mb-3">Results: <span class="font-medium"><?= (int)$total ?></span></div>

  <?php if ($list->num_rows===0): ?>
    <div class="p-6 bg-white rounded-xl shadow text-gray-600">No reviews found.</div>
  <?php else: ?>
    <div class="space-y-4">
      <?php while($r=$list->fetch_assoc()): ?>
        <div class="bg-white rounded-xl shadow p-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="text-sm text-gray-600">
                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800"><?= (int)$r['rating'] ?>/5</span>
                · <span><?= htmlspecialchars($r['created_at']) ?></span>
              </div>
              <div class="mt-1 text-base font-semibold text-blue-700">
                <a class="hover:underline" href="<?= url('/service.php?id='.(int)$r['service_id']) ?>"><?= htmlspecialchars($r['service_title']) ?></a>
              </div>
              <div class="text-sm text-gray-700">
                <span class="mr-2">User: <?= htmlspecialchars($r['user_name']) ?></span>
                · <span>Worker: <?= htmlspecialchars($r['worker_name']) ?></span>
              </div>
              <?php if (!empty($r['comment'])): ?>
                <p class="mt-2 text-gray-800"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
              <?php endif; ?>
            </div>

            <div class="min-w-[160px] flex flex-col gap-2">
              <a class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700 text-center"
                 onclick="return confirm('Delete this review?');"
                 href="<?= url('/admin/manage-reviews.php?action=delete&id='.(int)$r['review_id']) ?>">Delete</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <?php if ($pages>1): ?>
      <div class="mt-6 flex items-center gap-2">
        <?php if ($page>1): ?><a class="px-3 py-1 rounded bg-white shadow" href="<?= qs_r(['page'=>$page-1]) ?>">Prev</a><?php endif; ?>
        <span class="px-3 py-1 rounded bg-blue-600 text-white"><?= $page ?></span>
        <?php if ($page<$pages): ?><a class="px-3 py-1 rounded bg-white shadow" href="<?= qs_r(['page'=>$page+1]) ?>">Next</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
