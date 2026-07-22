<?php
/**
 * footer.php — Unified Global Footer for MSJOBS
 * Loaded across all public-facing pages.
 */
require_once __DIR__ . '/settings_helper.php';

// Load dynamic settings for footer
$_footer_name     = site_setting_raw('site_name', 'MSJOBS');
$_footer_tiktok   = site_setting_raw('social_tiktok');
$_footer_facebook = site_setting_raw('social_facebook');
$_footer_twitter  = site_setting_raw('social_twitter');
$_footer_linkedin = site_setting_raw('social_linkedin');
$_footer_instagram= site_setting_raw('social_instagram');
$_footer_youtube  = site_setting_raw('social_youtube');
$_footer_address  = site_setting_raw('contact_address_short', 'Real Group Building, Ajman Industrial Area 2, UAE');
$_footer_email    = site_setting_raw('support_email', 'support@msjobs.net');
$_footer_copy     = site_setting_raw('copyright_text', 'MSJOBS. All rights reserved.');

if (!function_exists('h')) {
    function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
<footer class="border-t bg-slate-900 text-white mt-auto">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 sm:py-9">
    <div class="grid gap-6 sm:gap-8 md:grid-cols-4 text-[11px] sm:text-sm">
      <div>
        <div class="font-bold text-lg"><?= h($_footer_name) ?></div>
        <p class="text-slate-400 mt-2 text-xs sm:text-sm italic"><?= h($_footer_address) ?></p>
        <?php if ($_footer_email): ?>
        <a href="mailto:<?= h($_footer_email) ?>" class="text-slate-400 hover:text-white text-xs mt-1 block transition-colors"><?= h($_footer_email) ?></a>
        <?php endif; ?>
      </div>
      <div>
        <div class="font-bold mb-3">For Jobseekers</div>
        <ul class="space-y-2 text-slate-300">
          <li><a class="hover:text-white" href="index.php">Browse Jobs</a></li>
          <li><a class="hover:text-white" href="career-advice.php">Career Advice</a></li>
          <li><a class="hover:text-white" href="blog.php">Latest Blogs</a></li>
        </ul>
      </div>
      <div>
        <div class="font-bold mb-3">For Employers</div>
        <ul class="space-y-2 text-slate-300">
          <li><a class="hover:text-white" href="login.php">Post a Job</a></li>
          <li><a class="hover:text-white" href="CompanyProfile.php">Company Profiles</a></li>
        </ul>
      </div>
      <div>
        <div class="font-bold mb-3">Legal</div>
        <ul class="space-y-2 text-slate-300">
          <li><a class="hover:text-white" href="privacy-policy.php">Privacy Policy</a></li>
          <li><a class="hover:text-white" href="terms-and-conditions.php">Terms &amp; Conditions</a></li>
          <li><a class="hover:text-white" href="cookies.php">Cookie Policy</a></li>
          <li><a class="hover:text-white" href="contact.php">Contact Us</a></li>
        </ul>
      </div>
    </div>

    <div class="mt-6 sm:mt-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between text-[11px] sm:text-xs text-slate-400">
      <div class="flex flex-wrap items-center gap-2 sm:gap-4">
        <span>🛡️ Secure &amp; Trusted</span>
        <span>✔️ Verified Employers</span>
        <span>⚡ Fast Hiring</span>
      </div>

      <!-- Dynamic Social Icons -->
      <div class="flex items-center gap-2 sm:gap-3">
        <?php if ($_footer_tiktok): ?>
        <a href="<?= h($_footer_tiktok) ?>" target="_blank" rel="noopener noreferrer" aria-label="MSJOBS on TikTok"
           class="inline-flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors border border-white/10">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="h-4 w-4 sm:h-5 sm:w-5" fill="currentColor" aria-hidden="true">
            <path d="M44 17.3c-4.4-.7-8.2-3.2-10.7-6.7v17.5c0 6.8-5.6 12.3-12.5 12.3S8.3 34.9 8.3 28c0-6.9 5.5-12.5 12.3-12.5 1.3 0 2.6.2 3.8.6v6.3a6.5 6.5 0 0 0-3.8-1.2c-3.6 0-6.6 3-6.6 6.7s3 6.6 6.6 6.6 6.7-2.9 6.7-6.6V4h6.3c1 5.4 5.2 9.7 10.4 10.8v6.5z"/>
          </svg>
        </a>
        <?php endif; ?>
        <?php if ($_footer_facebook): ?>
        <a href="<?= h($_footer_facebook) ?>" target="_blank" rel="noopener noreferrer" aria-label="MSJOBS on Facebook"
           class="inline-flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors border border-white/10">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 sm:h-5 sm:w-5" fill="currentColor" aria-hidden="true">
            <path d="M22 12.06C22 6.49 17.52 2 12 2S2 6.49 2 12.06C2 17.05 5.66 21.13 10.44 22v-7.02H7.9v-2.92h2.54V9.41c0-2.5 1.49-3.88 3.77-3.88 1.09 0 2.23.2 2.23.2v2.45h-1.25c-1.23 0-1.62.77-1.62 1.55v1.86h2.76l-.44 2.92h-2.32V22C18.34 21.13 22 17.05 22 12.06z"/>
          </svg>
        </a>
        <?php endif; ?>
        <?php if ($_footer_twitter): ?>
        <a href="<?= h($_footer_twitter) ?>" target="_blank" rel="noopener noreferrer" aria-label="MSJOBS on Twitter/X"
           class="inline-flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors border border-white/10">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 sm:h-5 sm:w-5" fill="currentColor" aria-hidden="true">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.622 5.905-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
          </svg>
        </a>
        <?php endif; ?>
        <?php if ($_footer_linkedin): ?>
        <a href="<?= h($_footer_linkedin) ?>" target="_blank" rel="noopener noreferrer" aria-label="MSJOBS on LinkedIn"
           class="inline-flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors border border-white/10">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 sm:h-5 sm:w-5" fill="currentColor" aria-hidden="true">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
          </svg>
        </a>
        <?php endif; ?>
        <?php if ($_footer_instagram): ?>
        <a href="<?= h($_footer_instagram) ?>" target="_blank" rel="noopener noreferrer" aria-label="MSJOBS on Instagram"
           class="inline-flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors border border-white/10">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 sm:h-5 sm:w-5" fill="currentColor" aria-hidden="true">
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
          </svg>
        </a>
        <?php endif; ?>
        <?php if ($_footer_youtube): ?>
        <a href="<?= h($_footer_youtube) ?>" target="_blank" rel="noopener noreferrer" aria-label="MSJOBS on YouTube"
           class="inline-flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors border border-white/10">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 sm:h-5 sm:w-5" fill="currentColor" aria-hidden="true">
            <path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>
          </svg>
        </a>
        <?php endif; ?>
      </div>

      <div class="text-center md:text-right">&copy; <?= date('Y') ?> <?= h($_footer_copy) ?></div>
    </div>
  </div>
</footer>
