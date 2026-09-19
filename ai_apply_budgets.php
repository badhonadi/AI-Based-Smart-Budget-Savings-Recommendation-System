<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/currency_util.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Ensure required budget columns exist
ensure_budget_wallet_column($conn);
ensure_budget_manual_column($conn);

// Add mode column for spend-vs-goal if missing
if (!function_exists('ensure_budget_mode_column')) {
    // Safety: older schema.php won't have it, but in this repo it will after our patch.
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$month = trim((string)($data['month'] ?? ''));
if ($month === '') {
    $month = date('Y-m', strtotime('first day of next month'));
}
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    echo json_encode(['success' => false, 'error' => 'Invalid month format. Use YYYY-MM']);
    exit;
}

$items = $data['items'] ?? null;
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['success' => false, 'error' => 'No items provided']);
    exit;
}

$monthStart = $month . '-01';
$monthEnd = date('Y-m-d', strtotime('last day of ' . $monthStart));

// Normalize + validate category ownership
$clean = [];
foreach ($items as $it) {
    if (!is_array($it)) continue;
    $cid = (int)($it['category_id'] ?? 0);
    $lim = (float)($it['recommended_limit_bdt'] ?? ($it['limit_bdt'] ?? 0));
    if ($cid <= 0 || $lim <= 0) continue;
    $lim = round($lim / 50) * 50;
    if ($lim < 200) $lim = 200;
    $clean[$cid] = $lim; // de-dupe by category
}

if (count($clean) === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid items']);
    exit;
}

// Verify categories exist and are Expense type
$validCategories = [];
$catStmt = $conn->prepare("SELECT category_id, category_name FROM categories WHERE user_id = ? AND category_type = 'Expense' AND category_id = ? LIMIT 1");
foreach ($clean as $cid => $lim) {
    $cidInt = (int)$cid;
    $catStmt->bind_param('ii', $user_id, $cidInt);
    $catStmt->execute();
    $res = $catStmt->get_result();
    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $validCategories[$cidInt] = [
            'category_name' => (string)$row['category_name'],
            'limit_bdt' => (float)$lim
        ];
    }
}

if (count($validCategories) === 0) {
    echo json_encode(['success' => false, 'error' => 'No valid expense categories found in items']);
    exit;
}

$conn->begin_transaction();
try {
    // Find or create a budget container for this month
    $existingBudgetId = null;
    $bSel = $conn->prepare("SELECT budget_id FROM budgets WHERE user_id = ? AND start_date = ? AND end_date = ? ORDER BY budget_id DESC LIMIT 1");
    $bSel->bind_param('iss', $user_id, $monthStart, $monthEnd);
    $bSel->execute();
    $bRes = $bSel->get_result();
    if ($bRes && $bRes->num_rows > 0) {
        $existingBudgetId = (int)$bRes->fetch_assoc()['budget_id'];
    }

    $budget_id = $existingBudgetId;
    if (!$budget_id) {
        $bIns = $conn->prepare("INSERT INTO budgets (user_id, start_date, end_date) VALUES (?, ?, ?)");
        $bIns->bind_param('iss', $user_id, $monthStart, $monthEnd);
        if (!$bIns->execute()) {
            throw new Exception('Failed to create budget');
        }
        $budget_id = (int)$conn->insert_id;
    }

    // Ensure mode column exists (patched in schema.php)
    if (function_exists('ensure_budget_mode_column')) {
        ensure_budget_mode_column($conn);
    }

    // Upsert each budget item
    foreach ($validCategories as $cid => $meta) {
        $limit = (float)$meta['limit_bdt'];

        // Check if item exists for this budget+category
        $sel = $conn->prepare("SELECT budget_item_id FROM budget_items WHERE budget_id = ? AND category_id = ? LIMIT 1");
        $sel->bind_param('ii', $budget_id, $cid);
        $sel->execute();
        $selRes = $sel->get_result();

        if ($selRes && $selRes->num_rows > 0) {
            $biid = (int)$selRes->fetch_assoc()['budget_item_id'];
            if (has_column($conn, 'budget_items', 'budget_mode')) {
                $up = $conn->prepare("UPDATE budget_items SET amount_limit = ?, currency_code = 'BDT', wallet_id = NULL, budget_mode = 'spend' WHERE budget_item_id = ?");
                $up->bind_param('di', $limit, $biid);
            } else {
                $up = $conn->prepare("UPDATE budget_items SET amount_limit = ?, currency_code = 'BDT', wallet_id = NULL WHERE budget_item_id = ?");
                $up->bind_param('di', $limit, $biid);
            }
            if (!$up->execute()) {
                throw new Exception('Failed to update budget item');
            }
        } else {
            if (has_column($conn, 'budget_items', 'budget_mode')) {
                $ins = $conn->prepare("INSERT INTO budget_items (budget_id, category_id, wallet_id, amount_limit, currency_code, budget_mode, manual_progress) VALUES (?, ?, NULL, ?, 'BDT', 'spend', 0)");
                $ins->bind_param('iid', $budget_id, $cid, $limit);
            } else {
                $ins = $conn->prepare("INSERT INTO budget_items (budget_id, category_id, wallet_id, amount_limit, currency_code, manual_progress) VALUES (?, ?, NULL, ?, 'BDT', 0)");
                $ins->bind_param('iid', $budget_id, $cid, $limit);
            }
            if (!$ins->execute()) {
                throw new Exception('Failed to insert budget item');
            }
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'budget_id' => $budget_id,
        'month' => $month,
        'applied_items' => count($validCategories)
    ]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    error_log('ai_apply_budgets error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to apply budgets']);
    exit;
}
