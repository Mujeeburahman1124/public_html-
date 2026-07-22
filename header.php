<?php
/**
 * header.php — Unified Global Header for MSJOBS
 * Loaded across all public-facing pages.
 */
require_once __DIR__ . '/settings_helper.php';

$_header_site_name = site_setting('site_name', 'MSJOBS');
?>
<header class="sticky top-0 z-50 border-b bg-white/95 backdrop-blur-md shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between">
    <a href="index.php" class="flex items-center gap-2 sm:gap-3 group">
      <div class="h-8 w-8 sm:h-9 sm:w-9 rounded-lg bg-gradient-to-br from-brand to-brandAlt flex items-center justify-center flex-shrink-0">
        <img src="img/1748025713_MS copy.png" alt="<?= $_header_site_name ?> Logo" class="h-5 w-5 sm:h-6 sm:w-6 object-contain" loading="eager">
      </div>
      <span class="font-bold text-base sm:text-xl gradient-text whitespace-nowrap"><?= $_header_site_name ?></span>
    </a>

    <!-- Desktop Nav -->
    <nav class="hidden md:flex items-center gap-6 text-sm">
      <a href="index.php" class="text-brand font-semibold border-b-2 border-brand pb-1">Jobs</a>
      <a href="CompanyProfile.php" class="text-slate-600 hover:text-brand transition-colors">About Us</a>
      <a href="career-advice.php" class="text-slate-600 hover:text-brand transition-colors">Career Advice</a>
      <a href="blog.php" class="text-slate-600 hover:text-brand transition-colors">Blogs</a>
    </nav>

    <div class="hidden md:flex items-center gap-3 text-sm">
      <a href="login.php" class="pill bg-brand hover:bg-brandDark text-white px-5 py-2.5 font-semibold transition-all">Sign in</a>
      <a href="login.php" class="text-slate-700 hover:text-brand transition-colors whitespace-nowrap">For Employers</a>
    </div>

    <!-- Mobile hamburger -->
    <button id="mobileMenuButton"
            class="block md:hidden inline-flex items-center justify-center h-9 w-9 rounded-lg border border-slate-200 text-ink hover:bg-slate-50"
            aria-label="Open menu" aria-controls="mobileMenu" aria-expanded="false" type="button">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
           viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
      <span class="sr-only">Toggle menu</span>
    </button>
  </div>

  <!-- Mobile Nav Panel -->
  <div id="mobileMenu" class="md:hidden hidden border-t bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
      <nav class="flex flex-col gap-2 text-sm">
        <a href="index.php" class="px-3 py-2 rounded-lg hover:bg-slate-50">Jobs</a>
        <a href="CompanyProfile.php" class="px-3 py-2 rounded-lg hover:bg-slate-50">About Us</a>
        <a href="career-advice.php" class="px-3 py-2 rounded-lg hover:bg-slate-50">Career Advice</a>
        <a href="blog.php" class="px-3 py-2 rounded-lg hover:bg-slate-50">Blogs</a>
        <div class="h-px bg-slate-200 my-2"></div>
        <a href="login.php" class="px-3 py-2 rounded-lg bg-brand text-white text-center">Sign in</a>
        <a href="login.php" class="px-3 py-2 rounded-lg hover:bg-slate-50 text-center">For Employers</a>
      </nav>
    </div>
  </div>
</header>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('mobileMenuButton');
    const menu = document.getElementById('mobileMenu');
    if(btn && menu) {
      btn.addEventListener('click', () => {
        const isHidden = menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', String(isHidden));
        btn.setAttribute('aria-label', isHidden ? 'Close menu' : 'Open menu');
      });

      // Close on link click
      menu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
          menu.classList.add('hidden');
          btn.setAttribute('aria-expanded', 'false');
          btn.setAttribute('aria-label', 'Open menu');
        });
      });
    }
  });
</script>
