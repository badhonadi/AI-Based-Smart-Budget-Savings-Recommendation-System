<?php
session_start();
header('Content-Type: application/json');
require "db.php";

// Accept JSON or form POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    $data = $_POST;
}

$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$report_type = trim((string)($data['subject'] ?? ($data['report_type'] ?? 'other')));
$message = trim((string)($data['message'] ?? ''));

if ($username === '' || $email === '' || $message === '') {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

$allowedTypes = ['bug','feature','security','account','other'];
if (!in_array($report_type, $allowedTypes, true)) {
    $report_type = 'other';
}

$user_id = null;

// If logged in, prefer session user_id
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
} else {
    // Try to map report to a known user by email
    $uStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $uStmt->bind_param('s', $email);
    $uStmt->execute();
    $uRes = $uStmt->get_result();
    if ($uRes && $uRes->num_rows === 1) {
        $user_id = (int)$uRes->fetch_assoc()['user_id'];
    }
}

$stmt = $conn->prepare("INSERT INTO support_reports (user_id, username, email, report_type, message) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('issss', $user_id, $username, $email, $report_type, $message);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "error" => "Failed to submit report"]);
