<?php
require_once __DIR__ . '/config.php';
$DB_HOST = $servername;
$DB_USER = $username;
$DB_PASS = $password;
$DB_NAME = $dbname;
$database = $dbname;

/************************************************************
 * MSJOBS — Jobseeker Dashboard + Profile + All Jobs + Favorites
 * Filename: employee_dashboard.php
 * Fix: Favorites never leak HTML (strict JSON), work even if not logged in (uid=0),
 *      removed Alerts & Peers’ Favorites, added full job view modal.
 ************************************************************/
declare(strict_types=1);

/* ===== 0) STRICT HEADERS ===== */
header_remove('X-Powered-By');
ini_set('display_errors','0'); // keep OFF in prod; HTML breaks JSON
ini_set('log_errors','1');
error_reporting(E_ALL);

/* ===== 1) SESSION ===== */
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime'=>0,'path'=>'/','domain'=>'',
  'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax',
]);
session_cache_limiter('nocache');
session_start();

/* ===== 2) CONFIG ===== */
$DB_HOST='127.0.0.1:3306';
// $DB_USER='u903588615_root'; (Refactored to config.php)
// $DB_PASS='Msjobs#1'; (Refactored to config.php)
// $DB_NAME='u903588615_exaple'; (Refactored to config.php)

/* ===== 3) DB CONNECT ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try{
  $db=new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
  $db->set_charset('utf8mb4');
}catch(mysqli_sql_exception $e){
  error_log("DB connect failed: ".$e->getMessage());
  http_response_code(500); die("Database error.");
}

/* ===== 4) AUTH HELPERS ===== */
function is_logged_in():bool{ return isset($_SESSION['user_id']); }
function is_jobseeker():bool{
  if(!isset($_SESSION['user_id'])) return false;
  if(!isset($_SESSION['user_type'])) return true;
  return in_array($_SESSION['user_type'],['jobseeker','candidate','employee','user'],true);
}
function require_jobseeker():void{
  if(!is_jobseeker()){ header('Location: login.php'); exit; }
}

/* JSON-only guard (used for share actions only) */
function require_jobseeker_json():void{
  if(!is_logged_in()){
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['ok'=>false,'error'=>'AUTH_REQUIRED']); exit;
  }
}

/* ===== 5) UTILS ===== */
function h($s):string{ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function csrf_token():string{ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t):bool{ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],(string)$t); }
function sanitize_url(string $url):string{
  $url=trim($url); if($url==='') return '';
  if(!preg_match('/^https?:\/\//i',$url)) $url='https://'.$url;
  return filter_var($url,FILTER_VALIDATE_URL)?$url:'';
}
function is_abs_url(string $s):bool{ return (bool)preg_match('~^https?://~i',$s); }
function normalize_logo_path(?string $raw):string{
  $logo=trim((string)$raw); if($logo==='') return '';
  if(is_abs_url($logo)) return $logo;
  if(preg_match('~^/?uploads/~i',$logo)) return ltrim($logo,'/');
  return 'uploads/logos/'.ltrim($logo,'/');
}
function has_table(mysqli $db,string $table):bool{
  $like=$db->real_escape_string($table);
  $res=$db->query("SHOW TABLES LIKE '$like'");
  return $res && $res->num_rows>0;
}
function table_cols(mysqli $db,string $table):array{
  if(!has_table($db,$table)) return [];
  $cols=[]; $res=$db->query("SHOW COLUMNS FROM `$table`");
  while($r=$res->fetch_assoc()){ $cols[strtolower($r['Field'])]=true; }
  return $cols;
}
function ensure_column(mysqli $db,string $table,string $column_def):void{
  [$col]=explode(' ',$column_def,2); $col=trim($col,'` ');
  $stmt=$db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $stmt->bind_param("ss",$table,$col); $stmt->execute();
  if($stmt->get_result()->num_rows===0){ $db->query("ALTER TABLE `$table` ADD COLUMN $column_def"); }
}
function try_add_unique_index(mysqli $db,string $table,string $index,string $cols):void{
  $idxEsc=$db->real_escape_string($index); $tblEsc=$db->real_escape_string($table);
  $res=$db->query("SHOW INDEX FROM `$tblEsc` WHERE Key_name='$idxEsc'");
  if($res && $res->num_rows===0){ $db->query("CREATE UNIQUE INDEX `$idxEsc` ON `$tblEsc` ($cols)"); }
}
function save_profile_image(array $file):?string{
  if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
  if($file['error']!==UPLOAD_ERR_OK) return null;
  if(($file['size']??0)>5*1024*1024) return null;
  $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$file['tmp_name']); finfo_close($finfo);
  $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; if(!isset($allowed[$mime])) return null;
  $dir=__DIR__.'/uploads/profiles'; if(!is_dir($dir)) @mkdir($dir,0775,true);
  $name='p_'.time().'_'.bin2hex(random_bytes(4)).'.'.$allowed[$mime]; $dest="$dir/$name";
  if(!move_uploaded_file($file['tmp_name'],$dest)) return null;
  return 'uploads/profiles/'.$name;
}

