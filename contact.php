<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (!defined('DB_HOST')) define('DB_HOST', $servername);
if (!defined('DB_USER')) define('DB_USER', $username);
if (!defined('DB_PASS')) define('DB_PASS', $password);
if (!defined('DB_NAME')) define('DB_NAME', $dbname);
if (!defined('DB_PORT')) {
    $port_parts = explode(':', $servername);
    define('DB_PORT', isset($port_parts[1]) ? (int)$port_parts[1] : 3306);
}

/************************************************************
 * MSJOBS — Contact (Single File, Production-Ready)
 * - PDO (robust host:port parsing) + Auto-migration
 * - PHPMailer (Composer or local fallback)
 * - CSRF, validation, rate limit
 * - Saves to DB + emails Support + auto-replies to sender
 ************************************************************/
session_start();
require_once __DIR__ . '/settings_helper.php';

/* ================== CONFIG: EDIT FOR YOUR ENV ================== */
// const DB_HOST = '127.0.0.1:3306'; (Refactored to config.php)
// const DB_NAME = 'u903588615_exaple'; (Refactored to config.php)
// const DB_USER = 'u903588615_root'; (Refactored to config.php)
// const DB_PASS = 'Msjobs#1'; (Refactored to config.php)

const SUPPORT_EMAIL = 'support@msjobs.net';
const SUPPORT_NAME  = 'MSJOBS Support';

const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'mshrc936@gmail.com';       // change if needed
const SMTP_PASS = 'nmspuxcjuptondkd';         // Gmail App Password
const SMTP_FROM = 'mshrc936@gmail.com';
const SMTP_FROMNAME = 'MSJOBS Contact';

/* ================== APP SETTINGS ================== */
const RATE_LIMIT_SECONDS = 20;
ini_set('display_errors','0'); // keep off in production
error_reporting(E_ALL);

/* ================== HELPERS ================== */
function h(?string $s): string { return htmlspecialchars((string)$s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_email(string $e): bool { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }
function arr_get(array $a, string $k, $d=''){ return isset($a[$k]) ? $a[$k] : $d; }

/* ================== DB CONNECT (robust) ================== */
function pdo_connect(): PDO {
  $hostRaw = DB_HOST; $host = $hostRaw; $port = 3306;
  if (strpos($hostRaw, ':') !== false) {
    [$maybeHost, $maybePort] = explode(':', $hostRaw, 2);
    if ($maybeHost !== '') $host = $maybeHost;
    if ($maybePort !== '' && ctype_digit($maybePort)) $port = (int)$maybePort;
  }
  $dsn = "mysql:host={$host};port={$port};dbname=".DB_NAME.";charset=utf8mb4";
  return new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
  ]);
}

try {
  $pdo = pdo_connect();
} catch (Throwable $e) {
  $msg  = $e->getMessage();
  $hint = 'Verify DB_HOST, DB_NAME, DB_USER, DB_PASS and that MySQL is running.';

  if (strpos($msg, 'SQLSTATE[HY000] [2002]') !== false) {
    $hint = 'Cannot reach MySQL (2002). Check host/port or MySQL service.';
  } elseif (strpos($msg, 'SQLSTATE[HY000] [1045]') !== false || stripos($msg,'Access denied')!==false) {
    $hint = 'Access denied (1045). Check DB_USER/DB_PASS or host privileges.';
  } elseif (strpos($msg, 'SQLSTATE[HY000] [1049]') !== false || stripos($msg,'Unknown database')!==false) {
    $hint = 'Unknown database (1049). Create DB or fix DB_NAME.';
  }

  http_response_code(500);
  echo "<!doctype html><meta charset='utf-8'><title><?= h(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= h(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <div style='font:14px system-ui;padding:24px;max-width:720px;line-height:1.5'>
    <h2 style='margin:0 0 8px'>Database connection failed</h2>
    <div style='color:#444;margin-bottom:10px'>".htmlspecialchars($msg, ENT_QUOTES)."</div>
    <div style='color:#0a66c2'><strong>Hint:</strong> {$hint}</div>
    <hr style='margin:16px 0;border:none;border-top:1px solid #eee'>
    <div>Current settings:</div>
    <pre style='background:#fafafa;border:1px solid #eee;padding:10px'>
DB_HOST = ".htmlspecialchars(DB_HOST)."
DB_NAME = ".htmlspecialchars(DB_NAME)."
DB_USER = ".htmlspecialchars(DB_USER)."
(Password hidden)
</pre>
  </div>";
  exit;
}

