<?php
require_once __DIR__ . '/config.php'; // $conn = new mysqli("localhost", "root", "", "exaple");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers for CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users.csv"');

$output = fopen('php://output', 'w');

// Output header row
fputcsv($output, ['ID', 'Email', 'Role', 'Status']);

// Fetch user data
$sql = "SELECT id, email, user_type, status FROM users";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['id'], $row['email'], $row['user_type'], $row['status']]);
    }
}

fclose($output);
$conn->close();
exit;
?>
