<?php
/* about.php — MSJOBS "About Us" with contact form via SMTP (PHPMailer)
 *
 * SETUP:
 * 1) composer require phpmailer/phpmailer
 * 2) Fill the SMTP_* settings and $ADMIN_EMAIL below.
 */

declare(strict_types=1);
session_start();
require_once __DIR__ . '/settings_helper.php';

/* ====== SETTINGS ====== */
$SITE_NAME    = 'MSJOBS';
$ADMIN_EMAIL  = 'info@msjobs.net';       // <-- where admin notifications go

// SMTP (edit these)
$SMTP_HOST    = 'smtp.gmail.com';
$SMTP_PORT    = 587;                         // 465 for SMTPS, 587 for STARTTLS
$SMTP_USER    = 'mshrc936@gmail.com';   // full mailbox/username
$SMTP_PASS    = 'nmspuxcjuptondkd';
$SMTP_SECURE  = 'tls';                       // 'tls' or 'ssl'
$FROM_EMAIL   = 'mshrc936@gmail.com';   // envelope sender; should match your domain
$FROM_NAME    = $SITE_NAME;

/* ====== CSRF TOKEN ====== */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ====== HELPERS ====== */
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_valid_email(string $e): bool { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }

$flash = ['type' => '', 'msg' => ''];

