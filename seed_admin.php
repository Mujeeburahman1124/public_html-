<?php
// Run once from CLI or browser, then delete this file.
require __DIR__ . '/config.php';

// change these:
$username = 'superadmin';
$email    = 'admin@example.com';
$plain    = 'Super@dm1n#2025';

// If user exists, update; else insert
$hash = password_hash($plain, PASSWORD_DEFAULT);

$sql = "SELECT id FROM super_admins WHERE username = ? OR email = ? LIMIT 1";
$stmt = db()->prepare($sql);
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 1) {
    $row = $res->fetch_assoc();
    $up = db()->prepare("UPDATE super_admins SET username=?, email=?, password_hash=? WHERE id=?");
    $up->bind_param('sssi', $username, $email, $hash, $row['id']);
    $up->execute();
    echo "Updated existing admin.\n";
} else {
    $in = db()->prepare("INSERT INTO super_admins (username, email, password_hash) VALUES (?,?,?)");
    $in->bind_param('sss', $username, $email, $hash);
    $in->execute();
    echo "Created new admin.\n";
}
