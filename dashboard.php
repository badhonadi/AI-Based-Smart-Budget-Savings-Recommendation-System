<?php
session_start();
require "db.php";
require_once "schema.php";

if (!isset($_SESSION["logged"]) || $_SESSION["logged"] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Ensure membership exists if possible (falls back to general if not)
ensure_membership_column($conn);
$hasMembership = has_column($conn, 'users', 'membership');

$sql = $hasMembership
    ? "SELECT first_name, last_name, email, birth_date, membership FROM users WHERE user_id = ?"
    : "SELECT first_name, last_name, email, birth_date FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $fName = $user['first_name'];
    $lName = $user['last_name'];
    $uEmail = $user['email'];
    // Database-e column-er nam 'birthday' na hole sheta check korun
    $uBirthday = isset($user['birth_date']) ? $user['birth_date'] : ''; 
    $membership = $hasMembership && isset($user['membership']) ? (string)$user['membership'] : 'general';
    $fullName = $fName . " " . $lName;
} else {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Tracker - Complete Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="currency.js"></script>
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
            position: relative;
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

        .search-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            z-index: 999;
        }

        body.dark-mode .search-dropdown {
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
        }

        .search-section-title {
            padding: 0.65rem 0.9rem;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
            background: rgba(168, 85, 247, 0.06);
            border-bottom: 1px solid var(--border);
        }

        .search-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 0.9rem;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        body.dark-mode .search-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .search-item:hover {
            background: rgba(168, 85, 247, 0.08);
        }

        .search-item .left {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .search-item .title {
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .search-item .subtitle {
            font-size: 0.85rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .search-item .badge {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-secondary);
            background: rgba(100, 116, 139, 0.12);
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .search-empty {
            padding: 1rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
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

        .btn-secondary {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(236, 72, 153, 0.4);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
            position: relative;
        }

        .btn-outline:hover {
            background: rgba(168, 85, 247, 0.1);
            border-color: var(--primary);
        }

        .btn-small {
            padding: 0.375rem 0.875rem;
            font-size: 0.875rem;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 0.625rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.25rem;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.6);
            animation: pulseBadge 2s infinite;
        }

        @keyframes pulseBadge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .currency-selector, .theme-toggle {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.2);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-value {
            font-size: clamp(1.35rem, 2vw, 2rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            font-variant-numeric: tabular-nums;
        }

        .wallets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .wallet-card {
            position: relative;
            padding: 2rem;
            border-radius: 20px;
            color: white;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .wallet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .wallet-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            opacity: 0.5;
        }

        .wallet-card.visa {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }

        .wallet-card.mastercard {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        }

        .wallet-card.cash {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }

        .wallet-card.bank {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }

        .wallet-card.savings {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .wallet-card.mobile {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }

        .wallet-card.default {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        }

        .card-header-section {
            position: relative;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .card-type-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
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
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .card-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .card-chip {
            width: 45px;
            height: 35px;
            background: linear-gradient(135deg, #f0b90b 0%, #f8d12f 100%);
            border-radius: 6px;
            margin: 1rem 0;
            position: relative;
            overflow: hidden;
            z-index: 10;
        }

        .card-chip::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            bottom: 5px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 3px;
        }

        .card-number {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 2px;
            margin-bottom: 1rem;
            font-family: 'Courier New', monospace;
            position: relative;
            z-index: 10;
        }

        .card-balance {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 10;
        }

        .card-holder-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 10;
        }

        .card-holder {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .card-label {
            font-size: 0.625rem;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-value {
            font-size: 0.875rem;
            font-weight: 600;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-input, .form-select, .form-textarea {
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

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background: var(--border);
            border-radius: 6px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
        }

        .hidden {
            display: none !important;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--success);
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            animation: pulseDot 2s infinite;
        }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .ai-insight {
            padding: 1.5rem;
            border-left: 4px solid;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .ai-insight.warning {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.1);
        }

        .ai-insight.success {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.1);
        }

        .ai-insight.info {
            border-color: var(--info);
            background: rgba(59, 130, 246, 0.1);
        }

        .bill-item {
            padding: 0.75rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bill-item.highlighted {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.05);
        }

        .bill-item.overdue {
            border-color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
        }

        .bill-status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }

        .bill-status-badge.paid {
            background: rgba(16, 185, 129, 0.12);
            color: var(--success);
        }

        .bill-status-badge.pending {
            background: rgba(245, 158, 11, 0.12);
            color: var(--warning);
        }

        .bill-status-badge.failed {
            background: rgba(239, 68, 68, 0.12);
            color: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

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

        .pricing-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .pricing-card {
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .pricing-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.2);
        }

        .pricing-card.selected {
            border-color: var(--primary);
            background: rgba(168, 85, 247, 0.05);
        }

        .pricing-badge {
            display: inline-block;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .pricing-plan {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .pricing-price {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .pricing-period {
            font-size: 0.875rem;
            color: var(--text-secondary);
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

        /* Calendar Styles */
        .calendar-container {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-month {
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--text-primary);
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .calendar-nav-btn {
            background: rgba(168, 85, 247, 0.1);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .calendar-nav-btn:hover {
            background: var(--primary);
            color: white;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .calendar-weekday {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            padding: 0.5rem;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-primary);
            position: relative;
        }

        .calendar-day:hover {
            background: rgba(168, 85, 247, 0.1);
        }

        .calendar-day.other-month {
            color: var(--text-secondary);
            opacity: 0.3;
        }

        .calendar-day.today {
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
            color: white;
            font-weight: 700;
        }

        .calendar-day.has-bill {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid var(--danger);
            font-weight: 700;
        }

        .calendar-day.has-bill::after {
            content: '';
            position: absolute;
            bottom: 2px;
            width: 4px;
            height: 4px;
            background: var(--danger);
            border-radius: 50%;
        }

        .calendar-day.today.has-bill {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }

            .pricing-cards {
                grid-template-columns: 1fr;
            }

            .wallets-grid {
                grid-template-columns: 1fr;
            }

            #dashboard-page > div[style*="grid-template-columns: 1fr 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
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
                <li class="nav-item active" id="nav-dashboard" onclick="navigateTo('dashboard'); return false;">
                    🏠 Home
                </li>
                <a href="category.php" class="nav-item" style="text-decoration: none;">
                    📁 Categories
                </a>
                <a href="wallet.php" class="nav-item" style="text-decoration: none;">
                    👛 Wallets
                </a>
                <a href="budget.php" class="nav-item" style="text-decoration: none;">
                    🎯 Budgets
                </a>
                <a href="transaction.php" class="nav-item" style="text-decoration: none;">
                    📝 Transactions
                </a>
                <a href="bill.php" class="nav-item" style="text-decoration: none;">
                    📋 Bills
                </a>
                <a href="analytics.php" class="nav-item" id="nav-analytics" onclick="navigateTo('analytics'); return false;">
                    📊 Analytics
                </a>
                <a href="#" class="nav-item" id="nav-profile" onclick="navigateTo('profile'); return false;">
                    👤 Profile
                </a>
                <a href="logout.php" class="nav-item" style="text-decoration: none; margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                    🚪 Logout
                </a>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-bar">
                    <input id="globalSearchInput" type="text" placeholder="Search transactions, categories..." autocomplete="off">
                    <div id="globalSearchDropdown" class="search-dropdown hidden" aria-label="Search results"></div>
                </div>
                <div class="top-bar-actions">
                    <a href="transaction.php" class="btn btn-primary btn-small" style="text-decoration: none;">
                        ➕ Transaction
                    </a>
                    <a href="bill.php" class="btn btn-secondary btn-small" style="text-decoration: none;">
                        📋 Bill
                    </a>
                    <select class="currency-selector" id="currencySelector" onchange="changeCurrency()">
                        <option value="BDT">৳ BDT</option>
                        <option value="USD">$ USD</option>
                        <option value="EUR">€ EUR</option>
                        <option value="INR">₹ INR</option>
                        <option value="GBP">£ GBP</option>
                        <option value="JPY">¥ JPY</option>
                        <option value="CNY">¥ CNY</option>
                        <option value="AUD">$ AUD</option>
                        <option value="CAD">$ CAD</option>
                    </select>
                    <button class="btn btn-outline theme-toggle" onclick="toggleDarkMode()">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <button class="btn btn-outline btn-small" onclick="window.location.href='notification.php'" style="position: relative;">
                        🔔
                        <span id="notificationBadge" class="notification-badge" style="display: none;"></span>
                    </button>
                    <button class="btn btn-outline" onclick="openFamilyModal()">
                        👨‍👩‍👧‍👦 Family
                    </button>
                    <button class="btn btn-primary" onclick="openUpgradeModal()" id="upgradeCta">
                        👑 Upgrade <span id="upgradePriceBadge" style="font-weight: 700;">৳0.00</span>
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Dashboard Page -->
                <div id="dashboard-page" class="page">
                    <div class="card" style="background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%); color: white; border: none;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">
                                    Welcome back, <span id="userName"><?php echo htmlspecialchars($fName); ?></span>! 👋
                                </h2>
                                <p style="color: rgba(255,255,255,0.8);">Here's what's happening with your finances today.</p>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.5rem; font-weight: 800;" id="currentTime"></div>
                                <div style="font-size: 0.875rem; opacity: 0.9;" id="currentDate"></div>
                                <div class="live-indicator" style="margin-top: 0.5rem; background: rgba(255,255,255,0.2);">
                                    <span class="live-dot"></span>
                                    Live
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <span style="font-size: 0.875rem; color: var(--text-secondary); font-weight: 600;">Total Income</span>
                                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">📈</div>
                            </div>
                            <div class="stat-value" id="totalIncome">$0.00</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);">This month</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <span style="font-size: 0.875rem; color: var(--text-secondary); font-weight: 600;">Total Expenses</span>
                                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">📉</div>
                            </div>
                            <div class="stat-value" id="totalExpenses">$0.00</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);">This month</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <span style="font-size: 0.875rem; color: var(--text-secondary); font-weight: 600;">Balance</span>
                                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">💰</div>
                            </div>
                            <div class="stat-value" id="totalBalance">$0.00</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);">Current balance</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <span style="font-size: 0.875rem; color: var(--text-secondary); font-weight: 600;">Savings</span>
                                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">🏦</div>
                            </div>
                            <div class="stat-value" id="totalSavings">$0.00</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);">Total saved</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                        <div class="card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h3 style="font-weight: 700;">📋 Upcoming Bills</h3>
                                <a href="bill.php" class="btn btn-outline btn-small" style="text-decoration: none;">
                                    ➕
                                </a>
                            </div>
                            <div id="billsList"></div>
                        </div>

                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h3 style="font-weight: 700;">📅 Calendar</h3>
                                <div class="calendar-nav">
                                    <button class="calendar-nav-btn" onclick="changeMonth(-1)">◀</button>
                                    <button class="calendar-nav-btn" onclick="changeMonth(1)">▶</button>
                                </div>
                            </div>
                            <div class="calendar-month" id="calendarMonth"></div>
                            <div class="calendar-weekdays">
                                <div class="calendar-weekday">Sun</div>
                                <div class="calendar-weekday">Mon</div>
                                <div class="calendar-weekday">Tue</div>
                                <div class="calendar-weekday">Wed</div>
                                <div class="calendar-weekday">Thu</div>
                                <div class="calendar-weekday">Fri</div>
                                <div class="calendar-weekday">Sat</div>
                            </div>
                            <div class="calendar-days" id="calendarDays"></div>
                        </div>

                        <div class="card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h3 style="font-weight: 700;">🎯 Budget Goals</h3>
                                <a href="budget.php" class="btn btn-primary btn-small" style="text-decoration: none;">Manage</a>
                            </div>
                            <div id="budgetsList"></div>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom: 2rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 900;">🤖 AI Coach</h3>
                                <p style="color: var(--text-secondary); margin-top: 0.25rem;">Where to spend less, how much to save, and simple investing ideas</p>
                            </div>
                            <div style="display:flex; gap: 0.5rem;">
                                <button class="btn btn-outline btn-small" onclick="generateAIInsights('aiInsightsDashboard', true)">Refresh</button>
                                <a href="#" class="btn btn-primary btn-small" onclick="navigateTo('analytics'); return false;" style="text-decoration:none;">Open Analytics</a>
                            </div>
                        </div>
                        <div id="aiInsightsDashboard"></div>
                    </div>

                    <div class="card" style="margin-bottom: 2rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 900;">📈 End‑of‑Month Forecast</h3>
                                <p style="color: var(--text-secondary); margin-top: 0.25rem;">Predicts your month-end income/expense and highlights risk</p>
                            </div>
                            <div style="display:flex; gap: 0.5rem;">
                                <button class="btn btn-outline btn-small" onclick="generateAIForecast(true)">Refresh</button>
                            </div>
                        </div>
                        <div id="aiForecast"></div>
                    </div>

                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="font-size: 1.5rem; font-weight: 800;">My Wallets</h3>
                            <a href="wallet.php" class="btn btn-primary" style="text-decoration: none;">
                                ➕ Manage Wallets
                            </a>
                        </div>
                        <div class="wallets-grid" id="walletsGrid"></div>
                    </div>
                </div>

                <!-- Analytics Page -->
                <div id="analytics-page" class="page hidden">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <div>
                            <h2 style="font-size: 2rem; font-weight: 800;">Analytics & Insights</h2>
                            <p style="color: var(--text-secondary); margin-top: 0.5rem;">AI-powered financial insights to help you save smarter</p>
                        </div>
                        <div style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%); color: white; border-radius: 12px; font-weight: 700;">
                            🧠 AI Powered
                        </div>
                    </div>

                    <div>
                        <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem;">🧠 AI-Powered Insights</h3>
                        <div id="aiInsights"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem;">
                        <div class="card">
                            <h3 style="font-weight: 700; margin-bottom: 1rem;">Expenses by Category</h3>
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="card">
                            <h3 style="font-weight: 700; margin-bottom: 1rem;">Income vs Expenses</h3>
                            <canvas id="incomeExpenseChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Profile Page -->
                <div id="profile-page" class="page hidden">
                    <div style="margin-bottom: 2rem;">
                        <h2 style="font-size: 2rem; font-weight: 800;">Profile Settings</h2>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Manage your account information and preferences</p>
                    </div>

                    <div class="card">
                        <div class="profile-header">
                            <h3 style="font-size: 1.5rem; font-weight: 800; margin-top: 1rem;" id="profileName">
                                <?php echo htmlspecialchars($fullName); ?>
                            </h3>
                            <p style="color: var(--text-secondary);" id="profileEmail">
                                <?php echo htmlspecialchars($uEmail); ?>
                            </p>
                        </div>

                        <form action="update_profile.php" method="POST">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($fName); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($lName); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($uEmail); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Version</label>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars(ucfirst($membership)); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Current Password (required to change password)</label>
                                <input type="password" name="current_password" class="form-input" placeholder="Enter current password">
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-input" placeholder="Leave blank to keep old password">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-input" placeholder="Re-enter new password">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Birthday</label>
                                <input type="date" name="birth_date" class="form-input" value="<?php echo htmlspecialchars($uBirthday); ?>">
                            </div>

                            <button type="submit" name="update_btn" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upgrade Modal -->
    <div id="upgradeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;">Upgrade to Premium</h3>
                <button onclick="closeModal('upgradeModal')" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body upgrade-content">
                <div class="upgrade-icon">👑</div>
                <h2 class="upgrade-title">Unlock Premium Features</h2>
                <p class="upgrade-description" id="upgradePriceLead">Choose a plan that works best for you</p>

                <div class="pricing-cards">
                    <div class="pricing-card" id="monthlyPlan" onclick="selectPlan('monthly')">
                        <div class="pricing-plan">Monthly</div>
                        <div class="pricing-price">$9.99<span class="pricing-period">/mo</span></div>
                    </div>
                    <div class="pricing-card selected" id="yearlyPlan" onclick="selectPlan('yearly')">
                        <span class="pricing-badge">Save 40%</span>
                        <div class="pricing-plan">Yearly</div>
                        <div class="pricing-price">$59.99<span class="pricing-period">/yr</span></div>
                    </div>
                </div>

                <div style="margin: 1rem 0; text-align: center;">
                    <div style="font-weight: 700; font-size: 1.2rem;" id="selectedPrice">$59.99/yr</div>
                    <div style="color: var(--text-secondary); font-size: 0.9rem;" id="selectedPriceLocal">৳0.00</div>
                </div>

                <ul class="features-list">
                    <li>Family Wallet Access</li>
                    <li>Unlimited Cards & Wallets</li>
                    <li>Advanced Analytics</li>
                    <li>Priority Support</li>
                    <li>Export Reports</li>
                    <li>Custom Categories</li>
                </ul>

                <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
                    <button class="btn btn-primary" style="width: 100%;" onclick="processPurchase('card')" id="cardPurchaseBtn">
                        Upgrade with Card — <span id="selectedPriceButton">$59.99/yr</span>
                    </button>

                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="height: 1px; background: var(--border); flex: 1;"></div>
                        <span style="color: var(--text-secondary); font-size: 0.85rem;">or pay from wallet</span>
                        <div style="height: 1px; background: var(--border); flex: 1;"></div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <select id="walletSelect" class="form-input"></select>
                        <button class="btn btn-secondary" style="width: 100%;" onclick="payWithWallet()" id="walletPayBtn">
                            Pay from Wallet — <span id="walletPrice">$59.99/yr</span>
                        </button>
                        <div id="walletBalanceHint" style="color: var(--text-secondary); font-size: 0.85rem;"></div>
                    </div>
                </div>
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
                <p class="upgrade-description" id="familyStatus">Premium-only: unlock to use shared wallets.</p>

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
                        View Plans <span id="familyPrice" style="font-weight: 700;">$59.99/yr</span>
                    </button>
                    <button class="btn btn-primary" onclick="closeModal('familyModal'); openUpgradeModal('wallet')">
                        Pay from Wallet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // App State - PHP এবং Database থেকে ডেটা আনা হচ্ছে
    let appState = {
        currentPage: 'dashboard',
        currentCurrency: (window.Currency && Currency.getCurrency()) ? Currency.getCurrency() : 'BDT',
        categories: [
            { icon: '🍕', name: 'Food & Dining', type: 'expense', subcategories: ['Restaurant', 'Fast Food', 'Groceries', 'Coffee Shop', 'Bakery'] },
            { icon: '🛒', name: 'Shopping', type: 'expense', subcategories: ['Clothing', 'Electronics', 'Books', 'Gifts'] },
            { icon: '🚗', name: 'Transportation', type: 'expense', subcategories: ['Bus', 'Train', 'Uber', 'Fuel', 'Parking'] },
            { icon: '🏠', name: 'Housing', type: 'expense', subcategories: ['Rent', 'Mortgage', 'Property Tax'] },
            { icon: '⚡', name: 'Bills & Utilities', type: 'expense', subcategories: ['Electricity', 'Water', 'Internet', 'Phone'] },
            { icon: '🏥', name: 'Healthcare', type: 'expense', subcategories: ['Doctor', 'Pharmacy', 'Hospital'] },
            { icon: '🎓', name: 'Education', type: 'expense', subcategories: ['Tuition', 'Books', 'Courses'] },
            { icon: '🎬', name: 'Entertainment', type: 'expense', subcategories: ['Movies', 'Games', 'Streaming'] },
            { icon: '💰', name: 'Salary & Wages', type: 'income', subcategories: ['Monthly Salary', 'Bonus', 'Overtime'] },
            { icon: '💼', name: 'Business', type: 'income', subcategories: ['Sales', 'Services', 'Freelance'] },
            { icon: '📈', name: 'Investments', type: 'income', subcategories: ['Dividends', 'Interest', 'Stocks'] },
            { icon: '🎁', name: 'Gifts Received', type: 'income', subcategories: ['Birthday', 'Wedding', 'Festival'] }
        ],
        dbCategories: [],
        // Transactions will be loaded from DB via get_transactions.php
        transactions: [],
        wallets: <?php 
            $w_sql = "SELECT wallet_id AS id, wallet_name as name, balance, wallet_type as type, currency_code
                      FROM wallets
                      WHERE user_id = $user_id";
            $w_res = mysqli_query($conn, $w_sql);
            $w_data = [];
            while($r = mysqli_fetch_assoc($w_res)) {
                // কার্ড ডিজাইনের জন্য কিছু ডিফল্ট গ্রাডিয়েন্ট
                $r['gradient'] = ($r['type'] == 'Bank' || $r['type'] == 'Card') ? 'linear-gradient(135deg, #6366f1, #a855f7)' : 'linear-gradient(135deg, #10b981, #059669)';
                if (!isset($r['currency_code']) || $r['currency_code'] === '') {
                    $r['currency_code'] = 'BDT';
                }
                $w_data[] = $r;
            }
            echo json_encode($w_data);
        ?>,
        budgets: <?php 
            require_once 'currency_util.php';
            $b_sql = "
                SELECT
                    bi.budget_item_id,
                    c.category_id,
                    c.category_name,
                    bi.amount_limit as targetAmount,
                    bi.currency_code,
                    bi.manual_progress,
                    bi.wallet_id,
                    w.balance as wallet_balance,
                    w.currency_code as wallet_currency,
                    b.start_date,
                    b.end_date as deadline
                FROM budgets b
                JOIN budget_items bi ON bi.budget_id = b.budget_id
                JOIN categories c ON c.category_id = bi.category_id
                LEFT JOIN wallets w ON w.wallet_id = bi.wallet_id
                WHERE b.user_id = $user_id
                ORDER BY b.end_date DESC
            ";
            $b_res = mysqli_query($conn, $b_sql);
            $b_data = [];
            while($r = mysqli_fetch_assoc($b_res)) {
                $icon = '🎯';
                $name = $r['category_name'];
                if (preg_match('/^(\X)\s+(.+)$/u', $name, $m)) {
                    $icon = $m[1];
                    $name = $m[2];
                }
                
                $budgetCurrency = normalize_currency((string)($r['currency_code'] ?? 'BDT'));
                $currentAmount = 0.0;
                $manual = (float)($r['manual_progress'] ?? 0);
                
                // Add manual progress (stored in BDT)
                $currentAmount += convert_amount($manual, 'BDT', $budgetCurrency);
                
                // Check if wallet is linked
                if (!empty($r['wallet_id']) && $r['wallet_balance'] !== null) {
                    // Add wallet balance to progress
                    $walletCurrency = normalize_currency((string)($r['wallet_currency'] ?? 'BDT'));
                    $currentAmount += convert_amount((float)$r['wallet_balance'], $walletCurrency, $budgetCurrency);
                }
                
                // If no wallet linked, fallback to transaction expenses
                if (empty($r['wallet_id'])) {
                    $startDateTime = $r['start_date'] . ' 00:00:00';
                    $endDateTime = $r['deadline'] . ' 23:59:59';
                    
                    $txnStmt = $conn->prepare("SELECT amount, currency_code FROM transactions WHERE user_id = ? AND category_id = ? AND transaction_type = 'Expense' AND transaction_time >= ? AND transaction_time <= ?");
                    $txnStmt->bind_param("iiss", $user_id, $r['category_id'], $startDateTime, $endDateTime);
                    $txnStmt->execute();
                    $txnRes = $txnStmt->get_result();
                    if ($txnRes) {
                        while ($tx = $txnRes->fetch_assoc()) {
                            $currentAmount += convert_amount((float)$tx['amount'], normalize_currency((string)($tx['currency_code'] ?? 'BDT')), $budgetCurrency);
                        }
                    }
                }
                
                $targetAmount = (float)$r['targetAmount'];
                $progress = $targetAmount > 0 ? ($currentAmount / $targetAmount) * 100 : 0;
                
                // Only include budgets that are NOT 100% complete
                if ($progress < 100) {
                    $b_data[] = [
                        'name' => $name,
                        'targetAmount' => $targetAmount,
                        'currentAmount' => $currentAmount,
                        'icon' => $icon,
                        'deadline' => $r['deadline']
                    ];
                }
            }
            echo json_encode($b_data);
        ?>,
        bills: <?php 
            // spendeeapp.sql schema uses bill_payments + utility_billers
            $bi_sql = "
                SELECT ub.biller_name as name, bp.amount, DATE(bp.payment_time) as dueDate, bp.status, '📋' as icon
                FROM bill_payments bp
                JOIN utility_billers ub ON ub.biller_id = bp.biller_id
                WHERE bp.user_id = $user_id
                ORDER BY bp.payment_time DESC
                LIMIT 50
            ";
            $bi_res = mysqli_query($conn, $bi_sql);
            $bi_data = [];
            if ($bi_res) {
                while($r = mysqli_fetch_assoc($bi_res)) $bi_data[] = $r;
            }
            echo json_encode($bi_data);
        ?>,
        userProfile: {
            firstName: '<?php echo $fName; ?>',
            lastName: '<?php echo $lName; ?>',
            email: '<?php echo $uEmail; ?>',
            membership: '<?php echo htmlspecialchars($membership, ENT_QUOTES); ?>'
        }
    };

    let selectedPlan = 'yearly';

    function getPlanPriceUSD(plan = 'yearly') {
        return plan === 'monthly' ? 9.99 : 59.99;
    }

    function getPlanPriceBDT(plan = 'yearly') {
        const usdPrice = getPlanPriceUSD(plan);
        if (window.Currency && typeof Currency.convert === 'function') {
            return Currency.convert(usdPrice, 'USD', 'BDT');
        }
        return usdPrice * 122.3;
    }

    function formatPlanPrice(plan = 'yearly', targetCurrency) {
        const usdPrice = getPlanPriceUSD(plan);
        const suffix = plan === 'monthly' ? '/mo' : '/yr';
        if (window.Currency && typeof Currency.formatAmount === 'function') {
            return Currency.formatAmount(usdPrice, 'USD', targetCurrency || appState.currentCurrency) + suffix;
        }
        return '$' + usdPrice.toFixed(2) + suffix;
    }

    function updateUpgradePriceUI() {
        const plan = selectedPlan || 'yearly';
        const priceLabel = formatPlanPrice(plan, appState.currentCurrency);
        const localLabel = formatPlanPrice(plan, 'BDT');

        const selectedPriceEl = document.getElementById('selectedPrice');
        const selectedPriceBtn = document.getElementById('selectedPriceButton');
        const walletPriceEl = document.getElementById('walletPrice');
        const upgradeLead = document.getElementById('upgradePriceLead');
        const localPriceEl = document.getElementById('selectedPriceLocal');
        const upgradeBadge = document.getElementById('upgradePriceBadge');
        const familyPrice = document.getElementById('familyPrice');

        if (selectedPriceEl) selectedPriceEl.textContent = priceLabel;
        if (selectedPriceBtn) selectedPriceBtn.textContent = priceLabel;
        if (walletPriceEl) walletPriceEl.textContent = priceLabel;
        if (upgradeLead) upgradeLead.textContent = `Premium for ${priceLabel}`;
        if (localPriceEl) localPriceEl.textContent = `≈ ${localLabel}`;
        if (upgradeBadge) upgradeBadge.textContent = localLabel;
        if (familyPrice) familyPrice.textContent = priceLabel;
    }

    function populateWalletSelect() {
        const sel = document.getElementById('walletSelect');
        const hint = document.getElementById('walletBalanceHint');
        if (!sel) return;
        sel.innerHTML = '<option value="">Select wallet</option>';
        (appState.wallets || []).forEach(w => {
            const bal = parseFloat(w.balance) || 0;
            const currency = w.currency_code || 'BDT';
            const converted = window.Currency ? Currency.convert(bal, currency, appState.currentCurrency) : bal;
            const symbol = (window.Currency && Currency.SYMBOLS) ? (Currency.SYMBOLS[appState.currentCurrency] || '') : '';
            const option = document.createElement('option');
            option.value = w.id;
            option.textContent = `${w.name} • ${symbol}${(converted || 0).toFixed(2)} (${currency})`;
            sel.appendChild(option);
        });
        if (hint) {
            hint.textContent = 'Choose a wallet to pay directly from your balance.';
            hint.style.color = 'var(--text-secondary)';
        }
        sel.onchange = updateWalletBalanceHint;
    }

    function updateWalletBalanceHint() {
        const sel = document.getElementById('walletSelect');
        const hint = document.getElementById('walletBalanceHint');
        if (!sel || !hint) return;
        const wallet = (appState.wallets || []).find(w => String(w.id) === String(sel.value));
        if (!wallet) {
            hint.textContent = 'Choose a wallet to pay directly from your balance.';
            hint.style.color = 'var(--text-secondary)';
            return;
        }
        const balanceBDT = window.Currency ? Currency.convert(parseFloat(wallet.balance) || 0, wallet.currency_code || 'BDT', 'BDT') : parseFloat(wallet.balance) || 0;
        const priceBDT = getPlanPriceBDT(selectedPlan || 'yearly');
        const hasFunds = balanceBDT >= priceBDT;
        const balanceDisplay = window.Currency ? Currency.formatAmount(wallet.balance || 0, wallet.currency_code || 'BDT', appState.currentCurrency) : `${wallet.balance} ${wallet.currency_code || 'BDT'}`;
        const priceDisplay = window.Currency ? Currency.formatAmount(priceBDT, 'BDT', wallet.currency_code || 'BDT') : `${priceBDT.toFixed(2)} BDT`;
        hint.textContent = hasFunds
            ? `Available: ${balanceDisplay}. This will charge about ${priceDisplay}.`
            : `Insufficient funds. You need about ${priceDisplay}.`;
        hint.style.color = hasFunds ? 'var(--text-secondary)' : 'var(--danger)';
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('show');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('show');
    }

    function applyCurrencyToSelector() {
        const sel = document.getElementById('currencySelector');
        if (!sel) return;
        sel.value = appState.currentCurrency;
    }

    function changeCurrency() {
        const sel = document.getElementById('currencySelector');
        if (!sel) return;
        const code = sel.value;
        if (window.Currency) {
            appState.currentCurrency = Currency.setCurrency(code);
        } else {
            appState.currentCurrency = code || 'BDT';
            localStorage.setItem('currency', appState.currentCurrency);
        }
        renderDashboard();
        // Re-render dropdown amounts in current currency
        refreshSearchDropdownIfOpen();
        updateUpgradePriceUI();
        updateWalletBalanceHint();
        if (appState.currentPage === 'analytics') {
            renderAnalytics();
        }
    }

    // --- Global Search (Dashboard Top Bar) ---
    function setupGlobalSearch() {
        const input = document.getElementById('globalSearchInput');
        const dropdown = document.getElementById('globalSearchDropdown');
        if (!input || !dropdown) return;

        let debounceTimer = null;
        let lastQuery = '';

        function hide() {
            dropdown.classList.add('hidden');
            dropdown.innerHTML = '';
        }

        function show() {
            dropdown.classList.remove('hidden');
        }

        function normalize(s) {
            return String(s || '').toLowerCase().trim();
        }

        function matches(term, ...fields) {
            const q = normalize(term);
            if (!q) return false;
            return fields.some(f => normalize(f).includes(q));
        }

        function buildSection(title) {
            const el = document.createElement('div');
            el.className = 'search-section-title';
            el.textContent = title;
            return el;
        }

        function buildItem(href, title, subtitle, badge) {
            const a = document.createElement('a');
            a.className = 'search-item';
            a.href = href;

            const left = document.createElement('div');
            left.className = 'left';

            const t = document.createElement('div');
            t.className = 'title';
            t.textContent = title;
            left.appendChild(t);

            if (subtitle) {
                const s = document.createElement('div');
                s.className = 'subtitle';
                s.textContent = subtitle;
                left.appendChild(s);
            }

            a.appendChild(left);

            if (badge) {
                const b = document.createElement('div');
                b.className = 'badge';
                b.textContent = badge;
                a.appendChild(b);
            }
            return a;
        }

        function formatTxnBadge(t) {
            const amt = parseFloat(t.amount) || 0;
            const cur = t.currency || 'BDT';
            const converted = window.Currency ? Currency.convert(amt, cur, appState.currentCurrency) : amt;
            const sign = t.type === 'expense' ? '-' : '+';
            return `${sign}${formatCurrencyAmount(converted)}`;
        }

        function computeResults(term) {
            const q = normalize(term);
            const limit = 6;
            if (!q) {
                return { transactions: [], categories: [], wallets: [], budgets: [], bills: [] };
            }

            const txns = (appState.transactions || []).filter(t =>
                matches(q, t.description, t.notes, t.categoryName, t.type, t.amount, t.currency)
            ).slice(0, limit);

            const catsSource = (appState.dbCategories && appState.dbCategories.length)
                ? appState.dbCategories
                : (appState.categories || []);
            const cats = catsSource.filter(c => matches(q, c.name, c.icon, c.type)).slice(0, limit);

            const wallets = (appState.wallets || []).filter(w => matches(q, w.name, w.type, w.currency_code)).slice(0, limit);
            const budgets = (appState.budgets || []).filter(b => matches(q, b.name, b.icon, b.targetAmount, b.currentAmount)).slice(0, limit);
            const bills = (appState.bills || []).filter(b => matches(q, b.name, b.status, b.dueDate, b.amount)).slice(0, limit);

            return { transactions: txns, categories: cats, wallets, budgets, bills };
        }

        function render(term) {
            const res = computeResults(term);
            dropdown.innerHTML = '';

            const total = (res.transactions.length + res.categories.length + res.wallets.length + res.budgets.length + res.bills.length);
            if (!total) {
                const empty = document.createElement('div');
                empty.className = 'search-empty';
                empty.textContent = 'No matches found.';
                dropdown.appendChild(empty);
                show();
                return;
            }

            // Transactions
            if (res.transactions.length) {
                dropdown.appendChild(buildSection('Transactions'));
                res.transactions.forEach(t => {
                    const title = (t.description || '(No description)');
                    const subtitle = [t.categoryName ? `Category: ${t.categoryName}` : null, t.date ? `Date: ${t.date}` : null].filter(Boolean).join(' • ');
                    dropdown.appendChild(buildItem('transaction.php', title, subtitle, formatTxnBadge(t)));
                });
            }

            // Categories
            if (res.categories.length) {
                dropdown.appendChild(buildSection('Categories'));
                res.categories.forEach(c => {
                    const title = `${c.icon ? c.icon + ' ' : ''}${c.name || ''}`.trim();
                    const subtitle = c.type ? String(c.type).toUpperCase() : '';
                    dropdown.appendChild(buildItem('category.php', title || 'Category', subtitle, 'Open'));
                });
            }

            // Wallets
            if (res.wallets.length) {
                dropdown.appendChild(buildSection('Wallets'));
                res.wallets.forEach(w => {
                    const title = w.name || 'Wallet';
                    const subtitle = w.type ? String(w.type).toUpperCase() : '';
                    dropdown.appendChild(buildItem('wallet.php', title, subtitle, 'Open'));
                });
            }

            // Budgets
            if (res.budgets.length) {
                dropdown.appendChild(buildSection('Budgets'));
                res.budgets.forEach(b => {
                    const title = `${b.icon ? b.icon + ' ' : ''}${b.name || ''}`.trim();
                    const subtitle = b.deadline ? `Deadline: ${b.deadline}` : '';
                    dropdown.appendChild(buildItem('budget.php', title || 'Budget', subtitle, 'Open'));
                });
            }

            // Bills
            if (res.bills.length) {
                dropdown.appendChild(buildSection('Bills'));
                res.bills.forEach(b => {
                    const title = `${b.icon ? b.icon + ' ' : ''}${b.name || ''}`.trim();
                    const subtitle = [b.dueDate ? `Date: ${b.dueDate}` : null, b.status ? `Status: ${b.status}` : null].filter(Boolean).join(' • ');
                    const badge = b.amount != null ? formatCurrency(b.amount, 'BDT') : 'Open';
                    dropdown.appendChild(buildItem('bill.php', title || 'Bill', subtitle, badge));
                });
            }

            show();
        }

        function scheduleRender() {
            const q = input.value;
            lastQuery = q;
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const trimmed = String(q || '').trim();
                if (!trimmed) {
                    hide();
                    return;
                }
                render(trimmed);
            }, 120);
        }

        input.addEventListener('input', scheduleRender);
        input.addEventListener('focus', scheduleRender);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hide();
                input.blur();
            }
        });

        document.addEventListener('click', (e) => {
            if (!dropdown || dropdown.classList.contains('hidden')) return;
            const searchBar = document.querySelector('.search-bar');
            if (searchBar && !searchBar.contains(e.target)) {
                hide();
            }
        });

        // Expose a refresh hook so currency changes update displayed amounts.
        window.__refreshDashboardSearch = function () {
            const trimmed = String(lastQuery || '').trim();
            if (!trimmed) return;
            if (dropdown.classList.contains('hidden')) return;
            render(trimmed);
        };
    }

    function refreshSearchDropdownIfOpen() {
        if (typeof window.__refreshDashboardSearch === 'function') {
            window.__refreshDashboardSearch();
        }
    }

    function loadDbCategoriesForSearch() {
        // Load both income + expense categories for better dashboard search results.
        return Promise.all([
            fetch('get_categories.php?type=expense', { credentials: 'same-origin' }).then(r => r.json()).catch(() => []),
            fetch('get_categories.php?type=income', { credentials: 'same-origin' }).then(r => r.json()).catch(() => []),
        ]).then(([expense, income]) => {
            const merged = ([]).concat(Array.isArray(expense) ? expense : [], Array.isArray(income) ? income : []);
            appState.dbCategories = merged;
        }).catch(() => {
            appState.dbCategories = [];
        });
    }

    // Initialize
    function init() {
        updateClock();
        setInterval(updateClock, 1000);
        applyCurrencyToSelector();
        updateUpgradePriceUI();
        populateWalletSelect();
        updateWalletBalanceHint();
        loadTheme();
        setupGlobalSearch();
        loadDbCategoriesForSearch();
        checkNotifications();
        setInterval(checkNotifications, 5000);

        // Default page
        navigateTo('dashboard');

        // Load transactions from DB
        fetch('get_transactions.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                appState.transactions = Array.isArray(data) ? data : [];
                renderDashboard();
                refreshSearchDropdownIfOpen();
                if (appState.currentPage === 'analytics') {
                    renderAnalytics();
                }
            })
            .catch(() => {
                appState.transactions = [];
                renderDashboard();
                refreshSearchDropdownIfOpen();
            });

        // অন্য পেজে ডেটা চেঞ্জ হলে অটো আপডেট হবে
        window.addEventListener('storage', () => {
            location.reload(); 
        });
    }

    function navigateTo(page) {
        const dashboardPage = document.getElementById('dashboard-page');
        const analyticsPage = document.getElementById('analytics-page');
        const profilePage = document.getElementById('profile-page');

        if (!dashboardPage || !analyticsPage || !profilePage) return;

        dashboardPage.classList.add('hidden');
        analyticsPage.classList.add('hidden');
        profilePage.classList.add('hidden');

        const navDashboard = document.getElementById('nav-dashboard');
        const navAnalytics = document.getElementById('nav-analytics');
        const navProfile = document.getElementById('nav-profile');
        [navDashboard, navAnalytics, navProfile].forEach(el => el && el.classList.remove('active'));

        if (page === 'analytics') {
            analyticsPage.classList.remove('hidden');
            if (navAnalytics) navAnalytics.classList.add('active');
            appState.currentPage = 'analytics';
            renderAnalytics();
            return;
        }

        if (page === 'profile') {
            profilePage.classList.remove('hidden');
            if (navProfile) navProfile.classList.add('active');
            appState.currentPage = 'profile';
            return;
        }

        // default
        dashboardPage.classList.remove('hidden');
        if (navDashboard) navDashboard.classList.add('active');
        appState.currentPage = 'dashboard';
        renderDashboard();
    }

    // --- আপডেটেড রেন্ডার ফাংশন ---
    function renderDashboard() {
        // Summary calculations: DB stores amounts in BDT, convert to display currency
        const totalIncomeBDT = appState.transactions
            .filter(t => t.type === 'income')
            .reduce((sum, t) => {
                const amt = parseFloat(t.amount) || 0;
                const currency = t.currency || 'BDT';
                const inBDT = window.Currency ? Currency.convert(amt, currency, 'BDT') : amt;
                return sum + inBDT;
            }, 0);

        const totalExpensesBDT = appState.transactions
            .filter(t => t.type === 'expense')
            .reduce((sum, t) => {
                const amt = parseFloat(t.amount) || 0;
                const currency = t.currency || 'BDT';
                const inBDT = window.Currency ? Currency.convert(amt, currency, 'BDT') : amt;
                return sum + inBDT;
            }, 0);

        const totalBalanceBDT = appState.wallets.reduce((sum, w) => {
            const bal = parseFloat(w.balance) || 0;
            const currency = w.currency_code || 'BDT';
            const inBDT = window.Currency ? Currency.convert(bal, currency, 'BDT') : bal;
            return sum + inBDT;
        }, 0);

        // Savings now comes only from savings wallet(s)
        const savingsBalanceBDT = appState.wallets.reduce((sum, w) => {
            const name = (w.name || '').toLowerCase();
            const type = (w.type || '').toLowerCase();
            const isSavings = type === 'savings' || name.includes('savings');
            if (!isSavings) return sum;
            const bal = parseFloat(w.balance) || 0;
            const currency = w.currency_code || 'BDT';
            const inBDT = window.Currency ? Currency.convert(bal, currency, 'BDT') : bal;
            return sum + inBDT;
        }, 0);

        // Convert BDT amounts to display currency
        const totalIncome = window.Currency ? Currency.convert(totalIncomeBDT, 'BDT', appState.currentCurrency) : totalIncomeBDT;
        const totalExpenses = window.Currency ? Currency.convert(totalExpensesBDT, 'BDT', appState.currentCurrency) : totalExpensesBDT;
        const totalBalance = window.Currency ? Currency.convert(totalBalanceBDT, 'BDT', appState.currentCurrency) : totalBalanceBDT;
        const totalSavings = window.Currency ? Currency.convert(savingsBalanceBDT, 'BDT', appState.currentCurrency) : savingsBalanceBDT;

        const incomeEl = document.getElementById('totalIncome');
        const expenseEl = document.getElementById('totalExpenses');
        const balanceEl = document.getElementById('totalBalance');
        const savingsEl = document.getElementById('totalSavings');

        if (incomeEl) {
            incomeEl.textContent = formatCurrencyAmount(totalIncome);
            incomeEl.title = incomeEl.textContent;
        }
        if (expenseEl) {
            expenseEl.textContent = formatCurrencyAmount(totalExpenses);
            expenseEl.title = expenseEl.textContent;
        }
        if (balanceEl) {
            balanceEl.textContent = formatCurrencyAmount(totalBalance);
            balanceEl.title = balanceEl.textContent;
        }
        if (savingsEl) {
            savingsEl.textContent = formatCurrencyAmount(totalSavings);
            savingsEl.title = savingsEl.textContent;
        }
        document.getElementById('userName').textContent = appState.userProfile.firstName;

        renderCalendar();
        renderBillsList();
        renderBudgetsList();
        renderWalletsGrid();

        // AI cards (cached)
        generateAIInsights('aiInsightsDashboard');
        generateAIForecast();
    }

        let __aiForecastCache = { ts: 0, data: null };

        async function generateAIForecast(force = false) {
            const box = document.getElementById('aiForecast');
            if (!box) return;

            const now = Date.now();
            const age = now - (__aiForecastCache.ts || 0);
            if (!force && __aiForecastCache.data && age < 60 * 1000) {
                renderAIForecast(__aiForecastCache.data);
                return;
            }

            box.innerHTML = `
                <div class="ai-insight info">
                    <h4 style="font-weight: 700; font-size: 1.125rem; margin-bottom: 0.5rem;">Forecasting…</h4>
                    <p style="font-size: 0.875rem; color: var(--text-secondary);">Using this month pace + last month trend.</p>
                </div>
            `;

            try {
                const res = await fetch('ai_spend_forecast.php', { cache: 'no-store', credentials: 'same-origin' });
                const out = await res.json();
                if (out && out.success) {
                    __aiForecastCache = { ts: Date.now(), data: out };
                    renderAIForecast(out);
                    return;
                }
                throw new Error(out?.error || 'Invalid forecast');
            } catch (e) {
                console.warn('Forecast failed:', e);
                box.innerHTML = `
                    <div class="ai-insight info">
                        <h4 style="font-weight: 700; font-size: 1.125rem; margin-bottom: 0.5rem;">Forecast unavailable</h4>
                        <p style="font-size: 0.875rem; color: var(--text-secondary);">Add more transactions to improve forecast.</p>
                    </div>
                `;
            }
        }

        function renderAIForecast(out) {
            const box = document.getElementById('aiForecast');
            if (!box) return;

            const inc = parseFloat(out.forecast_income_bdt || 0);
            const exp = parseFloat(out.forecast_expense_bdt || 0);
            const net = parseFloat(out.forecast_net_bdt || 0);

            const status = net < 0 ? 'warning' : 'success';
            const title = net < 0 ? 'Risk: You may end negative' : 'On track: Positive month';

            const meta = `To date: ${formatCurrency(out.income_to_date_bdt || 0, 'BDT')} income • ${formatCurrency(out.expense_to_date_bdt || 0, 'BDT')} expense`;
            const proj = `Forecast: ${formatCurrency(inc, 'BDT')} income • ${formatCurrency(exp, 'BDT')} expense • Net ${formatCurrency(net, 'BDT')}`;

            const insights = Array.isArray(out.insights) ? out.insights : [];
            const insightHtml = insights.map(i => `
                <div class="ai-insight ${i.type || 'info'}">
                    <h4 style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.4rem;">${i.title || ''}</h4>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.6rem;">${i.description || ''}</p>
                    <div style="background: rgba(168, 85, 247, 0.1); padding: 0.75rem; border-radius: 8px;">
                        <p style="font-size: 0.875rem; font-weight: 600; color: var(--primary);">💡 Action: ${i.action || ''}</p>
                    </div>
                </div>
            `).join('');

            box.innerHTML = `
                <div class="ai-insight ${status}">
                    <h4 style="font-weight: 800; font-size: 1.125rem; margin-bottom: 0.4rem;">${title}</h4>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.6rem;">${meta}</p>
                    <div style="background: rgba(168, 85, 247, 0.1); padding: 0.75rem; border-radius: 8px;">
                        <p style="font-size: 0.95rem; font-weight: 800; color: var(--primary);">${proj}</p>
                    </div>
                </div>
                ${insightHtml}
            `;
        }

    function renderBillsList() {
        const billsList = document.getElementById('billsList');
        if (appState.bills.length === 0) {
            billsList.innerHTML = '<div class="empty-state">📋 No bills added yet.<br><a href="bill.php" style="color: var(--primary);">Add your first bill</a></div>';
            return;
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        billsList.innerHTML = appState.bills
            .sort((a, b) => new Date(a.dueDate) - new Date(b.dueDate))
            .slice(0, 5)
            .map(bill => {
                const dueDate = new Date(bill.dueDate);
                const daysUntilDue = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
                const isOverdue = daysUntilDue < 0 && bill.status !== 'Paid';
                const isHighlighted = daysUntilDue <= 7 && daysUntilDue >= 0;
                const statusClass = bill.status.toLowerCase();
                
                return `
                    <div class="bill-item ${isHighlighted ? 'highlighted' : ''} ${isOverdue ? 'overdue' : ''}">
                        <div style="display: flex; align-items: center; gap: 0.75rem; width: 100%;">
                            <span style="font-size: 1.5rem;">${bill.icon || '💸'}</span>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 0.875rem;">${bill.name}</div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                    Due: ${dueDate.toLocaleDateString()} ${daysUntilDue < 0 ? '(Overdue)' : `(${daysUntilDue} days)`}
                                </div>
                                <div class="bill-status-badge ${statusClass}">${bill.status}</div>
                            </div>
                        </div>
                        <div style="font-weight: 700;">${formatCurrency(bill.amount)}</div>
                    </div>
                `;
            }).join('');
    }

    function renderBudgetsList() {
        const budgetsList = document.getElementById('budgetsList');
        if (appState.budgets.length === 0) {
            budgetsList.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--text-secondary);">🎯 No budget goals yet.</div>';
            return;
        }
        budgetsList.innerHTML = appState.budgets.slice(0, 3).map(budget => {
            const target = parseFloat(budget.targetAmount) || 0;
            const current = parseFloat(budget.currentAmount) || 0;
            const progress = Math.min(100, (target > 0 ? (current / target) * 100 : 0));
            return `
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">${budget.icon || '🎯'}</span>
                            <div>
                                <div style="font-weight: 600;">${budget.name}</div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">Target: ${formatCurrency(budget.targetAmount)}</div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700;">${progress.toFixed(0)}%</div>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress}%;"></div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderWalletsGrid() {
        const walletsGrid = document.getElementById('walletsGrid');
        if (appState.wallets.length === 0) {
            walletsGrid.innerHTML = '<div class="card" style="grid-column: 1/-1; text-align: center; padding: 2rem;">No Wallets Found</div>';
            return;
        }
        walletsGrid.innerHTML = appState.wallets.map(wallet => `
            <div class="wallet-card" style="background: ${wallet.gradient};">
                <div class="card-header-section">
                    <div class="card-type-label">${wallet.type.toUpperCase()}</div>
                </div>
                <div class="card-number" style="margin-top:15px">${wallet.name}</div>
                <div class="card-balance">${formatCurrency(wallet.balance)}</div>
                <div class="card-label">CURRENT BALANCE</div>
            </div>
        `).join('');
    }

    // --- আপনার আগের ফাংশনগুলো (অপরিবর্তিত) ---

    function formatCurrencyAmount(amount) {
        if (window.Currency) {
            const symbol = Currency.SYMBOLS[appState.currentCurrency] || '৳';
            return `${symbol}${Math.abs(parseFloat(amount) || 0).toFixed(2)}`;
        }
        // Fallback
        return `৳${Math.abs(parseFloat(amount) || 0).toFixed(2)}`;
    }

    function formatCurrency(amountBDT, sourceCurrency = 'BDT') {
        if (window.Currency) {
            // Convert from source currency to display currency
            const converted = Currency.convert(amountBDT, sourceCurrency, appState.currentCurrency);
            return formatCurrencyAmount(converted);
        }
        // Fallback: no conversion
        return formatCurrencyAmount(amountBDT);
    }

    function updateClock() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        const icon = document.getElementById('themeIcon');
        icon.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    }

    function loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            if(document.getElementById('themeIcon')) document.getElementById('themeIcon').textContent = '☀️';
        }
    }

    function checkNotifications() {
        fetch('get_notifications.php?summary=1', { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                const unreadCount = data && data.unread_count ? data.unread_count : 0;
                const badge = document.getElementById('notificationBadge');
                if (badge) {
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(err => {
                console.error('Failed to check notifications:', err);
            });
    }

// Analytics
        function renderAnalytics() {
            generateAIInsights();
            renderCharts();
        }

        let __aiInsightsCache = { ts: 0, data: null };

        async function generateAIInsights(targetId = 'aiInsights', force = false) {
            const container = document.getElementById(targetId);
            if (!container) return;

            const renderInsights = (insights, metaText = '') => {
                const meta = metaText
                    ? `<div style="margin-bottom: 0.75rem; color: var(--text-secondary); font-size: 0.85rem;">${metaText}</div>`
                    : '';

                container.innerHTML = meta + (insights || []).map(insight => `
                    <div class="ai-insight ${insight.type}">
                        <h4 style="font-weight: 700; font-size: 1.125rem; margin-bottom: 0.5rem;">${insight.title}</h4>
                        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.75rem;">${insight.description}</p>
                        <div style="background: rgba(168, 85, 247, 0.1); padding: 0.75rem; border-radius: 8px;">
                            <p style="font-size: 0.875rem; font-weight: 600; color: var(--primary);">💡 Action: ${insight.action}</p>
                        </div>
                    </div>
                `).join('');
            };

            const now = Date.now();
            const cacheAge = now - (__aiInsightsCache.ts || 0);
            if (!force && __aiInsightsCache.data && cacheAge < 60 * 1000) {
                const out = __aiInsightsCache.data;
                const meta = out.gemini_used
                    ? 'Powered by Gemini AI (server-side).'
                    : 'Powered by smart rules (offline fallback).';
                renderInsights(out.insights || [], meta);
                return;
            }

            container.innerHTML = `
                <div class="ai-insight info">
                    <h4 style="font-weight: 700; font-size: 1.125rem; margin-bottom: 0.5rem;">Generating insights…</h4>
                    <p style="font-size: 0.875rem; color: var(--text-secondary);">Analyzing your monthly income, expenses, top categories and bills.</p>
                </div>
            `;

            try {
                const res = await fetch('ai_dashboard_insights.php', { cache: 'no-store', credentials: 'same-origin' });
                const out = await res.json();
                if (out && out.success && Array.isArray(out.insights) && out.insights.length > 0) {
                    __aiInsightsCache = { ts: Date.now(), data: out };
                    const meta = out.gemini_used
                        ? 'Powered by Gemini AI (server-side).'
                        : 'Powered by smart rules (offline fallback).';
                    renderInsights(out.insights, meta);
                    return;
                }
                throw new Error(out?.error || 'Invalid insights');
            } catch (e) {
                console.warn('AI insights endpoint failed, using fallback:', e);

                // Fallback: existing heuristic (client-side)
                const totalIncome = appState.transactions.filter(t => t.type === 'income').reduce((sum, t) => sum + t.amount, 0);
                const totalExpense = appState.transactions.filter(t => t.type === 'expense').reduce((sum, t) => sum + t.amount, 0);
                const savingsRate = totalIncome > 0 ? ((totalIncome - totalExpense) / totalIncome) * 100 : 0;

                const insights = [];
                if (savingsRate < 10) {
                    insights.push({
                        type: 'warning',
                        title: 'Low Savings Rate',
                        description: `You're only saving ${savingsRate.toFixed(1)}% of your income. Aim for at least 15–20% if possible.`,
                        action: 'Cut one discretionary category this week and track daily.'
                    });
                } else if (savingsRate >= 20) {
                    insights.push({
                        type: 'success',
                        title: 'Excellent Savings Rate!',
                        description: `You're saving ${savingsRate.toFixed(1)}% of your income. Keep up the great work!`,
                        action: 'Consider investing part of the surplus after building an emergency fund.'
                    });
                } else {
                    insights.push({
                        type: 'info',
                        title: 'Good Savings Progress',
                        description: `You're saving ${savingsRate.toFixed(1)}% of your income. You're on the right track!`,
                        action: 'Try to increase your savings rate by 2–5% next month.'
                    });
                }

                insights.push({
                    type: 'info',
                    title: 'EMI / Bills Tip',
                    description: 'Separate upcoming bills/EMI money so daily spending stays safe.',
                    action: 'Create a wallet named “EMI/Bills” and move money there right after income.'
                });

                renderInsights(insights, 'Fallback mode (offline).');
            }
        }

        function renderCharts() {
            const categoryExpenses = {};
            appState.transactions.filter(t => t.type === 'expense').forEach(t => {
                const name = t.categoryName || 'Uncategorized';
                categoryExpenses[name] = (categoryExpenses[name] || 0) + (parseFloat(t.amount) || 0);
            });

            const categoryLabels = Object.keys(categoryExpenses);
            const categoryData = Object.values(categoryExpenses);

            const ctx1 = document.getElementById('categoryChart');
            if (ctx1.chart) ctx1.chart.destroy();
            ctx1.chart = new Chart(ctx1, {
                type: 'pie',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryData,
                        backgroundColor: ['#a855f7', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });

            const totalIncome = appState.transactions.filter(t => t.type === 'income').reduce((sum, t) => sum + t.amount, 0);
            const totalExpense = appState.transactions.filter(t => t.type === 'expense').reduce((sum, t) => sum + t.amount, 0);

            const ctx2 = document.getElementById('incomeExpenseChart');
            if (ctx2.chart) ctx2.chart.destroy();
            ctx2.chart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Income', 'Expenses'],
                    datasets: [{
                        label: 'Amount',
                        data: [totalIncome, totalExpense],
                        backgroundColor: ['#10b981', '#ef4444']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        }


    // Upgrade
    function openUpgradeModal(entryPoint) {
        updateUpgradePriceUI();
        populateWalletSelect();
        updateWalletBalanceHint();
        openModal('upgradeModal');
        if (entryPoint === 'wallet') {
            setTimeout(() => {
                const sel = document.getElementById('walletSelect');
                if (sel) sel.focus();
            }, 50);
        }
    }

    function selectPlan(plan) {
        selectedPlan = plan;
        document.querySelectorAll('.pricing-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.getElementById(plan + 'Plan').classList.add('selected');
        updateUpgradePriceUI();
        updateWalletBalanceHint();
    }

    function processPurchase(method = 'card', walletId = null) {
        const plan = selectedPlan || 'yearly';
        const form = new URLSearchParams();
        form.set('plan', plan);
        form.set('payment_method', method);
        if (method === 'wallet' && walletId) {
            form.set('wallet_id', walletId);
        }

        fetch('upgrade_membership.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: form.toString()
        })
            .then(r => r.json())
            .then(out => {
                if (out && out.success) {
                    appState.userProfile.membership = 'premium';
                    alert('Upgraded to Premium successfully.');
                    location.reload();
                    return;
                }
                alert(out?.error || 'Upgrade failed');
            })
            .catch(() => alert('Upgrade failed'))
            .finally(() => closeModal('upgradeModal'));
    }

    function payWithWallet() {
        const sel = document.getElementById('walletSelect');
        if (!sel || !sel.value) {
            alert('Select a wallet to pay from.');
            return;
        }
        const wallet = (appState.wallets || []).find(w => String(w.id) === String(sel.value));
        if (!wallet) {
            alert('Wallet not found.');
            return;
        }
        const priceBDT = getPlanPriceBDT(selectedPlan || 'yearly');
        const walletBalanceBDT = window.Currency ? Currency.convert(parseFloat(wallet.balance) || 0, wallet.currency_code || 'BDT', 'BDT') : parseFloat(wallet.balance) || 0;
        if (walletBalanceBDT < priceBDT) {
            alert('Not enough balance in this wallet to upgrade.');
            return;
        }
        processPurchase('wallet', sel.value);
    }

    function openFamilyModal() {
        const isPremium = (appState.userProfile.membership || '').toLowerCase() === 'premium';
        const status = document.getElementById('familyStatus');
        if (status) {
            status.textContent = isPremium
                ? 'You already have Premium. Family sharing is ready to use.'
                : 'Family is a Premium-only feature. Upgrade to unlock shared wallets.';
        }
        updateUpgradePriceUI();
        openModal('familyModal');
    }

    // Calendar logic
    let currentCalendarDate = new Date();
    function renderCalendar() {
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();
        document.getElementById('calendarMonth').textContent = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentCalendarDate);
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Create a map of bill due dates (only unpaid bills)
        const billDueDates = new Set();
        (appState.bills || []).forEach(bill => {
            if (bill.status !== 'Paid') {
                const dueDate = new Date(bill.dueDate);
                if (dueDate.getMonth() === month && dueDate.getFullYear() === year) {
                    billDueDates.add(dueDate.getDate());
                }
            }
        });

        let daysHTML = '';
        for (let i = 0; i < firstDay; i++) daysHTML += `<div class="calendar-day other-month"></div>`;
        for (let day = 1; day <= daysInMonth; day++) {
            let classes = 'calendar-day';
            const isToday = new Date().getDate() === day && new Date().getMonth() === month && new Date().getFullYear() === year;
            const hasBill = billDueDates.has(day);
            
            if (isToday) classes += ' today';
            if (hasBill) classes += ' has-bill';
            
            daysHTML += `<div class="${classes}">${day}</div>`;
        }
        document.getElementById('calendarDays').innerHTML = daysHTML;
    }

    function changeMonth(dir) {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + dir);
        renderCalendar();
    }

    init();
</script>
</body>
</html>
