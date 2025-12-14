<?php
/**
 * ProLink - User Messages (Chat with Workers)
 * Path: /Prolink/user/user-messages.php
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
} else {
    http_response_code(500);
    echo 'config.php not found';
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'DB connection missing';
    exit;
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// --- Auth: user only ---
if (empty($_SESSION['user_id'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$error  = '';
$success = '';

// --- Handle sending a message (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $worker_id = (int)($_POST['worker_id'] ?? 0);
    $body      = trim($_POST['body'] ?? '');

    if ($worker_id <= 0 || $body === '') {
        $error = 'Please select a worker and enter a message.';
    } else {
        // Ensure the user actually has (or had) a booking with this worker
        $hasRelation = false;
        if ($st = $conn->prepare("SELECT 1 FROM bookings WHERE user_id = ? AND worker_id = ? LIMIT 1")) {
            $st->bind_param('ii', $user_id, $worker_id);
            $st->execute();
            $hasRelation = (bool)$st->get_result()->fetch_row();
            $st->close();
        }

        if (!$hasRelation) {
            $error = 'You can only message workers you have a booking with.';
        } else {
            // Insert message (no booking_id link for now)
            if ($ins = $conn->prepare("
                INSERT INTO messages (booking_id, sender_role, sender_id, recipient_role, recipient_id, body, created_at)
                VALUES (NULL, 'user', ?, 'worker', ?, ?, NOW())
            ")) {
                $ins->bind_param('iis', $user_id, $worker_id, $body);
                if ($ins->execute()) {
                    $success = 'Message sent.';

                    // Optional: notify worker
                    if ($n = $conn->prepare("
                        INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at)
                        VALUES ('worker', ?, ?, ?, 0, NOW())
                    ")) {
                        $title = 'New message from a customer';
                        $msg   = 'You received a new message from a customer on ProLink.';
                        $n->bind_param('iss', $worker_id, $title, $msg);
                        $n->execute();
                        $n->close();
                    }

                    // Redirect to avoid resubmit
                    header('Location: ' . $baseUrl . '/user/user-messages.php?worker_id=' . $worker_id);
                    exit;
                } else {
                    $error = 'Could not send message. Please try again.';
                }
                $ins->close();
            } else {
                $error = 'Could not prepare message.';
            }
        }
    }
}

// --- Fetch list of workers the user has bookings with ---
$contacts = [];
if ($st = $conn->prepare("
    SELECT DISTINCT w.worker_id, w.full_name
    FROM bookings b
    JOIN workers w ON b.worker_id = w.worker_id
    WHERE b.user_id = ?
    ORDER BY w.full_name ASC
")) {
    $st->bind_param('i', $user_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $contacts[] = $row;
    }
    $st->close();
}

$activeWorkerId = (int)($_GET['worker_id'] ?? 0);
if ($activeWorkerId <= 0 && !empty($contacts)) {
    $activeWorkerId = (int)$contacts[0]['worker_id'];
}

// Ensure active worker is in contacts list
if ($activeWorkerId > 0) {
    $found = false;
    foreach ($contacts as $c) {
        if ((int)$c['worker_id'] === $activeWorkerId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $activeWorkerId = 0;
    }
}

// --- Fetch messages for the active worker ---
$messages = [];
$activeWorkerName = '';

if ($activeWorkerId > 0) {
    foreach ($contacts as $c) {
        if ((int)$c['worker_id'] === $activeWorkerId) {
            $activeWorkerName = $c['full_name'];
            break;
        }
    }

    if ($st = $conn->prepare("
        SELECT message_id, sender_role, sender_id, recipient_role, recipient_id, body, created_at
        FROM messages
        WHERE (sender_role = 'user' AND sender_id = ? AND recipient_role = 'worker' AND recipient_id = ?)
           OR (sender_role = 'worker' AND sender_id = ? AND recipient_role = 'user' AND recipient_id = ?)
        ORDER BY created_at ASC
    ")) {
        $st->bind_param('iiii', $user_id, $activeWorkerId, $activeWorkerId, $user_id);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $messages[] = $row;
        }
        $st->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Messages - ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Messages</h1>
      <a href="<?= h($baseUrl . '/user/my-bookings.php') ?>" class="text-sm text-purple-700 hover:underline">Back to my bookings</a>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
        <?= h($success) ?>
      </div>
    <?php endif; ?>

    <?php if (empty($contacts)): ?>
      <div class="bg-white rounded-xl border px-6 py-8 text-center">
        <p class="text-gray-600 mb-2">You don't have any conversations yet.</p>
        <p class="text-gray-500 text-sm mb-4">Book a service first, then you can message the worker here.</p>
        <a href="<?= h($baseUrl . '/browse.php') ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-purple-600 text-white text-sm hover:bg-purple-700">
          Browse services
        </a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Contacts list -->
        <aside class="bg-white rounded-xl border p-4 md:col-span-1">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Workers</h2>
          <div class="space-y-1 max-h-80 overflow-y-auto">
            <?php foreach ($contacts as $c): ?>
              <?php $isActive = ((int)$c['worker_id'] === $activeWorkerId); ?>
              <a href="<?= h($baseUrl . '/user/user-messages.php?worker_id=' . (int)$c['worker_id']) ?>"
                 class="flex items-center justify-between px-3 py-2 rounded-lg text-sm
                        <?= $isActive ? 'bg-purple-50 text-purple-700 font-semibold border border-purple-200' : 'hover:bg-gray-50 text-gray-800' ?>">
                <span><?= h($c['full_name']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </aside>

        <!-- Conversation -->
        <section class="bg-white rounded-xl border p-4 md:col-span-2 flex flex-col h-[480px]">
          <?php if ($activeWorkerId <= 0): ?>
            <div class="m-auto text-center text-gray-500 text-sm">
              <p>Select a worker from the left to view your messages.</p>
            </div>
          <?php else: ?>
            <div class="flex items-center justify-between border-b pb-3 mb-3">
              <div>
                <div class="text-sm text-gray-500">Chatting with</div>
                <div class="font-semibold text-gray-800"><?= h($activeWorkerName) ?></div>
              </div>
            </div>

            <div class="flex-1 overflow-y-auto pr-1 space-y-3 mb-3">
              <?php if (empty($messages)): ?>
                <p class="text-sm text-gray-500">No messages yet. Say hello 👋</p>
              <?php else: ?>
                <?php foreach ($messages as $m): ?>
                  <?php $isMine = ($m['sender_role'] === 'user'); ?>
                  <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[75%] rounded-2xl px-3 py-2 text-sm
                                <?= $isMine ? 'bg-purple-600 text-white rounded-br-none' : 'bg-gray-100 text-gray-900 rounded-bl-none' ?>">
                      <p><?= nl2br(h($m['body'])) ?></p>
                      <div class="mt-1 text-[11px] opacity-80 <?= $isMine ? 'text-purple-100' : 'text-gray-500' ?>">
                        <?= h(date('Y-m-d H:i', strtotime($m['created_at']))) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Send message form -->
            <form method="post" class="mt-auto pt-3 border-t flex items-center gap-2">
              <input type="hidden" name="worker_id" value="<?= (int)$activeWorkerId ?>">
              <input
                type="text"
                name="body"
                class="flex-1 border rounded-full px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                placeholder="Type your message..."
                autocomplete="off"
                required
              >
              <button
                type="submit"
                class="inline-flex items-center justify-center px-4 py-2 rounded-full bg-purple-600 text-white text-sm hover:bg-purple-700">
                Send
              </button>
            </form>
          <?php endif; ?>
        </section>
      </div>
    <?php endif; ?>
  </div>

  <?php include $root . '/partials/footer.php'; ?>
</body>
</html>
