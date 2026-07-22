<?php
// DB connection
$conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
require_once __DIR__ . '/settings_helper.php';

// Sanitize helper
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Resolve mode: list or detail (same page)
$detail_title_param = isset($_GET['title']) ? trim($_GET['title']) : '';

// Detail: fetch the requested blog (by title). Prefer id/slug in production.
$detail_post = null;
if ($detail_title_param !== '') {
    $stmt = $conn->prepare("SELECT title, image, content, created_at FROM blogs WHERE title = ? LIMIT 1");
    $stmt->bind_param('s', $detail_title_param);
    $stmt->execute();
    $detail_post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// List queries
$blog_result  = $conn->query("SELECT title, image, content, created_at FROM blogs ORDER BY created_at DESC LIMIT 5");
$video_result = $conn->query("SELECT title, description, video_path, uploaded_at FROM videos ORDER BY uploaded_at DESC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= e(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= e(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <link rel="icon" type="image/png" href="img/1748025713_MS copy.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Inter:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Optional libs you already had -->
  <link href="lib/animate/animate.min.css" rel="stylesheet">
  <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root{ --brand:#2563eb; --ink:#0f172a; --muted:#64748b; }
    body{font-family:'Heebo',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a}
    .nav-shadow{box-shadow:0 4px 14px rgba(2,6,23,.08)}
    .page-hero{
      background: radial-gradient(1200px 400px at 20% -10%, rgba(37,99,235,.35), transparent 60%),
                  radial-gradient(1200px 400px at 80% -10%, rgba(99,102,241,.25), transparent 60%),
                  #0b1220;
    }
    .card{
      border-radius:16px;border:1px solid rgba(15,23,42,.06);box-shadow:0 6px 20px rgba(2,6,23,.06);
      transition:transform .2s, box-shadow .2s, border-color .2s; background:#fff; position:relative;
    }
    .card:hover{ transform:translateY(-4px); box-shadow:0 12px 30px rgba(2,6,23,.10); border-color:rgba(37,99,235,.25) }
    .title-cut{ display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden }
    .excerpt-cut{ display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden }
    .media-wrap{ position:relative; width:100%; padding-top:56.25%; border-radius:12px; overflow:hidden; background:#0b1220 }
    .media-wrap img,.media-wrap video{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover }
    .section-title{ font-family:'Inter',sans-serif; font-weight:800; letter-spacing:-.02em }
    .stretched-link{ position:absolute; inset:0; z-index:10 }
    @media (max-width:480px){ .display-3{font-size:2rem} }
    .prose p{ margin: 0 0 1em 0; line-height:1.8 }
  </style>
</head>
<body class="bg-gray-50">
<div class="container-xxl p-0">


  <!-- HERO -->
  <?php require_once __DIR__ . '/header.php'; ?>


  <!-- ===== DETAIL VIEW (same page) ===== -->
  <?php if ($detail_post): ?>
    <section class="py-10 md:py-14">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-semibold mb-6">
          <i class="bi bi-arrow-left"></i> Back to all posts
        </a>

        <article class="card overflow-hidden">
          <?php if (!empty($detail_post['image'])): ?>
            <div class="media-wrap">
              <img src="<?= e($detail_post['image']) ?>" alt="<?= e($detail_post['title']) ?>">
            </div>
          <?php endif; ?>

          <div class="p-6 md:p-8">
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 mb-3">
              <?= e($detail_post['title']) ?>
            </h1>
            <?php if (!empty($detail_post['created_at'])): ?>
              <div class="text-xs text-slate-500 mb-5">
                <i class="bi bi-calendar-event"></i>
                <?= e(date('M j, Y', strtotime($detail_post['created_at']))) ?>
              </div>
            <?php endif; ?>

            <!-- Content: escaped + nl2br. If you store trusted HTML, echo it raw instead (with sanitization). -->
            <div class="prose prose-slate max-w-none text-slate-800 leading-8">
              <?= nl2br(e($detail_post['content'])) ?>
            </div>
          </div>
        </article>

        <!-- Next: link back to list shortcut -->
        <div class="mt-8">
          <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold">
            <i class="bi bi-arrow-left"></i> Back to all posts
          </a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ===== BLOG LIST (hidden if in detail mode) ===== -->
  <?php if (!$detail_post): ?>
    <section class="py-10 md:py-14">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between gap-4 mb-6">
          <h2 class="section-title text-2xl md:text-3xl text-slate-900">Latest Blog Posts</h2>
          <span class="hidden sm:inline-flex items-center gap-2 text-blue-600 font-semibold opacity-60">
            Browse the newest 5 posts
          </span>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <?php if ($blog_result && $blog_result->num_rows > 0): ?>
            <?php while ($row = $blog_result->fetch_assoc()): ?>
              <?php
                $titleSafe = e($row['title']);
                $titleUrl  = urlencode($row['title']);
              ?>
              <article class="card overflow-hidden h-full flex flex-col group">
                <!-- FULL-CARD LINK back to this same page with ?title=... -->
                <a href="?title=<?= $titleUrl ?>" class="stretched-link" aria-label="Open blog: <?= $titleSafe ?>"></a>

                <?php if (!empty($row['image'])): ?>
                  <div class="media-wrap">
                    <img loading="lazy" src="<?= e($row['image']) ?>" alt="<?= $titleSafe ?>">
                  </div>
                <?php endif; ?>
                <div class="p-5 flex flex-col gap-3 grow">
                  <h3 class="text-lg md:text-xl font-bold text-slate-900 title-cut"><?= $titleSafe ?></h3>

                  <?php if (!empty($row['created_at'])): ?>
                    <span class="text-xs text-slate-500">
                      <i class="bi bi-calendar-event"></i>
                      <?= e(date('M j, Y', strtotime($row['created_at']))) ?>
                    </span>
                  <?php endif; ?>

                  <p class="text-slate-600 text-sm md:text-base excerpt-cut">
                    <?= nl2br(e($row['content'])) ?>
                  </p>
                  <div class="mt-auto pt-2">
                    <span class="inline-flex items-center gap-2 text-blue-600 font-semibold">
                      Read more <i class="bi bi-arrow-right"></i>
                    </span>
                  </div>
                </div>
              </article>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="col-span-full">
              <div class="card p-8 text-center">
                <h3 class="text-slate-900 font-semibold text-lg mb-2">No blog posts found</h3>
                <p class="text-slate-500 text-sm">Please check back later for updates.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- VIDEOS (always show; you can hide on detail if desired) -->
  <section class="py-8 md:py-12 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-end justify-between gap-4 mb-6">
        <h2 class="section-title text-2xl md:text-3xl text-slate-900">Latest Videos</h2>
        <span class="hidden sm:inline-flex items-center gap-2 text-blue-600 font-semibold opacity-60">
          Recently uploaded
        </span>
      </div>

      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php if ($video_result && $video_result->num_rows > 0): ?>
          <?php while ($video = $video_result->fetch_assoc()): ?>
            <article class="card overflow-hidden h-full flex flex-col">
              <div class="media-wrap">
                <video controls preload="metadata" poster="img/video-cover.jpg">
                  <source src="uploads/videos/<?= e($video['video_path']) ?>" type="video/mp4">
                  Your browser does not support the video tag.
                </video>
              </div>
              <div class="p-5 flex flex-col gap-2 grow">
                <h3 class="text-lg md:text-xl font-bold text-slate-900 title-cut"><?= e($video['title']) ?></h3>
                <p class="text-slate-600 text-sm md:text-base excerpt-cut"><?= nl2br(e($video['description'])) ?></p>
                <?php if (!empty($video['uploaded_at'])): ?>
                  <span class="text-xs text-slate-500 mt-1">
                    <i class="bi bi-calendar-event"></i>
                    <?= e(date('M j, Y', strtotime($video['uploaded_at']))) ?>
                  </span>
                <?php endif; ?>
              </div>
            </article>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="col-span-full">
            <div class="card p-8 text-center">
              <h3 class="text-slate-900 font-semibold text-lg mb-2">No videos found</h3>
              <p class="text-slate-500 text-sm">We’ll upload new content soon.</p>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <?php require_once __DIR__ . '/footer.php'; ?>


</div>
</body>
</html>
<?php $conn->close(); ?>
