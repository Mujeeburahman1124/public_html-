<?php
/* =========================================================================
 * MSJOBS — Jobs Posted (List + Edit + Delete)  — Single File
 * - Filters by employer_id (ownership) + company_name LIKE '%ms human resource%'
 * - Secure: prepared statements, CSRF tokens, strict ownership checks
 * - UI: Bootstrap 5, mobile-first, modals for edit
 * - Uses jobs columns seen in your DB screenshots
 * ========================================================================= */

session_start();
require_once 'config.php'; // must define $conn (mysqli)

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'employer') {
    header('Location: login.php');
    exit;
}

$employer_id = (int) $_SESSION['user_id'];
$message = "";

/* --------------------- CSRF --------------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        die('<div class="alert alert-danger m-3">Invalid CSRF token. Please reload the page.</div>');
    }
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ----------------- Ownership guard -----------------
   Ensure the job belongs to this employer and matches the brand */
function owns_job(mysqli $conn, int $job_id, int $employer_id): bool {
    $pattern = '%ms human resource%'; // case-insensitive, catches variants
    $q = $conn->prepare("SELECT 1 FROM jobs WHERE id = ? AND employer_id = ? AND LOWER(company_name) LIKE LOWER(?) LIMIT 1");
    $q->bind_param("iis", $job_id, $employer_id, $pattern);
    $q->execute(); $q->store_result();
    $ok = ($q->num_rows === 1);
    $q->close();
    return $ok;
}

