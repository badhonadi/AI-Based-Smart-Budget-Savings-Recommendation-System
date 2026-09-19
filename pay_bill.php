<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function redirect_with(string $query): void {
    header("Location: bill.php?$query");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with('error=1');
}

$user_id = (int)$_SESSION['user_id'];
$bill_payment_id = (int)($_POST['bill_payment_id'] ?? 0);
$wallet_id_input = (int)($_POST['wallet_id'] ?? 0);

if ($bill_payment_id <= 0) {
    redirect_with('error=1');
}

$conn->begin_transaction();
try {
    // Lock bill row to prevent double-pay
    $billStmt = $conn->prepare(
        "SELECT bp.bill_payment_id, bp.wallet_id, bp.amount, bp.status, ub.biller_name
         FROM bill_payments bp
         JOIN utility_billers ub ON ub.biller_id = bp.biller_id
         WHERE bp.bill_payment_id = ? AND bp.user_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $billStmt->bind_param('ii', $bill_payment_id, $user_id);
    $billStmt->execute();
    $billRes = $billStmt->get_result();

    if (!$billRes || $billRes->num_rows !== 1) {
        throw new Exception('bill_not_found');
    }

    $bill = $billRes->fetch_assoc();
    $status = (string)$bill['status'];
    if ($status === 'Paid') {
        // Already paid; no-op
        $conn->commit();
        redirect_with('paid=1');
    }

    $amount = (float)$bill['amount'];
    if ($amount <= 0) {
        throw new Exception('invalid_amount');
    }

    $wallet_id = (int)($bill['wallet_id'] ?? 0);
    if ($wallet_id <= 0) {
        $wallet_id = $wallet_id_input;
    }

    if ($wallet_id <= 0) {
        throw new Exception('wallet_required');
    }

    // Lock wallet row and validate ownership
    $walletStmt = $conn->prepare(
        "SELECT balance
         FROM wallets
         WHERE wallet_id = ? AND user_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $walletStmt->bind_param('ii', $wallet_id, $user_id);
    $walletStmt->execute();
    $walletRes = $walletStmt->get_result();

    if (!$walletRes || $walletRes->num_rows !== 1) {
        throw new Exception('invalid_wallet');
    }

    $wallet = $walletRes->fetch_assoc();
    $balance = (float)$wallet['balance'];
    if ($balance < $amount) {
        throw new Exception('insufficient_funds');
    }

    // Record transaction (Expense)
    $billerName = (string)($bill['biller_name'] ?? 'Bill');
    $note = json_encode([
        'd' => 'Bill payment',
        'n' => $billerName
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($note) > 255) {
        $note = substr('Bill payment: ' . $billerName, 0, 255);
    }

    $txnStmt = $conn->prepare(
        "INSERT INTO transactions (user_id, wallet_id, category_id, transaction_type, amount, transaction_time, note)
         VALUES (?, ?, NULL, 'Expense', ?, NOW(), ?)"
    );
    $txnStmt->bind_param('iids', $user_id, $wallet_id, $amount, $note);
    if (!$txnStmt->execute()) {
        throw new Exception('txn_insert_failed');
    }

    // Update wallet cached balance
    $delta = -$amount;
    $u = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE wallet_id = ? AND user_id = ?");
    $u->bind_param('dii', $delta, $wallet_id, $user_id);
    if (!$u->execute()) {
        throw new Exception('wallet_update_failed');
    }

    // Mark bill paid and set actual payment time
    $paidStmt = $conn->prepare(
        "UPDATE bill_payments
         SET status = 'Paid', wallet_id = ?, payment_time = NOW()
         WHERE bill_payment_id = ? AND user_id = ?"
    );
    $paidStmt->bind_param('iii', $wallet_id, $bill_payment_id, $user_id);
    if (!$paidStmt->execute()) {
        throw new Exception('bill_update_failed');
    }

    $conn->commit();
    redirect_with('paid=1');
} catch (Throwable $e) {
    $conn->rollback();

    $code = $e->getMessage();
    if ($code === 'insufficient_funds') {
        redirect_with('insufficient=1');
    }
    if ($code === 'wallet_required') {
        redirect_with('wallet_required=1');
    }

    redirect_with('error=1');
}
