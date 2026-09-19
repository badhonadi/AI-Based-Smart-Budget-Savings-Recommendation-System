<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/currency_util.php';
require_once __DIR__ . '/ai_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Simple session cache to avoid repeated AI calls
$cacheKey = 'ai_dashboard_insights_cache';
$cacheTtl = defined('SUGGESTION_CACHE_TIME') ? (int)SUGGESTION_CACHE_TIME : 300;
if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
    $c = $_SESSION[$cacheKey];
    if (isset($c['ts'], $c['payload']) && (time() - (int)$c['ts'] < $cacheTtl)) {
        echo json_encode(['success' => true] + $c['payload']);
        exit;
    }
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-d', strtotime('last day of this month'));

// Income/expense (BDT)
$income = 0.0;
$expense = 0.0;

$sumStmt = $conn->prepare(
    "SELECT transaction_type, SUM(amount) as total
     FROM transactions
     WHERE user_id = ? AND DATE(transaction_time) >= ? AND DATE(transaction_time) <= ?
     GROUP BY transaction_type"
);
$sumStmt->bind_param('iss', $user_id, $monthStart, $monthEnd);
$sumStmt->execute();
$sumRes = $sumStmt->get_result();
while ($r = $sumRes->fetch_assoc()) {
    $t = strtolower((string)$r['transaction_type']);
    $total = (float)($r['total'] ?? 0);
    if ($t === 'income') $income = $total;
    if ($t === 'expense') $expense = $total;
}

$remaining = $income - $expense;
$savingsRate = $income > 0 ? (($income - $expense) / $income) * 100 : 0;

// Previous month for trend comparison (BDT)
$prevStart = date('Y-m-01', strtotime('first day of last month'));
$prevEnd = date('Y-m-d', strtotime('last day of last month'));
$prevIncome = 0.0;
$prevExpense = 0.0;

$prevStmt = $conn->prepare(
    "SELECT transaction_type, SUM(amount) as total
     FROM transactions
     WHERE user_id = ? AND DATE(transaction_time) >= ? AND DATE(transaction_time) <= ?
     GROUP BY transaction_type"
);
$prevStmt->bind_param('iss', $user_id, $prevStart, $prevEnd);
$prevStmt->execute();
$prevRes = $prevStmt->get_result();
while ($r = $prevRes->fetch_assoc()) {
    $t = strtolower((string)$r['transaction_type']);
    $total = (float)($r['total'] ?? 0);
    if ($t === 'income') $prevIncome = $total;
    if ($t === 'expense') $prevExpense = $total;
}

$expenseDeltaPct = $prevExpense > 0 ? (($expense - $prevExpense) / $prevExpense) * 100 : 0;

// Top categories (BDT)
$catStmt = $conn->prepare(
    "SELECT c.category_name, SUM(t.amount) as total
     FROM transactions t
     JOIN categories c ON c.category_id = t.category_id
     WHERE t.user_id = ? AND t.transaction_type = 'Expense'
       AND DATE(t.transaction_time) >= ? AND DATE(t.transaction_time) <= ?
     GROUP BY c.category_id
     ORDER BY total DESC
     LIMIT 5"
);
$catStmt->bind_param('iss', $user_id, $monthStart, $monthEnd);
$catStmt->execute();
$catRes = $catStmt->get_result();
$topCats = [];
while ($r = $catRes->fetch_assoc()) {
    $topCats[] = [
        'name' => (string)$r['category_name'],
        'total_bdt' => (float)($r['total'] ?? 0),
    ];
}

// Wider category breakdown (BDT)
$catAllStmt = $conn->prepare(
    "SELECT c.category_name, SUM(t.amount) as total
     FROM transactions t
     JOIN categories c ON c.category_id = t.category_id
     WHERE t.user_id = ? AND t.transaction_type = 'Expense'
       AND DATE(t.transaction_time) >= ? AND DATE(t.transaction_time) <= ?
     GROUP BY c.category_id
     ORDER BY total DESC
     LIMIT 12"
);
$catAllStmt->bind_param('iss', $user_id, $monthStart, $monthEnd);
$catAllStmt->execute();
$catAllRes = $catAllStmt->get_result();
$categoryBreakdown = [];
while ($r = $catAllRes->fetch_assoc()) {
    $total = (float)($r['total'] ?? 0);
    $pct = $expense > 0 ? ($total / $expense) * 100 : 0;
    $categoryBreakdown[] = [
        'name' => (string)$r['category_name'],
        'total_bdt' => $total,
        'percent_of_expense' => round($pct, 1),
    ];
}

