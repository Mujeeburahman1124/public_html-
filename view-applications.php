<?php
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
 * MSJOBS — Employer Applications (Back btn + Message Popup)
 * File: view-applications.php
 ************************************************************/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
session_start();

/* ====================== CONFIG ======================= */
// const DB_HOST = '127.0.0.1:3306'; (Refactored to config.php)
// const DB_USER = 'u903588615_root'; (Refactored to config.php)
// const DB_PASS = 'Msjobs#1'; (Refactored to config.php)
// const DB_NAME = 'u903588615_exaple'; (Refactored to config.php)

const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'mshrc936@gmail.com';
const SMTP_PASS = 'nmspuxcjuptondkd'; // Gmail App Password
const SMTP_FROM = 'mshrc936@gmail.com';
const SMTP_FROMNAME = 'MS JOBS HR';

const PUBLIC_BASE_URL = 'https://msjobs.net/'; // change if different
const CV_DIR_PUBLIC   = 'uploads/cvs/';        // relative url path
const PAGE_SIZE       = 20;

/* ================ SESSION GUARD ======================= */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$companyId = (int)$_SESSION['user_id'];

/* ================ DB CONNECT (strict) ================ */
ini_set('display_errors','0');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  error_log("DB connect failed: ".$e->getMessage());
  http_response_code(500);
  die("Service temporarily unavailable.");
}

/* ================= CSRF TOKEN ======================== */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];
function require_csrf() {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Invalid CSRF token.");
  }
}
function require_csrf_get() {
  if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
    http_response_code(403);
    die("Invalid CSRF token.");
  }
}

/* ================= HELPERS =========================== */
function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function safeResumeUrl($resume) {
  if (!$resume) return null;
  $rel = CV_DIR_PUBLIC . ltrim($resume, '/');
  return [ $rel, PUBLIC_BASE_URL . $rel ];
}
function fileExt($path) { return strtolower(pathinfo($path, PATHINFO_EXTENSION)); }

/* ================== ACTIONS ========================== */
/* Select + send custom email */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_custom_message'])) {
  require_csrf();

  $appId = (int)($_POST['application_id'] ?? 0);
  $customMessage = trim((string)($_POST['custom_message'] ?? ''));

  $q = $db->prepare("SELECT id, email, name, company_id FROM applications WHERE id = ?");
  $q->bind_param("i", $appId);
  $q->execute();
  $app = $q->get_result()->fetch_assoc();
  $q->close();

  if ($app && (int)$app['company_id'] === $companyId && $customMessage !== '') {
    $u = $db->prepare("UPDATE applications SET status='Selected' WHERE id=?");
    $u->bind_param("i", $appId);
    $u->execute();
    $u->close();

    $mail = new PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host = SMTP_HOST;
      $mail->SMTPAuth = true;
      $mail->Username = SMTP_USER;
      $mail->Password = SMTP_PASS;
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = SMTP_PORT;

      $mail->setFrom(SMTP_FROM, SMTP_FROMNAME);
      $mail->addAddress((string)$app['email'], (string)$app['name']);
      $mail->isHTML(true);
      $mail->Subject = "Your Application Update — MS JOBS";

      $html = "<html><body style='font-family:Segoe UI,Arial,sans-serif'>
        <h2 style='margin:0 0 10px'>Dear ".h($app['name']).",</h2>
        <p style='white-space:pre-wrap; line-height:1.6'>".nl2br(h($customMessage))."</p>
        <p style='margin-top:16px'>Best regards,<br><strong>MS JOBS HR Team</strong></p>
      </body></html>";
      $mail->Body = $html;
      $mail->send();
    } catch (Exception $e) {
      error_log("Mailer error: ".$mail->ErrorInfo);
    }
  }
  header("Location: view-applications.php?status=".urlencode($_GET['status'] ?? '')."&q=".urlencode($_GET['q'] ?? '')."&page=".urlencode($_GET['page'] ?? 1));
  exit;
}

/* Reject */
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'reject') {
  require_csrf_get();
  $appId = (int)$_GET['id'];

  $q = $db->prepare("SELECT id, company_id FROM applications WHERE id=?");
  $q->bind_param("i", $appId);
  $q->execute();
  $app = $q->get_result()->fetch_assoc();
  $q->close();

  if ($app && (int)$app['company_id'] === $companyId) {
    $u = $db->prepare("UPDATE applications SET status='Rejected' WHERE id=?");
    $u->bind_param("i", $appId);
    $u->execute();
    $u->close();
  }
  header("Location: view-applications.php?status=".urlencode($_GET['status'] ?? '')."&q=".urlencode($_GET['q'] ?? '')."&page=".urlencode($_GET['page'] ?? 1));
  exit;
}

