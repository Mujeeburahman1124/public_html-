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

/**************************************************************
 * MSJOBS — Jobseeker Database (Premium UI + View Limits)
 * - Sticky gradient header with Back/Home
 * - Collapsible filters (mobile), datalist for positions
 * - Modern list → profile viewer (CV preview + download)
 * - View limits per employer (unique views counted)
 **************************************************************/
session_start();

/* ===== Session guard ===== */
if (!isset($_SESSION['company_logged_in']) || $_SESSION['company_logged_in'] !== true) {
    echo "<script>alert('Please log in to access this page.'); window.location.href='login.php';</script>";
    exit();
}

/* ===== DB config ===== */
// const DB_HOST = '127.0.0.1:3306'; (Refactored to config.php)
// const DB_USER = 'u903588615_root'; (Refactored to config.php)
// const DB_PASS = 'Msjobs#1'; (Refactored to config.php)
// const DB_NAME = 'u903588615_exaple'; (Refactored to config.php)

const PAGE_SIZE = 20; // pagination page size

/* ===== Connect (strict) ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connect failed: '.$e->getMessage());
    die('Service unavailable.');
}

$employer_id = (int)($_SESSION['user_id'] ?? 0);
if ($employer_id <= 0) {
    echo "<script>alert('Invalid session. Please login again.'); window.location.href='login.php';</script>";
    exit();
}

/* ===== Utilities ===== */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function digits(string $s): string { return preg_replace('/\D+/', '', $s); }
function file_ext(string $path): string { return strtolower(pathinfo($path, PATHINFO_EXTENSION)); }
function viewer_url(string $absUrl, string $ext): ?string {
    $ext = strtolower($ext);
    if ($ext === 'pdf')  return 'https://docs.google.com/gview?url=' . rawurlencode($absUrl) . '&embedded=true';
    if ($ext === 'doc' || $ext === 'docx') return 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($absUrl);
    return null;
}

/* ===== Fetch view limit & counts ===== */
$limitQuery = $conn->prepare("SELECT view_limit FROM employer_limits WHERE employer_id = ?");
$limitQuery->bind_param("i", $employer_id);
$limitQuery->execute();
$view_limit = (int)($limitQuery->get_result()->fetch_assoc()['view_limit'] ?? 0);
$limitQuery->close();

$countQuery = $conn->prepare("SELECT COUNT(*) AS viewed FROM jobseeker_views WHERE employer_id = ?");
$countQuery->bind_param("i", $employer_id);
$countQuery->execute();
$viewed_count = (int)$countQuery->get_result()->fetch_assoc()['viewed'];
$countQuery->close();

/* ===== If viewing a specific jobseeker, enforce/record view ===== */
$selectedUser = null;
$selected_cv_url_abs = null; // absolute URL for viewer
$selected_cv_ext = null;

if (isset($_GET['id'])) {
    $jobseeker_id = (int)$_GET['id'];
} else {
    // If no ID is provided, find the first jobseeker from the latest pool (Auto-load feature)
    $firstQuery = $conn->query("SELECT user_id FROM jobseekers JOIN users ON jobseekers.user_id = users.id ORDER BY users.created_at DESC LIMIT 1");
    $jobseeker_id = (int)($firstQuery->fetch_assoc()['user_id'] ?? 0);
}

if ($jobseeker_id > 0) {
    // Check if already viewed by this employer (RESTORED logic)
    $checkStmt = $conn->prepare("SELECT 1 FROM jobseeker_views WHERE employer_id = ? AND jobseeker_id = ?");
    $checkStmt->bind_param("ii", $employer_id, $jobseeker_id);
    $checkStmt->execute();
    $already = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    if (!$already) {
        if ($viewed_count >= $view_limit && $view_limit > 0) {
            // Silence alert on auto-load, only alert if they explicitly click a new one?
            // For now, keep redirection to prevent unauthorized viewing
            echo "<script>alert('View limit reached. You cannot view more jobseeker profiles.'); window.location.href='Jobseekers.php';</script>";
            exit();
        }
        
        // Record view in database
        $logStmt = $conn->prepare("INSERT INTO jobseeker_views (employer_id, jobseeker_id) VALUES (?, ?)");
        $logStmt->bind_param("ii", $employer_id, $jobseeker_id);
        $logStmt->execute();
        $logStmt->close();
        
        // Increment local count for immediate UI update
        $viewed_count++;
    }

    // Load selected user profile (Using Jobseeker ID)
    $stmt = $conn->prepare("SELECT j.*, u.created_at FROM jobseekers j JOIN users u ON j.user_id = u.id WHERE j.user_id = ?");
    $stmt->bind_param("i", $jobseeker_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $selectedUser = $res->fetch_assoc();
    $stmt->close();

    // Prepare CV absolute url + ext for viewer
    if ($selectedUser && !empty($selectedUser['cv_file'])) {
        $cv = $selectedUser['cv_file'];
        $isAbs = (stripos($cv, 'http://') === 0 || stripos($cv, 'https://') === 0);
        $abs = $isAbs ? $cv : (rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], '/') . '/' . ltrim($cv, '/'));
        $selected_cv_url_abs = $abs;
        $selected_cv_ext = file_ext($cv);
    }
}

