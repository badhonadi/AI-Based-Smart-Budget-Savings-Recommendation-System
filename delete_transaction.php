<?php
session_start();
header('Content-Type: application/json');
require "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$user_id = (int)$_SESSION['user_id'];
$transaction_id = (int)($data['transaction_id'] ?? 0);

if ($transaction_id <= 0) {
    echo json_encode(["success" => false, "error" => "Transaction id is required"]);
    exit;
}

// Load transaction
$t = $conn->prepare("SELECT wallet_id, transaction_type, amount FROM transactions WHERE transaction_id = ? AND user_id = ? LIMIT 1");
$t->bind_param('ii', $transaction_id, $user_id);
$t->execute();
$tRes = $t->get_result();
if (!$tRes || $tRes->num_rows !== 1) {
    echo json_encode(["success" => false, "error" => "Transaction not found"]);
    exit;
}
$row = $tRes->fetch_assoc();
$wallet_id = (int)$row['wallet_id'];
$typeDb = (string)$row['transaction_type'];
$amount = (float)$row['amount'];

$conn->begin_transaction();
try {
    $d = $conn->prepare("DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?");
    $d->bind_param('ii', $transaction_id, $user_id);
    if (!$d->execute()) {
        throw new Exception('delete failed');
    }

    // Revert wallet cached balance
    $delta = ($typeDb === 'Income') ? -$amount : $amount;
    $u = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE wallet_id = ? AND user_id = ?");
    $u->bind_param('dii', $delta, $wallet_id, $user_id);
    if (!$u->execute()) {
        throw new Exception('wallet update failed');
    }

    $conn->commit();
    echo json_encode(["success" => true]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Failed to delete transaction"]);
    exit;
}
