<?php

declare(strict_types=1);

function has_column(mysqli $conn, string $table, string $column): bool {
    $tableEsc = $conn->real_escape_string($table);
    $colEsc = $conn->real_escape_string($column);

    $sql = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_membership_column(mysqli $conn): void {
    if (has_column($conn, 'users', 'membership')) {
        return;
    }

    // Try to add column for existing DBs. If this fails (permissions), we still operate with fallback 'general'.
    $conn->query("ALTER TABLE users ADD COLUMN membership ENUM('general','premium') NOT NULL DEFAULT 'general'");
}

/**
 * Ensure budgets can optionally link to a wallet (savings progress uses wallet balance).
 */
function ensure_budget_wallet_column(mysqli $conn): void {
    if (has_column($conn, 'budget_items', 'wallet_id')) {
        return;
    }

    // Add nullable wallet_id with FK to wallets
    $conn->query("ALTER TABLE budget_items ADD COLUMN wallet_id INT NULL AFTER category_id");
    $conn->query("ALTER TABLE budget_items ADD CONSTRAINT fk_budget_items_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(wallet_id)");
    $conn->query("CREATE INDEX idx_budget_items_wallet ON budget_items(wallet_id)");
}

/**
 * Ensure system flag exists on categories and provide seeding helper.
 */
function ensure_category_system_column(mysqli $conn): void {
    if (has_column($conn, 'categories', 'is_system')) {
        return;
    }
    $conn->query("ALTER TABLE categories ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER category_type");
    $conn->query("CREATE INDEX idx_categories_system ON categories(is_system)");
}

/** Ensure subcategories table exists */
function ensure_subcategories_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS subcategories (
        subcategory_id INT PRIMARY KEY AUTO_INCREMENT,
        category_id INT NOT NULL,
        name VARCHAR(120) NOT NULL,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE,
        UNIQUE (category_id, name)
    ) ENGINE=InnoDB");
}

/**
 * Seed initial default categories for a user, marked as system (non-editable),
 * and populate default subcategories.
 */
function seed_initial_categories_for_user(mysqli $conn, int $user_id): void {
    ensure_subcategories_table($conn);
    // Initial set (Expense & Income). Icons are stored by prefixing emoji in name
    $defaults = [
        // Expense categories
        ['name' => '🍕 Food & Dining', 'type' => 'Expense', 'subs' => ['Restaurant','Fast Food','Groceries','Coffee Shop','Bakery']],
        ['name' => '🛒 Shopping', 'type' => 'Expense', 'subs' => ['Clothing','Electronics','Books','Gifts']],
        ['name' => '🚗 Transportation', 'type' => 'Expense', 'subs' => ['Bus','Train','Uber','Fuel','Parking']],
        ['name' => '🏠 Housing', 'type' => 'Expense', 'subs' => ['Rent','Mortgage','Property Tax']],
        ['name' => '⚡ Bills & Utilities', 'type' => 'Expense', 'subs' => ['Electricity','Water','Internet','Phone']],
        ['name' => '🏥 Healthcare', 'type' => 'Expense', 'subs' => ['Doctor','Pharmacy','Hospital']],
        ['name' => '🎓 Education', 'type' => 'Expense', 'subs' => ['Tuition','Books','Courses']],
        ['name' => '🎬 Entertainment', 'type' => 'Expense', 'subs' => ['Movies','Games','Streaming']],
        // Income categories
        ['name' => '💼 Business', 'type' => 'Income', 'subs' => ['Sales','Services','Consulting']],
        ['name' => '🎁 Gifts Received', 'type' => 'Income', 'subs' => ['Birthday','Festival','Cash Gift']],
        ['name' => '📈 Investments', 'type' => 'Income', 'subs' => ['Dividends','Capital Gains','Interest']],
        ['name' => '💰 Salary & Wages', 'type' => 'Income', 'subs' => ['Base Salary','Bonus','Overtime','Freelance']],
    ];

    foreach ($defaults as $d) {
        $typeDb = $d['type'];
        $name = $conn->real_escape_string($d['name']);
        // Check if category already exists for this user
        $sel = $conn->prepare("SELECT category_id FROM categories WHERE user_id = ? AND category_name = ? AND category_type = ? LIMIT 1");
        $sel->bind_param("iss", $user_id, $name, $typeDb);
        $sel->execute();
        $res = $sel->get_result();
        if ($res && $res->num_rows > 0) {
            $cid = (int)$res->fetch_assoc()['category_id'];
        } else {
            // Insert only if not exists
            $ins = $conn->prepare("INSERT INTO categories (user_id, category_name, category_type, is_system) VALUES (?, ?, ?, 1)");
            $ins->bind_param("iss", $user_id, $name, $typeDb);
            if (!$ins->execute()) {
                // If insert fails due to duplicate, fetch the id
                $sel2 = $conn->prepare("SELECT category_id FROM categories WHERE user_id = ? AND category_name = ? AND category_type = ? LIMIT 1");
                $sel2->bind_param("iss", $user_id, $name, $typeDb);
                $sel2->execute();
                $res2 = $sel2->get_result();
                if ($res2 && $res2->num_rows > 0) {
                    $cid = (int)$res2->fetch_assoc()['category_id'];
                } else {
                    continue; // Skip this category if insert failed
                }
            } else {
                $cid = (int)$conn->insert_id;
            }
        }
        // Seed subcategories for this category (using INSERT IGNORE to prevent duplicates)
        foreach ($d['subs'] as $sub) {
            $subIns = $conn->prepare("INSERT IGNORE INTO subcategories (category_id, name, is_system) VALUES (?, ?, 1)");
            $subIns->bind_param("is", $cid, $sub);
            $subIns->execute();
        }
    }
}
/**
 * Ensure manual progress column exists so users can add budget funds manually.
 */
function ensure_budget_manual_column(mysqli $conn): void {
    if (has_column($conn, 'budget_items', 'manual_progress')) {
        return;
    }

    $conn->query("ALTER TABLE budget_items ADD COLUMN manual_progress DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER currency_code");
}

/**
 * Ensure budget_items can distinguish between savings goals vs monthly spending caps.
 * - goal: existing behavior (wallet-linked saving goal)
 * - spend: monthly spending budget (progress = spent amount)
 */
function ensure_budget_mode_column(mysqli $conn): void {
    if (has_column($conn, 'budget_items', 'budget_mode')) {
        return;
    }

    // Store the mode on each budget item (backwards compatible: defaults to goal)
    $conn->query("ALTER TABLE budget_items ADD COLUMN budget_mode ENUM('goal','spend') NOT NULL DEFAULT 'goal' AFTER wallet_id");
    $conn->query("CREATE INDEX idx_budget_items_mode ON budget_items(budget_mode)");
}
