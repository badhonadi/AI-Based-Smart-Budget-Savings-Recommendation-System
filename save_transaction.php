<?php
session_start();
header('Content-Type: application/json');
require "db.php";
require_once "currency_util.php";
require_once "notification_util.php";

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
require_once "currency_util.php";

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
$wallet_id = (int)($data['wallet_id'] ?? 0);
$wallet_splits = $data['wallet_splits'] ?? null;
$hasSplit = is_array($wallet_splits) && count($wallet_splits) > 0;
$category_id = isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null;
$type = strtolower(trim((string)($data['type'] ?? 'income')));
$amount = (float)($data['amount'] ?? 0);
$date = trim((string)($data['date'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$currency_code = normalize_currency((string)($data['currency_code'] ?? $DEFAULT_CURRENCY));
// Always store transaction amounts in BDT
$amount_bdt = convert_amount($amount, $currency_code, 'BDT');
$currency_code_db = 'BDT';

if ((!$hasSplit && $wallet_id <= 0) || $amount <= 0 || $date === '' || $description === '') {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

$typeDb = $type === 'expense' ? 'Expense' : 'Income';
$transaction_time = $date . ' 12:00:00';

// Pack note safely into varchar(255)
$notePayload = [
    'd' => $description,
    'n' => $notes
];
$note = json_encode($notePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (strlen($note) > 255) {
    // Fallback to delimiter with truncation
    $note = substr($description, 0, 200) . '||' . substr($notes, 0, 50);
    $note = substr($note, 0, 255);
}

// For split transactions: validate splits and ensure wallets belong to user
$splitPlan = [];
if ($hasSplit) {
    $merged = [];
    foreach ($wallet_splits as $s) {
        if (!is_array($s)) continue;
        $wid = (int)($s['wallet_id'] ?? 0);
        $amt = (float)($s['amount'] ?? 0);
        if ($wid <= 0 || $amt <= 0) continue;
        if (!isset($merged[$wid])) $merged[$wid] = 0.0;
        $merged[$wid] += $amt;
    }

    if (empty($merged)) {
        echo json_encode(["success" => false, "error" => "Invalid wallet_splits"]);
        exit;
    }

    $sum = 0.0;
    foreach ($merged as $amt) {
        $sum += (float)$amt;
    }

    if (abs(round($sum - $amount, 2)) > 0.05) {
        echo json_encode(["success" => false, "error" => "Split amounts must sum to total amount"]);
        exit;
    }

    // Load wallet currencies for all wallet ids in split (reference-safe)
    $walletMeta = [];
    $ids = array_keys($merged);
    $check = $conn->prepare("SELECT currency_code FROM wallets WHERE wallet_id = ? AND user_id = ? LIMIT 1");
    foreach ($ids as $wid) {
        $widInt = (int)$wid;
        $check->bind_param('ii', $widInt, $user_id);
        $check->execute();
        $r = $check->get_result();
        if (!$r || $r->num_rows !== 1) {
            echo json_encode(["success" => false, "error" => "One or more wallets in split are invalid"]);
            exit;
        }
        $walletMeta[$widInt] = normalize_currency((string)($r->fetch_assoc()['currency_code'] ?? $DEFAULT_CURRENCY));
    }

    foreach ($merged as $wid => $amt) {
        $splitPlan[] = [
            'wallet_id' => (int)$wid,
            'amount_input' => (float)$amt,
            'wallet_currency' => $walletMeta[(int)$wid],
        ];
    }

    // Keep a deterministic order (largest split first)
    usort($splitPlan, fn($a, $b) => $b['amount_input'] <=> $a['amount_input']);
} else {
    // Verify wallet belongs to user
    $w = $conn->prepare("SELECT balance, currency_code FROM wallets WHERE wallet_id = ? AND user_id = ? LIMIT 1");
    $w->bind_param('ii', $wallet_id, $user_id);
    $w->execute();
    $wRes = $w->get_result();
    if (!$wRes || $wRes->num_rows !== 1) {
        echo json_encode(["success" => false, "error" => "Invalid wallet"]);
        exit;
    }
}

// Optional: verify category belongs to user (if provided)
if ($category_id !== null) {
    $c = $conn->prepare("SELECT 1 FROM categories WHERE category_id = ? AND user_id = ? LIMIT 1");
    $c->bind_param('ii', $category_id, $user_id);
    $c->execute();
    $cRes = $c->get_result();
    if (!$cRes || $cRes->num_rows !== 1) {
        $category_id = null;
    }
}

$conn->begin_transaction();
try {
    $txn_ids = [];

    if ($hasSplit) {
        foreach ($splitPlan as $part) {
            $wid = (int)$part['wallet_id'];
            $partAmountInput = (float)$part['amount_input'];
            $partAmountBDT = convert_amount($partAmountInput, $currency_code, 'BDT');

            if ($category_id === null) {
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, transaction_type, amount, currency_code, transaction_time, note) VALUES (?, ?, NULL, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iisdsss', $user_id, $wid, $typeDb, $partAmountBDT, $currency_code_db, $transaction_time, $note);
            } else {
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, transaction_type, amount, currency_code, transaction_time, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iiisdsss', $user_id, $wid, $category_id, $typeDb, $partAmountBDT, $currency_code_db, $transaction_time, $note);
            }

            if (!$stmt->execute()) {
                throw new Exception('insert failed');
            }

            $txn_ids[] = (int)$conn->insert_id;

            // Update wallet cached balance
            $walletCurrency = (string)$part['wallet_currency'];
            $amountForWallet = convert_amount($partAmountBDT, 'BDT', $walletCurrency);
            $delta = ($typeDb === 'Income') ? $amountForWallet : -$amountForWallet;
            $u = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE wallet_id = ? AND user_id = ?");
            $u->bind_param('dii', $delta, $wid, $user_id);
            if (!$u->execute()) {
                throw new Exception('wallet update failed');
            }
        }
    } else {
        if ($category_id === null) {
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, transaction_type, amount, currency_code, transaction_time, note) VALUES (?, ?, NULL, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisdsss', $user_id, $wallet_id, $typeDb, $amount_bdt, $currency_code_db, $transaction_time, $note);
        } else {
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, transaction_type, amount, currency_code, transaction_time, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiisdsss', $user_id, $wallet_id, $category_id, $typeDb, $amount_bdt, $currency_code_db, $transaction_time, $note);
        }

        if (!$stmt->execute()) {
            throw new Exception('insert failed');
        }

        $txn_id = (int)$conn->insert_id;
        $txn_ids[] = $txn_id;

        // Update wallet cached balance
        $walletRow = $wRes->fetch_assoc();
        $walletCurrency = normalize_currency((string)($walletRow['currency_code'] ?? $DEFAULT_CURRENCY));
        // Wallet balances may not yet be migrated; adjust in wallet's currency
        $amountForWallet = convert_amount($amount_bdt, 'BDT', $walletCurrency);
        $delta = ($typeDb === 'Income') ? $amountForWallet : -$amountForWallet;
        $u = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE wallet_id = ? AND user_id = ?");
        $u->bind_param('dii', $delta, $wallet_id, $user_id);
        if (!$u->execute()) {
            throw new Exception('wallet update failed');
        }
    }

    // Add notification for transaction
    $transactionTypeLabel = $typeDb === 'Income' ? 'Income' : 'Expense';
    $notificationTitle = "New $transactionTypeLabel Added";
    $notificationMessage = $hasSplit
        ? "$transactionTypeLabel of $amount $currency_code added (split across " . count($txn_ids) . " wallet(s)): $description"
        : "$transactionTypeLabel of $amount $currency_code added: $description";
    $notificationType = $typeDb === 'Income' ? 'transaction_income' : 'transaction_expense';
    add_notification($conn, $user_id, $notificationTitle, $notificationMessage, $notificationType);

    $conn->commit();

    // Generate AI suggestions after successful transaction (only for expenses)
    $ai_suggestions = null;
    if ($typeDb === 'Expense') {
        // Resolve actual category name (if provided) for better insights
        $category_name = '';
        if ($category_id !== null) {
            $catStmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ? AND user_id = ? LIMIT 1");
            if ($catStmt) {
                $catStmt->bind_param('ii', $category_id, $user_id);
                $catStmt->execute();
                $catRes = $catStmt->get_result();
                if ($catRes && $catRes->num_rows > 0) {
                    $catRow = $catRes->fetch_assoc();
                    $category_name = (string)($catRow['category_name'] ?? '');
                }
                $catStmt->close();
            }
        }

        require_once 'ai_suggestions.php';
        try {
            $suggestions_data = generate_savings_suggestions($conn, $user_id, $category_name, $amount_bdt);
            if ($suggestions_data && $suggestions_data['success']) {
                $ai_suggestions = $suggestions_data;
            } else {
                error_log("AI suggestions failed: " . json_encode($suggestions_data));
            }
        } catch (Throwable $ai_error) {
            error_log("AI suggestions error: " . $ai_error->getMessage());
        }
    }

    $response = ["success" => true, "transaction_ids" => $txn_ids, "transaction_id" => $txn_ids[0] ?? null];
    if ($ai_suggestions) {
        $response["ai_suggestions"] = $ai_suggestions;
    }
    echo json_encode($response);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    error_log("Transaction save error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Failed to save transaction", "details" => $e->getMessage()]);
    exit;
}
