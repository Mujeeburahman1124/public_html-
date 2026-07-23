<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$DB_HOST = $servername;
$DB_USER = $username;
$DB_PASS = $password;
$DB_NAME = $dbname;
$database = $dbname;

/************************************************************
 * MSJOBS — ALL JOBS (Indeed-like)
 * Desktop: Left list + highlighted selection, right sticky detail
 * Mobile: Full-screen job view (back arrow + sticky Apply)
 * - Deep link: ?open=<ID>&solo=1
 * - Apply: login.php?next=<current-url>
 * - Share: SHARE_CANONICAL?open=<ID>&solo=1
 * - Filters: DISTINCT from DB over approved jobs
 * - Show-all: &all=1 (no pagination, when not solo)
 ************************************************************/
session_start();

ini_set('display_errors','1'); // turn off in production
error_reporting(E_ALL);

/* ==== CANONICAL SHARE BASE ==== */
define('SHARE_CANONICAL','https://msjobs.net/all-jobs');

/* ==== PATHS (logos) ==== */
define('LOGO_DIR', __DIR__.'/uploads/logos');
define('LOGO_PUBLIC', 'uploads/logos');
define('LOGO_PLACEHOLDER','img/new-company-placeholder.png');

/* ==== DB ==== */
// $DB_HOST = "127.0.0.1"; (Refactored to config.php)
$host_parts = explode(':', (string)$DB_HOST);
$DB_HOST_ONLY = $host_parts[0];
$DB_PORT = isset($host_parts[1]) ? (int)$host_parts[1] : 3306;
// $DB_USER = "u903588615_root"; (Refactored to config.php)
// $DB_PASS = "Msjobs#1"; (Refactored to config.php)
// $DB_NAME = "u903588615_exaple"; (Refactored to config.php)

