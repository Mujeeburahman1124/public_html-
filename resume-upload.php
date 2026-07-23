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

/************************************************************
 * MSJOBS — CV Center (ONE FILE)
 * - Auto-creates table: cv_candidates (utf8mb4_uca1400_ai_ci)
 * - Auto-creates folder: uploads/cv + .htaccess hardening
 * - Uploader (AJAX JSON): name, email, national_id, contact_number, resume
 * - Manager: search, paginate, download, delete
 ************************************************************/
session_start();

/* ================== CONFIG (edit as needed) ================== */
// const DB_HOST = '127.0.0.1:3306'; (Refactored to config.php)
// const DB_NAME = 'u903588615_exaple'; (Refactored to config.php)
// const DB_USER = 'u903588615_root'; (Refactored to config.php)
// const DB_PASS = 'Msjobs#1'; (Refactored to config.php)

/* Folder = uploads/cv (as you requested: "cv in uploads") */
const UPLOAD_DIR           = __DIR__ . '/uploads/cv';
const UPLOAD_PUBLIC_PREFIX = 'uploads/cv';
const MAX_BYTES            = 5 * 1024 * 1024; // 5 MB
$ALLOWED_EXT  = ['pdf','doc','docx','jpg','jpeg','png'];
$ALLOWED_MIME = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'image/jpeg',
  'image/png',
];

/* ================== HARDENED ERROR HANDLERS ================== */
ini_set('display_errors','0');           // keep JSON clean
ini_set('log_errors','1');
ini_set('error_log', __DIR__.'/cv_center_error.log');
error_reporting(E_ALL);

function clean_output(): void { while (ob_get_level() > 0) { @ob_end_clean(); } }
function json_out(array $p, int $code=200): never {
  clean_output();
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($p, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  exit;
}

/* ================== DB & SCHEMA ================== */
function db(): PDO {
  static $pdo=null; if ($pdo) return $pdo;
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function upsert_table(): void {
  // Matches your phpMyAdmin screenshot (collation + columns + indexes)
  $sql = "CREATE TABLE IF NOT EXISTS cv_candidates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) COLLATE utf8mb4_uca1400_ai_ci NOT NULL,
    email VARCHAR(190) COLLATE utf8mb4_uca1400_ai_ci NULL,
    national_id VARCHAR(64) COLLATE utf8mb4_uca1400_ai_ci NULL,
    contact_number VARCHAR(64) COLLATE utf8mb4_uca1400_ai_ci NULL,
    resume_original VARCHAR(255) COLLATE utf8mb4_uca1400_ai_ci NULL,
    resume_stored   VARCHAR(255) COLLATE utf8mb4_uca1400_ai_ci NULL,
    resume_mime     VARCHAR(190) COLLATE utf8mb4_uca1400_ai_ci NULL,
    resume_size_bytes BIGINT(20) UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (national_id),
    INDEX (contact_number)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci";
  db()->exec($sql);
}

/* ================== FOLDER + .htaccess ================== */
function ensureUploadsDir(): void {
  if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }
  @chmod(UPLOAD_DIR, 0775);
  $hta = UPLOAD_DIR.'/.htaccess';
  if (!file_exists($hta)) {
    @file_put_contents($hta, <<<HTA
Options -ExecCGI
php_flag engine off
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8
<FilesMatch "\\.(php|phtml|php\\d*)$">
  Require all denied
</FilesMatch>
HTA);
  }
}

/* ================== CSRF ================== */
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function verify_csrf(string $t): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/* ================== UTILS ================== */
function slugify(string $s): string {
  $s = preg_replace('~[\p{Z}\s]+~u','-',$s);
  $s = preg_replace('~[^\pL\pN\-\.]+~u','',$s);
  return strtolower(trim($s,'-') ?: 'file');
}
function detect_mime(string $tmp): string {
  if (class_exists('finfo')) { $fi=new finfo(FILEINFO_MIME_TYPE); $m=$fi->file($tmp); if ($m) return $m; }
  if (function_exists('mime_content_type')) { $m=@mime_content_type($tmp); if ($m) return $m; }
  return 'application/octet-stream';
}

/* ================== ROUTING ================== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_REQUEST['action'] ?? '';         // for both GET and POST
$view   = $_GET['view']    ?? 'upload';      // default UI: upload

/* Force-JSON for AJAX upload */
$isAjaxUpload = ($method === 'POST' && $action === 'upload');
if ($isAjaxUpload) {
  set_error_handler(function($sev,$msg,$file,$line){
    if (!(error_reporting() & $sev)) return false;
    throw new ErrorException($msg, 0, $sev, $file, $line);
  });
  set_exception_handler(function(Throwable $e){
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok'=>false,'error'=>'Server error during upload.']);
    error_log('AJAX upload exception: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
    exit;
  });
  register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
      while (ob_get_level() > 0) { @ob_end_clean(); }
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
      header('Cache-Control: no-store');
      echo json_encode(['ok'=>false,'error'=>'Server error during upload.']);
      error_log('AJAX upload fatal: '.$err['message'].' @ '.$err['file'].':'.$err['line']);
      exit;
    }
  });
}

