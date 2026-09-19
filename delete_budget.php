<?php
session_start();
header('Content-Type: application/json');
require 'db.php';
require_once 'notification_util.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$budget_item_id = (int)($data['budget_id'] ?? 0);

if ($budget_item_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid budget ID']);
    exit;
}

$conn->begin_transaction();

try {
    // Verify ownership and get budget_id
    $verify = $conn->prepare("
        SELECT bi.budget_item_id, bi.budget_id, b.user_id 
        FROM budget_items bi 
        JOIN budgets b ON b.budget_id = bi.budget_id 
        WHERE bi.budget_item_id = ? 
        LIMIT 1
    ");
    $verify->bind_param("i", $budget_item_id);
    $verify->execute();
    $result = $verify->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Budget not found');
    }
    
    $row = $result->fetch_assoc();
    
    if ((int)$row['user_id'] !== $user_id) {
        throw new Exception('Unauthorized');
    }
    
    $budget_id = (int)$row['budget_id'];
    
    // Delete budget_item first (child record)
    $deleteBudgetItem = $conn->prepare("DELETE FROM budget_items WHERE budget_item_id = ?");
    $deleteBudgetItem->bind_param("i", $budget_item_id);
    
    if (!$deleteBudgetItem->execute()) {
        throw new Exception('Failed to delete budget item');
    }
    
    // Check if this budget has any other budget_items
    $checkOthers = $conn->prepare("SELECT COUNT(*) as count FROM budget_items WHERE budget_id = ?");
    $checkOthers->bind_param("i", $budget_id);
    $checkOthers->execute();
    $countResult = $checkOthers->get_result();
    $count = (int)$countResult->fetch_assoc()['count'];
    
    // If no other budget_items exist, delete the parent budget record
    if ($count === 0) {
        $deleteBudget = $conn->prepare("DELETE FROM budgets WHERE budget_id = ? AND user_id = ?");
        $deleteBudget->bind_param("ii", $budget_id, $user_id);
        $deleteBudget->execute();
    }
    
    $conn->commit();

    // Add notification after successful commit
    try {
        error_log("Attempting to add delete notification for user_id: $user_id");
        add_notification(
            $conn,
            $user_id,
            'Budget deleted',
            "Budget goal deleted successfully",
            'budget'
        );
        error_log("Delete notification added successfully");
    } catch (Exception $notifError) {
        error_log("Delete notification error: " . $notifError->getMessage());
    }

    echo json_encode(['success' => true]);
    exit;
    
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Failed to delete budget: ' . $e->getMessage()]);
    exit;
}