/* ==== CONNECT ==== */
try {
  $mysqli = @new mysqli($DB_HOST_ONLY, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  die('Database connection failed.');
}
if ($mysqli->connect_error) { http_response_code(500); die('Database connection failed.'); }
$mysqli->set_charset('utf8mb4');

$isLoggedIn = !empty($_SESSION['user']);

/* ==== HELPERS ==== */
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function resolveLogoUrl(?string $logo): string {
  $logo = trim((string)$logo);
  if ($logo !== '' && (stripos($logo,'http://')===0 || stripos($logo,'https://')===0 || stripos($logo,'data:image')===0)) return $logo;
  if ($logo !== '') {
    $b = basename($logo);
    if (@file_exists(LOGO_DIR.'/'.$b)) return LOGO_PUBLIC.'/'.$b;
    $rel = ltrim($logo,'/');
    if (@file_exists(__DIR__.'/'.$rel)) return $rel;
  }
  return LOGO_PLACEHOLDER;
}
function detectLogoColumn(mysqli $db, string $schema, string $table='jobs'): ?string {
  $candidates = ['logo','logos','company_logo'];
  $res = $db->query("SHOW COLUMNS FROM `$table`");
  if ($res === false) return null;
  $found = [];
  while($r=$res->fetch_assoc()) $found[]=$r['Field'];
  foreach($candidates as $c) if(in_array($c,$found,true)) return $c;
  return null;
}

/* Description → bullets/chips */
function extract_structured_points(?string $html): array {
  $t = trim((string)$html); if ($t==='') return [];
  $plain = preg_replace('~\s+~u',' ', strip_tags($t));
  $chunks = preg_split('~(?:(?:^|\s)[\-•·—]\s*|\s*;\s*|\s*\.\s*|\s*\|\s*|\s*/\s*)~u',$plain);
  $chunks = array_values(array_filter(array_map('trim',$chunks)));
  $map = [
    'duty\s*time|work\s*hours?' =>'Duty Time', 'overtime'=>'Overtime',
    'lunch\s*break|break'=>'Lunch Break', 'weekly\s*day\s*off|week\s*off|day\s*off'=>'Weekly Day Off',
    'yearly\s*vacation|annual\s*leave|vacation'=>'Yearly Vacation',
    'salary|pay|wage'=>'Salary', 'accommodation|room|housing'=>'Accommodation', 'insurance'=>'Insurance',
    'medical|health'=>'Medical', 'transport|bus|vehicle'=>'Transport',
    'contract\s*duration|duration'=>'Contract Duration', 'visa'=>'Visa',
    'air\s*ticket|airfare|ticket'=>'Air Ticket', 'allowance'=>'Allowance', 'food|meal'=>'Food',
    'bonus|gratuity'=>'Bonus/Gratuity', 'shift'=>'Shift', 'experience'=>'Experience',
    'education|qualification'=>'Education', 'language'=>'Language', 'age'=>'Age',
    'nationality'=>'Nationality', 'location'=>'Location', 'notes?|others?'=>'Notes'
  ];
  $order=['Duty Time','Overtime','Lunch Break','Weekly Day Off','Yearly Vacation','Salary','Accommodation','Insurance','Medical','Transport','Contract Duration','Visa','Air Ticket','Allowance','Food','Bonus/Gratuity','Shift','Experience','Education','Language','Age','Nationality','Location','Notes'];

  $found=[];
  foreach($chunks as $c){
    $c = trim($c," \t\n\r\0\x0B.-•·—"); if ($c==='') continue;
    if (preg_match('~^\s*([a-zA-Z][a-zA-Z\s/]+?)\s*(?:[:\-—]\s*|\s+)\s*(.+)$~u',$c,$m)) {
      $label = mb_strtolower(trim($m[1])); $val = trim($m[2]);
    } else { $label=''; $val=$c; }
    $canon = null;
    foreach($map as $re=>$name){
      if ($label!=='' && preg_match('~'.$re.'~iu',$label)) { $canon=$name; break; }
      if ($label==='' && preg_match('~\b'.$re.'\b~iu',$c)) { $canon=$name; break; }
    }
    if ($canon===null) $canon='Notes';
    if (!isset($found[$canon])) $found[$canon]=$val;
    elseif (mb_stripos($found[$canon],$val)===false) $found[$canon].='; '.$val;
  }
  uksort($found,function($a,$b)use($order){
    $ia=array_search($a,$order,true); if($ia===false)$ia=PHP_INT_MAX;
    $ib=array_search($b,$order,true); if($ib===false)$ib=PHP_INT_MAX;
    return $ia<=>$ib;
  });
  return $found;
}
function render_structured_bullets(?string $html, int $max = 6): string {
  $kv = extract_structured_points($html);
  if (!$kv) return '';
  $out = '<ul class="desc-bullets">';
  $i=0; foreach($kv as $k=>$v){ if($i++>=$max) break; $out.='<li><b>'.h($k).':</b> '.h($v).'</li>'; }
  return $out.'</ul>';
}

/* DISTINCT filters from DB */
function fetchDistinct(string $col, mysqli $db): array {
  $allowed = ['category','type','experience_level','location'];
  if (!in_array($col,$allowed,true)) return [];
  $sql = "SELECT DISTINCT $col v FROM jobs WHERE status='approved' AND COALESCE($col,'')<>'' ORDER BY v ASC";
  $rows=[]; if($res=$db->query($sql)){ while($r=$res->fetch_assoc()) $rows[]=$r['v']; $res->free(); }
  return $rows;
}

/* ==== INPUTS ==== */
$q        = trim($_GET['q'] ?? '');
$loc      = trim($_GET['loc'] ?? '');
$category = trim($_GET['category'] ?? '');
$type     = trim($_GET['type'] ?? '');
$exp      = trim($_GET['exp'] ?? '');
$floc     = trim($_GET['floc'] ?? '');
$sort     = trim($_GET['sort'] ?? 'relevance'); // relevance/date/salary_x
$openId   = (int)($_GET['open'] ?? 0);
$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page-1)*$per_page;

$soloParam = isset($_GET['solo']) ? ($_GET['solo']==='1') : null;
$solo      = ($openId>0) ? ($soloParam ?? true) : false;
$showAll   = (!$solo) && (($_GET['all'] ?? '')==='1');

/* ==== filter options ==== */
$categories = fetchDistinct('category',$mysqli);
$types      = fetchDistinct('type',$mysqli);
$exps       = fetchDistinct('experience_level',$mysqli);
$locations  = fetchDistinct('location',$mysqli);

