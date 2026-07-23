<?php
require_once __DIR__ . '/config.php';
$DB_HOST = $servername;
$DB_USER = $username;
$DB_PASS = $password;
$DB_NAME = $dbname;
$database = $dbname;

/************************************************************
 * MSJOBS — Job Listings (Indeed-style) with Reliable Logos
 * Single file (all-jobs.php). Alpine.js for micro-interactions.
 * - FIX: auto-detect logo column (logo | logos | company_logo)
 ************************************************************/

declare(strict_types=1);
ini_set('display_errors', '1'); // turn off in production
error_reporting(E_ALL);

/* ==== PATHS (for logos) ==== */
define('LOGO_DIR', __DIR__ . '/uploads/logos');             // server path to logo folder
define('LOGO_PUBLIC', 'uploads/logos');                      // public web path to logo folder
define('LOGO_PLACEHOLDER', 'img/company-placeholder.png');   // fallback image

/* ==== DB CONFIG ==== */
// $DB_HOST = "127.0.0.1"; (Refactored to config.php)
$DB_PORT = 3306;
// $DB_USER = "u903588615_root"; (Refactored to config.php)
// $DB_PASS = "Msjobs#1"; (Refactored to config.php)
// $DB_NAME = "u903588615_exaple"; (Refactored to config.php)

/* ==== CONNECT ==== */
$mysqli = @new mysqli($DB_HOST . ":" . $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
  http_response_code(500);
  die("Database connection failed.");
}
$mysqli->set_charset('utf8mb4');

/* ==== HELPERS ==== */
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Detect which logo column exists in `jobs` table. */
function detectLogoColumn(mysqli $db, string $schema, string $table = 'jobs'): ?string {
  $candidates = ['logo','logos','company_logo'];
  $res = $db->query("SHOW COLUMNS FROM `$table`");
  if ($res === false) return null;
  $found = [];
  while ($r = $res->fetch_assoc()) $found[] = $r['Field'];
  // Keep stable priority: logo > logos > company_logo
  foreach ($candidates as $c) if (in_array($c, $found, true)) return $c;
  return null;
}

/** Resolve a DB `logo` value to a safe, visible public URL with server-side existence checks. */
function resolveLogoUrl(?string $logo): string {
  $logo = trim((string)$logo);

  // 1) Absolute URLs or data URIs → use as-is
  if ($logo !== '' && (stripos($logo, 'http://') === 0 || stripos($logo, 'https://') === 0 || stripos($logo, 'data:image') === 0)) {
    return $logo;
  }

  // 2) If DB stored just a filename or relative path, look in uploads/logos first
  if ($logo !== '') {
    $basename = basename($logo); // normalize
    $candidateLocal  = LOGO_DIR . '/' . $basename;     // server path
    $candidatePublic = LOGO_PUBLIC . '/' . $basename;  // web path
    if (@file_exists($candidateLocal)) {
      return $candidatePublic;
    }
    // If a relative path was stored and is web-accessible, allow it:
    if (@file_exists(__DIR__ . '/' . ltrim($logo, '/'))) {
      return ltrim($logo, '/');
    }
  }

  // 3) Final fallback placeholder
  return LOGO_PLACEHOLDER;
}

/* ==== FILTER OPTIONS (for dropdowns) ==== */
function fetchDistinct(string $col, mysqli $db): array {
  $allowed = ['category','type','experience_level','location'];
  if (!in_array($col, $allowed, true)) return [];
  $sql = "SELECT DISTINCT $col AS v FROM jobs WHERE status='approved' AND $col <> '' ORDER BY v ASC";
  $rows = [];
  if ($res = $db->query($sql)) {
    while ($r = $res->fetch_assoc()) $rows[] = $r['v'];
    $res->free();
  }
  return $rows;
}

