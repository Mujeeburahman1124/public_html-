<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch employer profile
$sql = "
    SELECT users.id, users.email, employers.company_name, employers.company_description, 
           employers.contact_person, employers.phone, employers.country, employers.logo
    FROM users
    INNER JOIN employers ON users.id = employers.user_id
    WHERE users.id = ?
";

$query = $conn->prepare($sql);
if (!$query) {
    die("Query Preparation Failed: " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$employer = $result->fetch_assoc();

if (!$employer) {
    header("Location: login.php");
    exit;
}

// Fetch blogs
$blog_result = $conn->query("SELECT * FROM blogs ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MS JOBS Employer Dashboard</title>

  <!-- Favicon(s) -->
  <link rel="icon" type="image/png" href="img/MS copy.png">
  <link rel="icon" href="img/favicon.ico" />

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Effects/Libraries (kept as you had) -->
  <link href="lib/animate/animate.min.css" rel="stylesheet">
  <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">

  <!-- TailwindCSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Swiper (kept as you had) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

  <style>
    :root{
      --brand:#1f6feb; /* Deep blue (Naukri vibe) */
      --brand2:#0ea5e9; /* Sky accent */
      --ink:#0f172a;    /* Slate-900 */
      --muted:#475569;  /* Slate-600 */
      --card:#ffffff;
      --line:#e5e7eb;
    }
    body{ font-family:"Poppins", system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Helvetica, Arial, sans-serif; }

    /* Utilities to avoid Tailwind config editing */
    .line-clamp-3{ display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
    .line-clamp-4{ display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden; }

    /* Card feel */
    .card{ background:var(--card); border:1px solid var(--line); border-radius:14px; box-shadow:0 6px 18px rgba(2,6,23,.05); }

    /* Gradient header */
    .gradbar{ background:linear-gradient(90deg, var(--brand) 0%, var(--brand2) 100%); }

    /* Badges */
    .badge{ background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:9999px; padding:2px 10px; font-size:12px; }

    /* Image ring */
    .ringed{ box-shadow:0 0 0 4px rgba(255,255,255,.9); }

    /* Smooth hover */
    .linkish{ color:var(--brand); }
    .linkish:hover{ text-decoration:underline; }
  </style>
</head>
<body class="bg-slate-50">

<!-- Navbar -->
<nav class="gradbar py-4 px-4 md:px-6 shadow-sm sticky top-0 z-30">
  <div class="max-w-7xl mx-auto flex justify-between items-center text-white">
    <div class="flex items-center gap-3">
      <img class="h-10 w-10 rounded-full border-2 border-white/70" src="img/1748025713_MS copy.png" alt="Logo">
      <div class="leading-tight">
        <div class="text-lg font-semibold tracking-wide">MS JOBS COMPANY DASHBOARD</div>
        <div class="text-[11px] opacity-90">Manage jobs & applications</div>
      </div>
    </div>

    <div class="hidden sm:flex items-center gap-6 text-sm font-medium">
      <a href="admin-add-job.php" class="hover:text-white/90"><i class="fa-solid fa-briefcase mr-2"></i>Post Job</a>
      <a href="manage_jobs.php" class="hover:text-white/90"><i class="fa-solid fa-list-check mr-2"></i>Manage Jobs</a>
      <a href="edit-employer-profile.php" class="hover:text-white/90"><i class="fa-solid fa-user-gear mr-2"></i>Profile Setting</a>
      <a href="view-applications.php" class="hover:text-white/90"><i class="fa-solid fa-inbox mr-2"></i>View Applications</a>
      <a href="Companylogin.php" class="hover:text-white/90"><i class="fa-solid fa-database mr-2"></i>Jobseeker Database</a>
      <a href="login.php" class="hover:text-white/90"><i class="fa-solid fa-right-from-bracket mr-2"></i>Logout</a>
    </div>

    <button id="menu-toggle" class="sm:hidden text-white text-2xl" aria-label="Menu">
      <i class="fas fa-bars"></i>
    </button>
  </div>
</nav>

<!-- Mobile Menu -->
<div id="mobile-menu" class="sm:hidden hidden bg-white text-blue-800 px-6 py-4 shadow space-y-2 border-b">
  <a href="admin-add-job.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-briefcase mr-2"></i>Post Job</a>
  <a href="manage_jobs.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-list-check mr-2"></i>Manage Jobs</a>
  <a href="edit-employer-profile.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-user-gear mr-2"></i>Profile Setting</a>
  <a href="view-applications.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-inbox mr-2"></i>View Applications</a>
  <a href="Companylogin.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-database mr-2"></i>Jobseeker Database</a>
  <a href="login.php" class="block py-2 hover:text-blue-600"><i class="fa-solid fa-right-from-bracket mr-2"></i>Logout</a>
</div>

<!-- Notification -->
<?php if (isset($_GET['approved']) && $_GET['approved'] == '1'): ?>
  <div class="max-w-7xl mx-auto px-4">
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm my-4 rounded shadow-sm">
      ✅ Your job post has been approved!
    </div>
  </div>
<?php endif; ?>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 py-8">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Blog Posts -->
    <div class="lg:col-span-2 card p-6">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-[var(--ink)]">From the Blog</h2>
        <span class="text-xs text-slate-500">Insights & updates</span>
      </div>
      <div class="mt-6 space-y-8">
        <?php while ($row = $blog_result->fetch_assoc()): ?>
          <article class="border-t first:border-t-0 pt-6">
            <div class="flex items-center gap-x-3 text-xs text-gray-500 mb-2">
              <time datetime="<?= isset($row['publish_date']) ? htmlspecialchars($row['publish_date']) : '' ?>">
                <?= !empty($row['publish_date']) ? date("M d, Y", strtotime($row['publish_date'])) : date("M d, Y"); ?>
              </time>
              <?php if (!empty($row['category'])): ?>
                <span class="badge"><?= htmlspecialchars($row['category']); ?></span>
              <?php endif; ?>
            </div>

            <h3 class="text-lg md:text-xl font-semibold text-slate-900">
              <?= htmlspecialchars($row['title']); ?>
            </h3>

            <?php if (!empty($row['image'])): ?>
              <img src="<?= htmlspecialchars($row['image']); ?>" alt="Blog Image"
                   class="w-full h-56 md:h-60 object-cover rounded-lg mt-4 border">
            <?php else: ?>
              <div class="w-full h-56 grid place-items-center rounded-lg mt-4 border bg-slate-50 text-slate-400">
                <i class="fa-regular fa-image text-2xl"></i>
              </div>
            <?php endif; ?>

            <p class="mt-4 text-slate-700 text-sm leading-relaxed line-clamp-4">
              <?= nl2br(htmlspecialchars($row['content'])); ?>
            </p>
          </article>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Employer Profile -->
    <div class="card p-6">
      <div class="flex flex-col items-center text-center space-y-4">
        <img src="<?= htmlspecialchars($employer['logo']); ?>" alt="Company Logo"
             class="w-24 h-24 object-cover rounded-full ringed">
        <h3 class="text-xl font-bold text-slate-900">
          Welcome, <span class="text-[var(--brand)]"><?= htmlspecialchars($employer['company_name']); ?></span>!
        </h3>

        <div class="w-full text-left text-sm text-slate-700 space-y-2 mt-2">
          <p><span class="font-medium text-slate-900">Email:</span> <?= htmlspecialchars($employer['email']); ?></p>
          <p><span class="font-medium text-slate-900">Company Name:</span> <?= htmlspecialchars($employer['company_name']); ?></p>
          <p><span class="font-medium text-slate-900">Contact Person:</span> <?= htmlspecialchars($employer['contact_person']); ?></p>
          <p><span class="font-medium text-slate-900">Phone:</span> <?= htmlspecialchars($employer['phone']); ?></p>
          <p><span class="font-medium text-slate-900">Country:</span> <?= htmlspecialchars($employer['country']); ?></p>
          <div class="pt-1">
            <p class="font-medium text-slate-900 mb-1">Description</p>
            <div class="max-h-36 overflow-y-auto text-slate-600 bg-slate-50 p-3 rounded border">
              <?= nl2br(htmlspecialchars($employer['company_description'])); ?>
            </div>
          </div>
        </div>

        <div class="w-full grid grid-cols-1 gap-2 pt-2">
          <a href="admin-add-job.php" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-[var(--brand)] text-white font-medium hover:opacity-95">
            <i class="fa-solid fa-plus"></i> Post Job
          </a>
          <a href="view-applications.php" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white border hover:bg-slate-50 text-slate-800">
            <i class="fa-solid fa-inbox"></i> View Applications
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Footer (unchanged links/content, just styled) -->
<div class="container-fluid bg-[#0b1220] text-white/70 footer pt-12 mt-6">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-3 col-md-6">
        <h5 class="text-white mb-4">Company</h5>
        <a class="btn btn-link text-white-50" href="">About Us</a>
        <a class="btn btn-link text-white-50" href="">Contact Us</a>
        <a class="btn btn-link text-white-50" href="">Our Services</a>
        <a class="btn btn-link text-white-50" href="">Privacy Policy</a>
        <a class="btn btn-link text-white-50" href="">Terms & Condition</a>
      </div>
      <div class="col-lg-3 col-md-6">
        <h5 class="text-white mb-4">Quick Links</h5>
        <a class="btn btn-link text-white-50" href="">About Us</a>
        <a class="btn btn-link text-white-50" href="">Contact Us</a>
        <a class="btn btn-link text-white-50" href="">Our Services</a>
        <a class="btn btn-link text-white-50" href="">Privacy Policy</a>
        <a class="btn btn-link text-white-50" href="">Terms & Condition</a>
      </div>
      <div class="col-lg-3 col-md-6">
        <h5 class="text-white mb-4">Contact</h5>
        <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>Real Group Building, Ajman Industrial Area 2, United Arab Emirates</p>
        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>+971585323967</p>
        <!--<p class="mb-2"><i class="fa fa-phone-alt me-3"></i>045729430</p>-->
        <p class="mb-2"><i class="fa fa-envelope me-3"></i>Support@msjobs.net</p>
        <div class="d-flex pt-2">
          <a class="btn btn-outline-light btn-social" href="https://www.linkedin.com/company/ms-group-of-companies-uae/"><i class="fab fa-linkedin-in"></i></a>
          <a class="btn btn-outline-light btn-social" href="https://www.tiktok.com/@msjobs2026"><i class="fab fa-tiktok"></i></a>
          <a class="btn btn-outline-light btn-social" href="https://www.instagram.com/ms_group2023?igsh=ZXVnYmN5dXJwNnZt"><i class="fab fa-instagram"></i></a>
          <a class="btn btn-outline-light btn-social" href="https://www.facebook.com/share/1CNVH7tY6K/"><i class="fab fa-facebook-f"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <h5 class="text-white mb-4">Newsletter</h5>
        <p></p>
        <div class="position-relative mx-auto" style="max-width: 400px;">
          <input class="form-control bg-transparent w-100 py-3 ps-4 pe-5" type="text" placeholder="Your email">
          <button type="button" class="btn btn-primary py-2 position-absolute top-0 end-0 mt-2 me-2">Login</button>
        </div>
      </div>
    </div>

    <div class="container">
      <div class="copyright">
        <div class="row">
          <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
            &copy; <a class="border-bottom" href="#">MS JOBS</a>, All Right Reserved.
            Designed By <a class="border-bottom" href="">Vithu</a>
          </div>
          <div class="col-md-6 text-center text-md-end">
            <div class="footer-menu">
              <a href="">Home</a>
              <a href="">Cookies</a>
              <a href="">Help</a>
              <a href="Faq.php">FQAs</a>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<!-- Footer End -->

<script>
  document.getElementById('menu-toggle')?.addEventListener('click', function () {
    document.getElementById('mobile-menu')?.classList.toggle('hidden');
  });
</script>
</body>
</html>
