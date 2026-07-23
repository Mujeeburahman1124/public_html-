<?php
/* MSJOBS — Home (SELF-FILTERING; DETAILS OPEN IN all-jobs.php)
 * - Filters submit to the SAME PAGE (no redirect)
 * - Job title + "View details" → all-jobs.php?open=ID
 * - Currency → Salary buckets recompute from APPROVED jobs in that currency
 * - Results, Featured, Stats all respect "approved" logic
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/settings_helper.php';

/* ------------------- helpers ------------------- */
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function numOrNull($v){ return is_numeric($v) ? (0 + $v) : null; }

/* ===== DB CONFIG ===== */
$host_parts = explode(':', $servername);
$DB_HOST_ONLY = $host_parts[0];
$DB_PORT = isset($host_parts[1]) ? (int)$host_parts[1] : 3306;
$DB_USER = $username;
$DB_PASS = $password;
$DB_NAME = $dbname;

/* ===== Connect PDO ===== */
try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST_ONLY;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  echo "<pre>Database connection failed: ".h($e->getMessage())."</pre>";
  exit;
}

/* ===== Schema helpers ===== */
function tableExists(PDO $pdo, string $db, string $t): bool {
  $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t LIMIT 1");
  $q->execute([':db'=>$db,':t'=>$t]);
  return (bool)$q->fetchColumn();
}
function tableCols(PDO $pdo, string $db, string $t): array {
  $q=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t");
  $q->execute([':db'=>$db,':t'=>$t]);
  return array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN));
}
function has(array $cols, string $c): bool { return in_array($c,$cols,true); }

/* ===== Build an "approved jobs" WHERE clause ===== */
function approvedWhere(array $jobsCols, string $alias='j'): array {
  foreach (['status','job_status','approval_status'] as $col) {
    if (has($jobsCols, $col)) {
      return ["LOWER($alias.$col) IN ('approved','active','open','published')", []];
    }
  }
  foreach (['is_approved','approved','is_active','active','publish','published'] as $col) {
    if (has($jobsCols, $col)) {
      return ["$alias.$col = 1", []];
    }
  }
  return ["1=1", []];
}

/* ===== Logo resolver ===== */
function resolve_logo(?string $val, string $defaultDir='uploads/logos'): string {
  $placeholder = 'img/company-placeholder.png';
  $v = trim((string)$val);
  if ($v === '') return $placeholder;
  if (preg_match('~^https?://~i', $v)) return $v;
  $v = str_replace('\\','/',$v);
  if (strpos($v,'/') === false) { $v = rtrim($defaultDir,'/').'/'.$v; }
  $vTrim = ltrim($v,'/');
  $absolutePath = __DIR__ . '/' . $vTrim;
  if (is_file($absolutePath) && is_readable($absolutePath)) {
    $segments = array_map('rawurlencode', explode('/', $vTrim));
    return implode('/', $segments);
  }
  return $placeholder;
}

/* ===== Introspect tables ===== */
$jobsCols = tableCols($pdo, $DB_NAME, 'jobs');
$hasCompanies = tableExists($pdo, $DB_NAME, 'companies');
$coCols = $hasCompanies ? tableCols($pdo, $DB_NAME, 'companies') : [];

[$approvedClause, $approvedParams] = approvedWhere($jobsCols, 'j');

/* ===== Field mapping ===== */
$expr = [
  'id'        => 'j.id',
  'title'     => has($jobsCols,'title') ? 'j.title'
                 : (has($jobsCols,'job_title')?'j.job_title':"'Untitled Job'"),
  'company'   => has($jobsCols,'company_name') ? 'j.company_name'
                 : (has($jobsCols,'company') ? 'j.company'
                   : ($hasCompanies && has($coCols,'name') ? 'c.name' : "'Unknown Company'")),
  'logo'      => has($jobsCols,'logos') ? 'j.logos'
                 : (has($jobsCols,'logo') ? 'j.logo'
                   : (has($jobsCols,'company_logo') ? 'j.company_logo'
                     : ($hasCompanies && has($coCols,'logos') ? 'c.logos'
                       : ($hasCompanies && has($coCols,'logo') ? 'c.logo'
                         : ($hasCompanies && has($coCols,'logo_url') ? 'c.logo_url' : "NULL"))))),
  'location'  => has($jobsCols,'location') ? 'j.location'
                 : (has($jobsCols,'city') ? 'j.city'
                   : (has($jobsCols,'state') ? 'j.state' : "'Location TBD'")),
  'category'  => has($jobsCols,'category') ? 'j.category'
                 : (has($jobsCols,'job_category') ? 'j.job_category' : "NULL"),
  'etype'     => has($jobsCols,'employment_type') ? 'j.employment_type'
                 : (has($jobsCols,'type')?'j.type':"'Full Time'"),
  'sal_min'   => has($jobsCols,'salary_min') ? 'j.salary_min'
                 : (has($jobsCols,'min_salary')?'j.min_salary':"NULL"),
  'sal_max'   => has($jobsCols,'salary_max') ? 'j.salary_max'
                 : (has($jobsCols,'max_salary')?'j.max_salary':"NULL"),
  'currency'  => has($jobsCols,'currency') ? 'j.currency'
                 : (has($jobsCols,'salary_currency')?'j.salary_currency':"'USD'"),
  'exp_min'   => has($jobsCols,'experience_min') ? 'j.experience_min'
                 : (has($jobsCols,'min_experience')?'j.min_experience':"NULL"),
  'exp_max'   => has($jobsCols,'experience_max') ? 'j.experience_max'
                 : (has($jobsCols,'max_experience')?'j.max_experience':"NULL"),
  'posted'    => has($jobsCols,'posted_at') ? 'j.posted_at'
                 : (has($jobsCols,'created_at')?'j.created_at'
                   : (has($jobsCols,'date_posted')?'j.date_posted':"NOW()")),
  'company_id'=> has($jobsCols,'company_id') ? 'j.company_id' : "NULL",
];

