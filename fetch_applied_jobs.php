<?php
/************************************************************
 * MSJOBS — Applied Jobs (NaukriGulf-like UI)
 * File: applied-jobs.php
 * Tech: PHP + MySQLi + Bootstrap 5 (icons)
 * Features: search, left filters, sort, pagination (client-side)
 ************************************************************/
declare(strict_types=1);
session_start();

/* ===== 0) DB CONNECT ===== */
$DB_HOST = "127.0.0.1:3306";
$DB_USER = "u903588615_root";
$DB_PASS = "Msjobs#1";
$DB_NAME = "u903588615_exaple";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  die("Database connection error.");
}

/* ===== 1) AUTH GUARD ===== */
if (!isset($_SESSION['user_id'])) {
  echo "<script>alert('Please log in to view your applied jobs.'); window.location.href='login.php';</script>";
  exit;
}
$user_id = (int)$_SESSION['user_id'];

/* ===== 2) FETCH APPLIED JOBS ===== */
$sql = "
  SELECT 
    jobs.id                 AS job_id,
    jobs.title              AS title,
    jobs.description        AS description,
    employers.company_name  AS company_name,
    applications.id         AS application_id,
    applications.applied_at AS applied_at
  FROM applications
  JOIN jobs ON applications.job_id = jobs.id
  LEFT JOIN employers ON jobs.company_id = employers.user_id
  WHERE applications.user_id = ?
  ORDER BY applications.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

/* Collect rows then close DB early */
$rows = [];
while ($r = $result->fetch_assoc()) { $rows[] = $r; }
$stmt->close();
$conn->close();

/* ===== 3) HELPERS ===== */
function e(?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function fmt_date(?string $dt): string {
  if (!$dt) return '';
  $ts = strtotime($dt);
  if ($ts === false) return e($dt);
  return date("d M Y, g:i A", $ts);
}
function initials(string $name): string {
  $name = trim($name);
  if ($name === '') return 'MS';
  $parts = preg_split('/\s+/', $name);
  $letters = array_map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)), array_slice($parts,0,2));
  return implode('', $letters);
}

