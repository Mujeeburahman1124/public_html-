<?php
/**
 * admin_site_settings.php — MSJOBS Super Admin: Site Settings Management
 * Allows super admins to update contact info, map links, social links, and more.
 */
declare(strict_types=1);
session_start();

/* ===== Auth Guard ===== */
if (empty($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header('Location: admin_login.php'); exit;
}

require_once __DIR__ . '/settings_helper.php';

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_settings'])) {
    $_SESSION['csrf_settings'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_settings'];

/* ===== Handle POST save ===== */
$flash = null;
$flash_type = 'success';

// Show success flash after redirect
if (isset($_GET['saved'])) {
    $flash = 'Site settings saved successfully!';
    $flash_type = 'success';
}
if (isset($_GET['error'])) {
    $flash = 'Failed to save settings. Please check the database connection.';
    $flash_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($csrf, $submitted_csrf)) {
        $flash = 'Security check failed. Please reload and try again.';
        $flash_type = 'error';
    } else {
        $allowed_keys = array_keys(_settings_defaults());
        $to_save = [];
        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                $to_save[$key] = trim((string)$_POST[$key]);
            }
        }
        if (save_site_settings($to_save)) {
            // POST-Redirect-GET: redirect so the form reloads fresh from DB
            $_SESSION['csrf_settings'] = bin2hex(random_bytes(32));
            header('Location: admin_site_settings.php?saved=1');
            exit;
        } else {
            header('Location: admin_site_settings.php?error=1');
            exit;
        }
    }
}

/* ===== Load current settings ===== */
$s = get_site_settings();
$admin_username = $_SESSION['super_admin_username'] ?? 'Super Admin';

