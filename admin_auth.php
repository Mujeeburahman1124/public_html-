<?php
session_start();
require __DIR__ . '/config2.php'; // Must define db(): mysqli

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    header('Location: admin_login.php?err=empty');
    exit;
}

// Lookup by username
$sql = "SELECT id, username, email, password_hash FROM super_admins WHERE username = ? LIMIT 1";
$stmt = db()->prepare($sql);
if (!$stmt) {
    // fallback (avoid exposing internals)
    header('Location: admin_login.php?err=invalid');
    exit;
}
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header('Location: admin_login.php?err=invalid');
    exit;
}

$user = $res->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    header('Location: admin_login.php?err=invalid');
    exit;
}

// Good login
$_SESSION['super_admin_id'] = (int)$user['id'];
$_SESSION['super_admin_username'] = $user['username'];
$_SESSION['is_super_admin'] = true;

// Harden session
session_regenerate_id(true);

// Redirect to SuperAdmin.php
header('Location: SuperAdmin.php');
exit;
