<?php
/**************************************************************
 * MSJOBS — Post a New Job (Premium UI + Mobile Responsive)
 * - Gradient header, back/home
 * - Drag & drop logo upload with preview
 * - Live currency symbols, counters
 * - PHPMailer BCC notifications to active jobseekers
 * - Defensive validation & prepared statements
 * - Alignment & spacing cleaned up (mobile-first)
 * - FIX: Currency prefix uses fixed-width box to prevent alignment shifts
 **************************************************************/

declare(strict_types=1);
session_start();

/** ====== CONFIG ====== */
const DB_HOST = '127.0.0.1:3306';
const DB_USER = 'u903588615_root';
const DB_PASS = 'Msjobs#1';
const DB_NAME = 'u903588615_exaple';

const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'mshrc936@gmail.com';
const SMTP_PASS = 'nmspuxcjuptondkd';
const SMTP_FROM = 'mshrc936@gmail.com';
const SMTP_FROM_NAME = 'Job Portal';

const LOGO_DIR = 'uploads/logos/'; // relative to this script/webroot
const MAX_LOGO_MB = 5;
const ENABLE_CSRF = false; // set true to enforce CSRF token

/** ====== LIBS ====== */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

/** ====== SESSION GUARD ====== */
if (!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'employer') {
  header('Location: login.php'); exit();
}
$user_id = (int)($_SESSION['user_id'] ?? 0);

/** ====== CSRF ====== */
if (ENABLE_CSRF && empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check(): void {
  if (!ENABLE_CSRF) return;
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403); die('Invalid CSRF token.');
  }
}

/** ====== DB CONNECT ====== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  error_log('DB connect failed: '.$e->getMessage());
  die('Service unavailable.');
}

/** ====== CURRENCIES ====== */
$currencies = [
  'USD' => ['symbol' => '$',   'name' => 'US Dollar'],
  'QAR' => ['symbol' => 'QAR', 'name' => 'Qatari Riyal'],
  'AED' => ['symbol' => 'AED', 'name' => 'UAE Dirham'],
  'EUR' => ['symbol' => '€',   'name' => 'Euro'],
  'SAR' => ['symbol' => 'SAR', 'name' => 'Saudi Riyal'],
  'OMR' => ['symbol' => 'OMR', 'name' => 'Omani Rial'],
  'KWD' => ['symbol' => 'KWD', 'name' => 'Kuwaiti Dinar'],
  'BHD' => ['symbol' => 'BHD', 'name' => 'Bahraini Dinar'],
  'MYR' => ['symbol' => 'RM',  'name' => 'Malaysian Ringgit'],
];

/** ====== FETCH EMPLOYER (debug banner) ====== */
$debug_user_info = '';
$debug_stmt = $conn->prepare("SELECT id, email FROM users WHERE id=? AND user_type='employer' LIMIT 1");
$debug_stmt->bind_param("i", $user_id);
$debug_stmt->execute();
if ($debug_row = $debug_stmt->get_result()->fetch_assoc()) {
  $debug_user_info = "Employer ID {$user_id}: ".($debug_row['email'] ?? 'No email');
} else {
  header('Location: login.php'); exit();
}
$debug_stmt->close();

/** ====== (NEW) AUTO-FETCH COMPANY NAME FROM employers ====== */
$company_name_fetched = '';
$emp_stmt = $conn->prepare("SELECT company_name FROM employers WHERE user_id = ? LIMIT 1");
$emp_stmt->bind_param("i", $user_id);
$emp_stmt->execute();
if ($emp_row = $emp_stmt->get_result()->fetch_assoc()) {
  $company_name_fetched = trim((string)($emp_row['company_name'] ?? ''));
}
$emp_stmt->close();

/** ====== ACTIVE JOBSEEKER EMAILS ====== */
$jobseeker_emails = [];
$js = $conn->prepare("SELECT DISTINCT email FROM users WHERE user_type='jobseeker' AND email IS NOT NULL AND email<>'' AND status='active'");
$js->execute();
$r = $js->get_result();
while ($row = $r->fetch_assoc()) {
  if (filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
    $jobseeker_emails[] = $row['email'];
  }
}
$js->close();

