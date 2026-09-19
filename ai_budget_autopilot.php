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

// Target month: default next month (YYYY-MM)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$targetMonth = is_array($data) && isset($data['month']) ? trim((string)$data['month']) : '';

if ($targetMonth === '') {
    $targetMonth = date('Y-m', strtotime('first day of next month'));
}

if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
    echo json_encode(['success' => false, 'error' => 'Invalid month format. Use YYYY-MM']);
    exit;
}

$monthStart = $targetMonth . '-01';
$monthEnd = date('Y-m-d', strtotime('last day of ' . $monthStart));

// Use last 3 full months as history
$histStart = date('Y-m-01', strtotime('-3 months', strtotime($monthStart)));
$histEnd = date('Y-m-d', strtotime('last day of last month', strtotime($monthStart)));

// Income history (BDT)
$incomeStmt = $conn->prepare(
    "SELECT DATE_FORMAT(transaction_time, '%Y-%m') as ym, SUM(amount) as total
     FROM transactions
     WHERE user_id = ? AND transaction_type = 'Income'
       AND DATE(transaction_time) >= ? AND DATE(transaction_time) <= ?
     GROUP BY ym"
);
$incomeStmt->bind_param('iss', $user_id, $histStart, $histEnd);
$incomeStmt->execute();
$incomeRes = $incomeStmt->get_result();
$incomeMonths = [];
while ($r = $incomeRes->fetch_assoc()) {
    $incomeMonths[(string)$r['ym']] = (float)($r['total'] ?? 0);
}

$incomeAvg = 0.0;
if (count($incomeMonths) > 0) {
    $incomeAvg = array_sum($incomeMonths) / max(1, count($incomeMonths));
}

// Expense by category history (BDT)
$expStmt = $conn->prepare(
    "SELECT t.category_id, c.category_name, SUM(t.amount) as total
     FROM transactions t
     JOIN categories c ON c.category_id = t.category_id
     WHERE t.user_id = ? AND t.transaction_type = 'Expense' AND t.category_id IS NOT NULL
       AND DATE(t.transaction_time) >= ? AND DATE(t.transaction_time) <= ?
     GROUP BY t.category_id
     ORDER BY total DESC"
);
$expStmt->bind_param('iss', $user_id, $histStart, $histEnd);
$expStmt->execute();
$expRes = $expStmt->get_result();

$categoryTotals = [];
$totalExpenseHist = 0.0;
while ($r = $expRes->fetch_assoc()) {
    $cid = (int)$r['category_id'];
    $total = (float)($r['total'] ?? 0);
    $totalExpenseHist += $total;
    $categoryTotals[] = [
        'category_id' => $cid,
        'category_name' => (string)$r['category_name'],
        'total_3m_bdt' => $total,
        'avg_month_bdt' => $total / 3.0,
    ];
}

// Next-month bills reserve (best-effort from bill_payments)
$billsReserve = 0.0;
$billStmt = $conn->prepare(
    "SELECT SUM(amount) as total
     FROM bill_payments
     WHERE user_id = ? AND DATE(payment_time) >= ? AND DATE(payment_time) <= ?
       AND status IN ('Pending','Paid')"
);
if ($billStmt) {
    $billStmt->bind_param('iss', $user_id, $monthStart, $monthEnd);
    $billStmt->execute();
    $billRes = $billStmt->get_result();
    if ($billRes && $billRes->num_rows > 0) {
        $billsReserve = (float)($billRes->fetch_assoc()['total'] ?? 0);
    }
}

// Savings target: 15% of expected income (if any)
$savingsTarget = $incomeAvg > 0 ? ($incomeAvg * 0.15) : 0.0;

// Available envelope for category budgets
$available = $incomeAvg > 0 ? max(0.0, $incomeAvg - $billsReserve - $savingsTarget) : 0.0;

// Build recommended limits for top categories
$items = [];
$maxItems = 8;
$sumSuggested = 0.0;

