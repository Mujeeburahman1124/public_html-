<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(E_ALL); ini_set('display_errors', 1);

function ensure_csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
  return $_SESSION['csrf_token'];
}
function verify_csrf_token(?string $t): void {
  if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
    throw new Exception('Security check failed. Please refresh and try again.');
  }
}

class DatabaseManager {
  private $conn;
  public function __construct() {
    require __DIR__ . '/config.php'; $this->conn = $conn; // $this->conn = new mysqli("127.0.0.1:3306","u903588615_root","Msjobs#1","u903588615_exaple");
    if ($this->conn->connect_error) throw new Exception("Database connection failed: ".$this->conn->connect_error);
    $this->conn->set_charset("utf8mb4");
  }
  public function getConnection(){ return $this->conn; }
  public function executeQuery($q,$params=[],$types=null){
    $stmt=$this->conn->prepare($q);
    if(!$stmt) throw new Exception("DB prepare failed: ".$this->conn->error);
    if($params){
      if($types===null) $types=str_repeat('s',count($params));
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res=$stmt->get_result();
    return $res?:true;
  }
}

class UserManager {
  private $db;
  public function __construct(DatabaseManager $db){ $this->db=$db; }
  public function deleteUser($id){
    $c=$this->db->getConnection(); $c->begin_transaction();
    try{
      $id=(int)$id;
      $this->db->executeQuery("DELETE FROM jobseekers WHERE user_id = ?",[$id],'i');
      $this->db->executeQuery("DELETE FROM employers  WHERE user_id = ?",[$id],'i');
      $this->db->executeQuery("DELETE FROM users      WHERE id      = ?",[$id],'i');
      $c->commit(); return "User ID $id has been successfully deleted.";
    }catch(Exception $e){ $c->rollback(); throw new Exception("Failed to delete user: ".$e->getMessage()); }
  }
  public function blockUser($id){ $id=(int)$id; $this->db->executeQuery("UPDATE users SET status='blocked',updated_at=NOW() WHERE id=?",[$id],'i'); return "User ID $id has been blocked successfully."; }
  public function unblockUser($id){ $id=(int)$id; $this->db->executeQuery("UPDATE users SET status='active',updated_at=NOW() WHERE id=?",[$id],'i'); return "User ID $id has been unblocked successfully."; }
  public function changePassword($id,$hash){ $id=(int)$id; $this->db->executeQuery("UPDATE users SET password=?,updated_at=NOW() WHERE id=?",[$hash,$id],'si'); return true; }
  public function getAllUsers(){ return $this->db->executeQuery("SELECT * FROM users ORDER BY created_at DESC"); }
  public function getUserDetails($uid,$type){
    $table=$type==='jobseeker'?'jobseekers':'employers';
    $cols =$type==='jobseeker'?'full_name, country':'company_name, country';
    $r=$this->db->executeQuery("SELECT $cols FROM $table WHERE user_id=?",[(int)$uid],'i');
    return $r->fetch_assoc();
  }
  public function getUserById($id){
    $r=$this->db->executeQuery("SELECT id,email,user_type,status FROM users WHERE id=?",[(int)$id],'i');
    return $r->fetch_assoc();
  }
}

class EmailService {
  private $mailer;
  public function __construct(){ $this->mailer=new PHPMailer(true); $this->configureMailer(); }
  private function configureMailer(){
    $this->mailer->isSMTP();
    $this->mailer->Host='smtp.gmail.com';
    $this->mailer->SMTPAuth=true;
    $this->mailer->Username='mshrc936@gmail.com';
    $this->mailer->Password='nmspuxcjuptondkd';
    $this->mailer->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS;
    $this->mailer->Port=587;
    $this->mailer->setFrom('mshrc936@gmail.com','MS JOBS');
    $this->mailer->isHTML(true);
  }
  public function sendBulkEmail($recips,$subject,$body){
    $sent=0; $errors=[];
    foreach($recips as $email){
      try{
        $this->mailer->clearAddresses();
        $this->mailer->addAddress($email);
        $this->mailer->Subject=$subject;
        $this->mailer->Body=$this->getEmailTemplate($body);
        $this->mailer->AltBody=strip_tags($body);
        $this->mailer->send(); $sent++;
      }catch(Exception $e){ $errors[]="Failed to send to $email: ".$e->getMessage(); }
    }
    return ['sent'=>$sent,'errors'=>$errors];
  }
  public function notifyPasswordChange($to){
    try{
      $this->mailer->clearAddresses(); $this->mailer->addAddress($to);
      $this->mailer->Subject='Your MS JOBS password was changed';
      $b="Hello,\n\nThis is a confirmation that your password on MS JOBS has been changed. If you did not request this change, please contact support immediately.";
      $this->mailer->Body=$this->getEmailTemplate($b);
      $this->mailer->AltBody=strip_tags($b);
      $this->mailer->send(); return true;
    }catch(Exception $e){ return false; }
  }
  private function getEmailTemplate($content){
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MS JOBS Notification</title><style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.6;color:#1a202c;background:#f7fafc}
    .container{max-width:640px;margin:40px auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,.08)}
    .header{background:linear-gradient(135deg,#667eea,#764ba2);padding:40px 30px;text-align:center;color:#fff}
    .logo{width:64px;height:64px;margin:0 auto 16px;background:rgba(255,255,255,.2);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700}
    .content{padding:40px 30px;font-size:16px}
    .footer{padding:30px;background:#f8fafc;text-align:center;color:#718096;font-size:14px;border-top:1px solid #e2e8f0}
    @media(max-width:640px){.container{margin:20px;border-radius:16px}.content,.header{padding:30px 20px}}
    </style></head><body>
    <div class="container"><div class="header"><div class="logo">MS</div><h1>MS JOBS</h1><p>Professional Job Portal</p></div>
    <div class="content">'.nl2br(htmlspecialchars($content,ENT_QUOTES,'UTF-8')).'</div>
    <div class="footer">&copy; '.date('Y').' MS JOBS</div></div></body></html>';
  }
}

try { $db=new DatabaseManager(); $um=new UserManager($db); $mailer=new EmailService(); }
catch(Exception $e){ die("System initialization failed: ".$e->getMessage()); }

try{
  if (isset($_GET['delete']))  { $m=$um->deleteUser($_GET['delete']);   header("Location: ManageUsers.php?msg=".urlencode($m)."&type=success"); exit; }
  if (isset($_GET['block']))   { $m=$um->blockUser($_GET['block']);     header("Location: ManageUsers.php?msg=".urlencode($m)."&type=success"); exit; }
  if (isset($_GET['unblock'])) { $m=$um->unblockUser($_GET['unblock']); header("Location: ManageUsers.php?msg=".urlencode($m)."&type=success"); exit; }

  if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_email'])) {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $emails  = $_POST['selected_users'] ?? [];
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    if (!$emails) throw new Exception("No users selected for email.");
    if ($subject==='' || $body==='') throw new Exception("Subject and message body are required.");
    $r=$mailer->sendBulkEmail($emails,$subject,$body);
    $msg = $r['errors'] ? "Email sent to {$r['sent']} user(s). ".count($r['errors'])." failed." : "Email successfully sent to {$r['sent']} user(s).";
    $type = $r['errors'] ? 'warning' : 'success';
    header("Location: ManageUsers.php?msg=".urlencode($msg)."&type=$type"); exit;
  }

  if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_password'])) {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $id=(int)($_POST['target_user_id'] ?? 0);
    $a=(string)($_POST['new_password'] ?? ''); $b=(string)($_POST['confirm_password'] ?? '');
    $notify= isset($_POST['notify_user']);
    if ($id<=0) throw new Exception("Invalid user selected.");
    if ($a!==$b) throw new Exception("Passwords do not match.");
    $lenOK = strlen($a)>=8;
    $classes = preg_match('/[a-z]/',$a)+preg_match('/[A-Z]/',$a)+preg_match('/\d/',$a)+preg_match('/[^a-zA-Z0-9]/',$a);
    if(!$lenOK || $classes<3) throw new Exception("Password too weak. Use 8+ characters with a mix of upper, lower, number, and symbol.");
    $um->changePassword($id, password_hash($a,PASSWORD_DEFAULT));
    if ($notify){ $u=$um->getUserById($id); if(!empty($u['email'])) $mailer->notifyPasswordChange($u['email']); }
    header("Location: ManageUsers.php?msg=".urlencode("Password updated successfully for User ID $id.")."&type=success"); exit;
  }
}catch(Exception $e){
  header("Location: ManageUsers.php?msg=".urlencode($e->getMessage())."&type=error"); exit;
}

$users      = $um->getAllUsers();
$totalUsers = $users->num_rows;
$csrfToken  = ensure_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1" />
<title>User Management - MS JOBS Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
<script>
tailwind.config = {
  theme:{ extend:{ colors:{ primary:{500:'#3b82f6'}, gray:{25:'#fcfcfd'} }, fontFamily:{sans:['Inter','system-ui','-apple-system','sans-serif']},
  animation:{'fade-in':'fadeIn .4s ease','slide-up':'slideUp .28s ease'}, keyframes:{
    fadeIn:{'0%':{opacity:0,transform:'translateY(8px)'},'100%':{opacity:1,transform:'translateY(0)'}},
    slideUp:{'0%':{opacity:0,transform:'translateY(10px)'},'100%':{opacity:1,transform:'translateY(0)'}}
  } } }
}
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
body{font-feature-settings:'cv02','cv03','cv04','cv11'}
.glass{background:rgba(255,255,255,.85);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.2)}
.card-hover{transition:all .25s cubic-bezier(.4,0,.2,1)}
.card-hover:hover{transform:translateY(-2px);box-shadow:0 18px 30px -12px rgba(0,0,0,.12)}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
.btn-primary:hover{filter:brightness(.95)}
.meter{height:8px;border-radius:9999px;background:#e5e7eb;overflow:hidden}.meter>div{height:100%;width:0;transition:width .25s}
@supports (height: 100dvh){ .h-screen-dvh{ height: 100dvh; } }
@supports not (height: 100dvh){ .h-screen-dvh{ height: 100vh; } }
</style>
</head>
<body class="bg-gradient-to-br from-gray-25 via-blue-50 to-indigo-50 min-h-screen">

<header class="sticky top-0 z-40 glass border-b border-gray-200/50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="h-20 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl grid place-items-center shadow-lg">
          <i class="ri-shield-user-line text-white text-xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold bg-gradient-to-r from-gray-900 via-blue-800 to-purple-800 bg-clip-text text-transparent">User Management</h1>
          <p class="text-sm text-gray-600">Admin Dashboard • Total Users: <?= $totalUsers ?></p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button onclick="openComposeModal()" class="btn-primary px-5 py-2.5 rounded-xl font-semibold shadow-lg flex items-center gap-2">
          <i class="ri-mail-send-line"></i><span>Compose Email</span>
        </button>
        <a href="export_users.php" class="px-4 py-2.5 bg-white hover:bg-gray-50 border border-gray-300 rounded-xl font-semibold text-gray-700 flex items-center gap-2">
          <i class="ri-download-2-line"></i><span class="hidden sm:inline">Export</span>
        </a>
      </div>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <?php if (isset($_GET['msg'])):
    $t = $_GET['type'] ?? 'success';
    $cls = ['success'=>'bg-emerald-50 border-emerald-200 text-emerald-800','error'=>'bg-red-50 border-red-200 text-red-800','warning'=>'bg-amber-50 border-amber-200 text-amber-800'];
    $ico = ['success'=>'ri-check-line','error'=>'ri-error-warning-line','warning'=>'ri-alert-line'];
  ?>
    <div class="animate-fade-in mb-6">
      <div class="<?= $cls[$t] ?> border rounded-2xl p-4 shadow-sm">
        <div class="flex items-start gap-3">
          <i class="<?= $ico[$t] ?> text-xl"></i>
          <div class="font-semibold"><?= htmlspecialchars($_GET['msg']) ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div class="flex-1 max-w-md relative">
        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input id="searchInput" onkeyup="searchCards()" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white" placeholder="Search by email, role, or details...">
      </div>
      <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
          <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
          <span>Select Visible</span>
        </label>
        <div class="text-sm text-gray-700">Selected: <span id="selectedCount" class="font-bold text-blue-600">0</span></div>
        <button id="emailSelectedBtn" onclick="openComposeModal()" disabled class="px-4 py-2.5 bg-blue-600 text-white rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed">Email Selected</button>
      </div>
    </div>
  </div>

  <div id="cardsWrap" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    <?php while ($u=$users->fetch_assoc()):
      $d=''; $dd=$um->getUserDetails($u['id'],$u['user_type']);
      if($dd){ $d = $u['user_type']==='jobseeker' ? (($dd['full_name'] ?? '').' • '.($dd['country'] ?? '')) : (($dd['company_name'] ?? '').' • '.($dd['country'] ?? '')); }
    ?>
    <div class="user-card bg-white border border-gray-200 rounded-2xl p-5 card-hover relative"
         data-email="<?= strtolower($u['email']) ?>" data-role="<?= strtolower($u['user_type']) ?>" data-details="<?= strtolower($d) ?>">
      <div class="absolute top-4 right-4">
        <input type="checkbox" class="user-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" value="<?= htmlspecialchars($u['email']) ?>">
      </div>
      <div class="flex items-center gap-4 mb-4">
        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-purple-400 rounded-full grid place-items-center text-white font-semibold shadow-inner">
          <?= strtoupper(substr($u['email'],0,1)) ?>
        </div>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($u['email']) ?></div>
          <div class="text-xs text-gray-500">ID: <?= $u['id'] ?></div>
        </div>
      </div>
      <div class="mb-4 space-y-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $u['user_type']==='employer'?'bg-purple-100 text-purple-800':'bg-green-100 text-green-800' ?>">
          <i class="<?= $u['user_type']==='employer'?'ri-building-line':'ri-user-line' ?> mr-1"></i><?= ucfirst($u['user_type']) ?>
        </span>
        <?php if($d): ?><div class="text-sm text-gray-600 truncate"><?= htmlspecialchars($d) ?></div><?php endif; ?>
      </div>
      <div class="mb-5">
        <?php if($u['status']==='blocked'): ?>
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800"><span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>Blocked</span>
        <?php else: ?>
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800"><span class="w-2 h-2 bg-emerald-500 rounded-full mr-2"></span>Active</span>
        <?php endif; ?>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" onclick="openPasswordModal(<?= (int)$u['id'] ?>,'<?= htmlspecialchars($u['email'],ENT_QUOTES) ?>')" class="px-3 py-2 text-blue-700 hover:text-white hover:bg-blue-600 border border-blue-200 rounded-lg text-sm font-medium"><i class="ri-key-2-line mr-1"></i>Change Password</button>
        <?php if($u['status']==='blocked'): ?>
          <a href="?unblock=<?= $u['id'] ?>" class="px-3 py-2 text-green-700 hover:text-white hover:bg-green-600 border border-green-200 rounded-lg text-sm font-medium"><i class="ri-lock-unlock-line mr-1"></i>Unblock</a>
        <?php else: ?>
          <a href="?block=<?= $u['id'] ?>" class="px-3 py-2 text-amber-700 hover:text-white hover:bg-amber-600 border border-amber-200 rounded-lg text-sm font-medium"><i class="ri-lock-line mr-1"></i>Block</a>
        <?php endif; ?>
        <a href="?delete=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')" class="px-3 py-2 text-red-700 hover:text-white hover:bg-red-600 border border-red-200 rounded-lg text-sm font-medium"><i class="ri-delete-bin-line mr-1"></i>Delete</a>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <div id="noResults" class="hidden">
    <div class="bg-white border border-dashed border-gray-300 rounded-2xl p-10 text-center mt-6">
      <i class="ri-search-line text-4xl text-gray-300"></i>
      <div class="text-lg font-semibold text-gray-600 mt-3">No users found</div>
      <div class="text-sm text-gray-400">Try adjusting your search terms</div>
    </div>
  </div>
</main>

<!-- Compose Email Modal: mobile-first, keyboard-safe -->
<div id="composeModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="composeTitle">
  <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" onclick="closeComposeModal()"></div>
  <div class="fixed inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div id="composePanel" class="bg-white w-full sm:max-w-2xl mx-0 sm:mx-2 h-screen-dvh sm:h-auto rounded-t-2xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col animate-slide-up">
      <div class="px-5 sm:px-8 py-4 sm:py-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl grid place-items-center shadow-lg">
              <i class="ri-mail-send-line text-white text-lg sm:text-xl"></i>
            </div>
            <div>
              <h3 id="composeTitle" class="text-lg sm:text-xl font-bold text-gray-900">Compose Email</h3>
              <p class="text-xs sm:text-sm text-gray-600">Send professional email to selected users</p>
            </div>
          </div>
          <button onclick="closeComposeModal()" class="p-2 rounded-xl text-gray-500 hover:bg-gray-100"><i class="ri-close-line text-2xl"></i></button>
        </div>
      </div>

      <form id="composeForm" method="POST" action="" class="flex-1 overflow-y-auto px-5 sm:px-8 py-5 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">Recipients</label>
          <div id="recipientChips" class="flex flex-wrap gap-2 p-3 sm:p-4 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 min-h-[54px] max-h-40 overflow-y-auto">
            <div class="text-sm text-gray-500 italic">Select users from the cards</div>
          </div>
          <div id="hiddenInputs"></div>
        </div>
        <div>
          <label for="emailSubject" class="block text-sm font-semibold text-gray-800 mb-2">Subject Line</label>
          <input id="emailSubject" name="subject" required class="w-full px-3.5 sm:px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter a compelling subject line...">
        </div>
        <div>
          <label for="emailBody" class="block text-sm font-semibold text-gray-800 mb-2">Message</label>
          <textarea id="emailBody" name="body" required rows="8" class="w-full px-3.5 sm:px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y min-h-[160px]" placeholder="Write your message here..."></textarea>
        </div>
      </form>

      <div id="composeFooter" class="px-5 sm:px-8 pt-3 sm:pt-4 border-t border-gray-200 bg-white" style="padding-bottom: max(env(safe-area-inset-bottom), 16px);">
        <div class="flex items-center justify-between gap-3">
          <button type="button" onclick="closeComposeModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold">Cancel</button>
          <button type="submit" form="composeForm" name="send_email" class="btn-primary px-6 sm:px-8 py-2.5 rounded-xl font-semibold shadow-lg flex items-center gap-2">
            <i class="ri-send-plane-fill"></i><span>Send Email</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div id="passwordModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="pwTitle">
  <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" onclick="closePasswordModal()"></div>
  <div class="fixed inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div class="bg-white w-full sm:max-w-md mx-0 sm:mx-2 h-screen-dvh sm:h-auto rounded-t-2xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col animate-slide-up">
      <div class="px-5 sm:px-8 py-4 sm:py-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl grid place-items-center shadow-lg">
              <i class="ri-key-2-line text-white text-lg sm:text-xl"></i>
            </div>
            <div>
              <h3 id="pwTitle" class="text-lg sm:text-xl font-bold text-gray-900">Change Password</h3>
              <p class="text-xs sm:text-sm text-gray-600">Update the user’s account password</p>
            </div>
          </div>
          <button onclick="closePasswordModal()" class="p-2 rounded-xl text-gray-500 hover:bg-gray-100"><i class="ri-close-line text-2xl"></i></button>
        </div>
      </div>

      <form id="passwordForm" method="POST" class="flex-1 overflow-y-auto px-5 sm:px-8 py-5 space-y-5" onsubmit="return validatePasswordForm()">
        <input type="hidden" name="change_password" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" id="pw_user_id" name="target_user_id" value="">
        <div><label class="block text-sm font-semibold text-gray-800 mb-1">User</label><div id="pw_user_email" class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2"></div></div>
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">New Password</label>
          <input type="password" id="new_password" name="new_password" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500" placeholder="Enter a strong password">
          <div class="mt-2 meter"><div id="meterBar"></div></div>
          <p id="meterText" class="text-xs mt-1 text-gray-500">Use 8+ chars with upper, lower, number, symbol.</p>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-2">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500" placeholder="Re-enter the password">
          <p id="matchText" class="text-xs mt-1 text-gray-500"></p>
        </div>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_user" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"><span class="text-sm text-gray-700">Notify user by email</span></label>
      </form>

      <div class="px-5 sm:px-8 pt-3 sm:pt-4 border-t border-gray-200 bg-white" style="padding-bottom: max(env(safe-area-inset-bottom), 16px);">
        <div class="flex items-center justify-between gap-3">
          <button type="button" onclick="closePasswordModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold">Cancel</button>
          <button type="submit" form="passwordForm" class="btn-primary px-6 sm:px-8 py-2.5 rounded-xl font-semibold shadow-lg flex items-center gap-2"><i class="ri-shield-check-line"></i><span>Update Password</span></button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let selectedEmails=new Set();

function updateSelectedCount(){ document.getElementById('selectedCount').textContent=selectedEmails.size; document.getElementById('emailSelectedBtn').disabled=selectedEmails.size===0; }
function visibleCheckboxes(){ return Array.from(document.querySelectorAll('.user-card:not(.hidden) .user-checkbox')); }
function updateSelectAllState(){
  const selectAll=document.getElementById('selectAll'); const boxes=visibleCheckboxes();
  const checked=boxes.filter(b=>b.checked).length; selectAll.indeterminate = checked>0 && checked<boxes.length; selectAll.checked = boxes.length>0 && checked===boxes.length;
}
function searchCards(){
  const q=(document.getElementById('searchInput').value||'').toLowerCase().trim();
  const cards=document.querySelectorAll('.user-card'); let visible=0;
  cards.forEach(c=>{ const e=c.dataset.email||'', r=c.dataset.role||'', d=c.dataset.details||''; const m=e.includes(q)||r.includes(q)||d.includes(q); c.classList.toggle('hidden',!m); if(m) visible++; });
  document.getElementById('noResults').classList.toggle('hidden',visible!==0); updateSelectAllState();
}

let vvResizeHandler=null;
function openComposeModal(){
  if (selectedEmails.size===0){ showNotification('Please select at least one user to send email.','warning'); return; }
  updateRecipientChips();
  document.getElementById('composeModal').classList.remove('hidden'); document.body.style.overflow='hidden';
  const panel=document.getElementById('composePanel');
  if (window.visualViewport){
    vvResizeHandler=()=>{ panel.style.height = window.visualViewport.height+'px'; };
    window.visualViewport.addEventListener('resize', vvResizeHandler); vvResizeHandler();
  }
  setTimeout(()=>document.getElementById('emailSubject').focus(),180);
}
function closeComposeModal(){
  document.getElementById('composeModal').classList.add('hidden'); document.body.style.overflow='auto';
  if(window.visualViewport && vvResizeHandler){ window.visualViewport.removeEventListener('resize', vvResizeHandler); vvResizeHandler=null; }
  document.getElementById('emailSubject').value=''; document.getElementById('emailBody').value='';
}
function updateRecipientChips(){
  const chips=document.getElementById('recipientChips'); const hidden=document.getElementById('hiddenInputs'); chips.innerHTML=''; hidden.innerHTML='';
  if(selectedEmails.size===0){ chips.innerHTML='<div class="text-sm text-gray-500 italic">No users selected</div>'; return; }
  selectedEmails.forEach(email=>{
    const chip=document.createElement('div');
    chip.className='inline-flex items-center gap-2 px-3 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-medium';
    chip.innerHTML=`<i class="ri-mail-line text-sm"></i><span>${email}</span>
      <button type="button" onclick="removeRecipient('${email}')" class="text-blue-600 hover:text-blue-800"><i class="ri-close-line text-sm"></i></button>`;
    chips.appendChild(chip);
    const h=document.createElement('input'); h.type='hidden'; h.name='selected_users[]'; h.value=email; hidden.appendChild(h);
  });
}
function removeRecipient(email){
  selectedEmails.delete(email);
  const cb=document.querySelector(`.user-checkbox[value="${CSS.escape(email)}"]`); if(cb) cb.checked=false;
  updateSelectedCount(); updateSelectAllState(); updateRecipientChips();
  if(selectedEmails.size===0) closeComposeModal();
}

function showNotification(msg,type='info'){
  const n=document.createElement('div');
  const colors={success:'bg-emerald-50 border-emerald-200 text-emerald-800',error:'bg-red-50 border-red-200 text-red-800',warning:'bg-amber-50 border-amber-200 text-amber-800',info:'bg-blue-50 border-blue-200 text-blue-800'};
  const icons={success:'ri-check-line',error:'ri-error-warning-line',warning:'ri-alert-line',info:'ri-information-line'};
  n.className=`fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg max-w-sm border ${colors[type]} animate-slide-up`;
  n.innerHTML=`<div class="flex items-start gap-3"><i class="${icons[type]} text-lg"></i><div class="font-semibold">${msg}</div><button onclick="this.closest('div').parentElement.remove()" class="ml-auto opacity-70 hover:opacity-100"><i class="ri-close-line"></i></button></div>`;
  document.body.appendChild(n); setTimeout(()=>n.remove(),5000);
}

function openPasswordModal(id,email){
  document.getElementById('pw_user_id').value=id;
  document.getElementById('pw_user_email').innerText=email;
  document.getElementById('new_password').value=''; document.getElementById('confirm_password').value='';
  updateMeter(''); updateMatch('');
  document.getElementById('passwordModal').classList.remove('hidden'); document.body.style.overflow='hidden';
}
function closePasswordModal(){ document.getElementById('passwordModal').classList.add('hidden'); document.body.style.overflow='auto'; }

function scorePassword(p){ if(!p) return 0; const sets=[/[a-z]/,/[A-Z]/,/\d/,/[^a-zA-Z0-9]/].reduce((a,r)=>a+(r.test(p)?1:0),0); return Math.min(100, Math.min(10,p.length)*5 + sets*12.5); }
function updateMeter(p){ const s=scorePassword(p), bar=document.getElementById('meterBar'), t=document.getElementById('meterText'); bar.style.width=s+'%'; let c='#ef4444',l='Weak'; if(s>=35&&s<65){c='#f59e0b';l='Fair';} if(s>=65&&s<85){c='#10b981';l='Good';} if(s>=85){c='#059669';l='Strong';} bar.style.background=c; t.textContent='Strength: '+l; }
function updateMatch(){ const a=document.getElementById('new_password').value, b=document.getElementById('confirm_password').value, t=document.getElementById('matchText'); if(!a&&!b){t.textContent='';return;} if(a===b){t.textContent='Passwords match'; t.className='text-xs mt-1 text-emerald-600';} else {t.textContent='Passwords do not match'; t.className='text-xs mt-1 text-red-600';} }
function validatePasswordForm(){
  const a=document.getElementById('new_password').value, b=document.getElementById('confirm_password').value;
  if(a!==b){ showNotification('Passwords do not match.','error'); return false; }
  const classes=[/[a-z]/,/[A-Z]/,/\d/,/[^a-zA-Z0-9]/].reduce((acc,re)=>acc+(re.test(a)?1:0),0);
  if(a.length<8 || classes<3){ showNotification('Password too weak. Use 8+ chars with upper, lower, number, symbol.','warning'); return false; }
  return true;
}

document.addEventListener('DOMContentLoaded', ()=>{
  document.getElementById('selectAll').addEventListener('change', function(){
    const boxes=visibleCheckboxes();
    boxes.forEach(b=>{ b.checked=this.checked; if(this.checked) selectedEmails.add(b.value); else selectedEmails.delete(b.value); });
    updateSelectedCount(); updateSelectAllState(); updateRecipientChips();
  });
  document.addEventListener('change', e=>{
    if(e.target.classList.contains('user-checkbox')){
      if(e.target.checked) selectedEmails.add(e.target.value); else selectedEmails.delete(e.target.value);
      updateSelectedCount(); updateSelectAllState();
    }
  });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape'){ closeComposeModal(); closePasswordModal(); }});
  let t; document.getElementById('searchInput').addEventListener('input', function(){ clearTimeout(t); t=setTimeout(searchCards,250); });
  document.getElementById('new_password')?.addEventListener('input', e=>{ updateMeter(e.target.value); updateMatch(); });
  document.getElementById('confirm_password')?.addEventListener('input', updateMatch);
  updateSelectedCount(); updateSelectAllState();
});
</script>
</body>
</html>
