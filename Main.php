<?php
/* MSJOBS — Worldwide Jobs Home (Enhanced, Fixed Logo Display)
 * - Multi-filter search → all-jobs.php?q=&loc=&exp=&cat=&salary_min=&remote=
 * - Latest Jobs from DB with robust logo handling (supports jobs.logos)
 * - Featured Companies carousel (supports companies.logos)
 * - Global copy & country grid (worldwide focus)
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/settings_helper.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function notEmpty($v): bool { return isset($v) && $v !== '' && $v !== null; }

/* ===== DB CONFIG ===== */
$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "u903588615_root";
$DB_PASS = "Msjobs#1";
$DB_NAME = "u903588615_exaple";

/* ===== Connect PDO ===== */
try {
  $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo "<pre>Database connection failed: ".h($e->getMessage())."</pre>";
  exit;
}

/* ===== Helpers ===== */
function tableExists(PDO $pdo, string $db, string $t): bool {
  $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t LIMIT 1");
  $q->execute([':db'=>$db,':t'=>$t]); return (bool)$q->fetchColumn();
}
function tableCols(PDO $pdo, string $db, string $t): array {
  $q=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t");
  $q->execute([':db'=>$db,':t'=>$t]); return array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN));
}
function has(array $cols, string $c): bool { return in_array($c,$cols,true); }

