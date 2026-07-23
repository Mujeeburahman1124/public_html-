<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$DB_HOST = $servername;
$DB_USER = $username;
$DB_PASS = $password;
$DB_NAME = $dbname;
$database = $dbname;

/************************************************************
 * MSJOBS — Apply (Responsive + Premium UI)
 * - Saves company_id
 * - Auto-fetches company name from job or company tables
 * - Safe file upload (PDF/DOC/DOCX, ≤ 5MB)
 ************************************************************/
session_start();

/* ===== DB CONFIG ===== */
$DB_HOST = "127.0.0.1:3306";
// $DB_USER = "u903588615_root"; (Refactored to config.php)
// $DB_PASS = "Msjobs#1"; (Refactored to config.php)
// $DB_NAME = "u903588615_exaple"; (Refactored to config.php)

/* ===== UPLOAD CONFIG ===== */
$UPLOAD_DIR           = __DIR__ . "/uploads/cvs";
$UPLOAD_PUBLIC_PREFIX = "uploads/cvs";
$MAX_BYTES            = 5 * 1024 * 1024; // 5MB
$ALLOWED_EXT          = ['pdf','doc','docx'];
$ALLOWED_MIME         = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

/* ===== DB CONNECT ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  die("Database connection failed.");
}

/* ===== HELPERS ===== */
function ensure_uploads_dir(string $dir): void {
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (!is_dir($dir) || !is_writable($dir)) {
    http_response_code(500);
    die("Uploads folder not writable: {$dir}");
  }
}
function safe_filename(string $name): string {
  return preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name);
}
function fetch_company_name(mysqli $conn, ?int $company_id, ?array $job): ?string {
  // 1) If job row has company_name field
  if ($job && array_key_exists('company_name', $job) && !empty($job['company_name'])) {
    return (string)$job['company_name'];
  }
  if (!$company_id) return null;

  // 2) Try company_info.name
  try {
    $stmt = $conn->prepare("SELECT `name` FROM `company_info` WHERE `id`=? LIMIT 1");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($r['name'])) return (string)$r['name'];
  } catch (Throwable $e) { /* table may not exist */ }

  // 3) Try employers.company_name
  try {
    $stmt = $conn->prepare("SELECT `company_name` FROM `employers` WHERE `id`=? LIMIT 1");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($r['company_name'])) return (string)$r['company_name'];
  } catch (Throwable $e) { /* table may not exist */ }

  return null;
}

/* ===== READ GET ===== */
$job_id_in     = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
$company_id_in = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

