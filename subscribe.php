<?php
session_start();
$conn = new mysqli("localhost", "root", "", "exaple");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Simulated logged-in user (use session in real case)
$user_id = $_SESSION['user_id'] ?? 2; // default demo user

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assume payment was successful
    $expiry_date = date('Y-m-d', strtotime("+30 days"));

    // Insert or update subscription
    $stmt = $conn->prepare("REPLACE INTO subscriptions (user_id, status, expiry_date) VALUES (?, 'active', ?)");
    $stmt->bind_param("is", $user_id, $expiry_date);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Subscription activated successfully!'); window.location.href = 'jobseekers.php';</script>";
    exit;
}

// Check existing subscription
$subscription = null;
$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$subscription = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscribe for Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4 text-center">Subscribe to Access Jobseeker Database</h2>

        <?php if ($subscription && $subscription['status'] === 'active' && $subscription['expiry_date'] >= date('Y-m-d')): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
                You have an active subscription until <strong><?= htmlspecialchars($subscription['expiry_date']) ?></strong>.
                <br><a href="jobseekers.php" class="text-blue-600 underline">Go to Jobseeker Database</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <p class="mb-4 text-gray-700">Subscribe for 30 days access to the jobseeker database. <br><strong>Price: $10</strong></p>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                    Pay Now & Activate Subscription
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