/* --------------------- DELETE --------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $job_id = (int) ($_POST['job_id'] ?? 0);

    if ($job_id && owns_job($conn, $job_id, $employer_id)) {
        $del = $conn->prepare("DELETE FROM jobs WHERE id = ? AND employer_id = ?");
        $del->bind_param("ii", $job_id, $employer_id);
        if ($del->execute()) {
            $message = '<div class="alert alert-success">Job deleted successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Failed to delete job.</div>';
        }
        $del->close();
    } else {
        $message = '<div class="alert alert-danger">Unauthorized or job not found.</div>';
    }
}

/* --------------------- UPDATE (EDIT) --------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    csrf_check();
    $job_id = (int) ($_POST['job_id'] ?? 0);

    if ($job_id && owns_job($conn, $job_id, $employer_id)) {
        // Collect inputs
        $title            = trim($_POST['title'] ?? '');
        $location         = trim($_POST['location'] ?? '');
        $type             = trim($_POST['type'] ?? '');
        $experience_level = trim($_POST['experience_level'] ?? '');
        $min_salary       = trim($_POST['min_salary'] ?? '0');
        $max_salary       = trim($_POST['max_salary'] ?? '0');
        $currency         = trim($_POST['currency'] ?? 'USD');
        $deadline         = trim($_POST['deadline'] ?? '');
        $category         = trim($_POST['category'] ?? '');
        $status           = trim($_POST['status'] ?? 'pending');
        $description      = trim($_POST['description'] ?? '');

        // Validate
        $errors = [];
        if ($title === '')    $errors[] = 'Title is required.';
        if ($location === '') $errors[] = 'Location is required.';

        $allowed_status = ['pending','approved','rejected'];
        if (!in_array($status, $allowed_status, true)) $status = 'pending';

        $allowed_curr = ['USD','AED','QAR','SAR','OMR','KWD','BHD','LKR'];
        if (!in_array($currency, $allowed_curr, true)) $currency = 'USD';

        if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $errors[] = 'Deadline must be YYYY-MM-DD.';
        }
        if (!is_numeric($min_salary)) $min_salary = '0';
        if (!is_numeric($max_salary)) $max_salary = '0';

        if (empty($errors)) {
            $sql = "UPDATE jobs SET
                        title = ?, location = ?, type = ?, experience_level = ?,
                        min_salary = ?, max_salary = ?, currency = ?, deadline = ?,
                        category = ?, status = ?, description = ?, updated_at = NOW()
                    WHERE id = ? AND employer_id = ?";
            $up = $conn->prepare($sql);
            // dd = double/double for decimal(10,2)
            $up->bind_param(
                "ssssddssssii",
                $title,
                $location,
                $type,
                $experience_level,
                $min_salary,    // double
                $max_salary,    // double
                $currency,
                $deadline,
                $category,
                $status,
                $description,
                $job_id,        // int
                $employer_id    // int
            );

            if ($up->execute()) {
                $message = '<div class="alert alert-success">Job updated successfully.</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to update job.</div>';
            }
            $up->close();
        } else {
            $message = '<div class="alert alert-danger"><ul class="mb-0 ps-3"><li>'.implode('</li><li>', array_map('h', $errors)).'</li></ul></div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Unauthorized or job not found.</div>';
    }
}

/* --------------------- FETCH LIST --------------------- */
$pattern = '%ms human resource%'; // catches "ms human resourc of consultancies.co", etc.
$list = $conn->prepare(
    "SELECT id, title, location, type, experience_level, min_salary, max_salary, currency,
            deadline, logo, category, description, status, created_at, company_name
     FROM jobs
     WHERE employer_id = ? AND LOWER(company_name) LIKE LOWER(?)
     ORDER BY created_at DESC"
);
$list->bind_param("is", $employer_id, $pattern);
$list->execute();
$jobs = $list->get_result();
$list->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Jobs You Posted — MSJOBS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root{--brand:#2563eb;--brand2:#0ea5e9;}
  .btn-brand{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border:0;}
  .btn-brand:hover{filter:brightness(.95);color:#fff;}
  .card-job{transition:all .2s ease-in-out;}
  .card-job:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(0,0,0,.08);}
  .logo-img{width:48px;height:48px;object-fit:cover;border-radius:8px;}
  .desc{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;}
</style>
</head>
<body class="bg-light">

<nav class="navbar navbar-light bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="company.php">
      <i class="bi bi-briefcase text-primary"></i>
      <span class="fw-semibold">Jobs You Posted</span>
    </a>
    <div class="d-flex gap-2">
      <a href="post_job.php" class="btn btn-sm btn-brand"><i class="bi bi-plus-lg me-1"></i>Post Job</a>
      <a href="company.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <?= $message ?>

  <?php if ($jobs->num_rows === 0): ?>
    <div class="alert alert-info">No jobs found for your company name containing <strong>“ms human resource”</strong>. If your company name is stored very differently, tell me the exact pattern and I’ll adjust the filter.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php while ($job = $jobs->fetch_assoc()): ?>
        <div class="col-12 col-md-6">
          <div class="card card-job border-0 shadow-sm h-100">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-center gap-3 mb-3">
                <?php if (!empty($job['logo'])): ?>
                  <img src="<?= h($job['logo']) ?>" class="logo-img" alt="Logo">
                <?php else: ?>
                  <div class="logo-img bg-light d-flex align-items-center justify-content-center">
                    <i class="bi bi-building text-secondary"></i>
                  </div>
                <?php endif; ?>
                <div>
                  <h5 class="mb-0"><?= h($job['title']) ?></h5>
                  <small class="text-muted"><?= h($job['location']) ?></small>
                </div>
              </div>

              <p class="text-muted desc mb-3"><?= h($job['description']) ?></p>

              <div class="row text-muted small">
                <div class="col-6 mb-1"><i class="bi bi-briefcase me-1"></i><?= h($job['type']) ?></div>
                <div class="col-6 mb-1"><i class="bi bi-mortarboard me-1"></i><?= h($job['experience_level'] ?: 'N/A') ?></div>
                <div class="col-6 mb-1"><i class="bi bi-cash-coin me-1"></i><?= h($job['currency']).' '.h($job['min_salary']).' - '.h($job['max_salary']) ?></div>
                <div class="col-6 mb-1"><i class="bi bi-calendar-event me-1"></i>Deadline: <?= h($job['deadline']) ?></div>
              </div>

              <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="badge <?= $job['status']==='approved'?'bg-success':($job['status']==='rejected'?'bg-danger':'bg-warning text-dark') ?>">
                  <?= h(ucfirst($job['status'])) ?>
                </span>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#editModal<?= (int)$job['id'] ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>
                  <form method="post" onsubmit="return confirm('Delete this job?');" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                  </form>
                </div>
              </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between small text-muted">
              <span><i class="bi bi-clock-history me-1"></i><?= date('M d, Y', strtotime($job['created_at'])) ?></span>
              <span class="text-truncate" title="<?= h($job['company_name']) ?>"><i class="bi bi-building me-1"></i><?= h($job['company_name']) ?></span>
            </div>
          </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= (int)$job['id'] ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">

                <div class="modal-header">
                  <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i>Edit: <?= h($job['title']) ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-12 col-md-8">
                      <label class="form-label">Title</label>
                      <input name="title" type="text" class="form-control" value="<?= h($job['title']) ?>" required>
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label">Status</label>
                      <select name="status" class="form-select">
                        <option value="pending"  <?= $job['status']==='pending'?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $job['status']==='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $job['status']==='rejected'?'selected':'' ?>>Rejected</option>
                      </select>
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">Location</label>
                      <input name="location" type="text" class="form-control" value="<?= h($job['location']) ?>" required>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label">Type</label>
                      <input name="type" type="text" class="form-control" value="<?= h($job['type']) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label">Experience</label>
                      <input name="experience_level" type="text" class="form-control" value="<?= h($job['experience_level']) ?>">
                    </div>

                    <div class="col-6 col-md-3">
                      <label class="form-label">Min Salary</label>
                      <input name="min_salary" type="number" step="0.01" class="form-control" value="<?= h($job['min_salary']) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label">Max Salary</label>
                      <input name="max_salary" type="number" step="0.01" class="form-control" value="<?= h($job['max_salary']) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label">Currency</label>
                      <select name="currency" class="form-select">
                        <?php foreach (['USD','AED','QAR','SAR','OMR','KWD','BHD','LKR'] as $c): ?>
                          <option value="<?= h($c) ?>" <?= $job['currency']===$c?'selected':'' ?>><?= h($c) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label">Deadline</label>
                      <input name="deadline" type="date" class="form-control" value="<?= h($job['deadline']) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label">Category</label>
                      <input name="category" type="text" class="form-control" value="<?= h($job['category']) ?>">
                    </div>

                    <div class="col-12">
                      <label class="form-label">Description</label>
                      <textarea name="description" rows="6" class="form-control"><?= h($job['description']) ?></textarea>
                    </div>
                  </div>
                </div>

                <div class="modal-footer">
                  <button class="btn btn-brand" type="submit"><i class="bi bi-save me-1"></i>Save Changes</button>
                  <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
                </div>

              </form>
            </div>
          </div>
        </div>
        <!-- /Edit Modal -->
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>

<footer class="text-center text-muted py-4 small">
  &copy; MSJOBS — <?= date('Y') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