/* ==== INPUTS ==== */
$q        = trim($_GET['q'] ?? '');
$loc      = trim($_GET['loc'] ?? '');
$category = trim($_GET['category'] ?? '');
$type     = trim($_GET['type'] ?? '');
$exp      = trim($_GET['exp'] ?? '');
$remote   = trim($_GET['remote'] ?? '');
$pay_min  = trim($_GET['pay_min'] ?? '');
$pay_max  = trim($_GET['pay_max'] ?? '');
$sort     = trim($_GET['sort'] ?? 'new'); // new | salary_desc | salary_asc
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

$categories = fetchDistinct('category', $mysqli);
$types      = fetchDistinct('type', $mysqli);
$exps       = fetchDistinct('experience_level', $mysqli);
$locations  = fetchDistinct('location', $mysqli);

/* ==== BUILD QUERY (prepared) ==== */
$where   = ["status='approved'"];
$params  = [];
$ptypes  = '';

if ($q !== '') {
  $like = '%' . $q . '%';
  $where[] = "(title LIKE ? OR company_name LIKE ? OR description LIKE ? OR category LIKE ?)";
  array_push($params, $like, $like, $like, $like);
  $ptypes .= 'ssss';
}
if ($loc !== '') {
  $where[] = "(location LIKE ?)";
  $params[] = '%' . $loc . '%';
  $ptypes .= 's';
}
if ($category !== '') { $where[] = "category = ?"; $params[] = $category; $ptypes .= 's'; }
if ($type !== '')     { $where[] = "type = ?";     $params[] = $type;     $ptypes .= 's'; }
if ($exp !== '')      { $where[] = "experience_level = ?"; $params[] = $exp; $ptypes .= 's'; }

if ($remote !== '' && ($remote === '1' || $remote === '0')) {
  $where[] = "is_remote = ?";
  $params[] = (int)$remote;
  $ptypes .= 'i';
}
if ($pay_min !== '' && is_numeric($pay_min)) {
  $where[] = "((min_salary IS NOT NULL AND min_salary >= ?) OR (max_salary IS NOT NULL AND max_salary >= ?))";
  array_push($params, (float)$pay_min, (float)$pay_min);
  $ptypes .= 'dd';
}
if ($pay_max !== '' && is_numeric($pay_max)) {
  $where[] = "((max_salary IS NOT NULL AND max_salary <= ?) OR (min_salary IS NOT NULL AND min_salary <= ?))";
  array_push($params, (float)$pay_max, (float)$pay_max);
  $ptypes .= 'dd';
}

$orderBy = "created_at DESC, id DESC";
if ($sort === 'salary_desc') $orderBy = "COALESCE(max_salary, min_salary) DESC, created_at DESC";
if ($sort === 'salary_asc')  $orderBy = "COALESCE(min_salary, max_salary) ASC, created_at DESC";

$whereSql = implode(' AND ', $where);

/* ==== COUNT ==== */
$countSql = "SELECT COUNT(*) AS c FROM jobs WHERE $whereSql";
$countStmt = $mysqli->prepare($countSql);
if ($ptypes !== '') $countStmt->bind_param($ptypes, ...$params);
$countStmt->execute();
$countRes = $countStmt->get_result();
$total = (int)($countRes->fetch_assoc()['c'] ?? 0);
$countStmt->close();

$pages = max(1, (int)ceil($total / $per_page));
if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $per_page; }

/* ==== DETECT LOGO COLUMN & BUILD SELECT ==== */
$logoCol = detectLogoColumn($mysqli, $DB_NAME, 'jobs');           // returns 'logo' | 'logos' | 'company_logo' | null
$logoSelect = $logoCol ? "$logoCol AS logo_any" : "NULL AS logo_any";

/* ==== DATA ==== */
$dataSql = "SELECT id, title, company_name, $logoSelect, category, type, experience_level, location, is_remote,
                   min_salary, max_salary, currency, description, created_at, company_id
            FROM jobs
            WHERE $whereSql
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
$dataStmt = $mysqli->prepare($dataSql);
$bindTypes = $ptypes . 'ii';
$bindParams = $params; $bindParams[] = $per_page; $bindParams[] = $offset;
$dataStmt->bind_param($bindTypes, ...$bindParams);
$dataStmt->execute();
$jobsRes = $dataStmt->get_result();

