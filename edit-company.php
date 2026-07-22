<?php
/* ==========================================================
 * MSJOBS — Admin: Edit Company Profile
 * - Secure: Super admin session guard, CSRF token, prepared statements
 * - Editable: company_name, company_description, contact_person, phone, country
 * - UI: Tailwind CSS + Font Awesome (Admin style)
 * ========================================================== */

session_start();
require_once 'config.php'; // must set $conn = new mysqli(...)

// Security: Check for super admin session
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$message = "";
$employer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($employer_id <= 0) {
    die("Invalid Company ID.");
}

/* ---------- CSRF token ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- Fetch current company data ---------- */
$employer = null;
if ($stmt = $conn->prepare("SELECT company_name, company_description, contact_person, phone, country, logo FROM employers WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $employer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $employer = $row;
    }
    $stmt->close();
}

if (!$employer) {
    die("Company not found.");
}

/* ---------- Handle update ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company_full'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $message = "<div class='bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6'>Security check failed. Please refresh.</div>";
    } else {
        // Sanitization
        $company_name = trim($_POST['company_name'] ?? '');
        $company_description = trim($_POST['company_description'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $country        = trim($_POST['country'] ?? '');
        $current_logo   = $employer['logo'];

        // Validation
        $errors = [];
        if ($company_name === '') $errors[] = "Company Name is required.";
        if ($contact_person === '') $errors[] = "Contact person is required.";
        if ($phone === '') $errors[] = "Enter a valid phone number.";

        // Handle Logo Upload
        $new_logo = $current_logo;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_name = basename($_FILES['logo']['name']);
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed_exts)) {
                $errors[] = "Invalid logo file type. Allowed: " . implode(', ', $allowed_exts);
            } else {
                $target_dir = "uploads/logos/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $new_logo = $target_dir . time() . "_" . preg_replace("/[^a-zA-Z0-9_\.-]/", "_", $file_name);
                if (!move_uploaded_file($file_tmp, $new_logo)) {
                    $errors[] = "Failed to upload new logo.";
                }
            }
        }

        if (empty($errors)) {
            if ($up = $conn->prepare("UPDATE employers SET company_name = ?, company_description = ?, contact_person = ?, phone = ?, country = ?, logo = ? WHERE id = ?")) {
                $up->bind_param("ssssssi", $company_name, $company_description, $contact_person, $phone, $country, $new_logo, $employer_id);
                if ($up->execute()) {
                    $message = "<div class='bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6'><i class='fa-solid fa-circle-check mr-2'></i> Company profile updated successfully.</div>";
                    // Refresh data
                    $employer['company_name'] = $company_name;
                    $employer['company_description'] = $company_description;
                    $employer['contact_person'] = $contact_person;
                    $employer['phone'] = $phone;
                    $employer['country'] = $country;
                    $employer['logo'] = $new_logo;
                } else {
                    $message = "<div class='bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6'>Failed to update profile. Please try again.</div>";
                }
                $up->close();
            } else {
                $message = "<div class='bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6'>Something went wrong. Please try later.</div>";
            }
        } else {
            $message = "<div class='bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6'><ul class='list-disc list-inside'><li>" . implode("</li><li>", array_map('htmlspecialchars', $errors)) . "</li></ul></div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin - Edit Company</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
          <h1 class="text-white text-lg sm:text-xl font-bold tracking-wide">MSJOBS — Admin / Edit Company</h1>
        </div>
        <a href="viewcompany.php" class="text-white/90 hover:text-white text-sm font-medium flex items-center gap-2">
          <i class="fa-solid fa-arrow-left"></i> Back to Companies
        </a>
      </div>
    </div>
  </nav>

  <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-6 border-b border-slate-100 flex items-center gap-4 bg-slate-50/50">
        <div class="relative group">
          <img src="<?= htmlspecialchars($employer['logo'] ?: 'https://placehold.co/160x160?text=Logo') ?>" 
               alt="Logo" class="h-20 w-20 rounded-xl object-cover ring-1 ring-slate-200 shadow-sm">
          <div class="absolute inset-0 rounded-xl bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
             <i class="fa-solid fa-camera text-white"></i>
          </div>
        </div>
        <div>
          <h2 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($employer['company_name']) ?></h2>
          <p class="text-slate-500 text-sm">Update company information and contact details</p>
        </div>
      </div>

      <div class="p-8">
        <?= $message ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-slate-700 mb-2">Company Name</label>
              <input type="text" name="company_name" value="<?= htmlspecialchars($employer['company_name']) ?>" 
                     placeholder="Enter company name"
                     class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-slate-700 mb-2">Company Description</label>
              <textarea name="company_description" rows="5" 
                        placeholder="Describe the company..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"><?= htmlspecialchars($employer['company_description']) ?></textarea>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-slate-700 mb-2">Company Logo</label>
              <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-200 border-dashed rounded-xl hover:border-indigo-400 transition-colors">
                <div class="space-y-1 text-center">
                  <i class="fa-solid fa-image text-slate-400 text-3xl mb-2"></i>
                  <div class="flex text-sm text-slate-600">
                    <label for="logo-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                      <span>Upload a file</span>
                      <input id="logo-upload" name="logo" type="file" class="sr-only" accept="image/*">
                    </label>
                    <p class="pl-1">or drag and drop</p>
                  </div>
                  <p class="text-xs text-slate-500">PNG, JPG, GIF up to 10MB</p>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Contact Person</label>
              <input type="text" name="contact_person" value="<?= htmlspecialchars($employer['contact_person'] ?? '') ?>" 
                     placeholder="Name of contact person"
                     class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
            </div>

            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Phone</label>
              <input type="text" name="phone" value="<?= htmlspecialchars($employer['phone'] ?? '') ?>" 
                     placeholder="Phone number"
                     class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
            </div>

            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Country</label>
              <input type="text" name="country" value="<?= htmlspecialchars($employer['country'] ?? '') ?>" 
                     placeholder="Country"
                     class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
            </div>
          </div>

          <div class="flex items-center gap-4 pt-4">
            <button type="submit" name="update_company_full" 
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-8 py-3.5 text-sm font-bold text-white hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-500/20 transition-all shadow-md active:scale-[0.98]">
              <i class="fa-solid fa-save"></i> Save Changes
            </button>
            <a href="viewcompany.php" 
               class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-8 py-3.5 text-sm font-bold text-slate-700 hover:bg-slate-200 transition-all">
              Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </main>

</body>
</html>