/* ===== FETCH JOB ===== */
$job = null;
if ($job_id_in) {
  $stmt = $conn->prepare("SELECT * FROM `jobs` WHERE `id`=?");
  $stmt->bind_param("i", $job_id_in);
  $stmt->execute();
  $job = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

/* ===== DETERMINE company_id ===== */
$company_id_effective = null;
if ($job && array_key_exists('company_id', $job) && !empty($job['company_id'])) {
  $company_id_effective = (int)$job['company_id'];
} elseif ($company_id_in) {
  $company_id_effective = $company_id_in;
}

/* ===== COMPANY NAME (for display) ===== */
$company_name = fetch_company_name($conn, $company_id_effective, $job);

/* ===== HANDLE POST (SUBMIT) ===== */
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $job_id    = (int)($_POST['job_id'] ?? 0);
  $job_title = trim((string)($_POST['job_title'] ?? ''));
  $name      = trim((string)($_POST['name'] ?? ''));
  $email     = trim((string)($_POST['email'] ?? ''));
  $message   = trim((string)($_POST['message'] ?? ''));
  $whatsapp  = trim((string)($_POST['whatsapp'] ?? ''));
  // If you need real user_id from auth; else store 0
  $user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

  $company_id_hidden = isset($_POST['company_id']) ? (int)$_POST['company_id'] : null;
  $company_id_final  = $company_id_effective ?? $company_id_hidden ?? 0;

  // Validate fields
  if ($job_id <= 0)                         $error = "Invalid job.";
  elseif ($name === '')                     $error = "Name is required.";
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Valid email is required.";
  elseif (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) $error = "Please attach your resume.";

  $resume_public_path = null;
  $resume_original    = null;
  $resume_legacy      = null;

  if ($error === '') {
    ensure_uploads_dir($UPLOAD_DIR);

    $f    = $_FILES['resume'];
    $size = (int)$f['size'];
    $tmp  = $f['tmp_name'];
    $resume_original = basename((string)$f['name']);

    if ($size <= 0 || $size > $MAX_BYTES) {
      $error = "Resume must be > 0 and ≤ 5MB.";
    } else {
      $ext = strtolower(pathinfo($resume_original, PATHINFO_EXTENSION));
      if (!in_array($ext, $ALLOWED_EXT, true)) {
        $error = "Only PDF, DOC, or DOCX files are allowed.";
      } else {
        // MIME check (if finfo available)
        $detected = '';
        if (class_exists('finfo')) {
          $finfo = new finfo(FILEINFO_MIME_TYPE);
          $detected = $finfo->file($tmp) ?: '';
        }
        if ($detected && !in_array($detected, $ALLOWED_MIME, true)) {
          $error = "File type not allowed (detected: {$detected}).";
        } else {
          $safeBase = safe_filename(pathinfo($resume_original, PATHINFO_FILENAME));
          $unique   = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $destFs   = rtrim($UPLOAD_DIR, '/').'/'.$unique;
          $destWeb  = rtrim($UPLOAD_PUBLIC_PREFIX, '/').'/'.$unique;

          if (!move_uploaded_file($tmp, $destFs)) {
            $error = "Failed to save the uploaded resume.";
          } else {
            $resume_public_path = $destWeb;
            $resume_legacy      = basename($resume_public_path); // for legacy `resume` column
          }
        }
      }
    }
  }

  if ($error === '') {
    // Insert (keeps legacy resume column)
    $stmt = $conn->prepare(
      "INSERT INTO `applications`
       (`job_id`,`user_id`,`job_title`,`name`,`email`,`resume`,`whatsapp`,`message`,`company_id`)
       VALUES (?,?,?,?,?,?,?,?,?)"
    );
    // i i s s s s s s i
    $stmt->bind_param(
      "iissssssi",
      $job_id,
      $user_id,
      $job_title,
      $name,
      $email,
      $resume_legacy,
      $whatsapp,
      $message,
      $company_id_final
    );
    $stmt->execute();
    $stmt->close();

    $success = true;
  }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Apply for Job</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: "#4f46e5", brandAlt: "#7c3aed" },
          boxShadow: { glow: "0 10px 30px rgba(79,70,229,.25)" }
        }
      }
    };
  </script>
  <style>
    .glass { background: rgba(255,255,255,0.9); backdrop-filter: blur(8px); }
    input[type="file"]::file-selector-button { cursor: pointer; }
  </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen p-4 sm:p-6">
  <div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="glass rounded-3xl shadow-xl border border-slate-100 p-6 sm:p-8 mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
            Apply for:
            <span class="text-brand">
              <?= htmlspecialchars($job['title'] ?? 'Job Not Found') ?>
            </span>
          </h1>
          <div class="mt-2 text-sm text-slate-600 flex flex-wrap gap-2">
            <?php if ($company_name): ?>
              <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand/10 text-brand font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zM3 9h2V7H3v2zm4 8h2v-2H7v2zm0-4h2v-2H7v2zM7 9h2V7H7v2zm4 8h2v-2h-2v2zm0-4h2v-2h-2v2zM11 9h2V7h-2v2zm4 8h6V3H15v14zm2-12h2v2h-2V5zm0 4h2v2h-2V9zm0 4h2v2h-2v-2z"/></svg>
                <?= htmlspecialchars($company_name) ?>
                <?php if (!empty($company_id_effective)): ?>
                  <span class="text-slate-400">(#<?= (int)$company_id_effective ?>)</span>
                <?php endif; ?>
              </span>
            <?php elseif (!empty($company_id_effective)): ?>
              <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                Company ID: #<?= (int)$company_id_effective ?>
              </span>
            <?php endif; ?>

            <?php if (!empty($job['location'])): ?>
              <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                <?= htmlspecialchars($job['location']) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
        <a href="javascript:history.back()" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:text-brandAlt transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M10 19l-7-7 7-7v4h8v6h-8v4z"/></svg>
          Back
        </a>
      </div>

      <?php if (!$job): ?>
        <div class="mt-4 p-4 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800 font-medium">
          Job not found.
        </div>
      <?php endif; ?>
    </div>

    <!-- Notifications -->
    <?php if ($success): ?>
      <div class="mb-6 p-4 rounded-2xl border bg-green-50 border-green-200 text-green-800 font-semibold shadow">
        🎉 Application submitted successfully!
      </div>
    <?php elseif (!empty($error)): ?>
      <div class="mb-6 p-4 rounded-2xl border bg-red-50 border-red-200 text-red-800 font-semibold shadow">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <?php if ($job): ?>
      <form method="POST" enctype="multipart/form-data" class="glass rounded-3xl shadow-xl border border-slate-100 p-6 sm:p-8 space-y-6">
        <input type="hidden" name="job_id" value="<?= (int)($job['id'] ?? 0) ?>">
        <input type="hidden" name="job_title" value="<?= htmlspecialchars($job['title'] ?? '') ?>">
        <input type="hidden" name="company_id" value="<?= (int)($company_id_effective ?? 0) ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-2">Full Name <span class="text-red-500">*</span></label>
            <input required type="text" id="name" name="name" placeholder="Your full name"
                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/60 focus:border-brand/60 transition" />
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-2">Email <span class="text-red-500">*</span></label>
            <input required type="email" id="email" name="email" placeholder="you@example.com"
                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/60 focus:border-brand/60 transition" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label for="whatsapp" class="block text-sm font-medium text-slate-700 mb-2">WhatsApp Number</label>
            <input type="tel" id="whatsapp" name="whatsapp" placeholder="+9477xxxxxxx"
                   class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/60 focus:border-brand/60 transition" />
          </div>
          <div>
            <label for="resume" class="block text-sm font-medium text-slate-700 mb-2">Resume (PDF / DOC / DOCX) <span class="text-red-500">*</span></label>
            <input required type="file" id="resume" name="resume" accept=".pdf,.doc,.docx"
                   class="w-full text-slate-700 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand/10 file:text-brand hover:file:bg-brand/15 focus:outline-none focus:ring-2 focus:ring-brand/60 focus:border-brand/60 transition" />
            <p class="text-xs text-slate-500 mt-2">Max size 5MB. Only PDF, DOC or DOCX.</p>
          </div>
        </div>

        <div>
          <label for="message" class="block text-sm font-medium text-slate-700 mb-2">Message</label>
          <textarea id="message" name="message" rows="4" placeholder="Write a brief message..."
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/60 focus:border-brand/60 transition"></textarea>
        </div>

        <button type="submit"
                class="w-full sm:w-auto inline-flex justify-center items-center gap-2 bg-gradient-to-r from-brand to-brandAlt text-white font-semibold px-6 py-3 rounded-xl hover:opacity-95 active:opacity-90 transition shadow-glow">
          Submit Application
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 12l18-9-4 18-7-7-7-2z"/></svg>
        </button>

        <p class="text-xs text-slate-500 pt-2">By submitting, you agree to our terms and privacy policy.</p>
      </form>
    <?php endif; ?>

    <div class="text-center text-xs text-slate-500 mt-8">© <?= date('Y') ?> MSJOBS. All rights reserved.</div>
  </div>
</body>
</html>