/* Pre-resolve logo URLs server-side so UI never sees broken icons */
$jobs = [];
while ($r = $jobsRes->fetch_assoc()) {
  // Prefer detected logo column; keep backward compat if some code reads ['logo'] elsewhere
  $rawLogo = $r['logo_any'] ?? ($r['logo'] ?? '');
  $r['logo_url'] = resolveLogoUrl($rawLogo);
  $jobs[] = $r;
}
$dataStmt->close();

/* helpers */
function moneyRange($min, $max, $cur): string {
  $sym = $cur ?: '$';
  $fmt = fn($v) => number_format((float)$v, 0);
  if ($min && $max) return "{$sym}{$fmt($min)} – {$sym}{$fmt($max)}";
  if ($min && !$max) return "{$sym}{$fmt($min)}+";
  if (!$min && $max) return "Up to {$sym}{$fmt($max)}";
  return "Not disclosed";
}
function buildQS(array $skip = ['page']): string {
  $q = $_GET; foreach ($skip as $k) unset($q[$k]);
  return http_build_query($q);
}
$qs = buildQS();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MSJOBS — Find Jobs</title>
  <link rel="icon" type="image/png" href="img/MS copy.png">
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8f9fa;color:#2d2d2d;line-height:1.5}

    .header{background:#fff;border-bottom:1px solid #e4e2e0;padding:12px 0}
    .header-container{max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:24px;padding:0 16px}
    .logo{font-size:28px;font-weight:700;color:#2557a7;text-decoration:none}
    .nav-links{display:flex;gap:24px}
    .nav-links a{color:#2d2d2d;font-size:14px;padding:8px 0;text-decoration:none;border-bottom:2px solid transparent}
    .nav-links a.active{border-bottom-color:#2557a7;font-weight:600}
    .header-right{margin-left:auto;display:flex;gap:16px;align-items:center}
    .header-right a{text-decoration:none;color:#2557a7;font-size:14px;font-weight:500}
    .mobile-menu-btn{display:none;background:none;border:none;font-size:18px;cursor:pointer}
    .mobile-nav{display:none;background:#fff;border-bottom:1px solid #e4e2e0;padding:16px}
    .mobile-nav a{display:block;padding:8px 0;color:#2d2d2d;text-decoration:none}

    .search-section{background:#fff;padding:24px 0 32px}
    .search-container{max-width:1200px;margin:0 auto;padding:0 16px}
    .search-form{display:flex;max-width:800px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.1);border-radius:8px;overflow:hidden}
    .search-input,.location-input{border:1px solid #d9d9d9;padding:16px;font-size:16px}
    .search-input{flex:1;border-right:none}
    .location-input{flex:.6;border-left:none;border-right:none}
    .search-input:focus,.location-input:focus{outline:none;border-color:#2557a7;box-shadow:inset 0 0 0 1px #2557a7}
    .search-button{background:#2557a7;border:none;color:#fff;padding:16px 24px;font-size:16px;font-weight:600;cursor:pointer}
    .search-button:hover{background:#1d4595}

    .filters{background:#fff;border-bottom:1px solid #e4e2e0;padding:12px 0}
    .filters-container{max-width:1200px;margin:0 auto;padding:0 16px;display:flex;gap:8px;flex-wrap:wrap}
    .filter-dropdown{position:relative}
    .filter-button{background:#fff;border:1px solid #d9d9d9;padding:8px 12px;border-radius:20px;font-size:14px;cursor:pointer;display:flex;align-items:center;gap:4px}
    .filter-button:hover{border-color:#2557a7}
    .filter-button svg{width:12px;height:12px}
    .filter-dropdown-content{display:none;position:absolute;top:100%;left:0;background:#fff;border:1px solid #d9d9d9;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:10;min-width:220px;max-height:300px;overflow-y:auto}
    .filter-dropdown.open .filter-dropdown-content{display:block}
    .filter-option{padding:12px 16px;cursor:pointer;border-bottom:1px solid #f0f0f0;color:#2d2d2d;text-decoration:none;display:block}
    .filter-option:hover{background:#f8f9fa}
    .filter-option:last-child{border-bottom:none}

    .main-content{max-width:1200px;margin:0 auto;padding:24px 16px;display:grid;grid-template-columns:1fr 400px;gap:24px}
    .jobs-info,.sort-by{font-size:14px;color:#595959;margin-bottom:16px}
    .sort-by a{color:#2557a7;text-decoration:none}

    .job-card{background:#fff;border:1px solid #e4e2e0;border-radius:8px;padding:16px;margin-bottom:16px;position:relative;cursor:pointer;display:grid;grid-template-columns:56px 1fr;gap:12px;align-items:start}
    .job-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.1)}
    .job-card.selected{border-color:#2557a7;box-shadow:0 0 0 2px rgba(37,87,167,.1)}
    .company-logo{width:56px;height:56px;border-radius:8px;border:1px solid #eee;object-fit:contain;background:#fff}
    .job-title{font-size:18px;font-weight:600;color:#2557a7;text-decoration:none;margin-bottom:4px;display:block}
    .job-title:hover{text-decoration:underline}
    .job-company,.job-location,.job-salary{color:#595959;font-size:14px}
    .job-type{display:inline-block;background:#f8f9fa;color:#595959;padding:4px 8px;border-radius:12px;font-size:12px;margin-right:8px}
    .job-remote{color:#0d7337;font-size:14px;font-weight:500}
    .job-description{color:#595959;font-size:14px;line-height:1.4;margin-top:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .save-job{position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:#595959}
    .save-job:hover{color:#2557a7}
    .save-job svg{width:20px;height:20px}

    .job-detail{background:#fff;border:1px solid #e4e2e0;border-radius:8px;height:fit-content;position:sticky;top:24px}
    .job-detail-header{padding:20px;border-bottom:1px solid #e4e2e0}
    .job-detail-title{font-size:20px;font-weight:600;color:#2d2d2d;margin-bottom:8px}
    .job-detail-company{display:flex;align-items:center;gap:8px;margin-bottom:8px}
    .job-detail-company a{color:#2557a7;text-decoration:none;font-size:14px}
    .company-rating{color:#595959;font-size:14px}
    .job-detail-meta{font-size:14px;color:#595959;margin-bottom:16px}
    .apply-button{background:#2557a7;color:#fff;border:none;padding:12px 24px;border-radius:8px;font-size:16px;font-weight:600;width:100%;cursor:pointer;margin-bottom:12px}
    .apply-button:hover{background:#1d4595}
    .detail-buttons{display:flex;gap:8px}
    .save-button,.share-button{background:#fff;border:1px solid #d9d9d9;padding:8px 16px;border-radius:8px;font-size:14px;cursor:pointer;display:flex;align-items:center;gap:4px}
    .save-button{color:#2557a7;border-color:#2557a7}
    .profile-insights{padding:20px;border-top:1px solid #e4e2e0}
    .profile-insights h3{font-size:16px;font-weight:600;margin-bottom:8px}

    .pagination{display:flex;justify-content:center;margin-top:32px}
    .pagination-list{display:flex;align-items:center;gap:4px;list-style:none}
    .pagination-list a{padding:8px 12px;border:1px solid #d9d9d9;border-radius:6px;text-decoration:none;color:#2d2d2d;font-size:14px}
    .pagination-list a:hover{border-color:#2557a7;color:#2557a7}
    .pagination-list a.active{background:#2557a7;color:#fff;border-color:#2557a7}

    @media (max-width:768px){
      .main-content{grid-template-columns:1fr;gap:16px}
      .job-detail{position:static;order:-1}
      .header-container .nav-links,.header-container .header-right{display:none}
      .mobile-menu-btn{display:block;margin-left:auto}
      .mobile-nav.open{display:block}
      .search-form{flex-direction:column}
      .search-input,.location-input{border-right:1px solid #d9d9d9;border-left:1px solid #d9d9d9}
      .filters-container{overflow-x:auto;flex-wrap:nowrap}
    }
  </style>
</head>
<body x-data="jobUI(<?= htmlspecialchars(json_encode($jobs), ENT_QUOTES) ?>)">
<header class="header">
  <div class="header-container">
    <a href="index" class="logo">MSJOBS</a>
    <nav class="nav-links">
      <a href="index" class="active">Home</a>
      <a href="CompanyProfile">Company reviews</a>
      <a href="contact.html">Find salaries</a>
    </nav>
    <div class="header-right">
      <a href="login.php">Sign in</a>
      <a href="login.php">Employers / Post Job</a>
    </div>
    <button class="mobile-menu-btn" @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Toggle menu">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M3 12h18M3 6h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
  </div>
  <div class="mobile-nav" :class="{'open': mobileMenuOpen}">
    <a href="index">Home</a>
    <a href="CompanyProfile">Company reviews</a>
    <a href="contact.html">Find salaries</a>
    <a href="login.php">Sign in</a>
    <a href="login.php">Employers / Post Job</a>
  </div>
</header>

<section class="search-section">
  <div class="search-container">
    <form method="get" class="search-form">
      <input name="q" value="<?=h($q)?>" placeholder="Job title, keywords, or company" class="search-input" type="text"/>
      <input name="loc" value="<?=h($loc)?>" placeholder="City, state, zip code, or &quot;remote&quot;" class="location-input" type="text"/>
      <input type="hidden" name="category" value="<?=h($category)?>">
      <input type="hidden" name="type" value="<?=h($type)?>">
      <input type="hidden" name="exp" value="<?=h($exp)?>">
      <input type="hidden" name="remote" value="<?=h($remote)?>">
      <input type="hidden" name="pay_min" value="<?=h($pay_min)?>">
      <input type="hidden" name="pay_max" value="<?=h($pay_max)?>">
      <input type="hidden" name="sort" value="<?=h($sort)?>">
      <button type="submit" class="search-button">Search</button>
    </form>
  </div>
</section>

<section class="filters">
  <div class="filters-container">
    <!-- Pay -->
    <div class="filter-dropdown" x-data="{ open: false }">
      <button class="filter-button" @click="open = !open">
        Pay <?= ($pay_min || $pay_max) ? '(' . ($pay_min ? '$' . number_format((float)$pay_min) : '') . ($pay_min && $pay_max ? '-' : '') . ($pay_max ? '$' . number_format((float)$pay_max) : '') . ')' : '' ?>
        <svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.5 6L8 9.5 11.5 6H4.5z"/></svg>
      </button>
      <div class="filter-dropdown-content" x-show="open" @click.outside="open = false">
        <form method="get" style="padding:16px;">
          <input type="hidden" name="q" value="<?=h($q)?>">
          <input type="hidden" name="loc" value="<?=h($loc)?>">
          <input type="hidden" name="category" value="<?=h($category)?>">
          <input type="hidden" name="type" value="<?=h($type)?>">
          <input type="hidden" name="exp" value="<?=h($exp)?>">
          <input type="hidden" name="remote" value="<?=h($remote)?>">
          <input type="hidden" name="sort" value="<?=h($sort)?>">
          <div style="margin-bottom:12px;">
            <input type="number" name="pay_min" value="<?=h($pay_min)?>" placeholder="Min salary" style="width:100%;padding:8px;border:1px solid #d9d9d9;border-radius:4px;">
          </div>
          <div style="margin-bottom:12px;">
            <input type="number" name="pay_max" value="<?=h($pay_max)?>" placeholder="Max salary" style="width:100%;padding:8px;border:1px solid #d9d9d9;border-radius:4px;">
          </div>
          <button type="submit" style="background:#2557a7;color:#fff;border:none;padding:8px 16px;border-radius:4px;width:100%;">Apply</button>
        </form>
      </div>
    </div>

    <!-- Category -->
    <div class="filter-dropdown" x-data="{ open: false }">
      <button class="filter-button" @click="open = !open">
        Category <?= $category ? '(' . h($category) . ')' : '' ?>
        <svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.5 6L8 9.5 11.5 6H4.5z"/></svg>
      </button>
      <div class="filter-dropdown-content" x-show="open" @click.outside="open = false">
        <a class="filter-option" href="?<?= h($qs) ?>&category=">Any</a>
        <?php foreach ($categories as $c): ?>
          <a class="filter-option" href="?<?= h($qs) ?>&category=<?= urlencode($c) ?>"><?= h($c) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Type -->
    <div class="filter-dropdown" x-data="{ open: false }">
      <button class="filter-button" @click="open = !open">
        Type <?= $type ? '(' . h($type) . ')' : '' ?>
        <svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.5 6L8 9.5 11.5 6H4.5z"/></svg>
      </button>
      <div class="filter-dropdown-content" x-show="open" @click.outside="open = false">
        <a class="filter-option" href="?<?= h($qs) ?>&type=">Any</a>
        <?php foreach ($types as $t): ?>
          <a class="filter-option" href="?<?= h($qs) ?>&type=<?= urlencode($t) ?>"><?= h($t) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Experience -->
    <div class="filter-dropdown" x-data="{ open: false }">
      <button class="filter-button" @click="open = !open">
        Experience <?= $exp ? '(' . h($exp) . ')' : '' ?>
        <svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.5 6L8 9.5 11.5 6H4.5z"/></svg>
      </button>
      <div class="filter-dropdown-content" x-show="open" @click.outside="open = false">
        <a class="filter-option" href="?<?= h($qs) ?>&exp=">Any</a>
        <?php foreach ($exps as $e): ?>
          <a class="filter-option" href="?<?= h($qs) ?>&exp=<?= urlencode($e) ?>"><?= h($e) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Remote -->
    <div class="filter-dropdown" x-data="{ open: false }">
      <button class="filter-button" @click="open = !open">
        Workplace <?= ($remote === '1') ? '(Remote)' : (($remote === '0') ? '(On-site)' : '') ?>
        <svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.5 6L8 9.5 11.5 6H4.5z"/></svg>
      </button>
      <div class="filter-dropdown-content" x-show="open" @click.outside="open = false">
        <a class="filter-option" href="?<?= h($qs) ?>&remote=">Any</a>
        <a class="filter-option" href="?<?= h($qs) ?>&remote=1">Remote</a>
        <a class="filter-option" href="?<?= h($qs) ?>&remote=0">On-site</a>
      </div>
    </div>

    <!-- Sort -->
    <div class="filter-dropdown" x-data="{ open: false }">
      <button class="filter-button" @click="open = !open">
        Sort: <?= $sort === 'salary_desc' ? 'Salary (High → Low)' : ($sort === 'salary_asc' ? 'Salary (Low → High)' : 'Newest') ?>
        <svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.5 6L8 9.5 11.5 6H4.5z"/></svg>
      </button>
      <div class="filter-dropdown-content" x-show="open" @click.outside="open = false">
        <a class="filter-option" href="?<?= h($qs) ?>&sort=new">Newest</a>
        <a class="filter-option" href="?<?= h($qs) ?>&sort=salary_desc">Salary (High → Low)</a>
        <a class="filter-option" href="?<?= h($qs) ?>&sort=salary_asc">Salary (Low → High)</a>
      </div>
    </div>
  </div>
</section>

<main class="main-content">
  <!-- Results -->
  <section>
    <div class="jobs-info">
      Showing <?= ($total === 0) ? '0' : (($offset+1) . '–' . min($offset + $per_page, $total)) ?> of <?= $total ?> jobs
      <?php if ($q): ?> • for "<strong><?= h($q) ?></strong>"<?php endif; ?>
      <?php if ($loc): ?> • in "<strong><?= h($loc) ?></strong>"<?php endif; ?>
    </div>

    <div class="sort-by">
      Sort:
      <a href="?<?= h($qs) ?>&sort=new" <?= $sort==='new' ? 'style="font-weight:600;text-decoration:underline;"' : '' ?>>Newest</a> |
      <a href="?<?= h($qs) ?>&sort=salary_desc" <?= $sort==='salary_desc' ? 'style="font-weight:600;text-decoration:underline;"' : '' ?>>Salary High→Low</a> |
      <a href="?<?= h($qs) ?>&sort=salary_asc" <?= $sort==='salary_asc' ? 'style="font-weight:600;text-decoration:underline;"' : '' ?>>Salary Low→High</a>
    </div>

    <?php if (!$jobs): ?>
      <article class="job-card">
        <img class="company-logo" src="<?= h(LOGO_PLACEHOLDER) ?>" alt="Company logo">
        <div>
          <a class="job-title">No jobs found</a>
          <div class="job-description">Try broader keywords, a different location, or clear some filters.</div>
        </div>
      </article>
    <?php else: ?>
      <?php foreach ($jobs as $i => $job): ?>
        <?php
          $salary   = moneyRange($job['min_salary'], $job['max_salary'], $job['currency']);
          $remoteTxt = ((string)$job['is_remote'] === '1') ? 'Remote' : '';
          $logoUrl  = $job['logo_url'] ?: LOGO_PLACEHOLDER;
        ?>
        <article class="job-card" :class="{'selected': selectedIndex === <?= $i ?>}" @click="select(<?= $i ?>)" role="button">
          <img class="company-logo"
               src="<?= h($logoUrl) ?>"
               alt="<?= h($job['company_name'] ?: 'Company logo') ?>"
               onerror="this.onerror=null;this.src='<?= h(LOGO_PLACEHOLDER) ?>';">
          <div>
            <button class="save-job" title="Save job" @click.stop="save(<?= (int)$job['id'] ?>)">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h9a3 3 0 0 1 3 3v17l-7.5-4L3 22V5a3 3 0 0 1 3-3z"/></svg>
            </button>

            <a class="job-title" href="job.php?id=<?= (int)$job['id'] ?>" target="_blank" rel="noopener">
              <?= h($job['title']) ?>
            </a>
            <div class="job-company"><?= h($job['company_name']) ?></div>
            <div class="job-location">
              <?= h($job['location']) ?>
              <?php if ($remoteTxt): ?> • <span class="job-remote"><?= $remoteTxt ?></span><?php endif; ?>
            </div>
            <div>
              <?php if (!empty($job['type'])): ?><span class="job-type"><?= h($job['type']) ?></span><?php endif; ?>
              <?php if (!empty($job['experience_level'])): ?><span class="job-type"><?= h($job['experience_level']) ?></span><?php endif; ?>
              <?php if (!empty($job['category'])): ?><span class="job-type"><?= h($job['category']) ?></span><?php endif; ?>
            </div>
            <div class="job-salary"><?= h($salary) ?></div>
            <div class="job-description"><?= h(mb_strimwidth(strip_tags((string)$job['description']), 0, 220, '…', 'UTF-8')) ?></div>
          </div>
        </article>
      <?php endforeach; ?>

      <!-- Pagination -->
      <nav class="pagination" aria-label="Pagination">
        <ul class="pagination-list">
          <?php if ($page > 1): ?>
            <li><a href="?<?= h($qs) ?>&page=1">First</a></li>
            <li><a href="?<?= h($qs) ?>&page=<?= $page - 1 ?>">Prev</a></li>
          <?php endif; ?>
          <?php
            $win = 2; $start = max(1, $page - $win); $end = min($pages, $page + $win);
            for ($p = $start; $p <= $end; $p++):
          ?>
            <li><a href="?<?= h($qs) ?>&page=<?= $p ?>" class="<?= $p===$page ? 'active' : '' ?>"><?= $p ?></a></li>
          <?php endfor; ?>
          <?php if ($page < $pages): ?>
            <li><a href="?<?= h($qs) ?>&page=<?= $page + 1 ?>">Next</a></li>
            <li><a href="?<?= h($qs) ?>&page=<?= $pages ?>">Last</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </section>

  <!-- Detail panel -->
  <aside class="job-detail" x-show="jobs.length">
    <div class="job-detail-header">
      <div class="job-detail-title" x-text="jobs[selectedIndex]?.title || ''"></div>
      <div class="job-detail-company">
        <img :src="logoSafe(jobs[selectedIndex]?.logo_url)" alt="" style="width:32px;height:32px;border-radius:6px;border:1px solid #eee;object-fit:contain;background:#fff"
             @error="$event.target.src='<?= h(LOGO_PLACEHOLDER) ?>'">
        <a :href="'CompanyProfile.php?id=' + (jobs[selectedIndex]?.company_id || '')" target="_blank" rel="noopener" x-text="jobs[selectedIndex]?.company_name || ''"></a>
        <span class="company-rating">• Verified</span>
      </div>
      <div class="job-detail-meta" x-text="(jobs[selectedIndex]?.location || '') + (Number(jobs[selectedIndex]?.is_remote) ? ' • Remote' : '')"></div>
      <button class="apply-button" @click="apply(jobs[selectedIndex]?.id)">Apply now</button>
      <div class="detail-buttons">
        <button class="save-button" @click="save(jobs[selectedIndex]?.id)">Save</button>
        <button class="share-button" @click="share(jobs[selectedIndex]?.id)">Share</button>
      </div>
    </div>
    <div style="padding:20px;">
      <h3 style="font-size:16px;font-weight:600;margin-bottom:8px;">Salary</h3>
      <p style="color:#595959;" x-text="salaryText(jobs[selectedIndex])"></p>

      <h3 style="font-size:16px;font-weight:600;margin:16px 0 8px;">About the job</h3>
      <div style="color:#595959;font-size:14px;white-space:pre-wrap;" x-html="safeDesc(jobs[selectedIndex]?.description)"></div>
    </div>
    <div class="profile-insights">
      <h3>Match your profile</h3>
      <div style="font-size:14px;color:#595959;margin-bottom:12px;">Based on your recent activity</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <span class="job-type">Communication</span>
        <span class="job-type">Teamwork</span>
        <span class="job-type">Problem-solving</span>
      </div>
    </div>
  </aside>
</main>

<script>
function jobUI(jobs) {
  return {
    mobileMenuOpen: false,
    jobs: Array.isArray(jobs) ? jobs : [],
    selectedIndex: 0,
    select(i){ this.selectedIndex = i; },
    logoSafe(u){ return u || '<?= h(LOGO_PLACEHOLDER) ?>' },
    salaryText(j){
      if(!j) return '';
      const cur = j.currency || '$';
      const fmt = v => Number(v || 0).toLocaleString();
      const min = j.min_salary ? cur + fmt(j.min_salary) : '';
      const max = j.max_salary ? cur + fmt(j.max_salary) : '';
      if(min && max) return `${min} – ${max}`;
      if(min && !max) return `${min}+`;
      if(!min && max) return `Up to ${max}`;
      return 'Not disclosed';
    },
    safeDesc(html){
      try {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        [...div.querySelectorAll('script')].forEach(s => s.remove());
        return div.innerHTML;
      } catch(e) { return ''; }
    },
    apply(id){ if(id) window.open('job.php?id=' + encodeURIComponent(id), '_blank'); },
    save(id){ if(id) alert('Saved job #' + id); },
    share(id){
      const url = location.origin + '/job.php?id=' + encodeURIComponent(id || '');
      if (navigator.share) navigator.share({ title: 'Job', url }).catch(()=>{});
      else { navigator.clipboard.writeText(url).then(()=>alert('Link copied')); }
    }
  }
}
</script>
</body>
</html>
<?php $mysqli->close(); ?>
