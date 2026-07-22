<?php
// ==== DB Connection (unchanged logic) ====
$conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ==== Handle limit updates (unchanged logic) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limits'])) {
    foreach ($_POST['limits'] as $employer_id => $limit) {
        $employer_id = intval($employer_id);
        $limit = intval($limit);

        $check = $conn->query("SELECT 1 FROM employer_limits WHERE employer_id = $employer_id");
        if ($check && $check->num_rows > 0) {
            $conn->query("UPDATE employer_limits SET view_limit = $limit WHERE employer_id = $employer_id");
        } else {
            $conn->query("INSERT INTO employer_limits (employer_id, view_limit) VALUES ($employer_id, $limit)");
        }
    }
    echo "<script>alert('Limits updated successfully'); window.location.href='admin_employer_limits.php';</script>";
    exit();
}

// ==== Fetch employer limits (unchanged SQL) ====
$limitSql = "
    SELECT 
        e.user_id AS employer_id,
        e.company_name,
        COALESCE(l.view_limit, 0) AS view_limit,
        (SELECT COUNT(*) FROM jobseeker_views WHERE employer_id = e.user_id) AS viewed_count
    FROM employers e
    LEFT JOIN employer_limits l ON e.user_id = l.employer_id
    ORDER BY e.company_name ASC, e.user_id ASC
";
$limitResult = $conn->query($limitSql);

// ==== Fetch jobseeker view summary (unchanged SQL) ====
$summarySql = "
    SELECT 
        jv.id,
        jv.employer_id,
        e.company_name,
        jv.jobseeker_id,
        jv.viewed_at
    FROM jobseeker_views jv
    LEFT JOIN employers e ON jv.employer_id = e.user_id
    ORDER BY jv.viewed_at DESC
";
$summaryResult = $conn->query($summarySql);