/* ===== Filters + Pagination ===== */
$where = [];
$params = [];
$types  = '';

$gender = trim((string)($_GET['gender'] ?? ''));
$country = trim((string)($_GET['country'] ?? ''));
$age_range = trim((string)($_GET['age_range'] ?? ''));
$salary_range = trim((string)($_GET['salary_range'] ?? ''));
$salary_expectation = trim((string)($_GET['salary_expectation'] ?? ''));
$position = trim((string)($_GET['position'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$present_location = trim((string)($_GET['present_location'] ?? ''));
$experience = trim((string)($_GET['experience'] ?? ''));
$expected_country = trim((string)($_GET['expected_country'] ?? ''));

if ($gender !== '') { $where[] = "j.gender = ?"; $params[] = $gender; $types .= 's'; }
if ($country !== '') { $where[] = "j.country LIKE ?"; $params[] = "%$country%"; $types .= 's'; }
if ($position !== '') { $where[] = "j.position = ?"; $params[] = $position; $types .= 's'; }
if ($category !== '') { $where[] = "j.category = ?"; $params[] = $category; $types .= 's'; }
if ($present_location !== '') { $where[] = "j.present_location = ?"; $params[] = $present_location; $types .= 's'; }
if ($salary_range !== '') { $where[] = "j.salary_range = ?"; $params[] = $salary_range; $types .= 's'; }
if ($salary_expectation !== '') { $where[] = "j.salary_expectation = ?"; $params[] = $salary_expectation; $types .= 's'; }
if ($age_range !== '' && strpos($age_range, '-') !== false) {
    [$minAge, $maxAge] = array_map('intval', explode('-', $age_range, 2));
    $where[] = "j.age BETWEEN ? AND ?"; $params[] = $minAge; $params[] = $maxAge; $types .= 'ii';
}
if ($experience !== '') {
    if ($experience === '0-1')       $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) <= 1";
    elseif ($experience === '1-3')   $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) BETWEEN 1 AND 3";
    elseif ($experience === '3-5')   $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) BETWEEN 3 AND 5";
    elseif ($experience === '5+')    $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) >= 5";
}
if ($expected_country !== '') {
    $where[] = "FIND_IN_SET(?, j.expected_countries) > 0";
    $params[] = $expected_country; $types .= 's';
}

/* ===== Count total for pagination ===== */
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PAGE_SIZE;

$sqlBase = "FROM jobseekers j JOIN users u ON j.user_id = u.id";
$sqlWhere = $where ? (" WHERE " . implode(" AND ", $where)) : '';

$sqlCount = "SELECT COUNT(*) AS c " . $sqlBase . $sqlWhere;
$stmt = $conn->prepare($sqlCount);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$total_applicants = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$total_pages = max(1, (int)ceil($total_applicants / PAGE_SIZE));

