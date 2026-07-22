<?php
/***********************************************
 * MS JOBS — Manage Jobs (Profile-Card UI)
 * - Uses existing jobs.currency column
 * - Currency dropdown populated from DB (DISTINCT)
 * - Inline edit, delete with prepared statements
 ***********************************************/
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employer') {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$categories = [
  "Cleaning & Hospitality","Engineering & Contractions","Maintenance","Manufacturing",
  "Hotels & Restaurants","Transportation","Delivery Service","Helpers",
  "Accounting & Finance","Auto Mobile","Beauty/Salon","Customer Service / Call Center",
  "Data Management & Analyst","Graphic Designer","Admin & HR","Sales / Business Development",
  "Secretarial / Front Office","Security Guard","Sports & Fitness","Travel & Tourism",
  "Medical & Health Care","Media, Art & Entertainment","Marketing & Advertising",
  "Marine Captain / Crew","Logistics & Distribution","Legal Services","Education","Drivers","Other"
];

/* ---------- Helpers ---------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function initials($str){
  $parts = preg_split('/\s+/u', trim((string)$str));
  $first = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1));
  $second = mb_strtoupper(mb_substr($parts[1] ?? '', 0, 1));
  return $first.$second;
}
function money_range($code, $min, $max){
  $code = trim((string)$code);
  $min  = is_null($min) ? 0 : (float)$min;
  $max  = is_null($max) ? 0 : (float)$max;
  if ($code !== '') {
    return $code.' '.number_format($min, 2).' - '.$code.' '.number_format($max, 2);
  }
  return number_format($min, 2).' - '.number_format($max, 2);
}

/* ---------- Fetch distinct currencies from DB ---------- */
$currencyOptions = [];
try {
  $rs = $conn->query("SELECT DISTINCT currency FROM jobs WHERE currency IS NOT NULL AND currency <> '' ORDER BY currency ASC");
  while ($r = $rs->fetch_assoc()) { $currencyOptions[] = $r['currency']; }
} catch (Throwable $t) {
  // ignore; fallback list below
}
if (empty($currencyOptions)) {
  // fallback list (only used if DB has no values yet)
  $currencyOptions = ['USD','AED','QAR','EUR','SAR','OMR','KWD','BHD','LKR'];
}

/* ---------- Handle Delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job_id'])) {
  $del_id = (int)$_POST['delete_job_id'];
  $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ? AND company_id = ?");
  $stmt->bind_param("ii", $del_id, $user_id);
  $stmt->execute();
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

/* ---------- Handle Edit/Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_job_id'])) {
  $edit_id    = (int)$_POST['edit_job_id'];
  $title      = $_POST['title'] ?? '';
  $category   = $_POST['category'] ?? '';
  $type       = $_POST['type'] ?? '';
  $location   = $_POST['location'] ?? '';
  $min_salary = (float)($_POST['min_salary'] ?? 0);
  $max_salary = (float)($_POST['max_salary'] ?? 0);
  $currency   = $_POST['currency'] ?? '';
  $description= $_POST['description'] ?? '';

  $stmt = $conn->prepare("
    UPDATE jobs
       SET title=?, category=?, type=?, location=?, min_salary=?, max_salary=?, currency=?, description=?
     WHERE id=? AND company_id = ?
  ");
  $stmt->bind_param(
    "ssssddssii",
    $title, $category, $type, $location, $min_salary, $max_salary, $currency, $description, $edit_id, $user_id
  );
  $stmt->execute();

  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

/* ---------- Filter & Fetch List ---------- */
$category_filter = $_GET['category'] ?? '';
$edit_job_id     = $_GET['edit'] ?? null;

$sql = "SELECT * FROM jobs WHERE company_id = ?";
$params = [$user_id];
$types  = 'i';