/** ====== HELPERS ====== */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function generateEmailTemplate(array $job, array $currencies): string {
  $deadline_formatted = date('F j, Y', strtotime($job['deadline']));
  $currency_symbol = $currencies[$job['currency']]['symbol'] ?? '$';
  $title = h($job['title']);
  $company = h($job['company_name']);
  $location = h($job['location']);
  $type = h($job['type']);
  $cat = h($job['category']);
  $desc = nl2br(h($job['description']));
  $minS = number_format((float)$job['min_salary']);
  $maxS = number_format((float)$job['max_salary']);

  return "
  <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa;'>
    <div style=\"background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding: 30px; text-align: center;\">
      <h1 style='color:white; margin:0; font-size:28px;'>New Job Opportunity!</h1>
    </div>
    <div style='padding:30px; background:#fff'>
      <div style='border-left:4px solid #667eea; padding-left:20px; margin-bottom:25px;'>
        <h2 style='color:#2d3748; margin:0 0 10px; font-size:24px;'>{$title}</h2>
        <p style='color:#667eea; font-size:18px; font-weight:600; margin:0'>{$company}</p>
      </div>
      <div style='background:#f7fafc; padding:20px; border-radius:8px; margin-bottom:25px'>
        <table style='width:100%; border-collapse:collapse'>
          <tr><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; font-weight:600; color:#4a5568;'>Location:</td><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; color:#2d3748;'>{$location}</td></tr>
          <tr><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; font-weight:600; color:#4a5568;'>Job Type:</td><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; color:#2d3748;'>{$type}</td></tr>
          <tr><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; font-weight:600; color:#4a5568;'>Salary:</td><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; color:#2d3748;'>{$currency_symbol}{$minS} - {$currency_symbol}{$maxS}</td></tr>
          <tr><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; font-weight:600; color:#4a5568;'>Category:</td><td style='padding:10px 0; border-bottom:1px solid #e2e8f0; color:#2d3748;'>{$cat}</td></tr>
          <tr><td style='padding:10px 0; font-weight:600; color:#4a5568;'>Deadline:</td><td style='padding:10px 0; color:#e53e3e; font-weight:600;'>".h($deadline_formatted)."</td></tr>
        </table>
      </div>
      <div style='margin-bottom:30px'>
        <h3 style='color:#2d3748; margin-bottom:15px;'>Job Description:</h3>
        <div style='background:#fff; padding:20px; border:1px solid #e2e8f0; border-radius:6px; line-height:1.6'>{$desc}</div>
      </div>
      <div style='text-align:center; margin-top:40px'>
        <a href='https://msjobs.net/login' style='display:inline-block; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; padding:15px 30px; text-decoration:none; border-radius:50px; font-weight:600; font-size:16px'> Apply Now</a>
      </div>
    </div>
    <div style='background:#2d3748; padding:20px; text-align:center'>
      <p style='color:#a0aec0; margin:0; font-size:14px'>This is an automated notification from Job Portal. Please do not reply to this email.</p>
    </div>
  </div>";
}

