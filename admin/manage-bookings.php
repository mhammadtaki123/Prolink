<?php

session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';
if (empty($_SESSION['admin_id'])) { header('Location: ' . $baseUrl . '/admin/login.php'); exit; }
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo 'DB connection missing.'; exit; }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function format_when($dt) {
  if (!$dt || $dt === '0000-00-00 00:00:00') return 'Not scheduled';
  $ts = strtotime($dt);
  if ($ts === false) return 'Not scheduled';
  return date('Y-m-d H:i', $ts);
}


function push_notification($conn, $role, $recipient_id, $title, $message) {
  $st = $conn->prepare('
    INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at)
    VALUES (?, ?, ?, ?, 0, NOW())
  ');
  if (!$st) {
    throw new Exception('Prepare failed (notification): ' . $conn->error);
  }

  $st->bind_param('siss', $role, $recipient_id, $title, $message);

  if (!$st->execute()) {
    $e = $st->error;
    $st->close();
    throw new Exception('Notification insert failed: ' . $e);
  }

  $st->close();
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$ok = ''; $err = '';

/* ---------- Actions: update status / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($csrf, $_POST['csrf_token'])) {
  $act = $_POST['action'] ?? '';
  $bid = (int)($_POST['booking_id'] ?? 0);

  if ($bid <= 0) { $err = 'Invalid booking id.'; }
  else {
    $st = $conn->prepare("SELECT b.booking_id, b.status, b.user_id, b.worker_id, b.service_id, s.title AS service_title
              FROM bookings b
              LEFT JOIN services s ON s.service_id = b.service_id
              WHERE b.booking_id = ? LIMIT 1");
    if (!$st) { $err = 'Prepare failed: ' . $conn->error; }
    else {
      $st->bind_param('i', $bid);
      $st->execute();
      $bk = $st->get_result()->fetch_assoc();
      $st->close();

      if (!$bk) { $err = 'Booking not found.'; }
      else {
        $cur = strtolower($bk['status']);
        if ($act === 'update') {
          $to = strtolower(trim($_POST['to'] ?? ''));
          $allowed = ['pending','accepted','completed','cancelled'];
          if (!in_array($to, $allowed, true)) { $err = 'Invalid status.'; }
          else {
            // Transition rules (admin)
            // NOTE: Admin is allowed to restore a cancelled booking back to "pending".
            $okTransitions = [
              'pending'  => ['accepted','cancelled'],
              'accepted' => ['completed','cancelled'],
              'completed'=> [],
              'cancelled'=> ['pending']
            ];
            if (!in_array($to, $okTransitions[$cur] ?? [], true)) {
              $err = 'Invalid transition from ' . $cur . ' to ' . $to . '.';
            } else {
              $conn->begin_transaction();
              try {
                $u = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
                if (!$u) { throw new Exception('Prepare failed: ' . $conn->error); }

                $u->bind_param('si', $to, $bid);
                if (!$u->execute()) { throw new Exception('Update failed: ' . $u->error); }
                $u->close();

                $service_title = !empty($bk['service_title']) ? $bk['service_title'] : 'service';
                $t = 'Booking ' . ucfirst($to) . ' (by Admin)';
                $m = 'Status update for "' . $service_title . '": ' . ucfirst($to) . '.';

                push_notification($conn, 'user', (int)$bk['user_id'], $t, $m);
                push_notification($conn, 'worker', (int)$bk['worker_id'], $t, $m);

                $conn->commit();
                $ok = "Booking #$bid updated to " . ucfirst($to) . '.';
              } catch (Exception $e) {
                $conn->rollback();
                $err = $e->getMessage();
              }
}
          }
        }

        if ($act === 'delete') {
          if (!in_array($cur, ['completed','cancelled'], true)) {
            $err = 'Only completed or cancelled bookings can be deleted.';
          } else {
            $conn->begin_transaction();
            try {
              $d = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
              if (!$d) { throw new Exception('Prepare failed: ' . $conn->error); }

              $d->bind_param('i', $bid);
              if (!$d->execute()) { throw new Exception('Delete failed: ' . $d->error); }
              $d->close();

              $service_title = !empty($bk['service_title']) ? $bk['service_title'] : 'service';
              $t = 'Booking Deleted by Admin';
              $m = 'Your booking for "' . $service_title . '" was deleted by admin.';

              push_notification($conn, 'user', (int)$bk['user_id'], $t, $m);
              push_notification($conn, 'worker', (int)$bk['worker_id'], $t, $m);

              $conn->commit();
              $ok = "Booking #$bid deleted.";
            } catch (Exception $e) {
              $conn->rollback();
              $err = $e->getMessage();
            }
}
        }
      }
    }
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $err = 'Invalid request (CSRF).';
}

/* ---------- Filters ---------- */
$q         = isset($_GET['q']) ? trim($_GET['q']) : '';
$status    = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where = [];
$params = [];
$types  = '';

if ($q !== '') {
  $where[] = "(s.title LIKE ? OR s.location LIKE ? OR u.full_name LIKE ? OR w.full_name LIKE ?)";
  $like = '%'.$q.'%';
  array_push($params, $like, $like, $like, $like);
  $types .= 'ssss';
}
if ($status !== '' && in_array($status, ['pending','accepted','completed','cancelled'], true)) {
  $where[] = "b.status = ?";
  $params[] = $status;
  $types .= 's';
}
if ($date_from !== '') {
  $where[] = "b.scheduled_at >= ?";
  $params[] = $date_from . ' 00:00:00';
  $types .= 's';
}
if ($date_to !== '') {
  $where[] = "b.scheduled_at <= ?";
  $params[] = $date_to . ' 23:59:59';
  $types .= 's';
}
$whereSql = count($where) ? ('WHERE '.implode(' AND ', $where)) : '';

/* ---------- Count ---------- */
$sqlCount = "SELECT COUNT(*) AS total
             FROM bookings b
             JOIN services s ON s.service_id = b.service_id
             JOIN users u ON u.user_id = b.user_id
             JOIN workers w ON w.worker_id = b.worker_id
             $whereSql";
$stC = $conn->prepare($sqlCount);
if (!$stC) { die('Prepare failed (count): ' . h($conn->error)); }
if ($types !== '') $stC->bind_param($types, ...$params);
$stC->execute();
$total = ($r = $stC->get_result()->fetch_assoc()) ? (int)$r['total'] : 0;
$stC->close();

/* ---------- Data ---------- */
$sql = "SELECT
          b.booking_id, b.status, b.scheduled_at, b.notes, b.booking_date,
          s.title AS service_title, s.location, s.price,
          u.user_id, u.full_name AS user_name,
          w.worker_id, w.full_name AS worker_name
        FROM bookings b
        JOIN services s ON s.service_id = b.service_id
        JOIN users u ON u.user_id = b.user_id
        JOIN workers w ON w.worker_id = b.worker_id
        $whereSql
        ORDER BY b.booking_id DESC
        LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);