/* ==== WHERE ==== */
$where=["status='approved'"]; $params=[]; $ptypes='';
if($q!==''){ $like='%'.$q.'%'; $where[]="(title LIKE ? OR company_name LIKE ? OR description LIKE ? OR category LIKE ?)"; array_push($params,$like,$like,$like,$like); $ptypes.='ssss'; }
if($loc!==''){ $where[]="location LIKE ?"; $params[]='%'.$loc.'%'; $ptypes.='s'; }
if($floc!==''){ $where[]="location = ?"; $params[]=$floc; $ptypes.='s'; }
if($category!==''){ $where[]="category = ?"; $params[]=$category; $ptypes.='s'; }
if($type!==''){ $where[]="type = ?"; $params[]=$type; $ptypes.='s'; }
if($exp!==''){ $where[]="experience_level = ?"; $params[]=$exp; $ptypes.='s'; }

$orderBy="created_at DESC, id DESC"; // relevance proxy
if($sort==='date') $orderBy="created_at DESC, id DESC";
if($sort==='salary_desc') $orderBy="COALESCE(max_salary,min_salary) DESC, created_at DESC";
if($sort==='salary_asc')  $orderBy="COALESCE(min_salary,max_salary) ASC, created_at DESC";
$whereSql = implode(' AND ',$where);

/* ==== COUNT/PAGES ==== */
if($solo){
  $st=$mysqli->prepare("SELECT COUNT(*) c FROM jobs WHERE status='approved' AND id=?");
  $st->bind_param('i',$openId); $st->execute(); $res=$st->get_result(); $total=(int)($res->fetch_assoc()['c']??0); $st->close();
  $pages=1; $page=1; $offset=0;
}else{
  $st=$mysqli->prepare("SELECT COUNT(*) c FROM jobs WHERE $whereSql");
  if($ptypes!=='') $st->bind_param($ptypes, ...$params);
  $st->execute(); $res=$st->get_result(); $total=(int)($res->fetch_assoc()['c']??0); $st->close();
  if($showAll){ $per_page=max(1,$total); $pages=1; $page=1; $offset=0; }
  else { $pages=max(1,(int)ceil($total/$per_page)); if($page>$pages){ $page=$pages; $offset=($page-1)*$per_page; } }
}

/* ==== DATA ==== */
$logoCol = detectLogoColumn($mysqli,$DB_NAME,'jobs');
$logoSel = $logoCol ? "`$logoCol` AS logo_any" : "NULL AS logo_any";

if($solo){
  $sql="SELECT id,title,company_name,$logoSel,category,type,experience_level,location,is_remote,
               min_salary,max_salary,currency,description,created_at,company_id
        FROM jobs WHERE status='approved' AND id=? LIMIT 1";
  $st=$mysqli->prepare($sql); $st->bind_param('i',$openId);
}else{
  $sql="SELECT id,title,company_name,$logoSel,category,type,experience_level,location,is_remote,
               min_salary,max_salary,currency,description,created_at,company_id
        FROM jobs WHERE $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?";
  $st=$mysqli->prepare($sql); $bindTypes=$ptypes.'ii'; $bindParams=$params; $bindParams[]=$per_page; $bindParams[]=$offset; $st->bind_param($bindTypes, ...$bindParams);
}
$st->execute(); $rs=$st->get_result();

$jobs=[];
while($r=$rs->fetch_assoc()){
  $r['logo_url']=resolveLogoUrl($r['logo_any']??'');
  $sym=$r['currency']?:'$'; $fmt=fn($v)=>number_format((float)$v,0);
  if($r['min_salary'] && $r['max_salary']) $r['money_range']=$sym.$fmt($r['min_salary'])." – ".$sym.$fmt($r['max_salary']);
  elseif($r['min_salary']) $r['money_range']=$sym.$fmt($r['min_salary'])."+";
  elseif($r['max_salary']) $r['money_range']="Up to ".$sym.$fmt($r['max_salary']);
  else $r['money_range']="Pay not provided";
  $r['bullets']=render_structured_bullets($r['description']??'',6);
  $jobs[]=$r;
}
$st->close();

/* ==== QS helpers ==== */
function buildQS(array $skip=['page']): string { $q=$_GET; foreach($skip as $k) unset($q[$k]); return http_build_query($q); }
function currUrl(): string {
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
  return $scheme.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}