/* ================== FILTERS & PAGING ================= */
$status = $_GET['status'] ?? 'all'; // all|Pending|Selected|Rejected
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PAGE_SIZE;

/* ================== COUNTS =========================== */
$counts = ['total'=>0,'Pending'=>0,'Selected'=>0,'Rejected'=>0];

$stmt = $db->prepare("SELECT COUNT(*) as c FROM applications WHERE company_id=?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$counts['total'] = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

foreach (['Pending','Selected','Rejected'] as $st) {
  $s = $db->prepare("SELECT COUNT(*) as c FROM applications WHERE company_id=? AND status=?");
  $s->bind_param("is", $companyId, $st);
  $s->execute();
  $counts[$st] = (int)$s->get_result()->fetch_assoc()['c'];
  $s->close();
}

/* ============== QUERY LIST (with filters) ============ */
$where = "a.company_id = ?";
$params = [$companyId];
$types  = "i";

if (in_array($status, ['Pending','Selected','Rejected'], true)) {
  $where .= " AND a.status = ?";
  $params[] = $status;
  $types   .= "s";
}
if ($search !== '') {
  $where .= " AND (a.name LIKE CONCAT('%',?,'%') OR a.email LIKE CONCAT('%',?,'%'))";
  $params[] = $search; $params[] = $search;
  $types   .= "ss";
}

/* Count for pagination */
$sqlCount = "SELECT COUNT(*) AS c FROM applications a WHERE $where";
$cstmt = $db->prepare($sqlCount);
$cstmt->bind_param($types, ...$params);
$cstmt->execute();
$totalRows = (int)$cstmt->get_result()->fetch_assoc()['c'];
$cstmt->close();

$totalPages = max(1, (int)ceil($totalRows / PAGE_SIZE));

/* Actual fetch */
$sql = "SELECT a.*, j.title AS job_title
        FROM applications a
        LEFT JOIN jobs j ON a.job_id = j.id
        WHERE $where
        ORDER BY a.applied_at DESC
        LIMIT ? OFFSET ?";
$params2 = $params;
$types2  = $types . "ii";
$params2[] = PAGE_SIZE;
$params2[] = $offset;

$qstmt = $db->prepare($sql);
$qstmt->bind_param($types2, ...$params2);
$qstmt->execute();
$list = $qstmt->get_result();

/* ==================== HTML ========================== */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Applications — MSJOBS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .pill { @apply px-3 py-1.5 rounded-full text-sm font-medium border transition; }
    .pill-active { @apply bg-slate-900 text-white border-slate-900; }
    .pill-inactive { @apply bg-white text-slate-700 border-slate-200 hover:bg-slate-50; }
  </style>
</head>
<body class="bg-slate-50 min-h-screen">
  <!-- Top Bar -->
  <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button onclick="handleBack()" class="rounded-xl border border-slate-300 px-3 py-1.5 hover:bg-slate-50">
          ← Back
        </button>
        <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-500 grid place-items-center text-white font-bold">MS</div>
        <h1 class="text-lg sm:text-xl font-semibold tracking-tight">Employer Applications</h1>
      </div>
      <div class="hidden md:flex items-center gap-6 text-sm text-slate-600">
        <div>Total <span class="font-semibold"><?= $counts['total'] ?></span></div>
        <div class="text-emerald-700">Selected <span class="font-semibold"><?= $counts['Selected'] ?></span></div>
        <div class="text-amber-700">Pending <span class="font-semibold"><?= $counts['Pending'] ?></span></div>
        <div class="text-rose-700">Rejected <span class="font-semibold"><?= $counts['Rejected'] ?></span></div>
      </div>
    </div>
  </header>

  <!-- Content -->
  <main class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <?php
            $tabs = [
              'all'      => 'All',
              'Pending'  => 'Pending',
              'Selected' => 'Selected',
              'Rejected' => 'Rejected',
            ];
            foreach ($tabs as $key=>$label):
              $active = ($status === $key) || ($key==='all' && !in_array($status,['Pending','Selected','Rejected'],true));
              $link = "view-applications.php?status=".urlencode($key)."&q=".urlencode($search)."&page=1";
          ?>
            <a href="<?= $link ?>" class="pill <?= $active ? 'pill-active' : 'pill-inactive' ?>"><?= h($label) ?></a>
          <?php endforeach; ?>
        </div>
        <form method="get" class="flex items-stretch gap-2">
          <input type="hidden" name="status" value="<?= h($status) ?>">
          <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search name or email..."
                 class="w-64 max-w-full rounded-xl border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
          <button class="px-4 rounded-xl bg-slate-900 text-white">Search</button>
        </form>
      </div>
    </div>

    <!-- Table (desktop) / Cards (mobile) -->
    <div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-200">
      <!-- Desktop table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-900 text-white">
            <tr>
              <th class="px-4 py-3 text-left">Job</th>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-left">WhatsApp</th>
              <th class="px-4 py-3 text-left">Resume</th>
              <th class="px-4 py-3 text-left">Message</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Applied</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
          <?php while ($row = $list->fetch_assoc()): ?>
            <?php
              [$rel,$abs] = safeResumeUrl($row['resume']) ?? [null,null];
              $ext = $rel ? fileExt($rel) : null;
              $wa = $row['whatsapp'] ? onlyDigits($row['whatsapp']) : '';
              $msg = $row['message'] ?? '';
            ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3"><?= h($row['job_title'] ?? '—') ?></td>
              <td class="px-4 py-3"><?= h($row['name']) ?></td>
              <td class="px-4 py-3"><?= h($row['email']) ?></td>
              <td class="px-4 py-3">
                <?php if ($wa): ?>
                  <a class="text-emerald-700 hover:underline" target="_blank" href="https://wa.me/<?= h($wa) ?>">Message</a>
                <?php else: ?><span class="text-slate-400">N/A</span><?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <?php if ($rel): ?>
                  <div class="space-y-1">
                    <a href="<?= h($rel) ?>" class="text-sky-700 hover:underline" target="_blank">Download</a>
                    <?php if (in_array($ext, ['pdf','doc','docx'], true)): ?>
                      <button onclick="previewCV('<?= h($abs) ?>','<?= h($ext) ?>')" class="text-sky-600 text-xs hover:underline block">Preview</button>
                    <?php endif; ?>
                  </div>
                <?php else: ?><span class="text-slate-400">No Resume</span><?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <?php if ($msg !== ''): ?>
                  <button
                    class="px-3 py-1.5 rounded bg-slate-900 text-white text-xs"
                    onclick="openMsgModal('<?= h($row['name']) ?>','<?= h($row['email']) ?>', `<?= nl2br(h($msg)) ?>`,'<?= h($row['job_title'] ?? '—') ?>')"
                  >View</button>
                <?php else: ?><span class="text-slate-400">No Message</span><?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <?php if ($row['status'] === 'Pending'): ?>
                  <span class="bg-amber-100 text-amber-800 px-2 py-1 rounded text-xs">Pending</span>
                <?php elseif ($row['status'] === 'Selected'): ?>
                  <span class="bg-emerald-100 text-emerald-800 px-2 py-1 rounded text-xs">Selected</span>
                <?php else: ?>
                  <span class="bg-rose-100 text-rose-800 px-2 py-1 rounded text-xs">Rejected</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3"><?= h(date('Y-m-d', strtotime($row['applied_at'] ?? 'now'))) ?></td>
              <td class="px-4 py-3 space-y-2 w-40">
                <?php if ($row['status'] === 'Pending'): ?>
                  <button class="w-full bg-slate-900 hover:bg-black text-white text-xs px-3 py-1.5 rounded" onclick="openSelectModal(<?= (int)$row['id'] ?>)">Select</button>
                  <a class="block w-full text-center bg-rose-600 hover:bg-rose-700 text-white text-xs px-3 py-1.5 rounded"
                     href="?action=reject&id=<?= (int)$row['id'] ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>&page=<?= (int)$page ?>&csrf=<?= h($CSRF) ?>"
                     onclick="return confirm('Reject this applicant?');">Reject</a>
                <?php else: ?>
                  <span class="text-slate-400 text-xs">No actions</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile Cards -->
      <div class="md:hidden divide-y">
        <?php
          $list->data_seek(0);
          while ($row = $list->fetch_assoc()):
            [$rel,$abs] = safeResumeUrl($row['resume']) ?? [null,null];
            $ext = $rel ? fileExt($rel) : null;
            $wa = $row['whatsapp'] ? onlyDigits($row['whatsapp']) : '';
            $msg = $row['message'] ?? '';
        ?>
        <div class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-sm text-slate-500"><?= h($row['job_title'] ?? '—') ?></div>
              <div class="text-base font-semibold"><?= h($row['name']) ?></div>
            </div>
            <div>
              <?php if ($row['status'] === 'Pending'): ?>
                <span class="bg-amber-100 text-amber-800 px-2 py-1 rounded text-xs">Pending</span>
              <?php elseif ($row['status'] === 'Selected'): ?>
                <span class="bg-emerald-100 text-emerald-800 px-2 py-1 rounded text-xs">Selected</span>
              <?php else: ?>
                <span class="bg-rose-100 text-rose-800 px-2 py-1 rounded text-xs">Rejected</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="mt-2 text-sm text-slate-600">
            <div><span class="font-medium">Email:</span> <?= h($row['email']) ?></div>
            <div><span class="font-medium">WhatsApp:</span>
              <?php if ($wa): ?><a class="text-emerald-700" target="_blank" href="https://wa.me/<?= h($wa) ?>">Open</a><?php else: ?><span class="text-slate-400">N/A</span><?php endif; ?>
            </div>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <?php if ($rel): ?>
              <a class="px-3 py-1.5 rounded bg-sky-50 text-sky-700 border border-sky-200" target="_blank" href="<?= h($rel) ?>">Download CV</a>
              <?php if (in_array($ext, ['pdf','doc','docx'], true)): ?>
                <button onclick="previewCV('<?= h($abs) ?>','<?= h($ext) ?>')" class="px-3 py-1.5 rounded bg-sky-600 text-white">Preview</button>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-slate-400">No CV</span>
            <?php endif; ?>

            <span class="ml-auto text-xs text-slate-500"><?= h(date('Y-m-d', strtotime($row['applied_at'] ?? 'now'))) ?></span>
          </div>

          <div class="mt-3 flex gap-2">
            <?php if ($msg !== ''): ?>
              <button class="flex-1 rounded border px-3 py-2" onclick="openMsgModal('<?= h($row['name']) ?>','<?= h($row['email']) ?>', `<?= nl2br(h($msg)) ?>`,'<?= h($row['job_title'] ?? '—') ?>')">View Message</button>
            <?php endif; ?>
            <?php if ($row['status'] === 'Pending'): ?>
              <button class="flex-1 rounded bg-slate-900 text-white py-2" onclick="openSelectModal(<?= (int)$row['id'] ?>)">Select</button>
              <a class="flex-1 rounded bg-rose-600 text-white py-2 text-center"
                 href="?action=reject&id=<?= (int)$row['id'] ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>&page=<?= (int)$page ?>&csrf=<?= h($CSRF) ?>"
                 onclick="return confirm('Reject this applicant?');">Reject</a>
            <?php else: ?>
              <span class="text-slate-400 text-xs">No actions</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="mt-6 flex items-center justify-center gap-2">
        <?php
          $base = "view-applications.php?status=".urlencode($status)."&q=".urlencode($search);
          $prev = max(1, $page-1);
          $next = min($totalPages, $page+1);
        ?>
        <a class="px-3 py-1.5 rounded border <?= $page===1 ? 'text-slate-400 border-slate-200 pointer-events-none' : 'hover:bg-slate-50' ?>"
           href="<?= $base."&page=".$prev ?>">Prev</a>
        <span class="text-sm text-slate-600">Page <strong><?= (int)$page ?></strong> of <strong><?= (int)$totalPages ?></strong></span>
        <a class="px-3 py-1.5 rounded border <?= $page===$totalPages ? 'text-slate-400 border-slate-200 pointer-events-none' : 'hover:bg-slate-50' ?>"
           href="<?= $base."&page=".$next ?>">Next</a>
      </div>
    <?php endif; ?>
  </main>

  <!-- Select + Custom Email Modal -->
  <div id="selectModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-xl w-[95%] max-w-xl p-6 relative">
      <button onclick="closeSelectModal()" class="absolute top-3 right-4 text-3xl leading-none text-slate-500 hover:text-black">&times;</button>
      <h2 class="text-lg font-semibold">Send Custom Message</h2>
      <p class="text-sm text-slate-600 mt-1">Selecting will update status to <span class="font-semibold">Selected</span> and send this email.</p>
      <form method="POST" class="mt-4 space-y-3">
        <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
        <input type="hidden" name="application_id" id="select_app_id">
        <textarea name="custom_message" id="custom_message" rows="8" required
                  placeholder="Type your message…"
                  class="w-full rounded-xl border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 p-3"></textarea>

        <div class="flex flex-wrap gap-2 text-xs">
          <button type="button" class="px-2 py-1 rounded bg-slate-100" onclick="insertTemplate('shortlist')">Insert: Shortlist</button>
          <button type="button" class="px-2 py-1 rounded bg-slate-100" onclick="insertTemplate('interview')">Insert: Interview</button>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" onclick="closeSelectModal()" class="rounded-xl border px-4 py-2">Cancel</button>
          <button type="submit" name="send_custom_message" class="rounded-xl bg-slate-900 text-white px-4 py-2">Send</button>
        </div>
      </form>
    </div>
  </div>

  <!-- CV Preview Modal -->
  <div id="cvPreviewModal" class="fixed inset-0 bg-black/60 hidden z-50 items-center justify-center">
    <div class="bg-white w-11/12 max-w-5xl h-[90vh] rounded-2xl overflow-hidden relative shadow-2xl border border-slate-200">
      <button onclick="closeCVPreview()" class="absolute top-3 right-4 text-3xl font-bold text-slate-500 hover:text-black">&times;</button>
      <iframe id="cvFrame" class="w-full h-full border-0"></iframe>
    </div>
  </div>

  <!-- Message View Modal -->
  <div id="msgModal" class="fixed inset-0 bg-black/60 hidden z-50 items-center justify-center">
    <div class="bg-white w-[95%] max-w-xl rounded-2xl overflow-hidden shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between px-5 py-4 border-b">
        <div>
          <div id="msgModalTitle" class="text-base font-semibold">Message</div>
          <div id="msgModalSub" class="text-xs text-slate-500"></div>
        </div>
        <button onclick="closeMsgModal()" class="text-3xl leading-none px-2 text-slate-500 hover:text-black">&times;</button>
      </div>
      <div class="p-5">
        <div id="msgModalBody" class="prose prose-sm max-w-none text-slate-800"></div>
      </div>
      <div class="px-5 py-4 border-t flex justify-end">
        <button onclick="closeMsgModal()" class="rounded-xl border px-4 py-2">Close</button>
      </div>
    </div>
  </div>

  <script>
    /* Back button with graceful fallback */
    function handleBack(){
      if (document.referrer && document.referrer !== location.href) {
        history.back();
      } else {
        // fallback: go to a dashboard if you have one
        window.location.href = 'employer-dashboard.php';
      }
    }

    function openSelectModal(id){
      document.getElementById('select_app_id').value = id;
      const m = document.getElementById('selectModal');
      m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeSelectModal(){
      const m = document.getElementById('selectModal');
      m.classList.add('hidden'); m.classList.remove('flex');
      document.getElementById('select_app_id').value = '';
    }
    function insertTemplate(type){
      const box = document.getElementById('custom_message');
      const name = 'Candidate';
      let txt = '';
      if (type === 'shortlist') {
        txt = `Dear ${name},

We are pleased to inform you that your profile has been shortlisted for the role. Our team will contact you with next steps shortly.

Best regards,
MS JOBS HR`;
      } else if (type === 'interview') {
        txt = `Dear ${name},

Congratulations! You have been selected for the interview round. We will send date/time and location/meeting link soon.

Best regards,
MS JOBS HR`;
      }
      box.value = txt;
      box.focus();
    }

    function previewCV(url, ext) {
      let viewerUrl = '';
      if (ext === 'pdf') {
        viewerUrl = 'https://docs.google.com/gview?url=' + encodeURIComponent(url) + '&embedded=true';
      } else if (ext === 'doc' || ext === 'docx') {
        viewerUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(url);
      } else {
        alert('Unsupported file type for preview.');
        return;
      }
      const frame = document.getElementById('cvFrame');
      frame.src = viewerUrl;
      const modal = document.getElementById('cvPreviewModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
    function closeCVPreview() {
      const modal = document.getElementById('cvPreviewModal');
      document.getElementById('cvFrame').src = '';
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    /* Message popup */
    function openMsgModal(name, email, msgHtml, jobTitle){
      document.getElementById('msgModalTitle').textContent = jobTitle ? `Message — ${jobTitle}` : 'Message';
      document.getElementById('msgModalSub').textContent = `${name} • ${email}`;
      document.getElementById('msgModalBody').innerHTML = msgHtml || '<span class="text-slate-400">No message</span>';
      const m = document.getElementById('msgModal');
      m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeMsgModal(){
      const m = document.getElementById('msgModal');
      m.classList.add('hidden'); m.classList.remove('flex');
      document.getElementById('msgModalBody').innerHTML = '';
    }
  </script>
</body>
</html>
<?php
$qstmt->close();
$db->close();