/* ===== 6) AUTO-MIGRATIONS ===== */
try{
  if(!has_table($db,'jobseekers')){
    $db->query("CREATE TABLE IF NOT EXISTS jobseekers (
      user_id BIGINT UNSIGNED PRIMARY KEY,
      full_name VARCHAR(190) NULL,
      professional_title VARCHAR(160) NULL,
      summary TEXT NULL,
      location VARCHAR(160) NULL,
      website VARCHAR(190) NULL,
      linkedin VARCHAR(190) NULL,
      twitter VARCHAR(190) NULL,
      github VARCHAR(190) NULL,
      skills_csv VARCHAR(1000) NULL,
      profile_picture VARCHAR(255) NULL,
      share_token VARCHAR(64) NULL,
      share_enabled TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }else{
    ensure_column($db,'jobseekers',"full_name VARCHAR(190) NULL");
    ensure_column($db,'jobseekers',"professional_title VARCHAR(160) NULL");
    ensure_column($db,'jobseekers',"summary TEXT NULL");
    ensure_column($db,'jobseekers',"location VARCHAR(160) NULL");
    ensure_column($db,'jobseekers',"website VARCHAR(190) NULL");
    ensure_column($db,'jobseekers',"linkedin VARCHAR(190) NULL");
    ensure_column($db,'jobseekers',"twitter VARCHAR(190) NULL");
    ensure_column($db,'jobseekers',"github VARCHAR(190) NULL");
    ensure_column($db,'jobseekers',"skills_csv VARCHAR(1000) NULL");
    ensure_column($db,'jobseekers',"profile_picture VARCHAR(255) NULL");
    ensure_column($db,'jobseekers',"share_token VARCHAR(64) NULL");
    ensure_column($db,'jobseekers',"share_enabled TINYINT(1) NOT NULL DEFAULT 0");
    try_add_unique_index($db,'jobseekers','idx_share_token','`share_token`');
  }

  /* Favorites */
  $db->query("CREATE TABLE IF NOT EXISTS jobseeker_favorites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fav (user_id, job_id),
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  /* Experience/Education */
  $db->query("CREATE TABLE IF NOT EXISTS jobseeker_experience (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    company VARCHAR(190) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $db->query("CREATE TABLE IF NOT EXISTS jobseeker_education (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    school VARCHAR(190) NOT NULL,
    degree VARCHAR(190) NULL,
    field VARCHAR(190) NULL,
    start_year INT NULL,
    end_year INT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}catch(mysqli_sql_exception $e){ error_log("Migration issue: ".$e->getMessage()); }

/* ===== 7) SCHEMA DISCOVERY ===== */
function pick_col($cols,array $candidates,$fallback=null){
  foreach($candidates as $c){ if(isset($cols[strtolower($c)])) return $c; }
  return $fallback;
}
$jobsT = has_table($db,'jobs') ? 'jobs' : null;
$empsT = has_table($db,'employers') ? 'employers' : (has_table($db,'companies') ? 'companies' : null);
$appsT = has_table($db,'applications') ? 'applications' : null;
$favsT = has_table($db,'jobseeker_favorites') ? 'jobseeker_favorites' : null;

$J=$jobsT?table_cols($db,$jobsT):[];
$E=$empsT?table_cols($db,$empsT):[];
$A=$appsT?table_cols($db,$appsT):[];

/* Job cols */
$JOB_ID       = pick_col($J,['id','job_id'],'id');
$JOB_TITLE    = pick_col($J,['title','job_title','name'],'title');
$JOB_LOCATION = pick_col($J,['location','city','job_location','place'],'location');
$JOB_COMPANYID= pick_col($J,['company_id','employer_id'],'company_id');
$JOB_STATUS   = pick_col($J,['status','is_active','active'],null);
$JOB_POSTED   = pick_col($J,['posted_at','created_at','date_posted','created_on'],null);
$JOB_DESC     = pick_col($J,['description','details','job_description'],null);
/* Fallbacks */
$JOB_COMPANYNAME_FB = pick_col($J,['company_name','employer_name','name','company'],null);
$JOB_LOGO_FB        = pick_col($J,['logos','company_logo','logo_url','image','photo','brand_logo'],null);

/* Employer cols */
$EMP_ID   = pick_col($E,['id','employer_id','company_id'],'id');
$EMP_NAME = pick_col($E,['company_name','name','employer_name'],'name');
$EMP_LOGO = pick_col($E,['logo','company_logo','logo_url','image','photo'],null);

/* Application cols */
$APP_ID    = pick_col($A,['id'],'id');
$APP_JOBID = pick_col($A,['job_id'],'job_id');
$APP_USERID= pick_col($A,['user_id','jobseeker_id'],'user_id');
$APP_STATUS= pick_col($A,['status'],null);
$APP_DATE  = pick_col($A,['applied_at','created_at','created_on'],null);

/* ===== 8) ROUTING ===== */
$view = $_GET['view'] ?? '';
$page = $_GET['page'] ?? 'home';

/* ===== 9) PUBLIC PROFILE ===== */
function render_public_profile(array $profile, mysqli_stmt $exp_rs, mysqli_stmt $edu_rs):void{
  $photo=!empty($profile['profile_picture'])?$profile['profile_picture']:'img/user-placeholder.png';
  $skills=!empty($profile['skills_csv'])?explode(',',$profile['skills_csv']):[];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($profile['full_name']??'Jobseeker Profile') ?> — MSJOBS</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>body{font-family:Poppins,system-ui;background:#f3f6fb}.section{background:#fff;border-radius:.5rem;box-shadow:0 1px 3px rgba(0,0,0,.1)}</style>
</head><body>
<div class="min-h-screen py-8"><div class="max-w-4xl mx-auto px-4">
<header class="mb-8 text-center"><img src="img/MS copy.png" class="h-10 mx-auto mb-2" alt="MSJOBS"><h1 class="text-2xl font-bold text-blue-700">Public Profile</h1></header>
<div class="section p-6 mb-6">
  <div class="flex flex-col md:flex-row items-center gap-6">
    <img src="<?= h($photo) ?>" class="w-32 h-32 rounded-full object-cover border-4 border-blue-500" alt="Profile">
    <div class="text-center md:text-left">
      <h2 class="text-2xl font-bold text-gray-900"><?= h($profile['full_name']??'') ?></h2>
      <p class="text-lg text-blue-700"><?= h($profile['professional_title']??'') ?></p>
      <div class="mt-2 text-gray-600">
        <span class="inline-block mr-4"><i class="fas fa-map-marker-alt mr-1"></i> <?= h($profile['location']??'') ?></span>
        <span class="inline-block"><i class="fas fa-envelope mr-1"></i> <?= h($profile['email']??'') ?></span>
      </div>
    </div>
  </div>
  <?php if(!empty($profile['summary'])): ?>
  <div class="mt-6"><h3 class="text-lg font-semibold text-gray-800 mb-2">Professional Summary</h3>
    <p class="text-gray-700"><?= nl2br(h($profile['summary'])) ?></p></div><?php endif; ?>
  <?php if(!empty($skills)): ?>
  <div class="mt-6"><h3 class="text-lg font-semibold text-gray-800 mb-2">Skills</h3>
    <div class="flex flex-wrap gap-2"><?php foreach($skills as $skill): ?>
      <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm"><?= h(trim($skill)) ?></span>
    <?php endforeach; ?></div></div><?php endif; ?>
</div>
<?php $exp_rs->execute(); $experience=$exp_rs->get_result(); if($experience->num_rows>0): ?>
<div class="section p-6 mb-6"><h3 class="text-xl font-semibold text-gray-800 mb-4">Work Experience</h3>
<?php while($exp=$experience->fetch_assoc()): ?>
  <div class="mb-4 pb-4 border-b last:border-0 last:pb-0 last:mb-0">
    <div class="flex justify-between items-start">
      <div><h4 class="font-bold text-lg text-gray-900"><?= h($exp['title']) ?></h4><p class="text-blue-700"><?= h($exp['company']) ?></p></div>
      <div class="text-right text-gray-600 text-sm">
        <?php if(!empty($exp['start_date'])): ?><?= date('M Y',strtotime($exp['start_date'])) ?>
        <?php if(!empty($exp['end_date'])): ?> - <?= date('M Y',strtotime($exp['end_date'])) ?>
        <?php elseif($exp['is_current']): ?> - Present<?php endif; endif; ?>
      </div>
    </div>
    <?php if(!empty($exp['description'])): ?><p class="mt-2 text-gray-700"><?= nl2br(h($exp['description'])) ?></p><?php endif; ?>
  </div>
<?php endwhile; ?></div><?php endif; ?>
<?php $edu_rs->execute(); $education=$edu_rs->get_result(); if($education->num_rows>0): ?>
<div class="section p-6 mb-6"><h3 class="text-xl font-semibold text-gray-800 mb-4">Education</h3>
<?php while($edu=$education->fetch_assoc()): ?>
  <div class="mb-4 pb-4 border-b last:border-0 last:pb-0 last:mb-0">
    <div class="flex justify-between items-start">
      <div><h4 class="font-bold text-lg text-gray-900"><?= h($edu['school']) ?></h4>
        <p class="text-blue-700"><?php if(!empty($edu['degree'])): ?><?= h($edu['degree']) ?><?php if(!empty($edu['field'])): ?> in <?= h($edu['field']) ?><?php endif; endif; ?></p></div>
      <div class="text-right text-gray-600 text-sm">
        <?php if(!empty($edu['start_year'])): ?><?= (int)$edu['start_year'] ?><?php if(!empty($edu['end_year'])): ?> - <?= (int)$edu['end_year'] ?><?php endif; endif; ?>
      </div>
    </div>
    <?php if(!empty($edu['description'])): ?><p class="mt-2 text-gray-700"><?= nl2br(h($edu['description'])) ?></p><?php endif; ?>
  </div>
<?php endwhile; ?></div><?php endif; ?>
<footer class="text-center text-gray-600 text-sm mt-8"><p>© <?= date('Y') ?> MSJOBS. All rights reserved.</p></footer>
</div></div></body></html>
<?php }

if($view==='public'){
  $token=trim($_GET['token']??''); if($token===''){ http_response_code(404); echo "Profile not found."; exit; }
  $stmt=$db->prepare("SELECT u.id user_id, u.email, j.* FROM jobseekers j JOIN users u ON u.id=j.user_id WHERE j.share_enabled=1 AND j.share_token=?");
  $stmt->bind_param("s",$token); $stmt->execute();
  $profile=$stmt->get_result()->fetch_assoc(); if(!$profile){ http_response_code(404); echo "Profile unavailable or sharing disabled."; exit; }
  $uid=(int)$profile['user_id'];
  $exp=$db->prepare("SELECT * FROM jobseeker_experience WHERE user_id=? ORDER BY start_date DESC, id DESC"); $exp->bind_param("i",$uid);
  $edu=$db->prepare("SELECT * FROM jobseeker_education WHERE user_id=? ORDER BY start_year DESC, id DESC"); $edu->bind_param("i",$uid);
  render_public_profile($profile,$exp,$edu); exit;
}

/* ===== 10) AJAX: SHARE & FAVORITE (strict JSON) ===== */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('X-Content-Type-Options: nosniff');

  // NOTE: favorites allow uid=0 (guest) to avoid redirects / non-JSON responses
  $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

  try{
    if($_POST['action']==='enable'){
      require_jobseeker_json(); // must be logged in to share
      $q=$db->prepare("SELECT share_token FROM jobseekers WHERE user_id=?");
      $q->bind_param("i",$uid); $q->execute();
      $row=$q->get_result()->fetch_assoc()?:[];
      $token = !empty($row['share_token']) ? (string)$row['share_token'] : bin2hex(random_bytes(16));
      $up=$db->prepare("UPDATE jobseekers SET share_token=?, share_enabled=1 WHERE user_id=?");
      $up->bind_param("si",$token,$uid); $up->execute();
      $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
      $base=rtrim(dirname($_SERVER['PHP_SELF']),'/');
      $url=$scheme.'://'.$_SERVER['HTTP_HOST'].$base.'/'.basename(__FILE__).'?view=public&token='.$token;
      echo json_encode(['ok'=>true,'url'=>$url]); exit;
    }

    if($_POST['action']==='disable'){
      require_jobseeker_json();
      $up=$db->prepare("UPDATE jobseekers SET share_enabled=0 WHERE user_id=?");
      $up->bind_param("i",$uid); $up->execute();
      echo json_encode(['ok'=>true]); exit;
    }

    if($_POST['action']==='favorite'){
      if(!$favsT) throw new Exception('Favorites unavailable');
      $jid=(int)($_POST['job_id']??0); if($jid<=0) throw new Exception('Invalid job ID');

      // Validate job exists (prevents orphan favorites and SQL errors)
      if($jobsT){
        $chk=$db->prepare("SELECT 1 FROM `$jobsT` WHERE `$JOB_ID`=? LIMIT 1");
        $chk->bind_param("i",$jid); $chk->execute();
        if(!$chk->get_result()->fetch_row()) throw new Exception('Job not found');
      }

      // Toggle
      $exists=$db->prepare("SELECT id FROM `$favsT` WHERE user_id=? AND job_id=?");
      $exists->bind_param("ii",$uid,$jid); $exists->execute();
      $row=$exists->get_result()->fetch_assoc();

      if($row){
        $del=$db->prepare("DELETE FROM `$favsT` WHERE user_id=? AND job_id=?");
        $del->bind_param("ii",$uid,$jid); $del->execute();
        echo json_encode(['ok'=>true,'favorited'=>false]); exit;
      }else{
        $ins=$db->prepare("INSERT INTO `$favsT` (user_id,job_id) VALUES (?,?)");
        $ins->bind_param("ii",$uid,$jid);
        try{ $ins->execute(); }catch(mysqli_sql_exception $e){
          if((int)$db->errno!==1062) throw $e; // race safe
        }
        echo json_encode(['ok'=>true,'favorited'=>true]); exit;
      }
    }

    throw new Exception('Invalid action');
  }catch(Exception $e){
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
  }
}

/* ===== 11) PAGE AUTH (page load only) ===== */
require_jobseeker();
$UID=(int)($_SESSION['user_id']??0);

/* ===== 12) LOAD ME ===== */
function load_me(mysqli $db,int $uid):array{
  $check=$db->prepare("SELECT 1 FROM jobseekers WHERE user_id=?");
  $check->bind_param("i",$uid); $check->execute();
  if($check->get_result()->num_rows===0){
    $insert=$db->prepare("INSERT INTO jobseekers (user_id) VALUES (?)");
    $insert->bind_param("i",$uid); $insert->execute();
  }
  $st=$db->prepare("SELECT u.email, j.* FROM users u JOIN jobseekers j ON j.user_id=u.id WHERE u.id=?");
  $st->bind_param("i",$uid); $st->execute();
  $me=$st->get_result()->fetch_assoc()?:[];
  $me+=['full_name'=>'','professional_title'=>'','location'=>'','skills_csv'=>'','profile_picture'=>'','share_enabled'=>0,'share_token'=>''];
  return $me;
}
$me=load_me($db,$UID);

/* ===== 13) NON-AJAX POST (Profile save) ===== */
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_profile']) && csrf_ok($_POST['csrf']??'')){
  try{
    $full_name=trim($_POST['full_name']??''); if($full_name==='') throw new Exception('Full name required');
    $professional_title=trim($_POST['professional_title']??'');
    $summary=trim($_POST['summary']??''); $location=trim($_POST['location']??'');
    $website=sanitize_url($_POST['website']??''); $linkedin=sanitize_url($_POST['linkedin']??'');
    $twitter=sanitize_url($_POST['twitter']??''); $github=sanitize_url($_POST['github']??'');
    $skills_csv=trim($_POST['skills_csv']??'');

    $profile_picture_uploaded=save_profile_image($_FILES['profile_picture']??[]);
    $profile_picture=$profile_picture_uploaded ?? ($me['profile_picture']??'');

    $up=$db->prepare("UPDATE jobseekers SET full_name=?,professional_title=?,summary=?,location=?,website=?,linkedin=?,twitter=?,github=?,skills_csv=?,profile_picture=? WHERE user_id=?");
    $up->bind_param("ssssssssssi",$full_name,$professional_title,$summary,$location,$website,$linkedin,$twitter,$github,$skills_csv,$profile_picture,$UID);
    $up->execute();

    /* Replace experience */
    $del=$db->prepare("DELETE FROM jobseeker_experience WHERE user_id=?"); $del->bind_param("i",$UID); $del->execute();
    if(isset($_POST['exp_title']) && is_array($_POST['exp_title'])){
      $stmt=$db->prepare("INSERT INTO jobseeker_experience (user_id,title,company,start_date,end_date,is_current,description) VALUES (?,?,?,?,?,?,?)");
      foreach($_POST['exp_title'] as $k=>$title){
        $title=trim((string)$title); $company=trim((string)($_POST['exp_company'][$k]??'')); if($title===''||$company==='') continue;
        $start=($_POST['exp_start'][$k]??null)?:null; $end=($_POST['exp_end'][$k]??null)?:null; $current=isset($_POST['exp_current'][$k])?1:0; $desc=trim((string)($_POST['exp_desc'][$k]??''));
        $stmt->bind_param("issssis",$UID,$title,$company,$start,$end,$current,$desc); $stmt->execute();
      }
    }

    /* Replace education */
    $del2=$db->prepare("DELETE FROM jobseeker_education WHERE user_id=?"); $del2->bind_param("i",$UID); $del2->execute();
    if(isset($_POST['edu_school']) && is_array($_POST['edu_school'])){
      $stmt=$db->prepare("INSERT INTO jobseeker_education (user_id,school,degree,field,start_year,end_year,description) VALUES (?,?,?,?,?,?,?)");
      foreach($_POST['edu_school'] as $k=>$school){
        $school=trim((string)$school); if($school==='') continue;
        $degree=trim((string)($_POST['edu_degree'][$k]??'')); $field=trim((string)($_POST['edu_field'][$k]??''));
        $start=(int)($_POST['edu_start'][$k]??0); $start=$start>0?$start:null;
        $end=(int)($_POST['edu_end'][$k]??0); $end=$end>0?$end:null;
        $desc=trim((string)($_POST['edu_desc'][$k]??''));
        $stmt->bind_param("isssiis",$UID,$school,$degree,$field,$start,$end,$desc); $stmt->execute();
      }
    }

    header('Location: '.basename(__FILE__).'?page=profile&success=1'); exit;
  }catch(Exception $e){ $msg=$e->getMessage(); }
}

/* ===== 14) COMMON DATA ===== */
function profile_completion(array $me):int{
  $fields=['full_name','professional_title','skills_csv','location','profile_picture'];
  $have=0; foreach($fields as $f){ if(!empty($me[$f])) $have++; }
  $pct=(int)round(($have/max(1,count($fields)))*100); return max(10,min(100,$pct));
}
$me=load_me($db,$UID); $completion=profile_completion($me);

/* counts */
$cnt_applied=0; if($appsT){ $st=$db->prepare("SELECT COUNT(*) c FROM `$appsT` WHERE `$APP_USERID`=?"); $st->bind_param("i",$UID); $st->execute(); $cnt_applied=(int)($st->get_result()->fetch_assoc()['c']??0); }
$cnt_favs=0; if($favsT){ $st=$db->prepare("SELECT COUNT(*) c FROM `$favsT` WHERE user_id=?"); $st->bind_param("i",$UID); $st->execute(); $cnt_favs=(int)($st->get_result()->fetch_assoc()['c']??0); }

/* ===== 15) ALL JOBS (search + pagination) ===== */
$all_rs=false; $total_all=0; $per_page=12;
$page_no=max(1,(int)($_GET['p']??1)); $search_q=trim((string)($_GET['q']??'')); $search_loc=trim((string)($_GET['l']??''));
$all_jobs_params=[]; $all_jobs_types='';

if($jobsT){
  $activeFilter = ($JOB_STATUS ? "AND j.`$JOB_STATUS`='approved'" : "");
  $orderDate    = ($JOB_POSTED ? "j.`$JOB_POSTED` DESC" : "j.`$JOB_ID` DESC");
  $companyExpr  = $EMP_NAME ? "e.`$EMP_NAME`" : ($JOB_COMPANYNAME_FB ? "j.`$JOB_COMPANYNAME_FB`" : "j.`$JOB_TITLE`");

  $EMP_NAME_SEL = $EMP_NAME ? "e.`$EMP_NAME` AS company_name" : ($JOB_COMPANYNAME_FB ? "j.`$JOB_COMPANYNAME_FB` AS company_name" : "NULL AS company_name");
  $EMP_LOGO_SEL = $EMP_LOGO ? "e.`$EMP_LOGO` AS company_logo" : ($JOB_LOGO_FB ? "j.`$JOB_LOGO_FB` AS company_logo" : "NULL AS company_logo");

  $fav_join = $favsT ? "LEFT JOIN `$favsT` f ON f.job_id = j.`$JOB_ID` AND f.user_id = $UID" : "";
  $fav_sel  = $favsT ? ", IF(f.id IS NOT NULL, 1, 0) AS is_favorite" : ", 0 AS is_favorite";

  $where="WHERE 1=1 $activeFilter";
  if($search_q!==''){
    $like='%'.$search_q.'%';
    $where.=" AND (j.`$JOB_TITLE` LIKE ? OR $companyExpr LIKE ?)";
    $all_jobs_types.='ss'; $all_jobs_params[]=$like; $all_jobs_params[]=$like;
  }
  if($search_loc!==''){
    $likeL='%'.$search_loc.'%';
    $where.=" AND (j.`$JOB_LOCATION` LIKE ?)";
    $all_jobs_types.='s'; $all_jobs_params[]=$likeL;
  }

  $count_sql="SELECT COUNT(*) c FROM `$jobsT` j $fav_join ".($empsT ? "LEFT JOIN `$empsT` e ON e.`$EMP_ID`=j.`$JOB_COMPANYID` " : "")." $where";
  $stc=$db->prepare($count_sql); if($all_jobs_params){ $stc->bind_param($all_jobs_types,...$all_jobs_params); }
  $stc->execute(); $total_all=(int)($stc->get_result()->fetch_assoc()['c']??0);

  $offset=($page_no-1)*$per_page;
  $list_sql="SELECT j.*, $EMP_NAME_SEL, $EMP_LOGO_SEL $fav_sel
             FROM `$jobsT` j
             $fav_join
             ".($empsT ? "LEFT JOIN `$empsT` e ON e.`$EMP_ID`=j.`$JOB_COMPANYID`" : "")."
             $where
             ORDER BY $orderDate
             LIMIT $per_page OFFSET $offset";
  $stl=$db->prepare($list_sql); if($all_jobs_params){ $stl->bind_param($all_jobs_types,...$all_jobs_params); }
  $stl->execute(); $all_rs=$stl->get_result();

  /* Applied list (recent 6) */
  $applied_rs=false;
  if($appsT){
    $status_sel = $APP_STATUS ? ", ap.`$APP_STATUS` AS app_status" : "";
    $sql="SELECT j.*, $EMP_NAME_SEL, $EMP_LOGO_SEL $fav_sel, ap.`$APP_DATE` AS applied_when $status_sel
          FROM `$appsT` ap
          JOIN `$jobsT` j ON j.`$JOB_ID`=ap.`$APP_JOBID`
          $fav_join
          ".($empsT ? "LEFT JOIN `$empsT` e ON e.`$EMP_ID`=j.`$JOB_COMPANYID`" : "")."
          WHERE ap.`$APP_USERID`=?
          ORDER BY ".($APP_DATE ? "ap.`$APP_DATE` DESC" : "ap.`$APP_ID` DESC")."
          LIMIT 6";
    $st=$db->prepare($sql); $st->bind_param("i",$UID); $st->execute(); $applied_rs=$st->get_result();
  }

  /* Favorites grid */
  $favs_rs=false;
  if($favsT){
    $sql="SELECT j.*, $EMP_NAME_SEL, $EMP_LOGO_SEL $fav_sel
          FROM `$favsT` f
          JOIN `$jobsT` j ON j.`$JOB_ID`=f.job_id
          ".($empsT ? "LEFT JOIN `$empsT` e ON e.`$EMP_ID`=j.`$JOB_COMPANYID`" : "")."
          WHERE f.user_id=?
          ORDER BY f.created_at DESC
          LIMIT 12";
    $st=$db->prepare($sql); $st->bind_param("i",$UID); $st->execute(); $favs_rs=$st->get_result();
  }
}

/* ===== 16) RENDER ===== */
($page==='profile')
  ? render_profile_page($db,$me,$completion,$msg, $favsT!==null)
  : render_home_page(
      $me,$completion,$cnt_applied,$cnt_favs,
      $applied_rs,$all_rs,$favs_rs,$total_all,$per_page,$page_no,$search_q,$search_loc,
      $msg,(bool)$appsT,$jobsT,$JOB_ID,$JOB_TITLE,$JOB_LOCATION,$JOB_DESC,$APP_STATUS,$favsT!==null
    );

$db->close(); exit;

/* ======================= VIEWS ======================= */
function navbar():void{ ?>
<header class="bg-white shadow-sm sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
    <div class="flex items-center gap-3">
      <img src="img/MS copy.png" class="h-9" alt="MSJOBS">
      <h1 class="text-lg font-semibold text-blue-700">MSJOBS</h1>
    </div>
    <nav class="hidden md:flex gap-6 text-gray-600 font-medium">
      <a href="<?= h(basename(__FILE__)) ?>?page=home" class="hover:text-blue-600">Home</a>
      <a href="<?= h(basename(__FILE__)) ?>?page=profile" class="hover:text-blue-600">Profile</a>
      <a href="fetch_applied_jobs.php" class="hover:text-blue-600">Applied</a>
      <a href="login.php" class="text-red-500">Logout</a>
    </nav>
    <button class="md:hidden p-2" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')"><i class="fa fa-bars"></i></button>
  </div>
  <div id="mobileMenu" class="hidden md:hidden bg-white border-t px-4 py-3">
    <nav class="flex flex-col gap-3 text-gray-600">
      <a href="<?= h(basename(__FILE__)) ?>?page=home" class="hover:text-blue-600">Home</a>
      <a href="<?= h(basename(__FILE__)) ?>?page=profile" class="hover:text-blue-600">Profile</a>
      <a href="fetch_applied_jobs.php" class="hover:text-blue-600">Applied</a>
      <a href="login.php" class="text-red-500">Logout</a>
    </nav>
  </div>
</header>
<?php }

function job_card(array $j,bool $canApply,?string $jobsT,string $JOB_ID,string $JOB_TITLE,string $JOB_LOCATION,?string $JOB_DESC,?string $APP_STATUS,bool $applied,bool $favoritesEnabled):string{
  $cname=$j['company_name']??'Company';
  $title=$j[$JOB_TITLE]??($j['title']??$j['job_title']??'Job');
  $loc=$j[$JOB_LOCATION]??($j['location']??'');
  $jid=(int)($j[$JOB_ID]??$j['id']??0);
  $rawLogo=$j['company_logo']??$j['logos']??$j['logo']??$j['logo_url']??$j['image']??$j['company_logo_url']??'';
  $logo=normalize_logo_path($rawLogo);
  $is_favorite=(int)($j['is_favorite']??0);
  $starIconClass=$is_favorite?'fa-solid fa-star':'fa-regular fa-star';

  $fullDesc = $JOB_DESC ? (string)($j[$JOB_DESC]??'') : '';
  $desc_snip = $fullDesc ? mb_substr(strip_tags($fullDesc),0,180).'…' : '';

//   $fav_btn = $favoritesEnabled
//     ? '<button class="ml-2 px-3 py-1.5 bg-yellow-100 text-yellow-700 text-sm rounded favorite-job" data-jobid="'.$jid.'" data-fav="'.$is_favorite.'"><i class="'.$starIconClass.'"></i> Favorite</button>'
//     : '';

  $view_btn = $fullDesc!=='' ? '<button class="ml-2 px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded view-job" data-jid="'.$jid.'" data-title="'.h($title).'" data-company="'.h($cname).'" data-loc="'.h($loc).'" data-desc="'.h($fullDesc).'"><i class="fa fa-eye"></i> View</button>' : '';

  $html='<div class="border rounded-lg p-4 bg-white hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <div class="font-semibold text-blue-700 truncate">'.h($title).'</div>
        <div class="text-sm text-gray-800 font-medium truncate">'.h($cname).'</div>
        <div class="text-xs text-gray-500 mt-2"><i class="fa fa-map-marker-alt"></i> '.h($loc).'</div>'.
        ($desc_snip?'<div class="text-sm text-gray-700 mt-2">'.$desc_snip.'</div>':'').'
      </div>'.($logo?'<img src="'.h($logo).'" class="w-10 h-10 rounded object-cover flex-shrink-0" alt="logo">':'').'
    </div>';
  if(!$applied && $canApply && $jobsT && $jid>0){
    $html.='<div class="mt-3 flex items-center flex-wrap gap-2">
      <a href="apply.php?job_id='.$jid.'" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded inline-block">Apply</a>'.$view_btn.$fav_btn.'
    </div>';
  }elseif($applied){
    $when=h($j['applied_when']??''); $status=$APP_STATUS? h($j['app_status']??'Pending') : 'Applied';
    $status_color=match(strtolower($status)){ 'pending'=>'gray','reviewed'=>'blue','accepted'=>'green','rejected'=>'red', default=>'gray' };
    $html.='<div class="mt-3 flex items-center gap-3 flex-wrap">
      <div class="text-xs text-gray-500">Applied '.$when.'</div>
      <div class="text-xs text-'.$status_color.'-600">Status: '.$status.'</div>'.$view_btn.$fav_btn.'
    </div>';
  }else{
    $html.=($view_btn || $fav_btn)?'<div class="mt-3">'.$view_btn.' '.$fav_btn.'</div>':'';
  }
  $html.='</div>';
  return $html;
}

/* --------- HOME PAGE (no Alerts / no Peers) --------- */
function render_home_page(
  array $me,int $completion,int $cnt_applied,int $cnt_favs,
  $applied_rs,$all_rs,$favs_rs,int $total_all,int $per_page,int $page_no,
  string $search_q,string $search_loc,
  string $msg,bool $canApply,?string $jobsT,string $JOB_ID,string $JOB_TITLE,string $JOB_LOCATION,?string $JOB_DESC,?string $APP_STATUS,bool $favoritesEnabled
):void{ ?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Home — MSJOBS</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
  body{font-family:Poppins,system-ui;background:#f3f6fb}
  .tab-btn{padding:.75rem 0;margin-right:1.2rem;color:#6b7280;border-bottom:3px solid transparent;white-space:nowrap}
  .tab-btn.active{color:#0b74e5;border-color:#0b74e5;font-weight:600}
  .pager a,.pager span{display:inline-block;padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:.5rem;margin-right:.25rem;font-size:.875rem}
  .pager a{background:#fff;color:#111827}.pager span.cur{background:#0b74e5;border-color:#0b74e5;color:#fff}
  dialog::backdrop{background:rgba(0,0,0,.5)}
</style>
</head><body>
<?php navbar(); ?>
<?php if($msg): ?><div class="bg-green-100 text-green-700 p-3 text-center"><?= h($msg) ?></div><?php endif; ?>

<section class="bg-gradient-to-r from-blue-600 to-sky-400 text-white py-10">
  <div class="max-w-7xl mx-auto px-4">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
      <div class="lg:col-span-2">
        <h2 class="text-3xl font-semibold mb-6">Welcome Back!</h2>

        <!-- Search -->
        <form method="get" class="max-w-2xl">
          <input type="hidden" name="page" value="home">
          <input type="hidden" name="tab" value="all">
          <div class="flex bg-white rounded-full overflow-hidden shadow-md">
            <input type="text" name="q" value="<?= h($search_q) ?>" placeholder="Enter skills, titles or company names" class="flex-grow px-4 py-3 text-gray-700 min-w-0"/>
            <input type="text" name="l" value="<?= h($search_loc) ?>" placeholder="Location" class="w-40 px-3 py-3 text-gray-700 border-l"/>
            <button class="bg-blue-600 hover:bg-blue-700 px-6 text-white">Search</button>
          </div>
        </form>

        <div class="bg-white rounded-xl shadow p-6 mt-8">
          <?php $activeTab=$_GET['tab']??'all'; if(!in_array($activeTab,['all','applies','favorites'],true)) $activeTab='all'; ?>
          <div class="border-b flex text-sm overflow-x-auto">
            <button class="tab-btn <?= $activeTab==='all'?'active':'' ?>" onclick="activateTab('all')">All Jobs</button>
            <button class="tab-btn <?= $activeTab==='applies'?'active':'' ?>" onclick="activateTab('applies')">Applies (<?= (int)$cnt_applied ?>)</button>
            <!--<?php if($favoritesEnabled): ?>-->
            <!--  <button class="tab-btn <?= $activeTab==='favorites'?'active':'' ?>" onclick="activateTab('favorites')">Favorites (<?= (int)$cnt_favs ?>)</button>-->
            <!--<?php endif; ?>-->
          </div>

          <div id="all" class="tab mt-6 <?= $activeTab==='all'?'':'hidden' ?>">
            <?php if($all_rs && $all_rs->num_rows): ?>
              <div class="grid md:grid-cols-2 gap-4">
                <?php while($j=$all_rs->fetch_assoc()): ?>
                  <?= job_card($j,true,$jobsT,$JOB_ID,$JOB_TITLE,$JOB_LOCATION,$JOB_DESC,$APP_STATUS,false,$favoritesEnabled) ?>
                <?php endwhile; ?>
              </div>
              <?php $total_pages=max(1,(int)ceil($total_all/max(1,$per_page)));
                if($total_pages>1):
                  $base=basename(__FILE__).'?page=home&tab=all&q='.urlencode($search_q).'&l='.urlencode($search_loc); ?>
                <div class="pager mt-6">
                  <?php if($page_no>1): ?><a href="<?= h($base.'&p='.($page_no-1)) ?>">&laquo; Prev</a><?php endif; ?>
                  <?php $start=max(1,$page_no-2); $end=min($total_pages,$page_no+2); for($i=$start;$i<=$end;$i++): ?>
                    <?php if($i==$page_no): ?><span class="cur"><?= $i ?></span>
                    <?php else: ?><a href="<?= h($base.'&p='.$i) ?>"><?= $i ?></a><?php endif; ?>
                  <?php endfor; ?>
                  <?php if($page_no<$total_pages): ?><a href="<?= h($base.'&p='.($page_no+1)) ?>">Next &raquo;</a><?php endif; ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-gray-500">No jobs found. Try a different search.</div>
            <?php endif; ?>
          </div>

          <div id="applies" class="tab mt-6 grid md:grid-cols-2 gap-4 <?= $activeTab==='applies'?'':'hidden' ?>">
            <?php if(isset($applied_rs) && $applied_rs && $applied_rs->num_rows): while($j=$applied_rs->fetch_assoc()): ?>
              <?= job_card($j,false,$jobsT,$JOB_ID,$JOB_TITLE,$JOB_LOCATION,$JOB_DESC,$APP_STATUS,true,$favoritesEnabled) ?>
            <?php endwhile; else: ?><div class="text-gray-500">You haven't applied to any jobs yet.</div><?php endif; ?>
          </div>

          <?php if($favoritesEnabled): ?>
          <div id="favorites" class="tab mt-6 grid md:grid-cols-2 gap-4 <?= $activeTab==='favorites'?'':'hidden' ?>">
            <?php if(isset($favs_rs) && $favs_rs && $favs_rs->num_rows): while($j=$favs_rs->fetch_assoc()): ?>
              <?= job_card($j,true,$jobsT,$JOB_ID,$JOB_TITLE,$JOB_LOCATION,$JOB_DESC,$APP_STATUS,false,$favoritesEnabled) ?>
            <?php endwhile; else: ?><div class="text-gray-500">No favorite jobs yet.</div><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mt-6">
          <!--<div class="bg-white rounded-xl shadow p-5 text-center">-->
          <!--  <div class="text-emerald-500 text-2xl"><i class="fa-solid fa-paper-plane"></i></div>-->
          <!--  <div class="text-3xl font-bold"><?= (int)$cnt_applied ?></div>-->
          <!--  <div class="text-sm text-gray-500">Applied Jobs</div>-->
          <!--</div>-->
          <!--<div class="bg-white rounded-xl shadow p-5 text-center">-->
          <!--  <div class="text-yellow-500 text-2xl"><i class="fa-regular fa-envelope"></i></div>-->
          <!--  <div class="text-3xl font-bold">00</div>-->
          <!--  <div class="text-sm text-gray-500">New Messages</div>-->
          <!--</div>-->
          <!--<div class="bg-white rounded-xl shadow p-5 text-center">-->
          <!--  <div class="text-sky-500 text-2xl"><i class="fa-solid fa-star"></i></div>-->
          <!--  <div class="text-3xl font-bold"><?= (int)$cnt_favs ?></div>-->
          <!--  <div class="text-sm text-gray-500">Favorites</div>-->
          <!--</div>-->
        </div>
      </div>

      <aside class="bg-white rounded-xl shadow p-6">
        <?php $photo=(!empty($me['profile_picture'])?$me['profile_picture']:'img/user-placeholder.png'); ?>
        <div class="flex items-center gap-3">
          <img src="<?= h($photo) ?>" class="w-14 h-14 rounded-full object-cover" alt="Profile">
          <div class="min-w-0">
            <div class="font-semibold text-blue-800 text-lg truncate"><?= h($me['full_name'] ?: 'Your Name') ?></div>
            <div class="text-sm text-gray-700 truncate"><?= h($me['professional_title'] ?: 'Jobseeker') ?></div>
          </div>
        </div>
        <div class="mt-5">
          <div class="w-full bg-gray-200 h-2 rounded-full"><div class="bg-emerald-500 h-2 rounded-full" style="width:<?= (int)$completion ?>%"></div></div>
          <div class="text-xs text-gray-500 mt-1"><?= (int)$completion ?>% completed</div>
        </div>
        <a href="<?= h(basename(__FILE__)) ?>?page=profile" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded w-full text-center">View & Edit Profile</a>
      </aside>
    </div>
  </div>
</section>

<footer class="bg-white border-t py-8 mt-12">
  <div class="max-w-7xl mx-auto px-4 text-center text-gray-600">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
      <div class="flex items-center gap-2"><img src="img/MS copy.png" class="h-6 rounded" alt="MS JOBS"><span class="font-semibold">MS JOBS</span></div>
      <div class="text-sm">© <?= date('Y') ?> MS JOBS. All rights reserved.</div>
      <div class="flex gap-4 text-gray-400"><a href="#" class="hover:text-blue-600"><i class="fab fa-linkedin-in"></i></a><a href="#" class="hover:text-blue-600"><i class="fab fa-twitter"></i></a></div>
    </div>
  </div>
</footer>

<!-- Job Full View Modal -->
<dialog id="jobModal" class="w-[min(100%-2rem,900px)] rounded-lg p-0">
  <form method="dialog">
    <div class="p-5 border-b flex items-center justify-between">
      <div>
        <h3 id="jm_title" class="text-xl font-semibold text-gray-900"></h3>
        <div id="jm_meta" class="text-sm text-gray-600 mt-1"></div>
      </div>
      <button class="px-3 py-1.5 rounded bg-gray-100 text-gray-700 hover:bg-gray-200"><i class="fa fa-times"></i></button>
    </div>
    <div class="p-5 max-h-[70vh] overflow-y-auto">
      <div id="jm_desc" class="prose prose-sm max-w-none"></div>
    </div>
    <div class="p-5 border-t flex justify-end gap-2">
      <button class="px-4 py-2 rounded bg-blue-600 text-white" id="jm_apply">Apply</button>
    </div>
  </form>
</dialog>
<!-- Scripts -->
<script>
function activateTab(id){
  const url=new URL(window.location.href);
  url.searchParams.set('tab',id);
  if(!url.searchParams.has('page')) url.searchParams.set('page','home');
  window.location.href=url.toString();
}

/* ---------- Helpers ---------- */
function safeJSON(text){ try{ return JSON.parse(text);}catch{ return null; } }
function updateFavoriteUI(jobId, nowFav){
  document.querySelectorAll(`.favorite-job[data-jobid="${jobId}"]`).forEach(btn=>{
    const icon=btn.querySelector('i');
    btn.dataset.fav = nowFav ? '1' : '0';
    if(icon) icon.className = nowFav ? 'fa-solid fa-star' : 'fa-regular fa-star';
  });
}

/* Favorite toggle — strict JSON only; robust to HTML/redirect noise */
document.addEventListener('click', async (e)=>{
  const btn=e.target.closest('.favorite-job'); if(!btn) return;
  e.preventDefault();
  const jobid=btn.dataset.jobid; const icon=btn.querySelector('i');
  const oldIcon=icon?icon.className:'';
  btn.disabled=true; if(icon) icon.className='fa fa-spinner fa-spin';
  try{
    const fd=new FormData(); fd.append('action','favorite'); fd.append('job_id',jobid);
    const res=await fetch('<?= h(basename(__FILE__)) ?>',{ method:'POST', body:fd, credentials:'same-origin' });
    const text=await res.text(); const data=safeJSON(text);
    if(!data) throw new Error('Unexpected server response (not JSON).');
    if(!data.ok){
      if(data.error==='AUTH_REQUIRED'){ throw new Error('Please log in to use Favorites.'); }
      throw new Error(data.error||'Failed to update favorite.');
    }
    updateFavoriteUI(jobid, !!data.favorited);
  }catch(err){
    if(icon) icon.className=oldIcon;
    alert('Error: '+err.message);
  }finally{
    btn.disabled=false;
  }
});

/* Job Full View modal open */
const dlg=document.getElementById('jobModal');
document.addEventListener('click',(e)=>{
  const v=e.target.closest('.view-job'); if(!v) return; e.preventDefault();
  const title=v.dataset.title||''; const company=v.dataset.company||''; const loc=v.dataset.loc||'';
  const descHTML=v.dataset.desc||''; const jid=v.dataset.jid||'';
  const titleEl=document.getElementById('jm_title');
  const metaEl=document.getElementById('jm_meta');
  const descEl=document.getElementById('jm_desc');
  const applyEl=document.getElementById('jm_apply');
  if(titleEl) titleEl.textContent=title;
  if(metaEl) metaEl.textContent=[company,loc].filter(Boolean).join(' • ');
  if(descEl) descEl.innerHTML=descHTML;
  if(applyEl){
    applyEl.onclick=(ev)=>{ ev.preventDefault(); if(jid) window.location.href=`apply.php?job_id=${jid}`; };
  }
  if(dlg && typeof dlg.showModal==='function'){ dlg.showModal(); }
  else{ alert((title?title+'\n':'')+([company,loc].filter(Boolean).join(' • ')+ '\n\n')+descHTML.replace(/<[^>]+>/g,'')); }
});
</script>

</body>
</html>
<?php }

/* --------- PROFILE PAGE (no Alerts/Peers; share + exp/edu) --------- */
function render_profile_page(mysqli $db, array $me, int $completion, string $msg, bool $favoritesEnabled): void
{
  $uid = (int)($_SESSION['user_id'] ?? 0);
  $me  = load_me($db, $uid);

  $exp = $db->prepare("SELECT * FROM jobseeker_experience WHERE user_id=? ORDER BY start_date DESC, id DESC");
  $exp->bind_param("i", $uid); $exp->execute(); $exp_rs = $exp->get_result();

  $edu = $db->prepare("SELECT * FROM jobseeker_education WHERE user_id=? ORDER BY start_year DESC, id DESC");
  $edu->bind_param("i", $uid); $edu->execute(); $edu_rs = $edu->get_result();

  $pct = profile_completion($me);
  $photo = (!empty($me['profile_picture']) ? $me['profile_picture'] : 'img/user-placeholder.png');
  $share_enabled = (int)($me['share_enabled'] ?? 0);
  $share_token   = (string)($me['share_token'] ?? '');
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $share_url = ($share_enabled && $share_token)
      ? $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/' . basename(__FILE__) . '?view=public&token=' . $share_token
      : '';
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Employee Profile — MSJOBS</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
  body{font-family:Poppins,system-ui;background:#f3f6fb}
  .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);transition:transform .2s}
  .btn-primary:hover{transform:translateY(-1px)}
</style>
</head>
<body>
<?php navbar(); ?>
<?php if(isset($_GET['success'])): ?>
  <div class="max-w-7xl mx-auto px-4 mt-4">
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">Profile updated successfully!</div>
  </div>
<?php endif; ?>
<?php if($msg): ?>
  <div class="max-w-7xl mx-auto px-4 mt-4">
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"><?= h($msg) ?></div>
  </div>
<?php endif; ?>

<section class="bg-gradient-to-r from-blue-600 to-sky-400 text-white py-10">
  <div class="max-w-7xl mx-auto px-4 text-center">
    <h1 class="text-2xl md:text-3xl font-semibold mb-2">Build Your Professional Profile</h1>
    <p class="text-lg opacity-90">Create a stunning profile to showcase your skills and experience</p>
  </div>
</section>

<main class="max-w-7xl mx-auto px-4 -mt-8 relative z-10 grid grid-cols-1 lg:grid-cols-3 gap-8 pb-12">
  <div class="lg:col-span-2 space-y-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-900">Professional Profile</h2>
        <div class="text-sm text-gray-500"><i class="fa fa-shield-alt"></i> Your data is secure</div>
      </div>

      <form method="post" enctype="multipart/form-data" class="space-y-6" action="<?= h(basename(__FILE__)) ?>?page=profile">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="save_profile" value="1">

        <div class="bg-gray-50 rounded-lg p-4">
          <h3 class="font-semibold text-gray-800 mb-4">Basic Information</h3>
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
              <input name="full_name" value="<?= h($me['full_name']) ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-900">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Professional Title</label>
              <input name="professional_title" value="<?= h($me['professional_title']) ?>" placeholder="e.g., Senior Software Engineer" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
              <input name="location" value="<?= h($me['location']) ?>" placeholder="e.g., Dubai, UAE" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label>
              <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
              <p class="text-xs text-gray-500 mt-1">Max 5MB. JPG/PNG/WebP</p>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <h3 class="font-semibold text-gray-800 mb-4">Professional Summary</h3>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">About You</label>
            <textarea name="summary" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Brief summary..."><?= h($me['summary']??'') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Skills</label>
            <input name="skills_csv" value="<?= h($me['skills_csv']) ?>" placeholder="PHP, JavaScript, React, MySQL" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            <p class="text-xs text-gray-500 mt-1">Separate with commas</p>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <h3 class="font-semibold text-gray-800 mb-4">Professional Links</h3>
          <div class="grid md:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Website</label><input name="website" type="url" value="<?= h($me['website']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn</label><input name="linkedin" type="url" value="<?= h($me['linkedin']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">GitHub</label><input name="github" type="url" value="<?= h($me['github']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Twitter/X</label><input name="twitter" type="url" value="<?= h($me['twitter']??'') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
          </div>
        </div>

        <!-- Experience -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Work Experience</h3>
            <button type="button" id="addExp" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm"><i class="fa fa-plus mr-1"></i> Add Experience</button>
          </div>
          <div id="expWrap" class="space-y-4">
            <?php if($exp_rs->num_rows>0): while($e=$exp_rs->fetch_assoc()): ?>
              <div class="experience-item border border-gray-200 rounded-lg p-4 bg-white relative">
                <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700 remove-exp" title="Remove"><i class="fa fa-times"></i></button>
                <div class="grid md:grid-cols-2 gap-4">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Job Title *</label><input name="exp_title[]" value="<?= h($e['title']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" required></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Company *</label><input name="exp_company[]" value="<?= h($e['company']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" required></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label><input type="date" name="exp_start[]" value="<?= h($e['start_date']) ?>" class="w-full border border-gray-300 rounded px-3 py-2"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">End Date</label><input type="date" name="exp_end[]" value="<?= h($e['end_date']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" <?= ((int)$e['is_current']===1?'disabled':'') ?>></div>
                  <div class="md:col-span-2"><label class="flex items-center text-sm font-medium text-gray-700"><input type="checkbox" name="exp_current[]" class="mr-2 current-job" <?= ((int)$e['is_current']===1?'checked':'') ?>> I currently work here</label></div>
                  <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea name="exp_desc[]" class="w-full border border-gray-300 rounded px-3 py-2" rows="3"><?= h($e['description']) ?></textarea></div>
                </div>
              </div>
            <?php endwhile; endif; ?>
          </div>
        </div>

        <!-- Education -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Education</h3>
            <button type="button" id="addEdu" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm"><i class="fa fa-plus mr-1"></i> Add Education</button>
          </div>
          <div id="eduWrap" class="space-y-4">
            <?php if($edu_rs->num_rows>0): while($e=$edu_rs->fetch_assoc()): ?>
              <div class="education-item border border-gray-200 rounded-lg p-4 bg-white relative">
                <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700 remove-edu" title="Remove"><i class="fa fa-times"></i></button>
                <div class="grid md:grid-cols-2 gap-4">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">School/University *</label><input name="edu_school[]" value="<?= h($e['school']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" required></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Degree</label><input name="edu_degree[]" value="<?= h($e['degree']) ?>" class="w-full border border-gray-300 rounded px-3 py-2"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Field of Study</label><input name="edu_field[]" value="<?= h($e['field']) ?>" class="w-full border border-gray-300 rounded px-3 py-2"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Graduation Year</label><input name="edu_end[]" type="number" value="<?= h($e['end_year']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" min="1950" max="<?= date('Y') + 10 ?>"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Year</label><input name="edu_start[]" type="number" value="<?= h($e['start_year']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" min="1950" max="<?= date('Y') ?>"></div>
                  <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label><textarea name="edu_desc[]" class="w-full border border-gray-300 rounded px-3 py-2" rows="2"><?= h($e['description']) ?></textarea></div>
                </div>
              </div>
            <?php endwhile; endif; ?>
          </div>
        </div>

        <div class="flex flex-wrap gap-4 pt-2">
          <button type="submit" class="btn-primary text-white rounded-lg px-8 py-3 font-semibold"><i class="fa fa-save mr-2"></i> Save Profile</button>
          <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg px-6 py-3 font-semibold" onclick="window.location.reload()"><i class="fa fa-undo mr-2"></i> Reset</button>
        </div>
      </form>
    </div>
  </div>

  <aside class="space-y-6">
    <div class="bg-white rounded-xl shadow-lg p-6 text-center">
      <img src="<?= h($photo) ?>" class="w-24 h-24 mx-auto rounded-full object-cover border-4 border-blue-500 shadow-lg" alt="Profile Picture">
      <h3 class="mt-4 font-bold text-blue-700 text-lg truncate"><?= h($me['full_name'] ?: 'Your Name') ?></h3>
      <p class="text-sm text-gray-600"><?= h($me['email'] ?? '') ?></p>
      <?php if(!empty($me['professional_title'])): ?><p class="text-sm text-blue-600 font-medium mt-1"><?= h($me['professional_title']) ?></p><?php endif; ?>
      <div class="mt-6">
        <div class="w-full bg-gray-200 rounded-full h-3"><div class="bg-gradient-to-r from-green-400 to-blue-500 h-3 rounded-full" style="width: <?= (int)$pct ?>%"></div></div>
        <p class="text-sm text-gray-600 mt-2"><span class="font-semibold text-gray-700"><?= (int)$pct ?>%</span> Profile Completed</p>
      </div>
    </div>

    <!--<div class="bg-white rounded-xl shadow-lg p-6">-->
    <!--  <div class="flex items-center justify-between mb-4">-->
    <!--    <h3 class="font-semibold text-gray-800">Share Your Profile</h3>-->
    <!--    <i class="fa fa-share-alt text-blue-500"></i>-->
    <!--  </div>-->
    <!--  <p class="text-sm text-gray-600 mb-4">Generate a public link to share your professional profile.</p>-->

    <!--  <div class="space-y-3">-->
    <!--    <div class="flex items-center gap-2">-->
    <!--      <input id="shareUrl" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50" readonly value="<?= h($share_url) ?>">-->
    <!--      <button id="copyBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg px-3 py-2 text-sm" title="Copy"><i class="fa fa-copy"></i></button>-->
    <!--    </div>-->
    <!--    <div class="flex gap-2">-->
    <!--      <button id="genBtn" class="flex-1 bg-green-600 hover:bg-green-700 text-white rounded-lg px-4 py-2 text-sm font-medium"><?= $share_enabled ? 'Update Link' : 'Enable Sharing' ?></button>-->
    <!--      <button id="disableBtn" class="flex-1 bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 rounded-lg px-4 py-2 text-sm font-medium">Disable</button>-->
    <!--    </div>-->
    <!--    <div id="shareStatus" class="text-center">-->
    <!--      <?php if ($share_enabled): ?>-->
    <!--        <p class="text-xs text-green-600"><i class="fa fa-check-circle mr-1"></i> Public sharing is enabled</p>-->
    <!--      <?php else: ?>-->
    <!--        <p class="text-xs text-gray-500"><i class="fa fa-eye-slash mr-1"></i> Public sharing is disabled</p>-->
    <!--      <?php endif; ?>-->
    <!--    </div>-->
    <!--    <p id="shareMsg" class="text-xs text-center text-gray-500"></p>-->
    <!--  </div>-->
    <!--</div>-->

    <div class="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-xl p-6">
      <h3 class="font-semibold text-gray-800 mb-3"><i class="fa fa-lightbulb text-yellow-500 mr-2"></i> Profile Tips</h3>
      <ul class="text-sm text-gray-700 space-y-2">
        <li class="flex items-start"><i class="fa fa-check text-green-500 mr-2 mt-0.5 text-xs"></i>Use a professional headshot</li>
        <li class="flex items-start"><i class="fa fa-check text-green-500 mr-2 mt-0.5 text-xs"></i>Write a compelling summary</li>
        <li class="flex items-start"><i class="fa fa-check text-green-500 mr-2 mt-0.5 text-xs"></i>List relevant skills</li>
        <li class="flex items-start"><i class="fa fa-check text-green-500 mr-2 mt-0.5 text-xs"></i>Include measurable achievements</li>
      </ul>
    </div>
  </aside>
</main>

<footer class="bg-white border-t py-8 mt-12">
  <div class="max-w-7xl mx-auto px-4 text-center text-gray-600">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
      <div class="flex items-center gap-2"><img src="img/MS copy.png" class="h-6 rounded" alt="MS JOBS"><span class="font-semibold">MS JOBS</span></div>
      <div class="text-sm">© <?= date('Y') ?> MS JOBS. All rights reserved.</div>
      <div class="flex gap-4 text-gray-400"><a href="#" class="hover:text-blue-600"><i class="fab fa-facebook-f"></i></a><a href="#" class="hover:text-blue-600"><i class="fab fa-linkedin-in"></i></a></div>
    </div>
  </div>
</footer>

<script>
/* Experience dynamic */
const expTemplate = () => `<div class="experience-item border border-gray-200 rounded-lg p-4 bg-white relative">
  <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700 remove-exp" title="Remove"><i class="fa fa-times"></i></button>
  <div class="grid md:grid-cols-2 gap-4">
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Job Title *</label><input name="exp_title[]" class="w-full border border-gray-300 rounded px-3 py-2" required></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Company *</label><input name="exp_company[]" class="w-full border border-gray-300 rounded px-3 py-2" required></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label><input type="date" name="exp_start[]" class="w-full border border-gray-300 rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">End Date</label><input type="date" name="exp_end[]" class="w-full border border-gray-300 rounded px-3 py-2"></div>
    <div class="md:col-span-2"><label class="flex items-center text-sm font-medium text-gray-700"><input type="checkbox" name="exp_current[]" class="mr-2 current-job"> I currently work here</label></div>
    <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea name="exp_desc[]" class="w-full border border-gray-300 rounded px-3 py-2" rows="3"></textarea></div>
  </div>
</div>`;
document.getElementById('addExp')?.addEventListener('click', ()=>{ document.getElementById('expWrap').insertAdjacentHTML('beforeend', expTemplate()); attachExp(); });
function attachExp(){
  document.querySelectorAll('.remove-exp').forEach(b=>b.onclick=()=>b.closest('.experience-item').remove());
  document.querySelectorAll('.current-job').forEach(ch=>{
    ch.onchange=()=>{
      const end = ch.closest('.experience-item').querySelector('input[name="exp_end[]"]');
      if(!end) return;
      if(ch.checked){ end.disabled=true; end.value=''; end.style.backgroundColor='#f9fafb'; }
      else{ end.disabled=false; end.style.backgroundColor=''; }
    };
  });
}
attachExp();

/* Education dynamic */
const eduTemplate = () => `<div class="education-item border border-gray-200 rounded-lg p-4 bg-white relative">
  <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700 remove-edu" title="Remove"><i class="fa fa-times"></i></button>
  <div class="grid md:grid-cols-2 gap-4">
    <div><label class="block text-sm font-medium text-gray-700 mb-1">School/University *</label><input name="edu_school[]" class="w-full border border-gray-300 rounded px-3 py-2" required></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Degree</label><input name="edu_degree[]" class="w-full border border-gray-300 rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Field of Study</label><input name="edu_field[]" class="w-full border border-gray-300 rounded px-3 py-2"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Graduation Year</label><input name="edu_end[]" type="number" class="w-full border border-gray-300 rounded px-3 py-2" min="1950" max="<?= date('Y') + 10 ?>"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Year</label><input name="edu_start[]" type="number" class="w-full border border-gray-300 rounded px-3 py-2" min="1950" max="<?= date('Y') ?>"></div>
    <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label><textarea name="edu_desc[]" class="w-full border border-gray-300 rounded px-3 py-2" rows="2"></textarea></div>
  </div>
</div>`;
document.getElementById('addEdu')?.addEventListener('click', ()=>{ document.getElementById('eduWrap').insertAdjacentHTML('beforeend', eduTemplate()); attachEdu(); });
function attachEdu(){ document.querySelectorAll('.remove-edu').forEach(b=>b.onclick=()=>b.closest('.education-item').remove()); }
attachEdu();

/* Share profile functionality (strict JSON) */
document.getElementById('genBtn')?.addEventListener('click', async function(){
  try{
    const res=await fetch('<?= h(basename(__FILE__)) ?>',{ method:'POST', body:new URLSearchParams({ 'action':'enable','csrf':'<?= h(csrf_token()) ?>' }), credentials:'same-origin' });
    const text=await res.text(); const data=(()=>{ try{return JSON.parse(text)}catch{return null} })();
    if(data && data.ok){
      document.getElementById('shareUrl').value=data.url;
      document.getElementById('shareStatus').innerHTML='<p class="text-xs text-green-600"><i class="fa fa-check-circle mr-1"></i> Public sharing is enabled</p>';
      document.getElementById('shareMsg').textContent='Link copied to clipboard!';
      navigator.clipboard.writeText(data.url);
    }else{
      document.getElementById('shareMsg').textContent='Error: '+(data?.error||'Unexpected response');
    }
  }catch(err){ document.getElementById('shareMsg').textContent='Network error: '+err.message; }
});
document.getElementById('disableBtn')?.addEventListener('click', async function(){
  try{
    const res=await fetch('<?= h(basename(__FILE__)) ?>',{ method:'POST', body:new URLSearchParams({ 'action':'disable','csrf':'<?= h(csrf_token()) ?>' }), credentials:'same-origin' });
    const text=await res.text(); const data=(()=>{ try{return JSON.parse(text)}catch{return null} })();
    if(data && data.ok){
      document.getElementById('shareUrl').value='';
      document.getElementById('shareStatus').innerHTML='<p class="text-xs text-gray-500"><i class="fa fa-eye-slash mr-1"></i> Public sharing is disabled</p>';
      document.getElementById('shareMsg').textContent='Sharing disabled successfully';
    }else{
      document.getElementById('shareMsg').textContent='Error: '+(data?.error||'Unexpected response');
    }
  }catch(err){ document.getElementById('shareMsg').textContent='Network error: '+err.message; }
});
document.getElementById('copyBtn')?.addEventListener('click', function(){
  const shareUrl=document.getElementById('shareUrl');
  if(shareUrl.value){ navigator.clipboard.writeText(shareUrl.value); document.getElementById('shareMsg').textContent='Link copied to clipboard!'; }
  else{ document.getElementById('shareMsg').textContent='No link to copy'; }
});
</script>

</body>
</html>
<?php }