/* ====== FORM HANDLER (SMTP) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  $posted_token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $posted_token)) {
    $flash = ['type' => 'error', 'msg' => 'Security verification failed. Please try again.'];
  } else {
    // Honeypot
    if (trim((string)($_POST['website'] ?? '')) !== '') {
      $flash = ['type' => 'success', 'msg' => 'Thanks! We received your message.'];
    } else {
      $name    = trim((string)($_POST['name'] ?? ''));
      $email   = trim((string)($_POST['email'] ?? ''));
      $company = trim((string)($_POST['company'] ?? ''));
      $message = trim((string)($_POST['message'] ?? ''));

      $errors = [];
      if ($name === '')             $errors[] = 'Full name is required.';
      if (!is_valid_email($email))  $errors[] = 'A valid email address is required.';
      if ($message === '')          $errors[] = 'Please enter a message.';

      if ($errors) {
        $flash = ['type' => 'error', 'msg' => implode(' ', $errors)];
      } else {
        // Build email
        $subject = "New contact message — {$SITE_NAME}";
        $textBody = "You have received a new message via the About page contact form.\n\n".
                    "Name:    {$name}\n".
                    "Email:   {$email}\n".
                    "Company: ".($company !== '' ? $company : '(not provided)')."\n".
                    "IP:      ".($_SERVER['REMOTE_ADDR'] ?? 'unknown')."\n".
                    "Agent:   ".($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')."\n".
                    "Date:    ".date('Y-m-d H:i:s')."\n\n".
                    "Message:\n{$message}\n";

        $htmlBody = '<h2 style="margin:0 0 10px;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif">New contact message — '.h($SITE_NAME).'</h2>'.
                    '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif">'.
                    '<tr><td><strong>Name</strong></td><td>'.h($name).'</td></tr>'.
                    '<tr><td><strong>Email</strong></td><td>'.h($email).'</td></tr>'.
                    '<tr><td><strong>Company</strong></td><td>'.($company !== '' ? h($company) : '(not provided)').'</td></tr>'.
                    '<tr><td><strong>IP</strong></td><td>'.h($_SERVER['REMOTE_ADDR'] ?? 'unknown').'</td></tr>'.
                    '<tr><td><strong>Agent</strong></td><td>'.h($_SERVER['HTTP_USER_AGENT'] ?? 'unknown').'</td></tr>'.
                    '<tr><td><strong>Date</strong></td><td>'.date('Y-m-d H:i:s').'</td></tr>'.
                    '</table>'.
                    '<hr style="margin:14px 0;border:none;border-top:1px solid #eee" />'.
                    '<div><strong>Message</strong></div>'.
                    '<div style="white-space:pre-wrap">'.nl2br(h($message)).'</div>';

        // Send via PHPMailer SMTP
        try {
          require_once __DIR__ . '/vendor/autoload.php';
          $mailer = new PHPMailer\PHPMailer\PHPMailer(true);

          // Server settings
          $mailer->isSMTP();
          $mailer->Host       = $SMTP_HOST;
          $mailer->Port       = $SMTP_PORT;
          $mailer->SMTPAuth   = true;
          $mailer->Username   = $SMTP_USER;
          $mailer->Password   = $SMTP_PASS;
          $mailer->SMTPSecure = $SMTP_SECURE; // 'tls' or 'ssl'
          $mailer->CharSet    = 'UTF-8';

          // Recipients
          $mailer->setFrom($FROM_EMAIL, $FROM_NAME);
          $mailer->addAddress($ADMIN_EMAIL);               // Admin notification
          if (is_valid_email($email)) {
            // Let admin reply to the user
            $mailer->addReplyTo($email, $name);
          }

          // Content
          $mailer->isHTML(true);
          $mailer->Subject = $subject;
          $mailer->Body    = $htmlBody;
          $mailer->AltBody = $textBody;

          $mailer->send();
          $flash = ['type' => 'success', 'msg' => 'Thank you! Your message has been sent.'];
          $_POST = []; // clear form
        } catch (Throwable $e) {
          $flash = ['type' => 'error', 'msg' => 'Mailer error: '.h($e->getMessage())];
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title><?= h(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= h(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <link rel="icon" type="image/png" href="img/1748025713_MS copy.png" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <meta name="description" content="MSJOBS connects jobseekers and employers with verified jobs, simple hiring tools, and faster outcomes." />
  <meta name="keywords" content="MSJOBS, About, recruitment, jobs, careers, UAE, GCC" />
  <link rel="canonical" href="https://www.msjobs.net/about.php" />

  <!-- Open Graph -->
  <meta property="og:title" content="About MSJOBS" />
  <meta property="og:description" content="Recruitment made simple for jobseekers and employers." />
  <meta property="og:image" content="img/1748025713_MS copy.png" />
  <meta property="og:type" content="website" />

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Inter:wght@600;700;800&display=swap" rel="stylesheet" />

  <!-- Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Optional libs -->
  <link href="lib/animate/animate.min.css" rel="stylesheet" />
  <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet" />
  <link href="css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" />

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root { --brand:#0a66c2; --brand-ink:#0e2a47; }
    body { font-family: Heebo, system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif; }
    .card { transition: box-shadow .2s ease, transform .2s ease; }
    .card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(2, 32, 71, .06); }
    .container { max-width: 1200px; }
  </style>

  <!-- JSON-LD Breadcrumbs -->
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"BreadcrumbList",
    "itemListElement":[
      {"@type":"ListItem","position":1,"name":"Home","item":"https://www.msjobs.net/"},
      {"@type":"ListItem","position":2,"name":"About Us","item":"https://www.msjobs.net/about.php"}
    ]
  }
  </script>
</head>

<body class="antialiased text-slate-800 bg-white">

  <!-- Top Nav -->
  <nav class="bg-white/95 backdrop-blur border-b">
    <div class="container mx-auto px-4">
      <div class="h-16 flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-2">
          <img src="img/1748025713_MS copy.png" alt="MSJOBS" class="h-9 w-9" />
          <span class="text-xl font-extrabold text-[var(--brand-ink)]">MSJOBS</span>
        </a>
        <button class="md:hidden p-2 rounded hover:bg-gray-100" onclick="document.getElementById('mob').classList.toggle('hidden')">
          <i class="bi bi-list text-2xl"></i>
        </button>
        <div class="hidden md:flex items-center gap-6 text-sm">
          <a href="index.php" class="hover:text-[var(--brand)]">Jobs</a>
          <a href="about.php" class="text-[var(--brand)] font-semibold">About</a>
          <a href="contact.php" class="hover:text-[var(--brand)]">Contact</a>
          <a href="login.php" class="ml-2 inline-flex items-center gap-2 bg-[var(--brand)] text-white px-4 py-2 rounded hover:bg-[#0959ab]">
            Login <i class="bi bi-arrow-right-short text-lg"></i>
          </a>
        </div>
      </div>
      <div id="mob" class="md:hidden hidden pb-3 border-t">
        <a class="block px-2 py-2" href="index.php">Jobs</a>
        <a class="block px-2 py-2 text-[var(--brand)] font-semibold" href="about.php">About</a>
        <a class="block px-2 py-2" href="contact.html">Contact</a>
        <a class="block px-2 py-2 bg-[var(--brand)] text-white rounded mt-2 text-center" href="login.php">Login</a>
      </div>
    </div>
  </nav>

  <!-- Flash Messages -->
  <?php if ($flash['msg'] !== ''): ?>
    <div class="container mx-auto px-4 mt-4">
      <div class="<?= $flash['type']==='success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?> border rounded-lg p-3">
        <div class="flex items-center gap-2">
          <i class="bi <?= $flash['type']==='success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
          <span class="text-sm"><?= h($flash['msg']) ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Breadcrumb + Hero -->
  <?php require_once __DIR__ . '/header.php'; ?>


  <!-- Stats -->
  <section class="bg-white">
    <div class="container mx-auto px-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 py-8">
        <div class="card p-6 border rounded-xl text-center">
          <div class="text-3xl font-extrabold">50K+</div>
          <div class="mt-1 text-sm text-slate-600">Monthly Job Views</div>
        </div>
        <div class="card p-6 border rounded-xl text-center">
          <div class="text-3xl font-extrabold">10K+</div>
          <div class="mt-1 text-sm text-slate-600">Active Candidates</div>
        </div>
        <div class="card p-6 border rounded-xl text-center">
          <div class="text-3xl font-extrabold">2K+</div>
          <div class="mt-1 text-sm text-slate-600">Verified Employers</div>
        </div>
        <div class="card p-6 border rounded-xl text-center">
          <div class="text-3xl font-extrabold">24/7</div>
          <div class="mt-1 text-sm text-slate-600">Support</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Mission -->
  <section class="bg-gray-50">
    <div class="container mx-auto px-4 py-12 md:py-16">
      <div class="grid md:grid-cols-2 gap-10 items-start">
        <div>
          <h2 class="text-2xl md:text-3xl font-bold text-slate-900">Our mission</h2>
          <p class="mt-4 text-slate-700">
            To make recruitment simple, transparent, and effective for everyone. We focus on verified listings,
            streamlined applications, and clear communication—so great teams can form faster.
          </p>
          <ul class="mt-6 space-y-3 text-slate-700">
            <li class="flex items-start gap-3"><i class="bi bi-check-circle-fill text-[var(--brand)] mt-0.5"></i> Verified jobs from trusted employers</li>
            <li class="flex items-start gap-3"><i class="bi bi-check-circle-fill text-[var(--brand)] mt-0.5"></i> Structured workflows for recruiters & HR teams</li>
            <li class="flex items-start gap-3"><i class="bi bi-check-circle-fill text-[var(--brand)] mt-0.5"></i> Privacy-first candidate experience</li>
          </ul>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="card p-6 bg-white border rounded-xl text-center">
            <i class="bi bi-search text-3xl text-[var(--brand)]"></i>
            <h3 class="mt-3 font-semibold">Smart Search</h3>
            <p class="text-sm text-slate-600 mt-1">Filter by skills, location, and salary.</p>
          </div>
          <div class="card p-6 bg-white border rounded-xl text-center">
            <i class="bi bi-person-check text-3xl text-[var(--brand)]"></i>
            <h3 class="mt-3 font-semibold">Quality Matches</h3>
            <p class="text-sm text-slate-600 mt-1">Relevant, screened candidate profiles.</p>
          </div>
          <div class="card p-6 bg-white border rounded-xl text-center">
            <i class="bi bi-calendar-check text-3xl text-[var(--brand)]"></i>
            <h3 class="mt-3 font-semibold">Easy Scheduling</h3>
            <p class="text-sm text-slate-600 mt-1">Slots, reminders, & follow-ups.</p>
          </div>
          <div class="card p-6 bg-white border rounded-xl text-center">
            <i class="bi bi-graph-up-arrow text-3xl text-[var(--brand)]"></i>
            <h3 class="mt-3 font-semibold">Hiring Analytics</h3>
            <p class="text-sm text-slate-600 mt-1">Insights to reduce time-to-hire.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- How we work -->
  <section class="bg-white">
    <div class="container mx-auto px-4 py-12 md:py-16">
      <h2 class="text-2xl md:text-3xl font-bold text-center">How MSJOBS works</h2>
      <div class="grid md:grid-cols-2 gap-6 mt-10">
        <div class="card p-6 border rounded-xl">
          <div class="flex items-center gap-3">
            <i class="bi bi-person-fill text-2xl text-[var(--brand)]"></i>
            <h3 class="text-xl font-semibold">For Jobseekers</h3>
          </div>
          <ol class="mt-4 space-y-3 list-decimal list-inside text-slate-700">
            <li>Create your profile & upload a resume</li>
            <li>Search & filter jobs that fit your goals</li>
            <li>Apply quickly & track application status</li>
            <li>Get interview invites and timely updates</li>
          </ol>
          <a href="login.php" class="inline-flex items-center gap-2 mt-4 text-[var(--brand)] font-semibold">
            Start your profile <i class="bi bi-arrow-right"></i>
          </a>
        </div>
        <div class="card p-6 border rounded-xl">
          <div class="flex items-center gap-3">
            <i class="bi bi-building text-2xl text-[var(--brand)]"></i>
            <h3 class="text-xl font-semibold">For Employers</h3>
          </div>
          <ol class="mt-4 space-y-3 list-decimal list-inside text-slate-700">
            <li>Post jobs with clear requirements</li>
            <li>Screen candidates with built-in tools</li>
            <li>Schedule interviews & share offers</li>
            <li>Track results with hiring analytics</li>
          </ol>
          <a href="login.php" class="inline-flex items-center gap-2 mt-4 text-[var(--brand)] font-semibold">
            Post a job <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Values -->
  <section class="bg-gray-50">
    <div class="container mx-auto px-4 py-12 md:py-16">
      <h2 class="text-2xl md:text-3xl font-bold text-center">Our values</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
        <div class="card p-6 bg-white border rounded-xl">
          <i class="bi bi-handshake-fill text-2xl text-[var(--brand)]"></i>
          <h3 class="mt-3 font-semibold">Integrity</h3>
          <p class="text-sm text-slate-600 mt-1">We build trust through transparency and fairness.</p>
        </div>
        <div class="card p-6 bg-white border rounded-xl">
          <i class="bi bi-lightbulb-fill text-2xl text-[var(--brand)]"></i>
          <h3 class="mt-3 font-semibold">Innovation</h3>
          <p class="text-sm text-slate-600 mt-1">Practical tools that make hiring easier.</p>
        </div>
        <div class="card p-6 bg-white border rounded-xl">
          <i class="bi bi-people-fill text-2xl text-[var(--brand)]"></i>
          <h3 class="mt-3 font-semibold">Teamwork</h3>
          <p class="text-sm text-slate-600 mt-1">We collaborate to deliver the best results.</p>
        </div>
        <div class="card p-6 bg-white border rounded-xl">
          <i class="bi bi-star-fill text-2xl text-[var(--brand)]"></i>
          <h3 class="mt-3 font-semibold">Customer First</h3>
          <p class="text-sm text-slate-600 mt-1">Your goals guide our roadmap.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Leadership -->
  <section class="bg-white">
    <div class="container mx-auto px-4 py-12 md:py-16">
      <h2 class="text-2xl md:text-3xl font-bold text-center">Leadership</h2>
      <p class="text-center text-slate-600 mt-2">Focused on outcomes, culture, and customer success.</p>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
        <article class="card border rounded-xl p-6 text-center">
          <div class="mx-auto h-20 w-20 rounded-full bg-gray-100 flex items-center justify-center">
            <i class="bi bi-person text-3xl text-slate-500"></i>
          </div>
          <h3 class="mt-3 font-semibold">M.S. Safayar</h3>
          <p class="text-sm text-slate-500">Managing Director</p>
          <p class="text-sm text-slate-600 mt-2">Building reliable hiring systems across the GCC.</p>
          <div class="mt-3 flex items-center justify-center gap-3 text-slate-500">
            <a href="#" class="hover:text-[var(--brand)]"><i class="bi bi-linkedin"></i></a>
            <a href="#" class="hover:text-[var(--brand)]"><i class="bi bi-twitter-x"></i></a>
          </div>
        </article>

        <article class="card border rounded-xl p-6 text-center">
          <div class="mx-auto h-20 w-20 rounded-full bg-gray-100 flex items-center justify-center">
            <i class="bi bi-person text-3xl text-slate-500"></i>
          </div>
          <h3 class="mt-3 font-semibold">Team Operations</h3>
          <p class="text-sm text-slate-500">Recruitment & Support</p>
          <p class="text-sm text-slate-600 mt-2">Delivering candidate care and employer success.</p>
          <div class="mt-3 flex items-center justify-center gap-3 text-slate-500">
            <a href="#" class="hover:text-[var(--brand)]"><i class="bi bi-linkedin"></i></a>
            <a href="#" class="hover:text-[var(--brand)]"><i class="bi bi-twitter-x"></i></a>
          </div>
        </article>

        <article class="card border rounded-xl p-6 text-center">
          <div class="mx-auto h-20 w-20 rounded-full bg-gray-100 flex items-center justify-center">
            <i class="bi bi-person text-3xl text-slate-500"></i>
          </div>
          <h3 class="mt-3 font-semibold">Engineering</h3>
          <p class="text-sm text-slate-500">Product & Data</p>
          <p class="text-sm text-slate-600 mt-2">Tools that reduce time-to-hire and improve quality.</p>
          <div class="mt-3 flex items-center justify-center gap-3 text-slate-500">
            <a href="#" class="hover:text-[var(--brand)]"><i class="bi bi-linkedin"></i></a>
            <a href="#" class="hover:text-[var(--brand)]"><i class="bi bi-twitter-x"></i></a>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- Recognitions -->
  <section class="bg-gray-50">
    <div class="container mx-auto px-4 py-12 md:py-16">
      <h2 class="text-2xl md:text-3xl font-bold text-center">Recognitions</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8">
        <div class="border rounded-xl p-6 text-center bg-white card">
          <i class="bi bi-award text-2xl text-[var(--brand)]"></i>
          <p class="text-sm mt-2 text-slate-600">Top HR Service</p>
        </div>
        <div class="border rounded-xl p-6 text-center bg-white card">
          <i class="bi bi-trophy text-2xl text-[var(--brand)]"></i>
          <p class="text-sm mt-2 text-slate-600">Customer Choice</p>
        </div>
        <div class="border rounded-xl p-6 text-center bg-white card">
          <i class="bi bi-megaphone text-2xl text-[var(--brand)]"></i>
          <p class="text-sm mt-2 text-slate-600">In the News</p>
        </div>
        <div class="border rounded-xl p-6 text-center bg-white card">
          <i class="bi bi-shield-check text-2xl text-[var(--brand)]"></i>
          <p class="text-sm mt-2 text-slate-600">Verified by Clients</p>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQs -->
  <section class="bg-white">
    <div class="container mx-auto px-4 py-12 md:py-16">
      <h2 class="text-2xl md:text-3xl font-bold text-center">FAQs</h2>
      <div class="max-w-3xl mx-auto mt-8 space-y-4">
        <details class="border rounded-lg p-4 card">
          <summary class="cursor-pointer font-semibold">Is MSJOBS free for jobseekers?</summary>
          <p class="mt-2 text-slate-700">Yes, job search and applications are free for candidates.</p>
        </details>
        <details class="border rounded-lg p-4 card">
          <summary class="cursor-pointer font-semibold">How do you verify employers?</summary>
          <p class="mt-2 text-slate-700">We review company info and documents before publishing jobs.</p>
        </details>
        <details class="border rounded-lg p-4 card">
          <summary class="cursor-pointer font-semibold">Do you share candidate data?</summary>
          <p class="mt-2 text-slate-700">We only share application data with the employer of the job you apply to.</p>
        </details>
      </div>
    </div>
  </section>

  <!-- Contact / Office -->
  <section class="bg-gray-50" id="contact">
    <div class="container mx-auto px-4 py-12 md:py-16">
      <div class="grid md:grid-cols-2 gap-6 items-start">
        <!-- Office -->
        <div class="card border rounded-xl p-6 bg-white">
          <h3 class="text-xl font-semibold">Our Office</h3>
          <p class="mt-2 text-slate-700"><?= site_setting('contact_address','Real Group Building, Ajman Industrial Area 2, United Arab Emirates') ?></p>
          <p class="mt-2 text-slate-700">Email: <a href="mailto:<?= site_setting('support_email','support@msjobs.net') ?>" class="text-[var(--brand)]"><?= site_setting('support_email','support@msjobs.net') ?></a></p>
          <div class="mt-4 flex gap-3 text-slate-600">
            <?php if (site_setting_raw('social_linkedin')): ?><a class="hover:text-[var(--brand)]" href="<?= site_setting('social_linkedin') ?>"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
            <?php if (site_setting_raw('social_tiktok')): ?><a class="hover:text-[var(--brand)]" href="<?= site_setting('social_tiktok') ?>"><i class="fab fa-tiktok"></i></a><?php endif; ?>
            <?php if (site_setting_raw('social_instagram')): ?><a class="hover:text-[var(--brand)]" href="<?= site_setting('social_instagram') ?>"><i class="fab fa-instagram"></i></a><?php endif; ?>
            <?php if (site_setting_raw('social_facebook')): ?><a class="hover:text-[var(--brand)]" href="<?= site_setting('social_facebook') ?>"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
          </div>
        </div>

        <!-- Contact Form -->
        <form method="post" action="#contact" class="card border rounded-xl p-6 bg-white">
          <h3 class="text-xl font-semibold">Talk to us</h3>

          <!-- CSRF token -->
          <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
          <!-- Honeypot -->
          <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

          <div class="grid md:grid-cols-2 gap-4 mt-4">
            <div>
              <label class="block text-sm mb-1">Full name<span class="text-red-500">*</span></label>
              <input name="name" value="<?= h($_POST['name'] ?? '') ?>" required type="text" placeholder="Full name" class="w-full px-3 py-2 border rounded focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm mb-1">Email address<span class="text-red-500">*</span></label>
              <input name="email" value="<?= h($_POST['email'] ?? '') ?>" required type="email" placeholder="Email address" class="w-full px-3 py-2 border rounded focus:outline-none" />
            </div>
          </div>

          <div class="mt-4">
            <label class="block text-sm mb-1">Company (optional)</label>
            <input name="company" value="<?= h($_POST['company'] ?? '') ?>" type="text" placeholder="Company" class="w-full px-3 py-2 border rounded focus:outline-none" />
          </div>

          <div class="mt-4">
            <label class="block text-sm mb-1">Message<span class="text-red-500">*</span></label>
            <textarea name="message" rows="4" required placeholder="Your message..." class="w-full px-3 py-2 border rounded focus:outline-none"><?= h($_POST['message'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="mt-4 inline-flex items-center gap-2 bg-[var(--brand)] text-white px-5 py-2 rounded hover:bg-[#0959ab]">
            Send <i class="bi bi-send"></i>
          </button>

          <?php if ($flash['msg'] !== ''): ?>
            <p class="mt-3 text-sm <?= $flash['type']==='success' ? 'text-green-600' : 'text-red-600' ?>">
              <?= h($flash['msg']) ?>
            </p>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <?php require_once __DIR__ . '/footer.php'; ?>


  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>
