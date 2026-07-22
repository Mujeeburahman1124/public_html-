<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$DB_HOST = $servername;
$DB_USER = $username;
$DB_PASS = $password;
$DB_NAME = $dbname;
$database = $dbname;

session_start();

/* ==== DB CONFIG (inline) ==== */
$DB_HOST = "127.0.0.1:3306";
// $DB_USER = "u903588615_root"; (Refactored to config.php)
// $DB_PASS = "Msjobs#1"; (Refactored to config.php)
// $DB_NAME = "u903588615_exaple"; (Refactored to config.php)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$conn->set_charset('utf8mb4');

function bad($msg){ http_response_code(400); echo $msg; exit; }

$name        = trim($_POST['name'] ?? '');
$industry    = trim($_POST['industry'] ?? '');
$hq          = trim($_POST['hq_location'] ?? '');
$website     = trim($_POST['website'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($name==='' || $industry==='') bad('Missing required fields');

/* Upload dir */
$uploadDir = __DIR__ . '/uploads/company_logos';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

$logoFile = null;
if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
  $tmp  = $_FILES['logo']['tmp_name'];
  $size = (int)$_FILES['logo']['size'];
  if ($size > 1024*1024) bad('Logo too large');

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($tmp);
  $ext   = $mime === 'image/png' ? 'png' : ($mime === 'image/jpeg' ? 'jpg' : null);
  if (!$ext) bad('Unsupported logo type');

  $safe  = preg_replace('/[^a-z0-9]+/i', '-', strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_FILENAME)));
  $nameOnDisk = $safe . '-' . dechex(random_int(1000, 999999)) . '.' . $ext;
  $dest  = $uploadDir . '/' . $nameOnDisk;
  if (!move_uploaded_file($tmp, $dest)) bad('Failed to save logo');
  $logoFile = $nameOnDisk;
}

/* Insert company */
$stmt = $conn->prepare("INSERT INTO companies (name,industry,hq_location,website,description,logo) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("ssssss", $name,$industry,$hq,$website,$description,$logoFile);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

header('Location: company.php?id='.$newId);
