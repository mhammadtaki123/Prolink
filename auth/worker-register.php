<?php
declare(strict_types=1);
session_start();

/**
 * Prolink - Worker Registration (restyled like login pages)
 */

// --- Bootstrap DB connection ($conn = new mysqli(...)) ---
$__db_bootstrap_files = [
    __DIR__ . '/../includes/db.php',
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../db.php',
    __DIR__ . '/db.php',
];
foreach ($__db_bootstrap_files as $__db_file) {
    if (is_file($__db_file)) {
        require_once $__db_file;
        break;
    }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_name = getenv('DB_NAME') ?: 'prolink_db';
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_errno) {
        http_response_code(500);
        exit('Database connection failed: ' . htmlspecialchars($conn->connect_error));
    }
}
$conn->set_charset('utf8mb4');

// Optional: try to load main config to get BASE_URL & navbar paths
$root = dirname(__DIR__);
$cfg1 = $root . '/Lib/config.php';
$cfg2 = $root . '/lib/config.php';
if (file_exists($cfg1)) {
    require_once $cfg1;
} elseif (file_exists($cfg2)) {
    require_once $cfg2;
}

// Base URL for links
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/Prolink';

/** Small helpers **/
function is_json_request(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($accept, 'application/json') !== false) || (strtolower($xhr) === 'xmlhttprequest');
}
function send_json($payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function old(string $key, string $default = ''): string {
    return h($_POST[$key] ?? $default);
}

$errors = [];

// --- Handle POST (registration) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name      = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $password_plain = (string)($_POST['password'] ?? '');
    $confirm        = (string)($_POST['confirm'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $skill_category = trim($_POST['skill_category'] ?? '');
    $hourly_rate_in = trim($_POST['hourly_rate'] ?? '');
    $bio            = trim($_POST['bio'] ?? '');

    if ($full_name === '') {
        $errors['full_name'] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email is required.';
    }
    if ($password_plain === '' || strlen($password_plain) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }
    if ($confirm === '' || $confirm !== $password_plain) {
        $errors['confirm'] = 'Passwords do not match.';
    }
    if ($skill_category === '') {
        $errors['skill_category'] = 'Skill category is required.';
    }
    if ($hourly_rate_in === '' || !is_numeric($hourly_rate_in)) {
        $errors['hourly_rate'] = 'Hourly rate must be a number.';
    }

    if (!empty($errors)) {
        if (is_json_request()) {
            send_json(['ok' => false, 'errors' => $errors], 422);
        }
    } else {
        // Make sure email is unique
        $sql = "SELECT 1 FROM workers WHERE email = ? LIMIT 1";
        if (!$stmt = $conn->prepare($sql)) {
            $msg = 'Prepare failed (unique check): ' . $conn->error;
            if (is_json_request()) send_json(['ok'=>false, 'error'=>$msg], 500);
            $errors['fatal'] = $msg;
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors['email'] = 'This email is already registered.';
            }
            $stmt->close();
        }
    }

    if (!empty($errors)) {
        if (is_json_request()) {
            send_json(['ok' => false, 'errors' => $errors], 409);
        }
    } else {
        $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
        $hourly_rate     = (float)$hourly_rate_in;

        $sql = "INSERT INTO workers (full_name, email, password, phone, skill_category, hourly_rate, bio)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $msg = 'Prepare failed (insert): ' . $conn->error;
            if (is_json_request()) send_json(['ok'=>false, 'error'=>$msg], 500);
            $errors['fatal'] = $msg;
        } else {
            if (!$stmt->bind_param(
                "sssssds",
                $full_name,
                $email,
                $hashed_password,
                $phone,
                $skill_category,
                $hourly_rate,
                $bio
            )) {
                $msg = 'bind_param failed: ' . $stmt->error;
                if (is_json_request()) send_json(['ok'=>false, 'error'=>$msg], 500);
                $errors['fatal'] = $msg;
            } else {
                if (!$stmt->execute()) {
                    $msg = 'Execute failed: ' . $stmt->error;
                    if (is_json_request()) send_json(['ok'=>false, 'error'=>$msg], 500);
                    $errors['fatal'] = $msg;
                } else {
                    // Success
                    if (is_json_request()) {
                        send_json(['ok' => true, 'message' => 'Worker registered successfully.']);
                    } else {
                        $_SESSION['flash_success'] = 'Worker registered successfully. You can now log in.';
                        header('Location: ' . $baseUrl . '/auth/worker-login.php');
                        exit;
                    }
                }
            }
            $stmt->close();
        }
    }
    // If we reach here with errors and non-JSON, we fall through to form
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Worker Signup • ProLink</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <?php if (file_exists($root . '/partials/navbar.php')) include $root . '/partials/navbar.php'; ?>

  <div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-4">Worker Signup</h1>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
        <?= h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <strong class="block mb-1">We couldn’t save your registration:</strong>
        <ul class="list-disc list-inside space-y-0.5">
          <?php foreach ($errors as $f => $m): if ($f === 'fatal') continue; ?>
            <li><?= h($m) ?></li>
          <?php endforeach; ?>
          <?php if (!empty($errors['fatal'])): ?>
            <li><em><?= h($errors['fatal']) ?></em></li>
          <?php endif; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="bg-white rounded-xl shadow p-6 space-y-4">
      <input type="hidden" name="role" value="worker">

      <div>
        <label class="block text-sm mb-1">Full Name*</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          name="full_name"
          required
          value="<?= old('full_name') ?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Email*</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          type="email"
          name="email"
          required
          value="<?= old('email') ?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Password*</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          type="password"
          name="password"
          required>
      </div>

      <div>
        <label class="block text-sm mb-1">Confirm Password*</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          type="password"
          name="confirm"
          required>
      </div>

      <div>
        <label class="block text-sm mb-1">Phone</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          name="phone"
          value="<?= old('phone') ?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Address</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          name="address"
          value="<?= old('address') ?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Skill Category*</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          name="skill_category"
          required
          value="<?= old('skill_category') ?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Hourly Rate*</label>
        <input
          class="w-full border rounded-lg px-3 py-2"
          type="number"
          step="0.01"
          name="hourly_rate"
          required
          value="<?= old('hourly_rate') ?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Bio (optional)</label>
        <textarea
          class="w-full border rounded-lg px-3 py-2"
          name="bio"
          rows="3"><?= old('bio') ?></textarea>
      </div>

      <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2 font-medium" type="submit">
        Create Worker Account
      </button>

      <div class="text-sm text-center text-gray-600 mt-2">
        Already have an account?
        <a class="text-blue-700 underline" href="<?= h($baseUrl) ?>/auth/worker-login.php">Worker Login</a>
      </div>
    </form>
  </div>

  <?php if (file_exists($root . '/partials/footer.php')) include $root . '/partials/footer.php'; ?>
</body>
</html>
