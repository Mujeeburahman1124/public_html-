<?php
session_start();
require __DIR__ . '/config2.php';

$token = trim($_GET['token'] ?? '');
$valid = false;
$userId = null;

if ($token !== '') {
    $sql = "SELECT id, reset_expires FROM super_admins WHERE reset_token = ? LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $expires = DateTime::createFromFormat('Y-m-d H:i:s', $row['reset_expires']);
        if ($expires && new DateTime() <= $expires) {
            $valid = true;
            $userId = (int)$row['id'];
        }
    }
}

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($token === '' || $pass1 === '' || $pass2 === '') {
        $error = 'All fields are required.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Verify token again to be safe
        $sql = "SELECT id FROM super_admins WHERE reset_token = ? AND reset_expires >= NOW() LIMIT 1";
        $stmt = db()->prepare($sql);
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $uid = (int)$row['id'];
            $hash = password_hash($pass1, PASSWORD_DEFAULT);

            $up = db()->prepare("UPDATE super_admins SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $up->bind_param('si', $hash, $uid);
            $up->execute();

            $done = true;
        } else {
            $error = 'Invalid or expired token.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password — Super Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
  <div class="w-full max-w-md bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/20 p-8">
    <h2 class="text-2xl font-bold text-white text-center">Reset Password</h2>
    <?php if ($done): ?>
      <div class="mt-4 bg-green-500/20 border border-green-400/40 text-green-100 rounded-lg p-3 text-sm">
        Password updated. You can now <a href="super_admin_login.php" class="underline">log in</a>.
      </div>
    <?php elseif (!$valid && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
      <div class="mt-4 bg-red-500/20 border border-red-400/40 text-red-100 rounded-lg p-3 text-sm">
        Invalid or expired reset link.
      </div>
    <?php endif; ?>

    <?php if (!$done && ($valid || $_SERVER['REQUEST_METHOD'] === 'POST')): ?>
      <?php if (!empty($error)): ?>
        <div class="mt-4 bg-red-500/20 border border-red-400/40 text-red-100 rounded-lg p-3 text-sm">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="mt-6 space-y-4">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div>
          <label class="block text-slate-200 font-semibold mb-1">New Password</label>
          <input name="password" type="password" required minlength="8"
            class="w-full px-4 py-2.5 rounded-lg border border-white/20 bg-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-slate-200 font-semibold mb-1">Confirm Password</label>
          <input name="password2" type="password" required minlength="8"
            class="w-full px-4 py-2.5 rounded-lg border border-white/20 bg-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <button type="submit"
          class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-600 to-blue-800 text-white font-semibold tracking-wide shadow-lg hover:scale-[1.02] active:scale-[0.98] transition">
          Update Password
        </button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
