<?php
declare(strict_types=1);
session_start();

/* ==== DB CONFIG (inline) ==== */
$DB_HOST = "127.0.0.1:3306";
$DB_USER = "u903588615_root";
$DB_PASS = "Msjobs#1";
$DB_NAME = "u903588615_exaple";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$conn->set_charset('utf8mb4');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { header('Location: companies.php'); exit; }

/* company */
$stmt = $conn->prepare("SELECT * FROM companies WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$company) { header('Location: companies.php'); exit; }

/* rating summary */
$stmt = $conn->prepare("SELECT COALESCE(AVG(rating),0) avg_rating, COUNT(*) cnt FROM company_reviews WHERE company_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$sum = $stmt->get_result()->fetch_assoc();
$stmt->close();

$avg = (float)$sum['avg_rating'];
$cnt = (int)$sum['cnt'];
$full = str_repeat('★', (int)round($avg));
$empty= str_repeat('☆', 5 - (int)round($avg));

/* reviews list */
$stmt = $conn->prepare("SELECT rating, title, review_text, reviewer, created_at FROM company_reviews WHERE company_id=? ORDER BY id DESC LIMIT 50");
$stmt->bind_param("i", $id);
$stmt->execute();
$reviews = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?=h($company['name'])?> — Reviews</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{brand:"#1f57c3",brandDark:"#153c83"},fontFamily:{sans:["Inter","system-ui"]}}}}</script>
</head>
<body class="bg-white text-gray-900">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <a href="companies.php" class="text-blue-600 hover:underline">&larr; Back to companies</a>

    <header class="mt-4 flex items-start gap-4">
      <?php $logo = $company['logo']?'uploads/company_logos/'.$company['logo']:''; ?>
      <?php if ($logo): ?>
        <img src="<?=h($logo)?>" class="w-16 h-16 rounded border object-contain bg-white" alt="Logo">
      <?php else: ?>
        <div class="w-16 h-16 rounded border grid place-items-center bg-gray-50">🏢</div>
      <?php endif; ?>
      <div>
        <h1 class="text-2xl font-bold"><?=h($company['name'])?></h1>
        <div class="text-gray-600"><?=h($company['industry'])?> • <?=h($company['hq_location'] ?? '')?></div>
        <?php if ($company['website']): ?>
          <a href="<?=h($company['website'])?>" target="_blank" class="text-blue-600 hover:underline text-sm">
            <?=h($company['website'])?>
          </a>
        <?php endif; ?>
        <div class="mt-1 text-amber-500">
          <span class="text-lg"><?= $full.$empty ?></span>
          <span class="text-sm text-gray-600 ml-2"><?= number_format($cnt) ?> reviews</span>
        </div>
      </div>
    </header>

    <?php if (!empty($company['description'])): ?>
      <p class="mt-4 text-gray-700"><?=nl2br(h($company['description']))?></p>
    <?php endif; ?>

    <!-- Add a review -->
    <section class="mt-8">
      <h2 class="text-xl font-semibold mb-3">Write a review</h2>
      <form action="review_save.php" method="post" class="grid gap-3 rounded-xl border p-4">
        <input type="hidden" name="company_id" value="<?=$id?>">
        <div>
          <label class="block text-sm font-medium">Rating</label>
          <select name="rating" required class="mt-1 px-3 py-2 rounded border">
            <option value="">Select rating</option>
            <option value="5">★★★★★ (5)</option>
            <option value="4">★★★★☆ (4)</option>
            <option value="3">★★★☆☆ (3)</option>
            <option value="2">★★☆☆☆ (2)</option>
            <option value="1">★☆☆☆☆ (1)</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium">Title (optional)</label>
          <input name="title" class="mt-1 px-3 py-2 rounded border w-full" placeholder="Great place to grow">
        </div>
        <div>
          <label class="block text-sm font-medium">Your review</label>
          <textarea name="review_text" required rows="4" class="mt-1 px-3 py-2 rounded border w-full"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium">Display name (optional)</label>
          <input name="reviewer" class="mt-1 px-3 py-2 rounded border w-full" placeholder="John D.">
        </div>
        <div class="flex items-center gap-3">
          <button class="px-5 py-2 rounded bg-brand text-white font-semibold hover:bg-brandDark">Submit review</button>
        </div>
      </form>
    </section>

    <!-- Reviews -->
    <section class="mt-8">
      <h2 class="text-xl font-semibold mb-3">Recent reviews</h2>
      <?php if ($reviews->num_rows > 0): ?>
        <div class="grid gap-4">
          <?php while($r = $reviews->fetch_assoc()):
            $f = str_repeat('★',(int)$r['rating']); $e = str_repeat('☆', 5-(int)$r['rating']); ?>
            <article class="rounded-xl border p-4">
              <div class="text-amber-500 text-lg"><?= $f.$e ?></div>
              <?php if(!empty($r['title'])): ?><div class="mt-1 font-semibold"><?=h($r['title'])?></div><?php endif; ?>
              <p class="mt-2 text-gray-700 whitespace-pre-line"><?=h($r['review_text'])?></p>
              <div class="mt-2 text-sm text-gray-500">
                <?= h($r['reviewer'] ?: 'Anonymous') ?> • <?= h(date('M j, Y', strtotime($r['created_at']))) ?>
              </div>
            </article>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <div class="rounded-xl border p-6 text-gray-600">No reviews yet. Be the first to review.</div>
      <?php endif; ?>
    </section>
  </div>
</body>
</html>
