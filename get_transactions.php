<?php
session_start();
header('Content-Type: application/json');
require "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT t.transaction_id, t.wallet_id, t.category_id, c.category_name, t.transaction_type, t.amount, t.currency_code, t.transaction_time, t.note FROM transactions t LEFT JOIN categories c ON c.category_id = t.category_id WHERE t.user_id = ? ORDER BY t.transaction_time DESC LIMIT 500");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
    $noteRaw = (string)($row['note'] ?? '');
    $description = $noteRaw;
    $notes = '';

    // If stored as JSON {"d":"...","n":"..."}
    if ($noteRaw !== '' && ($noteRaw[0] === '{' || $noteRaw[0] === '[')) {
        $decoded = json_decode($noteRaw, true);
        if (is_array($decoded)) {
            $description = (string)($decoded['d'] ?? ($decoded['description'] ?? $description));
            $notes = (string)($decoded['n'] ?? ($decoded['notes'] ?? ''));
        }
    } elseif (strpos($noteRaw, "||") !== false) {
        [$description, $notes] = array_pad(explode('||', $noteRaw, 2), 2, '');
        $description = trim((string)$description);
        $notes = trim((string)$notes);
    }

    $out[] = [
        'id' => (string)$row['transaction_id'],
        'type' => strtolower((string)$row['transaction_type']) === 'expense' ? 'expense' : 'income',
        'amount' => (float)$row['amount'],
        'description' => $description,
        'notes' => $notes,
        'walletId' => (string)$row['wallet_id'],
        'categoryId' => $row['category_id'] !== null ? (string)$row['category_id'] : null,
        'categoryName' => $row['category_name'] !== null ? (string)$row['category_name'] : null,
        'date' => date('Y-m-d', strtotime($row['transaction_time'])),
        'createdAt' => $row['transaction_time'],
        'currency' => $row['currency_code'] ?? 'BDT',
    ];
}

echo json_encode($out);
