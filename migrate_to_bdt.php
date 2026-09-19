<?php
// One-time migration: convert all amounts to BDT and mark currency_code='BDT'
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/currency_util.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo '<h2>Unauthorized</h2><p>Please login first.</p>';
    exit;
}

$userId = (int)$_SESSION['user_id'];
$isSupport = !empty($_SESSION['is_support']);
$migrateAll = isset($_GET['all']) && $_GET['all'] === '1' && $isSupport;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Ensure column exists helper
function ensure_currency_column(mysqli $conn, string $table): void {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE 'currency_code'");
    if (!$res || $res->num_rows === 0) {
        $conn->query("ALTER TABLE `$t` ADD COLUMN `currency_code` CHAR(3) NOT NULL DEFAULT 'BDT'");
    }
}

ensure_currency_column($conn, 'wallets');
ensure_currency_column($conn, 'transactions');
ensure_currency_column($conn, 'budget_items');

$summary = [
    'scope' => $migrateAll ? 'ALL USERS (support)' : ('user_id=' . $userId),
    'wallets' => ['scanned' => 0, 'converted' => 0, 'skipped' => 0],
    'transactions' => ['scanned' => 0, 'converted' => 0, 'skipped' => 0],
    'budgets' => ['scanned' => 0, 'converted' => 0, 'skipped' => 0],
    'errors' => []
];

// 1) Wallets
try {
    $where = $migrateAll ? '1=1' : 'user_id = ?';
    $sql = "SELECT wallet_id, user_id, balance, currency_code FROM wallets WHERE $where";
    $stmt = $conn->prepare($sql);
    if ($migrateAll) {
        $stmt->execute();
    } else {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
    $res = $stmt->get_result();

    $conn->begin_transaction();
    while ($row = $res->fetch_assoc()) {
        $summary['wallets']['scanned']++;
        $code = normalize_currency((string)($row['currency_code'] ?? $DEFAULT_CURRENCY));
        $bal = (float)$row['balance'];
        if ($code === 'BDT') { $summary['wallets']['skipped']++; continue; }
        $balBDT = convert_amount($bal, $code, 'BDT');
        $upd = $conn->prepare('UPDATE wallets SET balance = ?, currency_code = "BDT" WHERE wallet_id = ?');
        $upd->bind_param('di', $balBDT, $row['wallet_id']);
        if ($upd->execute()) {
            $summary['wallets']['converted']++;
        } else {
            $summary['errors'][] = 'Wallet ' . $row['wallet_id'] . ' update failed: ' . $upd->error;
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $summary['errors'][] = 'Wallet migration error: ' . $e->getMessage();
}

// 2) Transactions
try {
    $where = $migrateAll ? '1=1' : 'user_id = ?';
    $sql = "SELECT transaction_id, user_id, amount, currency_code FROM transactions WHERE $where";
    $stmt = $conn->prepare($sql);
    if ($migrateAll) {
        $stmt->execute();
    } else {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
    $res = $stmt->get_result();

    $conn->begin_transaction();
    while ($row = $res->fetch_assoc()) {
        $summary['transactions']['scanned']++;
        $code = normalize_currency((string)($row['currency_code'] ?? $DEFAULT_CURRENCY));
        $amt = (float)$row['amount'];
        if ($code === 'BDT') { $summary['transactions']['skipped']++; continue; }
        $amtBDT = convert_amount($amt, $code, 'BDT');
        $upd = $conn->prepare('UPDATE transactions SET amount = ?, currency_code = "BDT" WHERE transaction_id = ?');
        $upd->bind_param('di', $amtBDT, $row['transaction_id']);
        if ($upd->execute()) {
            $summary['transactions']['converted']++;
        } else {
            $summary['errors'][] = 'Transaction ' . $row['transaction_id'] . ' update failed: ' . $upd->error;
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $summary['errors'][] = 'Transaction migration error: ' . $e->getMessage();
}

// 3) Budget items (filter by budgets.user_id)
try {
    if ($migrateAll) {
        $sql = "SELECT bi.budget_item_id, bi.amount_limit, bi.currency_code
                FROM budget_items bi JOIN budgets b ON b.budget_id = bi.budget_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        $sql = "SELECT bi.budget_item_id, bi.amount_limit, bi.currency_code
                FROM budget_items bi JOIN budgets b ON b.budget_id = bi.budget_id
                WHERE b.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
    $res = $stmt->get_result();

    $conn->begin_transaction();
    while ($row = $res->fetch_assoc()) {
        $summary['budgets']['scanned']++;
        $code = normalize_currency((string)($row['currency_code'] ?? $DEFAULT_CURRENCY));
        $lim = (float)$row['amount_limit'];
        if ($code === 'BDT') { $summary['budgets']['skipped']++; continue; }
        $limBDT = convert_amount($lim, $code, 'BDT');
        $upd = $conn->prepare('UPDATE budget_items SET amount_limit = ?, currency_code = "BDT" WHERE budget_item_id = ?');
        $upd->bind_param('di', $limBDT, $row['budget_item_id']);
        if ($upd->execute()) {
            $summary['budgets']['converted']++;
        } else {
            $summary['errors'][] = 'Budget item ' . $row['budget_item_id'] . ' update failed: ' . $upd->error;
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $summary['errors'][] = 'Budget migration error: ' . $e->getMessage();
}

// Output summary
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>BDT Migration Summary</title>
    <style>
        body{font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial; padding: 24px; background:#0f172a; color:#e2e8f0}
        .card{background:#111827; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:16px}
        .ok{color:#84cc16}
        .warn{color:#f59e0b}
        .err{color:#f87171}
        table{width:100%; border-collapse:collapse; margin-top:8px}
        th,td{padding:8px; border-bottom:1px solid #334155; text-align:left}
        code{background:#0b1220; padding:2px 6px; border-radius:6px}
        a{color:#93c5fd}
    </style>
</head>
<body>
    <h2>BDT Migration Completed</h2>
    <div class="card">
        <div>Scope: <strong><?php echo h($summary['scope']); ?></strong></div>
        <div>Wallets — scanned: <span><?php echo h($summary['wallets']['scanned']); ?></span>, converted: <span class="ok"><?php echo h($summary['wallets']['converted']); ?></span>, skipped: <span class="warn"><?php echo h($summary['wallets']['skipped']); ?></span></div>
        <div>Transactions — scanned: <span><?php echo h($summary['transactions']['scanned']); ?></span>, converted: <span class="ok"><?php echo h($summary['transactions']['converted']); ?></span>, skipped: <span class="warn"><?php echo h($summary['transactions']['skipped']); ?></span></div>
        <div>Budget Items — scanned: <span><?php echo h($summary['budgets']['scanned']); ?></span>, converted: <span class="ok"><?php echo h($summary['budgets']['converted']); ?></span>, skipped: <span class="warn"><?php echo h($summary['budgets']['skipped']); ?></span></div>
    </div>

    <?php if (!empty($summary['errors'])): ?>
    <div class="card">
        <h3>Errors</h3>
        <ul>
            <?php foreach ($summary['errors'] as $e): ?>
                <li class="err"><?php echo h($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <p>Run for all users (support only): <code>migrate_to_bdt.php?all=1</code></p>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>