/* ================== AUTO-MIGRATION ================== */
$pdo->exec("
CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (email),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ================== PHPMailer Loader ================== */
function loadPHPMailer(): array {
  $loaded = false; $err = null;
  try {
    if (file_exists(__DIR__.'/vendor/autoload.php')) {
      require_once __DIR__.'/vendor/autoload.php';
      $loaded = true;
    } else {
      $base = __DIR__.'/PHPMailer/src';
      require_once "$base/PHPMailer.php";
      require_once "$base/Exception.php";
      require_once "$base/SMTP.php";
      $loaded = true;
    }
  } catch (Throwable $e) { $err = $e->getMessage(); }
  return [$loaded, $err];
}

/* ================== CSRF ================== */
if (empty($_SESSION['csrf_contact'])) {
  $_SESSION['csrf_contact'] = bin2hex(random_bytes(16));
}

/* ================== POST HANDLER ================== */
$flash = ['ok'=>false,'msg'=>null];
$old   = ['name'=>'','email'=>'','subject'=>'','message'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Rate-limit
  $last = (int)($_SESSION['last_submit_ts'] ?? 0);
  if (time() - $last < RATE_LIMIT_SECONDS) {
    $flash = ['ok'=>false,'msg'=>'Please wait a few seconds before submitting again.'];
  } else {
    // CSRF
    $csrf = arr_get($_POST, 'csrf', '');
    if (!hash_equals($_SESSION['csrf_contact'], $csrf)) {
      $flash = ['ok'=>false,'msg'=>'Security check failed. Please reload and try again.'];
    } else {
      // Validate
      $name    = trim((string)arr_get($_POST, 'name'));
      $email   = trim((string)arr_get($_POST, 'email'));
      $subject = trim((string)arr_get($_POST, 'subject'));
      $message = trim((string)arr_get($_POST, 'message'));
      $old     = compact('name','email','subject','message');

      $errors = [];
      if ($name === '' || mb_strlen($name) < 2)           $errors[] = 'Please enter your full name.';
      if (!is_email($email))                               $errors[] = 'Please enter a valid email.';
      if ($subject !== '' && mb_strlen($subject) > 190)    $errors[] = 'Subject is too long.';
      if ($message === '' || mb_strlen($message) < 10)     $errors[] = 'Message should be at least 10 characters.';

      if ($errors) {
        $flash = ['ok'=>false, 'msg'=>implode(' ', $errors)];
      } else {
        // Save
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name,email,subject,message,ip,user_agent) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
          $name, $email, ($subject ?: null), $message,
          $_SERVER['REMOTE_ADDR'] ?? null,
          substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)
        ]);

        // Email
        [$sent, $err] = sendEmails($name, $email, $subject, $message);
        if (!$sent) {
          $flash = ['ok'=>false, 'msg'=>"Message saved but email could not be sent ($err). We’ll reach out from support."];
        } else {
          $flash = ['ok'=>true, 'msg'=>"Thanks, $name! We received your message and emailed a confirmation to $email."];
          $old   = ['name'=>'','email'=>'','subject'=>'','message'=>''];
          $_SESSION['csrf_contact'] = bin2hex(random_bytes(16)); // rotate token
        }
        $_SESSION['last_submit_ts'] = time();
      }
    }
  }
}

