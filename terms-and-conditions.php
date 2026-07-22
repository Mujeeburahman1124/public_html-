<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/settings_helper.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?= h(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= h(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    .gradient-text {
      background: linear-gradient(135deg, #0156D4, #00A7B7);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
  </style>
</head>
<body class="bg-white text-slate-900 font-sans">

<?php require_once __DIR__ . '/header.php'; ?>


<main class="max-w-4xl mx-auto px-4 py-20">
  <h1 class="text-4xl font-black mb-4 gradient-text">Terms & Conditions</h1>
  <p class="text-slate-500 mb-12">Please read these rules carefully before using our platform.</p>
  
  <div class="space-y-12">
    <!-- Section 1 -->
    <section class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-1">
        <h2 class="text-lg font-bold uppercase tracking-widest text-brand">01. Acceptance</h2>
      </div>
      <div class="md:col-span-2">
        <p class="text-slate-600 leading-relaxed">
          When you use the site — whether browsing jobs, posting your CV, or applying to roles — you agree to follow the rules laid out in these Terms.
        </p>
      </div>
    </section>

    <!-- Section 2 -->
    <section class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-1">
        <h2 class="text-lg font-bold uppercase tracking-widest text-brand">02. Obligations</h2>
      </div>
      <div class="md:col-span-2">
        <p class="text-slate-600 leading-relaxed">
          You must provide accurate information about yourself (e.g., correct email, resume details) and not misuse the platform (like posting false job listings or offensive content).
        </p>
      </div>
    </section>

    <!-- Section 3 -->
    <section class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-1">
        <h2 class="text-lg font-bold uppercase tracking-widest text-brand">03. Accounts</h2>
      </div>
      <div class="md:col-span-2">
        <p class="text-slate-600 leading-relaxed">
          If you create an account, you’ll be responsible for keeping your login information secure and won’t share it with others.
        </p>
      </div>
    </section>

    <!-- Section 4 -->
    <section class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-1">
        <h2 class="text-lg font-bold uppercase tracking-widest text-brand">04. Content</h2>
      </div>
      <div class="md:col-span-2">
        <p class="text-slate-600 leading-relaxed">
          The site owns the rights to its job descriptions, blog posts, videos, and user interface. You can use them only for personal job searches or career planning.
        </p>
      </div>
    </section>

    <!-- Section 5 -->
    <section class="grid md:grid-cols-3 gap-6 border-t pt-12">
      <div class="md:col-span-1">
        <h2 class="text-lg font-bold uppercase tracking-widest text-red-500">05. Liability</h2>
      </div>
      <div class="md:col-span-2">
        <p class="text-slate-600 leading-relaxed mb-4">
          The job portal usually disclaims responsibility if:
        </p>
        <ul class="space-y-3 text-sm text-slate-500 italic">
          <li>- A job turns out to be inaccurate</li>
          <li>- You don’t get selected for a job</li>
          <li>- Any mistakes occur in the listings</li>
        </ul>
      </div>
    </section>

    <!-- Section 6 -->
    <section class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-1">
        <h2 class="text-lg font-bold uppercase tracking-widest text-brand">06. Changes</h2>
      </div>
      <div class="md:col-span-2">
        <p class="text-slate-600 leading-relaxed">
          The Terms may be updated occasionally. By continuing to use the site, you accept those changes.
        </p>
      </div>
    </section>

    <!-- Section 7 -->
    <section class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-1">
        <h2 class="text-lg font-bold uppercase tracking-widest text-brand">07. Compliance</h2>
      </div>
      <div class="md:col-span-2">
        <p class="text-slate-600 leading-relaxed">
          You agree to follow local laws and not use the site for illegal activity.
        </p>
      </div>
    </section>

  </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>


</body>
</html>