/** Logo resolver with safe fallback — defaults to uploads/logos */
function resolve_logo(?string $val, string $defaultDir='uploads/logos'): string {
  $placeholder = 'img/company-placeholder.png';
  $v = trim((string)$val);
  if ($v === '') return $placeholder;

  // absolute URL?
  if (preg_match('~^https?://~i', $v)) return $v;

  // normalize slashes
  $v = str_replace('\\','/',$v);

  // if just a filename, prepend uploads/logos
  if (strpos($v,'/') === false) {
    $v = rtrim($defaultDir,'/').'/'.$v;
  }

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

/* ===== Field mapping with fallbacks (logos prioritized) ===== */
$expr = [
  'id'        => 'j.id',
  'title'     => has($jobsCols,'title') ? 'j.title' : (has($jobsCols,'job_title')?'j.job_title':"'Untitled Job'"),
  'company'   => has($jobsCols,'company_name') ? 'j.company_name'
                : (has($jobsCols,'company') ? 'j.company'
                  : ($hasCompanies && has($coCols,'name') ? 'c.name' : "'Unknown Company'")),
  // Prefer jobs.logos, then jobs.logo / jobs.company_logo, then companies.logos/logo/logo_url
  'logo'      => has($jobsCols,'logos') ? 'j.logos'
                : (has($jobsCols,'logo') ? 'j.logo'
                  : (has($jobsCols,'company_logo') ? 'j.company_logo'
                    : ($hasCompanies && has($coCols,'logos') ? 'c.logos'
                      : ($hasCompanies && has($coCols,'logo') ? 'c.logo'
                        : ($hasCompanies && has($coCols,'logo_url') ? 'c.logo_url' : "NULL"))))),
  'location'  => has($jobsCols,'location') ? 'j.location'
                : (has($jobsCols,'city') ? 'j.city'
                  : (has($jobsCols,'state') ? 'j.state' : "'Location TBD'")),
  'category'  => has($jobsCols,'category') ? 'j.category' : (has($jobsCols,'job_category') ? 'j.job_category' : "NULL"),
  'etype'     => has($jobsCols,'employment_type') ? 'j.employment_type' : (has($jobsCols,'type')?'j.type':"'Full-time'"),
  'sal_min'   => has($jobsCols,'salary_min') ? 'j.salary_min' : (has($jobsCols,'min_salary')?'j.min_salary':"NULL"),
  'sal_max'   => has($jobsCols,'salary_max') ? 'j.salary_max' : (has($jobsCols,'max_salary')?'j.max_salary':"NULL"),
  'currency'  => has($jobsCols,'currency') ? 'j.currency' : (has($jobsCols,'salary_currency')?'j.salary_currency':"'USD'"),
  'exp_min'   => has($jobsCols,'experience_min') ? 'j.experience_min' : (has($jobsCols,'min_experience')?'j.min_experience':"NULL"),
  'exp_max'   => has($jobsCols,'experience_max') ? 'j.experience_max' : (has($jobsCols,'max_experience')?'j.max_experience':"NULL"),
  'posted'    => has($jobsCols,'posted_at') ? 'j.posted_at'
                : (has($jobsCols,'created_at')?'j.created_at'
                  : (has($jobsCols,'date_posted')?'j.date_posted':"NOW()")),
  'company_id'=> has($jobsCols,'company_id') ? 'j.company_id' : "NULL",
];

/* ===== Latest Jobs Query ===== */
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

$sqlLatest = "SELECT $select
              FROM jobs j
              ".($hasCompanies ? "LEFT JOIN companies c ON c.id = ".($expr['company_id']!=="NULL"?$expr['company_id']:"j.company_id")." " : "")."
              ORDER BY ".$expr['posted']." DESC, j.id DESC
              LIMIT 12";

try {
  $latestJobs = $pdo->query($sqlLatest)->fetchAll();
} catch (Throwable $e) {
  $latestJobs = [];
  error_log("Latest jobs query failed: " . $e->getMessage());
}

/* ===== Featured Companies ===== */
$featured = [];
if ($hasCompanies) {
  // Prefer companies.logos, then logo, then logo_url
  $coLogo = has($coCols,'logos') ? 'c.logos'
          : (has($coCols,'logo') ? 'c.logo'
          : (has($coCols,'logo_url') ? 'c.logo_url' : "NULL"));
  $nameEx = has($coCols,'name') ? 'c.name' : "'Company'";
  $sqlFeat = "SELECT c.id, $nameEx AS name, $coLogo AS logo, COUNT(j.id) AS openings
              FROM companies c
              LEFT JOIN jobs j ON j.company_id = c.id
              GROUP BY c.id, c.name, $coLogo
              HAVING openings > 0
              ORDER BY openings DESC
              LIMIT 16";
  try {
    $featured = $pdo->query($sqlFeat)->fetchAll();
  } catch (Throwable $e) { 
    $featured = [];
    error_log("Featured companies query failed: " . $e->getMessage());
  }
}

/* ===== Request params (for prefilling search) ===== */
$q   = trim($_GET['q']   ?? '');
$loc = trim($_GET['loc'] ?? '');

/* ===== Formatting helpers ===== */
function fmtSalary($min,$max,$cur): string{
  if ($min===null && $max===null) return "Negotiable";
  $n=function($x){ return number_format((float)$x,0,'.',','); };
  $cur=$cur?:'USD';
  if ($min!==null && $max!==null) return "$cur ".$n($min)."–".$n($max);
  if ($min!==null) return "$cur ".$n($min)."+";
  if ($max!==null) return "Up to $cur ".$n($max);
  return "Negotiable";
}
function fmtExp($min,$max): string{
  if ($min===null && $max===null) return "Any level";
  if ($min!==null && $max!==null) return "{$min}-{$max} years";
  if ($min!==null) return "{$min}+ years";
  if ($max!==null) return "Up to {$max} years";
  return "Any level";
}
function timeAgo($datetime): string {
  if (!$datetime) return '';
  $time = time() - strtotime($datetime);
  if ($time < 60) return 'Just now';
  if ($time < 3600) return floor($time/60) . 'm ago';
  if ($time < 86400) return floor($time/3600) . 'h ago';
  if ($time < 2592000) return floor($time/86400) . 'd ago';
  return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?= h(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= h(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Discover verified jobs worldwide across the USA, UK, Europe, Middle East, and Asia-Pacific. Search by title, location, experience, category & salary. Upload your CV and get hired faster.">
  <meta name="keywords" content="global jobs, worldwide jobs, remote jobs, USA jobs, UK jobs, Europe jobs, Middle East jobs, Asia jobs, Canada jobs, Australia jobs, careers">
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ["Inter","system-ui","Segoe UI","Roboto","Arial","sans-serif"] },
          colors: { 
            brand: "#0156D4",
            brandDark: "#0B3C8C",
            brandLight: "#4A90E2",
            brandAlt: "#00A7B7",
            ink: "#0F172A",
            success: "#059669",
            warning: "#D97706"
          },
          boxShadow: { 
            search: "0 10px 32px rgba(2, 6, 23, .12)", 
            soft: "0 6px 18px rgba(15, 23, 42, .08)",
            card: "0 4px 12px rgba(15, 23, 42, .05)"
          },
          backgroundImage: {
            hero: "radial-gradient(1200px 600px at 70% -10%, rgba(1,86,212,.12), transparent 60%), linear-gradient(180deg, #F8FAFF 0%, #FFFFFF 35%)"
          },
          animation: {
            'fade-in': 'fadeIn 0.5s ease-in-out',
            'slide-up': 'slideUp 0.6s ease-out'
          }
        }
      }
    }
  </script>
  <style>
    .pill { border-radius: 9999px; }
    .snap-x { scroll-snap-type: x mandatory; }
    .snap { scroll-snap-align: start; }
    .gradient-text { background: linear-gradient(135deg, #0156D4, #00A7B7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .logo-container { 
      background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
      border: 1px solid #e2e8f0;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .job-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(15, 23, 42, .12); }
    .company-card:hover { transform: translateY(-1px); }
    .search-form { 
      background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.95) 100%);
      backdrop-filter: blur(10px);
    }
  </style>
