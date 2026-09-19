<?php
require "db.php";
require_once 'schema.php';
session_start();

// লগইন করা ইউজারের আইডি সেশন থেকে নিন (যদি সেশন না থাকে তবে রিডাইরেক্ট করুন)
if (!isset($_SESSION['user_id'])) {
    // উদাহরণস্বরূপ: $_SESSION['user_id'] = 1; (টেস্ট করার জন্য এটি ব্যবহার করতে পারেন)
    die("Please login first.");
}
$current_user_id = $_SESSION['user_id'];

// Ensure system flag exists and seed initial categories + subcategories for this user
ensure_category_system_column($conn);
ensure_subcategories_table($conn);
seed_initial_categories_for_user($conn, (int)$current_user_id);

// নতুন ক্যাটাগরি সেভ করার হ্যান্ডলার (user-created only; system categories are not addable)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    header('Content-Type: application/json');

    $name = trim((string)($_POST['name'] ?? ''));
    $type = strtolower((string)($_POST['type'] ?? 'expense'));
    $icon = trim((string)($_POST['icon'] ?? ''));

    if ($name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Category name is required']);
        exit;
    }

    // spendeeapp.sql uses ENUM('Income','Expense')
    $typeDb = $type === 'income' ? 'Income' : 'Expense';

    // Persist icon by prefixing into category_name (schema has no icon column)
    $storedName = $icon !== '' ? ($icon . ' ' . $name) : $name;

    // Check if category already exists for this user
    $check = $conn->prepare("SELECT 1 FROM categories WHERE user_id = ? AND category_name = ? AND category_type = ? LIMIT 1");
    $check->bind_param("iss", $current_user_id, $storedName, $typeDb);
    $check->execute();
    $checkRes = $check->get_result();
    if ($checkRes && $checkRes->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Category name exists, use a different name']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO categories (user_id, category_name, category_type, is_system) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("iss", $current_user_id, $storedName, $typeDb);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save category']);
    }
    $stmt->close();
    exit;
}

// ক্যাটাগরি কুয়েরি: বর্তমান ইউজারের ক্যাটাগরি (বাজেট-লিঙ্কড ক্যাটাগরি বাদ) + is_system flag + DISTINCT
$query = "SELECT DISTINCT c.category_id, c.category_name, c.category_type, c.is_system
                    FROM categories c
                    WHERE c.user_id = ?
                        AND NOT EXISTS (
                                SELECT 1
                                FROM budget_items bi
                                JOIN budgets b ON b.budget_id = bi.budget_id
                                WHERE bi.category_id = c.category_id
                                    AND b.user_id = ?
                        )
                    ORDER BY c.category_type, c.category_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $current_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$all_categories = [];
$catIdIndex = [];
$seenIds = new SplFixedArray(1000); // Track seen IDs to prevent duplicates
$seenCount = 0;

while($row = $result->fetch_assoc()) {
    $cid = (int)$row['category_id'];
    
    // Skip if already processed this category_id
    $isDuplicate = false;
    for ($i = 0; $i < $seenCount; $i++) {
        if ($seenIds[$i] === $cid) {
            $isDuplicate = true;
            break;
        }
    }
    if ($isDuplicate) continue;
    
    $seenIds[$seenCount++] = $cid;
    
    $rawName = (string)$row['category_name'];
    $icon = '📁';
    $name = $rawName;
    if (preg_match('/^(\X)\s+(.+)$/u', $rawName, $m)) {
        $icon = $m[1];
        $name = $m[2];
    }

    $all_categories[] = [
        'category_id' => $cid,
        'category_icon' => $icon,
        'category_name' => $name,
        'category_type' => strtolower((string)$row['category_type']),
        'subcategories' => [],
        'is_system' => (int)($row['is_system'] ?? 0) === 1
    ];
    $catIdIndex[] = $cid;
}

