<?php
session_start();
include 'config.php';

// Check if jobseeker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'jobseeker') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch current profile info
$query = $conn->prepare("SELECT full_name, nationality, language, experience, profile_picture FROM jobseekers WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$jobseeker = $result->fetch_assoc();

// Update profile on form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $nationality = $_POST['nationality'];
    $language = $_POST['language'];
    $experience = $_POST['experience'];

    // Handle profile picture upload
    if ($_FILES['profile_picture']['name']) {
        $target_dir = "uploads/";
        $file_name = basename($_FILES["profile_picture"]["name"]);
        $target_file = $target_dir . time() . "_" . $file_name;
        move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file);
    } else {
        $target_file = $jobseeker['profile_picture']; // keep existing
    }

    // Update the database
    $update = $conn->prepare("UPDATE jobseekers SET full_name = ?, nationality = ?, language = ?, experience = ?, profile_picture = ? WHERE user_id = ?");
    $update->bind_param("sssssi", $full_name, $nationality, $language, $experience, $target_file, $user_id);
    $update->execute();

    if ($update) {
        header("Location: employee_dashboard.php?success=1");
        exit;
    } else {
        echo "Error updating profile: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f2f2f2;
            padding: 50px;
        }

        .container {
            max-width: 500px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="file"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        .btn {
            background: #4CAF50;
            color: #fff;
            padding: 12px;
            width: 100%;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn:hover {
            background: #45a049;
        }

        .profile-img {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-img img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4CAF50;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #333;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Profile</h2>

    <form method="post" enctype="multipart/form-data">
        <div class="profile-img">
            <img src="<?php echo htmlspecialchars($jobseeker['profile_picture']); ?>" alt="Current Picture">
        </div>

        <label for="full_name">Full Name</label>
        <input type="text" name="full_name" required value="<?php echo htmlspecialchars($jobseeker['full_name']); ?>">

        <label for="nationality">Nationality</label>
        <input type="text" name="nationality" required value="<?php echo htmlspecialchars($jobseeker['nationality']); ?>">

        <label for="language">Language</label>
        <input type="text" name="language" required value="<?php echo htmlspecialchars($jobseeker['language']); ?>">

        <label for="experience">Experience (Years)</label>
        <input type="number" name="experience" required min="0" value="<?php echo htmlspecialchars($jobseeker['experience']); ?>">

        <label for="profile_picture">Change Profile Picture</label>
        <input type="file" name="profile_picture">

        <button type="submit" class="btn">Update Profile</button>
    </form>

    <a class="back-link" href="employee_dashboard.php">← Back to Dashboard</a>
</div>

</body>
</html>