foreach ($categoryTotals as $row) {
    if (count($items) >= $maxItems) break;

    $avg = (float)$row['avg_month_bdt'];
    if ($avg <= 0) continue;

    // Basic guardrails: don’t set tiny budgets; round nicely
    $suggest = round($avg / 50) * 50;
    if ($suggest < 300) $suggest = 300;

    $items[] = [
        'category_id' => (int)$row['category_id'],
        'category_name' => (string)$row['category_name'],
        'recommended_limit_bdt' => (float)$suggest,
        'basis' => 'Average of last 3 months',
    ];
    $sumSuggested += $suggest;
}

// If we have a budget envelope, scale down if needed
if ($available > 0 && $sumSuggested > $available && $sumSuggested > 0) {
    $scale = $available / $sumSuggested;
    $sumSuggested = 0.0;
    foreach ($items as &$it) {
        $it['recommended_limit_bdt'] = (float)(round(($it['recommended_limit_bdt'] * $scale) / 50) * 50);
        if ($it['recommended_limit_bdt'] < 200) $it['recommended_limit_bdt'] = 200;
        $it['basis'] = $it['basis'] . ' (scaled to fit expected income)';
        $sumSuggested += $it['recommended_limit_bdt'];
    }
    unset($it);
}

$payload = [
    'month' => $targetMonth,
    'month_start' => $monthStart,
    'month_end' => $monthEnd,
    'expected_income_bdt' => round($incomeAvg, 2),
    'bills_reserve_bdt' => round($billsReserve, 2),
    'savings_target_bdt' => round($savingsTarget, 2),
    'category_budget_total_bdt' => round($sumSuggested, 2),
    'items' => $items,
    'notes' => [
        'All budget limits are stored in BDT.',
        'This is an estimate based on your last 3 months.'
    ]
];

// Optional Gemini refinement
$useGemini = defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI;
$apiKey = $useGemini ? get_gemini_api_key() : '';
if ($useGemini && $apiKey !== '' && count($items) > 0) {
    require_once __DIR__ . '/gemini_ai.php';
    $assistant = new GeminiAIAssistant($apiKey);

    if ($assistant->isConfigured()) {
        $prompt = "You are a budget autopilot. Produce a realistic monthly category budget plan in BDT.\n" .
            "Return STRICT JSON ONLY with shape:\n" .
            "{\"items\":[{\"category_id\":number,\"recommended_limit_bdt\":number,\"basis\":string}],\"notes\":[string]}\n" .
            "Rules:\n" .
            "- Use ONLY the provided category_id values.\n" .
            "- recommended_limit_bdt must be positive and rounded to nearest 50.\n" .
            "- Keep total of items roughly within category_budget_total_bdt if it is > 0.\n" .
            "- Prefer fewer items (5-8).\n\n" .
            "Context:\n" .
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $out = $assistant->generateFromPrompt($prompt);
        $json = is_string($out) ? json_decode(trim($out), true) : null;
        if (is_array($json) && isset($json['items']) && is_array($json['items'])) {
            $validIds = array_fill_keys(array_map(fn($i) => (int)$i['category_id'], $items), true);
            $newItems = [];
            foreach ($json['items'] as $it) {
                if (!is_array($it)) continue;
                $cid = (int)($it['category_id'] ?? 0);
                $lim = (float)($it['recommended_limit_bdt'] ?? 0);
                if ($cid <= 0 || !isset($validIds[$cid]) || $lim <= 0) continue;
                $lim = round($lim / 50) * 50;
                if ($lim < 200) $lim = 200;
                // resolve name
                $name = '';
                foreach ($items as $base) {
                    if ((int)$base['category_id'] === $cid) { $name = (string)$base['category_name']; break; }
                }
                $newItems[] = [
                    'category_id' => $cid,
                    'category_name' => $name,
                    'recommended_limit_bdt' => (float)$lim,
                    'basis' => isset($it['basis']) ? (string)$it['basis'] : 'Gemini refined'
                ];
            }
            if (count($newItems) > 0) {
                $payload['items'] = $newItems;
                $payload['category_budget_total_bdt'] = array_sum(array_map(fn($x) => (float)$x['recommended_limit_bdt'], $newItems));
                if (isset($json['notes']) && is_array($json['notes'])) {
                    $payload['notes'] = array_values(array_map('strval', $json['notes']));
                }
            }
        }
    }
}

echo json_encode(['success' => true] + $payload);
