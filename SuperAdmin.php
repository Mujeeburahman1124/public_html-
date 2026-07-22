<?php
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) {
//   header("Location: admin_login.php");
//   exit();
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>MSJOBS — Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

  <!-- Tailwind & Icons -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.bunny.net" />
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <!-- Popper for dropdowns -->
  <script defer src="https://unpkg.com/@popperjs/core@2"></script>

  <!-- Tailwind Config -->
  <script>
    tailwind.config = {
      darkMode: ['class'],
      theme: {
        extend: {
          colors: {
            brand: '#0ea5e9',
            brandDark: '#0284c7',
            neon: '#22d3ee',
            base: {
              25: '#0b1220',
              50: '#0f172a',
              100: '#111827',
              200: '#1f2937',
              300: '#334155',
              400: '#475569',
              500: '#64748b',
              600: '#94a3b8',
              700: '#cbd5e1',
              800: '#e2e8f0',
              900: '#f8fafc'
            }
          },
          boxShadow: {
            glow: '0 10px 30px rgba(34,211,238,0.25)',
            soft: '0 8px 24px rgba(2,132,199,0.15)'
          },
          backgroundImage: {
            grid: "radial-gradient(circle at 1px 1px, rgba(255,255,255,.06) 1px, transparent 0)"
          },
          fontFamily: {
            inter: ['Inter','ui-sans-serif','system-ui']
          }
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
    html,body { height: 100%; }
    body { font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .backdrop { backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }

    .card {
      background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.05));
      border: 1px solid var(--stroke);
      border-radius: 16px;
      transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow, 0 8px 30px rgba(0,0,0,.25)), 0 0 0 1px rgba(34,211,238,.10) inset;
      border-color: rgba(34,211,238,.28);
    }

    .sidelink { color: #cbd5e1; }
    .sidelink:hover { color: #e2e8f0; background: rgba(255,255,255,.06); }
    .sidelink.active { color: white; background: linear-gradient(90deg, rgba(14,165,233,.22), rgba(34,211,238,.10)); border: 1px solid rgba(34,211,238,.25); }

    .thin-scroll::-webkit-scrollbar{ width:8px; height:8px }
    .thin-scroll::-webkit-scrollbar-thumb{ background:#39465f; border-radius:8px }

    /* Mobile tweaks */
    @media (max-width: 640px) {
      .card { border-radius: 14px; }
      .touch-safe { padding-bottom: env(safe-area-inset-bottom); }
    }
  </style>
</head>

<body class="bg-base-25 text-base-900 dark text-base-800 [background-image:linear-gradient(180deg,#0b1220,40%,#0a0f1c)_] relative touch-safe">
  <!-- Decorative gradient glow -->
  <div class="pointer-events-none absolute -top-32 -left-24 h-80 w-80 rounded-full blur-3xl opacity-40" style="background: radial-gradient(closest-side, rgba(34,211,238,.35), transparent)"></div>
  <div class="pointer-events-none absolute -bottom-24 -right-16 h-72 w-72 rounded-full blur-3xl opacity-40" style="background: radial-gradient(closest-side, rgba(14,165,233,.35), transparent)"></div>

  <!-- Sidebar (off-canvas on mobile) -->
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out">
    <div class="h-full backdrop" style="background: var(--sidebar-glass); border-right:1px solid var(--stroke)">
      <div class="px-5 py-4 border-b" style="border-color: var(--stroke)">
        <div class="flex items-center gap-3">
          <div class="h-11 w-11 rounded-xl grid place-items-center shadow-glow text-white"
               style="background: radial-gradient(circle at 30% 20%, #22d3ee, #0ea5e9);">
            <i class="ri-rocket-2-fill text-lg"></i>
          </div>
          <div>
            <h1 class="text-xl font-extrabold tracking-tight text-white">
              MS <span class="px-2 rounded-md bg-brand text-white shadow-soft">JOBS</span>
            </h1>
            <p class="text-xs text-base-600">Admin Console</p>
          </div>
        </div>
      </div>

      <nav class="px-3 py-5 space-y-7 overflow-y-auto thin-scroll h-[calc(100%-5.75rem)]">
        <!-- Admin -->
        <div>
          <div class="px-3 text-[11px] uppercase tracking-wider text-base-600 mb-2">Admin</div>
          <a href="#" class="sidelink active flex items-center gap-3 px-3 py-2 rounded-xl">
            <i class="ri-home-5-line text-[18px]"></i> <span>Dashboard</span>
          </a>

          <div class="mt-1">
            <button class="w-full flex items-center justify-between sidelink px-3 py-2 rounded-xl group" data-collapse="#usersMenu">
              <span class="flex items-center gap-3">
                <i class='bx bx-user text-[18px]'></i> Users
              </span>
              <i class="ri-arrow-right-s-line transition group-[.open]:rotate-90"></i>
            </button>
            <div id="usersMenu" class="pl-10 hidden space-y-1 mt-1">
              <a href="ManageUsers.php" class="block px-3 py-1.5 rounded-lg text-base-600 hover:text-white hover:bg-white/10">All</a>
              <a href="#" class="block px-3 py-1.5 rounded-lg text-base-600 hover:text-white hover:bg-white/10">Roles</a>
            </div>
          </div>

          <a href="#" class="sidelink flex items-center gap-3 px-3 py-2 rounded-xl mt-1">
            <i class='bx bx-list-ul text-[18px]'></i> Activities
          </a>
        </div>

        <!-- Blog -->
        <div>
          <div class="px-3 text-[11px] uppercase tracking-wider text-base-600 mb-2">Blog</div>

          <div class="mt-1">
            <button class="w-full flex items-center justify-between sidelink px-3 py-2 rounded-xl group" data-collapse="#postMenu">
              <span class="flex items-center gap-3">
                <i class='bx bxl-blogger text-[18px]'></i> Post
              </span>
              <i class="ri-arrow-right-s-line transition group-[.open]:rotate-90"></i>
            </button>
            <div id="postMenu" class="pl-10 hidden space-y-1 mt-1">
              <a href="admin-add-blog.php" class="block px-3 py-1.5 rounded-lg text-base-600 hover:text-white hover:bg-white/10">All</a>
              <a href="#" class="block px-3 py-1.5 rounded-lg text-base-600 hover:text-white hover:bg-white/10">Categories</a>
            </div>
          </div>

          <a href="#" class="sidelink flex items-center gap-3 px-3 py-2 rounded-xl mt-1">
            <i class='bx bx-archive text-[18px]'></i> Archive
          </a>
        </div>

        <!-- Personal -->
        <div>
          <div class="px-3 text-[11px] uppercase tracking-wider text-base-600 mb-2">Personal</div>
          <a href="#" class="sidelink flex items-center gap-3 px-3 py-2 rounded-xl">
            <i class='bx bx-bell text-[18px]'></i> Notifications
            <span class="ml-auto text-[11px] font-bold bg-rose-500/20 text-rose-300 rounded-full h-5 min-w-[20px] px-1.5 grid place-items-center">5</span>
          </a>
          <a href="#" class="sidelink flex items-center gap-3 px-3 py-2 rounded-xl">
            <i class='bx bx-envelope text-[18px]'></i> Messages
            <span class="ml-auto text-[11px] font-bold bg-emerald-500/20 text-emerald-300 rounded-full h-5 min-w-[20px] px-1.5 grid place-items-center">2</span>
          </a>
        </div>

        <div class="px-3 pt-4 border-t mt-6 text-[11px] text-base-600" style="border-color: var(--stroke)">
          © <?= date('Y'); ?> MSJOBS — All rights reserved.
        </div>
      </nav>
    </div>
  </aside>

  <!-- Overlay (mobile) -->
  <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

  <!-- Main -->
  <main class="md:ml-72 transition-all">
    <!-- Topbar -->
    <header class="sticky top-0 z-30 backdrop" style="background: var(--card-glass); border-bottom:1px solid var(--stroke)">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center gap-2">
        <button id="openSidebar" class="md:hidden h-10 w-10 grid place-items-center rounded-xl hover:bg-white/10 text-white">
          <i class="ri-menu-line text-xl"></i>
        </button>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
          <!-- Search -->
          <div class="relative dropdown">
            <button class="h-10 w-10 grid place-items-center rounded-xl hover:bg-white/10 text-white dropdown-toggle">
              <i class="ri-search-line text-[20px]"></i>
            </button>
            <div class="dropdown-menu hidden absolute right-0 mt-2 w-[88vw] sm:w-80 max-w-[92vw] card backdrop p-3">
              <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-base-600"></i>
                <input type="text" class="w-full pl-10 pr-3 py-2 rounded-xl bg-white/5 border border-white/10 outline-none focus:ring-2 focus:ring-brand text-base-800 placeholder:text-base-600"
                  placeholder="Search...">
              </div>
            </div>
          </div>

          <!-- Notifications (hidden on xs if needed) -->
          <div class="relative dropdown hidden xs:flex sm:flex">
            <button class="h-10 w-10 grid place-items-center rounded-xl hover:bg-white/10 text-white dropdown-toggle">
              <i class="ri-notification-3-line text-[20px]"></i>
            </button>
            <div class="dropdown-menu hidden absolute right-0 mt-2 w-[92vw] sm:w-[420px] max-w-[92vw] card backdrop">
              <div class="px-4 pt-3 border-b pb-2 flex items-center gap-6" style="border-color: var(--stroke)">
                <button data-tab="notify" data-tab-page="notifications"
                        class="text-sm font-semibold pb-2 border-b-2 border-transparent hover:text-neon active">
                  Notifications
                </button>
                <button data-tab="notify" data-tab-page="messages"
                        class="text-sm font-semibold pb-2 border-b-2 border-transparent hover:text-neon">
                  Messages
                </button>
              </div>
              <div class="p-4 space-y-3">
                <div data-tab-for="notify" data-page="notifications">
                  <div class="flex items-start gap-3">
                    <div class="h-9 w-9 rounded-lg bg-white/10 grid place-items-center text-neon"><i class="ri-bell-line"></i></div>
                    <div class="text-sm">
                      <div class="font-semibold text-white">System</div>
                      <p class="text-base-600">Welcome to MSJOBS Admin.</p>
                    </div>
                  </div>
                </div>
                <div data-tab-for="notify" data-page="messages" class="hidden">
                  <div class="text-sm text-base-600">No new messages.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Fullscreen (hide on very small screens) -->
          <button id="fullscreen-button" class="hidden sm:grid h-10 w-10 place-items-center rounded-xl hover:bg-white/10 text-white">
            <i class="ri-fullscreen-line text-[20px]"></i>
          </button>

          <!-- Profile -->
          <div class="relative dropdown ml-1">
            <button class="dropdown-toggle flex items-center gap-2 sm:gap-3 px-2 py-1 rounded-xl hover:bg-white/10 text-white">
              <div class="relative">
                <img class="h-9 w-9 rounded-xl object-cover"
                     src="https://laravelui.spruko.com/tailwind/ynex/build/assets/images/faces/9.jpg" alt="">
                <span class="absolute -right-0 -top-0 h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-white/80"></span>
              </div>
              <div class="hidden md:block text-left">
                <div class="text-sm font-semibold leading-tight">MS JOBS</div>
                <div class="text-xs text-base-600 -mt-0.5">Administrator</div>
              </div>
              <i class="ri-arrow-down-s-line hidden sm:inline"></i>
            </button>
            <ul class="dropdown-menu hidden absolute right-0 mt-2 w-48 card backdrop py-2">
              <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10 text-base-800">Profile</a></li>
              <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10 text-base-800">Settings</a></li>
            </ul>
          </div>

          <!-- Logout -->
          <form action="logout.php" method="post" class="ml-1">
            <button type="submit" class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 rounded-full bg-rose-600 text-white text-sm sm:text-[15px] font-semibold shadow-soft hover:bg-rose-700 transition">
              <i class="ri-logout-box-r-line text-base sm:text-lg"></i> <span class="hidden xs:inline">Logout</span>
            </button>
          </form>
        </div>
      </div>
    </header>

    <!-- Content -->
    <section class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
      <div class="mb-6 sm:mb-7 flex items-center justify-between gap-2">
        <div>
          <h2 class="text-xl sm:text-3xl font-extrabold tracking-tight text-white">Dashboard</h2>
          <p class="text-sm sm:text-base text-base-600 mt-1">Quick access to your most-used admin modules.</p>
        </div>
        <a href="admin-approve.php" class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 rounded-full bg-brand text-white text-sm sm:text-[15px] font-semibold shadow-soft hover:bg-brandDark transition">
          <i class="ri-verified-badge-line text-base sm:text-lg"></i> Review
        </a>
      </div>

      <!-- Modules (1-col on mobile) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Users -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fas fa-users text-neon"></i> <span>Users</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="logout.php" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="ManageUsers.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Companies -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fas fa-building text-emerald-400"></i> <span>Companies</span>
              <span class="ml-2 text-[10px] sm:text-[11px] font-bold bg-emerald-400/15 text-emerald-300 px-1.5 py-0.5 rounded-full">+30%</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="viewcompany.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Manage Jobs -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-briefcase text-indigo-300"></i> <span>Manage Jobs</span>
              <span class="ml-2 text-[10px] sm:text-[11px] font-bold bg-indigo-400/15 text-indigo-200 px-1.5 py-0.5 rounded-full">Live</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="manage_jobs" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Jobs Approve -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-shield-halved text-teal-300"></i> <span>Jobs Approve</span>
              <span class="ml-2 text-[10px] sm:text-[11px] font-bold bg-teal-400/15 text-teal-200 px-1.5 py-0.5 rounded-full">Queue</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="admin-approve.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Limit CV -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-file-circle-check text-orange-300"></i> <span>Limit CV</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="admin_employer_limits" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- FAQ -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-circle-question text-purple-300"></i> <span>Add FAQ</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="adminFaq.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Videos -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-clapperboard text-rose-300"></i> <span>Add Videos</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="adminvedio.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Employee DB -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-database text-sky-300"></i> <span>Employee Database</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="jobseekerAdmin.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Job Application -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-file-signature text-cyan-300"></i> <span>Job Application</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="FullView.Application.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Subscription Admin -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-crown text-amber-300"></i> <span>Subscription Admin</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="Admin_Sub.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Super Admin Password Manage -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fa-solid fa-user-shield text-amber-300"></i> <span>Super Admin Password Manage</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="SuperAdminUsers.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Blogs -->
        <div class="card p-4 sm:p-6">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="fas fa-blog text-purple-300"></i> <span>Blogs</span>
            </div>
            <div class="relative dropdown">
              <button class="dropdown-toggle h-9 w-9 grid place-items-center rounded-lg hover:bg-white/10 text-base-700"><i class="ri-more-fill"></i></button>
              <ul class="dropdown-menu hidden absolute right-0 mt-2 w-40 sm:w-44 card backdrop py-2">
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Profile</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Settings</a></li>
                <li><a href="#" class="block px-3 py-2 text-sm rounded-lg hover:bg-white/10">Logout</a></li>
              </ul>
            </div>
          </div>
          <a href="admin-add-blog.php" class="inline-flex items-center gap-2 text-brand font-semibold hover:text-brandDark transition">
            View <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

        <!-- Site Settings -->
        <div class="card p-4 sm:p-6" style="border-color:rgba(34,211,238,.30); background:linear-gradient(135deg,rgba(14,165,233,.12),rgba(34,211,238,.07))">
          <div class="flex items-start justify-between mb-4 sm:mb-5">
            <div class="flex items-center gap-2 text-sm font-semibold text-base-700">
              <i class="ri-settings-3-fill text-neon text-base"></i>
              <span class="text-white">Site Settings</span>
              <span class="ml-1 text-[10px] sm:text-[11px] font-bold bg-neon/15 text-neon px-1.5 py-0.5 rounded-full">New</span>
            </div>
          </div>
          <p class="text-xs text-base-600 mb-3 leading-relaxed">
            Update contact info, address, Google Maps link, and social media profiles across the whole website.
          </p>
          <a href="admin_site_settings.php"
             class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold text-white shadow-glow transition hover:shadow-none"
             style="background:linear-gradient(135deg,#0ea5e9,#22d3ee)">
            Manage Settings <i class="ri-arrow-right-up-line"></i>
          </a>
        </div>

      </div>
    </section>
  </main>

  <!-- JS -->
  <script>
    // Sidebar mobile
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const openSidebar = document.getElementById('openSidebar');

    function closeNav(){ sidebar.classList.add('-translate-x-full'); overlay?.classList.add('hidden'); }
    function openNav(){ sidebar.classList.remove('-translate-x-full'); overlay?.classList.remove('hidden'); }

    openSidebar?.addEventListener('click', openNav);
    overlay?.addEventListener('click', closeNav);

    // Collapse sections
    document.querySelectorAll('[data-collapse]').forEach(btn=>{
      const target = document.querySelector(btn.getAttribute('data-collapse'));
      btn.addEventListener('click', ()=>{
        const open = target.classList.toggle('hidden') === false;
        btn.classList.toggle('open', open);
      });
    });

    // Dropdowns (one open at a time)
    const popperInstance = {};
    function bindDropdown(drop){
      const toggle = drop.querySelector('.dropdown-toggle');
      const menu = drop.querySelector('.dropdown-menu');
      if(!toggle || !menu) return;

      const id = Math.random().toString(36).slice(2);
      menu.dataset.popperId = id;
      popperInstance[id] = Popper.createPopper(toggle, menu, {
        placement: 'bottom-end',
        modifiers:[{name:'offset', options:{offset:[0,8]}},
                   {name:'preventOverflow', options:{padding: 8, boundary: document.body}}]
      });

      toggle.addEventListener('click', (e)=>{
        e.stopPropagation();
        const isHidden = menu.classList.contains('hidden');
        document.querySelectorAll('.dropdown-menu').forEach(m=>m.classList.add('hidden'));
        if(isHidden){ menu.classList.remove('hidden'); popperInstance[id].update(); }
      });
    }
    document.querySelectorAll('.dropdown').forEach(bindDropdown);
    document.addEventListener('click', ()=> {
      document.querySelectorAll('.dropdown-menu').forEach(m=>m.classList.add('hidden'));
    });

    // Tabs inside dropdowns
    document.querySelectorAll('[data-tab]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const tab = btn.dataset.tab, page = btn.dataset.tabPage;
        document.querySelectorAll(`[data-tab="${tab}"]`).forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll(`[data-tab-for="${tab}"]`).forEach(p=>p.classList.add('hidden'));
        document.querySelector(`[data-tab-for="${tab}"][data-page="${page}"]`)?.classList.remove('hidden');
      });
    });

    // Fullscreen
    document.getElementById('fullscreen-button')?.addEventListener('click', ()=>{
      if (document.fullscreenElement) document.exitFullscreen();
      else document.documentElement.requestFullscreen();
    });

    // Close sidebar with ESC on mobile
    document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') closeNav(); });
  </script>
</body>
</html>
