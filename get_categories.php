<?php
require "db.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$type = isset($_GET['type']) ? strtolower($_GET['type']) : 'income';
$typeDb = $type === 'expense' ? 'Expense' : 'Income';

// spendeeapp.sql schema: categories(category_id,user_id,category_name,category_type)
$sql = "SELECT category_id, category_name FROM categories WHERE user_id = ? AND category_type = ? ORDER BY category_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $typeDb);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];

// Seed defaults if user has no categories yet
if ($result && $result->num_rows === 0) {
    $defaults = [];
    if ($typeDb === 'Expense') {
        $defaults = [
            '🍕 Food & Dining',
            '🛒 Shopping',
            '🚗 Transportation',
            '🏠 Housing',
            '⚡ Bills & Utilities',
            '🏥 Healthcare',
            '🎓 Education',
            '🎬 Entertainment',
        ];
    } else {
        $defaults = [
            '💰 Salary & Wages',
            '💼 Business',
            '📈 Investments',
            '🎁 Gifts Received',
        ];
    }

    $ins = $conn->prepare("INSERT IGNORE INTO categories (user_id, category_name, category_type) VALUES (?, ?, ?)");
    foreach ($defaults as $catName) {
        $ins->bind_param("iss", $user_id, $catName, $typeDb);
        $ins->execute();
    }

    // Re-run query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $typeDb);
    $stmt->execute();
    $result = $stmt->get_result();
}

while ($row = $result->fetch_assoc()) {
    $rawName = (string)$row['category_name'];
    $icon = '📁';
    $name = $rawName;

    // If stored as "🍕 Food & Dining", split icon/name.
    if (preg_match('/^(\X)\s+(.+)$/u', $rawName, $m)) {
        $icon = $m[1];
        $name = $m[2];
    }

    $categories[] = [
        "id" => (string)$row['category_id'],
        "name" => $name,
        "icon" => $icon,
        "subcategories" => []
    ];
}

echo json_encode($categories);