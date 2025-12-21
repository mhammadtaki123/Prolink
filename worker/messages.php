<?php
/**
 * ProLink – Worker Messages (Chat with Users)
 * Recommended path: /Prolink/worker/messages.php
 */
session_start();

$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) { require_once $cfg1; }
elseif (file_exists($cfg2)) { require_once $cfg2; }
else { http_response_code(500); echo 'config.php not found'; exit; }

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'DB connection missing';
    exit;
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

// --- Auth: worker only ---
if (empty($_SESSION['worker_id'])) {
    header('Location: ' . $baseUrl . '/auth/worker-login.php');
    exit;
}
$worker_id = (int)$_SESSION['worker_id'];

$error   = '';
$success = '';

// --- Handle sending a message (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $body    = trim($_POST['body'] ?? '');

    if ($user_id <= 0 || $body === '') {
        $error = 'Please select a user and enter a message.';
    } else {
        // Ensure this worker actually has/had a booking with this user
        $hasRelation = false;
        if ($st = $conn->prepare("SELECT 1 FROM bookings WHERE worker_id = ? AND user_id = ? LIMIT 1")) {
            $st->bind_param('ii', $worker_id, $user_id);
            $st->execute();
            $hasRelation = (bool)$st->get_result()->fetch_row();
            $st->close();
        }

        if (!$hasRelation) {
            $error = 'You can only message users you have a booking with.';
        } else {
            if ($ins = $conn->prepare("
                INSERT INTO messages (booking_id, sender_role, sender_id, recipient_role, recipient_id, body, created_at)
                VALUES (NULL, 'worker', ?, 'user', ?, ?, NOW())
            ")) {
                $ins->bind_param('iis', $worker_id, $user_id, $body);
                if ($ins->execute()) {
                    $success = 'Message sent.';

                    // Optional: notify user
                    if ($n = $conn->prepare("
                        INSERT INTO notifications (recipient_role, recipient_id, title, message, is_read, created_at)
                        VALUES ('user', ?, ?, ?, 0, NOW())
                    ")) {
                        $title = 'New message from a worker';
                        $msg   = 'You received a new message from a worker on ProLink.';
                        $n->bind_param('iss', $user_id, $title, $msg);
                        $n->execute();
                        $n->close();
                    }

                    // Redirect to avoid resubmit
                    header('Location: ' . $baseUrl . '/worker/messages.php?user_id=' . $user_id);
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

// --- Fetch list of users this worker has bookings with ---
$contacts = [];
if ($st = $conn->prepare("
    SELECT DISTINCT u.user_id, u.full_name
    FROM bookings b
    JOIN users u ON u.user_id = b.user_id
    WHERE b.worker_id = ?
    ORDER BY u.full_name ASC
")) {
    $st->bind_param('i', $worker_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $contacts[] = $row;
    }
    $st->close();
}

$activeUserId = (int)($_GET['user_id'] ?? 0);
if ($activeUserId <= 0 && !empty($contacts)) {
    $activeUserId = (int)$contacts[0]['user_id'];
}

// Ensure active user is in contacts list
if ($activeUserId > 0) {
    $found = false;
    foreach ($contacts as $c) {
        if ((int)$c['user_id'] === $activeUserId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $activeUserId = 0;
    }
}

// --- Fetch messages for the active user ---
$messages = [];
$activeUserName = '';

if ($activeUserId > 0) {
    foreach ($contacts as $c) {
        if ((int)$c['user_id'] === $activeUserId) {
            $activeUserName = $c['full_name'];
            break;
        }
    }

    if ($st = $conn->prepare("
        SELECT message_id, sender_role, sender_id, recipient_role, recipient_id, body, created_at
        FROM messages
        WHERE (sender_role = 'worker' AND sender_id = ? AND recipient_role = 'user' AND recipient_id = ?)
           OR (sender_role = 'user' AND sender_id = ? AND recipient_role = 'worker' AND recipient_id = ?)
        ORDER BY created_at ASC
    ")) {
        $st->bind_param('iiii', $worker_id, $activeUserId, $activeUserId, $worker_id);
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
  <title>Messages - ProLink Worker</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php include $root . '/partials/navbar.php'; ?>

  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Messages</h1>
      <a href="<?= h(url('/dashboard/worker-dashboard.php')) ?>" class="text-sm text-blue-700 hover:underline">
        Back to dashboard
      </a>
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
        <p class="text-gray-500 text-sm mb-4">
          Once users book your services, you can message them here.
        </p>
        <a href="<?= h(url('/worker/services.php')) ?>"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
          View my services
        </a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Contacts list -->
        <aside class="bg-white rounded-xl border p-4 md:col-span-1">
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Users</h2>
          <div class="space-y-1 max-h-80 overflow-y-auto">
            <?php foreach ($contacts as $c): ?>
              <?php $isActive = ((int)$c['user_id'] === $activeUserId); ?>
              <a href="<?= h(url('/worker/messages.php?user_id=' . (int)$c['user_id'])) ?>"
                 class="flex items-center justify-between px-3 py-2 rounded-lg text-sm
                        <?= $isActive ? 'bg-blue-50 text-blue-700 font-semibold border border-blue-200' : 'hover:bg-gray-50 text-gray-800' ?>">
                <span><?= h($c['full_name']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </aside>

        <!-- Conversation -->
        <section class="bg-white rounded-xl border p-4 md:col-span-2 flex flex-col h-[480px]">
          <?php if ($activeUserId <= 0): ?>
            <div class="m-auto text-center text-gray-500 text-sm">
              <p>Select a user from the left to view your messages.</p>
            </div>
          <?php else: ?>
            <div class="flex items-center justify-between border-b pb-3 mb-3">
              <div>
                <div class="text-sm text-gray-500">Chatting with</div>
                <div class="font-semibold text-gray-800"><?= h($activeUserName) ?></div>
              </div>
            </div>

            <div class="flex-1 overflow-y-auto pr-1 space-y-3 mb-3">
              <?php if (empty($messages)): ?>
                <p class="text-sm text-gray-500">No messages yet. Start the conversation 👋</p>
              <?php else: ?>
                <?php foreach ($messages as $m): ?>
                  <?php $isMine = ($m['sender_role'] === 'worker'); ?>
                  <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[75%] rounded-2xl px-3 py-2 text-sm
                                <?= $isMine ? 'bg-blue-600 text-white rounded-br-none' : 'bg-gray-100 text-gray-900 rounded-bl-none' ?>">
                      <p><?= nl2br(h($m['body'])) ?></p>
                      <div class="mt-1 text-[11px] opacity-80 <?= $isMine ? 'text-blue-100' : 'text-gray-500' ?>">
                        <?= h(date('Y-m-d H:i', strtotime($m['created_at']))) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Send message form -->
            <form method="post" class="mt-auto pt-3 border-t flex items-center gap-2">
              <input type="hidden" name="user_id" value="<?= (int)$activeUserId ?>">
              <input
                type="text"
                name="body"
                class="flex-1 border rounded-full px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Type your message..."
                autocomplete="off"
                required
              >
              <button
                type="submit"
                class="inline-flex items-center justify-center px-4 py-2 rounded-full bg-blue-600 text-white text-sm hover:bg-blue-700">
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
