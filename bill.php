<?php
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function redirect_with(string $query): void {
    header("Location: bill.php?$query");
    exit;
}

// Delete bill payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $bill_payment_id = (int)($_POST['bill_payment_id'] ?? 0);
    if ($bill_payment_id > 0) {
        $stmt = $conn->prepare("DELETE FROM bill_payments WHERE bill_payment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $bill_payment_id, $user_id);
        $stmt->execute();
    }
    redirect_with('deleted=1');
}

// Add bill payment (+ optional create biller)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $biller_id = (int)($_POST['biller_id'] ?? 0);
    $new_biller_name = trim((string)($_POST['new_biller_name'] ?? ''));
    $bill_type = trim((string)($_POST['bill_type'] ?? 'Other'));
    $wallet_id = (int)($_POST['wallet_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_date = trim((string)($_POST['payment_date'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'Paid'));

    if ($amount <= 0 || $payment_date === '') {
        redirect_with('error=1');
    }

    $allowedBillTypes = ['Electricity','Gas','Internet','Water','Other'];
    if (!in_array($bill_type, $allowedBillTypes, true)) {
        $bill_type = 'Other';
    }

    $allowedStatuses = ['Pending','Paid','Failed'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'Paid';
    }

    // Create biller if needed
    if ($biller_id <= 0) {
        if ($new_biller_name === '') {
            redirect_with('error=1');
        }

        $sel = $conn->prepare("SELECT biller_id FROM utility_billers WHERE biller_name = ? LIMIT 1");
        $sel->bind_param("s", $new_biller_name);
        $sel->execute();
        $selRes = $sel->get_result();
        if ($selRes && $selRes->num_rows > 0) {
            $biller_id = (int)$selRes->fetch_assoc()['biller_id'];
        } else {
            $ins = $conn->prepare("INSERT INTO utility_billers (biller_name, bill_type) VALUES (?, ?)");
            $ins->bind_param("ss", $new_biller_name, $bill_type);
            $ins->execute();
            $biller_id = (int)$conn->insert_id;
        }
    }

    $payment_time = $payment_date . ' 12:00:00';
    $wallet_id_or_null = $wallet_id > 0 ? $wallet_id : null;

    $stmt = $conn->prepare("INSERT INTO bill_payments (user_id, biller_id, wallet_id, amount, payment_time, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiidss", $user_id, $biller_id, $wallet_id_or_null, $amount, $payment_time, $status);
    if ($stmt->execute()) {
        redirect_with('success=1');
    }

    redirect_with('error=1');
}

// Wallets
$wallets = [];
$wStmt = $conn->prepare("SELECT wallet_id, wallet_name FROM wallets WHERE user_id = ? ORDER BY wallet_name");
$wStmt->bind_param("i", $user_id);
$wStmt->execute();
$wRes = $wStmt->get_result();
while ($r = $wRes->fetch_assoc()) {
    $wallets[] = $r;
}

// Billers
$billers = [];
$bRes = $conn->query("SELECT biller_id, biller_name, bill_type FROM utility_billers ORDER BY biller_name");
if ($bRes) {
    while ($r = $bRes->fetch_assoc()) {
        $billers[] = $r;
    }
}

// Payments list
$payments = [];
$pStmt = $conn->prepare("
    SELECT
        bp.bill_payment_id,
        ub.biller_name,
        ub.bill_type,
        w.wallet_name,
        bp.amount,
        bp.payment_time,
        bp.status
    FROM bill_payments bp
    JOIN utility_billers ub ON ub.biller_id = bp.biller_id
    LEFT JOIN wallets w ON w.wallet_id = bp.wallet_id
    WHERE bp.user_id = ?
    ORDER BY bp.payment_time DESC
    LIMIT 200
");
$pStmt->bind_param("i", $user_id);
$pStmt->execute();
$pRes = $pStmt->get_result();
while ($r = $pRes->fetch_assoc()) {
    $payments[] = $r;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bills - Budget Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        @keyframes pulse { 0%, 100% { opacity: 0.5; } 50% { opacity: 1; } }

        .app-container { display: flex; min-height: 100vh; }

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

        .logo-section { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; }
        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }
        .logo-text {
            font-size: 1.25rem;
            font-weight: 900;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }
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
        .nav-item:hover { background: rgba(168, 85, 247, 0.1); color: var(--primary); }
        .nav-item.active {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }

        .main-content { margin-left: 256px; flex: 1; min-height: 100vh; }
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
            gap: 1rem;
        }
        body.dark-mode .top-bar { background: rgba(15, 6, 40, 0.8); }

        .search-bar { flex: 1; max-width: 600px; }
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

        .top-bar-actions { display: flex; align-items: center; gap: 1rem; }

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
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(168, 85, 247, 0.5); }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
        }
        .btn-outline:hover { background: rgba(168, 85, 247, 0.1); border-color: var(--primary); }

        .content-area { padding: 2rem; max-width: 1200px; margin: 0 auto; }

        .page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 2rem; }
        .page-title { font-size: 2rem; font-weight: 900; }
        .page-subtitle { color: var(--text-secondary); margin-top: 0.5rem; }

        .bills-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem; }
        .bill-card {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 1.25rem;
            transition: all 0.3s ease;
            position: relative;
        }
        .bill-card:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 8px 24px rgba(168, 85, 247, 0.2); }

        .bill-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
        .bill-name { font-size: 1.125rem; font-weight: 800; }
        .bill-meta { color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem; }
        .bill-amount { font-size: 1.75rem; font-weight: 900; margin: 0.75rem 0; }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            border: 1px solid var(--border);
        }
        .badge.paid { background: rgba(16, 185, 129, 0.12); color: var(--success); }
        .badge.pending { background: rgba(245, 158, 11, 0.12); color: var(--warning); }
        .badge.failed { background: rgba(239, 68, 68, 0.12); color: var(--danger); }

        .bill-actions { display: flex; gap: 0.5rem; }
        .icon-btn {
            width: 36px; height: 36px;
            border-radius: 10px;
            border: 2px solid var(--border);
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .icon-btn:hover { border-color: var(--primary); background: rgba(168, 85, 247, 0.08); }
        .icon-btn.danger:hover { border-color: var(--danger); background: rgba(239, 68, 68, 0.08); }
        .icon-btn.success:hover { border-color: var(--success); background: rgba(16, 185, 129, 0.10); }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--card-bg);
            border-radius: 24px;
            width: 92%;
            max-width: 640px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
        }
        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body { padding: 1.5rem 2rem 2rem; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-weight: 700; margin-bottom: 0.5rem; }
        .form-input, .form-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            border: 2px dashed var(--border);
            border-radius: 24px;
            color: var(--text-secondary);
            background: rgba(168, 85, 247, 0.03);
        }

        /* Toasts (dashboard-style) */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 420px;
        }

        .toast {
            padding: 14px 18px;
            border-radius: 12px;
            background: var(--card-bg);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.25s ease-out;
            min-height: 48px;
            font-weight: 700;
        }

        .toast.error {
            border-left: 4px solid var(--danger);
            background: rgba(239, 68, 68, 0.08);
            color: var(--text-primary);
        }

        .toast.success {
            border-left: 4px solid var(--success);
            background: rgba(16, 185, 129, 0.10);
            color: var(--text-primary);
        }

        .toast.info {
            border-left: 4px solid var(--info);
            background: rgba(59, 130, 246, 0.10);
            color: var(--text-primary);
        }

        @keyframes slideIn {
            from {
                transform: translateX(420px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            to {
                transform: translateX(420px);
                opacity: 0;
            }
        }

        /* Confirm modal tweaks */
        .confirm-content {
            max-width: 520px;
        }
        .confirm-title {
            font-size: 1.25rem;
            font-weight: 900;
        }
        .confirm-message {
            margin-top: 0.75rem;
            color: var(--text-secondary);
            line-height: 1.5;
            font-weight: 600;
        }
        .confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        @media (max-width: 968px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
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
                <a href="dashboard.php" class="nav-item">🏠 Home</a>
                <a href="category.php" class="nav-item">📁 Categories</a>
                <a href="wallet.php" class="nav-item">👛 Wallets</a>
                <a href="budget.php" class="nav-item">🎯 Budgets</a>
                <a href="transaction.php" class="nav-item">📝 Transactions</a>
                <a href="analytics.php" class="nav-item">📊 Analytics</a>
                <a href="bill.php" class="nav-item active">📋 Bills</a>
                <a href="#" class="nav-item" id="nav-profile" onclick="navigateTo('profile'); return false;">
                    👤 Profile
                </a>
                <a href="login.php" class="nav-item" style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">🚪 Logout</a>
            </ul>
        </aside>

        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-bar">
                    <input type="text" placeholder="Search bills..." id="searchBills" oninput="searchBills()">
                </div>
                <div class="top-bar-actions">
                    <button class="btn btn-outline theme-toggle" onclick="toggleDarkMode()"><span id="themeIcon">🌙</span></button>
                    <button class="btn btn-primary" onclick="openModal('billModal')">➕ Add Bill</button>
                </div>
            </div>

            <div class="content-area">
                <div class="page-header">
                    <div>
                        <div class="page-title">Bills</div>
                        <div class="page-subtitle">Track utility payments and due dates</div>
                    </div>
                </div>

                <div id="billsGrid" class="bills-grid">
                    <?php if (count($payments) === 0): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <div style="font-size: 3rem; margin-bottom: 0.75rem;">📋</div>
                            <div style="font-weight: 800; font-size: 1.25rem; margin-bottom: 0.5rem;">No bills yet</div>
                            <div>Add your first bill payment to get started.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($payments as $p):
                            $statusClass = strtolower($p['status']);
                            if ($statusClass !== 'paid' && $statusClass !== 'pending' && $statusClass !== 'failed') {
                                $statusClass = 'paid';
                            }
                            $isPayable = ($p['status'] !== 'Paid');
                            $walletMissing = empty($p['wallet_name']);
                        ?>
                            <div class="bill-card" data-search="<?php echo htmlspecialchars(strtolower($p['biller_name'] . ' ' . $p['bill_type'] . ' ' . ($p['wallet_name'] ?? ''))); ?>">
                                <div class="bill-top">
                                    <div>
                                        <div class="bill-name"><?php echo htmlspecialchars($p['biller_name']); ?></div>
                                        <div class="bill-meta"><?php echo htmlspecialchars($p['bill_type']); ?> • <?php echo htmlspecialchars($p['wallet_name'] ?? 'No wallet'); ?></div>
                                    </div>
                                    <div class="bill-actions">
                                        <?php if ($isPayable): ?>
                                            <form method="POST" action="pay_bill.php" data-confirm="Pay this bill now?" style="display:flex; align-items:center; gap:0.5rem;">
                                                <input type="hidden" name="bill_payment_id" value="<?php echo (int)$p['bill_payment_id']; ?>">
                                                <?php if ($walletMissing): ?>
                                                    <select name="wallet_id" class="form-select" style="height: 36px; padding: 0.25rem 0.5rem; border-radius: 10px; font-weight: 700;">
                                                        <option value="0">Select wallet</option>
                                                        <?php foreach ($wallets as $w): ?>
                                                            <option value="<?php echo (int)$w['wallet_id']; ?>"><?php echo htmlspecialchars($w['wallet_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                                <button type="submit" class="icon-btn success" title="Pay Now">💳</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="bill.php" data-confirm="Delete this bill payment?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="bill_payment_id" value="<?php echo (int)$p['bill_payment_id']; ?>">
                                            <button type="submit" class="icon-btn danger" title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="bill-amount">৳<?php echo number_format((float)$p['amount'], 2); ?></div>

                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                                    <div class="bill-meta">Date: <strong><?php echo date('d M, Y', strtotime($p['payment_time'])); ?></strong></div>
                                    <div class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($p['status']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Bill Modal -->
    <div id="billModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 900;">Add Bill Payment</h3>
                <button onclick="closeModal('billModal')" class="icon-btn" style="border: none; background: rgba(239, 68, 68, 0.1);">✕</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="bill.php" id="billForm">
                    <input type="hidden" name="action" value="add">

                    <div class="form-group">
                        <label class="form-label">Biller</label>
                        <select class="form-select" name="biller_id" id="billerSelect" onchange="toggleNewBiller()">
                            <option value="0">➕ Add new biller...</option>
                            <?php foreach ($billers as $b): ?>
                                <option value="<?php echo (int)$b['biller_id']; ?>"><?php echo htmlspecialchars($b['biller_name']); ?> (<?php echo htmlspecialchars($b['bill_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="newBillerFields" style="display: none;">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">New biller name</label>
                                <input class="form-input" type="text" name="new_biller_name" placeholder="e.g., DESCO">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bill type</label>
                                <select class="form-select" name="bill_type">
                                    <option value="Electricity">Electricity</option>
                                    <option value="Gas">Gas</option>
                                    <option value="Internet">Internet</option>
                                    <option value="Water">Water</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input class="form-input" type="number" name="amount" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input class="form-input" type="date" name="payment_date" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Wallet (optional)</label>
                            <select class="form-select" name="wallet_id">
                                <option value="0">No wallet</option>
                                <?php foreach ($wallets as $w): ?>
                                    <option value="<?php echo (int)$w['wallet_id']; ?>"><?php echo htmlspecialchars($w['wallet_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="Paid">Paid</option>
                                <option value="Pending">Pending</option>
                                <option value="Failed">Failed</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Save Bill</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toasts -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Confirm Modal (replaces browser confirm/"localhost says") -->
    <div id="confirmModal" class="modal" aria-hidden="true">
        <div class="modal-content confirm-content" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
            <div class="modal-header">
                <div>
                    <div class="confirm-title" id="confirmTitle">Confirm</div>
                    <div class="confirm-message" id="confirmMessage"></div>
                </div>
                <button type="button" onclick="closeConfirm(false)" class="icon-btn" style="border: none; background: rgba(239, 68, 68, 0.1);">✕</button>
            </div>
            <div class="modal-body" style="padding-top: 1.25rem;">
                <div class="confirm-actions">
                    <button type="button" class="btn btn-outline" onclick="closeConfirm(false)">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmOkBtn" onclick="closeConfirm(true)">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.25s ease-out';
                setTimeout(() => toast.remove(), 250);
            }, 3500);
        }

        let __confirmResolve = null;

        function showConfirm(message, opts = {}) {
            const modal = document.getElementById('confirmModal');
            const titleEl = document.getElementById('confirmTitle');
            const msgEl = document.getElementById('confirmMessage');
            const okBtn = document.getElementById('confirmOkBtn');
            if (!modal || !titleEl || !msgEl || !okBtn) {
                // Fallback if modal not available
                return Promise.resolve(window.confirm(message));
            }

            titleEl.textContent = opts.title || 'Confirm';
            msgEl.textContent = message || 'Are you sure?';
            okBtn.textContent = opts.okText || 'OK';

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');

            return new Promise(resolve => {
                __confirmResolve = resolve;
                // Focus OK for quick keyboard confirm
                setTimeout(() => okBtn.focus(), 0);
            });
        }

        function closeConfirm(result) {
            const modal = document.getElementById('confirmModal');
            if (modal) {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
            }
            if (typeof __confirmResolve === 'function') {
                const resolve = __confirmResolve;
                __confirmResolve = null;
                resolve(!!result);
            }
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
            if (modalId === 'billModal') {
                // Set date default
                const dateInput = document.querySelector('input[name="payment_date"]');
                if (dateInput && !dateInput.value) {
                    dateInput.value = new Date().toISOString().slice(0, 10);
                }
                toggleNewBiller();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.getElementById('themeIcon').textContent = '☀️';
            }
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('themeIcon');
            icon.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        function toggleNewBiller() {
            const select = document.getElementById('billerSelect');
            const fields = document.getElementById('newBillerFields');
            if (!select || !fields) return;
            fields.style.display = select.value === '0' ? 'block' : 'none';
        }

        function searchBills() {
            const term = (document.getElementById('searchBills')?.value || '').toLowerCase();
            const cards = document.querySelectorAll('#billsGrid .bill-card');
            cards.forEach(card => {
                const s = (card.getAttribute('data-search') || '');
                card.style.display = !term || s.includes(term) ? '' : 'none';
            });
        }

        // init
        loadTheme();

        // Close confirm on overlay click or Escape
        document.getElementById('confirmModal')?.addEventListener('click', (e) => {
            if (e.target && e.target.id === 'confirmModal') {
                closeConfirm(false);
            }
        });
        document.addEventListener('keydown', (e) => {
            const modal = document.getElementById('confirmModal');
            if (!modal || !modal.classList.contains('show')) return;
            if (e.key === 'Escape') {
                closeConfirm(false);
            }
        });

        // Replace native confirm() on forms with dashboard-style confirm modal
        document.addEventListener('submit', async (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            const msg = form.dataset.confirm;
            if (!msg) return;
            e.preventDefault();
            const ok = await showConfirm(msg, { title: 'Confirm' });
            if (ok) form.submit();
        }, true);

        // Auto-open modal on errors/success
        const params = new URLSearchParams(window.location.search);
        if (params.has('error')) {
            openModal('billModal');
            showToast('Please check the form fields and try again.', 'error');
        }

        if (params.has('paid')) {
            showToast('Bill paid successfully.', 'success');
        } else if (params.has('insufficient')) {
            showToast('Insufficient wallet balance for this payment.', 'error');
        } else if (params.has('wallet_required')) {
            showToast('Please select a wallet to pay from.', 'error');
        } else if (params.has('deleted')) {
            showToast('Bill deleted.', 'info');
        } else if (params.has('success')) {
            showToast('Bill saved.', 'success');
        }

        // Prevent repeating toasts/modals on refresh
        if (window.location.search) {
            const keys = ['error','paid','insufficient','wallet_required','deleted','success'];
            const hasAny = keys.some(k => params.has(k));
            if (hasAny) {
                history.replaceState({}, document.title, window.location.pathname);
            }
        }
    </script>
</body>
</html>