<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Budget Tracker</title>
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

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-small {
            padding: 0.375rem 0.875rem;
            font-size: 0.875rem;
        }

        .btn-success {
            background: var(--success);
            color: white;
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

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .filter-section {
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(168, 85, 247, 0.2);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
        }

        .stat-card.income .stat-value {
            color: var(--success);
        }

        .stat-card.expense .stat-value {
            color: var(--danger);
        }

        .stat-card.balance .stat-value {
            color: var(--primary);
        }

        .transaction-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .transaction-item {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .transaction-item:hover {
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .transaction-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .transaction-icon.income {
            background: rgba(16, 185, 129, 0.1);
        }

        .transaction-icon.expense {
            background: rgba(239, 68, 68, 0.1);
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-description {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .transaction-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: flex;
            gap: 1rem;
        }

        .transaction-amount {
            font-size: 1.25rem;
            font-weight: 700;
            margin-right: 1rem;
        }

        .transaction-amount.income {
            color: var(--success);
        }

        .transaction-amount.expense {
            color: var(--danger);
        }

        .transaction-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            background: rgba(168, 85, 247, 0.1);
            border: none;
            color: var(--primary);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .action-btn.delete:hover {
            background: var(--danger);
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

        .suggestions-modal {
            max-width: 700px;
            max-height: 80vh;
        }

        .suggestions-body {
            max-height: 70vh;
            overflow-y: auto;
        }

        .suggestion-card {
            padding: 1.25rem;
            border-radius: 12px;
            border-left: 4px solid;
            background: rgba(168, 85, 247, 0.05);
            margin-bottom: 1rem;
        }

        .suggestion-card.priority-high {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.08);
        }

        .suggestion-card.priority-medium {
            border-left-color: #f59e0b;
            background: rgba(245, 158, 11, 0.08);
        }

        .suggestion-card.priority-low {
            border-left-color: #10b981;
            background: rgba(16, 185, 129, 0.08);
        }

        .suggestion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .suggestion-title {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1.05rem;
        }

        .suggestion-priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .suggestion-priority-badge.high {
            background: #ef4444;
            color: white;
        }

        .suggestion-priority-badge.medium {
            background: #f59e0b;
            color: white;
        }

        .suggestion-priority-badge.low {
            background: #10b981;
            color: white;
        }

        .suggestion-message {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .suggestion-amount {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .suggestion-recommendation {
            color: var(--text-primary);
            font-style: italic;
            padding: 0.75rem;
            background: rgba(168, 85, 247, 0.1);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            padding: 1rem;
            border-radius: 12px;
            background: rgba(168, 85, 247, 0.1);
            text-align: center;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
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

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
        }

        .type-toggle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .type-option {
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .type-option:hover {
            border-color: var(--primary);
        }

        .type-option.active {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
            border-color: var(--primary);
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
            padding: 0.5rem;
            border: 2px solid var(--border);
            border-radius: 12px;
        }

        .category-option {
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .category-option:hover {
            border-color: var(--primary);
            background: rgba(168, 85, 247, 0.1);
        }

        .category-option.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .subcategory-section {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(168, 85, 247, 0.05);
            border-radius: 12px;
            border: 2px solid var(--border);
            display: none;
        }

        .subcategory-section.show {
            display: block;
        }

        .subcategory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .subcategory-option {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
            background: var(--card-bg);
        }

        .subcategory-option:hover {
            border-color: var(--primary);
            background: rgba(168, 85, 247, 0.1);
        }

        .subcategory-option.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }

            .grid-2 {
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
                <a href="wallet.php" class="nav-item">
                    👛 Wallets
                </a>
                <a href="budget.php" class="nav-item">
                    🎯 Budgets
                </a>
                <a href="transaction.php" class="nav-item active">
                    📝 Transactions
                </a>
                <a href="bill.php" class="nav-item" style="text-decoration: none;">
                    📋 Bills
                </a>
                <a href="analytics.php" class="nav-item">
                    📊 Analytics
                </a>
                <a href="dashboard.php" class="nav-item">
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
                    <input type="text" placeholder="Search transactions..." id="searchTransactions" oninput="searchTransactions()">
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
                    <button class="btn btn-primary" onclick="openModal('transactionModal')">
                        ➕ Add Transaction
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div style="margin-bottom: 2rem;">
                    <h2 style="font-size: 2rem; font-weight: 800;">Transactions</h2>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem;">Track all your income and expenses</p>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card income">
                        <div class="stat-label">Total Income</div>
                        <div class="stat-value" id="totalIncome">$0.00</div>
                    </div>
                    <div class="stat-card expense">
                        <div class="stat-label">Total Expenses</div>
                        <div class="stat-value" id="totalExpenses">$0.00</div>
                    </div>
                    <div class="stat-card balance">
                        <div class="stat-label">Net Balance</div>
                        <div class="stat-value" id="netBalance">$0.00</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card filter-section">
                    <div class="grid-2">
                        <div>
                            <label class="form-label">Filter by Type</label>
                            <select class="form-select" id="filterType" onchange="applyFilters()">
                                <option value="all">All Transactions</option>
                                <option value="income">Income Only</option>
                                <option value="expense">Expenses Only</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Filter by Period</label>
                            <select class="form-select" id="filterPeriod" onchange="applyFilters()">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Transactions List -->
                <div class="card">
                    <h3 style="font-weight: 700; margin-bottom: 1.5rem;">All Transactions</h3>
                    <div class="transaction-list" id="transactionsList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Modal -->
    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;" id="transactionModalTitle">Add Transaction</h3>
                <button onclick="closeModal('transactionModal')" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body">
                <div class="type-toggle">
                    <div class="type-option active" id="incomeType" onclick="selectType('income')">
                        💰 Income
                    </div>
                    <div class="type-option" id="expenseType" onclick="selectType('expense')">
                        💸 Expense
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Currency</label>
                    <select class="form-select" id="transactionCurrency" onchange="updateTransactionCurrency()">
                        <option value="BDT">৳ BDT (Bangladesh Taka)</option>
                        <option value="USD">$ USD (US Dollar)</option>
                        <option value="EUR">€ EUR (Euro)</option>
                        <option value="INR">₹ INR (Indian Rupee)</option>
                        <option value="GBP">£ GBP (British Pound)</option>
                        <option value="JPY">¥ JPY (Japanese Yen)</option>
                        <option value="CNY">¥ CNY (Chinese Yuan)</option>
                        <option value="AUD">$ AUD (Australian Dollar)</option>
                        <option value="CAD">$ CAD (Canadian Dollar)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Amount</label>
                    <input type="number" class="form-input" id="transactionAmount" placeholder="0.00" step="0.01">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-input" id="transactionDescription" placeholder="e.g., Salary, Groceries">
                </div>

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <div class="category-grid" id="categoryGrid"></div>
                    <div class="subcategory-section" id="subcategorySection">
                        <div style="font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">Select Subcategory</div>
                        <div class="subcategory-grid" id="subcategoryGrid"></div>
                    </div>

                    <div id="aiCategoryBox" style="margin-top: 0.75rem; padding: 0.875rem; border: 1px solid var(--border); border-radius: 12px; background: rgba(59, 130, 246, 0.06);">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap: 0.75rem;">
                            <div style="font-weight: 800;">🤖 AI Category Auto‑Fill</div>
                            <div style="display:flex; gap: 0.5rem; flex-wrap: wrap;">
                                <button class="btn btn-secondary" type="button" onclick="requestAICategorySuggestion()" style="padding: 0.5rem 0.75rem; border-radius: 10px;">Suggest</button>
                                <button class="btn btn-primary" type="button" onclick="applyAICategorySuggestion()" style="padding: 0.5rem 0.75rem; border-radius: 10px;">Apply</button>
                                <button class="btn" type="button" onclick="clearAICategorySuggestion()" style="padding: 0.5rem 0.75rem; border-radius: 10px; background: rgba(148, 163, 184, 0.2);">Clear</button>
                            </div>
                        </div>
                        <div id="aiCategorySuggestion" style="margin-top: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">
                            Type description/notes to get a category suggestion.
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Wallet</label>
                   <select class="form-select" id="transactionWallet"></select>

                    <div id="aiWalletBox" style="margin-top: 0.75rem; padding: 0.875rem; border: 1px solid var(--border); border-radius: 12px; background: rgba(168, 85, 247, 0.05);">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap: 0.75rem;">
                            <div style="font-weight: 800;">🤖 AI Wallet Suggestion</div>
                            <div style="display:flex; gap: 0.5rem;">
                                <button class="btn btn-secondary" type="button" onclick="requestAIWalletSuggestion()" style="padding: 0.5rem 0.75rem; border-radius: 10px;">Suggest</button>
                                <button class="btn" type="button" onclick="clearAIWalletSuggestion()" style="padding: 0.5rem 0.75rem; border-radius: 10px; background: rgba(148, 163, 184, 0.2);">Clear</button>
                            </div>
                        </div>
                        <div id="aiWalletSuggestion" style="margin-top: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">
                            Enter amount + pick a category to get a suggestion.
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-input" id="transactionDate">
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea class="form-textarea" id="transactionNotes" placeholder="Add any additional notes..."></textarea>
                </div>

                <button class="btn btn-primary" onclick="saveTransaction()" style="width: 100%;" id="transactionSaveBtn">Add Transaction</button>
            </div>
        </div>
    </div>

    <!-- AI Suggestions Modal -->
    <div id="suggestionsModal" class="modal">
        <div class="modal-content suggestions-modal">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;">🤖 AI Savings Assistant</h3>
                <button onclick="closeSuggestionsModal()" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body suggestions-body" id="suggestionsContent">
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">⏳</div>
                    <p>Analyzing your finances...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="currency.js"></script>
    <script>
        // App State
        let appState = {
            transactions: [],
            categories: [],
            wallets: [],
            selectedType: 'income',
            selectedCategory: null,
            selectedSubcategory: null,
            editingTransactionId: null,
            displayCurrency: 'BDT',
            selectedTransactionCurrency: 'BDT',
            walletSplitPlan: null,
            aiWalletSuggestion: null,
            aiCategorySuggestion: null,
            categoryManualOverride: false
        };

        let __aiWalletTimer = null;
        function scheduleAIWalletSuggestion() {
            if (appState.editingTransactionId) return;
            clearTimeout(__aiWalletTimer);
            __aiWalletTimer = setTimeout(() => {
                requestAIWalletSuggestion(true);
            }, 650);
        }

        let __aiCategoryTimer = null;
        function scheduleAICategorySuggestion() {
            if (appState.editingTransactionId) return;
            clearTimeout(__aiCategoryTimer);
            __aiCategoryTimer = setTimeout(() => {
                requestAICategorySuggestion(true);
            }, 550);
        }

        // Load data from localStorage

        // 2. Fixed Load Categories (Promise return korbe jate data ashar por grid update hoy)
        function loadCategoriesFromServer(type) {
            return fetch(`get_categories.php?type=${type}`)
                .then(res => res.json())
                .then(data => {
                    appState.categories = Array.isArray(data) ? data : [];
                    updateCategoryGrid();
                })
                .catch(err => {
                    console.error('Category load failed:', err);
                    appState.categories = [];
                    updateCategoryGrid();
                });
        }

        // 3. Fixed Init Function
        async function init() {
            loadTheme();
            loadTransactionCurrency();
            setTodayDate();

            await Promise.all([
                fetchWallets(),
                loadCategoriesFromServer('income'),
                fetchTransactions()
            ]);

            renderTransactions();
            updateStats();
        }

        async function fetchWallets() {
            try {
                const response = await fetch('get_wallet.php', { cache: 'no-store' });
                const data = await response.json();
                const wallets = Array.isArray(data) ? data : [];

                // Normalize wallet shape for this UI
                appState.wallets = wallets.map(w => {
                    const rawType = (w.type || w.wallet_type || 'Other');
                    const t = String(rawType).toLowerCase();
                    let icon = '👛';
                    if (t === 'cash') icon = '💵';
                    else if (t === 'bank') icon = '🏦';
                    else if (t === 'card') icon = '💳';
                    else if (t === 'mobile') icon = '📱';

                    return {
                        id: String(w.id),
                        name: w.wallet_name ?? w.name ?? '',
                        balance: parseFloat(w.balance) || 0,
                        type: rawType,
                        icon
                    };
                });

                updateWalletSelect();
            } catch (err) {
                console.error('Wallet fetch error:', err);
                appState.wallets = [];
                updateWalletSelect();
            }
        }

        async function fetchTransactions() {
            try {
                const res = await fetch('get_transactions.php', { cache: 'no-store' });
                const data = await res.json();
                appState.transactions = Array.isArray(data) ? data : [];
            } catch (err) {
                console.error('Transaction fetch error:', err);
                appState.transactions = [];
            }
        }

        function setTodayDate() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('transactionDate').value = today;
        }

        function clearAIWalletSuggestion() {
            appState.aiWalletSuggestion = null;
            appState.walletSplitPlan = null;
            const el = document.getElementById('aiWalletSuggestion');
            if (el) el.textContent = 'Enter amount + pick a category to get a suggestion.';
        }

        function clearAICategorySuggestion() {
            appState.aiCategorySuggestion = null;
            const el = document.getElementById('aiCategorySuggestion');
            if (el) el.textContent = 'Type description/notes to get a category suggestion.';
        }

        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.getElementById('themeIcon').textContent = '☀️';
            }
        }

        // Currency Management
        function loadTransactionCurrency() {
            const saved = localStorage.getItem('currency_transactions');
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
            localStorage.setItem('currency_transactions', norm);
            updateCurrencyDisplay();
            renderTransactions();
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

        function updateTransactionCurrency() {
            appState.selectedTransactionCurrency = Currency.normalize(document.getElementById('transactionCurrency').value);
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

    if (modalId === 'transactionModal') {
        selectType(appState.selectedType); // 🔥 this line fixes category
    }
}


        // Currency
        function formatCurrency(amount, fromCurrency) {
            const from = fromCurrency || appState.selectedTransactionCurrency || Currency.DEFAULT;
            return Currency.formatAmount(amount, from, appState.displayCurrency);
        }

        // Type Selection
        function selectType(type) {
    appState.selectedType = type;
    document.getElementById('incomeType').classList.toggle('active', type === 'income');
    document.getElementById('expenseType').classList.toggle('active', type === 'expense');

    loadCategoriesFromServer(type);
    clearAIWalletSuggestion();
    clearAICategorySuggestion();
    appState.categoryManualOverride = false;
}


        // Category Grid
        function updateCategoryGrid() {
    const grid = document.getElementById('categoryGrid');
    if (!grid) return;

    // appState.categories direct use koro
    if (!appState.categories || appState.categories.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-secondary);">No categories found.</div>';
        return;
    }

    grid.innerHTML = appState.categories.map(cat => `
        <div class="category-option ${appState.selectedCategory === cat.id ? 'selected' : ''}" 
             onclick="selectCategory('${cat.id}')">
            <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">${cat.icon || '📁'}</div>
            <div style="font-size: 0.75rem;">${cat.name}</div>
        </div>
    `).join('');
}

function selectCategory(categoryId) {
    appState.selectedCategory = categoryId;
    appState.selectedSubcategory = null; // reset subcategory on new category select
    appState.categoryManualOverride = true;
    updateCategoryGrid();
    updateSubcategoryGrid();
    scheduleAIWalletSuggestion();
}

        function updateSubcategoryGrid() {
    // Current selected category-ti khuje ber kora
    const category = appState.categories.find(c => String(c.id) === String(appState.selectedCategory));
    const subcategorySection = document.getElementById('subcategorySection');
    const subcategoryGrid = document.getElementById('subcategoryGrid');

    if (category && category.subcategories && category.subcategories.length > 0) {
        subcategorySection.classList.add('show');
        subcategoryGrid.innerHTML = category.subcategories.map(subName => `
            <div class="subcategory-option ${appState.selectedSubcategory === subName ? 'selected' : ''}" 
                 onclick="selectSubcategory('${subName}')">
                <div style="font-size: 0.875rem;">${subName}</div>
            </div>
        `).join('');
    } else {
        subcategorySection.classList.remove('show');
        subcategoryGrid.innerHTML = '';
    }
}

// Subcategory select korar function
function selectSubcategory(subName) {
    appState.selectedSubcategory = subName;
    updateSubcategoryGrid();
    scheduleAIWalletSuggestion();
}

        function renderAICategorySuggestion(suggestion) {
            const el = document.getElementById('aiCategorySuggestion');
            if (!el) return;
            if (!suggestion || !suggestion.category_id) {
                el.textContent = suggestion?.reason || 'No confident category suggestion yet.';
                return;
            }

            const conf = typeof suggestion.confidence === 'number' ? suggestion.confidence : 0;
            const badge = conf >= 0.75 ? '✅' : conf >= 0.5 ? '⚡' : '🤔';
            const src = suggestion.source === 'gemini' ? 'Gemini' : 'Smart rules';

            el.innerHTML = `
                <div style="display:flex; align-items:center; justify-content:space-between; gap: 0.75rem;">
                    <div style="font-weight:800;">${badge} ${suggestion.category_name || 'Suggested category'}</div>
                    <div style="font-size:0.8rem; color: var(--text-secondary);">${src} • ${(conf * 100).toFixed(0)}%</div>
                </div>
                <div style="margin-top:0.5rem; color: var(--text-secondary);">${suggestion.reason || ''}</div>
                ${appState.categoryManualOverride ? '<div style="margin-top:0.5rem; font-size:0.85rem; color: var(--warning);">You manually selected a category; AI will not auto-apply.</div>' : ''}
            `;
        }

        async function requestAICategorySuggestion(silent = false) {
            if (appState.editingTransactionId) return;
            const desc = document.getElementById('transactionDescription')?.value || '';
            const notes = document.getElementById('transactionNotes')?.value || '';
            const amount = parseFloat(document.getElementById('transactionAmount')?.value || '0');
            const el = document.getElementById('aiCategorySuggestion');

            const hasText = (desc.trim().length + notes.trim().length) >= 4;
            if (!hasText) {
                if (!silent && el) el.textContent = 'Add a bit more detail (description/notes) first.';
                return;
            }

            if (!appState.categories || appState.categories.length === 0) {
                if (el) el.textContent = 'No categories loaded.';
                return;
            }

            if (el) el.innerHTML = '<div style="display:flex; gap:0.5rem; align-items:center;"><span>⏳</span><span>Analyzing…</span></div>';

            try {
                const payload = {
                    type: appState.selectedType,
                    amount: amount || 0,
                    currency_code: appState.selectedTransactionCurrency || Currency.DEFAULT,
                    description: desc,
                    notes
                };

                const res = await fetch('ai_category_suggest.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                const out = await res.json();
                if (out && out.success && out.suggestion) {
                    appState.aiCategorySuggestion = out.suggestion;
                    renderAICategorySuggestion(out.suggestion);
                    return;
                }
                throw new Error(out?.error || 'Invalid category suggestion');
            } catch (e) {
                console.warn('AI category suggest failed:', e);
                appState.aiCategorySuggestion = null;
                if (el) el.textContent = 'Could not generate category suggestion right now.';
            }
        }

        function applyAICategorySuggestion() {
            const s = appState.aiCategorySuggestion;
            if (!s || !s.category_id) {
                showToast('No category suggestion to apply', 'info');
                return;
            }
            appState.categoryManualOverride = false;
            selectCategory(String(s.category_id));
            showToast('Applied category suggestion', 'success');
        }

       



        // Wallet Select
       function updateWalletSelect() {
    const select = document.getElementById('transactionWallet');
    select.innerHTML = '';

    if (!appState.wallets || appState.wallets.length === 0) {
        select.innerHTML = '<option value="">No wallets available</option>';
        return;
    }

    select.innerHTML = '<option value="">Select Wallet</option>';

    appState.wallets.forEach(wallet => {
        const option = document.createElement('option');
        option.value = wallet.id;
        option.textContent = `${wallet.name} (৳${(parseFloat(wallet.balance) || 0).toFixed(2)})`;
        select.appendChild(option);
    });
}


        // Stats
        function updateStats() {
            const totalIncome = appState.transactions
                .filter(t => t.type === 'income')
                .reduce((sum, t) => sum + t.amount, 0);
            
            const totalExpenses = appState.transactions
                .filter(t => t.type === 'expense')
                .reduce((sum, t) => sum + t.amount, 0);
            
            const netBalance = totalIncome - totalExpenses;

            document.getElementById('totalIncome').textContent = formatCurrency(totalIncome);
            document.getElementById('totalExpenses').textContent = formatCurrency(totalExpenses);
            document.getElementById('netBalance').textContent = formatCurrency(netBalance);
        }

        // Transactions
        function renderTransactions() {
            const list = document.getElementById('transactionsList');
            let transactions = [...appState.transactions];

            // Apply filters
            const typeFilter = document.getElementById('filterType')?.value || 'all';
            const periodFilter = document.getElementById('filterPeriod')?.value || 'all';

            if (typeFilter !== 'all') {
                transactions = transactions.filter(t => t.type === typeFilter);
            }

            if (periodFilter !== 'all') {
                const now = new Date();
                transactions = transactions.filter(t => {
                    const tDate = new Date(t.date);
                    switch(periodFilter) {
                        case 'today':
                            return tDate.toDateString() === now.toDateString();
                        case 'week':
                            const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                            return tDate >= weekAgo;
                        case 'month':
                            return tDate.getMonth() === now.getMonth() && tDate.getFullYear() === now.getFullYear();
                        case 'year':
                            return tDate.getFullYear() === now.getFullYear();
                        default:
                            return true;
                    }
                });
            }

            if (transactions.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">No Transactions Yet</h3>
                        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Add your first transaction to start tracking</p>
                        <button class="btn btn-primary" onclick="openModal('transactionModal')">➕ Add Transaction</button>
                    </div>
                `;
                return;
            }

            // Sort by date (newest first)
            transactions.sort((a, b) => new Date(b.date) - new Date(a.date));

            list.innerHTML = transactions.map(transaction => {
                const category = appState.categories.find(c => c.id === transaction.categoryId);
                const wallet = appState.wallets.find(w => w.id === transaction.walletId);
                
                return `
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-icon ${transaction.type}">
                                ${category ? category.icon : transaction.type === 'income' ? '💰' : '💸'}
                            </div>
                            <div class="transaction-details">
                                <div class="transaction-description">${transaction.description}</div>
                                <div class="transaction-meta">
                                    <span>📅 ${new Date(transaction.date).toLocaleDateString()}</span>
                                    <span>${category ? category.name : 'Uncategorized'}</span>
                                    <span>${wallet ? wallet.icon + ' ' + wallet.name : 'No Wallet'}</span>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <div class="transaction-amount ${transaction.type}">
                                ${transaction.type === 'income' ? '+' : '-'}${formatCurrency(transaction.amount, transaction.currency)}
                            </div>
                            <div class="transaction-actions">
                                <button class="action-btn" onclick="editTransaction('${transaction.id}')" title="Edit">✏️</button>
                                <button class="action-btn delete" onclick="deleteTransaction('${transaction.id}')" title="Delete">🗑️</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
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

        function saveTransaction() {
            const amount = parseFloat(document.getElementById('transactionAmount').value);
            const description = document.getElementById('transactionDescription').value;
            const walletId = document.getElementById('transactionWallet').value;
            const date = document.getElementById('transactionDate').value;
            const notes = document.getElementById('transactionNotes').value;

            if (!amount || amount <= 0) {
                showToast('Please enter a valid amount', 'error');
                return;
            }

            if (!description) {
                showToast('Please enter a description', 'error');
                return;
            }

            if (!appState.selectedCategory) {
                showToast('Please select a category', 'error');
                return;
            }

            const hasSplit = Array.isArray(appState.walletSplitPlan) && appState.walletSplitPlan.length > 0;
            if (!hasSplit && !walletId) {
                showToast('Please select a wallet (or apply AI split)', 'error');
                return;
            }

            if (!date) {
                showToast('Please select a date', 'error');
                return;
            }

            const payload = {
                transaction_id: appState.editingTransactionId,
                type: appState.selectedType,
                amount,
                description,
                notes,
                wallet_id: hasSplit ? parseInt(appState.walletSplitPlan[0].wallet_id, 10) : parseInt(walletId, 10),
                category_id: appState.selectedCategory ? parseInt(appState.selectedCategory, 10) : null,
                date,
                currency_code: appState.selectedTransactionCurrency
            };

            if (hasSplit && !appState.editingTransactionId) {
                payload.wallet_splits = appState.walletSplitPlan.map(s => ({
                    wallet_id: parseInt(s.wallet_id, 10),
                    amount: parseFloat(s.amount)
                }));
            }

            const url = appState.editingTransactionId ? 'update_transaction.php' : 'save_transaction.php';
            const body = appState.editingTransactionId
                ? { ...payload, transaction_id: parseInt(appState.editingTransactionId, 10) }
                : payload;

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
            .then(r => r.json())
            .then(async out => {
                console.log('Transaction response:', out); // Debug log
                console.log('Selected type:', appState.selectedType); // Debug log
                console.log('Has ai_suggestions:', !!out?.ai_suggestions); // Debug log
                
                if (!out || !out.success) {
                    showToast((out?.error || 'Failed to save transaction') + (out?.details ? ' - ' + out.details : ''), 'error');
                    return;
                }
                closeModal('transactionModal');
                showToast(appState.editingTransactionId ? 'Transaction updated successfully' : 'Transaction added successfully', 'success');
                
                // Show AI suggestions if available (only for new expenses)
                if (!appState.editingTransactionId && appState.selectedType === 'expense' && out.ai_suggestions) {
                    console.log('Displaying AI suggestions...'); // Debug log
                    await new Promise(resolve => setTimeout(resolve, 300)); // Small delay for better UX
                    displayAISuggestions(out.ai_suggestions);
                } else {
                    console.log('Not showing suggestions - editingId:', appState.editingTransactionId, 'type:', appState.selectedType, 'has suggestions:', !!out?.ai_suggestions); // Debug log
                }
                
                await Promise.all([fetchTransactions(), fetchWallets()]);
                renderTransactions();
                updateStats();
            })
            .catch(e => {
                console.error('Fetch error:', e); // Debug log
                showToast('Failed to save transaction', 'error');
            });
        }

        function editTransaction(transactionId) {
            const transaction = appState.transactions.find(t => t.id === transactionId);
            if (!transaction) return;

            appState.editingTransactionId = transactionId;
            appState.selectedType = transaction.type;
            appState.selectedCategory = transaction.categoryId;
            appState.selectedTransactionCurrency = transaction.currency || Currency.DEFAULT;

            document.getElementById('transactionModalTitle').textContent = 'Edit Transaction';
            document.getElementById('transactionSaveBtn').textContent = 'Update Transaction';
            
            selectType(transaction.type);
            document.getElementById('transactionAmount').value = transaction.amount;
            document.getElementById('transactionDescription').value = transaction.description;
            document.getElementById('transactionWallet').value = transaction.walletId;
            document.getElementById('transactionDate').value = transaction.date;
            document.getElementById('transactionNotes').value = transaction.notes || '';
            document.getElementById('transactionCurrency').value = appState.selectedTransactionCurrency;
            
            updateCategoryGrid();
            openModal('transactionModal');
        }

        function deleteTransaction(transactionId) {
            if (!confirm('Are you sure you want to delete this transaction?')) return;

            fetch('delete_transaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transaction_id: parseInt(transactionId, 10) })
            })
            .then(r => r.json())
            .then(async out => {
                if (!out || !out.success) {
                    showToast(out?.error || 'Failed to delete transaction', 'error');
                    return;
                }
                showToast('Transaction deleted successfully', 'success');
                await Promise.all([fetchTransactions(), fetchWallets()]);
                renderTransactions();
                updateStats();
            })
            .catch(() => showToast('Failed to delete transaction', 'error'));
        }

        function resetTransactionForm() {
            appState.editingTransactionId = null;
            appState.selectedCategory = null;
            appState.selectedSubcategory = null;
            appState.selectedType = 'income';
            appState.selectedTransactionCurrency = appState.displayCurrency;
            appState.walletSplitPlan = null;
            appState.aiWalletSuggestion = null;
            appState.aiCategorySuggestion = null;
            appState.categoryManualOverride = false;
            
            document.getElementById('transactionModalTitle').textContent = 'Add Transaction';
            document.getElementById('transactionSaveBtn').textContent = 'Add Transaction';
            document.getElementById('transactionAmount').value = '';
            document.getElementById('transactionDescription').value = '';
            document.getElementById('transactionNotes').value = '';
            document.getElementById('transactionCurrency').value = appState.selectedTransactionCurrency;
            document.getElementById('subcategorySection').classList.remove('show');
            setTodayDate();
            selectType('income');
            clearAIWalletSuggestion();
            clearAICategorySuggestion();
        }

        async function requestAIWalletSuggestion(silent = false) {
            if (appState.editingTransactionId) return;

            const amount = parseFloat(document.getElementById('transactionAmount').value);
            const description = document.getElementById('transactionDescription').value || '';
            const notes = document.getElementById('transactionNotes').value || '';
            const el = document.getElementById('aiWalletSuggestion');

            if (!amount || amount <= 0) {
                if (!silent && el) el.textContent = 'Enter a valid amount first.';
                return;
            }

            if (!appState.wallets || appState.wallets.length === 0) {
                if (el) el.textContent = 'No wallets found.';
                return;
            }

            if (el) el.innerHTML = '<div style="display:flex; gap:0.5rem; align-items:center;"><span>⏳</span><span>Thinking…</span></div>';

            try {
                const res = await fetch('ai_wallet_suggestions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: appState.selectedType,
                        amount,
                        currency_code: appState.selectedTransactionCurrency,
                        category_id: appState.selectedCategory ? parseInt(appState.selectedCategory, 10) : null,
                        description,
                        notes
                    })
                });
                const out = await res.json();
                if (!out || !out.success || !out.suggestion) {
                    if (el) el.textContent = out?.error || 'Failed to get suggestion.';
                    return;
                }

                appState.aiWalletSuggestion = out.suggestion;
                renderAIWalletSuggestion(out.suggestion);
            } catch (e) {
                console.error('AI wallet suggestion error:', e);
                if (el) el.textContent = 'AI suggestion failed. You can still choose a wallet manually.';
            }
        }

        function renderAIWalletSuggestion(suggestion) {
            const el = document.getElementById('aiWalletSuggestion');
            if (!el) return;

            if (!suggestion || !Array.isArray(suggestion.wallets) || suggestion.wallets.length === 0) {
                el.textContent = suggestion?.message || 'No suggestion available.';
                return;
            }

            const rows = suggestion.wallets.map(w => {
                const wallet = (appState.wallets || []).find(x => String(x.id) === String(w.wallet_id));
                const name = wallet ? wallet.name : ('Wallet #' + w.wallet_id);
                const amt = (parseFloat(w.amount) || 0).toFixed(2);
                const reason = w.reason ? ` — ${w.reason}` : '';
                return `<div style="display:flex; justify-content:space-between; gap:0.75rem; padding:0.5rem 0; border-bottom: 1px dashed rgba(148, 163, 184, 0.35);">
                    <div style="font-weight:700;">${name}</div>
                    <div style="font-weight:800;">${amt} ${w.currency_code || appState.selectedTransactionCurrency}</div>
                </div><div style="margin:0.35rem 0 0.75rem; color: var(--text-secondary); font-size: 0.85rem;">${reason || ''}</div>`;
            }).join('');

            const badge = suggestion.mode === 'split'
                ? `<span style="display:inline-flex; padding:0.25rem 0.5rem; border-radius: 999px; background: rgba(245, 158, 11, 0.15); color: var(--warning); font-weight: 800; font-size: 0.75rem;">Split</span>`
                : `<span style="display:inline-flex; padding:0.25rem 0.5rem; border-radius: 999px; background: rgba(16, 185, 129, 0.15); color: var(--success); font-weight: 800; font-size: 0.75rem;">Single</span>`;

            el.innerHTML = `
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; margin-bottom:0.5rem;">
                    <div style="font-weight:800;">Suggestion ${badge}</div>
                    <button class="btn btn-primary" type="button" onclick="applyAIWalletSuggestion()" style="padding: 0.5rem 0.75rem; border-radius: 10px;">Apply</button>
                </div>
                <div style="color: var(--text-secondary); margin-bottom: 0.75rem;">${suggestion.message || ''}</div>
                <div>${rows}</div>
            `;
        }

        function applyAIWalletSuggestion() {
            const suggestion = appState.aiWalletSuggestion;
            if (!suggestion || !Array.isArray(suggestion.wallets) || suggestion.wallets.length === 0) return;

            if (appState.editingTransactionId) {
                showToast('AI wallet split is for new transactions only.', 'info');
                return;
            }

            if (suggestion.mode === 'split') {
                appState.walletSplitPlan = suggestion.wallets;
                const firstId = suggestion.wallets[0].wallet_id;
                document.getElementById('transactionWallet').value = String(firstId);
                showToast(`Applied split across ${suggestion.wallets.length} wallet(s)`, 'success');
            } else {
                appState.walletSplitPlan = null;
                const wid = suggestion.wallets[0].wallet_id;
                document.getElementById('transactionWallet').value = String(wid);
                showToast('Applied wallet suggestion', 'success');
            }
        }

        // Auto-suggest when user changes key fields
        document.addEventListener('DOMContentLoaded', () => {
            const amt = document.getElementById('transactionAmount');
            const desc = document.getElementById('transactionDescription');
            const notes = document.getElementById('transactionNotes');
            const cur = document.getElementById('transactionCurrency');
            if (amt) amt.addEventListener('input', () => { appState.walletSplitPlan = null; scheduleAIWalletSuggestion(); });
            if (desc) desc.addEventListener('input', () => { scheduleAIWalletSuggestion(); scheduleAICategorySuggestion(); });
            if (notes) notes.addEventListener('input', () => { scheduleAIWalletSuggestion(); scheduleAICategorySuggestion(); });
            if (cur) cur.addEventListener('change', () => { appState.walletSplitPlan = null; scheduleAIWalletSuggestion(); });
        });

        function applyFilters() {
            renderTransactions();
        }

        function searchTransactions() {
            const searchTerm = document.getElementById('searchTransactions').value.toLowerCase();
            
            if (!searchTerm) {
                renderTransactions();
                return;
            }

            const filtered = appState.transactions.filter(t => 
                t.description.toLowerCase().includes(searchTerm) ||
                (appState.categories.find(c => c.id === t.categoryId)?.name || '').toLowerCase().includes(searchTerm)
            );

            const original = [...appState.transactions];
            appState.transactions = filtered;
            renderTransactions();
            appState.transactions = original;
        }
        function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    if (modalId === 'transactionModal') {
        resetTransactionForm();
    }
}

        function displayAISuggestions(data) {
            const modal = document.getElementById('suggestionsModal');
            const content = document.getElementById('suggestionsContent');

            let html = '<div class="summary-stats">';
            
            // Income summary
            html += `<div class="stat-box">
                <div class="stat-label">💰 Income</div>
                <div class="stat-value">${data.current_income.toFixed(0)} BDT</div>
            </div>`;

            // Expenses summary
            html += `<div class="stat-box">
                <div class="stat-label">💸 Expenses</div>
                <div class="stat-value">${data.current_expenses.toFixed(0)} BDT</div>
            </div>`;

            // Remaining
            html += `<div class="stat-box">
                <div class="stat-label">📊 Remaining</div>
                <div class="stat-value">${data.remaining.toFixed(0)} BDT</div>
            </div>`;

            // Savings rate
            html += `<div class="stat-box">
                <div class="stat-label">🎯 Savings Rate</div>
                <div class="stat-value">${data.savings_rate.toFixed(1)}%</div>
            </div></div>`;

            // Add suggestions
            if (data.suggestions && data.suggestions.length > 0) {
                html += '<h4 style="margin-top: 1.5rem; margin-bottom: 1rem; font-weight: 700;">Smart Recommendations:</h4>';
                
                data.suggestions.forEach(sug => {
                    const priorityClass = `priority-${sug.priority}`;
                    const badgeClass = sug.priority;
                    
                    html += `
                        <div class="suggestion-card ${priorityClass}">
                            <div class="suggestion-header">
                                <div class="suggestion-title">${sug.message}</div>
                                <span class="suggestion-priority-badge ${badgeClass}">${sug.priority}</span>
                            </div>
                            <div class="suggestion-recommendation">
                                💡 ${sug.recommendation}
                            </div>
                            ${sug.amount_to_save > 0 ? `<div class="suggestion-amount">Potential Savings: ${sug.amount_to_save.toFixed(2)} BDT</div>` : ''}
                        </div>
                    `;
                });
            } else {
                html += '<p style="text-align: center; color: var(--text-secondary); margin-top: 1rem;">No suggestions at this time. Keep tracking your expenses!</p>';
            }

            // Optional: Gemini AI conversational advice
            if (data.ai_text) {
                html += `
                    <h4 style="margin-top: 1.5rem; margin-bottom: 0.75rem; font-weight: 700;">Gemini Advice</h4>
                    <div class="suggestion-card priority-low">
                        <div class="suggestion-recommendation" style="white-space: pre-wrap;">${data.ai_text}</div>
                    </div>
                `;
            }

            if (data.total_savings_potential > 0) {
                html += `<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05)); padding: 1rem; border-radius: 12px; margin-top: 1rem; border: 2px solid rgba(16, 185, 129, 0.25); text-align: center;">
                    <div style="font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">Total Savings Potential</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--success);">${data.total_savings_potential.toFixed(2)} BDT</div>
                </div>`;
            }

            content.innerHTML = html;
            modal.classList.add('show');
        }

        function closeSuggestionsModal() {
            document.getElementById('suggestionsModal').classList.remove('show');
        }

        init();
    </script>
</body>
</html>