function sv(string $key): string {
    global $s;
    return htmlspecialchars((string)($s[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Site Settings — MSJOBS Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

  <!-- Tailwind & Icons -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <script>
    tailwind.config = {
      darkMode: ['class'],
      theme: {
        extend: {
          colors: {
            brand: '#0ea5e9', brandDark: '#0284c7', neon: '#22d3ee',
            base: {
              25:'#0b1220', 50:'#0f172a', 100:'#111827', 200:'#1f2937',
              300:'#334155', 400:'#475569', 500:'#64748b', 600:'#94a3b8',
              700:'#cbd5e1', 800:'#e2e8f0', 900:'#f8fafc'
            }
          },
          boxShadow: {
            glow: '0 10px 30px rgba(34,211,238,0.25)',
            soft: '0 8px 24px rgba(2,132,199,0.15)'
          },
          fontFamily: { inter: ['Inter','ui-sans-serif','system-ui'] }
        }
      }
    }
  </script>

  <style>
    :root {
      --card-glass: rgba(255,255,255,.08);
      --sidebar-glass: rgba(255,255,255,.06);
      --stroke: rgba(255,255,255,.12);
    }
    html, body { height: 100%; }
    body { font-family: "Inter", system-ui, -apple-system, Roboto, Arial, sans-serif; }
    .backdrop { backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
    .card {
      background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.05));
      border: 1px solid var(--stroke);
      border-radius: 16px;
    }
    .field-input {
      width: 100%;
      padding: 10px 14px;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 10px;
      color: #e2e8f0;
      font-size: 14px;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .field-input:focus {
      border-color: rgba(34,211,238,.5);
      box-shadow: 0 0 0 3px rgba(34,211,238,.12);
    }
    .field-input::placeholder { color: #475569; }
    .tab-btn { transition: all .2s; }
    .tab-btn.active {
      background: linear-gradient(90deg, rgba(14,165,233,.22), rgba(34,211,238,.10));
      border-color: rgba(34,211,238,.35);
      color: white;
    }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    @keyframes slideIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
    .animate-slide-in { animation: slideIn .3s ease-out both; }
    .save-btn {
      background: linear-gradient(135deg, #0ea5e9, #22d3ee);
      transition: all .2s;
    }
    .save-btn:hover { filter: brightness(1.1); box-shadow: 0 6px 20px rgba(34,211,238,.35); transform: translateY(-1px); }
    .save-btn:active { transform: translateY(0); }
  </style>
</head>

<body class="bg-base-25 dark [background-image:linear-gradient(180deg,#0b1220,40%,#0a0f1c)] min-h-screen text-base-800">
  <!-- Decorative glows -->
  <div class="pointer-events-none fixed -top-32 -left-24 h-80 w-80 rounded-full blur-3xl opacity-30"
       style="background:radial-gradient(closest-side,rgba(34,211,238,.35),transparent)"></div>
  <div class="pointer-events-none fixed -bottom-24 -right-16 h-72 w-72 rounded-full blur-3xl opacity-30"
       style="background:radial-gradient(closest-side,rgba(14,165,233,.35),transparent)"></div>

  <!-- Top Bar -->
  <header class="sticky top-0 z-30 backdrop" style="background:var(--card-glass);border-bottom:1px solid var(--stroke)">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 h-14 sm:h-16 flex items-center gap-3">
      <a href="SuperAdmin.php"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-base-700 hover:text-white hover:bg-white/10 transition text-sm"
         style="border-color:var(--stroke)">
        <i class="ri-arrow-left-s-line text-base"></i> Back to Dashboard
      </a>

      <div class="flex items-center gap-2 ml-2">
        <div class="h-8 w-8 rounded-lg grid place-items-center shadow-glow text-white text-sm"
             style="background:radial-gradient(circle at 30% 20%,#22d3ee,#0ea5e9)">
          <i class="ri-settings-3-fill"></i>
        </div>
        <div>
          <h1 class="text-base sm:text-lg font-extrabold tracking-tight text-white leading-tight">Site Settings</h1>
          <p class="text-[11px] text-base-600 hidden sm:block">Manage global contact info, map & social links</p>
        </div>
      </div>

      <div class="ml-auto text-sm text-base-600">
        Signed in as <span class="font-semibold text-white"><?= htmlspecialchars($admin_username) ?></span>
      </div>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

    <!-- Flash Message -->
    <?php if ($flash): ?>
    <div class="animate-slide-in mb-6 px-4 py-3 rounded-xl border text-sm font-semibold
      <?= $flash_type === 'success'
          ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-200'
          : 'bg-rose-500/15 border-rose-400/30 text-rose-200' ?>">
      <i class="<?= $flash_type === 'success' ? 'ri-check-double-line' : 'ri-alert-line' ?> mr-2"></i>
      <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="mb-6">
      <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Site Settings</h2>
      <p class="text-sm text-base-600 mt-1">
        Changes here are reflected live across all pages of the website including the header, footer, contact page, and maps.
      </p>
    </div>

    <!-- Tab Navigation -->
    <div class="flex flex-wrap gap-2 mb-6">
      <button class="tab-btn active px-4 py-2 rounded-xl border text-sm font-semibold" style="border-color:var(--stroke)"
              data-tab="general">
        <i class="ri-global-line mr-1.5"></i>General
      </button>
      <button class="tab-btn px-4 py-2 rounded-xl border text-sm font-semibold text-base-600" style="border-color:var(--stroke)"
              data-tab="contact">
        <i class="ri-phone-line mr-1.5"></i>Contact Info
      </button>
      <button class="tab-btn px-4 py-2 rounded-xl border text-sm font-semibold text-base-600" style="border-color:var(--stroke)"
              data-tab="map">
        <i class="ri-map-pin-line mr-1.5"></i>Map & Location
      </button>
      <button class="tab-btn px-4 py-2 rounded-xl border text-sm font-semibold text-base-600" style="border-color:var(--stroke)"
              data-tab="social">
        <i class="ri-share-line mr-1.5"></i>Social Links
      </button>
    </div>

    <!-- FORM wraps all tabs -->
    <form method="POST" action="" id="settingsForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

      <!-- ======================== TAB: General ======================== -->
      <div class="tab-panel active animate-slide-in" id="tab-general">
        <div class="card p-6 space-y-5">
          <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i class="ri-global-line text-neon"></i> General Settings
          </h3>

          <div class="grid sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Site Name</label>
              <input type="text" name="site_name" class="field-input" value="<?= sv('site_name') ?>" placeholder="MSJOBS">
            </div>
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Support Email</label>
              <input type="email" name="support_email" class="field-input" value="<?= sv('support_email') ?>" placeholder="support@msjobs.net">
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Site Tagline</label>
            <input type="text" name="site_tagline" class="field-input" value="<?= sv('site_tagline') ?>" placeholder="Recruitment made simple...">
          </div>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Support Hours</label>
            <input type="text" name="support_hours" class="field-input" value="<?= sv('support_hours') ?>" placeholder="Sun–Sat: 9:00 AM – 7:00 PM (Gulf Time)">
          </div>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Copyright Text (after "© Year")</label>
            <input type="text" name="copyright_text" class="field-input" value="<?= sv('copyright_text') ?>" placeholder="MSJOBS. All rights reserved.">
          </div>
        </div>
      </div>

      <!-- ======================== TAB: Contact ======================== -->
      <div class="tab-panel" id="tab-contact">
        <div class="card p-6 space-y-5">
          <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i class="ri-phone-line text-neon"></i> Contact Information
          </h3>

          <div class="grid sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Phone Number</label>
              <div class="relative">
                <i class="ri-phone-fill absolute left-3 top-1/2 -translate-y-1/2 text-base-500 text-sm"></i>
                <input type="text" name="contact_phone" class="field-input pl-9" value="<?= sv('contact_phone') ?>" placeholder="+971 58 597 4340">
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">WhatsApp Link (Full URL)</label>
              <div class="relative">
                <i class="fa-brands fa-whatsapp absolute left-3 top-1/2 -translate-y-1/2 text-emerald-400 text-sm"></i>
                <input type="url" name="contact_whatsapp" class="field-input pl-9" value="<?= sv('contact_whatsapp') ?>" placeholder="https://wa.me/971...">
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Full Address (used on Contact page)</label>
            <div class="relative">
              <i class="ri-map-pin-fill absolute left-3 top-3.5 text-base-500 text-sm"></i>
              <textarea name="contact_address" rows="2" class="field-input pl-9 resize-none" placeholder="Real Group Building, Ajman Industrial Area 2, UAE"><?= sv('contact_address') ?></textarea>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Short Address (used in footers)</label>
            <div class="relative">
              <i class="ri-map-pin-2-fill absolute left-3 top-1/2 -translate-y-1/2 text-base-500 text-sm"></i>
              <input type="text" name="contact_address_short" class="field-input pl-9" value="<?= sv('contact_address_short') ?>" placeholder="Short form for footer use">
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Support Email</label>
            <div class="relative">
              <i class="ri-mail-fill absolute left-3 top-1/2 -translate-y-1/2 text-base-500 text-sm"></i>
              <input type="email" name="support_email" class="field-input pl-9" value="<?= sv('support_email') ?>" placeholder="support@msjobs.net">
            </div>
            <p class="text-xs text-base-500 mt-1">Also editable from the General tab.</p>
          </div>
        </div>
      </div>

      <!-- ======================== TAB: Map ======================== -->
      <div class="tab-panel" id="tab-map">
        <div class="card p-6 space-y-5">
          <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i class="ri-map-pin-line text-neon"></i> Map & Location
          </h3>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Google Maps Embed URL</label>
            <p class="text-xs text-base-500 mb-2">
              Go to <strong class="text-base-600">Google Maps</strong> → Search your location → Share → Embed a map → Copy the <code class="bg-white/10 px-1 rounded text-neon">src="..."</code> URL from the iframe code.
            </p>
            <textarea name="map_embed_url" rows="3" class="field-input resize-none font-mono text-xs"
                      placeholder="https://www.google.com/maps?q=...&output=embed"><?= sv('map_embed_url') ?></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Map Location Label (shown above map)</label>
            <input type="text" name="map_label" class="field-input" value="<?= sv('map_label') ?>" placeholder="Ajman Industrial Area 2, Ajman, UAE">
          </div>

          <!-- Live Preview -->
          <div>
            <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">Map Preview</label>
            <div class="rounded-xl overflow-hidden border" style="border-color:var(--stroke)">
              <iframe id="mapPreview"
                      src="<?= sv('map_embed_url') ?>"
                      class="w-full" style="height:280px; border:0;" loading="lazy"
                      referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            <button type="button" onclick="refreshMapPreview()"
                    class="mt-2 px-3 py-1.5 text-xs rounded-lg bg-white/10 border hover:bg-white/15 transition text-base-700"
                    style="border-color:var(--stroke)">
              <i class="ri-refresh-line mr-1"></i>Refresh Preview
            </button>
          </div>
        </div>
      </div>

      <!-- ======================== TAB: Social ======================== -->
      <div class="tab-panel" id="tab-social">
        <div class="card p-6 space-y-5">
          <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i class="ri-share-line text-neon"></i> Social Media Links
          </h3>
          <p class="text-xs text-base-500">Leave blank to hide that icon from the website. Enter full URLs (https://...).</p>

          <div class="grid sm:grid-cols-2 gap-5">
            <!-- Facebook -->
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">
                <i class="fab fa-facebook text-blue-400 mr-1"></i>Facebook
              </label>
              <input type="url" name="social_facebook" class="field-input" value="<?= sv('social_facebook') ?>"
                     placeholder="https://facebook.com/...">
            </div>

            <!-- TikTok -->
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">
                <i class="fab fa-tiktok mr-1"></i>TikTok
              </label>
              <input type="url" name="social_tiktok" class="field-input" value="<?= sv('social_tiktok') ?>"
                     placeholder="https://tiktok.com/@...">
            </div>

            <!-- Twitter/X -->
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">
                <i class="fab fa-x-twitter mr-1"></i>Twitter / X
              </label>
              <input type="url" name="social_twitter" class="field-input" value="<?= sv('social_twitter') ?>"
                     placeholder="https://x.com/...">
            </div>

            <!-- LinkedIn -->
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">
                <i class="fab fa-linkedin text-sky-400 mr-1"></i>LinkedIn
              </label>
              <input type="url" name="social_linkedin" class="field-input" value="<?= sv('social_linkedin') ?>"
                     placeholder="https://linkedin.com/company/...">
            </div>

            <!-- Instagram -->
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">
                <i class="fab fa-instagram text-pink-400 mr-1"></i>Instagram
              </label>
              <input type="url" name="social_instagram" class="field-input" value="<?= sv('social_instagram') ?>"
                     placeholder="https://instagram.com/...">
            </div>

            <!-- YouTube -->
            <div>
              <label class="block text-xs font-semibold text-base-600 mb-1.5 uppercase tracking-wider">
                <i class="fab fa-youtube text-red-400 mr-1"></i>YouTube
              </label>
              <input type="url" name="social_youtube" class="field-input" value="<?= sv('social_youtube') ?>"
                     placeholder="https://youtube.com/...">
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button (always visible) -->
      <div class="mt-6 flex justify-end">
        <button type="submit" class="save-btn inline-flex items-center gap-2 px-6 py-3 rounded-full text-white font-bold shadow-glow text-sm">
          <i class="ri-save-3-fill text-lg"></i>
          Save All Settings
        </button>
      </div>
    </form>

  </main>

  <footer class="mt-10 py-6 text-center text-xs text-base-500" style="border-top:1px solid var(--stroke)">
    © <?= date('Y') ?> MSJOBS — Admin Console
  </footer>

<script>
  /* ===== Tab switching ===== */
  const tabBtns  = document.querySelectorAll('.tab-btn');
  const tabPanels = document.querySelectorAll('.tab-panel');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;

      tabBtns.forEach(b => b.classList.remove('active'));
      tabPanels.forEach(p => p.classList.remove('active'));

      btn.classList.add('active');
      const panel = document.getElementById('tab-' + tab);
      if (panel) { panel.classList.add('active'); panel.classList.add('animate-slide-in'); }
    });
  });

  /* ===== Map preview refresh ===== */
  function refreshMapPreview() {
    const urlInput = document.querySelector('[name="map_embed_url"]');
    const iframe   = document.getElementById('mapPreview');
    if (urlInput && iframe) {
      iframe.src = urlInput.value.trim();
    }
  }

  /* ===== Auto-save indicator ===== */
  const form = document.getElementById('settingsForm');
  form?.addEventListener('submit', function() {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.innerHTML = '<i class="ri-loader-4-line animate-spin text-lg"></i> Saving…';
      btn.disabled = true;
    }
  });
</script>
</body>
</html>