if ($category_filter !== '') {
  $sql .= " AND category = ?";
  $params[] = $category_filter;
  $types .= 's';
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>MS JOBS - Manage Jobs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <style>
    :root {
      --brand:#6b46c1;
      --brand-2:#7c3aed;
    }
    body { font-family: 'Poppins', sans-serif; }
    .glass { background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.86)); backdrop-filter: blur(8px); }
    .ring-gradient { background: linear-gradient(135deg, var(--brand), #22c55e); padding: 2px; border-radius: 9999px; }
    .truncate-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .shadow-premium { box-shadow: 0 10px 20px rgba(17,24,39,.08), 0 6px 6px rgba(17,24,39,.04); }
    .pill { border-radius: 9999px; }
  </style>
</head>
<body class="min-h-screen bg-[conic-gradient(at_top_right,_#faf5ff,_#eef2ff,_#f8fafc)] text-slate-800">

  <!-- Navbar -->
  <nav class="bg-violet-600 py-4 px-4 md:px-6 shadow-sm sticky top-0 z-30">
    <div class="max-w-7xl mx-auto flex justify-between items-center text-white">
      <div class="flex items-center gap-3">
        <img class="h-10 w-10 rounded-full border-2 border-white/70" src="img/1748025713_MS copy.png" alt="Logo">
        <div class="leading-tight">
          <div class="text-lg font-semibold tracking-wide">MS JOBS COMPANY DASHBOARD</div>
          <div class="text-[11px] opacity-90">Manage jobs & applications</div>
        </div>
      </div>

      <div class="hidden sm:flex items-center gap-6 text-sm font-medium">
        <a href="company.php" class="hover:text-white/90"><i class="fa-solid fa-house mr-2"></i>Home</a>
        <a href="admin-add-job.php" class="hover:text-white/90"><i class="fa-solid fa-briefcase mr-2"></i>Post Job</a>
        <a href="manage_jobs.php" class="hover:text-white/90 underline decoration-2 underline-offset-4"><i class="fa-solid fa-list-check mr-2"></i>Manage Jobs</a>
        <a href="edit-employer-profile.php" class="hover:text-white/90"><i class="fa-solid fa-user-gear mr-2"></i>Profile Setting</a>
        <a href="view-applications.php" class="hover:text-white/90"><i class="fa-solid fa-inbox mr-2"></i>View Applications</a>
        <a href="login.php" class="hover:text-white/90"><i class="fa-solid fa-right-from-bracket mr-2"></i>Logout</a>
      </div>

      <button id="menu-toggle" class="sm:hidden text-white text-2xl" aria-label="Menu">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </nav>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="sm:hidden hidden bg-white text-blue-800 px-6 py-4 shadow space-y-2 border-b sticky top-[68px] z-30">
    <a href="company.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-house mr-2"></i>Home</a>
    <a href="admin-add-job.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-briefcase mr-2"></i>Post Job</a>
    <a href="manage_jobs.php" class="block py-2 text-violet-700 font-bold"><i class="fa-solid fa-list-check mr-2"></i>Manage Jobs</a>
    <a href="edit-employer-profile.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-user-gear mr-2"></i>Profile Setting</a>
    <a href="view-applications.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-inbox mr-2"></i>View Applications</a>
    <a href="login.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-right-from-bracket mr-2"></i>Logout</a>
  </div>

  <script>
    document.getElementById('menu-toggle')?.addEventListener('click', function () {
      document.getElementById('mobile-menu')?.classList.toggle('hidden');
    });
  </script>

  <!-- Header -->
  <header class="relative">
    <div class="absolute inset-0 pointer-events-none opacity-30"
         style="background: radial-gradient(800px 400px at 20% -10%, rgba(124,58,237,.15), transparent),
                          radial-gradient(800px 400px at 80% -10%, rgba(99,102,241,.15), transparent);"></div>
    <div class="max-w-7xl mx-auto px-6 pt-10 pb-6 relative">
      <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight">Manage Job Vacancies</h1>
      <p class="mt-2 text-slate-600">Filter, edit, or remove your postings in a clean, profile-card layout.</p>
    </div>
  </header>

  <!-- Filter -->
  <div class="max-w-7xl mx-auto px-6">
    <form method="GET" class="glass shadow-premium rounded-2xl border border-violet-100 p-4 sm:p-5 mb-8">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
        <div class="flex-1">
          <label class="text-xs font-semibold text-violet-700 block mb-1">Category</label>
          <div class="relative">
            <select name="category" class="w-full appearance-none bg-white/80 border border-violet-200 rounded-xl px-4 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= ($cat === $category_filter ? 'selected' : '') ?>><?= e($cat) ?></option>
              <?php endforeach; ?>
            </select>
            <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
            </svg>
          </div>
        </div>

        <button type="submit"
          class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-premium">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M3 6h18M6 12h12M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Apply Filter
        </button>
      </div>
    </form>
  </div>

  <!-- Cards -->
  <main class="max-w-7xl mx-auto px-6 pb-16">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $logoPath  = !empty($row['logo']) ? 'uploads/logos/' . $row['logo'] : '';
            $isEditing = ($edit_job_id == $row['id']);
            $title     = $row['title'] ?? 'Untitled Job';
            $cat       = $row['category'] ?? 'Other';
            $type      = $row['type'] ?? 'Full-time';
            $loc       = $row['location'] ?? '—';
            $currency  = $row['currency'] ?? ''; // existing column
            $salaryStr = money_range($currency, $row['min_salary'] ?? 0, $row['max_salary'] ?? 0);
          ?>
          <article class="glass shadow-premium rounded-2xl border border-violet-100 p-5 sm:p-6 hover:shadow-xl transition">
            <div class="flex items-start gap-4">
              <!-- Avatar / Logo -->
              <?php if ($logoPath): ?>
                <div class="ring-gradient">
                  <img src="<?= e($logoPath) ?>" alt="Logo"
                       class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white object-contain p-2" />
                </div>
              <?php else: ?>
                <div class="ring-gradient">
                  <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white flex items-center justify-center text-violet-700 font-bold">
                    <?= e(initials($title)) ?>
                  </div>
                </div>
              <?php endif; ?>

              <div class="flex-1 min-w-0">
                <?php if (!$isEditing): ?>
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="pill bg-violet-100 text-violet-800 text-[11px] font-semibold px-2.5 py-1 border border-violet-200"><?= e($cat) ?></span>
                    <span class="pill bg-emerald-100 text-emerald-800 text-[11px] font-semibold px-2.5 py-1 border border-emerald-200"><?= e($type) ?></span>
                  </div>

                  <h3 class="mt-2 text-lg sm:text-xl font-semibold text-slate-900 leading-snug">
                    <?= e($title) ?>
                  </h3>

                  <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-slate-600">
                    <span class="inline-flex items-center gap-1.5">
                      <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 0c-4.5 0-6 3-6 5.5C6 19.985 8.686 21 12 21s6-.986 6-4.5c0-2.5-1.5-5.5-6-5.5Z"/>
                      </svg>
                      <?= e($loc) ?>
                    </span>
                    <span class="pill bg-slate-100 border border-slate-200 px-3 py-1 text-slate-700 font-medium">
                      <?= e($salaryStr) ?>
                    </span>
                  </div>

                  <p class="mt-3 text-sm text-slate-700 truncate-3"><?= e($row['description']) ?></p>
                <?php else: ?>
                  <!-- Edit Form -->
                  <form method="POST" class="mt-1 space-y-4">
                    <input type="hidden" name="edit_job_id" value="<?= (int)$row['id'] ?>">

                    <div class="grid sm:grid-cols-2 gap-4">
                      <div>
                        <label class="text-xs font-semibold text-violet-700">Title</label>
                        <input type="text" name="title" value="<?= e($row['title']) ?>" required
                               class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                      </div>
                      <div>
                        <label class="text-xs font-semibold text-violet-700">Type</label>
                        <input type="text" name="type" value="<?= e($row['type']) ?>" required
                               class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500" />
                      </div>
                      <div>
                        <label class="text-xs font-semibold text-violet-700">Category</label>
                        <select name="category" required
                                class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                          <?php foreach ($categories as $catOpt): ?>
                            <option value="<?= e($catOpt) ?>" <?= ($catOpt === ($row['category'] ?? '')) ? 'selected' : '' ?>><?= e($catOpt) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div>
                        <label class="text-xs font-semibold text-violet-700">Location</label>
                        <input type="text" name="location" value="<?= e($row['location']) ?>" required
                               class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500" />
                      </div>
                      <div>
                        <label class="text-xs font-semibold text-violet-700">Min Salary</label>
                        <input type="number" step="0.01" name="min_salary" value="<?= e($row['min_salary']) ?>" required
                               class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500" />
                      </div>
                      <div>
                        <label class="text-xs font-semibold text-violet-700">Max Salary</label>
                        <input type="number" step="0.01" name="max_salary" value="<?= e($row['max_salary']) ?>" required
                               class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500" />
                      </div>

                      <div>
                        <label class="text-xs font-semibold text-violet-700">Currency</label>
                        <select name="currency" required
                                class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                          <?php
                            $currentCurrency = (string)($row['currency'] ?? '');
                            // ensure current value appears first even if not in distinct list
                            if ($currentCurrency !== '' && !in_array($currentCurrency, $currencyOptions, true)) {
                              echo '<option value="'.e($currentCurrency).'" selected>'.e($currentCurrency).' (current)</option>';
                            }
                            foreach ($currencyOptions as $cc) {
                              $sel = ($cc === $currentCurrency) ? 'selected' : '';
                              echo '<option value="'.e($cc).'" '.$sel.'>'.e($cc).'</option>';
                            }
                          ?>
                        </select>
                      </div>
                    </div>

                    <div>
                      <label class="text-xs font-semibold text-violet-700">Description</label>
                      <textarea name="description" rows="4" required
                                class="mt-1 w-full bg-white/90 border border-violet-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"><?= e($row['description']) ?></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-1">
                      <a href="<?= e($_SERVER['PHP_SELF']) ?>"
                         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-semibold">
                        Cancel
                      </a>
                      <button type="submit"
                              class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-premium">
                        Save Changes
                      </button>
                    </div>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <?php if (!$isEditing): ?>
              <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-slate-500">Job ID: <?= (int)$row['id'] ?></div>
                <div class="flex items-center gap-2">
                  <a href="?<?= e(http_build_query(array_merge($_GET, ['edit' => $row['id']]))) ?>"
                     class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-100 text-amber-800 hover:bg-amber-200 border border-amber-200 text-sm font-semibold">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M15.232 5.232a2.5 2.5 0 1 1 3.536 3.536L8.5 19.036l-4 1 1-4 9.732-10.804Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Edit
                  </a>
                  <form method="POST" onsubmit="return confirm('Delete this job permanently?')">
                    <input type="hidden" name="delete_job_id" value="<?= (int)$row['id'] ?>">
                    <button type="submit"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white border border-rose-600 text-sm font-semibold">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M3 6h18M8 6V4h8v2m-1 0v14a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V6h8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      Delete
                    </button>
                  </form>
                </div>
              </div>
            <?php endif; ?>
          </article>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="lg:col-span-2">
          <div class="glass shadow-premium rounded-2xl border border-violet-100 p-10 text-center">
            <div class="text-5xl mb-3">🔎</div>
            <h3 class="text-xl font-semibold text-slate-900">No jobs found</h3>
            <p class="text-slate-600 mt-1">Try a different category filter.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>