/* Build unique company list for filter */
$companies = [];
foreach ($rows as $r) {
  $c = trim((string)($r['company_name'] ?? ''));
  if ($c !== '') { $companies[$c] = true; }
}
ksort($companies);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Applied Jobs | MSJOBS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --nk-bg:#f5f7f9;
      --nk-card-bg:#ffffff;
      --nk-text:#1f2937;
      --nk-muted:#6b7280;
      --nk-primary:#0d6efd; /* Bootstrap primary (Naukri-like blue) */
      --nk-border:#e5e7eb;
    }
    body{ background-color: var(--nk-bg); color: var(--nk-text); }
    .nk-header{
      background:#ffffff;
      border-bottom:1px solid var(--nk-border);
    }
    .nk-searchbar{
      border:1px solid var(--nk-border);
      border-radius: .75rem;
      background:#fff;
      padding:.5rem .75rem;
    }
    .nk-searchbar input{
      border:0; outline:0; box-shadow:none;
    }
    .nk-card{
      background: var(--nk-card-bg);
      border:1px solid var(--nk-border);
      border-radius: .75rem;
      box-shadow: 0 2px 10px rgba(17,24,39,.06);
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
    }
    .nk-card:hover{
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(17,24,39,.08);
      border-color:#d9dde3;
    }
    .nk-badge{
      font-size:.75rem;
      background:#e9f2ff;
      color:#0b5ed7;
      border:1px solid #d6e6ff;
      padding:.25rem .5rem;
      border-radius: 999px;
    }
    .nk-company-logo{
      width:44px; height:44px; border-radius:50%;
      display:inline-flex; align-items:center; justify-content:center;
      background:#eef2ff; color:#3b82f6; font-weight:600; border:1px solid #e5e7eb;
    }
    .nk-sidebar{
      position:sticky; top:88px;
    }
    .nk-muted{ color: var(--nk-muted); }
    .nk-divider{ border-top:1px dashed #e5e7eb; }
    .form-check .form-check-input{
      cursor:pointer;
    }
    .nk-page-btn{
      border-radius: .5rem;
    }
    @media (max-width: 991.98px){
      .nk-sidebar{ position: static; top:auto; }
    }
  </style>
</head>
<body>

  <!-- Top Header -->
  <header class="nk-header">
    <div class="container py-3">
      <div class="d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-briefcase-fill text-primary fs-4"></i>
          <h1 class="h5 mb-0 fw-semibold">Applied Jobs</h1>
        </div>
        <a href="employee_dashboard" class="btn btn-outline-secondary btn-sm d-none d-sm-inline-flex">
          <i class="bi bi-grid me-2"></i> Dashboard
        </a>
      </div>

      <!-- Search / Sort (Naukri-like top utility) -->
      <div class="mt-3">
        <div class="row g-2 g-md-3">
          <div class="col-12 col-lg-8">
            <div class="nk-searchbar d-flex align-items-center gap-2">
              <i class="bi bi-search text-muted"></i>
              <input id="q" type="text" class="form-control" placeholder="Search by job title, company, or keywords..." />
            </div>
          </div>
          <div class="col-6 col-lg-2">
            <select id="sort" class="form-select">
              <option value="new">Newest first</option>
              <option value="old">Oldest first</option>
              <option value="title">Title A–Z</option>
              <option value="company">Company A–Z</option>
            </select>
          </div>
          <div class="col-6 col-lg-2 d-grid">
            <button id="reset" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Reset</button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Main -->
  <main class="container my-4 my-lg-5">
    <div class="row g-4">

      <!-- Sidebar (filters) -->
      <aside class="col-12 col-lg-3">
        <!-- Mobile filter toggle -->
        <button class="btn btn-outline-primary w-100 d-lg-none mb-2" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas">
          <i class="bi bi-sliders me-2"></i> Filters
        </button>

        <div class="offcanvas offcanvas-start" tabindex="-1" id="filterCanvas">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title">Filters</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <?php /* mobile body mirrors desktop */ ?>
            <div id="filters-mobile"></div>
          </div>
        </div>

        <!-- Desktop filter card -->
        <div class="nk-card p-3 d-none d-lg-block nk-sidebar">
          <h6 class="mb-3">Refine Results</h6>

          <div class="mb-3">
            <label class="form-label mb-1">Company</label>
            <div class="border rounded p-2" style="max-height: 220px; overflow:auto;" id="companyFilter">
              <?php if (count($companies) > 0): ?>
                <?php foreach(array_keys($companies) as $c): $id = 'cmp_'.md5($c); ?>
                  <div class="form-check">
                    <input class="form-check-input cmp" type="checkbox" value="<?php echo e($c); ?>" id="<?php echo $id; ?>">
                    <label class="form-check-label" for="<?php echo $id; ?>"><?php echo e($c); ?></label>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-muted small">No company filters</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label mb-1">Applied Date</label>
            <select id="dateFilter" class="form-select">
              <option value="any">Any time</option>
              <option value="24h">Last 24 hours</option>
              <option value="7d">Last 7 days</option>
              <option value="30d">Last 30 days</option>
              <option value="365d">Last year</option>
            </select>
          </div>

          <button id="clearFilters" class="btn btn-outline-secondary w-100">Clear Filters</button>
        </div>
      </aside>

      <!-- List -->
      <section class="col-12 col-lg-9">
        <?php if (count($rows) > 0): ?>
          <div id="resultsInfo" class="nk-muted small mb-2"></div>

          <div id="cards" class="row g-3">
            <?php foreach ($rows as $row):
              $title   = e($row['title']);
              $desc    = trim((string)$row['description']);
              $company = e($row['company_name'] ?? 'N/A');
              $applied = fmt_date($row['applied_at']);
              $badge   = initials($row['company_name'] ?? 'MS');
              $appliedTs = strtotime($row['applied_at'] ?? '') ?: 0;
            ?>
            <div class="col-12">
              <article class="nk-card p-3 p-md-4" 
                       data-title="<?php echo strtolower($title); ?>"
                       data-company="<?php echo strtolower($company); ?>"
                       data-date="<?php echo $appliedTs; ?>">
                <div class="d-flex align-items-start gap-3">
                  <div class="nk-company-logo flex-shrink-0"><?php echo e($badge); ?></div>
                  <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <h2 class="h6 mb-0"><?php echo $title; ?></h2>
                      <span class="nk-badge">Applied</span>
                    </div>
                    <div class="nk-muted small mt-1">
                      <i class="bi bi-building me-1"></i>
                      <strong class="text-body"><?php echo $company; ?></strong>
                    </div>

                    <div class="nk-muted small mt-2">
                      <i class="bi bi-calendar-check me-1"></i>
                      Applied on: <span class="text-body"><?php echo e($applied); ?></span>
                    </div>

                    <?php if ($desc !== ''): ?>
                      <hr class="nk-divider my-3">
                      <p class="mb-0 text-secondary" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                        <?php echo nl2br(e($desc)); ?>
                      </p>
                    <?php endif; ?>

                    <div class="mt-3 d-flex gap-2">
                      <button class="btn btn-sm btn-outline-primary" 
                              onclick='openModal(<?php echo json_encode([
                                "title"=>$title,"company"=>$company,"applied"=>$applied,
                                "desc"=>nl2br(e($desc))
                              ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                        <i class="bi bi-eye me-1"></i> View details
                      </button>
                      <a class="btn btn-sm btn-light" href="jobs.php">
                        <i class="bi bi-briefcase me-1"></i> Find similar
                      </a>
                    </div>
                  </div>
                </div>
              </article>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Pagination -->
          <nav class="d-flex justify-content-between align-items-center mt-3">
            <div class="small nk-muted" id="pageInfo"></div>
            <ul class="pagination mb-0" id="pager"></ul>
          </nav>

        <?php else: ?>
          <div class="nk-card p-5 text-center">
            <div class="text-primary fs-3"><i class="bi bi-inbox"></i></div>
            <h6 class="mt-2 mb-1">No applications yet</h6>
            <p class="nk-muted mb-3">Apply to jobs and your applications will appear here.</p>
            <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <!-- Detail Modal -->
  <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="mTitle"></h5>
            <div class="nk-muted small">
              <i class="bi bi-building me-1"></i><span id="mCompany"></span>
              <span class="mx-2">•</span>
              <i class="bi bi-calendar-check me-1"></i><span id="mApplied"></span>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="mDesc" class="text-secondary"></div>
        </div>
        <div class="modal-footer">
          <a href="jobs.php" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Find similar</a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const qEl = document.getElementById('q');
    const sortEl = document.getElementById('sort');
    const resetEl = document.getElementById('reset');
    const cardsWrap = document.getElementById('cards');
    const resultsInfo = document.getElementById('resultsInfo');
    const pageInfo = document.getElementById('pageInfo');
    const pager = document.getElementById('pager');

    // Modal refs
    const mTitle = document.getElementById('mTitle');
    const mCompany = document.getElementById('mCompany');
    const mApplied = document.getElementById('mApplied');
    const mDesc = document.getElementById('mDesc');
    const modal = new bootstrap.Modal('#detailModal');

    // Filters (desktop)
    const cmpChecks = Array.from(document.querySelectorAll('.form-check-input.cmp'));
    const dateFilter = document.getElementById('dateFilter');
    const clearFilters = document.getElementById('clearFilters');

    // Mirror filters into offcanvas for mobile
    const filtersMobile = document.getElementById('filters-mobile');
    if (filtersMobile) {
      const cloneCompany = document.getElementById('companyFilter')?.cloneNode(true);
      const cloneDate = dateFilter?.cloneNode(true);
      filtersMobile.innerHTML = '';
      if (cloneCompany){
        const wrap = document.createElement('div');
        wrap.className = 'mb-3';
        wrap.innerHTML = '<label class="form-label mb-1">Company</label>';
        wrap.appendChild(cloneCompany);
        filtersMobile.appendChild(wrap);
      }
      if (cloneDate){
        const wrap2 = document.createElement('div');
        wrap2.className = 'mb-3';
        wrap2.innerHTML = '<label class="form-label mb-1">Applied Date</label>';
        cloneDate.id = 'dateFilterMobile';
        wrap2.appendChild(cloneDate);
        filtersMobile.appendChild(wrap2);
      }
    }

    function normalized(s){ return (s||'').toString().toLowerCase().trim(); }
    function daysAgo(ts){ return (Date.now()/1000 - (ts||0)) / 86400; }

    function getVisibleCards(){
      return Array.from(cardsWrap.querySelectorAll('.nk-card'));
    }

    // Pagination
    const PAGE_SIZE = 8;
    let currentPage = 1;

    function renderPagination(totalVisible){
      const totalPages = Math.max(1, Math.ceil(totalVisible / PAGE_SIZE));
      currentPage = Math.min(currentPage, totalPages);

      pager.innerHTML = '';
      const makeBtn = (page, label=page, active=false, disabled=false) => {
        const li = document.createElement('li');
        li.className = 'page-item' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
        const a = document.createElement('button');
        a.className = 'page-link nk-page-btn';
        a.textContent = label;
        a.addEventListener('click', () => { if(!disabled){ currentPage = page; applyAll(); }});
        li.appendChild(a);
        return li;
      };

      const totalPagesNum = totalPages;
      pager.appendChild(makeBtn(Math.max(1, currentPage-1), '«', false, currentPage===1));
      for(let p=1;p<=totalPagesNum;p++){
        if (p===1 || p===totalPagesNum || Math.abs(p-currentPage)<=1){
          pager.appendChild(makeBtn(p, String(p), p===currentPage));
        } else if (Math.abs(p-currentPage)===2) {
          const li = document.createElement('li');
          li.className = 'page-item disabled';
          li.innerHTML = '<span class="page-link">…</span>';
          pager.appendChild(li);
        }
      }
      pager.appendChild(makeBtn(Math.min(totalPages, currentPage+1), '»', false, currentPage===totalPages));
      pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    function applyAll(){
      const q = normalized(qEl.value);
      const selectedCompanies = new Set(
        Array.from(document.querySelectorAll('.form-check-input.cmp:checked')).map(i=>normalized(i.value))
      );
      const dateValue = (document.getElementById('dateFilterMobile') || dateFilter)?.value || 'any';

      // Filter visibility
      const cards = Array.from(cardsWrap.querySelectorAll('.nk-card')).map(card=>{
        const title = card.getAttribute('data-title') || '';
        const company = card.getAttribute('data-company') || '';
        const date = parseInt(card.getAttribute('data-date') || '0', 10);
        const textBlob = (title + ' ' + company + ' ' + card.innerText).toLowerCase();

        let passQ = (q === '' || textBlob.includes(q));
        let passCompany = (selectedCompanies.size===0 || selectedCompanies.has(company));
        let passDate = true;
        const age = daysAgo(date);
        if (dateValue === '24h') passDate = age <= 1;
        else if (dateValue === '7d') passDate = age <= 7;
        else if (dateValue === '30d') passDate = age <= 30;
        else if (dateValue === '365d') passDate = age <= 365;

        const visible = passQ && passCompany && passDate;
        card.parentElement.style.display = visible ? '' : 'none';
        return {card, visible, date, title, company};
      });

      // Sort visible
      const mode = sortEl.value;
      const visibleCards = cards.filter(c=>c.visible);
      visibleCards.sort((a,b)=>{
        if (mode==='new') return (b.date - a.date);
        if (mode==='old') return (a.date - b.date);
        if (mode==='title') return a.title.localeCompare(b.title);
        if (mode==='company') return a.company.localeCompare(b.company);
        return 0;
      });
      // Re-append in sorted order
      visibleCards.forEach(({card})=> cardsWrap.appendChild(card.parentElement));

      // Pagination show/hide
      const totalVisible = visibleCards.length;
      renderPagination(totalVisible);

      let shown = 0, start = (currentPage-1)*PAGE_SIZE, end = start + PAGE_SIZE;
      for (let i=0;i<visibleCards.length;i++){
        const holder = visibleCards[i].card.parentElement; // col-12
        holder.style.display = (i>=start && i<end) ? '' : 'none';
        if (i>=start && i<end) shown++;
      }
      const total = cards.length;
      resultsInfo.textContent = `${totalVisible} result(s) • showing ${shown} on this page`;
    }

    // Events
    qEl.addEventListener('input', ()=>{ currentPage=1; applyAll(); });
    sortEl.addEventListener('change', ()=>{ currentPage=1; applyAll(); });
    resetEl.addEventListener('click', ()=>{
      qEl.value=''; sortEl.value='new'; currentPage=1;
      document.querySelectorAll('.form-check-input.cmp').forEach(el=> el.checked=false);
      (document.getElementById('dateFilterMobile')||dateFilter).value='any';
      applyAll();
    });
    cmpChecks.forEach(ch=> ch.addEventListener('change', ()=>{ currentPage=1; applyAll(); }));
    if (dateFilter) dateFilter.addEventListener('change', ()=>{ currentPage=1; applyAll(); });
    if (document.getElementById('dateFilterMobile'))
      document.getElementById('dateFilterMobile').addEventListener('change', ()=>{ currentPage=1; applyAll(); });

    // Modal open
    function openModal(payload){
      mTitle.textContent = payload.title || '';
      mCompany.textContent = payload.company || '';
      mApplied.textContent = payload.applied || '';
      mDesc.innerHTML = payload.desc || '';
      modal.show();
    }
    window.openModal = openModal;

    // Init
    applyAll();
  </script>
</body>
</html>
