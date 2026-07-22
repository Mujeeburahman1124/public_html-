<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['company_logged_in']) || $_SESSION['company_logged_in'] !== true) {
    echo "<script>alert('Please log in to access this page.'); window.location.href = 'login.php';</script>";
    exit();
}

// DB Connection
require_once __DIR__ . '/config.php'; // $conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$employer_id = $_SESSION['user_id'];

// Fetch employer view limit
$limitQuery = $conn->prepare("SELECT view_limit FROM employer_limits WHERE employer_id = ?");
$limitQuery->bind_param("i", $employer_id);
$limitQuery->execute();
$limitResult = $limitQuery->get_result();
$view_limit = $limitResult->fetch_assoc()['view_limit'] ?? 0;

// Count jobseeker views
$countQuery = $conn->prepare("SELECT COUNT(*) AS viewed FROM jobseeker_views WHERE employer_id = ?");
$countQuery->bind_param("i", $employer_id);
$countQuery->execute();
$countResult = $countQuery->get_result();
$viewed_count = $countResult->fetch_assoc()['viewed'];

// Record view and fetch jobseeker profile
$selectedUser = null;
if (isset($_GET['id'])) {
    $jobseeker_id = intval($_GET['id']);

    $checkStmt = $conn->prepare("SELECT id FROM jobseeker_views WHERE employer_id = ? AND jobseeker_id = ?");
    $checkStmt->bind_param("ii", $employer_id, $jobseeker_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0 && $viewed_count < $view_limit) {
        $logStmt = $conn->prepare("INSERT INTO jobseeker_views (employer_id, jobseeker_id) VALUES (?, ?)");
        $logStmt->bind_param("ii", $employer_id, $jobseeker_id);
        $logStmt->execute();
    } elseif ($viewed_count >= $view_limit) {
        echo "<script>alert('View limit reached. You cannot view more jobseeker profiles.'); window.location.href = 'Jobseekers.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("SELECT j.*, u.created_at FROM jobseekers j JOIN users u ON j.user_id = u.id WHERE j.user_id = ?");
    $stmt->bind_param("i", $jobseeker_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $selectedUser = $res->fetch_assoc();
}

// Filtering conditions
$where = [];
$params = [];
$types = '';

if (!empty($_GET['name'])) {
    $where[] = "j.full_name LIKE ?";
    $params[] = '%' . $_GET['name'] . '%';
    $types .= 's';
}
if (!empty($_GET['gender'])) {
    $where[] = "j.gender = ?";
    $params[] = $_GET['gender'];
    $types .= 's';
}
if (!empty($_GET['country'])) {
    $where[] = "j.country LIKE ?";
    $params[] = '%' . $_GET['country'] . '%';
    $types .= 's';
}
if (!empty($_GET['age_range']) && strpos($_GET['age_range'], '-') !== false) {
    list($minAge, $maxAge) = explode('-', $_GET['age_range']);
    $where[] = "j.age BETWEEN ? AND ?";
    $params[] = (int)$minAge;
    $params[] = (int)$maxAge;
    $types .= 'ii';
}
if (!empty($_GET['salary_range'])) {
    $where[] = "j.salary_range = ?";
    $params[] = $_GET['salary_range'];
    $types .= 's';
}
if (!empty($_GET['salary_expectation'])) {
    $where[] = "j.salary_expectation = ?";
    $params[] = $_GET['salary_expectation'];
    $types .= 's';
}
if (!empty($_GET['position'])) {
    $where[] = "j.position = ?";
    $params[] = $_GET['position'];
    $types .= 's';
}
if (!empty($_GET['category'])) {
    $where[] = "j.category = ?";
    $params[] = $_GET['category'];
    $types .= 's';
}
if (!empty($_GET['present_location'])) {
    $where[] = "j.present_location = ?";
    $params[] = $_GET['present_location'];
    $types .= 's';
}
if (!empty($_GET['experience'])) {
    $exp = $_GET['experience'];
    if ($exp === '0-1') {
        $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) <= 1";
    } elseif ($exp === '1-3') {
        $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) BETWEEN 1 AND 3";
    } elseif ($exp === '3-5') {
        $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) BETWEEN 3 AND 5";
    } elseif ($exp === '5+') {
        $where[] = "CAST(SUBSTRING_INDEX(j.experience, ' ', 1) AS UNSIGNED) >= 5";
    }
}
if (!empty($_GET['expected_countries']) && is_array($_GET['expected_countries'])) {
    $expectedConditions = [];
    foreach ($_GET['expected_countries'] as $country) {
        $expectedConditions[] = "FIND_IN_SET(?, j.expected_countries) > 0";
        $params[] = $country;
        $types .= 's';
    }
    if ($expectedConditions) {
        $where[] = '(' . implode(' OR ', $expectedConditions) . ')';
    }
}

