<?php
session_start();
require 'db.php';
require 'schema.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

ensure_membership_column($conn);

$user_id = (int)$_SESSION['user_id'];
$plan = (isset($_POST['plan']) && $_POST['plan'] === 'monthly') ? 'monthly' : 'yearly';
$paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'card';
$walletId = isset($_POST['wallet_id']) ? (int)$_POST['wallet_id'] : 0;

// Mirror client-side rates for consistent pricing
$RATES = [
    'USD' => 1,
    'BDT' => 122.30,
    'EUR' => 0.92,
    'GBP' => 0.79,
    'INR' => 83,
    'JPY' => 149,
    'CNY' => 7.24,
    'AUD' => 1.52,
    'CAD' => 1.36,
];

function normalize_currency($code, $rates) {
    $upper = strtoupper((string)$code);
    return array_key_exists($upper, $rates) ? $upper : 'BDT';
}

function convert_amount($amount, $fromCode, $toCode, $rates) {
    $from = normalize_currency($fromCode, $rates);
    $to = normalize_currency($toCode, $rates);
    $fromRate = isset($rates[$from]) ? (float)$rates[$from] : 1.0;
    $toRate = isset($rates[$to]) ? (float)$rates[$to] : 1.0;
    $baseUsd = (float)$amount / $fromRate;
    return $baseUsd * $toRate;
}

$priceUsd = ($plan === 'monthly') ? 9.99 : 59.99;
$priceBdt = convert_amount($priceUsd, 'USD', 'BDT', $RATES);

header('Content-Type: application/json');

// If paying from wallet, deduct first then upgrade membership
if ($paymentMethod === 'wallet') {
    if ($walletId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Wallet is required for wallet payment']);
        exit;
    }

    $conn->begin_transaction();

    $sel = $conn->prepare("SELECT balance, currency_code FROM wallets WHERE wallet_id = ? AND user_id = ? FOR UPDATE");
    $sel->bind_param('ii', $walletId, $user_id);
    $sel->execute();
    $res = $sel->get_result();
    if ($res->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Wallet not found']);
        exit;
    }

    $row = $res->fetch_assoc();
    $walletBalance = (float)$row['balance'];
    $walletCurrency = isset($row['currency_code']) && $row['currency_code'] !== '' ? $row['currency_code'] : 'BDT';

    $walletBalanceBdt = convert_amount($walletBalance, $walletCurrency, 'BDT', $RATES);
    if ($walletBalanceBdt + 0.0001 < $priceBdt) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Insufficient wallet balance for upgrade']);
        exit;
    }

    $deductAmount = convert_amount($priceBdt, 'BDT', $walletCurrency, $RATES);
    $upd = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE wallet_id = ? AND user_id = ? AND balance >= ?");
    $upd->bind_param('diid', $deductAmount, $walletId, $user_id, $deductAmount);
    $upd->execute();

    if ($upd->affected_rows !== 1) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to charge wallet']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET membership = 'premium' WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);

    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'membership' => 'premium',
            'paid_with' => 'wallet',
            'wallet_id' => $walletId
        ]);
        exit;
    }

    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Failed to upgrade']);
    exit;
}

// Card/other payment: just set membership (payment handled externally)
$stmt = $conn->prepare("UPDATE users SET membership = 'premium' WHERE user_id = ?");
$stmt->bind_param('i', $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'membership' => 'premium', 'paid_with' => 'card']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Failed to upgrade']);