// Buffer rows so we can reuse for multiple render modes
$rows = [];
if ($limitResult && $limitResult->num_rows > 0) {
    while ($r = $limitResult->fetch_assoc()) { $rows[] = $r; }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <title>MSJOBS • Employer View Limits & Summary</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter', 'ui-sans-serif', 'system-ui'] },
          boxShadow: {
            'elev': '0 10px 30px rgba(2, 6, 23, 0.25)',
            'inset': 'inset 0 1px 0 0 rgba(255,255,255,.08)'
          },
          animation: {
            'fade-in': 'fadeIn .5s ease both',
            'floaty': 'floaty 6s ease-in-out infinite',
          },
          keyframes: {
            fadeIn: { '0%': {opacity:0, transform:'translateY(4px)'}, '100%': {opacity:1, transform:'translateY(0)'} },
            floaty: { '0%': { transform:'translateY(0)' }, '50%': { transform:'translateY(-6px)' }, '100%': { transform:'translateY(0)' } },
          },
          colors: {
            brand: {
              50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',400:'#818cf8',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81'
            }
          }
        }
      },
      darkMode: 'class'
    }
  </script>
  <style>
    .glass { backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); background: rgba(255,255,255,0.65); }
    .dark .glass { background: rgba(17, 24, 39, 0.55); }
    .neo { background: radial-gradient(1200px 600px at 10% -10%, rgba(99,102,241,.25), transparent 60%),
                      radial-gradient(800px 400px at 110% 0%, rgba(16,185,129,.18), transparent 60%),
                      linear-gradient(135deg, #0ea5e9, #6366f1 50%, #8b5cf6); }
    .ring-neo { box-shadow: 0 0 0 1px rgba(99,102,241,.25), 0 10px 30px rgba(2,6,23,.25); }
  </style>
</head>

<body class="min-h-screen bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-display">
  <!-- Topbar -->
  <header class="neo text-white">
    <div class="max-w-7xl mx-auto px-6 py-6 flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center shadow-elev animate-floaty">
          <!-- briefcase icon -->
          <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' class='w-6 h-6'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M9 7V6a3 3 0 013-3h0a3 3 0 013 3v1m-9 4h12m-13 0a2 2 0 00-2 2v5a2 2 0 002 2h14a2 2 0 002-2v-5a2 2 0 00-2-2m-13 0V9a2 2 0 012-2h10a2 2 0 012 2v2'/></svg>
        </div>
        <div>
          <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Employer View Controls</h1>
          <p class="text-white/85">Premium admin panel to manage profile view limits & usage.</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <a href="SuperAdmin.php" class="rounded-xl bg-white/15 hover:bg-white/25 px-4 py-2 text-sm font-semibold shadow-elev">Home</a>
        <a href="admin_dashboard.php" class="rounded-xl bg-white/15 hover:bg-white/25 px-4 py-2 text-sm font-semibold shadow-elev">Admin</a>
        <button id="themeToggle" class="rounded-xl bg-white/15 hover:bg-white/25 px-3 py-2 text-sm font-semibold shadow-elev" title="Toggle dark mode">🌙</button>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-6 py-10 space-y-10">

    <!-- Controls Row -->
    <div class="glass ring-neo rounded-2xl px-4 sm:px-6 py-4 flex flex-col md:flex-row gap-4 md:items-center md:justify-between animate-fade-in">
      <div class="flex items-center gap-3">
        <div class="hidden sm:block text-brand-200">▶</div>
        <div class="font-semibold">Quick Tools</div>
      </div>
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative">
          <input id="searchBox" type="text" placeholder="Search company or employer ID..." class="w-72 max-w-full rounded-xl border border-white/40 bg-white/70 dark:bg-slate-800/60 px-4 py-2.5 text-sm shadow focus:outline-none focus:ring-2 focus:ring-brand-400" />
          <span class="pointer-events-none absolute right-3 top-2.5 opacity-60">🔎</span>
        </div>
        <select id="limitFilter" class="rounded-xl border border-white/40 bg-white/70 dark:bg-slate-800/60 px-4 py-2.5 text-sm shadow focus:outline-none focus:ring-2 focus:ring-brand-400">
          <option value="all">All</option>
          <option value="capped">Capped only</option>
          <option value="unlimited">Unlimited (0)</option>
          <option value="reached">Reached / Exceeded</option>
        </select>
        <a href="#summary" class="rounded-xl bg-brand-600 hover:bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-elev">Jump to Summary</a>
      </div>
    </div>

    <!-- Limits Section -->
    <section class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold">Employer View Limits</h2>
          <p class="text-slate-500 dark:text-slate-400">Edit limits inline; badges and bars show real usage.</p>
        </div>
        <div class="text-sm text-slate-500">0 = Unlimited</div>
      </div>

      <form method="POST" class="space-y-6" id="limitsForm">
        <!-- Card grid on mobile -->
        <div class="md:hidden grid grid-cols-1 sm:grid-cols-2 gap-4" id="cardsContainer">
          <?php if (!empty($rows)): foreach ($rows as $row):
            $eid   = (int)$row['employer_id'];
            $name  = $row['company_name'] ?? 'Unknown';
            $limit = (int)$row['view_limit'];
            $used  = (int)$row['viewed_count'];
            $pct   = ($limit > 0) ? min(100, round(($used / $limit) * 100)) : ($used > 0 ? 100 : 0);
            $badge = $limit > 0 ? "$used / $limit" : "$used / ∞";
            $state = ($limit > 0 && $used >= $limit);
          ?>
          <div class="glass ring-neo rounded-2xl p-5 shadow-elev transition hover:-translate-y-0.5" data-name="<?= strtolower(h($name)) ?>" data-id="<?= $eid ?>" data-limit="<?= $limit ?>" data-used="<?= $used ?>">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs text-slate-500">Employer ID</div>
                <div class="font-semibold text-slate-900 dark:text-slate-100"><?= $eid ?></div>
              </div>
              <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $state ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' ?>"><?= h($badge) ?></span>
            </div>

            <div class="mt-3">
              <div class="text-slate-700 dark:text-slate-200 font-semibold line-clamp-2" title="<?= h($name) ?>"><?= h($name) ?></div>
              <div class="mt-3">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"><span>Usage</span><span><?= $pct ?>%</span></div>
                <div class="mt-1 h-2.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                  <div class="h-full <?= $pct >= 100 ? 'bg-rose-500' : 'bg-brand-500' ?>" style="width: <?= $pct ?>%;"></div>
                </div>
              </div>

              <div class="mt-4">
                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">View Limit</label>
                <input type="number" class="w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-800/70 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-400" name="limits[<?= $eid ?>]" value="<?= $limit ?>" min="0" />
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Set 0 for unlimited.</p>
              </div>
            </div>
          </div>
          <?php endforeach; else: ?>
            <div class="text-center text-rose-600">No employers found.</div>
          <?php endif; ?>
        </div>

        <!-- Desktop table -->
        <?php if (!empty($rows)): ?>
        <div class="hidden md:block glass ring-neo rounded-2xl overflow-hidden shadow-elev">
          <table class="w-full" id="limitsTable">
            <thead class="bg-slate-900 text-white text-xs uppercase tracking-wide">
              <tr>
                <th class="px-4 py-3 text-left">Employer ID</th>
                <th class="px-4 py-3 text-left">Company Name</th>
                <th class="px-4 py-3 text-left">Usage</th>
                <th class="px-4 py-3 text-left">View Limit</th>
                <th class="px-4 py-3 text-left">Profiles Viewed</th>
              </tr>
            </thead>
            <tbody class="bg-white/70 dark:bg-slate-900/40" id="limitsBody">
              <?php foreach ($rows as $row):
                $eid   = (int)$row['employer_id'];
                $name  = $row['company_name'] ?? 'Unknown';
                $limit = (int)$row['view_limit'];
                $used  = (int)$row['viewed_count'];
                $pct   = ($limit > 0) ? min(100, round(($used / $limit) * 100)) : ($used > 0 ? 100 : 0);
              ?>
              <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-white/90 dark:hover:bg-slate-900/60 transition-colors" data-name="<?= strtolower(h($name)) ?>" data-id="<?= $eid ?>" data-limit="<?= $limit ?>" data-used="<?= $used ?>">
                <td class="px-4 py-3 font-semibold"><?= $eid ?></td>
                <td class="px-4 py-3"><?= h($name) ?></td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-48 h-2.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                      <div class="h-full <?= $pct >= 100 ? 'bg-rose-500' : 'bg-brand-500' ?>" style="width: <?= $pct ?>%;"></div>
                    </div>
                    <span class="text-xs opacity-80"><?= $pct ?>%</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <input type="number" class="w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-800/70 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-400" name="limits[<?= $eid ?>]" value="<?= $limit ?>" min="0" />
                  <div class="text-[11px] opacity-70 mt-1">0 = unlimited</div>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= ($limit>0 && $used >= $limit) ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' ?>">
                    <?= $limit > 0 ? "$used / $limit" : "$used / ∞" ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <!-- Sticky submit bar -->
        <div class="sticky bottom-4 z-20">
          <div class="glass ring-neo rounded-2xl px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-elev">
            <div class="text-sm opacity-80">Make your changes above, then save.</div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 px-5 py-2.5 font-semibold text-white shadow-elev focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-400 focus:ring-offset-slate-50 dark:focus:ring-offset-slate-900">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Update Limits
            </button>
          </div>
        </div>
      </form>
    </section>

    <!-- Views Summary -->
    <section id="summary" class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold">Jobseeker View Summary</h2>
          <p class="text-slate-500 dark:text-slate-400">Recent profile views across all employers.</p>
        </div>
        <a href="#top" class="rounded-xl bg-slate-900/90 dark:bg-white/10 hover:bg-slate-900 text-white dark:text-slate-100 px-4 py-2 text-sm font-semibold shadow-elev">Back to Top</a>
      </div>

      <div class="glass ring-neo rounded-2xl overflow-hidden shadow-elev">
        <?php if ($summaryResult && $summaryResult->num_rows > 0): ?>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-slate-900 text-white text-xs uppercase tracking-wide">
                <tr>
                  <th class="px-4 py-3 text-left">#</th>
                  <th class="px-4 py-3 text-left">Employer</th>
                  <th class="px-4 py-3 text-left">Company</th>
                  <th class="px-4 py-3 text-left">Jobseeker</th>
                  <th class="px-4 py-3 text-left">Viewed At</th>
                </tr>
              </thead>
              <tbody class="bg-white/70 dark:bg-slate-900/40">
                <?php while ($row = $summaryResult->fetch_assoc()): ?>
                  <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-white/90 dark:hover:bg-slate-900/60 transition-colors">
                    <td class="px-4 py-3 font-semibold"><?= (int)$row['id'] ?></td>
                    <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">ID <?= (int)$row['employer_id'] ?></span></td>
                    <td class="px-4 py-3"><?= h($row['company_name'] ?? 'Unknown') ?></td>
                    <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-100 text-brand-700 dark:bg-brand-500/20 dark:text-brand-200">JS <?= (int)$row['jobseeker_id'] ?></span></td>
                    <td class="px-4 py-3"><span class="opacity-90"><?= h($row['viewed_at']) ?></span></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-10 text-center opacity-80">No jobseeker views found.</div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="py-10 text-center text-sm opacity-70">© <?= date('Y') ?> MSJOBS — Admin</footer>

  <script>
    // Dark mode toggle with persistence
    const body = document.documentElement;
    const toggle = document.getElementById('themeToggle');
    const mode = localStorage.getItem('msjobs_theme');
    if (mode === 'dark') body.classList.add('dark');

    toggle?.addEventListener('click', () => {
      body.classList.toggle('dark');
      localStorage.setItem('msjobs_theme', body.classList.contains('dark') ? 'dark' : 'light');
    });

    // Client-side search + filter (no backend change)
    const searchBox = document.getElementById('searchBox');
    const filterSel = document.getElementById('limitFilter');

    function matchesFilter(limit, used, mode) {
      if (mode === 'capped') return limit > 0;
      if (mode === 'unlimited') return limit === 0;
      if (mode === 'reached') return limit > 0 && used >= limit;
      return true;
    }

    function applyFilters() {
      const q = (searchBox?.value || '').trim().toLowerCase();
      const mode = filterSel?.value || 'all';

      // Cards (mobile)
      document.querySelectorAll('#cardsContainer > div[data-id]').forEach(el => {
        const name = el.getAttribute('data-name');
        const id = el.getAttribute('data-id');
        const limit = parseInt(el.getAttribute('data-limit')) || 0;
        const used = parseInt(el.getAttribute('data-used')) || 0;
        const textHit = !q || name.includes(q) || (id && id.includes(q));
        const filtHit = matchesFilter(limit, used, mode);
        el.style.display = (textHit && filtHit) ? '' : 'none';
      });

      // Table rows (desktop)
      document.querySelectorAll('#limitsBody > tr[data-id]').forEach(tr => {
        const name = tr.getAttribute('data-name');
        const id = tr.getAttribute('data-id');
        const limit = parseInt(tr.getAttribute('data-limit')) || 0;
        const used = parseInt(tr.getAttribute('data-used')) || 0;
        const textHit = !q || name.includes(q) || (id && id.includes(q));
        const filtHit = matchesFilter(limit, used, mode);
        tr.style.display = (textHit && filtHit) ? '' : 'none';
      });
    }

    searchBox?.addEventListener('input', applyFilters);
    filterSel?.addEventListener('change', applyFilters);
    applyFilters();
  </script>
</body>
</html>
