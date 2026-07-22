<?php
session_start();
include 'config.php';

$message = "";

// Add new FAQ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faq'])) {
    $question = trim($_POST['question']);
    $answer = trim($_POST['answer']);

    if (!empty($question) && !empty($answer)) {
        $stmt = $conn->prepare("INSERT INTO faqs (question, answer) VALUES (?, ?)");
        $stmt->bind_param("ss", $question, $answer);

        if ($stmt->execute()) {
            $message = "FAQ added successfully!";
        } else {
            $message = "Error adding FAQ.";
        }
    } else {
        $message = "Please fill in both the question and answer.";
    }
}

// Delete FAQ
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "FAQ deleted successfully!";
        } else {
            $message = "Error deleting FAQ.";
        }
    } else {
        $message = "Invalid FAQ ID.";
    }
}

// Fetch FAQs
$stmt = $conn->prepare("SELECT * FROM faqs ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();
$faqs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage FAQs - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .faq-item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #fff;
            border-radius: 5px;
        }
        .faq-item .faq-question {
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Admin Panel - Manage FAQs</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Add New FAQ</div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="question" class="form-label">Question</label>
                    <input type="text" name="question" id="question" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="answer" class="form-label">Answer</label>
                    <textarea name="answer" id="answer" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" name="add_faq" class="btn btn-success">Add FAQ</button>
            </form>
        </div>
    </div>

    <h3>Existing FAQs</h3>
    <?php if (count($faqs) > 0): ?>
        <?php foreach ($faqs as $faq): ?>
            <div class="faq-item">
                <div class="faq-question"><?= htmlspecialchars($faq['question']) ?></div>
                <div class="faq-answer"><?= nl2br(htmlspecialchars($faq['answer'])) ?></div>
                <a href="?delete_id=<?= $faq['id'] ?>" class="btn btn-danger btn-sm mt-2"
                   onclick="return confirm('Are you sure you want to delete this FAQ?');">Delete</a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No FAQs found.</p>
    <?php endif; ?>
</div>
</body>
</html>
