<?php
// config.php must include $conn = new mysqli(...) to connect to DB
include 'config.php';

// Fetch latest 5 blog posts
$blog_result = $conn->query("SELECT title, image, content FROM blogs ORDER BY created_at DESC LIMIT 5");
$video_result = $conn->query("SELECT title, video_link, thumbnail FROM videos ORDER BY created_at DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Latest Updates</title>
      <link rel="icon" type="image/png" href="img/MS copy.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Inter:wght@700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome & Bootstrap Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
     <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- CSS Libraries -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Bootstrap & Custom Styles -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
<div class="container-xxl bg-white p-0">

    <!-- Spinner Start -->
    <div id="spinner" class="show bg-white position-fixed top-50 start-50 translate-middle w-100 vh-100 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

   
       <!-- Tailwind Navbar Start -->
<nav class="bg-white shadow sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <!-- Logo Section -->
      <a href="index.php" class="flex items-center space-x-2">
        <img src="img/MS copy.png" alt="MSJOBS Logo" class="h-10">
        <h1 class="text-xl font-bold text-blue-600">MSJOBS</h1>
      </a>

      <!-- Mobile menu button -->
      <div class="-mr-2 flex md:hidden">
        <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-blue-600 hover:bg-gray-100 focus:outline-none" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
          <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path class="inline" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

      <!-- Desktop menu -->
      <div class="hidden md:flex md:items-center space-x-6">
        <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium">Home</a>
        <a href="about.php" class="text-gray-700 hover:text-blue-600 font-medium">About</a>
        <a href="career-advice.php" class="text-gray-700 hover:text-blue-600 font-medium">Career Advice</a>
        <a href="contact.php" class="text-gray-700 hover:text-blue-600 font-medium">Contact</a>
        <a href="login.php" class="ml-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded transition">LOGIN <i class="fa fa-arrow-right ml-2"></i></a>
      </div>
    </div>
  </div>

  <!-- Mobile Menu -->
  <div class="md:hidden hidden" id="mobile-menu">
    <div class="pt-4 pb-4 space-y-2">
      <a href="index.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Home</a>
      <a href="about.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">About</a>
      <a href="career-advice.php" class="block px-4 py-2 text-emerald-600 font-semibold bg-gray-50">Career Advice</a>
      <a href="contact.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Contact</a>
      <a href="login.php" class="block px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 font-medium text-center rounded">LOGIN</a>
    </div>
  </div>
</nav>
<!-- Tailwind Navbar End -->


    <!-- Page Header Start -->
    <div class="container-xxl py-5 bg-dark page-header mb-5">
        <div class="container my-5 pt-5 pb-4">
            <h1 class="display-3 text-white mb-3">About Us</h1>
            <nav aria-label="breadcrumb">
                
            </nav>
        </div>
    </div>
    <!-- Page Header End -->
    <!-- Blog Section Start -->
    <div class="container-xxl py-5">
        <div class="container">
            <h2 class="mb-5">Latest Blog Posts</h2>
            <div class="row">
                <?php if ($blog_result && $blog_result->num_rows > 0): ?>
                    <?php while ($row = $blog_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="bg-white p-4 rounded shadow-sm h-100">
                                <h5 class="text-primary mb-3"><?= htmlspecialchars($row['title']) ?></h5>
                                <?php if (!empty($row['image'])): ?>
                                    <img src="<?= htmlspecialchars($row['image']) ?>" alt="Blog Image" class="img-fluid rounded mb-3" style="height: 200px; object-fit: cover; width: 100%;">
                                <?php endif; ?>
                                <p class="text-muted" style="max-height: 100px; overflow: hidden;"><?= nl2br(htmlspecialchars($row['content'])) ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No blog posts found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Blog Section End -->
    <!-- Latest Videos Section Start -->
<div class="container-xxl py-5">
    <div class="container">
        <h2 class="mb-5">Latest Videos</h2>
        <div class="row">
            <?php
            // Connect to database
            $conn = new mysqli("localhost", "root", "", "exaple");
            if ($conn->connect_error) {
                echo "<p class='text-red-500'>Database connection failed: " . htmlspecialchars($conn->connect_error) . "</p>";
            } else {
                $result = $conn->query("SELECT title, description, video_path, uploaded_at FROM videos ORDER BY uploaded_at DESC LIMIT 3");

                if ($result && $result->num_rows > 0):
                    while ($video = $result->fetch_assoc()):
                        $videoUrl = 'uploads/videos/' . $video['video_path'];
            ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="bg-white p-3 rounded shadow-sm h-100">
                        <h5 class="text-primary"><?= htmlspecialchars($video['title']) ?></h5>
                        <video controls width="100%" style="height: 200px; object-fit: cover;">
                            <source src="<?= htmlspecialchars($videoUrl) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <p class="text-muted mt-2"><?= nl2br(htmlspecialchars($video['description'])) ?></p>
                    </div>
                </div>
            <?php
                    endwhile;
                else:
                    echo "<p>No videos found.</p>";
                endif;
                $conn->close();
            }
            ?>
        </div>
    </div>
</div>






      <!-- Footer Start -->
        <div class="container-fluid bg-dark text-white-50 footer pt-5 mt-5 wow fadeIn" data-wow-delay="0.1s">
            <div class="container py-5">
                <div class="row g-5">
                    <div class="col-lg-3 col-md-6">
                        <h5 class="text-white mb-4">Company</h5>
                        <a class="btn btn-link text-white-50" href="about.php">About Us</a>
                        <a class="btn btn-link text-white-50" href="contact.php">Contact Us</a>
                        <a class="btn btn-link text-white-50" href="career-advice.php">Career Advice</a>
                        <a class="btn btn-link text-white-50" href="blog.php">Latest Blogs</a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h5 class="text-white mb-4">Legal</h5>
                        <a class="btn btn-link text-white-50" href="privacy-policy.php">Privacy Policy</a>
                        <a class="btn btn-link text-white-50" href="terms-conditions.php">Terms & Conditions</a>
                        <a class="btn btn-link text-white-50" href="cookies.php">Cookie Policy</a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h5 class="text-white mb-4">Contact</h5>
                        <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>Real Group Building, Ajman Industrial Area 2, United Arab Emirates</p>
                        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>+971585538199</p>
                        <p class="mb-2"><i class="fa fa-envelope me-3"></i>support@msjobs.net</p>
                        <div class="d-flex pt-2">
                            <a class="btn btn-outline-light btn-social" href="https://www.linkedin.com/company/ms-group-of-companies-uae/"><i class="fab fa-linkedin-in"></i></a>
                            <a class="btn btn-outline-light btn-social" href="https://www.tiktok.com/@msjobs.net1?_t=ZS-8yqnnjfytl4&_r=1"><i class="fab fa-tiktok"></i></a>
                            <a class="btn btn-outline-light btn-social" href="https://www.instagram.com/ms_group2023?igsh=ZXVnYmN5dXJwNnZt"><i class="fab fa-instagram"></i></a>
                            <a class="btn btn-outline-light btn-social" href="https://www.facebook.com/share/1BqcPQMQEC/"><i class="fab fa-facebook-f"></i></a>
                        </div>

                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h5 class="text-white mb-4">Newsletter</h5>
                        <p></p>
                        <div class="position-relative mx-auto" style="max-width: 400px;">
                            <input class="form-control bg-transparent w-100 py-3 ps-4 pe-5" type="text"
                                placeholder="Your email">
                            <button type="button"
                                class="btn btn-primary py-2 position-absolute top-0 end-0 mt-2 me-2">Login</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="copyright">
                    <div class="row">
                        <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                            &copy; <a class="border-bottom" href="#">MS JOBS</a>, All Right Reserved.

                            <!--/*** This template is free as long as you keep the footer author’s credit link/attribution link/backlink. If you'd like to use the template without the footer author’s credit link/attribution link/backlink, you can purchase the Credit Removal License from "https://htmlcodex.com/credit-removal". Thank you for your support. ***/-->
                            Designed By <a class="border-bottom" href="">MS JOBS</a>
                        </div>
                        <div class="col-md-6 text-center text-md-end">
                            <div class="footer-menu">
                                <a href="index.php">Home</a>
                                <a href="">Cookies</a>
                                <a href="">Help</a>
                                <a href="Faq.php">FAQs</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->

</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="lib/wow/wow.min.js"></script>
<script src="lib/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom Script -->
<script>
    window.addEventListener('load', function () {
        document.getElementById('spinner').classList.remove('show');
    });
</script>
</body>
</html>