/* ===== Base column names ===== */
$etypeColName    = has($jobsCols,'employment_type') ? 'employment_type'
                      : (has($jobsCols,'type') ? 'type' : null);
$categoryColName = has($jobsCols,'category') ? 'category'
                      : (has($jobsCols,'job_category') ? 'job_category' : null);
$currencyColName = has($jobsCols,'currency') ? 'currency'
                      : (has($jobsCols,'salary_currency') ? 'salary_currency' : null);

/* ===== Request params ===== */
$q       = trim($_GET['q']   ?? '');
$loc     = trim($_GET['loc'] ?? '');
$exp     = trim($_GET['exp'] ?? '');
$cat     = trim($_GET['cat'] ?? '');
$etype   = trim($_GET['etype'] ?? '');
$cur     = strtoupper(trim($_GET['cur'] ?? ''));
$prefill_min = trim($_GET['salary_min'] ?? '');
$prefill_max = trim($_GET['salary_max'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

/* ===== Currency options (approved only) ===== */
$currencyOptions = [];
if ($currencyColName) {
  try {
    $stmt = $pdo->prepare(
      "SELECT DISTINCT $currencyColName AS cur
       FROM jobs j
       WHERE $approvedClause
         AND $currencyColName IS NOT NULL
         AND $currencyColName <> ''"
    );
    $stmt->execute($approvedParams);
    $currencyOptions = array_values(array_unique(
      array_map(fn($r)=>strtoupper(trim((string)$r['cur'])), $stmt->fetchAll())
    ));
    sort($currencyOptions, SORT_STRING);
  } catch(Throwable $e){
    error_log("Fetch currencies failed: ".$e->getMessage());
  }
}
if (empty($currencyOptions)) {
  $currencyOptions = ['AED','USD','EUR','GBP','SAR','QAR','INR','PKR','BDT','LKR','AUD','CAD','SGD'];
}

/* ===== Salary range buckets ===== */
$salMinCol = has($jobsCols,'salary_min') ? 'salary_min'
             : (has($jobsCols,'min_salary') ? 'min_salary' : null);
$salMaxCol = has($jobsCols,'salary_max') ? 'salary_max'
             : (has($jobsCols,'max_salary') ? 'max_salary' : null);

function getBoundsForCurrency(
  PDO $pdo,
  ?string $minCol,
  ?string $maxCol,
  ?string $currencyCol,
  string $cur,
  string $approvedClause,
  array $approvedParams
): array {
  if (!$cur || !$currencyCol) return [null,null];
  $minVal = null; $maxVal = null;

  if ($minCol) {
    $q=$pdo->prepare(
      "SELECT MIN($minCol) FROM jobs j
       WHERE $approvedClause
         AND $currencyCol = :c
         AND $minCol IS NOT NULL"
    );
    $q->execute([':c'=>$cur] + $approvedParams);
    $n = numOrNull($q->fetchColumn());
    if ($n!==null) $minVal=$n;
  }

  if ($maxCol) {
    $q=$pdo->prepare(
      "SELECT MAX($maxCol) FROM jobs j
       WHERE $approvedClause
         AND $currencyCol = :c
         AND $maxCol IS NOT NULL"
    );
    $q->execute([':c'=>$cur] + $approvedParams);
    $n = numOrNull($q->fetchColumn());
    if ($n!==null) $maxVal=$n;
  }

  if ($minVal===null && $maxVal!==null) $minVal=max(0,(int)round($maxVal*0.1));
  if ($maxVal===null && $minVal!==null) $maxVal=(int)round($minVal*2);
  if ($minVal===null && $maxVal===null) return [null,null];
  if ($maxVal<$minVal) $maxVal=$minVal;
  return [(float)$minVal,(float)$maxVal];
}
function niceStep(float $min, float $max, int $target=7): float {
  $span = max(0.0,$max-$min);
  if ($span<=0) $span = max(1000.0,$max?:1000.0);
  $raw=$span/max(1,$target);
  $pow=pow(10,floor(log10($raw)));
  foreach([1,2,5,10] as $m){
    $step=$m*$pow;
    if ($raw<=$step) return $step;
  }
  return 10*$pow;
}
function rdown(float $x,float $s): int { return (int)floor($x/$s)*(int)$s; }
function rup  (float $x,float $s): int { return (int)ceil ($x/$s)*(int)$s; }

$rangeOptions = [];
$haveCurrency = ($cur!=='');
if ($haveCurrency) {
  [$gMin,$gMax] = getBoundsForCurrency(
    $pdo,$salMinCol,$salMaxCol,$currencyColName,$cur,$approvedClause,$approvedParams
  );
  if ($gMin!==null && $gMax!==null) {
    $step = niceStep($gMin,$gMax,7);
    $low  = max(0, rdown($gMin,$step));
    $high = rup($gMax,$step);
    if ($high <= $low) {
      $rangeOptions = [[$low,$low]];
    } else {
      for ($s=$low; $s<$high; $s=min($high, $s+$step)) {
        $e = min($s+$step, $high);
        $rangeOptions[] = [(int)$s,(int)$e];
        if (count($rangeOptions) > 12) break;
      }
    }
  }
}
$prefill_key = ($prefill_min!=='' && $prefill_max!=='')
  ? ((int)$prefill_min.'-'.(int)$prefill_max)
  : '';

/* ===== Build FILTER WHERE for results ===== */
$conditions = [];
$params = [];

$conditions[] = $approvedClause;
$params = $params + $approvedParams;

if ($q !== '') {
  $like = '%'.$q.'%';
  $parts = [];
  $parts[] = $expr['title']." LIKE :q";
  if ($expr['company'] !== "'Unknown Company'") $parts[] = $expr['company']." LIKE :q";
  if ($expr['category'] !== "NULL") $parts[] = $expr['category']." LIKE :q";
  $parts[] = $expr['location']." LIKE :q";
  $conditions[] = '('.implode(' OR ', $parts).')';
  $params[':q'] = $like;
}
if ($loc !== '') {
  $conditions[] = $expr['location']." LIKE :loc";
  $params[':loc'] = '%'.$loc.'%';
}
if ($cat !== '' && $expr['category'] !== "NULL") {
  $conditions[] = $expr['category']." = :cat";
  $params[':cat'] = $cat;
}
/* Employment Type filter */
if ($etypeColName && $etype !== '') {
  $conditions[] = "REPLACE(UPPER(TRIM(j.$etypeColName)),'-',' ') = :etnorm";
  $params[':etnorm'] = strtoupper(str_replace('-', ' ', trim($etype)));
}
if ($exp !== '') {
  if (preg_match('~^(\d+)\s*-\s*(\d+)~', $exp, $m)) {
    $emin = (int)$m[1]; $emax = (int)$m[2];
    $conditions[] = "(
      (".$expr['exp_min']." IS NULL OR ".$expr['exp_min']." <= :emax)
      AND
      (".$expr['exp_max']." IS NULL OR ".$expr['exp_max']." >= :emin)
    )";
    $params[':emin'] = $emin;
    $params[':emax'] = $emax;
  } elseif (preg_match('~^(\d+)\+~', $exp, $m)) {
    $emin = (int)$m[1];
    $conditions[] = "(".$expr['exp_max']." IS NULL OR ".$expr['exp_max']." >= :emin2)";
    $params[':emin2'] = $emin;
  }
}
if ($cur !== '') {
  $conditions[] = "UPPER(".$expr['currency'].") = :cc";
  $params[':cc'] = $cur;
}
if ($prefill_min !== '' && $prefill_max !== '') {
  $minV = (float)$prefill_min; $maxV = (float)$prefill_max;
  $a = $expr['sal_min']; $b = $expr['sal_max'];
  $conditions[] = "(
      (($a IS NOT NULL AND $a <= :smax) OR $a IS NULL)
      AND
      (($b IS NOT NULL AND $b >= :smin) OR $b IS NULL)
  )";
  $params[':smin'] = $minV;
  $params[':smax'] = $maxV;
}

