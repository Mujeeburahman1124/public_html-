<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$DB_HOST = $servername;
$DB_USER = $username;
$DB_PASS = $password;
$DB_NAME = $dbname;
$database = $dbname;

session_start();

/* ==== DB CONFIG (inline) ==== */
$DB_HOST = "127.0.0.1:3306";
// $DB_USER = "u903588615_root"; (Refactored to config.php)
// $DB_PASS = "Msjobs#1"; (Refactored to config.php)
// $DB_NAME = "u903588615_exaple"; (Refactored to config.php)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$conn->set_charset('utf8mb4');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* inputs */
$industry = trim($_GET['industry'] ?? '');
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset   = ($page - 1) * $per_page;

/* industries (static list to mirror Indeed style) */
$industries = [
  "Aerospace & Defense","Agriculture","Arts, Entertainment & Recreation",
  "Construction, Repair & Maintenance Services","Education","Energy, Mining & Utilities",
  "Finance","Healthcare","Hospitality","Information Technology",
  "Logistics & Distribution","Manufacturing","Marketing & Advertising","Retail"
];

/* WHERE */
$where = "1";
$params = [];
$types  = "";
if ($industry !== '') { $where .= " AND industry = ?"; $params[]=$industry; $types.='s'; }
if ($q !== '')        { $where .= " AND (name LIKE ? OR hq_location LIKE ?)"; $like="%$q%"; $params[]=$like; $params[]=$like; $types.='ss'; }

/* total */
$stmt = $conn->prepare("SELECT COUNT(*) c FROM companies WHERE $where");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$total_pages = max(1, (int)ceil($total / $per_page));

/* list with rating summary */
$sql = "
  SELECT c.id, c.name, c.industry, c.hq_location, c.website, c.logo,
         COALESCE(AVG(r.rating),0)  AS avg_rating,
         COUNT(r.id)                AS reviews_count
  FROM companies c
  LEFT JOIN company_reviews r ON r.company_id = c.id
  WHERE $where
  GROUP BY c.id
  ORDER BY reviews_count DESC, avg_rating DESC, c.name ASC
  LIMIT ? OFFSET ?";

if ($types) {
  $stmt = $conn->prepare($sql);
  $types2 = $types . "ii";
  $params2 = [...$params, $per_page, $offset];
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>MSJOBS — Company reviews</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:["Inter","system-ui"]},colors:{brand:"#1f57c3",brandDark:"#153c83"}}}}</script>
  <style>.pill{border-radius:9999px}</style>
