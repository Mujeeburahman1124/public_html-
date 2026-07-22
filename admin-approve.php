<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===== Currency list (unchanged) ===== */
$currencies = [
    'USD' => ['symbol' => '$', 'name' => 'US Dollar'],
    'QAR' => ['symbol' => 'QAR', 'name' => 'Qatari Riyal'],
    'AED' => ['symbol' => 'AED', 'name' => 'UAE Dirham'],
    'EUR' => ['symbol' => '€', 'name' => 'Euro'],
    'SAR' => ['symbol' => 'SAR', 'name' => 'Saudi Riyal'],
    'OMR' => ['symbol' => 'OMR', 'name' => 'Omani Rial'],
    'KWD' => ['symbol' => 'KWD', 'name' => 'Kuwaiti Dinar'],
    'BHD' => ['symbol' => 'BHD', 'name' => 'Bahraini Dinar'],
    'MYR' => ['symbol' => 'RM', 'name' => 'Malaysian Ringgit'],
];

/* ===== DB Connect (unchanged) ===== */
require_once __DIR__ . '/config.php'; // $conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===== Approve/Reject action (unchanged logic) ===== */
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'approved' : 'rejected';

        $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ?");
        if (!$stmt) die("Prepare failed: " . $conn->error);
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();

        // Notify employer
        $empStmt = $conn->prepare("SELECT employer_id FROM jobs WHERE id = ?");
        $empStmt->bind_param("i", $id);
        $empStmt->execute();
        $result = $empStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $employer_id = (int)$row['employer_id'];
            $message = ($status === 'approved') ? 'Your job post has been approved.' : 'Your job post has been rejected.';

            $notiStmt = $conn->prepare("INSERT INTO notifications (employer_id, job_id, message) VALUES (?, ?, ?)");
            $notiStmt->bind_param('iis', $employer_id, $id, $message);
            $notiStmt->execute();
            $notiStmt->close();
        }
        $empStmt->close();

        // Preserve current filters when returning
        $qs = $_GET;
        unset($qs['action'], $qs['id']);
        $back = 'admin-approve.php';
        if (!empty($qs)) $back .= '?' . http_build_query($qs);
        header("Location: $back");
        exit;
    }
}

