<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config.php'; // $conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_custom_message'])) {
    $app_id = intval($_POST['application_id']);
    $custom_message = trim($_POST['custom_message']);

    $stmt = $conn->prepare("SELECT email, name FROM applications WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($applicant && !empty($custom_message)) {
        $stmt = $conn->prepare("UPDATE applications SET status = 'Selected' WHERE id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $stmt->close();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mshrc936@gmail.com';
            $mail->Password = 'nmspuxcjuptondkd';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('mshrc936@gmail.com', 'MS JOBS HR');
            $mail->addAddress($applicant['email'], $applicant['name']);
            $mail->isHTML(true);
            $mail->Subject = "Your Application Update";

            $htmlMessage = "<html><body><h2>Dear " . htmlspecialchars($applicant['name']) . ",</h2><p>" . nl2br(htmlspecialchars($custom_message)) . "</p><p>Best regards,<br>MS JOBS HR Team</p></body></html>";
            $mail->Body = $htmlMessage;
            $mail->send();
        } catch (Exception $e) {
            echo "Mailer Error: " . $mail->ErrorInfo;
        }
    }

    header("Location: view-applications.php");
    exit;
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'reject') {
    $app_id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE applications SET status = 'Rejected' WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $stmt->close();
    header("Location: view-applications.php");
    exit;
}

$stmt = $conn->prepare("SELECT a.*, j.title AS job_title 
                        FROM applications a 
                        LEFT JOIN jobs j ON a.job_id = j.id 
                        ORDER BY a.applied_at DESC");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Job Applications</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
<div class="max-w-7xl mx-auto bg-white shadow-md rounded-lg p-6">
  <h1 class="text-2xl font-bold mb-6">Job Applications</h1>
  <div class="overflow-x-auto">
    <table class="min-w-full table-auto text-sm">
      <thead class="bg-gray-800 text-white">
        <tr>
          <th class="px-4 py-3 text-left">Job</th>
          <th class="px-4 py-3 text-left">Name</th>
          <th class="px-4 py-3 text-left">Email</th>
          <th class="px-4 py-3 text-left">WhatsApp</th>
          <th class="px-4 py-3 text-left">Resume</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Message</th>
          <th class="px-4 py-3 text-left">Applied</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-3"><?= htmlspecialchars($row['job_title']) ?></td>
          <td class="px-4 py-3"><?= htmlspecialchars($row['name']) ?></td>
          <td class="px-4 py-3"><?= htmlspecialchars($row['email']) ?></td>
          <td class="px-4 py-3">
            <?php if (!empty($row['whatsapp'])): ?>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['whatsapp']) ?>" target="_blank" class="text-green-600 hover:underline">Message</a>
            <?php else: ?>
              <span class="text-gray-400">N/A</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($row['resume']): 
              $filePath = "uploads/cvs/" . htmlspecialchars($row['resume']);
              $publicURL = "https://msjobs.net/" . $filePath; // Replace with your actual domain
              $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            ?>
              <div class="space-y-1">
                <a href="<?= $filePath ?>" target="_blank" class="text-blue-600 hover:underline">Download</a>
                <button onclick="previewCV('<?= $publicURL ?>', '<?= $fileExt ?>')" class="text-sm text-blue-500 hover:underline block">Preview</button>
              </div>
            <?php else: ?>
              <span class="text-gray-400">No Resume</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($row['status'] === 'Pending'): ?>
              <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Pending</span>
            <?php elseif ($row['status'] === 'Selected'): ?>
              <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">Selected</span>
            <?php else: ?>
              <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs">Rejected</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 max-w-xs">
            <div class="bg-gray-100 p-2 rounded overflow-y-auto max-h-24 text-xs whitespace-pre-wrap">
              <?= htmlspecialchars($row['message']) ?>
            </div>
          </td>
          <td class="px-4 py-3"><?= date('Y-m-d', strtotime($row['applied_at'])) ?></td>
          <td class="px-4 py-3 space-y-2">
            <?php if ($row['status'] === 'Pending'): ?>
              <button class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded w-full" onclick="openModal(<?= $row['id'] ?>)">Select</button>
              <a href="?action=reject&id=<?= $row['id'] ?>" onclick="return confirm('Reject this applicant?');" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded w-full block text-center">Reject</a>
            <?php else: ?>
              <span class="text-gray-400 text-xs">No actions</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal HTML -->
<div id="selectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
    <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-600 hover:text-black text-3xl font-bold leading-none">&times;</button>
    <h2 class="text-xl font-semibold mb-4">Send Custom Message</h2>
    <form method="POST">
      <input type="hidden" name="application_id" id="app_id" />
      <div class="mb-4">
        <label for="custom_message" class="block text-sm font-medium mb-1">Message</label>
        <textarea name="custom_message" id="custom_message" rows="6" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Write your message here..."></textarea>
      </div>
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="closeModal()" class="bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded">Cancel</button>
        <button type="submit" name="send_custom_message" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Send</button>
      </div>
    </form>
  </div>
</div>

<div id="cvPreviewModal" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 items-center justify-center">
  <div class="bg-white w-11/12 max-w-4xl h-[90vh] rounded-lg overflow-hidden relative shadow-lg">
    <button onclick="closeCVPreview()" class="absolute top-3 right-4 text-2xl font-bold text-gray-600 hover:text-black">&times;</button>
    <iframe id="cvFrame" class="w-full h-full border-none"></iframe>
  </div>
</div>

<script>
  function openModal(id) {
    document.getElementById('selectModal').classList.remove('hidden');
    document.getElementById('app_id').value = id;
  }
  function closeModal() {
    document.getElementById('selectModal').classList.add('hidden');
  }
  function previewCV(url, ext) {
    let viewerUrl = '';
    if (ext === 'pdf') {
      viewerUrl = 'https://docs.google.com/gview?url=' + encodeURIComponent(url) + '&embedded=true';
    } else if (ext === 'doc' || ext === 'docx') {
      viewerUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(url);
    } else {
      alert('Unsupported file type for preview.');
      return;
    }
    document.getElementById('cvFrame').src = viewerUrl;
    document.getElementById('cvPreviewModal').classList.remove('hidden');
  }
  function closeCVPreview() {
    document.getElementById('cvPreviewModal').classList.add('hidden');
    document.getElementById('cvFrame').src = '';
  }
</script>

</body>
</html>
<?php $conn->close(); ?>
