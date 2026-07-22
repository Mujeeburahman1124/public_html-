<?php
session_start();
$conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = $_POST['email'];
$password = $_POST['password'];

$stmt = $conn->prepare("
    SELECT users.id, users.password, users.user_type, employers.status 
    FROM users
    LEFT JOIN employers ON users.id = employers.user_id
    WHERE users.email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        // Check if employer is blocked due to cheating status
        if ($user['user_type'] === 'employer' && isset($user['status']) && $user['status'] === 'cheating') {
            echo "<script>alert('Your company has been blocked due to policy violations. You cannot login.'); window.location.href='login';</script>";
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];

        // Redirect based on role
        if ($user['user_type'] == 'jobseeker') {
            header("Location: employee_dashboard");
        } elseif ($user['user_type'] == 'employer') {
            header("Location: company");
        } 
        exit;
    } else {
        echo "<script>alert('Invalid password!'); window.location.href='login';</script>";
    }
} else {
    echo "<script>alert('User not found!'); window.location.href='login';</script>";
}

$stmt->close();
$conn->close();
?>