// Load subcategories for these categories
$subsMap = [];
if (count($catIdIndex) > 0) {
    $in = implode(',', array_map('intval', $catIdIndex));
    $subRes = $conn->query("SELECT category_id, name FROM subcategories WHERE category_id IN ($in) ORDER BY name");
    if ($subRes) {
        while ($sr = $subRes->fetch_assoc()) {
            $cid = (int)$sr['category_id'];
            if (!isset($subsMap[$cid])) $subsMap[$cid] = [];
            $subsMap[$cid][] = $sr['name'];
        }
    }
}

// Attach subcategories back into array
foreach ($all_categories as &$c) {
    $cid = $c['category_id'];
    $c['subcategories'] = $subsMap[$cid] ?? [];
}
unset($c);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Budget Tracker</title>
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

        .tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--border);
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
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
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge Legacy */
        }

        .modal-content::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none; /* Chrome/Safari */
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

        /* Emoji picker */
        .emoji-input-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .emoji-input-row .form-input {
            flex: 1;
        }

        .emoji-open-btn {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: var(--card-bg);
            cursor: pointer;
            font-size: 1.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .emoji-open-btn:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.08);
        }

        .emoji-picker {
            margin-top: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 16px;
            background: var(--card-bg);
            padding: 0.75rem;
            display: none;
        }

        .emoji-picker.show {
            display: block;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 0.5rem;
            margin-top: 0.75rem;
            max-height: 220px;
            overflow: auto;
            padding-right: 4px;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge Legacy */
        }

        .emoji-grid::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none; /* Chrome/Safari */
        }

        .emoji-btn {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(168, 85, 247, 0.04);
            cursor: pointer;
            font-size: 1.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .emoji-btn:hover {
            transform: translateY(-1px);
            border-color: var(--primary);
            background: rgba(168, 85, 247, 0.10);
        }

        .emoji-help {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
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
                <a href="dashboard.php" class="nav-item">
                    🏠 Home
                </a>
                <a href="category.php" class="nav-item active">
                    📁 Categories
                </a>
                <a href="wallet.php" class="nav-item">
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
                <a href="dashboard.php" class="nav-item">
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
                    <input type="text" placeholder="Search categories..." id="searchCategories" oninput="searchCategories()">
                </div>
                <div class="top-bar-actions">
                    <button class="btn btn-outline theme-toggle" onclick="toggleDarkMode()">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <button class="btn btn-primary" onclick="openModal('categoryModal')">
                        ➕ Add Category
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h2 style="font-size: 2rem; font-weight: 800;">Categories</h2>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Manage your expense and income categories</p>
                    </div>
                </div>

                <div class="tabs">
                    <button class="tab active" onclick="switchCategoryTab('expense')">Expenses</button>
                    <button class="tab" onclick="switchCategoryTab('income')">Income</button>
                </div>

                <div id="expenseCategories" class="category-list">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;" id="expenseCategoriesGrid"></div>
                </div>

                <div id="incomeCategories" class="category-list hidden">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;" id="incomeCategoriesGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;">Add Category</h3>
                <button onclick="closeModal('categoryModal')" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" id="categoryType">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Icon (emoji)</label>
                    <div class="emoji-input-row">
                        <input type="text" class="form-input" id="categoryIcon" placeholder="🍕" autocomplete="off">
                        <button type="button" class="emoji-open-btn" onclick="toggleEmojiPicker('add')" aria-label="Open emoji picker">😀</button>
                    </div>
                    <div class="emoji-picker" id="emojiPickerAdd" aria-hidden="true">
                        <div class="emoji-help">Select an emoji below (you can also type one).</div>
                        <div class="emoji-grid" id="emojiGridAdd"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Category Name</label>
                    <input type="text" class="form-input" id="categoryName" placeholder="e.g., Food & Dining">
                </div>

                <div class="form-group">
                    <label class="form-label">Subcategories (comma separated)</label>
                    <input type="text" class="form-input" id="categorySubcategories" placeholder="e.g., Restaurant, Fast Food, Groceries">
                </div>

                <button class="btn btn-primary" onclick="addCategory()" style="width: 100%;">Add Category</button>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-size: 1.5rem; font-weight: 800;">Edit Category</h3>
                <button onclick="closeModal('editCategoryModal')" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 1.25rem;">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" id="editCategoryType">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Icon (emoji)</label>
                    <div class="emoji-input-row">
                        <input type="text" class="form-input" id="editCategoryIcon" placeholder="🍕" autocomplete="off">
                        <button type="button" class="emoji-open-btn" onclick="toggleEmojiPicker('edit')" aria-label="Open emoji picker">😀</button>
                    </div>
                    <div class="emoji-picker" id="emojiPickerEdit" aria-hidden="true">
                        <div class="emoji-help">Select an emoji below (you can also type one).</div>
                        <div class="emoji-grid" id="emojiGridEdit"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Category Name</label>
                    <input type="text" class="form-input" id="editCategoryName" placeholder="e.g., Food & Dining">
                </div>

                <button class="btn btn-primary" onclick="saveCategoryEdit()" style="width: 100%;">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
         const dbCategories = <?php echo json_encode($all_categories); ?>;
        // DEFAULT (CODE) CATEGORIES
const defaultCategories = [
    // EXPENSE
    {
        category_icon: '🍕',
        category_name: 'Food & Dining',
        category_type: 'expense',
        subcategories: ['Restaurant', 'Fast Food', 'Groceries', 'Coffee Shop', 'Bakery']
    },
    {
        category_icon: '🛒',
        category_name: 'Shopping',
        category_type: 'expense',
        subcategories: ['Clothing', 'Electronics', 'Books', 'Gifts']
    },
    {
        category_icon: '🚗',
        category_name: 'Transportation',
        category_type: 'expense',
        subcategories: ['Bus', 'Train', 'Uber', 'Fuel', 'Parking']
    },
    {
        category_icon: '🏠',
        category_name: 'Housing',
        category_type: 'expense',
        subcategories: ['Rent', 'Mortgage', 'Property Tax']
    },
    {
        category_icon: '⚡',
        category_name: 'Bills & Utilities',
        category_type: 'expense',
        subcategories: ['Electricity', 'Water', 'Internet', 'Phone']
    },
    {
        category_icon: '🏥',
        category_name: 'Healthcare',
        category_type: 'expense',
        subcategories: ['Doctor', 'Pharmacy', 'Hospital']
    },
    {
        category_icon: '🎓',
        category_name: 'Education',
        category_type: 'expense',
        subcategories: ['Tuition', 'Books', 'Courses']
    },
    {
        category_icon: '🎬',
        category_name: 'Entertainment',
        category_type: 'expense',
        subcategories: ['Movies', 'Games', 'Streaming']
    },

    // INCOME
    {
        category_icon: '💰',
        category_name: 'Salary & Wages',
        category_type: 'income',
        subcategories: ['Monthly Salary', 'Bonus', 'Overtime']
    },
    {
        category_icon: '💼',
        category_name: 'Business',
        category_type: 'income',
        subcategories: ['Sales', 'Services', 'Freelance']
    },
    {
        category_icon: '📈',
        category_name: 'Investments',
        category_type: 'income',
        subcategories: ['Dividends', 'Interest', 'Stocks']
    },
    {
        category_icon: '🎁',
        category_name: 'Gifts Received',
        category_type: 'income',
        subcategories: ['Birthday', 'Wedding', 'Festival']
    }
];

// DB categories PHP থেকে আসবে

// If DB categories exist, use them. Otherwise show built-in defaults as non-editable (system).
const normalizedDefaults = (defaultCategories || []).map((c, idx) => ({
    ...c,
    category_id: -1 * (idx + 1),
    is_system: true
}));

let appState = {
    categories: (Array.isArray(dbCategories) && dbCategories.length > 0) ? dbCategories : normalizedDefaults,
    editingCategoryId: null
};

        const EMOJIS = [
            '🍕','🍔','🍟','🌭','🥪','🌮','🌯','🥗','🍝','🍜','🍣','🍱','🍛','🍚','🍙','🥟','🍤','🍗','🥩','🥚',
            '🍞','🥐','🥖','🧀','🍰','🧁','🍩','🍪','🍫','🍿','🍯','🍎','🍌','🍇','🍉','🍓','🍍','🥭','🍒','🥑',
            '🥕','🌽','🥔','🍅','🥒','🥦','🍄','🧅','🧄',
            '🚗','🚕','🚌','🚎','🚓','🚑','🚒','🚚','🚛','🚲','🛵','🏍️','🚆','🚇','✈️','🛫','🛬','🛳️','⛴️','🚤',
            '🏠','🏡','🏢','🏬','🏦','🏥','🏫','🏪','🏭','🏨','🕌','🕍','⛪','🛕',
            '⚡','💡','🔌','💧','🚿','🧾','📄','📃','📑','🧻','🧼','🧽',
            '💸','💰','💳','🏧','🪙','📈','📉','🧮','🧾','🛒','🛍️','🏷️',
            '📱','📞','📲','💻','⌨️','🖥️','🖨️','🧠','🤖','🔔','🔕','📣','📢',
            '📚','📖','✏️','📝','📌','📍','📎','📦','🗂️','📁','📂','🗃️',
            '🎯','✅','❌','⚠️','⏰','📅','🗓️','🕒','🕓','🕔','🕕','🕖','🕗','🕘','🕙','🕚','🕛',
            '👛','👜','🎒','🧳','🪪','🔑','🗝️','🔒','🔓','🧷','🧰','🔧','🪛','🛠️',
            '🏥','💊','🩺','🧑‍⚕️','🧘','🏃','🏋️','🚴','⚽','🏀','🏸','🎾','🏏','🏓','🎮','🎲','🎬','🎧','🎵',
            '👶','🧒','👧','🧑','👩','👨','🧓','👵','👴','👪','👨‍👩‍👧‍👦',
            '🌞','🌙','⭐','☁️','🌧️','⛈️','❄️','🔥','🌈','🌊','🌳','🌲','🌴','🌵','🌸','🌼','🌻','🌺',
            '🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧','🐦',
            '🧾','📊','📌','📍','🧠','🧠','🧭','🧯','🪫','🔋','🔦','🕯️'
        ];

        let __openEmojiPicker = null;

        function renderEmojiGrid(targetId, mode) {
            const grid = document.getElementById(targetId);
            if (!grid) return;
            grid.innerHTML = EMOJIS.map(e => `
                <button type="button" class="emoji-btn" onclick="selectEmoji('${mode}', '${e.replace(/'/g, "\\'")}')" aria-label="${e}">${e}</button>
            `).join('');
        }

        function toggleEmojiPicker(mode) {
            const pickerId = mode === 'edit' ? 'emojiPickerEdit' : 'emojiPickerAdd';
            const picker = document.getElementById(pickerId);
            if (!picker) return;

            const willOpen = !picker.classList.contains('show');

            // Close any other open picker
            ['emojiPickerAdd','emojiPickerEdit'].forEach(id => {
                const el = document.getElementById(id);
                if (el && id !== pickerId) {
                    el.classList.remove('show');
                    el.setAttribute('aria-hidden', 'true');
                }
            });

            picker.classList.toggle('show', willOpen);
            picker.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
            __openEmojiPicker = willOpen ? pickerId : null;
        }

        function selectEmoji(mode, emoji) {
            const inputId = mode === 'edit' ? 'editCategoryIcon' : 'categoryIcon';
            const input = document.getElementById(inputId);
            if (input) {
                input.value = emoji;
                input.focus();
            }
            // Close picker
            toggleEmojiPicker(mode);
        }


        // Initialize
        function init() {
            renderCategories();
            loadTheme();
            renderEmojiGrid('emojiGridAdd', 'add');
            renderEmojiGrid('emojiGridEdit', 'edit');

            // Close emoji picker on outside click / escape
            document.addEventListener('click', (e) => {
                if (!__openEmojiPicker) return;
                const open = document.getElementById(__openEmojiPicker);
                if (!open) return;
                const isBtn = (e.target && e.target.classList && e.target.classList.contains('emoji-open-btn'));
                if (isBtn) return;
                if (!open.contains(e.target)) {
                    open.classList.remove('show');
                    open.setAttribute('aria-hidden', 'true');
                    __openEmojiPicker = null;
                }
            });

            document.addEventListener('keydown', (e) => {
                if (!__openEmojiPicker) return;
                if (e.key === 'Escape') {
                    const open = document.getElementById(__openEmojiPicker);
                    if (open) {
                        open.classList.remove('show');
                        open.setAttribute('aria-hidden', 'true');
                    }
                    __openEmojiPicker = null;
                }
            });
        }

        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.getElementById('themeIcon').textContent = '☀️';
            }
        }

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
        }

        // Categories
        function renderCategories() {
            // Deduplicate categories by category_id in JavaScript
            const seen = new Set();
            const uniqueCategories = appState.categories.filter(c => {
                if (seen.has(c.category_id)) return false;
                seen.add(c.category_id);
                return true;
            });

            const expenseCategories = uniqueCategories.filter(c => c.category_type === 'expense');
            const incomeCategories = uniqueCategories.filter(c => c.category_type === 'income');


            document.getElementById('expenseCategoriesGrid').innerHTML = expenseCategories.map(cat => `
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span style="font-size: 2.5rem;">${cat.category_icon}</span>
                            <div>
                                <h4 style="font-weight: 700; font-size: 1.125rem;">${cat.category_name}</h4>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">${cat.category_type}</p>
                            </div>
                        </div>
                        ${cat.is_system ? '' : `
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-outline btn-small" onclick="editCategory(${cat.category_id})" style="padding: 0.25rem 0.5rem;">✏️</button>
                            <button class="btn btn-outline btn-small" onclick="deleteCategory(${cat.category_id})" style="padding: 0.25rem 0.5rem; color: var(--danger);">🗑️</button>
                        </div>`}
                    </div>
                    <div>
                        <p style="font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">Subcategories:</p>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            ${cat.subcategories.map(sub => `
                                <span style="padding: 0.25rem 0.75rem; background: rgba(239, 68, 68, 0.1); color: var(--danger); font-size: 0.75rem; font-weight: 600; border-radius: 12px;">${sub}</span>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `).join('');

            document.getElementById('incomeCategoriesGrid').innerHTML = incomeCategories.map(cat => `
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span style="font-size: 2.5rem;">${cat.category_icon}</span>
                            <div>
                                <h4 style="font-weight: 700; font-size: 1.125rem;">${cat.category_name}</h4>
                                <p style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">${cat.category_type}</p>
                            </div>
                        </div>
                        ${cat.is_system ? '' : `
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-outline btn-small" onclick="editCategory(${cat.category_id})" style="padding: 0.25rem 0.5rem;">✏️</button>
                            <button class="btn btn-outline btn-small" onclick="deleteCategory(${cat.category_id})" style="padding: 0.25rem 0.5rem; color: var(--danger);">🗑️</button>
                        </div>`}
                    </div>
                    <div>
                        <p style="font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">Subcategories:</p>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            ${cat.subcategories.map(sub => `
                                <span style="padding: 0.25rem 0.75rem; background: rgba(16, 185, 129, 0.1); color: var(--success); font-size: 0.75rem; font-weight: 600; border-radius: 12px;">${sub}</span>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function switchCategoryTab(type) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            document.getElementById('expenseCategories').classList.toggle('hidden', type !== 'expense');
            document.getElementById('incomeCategories').classList.toggle('hidden', type !== 'income');
        }

        function addCategory() {
    const type = document.getElementById('categoryType').value;
    const icon = document.getElementById('categoryIcon').value;
    const name = document.getElementById('categoryName').value;
    const subcats = document.getElementById('categorySubcategories').value;

    if (!icon || !name) {
        alert('Please fill in all fields');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('type', type);
    formData.append('icon', icon);
    formData.append('name', name);
    formData.append('subcategories', subcats);

    fetch('category.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            location.reload(); // ডাটা সেভ হলে শুধুমাত্র ওই ইউজারের লিস্টে এটি যুক্ত হবে
        } else {
            alert(data.message || 'Error adding category');
        }
    })
    .catch(err => {
        alert('Failed to add category');
        console.error(err);
    });
}
function deleteCategory(categoryName) {
            const categoryId = Number(categoryName);
            const cat = appState.categories.find(c => Number(c.category_id) === categoryId);
            if (!cat) return;
            if (cat.is_system) {
                alert('System categories cannot be deleted.');
                return;
            }

            if (!confirm(`Are you sure you want to delete "${cat.category_name}"?`)) return;

            const formData = new FormData();
            formData.append('category_id', String(categoryId));

            fetch('delete_category.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    appState.categories = appState.categories.filter(c => Number(c.category_id) !== categoryId);
                    renderCategories();
                } else {
                    alert(data.message || 'Failed to delete category');
                }
            })
            .catch(err => {
                alert('Failed to delete category');
                console.error(err);
            });
        }

        function editCategory(categoryId) {
            const id = Number(categoryId);
            const cat = appState.categories.find(c => Number(c.category_id) === id);
            if (!cat) return;
            if (cat.is_system) {
                alert('System categories cannot be edited.');
                return;
            }

            appState.editingCategoryId = id;
            document.getElementById('editCategoryType').value = (cat.category_type === 'income') ? 'income' : 'expense';
            document.getElementById('editCategoryIcon').value = cat.category_icon || '';
            document.getElementById('editCategoryName').value = cat.category_name || '';
            openModal('editCategoryModal');
        }

        function saveCategoryEdit() {
            const id = Number(appState.editingCategoryId);
            if (!id) return;

            const type = document.getElementById('editCategoryType').value;
            const icon = document.getElementById('editCategoryIcon').value;
            const name = document.getElementById('editCategoryName').value;

            if (!name) {
                alert('Category name is required');
                return;
            }

            const formData = new FormData();
            formData.append('category_id', String(id));
            formData.append('type', type);
            formData.append('icon', icon);
            formData.append('name', name);

            fetch('update_category.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.category) {
                    const idx = appState.categories.findIndex(c => Number(c.category_id) === id);
                    if (idx >= 0) {
                        appState.categories[idx] = {
                            ...appState.categories[idx],
                            ...data.category
                        };
                    }
                    closeModal('editCategoryModal');
                    renderCategories();
                } else {
                    alert(data.message || 'Failed to update category');
                }
            })
            .catch(err => {
                alert('Failed to update category');
                console.error(err);
            });
        }

        function searchCategories() {
            const searchTerm = document.getElementById('searchCategories').value.toLowerCase();
            
            if (!searchTerm) {
                renderCategories();
                return;
            }

            // সঠিক ফিল্টার লজিক: cat.category_name এবং cat.subcategories চেক করা হচ্ছে
            const filteredCategories = appState.categories.filter(cat => 
                (cat.category_name && cat.category_name.toLowerCase().includes(searchTerm)) || 
                (cat.subcategories && cat.subcategories.some(sub => sub.toLowerCase().includes(searchTerm)))
            );

            // সাময়িকভাবে ফিল্টার করা ডাটা দেখানো
            const originalCategories = [...appState.categories];
            appState.categories = filteredCategories;
            renderCategories();
            appState.categories = originalCategories;
        }

        init();
    </script>
</body>
</html>

      