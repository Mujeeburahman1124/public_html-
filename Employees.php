<?php
// DB connection
require_once __DIR__ . '/config.php'; // $conn = new mysqli("localhost", "root", "", "job_portal"); // update DB info
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$employees = $conn->query("SELECT * FROM employees");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Premium Employee Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1200px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            text-align: center;
        }
        th {
            background: #007bff;
            color: white;
        }
        tr:hover {
            background: #f1f1f1;
        }
        img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
        }
        a.btn {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            border-radius: 5px;
            text-decoration: none;
        }
        a.btn:hover {
            background: #218838;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Premium Employee Dashboard</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Nationality</th>
                    <th>Age</th>
                    <th>Language</th>
                    <th>Religion</th>
                    <th>Experience</th>
                    <th>Country</th>
                    <th>CV</th>
                    <th>Profile Picture</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employees->num_rows > 0): ?>
                    <?php while($row = $employees->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= $row['user_id'] ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['nationality']) ?></td>
                            <td><?= $row['age'] ?></td>
                            <td><?= htmlspecialchars($row['language']) ?></td>
                            <td><?= htmlspecialchars($row['religion']) ?></td>
                            <td><?= htmlspecialchars($row['experience']) ?></td>
                            <td><?= htmlspecialchars($row['country']) ?></td>
                            <td>
                                <?php if ($row['cv_file']): ?>
                                    <a class="btn" href="uploads/<?= $row['cv_file'] ?>" target="_blank">View CV</a>
                                <?php else: ?>N/A<?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['profile_picture']): ?>
                                    <img src="uploads/<?= $row['profile_picture'] ?>" alt="Profile">
                                <?php else: ?>N/A<?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="no-data">No employee records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
