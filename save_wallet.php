<?php
session_start();
header('Content-Type: application/json');
require "db.php";
require_once "currency_util.php";
require_once "notification_util.php";

function ensure_wallet_type_supports_savings(mysqli $conn): void {
    // If wallet_type is an ENUM missing 'Savings', add it.
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

    // Best-effort alter; ignore failures (older DBs might differ).
    $conn->query("ALTER TABLE wallets MODIFY wallet_type ENUM('Cash','Bank','Mobile','Card','Savings','Other') NOT NULL");
}

function ensure_wallet_card_columns(mysqli $conn): void {
    // Best-effort add columns for card metadata (do NOT store full PAN).
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
    // Return one of: Visa, Mastercard, Amex, Unknown
    if ($digits === '') return 'Unknown';
    // Visa: 13/16/19 digits, starts with 4
    if (preg_match('/^4\d{12}(\d{3})?(\d{3})?$/', $digits)) return 'Visa';
    // AmEx: 15 digits, starts with 34 or 37
    if (preg_match('/^3[47]\d{13}$/', $digits)) return 'Amex';
    // Mastercard: 16 digits, 51-55 or 2221-2720
    if (preg_match('/^(5[1-5]\d{14})$/', $digits)) return 'Mastercard';
    if (preg_match('/^(222[1-9]\d{12}|22[3-9]\d{13}|2[3-6]\d{14}|27[01]\d{13}|2720\d{12})$/', $digits)) return 'Mastercard';
    return 'Unknown';
}

// Debug log
error_log("save_wallet.php called");

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$raw = file_get_contents("php://input");
error_log("Raw input: " . $raw);

$data = json_decode($raw, true);

if (!$data) {
    error_log("No data decoded");
    echo json_encode(["success" => false, "error" => "No data received"]);
    exit;
}

// Extract and validate input
$wallet_name = trim((string)($data["wallet_name"] ?? ''));
$wallet_type = trim((string)($data["wallet_type"] ?? ''));
$balance = (float)($data["balance"] ?? 0);
$currency_code = normalize_currency((string)($data['currency_code'] ?? $DEFAULT_CURRENCY));
$user_id = (int)$_SESSION["user_id"];

error_log("Parsed data - wallet_name: $wallet_name, wallet_type: $wallet_type, balance: $balance, currency_code: $currency_code, user_id: $user_id");

if ($wallet_name === '') {
    error_log("Wallet name empty");
    echo json_encode(["success" => false, "error" => "Wallet name is required"]);
    exit;
}

if ($wallet_type === '') {
    error_log("Wallet type empty");
    echo json_encode(["success" => false, "error" => "Wallet type is required"]);
    exit;
}

// Normalize wallet type
$allowedTypes = ['Cash','Bank','Mobile','Card','Savings','Other'];
$normalizedType = ucfirst(strtolower($wallet_type));
if (!in_array($normalizedType, $allowedTypes, true)) {
    $normalizedType = 'Other';
}

// Ensure DB supports Savings enum before insert
ensure_wallet_type_supports_savings($conn);
ensure_wallet_card_columns($conn);

error_log("Normalized type: $normalizedType");

// Always store in BDT: convert incoming balance to BDT and persist currency_code='BDT'
$balance_bdt = convert_amount($balance, $currency_code, 'BDT');
$currency_code_db = 'BDT';

// Card metadata (store only brand + last4; never persist full number)
$card_brand = '';
$card_last4 = '';
if ($normalizedType === 'Card') {
    $rawCard = (string)($data['card_number'] ?? '');
    $digits = preg_replace('/\D+/', '', $rawCard);
    if ($digits !== '') {
        $brand = detect_card_brand($digits);
        // Prefer Luhn-valid numbers; otherwise keep Unknown
        if ($brand !== 'Unknown' && !luhn_is_valid($digits)) {
            $brand = 'Unknown';
        }
        $card_brand = $brand;
        $card_last4 = strlen($digits) >= 4 ? substr($digits, -4) : '';
    }
}

// Insert wallet (wallet_id is auto-increment, created_at is auto-timestamp)
$sql = "INSERT INTO wallets (user_id, wallet_name, wallet_type, balance, currency_code, card_brand, card_last4)
        VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(["success" => false, "error" => "Database prepare error: " . $conn->error]);
    exit;
}

// Bind parameters: i=int, s=string, d=double/float
// Order: user_id, wallet_name, wallet_type, balance, currency_code, card_brand, card_last4
$stmt->bind_param("issdsss", $user_id, $wallet_name, $normalizedType, $balance_bdt, $currency_code_db, $card_brand, $card_last4);

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    // Check for duplicate wallet name error
    if (strpos($stmt->error, 'Duplicate entry') !== false) {
        echo json_encode(["success" => false, "error" => "Wallet with this name already exists"]);
    } else {
        echo json_encode(["success" => false, "error" => "Failed to save wallet: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

error_log("Wallet saved successfully");

// Add notification for wallet creation
$notificationTitle = "New Wallet Created";
$notificationMessage = "Wallet '$wallet_name' ($normalizedType) created with balance: $balance $currency_code";
add_notification($conn, $user_id, $notificationTitle, $notificationMessage, 'wallet_created');

$stmt->close();
echo json_encode(["success" => true]);

