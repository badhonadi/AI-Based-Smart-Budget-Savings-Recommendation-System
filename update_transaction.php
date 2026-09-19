<?php
session_start();
header('Content-Type: application/json');
require "db.php";
require_once "currency_util.php";

// Ensure currency_code columns exist (runtime safeguard if migration not run)
function ensure_currency_column(mysqli $conn, string $table): void {
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW COLUMNS FROM `$tableEsc` LIKE 'currency_code'");
    if ($res && $res->num_rows > 0) {
        return;
    }
    $conn->query("ALTER TABLE `$tableEsc` ADD COLUMN currency_code CHAR(3) NOT NULL DEFAULT 'BDT'");
}

ensure_currency_column($conn, 'wallets');
ensure_currency_column($conn, 'transactions');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(["success" => false, "error" => "No data received"]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$transaction_id = (int)($data['transaction_id'] ?? 0);
$wallet_id = (int)($data['wallet_id'] ?? 0);
$category_id = isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null;
$type = strtolower(trim((string)($data['type'] ?? 'income')));
$amount = (float)($data['amount'] ?? 0);
$date = trim((string)($data['date'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$currency_code = normalize_currency((string)($data['currency_code'] ?? $DEFAULT_CURRENCY));
// Always store new amount in BDT
$amount_bdt = convert_amount($amount, $currency_code, 'BDT');
$currency_code_db = 'BDT';

if ($transaction_id <= 0 || $wallet_id <= 0 || $amount <= 0 || $date === '' || $description === '') {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

$typeDb = $type === 'expense' ? 'Expense' : 'Income';
$transaction_time = $date . ' 12:00:00';

$notePayload = ['d' => $description, 'n' => $notes];
$note = json_encode($notePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (strlen($note) > 255) {
    $note = substr($description, 0, 200) . '||' . substr($notes, 0, 50);
    $note = substr($note, 0, 255);
}

// Load old transaction (must belong to user)
$oldStmt = $conn->prepare("SELECT wallet_id, transaction_type, amount, currency_code FROM transactions WHERE transaction_id = ? AND user_id = ? LIMIT 1");
$oldStmt->bind_param('ii', $transaction_id, $user_id);
$oldStmt->execute();
$oldRes = $oldStmt->get_result();
if (!$oldRes || $oldRes->num_rows !== 1) {
    echo json_encode(["success" => false, "error" => "Transaction not found"]);
    exit;
}
$old = $oldRes->fetch_assoc();
$oldWalletId = (int)$old['wallet_id'];
$oldTypeDb = (string)$old['transaction_type'];
$oldAmount = (float)$old['amount'];
$oldCurrency = normalize_currency((string)($old['currency_code'] ?? $DEFAULT_CURRENCY));
// Normalize old amount to BDT (older records may not be BDT)
$oldAmountBDT = convert_amount($oldAmount, $oldCurrency, 'BDT');

// Verify new wallet belongs to user
$w = $conn->prepare("SELECT currency_code FROM wallets WHERE wallet_id = ? AND user_id = ? LIMIT 1");
$w->bind_param('ii', $wallet_id, $user_id);
$w->execute();
$wRes = $w->get_result();
if (!$wRes || $wRes->num_rows !== 1) {
    echo json_encode(["success" => false, "error" => "Invalid wallet"]);
    exit;
}
$newWalletCurrency = normalize_currency((string)($wRes->fetch_assoc()['currency_code'] ?? $DEFAULT_CURRENCY));

// Verify category belongs to user (if provided)
if ($category_id !== null) {
    $c = $conn->prepare("SELECT 1 FROM categories WHERE category_id = ? AND user_id = ? LIMIT 1");
    $c->bind_param('ii', $category_id, $user_id);
    $c->execute();
    $cRes = $c->get_result();
    if (!$cRes || $cRes->num_rows !== 1) {
        $category_id = null;
    }
}


// Load old wallet currency
$oldWalletStmt = $conn->prepare("SELECT currency_code FROM wallets WHERE wallet_id = ? AND user_id = ? LIMIT 1");
$oldWalletStmt->bind_param('ii', $oldWalletId, $user_id);
$oldWalletStmt->execute();
$oldWalletRes = $oldWalletStmt->get_result();
$oldWalletCurrency = normalize_currency((string)($oldWalletRes->fetch_assoc()['currency_code'] ?? $DEFAULT_CURRENCY));

$conn->begin_transaction();
try {
    if ($category_id === null) {
        $uStmt = $conn->prepare("UPDATE transactions SET wallet_id = ?, category_id = NULL, transaction_type = ?, amount = ?, currency_code = ?, transaction_time = ?, note = ? WHERE transaction_id = ? AND user_id = ?");
        $uStmt->bind_param('isdsssii', $wallet_id, $typeDb, $amount_bdt, $currency_code_db, $transaction_time, $note, $transaction_id, $user_id);
    } else {
        $uStmt = $conn->prepare("UPDATE transactions SET wallet_id = ?, category_id = ?, transaction_type = ?, amount = ?, currency_code = ?, transaction_time = ?, note = ? WHERE transaction_id = ? AND user_id = ?");
        $uStmt->bind_param('iisdsssii', $wallet_id, $category_id, $typeDb, $amount_bdt, $currency_code_db, $transaction_time, $note, $transaction_id, $user_id);
    }

    if (!$uStmt->execute()) {
        throw new Exception('update failed');
    }

    // Adjust wallet balances: revert old, apply new
    $oldAmountForWallet = convert_amount($oldAmountBDT, 'BDT', $oldWalletCurrency);
    $oldDelta = ($oldTypeDb === 'Income') ? -$oldAmountForWallet : $oldAmountForWallet;

    $newAmountForWallet = convert_amount($amount_bdt, 'BDT', $newWalletCurrency);
    $newDelta = ($typeDb === 'Income') ? $newAmountForWallet : -$newAmountForWallet;

    if ($oldWalletId === $wallet_id) {
        $delta = $oldDelta + $newDelta;
        $bStmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE wallet_id = ? AND user_id = ?");
        $bStmt->bind_param('dii', $delta, $wallet_id, $user_id);
        if (!$bStmt->execute()) {
            throw new Exception('balance update failed');
        }
    } else {
        $b1 = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE wallet_id = ? AND user_id = ?");
        $b1->bind_param('dii', $oldDelta, $oldWalletId, $user_id);
        if (!$b1->execute()) {
            throw new Exception('balance revert failed');
        }

        $b2 = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE wallet_id = ? AND user_id = ?");
        $b2->bind_param('dii', $newDelta, $wallet_id, $user_id);
        if (!$b2->execute()) {
            throw new Exception('balance apply failed');
        }
    }

    $conn->commit();
    echo json_encode(["success" => true]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Failed to update transaction"]);
    exit;
}
