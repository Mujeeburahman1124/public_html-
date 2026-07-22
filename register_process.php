<?php
// Enable error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once __DIR__ . '/config.php'; // $conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper function to sanitize input
function sanitize($conn, $data) {
    return htmlspecialchars($conn->real_escape_string(trim($data)));
}

// Check if form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

// Collect and sanitize common user info
$email = isset($_POST['email']) ? sanitize($conn, $_POST['email']) : '';
$password = $_POST['password'] ?? '';
$role = isset($_POST['user_type']) ? sanitize($conn, $_POST['user_type']) : '';

if (!$email || !$password || !$role) {
    die("Missing required fields: email, password, and user role are required.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format.");
}
if (!in_array($role, ['jobseeker', 'employer'])) {
    die("Invalid role selected.");
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    die("Email is already registered.");
}
$stmt->close();

// Hash the password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Insert into users table
$stmt = $conn->prepare("INSERT INTO users (email, password, user_type) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $hashed_password, $role);
$stmt->execute();
$user_id = $stmt->insert_id;
$stmt->close();

if (!$user_id) {
    die("Failed to register user.");
}

// File upload handler
function upload_file($file_input_name, $target_dir) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($ext, $allowed_exts)) {
            die("File type not allowed for $file_input_name.");
        }

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $new_filename = $target_dir . time() . "_" . preg_replace("/[^a-zA-Z0-9_\.-]/", "_", $file_name);
        if (move_uploaded_file($file_tmp, $new_filename)) {
            return $new_filename;
        } else {
            die("Failed to upload file: $file_input_name.");
        }
    }
    return '';
}

// JOBSEEKER REGISTRATION
if ($role === 'jobseeker') {
    $full_name = sanitize($conn, $_POST['full_name'] ?? '');
    $nationality = sanitize($conn, $_POST['nationality'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $language = sanitize($conn, $_POST['language'] ?? '');
    $religion = sanitize($conn, $_POST['religion'] ?? '');
    $experience = sanitize($conn, $_POST['experience'] ?? '');
    $country = sanitize($conn, $_POST['country'] ?? '');
    $gender = sanitize($conn, $_POST['gender'] ?? '');
    $position = sanitize($conn, $_POST['position'] ?? '');
    $salary_range = sanitize($conn, $_POST['salary_range'] ?? '');
    $expected_position = sanitize($conn, $_POST['expected_position'] ?? '');
    $current_job_status = sanitize($conn, $_POST['current_job_status'] ?? '');
    $whatsapp = sanitize($conn, $_POST['whatsapp'] ?? '');
    $category = sanitize($conn, $_POST['category'] ?? ''); 
    $present_location = sanitize($conn, $_POST['present_location'] ?? '');
    // New category field

    // Handle expected countries
    if (isset($_POST['expected_countries']) && is_array($_POST['expected_countries'])) {
        $expected_countries_array = array_map(function($c) use ($conn) {
            return sanitize($conn, $c);
        }, $_POST['expected_countries']);
        $expected_countries = implode(',', $expected_countries_array);
    } else {
        $expected_countries = '';
    }

    if (
    !$full_name || !$nationality || !$age || !$language || !$religion || !$experience || !$country ||
    !$gender || !$position || !$salary_range || !$expected_position || !$current_job_status ||
    !$expected_countries || !$whatsapp || !$category || !$present_location
) {
    die("Missing required jobseeker fields.");
}

    $cv_file = upload_file('cv_file', 'uploads/cvs/');
    $profile_picture = upload_file('profile_picture', 'uploads/profile_pictures/');

  $stmt = $conn->prepare("
    INSERT INTO jobseekers 
    (user_id, full_name, nationality, age, language, religion, experience, country, gender, position, salary_range, expected_position, current_job_status, expected_countries, whatsapp, cv_file, profile_picture, category, present_location) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");


    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

  $stmt->bind_param(
    "ississsssssssssssss", // ✔️ 19 types for 19 variables
    $user_id,
    $full_name,
    $nationality,
    $age,
    $language,
    $religion,
    $experience,
    $country,
    $gender,
    $position,
    $salary_range,
    $expected_position,
    $current_job_status,
    $expected_countries,
    $whatsapp,
    $cv_file,
    $profile_picture,
    $category,
    $present_location
);



    if ($stmt->execute()) {
        echo "Jobseeker registration successful!";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// EMPLOYER REGISTRATION
if ($role === 'employer') {
    $company_name = sanitize($conn, $_POST['company_name'] ?? '');
    $company_description = sanitize($conn, $_POST['company_description'] ?? '');
    $contact_person = sanitize($conn, $_POST['contact_person'] ?? '');
    $phone = sanitize($conn, $_POST['phone'] ?? '');
    $employer_country = sanitize($conn, $_POST['employer_country'] ?? '');

    if (!$company_name || !$company_description || !$contact_person || !$phone || !$employer_country) {
        die("Missing required employer fields.");
    }

    $logo = upload_file('logo', 'uploads/logos/');
    $company_license = upload_file('company_license', 'uploads/licenses/');

    $stmt = $conn->prepare("
        INSERT INTO employers 
        (user_id, company_name, company_description, contact_person, phone, country, logo, company_license) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "isssssss",
        $user_id,
        $company_name,
        $company_description,
        $contact_person,
        $phone,
        $employer_country,
        $logo,
        $company_license
    );
    $stmt->execute();
    $stmt->close();
}

$conn->close();

echo "<script>
    alert('Registration successful! Please login.');
    window.location.href = 'login.php';
</script>";
exit;
?>
