<?php
/* ==========================================================
 * MSJOBS — Employer Profile Edit (Responsive + Premium UI)
 * - Secure: Session guard, CSRF token, prepared statements
 * - Only editable: contact_person, phone
 * - Bootstrap 5 + Tailwind utility helpers (safe mix)
 * - Clean alerts, mobile-first layout, input validation
 * ========================================================== */

session_start();
require_once 'config.php'; // must set $conn = new mysqli(...)

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'employer') {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$message = "";

/* ---------- CSRF token ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- Fetch current profile ---------- */
$employer = [
    'company_name'        => '',
    'company_description' => '',
    'contact_person'      => '',
    'phone'               => '',
    'country'             => ''
];

if ($stmt = $conn->prepare("SELECT company_name, company_description, contact_person, phone, country FROM employers WHERE user_id = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $employer = $row;
    }
    $stmt->close();
}

/* ---------- Handle update ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $message = "<div class='alert alert-danger'>Security check failed. Please reload the page and try again.</div>";
    } else {
        // Basic sanitization
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');

        // Simple validation
        $errors = [];
        if ($contact_person === '') $errors[] = "Contact person is required.";
        // Accept +, spaces, parentheses and dashes; 7-20 total digits typical
        if ($phone === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors[] = "Enter a valid phone number.";
        }

        if (empty($errors)) {
            if ($up = $conn->prepare("UPDATE employers SET contact_person = ?, phone = ? WHERE user_id = ?")) {
                $up->bind_param("ssi", $contact_person, $phone, $user_id);
                if ($up->execute()) {
                    $message = "<div class='alert alert-success mb-0'>Profile updated successfully.</div>";
                    // Refresh data
                    if ($stmt = $conn->prepare("SELECT company_name, company_description, contact_person, phone, country FROM employers WHERE user_id = ? LIMIT 1")) {
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $employer = $row;
                        }
                        $stmt->close();
                    }
                } else {
                    $message = "<div class='alert alert-danger'>Failed to update profile. Please try again.</div>";
                }
                $up->close();
            } else {
                $message = "<div class='alert alert-danger'>Something went wrong. Please try later.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'><ul class='mb-0 ps-3'><li>" . implode("</li><li>", array_map('htmlspecialchars', $errors)) . "</li></ul></div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>MSJOBS — Edit Company Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tailwind (utility helpers) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{--brand:#2563eb;--brand-2:#0ea5e9;}
    body{font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';}
    .glass {
      background: linear-gradient(180deg, rgba(255,255,255,.7), rgba(255,255,255,.5));
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,.5);
    }
    .brand-gradient {
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
    }
    .btn-brand {
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      color: #fff;
      border: 0;
    }
    .btn-brand:hover { filter: brightness(0.95); color:#fff; }
  </style>
</head>

<body class="bg-slate-50">

<!-- Navbar -->
<nav class="bg-white/90 backdrop-blur border-b sticky top-0 z-50">
  <div class="container d-flex align-items-center justify-content-between py-2">
    <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none">
      <img src="img/MS copy.png" alt="MSJOBS" class="h-9 w-9 rounded shadow-sm" style="height:36px;width:36px;object-fit:cover;">
      <span class="fw-bold text-primary">MSJOBS</span>
    </a>
    <button class="btn btn-outline-secondary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav">
      <i class="bi bi-list"></i>
    </button>
    <div class="d-none d-md-flex align-items-center gap-3">
      <a href="about.php" class="text-secondary text-decoration-none">About</a>
      <a href="contact.html" class="text-secondary text-decoration-none">Contact</a>
      <a href="login.php" class="btn btn-brand btn-sm px-3">LOGIN <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
  </div>
  <div class="collapse" id="mobileNav">
    <div class="container pb-3 d-md-none">
      <a href="about.php" class="d-block py-2 text-secondary text-decoration-none">About</a>
      <a href="contact.html" class="d-block py-2 text-secondary text-decoration-none">Contact</a>
      <a href="login.php" class="btn btn-brand w-100 mt-2">LOGIN</a>
    </div>
  </div>
</nav>

<!-- Header band -->
<section class="brand-gradient text-white">
  <div class="container py-5">
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-building-check me-2"></i>Edit Company Profile</h1>
    <p class="mb-0 text-white/90">Keep your contact details up to date so candidates can reach you easily.</p>
  </div>
</section>

<!-- Main -->
<main class="container my-4 my-md-5">
  <div class="row g-4">
    <!-- Profile card (read-only bits) -->
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width:64px;height:64px;">
              <i class="bi bi-buildings text-primary fs-3"></i>
            </div>
            <div>
              <div class="fw-bold fs-5 mb-0"><?= htmlspecialchars($employer['company_name'] ?? ''); ?></div>
              <div class="text-muted small"><?= htmlspecialchars($employer['country'] ?? ''); ?></div>
            </div>
          </div>
          <div class="mb-2 fw-semibold">About Company</div>
          <p class="text-muted mb-0" style="white-space:pre-line;"><?= nl2br(htmlspecialchars($employer['company_description'] ?? '')); ?></p>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
          <a href="company.php" class="btn btn-outline-secondary w-50">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
          </a>
          <a href="jobs_posted.php" class="btn btn-outline-primary w-50">
            <i class="bi bi-briefcase me-1"></i> Jobs
          </a>
        </div>
      </div>
    </div>

    <!-- Edit form -->
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <?php if ($message): ?>
            <div class="mb-3"><?= $message ?></div>
          <?php endif; ?>

          <form method="POST" novalidate class="needs-validation">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="row g-3">
              <!-- Read-only fields shown but locked -->
              <div class="col-12">
                <label class="form-label fw-semibold">Company Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($employer['company_name'] ?? '') ?>" readonly>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Company Description</label>
                <textarea class="form-control" rows="4" readonly><?= htmlspecialchars($employer['company_description'] ?? '') ?></textarea>
              </div>

              <!-- Editable -->
              <div class="col-12 col-md-6">
                <label for="contact_person" class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                <input id="contact_person" name="contact_person" type="text" class="form-control" maxlength="120"
                       value="<?= htmlspecialchars($employer['contact_person'] ?? '') ?>" required>
                <div class="invalid-feedback">Please enter contact person.</div>
              </div>

              <div class="col-12 col-md-6">
                <label for="phone" class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                <input id="phone" name="phone" type="text" class="form-control" maxlength="20" inputmode="tel"
                       pattern="[0-9+\-\s()]{7,20}" value="<?= htmlspecialchars($employer['phone'] ?? '') ?>" required>
                <div class="form-text">Digits, +, (), - and spaces only.</div>
                <div class="invalid-feedback">Enter a valid phone number.</div>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Country</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($employer['country'] ?? '') ?>" readonly>
              </div>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-brand">
                <i class="bi bi-save me-1"></i> Update Profile
              </button>
              <a href="company.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
              </a>
            </div>
          </form>
        </div>
      </div>

      <!-- Tips -->
      <div class="alert alert-info mt-3 mb-0">
        <i class="bi bi-lightbulb me-1"></i>
        Tip: Use an official phone number to increase candidate trust.
      </div>
    </div>
  </div>
</main>

<!-- Footer -->
<footer class="bg-dark text-white-50 pt-5 mt-4">
  <div class="container">
    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-3">
        <h5 class="text-white mb-3">Company</h5>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">About Us</a>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">Contact Us</a>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">Our Services</a>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">Privacy Policy</a>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">Terms &amp; Conditions</a>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <h5 class="text-white mb-3">Quick Links</h5>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">Jobs</a>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">Companies</a>
        <a class="btn btn-link text-white-50 p-0 d-block" href="#">Salaries</a>
        <a class="btn btn-link text-white-50 p-0 d-block" href="Faq.php">FAQs</a>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <h5 class="text-white mb-3">Contact</h5>
        <p class="mb-2"><i class="bi bi-geo-alt me-2"></i>Office No. 09, Hapag-Lloyd Dubai - Middle East HQ, Al Garhoud Road, Deira, Dubai</p>
        <p class="mb-2"><i class="bi bi-telephone me-2"></i>0527212677</p>
        <p class="mb-2"><i class="bi bi-telephone me-2"></i>045729430</p>
        <p class="mb-0"><i class="bi bi-envelope me-2"></i>info@msjobs.net</p>
        <div class="d-flex gap-2 pt-2">
          <a class="btn btn-outline-light btn-sm" href="https://www.linkedin.com/company/ms-group-of-companies-uae/"><i class="bi bi-linkedin"></i></a>
          <a class="btn btn-outline-light btn-sm" href="https://www.tiktok.com/@mshr1992?_t=ZS-8w1OLGBJu7T&_r=1"><i class="bi bi-tiktok"></i></a>
          <a class="btn btn-outline-light btn-sm" href="https://www.instagram.com/ms_group2023?igsh=ZXVnYmN5dXJwNnZt"><i class="bi bi-instagram"></i></a>
          <a class="btn btn-outline-light btn-sm" href="https://www.facebook.com/share/1BqcPQMQEC/"><i class="bi bi-facebook"></i></a>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <h5 class="text-white mb-3">Newsletter</h5>
        <div class="input-group">
          <input type="email" class="form-control" placeholder="Your email" />
          <button class="btn btn-brand">Login</button>
        </div>
      </div>
    </div>
    <div class="border-top border-secondary mt-4 py-3 d-flex flex-column flex-md-row gap-2 justify-content-between">
      <div>&copy; <span class="text-white">MS JOBS</span>. All Rights Reserved.</div>
      <div>Designed by <span class="text-white">Vithu</span></div>
    </div>
  </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Bootstrap client-side validation
  (function () {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();
</script>
</body>
</html>
