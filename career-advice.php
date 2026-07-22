<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/settings_helper.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8" />
  <title><?= h(site_setting_raw('site_name', 'MSJOBS')) ?> — <?= h(site_setting_raw('site_tagline', 'Recruitment made simple')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Get expert career advice and job search tips to land your dream job. Explore resume tips, interview prep, and more.">
  <link rel="icon" type="image/png" href="img/1748025713_MS copy.png" />
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ["Inter","system-ui","Segoe UI","Roboto","Arial","sans-serif"] },
          colors: {
            brand:"#0156D4",
            brandDark:"#0B3C8C",
            brandAlt:"#00A7B7",
            ink:"#0F172A",
          },
          boxShadow: {
            card: "0 4px 12px rgba(15,23,42,.05)",
            hover: "0 10px 32px rgba(2,6,23,.12)",
          }
        }
      }
    }
  </script>
  
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  
  <style>
    .gradient-text {
      background: linear-gradient(135deg, #0156D4, #00A7B7);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .hero-bg {
      background: radial-gradient(1200px 600px at 70% -10%, rgba(1,86,212,.12), transparent 60%), linear-gradient(180deg, #F8FAFF 0%, #FFFFFF 35%);
    }
    .section-card {
      transition: all 0.3s ease;
    }
    .section-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 32px rgba(2,6,23,.12);
    }
  </style>
</head>
<body class="bg-white text-ink font-sans flex flex-col min-h-screen">

<?php require_once __DIR__ . '/header.php'; ?>


<main class="flex-grow hero-bg">
  <!-- Hero Section -->
  <section class="max-w-4xl mx-auto px-4 pt-12 pb-8 text-center">
    <div class="inline-flex items-center gap-2 text-sm font-bold text-brandAlt bg-brandAlt/10 rounded-full px-4 py-2 mb-6">
      <i class="fa fa-lightbulb"></i>
      <span>Elevate Your Career</span>
    </div>
    <h1 class="text-3xl md:text-5xl font-extrabold mb-6 leading-tight">
      Professional Advice to <br><span class="gradient-text">Accelerate Your Success</span>
    </h1>
    <p class="text-slate-600 text-lg md:text-xl max-w-2xl mx-auto mb-12">
      Whether you're starting your first job or looking to make a major career move, our expert tips cover everything you need to stand out.
    </p>
  </section>

  <!-- Advice Sections -->
  <section class="max-w-7xl mx-auto px-4 py-12">
    <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
      
      <!-- 1. Know Yourself -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-user-gear"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">1. Know Yourself</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Identify your strengths, skills, and interests to target jobs more effectively.
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Understand what roles and industries match your profile.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Self-awareness helps you focus on the right opportunities.</span>
          </li>
        </ul>
      </div>

      <!-- 2. Optimize CV -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-blue-50 text-brand rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-file-pen"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">2. Optimize Your CV</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Make it clear, concise, and tailored. Highlight achievements, not just duties.
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Include keywords from job descriptions to pass ATS screening.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Customized resumes get more interviews.</span>
          </li>
        </ul>
      </div>

      <!-- 3. Cover Letter -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-cyan-50 text-brandAlt rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-envelope-open-text"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">3. Write a Strong Cover Letter</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Explain why you’re a fit for the role. Show motivation and real enthusiasm.
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Keep it short, personalized, and far from generic.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Show how you solve the employer's specific problems.</span>
          </li>
        </ul>
      </div>

      <!-- 4. Interviews -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-comments"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">4. Prepare for Interviews</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Practice common questions and research the company deeply before.
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Dress professionally and communicate with confidence.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Know your "Why this role?" and your core strengths.</span>
          </li>
        </ul>
      </div>

      <!-- 5. Skills -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-graduation-cap"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">5. Develop Your Skills</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Keep learning through certifications, online courses, and local workshops.
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Build soft skills like teamwork and problem-solving.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Stay up-to-date with industry-relevant technologies.</span>
          </li>
        </ul>
      </div>

      <!-- 6. Network -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-network-wired"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">6. Network</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Connect on LinkedIn and attend industry events or local job fairs.
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Networking often uncovers the "hidden" job market.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Build genuine professional relationships for longevity.</span>
          </li>
        </ul>
      </div>

      <!-- 7. Job Portals -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-magnifying-glass-chart"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">7. Use Job Portals Effectively</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Maximize your presence on MSJOBS.net with a complete profile.
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Set job alerts for roles matching your specific skills.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Apply quickly to relevant vacancies – speed matters.</span>
          </li>
        </ul>
      </div>

      <!-- 8. Set Goals -->
      <div class="section-card bg-white rounded-2xl p-6 border border-slate-100 shadow-card">
        <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center mb-5 text-xl">
          <i class="fa fa-bullseye"></i>
        </div>
        <h3 class="text-lg font-bold mb-3 text-slate-900">8. Set Career Goals</h3>
        <p class="text-slate-600 text-sm leading-relaxed mb-4">
          Plan for the short-term (1 yr), mid-term (3 yrs), and long-term (5+ yrs).
        </p>
        <ul class="space-y-2 text-xs text-slate-700">
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Identify actionable steps for growth into leadership.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa fa-check text-emerald-500 mt-0.5"></i>
            <span>Regularly review and adjust your path as you evolve.</span>
          </li>
        </ul>
      </div>

      <!-- CTA Card -->
      <div class="lg:col-span-2 xl:col-span-4 bg-gradient-to-br from-brand to-brandDark rounded-2xl p-8 text-white shadow-hover flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
          <h3 class="text-2xl font-bold mb-2 text-center md:text-left">Ready to Apply?</h3>
          <p class="opacity-90 text-center md:text-left">Put these tips into action and find your next opportunity on MSJOBS.</p>
        </div>
        <a href="index.php" class="bg-white text-brand px-8 py-3 rounded-full font-bold hover:bg-slate-50 transition-colors whitespace-nowrap">
          Browse All Jobs
        </a>
      </div>

    </div>
  </section>

  <!-- Newsletter -->
  <section class="max-w-7xl mx-auto px-4 py-20">
    <div class="bg-slate-900 rounded-3xl p-8 md:p-12 lg:p-16 text-center relative overflow-hidden">
      <div class="absolute top-0 right-0 w-64 h-64 bg-brand/10 blur-3xl -mr-32 -mt-32"></div>
      <div class="relative z-10">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">Get More Advice in Your In-box</h2>
        <p class="text-slate-400 mb-8 max-w-lg mx-auto">Join our newsletter to receive the latest career trends and exclusive job search tips weekly.</p>
        <form class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
          <input type="email" placeholder="Your email address" class="bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-brand flex-grow">
          <button type="submit" class="bg-brand hover:bg-brandDark text-white font-bold px-6 py-3 rounded-xl transition-all">
            Subscribe
          </button>
        </form>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>


<script>
  // Mobile Menu Toggle
  const mobileBtn = document.getElementById('mobileBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  
  if (mobileBtn && mobileMenu) {
    mobileBtn.addEventListener('click', () => {
      mobileMenu.classList.toggle('hidden');
    });
  }
</script>

</body>
</html>