/* ================== EMAIL SENDER ================== */
function sendEmails(string $name, string $email, string $subject, string $message): array {
  [$loaded, $err] = loadPHPMailer();
  if (!$loaded) return [false, 'PHPMailer not found: '.$err];

  if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $Mailer = 'PHPMailer\PHPMailer\PHPMailer';
  } else {
    $Mailer = 'PHPMailer';
  }

  try {
    // To Support
    $mail = new $Mailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = 'tls';
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_FROM, SMTP_FROMNAME);
    $mail->addAddress(SUPPORT_EMAIL, SUPPORT_NAME);
    $mail->addReplyTo($email, $name);

    $subj = ($subject !== '' ? $subject : 'New contact message');
    $mail->Subject = "Contact | $subj";
    $mail->isHTML(true);
    $mail->Body    = "<p><strong>Name:</strong> ".h($name)."</p>"
                   . "<p><strong>Email:</strong> ".h($email)."</p>"
                   . "<p><strong>Subject:</strong> ".h($subj)."</p>"
                   . "<p><strong>Message:</strong><br>".nl2br(h($message))."</p>";
    $mail->AltBody = "Name: $name\nEmail: $email\nSubject: $subj\n\n$message";
    $mail->send();

    // Auto-reply
    $ack = new $Mailer(true);
    $ack->isSMTP();
    $ack->Host       = SMTP_HOST;
    $ack->SMTPAuth   = true;
    $ack->Username   = SMTP_USER;
    $ack->Password   = SMTP_PASS;
    $ack->SMTPSecure = 'tls';
    $ack->Port       = SMTP_PORT;
    $ack->CharSet    = 'UTF-8';

    $ack->setFrom(SMTP_FROM, SMTP_FROMNAME);
    $ack->addAddress($email, $name);
    $ack->Subject = "We received your message – MSJOBS";
    $ack->isHTML(true);
    $ack->Body    = "<p>Hi ".h($name).",</p>
      <p>Thanks for contacting <strong>MSJOBS</strong>. We’ve received your message and our team will get back to you shortly.</p>
      <p><strong>Your message:</strong><br>".nl2br(h($message))."</p>
      <p>Regards,<br>MSJOBS Support</p>";
    $ack->AltBody = "Hi $name,\n\nThanks for contacting MSJOBS. We received your message:\n\n$message\n\nRegards,\nMSJOBS Support";
    $ack->send();

    return [true, null];
  } catch (Throwable $e) {
    return [false, $e->getMessage()];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title><?= h(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= h(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <link rel="icon" type="image/png" href="img/1748025713_MS copy.png">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta name="description" content="Get in touch with MSJOBS — we're here to help jobseekers and employers.">
  <meta name="keywords" content="MSJOBS contact, support, recruitment, jobs, careers">

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Inter:wght@700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Libs (optional) -->
  <link href="lib/animate/animate.min.css" rel="stylesheet">
  <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

  <!-- Bootstrap + your CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root{--brand:#0a66c2;--ink:#0e2a47}
    body{font-family:Heebo,system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial,sans-serif}
    .card{transition:box-shadow .2s, transform .2s}
    .card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(2,32,71,.06)}
    .container-xl{max-width:1200px}
  </style>
</head>

<body class="antialiased text-slate-800 bg-white">

  <!-- Navbar -->
  <nav class="bg-white shadow sticky top-0 z-50">
    <div class="container-xl mx-auto px-4">
      <div class="h-16 flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-2">
          <img src="img/1748025713_MS copy.png" alt="MSJOBS" class="h-9 w-9">
          <span class="text-xl font-extrabold text-[var(--ink)]">MSJOBS</span>
        </a>
        <button class="md:hidden p-2 rounded hover:bg-gray-100" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
          <i class="bi bi-list text-2xl"></i>
        </button>
        <div class="hidden md:flex items-center gap-6 text-sm">
          <a href="index.php" class="hover:text-[var(--brand)]">Home</a>
          <a href="CompanyProfile.php" class="hover:text-[var(--brand)]">About</a>
          <a href="contact.php" class="text-[var(--brand)] font-semibold">Contact</a>
          <a href="login.php" class="ml-2 inline-flex items-center gap-2 bg-[var(--brand)] text-white px-4 py-2 rounded hover:bg-[#0959ab]">
            Login <i class="bi bi-arrow-right-short text-lg"></i>
          </a>
        </div>
      </div>
      <div id="mobile-menu" class="md:hidden hidden pb-3 border-t">
        <a class="block px-2 py-2" href="index.php">Home</a>
        <a class="block px-2 py-2" href="about.php">About</a>
        <a class="block px-2 py-2 text-[var(--brand)] font-semibold" href="contact.php">Contact</a>
        <a class="block px-2 py-2 bg-[var(--brand)] text-white rounded mt-2 text-center" href="login.php">Login</a>
      </div>
    </div>
  </nav>

  <!-- Breadcrumb + Title -->
  <?php require_once __DIR__ . '/header.php'; ?>


  <!-- Flash -->
  <div class="container-xl mx-auto px-4">
    <?php if ($flash['msg'] !== null): ?>
      <div class="mt-4 mb-2 px-4 py-3 rounded <?php echo $flash['ok']?'bg-green-50 text-green-800 border border-green-200':'bg-red-50 text-red-800 border border-red-200'; ?>">
        <?php echo h($flash['msg']); ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Info Cards -->
  <section class="bg-white">
    <div class="container-xl mx-auto px-4 py-8">
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <div class="card border rounded-xl p-5">
          <div class="flex items-center gap-3"><i class="fa-solid fa-location-dot text-[var(--brand)] text-xl"></i><h3 class="font-semibold">Address</h3></div>
          <p class="text-sm text-slate-700 mt-2"><?= site_setting('contact_address', 'Real Group Building, Ajman Industrial Area 2, United Arab Emirates') ?></p>
        </div>
        <div class="card border rounded-xl p-5">
          <div class="flex items-center gap-3"><i class="fa-solid fa-envelope text-[var(--brand)] text-xl"></i><h3 class="font-semibold">Email</h3></div>
          <p class="text-sm text-slate-700 mt-2"><a href="mailto:<?php echo h(SUPPORT_EMAIL); ?>" class="text-[var(--brand)]"><?php echo h(SUPPORT_EMAIL); ?></a></p>
        </div>
        <div class="card border rounded-xl p-5">
          <div class="flex items-center gap-3"><i class="fa-solid fa-clock text-[var(--brand)] text-xl"></i><h3 class="font-semibold">Support Hours</h3></div>
          <p class="text-sm text-slate-700 mt-2">Sun–Sat: 9:00 AM – 7:00 PM (Gulf Time)</p>
        </div>
        <div class="card border rounded-xl p-5">
          <div class="flex items-center gap-3"><i class="fa-brands fa-whatsapp text-[var(--brand)] text-xl"></i><h3 class="font-semibold">WhatsApp</h3></div>
          <p class="text-sm text-slate-700 mt-2"><a href="<?= site_setting('contact_whatsapp', 'https://wa.me/971585974340') ?>" target="_blank" rel="noopener" class="text-[var(--brand)]"><?= site_setting('contact_phone', '+971 58 597 4340') ?></a></p>
        </div>
      </div>
    </div>
  </section>

  <!-- Map + Form -->
  <section class="bg-gray-50">
    <div class="container-xl mx-auto px-4 py-10">
      <div class="grid md:grid-cols-2 gap-6 items-start">
        <!-- Map -->
        <div class="card border rounded-xl overflow-hidden bg-white">
          <div class="p-5 border-b">
            <h3 class="text-lg font-semibold">Find us on the map</h3>
            <p class="text-sm text-slate-600">Al Garhoud Road, Deira, Dubai</p>
          </div>
          <div class="w-full">
            <iframe class="w-100" style="min-height: 420px; border:0; width:100%" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
              src="<?= site_setting('map_embed_url', 'https://www.google.com/maps?q=Ajman%20Industrial%20Area%202%2C%20Ajman%2C%20UAE&output=embed') ?>"></iframe>
          </div>
        </div>

        <!-- Form -->
        <div class="card border rounded-xl p-6 bg-white">
          <h3 class="text-xl font-semibold">Send us a message</h3>
          <p class="text-sm text-slate-600 mt-1">Fill out the form and we’ll get back to you shortly.</p>

          <form class="mt-4" method="post" action="contact.php" novalidate>
            <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf_contact']); ?>">
            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label for="name" class="text-sm text-slate-600">Full name</label>
                <input id="name" name="name" type="text" class="mt-1 w-full px-3 py-2 border rounded focus:outline-none" placeholder="Your name" required value="<?php echo h($old['name']); ?>">
              </div>
              <div>
                <label for="email" class="text-sm text-slate-600">Email</label>
                <input id="email" name="email" type="email" class="mt-1 w-full px-3 py-2 border rounded focus:outline-none" placeholder="you@example.com" required value="<?php echo h($old['email']); ?>">
              </div>
            </div>
            <div class="mt-4">
              <label for="subject" class="text-sm text-slate-600">Subject</label>
              <input id="subject" name="subject" type="text" class="mt-1 w-full px-3 py-2 border rounded focus:outline-none" placeholder="How can we help?" value="<?php echo h($old['subject']); ?>">
            </div>
            <div class="mt-4">
              <label for="message" class="text-sm text-slate-600">Message</label>
              <textarea id="message" name="message" rows="5" class="mt-1 w-full px-3 py-2 border rounded focus:outline-none" placeholder="Write your message..." required><?php echo h($old['message']); ?></textarea>
            </div>
            <button type="submit" class="mt-4 inline-flex items-center gap-2 bg-[var(--brand)] text-white px-5 py-2 rounded hover:bg-[#0959ab]">
              Send Message <i class="bi bi-send"></i>
            </button>
          </form>

          <div class="mt-6 text-sm text-slate-600">
            Prefer email? Write to <a href="mailto:<?php echo h(SUPPORT_EMAIL); ?>" class="text-[var(--brand)]"><?php echo h(SUPPORT_EMAIL); ?></a>.
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <?php
    $_cf_fb    = site_setting_raw('social_facebook');
    $_cf_tt    = site_setting_raw('social_tiktok');
    $_cf_copy  = site_setting_raw('copyright_text','MSJOBS. All rights reserved.');
  ?>
  <?php require_once __DIR__ . '/footer.php'; ?>


  <!-- JS libs (optional) -->
  <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="lib/wow/wow.min.js"></script>
  <script src="lib/easing/easing.min.js"></script>
  <script src="lib/waypoints/waypoints.min.js"></script>
  <script src="lib/owlcarousel/owl.carousel.min.js"></script>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
</body>
</html>
