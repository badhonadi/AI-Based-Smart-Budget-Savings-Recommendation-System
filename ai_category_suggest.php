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
$typeDb = $type === 'income' ? 'Income' : 'Expense';

$amount = (float)($data['amount'] ?? 0);
$currency_code = normalize_currency((string)($data['currency_code'] ?? $DEFAULT_CURRENCY));
$description = trim((string)($data['description'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

$text = trim(mb_strtolower($description . ' ' . $notes));
if ($text === '') {
    echo json_encode([
        'success' => true,
        'suggestion' => [
            'category_id' => null,
            'category_name' => null,
            'subcategory' => null,
            'confidence' => 0.0,
            'reason' => 'No description/notes provided',
            'source' => 'heuristic'
        ]
    ]);
    exit;
}

// Load categories for this user + type
$catStmt = $conn->prepare("SELECT category_id, category_name FROM categories WHERE user_id = ? AND category_type = ? ORDER BY category_name");
$catStmt->bind_param('is', $user_id, $typeDb);
$catStmt->execute();
$catRes = $catStmt->get_result();

$categories = [];
while ($r = $catRes->fetch_assoc()) {
    $categories[] = [
        'category_id' => (int)$r['category_id'],
        'category_name' => (string)$r['category_name'],
    ];
}

if (count($categories) === 0) {
    echo json_encode([
        'success' => true,
        'suggestion' => [
            'category_id' => null,
            'category_name' => null,
            'subcategory' => null,
            'confidence' => 0.0,
            'reason' => 'No categories found for this type',
            'source' => 'heuristic'
        ]
    ]);
    exit;
}

function normalize_cat_name(string $name): string {
    $s = trim(mb_strtolower($name));
    // Strip leading emoji + spaces if present
    if (preg_match('/^(\X)\s+(.+)$/u', $s, $m)) {
        return trim((string)$m[2]);
    }
    return $s;
}

// Basic keyword -> intent mapping (works even when Gemini is off)
$keywordHints = [
    // Expense
    'expense' => [
        'food' => ['food','pizza','burger','grocer','grocery','rice','lunch','dinner','breakfast','kfc','restaurant','cafe','coffee','tea'],
        'shopping' => ['shopping','daraz','amazon','mall','cloth','clothes','shirt','pant','shoes','bag','gift'],
        'transport' => ['uber','pathao','bus','train','taxi','fuel','petrol','gasoline','cng','rickshaw','parking'],
        'bills' => ['bill','electric','electricity','gas','water','internet','wifi','phone','mobile','recharge','bkash','nagad'],
        'health' => ['doctor','hospital','pharmacy','medicine','med','test','xray','clinic'],
        'education' => ['course','tuition','school','college','university','exam','book','class'],
        'entertainment' => ['movie','cinema','netflix','spotify','game','gaming','concert','fun'],
        'housing' => ['rent','house','flat','apartment','mortgage'],
        'travel' => ['hotel','flight','air','tour','trip','visa'],
    ],
    // Income
    'income' => [
        'salary' => ['salary','payroll','wage','bonus','commission','overtime'],
        'business' => ['client','sale','sales','invoice','service','consulting','project'],
        'investment' => ['interest','dividend','profit','stock','share'],
        'gift' => ['gift','present','cash gift','donation','received'],
    ]
];

$tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
$tokenSet = array_fill_keys($tokens ?: [], true);

// Score categories
$scores = []; // category_id => score
$reasons = []; // category_id => reason strings

foreach ($categories as $cat) {
    $cid = (int)$cat['category_id'];
    $cnameNorm = normalize_cat_name((string)$cat['category_name']);

    $scores[$cid] = 0.05;
    $reasons[$cid] = [];

    // Token overlap with category name
    foreach ($tokenSet as $tok => $_) {
        if (mb_strlen($tok) < 3) continue;
        if (mb_strpos($cnameNorm, $tok) !== false) {
            $scores[$cid] += 0.18;
            $reasons[$cid][] = "Matched keyword '{$tok}' in category name";
        }
    }

    // Keyword hints
    $hintBucket = $type === 'income' ? ($keywordHints['income'] ?? []) : ($keywordHints['expense'] ?? []);
    foreach ($hintBucket as $hintName => $words) {
        foreach ($words as $w) {
            $w = (string)$w;
            if (mb_strlen($w) < 3) continue;
            if (mb_strpos($text, $w) !== false) {
                if (mb_strpos($cnameNorm, $hintName) !== false || mb_strpos($cnameNorm, $w) !== false) {
                    $scores[$cid] += 0.25;
                    $reasons[$cid][] = "Hint '{$hintName}' matched '{$w}'";
                    break 2;
                }
            }
        }
    }
}

// Recent similar transactions boost (best-effort; note stores JSON containing description)
$patterns = [];
if (mb_strlen($description) >= 4) {
    $patterns[] = '%' . $description . '%';
}
if (mb_strlen($notes) >= 4) {
    $patterns[] = '%' . $notes . '%';
}
if (!empty($tokens)) {
    foreach ($tokens as $tok) {
        if (mb_strlen($tok) >= 5) {
            $patterns[] = '%' . $tok . '%';
            break;
        }
    }
}

$patterns = array_values(array_unique($patterns));
$patterns = array_slice($patterns, 0, 3);

if (count($patterns) > 0) {
    // Pad to 3 for binding simplicity
    while (count($patterns) < 3) $patterns[] = '###NO_MATCH###';

    $since = date('Y-m-d', strtotime('-120 days'));
    $hist = $conn->prepare(
        "SELECT t.category_id, COUNT(*) as cnt
         FROM transactions t
         WHERE t.user_id = ? AND t.transaction_type = ? AND t.category_id IS NOT NULL
           AND DATE(t.transaction_time) >= ?
           AND (t.note LIKE ? OR t.note LIKE ? OR t.note LIKE ?)
         GROUP BY t.category_id
         ORDER BY cnt DESC
         LIMIT 5"
    );
    if ($hist) {
        $hist->bind_param('isssss', $user_id, $typeDb, $since, $patterns[0], $patterns[1], $patterns[2]);
        $hist->execute();
        $histRes = $hist->get_result();
        while ($r = $histRes->fetch_assoc()) {
            $cid = (int)$r['category_id'];
            $cnt = (int)($r['cnt'] ?? 0);
            if (!isset($scores[$cid])) continue;
            $boost = min(0.45, 0.15 + 0.06 * $cnt);
            $scores[$cid] += $boost;
            $reasons[$cid][] = "Matched {$cnt} similar transaction(s) in last 120 days";
        }
    }
}

// Pick best
arsort($scores);
$bestId = (int)array_key_first($scores);
$bestCat = null;
foreach ($categories as $c) {
    if ((int)$c['category_id'] === $bestId) {
        $bestCat = $c;
        break;
    }
}

$bestScore = isset($scores[$bestId]) ? (float)$scores[$bestId] : 0.0;
$confidence = max(0.0, min(0.92, $bestScore));

$suggestion = [
    'category_id' => $bestCat ? (int)$bestCat['category_id'] : null,
    'category_name' => $bestCat ? (string)$bestCat['category_name'] : null,
    'subcategory' => null,
    'confidence' => $confidence,
    'reason' => $bestCat ? implode('; ', array_slice($reasons[$bestId] ?? [], 0, 3)) : 'No match',
    'source' => 'heuristic'
];

// Optional Gemini refinement
$useGemini = defined('ENABLE_GEMINI_AI') && ENABLE_GEMINI_AI;
$apiKey = $useGemini ? get_gemini_api_key() : '';
if ($useGemini && $apiKey !== '' && $confidence < 0.9) {
    require_once __DIR__ . '/gemini_ai.php';
    $assistant = new GeminiAIAssistant($apiKey);

    if ($assistant->isConfigured()) {
        $catList = array_map(function ($c) {
            return [
                'category_id' => (int)$c['category_id'],
                'category_name' => (string)$c['category_name'],
            ];
        }, $categories);

        $prompt = "You are a transaction categorization assistant.\n" .
            "Return STRICT JSON ONLY with shape: {\"category_id\": number|null, \"subcategory\": string|null, \"confidence\": number, \"reason\": string}.\n" .
            "Rules:\n" .
            "- category_id MUST be one of the provided category_id values (or null).\n" .
            "- confidence is 0..1.\n" .
            "- Be conservative; if unclear, return null with low confidence.\n\n" .
            "Transaction:\n" .
            "- type: {$typeDb}\n" .
            "- amount: {$amount} {$currency_code}\n" .
            "- description: " . json_encode($description) . "\n" .
            "- notes: " . json_encode($notes) . "\n\n" .
            "Categories:\n" . json_encode($catList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n" .
            "Heuristic guess (can accept or override):\n" . json_encode($suggestion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $out = $assistant->generateFromPrompt($prompt);
        if (is_string($out) && trim($out) !== '') {
            $json = json_decode(trim($out), true);
            if (is_array($json)) {
                $cid = isset($json['category_id']) ? (int)$json['category_id'] : 0;
                $valid = false;
                foreach ($categories as $c) {
                    if ((int)$c['category_id'] === $cid) {
                        $valid = true;
                        $bestCat = $c;
                        break;
                    }
                }
                if ($valid) {
                    $conf = (float)($json['confidence'] ?? 0);
                    $conf = max(0.0, min(0.98, $conf));
                    $suggestion = [
                        'category_id' => (int)$bestCat['category_id'],
                        'category_name' => (string)$bestCat['category_name'],
                        'subcategory' => isset($json['subcategory']) && is_string($json['subcategory']) && trim($json['subcategory']) !== '' ? trim((string)$json['subcategory']) : null,
                        'confidence' => $conf,
                        'reason' => isset($json['reason']) ? (string)$json['reason'] : 'Gemini suggestion',
                        'source' => 'gemini'
                    ];
                }
            }
        }
    }
}

echo json_encode(['success' => true, 'suggestion' => $suggestion]);
