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
      <i class="fa fa-shield-halved"></i>
      <span>Legal Information</span>
    </div>
    
    <h1 class="text-3xl md:text-4xl font-extrabold mb-8">Privacy Policy</h1>
    
    <div class="prose prose-slate max-w-none space-y-8">
      <section>
        <p class="text-slate-600 leading-relaxed italic border-l-4 border-slate-200 pl-4">
          Last updated: <?php echo date('F j, Y'); ?>
        </p>
        <p class="text-slate-600 leading-relaxed mt-4">
          Welcome to MSJOBS. We value your privacy and are committed to protecting your personal data. This policy explains what information we collect, how we use it, and how we protect it.
        </p>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">1</span>
          Information Collected
        </h2>
        <p class="text-slate-600 leading-relaxed">
          We may store your contact details (e.g., name, email, phone), resume/CV data, and preferences when you apply or register on the site.
        </p>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">2</span>
          Use of Your Data
        </h2>
        <p class="text-slate-600 leading-relaxed mb-4">
          Your information is usually used to:
        </p>
        <ul class="space-y-2 list-none">
          <li class="flex items-center gap-3 text-sm text-slate-700">
            <i class="fa fa-check text-emerald-500"></i>
            Help match you with job opportunities
          </li>
          <li class="flex items-center gap-3 text-sm text-slate-700">
            <i class="fa fa-check text-emerald-500"></i>
            Communicate important updates regarding your applications
          </li>
          <li class="flex items-center gap-3 text-sm text-slate-700">
            <i class="fa fa-check text-emerald-500"></i>
            Improve our platform and services
          </li>
        </ul>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">3</span>
          Data Sharing
        </h2>
        <p class="text-slate-600 leading-relaxed">
          Job sites usually only share personal data with employers or service partners when needed for your job application — not for marketing to unrelated companies.
        </p>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">4</span>
          Protection & Security
        </h2>
        <p class="text-slate-600 leading-relaxed">
          We implement security measures like encryption and access controls to keep personal data safe from unauthorized access.
        </p>
      </section>

      <section>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center text-sm">5</span>
          Your Rights
        </h2>
        <p class="text-slate-600 leading-relaxed">
          You normally can request access, correction, or deletion of your data, and sometimes opt‑out of promotional emails.
        </p>
      </section>

      <section class="bg-blue-50 rounded-2xl p-6 border border-blue-100">
        <h2 class="text-xl font-bold mb-2">Contact Us</h2>
        <p class="text-slate-600 text-sm">
          If you have any questions about this Privacy Policy, please contact us at 
          <a href="mailto:<?= site_setting('support_email','support@msjobs.net') ?>" class="text-brand font-bold"><?= site_setting('support_email','support@msjobs.net') ?></a>.
        </p>
      </section>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>


</body>
</html>
