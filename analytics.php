<?php
session_start();
require "db.php";
require_once "currency_util.php";

if (!isset($_SESSION["logged"]) || $_SESSION["logged"] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Get user info
$sql = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$fullName = ($user ? $user['first_name'] . " " . $user['last_name'] : "User");

// Get all transactions grouped by category
$transactions_sql = "
    SELECT 
        c.category_id,
        c.category_name,
        c.category_type,
        t.amount,
        t.currency_code,
        t.transaction_time
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_time DESC
";

$stmt = $conn->prepare($transactions_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Process transactions and group by category
$categories_data = [];
$total_income = 0;
$total_expense = 0;

while($row = $result->fetch_assoc()) {
    $category_name = $row['category_name'];
    $category_type = $row['category_type'];
    $amount = (float)$row['amount'];
    $currency = $row['currency_code'] ?? 'BDT';
    
    // Convert to BDT
    $amount_bdt = convert_amount($amount, $currency, 'BDT');
    
    // Parse icon from category name
    $icon = '📁';
    $clean_name = $category_name;
    if (preg_match('/^(\X)\s+(.+)$/u', $category_name, $m)) {
        $icon = $m[1];
        $clean_name = $m[2];
    }
    
    if (!isset($categories_data[$clean_name])) {
        $categories_data[$clean_name] = [
            'icon' => $icon,
            'type' => strtolower($category_type),
            'income' => 0,
            'expense' => 0,
            'transactions' => 0
        ];
    }
    
    if (strtolower($category_type) === 'income') {
        $categories_data[$clean_name]['income'] += $amount_bdt;
        $total_income += $amount_bdt;
    } else {
        $categories_data[$clean_name]['expense'] += $amount_bdt;
        $total_expense += $amount_bdt;
    }
    
    $categories_data[$clean_name]['transactions']++;
}

// Get summary for current month
$current_month = date('Y-m-01');
$month_sql = "
    SELECT 
        c.category_type,
        SUM(t.amount) as total
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ? AND DATE(t.transaction_time) >= ?
    GROUP BY c.category_type
";

$stmt = $conn->prepare($month_sql);
$stmt->bind_param("is", $user_id, $current_month);
$stmt->execute();
$month_result = $stmt->get_result();

$month_income = 0;
$month_expense = 0;
while($row = $month_result->fetch_assoc()) {
    if (strtolower($row['category_type']) === 'income') {
        $month_income += (float)$row['total'];
    } else {
        $month_expense += (float)$row['total'];
    }
}

// Convert to BDT
$month_income_bdt = convert_amount($month_income, $DEFAULT_CURRENCY, 'BDT');
$month_expense_bdt = convert_amount($month_expense, $DEFAULT_CURRENCY, 'BDT');

// Prepare data for charts
$chart_categories = [];
$chart_income = [];
$chart_expense = [];

foreach($categories_data as $name => $data) {
    $chart_categories[] = $name;
    $chart_income[] = $data['income'];
    $chart_expense[] = $data['expense'];
}

$chart_categories_json = json_encode($chart_categories);
$chart_income_json = json_encode($chart_income);
$chart_expense_json = json_encode($chart_expense);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Budget Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="currency.js"></script>
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

        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: rgba(168, 85, 247, 0.1);
        }

        .content-wrapper {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Analytics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(168, 85, 247, 0.15);
            transform: translateY(-4px);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            color: var(--success);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        /* Charts */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            height: auto;
            min-height: 450px;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .chart-canvas {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }

        /* Custom Legend Styling */
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: var(--card-bg);
            border: 2px solid;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .legend-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .legend-item.income {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        body.dark-mode .legend-item.income {
            color: #60a5fa;
            border-color: #60a5fa;
        }

        .legend-item.expense {
            border-color: #a855f7;
            color: #a855f7;
        }

        body.dark-mode .legend-item.expense {
            color: #c084fc;
            border-color: #c084fc;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .legend-item.income .legend-dot {
            background: #3b82f6;
        }

        body.dark-mode .legend-item.income .legend-dot {
            background: #60a5fa;
        }

        .legend-item.expense .legend-dot {
            background: #a855f7;
        }

        body.dark-mode .legend-item.expense .legend-dot {
            background: #c084fc;
        }

        /* Categories Table */
        .categories-section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .categories-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .category-item:hover {
            background: rgba(168, 85, 247, 0.05);
            border-color: var(--primary);
        }

        .category-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .category-icon {
            font-size: 1.75rem;
            min-width: 40px;
            text-align: center;
        }

        .category-details {
            flex: 1;
        }

        .category-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .category-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .category-amounts {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .amount-box {
            text-align: right;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(99, 102, 241, 0.08) 100%);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1px solid rgba(99, 102, 241, 0.15);
            min-width: 120px;
            transition: all 0.3s ease;
        }

        body.dark-mode .amount-box {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(124, 58, 237, 0.12) 100%);
            border: 1px solid rgba(124, 58, 237, 0.25);
        }

        .amount-box:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(124, 58, 237, 0.12) 100%);
            border-color: rgba(124, 58, 237, 0.4);
        }

        .amount-label {
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .amount-value {
            font-size: 1.125rem;
            font-weight: 800;
        }

        .income {
            color: #3b82f6;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .expense {
            color: #a855f7;
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .category-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .category-amounts {
                width: 100%;
                margin-top: 1rem;
            }
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
                <a href="dashboard.php" class="nav-item">
                    🏠 Home
                </a>
                <a href="category.php" class="nav-item">
                    📁 Categories
                </a>
                <a href="wallet.php" class="nav-item">
                    👛 Wallets
                </a>
                <a href="budget.php" class="nav-item">
                    🎯 Budgets
                </a>
                <a href="transaction.php" class="nav-item">
                    📝 Transactions
                </a>
                <a href="bill.php" class="nav-item" style="text-decoration: none;">
                    📋 Bills
                </a>
                <a href="analytics.php" class="nav-item active">
                    📊 Analytics
                </a>
                <a href="#" class="nav-item" id="nav-profile" onclick="navigateTo('profile'); return false;">
                    👤 Profile
                </a>
                <a href="logout.php" class="nav-item" style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem; color: #ef4444;">
                    🚪 Logout
                </a>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h3>Analytics Dashboard</h3>
                <div class="top-bar-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                        <span id="theme-icon">🌙</span>
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>📊 Analytics & Insights</h1>
                    <p>Your spending patterns and income breakdown at a glance</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-label">Total Income</div>
                        <div class="stat-value">৳<?php echo number_format($total_income, 0); ?></div>
                        <div class="stat-change">This Month: ৳<?php echo number_format($month_income_bdt, 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💸</div>
                        <div class="stat-label">Total Expense</div>
                        <div class="stat-value" style="color: #ef4444;">৳<?php echo number_format($total_expense, 0); ?></div>
                        <div class="stat-change">This Month: ৳<?php echo number_format($month_expense_bdt, 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-label">Net Balance</div>
                        <div class="stat-value" style="color: <?php echo ($total_income - $total_expense) >= 0 ? '#10b981' : '#ef4444'; ?>">
                            ৳<?php echo number_format($total_income - $total_expense, 0); ?>
                        </div>
                        <div class="stat-change"><?php echo ($total_income - $total_expense) >= 0 ? '✅ Positive' : '⚠️ Negative'; ?></div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="charts-grid">
                    <div class="chart-container">
                        <div class="chart-title">Income vs Expense</div>
                        <div class="chart-canvas">
                            <canvas id="incomeExpenseChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item income">
                                <span class="legend-dot"></span>
                                <span>Income</span>
                            </div>
                            <div class="legend-item expense">
                                <span class="legend-dot"></span>
                                <span>Expense</span>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <div class="chart-title">Category Distribution</div>
                        <div class="chart-canvas">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item income">
                                <span class="legend-dot"></span>
                                <span>Income</span>
                            </div>
                            <div class="legend-item expense">
                                <span class="legend-dot"></span>
                                <span>Expense</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Breakdown -->
                <div class="categories-section">
                    <div class="categories-title">📋 Category Breakdown</div>
                    <?php if (count($categories_data) > 0): ?>
                        <?php foreach($categories_data as $name => $data): ?>
                            <div class="category-item">
                                <div class="category-info">
                                    <div class="category-icon"><?php echo $data['icon']; ?></div>
                                    <div class="category-details">
                                        <div class="category-name"><?php echo htmlspecialchars($name); ?></div>
                                        <div class="category-stats">
                                            <span>📊 <?php echo $data['transactions']; ?> transactions</span>
                                            <span style="color: var(--text-secondary);">•</span>
                                            <span>Type: <strong><?php echo ucfirst($data['type']); ?></strong></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="category-amounts">
                                    <?php if ($data['income'] > 0): ?>
                                        <div class="amount-box">
                                            <div class="amount-label">Income</div>
                                            <div class="amount-value income">+৳<?php echo number_format($data['income'], 0); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($data['expense'] > 0): ?>
                                        <div class="amount-box">
                                            <div class="amount-label">Expense</div>
                                            <div class="amount-value expense">-৳<?php echo number_format($data['expense'], 0); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                            No transactions yet. Start adding transactions to see analytics!
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize theme
        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.classList.toggle('dark-mode', savedTheme === 'dark');
            updateThemeIcon();
        }

        function toggleTheme() {
            const isDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcon();
            // Redraw charts with new theme
            setTimeout(() => {
                location.reload();
            }, 300);
        }

        function updateThemeIcon() {
            const icon = document.getElementById('theme-icon');
            icon.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
        }

        // Chart data
        const chartCategories = <?php echo $chart_categories_json; ?>;
        const chartIncome = <?php echo $chart_income_json; ?>;
        const chartExpense = <?php echo $chart_expense_json; ?>;

        // Chart colors
        const isDarkMode = document.body.classList.contains('dark-mode');
        const primaryColor = '#a855f7';
        const incomeColor = '#3b82f6';  // Professional Blue
        const expenseColor = '#a855f7'; // Deep Purple matching theme
        const textColor = isDarkMode ? '#e2e8f0' : '#1f2937';
        const gridColor = isDarkMode ? 'rgba(139, 92, 246, 0.1)' : 'rgba(0, 0, 0, 0.05)';

        // Income vs Expense Chart
        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        const incomeExpenseChart = new Chart(incomeExpenseCtx, {
            type: 'doughnut',
            data: {
                labels: ['Income', 'Expense'],
                datasets: [{
                    data: [<?php echo $total_income; ?>, <?php echo $total_expense; ?>],
                    backgroundColor: [incomeColor, expenseColor],
                    borderColor: [incomeColor, expenseColor],
                    borderWidth: 3,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: textColor,
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true
                    }
                }
            }
        });

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: chartCategories,
                datasets: [
                    {
                        label: 'Income',
                        data: chartIncome,
                        backgroundColor: incomeColor,
                        borderRadius: 8,
                        borderSkipped: false,
                        hoverBackgroundColor: '#2563eb'
                    },
                    {
                        label: 'Expense',
                        data: chartExpense,
                        backgroundColor: expenseColor,
                        borderRadius: 8,
                        borderSkipped: false,
                        hoverBackgroundColor: '#9333ea'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: textColor,
                        borderWidth: 1,
                        padding: 12
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: textColor,
                            font: {
                                family: "'Inter', sans-serif",
                                size: 12
                            }
                        },
                        grid: {
                            color: gridColor,
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor,
                            font: {
                                family: "'Inter', sans-serif",
                                size: 12
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        initTheme();
    </script>
</body>
</html>