/* Ensure infra exists on every request */
ensureUploadsDir();
upsert_table();

/* ================== ACTION: UPLOAD (AJAX JSON) ================== */
if ($isAjaxUpload) {
  try {
    if (!verify_csrf($_POST['csrf'] ?? '')) json_out(['ok'=>false,'error'=>'Invalid session. Refresh and try again.'], 400);

    $name   = trim((string)($_POST['name'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $nid    = trim((string)($_POST['national_id'] ?? ''));
    $mobile = trim((string)($_POST['contact_number'] ?? ''));

    if ($name === '') json_out(['ok'=>false,'error'=>'Name is required.'], 400);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok'=>false,'error'=>'Invalid email.'], 400);

    if (empty($_FILES['resume']) || !isset($_FILES['resume']['error'])) json_out(['ok'=>false,'error'=>'No file received.'], 400);
    $f = $_FILES['resume'];
    if ($f['error'] !== UPLOAD_ERR_OK) json_out(['ok'=>false,'error'=>'Upload error code: '.$f['error']], 400);
    if ($f['size'] <= 0) json_out(['ok'=>false,'error'=>'Empty file.'], 400);
    if ($f['size'] > MAX_BYTES) json_out(['ok'=>false,'error'=>'File too large (max 5 MB).'], 400);

    $orig = $f['name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    global $ALLOWED_EXT, $ALLOWED_MIME;
    if (!in_array($ext, $ALLOWED_EXT, true)) json_out(['ok'=>false,'error'=>'Unsupported type (PDF/DOC/DOCX/JPG/PNG).'], 400);

    $mime = detect_mime($f['tmp_name']);
    if (!in_array($mime, $ALLOWED_MIME, true)) json_out(['ok'=>false,'error'=>'Invalid file content (MIME mismatch).'], 400);

    if (!is_uploaded_file($f['tmp_name'])) json_out(['ok'=>false,'error'=>'Security check failed.'], 400);

    $base   = slugify(pathinfo($orig, PATHINFO_FILENAME));
    $stored = uniqid($base.'_', true).'.'.$ext;
    $dest   = UPLOAD_DIR.'/'.$stored;
    if (!@move_uploaded_file($f['tmp_name'], $dest)) {
      json_out(['ok'=>false,'error'=>'Failed to save file. Check permissions on '.UPLOAD_PUBLIC_PREFIX], 500);
    }

    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO cv_candidates
      (name,email,national_id,contact_number,resume_original,resume_stored,resume_mime,resume_size_bytes)
      VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$name,$email ?: null,$nid ?: null,$mobile ?: null,$orig,$stored,$mime,(int)$f['size']]);
    $id = (int)$pdo->lastInsertId();

    json_out([
      'ok' => true,
      'message' => 'CV uploaded successfully',
      'candidate_id' => $id,
      'file_url' => UPLOAD_PUBLIC_PREFIX.'/'.rawurlencode($stored),
      'original_name' => $orig
    ]);
  } catch (Throwable $e) {
    error_log('CV upload error: '.$e->getMessage());
    json_out(['ok'=>false,'error'=>'Server error during upload.'], 500);
  }
}

/* ================== ACTION: DELETE ================== */
if ($method === 'POST' && $action === 'delete') {
  if (!verify_csrf($_POST['csrf'] ?? '')) { http_response_code(400); die('Bad CSRF'); }
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { http_response_code(400); die('Bad id'); }
  $pdo = db();
  $s = $pdo->prepare("SELECT resume_stored FROM cv_candidates WHERE id=?");
  $s->execute([$id]);
  if ($row = $s->fetch()) {
    $pdo->prepare("DELETE FROM cv_candidates WHERE id=?")->execute([$id]);
    $stored = $row['resume_stored'] ?? '';
    if ($stored) {
      $p = UPLOAD_DIR.'/'.$stored;
      if (is_file($p)) @unlink($p);
    }
  }
  header('Location: '.basename(__FILE__).'?view=manage&msg=deleted');
  exit;
}

/* ================== VIEWS ================== */
$csrf = csrf_token();

function page_head(string $title='CV Center'): void {
  ?><!DOCTYPE html>
  <html lang="en"><head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= e($title) ?> | MSJOBS</title>
    <link rel="icon" type="image/png" href="img/MS copy.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{brand:'#0F73EE',brandDark:'#0b58b6'}}}}</script>
  </head><body class="bg-slate-50 min-h-screen"><?php
}
function page_nav(): void {
  $self = basename(__FILE__);
  ?><header class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a class="text-sm text-brand underline" href="<?= e($self) ?>">Upload CV</a>
        <a class="text-sm text-slate-700 hover:text-brand underline" href="<?= e($self) ?>?view=manage">Manage CVs</a>
      </div>
    </div>
  </header><?php
}
function page_foot(): void { echo "</body></html>"; }

/* ----------- UPLOAD PAGE ----------- */
if ($view === 'upload') {
  page_head('Upload CV'); page_nav(); ?>
  <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-xl border p-6 sm:p-8 shadow">
      <h1 class="text-2xl font-bold text-slate-800 mb-4">Upload Candidate CV</h1>
      <form id="uploader" action="<?= e(basename(__FILE__)) ?>" method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1">Full Name <span class="text-red-500">*</span></label>
            <input name="name" type="text" required class="w-full rounded-lg border-slate-300 focus:ring-brand focus:border-brand" />
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1">Email</label>
            <input name="email" type="email" class="w-full rounded-lg border-slate-300 focus:ring-brand focus:border-brand" />
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1">National ID</label>
            <input name="national_id" type="text" class="w-full rounded-lg border-slate-300 focus:ring-brand focus:border-brand" />
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1">Contact Number</label>
            <input name="contact_number" type="text" class="w-full rounded-lg border-slate-300 focus:ring-brand focus:border-brand" />
          </div>
        </div>

        <label class="mt-2 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-slate-300 rounded-xl py-10 px-4 cursor-pointer hover:border-brand transition" id="dropzone">
          <svg class="w-7 h-7 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div class="text-center">
            <span class="text-slate-800 font-medium">Drag & drop CV here</span>
            <span class="block text-slate-500 text-sm">or click to browse (PDF, DOC, DOCX, JPG, PNG · max 5 MB)</span>
          </div>
          <input id="file" name="resume" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="hidden" />
        </label>

        <div id="selected" class="hidden flex items-center justify-between rounded-lg bg-slate-50 border px-3 py-2">
          <div class="flex items-center gap-2 min-w-0">
            <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
            <span id="filename" class="truncate text-sm text-slate-700"></span>
          </div>
          <button type="button" id="clearBtn" class="text-slate-500 hover:text-slate-700 text-sm">Remove</button>
        </div>

        <div id="progressWrap" class="hidden">
          <div class="h-2 w-full bg-slate-200 rounded-full overflow-hidden">
            <div id="progressBar" class="h-full w-0 bg-brand transition-[width] duration-200"></div>
          </div>
          <div id="progressText" class="mt-2 text-xs text-slate-600">0%</div>
        </div>

        <div class="flex items-center gap-3">
          <button id="uploadBtn" type="submit" class="inline-flex items-center gap-2 bg-brand hover:bg-brandDark text-white font-medium px-4 py-2.5 rounded-lg">
            <span>Upload CV</span>
          </button>
          <a class="text-sm text-slate-600 underline" href="<?= e(basename(__FILE__)) ?>?view=manage">Manage CVs</a>
        </div>

        <div id="success" class="hidden rounded-lg border bg-green-50 border-green-200 p-4 mt-4">
          <div class="flex items-center gap-2 text-green-700"><strong>Uploaded!</strong></div>
          <p id="successMsg" class="mt-1 text-sm text-green-700"></p>
          <a id="viewLink" href="#" target="_blank" class="mt-2 inline-flex items-center gap-2 text-sm text-green-800 underline">View file</a>
        </div>

        <div id="error" class="hidden rounded-lg border bg-red-50 border-red-200 p-4 mt-4">
          <div class="flex items-center gap-2 text-red-700"><strong>Upload failed</strong></div>
          <p id="errorMsg" class="mt-1 text-sm text-red-700"></p>
        </div>
      </form>
    </div>
    <div class="mt-6 text-center text-xs text-slate-500">Tip: Use a clear filename (e.g., <em>Vidhu_JAVA_Developer_2025.pdf</em>).</div>
  </main>

  <script>
  const dz = document.getElementById('dropzone');
  const fileInput = document.getElementById('file');
  const form = document.getElementById('uploader');
  const filenameEl = document.getElementById('filename');
  const selectedWrap = document.getElementById('selected');
  const clearBtn = document.getElementById('clearBtn');
  const progressWrap = document.getElementById('progressWrap');
  const progressBar = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  const successBox = document.getElementById('success');
  const successMsg = document.getElementById('successMsg');
  const viewLink = document.getElementById('viewLink');
  const errorBox = document.getElementById('error');
  const errorMsg = document.getElementById('errorMsg');

  ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dz.classList.add('ring-2','ring-brand'); }));
  ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dz.classList.remove('ring-2','ring-brand'); }));
  dz.addEventListener('drop', e => { const f = e.dataTransfer.files?.[0]; if (f) setFile(f); });
  dz.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });
  clearBtn.addEventListener('click', () => { fileInput.value=''; selectedWrap.classList.add('hidden'); filenameEl.textContent=''; });

  function setFile(f){ selectedWrap.classList.remove('hidden'); filenameEl.textContent = f.name; hide(successBox); hide(errorBox); }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (!fileInput.files[0]) { showError('Please choose a file.'); return; }
    progressWrap.classList.remove('hidden'); progressBar.style.width='0%'; progressText.textContent='0%';

    const data = new FormData(form);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.responseType = 'json';
    xhr.setRequestHeader('Accept','application/json');

    xhr.upload.onprogress = function(e){
      if (e.lengthComputable) {
        const p = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = p + '%';
        progressText.textContent = p + '%';
      }
    };

    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4) {
        const ct = xhr.getResponseHeader('Content-Type') || '';
        const isJSON = ct.indexOf('application/json') !== -1;
        const res = isJSON ? (xhr.response || {}) : null;

        if (isJSON && xhr.status === 200 && res.ok) {
          showSuccess(res.message + ' (' + (res.original_name || 'file') + ')', res.file_url);
        } else if (isJSON) {
          showError(res.error || ('Upload failed (' + xhr.status + ')'));
        } else {
          const raw = (xhr.responseText || '').slice(0, 200).replace(/\s+/g,' ').trim();
          showError('Unexpected server response: ' + raw);
        }
      }
    };
    xhr.onerror = function(){ showError('Network error. Please try again.'); };
    xhr.send(data);
  });

  function showError(msg){ errorBox.classList.remove('hidden'); errorMsg.textContent = msg; hide(successBox); }
  function showSuccess(msg, url){ successBox.classList.remove('hidden'); successMsg.textContent = msg; viewLink.href = url; hide(errorBox); }
  function hide(el){ el.classList.add('hidden'); }
  </script>
  <?php page_foot(); exit;
}

