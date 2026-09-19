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

ensure_category_system_column($conn);
ensure_subcategories_table($conn);

if ($category_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
    exit;
}

// Prevent delete if linked to any budget for this user
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

$info = $conn->prepare("SELECT category_id, is_system FROM categories WHERE category_id = ? AND user_id = ? LIMIT 1");
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
    echo json_encode(['status' => 'error', 'message' => 'System categories cannot be deleted']);
    exit;
}

$conn->begin_transaction();

try {
    // Unlink from transactions to avoid FK issues / preserve history
    $unlink = $conn->prepare("UPDATE transactions SET category_id = NULL WHERE user_id = ? AND category_id = ?");
    $unlink->bind_param("ii", $user_id, $category_id);
    if (!$unlink->execute()) {
        throw new Exception('Failed to unlink transactions');
    }

    // Delete subcategories (if table exists)
    $delSubs = $conn->prepare("DELETE FROM subcategories WHERE category_id = ?");
    $delSubs->bind_param("i", $category_id);
    if (!$delSubs->execute()) {
        throw new Exception('Failed to delete subcategories');
    }

    // Delete the category
    $del = $conn->prepare("DELETE FROM categories WHERE category_id = ? AND user_id = ? AND (is_system IS NULL OR is_system = 0)");
    $del->bind_param("ii", $category_id, $user_id);
    if (!$del->execute()) {
        throw new Exception('Failed to delete category');
    }

    if ($del->affected_rows < 1) {
        throw new Exception('Category could not be deleted');
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