// Upcoming bills (next 30 days) - uses bill_payments as due list
$upcomingBills = [];
$billSince = date('Y-m-d');
$billUntil = date('Y-m-d', strtotime('+30 days'));
$billStmt = $conn->prepare(
    "SELECT ub.biller_name, bp.amount, DATE(bp.payment_time) as due_date, bp.status
     FROM bill_payments bp
     JOIN utility_billers ub ON ub.biller_id = bp.biller_id
     WHERE bp.user_id = ? AND DATE(bp.payment_time) >= ? AND DATE(bp.payment_time) <= ?
     ORDER BY bp.payment_time ASC
     LIMIT 10"
);
if ($billStmt) {
    $billStmt->bind_param('iss', $user_id, $billSince, $billUntil);
    $billStmt->execute();
    $billRes = $billStmt->get_result();
    while ($r = $billRes->fetch_assoc()) {
        $upcomingBills[] = [
            'name' => (string)$r['biller_name'],
            'amount_bdt' => (float)($r['amount'] ?? 0),
            'due_date' => (string)$r['due_date'],
            'status' => (string)($r['status'] ?? ''),
        ];
    }
}

// Heuristic insights (fallback)
$insights = [];

// 1) Health check: overspending / savings
if ($income > 0 && $remaining < 0) {
    $insights[] = [
        'type' => 'warning',
        'title' => 'You’re Spending More Than You Earn',
        'description' => 'This month expenses are higher than income. This can create debt quickly if it continues.',
        'action' => 'Pause non-essential purchases for 7 days and set a strict cap for your top category.'
    ];
}

if ($savingsRate < 10) {
    $insights[] = [
        'type' => 'warning',
        'title' => 'Low Savings Rate',
        'description' => 'Your savings rate is low this month. Aim for at least 15–20% if possible.',
        'action' => 'Cut 1–2 discretionary categories by a fixed weekly cap (Food/Entertainment/Shopping).'
    ];
} elseif ($savingsRate >= 20) {
    $insights[] = [
        'type' => 'success',
        'title' => 'Strong Savings Rate',
        'description' => 'You are saving a healthy portion of your income this month.',
        'action' => 'Keep an emergency fund first, then invest a portion of the surplus.'
    ];
} else {
    $insights[] = [
        'type' => 'info',
        'title' => 'Good Progress',
        'description' => 'Your savings rate is decent, but there is room to improve.',
        'action' => 'Try increasing savings by 2–5% next month.'
    ];
}

// 2) Trend warning: expenses rising
if ($prevExpense > 0 && $expenseDeltaPct >= 20) {
    $insights[] = [
        'type' => 'warning',
        'title' => 'Expenses Rising Fast',
        'description' => 'Your expenses increased significantly vs last month.',
        'action' => 'Review the last 10 expense transactions and stop the “leaks” (subscriptions, small daily spends).'
    ];
}

if (!empty($topCats)) {
    $top = $topCats[0];
    $topPctOfExpense = $expense > 0 ? ($top['total_bdt'] / $expense) * 100 : 0;

    $insights[] = [
        'type' => 'warning',
        'title' => 'Top Spending Category',
        'description' => 'Your highest expense category is ' . $top['name'] . ' this month (' . number_format($topPctOfExpense, 1) . '% of expenses).',
        'action' => 'Set a weekly limit for ' . $top['name'] . ' and review progress every 3 days.'
    ];

    // Spend-less suggestion: reduce top category to 25% of expenses
    if ($expense > 0) {
        $targetPct = 25.0;
        $target = ($expense * $targetPct) / 100.0;
        $reduce = $top['total_bdt'] - $target;
        if ($reduce > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Where To Spend Less (Fast Win)',
                'description' => 'Your spending in ' . $top['name'] . ' is high. Reducing it can quickly improve savings.',
                'action' => 'Try reducing ' . $top['name'] . ' by about ' . number_format($reduce, 0) . ' BDT this month.'
            ];
        }
    }
}

$insights[] = [
    'type' => 'info',
    'title' => 'EMI / Bills Plan',
    'description' => 'Keep upcoming bills/EMI separated so they don’t eat into daily spending.',
    'action' => 'Create a dedicated wallet named “EMI/Bills” and move money there right after income.'
];

