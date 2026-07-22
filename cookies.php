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
<body class="bg-slate-50 text-slate-900 font-sans">

<?php require_once __DIR__ . '/header.php'; ?>


<main class="max-w-4xl mx-auto px-4 py-12">
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 md:p-12">
    <div class="inline-flex items-center gap-2 text-sm font-bold text-brand bg-blue-50 rounded-full px-4 py-2 mb-6">
      <i class="fa fa-cookie-bite"></i>
      <span>Cookies Policy</span>
    </div>
    
    <h1 class="text-3xl md:text-4xl font-extrabold mb-8">How We Use Cookies</h1>
    
    <div class="prose prose-slate max-w-none space-y-8">
      <section>
        <p class="text-slate-600 leading-relaxed italic border-l-4 border-slate-200 pl-4">
          Last updated: <?php echo date('F j, Y'); ?>
        </p>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">1</span>
          What are Cookies?
        </h2>
        <p class="text-slate-600 leading-relaxed">
          Cookies are small text files stored on your device when you visit websites. They help the site recognize you and provide a better experience.
        </p>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">2</span>
          How We Use Cookies
        </h2>
        <div class="grid sm:grid-cols-2 gap-4 mt-6">
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <h3 class="font-bold mb-2 text-brand">Essential Cookies</h3>
            <p class="text-sm text-slate-600">Required for basic site functionality like account login and application forms.</p>
          </div>
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <h3 class="font-bold mb-2 text-brand">Analytics Cookies</h3>
            <p class="text-sm text-slate-600">Track how visitors use the site to help us improve our services and layout.</p>
          </div>
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <h3 class="font-bold mb-2 text-brand">Functional Cookies</h3>
            <p class="text-sm text-slate-600">Remember your settings, preferences, and personalized features.</p>
          </div>
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
            <h3 class="font-bold mb-2 text-brand">Advertising Cookies</h3>
            <p class="text-sm text-slate-600">Used to show relevant job ads and offers based on your browsing history.</p>
          </div>
        </div>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">3</span>
          Your Options
        </h2>
        <p class="text-slate-600 leading-relaxed mb-4">
          You have control over your cookie preferences:
        </p>
        <ul class="space-y-3">
          <li class="flex items-start gap-3">
            <i class="fa fa-circle-check text-emerald-500 mt-1"></i>
            <span class="text-slate-700 text-sm"><strong>Accept All:</strong> Full site functionality and personalization.</span>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa fa-circle-check text-emerald-500 mt-1"></i>
            <span class="text-slate-700 text-sm"><strong>Manage/Reject:</strong> Limit tracking (some features may be restricted).</span>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa fa-circle-check text-emerald-500 mt-1"></i>
            <span class="text-slate-700 text-sm"><strong>Clear Cookies:</strong> Manually remove stored data from your browser.</span>
          </li>
        </ul>
      </section>

      <section class="bg-blue-50 rounded-2xl p-6 border border-blue-100">
        <h2 class="text-xl font-bold mb-2">Questions?</h2>
        <p class="text-slate-600 text-sm">
          If you have questions about our use of cookies, please contact us at 
          <a href="mailto:support@msjobs.net" class="text-brand font-bold">support@msjobs.net</a>.
        </p>
      </section>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>


</body>
</html>
