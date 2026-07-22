<?php
/**
 * share_profile.php — JSON endpoint for enabling/disabling public profile sharing.
 * Robustly accepts: form-data, x-www-form-urlencoded, JSON, or querystring.
 */
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, must-revalidate');

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------- Auth ----------
if (!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'jobseeker') {
    respond(401, ['ok' => false, 'error' => 'Unauthorized']);
}
$UID = (int)$_SESSION['user_id'];

// ---------- Read action robustly ----------
$action = '';

// 1) Normal PHP superglobals
if (isset($_POST['action'])) $action = (string)$_POST['action'];
elseif (isset($_GET['action'])) $action = (string)$_GET['action'];

// 2) If still empty, try JSON / urlencoded raw body
if ($action === '') {
    $raw = file_get_contents('php://input') ?: '';
    $ctype = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if ($raw !== '') {
        if (stripos($ctype, 'application/json') !== false) {
            $j = json_decode($raw, true);
            if (is_array($j) && isset($j['action'])) $action = (string)$j['action'];
        } elseif (stripos($ctype, 'application/x-www-form-urlencoded') !== false) {
            parse_str($raw, $arr);
            if (isset($arr['action'])) $action = (string)$arr['action'];
        }
        // multipart/form-data is handled by PHP automatically into $_POST normally.
    }
}

$action = strtolower(trim($action));
if ($action !== 'enable' && $action !== 'disable') {
    respond(400, ['ok' => false, 'error' => 'Missing or invalid action (use enable|disable)']);
}

// ---------- DB ----------
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli('127.0.0.1:3306', 'u903588615_root', 'Msjobs#1', 'u903588615_exaple');
    $db->set_charset('utf8mb4');
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => 'DB connection failed']);
}

// ---------- Logic ----------
try {
    if ($action === 'enable') {
        // ensure token
        $q = $db->prepare("SELECT share_token FROM jobseekers WHERE user_id=?");
        $q->bind_param("i", $UID);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $token = (!empty($row['share_token'])) ? $row['share_token'] : bin2hex(random_bytes(16));

        $u = $db->prepare("UPDATE jobseekers SET share_token=?, share_enabled=1 WHERE user_id=?");
        $u->bind_param("si", $token, $UID);
        $u->execute();

        // build URL
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme  = $isHttps ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // NOTE: Use the public dashboard filename as you actually named it:
        $baseScript = 'employee_dashboard.php';
        $url = $scheme . '://' . $host . '/' . $baseScript . '?view=public&token=' . $token;

        respond(200, ['ok' => true, 'url' => $url]);
    } else {
        $u = $db->prepare("UPDATE jobseekers SET share_enabled=0 WHERE user_id=?");
        $u->bind_param("i", $UID);
        $u->execute();
        respond(200, ['ok' => true]);
    }
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => 'Server error']);
}
