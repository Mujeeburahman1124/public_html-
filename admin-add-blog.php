<?php
require_once __DIR__ . '/config.php'; // $conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");

// Handle blog deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM blogs WHERE id = $id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle blog edit
if (isset($_POST['update'])) {
    $id = $_POST['blog_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];

    if ($_FILES['image']['name']) {
        $image = $_FILES['image']['name'];
        $tmp = $_FILES['image']['tmp_name'];
        $path = 'uploads/' . basename($image);
        move_uploaded_file($tmp, $path);

        $stmt = $conn->prepare("UPDATE blogs SET title=?, content=?, image=? WHERE id=?");
        $stmt->bind_param("sssi", $title, $content, $path, $id);
    } else {
        $stmt = $conn->prepare("UPDATE blogs SET title=?, content=? WHERE id=?");
        $stmt->bind_param("ssi", $title, $content, $id);
    }

    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle blog insert
if (isset($_POST['submit'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];

    $image = $_FILES['image']['name'];
    $tmp = $_FILES['image']['tmp_name'];
    $path = 'uploads/' . basename($image);
    move_uploaded_file($tmp, $path);

    $stmt = $conn->prepare("INSERT INTO blogs (title, content, image, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $title, $content, $path);
    $stmt->execute();
}

$blogs = $conn->query("SELECT * FROM blogs ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Blogs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <div class="px-4 py-12">
    <div class="max-w-4xl mx-auto mb-12">
      <h2 class="text-2xl font-bold mb-4">Add New Blog</h2>
      <form method="post" enctype="multipart/form-data" class="bg-white p-6 rounded shadow">
        <input type="hidden" name="blog_id">
        <label class="block mb-2">Title:</label>
        <input type="text" name="title" class="w-full mb-4 p-2 border rounded" required>
        <label class="block mb-2">Content:</label>
        <textarea name="content" rows="4" class="w-full mb-4 p-2 border rounded" required></textarea>
        <label class="block mb-2">Image:</label>
        <input type="file" name="image" class="mb-4">
        <button type="submit" name="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded">Post Blog</button>
      </form>
    </div>

    <div class="max-w-5xl mx-auto">
      <h2 class="text-3xl font-bold text-slate-900 mb-8">Latest Blog Posts MS GROUP</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while ($row = $blogs->fetch_assoc()): ?>
          <div class="bg-white rounded overflow-hidden shadow">
            <img src="<?php echo $row['image']; ?>" alt="Blog Image" class="w-full h-52 object-cover">
            <div class="p-6">
              <h3 class="text-lg font-semibold text-slate-900 mb-3"><?php echo htmlspecialchars($row['title']); ?></h3>
              <p class="text-slate-600 text-sm leading-relaxed"><?php echo substr(strip_tags($row['content']), 0, 100) . '...'; ?></p>
              <p class="text-orange-500 text-[13px] font-semibold mt-2"><?php echo date('d M Y', strtotime($row['created_at'])); ?></p>
              <div class="flex justify-between mt-4">
                <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this blog?');" class="text-red-500 text-sm">Delete</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</body>
</html>