/* ===== Filters: default to 'pending' tab ===== */
$status   = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'pending';
if (!in_array($status, ['pending','approved','rejected','all'], true)) $status = 'pending';
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$company  = isset($_GET['company']) ? trim($_GET['company']) : '';
$currency = isset($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : '';

/* ===== SQL helpers for status normalization =====
   Treat NULL/empty/unknown statuses as PENDING.
   Pending condition = (status IS NULL OR status='' OR status='pending' OR status NOT IN ('approved','rejected'))
*/
$PENDING_COND = "(status IS NULL OR status='' OR status='pending' OR (status NOT IN ('approved','rejected')))";

/* ===== Build WHERE + Params (prepared) ===== */
$where = [];
$params = [];
$types = '';

if ($status !== 'all') {
    if ($status === 'pending') {
        $where[] = $PENDING_COND;
    } elseif ($status === 'approved') {
        $where[] = "status = 'approved'";
    } elseif ($status === 'rejected') {
        $where[] = "status = 'rejected'";
    }
    // no bind params for status now (we embedded constants)
}
if ($q !== '') {
    $where[] = "(title LIKE CONCAT('%', ?, '%') OR company_name LIKE CONCAT('%', ?, '%') OR category LIKE CONCAT('%', ?, '%'))";
    $params[] = $q; $params[] = $q; $params[] = $q;
    $types   .= 'sss';
}
if ($category !== '') {
    $where[] = "category = ?";
    $params[] = $category;
    $types   .= 's';
}
if ($company !== '') {
    $where[] = "company_name = ?";
    $params[] = $company;
    $types   .= 's';
}
if ($currency !== '') {
    $where[] = "currency = ?";
    $params[] = $currency;
    $types   .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ===== Data for filter dropdowns (distinct values) ===== */
$cats = [];
$cos  = [];
$curs = [];
$catRS = $conn->query("SELECT DISTINCT category FROM jobs WHERE category IS NOT NULL AND category <> '' ORDER BY category");
if ($catRS) { while($r=$catRS->fetch_assoc()){ $cats[]=$r['category']; } $catRS->close(); }
$coRS = $conn->query("SELECT DISTINCT company_name FROM jobs WHERE company_name IS NOT NULL AND company_name <> '' ORDER BY company_name");
if ($coRS) { while($r=$coRS->fetch_assoc()){ $cos[]=$r['company_name']; } $coRS->close(); }
$curRS = $conn->query("SELECT DISTINCT currency FROM jobs WHERE currency IS NOT NULL AND currency <> '' ORDER BY currency");
if ($curRS){ while($r=$curRS->fetch_assoc()){ $curs[]=$r['currency']; } $curRS->close(); }

/* ===== Fetch cards (filtered) ===== */
$sqlList = "SELECT id, title, company_name, category, min_salary, max_salary, description, status, currency 
            FROM jobs
            $whereSql
            ORDER BY id DESC";
$stmtList = $conn->prepare($sqlList);
if ($params) $stmtList->bind_param($types, ...$params);
$stmtList->execute();
$listRS = $stmtList->get_result();

/* ===== Counts per status (respect other filters, vary only status) ===== */
function count_for_status(mysqli $conn, string $target, string $q, string $category, string $company, string $currency, string $PENDING_COND): int {
    $where = [];
    $params = [];
    $types = '';

    if ($target !== 'all') {
        if ($target === 'pending') {
            $where[] = $PENDING_COND;
        } elseif ($target === 'approved') {
            $where[] = "status = 'approved'";
        } elseif ($target === 'rejected') {
            $where[] = "status = 'rejected'";
        }
    }
    if ($q !== '') {
        $where[] = "(title LIKE CONCAT('%', ?, '%') OR company_name LIKE CONCAT('%', ?, '%') OR category LIKE CONCAT('%', ?, '%'))";
        $params[] = $q; $params[] = $q; $params[] = $q;
        $types   .= 'sss';
    }
    if ($category !== '') { $where[] = "category = ?"; $params[] = $category; $types .= 's'; }
    if ($company  !== '') { $where[] = "company_name = ?"; $params[] = $company; $types .= 's'; }
    if ($currency !== '') { $where[] = "currency = ?"; $params[] = $currency; $types .= 's'; }

    $sql = "SELECT COUNT(*) AS c FROM jobs " . ($where ? "WHERE ".implode(" AND ", $where) : "");
    $st = $conn->prepare($sql);
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $rs = $st->get_result(); $row = $rs->fetch_assoc();
    $st->close();
    return (int)($row['c'] ?? 0);
}
$count_pending  = count_for_status($conn, 'pending',  $q, $category, $company, $currency, $PENDING_COND);
$count_approved = count_for_status($conn, 'approved', $q, $category, $company, $currency, $PENDING_COND);
$count_rejected = count_for_status($conn, 'rejected', $q, $category, $company, $currency, $PENDING_COND);
$count_all      = count_for_status($conn, 'all',      $q, $category, $company, $currency, $PENDING_COND);

/* ===== Helpers (UI only) ===== */
function safe_num($val): string {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return htmlspecialchars((string)$val);
    return number_format((float)$val, (floor($val) == $val ? 0 : 2), '.', ',');
}
function trim_text(string $text, int $limit = 32): string {
    $words = preg_split('/\s+/', trim($text));
    if (!$words) return '';
    if (count($words) <= $limit) return implode(' ', $words);
    return implode(' ', array_slice($words, 0, $limit)) . '…';
}
function initials(string $name): string {
    $name = trim($name);
    if ($name === '') return 'MS';
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = mb_substr($parts[count($parts)-1] ?? '', 0, 1);
    return mb_strtoupper($first . $last);
}
function statusBadgeClasses(string $status): string {
    // Unknown/empty => pending badge style
    $s = strtolower(trim($status));
    if ($s === 'approved') return 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200';
    if ($s === 'rejected') return 'bg-rose-100 text-rose-700 ring-1 ring-rose-200';
    return 'bg-amber-100 text-amber-700 ring-1 ring-amber-200';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin — Manage Jobs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
      :root { --brand1: 248 89% 60%; --brand2: 283 85% 47%; }
      body { font-family: 'Figtree', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial; }
      .brand-gradient { background: linear-gradient(135deg, hsl(var(--brand1)) 0%, hsl(var(--brand2)) 100%); }
      .card {
        background: white; border-radius: 1rem;
        box-shadow: 0 10px 25px -10px rgba(16,24,40,.2), inset 0 1px 0 0 rgba(255,255,255,.6);
        transition: transform .2s ease, box-shadow .2s ease;
      }
      .card:hover { transform: translateY(-3px); box-shadow: 0 20px 40px -15px rgba(16,24,40,.25); }
      .pill { border-radius: 9999px; }
      .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
      .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
      dialog::backdrop { background: rgba(2,6,23,.5); }
      dialog { border: 0; border-radius: 1rem; width: min(900px, 92vw); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">
    <!-- Header -->
    <header class="brand-gradient text-white">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 text-white font-semibold rounded-lg hover:bg-white/30 transition-colors">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                        Back to Home
                    </a>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Admin Panel — Manage Jobs</h1>
                        <p class="text-white/90 mt-1">Filter by status and review full descriptions. New jobs are shown under <b>Pending</b>.</p>
                    </div>
                </div>
                <?php if (isset($_GET['flash'])): ?>
                    <span class="px-3 py-1 rounded-full text-sm bg-white/20">Updated: <?php echo htmlspecialchars($_GET['flash']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Filters + Tabs -->
    <div class="max-w-7xl mx-auto px-4 pt-6">
        <!-- Tabs -->
        <div class="flex flex-wrap gap-2">
            <?php
              function tabHref($st){
                  $qs = $_GET; $qs['status'] = $st;
                  return '?' . http_build_query($qs);
              }
              function tabClass($isActive){
                  return $isActive
                      ? 'bg-indigo-600 text-white'
                      : 'bg-white text-slate-700 hover:bg-slate-100';
              }
            ?>
            <a href="<?php echo tabHref('pending'); ?>" class="pill px-4 py-2 text-sm font-semibold shadow <?php echo tabClass($status==='pending'); ?>">
                Pending <span class="ml-2 px-2 py-0.5 text-xs bg-white/30 rounded-full"><?php echo $count_pending; ?></span>
            </a>
            <a href="<?php echo tabHref('approved'); ?>" class="pill px-4 py-2 text-sm font-semibold shadow <?php echo tabClass($status==='approved'); ?>">
                Approved <span class="ml-2 px-2 py-0.5 text-xs bg-white/30 rounded-full"><?php echo $count_approved; ?></span>
            </a>
            <a href="<?php echo tabHref('rejected'); ?>" class="pill px-4 py-2 text-sm font-semibold shadow <?php echo tabClass($status==='rejected'); ?>">
                Rejected <span class="ml-2 px-2 py-0.5 text-xs bg-white/30 rounded-full"><?php echo $count_rejected; ?></span>
            </a>
            <a href="<?php echo tabHref('all'); ?>" class="pill px-4 py-2 text-sm font-semibold shadow <?php echo tabClass($status==='all'); ?>">
                All <span class="ml-2 px-2 py-0.5 text-xs bg-white/30 rounded-full"><?php echo $count_all; ?></span>
            </a>
        </div>

        <!-- Filters -->
        <form method="get" class="mt-4 card p-4">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Title, company, category..."
                           class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                    <select name="category" class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-indigo-400">
                        <option value="">All</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($c===$category?'selected':''); ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Company</label>
                    <select name="company" class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-indigo-400">
                        <option value="">All</option>
                        <?php foreach ($cos as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($c===$company?'selected':''); ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                    <select name="currency" class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-indigo-400">
                        <option value="">All</option>
                        <?php foreach ($curs as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($c===$currency?'selected':''); ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3 flex gap-2">
                <button class="pill px-4 py-2 bg-indigo-600 text-white font-semibold">Apply Filters</button>
                <?php
                    $qs = $_GET; unset($qs['q'],$qs['category'],$qs['company'],$qs['currency']);
                    $resetHref = '?' . http_build_query($qs);
                ?>
                <a href="<?php echo $resetHref; ?>" class="pill px-4 py-2 bg-slate-100 text-slate-700 font-semibold">Reset</a>
            </div>
        </form>
    </div>

    <!-- Cards -->
    <main class="max-w-7xl mx-auto px-4 py-6">
        <?php if ($listRS && $listRS->num_rows > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($row = $listRS->fetch_assoc()): ?>
                    <?php
                        $id           = (int)$row['id'];
                        $title        = htmlspecialchars($row['title'] ?? '');
                        $companyName  = htmlspecialchars($row['company_name'] ?? 'Unknown Company');
                        $categoryName = htmlspecialchars($row['category'] ?? 'General');

                        // Normalize status for display: unknown/empty => pending
                        $rawStatus    = (string)($row['status'] ?? '');
                        $statusNorm   = strtolower(trim($rawStatus));
                        if ($statusNorm !== 'approved' && $statusNorm !== 'rejected' && $statusNorm !== 'pending') {
                            $statusNorm = 'pending';
                        }
                        $statusVal    = htmlspecialchars($statusNorm);

                        $currencyCode = strtoupper((string)($row['currency'] ?? 'USD'));
                        $symbol       = $currencies[$currencyCode]['symbol'] ?? '';
                        $minSal       = safe_num($row['min_salary'] ?? '');
                        $maxSal       = safe_num($row['max_salary'] ?? '');
                        $salaryLine   = ($minSal !== '' || $maxSal !== '')
                            ? trim(($minSal !== '' ? "{$symbol}{$minSal}" : '') . ($maxSal !== '' ? " – {$symbol}{$maxSal}" : ''))
                            : 'Not disclosed';

                        $descFull     = (string)($row['description'] ?? '');
                        $descCard     = trim_text(strip_tags($descFull), 40);
                        $badgeClass   = statusBadgeClasses($statusVal);
                        $initials     = initials($companyName);

                        // Keep current query when clicking actions
                        $qs = $_GET; $qs['id'] = $id; $qsApprove=$qs; $qsReject=$qs;
                        $qsApprove['action']='approve'; $qsReject['action']='reject';
                        $hrefApprove='?'.http_build_query($qsApprove);
                        $hrefReject ='?'.http_build_query($qsReject);
                    ?>
                    <article class="card p-5">
                        <div class="flex items-start gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white grid place-items-center text-sm font-semibold shadow-inner">
                                <?php echo $initials; ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <h2 class="text-lg font-bold leading-tight line-clamp-2"><?php echo $title; ?></h2>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($statusVal); ?>
                                    </span>
                                </div>
                                <div class="mt-1 text-slate-600 text-sm">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-800"><?php echo $companyName; ?></span>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 ring-1 ring-slate-200">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="opacity-70"><path d="M12 3l9 6-9 6-9-6 9-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M21 14l-9 6-9-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <?php echo $categoryName; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-2 text-sm">
                                    <span class="px-2 py-1 rounded-md bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100 font-semibold"><?php echo htmlspecialchars($currencyCode); ?></span>
                                    <span class="text-slate-700 font-medium"><?php echo $salaryLine; ?></span>
                                </div>
                                <p class="mt-3 text-slate-600 text-sm leading-6 line-clamp-3"><?php echo htmlspecialchars($descCard); ?></p>

                                <div class="mt-4 flex items-center gap-2">
                                    <button data-open="<?php echo $id; ?>"
                                            class="pill px-3 py-2 bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                                        View full description
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between">
                            <div class="text-xs text-slate-500">
                                ID: <span class="font-mono"><?php echo $id; ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($statusVal !== 'approved'): ?>
                                    <a href="<?php echo $hrefApprove; ?>"
                                       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold shadow hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 12l4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Approve
                                    </a>
                                <?php endif; ?>
                                <?php if ($statusVal !== 'rejected'): ?>
                                    <a href="<?php echo $hrefReject; ?>"
                                       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-rose-600 text-white text-sm font-semibold shadow hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Reject
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>

                    <!-- Modal (dialog) with full description -->
                    <dialog id="dlg-<?php echo $id; ?>">
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-bold"><?php echo $title; ?></h3>
                                    <div class="mt-1 text-slate-600 text-sm">
                                        <span class="font-medium text-slate-800"><?php echo $companyName; ?></span>
                                        <span class="mx-2">•</span>
                                        <span class="text-slate-700"><?php echo $categoryName; ?></span>
                                    </div>
                                    <div class="mt-2 text-sm">
                                        <span class="px-2 py-1 rounded-md bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100 font-semibold">
                                            <?php echo htmlspecialchars($currencyCode); ?>
                                        </span>
                                        <span class="ml-2 text-slate-700 font-medium"><?php echo $salaryLine; ?></span>
                                    </div>
                                </div>
                                <form method="dialog">
                                    <button class="pill px-3 py-2 bg-slate-100 text-slate-700 font-semibold">Close</button>
                                </form>
                            </div>
                            <div class="mt-4 prose max-w-none">
                                <?php echo nl2br(htmlspecialchars($descFull)); ?>
                            </div>
                            <div class="mt-6 flex gap-2">
                                <?php if ($statusVal !== 'approved'): ?>
                                    <a href="<?php echo $hrefApprove; ?>" class="pill px-4 py-2 bg-emerald-600 text-white font-semibold">Approve</a>
                                <?php endif; ?>
                                <?php if ($statusVal !== 'rejected'): ?>
                                    <a href="<?php echo $hrefReject; ?>" class="pill px-4 py-2 bg-rose-600 text-white font-semibold">Reject</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </dialog>

                    <script>
                        (function(){
                          const btns = document.querySelectorAll('[data-open="<?php echo $id; ?>"]');
                          const dlg  = document.getElementById('dlg-<?php echo $id; ?>');
                          btns.forEach(b => b.addEventListener('click', () => dlg.showModal()));
                        })();
                    </script>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="rounded-2xl p-10 text-center shadow-sm bg-white">
                <div class="text-6xl mb-4">🔍</div>
                <h2 class="text-xl font-semibold text-slate-800">No jobs found</h2>
                <p class="text-slate-600 mt-1">Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="py-10"></footer>
</body>
</html>