if (!$st) { die('Prepare failed (data): ' . h($conn->error)); }
if ($types === '') {
  $st->bind_param('ii', $perPage, $offset);
} else {
  $types2 = $types . 'ii';
  $params2 = array_merge($params, [$perPage, $offset]);
  $st->bind_param($types2, ...$params2);
}
$st->execute();
$res = $st->get_result();
$rows = [];
while ($x = $res->fetch_assoc()) { $rows[] = $x; }
$st->close();

$totalPages = (int)ceil(max(1, $total) / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Bookings • ProLink (Admin)</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Manage Bookings</h1>

    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok):  ?><div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($ok) ?></div><?php endif; ?>

    <!-- Filters -->
    <form method="get" class="bg-white border rounded-xl p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
      <div class="md:col-span-2">
        <label class="block text-sm mb-1">Search</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Service, location, user, worker" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Status</label>
        <select name="status" class="w-full border rounded-lg px-3 py-2">
          <?php
            $opts = ['' => 'All', 'pending'=>'Pending', 'accepted'=>'Accepted', 'completed'=>'Completed', 'cancelled'=>'Cancelled'];
            foreach ($opts as $k=>$v) {
              $sel = ($status === $k) ? 'selected' : '';
              echo "<option value='".h($k)."' $sel>".h($v)."</option>";
            }
          ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">From</label>
        <input type="date" name="date_from" value="<?= h($date_from) ?>" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">To</label>
        <input type="date" name="date_to" value="<?= h($date_to) ?>" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div class="md:col-span-1 flex items-end gap-2">
        <button class="bg-blue-600 text-white rounded-lg px-4 py-2" type="submit">Filter</button>
        <a class="border rounded-lg px-4 py-2" href="<?= h($_SERVER['PHP_SELF']) ?>">Reset</a>
      </div>
    </form>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">No bookings found.</div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($rows as $b): ?>
          <div class="bg-white rounded-xl border p-5">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs px-2 py-1 rounded-full border"><?= h(ucfirst($b['status'])) ?></span>
              <span class="text-xs text-gray-600">#<?= (int)$b['booking_id'] ?></span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <div><strong>Service:</strong> <?= h($b['service_title']) ?> (<?= h($b['location']) ?>)</div>
                <div class="mt-2"><strong>User:</strong> <?= h($b['user_name']) ?> (ID <?= (int)$b['user_id'] ?>)</div>
                <div><strong>Price:</strong> $<?= number_format((float)$b['price'], 2) ?></div>
              </div>
              <div>
                <div><strong>When:</strong> <?= h(format_when($b['scheduled_at'])) ?></div>
                <div class="mt-2"><strong>Worker:</strong> <?= h($b['worker_name']) ?> (ID <?= (int)$b['worker_id'] ?>)</div>
                <div class="mt-2 text-xs text-gray-600"><strong>Booked:</strong> <?= h($b['booking_date']) ?></div>
              </div>
            </div>

            <?php if (!empty($b['notes'])): ?>
              <div class="mt-3 text-sm text-gray-700"><strong>Notes:</strong> <?= nl2br(h($b['notes'])) ?></div>
            <?php endif; ?>

            <div class="mt-4 flex flex-wrap gap-2">
              <?php if ($b['status'] === 'pending'): ?>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="to" value="accepted">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                  <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit">Accept</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="to" value="cancelled">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                  <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit">Cancel</button>
                </form>
              <?php elseif ($b['status'] === 'accepted'): ?>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="to" value="completed">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                  <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit">Complete</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="to" value="cancelled">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                  <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit">Cancel</button>
                </form>
              <?php else: /* completed or cancelled */ ?>
                <?php if ($b['status'] === 'cancelled'): ?>
                  <form method="post" onsubmit="return confirm('Change this booking from Cancelled back to Pending?')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="to" value="pending">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                    <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit">Edit</button>
                  </form>
                <?php endif; ?>

                <form method="post" onsubmit="return confirm('Delete this booking?')">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                  <button class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-600">Page <?= (int)$page ?> of <?= (int)$totalPages ?> (<?= (int)$total ?> total)</div>
        <div class="space-x-2">
          <?php
            $qs = $_GET;
            if ($page > 1) { $qs['page']=$page-1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Prev</a>'; }
            if ($page < $totalPages) { $qs['page']=$page+1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Next</a>'; }
          ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