/** ====== FORM PROCESS ====== */
$success = null; $errors = []; $posted_job = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_job'])) {
  csrf_check();

  // Collect & sanitize
  $title        = trim($_POST['title'] ?? '');
  $company_name = trim($_POST['company_name'] ?? '');
  $location     = trim($_POST['location'] ?? '');
  $type         = trim($_POST['type'] ?? '');
  $min_salary   = (float)($_POST['min_salary'] ?? 0);
  $max_salary   = (float)($_POST['max_salary'] ?? 0);
  $currency     = $_POST['currency'] ?? 'USD';
  $deadline     = trim($_POST['deadline'] ?? '');
  $category     = trim($_POST['category'] ?? '');
  $description  = trim($_POST['description'] ?? '');

  /** (NEW) Override company name with value fetched from employers */
  if ($company_name_fetched !== '') {
    $company_name = $company_name_fetched;
  }

  // Validate
  if ($title === '')                      $errors[] = 'Job title is required';
  if ($company_name === '')               $errors[] = 'Company name is required';
  if ($location === '')                   $errors[] = 'Location is required';
  if ($type === '')                       $errors[] = 'Job type is required';
  if ($min_salary <= 0)                   $errors[] = 'Minimum salary must be greater than 0';
  if ($max_salary <= 0)                   $errors[] = 'Maximum salary must be greater than 0';
  if ($min_salary >= $max_salary)         $errors[] = 'Maximum salary must be greater than minimum salary';
  if (!isset($currencies[$currency]))     $errors[] = 'Invalid currency selected';
  if ($deadline === '')                   $errors[] = 'Application deadline is required';
  elseif (strtotime($deadline) <= time()) $errors[] = 'Deadline must be in the future';
  if ($category === '')                   $errors[] = 'Job category is required';
  if ($description === '')                $errors[] = 'Job description is required';

  // File upload (logo required)
  $logo_filename = '';
  if (($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $maxBytes = MAX_LOGO_MB * 1024 * 1024;
    $info = $_FILES['logo'];
    $tmp = $info['tmp_name'];
    $size = (int)$info['size'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    if (!in_array($detected, $allowed, true)) {
      $errors[] = 'Invalid logo type. Allowed: JPEG, PNG, GIF, WebP.';
    } elseif ($size > $maxBytes) {
      $errors[] = 'Logo too large. Max '.MAX_LOGO_MB.'MB.';
    } else {
      // Ensure upload directory exists
      if (!is_dir(LOGO_DIR) && !mkdir(LOGO_DIR, 0755, true)) {
        $errors[] = 'Upload directory creation failed.';
      } else {
        $ext = strtolower(pathinfo($info['name'], PATHINFO_EXTENSION));
        $logo_filename = uniqid('logo_', true).'.'.$ext;
        $target = rtrim(LOGO_DIR, '/').'/'.$logo_filename;
        if (!move_uploaded_file($tmp, $target)) {
          $errors[] = 'Failed to save uploaded logo.';
          $logo_filename = '';
        }
      }
    }
  } else {
    $errors[] = 'Company logo is required.';
  }

  // Insert + notify
  if (!$errors) {
    $stmt = $conn->prepare("
      INSERT INTO jobs
      (title, company_name, location, type, min_salary, max_salary, currency, deadline, logo, company_id, category, description, created_at, status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?, ?, NOW(),'active')
    ");
    // s s s s d d s s s i s s
    $stmt->bind_param(
      "ssssddsssiss",
      $title,
      $company_name,
      $location,
      $type,
      $min_salary,
      $max_salary,
      $currency,
      $deadline,
      $logo_filename,
      $user_id,
      $category,
      $description
    );

    if ($stmt->execute()) {
      $job_id = $conn->insert_id;
      $posted_job = [
        'id'=>$job_id,'title'=>$title,'company_name'=>$company_name,'location'=>$location,'type'=>$type,
        'min_salary'=>$min_salary,'max_salary'=>$max_salary,'currency'=>$currency,'logo'=>$logo_filename,
        'category'=>$category,'description'=>$description,'deadline'=>$deadline
      ];
      $success = 'Job posted successfully.';

      // Email notifications
      if (!empty($jobseeker_emails)) {
        $mail = new PHPMailer(true);
        try {
          $mail->isSMTP();
          $mail->Host = SMTP_HOST;
          $mail->SMTPAuth = true;
          $mail->Username = SMTP_USER;
          $mail->Password = SMTP_PASS;
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
          $mail->Port = SMTP_PORT;

          $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
          foreach ($jobseeker_emails as $addr) $mail->addBCC($addr);
          $mail->isHTML(true);
          $mail->Subject = 'New Job Alert From Msjobs: '.$title.' at '.$company_name;
          $mail->Body = generateEmailTemplate($posted_job, $currencies);

          $mail->send();
          $success .= ' Email notifications sent to '.count($jobseeker_emails).' jobseekers.';
        } catch (Exception $e) {
          error_log('PHPMailer error: '.$mail->ErrorInfo);
          $success .= ' (Note: Email notifications could not be sent.)';
        }
      } else {
        $success .= ' (No active jobseekers found for email notifications)';
      }
    } else {
      $errors[] = 'DB error: '.$stmt->error;
    }
    $stmt->close();
  }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Post New Job — MSJOBS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root{ --brand:#667eea; --brand2:#764ba2; }
    .gradient-bg{ background:linear-gradient(135deg,var(--brand) 0%, var(--brand2) 100%) }
    .glass{ background:rgba(255,255,255,.92); backdrop-filter:saturate(140%) blur(8px) }
    .card{ background:#fff; border:1px solid #e5e7eb; border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.05) }
    .dropzone{ border:2px dashed #cbd5e1; border-radius:14px; transition:.2s; }
    .dropzone.drag{ border-color:#7c3aed; background:#faf5ff }
    .shadow-soft{ box-shadow:0 10px 25px -15px rgba(0,0,0,.2) }
    /* FIX: keep prefix width stable for $, AED, QAR, etc. */
    .prefix-box { min-width: 3.5rem; } /* ~56px */
  </style>
</head>
<body class="bg-gray-50 min-h-screen">
  <!-- Header -->
  <header class="sticky top-0 z-40 gradient-bg text-white shadow-soft">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button onclick="goBack()" class="px-3 py-2 rounded-lg bg-white/15 hover:bg-white/25 focus:outline-none focus:ring-2 focus:ring-white/40">
          ← Back
        </button>
        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">Post a New Job</h1>
      </div>
      <a href="company.php" class="px-4 py-2 rounded-lg bg-white text-indigo-900 font-semibold hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/50">
        Home
      </a>
    </div>
  </header>

  <!-- Container -->
  <main class="max-w-6xl mx-auto px-4 py-6">
    <!-- Banner -->
    <section class="gradient-bg rounded-2xl p-6 text-white mb-6">
      <p class="opacity-90 text-sm sm:text-base">Fill the form to publish your job and notify active jobseekers.</p>
    </section>

    <!-- Alerts -->
    <?php if ($success): ?>
      <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10Zm-1-5 8-8-1.41-1.42L11 14.17l-3.59-3.58L6 12l5 5Z"/></svg>
          <div class="leading-6"><?= h($success) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl">
        <div class="font-semibold mb-2">Please fix the following:</div>
        <ul class="list-disc ps-5 space-y-1">
          <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
      <!-- FORM -->
      <section class="card p-6">
        <form method="POST" enctype="multipart/form-data" novalidate class="space-y-6">
          <?php if (ENABLE_CSRF): ?>
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
          <?php endif; ?>

          <!-- Basic Info -->
          <div>
            <h2 class="text-base font-semibold text-slate-800 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <!-- Title -->
              <div class="md:col-span-2">
                <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Job Title *</label>
                <div class="relative">
                  <input id="title" name="title" type="text" maxlength="80"
                         value="<?= h($_POST['title'] ?? '') ?>"
                         class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                         placeholder="e.g. Senior Software Developer" required>
                  <div class="absolute right-2 bottom-2 text-xs text-gray-500"><span id="titleCount">0</span>/80</div>
                </div>
              </div>

              <!-- Company (auto-filled, read-only) -->
              <div>
                <label for="company_name" class="block text-sm font-medium text-slate-700 mb-1">Company Name *</label>
                <input id="company_name" name="company_name" type="text"
                       value="<?= h($company_name_fetched) ?>"
                       class="w-full px-4 py-3 border rounded-lg bg-gray-50 text-gray-700 focus:ring-0 focus:border-gray-300"
                       readonly aria-readonly="true" required>
                <p class="text-xs text-gray-500 mt-1">Auto-filled from your employer profile.</p>
              </div>

              <!-- Location -->
              <div>
                <label for="location" class="block text-sm font-medium text-slate-700 mb-1">Location *</label>
                <input id="location" name="location" type="text"
                       value="<?= h($_POST['location'] ?? '') ?>"
                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
              </div>

              <!-- Type -->
              <div>
                <label for="type" class="block text-sm font-medium text-slate-700 mb-1">Job Type *</label>
                <select id="type" name="type"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                  <?php $t = $_POST['type'] ?? ''; ?>
                  <option value="">Select job type</option>
                  <option <?= $t==='Full Time'?'selected':'' ?>>Full Time</option>
                  <option <?= $t==='Part Time'?'selected':'' ?>>Part Time</option>
                  <option <?= $t==='Internship'?'selected':'' ?>>Internship</option>
                  <option <?= $t==='Remote'?'selected':'' ?>>Remote</option>
                </select>
              </div>

              <!-- Category -->
              <div>
                <label for="category" class="block text-sm font-medium text-slate-700 mb-1">Job Category *</label>
                <select id="category" name="category"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                  <?php
                  $categories = [
                    "Cleaning & Hospitality","Engineering & Constructions","Maintenance","Manufacturing","Hotels & Restaurants",
                    "Transportation","Delivery Service","Helpers","Accounting & Finance","Auto Mobile","Beauty/Salon",
                    "Customer Service / Call Center","Data Management & Analyst","Graphic Designer","Admin & HR",
                    "Sales / Business Development","Secretarial / Front Office","Security Guard","Sports & Fitness",
                    "Travel & Tourism","Medical & Health Care","Media, Art & Entertainment","Marketing & Advertising",
                    "Marine Captain / Crew","Logistics & Distribution","Legal Services","Education","Drivers",
                    "hypermarket","supermarket","Other"
                  ];
                  $selCat = $_POST['category'] ?? '';
                  echo '<option value="">Select category</option>';
                  foreach ($categories as $cat) {
                    $sel = $selCat === $cat ? 'selected' : '';
                    echo '<option value="'.h($cat).'" '.$sel.'>'.h($cat).'</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Compensation -->
          <div>
            <h2 class="text-base font-semibold text-slate-800 mb-4">Compensation</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <!-- Currency -->
              <div class="md:col-span-2">
                <label for="currency" class="block text-sm font-medium text-slate-700 mb-1">Currency *</label>
                <select id="currency" name="currency"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        required onchange="updateCurrencySymbols()">
                  <?php $cur = $_POST['currency'] ?? 'USD';
                  foreach ($currencies as $code=>$info): ?>
                    <option value="<?= h($code) ?>" <?= $cur===$code?'selected':'' ?>>
                      <?= h($info['symbol'].' '.$code.' - '.$info['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Salary: FIXED PREFIX BOX (no alignment shift) -->
              <div>
                <label for="min_salary" class="block text-sm font-medium text-slate-700 mb-1">Minimum Salary *</label>
                <div class="flex">
                  <div id="min-currency-symbol"
                       class="prefix-box inline-flex items-center justify-center px-3 border border-r-0 rounded-l-lg bg-gray-50 text-gray-700 font-semibold select-none">
                    $
                  </div>
                  <input id="min_salary" name="min_salary" type="number" min="0" step="100"
                         value="<?= h($_POST['min_salary'] ?? '') ?>"
                         class="w-full px-3 py-3 border rounded-r-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                         required placeholder="5000">
                </div>
              </div>

              <div>
                <label for="max_salary" class="block text-sm font-medium text-slate-700 mb-1">Maximum Salary *</label>
                <div class="flex">
                  <div id="max-currency-symbol"
                       class="prefix-box inline-flex items-center justify-center px-3 border border-r-0 rounded-l-lg bg-gray-50 text-gray-700 font-semibold select-none">
                    $
                  </div>
                  <input id="max_salary" name="max_salary" type="number" min="0" step="100"
                         value="<?= h($_POST['max_salary'] ?? '') ?>"
                         class="w-full px-3 py-3 border rounded-r-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                         required placeholder="8000">
                </div>
              </div>
            </div>
          </div>

          <!-- Timeline & Logo -->
          <div>
            <h2 class="text-base font-semibold text-slate-800 mb-4">Timeline & Branding</h2>
            <div class="grid grid-cols-1 gap-5">
              <!-- Deadline -->
              <div>
                <label for="deadline" class="block text-sm font-medium text-slate-700 mb-1">Application Deadline *</label>
                <input id="deadline" name="deadline" type="date"
                       value="<?= h($_POST['deadline'] ?? '') ?>"
                       min="<?= h(date('Y-m-d', strtotime('+1 day'))) ?>"
                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
              </div>

              <!-- Logo Uploader -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Company Logo *</label>
                <div id="logoDrop" class="dropzone p-6 flex flex-col items-center justify-center text-center cursor-pointer">
                  <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" required>
                  <div id="logoPreview" class="hidden mb-3">
                    <img id="logoImg" src="" alt="Logo preview" class="h-20 w-20 object-cover rounded-lg border" />
                  </div>
                  <div class="text-gray-600">
                    <p><strong>Click</strong> to upload or drag & drop</p>
                    <p class="text-xs mt-1">JPEG, PNG, GIF, WebP — up to <?= MAX_LOGO_MB ?>MB</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div>
            <h2 class="text-base font-semibold text-slate-800 mb-4">Job Description</h2>
            <div class="relative">
              <textarea id="description" name="description" rows="8" maxlength="4000"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Describe the responsibilities, requirements, and qualifications..." required><?= h($_POST['description'] ?? '') ?></textarea>
              <div class="absolute right-2 bottom-2 text-xs text-gray-500"><span id="descCount">0</span>/4000</div>
            </div>
          </div>

          <!-- Submit -->
          <div>
            <button type="submit" name="submit_job"
                    class="w-full gradient-bg hover:opacity-95 text-white font-bold py-4 rounded-xl transition transform hover:scale-[1.01] focus:outline-none focus:ring-2 focus:ring-indigo-400">
          Post Job & Notify Jobseekers
            </button>
          </div>

          <!-- Debug login state (optional) -->
          <?php if (!empty($debug_user_info)): ?>
            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-900">
              <b>Login Status:</b> <?= h($debug_user_info) ?>
            </div>
          <?php endif; ?>
        </form>
      </section>

      <!-- PREVIEW -->
      <aside class="card p-6">
        <?php if ($posted_job): ?>
          <h2 class="text-base font-semibold text-slate-800 mb-4">Job Preview</h2>
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <?php if (!empty($posted_job['logo'])): ?>
                <img src="<?= h(LOGO_DIR.$posted_job['logo']) ?>" class="h-14 w-14 rounded-xl object-cover border" alt="Logo">
              <?php else: ?>
                <div class="h-14 w-14 rounded-xl bg-gray-100 grid place-items-center text-gray-500">No Logo</div>
              <?php endif; ?>
              <div>
                <div class="text-xl font-bold text-slate-900"><?= h($posted_job['title']) ?></div>
                <div class="text-sm text-gray-600"><?= h($posted_job['company_name']) ?> • <?= h($posted_job['location']) ?></div>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <span class="px-2 py-1 text-xs rounded-full bg-indigo-50 text-indigo-700"><?= h($posted_job['type']) ?></span>
              <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700"><?= h($posted_job['category']) ?></span>
            </div>

            <div class="text-sm text-slate-800">
              <span class="font-medium">Salary:</span>
              <?php
                $sym = $currencies[$posted_job['currency']]['symbol'] ?? '$';
                echo ' '.h($sym).number_format((float)$posted_job['min_salary']).' - '.h($sym).number_format((float)$posted_job['max_salary']).' '.h($posted_job['currency']);
              ?>
            </div>

            <div>
              <div class="text-sm font-semibold text-slate-800 mb-1">Job Description</div>
              <div class="prose prose-sm max-w-none text-gray-800"><?= nl2br(h($posted_job['description'])) ?></div>
            </div>

            <div class="pt-3 border-t text-xs text-gray-500">
              Posted on <?= h(date('F j, Y')) ?> • Apply before <?= h(date('F j, Y', strtotime($posted_job['deadline']))) ?>
            </div>

            <div class="flex flex-wrap gap-2">
              <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Apply Now</button>
              <button class="px-4 py-2 rounded-lg bg-gray-100 text-sm font-semibold hover:bg-gray-200">Save</button>
              <button class="px-4 py-2 rounded-lg bg-gray-100 text-sm font-semibold hover:bg-gray-200">Share</button>
            </div>
          </div>
        <?php else: ?>
          <div class="text-center py-10">
            <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V6a2 2 0 012 2v6M8 6v10a2 2 0 002 2h4a2 2 0 002-2V6" />
            </svg>
            <div class="text-gray-700 font-medium mb-1">No Job Posted Yet</div>
            <div class="text-gray-500 text-sm">Post a job to see its preview here.</div>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </main>

  <script>
    // Back navigation
    function goBack(){
      if (document.referrer && document.referrer !== location.href) history.back();
      else window.location.href = 'company.php';
    }

    // Currency symbol live update — uses fixed-width prefix to avoid layout shift
    function updateCurrencySymbols(){
      const map = { USD:'$', QAR:'QAR', AED:'AED', EUR:'€', SAR:'SAR', OMR:'OMR', KWD:'KWD', BHD:'BHD', MYR:'RM' };
      const sel = document.getElementById('currency').value || 'USD';
      const sym = map[sel] || '$';
      document.getElementById('min-currency-symbol').textContent = sym;
      document.getElementById('max-currency-symbol').textContent = sym;
    }

    // Character counters
    const titleEl = document.getElementById('title');
    const descEl = document.getElementById('description');
    const titleCount = document.getElementById('titleCount');
    const descCount = document.getElementById('descCount');

    function bindCounters(){
      if (titleEl && titleCount) {
        titleEl.addEventListener('input', ()=> titleCount.textContent = titleEl.value.length);
        titleCount.textContent = titleEl.value.length;
      }
      if (descEl && descCount) {
        descEl.addEventListener('input', ()=> descCount.textContent = descEl.value.length);
        descCount.textContent = descEl.value.length;
      }
    }

    // Drag-and-drop logo
    const drop = document.getElementById('logoDrop');
    const input = document.getElementById('logo');
    const previewWrap = document.getElementById('logoPreview');
    const img = document.getElementById('logoImg');

    function showPreview(file){
      const okTypes = ['image/jpeg','image/png','image/gif','image/webp'];
      const maxBytes = <?= (int)(MAX_LOGO_MB * 1024 * 1024) ?>;
      if (!okTypes.includes(file.type)) { alert('Invalid file type. Use JPEG/PNG/GIF/WebP.'); return; }
      if (file.size > maxBytes) { alert('File too large. Max <?= MAX_LOGO_MB ?>MB.'); return; }
      const url = URL.createObjectURL(file);
      img.src = url;
      previewWrap.classList.remove('hidden');
    }
    function wireDrop(){
      drop.addEventListener('click', ()=> input.click());
      drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('drag'); });
      drop.addEventListener('dragleave', ()=> drop.classList.remove('drag'));
      drop.addEventListener('drop', e => {
        e.preventDefault(); drop.classList.remove('drag');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          input.files = e.dataTransfer.files;
          showPreview(e.dataTransfer.files[0]);
        }
      });
      input.addEventListener('change', e => {
        if (e.target.files && e.target.files[0]) showPreview(e.target.files[0]);
      });
    }

    document.addEventListener('DOMContentLoaded', ()=>{
      updateCurrencySymbols();
      bindCounters();
      wireDrop();
    });
  </script>
</body>
</html>
