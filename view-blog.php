<?php
include 'config.php';

if (!isset($_GET['id'])) {
    echo "Blog not found.";
    exit;
}

$blog_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
$stmt->bind_param("i", $blog_id);
$stmt->execute();
$result = $stmt->get_result();
$blog = $result->fetch_assoc();

if (!$blog) {
    echo "Blog not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($blog['title']) ?> | MS JOBS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

  <div class="max-w-4xl mx-auto py-10 px-4">
    <!-- Blog Title -->
    <h1 class="text-3xl font-extrabold text-blue-600 mb-6"><?= htmlspecialchars($blog['title']) ?></h1>

    <!-- Blog Image -->
    <?php if (!empty($blog['image'])): ?>
      <img src="<?= htmlspecialchars($blog['image']) ?>" alt="Blog Image" class="w-full rounded-lg shadow-md mb-8">
    <?php endif; ?>

    <!-- Blog Content -->
    <div class="text-base leading-relaxed space-y-4 mb-8">
      <?= nl2br(htmlspecialchars($blog['content'])) ?>
    </div>

    <!-- Go Back Button -->
    <a href="javascript:history.back()" class="inline-block text-blue-600 hover:underline">
      ← Go Back
    </a>
  </div>

</body>
</html>
