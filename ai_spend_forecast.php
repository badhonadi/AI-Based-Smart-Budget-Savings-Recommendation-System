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

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-d', strtotime('last day of this month'));
$today = date('Y-m-d');

$daysInMonth = (int)date('t');
$dayOfMonth = (int)date('j');
$elapsedDays = max(1, $dayOfMonth);

function sum_for_range(mysqli $conn, int $user_id, string $type, string $start, string $end): float {
    $stmt = $conn->prepare(
        "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND transaction_type = ? AND DATE(transaction_time) >= ? AND DATE(transaction_time) <= ?"
    );
    $stmt->bind_param('isss', $user_id, $type, $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        return (float)($res->fetch_assoc()['total'] ?? 0);
    }
    return 0.0;
}

$incomeToDate = sum_for_range($conn, $user_id, 'Income', $monthStart, $today);
$expenseToDate = sum_for_range($conn, $user_id, 'Expense', $monthStart, $today);

// Linear projection
$expenseLinear = ($expenseToDate / $elapsedDays) * $daysInMonth;
$incomeLinear = ($incomeToDate / $elapsedDays) * $daysInMonth;

// Trend-based projection using last month pattern
$prevStart = date('Y-m-01', strtotime('first day of last month'));
$prevEnd = date('Y-m-d', strtotime('last day of last month'));
$prevExpenseTotal = sum_for_range($conn, $user_id, 'Expense', $prevStart, $prevEnd);
$prevIncomeTotal = sum_for_range($conn, $user_id, 'Income', $prevStart, $prevEnd);

// Previous month same-day window
$prevSameEnd = date('Y-m-d', strtotime($prevStart . ' +' . ($elapsedDays - 1) . ' days'));
$prevExpenseSame = sum_for_range($conn, $user_id, 'Expense', $prevStart, $prevSameEnd);
$prevIncomeSame = sum_for_range($conn, $user_id, 'Income', $prevStart, $prevSameEnd);

$expenseFactor = ($prevExpenseSame > 0) ? ($expenseToDate / $prevExpenseSame) : 1.0;
$incomeFactor = ($prevIncomeSame > 0) ? ($incomeToDate / $prevIncomeSame) : 1.0;

$expenseTrend = $prevExpenseTotal * $expenseFactor;
$incomeTrend = $prevIncomeTotal * $incomeFactor;

// Blend projections
$expenseForecast = ($expenseLinear * 0.6) + ($expenseTrend * 0.4);
$incomeForecast = ($incomeLinear * 0.6) + ($incomeTrend * 0.4);

// Top categories to date
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
$catStmt->bind_param('iss', $user_id, $monthStart, $today);
$catStmt->execute();
$catRes = $catStmt->get_result();
$topCats = [];
while ($r = $catRes->fetch_assoc()) {
    $topCats[] = [
        'name' => (string)$r['category_name'],
        'total_bdt' => (float)($r['total'] ?? 0)
    ];
}

// Simple insights
$insights = [];
$netForecast = $incomeForecast - $expenseForecast;

if ($incomeForecast > 0 && $netForecast < 0) {
    $insights[] = [
        'type' => 'warning',
        'title' => 'Forecast: Overspending Risk',
        'description' => 'At this pace, expenses may exceed income by month-end.',
        'action' => 'Try cutting about ' . number_format(abs($netForecast), 0) . ' BDT across top categories to stay positive.'
    ];
} elseif ($incomeForecast > 0) {
    $insights[] = [
        'type' => 'success',
        'title' => 'Forecast: Positive Month',
        'description' => 'You are on track to end the month with surplus.',
        'action' => 'Consider reserving 20–30% of the forecast surplus for savings/investment.'
    ];
} else {
    $insights[] = [
        'type' => 'info',
        'title' => 'Forecast: Limited Income Data',
        'description' => 'Not enough income transactions this month to forecast reliably.',
        'action' => 'Add income entries (salary/business) to improve forecasting.'
    ];
}

$payload = [
    'month_start' => $monthStart,
    'month_end' => $monthEnd,
    'today' => $today,
    'days_in_month' => $daysInMonth,
    'elapsed_days' => $elapsedDays,
    'income_to_date_bdt' => round($incomeToDate, 2),
    'expense_to_date_bdt' => round($expenseToDate, 2),
    'forecast_income_bdt' => round($incomeForecast, 2),
    'forecast_expense_bdt' => round($expenseForecast, 2),
    'forecast_net_bdt' => round($netForecast, 2),
    'top_categories_to_date' => $topCats,
    'insights' => $insights
];

// Optional Gemini: add 2-3 richer insights
$useGemini = defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI;
$apiKey = $useGemini ? get_gemini_api_key() : '';
if ($useGemini && $apiKey !== '') {
    require_once __DIR__ . '/gemini_ai.php';
    $assistant = new GeminiAIAssistant($apiKey);
    if ($assistant->isConfigured()) {
        $prompt = "You are a finance forecasting assistant.\n" .
            "Given the JSON data, produce STRICT JSON ONLY with shape {\"insights\":[{\"type\":\"info\"|\"warning\"|\"success\",\"title\":string,\"description\":string,\"action\":string}]}\n" .
            "Rules: 2-4 insights max, actionable, use BDT amounts when possible.\n\n" .
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $out = $assistant->generateFromPrompt($prompt);
        $json = is_string($out) ? json_decode(trim($out), true) : null;
        if (is_array($json) && isset($json['insights']) && is_array($json['insights']) && count($json['insights']) > 0) {
            $payload['insights'] = array_slice($json['insights'], 0, 4);
        }
    }
}

echo json_encode(['success' => true] + $payload);
