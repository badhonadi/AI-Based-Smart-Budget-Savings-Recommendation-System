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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$type = strtolower(trim((string)($data['type'] ?? 'expense')));
$amount = (float)($data['amount'] ?? 0);
$currency_code = normalize_currency((string)($data['currency_code'] ?? $DEFAULT_CURRENCY));
$category_id = isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null;
$description = trim((string)($data['description'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Amount must be > 0']);
    exit;
}

$amount_bdt = convert_amount($amount, $currency_code, 'BDT');

// Wallets
$wStmt = $conn->prepare("SELECT wallet_id, wallet_name, wallet_type, currency_code, balance FROM wallets WHERE user_id = ? ORDER BY wallet_name");
$wStmt->bind_param('i', $user_id);
$wStmt->execute();
$wRes = $wStmt->get_result();

$wallets = [];
while ($row = $wRes->fetch_assoc()) {
    $wCur = normalize_currency((string)($row['currency_code'] ?? $DEFAULT_CURRENCY));
    $balanceRaw = (float)($row['balance'] ?? 0);
    $balance_bdt = convert_amount($balanceRaw, $wCur, 'BDT');

    $wallets[] = [
        'wallet_id' => (int)$row['wallet_id'],
        'name' => (string)$row['wallet_name'],
        'type' => (string)$row['wallet_type'],
        'currency_code' => $wCur,
        'balance' => $balanceRaw,
        'balance_bdt' => $balance_bdt,
    ];
}

if (count($wallets) === 0) {
    echo json_encode([
        'success' => true,
        'suggestion' => [
            'mode' => 'none',
            'message' => 'No wallets found. Create a wallet first.',
            'wallets' => []
        ]
    ]);
    exit;
}

// Category wallet usage (last 90 days)
$walletUsage = []; // wallet_id => total_bdt
if ($category_id !== null && $category_id > 0) {
    $since = date('Y-m-d', strtotime('-90 days'));
    $usageStmt = $conn->prepare(
        "SELECT wallet_id, SUM(amount) as total_bdt
         FROM transactions
         WHERE user_id = ? AND category_id = ? AND DATE(transaction_time) >= ?
         GROUP BY wallet_id"
    );
    $usageStmt->bind_param('iis', $user_id, $category_id, $since);
    $usageStmt->execute();
    $usageRes = $usageStmt->get_result();
    while ($r = $usageRes->fetch_assoc()) {
        $wid = (int)$r['wallet_id'];
        $walletUsage[$wid] = (float)($r['total_bdt'] ?? 0);
    }
}

function pick_single_wallet(array $wallets, array $walletUsage, float $amount_bdt): array {
    // Prefer wallet used most for the category IF it has enough balance.
    if (!empty($walletUsage)) {
        arsort($walletUsage);
        foreach ($walletUsage as $wid => $total) {
            foreach ($wallets as $w) {
                if ($w['wallet_id'] === (int)$wid && $w['balance_bdt'] >= $amount_bdt) {
                    return [$w, 'Based on your recent usage for this category.'];
                }
            }
        }
    }

    // Otherwise pick highest balance wallet that can cover.
    $candidates = array_filter($wallets, fn($w) => $w['balance_bdt'] >= $amount_bdt);
    usort($candidates, fn($a, $b) => $b['balance_bdt'] <=> $a['balance_bdt']);
    if (!empty($candidates)) {
        return [$candidates[0], 'Highest available balance to cover this transaction.'];
    }

    // If no wallet can cover fully, fall back to highest balance.
    $sorted = $wallets;
    usort($sorted, fn($a, $b) => $b['balance_bdt'] <=> $a['balance_bdt']);
    return [$sorted[0], 'No single wallet fully covers it; this is the closest match.'];
}

function build_split_plan(array $wallets, float $amount_bdt): array {
    $sorted = $wallets;
    usort($sorted, fn($a, $b) => $b['balance_bdt'] <=> $a['balance_bdt']);

    $remaining = $amount_bdt;
    $splits = [];

    foreach ($sorted as $w) {
        if ($remaining <= 0) break;
        $can = max(0.0, (float)$w['balance_bdt']);
        if ($can <= 0) continue;
        $take = min($can, $remaining);
        if ($take <= 0) continue;
        $splits[] = [
            'wallet_id' => (int)$w['wallet_id'],
            'amount_bdt' => $take,
            'reason' => 'Use available balance.'
        ];
        $remaining -= $take;
    }

    return [$splits, $remaining];
}

// Base heuristic suggestion
$suggestion = [
    'mode' => 'single',
    'message' => '',
    'wallets' => [],
    'confidence' => 0.62
];

if ($type === 'income') {
    // For income: prefer Bank wallet, otherwise highest balance wallet.
    $bank = null;
    foreach ($wallets as $w) {
        if (strtolower((string)$w['type']) === 'bank') {
            $bank = $w;
            break;
        }
    }
    if ($bank) {
        $suggestion['wallets'] = [[
            'wallet_id' => $bank['wallet_id'],
            'amount' => $amount,
            'currency_code' => $currency_code,
            'reason' => 'Income is usually best kept in a bank wallet.'
        ]];
        $suggestion['message'] = 'Suggested a bank wallet for income.';
        $suggestion['confidence'] = 0.7;
    } else {
        [$w, $reason] = pick_single_wallet($wallets, $walletUsage, 0);
        $suggestion['wallets'] = [[
            'wallet_id' => $w['wallet_id'],
            'amount' => $amount,
            'currency_code' => $currency_code,
            'reason' => $reason
        ]];
        $suggestion['message'] = 'Suggested a wallet for income.';
    }
} else {
    // Expense
    [$singleWallet, $reason] = pick_single_wallet($wallets, $walletUsage, $amount_bdt);

    if ($singleWallet['balance_bdt'] >= $amount_bdt) {
        $suggestion['mode'] = 'single';
        $suggestion['wallets'] = [[
            'wallet_id' => $singleWallet['wallet_id'],
            'amount' => $amount,
            'currency_code' => $currency_code,
            'reason' => $reason
        ]];
        $suggestion['message'] = 'This wallet can cover the expense.';
        $suggestion['confidence'] = 0.75;
    } else {
        [$splits, $remaining] = build_split_plan($wallets, $amount_bdt);
        $suggestion['mode'] = 'split';
        $suggestion['confidence'] = 0.68;

        // Convert split BDT back to the user's input currency for display/saving.
        $walletOut = [];
        $sumOut = 0.0;
        foreach ($splits as $s) {
            $amt = convert_amount((float)$s['amount_bdt'], 'BDT', $currency_code);
            $sumOut += $amt;
            $walletOut[] = [
                'wallet_id' => (int)$s['wallet_id'],
                'amount' => round($amt, 2),
                'currency_code' => $currency_code,
                'reason' => $s['reason']
            ];
        }

        // Ensure the split sums to the original amount (rounding fix on last split).
        if (!empty($walletOut)) {
            $diff = round($amount - $sumOut, 2);
            $walletOut[count($walletOut) - 1]['amount'] = round($walletOut[count($walletOut) - 1]['amount'] + $diff, 2);
        }

        $suggestion['wallets'] = $walletOut;

        if ($remaining > 0.01) {
            $suggestion['message'] = 'Insufficient funds across wallets; this is the maximum possible split.';
        } else {
            $suggestion['message'] = 'Split across wallets to cover the full expense.';
        }
    }
}

// Optional Gemini refinement (never required)
$useGemini = defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI;
$apiKey = $useGemini ? get_gemini_api_key() : '';

if ($useGemini && $apiKey !== '') {
    require_once __DIR__ . '/gemini_ai.php';
    $assistant = new GeminiAIAssistant($apiKey);

    if ($assistant->isConfigured()) {
        $walletSummary = array_map(function ($w) {
            return [
                'wallet_id' => $w['wallet_id'],
                'name' => $w['name'],
                'type' => $w['type'],
                'balance_bdt' => round((float)$w['balance_bdt'], 2),
            ];
        }, $wallets);

        $prompt = "You are a finance assistant. Suggest which wallet(s) to use for this transaction.\n" .
            "Return STRICT JSON ONLY with this shape:\n" .
            "{\"mode\":\"single\"|\"split\",\"wallets\":[{\"wallet_id\":number,\"amount\":" .
            "number,\"reason\":string}],\"message\":string,\"confidence\":number}\n\n" .
            "Rules:\n" .
            "- Use only wallet_id values from the provided wallets list.\n" .
            "- Amounts must be in the user's input currency {$currency_code}.\n" .
            "- Sum(wallets[].amount) must equal {$amount}.\n" .
            "- If expense and one wallet covers it, prefer single unless there is a strong reason.\n\n" .
            "Transaction:\n" .
            "- type: {$type}\n" .
            "- amount: {$amount} {$currency_code}\n" .
            "- category_id: " . ($category_id !== null ? (string)$category_id : 'null') . "\n" .
            "- description: " . json_encode($description) . "\n" .
            "- notes: " . json_encode($notes) . "\n\n" .
            "Wallets (balance is in BDT):\n" . json_encode($walletSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n" .
            "Heuristic suggestion (can refine):\n" . json_encode($suggestion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $text = (string)$assistant->generateFromPrompt($prompt);
            $maybe = json_decode(trim($text), true);
            if (is_array($maybe) && isset($maybe['mode']) && isset($maybe['wallets']) && is_array($maybe['wallets'])) {
                // Validate wallet ids and sum
                $allowed = array_flip(array_map(fn($w) => (string)$w['wallet_id'], $wallets));
                $sum = 0.0;
                $cleanWallets = [];
                foreach ($maybe['wallets'] as $w) {
                    $wid = (int)($w['wallet_id'] ?? 0);
                    $amt = (float)($w['amount'] ?? 0);
                    if ($wid <= 0 || $amt <= 0) continue;
                    if (!isset($allowed[(string)$wid])) continue;
                    $sum += $amt;
                    $cleanWallets[] = [
                        'wallet_id' => $wid,
                        'amount' => round($amt, 2),
                        'currency_code' => $currency_code,
                        'reason' => (string)($w['reason'] ?? '')
                    ];
                }
                if (!empty($cleanWallets) && abs(round($sum - $amount, 2)) <= 0.05) {
                    // rounding fix
                    $diff = round($amount - $sum, 2);
                    $cleanWallets[count($cleanWallets) - 1]['amount'] = round($cleanWallets[count($cleanWallets) - 1]['amount'] + $diff, 2);

                    $suggestion['mode'] = ($maybe['mode'] === 'split') ? 'split' : 'single';
                    $suggestion['wallets'] = $cleanWallets;
                    $suggestion['message'] = (string)($maybe['message'] ?? $suggestion['message']);
                    $suggestion['confidence'] = isset($maybe['confidence']) ? (float)$maybe['confidence'] : $suggestion['confidence'];
                }
            }
        } catch (Throwable $e) {
            error_log('Gemini wallet suggestion failed: ' . $e->getMessage());
        }
    }
}

echo json_encode(['success' => true, 'suggestion' => $suggestion]);
