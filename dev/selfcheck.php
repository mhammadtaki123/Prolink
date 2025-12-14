
<!-- For infinity free hosting -->

<?php
// dev/selfcheck.php
session_start();
require_once __DIR__ . '/../Lib/config.php';

function ok($b){ return $b ? '✅' : '❌'; }
$checks = [];
$checks['APP_ROOT exists'] = is_dir(APP_ROOT);
$checks['BASE_URL set']    = (BASE_URL !== '');

$must = [
  '/index.php','/login.php','/register.php','/logout.php',
  '/partials/navbar.php',
  '/dashboard/user-dashboard.php','/dashboard/worker-dashboard.php','/dashboard/admin-dashboard.php',
  '/user/browse-services.php','/user/book-service.php',
  '/worker/add-service.php','/worker/service-images.php',
  '/admin/manage-users.php','/admin/manage-workers.php','/admin/manage-services.php','/admin/manage-bookings.php'
];
$missing = [];
foreach ($must as $f) if(!file_exists(APP_ROOT.$f)) $missing[] = $f;

// Schema spot-checks (based on your dump)
$schemaErr = null;
try {
  $tables = [
    'users'   => ['user_id','full_name','email','password'],
    'workers' => ['worker_id','full_name','email','password'],
    'admins'  => ['admin_id','username','email','password'],
    'services'=> ['service_id','worker_id','title','status'],
    'bookings'=> ['booking_id','user_id','service_id','status'],
    'service_images' => ['image_id','service_id','file_path']
  ];
  foreach ($tables as $t=>$cols) {
    $res = $conn->query("SHOW COLUMNS FROM `$t`"); $have = array_column($res->fetch_all(MYSQLI_ASSOC),'Field');
    foreach ($cols as $c) if(!in_array($c,$have,true)) throw new Exception("Table $t missing column $c");
  }
} catch (Throwable $e) { $schemaErr = $e->getMessage(); }

$uploadsOk = is_dir(APP_ROOT.'/uploads') || @mkdir(APP_ROOT.'/uploads',0775,true);
?><!doctype html><meta charset="utf-8"><title>ProLink Self-Check</title>
<style>body{font:14px/1.5 system-ui,Segoe UI,Roboto,Arial;margin:2rem;max-width:900px}</style>
<h1>ProLink Self-Check</h1>
<ul>
  <li>APP_ROOT: <?= htmlspecialchars(APP_ROOT) ?> <?= ok($checks['APP_ROOT exists']) ?></li>
  <li>BASE_URL: <?= htmlspecialchars(BASE_URL) ?> <?= ok($checks['BASE_URL set']) ?></li>
</ul>
<h3>Critical files</h3>
<?= empty($missing) ? '<p>✅ All present</p>' : '<p>❌ Missing:</p><ul><li>'.implode('</li><li>',$missing).'</li></ul>' ?>
<h3>Database</h3>
<p><?= $schemaErr ? '❌ '.$schemaErr : '✅ Required tables/columns found' ?></p>
<h3>Uploads</h3>
<p><?= ok($uploadsOk) ?> uploads/ <?= $uploadsOk ? 'OK' : 'cannot create (permissions)' ?></p>
