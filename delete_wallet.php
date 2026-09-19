<?php
session_start();
header('Content-Type: application/json');
require "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(["success" => false, "error" => "No data received"]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$wallet_id = (int)($data['wallet_id'] ?? 0);

if ($wallet_id <= 0) {
    echo json_encode(["success" => false, "error" => "Wallet id is required"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM wallets WHERE wallet_id = ? AND user_id = ?");
$stmt->bind_param("ii", $wallet_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "error" => "Failed to delete wallet"]);