$sql = "SELECT j.*, u.created_at FROM jobseekers j JOIN users u ON j.user_id = u.id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>


<!-- HTML remains unchanged (same as your template above) -->

<!-- Insert your existing HTML page here from <html> to </html> -->


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Jobseekers Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<h1 class="text-3xl font-bold text-blue-700 mb-6 text-center uppercase">
        MS JOBS JOBSEEKER DATA BASE
    </h1>
    <div class="p-4">
    <a href="SuperAdmin.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        &larr; Back
    </a>
</div>

<div class="p-4">
    <a href="company.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        &larr; Home 
    </a>
</div>

<div class="max-w-7xl mx-auto py-10 px-6">
   <form method="GET" class="bg-white p-6 rounded-xl shadow mb-10 grid grid-cols-1 md:grid-cols-7 gap-4">
        <!--<input type="text" name="name" placeholder="Name"-->
        <!--       value="<?= htmlspecialchars($_GET['name'] ?? '') ?>"-->
               <!--class="border p-2 rounded-md md:col-span-1">-->

        <select name="gender" class="border p-2 rounded-md md:col-span-1">
            <option value="">Gender</option>
            <option value="Male" <?= ($_GET['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= ($_GET['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
        
        
        <input list="positions" name="position" class="border p-2 rounded-md md:col-span-1" placeholder="Search position" value="<?= htmlspecialchars($_GET['position'] ?? '') ?>">

<datalist id="positions">
    <?php
    // Fetch distinct job titles (positions) from the database
    $posResult = $conn->query("SELECT DISTINCT title FROM jobs WHERE status='approved' ORDER BY title ASC");
    while ($pos = $posResult->fetch_assoc()):
        echo "<option value=\"" . htmlspecialchars($pos['title']) . "\">";
    endwhile;
    ?>
</datalist>


        <select name="country" class="border p-2 rounded-md md:col-span-1">
            <option value="">Select Country</option>
            <?php
            $countries = [ "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina",
                "Armenia", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados",
                "Belarus", "Belgium", "Belize", "Benin", "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana",
                "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada",
                "Cape Verde", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros",
                "Congo (Brazzaville)", "Congo (Kinshasa)", "Costa Rica", "Croatia", "Cuba", "Cyprus",
                "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt",
                "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji",
                "Finland", "France", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada",
                "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hungary", "Iceland",
                "India", "Indonesia", "Iran", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan",
                "Kazakhstan", "Kenya", "Kiribati", "Korea (North)", "Korea (South)", "Kuwait", "Kyrgyzstan",
                "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania",
                "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands",
                "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro",
                "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Zealand",
                "Nicaragua", "Niger", "Nigeria", "North Macedonia", "Norway", "Oman", "Pakistan", "Palau",
                "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar",
                "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines",
                "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles",
                "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa",
                "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan",
                "Tanzania", "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey",
                "Turkmenistan", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom",
                "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam",
                "Yemen", "Zambia", "Zimbabwe"];
            $selectedCountry = $_GET['country'] ?? '';
            foreach ($countries as $country) {
                $selected = ($selectedCountry === $country) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($country) . '" ' . $selected . '>' . htmlspecialchars($country) . '</option>';
            }
            ?>
        </select>

   <select name="age_range" class="border p-2 rounded-md md:col-span-1">
    <option value="">Select Age Range</option>
    <?php
    $ranges = ["18-20", "18-25", "18-30", "18-35", "18-40", "18-45", "18-50", "18-55", "18-60", "18-65"];
    $selectedRange = $_GET['age_range'] ?? '';
    foreach ($ranges as $range) {
        $selected = ($selectedRange === $range) ? 'selected' : '';
        echo "<option value=\"$range\" $selected>$range</option>";
    }
    ?>
</select>


        <select name="experience" class="border p-2 rounded-md md:col-span-1">
            <option value="">Experience</option>
            <option value="0-1" <?= ($_GET['experience'] ?? '') === '0-1' ? 'selected' : '' ?>>0-1 year</option>
            <option value="1-3" <?= ($_GET['experience'] ?? '') === '1-3' ? 'selected' : '' ?>>1-3 years</option>
            <option value="3-5" <?= ($_GET['experience'] ?? '') === '3-5' ? 'selected' : '' ?>>3-5 years</option>
            <option value="5+" <?= ($_GET['experience'] ?? '') === '5+' ? 'selected' : '' ?>>5+ years</option>
        </select>
        
        <select name="category" class="border p-2 rounded-md md:col-span-1">
    <option value="">Select Category</option>
    <?php
    $categories = [
        "Cleaning & Hospitality", "Engineering & Contractions", "Maintenance", "Manufacturing",
        "Hotels & Restaurants", "Transportation", "Delivery Service", "Helpers",
        "Accounting & Finance", "Auto Mobile", "Beauty/Salon", "Customer Service / Call Center",
        "Data Management & Analyst", "Graphic Designer", "Admin & HR", "Sales / Business Development",
        "Secretarial / Front Office", "Security Guard", "Sports & Fitness", "Travel & Tourism",
        "Medical & Health Care", "Media, Art & Entertainment", "Marketing & Advertising",
        "Marine Captain / Crew", "Logistics & Distribution", "Legal Services", "Education",
        "Drivers", "hypermarket", "supermarket", "Other"
    ];

    foreach ($categories as $cat) {
        $selected = ($_GET['category'] ?? '') === $cat ? 'selected' : '';
        echo "<option value=\"" . htmlspecialchars($cat) . "\" $selected>" . htmlspecialchars($cat) . "</option>";
    }
    ?>
</select>


        <select name="salary_range" class="border p-2 rounded-md md:col-span-1">
    <option value="">Select Salary Range (USD)</option>
    <?php
    $salaryRanges = [
        "500 - 1000", "1000 - 1500", "1500 - 2000",
        "2000 - 3000", "3000 - 5000", "5000 - 7000", "7000 - 10000"
    ];
    $selectedSalaryRange = $_GET['salary_range'] ?? '';
    foreach ($salaryRanges as $range) {
        $display = "$" . str_replace(" - ", " - $", $range) . " USD";
        $selected = ($selectedSalaryRange === $range) ? 'selected' : '';
        echo "<option value=\"$range\" $selected>$display</option>";
    }
    ?>
</select>


        <!-- Expected Countries Multi-select -->
       <select name="expected_countries[]" multiple size="10" class="border p-2 rounded-md md:col-span-2">
    <option disabled>Expected Countries (multi-select)</option>
    <?php
    $expectedCountryOptions = [
        "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina",
        "Armenia", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados",
        "Belarus", "Belgium", "Belize", "Benin", "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana",
        "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada",
        "Cape Verde", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros",
        "Congo (Brazzaville)", "Congo (Kinshasa)", "Costa Rica", "Croatia", "Cuba", "Cyprus", "Czech Republic",
        "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt",
        "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland",
        "France", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala",
        "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India",
        "Indonesia", "Iran", "Iraq", "Ireland", "Israel", "Italy", "Ivory Coast", "Jamaica", "Japan",
        "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, North", "Korea, South", "Kosovo", "Kuwait",
        "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein",
        "Lithuania", "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta",
        "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova", "Monaco",
        "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal",
        "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Macedonia", "Norway",
        "Oman", "Pakistan", "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru",
        "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis",
        "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe",
        "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia",
        "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Sudan", "Spain", "Sri Lanka",
        "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan", "Tanzania",
        "Thailand", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan",
        "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States",
        "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", "Yemen", "Zambia",
        "Zimbabwe"
    ];
    $selectedExpectedCountries = $_GET['expected_countries'] ?? [];
    if (!is_array($selectedExpectedCountries)) {
        $selectedExpectedCountries = [$selectedExpectedCountries];
    }
    foreach ($expectedCountryOptions as $country) {
        $selected = in_array($country, $selectedExpectedCountries) ? 'selected' : '';
        echo "<option value=\"" . htmlspecialchars($country) . "\" $selected>" . htmlspecialchars($country) . "</option>";
    }
    ?>
</select>

        <!-- Submit Button -->
        <div class="md:col-span-1 flex items-center">
            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 w-full">
                Filter
            </button>
        </div>
    </form>

    <div class="grid md:grid-cols-2 gap-8">
        <!-- Jobseeker List -->
        <div class="bg-white p-6 rounded-xl shadow">
            <h2 class="text-lg font-bold text-blue-700 mb-4">All Jobseekers</h2>
            <div class="overflow-y-auto max-h-[600px]">
                <table class="w-full text-sm text-left border border-gray-200">
                    <thead class="bg-blue-600 text-white text-xs uppercase">
                        <tr>
                            <th class="px-4 py-2">ID</th>
                            <th class="px-4 py-2">Full Name</th>
                            <th class="px-4 py-2">Country</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="border-b hover:bg-blue-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['id']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['country']) ?></td>
                                    <td class="px-4 py-2">
                                        <a href="?id=<?= urlencode($row['user_id']) ?>" class="text-blue-600 hover:underline">View</a>
                                         <a href="?delete_id=<?= urlencode($row['user_id']) ?>" onclick="return confirm('Are you sure you want to delete this jobseeker?');" class="ml-4 text-red-600 hover:underline">Delete</a>
                                    </td> 
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-red-600 p-4">No jobseekers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Profile View -->
        <div class="bg-white p-6 rounded-xl shadow">
            <?php if ($selectedUser): ?>
                <h2 class="text-lg font-bold text-green-700 mb-4"><?= htmlspecialchars($selectedUser['full_name']) ?>'s Profile</h2>

                <?php if (!empty($selectedUser['profile_picture']) && file_exists($selectedUser['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($selectedUser['profile_picture']) ?>" class="w-32 h-32 rounded-full mb-4 border shadow-md" alt="Profile Picture">
                <?php else: ?>
                    <div class="w-32 h-32 bg-gray-200 rounded-full flex items-center justify-center mb-4">No Photo</div>
                <?php endif; ?>

             <div class="space-y-2 text-sm">
    <p><strong>Gender:</strong> <?= htmlspecialchars($selectedUser['gender']) ?></p>
    <p><strong>Created At:</strong> <?= htmlspecialchars($selectedUser['created_at']) ?></p>
    <p><strong>Nationality:</strong> <?= htmlspecialchars($selectedUser['nationality']) ?></p>
    <p><strong>Age:</strong> <?= htmlspecialchars($selectedUser['age']) ?></p>
    <p><strong>Language:</strong> <?= htmlspecialchars($selectedUser['language']) ?></p>
    <p><strong>Country:</strong> <?= htmlspecialchars($selectedUser['country']) ?></p>
    <p><strong>Experience:</strong> <?= htmlspecialchars($selectedUser['experience']) ?></p>
    <p><strong>position:</strong> <?= htmlspecialchars($selectedUser['position']) ?></p>
    <p><strong>salaryRange:</strong> <?= htmlspecialchars($selectedUser['salary_range']) ?></p>
    <p><strong>current Job Status:</strong> <?= htmlspecialchars($selectedUser['current_job_status']) ?></p>
    <p><strong>category:</strong> <?= htmlspecialchars($selectedUser['category']) ?></p>

    <?php if (!empty($selectedUser['whatsapp'])): ?>
        <?php
            $whatsappNumber = preg_replace('/\D/', '', $selectedUser['whatsapp']); // Clean up number
            $message = urlencode("Hello, I am contacting you regarding your job application.");
            $whatsappLink = "https://wa.me/{$whatsappNumber}?text={$message}";
        ?>
        <p>
            <strong>WhatsApp:</strong>
            <a href="<?= $whatsappLink ?>" target="_blank" class="text-blue-600 underline">
                <?= htmlspecialchars($selectedUser['whatsapp']) ?>
            </a>
        </p>
    <?php endif; ?>
</div>


                <hr class="my-4">

                <?php if (!empty($selectedUser['cv_file']) && file_exists($selectedUser['cv_file'])): ?>
                    <p class="font-semibold text-sm mb-2">CV Preview:</p>
                    <iframe src="<?= htmlspecialchars($selectedUser['cv_file']) ?>" width="100%" height="500px" class="rounded border"></iframe>
                    <a href="<?= htmlspecialchars($selectedUser['cv_file']) ?>" download class="mt-2 inline-block text-blue-600 hover:underline">Download CV</a>
                <?php else: ?>
                    <p class="text-red-600 font-semibold">CV not available.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Click <strong>View</strong> to see jobseeker details and CV preview.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