/* ----------- MANAGE PAGE ----------- */
page_head('Manage CVs'); page_nav();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 12; $off = ($page-1)*$per;

$where = ''; $args=[];
if ($q !== '') {
  $where = "WHERE name LIKE ? OR email LIKE ? OR national_id LIKE ? OR contact_number LIKE ?";
  $like='%'.$q.'%'; $args=[$like,$like,$like,$like];
}
$pdo = db();
$sc = $pdo->prepare("SELECT COUNT(*) AS c FROM cv_candidates $where");
$sc->execute($args); $total = (int)$sc->fetch()['c'];
$pages = max(1, (int)ceil($total/$per));

$sql = "SELECT id,name,email,national_id,contact_number,resume_original,resume_stored,resume_mime,resume_size_bytes,created_at
        FROM cv_candidates $where
        ORDER BY created_at DESC
        LIMIT $per OFFSET $off";
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
$self = basename(__FILE__);
?>
<main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <?php if (!empty($_GET['msg']) && $_GET['msg']==='deleted'): ?>
    <div class="mb-4 rounded-lg border bg-green-50 border-green-200 p-3 text-green-700">Deleted successfully.</div>
  <?php endif; ?>

  <form class="mb-5 flex gap-2 items-center" method="get" action="<?= e($self) ?>">
    <input type="hidden" name="view" value="manage">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, email, national ID, mobile" class="flex-1 rounded-lg border-slate-300 focus:ring-brand focus:border-brand" />
    <button class="px-4 py-2 rounded-lg bg-brand text-white">Search</button>
    <?php if ($q !== ''): ?><a href="<?= e($self) ?>?view=manage" class="px-4 py-2 rounded-lg border">Clear</a><?php endif; ?>
  </form>

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
              <a class="text-brand underline" target="_blank" href="<?= e(UPLOAD_PUBLIC_PREFIX.'/'.$r['resume_stored']) ?>"><?= e($r['resume_original'] ?? 'download') ?></a>
            <?php else: ?>
              <span class="text-slate-400">—</span>
            <?php endif; ?>
          </td>
          <td class="p-3"><?= $r['resume_size_bytes'] ? number_format((int)$r['resume_size_bytes']/1024,1).' KB' : '—' ?></td>
          <td class="p-3"><?= e($r['created_at']) ?></td>
          <td class="p-3">
            <form method="post" onsubmit="return confirm('Delete this record?');" class="inline" action="<?= e($self) ?>?view=manage">
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
      <?php for ($i=1; $i<=$pages; $i++):
        $u = $self.'?view=manage&page='.$i.($q!==''?('&q='.urlencode($q)):''); ?>
        <a href="<?= e($u) ?>" class="px-3 py-1.5 rounded border <?= $i===$page?'bg-brand text-white border-brand':'bg-white' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</main>
<?php page_foot();