$qs = buildQS();
$backUrl = 'all-jobs.php?'.buildQS(['open','solo','page']);
$hasOpen = $openId>0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MSJOBS — Jobs</title>
<link rel="icon" type="image/png" href="img/MS copy.png">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
  [x-cloak]{display:none!important}
  :root{
    --brand:#2557a7; --ink:#1f2328; --muted:#5a5f66; --bg:#f6f7f9; --card:#fff; --border:#e4e7eb;
    --radius:12px; --shadow:0 8px 18px rgba(0,0,0,.06);
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink)}
  .container{max-width:1200px;margin:0 auto;padding:0 16px}

  /* Header */
  .hdr{position:sticky;top:0;z-index:60;background:#fff;border-bottom:1px solid var(--border)}
  .hdr-in{display:flex;align-items:center;gap:18px;height:60px}
  .logo{color:var(--brand);font-weight:900;font-size:24px;text-decoration:none}
  .nav{display:flex;gap:16px}
  .nav a{color:var(--ink);text-decoration:none;font-size:14px;padding:6px 0;border-bottom:2px solid transparent}
  .nav a.active{border-bottom-color:var(--brand);font-weight:700}
  .sp{flex:1}
  .auth a{color:var(--brand);text-decoration:none;font-weight:700}
  .mbtn{display:none;border:0;background:transparent;font-size:20px}
  .mnav{display:none;border-top:1px solid var(--border);padding:8px 0}
  .mnav a{display:block;color:var(--ink);text-decoration:none;padding:10px 0}

  /* Search */
  .srch{background:#fff;border-bottom:1px solid var(--border)}
  .sr-in{padding:14px 0}
  .sform{display:flex;max-width:950px;margin:0 auto;border:1px solid #dee2e7;border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.04)}
  .sinput,.sloc{border:0;padding:14px 16px;font-size:16px}
  .sinput{flex:1;border-right:1px solid #edf0f3}
  .sloc{flex:.7}
  .sbtn{border:0;background:var(--brand);color:#fff;padding:0 20px;font-weight:800;cursor:pointer}

  /* Filters (desktop chips) */
  .filters{background:#fff;border-bottom:1px solid var(--border)}
  .f-in{display:flex;gap:10px;flex-wrap:wrap;padding:10px 0}
  .chip{display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #d9d9d9;border-radius:999px;padding:8px 12px;font-size:14px;cursor:pointer}
  .dd{position:relative}
  .menu{position:absolute;top:100%;left:0;background:#fff;border:1px solid #d9d9d9;border-radius:12px;box-shadow:var(--shadow);min-width:220px;max-height:320px;overflow:auto;z-index:50}
  .menu a{display:block;padding:12px 14px;text-decoration:none;color:var(--ink);border-bottom:1px solid #f3f3f3}
  .menu a:hover{background:#f7f9ff}
  .menu a:last-child{border-bottom:0}

  /* Mobile filters (native selects) */
  .mf{display:none;background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
  .mf-in{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:12px 0}
  .mf-in select{width:100%;padding:12px 10px;border:1px solid #d9d9d9;border-radius:10px;background:#fff}

  /* Main 2-col desktop */
  .main{display:grid;grid-template-columns:minmax(0,1fr) 460px;gap:22px;padding:16px 0}
  .meta{color:var(--muted);font-size:14px;margin:6px 0 12px}

  /* Job list card */
  .card{display:grid;grid-template-columns:56px 1fr;gap:12px;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:14px;margin-bottom:12px}
  .card.selected{border-color:#b9c7f2; box-shadow:0 0 0 3px #e8eeff}
  .logoimg{width:56px;height:56px;border:1px solid #eee;border-radius:12px;object-fit:contain;background:#fff}
  .ttl a{font-weight:800;font-size:18px;color:var(--ink);text-decoration:none}
  .ttl a:hover{color:var(--brand);text-decoration:underline}
  .muted{color:var(--muted);font-size:14px}
  .badges{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}
  .badge{display:inline-block;background:#f1f3f7;border:1px solid #e6e9ef;color:#374151;padding:4px 10px;border-radius:999px;font-size:12px}
  .desc-bullets{margin-top:6px;padding-left:18px;color:var(--muted);font-size:14px;line-height:1.6;list-style:disc;text-align:left}
  .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;font-size:14px;cursor:pointer;text-decoration:none}
  .primary{background:var(--brand);border:0;color:#fff;font-weight:800}
  .ghost{background:#fff;border:1px solid #d9d9d9;color:#111}

  /* Right sticky detail (desktop) */
  .detail{position:sticky;top:84px;height:fit-content;background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden}
  .detail-h{padding:18px;border-bottom:1px solid var(--border)}
  .detail-title{font-weight:900;font-size:20px;margin-bottom:4px}
  .detail-meta{color:var(--muted);font-size:14px;margin:0 0 12px}
  .detail-cta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .iconbtn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid #d9d9d9;border-radius:10px;background:#fff}
  .detail-body{padding:18px;white-space:pre-wrap;line-height:1.7}
  .section{padding:16px 18px;border-top:1px solid var(--border)}
  .chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
  .chipSm{font-size:12px;padding:6px 10px;border-radius:999px;background:#eef2ff;border:1px solid #d8e0ff;color:#1f3acb}

  /* Pagination */
  .pagination{display:flex;justify-content:center;margin:18px 0}
  .plist{display:flex;gap:6px;list-style:none;padding:0;margin:0}
  .plist a{display:inline-block;padding:8px 12px;border:1px solid #d9d9d9;border-radius:10px;text-decoration:none;color:#111;font-size:14px}
  .plist a:hover{border-color:var(--brand);color:var(--brand)}
  .plist a.active{background:var(--brand);color:#fff;border-color:var(--brand)}

  /* MOBILE full-screen detail */
  .m-detail{display:none; position:fixed; inset:0; background:#fff; z-index:80; overflow:auto}
  .m-topbar{position:sticky; top:0; background:#fff; border-bottom:1px solid var(--border); padding:12px 16px; display:flex; align-items:center; gap:10px}
  .m-back{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid #d9d9d9;border-radius:10px;background:#fff;text-decoration:none;color:#111}
  .m-hdr h1{font-size:18px;margin:6px 0 2px}
  .m-hdr .muted{font-size:13.5px}
  .m-sec{padding:14px 16px;border-top:1px solid var(--border); background:#fff}
  .m-apply{position:sticky; bottom:0; background:#fff; border-top:1px solid var(--border); padding:12px 16px}
  .m-apply .primary{width:100%; justify-content:center}
  .row{display:flex;align-items:center;gap:8px}

  /* Responsive switches */
  @media (max-width:1024px){ .main{grid-template-columns:1fr} .detail{position:static;top:auto;order:-1} }
  @media (max-width:768px){
    .nav,.auth{display:none}
    .mbtn{display:block;margin-left:auto}
    .mnav.open{display:block}
    .sform{flex-direction:column;border-radius:14px}
    .sinput{border-right:0;border-bottom:1px solid #edf0f3}
    .filters{display:none}  /* hide desktop chips */
    .mf{display:block}      /* show mobile selects */
    .main{padding:12px 0}
    .card{grid-template-columns:48px 1fr;padding:12px}
    .logoimg{width:48px;height:48px}
    /* When a job is open, show full-screen view and hide list */
    body[data-open="1"] .m-detail{display:block}
    body[data-open="1"] .mf,
    body[data-open="1"] .srch,
    body[data-open="1"] .main,
    body[data-open="1"] .pagination{display:none}
  }
</style>
</head>
<body
  data-open="<?= $hasOpen ? '1':'0' ?>"
  x-data="jobUI(
    <?= htmlspecialchars(json_encode($jobs), ENT_QUOTES) ?>,
    <?= $isLoggedIn ? 'true' : 'false' ?>,
    <?= (int)$openId ?>,
    '<?= h(SHARE_CANONICAL) ?>'
  )"
  x-init="init()"
>

<!-- Header -->
<header class="hdr">
  <div class="container hdr-in">
    <a class="logo" href="index.php">MSJOBS</a>
    <nav class="nav">
      <a href="index.php">Home</a>
      <a href="all-jobs.php" class="active">Jobs</a>
      <a href="contact.php">Contact</a>
    </nav>
    <div class="sp"></div>
    <div class="auth">
      <?php if ($isLoggedIn): ?>
        <a href="dashboard.php">Dashboard</a>
      <?php else: ?>
        <a href="login.php?next=<?= urlencode(currUrl()) ?>">Login</a>
      <?php endif; ?>
    </div>
    <button class="mbtn" type="button" onclick="document.querySelector('.mnav').classList.toggle('open')">☰</button>
  </div>
  <div class="container mnav">
    <a href="index.php">Home</a>
    <a href="all-jobs.php" class="active">Jobs</a>
    <a href="contact.php">Contact</a>
    <?php if ($isLoggedIn): ?>
      <a href="dashboard.php">Dashboard</a>
    <?php else: ?>
      <a href="login.php?next=<?= urlencode(currUrl()) ?>">Login</a>
    <?php endif; ?>
  </div>
</header>

<!-- Search -->
<section class="srch">
  <div class="container sr-in">
    <form class="sform" method="get" action="all-jobs.php">
      <input class="sinput" name="q" value="<?= h($q) ?>" placeholder="Job title, keywords, or company">
      <input class="sloc" name="loc" value="<?= h($loc) ?>" placeholder="City or location">
      <button class="sbtn" type="submit">Find jobs</button>
      <?php foreach(['category','type','exp','floc','sort','all'] as $keep) if(isset($_GET[$keep])): ?>
        <input type="hidden" name="<?= h($keep) ?>" value="<?= h((string)$_GET[$keep]) ?>">
      <?php endif; ?>
    </form>
  </div>
</section>

<!-- Desktop filters -->
<section class="filters">
  <div class="container">
    <div class="f-in">
      <div class="dd" x-data="{open:false}" @click.outside="open=false">
        <button class="chip" @click="open=!open">Category ▾</button>
        <div class="menu" x-cloak x-show="open">
          <a href="?<?= h(buildQS(['page','category'])) ?>">All</a>
          <?php foreach($categories as $opt): ?>
            <a href="?<?= h(http_build_query(array_merge($_GET,['category'=>$opt,'page'=>1]))) ?>"><?= h($opt) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="dd" x-data="{open:false}" @click.outside="open=false">
        <button class="chip" @click="open=!open">Type ▾</button>
        <div class="menu" x-cloak x-show="open">
          <a href="?<?= h(buildQS(['page','type'])) ?>">All</a>
          <?php foreach($types as $opt): ?>
            <a href="?<?= h(http_build_query(array_merge($_GET,['type'=>$opt,'page'=>1]))) ?>"><?= h($opt) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="dd" x-data="{open:false}" @click.outside="open=false">
        <button class="chip" @click="open=!open">Experience ▾</button>
        <div class="menu" x-cloak x-show="open">
          <a href="?<?= h(buildQS(['page','exp'])) ?>">All</a>
          <?php foreach($exps as $opt): ?>
            <a href="?<?= h(http_build_query(array_merge($_GET,['exp'=>$opt,'page'=>1]))) ?>"><?= h($opt) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="dd" x-data="{open:false}" @click.outside="open=false">
        <button class="chip" @click="open=!open">Location ▾</button>
        <div class="menu" x-cloak x-show="open">
          <a href="?<?= h(buildQS(['page','floc'])) ?>">All</a>
          <?php foreach($locations as $opt): ?>
            <a href="?<?= h(http_build_query(array_merge($_GET,['floc'=>$opt,'page'=>1]))) ?>"><?= h($opt) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="dd" x-data="{open:false}" @click.outside="open=false">
        <button class="chip" @click="open=!open">Sort ▾</button>
        <div class="menu" x-cloak x-show="open">
          <a href="?<?= h(http_build_query(array_merge($_GET,['sort'=>'relevance','page'=>1]))) ?>">relevance</a>
          <a href="?<?= h(http_build_query(array_merge($_GET,['sort'=>'date','page'=>1]))) ?>">date</a>
          <a href="?<?= h(http_build_query(array_merge($_GET,['sort'=>'salary_desc','page'=>1]))) ?>">Salary: High → Low</a>
          <a href="?<?= h(http_build_query(array_merge($_GET,['sort'=>'salary_asc','page'=>1]))) ?>">Salary: Low → High</a>
        </div>
      </div>
      <?php if(!$solo): ?>
        <?php if($showAll): ?>
          <span class="chip" style="background:#eef3ff;border-color:#cfdaf7;color:#1d4595">Showing All (<?= (int)$total ?>)</span>
          <a class="chip" href="?<?= h(http_build_query(array_merge($_GET,['all'=>null,'page'=>1]))) ?>">Back to pages</a>
        <?php else: ?>
          <a class="chip" href="?<?= h(http_build_query(array_merge($_GET,['all'=>1,'page'=>1]))) ?>">Show all</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Mobile filters -->
<section class="mf">
  <div class="container mf-in">
    <form id="mfilter" method="get" action="all-jobs.php" style="grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <input type="hidden" name="q" value="<?= h($q) ?>">
      <input type="hidden" name="loc" value="<?= h($loc) ?>">
      <select name="category" onchange="this.form.submit()">
        <option value="">Category: All</option>
        <?php foreach($categories as $opt): ?>
          <option value="<?= h($opt) ?>" <?= $category===$opt?'selected':'' ?>><?= h($opt) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type" onchange="this.form.submit()">
        <option value="">Type: All</option>
        <?php foreach($types as $opt): ?>
          <option value="<?= h($opt) ?>" <?= $type===$opt?'selected':'' ?>><?= h($opt) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="exp" onchange="this.form.submit()">
        <option value="">Experience: All</option>
        <?php foreach($exps as $opt): ?>
          <option value="<?= h($opt) ?>" <?= $exp===$opt?'selected':'' ?>><?= h($opt) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="floc" onchange="this.form.submit()">
        <option value="">Location: All</option>
        <?php foreach($locations as $opt): ?>
          <option value="<?= h($opt) ?>" <?= $floc===$opt?'selected':'' ?>><?= h($opt) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sort" onchange="this.form.submit()">
        <option value="relevance" <?= $sort==='relevance'?'selected':'' ?>>Relevance</option>
        <option value="date" <?= $sort==='date'?'selected':'' ?>>Date</option>
        <option value="salary_desc" <?= $sort==='salary_desc'?'selected':'' ?>>Salary: High → Low</option>
        <option value="salary_asc" <?= $sort==='salary_asc'?'selected':'' ?>>Salary: Low → High</option>
      </select>
      <input type="hidden" name="page" value="1">
      <?php if($showAll): ?><input type="hidden" name="all" value="1"><?php endif; ?>
    </form>
  </div>
</section>

<!-- Main (desktop: list + detail) -->
<main class="container main">
  <section>
    <div class="meta">
      <?php if($solo): ?>
        Viewing 1 of <?= (int)$total ?> result<?= $total===1?'':'s' ?>.
      <?php else: ?>
        <?= (int)min($per_page, max(0,$total-$offset)) ?> of <?= (int)$total ?> jobs
        <?php if(!$showAll): ?> — Page <?= (int)$page ?> / <?= (int)$pages ?><?php endif; ?>.
      <?php endif; ?>
    </div>

    <?php if(empty($jobs)): ?>
      <p class="muted">No jobs found. Try widening your filters.</p>
    <?php else: foreach($jobs as $j): ?>
      <?php $detail='all-jobs.php?'.http_build_query(array_merge($_GET,['open'=>$j['id'],'solo'=>1,'page'=>null])); ?>
      <article class="card <?= ($openId===$j['id'])?'selected':'' ?>" id="job-<?= (int)$j['id'] ?>">
        <img class="logoimg" src="<?= h($j['logo_url']) ?>" alt="<?= h($j['company_name'] ?: 'Company') ?> logo" loading="lazy">
        <div>
          <div class="ttl"><a href="<?= h($detail) ?>"><?= h($j['title']) ?></a></div>
          <div class="muted"><?= h($j['company_name'] ?: 'Confidential') ?> · <?= h($j['location'] ?: 'Location not specified') ?></div>
          <div class="badges">
            <?php if($j['category']): ?><span class="badge"><?= h($j['category']) ?></span><?php endif; ?>
            <?php if($j['type']): ?><span class="badge"><?= h($j['type']) ?></span><?php endif; ?>
            <?php if($j['experience_level']): ?><span class="badge"><?= h($j['experience_level']) ?></span><?php endif; ?>
            <span class="badge"><?= h($j['money_range']) ?></span>
          </div>
          <?= $j['bullets'] ?>
          <div class="actions">
            <a class="btn primary" href="javascript:void(0)" onclick="(function(){const n=encodeURIComponent(location.href);location.href='login.php?next='+n;})()">Apply now</a>
            <button class="btn ghost" type="button" onclick="window.__shareCanonical(<?= (int)$j['id'] ?>)">Share</button>
            <a class="btn ghost" href="<?= h($detail) ?>">View details</a>
          </div>
        </div>
      </article>
    <?php endforeach; endif; ?>

    <?php if(!$solo && !$showAll && $pages>1): ?>
      <nav class="pagination">
        <ul class="plist">
          <?php $base=$_GET; unset($base['page']); $mk=function($p)use($base){return 'all-jobs.php?'.http_build_query(array_merge($base,['page'=>$p]));}; ?>
          <?php if($page>1): ?><li><a href="<?= h($mk($page-1)) ?>">Previous</a></li><?php endif; ?>
          <?php for($p=1;$p<=$pages;$p++): ?>
            <li><a class="<?= $p===$page?'active':'' ?>" href="<?= h($mk($p)) ?>"><?= (int)$p ?></a></li>
          <?php endfor; ?>
          <?php if($page<$pages): ?><li><a href="<?= h($mk($page+1)) ?>">Next</a></li><?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </section>

  <!-- Right sticky detail (desktop, when open) -->
  <?php if($solo && !empty($jobs)): $d=$jobs[0]; ?>
    <aside class="detail">
      <div class="detail-h">
        <div class="detail-title"><?= h($d['title']) ?></div>
        <div class="detail-meta"><?= h($d['company_name'] ?: 'Confidential') ?> · <?= h($d['location'] ?: 'Location not specified') ?> · <?= h($d['money_range']) ?></div>
        <a class="btn primary" style="width:100%;justify-content:center" href="javascript:void(0)" onclick="(function(){const n=encodeURIComponent(location.href);location.href='login.php?next='+n;})()">Apply on company site</a>
        <div class="detail-cta">
          <button class="iconbtn" type="button" onclick="window.__shareCanonical(<?= (int)$d['id'] ?>)" title="Copy link">🔗</button>
          <a class="iconbtn" href="<?= h('all-jobs.php?'.buildQS(['open','solo']).'&open='.(int)$d['id'].'&solo=1') ?>" title="Open detail">🗂️</a>
        </div>
      </div>
      <div class="section">
        <div class="muted">Profile insights</div>
        <div class="chips">
          <?php
            $insights = array_slice(array_keys(extract_structured_points($d['description'] ?? '')), 0, 5);
            if(!$insights) $insights = ['Technical Proficiency','Stakeholder management','Product management'];
            foreach($insights as $c): ?>
              <span class="chipSm"><?= h($c) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="section">
        <div class="muted" style="margin-bottom:6px">Job details</div>
        <div><?= nl2br(h($d['description'] ?? '')) ?></div>
      </div>
    </aside>
  <?php endif; ?>
</main>

<!-- MOBILE full-screen job view (exact behavior like screenshot) -->
<?php if($solo && !empty($jobs)): $m=$jobs[0]; ?>
<section class="m-detail">
  <div class="m-topbar">
    <a class="m-back" href="<?= h($backUrl) ?>" aria-label="Back">←</a>
    <div class="m-hdr">
      <h1><?= h($m['title']) ?></h1>
      <div class="muted"><?= h($m['company_name'] ?: 'Confidential') ?></div>
    </div>
  </div>

  <div class="m-sec">
    <div class="row" style="gap:10px;flex-wrap:wrap">
      <div class="row"><span>💼</span><span class="muted"><?= h($m['money_range']) ?></span></div>
      <div class="row"><span>📍</span><span class="muted"><?= h($m['location'] ?: 'Location not specified') ?></span></div>
    </div>
  </div>

  <div class="m-sec">
    <div class="muted" style="margin-bottom:8px">Profile insights</div>
    <div class="chips">
      <?php
        $ins = array_slice(array_keys(extract_structured_points($m['description'] ?? '')), 0, 5);
        if(!$ins) $ins = ['Machine learning/AI-based analysis','AI','Technical Proficiency'];
        foreach($ins as $c): ?>
          <span class="chipSm"><?= h($c) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="m-sec">
    <div class="muted" style="margin-bottom:8px">Job details</div>
    <div><?= nl2br(h($m['description'] ?? '')) ?></div>
  </div>

  <div class="m-apply">
    <a class="btn primary" href="javascript:void(0)" onclick="(function(){const n=encodeURIComponent(location.href);location.href='login.php?next='+n;})()">Apply now</a>
  </div>
</section>
<?php endif; ?>

<script>
function jobUI(jobs,isLoggedIn,openId,canonicalBase){
  return {
    jobs,isLoggedIn,openId,canonicalBase,
    init(){ window.__shareCanonical=(id)=>this.share(id); },
    link(id){ const u=new URL(this.canonicalBase); u.searchParams.set('open',String(id)); u.searchParams.set('solo','1'); return u.toString(); },
    async share(id){
      const link=this.link(id);
      try{ if(navigator.share){ await navigator.share({title:'MSJOBS — Job',url:link}); return; } }catch(e){}
      try{ await navigator.clipboard.writeText(link); alert('Share link copied:\\n'+link); }
      catch(e){ prompt('Copy this link:', link); }
    }
  }
}
</script>

</body>
</html>