</head>
<body class="bg-white text-ink font-sans">

  <!-- Sticky Navigation -->
<?php require_once __DIR__ . '/header.php'; ?>


  <!-- Hero -->
  <section class="bg-hero min-h-[600px] flex items-center">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20">
      <div class="grid lg:grid-cols-2 gap-12 items-center">
        <div class="animate-slide-up">
          <div class="inline-flex items-center gap-2 text-xs font-bold text-brandAlt bg-brandAlt/10 rounded-full px-4 py-2 mb-4">
            <div class="h-2 w-2 bg-brandAlt rounded-full animate-pulse"></div>
            <span>Global Careers</span>
            <span class="text-slate-500">Americas • Europe • Middle East • Africa • APAC</span>
          </div>

          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-4">
            Find Your Next <span class="gradient-text">Dream Job</span> Worldwide
          </h1>

          <p class="text-slate-600 text-lg lg:text-xl mb-8 leading-relaxed">
            Search thousands of verified roles across top companies in every region. Filter by experience, category, salary, and work style to find the perfect match.
          </p>

          <!-- Multi-filter Search -->
          <form action="all-jobs.php" method="get" class="search-form rounded-2xl shadow-search border border-white/50 p-4 sm:p-6 space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
              <label class="flex items-center gap-3 border border-slate-200 rounded-xl px-4 py-3 bg-white focus-within:ring-2 focus-within:ring-brand/20 focus-within:border-brand transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M10 4a6 6 0 014.472 9.986l4.771 4.771-1.414 1.414-4.771-4.771A6 6 0 1110 4zm0 2a4 4 0 100 8 4 4 0 000-8z"/>
                </svg>
                <input name="q" value="<?=h($q)?>" class="w-full outline-none bg-transparent text-sm placeholder-slate-500" placeholder="Job title, keywords, or company" />
              </label>
              <label class="flex items-center gap-3 border border-slate-200 rounded-xl px-4 py-3 bg-white focus-within:ring-2 focus-within:ring-brand/20 focus-within:border-brand transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2a7 7 0 017 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 017-7zm0 9.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                </svg>
                <input name="loc" value="<?=h($loc)?>" class="w-full outline-none bg-transparent text-sm placeholder-slate-500" placeholder='City, country, or "remote"' />
              </label>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
              <select name="exp" class="border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand">
                <option value="">Experience Level</option>
                <option>0-1 years</option>
                <option>2-4 years</option>
                <option>5-7 years</option>
                <option>8-10 years</option>
                <option>10+ years</option>
              </select>

              <select name="cat" class="border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand">
                <option value="">Job Category</option>
                <option>Accounting & Finance</option>
                <option>Admin & HR</option>
                <option>Construction</option>
                <option>Engineering</option>
                <option>Healthcare</option>
                <option>Hospitality</option>
                <option>IT & Software</option>
                <option>Logistics</option>
                <option>Sales & Marketing</option>
                <option>Security</option>
                <option>Education</option>
                <option>Design & Creative</option>
              </select>

              <select name="salary_min" class="border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white focus:ring-2 focus:ring-brand/20 focus:border-brand">
                <option value="">Min Salary (USD)</option>
                <option value="1000">1,000</option>
                <option value="2000">2,000</option>
                <option value="4000">4,000</option>
                <option value="6000">6,000</option>
                <option value="10000">10,000</option>
                <option value="15000">15,000</option>
                <option value="25000">25,000</option>
              </select>
            </div>

            <div class="flex items-center justify-between gap-4">
              <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="remote" value="1" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/20">
                <span>Remote / Hybrid</span>
              </label>
              <button type="submit" class="pill bg-gradient-to-r from-brand to-brandDark hover:shadow-lg text-white font-bold px-8 py-3 transition-all transform hover:scale-105">
                Search Jobs
              </button>
            </div>
          </form>

          <!-- Trending keywords -->
          <div class="mt-6 flex flex-wrap gap-2 text-sm">
            <span class="text-slate-500 font-medium">Trending:</span>
            <?php
              $trending = ['Software Engineer','Data Analyst','Accountant','Project Manager','Nurse','Digital Marketer','Civil Engineer','HR Specialist'];
              foreach ($trending as $keyword):
            ?>
              <a class="pill px-3 py-1.5 bg-white/60 hover:bg-brand hover:text-white border border-slate-200 hover:border-brand transition-all" 
                 href="all-jobs.php?q=<?=h(urlencode($keyword))?>"><?=$keyword?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Hero Visual -->
        <div class="hidden lg:block animate-fade-in">
          <div class="relative">
            <div class="absolute -inset-4 bg-gradient-to-r from-brand/20 to-brandAlt/20 rounded-3xl blur-2xl"></div>
            <div class="relative rounded-3xl border border-white/50 bg-white/80 backdrop-blur p-8 shadow-card">
              <img src="img/woman_with_virtual_space_03.jpg" alt="Global careers" class="rounded-2xl w-full h-[400px] object-cover" loading="lazy">
              <div class="absolute -bottom-6 left-8 right-8 bg-white rounded-2xl shadow-card border border-slate-200 p-6 flex items-center justify-between">
                <!--<div>-->
                <!--  <div class="font-bold text-brand">Upload your CV today</div>-->
                <!--  <div class="text-slate-600 text-sm">Get matched with top employers worldwide</div>-->
                <!--</div>-->
                <!--<a href="resume-upload.php" class="pill bg-gradient-to-r from-brandAlt to-success hover:shadow-lg text-white px-5 py-3 text-sm font-bold transition-all">-->
                <!--  Upload CV-->
                <!--</a>-->
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Countries / Regions (Worldwide) -->
  <section class="py-12 sm:py-16 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <h2 class="text-2xl sm:text-3xl font-bold mb-8 text-center">Explore Jobs by Country / Region</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php
          $countries = [
            ['United States','NYC, SF, Austin','🇺🇸','us'],
            ['United Kingdom','London, Manchester','🇬🇧','uk'],
            ['Canada','Toronto, Vancouver','🇨🇦','ca'],
            ['Germany','Berlin, Munich','🇩🇪','de'],
            ['India','Bengaluru, Mumbai','🇮🇳','in'],
            ['Australia','Sydney, Melbourne','🇦🇺','au'],
            ['UAE','Dubai, Abu Dhabi','🇦🇪','ae'],
            ['Saudi Arabia','Riyadh, Jeddah','🇸🇦','sa'],
            ['Singapore','City-wide','🇸🇬','sg'],
            ['Qatar','Doha','🇶🇦','qa'],
            ['Sri Lanka','Colombo','🇱🇰','lk'],
            ['Remote','Global','🌍','remote'],
          ];
          foreach ($countries as [$name,$cities,$flag,$code]): ?>
          <a class="group border border-slate-200 rounded-xl p-5 hover:shadow-card hover:border-brand/30 transition-all bg-white" href="all-jobs.php?loc=<?=h(urlencode($name))?>">
            <div class="text-2xl mb-2"><?=$flag?></div>
            <div class="font-bold text-lg group-hover:text-brand transition-colors"><?=$name?></div>
            <div class="text-xs text-slate-500 mt-1"><?=$cities?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Categories -->
