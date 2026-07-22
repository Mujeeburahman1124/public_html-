<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['title']) || empty($_POST['description']) || !isset($_FILES['video'])) {
        $message = ["type" => "error", "text" => "All fields are required."];
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);

        $videoName = $_FILES['video']['name'];
        $videoTmpName = $_FILES['video']['tmp_name'];
        $videoSize = $_FILES['video']['size'];
        $videoError = $_FILES['video']['error'];
        $fileExt = strtolower(pathinfo($videoName, PATHINFO_EXTENSION));

        $allowed = ['mp4', 'avi', 'mov', 'wmv'];

        if (!in_array($fileExt, $allowed)) {
            $message = ["type" => "error", "text" => "Only MP4, AVI, MOV, or WMV videos are allowed."];
        } elseif ($videoError !== 0) {
            $message = ["type" => "error", "text" => "Error uploading the file."];
        } elseif ($videoSize > 100 * 1024 * 1024) {
            $message = ["type" => "error", "text" => "Video size exceeds 100MB limit."];
        } else {
            $videoNewName = uniqid("video_", true) . '.' . $fileExt;
            $videoDestination = 'uploads/videos/' . $videoNewName;

            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }

            if (!move_uploaded_file($videoTmpName, $videoDestination)) {
                $message = ["type" => "error", "text" => "Failed to move uploaded file."];
            } else {
               require_once __DIR__ . '/config.php'; // $conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");

                if ($conn->connect_error) {
                    $message = ["type" => "error", "text" => "Connection failed: " . $conn->connect_error];
                } else {
                    $stmt = $conn->prepare("INSERT INTO videos (title, description, video_path) VALUES (?, ?, ?)");
                    if ($stmt === false) {
                        $message = ["type" => "error", "text" => "Prepare failed: " . $conn->error];
                    } else {
                        $stmt->bind_param("sss", $title, $description, $videoNewName);
                        if ($stmt->execute()) {
                            $message = ["type" => "success", "text" => "Video uploaded and saved successfully!"];
                        } else {
                            $message = ["type" => "error", "text" => "Execute failed: " . $stmt->error];
                        }
                        $stmt->close();
                    }
                    $conn->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Video Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-2xl p-8 bg-white shadow-lg rounded-2xl">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">📹 Upload Video</h2>

        <?php if (isset($message)): ?>
            <div class="mb-4 px-4 py-3 rounded text-white <?= $message['type'] === 'success' ? 'bg-green-500' : 'bg-red-500' ?>">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data" class="space-y-5">
            <div>
                <label class="block text-gray-700 font-semibold mb-1">Title</label>
                <input
                    type="text"
                    name="title"
                    required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value="<?= isset($title) ? htmlspecialchars($title) : '' ?>"
                />
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-1">Description</label>
                <textarea
                    name="description"
                    required
                    rows="4"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                ><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-1">Upload Video (Max 100MB)</label>
                <input type="hidden" name="MAX_FILE_SIZE" value="104857600" />
                <input
                    type="file"
                    name="video"
                    accept="video/mp4,video/avi,video/quicktime,video/x-ms-wmv"
                    required
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4
                    file:rounded-lg file:border-0 file:text-sm file:font-semibold
                    file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
            </div>

            <div class="text-center">
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200"
                >
                    Upload
                </button>
            </div>
        </form>
    </div>
</body>
</html>
