<?php
// 1️⃣ PHP CODE — একদম উপরে
session_start();
require "db.php";

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

// Ensure currency_code columns exist (runtime safeguard if migration not run)
function ensure_currency_column(mysqli $conn, string $table): void {
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW COLUMNS FROM `$tableEsc` LIKE 'currency_code'");
    if ($res && $res->num_rows > 0) {
        return;
    }
    // Best-effort add; ignore failure
    $conn->query("ALTER TABLE `$tableEsc` ADD COLUMN currency_code CHAR(3) NOT NULL DEFAULT 'BDT'");
}

ensure_currency_column($conn, 'wallets');
ensure_currency_column($conn, 'transactions');
ensure_currency_column($conn, 'budget_items');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

ensure_wallet_card_columns($conn);

$user_full_name = '';
$u = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1");
if ($u) {
    $uid = (int)$_SESSION['user_id'];
    $u->bind_param('i', $uid);
    $u->execute();
    $ur = $u->get_result();
    if ($ur && ($row = $ur->fetch_assoc())) {
        $first = trim((string)($row['first_name'] ?? ''));
        $last = trim((string)($row['last_name'] ?? ''));
        $user_full_name = trim($first . ' ' . $last);
    }
    $u->close();
}

// Database থেকে wallets আনবে (spendeeapp.sql schema)
$sql = "SELECT wallet_id AS id, wallet_name, balance, wallet_type, currency_code, card_brand, card_last4 FROM wallets WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$wallets = [];
while($row = mysqli_fetch_assoc($result)){
    $wallets[] = $row;
}

