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

/*************************************************************
 * SuperAdminUsers.php — Super Admin Password & User Management
 * Self-contained: manual DB connect, auto-migrate table, CSRF, flash
 * Actions: Create, Edit (username/email/role), Enable/Disable, Update Password, Delete
 *************************************************************/
declare(strict_types=1);
session_start();

/* --------- ENV / DB CONFIG (edit if needed) --------- */
// const DB_HOST = '127.0.0.1'; (Refactored to config.php)
// const DB_PORT = 3306; (Refactored to config.php)
// const DB_USER = 'u903588615_root'; (Refactored to config.php)
// const DB_PASS = 'Msjobs#1'; (Refactored to config.php)
// const DB_NAME = 'u903588615_exaple'; (Refactored to config.php)

/* --------- Access control --------- */
if (empty($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header('Location: admin_login.php'); exit;
}

/* --------- Utility --------- */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function flash_set(string $t, string $m): void { $_SESSION['flash'] = ['t'=>$t,'m'=>$m]; }
function flash_get(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_check(?string $t): void {
    if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) { http_response_code(400); die('CSRF validation failed.'); }
}

/* --------- DB --------- */
function db(): mysqli {
    static $conn = null;
    if ($conn) return $conn;
    $conn = @new mysqli(DB_HOST . ':' . DB_PORT, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) { http_response_code(500); die('DB connect failed: ' . $conn->connect_error); }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* --------- Auto-migrate table (safe idempotent) --------- */
function migrate(mysqli $db): void {
    $db->query("
        CREATE TABLE IF NOT EXISTS super_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL UNIQUE,
            role VARCHAR(50) NOT NULL DEFAULT 'super_admin',
            password_hash VARCHAR(255) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Add missing columns if someone changed schema earlier
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM super_admins");
    while ($row = $res->fetch_assoc()) $cols[$row['Field']] = true;

    if (!isset($cols['role']))        $db->query("ALTER TABLE super_admins ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'super_admin'");
    if (!isset($cols['active']))      $db->query("ALTER TABLE super_admins ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
    if (!isset($cols['created_at']))  $db->query("ALTER TABLE super_admins ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    if (!isset($cols['updated_at']))  $db->query("ALTER TABLE super_admins ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    // Ensure at least one admin exists to avoid lockout (optional)
    $check = $db->query("SELECT id FROM super_admins LIMIT 1");
    if ($check && $check->num_rows === 0) {
        $u = 'superadmin'; $email = 'admin@example.com'; $hash = password_hash('Super@1234', PASSWORD_DEFAULT);
        $st = $db->prepare("INSERT INTO super_admins (username,email,role,password_hash,active) VALUES (?,?,?,?,1)");
        $role = 'super_admin';
        $st->bind_param('ssss', $u, $email, $role, $hash); $st->execute();
    }
}
$db = db(); migrate($db);

/* --------- Safety helpers --------- */
function count_active_superadmins(mysqli $db): int {
    $q = $db->query("SELECT COUNT(*) AS c FROM super_admins WHERE active=1");
    $row = $q->fetch_assoc(); return (int)($row['c'] ?? 0);
}
function username_exists(mysqli $db, string $username, ?int $ignoreId=null): bool {
    if ($ignoreId) { $st=$db->prepare("SELECT id FROM super_admins WHERE username=? AND id<>? LIMIT 1"); $st->bind_param('si',$username,$ignoreId);
    } else { $st=$db->prepare("SELECT id FROM super_admins WHERE username=? LIMIT 1"); $st->bind_param('s',$username); }
    $st->execute(); $r=$st->get_result(); return $r->num_rows>0;
}
function email_exists(mysqli $db, string $email, ?int $ignoreId=null): bool {
    if ($ignoreId) { $st=$db->prepare("SELECT id FROM super_admins WHERE email=? AND id<>? LIMIT 1"); $st->bind_param('si',$email,$ignoreId);
    } else { $st=$db->prepare("SELECT id FROM super_admins WHERE email=? LIMIT 1"); $st->bind_param('s',$email); }
    $st->execute(); $r=$st->get_result(); return $r->num_rows>0;
}

/* --------- Handle POST actions --------- */
$selfId = (int)($_SESSION['super_admin_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    csrf_check($_POST['csrf'] ?? null);

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = trim($_POST['role'] ?? 'super_admin');
        $p1       = (string)($_POST['password'] ?? '');
        $p2       = (string)($_POST['password2'] ?? '');

        if ($username===''||$email===''||$p1===''||$p2==='') flash_set('error','All fields are required.');
        elseif (!filter_var($email,FILTER_VALIDATE_EMAIL))    flash_set('error','Invalid email address.');
        elseif ($p1!==$p2)                                    flash_set('error','Passwords do not match.');
        elseif (strlen($p1)<8)                                flash_set('error','Password must be at least 8 characters.');
        elseif (username_exists($db,$username))               flash_set('error','Username already exists.');
        elseif (email_exists($db,$email))                     flash_set('error','Email already exists.');
        else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $st = $db->prepare("INSERT INTO super_admins (username,email,role,password_hash,active) VALUES (?,?,?,?,1)");
            $st->bind_param('ssss',$username,$email,$role,$hash);
            if ($st->execute()) flash_set('success','Super admin created successfully.');
            else                flash_set('error','Failed to create super admin.');
        }
        header('Location: SuperAdminUsers.php'); exit;
    }

    if ($action === 'update_user') {
        $id=(int)($_POST['id']??0); $username=trim($_POST['username']??''); $email=trim($_POST['email']??''); $role=trim($_POST['role']??'super_admin');
        if ($id<=0||$username===''||$email==='')             flash_set('error','Missing required fields.');
        elseif (!filter_var($email,FILTER_VALIDATE_EMAIL))    flash_set('error','Invalid email.');
        elseif (username_exists($db,$username,$id))           flash_set('error','Username already in use.');
        elseif (email_exists($db,$email,$id))                 flash_set('error','Email already in use.');
        else {
            $st=$db->prepare("UPDATE super_admins SET username=?, email=?, role=? WHERE id=?");
            $st->bind_param('sssi',$username,$email,$role,$id);
            if ($st->execute()) {
                if ($id===$selfId) $_SESSION['super_admin_username']=$username;
                flash_set('success','User updated.');
            } else flash_set('error','Update failed.');
        }
        header('Location: SuperAdminUsers.php'); exit;
    }

    if ($action === 'update_password') {
        $id=(int)($_POST['id']??0); $p1=(string)($_POST['password']??''); $p2=(string)($_POST['password2']??'');
        if ($id<=0||$p1===''||$p2==='')                       flash_set('error','Password fields are required.');
        elseif ($p1!==$p2)                                    flash_set('error','Passwords do not match.');
        elseif (strlen($p1)<8)                                flash_set('error','Password must be at least 8 characters.');
        else {
            $hash=password_hash($p1,PASSWORD_DEFAULT);
            $st=$db->prepare("UPDATE super_admins SET password_hash=? WHERE id=?");
            $st->bind_param('si',$hash,$id);
            if ($st->execute()) flash_set('success','Password updated.');
            else                flash_set('error','Password update failed.');
        }
        header('Location: SuperAdminUsers.php'); exit;
    }

    if ($action === 'toggle_active') {
        $id=(int)($_POST['id']??0); $to=(int)($_POST['to']??0);
        if ($id<=0)                                    flash_set('error','Invalid user.');
        elseif ($id===$selfId && $to===0)             flash_set('error','You cannot disable your own account.');
        else {
            if ($to===0) { // disabling → ensure not last active
                $actives=count_active_superadmins($db);
                if ($actives<=1) { flash_set('error','Cannot disable the last active super admin.'); header('Location: SuperAdminUsers.php'); exit; }
            }
            $st=$db->prepare("UPDATE super_admins SET active=? WHERE id=?");
            $st->bind_param('ii',$to,$id);
            if ($st->execute()) flash_set('success', $to? 'User enabled.' : 'User disabled.');
            else                flash_set('error','Operation failed.');
        }
        header('Location: SuperAdminUsers.php'); exit;
    }

    if ($action === 'delete') {
        $id=(int)($_POST['id']??0);
        if ($id<=0)                      flash_set('error','Invalid user.');
        elseif ($id===$selfId)           flash_set('error','You cannot delete your own account.');
        else {
            // check last active protection
            $rs=$db->prepare("SELECT active FROM super_admins WHERE id=?"); $rs->bind_param('i',$id); $rs->execute(); $r=$rs->get_result()->fetch_assoc();
            $tgtActive=(int)($r['active']??0); $actives=count_active_superadmins($db);
            if ($tgtActive===1 && $actives<=1) { flash_set('error','Cannot delete the last active super admin.'); header('Location: SuperAdminUsers.php'); exit; }
            $st=$db->prepare("DELETE FROM super_admins WHERE id=?"); $st->bind_param('i',$id);
            if ($st->execute()) flash_set('success','User deleted.'); else flash_set('error','Delete failed.');
        }
        header('Location: SuperAdminUsers.php'); exit;
    }
}

/* --------- Fetch users (with search) --------- */
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $like = "%$search%";
    $st = $db->prepare("SELECT id, username, email, role, active, created_at, updated_at
                        FROM super_admins
                        WHERE username LIKE ? OR email LIKE ?
                        ORDER BY id DESC");
    $st->bind_param('ss', $like, $like);
    $st->execute();
    $users = $st->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $q = $db->query("SELECT id, username, email, role, active, created_at, updated_at
                     FROM super_admins
                     ORDER BY id DESC");
    $users = $q->fetch_all(MYSQLI_ASSOC);
}
$flash = flash_get();
$csrf  = csrf_token();
$username = $_SESSION['super_admin_username'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Super Admin Users — Password Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { colors: { brand: { DEFAULT:'#2563eb', dark:'#1e40af', light:'#60a5fa' } } } }
    }
  </script>
  <style>
    @keyframes fadeIn { from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:translateY(0)} }
    .animate-fadeIn { animation: fadeIn .4s ease-out both; }
    dialog::backdrop { background: rgba(2,6,23,.6); backdrop-filter: blur(4px); }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-white">

  <!-- Top bar -->
  <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-900/60 backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="SuperAdmin.php" class="px-3 py-1.5 rounded-lg bg-white/10 border border-white/10 hover:bg-white/15">← Back</a>
        <h1 class="text-xl sm:text-2xl font-extrabold">Super Admin Users</h1>
      </div>
      <div class="text-sm text-slate-300">Signed in as <span class="font-semibold text-white"><?= e($username) ?></span></div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <?php if ($flash): ?>
      <div class="animate-fadeIn rounded-xl p-3 border
        <?= $flash['t']==='success' ? 'bg-green-500/15 border-green-400/30 text-green-100' : 'bg-red-500/15 border-red-400/30 text-red-100' ?>">
        <?= e($flash['m']) ?>
      </div>
    <?php endif; ?>

    <!-- Create New -->
    <section class="rounded-2xl bg-white/5 border border-white/10 p-6 shadow-xl">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <h2 class="text-lg sm:text-xl font-bold">Create New Super Admin</h2>
      </div>
      <form method="post" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <div>
          <label class="block text-sm text-slate-300 mb-1">Username</label>
          <input name="username" required class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand" placeholder="superadmin">
        </div>

        <div>
          <label class="block text-sm text-slate-300 mb-1">Email</label>
          <input name="email" type="email" required class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand" placeholder="admin@example.com">
        </div>

        <div>
          <label class="block text-sm text-slate-300 mb-1">Role</label>
          <select name="role" class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand">
            <option value="super_admin">super_admin</option>
          </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-1">
          <label class="block text-sm text-slate-300 mb-1">Password</label>
          <input name="password" type="password" required minlength="8" class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand" placeholder="Min 8 characters">
        </div>
        <div class="sm:col-span-2 lg:col-span-1">
          <label class="block text-sm text-slate-300 mb-1">Confirm Password</label>
          <input name="password2" type="password" required minlength="8" class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand" placeholder="Repeat password">
        </div>

        <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
          <button class="px-5 py-2.5 rounded-xl bg-brand hover:bg-brand/90 shadow font-semibold">Create</button>
        </div>
      </form>
    </section>

    <!-- Search -->
    <section class="rounded-2xl bg-white/5 border border-white/10 p-6 shadow-xl">
      <form class="flex flex-wrap gap-3 items-center" method="get" action="SuperAdminUsers.php">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by username or email" class="flex-1 min-w-[220px] px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand">
        <button class="px-4 py-2 rounded-lg bg-white/10 border border-white/10 hover:bg-white/20">Search</button>
        <a href="SuperAdminUsers.php" class="px-4 py-2 rounded-lg bg-white/10 border border-white/10 hover:bg-white/20">Reset</a>
      </form>
    </section>

    <!-- Users Table -->
    <section class="rounded-2xl bg-white/5 border border-white/10 p-6 shadow-xl overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-slate-300">
            <th class="py-2 pr-4">ID</th>
            <th class="py-2 pr-4">Username</th>
            <th class="py-2 pr-4">Email</th>
            <th class="py-2 pr-4">Role</th>
            <th class="py-2 pr-4">Active</th>
            <th class="py-2 pr-4">Created</th>
            <th class="py-2 pr-4">Updated</th>
            <th class="py-2 pr-4">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/10">
          <?php if (empty($users)): ?>
            <tr><td colspan="8" class="py-6 text-center text-slate-400">No admins found.</td></tr>
          <?php else: foreach ($users as $u): ?>
            <tr>
              <td class="py-3 pr-4"><?= (int)$u['id'] ?></td>
              <td class="py-3 pr-4 font-medium"><?= e($u['username']) ?></td>
              <td class="py-3 pr-4"><?= e($u['email']) ?></td>
              <td class="py-3 pr-4"><?= e($u['role']) ?></td>
              <td class="py-3 pr-4">
                <?php if ((int)$u['active'] === 1): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-500/20 border border-green-400/30 text-green-100">Active</span>
                <?php else: ?>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-500/20 border border-red-400/30 text-red-100">Disabled</span>
                <?php endif; ?>
              </td>
              <td class="py-3 pr-4 whitespace-nowrap"><?= e($u['created_at'] ?? '-') ?></td>
              <td class="py-3 pr-4 whitespace-nowrap"><?= e($u['updated_at'] ?? '-') ?></td>
              <td class="py-3 pr-4">
                <div class="flex flex-wrap gap-2">
                  <!-- Edit (modal) -->
                  <button type="button"
                          class="px-3 py-1.5 rounded bg-white/10 border border-white/10 hover:bg-white/20"
                          onclick="openEdit(<?= (int)$u['id'] ?>,'<?= e($u['username']) ?>','<?= e($u['email']) ?>','<?= e($u['role']) ?>')">
                    Edit
                  </button>

                  <!-- Toggle active -->
                  <form method="post" class="inline">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="to" value="<?= (int)$u['active']===1 ? 0 : 1 ?>">
                    <?php if ((int)$u['active']===1): ?>
                      <button class="px-3 py-1.5 rounded bg-yellow-500/20 border border-yellow-400/30 text-yellow-100 hover:bg-yellow-500/30">Disable</button>
                    <?php else: ?>
                      <button class="px-3 py-1.5 rounded bg-green-500/20 border border-green-400/30 text-green-100 hover:bg-green-500/30">Enable</button>
                    <?php endif; ?>
                  </form>

                  <!-- Update password (modal) -->
                  <button type="button"
                          class="px-3 py-1.5 rounded bg-brand border border-brand/50 hover:bg-brand/90"
                          onclick="openPwd(<?= (int)$u['id'] ?>,'<?= e($u['username']) ?>')">
                    Password
                  </button>

                  <!-- Delete -->
                  <form method="post" class="inline" onsubmit="return confirm('Delete this admin? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="px-3 py-1.5 rounded bg-red-500/20 border border-red-400/30 text-red-100 hover:bg-red-500/30">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Edit Modal -->
  <dialog id="editModal" class="rounded-2xl p-0 w-full max-w-lg bg-slate-900 border border-white/10">
    <form method="post" class="p-6 space-y-4">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" id="edit_id" name="id" value="0">

      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold">Edit User</h3>
        <button type="button" onclick="closeEdit()" class="px-2 py-1 rounded bg-white/10 hover:bg-white/20">✕</button>
      </div>

      <div>
        <label class="block text-sm text-slate-300 mb-1">Username</label>
        <input id="edit_username" name="username" required class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand">
      </div>

      <div>
        <label class="block text-sm text-slate-300 mb-1">Email</label>
        <input id="edit_email" name="email" type="email" required class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand">
      </div>

      <div>
        <label class="block text-sm text-slate-300 mb-1">Role</label>
        <select id="edit_role" name="role" class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand">
          <option value="super_admin">super_admin</option>
        </select>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeEdit()" class="px-4 py-2 rounded-lg bg-white/10 border border-white/10 hover:bg-white/20">Cancel</button>
        <button class="px-5 py-2 rounded-lg bg-brand hover:bg-brand/90 font-semibold">Save</button>
      </div>
    </form>
  </dialog>

  <!-- Password Modal -->
  <dialog id="pwdModal" class="rounded-2xl p-0 w-full max-w-md bg-slate-900 border border-white/10">
    <form method="post" class="p-6 space-y-4">
      <input type="hidden" name="action" value="update_password">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" id="pwd_id" name="id" value="0">

      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold">Update Password</h3>
        <button type="button" onclick="closePwd()" class="px-2 py-1 rounded bg-white/10 hover:bg-white/20">✕</button>
      </div>
      <div class="text-slate-300 text-sm" id="pwd_user"></div>

      <div>
        <label class="block text-sm text-slate-300 mb-1">New Password</label>
        <input name="password" type="password" required minlength="8" class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand">
      </div>
      <div>
        <label class="block text-sm text-slate-300 mb-1">Confirm Password</label>
        <input name="password2" type="password" required minlength="8" class="w-full px-3 py-2 rounded-lg bg-white/10 border border-white/10 focus:ring-2 focus:ring-brand">
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closePwd()" class="px-4 py-2 rounded-lg bg-white/10 border border-white/10 hover:bg-white/20">Cancel</button>
        <button class="px-5 py-2 rounded-lg bg-brand hover:bg-brand/90 font-semibold">Update</button>
      </div>
    </form>
  </dialog>

  <script>
    const dlgPwd  = document.getElementById('pwdModal');
    const dlgEdit = document.getElementById('editModal');

    function openPwd(id, user){
      document.getElementById('pwd_id').value = id;
      document.getElementById('pwd_user').textContent = 'User: ' + user;
      dlgPwd.showModal();
    }
    function closePwd(){ dlgPwd.close(); }

    function openEdit(id, username, email, role){
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_username').value = username;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_role').value = role || 'super_admin';
      dlgEdit.showModal();
    }
    function closeEdit(){ dlgEdit.close(); }
  </script>
</body>
</html>
