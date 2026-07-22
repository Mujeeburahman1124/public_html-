<?php
$pdo = new PDO("mysql:host=localhost;dbname=exaple;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Initialize variables
$error = '';
$success = '';
$id = $_GET['id'] ?? null;

if (!$id) {
    die("Candidate ID is required");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $age = $_POST['age'] ?? null;
    $marital_status = $_POST['marital_status'] ?? '';
    $position = $_POST['position'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $location = $_POST['location'] ?? '';
    $status = $_POST['status'] ?? '';
    $country = $_POST['country'] ?? '';
    $address = $_POST['address'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $email = $_POST['email'] ?? '';
    $accommodation = $_POST['accommodation'] ?? '';
    $processing_status = $_POST['processing_status'] ?? '';
    $process_started_at = $_POST['process_started_at'] ?? null;
    $visa_expiry_date = $_POST['visa_expiry_date'] ?? null;
    $registration_expiry_date = $_POST['registration_expiry_date'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $other_certificates = $_POST['other_certificates'] ?? '';

    // Basic validation (add more as needed)
    if (!$name || !$email) {
        $error = "Name and Email are required.";
    } else {
        // Update candidate
        $stmt = $pdo->prepare("UPDATE candidates SET
            name = ?, age = ?, marital_status = ?, position = ?, salary = ?, location = ?, status = ?, country = ?, address = ?, mobile = ?, email = ?, accommodation = ?, processing_status = ?, process_started_at = ?, visa_expiry_date = ?, registration_expiry_date = ?, notes = ?, other_certificates = ?
            WHERE id = ?");
        $stmt->execute([
            $name, $age, $marital_status, $position, $salary, $location, $status, $country, $address, $mobile, $email, $accommodation, $processing_status, $process_started_at ?: null, $visa_expiry_date ?: null, $registration_expiry_date ?: null, $notes, $other_certificates, $id
        ]);
        $success = "Candidate updated successfully.";
    }
}

// Fetch existing candidate data
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    die("Candidate not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Candidate #<?= htmlspecialchars($candidate['id']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded shadow-lg">
        <h1 class="text-3xl font-bold mb-6">Edit Candidate #<?= htmlspecialchars($candidate['id']) ?></h1>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="grid grid-cols-2 gap-4">
                <label>
                    Name:<br />
                    <input type="text" name="name" value="<?= htmlspecialchars($candidate['name']) ?>" class="border p-2 w-full" required />
                </label>
                <label>
                    Age:<br />
                    <input type="number" name="age" value="<?= htmlspecialchars($candidate['age']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Marital Status:<br />
                    <input type="text" name="marital_status" value="<?= htmlspecialchars($candidate['marital_status']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Position:<br />
                    <input type="text" name="position" value="<?= htmlspecialchars($candidate['position']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Salary:<br />
                    <input type="text" name="salary" value="<?= htmlspecialchars($candidate['salary']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Location:<br />
                    <input type="text" name="location" value="<?= htmlspecialchars($candidate['location']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Status:<br />
                    <input type="text" name="status" value="<?= htmlspecialchars($candidate['status']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Country:<br />
                    <input type="text" name="country" value="<?= htmlspecialchars($candidate['country']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Address:<br />
                    <textarea name="address" class="border p-2 w-full"><?= htmlspecialchars($candidate['address']) ?></textarea>
                </label>
                <label>
                    Mobile:<br />
                    <input type="text" name="mobile" value="<?= htmlspecialchars($candidate['mobile']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Email:<br />
                    <input type="email" name="email" value="<?= htmlspecialchars($candidate['email']) ?>" class="border p-2 w-full" required />
                </label>
                <label>
                    Accommodation:<br />
                    <input type="text" name="accommodation" value="<?= htmlspecialchars($candidate['accommodation']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Processing Status:<br />
                    <input type="text" name="processing_status" value="<?= htmlspecialchars($candidate['processing_status']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Process Started At:<br />
                    <input type="datetime-local" name="process_started_at" value="<?= htmlspecialchars($candidate['process_started_at'] ? date('Y-m-d\TH:i', strtotime($candidate['process_started_at'])) : '') ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Visa Expiry Date:<br />
                    <input type="date" name="visa_expiry_date" value="<?= htmlspecialchars($candidate['visa_expiry_date']) ?>" class="border p-2 w-full" />
                </label>
                <label>
                    Registration Expiry Date:<br />
                    <input type="date" name="registration_expiry_date" value="<?= htmlspecialchars($candidate['registration_expiry_date']) ?>" class="border p-2 w-full" />
                </label>
                <label class="col-span-2">
                    Other Certificates:<br />
                    <textarea name="other_certificates" class="border p-2 w-full"><?= htmlspecialchars($candidate['other_certificates']) ?></textarea>
                </label>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Changes</button>
                <a href="super-admin-candidates.php" class="ml-4 text-gray-700 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
