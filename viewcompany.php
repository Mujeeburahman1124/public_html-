<?php
include 'config.php';

// Handle actions: block, unblock, delete (UNCHANGED)
if (isset($_GET['action'], $_GET['id'])) {
    $company_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'block') {
        $new_status = 'cheating';
    } elseif ($action === 'unblock') {
        $new_status = 'active';
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM employers WHERE id = ?");
        $stmt->bind_param("i", $company_id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
            exit;
        } else {
            echo "Failed to delete company.";
        }
    }

    if (isset($new_status)) {
        $stmt = $conn->prepare("UPDATE employers SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $company_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch companies (UNCHANGED)
$query = $conn->query("
    SELECT users.id, users.email, employers.id as employer_id, employers.company_name, 
           employers.company_description, employers.contact_person, employers.phone, 
           employers.country, employers.logo, employers.status, employers.company_license
    FROM users
    INNER JOIN employers ON users.id = employers.user_id
    ORDER BY employers.company_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin - Manage Companies</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Tiny modal helper (no framework) -->
  <style>
    .fade-enter { opacity: 0; }
    .fade-enter-active { opacity: 1; transition: opacity .15s ease-in-out; }
    .fade-leave { opacity: 1; }
    .fade-leave-active { opacity: 0; transition: opacity .15s ease-in-out; }
  </style>
</head>
<body class="bg-slate-50 text-slate-800">

  <!-- Navbar -->
  <nav class="relative">
    <div class="absolute inset-0 bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-700"></div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between py-4">
        <div class="flex items-center gap-3">
          <div class="h-9 w-9 rounded-xl bg-white/10 flex items-center justify-center">
            <i class="fa-solid fa-building text-white"></i>
          </div>
          <h1 class="text-white text-lg sm:text-xl font-bold tracking-wide">MSJOBS — Admin / Companies</h1>
        </div>
        <a href="logout.php" class="text-white/90 hover:text-white text-sm font-medium flex items-center gap-2">
          <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
      </div>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
      <div>
        <h2 class="text-2xl sm:text-3xl font-bold">All Registered Companies</h2>
        <p class="text-slate-500 mt-1">Manage employer profiles, status, and compliance</p>
      </div>
      <!-- Quick Filter (client-side) -->
      <div class="flex items-center gap-3">
        <input id="searchBox"
               type="text"
               placeholder="Search company, email, country..."
               class="w-full md:w-80 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
      <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-700">
        <i class="fa-solid fa-check-circle mr-2"></i> Company deleted successfully.
      </div>
    <?php endif; ?>

    <!-- Cards -->
    <div id="cardsGrid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
      <?php while ($row = $query->fetch_assoc()): 
        $status = $row['status'] === 'active' ? 'active' : 'cheating';
        $statusLabel = $status === 'active' ? 'Active' : 'Cheating (Blocked)';
        $statusClasses = $status === 'active'
            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
            : 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
        $logo = htmlspecialchars($row['logo'] ?: '');
        $company = htmlspecialchars($row['company_name'] ?: 'Unknown Company');
        $email = htmlspecialchars($row['email'] ?: '');
        $contact = htmlspecialchars($row['contact_person'] ?: '—');
        $phone = htmlspecialchars($row['phone'] ?: '—');
        $country = htmlspecialchars($row['country'] ?: '—');
        $desc = nl2br(htmlspecialchars($row['company_description'] ?: 'No description provided.'));
        $license = htmlspecialchars($row['company_license'] ?: '');
        $eid = (int)$row['employer_id'];
      ?>
        <article 
          class="company-card group relative rounded-2xl bg-white border border-slate-200 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200"
          data-search="<?= $company . ' ' . $email . ' ' . $country ?>"
        >
          <!-- Status ribbon -->
          <div class="absolute right-3 top-3">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold <?= $statusClasses ?>">
              <i class="fa-solid <?= $status === 'active' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
              <?= $statusLabel ?>
            </span>
          </div>

          <!-- Header -->
          <div class="p-6 pb-3">
            <div class="flex items-center gap-4">
              <div class="relative">
                <img
                  src="<?= $logo ?>"
                  alt="Company Logo"
                  class="h-16 w-16 rounded-xl object-cover ring-1 ring-slate-200"
                  onerror="this.onerror=null;this.src='https://placehold.co/160x160?text=Logo';"
                />
                <div class="absolute -bottom-1 -right-1 h-6 w-6 rounded-lg bg-white flex items-center justify-center ring-1 ring-slate-200">
                  <i class="fa-solid fa-building text-slate-500 text-xs"></i>
                </div>
              </div>
              <div class="min-w-0">
                <h3 class="truncate text-lg font-bold text-slate-900"><?= $company ?></h3>
                <div class="mt-1 flex items-center gap-2 text-sm text-slate-600">
                  <i class="fa-solid fa-envelope text-slate-400"></i>
                  <span class="truncate"><?= $email ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Body -->
          <div class="px-6 pb-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div class="flex items-start gap-2">
                <i class="fa-solid fa-user-tie mt-0.5 text-slate-400"></i>
                <div>
                  <dt class="text-slate-500">Contact Person</dt>
                  <dd class="font-medium text-slate-800"><?= $contact ?></dd>
                </div>
              </div>

              <div class="flex items-start gap-2">
                <i class="fa-solid fa-phone mt-0.5 text-slate-400"></i>
                <div>
                  <dt class="text-slate-500">Phone</dt>
                  <dd class="font-medium text-slate-800"><?= $phone ?></dd>
                </div>
              </div>

              <div class="flex items-start gap-2">
                <i class="fa-solid fa-earth-asia mt-0.5 text-slate-400"></i>
                <div>
                  <dt class="text-slate-500">Country</dt>
                  <dd class="font-medium text-slate-800"><?= $country ?></dd>
                </div>
              </div>

              <div class="flex items-start gap-2 sm:col-span-2">
                <i class="fa-solid fa-note-sticky mt-0.5 text-slate-400"></i>
                <div class="w-full">
                  <dt class="text-slate-500">Description</dt>
                  <dd class="font-medium text-slate-800 leading-relaxed max-h-28 overflow-y-auto pr-1">
                    <?= $desc ?>
                  </dd>
                </div>
              </div>
            </dl>

            <?php if (!empty($license)): ?>
              <div class="mt-4">
                <button
                  class="inline-flex items-center gap-2 text-sm font-semibold rounded-lg px-3 py-2 ring-1 ring-slate-200 hover:ring-indigo-300 hover:text-indigo-700 transition"
                  onclick="openLicense('<?= $license ?>')"
                  type="button"
                  title="Preview Company License"
                >
                  <i class="fa-solid fa-id-card-clip"></i> View Company License
                </button>
              </div>
            <?php endif; ?>
          </div>

          <!-- Footer actions -->
          <div class="border-t border-slate-100 p-4 flex flex-wrap items-center justify-between gap-2">
            <a href="edit-company.php?id=<?= $eid ?>"
               class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 hover:ring-indigo-300 hover:text-indigo-700 transition">
              <i class="fa-solid fa-pen-to-square"></i> Edit
            </a>

            <div class="flex items-center gap-2">
              <?php if ($status === 'active'): ?>
                <a href="?action=block&id=<?= $eid ?>"
                   class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-rose-200 text-rose-700 hover:bg-rose-50 transition"
                   onclick="return confirm('Mark this company as CHEATING and block it?');">
                  <i class="fa-solid fa-ban"></i> Mark Cheating
                </a>
              <?php else: ?>
                <a href="?action=unblock&id=<?= $eid ?>"
                   class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-emerald-200 text-emerald-700 hover:bg-emerald-50 transition"
                   onclick="return confirm('Unblock this company?');">
                  <i class="fa-solid fa-unlock"></i> Unblock
                </a>
              <?php endif; ?>

              <a href="?action=delete&id=<?= $eid ?>"
                 class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 transition"
                 onclick="return confirm('Are you sure you want to permanently delete this company?');">
                <i class="fa-solid fa-trash-can"></i> Delete
              </a>
            </div>
          </div>
        </article>
      <?php endwhile; ?>
    </div>
  </main>

  <!-- License Modal -->
  <div id="licenseModal" class="hidden fixed inset-0 z-[100]">
    <div class="fade-enter fixed inset-0 bg-black/50" id="licenseBackdrop" onclick="closeLicense()"></div>
    <div class="fade-enter absolute inset-0 flex items-center justify-center p-4">
      <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
          <h3 class="text-lg font-semibold">Company License</h3>
          <button class="p-2 rounded-lg hover:bg-slate-100" onclick="closeLicense()" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="p-4">
          <img id="licenseImage" src="" alt="Company License" class="w-full max-h-[70vh] object-contain rounded-lg ring-1 ring-slate-100" />
          <div class="mt-4 flex items-center justify-between gap-3">
            <a id="licenseOpenNew" href="#" target="_blank"
               class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 hover:ring-indigo-300 hover:text-indigo-700 transition">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in New Tab
            </a>
            <button class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800 transition"
                    onclick="closeLicense()">
              <i class="fa-solid fa-check"></i> Done
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts: search & modal -->
  <script>
    // Client-side search filter
    const searchBox = document.getElementById('searchBox');
    const cardsGrid = document.getElementById('cardsGrid');
    const cards = Array.from(cardsGrid.querySelectorAll('.company-card'));

    searchBox?.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      cards.forEach(card => {
        const hay = (card.getAttribute('data-search') || '').toLowerCase();
        card.style.display = hay.includes(q) ? '' : 'none';
      });
    });

    // Simple modal controls
    const modal = document.getElementById('licenseModal');
    const backdrop = document.getElementById('licenseBackdrop');
    const img = document.getElementById('licenseImage');
    const openNew = document.getElementById('licenseOpenNew');

    function openLicense(url) {
      img.src = url;
      openNew.href = url;
      modal.classList.remove('hidden');
      // enter animation
      requestAnimationFrame(() => {
        modal.querySelectorAll('.fade-enter').forEach(el => el.classList.remove('fade-enter'));
      });
      document.body.style.overflow = 'hidden';
    }
    function closeLicense() {
      // leave animation
      modal.querySelectorAll('.fade-enter-active, .fade-leave, .fade-leave-active').forEach(() => {});
      modal.classList.add('hidden');
      img.src = '';
      document.body.style.overflow = '';
    }
    window.openLicense = openLicense;
    window.closeLicense = closeLicense;
  </script>
</body>
</html>