<section class="py-12 sm:py-16">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="text-2xl sm:text-3xl font-bold mb-8 text-center">Popular Job Categories</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php
        $categories = [
          ['IT & Software','all-jobs.php?cat=IT%20%26%20Software','💻'],
          ['Engineering','all-jobs.php?cat=Engineering','🛠️'],
          ['Healthcare','all-jobs.php?cat=Healthcare','🏥'],
          ['Construction','all-jobs.php?cat=Construction','🏗️'],
          ['Hospitality','all-jobs.php?cat=Hospitality','🏨'],
          ['Sales & Marketing','all-jobs.php?cat=Sales%20%26%20Marketing','📈'],
          ['Accounting & Finance','all-jobs.php?cat=Accounting%20%26%20Finance','💰'],
          ['Logistics','all-jobs.php?cat=Logistics','🚛'],
        ];
        foreach ($categories as [$label,$href,$emoji]): ?>
        <a href="<?=$href?>" class="group rounded-2xl overflow-hidden border border-slate-200 bg-white hover:shadow-card hover:border-brand/30 transition-all p-6 flex flex-col items-center text-center">
          <div class="text-5xl mb-4"><?=$emoji?></div>
          <div class="font-bold text-lg group-hover:text-brand transition-colors"><?=$label?></div>
          <div class="text-sm text-slate-600 mt-1">Browse opportunities</div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

  <!-- Featured Companies -->
  <?php if (!empty($featured)): ?>
  <section class="py-12 sm:py-16 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h2 class="text-2xl sm:3xl md:text-3xl font-bold">Featured Companies</h2>
          <p class="text-slate-600 mt-1">Top employers actively hiring around the world</p>
        </div>
        <div class="hidden sm:flex gap-2">
          <button aria-label="Previous" class="pill border border-slate-300 bg-white hover:bg-slate-50 px-4 py-2 text-lg font-bold transition-all" onclick="scrollBySnap('company-carousel',-1)">‹</button>
          <button aria-label="Next" class="pill border border-slate-300 bg-white hover:bg-slate-50 px-4 py-2 text-lg font-bold transition-all" onclick="scrollBySnap('company-carousel',1)">›</button>
        </div>
      </div>
      
      <div id="company-carousel" class="flex gap-4 overflow-x-auto snap-x scroll-smooth pb-4">
        <?php foreach ($featured as $co):
          $coLogo = resolve_logo($co['logo'] ?? '', 'uploads/logos');
          $companyName = $co['name'] ?: 'Company';
          $openings = (int)$co['openings'];
        ?>
          <a href="CompanyProfile.php?id=<?= (int)$co['id'] ?>" class="company-card snap min-w-[280px] bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-card hover:border-brand/30 transition-all flex items-center gap-4">
            <div class="logo-container rounded-xl p-3 flex-shrink-0">
              <img src="<?= h($coLogo) ?>" alt="<?= h($companyName) ?> logo"
                   class="h-12 w-12 rounded-lg object-cover" loading="lazy"
                   onerror="this.src='img/company-placeholder.png'">
            </div>
            <div class="min-w-0 flex-1">
              <div class="font-bold text-lg truncate text-ink group-hover:text-brand transition-colors"><?= h($companyName) ?></div>
              <div class="text-sm mt-1">
                <span class="text-brand font-bold"><?= $openings ?></span>
                <span class="text-slate-600"> open position<?= $openings !== 1 ? 's' : '' ?></span>
              </div>
              <div class="text-xs text-slate-500 mt-1">Click to view profile</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Latest Jobs -->
  <section class="py-12 sm:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h2 class="text-2xl sm:text-3xl font-bold">Latest Job Opportunities</h2>
          <p class="text-slate-600 mt-1">Fresh roles posted by top employers</p>
        </div>
        <a href="all-jobs.php" class="pill bg-brand hover:bg-brandDark text-white px-6 py-3 font-semibold transition-all">
          View All Jobs
        </a>
      </div>

      <?php if (empty($latestJobs)): ?>
        <div class="text-center py-12">
          <div class="text-6xl mb-4">🔍</div>
          <div class="text-xl font-semibold text-slate-600 mb-2">No jobs found yet</div>
          <div class="text-slate-500">Check back soon for new opportunities</div>
        </div>
      <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($latestJobs as $job):
            // Use the aliased "logo" from SELECT (mapped to j.logos if available)
            $logoPath = resolve_logo($job['logo'] ?? '', 'uploads/logos');
            $company  = $job['company_name'] ?: 'Unknown Company';
            $salary   = fmtSalary($job['salary_min'], $job['salary_max'], $job['currency']);
            $expTxt   = fmtExp($job['experience_min'], $job['experience_max']);
            $posted   = timeAgo($job['posted_at']);
            $etype    = $job['employment_type'] ?: 'Full-time';
            $location = $job['location'] ?: 'Location TBD';
            $category = $job['category'];
          ?>
          <a href="job.php?id=<?= (int)$job['id'] ?>" class="job-card bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-card transition-all">
            <div class="flex gap-4 mb-4">
              <div class="logo-container rounded-xl p-2 flex-shrink-0">
                <img src="<?= h($logoPath) ?>" alt="<?= h($company) ?> logo"
                     class="h-12 w-12 rounded-lg object-cover" loading="lazy"
                     onerror="this.src='img/company-placeholder.png'">
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="font-bold text-lg truncate text-ink hover:text-brand transition-colors"><?= h($job['title']) ?></h3>
                <div class="text-slate-600 text-sm truncate"><?= h($company) ?></div>
                <div class="text-slate-500 text-sm flex items-center gap-1 mt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2a7 7 0 017 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 017-7zm0 9.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                  </svg>
                  <?= h($location) ?>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex flex-wrap gap-2 text-xs">
                <?php if ($category): ?>
                  <span class="pill bg-brand/10 text-brand px-3 py-1 font-medium"><?= h($category) ?></span>
                <?php endif; ?>
                <span class="pill bg-slate-100 text-slate-700 px-3 py-1 font-medium"><?= h($etype) ?></span>
                <span class="pill bg-slate-100 text-slate-700 px-3 py-1 font-medium"><?= h($expTxt) ?></span>
              </div>

              <?php if ($salary && $salary !== 'Negotiable'): ?>
                <div class="flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-success" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/>
                  </svg>
                  <span class="font-semibold text-success"><?= h($salary) ?></span>
                </div>
              <?php endif; ?>

              <?php if ($posted): ?>
                <div class="text-xs text-slate-500 border-t pt-3">
                  Posted <?= h($posted) ?>
                </div>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>

        <!-- CTA -->
        <div class="mt-12 text-center">
          <div class="inline-block bg-gradient-to-r from-brand/10 to-brandAlt/10 rounded-3xl p-8">
            <h3 class="text-2xl font-bold mb-2">Ready to find your dream job?</h3>
            <!--<p class="text-slate-600 mb-6">Upload your CV and let top employers find you</p>-->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
              <!--<a href="resume-upload.php" class="pill bg-gradient-to-r from-brandAlt to-success hover:shadow-lg text-white px-8 py-4 font-bold transition-all">-->
              <!--  Upload CV Now-->
              <!--</a>-->
              <a href="all-jobs.php" class="pill border-2 border-brand text-brand hover:bg-brand hover:text-white px-8 py-4 font-bold transition-all">
                Browse All Jobs
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>



  <script>
    function scrollBySnap(id, direction) {
      const container = document.getElementById(id);
      if (!container) return;
      const card = container.querySelector('.snap');
      const cardWidth = card ? card.getBoundingClientRect().width + 16 : 300;
      container.scrollBy({ left: direction * cardWidth, behavior: 'smooth' });
    }

    function toggleMobileNav() {
      const nav = document.getElementById('mobile-nav');
      if (nav) { nav.classList.toggle('hidden'); }
    }

    // Auto-scroll for company carousel (desktop only)
    let autoScrollInterval;
    const startAutoScroll = () => {
      const carousel = document.getElementById('company-carousel');
      if (!carousel) return;
      autoScrollInterval = setInterval(() => {
        scrollBySnap('company-carousel', 1);
        if (carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 10) {
          setTimeout(() => { carousel.scrollTo({ left: 0, behavior: 'smooth' }); }, 2000);
        }
      }, 4000);
    };

    document.addEventListener('DOMContentLoaded', () => {
      if (window.innerWidth >= 1024) { startAutoScroll(); }
      const carousel = document.getElementById('company-carousel');
      if (carousel) {
        carousel.addEventListener('mouseenter', () => clearInterval(autoScrollInterval));
        carousel.addEventListener('mouseleave', startAutoScroll);
      }
    });
  </script>
</body>
</html>