// Map DB wallets to the JS structure used by this UI
$wallets_for_js = [];
foreach ($wallets as $w) {
    $rawType = (string)($w['wallet_type'] ?? 'Other');
    $uiType = strtolower($rawType);
    $typeClass = 'default';
    if ($uiType === 'cash') $typeClass = 'cash';
    elseif ($uiType === 'bank') $typeClass = 'bank';
    elseif ($uiType === 'mobile') $typeClass = 'mobile';
    elseif ($uiType === 'card') {
        $brand = strtolower((string)($w['card_brand'] ?? ''));
        if ($brand === 'visa') $typeClass = 'visa';
        elseif ($brand === 'mastercard') $typeClass = 'mastercard';
        elseif ($brand === 'amex') $typeClass = 'amex';
        else $typeClass = 'card';
    }
    elseif ($uiType === 'savings') $typeClass = 'savings';

    $wallets_for_js[] = [
        'id' => (string)$w['id'],
        'name' => (string)($w['wallet_name'] ?? ''),
        'balance' => (float)($w['balance'] ?? 0),
        'type' => $typeClass,
        'dbType' => $rawType,
        'currency_code' => isset($w['currency_code']) ? (string)$w['currency_code'] : 'BDT',
        'card_brand' => isset($w['card_brand']) ? (string)$w['card_brand'] : '',
        'card_last4' => isset($w['card_last4']) ? (string)$w['card_last4'] : '',
        'isFamily' => false,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallets - Budget Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #a855f7;
            --primary-dark: #9333ea;
            --secondary: #ec4899;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --bg-gradient: linear-gradient(135deg, #faf9fc 0%, #f5f3ff 50%, #ede9fe 100%);
        }

        body.dark-mode {
            --sidebar-bg: #0f0628;
            --card-bg: rgba(15, 6, 40, 0.95);
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --border: rgba(139, 92, 246, 0.25);
            --bg-gradient: linear-gradient(135deg, #0a0118 0%, #1a0b3e 50%, #0f0628 100%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 50%, rgba(236, 72, 153, 0.15) 0%, transparent 50%);
            animation: pulse 8s ease-in-out infinite;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }

        .toast {
            padding: 14px 18px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
            min-height: 50px;
        }

        .toast.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .toast.success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }

        .toast.info {
            background: #dbeafe;
            color: #0c4a6e;
            border-left: 4px solid #0284c7;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        /* Sidebar */
        .sidebar {
            width: 256px;
            background: var(--sidebar-bg);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 1.5rem;
            z-index: 50;
            border-right: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
            transition: all 0.3s ease;
        }

        body.dark-mode .logo-icon {
            background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.6);
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 900;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        body.dark-mode .logo-text {
            background: linear-gradient(135deg, #c084fc 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-item {
            padding: 0.875rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-item:hover {
            background: rgba(168, 85, 247, 0.1);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }

        .main-content {
            margin-left: 256px;
            flex: 1;
            min-height: 100vh;
        }

        .top-bar {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        body.dark-mode .top-bar {
            background: rgba(15, 6, 40, 0.8);
        }

        .search-bar {
            flex: 1;
            max-width: 600px;
        }

        .search-bar input {
            width: 100%;
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(168, 85, 247, 0.5);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: rgba(168, 85, 247, 0.1);
            border-color: var(--primary);
        }

        .btn-small {
            padding: 0.375rem 0.875rem;
            font-size: 0.875rem;
        }

        .theme-toggle {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        /* Currency Selector */
        .currency-selector {
            position: relative;
        }

        .currency-button {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 100px;
        }

        .currency-button:hover {
            border-color: var(--primary);
            background: rgba(168, 85, 247, 0.05);
        }

        .currency-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 100;
            min-width: 140px;
            overflow: hidden;
            display: none;
        }

        .currency-dropdown.show {
            display: block;
        }

        .currency-option {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
        }

        .currency-option:last-child {
            border-bottom: none;
        }

        .currency-option:hover {
            background: rgba(168, 85, 247, 0.1);
        }

        .currency-option.active {
            background: rgba(168, 85, 247, 0.15);
            color: var(--primary);
            font-weight: 700;
        }

        .content-area {
            padding: 2rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }

        /* ===== PROFESSIONAL WALLET CARD STYLES ===== */
        .wallets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 2rem;
        }

        .wallet-card {
            position: relative;
            padding: 2.15rem;
            border-radius: 20px;
            color: white;
            aspect-ratio: 1.586 / 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .wallet-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.2), 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        /* Decorative pattern overlay */
        .wallet-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 255, 255, 0.03) 10px,
                rgba(255, 255, 255, 0.03) 20px
            );
            pointer-events: none;
        }

        /* Gradient glow effect */
        .wallet-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(
                circle at center,
                rgba(255, 255, 255, 0.15) 0%,
                transparent 60%
            );
            opacity: 0.6;
            pointer-events: none;
        }

        /* Card Type Colors */
        .wallet-card.visa {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
        }

        .wallet-card.mastercard {
            background: linear-gradient(135deg, #7c2d12 0%, #ea580c 50%, #fb923c 100%);
        }

        .wallet-card.amex {
            background: linear-gradient(135deg, #0f766e 0%, #06b6d4 55%, #67e8f9 100%);
        }

        .wallet-card.cash {
            background: linear-gradient(135deg, #15803d 0%, #22c55e 50%, #4ade80 100%);
        }

        .wallet-card.bank {
            background: linear-gradient(135deg, #075985 0%, #0ea5e9 50%, #38bdf8 100%);
        }

        .wallet-card.savings {
            background: linear-gradient(135deg, #047857 0%, #10b981 50%, #34d399 100%);
        }

        .wallet-card.mobile {
            background: linear-gradient(135deg, #9f1239 0%, #ec4899 50%, #f472b6 100%);
        }

        .wallet-card.card {
            background: linear-gradient(135deg, #4c1d95 0%, #8b5cf6 50%, #a78bfa 100%);
        }

        .wallet-card.default {
            background: linear-gradient(135deg, #4c1d95 0%, #8b5cf6 50%, #a78bfa 100%);
        }

        .card-header-section {
            position: relative;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .card-type-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.95;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .card-brand {
            font-size: 1.25rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.9;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .wallet-card:hover .card-actions {
            opacity: 1;
        }

        .card-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.875rem;
            backdrop-filter: blur(10px);
        }

        .card-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        /* EMV Chip - Only for non-cash wallets */
        .card-chip {
            width: 50px;
            height: 40px;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 8px;
            margin: 0.85rem 0 1.1rem 0;
            position: relative;
            overflow: hidden;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .card-chip::before {
            content: '';
            position: absolute;
            top: 6px;
            left: 6px;
            right: 6px;
            bottom: 6px;
            border: 1.5px solid rgba(0, 0, 0, 0.15);
            border-radius: 4px;
        }

        .card-chip::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            height: 60%;
            background: repeating-linear-gradient(
                90deg,
                rgba(0, 0, 0, 0.1),
                rgba(0, 0, 0, 0.1) 2px,
                transparent 2px,
                transparent 4px
            );
        }

        /* Cash wallets don't need chip */
        .wallet-card.cash .card-chip {
            display: none;
        }

        .card-number {
            font-size: 1.35rem;
            font-weight: 600;
            letter-spacing: 3px;
            margin-bottom: 1.05rem;
            font-family: 'Courier New', Courier, monospace;
            position: relative;
            z-index: 10;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            opacity: 0.95;
        }

        .card-balance-label {
            font-size: 0.7rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            font-weight: 600;
            position: relative;
            z-index: 10;
        }

        .card-balance {
            font-size: 2.25rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 10;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            line-height: 1;
        }

        .card-holder-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 10;
            margin-top: 1rem;
        }

        .card-holder {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .card-label {
            font-size: 0.625rem;
            opacity: 0.75;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 600;
        }

        .card-value {
            font-size: 0.95rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.5px;
        }

        .card-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-brand-logo {
            height: 28px;
            width: auto;
            max-width: 110px;
            object-fit: contain;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.25));
        }

        .card-brand-fallback {
            font-size: 1.1rem;
            font-weight: 900;
            letter-spacing: 1.5px;
            opacity: 0.9;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0,0,0,0.25);
        }

        .card-logo-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        /* Premium Lock */
        .premium-lock {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 20px;
        }

        .premium-lock-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: shake 2s ease-in-out infinite;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }

        .premium-lock-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
        }

        .premium-lock-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
        }

        .btn-upgrade {
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-upgrade:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.5);
        }

        .hidden {
            display: none !important;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
        }

        .modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 2rem;
        }

        /* Family modal (same style as dashboard) */
        .upgrade-content {
            text-align: center;
            padding: 2rem;
        }

        .upgrade-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
        }

        .upgrade-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .upgrade-description {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .features-list {
            list-style: none;
            text-align: left;
            margin-bottom: 2rem;
        }

        .features-list li {
            padding: 0.5rem 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .features-list li::before {
            content: '✓';
            color: var(--success);
            font-weight: bold;
            font-size: 1.125rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
        }

        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }

            .wallets-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Toast Notification Container -->
        <div id="toastContainer" class="toast-container"></div>
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-section">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 7C3 5.89543 3.89543 5 5 5H19C20.1046 5 21 5.89543 21 7V17C21 18.1046 20.1046 19 19 19H5C3.89543 19 3 18.1046 3 17V7Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 9H7.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 9H17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 13H7.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 13H17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="logo-text">BudgetTracker</div>
            </div>
            
            <ul class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    🏠 Home
                </a>
                <a href="category.php" class="nav-item">
                    📁 Categories
                </a>
                <a href="wallet.php" class="nav-item active">
                    👛 Wallets
                </a>
                <a href="budget.php" class="nav-item">
                    🎯 Budgets
                </a>
                <a href="transaction.php" class="nav-item">
                    📝 Transactions
                </a>
                <a href="bill.php" class="nav-item" style="text-decoration: none;">
                    📋 Bills
                </a>
                <a href="analytics.php" class="nav-item">
                    📊 Analytics
                </a>
                <a href="dashboard.php#profile" class="nav-item">
                    👤 Profile
                </a>
                <a href="logout.php" class="nav-item" style="color: #ef4444; margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                    🚪 Logout
                </a>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-bar">
                    <input type="text" placeholder="Search wallets..." id="searchWallets" oninput="searchWallets()">
                </div>
                <div class="top-bar-actions">
                    <!-- Currency Selector -->
                    <div class="currency-selector">
                        <button class="currency-button" onclick="toggleCurrencyDropdown()">
                            <span id="currentCurrencySymbol">৳</span>
                            <span id="currentCurrencyCode">BDT</span>
                        </button>
                        <div id="currencyDropdown" class="currency-dropdown">
                            <div class="currency-option active" onclick="changeCurrency('BDT', '৳')">৳ BDT</div>
                            <div class="currency-option" onclick="changeCurrency('USD', '$')">$ USD</div>
                            <div class="currency-option" onclick="changeCurrency('EUR', '€')">€ EUR</div>
                            <div class="currency-option" onclick="changeCurrency('INR', '₹')">₹ INR</div>
                            <div class="currency-option" onclick="changeCurrency('GBP', '£')">£ GBP</div>
                            <div class="currency-option" onclick="changeCurrency('JPY', '¥')">¥ JPY</div>
                            <div class="currency-option" onclick="changeCurrency('CNY', '¥')">¥ CNY</div>
                            <div class="currency-option" onclick="changeCurrency('AUD', '$')">$ AUD</div>
                            <div class="currency-option" onclick="changeCurrency('CAD', '$')">$ CAD</div>
                        </div>
                    </div>

                    <button class="btn btn-outline theme-toggle" onclick="toggleDarkMode()">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <button class="btn btn-primary" onclick="openModal('walletModal')">
                        ➕ Add Wallet
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h2 style="font-size: 2rem; font-weight: 800;">My Wallets</h2>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Manage all your payment methods and accounts</p>
                    </div>
                </div>

                <!-- Personal Wallets -->
                <div>
                    <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 1.5rem;">Personal Wallets</h3>
                    <div class="wallets-grid" id="personalWalletsGrid">
                        <!-- Wallets will be rendered here by JavaScript -->
                    </div>
                </div>

                <!-- Family Wallet Section -->
                <div style="margin-top: 2rem;">
                    <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 1.5rem;">Family Wallet</h3>
                    <div class="wallets-grid">
                        <div class="wallet-card default" style="position: relative;">
                            <div class="premium-lock">
                                <div class="premium-lock-icon">🔒</div>
                                <div class="premium-lock-text">Premium Feature</div>
                                <div class="premium-lock-subtitle">Upgrade to unlock Family Wallet</div>
                                <button class="btn-upgrade" onclick="openFamilyModal()">Upgrade Now</button>
                            </div>
                            
                            <div class="card-header-section">
                                <div class="card-type-label">Family Wallet</div>
                            </div>
                            <div class="card-chip"></div>
                            <div class="card-balance-label">Available Balance</div>
                            <div class="card-holder-info">
                                <div class="card-holder">
                                    <div class="card-label">Account Type</div>
                                    <div class="card-value">Family Account</div>
                                </div>
                                <div class="card-logo">
                                    <div class="card-logo-circle"></div>
                                    <div class="card-logo-circle"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet Modal -->
    <div id="walletModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;" id="walletModalTitle">Add Wallet</h3>
                <button onclick="closeModal('walletModal')" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Wallet Type</label>
                    <select class="form-select" id="walletType" onchange="updateWalletForm()">
                        <option value="cash">💵 Cash</option>
                        <option value="bank">🏦 Bank Account</option>
                        <option value="card">💳 Credit/Debit Card</option>
                        <option value="mobile">📱 Mobile Banking</option>
                        <option value="savings">🏦 Savings</option>
                    </select>
                </div>

                <div class="form-group hidden" id="cardGroup">
                    <label class="form-label">Card Number</label>
                    <input type="text" class="form-input" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="23" oninput="formatCardNumber(this)">
                </div>

                <div class="form-group hidden" id="mobileGroup">
                    <label class="form-label">Provider</label>
                    <select class="form-select" id="mobileProvider">
                        <option value="bKash">bKash</option>
                        <option value="Nagad">Nagad</option>
                        <option value="Rocket">Rocket</option>
                        <option value="Upay">Upay</option>
                    </select>
                </div>

                <div class="form-group hidden" id="phoneGroup">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-input" id="mobilePhone" placeholder="+880 1234 567890">
                </div>

                <div class="form-group">
                    <label class="form-label">Wallet Name</label>
                    <input type="text" class="form-input" id="walletName" placeholder="e.g., My Cash Wallet">
                </div>

                <div class="form-group">
                    <label class="form-label">Balance</label>
                    <input type="number" class="form-input" id="walletBalance" placeholder="0.00" step="0.01" value="0">
                </div>

                <button type="button" class="btn btn-primary" onclick="saveWallet()" style="width: 100%;" id="walletSaveBtn">
                    Add Wallet
                </button>
            </div>
        </div>
    </div>

    <!-- Upgrade Modal -->
    <div id="upgradeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;">Unlock Premium Features</h3>
                <button onclick="closeModal('upgradeModal')" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">👑</div>
                    <h4 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Upgrade to Premium</h4>
                    <p style="color: var(--text-secondary);">Unlock Family Wallet and other premium features</p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <div class="card" style="text-align: center; cursor: pointer; border: 2px solid var(--border);" onclick="selectPlan('monthly')">
                        <h4 style="font-weight: 700; margin-bottom: 0.5rem;">Monthly</h4>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">$9.99</div>
                        <p style="font-size: 0.875rem; color: var(--text-secondary);">per month</p>
                    </div>
                    <div class="card" style="text-align: center; cursor: pointer; border: 2px solid var(--primary); position: relative;" onclick="selectPlan('yearly')">
                        <div style="position: absolute; top: -10px; right: -10px; background: var(--success); color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">Save 40%</div>
                        <h4 style="font-weight: 700; margin-bottom: 0.5rem;">Yearly</h4>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">$59.99</div>
                        <p style="font-size: 0.875rem; color: var(--text-secondary);">per year</p>
                    </div>
                </div>

                <button class="btn btn-primary" onclick="processPurchase()" style="width: 100%;">Continue to Payment</button>
            </div>
        </div>
    </div>

    <!-- Family Modal -->
    <div id="familyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;">Family Features</h3>
                <button onclick="closeModal('familyModal')" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body upgrade-content">
                <div class="upgrade-icon">👨‍👩‍👧‍👦</div>
                <h2 class="upgrade-title">Family Features</h2>
                <p class="upgrade-description">Family is a Premium-only feature. Upgrade to unlock shared wallets.</p>

                <ul class="features-list" style="margin-bottom: 1.5rem;">
                    <li>Shared Family Wallet</li>
                    <li>Manage family expenses together</li>
                    <li>Set allowances for family members</li>
                    <li>Track shared goals</li>
                    <li>Family spending analytics</li>
                    <li>Permission controls</li>
                </ul>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; width: 100%;">
                    <button class="btn btn-outline" onclick="closeModal('familyModal'); openUpgradeModal()">
                        View Plans <span id="familyPlanPrice" style="font-weight: 700;"></span>
                    </button>
                    <button class="btn btn-primary" onclick="closeModal('familyModal'); openUpgradeModal('wallet')">
                        Pay from Wallet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="currency.js"></script>
    <script>
        // App State
        let appState = {
            wallets: <?php echo json_encode($wallets_for_js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            editingWalletId: null,
            displayCurrency: 'BDT'
        };

        const USER_FULL_NAME = <?php echo json_encode($user_full_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        let selectedPlan = 'monthly';

        // Initialize
        function init() {
            renderWallets();
            loadTheme();
            loadWalletCurrency();
        }

        function uiTypeToDbType(uiType) {
            switch ((uiType || '').toLowerCase()) {
                case 'cash': return 'Cash';
                case 'bank': return 'Bank';
                case 'mobile': return 'Mobile';
                case 'card':
                case 'visa':
                case 'mastercard':
                case 'amex':
                    return 'Card';
                case 'savings':
                    return 'Savings';
                default:
                    return 'Other';
            }
        }

        function normalizeBrandClass(brand) {
            const b = String(brand || '').toLowerCase();
            if (b === 'visa') return 'visa';
            if (b === 'mastercard') return 'mastercard';
            if (b === 'amex' || b === 'american express' || b === 'americanexpress') return 'amex';
            return 'card';
        }

        function formatMaskedCard(last4) {
            const l4 = String(last4 || '').replace(/\D/g, '').slice(-4);
            return l4 ? `•••• •••• •••• ${l4}` : '•••• •••• •••• ••••';
        }

        function getBrandLogoSrc(brandClass) {
            // You provide these files locally:
            // assets/card-logos/visa.png
            // assets/card-logos/mastercard.png
            // assets/card-logos/amex.png
            const b = String(brandClass || '').toLowerCase();
            if (b === 'visa') return 'assets/card-logos/visa.png';
            if (b === 'mastercard') return 'assets/card-logos/mastercard.png';
            if (b === 'amex') return 'assets/card-logos/amex.png';
            return '';
        }

        async function refreshWallets() {
            const res = await fetch('get_wallet.php', { cache: 'no-store' });
            const data = await res.json();
            console.log('Fetched wallets:', data);
            const wallets = Array.isArray(data) ? data : [];
            appState.wallets = wallets.map(w => {
                const rawType = (w.type || w.wallet_type || 'Other');
                const uiType = (rawType || '').toLowerCase();
                let typeClass = 'default';
                if (uiType === 'cash') typeClass = 'cash';
                else if (uiType === 'bank') typeClass = 'bank';
                else if (uiType === 'mobile') typeClass = 'mobile';
                else if (uiType === 'card') typeClass = normalizeBrandClass(w.card_brand);
                else if (uiType === 'savings') typeClass = 'savings';

                return {
                    id: String(w.id),
                    name: w.wallet_name ?? w.name ?? '',
                    balance: parseFloat(w.balance) || 0,
                    type: typeClass,
                    dbType: rawType,
                    currency_code: w.currency_code || 'BDT',
                    card_brand: w.card_brand || '',
                    card_last4: w.card_last4 || '',
                    isFamily: false
                };
            });
            console.log('Mapped wallets:', appState.wallets);
            renderWallets();
        }

        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.getElementById('themeIcon').textContent = '☀️';
            }
        }

        // Currency Management
        function loadWalletCurrency() {
            const saved = localStorage.getItem('currency_wallets');
            appState.displayCurrency = Currency.normalize(saved || Currency.DEFAULT);
            updateCurrencyDisplay();
        }

        function toggleCurrencyDropdown() {
            const dropdown = document.getElementById('currencyDropdown');
            dropdown.classList.toggle('show');
        }

        function changeCurrency(code) {
            const norm = Currency.normalize(code);
            appState.displayCurrency = norm;
            localStorage.setItem('currency_wallets', norm);
            updateCurrencyDisplay();
            renderWallets();
            toggleCurrencyDropdown();
        }

        function updateCurrencyDisplay() {
            const code = appState.displayCurrency;
            const symbols = Currency.SYMBOLS;
            document.getElementById('currentCurrencyCode').textContent = code;
            document.getElementById('currentCurrencySymbol').textContent = symbols[code] || '$';

            document.querySelectorAll('.currency-option').forEach(option => {
                option.classList.remove('active');
                if (option.textContent.includes(code)) {
                    option.classList.add('active');
                }
            });
        }

        document.addEventListener('click', function(event) {
            const currencySelector = document.querySelector('.currency-selector');
            if (currencySelector && !currencySelector.contains(event.target)) {
                document.getElementById('currencyDropdown').classList.remove('show');
            }
        });

        // Dark Mode
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('themeIcon');
            icon.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        // Modal
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            if (modalId === 'walletModal') {
                resetWalletForm();
            }
        }

        // Wallets
        function renderWallets() {
            const personalWallets = appState.wallets.filter(w => !w.isFamily);
            console.log('Rendering wallets:', personalWallets);
            
            const symbols = Currency.SYMBOLS;
            const displayCurrency = appState.displayCurrency;
            const symbol = symbols[displayCurrency] || '৳';

            const grid = document.getElementById('personalWalletsGrid');
            if (!grid) {
                console.error('personalWalletsGrid element not found!');
                return;
            }

            grid.innerHTML = personalWallets.map(wallet => {
                const cardClass = wallet.type;
                const showChip = wallet.type !== 'cash';

                const isDbCard = String(wallet.dbType || '').toLowerCase() === 'card';
                const brandClass = isDbCard ? normalizeBrandClass(wallet.card_brand) : wallet.type;
                const brandLabel = isDbCard
                    ? (brandClass === 'visa' ? 'VISA' : (brandClass === 'mastercard' ? 'MASTERCARD' : (brandClass === 'amex' ? 'AMERICAN EXPRESS' : 'CARD')))
                    : wallet.type.toUpperCase();
                const brandLogo = isDbCard ? getBrandLogoSrc(brandClass) : '';
                
                const convertedBalance = Currency.convert(wallet.balance, wallet.currency_code || 'BDT', displayCurrency);

                return `
                    <div class="wallet-card ${brandClass}">
                        <div class="card-header-section">
                            <div class="card-type-label">${isDbCard ? 'CARD' : wallet.type.toUpperCase()}</div>
                            <div class="card-actions">
                                <button class="card-action-btn" onclick="editWallet('${wallet.id}')">✏️</button>
                                <button class="card-action-btn" onclick="deleteWallet('${wallet.id}')">🗑️</button>
                            </div>
                        </div>
                        ${showChip ? '<div class="card-chip"></div>' : '<div style="height: 1rem;"></div>'}
                        ${isDbCard ? `<div class="card-number">${formatMaskedCard(wallet.card_last4)}</div>` : ''}
                        <div class="card-balance-label">Available Balance</div>
                        <div class="card-balance">${symbol}${convertedBalance.toFixed(2)}</div>
                        <div class="card-holder-info">
                            <div class="card-holder">
                                <div class="card-label">${isDbCard ? 'Card Holder' : 'Account Name'}</div>
                                <div class="card-value">${isDbCard ? (USER_FULL_NAME || wallet.name) : wallet.name}</div>
                            </div>
                            <div class="card-logo">
                                ${isDbCard && brandLogo ? `<img class="card-brand-logo" src="${brandLogo}" alt="${brandLabel}">` : `<div class="card-brand-fallback">${brandLabel}</div>`}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            console.log('Rendered', personalWallets.length, 'wallets');
        }

        function updateWalletForm() {
            const type = document.getElementById('walletType').value;
            
            // Hide all conditional fields
            document.getElementById('cardGroup').classList.add('hidden');
            document.getElementById('mobileGroup').classList.add('hidden');
            document.getElementById('phoneGroup').classList.add('hidden');
            
            // Show relevant fields based on type
            if (type === 'card') {
                document.getElementById('cardGroup').classList.remove('hidden');
            } else if (type === 'mobile') {
                document.getElementById('mobileGroup').classList.remove('hidden');
                document.getElementById('phoneGroup').classList.remove('hidden');
            }
        }

        function formatCardNumber(input) {
            const digits = String(input.value || '').replace(/\D/g, '');

            // Decide grouping + max digits
            const isAmex = /^3[47]/.test(digits);
            const isMc = /^5[1-5]/.test(digits) || /^(222[1-9]|22[3-9]|2[3-6]|27[01]|2720)/.test(digits);
            const isVisa = /^4/.test(digits);

            let maxDigits = 19;
            let groups = [4, 4, 4, 4, 3];
            if (isAmex) {
                maxDigits = 15;
                groups = [4, 6, 5];
                input.maxLength = 17; // 15 digits + 2 spaces
            } else if (isMc) {
                maxDigits = 16;
                groups = [4, 4, 4, 4];
                input.maxLength = 19; // 16 digits + 3 spaces
            } else if (isVisa) {
                // Visa can be 13/16/19; allow up to 19
                maxDigits = 19;
                groups = [4, 4, 4, 4, 3];
                input.maxLength = 23; // 19 digits + 4 spaces
            } else {
                // Unknown card brand: allow up to 19 digits
                maxDigits = 19;
                groups = [4, 4, 4, 4, 3];
                input.maxLength = 23;
            }

            const clipped = digits.slice(0, maxDigits);
            const parts = [];
            let idx = 0;
            for (const size of groups) {
                if (idx >= clipped.length) break;
                parts.push(clipped.slice(idx, idx + size));
                idx += size;
            }

            input.value = parts.join(' ');
        }

        function detectCardType(cardNumber) {
            const digits = String(cardNumber || '').replace(/\D/g, '');
            // Visa
            if (/^4\d{12}(\d{3})?(\d{3})?$/.test(digits)) return 'visa';
            // AmEx
            if (/^3[47]\d{13}$/.test(digits)) return 'amex';
            // Mastercard 51-55 or 2221-2720
            if (/^5[1-5]\d{14}$/.test(digits)) return 'mastercard';
            if (/^(222[1-9]\d{12}|22[3-9]\d{13}|2[3-6]\d{14}|27[01]\d{13}|2720\d{12})$/.test(digits)) return 'mastercard';
            return 'card';
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function saveWallet() {
            const type = document.getElementById('walletType').value;
            const name = document.getElementById('walletName').value;
            const balance = parseFloat(document.getElementById('walletBalance').value) || 0;
            const currencyCode = appState.displayCurrency || Currency.DEFAULT;
            
            if (!name) {
                showToast('Please enter a wallet name', 'error');
                return;
            }

            const isEdit = !!appState.editingWalletId;
            const url = isEdit ? 'update_wallet.php' : 'save_wallet.php';

            const payload = {
                wallet_name: name,
                wallet_type: uiTypeToDbType(type),
                balance: balance,
                currency_code: currencyCode
            };

            if (type === 'card') {
                payload.card_number = document.getElementById('cardNumber').value;
            }

            if (isEdit) {
                payload.wallet_id = parseInt(appState.editingWalletId, 10);
            }

            console.log('Sending payload:', payload);

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(r => r.json())
                .then(async (out) => {
                    console.log('Response:', out);
                    if (!out || !out.success) {
                        showToast(out?.error || 'Failed to save wallet', 'error');
                        return;
                    }
                    closeModal('walletModal');
                    showToast(isEdit ? 'Wallet updated successfully' : 'Wallet added successfully', 'success');
                    await refreshWallets();
                })
                .catch(err => {
                    console.error('Error:', err);
                    showToast('Failed to save wallet', 'error');
                });
        }

        function editWallet(walletId) {
            const wallet = appState.wallets.find(w => w.id === walletId);
            if (!wallet) return;

            appState.editingWalletId = walletId;
            
            document.getElementById('walletModalTitle').textContent = 'Edit Wallet';
            document.getElementById('walletSaveBtn').textContent = 'Update Wallet';
            
            document.getElementById('walletType').value = wallet.type === 'visa' || wallet.type === 'mastercard' ? 'card' : wallet.type;
            document.getElementById('walletName').value = wallet.name;
            document.getElementById('walletBalance').value = wallet.balance;

            // We don't store full card numbers; show last4 as placeholder.
            const cardInput = document.getElementById('cardNumber');
            cardInput.value = '';
            if (String(wallet.dbType || '').toLowerCase() === 'card' && wallet.card_last4) {
                cardInput.placeholder = formatMaskedCard(wallet.card_last4);
            } else {
                cardInput.placeholder = '1234 5678 9012 3456';
            }
            
            if (wallet.provider) {
                document.getElementById('mobileProvider').value = wallet.provider;
                document.getElementById('mobilePhone').value = wallet.phone;
            }
            
            updateWalletForm();
            openModal('walletModal');
        }

        function deleteWallet(walletId) {
            if (confirm('Are you sure you want to delete this wallet?')) {
                fetch('delete_wallet.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ wallet_id: parseInt(walletId, 10) })
                })
                    .then(r => r.json())
                    .then(async (out) => {
                        if (!out || !out.success) {
                            showToast(out?.error || 'Failed to delete wallet', 'error');
                            return;
                        }
                        showToast('Wallet deleted successfully', 'success');
                        await refreshWallets();
                    })
                    .catch(() => showToast('Failed to delete wallet', 'error'));
            }
        }

        function resetWalletForm() {
            appState.editingWalletId = null;
            document.getElementById('walletModalTitle').textContent = 'Add Wallet';
            document.getElementById('walletSaveBtn').textContent = 'Add Wallet';
            document.getElementById('walletType').value = 'cash';
            document.getElementById('walletName').value = '';
            document.getElementById('walletBalance').value = '0';
            document.getElementById('cardNumber').value = '';
            document.getElementById('cardNumber').placeholder = '1234 5678 9012 3456';
            document.getElementById('mobileProvider').value = 'bKash';
            document.getElementById('mobilePhone').value = '';
            updateWalletForm();
        }

        function searchWallets() {
            const searchTerm = document.getElementById('searchWallets').value.toLowerCase();
            const allWallets = [...appState.wallets];
            
            if (!searchTerm) {
                renderWallets();
                return;
            }

            const filteredWallets = allWallets.filter(wallet => 
                wallet.name.toLowerCase().includes(searchTerm) || 
                wallet.type.toLowerCase().includes(searchTerm) ||
                (wallet.cardNumber && wallet.cardNumber.includes(searchTerm))
            );

            // Temporarily update wallets to show filtered results
            const originalWallets = [...appState.wallets];
            appState.wallets = filteredWallets;
            renderWallets();
            appState.wallets = originalWallets;
        }

        // Premium
        function openUpgradeModal(_source) {
            openModal('upgradeModal');
        }

        function getYearlyPlanPriceLabel() {
            const usd = 59.99;
            const target = appState.displayCurrency || (window.Currency ? Currency.DEFAULT : 'BDT');
            if (window.Currency && typeof Currency.formatAmount === 'function') {
                return Currency.formatAmount(usd, 'USD', target) + '/yr';
            }
            // Fallback rate if Currency helper unavailable
            const approx = target === 'BDT' ? (usd * 122.3) : usd;
            return (target === 'BDT' ? '৳' : '$') + approx.toFixed(2) + '/yr';
        }

        function openFamilyModal() {
            const priceEl = document.getElementById('familyPlanPrice');
            if (priceEl) priceEl.textContent = getYearlyPlanPriceLabel();
            openModal('familyModal');
        }

        function selectPlan(plan) {
            selectedPlan = plan;
        }

        function processPurchase() {
            alert(`Processing ${selectedPlan} plan purchase...\n\nIn production, this would redirect to a secure payment gateway.\n\nSelected Plan: ${selectedPlan === 'monthly' ? 'Monthly ($9.99/mo)' : 'Yearly ($59.99/yr - Save 40%)'}`);
            closeModal('upgradeModal');
        }

        init();
    </script>
</body>
</html>

