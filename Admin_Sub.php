<?php
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: admin_login.php");
//     exit();
// }

// DB Connection
$servername = "127.0.0.1";
$username = "u903588615_root";
$password = "Msjobs#1";
$database = "u903588615_exaple";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $status = trim($_POST['subscription_status']);
    $expiry = trim($_POST['expiry_date']);
    $limit = intval($_POST['users_limit']);

    if (!empty($status) && !empty($expiry)) {
        $stmt = $conn->prepare("UPDATE users SET subscription_status = ?, expiry_date = ?, users_limit = ? WHERE id = ?");
        $stmt->bind_param("ssii", $status, $expiry, $limit, $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch All Companies ONLY (exclude jobseekers)
$sql = "
    SELECT users.id, users.email, users.subscription_status, users.expiry_date, users.users_limit, employers.company_name
    FROM users
    INNER JOIN employers ON users.id = employers.user_id
    ORDER BY users.id DESC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Panel - Manage Subscriptions</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-100 via-purple-100 to-pink-100 min-h-screen py-10">
    <div class="max-w-7xl mx-auto bg-white shadow-2xl rounded-lg p-10">
        <h2 class="text-4xl font-extrabold text-center text-gray-800 mb-10">
            Admin Panel – Manage Company Subscriptions
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300 rounded-lg text-gray-800">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-6 py-3 text-left">Company Name</th>
                        <th class="px-6 py-3 text-left">Email</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-left">Expiry Date</th>
                        <!--<th class="px-6 py-3 text-left">User Limit</th>-->
                        <th class="px-6 py-3 text-left">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition-all duration-150">
                            <form method="POST">
                                <!-- hidden user id for update -->
                                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                <td class="px-6 py-4 font-medium">
                                    <?= htmlspecialchars($row['company_name'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?= htmlspecialchars($row['email']) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <select name="subscription_status" class="border px-3 py-2 rounded w-full <?= $row['subscription_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <option value="active" <?= $row['subscription_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $row['subscription_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4">
                                    <input type="date" name="expiry_date" value="<?= htmlspecialchars($row['expiry_date']) ?>" class="border px-3 py-2 rounded w-full bg-blue-50" required>
                                </td>
                                <!--<td class="px-6 py-4">-->
                                <!--    <input type="number" name="users_limit" value="<?= htmlspecialchars($row['users_limit']) ?>" class="border px-3 py-2 rounded w-full bg-yellow-50" min="0" required>-->
                                <!--</td>-->
                                <td class="px-6 py-4">
                                    <button type="submit" name="update" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow">
                                        Update
                                    </button>
                                </td>
                            </form>
                        </tr>
                    <?php endwhile; ?>
                    <?php $conn->close(); ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