/* ===== Build SELECT + COUNT for results ===== */
$select = implode(", ", [
  $expr['id']." AS id",
  $expr['title']." AS title",
  $expr['company']." AS company_name",
  $expr['logo']." AS logo",
  $expr['location']." AS location",
  $expr['category']." AS category",
  $expr['etype']." AS employment_type",
  $expr['sal_min']." AS salary_min",
  $expr['sal_max']." AS salary_max",
  $expr['currency']." AS currency",
  $expr['exp_min']." AS experience_min",
  $expr['exp_max']." AS experience_max",
  $expr['posted']." AS posted_at",
]);

$fromJoin = "FROM jobs j ".
            ($hasCompanies && $expr['company_id']!=="NULL"
              ? "LEFT JOIN companies c ON c.id = j.company_id "
              : "");
$whereSql = "WHERE ".implode(" AND ", $conditions);

/* count */
$totalRows = 0;
try {
  $st = $pdo->prepare("SELECT COUNT(*) $fromJoin $whereSql");
  $st->execute($params);
  $totalRows = (int)$st->fetchColumn();
} catch(Throwable $e) {
  error_log("Count failed: ".$e->getMessage());
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* fetch page */
$jobs = [];
try {
  $st = $pdo->prepare(
    "SELECT $select $fromJoin $whereSql
     ORDER BY ".$expr['posted']." DESC, j.id DESC
     LIMIT :lim OFFSET :off"
  );
  foreach ($params as $k=>$v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->execute();
  $jobs = $st->fetchAll();
} catch(Throwable $e) {
  error_log("Fetch jobs failed: ".$e->getMessage());
}

/* ===== Featured Companies (approved only) ===== */
$featured = [];
if ($hasCompanies) {
  $logoExpr = has($coCols,'logos') ? 'c.logos'
              : (has($coCols,'logo') ? 'c.logo'
                : (has($coCols,'logo_url') ? 'c.logo_url' : "NULL"));
  $nameExpr = has($coCols,'name') ? 'c.name' : "'Company'";
  $sqlFeat = "SELECT c.id,
                     $nameExpr AS company_name,
                     $logoExpr AS logo,
                     COUNT(j.id) AS openings
              FROM companies c
              LEFT JOIN jobs j ON j.company_id = c.id AND ($approvedClause)
              GROUP BY c.id, $nameExpr, $logoExpr
              HAVING openings > 0
              ORDER BY openings DESC
              LIMIT 16";
  try {
    $st = $pdo->prepare($sqlFeat);
    $st->execute($approvedParams);
    $featured = $st->fetchAll();
  } catch (Throwable $e) {
    error_log("Featured companies failed: ".$e->getMessage());
  }
}

/* ===== Stats (approved only) ===== */
$totalJobs = 0; $activeCompanies = 0; $countriesCount = 0;
try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM jobs j WHERE $approvedClause");
  $st->execute($approvedParams);
  $totalJobs = (int)$st->fetchColumn();
} catch(Throwable $e){}

try {
  if ($hasCompanies) {
    $st = $pdo->prepare(
      "SELECT COUNT(DISTINCT c.id)
       FROM companies c
       JOIN jobs j ON j.company_id=c.id
       WHERE $approvedClause"
    );
    $st->execute($approvedParams);
    $activeCompanies = (int)$st->fetchColumn();
  } else if (has($jobsCols,'company_name')) {
    $st = $pdo->prepare(
      "SELECT COUNT(DISTINCT j.company_name)
       FROM jobs j
       WHERE $approvedClause
         AND j.company_name IS NOT NULL
         AND j.company_name<>''"
    );
    $st->execute($approvedParams);
    $activeCompanies = (int)$st->fetchColumn();
  }
} catch(Throwable $e){}

try {
  $locCol = has($jobsCols,'location') ? 'location'
           : (has($jobsCols,'city') ? 'city'
             : (has($jobsCols,'state') ? 'state' : null));
  if ($locCol) {
    $st = $pdo->prepare(
      "SELECT DISTINCT $locCol AS l
       FROM jobs j
       WHERE $approvedClause
         AND $locCol IS NOT NULL
         AND $locCol<>'' LIMIT 2000"
    );
    $st->execute($approvedParams);
    $rows = $st->fetchAll();
    $countries = [];
    foreach ($rows as $r) {
      $parts = preg_split('~[,|/|-]~', (string)$r['l']);
      $last = trim((string)end($parts));
      if ($last!=='') $countries[strtoupper($last)] = true;
    }
    $countriesCount = count($countries);
  }
} catch(Throwable $e){}

/* ===== Quick Filters (ONLY: Full Time, Part Time, Remote) ===== */
$quickFilters = [
  ['label'=>'Full Time', 'type'=>'etype', 'value'=>'Full Time'],
  ['label'=>'Part Time', 'type'=>'etype', 'value'=>'Part Time'],
  ['label'=>'Remote',    'type'=>'loc',   'value'=>'remote'],
];

/* ===== UI helpers ===== */
function salaryText($min,$max,$curCode){
  if ($min===null && $max===null) return "Negotiable";
  $n=function($x){ return number_format((float)$x,0,'.',','); };
  $curCode=$curCode?:'USD';
  if ($min!==null && $max!==null) return "$curCode ".$n($min)."–".$n($max);
  if ($min!==null) return "$curCode ".$n($min)."+";
  if ($max!==null) return "Up to $curCode ".$n($max);
  return "Negotiable";
}
function expText($min,$max){
  if ($min===null && $max===null) return "Any level";
  if ($min!==null && $max!==null) return "{$min}-{$max} years";
  if ($min!==null) return "{$min}+ years";
  if ($max!==null) return "Up to {$max} years";
  return "Any level";
}
function buildQuery(array $overrides = []): string {
  $q = array_merge($_GET, $overrides);
  unset($q['page']);
  return http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8" />
  <title><?= h(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= h(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Discover verified, approved jobs worldwide. Filter directly on this page — no redirect.">
  <link rel="icon" type="image/png" href="img/1748025713_MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ["Inter","system-ui","Segoe UI","Roboto","Arial","sans-serif"] },
          colors: {
            brand:"#0156D4",
            brandDark:"#0B3C8C",
            brandAlt:"#00A7B7",
            ink:"#0F172A",
            success:"#059669"
          },
          boxShadow: {
            search:"0 10px 32px rgba(2,6,23,.12)",
            card:"0 4px 12px rgba(15,23,42,.05)"
          },
          backgroundImage:{
            hero:"radial-gradient(1200px 600px at 70% -10%, rgba(1,86,212,.12), transparent 60%), linear-gradient(180deg, #F8FAFF 0%, #FFFFFF 35%)"
          }
        }
      }
    }
  </script>
  <style>
    .pill{border-radius:9999px}
    .snap-x{scroll-snap-type:x mandatory}
    .snap{scroll-snap-align:start}
    .gradient-text{
      background:linear-gradient(135deg,#0156D4,#00A7B7);
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent
    }
    .logo-container{
      background:linear-gradient(135deg,#f8fafc 0%,#fff 100%);
      border:1px solid #e2e8f0
    }
    .job-card:hover{
      transform:translateY(-2px);
      box-shadow:0 8px 25px rgba(15,23,42,.12)
    }
    .search-form{
      background:linear-gradient(135deg,rgba(255,255,255,.97) 0%,rgba(248,250,252,.97) 100%);
      backdrop-filter:blur(10px)
    }
    .stat-card{
      background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%)
    }
    .how-step{
      background:linear-gradient(180deg,#ffffff 0%,#f1f5f9 100%)
    }
    @media (max-width:640px){
      .hero-min{min-height:520px}
      body{overflow-x:hidden}
    }
  </style>
</head>
<body class="bg-white text-ink font-sans overflow-x-hidden min-h-screen flex flex-col">
<?php require_once __DIR__ . '/header.php'; ?>


<main id="main" class="flex-1">
  <!-- Hero + Filters -->
  <section class="bg-hero hero-min flex items-center">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 lg:py-14 w-full">
      <div class="grid lg:grid-cols-2 gap-8 lg:gap-10 items-center">
        <div>
          <div class="inline-flex items-center gap-2 text-[11px] sm:text-sm font-bold text-brandAlt bg-brandAlt/10 rounded-full px-3 sm:px-4 py-1.5 sm:py-2 mb-3 sm:mb-4">
            <div class="h-2 w-2 bg-brandAlt rounded-full animate-pulse"></div>
            <span>Global Careers</span>
            <span class="hidden sm:inline text-slate-500">Middle East • Asia • Europe • Worldwide</span>
          </div>

          <h1 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold leading-tight mb-3 sm:mb-4">
            Find Your Next <span class="gradient-text">Dream Job</span> Worldwide
          </h1>

          <p class="text-slate-600 text-sm sm:text-base lg:text-lg mb-5 sm:mb-7 leading-relaxed max-w-xl">
            Filter right here — no redirect. Pick a <strong>Currency</strong> to unlock a matching <strong>Salary Range</strong>.
          </p>

          <!-- Filter Form -->
          <form method="get"
                class="search-form rounded-2xl shadow-search border border-white/50 p-4 sm:p-6 space-y-4 sm:space-y-5"
                onsubmit="syncRangeHidden()">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
              <label class="flex items-center gap-3 border border-slate-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 bg-white focus-within:ring-2 focus-within:ring-brand/20 focus-within:border-brand transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M10 4a6 6 0 014.472 9.986l4.771 4.771-1.414 1.414-4.771-4.771A6 6 0 1110 4zm0 2a4 4 0 100 8 4 4 0 000-8z"/>
                </svg>
                <input name="q" value="<?=h($q)?>"
                       class="w-full outline-none bg-transparent text-sm placeholder-slate-500"
                       placeholder="Job title, keywords, or company" />
              </label>
              <label class="flex items-center gap-3 border border-slate-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 bg-white focus-within:ring-2 focus-within:ring-brand/20 focus-within:border-brand transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2a7 7 0 017 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 017-7zm0 9.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                </svg>
                <input name="loc" value="<?=h($loc)?>"
                       class="w-full outline-none bg-transparent text-sm placeholder-slate-500"
                       placeholder='City, country, or "remote"' />
              </label>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4">
              <!-- Experience -->
              <select name="exp"
                      class="border border-slate-200 rounded-xl px-3 sm:px-4 py-2.5 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand w-full"
                      onchange="this.form.submit()">
                <option value="">Experience</option>
                <?php foreach (['0-1 years','2-4 years','5-7 years','8-10 years','10+ years'] as $opt): ?>
                  <option <?= ($exp===$opt)?'selected':''; ?>><?=h($opt)?></option>
                <?php endforeach; ?>
              </select>

              <!-- Category -->
              <select name="cat"
                      class="border border-slate-200 rounded-xl px-3 sm:px-4 py-2.5 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand w-full"
                      onchange="this.form.submit()">
                <option value="">Category</option>
                <?php
                if ($categoryColName) {
                  try {
                    $catSql = "SELECT $categoryColName AS cat
                               FROM jobs j
                               WHERE $approvedClause
                                 AND $categoryColName IS NOT NULL
                                 AND TRIM($categoryColName) <> ''
                               GROUP BY $categoryColName
                               ORDER BY $categoryColName
                               LIMIT 24";
                    $st = $pdo->prepare($catSql);
                    $st->execute($approvedParams);
                    foreach ($st->fetchAll() as $r) {
                      $label = trim((string)$r['cat']);
                      $sel = ($cat === $label) ? 'selected' : '';
                      echo '<option '.$sel.'>'.h($label).'</option>';
                    }
                  } catch(Throwable $e){ /* ignore */ }
                }
                ?>
              </select>

              <!-- Currency -->
              <select id="cur" name="cur"
                      class="border border-slate-200 rounded-xl px-3 sm:px-4 py-2.5 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand w-full"
                      onchange="onCurrencyChange(this)">
                <option value="">Currency</option>
                <?php foreach ($currencyOptions as $c): ?>
                  <option value="<?=h($c)?>" <?= ($c===$cur)?'selected':''; ?>><?=h($c)?></option>
                <?php endforeach; ?>
              </select>

              <!-- Salary RANGE -->
              <select id="salary_range"
                      class="border border-slate-200 rounded-xl px-3 sm:px-4 py-2.5 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand w-full <?= $haveCurrency?'':'opacity-50' ?>"
                      <?= $haveCurrency?'':'disabled' ?>
                      onchange="onRangeChange(this)">
                <option value=""><?= $haveCurrency ? 'Salary Range' : 'Select currency' ?></option>
                <?php if ($haveCurrency && !empty($rangeOptions)):
                  foreach ($rangeOptions as [$a,$b]):
                    $key   = $a.'-'.$b;
                    $label = number_format($a,0,'.',',').' – '.number_format($b,0,'.',',');
                    $sel   = ($prefill_key === $key) ? 'selected' : '';
                    echo '<option value="'.h($key).'" '.$sel.'>'.h($label).'</option>';
                  endforeach;
                elseif ($haveCurrency): ?>
                  <option disabled>No salary data for <?=h($cur)?></option>
                <?php endif; ?>
              </select>

              <!-- Hidden numeric range inputs -->
              <input type="hidden" id="salary_min" name="salary_min" value="<?=h($prefill_min)?>">
              <input type="hidden" id="salary_max" name="salary_max" value="<?=h($prefill_max)?>">
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
              <button type="submit"
                      class="pill bg-gradient-to-r from-brand to-brandDark hover:shadow-lg text-white font-bold px-5 sm:px-7 py-2.5 sm:py-3 text-sm sm:text-base transition-all w-full sm:w-auto text-center">
                Search Jobs
              </button>
              <input type="hidden" name="page" value="1">
            </div>
          </form>

          <!-- Quick Filters -->
          <div class="mt-4 sm:mt-6 flex flex-wrap gap-2 text-[11px] sm:text-sm">
            <span class="text-slate-500 font-medium">Quick filters:</span>
            <?php
              foreach ($quickFilters as $qf) {
                $over = ['page'=>1];
                if ($qf['type']==='etype') $over['etype'] = $qf['value'];
                if ($qf['type']==='loc')   $over['loc']   = $qf['value'];
                $href = 'index.php?'.buildQuery($over);
                echo '<a class="pill px-3 py-1.5 bg-white/60 hover:bg-brand hover:text-white border border-slate-200 hover:border-brand transition-all" href="'.h($href).'">'.h($qf['label']).'</a>';
              }
            ?>
          </div>
        </div>

        <!-- Hero Visual + Stats (hidden on very small to keep it clean) -->
        <div class="hidden sm:block">
          <div class="relative max-w-md sm:max-w-none mx-auto">
            <div class="absolute -inset-4 bg-gradient-to-r from-brand/20 to-brandAlt/20 rounded-3xl blur-2xl"></div>
            <div class="relative rounded-3xl border border-white/50 bg-white/80 backdrop-blur p-5 sm:p-6 lg:p-8 shadow-card">
              <img src="img/woman_with_virtual_space_03.jpg"
                   alt="Global careers"
                   class="rounded-2xl w-full h-[260px] sm:h-[320px] lg:h-[400px] object-cover"
                   loading="lazy">
              <div class="grid grid-cols-3 gap-3 mt-4 text-center text-[11px] sm:text-xs">
                <div class="stat-card border rounded-xl py-2.5 sm:py-3">
                  <div class="font-extrabold text-base sm:text-lg"><?= number_format($totalJobs) ?></div>
                  <div class="text-slate-500">Active Jobs</div>
                </div>
                <div class="stat-card border rounded-xl py-2.5 sm:py-3">
                  <div class="font-extrabold text-base sm:text-lg"><?= number_format($activeCompanies) ?></div>
                  <div class="text-slate-500">Companies</div>
                </div>
                <div class="stat-card border rounded-xl py-2.5 sm:py-3">
                  <div class="font-extrabold text-base sm:text-lg"><?= number_format($countriesCount) ?></div>
                  <div class="text-slate-500">Countries</div>
                </div>
              </div>
              <div class="mt-3 sm:mt-4 flex flex-wrap items-center justify-center gap-2 text-[11px] sm:text-xs text-slate-600">
                <span class="pill bg-slate-100 px-3 py-1">✔️ Verified Listings</span>
                <span class="pill bg-slate-100 px-3 py-1">🛡️ Safe Apply</span>
                <span class="pill bg-slate-100 px-3 py-1">⚡ Fast Response</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Featured Companies -->
  <?php if (!empty($featured)): ?>
  <section class="py-7 sm:py-9 lg:py-11 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-5 sm:mb-7">
        <div>
          <h2 class="text-lg sm:text-2xl lg:text-3xl font-bold">Featured Companies</h2>
          <p class="text-slate-600 mt-1 text-xs sm:text-sm lg:text-base">Top employers actively hiring</p>
        </div>
      </div>

      <div id="company-carousel"
           class="flex gap-3 sm:gap-4 overflow-x-auto snap-x scroll-smooth pb-2 sm:pb-3 -mx-2 px-2 sm:mx-0 sm:px-0">
        <?php foreach ($featured as $co):
          $coLogo = resolve_logo($co['logo'] ?? '', 'uploads/logos');
          $companyName = $co['company_name'] ?: 'Company';
          $openings = (int)($co['openings'] ?? 0);
        ?>
          <a href="CompanyProfile.php?id=<?= (int)$co['id'] ?>"
             class="snap min-w-[210px] sm:min-w-[250px] bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 hover:shadow-card transition-all flex items-center gap-3 sm:gap-4">
            <div class="logo-container rounded-xl p-2.5 flex-shrink-0">
              <img src="<?= h($coLogo) ?>"
                   alt="<?= h($companyName) ?> logo"
                   class="h-9 w-9 sm:h-11 sm:w-11 rounded-lg object-cover"
                   loading="lazy"
                   onerror="this.src='img/company-placeholder.png'">
            </div>
            <div class="min-w-0 flex-1">
              <div class="font-bold text-sm sm:text-base lg:text-lg truncate text-ink"><?= h($companyName) ?></div>
              <div class="text-[11px] sm:text-sm mt-1">
                <span class="text-brand font-bold"><?= $openings ?></span>
                <span class="text-slate-600"> open position<?= $openings !== 1 ? 's' : '' ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Results -->
  <section class="py-7 sm:py-9 lg:py-11">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-5 sm:mb-7">
        <div>
          <h2 class="text-lg sm:text-2xl lg:text-3xl font-bold">Job Opportunities</h2>
          <p class="text-slate-600 mt-1 text-xs sm:text-sm lg:text-base">
            Showing <?= number_format(min($perPage, max(0, $totalRows - $offset))) ?>
            of <?= number_format($totalRows) ?> results
            <?= ($cur ? " • Currency: ".h($cur) : "") ?>
          </p>
        </div>
      </div>

      <?php if (empty($jobs)): ?>
        <div class="text-center py-10 sm:py-12">
          <div class="text-5xl sm:text-6xl mb-3 sm:mb-4">🔍</div>
          <div class="text-lg sm:text-xl font-semibold text-slate-600 mb-2">No matching approved jobs</div>
          <div class="text-slate-500 text-sm sm:text-base">Try adjusting filters or clearing the salary/currency.</div>
        </div>
      <?php else: ?>
        <div class="grid gap-3.5 sm:gap-5 md:grid-cols-2 lg:grid-cols-3">
          <?php foreach ($jobs as $job):
            $logoPath = resolve_logo($job['logo'] ?? '', 'uploads/logos');
            $company  = $job['company_name'] ?: 'Unknown Company';
            $salary   = salaryText($job['salary_min'],$job['salary_max'],$job['currency']);
            $expTxt   = expText($job['experience_min'],$job['experience_max']);
            $posted   = (function($d){
              if (!$d) return '';
              $ts = strtotime($d); if ($ts===false) return '';
              $time = time() - $ts;
              if ($time < 60) return 'Just now';
              if ($time < 3600) return floor($time/60) . 'm ago';
              if ($time < 86400) return floor($time/3600) . 'h ago';
              if ($time < 2592000) return floor($time/86400) . 'd ago';
              return date('M j, Y', $ts);
            })($job['posted_at']);
            $etypeTxt = $job['employment_type'] ?: 'Full Time';
            $location = $job['location'] ?: 'Location TBD';
            $category = $job['category'];
            $id = (int)$job['id'];
          ?>
          <div class="job-card bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 hover:shadow-card transition-all">
            <div class="flex gap-3 sm:gap-4 mb-3 sm:mb-4">
              <div class="logo-container rounded-xl p-2 flex-shrink-0">
                <img src="<?= h($logoPath) ?>"
                     alt="<?= h($company) ?> logo"
                     class="h-9 w-9 sm:h-11 sm:w-11 rounded-lg object-cover"
                     loading="lazy"
                     onerror="this.src='img/company-placeholder.png'">
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="font-bold text-sm sm:text-base lg:text-lg truncate text-ink">
                  <a class="hover:text-brand underline-offset-2 hover:underline"
                     href="all-jobs.php?open=<?= $id ?>">
                    <?= h($job['title']) ?>
                  </a>
                </h3>
                <div class="text-slate-600 text-xs sm:text-sm truncate"><?= h($company) ?></div>
                <div class="text-slate-500 text-[11px] sm:text-xs mt-1 truncate"><?= h($location) ?></div>
              </div>
            </div>
            <div class="space-y-2.5 sm:space-y-3">
              <div class="flex flex-wrap gap-1.5 sm:gap-2 text-[11px] sm:text-xs">
                <?php if ($category): ?>
                  <span class="pill bg-brand/10 text-brand px-2.5 py-1 font-medium"><?= h($category) ?></span>
                <?php endif; ?>
                <span class="pill bg-slate-100 text-slate-700 px-2.5 py-1 font-medium"><?= h($etypeTxt) ?></span>
                <span class="pill bg-slate-100 text-slate-700 px-2.5 py-1 font-medium"><?= h($expTxt) ?></span>
              </div>
              <?php if ($salary && $salary!=='Negotiable'): ?>
                <div class="text-success font-semibold text-sm sm:text-base"><?= h($salary) ?></div>
              <?php endif; ?>
              <div class="flex items-center justify-between pt-1.5 sm:pt-2">
                <?php if ($posted): ?>
                  <div class="text-[11px] sm:text-xs text-slate-500">Posted <?= h($posted) ?></div>
                <?php endif; ?>
                <a href="all-jobs.php?open=<?= $id ?>"
                   class="text-xs sm:text-sm font-semibold text-brand hover:text-brandDark whitespace-nowrap">
                  View details →
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div class="mt-6 sm:mt-8 flex flex-wrap items-center justify-center gap-1.5 sm:gap-2">
            <?php
              $base = 'index.php?'.buildQuery();
              $button = function($p,$lbl,$disabled=false,$current=false) use($base){
                $href = $disabled ? '#' : $base.'&page='.$p.'#main';
                $cls  = 'px-2.5 sm:px-3 py-1.5 sm:py-2 border rounded-lg text-xs sm:text-sm ';
                if ($current) $cls.='bg-brand text-white border-brand';
                else $cls.='bg-white hover:bg-slate-50 border-slate-200';
                if ($disabled) $cls.=' opacity-50 cursor-not-allowed';
                echo '<a class="'.h($cls).'" href="'.h($href).'">'.h($lbl).'</a>';
              };
              $button(max(1,$page-1),'« Prev', $page<=1, false);
              $start = max(1, $page-2);
              $end   = min($totalPages, $page+2);
              for($p=$start;$p<=$end;$p++){
                $button($p, (string)$p, false, $p===$page);
              }
              $button(min($totalPages,$page+1),'Next »', $page>=$totalPages, false);
            ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- How it works -->
  <section class="py-7 sm:py-9 lg:py-11">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-6 sm:mb-8">
        <h2 class="text-lg sm:text-2xl md:text-3xl font-bold">How It Works</h2>
        <p class="text-slate-600 mt-2 text-xs sm:text-base">Three simple steps to land your next role</p>
      </div>
      <div class="grid gap-3 sm:gap-4 md:grid-cols-3">
        <div class="how-step border rounded-2xl p-4 sm:p-6">
          <div class="text-2xl sm:text-3xl mb-1.5 sm:mb-2">🔎</div>
          <div class="font-bold text-sm sm:text-lg">Search</div>
          <p class="text-slate-600 mt-1 text-xs sm:text-sm">Use the filters above to narrow jobs without leaving this page.</p>
        </div>
        <div class="how-step border rounded-2xl p-4 sm:p-6">
          <div class="text-2xl sm:text-3xl mb-1.5 sm:mb-2">📄</div>
          <div class="font-bold text-sm sm:text-lg">Review</div>
          <p class="text-slate-600 mt-1 text-xs sm:text-sm">Open job details in the All Jobs page.</p>
        </div>
        <div class="how-step border rounded-2xl p-4 sm:p-6">
          <div class="text-2xl sm:text-3xl mb-1.5 sm:mb-2">🚀</div>
          <div class="font-bold text-sm sm:text-lg">Apply</div>
          <p class="text-slate-600 mt-1 text-xs sm:text-sm">Use the employer’s preferred channel to apply fast.</p>
        </div>
      </div>
    </div>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>

<script>
  // Salary range hidden fields
  function syncRangeHidden(){
    const sel = document.getElementById('salary_range');
    const minI = document.getElementById('salary_min');
    const maxI = document.getElementById('salary_max');
    if (!sel || !minI || !maxI) return;
    if (!sel.value){
      minI.value='';
      maxI.value='';
      return;
    }
    const parts = sel.value.split('-').map(v=>v.trim());
    minI.value = parts[0] || '';
    maxI.value = parts[1] || '';
  }
  function onCurrencyChange(selectEl){
    const minI=document.getElementById('salary_min'),
          maxI=document.getElementById('salary_max'),
          sel=document.getElementById('salary_range');
    if (minI) minI.value='';
    if (maxI) maxI.value='';
    if (sel) sel.selectedIndex=0;
    const form = selectEl.form;
    if (form) {
      const pageHidden = form.querySelector('input[name="page"]');
      if (pageHidden) pageHidden.value = '1';
      form.submit();
    }
  }
  function onRangeChange(sel){
    syncRangeHidden();
    const form = sel.form;
    if (form) {
      const pageHidden = form.querySelector('input[name="page"]');
      if (pageHidden) pageHidden.value = '1';
      form.submit();
    }
  }
  document.addEventListener('DOMContentLoaded', syncRangeHidden);

  // Mobile menu toggle
  document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('mobileMenuButton');
    const panel = document.getElementById('mobileMenu');
    if (!btn || !panel) return;

    btn.addEventListener('click', () => {
      const isHidden = panel.classList.contains('hidden');
      panel.classList.toggle('hidden');
      btn.setAttribute('aria-expanded', String(isHidden));
      btn.setAttribute('aria-label', isHidden ? 'Close menu' : 'Open menu');
    });

    // Close on link click
    panel.querySelectorAll('a').forEach(a=>{
      a.addEventListener('click', ()=> {
        panel.classList.add('hidden');
        btn.setAttribute('aria-expanded','false');
        btn.setAttribute('aria-label','Open menu');
      });
    });
  });
</script>
</body>
</html>
