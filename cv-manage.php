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
 * MSJOBS — Manage CVs (cv_candidates)
 ************************************************************/
declare(strict_types=1);
session_start();

ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log', __DIR__.'/cv_manage_error.log');
error_reporting(E_ALL);

// const DB_HOST = '127.0.0.1:3306'; (Refactored to config.php)
// const DB_NAME = 'u903588615_exaple'; (Refactored to config.php)
// const DB_USER = 'u903588615_root'; (Refactored to config.php)
// const DB_PASS = 'Msjobs#1'; (Refactored to config.php)

const UPLOAD_PUBLIC_PREFIX = 'uploads/resumes';
const UPLOAD_DIR = __DIR__ . '/uploads/resumes';

function db(): PDO {
  static $pdo=null; if ($pdo) return $pdo;
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function verify_csrf(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/* Delete */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if (!verify_csrf($_POST['csrf'] ?? '')) { http_response_code(400); die('Bad CSRF'); }
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { http_response_code(400); die('Bad id'); }

  $pdo = db();
  $stmt = $pdo->prepare("SELECT resume_stored FROM cv_candidates WHERE id=?");
  $stmt->execute([$id]);
  if ($row = $stmt->fetch()) {
    $pdo->prepare("DELETE FROM cv_candidates WHERE id=?")->execute([$id]);
    if (!empty($row['resume_stored'])) {
      $path = UPLOAD_DIR . '/' . $row['resume_stored'];
      if (is_file($path)) @unlink($path);
    }
  }
  header('Location: cv-manage.php?msg=deleted');
  exit;
}

/* Search + paginate */
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 12; $off = ($page-1)*$per;

$pdo = db();
$where = ''; $args = [];
if ($q !== '') { $where = "WHERE name LIKE ? OR email LIKE ? OR national_id LIKE ? OR contact_number LIKE ?"; $like='%'.$q.'%'; $args=[$like,$like,$like,$like]; }

$stmtCount = $pdo->prepare("SELECT COUNT(*) AS c FROM cv_candidates $where");
$stmtCount->execute($args);
$total = (int)$stmtCount->fetch()['c'];

$stmt = $pdo->prepare("SELECT id,name,email,national_id,contact_number,resume_original,resume_stored,resume_mime,resume_size_bytes,created_at
                       FROM cv_candidates $where ORDER BY created_at DESC LIMIT $per OFFSET $off");
$stmt->execute($args);
$rows = $stmt->fetchAll();

$pages = max(1, (int)ceil($total/$per));
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage CVs | MSJOBS</title>
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{brand:'#0F73EE',brandDark:'#0b58b6'}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen">
  <header class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <a href="cv-upload.php" class="text-sm text-brand underline">+ Upload new CV</a>
      <form class="flex gap-2 items-center" method="get">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, email, national ID, mobile" class="rounded-lg border-slate-300 focus:ring-brand focus:border-brand" />
        <button class="px-4 py-2 rounded-lg bg-brand text-white">Search</button>
        <?php if ($q !== ''): ?><a href="cv-manage.php" class="px-4 py-2 rounded-lg border">Clear</a><?php endif; ?>
      </form>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?php if (!empty($_GET['msg']) && $_GET['msg']==='deleted'): ?>
      <div class="mb-4 rounded-lg border bg-green-50 border-green-200 p-3 text-green-700">Deleted successfully.</div>
    <?php endif; ?>

    <div class="overflow-x-auto bg-white border rounded-xl">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-slate-700">
          <tr>
            <th class="p-3 text-left">ID</th>
            <th class="p-3 text-left">Name</th>
            <th class="p-3 text-left">Email</th>
            <th class="p-3 text-left">National ID</th>
            <th class="p-3 text-left">Contact</th>
            <th class="p-3 text-left">File</th>
            <th class="p-3 text-left">Size</th>
            <th class="p-3 text-left">Created</th>
            <th class="p-3 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td class="p-4 text-center text-slate-500" colspan="9">No records found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr class="border-t">
              <td class="p-3"><?= (int)$r['id'] ?></td>
              <td class="p-3"><?= e($r['name'] ?? '') ?></td>
              <td class="p-3"><?= e($r['email'] ?? '') ?></td>
              <td class="p-3"><?= e($r['national_id'] ?? '') ?></td>
              <td class="p-3"><?= e($r['contact_number'] ?? '') ?></td>
              <td class="p-3">
                <?php if (!empty($r['resume_stored'])): ?>
                  <a class="text-brand underline" target="_blank" href="<?= e(UPLOAD_PUBLIC_PREFIX . '/' . $r['resume_stored']) ?>"><?= e($r['resume_original'] ?? 'download') ?></a>
                <?php else: ?>
                  <span class="text-slate-400">—</span>
                <?php endif; ?>
              </td>
              <td class="p-3"><?= $r['resume_size_bytes'] ? number_format((int)$r['resume_size_bytes']/1024,1).' KB' : '—' ?></td>
              <td class="p-3"><?= e($r['created_at']) ?></td>
              <td class="p-3">
                <form method="post" onsubmit="return confirm('Delete this record?');" class="inline">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>" />
                  <button class="px-3 py-1.5 rounded bg-red-600 text-white">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="mt-4 flex items-center gap-2">
        <?php for ($i=1;$i<=$pages;$i++): 
          $u = 'cv-manage.php?page='.$i.($q!==''?('&q='.urlencode($q)):''); ?>
          <a href="<?= e($u) ?>" class="px-3 py-1.5 rounded border <?= $i===$page?'bg-brand text-white border-brand':'bg-white' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
