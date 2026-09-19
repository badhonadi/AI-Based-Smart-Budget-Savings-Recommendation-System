<?php
require "db.php";
require_once 'schema.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$name = trim((string)($_POST['name'] ?? ''));
$icon = trim((string)($_POST['icon'] ?? ''));
$type = strtolower((string)($_POST['type'] ?? ''));

if ($category_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
    exit;
}

ensure_category_system_column($conn);

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Category name is required']);
    exit;
}

$typeDb = $type === 'income' ? 'Income' : 'Expense';

// Prevent update if linked to any budget for this user
$budgetCheck = $conn->prepare(
    "SELECT 1
     FROM budget_items bi
     JOIN budgets b ON b.budget_id = bi.budget_id
     WHERE b.user_id = ? AND bi.category_id = ?
     LIMIT 1"
);
$budgetCheck->bind_param("ii", $user_id, $category_id);
$budgetCheck->execute();
$budgetRes = $budgetCheck->get_result();
if ($budgetRes && $budgetRes->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'This category is linked to a budget. Remove it from the budget first.']);
    exit;
}

$info = $conn->prepare("SELECT category_id, category_name, category_type, is_system FROM categories WHERE category_id = ? AND user_id = ? LIMIT 1");
$info->bind_param("ii", $category_id, $user_id);
$info->execute();
$infoRes = $info->get_result();
if (!$infoRes || $infoRes->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Category not found']);
    exit;
}
$row = $infoRes->fetch_assoc();
$is_system = (int)($row['is_system'] ?? 0) === 1;
if ($is_system) {
    echo json_encode(['status' => 'error', 'message' => 'System categories cannot be edited']);
    exit;
}

$storedName = $icon !== '' ? ($icon . ' ' . $name) : $name;

// Uniqueness check (within same user + type)
$check = $conn->prepare(
    "SELECT 1 FROM categories
     WHERE user_id = ? AND category_name = ? AND category_type = ? AND category_id <> ?
     LIMIT 1"
);
$check->bind_param("issi", $user_id, $storedName, $typeDb, $category_id);
$check->execute();
$checkRes = $check->get_result();
if ($checkRes && $checkRes->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Category name exists, use a different name']);
    exit;
}

$upd = $conn->prepare("UPDATE categories SET category_name = ?, category_type = ? WHERE category_id = ? AND user_id = ? AND (is_system IS NULL OR is_system = 0)");
$upd->bind_param("ssii", $storedName, $typeDb, $category_id, $user_id);

if (!$upd->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update category']);
    exit;
}

// Return split icon/name for UI consistency
$outIcon = '📁';
$outName = $storedName;
if (preg_match('/^(\X)\s+(.+)$/u', $storedName, $m)) {
    $outIcon = $m[1];
    $outName = $m[2];
}

echo json_encode([
    'status' => 'success',
    'category' => [
        'category_id' => $category_id,
        'category_icon' => $outIcon,
        'category_name' => $outName,
        'category_type' => strtolower($typeDb),
    ]
]);