/* ===== Fetch page rows ===== */
$sql = "SELECT j.*, u.created_at " . $sqlBase . $sqlWhere . " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params2 = $params;
$types2  = $types . 'ii';
$params2[] = PAGE_SIZE;
$params2[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

/* ===== Fetch viewed IDs for highlighting ===== */
$viewedIds = [];
$vQuery = $conn->prepare("SELECT jobseeker_id FROM jobseeker_views WHERE employer_id = ?");
$vQuery->bind_param("i", $employer_id);
$vQuery->execute();
$vRes = $vQuery->get_result();
while ($vRow = $vRes->fetch_assoc()) { $viewedIds[] = (int)$vRow['jobseeker_id']; }
$vQuery->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>MSJOBS — Jobseeker Database</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --brand:#2563eb; --brand2:#7c3aed; --ink:#0f172a;
    }
    *{ -webkit-tap-highlight-color:transparent }
    body{ font-family:'Inter',sans-serif; background:#f7f9fc; color:var(--ink) }
    .glass{ background:rgba(255,255,255,.9); backdrop-filter:saturate(140%) blur(8px); }
    .card{ background:#fff; border:1px solid #e5e7eb; border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.05) }
    .badge{ font-size:.72rem; border:1px solid #e5e7eb; color:#475569; padding:.25rem .5rem; border-radius:999px; background:#f8fafc }
    .chip{ border:1px solid #e5e7eb; border-radius:12px; padding:.7rem .9rem; background:#fff; width:100% }
    .chip:focus{ outline:2px solid var(--brand); outline-offset:2px }
    .btn{ border-radius:12px; padding:.85rem 1rem; font-weight:700 }
    .btn-brand{ background:linear-gradient(135deg,var(--brand),var(--brand2)); color:#fff }
    .btn-brand:hover{ filter:brightness(0.95) }
    .btn-ghost{ background:#fff; border:1px solid #e5e7eb }
    .list-row{ transition:background .2s, transform .1s }
    .list-row:hover{ background:#f1f5ff }
    .list-row:active{ transform:scale(.995) }
    .split{ display:grid; grid-template-columns:1fr; gap:1.5rem; align-items: start; }
    @media(min-width:1024px){ .split{ grid-template-columns: minmax(0,1.2fr) minmax(0,1.8fr) } }
    .sticky-side{ position: sticky; top: 130px; }
  </style>
</head>
<body class="min-h-screen">
  <!-- Header -->
  <header class="bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4 flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <button onclick="goBack()" class="h-10 w-10 grid place-items-center bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition-all">
          <i class="fa-solid fa-arrow-left text-slate-600"></i>
        </button>
        <div class="h-12 w-12 rounded-2xl grid place-items-center text-white font-black text-xl shadow-lg shadow-blue-200"
             style="background:linear-gradient(135deg,#3b82f6,#8b5cf6)">MS</div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">Jobseeker Database</h1>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Professional Dashboard
          </p>
        </div>
      </div>
      
      <!-- Stats Pill -->
      <div class="flex items-center bg-slate-50 border border-slate-200 rounded-2xl p-1.5 shadow-sm">
        <div class="px-6 py-2 text-center">
            <div class="text-[10px] uppercase tracking-tighter font-extrabold text-slate-400">Profile Views</div>
            <div class="text-lg font-black text-slate-900"><?= number_format($viewed_count) ?></div>
        </div>
        <div class="w-px h-8 bg-slate-200"></div>
        <div class="px-6 py-2 text-center">
            <div class="text-[10px] uppercase tracking-tighter font-extrabold text-slate-400">Remaining Views</div>
            <div class="text-lg font-black <?= ($view_limit - $viewed_count) <= 0 ? 'text-rose-600' : 'text-blue-600' ?>">
                <?= number_format(max(0, $view_limit - $viewed_count)) ?>
            </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <a href="company.php" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-900 transition-all">Home</a>
        <a href="Companylogin.php" class="px-6 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:shadow-lg transition-all">Company Panel</a>
      </div>
    </div>
  </header>

  <!-- Tab Navigation -->
  <nav class="bg-white border-b border-slate-200 sticky top-[65px] z-30">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 flex gap-8">
      <button onclick="switchTab('database')" id="btn-db" class="py-4 px-2 text-sm font-bold border-b-2 border-blue-600 text-blue-600 transition-all">
        <i class="fa-solid fa-database mr-2"></i> Jobseeker Database
      </button>
      <button onclick="switchTab('subscription')" id="btn-sub" class="py-4 px-2 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition-all">
        <i class="fa-solid fa-credit-card mr-2"></i> Subscription & Account
      </button>
    </div>
  </nav>

  <div id="tab-database" class="tab-content transition-all duration-300">

  <!-- Mobile quick actions -->
  <div class="sm:hidden px-4 py-3 flex gap-3 glass border-b border-slate-200">
    <button id="toggleFilters" class="flex-1 btn btn-brand">Filters</button>
    <a href="company.php" class="flex-1 text-center btn btn-ghost">Home</a>
  </div>

  <!-- Filters -->
  <section class="max-w-7xl mx-auto px-4 lg:px-8 mt-4">
    <form method="GET" class="card overflow-hidden" id="filtersCard">
    <form method="GET" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-4 md:p-6" id="filtersCard">
      <div id="filtersBody">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">

          <select name="gender" class="chip">
            <option value="">Gender</option>
            <option value="Male"   <?= $gender   === 'Male'   ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender   === 'Female' ? 'selected' : '' ?>>Female</option>
          </select>

          <div class="relative">
            <input list="positions" name="position" class="chip pl-9" placeholder="Position" value="<?= h($position) ?>">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="currentColor" stroke-width="2"/></svg>
            <datalist id="positions"><?php $posResult = $conn->query("SELECT DISTINCT title FROM jobs WHERE status='approved' ORDER BY title ASC"); while ($pos = $posResult->fetch_assoc()) echo '<option value="'.h($pos['title']).'">'; ?></datalist>
          </div>

          <select name="country" class="chip">
            <option value="">Country</option>
            <?php
              $countries = ["Afghanistan","Albania","Algeria","Andorra","Angola","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahrain","Bangladesh","Belgium","Bhutan","Brazil","Brunei","Bulgaria","Cambodia","Canada","China","Colombia","Cyprus","Czech Republic","Denmark","Dubai","Egypt","Ethiopia","Finland","France","Germany","Ghana","Greece","Hong Kong","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Japan","Jordan","Kazakhstan","Kenya","Kuwait","Lebanon","Libya","Malaysia","Maldives","Mauritius","Mexico","Morocco","Myanmar","Nepal","Netherlands","New Zealand","Nigeria","Norway","Oman","Pakistan","Palestine","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saudi Arabia","Serbia","Singapore","South Africa","South Korea","Spain","Sri Lanka","Sudan","Sweden","Switzerland","Syria","Taiwan","Tanzania","Thailand","Tunisia","Turkey","Uganda","Ukraine","United Arab Emirates","United States","Uzbekistan","Vietnam","Yemen","Zambia","Zimbabwe"];
              foreach ($countries as $c) { $sel = ($country===$c)?'selected':''; echo '<option value="'.h($c).'" '.$sel.'>'.h($c).'</option>'; }
            ?>
          </select>

          <select name="age_range" class="chip">
            <option value="">Age</option>
            <?php
              $ranges = ["18-20","18-25","18-30","18-35","18-40","18-45","18-50","18-55","18-60","18-65"];
              foreach ($ranges as $r) { $sel = ($age_range===$r)?'selected':''; echo "<option value=\"$r\" $sel>$r</option>"; }
            ?>
          </select>

          <select name="experience" class="chip">
            <option value="">Experience</option>
            <option value="0-1" <?= $experience==='0-1'?'selected':'' ?>>0-1 yr</option>
            <option value="1-3" <?= $experience==='1-3'?'selected':'' ?>>1-3 yrs</option>
            <option value="3-5" <?= $experience==='3-5'?'selected':'' ?>>3-5 yrs</option>
            <option value="5+"  <?= $experience==='5+'?'selected':''  ?>>5+ yrs</option>
          </select>

          <select name="category" class="chip">
            <option value="">Category</option>
            <?php
              $categories = ["Cleaning & Hospitality","Engineering & Contractions","Maintenance","Manufacturing","Hotels & Restaurants","Transportation","Delivery Service","Helpers","Accounting & Finance","Auto Mobile","Beauty/Salon","Customer Service / Call Center","Data Management & Analyst","Graphic Designer","Admin & HR","Sales / Business Development","Secretarial / Front Office","Security Guard","Sports & Fitness","Travel & Tourism","Medical & Health Care","Media, Art & Entertainment","Marketing & Advertising","Marine Captain / Crew","Logistics & Distribution","Legal Services","Education","Drivers","hypermarket","supermarket","Other"];
              foreach ($categories as $cat) { $sel = ($category===$cat)?'selected':''; echo '<option value="'.h($cat).'" '.$sel.'>'.h($cat).'</option>'; }
            ?>
          </select>

          <select name="salary_range" class="chip">
            <option value="">Salary</option>
            <?php
              $salary_ranges = ["500 - 1000","1000 - 1500","1500 - 2000","2000 - 3000","3000 - 5000","5000 - 7000","7000 - 10000"];
              foreach ($salary_ranges as $sr) {
                $disp = "$" . str_replace(" - ", " - $", $sr) . " USD";
                $sel = ($salary_range===$sr)?'selected':'';
                echo "<option value=\"$sr\" $sel>$disp</option>";
              }
            ?>
          </select>

          <select name="expected_country" class="chip">
            <option value="">Expected Count</option>
            <?php
              $expected = ["United Arab Emirates","Qatar","Saudi Arabia","Oman","Kuwait","Bahrain","United Kingdom","United States","Canada","Australia","Germany","France","Italy","Spain","Netherlands","Singapore","Malaysia","India","Sri Lanka","Other"];
              foreach ($expected as $c) { $sel = ($expected_country===$c)?'selected':''; echo '<option value="'.h($c).'" '.$sel.'>'.h($c).'</option>'; }
            ?>
          </select>

          <select name="present_location" class="chip">
            <option value="">Location</option>
            <?php
              $locs = ["UAE","Qatar","Saudi Arabia","Oman","Kuwait","Bahrain","India","Sri Lanka","Pakistan","Nepal","Bangladesh","Philippines","Other"];
              foreach ($locs as $c) { $sel = ($present_location===$c)?'selected':''; echo '<option value="'.h($c).'" '.$sel.'>'.h($c).'</option>'; }
            ?>
          </select>

          <div class="md:col-span-2 lg:col-span-1">
            <button type="submit" class="btn btn-brand w-full py-2.5">Search</button>
          </div>
        </div>
      </div>
    </form>
  </section>

  <!-- Main -->
  <main class="max-w-7xl mx-auto px-4 lg:px-8 py-6">
    <div class="split">
      <!-- Left: list -->
      <section class="card overflow-hidden">
        <div class="px-4 py-3 flex items-center justify-between border-b">
          <h2 class="font-semibold">All Jobseekers</h2>
          <span class="badge">Results: <?= $total_applicants ?></span>
        </div>

        <!-- Mobile cards -->
        <div class="lg:hidden">
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <article class="p-4 border-b last:border-b-0">
                <div class="flex items-center gap-3">
                  <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-sm">
                    <?= strtoupper(substr($row['full_name'] ?? 'U', 0, 1)) ?>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                      <h3 class="font-semibold text-slate-900 truncate"><?= h($row['full_name']) ?></h3>
                      <?php if (in_array((int)$row['id'], $viewedIds)): ?>
                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full uppercase border border-emerald-100">Viewed</span>
                      <?php endif; ?>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-2">
                      <span class="badge">ID: <?= h((string)$row['id']) ?></span>
                      <?php if (!empty($row['country'])): ?><span class="badge"><?= h($row['country']) ?></span><?php endif; ?>
                      <?php if (!empty($row['position'])): ?><span class="badge"><?= h($row['position']) ?></span><?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="mt-3">
                  <a href="?<?= http_build_query(array_merge($_GET, ['id'=>$row['id']])) ?>" class="text-sky-700 font-semibold">View Profile →</a>
                </div>
              </article>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="p-8 text-center text-rose-600 font-semibold">No jobseekers found.</div>
          <?php endif; ?>
        </div>

        <!-- Desktop rows -->
        <?php
          // Re-run for desktop (cursor consumed by mobile loop)
          $stmt2 = $conn->prepare($sql);
          $stmt2->bind_param($types2, ...$params2);
          $stmt2->execute();
          $desktop = $stmt2->get_result();
        ?>
        <div class="hidden lg:block divide-y">
          <?php if ($desktop->num_rows > 0): ?>
            <?php while ($row = $desktop->fetch_assoc()): ?>
              <article class="list-row flex items-center justify-between gap-4 p-4">
                <div class="flex items-center gap-4 min-w-0">
                  <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-sm shrink-0">
                    <?= strtoupper(substr($row['full_name'] ?? 'U', 0, 1)) ?>
                  </div>
                  <div class="min-w-0">
                    <div class="flex items-center gap-3">
                      <h3 class="font-semibold truncate text-slate-900"><?= h($row['full_name']) ?></h3>
                      <?php if (in_array((int)$row['id'], $viewedIds)): ?>
                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full uppercase border border-emerald-100">Viewed</span>
                      <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-1">
                      <span class="badge">ID: <?= h((string)$row['id']) ?></span>
                      <?php if (!empty($row['country'])): ?><span class="badge"><?= h($row['country']) ?></span><?php endif; ?>
                      <?php if (!empty($row['position'])): ?><span class="badge"><?= h($row['position']) ?></span><?php endif; ?>
                      <?php if (!empty($row['experience'])): ?><span class="badge"><?= h($row['experience']) ?></span><?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="shrink-0">
                  <a href="?<?= http_build_query(array_merge($_GET, ['id'=>$row['id']])) ?>" class="text-sky-700 font-semibold">View Profile →</a>
                </div>
              </article>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="p-8 text-center text-rose-600 font-semibold">No jobseekers found.</div>
          <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="px-4 py-4 flex items-center justify-center gap-2 border-t">
            <?php
              $baseParams = $_GET; unset($baseParams['page']);
              $base = '?'.http_build_query($baseParams);
              $prev = max(1, $page-1); $next = min($total_pages, $page+1);
            ?>
            <a class="btn btn-ghost px-3 py-2 <?= $page===1 ? 'pointer-events-none opacity-50' : '' ?>" href="<?= $base.'&page='.$prev ?>">Prev</a>
            <span class="text-sm text-slate-600">Page <b><?= $page ?></b> of <b><?= $total_pages ?></b></span>
            <a class="btn btn-ghost px-3 py-2 <?= $page===$total_pages ? 'pointer-events-none opacity-50' : '' ?>" href="<?= $base.'&page='.$next ?>">Next</a>
          </div>
        <?php endif; ?>
      </section>

      <!-- Right: profile -->
      <aside class="sticky-side">
        <div class="card p-5 lg:p-8">
        <?php if ($selectedUser): ?>
          <div class="flex items-start gap-4">
            <?php if (!empty($selectedUser['profile_picture'])): ?>
              <img src="<?= h($selectedUser['profile_picture']) ?>" class="h-20 w-20 rounded-full border object-cover" alt="Profile Picture">
            <?php else: ?>
              <div class="h-20 w-20 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">No Photo</div>
            <?php endif; ?>
            <div class="min-w-0">
              <h2 class="text-xl font-extrabold leading-tight break-words"><?= h($selectedUser['full_name']) ?></h2>
              <div class="mt-2 flex flex-wrap gap-2">
                <?php if (!empty($selectedUser['position'])): ?><span class="badge"><?= h($selectedUser['position']) ?></span><?php endif; ?>
                <?php if (!empty($selectedUser['category'])): ?><span class="badge"><?= h($selectedUser['category']) ?></span><?php endif; ?>
                <?php if (!empty($selectedUser['experience'])): ?><span class="badge"><?= h($selectedUser['experience']) ?></span><?php endif; ?>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 mt-6 text-sm">
            <p><span class="text-slate-500">Gender:</span> <span class="font-medium"><?= h($selectedUser['gender'] ?? '') ?></span></p>
            <p><span class="text-slate-500">Created at:</span> <span class="font-medium"><?= h($selectedUser['created_at'] ?? '') ?></span></p>
            <p><span class="text-slate-500">Nationality:</span> <span class="font-medium"><?= h($selectedUser['nationality'] ?? '') ?></span></p>
            <p><span class="text-slate-500">Age:</span> <span class="font-medium"><?= h((string)($selectedUser['age'] ?? '')) ?></span></p>
            <p><span class="text-slate-500">Language:</span> <span class="font-medium"><?= h($selectedUser['language'] ?? '') ?></span></p>
            <p><span class="text-slate-500">Country:</span> <span class="font-medium"><?= h($selectedUser['country'] ?? '') ?></span></p>
            <p><span class="text-slate-500">Salary Range:</span> <span class="font-medium"><?= h($selectedUser['salary_range'] ?? '') ?></span></p>
            <p><span class="text-slate-500">Job Status:</span> <span class="font-medium"><?= h($selectedUser['current_job_status'] ?? '') ?></span></p>
            <p class="sm:col-span-2"><span class="text-slate-500">Expected Countries:</span> <span class="font-medium break-words"><?= h($selectedUser['expected_countries'] ?? '') ?></span></p>
            <p class="sm:col-span-2"><span class="text-slate-500">Present Location:</span> <span class="font-medium"><?= h($selectedUser['present_location'] ?? '') ?></span></p>

            <?php if (!empty($selectedUser['whatsapp'])):
              $wa = digits($selectedUser['whatsapp']); $msg = urlencode("Hello, I am contacting you regarding your job application.");
              $whatsappLink = "https://wa.me/{$wa}?text={$msg}";
            ?>
            <p class="sm:col-span-2">
              <span class="text-slate-500">WhatsApp:</span>
              <a href="<?= h($whatsappLink) ?>" target="_blank" class="text-sky-700 font-semibold break-words"><?= h($selectedUser['whatsapp']) ?></a>
            </p>
            <?php endif; ?>
          </div>

          <hr class="my-6">

          <?php if (!empty($selectedUser['cv_file'])):
              $abs = $selected_cv_url_abs;
              $ext = $selected_cv_ext;
              $viewer = $abs ? viewer_url($abs, $ext) : null;
          ?>
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="font-semibold">CV Preview</p>
                <div class="flex items-center gap-2">
                  <?php if ($viewer): ?>
                    <button class="btn btn-ghost px-3 py-2" onclick="openCV('<?= h($viewer) ?>')">Open in Viewer</button>
                  <?php endif; ?>
                  <a href="<?= h($selectedUser['cv_file']) ?>" download class="text-sky-700 font-semibold">Download</a>
                </div>
              </div>
              <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-inner bg-slate-50">
                <?php if ($viewer): ?>
                  <iframe id="cvFrameInline" src="<?= h($viewer) ?>" width="100%" height="800" class="border-0"></iframe>
                <?php else: ?>
                  <div class="p-12 text-center">
                    <div class="h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <i class="fa-solid fa-file-circle-exclamation text-slate-400 text-xl"></i>
                    </div>
                    <p class="text-sm text-slate-500">Preview unavailable for this file type.</p>
                    <a href="<?= h($selectedUser['cv_file']) ?>" download class="inline-block mt-4 text-blue-600 font-bold hover:underline">Download Original File</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <p class="text-rose-600 font-semibold">CV not available.</p>
          <?php endif; ?>

        <?php else: ?>
          <div class="text-slate-600">
            Select <span class="font-semibold">View Profile</span> to see candidate details and CV preview.
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </div> <!-- End tab-database -->

  <!-- Subscription Tab Content -->
  <div id="tab-subscription" class="tab-content hidden animate-fade-in py-10">
    <div class="max-w-4xl mx-auto px-4 lg:px-8">
      <!-- Account Overview Card -->
      <div class="bg-white rounded-[2rem] border border-slate-200 shadow-xl overflow-hidden mb-8">
        <div class="p-8 md:p-12">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
            <div>
              <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-600 text-xs font-bold uppercase tracking-widest">Active Plan</span>
              <h2 class="text-4xl font-extrabold text-slate-900 mt-2">Your Subscription</h2>
            </div>
            <div class="flex flex-col items-end">
              <div class="text-sm text-slate-400 font-bold uppercase tracking-wider">Valid Until</div>
              <div class="text-2xl font-extrabold text-slate-900"><?= date('F d, Y', strtotime($expiry_date ?? 'now')) ?></div>
            </div>
          </div>

          <!-- Stats Grid -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100">
              <div class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Total Credits</div>
              <div class="text-3xl font-black text-slate-900"><?= number_format($view_limit) ?></div>
            </div>
            <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100">
              <div class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Used Views</div>
              <div class="text-3xl font-black text-blue-600"><?= number_format($viewed_count) ?></div>
            </div>
            <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100">
              <div class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">Remaining</div>
              <div class="text-3xl font-black text-emerald-600"><?= max(0, $view_limit - $viewed_count) ?></div>
            </div>
          </div>

          <!-- Progress Bar -->
          <?php $usage_pct = ($view_limit > 0) ? min(100, ($viewed_count / $view_limit) * 100) : 0; ?>
          <div class="mt-10">
            <div class="flex justify-between items-end mb-2">
              <span class="text-sm font-bold text-slate-600">Usage Progress</span>
              <span class="text-sm font-bold text-slate-900"><?= round($usage_pct) ?>%</span>
            </div>
            <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-blue-600 to-indigo-600 transition-all duration-1000" style="width: <?= $usage_pct ?>%"></div>
            </div>
          </div>
        </div>

        <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
          <p class="text-sm text-slate-500 font-medium">Need more credits? Explore our premium plans.</p>
          <a href="Companylogin.php#pricing" class="px-8 py-3 bg-slate-900 text-white rounded-xl font-bold text-sm hover:scale-105 transition-all">
            Upgrade Plan <i class="fa-solid fa-crown ml-2 text-yellow-500"></i>
          </a>
        </div>
      </div>

      <!-- Payment Support Section -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-lg">
          <h3 class="text-xl font-extrabold mb-4 flex items-center gap-3">
             <i class="fa-solid fa-building-columns text-blue-600"></i> Bank Details
          </h3>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-slate-400">Bank</span>
              <span class="font-bold">Abu Dhabi Commercial Bank</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Account</span>
              <span class="font-bold">13667241920001</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">IBAN</span>
              <span class="font-bold select-all">AE520030013667241920001</span>
            </div>
          </div>
        </div>
        <div class="bg-emerald-50 rounded-3xl p-8 border border-emerald-100 shadow-lg flex flex-col justify-between">
          <div>
            <h3 class="text-xl font-extrabold text-emerald-900 mb-2">Direct Support</h3>
            <p class="text-sm text-emerald-700">Need help with activation or a manual top-up? Chat with our team.</p>
          </div>
          <a href="https://wa.me/971585323967" target="_blank" class="mt-6 flex items-center justify-center gap-2 bg-emerald-500 text-white rounded-xl py-3 font-bold hover:bg-emerald-600 transition-all no-underline">
            Contact on WhatsApp <i class="fa-brands fa-whatsapp"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <style>
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
  </style>

  <script>
    function switchTab(tab) {
      const dbTab = document.getElementById('tab-database');
      const subTab = document.getElementById('tab-subscription');
      const btnDb = document.getElementById('btn-db');
      const btnSub = document.getElementById('btn-sub');

      if (tab === 'database') {
        dbTab.classList.remove('hidden');
        subTab.classList.add('hidden');
        btnDb.classList.add('border-blue-600', 'text-blue-600');
        btnDb.classList.remove('border-transparent', 'text-slate-500');
        btnSub.classList.add('border-transparent', 'text-slate-500');
        btnSub.classList.remove('border-blue-600', 'text-blue-600');
      } else {
        dbTab.classList.add('hidden');
        subTab.classList.remove('hidden');
        btnSub.classList.add('border-blue-600', 'text-blue-600');
        btnSub.classList.remove('border-transparent', 'text-slate-500');
        btnDb.classList.add('border-transparent', 'text-slate-500');
        btnDb.classList.remove('border-blue-600', 'text-blue-600');
      }
    }
  </script>

  <!-- Mobile bottom bar -->
  <div class="sm:hidden fixed bottom-4 inset-x-4 flex gap-3">
    <button onclick="goBack()" class="flex-1 text-center btn btn-ghost">← Back</button>
    <a href="company.php" class="flex-1 text-center btn btn-brand">Home</a>
  </div>

  <!-- CV Modal (optional full-screen viewer) -->
  <div id="cvModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-white w-11/12 max-w-5xl h-[90vh] rounded-2xl overflow-hidden relative shadow-2xl border border-slate-200">
      <button onclick="closeCV()" class="absolute top-3 right-4 text-3xl font-bold text-slate-500 hover:text-black">&times;</button>
      <iframe id="cvFrame" class="w-full h-full border-0"></iframe>
    </div>
  </div>

  <script>
    // Back button
    function goBack(){
      if (document.referrer && document.referrer !== location.href) history.back();
      else window.location.href = 'Companylogin';
    }

    // Filters collapse
    const toggle = document.getElementById('toggleFilters');
    const collapseBtn = document.getElementById('collapseBtn');
    const body = document.getElementById('filtersBody');
    const card = document.getElementById('filtersCard');

    function hideFilters(){ body.style.display='none'; if (collapseBtn) collapseBtn.textContent='Show'; }
    function showFilters(){ body.style.display=''; if (collapseBtn) collapseBtn.textContent='Hide'; }

    if (toggle) toggle.addEventListener('click', ()=>{ body.style.display==='none'?showFilters():hideFilters(); card.scrollIntoView({behavior:'smooth',block:'start'}); });
    if (collapseBtn) collapseBtn.addEventListener('click', ()=>{ body.style.display==='none'?showFilters():hideFilters(); });

    // CV modal
    function openCV(viewer){
      const m = document.getElementById('cvModal');
      const f = document.getElementById('cvFrame');
      f.src = viewer;
      m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeCV(){
      const m = document.getElementById('cvModal');
      const f = document.getElementById('cvFrame');
      f.src = '';
      m.classList.add('hidden'); m.classList.remove('flex');
    }
  </script>
</body>
</html>
<?php
$stmt->close();
if (isset($stmt2)) $stmt2->close();
$conn->close();
