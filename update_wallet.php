<?php
session_start();
header('Content-Type: application/json');
require "db.php";
require_once "currency_util.php";
require_once "notification_util.php";

function ensure_wallet_type_supports_savings(mysqli $conn): void {
    $res = $conn->query("SHOW COLUMNS FROM wallets LIKE 'wallet_type'");
    if (!$res || $res->num_rows === 0) {
        return;
    }
    $row = $res->fetch_assoc();
    $type = strtolower((string)($row['Type'] ?? ''));
    if (strpos($type, 'enum(') !== 0) {
        return;
    }
    if (strpos($type, "'savings'") !== false) {
        return;
    }
    $conn->query("ALTER TABLE wallets MODIFY wallet_type ENUM('Cash','Bank','Mobile','Card','Savings','Other') NOT NULL");
}

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

function luhn_is_valid(string $digits): bool {
    if ($digits === '' || preg_match('/\D/', $digits)) return false;
    $sum = 0;
    $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $n = (int)$digits[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10) === 0;
}

function detect_card_brand(string $digits): string {
    if ($digits === '') return 'Unknown';
    if (preg_match('/^4\d{12}(\d{3})?(\d{3})?$/', $digits)) return 'Visa';
    if (preg_match('/^3[47]\d{13}$/', $digits)) return 'Amex';
    if (preg_match('/^(5[1-5]\d{14})$/', $digits)) return 'Mastercard';
    if (preg_match('/^(222[1-9]\d{12}|22[3-9]\d{13}|2[3-6]\d{14}|27[01]\d{13}|2720\d{12})$/', $digits)) return 'Mastercard';
    return 'Unknown';
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(["success" => false, "error" => "No data received"]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$wallet_id = (int)($data['wallet_id'] ?? 0);
$wallet_name = trim((string)($data['wallet_name'] ?? ''));
$wallet_type = trim((string)($data['wallet_type'] ?? ''));
$balance = (float)($data['balance'] ?? 0);
$currency_code = normalize_currency((string)($data['currency_code'] ?? $DEFAULT_CURRENCY));

if ($wallet_id <= 0 || $wallet_name === '' || $wallet_type === '') {
    echo json_encode(["success" => false, "error" => "Wallet id, name, and type are required"]);
    exit;
}

$allowedTypes = ['Cash','Bank','Mobile','Card','Savings','Other'];
$normalizedType = ucfirst(strtolower($wallet_type));
if (!in_array($normalizedType, $allowedTypes, true)) {
    $normalizedType = 'Other';
}

ensure_wallet_type_supports_savings($conn);
ensure_wallet_card_columns($conn);

$balance_bdt = convert_amount($balance, $currency_code, 'BDT');
$currency_code_db = 'BDT';

// Card metadata (store only brand + last4; never persist full number)
$card_brand = '';
$card_last4 = '';
$hasNewCardNumber = false;
if ($normalizedType === 'Card') {
    $rawCard = (string)($data['card_number'] ?? '');
    $digits = preg_replace('/\D+/', '', $rawCard);
    if ($digits !== '') {
        $hasNewCardNumber = true;
        $brand = detect_card_brand($digits);
        if ($brand !== 'Unknown' && !luhn_is_valid($digits)) {
            $brand = 'Unknown';
        }
        $card_brand = $brand;
        $card_last4 = strlen($digits) >= 4 ? substr($digits, -4) : '';
    }
}

// If updating a Card wallet but no new card_number was provided, keep existing metadata.
// If changing away from Card, clear metadata.
if ($normalizedType === 'Card') {
    $stmt = $conn->prepare("UPDATE wallets
        SET wallet_name = ?, wallet_type = ?, currency_code = ?, balance = ?,
            card_brand = COALESCE(NULLIF(?, ''), card_brand),
            card_last4 = COALESCE(NULLIF(?, ''), card_last4)
        WHERE wallet_id = ? AND user_id = ?");
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database prepare error: " . $conn->error]);
        exit;
    }
    // If no new number, send empty strings so COALESCE keeps existing.
    if (!$hasNewCardNumber) {
        $card_brand = '';
        $card_last4 = '';
    }
    $stmt->bind_param("sssdssii", $wallet_name, $normalizedType, $currency_code_db, $balance_bdt, $card_brand, $card_last4, $wallet_id, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE wallets
        SET wallet_name = ?, wallet_type = ?, currency_code = ?, balance = ?,
            card_brand = NULL,
            card_last4 = NULL
        WHERE wallet_id = ? AND user_id = ?");
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database prepare error: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("sssdii", $wallet_name, $normalizedType, $currency_code_db, $balance_bdt, $wallet_id, $user_id);
}
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Database prepare error: " . $conn->error]);
    exit;
}

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "error" => "Failed to update wallet: " . $stmt->error]);
    $stmt->close();
    exit;
}

// Add notification for wallet update
$notificationTitle = "Wallet Updated";
$notificationMessage = "Wallet '$wallet_name' ($normalizedType) updated with balance: $balance $currency_code";
add_notification($conn, $user_id, $notificationTitle, $notificationMessage, 'wallet_updated');

$stmt->close();
echo json_encode(["success" => true]);
