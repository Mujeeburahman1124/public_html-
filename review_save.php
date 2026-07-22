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

$company_id = (int)($_POST['company_id'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 0);
$title      = trim($_POST['title'] ?? '');
$review     = trim($_POST['review_text'] ?? '');
$reviewer   = trim($_POST['reviewer'] ?? '');

if ($company_id<=0 || $rating<1 || $rating>5 || $review==='') {
  header('Location: company.php?id='.$company_id); exit;
}

$stmt = $conn->prepare("INSERT INTO company_reviews (company_id,rating,title,review_text,reviewer) VALUES (?,?,?,?,?)");
$stmt->bind_param("iisss", $company_id,$rating,$title,$review,$reviewer);
$stmt->execute();
$stmt->close();

header('Location: company.php?id='.$company_id);