</head>
<body class="bg-white text-gray-900">
  <!-- Top bar -->
  <header class="border-b bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-3">
        <img src="img/MS copy.png" class="h-8 w-8 rounded" alt="MSJOBS">
        <span class="text-lg font-semibold">MSJOBS</span>
      </a>
      <nav class="hidden md:flex items-center gap-6 text-sm">
        <a href="index.php" class="hover:text-gray-900 text-gray-600">Home</a>
        <a href="companies.php" class="text-gray-900 font-medium">Company reviews</a>
        <a href="salaries.php" class="hover:text-gray-900 text-gray-600">Find salaries</a>
      </nav>
      <div class="hidden md:flex items-center gap-6 text-sm">
        <a href="login.php" class="text-brand hover:text-brandDark font-semibold">Sign in</a>
        <a href="company_add.php" class="text-gray-700 hover:text-gray-900">Add company</a>
      </div>
      <button class="md:hidden p-2" onclick="document.getElementById('mnav').classList.toggle('hidden')">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
    <div id="mnav" class="md:hidden hidden border-t">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 grid gap-2 text-sm">
        <a href="index.php" class="py-2">Home</a>
        <a href="companies.php" class="py-2">Company reviews</a>
        <a href="salaries.php" class="py-2">Find salaries</a>
        <a href="company_add.php" class="py-2">Add company</a>
        <a href="login.php" class="py-2">Sign in</a>
      </div>
    </div>
  </header>

  <main class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <h1 class="text-2xl sm:text-3xl font-bold">Browse companies by industry</h1>

      <!-- industries -->
      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($industries as $ind): ?>
          <a href="?industry=<?=urlencode($ind)?>" class="flex items-center justify-between rounded-xl border px-4 py-4 hover:shadow transition bg-white">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-blue-50 grid place-items-center">🏷️</div>
              <div class="font-medium"><?=h($ind)?></div>
            </div>
            <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="currentColor"><path d="M9 5l7 7-7 7"/></svg>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="mt-4">
        <a href="companies.php" class="pill inline-block px-4 py-2 border hover:bg-gray-50">See all industries</a>
      </div>

      <!-- search -->
      <form method="get" class="mt-8 flex flex-col sm:flex-row gap-3">
        <input type="text" name="q" value="<?=h($q)?>" placeholder="Search companies by name or location"
               class="flex-1 px-4 py-3 rounded-xl border" />
        <?php if($industry!==''): ?><input type="hidden" name="industry" value="<?=h($industry)?>"><?php endif; ?>
        <button class="pill px-5 py-3 bg-brand text-white font-semibold hover:bg-brandDark">Search</button>
      </form>

      <!-- list -->
      <section class="mt-10">
        <h2 class="text-xl sm:text-2xl font-bold mb-4">Popular companies</h2>

        <?php if ($res->num_rows > 0): ?>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php while ($row = $res->fetch_assoc()):
              $avg = (float)$row['avg_rating'];
              $cnt = (int)$row['reviews_count'];
              $logo = $row['logo'] ? 'uploads/company_logos/'.$row['logo'] : '';
              $full = str_repeat('★', (int)round($avg));
              $empty= str_repeat('☆', 5 - (int)round($avg));
            ?>
              <article class="rounded-2xl border bg-white p-4 sm:p-5 hover:shadow transition">
                <div class="flex gap-4">
                  <?php if ($logo): ?>
                    <img src="<?=h($logo)?>" class="w-12 h-12 rounded border object-contain bg-white" alt="Logo">
                  <?php else: ?>
                    <div class="w-12 h-12 rounded border grid place-items-center bg-gray-50">🏢</div>
                  <?php endif; ?>
                  <div class="min-w-0 flex-1">
                    <a href="company.php?id=<?= (int)$row['id'] ?>" class="font-semibold hover:underline">
                      <?=h($row['name'])?>
                    </a>
                    <div class="text-sm text-gray-600"><?=h($row['industry'])?> • <?=h($row['hq_location'] ?? '')?></div>
                    <div class="mt-1 text-amber-500 text-base leading-none">
                      <span><?= $full.$empty ?></span>
                      <a class="text-blue-600 text-sm ml-2 hover:underline" href="company.php?id=<?= (int)$row['id'] ?>">
                        <?= number_format($cnt) ?> reviews
                      </a>
                    </div>
                    <div class="mt-2 text-sm text-gray-600 flex gap-4">
                      <a class="hover:underline" href="salaries.php?company=<?=urlencode($row['name'])?>">Salaries</a>
                      <span>Q&amp;A</span>
                      <a class="hover:underline" href="all-jobs.php?q=<?=urlencode($row['name'])?>">Open jobs</a>
                    </div>
                  </div>
                </div>
              </article>
            <?php endwhile; ?>
          </div>

          <?php if ($total_pages > 1): 
            $qs = $_GET; unset($qs['page']); $base = 'companies.php?' . http_build_query($qs);
          ?>
            <div class="mt-6 flex items-center justify-center gap-2">
              <a class="px-3 py-2 rounded border <?= $page<=1?'pointer-events-none text-gray-400 border-gray-200':'hover:bg-gray-50' ?>"
                 href="<?=$page>1?($base.'&page='.($page-1)):'#'?>">Prev</a>
              <?php $win=2; $start=max(1,$page-$win); $end=min($total_pages,$page+$win);
              for($p=$start;$p<=$end;$p++): ?>
                <a class="px-3 py-2 rounded border <?= $p===$page?'bg-brand text-white border-brand':'hover:bg-gray-50'?>"
                   href="<?= $base.'&page='.$p ?>"><?=$p?></a>
              <?php endfor; ?>
              <a class="px-3 py-2 rounded border <?= $page>=$total_pages?'pointer-events-none text-gray-400 border-gray-200':'hover:bg-gray-50' ?>"
                 href="<?=$page<$total_pages?($base.'&page='.($page+1)):'#'?>">Next</a>
            </div>
          <?php endif; ?>

        <?php else: ?>
          <div class="rounded-xl border bg-white p-8 text-center text-gray-600">
            No companies found.
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
<?php
$stmt->close();
$conn->close();