// 3) Bills pressure
$billTotal = 0.0;
foreach ($upcomingBills as $b) {
    $billTotal += (float)($b['amount_bdt'] ?? 0);
}
if ($billTotal > 0 && $income > 0) {
    $billPct = ($billTotal / $income) * 100;
    if ($billPct >= 15) {
        $insights[] = [
            'type' => 'warning',
            'title' => 'Bills Are Heavy This Month',
            'description' => 'Upcoming bills/EMI are taking a big chunk of income.',
            'action' => 'Reserve ' . number_format($billTotal, 0) . ' BDT now so you don’t borrow later.'
        ];
    }
}

// 4) Investment suggestion (general)
if ($income > 0 && $remaining > 0) {
    $suggestInvest = max(0.0, $remaining * 0.3);
    $insights[] = [
        'type' => 'success',
        'title' => 'Where To Invest (General Ideas)',
        'description' => 'If your emergency fund is covered, you can start investing small and consistent. (Not financial advice.)',
        'action' => 'Consider investing about ' . number_format($suggestInvest, 0) . ' BDT this month via low-risk options (FD, govt bonds) or diversified funds.'
    ];
}

$payload = [
    'summary' => [
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
        'prev_month_start' => $prevStart,
        'prev_month_end' => $prevEnd,
        'income_bdt' => $income,
        'expense_bdt' => $expense,
        'prev_income_bdt' => $prevIncome,
        'prev_expense_bdt' => $prevExpense,
        'expense_delta_pct' => $expenseDeltaPct,
        'remaining_bdt' => $remaining,
        'savings_rate' => $savingsRate,
        'top_categories' => $topCats,
        'category_breakdown' => $categoryBreakdown,
        'upcoming_bills' => $upcomingBills,
        'upcoming_bills_total_bdt' => $billTotal,
    ],
    'insights' => $insights,
    'gemini_used' => false,
];

// Optional Gemini enrichment: keep same object shape (type/title/description/action)
$useGemini = defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI;
$apiKey = $useGemini ? get_gemini_api_key() : '';

if ($useGemini && $apiKey !== '') {
    require_once __DIR__ . '/gemini_ai.php';
    $assistant = new GeminiAIAssistant($apiKey);
    if ($assistant->isConfigured()) {
        $prompt = "You are a personal finance coach. Based on the user's monthly summary, generate 6-9 actionable insights.\n" .
            "Return STRICT JSON ONLY with shape: {\"insights\":[{\"type\":\"warning\"|\"info\"|\"success\",\"title\":string,\"description\":string,\"action\":string}]}\n\n" .
            "Guidelines:\n" .
            "- MUST include 2 concrete 'Spend less' insights naming categories from category_breakdown, with an estimated BDT amount to reduce.\n" .
            "- MUST include a savings/goal insight (how much to save this month).\n" .
            "- Include 1 investing tip appropriate for Bangladesh (general education, not financial advice).\n" .
            "- Include 1 EMI/bills plan tip (create a bill wallet, reserve money).\n" .
            "- Keep it short and practical.\n" .
            "- Be concise.\n\n" .
            "Monthly Summary (amounts in BDT):\n" . json_encode($payload['summary'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $text = (string)$assistant->generateFromPrompt($prompt);

            $maybe = json_decode(trim($text), true);
            if (is_array($maybe) && isset($maybe['insights']) && is_array($maybe['insights'])) {
                $clean = [];
                foreach ($maybe['insights'] as $i) {
                    $type = (string)($i['type'] ?? 'info');
                    if (!in_array($type, ['warning','info','success'], true)) $type = 'info';
                    $title = trim((string)($i['title'] ?? ''));
                    $desc = trim((string)($i['description'] ?? ''));
                    $action = trim((string)($i['action'] ?? ''));
                    if ($title === '' || $desc === '' || $action === '') continue;
                    $clean[] = ['type' => $type, 'title' => $title, 'description' => $desc, 'action' => $action];
                }
                if (!empty($clean)) {
                    $payload['insights'] = $clean;
                    $payload['gemini_used'] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('Gemini dashboard insights failed: ' . $e->getMessage());
        }
    }
}

$_SESSION[$cacheKey] = ['ts' => time(), 'payload' => $payload];

echo json_encode(['success' => true] + $payload);
