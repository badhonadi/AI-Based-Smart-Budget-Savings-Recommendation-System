<?php
session_start();
require_once 'db.php'; // Apnar connection file
require_once 'currency_util.php';
require_once 'schema.php';
require_once 'notification_util.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure wallet link column exists for budgets
ensure_budget_wallet_column($conn);
ensure_budget_manual_column($conn);
ensure_budget_mode_column($conn);

$user_id = $_SESSION['user_id'];

// ১. Database theke wallets fetch kora (Budget option-e dekhanor jonno)
$wallet_sql = "SELECT wallet_id AS id, wallet_name, balance FROM wallets WHERE user_id = ?";
$wallet_stmt = $conn->prepare($wallet_sql);
$wallet_stmt->bind_param("i", $user_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$wallets = [];
while($row = mysqli_fetch_assoc($wallet_result)){
    $wallets[] = $row;
}

// ২. Budget save korar logic
// Handle manual add money into a budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_manual_budget') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $budget_item_id = (int)($_POST['budget_id'] ?? 0);
    $amount_input = (float)($_POST['amount'] ?? 0);
    $input_currency = normalize_currency((string)($_POST['currency_code'] ?? $DEFAULT_CURRENCY));

    if ($budget_item_id <= 0 || $amount_input <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit();
    }

    // Verify ownership and get budget currency & wallet link
    $q = $conn->prepare("SELECT bi.currency_code, bi.manual_progress, bi.wallet_id, b.user_id FROM budget_items bi JOIN budgets b ON b.budget_id = bi.budget_id WHERE bi.budget_item_id = ? LIMIT 1");
    $q->bind_param("i", $budget_item_id);
    $q->execute();
    $res = $q->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Budget not found']);
        exit();
    }
    $row = $res->fetch_assoc();
    if ((int)$row['user_id'] !== (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $budget_currency = normalize_currency((string)($row['currency_code'] ?? $DEFAULT_CURRENCY));
    $amount_bdt = convert_amount($amount_input, $input_currency, 'BDT');

    // Update manual progress
    $up = $conn->prepare("UPDATE budget_items SET manual_progress = manual_progress + ? WHERE budget_item_id = ?");
    $up->bind_param("di", $amount_bdt, $budget_item_id);
    $up->execute();

    // Return updated current (manual + wallet/txn fallback)
    // Fetch wallet balance if linked
    $wallet_balance_bdt = 0.0;
    if (!empty($row['wallet_id'])) {
        $wq = $conn->prepare("SELECT balance, currency_code FROM wallets WHERE wallet_id = ? AND user_id = ? LIMIT 1");
        $wq->bind_param("ii", $row['wallet_id'], $_SESSION['user_id']);
        $wq->execute();
        $wr = $wq->get_result();
        if ($wr && $wr->num_rows > 0) {
            $wrow = $wr->fetch_assoc();
            $wallet_balance_bdt = convert_amount((float)$wrow['balance'], normalize_currency((string)$wrow['currency_code']), 'BDT');
        }
    }

    // Manual progress
    $manual_stmt = $conn->prepare("SELECT manual_progress FROM budget_items WHERE budget_item_id = ? LIMIT 1");
    $manual_stmt->bind_param("i", $budget_item_id);
    $manual_stmt->execute();
    $manual_res = $manual_stmt->get_result();
    $manual = 0.0;
    if ($manual_res && $manual_res->num_rows > 0) {
        $manual = (float)$manual_res->fetch_assoc()['manual_progress'];
    }

    $current_bdt = $manual + $wallet_balance_bdt; // txn fallback skipped here for speed; UI will reload page if needed
    $current_display = convert_amount($current_bdt, 'BDT', $budget_currency);

    echo json_encode([
        'success' => true,
        'current' => $current_display,
        'manual_progress' => $manual,
        'currency_code' => $budget_currency,
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_budget'])) {
    $name = trim((string)($_POST['name'] ?? ''));
    $target = (float)($_POST['target'] ?? 0);
    $deadline = trim((string)($_POST['deadline'] ?? ''));
    $emoji = trim((string)($_POST['emoji'] ?? '🎯'));
    $wallet_id = (int)($_POST['wallet_id'] ?? 0);
    $currency_code = normalize_currency((string)($_POST['currency_code'] ?? $DEFAULT_CURRENCY));
    // Force BDT storage for budgets
    $target_bdt = convert_amount($target, $currency_code, 'BDT');
    $budget_currency_db = 'BDT';

    if ($name === '' || $target <= 0 || $deadline === '' || $wallet_id <= 0) {
        die("Error: Please fill required fields");
    }

    // Map existing UI to schema:
    // - budgets: (user_id, start_date=today, end_date=deadline)
    // - budget_items: tie to a category created from (emoji + name), amount_limit = target
    $conn->begin_transaction();

    try {
        // Ensure category exists for this budget item
        $storedCategoryName = $emoji !== '' ? ($emoji . ' ' . $name) : $name;
        $catType = 'Expense';

        $catSel = $conn->prepare("SELECT category_id FROM categories WHERE user_id = ? AND category_name = ? AND category_type = ? LIMIT 1");
        $catSel->bind_param("iss", $user_id, $storedCategoryName, $catType);
        $catSel->execute();
        $catRes = $catSel->get_result();
        if ($catRes && $catRes->num_rows > 0) {
            $category_id = (int)$catRes->fetch_assoc()['category_id'];
        } else {
            $catIns = $conn->prepare("INSERT INTO categories (user_id, category_name, category_type) VALUES (?, ?, ?)");
            $catIns->bind_param("iss", $user_id, $storedCategoryName, $catType);
            $catIns->execute();
            $category_id = (int)$conn->insert_id;
        }

        $start_date = date('Y-m-d');
        $end_date = $deadline;

        $bIns = $conn->prepare("INSERT INTO budgets (user_id, start_date, end_date) VALUES (?, ?, ?)");
        $bIns->bind_param("iss", $user_id, $start_date, $end_date);
        $bIns->execute();
        $budget_id = (int)$conn->insert_id;

        if (has_column($conn, 'budget_items', 'budget_mode')) {
            $biIns = $conn->prepare("INSERT INTO budget_items (budget_id, category_id, wallet_id, amount_limit, currency_code, budget_mode, manual_progress) VALUES (?, ?, ?, ?, ?, 'goal', 0)");
            $biIns->bind_param("iiids", $budget_id, $category_id, $wallet_id, $target_bdt, $budget_currency_db);
        } else {
            $biIns = $conn->prepare("INSERT INTO budget_items (budget_id, category_id, wallet_id, amount_limit, currency_code, manual_progress) VALUES (?, ?, ?, ?, ?, 0)");
            $biIns->bind_param("iiids", $budget_id, $category_id, $wallet_id, $target_bdt, $budget_currency_db);
        }
        $biIns->execute();

        $conn->commit();

        // Add notification after successful commit (don't let notification errors affect budget creation)
        try {
            error_log("Attempting to add notification for user_id: $user_id");
            add_notification(
                $conn,
                $user_id,
                'Budget created',
                "Budget '$name' target " . number_format($target, 2) . " $currency_code set for $deadline",
                'budget'
            );
            error_log("Notification added successfully");
        } catch (Exception $notifError) {
            error_log("Notification error: " . $notifError->getMessage());
        }

        header("Location: budget.php?success=1");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        die("Error: Failed to save budget - " . $e->getMessage());
    }
}

// ৩. Existing budgets dekhonor jonno fetch (map to UI: one card per budget_item)
$budget_sql = "
    SELECT
        bi.budget_item_id AS id,
        c.category_id,
        c.category_name,
        bi.budget_mode,
        bi.wallet_id,
        w.wallet_name,
        w.balance AS wallet_balance,
        w.currency_code AS wallet_currency,
        bi.amount_limit,
        bi.currency_code,
        bi.manual_progress,
        b.start_date,
        b.end_date
    FROM budgets b
    JOIN budget_items bi ON bi.budget_id = b.budget_id
    JOIN categories c ON c.category_id = bi.category_id
    LEFT JOIN wallets w ON w.wallet_id = bi.wallet_id
    WHERE b.user_id = ?
    ORDER BY b.end_date DESC
";
$budget_stmt = $conn->prepare($budget_sql);
$budget_stmt->bind_param("i", $user_id);
$budget_stmt->execute();
$budget_result = $budget_stmt->get_result();

$budgets_for_js = [];
while ($r = $budget_result->fetch_assoc()) {
    $rawName = (string)$r['category_name'];
    $icon = '🎯';
    $displayName = $rawName;
    if (preg_match('/^(\X)\s+(.+)$/u', $rawName, $m)) {
        $icon = $m[1];
        $displayName = $m[2];
    }

    $budgetCurrency = normalize_currency((string)($r['currency_code'] ?? $DEFAULT_CURRENCY));
    $current = 0.0;
    $manual = (float)($r['manual_progress'] ?? 0);
    $mode = (string)($r['budget_mode'] ?? 'goal');

    if ($mode === 'spend') {
        // Monthly spending budget: progress is total Expense for the category within the window
        $startDateTime = $r['start_date'] . ' 00:00:00';
        $endDateTime = $r['end_date'] . ' 23:59:59';
        $spentBdt = 0.0;
        $txnStmt = $conn->prepare("SELECT amount FROM transactions WHERE user_id = ? AND category_id = ? AND transaction_type = 'Expense' AND transaction_time >= ? AND transaction_time <= ?");
        $txnStmt->bind_param("iiss", $user_id, $r['category_id'], $startDateTime, $endDateTime);
        $txnStmt->execute();
        $txnRes = $txnStmt->get_result();
        if ($txnRes) {
            while ($tx = $txnRes->fetch_assoc()) {
                $spentBdt += (float)($tx['amount'] ?? 0);
            }
        }

        $current = convert_amount($spentBdt, 'BDT', $budgetCurrency);
        $manual = 0.0;
    } else {

    // Always add manual progress first (stored in BDT)
    $current += convert_amount($manual, 'BDT', $budgetCurrency);

    if (!empty($r['wallet_id']) && $r['wallet_balance'] !== null) {
        // Use linked wallet balance as progress toward the budget goal
        $walletCurrency = normalize_currency((string)($r['wallet_currency'] ?? $DEFAULT_CURRENCY));
        $current = convert_amount((float)$r['wallet_balance'], $walletCurrency, $budgetCurrency);
    } else {
        // Fallback: sum expenses for the category within the budget window
        $startDateTime = $r['start_date'] . ' 00:00:00';
        $endDateTime = $r['end_date'] . ' 23:59:59';

        $txnStmt = $conn->prepare("SELECT amount, currency_code FROM transactions WHERE user_id = ? AND category_id = ? AND transaction_type = 'Expense' AND transaction_time >= ? AND transaction_time <= ?");
        $txnStmt->bind_param("iiss", $user_id, $r['category_id'], $startDateTime, $endDateTime);
        $txnStmt->execute();
        $txnRes = $txnStmt->get_result();
        if ($txnRes) {
            while ($tx = $txnRes->fetch_assoc()) {
                $current += convert_amount((float)$tx['amount'], (string)($tx['currency_code'] ?? $DEFAULT_CURRENCY), $budgetCurrency);
            }
        }
    }

    }

    $budgets_for_js[] = [
        'id' => (string)$r['id'],
        'name' => $displayName,
        'target' => (float)$r['amount_limit'],
        'current' => (float)$current,
        'currency_code' => $budgetCurrency,
        'deadline' => $r['end_date'],
        'startDate' => $r['start_date'],
        'icon' => $icon,
        'walletId' => !empty($r['wallet_id']) ? (string)$r['wallet_id'] : '',
        'walletName' => (string)($r['wallet_name'] ?? ''),
        'manual' => (float)$manual,
        'mode' => $mode
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgets - Budget Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #a855f7;
            --primary-dark: #9333ea;
            --secondary: #ec4899;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --bg-gradient: linear-gradient(135deg, #faf9fc 0%, #f5f3ff 50%, #ede9fe 100%);
        }

        body.dark-mode {
            --sidebar-bg: #0f0628;
            --card-bg: rgba(15, 6, 40, 0.95);
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --border: rgba(139, 92, 246, 0.25);
            --bg-gradient: linear-gradient(135deg, #0a0118 0%, #1a0b3e 50%, #0f0628 100%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 50%, rgba(236, 72, 153, 0.15) 0%, transparent 50%);
            animation: pulse 8s ease-in-out infinite;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 256px;
            background: var(--sidebar-bg);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 1.5rem;
            z-index: 50;
            border-right: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
            transition: all 0.3s ease;
        }

        body.dark-mode .logo-icon {
            background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.6);
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 900;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        body.dark-mode .logo-text {
            background: linear-gradient(135deg, #c084fc 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-item {
            padding: 0.875rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-item:hover {
            background: rgba(168, 85, 247, 0.1);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }

        .main-content {
            margin-left: 256px;
            flex: 1;
            min-height: 100vh;
        }

        .top-bar {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        body.dark-mode .top-bar {
            background: rgba(15, 6, 40, 0.8);
        }

        .search-bar {
            flex: 1;
            max-width: 600px;
        }

        .search-bar input {
            width: 100%;
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(168, 85, 247, 0.5);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: rgba(168, 85, 247, 0.1);
            border-color: var(--primary);
        }

        .btn-small {
            padding: 0.375rem 0.875rem;
            font-size: 0.875rem;
        }

        .theme-toggle {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        /* Currency Selector */
        .currency-selector {
            position: relative;
        }

        .currency-button {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 100px;
        }

        .currency-button:hover {
            border-color: var(--primary);
            background: rgba(168, 85, 247, 0.05);
        }

        .currency-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 100;
            min-width: 140px;
            overflow: hidden;
            display: none;
        }

        .currency-dropdown.show {
            display: block;
        }

        .currency-option {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
        }

        .currency-option:last-child {
            border-bottom: none;
        }

        .currency-option:hover {
            background: rgba(168, 85, 247, 0.1);
        }

        .currency-option.active {
            background: rgba(168, 85, 247, 0.15);
            color: var(--primary);
            font-weight: 700;
        }

        .content-area {
            padding: 2rem;
        }

        .budgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .budget-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
        }

        .budget-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(168, 85, 247, 0.2);
            border-color: var(--primary);
        }

        .budget-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .budget-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .budget-icon {
            font-size: 1.5rem;
        }

        .budget-name {
            font-size: 1.125rem;
            font-weight: 700;
        }

        .budget-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
            font-weight: 600;
        }

        .meta-pill {
            background: rgba(168, 85, 247, 0.08);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.6rem 0.75rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-pill strong {
            color: var(--text-secondary);
            font-weight: 700;
        }

        .budget-add-manual {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .budget-add-manual input {
            flex: 1;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: rgba(168, 85, 247, 0.05);
            color: var(--text-primary);
            font-weight: 600;
        }

        .budget-add-manual input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.15);
        }
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .budget-action-btn.delete:hover {
            background: var(--danger);
        }

        .budget-progress {
            margin-bottom: 1rem;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .progress-current {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .progress-target {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .progress-bar-container {
            width: 100%;
            height: 12px;
            background: var(--border);
            border-radius: 100px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            border-radius: 100px;
            transition: width 0.3s ease;
        }

        .progress-percentage {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .budget-savings-plan {
            margin-top: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px dashed var(--border);
            background: rgba(168, 85, 247, 0.05);
        }

        .budget-savings-plan .plan-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            margin-bottom: 0.35rem;
        }

        .budget-savings-plan .plan-row:last-child {
            margin-bottom: 0;
        }

        .plan-label {
            color: var(--text-secondary);
            font-weight: 600;
        }

        .plan-value {
            font-weight: 800;
            color: var(--text-primary);
        }

        .status-row {
            align-items: flex-start;
            gap: 0.5rem;
        }

        .status-chip {
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.12);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .status-danger .status-chip {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .status-detail {
            flex: 1;
            color: var(--text-secondary);
            line-height: 1.3;
        }

        .suggestion-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .budget-deadline {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .budget-wallet {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            padding: 0.5rem 0.75rem;
            background: rgba(168, 85, 247, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(168, 85, 247, 0.2);
        }

        .budget-add-funds {
            display: flex;
            gap: 0.5rem;
        }

        .budget-add-funds input {
            flex: 1;
            padding: 0.5rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .budget-add-funds input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .hidden {
            display: none !important;
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            pointer-events: none;
        }

        .toast {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 320px;
            max-width: 400px;
            animation: slideInRight 0.3s ease-out;
            pointer-events: auto;
            border-left: 4px solid;
        }

        body.dark-mode .toast {
            background: var(--card-bg);
            border-color: var(--border);
        }

        .toast.toast-removing {
            animation: slideOutRight 0.3s ease-in forwards;
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        .toast.info {
            border-left-color: var(--info);
        }

        .toast-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .toast.success .toast-icon {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .toast.error .toast-icon {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .toast.info .toast-icon {
            background: rgba(59, 130, 246, 0.15);
            color: var(--info);
        }

        .toast-message {
            flex: 1;
            font-weight: 600;
            color: var(--text-primary);
        }

        .toast-close {
            width: 24px;
            height: 24px;
            border: none;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.2s;
            flex-shrink: 0;
        }

        body.dark-mode .toast-close {
            background: rgba(255, 255, 255, 0.05);
        }

        .toast-close:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        body.dark-mode .toast-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Custom Confirm Dialog */
        .confirm-dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .confirm-dialog.show {
            display: flex;
        }

        .confirm-content {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
        }

        .confirm-title {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .confirm-message {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .confirm-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .confirm-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .confirm-btn.cancel {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
        }

        .confirm-btn.cancel:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .confirm-btn.ok {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .confirm-btn.ok:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
        }

        .modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-help {
            display: block;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
        }

        /* Emoji Picker Styles */
        .emoji-picker-button {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            text-align: left;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .emoji-picker-button:hover {
            border-color: var(--primary);
        }

        .emoji-picker-button .emoji-display {
            font-size: 2rem;
        }

        .emoji-picker-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 0.5rem;
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 100;
            max-height: 320px;
            overflow-y: auto;
            padding: 1rem;
        }

        .emoji-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .emoji-picker-header span {
            font-weight: 600;
            color: var(--text-primary);
        }

        .emoji-picker-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.25rem;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .emoji-picker-close:hover {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 0.5rem;
        }

        .emoji-option {
            font-size: 1.5rem;
            padding: 0.5rem;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .emoji-option:hover {
            background: rgba(168, 85, 247, 0.1);
            transform: scale(1.1);
        }

        .emoji-option.selected {
            background: rgba(168, 85, 247, 0.2);
            box-shadow: 0 0 0 2px var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .empty-state-text {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .budget-section {
            margin-bottom: 3rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .section-header h3 {
            font-size: 1.375rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
        }

        .section-badge {
            background: var(--primary);
            color: white;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .section-header.completed .section-badge {
            background: var(--success);
        }

        .budget-card.completed {
            opacity: 0.7;
            border-color: var(--success);
        }

        .budget-card.completed .budget-progress {
            opacity: 0.8;
        }

        .budget-card.completed .progress-bar {
            background: var(--success);
        }

        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }

            .budgets-grid {
                grid-template-columns: 1fr;
            }

            .emoji-grid {
                grid-template-columns: repeat(6, 1fr);
            }

            .toast {
                min-width: 280px;
            }
        }
    </style>
        <!-- Currency helpers (needed for format/convert) -->
        <script src="currency.js"></script>
    </head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-section">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 7C3 5.89543 3.89543 5 5 5H19C20.1046 5 21 5.89543 21 7V17C21 18.1046 20.1046 19 19 19H5C3.89543 19 3 18.1046 3 17V7Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 9H7.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 9H17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 13H7.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 13H17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="logo-text">BudgetTracker</div>
            </div>
            
            <ul class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    🏠 Home
                </a>
                <a href="category.php" class="nav-item">
                    📁 Categories
                </a>
                <a href="wallet.php" class="nav-item">
                    👛 Wallets
                </a>
                <a href="budget.php" class="nav-item active">
                    🎯 Budgets
                </a>
                <a href="transaction.php" class="nav-item">
                    📝 Transactions
                </a>
                <a href="bill.php" class="nav-item" style="text-decoration: none;">
                    📋 Bills
                </a>
                <a href="analytics.php" class="nav-item">
                    📊 Analytics
                </a>
                <a href="dashboard.php" class="nav-item">
                    👤 Profile
                </a>
                <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                    🚪 Logout
                </a>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-bar">
                    <input type="text" placeholder="Search budgets..." id="searchBudgets" oninput="searchBudgets()">
                </div>
                <div class="top-bar-actions">
                    <!-- Currency Selector -->
                    <div class="currency-selector">
                        <button class="currency-button" onclick="toggleCurrencyDropdown()">
                            <span id="currentCurrencySymbol">$</span>
                            <span id="currentCurrencyCode">USD</span>
                        </button>
                        <div id="currencyDropdown" class="currency-dropdown">
                            <div class="currency-option active" onclick="changeCurrency('USD', '$')">$ USD</div>
                            <div class="currency-option" onclick="changeCurrency('EUR', '€')">€ EUR</div>
                            <div class="currency-option" onclick="changeCurrency('BDT', '৳')">৳ BDT</div>
                            <div class="currency-option" onclick="changeCurrency('INR', '₹')">₹ INR</div>
                            <div class="currency-option" onclick="changeCurrency('GBP', '£')">£ GBP</div>
                            <div class="currency-option" onclick="changeCurrency('JPY', '¥')">¥ JPY</div>
                            <div class="currency-option" onclick="changeCurrency('CNY', '¥')">¥ CNY</div>
                            <div class="currency-option" onclick="changeCurrency('AUD', '$')">$ AUD</div>
                            <div class="currency-option" onclick="changeCurrency('CAD', '$')">$ CAD</div>
                        </div>
                    </div>

                    <button class="btn btn-outline theme-toggle" onclick="toggleDarkMode()">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <button class="btn btn-primary" onclick="openModal('budgetModal')">
                        ➕ Add Budget Goal
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div style="margin-bottom: 2rem;">
                    <h2 style="font-size: 2rem; font-weight: 800;">Budget Goals</h2>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem;">Track your savings goals and progress</p>
                </div>

                <div class="card" style="margin-bottom: 1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 1rem; flex-wrap: wrap;">
                        <div>
                            <h3 style="font-weight: 900; font-size: 1.25rem;">🤖 AI Budget Autopilot</h3>
                            <p style="color: var(--text-secondary); margin-top: 0.25rem;">Generates next month spending budgets from your last 3 months + bills</p>
                        </div>
                        <div style="display:flex; gap: 0.5rem; align-items:center; flex-wrap: wrap;">
                            <input type="month" id="aiBudgetMonth" class="form-input" style="width: 170px;" />
                            <button class="btn btn-secondary" type="button" onclick="generateAIBudgetAutopilot()">Generate</button>
                            <button class="btn btn-primary" type="button" onclick="applyAIBudgetAutopilot()">Apply to month</button>
                        </div>
                    </div>
                    <div id="aiBudgetAutopilot" style="margin-top: 1rem;"></div>
                </div>

                <!-- Uncompleted Budgets Section -->
                <div class="budget-section" id="uncompletedSection">
                    <div class="section-header">
                        <h3>🎯 Active Budgets</h3>
                        <span class="section-badge" id="uncompletedCount">0</span>
                    </div>
                    <div class="budgets-grid" id="uncompletedGrid"></div>
                </div>

                <!-- Completed Budgets Section -->
                <div class="budget-section" id="completedSection" style="display: none;">
                    <div class="section-header completed">
                        <h3>✅ Completed Budgets</h3>
                        <span class="section-badge" id="completedCount">0</span>
                    </div>
                    <div class="budgets-grid" id="completedGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Custom Confirm Dialog -->
    <div id="confirmDialog" class="confirm-dialog">
        <div class="confirm-content">
            <h3 class="confirm-title" id="confirmTitle">Confirm</h3>
            <p class="confirm-message" id="confirmMessage">Are you sure?</p>
            <div class="confirm-buttons">
                <button class="confirm-btn cancel" onclick="cancelConfirm()">Cancel</button>
                <button class="confirm-btn ok" onclick="confirmOk()">OK</button>
            </div>
        </div>
    </div>

    <!-- Budget Modal -->
<div id="budgetModal" class="modal">
    <div class="modal-content">

        <div class="modal-header">
            <h3 style="font-size: 1.5rem; font-weight: 800;" id="budgetModalTitle">
                Add Budget Goal
            </h3>
            <button onclick="closeModal('budgetModal')"
                style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none;
                width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">
                ✕
            </button>
        </div>

        <!-- FORM START -->
        <form id="budgetForm" method="POST" action="budget.php">

            <!-- hidden fields -->
            <input type="hidden" name="create_budget" value="1">
            <input type="hidden" name="emoji" id="emojiInput" value="🎯">
            <input type="hidden" name="currency_code" id="budgetCurrencyCode" value="BDT">

            <div class="modal-body">

                <div class="form-group">
                    <label class="form-label">Goal Name</label>
                    <input type="text"
                           class="form-input"
                           id="budgetName"
                           name="name"
                           placeholder="e.g., New Car, Vacation"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Target Amount</label>
                    <input type="number"
                           class="form-input"
                           id="budgetTarget"
                           name="target"
                           placeholder="0.00"
                           step="0.01"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Current Amount</label>
                    <input type="number"
                           class="form-input"
                           id="budgetCurrent"
                           name="current"
                           value="0"
                           step="0.01">
                </div>

                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="date"
                           class="form-input"
                           id="budgetDeadline"
                           name="deadline"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Wallet</label>
                    <select name="wallet_id"
                            id="budgetWallet"
                            class="form-select"
                            required>
                        <option value="">Select a wallet</option>
                        <?php foreach($wallets as $wallet): ?>
                            <option value="<?php echo $wallet['id']; ?>">
                                <?php echo htmlspecialchars($wallet['wallet_name']); ?>
                                (Bal: <?php echo $wallet['balance']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Icon (Optional)</label>
                    <button type="button"
                            class="emoji-picker-button"
                            onclick="toggleEmojiPicker()">
                        <span class="emoji-display" id="selectedEmoji">🎯</span>
                        <span style="color: var(--text-secondary);" id="emojiButtonText">
                            Select an icon
                        </span>
                    </button>

                    <div id="emojiPicker" class="emoji-picker-dropdown hidden">
                        <div class="emoji-picker-header">
                            <span>Choose an Icon</span>
                            <button type="button"
                                    class="emoji-picker-close"
                                    onclick="closeEmojiPicker()">✕</button>
                        </div>
                        <div class="emoji-grid" id="emojiGrid"></div>
                    </div>

                    <span class="form-help">
                        Choose an emoji to represent your budget goal
                    </span>
                </div>

                <button type="button"
                        class="btn btn-primary"
                        style="width: 100%;"
                        onclick="saveBudget()"
                        id="budgetSaveBtn">
                    Create Budget Goal
                </button>

            </div>
        </form>
        <!-- FORM END -->

    </div>
</div>


    <script>
        const EXCHANGE_RATES = Currency.RATES;

        const CURRENCY_SYMBOLS = Currency.SYMBOLS;

        const BUDGET_CURRENCY_KEY = 'currency_budgets';

        // Budget Emojis
        const BUDGET_EMOJIS = [
            '🎯', '💰', '💵', '💴', '💶', '💷', '💳', '💎', '🏆', '🎖️',
            '⭐', '🌟', '✨', '🔥', '💪', '🚀', '🏠', '🏡', '🏢', '🏪',
            '🏦', '🏨', '🏖️', '✈️', '🚗', '🚙', '🏍️', '🚲', '🎓', '📚',
            '💻', '📱', '⌚', '👔', '👗', '👠', '💄', '🎮', '🎸', '🎨',
            '🎬', '📷', '🎵', '🎹', '🏋️', '⚽', '🏀', '🎾', '🏐', '🎳',
            '🍕', '🍔', '🍜', '☕', '🍷', '🎂', '🎁', '🎈', '💐', '🌹',
            '🌺', '🌻', '🌴', '🌈', '☀️', '⚡', '❄️', '🔑', '🔒', '💡'
        ];

        // App State
        let appState = {
            budgets: [],
            wallets: [],
            currentCurrency: Currency.DEFAULT,
            editingBudgetId: null,
            selectedEmoji: '🎯',
            confirmCallback: null
        };

        // Load data from localStorage
        function loadData() {
            //const savedBudgets = localStorage.getItem('budgets');
            //const savedWallets = localStorage.getItem('wallets');
            const savedCurrency = localStorage.getItem(BUDGET_CURRENCY_KEY);
            
            /*if (savedBudgets) {
                appState.budgets = JSON.parse(savedBudgets);
            }

            if (savedWallets) {
                appState.wallets = JSON.parse(savedWallets);
            } else {
                // Create default wallets
                appState.wallets = [
                    { id: '1', name: 'Cash', balance: 1000, icon: '💵' },
                    { id: '2', name: 'Bank Account', balance: 5000, icon: '🏦' },
                    { id: '3', name: 'Credit Card', balance: 2000, icon: '💳' }
                ];
                localStorage.setItem('wallets', JSON.stringify(appState.wallets));
            }*/

            if (savedCurrency) {
                appState.currentCurrency = Currency.normalize(savedCurrency);
            } else {
                appState.currentCurrency = Currency.DEFAULT;
            }

            updateCurrencyDisplay();
        }

        // Initialize
        function init() {
            loadData();
            renderBudgets();
            //renderWalletOptions();
            renderEmojiGrid();
            loadTheme();
            const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast("Budget goal saved successfully!", "success");
    }
    // -------------------------------------------
}
        

        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.getElementById('themeIcon').textContent = '☀️';
            }
        }

        // Dark Mode
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('themeIcon');
            icon.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        // Toast Notification System
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icons = {
                success: '✓',
                error: '✕',
                info: 'ℹ'
            };

            toast.innerHTML = `
                <div class="toast-icon">${icons[type]}</div>
                <div class="toast-message">${message}</div>
                <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
            `;

            container.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('toast-removing');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Custom Confirm Dialog
        function customConfirm(message, callback) {
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmDialog').classList.add('show');
            appState.confirmCallback = callback;
        }

        function confirmOk() {
            document.getElementById('confirmDialog').classList.remove('show');
            if (appState.confirmCallback) {
                appState.confirmCallback();
                appState.confirmCallback = null;
            }
        }

        function cancelConfirm() {
            document.getElementById('confirmDialog').classList.remove('show');
            appState.confirmCallback = null;
        }

        // Custom Alert
        function customAlert(message) {
            showToast(message, 'error');
        }

        // Currency Functions
        function toggleCurrencyDropdown() {
            const dropdown = document.getElementById('currencyDropdown');
            dropdown.classList.toggle('show');
        }

        function changeCurrency(code) {
            const norm = Currency.normalize(code);
            appState.currentCurrency = norm;
            localStorage.setItem(BUDGET_CURRENCY_KEY, norm);
            updateCurrencyDisplay();
            renderBudgets();
            toggleCurrencyDropdown();
            showToast(`Currency changed to ${norm}`, 'info');
        }

        function updateCurrencyDisplay() {
            const code = appState.currentCurrency;
            const symbol = CURRENCY_SYMBOLS[code];
            document.getElementById('currentCurrencyCode').textContent = code;
            document.getElementById('currentCurrencySymbol').textContent = symbol;
            const currencyField = document.getElementById('budgetCurrencyCode');
            if (currencyField) {
                currencyField.value = code;
            }

            // Update active state in dropdown
            document.querySelectorAll('.currency-option').forEach(option => {
                option.classList.remove('active');
                if (option.textContent.includes(code)) {
                    option.classList.add('active');
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const currencySelector = document.querySelector('.currency-selector');
            const emojiPicker = document.getElementById('emojiPicker');
            const emojiButton = document.querySelector('.emoji-picker-button');
            
            if (currencySelector && !currencySelector.contains(event.target)) {
                document.getElementById('currencyDropdown').classList.remove('show');
            }
            
            if (!emojiPicker.contains(event.target) && !emojiButton.contains(event.target)) {
                closeEmojiPicker();
            }
        });

        // Modal
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            if (modalId === 'budgetModal') {
                resetBudgetForm();
            }
        }

        // Emoji Picker
        function renderEmojiGrid() {
            const grid = document.getElementById('emojiGrid');
            grid.innerHTML = BUDGET_EMOJIS.map(emoji => 
                `<button type="button" class="emoji-option" onclick="selectEmoji('${emoji}')">${emoji}</button>`
            ).join('');
        }

        function toggleEmojiPicker() {
            const picker = document.getElementById('emojiPicker');
            picker.classList.toggle('hidden');
        }

        function closeEmojiPicker() {
            document.getElementById('emojiPicker').classList.add('hidden');
        }

        function selectEmoji(emoji) {
    appState.selectedEmoji = emoji;
    document.getElementById('selectedEmoji').textContent = emoji;
    document.getElementById('emojiInput').value = emoji;
    document.getElementById('emojiButtonText').textContent = 'Change icon';
    closeEmojiPicker();
}


        // Wallet Options
        function renderWalletOptions() {
            const select = document.getElementById('budgetWallet');
            const options = appState.wallets.map(wallet => 
                `<option value="${wallet.id}">${wallet.icon} ${wallet.name}</option>`
            ).join('');
            select.innerHTML = '<option value="">No wallet selected</option>' + options;
        }

        // Currency Formatting
        function formatCurrency(amount, fromCode) {
            const target = appState.currentCurrency || Currency.DEFAULT;
            const source = fromCode || target;
            return Currency.formatAmount(amount, source, target);
        }

        // Budgets
        function renderBudgets() {
            const uncompletedGrid = document.getElementById('uncompletedGrid');
            const completedGrid = document.getElementById('completedGrid');
            const uncompletedSection = document.getElementById('uncompletedSection');
            const completedSection = document.getElementById('completedSection');
            
            if (appState.budgets.length === 0) {
                uncompletedGrid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <div class="empty-state-icon">🎯</div>
                        <h3 class="empty-state-title">No Budget Goals Yet</h3>
                        <p class="empty-state-text">Create your first budget goal to start tracking</p>
                        <button class="btn btn-primary" onclick="openModal('budgetModal')">➕ Add Budget Goal</button>
                    </div>
                `;
                completedSection.style.display = 'none';
                return;
            }

            // Separate budgets into uncompleted and completed
            const uncompletedBudgets = [];
            const completedBudgets = [];

            appState.budgets.forEach(budget => {
                // Check if budget is completed: deadline passed AND target reached
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const deadline = new Date(budget.deadline);
                deadline.setHours(0, 0, 0, 0);
                const isDeadlinePassed = deadline < today;
                const mode = budget.mode || 'goal';
                const isTargetReached = mode === 'goal' ? (budget.current >= budget.target) : false;
                
                // A budget is completed if deadline has passed OR target is reached
                if (mode === 'spend') {
                    if (isDeadlinePassed) completedBudgets.push(budget);
                    else uncompletedBudgets.push(budget);
                } else if (isDeadlinePassed || isTargetReached) {
                    completedBudgets.push(budget);
                } else {
                    uncompletedBudgets.push(budget);
                }
            });

            // Render uncompleted budgets
            if (uncompletedBudgets.length === 0) {
                uncompletedGrid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <div class="empty-state-icon">🎉</div>
                        <h3 class="empty-state-title">All Caught Up!</h3>
                        <p class="empty-state-text">You have no active budgets. Great job!</p>
                        <button class="btn btn-primary" onclick="openModal('budgetModal')">➕ Add New Budget Goal</button>
                    </div>
                `;
            } else {
                uncompletedGrid.innerHTML = uncompletedBudgets.map(budget => 
                    renderBudgetCard(budget, false)
                ).join('');
            }
            document.getElementById('uncompletedCount').textContent = uncompletedBudgets.length;

            // Render completed budgets
            if (completedBudgets.length > 0) {
                completedSection.style.display = 'block';
                completedGrid.innerHTML = completedBudgets.map(budget => 
                    renderBudgetCard(budget, true)
                ).join('');
                document.getElementById('completedCount').textContent = completedBudgets.length;
            } else {
                completedSection.style.display = 'none';
            }
        }

        function renderBudgetCard(budget, isCompleted) {
            const mode = budget.mode || 'goal';

            if (mode === 'spend') {
                const limit = parseFloat(budget.target) || 0;
                const spent = parseFloat(budget.current) || 0;
                const pct = limit > 0 ? Math.min(140, (spent / limit) * 100) : 0;
                const remaining = limit - spent;
                const daysLeft = Math.max(0, Math.ceil((new Date(budget.deadline) - new Date()) / (1000 * 60 * 60 * 24)));
                const perDay = daysLeft > 0 ? Math.max(0, remaining) / daysLeft : Math.max(0, remaining);
                const isOver = remaining < -0.01;
                const statusText = isOver
                    ? `You are over budget by ${formatCurrency(Math.abs(remaining), budget.currency_code)}.`
                    : `You have ${formatCurrency(remaining, budget.currency_code)} left for this budget.`;

                return `
                    <div class="budget-card${isCompleted ? ' completed' : ''}">
                        <div class="budget-header">
                            <div class="budget-title">
                                <span class="budget-icon">${budget.icon || '📊'}</span>
                                <span class="budget-name">${budget.name}</span>
                            </div>
                            <div class="budget-actions">
                                <button class="budget-action-btn delete" onclick="deleteBudget('${budget.id}')" title="Delete">🗑️</button>
                            </div>
                        </div>

                        <div class="budget-progress">
                            <div class="progress-info">
                                <span class="progress-current">${formatCurrency(spent, budget.currency_code)}</span>
                                <span class="progress-target">spent of ${formatCurrency(limit, budget.currency_code)}</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: ${pct}%; background: ${isOver ? 'var(--danger)' : 'var(--primary)'}"></div>
                            </div>
                            <div class="progress-percentage">${(limit > 0 ? ((spent / limit) * 100) : 0).toFixed(0)}% Used</div>
                        </div>

                        <div class="budget-deadline">
                            📅 ${isCompleted ? 'Month completed' : `${daysLeft} days left`}
                        </div>

                        <div class="budget-meta">
                            <div class="meta-pill">📌 <strong>Type</strong> Monthly spending</div>
                            <div class="meta-pill">⏳ <strong>Remaining</strong> ${isOver ? '<span style="color: var(--danger); font-weight: 700;">' + formatCurrency(Math.abs(remaining), budget.currency_code) + ' over</span>' : formatCurrency(remaining, budget.currency_code)}</div>
                            <div class="meta-pill">🎯 <strong>Limit</strong> ${formatCurrency(limit, budget.currency_code)}</div>
                        </div>

                        <div class="budget-savings-plan">
                            <div class="plan-row status-row ${isOver ? 'status-danger' : ''}">
                                <div class="status-chip">${isOver ? 'Over' : 'On track'}</div>
                                <div class="status-detail">${statusText}</div>
                            </div>
                            ${!isCompleted ? `
                                <div class="plan-row">
                                    <span class="plan-label">Safe daily spend</span>
                                    <span class="plan-value">${formatCurrency(perDay, budget.currency_code)}</span>
                                </div>
                            ` : ''}
                            <div class="plan-row suggestion-row">
                                <span class="plan-label">Recommendation</span>
                                <span class="plan-value">${isOver ? 'Freeze this category for 3–5 days and review recent transactions.' : 'Stay under the daily limit to finish the month safely.'}</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            const percentage = Math.min(100, (budget.target > 0 ? (budget.current / budget.target) * 100 : 0));
            const remaining = Math.max(0, budget.target - budget.current);
            const daysLeft = Math.ceil((new Date(budget.deadline) - new Date()) / (1000 * 60 * 60 * 24));
            const isOverdue = daysLeft < 0;
            const isClose = daysLeft <= 7 && daysLeft >= 0;
            const wallet = budget.walletId ? { name: budget.walletName || 'Linked wallet' } : null;

            // Savings assistant calculations
            const msPerDay = 1000 * 60 * 60 * 24;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const startDate = budget.startDate ? new Date(budget.startDate) : new Date();
            startDate.setHours(0, 0, 0, 0);
            const deadlineDate = new Date(budget.deadline);
            deadlineDate.setHours(0, 0, 0, 0);

            const totalDays = Math.max(1, Math.ceil((deadlineDate - startDate) / msPerDay));
            const remainingDays = Math.max(0, Math.ceil((deadlineDate - today) / msPerDay));
            const elapsedDays = Math.max(0, totalDays - remainingDays);

            const requiredPerDay = remainingDays > 0 ? remaining / remainingDays : remaining;
            const requiredPerMonth = requiredPerDay * 30;
            const requiredPerYear = requiredPerDay * 365;

            const expectedByToday = (budget.target / totalDays) * elapsedDays;
            const deficit = Math.max(0, expectedByToday - budget.current);
            const statusIsBehind = deficit > 0.01;

            const catchUpPerDay = remainingDays > 0 ? (remaining + deficit) / Math.max(1, remainingDays) : remaining + deficit;
            const catchUpPerMonth = catchUpPerDay * 30;
            const catchUpPerYear = catchUpPerDay * 365;

            const statusText = statusIsBehind
                ? `You are behind by ${formatCurrency(deficit, budget.currency_code)} versus the planned pace.`
                : `You're on track for this goal. Keep saving at the planned pace.`;

            const recommendationText = statusIsBehind
                ? `Add ${formatCurrency(deficit, budget.currency_code)} now or save ${formatCurrency(catchUpPerDay, budget.currency_code)} daily (${formatCurrency(catchUpPerMonth, budget.currency_code)} monthly) to finish by ${new Date(budget.deadline).toLocaleDateString()}.`
                : `Save ${formatCurrency(requiredPerDay, budget.currency_code)} daily (${formatCurrency(requiredPerMonth, budget.currency_code)} monthly) to finish by ${new Date(budget.deadline).toLocaleDateString()}.`;

            const completionIndicator = isCompleted ? 
                `<div style="color: var(--success); font-weight: 700; font-size: 1rem; margin-left: auto;">✅ Completed</div>` : 
                '';

            return `
                <div class="budget-card${isCompleted ? ' completed' : ''}">
                    <div class="budget-header">
                        <div class="budget-title">
                            <span class="budget-icon">${budget.icon}</span>
                            <span class="budget-name">${budget.name}</span>
                        </div>
                        <div class="budget-actions">
                            <button class="budget-action-btn" onclick="editBudget('${budget.id}')" title="Edit">✏️</button>
                            <button class="budget-action-btn delete" onclick="deleteBudget('${budget.id}')" title="Delete">🗑️</button>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <div style="flex: 1;"></div>
                        ${completionIndicator}
                    </div>

                    <div class="budget-progress">
                        <div class="progress-info">
                            <span class="progress-current">${formatCurrency(budget.current, budget.currency_code)}</span>
                            <span class="progress-target">of ${formatCurrency(budget.target, budget.currency_code)}</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: ${percentage}%"></div>
                        </div>
                        <div class="progress-percentage">${percentage.toFixed(0)}% Complete</div>
                    </div>

                    <div class="budget-deadline">
                        📅 ${isOverdue ? '<span style="color: var(--danger); font-weight: 600;">Overdue!</span>' : 
                             isClose ? `<span style="color: var(--warning); font-weight: 600;">${daysLeft} days left</span>` : 
                             `Deadline: ${new Date(budget.deadline).toLocaleDateString()}`}
                    </div>

                    <div class="budget-meta">
                        <div class="meta-pill">💼 <strong>Wallet</strong> ${wallet ? wallet.name : 'Not linked'}</div>
                        <div class="meta-pill">⏳ <strong>Remaining</strong> ${formatCurrency(remaining, budget.currency_code)}</div>
                        <div class="meta-pill">🎯 <strong>Target</strong> ${formatCurrency(budget.target, budget.currency_code)}</div>
                        <div class="meta-pill">➕ <strong>Manual</strong> ${formatCurrency(budget.manual || 0, budget.currency_code)}</div>
                    </div>

                    <div class="budget-savings-plan">
                        <div class="plan-row status-row ${statusIsBehind ? 'status-danger' : ''}">
                            <div class="status-chip">${statusIsBehind ? 'Behind' : 'On track'}</div>
                            <div class="status-detail">${statusText}</div>
                        </div>
                        <div class="plan-row">
                            <span class="plan-label">Daily need</span>
                            <span class="plan-value">${formatCurrency(requiredPerDay, budget.currency_code)}</span>
                        </div>
                        <div class="plan-row">
                            <span class="plan-label">Monthly need</span>
                            <span class="plan-value">${formatCurrency(requiredPerMonth, budget.currency_code)}</span>
                        </div>
                        <div class="plan-row">
                            <span class="plan-label">Yearly pace</span>
                            <span class="plan-value">${formatCurrency(requiredPerYear, budget.currency_code)}</span>
                        </div>
                        <div class="plan-row suggestion-row">
                            <span class="plan-label">Recommendation</span>
                            <span class="plan-value">${recommendationText}</span>
                        </div>
                    </div>

                    ${!isCompleted ? `
                        <div class="budget-add-manual">
                            <input type="number" placeholder="Add amount" id="add-${budget.id}" step="0.01">
                            <button class="btn btn-primary btn-small" onclick="addMoney('${budget.id}')">Add</button>
                        </div>
                    ` : ''}
                </div>
            `;
        }
function saveBudget() {
    const form = document.getElementById('budgetForm');
    const name = document.getElementById('budgetName').value.trim();
    const target = parseFloat(document.getElementById('budgetTarget').value);
    const deadline = document.getElementById('budgetDeadline').value;
    const wallet = document.getElementById('budgetWallet').value;
    const saveBtn = document.getElementById('budgetSaveBtn');

    // Validation
    if (!name || !target || target <= 0 || !deadline || !wallet) {
        showToast("Please fill all required fields", "error");
        return;
    }

    // Show loading state
    const originalText = saveBtn.textContent;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Creating...';

    // Submit form
    form.submit();
}


        function editBudget(budgetId) {
            const budget = appState.budgets.find(b => b.id === budgetId);
            if (!budget) return;

            appState.editingBudgetId = budgetId;
            appState.selectedEmoji = budget.icon;
            
            document.getElementById('budgetModalTitle').textContent = 'Edit Budget Goal';
            document.getElementById('budgetSaveBtn').textContent = 'Update Budget Goal';
            
            document.getElementById('budgetName').value = budget.name;
            document.getElementById('budgetTarget').value = budget.target;
            document.getElementById('budgetCurrent').value = budget.current;
            document.getElementById('budgetDeadline').value = budget.deadline;
            document.getElementById('budgetWallet').value = budget.walletId || '';
            document.getElementById('selectedEmoji').textContent = budget.icon;
            document.getElementById('emojiButtonText').textContent = 'Change icon';
            
            openModal('budgetModal');
        }

        async function deleteBudget(budgetId) {
            const budget = appState.budgets.find(b => b.id === budgetId);
            if (!budget) return;

            customConfirm('Are you sure you want to delete this budget goal?', async () => {
                try {
                    const response = await fetch('delete_budget.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ budget_id: budgetId })
                    });

                    const result = await response.json();

                    if (result.success) {
                        appState.budgets = appState.budgets.filter(b => b.id !== budgetId);
                        localStorage.setItem('budgets', JSON.stringify(appState.budgets));
                        renderBudgets();
                        showToast(`Budget goal "${budget.name}" deleted successfully`, 'success');
                    } else {
                        showToast('Failed to delete budget: ' + (result.error || 'Unknown error'), 'error');
                    }
                } catch (error) {
                    console.error('Error deleting budget:', error);
                    showToast('Failed to delete budget. Please try again.', 'error');
                }
            });
        }

        function addFunds(budgetId) {
            const input = document.getElementById(`add-${budgetId}`);
            const amount = parseFloat(input.value) || 0;

            if (amount <= 0) {
                customAlert('Please enter a valid amount');
                return;
            }

            const budget = appState.budgets.find(b => b.id === budgetId);
            if (budget) {
                const amountInBudgetCurrency = Currency.convert(amount, appState.currentCurrency, budget.currency_code || appState.currentCurrency);
                budget.current = Math.min(budget.target, budget.current + amountInBudgetCurrency);
                localStorage.setItem('budgets', JSON.stringify(appState.budgets));
                input.value = '';
                renderBudgets();
                showToast(`Added ${formatCurrency(amount)} to "${budget.name}"`, 'success');
            }
        }

        // Add manual money to budget (persists to DB)
        async function addMoney(budgetId) {
            const input = document.getElementById(`add-${budgetId}`);
            const amount = parseFloat(input.value) || 0;

            if (amount <= 0) {
                customAlert('Please enter a valid amount');
                return;
            }

            try {
                const fd = new FormData();
                fd.append('action', 'add_manual_budget');
                fd.append('budget_id', budgetId);
                fd.append('amount', amount);
                fd.append('currency_code', appState.currentCurrency || Currency.DEFAULT);

                const res = await fetch('budget.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.success) {
                    customAlert(data.message || 'Failed to add money');
                    return;
                }

                // Update local state and re-render
                const b = appState.budgets.find(x => x.id === budgetId);
                if (b) {
                    b.manual = data.manual_progress;
                    // Convert returned current (in budget currency) to display currency via formatCurrency
                    b.current = data.current;
                }
                input.value = '';
                renderBudgets();
                showToast('Money added to budget', 'success');
            } catch (e) {
                console.error(e);
                customAlert('Something went wrong');
            }
        }

        function resetBudgetForm() {
            appState.editingBudgetId = null;
            appState.selectedEmoji = '🎯';
            document.getElementById('budgetModalTitle').textContent = 'Add Budget Goal';
            document.getElementById('budgetSaveBtn').textContent = 'Create Budget Goal';
            document.getElementById('budgetName').value = '';
            document.getElementById('budgetTarget').value = '';
            document.getElementById('budgetCurrent').value = '0';
            document.getElementById('budgetDeadline').value = '';
            document.getElementById('budgetWallet').value = '';
            document.getElementById('selectedEmoji').textContent = '🎯';
            document.getElementById('emojiButtonText').textContent = 'Select an icon';
            closeEmojiPicker();
        }

        function searchBudgets() {
            const searchTerm = document.getElementById('searchBudgets').value.toLowerCase();
            
            if (!searchTerm) {
                renderBudgets();
                return;
            }

            const filteredBudgets = appState.budgets.filter(budget => 
                budget.name.toLowerCase().includes(searchTerm) ||
                budget.icon.includes(searchTerm)
            );

            const originalBudgets = [...appState.budgets];
            appState.budgets = filteredBudgets;
            renderBudgets();
            appState.budgets = originalBudgets;
        }
        // PHP থেকে বাজেট ডেটা জাভাস্ক্রিপ্ট অ্যারেতে নিয়ে আসা
        appState.budgets = <?php echo json_encode($budgets_for_js); ?>;

        // Initialize app
        init();

        // --- AI Budget Autopilot ---
        let __aiBudgetPlan = null;

        function setDefaultAutopilotMonth() {
            const el = document.getElementById('aiBudgetMonth');
            if (!el) return;
            const d = new Date();
            d.setMonth(d.getMonth() + 1);
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            el.value = `${y}-${m}`;
        }

        function renderAIBudgetAutopilot(plan) {
            const box = document.getElementById('aiBudgetAutopilot');
            if (!box) return;
            if (!plan || !Array.isArray(plan.items) || plan.items.length === 0) {
                box.innerHTML = `<div class="empty-state" style="padding: 1rem;">No plan yet. Click Generate.</div>`;
                return;
            }

            const rows = plan.items.map(it => `
                <div style="display:flex; justify-content:space-between; gap: 1rem; padding: 0.6rem 0; border-bottom: 1px solid rgba(148, 163, 184, 0.25);">
                    <div style="font-weight: 700;">${it.category_name || ('Category #' + it.category_id)}</div>
                    <div style="font-weight: 800;">${formatCurrency(it.recommended_limit_bdt, 'BDT')}</div>
                </div>
            `).join('');

            const meta = `Expected income: ${formatCurrency(plan.expected_income_bdt, 'BDT')} • Bills reserve: ${formatCurrency(plan.bills_reserve_bdt, 'BDT')} • Savings target: ${formatCurrency(plan.savings_target_bdt, 'BDT')}`;

            box.innerHTML = `
                <div style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.75rem;">${meta}</div>
                <div>${rows}</div>
                <div style="margin-top: 0.75rem; color: var(--text-secondary); font-size: 0.85rem;">Applies as monthly spending budgets (progress = spent amount).</div>
            `;
        }

        async function generateAIBudgetAutopilot() {
            const month = document.getElementById('aiBudgetMonth')?.value || '';
            const box = document.getElementById('aiBudgetAutopilot');
            if (box) box.innerHTML = `<div class="empty-state" style="padding: 1rem;">⏳ Generating plan…</div>`;

            try {
                const res = await fetch('ai_budget_autopilot.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ month })
                });
                const out = await res.json();
                if (out && out.success) {
                    __aiBudgetPlan = out;
                    renderAIBudgetAutopilot(out);
                    showToast('AI budget plan generated', 'success');
                    return;
                }
                throw new Error(out?.error || 'Failed to generate plan');
            } catch (e) {
                console.error(e);
                __aiBudgetPlan = null;
                if (box) box.innerHTML = `<div class="empty-state" style="padding: 1rem;">Failed to generate plan.</div>`;
                showToast('Failed to generate AI budget plan', 'error');
            }
        }

        async function applyAIBudgetAutopilot() {
            const month = document.getElementById('aiBudgetMonth')?.value || '';
            if (!__aiBudgetPlan || !Array.isArray(__aiBudgetPlan.items) || __aiBudgetPlan.items.length === 0) {
                showToast('Generate a plan first', 'info');
                return;
            }

            try {
                const res = await fetch('ai_apply_budgets.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ month, items: __aiBudgetPlan.items })
                });
                const out = await res.json();
                if (out && out.success) {
                    showToast('Applied monthly budgets', 'success');
                    setTimeout(() => location.reload(), 600);
                    return;
                }
                throw new Error(out?.error || 'Failed to apply budgets');
            } catch (e) {
                console.error(e);
                showToast('Failed to apply budgets', 'error');
            }
        }

        // Setup default month and initial empty render
        setDefaultAutopilotMonth();
        renderAIBudgetAutopilot(null);
    </script>
</body>
</html>
