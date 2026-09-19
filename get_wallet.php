<?php
session_start();
require_once 'db.php'; // database connection

function ensure_wallet_card_columns(mysqli $conn): void {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM wallets");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[strtolower((string)($row['Field'] ?? ''))] = true;
        }
    }
    if (!isset($cols['card_brand'])) {
        $conn->query("ALTER TABLE wallets ADD COLUMN card_brand VARCHAR(20) NULL");
    }
    if (!isset($cols['card_last4'])) {
        $conn->query("ALTER TABLE wallets ADD COLUMN card_last4 VARCHAR(4) NULL");
    }
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

ensure_wallet_card_columns($conn);

$sql = "SELECT wallet_id AS id, wallet_name, balance, wallet_type AS type, currency_code, card_brand, card_last4
        FROM wallets WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$wallets = [];

while ($row = $result->fetch_assoc()) {
    $wallets[] = $row;
}

echo json_encode($wallets);
