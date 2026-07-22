<?php
session_start();
require __DIR__ . '/config2.php';
require __DIR__ . '/vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $who = trim($_POST['who'] ?? ''); // email or username
    if ($who !== '') {
        // Try by email first, else by username
        $sql = "SELECT id, email FROM super_admins WHERE email = ? OR username = ? LIMIT 1";
        $stmt = db()->prepare($sql);
        $stmt->bind_param('ss', $who, $who);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $token = random_token(32);
            $expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

            $up = db()->prepare("UPDATE super_admins SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $up->bind_param('ssi', $token, $expires, $row['id']);
            $up->execute();

            // Build reset link
            $resetLink = rtrim(APP_BASE_URL, '/') . '/reset_password.php?token=' . urlencode($token);

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(APP_FROM_EMAIL, APP_FROM_NAME);
                $mail->addAddress($row['email']);

                $mail->isHTML(true);
                $mail->Subject = 'Reset your Super Admin password';
                $mail->Body = '
                  <p>Hello,</p>
                  <p>We received a request to reset your password. Click the link below to set a new password (valid for 30 minutes):</p>
                  <p><a href="'.e($resetLink).'">'.e($resetLink).'</a></p>
                  <p>If you didn’t request this, you can ignore this email.</p>
                ';

                $mail->send();
                $message = 'If the account exists, a reset link has been sent to the email.';
            } catch (Exception $e) {
                // Do not reveal too much
                $message = 'If the account exists, a reset link has been sent to the email.';
            }
        } else {
            // Do not reveal user existence
            $message = 'If the account exists, a reset link has been sent to the email.';
        }
    } else {
        $message = 'Please enter your email or username.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password — Super Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
  <div class="w-full max-w-md bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/20 p-8">
    <h2 class="text-2xl font-bold text-white text-center">Forgot Password</h2>
    <p class="text-slate-300 text-center mt-2">Enter your email or username to receive a reset link.</p>

    <?php if (!empty($message)): ?>
      <div class="mt-4 bg-white/10 border border-white/20 text-slate-100 rounded-lg p-3 text-sm">
        <?= e($message) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="mt-6 space-y-4">
      <div>
        <label class="block text-slate-200 font-semibold mb-1">Email or Username</label>
        <input name="who" type="text" required
          class="w-full px-4 py-2.5 rounded-lg border border-white/20 bg-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          placeholder="you@example.com or superadmin">
      </div>
      <button type="submit"
        class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-600 to-blue-800 text-white font-semibold tracking-wide shadow-lg hover:scale-[1.02] active:scale-[0.98] transition">
        Send Reset Link
      </button>
      <div class="text-center">
        <a href="super_admin_login.php" class="text-sm text-blue-300 hover:underline">Back to Login</a>
      </div>
    </form>
  </div>
</body>
</html>
