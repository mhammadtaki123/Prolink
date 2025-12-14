<?php
/**
 * ProLink – Admin: Contact Messages (DB-first with file fallback)
 * Path: /Prolink/admin/contact-messages.php
 * - If table contact_messages exists, uses DB (search + pagination + status actions)
 * - Else, falls back to /storage/contact/messages.txt (JSONL) with delete/clear
 */
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

// Detect table
$hasTable = false;
$chk = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($chk && $chk->num_rows > 0) $hasTable = true;

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$ok=''; $err='';

if ($hasTable) {
  // DB MODE
  $q      = isset($_GET['q']) ? trim($_GET['q']) : '';
  $status = isset($_GET['status']) ? trim($_GET['status']) : '';
  $page   = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
  $pp     = 15;
  $off    = ($page-1)*$pp;

  if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['csrf_token']) && hash_equals($csrf, $_POST['csrf_token'])) {
    $act = $_POST['action'] ?? '';
    if ($act === 'set_status') {
      $id = (int)($_POST['id'] ?? 0);
      $to = $_POST['to'] ?? 'new';
      $allowed = ['new','read','archived'];
      if ($id>0 && in_array($to,$allowed,true)) {
        $u = $conn->prepare("UPDATE contact_messages SET status=? WHERE id=?");
        if ($u) { $u->bind_param('si',$to,$id); $u->execute(); $u->close(); $ok='Status updated.'; }
      }
    } elseif ($act === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id>0) {
        $d = $conn->prepare("DELETE FROM contact_messages WHERE id=?");
        if ($d) { $d->bind_param('i',$id); $d->execute(); $d->close(); $ok='Message deleted.'; }
      }
    }
  } elseif ($_SERVER['REQUEST_METHOD']==='POST') { $err='Invalid request (CSRF).'; }

  $where=[]; $params=[]; $types='';
  if ($q!=='') { $where[]="(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)"; $like='%'.$q.'%'; $params += [$like,$like,$like,$like]; $params = array_values($params); $types.='ssss'; }
  if ($status!=='' && in_array($status,['new','read','archived'],true)) { $where[]="status=?"; $params[]=$status; $types.='s'; }
  $wSql = count($where)?('WHERE '.implode(' AND ',$where)) : '';

  // Count
  $stC = $conn->prepare("SELECT COUNT(*) AS t FROM contact_messages $wSql");
  if (!$stC) die('Prepare failed (count): '.h($conn->error));
  if ($types!=='') $stC->bind_param($types, ...$params);
  $stC->execute();
  $total = ($r=$stC->get_result()->fetch_assoc()) ? (int)$r['t'] : 0;
  $stC->close();

  // Data
  $st = $conn->prepare("SELECT id, name, email, subject, message, status, created_at FROM contact_messages $wSql ORDER BY id DESC LIMIT ? OFFSET ?");
  if (!$st) die('Prepare failed (data): '.h($conn->error));
  $types2 = $types.'ii'; $params2 = array_merge($params, [$pp,$off]);
  if ($types==='') $st->bind_param('ii', $pp, $off);
  else            $st->bind_param($types2, ...$params2);
  $st->execute();
  $res = $st->get_result();
  $rows=[]; while($x=$res->fetch_assoc()) $rows[]=$x; $st->close();
  $pages = (int)ceil(max(1,$total)/$pp);

  ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Contact Messages • ProLink (Admin)</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head><body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>
  <div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Contact Messages</h1>
    <?php if ($err): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok):  ?><div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3"><?= h($ok) ?></div><?php endif; ?>

    <form method="get" class="bg-white border rounded-xl p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
      <div class="md:col-span-4">
        <label class="block text-sm mb-1">Search</label>
        <input name="q" value="<?= h($q) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Name, email, subject, message">
      </div>
      <div>
        <label class="block text-sm mb-1">Status</label>
        <select name="status" class="w-full border rounded-lg px-3 py-2">
          <?php $opts=[''=>'All','new'=>'New','read'=>'Read','archived'=>'Archived'];
            foreach($opts as $k=>$v){ $sel=($status===$k)?'selected':''; echo "<option value='".h($k)."' $sel>".h($v)."</option>"; } ?>
        </select>
      </div>
      <div class="md:col-span-1 flex items-end"><button class="bg-blue-600 text-white rounded-lg px-4 py-2">Filter</button></div>
    </form>

    <?php if (empty($rows)): ?>
      <div class="bg-white border rounded-xl p-6 text-gray-600">No messages found.</div>
    <?php else: ?>
      <div class="bg-white border rounded-xl overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="text-left px-4 py-2">When</th>
              <th class="text-left px-4 py-2">From</th>
              <th class="text-left px-4 py-2">Subject</th>
              <th class="text-left px-4 py-2">Message</th>
              <th class="text-left px-4 py-2">Status</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $m): ?>
              <tr class="border-t align-top">
                <td class="px-4 py-2 whitespace-nowrap"><?= h($m['created_at']) ?></td>
                <td class="px-4 py-2">
                  <div class="font-medium"><?= h($m['name']) ?></div>
                  <a class="text-blue-700 underline" href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>
                </td>
                <td class="px-4 py-2"><?= h($m['subject']) ?></td>
                <td class="px-4 py-2"><?= nl2br(h($m['message'])) ?></td>
                <td class="px-4 py-2"><?= h(ucfirst($m['status'])) ?></td>
                <td class="px-4 py-2 space-y-2">
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <select name="to" class="border rounded px-2 py-1 text-sm">
                      <?php foreach(['new','read','archived'] as $st){ $sel=($m['status']===$st)?'selected':''; echo "<option $sel>".h($st)."</option>"; } ?>
                    </select>
                    <button class="ml-1 px-2 py-1 border rounded text-sm" type="submit">Update</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Delete this message?')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button class="px-2 py-1 border rounded text-sm" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-600">Page <?= (int)$page ?> of <?= (int)ceil(max(1,$total)/$pp) ?> (<?= (int)$total ?> total)</div>
        <div class="space-x-2">
          <?php $qs=$_GET; if($page>1){$qs['page']=$page-1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Prev</a>';} if($page*$pp<$total){$qs['page']=$page+1; echo '<a class="px-3 py-2 border rounded-lg" href="?'.h(http_build_query($qs)).'">Next</a>'; } ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php include $root . '/partials/footer.php'; ?>
  </body></html>
  <?php
  exit;
}

// FILE FALLBACK MODE (no table)
include $root . '/admin/contact-messages.php';
